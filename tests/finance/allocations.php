<?php
define('ABSPATH', __DIR__);
require __DIR__.'/../../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
$m = new ReflectionMethod('MDG_V5_Finance', 'allocation_shares'); $m->setAccessible(true);
$row = (object)array('id'=>98,'scope'=>'general','category'=>'Matbaa / Tanıtım','event_id'=>0,'amount'=>58000);
$rule = array('amount_cents'=>5800000,'weights'=>array(16=>1000,15=>1000,11=>4000,7=>4000));
$before = serialize($row);
$shares = $m->invoke(null,$row,$rule);
if ($shares[16] !== 5800 || $shares[15] !== 5800 || $shares[11] !== 23200 || $shares[7] !== 23200 || array_sum($shares) !== 58000 || serialize($row) !== $before) throw new Exception('Allocation conservation/input mutation');
$tiny = clone $row; $tiny->amount = 0.01;
$fraction = array('amount_cents'=>1,'weights'=>array(1=>3333,2=>3333,3=>3334));
if (array_sum($m->invoke(null,$tiny,$fraction)) !== 0.01) throw new Exception('Cent lost');
foreach (array(array('amount_cents'=>5200000,'weights'=>$rule['weights']),array('amount_cents'=>5800000,'weights'=>array(1=>4000)),array('amount_cents'=>5800000,'weights'=>array(1=>-1000,2=>11000)),array('amount_cents'=>5800000,'weights'=>array(0=>10000))) as $bad) if ($m->invoke(null,$row,$bad)) throw new Exception('Invalid rule accepted');
$direct=clone $row; $direct->scope='program';
if ($m->invoke(null,$direct,$rule)) throw new Exception('Direct expense allocated twice');
echo "Finance allocation contracts passed\n";
