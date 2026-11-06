<?php
namespace Madagaskar\WebAssistant {
    class WordPressSource {
        public function bundle($id){if($id!==13)throw new \Exception('wrong program');return $GLOBALS['bundle'];}
        public function origin(){return $GLOBALS['origin'];}
    }
}
namespace Madagaskar\Visitor { function time(){return $GLOBALS['clock'];} }
namespace {
define('ABSPATH',__DIR__);
class WP_Error {public $code;public $data;function __construct($c,$m,$d){$this->code=$c;$this->data=$d;}}
class WP_REST_Response {public $data;public $status;public $headers=array();function __construct($d,$s){$this->data=$d;$this->status=$s;}function header($k,$v){$this->headers[$k]=$v;}}
class Request {private $data;public $headers;public $raw;function __construct($d=array(),$h=array()){$this->data=$d;$this->headers=$h;$this->raw=json_encode($d);}function get_body(){return $this->raw;}function get_json_params(){return $this->data;}function get_header($h){return $this->headers[$h]??'';}}
class DB {public $options='wp_options';public $deletes=0;function delete($table,$where,$formats){if($table!==$this->options||strpos($where['option_name'],'mdg_vp_lock_')!==0)throw new \Exception('business write');$this->deletes++;$name=$where['option_name'];if(($GLOBALS['opts'][$name]??null)===$where['option_value']){unset($GLOBALS['opts'][$name]);return 1;}return 0;}}
function add_action(){}function add_filter(){}function home_url($p){return $GLOBALS['origin'].$p;}function current_user_can($c){return $GLOBALS['admin'];}function wp_salt($s){return 'fixture-'.$s;}
function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}function add_option($k,$v){if(array_key_exists($k,$GLOBALS['opts']))return false;$GLOBALS['opts'][$k]=$v;return true;}
function get_transient($k){return $GLOBALS['cache'][$k]??false;}function set_transient($k,$v,$s){$GLOBALS['cache'][$k]=$v;}
function wp_cache_delete(){}function wp_json_encode($d,$flags=0){return json_encode($d,$flags);}function esc_url($u){return htmlspecialchars($u,ENT_QUOTES);}
function is_wp_error($r){return $r instanceof \Exception;}function wp_remote_retrieve_response_code($r){return $r['status'];}function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_request($url,$args){$GLOBALS['calls'][]=array($url,$args);return $GLOBALS['upstream'];}
function register_rest_route($ns,$r,$args){$GLOBALS['routes'][$r]=$args;}
function is_page($slug){return $slug==='bilet-al';}function in_the_loop(){return true;}function is_main_query(){return true;}
require __DIR__.'/../wp-content/plugins/mdg-visitor-pilot/mdg-visitor-pilot.php';
use Madagaskar\Visitor\Gate;use Madagaskar\Visitor\Model;use Madagaskar\Visitor\Gemini;use Madagaskar\Visitor\Api;use Madagaskar\Visitor\Problem;
$checks=0;function ok($v,$label){global $checks;$checks++;if(!$v)throw new \Exception('FAIL '.$label);}
function problem($fn,$code){try{$fn();throw new \Exception('Expected '.$code);}catch(Problem $e){ok($e->getMessage()===$code,'expected '.$code);}}
function reset_case(){
 $property=new \ReflectionProperty(\Madagaskar\Visitor\Page::class,'entry_rendered');$property->setAccessible(true);$property->setValue(false);
 $GLOBALS['clock']=strtotime('2026-11-06 00:00:00 UTC');$GLOBALS['origin']=Gate::LIVE;$GLOBALS['admin']=false;$_SERVER['REMOTE_ADDR']='192.0.2.1';
 $GLOBALS['opts']=array('mdg_vp_enabled'=>'1','mdg_vp_ai_enabled'=>'1','connectors_ai_google_api_key'=>'fixture-secret');$GLOBALS['cache']=array();$GLOBALS['calls']=array();$GLOBALS['routes']=array();$GLOBALS['wpdb']=new DB;
 $bridge=array('program_id'=>13,'mmc_event_id'=>13,'mdg_event_id'=>10);
 $GLOBALS['bundle']=array('program'=>array('id'=>13,'brand'=>'Madagaskar Sirki','status'=>'sales_open','planned_date'=>'2026-11-08','city'=>'İzmir','district'=>'Konak'),
 'mmc_event'=>array('id'=>13,'program_id'=>13,'status'=>'sales_open','event_date'=>'2026-11-08'), 'mdg_event'=>array('id'=>10,'status'=>'onsale','title'=>'Madagaskar İzmir','sale_start'=>null,'sale_end'=>null),
 'program_bridges'=>array($bridge),'event_bridges'=>array($bridge),'venue_matches'=>true,'venue'=>array('name'=>'Fixture <img src=x>','address'=>'Fixture address'),'ticket_url'=>Gate::LIVE.'/etkinlik/madagaskar-sirki-izmir-08-kasim-2026/',
 'mmc_session_times'=>array('2026-11-08 12:00:00'),'family_conflict'=>false,'family'=>array('available'=>true,'price_minor'=>130000,'source'=>'fixture'),
 'sessions'=>array(array('id'=>88,'event_id'=>10,'status'=>'onsale','start_at'=>'2026-11-08 09:00:00','sale_start'=>null,'sale_end'=>null,'native_closed'=>false,'available_units'=>500,'product_ready'=>true,'parent_max_quantity'=>40,'tickera_event_id'=>999,
 'tickets'=>array(array('code'=>'YETISKIN','active'=>true,'price'=>'585','capacity_units'=>1,'variation_id'=>1,'variation_ready'=>true,'wc_price'=>'585','max_quantity'=>20),array('code'=>'COCUK','active'=>true,'price'=>'290','capacity_units'=>1,'variation_id'=>2,'variation_ready'=>true,'wc_price'=>'290','max_quantity'=>20)))));
 $GLOBALS['upstream']=array('status'=>200,'body'=>json_encode(array('candidates'=>array(array('content'=>array('parts'=>array(array('text'=>json_encode(array('city_key'=>'İzmir / Konak','adults'=>2,'children'=>3,'infants'=>0))))))))));
}
function dispatch($route,$data,$headers=array()){$r=Api::dispatch($route,new Request($data,$headers));ok(strpos(json_encode($r),'fixture-secret')===false,'no secret returned');return $r;}
reset_case();Api::routes();ok(count($GLOBALS['routes'])===5,'four visitor routes and one admin health');ok(!current_user_can('manage_options'),'anonymous fixture');
$boot=Api::bootstrap(new Request());ok($boot->status===200,'anonymous bootstrap');$token=$boot->data['token'];ok(strlen(Gate::verify($token))===48,'signed session');
$cat=dispatch('catalogue',array('token'=>$token));ok($cat->status===200&&count($cat->data['offers'])===1,'anonymous safe catalogue');ok(!isset($cat->data['offers'][0]['tickets'])&&!isset($cat->data['blocked']),'no internal price mappings/diagnostics');
$input=array('token'=>$token,'session_id'=>88,'party'=>array('adults'=>2,'children'=>3,'infants'=>0),'source_revision'=>$cat->data['offers'][0]['source_revision']);
$q=dispatch('quote',$input);ok($q->status===200&&$q->data['recommended']['total_minor']===159000&&!$q->data['reservation'],'anonymous native quote');ok(!isset($q->data['alternatives']),'bounded response');
$parsed=dispatch('parse',array('token'=>$token,'text'=>'İzmir iki yetişkin üç çocuk'));ok($parsed->status===200&&$parsed->data['requires_confirmation'],'bounded model proposal');ok(count($GLOBALS['calls'])===1,'one model request');ok(strpos($GLOBALS['calls'][0][0],'fixture-secret')===false,'key not URL');ok($GLOBALS['calls'][0][1]['redirection']===0,'no redirects');ok($GLOBALS['calls'][0][1]['headers']['x-goog-api-key']==='fixture-secret','server key header');ok(Api::health()->status===403,'health not public');
reset_case();$GLOBALS['opts']['mdg_vp_enabled']='0';ok(Api::bootstrap(new Request())->status===404,'off by default gate');ok(Api::permission(new Request()) instanceof WP_Error,'REST permission denies disabled');
reset_case();ok(Api::bootstrap(new Request(array(),array('origin'=>'https://evil.example')))->status===403,'foreign origin');ok(Api::bootstrap(new Request(array(),array('sec-fetch-site'=>'cross-site')))->status===403,'cross-site denied');
reset_case();$GLOBALS['origin']='https://untrusted.example';ok(Api::bootstrap(new Request())->status===404,'untrusted site');
reset_case();$t=Gate::issue();problem(function()use($t){Gate::verify(substr($t,0,-1).($t[strlen($t)-1]==='a'?'b':'a'));},'SESSION_REQUIRED');$GLOBALS['clock']+=1801;problem(function()use($t){Gate::verify($t);},'SESSION_REQUIRED');
reset_case();ok(dispatch('catalogue',array('token'=>'fake'))->status===403,'forged bearer');$bad=$input;$bad['token']=Gate::issue();$bad['price']=1;ok(dispatch('quote',$bad)->status===400,'client price denied');
reset_case();$bad=$input;$bad['token']=Gate::issue();$bad['session_id']=999;ok(dispatch('quote',$bad)->status===409,'other event/session excluded');
reset_case();$bad=$input;$bad['token']=Gate::issue();$bad['source_revision']=str_repeat('0',64);ok(dispatch('quote',$bad)->data['code']==='STALE_SELECTION','stale quote');
reset_case();$GLOBALS['bundle']['program']['status']='venue_confirmed';ok(Model::catalogue()['offers']===array(),'state conflict excluded');
reset_case();$GLOBALS['bundle']['mdg_event']['id']=39;ok(Model::catalogue()['offers']===array(),'other event rejected before quote');
reset_case();$GLOBALS['clock']=strtotime('2026-11-09 00:00:00 UTC');ok(Model::catalogue()['offers']===array(),'expired pilot not rolled forward');
reset_case();$GLOBALS['opts']['mdg_vp_ai_enabled']='0';ok(dispatch('parse',array('token'=>Gate::issue(),'text'=>'İzmir iki yetişkin'))->status===503&&!$GLOBALS['calls'],'AI independent kill switch');ok(count(Model::catalogue()['offers'])===1,'manual catalogue still works');
reset_case();$GLOBALS['upstream']=array('status'=>429,'body'=>'fixture-secret');$r=dispatch('parse',array('token'=>Gate::issue(),'text'=>'İzmir iki yetişkin'));ok($r->status===502&&count($GLOBALS['calls'])===1,'no retry/error leak');
reset_case();$req=new Request(array('token'=>Gate::issue()));$req->raw=str_repeat('x',3501);ok(Api::dispatch('catalogue',$req)->status===413,'bounded input');
reset_case();for($i=0;$i<20;$i++)ok(Api::bootstrap(new Request())->status===200,'bootstrap within limit');ok(Api::bootstrap(new Request())->status===429,'bootstrap IP ceiling');
reset_case();$s=Gate::issue();for($i=0;$i<5;$i++)ok(dispatch('parse',array('token'=>$s,'text'=>'İzmir iki yetişkin'))->status===200,'session below cap');ok(dispatch('parse',array('token'=>$s,'text'=>'İzmir iki yetişkin'))->status===429,'session cap');ok(count($GLOBALS['calls'])===5,'capped before upstream');
reset_case();for($i=1;$i<=50;$i++){$_SERVER['REMOTE_ADDR']='192.0.2.'.$i;$r=dispatch('parse',array('token'=>Gate::issue(),'text'=>'İzmir iki yetişkin'));ok($r->status===200,'global budget admission');}$_SERVER['REMOTE_ADDR']='192.0.2.51';ok(dispatch('parse',array('token'=>Gate::issue(),'text'=>'İzmir iki yetişkin'))->status===429,'global daily ceiling');ok(count($GLOBALS['calls'])===50,'no fifty-first model call');
reset_case();$GLOBALS['opts']['mdg_vp_lock_rate']=($GLOBALS['clock']-1).'|'.str_repeat('a',24);Gate::rate(array(array('test',60,1)));ok(!isset($GLOBALS['opts']['mdg_vp_lock_rate']),'expired own lock recovered');
reset_case();$new=($GLOBALS['clock']+100).'|'.str_repeat('b',24);Gate::locked('ownership',function()use($new){$GLOBALS['opts']['mdg_vp_lock_ownership']=$new;});ok($GLOBALS['opts']['mdg_vp_lock_ownership']===$new,'old owner cannot delete replacement');
reset_case();foreach(array(array('adults'=>0),array('adults'=>2.5),array('children'=>21),array('city_key'=>'İstanbul / Fake'),array('price'=>1)) as $patch){$p=array_merge(array('city_key'=>'İzmir / Konak','adults'=>2,'children'=>3,'infants'=>0),$patch);problem(function()use($p){Gemini::validate($p,array('İzmir / Konak'));},'PARSE_NOT_CONFIRMED');}
reset_case();$GLOBALS['admin']=true;ok(Api::health()->data['google_credential_present'],'admin health only presence');
reset_case();ok(\Madagaskar\Visitor\Page::entry('Original')==='Original','listing off by default');$GLOBALS['opts']['mdg_vp_listed']='1';ok(strpos(\Madagaskar\Visitor\Page::entry('Original'),'/bilet-onerisi/')!==false,'explicit entry switch');$GLOBALS['opts']['mdg_vp_enabled']='0';ok(\Madagaskar\Visitor\Page::entry('Original')==='Original','master removes entry');
reset_case();$_SERVER['REQUEST_URI']='/bilet-al/';ob_start();\Madagaskar\Visitor\Page::footer_entry();ok(ob_get_clean()==='','footer listing off');$GLOBALS['opts']['mdg_vp_listed']='1';ob_start();\Madagaskar\Visitor\Page::footer_entry();$entry=ob_get_clean();ok(strpos($entry,'id="mdg-vp-entry"')!==false&&strpos($entry,'hero.appendChild(entry)')!==false,'native template footer entry');ob_start();\Madagaskar\Visitor\Page::footer_entry();ok(ob_get_clean()==='','entry not duplicated');
reset_case();$GLOBALS['opts']['mdg_vp_listed']='1';$_SERVER['REQUEST_URI']='/checkout/';ob_start();\Madagaskar\Visitor\Page::footer_entry();ok(ob_get_clean()==='','no entry on checkout');
echo "PASS $checks anonymous-boundary, canonical-pilot, budget and secret-isolation assertions; no live HTTP\n";
}
