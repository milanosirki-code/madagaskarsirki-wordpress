<?php
define('ABSPATH',__DIR__);
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
$base='https://madagaskarsirki.com';$rows=array();
foreach(array(28=>array('Karamürsel','2026-10-21',array(181=>'17:30',182=>'19:30')),24=>array('Yalova','2026-10-22',array(154=>'18:00',155=>'19:30')),27=>array('Darıca','2026-10-23',array(179=>'17:30',180=>'19:30')),26=>array('Derince','2026-10-24',array(176=>'12:00',177=>'14:00',178=>'16:00'))) as $id=>$e){foreach($e[2] as $sid=>$time){$rows[]=array('event_id'=>$id,'session_id'=>$sid,'title'=>'Milano Sirki — '.$e[0],'date'=>$e[1],'time'=>$time,'children_price'=>245,'adults_price'=>490);}}
echo '<!doctype html><html lang="tr"><meta name="viewport" content="width=device-width,initial-scale=1"><title>İzole kampanya görünüm testi</title><body>';
include __DIR__.'/../../wp-content/plugins/milano-sagliksen/view.php';
echo '</body></html>';
