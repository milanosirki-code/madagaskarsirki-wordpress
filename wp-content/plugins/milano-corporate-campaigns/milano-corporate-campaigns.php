<?php
/**
 * Plugin Name: Milano Sirki Kurumsal Kampanyalar
 * Description: Ortak kurumsal kampanya ekranını Milano markasıyla kullanır; fiyat ve yaş kurallarını ortak motora bırakır.
 * Version: 0.2.1
 */
if(!defined('ABSPATH')){exit;}
final class Milano_Corporate_Campaigns {
    const SHORTCODE='milano_corporate_campaigns';
    private static $previous_validation=null;
    public static function is_manifest($m) {
        if(!is_array($m) || isset($m['schema']) || !is_string($m['code']??null)
            || !is_callable(array('MDG_Corporate_Campaigns_20261005','registry'))){return false;}
        $engine='MDG_Corporate_Campaigns_20261005';
        $r=$engine::registry()[$engine::normalize($m['code'])]??array();
        return ($r['brand']??'')==='milano' && empty($r['schema']);
    }
    public static function authorize_item($data,$pid,$vid,$qty,$eid) {
        $engine='MDG_Corporate_Campaigns_20261005';
        $m=$data['mdg_campaign_manifest']??null;
        if(!self::is_manifest($m) || !$engine::signed($data)){return false;}
        $q=$engine::manifest_quote($m);
        if(isset($q['error']) || (int)$q['event']->id!==(int)$eid){return false;}
        $p=$engine::plan($q)[$data['mdg_campaign_role']??'']??null;
        return $p && $p['qty']>0 && (float)$qty===(float)$p['qty']
            && (int)$p['type']['parent']===(int)$pid && (int)$p['type']['variation']===(int)$vid
            && (int)($data['mdg_event_id']??0)===(int)$eid
            && (int)($data['mdg_session_id']??0)===(int)$m['session']
            && (int)($data['mdg_ticket_type_id']??0)===(int)$p['type']['type_id']
            && (int)($data['mdg_capacity_units']??0)===1;
    }
    public static function validation($passed,$pid,$qty,$vid=0,$vars=array(),$data=array()) {
        if(!$passed){return false;}
        if(self::authorize_item($data,$pid,$vid,$qty,(int)($data['mdg_event_id']??0))){return true;}
        return is_callable(self::$previous_validation)
            ? call_user_func(self::$previous_validation,$passed,$pid,$qty,$vid,$vars,$data) : false;
    }
    public static function mark($data,$pid,$vid,$qty) {
        if(self::authorize_item($data,$pid,$vid,$qty,(int)($data['mdg_event_id']??0))){$data['milano_handoff']=1;}
        return $data;
    }
    public static function boot() {
        // Wrap only the known installed guard; every other selection follows its original callback.
        $guard=array('Milano_Sagliksen','handoff_validation');
        if(has_filter('woocommerce_add_to_cart_validation',$guard)!==90){$guard=array('Milano_Shared_Checkout_Handoff','validate_cart_entry');}
        if(has_filter('woocommerce_add_to_cart_validation',$guard)!==90){return;}
        self::$previous_validation=$guard;
        remove_filter('woocommerce_add_to_cart_validation',$guard,90);
        add_filter('woocommerce_add_to_cart_validation',array(__CLASS__,'validation'),90,6);
        add_filter('woocommerce_add_cart_item_data',array(__CLASS__,'mark'),62,4);
    }
    public static function portal_page() {
        // Front-site pages retain their own theme. Only the central Milano portal uses this shell.
        if(strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))!=='madagaskarsirki.com'){return false;}
        $p=get_post();
        return is_singular('page') && $p && has_shortcode($p->post_content,self::SHORTCODE) && !post_password_required($p);
    }
    public static function document_title($title) {
        return self::portal_page()?'Kurumsal Kampanyalar | Milano Sirki':$title;
    }
    public static function template() {
        if(!self::portal_page()){return;}
        if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}
        nocache_headers();
        ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?>
        <style>html,body{margin:0;background:#fffdf8;color:#29221c;font-family:system-ui,-apple-system,sans-serif}body{line-height:1.55}a{color:#8e291e}.milano-corporate-header{padding:20px max(20px,calc((100% - 900px)/2));border-bottom:1px solid #e3d2b9;display:flex;gap:24px;justify-content:space-between;align-items:center}.milano-corporate-header strong{font-size:22px}.milano-corporate-main{padding:12px 20px 32px}.milano-corporate-footer{max-width:900px;padding:20px;margin:auto;border-top:1px solid #e3d2b9;font-size:14px}.mc-milano .mc-intro{border-top:4px solid #a93428}.mc-milano .mc-poster{max-width:390px}.mc-milano .mc-result{background:#fff;padding:20px;border-radius:8px}.mc-milano button{min-height:48px}.mc-milano .mc-error{padding:12px;background:#fff0eb;border-radius:8px}@media(max-width:540px){.milano-corporate-header{align-items:flex-start;flex-direction:column;gap:6px}.mc-milano button{width:100%}.mc-milano .mc-grid{grid-template-columns:minmax(0,1fr)}}</style></head><body>
        <header class="milano-corporate-header"><a href="https://milanosirki.com/"><strong>Milano Sirki</strong></a><span>Kurumsal Kampanyalar</span></header>
        <main class="milano-corporate-main"><?php echo self::render(); ?></main>
        <footer class="milano-corporate-footer">Ödemeler Dünya Organizasyon’un ortak altyapısında tamamlanır. Biletiniz Milano Sirki içindir.</footer>
        <?php wp_footer(); ?></body></html><?php exit;
    }
    public static function render() {
        if(is_callable(array('MDG_Corporate_Campaigns_20261005','render'))){
            return MDG_Corporate_Campaigns_20261005::render(array('brand'=>'milano'));
        }
        // The front site never copies campaign prices, keys or venue/session data.
        ob_start(); ?>
        <section class="milano-corporate-entry" aria-label="Kurumsal kampanyalar">
        <style>.milano-corporate-entry{max-width:900px;margin:24px auto;padding:clamp(20px,4vw,36px);border:1px solid #e3d2b9;border-radius:16px;background:#fff8ed;color:#29221c}.milano-corporate-entry h2{font-size:clamp(26px,5vw,42px);line-height:1.15}.milano-corporate-entry label{display:block;font-weight:600}.milano-corporate-entry input{box-sizing:border-box;display:block;width:100%;min-height:48px;padding:12px;margin:8px 0 14px;border:1px solid #9a8870;border-radius:6px;font:inherit}.milano-corporate-entry button{padding:14px 22px;border:0;border-radius:6px;background:#a93428;color:white;font:inherit;font-weight:700}.milano-corporate-entry :focus-visible{outline:3px solid #987023;outline-offset:3px}</style>
        <p><strong>MİLANO SİRKİ · KURUMSAL KAMPANYALAR</strong></p>
        <h2>Kampanya kodunuzu girin</h2>
        <p>Size iletilen kodla ilinizdeki gösterileri ve seansları görüntüleyin. Kodlarda büyük veya küçük harf kullanabilirsiniz.</p>
        <form method="get" action="https://madagaskarsirki.com/kurumsal-kampanyalar-milano/">
        <label>Kampanya kodu<input name="kod" required maxlength="120" autocomplete="off" placeholder="Size iletilen kampanya kodu"></label>
        <button type="submit">Gösterileri görüntüle</button></form>
        <p>Biletler başarılı ödeme sonrasında oluşturulur. Ödeme ortak Madagaskar Sirki altyapısında tamamlanır; biletiniz Milano Sirki içindir.</p>
        </section>
        <?php return ob_get_clean();
    }
}
add_shortcode(Milano_Corporate_Campaigns::SHORTCODE,array('Milano_Corporate_Campaigns','render'));

add_action('plugins_loaded',array('Milano_Corporate_Campaigns','boot'),50);

add_action('template_redirect',array('Milano_Corporate_Campaigns','template'),2);
add_filter('pre_get_document_title',array('Milano_Corporate_Campaigns','document_title'));

// Support native Code Snippets deployments that load after plugins_loaded.
if(function_exists('did_action') && did_action('plugins_loaded')){Milano_Corporate_Campaigns::boot();}
