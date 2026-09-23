<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Marketing_Service {
    public static function hooks() {
        add_action( 'mmc_program_logged', array( __CLASS__, 'on_program_log' ), 20, 7 );
    }

    public static function item_statuses() {
        return array(
            'draft'        => 'Taslak',
            'review'       => 'Onay Bekliyor',
            'approved'     => 'Onaylandı',
            'published'    => 'Yayınlandı',
            'needs_update' => 'Güncelleme Gerekli',
            'retired'      => 'Pasif / Kaldırıldı',
            'error'        => 'Hata',
        );
    }

    public static function item_types() {
        return array(
            'poster'         => 'Afiş Briefi',
            'instagram_post' => 'Instagram Gönderisi',
            'instagram_story'=> 'Instagram Story',
            'reel'           => 'Reels',
            'countdown'      => 'Geri Sayım',
            'giveaway'       => 'Çekiliş',
            'meta_ad'        => 'Meta Reklam Metni',
        );
    }

    public static function ensure_pack( $program_id, $force = false ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $snapshot = self::snapshot( $program_id );
        if ( is_wp_error( $snapshot ) ) return $snapshot;
        if ( empty( $snapshot['ready'] ) ) return new WP_Error( 'mmc_marketing_not_ready', implode( ' ', $snapshot['errors'] ) );

        $table = $wpdb->prefix . 'mmc_marketing_items';
        $hash = self::source_hash( $snapshot );
        foreach ( self::item_types() as $type => $label ) {
            $latest = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table WHERE program_id=%d AND item_type=%s ORDER BY version_no DESC,id DESC LIMIT 1",
                $program_id, $type
            ) );
            if ( $latest && ! $force && hash_equals( (string) $latest->source_hash, $hash ) ) continue;

            $content = self::generate_item( $type, $snapshot );
            if ( $latest && in_array( $latest->status, array( 'approved','published' ), true ) ) {
                $wpdb->update( $table, array( 'status'=>'needs_update', 'updated_at'=>current_time('mysql') ), array( 'id'=>(int)$latest->id ) );
            }
            $version = $latest ? (int)$latest->version_no + 1 : 1;
            $now = current_time('mysql');
            $wpdb->insert( $table, array(
                'program_id'=>$program_id,
                'event_id'=>(int)$snapshot['event']->id,
                'item_type'=>$type,
                'channel'=>self::channel_for_type($type),
                'version_no'=>$version,
                'title'=>$content['title'],
                'body'=>$content['body'],
                'brief'=>$content['brief'],
                'status'=>'draft',
                'source_hash'=>$hash,
                'created_by'=>get_current_user_id() ?: null,
                'created_at'=>$now,
                'updated_at'=>$now,
            ) );
        }
        self::ensure_meta_plan( $program_id, $snapshot, $hash );
        MMC_Program_Service::add_log( $program_id, 'marketing_pack_generated', 'marketing', null, null, array('source_hash'=>$hash), 'Afiş, sosyal medya ve Meta hazırlık paketi güncellendi.' );
        return true;
    }

    public static function items( $program_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mmc_marketing_items WHERE program_id=%d ORDER BY item_type ASC,version_no DESC,id DESC",
            absint($program_id)
        ) );
    }

    public static function latest_items( $program_id ) {
        $rows = self::items( $program_id ); $out=array();
        foreach ( $rows as $row ) if ( ! isset($out[$row->item_type]) ) $out[$row->item_type]=$row;
        return $out;
    }

    public static function get_item( $item_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_marketing_items WHERE id=%d", absint($item_id) ) );
    }

    public static function save_item( $item_id, $data ) {
        global $wpdb;
        $row = self::get_item($item_id); if(!$row) return new WP_Error('mmc_marketing_item_missing','İçerik bulunamadı.');
        $status = sanitize_key($data['status'] ?? $row->status);
        if ( ! isset(self::item_statuses()[$status]) ) $status=$row->status;
        $update=array(
            'title'=>sanitize_text_field($data['title'] ?? $row->title),
            'body'=>sanitize_textarea_field($data['body'] ?? $row->body),
            'brief'=>sanitize_textarea_field($data['brief'] ?? $row->brief),
            'status'=>$status,
            'scheduled_at'=>self::dt_or_null($data['scheduled_at'] ?? ''),
            'external_url'=>esc_url_raw($data['external_url'] ?? $row->external_url),
            'updated_at'=>current_time('mysql'),
        );
        if ( 'approved' === $status && 'approved' !== $row->status ) { $update['approved_by']=get_current_user_id() ?: null; $update['approved_at']=current_time('mysql'); }
        if ( 'published' === $status && 'published' !== $row->status ) { $update['published_at']=current_time('mysql'); }
        $ok=$wpdb->update($wpdb->prefix.'mmc_marketing_items',$update,array('id'=>(int)$row->id));
        if(false===$ok) return new WP_Error('mmc_marketing_save_failed','İçerik kaydedilemedi.');
        MMC_Program_Service::add_log($row->program_id,'marketing_item_updated','marketing_item',$row->id,$row->status,$status,$row->item_type);
        return true;
    }

    public static function meta_plan( $program_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_meta_plans WHERE program_id=%d LIMIT 1",absint($program_id)) );
    }

    public static function save_meta_plan( $program_id, $data ) {
        global $wpdb; $plan=self::meta_plan($program_id); if(!$plan) return new WP_Error('mmc_meta_plan_missing','Meta planı bulunamadı.');
        $spend=self::money($data['spend'] ?? $plan->spend); $purchases=absint($data['purchases'] ?? $plan->purchases); $revenue=self::money($data['revenue'] ?? $plan->revenue);
        $cpa=$purchases>0 ? $spend/$purchases : 0; $roas=$spend>0 ? $revenue/$spend : 0;
        $status=sanitize_key($data['status'] ?? $plan->status); if(!in_array($status,array('draft','review','approved','active','paused','stop_required','completed'),true)) $status=$plan->status;
        $ok=$wpdb->update($wpdb->prefix.'mmc_meta_plans',array(
            'campaign_name'=>sanitize_text_field($data['campaign_name'] ?? $plan->campaign_name),
            'geo_summary'=>sanitize_text_field($data['geo_summary'] ?? $plan->geo_summary),
            'audience_notes'=>sanitize_textarea_field($data['audience_notes'] ?? $plan->audience_notes),
            'daily_budget'=>self::money($data['daily_budget'] ?? $plan->daily_budget),
            'total_budget'=>self::money($data['total_budget'] ?? $plan->total_budget),
            'start_at'=>self::dt_or_null($data['start_at'] ?? $plan->start_at),
            'end_at'=>self::dt_or_null($data['end_at'] ?? $plan->end_at),
            'status'=>$status,
            'external_campaign_id'=>sanitize_text_field($data['external_campaign_id'] ?? $plan->external_campaign_id),
            'external_adset_id'=>sanitize_text_field($data['external_adset_id'] ?? $plan->external_adset_id),
            'external_ad_id'=>sanitize_text_field($data['external_ad_id'] ?? $plan->external_ad_id),
            'spend'=>$spend,'purchases'=>$purchases,'revenue'=>$revenue,'cpa'=>$cpa,'roas'=>$roas,
            'last_synced_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),
        ),array('id'=>(int)$plan->id));
        if(false===$ok) return new WP_Error('mmc_meta_save_failed','Meta planı kaydedilemedi.');
        MMC_Program_Service::add_log($program_id,'meta_plan_updated','marketing', $plan->id, $plan->status,$status,'Meta reklam planı güncellendi.');
        return true;
    }

    public static function on_program_log( $program_id, $action, $entity_type, $entity_id, $old_value, $new_value, $note ) {
        if(!$program_id || 0===strpos((string)$action,'marketing_') || 0===strpos((string)$action,'meta_')) return;
        $interesting=array('program','event','session','ticket_type','integration','program_venue');
        if(!in_array($entity_type,$interesting,true)) return;
        if('program_status_changed'===$action && 'cancelled'===$new_value){ self::handle_cancel($program_id); return; }
        $snapshot=self::snapshot($program_id);
        if(is_wp_error($snapshot) || empty($snapshot['ready'])) return;
        self::ensure_pack($program_id,false);
        if(in_array($action,array('event_updated','session_created','session_deleted','ticket_type_updated','program_status_changed'),true)) self::ensure_update_task($program_id,$action,'high');
    }

    public static function handle_cancel( $program_id ) {
        global $wpdb; $now=current_time('mysql');
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}mmc_marketing_items SET status='retired',updated_at=%s WHERE program_id=%d AND status IN ('draft','review','approved','published','needs_update')",$now,absint($program_id)));
        $wpdb->update($wpdb->prefix.'mmc_meta_plans',array('status'=>'stop_required','updated_at'=>$now),array('program_id'=>absint($program_id)));
        self::ensure_update_task($program_id,'cancelled','critical','Tüm Meta reklamlarını durdur; eski afiş, post, story ve Reels içeriklerini aktif yayınlardan kaldır.');
    }

    private static function ensure_update_task($program_id,$reason,$priority='high',$title='Pazarlama içeriklerini güncel program bilgileriyle kontrol et'){
        global $wpdb; $table=$wpdb->prefix.'mmc_tasks';
        $exists=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE program_id=%d AND module='marketing' AND status='open' AND title=%s LIMIT 1",absint($program_id),$title));
        if($exists) return;
        $wpdb->insert($table,array('program_id'=>absint($program_id),'module'=>'marketing','title'=>$title,'status'=>'open','priority'=>$priority,'metadata'=>wp_json_encode(array('reason'=>$reason),JSON_UNESCAPED_UNICODE),'created_by'=>get_current_user_id()?:null,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
    }

    private static function ensure_meta_plan( $program_id, $snapshot, $hash ) {
        global $wpdb; $table=$wpdb->prefix.'mmc_meta_plans'; $existing=self::meta_plan($program_id); $event=$snapshot['event']; $program=$snapshot['program'];
        $date=$event->event_date ? wp_date('d.m.Y',strtotime($event->event_date)) : '';
        $district=$program->district_name ?: $program->province_name;
        $name='MDG | '.strtoupper($district).' | '.$date.' | SALES';
        $geo=self::target_geo($program_id,$program);
        $utm=sanitize_title('mdg-'.$district.'-'.($event->event_date?:$program->planned_date));
        $start=$event->event_date ? wp_date('Y-m-d 09:00:00',strtotime($event->event_date.' -10 days')) : null;
        $end=$event->event_date ? wp_date('Y-m-d 23:00:00',strtotime($event->event_date)) : null;
        $data=array('event_id'=>(int)$event->id,'campaign_name'=>$name,'objective'=>'sales','geo_summary'=>$geo,'audience_notes'=>'Yerel aile eğlencesi; hedef coğrafya Program Hazırlık Dashboardu tanıtım havzasından gelir.','start_at'=>$start,'end_at'=>$end,'utm_source'=>'meta','utm_medium'=>'paid_social','utm_campaign'=>$utm,'source_hash'=>$hash,'updated_at'=>current_time('mysql'));
        if(!$existing){ $data['program_id']=absint($program_id);$data['status']='draft';$data['created_at']=current_time('mysql');$wpdb->insert($table,$data); }
        elseif(!hash_equals((string)$existing->source_hash,$hash)){
            if(in_array($existing->status,array('active','approved'),true)){ $data['status']='stop_required'; self::ensure_update_task($program_id,'meta_source_changed','critical','Aktif/Onaylı Meta reklamını yeni tarih, salon, seans ve fiyatlarla kontrol et; eski kreatifi durdur.'); }
            else $data['status']='draft';
            $wpdb->update($table,$data,array('id'=>(int)$existing->id));
        }
    }

    private static function generate_item($type,$s){
        $p=$s['program'];$e=$s['event'];$v=$s['venue'];$sessions=$s['sessions'];$tickets=$s['tickets'];
        $place=$p->district_name ?: $p->province_name; $date=$e->event_date?wp_date('d F Y',strtotime($e->event_date)):'';
        $times=implode(' • ',array_map(function($x){return wp_date('H:i',strtotime($x->session_time));},$sessions));
        $prices=array(); foreach($tickets as $t){ if((int)$t->is_active) $prices[]=$t->ticket_name.' '.number_format_i18n((float)$t->price,0).' TL'; }
        $price_text=implode(' • ',$prices); $venue=$v?$v->venue_name:'';
        $base="{$place}’de Madagaskar Sirki! 🎪\n{$date} • {$venue}\nSeanslar: {$times}\n{$price_text}\n🎟️ Bilet: madagaskarsirki.com/bilet-al/\nUluslararası sanatçılar • Hayvansız modern sirk • Aile eğlencesi";
        switch($type){
            case 'poster': return array('title'=>"{$place} Afiş Briefi",'body'=>'','brief'=>"1080×1350 ana post ve 1080×1920 story. Üstte MADAGASKAR SİRKİ; belirgin şehir: {$place}; tarih: {$date}; salon: {$venue}; seanslar: {$times}; fiyatlar: {$price_text}. Hayvan görseli kullanma. Uluslararası akrobasi ve aile deneyimini öne çıkar. CTA: BİLET AL — madagaskarsirki.com/bilet-al/.");
            case 'instagram_story': return array('title'=>"{$place} Story",'body'=>"🎪 MADAGASKAR SİRKİ {$place}’DE!\n📅 {$date}\n📍 {$venue}\n🕒 {$times}\n🎟️ Bilet için: madagaskarsirki.com/bilet-al/",'brief'=>'1080×1920; büyük şehir/tarih; alt bölümde bilet CTA.');
            case 'reel': return array('title'=>"{$place} Reels",'body'=>"{$place}, hazır mısın? 🎪 {$date} tarihinde Madagaskar Sirki geliyor. Uluslararası akrobasi, palyaço ve ailece eğlence için seansını seç. Biletler madagaskarsirki.com’da.",'brief'=>'20–30 sn. İlk 3 sn şehir+tarih. Hızlı akrobasi/palyaço/hula hoop kesitleri. Son 5 sn salon+seans+bilet CTA.');
            case 'countdown': return array('title'=>"{$place} Geri Sayım",'body'=>"⏳ {$place} için geri sayım başladı!\n{$date} • {$venue}\nSeanslar: {$times}\nBilet: madagaskarsirki.com/bilet-al/",'brief'=>'Etkinliğe 7, 3 ve 1 gün kala aynı şablonun tarih sayacı varyasyonları.');
            case 'giveaway': return array('title'=>"{$place} Çekiliş Taslağı",'body'=>"🎉 {$place} ÇEKİLİŞİ!\nMadagaskar Sirki için çift kişilik bilet kazanma şansı. Gönderiyi beğen, bir arkadaşını etiketle ve @madagaskarsirkiturkiye hesabını takip et. Sonuç/katılım koşulları yayın öncesi yönetici tarafından kesinleştirilecektir.",'brief'=>'Varsayılan olarak yayınlama. Ödül adedi, son katılım ve sonuç tarihi yönetici onayı olmadan eklenmez.');
            case 'meta_ad': return array('title'=>"{$place} Meta Reklam",'body'=>"🎪 Madagaskar Sirki {$place}’de! {$date} • {$venue}. Uluslararası sanatçılarla hayvansız modern sirk deneyimi. Seansını seç, biletini şimdi al.",'brief'=>'Amaç: bilet satışı. Tek kreatif farklı şehirlerde kullanılıyorsa şehir/tarih/salon/fiyat metni mutlaka bu Program Dosyasından gelsin.');
            default: return array('title'=>"{$place} Instagram Gönderisi",'body'=>$base,'brief'=>'1080×1350; şehir/tarih/salon/seans net; sıcak, kısa, aile dostu dil.');
        }
    }

    private static function snapshot($program_id){
        $program=MMC_Program_Service::get_program($program_id); if(!$program) return new WP_Error('mmc_marketing_program_missing','Program bulunamadı.');
        $event=class_exists('MMC_Event_Service')?MMC_Event_Service::event_for_program($program_id):null;
        $errors=array(); if(!$event)$errors[]='Etkinlik kaydı yok.'; elseif(!$event->event_date)$errors[]='Etkinlik tarihi kesin değil.';
        $venue=($event&&$event->program_venue_id&&class_exists('MMC_Venue_Service'))?MMC_Venue_Service::get_program_venue($event->program_venue_id):null; if(!$venue)$errors[]='Kesin salon yok.';
        $sessions=$event?MMC_Event_Service::sessions($event->id):array(); if(!$sessions)$errors[]='Seans yok.';
        $tickets=$event?MMC_Event_Service::ticket_types($event->id):array(); $active=array_filter($tickets,function($t){return (int)$t->is_active && (float)$t->price>=0;}); if(!$active)$errors[]='Aktif bilet/fiyat yok.';
        return array('ready'=>empty($errors),'errors'=>$errors,'program'=>$program,'event'=>$event,'venue'=>$venue,'sessions'=>$sessions,'tickets'=>$tickets);
    }
    private static function source_hash($s){$data=array('p'=>$s['program']->program_code,'ps'=>$s['program']->status,'e'=>$s['event']->event_date,'es'=>$s['event']->status,'v'=>$s['venue']?$s['venue']->venue_name:'','a'=>$s['venue']?$s['venue']->address:'','s'=>array_map(function($x){return array($x->session_time,(int)$x->capacity);},$s['sessions']),'t'=>array_map(function($x){return array($x->ticket_code,(float)$x->price,(int)$x->is_active);},$s['tickets']));return hash('sha256',wp_json_encode($data));}
    private static function channel_for_type($type){return in_array($type,array('instagram_post','instagram_story','reel','countdown','giveaway'),true)?'instagram':('meta_ad'===$type?'meta':'creative');}
    private static function target_geo($program_id,$program){global $wpdb;$rows=$wpdb->get_col($wpdb->prepare("SELECT district_name FROM {$wpdb->prefix}mmc_program_target_districts WHERE program_id=%d AND is_selected=1 ORDER BY district_name",absint($program_id)));if(!$rows)$rows=array($program->district_name?:$program->province_name);return implode(', ',$rows);}
    private static function dt_or_null($v){$v=trim((string)$v);if(!$v)return null;$ts=strtotime($v);return $ts?wp_date('Y-m-d H:i:s',$ts):null;}
    private static function money($v){
        $v=preg_replace('/[^0-9,.-]/','',trim((string)$v));
        if(strpos($v,',')!==false && strpos($v,'.')!==false){
            if(strrpos($v,',')>strrpos($v,'.')){$v=str_replace('.','',$v);$v=str_replace(',','.',$v);}else{$v=str_replace(',','',$v);}
        }elseif(strpos($v,',')!==false){$v=str_replace(',','.',$v);}
        return max(0,(float)$v);
    }
}
