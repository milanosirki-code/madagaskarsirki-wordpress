<?php
// Synthetic legacy snapshots and legitimate non-web revenue; no production writes.
define('ABSPATH', __DIR__);
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
$m = new ReflectionMethod('MDG_V5_Finance', 'is_web_snapshot');
$m->setAccessible(true);
$rows = array(
 (object)array('channel'=>'WooCommerce','net_amount'=>420,'collection_status'=>'collected'),
 (object)array('channel'=>'Biletinial','net_amount'=>200,'collection_status'=>'collected'),
 (object)array('channel'=>'Gişe','net_amount'=>300,'collection_status'=>'collected'),
 (object)array('channel'=>'Kantin','net_amount'=>80,'collection_status'=>'collected'),
 (object)array('channel'=>'Biletinial','net_amount'=>50,'collection_status'=>'pending')
);
$before=serialize($rows);$manual=0;$pending=0;
foreach($rows as $r){if($m->invoke(null,$r))continue;if($r->collection_status==='collected')$manual+=$r->net_amount;else $pending+=$r->net_amount;}
if(420+$manual!==1000 || $pending!==50 || serialize($rows)!==$before)throw new Exception('Web counted twice, other income lost, or input mutated');
foreach(array('WooCommerce',' woocommerce ','WOOCOMMERCE') as $v)if(!$m->invoke(null,(object)array('channel'=>$v)))throw new Exception('Legacy channel normalization');
foreach(array('Banka/POS','PayTR','Web tasarım','Kurumsal', '') as $v)if($m->invoke(null,(object)array('channel'=>$v)))throw new Exception('Unproven channel exclusion');
echo "Finance web snapshot contracts passed\n";
