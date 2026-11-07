<?php
define('ABSPATH',__DIR__); define('ARRAY_A','ARRAY_A');
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
function absint($v){return abs((int)$v);}
function current_user_can($cap){return $GLOBALS['can'];}
function check_admin_referer($v){if(!$GLOBALS['nonce'])throw new DomainException('nonce');}
function sanitize_key($v){return $v;}
function esc_html($v){return $v;}
function wp_die($v){throw new DomainException($v);}
function current_time($v){return '2026-10-26 12:00:00';}
function get_current_user_id(){return 1;}
function wp_json_encode($v){return json_encode($v);}
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}
function add_option($k,$v,$unused='',$autoload=false){$GLOBALS['options'][$k]=$v;return true;}
function update_option($k,$v,$autoload=false){$GLOBALS['options'][$k]=$v;return true;}
function wp_cache_delete($k,$group){}
function admin_url($v){return $v;}
class Done extends Exception {}
function wp_safe_redirect($v){throw new Done($v);}
function check($v,$why){if(!$v)throw new Exception($why);}
class RepairDB {
 public $prefix='wp_'; public $data=array(); public $history=array(); public $snapshot; public $targets; public $fail_history=false;
 function prepare($sql,...$args){return $sql.' /*'.base64_encode(serialize($args)).'*/';}
 function args($sql){return preg_match('~/\*(.+)\*/$~',$sql,$m)?unserialize(base64_decode($m[1])):array();}
 function get_var($sql){$a=$this->args($sql);if(strpos($sql,'GET_LOCK')!==false||strpos($sql,'RELEASE_LOCK')!==false)return 1;if(strpos($sql,'SELECT public_uuid')===0)return $this->targets[$a[0]]??null;return 0;}
 function get_row($sql,$mode=null){$a=$this->args($sql);if(strpos($sql,'SHOW TABLE STATUS')===0)return (object)array('Engine'=>'InnoDB');$type=strpos($sql,'mdg_v5_incomes')!==false?'income':'expense';$id=$a[0]??113;$r=$this->data[$type][$id]??null;return $r===null?null:($mode===ARRAY_A?$r:(object)$r);}
 function query($sql){if($sql==='COMMIT')$this->snapshot=null;if($sql==='START TRANSACTION')$this->snapshot=serialize(array($this->data,$this->history,$GLOBALS['options']));if($sql==='ROLLBACK'&&$this->snapshot)list($this->data,$this->history,$GLOBALS['options'])=unserialize($this->snapshot);return 1;}
 function update($table,$data,$where){$type=strpos($table,'incomes')!==false?'income':'expense';$this->data[$type][$where['id']]=array_merge($this->data[$type][$where['id']],$data);return 1;}
 function insert($table,$data){if($this->fail_history)return false;$this->history[]=$data;return 1;}
}
function submit($op){$_POST=array('repair_operation'=>$op);try{MDG_V5_Finance_Identity::repair();}catch(Done $e){return 'ok';}catch(DomainException $e){return $e->getMessage();}}
$can=true;$nonce=true;$options=array();$wpdb=new RepairDB();
$dir=getenv('MDG_FINANCE_FIXTURE_DIR');
$options['mdg_finance_identity_manifest_20261026']=$dir?array('records'=>json_decode(file_get_contents($dir.'/plan.json'),true),'targets'=>json_decode(file_get_contents($dir.'/targets.json'),true),'retired_event_ids'=>array(17)):array('records'=>array(array('type'=>'income','id'=>1,'old'=>3,'new'=>16,'fingerprint'=>str_repeat('a',64))),'targets'=>array(16=>'11111111-2222-4333-8444-555555555555'),'retired_event_ids'=>array(17));
$m=new ReflectionMethod('MDG_V5_Finance_Identity','targets');$m->setAccessible(true);$wpdb->targets=$m->invoke(null);
$m=new ReflectionMethod('MDG_V5_Finance_Identity','plan');$m->setAccessible(true);$plan=$m->invoke(null);$keys=array();
foreach($plan as $r){$key=$r['type'].':'.$r['id'];check(!isset($keys[$key]),'Duplicate migration key');$keys[$key]=true;check(isset($wpdb->targets[$r['new']]),'Missing UUID');check(preg_match('/^[a-f0-9]{64}$/',$r['fingerprint'])===1,'Invalid fingerprint');}
check(MDG_V5_Finance_Identity::valid('16|'.$wpdb->targets[16]),'Canonical identity rejected');
check(!MDG_V5_Finance_Identity::valid('16'),'Bare numeric ID accepted');
check(!MDG_V5_Finance_Identity::valid('3|'.$wpdb->targets[16]),'Wrong namespace/UUID accepted');
check(!MDG_V5_Finance_Identity::valid(array()),'Non-scalar identity accepted');
$r=(object)array('id'=>'1','event_id'=>'3','description'=>'x','income_date'=>'2026-10-01','net_amount'=>'1.00');$hash=MDG_V5_Finance_Identity::fingerprint('income',$r);$r->net_amount='2.00';check($hash!==MDG_V5_Finance_Identity::fingerprint('income',$r),'Stale amount accepted');
$r=(object)array('id'=>999,'collection_status'=>'excluded_duplicate','net_amount'=>100);
check(MDG_V5_Finance_Records::collected($r)===0.0,'Duplicate income counted');
echo "Identity, stale-write, exclusion and migration-manifest contracts passed\n";
$dir=getenv('MDG_FINANCE_FIXTURE_DIR');if(!$dir)exit;
foreach(array('income'=>'incomes','expense'=>'expenses') as $type=>$file)foreach(json_decode(file_get_contents($dir.'/'.$file.'.json'),true) as $r)$wpdb->data[$type][$r['id']]=$r;
$original=serialize($wpdb->data);
check(submit('apply')==='ok','Private snapshot migration failed');
check(count($wpdb->history)===count($plan),'Missing audit entries');
$applied=serialize($wpdb->data);check(submit('apply')!=='ok'&&serialize($wpdb->data)===$applied,'Non-idempotent retry');
check(submit('rollback')==='ok'&&serialize($wpdb->data)===$original,'Rollback did not restore exact original rows');
$wpdb->fail_history=true;check(submit('apply')!=='ok'&&serialize($wpdb->data)===$original,'Audit failure committed');$wpdb->fail_history=false;
check(submit('apply')==='ok','Reapply failed');$wpdb->data['expense'][70]['amount']='1.00';$changed=serialize($wpdb->data);
check(submit('rollback')!=='ok'&&serialize($wpdb->data)===$changed,'Rollback overwrote concurrent edit');
$can=false;check(submit('apply')!=='ok','Capability bypass');$can=true;$nonce=false;check(submit('apply')!=='ok','Nonce bypass');
echo "Private live-snapshot apply/retry/rollback/audit-failure/concurrency/capability/nonce simulation passed\n";
