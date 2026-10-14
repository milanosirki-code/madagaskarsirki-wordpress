<?php
/**
 * Composed central checkout preflight, pure fixtures only.
 * php integrations/milano/tests/central-preflight-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-checkout-intent.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-order-claim-registry.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-central-eligibility.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-family22-normalizer.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-central-family-quote.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-order-retry-verifier.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-central-preflight.php';
function expect_preflight( $v, $m ) {
    if ( ! $v ) { fwrite(STDERR, "FAIL {$m}\n"); exit(1); }
}
class PreflightDB {
    public $rows = array();
    public $last_error = '';
    public $insertCount = 0;
    public $selectKey = null;
    public function insert( $table, $row, $formats ) {
        ++$this->insertCount;
        if ( isset($this->rows[$row['request_id']]) ) { return false; }
        $this->rows[$row['request_id']] = $row;
        return 1;
    }
    public function prepare( $sql, ...$args ) {
        $this->selectKey = $args[0] ?? '';
        return 'MOCK_SQL';
    }
    public function get_row( $sql ) {
        return isset($this->rows[$this->selectKey]) ? (object) $this->rows[$this->selectKey] : null;
    }
}
$case = $argv[1] ?? 'new';
$now=1781000000;
$key=str_repeat('only-mock-not-secret-', 3);
$intent=mcpi_make_intent(72,93,array(array('code'=>'ADULT','quantity'=>2),array('code'=>'CHILD','quantity'=>2)),$now);
$sig=mcpi_sign($intent,$key);
$db=new PreflightDB();
$table='wp_dunya_milano_checkout_intents';
$snapshot=array(
    'event'=>array('id'=>72,'owner_brand'=>'milano','status'=>'onsale','sale_start_utc'=>$now-1000,'sale_end_utc'=>null),
    'session'=>array(
        'id'=>93,'event_id'=>72,'status'=>'onsale','start_utc'=>$now+86400,
        'closed_by_time'=>false,'wc_product_id'=>1234,'tickera_event_id'=>55,
        'capacity_total'=>100,'available_units'=>20,'sale_start_utc'=>null,'sale_end_utc'=>null),
    'product'=>array('id'=>1234,'published'=>true,'variable'=>true,'currency'=>'TRY'),
    'tickets'=>array(
        array('code'=>'ADULT','active'=>true,'variation_id'=>101,'variation_parent_id'=>1234,
            'purchasable'=>true,'in_stock'=>true,'capacity_units'=>1,'price_cents'=>49000,'woo_price_cents'=>49000),
        array('code'=>'CHILD','active'=>true,'variation_id'=>102,'variation_parent_id'=>1234,
            'purchasable'=>true,'in_stock'=>true,'capacity_units'=>1,'price_cents'=>24500,'woo_price_cents'=>24500),
    ),
);
$lookups=0;
$lookup=function($eid,$sid)use(&$snapshot,&$lookups){
    ++$lookups;
    expect_preflight($eid===72 && $sid===93,'must use signed event/session IDs');
    return $snapshot;
};
$orderLookups=0;
$order=array(
    'id'=>5001, 'source_brand'=>'milano', 'event_id'=>72,
    'session_id'=>93, 'request_id'=>$intent['request_id'],
    'intent_payload_hash'=>hash('sha256',
        "milano.checkout-intent.v1\n" . json_encode(
            $intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    'currency'=>'TRY', 'status'=>'pending',
);
$orderLookup=function($id)use(&$order,&$orderLookups){
    ++$orderLookups;
    expect_preflight($id===5001,'must load the authoritative existing Woo order');
    return $order;
};
$authorized=true;
$expect='claimed';
switch($case) {
    case 'new':break;
    case 'family_22':
    case 'family_22_missing_price':
    case 'family_22_soldout':
        $intent=mcpi_make_intent(72,93,array(
            array('code'=>'FAMILY22','quantity'=>1)), $now);
        $sig=mcpi_sign($intent,$key);
        $snapshot['family']=array('enabled'=>true,'price_cents'=>110000,
            'adult_code'=>'ADULT','child_code'=>'CHILD',
            'adult_ticket_type_id'=>201,'child_ticket_type_id'=>202);
        if('family_22_missing_price'===$case){
            unset($snapshot['family']['price_cents']);$expect='blocked';
        }
        if('family_22_soldout'===$case){
            $snapshot['session']['available_units']=3;$expect='blocked';
        }
        break;
    case 'repeat_processing':break;
    case 'existing_order':break;
    case 'same_id_different_items':break;
    case 'existing_order_sold_out':break;
    case 'existing_order_changed_price':break;
    case 'existing_order_cancelled_session':break;
    case 'processing_sold_out':break;
    case 'claim_conflict_sold_out':break;
    case 'existing_order_wrong_brand':break;
    case 'existing_order_wrong_event':break;
    case 'existing_order_wrong_session':break;
    case 'existing_order_wrong_payload':break;
    case 'existing_order_wrong_request':break;
    case 'existing_order_wrong_currency':break;
    case 'existing_order_wrong_order_id':break;
    case 'existing_order_refunded':break;
    case 'existing_order_unauthorized':break;
    case 'existing_order_no_lookup':break;
    case 'existing_order_lookup_error':break;
    case 'existing_order_missing_source_order':break;
    case 'read_failure':
        $db->last_error='simulated SQL failure';
        $expect='unavailable';break;
    case 'wrong_signature':
        $sig=str_repeat('0',64);$expect='rejected';break;
    case 'expired':
        $now+=91;$expect='rejected';break;
    case 'wrong_brand':
        $snapshot['event']['owner_brand']='madagaskar';$expect='blocked';break;
    case 'missing_brand':
        unset($snapshot['event']['owner_brand']);$expect='blocked';break;
    case 'out_of_stock':
        $snapshot['session']['available_units']=0;$expect='blocked';break;
    case 'price_mismatch':
        $snapshot['tickets'][0]['woo_price_cents']=49900;$expect='blocked';break;
    case 'cancelled_session':
        $snapshot['session']['status']='cancelled';$expect='blocked';break;
    case 'server_error':
        $lookup=function($eid,$sid)use(&$lookups){++$lookups;throw new RuntimeException('unavailable');};
        $expect='unavailable';break;
    case 'no_lookup':$lookup='not_callable';$expect='rejected';break;
    case 'invalid_db':$db=new stdClass();$expect='rejected';break;
    default:expect_preflight(false,'unknown scenario');
}
$retryCases=array(
    'repeat_processing','existing_order','same_id_different_items',
    'existing_order_sold_out','existing_order_changed_price',
    'existing_order_cancelled_session','processing_sold_out','claim_conflict_sold_out',
    'existing_order_wrong_brand','existing_order_wrong_event',
    'existing_order_wrong_session','existing_order_wrong_payload',
    'existing_order_wrong_request','existing_order_wrong_currency',
    'existing_order_wrong_order_id','existing_order_refunded',
    'existing_order_unauthorized','existing_order_no_lookup',
    'existing_order_lookup_error','existing_order_missing_source_order',
);
if(in_array($case,$retryCases,true)) {
    $first=MCPI_Central_Preflight::begin($db,$table,$intent,$sig,$key,$now,$lookup,$orderLookup,$authorized);
    expect_preflight($first['status']==='claimed','initial claim missing');
    if(in_array($case,array(
          'existing_order','existing_order_sold_out',
          'existing_order_changed_price','existing_order_cancelled_session',
          'existing_order_wrong_brand','existing_order_wrong_event',
          'existing_order_wrong_session','existing_order_wrong_payload',
          'existing_order_wrong_request','existing_order_wrong_currency',
          'existing_order_wrong_order_id','existing_order_refunded',
          'existing_order_unauthorized','existing_order_no_lookup',
          'existing_order_lookup_error','existing_order_missing_source_order',
       ),true)) {
        $db->rows[$intent['request_id']]['state']='ready';
        $db->rows[$intent['request_id']]['order_id']=5001;
        $expect='existing_order';
    } elseif(in_array($case,array('repeat_processing','processing_sold_out'),true)) {
        $expect='processing';
    } else {
        $intent['items'][0]['quantity']=3;
        $sig=mcpi_sign($intent,$key);
        $expect='conflict';
    }
    if(in_array($case,array('existing_order_sold_out',
        'processing_sold_out','claim_conflict_sold_out'),true)) {
        $snapshot['session']['available_units']=0;
    }
    if('existing_order_changed_price'===$case) {
        $snapshot['tickets'][0]['woo_price_cents']=99999;
    }
    if('existing_order_cancelled_session'===$case) {
        $snapshot['session']['status']='cancelled';
    }
    if('existing_order_wrong_brand'===$case) {$order['source_brand']='madagaskar';$expect='reconcile_required';}
    if('existing_order_wrong_event'===$case) {$order['event_id']=404;$expect='reconcile_required';}
    if('existing_order_wrong_session'===$case) {$order['session_id']=404;$expect='reconcile_required';}
    if('existing_order_wrong_payload'===$case) {$order['intent_payload_hash']=str_repeat('0',64);$expect='reconcile_required';}
    if('existing_order_wrong_request'===$case) {$order['request_id']=str_repeat('f',32);$expect='reconcile_required';}
    if('existing_order_wrong_currency'===$case) {$order['currency']='USD';$expect='reconcile_required';}
    if('existing_order_wrong_order_id'===$case) {$order['id']=777;$expect='reconcile_required';}
    if('existing_order_refunded'===$case) {$order['status']='refunded';$expect='reconcile_required';}
    if('existing_order_unauthorized'===$case) {$authorized=false;$expect='reconcile_required';}
    if('existing_order_no_lookup'===$case) {$orderLookup=null;$expect='reconcile_required';}
    if('existing_order_lookup_error'===$case) {
        $orderLookup=function($id)use(&$orderLookups){++$orderLookups;throw new RuntimeException('DB temporarily down');};
        $expect='unavailable';
    }
    if('existing_order_missing_source_order'===$case) {
        $orderLookup=function($id)use(&$orderLookups){++$orderLookups;return null;};
        $expect='reconcile_required';
    }
}
$r=MCPI_Central_Preflight::begin($db,$table,$intent,$sig,$key,$now,$lookup,$orderLookup,$authorized);
expect_preflight($r['status']===$expect,'status '.$r['status'].' rather than '.$expect);
if('claimed'===$expect) {
    expect_preflight(!empty($r['claim_token']) && $r['quote']['quote_only']===true,'claim/quote contract broken');
    $payable='family_22'===$case ? 110000 : 147000;
    expect_preflight($r['quote']['admission_units']===4
        && $r['quote']['total_cents']===$payable,'central price/units mismatch');
    if('family_22'===$case) {
        expect_preflight($r['quote']['family_discount_cents']===37000,
            'one family 2+2 must discount 370 TL');
        expect_preflight(count($r['quote']['lines'])===2,
            'one family must produce real ADULT+CHILD product lines');
    }
} else {
    expect_preflight(!isset($r['claim_token'])&&!isset($r['quote']),'secret worker state leaked on non-claim');
}
if(in_array($case,array('wrong_signature','expired','no_lookup','read_failure'),true)) {
    expect_preflight($lookups===0 && $db->insertCount===0,'invalid/unavailable request touched quote or inserted DB');
}
if(in_array($case,$retryCases,true)) {
    // First valid request generates quote; retry NEVER re-looks up stale/changed snapshot.
    expect_preflight($lookups===1,'repeat request re-quoted instead of resolving original state');
    expect_preflight($db->insertCount===1,'repeat created extra order request claim');
}
if(in_array($case,array('wrong_brand','missing_brand','out_of_stock','price_mismatch',
    'cancelled_session','server_error','family_22_missing_price','family_22_soldout'),true)){
    expect_preflight($db->insertCount===0,'blocked or unavailable created registry row');
}
if(in_array($case,$retryCases,true)) {
    expect_preflight(count($db->rows)===1,'duplicate created extra record');
}
if('existing_order'===$expect) {
    expect_preflight($r['order_id']===5001,'failed to reuse same central order');
}
if(in_array($case,$retryCases,true) && 'existing_order'!==$expect) {
    expect_preflight(!isset($r['order_id']),'unverified Woo order identity leaked');
}
if(in_array($case,array('existing_order_unauthorized','existing_order_no_lookup'),true)){
    expect_preflight($orderLookups===0,'unverified caller must not read central order');
}
echo "PASS {$case}\n";
