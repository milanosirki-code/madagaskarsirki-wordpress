<?php
/**
 * Pure staging fixture for per-order-line admission instance identity.
 * php integrations/milano/tests/tickera-issuance-ledger-contract.php <scenario>
 * NEVER touches WordPress, tickets, PayTR, live MySQL, QR or user data.
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/checkout-intent-protocol/milano-ticket-issuance-ledger.php';
function require_ledger($yes,$reason){if(!$yes){fwrite(STDERR,"FAIL {$reason}\n");exit(1);}}
class FakeTicketDB {
    public $rows=array(),$selected=array(),$updated=array(),$insert_count=0,$update_count=0;
    public $down=false;
    private function slot($order,$line,$unit){return $order.':'.$line.':'.$unit;}
    public function insert($table,$data,$formats){
        ++$this->insert_count;
        require_ledger(count($data)===count($formats),'SQL format mismatch');
        if($this->down)return false;
        $slot=$this->slot($data['order_id'],$data['order_item_id'],$data['unit_index']);
        if(isset($this->rows[$slot]))return false;
        $this->rows[$slot]=$data;return 1;
    }
    public function prepare($sql,...$args){
        if(strpos($sql,'SELECT ')!==false){
            require_ledger(strpos($sql,'unit_index=%d LIMIT 1')!==false,'unsafe select key');
            $this->selected=$args;return 'READ';
        }
        require_ledger(strpos($sql,'claim_token=%s')!==false,'missing worker CAS token');
        require_ledger(strpos($sql,'ticket_instance_id IS NULL')!==false,'overwrite admitted');
        $this->updated=$args;return 'WRITE';
    }
    public function get_row($query){
        if($this->down)return null;
        $key=$this->slot(...$this->selected);
        return isset($this->rows[$key])?(object)$this->rows[$key]:null;
    }
    public function query($query){
        ++$this->update_count;
        if($this->down)return false;
        list($ticket,$state,$time,$order,$line,$unit,$event,$session,$type,$expected,$token,$old)=$this->updated;
        $key=$this->slot($order,$line,$unit);
        if(!isset($this->rows[$key]))return 0;
        $r=&$this->rows[$key];
        if($r['claim_token']!==$token||$r['state']!==$old
           ||null!==$r['ticket_instance_id']
           ||$r['event_id']!==$event||$r['session_id']!==$session
           ||$r['ticket_type_id']!==$type||$r['expected_units']!==$expected)return 0;
        foreach($this->rows as $otherKey=>$other){
           if($otherKey!==$key && $other['ticket_instance_id']===$ticket)return false;
        }
        $r['ticket_instance_id']=$ticket;$r['state']=$state;$r['updated_at']=$time;
        return 1;
    }
}
$case=$argv[1]??'new';
$table='wp_dunya_milano_issuance_slots';$cls='MCPI_Ticket_Issuance_Ledger';
require_ledger(strpos($cls::ddl($table),'PRIMARY KEY (order_id, order_item_id, unit_index)')!==false,'slot not unique');
require_ledger(strpos($cls::ddl($table),'UNIQUE KEY ticket_instance_id')!==false,'ticket id reuse possible');
require_ledger($cls::ddl('bad-name')===null,'unsafe table accepted');
$id=array('order_id'=>500,'order_item_id'=>610,'unit_index'=>0,
 'event_id'=>72,'session_id'=>93,'ticket_type_id'=>501,
 'expected_units'=>4,'owner_brand'=>'milano','order_brand'=>'milano',
 'settlement_confirmed'=>true);
$db=new FakeTicketDB();$now=1781000000;
$expected='claimed';
switch($case){
 case 'new':case 'duplicate':case 'attach_once':case 'already_issued':
 case 'wrong_token':case 'ticket_id_reused':case 'family4':
 case 'crashed_worker':case 'identity_conflict':case 'stuck_after_issue':break;
 case 'other_brand':$id['owner_brand']='madagaskar';$expected='blocked';break;
 case 'order_brand_mismatch':$id['order_brand']='madagaskar';$expected='blocked';break;
 case 'unsettled':$id['settlement_confirmed']=false;$expected='blocked';break;
 case 'invalid_line':$id['order_item_id']=0;$expected='blocked';break;
 case 'bad_unit':$id['unit_index']=4;$expected='blocked';break;
 case 'negative_index':$id['unit_index']=-1;$expected='blocked';break;
 case 'large_family':$id['expected_units']=101;$expected='blocked';break;
 case 'bad_type':$id['ticket_type_id']=0;$expected='blocked';break;
 case 'bad_table':$table='unsafe-name';$expected='blocked';break;
 case 'db_unavailable':$db->down=true;$expected='unavailable';break;
 default:require_ledger(false,'unknown scenario');
}
$r=$cls::claim($db,$table,$id,$now);
require_ledger($expected===$r['status'],'first status '.$case.' '.$r['status']);
if('claimed'!==$expected){
    require_ledger(!isset($r['claim_token']),'denied worker got token');
    require_ledger(count($db->rows)===0,'denied created ticket ledger');
    echo "PASS {$case}\n";exit;
}
require_ledger(count($db->rows)===1 && $db->update_count===0,'claim created tickets');
if('duplicate'===$case){
    $second=$cls::claim($db,$table,$id,$now);
    require_ledger('processing'===$second['status']&&!isset($second['claim_token']),'duplicate worker granted');
}
if('attach_once'===$case||'already_issued'===$case){
    require_ledger($cls::attach($db,$table,$id,$r['claim_token'],9001,$now),'attach failed');
    require_ledger(!$cls::attach($db,$table,$id,$r['claim_token'],9002,$now),'issued ticket overwritten');
    if('already_issued'===$case){
        $again=$cls::claim($db,$table,$id,$now);
        require_ledger($again['status']==='already_issued'&&$again['ticket_instance_id']===9001,'duplicate issued another QR');
    }
}
if('wrong_token'===$case){
    require_ledger(!$cls::attach($db,$table,$id,str_repeat('0',32),9001,$now),'unauthorized worker completed');
    require_ledger($db->rows['500:610:0']['ticket_instance_id']===null,'bad token changed state');
}
if('crashed_worker'===$case){
    $db->rows['500:610:0']['updated_at']='2000-01-01 00:00:00';
    require_ledger($cls::claim($db,$table,$id,$now)['status']==='processing','stale slot stolen');
}
if('stuck_after_issue'===$case){
    $db->rows['500:610:0']['state']='claimed';
    $db->rows['500:610:0']['ticket_instance_id']=9001;
    require_ledger($cls::claim($db,$table,$id,$now)['status']==='reconcile_required','crash window not reconciled');
}
if('identity_conflict'===$case){
    $other=$id;$other['ticket_type_id']=999;
    require_ledger($cls::claim($db,$table,$other,$now)['status']==='identity_conflict','different ticket type allowed same unit');
}
if('family4'===$case){
    // One commercial 2+2 family line has four individual admission slots.
    for($i=1;$i<4;$i++){
        $slot=$id;$slot['unit_index']=$i;
        $next=$cls::claim($db,$table,$slot,$now);
        require_ledger($next['status']==='claimed','family admission slot missing');
        require_ledger($cls::attach($db,$table,$slot,$next['claim_token'],9001+$i,$now),'family attach failed');
    }
    require_ledger($cls::attach($db,$table,$id,$r['claim_token'],9001,$now),'family first attach');
    require_ledger(count($db->rows)===4,'family did not receive exactly four identities');
    foreach($db->rows as $row){require_ledger($row['state']==='issued','missing ticket for family');}
    require_ledger($cls::claim($db,$table,$id,$now)['status']==='already_issued','family duplicate bypassed');
}
if('ticket_id_reused'===$case){
    require_ledger($cls::attach($db,$table,$id,$r['claim_token'],9001,$now),'first attach');
    $other=$id;$other['unit_index']=1;
    $next=$cls::claim($db,$table,$other,$now);
    require_ledger($next['status']==='claimed','second guest failed claim');
    require_ledger(!$cls::attach($db,$table,$other,$next['claim_token'],9001,$now),'ticket reused across guests');
}
echo "PASS {$case}\n";
