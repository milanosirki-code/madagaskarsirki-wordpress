<?php
/** Execute actual draft sync against isolated dependencies, never real WordPress. */
define('ABSPATH',__DIR__);
function absint($v){return abs((int)$v);}
function current_user_can($v){return true;}
function get_current_user_id(){return 1;}
function wp_timezone(){return new DateTimeZone('Europe/Istanbul');}
function sanitize_key($v){return strtolower($v);}
function sanitize_text_field($v){return (string)$v;}
function remove_accents($v){return $v;}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$default=array()){return $GLOBALS['family'];}
function update_option($k,$v,$autoload=false){$GLOBALS['family']=$v;$GLOBALS['option_writes']++;}
class WP_Error {
 public function __construct(public $code,public $message){}
 public function get_error_message(){return $this->message;}
}
class MMC_Program_Service {
 static function get_program($id){return (object)array('brand'=>$GLOBALS['brand'],'program_code'=>'TEST','province_name'=>'Yalova','district_name'=>'Merkez');}
 static function normalized_program_brand($v){return in_array($v,array('Milano Sirki','Madagaskar Sirki'),true)?$v:'';}
}
class MMC_Event_Service {
 static function event_for_program($id){return (object)array('id'=>14,'door_open_minutes'=>30,'seating_mode'=>'free');}
 static function sessions($id){return array((object)array('session_time'=>'2026-10-22 18:00:00','capacity'=>500),(object)array('session_time'=>'2026-10-22 19:30:00','capacity'=>500));}
 static function ticket_types($id){return array((object)array('ticket_code'=>'adult','ticket_name'=>'Yetişkin','price'=>490,'capacity_units'=>1,'is_active'=>1),(object)array('ticket_code'=>'child','ticket_name'=>'Çocuk','price'=>245,'capacity_units'=>1,'is_active'=>1),(object)array('ticket_code'=>'family_2_2','ticket_name'=>'Aile 2+2','price'=>1100,'capacity_units'=>4,'is_active'=>1));}
}
class MMC_Venue_Service {
 static function venues_for_program($id){return array((object)array('is_selected'=>1,'venue_source'=>'mdg','venue_id'=>181));}
}
class MDG_Venues {
 static function get($id){return (object)array('id'=>181,'is_active'=>1,'default_duration'=>60,'default_capacity'=>500,'province_code'=>'77','province_name'=>'Yalova','district'=>'Merkez','name'=>'90. Yıl Spor Salonu','address'=>'Yalova','latitude'=>null,'longitude'=>null,'maps_url'=>'','location_qr_attachment_id'=>null);}
}
class MDG_DB { static function table($v){return 'wp_mdg_'.$v;} static function now(){return '2026-10-13 18:00:00';} }
class MDG_Sessions {
 static function replace_draft_structure($id,$sessions,$tickets){$GLOBALS['structure']=array($sessions,$tickets);return true;}
}
class MMC_MDG_Bridge_Service {
 static function legacy_available(){return true;}
 static function bridge_for_program($id){return null;}
 static function link(...$args){return true;}
}
class DraftDb {
 public $insert_id=901,$writes=0,$row;
 function prepare($sql,...$args){return $sql;}
 function get_row($sql){return null;}
 function insert($table,$data){$this->writes++;$this->row=$data;return 1;}
 function query($sql){return 1;}
}
require dirname(__DIR__,3).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-mdg-draft-sync-service.php';
function check($v,$why){if(!$v)throw new RuntimeException($why);}
foreach(array('Milano Sirki','Madagaskar Sirki','','both') as $brand){
 $wpdb=new DraftDb;
 $family=array('label'=>'Mevcut aile','price'=>1100,'events'=>array(42=>1),'event_prices'=>array(42=>1300));
 $before=serialize($family);$option_writes=0;$structure=null;
 $result=MMC_MDG_Draft_Sync_Service::sync(14);
 if(''===$brand || 'both'===$brand){
  check(is_wp_error($result) && $result->code==='mmc_mdg_brand_required','Invalid brand must stop');
  check($wpdb->writes===0 && $structure===null && serialize($family)===$before,'Invalid brand mutated draft/options');
  continue;
 }
 check($result===901 && $wpdb->row['title']===$brand.' — Yalova','Correct canonical draft title');
 check(count($structure[1])===2,'Family must not become real third MDG type');
 check($structure[1][0]['capacity_units']===1 && $structure[1][1]['capacity_units']===1,'Only real single-person types');
 check($structure[0][0]['start_at']==='2026-10-22 15:00:00' && $structure[0][1]['start_at']==='2026-10-22 16:30:00','Local session conversion');
 check($structure[0][0]['capacity_total']===500 && $structure[0][1]['capacity_total']===500,'Independent capacity');
 if('Milano Sirki'===$brand)check($option_writes===0 && serialize($family)===$before,'Milano leaked into Madagaskar family allowlist');
 else check($option_writes===1 && $family['events'][901]===1 && $family['event_prices'][901]===1100.0 && $family['event_prices'][42]===1300,'Madagaskar family compatibility');
}
echo "PASS: real draft sync brand rejection, two tickets, UTC/capacity, Milano family isolation and Madagaskar preservation\n";
