<?php
/** Milano must NEVER use legacy MMC draft fallback (hard-coded Madagaskar). */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
function assert_guard($value,$reason){if(!$value){fwrite(STDERR,"FAIL ".$reason."\n");exit(1);}}
function absint($v){return abs((int)$v);}
class WP_Error{
 public $code,$message;
 public function __construct($code,$message){$this->code=$code;$this->message=$message;}
 public function get_error_code(){return $this->code;}
}
$scenario=$argv[1]??'milano';
$brand='Milano Sirki';
if('missing_brand'===$scenario)$brand='';
if('invalid_brand'===$scenario)$brand='Both';
if('alias_milano'===$scenario)$brand='milano';
class MMC_Program_Service{
 public static function get_program($id){
    global $brand;
    return $id===123?(object)array('brand'=>$brand):null;
 }
 public static function normalized_program_brand($brand){
    if($brand==='milano'||$brand==='Milano Sirki')return 'Milano Sirki';
    if($brand==='madagaskar'||$brand==='Madagaskar Sirki')return 'Madagaskar Sirki';
    return '';
 }
}
$path=dirname(__DIR__,3).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-mdg-bridge-service.php';
require $path;
assert_guard(!class_exists('MMC_MDG_Draft_Sync_Service'),
   'test is not exercising fallback-only path');
switch($scenario){
 case 'milano':break;
 case 'alias_milano':break;
 case 'missing_brand':break;
 case 'invalid_brand':break;
 case 'orphan_program':break;
 default:assert_guard(false,'unknown scenario');
}
$id=$scenario==='orphan_program'?777:123;
$result=MMC_MDG_Bridge_Service::create_draft_from_program($id);
assert_guard($result instanceof WP_Error,'legacy fallback did not block untrusted brand');
assert_guard($result->get_error_code()==='mmc_milano_draft_sync_required',
   'unexpected error type in brand blocker');
$src=file_get_contents($path);
assert_guard(strpos($src,"'Madagaskar Sirki' !== \$source_brand")!==false,
   'only canonical Madagaskar brand may pass to legacy draft fallback');
echo "PASS {$scenario}\n";
