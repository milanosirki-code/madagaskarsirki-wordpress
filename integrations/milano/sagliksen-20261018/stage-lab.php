<?php
/**
 * Plugin Name: Milano Sağlık-Sen İzole Prova Laboratuvarı
 * Version: 0.1.1
 * Description: Yalnız belirtilen staging hostunda; Code Snippets otomasyonlarını etkinleştirmeden kampanya kaynaklarını test eder.
 */
if(!defined('ABSPATH')){exit;}
if(strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))!=='staging-f321-milanosirki-lgajf.wpcomstaging.com'){return;}
require_once __DIR__.'/corporate.php';
require_once __DIR__.'/milano-sagliksen.php';
add_action('rest_api_init',static function(){
    register_rest_route('milano-stage-lab/v1','/preflight',array('methods'=>'GET','permission_callback'=>static function(){return current_user_can('manage_woocommerce');},'callback'=>static function(){
        $out=array('host'=>wp_parse_url(home_url('/'),PHP_URL_HOST),'paytr_test'=>get_option('woocommerce_paytr_payment_gateway_settings',array())['test']??null,'events'=>array());
        foreach(Milano_Sagliksen::STAGE_EVENTS as $eid=>$scope){
            $e=MDG_Events::get($eid);$r=MDG_Production_Readiness::check_event($e);
            $out['events'][]=array('id'=>$eid,'title'=>$e->title??null,'status'=>$e->status??null,'ready'=>$r['ready']??false,'errors'=>$r['errors']??array(),'sales_plan'=>$r['sales_plan']??null);
        }
        return rest_ensure_response($out);
    }));
});

// Content-only fixture repair. Never use the draft editor, which recreates sessions.
final class Milano_Sagliksen_Stage_Content {
    const TEXT = 'Milano Sirki, 22 Ekim 2026’da Yalova 90. Yıl Kapalı Spor Salonu’nda! 18:00 ve 19:30 seanslarında ailece geleneksel sirk sanatları gösterisini izleyin. Biletiniz seçtiğiniz seans için geçerlidir.';
    public static function repair() {
        if(strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))!==Milano_Sagliksen::STAGE
            || !current_user_can('manage_options') || !current_user_can('manage_woocommerce')
            || (get_option('woocommerce_paytr_payment_gateway_settings',array())['test']??'')!=='yes') {
            return new WP_Error('scope','Yalnız yetkili test ortamı kullanılabilir.');
        }
        $event=MDG_Events::get(24);
        if(!$event || $event->title!=='Milano Sirki — Yalova' || $event->status!=='draft'
            || empty($event->hero_attachment_id)) {return new WP_Error('event','Yalova taslak kimliği doğrulanamadı.');}
        $sessions=MDG_Sessions::by_event(24);
        $ids=array_map(static function($s){return (int)$s->id;},$sessions);sort($ids);
        if($ids!==array(150,151)){return new WP_Error('sessions','Mevcut seans eşleşmeleri değişmiş.');}
        if((string)$event->short_description===self::TEXT){return array('status'=>'already_complete');}
        if(!empty($event->short_description)||!empty($event->long_description)){return new WP_Error('content','Mevcut içerik korunuyor.');}
        $backup=array('short_description'=>$event->short_description,'long_description'=>$event->long_description,'updated_at'=>$event->updated_at,'sessions_sha256'=>hash('sha256',wp_json_encode($sessions)));
        if(!add_option('milano_stage_yalova_content_before_v2',$backup,'',false)
            && get_option('milano_stage_yalova_content_before_v2')!==$backup){return new WP_Error('backup','Geri dönüş kaydı farklı; işlem durduruldu.');}
        global $wpdb;
        $ok=$wpdb->update(MDG_DB::table('events'),array('short_description'=>self::TEXT),
            array('id'=>24,'status'=>'draft','short_description'=>$event->short_description,'long_description'=>$event->long_description,'updated_at'=>$event->updated_at));
        if($ok!==1){return new WP_Error('write','Eşzamanlı değişiklik veya kayıt hatası.');}
        if(hash('sha256',wp_json_encode(MDG_Sessions::by_event(24)))!==$backup['sessions_sha256']){return new WP_Error('sessions_changed','Seanslar başka bir işlem tarafından değişti.');}
        return array('status'=>'complete','session_ids'=>$ids);
    }
    public static function page() {
        if(!current_user_can('manage_options')||!current_user_can('manage_woocommerce')){wp_die('Yetkiniz yok.');}
        $message='';
        if(isset($_POST['milano_stage_content'])){
            check_admin_referer('milano_stage_content');$result=self::repair();
            $message=is_wp_error($result)?$result->get_error_message():'Yalova test tanıtımı tamamlandı; mevcut seanslar korundu.';
        }
        echo '<div class="wrap"><h1>Milano — Yalova test tanıtımı</h1><p>'.esc_html($message).'</p><p>'.esc_html(self::TEXT).'</p><p>Yalnız boş tanıtım alanı tamamlanır. Seanslar, fiyatlar, ürünler ve siparişler değiştirilmez.</p><form method="post">';
        wp_nonce_field('milano_stage_content');echo '<button class="button button-primary" name="milano_stage_content" value="1">Yalova test tanıtımını tamamla</button></form></div>';
    }
}
add_action('admin_menu',static function(){add_management_page('Milano Yalova test tanıtımı','Milano Yalova test tanıtımı','manage_options','milano-stage-content',array('Milano_Sagliksen_Stage_Content','page'));});
// Staging lab never sends order emails or CRM messages to real recipients.
add_filter('pre_wp_mail',static function(){return true;},PHP_INT_MAX);
add_filter('pre_http_request',static function($pre,$args,$url){
    $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST));
    if(preg_match('/(^|\.)(kommo\.com|amocrm\.ru|facebook\.com)$/D',$host)){return new WP_Error('stage_external','Prova ortamında CRM/reklam gönderimi kapalı.');}
    return $pre;
},PHP_INT_MAX,3);
