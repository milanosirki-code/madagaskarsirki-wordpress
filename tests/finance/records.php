<?php
define('ABSPATH',__DIR__);
define('OBJECT_K','OBJECT_K'); define('ARRAY_A','ARRAY_A');
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
require __DIR__.'/../../wp-content/plugins/madagaskar-management-center/includes/class-mmc-finance-service.php';
class RedirectResult extends Exception {}
function absint($v){return abs((int)$v);}
function wp_unslash($v){return $v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower($v));}
function sanitize_text_field($v){return trim(strip_tags($v));}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}
function current_user_can($v){return $GLOBALS['can'];}
function check_admin_referer($a,$b=null){if(!$GLOBALS['nonce'])wp_die('nonce');}
function wp_die($v){throw new DomainException($v);}
function wp_json_encode($v){return json_encode($v);}
function current_time($v){return '2026-10-10 12:00:00';}
function wp_date($v){return $v==='Y-m-d'?'2026-10-10':'2026-10';}
function get_current_user_id(){return 9;}
function get_option($v,$default=null){return $v===MDG_V5_Finance_Records::ACCOUNTS?array('cash'=>array('name'=>'Test Kasa')):$default;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function admin_url($v){return $v;}
function wp_safe_redirect($v){throw new RedirectResult($v);}
class MDG_DB {static function table($v){return 'wp_mdg_'.$v;}}
class TestFinanceDb {
 public $prefix='wp_';public $rows=array();public $payments=array();public $starts=array();public $history=array();public $insert_id=0;public $snapshot;public $fail_history=false;public $lock=1;public $readonly=false;public $released=0;
 function prepare($sql,...$args){if(count($args)===1&&is_array($args[0]))$args=$args[0];return $sql.' /*args:'.base64_encode(serialize($args)).'*/';}
 function args($sql){return preg_match('/\/\*args:([^*]+)\*\//',$sql,$m)?unserialize(base64_decode($m[1])):array();}
 function get_row($sql,$mode=null){$a=$this->args($sql);
  if(strpos($sql,'SHOW TABLE STATUS')===0)return(object)array('Engine'=>'InnoDB');
  if(strpos($sql,'mdg_v5_settlements')!==false)return isset($this->starts[$a[0].':'.$a[1]])?(object)$this->starts[$a[0].':'.$a[1]]:null;
  if(strpos($sql,'mdg_v5_payments')!==false){foreach($this->payments as $p){if(strpos($sql,'request_key=')!==false&&$p['request_key']===$a[0])return(object)$p;if(strpos($sql,'WHERE id=')!==false&&$p['id']===$a[0]&&$p['record_type']===$a[1]&&$p['record_id']===$a[2])return(object)$p;}return null;}
  if(strpos($sql,'mdg_v5_expenses')!==false)return isset($this->rows[$a[0]])?(object)$this->rows[$a[0]]:null;
  return $mode===ARRAY_A?array():null;
 }
 function get_var($sql){$a=$this->args($sql);if(strpos($sql,'GET_LOCK')!==false)return $this->lock;if(strpos($sql,'RELEASE_LOCK')!==false){$this->released++;return 1;}
  if(strpos($sql,'SUM(amount)')!==false){$total=0;foreach($this->payments as $p)if($p['record_type']===$a[0]&&$p['record_id']===$a[1]&&$p['status']==='posted')$total+=$p['amount'];return $total;}
  if(strpos($sql,'wp_mdg_events')!==false)return $a[0]===53?53:null;return 0;
 }
 function get_results($sql,$mode=null){return array();}
 function query($sql){if($this->readonly)throw new Exception('Read wrote SQL: '.$sql);if($sql==='START TRANSACTION')$this->snapshot=serialize(array($this->rows,$this->payments,$this->starts,$this->history));if($sql==='ROLLBACK')list($this->rows,$this->payments,$this->starts,$this->history)=unserialize($this->snapshot);return 1;}
 function insert($table,$data){if($this->readonly)throw new Exception('Read inserted');if(strpos($table,'history')!==false){if($this->fail_history)return false;$this->history[]=$data;}elseif(strpos($table,'settlements')!==false){$key=$data['record_type'].':'.$data['record_id'];if(isset($this->starts[$key]))return false;$this->starts[$key]=$data;}else{$data['id']=count($this->payments)+1;$this->payments[$data['id']]=$data;}return 1;}
 function update($table,$data,$where){if($this->readonly)throw new Exception('Read updated');if(strpos($table,'settlements')!==false){$key=$where['record_type'].':'.$where['record_id'];$this->starts[$key]=array_merge($this->starts[$key],$data);}elseif(strpos($table,'payments')!==false)$this->payments[$where['id']]=array_merge($this->payments[$where['id']],$data);else $this->rows[$where['id']]=array_merge($this->rows[$where['id']],$data);return 1;}
}
$assertions=0;function check($condition,$message){global $assertions;$assertions++;if(!$condition)throw new Exception($message);}
function submit($op,$extra=array()){
 $_POST=array_merge(array('record_type'=>'expense','record_id'=>1,'operation'=>$op,'reason'=>'Test teyidi','request_key'=>'11111111-1111-4111-8111-111111111111'),$extra);$_REQUEST=$_POST;
 try{MDG_V5_Finance_Records::save();}catch(RedirectResult $e){return 'ok';}catch(DomainException $e){return $e->getMessage();}throw new Exception('No response');
}
$can=true;$nonce=true;$wpdb=new TestFinanceDb();
$wpdb->rows[1]=array('id'=>1,'amount'=>100,'scope'=>'program','event_id'=>53,'category'=>'Ulaşım','description'=>'Taşıma','payment_method'=>'Havale','document_no'=>'','expense_date'=>'2026-10-09','notes'=>'','updated_at'=>'2026-10-09 12:00:00');
$row=(object)$wpdb->rows[1];$original=serialize($row);
check(!MDG_V5_Finance_Records::state('expense',$row,true)['known'],'Method must not imply payment');
check(submit('payment',array('amount'=>'10','payment_date'=>'2026-10-10','account_id'=>'cash'))!=='ok','Unconfirmed legacy payment accepted');
check(submit('start',array('opening_amount'=>'20'))==='ok','Opening settlement failed');
check(submit('start',array('opening_amount'=>'20'))!=='ok','Opening duplicated');
check(submit('payment',array('amount'=>'30','payment_date'=>'2026-10-10','account_id'=>'cash'))==='ok','Partial payment failed');
$state=MDG_V5_Finance_Records::state('expense',$row,true);
check($state['settled']===50.0&&$state['remaining']===50.0,'Partial balance wrong');
check(serialize($row)===$original&&$wpdb->rows[1]['amount']===100,'Payment changed cost');
check(submit('payment',array('amount'=>'30','payment_date'=>'2026-10-10','account_id'=>'cash'))==='ok'&&count($wpdb->payments)===1,'Retry created duplicate');
check(submit('payment',array('amount'=>'30','payment_date'=>'2026-10-10','account_id'=>'other'))!=='ok','Retry account mismatch accepted');
check(submit('payment',array('amount'=>'50.01','payment_date'=>'2026-10-10','account_id'=>'cash','request_key'=>'22222222-2222-4222-8222-222222222222'))!=='ok','Overpayment accepted');
check(submit('payment',array('amount'=>'10','payment_date'=>'2026-02-30','account_id'=>'cash','request_key'=>'22222222-2222-4222-8222-222222222222'))!=='ok','Invalid calendar date accepted');
check(submit('payment',array('amount'=>'10','payment_date'=>'2026-10-11','account_id'=>'cash','request_key'=>'22222222-2222-4222-8222-222222222222'))!=='ok','Future payment accepted');
$edit=array('expected_hash'=>hash('sha256',json_encode($row)),'description'=>'Yeni','amount'=>'49','record_date'=>'2026-10-09','scope'=>'program','event_id'=>53);
check(submit('edit',$edit)!=='ok'&&$wpdb->rows[1]['amount']===100,'Edit below settlement accepted');
$edit['amount']='110';$edit['expected_hash']='stale';check(submit('edit',$edit)!=='ok','Stale edit accepted');
$edit['expected_hash']=hash('sha256',json_encode($row));$wpdb->fail_history=true;
check(submit('edit',$edit)!=='ok'&&$wpdb->rows[1]['amount']===100,'Edit without audit committed');
$wpdb->fail_history=false;check(submit('edit',$edit)==='ok'&&$wpdb->rows[1]['amount']===110.0,'Edit not saved');
check(submit('void',array('payment_id'=>1))==='ok','Void failed');
check(submit('void',array('payment_id'=>1))!=='ok','Double void accepted');
check(MDG_V5_Finance_Records::state('expense',(object)$wpdb->rows[1],true)['remaining']===90.0,'Void remaining incorrect');
check(count($wpdb->history)===4&&$wpdb->history[2]['before_data']!==$wpdb->history[2]['after_data'],'History incomplete');
check(submit('opening',array('opening_amount'=>'15'))==='ok'&&MDG_V5_Finance_Records::state('expense',(object)$wpdb->rows[1],true)['remaining']===95.0,'Opening correction failed');
check(submit('opening',array('opening_amount'=>'111'))!=='ok','Opening overpayment accepted');
$can=false;check(submit('start',array('opening_amount'=>'0'))!=='ok','Capability bypass');$can=true;$nonce=false;check(submit('start',array('opening_amount'=>'0'))!=='ok','Nonce bypass');$nonce=true;
$wpdb->lock=0;check(submit('edit',$edit)!=='ok','Concurrent lock bypass');$wpdb->lock=1;
$_REQUEST=array('summary_event'=>53,'program_id'=>99,'month'=>'2026-09');check(MDG_V5_Finance::context_event()===53,'Explicit event lost');check(strpos(MDG_V5_Finance::url(array('section'=>'expense')),'summary_event=53')!==false,'Tab context lost');
$cent=MDG_V5_Finance_Records::amounts(0.03,0.01,0.01);check($cent['remaining']===0.01,'Cent math failed');
$income=(object)array('id'=>7,'net_amount'=>80,'collection_status'=>'collected');check(MDG_V5_Finance_Records::state('income',$income,true)['settled']===80.0,'Historical collected income changed');
$wpdb->readonly=true;$summary=MMC_Finance_Service::summary(99);check($summary['total_revenue']===0.0&&$summary['manual_paid_audience']===0,'Summary default/readonly failed');
echo "Finance records: $assertions assertions passed\n";
