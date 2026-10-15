<?php
/**
 * Isolated idempotency-registry scenarios. NO WP, PayTR or live database.
 * php integrations/milano/tests/order-claim-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-checkout-intent.php';
require dirname( __DIR__ ) . '/checkout-intent-protocol/milano-order-claim-registry.php';

function verify_case( $ok, $message ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL {$message}\n" ); exit( 1 ); }
}
final class RegistryMockDB {
    public $rows = array();
    public $selectParams = array();
    public $updateParams = array();
    public $failInsert = false;
    public $failSelect = false;
    public $failedUpdates = false;
    public $insertCalls = 0;
    public $updateCalls = 0;
    public $queries = array();

    public function insert( $table, $record, $formats ) {
        ++$this->insertCalls;
        verify_case( 'wp_dunya_milano_checkout_intents' === $table, 'unexpected destination table' );
        verify_case( count( $record ) === count( $formats ), 'wrong SQL field format count' );
        if ( $this->failInsert || isset( $this->rows[$record['request_id']] ) ) { return false; }
        $this->rows[$record['request_id']] = $record;
        return 1;
    }
    public function prepare( $sql, ...$args ) {
        $this->queries[] = $sql;
        if ( strpos( $sql, 'SELECT ' ) !== false ) {
            verify_case( strpos( $sql, 'WHERE request_id=%s LIMIT 1' ) !== false, 'unsafe request lookup' );
            $this->selectParams = $args;
            return 'MOCK_SELECT';
        }
        if ( strpos( $sql, 'UPDATE ' ) !== false ) {
            verify_case( strpos( $sql, 'claim_token=%s AND state=%s AND order_id IS NULL' ) !== false, 'unsafe compare-and-swap order' );
            $this->updateParams = $args;
            return 'MOCK_UPDATE';
        }
        verify_case( false, 'unexpected SQL' );
    }
    public function get_row( $query ) {
        verify_case( 'MOCK_SELECT' === $query, 'unexpected query' );
        if ( $this->failSelect ) { return null; }
        $id = $this->selectParams[0];
        return isset($this->rows[$id]) ? (object) $this->rows[$id] : null;
    }
    public function query( $query ) {
        ++$this->updateCalls;
        verify_case( 'MOCK_UPDATE' === $query, 'unexpected update' );
        if ( $this->failedUpdates ) { return false; }
        list($orderId,$status,$updated,$request,$token,$currentState) = $this->updateParams;
        if ( ! isset( $this->rows[$request] )
            || $this->rows[$request]['claim_token'] !== $token
            || $this->rows[$request]['state'] !== $currentState
            || null !== $this->rows[$request]['order_id'] ) { return 0; }
        foreach( $this->rows as $otherId=>$item ) {
            if ( $otherId !== $request && (int)($item['order_id'] ?? 0) === $orderId ) {
                return false; // UNIQUE order_id
            }
        }
        $this->rows[$request]['order_id'] = $orderId;
        $this->rows[$request]['state'] = $status;
        $this->rows[$request]['updated_at'] = $updated;
        return 1;
    }
}
$case = $argv[1] ?? 'new_claim';
$db = new RegistryMockDB();
$table = 'wp_dunya_milano_checkout_intents';
$secret = str_repeat( 'sample-private-not-real-key-', 3 );
$now = 1781000000;
$payload = mcpi_make_intent( 72, 93, array( array('code'=>'ADULT','quantity'=>2),array('code'=>'CHILD','quantity'=>2) ), $now );
$sig = mcpi_sign( $payload, $secret );
verify_case( is_array($payload) && is_string($sig), 'unable to generate HMAC test fixture' );
$reg = 'MCPI_Order_Claim_Registry';
$ddl = $reg::ddl( $table );
verify_case( strpos($ddl,'PRIMARY KEY (request_id)')!==false, 'request id not unique in DDL' );
verify_case( strpos($ddl,'UNIQUE KEY order_id (order_id)')!==false, 'order id not unique in DDL' );
verify_case( strpos($ddl,'ENGINE=InnoDB')!==false, 'table must use InnoDB' );
verify_case( null === $reg::ddl( 'bad-name;drop table' ), 'invalid table accepted' );
$base = $reg::claim($db,$table,$payload,$sig,$secret,$now);

if ( 'insert_unavailable' === $case ) {
    // Same identity exists, so instead force separate DB outage at lookup.
    $db2=new RegistryMockDB();
    $db2->failInsert=true;
    $db2->failSelect=true;
    $r=$reg::claim($db2,$table,$payload,$sig,$secret,$now);
    verify_case('unavailable' === $r['status'],'DB unavailable not rejected');
    verify_case(count($db2->rows)===0,'DB outage persisted state');
} elseif ( 'bad_signature' === $case || 'expired' === $case || 'weak_secret' === $case || 'bad_table' === $case ) {
    $db2=new RegistryMockDB();
    $p=$payload;$sign=$sig;$key=$secret;$timestamp=$now;$t=$table;
    if('bad_signature'===$case){$sign=str_repeat('0',64);}
    if('expired'===$case){$timestamp+=91;}
    if('weak_secret'===$case){$key='not-a-valid-secret';}
    if('bad_table'===$case){$t='wp-test;DROP';}
    $r=$reg::claim($db2,$t,$p,$sign,$key,$timestamp);
    verify_case('rejected'===$r['status'],'invalid input admitted');
    verify_case(0===$db2->insertCalls,'invalid signed claim touched DB');
} else {
    verify_case('claimed'===$base['status'] && strlen($base['claim_token'])===32,'first worker not claimed');
    verify_case(count($db->rows)===1, 'missing one unique claim');
    $id=$payload['request_id'];$token=$base['claim_token'];
    switch($case) {
        case 'new_claim':
            verify_case(0===$db->updateCalls, 'claim should not generate order or hold');break;
        case 'same_in_flight':
        case 'parallel_worker':
            $again=$reg::claim($db,$table,$payload,$sig,$secret,$now);
            verify_case('processing'===$again['status'], 'duplicate must remain in flight');
            verify_case(!isset($again['claim_token']),'second worker must not own token');
            verify_case(count($db->rows)===1, 'duplicate inserted new claim');
            break;
        case 'attach_once':
            verify_case($reg::attach_order($db,$table,$id,$token,1001,$now),'first attach failed');
            verify_case(!$reg::attach_order($db,$table,$id,$token,1002,$now),'claim overwrote existing order');
            verify_case($db->rows[$id]['order_id']===1001,'wrong order after second attach');
            break;
        case 'duplicate_ready':
            verify_case($reg::attach_order($db,$table,$id,$token,1001,$now),'attach failed');
            $r=$reg::claim($db,$table,$payload,$sig,$secret,$now);
            verify_case('existing_order'===$r['status'] && 1001===$r['order_id'], 'ready duplicate created new order');
            verify_case(!isset($r['claim_token']),'ready response leaked worker token');
            break;
        case 'different_payload_same_request':
            $other=$payload;
            $other['items'][0]['quantity']=3;
            $otherSig=mcpi_sign($other,$secret);
            $r=$reg::claim($db,$table,$other,$otherSig,$secret,$now);
            verify_case('conflict'===$r['status'],'same ID different payload not blocked');
            verify_case(count($db->rows)===1,'tamper created row');
            break;
        case 'changed_event_same_request':
            $other=$payload;$other['event_id']=73;$otherSig=mcpi_sign($other,$secret);
            $r=$reg::claim($db,$table,$other,$otherSig,$secret,$now);
            verify_case('conflict'===$r['status'],'changed event ID with reused request accepted');
            break;
        case 'unauthorized_worker':
            verify_case(!$reg::attach_order($db,$table,$id,str_repeat('0',32),1001,$now),'wrong claim token bound order');
            verify_case(null===$db->rows[$id]['order_id'],'unauthorized worker changed row');
            break;
        case 'invalid_order':
            verify_case(!$reg::attach_order($db,$table,$id,$token,0,$now),'zero order ID accepted');break;
        case 'invalid_request':
            verify_case(!$reg::attach_order($db,$table,'bad',$token,1001,$now),'bad request ID accepted');break;
        case 'reuse_order_other_request':
            verify_case($reg::attach_order($db,$table,$id,$token,1001,$now),'attach failed');
            $second=mcpi_make_intent(72,93,$payload['items'],$now);
            $sig2=mcpi_sign($second,$secret);
            $claim2=$reg::claim($db,$table,$second,$sig2,$secret,$now);
            verify_case('claimed'===$claim2['status'],'second distinct request must be claimable before semantic check');
            verify_case(!$reg::attach_order($db,$table,$second['request_id'],$claim2['claim_token'],1001,$now),'same Woo order attached twice');
            break;
        case 'no_automatic_claim_timeout':
            $db->rows[$id]['updated_at']='2010-01-01 00:00:00';
            $later=$reg::claim($db,$table,$payload,$sig,$secret,$now);
            verify_case('processing'===$later['status'],'stale claim stolen, risk duplicate Woo order');
            break;
        case 'invalid_state':
            $db->rows[$id]['state']='unknown';
            $r=$reg::claim($db,$table,$payload,$sig,$secret,$now);
            verify_case('reconcile_required'===$r['status'],'unknown state should reconcile');
            break;
        case 'broken_ready':
            $db->rows[$id]['state']='ready';
            $r=$reg::claim($db,$table,$payload,$sig,$secret,$now);
            verify_case('reconcile_required'===$r['status'],'ready without order accepted');
            break;
        case 'db_update_failure':
            $db->failedUpdates=true;
            verify_case(!$reg::attach_order($db,$table,$id,$token,1001,$now),'failed DB update falsely succeeded');
            verify_case($db->rows[$id]['state']==='claimed','DB failure modified row');
            break;
        case 'order_id_wrong_type':
            verify_case(!$reg::attach_order($db,$table,$id,$token,'1001',$now),'string order id accepted');break;
        default: verify_case(false, 'unknown scenario');
    }
}
echo "PASS {$case}\n";
