<?php
/**
 * Standalone HPOS-safe Woo order identity and verified-customer contract.
 * php integrations/milano/tests/woo-order-resume-reader-contract.php <case>
 * Never accesses a site/database or creates/modifies a Woo order.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname(__DIR__).'/checkout-intent-protocol/milano-checkout-intent.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-order-retry-verifier.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-woo-order-resume-reader.php';
function must( $test, $msg ) {
    if ( !$test ) { fwrite( STDERR, "FAIL ".$msg."\n" ); exit(1); }
}
class FakeWooOrder {
    public $id=5001;
    public $customer=77;
    public $currency='TRY';
    public $status='pending';
    public $meta=array();
    public $throws=false;
    public function get_id() { return $this->id; }
    public function get_customer_id() { return $this->customer; }
    public function get_currency() { return $this->currency; }
    public function get_status() { return $this->status; }
    public function get_meta($key,$single=true,$context='view') {
        if ($this->throws) { throw new RuntimeException('sensitive DB failure'); }
        must($single===true && $context==='edit',
            'order meta must use privileged Woo edit CRUD context');
        return $this->meta[$key] ?? '';
    }
}
$fakeOrder=new FakeWooOrder();
$now=1781000000;
$intent=mcpi_make_intent(72,93,array(
    array('code'=>'ADULT','quantity'=>2),array('code'=>'CHILD','quantity'=>2)
),$now);
must(is_array($intent),'signed intent fixture invalid');
$hash=hash('sha256',"milano.checkout-intent.v1\n".json_encode(
    $intent,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$fakeOrder->meta=array(
    '_mcpi_source_brand'=>'milano',
    '_mcpi_event_id'=>'72',
    '_mcpi_session_id'=>'93',
    '_mcpi_request_id'=>$intent['request_id'],
    '_mcpi_intent_payload_hash'=>$hash,
);
function wc_get_order($id) {
    global $fakeOrder;
    return $fakeOrder;
}
$case=$argv[1]??'valid_pending';
$verified_customer=77;
$requested_id=5001;
$expected=true;
switch($case) {
    case 'valid_pending':break;
    case 'valid_on_hold':$fakeOrder->status='on-hold';break;
    case 'valid_processing':$fakeOrder->status='processing';break;
    case 'valid_completed':$fakeOrder->status='completed';break;
    case 'other_customer':$verified_customer=78;$expected=false;break;
    case 'guest_order':$fakeOrder->customer=0;$expected=false;break;
    case 'guest_identity':$verified_customer=0;$expected=false;break;
    case 'negative_identity':$verified_customer=-77;$expected=false;break;
    case 'unsigned_identity':$verified_customer='77junk';$expected=false;break;
    case 'overflow_identity':$verified_customer='9999999999999999999999999';$expected=false;break;
    case 'wrong_woo_order':$fakeOrder->id=9000;$expected=false;break;
    case 'zero_order_id':$requested_id=0;$expected=false;break;
    case 'wrong_brand':$fakeOrder->meta['_mcpi_source_brand']='madagaskar';$expected=false;break;
    case 'missing_brand':unset($fakeOrder->meta['_mcpi_source_brand']);$expected=false;break;
    case 'wrong_event':$fakeOrder->meta['_mcpi_event_id']=404;$expected=false;break;
    case 'wrong_session':$fakeOrder->meta['_mcpi_session_id']=404;$expected=false;break;
    case 'bad_event_id':$fakeOrder->meta['_mcpi_event_id']='72foo';$expected=false;break;
    case 'wrong_request':$fakeOrder->meta['_mcpi_request_id']=str_repeat('f',32);$expected=false;break;
    case 'bad_request':$fakeOrder->meta['_mcpi_request_id']='not-hex';$expected=false;break;
    case 'wrong_hash':$fakeOrder->meta['_mcpi_intent_payload_hash']=str_repeat('0',64);$expected=false;break;
    case 'bad_hash':$fakeOrder->meta['_mcpi_intent_payload_hash']='bad';$expected=false;break;
    case 'missing_hash':unset($fakeOrder->meta['_mcpi_intent_payload_hash']);$expected=false;break;
    case 'other_currency':$fakeOrder->currency='USD';$expected=false;break;
    case 'failed':$fakeOrder->status='failed';$expected=false;break;
    case 'cancelled':$fakeOrder->status='cancelled';$expected=false;break;
    case 'refunded':$fakeOrder->status='refunded';$expected=false;break;
    case 'trash':$fakeOrder->status='trash';$expected=false;break;
    case 'legacy_order':$fakeOrder->meta=array();$expected=false;break;
    case 'meta_exception':$fakeOrder->throws=true;$expected=false;break;
    case 'other_intent_same_order':
        $intent=mcpi_make_intent(73,93,array(
            array('code'=>'ADULT','quantity'=>2)
        ),$now);
        $expected=false;
        break;
    case 'same_customer_different_session':
        $intent=mcpi_make_intent(72,94,array(
            array('code'=>'ADULT','quantity'=>2)
        ),$now);
        $expected=false;
        break;
    default:must(false,'unknown scenario');
}
$read=MCPI_Woo_Order_Resume_Reader::lookup($requested_id,$verified_customer);
$allowed=is_array($read)
    && MCPI_Order_Retry_Verifier::allows($read,$intent,$requested_id,true);
must($allowed===$expected,'incorrect customer/order release: '.$case);
if($allowed){
    must($read['source_brand']==='milano','wrong source brand');
    must($read['event_id']===72 && $read['session_id']===93,
        'noncanonical event/session');
    must($read['request_id']===$intent['request_id'],'wrong request');
    must(!isset($read['customer_id'])&&!isset($read['email'])
        &&!isset($read['payment_token'])&&!isset($read['card_number']),
        'snapshot leaked PII or payment secrets');
}
echo "PASS ".$case."\n";
