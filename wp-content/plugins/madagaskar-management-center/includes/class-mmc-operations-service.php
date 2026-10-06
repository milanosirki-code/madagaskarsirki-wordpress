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
        if('task_updated'===$action && 'task'===$entity_type){self::mark_manual_task_fields($program_id,$entity_id,$new_value);return;}
        $seed_actions = array( 'venue_confirmed','event_draft_created','event_updated','session_created','session_deleted','sales_readiness_passed','sales_integration_updated' );
        $should_seed = in_array( $action, $seed_actions, true );
        if ( 'program_status_changed' === $action && in_array( (string) $new_value, array( 'operations','show_day' ), true ) ) { $should_seed = true; }
        if ( ! $should_seed ) { return; }

        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program || in_array( $program->status, array( 'cancelled', 'completed', 'financial_close', 'deposit_refund' ), true ) ) { return; }
        self::$handling_log = true;
        try { self::sync_schedule_from_event( $program_id ); }
        finally { self::$handling_log = false; }
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

    /** Explicit write path only. Serialize plan/checklist/task initialization per program. */
    public static function ensure_plan( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { return new WP_Error( 'mmc_ops_program_missing', 'Program bulunamadı.' ); }
        if ( in_array( $program->status, array( 'cancelled', 'completed' ), true ) ) {
            return new WP_Error( 'mmc_ops_program_closed', 'Kapalı program için operasyon hazırlığı yapılamaz.' );
        }
        $lock = 'mmc_ops_' . substr( hash( 'sha256', $wpdb->prefix . $program_id ), 0, 40 );
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
            return new WP_Error( 'mmc_ops_busy', 'Operasyon hazırlığı başka bir işlemde; yeniden deneyin.' );
        }
        try { return self::initialize_plan( $program_id ); }
        finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
    }

    private static function initialize_plan( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { return new WP_Error( 'mmc_ops_program_missing', 'Program bulunamadı.' ); }
        $table = $wpdb->prefix . 'mmc_operation_plans';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE program_id=%d LIMIT 1", $program_id ) );
        if ( $row ) {
            self::seed_checklist( $program_id );
            $task_result=self::ensure_operation_task( $program_id );
            if(is_wp_error($task_result)){return $task_result;}
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
        $plan_id = (int) $wpdb->insert_id;
        self::seed_checklist( $program_id );
        $task_result=self::ensure_operation_task( $program_id );
        if(is_wp_error($task_result)){return $task_result;}
        MMC_Program_Service::add_log( $program_id, 'operations_plan_created', 'operations', $plan_id, null, array( 'origin_city'=>'Ankara' ), 'Operasyon planı otomatik oluşturuldu.' );
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
        $timeline_check = self::validate_operation_timeline( $program_id, $payload );
        if ( is_wp_error( $timeline_check ) ) { return $timeline_check; }
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
        $program_id=absint($program_id);
        $plan_exists=(bool) self::get_plan($program_id);
        $check=$wpdb->prefix.'mmc_operation_checklist'; $res=$wpdb->prefix.'mmc_program_resources';
        $all=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total, SUM(status='done') done_count, SUM(status='problem') problems FROM $check WHERE program_id=%d AND status<>'not_applicable'",$program_id),ARRAY_A);
        $pre=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total, SUM(status='done') done_count, SUM(status='problem') problems FROM $check WHERE program_id=%d AND phase IN ('pre_departure','venue_setup') AND is_required=1 AND status<>'not_applicable'",$program_id),ARRAY_A);
        $counts=$wpdb->get_results($wpdb->prepare("SELECT resource_type,COUNT(*) total FROM $res WHERE program_id=%d AND status<>'cancelled' GROUP BY resource_type",$program_id),OBJECT_K);
        $pre_total=(int)($pre['total']??0); $pre_done=(int)($pre['done_count']??0);
        $alerts=self::task_alerts($program_id);
        return array(
            'overdue_count'=>$alerts['counts']['overdue'],'due_today_count'=>$alerts['counts']['due_today'],
            'upcoming_count'=>$alerts['counts']['upcoming_48h'],'unassigned_count'=>$alerts['counts']['unassigned'],'next_due_task'=>$alerts['next_due_task'],
            'plan_exists'=>$plan_exists,
            'check_total'=>(int)($all['total']??0),'check_done'=>(int)($all['done_count']??0),'problems'=>(int)($all['problems']??0),
            'pre_total'=>$pre_total,'pre_done'=>$pre_done,'pre_percent'=>$pre_total?round(100*$pre_done/$pre_total,1):0,
            'vehicles'=>isset($counts['vehicle'])?(int)$counts['vehicle']->total:0,
            'artists'=>isset($counts['artist'])?(int)$counts['artist']->total:0,
            'people'=>isset($counts['person'])?(int)$counts['person']->total:0,
            'equipment'=>isset($counts['equipment'])?(int)$counts['equipment']->total:0,
        );
    }

    /** One bounded aggregate read for all programs, without initialization or per-row queries. */
    public static function readiness_overview( $include_archive = false ) {
        global $wpdb;
        $p=$wpdb->prefix; $now=current_time('mysql'); $today=substr($now,0,10);
        $where=$include_archive ? '1=1' : $wpdb->prepare("COALESCE(e.event_date,p.planned_date)>=%s AND p.status NOT IN ('cancelled','completed','financial_close','deposit_refund') AND COALESCE(e.status,'draft')<>'cancelled'",$today);
        $template_keys=implode(',',array_map(static function($item){return "'".$item[1]."'";},self::checklist_template()));
        $sql=$wpdb->prepare("SELECT p.id,p.program_code,p.province_name,p.district_name,p.status program_status,
            COALESCE(e.event_date,p.planned_date) program_date,p.planned_date,e.event_date,e.status event_status,e.id event_id,pv.venue_id,v.venue_name,
            o.id plan_id,o.status plan_status,o.operation_mode,o.accommodation_required,o.lodging_name,o.doors_open_at,
            ss.first_session,ss.next_session,COALESCE(ss.session_count,0) session_count,
            COALESCE(c.template_check_count,0) template_check_count,COALESCE(c.check_total,0) check_total,COALESCE(c.check_done,0) check_done,COALESCE(c.problems,0) problems,
            COALESCE(c.required_total,0) required_total,COALESCE(c.required_done,0) required_done,
            COALESCE(c.required_pending,0) required_pending,COALESCE(c.lodging_required,0) lodging_required,
            COALESCE(c.vehicles_required,0) vehicles_required,COALESCE(c.artists_required,0) artists_required,
            COALESCE(c.people_required,0) people_required,COALESCE(c.equipment_required,0) equipment_required,
            COALESCE(r.vehicles,0) vehicles,COALESCE(r.artists,0) artists,COALESCE(r.people,0) people,COALESCE(r.equipment,0) equipment,
            COALESCE(t.open_tasks,0) open_tasks,COALESCE(t.overdue_tasks,0) overdue_tasks,COALESCE(t.high_tasks,0) high_tasks,
            COALESCE(t.operation_tasks,0) operation_tasks
            FROM {$p}mmc_programs p
            LEFT JOIN {$p}mmc_events e ON e.id=(SELECT MIN(ee.id) FROM {$p}mmc_events ee WHERE ee.program_id=p.id)
            LEFT JOIN {$p}mmc_program_venues pv ON pv.id=e.program_venue_id AND pv.program_id=p.id
            LEFT JOIN {$p}mmc_venues v ON v.id=pv.venue_id
            LEFT JOIN {$p}mmc_operation_plans o ON o.program_id=p.id
            LEFT JOIN (SELECT event_id,MIN(session_time) first_session,MIN(CASE WHEN session_time>=%s THEN session_time END) next_session,COUNT(*) session_count FROM {$p}mmc_sessions WHERE status<>'cancelled' GROUP BY event_id) ss ON ss.event_id=e.id
            LEFT JOIN (SELECT program_id,COUNT(DISTINCT CASE WHEN item_key IN ($template_keys) THEN item_key END) template_check_count,COUNT(*) check_total,SUM(status='done') check_done,SUM(status='problem') problems,
                SUM(is_required=1 AND phase IN ('pre_departure','venue_setup') AND status<>'not_applicable') required_total,
                SUM(is_required=1 AND phase IN ('pre_departure','venue_setup') AND status='done') required_done,
                SUM(is_required=1 AND status NOT IN ('done','not_applicable')) required_pending,
                SUM(item_key='lodging_confirmed' AND status<>'not_applicable') lodging_required,
                SUM(item_key='vehicles_ready' AND is_required=1) vehicles_required,
                SUM(item_key='artists_complete' AND is_required=1) artists_required,
                SUM(item_key='crew_complete' AND is_required=1) people_required,
                SUM(item_key='equipment_loaded' AND is_required=1) equipment_required
                FROM {$p}mmc_operation_checklist GROUP BY program_id) c ON c.program_id=p.id
            LEFT JOIN (SELECT program_id,SUM(resource_type='vehicle') vehicles,SUM(resource_type='artist') artists,SUM(resource_type='person') people,SUM(resource_type='equipment') equipment FROM {$p}mmc_program_resources WHERE status<>'cancelled' GROUP BY program_id) r ON r.program_id=p.id
            LEFT JOIN (SELECT program_id,SUM(status='open') open_tasks,SUM(status='open' AND due_at<%s) overdue_tasks,
                SUM(status='open' AND priority IN ('high','critical')) high_tasks,SUM(module='operations') operation_tasks
                FROM {$p}mmc_tasks GROUP BY program_id) t ON t.program_id=p.id
            WHERE $where ORDER BY program_date,ss.first_session,p.id",$now,$now);
        $rows=(array)$wpdb->get_results($sql,ARRAY_A);
        foreach($rows as &$row){ $row=self::readiness_state($row); }
        unset($row);
        return $rows;
    }

    /** Deterministic, read-only interpretation of proven database fields. */
    public static function readiness_state( $row ) {
        $eligibility=self::operational_eligibility_state((object)array('status'=>$row['program_status']??null,'planned_date'=>$row['planned_date']??null),
            empty($row['plan_id'])?null:(object)array('id'=>$row['plan_id']),
            empty($row['event_id'])?null:(object)array('id'=>$row['event_id'],'status'=>$row['event_status']??null,'event_date'=>$row['event_date']??($row['program_date']??null)),
            self::canonical_timestamp($row['first_session']??null)!==null);
        $row['operationally_eligible']=$eligibility['eligible'];$row['date_drift']=$eligibility['date_drift'];$row['eligibility_reasons']=$eligibility['reasons'];
        $issues=array();
        if(empty($row['plan_id'])){$issues[]='Operasyon planı yok';}
        if(empty($row['venue_id'])){$issues[]='Salon bağlantısı yok';}
        if(empty($row['session_count'])){$issues[]='Aktif seans yok';}
        if(!empty($row['plan_id']) && empty($row['check_total'])){$issues[]='Kontrol listesi yok';}
        elseif(!empty($row['plan_id']) && (int)($row['template_check_count']??$row['check_total'])<count(self::checklist_template())){$issues[]='Standart kontrol listesinde eksik madde var';}
        if(!empty($row['problems'])){$issues[]='Kontrol listesinde sorun var';}
        if(!empty($row['required_pending'])){$issues[]='Zorunlu kontroller tamamlanmadı';}
        foreach(array('vehicles'=>'Araç','artists'=>'Sanatçı','people'=>'Personel','equipment'=>'Ekipman') as $key=>$label){
            if(!empty($row[$key.'_required']) && empty($row[$key])){$issues[]=$label.' ataması yok';}
        }
        if(!empty($row['accommodation_required']) && empty($row['lodging_name'])){$issues[]='Gerekli konaklama bilgisi yok';}
        if(!empty($row['high_tasks'])){$issues[]='Açık yüksek öncelikli görev var';}
        $total=(int)($row['required_total']??0);
        $row['readiness_percent']=$total?round(100*(int)($row['required_done']??0)/$total,1):0;
        $row['critical_missing']=$issues;
        $row['next_action']=$issues?reset($issues):'Mevcut plan ve görevleri takip edin';
        $next=self::canonical_timestamp($row['next_session']??null);
        $row['seconds_to_next_session']=$next?max(0,(new DateTimeImmutable($next,wp_timezone()))->getTimestamp()-(new DateTimeImmutable(current_time('mysql'),wp_timezone()))->getTimestamp()):null;
        $row['show_day']=($row['program_status']??'')==='show_day' || ($row['program_date']??'')===substr(current_time('mysql'),0,10);
        return $row;
    }

    /** Preview only: does not invoke ensure/backfill or infer deadlines/owners. */
    public static function backfill_preview() {
        global $wpdb;
        $existing=array();
        foreach((array)$wpdb->get_results("SELECT program_id,title,metadata FROM {$wpdb->prefix}mmc_tasks WHERE module='operations'") as $task){
            $existing[(int)$task->program_id]['titles'][$task->title]=true;
            $meta=json_decode((string)($task->metadata??''),true);
            if(is_array($meta) && isset($meta['source_key'])){$existing[(int)$task->program_id]['keys'][$meta['source_key']]=true;}
        }
        $preview=array();
        foreach(self::readiness_overview(true) as $row){
            $closed=in_array($row['program_status'],array('cancelled','completed','financial_close','deposit_refund'),true);
            $templates_due=in_array($row['program_status'],array('operations','show_day'),true);
            $templates=self::task_templates();if(!$templates_due){$templates=array('plan'=>$templates['plan']);}
            $missing_tasks=0;
            if(!$closed){foreach($templates as$key=>$template){if(empty($existing[(int)$row['id']]['titles'][$template[1]]) && empty($existing[(int)$row['id']]['keys']['operations_v1.'.$key])){$missing_tasks++;}}}
            $missing_checks=$closed?0:max(0,count(self::checklist_template())-(int)($row['template_check_count']??$row['check_total']));
            $preview[]=array('would_create'=>array('plan'=>(int)(!$closed && empty($row['plan_id'])),'checklist'=>$missing_checks,'tasks'=>$missing_tasks),
                'would_update'=>'Canonical schedule changes require explicit sync; preview does not run sync or edit manual state',
                'program_id'=>(int)$row['id'],'plan_exists'=>!empty($row['plan_id']),
                'task_exists'=>(int)$row['operation_tasks']>0,'checklist_count'=>(int)$row['check_total'],
                'would_create_plan'=>!$closed && empty($row['plan_id']),
                'would_review_tasks'=>!$closed && ($templates_due || empty($row['operation_tasks'])),
                'would_review_checklist'=>!$closed && (int)$row['check_total']<count(self::checklist_template()),
                'would_leave_unchanged'=>$closed || (!empty($row['plan_id']) && !$missing_tasks && !$missing_checks),
                'policy'=>'PREVIEW_ONLY; no dates, owners or domain state changed');
        }
        return $preview;
    }

    /** Actual local timestamps only: no T-minus or invented program-date clock. */
    private static function canonical_timestamp( $value ) {
        if(!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/',$value)){return null;}
        if(strlen($value)===16){$value.=':00';}
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,wp_timezone());
        return $date && $date->format('Y-m-d H:i:s')===$value?$value:null;
    }

    /** Existing lifecycle values only; Operations readiness can coexist with sales. */
    public static function operational_statuses() {
        return array('venue_confirmed','event_setup','sales_prep','sales_open','promotion','operations','show_day');
    }

    public static function operational_eligibility_state( $program, $plan, $event, $has_session ) {
        $date=$event->event_date??($program->planned_date??null);$source=isset($event->event_date)?'event.event_date':'program.planned_date';
        $valid=is_string($date)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$date);
        if($valid){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());$valid=$d&&$d->format('Y-m-d')===$date;}
        $reasons=array();
        if(!$program||!in_array($program->status??'',self::operational_statuses(),true)){$reasons[]='LIFECYCLE_OUTSIDE_OPERATIONAL_SCOPE';}
        if(!$valid||$date<substr(current_time('mysql'),0,10)){$reasons[]='INVALID_OR_PAST_CANONICAL_DATE';}
        if(!$plan||empty($plan->id)){$reasons[]='MISSING_OPERATION_PLAN';}
        if(!$event||empty($event->id)||($event->status??'')==='cancelled'){$reasons[]='MISSING_OR_CANCELLED_EVENT';}
        if(!$has_session){$reasons[]='MISSING_VALID_ACTIVE_SESSION';}
        return array('eligible'=>!$reasons,'date'=>$valid?$date:null,'date_source'=>$source,
            'date_drift'=>isset($event->event_date,$program->planned_date)&&$event->event_date!==$program->planned_date,'reasons'=>$reasons);
    }

    public static function is_operationally_eligible( $program_id ) {
        return self::task_automation_context($program_id)['eligible'];
    }

    /** Same policy in atomic writes; aliases are fixed internal SQL identifiers. */
    private static function operational_scope_sql() {
        global $wpdb;$p=$wpdb->prefix;$statuses=implode("','",self::operational_statuses());
        return "p.status IN ('$statuses') AND e.id IS NOT NULL AND e.status<>'cancelled'
            AND EXISTS(SELECT 1 FROM {$p}mmc_operation_plans op WHERE op.program_id=p.id)
            AND EXISTS(SELECT 1 FROM {$p}mmc_sessions os WHERE os.event_id=e.id AND os.status<>'cancelled' AND os.session_time>'1000-01-01 00:00:00')
            AND COALESCE(e.event_date,p.planned_date)>=%s";
    }

    /** One canonical context per program, reused for every task in preview/apply. */
    public static function task_automation_context( $program_id ) {
        $program=MMC_Program_Service::get_program(absint($program_id));
        $plan=self::get_plan($program_id);
        $event=class_exists('MMC_Event_Service')?MMC_Event_Service::event_for_program($program_id):null;
        $first=null;$last_start=null;$last_end=null;
        if($event){foreach((array)MMC_Event_Service::sessions($event->id) as $session){
            if(($session->status??'active')==='cancelled'){continue;}
            $start=self::canonical_timestamp($session->session_time??null);if(!$start){continue;}
            if(!$first || $start<$first){$first=$start;}
            if(!$last_start || $start>$last_start){
                $last_start=$start;
                // Current schema has no end_at. Never treat generated schedule +60m as proof.
                $end=self::canonical_timestamp($session->end_at??null);
                $last_end=$end && $end>=$start?$end:null;
            }
        }}
        $eligibility=self::operational_eligibility_state($program,$plan,$event,$first!==null);
        $owner=absint($program->owner_user_id??0);
        $valid_owner=$owner && get_user_by('id',$owner)?$owner:null;
        return array('program'=>$program,'plan'=>$plan,'event'=>$event,'first_session'=>$first,'last_session_start'=>$last_start,'last_session_end'=>$last_end,
            'program_date'=>$eligibility['date'],'eligible'=>$eligibility['eligible'],'eligibility'=>$eligibility,'owner_user_id'=>$valid_owner);
    }

    public static function operation_timeline_fields() {
        return array(
            'departure_at'=>'Hareket',
            'venue_entry_at'=>'Salon Giriş',
            'setup_start_at'=>'Kurulum Başlangıç',
            'rehearsal_at'=>'Prova',
            'doors_open_at'=>'Kapı Açılış',
            'teardown_end_at'=>'Söküm Bitiş',
            'return_at'=>'Dönüş / Hareket',
        );
    }

    /** Read-only guided timeline: never writes plan/task state or invents unsupported timestamps. */
    public static function guided_timeline_preview( $program_id ) {
        $context=self::task_automation_context($program_id);$plan=$context['plan'];$event=$context['event'];
        $out=array('program_id'=>absint($program_id),'eligible'=>$context['eligible'],'first_session'=>$context['first_session']??null,
            'last_session_start'=>$context['last_session_start']??null,'fields'=>array());
        foreach(self::operation_timeline_fields() as$field=>$label){
            $current=$plan?self::canonical_timestamp($plan->$field??null):null;$suggested=null;$source=null;
            if('doors_open_at'===$field && !$current){
                $minutes=$event->door_open_minutes??null;$first=self::canonical_timestamp($context['first_session']??null);
                if($first && (is_int($minutes)||(is_string($minutes)&&preg_match('/^\d+$/',$minutes))) && (int)$minutes>=0){
                    $suggested=(new DateTimeImmutable($first,wp_timezone()))->modify('-'.(int)$minutes.' minutes')->format('Y-m-d H:i:s');
                    $source='event.first_session_minus_door_open_minutes';
                }
            }
            $out['fields'][$field]=array('label'=>$label,'current'=>$current,'suggested'=>$suggested,'source'=>$source,
                'manager_required'=>!$current&&!$suggested);
        }
        return $out;
    }

    /** Validate only timestamps that are actually present; partial plans remain allowed. */
    public static function validate_operation_timeline( $program_id, $payload ) {
        $values=array();foreach(array_keys(self::operation_timeline_fields()) as$field){$values[$field]=self::canonical_timestamp($payload[$field]??null);}
        $pairs=array(
            array('departure_at','venue_entry_at','Hareket, salon girişinden sonra olamaz.'),
            array('venue_entry_at','setup_start_at','Salon giriş, kurulum başlangıcından sonra olamaz.'),
            array('setup_start_at','rehearsal_at','Kurulum başlangıcı, provadan sonra olamaz.'),
            array('rehearsal_at','doors_open_at','Prova, kapı açılışından sonra olamaz.'),
            array('teardown_end_at','return_at','Söküm bitişi, dönüş/hareket saatinden sonra olamaz.'),
        );
        foreach($pairs as$rule){if($values[$rule[0]]&&$values[$rule[1]]&&$values[$rule[0]]>$values[$rule[1]]){return new WP_Error('mmc_ops_timeline_order',$rule[2]);}}
        $context=self::task_automation_context($program_id);$first=self::canonical_timestamp($context['first_session']??null);$last=self::canonical_timestamp($context['last_session_start']??null);
        if($values['doors_open_at']&&$first&&$values['doors_open_at']>$first){return new WP_Error('mmc_ops_timeline_doors','Kapı açılışı ilk seans başlangıcından sonra olamaz.');}
        if($values['teardown_end_at']&&$last&&$values['teardown_end_at']<$last){return new WP_Error('mmc_ops_timeline_teardown','Söküm bitişi son seans başlangıcından önce olamaz.');}
        if($values['return_at']&&$last&&$values['return_at']<$last){return new WP_Error('mmc_ops_timeline_return','Dönüş/hareket saati son seans başlangıcından önce olamaz.');}
        return true;
    }

    public static function resolve_task_deadline( $key, $context ) {
        $plan=$context['plan']??null;
        $map=array(
            'plan'=>array('departure_at','venue_entry_at'),
            'transport'=>array('departure_at'),
            'crew'=>array('departure_at','venue_entry_at'),
            'equipment'=>array('departure_at','venue_entry_at'),
            'venue_entry'=>array('venue_entry_at'),'handover_in'=>array('venue_entry_at'),
            'technical'=>array('rehearsal_at','setup_start_at','@first'),
            'box_office'=>array('doors_open_at','@doors'),
            'briefing'=>array('doors_open_at','rehearsal_at'),
            'inventory'=>array('teardown_end_at','@last_end'),'handover_out'=>array('teardown_end_at'),
            'reconcile'=>array('teardown_end_at','@last_end'),'return'=>array('return_at'),
        );
        foreach($map[$key]??array() as $anchor){
            $due=null;$source=null;
            if($anchor==='@first'){$due=$context['first_session']??null;$source='event.first_session';}
            elseif($anchor==='@last_end'){$due=$context['last_session_end']??null;$source='event.last_session.end_at';}
            elseif($anchor==='@doors'){
                $minutes=$context['event']->door_open_minutes??null;
                $first=self::canonical_timestamp($context['first_session']??null);
                if($first && (is_int($minutes) || (is_string($minutes) && preg_match('/^\d+$/',$minutes))) && (int)$minutes>=0){
                    $due=(new DateTimeImmutable($first,wp_timezone()))->modify('-'.(int)$minutes.' minutes')->format('Y-m-d H:i:s');
                    $source='event.first_session_minus_door_open_minutes';
                }
            }else{$due=$plan->$anchor??null;$source='plan.'.$anchor;}
            $due=self::canonical_timestamp($due);
            if($due){return array('due_at'=>$due,'due_source'=>$source,'due_policy_version'=>3);}
        }
        return array('due_at'=>null,'due_source'=>null,'due_policy_version'=>3);
    }

    /** Pure proposal. Only explicit apply/new INSERT uses its payload. */
    public static function task_automation_proposal( $task, $context ) {
        $meta=json_decode((string)($task->metadata??''),true);if(!is_array($meta)){$meta=array();}
        $source=$meta['source_key']??'';$key=is_string($source) && strpos($source,'operations_v1.')===0?substr($source,14):'';
        $system=isset($meta['system_generated']) && true===$meta['system_generated'];
        $current_due=$task->due_at??null;$current_owner=absint($task->assigned_user_id??0)?:null;
        $result=array('task_id'=>(int)($task->id??0),'program_id'=>(int)($task->program_id??0),'task'=>$task->title??'',
            'system_generated'=>$system,'current_due'=>$current_due,'proposed_due'=>$current_due,'due_source'=>$meta['due_source']??null,
            'current_owner'=>$current_owner,'proposed_owner'=>$current_owner,'would_update_due'=>false,'would_update_owner'=>false,
            'would_leave_unchanged'=>true,'reason'=>array(),'update_payload'=>array());
        if(($task->module??'')!=='operations'){$result['reason'][]='MODULE_OUT_OF_SCOPE';return $result;}
        if(!$system){$result['reason'][]='UNMARKED_OR_MANUAL_TASK';return $result;}
        if(!isset(self::task_templates()[$key])){$result['reason'][]='UNKNOWN_TEMPLATE';return $result;}
        if(($task->status??'')!=='open' || !empty($task->completed_at)){$result['reason'][]='TASK_CLOSED';return $result;}
        if(empty($context['eligible'])){$result['reason'][]='PROGRAM_NOT_ACTIVE_FUTURE_OPERATIONS';return $result;}
        $resolved=self::resolve_task_deadline($key,$context);$result['due_source']=$resolved['due_source'];
        $due_managed=($meta['due_managed']??'')==='operations_v2' && array_key_exists('due_last_value',$meta)
            && (string)$current_due===(string)$meta['due_last_value'];
        $due_diverged=($meta['due_managed']??'')==='operations_v2' && array_key_exists('due_last_value',$meta) && (string)$current_due!==(string)$meta['due_last_value'];
        $due_allowed=empty($meta['due_manual_override']) && !$due_diverged && (empty($current_due) || $due_managed);
        if($due_allowed && $resolved['due_at']){
            $result['proposed_due']=$resolved['due_at'];
            if((string)$current_due!==(string)$resolved['due_at']){
                $result['would_update_due']=true;$result['update_payload']['due_at']=$resolved['due_at'];
                $meta['due_source']=$resolved['due_source'];$meta['due_policy_version']=$resolved['due_policy_version'];
                $meta['due_managed']='operations_v2';$meta['due_last_value']=$resolved['due_at'];
            }
        }else{$result['reason'][]=$due_allowed?('plan'===$key?'DUE_ANCHOR_INSUFFICIENT':'NO_CONFIRMED_DEADLINE_ANCHOR'):'MANUAL_DUE_PRESERVED';}
        $owner_managed=($meta['assignment_source']??'')==='program_owner' && array_key_exists('assignment_last_user_id',$meta)
            && $current_owner===(absint($meta['assignment_last_user_id'])?:null);
        $owner_diverged=($meta['assignment_source']??'')==='program_owner' && array_key_exists('assignment_last_user_id',$meta) && $current_owner!==(absint($meta['assignment_last_user_id'])?:null);
        $owner_allowed=empty($meta['assignment_manual_override']) && !$owner_diverged && (!$current_owner || $owner_managed);
        $owner=$context['owner_user_id']??null;
        if($owner_allowed && $owner){
            $result['proposed_owner']=$owner;
            if($current_owner!==$owner){$result['would_update_owner']=true;$result['update_payload']['assigned_user_id']=$owner;
                $meta['assignment_source']='program_owner';$meta['assignment_last_user_id']=$owner;}
        }else{$result['reason'][]=$owner_allowed?'NO_VALID_PROGRAM_OWNER':'MANUAL_OWNER_PRESERVED';}
        $result['would_leave_unchanged']=!$result['would_update_due'] && !$result['would_update_owner'];
        if(!$result['would_leave_unchanged']){$result['update_payload']['metadata']=wp_json_encode($meta);$result['reason'][]='CANONICAL_PROPOSAL';}
        elseif(!$result['reason']){$result['reason'][]='UP_TO_DATE';}
        return $result;
    }

    /** Exact title only. Unknown metadata/notes fail closed; no fuzzy or SQL collation match. */
    public static function legacy_task_classification( $task, $program, $duplicate_count ) {
        $raw=$task->metadata??null;$meta=($raw===null || $raw==='')?array():json_decode((string)$raw,true);
        $key=null;foreach(self::task_templates() as$k=>$template){if(($task->title??null)===$template[1]){$key=$k;break;}}
        $out=array('task_id'=>(int)$task->id,'program_id'=>(int)($task->program_id??0),'program'=>$program->program_code??null,
            'title'=>$task->title??'','module'=>$task->module??'','status'=>$task->status??'','metadata'=>$meta,
            'exact_template_match'=>$key!==null,'template_key'=>$key,'duplicate_count'=>(int)$duplicate_count,
            'current_due'=>$task->due_at??null,'current_owner'=>$task->assigned_user_id??null,'adoptable'=>false,'classification'=>'AMBIGUOUS','reject_reason'=>null);
        if(($task->module??'')!=='operations'){$out['classification']='WRONG_MODULE';$out['reject_reason']='OPERATIONS_SCOPE_ONLY';}
        elseif(($task->status??'')!=='open' || !empty($task->completed_at)){$out['classification']='CLOSED';$out['reject_reason']='TASK_NOT_OPEN';}
        elseif(!$program || (int)$program->id!==(int)($task->program_id??0)){$out['reject_reason']='MISSING_PROGRAM';}
        elseif(in_array($program->status,array('cancelled','completed','financial_close','deposit_refund'),true)){$out['reject_reason']='PROGRAM_CLOSED_OR_FINANCE';}
        elseif($key===null){$out['classification']='CUSTOM';$out['reject_reason']='EXACT_TEMPLATE_REQUIRED';}
        elseif(!is_array($meta) || (array_is_list($meta) && $meta)){$out['reject_reason']='INVALID_METADATA';}
        elseif(($meta['system_generated']??false)===true){$out['reject_reason']='ALREADY_MANAGED';}
        elseif($duplicate_count!==1){$out['classification']='DUPLICATE';$out['reject_reason']='DUPLICATE_REVIEW_REQUIRED';}
        else{
            $allowed=array('source_key'=>'operations_v1.'.$key,'system_generated'=>false,'template_version'=>1,'phase'=>self::task_templates()[$key][0]);
            $custom=!empty($task->notes);foreach($meta as$k=>$v){if(!array_key_exists($k,$allowed) || $allowed[$k]!==$v){$custom=true;break;}}
            if($custom){$out['classification']='CUSTOM';$out['reject_reason']='CUSTOM_METADATA_OR_NOTES';}
            else{$out['classification']='ADOPTABLE';$out['adoptable']=true;}
        }
        return $out;
    }

    private static function legacy_adoption_metadata( $key ) {
        return array('source_key'=>'operations_v1.'.$key,'system_generated'=>true,'phase'=>self::task_templates()[$key][0],
            'template_version'=>1,'adopted_from_legacy'=>true,'adopted_at'=>current_time('mysql'),'adoption_policy'=>'operations_v3_exact_title');
    }

    /** SELECT only; proposed post-adoption backfill is simulated on clones, not persisted. */
    public static function task_adoption_preview( $program_id ) {
        global $wpdb;$pid=absint($program_id);$context=self::task_automation_context($pid);
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_tasks WHERE program_id=%d ORDER BY id",$pid));
        $duplicates=array();foreach($rows as$r){if(($r->module??'')==='operations'){$duplicates[$r->title]=($duplicates[$r->title]??0)+1;}}
        $out=array('program_id'=>$pid,'eligible'=>$context['eligible'],'snapshot'=>hash('sha256',wp_json_encode($rows)),
            'items'=>array(),'counts'=>array_fill_keys(array('ADOPTABLE','DUPLICATE','CUSTOM','CLOSED','WRONG_MODULE','AMBIGUOUS'),0),
            'managed'=>0,'legacy'=>0,'would_adopt'=>0,'would_update_due'=>0,'would_update_owner'=>0);
        foreach($rows as$r){$item=self::legacy_task_classification($r,$context['program'],$duplicates[$r->title]??0);
            if(($item['metadata']['system_generated']??false)===true){$out['managed']++;}else{$out['legacy']++;}
            $out['counts'][$item['classification']]++;
            $clone=clone $r;if($item['adoptable']){$clone->metadata=wp_json_encode(array_merge($item['metadata'],self::legacy_adoption_metadata($item['template_key'])));}
            $proposal=self::task_automation_proposal($clone,$context);unset($proposal['update_payload']);$item['backfill_preview']=$proposal;
            $item['scope_eligible']=$context['eligible'];$out['would_adopt']+=(int)($item['adoptable']&&$context['eligible']);
            $out['would_update_due']+=(int)$proposal['would_update_due'];$out['would_update_owner']+=(int)$proposal['would_update_owner'];$out['items'][]=$item;
        }return $out;
    }

    /** Metadata-only adoption; separate from Phase2 apply. Program lock + transaction + byte CAS. */
    public static function adopt_legacy_tasks( $program_id, $task_ids, $snapshot ) {
        global $wpdb;
        if(!current_user_can('mmc_manage_operations')){return new WP_Error('mmc_ops_forbidden','Operasyon yetkisi gerekir.');}
        $pid=absint($program_id);$ids=array_values(array_unique(array_filter(array_map('absint',(array)$task_ids))));
        if(!$ids || count($ids)>100){return new WP_Error('mmc_ops_selection','Bu programdan görev seçin (en çok 100).');}
        $lock='mmc_ops_'.substr(hash('sha256',$wpdb->prefix.$pid),0,40);$transaction=false;
        if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))){return new WP_Error('mmc_ops_busy','Program başka işlemde.');}
        try{
            foreach(array('mmc_tasks','mmc_programs') as$table){
                $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->prefix.$table));
                if(strtoupper((string)$engine)!=='INNODB'){throw new RuntimeException('Transactional tables required for atomic adoption');}
            }
            if(false===$wpdb->query('START TRANSACTION')){throw new RuntimeException('Transaction unavailable');}$transaction=true;
            $wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mmc_programs WHERE id=%d FOR UPDATE",$pid));
            $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_tasks WHERE program_id=%d ORDER BY id FOR UPDATE",$pid));
            $preview=self::task_adoption_preview($pid);
            if(!$preview['eligible']){throw new RuntimeException('Program is not operationally eligible');}
            $selected=array_filter($preview['items'],static function($i)use($ids){return in_array($i['task_id'],$ids,true);});
            if(count($selected)!==count($ids)){throw new RuntimeException('Selected task is not in this program');}
            $all_managed=true;foreach($selected as$i){if($i['reject_reason']!=='ALREADY_MANAGED'){$all_managed=false;}}
            if($all_managed){$wpdb->query('COMMIT');$transaction=false;return array('adopted'=>0,'due_updated'=>0,'owner_updated'=>0);}
            if(!is_string($snapshot) || !hash_equals($preview['snapshot'],$snapshot)){throw new RuntimeException('Stale adoption snapshot; reload preview');}
            $by_id=array();foreach($rows as$r){$by_id[(int)$r->id]=$r;}$changed=0;
            foreach($selected as$item){if(!$item['adoptable']){throw new RuntimeException('Selected task requires review');}
                $row=$by_id[$item['task_id']];$meta=array_merge($item['metadata'],self::legacy_adoption_metadata($item['template_key']));
                // GROUP BY materializes the same-table duplicate guard; binary title bypasses CI collation.
                $sql="UPDATE {$wpdb->prefix}mmc_tasks t JOIN
                    (SELECT program_id,CAST(title AS BINARY) exact_title,COUNT(*) n FROM {$wpdb->prefix}mmc_tasks WHERE module='operations' GROUP BY program_id,CAST(title AS BINARY)) d
                    ON d.program_id=t.program_id AND d.exact_title=CAST(t.title AS BINARY) AND d.n=1
                    SET t.metadata=%s WHERE t.id=%d AND t.program_id=%d AND t.module='operations' AND t.status='open'";
                $args=array(wp_json_encode($meta),(int)$row->id,$pid);
                foreach(array('title','metadata','due_at','assigned_user_id','completed_at','priority','created_at','updated_at','notes') as$column){
                    if(!property_exists($row,$column)){continue;}$v=$row->$column;
                    if(null===$v){$sql.=" AND t.$column IS NULL";}else{$sql.=" AND CAST(t.$column AS BINARY)=%s";$args[]=$v;}}
                $p=$wpdb->prefix;$scope=self::operational_scope_sql();
                $sql.=" AND EXISTS(SELECT 1 FROM {$p}mmc_programs p LEFT JOIN {$p}mmc_events e ON e.id=(SELECT MIN(ee.id) FROM {$p}mmc_events ee WHERE ee.program_id=p.id)
                    WHERE p.id=t.program_id AND $scope)";
                $args[]=substr(current_time('mysql'),0,10);
                $context=self::task_automation_context($pid);
                if(!$context['eligible']){throw new RuntimeException('Program scope changed');}
                if(1!==(int)$wpdb->query($wpdb->prepare($sql,...$args))){throw new RuntimeException('Task changed or duplicate detected; adoption rolled back');}$changed++;
            }
            if(false===$wpdb->query('COMMIT')){throw new RuntimeException('Commit failed');}$transaction=false;
            return array('adopted'=>$changed,'due_updated'=>0,'owner_updated'=>0);
        }catch(Throwable$e){return new WP_Error('mmc_ops_adoption_guard',$e->getMessage());}
        finally{if($transaction){$wpdb->query('ROLLBACK');}$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }

    public static function task_automation_preview( $program_id ) {
        global $wpdb;
        $context=self::task_automation_context($program_id);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_tasks WHERE program_id=%d ORDER BY id",absint($program_id)));
        $out=array('program_id'=>absint($program_id),'eligible'=>$context['eligible'],'items'=>array(),'would_update_due'=>0,'would_update_owner'=>0);
        foreach((array)$rows as $task){$item=self::task_automation_proposal($task,$context);unset($item['update_payload']);
            $out['would_update_due']+=(int)$item['would_update_due'];$out['would_update_owner']+=(int)$item['would_update_owner'];$out['items'][]=$item;}
        return $out;
    }

    /** Explicit program-scoped write; never called by dashboard/preview or on deployment. */
    public static function apply_task_automation( $program_id ) {
        global $wpdb;
        if(!current_user_can('mmc_manage_operations')){return new WP_Error('mmc_ops_forbidden','Operasyon yetkisi gerekir.');}
        $program_id=absint($program_id);$lock='mmc_ops_'.substr(hash('sha256',$wpdb->prefix.$program_id),0,40);
        if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))){return new WP_Error('mmc_ops_busy','Başka bir hazırlık işlemi var.');}
        try{
            $context=self::task_automation_context($program_id);
            if(!$context['eligible']){return new WP_Error('mmc_ops_automation_scope','Yalnız gerçek plan/event/seans bağlı aktif/gelecek operasyon kapsamındaki programda uygulanabilir.');}
            $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_tasks WHERE program_id=%d AND module='operations' AND status='open' ORDER BY id",$program_id));
            $result=array('tasks_updated'=>0,'due_updated'=>0,'owner_updated'=>0,'stale_skipped'=>0);
            foreach((array)$rows as $task){$proposal=self::task_automation_proposal($task,$context);if($proposal['would_leave_unchanged']){continue;}
                $payload=$proposal['update_payload'];$payload['updated_at']=current_time('mysql');
                // Compare-and-swap protects concurrent manual edits even outside this advisory lock.
                $where=array('id'=>(int)$task->id,'program_id'=>$program_id,'status'=>'open','completed_at'=>$task->completed_at??null,
                    'due_at'=>$task->due_at??null,'assigned_user_id'=>$task->assigned_user_id??null,'metadata'=>$task->metadata??null);
                $changed=self::apply_task_fields_if_current($payload,$where,$context);
                if(false===$changed){return new WP_Error('mmc_ops_automation_update','Güncelleme başarısız; başarılı kayıtlar korunur, tekrar işlem idempotenttir.');}
                if(!$changed){$result['stale_skipped']++;continue;}
                $result['tasks_updated']++;$result['due_updated']+=(int)$proposal['would_update_due'];$result['owner_updated']+=(int)$proposal['would_update_owner'];
            }
            if($result['tasks_updated']){MMC_Program_Service::add_log($program_id,'operations_task_automation_applied','operations',$program_id,null,$result,'Açık görev otomasyonu program bazında uygulandı.');}
            return $result;
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }

    /** Atomic scope guard: cancellation or owner deletion/change during apply fails closed. */
    private static function apply_task_fields_if_current( $payload, $where, $context ) {
        global $wpdb;$p=$wpdb->prefix;$sets=array();$clauses=array();$params=array();
        foreach($payload as$key=>$value){$sets[]=$key.'=%s';$params[]=$value;}
        foreach($where as$key=>$value){if(null===$value){$clauses[]=$key.' IS NULL';}else{$clauses[]=('metadata'===$key?'CAST(metadata AS BINARY)':$key).'=%s';$params[]=$value;}}
        $params[]=(int)$where['program_id'];$params[]=substr(current_time('mysql'),0,10);
        $owner_guard='';
        if(isset($payload['assigned_user_id'])){
            $owner_guard=" AND p.owner_user_id=%d AND EXISTS(SELECT 1 FROM {$p}users u WHERE u.ID=p.owner_user_id)";
            $params[]=(int)$payload['assigned_user_id'];
        }
        $scope=self::operational_scope_sql();
        $sql="UPDATE {$p}mmc_tasks SET ".implode(',',$sets).' WHERE '.implode(' AND ',$clauses).
            " AND EXISTS(SELECT 1 FROM {$p}mmc_programs p
                LEFT JOIN {$p}mmc_events e ON e.id=(SELECT MIN(ee.id) FROM {$p}mmc_events ee WHERE ee.program_id=p.id)
                WHERE p.id=%d AND $scope $owner_guard)";
        return $wpdb->query($wpdb->prepare($sql,...$params));
    }

    /** Existing manual task-update actions publish this log; clearing/same-value edits also win. */
    private static function mark_manual_task_fields( $program_id, $task_id, $new_value ) {
        global $wpdb;
        if(!is_array($new_value) || (!array_key_exists('due_at',$new_value) && !array_key_exists('assigned_user_id',$new_value))){return;}
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_tasks WHERE id=%d AND program_id=%d AND module='operations'",absint($task_id),absint($program_id)));
        if(!$row){return;}$meta=json_decode((string)($row->metadata??''),true);
        if(!is_array($meta) || ($meta['system_generated']??false)!==true){return;}
        if(array_key_exists('due_at',$new_value)){$meta['due_manual_override']=true;unset($meta['due_managed'],$meta['due_last_value']);}
        if(array_key_exists('assigned_user_id',$new_value)){$meta['assignment_manual_override']=true;unset($meta['assignment_source'],$meta['assignment_last_user_id']);}
        $encoded=wp_json_encode($meta);
        if($encoded!==(string)$row->metadata){$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}mmc_tasks SET metadata=%s WHERE id=%d AND program_id=%d AND module='operations' AND CAST(metadata AS BINARY)=%s",$encoded,(int)$row->id,absint($program_id),$row->metadata));}
    }

    public static function task_due_state( $task, $now = null ) {
        $now=$now?:current_time('mysql');$due=self::canonical_timestamp($task->due_at??null);$open=($task->status??'')==='open';
        $overdue=$open && $due && $due<$now;$today=$open && $due && substr($due,0,10)===substr($now,0,10);
        $end=(new DateTimeImmutable($now,wp_timezone()))->modify('+48 hours')->format('Y-m-d H:i:s');
        return array('state'=>!$open?strtoupper($task->status??'closed'):(!$due?'NO_DEADLINE':($overdue?'OVERDUE':($today?'DUE_TODAY':'UPCOMING'))),
            'overdue'=>(bool)$overdue,'due_today'=>(bool)$today,'upcoming_48h'=>(bool)($open && $due && $due>=$now && $due<=$end));
    }

    /** One query for in-app cards and all filters; no provider calls or notification log. */
    public static function task_alerts( $program_id = 0, $filter = 'all' ) {
        global $wpdb;$p=$wpdb->prefix;$today=substr(current_time('mysql'),0,10);$params=array($today);
        $scope='';if($program_id){$scope=' AND t.program_id=%d';$params[]=absint($program_id);}
        $rows=$wpdb->get_results($wpdb->prepare("SELECT t.*,p.program_code,p.province_name,p.district_name,COALESCE(e.event_date,p.planned_date) program_date
            FROM {$p}mmc_tasks t INNER JOIN {$p}mmc_programs p ON p.id=t.program_id
            LEFT JOIN {$p}mmc_events e ON e.id=(SELECT MIN(ee.id) FROM {$p}mmc_events ee WHERE ee.program_id=p.id)
            WHERE t.module='operations' AND t.status='open' AND COALESCE(e.event_date,p.planned_date)>=%s
            AND p.status NOT IN ('cancelled','completed','financial_close','deposit_refund') AND COALESCE(e.status,'draft')<>'cancelled' $scope
            ORDER BY t.due_at IS NULL,t.due_at,t.id",...$params));
        $counts=array('open'=>0,'overdue'=>0,'due_today'=>0,'upcoming_48h'=>0,'no_deadline'=>0,'mine'=>0,'high'=>0,'unassigned'=>0);
        $items=array();$next=null;$uid=get_current_user_id();
        foreach((array)$rows as $row){$state=self::task_due_state($row);$mine=$uid && (int)($row->assigned_user_id??0)===$uid;$high=in_array($row->priority??'',array('high','critical'),true);
            $counts['open']++;$counts['overdue']+=(int)$state['overdue'];$counts['due_today']+=(int)$state['due_today'];$counts['upcoming_48h']+=(int)$state['upcoming_48h'];
            $counts['no_deadline']+=(int)($state['state']==='NO_DEADLINE');$counts['mine']+=(int)$mine;$counts['high']+=(int)$high;$counts['unassigned']+=(int)empty($row->assigned_user_id);
            if(!empty($row->due_at) && (!$next || $row->due_at<$next['due_at'])){$next=array('task_id'=>(int)$row->id,'due_at'=>$row->due_at);}
            $matches=array('all'=>true,'overdue'=>$state['overdue'],'today'=>$state['due_today'],'48h'=>$state['upcoming_48h'],'mine'=>$mine,'high'=>$high,'no_deadline'=>$state['state']==='NO_DEADLINE');
            if(empty($matches[$filter]) && $filter!=='all'){continue;}
            $meta=json_decode((string)($row->metadata??''),true);$item=(array)$row;unset($item['metadata']);$item['phase']=is_array($meta)?($meta['phase']??''):'';$item['computed_state']=$state['state'];$items[]=$item;
        }
        return array('counts'=>$counts,'items'=>$items,'next_due_task'=>$next,
            'notification_policy_preview'=>array('provider_calls'=>0,'notification_log_writes'=>0,'candidates'=>array('overdue'=>$counts['overdue'],'due_today'=>$counts['due_today'],'high_priority'=>$counts['high']),'mode'=>'IN_APP_ONLY'));
    }

    public static function operation_tasks( $program_id ) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT id,module,title,status,priority,due_at,assigned_user_id,completed_at,metadata FROM {$wpdb->prefix}mmc_tasks WHERE program_id=%d AND module IN ('operations','finance') ORDER BY status='open' DESC,due_at IS NULL,due_at,id",absint($program_id)));
    }

    public static function schedule( $program_id ) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_operation_schedule WHERE program_id=%d ORDER BY start_at IS NULL,start_at,sort_order,id",absint($program_id)));
    }

    public static function sync_schedule_from_event( $program_id ) {
        global $wpdb;
        $program_id=absint($program_id);
        $lock='mmc_schedule_'.substr(hash('sha256',$wpdb->prefix.$program_id),0,40);
        if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))){return new WP_Error('mmc_ops_busy','Akış başka bir işlemde; yeniden deneyin.');}
        try{return self::synchronize_schedule($program_id);}
        finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }

    private static function synchronize_schedule( $program_id ) {
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
            if(!$v[2]) {
                $wpdb->delete($table,array('program_id'=>$program_id,'source_key'=>'plan_'.$key,'is_system'=>1));
                continue;
            }
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
        if($id){
            $current=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",(int)$id));
            if($is_system){unset($payload['assigned_name'],$payload['notes']);}
            $changed=false;
            foreach($payload as $key=>$value){if('updated_at'!==$key && (string)($current->$key??'')!==(string)$value){$changed=true;break;}}
            if($changed){$wpdb->update($table,$payload,array('id'=>(int)$id));}
            return (int)$id;
        }
        $payload['program_id']=$program_id;$payload['source_key']=$source_key;$payload['status']='planned';$payload['created_at']=$now;$wpdb->insert($table,$payload);return (int)$wpdb->insert_id;
    }

    private static function apply_accommodation_checklist_rule( $program_id, $required ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_operation_checklist';
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE program_id=%d AND item_key='lodging_confirmed' LIMIT 1",absint($program_id)));
        if(!$row)return;
        if((int)$row->is_required !== (int)(bool)$required){$wpdb->update($table,array('is_required'=>(int)(bool)$required,'updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));}
        if(!$required && in_array($row->status,array('pending','ready','problem'),true)){$wpdb->update($table,array('status'=>'not_applicable','updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));}
        if($required && 'not_applicable'===$row->status){$wpdb->update($table,array('status'=>'pending','updated_at'=>current_time('mysql')),array('id'=>(int)$row->id));}
    }

    private static function recalculate_plan_status( $program_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_operation_checklist'; $plan=self::get_plan($program_id); if(!$plan)return;
        $program=MMC_Program_Service::get_program($program_id);
        if(!$program || in_array($program->status,array('cancelled','completed'),true)){return;}
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

    /** High-level follow-up work; detailed controls remain in the checklist. */
    public static function task_templates() {
        return array(
            'plan' => array('pre_departure','Operasyon ve lojistik planını tamamla'),
            'transport' => array('pre_departure','Araç / ulaşım planını kesinleştir'),
            'crew' => array('pre_departure','Sanatçı ve ekip kadrosunu kesinleştir'),
            'equipment' => array('pre_departure','Gösteri ekipmanlarını kontrol et'),
            'venue_entry' => array('pre_departure','Salon giriş / kurulum saatini teyit et'),
            'handover_in' => array('show_day','Salon giriş ve alan teslimini kontrol et'),
            'technical' => array('show_day','Ses / ışık / bilgisayar testini tamamla'),
            'box_office' => array('show_day','Gişe / POS / QR kontrolünü tamamla'),
            'briefing' => array('show_day','Personel görev dağılımını tamamla'),
            'inventory' => array('post_show','Ekipman ve kostüm sayımını tamamla'),
            'handover_out' => array('post_show','Salon çıkış teslimini tamamla'),
            'reconcile' => array('post_show','Satış / gişe mutabakatını tamamla'),
            'return' => array('post_show','Dönüş / sonraki şehir hareketini tamamla'),
        );
    }

    private static function ensure_operation_task( $program_id ) {
        global $wpdb;
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program || in_array( $program->status, array('cancelled','completed','financial_close','deposit_refund'), true ) ) { return; }
        $templates = self::task_templates();
        // Earlier preparation keeps the existing single planning task. No mass backfill.
        if ( ! in_array( $program->status, array('operations','show_day'), true ) ) { $templates = array('plan'=>$templates['plan']); }
        $table = $wpdb->prefix . 'mmc_tasks';
        $existing = $wpdb->get_results( $wpdb->prepare( "SELECT title,metadata FROM $table WHERE program_id=%d AND module='operations'", absint($program_id) ) );
        $titles = array(); $keys = array();
        foreach ( (array)$existing as $task ) {
            $titles[$task->title] = true;
            $meta = json_decode( (string)($task->metadata ?? ''), true );
            if ( is_array($meta) && isset($meta['source_key']) ) { $keys[$meta['source_key']] = true; }
        }
        $automation_context=self::task_automation_context($program_id);
        // Preserve completed/cancelled/manual matching tasks; never reset owner or deadline.
        foreach ( $templates as $key=>$template ) {
            $source_key = 'operations_v1.' . $key;
            if ( isset($titles[$template[1]]) || isset($keys[$source_key]) ) { continue; }
            // Also supports older integrations that only expose the existing title lookup.
            if ( $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND module='operations' AND title=%s LIMIT 1",absint($program_id),$template[1])) ) { continue; }
            $now=current_time('mysql');
            $new_task=array('program_id'=>absint($program_id),'module'=>'operations','title'=>$template[1],
                'status'=>'open','priority'=>('plan'===$key?'high':'normal'),'metadata'=>wp_json_encode(array('source_key'=>$source_key,'system_generated'=>true,'phase'=>$template[0],'template_version'=>1)),
                'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now);
            $proposal=self::task_automation_proposal((object)$new_task,$automation_context);
            $new_task=array_merge($new_task,$proposal['update_payload']);
            $inserted=$wpdb->insert($table,$new_task);
            if(false===$inserted){return new WP_Error('mmc_ops_task_insert','Operasyon görevi oluşturulamadı; mevcut kayıtlar korunarak yeniden denenebilir.');}
            // Initialization is already recorded by the plan/program audit; task metadata identifies its origin.
        }
    }

    private static function ensure_close_tasks( $program_id ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_tasks'; $now=current_time('mysql');
        $program=MMC_Program_Service::get_program($program_id);
        if(!$program || in_array($program->status,array('cancelled','completed'),true)){return;}
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
