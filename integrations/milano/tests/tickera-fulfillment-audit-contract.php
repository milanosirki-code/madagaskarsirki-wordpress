<?php
/**
 * Synthetic commercial-line vs physical-admission reconciliation.
 * No personal data, raw QR, WP/PayTR/Tickera operations or external calls.
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/checkout-intent-protocol/milano-ticket-fulfillment-audit.php';
function ensure_fulfill($ok,$msg){if(!$ok){fwrite(STDERR,"FAIL {$msg}\n");exit(1);}}
$case=$argv[1]??'family4';
$quote=array(
 'order_id'=>42, 'source_brand'=>'milano','owner_brand'=>'milano',
 'session_id'=>93,'event_id'=>72,'payment_final'=>true,'mdg_committed'=>true,
 'lines'=>array(array('code'=>'FAMILY4','quantity'=>1,
   'capacity_units_per_ticket_type'=>4,'line_admission_units'=>4,
   'variation_id'=>103,'ticket_type_id'=>501))
);
$woo=array(array('code'=>'FAMILY4','order_id'=>42,'order_item_id'=>610,
    'quantity'=>1,'variation_id'=>103,'mdg_session_id'=>93));
$ticket=function($id,$line=610,$type=501) {
 return array('id'=>$id,'code_hash'=>hash('sha256','synthetic-qr-seed-'.$id),
 'order_id'=>42,'order_item_id'=>$line,'event_id'=>72,
 'ticket_type_id'=>$type,'status'=>'publish');
};
$tickets=array($ticket(9001),$ticket(9002),$ticket(9003),$ticket(9004));
$ok=true;
switch($case){
 case 'family4':break;
 case 'two_families':
    $quote['lines'][0]['quantity']=2;
    $quote['lines'][0]['line_admission_units']=8;
    $woo[0]['quantity']=2;
    for($id=9005;$id<=9008;$id++)$tickets[]=$ticket($id);
    break;
 case 'split_2adults_2children':
    $quote['lines']=array(
       array('code'=>'ADULT','quantity'=>2,'capacity_units_per_ticket_type'=>1,
            'line_admission_units'=>2,'variation_id'=>104,'ticket_type_id'=>502),
       array('code'=>'CHILD','quantity'=>2,'capacity_units_per_ticket_type'=>1,
            'line_admission_units'=>2,'variation_id'=>105,'ticket_type_id'=>503)
    );
    $woo=array(
       array('code'=>'ADULT','order_id'=>42,'order_item_id'=>611,
             'quantity'=>2,'variation_id'=>104,'mdg_session_id'=>93),
       array('code'=>'CHILD','order_id'=>42,'order_item_id'=>612,
             'quantity'=>2,'variation_id'=>105,'mdg_session_id'=>93)
    );
    $tickets=array($ticket(9001,611,502),$ticket(9002,611,502),
                   $ticket(9003,612,503),$ticket(9004,612,503));
    break;
 case 'one_ticket_for_family':$tickets=array($ticket(9001));$ok=false;break;
 case 'three_of_four':array_pop($tickets);$ok=false;break;
 case 'five_of_four':$tickets[]=$ticket(9005);$ok=false;break;
 case 'duplicate_ticket_id':$tickets[1]['id']=9001;$ok=false;break;
 case 'duplicate_qr_hash':$tickets[1]['code_hash']=$tickets[0]['code_hash'];$ok=false;break;
 case 'incorrect_hash':$tickets[1]['code_hash']='xyz';$ok=false;break;
 case 'invalid_ticket_id':$tickets[0]['id']=0;$ok=false;break;
 case 'different_order':$tickets[0]['order_id']=43;$ok=false;break;
 case 'wrong_ticket_item':$tickets[0]['order_item_id']=700;$ok=false;break;
 case 'different_event':$tickets[1]['event_id']=73;$ok=false;break;
 case 'wrong_ticket_type':$tickets[2]['ticket_type_id']=502;$ok=false;break;
 case 'ticket_trashed':$tickets[2]['status']='trash';$ok=false;break;
 case 'brand_leak':$quote['source_brand']='madagaskar';$ok=false;break;
 case 'event_owner_leak':$quote['owner_brand']='madagaskar';$ok=false;break;
 case 'payment_pending':$quote['payment_final']=false;$ok=false;break;
 case 'mdg_hold_not_committed':$quote['mdg_committed']=false;$ok=false;break;
 case 'wrong_order_id':$woo[0]['order_id']=43;$ok=false;break;
 case 'wrong_woo_quantity':$woo[0]['quantity']=4;$ok=false;break;
 case 'wrong_woo_variation':$woo[0]['variation_id']=104;$ok=false;break;
 case 'wrong_woo_session':$woo[0]['mdg_session_id']=92;$ok=false;break;
 case 'extra_woo_line':$woo[]=$woo[0];$ok=false;break;
 case 'missing_woo_line':$woo=array();$ok=false;break;
 case 'wrong_admission_math':$quote['lines'][0]['line_admission_units']=1;$ok=false;break;
 case 'capacity_zero':$quote['lines'][0]['capacity_units_per_ticket_type']=0;$ok=false;break;
 case 'negative_quantity':$quote['lines'][0]['quantity']=-1;$ok=false;break;
 case 'duplicate_quote_line':$quote['lines'][]=$quote['lines'][0];$ok=false;break;
 case 'unknown_order':$quote['order_id']=0;$ok=false;break;
 case 'large_admissions':
    $quote['lines'][0]['quantity']=30;
    $quote['lines'][0]['line_admission_units']=120;
    $ok=false;break;
 case 'untrusted_line_key':$quote['lines'][0]['code']='<script>';$ok=false;break;
 default:ensure_fulfill(false,'unknown scenario '.$case);
}
$result=MCPI_Tickera_Fulfillment_Audit::check($quote,$woo,$tickets);
ensure_fulfill($result['ok']===$ok,$case.' unexpectedly '.json_encode($result));
if($ok) {
    ensure_fulfill($result['admission_units']===count($tickets),'wrong physical admission count');
    ensure_fulfill($result['ticket_instances']===count($tickets),'wrong issued count');
    ensure_fulfill($result['commercial_lines']===count($quote['lines']),'wrong line count');
} else {
    ensure_fulfill(isset($result['reason'])&&!isset($result['qr_code']),'denial needs safe reason');
}
echo "PASS {$case}\n";
