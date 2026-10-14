<?php
/** Isolated Milano Woo -> MDG hold -> PayTR amount parity, no live effects. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/checkout-intent-protocol/milano-before-paytr-order-gate.php';
function must_gate($v,$why){if(!$v){fwrite(STDERR,"FAIL ".$why."\n");exit(1);}}
$case=$argv[1]??'family22';
$req=str_repeat('a',32);
$quote=array(
 'owner_brand'=>'milano','event_id'=>72,'session_id'=>93,
 'wc_product_id'=>301,'currency'=>'TRY','total_cents'=>110000,
 'admission_units'=>4,'family_discount_cents'=>37000,
 'component_subtotal_cents'=>147000,'quote_only'=>true,
 'lines'=>array(
   array('code'=>'ADULT','variation_id'=>401,'quantity'=>2,
     'line_total_cents'=>98000,'line_admission_units'=>2,
     'capacity_units_per_ticket_type'=>1),
   array('code'=>'CHILD','variation_id'=>402,'quantity'=>2,
     'line_total_cents'=>49000,'line_admission_units'=>2,
     'capacity_units_per_ticket_type'=>1)
 )
);
$order=array(
 'id'=>500,'source_brand'=>'milano','event_id'=>72,'session_id'=>93,
 'wc_product_id'=>301,'currency'=>'TRY','status'=>'pending',
 'payment_method'=>'paytr_payment_gateway','total_cents'=>110000,
 'total_tax_cents'=>0,'checkout_request_id'=>$req,
 'lines'=>array(
  array('id'=>901,'code'=>'ADULT','variation_id'=>401,'quantity'=>2,
     'subtotal_cents'=>98000,'total_cents'=>98000,
     'total_tax_cents'=>0,'session_id'=>93),
  array('id'=>902,'code'=>'CHILD','variation_id'=>402,'quantity'=>2,
     'subtotal_cents'=>49000,'total_cents'=>49000,
     'total_tax_cents'=>0,'session_id'=>93)
 ),
 'fees'=>array(array('code'=>'MILANO_FAMILY22_DISCOUNT',
                     'total_cents'=>-37000,'tax_cents'=>0))
);
$hold=array('order_id'=>500,'event_id'=>72,'session_id'=>93,
  'units'=>4,'state'=>'held','owner_brand'=>'milano');
$calcTaxes=false;$expected='eligible';$expectedAmount=110000;$expectedUnits=4;
switch($case){
 case 'family22':break;
 case 'two_families':
    $quote['lines'][0]['quantity']=4;$quote['lines'][0]['line_total_cents']=196000;
    $quote['lines'][0]['line_admission_units']=4;
    $quote['lines'][1]['quantity']=4;$quote['lines'][1]['line_total_cents']=98000;
    $quote['lines'][1]['line_admission_units']=4;
    $order['lines'][0]['quantity']=4;$order['lines'][0]['subtotal_cents']=196000;
    $order['lines'][0]['total_cents']=196000;
    $order['lines'][1]['quantity']=4;$order['lines'][1]['subtotal_cents']=98000;
    $order['lines'][1]['total_cents']=98000;
    $quote['component_subtotal_cents']=294000;
    $quote['family_discount_cents']=74000;$order['fees'][0]['total_cents']=-74000;
    $quote['total_cents']=220000;$order['total_cents']=220000;
    $quote['admission_units']=8;$hold['units']=8;
    $expectedAmount=220000;$expectedUnits=8;break;
 case 'extra_child':
    $quote['lines'][1]['quantity']=3;$quote['lines'][1]['line_total_cents']=73500;
    $quote['lines'][1]['line_admission_units']=3;
    $order['lines'][1]['quantity']=3;$order['lines'][1]['subtotal_cents']=73500;
    $order['lines'][1]['total_cents']=73500;
    $quote['component_subtotal_cents']=171500;
    $quote['total_cents']=134500;$order['total_cents']=134500;
    $quote['admission_units']=5;$hold['units']=5;
    $expectedAmount=134500;$expectedUnits=5;break;
 case 'individuals':
    $quote['lines'][0]['quantity']=1;$quote['lines'][0]['line_total_cents']=49000;
    $quote['lines'][0]['line_admission_units']=1;
    $quote['lines'][1]['quantity']=1;$quote['lines'][1]['line_total_cents']=24500;
    $quote['lines'][1]['line_admission_units']=1;
    $order['lines'][0]['quantity']=1;$order['lines'][0]['subtotal_cents']=49000;
    $order['lines'][0]['total_cents']=49000;
    $order['lines'][1]['quantity']=1;$order['lines'][1]['subtotal_cents']=24500;
    $order['lines'][1]['total_cents']=24500;
    $quote['component_subtotal_cents']=73500;
    $quote['family_discount_cents']=0;$order['fees']=array();
    $quote['total_cents']=73500;$order['total_cents']=73500;
    $quote['admission_units']=2;$hold['units']=2;
    $expectedAmount=73500;$expectedUnits=2;break;
 case 'other_brand':$order['source_brand']='madagaskar';$expected='blocked';break;
 case 'wrong_owner':$quote['owner_brand']='madagaskar';$expected='blocked';break;
 case 'wrong_hold_brand':$hold['owner_brand']='madagaskar';$expected='blocked';break;
 case 'other_event':$order['event_id']=73;$expected='blocked';break;
 case 'other_session':$order['session_id']=94;$expected='blocked';break;
 case 'other_product':$order['wc_product_id']=305;$expected='blocked';break;
 case 'other_hold_session':$hold['session_id']=94;$expected='blocked';break;
 case 'other_hold_event':$hold['event_id']=73;$expected='blocked';break;
 case 'other_hold_order':$hold['order_id']=501;$expected='blocked';break;
 case 'hold_short':$hold['units']=3;$expected='blocked';break;
 case 'hold_sold':$hold['state']='sold';$expected='blocked';break;
 case 'hold_released':$hold['state']='released';$expected='blocked';break;
 case 'bad_order_id':$order['id']=0;$expected='blocked';break;
 case 'bad_signed_request':$req=str_repeat('b',32);$expected='blocked';break;
 case 'bad_order_request':$order['checkout_request_id']='not-a-request';$expected='blocked';break;
 case 'wrong_currency':$order['currency']='EUR';$expected='blocked';break;
 case 'other_gateway':$order['payment_method']='bacs';$expected='blocked';break;
 case 'order_completed':$order['status']='completed';$expected='blocked';break;
 case 'order_on_hold':$order['status']='on-hold';$expected='blocked';break;
 case 'price_1k_off':$order['total_cents']=109000;$expected='blocked';break;
 case 'wrong_fee':$order['fees'][0]['total_cents']=-36000;$expected='blocked';break;
 case 'extra_fee':
    $order['fees'][]=array('code'=>'SUSPICIOUS','total_cents'=>-100,'tax_cents'=>0);
    $expected='blocked';break;
 case 'wrong_fee_label':$order['fees'][0]['code']='COUPON';$expected='blocked';break;
 case 'missing_fee':$order['fees']=array();$expected='blocked';break;
 case 'wrong_tax_enabled':$calcTaxes=true;$expected='blocked';break;
 case 'wrong_tax_cents':$order['total_tax_cents']=2000;$expected='blocked';break;
 case 'wrong_line_tax':$order['lines'][0]['total_tax_cents']=2000;$expected='blocked';break;
 case 'line_subtotal_changed':$order['lines'][0]['subtotal_cents']=99000;$expected='blocked';break;
 case 'line_discount_changed':$order['lines'][0]['total_cents']=97000;$expected='blocked';break;
 case 'wrong_variation':$order['lines'][1]['variation_id']=888;$expected='blocked';break;
 case 'wrong_line_qty':$order['lines'][0]['quantity']=1;$expected='blocked';break;
 case 'duplicate_line_id':$order['lines'][1]['id']=901;$expected='blocked';break;
 case 'duplicate_line_code':$order['lines'][1]['code']='ADULT';$expected='blocked';break;
 case 'missing_line':array_pop($order['lines']);$expected='blocked';break;
 case 'wrong_line_session':$order['lines'][1]['session_id']=94;$expected='blocked';break;
 case 'wrong_admission_units':$quote['admission_units']=3;$expected='blocked';break;
 case 'four_unit_fake_family':
    $quote['lines'][0]['capacity_units_per_ticket_type']=4;$expected='blocked';break;
 case 'quoted_subtotal_changed':$quote['component_subtotal_cents']=145000;$expected='blocked';break;
 case 'quoted_discount_changed':$quote['family_discount_cents']=39000;$expected='blocked';break;
 case 'quote_not_only':$quote['quote_only']=false;$expected='blocked';break;
 case 'unknown_fee_no_family':
    $quote['family_discount_cents']=0;
    $order['fees']=array(array('code'=>'COUPON','total_cents'=>-37000,'tax_cents'=>0));
    $expected='blocked';break;
 default:must_gate(false,'unknown scenario '.$case);
}
$r=MCPI_Before_PayTR_Order_Gate::check($quote,$order,$hold,$calcTaxes,$req);
must_gate(($r['status']??'')===$expected,
  $case.' expected '.$expected.' got '.json_encode($r));
if($expected==='eligible'){
 must_gate($r['payment_amount_cents']===$expectedAmount,
   'amount not equal to trusted Woo payable');
 must_gate($r['admission_units']===$expectedUnits,'capacity units differ');
 must_gate($r['token_requested']===false,'pure gate invoked PayTR');
 must_gate($r['order_id']===500,'wrong private Woo order');
}else{
 must_gate(!isset($r['payment_amount_cents'])&&!isset($r['order_id']),
   'a rejected quote can initiate payment');
}
echo "PASS {$case}\n";
