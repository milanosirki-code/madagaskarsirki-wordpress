<?php
/**
 * Temporary admin-only #115 diagnostics; never production autoloaded.
 * Activate in passive snippet119 then restore its exact original body.
 * GET blocks non-transient SQL and remote writes before execution.
 * POST atomically patches only the hash-verified Kommo service, reversible.
 */
if(!defined('ABSPATH'))exit;
function mmc115_blob($s){return sha1("blob ".strlen($s)."\0".$s);}
function mmc115_sources(){
    $out=[];
    foreach(['class-mmc-sales-service.php','class-mmc-kommo-service.php'] as $file){
        $s=file_get_contents(WP_PLUGIN_DIR.'/madagaskar-management-center/includes/'.$file);
        $out[$file]=['git_blob'=>mmc115_blob($s),'sha256'=>hash('sha256',$s)];
    }return $out;
}
function mmc115_snapshot(){
    global $wpdb;
    $rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}mmc_kommo_profiles ORDER BY id",ARRAY_A);
    $hashes=[];$selected=null;
    foreach((array)$rows as $row){
        $hashes[(string)$row['id']]=hash('sha256',wp_json_encode($row));
        if((int)$row['program_id']===9){
            $fields=[];foreach($row as $k=>$v)$fields[$k]=['type'=>gettype($v),'sha256'=>hash('sha256',wp_json_encode($v))];
            $selected=['id'=>(int)$row['id'],'program_id'=>9,'updated_at'=>$row['updated_at'],'fields'=>$fields];
        }
    }return ['captured_at'=>gmdate('c'),'storage'=>'custom_table:mmc_kommo_profiles','row_count'=>count($hashes),'all_rows_sha256'=>hash('sha256',wp_json_encode($hashes)),'profile'=>$selected];
}
function mmc115_probe($name,$input){
    global $wpdb;
    $allowed=json_decode('["madagaskar/kommo-unified-source-v2-preview","madagaskar/system-health-checks","madagaskar/program-integrity-checks","madagaskar/program-selected-venue-check","madagaskar/mmc-sales-health","madagaskar/mmc-sales-mappings","madagaskar/mmc-sales-summary","madagaskar/mmc-sales-recent-orders","madagaskar/dashboard-overview","madagaskar/dashboard-programs","madagaskar/dashboard-critical-alerts","madagaskar/tasks-list","madagaskar/task-get","madagaskar/tasks-summary","madagaskar/refund-preflight","madagaskar/refund-cases-list","madagaskar/refund-case-get","madagaskar/order-refunds-history","madagaskar/v4-sales-product-status","madagaskar/v4-postponement-mappings","madagaskar/v4-postponement-map-get","madagaskar/v4-postponement-preflight","madagaskar/v4-transfer-scan","madagaskar/v4-transfer-last","madagaskar/customer-tickets-query","madagaskar/customer-ticket-lookups","madagaskar/sales-system-audit","madagaskar/daily-report-snapshot","madagaskar/report-runs","madagaskar/report-run-get","madagaskar/region-sources","madagaskar/region-districts","madagaskar/region-program-summary","madagaskar/population-lookup","madagaskar/population-target-summary","madagaskar/region-recent-imports","madagaskar/field-targets","madagaskar/field-summary","madagaskar/field-routes","madagaskar/field-recent-visits","madagaskar/field-share-links","madagaskar/operations-plan-get","madagaskar/operations-summary","madagaskar/operations-resources-list","madagaskar/operations-program-resources","madagaskar/operations-checklist-list","madagaskar/operations-schedule-list","madagaskar/marketing-pack-get","madagaskar/marketing-item-get","madagaskar/meta-plan-get","madagaskar/mdg-bridge-status","madagaskar/mdg-bridge-candidates","madagaskar/mdg-publish-preview","madagaskar/kommo-configuration","madagaskar/kommo-connection-diagnostics","madagaskar/kommo-pipeline-diagnostics","madagaskar/kommo-program-status","madagaskar/kommo-source-consistency-check","madagaskar/legacy-mdg-mmc-migration-preview"]',true);
    if(!in_array($name,$allowed,true))return new WP_Error('mmc115_not_allowed','Not an audited read-only ability.');
    $before=mmc115_snapshot();$attempts=[];$warnings=[];$http=[];
    $guard=function($sql)use(&$attempts,$wpdb){
        if(preg_match('/^\\s*(SELECT|SHOW|DESC|DESCRIBE|EXPLAIN)\\b/i',$sql))return $sql;
        $technical=false;
        if(strpos($sql,$wpdb->options)!==false){
            $keys=[];
            if(preg_match_all("/option_name\\s*=\\s*'([^']+)'/i",$sql,$m))$keys=$m[1];
            if(preg_match_all("/(?:VALUES|,)\\s*\\(\\s*'(_transient_[^']+)'/i",$sql,$m))$keys=array_merge($keys,$m[1]);
            $technical=count($keys)>0;
            foreach($keys as $key)if(strpos($key,'_transient_')!==0)$technical=false;
        }
        preg_match('/^\\s*(\\w+)/',$sql,$op);
        preg_match('/(?:UPDATE|INTO|FROM)\\s+`?([a-zA-Z0-9_]+)/i',$sql,$table);
        preg_match_all('/`([a-zA-Z0-9_]+)`\\s*=/',$sql,$cols);
        $trace=array_map(function($t){return ['class'=>$t['class']??'','method'=>$t['function']??'','file'=>isset($t['file'])?str_replace(ABSPATH,'',$t['file']):'','line'=>$t['line']??0];},array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),1,10));
        $attempts[]=['operation'=>$op[1]??'unknown','table'=>$table[1]??'unknown','columns'=>$cols[1]??[],'type'=>$technical?'SIDE_EFFECT_TECHNICAL':'SIDE_EFFECT_DOMAIN','executed'=>$technical,'trace'=>$trace];
        if(!$technical)throw new RuntimeException('MMC115_PERSISTENT_WRITE_BLOCKED');
        return $sql;
    };
    $remote=function($pre,$args,$url)use(&$http){
        $method=strtoupper($args['method']??'GET');
        $http[]=['method'=>$method,'host'=>wp_parse_url($url,PHP_URL_HOST)];
        if(!in_array($method,['GET','HEAD'],true))return new WP_Error('mmc115_remote_write_blocked','Remote write blocked.');
        return $pre;
    };
    add_filter('query',$guard,PHP_INT_MAX);add_filter('pre_http_request',$remote,PHP_INT_MAX,3);
    set_error_handler(function($level,$message,$file,$line)use(&$warnings){if(error_reporting()&$level)$warnings[]=['level'=>$level,'file'=>str_replace(ABSPATH,'',$file),'line'=>$line];return true;});
    $level=ob_get_level();ob_start();$out=[];
    try{
        $a=wp_get_ability($name);$r=$a?$a->execute($input):new WP_Error('missing_ability','Missing ability.');
        $out=['name'=>$name,'status'=>is_wp_error($r)?'EXPECTED_OR_SERVICE_ERROR':'CALL_OK','error_code'=>is_wp_error($r)?$r->get_error_code():null,'keys'=>is_array($r)?array_keys($r):[]];
        if(is_array($r)&&isset($r['summary']))$out['summary']=$r['summary'];
        if($name==='madagaskar/program-integrity-checks'&&is_array($r))$out['checks']=array_map(function($c){return ['key'=>$c['key'],'severity'=>$c['severity']];},$r['checks']??[]);
    }catch(Throwable $e){
        $out=['name'=>$name,'status'=>$e->getMessage()==='MMC115_PERSISTENT_WRITE_BLOCKED'?'PERSISTENT_WRITE_BLOCKED':'PHP_ERROR','exception'=>get_class($e),'file'=>str_replace(ABSPATH,'',$e->getFile()),'line'=>$e->getLine()];
    }finally{
        while(ob_get_level()>$level)ob_end_clean();
        restore_error_handler();remove_filter('query',$guard,PHP_INT_MAX);remove_filter('pre_http_request',$remote,PHP_INT_MAX);
    }
    $after=mmc115_snapshot();$out['persistent_profile_unchanged']=$before['all_rows_sha256']===$after['all_rows_sha256'];
    $out['before']=$before;$out['after']=$after;$out['attempts']=$attempts;$out['http']=$http;$out['warnings']=$warnings;
    return $out;
}
function mmc115_change_file($rollback=false){
    $path=WP_PLUGIN_DIR.'/madagaskar-management-center/includes/class-mmc-kommo-service.php';
    $old=<<<'BEFORE'
        $profile = self::ensure_profile( $program_id );
        if ( is_wp_error( $profile ) ) {
            return $profile;
        }

        $pipeline_id = absint( get_option( 'mmc_kommo_pipeline_id', 0 ) );
BEFORE;
    $new=<<<'AFTER'
        // Preview must not create or refresh persistent profile/source metadata.
        // A missing profile represents a not-yet-created lead; write paths still
        // call ensure_profile() explicitly before synchronizing.
        $profile = self::get_profile( $program_id );
        if ( ! $profile ) {
            $profile = (object) array( 'kommo_lead_id' => 0 );
        }

        $pipeline_id = absint( get_option( 'mmc_kommo_pipeline_id', 0 ) );
AFTER;
    $expected=$rollback?'10cd9085aef52462aa68312c08c995297dedff87':'10418343eb9cc162e6c463a37f70bd040e9d5f07';
    $wanted=$rollback?'10418343eb9cc162e6c463a37f70bd040e9d5f07':'10cd9085aef52462aa68312c08c995297dedff87';
    $s=file_get_contents($path);if(mmc115_blob($s)!==$expected)return new WP_Error('mmc115_stale_source','Source hash mismatch; no write.');
    $from=$rollback?$new:$old;$to=$rollback?$old:$new;
    if(substr_count($s,$from)!==1)return new WP_Error('mmc115_ambiguous','Patch target mismatch; no write.');
    $patched=str_replace($from,$to,$s);
    if(mmc115_blob($patched)!==$wanted)return new WP_Error('mmc115_wrong_result','Patched hash mismatch; no write.');
    $temp=tempnam(dirname($path),'.mmc115-');
    if(!$temp||file_put_contents($temp,$patched)!==strlen($patched)){if($temp)unlink($temp);return new WP_Error('mmc115_temp','Temporary file failed.');}
    chmod($temp,fileperms($path)&0777);
    if(!rename($temp,$path)){unlink($temp);return new WP_Error('mmc115_rename','Atomic replacement failed.');}
    if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
    $read=file_get_contents($path);
    return ['at'=>gmdate('c'),'rollback'=>$rollback,'before_blob'=>$expected,'after_blob'=>mmc115_blob($read),'readback_verified'=>mmc115_blob($read)===$wanted,'sha256'=>hash('sha256',$read)];
}
add_action('rest_api_init',function(){
    $permission=function(){return current_user_can('manage_options');};
    register_rest_route('mmc-issue115/v1','/check',['methods'=>'GET','permission_callback'=>$permission,'callback'=>function($r){
        $mode=$r->get_param('mode')?:'snapshot';
        if($mode==='snapshot')return ['sources'=>mmc115_sources(),'snapshot'=>mmc115_snapshot()];
        if($mode==='probe')return mmc115_probe((string)$r->get_param('name'),json_decode((string)$r->get_param('input'),true)?:[]);
        return new WP_Error('mmc115_mode','Invalid mode.');
    }]);
    register_rest_route('mmc-issue115/v1','/deploy',['methods'=>'POST','permission_callback'=>$permission,'callback'=>function($r){
        if($r->get_param('confirm')!=='issue115-only')return new WP_Error('mmc115_confirm','Explicit scoped confirmation required.');
        return mmc115_change_file((bool)$r->get_param('rollback'));
    }]);
});
