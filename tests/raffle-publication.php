<?php
// Synthetic fixtures only; no WordPress connection, credentials or production writes.
define('ABSPATH', __DIR__.'/');
class TestStop extends RuntimeException {}
class WP_Error {}
$options=[]; $cap=true; $nonce=true; $hooks=[];
function add_action($name,$cb,$priority=10){$GLOBALS['hooks'][$name][$priority][]=$cb;}
function add_filter(...$a){} function add_shortcode(...$a){} function register_activation_hook(...$a){}
function current_user_can($cap){return $GLOBALS['cap'];}
function get_current_user_id(){return 7;}
function check_admin_referer($key){if(!$GLOBALS['nonce'])throw new TestStop('nonce rejected');}
function wp_die($message,...$a){throw new TestStop($message);}
function wp_safe_redirect($url){throw new TestStop('redirect:'.$url);}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function admin_url($p=''){return '/wp-admin/'.$p;}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=null){$old=$GLOBALS['options'][$key]??null;if($old===$value)return false;$GLOBALS['options'][$key]=$value;$GLOBALS['option_writes']++;return true;}
function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;}
function wp_cache_delete(...$a){}
function current_time($kind){return '2026-10-10 01:00:00';}
function wp_json_encode($v,...$a){return json_encode($v);}
function absint($v){return abs((int)$v);}
function wp_unslash($v){return $v;}
function sanitize_text_field($v){return trim(strip_tags($v));}
function sanitize_textarea_field($v){return trim(strip_tags($v));}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',$v));}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function home_url($p=''){return 'https://example.test'.$p;}
function mb_strtoupper($v){return strtoupper($v);}
function is_admin(){return false;}
function is_page($p){return $GLOBALS['on_results_page']??false;}
function get_query_var($k,$d=''){return $GLOBALS['query_vars'][$k]??$d;}
function nocache_headers(){$GLOBALS['nocache']=true;}
function mysql2date($format,$value,...$a){return $value;}
class FakeDb {
 public $prefix='wp_';public $campaign;public $rows=[];public $updates=0;public $replaceOnUpdate=false;public $startFails=false;public $commitFails=false;public $txOptions;
 function prepare($q,...$args){foreach($args as $arg){$q=preg_replace('/%[ds]/',is_int($arg)?(string)$arg:"'".$arg."'",$q,1);}return $q;}
 function get_row($q){
  if(strpos($q,'mck_campaigns')!==false)return clone $this->campaign;
  preg_match('/id\s*=\s*(\d+)/',$q,$id);preg_match('/campaign_id\s*=\s*(\d+)/',$q,$campaign);
  foreach($this->rows as $r)if($r->id==(int)($id[1]??0)&&(!isset($campaign[1])||$r->campaign_id==(int)$campaign[1]))return clone $r;
  return null;
 }
 function get_results($q){if(strpos($q,'mck_campaigns')!==false)return [$this->campaign];return array_values(array_map(fn($r)=>clone $r,array_filter($this->rows,fn($r)=>$r->result_type==='winner')));}
 function query($q){
  if($q==='START TRANSACTION'){if($this->startFails)return false;$this->txOptions=$GLOBALS['options'];}
  if($q==='COMMIT'&&$this->commitFails)return false;
  if($q==='ROLLBACK')$GLOBALS['options']=$this->txOptions;
  return 1;
 }
 function update($table,$data,$where,...$a){
  if($this->replaceOnUpdate){$this->rows[0]->comment_id='replacement-comment';$this->rows[0]->username='replacement-user';$this->rows[0]->verification_status='pending';$this->replaceOnUpdate=false;}
  foreach($this->rows as $r){$match=true;foreach($where as $k=>$v)if((string)$r->$k!==(string)$v)$match=false;if(!$match)continue;
   $changed=false;foreach($data as $k=>$v){if($r->$k!==$v)$changed=true;$r->$k=$v;}if($changed)$this->updates++;return (int)$changed;
  }return 0;
 }
}
$wpdb=new FakeDb;
require getenv('MCK_PLUGIN_PATH') ?: __DIR__.'/../wp-content/plugins/madagaskar-cekilis/madagaskar-cekilis.php';
$plugin=new Madagaskar_Cekilis_V2;
function invoke($name,...$args){global $plugin;return (new ReflectionMethod($plugin,$name))->invoke($plugin,...$args);}
function reset_fixture(){global $wpdb,$options,$cap,$nonce;
 $wpdb=new FakeDb;$options=[];$cap=true;$nonce=true;$GLOBALS['option_writes']=0;$GLOBALS['transients']=[];
 $wpdb->campaign=(object)['id'=>14,'status'=>'drawn','winner_count'=>2,'drawn_at'=>'2026-10-10 00:00:00','eligible_hash'=>'fixture','title'=>'Fixture','total_comments'=>98,'valid_entries'=>63];
 for($i=1;$i<=2;$i++)$wpdb->rows[]=(object)['id'=>$i,'campaign_id'=>14,'result_type'=>'winner','position_no'=>$i,'comment_id'=>'comment-'.$i,'username'=>'user'.$i,'drawn_at'=>'2026-10-10 00:00:00','score_hash'=>'score'.$i,'verification_status'=>'verified','verification_note'=>''];
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['campaign_id'=>14];$_GET=[];
}
$passed=0;
function check($name,$condition){global $passed;if(!$condition)throw new RuntimeException('FAIL: '.$name);echo 'PASS: '.$name."\n";$passed++;}
function action($method){global $plugin;try{$plugin->$method();}catch(TestStop $e){return $e->getMessage();}throw new RuntimeException('Action did not stop');}
reset_fixture();
check('drawn and verified does not auto-publish',!$plugin->public_results_published($wpdb->campaign));
$_POST['expected_results']=invoke('publication_fingerprint',$wpdb->campaign,$wpdb->rows);
check('explicit publish succeeds',strpos(action('publish_results'),'published')!==false);
check('published snapshot visible',$plugin->public_results_published($wpdb->campaign));
check('publication actor and time recorded',$options['mck2_publication_14']['published_by']===7 && !empty($options['mck2_publication_14']['published_at']));
action('publish_results');check('double publish is idempotent',$GLOBALS['option_writes']===1);
$html=$plugin->shortcode_results();check('published verified users visible',strpos($html,'@user1')!==false&&strpos($html,'@user2')!==false);
$wpdb->rows[0]->comment_id='new-occupant';check('replacement requires fresh publication',!$plugin->public_results_published($wpdb->campaign));
$html=$plugin->shortcode_results();check('stale publication hides names and ticket entitlement',strpos($html,'@user1')===false&&strpos($html,'Ücretsiz giriş hakkı:')===false);
check('stale publish form rejected',strpos(action('publish_results'),'error')!==false);
reset_fixture();$wpdb->rows[0]->verification_status='pending';$_POST['expected_results']=invoke('publication_fingerprint',$wpdb->campaign,$wpdb->rows);
check('pending winner blocks publish',strpos(action('publish_results'),'error')!==false&&$GLOBALS['option_writes']===0);
$wpdb->rows[0]->verification_status='disqualified';check('disqualified winner blocks publish',strpos(action('publish_results'),'error')!==false);
$wpdb->rows[0]->verification_status='verified';array_pop($wpdb->rows);check('missing winner slot blocks publish',strpos(action('publish_results'),'error')!==false);
reset_fixture();$wpdb->rows[1]->position_no=1;check('duplicate winner position blocks readiness',!invoke('publication_ready',$wpdb->campaign,$wpdb->rows));
reset_fixture();$wpdb->rows[1]->campaign_id=99;check('cross-campaign row blocks readiness',!invoke('publication_ready',$wpdb->campaign,$wpdb->rows));
reset_fixture();$_POST['expected_results']=invoke('publication_fingerprint',$wpdb->campaign,$wpdb->rows);$cap=false;
check('non-admin publication rejected',strpos(action('publish_results'),'yetkiniz')!==false&&$GLOBALS['option_writes']===0);
$cap=true;$nonce=false;check('bad publication nonce rejected',action('publish_results')==='nonce rejected');
$nonce=true;$_SERVER['REQUEST_METHOD']='GET';check('GET cannot publish',strpos(action('publish_results'),'POST')!==false);
reset_fixture();$_POST['expected_results']=invoke('publication_fingerprint',$wpdb->campaign,$wpdb->rows);$wpdb->startFails=true;
check('transaction start failure stops publish',strpos(action('publish_results'),'error')!==false&&$GLOBALS['option_writes']===0);
reset_fixture();$_POST['expected_results']=invoke('publication_fingerprint',$wpdb->campaign,$wpdb->rows);$wpdb->commitFails=true;
check('commit failure does not publish',strpos(action('publish_results'),'error')!==false&&!$plugin->public_results_published($wpdb->campaign));
reset_fixture();$_POST['expected_results']=invoke('publication_fingerprint',$wpdb->campaign,$wpdb->rows);action('publish_results');action('unpublish_results');
check('explicit unpublish hides result',!$plugin->public_results_published($wpdb->campaign));
reset_fixture();$wpdb->rows[0]->verification_status='pending';$_POST=['campaign_id'=>14,'draw_id'=>1,'expected_result'=>invoke('result_snapshot',$wpdb->rows[0]),'verification_status'=>'verified','verification_note'=>'checked'];
check('current occupant verification succeeds',strpos(action('verify_result'),'verified')!==false&&$wpdb->rows[0]->verification_status==='verified');
reset_fixture();$_POST=['campaign_id'=>99,'draw_id'=>1,'expected_result'=>invoke('result_snapshot',$wpdb->rows[0]),'verification_status'=>'verified'];
check('wrong campaign verification rejected',strpos(action('verify_result'),'error')!==false&&$wpdb->updates===0);
reset_fixture();$_POST=['campaign_id'=>14,'draw_id'=>1,'expected_result'=>invoke('result_snapshot',$wpdb->rows[0]),'verification_status'=>'disqualified'];$wpdb->rows[0]->comment_id='replaced-before-submit';
check('old occupant form rejected',strpos(action('verify_result'),'error')!==false&&$wpdb->updates===0);
reset_fixture();$_POST=['campaign_id'=>14,'draw_id'=>1,'expected_result'=>invoke('result_snapshot',$wpdb->rows[0]),'verification_status'=>'verified'];$wpdb->replaceOnUpdate=true;
check('replacement between read and update cannot be verified',strpos(action('verify_result'),'error')!==false&&$wpdb->rows[0]->verification_status==='pending'&&$wpdb->updates===0);
reset_fixture();$_GET=['campaign_id'=>14];
check('unpublished Story blocked',strpos(action('story_result'),'yayımlayın')!==false);
$GLOBALS['on_results_page']=true;$plugin->nocache_public_results();check('public results cache bypass set',defined('DONOTCACHEPAGE')&&DONOTCACHEPAGE&&!empty($GLOBALS['nocache']));
check('sitemap omits unpublished campaign',$plugin->wp_sitemap_verification_urls()===[]);
check('empty published sitemap page count',$plugin->wp_sitemap_verification_max_pages()===0);
echo "TOTAL PASS: $passed\n";
