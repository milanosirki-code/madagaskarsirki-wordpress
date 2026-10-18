<?php
/** Staging-only, administrator-only in-memory renderer. Never saves a template/order/ticket. */
if (!defined('ABSPATH')) { exit; }
if (strpos(home_url(),'staging-f321-milanosirki-lgajf.wpcomstaging.com')===false) { return; }
add_action('rest_api_init',function(){
 $perm=function(){return current_user_can('manage_options');};
 register_rest_route('milano-layout-stage/v1','/render',array('methods'=>'POST','permission_callback'=>$perm,'callback'=>function(WP_REST_Request $r){
  $c=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Template');
  $g=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_PDF_Generator');
  $t=new $c();$t->set('template_data',wp_json_encode($r->get_param('layout')));
  $t->set('settings',wp_json_encode(array('orientation'=>'landscape','size'=>'custom','width'=>595,'height'=>420,'unit'=>'pt')));
  $items=array();foreach((array)$r->get_param('data') as $d){$items[]=array('template'=>$t,'ticket_data'=>milano_pdf_layout_fields_v3($d));}
  if(count($items)>20)return new WP_Error('limit','At most 20 fixtures');
  if(function_exists('tickera_ticket_designer_ensure_tcpdf'))tickera_ticket_designer_ensure_tcpdf();
  $pdf=$g::generate_multi($items,'S','Milano-preview.pdf');
  $encoded=base64_encode($pdf);set_transient('milano_layout_stage_pdf',$encoded,900);
  return array('valid'=>substr($pdf,0,5)==='%PDF-','bytes'=>strlen($pdf),'base64_length'=>strlen($encoded));
 }));
 register_rest_route('milano-layout-stage/v1','/chunk',array('methods'=>'GET','permission_callback'=>$perm,'callback'=>function(WP_REST_Request $r){return array('base64'=>substr((string)get_transient('milano_layout_stage_pdf'),absint($r->get_param('offset')),60000));}));
});
