<?php
define('ABSPATH',__DIR__);
class WP_Error { public $message; public function __construct($code,$message){$this->message=$message;} }
function absint($v){return abs((int)$v);}
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$default=array()){return $GLOBALS['maps'] ?? $default;}
function current_time($f){return '2026-10-08 10:00:00';}
class MDG_DB {static function table($t){return 'wp_mdg_'.$t;}}
class MDG_V5_Finance {}
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v2-v3/includes/class-mdgy-meta-expenses.php';
function check($ok,$label){if(!$ok)throw new Exception($label);}
$a=MDGY_Meta_Expenses::allocations(array(array('event_id'=>1,'percent'=>'33.33'),array('event_id'=>2,'percent'=>'66.67')));
check($a===array(1=>3333,2=>6667),'percent validation');
foreach(array(0,0.01,1.01,750,36728.60)as$v)check(abs(array_sum(MDGY_Meta_Expenses::split($v,$a))-$v)<0.001,'conserve total');
foreach(array(array(array('event_id'=>1,'percent'=>99)),array(array('event_id'=>1,'percent'=>50),array('event_id'=>1,'percent'=>50)),array(array('event_id'=>1,'percent'=>'100.001')))as$bad)check(is_wp_error(MDGY_Meta_Expenses::allocations($bad)),'reject invalid allocation');
class DB {
 public $prefix='wp_'; public $entries=array();public $fail=false;public $locked=false;public $releases=0;public $currency='TRY';public $spend=750;
 function prepare($sql,...$args){return array($sql,$args);}
 function get_var($q){if(strpos($q[0],'RELEASE_LOCK')!==false){$this->releases++;return 1;}return $this->locked?0:1;}
 function get_results($q){return array((object)array('raw_json'=>json_encode(array('account_currency'=>$this->currency)),'metric_date'=>'2026-10-08','spend'=>$this->spend,'campaign_name'=>'Test'));}
 function get_row($q){if(strpos($q[0],'mdg_events')!==false)return(object)array('id'=>$q[1][0],'province_name'=>'Manisa');foreach($this->entries as$e)if($e['document_no']===$q[1][0])return(object)$e;return null;}
 function insert($t,$data){if($this->fail)return false;$data['id']=count($this->entries)+1;$this->entries[$data['id']]=$data;return 1;}
 function update($t,$data,$where){if($this->fail)return false;$this->entries[$where['id']]=array_merge($this->entries[$where['id']],$data);return 1;}
}
$wpdb=new DB();$GLOBALS['maps']=array(array('account'=>'act_1','campaign'=>'99','start'=>'2026-10-08','allocations'=>array(1=>10000)));
check(MDGY_Meta_Expenses::sync()===1,'initial insert');check(count($wpdb->entries)===1,'one expense');check($wpdb->entries[1]['payment_method']==='Ödenmedi','not marked paid');
$wpdb->spend=900;MDGY_Meta_Expenses::sync();check(count($wpdb->entries)===1&&(float)$wpdb->entries[1]['amount']===900.0,'update without duplicate');
$wpdb->entries[1]['payment_method']='Şirket Kartı';$wpdb->spend=950;MDGY_Meta_Expenses::sync();check($wpdb->entries[1]['payment_method']==='Şirket Kartı','preserve payment');
$wpdb->spend=0;MDGY_Meta_Expenses::sync();check((float)$wpdb->entries[1]['amount']===0.0,'downward adjustment');
$wpdb->currency='USD';$wpdb->spend=100;check(is_wp_error(MDGY_Meta_Expenses::sync()),'reject foreign currency');
$wpdb->currency='TRY';$wpdb->fail=true;$before=$wpdb->releases;check(is_wp_error(MDGY_Meta_Expenses::sync())&&$wpdb->releases===$before+1,'failure releases lock');
$wpdb->locked=true;check(is_wp_error(MDGY_Meta_Expenses::sync()),'concurrent sync rejected');
$wpdb->locked=false;$GLOBALS['maps'][0]['paused']=true;check(MDGY_Meta_Expenses::sync()===0,'paused campaign');
echo "Meta expense contracts passed\n";
