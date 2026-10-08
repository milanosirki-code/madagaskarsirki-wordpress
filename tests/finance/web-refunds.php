<?php
// Synthetic WooCommerce reads; no order changes or payment operations.
define('ABSPATH',__DIR__);
function absint($n){return abs((int)$n);}
function wc_get_is_paid_statuses(){return array('processing','completed');}
function wc_get_order($id){global $orders,$reads;$reads[$id]=($reads[$id]??0)+1;return $orders[$id]??false;}
class MDG_DB{static function table($name){return 'wp_mdg_'.$name;}}
class FinanceDB{
 public $rows;
 function prepare($sql,$ids){if(strpos($sql,'order_status')!==false)throw new Exception('Stale status filter');return $sql;}
 function get_results($sql){return $this->rows;}
}
class FinanceOrder{
 public $status,$refunds,$paid;
 function __construct($status,$refunds=array(),$paid=true){$this->status=$status;$this->refunds=$refunds;$this->paid=$paid;}
 function get_status(){return $this->status;}
 function get_date_paid(){return $this->paid?'2026-10-01':null;}
 function get_item($id){return $id===999?false:(object)array('id'=>$id);}
 function get_total_refunded_for_item($id){return $this->refunds[$id]??0;}
}
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
$wpdb=new FinanceDB();$reads=array();
$orders=array(1=>new FinanceOrder('refunded'),2=>new FinanceOrder('processing',array(21=>-25)),3=>new FinanceOrder('failed'),4=>new FinanceOrder('completed',array(41=>200)),5=>new FinanceOrder('pending'),6=>new FinanceOrder('processing',array(),false));
$wpdb->rows=array();
foreach(array(array(15,1,11,100),array(7,2,21,100),array(8,2,22,200),array(7,3,31,300),array(7,4,41,100),array(7,5,51,100),array(7,6,61,100),array(7,77,771,100),array(7,2,999,100)) as $v)$wpdb->rows[]=(object)array_combine(array('event_id','order_id','order_item_id','line_total'),$v);
$before=serialize(array($wpdb->rows,$orders));
$m=new ReflectionMethod('MDG_V5_Finance','web_revenues_for_events');$m->setAccessible(true);
$out=$m->invoke(null,array(7,8,15));
if(($out[7]??0)!==75.0||($out[8]??0)!==200.0||($out[15]??0)!==0)throw new Exception('Refund/status/program allocation mismatch');
if($reads[2]!==1)throw new Exception('Order must be read once per aggregation');
if(serialize(array($wpdb->rows,$orders))!==$before)throw new Exception('Input mutated');
$p=new ReflectionProperty('MDG_V5_Finance','web_unverified');$p->setAccessible(true);if(!$p->getValue())throw new Exception('Missing order/item warning');
echo "Finance current status and item refund contracts passed\n";

$source=file_get_contents(__DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php');
if(strpos($source,'$month_events=self::month_events(self::events(),$month);')===false)throw new Exception('Compatibility patcher would overwrite current source');

$m=new ReflectionMethod('MDG_V5_Finance','is_ticket_refund');$m->setAccessible(true);
foreach(array('İade / WooCommerce','İade / Biletinial') as $category)if(!$m->invoke(null,(object)array('scope'=>'program','event_id'=>15,'category'=>$category)))throw new Exception('Refund cash double-counted');
foreach(array('Salon / Kira','İade / Depozito','Kantin / Avans') as $category)if($m->invoke(null,(object)array('scope'=>'program','event_id'=>15,'category'=>$category)))throw new Exception('Real operating expense excluded');
$cost=0;$cash=0;foreach(array(array('İade / WooCommerce',13250),array('İade / Biletinial',6250),array('Salon / Kira',5500)) as $v){$r=(object)array('scope'=>'program','event_id'=>15,'category'=>$v[0],'amount'=>$v[1]);if($m->invoke(null,$r))$cash+=$r->amount;else $cost+=$r->amount;}
if($cash!==19500||$cost!==5500)throw new Exception('Confirmed cancellation cash vs cost mismatch');
