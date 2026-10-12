<?php
/**
 * Canonical central MDG/MMC/Woo read-only adapter fixtures.
 * Run: php integrations/milano/tests/canonical-mdg-snapshot-contract.php SCENARIO
 * Zero WordPress, PayTR, real SQL, order, hold or Tickera side effects.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = $argv[1] ?? 'valid';
function demand_snapshot($ok,$why){if(!$ok){fwrite(STDERR,"FAIL {$why}\n");exit(1);}}
function absint($v){return abs((int)$v);}
$now = 1781000000;
$programBrand = 'Milano Sirki';
$bridge = array(20);
$event = (object)array(
    'id'=>72,'status'=>'onsale','sale_start'=>'2026-01-01 00:00:00',
    'sale_end'=>'2027-01-01 00:00:00'
);
$session = (object)array(
    'id'=>93,'event_id'=>72,'status'=>'onsale',
    'start_at'=>'2026-10-15 14:30:00',
    'sale_start'=>'2026-01-01 00:00:00',
    'sale_end'=>null,
    'wc_product_id'=>1234,'tickera_event_id'=>999,'capacity_total'=>100
);
$catalogue=array(
    (object)array('code'=>'ADULT','is_active'=>1,'wc_variation_id'=>101,'capacity_units'=>1,'price'=>'490.00'),
    (object)array('code'=>'CHILD','is_active'=>1,'wc_variation_id'=>102,'capacity_units'=>1,'price'=>'245.00')
);
$available=25;$siteCurrency='TRY';$productStatus='publish';$closedByTime=false;
class SnapshotDB {
    public $getRowCalls=0,$getColCalls=0,$requests=array();
    public function prepare($sql,...$args) {
        demand_snapshot(strpos($sql,'UPDATE ')===false&&strpos($sql,'INSERT ')===false&&strpos($sql,'DELETE ')===false,'mutating SQL attempted');
        $this->requests[]=$sql;
        if(strpos($sql,'SELECT program_id')!==false) {
            demand_snapshot(strpos($sql,'LIMIT 2')!==false,'ambiguous bridge query LIMIT 1');
            demand_snapshot($args===array(72),'bridge must query signed event');
        }
        if(strpos($sql,'SELECT * FROM wp_mdg_sessions')!==false) {
            demand_snapshot($args===array(93,72),'session must join signed event');
        }
        return $sql;
    }
    public function get_col($sql){global $bridge;$this->getColCalls++;return $bridge;}
    public function get_row($sql){global $session;$this->getRowCalls++;return $session;}
}
$wpdb=new SnapshotDB();
class MDG_DB {public static function table($v){return 'wp_mdg_'.$v;}}
class MDG_Status {const ONSALE='onsale';}
class MDG_Events {
    public static function get($id){global $event;return $event;}
}
class MDG_Sessions {
    public static function ticket_types_by_session($id){
        global $catalogue;
        demand_snapshot($id===93,'wrong ticket session');
        return $catalogue;
    }
    public static function sales_closed_by_time($s,$nowTs){
        global $closedByTime;return $closedByTime;
    }
}
class MDG_Capacity {
    public static function available($sid){global $available;return $available;}
}
class MMC_MDG_Bridge_Service {
    public static function table(){return 'wp_mmc_mdg_event_bridge';}
}
class MMC_Program_Service {
    public static function get_program($pid){global $programBrand;
        return $pid===20?(object)array('brand'=>$programBrand):null;
    }
    public static function normalized_program_brand($b){
        return in_array($b,array('Milano Sirki','Madagaskar Sirki'),true)?$b:'';
    }
}
class SnapshotProduct {
    public $id,$kind,$parent,$price,$buy=true,$stock=true;
    public function __construct($id,$kind,$parent,$price){$this->id=$id;$this->kind=$kind;$this->parent=$parent;$this->price=$price;}
    public function is_type($kind){return $this->kind===$kind;}
    public function get_parent_id(){return $this->parent;}
    public function get_price(){return $this->price;}
    public function is_purchasable(){return $this->buy;}
    public function is_in_stock(){return $this->stock;}
}
$products=array(
    1234=>new SnapshotProduct(1234,'variable',0,'0'),
    101=>new SnapshotProduct(101,'variation',1234,'490'),
    102=>new SnapshotProduct(102,'variation',1234,'245.00')
);
function wc_get_product($id){global $products;return $products[$id]??false;}
function get_post_status($id){global $productStatus;return $productStatus;}
function get_woocommerce_currency(){global $siteCurrency;return $siteCurrency;}
require dirname(__DIR__).'/checkout-intent-protocol/milano-checkout-intent.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-central-eligibility.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-canonical-mdg-snapshot.php';

$expected='eligible';
switch($scenario){
case 'valid':break;
case 'family4':
    $catalogue[]= (object)array('code'=>'FAMILY4','is_active'=>1,'wc_variation_id'=>103,'capacity_units'=>4,'price'=>'1100.00');
    $products[103]=new SnapshotProduct(103,'variation',1234,'1100');
    break;
case 'no_brand':$programBrand='';$expected='null';break;
case 'wrong_brand':$programBrand='Madagaskar Sirki';$expected='null';break;
case 'no_bridge':$bridge=array();$expected='null';break;
case 'two_bridges':$bridge=array(20,30);$expected='null';break;
case 'bridge_error':$bridge=false;$expected='null';break;
case 'orphan_bridge':$bridge=array(42);$expected='null';break;
case 'malformed_brand':$programBrand='both';$expected='null';break;
case 'missing_event':$event=null;$expected='null';break;
case 'wrong_event_id':$event->id=71;$expected='null';break;
case 'event_draft':$event->status='draft';$expected='event_not_owned_or_open';break;
case 'session_missing':$session=null;$expected='null';break;
case 'wrong_session_id':$session->id=94;$expected='null';break;
case 'wrong_session_event':$session->event_id=71;$expected='null';break;
case 'closed_session':$session->status='cancelled';$expected='session_not_available';break;
case 'time_closed':$closedByTime=true;$expected='session_not_available';break;
case 'past_session':$session->start_at='2025-10-15 14:30:00';$expected='session_not_available';break;
case 'invalid_start':$session->start_at='not a date';$expected='null';break;
case 'impossible_date':$session->start_at='2026-02-31 14:30:00';$expected='null';break;
case 'invalid_window':$session->sale_end='2027-55-55 12:00:00';$expected='null';break;
case 'sale_future':$event->sale_start='2027-01-01 00:00:00';$expected='sale_window_closed';break;
case 'sale_ended':$event->sale_end='2026-01-01 00:00:00';$expected='sale_window_closed';break;
case 'no_product':unset($products[1234]);$expected='null';break;
case 'unpublished':$productStatus='private';$expected='null';break;
case 'simple_product':$products[1234]->kind='simple';$expected='null';break;
case 'wrong_currency':$siteCurrency='EUR';$expected='null';break;
case 'no_tickera':$session->tickera_event_id=0;$expected='null';break;
case 'bad_parent_id':$products[101]->parent=987;$expected='ticket_product_mismatch';break;
case 'out_of_stock':$products[101]->stock=false;$expected='ticket_product_mismatch';break;
case 'unavailable_variation':unset($products[101]);$expected='ticket_product_mismatch';break;
case 'inactive_type':$catalogue[0]->is_active=0;$expected='ticket_product_mismatch';break;
case 'duplicated_code':$catalogue[]=(object)array('code'=>'ADULT','is_active'=>1,'wc_variation_id'=>102,'capacity_units'=>1,'price'=>'245.00');$expected='null';break;
case 'invalid_code':$catalogue[0]->code='X<script>';$expected='null';break;
case 'no_tickets':$catalogue=array();$expected='null';break;
case 'wrong_woo_price':$products[101]->price='499';$expected='ticket_product_mismatch';break;
case 'fractional_subcents':$catalogue[0]->price='490.001';$expected='null';break;
case 'trailing_zero_price':$products[101]->price='490.0000';break;
case 'zero_capacity':$available=0;$expected='insufficient_capacity';break;
case 'low_capacity':$available=3;$expected='insufficient_capacity';break;
case 'invalid_available':$available=101;$expected='null';break;
case 'no_capacity_total':$session->capacity_total=0;$expected='null';break;
case 'invalid_request_id':
    $ret=MCPI_Canonical_MDG_Snapshot::lookup(-1,93,$now);
    demand_snapshot(null===$ret&&$wpdb->getColCalls===0,'invalid ID queried central DB');
    echo "PASS {$scenario}\n";exit(0);
default:demand_snapshot(false,'unknown fixture');
}
$snap=MCPI_Canonical_MDG_Snapshot::lookup(72,93,$now);
if($expected==='null'){
    demand_snapshot(null===$snap,'malformed canonical inputs must fail closed');
}else{
    demand_snapshot(is_array($snap),'valid central snapshot missing');
    demand_snapshot($snap['event']['owner_brand']==='milano','snapshot owner mismatch');
    demand_snapshot($snap['product']['currency']==='TRY','wrong currency');
    demand_snapshot($snap['session']['start_utc']>$now,'session UTC conversion failed');
    demand_snapshot($snap['tickets'][0]['price_cents']===49000,'price parser mismatch');
    $items=$scenario==='family4'
        ?array(array('code'=>'FAMILY4','quantity'=>1))
        :array(array('code'=>'ADULT','quantity'=>2),array('code'=>'CHILD','quantity'=>2));
    $intent=mcpi_make_intent(72,93,$items,$now);
    $result=MCPI_Central_Eligibility::validate($intent,$snap,$now);
    $actual=$result['status']==='eligible'?'eligible':($result['reason']??$result['status']);
    demand_snapshot($actual===$expected,"eligibility expected {$expected}, received {$actual}");
    if($expected==='eligible'){
        demand_snapshot($result['admission_units']===4,'one family must consume four units');
        demand_snapshot($result['total_cents']===($scenario==='family4'?110000:147000),'canonical total wrong');
        demand_snapshot($result['quote_only']===true,'side-effect-free flag missing');
    }
}
echo "PASS {$scenario}\n";
