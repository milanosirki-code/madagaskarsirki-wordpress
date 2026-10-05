<?php
/** Actual replacement service against an isolated transactional database fixture. */
define('ABSPATH', '/fixture/'); define('ARRAY_A', 'ARRAY_A');
class WP_Error { public function __construct(public $code,public $message){} public function get_error_message(){return $this->message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
$allowed=true; $hooks=[]; $nonce_ok=true;
function current_user_can($c){return $GLOBALS['allowed'];}
function get_current_user_id(){return 17;}
function current_time($f){return '2026-10-05 09:00:00';}
function add_action($tag,$fn,...$args){$GLOBALS['hooks'][$tag][]=$fn;}
function sanitize_key($v){return $v;}
function wp_unslash($v){return $v;}
function get_transient($v){return false;}
function get_current_screen(){return (object)['id'=>'toplevel_page_madagaskar-cekilis'];}
function admin_url($v){return 'https://fixture/wp-admin/'.$v;}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_html($v){return esc_attr($v);}
function esc_url($v){return esc_attr($v);}
function wp_nonce_field($v){echo '<input name="_wpnonce" value="fixture">';}
function wp_die($s,$code){throw new RuntimeException((string)$code);}
function check_admin_referer($action){if(!$GLOBALS['nonce_ok'])throw new RuntimeException('nonce');}
class FixtureDB {
 public $prefix='wp_', $last_error='', $insert_id=0, $writes=0, $fail_insert=false;
 public $campaigns=[], $draws=[], $comments=[], $audit=[], $snapshot=null;
 function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[ds]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".str_replace("'","''",$v)."'";},$sql);}
 function id($sql){preg_match('/(?:WHERE\s+id\s*=|campaign_id\s*=)\s*(\d+)/i',$sql,$m);return (int)($m[1]??0);}
 function get_var($sql){if(str_starts_with($sql,'SHOW TABLES')){preg_match("/LIKE '([^']+)'/",$sql,$m);return $m[1];}return $this->draws[$this->id($sql)]['campaign_id']??null;}
 function get_row($sql){if(str_starts_with($sql,'SHOW TABLE STATUS'))return (object)['Engine'=>'InnoDB'];$rows=str_contains($sql,'mck_campaigns')?$this->campaigns:$this->draws;return isset($rows[$this->id($sql)])?(object)$rows[$this->id($sql)]:null;}
 function get_results($sql,$format=null){
  $cid=$this->id($sql);
  if(str_contains($sql,'mck_redraw_audit')){$rows=$this->audit;}
  elseif(str_contains($sql,'mck_comments')){$rows=array_filter($this->comments,fn($r)=>$r['campaign_id']===$cid&&$r['is_valid']);}
  else{$rows=$this->draws;if(str_contains($sql,'disqualified'))$rows=array_filter($rows,fn($r)=>$r['verification_status']==='disqualified');}
  if($cid)$rows=array_filter($rows,fn($r)=>$r['campaign_id']===$cid);
  $rows=array_map(function($r){$r['campaign_title']='Fixture';return $r;},array_values($rows));
  return $format===ARRAY_A?$rows:array_map(fn($r)=>(object)$r,$rows);
 }
 function query($sql){
  if($sql==='START TRANSACTION')$this->snapshot=serialize([$this->campaigns,$this->draws,$this->comments,$this->audit]);
  elseif($sql==='ROLLBACK')[$this->campaigns,$this->draws,$this->comments,$this->audit]=unserialize($this->snapshot);
  elseif($sql!=='COMMIT')throw new RuntimeException('Unexpected SQL:'.$sql);
  return 1;
 }
 function insert($table,$data,...$args){if($this->fail_insert)return false;$this->writes++;$this->insert_id++;$this->audit[]=['id'=>$this->insert_id]+$data;return 1;}
 function update($table,$data,$where,...$args){$this->writes++;$which=str_contains($table,'campaigns')?'campaigns':'draws';$this->{$which}[$where['id']]=array_replace($this->{$which}[$where['id']],$data);return 1;}
}
require dirname(__DIR__,2).'/docs/code-snippets/madagaskar-raffle-replacement.php';
$assertions=0;
function check($ok,$message){$GLOBALS['assertions']++;if(!$ok)throw new RuntimeException($message);}
function fixture($n,$missing,$candidate_count=6){
 $db=new FixtureDB;
 $db->campaigns[1]=['id'=>1,'title'=>'Fixture','status'=>'drawn','one_user_one_win'=>0,'one_user_one_entry'=>0,'audit_seed'=>bin2hex(random_bytes(32)),'eligible_hash'=>'fixture','updated_at'=>'before'];
 for($i=1;$i<=$n;$i++)$db->draws[$i]=['id'=>$i,'campaign_id'=>1,'result_type'=>'winner','position_no'=>$i,'comment_id'=>'old'.$i,'username'=>'olduser'.$i,'comment_text'=>'fixture','verification_status'=>in_array($i,$missing)?'disqualified':'verified','verification_note'=>'original','drawn_at'=>'before'];
 for($i=1;$i<=$candidate_count;$i++)$db->comments[]=['id'=>$i,'campaign_id'=>1,'comment_id'=>'new'.$i,'username'=>'newuser'.$i,'comment_text'=>'fixture','normalized_text'=>'fixture','is_valid'=>1];
 // Extra comments from current winners must never give them another win.
 foreach($db->draws as $r)$db->comments[]=['id'=>100+$r['id'],'campaign_id'=>1,'comment_id'=>'extra'.$r['id'],'username'=>strtoupper($r['username']),'comment_text'=>'fixture','normalized_text'=>'fixture','is_valid'=>1];
 $db->comments[]=['id'=>999,'campaign_id'=>99,'comment_id'=>'foreign','username'=>'foreign','comment_text'=>'fixture','normalized_text'=>'fixture','is_valid'=>1];
 return $db;
}
function snapshot($db){return serialize([$db->campaigns,$db->draws,$db->comments,$db->audit]);}
foreach([[3,[2]],[5,[2,4]]] as [$n,$missing]){
 $wpdb=fixture($n,$missing);$original=$wpdb->draws;$expected=[];foreach($missing as $id)$expected[$id]=$original[$id]['comment_id'];
 $result=mckr_redraw_slots($missing,17,$expected);
 check(!is_wp_error($result)&&$result['count']===count($missing),'Only missing slots replaced');
 foreach($original as $id=>$row){if(!in_array($id,$missing))check($wpdb->draws[$id]===$row,'Verified winner unchanged');else check($wpdb->draws[$id]['verification_status']==='pending'&&str_starts_with($wpdb->draws[$id]['username'],'newuser'),'Replacement pending');}
 check(count($wpdb->audit)===count($missing),'One audit per replacement');
 foreach($wpdb->audit as $a)check($a['old_verification_status']==='disqualified'&&$a['actor_user_id']===17,'Disqualification history retained');
 $state=snapshot($wpdb);$again=mckr_redraw_slots($missing,17,$expected);check(is_wp_error($again)&&snapshot($wpdb)===$state,'Double-click leaves state unchanged');
 $reloaded=unserialize(serialize($wpdb));check($reloaded->draws===$wpdb->draws&&$reloaded->audit===$wpdb->audit,'Reload reads persisted state');
}
$wpdb=fixture(3,[2]);$first=mckr_redraw_slots([2]);$previous=$wpdb->draws[2]['username'];$wpdb->draws[2]['verification_status']='disqualified';
$wpdb->comments[]=['id'=>1000,'campaign_id'=>1,'comment_id'=>'new-comment-old-user','username'=>'@OLDUSER2','comment_text'=>'fixture','normalized_text'=>'fixture','is_valid'=>1];
$second=mckr_redraw_slots([2]);check(!is_wp_error($second),'Second manual disqualification replaces one slot');check(!in_array($wpdb->draws[2]['username'],[$previous,'olduser2','@OLDUSER2']),'All historical identities excluded');
$wpdb->draws[2]['verification_status']='disqualified';$state=snapshot($wpdb);$stale=mckr_redraw_slots([2],17,[2=>'old2']);check(is_wp_error($stale)&&snapshot($wpdb)===$state,'Stale occupant rejected even after another disqualification');
$wpdb=fixture(3,[2]);mckr_redraw_slots([2]);$wpdb->draws[2]['verification_status']='disqualified';$wpdb->comments=[['id'=>2000,'campaign_id'=>1,'comment_id'=>'another-historical-comment','username'=>'@OLDUSER2','comment_text'=>'fixture','normalized_text'=>'fixture','is_valid'=>1]];$state=snapshot($wpdb);$r=mckr_redraw_slots([2]);check(is_wp_error($r)&&snapshot($wpdb)===$state,'Historical-only candidate pool must be rejected without any update');
$wpdb=fixture(5,[2,4]);$state=snapshot($wpdb);$r=mckr_redraw_slots([2,4],17,[2=>'old2']);check(is_wp_error($r)&&snapshot($wpdb)===$state,'Incomplete occupant snapshot cannot partially write');
$wpdb=fixture(5,[2,4],1);$before=snapshot($wpdb);$r=mckr_redraw_slots([2,4]);check(is_wp_error($r)&&$r->get_error_message()==='Yeterli yeni uygun aday bulunamadı.','Insufficient pool explicit error');check(snapshot($wpdb)===$before,'Insufficient batch atomically rolls back winners/audit/timestamp');
$wpdb=fixture(3,[2]);$wpdb->fail_insert=true;$before=snapshot($wpdb);check(is_wp_error(mckr_redraw_slots([2]))&&snapshot($wpdb)===$before,'Audit failure rolls back');
$wpdb=fixture(3,[2]);$allowed=false;$before=snapshot($wpdb);check(is_wp_error(mckr_redraw_slots([2]))&&snapshot($wpdb)===$before,'Unauthorized service cannot write');
$handler=$hooks['admin_post_mckr_redraw'][0];try{$handler();check(false,'Unauthorized POST should reject');}catch(RuntimeException $e){check($e->getMessage()==='403','Unauthorized endpoint rejects');}
$allowed=true;$_SERVER['REQUEST_METHOD']='GET';try{$handler();check(false,'GET should reject');}catch(RuntimeException $e){check($e->getMessage()==='405','GET endpoint rejects');}
$_SERVER['REQUEST_METHOD']='POST';$nonce_ok=false;try{$handler();check(false,'Nonce should reject');}catch(RuntimeException $e){check($e->getMessage()==='nonce','Nonce validation required');}
$nonce_ok=true;check(empty($hooks['current_screen']),'No automatic GET initialization/redraw hook');
$_GET=['page'=>'madagaskar-cekilis','campaign_id'=>1];$before=snapshot($wpdb);ob_start();$hooks['admin_notices'][0]();$html=ob_get_clean();check(snapshot($wpdb)===$before&&$wpdb->writes===0,'Admin render never writes domain state');check(str_contains($html,'1 Kişi İçin Yeniden Çek')&&str_contains($html,'method="post"'),'Explicit manual POST button rendered');
$wpdb=fixture(3,[2]);mckr_redraw_slots([2]);$_GET=['page'=>'madagaskar-cekilis','campaign_id'=>1];$state=snapshot($wpdb);ob_start();mckr_render_replacement_controls();$html=ob_get_clean();check(str_contains($html,'(disqualified)')&&str_contains($html,'Yönetici #17')&&snapshot($wpdb)===$state,'Audit history renders without domain write');
echo "PASS: {$assertions} replacement assertions; no production data or Meta API used.\n";
