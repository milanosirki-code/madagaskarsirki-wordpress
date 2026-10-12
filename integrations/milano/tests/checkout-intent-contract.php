<?php
/**
 * Isolated checkout intent HMAC contract — NO PayTR, WP, orders or stock.
 * php integrations/milano/tests/checkout-intent-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-checkout-intent.php';
$scenario = $argv[1] ?? 'valid';
function insist( $ok, $description ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL {$description}\n" );
        exit( 1 );
    }
}
$secret = str_repeat( 'server-private-test-key-', 3 );
$now = 1781000000; // fixture clock; not current time
$items = array(
    array('code'=>'ADULT', 'quantity'=>2),
    array('code'=>'CHILD', 'quantity'=>2),
);
$intent = mcpi_make_intent( 72, 93, $items, $now );
insist( is_array( $intent ) && mcpi_schema_valid($intent), 'valid intent cannot be built' );
insist( 'milano' === $intent['brand'], 'channel must not be caller controlled' );
insist( 90 === mcpi_ttl_seconds(), 'TTL unexpectedly changed' );
insist( 32 === strlen( $intent['request_id'] ), 'nonce entropy size' );
$sig = mcpi_sign( $intent, $secret );
insist( is_string( $sig ) && strlen( $sig ) === 64, 'cannot sign valid request' );

$reject = false;
$reject_sign = false;
switch( $scenario ) {
    case 'valid':
        insist( mcpi_verify( $intent, $sig, $secret, $now ), 'valid signature rejected' );
        break;
    case 'sort_items':
        $reverse = mcpi_make_intent( 72, 93, array_reverse($items), $now );
        insist( array_column( $intent['items'], 'code' ) === array_column($reverse['items'],'code'), 'not canonical sorted order' );
        break;
    case 'random_nonce':
        $second = mcpi_make_intent( 72, 93, $items, $now );
        insist( $second['request_id'] !== $intent['request_id'], 'nonce reused' );
        break;
    case 'tamper_event': $intent['event_id'] = 73; $reject=true; break;
    case 'tamper_session': $intent['session_id'] = 94; $reject=true; break;
    case 'tamper_quantity': $intent['items'][0]['quantity'] = 3; $reject=true; break;
    case 'tamper_brand': $intent['brand'] = 'madagaskar'; $reject=true; break;
    case 'extra_price': $intent['price'] = '1.00'; $reject=true; break;
    case 'buyer_email': $intent['buyer_email'] = 'any@example.com'; $reject=true; break;
    case 'wrong_key': $secret = str_repeat('different-server-key-', 3); $reject=true; break;
    case 'weak_key': $secret = '123'; $reject=true; break;
    case 'expired': $now += 91; $reject=true; break;
    case 'clock_skew': $now -= 31; $reject=true; break;
    case 'future_within_skew': $now -= 30; insist(mcpi_verify($intent,$sig,$secret,$now),'allowed skew blocked');break;
    case 'ttl_override': $intent['expires_at'] += 1800; $reject=true; break;
    case 'signature_garbage': $sig = 'invalid'; $reject=true; break;
    case 'negative_id': $intent['event_id'] = -1; $reject=true; break;
    case 'duplicate_ticket':
        $intent['items'][] = array('code'=>'ADULT','quantity'=>1); $reject=true; break;
    case 'invalid_qty': $intent['items'][0]['quantity'] = 99; $reject=true; break;
    case 'unknown_field_item':
        $intent['items'][0]['price'] = 1; $reject=true; break;
    case 'untrusted_code': $intent['items'][0]['code'] = '../../attack'; $reject=true; break;
    case 'reordered_fields':
        $intent = array_reverse($intent, true); $reject=true; break;
    case 'empty_items': $intent['items'] = array(); $reject=true; break;
    case 'duplicate_pre_sign':
        $draft=mcpi_make_intent(72,93,array(array('code'=>'ADULT','quantity'=>1),array('code'=>'ADULT','quantity'=>1)),$now);
        insist(null===$draft,'duplicate accepted on issuance'); break;
    case 'too_many_units':
        $draft=mcpi_make_intent(72,93,array(
            array('code'=>'A','quantity'=>8),array('code'=>'B','quantity'=>8),
            array('code'=>'C','quantity'=>8)),$now);
        insist(null===$draft,'more than 20 units accepted'); break;
    case 'missing_items':
        $draft=mcpi_make_intent(72,93,array(),$now);
        insist(null===$draft,'missing tickets accepted'); break;
    case 'replay_contract':
        // Signature verification is NOT replay protection. Central recipient
        // MUST enforce UNIQUE(request_id) atomically before creating an order.
        insist(mcpi_verify($intent,$sig,$secret,$now),'first verified');
        insist(mcpi_verify($intent,$sig,$secret,$now),'same request is cryptographically valid twice');
        break;
    default: insist(false, 'unknown scenario');
}
if($reject) {
    insist(!mcpi_verify($intent,$sig,$secret,$now),'tampered/invalid envelope verified');
}
echo "PASS {$scenario}\n";
