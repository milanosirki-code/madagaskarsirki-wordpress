<?php
define('ABSPATH',__DIR__);
function add_action($a,$b){}
class Item {
 public $m,$qty,$total,$tax;
 function __construct($code,$role,$qty,$total,$tax=0){$this->m=array('_mdg_campaign_code'=>$code,'_mdg_campaign_role'=>$role,'_mdg_session_id'=>99);$this->qty=$qty;$this->total=$total;$this->tax=$tax;}
 function get_meta($key){return $this->m[$key]??'';}
 function get_product(){return null;}
 function get_quantity(){return $this->qty;}
 function get_total(){return $this->total;}
 function get_total_tax(){return $this->tax;}
 function get_taxes(){return array('total'=>$this->tax?array(1=>$this->tax):array());}
}
class Order {
 public $items,$paid=true,$refund=array(),$qtyrefund=array(),$taxrefund=array();
 function get_items($t){return $this->items;}
 function is_paid(){return $this->paid;}
 function get_date_paid(){return null;}
 function get_qty_refunded_for_item($id){return $this->qtyrefund[$id]??0;}
 function get_total_refunded_for_item($id){return $this->refund[$id]??0;}
 function get_tax_refunded_for_item($id,$rate){return $this->taxrefund[$id]??0;}
}
require __DIR__.'/../../docs/code-snippets/mdg-campaign-sales-report.php';
$c='MDG_Campaign_Sales_Report_20261005';$o=new Order();
$o->items=array(1=>new Item('bms','adult',1,500),2=>new Item('bms','free_child',2,0),3=>new Item('bms','paid_child',1,250),4=>new Item('','adult',1,900),5=>new Item('sagliksendenizli','adult',1,500));
function check($v,$name){if(!$v)throw new Exception($name);echo "PASS $name\n";}
$g=$c::summarize($o,'bms');check(count($g)===1 && $g[0]['gross']===750.0 && $g[0]['net']===750.0,'campaign-only totals exclude ordinary and other code');
check($g[0]['roles']===array('adult'=>1,'paid_child'=>1,'free_child'=>2,'infant'=>0),'role quantities include free tickets');
$o->paid=false;check($c::summarize($o,'bms')[0]['net']===0.0,'unpaid and failed excluded from receipts');
$o->paid=true;$o->refund[3]=250;$o->qtyrefund[3]=-1;
$g=$c::summarize($o,'bms')[0];check($g['net']===500.0 && $g['refund']===250.0 && $g['refunded']===1,'partial refund separated and deducted');
$o->items[1]->tax=50;$o->refund[1]=500;$o->taxrefund[1]=50;
check($c::summarize($o,'bms')[0]['net']===0.0,'full line refunds including tax deducted');
check(count($c::summarize($o))===2,'multiple institution codes separated');
$o->items[6]=new Item('bms','infant',1,0);check($c::summarize($o,'bms')[0]['roles']['infant']===1,'infants shown separately');
$o->items[7]=new Item('bms','adult',1,500);$o->items[7]->m['_mdg_session_id']=100;
check(count($c::summarize($o,'bms'))===2,'different sessions separated');
echo "All 8 campaign sales report checks passed.\n";
