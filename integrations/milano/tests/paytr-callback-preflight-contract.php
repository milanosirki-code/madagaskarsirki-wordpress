<?php
/**
 * PayTR identity preflight -- isolated fixture, NO real payment or WP database.
 * Run: php integrations/milano/tests/paytr-callback-preflight-contract.php CASE
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-paytr-callback-preflight.php';
function assert_preflight( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit(1); }
}
function signed_notice( $oid, $status, $total, $key, $salt ) {
    return array(
        'merchant_oid' => $oid,
        'status' => $status,
        'total_amount' => (string) $total,
        'payment_amount' => '49000',  // deliberately unsigned PayTR auxiliary
        'currency' => 'TL',          // deliberately unsigned PayTR auxiliary
        'hash' => base64_encode( hash_hmac( 'sha256',
            $oid . $salt . $status . (string)$total, $key, true ) ),
    );
}
$case = $argv[1] ?? 'valid';
$key = str_repeat('fixture-key-not-real-', 3);
$salt = 'fixture-salt-not-real';
$oid = '1781000000PAYTRWOO42';
$notice = signed_notice($oid, 'success', '49000', $key, $salt);
$order = array(
    'id'=>42, 'source_brand'=>'milano',
    'payment_method'=>'paytr_payment_gateway', 'currency'=>'TRY',
    'status'=>'pending', 'total_cents'=>49000,
    'paytr_pending_order_id'=>$oid,
    'attempts'=>array(array('merchant_oid'=>$oid, 'status'=>'success',
                           'amount'=>'49000','currency'=>'TRY')),
);
$expected = 'ready_success';
switch ($case) {
    case 'valid':break;
    case 'wrong_brand':$order['source_brand']='madagaskar';$expected='blocked';break;
    case 'empty_brand':$order['source_brand']='';$expected='blocked';break;
    case 'wrong_gateway':$order['payment_method']='bacs';$expected='blocked';break;
    case 'wrong_order':$order['id']=43;$expected='blocked';break;
    case 'wrong_pending_oid':$order['paytr_pending_order_id']='1781000001PAYTRWOO42';$expected='blocked';break;
    case 'missing_attempt':$order['attempts']=array();$expected='blocked';break;
    case 'duplicate_attempt':$order['attempts'][]=$order['attempts'][0];$expected='blocked';break;
    case 'failed_token':$order['attempts'][0]['status']='request_error';$expected='blocked';break;
    case 'other_attempt_oid':$order['attempts'][0]['merchant_oid']='1781000001PAYTRWOO42';$expected='blocked';break;
    case 'attempt_amount_changed':$order['attempts'][0]['amount']='49500';$expected='blocked';break;
    case 'order_amount_changed':$order['total_cents']=49500;$expected='blocked';break;
    case 'wrong_currency':$order['currency']='USD';$expected='blocked';break;
    case 'attempt_currency':$order['attempts'][0]['currency']='EUR';$expected='blocked';break;
    case 'tamper_status':$notice['status']='failed';$expected='rejected';break;
    case 'tamper_paid_total':$notice['total_amount']='49001';$expected='rejected';break;
    case 'tamper_oid':$notice['merchant_oid']='1781000000PAYTRWOO43';$expected='rejected';break;
    case 'invalid_hash':$notice['hash']=str_repeat('A',43).'=';$expected='rejected';break;
    case 'weak_key':$key='too-short';$expected='rejected';break;
    case 'missing_field':unset($notice['hash']);$expected='rejected';break;
    case 'invalid_notice_total':$notice['total_amount']='4.90';$expected='rejected';break;
    case 'unsigned_payment_changed':$notice['payment_amount']='1';$expected='blocked';break;
    case 'unsigned_currency_changed':$notice['currency']='USD';$expected='blocked';break;
    case 'underpayment':
        $notice=signed_notice($oid,'success','40000',$key,$salt);$expected='blocked';break;
    case 'installment_difference':
        $notice=signed_notice($oid,'success','52000',$key,$salt);$expected='manual_review';break;
    case 'failed_callback':
        $notice=signed_notice($oid,'failed','0',$key,$salt);$expected='ready_failure';break;
    case 'already_completed':$order['status']='completed';$expected='already_final';break;
    case 'already_refunded':$order['status']='refunded';$expected='already_final';break;
    case 'already_cancelled':$order['status']='cancelled';$expected='already_final';break;
    case 'failed_order':$order['status']='failed';$expected='blocked';break;
    case 'invalid_order_total':$order['total_cents']=0;$expected='blocked';break;
    case 'missing_stored_order_meta':unset($order['paytr_pending_order_id']);$expected='blocked';break;
    case 'untrusted_extra_callback_field':
        $notice['brand']='madagaskar'; // must never override central identity
        break;
    case 'spoofed_meta_brand':
        $notice['source_brand']='madagaskar'; // does not affect stored brand
        break;
    default:assert_preflight(false,'unknown scenario '.$case);
}
$actual = MCPI_PayTR_Callback_Preflight::check($notice,$order,$key,$salt);
assert_preflight($actual['status']===$expected, "expected {$expected} got ".$actual['status'].' '.$actual['reason']);
assert_preflight(count($order['attempts']??array()) <= 2,'fixture mutated');
assert_preflight(!isset($actual['order_id'])&&!isset($actual['claim_token']),'preflight must not grant order settlement');
echo "PASS {$case}\n";
