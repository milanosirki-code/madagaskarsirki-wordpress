<?php
/**
 * Signed FAMILY22 order quote -> central paid component tickets.
 * Pure fixture: NEVER touches WordPress, Woo, PayTR, MDG stock or Tickera.
 * php integrations/milano/tests/central-family-quote-contract.php <case>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname(__DIR__).'/checkout-intent-protocol/milano-checkout-intent.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-central-eligibility.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-family22-normalizer.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-central-family-quote.php';

function assert_family_quote($truth,$message){if(!$truth){fwrite(STDERR,"FAIL ".$message."\n");exit(1);}}
$scenario=$argv[1]??'family';
$now=1781000000;
$items=array(array('code'=>'FAMILY22','quantity'=>1));
$snapshot=array(
 'event'=>array('id'=>72,'owner_brand'=>'milano','status'=>'onsale',
     'sale_start_utc'=>null,'sale_end_utc'=>null),
 'session'=>array('id'=>93,'event_id'=>72,'status'=>'onsale',
     'start_utc'=>$now+86400,'closed_by_time'=>false,
     'wc_product_id'=>501,'tickera_event_id'=>901,
     'capacity_total'=>500,'available_units'=>500,
     'sale_start_utc'=>null,'sale_end_utc'=>null),
 'product'=>array('id'=>501,'published'=>true,'variable'=>true,'currency'=>'TRY'),
 'tickets'=>array(
   array('code'=>'CHILD','active'=>true,'variation_id'=>602,
     'variation_parent_id'=>501,'purchasable'=>true,'in_stock'=>true,
     'capacity_units'=>1,'price_cents'=>24500,'woo_price_cents'=>24500),
   array('code'=>'ADULT','active'=>true,'variation_id'=>601,
     'variation_parent_id'=>501,'purchasable'=>true,'in_stock'=>true,
     'capacity_units'=>1,'price_cents'=>49000,'woo_price_cents'=>49000),
 ),
 'family'=>array('enabled'=>true,'price_cents'=>110000,
    'adult_code'=>'ADULT','child_code'=>'CHILD',
    'adult_ticket_type_id'=>201,'child_ticket_type_id'=>202)
);
$want='eligible';$admissions=4;$payable=110000;$discount=37000;
switch($scenario){
 case 'family':break;
 case 'two_families':
    $items[0]['quantity']=2;$admissions=8;$payable=220000;$discount=74000;break;
 case 'extra_child':
    $items[]=array('code'=>'CHILD','quantity'=>1);
    $admissions=5;$payable=134500;break;
 case 'extra_adult':
    $items[]=array('code'=>'ADULT','quantity'=>1);
    $admissions=5;$payable=159000;break;
 case 'soldout':
    $snapshot['session']['available_units']=3;$want='blocked';break;
 case 'wrong_brand':
    $snapshot['event']['owner_brand']='madagaskar';$want='blocked';break;
 case 'wrong_session':
    $snapshot['session']['id']=94;$want='blocked';break;
 case 'wrong_currency':
    $snapshot['product']['currency']='USD';$want='blocked';break;
 case 'invalid_parent':
    $snapshot['tickets'][0]['variation_parent_id']=999;$want='blocked';break;
 case 'price_mismatch':
    $snapshot['tickets'][1]['woo_price_cents']=77777;$want='blocked';break;
 case 'disabled_family':
    $snapshot['family']['enabled']=false;$want='blocked';break;
 case 'missing_family':
    unset($snapshot['family']);$want='blocked';break;
 case 'price_above_components':
    $snapshot['family']['price_cents']=147001;$want='blocked';break;
 case 'free_family':
    $snapshot['family']['price_cents']=0;$want='blocked';break;
 case 'wrong_adult_code':
    $snapshot['family']['adult_code']='VIP';$want='blocked';break;
 case 'invalid_family_ticket_id':
    $snapshot['family']['child_ticket_type_id']=0;$want='blocked';break;
 case 'inactive_child':
    $snapshot['tickets'][0]['active']=false;$want='blocked';break;
 case 'multi_unit_child':
    $snapshot['tickets'][0]['capacity_units']=4;$want='blocked';break;
 case 'too_many_families':
    $items[0]['quantity']=5;$want='blocked';break;
 case 'too_many_with_extra':
    $items[0]['quantity']=4;
    $items[]=array('code'=>'ADULT','quantity'=>1);$want='blocked';break;
 case 'fake_family_variation':
    // Even if Woo contains a FAMILY22 variation, it must NEVER represent
    // a physical four-unit ticket or appear among resulting Woo lines.
    $snapshot['tickets'][]=array('code'=>'FAMILY22','active'=>true,
      'variation_id'=>999,'variation_parent_id'=>501,
      'purchasable'=>true,'in_stock'=>true,
      'capacity_units'=>4,'price_cents'=>110000,'woo_price_cents'=>110000);
    break;
 case 'unknown_extra_code':
    $items[]=array('code'=>'VIP','quantity'=>1);$want='blocked';break;
 case 'tampered_client_amount':
    $items[0]['price_cents']=1;$want='blocked';break;
 default:assert_family_quote(false,'unknown scenario '.$scenario);
}
$intent=mcpi_make_intent(72,93,$items,$now);
if('tampered_client_amount'===$scenario){
    assert_family_quote(null===$intent,'unsigned client price was accepted');
    echo "PASS {$scenario}\n";exit;
}
assert_family_quote(is_array($intent),'signed intent fixture invalid');
$r=MCPI_Milano_Central_Family_Quote::validate($intent,$snapshot,$now);
assert_family_quote(($r['status']??'')===$want,$scenario.' unexpected '.json_encode($r));
if('blocked'===$want){
    assert_family_quote(!isset($r['total_cents'])&&!isset($r['lines']),
        'a blocked basket emitted a sellable quote');
    echo "PASS {$scenario}\n";exit;
}
assert_family_quote($r['total_cents']===$payable,'wrong payable amount');
assert_family_quote($r['admission_units']===$admissions,'wrong physical admissions');
assert_family_quote($r['component_subtotal_cents']-$r['family_discount_cents']===$payable,
    'missing 2+2 fee discount');
assert_family_quote($r['family_discount_cents']===$discount,'wrong discount');
assert_family_quote(count($r['lines'])===2,'not two real Woo product lines');
assert_family_quote($r['lines'][0]['code']==='ADULT'&&$r['lines'][1]['code']==='CHILD',
    'virtual family product escaped into physical tickets');
assert_family_quote($r['lines'][0]['line_admission_units']+$r['lines'][1]['line_admission_units']
    ===$admissions,'under-counted physical QR admissions');
assert_family_quote($r['family_virtual_line_stored_as_ticket']===false,
    'virtual family treated as a real ticket');
assert_family_quote($r['quote_only']===true,'unexpected payment/hold authorization');
assert_family_quote(!isset($r['order_id'])&&!isset($r['paytr_token']),
    'pure quote caused a payment/order side effect');
echo "PASS {$scenario}\n";
