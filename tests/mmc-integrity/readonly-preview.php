<?php
/** Actual Kommo preview regression; SQL writes fail before any persistent mutation. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS',60);
define('MMC_KOMMO_TOKEN','synthetic-test-token');
define('MMC_KOMMO_SUBDOMAIN','synthetic');
class WP_Error { public function __construct(public $code, public $message='') {} }
function is_wp_error($x){return $x instanceof WP_Error;}
function absint($x){return abs((int)$x);}
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}
function get_transient($k){return $GLOBALS['cache'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['cache'][$k]=$v;$GLOBALS['cache_writes']++;return true;}
function sanitize_text_field($v){return (string)$v;}
function sanitize_title($v){return strtolower($v);}
function remove_accents($v){return strtr($v,['ı'=>'i','İ'=>'I','ş'=>'s','Ş'=>'S','ğ'=>'g','Ğ'=>'G','ü'=>'u','Ü'=>'U','ö'=>'o','Ö'=>'O','ç'=>'c','Ç'=>'C']);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_date($format,$timestamp=null){return date($format,$timestamp??1700000000);}
function current_time($type){return '2026-10-04 13:00:00';}
function home_url($path){return 'https://example.invalid'.$path;}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function wp_remote_request($url,$args){
    if($args['method']!=='GET')throw new RuntimeException('REMOTE_WRITE');
    $GLOBALS['remote_reads']++;
    $data=str_contains($url,'/leads/pipelines')?['_embedded'=>['pipelines'=>$GLOBALS['catalog']]]:$GLOBALS['lead'];
    return ['response'=>['code'=>200],'body'=>json_encode($data)];
}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
class MMC_Program_Service {
    static function get_program($id){return $id===9?$GLOBALS['program']:null;}
    static function statuses(){return ['cancelled'=>'İptal','preparation'=>'Hazırlık','completed'=>'Arşiv'];}
}
class PreviewDB {
    public $prefix='wp_'; public $writes=0;
    function prepare($sql,...$args){return $sql;}
    function get_row($sql){return $GLOBALS['profile']?clone $GLOBALS['profile']:null;}
    function update(...$args){$this->writes++;throw new RuntimeException('DOMAIN_WRITE');}
    function insert(...$args){$this->writes++;throw new RuntimeException('DOMAIN_WRITE');}
    function delete(...$args){$this->writes++;throw new RuntimeException('DOMAIN_WRITE');}
}
$wpdb=new PreviewDB;
$options=['mmc_kommo_pipeline_id'=>77];
$cache=[];$cache_writes=0;$remote_reads=0;$assertions=0;
$program=(object)['status'=>'cancelled','program_code'=>'TEST','province_name'=>'Test','district_name'=>'Test'];
$profile=(object)['id'=>3,'program_id'=>9,'kommo_lead_id'=>800,'ai_source_status'=>'synced','ai_source_id'=>'synthetic-id','ai_synced_hash'=>'unchanged','source_hash'=>'unchanged','source_token'=>'synthetic-token','updated_at'=>'2026-01-01 00:00:00'];
$source=__DIR__.'/../../wp-content/plugins/madagaskar-management-center/includes/class-mmc-kommo-service.php';
require $source;
function check($condition,$message){$GLOBALS['assertions']++;if(!$condition)throw new RuntimeException($message);}
$before=serialize($profile);
$a=MMC_Kommo_Service::status_bridge_preview(9);
$b=MMC_Kommo_Service::status_bridge_preview(9);
check($a===$b,'Repeated preview changed response');
check($a['action']==='manual_cancel','Cancelled semantics changed');
check($a['lead_id']===800,'Existing lead lost');
check(serialize($profile)===$before,'Persistent metadata changed');
check($wpdb->writes===0,'Preview wrote domain data');
check(is_wp_error(MMC_Kommo_Service::status_bridge_preview(0)),'Invalid program accepted');
$profile=null;
$a=MMC_Kommo_Service::status_bridge_preview(9);
check($a['lead_id']===0,'Missing profile preview should use no lead');
check($wpdb->writes===0,'Missing profile created record/templates');
$program->status='preparation';
$stages=MMC_Kommo_Service::program_pipeline_blueprint()['stages'];
$statuses=[];foreach($stages as $i=>$stage)$statuses[]=['id'=>100+$i,'name'=>$stage['name'],'sort'=>$i];
$catalog=[['id'=>77,'name'=>'MMC','_embedded'=>['statuses'=>$statuses]]];
$a=MMC_Kommo_Service::status_bridge_preview(9);
check(!is_wp_error($a),'Pipeline preview failed');
check($a['action']==='create'&&$a['should_update']===true,'Missing lead action changed');
check($wpdb->writes===0,'Create preview performed creation');
check($cache_writes>0,'Technical transient caching no longer works');
$profile=(object)['kommo_lead_id'=>800];
$lead=['id'=>800,'pipeline_id'=>77,'status_id'=>100];
$a=MMC_Kommo_Service::status_bridge_preview(9,true);
check(!is_wp_error($a)&&$a['action']==='stay','Existing matching lead semantics changed');
$lead['status_id']=101;
$a=MMC_Kommo_Service::status_bridge_preview(9,true);
check(!is_wp_error($a)&&$a['action']==='preserve_ahead','Ahead-stage protection changed');
$program->status='completed';$lead['status_id']=100;
$a=MMC_Kommo_Service::status_bridge_preview(9,true);
check(!is_wp_error($a)&&$a['action']==='advance','Forward stage preview changed');
check($wpdb->writes===0,'Stage preview wrote data');
$lead['pipeline_id']=999;
$a=MMC_Kommo_Service::status_bridge_preview(9,true);
check(!is_wp_error($a)&&$a['should_update']===false,'Foreign pipeline protection changed');
/* Negative control: execute the original preview path, not a duplicated model. */
$legacy=file_get_contents($source);
$read=<<<'READ'
        // Preview must not create or refresh persistent profile/source metadata.
        // A missing profile represents a not-yet-created lead; write paths still
        // call ensure_profile() explicitly before synchronizing.
        $profile = self::get_profile( $program_id );
        if ( ! $profile ) {
            $profile = (object) array( 'kommo_lead_id' => 0 );
        }
READ;
$old=<<<'OLD'
        $profile = self::ensure_profile( $program_id );
        if ( is_wp_error( $profile ) ) {
            return $profile;
        }
OLD;
check(substr_count($legacy,$read)===1,'Negative-control patch target mismatch');
$legacy=str_replace($read,$old,$legacy);
$legacy=str_replace('class MMC_Kommo_Service','class Legacy_Kommo_Service',$legacy);
eval(substr($legacy,5));
$program->status='cancelled';$profile=(object)['id'=>3,'kommo_lead_id'=>800,'source_token'=>'synthetic','ai_source_status'=>'synced','ai_source_id'=>'synthetic','ai_synced_hash'=>'unchanged'];
$blocked=false;try{Legacy_Kommo_Service::status_bridge_preview(9);}catch(RuntimeException $e){$blocked=$e->getMessage()==='DOMAIN_WRITE';}
check($blocked,'Original code did not reproduce blocked persistent update');
echo "PASS $assertions assertions; repeated/missing profile/stage semantics; legacy persistent update blocked.\n";
