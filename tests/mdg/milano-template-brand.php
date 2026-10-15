<?php
define('ABSPATH',__DIR__);
function absint($v){return abs((int)$v);}
function get_post_meta($id,$key,$single=true){return $key==='tc_designer_template_id' ? 6 : '';}
class MDG_Events {static function get($id){return (object)['id'=>$id,'title'=>'Milano Sirki — Kocaeli / Karamürsel','venue_id'=>196,'venue_name'=>'Atatürk','venue_maps_url'=>'https://example.test/maps','venue_qr_attachment_id'=>6112];}}
class MDG_QR {static function attachment_url($id){return 'https://example.test/qr.png';}}
class MDG_Sessions {
 static function by_event($id){return [(object)['id'=>152,'start_at'=>'2026-10-21 14:30:00','wc_product_id'=>6081]];}
 static function ticket_types_by_session($id){return [(object)['wc_variation_id'=>6082],(object)['wc_variation_id'=>6083]];}
}
class MDG_New_Event_Production_Plan {static function build($e){return ['template'=>['base_template_id'=>$GLOBALS['planId']]];}}
class FakeDB {
 public $prefix='wp_';
 function prepare($sql,...$args){return vsprintf(str_replace('%d','%s',$sql),$args);}
 function get_var($sql){
  if(strpos($sql,'SELECT name')!==false){return $GLOBALS['names'][$GLOBALS['planId']]??'';}
  if(strpos($sql,'SELECT template_data')!==false){return json_encode(['width'=>595,'height'=>420,'elements'=>[['id'=>'venue-qr','type'=>'image','x'=>450,'y'=>300,'width'=>75,'height'=>75,'src'=>'https://example.test/venue-qr.png']]]);}
  throw new Exception('Unexpected query '.$sql);
 }
}
$wpdb=new FakeDB();$names=[28=>'Milano Sirki A5 - Yalova 2026 staging base',6=>'Madagaskar Sirki A5 yatay'];$planId=28;
require __DIR__.'/../../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-ticket-template-qr-binder.php';
$count=0;
function check($ok,$name){global $count;if(!$ok){fwrite(STDERR,"FAIL $name\n");exit(1);}$count++;}
$r=MDG_Ticket_Template_QR_Binder::analyze_event(25);
check($r['ready'] && $r['base_template_id']===28,'Milano plan overrides stale variation Madagaskar template');
check($r['candidate']['id']==='venue-qr','venue QR candidate retained');
$planId=6;$r=MDG_Ticket_Template_QR_Binder::analyze_event(25);
check(!$r['ready'] && !$r['base_template_id'],'wrong brand plan fails closed');
$planId=0;$r=MDG_Ticket_Template_QR_Binder::analyze_event(25);
check(!$r['ready'] && !$r['base_template_id'],'missing plan fails closed');
echo "PASS $count Milano QR template brand checks\n";
