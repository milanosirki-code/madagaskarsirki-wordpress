<?php
/** Reporting contracts use isolated local rows; mutation methods always throw. */
define('ABSPATH','/fixture/'); define('ARRAY_A','ARRAY_A');
$allowed=true;$nonce=true;$checks=0;
function absint($v){return abs((int)$v);}function current_user_can($c){return $GLOBALS['allowed'];}
function check_admin_referer($a){if(!$GLOBALS['nonce'])throw new RuntimeException('nonce');}
function wp_die($m,...$a){throw new RuntimeException($m);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function sanitize_key($v){return preg_replace('/[^a-z_]/','',$v);}function admin_url($p){return 'https://fixture/'.$p;}
function add_query_arg($a,$u){return $u.'?'.http_build_query($a);}function wp_nonce_url($u,$a){return $u.'&_wpnonce=fixture';}
function check($v,$m){$GLOBALS['checks']++;if(!$v)throw new RuntimeException($m);}
function row($id,$changes=array()) {return array_replace(array('id'=>$id,'title'=>'Fixture '.$id,'post_url'=>'https://instagram.com/p/test','status'=>'drawn','drawn_at'=>'2026-10-04 12:00:00','one_user_one_entry'=>0,'winner_count'=>2,'reserve_count'=>0,'comments'=>10,'participants'=>4,'valid_comments'=>8,'valid_users'=>3,'results'=>2,'winners'=>2,'reserves'=>0,'verified'=>0,'pending'=>2,'disqualified'=>0,'unknown_verification'=>0,'replacements'=>0,'replaced_slots'=>0,'historical_disqualified'=>0),$changes);}
class ReportingDB {
 public $prefix='wp_',$queries=0,$last_error='',$writes=0;public $rows,$history,$draws;
 function __construct(){
  $this->rows=array(row(1,array('status'=>'draft','drawn_at'=>null,'results'=>0,'winners'=>0,'pending'=>0,'comments'=>0,'participants'=>0,'valid_comments'=>0,'valid_users'=>0)),row(2),row(3,array('verified'=>2,'pending'=>0)),row(4,array('pending'=>1,'disqualified'=>1)),row(5,array('replacements'=>2,'replaced_slots'=>1,'historical_disqualified'=>2,'one_user_one_entry'=>1)),row(6,array('winners'=>1,'results'=>2,'reserves'=>1,'verified'=>2,'pending'=>0)),row(7,array('unknown_verification'=>1,'pending'=>0,'verified'=>1)));
  $base=array('campaign_id'=>5,'draw_id'=>9,'result_type'=>'winner','position_no'=>1,'old_comment_id'=>'old','old_username'=>'old','old_verification_status'=>'disqualified','old_verification_note'=>'Reason <script>','new_comment_id'=>'new','new_username'=>'new','actor_user_id'=>17,'actor_name'=>'Operator','campaign_title'=>'Fixture5','created_at'=>'2026-10-05 12:00:00');
  $this->history=array(array('id'=>2)+$base,array_replace(array('id'=>1)+$base,array('new_username'=>'previous','created_at'=>'2026-10-04 12:00:00')));
  $this->draws=array(array('id'=>9,'username'=>'=SUM(A1)','result_type'=>'winner','verification_status'=>'pending','drawn_at'=>'2026-10-05 12:00:00'));
 }
 function prepare($s,...$a){foreach($a as$v)$s=preg_replace('/%[ds]/',is_int($v)?(string)$v:"'".$v."'",$s,1);return $s;}
 function guard($s){$this->queries++;if(preg_match('/^\s*(INSERT|UPDATE|DELETE|CREATE|REPLACE|ALTER|DROP)/i',$s)){++$this->writes;throw new RuntimeException('domain write');}}
 function get_var($s){$this->guard($s);return str_starts_with($s,'SHOW')?'wp_mck_redraw_audit':12;}
 function get_results($s,$f){$this->guard($s);if(str_contains($s,'FROM wp_mck_campaigns c'))return $this->rows;if(str_contains($s,'FROM wp_mck_redraw_audit a'))return $this->history;if(str_contains($s,'FROM wp_mck_draws WHERE'))return $this->draws;throw new RuntimeException('Unexpected query '.$s);}
 function query($s){$this->guard($s);}function update(...$a){throw new RuntimeException('update');}function insert(...$a){throw new RuntimeException('insert');}
}
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-cekilis/includes/class-mck-reporting.php';
$wpdb=new ReportingDB;$before=serialize(array($wpdb->rows,$wpdb->history,$wpdb->draws));
$o=MCK_Reporting::overview();check($wpdb->queries===3,'Overview uses fixed three queries');check(count($o['campaigns'])===7,'Seven campaign fixtures');check($o['totals']['comments']===60,'Local imported comment totals');check($o['totals']['unique_participants']===12,'Cross-campaign distinct not summed');
$empty=MCK_Reporting::campaign(1);check($empty['display_state']==='Açık'&&$empty['eligible_entries']===0,'Empty undrawn campaign');check(!$empty['complete'],'No empty fake completion');
check(MCK_Reporting::campaign(2)['display_state']==='Doğrulama Bekliyor','Normal winners pending');check(MCK_Reporting::campaign(3)['complete'],'All verified complete');
check(MCK_Reporting::campaign(4)['disqualified']===1&&!MCK_Reporting::campaign(4)['complete'],'Disqualified excluded from completion');check(MCK_Reporting::campaign(5)['eligible_entries']===3,'Single-user entry rule');
check(MCK_Reporting::campaign(6)['missing_slots']===1&&!MCK_Reporting::campaign(6)['complete'],'Reserve cannot fill missing primary winner');check(!MCK_Reporting::campaign(7)['complete'],'Unknown verification cannot become complete');
foreach(array('pending'=>2,'complete'=>3,'replacement'=>5,'missing'=>6)as$f=>$id)check(MCK_Reporting::matches(MCK_Reporting::campaign($id),$f),'Filter '.$f);
check(!MCK_Reporting::matches(MCK_Reporting::campaign(2),'complete'),'Filter excludes pending');
$meta=MCK_Reporting::result_metadata(5);check($meta[9]['id']===2,'Latest of multiple replacements used');check(count(MCK_Reporting::audit(5))===2,'Full replacement chain retained');
ob_start();MCK_Reporting::render_audit(5);$html=ob_get_clean();check(str_contains($html,'old')&&str_contains($html,'previous')&&str_contains($html,'Operator'),'History identities and actor');check(!str_contains($html,'<script>'),'Audit note escaped');
$_GET=array('report_filter'=>'replacement');$q=$wpdb->queries;ob_start();MCK_Reporting::render_list();$html=ob_get_clean();check(str_contains($html,'Fixture 5')&&!str_contains($html,'Fixture 2'),'Filtered cards');check(str_contains($html,'Toplam katılım'),'Manager summary');check($wpdb->queries-$q===1,'List adds only one global audit join');
$q=$wpdb->queries;ob_start();MCK_Reporting::render_list();$again=ob_get_clean();check($again===$html&&$wpdb->queries===$q,'Reload same data no N+1 query');
$csv=MCK_Reporting::csv_rows(5);check(count($csv[0])===5&&$csv[0]===array('username','result_type','verification_state','selected_at','replacement_flag'),'Minimal five-field CSV contract');check($csv[1][0]==="'=SUM(A1)"&&$csv[1][4]==='1','Replacement CSV and formula protection');
foreach(array('=1','+1','-1','@name'," \t=1","\r\n@name")as$v)check(str_starts_with(MCK_Reporting::csv_cell($v),"'"),'Excel unsafe prefix escaped');check(MCK_Reporting::csv_cell('normal_user')==='normal_user','Normal value unchanged');
$allowed=false;try{MCK_Reporting::authorize_export(5);check(false,'Unauthorized export');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'yetkiniz'),'Capability rejects');}
$allowed=true;$nonce=false;try{MCK_Reporting::authorize_export(5);check(false,'Invalid nonce');}catch(RuntimeException $e){check($e->getMessage()==='nonce','Nonce rejects');}$nonce=true;
MCK_Reporting::authorize_export(5);check(true,'Authorized export');try{MCK_Reporting::authorize_export(999);check(false,'Unknown campaign');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'bulunamadı'),'Unknown campaign rejects');}
check($before===serialize(array($wpdb->rows,$wpdb->history,$wpdb->draws))&&$wpdb->writes===0,'All GET/render/export paths zero domain mutation');
check(MCK_Reporting::derive(row(8,array('status'=>'ready','drawn_at'=>null,'results'=>0,'winners'=>0,'pending'=>0)))['display_state']==='Açık','Imported ready is open without persisted status change');
$undrawn=MCK_Reporting::derive(row(8,array('status'=>'draft','drawn_at'=>null,'results'=>0,'winners'=>0,'pending'=>0)));
check($undrawn['display_state']==='Açık'&&$undrawn['eligible_entries']===8,'Imported but not drawn');
$stream=fopen('php://temp','w+');foreach($csv as$r)fputcsv($stream,$r,';','"','');rewind($stream);$bytes=stream_get_contents($stream);fclose($stream);check(str_contains($bytes,"'=SUM(A1)")&&!str_contains($bytes,'email'),'CSV bytes and privacy');
$property=new ReflectionProperty(MCK_Reporting::class,'overview');$property->setValue(null,null);$wpdb->rows=array();$none=MCK_Reporting::overview();check($none['totals']['campaigns']===0&&$none['campaigns']===array(),'Zero campaigns');
echo "PASS: {$checks} raffle reporting assertions; domain writes0.\n";
