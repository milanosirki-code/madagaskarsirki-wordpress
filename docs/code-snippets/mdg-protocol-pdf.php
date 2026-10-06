<?php
/** Protocol-only in-memory Designer layout. Never saves shared templates. */
if(!defined('ABSPATH'))exit;
if(!class_exists('MDG_Protocol_PDF',false)){
final class MDG_Protocol_PDF {
 static function text($field,$x,$y,$width,$size,$label='',$color='#eee7d5',$align='left'){
  return array('id'=>'protocol_'.$field,'type'=>'dynamic_text','baseType'=>'text','dataField'=>$field,'label'=>$label,'labelPosition'=>'before','x'=>$x,'y'=>$y,'width'=>$width,'height'=>$size*3,'fontSize'=>$size,'fontFamily'=>'Arial','fontWeight'=>'normal','fill'=>$color,'textAlign'=>$align,'rotation'=>0,'opacity'=>1);
 }
 static function layout($original){
  $qr=null;$map=null;foreach($original['elements']??array() as $el){if(($el['type']??'')==='qr_code'&&($el['dataField']??'')==='ticket_id')$qr=$el;if(($el['type']??'')==='custom_image'&&!empty($el['mdgVenueQr']))$map=$el;}
  if(!$qr)throw new Exception('Native giriş QR alanı bulunamadı.');
  $e=array(array('id'=>'protocol_border','type'=>'rectangle','x'=>12,'y'=>12,'width'=>571,'height'=>396,'fill'=>'transparent','stroke'=>'#d4af37','strokeWidth'=>1.5,'rx'=>5,'ry'=>5));
  $e[]=self::text('el_tc_ticket_type_element_custom',26,26,543,18,'','#d4af37','center');
  $e[]=self::text('event_name',26,63,543,17,'','#f5f0e1','center');
  $e[]=self::text('event_datetime',28,122,395,13,'TARİH / SEANS:');
  $e[]=self::text('venue_name',28,162,395,13,'SALON:');
  $e[]=self::text('protocol_address',28,224,395,11,'ADRES:');
  $e[]=self::text('attendee_name',28,282,395,14,'MİSAFİR / KOLTUK:','#f5f0e1');
  $e[]=self::text('protocol_terms',28,346,395,10);
  $qr['x']=460;$qr['y']=128;$qr['width']=96;$qr['height']=96;$qr['size']=96;$qr['rotation']=0;$e[]=$qr;
  $e[]=self::text('protocol_entry_label',450,101,116,12,'','#d4af37','center');
  $e[]=self::text('ticket_code',447,234,120,12,'','#d4af37','center');
  if($map){$map['x']=470;$map['y']=303;$map['width']=78;$map['height']=78;$map['rotation']=0;$e[]=$map;$e[]=self::text('protocol_map_label',440,280,138,11,'','#d4af37','center');}
  return array('width'=>595,'height'=>420,'background'=>'#111111','elements'=>$e);
 }
 static function clean_data($data){
  $address=trim((string)($data['venue_address']??''));if($address===''){$raw=preg_replace('/<br\s*\/?>/i',"\n",(string)($data['event_terms']??''));$raw=html_entity_decode(wp_strip_all_tags($raw),ENT_QUOTES,'UTF-8');$address=trim(preg_split('/Bilgi\s*&\s*Destek|Koltuk numarası|Fatura Bilgilendirmesi/iu',$raw)[0]);}
  $data['protocol_address']=preg_replace('/^Adres\s*:\s*/iu','',$address);
  $data['protocol_entry_label']='GİRİŞ QR';$data['protocol_map_label']='SALON KONUMU';
  $data['protocol_terms']="Bu davetiye bir kişilik ve yalnız belirtilen seans için geçerlidir.\nGirişte QR kodunuzu, yeriniz için koltuk bilginizi gösterin.\nLütfen seans saatinden 30 dakika önce salonda olun.";
  return $data;
 }
 static function render($template_id,$data){
  $t=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Template');$g=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_PDF_Generator');$native=new $t($template_id);$copy=clone $native;
  $copy->set('template_data',wp_json_encode(self::layout($native->get_template_array())));
  if(function_exists('tickera_ticket_designer_ensure_tcpdf'))tickera_ticket_designer_ensure_tcpdf();
  $pdf=$g::generate($copy,self::clean_data($data),'S','davetiye.pdf');if(!is_string($pdf)||substr($pdf,0,5)!=='%PDF-')throw new Exception('Protokol PDF oluşturulamadı.');return $pdf;
 }
 static function diagnostics(){
  $id=4596;$data=MDG_Denizli_Invitations::data($id);$type=(int)get_post_meta($id,'ticket_type_id',true);$template=(int)get_post_meta($type,'tc_designer_template_id',true);$t=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Template');$o=new $t($template);$original=$o->get('template_data');$pdf=self::render($template,$data);
  return array('pdf'=>base64_encode($pdf),'bytes'=>strlen($pdf),'native_template_unchanged'=>$original===(new $t($template))->get('template_data'),'qr_payload_hash'=>hash('sha256',(string)$data['ticket_id']),'qr_code_preserved'=>$data['ticket_code']===get_post_meta($id,'ticket_code',true),'datetime'=>$data['event_datetime'],'attendee'=>$data['attendee_name'],'venue'=>$data['venue_name'],'address'=>self::clean_data($data)['protocol_address'],'no_generation'=>true);
 }
}
add_action('rest_api_init',function(){register_rest_route('mdg-protocol-pdf/v1','/diagnostics',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_woocommerce');},'callback'=>array('MDG_Protocol_PDF','diagnostics')));});
}
