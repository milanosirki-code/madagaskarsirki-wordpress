<?php
define( 'ABSPATH', __DIR__ );
function add_action() {}
function add_filter() {}
function absint($n) { return abs((int)$n); }
function remove_accents($s) { return strtr($s, array('Ç'=>'C','ç'=>'c','ş'=>'s','ı'=>'i','İ'=>'I')); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_unslash($s) { return $s; }
function wp_json_encode($s) { return json_encode($s); }
function wp_generate_uuid4() { return 'test-group'; }
function wp_send_json_error($payload, $status) { throw new Exception($payload['message'], $status); }
function is_admin() { return false; }
function wc_get_order($id) { return $GLOBALS['order']; }
function current_time($type) { return '2026-11-06 00:00:00'; }
class MDG_Events { static function get($id) { return (object)array('status'=>$GLOBALS['event_status'] ?? 'onsale'); } }
class MMC_MDG_Bridge_Service { static function program_for_mdg_event($id) { return $id; } }
class MMC_Program_Service { static function get_program($id) { return (object)array('brand'=>$GLOBALS['brand'] ?? 'Madagaskar Sirki','status'=>$GLOBALS['program_status'] ?? 'sales_open'); } }
class MMC_Event_Service {
    static function event_for_program($id) { return (object)array('id'=>$id); }
    static function ticket_types($id) { return array((object)array('ticket_code'=>'family_2_2','price'=>1100,'is_active'=>0)); }
}
class MDG_DB { static function table($name) { return 'wp_mdg_'.$name; } }
class FakeDB {
    public $prefix = 'wp_'; public $writes = array();
    function prepare($sql, ...$args) { return array($sql,$args); }
    function get_row($query) { return ($query[1][0]===88 && $query[1][1]===10) ? (object)array('id'=>88) : null; }
    function get_col($query) { return array(88); }
    function get_var($query) { return $GLOBALS['multi'] ?? 0; }
    function get_results($query) { return $GLOBALS['types']; }
    function update($table,$data,$where) { $this->writes[$table.':'.$where[array_key_exists('order_item_id',$where)?'order_item_id':'external_order_item_id']] = array($data,$where); return 1; }
}
$GLOBALS['wpdb'] = new FakeDB();
require __DIR__.'/../../wp-content/plugins/madagaskar-aile-paketi-24/madagaskar-aile-paketi-24.php';
$n = 0;
function check($condition,$message) { global $n; ++$n; if(!$condition) throw new Exception('FAIL: '.$message); }
function fails($callable,$label) { try { $callable(); } catch(Throwable $e) { check(true,$label); return; } check(false,$label); }
function selection($c,$q) { return array('code'=>$c,'qty'=>$q); }
function package_set($price24=1250,$price22=1100,$qty24=1,$qty22=0) { return array('family_2_4'=>array('qty'=>$qty24,'price'=>$price24),'family_2_2'=>array('qty'=>$qty22,'price'=>$price22)); }
$p = MDG_Family24_Pricing::expand(array(selection('MDG_FAMILY_2_4',1)), 'CHILD','ADULT');
check($p['lines']===array(selection('CHILD',4),selection('ADULT',2)),'one package produces six actual tickets');
$p = MDG_Family24_Pricing::expand(array(selection('MDG_FAMILY_2_4',1),selection('MDG_FAMILY_2_2',1),selection('CHILD',1)), 'CHILD','ADULT');
check($p['lines']===array(selection('CHILD',7),selection('ADULT',4)),'mixed packages and a single child');
foreach(array(-1,'-1',1.5,'1.5','2x',array(1),21,'999999999999999999999') as $bad) fails(function()use($bad){MDG_Family24_Pricing::expand(array(selection('MDG_FAMILY_2_4',$bad)),'CHILD','ADULT');},'invalid quantity');
fails(function(){MDG_Family24_Pricing::expand(array(selection('MDG_FAMILY_2_4',6)),'CHILD','ADULT');},'six packages exceed native child limit');
fails(function(){MDG_Family24_Pricing::expand(array(selection('MDG_FAMILY_2_4',5),selection('CHILD',1)),'CHILD','ADULT');},'combined child limit');
$p=MDG_Family24_Pricing::expand(array(selection('MDG_FAMILY_2_4',5)),'CHILD','ADULT');
check(array_sum(array_column($p['lines'],'qty'))===30,'five packages consume thirty people');
foreach(array(array(290,585,1500,830),array(245,490,1250,710),array(250,500,1250,750)) as $case) {
    list($c,$a,$price,$off)=$case;
    $r=MDG_Family24_Pricing::discount(package_set($price),4,2,$c,$a);
    check($r['amount']===$off*1.0,'city-specific package discount');
    check(4*$c+2*$a-$r['amount']===$price*1.0,'exact package total');
}
check(MDG_Family24_Pricing::discount(package_set(),3,2,245,490)['amount']===0.0,'removing a child removes complete 2+4 discount');
check(MDG_Family24_Pricing::discount(package_set(),4,1,245,490)['amount']===0.0,'removing an adult removes complete discount');
check(MDG_Family24_Pricing::discount(package_set(1250,1100,2),4,2,245,490)['effective']['family_2_4']===1,'reducing two packages to one');
check(MDG_Family24_Pricing::discount(package_set(1250,1100,1,1),6,4,245,490)['amount']===1080.0,'mixed discount does not stack on same tickets');
check(MDG_Family24_Pricing::discount(package_set(1250,1100,1,1),2,2,245,490)['amount']===370.0,'remaining complete small package');
check(MDG_Family24_Pricing::discount(package_set(3000),4,2,245,490)['amount']===0.0,'invalid price fails closed');
foreach(array(array(980,980),array(1,1,1),array(0,5.51),array(.01,.01,.01,.01)) as $gross) {
    for($d=0;$d<=array_sum($gross)*100;$d+=max(1,(int)(array_sum($gross)*7))) {
        $allocated=MDG_Family24_Pricing::allocate($gross,$d/100);
        check(abs(array_sum($allocated)-(array_sum($gross)-$d/100))<.0001,'penny allocation exact');
        foreach($allocated as $k=>$net) check($net>=0 && $net<=$gross[$k],'allocated line remains within gross');
    }
}
$GLOBALS['options']=array('mdg_family_package_24_v1'=>array('events'=>array(10=>1500)),'mdg_family_package_22_v1'=>array('events'=>array(10=>1),'event_prices'=>array(10=>1300),'price'=>1100));
check(MDG_Family_Package_24::offer(10)['price']===1500.0,'explicit event price');
check(MDG_Family_Package_24::offer(40)===null,'no automatic city/event opt-in');
$GLOBALS['brand']='Milano Sirki'; check(MDG_Family_Package_24::offer(10)===null,'other brand excluded'); unset($GLOBALS['brand']);
$GLOBALS['program_status']='venue_confirmed';check(MDG_Family_Package_24::offer(10)===null,'contradictory sale state excluded');unset($GLOBALS['program_status']);
$GLOBALS['types']=array((object)array('id'=>1,'label'=>'Çocuk','code'=>'CHILD','price'=>290,'capacity_units'=>1,'wc_variation_id'=>101),(object)array('id'=>2,'label'=>'Yetişkin','code'=>'ADULT','price'=>585,'capacity_units'=>1,'wc_variation_id'=>102));
$_POST=array('event_id'=>10,'session_id'=>88,'lines'=>json_encode(array(selection('MDG_FAMILY_2_4',1),selection('MDG_FAMILY_2_2',1))));
MDG_Family_Package_24::preprocess();
check(json_decode($_POST['lines'],true)===array(selection('CHILD',6),selection('ADULT',4)),'actual adapter rewrites both packages');
$native=array('mdg_event_id'=>10,'mdg_session_id'=>88,'mdg_ticket_type_id'=>1);
$marked=MDG_Family_Package_24::mark($native,99,101,6);
check(isset($marked['mdg_family24'])&&!isset($marked['mdg_family22']),'new group isolated from old financial hooks');
check(MDG_Family_Package_24::mark($native,99,999,6)===$native,'wrong variation never marked');
$_POST['lines']=json_encode(array(selection('MDG_FAMILY_2_2',1)));MDG_Family_Package_24::preprocess();
check(MDG_Family_Package_24::mark($native,99,101,2)===$native,'2+2-only request is untouched and context reset');
$_POST['lines']=json_encode(array(selection('MDG_FAMILY_2_4',1)));$_POST['session_id']=999;
fails(function(){MDG_Family_Package_24::preprocess();},'wrong session rejected');
$_POST['session_id']=88;$GLOBALS['multi']=1;
fails(function(){MDG_Family_Package_24::preprocess();},'legacy multi-capacity real ticket rejected');unset($GLOBALS['multi']);
class FakeProduct { private $price; function __construct($p){$this->price=$p;} function get_price(){return $this->price;} }
class FakeCart {
    public $lines; public $fees=array();
    function get_cart(){return $this->lines;}
    function fees_api(){return $this;}
    function add_fee($fee){$this->fees[$fee['id']]=$fee;}
}
MDG_Family_Package_24::preprocess();
$cart=new FakeCart();$cart->lines=array();
foreach(array(array(1,101,4,290),array(2,102,2,585)) as $line){list($id,$var,$qty,$price)=$line;$data=MDG_Family_Package_24::mark(array('mdg_event_id'=>10,'mdg_session_id'=>88,'mdg_ticket_type_id'=>$id),99,$var,$qty);$data['quantity']=$qty;$data['data']=new FakeProduct($price);$cart->lines[]=$data;}
MDG_Family_Package_24::fees($cart);
check($cart->fees['mdg-family24-test-group']['amount']===-830.0,'actual fee hook exact Izmir total');
$cart->lines[0]['quantity']=3;$cart->fees=array();MDG_Family_Package_24::fees($cart);
check(!$cart->fees,'actual fee hook removes incomplete package discount');
class FakeItem {
    public $meta=array();public $amount;
    function __construct($amount){$this->amount=$amount;}
    function add_meta_data($k,$v,$unique){$this->meta[$k]=$v;}
    function get_meta($k,$single){return $this->meta[$k]??'';}
    function get_total(){return $this->amount;}
    function get_total_tax(){return 0;}
}
class FakeOrder {
    public $items;public $fee;public $paid=true;public $refund=0;
    function get_items($type){return $type==='fee'?array($this->fee):$this->items;}
    function get_id(){return 42;}
    function get_date_paid(){return $this->paid;}
    function get_total_refunded_for_item($id){return $id===1?$this->refund:0;}
    function get_tax_refunded_for_item($id,$type){return 0;}
}
$order=new FakeOrder();$order->items=array(1=>new FakeItem(1160),2=>new FakeItem(1170));
foreach($order->items as $id=>$item){MDG_Family_Package_24::order_line($item,'k',$cart->lines[$id-1],$order);}
$order->fee=new FakeItem(-830);MDG_Family_Package_24::order_fee($order->fee,'mdg-family24-test-group',null,$order);
check($order->fee->get_meta('_mdg_family24_group',true)==='test-group','order fee retains exact group');
$GLOBALS['order']=$order;MDG_Family_Package_24::reconcile(42);
$writes=$GLOBALS['wpdb']->writes;
check($writes['wp_mmc_sales_ledger:1'][0]['gross_amount']+$writes['wp_mmc_sales_ledger:2'][0]['gross_amount']===1500.0,'ledger sum matches collected money');
check($writes['wp_mdg_order_map:1'][0]['line_total']+$writes['wp_mdg_order_map:2'][0]['line_total']===1500.0,'V5 sum matches collected money');
MDG_Family_Package_24::reconcile(42);check($GLOBALS['wpdb']->writes===$writes,'replayed hook idempotent');
$order->refund=100;MDG_Family_Package_24::reconcile(42);check($GLOBALS['wpdb']->writes['wp_mmc_sales_ledger:1'][0]['net_amount']===$writes['wp_mmc_sales_ledger:1'][0]['net_amount']-100,'partial refund net');
$order->paid=false;MDG_Family_Package_24::reconcile(42);check($GLOBALS['wpdb']->writes['wp_mmc_sales_ledger:2'][0]['net_amount']===0,'unpaid order has zero collected revenue');
echo "PASS $n assertions\n";
