<?php
define('ABSPATH',__DIR__);
class WP_Error { public $code; function __construct($code,$message){$this->code=$code;} }
function sanitize_text_field($v){return trim((string)$v);}
function sanitize_textarea_field($v){return trim((string)$v);}
function sanitize_key($v){return $v;}
function absint($v){return abs((int)$v);}
function wp_date($v){return '2026';}
function current_time($v){return '2026-10-14 19:00:00';}
function get_current_user_id(){return 1;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function do_action(...$args){}
class Test_DB {
 public $prefix='wp_',$insert_id=0,$rows=array(),$fail=false;
 function prepare($sql,...$args){return $sql;}
 function esc_like($s){return $s;}
 function get_var($sql){return null;}
 function insert($table,$data,$formats=null){
  if($this->fail&&$table==='wp_mmc_programs'){return false;}
  $this->insert_id++;$this->rows[]=array($table,$data);return 1;
 }
}
$wpdb=new Test_DB();
require __DIR__.'/../../wp-content/plugins/madagaskar-management-center/includes/class-mmc-program-service.php';
function ok($v,$msg){if(!$v){throw new Exception($msg);} echo "PASS $msg\n";}
$base=array('province_name'=>'Yalova','district_name'=>'Merkez','planned_date'=>'2026-10-22');
$id=MMC_Program_Service::create_program($base);
ok(is_int($id)&&$wpdb->rows[0][1]['brand']==='Madagaskar Sirki','existing callers retain Madagaskar');
ok(count($wpdb->rows)===5,'one program, one audit, three initial tasks');
$wpdb=new Test_DB();
$id=MMC_Program_Service::create_program($base+array('brand'=>'Milano Sirki'));
ok(is_int($id)&&$wpdb->rows[0][1]['brand']==='Milano Sirki','Milano is stored at initial insertion');
ok(json_decode($wpdb->rows[1][1]['new_value'],true)['brand']==='Milano Sirki','audit records actual brand');
foreach(array('unknown','',null,array('Milano Sirki')) as $bad){
 $wpdb=new Test_DB(); $v=MMC_Program_Service::create_program($base+array('brand'=>$bad));
 // null retains the legacy omitted-value default, matching PHP null coalescing.
 if($bad===null){ok(is_int($v),'null preserves legacy default');}
 else{ok($v instanceof WP_Error&&!$wpdb->rows,'invalid explicit brand creates no records');}
}
$wpdb=new Test_DB();$v=MMC_Program_Service::create_program(array('brand'=>'Milano Sirki'));
ok($v instanceof WP_Error&&!$wpdb->rows,'missing province rejected');
$wpdb=new Test_DB();$wpdb->fail=true;$v=MMC_Program_Service::create_program($base+array('brand'=>'Milano Sirki'));
ok($v instanceof WP_Error&&!$wpdb->rows,'failed insert creates no logs/tasks');
