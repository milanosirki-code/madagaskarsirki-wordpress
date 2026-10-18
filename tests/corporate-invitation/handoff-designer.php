<?php
namespace Tickera {
function tickera_ticket_designer_selected_template_value($id,$key){return 'd_'.\get_post_meta($id,'tc_designer_template_id',true);}
}
namespace {
define('ABSPATH',__DIR__);$meta=array();$status='publish';$designStatus='active';$stock=true;$price=245;$brand='Milano Sirki';
function add_action(...$a){} function home_url($p='/'){return 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com'.$p;}
function wp_parse_url($u,$p){return parse_url($u,$p);} function current_user_can($c){return true;} function current_time(...$a){return '2026-10-18 10:00:00';}
function get_post_meta($id,$k,$single=true){global $meta;return $meta[$id][$k]??'';}
function get_post_status($id){global $status;return $status;}
function get_post($id){global $status;return (object)array('post_type'=>'tc_events','post_status'=>$status);}
function get_option($k,$d=null){return $d;}
class WP_Error {function __construct(public $code,public $message){}}
function is_wp_error($v){return $v instanceof WP_Error;}
class MDG_DB {} class MDG_Events {static function get($id){return (object)array('title'=>'Milano Sirki — Test','status'=>'onsale');}}
class MDG_Capacity {static function available($id){return 500;}}
class MDG_Sessions {static function sales_closed_by_time($s){return false;}static function local_parts($s){return array('2026-10-21','17:30');}}
class Product {function __construct(public $id){} function is_type($t){return $t===($this->id===11?'variable':'variation');}function get_id(){return $this->id;}function get_parent_id(){return 11;}function get_price(){global $price;return $this->id===13?$price:490;}function is_purchasable(){return true;}function is_in_stock(){global $stock;return $stock;}}
function wc_get_product($id){return new Product($id);}
class DB {public $prefix='wp_';public $last_error='';function prepare($q,...$a){return $q;}function get_row($q){global $designStatus;if(str_contains($q,'tickera_ticket_templates'))return (object)array('name'=>'Milano','status'=>$designStatus);return (object)array('id'=>152,'event_id'=>25,'status'=>'onsale','start_at'=>'2026-10-21 14:30:00','wc_product_id'=>11,'tickera_event_id'=>12);}function get_results($q){global $brand;if(str_contains($q,'mmc_mdg_event_bridge'))return array((object)array('brand'=>$brand,'mmc_event_id'=>15));if(str_contains($q,'mdg_ticket_types'))return array((object)array('code'=>'CHILD','capacity_units'=>1,'wc_variation_id'=>13,'price'=>245),(object)array('code'=>'ADULT','capacity_units'=>1,'wc_variation_id'=>14,'price'=>490));return array();}}
$wpdb=new DB();foreach(array(11,13,14) as $id)$meta[$id]=array('tc_designer_template_id'=>31,'_ticket_template'=>0,'_allow_ticket_checkout'=>'no');
$meta[11]+=array('_tc_is_ticket'=>'yes','_mdg_event_id'=>25,'_mdg_session_id'=>152,'_mdg_production_key'=>'fixture');$meta[12]=array('_mdg_event_id'=>25,'_mdg_production_key'=>'fixture');
require __DIR__.'/../../wp-content/plugins/milano-shared-checkout-handoff/milano-shared-checkout-handoff.php';
$n=0;function check($v,$name){global $n;if(!$v)throw new \Exception('FAIL '.$name);$n++;echo "PASS $name\n";}
check(!is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'native Designer storage accepted with exit scan disabled');
$meta[13]['tc_designer_template_id']=32;check(is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'wrong child Designer rejected');$meta[13]['tc_designer_template_id']=31;
$status='draft';check(is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'unpublished sales objects rejected');$status='publish';
$designStatus='inactive';check(is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'inactive Designer rejected');$designStatus='active';
$stock=false;check(is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'out of stock rejected');$stock=true;
$price=1;check(is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'changed variation price rejected');$price=245;
$brand='Other';check(is_wp_error(Milano_Shared_Checkout_Handoff::snapshot(25,152)),'other brand rejected');
echo "TOTAL $n assertions\n";
}
