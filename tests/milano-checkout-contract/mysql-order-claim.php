<?php
/** MySQL 8.4 integration test: 2 concurrent checkout intent claims, one Woo order. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
    public string $code;
    public function __construct($code,$message=''){ $this->code=$code; }
}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
class MysqlWpdb {
    public string $prefix='wp_';
    public static bool $foundRows=false;
    public PDO $pdo;
    public function __construct(){
        $this->pdo=new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=milano_claim_test;charset=utf8mb4',
            'root','test-only-root',[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::MYSQL_ATTR_FOUND_ROWS=>self::$foundRows
            ]
        );
    }
    public function prepare($format,...$args):string{
        $args=array_map(function($a){
            return is_string($a)?$this->pdo->quote($a):(int)$a;
        },$args);
        return vsprintf($format,$args);
    }
    public function query($sql){
        try{return $this->pdo->exec($sql);}
        catch(Throwable $e){return false;}
    }
    public function get_row($sql){
        try{$r=$this->pdo->query($sql)->fetch(PDO::FETCH_OBJ);return $r?:null;}
        catch(Throwable $e){return null;}
    }
}
require dirname(__DIR__,2).'/integrations/milano/checkout-contract/milano-canonical-order-claim.php';
function test($ok,$message):void{
    global $passes;
    if(!$ok){fwrite(STDERR,"FAIL $message\n");exit(1);}
    $passes++;echo "PASS $message\n";
}
function is_code($r,string $code):bool{return is_wp_error($r)&&$r->code===$code;}
function claim_key(int $n):string{return str_pad(dechex($n),64,'a',STR_PAD_LEFT);}
function reset_ledger(MysqlWpdb $db):void{
    $db->pdo->exec('DROP TABLE IF EXISTS wp_milano_order_claims_v1');
    $s=Milano_Canonical_Order_Claim::schema_sql('wp_');
    if(is_wp_error($s))throw new RuntimeException('Schema error');
    $db->pdo->exec($s);
}
function count_intent(MysqlWpdb $db,string $k):int{
    $q=$db->pdo->prepare('SELECT COUNT(*) FROM wp_milano_order_claims_v1 WHERE intent_key=?');
    $q->execute([$k]);return (int)$q->fetchColumn();
}
function fork_race(string $mode,string $intent,string $quote,int $order_id):array{
    $barrier=sys_get_temp_dir().'/milano-intent-race-'.bin2hex(random_bytes(8));
    $child=pcntl_fork();
    if($child<0)throw new RuntimeException('Fork unavailable');
    if($child===0){
        global $wpdb;
        $wpdb=new MysqlWpdb();
        file_put_contents($barrier.'.child','ready');
        $deadline=microtime(true)+8;
        while(!is_file($barrier.'.parent')){
            if(microtime(true)>$deadline)exit(2);
            usleep(10000);
        }
        if($mode==='claim') {
            $r=Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,4);
        } elseif($mode==='reserve') {
            $r=Milano_Canonical_Order_Claim::reservation_transition($intent,$order_id,'bound','reserving');
        } else {
            $r=Milano_Canonical_Order_Claim::attach_order($intent,$order_id+1);
        }
        file_put_contents($barrier.'.child-result',json_encode([
            'error'=>is_wp_error($r)?$r->code:null,
            'created'=>is_array($r)?($r['created']??null):null,
            'replayed'=>is_array($r)?($r['replayed']??null):null,
            'order'=>is_array($r)?($r['wc_order_id']??null):null
        ]));
        exit(0);
    }
    global $wpdb;
    $wpdb=new MysqlWpdb();
    file_put_contents($barrier.'.parent','ready');
    $deadline=microtime(true)+8;
    while(!is_file($barrier.'.child')){
        if(microtime(true)>$deadline)throw new RuntimeException('Child readiness timed out');
        usleep(10000);
    }
    if($mode==='claim'){
        $parent=Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,4);
    }elseif($mode==='reserve'){
        $parent=Milano_Canonical_Order_Claim::reservation_transition($intent,$order_id,'bound','reserving');
    }else{
        $parent=Milano_Canonical_Order_Claim::attach_order($intent,$order_id);
    }
    pcntl_waitpid($child,$status);
    if(!pcntl_wifexited($status)||pcntl_wexitstatus($status)!==0)
        throw new RuntimeException('Concurrent child failed exit='.pcntl_wexitstatus($status));
    $child_result=json_decode(file_get_contents($barrier.'.child-result'),true);
    foreach(['.child','.parent','.child-result'] as $suffix)@unlink($barrier.$suffix);
    return [$parent,$child_result];
}
$passes=0;
$wpdb=new MysqlWpdb();
reset_ledger($wpdb);
$quote=hash('sha256','yalova-MDG24-session151-family2plus2-110000-4');
$intent=claim_key(222);
test(is_code(Milano_Canonical_Order_Claim::schema_sql('wp_unsafe;'),'milano_schema_prefix'),'reject dangerous table prefix');
test(is_code(Milano_Canonical_Order_Claim::claim('short',$quote,24,151,110000,4),'milano_claim_invalid'),'reject invalid nonce');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,'INVALID',24,151,110000,4),'milano_claim_invalid'),'reject unsigned quote hash');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,0),'milano_claim_invalid'),'reject zero admission units');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,21),'milano_claim_invalid'),'reject excessive admission units');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,-1,4),'milano_claim_invalid'),'reject invalid price');
test(count_intent($wpdb,$intent)===0,'invalid claims make no DB rows');
[$a,$b]=fork_race('claim',$intent,$quote,6010);
$wpdb=new MysqlWpdb();
test(is_array($a)&&$b['error']===null&&($a['created']!==$b['created']),'concurrent identical checkout requests yield one and only one creator');
test(count_intent($wpdb,$intent)===1,'unique intent ledger holds one row after race');
// Simulate MySQL CLIENT_FOUND_ROWS=1, in which an upsert-with-no-op used to
// return affected_rows=1 for both creators and duplicate replays.
MysqlWpdb::$foundRows=true;
$foundIntent=claim_key(229);
[$foundFirst,$foundSecond]=fork_race('claim',$foundIntent,$quote,6020);
$wpdb=new MysqlWpdb();
test(is_array($foundFirst)&&$foundSecond['error']===null
    && $foundFirst['created']!==$foundSecond['created'],
    'CLIENT_FOUND_ROWS=1 still allows exactly one creator');
test(count_intent($wpdb,$foundIntent)===1,'CLIENT_FOUND_ROWS duplicate stays one DB row');
$foundReplay=Milano_Canonical_Order_Claim::claim($foundIntent,$quote,24,151,110000,4);
test(is_array($foundReplay)&&$foundReplay['created']===false,
    'CLIENT_FOUND_ROWS retry never grants create-order permission');
MysqlWpdb::$foundRows=false;
$same=Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,4);
test(is_array($same)&&$same['created']===false&&$same['wc_order_id']===null,'retry cannot authorize second Woo order');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,hash('sha256','tampered-quote'),24,151,110000,4),'milano_claim_conflict'),'same nonce with different price quote digest refused');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,$quote,24,150,110000,4),'milano_claim_conflict'),'same nonce wrong session refused');
test(is_code(Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110100,4),'milano_claim_conflict'),'same nonce changed price refused');
[$first,$second]=fork_race('bind',$intent,$quote,6010);
$wpdb=new MysqlWpdb();
$winner=is_array($first)?$first['wc_order_id']:$second['order'];
$loser_error=is_array($first)?$second['error']:($first->code??'');
test(in_array((int)$winner,[6010,6011],true)&&$loser_error==='milano_order_bind_conflict','competing Woo order bindings accept exactly one order');
$repeat=Milano_Canonical_Order_Claim::attach_order($intent,(int)$winner);
test(is_array($repeat)&&$repeat['replayed']===true,'same winning Woo binding replay is idempotent');
$bound=Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,4);
test(is_array($bound)&&$bound['created']===false&&$bound['state']==='bound'&&$bound['wc_order_id']===(int)$winner,'repeat checkout returns canonical bound order');
// Two concurrent requests must never both invoke the MDG stock-reservation owner.
[$reserveParent,$reserveChild]=fork_race('reserve',$intent,$quote,(int)$winner);
$wpdb=new MysqlWpdb();
$one_reserver=is_array($reserveParent)!==($reserveChild['error']===null);
test($one_reserver,'two simultaneous reserve attempts allow exactly one caller into MDG');
test(is_code($reserveParent,'milano_reservation_transition_conflict')
     ||$reserveChild['error']==='milano_reservation_transition_conflict',
     'losing worker is explicitly blocked from duplicate MDG stock hold');
$inflight=Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,4);
test(is_array($inflight)&&$inflight['state']==='reserving'
     &&$inflight['wc_order_id']===(int)$winner,
     'ambiguous in-flight reservation remains the same canonical order');
$existingInFlight=Milano_Canonical_Order_Claim::existing($intent,$quote,24,151,110000,4);
test(is_array($existingInFlight)&&$existingInFlight['state']==='reserving'
     &&$existingInFlight['wc_order_id']===(int)$winner,
     'read-only lookup exposes reserving state for payment gate blocking');
test(is_code(Milano_Canonical_Order_Claim::reservation_transition($intent,(int)$winner,'bound','reserving'),
     'milano_reservation_transition_conflict'),
     'retry is blocked while a reservation is in progress');
$ready=Milano_Canonical_Order_Claim::reservation_transition($intent,(int)$winner,'reserving','ready');
test(is_array($ready)&&$ready['state']==='ready',
    'only completed MDG audit may advance reservation to ready');
$readyRepeat=Milano_Canonical_Order_Claim::claim($intent,$quote,24,151,110000,4);
test(is_array($readyRepeat)&&$readyRepeat['state']==='ready'
     &&$readyRepeat['created']===false
     &&$readyRepeat['wc_order_id']===(int)$winner,
     'ready replay returns original order, never grants another WC order');
$existingReady=Milano_Canonical_Order_Claim::existing($intent,$quote,24,151,110000,4);
test(is_array($existingReady)&&$existingReady['state']==='ready'
     &&$existingReady['wc_order_id']===(int)$winner,
     'read-only lookup returns ready state for same-order replay');
test(is_code(Milano_Canonical_Order_Claim::existing($intent,$quote,24,150,110000,4),'milano_claim_conflict'),
     'wrong-session lookup cannot reuse ready reservation');
test(is_code(Milano_Canonical_Order_Claim::reservation_transition($intent,(int)$winner,'reserving','ready'),
     'milano_reservation_transition_conflict'),
     'duplicate ready write never replays gateway or MDG reservation');
test(is_code(Milano_Canonical_Order_Claim::reservation_transition($intent,(int)$winner+99,'bound','reserving'),
     'milano_reservation_transition_conflict'),
     'foreign Woo order ID cannot reserve the same intent');
test(is_code(Milano_Canonical_Order_Claim::reservation_transition($intent,(int)$winner,'ready','bound'),
     'milano_reservation_transition_invalid'),
     'completed reservation cannot be reset to bound');
$other=claim_key(333);
test(is_array(Milano_Canonical_Order_Claim::claim($other,$quote,24,151,110000,4)),'new intent may independently claim');
test(is_code(Milano_Canonical_Order_Claim::attach_order($other,(int)$winner),'milano_order_bind_conflict'),'SQL unique Woo order index blocks cross-intent reuse');
$other_result=Milano_Canonical_Order_Claim::claim($other,$quote,24,151,110000,4);
test(is_array($other_result)&&$other_result['state']==='claimed'&&$other_result['wc_order_id']===null,'conflicted order id never mutates claim');
test(count_intent($wpdb,$other)===1,'independent purchase claim remains singular');
test((int)$wpdb->pdo->query('SELECT COUNT(*) FROM wp_milano_order_claims_v1')->fetchColumn()===3,'no duplicate/order/hold/ticket/payment records created by test');
echo "ALL $passes real isolated MySQL idempotent checkout tests passed.\n";
