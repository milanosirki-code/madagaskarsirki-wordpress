<?php
define('ABSPATH', __DIR__);
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function wp_date($f, $t = null) { return date($f, $t ?? time()); }
function mysql2date($f, $v) { return date($f, strtotime($v)); }
function date_i18n($f, $t) { return date($f, $t); }
function number_format_i18n($v, $d = 0) { return number_format($v, $d, '.', ','); }
function remove_accents($v) { return strtr($v, array('ı'=>'i','İ'=>'I','ş'=>'s','Ş'=>'S')); }
function absint($v) { return abs((int)$v); }
function wp_json_encode($v) { return json_encode($v); }
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($v, $enc = null) { return strtoupper(strtr($v, array('i'=>'İ','ı'=>'I','ş'=>'Ş','ç'=>'Ç','ö'=>'Ö','ü'=>'Ü','ğ'=>'Ğ'))); }
    function mb_strtolower($v, $enc = null) { return strtolower(strtr($v, array('İ'=>'i','I'=>'ı','Ş'=>'ş','Ç'=>'ç','Ö'=>'ö','Ü'=>'ü','Ğ'=>'ğ'))); }
    function mb_substr($v, $start, $length = null, $enc = null) { return implode('',array_slice(preg_split('//u',$v,-1,PREG_SPLIT_NO_EMPTY),$start,$length)); }
}
$root = dirname(__DIR__, 3) . '/wp-content/plugins/madagaskar-management-center/includes/';
foreach(array('program-service','mdg-draft-sync-service','marketing-service','publish-content-excel') as $file) require_once $root . 'class-mmc-' . $file . '.php';
function check($ok, $label) { if (!$ok) throw new RuntimeException($label); }
function call_private($class, $name, $args, $instance = null) { return (new ReflectionMethod($class,$name))->invokeArgs($instance,$args); }
$excel=(new ReflectionClass('MMC_Publish_Content_Excel'))->newInstanceWithoutConstructor();
$hashes=array();
foreach(array('Milano Sirki','Madagaskar Sirki') as $brand) {
    $p=(object)array('brand'=>$brand,'province_name'=>'Yalova','district_name'=>'Merkez','program_code'=>'PRG-2026-YAL-MERKEZ-001','status'=>'preparation');
    $s=array('program'=>$p,
        'event'=>(object)array('event_date'=>'2026-10-22','status'=>'configured','seating_mode'=>'free','door_open_minutes'=>30),
        'venue'=>(object)array('venue_name'=>'90. Yıl Spor Salonu','address'=>'Yalova'),
        'sessions'=>array((object)array('session_time'=>'2026-10-22 18:00:00','capacity'=>500),(object)array('session_time'=>'2026-10-22 19:30:00','capacity'=>500)),
        'tickets'=>array((object)array('ticket_code'=>'adult','ticket_name'=>'Yetişkin','price'=>490,'is_active'=>1),(object)array('ticket_code'=>'child','ticket_name'=>'Çocuk','price'=>245,'is_active'=>1)));
    $profile=MMC_Program_Service::public_brand_profile($p);
    $wrong='Milano Sirki'===$brand?'madagaskar':'milano';
    $title=call_private('MMC_MDG_Draft_Sync_Service','public_event_title',array($p));
    check($title === $brand . ' — Yalova','MDG title follows program');
    $vars=call_private('MMC_Publish_Content_Excel','variables',array($s),$excel);
    check($vars['{{MARKA}}']===$brand && $vars['{{ETKINLIK_ADI}}']===$title,'Excel brand and event title');
    check(!str_contains(strtolower($vars['{{BILET_LINKI}}']),$wrong),'Excel exclusive official site');
    check($vars['{{BILGI_HATTI}}']===$profile['phone'],'Excel phone');
    foreach(array('poster','instagram_story','reel','countdown','giveaway','meta_ad','instagram_post') as $type) {
        $copy=implode(' ',call_private('MMC_Marketing_Service','generate_item',array($type,$s)));
        check(!str_contains(strtolower($copy),$wrong),'No other brand in ' . $type);
        if('giveaway'===$type) check(str_contains($copy,$profile['instagram']),'Instagram handle');
        if('instagram_post'===$type) check(str_contains($copy,'18:00 • 19:30') && str_contains($copy,'490 TL') && str_contains($copy,'245 TL'),'Session times and prices preserved');
    }
    $hashes[]=call_private('MMC_Marketing_Service','source_hash',array($s));
}
check($hashes[0]!==$hashes[1],'Brand change invalidates marketing source hash');
check(MMC_Program_Service::public_brand_profile((object)array())['name']==='Madagaskar Sirki','Legacy compatibility');
check(MMC_Program_Service::public_brand_profile((object)array('brand'=>'both'))['ticket_url']==='','Unknown brand has no sales link');
echo "PASS: generated titles, Excel fields, seven marketing formats, hash invalidation and legacy compatibility\n";
