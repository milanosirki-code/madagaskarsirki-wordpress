<?php
// Synthetic WooCommerce reads; no order changes or payment operations.
define('ABSPATH',__DIR__);
function get_option($k,$default=array()){return array('refund_event_ids'=>array(900));}
function absint($n){return abs((int)$n);}
function wc_get_is_paid_statuses(){return array('processing','completed');}
function wc_get_order($id){global $orders,$reads;$reads[$id]=($reads[$id]??0)+1;return $orders[$id]??false;}
class MDG_DB{static function table($name){return 'wp_mdg_'.$name;}}
class FinanceDB{
 public $rows;
 function prepare($sql,$ids){if(strpos($sql,'order_status')!==false)throw new Exception('Stale status filter');return $sql;}
 function get_results($sql){return $this->rows;}
}
class FinanceItem{public $total;function __construct($v){$this->total=$v;}function get_total(){return $this->total;}}
class FinanceOrder{
 public $status,$refunds,$paid; public $items=array();public $total=null;
 function get_items($type='line_item'){return $this->items;}
 function get_total(){return $this->total??array_sum(array_map(function($i){return $i->get_total();},$this->items));}
 function get_total_tax(){return 0;}
 function get_shipping_total(){return 0;}
 function __construct($status,$refunds=array(),$paid=true){$this->status=$status;$this->refunds=$refunds;$this->paid=$paid;}
 function get_status(){return $this->status;}
 function get_date_paid(){return $this->paid?'2026-10-01':null;}
 function get_item($id){return $this->items[$id]??false;}
 function get_total_refunded_for_item($id){return $this->refunds[$id]??0;}
}
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
$wpdb=new FinanceDB();$reads=array();
$orders=array(1=>new FinanceOrder('refunded'),2=>new FinanceOrder('processing',array(21=>-25)),3=>new FinanceOrder('failed'),4=>new FinanceOrder('completed',array(41=>200)),5=>new FinanceOrder('pending'),6=>new FinanceOrder('processing',array(),false));
$wpdb->rows=array();
foreach(array(array(900,1,11,100),array(701,2,21,100),array(702,2,22,200),array(701,3,31,300),array(701,4,41,100),array(701,5,51,100),array(701,6,61,100),array(701,77,771,100),array(701,2,999,100)) as $v)$wpdb->rows[]=(object)array_combine(array('event_id','order_id','order_item_id','line_total'),$v);
foreach($wpdb->rows as $r)if(isset($orders[$r->order_id])&&$r->order_item_id!==999)$orders[$r->order_id]->items[$r->order_item_id]=new FinanceItem($r->line_total);
$before=serialize(array($wpdb->rows,$orders));
$m=new ReflectionMethod('MDG_V5_Finance','web_revenues_for_events');$m->setAccessible(true);
$out=$m->invoke(null,array(701,702,900));
if(($out[701]??0)!==75.0||($out[702]??0)!==200.0||($out[900]??0)!==0)throw new Exception('Refund/status/program allocation mismatch');
if($reads[2]!==1)throw new Exception('Order must be read once per aggregation');
if(serialize(array($wpdb->rows,$orders))!==$before)throw new Exception('Input mutated');
$p=new ReflectionProperty('MDG_V5_Finance','web_unverified');$p->setAccessible(true);if(!$p->getValue())throw new Exception('Missing order/item warning');
echo "Finance current status and item refund contracts passed\n";

$source=file_get_contents(__DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php');
if(strpos($source,'$month_events=self::month_events(self::events(),$month);')===false)throw new Exception('Compatibility patcher would overwrite current source');

$m=new ReflectionMethod('MDG_V5_Finance','is_ticket_refund');$m->setAccessible(true);
foreach(array('İade / WooCommerce','İade / Biletinial') as $category)if(!$m->invoke(null,(object)array('scope'=>'program','event_id'=>900,'category'=>$category)))throw new Exception('Refund cash double-counted');
foreach(array('Salon / Kira','İade / Depozito','Kantin / Avans') as $category)if($m->invoke(null,(object)array('scope'=>'program','event_id'=>900,'category'=>$category)))throw new Exception('Real operating expense excluded');
$cost=0;$cash=0;foreach(array(array('İade / WooCommerce',100),array('İade / Biletinial',200),array('Salon / Kira',400)) as $v){$r=(object)array('scope'=>'program','event_id'=>900,'category'=>$v[0],'amount'=>$v[1]);if($m->invoke(null,$r))$cash+=$r->amount;else $cost+=$r->amount;}
if($cash!==300||$cost!==400)throw new Exception('Confirmed cancellation cash vs cost mismatch');

$m=new ReflectionMethod('MDG_V5_Finance','order_item_revenues');$m->setAccessible(true);
$o=new FinanceOrder('processing');$o->items=array(1=>new FinanceItem(1000),2=>new FinanceItem(500));$o->total=1100;
$parts=$m->invoke(null,$o);
if(array_sum($parts)!==1100.0||$parts[1]!==733.33||$parts[2]!==366.67)throw new Exception('Family fee or cent conservation failed');
$o->total=0;if(array_sum($m->invoke(null,$o))!==0.0)throw new Exception('Free order');
echo "Family discount and multi-program cent conservation passed\n";
