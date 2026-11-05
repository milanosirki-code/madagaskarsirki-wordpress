<?php
define( 'ABSPATH', __DIR__ );
class WP_REST_Response {
    public $data; public $status; public $headers = array();
    function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
    function header( $key, $value ) { $this->headers[$key] = $value; }
}
class DiagnosticRequest { private $body; function __construct( $body = '{}' ) { $this->body=$body; } function get_body() { return $this->body; } }
function add_action() {}
function home_url( $path ) { return $GLOBALS['origin'] . $path; }
function current_user_can( $cap ) { return $cap === 'manage_options' && $GLOBALS['admin']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function get_site_option( $key, $default = false ) { return $default; }
function get_transient( $key ) { return $GLOBALS['cooldown']; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['cooldown']=$value; }
function add_option( $key, $value ) { if(isset($GLOBALS['options'][$key]))return false; $GLOBALS['options'][$key]=$value; return true; }
function delete_option( $key ) { unset($GLOBALS['options'][$key]); }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][]=array($ns,$route,$args); }
function wp_json_encode( $value ) { return json_encode($value); }
function is_wp_error( $value ) { return $value instanceof Exception; }
function wp_remote_retrieve_response_code( $value ) { return $value['status']; }
function wp_remote_retrieve_body( $value ) { return $value['body']; }
function wp_remote_request( $url, $args ) {
    $GLOBALS['calls'][]=array($url,$args);
    if($GLOBALS['throw'])throw new Exception('fake-secret');
    return array_shift($GLOBALS['responses']);
}
require __DIR__.'/../wp-content/plugins/mdg-gemini-diagnostic/mdg-gemini-diagnostic.php';
use Madagaskar\GeminiDiagnostic\Check;
$checks=0;
function ok($condition,$label){global $checks; $checks++; if(!$condition)throw new Exception($label);}
function reset_case(){
    $GLOBALS['origin']=Check::ORIGIN;$GLOBALS['admin']=true;$GLOBALS['cooldown']=false;$GLOBALS['throw']=false;
    $GLOBALS['calls']=array();$GLOBALS['routes']=array();
    $GLOBALS['options']=array('connectors_ai_google_api_key'=>'fake-secret','active_plugins'=>array('ai-provider-for-google/plugin.php'));
    $GLOBALS['responses']=array(
        array('status'=>200,'body'=>json_encode(array('models'=>array(array('name'=>'models/gemini-2.5-flash-lite','supportedGenerationMethods'=>array('generateContent')))))),
        array('status'=>200,'body'=>json_encode(array('candidates'=>array(array('content'=>array('parts'=>array(array('text'=>'MDG_GEMINI_OK'))))))))
    );
}
function run_case(){ $result=Check::run(new DiagnosticRequest());ok(strpos(json_encode($result),'fake-secret')===false,'no secret in output');return $result; }
reset_case();Check::routes();ok(count($GLOBALS['routes'])===1,'staging registered');ok($GLOBALS['routes'][0][2]['methods']==='POST','POST only');
$r=run_case();ok($r->status===200 && $r->data['success'],'confirmed generation');ok(count($GLOBALS['calls'])===2,'one list plus one generation');
foreach($GLOBALS['calls'] as $call){ok(strpos($call[0],'fake-secret')===false,'secret absent from URL');ok($call[1]['redirection']===0,'no redirects');ok($call[1]['headers']['x-goog-api-key']==='fake-secret','header authentication');}
ok(!isset($GLOBALS['options'][Check::LOCK]),'lock released');ok($r->headers['Cache-Control']==='private, no-store','no caching');
reset_case();$GLOBALS['origin']='https://madagaskarsirki.com';Check::routes();ok(count($GLOBALS['routes'])===0,'production no route');ok(run_case()->status===403 && count($GLOBALS['calls'])===0,'production no request');
reset_case();$GLOBALS['admin']=false;ok(run_case()->status===403 && count($GLOBALS['calls'])===0,'non-admin no request');
reset_case();$r=Check::run(new DiagnosticRequest('{"prompt":"hello"}'));ok($r->status===400 && count($GLOBALS['calls'])===0,'no arbitrary prompts');
reset_case();$GLOBALS['cooldown']=true;ok(run_case()->status===429 && count($GLOBALS['calls'])===0,'cooldown');
reset_case();$GLOBALS['options'][Check::LOCK]=1;ok(run_case()->status===429 && count($GLOBALS['calls'])===0,'concurrency lock');
reset_case();$GLOBALS['options']['active_plugins']=array();ok(run_case()->data['code']==='PROVIDER_INACTIVE','inactive provider');
reset_case();$GLOBALS['options']['connectors_ai_google_api_key']='';ok(run_case()->data['code']==='CREDENTIAL_MISSING','missing key');
foreach(array(400,401,403,404,429,500) as $status){reset_case();$GLOBALS['responses'][0]=array('status'=>$status,'body'=>'fake-secret');$r=run_case();ok($r->status===502 && $r->data['upstream_status']===$status,'safe upstream status');ok(count($GLOBALS['calls'])===1,'no retry');}
reset_case();$GLOBALS['responses'][0]=new Exception('fake-secret');ok(run_case()->data['code']==='GOOGLE_NETWORK_ERROR','network redaction');
reset_case();$GLOBALS['responses'][0]['body']='invalid';ok(run_case()->data['code']==='GOOGLE_INVALID_RESPONSE','invalid json');
reset_case();$GLOBALS['responses'][0]['body']='{"models":[]}';ok(run_case()->data['code']==='SUPPORTED_MODEL_NOT_FOUND','no model guessing');
reset_case();$GLOBALS['responses'][1]['body']='{"candidates":[]}';ok(run_case()->data['code']==='GENERATION_NOT_CONFIRMED','empty text not success');
reset_case();$GLOBALS['responses'][1]=array('status'=>429,'body'=>'fake-secret');$r=run_case();ok($r->data['phase']==='generation' && !$r->data['success'],'generation failure not success');
reset_case();$GLOBALS['throw']=true;ok(run_case()->data['code']==='DIAGNOSTIC_INTERNAL_ERROR','exception redaction');ok(!isset($GLOBALS['options'][Check::LOCK]),'lock released on exception');
echo "PASS $checks assertions; no live API calls in tests\n";
