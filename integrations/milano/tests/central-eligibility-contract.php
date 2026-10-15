<?php
/**
 * Central eligibility pure-contract regression. NO real Woo/MDG/PayTR.
 * php integrations/milano/tests/central-eligibility-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-checkout-intent.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-central-eligibility.php';

function ensure_case( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL {$message}\n" ); exit( 1 ); }
}
$case = $argv[1] ?? 'valid';
$now = 1781000000;
$intent = mcpi_make_intent( 72, 93, array(
    array( 'code' => 'ADULT', 'quantity' => 2 ),
    array( 'code' => 'CHILD', 'quantity' => 2 ),
), $now );
ensure_case( is_array( $intent ), 'invalid intent fixture' );
$central = array(
    'event' => array(
        'id'=>72,'owner_brand'=>'milano','status'=>'onsale',
        'sale_start_utc'=>$now-3600,'sale_end_utc'=>$now+3600,
    ),
    'session' => array(
        'id'=>93,'event_id'=>72,'status'=>'onsale',
        'start_utc'=>$now+86400,'closed_by_time'=>false,
        'wc_product_id'=>1234,'tickera_event_id'=>999,
        'capacity_total'=>100,'available_units'=>15,
        'sale_start_utc'=>$now-3600,'sale_end_utc'=>$now+3600,
    ),
    'product' => array(
        'id'=>1234,'published'=>true,'variable'=>true,'currency'=>'TRY',
    ),
    'tickets' => array(
        array( 'code'=>'ADULT','active'=>true,'variation_id'=>101,
               'variation_parent_id'=>1234,'purchasable'=>true,'in_stock'=>true,
               'capacity_units'=>1,'price_cents'=>49000,'woo_price_cents'=>49000 ),
        array( 'code'=>'CHILD','active'=>true,'variation_id'=>102,
               'variation_parent_id'=>1234,'purchasable'=>true,'in_stock'=>true,
               'capacity_units'=>1,'price_cents'=>24500,'woo_price_cents'=>24500 ),
    ),
);
$expected = 'eligible';
switch ( $case ) {
    case 'valid': break;
    case 'family_package':
        $intent=mcpi_make_intent(72,93,array(array('code'=>'FAMILY4','quantity'=>1)),$now);
        $central['tickets'][]=array(
            'code'=>'FAMILY4','active'=>true,'variation_id'=>103,
            'variation_parent_id'=>1234,'purchasable'=>true,'in_stock'=>true,
            'capacity_units'=>4,'price_cents'=>110000,'woo_price_cents'=>110000,
        );
        break;
    case 'wrong_brand': $central['event']['owner_brand']='madagaskar';$expected='event_not_owned_or_open';break;
    case 'unknown_brand': unset($central['event']['owner_brand']);$expected='event_not_owned_or_open';break;
    case 'event_draft': $central['event']['status']='draft';$expected='event_not_owned_or_open';break;
    case 'event_id_mismatch': $central['event']['id']=71;$expected='event_not_owned_or_open';break;
    case 'session_id_mismatch': $central['session']['id']=94;$expected='session_not_available';break;
    case 'session_event_mismatch': $central['session']['event_id']=71;$expected='session_not_available';break;
    case 'session_closed': $central['session']['status']='closed';$expected='session_not_available';break;
    case 'session_past': $central['session']['start_utc']=$now-1;$expected='session_not_available';break;
    case 'cutoff_closed': $central['session']['closed_by_time']=true;$expected='session_not_available';break;
    case 'event_sale_future': $central['event']['sale_start_utc']=$now+1;$expected='sale_window_closed';break;
    case 'event_sale_ended': $central['event']['sale_end_utc']=$now;$expected='sale_window_closed';break;
    case 'session_sale_future': $central['session']['sale_start_utc']=$now+1;$expected='sale_window_closed';break;
    case 'session_sale_ended': $central['session']['sale_end_utc']=$now;$expected='sale_window_closed';break;
    case 'missing_window': unset($central['session']['sale_end_utc']);$expected='sale_window_closed';break;
    case 'product_mismatch': $central['product']['id']=777;$expected='product_not_usable';break;
    case 'unpublished_product': $central['product']['published']=false;$expected='product_not_usable';break;
    case 'simple_product': $central['product']['variable']=false;$expected='product_not_usable';break;
    case 'wrong_currency': $central['product']['currency']='USD';$expected='product_not_usable';break;
    case 'no_tickera': $central['session']['tickera_event_id']=0;$expected='session_not_available';break;
    case 'zero_capacity': $central['session']['available_units']=0;$expected='insufficient_capacity';break;
    case 'insufficient_capacity': $central['session']['available_units']=3;$expected='insufficient_capacity';break;
    case 'capacity_negative': $central['session']['available_units']=-1;$expected='session_not_available';break;
    case 'available_greater_than_total': $central['session']['available_units']=101;$expected='session_not_available';break;
    case 'unknown_type':
        $intent=mcpi_make_intent(72,93,array(array('code'=>'VIP','quantity'=>1)),$now);
        $expected='ticket_type_missing';break;
    case 'inactive_type': $central['tickets'][0]['active']=false;$expected='ticket_product_mismatch';break;
    case 'unavailable_variation': $central['tickets'][0]['in_stock']=false;$expected='ticket_product_mismatch';break;
    case 'unpurchasable_variation': $central['tickets'][0]['purchasable']=false;$expected='ticket_product_mismatch';break;
    case 'variation_parent_mismatch': $central['tickets'][0]['variation_parent_id']=999;$expected='ticket_product_mismatch';break;
    case 'wrong_price': $central['tickets'][0]['woo_price_cents']=49900;$expected='ticket_product_mismatch';break;
    case 'float_price': $central['tickets'][0]['price_cents']=490.0;$expected='ticket_product_mismatch';break;
    case 'negative_price': $central['tickets'][0]['price_cents']=-1;$expected='ticket_product_mismatch';break;
    case 'duplicate_codes': $central['tickets'][]=$central['tickets'][0];$expected='ticket_catalogue_invalid';break;
    case 'free_checkout':
        $central['tickets'][0]['price_cents']=0;
        $central['tickets'][0]['woo_price_cents']=0;
        $central['tickets'][1]['price_cents']=0;
        $central['tickets'][1]['woo_price_cents']=0;
        $expected='no_payable_total';break;
    case 'overpriced':
        $central['tickets'][0]['price_cents']=60000000;
        $central['tickets'][0]['woo_price_cents']=60000000;
        $expected='amount_over_limit';break;
    case 'too_many_admissions':
        $intent=mcpi_make_intent(72,93,array(
            array('code'=>'A','quantity'=>8),
            array('code'=>'B','quantity'=>8),
            array('code'=>'C','quantity'=>4)
        ),$now);
        $central['tickets']=array();
        foreach(array('A','B','C') as $i=>$code){
            $central['tickets'][]=array(
                'code'=>$code,'active'=>true,'variation_id'=>101+$i,
                'variation_parent_id'=>1234,'purchasable'=>true,'in_stock'=>true,
                'capacity_units'=>6,'price_cents'=>100,'woo_price_cents'=>100,
            );
        }
        $central['session']['capacity_total']=200;
        $central['session']['available_units']=200;
        $expected='admission_limit';break;
    case 'malformed_intent': $intent['price']=1;$expected='invalid_contract';break;
    case 'missing_snapshot': unset($central['tickets']);$expected='invalid_contract';break;
    default: ensure_case(false,'unknown test scenario');
}
$result=MCPI_Central_Eligibility::validate($intent,$central,$now);
$actual = $result['status'] === 'eligible' ? 'eligible' : ( $result['reason'] ?? $result['status'] );
ensure_case( $actual === $expected, $case.' expected '.$expected.' got '.$actual );
if($expected==='eligible') {
    ensure_case($result['quote_only']===true, 'must remain quote-only');
    ensure_case($result['owner_brand']==='milano', 'not a Milano quote');
    ensure_case($result['wc_product_id']===1234,'wrong canonical WC product');
    ensure_case($result['currency']==='TRY', 'unexpected currency');
    ensure_case(!isset($result['order_id'])&&!isset($result['paytr_token'])&&!isset($result['qr']), 'unexpected payment/QR side effect');
    if($case==='family_package'){
        ensure_case($result['total_cents']===110000,'wrong family price cents');
        ensure_case($result['admission_units']===4,'family must deduct four admission units');
        ensure_case(count($result['lines'])===1,'family needs one commercial line');
        ensure_case($result['lines'][0]['line_admission_units']===4,'family mapping wrong');
    }else{
        ensure_case($result['total_cents']===147000,'wrong 2+2 price');
        ensure_case($result['admission_units']===4,'2+2 admission count');
        ensure_case(count($result['lines'])===2,'wrong commercial lines');
    }
}
echo "PASS {$case}\n";
