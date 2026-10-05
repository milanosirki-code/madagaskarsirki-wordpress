<?php
/**
 * Madagaskar Sirki – Yarım Kalan Ödeme Kaydı V1.1 (DENEME MODU)
 *
 * Ödemesi tamamlanmayan siparişleri izler ve "bu müşteriye hatırlatma
 * gönderilirdi / gönderilmezdi" kararını WooCommerce günlüğüne yazar.
 *
 * BU SÜRÜM:
 * - Hiçbir mesaj göndermez. İçinde gönderim kodu yoktur.
 * - Kommo'ya yazmaz.
 * - Siparişi, sipariş notlarını ve sipariş alanlarını değiştirmez.
 * - Yalnızca günlüğe satır yazar ve kısa ömürlü sayaç (transient) tutar.
 *
 * Kayıtlar: WooCommerce > Durum > Günlükler > "madagaskar-odeme-hatirlatma"
 *
 * Kurallar:
 * - Sipariş "ödeme bekliyor" veya "başarısız" durumunda 20 dakika kaldıysa
 *   değerlendirilir.
 * - Sipariş bu sürede ödendiyse veya iptal edildiyse: gönderilmezdi.
 * - Aynı telefon VE aynı etkinlik için daha sonra ödenmiş sipariş varsa: gönderilmezdi.
 * - İptal/kapalı program, V4 satış kilidi, başlamış seans ve satın alınamayan ürün dışlanır.
 * - Kalıcı gönderim kaydı dry-run kararından ayrı okunur; gerçek SEND kodu yoktur.
 * - Her sipariş için en fazla bir "gönderilirdi" kaydı yazılır.
 *
 * Geri alma: bu snippet'i devre dışı bırakmak yeterlidir.
 * Code Snippets kullanırken <?php etiketi eklemeyin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MS_OH_BEKLEME' ) ) {
    define( 'MS_OH_BEKLEME', 20 * 60 );
}

if ( ! defined( 'MS_OH_KANCA' ) ) {
    define( 'MS_OH_KANCA', 'ms_oh_kontrol' );
}

if ( ! defined( 'MS_OH_GRUP' ) ) {
    define( 'MS_OH_GRUP', 'madagaskar-odeme-hatirlatma' );
}

if ( ! defined( 'MS_OH_MAX_ERTELEME' ) ) {
    define( 'MS_OH_MAX_ERTELEME', 3 );
}


/**
 * Günlüğe yaz.
 */
if ( ! function_exists( 'ms_oh_log' ) ) {
    function ms_oh_log( $mesaj, $baglam = array() ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $baglam['source'] = MS_OH_GRUP;

        wc_get_logger()->info( '[DENEME MODU] ' . $mesaj, $baglam );
    }
}


/**
 * Sipariş için bir kontrol planla.
 */
if ( ! function_exists( 'ms_oh_planla' ) ) {
    function ms_oh_planla( $order_id, $ne_zaman = 0, $erteleme = 0 ) {
        $order_id = absint( $order_id );
        $erteleme = absint( $erteleme );

        if ( ! $order_id ) {
            return;
        }

        if ( ! $ne_zaman ) {
            $ne_zaman = time() + MS_OH_BEKLEME;
        }

        $args = array( $order_id, $erteleme );

        if ( function_exists( 'as_schedule_single_action' ) ) {

            if (
                function_exists( 'as_next_scheduled_action' ) &&
                as_next_scheduled_action( MS_OH_KANCA, $args, MS_OH_GRUP )
            ) {
                return;
            }

            as_schedule_single_action( $ne_zaman, MS_OH_KANCA, $args, MS_OH_GRUP );
            return;
        }

        if ( ! wp_next_scheduled( MS_OH_KANCA, $args ) ) {
            wp_schedule_single_event( $ne_zaman, MS_OH_KANCA, $args );
        }
    }
}


/**
 * Sipariş olaylarında yalnızca kontrol planlar.
 * Ödeme sayfasını hiçbir koşulda bozmaması için her hata yutulur.
 */
if ( ! function_exists( 'ms_oh_olay' ) ) {
    function ms_oh_olay( $order_id ) {
        try {
            ms_oh_planla( $order_id );
        } catch ( \Throwable $e ) {
            return;
        }
    }
}

add_action( 'woocommerce_new_order', 'ms_oh_olay', 99, 1 );
add_action( 'woocommerce_order_status_pending', 'ms_oh_olay', 99, 1 );
add_action( 'woocommerce_order_status_failed', 'ms_oh_olay', 99, 1 );


/** Canonical, country-aware phone identity; never compare arbitrary last10 digits. */
if (!function_exists('ms_oh_telefon')) {
    function ms_oh_telefon($raw) {
        $raw=trim((string)$raw);
        if (preg_match('/[^0-9+()\s.\-]/',$raw)) return '';
        if (strpos($raw,'+')!==false && (substr_count($raw,'+')!==1 || strpos($raw,'+')!==0)) return '';
        $digits=preg_replace('/\D+/','',$raw);
        if (preg_match('/^5[0-9]{9}$/D',$digits)) return '90'.$digits;
        if (preg_match('/^05[0-9]{9}$/D',$digits)) return '90'.substr($digits,1);
        if (preg_match('/^905[0-9]{9}$/D',$digits)) return $digits;
        if (preg_match('/^00905[0-9]{9}$/D',$digits)) return substr($digits,2);
        if (strpos($raw,'+')===0 || strpos($digits,'00')===0) {
            if (strpos($digits,'00')===0) $digits=substr($digits,2);
            return preg_match('/^[1-9][0-9]{7,14}$/D',$digits)?$digits:'';
        }
        return '';
    }
}

if (!function_exists('ms_oh_order_context')) {
    /** Identity and availability are read from owning models; every line must map. */
    function ms_oh_order_context($order,$now=null) {
        global $wpdb;
        $result=array('event_ids'=>array(),'session_ids'=>array(),'program_ids'=>array(),'reason_code'=>'','reason'=>'');
        $fail=function($code,$reason)use(&$result){if(!$result['reason_code'] || $code==='EVENT_CANCELLED'){$result['reason_code']=$code;$result['reason']=$reason;}};
        if (!class_exists('MDG_DB') || !class_exists('MDG_Events') || !class_exists('MDG_Sessions') || !method_exists('MDG_Sessions','sales_closed_by_time')) {
            $fail('MAPPING_MISSING','Kanonik etkinlik/seans kaynağı doğrulanamadı.');return $result;
        }
        $blocked=array('cancelled','sales_closed','closed','completed','postponed','soldout');
        $items=$order->get_items('line_item');
        if (!$items) {$fail('MAPPING_MISSING','Siparişin bilet kalemleri bulunamadı.');return $result;}
        foreach($items as$item) {
            $pid=(int)$item->get_product_id();$vid=(int)$item->get_variation_id();
            if (class_exists('MMC_Event_Service') && class_exists('MMC_Program_Service')) {
                $mappings=$wpdb->get_results($wpdb->prepare("SELECT program_id,event_id FROM {$wpdb->prefix}mmc_sales_mappings WHERE is_active=1 AND (wc_product_id=%d OR (wc_variation_id=%d AND wc_variation_id>0))",$pid,$vid));
                if ($wpdb->last_error) {$fail('MAPPING_MISSING','MMC eşlemesi doğrulanamadı.');return $result;}
                foreach((array)$mappings as$mapping) {
                    $result['program_ids'][]=(int)$mapping->program_id;
                    $program=MMC_Program_Service::get_program((int)$mapping->program_id);$event=MMC_Event_Service::get_event((int)$mapping->event_id);
                    if (!$program || !$event) {$fail('MAPPING_MISSING','MMC program/etkinlik bulunamadı.');continue;}
                    if (in_array((string)$program->status,$blocked,true) || in_array((string)$event->status,$blocked,true)) {
                        $cancelled=$program->status==='cancelled' || $event->status==='cancelled';
                        $fail($cancelled?'EVENT_CANCELLED':'SALES_CLOSED','iptal/kapalı MMC programı #'.(int)$mapping->program_id);
                    }
                }
            }
            $sessions=$wpdb->get_results($wpdb->prepare('SELECT s.*,t.is_active AS ticket_active FROM '.MDG_DB::table('ticket_types').' t INNER JOIN '.MDG_DB::table('sessions').' s ON s.id=t.session_id WHERE t.wc_variation_id=%d AND t.wc_variation_id>0',$vid));
            if ($wpdb->last_error) {$fail('MAPPING_MISSING','MDG bilet eşlemesi doğrulanamadı.');return $result;}
            $saved=$wpdb->get_results($wpdb->prepare('SELECT s.* FROM '.MDG_DB::table('order_map').' m INNER JOIN '.MDG_DB::table('sessions').' s ON s.id=m.session_id WHERE m.order_id=%d AND m.order_item_id=%d',(int)$order->get_id(),(int)$item->get_id()));
            if ($wpdb->last_error) {$fail('MAPPING_MISSING','MDG sipariş eşlemesi doğrulanamadı.');return $result;}
            $by_id=array();foreach(array_merge((array)$saved,(array)$sessions)as$s){$by_id[(int)$s->id]=$s;}
            if(count($by_id)!==1){$fail('MAPPING_MISSING','Bilet kaleminin seans eşlemesi eksik veya belirsiz.');continue;}
            $session=reset($by_id);$result['session_ids'][]=(int)$session->id;$result['event_ids'][]=(int)$session->event_id;
            if((int)$session->wc_product_id!==$pid){$fail('MAPPING_MISSING','Sipariş ana ürünü kanonik seansla eşleşmiyor.');}
            $event=MDG_Events::get((int)$session->event_id);
            if(!$event){$fail('MAPPING_MISSING','MDG etkinliği bulunamadı.');continue;}
            if($event->status==='cancelled'){$fail('EVENT_CANCELLED','İptal MDG etkinliği #'.(int)$event->id);}
            elseif($event->status!=='onsale' || $session->status!=='onsale'){$fail('SALES_CLOSED','Etkinlik/seans artık satışa açık değil.');}
            if(MDG_Sessions::sales_closed_by_time($session,$now)){$fail('SESSION_STARTED','Seans başladı; bilet satışı sona erdi.');}
            if('yes'===get_post_meta($pid,'_mdg_v371_sales_closed',true) || ($vid && 'yes'===get_post_meta($vid,'_mdg_v371_sales_closed',true))){$fail('SALES_CLOSED','V4 ürün/ana ürün satış kilidi kapalı.');}
            $product=function_exists('wc_get_product')?wc_get_product($vid?:$pid):false;
            if(isset($session->ticket_active) && !(int)$session->ticket_active){$fail('PRODUCT_UNAVAILABLE','Bilet tipi artık aktif değil.');}
            if(!$product || $product->get_status()!=='publish' || !$product->is_purchasable() || !$product->is_in_stock()){$fail('PRODUCT_UNAVAILABLE','Bilet ürünü artık satın alınamıyor.');}
        }
        foreach(array('event_ids','session_ids','program_ids')as$key){$result[$key]=array_values(array_unique($result[$key]));sort($result[$key],SORT_NUMERIC);}
        return $result;
    }
}

if (!function_exists('ms_oh_etkinlik_engeli')) {
    function ms_oh_etkinlik_engeli($order) {return ms_oh_order_context($order)['reason'];}
}

if (!function_exists('ms_oh_sonradan_odedi_mi')) {
    function ms_oh_sonradan_odedi_mi($order,$context=null) {
        global $wpdb;
        if(!function_exists('wc_get_orders'))throw new RuntimeException('Ödenmiş sipariş kaynağı doğrulanamadı.');
        $phone=ms_oh_telefon($order->get_billing_phone());$date=$order->get_date_created();
        if(!$phone || !$date)return false;
        $context=$context??ms_oh_order_context($order);
        if(!$context['event_ids'])return false;
        $page=1;
        do {
            $orders=wc_get_orders(array('status'=>function_exists('wc_get_is_paid_statuses')?wc_get_is_paid_statuses():array('processing','completed'),'date_created'=>'>='.$date->getTimestamp(),'limit'=>100,'page'=>$page,'orderby'=>'date','order'=>'ASC','return'=>'objects'));
            if(!is_array($orders) || $wpdb->last_error)throw new RuntimeException('Ödenmiş sipariş sorgusu başarısız.');
            foreach($orders as$candidate){
                if((int)$candidate->get_id()===(int)$order->get_id() || !$candidate->is_paid())continue;
                $created=$candidate->get_date_created();
                if(!$created || $created->getTimestamp()<$date->getTimestamp() || ($created->getTimestamp()===$date->getTimestamp() && (int)$candidate->get_id()<=(int)$order->get_id()))continue;
                if(!hash_equals($phone,ms_oh_telefon($candidate->get_billing_phone())))continue;
                $paid_context=ms_oh_order_context($candidate);
                if(array_intersect($context['event_ids'],$paid_context['event_ids']))return true;
            }
            $page++;
        }while(count($orders)===100);
        return false;
    }
}

if (!function_exists('ms_oh_dry_run_report')) {
    /** Read-only eligibility. Does not log, schedule, set markers or send anything. */
    function ms_oh_dry_run_report($order,$now=null) {
        $now=$now??time();$phone=ms_oh_telefon($order->get_billing_phone());$context=ms_oh_order_context($order,$now);
        $sent=(bool)$order->get_meta('_ms_oh_reminder_sent_at',true);
        $seen=get_transient('ms_oh_yazildi_'.(int)$order->get_id())==='gonderilirdi';
        $report=array('order_id'=>(int)$order->get_id(),'event_ids'=>$context['event_ids'],'session_ids'=>$context['session_ids'],'program_ids'=>$context['program_ids'],'payment_status'=>(string)$order->get_status(),'eligibility'=>'EXCLUDED','exclusion_reason'=>'','reason'=>'','phone_available'=>$phone!=='','phone_masked'=>$phone?'***'.substr($phone,-4):'','replacement_paid'=>null,'already_reminded'=>$sent,'already_evaluated'=>$seen,'mode'=>'DRY_RUN','real_send_count'=>0);
        $code=$context['reason_code'];$reason=$context['reason'];
        if(!in_array($order->get_status(),array('pending','failed'),true) || $order->is_paid()){$code='PAYMENT_NOT_ELIGIBLE';$reason='Sipariş ödenmiş, iptal edilmiş veya ödeme beklemiyor.';}
        if(!$code && $sent){$code='ALREADY_REMINDED';$reason='Bu sipariş için kalıcı gönderim kaydı var.';}
        if(!$code && $seen){$code='ALREADY_EVALUATED';$reason='Bu sipariş için dry-run uygunluk kararı daha önce kaydedildi; gerçek mesaj gönderildiği anlamına gelmez.';}
        if(!$code && !$phone){$code='PHONE_UNAVAILABLE';$reason='Geçerli ülke kodlu telefon bulunamadı.';}
        if(!$code && !$order->get_date_created()){$code='DATE_MISSING';$reason='Sipariş oluşturma zamanı doğrulanamadı.';}
        if(!$code){
            try{$report['replacement_paid']=ms_oh_sonradan_odedi_mi($order,$context);}
            catch(Throwable $e){$code='REPLACEMENT_CHECK_FAILED';$reason='Sonradan ödenmiş sipariş kontrolü doğrulanamadı.';}
            if($report['replacement_paid']){$code='REPLACEMENT_PAID';$reason='Aynı telefon ve etkinlik için daha sonra ödenmiş sipariş var.';}
        }
        if(!$code && !in_array($order->get_created_via(),array('checkout','store-api'),true)){$code='NOT_CHECKOUT';$reason='Sipariş ödeme sayfasından açılmamış.';}
        $modified=$order->get_date_modified();$elapsed=$modified?$now-$modified->getTimestamp():MS_OH_BEKLEME;
        if(!$code && $elapsed<MS_OH_BEKLEME-60){$code='WAITING';$reason='Ödeme bekleme süresi henüz dolmadı.';$report['eligibility']='DEFERRED';}
        if(!$code){$report['eligibility']='ELIGIBLE';$reason='Ödenmemiş, gelecekte ve satın alınabilir seans; yalnız dry-run.';}
        $report['exclusion_reason']=$code;$report['reason']=$reason;$report['wait_minutes']=(int)floor($elapsed/60);
        return $report;
    }
}

if (!function_exists('ms_oh_kontrol')) {
    function ms_oh_kontrol($order_id,$erteleme=0) {
        try{
            $order_id=absint($order_id);$erteleme=absint($erteleme);
            if(!$order_id || !function_exists('wc_get_order') || get_transient('ms_oh_yazildi_'.$order_id))return;
            $order=wc_get_order($order_id);
            if(!$order || $order->get_type()!=='shop_order' || $order->get_status()==='checkout-draft')return;
            $report=ms_oh_dry_run_report($order);
            if($report['exclusion_reason']==='WAITING' && $erteleme<MS_OH_MAX_ERTELEME){
                $modified=$order->get_date_modified();$elapsed=$modified?time()-$modified->getTimestamp():0;
                ms_oh_planla($order_id,time()+max(60,MS_OH_BEKLEME-$elapsed),$erteleme+1);return;
            }
            $eligible=$report['eligibility']==='ELIGIBLE';
            ms_oh_log('Sipariş #'.$order_id.': '.($eligible?'GÖNDERİLİRDİ':'GÖNDERİLMEZDİ').' ('.$report['reason'].').',$report);
            set_transient('ms_oh_yazildi_'.$order_id,$eligible?'gonderilirdi':'atlandi',3*DAY_IN_SECONDS);
        }catch(Throwable $e){return;}
    }
}
add_action(MS_OH_KANCA,'ms_oh_kontrol',10,2);
