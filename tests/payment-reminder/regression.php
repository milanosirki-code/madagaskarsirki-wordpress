<?php
// Synthetic only. All data writes and remote sending are counted/refused.
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);
function absint($v){return abs((int)$v);}
function add_action(...$args){} function apply_filters($tag,$value,...$args){return $value;}
$meta=array();$markers=array();$logs=array();$sends=0;$domain_writes=0;
function get_post_meta($id,$key,$single=true){global $meta;return $meta[$id][$key]??'';}
function get_transient($key){global $markers;return $markers[$key]??false;}
function set_transient($key,$value,$ttl){global $markers;$markers[$key]=$value;return true;}
function wc_get_logger(){return new class{function info($message,$context){global $logs;$logs[]=array($message,$context);}};}
function wp_remote_post(...$args){global $sends;$sends++;throw new RuntimeException('Sending forbidden');}
function wp_remote_request(...$args){global $sends;$sends++;throw new RuntimeException('Sending forbidden');}
function wp_next_scheduled(...$args){return false;} function wp_schedule_single_event(...$args){}
class MDG_DB{static function table($name){return 'wp_mdg_'.$name;}}
class MDG_Events{static $events=array();static function get($id){return self::$events[$id]??null;}}
class MMC_Program_Service{static $rows=array();static function get_program($id){return self::$rows[$id]??null;}}
class MMC_Event_Service{static $rows=array();static function get_event($id){return self::$rows[$id]??null;}}
class FixtureProduct{
 public $available=true;public $stock=true;public $status='publish';
 function get_status(){return $this->status;}function is_purchasable(){return $this->available;}function is_in_stock(){return $this->stock;}
}
$products=array();$orders=array();$paid_orders=array();$paid_query_error=false;
function wc_get_product($id){global $products;return $products[$id]??false;}
function wc_get_order($id){global $orders;return $orders[$id]??false;}
function wc_get_is_paid_statuses(){return array('processing','completed');}
function wc_get_orders($args){global $paid_orders,$paid_query_error;return $paid_query_error?false:array_slice($paid_orders,($args['page']-1)*$args['limit'],$args['limit']);}
class FixtureItem{
 public $pid;public $vid;public $id;
 function __construct($pid,$vid,$id=1){$this->pid=$pid;$this->vid=$vid;$this->id=$id;}
 function get_product_id(){return $this->pid;}function get_variation_id(){return $this->vid;}function get_id(){return $this->id;}
}
class FixtureOrder{
 public $id;public $items;public $phone='05000000000';public $status='failed';public $meta=array();public $created;public $modified;public $via='checkout';
 function __construct($id,$pid=101,$vid=201){$this->id=$id;$this->items=array(new FixtureItem($pid,$vid));$this->created=time()-4000;$this->modified=time()-3000;}
 function get_id(){return $this->id;}function get_items($type='line_item'){return $this->items;}function get_billing_phone(){return $this->phone;}
 function get_status(){return $this->status;}function is_paid(){return in_array($this->status,array('processing','completed'),true);}
 function get_meta($key,$single=true){return $this->meta[$key]??'';}function get_date_created(){return $this->created===null?null:new DateTimeImmutable('@'.$this->created);}
 function get_date_modified(){return new DateTimeImmutable('@'.$this->modified);}function get_created_via(){return $this->via;}function get_type(){return 'shop_order';}
 function save(){global $domain_writes;$domain_writes++;throw new RuntimeException('Domain write forbidden');}
}
class FixtureDB{
 public $prefix='wp_';public $last_error='';public $mappings=array();public $sessions=array();public $saved=array();
 function prepare($sql,...$args){return preg_replace_callback('/%[ds]/',function()use(&$args){return (string)array_shift($args);},$sql);}
 function get_results($sql){
  if(strpos($sql,'mmc_sales_mappings')!==false){preg_match('/wc_product_id=(\d+)/',$sql,$m);return $this->mappings[(int)($m[1]??0)]??array();}
  if(strpos($sql,'mdg_ticket_types')!==false){preg_match('/t.wc_variation_id=(\d+)/',$sql,$m);return $this->sessions[(int)($m[1]??0)]??array();}
  if(strpos($sql,'mdg_order_map')!==false){preg_match('/m.order_id=(\d+)/',$sql,$m);return $this->saved[(int)($m[1]??0)]??array();}
  throw new RuntimeException('Unexpected SQL');
 }
 function query($sql){global $domain_writes;$domain_writes++;throw new RuntimeException('SQL write forbidden');}
}
$wpdb=new FixtureDB();
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-sessions.php';
require dirname(__DIR__,2).'/docs/code-snippets/ms-incomplete-payment-dryrun.php';
$checks=0;$check=function($name,$actual,$expected)use(&$checks){if($actual!==$expected)throw new RuntimeException($name.': '.json_encode(array($actual,$expected)));$checks++;};
$reset=function()use(&$meta,&$markers,&$paid_orders,&$paid_query_error,&$products,$wpdb){
 $meta=array();$markers=array();$paid_orders=array();$paid_query_error=false;$wpdb->last_error='';$wpdb->saved=array();
 MMC_Program_Service::$rows=array(1=>(object)array('status'=>'onsale'),2=>(object)array('status'=>'cancelled'));
 MMC_Event_Service::$rows=array(1=>(object)array('status'=>'onsale'),2=>(object)array('status'=>'onsale'));
 MDG_Events::$events=array(7=>(object)array('id'=>7,'status'=>'onsale'),8=>(object)array('id'=>8,'status'=>'onsale'));
 $wpdb->mappings=array(101=>array((object)array('program_id'=>1,'event_id'=>1)),102=>array((object)array('program_id'=>1,'event_id'=>1)));
 $wpdb->sessions=array(201=>array((object)array('id'=>11,'event_id'=>7,'wc_product_id'=>101,'start_at'=>gmdate('Y-m-d H:i:s',time()+7200),'end_at'=>gmdate('Y-m-d H:i:s',time()+10800),'status'=>'onsale','ticket_active'=>1)),202=>array((object)array('id'=>12,'event_id'=>8,'wc_product_id'=>102,'start_at'=>gmdate('Y-m-d H:i:s',time()+7200),'end_at'=>gmdate('Y-m-d H:i:s',time()+10800),'status'=>'onsale','ticket_active'=>1)));
 $products=array(201=>new FixtureProduct(),202=>new FixtureProduct());
};
$reset();$order=new FixtureOrder(1001);$report=ms_oh_dry_run_report($order);$check('Future failed order eligible',$report['eligibility'],'ELIGIBLE');
$check('Masked phone only',$report['phone_masked'],'***0000');$check('No raw phone in report',strpos(json_encode($report),$order->phone),false);
$check('TR formats equivalent',ms_oh_telefon('+90 (500) 000 00 00'),ms_oh_telefon('05000000000'));
$check('00 country prefix equivalent',ms_oh_telefon('00905000000000'),ms_oh_telefon('5000000000'));
$check('Foreign country suffix not equal',ms_oh_telefon('+15000000000')===ms_oh_telefon('05000000000'),false);
foreach(array(3934,3924,3907,3836,3820,3467)as$id){$reset();$wpdb->mappings[101]=array((object)array('program_id'=>2,'event_id'=>2));$r=ms_oh_dry_run_report(new FixtureOrder($id));$check('Cancelled fixture'.$id,$r['exclusion_reason'],'EVENT_CANCELLED');$check('Program2 reason'.$id,strpos($r['reason'],'MMC programı #2')!==false,true);}
$reset();MMC_Program_Service::$rows[1]->status='sales_closed';$check('MMC sales closed',ms_oh_dry_run_report($order)['exclusion_reason'],'SALES_CLOSED');
$reset();$meta[101]['_mdg_v371_sales_closed']='yes';$check('V4 parent closed',ms_oh_dry_run_report($order)['exclusion_reason'],'SALES_CLOSED');
$reset();$meta[201]['_mdg_v371_sales_closed']='yes';$check('V4 variation closed',ms_oh_dry_run_report($order)['exclusion_reason'],'SALES_CLOSED');
$reset();$wpdb->sessions[201][0]->start_at=gmdate('Y-m-d H:i:s',time());$check('Exact start boundary',ms_oh_dry_run_report($order)['exclusion_reason'],'SESSION_STARTED');
$reset();$wpdb->sessions[201][0]->start_at=gmdate('Y-m-d H:i:s',time()-600);$check('Running session excluded',ms_oh_dry_run_report($order)['exclusion_reason'],'SESSION_STARTED');
$reset();$paid=new FixtureOrder(1002);$paid->created=$order->created+60;$paid->status='processing';$paid->phone='+905000000000';$paid_orders=array($paid);$r=ms_oh_dry_run_report($order);$check('Paid processing replacement',$r['exclusion_reason'],'REPLACEMENT_PAID');$check('Replacement flag',$r['replacement_paid'],true);
$paid->status='completed';$check('Paid completed replacement',ms_oh_dry_run_report($order)['exclusion_reason'],'REPLACEMENT_PAID');
$reset();$paid=new FixtureOrder(1002,102,202);$paid->created=$order->created+60;$paid->status='completed';$paid_orders=array($paid);$check('Same phone different event does not suppress',ms_oh_dry_run_report($order)['eligibility'],'ELIGIBLE');
$reset();$paid=new FixtureOrder(1002);$paid->created=$order->created+60;$paid->status='completed';$paid->phone='+15000000000';$paid_orders=array($paid);$check('Different country same suffix does not suppress',ms_oh_dry_run_report($order)['eligibility'],'ELIGIBLE');
$reset();$paid=new FixtureOrder(1002);$paid->created=$order->created-60;$paid->status='completed';$paid_orders=array($paid);$check('Earlier unrelated purchase does not suppress',ms_oh_dry_run_report($order)['eligibility'],'ELIGIBLE');
$reset();$order->meta['_ms_oh_reminder_sent_at']='2026-10-01T00:00:00Z';$check('Already reminded',ms_oh_dry_run_report($order)['exclusion_reason'],'ALREADY_REMINDED');$order->meta=array();
$markers['ms_oh_yazildi_1001']='gonderilirdi';$r=ms_oh_dry_run_report($order);$check('Dryrun already recorded',$r['exclusion_reason'],'ALREADY_EVALUATED');$check('Dryrun marker is not proof of sending',$r['already_reminded'],false);
$reset();$wpdb->sessions[201]=array();$check('Missing mapping fails closed',ms_oh_dry_run_report($order)['exclusion_reason'],'MAPPING_MISSING');
$reset();$wpdb->saved[1001]=$wpdb->sessions[202];$check('Ambiguous mapping fails closed',ms_oh_dry_run_report($order)['exclusion_reason'],'MAPPING_MISSING');
$reset();$wpdb->sessions[201][0]->wc_product_id=102;$check('Wrong parent mapping fails closed',ms_oh_dry_run_report($order)['exclusion_reason'],'MAPPING_MISSING');
$reset();$products[201]->available=false;$check('Unavailable product excluded',ms_oh_dry_run_report($order)['exclusion_reason'],'PRODUCT_UNAVAILABLE');
$reset();$paid_query_error=true;$check('Replacement query failure fails closed',ms_oh_dry_run_report($order)['exclusion_reason'],'REPLACEMENT_CHECK_FAILED');
$reset();for($i=0;$i<100;$i++){$p=new FixtureOrder(2000+$i,102,202);$p->status='completed';$p->created=$order->created+60;$paid_orders[]=$p;}$p=new FixtureOrder(3001);$p->status='processing';$p->created=$order->created+60;$paid_orders[]=$p;$check('Replacement beyond first page found',ms_oh_dry_run_report($order)['exclusion_reason'],'REPLACEMENT_PAID');
$reset();$orders[1001]=$order;$logs=array();ms_oh_kontrol(1001);ms_oh_kontrol(1001);$check('Repeated callback logs once',count($logs),1);$check('Repeated callback marker',$markers['ms_oh_yazildi_1001'],'gonderilirdi');
$check('No domain writes',$domain_writes,0);$check('Actual sends',$sends,0);
echo $checks." payment reminder checks passed; domain writes0; sends0\n";
