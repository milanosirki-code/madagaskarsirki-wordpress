<?php
// Synthetic accounting-only fixtures. No gateway/order APIs are defined or called.
$options=array();$admin=true;$commerce=true;$identity=true;$refund=true;$assertions=0;
class WP_Error { public $code;public $message;function __construct($c,$m){$this->code=$c;$this->message=$m;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function current_user_can($c){return $c==='manage_options'?$GLOBALS['admin']:$GLOBALS['commerce'];}
function get_current_user_id(){return 7;}
function get_option($k,$d=array()){return $GLOBALS['options'][$k]??$d;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function sanitize_textarea_field($s){return trim(strip_tags($s));}
function add_action($hook,$fn){}
function current_time($type){return '2030-01-03 10:00:00';}
class MDG_V5_Finance_Identity { static function valid($v){return $GLOBALS['identity']&&$v==='66|fixture-event';} }
class MDG_V5_Finance { static function is_ticket_refund($r){return $GLOBALS['refund'];} }
class RefundCashDB {
 public $prefix='test_';public $row;public $history=array();public $snapshot;public $engine='InnoDB';public $busy=false;public $tracked=0;public $fail_audit=false;public $fail_commit=false;public $fail_update=false;public $tracking_error=false;public $releases=0;
 function prepare($query,...$args){return json_encode(array($query,$args));}
 function get_var($sql){list($q,$a)=json_decode($sql,true);if(strpos($q,'SHOW TABLES')===0)return $a[0];if(strpos($q,'GET_LOCK')!==false)return $this->busy?0:1;if(strpos($q,'RELEASE_LOCK')!==false){$this->releases++;return 1;}if(strpos($q,'COUNT(*)')!==false)return $this->tracking_error?null:$this->tracked;throw new Exception('Unexpected read: '.$q);}
 function get_row($sql){list($q,$a)=json_decode($sql,true);if(strpos($q,'SHOW TABLE STATUS')===0)return(object)array('Engine'=>$this->engine);if(strpos($q,'test_mdg_v5_expenses')!==false)return $this->row?(object)$this->row:null;if(strpos($q,'test_mdg_v5_finance_history')!==false)return $this->history?(object)end($this->history):null;throw new Exception('Unexpected row read');}
 function query($q){if($q==='START TRANSACTION')$this->snapshot=array($this->row,$this->history);elseif($q==='ROLLBACK'){if($this->snapshot)list($this->row,$this->history)=$this->snapshot;$this->snapshot=null;}elseif($q==='COMMIT'){if($this->fail_commit)return false;$this->snapshot=null;}else throw new Exception('Unexpected write');return 1;}
 function update($table,$data,$where){if($this->fail_update)return false;if($table!=='test_mdg_v5_expenses'||(int)$where['id']!==501)throw new Exception('Wrong write target');if(array_diff(array_keys($data),array('amount','notes','updated_at')))throw new Exception('Disallowed field');$this->row=array_merge($this->row,$data);return 1;}
 function insert($table,$data){if($this->fail_audit)return false;if($table!=='test_mdg_v5_finance_history')throw new Exception('Wrong insert target');$this->history[]=$data;return 1;}
}
require __DIR__.'/../../wp-content/plugins/madagaskar-ai-abilities/modules/v5-finance.php';
function check($ok,$message){$GLOBALS['assertions']++;if(!$ok)throw new Exception($message);}
function fixture(){global $wpdb,$options,$admin,$commerce,$identity,$refund;$admin=$commerce=$identity=$refund=true;$wpdb=new RefundCashDB();$wpdb->row=array('id'=>'501','event_id'=>'66','scope'=>'program','category'=>'İade / WooCommerce','amount'=>'58.00','notes'=>'Previously confirmed','document_no'=>'','description'=>'Cancelled show manual return','updated_at'=>'2030-01-02 10:00:00');$options=array('mdg_v5_refund_cash_review_plans'=>array('fixture_plan'=>array('record_id'=>501,'event_id'=>66,'event_uuid'=>'fixture-event','expected_hash'=>mdg_ai_v5_refund_cash_hash($wpdb->row),'amount_cents'=>6100,'notes'=>'Newly confirmed accounting return','reason'=>'Operator confirmed return total from reconciliation evidence.','evidence_ref'=>'private reviewed evidence','confirmed_by'=>7,'confirmed_at'=>'2030-01-03')));return $wpdb->row;}
function call_review($op='apply',$key='fixture_plan'){return mdg_ai_v5_refund_cash_reconcile(array('plan_id'=>$key,'operation'=>$op));}
$original=fixture();$r=mdg_ai_v5_refund_cash_preview(array('plan_id'=>'fixture_plan','operation'=>'apply'));check($r['status']==='preview'&&$wpdb->row===$original&&!$wpdb->history,'Preview must never mutate');
$r=call_review();check($r['status']==='applied'&&$r['gateway_refund_created']===false,'Explicit accounting-only result');check($wpdb->row['amount']==='61.00'&&count($wpdb->history)===1,'Exactly one amount change and audit');check($wpdb->row['event_id']==='66'&&$wpdb->row['description']===$original['description'],'Identity and source fields preserved');$after=$wpdb->row;
$r=call_review();check($r['status']==='already_applied'&&count($wpdb->history)===1&&$wpdb->row===$after,'Retry is idempotent');
$r=call_review('rollback');check($r['status']==='rolled_back'&&$wpdb->row===$original&&count($wpdb->history)===2,'Exact before image rollback and separate audit');
$r=call_review('rollback');check($r['status']==='already_rolled_back'&&count($wpdb->history)===2,'Rollback retry is idempotent');check(is_wp_error(call_review()),'A rolled-back plan cannot silently reapply');
fixture();$wpdb->row['notes']='Concurrent change';check(is_wp_error(call_review())&&!$wpdb->history,'Stale plan refuses edits');
fixture();call_review();$wpdb->row['description']='Later source correction';$changed=$wpdb->row;check(is_wp_error(call_review('rollback'))&&$wpdb->row===$changed,'Rollback preserves later edits');check(is_wp_error(call_review()),'Apply retry also rejects later edits');
fixture();$admin=false;check(is_wp_error(call_review())&&!$wpdb->history,'WooCommerce capability alone is insufficient');fixture();$commerce=false;check(is_wp_error(call_review())&&!$wpdb->history,'Administrator capability alone is insufficient');
fixture();$identity=false;check(is_wp_error(call_review())&&!$wpdb->history,'Wrong UUID is rejected');
fixture();$wpdb->tracked=1;check(is_wp_error(call_review())&&!$wpdb->history,'Tracked payments/settlements need source reconciliation');
fixture();$wpdb->tracking_error=true;check(is_wp_error(call_review())&&!$wpdb->history,'Tracking query failure must fail closed');
fixture();$wpdb->engine='MyISAM';check(is_wp_error(call_review())&&!$wpdb->history,'Nontransactional table rejected');
fixture();$wpdb->busy=true;check(is_wp_error(call_review())&&!$wpdb->history,'Concurrent owner lock rejected');
foreach(array('fail_audit','fail_commit','fail_update') as $failure){$original=fixture();$wpdb->$failure=true;check(is_wp_error(call_review())&&$wpdb->row===$original&&!$wpdb->history,'Failure rolls back atomically: '.$failure);check($wpdb->releases===1,'Lock always released');}
foreach(array('category'=>'Travel','scope'=>'general','event_id'=>'99','document_no'=>'woo-source') as $field=>$value){fixture();$wpdb->row[$field]=$value;check(is_wp_error(call_review())&&!$wpdb->history,'Protected/wrong source rejected: '.$field);}
fixture();check(is_wp_error(call_review('apply','unknown_plan'))&&!$wpdb->history,'Missing private plan rejected');check(is_wp_error(call_review('payment')),'Payment operations cannot be requested');
fixture();$options['mdg_v5_refund_cash_review_plans']['fixture_plan']['amount_cents']=-1;check(is_wp_error(call_review())&&!$wpdb->history,'Invalid planned cents rejected');
fixture();$options['mdg_v5_refund_cash_review_plans']['fixture_plan']['confirmed_by']=8;check(is_wp_error(call_review())&&!$wpdb->history,'Plan belongs to confirmed operator');
fixture();$options['mdg_v5_refund_cash_review_plans']['fixture_plan']['evidence_ref']='';check(is_wp_error(call_review())&&!$wpdb->history,'Evidence reference required');
fixture();call_review();$wpdb->fail_audit=true;$after=$wpdb->row;check(is_wp_error(call_review('rollback'))&&$wpdb->row===$after&&count($wpdb->history)===1,'Rollback audit failure also restores transaction');
echo 'Refund cash contract: '.$assertions." assertions passed\n";
