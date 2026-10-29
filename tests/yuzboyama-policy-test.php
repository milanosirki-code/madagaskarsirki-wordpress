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
    public $paid=true,$marker='yes',$confirmation='PAYTRWOO1234',$refund=0,$refunded_qty=0,$gateway='paytr_payment_gateway';
    function get_meta($key){return $key==='_mdg_yb'?$this->marker:$this->confirmation;}
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
echo $checks." checks passed\n";
