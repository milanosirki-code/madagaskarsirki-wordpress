<?php
/** Staging-only registry tests: NO Woo/PayTR/WordPress/live tables. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/checkout-intent-protocol/milano-paytr-callback-preflight.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-paytr-settlement-claim.php';
function demand_claim($yes,$why){if(!$yes){fwrite(STDERR,"FAIL: {$why}\n");exit(1);}}
class FakeSettlementDB {
    public $rows=array(),$args=array(),$inserts=0,$writes=0,$broken=false;
    public function insert($table,$data,$formats){
        $this->inserts++;
        demand_claim(count($data)===count($formats),'SQL placeholders');
        if($this->broken||isset($this->rows[$data['merchant_oid']]))return false;
        $this->rows[$data['merchant_oid']]=$data;
        return 1;
    }
    public function prepare($sql,...$args){
        $this->args=$args;
        if(strpos($sql,'SELECT ')!==false){
            demand_claim(strpos($sql,'WHERE merchant_oid=%s LIMIT 1')!==false,'unsafe lookup');
            return 'SELECT';
        }
        demand_claim(strpos($sql,'claim_token=%s AND state=%s')!==false,'unsafe finish');
        return 'UPDATE';
    }
    public function get_row($sql){
        if($this->broken)return null;
        $r=$this->rows[$this->args[0]]??null;
        return $r?(object)$r:null;
    }
    public function query($sql){
        $this->writes++;
        if($this->broken)return false;
        list($final,$updated,$oid,$token,$old)=$this->args;
        if(!isset($this->rows[$oid]) || $this->rows[$oid]['claim_token']!==$token
            || $this->rows[$oid]['state']!==$old)return 0;
        $this->rows[$oid]['state']=$final;
        return 1;
    }
}
$case=$argv[1]??'first';
$key=str_repeat('fixture-secret-placeholder-',3);$salt='fixture-salt';
$oid='1781000000PAYTRWOO42';
$notice=array(
 'merchant_oid'=>$oid,'status'=>'success','total_amount'=>'49000',
 'payment_amount'=>'49000','currency'=>'TL');
$sign=function(&$n)use($key,$salt){
    $n['hash']=base64_encode(hash_hmac('sha256',$n['merchant_oid'].$salt.$n['status'].$n['total_amount'],$key,true));
};
$sign($notice);
$order=array(
 'id'=>42,'source_brand'=>'milano','payment_method'=>'paytr_payment_gateway',
 'currency'=>'TRY','status'=>'pending','total_cents'=>49000,
 'paytr_pending_order_id'=>$oid,
 'attempts'=>array(array('merchant_oid'=>$oid,'status'=>'success','amount'=>'49000','currency'=>'TRY'))
);
$db=new FakeSettlementDB();
$table='wp_milano_paytr_settlements';
$now=1781000000;$class='MCPI_PayTR_Settlement_Claim';
demand_claim(strpos($class::ddl($table),'PRIMARY KEY (merchant_oid)')!==false,'unique merchant oid required');
demand_claim($class::ddl('bad-name')===null,'bad table accepted');
$expected='claimed';
if($case==='tampered_signature'){$notice['hash']=str_repeat('A',43).'=';$expected='rejected';}
if($case==='wrong_brand'){$order['source_brand']='madagaskar';$expected='blocked';}
if($case==='installment_review'){$notice['total_amount']='50000';$sign($notice);$expected='manual_review';}
if($case==='already_paid'){$order['status']='completed';$expected='already_final';}
if($case==='bad_db'){$db=new stdClass();$expected='unavailable';}
if($case==='db_unavailable'){$db->broken=true;$expected='unavailable';}
$r=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
demand_claim($r['status']===$expected, "{$case} got ".$r['status']);
if($expected!=='claimed'){
    demand_claim(!isset($r['claim_token']),'blocked result exposed token');
    demand_claim(count($db->rows??array())===0,'invalid notice created claim');
    echo "PASS {$case}\n";exit;
}
demand_claim(count($db->rows)===1 && $db->writes===0,'first claim had side effects');
if($case==='first'){$expected='claimed';}
elseif($case==='repeat'){
    $second=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
    demand_claim($second['status']==='processing'&&!isset($second['claim_token']),'double worker admitted');
    demand_claim(count($db->rows)===1,'duplicate row');
}
elseif($case==='different_signed_total'){
    $notice['total_amount']='49001';$sign($notice);
    // amount difference can legitimately be installments, so preload a
    // conflicting signed payload only in mock storage.
    $db->rows[$oid]['payload_hash']=str_repeat('0',64);
    $second=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
    demand_claim($second['status']==='manual_review','installment must await reconciliation');
}
elseif($case==='wrong_token'){
    demand_claim(!$class::finish($db,$table,$oid,str_repeat('0',32),$now),'unauthorized final');
    demand_claim($db->rows[$oid]['state']==='claimed','state changed by wrong token');
}
elseif($case==='finish_once'||$case==='repeat_final'){
    demand_claim($class::finish($db,$table,$oid,$r['claim_token'],$now),'first final failed');
    demand_claim(!$class::finish($db,$table,$oid,$r['claim_token'],$now),'final repeated');
    if($case==='repeat_final'){
        $again=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
        demand_claim($again['status']==='already_final','final callback reprocessed');
    }
}
elseif($case==='stale_claim'){
    $db->rows[$oid]['updated_at']='2010-01-01 00:00:00';
    $again=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
    demand_claim($again['status']==='processing','stale claim stolen');
}
elseif($case==='corrupt_state'){
    $db->rows[$oid]['state']='unknown';
    $again=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
    demand_claim($again['status']==='reconcile_required','invalid state not quarantined');
}
elseif($case==='different_order_record'){
    $db->rows[$oid]['order_id']=43;
    $again=$class::claim($db,$table,$notice,$order,$key,$salt,$now);
    demand_claim($again['status']==='conflict','OID bound to wrong order');
}
elseif($case==='failure_notification'){
    // A separate signed failure attempt (new OID) must be recorded only once.
    $failed=$notice;$failed['status']='failed';$failed['total_amount']='0';$sign($failed);
    $db2=new FakeSettlementDB();
    $f=$class::claim($db2,$table,$failed,$order,$key,$salt,$now);
    demand_claim($f['status']==='claimed','failed callback claim missing');
    demand_claim($class::claim($db2,$table,$failed,$order,$key,$salt,$now)['status']==='processing','failed callback duplicate');
}
elseif($case!=='first'){demand_claim(false,'unknown case');}
echo "PASS {$case}\n";
