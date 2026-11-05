<?php
namespace Madagaskar\WebAssistant { class Service { public static function catalogue() { return array('offers'=>array(array('city'=>'İzmir','district'=>'Konak'))); } } }
namespace {
define('ABSPATH',__DIR__);
class WP_REST_Response { public $data;public $status;function __construct($d,$s){$this->data=$d;$this->status=$s;}function header($k,$v){} }
class Request { public $data;public $body;function __construct($data=array('text'=>'İzmir iki yetişkin üç çocuk')){$this->data=$data;$this->body=json_encode($data);}function get_body(){return $this->body;}function get_json_params(){return $this->data;} }
function add_action(){}function home_url($p){return $GLOBALS['origin'].$p;}function current_user_can($c){return $GLOBALS['admin']&&$c==='manage_options';}
function get_current_user_id(){return 1;}function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}function update_option($k,$v){$GLOBALS['options'][$k]=$v;}
function add_option($k,$v){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}function delete_option($k){unset($GLOBALS['options'][$k]);}
function get_transient($k){return $GLOBALS['cooldown'];}function set_transient($k,$v,$t){$GLOBALS['cooldown']=$v;}function wp_json_encode($v){return json_encode($v);}
function is_wp_error($v){return $v instanceof \Exception;}function wp_remote_retrieve_response_code($v){return $v['status'];}function wp_remote_retrieve_body($v){return $v['body'];}
function wp_remote_request($url,$args){$GLOBALS['calls'][]=array($url,$args);return $GLOBALS['upstream'];}
function register_rest_route($ns,$r,$a){$GLOBALS['routes'][]=$a;}
require __DIR__.'/../wp-content/plugins/mdg-gemini-intake/mdg-gemini-intake.php';
use Madagaskar\GeminiIntake\Intake;
$checks=0;function ok($v,$label){global $checks;$checks++;if(!$v)throw new \Exception($label);}
function proposal(){return array('city_key'=>'İzmir / Konak','adults'=>2,'children'=>3,'infants'=>0);}
function reset_case(){
    $GLOBALS['origin']=Intake::ORIGIN;$GLOBALS['admin']=true;$GLOBALS['calls']=array();$GLOBALS['cooldown']=false;$GLOBALS['routes']=array();$GLOBALS['options']=array('connectors_ai_google_api_key'=>'fake-secret');
    $GLOBALS['upstream']=array('status'=>200,'body'=>json_encode(array('candidates'=>array(array('content'=>array('parts'=>array(array('text'=>json_encode(proposal())))))))));
}
function run_case($req=null){$r=Intake::parse($req??new Request());ok(strpos(json_encode($r),'fake-secret')===false,'no leaked key');return $r;}
reset_case();Intake::routes();ok(count($GLOBALS['routes'])===1,'staging route');$r=run_case();ok($r->status===200&&$r->data['proposal']===proposal(),'valid structured proposal');ok($r->data['requires_confirmation']&&!$r->data['incomplete'],'confirmation required');ok(count($GLOBALS['calls'])===1,'one request');
ok(strpos($GLOBALS['calls'][0][0],'fake-secret')===false,'no key in URL');ok($GLOBALS['calls'][0][1]['redirection']===0,'no redirects');ok(!isset($GLOBALS['options'][Intake::LOCK]),'lock released');
reset_case();$GLOBALS['origin']='https://madagaskarsirki.com';Intake::routes();ok(!$GLOBALS['routes'],'no production route');ok(run_case()->status===403&&!$GLOBALS['calls'],'production request rejected');
reset_case();$GLOBALS['admin']=false;ok(run_case()->status===403&&!$GLOBALS['calls'],'non-admin rejected');
reset_case();ok(run_case(new Request(array('text'=>'hello','price'=>1)))->status===400&&!$GLOBALS['calls'],'extra fields rejected');
reset_case();$req=new Request();$req->body=str_repeat('x',3001);ok(run_case($req)->status===400,'size limit');
reset_case();$GLOBALS['cooldown']=true;ok(run_case()->status===429&&!$GLOBALS['calls'],'rate limit');
reset_case();$GLOBALS['options'][Intake::LOCK]=1;ok(run_case()->status===429&&!$GLOBALS['calls'],'atomic lock');
reset_case();$GLOBALS['options']['mdg_gemini_intake_budget']=array('day'=>gmdate('Y-m-d'),'used'=>50);ok(run_case()->status===429&&!$GLOBALS['calls'],'daily cost cap');
reset_case();$GLOBALS['options']['connectors_ai_google_api_key']='';ok(run_case()->status===503&&!$GLOBALS['calls'],'missing key');
reset_case();$GLOBALS['upstream']=new \Exception('fake-secret');ok(run_case()->status===502,'network error safe');ok(!isset($GLOBALS['options'][Intake::LOCK]),'lock on error released');
reset_case();$GLOBALS['upstream']=array('status'=>429,'body'=>'fake-secret');ok(run_case()->status===502,'upstream error safe');
foreach(array(array('adults'=>0),array('adults'=>2.0),array('children'=>21),array('children'=>'3'),array('city_key'=>'İstanbul / Fake'),array('price'=>1)) as $patch){$p=array_merge(proposal(),$patch);try{Intake::validate($p,array('İzmir / Konak'));ok(false,'invalid output accepted');}catch(\RuntimeException $e){ok(true,'invalid output rejected');}}
$p=proposal();$p['children']=null;ok(Intake::validate($p,array('İzmir / Konak'))['incomplete'],'missing counts remain unknown');
reset_case();$GLOBALS['upstream']['body']='{"candidates":[]}';ok(run_case()->status===502,'empty generation refused');
echo "PASS $checks assertions; HTTP fully mocked\n";
}
