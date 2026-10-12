<?php
/**
 * Pure in-memory atomic key fixture: NO WordPress, PayTR or SQL writes.
 * php integrations/milano/tests/paytr-order-settlement-lock-contract.php <case>
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/checkout-intent-protocol/milano-paytr-callback-preflight.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-paytr-order-settlement-lock.php';
function ok_order_lock($yes,$reason){
    if(!$yes){fwrite(STDERR,"FAIL ".$reason."\n");exit(1);}
}
class FakeOrderLockDB {
    public $rows=array(),$insert_calls=0,$write_calls=0,$broken=false,$prepared=array(),$collision=false;
    public function insert($table,$data,$formats){
        ++$this->insert_calls;
        ok_order_lock(count($data)===count($formats),'insert format count');
        if($this->broken || isset($this->rows[$data['order_id']]))return false;
        foreach($this->rows as $row){if($row['merchant_oid']===$data['merchant_oid']){ $this->collision=true; return false;}}
        $this->rows[$data['order_id']]=$data;return 1;
    }
    public function prepare($sql,...$args){
        $this->prepared=$args;
        if(strpos($sql,'SELECT ')!==false){
            ok_order_lock(strpos($sql,'WHERE order_id=%d LIMIT 1')!==false,'unscoped read');
            return 'SELECT';
        }
        ok_order_lock(strpos($sql,'worker_token=%s AND state=%s')!==false,'no CAS');
        return 'UPDATE';
    }
    public function get_row($query){
        if($this->broken)return null;
        $row=$this->rows[$this->prepared[0]]??null;
        return $row?(object)$row:null;
    }
    public function query($sql){
        ++$this->write_calls;
        if($this->broken)return false;
        list($state,$utc,$order_id,$oid,$token,$previous)=$this->prepared;
        if(!isset($this->rows[$order_id]))return 0;
        $row=&$this->rows[$order_id];
        if($row['merchant_oid']!==$oid||$row['worker_token']!==$token
           ||$row['state']!==$previous)return 0;
        $row['state']=$state;$row['changed_utc']=$utc;
        return 1;
    }
}
$case=$argv[1]??'first';
$cls='MCPI_PayTR_Order_Settlement_Lock';
$key=str_repeat('synthetic-server-only-key-',3);$salt='test-salt';
$oid='1781000000PAYTRWOO42';$table='wp_milano_paytr_orders';$now=1781000000;
$order=array(
 'id'=>42,'source_brand'=>'milano','payment_method'=>'paytr_payment_gateway',
 'currency'=>'TRY','status'=>'pending','total_cents'=>49000,
 'paytr_pending_order_id'=>$oid,
 'attempts'=>array(array('merchant_oid'=>$oid,'status'=>'success','amount'=>'49000','currency'=>'TRY'))
);
$make_notice=function($oid,$status='success',$amount='49000')use($key,$salt){
  return array('merchant_oid'=>$oid,'status'=>$status,
    'total_amount'=>$amount,'payment_amount'=>'49000','currency'=>'TL',
    'hash'=>base64_encode(hash_hmac('sha256',$oid.$salt.$status.$amount,$key,true)));
};
$notice=$make_notice($oid);
$db=new FakeOrderLockDB();
ok_order_lock(strpos($cls::ddl($table),'PRIMARY KEY (order_id)')!==false,'missing per-order PK');
ok_order_lock(strpos($cls::ddl($table),'UNIQUE KEY merchant_oid')!==false,'missing secondary unique oid');
ok_order_lock($cls::ddl('unsafe-name')===null,'unsafe SQL table');
$expected='claimed';
switch($case){
    case 'first':case 'parallel_same':case 'second_oid':case 'final_once':
    case 'already_final':case 'crash_reconcile':case 'payload_conflict':
    case 'wrong_worker':case 'cross_order_same_oid':break;
    case 'bad_hash':$notice['hash']=str_repeat('A',43).'=';$expected='rejected';break;
    case 'failed_callback':$notice=$make_notice($oid,'failed','0');$expected='failure_not_claimed';break;
    case 'wrong_brand':$order['source_brand']='madagaskar';$expected='blocked';break;
    case 'wrong_order_brand':$order['source_brand']='';$expected='blocked';break;
    case 'wrong_order':$order['id']=43;$expected='blocked';break;
    case 'missing_pending_id':unset($order['paytr_pending_order_id']);$expected='blocked';break;
    case 'already_paid':$order['status']='completed';$expected='already_final';break;
    case 'refunded':$order['status']='refunded';$expected='already_final';break;
    case 'underpaid':
      $notice=$make_notice($oid,'success','48000');$expected='blocked';break;
    case 'installments':
      $notice=$make_notice($oid,'success','52000');$expected='manual_review';break;
    case 'not_authorized':
      $order['attempts'][0]['status']='request_error';$expected='blocked';break;
    case 'bad_table':$table='unsafe-table';$expected='unavailable';break;
    case 'bad_db':$db=new stdClass();$expected='unavailable';break;
    case 'db_broken':$db->broken=true;$expected='unavailable';break;
    default:ok_order_lock(false,'unknown scenario '.$case);
}
$res=$cls::claim($db,$table,$notice,$order,$key,$salt,$now);
ok_order_lock($res['status']===$expected, $case.' expected '.$expected.' got '.$res['status']);
if($expected!=='claimed'){
    ok_order_lock(!isset($res['worker_token']),'denied callback received worker token');
    ok_order_lock(count($db->rows??array())===0,'invalid callback inserted order lock');
    echo "PASS {$case}\n";exit;
}
ok_order_lock(count($db->rows)===1 && $db->write_calls===0,'lock had side effects');
if($case==='parallel_same'){
    $repeat=$cls::claim($db,$table,$notice,$order,$key,$salt,$now);
    ok_order_lock($repeat['status']==='processing'&&!isset($repeat['worker_token']),'same callback parallel worker admitted');
}
if($case==='second_oid'){
    // Both OIDs have distinct valid PayTR HMAC and successful token attempts,
    // and each worker has a fresh snapshot of its corresponding pending OID.
    $other='1781000001PAYTRWOO42';
    $order2=$order;$order2['paytr_pending_order_id']=$other;
    $order2['attempts'][]=array('merchant_oid'=>$other,'status'=>'success','amount'=>'49000','currency'=>'TRY');
    $notice2=$make_notice($other);
    $blocked=$cls::claim($db,$table,$notice2,$order2,$key,$salt,$now);
    ok_order_lock($blocked['status']==='different_payment_attempt','second payment OID granted issuance');
    ok_order_lock(count($db->rows)===1,'new settlement for same order');
}
if($case==='final_once'||$case==='already_final'){
    ok_order_lock($cls::mark_final($db,$table,42,$oid,$res['worker_token'],$now),'final CAS failed');
    ok_order_lock(!$cls::mark_final($db,$table,42,$oid,$res['worker_token'],$now),'double final allowed');
    if($case==='already_final'){
        $again=$cls::claim($db,$table,$notice,$order,$key,$salt,$now);
        ok_order_lock($again['status']==='already_final','final reprocessed');
    }
}
if($case==='wrong_worker'){
    ok_order_lock(!$cls::mark_final($db,$table,42,$oid,str_repeat('0',32),$now),'attacker token finalized');
    ok_order_lock(!$cls::mark_final($db,$table,43,$oid,$res['worker_token'],$now),'wrong order finalized');
    ok_order_lock(!$cls::mark_final($db,$table,42,'1781000001PAYTRWOO42',$res['worker_token'],$now),'wrong OID finalized');
}
if($case==='crash_reconcile'){
    $db->rows[42]['changed_utc']='2000-01-01 00:00:00';
    $again=$cls::claim($db,$table,$notice,$order,$key,$salt,$now);
    ok_order_lock($again['status']==='processing','stale claim stolen automatically');
    $db->rows[42]['state']='unknown';
    $again=$cls::claim($db,$table,$notice,$order,$key,$salt,$now);
    ok_order_lock($again['status']==='reconcile_required','corrupted state silently reset');
}
if($case==='payload_conflict'){
    $db->rows[42]['signed_payload_hash']=str_repeat('0',64);
    $again=$cls::claim($db,$table,$notice,$order,$key,$salt,$now);
    ok_order_lock($again['status']==='payload_conflict','changed signed payload accepted');
}
if($case==='cross_order_same_oid'){
    // InnoDB UNIQUE on merchant_oid protects a bad caller issuing the same OID
    // for two orders even when the order ID and OID are assembled incorrectly.
    $bad=$db->rows[42];$bad['order_id']=43;
    ok_order_lock($db->insert($table,$bad,array_fill(0,8,'%s'))===false,'OID reused for another order');
}
echo "PASS {$case}\n";
