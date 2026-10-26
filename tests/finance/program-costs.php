<?php
define('ABSPATH',__DIR__);define('OBJECT_K','OBJECT_K');
function absint($v){return abs((int)$v);}
function get_option($k,$default=array()){
 if($k===MDG_V5_Finance::COUNT_OPTION)return $GLOBALS['counts'];
 if($k===MDG_V5_Finance::ALLOCATION_OPTION)return array(1=>array('amount_cents'=>2500,'weights'=>array(1=>5000,2=>5000)));
 return $default;
}
class MDG_DB{static function table($v){return 'wp_mdg_'.$v;}}
class CostDB{
 public $prefix='wp_';
 function prepare($sql,...$args){return $sql.' '.implode(' ',$args);}
 function get_results($sql,$mode=null){
  if(strpos($sql,'wp_mdg_events')!==false){$out=array();foreach(array(array(1,'2026-10-01','onsale'),array(2,'2026-10-02','onsale'),array(3,'2026-10-03','cancelled'),array(4,'2026-11-01','onsale')) as $v)$out[]=(object)array('id'=>$v[0],'event_date'=>$v[1],'status'=>$v[2]);return $out;}
  if(strpos($sql,'fixed_costs')!==false)return array((object)array('total_amount'=>strpos($sql,'2026-11')!==false?200:100));
  if(strpos($sql,'attendance')!==false)return array();
  if(strpos($sql,'expenses')!==false)return array((object)array('id'=>1,'scope'=>'general','category'=>'Shared','event_id'=>0,'amount'=>25));
  throw new Exception('Unexpected read');
 }
}
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
$wpdb=new CostDB();$counts=array();
$a=MDG_V5_Finance::cost_shares(1);$b=MDG_V5_Finance::cost_shares(2);
if($a['fixed_share']!==50.0||$a['common']!==12.5||$a['fixed_share']+$b['fixed_share']!==100.0)throw new Exception('Single program charged full fixed costs or lost common share');
if(MDG_V5_Finance::cost_shares(3)['fixed_share']!==0.0)throw new Exception('Cancelled program charged fixed share');
if(MDG_V5_Finance::cost_shares(4)['fixed_share']!==200.0)throw new Exception('Wrong fixed-cost month');
$counts=array('2026-10'=>4);if(MDG_V5_Finance::cost_shares(1)['fixed_share']!==25.0)throw new Exception('Manual allocation denominator ignored');
echo "Program fixed-month/share and common-allocation contracts passed\n";
