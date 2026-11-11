<?php
/** Execute the actual dry-run builder; these fixtures never write WordPress data. */
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
class WooCommerce {}
function absint($v) { return abs((int)$v); }
function remove_accents($v) { return strtr($v, ['ı'=>'i','İ'=>'I','ş'=>'s','Ş'=>'S','ç'=>'c','Ç'=>'C','ğ'=>'g','Ğ'=>'G','ö'=>'o','Ö'=>'O','ü'=>'u','Ü'=>'U']); }
function sanitize_title($v) { return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', remove_accents($v)), '-')); }
function wc_get_product($id) { return null; }
function post_type_exists($t) { return true; }
function wp_attachment_is_image($id) { return $id > 0; }
function wp_get_attachment_url($id) { return $id ? 'https://example.test/qr.png' : false; }
function get_option($name, $default=[]) { return $GLOBALS['options'][$name] ?? $default; }
function is_multisite() { return false; }
function get_page_by_path($s, $o, $t) { return null; }
function wc_get_product_id_by_sku($s) { return 0; }
function term_exists($name, $taxonomy) { return 0; }
function wp_timezone() { return new DateTimeZone('Europe/Istanbul'); }
function get_post($id) { throw new RuntimeException('unexpected existing object access'); }
class MDG_Sessions {
    public static function by_event($id) {
        return array_map(function($hour, $i) { return (object)['id'=>150+$i,'start_at'=>"2026-10-22 $hour:00",'end_at'=>"2026-10-22 $hour:00",'capacity_total'=>500,'wc_product_id'=>0,'tickera_event_id'=>0]; }, ['15:00','16:30'], [0,1]);
    }
    public static function local_parts($utc) { $d=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(wp_timezone()); return [$d->format('Y-m-d'),$d->format('H:i')]; }
    public static function ticket_types_by_session($id) {
        return [(object)['id'=>$id*2,'code'=>'CHILD','label'=>'Çocuk','price'=>'245.00','capacity_units'=>$GLOBALS['units'],'wc_variation_id'=>0],(object)['id'=>$id*2+1,'code'=>'ADULT','label'=>'Yetişkin','price'=>'490.00','capacity_units'=>1,'wc_variation_id'=>0]];
    }
}
class FixtureDB {
    public $prefix='wp_'; public $last_error=''; public $queries=[];
    public function prepare($sql, ...$args) { foreach($args as $a) { $sql=preg_replace('/%[ds]/', (string)$a, $sql, 1); } return $sql; }
    public function get_var($sql) {
        $this->queries[]=$sql;
        if(strpos($sql,'SHOW TABLES LIKE ')===0) { $name=substr($sql,17); return in_array($name,$GLOBALS['missing_tables'],true)?null:$name; }
        if(strpos($sql,'SELECT name FROM ')===0) { preg_match('/id=(\d+)/',$sql,$m); return $GLOBALS['templates'][(int)$m[1]]->name ?? null; }
        if(strpos($sql,"name LIKE '%Madagaskar%'")!==false) { return 6; }
        throw new RuntimeException('unexpected SQL '.$sql);
    }
    public function get_results($sql) {
        $this->queries[]=$sql;
        if(strpos($sql,'b.is_active=1 LIMIT 2')===false || strpos($sql,'LEFT JOIN wp_mmc_programs')===false || strpos($sql,'b.mdg_event_id=24')===false) { throw new RuntimeException('owner query loses identity/cardinality'); }
        $this->last_error=$GLOBALS['db_error'] ? 'fixture SQL error' : '';
        return $GLOBALS['owners'];
    }
    public function get_row($sql) { $this->queries[]=$sql; preg_match('/id=(\d+)/',$sql,$m); return $GLOBALS['templates'][(int)$m[1]] ?? null; }
    public function __call($name,$args) { throw new RuntimeException('dry-run attempted '.$name); }
}
require __DIR__.'/../../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-new-event-production-plan.php';
function reset_fixture() {
    $GLOBALS['wpdb']=new FixtureDB(); $GLOBALS['db_error']=false; $GLOBALS['missing_tables']=[]; $GLOBALS['units']=1;
    $GLOBALS['owners']=[(object)['program_id'=>14,'owner_id'=>14,'brand'=>'Milano Sirki']];
    $json=json_encode(['width'=>595,'height'=>420,'elements'=>[['id'=>'venue-qr','type'=>'image','baseType'=>'image','x'=>450,'y'=>290,'width'=>80,'height'=>80,'src'=>'https://example.test/venue-qr.png'],['id'=>'admission','type'=>'qr_code','baseType'=>'qrcode','x'=>450,'y'=>290,'width'=>80,'height'=>80]]]);
    $GLOBALS['templates']=[6=>(object)['id'=>6,'name'=>'Madagaskar Sirki A5 yatay','template_data'=>$json,'status'=>'active'],9=>(object)['id'=>9,'name'=>'Milano Sirki A5 yatay','template_data'=>$json,'status'=>'active']];
    $GLOBALS['options']=['active_plugins'=>['bridge-for-woocommerce/bridge-for-woocommerce.php'],'mdg_ticket_template_qr_bindings_v1'=>[19=>['base_template_id'=>6]],'mdg_ticket_designer_brand_bases_v1'=>['milano'=>['approved'=>true,'template_id'=>9,'template_sha256'=>hash('sha256',$json),'candidate_id'=>'venue-qr']]];
    return (object)['id'=>24,'province_name'=>'Yalova','district'=>'Merkez','public_uuid'=>'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','status'=>'draft','title'=>'Milano Sirki — Yalova','hero_attachment_id'=>7,'venue_address'=>'Bahçelievler Mah. Turhan Koçal Cad. No:13','venue_maps_url'=>'https://example.test/maps','venue_qr_attachment_id'=>8,'venue_name'=>'90. Yıl Spor Salonu','seating_type'=>'free','rules'=>''];
}
function check($ok,$message) { if(!$ok) { throw new RuntimeException($message); } }
$cases=[
    'milano'=>function(&$e) {},
    'no_binding'=>function(&$e) { unset($GLOBALS['options']['mdg_ticket_designer_brand_bases_v1']); },
    'approval_string'=>function(&$e) { $GLOBALS['options']['mdg_ticket_designer_brand_bases_v1']['milano']['approved']='true'; },
    'wrong_template'=>function(&$e) { $GLOBALS['options']['mdg_ticket_designer_brand_bases_v1']['milano']['template_id']=6; },
    'changed_content'=>function(&$e) { $GLOBALS['templates'][9]->template_data.=' '; },
    'inactive_template'=>function(&$e) { $GLOBALS['templates'][9]->status='inactive'; },
    'missing_template'=>function(&$e) { unset($GLOBALS['templates'][9]); },
    'wrong_candidate'=>function(&$e) { $GLOBALS['options']['mdg_ticket_designer_brand_bases_v1']['milano']['candidate_id']='admission'; },
    'malformed_hash'=>function(&$e) { $GLOBALS['options']['mdg_ticket_designer_brand_bases_v1']['milano']['template_sha256']='bad'; },
    'missing_owner'=>function(&$e) { $GLOBALS['owners']=[]; },
    'duplicate_owner'=>function(&$e) { $GLOBALS['owners'][]=$GLOBALS['owners'][0]; },
    'dangling_owner'=>function(&$e) { $GLOBALS['owners'][0]->owner_id=null; },
    'unknown_brand'=>function(&$e) { $GLOBALS['owners'][0]->brand='unknown'; },
    'missing_brand'=>function(&$e) { $GLOBALS['owners'][0]->brand=''; },
    'db_error'=>function(&$e) { $GLOBALS['db_error']=true; },
    'missing_table'=>function(&$e) { $GLOBALS['missing_tables']=['wp_mmc_mdg_event_bridge']; },
    'missing_hero'=>function(&$e) { $e->hero_attachment_id=0; },
    'wrong_event_title'=>function(&$e) { $e->title='Madagaskar Sirki — Yalova'; },
    'multiunit'=>function(&$e) { $GLOBALS['units']=4; },
    'madagaskar'=>function(&$e) { $GLOBALS['owners'][0]->brand='Madagaskar Sirki';$e->title='Madagaskar Sirki — Yalova'; },
    'madagaskar_skip_milano_base'=>function(&$e) { $GLOBALS['owners'][0]->brand='Madagaskar Sirki';$e->title='Madagaskar Sirki — Yalova';$GLOBALS['options']['mdg_ticket_template_qr_bindings_v1'][24]=['base_template_id'=>9]; },
];
foreach($cases as $name=>$change) {
    $event=reset_fixture(); $change($event);$before=serialize([$GLOBALS['options'],$GLOBALS['templates'],$GLOBALS['owners']]);
    $plan=MDG_New_Event_Production_Plan::build($event);
    check($before===serialize([$GLOBALS['options'],$GLOBALS['templates'],$GLOBALS['owners']]),$name.' mutated source');
    $success=in_array($name,['milano','madagaskar','madagaskar_skip_milano_base'],true);
    check($plan['ready']===$success,$name.' unexpected ready');
    if($success) {
        $milano=$name==='milano'; $brand=$milano?'Milano Sirki':'Madagaskar Sirki'; $slug=$milano?'milano-sirki-':'madagaskar-sirki-';
        check(count($plan['products'])===2,$name.' session count');
        check($plan['products'][0]['time']==='18:00' && $plan['products'][1]['time']==='19:30',$name.' local times');
        check($plan['products'][0]['capacity_total']===500,$name.' capacity');
        foreach($plan['products'] as $product) { check(strpos($product['product_name'],$brand.' – ')===0,$name.' product brand');check(strpos($product['product_slug'],$slug)===0,$name.' product slug');check(array_column($product['variations'],'price')===['245.00','490.00'],$name.' prices'); }
        check(strpos($plan['tickera']['slug'],$slug)===0,$name.' event slug');
        check($plan['template']['base_template_id']===($milano?9:6),$name.' template selection');
        check(strpos($plan['tickera']['event_terms'],$milano?'536 358 33 81':'312 911 37 10')!==false,$name.' support phone');
    }
    if($name==='no_binding') { check($plan['template']['base_template_id']===0,'unapproved Milano falls back'); }
    echo "PASS $name\n";
}
echo count($cases)." actual-builder cases passed (fixture only, no payment/QR E2E).\n";
