<?php
/** Isolated staging presentation; no registry writes and no payment/order emulation. */
if(!defined('ABSPATH')){exit;}
if(strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))!=='staging-f321-milanosirki-lgajf.wpcomstaging.com'){return;}
final class Milano_Corporate_Preview {
    public static function page() {
        $page=get_post();
        return is_singular('page') && $page && has_shortcode($page->post_content,'milano_corporate_campaigns');
    }
    public static function registry($rows) {
        if(!self::page()){return $rows;}
        $verified=MDG_Corporate_Campaigns_20261005::milano_sessions();$cities=array();
        foreach(MDG_Corporate_Campaigns_20261005::source_events('milano') as $event){
            if(!isset($verified[(int)$event->id]))continue;
            $province=(string)$event->province_name;
            $cities[MDG_Corporate_Campaigns_20261005::normalize($province)]=array('province'=>$province,'active'=>true,'end_date'=>'','pricing'=>array('adult_campaign'=>475,'child_campaign'=>245));
        }
        $rows['milano-onizleme']=array('name'=>'Milano Sirki Kurumsal Kampanya Önizlemesi','active'=>true,'brand'=>'milano','preview_only'=>true,'cities'=>$cities,'schema_version'=>2);
        return $rows;
    }
    public static function template() {
        if(!self::page()){return;}
        nocache_headers();
        ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Kurumsal Kampanyalar | Milano Sirki</title>
        <style>html,body{margin:0;background:#fffdf8;color:#29221c;font-family:system-ui,-apple-system,sans-serif}body{line-height:1.55}a{color:#8e291e}.milano-preview-header{padding:20px max(20px,calc((100% - 900px)/2));border-bottom:1px solid #e3d2b9;display:flex;gap:24px;justify-content:space-between;align-items:center}.milano-preview-header strong{font-size:22px}.milano-preview-main{padding:12px 20px 32px}.milano-preview-footer{max-width:900px;padding:20px;margin:auto;border-top:1px solid #e3d2b9;font-size:14px}.mc-milano .mc-intro{border-top:4px solid #a93428}.mc-milano .mc-poster{max-width:390px}.mc-milano .mc-result{background:#fff;padding:20px;border-radius:8px}.mc-milano button{min-height:48px}.mc-milano .mc-error{padding:12px;background:#fff0eb;border-radius:8px}@media(max-width:540px){.milano-preview-header{align-items:flex-start;flex-direction:column;gap:6px}.mc-milano button{width:100%}.mc-milano .mc-grid{grid-template-columns:minmax(0,1fr)}}</style></head><body>
        <header class="milano-preview-header"><strong>Milano Sirki</strong><span>Kurumsal Kampanyalar</span></header>
        <main class="milano-preview-main"><?php echo do_shortcode(get_post()->post_content); ?></main>
        <footer class="milano-preview-footer">Test ortamı · Gerçek ödeme yapmayın.</footer>
        <?php MDG_Corporate_Campaigns_20261005::script(); ?></body></html><?php exit;
    }
}
add_filter('mdg_corporate_campaign_registry',array('Milano_Corporate_Preview','registry'));
add_action('template_redirect',array('Milano_Corporate_Preview','template'),2);
