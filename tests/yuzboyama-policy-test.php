<?php
define('ABSPATH', __DIR__ . '/');
function register_activation_hook(...$args) {}
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function wc_get_product_id_by_sku($sku) { return 999; }
require __DIR__ . '/../wp-content/plugins/mdg-yuzboyama/mdg-yuzboyama.php';
$checks=0;
function check($actual,$expected,$label) { global $checks; $checks++; if($actual!==$expected) throw new Exception($label.': '.var_export($actual,true)); }
check(MDG_YB_Policy::capacity(true,'TRY',3,30000,0,0,false),3,'Three people');
check(MDG_YB_Policy::capacity(false,'TRY',3,30000,0,0,false),0,'Unpaid blocked');
check(MDG_YB_Policy::capacity(true,'EUR',3,30000,0,0,false),0,'Foreign currency blocked');
check(MDG_YB_Policy::capacity(true,'TRY',3,29000,0,0,false),0,'Incorrect total blocked');
check(MDG_YB_Policy::capacity(true,'TRY',3,30000,10000,1,false),2,'One person refunded');
check(MDG_YB_Policy::capacity(true,'TRY',3,30000,30000,3,false),0,'Full refund blocked');
check(MDG_YB_Policy::capacity(true,'TRY',3,30000,10000,0,true),0,'Unallocated refund review');
check(MDG_YB_Policy::can_redeem(3,0,1),true,'First use');
check(MDG_YB_Policy::can_redeem(3,1,2),true,'Remaining two');
check(MDG_YB_Policy::can_redeem(3,3,1),false,'Double use blocked');
check(MDG_YB_Policy::can_redeem(3,2,2),false,'Overuse blocked');
check(MDG_YB_Policy::can_redeem(3,0,0),false,'Zero use blocked');
check(MDG_YB_Policy::can_redeem(3,0,1.5),false,'Fractional use blocked');
class FakeItem { function get_product_id(){return 999;} function get_quantity(){return 3;} }
class FakeOrder {
    public $paid=true,$marker='yes',$confirmation='PAYTRWOO1234',$refund=0,$refunded_qty=0,$gateway='paytr_payment_gateway',$pos='no';
    function get_meta($key){return $key==='_mdg_yb'?$this->marker:($key==='_mdg_yb_pos'?$this->pos:$this->confirmation);}
    function get_items(){return array(123=>new FakeItem());}
    function get_qty_refunded_for_item($id){return $this->refunded_qty;}
    function get_total_refunded(){return $this->refund;}
    function get_total(){return 300;}
    function get_currency(){return 'TRY';}
    function is_paid(){return $this->paid;}
    function get_date_paid(){return true;}
    function get_payment_method(){return $this->gateway;}
}
$order=new FakeOrder();
check(MDG_Yuzboyama::capacity($order),3,'Verified PayTR order');
$order->confirmation='';check(MDG_Yuzboyama::capacity($order),0,'Paid status without provider confirmation');
$order->confirmation='PAYTRWOO1234';$order->paid=false;check(MDG_Yuzboyama::capacity($order),0,'Failed payment');
$order->paid=true;$order->marker='no';check(MDG_Yuzboyama::capacity($order),0,'Unrelated ticket order');
$order->marker='yes';$order->gateway='bacs';check(MDG_Yuzboyama::capacity($order),0,'Other payment method');
$order->gateway='paytr_payment_gateway';$order->refund=100;$order->refunded_qty=1;check(MDG_Yuzboyama::capacity($order),2,'Order partial refund');
$order->refunded_qty=0;check(MDG_Yuzboyama::capacity($order),0,'Order amount-only refund');
check(MDG_Yuzboyama::vat_breakdown(100),array('rate'=>20,'base'=>83.33,'vat'=>16.67,'total'=>100),'One-person inclusive VAT');
check(MDG_Yuzboyama::vat_breakdown(200),array('rate'=>20,'base'=>166.67,'vat'=>33.33,'total'=>200),'Two-person inclusive VAT');
check(MDG_Yuzboyama::vat_breakdown(300),array('rate'=>20,'base'=>250,'vat'=>50,'total'=>300),'Three-person inclusive VAT');
for ($n=1;$n<=50;$n++) {
    $v=MDG_Yuzboyama::vat_breakdown($n*100);
    check((int)round(($v['base']+$v['vat'])*100),$n*10000,'VAT sum for '.$n.' people');
}
$order=new FakeOrder();$order->marker='no';$order->pos='yes';
check(MDG_Yuzboyama::capacity($order),3,'Legacy POS uses canonical entitlements');
$order->confirmation='';check(MDG_Yuzboyama::capacity($order),0,'Unconfirmed POS has no rights');
check(MDG_Yuzboyama::payment_code($order),'','Unconfirmed POS exposes no code');
$order->confirmation='PAYTRWOO1234';$order->gateway='paytr_eft';check(MDG_Yuzboyama::capacity($order),0,'Non-card PayTR blocked');
function wp_date($format){return '2026-10-29';}
function get_option($key,$default=false){return $default;}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
class SnapshotOrder extends FakeOrder {
    public $metadata=array();
    function get_formatted_billing_full_name(){return 'Test Ödeyen';}
    function update_meta_data($key,$value){$this->metadata[$key]=$value;}
}
$order=new SnapshotOrder();MDG_Yuzboyama::order_meta($order);
check($order->metadata['_mdg_yb'],'yes','POS creation sets canonical marker');
check($order->metadata['_mdg_yb_vat_base'],250,'POS snapshot base');
check($order->metadata['_mdg_yb_vat_amount'],50,'POS snapshot VAT');
check($order->metadata['_mdg_yb_event_id'],0,'Sale without program');
check($order->metadata['_mdg_yb_customer_name'],'Test Ödeyen','Customer name snapshot');
check(MDG_Yuzboyama::day_bounds('2026-10-29'),array('2026-10-29 00:00:00','2026-10-30 00:00:00'),'Same-day income boundaries');
check(MDG_Yuzboyama::day_bounds('2026-12-31'),array('2026-12-31 00:00:00','2027-01-01 00:00:00'),'Year rollover');
check(MDG_Yuzboyama::day_bounds('2028-02-29'),array('2028-02-29 00:00:00','2028-03-01 00:00:00'),'Leap day');
check(MDG_Yuzboyama::day_bounds('2026-02-29'),false,'Invalid date blocked');
check(MDG_Yuzboyama::day_bounds("2026-10-29' OR 1=1"),false,'Date injection blocked');
class WP_Error { public $code; function __construct($code,$message,$data=array()){$this->code=$code;} }
function is_wp_error($value){return $value instanceof WP_Error;}
check(MDG_Yuzboyama::search(array('query'=>'A','date'=>'2026-10-29'))->code,'invalid_search','Short name rejected');
check(MDG_Yuzboyama::search(array('query'=>'Test','date'=>'2026-02-29'))->code,'invalid_search','Invalid search date rejected');
class SearchDB {
    public $prefix='wp_',$args=array(),$writes=array(),$codes=array();
    function prepare($sql,...$args){$this->args=$args;return $sql;}
    function esc_like($value){return addcslashes($value,'_%\\');}
    function get_col($sql){return $this->codes;}
    function get_var($sql){return 0;}
    function update($table,$data,$where){$this->writes[]=array($data,$where);return 1;}
}
$wpdb=new SearchDB();
$result=MDG_Yuzboyama::search(array('query'=>'Test%','date'=>'2026-10-29'));
check($result,array('matches'=>array(),'more'=>false),'Empty name results');
check($wpdb->args,array('2026-10-29 00:00:00','2026-10-30 00:00:00','%Test\\%%'),'Name search escaped and day scoped');
class MDG_V5_Finance { static function event_date($event){return $event->date;} }
$assign=new ReflectionMethod('MDG_Yuzboyama','assign_day');if (PHP_VERSION_ID < 80100) $assign->setAccessible(true);
$wpdb->codes=array(12);$event=(object)array('id'=>7,'event_uuid'=>'test-uuid','date'=>'2026-10-28');
$assign->invoke(null,$event);check(count($wpdb->writes),0,'Other-day receipts untouched');
$event->date='2026-10-29';$assign->invoke(null,$event);
check($wpdb->writes,array(array(array('event_id'=>7,'event_uuid'=>'test-uuid'),array('order_id'=>12,'event_id'=>0))),'Only unassigned same-day receipt allocated');
echo $checks." checks passed\n";
