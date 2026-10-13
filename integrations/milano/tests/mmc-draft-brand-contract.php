<?php
/** Draft-only safety contract: MDG titles must use canonical MMC brand. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
function assert_draft_brand($truth,$why){if(!$truth){fwrite(STDERR,"FAIL ".$why."\n");exit(1);}}
// WordPress text helper stub needed by the isolated draft-title fixture.
function remove_accents($value){return $value;}
class MMC_Program_Service{
 public static function normalized_program_brand($v){
  if('milano'===$v||'Milano Sirki'===$v)return 'Milano Sirki';
  if('madagaskar'===$v||'Madagaskar Sirki'===$v)return 'Madagaskar Sirki';
  return '';
 }
}
$path=dirname(__DIR__,3).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-mdg-draft-sync-service.php';
assert_draft_brand(is_file($path),'source not found');
require $path;
$method=new ReflectionMethod('MMC_MDG_Draft_Sync_Service','public_event_title');
$scenario=$argv[1]??'milano';
$program=(object)array('brand'=>'Milano Sirki','province_name'=>'Yalova','district_name'=>'Merkez');
$expected='Milano Sirki — Yalova';
switch($scenario){
 case 'milano':break;
 case 'milano_alias':$program->brand='milano';break;
 case 'milano_district':$program->district_name='Termal';$expected='Milano Sirki — Yalova / Termal';break;
 case 'madagaskar':$program->brand='Madagaskar Sirki';$expected='Madagaskar Sirki — Yalova';break;
 case 'madagaskar_alias':$program->brand='madagaskar';$expected='Madagaskar Sirki — Yalova';break;
 case 'missing_brand':$program->brand='';$expected='';break;
 case 'unknown_brand':$program->brand='both';$expected='';break;
 case 'missing_program':$program=null;$expected='';break;
 default:assert_draft_brand(false,'unexpected scenario');
}
$title=$method->invoke(null,$program);
assert_draft_brand($title===$expected,'wrong MDG title: '.(string)$title);
$src=file_get_contents($path);
assert_draft_brand(strpos($src,"if ( ! \$brand ) {")!==false
 && strpos($src,"'mmc_mdg_brand_required'")!==false,
 'draft sync does not reject unknown brand before writing');
assert_draft_brand(strpos($src,"if ( 'Madagaskar Sirki' === \$brand ) {\n            self::sync_family_package_option")!==false,
 'Milano could be silently enabled in Madagaskar family plugin');
assert_draft_brand(strpos($src,"$" . "title = $". "brand . ' — '")!==false,
 'MDG title does not originate from MMC brand');
echo "PASS {$scenario}\n";
