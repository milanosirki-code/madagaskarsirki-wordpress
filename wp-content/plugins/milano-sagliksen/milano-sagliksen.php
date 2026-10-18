<?php
/**
 * Plugin Name: Milano Sağlık-Sen Kurumsal Kampanya
 * Description: Mevcut MDG kampanya sepetini ve Milano merkezi ödeme geçişini kullanır. Varsayılan satış kapalıdır.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
final class Milano_Sagliksen {
    const SCHEMA = 'milano_bundle_v1';
    const SHORTCODE = 'milano_sagliksen';
    const CENTRAL = 'https://madagaskarsirki.com';
    const STAGE = 'staging-f321-milanosirki-lgajf.wpcomstaging.com';
    const EVENTS = array(28=>array('date'=>'2026-10-21','sessions'=>array(181,182)),24=>array('date'=>'2026-10-22','sessions'=>array(154,155)),27=>array('date'=>'2026-10-23','sessions'=>array(179,180)),26=>array('date'=>'2026-10-24','sessions'=>array(176,177,178)));
    const STAGE_EVENTS = array(25=>array('date'=>'2026-10-21','sessions'=>array(152,153)),24=>array('date'=>'2026-10-22','sessions'=>array(150,151)),26=>array('date'=>'2026-10-23','sessions'=>array(154,155)),27=>array('date'=>'2026-10-24','sessions'=>array(156,157,158)));
    public static function scope() { return self::host()===self::STAGE?self::STAGE_EVENTS:self::EVENTS; }

    private static function host() { return strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST)); }
    public static function central() { return in_array(self::host(),array('madagaskarsirki.com',self::STAGE),true); }
    public static function record() {
        $rows=get_option('mdg_corporate_campaign_codes_v1',array());
        $r=is_array($rows)?($rows['sagliksen']??array()):array();
        // No auto-registration, auto-enabling, or fallback to another Sağlık-Sen campaign.
        return is_array($r)?$r:array();
    }
    public static function enabled($r) {
        return ($r['active']??false)===true && ($r['brand']??'')==='milano'
            && ($r['schema']??'')===self::SCHEMA && ($r['bundle_price']??0)===475
            && ($r['event_ids']??array())===array_keys(self::scope());
    }
    public static function selection($input) {
        if(!is_callable(array('MDG_Corporate_Campaigns_20261005','normalize'))){return array('error'=>'Kampanya bağımlılığı hazır değil.');}
        if(!is_array($input)){return array('error'=>'Kampanya seçimi geçersiz.');}
        $allowed=array('schema','code','event_id','session','packages','extra_children','extra_adults','action','mdg_campaign_nonce','mdg_campaign_action','_wp_http_referer');
        if(array_diff(array_keys($input),$allowed)){return array('error'=>'Fiyat ve bilet türü sunucuda belirlenir.');}
        if(!is_string($input['code']??null) || MDG_Corporate_Campaigns_20261005::normalize($input['code'])!=='sagliksen'){
            return array('error'=>'Kampanya kodu geçersiz.');
        }
        $m=array('schema'=>self::SCHEMA,'code'=>'sagliksen');
        foreach(array('event_id','session','packages','extra_children','extra_adults') as $k){
            $v=$input[$k]??null;
            if(!is_string($v)||!preg_match('/^(?:0|[1-9][0-9]{0,7})$/D',$v)){return array('error'=>'Geçerli kişi sayısı ve seans seçin.');}
            $m[$k]=$v;
        }
        if(!in_array($m['packages'],array('1','2'),true)||(int)$m['extra_children']>16||(int)$m['extra_adults']>18){return array('error'=>'Paket veya kişi sayısı geçersiz.');}
        return $m;
    }
    public static function quote_snapshot($m,$s,$types,$record) {
        $m=self::selection($m); if(isset($m['error'])){return $m;}
        if(!self::enabled($record)){return array('error'=>'Sağlık-Sen kampanyası henüz satışa açık değil.');}
        $eid=(int)$m['event_id'];$sid=(int)$m['session'];$allow=self::scope()[$eid]??null;
        if(!$allow||!in_array($sid,$allow['sessions'],true)||(int)($s['event_id']??0)!==$eid
            ||(int)($s['session_id']??0)!==$sid||($s['date']??'')!==$allow['date']||!empty($s['preview'])){
            return array('error'=>'Etkinlik veya seans kampanyaya ait değil.');
        }
        foreach(array('adult','child') as $role){
            $t=$types[$role]??array();
            if(empty($t['variation'])||empty($t['parent'])||empty($t['type_id'])||!is_numeric($t['price']??null)
                ||!is_finite((float)$t['price'])||(float)$t['price']<=0){return array('error'=>'Bilet fiyatı doğrulanamadı.');}
        }
        $n=(int)$m['packages'];$ec=(int)$m['extra_children'];$ea=(int)$m['extra_adults'];$units=3*$n+$ec+$ea;
        if(2*$n+$ec>20||$n+$ea>20||!isset($s['capacity_snapshot'])||$units>(int)$s['capacity_snapshot']){
            return array('error'=>'Kişi sayısı kalan kontenjana veya satış sınırına uygun değil.');
        }
        $discounted=$types['adult'];$discounted['price']=475.0;
        $plan=array('adult'=>array('qty'=>$n,'type'=>$discounted,'free'=>false),
            'free_child'=>array('qty'=>2*$n,'type'=>$types['child'],'free'=>true),
            'paid_child'=>array('qty'=>$ec,'type'=>$types['child'],'free'=>false),
            'extra_adult'=>array('qty'=>$ea,'type'=>$types['adult'],'free'=>false));
        return array('event'=>(object)array('id'=>$eid),'session'=>array('types'=>$types),'milano_bundle_plan'=>$plan,
            'units'=>$units,'total'=>round(475*$n+$ec*$types['child']['price']+$ea*$types['adult']['price'],2),
            'snapshot'=>$s);
    }
    public static function manifest_quote($m) {
        $m=self::selection($m);if(isset($m['error'])){return $m;}
        if(!self::central()||!is_callable(array('Milano_Shared_Checkout_Handoff','catalog'))||!class_exists('MDG_Sessions')
            ||!is_callable(array('MDG_Corporate_Campaigns_20261005','checkout_manifest'))){return array('error'=>'Merkezi kampanya altyapısı hazır değil.');}
        if(!self::enabled(self::record())){return array('error'=>'Sağlık-Sen kampanyası henüz satışa açık değil.');}
        $s=self::snapshot((int)$m['event_id'],(int)$m['session']);
        if(is_wp_error($s)){return array('error'=>$s->get_error_message());}
        $types=array();
        foreach((array)MDG_Sessions::ticket_types_by_session((int)$m['session']) as $t){
            $role=strtolower((string)$t->code);if(!in_array($role,array('adult','child'),true)||!(int)$t->is_active){continue;}
            if(isset($types[$role])||(int)$t->capacity_units!==1){return array('error'=>'Bilet türleri belirsiz.');}
            $types[$role]=array('variation'=>(int)$t->wc_variation_id,'parent'=>(int)$s['wc_product_id'],'type_id'=>(int)$t->id,'price'=>(float)$t->price);
        }
        return self::quote_snapshot($m,$s,$types,self::record());
    }
    public static function authorize_item($data,$pid,$vid,$qty,$eid) {
        if(!is_callable(array('MDG_Corporate_Campaigns_20261005','signed'))){return false;}
        if(($data['mdg_campaign_manifest']['schema']??'')!==self::SCHEMA||!MDG_Corporate_Campaigns_20261005::signed($data)){return false;}
        $q=self::manifest_quote($data['mdg_campaign_manifest']);if(isset($q['error'])||(int)$q['event']->id!==$eid){return false;}
        $p=$q['milano_bundle_plan'][$data['mdg_campaign_role']??'']??null;
        return $p && $p['qty']>0 && $p['qty']===(int)$qty && $p['type']['parent']===(int)$pid && $p['type']['variation']===(int)$vid
            && (int)($data['mdg_event_id']??0)===$eid && (int)($data['mdg_session_id']??0)===(int)$data['mdg_campaign_manifest']['session']
            && (int)($data['mdg_ticket_type_id']??0)===$p['type']['type_id'] && (int)($data['mdg_capacity_units']??0)===1;
    }
    public static function snapshot($eid,$sid) {
        // Use the installed handoff's public canonical catalogue. Do not replace its private mapping validator.
        $catalog=Milano_Shared_Checkout_Handoff::catalog();if(is_wp_error($catalog)){return $catalog;}$d=$catalog->get_data();
        foreach($d['sessions']??array() as $s){
            if((int)$s['event_id']!==$eid||(int)$s['session_id']!==$sid){continue;}
            global $wpdb;
            $pid=(int)$wpdb->get_var($wpdb->prepare("SELECT wc_product_id FROM {$wpdb->prefix}mdg_sessions WHERE id=%d AND event_id=%d",$sid,$eid));
            if(!$pid){break;}$s['wc_product_id']=$pid;return $s;
        }
        return new WP_Error('session','Doğrulanmış satışa açık Milano seansı bulunamadı.');
    }
    public static function handoff_validation($passed,$pid,$qty,$vid=0,$vars=array(),$data=array()) {
        if(!$passed){return false;}
        if(self::authorize_item($data,$pid,$vid,$qty,(int)($data['mdg_event_id']??0))){return true;}
        return Milano_Shared_Checkout_Handoff::validate_cart_entry($passed,$pid,$qty,$vid,$vars,$data);
    }
    public static function handoff_mark($data,$pid,$vid,$qty) {
        if(self::authorize_item($data,$pid,$vid,$qty,(int)($data['mdg_event_id']??0))){$data['milano_handoff']=1;}
        return $data;
    }
    public static function validate_cart() {
        if(!function_exists('WC')||!WC()->cart){return;}
        $has=false;$other=false;
        foreach(WC()->cart->get_cart() as $i){if(($i['mdg_campaign_manifest']['schema']??'')===self::SCHEMA){$has=true;}else{$other=true;}}
        if($has&&($other||WC()->cart->get_applied_coupons())){wc_add_notice('Sağlık-Sen paketi başka seçim veya kuponla birleştirilemez.','error');}
    }
    public static function validate_order($order) {
        if(!function_exists('WC')||!WC()->cart){return;}
        $has=false;
        foreach(WC()->cart->get_cart() as $i){if(($i['mdg_campaign_manifest']['schema']??'')===self::SCHEMA){$has=true;}}
        if(!$has){return;}
        foreach(WC()->cart->get_cart() as $i){if(($i['mdg_campaign_manifest']['schema']??'')!==self::SCHEMA){throw new Exception('Kampanya başka seçimle birleştirilemez.');}}
        if(WC()->cart->get_applied_coupons()){throw new Exception('Kampanya kuponlarla birleştirilemez.');}
    }
    public static function coupon($valid) {
        if(function_exists('WC')&&WC()->cart){foreach(WC()->cart->get_cart() as $i){if(($i['mdg_campaign_manifest']['schema']??'')===self::SCHEMA){return false;}}}
        return $valid;
    }
    public static function checkout() {
        if(!is_callable(array('MDG_Corporate_Campaigns_20261005','checkout_manifest'))){wp_die('Kampanya bağımlılığı hazır değil.');}
        check_admin_referer(MDG_Corporate_Campaigns_20261005::SHORTCODE,'mdg_campaign_nonce');
        $m=self::selection(wp_unslash($_POST));if(isset($m['error'])){wp_die(esc_html($m['error']));}
        // This checks the gate, canonical brand/mapping/window/stock/capacity afresh.
        $q=self::manifest_quote($m);if(isset($q['error'])){wp_die(esc_html($q['error']));}
        if(function_exists('wc_load_cart')&&(!WC()->cart||!WC()->session)){wc_load_cart();}
        if(!WC()->cart||WC()->cart->get_applied_coupons()){wp_die('Kampanya için kuponsuz bir sepet gereklidir.');}
        MDG_Corporate_Campaigns_20261005::checkout_manifest($m);
    }
    public static function catalog() {
        if(!self::central()||!self::enabled(self::record())||!is_callable(array('Milano_Shared_Checkout_Handoff','catalog'))
            ||!is_callable(array('MDG_Corporate_Campaigns_20261005','checkout_manifest'))){return new WP_Error('closed','Kampanya henüz satışa açık değil.',array('status'=>503));}
        $r=Milano_Shared_Checkout_Handoff::catalog();if(is_wp_error($r)){return $r;}$d=$r->get_data();$rows=array();
        foreach($d['sessions']??array() as $s){$a=self::scope()[(int)$s['event_id']]??null;if($a&&$a['date']===$s['date']&&in_array((int)$s['session_id'],$a['sessions'],true)&&$s['capacity_snapshot']>=3){$rows[]=$s;}}
        return rest_ensure_response(array('brand'=>'milano','sessions'=>$rows));
    }
    public static function render() {
        $base=self::host()===self::STAGE?home_url():self::CENTRAL;
        if(self::central()){$r=self::catalog();$d=is_wp_error($r)?array():$r->get_data();}
        else{$r=wp_remote_get($base.'/wp-json/milano-sagliksen/v1/catalog',array('timeout'=>8,'redirection'=>0));$d=!is_wp_error($r)&&wp_remote_retrieve_response_code($r)===200?json_decode(wp_remote_retrieve_body($r),true):array();}
        $rows=($d['brand']??'')==='milano'?($d['sessions']??array()):array();
        if(!$rows){return '<p role="status">Sağlık-Sen kampanyası test aşamasındadır; henüz satışa açık değildir.</p>';}
        ob_start();include __DIR__.'/view.php';return ob_get_clean();
    }
    public static function landing() {
        if(!self::central()||!isset($_GET['milano_campaign'])){return;}
        nocache_headers();
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){wp_die('Kampanya sayfasından seçim yapın.','Milano',array('response'=>405));}
        $m=self::selection(wp_unslash($_POST));$q=isset($m['error'])?$m:self::manifest_quote($m);
        if(isset($q['error'])){wp_die(esc_html($q['error']),'Milano kampanyası',array('response'=>409));}
        get_header();echo '<main class="milano-campaign" style="max-width:820px;margin:32px auto;padding:24px"><h1>Milano Sirki — Sağlık-Sen Ortak Ödeme</h1><p>'.esc_html($q['snapshot']['title'].' · '.$q['snapshot']['date'].' · '.$q['snapshot']['time']).'</p><p>'.(int)$q['units'].' ayrı giriş hakkı · Bilet toplamı: <strong>'.esc_html(number_format_i18n($q['total'],2)).' TL</strong></p><p>Ödemeniz Dünya Organizasyon’un madagaskarsirki.com üzerindeki merkezi altyapısında tamamlanır. Biletiniz Milano Sirki içindir. Varsa hizmet bedeli ödeme özetinde, ödeme onayından önce ayrıca gösterilir.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field(MDG_Corporate_Campaigns_20261005::SHORTCODE,'mdg_campaign_nonce',false);
        foreach($m as $k=>$v){echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';}
        echo '<button name="action" value="milano_sagliksen_checkout" type="submit">Güvenli ödeme ekranına devam et</button></form></main>';get_footer();exit;
    }
    public static function boot() {
        add_shortcode(self::SHORTCODE,array(__CLASS__,'render'));
        if(!self::central()){return;}
        if(is_callable(array('Milano_Shared_Checkout_Handoff','validate_cart_entry'))){
            remove_filter('woocommerce_add_to_cart_validation',array('Milano_Shared_Checkout_Handoff','validate_cart_entry'),90);
            add_filter('woocommerce_add_to_cart_validation',array(__CLASS__,'handoff_validation'),90,6);
            add_filter('woocommerce_add_cart_item_data',array(__CLASS__,'handoff_mark'),61,4);
        }
        add_action('template_redirect',array(__CLASS__,'landing'),0);
        add_action('rest_api_init',static function(){register_rest_route('milano-sagliksen/v1','/catalog',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'catalog')));});
        add_action('admin_post_milano_sagliksen_checkout',array(__CLASS__,'checkout'));
        add_action('admin_post_nopriv_milano_sagliksen_checkout',array(__CLASS__,'checkout'));
        add_action('woocommerce_check_cart_items',array(__CLASS__,'validate_cart'),95);
        add_action('woocommerce_checkout_create_order',array(__CLASS__,'validate_order'),2);
        add_action('woocommerce_store_api_checkout_order_processed',array(__CLASS__,'validate_order'),2);
        add_filter('woocommerce_coupon_is_valid',array(__CLASS__,'coupon'),20);
    }
}
add_action('plugins_loaded',array('Milano_Sagliksen','boot'),45);
