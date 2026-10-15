<?php
// Run the real adopter against the three Kocaeli groups, in descending product order.
define('ABSPATH', __DIR__);
class WooCommerce {}
function absint($x) { return abs((int)$x); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function remove_accents($s) { return strtr($s, ['Ç'=>'C','ç'=>'c','ı'=>'i','İ'=>'I','ş'=>'s','Ş'=>'S','ü'=>'u','Ü'=>'U','ö'=>'o','Ö'=>'O','ğ'=>'g','Ğ'=>'G']); }
function post_type_exists($x) { return true; }
function get_post_type($x) { return isset($GLOBALS['events'][$x]) ? 'tc_events' : 'product'; }
function get_the_title($x) { return $GLOBALS['events'][$x]['title'] ?? ''; }
function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function wc_get_product($id) { return $GLOBALS['products'][$id] ?? null; }
function wc_price($x) { return (string)$x; }
class WP_Query {
    public $posts;
    function __construct($args) {
        $this->posts = array_keys(array_filter($GLOBALS['products'], function($p) {return $p->kind === 'variable';}));
        rsort($this->posts);
    }
}
class Product {
    public $id, $name, $kind, $children, $price, $attrs;
    function __construct($id,$name,$kind,$children=[],$price=0,$attrs=[]) {
        $this->id=$id; $this->name=$name; $this->kind=$kind; $this->children=$children; $this->price=$price; $this->attrs=$attrs;
    }
    function get_id(){return $this->id;}
    function is_type($k){return $this->kind===$k;}
    function get_name(){return $this->name;}
    function get_children(){return $this->children;}
    function get_status(){return 'draft';}
    function get_sku(){return '';}
    function get_price(){return $this->price;}
    function get_attributes(){return $this->attrs;}
}
class MDG_Sessions {
    static function by_event($id) { return $GLOBALS['sessions'][$id]; }
    static function local_parts($utc) {
        $d=(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Istanbul'));
        return [$d->format('Y-m-d'),$d->format('H:i')];
    }
    static function ticket_types_by_session($id) { return $GLOBALS['types'][$id]; }
}
require $argv[1] ?? __DIR__.'/../../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-sales-adopter.php';
$method=new ReflectionMethod('MDG_Sales_Adopter','build_plan');
$method->setAccessible(true);
$events=[];$meta=[];$products=[];$sessions=[];$types=[];
$rows=[
    [25,6102,'Karamürsel','2026-10-21',[[152,6081,'17:30'],[153,6084,'19:30']]],
    [26,6104,'Darıca','2026-10-23',[[154,6087,'17:30'],[155,6090,'19:30']]],
    [27,6106,'Derince','2026-10-24',[[156,6093,'12:00'],[157,6096,'14:00'],[158,6099,'16:00']]],
];
$mdg=[];
foreach($rows as [$event,$tc,$city,$day,$slots]) {
    $mdg[$event]=(object)['id'=>$event,'title'=>'Milano Sirki — Kocaeli / '.$city,'province_name'=>'Kocaeli','district'=>$city];
    $events[$tc]=['title'=>'Milano Sirki — '.$city];
    $meta[$tc]=['event_date_time'=>$day.' '.$slots[0][2]];
    foreach($slots as [$sid,$pid,$time]) {
        $utc=(new DateTimeImmutable($day.' '.$time,new DateTimeZone('Europe/Istanbul')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $sessions[$event][]=(object)['id'=>$sid,'start_at'=>$utc,'capacity_total'=>500];
        $products[$pid]=new Product($pid,'Milano Sirki – '.$city.' – '.$day.' – '.$time.' Bileti','variable',[$pid+1,$pid+2]);
        $meta[$pid]=['_tc_is_ticket'=>'yes','_event_name'=>$tc];
        $types[$sid]=[];
        foreach([['Çocuk',245],['Yetişkin',490]] as $i=>[$label,$price]) {
            $vid=$pid+$i+1;
            $products[$vid]=new Product($vid,'','variation',[],$price,['bilet-tipi'=>$label]);
            $types[$sid][]=(object)['id'=>346+2*($sid-152)+$i,'code'=>$label,'label'=>$label,'price'=>$price,'is_active'=>1];
        }
    }
}
$fixture = serialize([$events,$meta,$products,$sessions,$types,$mdg]);
$count=0;
function check($ok,$name) {
    global $count;
    if(!$ok){fwrite(STDERR,"FAIL $name\n");exit(1);}
    $count++;
}
foreach($rows as [$id,$tc,$city,$day,$slots]) {
    $plan=$method->invoke(null,$mdg[$id]);
    check(empty($plan['errors']) && $plan['tickera_event_id']===$tc, "$city chooses its own group");
    check(array_column($plan['sessions'],'product_id')===array_column($slots,1), "$city exact parent IDs");
    foreach($plan['sessions'] as $r) {
        check($r['date']===$day && count($r['variations'])===2, "$city local date and child/adult map");
        check(array_column($r['variations'],'variation_id')===[$r['product_id']+1,$r['product_id']+2], "$city exact variation IDs");
    }
}
// Missing correct group must never fall back to Darıca with identical times/prices.
unset($products[6081],$products[6084]);
$plan=$method->invoke(null,$mdg[25]);
check(!empty($plan['errors']) && !$plan['tickera_event_id'], 'no wrong district/date fallback');
// Wrong vendor date in an otherwise matching group.
$meta[6104]['event_date_time']='2026-10-22 17:30';
check(!empty($method->invoke(null,$mdg[26])['errors']), 'wrong local date rejected');
$meta[6104]['event_date_time']='2026-10-23 17:30';
$events[6104]['title']='Madagaskar Sirki — Darıca';
check(!empty($method->invoke(null,$mdg[26])['errors']), 'wrong brand rejected');
$events[6104]['title']='Milano Sirki — Darıca';
$meta[6087]['_mdg_event_id']=25;
check(!empty($method->invoke(null,$mdg[26])['errors']), 'conflicting MDG owner rejected');
unset($meta[6087]['_mdg_event_id']);
$meta[6087]['_mdg_session_id']=155;
check(!empty($method->invoke(null,$mdg[26])['errors']), 'conflicting session rejected');
unset($meta[6087]['_mdg_session_id']);
$products[6088]->price=250;
check(!empty($method->invoke(null,$mdg[26])['errors']), 'price mismatch rejected');
$products[6088]->price=245;
// Duplicate fully matching group: no score or ID-order tiebreak.
$events[7000]=['title'=>'Milano Sirki — Darıca'];
$meta[7000]=['event_date_time'=>'2026-10-23 17:30'];
foreach([6087,6090] as $old) {
    $new=$old+10000;
    $products[$new]=clone $products[$old];
    $products[$new]->id=$new;
    $meta[$new]=['_tc_is_ticket'=>'yes','_event_name'=>7000];
}
check(!empty($method->invoke(null,$mdg[26])['errors']), 'ambiguous groups rejected');
echo "PASS $count sales adoption identity checks\n";
