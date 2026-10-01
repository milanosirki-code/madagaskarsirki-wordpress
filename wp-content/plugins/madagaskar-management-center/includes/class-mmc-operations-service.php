<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Operations_Service {
    private static $handling_log = false;

    public static function hooks() {
        add_action( 'mmc_program_logged', array( __CLASS__, 'on_program_logged' ), 20, 7 );
    }

    public static function on_program_logged( $program_id, $action, $entity_type, $entity_id, $old_value, $new_value, $note ) {
        if ( self::$handling_log || ! $program_id ) { return; }
        $action = sanitize_key( $action );
        $seed_actions = array( 'venue_confirmed','event_draft_created','event_updated','session_created','session_deleted','sales_readiness_passed','sales_integration_updated' );
        $should_seed = in_array( $action, $seed_actions, true );
        if ( 'program_status_changed' === $action && in_array( (string) $new_value, array( 'operations','show_day' ), true ) ) { $should_seed = true; }
        if ( ! $should_seed ) { return; }

        self::$handling_log = true;
        self::ensure_plan( $program_id );
        self::sync_schedule_from_event( $program_id );
        self::$handling_log = false;
    }

    public static function backfill_existing_programs() {
        if ( ! class_exists( 'MMC_Program_Service' ) ) { return; }
        foreach ( (array) MMC_Program_Service::all_programs() as $program ) {
            if ( in_array( (string)$program->status, array( 'completed','cancelled' ), true ) ) { continue; }
            self::ensure_plan( (int)$program->id );
            self::sync_schedule_from_event( (int)$program->id );
        }
    }

    public static function operation_modes() {
        return array(
            'undecided' => 'Henüz Belirlenmedi',
            'daytrip'   => 'Günübirlik / Ankara Dönüş',
            'overnight' => 'Konaklamalı',
            'multi_city'=> 'Sonraki Şehre Devam',
        );
    }

    public static function plan_statuses() {
        return array(
            'draft'       => 'Taslak',
            'planned'     => 'Planlandı',
            'ready'       => 'Operasyona Hazır',
            'in_progress' => 'Operasyon Devam Ediyor',
            'completed'   => 'Operasyon Tamamlandı',
            'cancelled'   => 'İptal',
        );
    }

    public static function resource_types() {
        return array(
            'vehicle'   => 'Araç',
            'person'    => 'Personel',
            'artist'    => 'Sanatçı',
            'equipment' => 'Ekipman',
            'service'   => 'Hizmet / Tedarik',
        );
    }

    public static function assignment_statuses() {
        return array(
            'planned'    => 'Planlandı',
            'confirmed'  => 'Teyit Edildi',
            'checked_in' => 'Hazır / Giriş Yaptı',
            'completed'  => 'Tamamlandı',
            'cancelled'  => 'İptal',
        );
    }

    public static function checklist_statuses() {
        return array(
            'pending'        => 'Bekliyor',
            'ready'          => 'Hazır',
            'done'           => 'Tamamlandı',
            'not_applicable' => 'Gerekli Değil',
            'problem'        => 'Sorun Var',
        );
    }

    public static function phases() {
        return array(
            'pre_departure' => '1. Hareket Öncesi',
            'venue_setup'   => '2. Salon / Kurulum',
            'show_day'      => '3. Gösteri Günü',
            'post_show'     => '4. Gösteri Sonrası / Teslim',
        );
    }

    public static function ensure_plan( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { return new WP_Error( 'mmc_ops_program_missing', 'Program bulunamadı.' ); }
        $table = $wpdb->prefix . 'mmc_operation_plans';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE program_id=%d LIMIT 1", $program_id ) );
        if ( $row ) {
            self::seed_checklist( $program_id );
            self::ensure_operation_task( $program_id );
            return $row;
        }

        $now = current_time( 'mysql' );
        $wpdb->insert( $table, array(
            'program_id' => $program_id,
            'operation_mode' => 'undecided',
            'origin_city' => 'Ankara',
            'status' => 'draft',
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ) );
        if ( ! $wpdb->insert_id ) { return new WP_Error( 'mmc_ops_plan_insert', 'Operasyon planı oluşturulamadı.' ); }
        self::seed_checklist( $program_id );
        self::ensure_operation_task( $program_id );
        MMC_Program_Service::add_log( $program_id, 'operations_plan_created', 'operations', (int) $wpdb->insert_id, null, array( 'origin_city'=>'Ankara' ), 'Operasyon planı otomatik oluşturuldu.' );
        return self::get_plan( $program_id );
    }

    public static function get_plan( $program_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_operation_plans WHERE program_id=%d LIMIT 1", absint($program_id) ) );
    }

    public static function save_plan( $program_id, $data ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $plan = self::ensure_plan( $program_id );
        if ( is_wp_error( $plan ) ) { return $plan; }

        $mode = sanitize_key( $data['operation_mode'] ?? 'undecided' );
        if ( ! isset( self::operation_modes()[ $mode ] ) ) { $mode = 'undecided'; }
        $status = sanitize_key( $data['status'] ?? 'draft' );
        if ( ! isset( self::plan_statuses()[ $status ] ) ) { $status = 'draft'; }
        $accommodation_required = ! empty( $data['accommodation_required'] ) ? 1 : 0;
        if ( 'daytrip' === $mode ) { $accommodation_required = 0; }
        if ( in_array( $mode, array('overnight','multi_city'), true ) ) { $accommodation_required = 1; }

        $payload = array(
            'operation_mode' => $mode,
            'origin_city' => sanitize_text_field( $data['origin_city'] ?? 'Ankara' ),
            'next_destination' => sanitize_text_field( $data['next_destination'] ?? '' ),
            'departure_at' => self::datetime_or_null( $data['departure_at'] ?? '' ),
            'venue_entry_at' => self::datetime_or_null( $data['venue_entry_at'] ?? '' ),
            'setup_start_at' => self::datetime_or_null( $data['setup_start_at'] ?? '' ),
            'rehearsal_at' => self::datetime_or_null( $data['rehearsal_at'] ?? '' ),
            'doors_open_at' => self::datetime_or_null( $data['doors_open_at'] ?? '' ),
            'teardown_end_at' => self::datetime_or_null( $data['teardown_end_at'] ?? '' ),
            'return_at' => self::datetime_or_null( $data['return_at'] ?? '' ),
            'accommodation_required' => $accommodation_required,
            'lodging_name' => sanitize_text_field( $data['lodging_name'] ?? '' ),
            'lodging_address' => sanitize_textarea_field( $data['lodging_address'] ?? '' ),
            'lodging_rooms' => absint( $data['lodging_rooms'] ?? 0 ),
            'lodging_cost' => self::money( $data['lodging_cost'] ?? 0 ),
            'meal_plan' => sanitize_textarea_field( $data['meal_plan'] ?? '' ),
            'meal_cost' => self::money( $data['meal_cost'] ?? 0 ),
            'transport_cost' => self::money( $data['transport_cost'] ?? 0 ),
            'other_cost' => self::money( $data['other_cost'] ?? 0 ),
            'status' => $status,
            'notes' => sanitize_textarea_field( $data['notes'] ?? '' ),
            'updated_at' => current_time( 'mysql' ),
        );
        $ok = $wpdb->update( $wpdb->prefix . 'mmc_operation_plans', $payload, array( 'program_id'=>$program_id ) );
        if ( false === $ok ) { return new WP_Error( 'mmc_ops_plan_update', 'Operasyon planı güncellenemedi.' ); }

        self::apply_accommodation_checklist_rule( $program_id, $accommodation_required );
        self::sync_schedule_from_event( $program_id );
        self::sync_operation_finance( $program_id );
        if ( 'ready' === $status ) {
            $program = MMC_Program_Service::get_program( $program_id );
            if ( $program && ! in_array( $program->status, array('show_day','financial_close','deposit_refund','completed','cancelled'), true ) ) {
                MMC_Program_Service::set_status( $program_id, 'operations', 'Operasyon planı hazır olarak işaretlendi.' );
            }
        }
        MMC_Program_Service::add_log( $program_id, 'operations_plan_updated', 'operations', (int)$plan->id, null, $payload, 'Operasyon ve lojistik planı güncellendi.' );
        return true;
    }

    public static function resources( $type = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_resources';
        if ( $type && isset( self::resource_types()[ $type ] ) ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE resource_type=%s AND is_active=1 ORDER BY resource_name", $type ) );
        }
        return $wpdb->get_results( "SELECT * FROM $table WHERE is_active=1 ORDER BY resource_type,resource_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function add_resource( $data ) {
        global $wpdb;
        $type = sanitize_key( $data['resource_type'] ?? '' );
        $name = sanitize_text_field( $data['resource_name'] ?? '' );
        if ( ! isset( self::resource_types()[ $type ] ) || ! $name ) { return new WP_Error( 'mmc_ops_resource_invalid', 'Kaynak türü ve adı zorunludur.' ); }
        $table = $wpdb->prefix . 'mmc_resources';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE resource_type=%s AND resource_name=%s AND identifier=%s LIMIT 1", $type, $name, sanitize_text_field($data['identifier'] ?? '') ) );
        if ( $exists ) { return (int)$exists; }
        $now = current_time( 'mysql' );
        $wpdb->insert( $table, array(
            'resource_type'=>$type,
            'resource_name'=>$name,
            'subtype'=>sanitize_text_field( $data['subtype'] ?? '' ),
            'identifier'=>sanitize_text_field( $data['identifier'] ?? '' ),
            'country'=>sanitize_text_field( $data['country'] ?? '' ),
            'notes'=>sanitize_textarea_field( $data['notes'] ?? '' ),
            'is_active'=>1,
            'created_by'=>get_current_user_id() ?: null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ) );
        return $wpdb->insert_id ? (int)$wpdb->insert_id : new WP_Error( 'mmc_ops_resource_insert', 'Kaynak eklenemedi.' );
    }

    public static function assign_resource( $program_id, $resource_id, $data = array() ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $resource_id = absint( $resource_id );
        $resource = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_resources WHERE id=%d LIMIT 1", $resource_id ) );
        if ( ! $resource ) { return new WP_Error( 'mmc_ops_resource_missing', 'Kaynak bulunamadı.' ); }
        self::ensure_plan( $program_id );
        $table = $wpdb->prefix . 'mmc_program_resources';
        $now = current_time( 'mysql' );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE program_id=%d AND resource_id=%d LIMIT 1", $program_id, $resource_id ) );
        $payload = array(
            'resource_type'=>$resource->resource_type,
            'resource_name'=>$resource->resource_name,
            'role_name'=>sanitize_text_field( $data['role_name'] ?? '' ),
            'quantity'=>max(1,absint($data['quantity'] ?? 1)),
            'status'=>sanitize_key( $data['status'] ?? 'planned' ),
            'notes'=>sanitize_textarea_field( $data['notes'] ?? '' ),
            'updated_at'=>$now,
        );
        if ( ! isset( self::assignment_statuses()[ $payload['status'] ] ) ) { $payload['status']='planned'; }
        if ( $existing ) {
            $wpdb->update( $table, $payload, array('id'=>(int)$existing) );
            $id=(int)$existing;
        } else {
            $payload['program_id']=$program_id; $payload['resource_id']=$resource_id; $payload['created_at']=$now;
            $wpdb->insert( $table, $payload ); $id=(int)$wpdb->insert_id;
        }
        MMC_Program_Service::add_log( $program_id, 'operations_resource_assigned', 'operation_resource', $id, null, array('type'=>$resource->resource_type,'name'=>$resource->resource_name), 'Operasyon kaynağı programa atandı.' );
        return $id;
    }

    public static function program_resources( $program_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_program_resources WHERE program_id=%d ORDER BY resource_type,resource_name", absint($program_id) ) );
    }

    public static function update_assignment( $program_id, $assignment_id, $data ) {
        global $wpdb;
        $program_id=absint($program_id); $assignment_id=absint($assignment_id);
        $status=sanitize_key($data['status']??'planned'); if(!isset(self::assignment_statuses()[$status])){$status='planned';}
        $payload=array(
            'role_name'=>sanitize_text_field($data['role_name']??''),
            'quantity'=>max(1,absint($data['quantity']??1)),
            'status'=>$status,
            'notes'=>sanitize_textarea_field($data['notes']??''),
            'check_in_at'=>self::datetime_or_null($data['check_in_at']??''),
            'check_out_at'=>self::datetime_or_null($data['check_out_at']??''),
            'updated_at'=>current_time('mysql'),
        );
        $ok=$wpdb->update($wpdb->prefix.'mmc_program_resources',$payload,array('id'=>$assignment_id,'program_id'=>$program_id));
        return false===$ok?new WP_Error('mmc_ops_assignment_update','Atama güncellenemedi.'):true;
    }

    public static function checklist( $program_id, $phase = '' ) {
        global $wpdb;
        $table=$wpdb->prefix.'mmc_operation_checklist';
        if($phase && isset(self::phases()[$phase])){
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d AND phase=%s ORDER BY sort_order,id",absint($program_id),$phase));
        }
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d ORDER BY FIELD(phase,'pre_departure','venue_setup','show_day','post_show'),sort_order,id",absint($program_id)));
    }

    public static function seed_checklist( $program_id ) {
        global $wpdb;
        $program_id=absint($program_id); $table=$wpdb->prefix.'mmc_operation_checklist'; $now=current_time('mysql');
        $items=self::checklist_template();
        foreach($items as $item){
            $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND item_key=%s LIMIT 1",$program_id,$item[1]));
            if($exists) continue;
            $wpdb->insert($table,array(
                'program_id'=>$program_id,'phase'=>$item[0],'item_key'=>$item[1],'title'=>$item[2],
                'is_required'=>$item[3],'sort_order'=>$item[4],'status'=>'pending','created_at'=>$now,'updated_at'=>$now,
            ));
        }
        $plan=self::get_plan($program_id);
        if($plan){self::apply_accommodation_checklist_rule($program_id,(int)$plan->accommodation_required);}
    }

    private static function checklist_template() {
        return array(
            array('pre_departure','vehicles_ready','Araçlar hazır ve yakıt/temel kontrol tamam',1,10),
            array('pre_departure','drivers_confirmed','Şoför / sürücüler teyit edildi',1,20),
            array('pre_departure','route_confirmed','Rota ve hareket saati teyit edildi',1,30),
            array('pre_departure','artists_complete','Sanatçı kadrosu tam',1,40),
            array('pre_departure','crew_complete','Operasyon / gişe / saha personeli tam',1,50),
            array('pre_departure','costumes_loaded','Kostümler kontrol edilip yüklendi',1,60),
            array('pre_departure','equipment_loaded','Gösteri ekipmanı ve materyaller yüklendi',1,70),
            array('pre_departure','venue_entry_confirmed','Salon giriş saati ve yetkili kişi teyit edildi',1,80),
            array('pre_departure','lodging_confirmed','Konaklama rezervasyonu / oda dağılımı tamam',0,90),
            array('pre_departure','meal_plan_confirmed','Öğle/akşam yemek planı tamam',1,100),
            array('pre_departure','staff_badges_ready','Personel görev kartı / yaka kartları hazır',0,110),
            array('pre_departure','cash_change_ready','Gişe için para üstü / kasa hazırlığı tamam',0,120),
            array('pre_departure','cleaning_supplies_ready','Temizlik malzemeleri hazır',0,130),
            array('venue_setup','venue_handover_in','Salon giriş teslimi / alan kontrolü yapıldı',1,10),
            array('venue_setup','stage_decor_ready','Sahne / dekor / arka fon hazır',1,20),
            array('venue_setup','sound_ready','Ses sistemi hazır ve test edildi',1,30),
            array('venue_setup','lighting_ready','Işık sistemi hazır ve test edildi',1,40),
            array('venue_setup','backstage_ready','Kulis / sanatçı hazırlık alanı hazır',1,50),
            array('venue_setup','computer_audio_test','Bilgisayar / müzik / ses geçiş testi tamam',1,60),
            array('venue_setup','pos_ready','POS cihazları çalışıyor',1,70),
            array('venue_setup','qr_ready','QR / check-in cihazları çalışıyor',1,80),
            array('venue_setup','box_office_ready','Gişe alanı ve bilet satış personeli hazır',1,90),
            array('venue_setup','security_ready','Güvenlik / giriş kontrol personeli hazır',1,100),
            array('venue_setup','cleaning_staff_ready','Temizlik personeli hazır',0,110),
            array('venue_setup','seating_staff_ready','Salon yerleştirme / yönlendirme personeli hazır',1,120),
            array('venue_setup','concession_ready','Mısır / balon / su / satış masaları hazır',0,130),
            array('venue_setup','signage_ready','Afiş / salon içi yönlendirmeler hazır',1,140),
            array('venue_setup','tables_chairs_ready','Gişe / satış için masa ve sandalyeler hazır',0,150),
            array('show_day','staff_briefing_done','Görev dağılımı ve personel brifingi tamam',1,10),
            array('show_day','artist_warmup_done','Sanatçı ısınması tamam',1,20),
            array('show_day','rehearsal_done','Prova / sahne ölçüsü / müzik geçişleri tamam',1,30),
            array('show_day','sales_status_checked','Seans satış ve doluluk durumu kontrol edildi',1,40),
            array('show_day','doors_open_ready','Kapı açılışı / seyirci karşılama hazır',1,50),
            array('show_day','session_flow_ready','Gösteri akışı / sunucu / numara sırası hazır',1,60),
            array('post_show','audience_exit_done','Seyirci tahliyesi güvenli şekilde tamamlandı',1,10),
            array('post_show','teardown_done','Sahne / dekor / ekipman sökümü tamamlandı',1,20),
            array('post_show','costume_count_done','Kostüm sayımı ve teslimi tamamlandı',1,30),
            array('post_show','equipment_count_done','Ekipman sayımı tamamlandı',1,40),
            array('post_show','vehicle_load_done','Araç yükleme tamamlandı',1,50),
            array('post_show','venue_cleaning_done','Salon temizliği tamamlandı',1,60),
            array('post_show','venue_handover_completed','Salon çıkış teslimi tamamlandı',1,70),
            array('post_show','sales_reconciliation_done','Günlük satış / gişe mutabakatı tamamlandı',1,80),
            array('post_show','return_departure_done','Ankara dönüş / sonraki şehre hareket tamamlandı',1,90),
        );
    }

    public static function save_checklist_rows( $program_id, $rows ) {
        global $wpdb;
        $program_id=absint($program_id); $table=$wpdb->prefix.'mmc_operation_checklist'; $statuses=self::checklist_statuses();
        foreach((array)$rows as $id=>$row){
            $id=absint($id); if(!$id)continue;
            $current=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND program_id=%d LIMIT 1",$id,$program_id));
            if(!$current)continue;
            $status=sanitize_key($row['status']??$current->status); if(!isset($statuses[$status]))$status=$current->status;
            $completed='done'===$status?($current->completed_at?:current_time('mysql')):null;
            $wpdb->update($table,array(
                'status'=>$status,
                'assigned_name'=>sanitize_text_field($row['assigned_name']??''),
                'notes'=>sanitize_textarea_field($row['notes']??''),
                'completed_at'=>$completed,
                'updated_at'=>current_time('mysql'),
            ),array('id'=>$id,'program_id'=>$program_id));
            if('venue_handover_completed'===$current->item_key && 'done'===$status){self::ensure_close_tasks($program_id);}
        }
        self::recalculate_plan_status($program_id);
        MMC_Program_Service::add_log($program_id,'operations_checklist_updated','operations',$program_id,null,null,'Operasyon kontrol listesi güncellendi.');
        return true;
    }

    public static function summary( $program_id ) {
        global $wpdb;
        $program_id=absint($program_id); self::ensure_plan($program_id);
        $check=$wpdb->prefix.'mmc_operation_checklist'; $res=$wpdb->prefix.'mmc_program_resources';
        $all=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total, SUM(status='done') done_count, SUM(status='problem') problems FROM $check WHERE program_id=%d AND status<>'not_applicable'",$program_id),ARRAY_A);
        $pre=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total, SUM(status='done') done_count, SUM(status='problem') problems FROM $check WHERE program_id=%d AND phase IN ('pre_departure','venue_setup') AND is_required=1 AND status<>'not_applicable'",$program_id),ARRAY_A);
        $counts=$wpdb->get_results($wpdb->prepare("SELECT resource_type,COUNT(*) total FROM $res WHERE program_id=%d AND status<>'cancelled' GROUP BY resource_type",$program_id),OBJECT_K);
        $pre_total=(int)($pre['total']??0); $pre_done=(int)($pre['done_count']??0);
        return array(
            'check_total'=>(int)($all['total']??0),'check_done'=>(int)($all['done_count']??0),'problems'=>(int)($all['problems']??0),
            'pre_total'=>$pre_total,'pre_done'=>$pre_done,'pre_percent'=>$pre_total?round(100*$pre_done/$pre_total,1):0,
            'vehicles'=>isset($counts['vehicle'])?(int)$counts['vehicle']->total:0,
            'artists'=>isset($counts['artist'])?(int)$counts['artist']->total:0,
            'people'=>isset($counts['person'])?(int)$counts['person']->total:0,
            'equipment'=>isset($counts['equipment'])?(int)$counts['equipment']->total:0,
        );
    }

    public static function schedule( $program_id ) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_operation_schedule WHERE program_id=%d ORDER BY start_at IS NULL,start_at,sort_order,id",absint($program_id)));
    }

    public static function sync_schedule_from_event( $program_id ) {
        global $wpdb;
        $program_id=absint($program_id); $plan=self::ensure_plan($program_id); if(is_wp_error($plan))return $plan;
        $table=$wpdb->prefix.'mmc_operation_schedule'; $now=current_time('mysql');
        $system=array(
            'departure'=>array('departure','Hareket / Yola Çıkış',$plan->departure_at,10),
            'venue_entry'=>array('venue_entry','Salona Giriş',$plan->venue_entry_at,20),
            'setup'=>array('setup','Kurulum Başlangıcı',$plan->setup_start_at,30),
            'rehearsal'=>array('rehearsal','Prova / Ses Kontrol',$plan->rehearsal_at,40),
            'doors_open'=>array('doors_open','Kapı Açılışı',$plan->doors_open_at,50),
            'teardown'=>array('teardown','Söküm Tamamlanması',$plan->teardown_end_at,900),
            'return'=>array('return','Dönüş / Sonraki Şehre Hareket',$plan->return_at,910),
        );
        foreach($system as $key=>$v){
            if(!$v[2])continue;
            self::upsert_schedule($program_id,'plan_'.$key,0,$v[0],$v[1],$v[2],null,$v[3],1);
        }
        $event=class_exists('MMC_Event_Service')?MMC_Event_Service::event_for_program($program_id):null;
        $valid_keys=array();
        if($event){
            $sessions=MMC_Event_Service::sessions($event->id);
            foreach($sessions as $s){
                $key='session_'.(int)$s->id; $valid_keys[]=$key;
                $start=$s->session_time;
                $local_start=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$start,wp_timezone());
                $end=$local_start?$local_start->modify('+60 minutes')->format('Y-m-d H:i:s'):null;
                self::upsert_schedule($program_id,$key,(int)$s->id,'show','Gösteri '.substr($start,11,5),$start,$end,100+(int)$s->id,1);
            }
        }
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,source_key FROM $table WHERE program_id=%d AND is_system=1 AND source_key LIKE 'session_%%'",$program_id));
        foreach($rows as $r){if(!in_array($r->source_key,$valid_keys,true)){$wpdb->delete($table,array('id'=>(int)$r->id));}}
        return true;
    }

    public static function add_schedule_item( $program_id, $data ) {
        $program_id=absint($program_id); self::ensure_plan($program_id);
        $title=sanitize_text_field($data['title']??''); $start=self::datetime_or_null($data['start_at']??'');
        if(!$title)return new WP_Error('mmc_ops_schedule_title','Akış başlığı zorunludur.');
        $key='manual_'.strtolower(wp_generate_password(10,false,false));
        return self::upsert_schedule($program_id,$key,0,sanitize_key($data['item_type']??'other'),$title,$start,self::datetime_or_null($data['end_at']??''),500,0,sanitize_text_field($data['assigned_name']??''),sanitize_textarea_field($data['notes']??''));
    }

    public static function update_schedule_status( $program_id, $id, $status ) {
        global $wpdb; $allowed=array('planned','ready','done','cancelled'); $status=sanitize_key($status); if(!in_array($status,$allowed,true))$status='planned';
        $ok=$wpdb->update($wpdb->prefix.'mmc_operation_schedule',array('status'=>$status,'updated_at'=>current_time('mysql')),array('id'=>absint($id),'program_id'=>absint($program_id)));
        return false===$ok?new WP_Error('mmc_ops_schedule_status','Akış durumu güncellenemedi.'):true;
    }

    private static function upsert_schedule( $program_id,$source_key,$session_id,$item_type,$title,$start_at,$end_at,$sort_order,$is_system,$assigned_name='',$notes='' ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_operation_schedule'; $now=current_time('mysql');
        $id=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND source_key=%s LIMIT 1",$program_id,$source_key));
        $payload=array('session_id'=>$session_id?:null,'item_type'=>$item_type,'title'=>$title,'start_at'=>$start_at,'end_at'=>$end_at,'sort_order'=>$sort_order,'is_system'=>$is_system,'assigned_name'=>$assigned_name,'notes'=>$notes,'updated_at'=>$now);
        if($id){$wpdb->update($table,$payload,array('id'=>(int)$id));return (int)$id;}
        $payload['program_id']=$program_id;$payload['source_key']=$source_key;$payload['status']='planned';$payload['created_at']=$now;$wpdb->insert($table,$payload);return (int)$wpdb->insert_id;
    }

    private static function apply_accommodation_checklist_rule( $program_id, $required ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_operation_checklist';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d AND item_key='lodging_confirmed' LIMIT 1",absint($program_id)));
        if(!$row)return;
        if(!$required && in_array($row->status,array('pending','ready','problem'),true)){$wpdb->update($table,array('status'=>'not_applicable','updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));}
        if($required && 'not_applicable'===$row->status){$wpdb->update($table,array('status'=>'pending','updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));}
    }

    private static function recalculate_plan_status( $program_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_operation_checklist'; $plan=self::get_plan($program_id); if(!$plan)return;
        $row=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total,SUM(status='done') done_count,SUM(status='problem') problems FROM $table WHERE program_id=%d AND phase IN ('pre_departure','venue_setup') AND is_required=1 AND status<>'not_applicable'",absint($program_id)),ARRAY_A);
        $total=(int)($row['total']??0);$done=(int)($row['done_count']??0);$problems=(int)($row['problems']??0);
        if($total>0 && $done===$total && 0===$problems && in_array($plan->status,array('draft','planned'),true)){
            $wpdb->update($wpdb->prefix.'mmc_operation_plans',array('status'=>'ready','updated_at'=>current_time('mysql')),array('program_id'=>absint($program_id)));
            $program=MMC_Program_Service::get_program($program_id);
            if($program && !in_array($program->status,array('show_day','financial_close','deposit_refund','completed','cancelled'),true)){MMC_Program_Service::set_status($program_id,'operations','Zorunlu hareket öncesi ve salon kurulum kontrolleri tamamlandı.');}
        }
        $post=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total,SUM(status='done') done_count,SUM(status='problem') problems FROM $table WHERE program_id=%d AND phase='post_show' AND is_required=1 AND status<>'not_applicable'",absint($program_id)),ARRAY_A);
        $pt=(int)($post['total']??0);$pd=(int)($post['done_count']??0);$pp=(int)($post['problems']??0);
        if($pt>0 && $pd===$pt && 0===$pp){
            $wpdb->update($wpdb->prefix.'mmc_operation_plans',array('status'=>'completed','updated_at'=>current_time('mysql')),array('program_id'=>absint($program_id)));
            $program=MMC_Program_Service::get_program($program_id);
            if($program && !in_array($program->status,array('financial_close','deposit_refund','completed','cancelled'),true)){MMC_Program_Service::set_status($program_id,'financial_close','Gösteri sonrası operasyon ve salon teslimi tamamlandı.');}
            self::ensure_close_tasks($program_id);
        }
    }

    private static function ensure_operation_task( $program_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_tasks';
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND module='operations' AND title=%s AND status<>'cancelled' LIMIT 1",absint($program_id),'Operasyon ve lojistik planını tamamla'));
        if($exists)return;
        $now=current_time('mysql');$wpdb->insert($table,array('program_id'=>absint($program_id),'module'=>'operations','title'=>'Operasyon ve lojistik planını tamamla','status'=>'open','priority'=>'high','created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));
    }

    private static function ensure_close_tasks( $program_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_tasks'; $now=current_time('mysql');
        $tasks=array(
            array('finance','Gelir-gider ve günlük satış mutabakatını tamamla','high'),
            array('finance','Salon teminat iade dilekçesi ve iade takibini başlat','high'),
        );
        foreach($tasks as $t){
            $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND module=%s AND title=%s AND status<>'cancelled' LIMIT 1",absint($program_id),$t[0],$t[1]));
            if(!$exists){$wpdb->insert($table,array('program_id'=>absint($program_id),'module'=>$t[0],'title'=>$t[1],'status'=>'open','priority'=>$t[2],'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));}
        }
    }

    private static function sync_operation_finance( $program_id ) {
        $plan=self::get_plan($program_id); if(!$plan)return;
        self::upsert_finance($program_id,$plan->id,'transport_fuel','Ulaşım / Yakıt Operasyon Bütçesi',(float)$plan->transport_cost);
        self::upsert_finance($program_id,$plan->id,'hotel_accommodation','Otel / Konaklama Operasyon Bütçesi',(float)$plan->lodging_cost);
        self::upsert_finance($program_id,$plan->id,'meals','Yemek Operasyon Bütçesi',(float)$plan->meal_cost);
        self::upsert_finance($program_id,$plan->id,'operation_other','Diğer Operasyon Gideri',(float)$plan->other_cost);
    }

    private static function upsert_finance( $program_id,$plan_id,$category,$title,$amount ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_finance_entries';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d AND related_entity_type='operation_plan' AND related_entity_id=%d AND category=%s LIMIT 1",absint($program_id),absint($plan_id),$category));
        $now=current_time('mysql');
        if($row){
            if('paid'===$row->status)return;
            $wpdb->update($table,array('amount'=>$amount,'status'=>$amount>0?'planned':'cancelled','title'=>$title,'updated_at'=>$now),array('id'=>(int)$row->id));
        } elseif($amount>0){
            $wpdb->insert($table,array('program_id'=>absint($program_id),'related_entity_type'=>'operation_plan','related_entity_id'=>absint($plan_id),'entry_class'=>'expense','category'=>$category,'title'=>$title,'amount'=>$amount,'status'=>'planned','created_by'=>get_current_user_id()?:null,'created_at'=>$now,'updated_at'=>$now));
        }
    }

    private static function datetime_or_null( $value ) {
        $value=sanitize_text_field($value); if(!$value)return null;
        $value=str_replace('T',' ',$value);
        if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/',$value))return null;
        $date=DateTimeImmutable::createFromFormat(strlen($value)===16?'!Y-m-d H:i':'!Y-m-d H:i:s',$value,wp_timezone());
        return $date&&$date->format(strlen($value)===16?'Y-m-d H:i':'Y-m-d H:i:s')===$value?$date->format('Y-m-d H:i:s'):null;
    }

    private static function money( $value ) {
        if(is_string($value)){$value=str_replace(array('. ', ' '),'',$value);$value=str_replace(',','.',$value);} return round(max(0,(float)$value),2);
    }
}
