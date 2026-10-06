<?php
/** Fixed, authorized Denizli protocol batch. Uses native Woo/Bridge ticket issuance. */
if(!defined('ABSPATH'))exit;
final class MDG_Denizli_Invitations {
 const OPTION='mdg_denizli_protocol_20261008_a2_a11';
 const LOCK='mdg_denizli_protocol_batch_lock';
 public static function seats(){return array_map(function($n){return 'A'.$n;},range(2,11));}
 public static function hooks(){
  add_action('rest_api_init',function(){
   foreach(array('status'=>'GET','create'=>'POST','verify'=>'GET') as $action=>$method){
    register_rest_route('mdg-invitations/v1','/'.$action,array('methods'=>$method,'permission_callback'=>function(){return current_user_can('manage_woocommerce');},'callback'=>array(__CLASS__,$action)));
   }
  });
  foreach(array('new_order','customer_processing_order','customer_completed_order','customer_on_hold_order') as $email){add_filter('woocommerce_email_enabled_'.$email,function($enabled,$order){return $order instanceof WC_Order && $order->get_meta('_mdg_protocol_batch')===self::OPTION?false:$enabled;},10,2);}
  add_action('template_redirect',array(__CLASS__,'page'),1);
 }
 public static function tickets($oid){return get_posts(array('post_type'=>'tc_tickets_instances','post_status'=>'publish','post_parent'=>$oid,'numberposts'=>30,'fields'=>'ids','orderby'=>'ID','order'=>'ASC'));}
 public static function status(){
  $batch=get_option(self::OPTION,array());$rows=array();
  foreach($batch['orders']??array() as $sid=>$oid){$o=wc_get_order($oid);$ids=self::tickets($oid);$seats=array();foreach($ids as $id)$seats[]=get_post_meta($id,'_mdg_protocol_seat',true);$rows[]=array('session'=>(int)$sid,'order'=>$oid,'status'=>$o?$o->get_status():'missing','total'=>$o?$o->get_total():null,'tickets'=>count($ids),'seats'=>$seats);}
  return array('batch'=>$rows,'ready'=>!empty($batch['ready']),'url'=>!empty($batch['ready'])?add_query_arg('mdg_davetiye',$batch['token'],home_url('/')):null,'engine'=>class_exists('MDG_Ticket_Session_Datetime'),'section'=>'Ana salon');
 }
 public static function create($request){
  if($request->get_param('confirm')!=='DENIZLI-20261008-ANA-A2-A11-20')return new WP_Error('confirmation','Kesin koltuk/seans onayı eşleşmedi.',array('status'=>400));
  if(!class_exists('MDG_Capacity')||!class_exists('MDG_Live_Sales')||!class_exists('MDG_Ticket_Session_Datetime'))return new WP_Error('engine','Bilet motoru eksik.',array('status'=>503));
  if(!add_option(self::LOCK,time(),'','no'))return new WP_Error('locked','Bu paket zaten işleniyor; durumunu kontrol edin.',array('status'=>409));
  try{
   $batch=get_option(self::OPTION,array());
   if(!empty($batch['ready']))return self::status();
   global $wpdb;
   $spec=array(99=>2427,100=>2430);
   foreach($spec as $sid=>$vid){
    $s=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.MDG_DB::table('sessions').' WHERE id=%d AND event_id=12',$sid));
    $p=wc_get_product($vid);
    if(!$s||$s->status!=='onsale'||!$p||(int)$p->get_parent_id()!==(int)$s->wc_product_id||(int)$p->get_meta('_mdg_session_id')!==$sid)throw new Exception('Seans/ürün doğrulaması başarısız.');
    if(empty($batch['orders'][$sid])&&MDG_Capacity::available($sid)<10)throw new Exception('10 kişilik kapasite yok.');
    $clash=get_posts(array('post_type'=>'tc_tickets_instances','post_status'=>'publish','fields'=>'ids','numberposts'=>1,'meta_query'=>array(array('key'=>'_mdg_protocol_session','value'=>$sid),array('key'=>'_mdg_protocol_seat','value'=>self::seats(),'compare'=>'IN'))));
    if($clash&&empty($batch['orders'][$sid]))throw new Exception('Koltuklar için daha önce davetiye var.');
   }
   if(empty($batch['token'])){$batch['token']=bin2hex(random_bytes(24));$batch['orders']=array();update_option(self::OPTION,$batch,false);}
   // No recipient email/phone and no explicit notification calls. These are complimentary internal orders.
   foreach($spec as $sid=>$vid){
    if(empty($batch['orders'][$sid])){
     $o=wc_create_order(array('created_via'=>'mdg_numbered_protocol','status'=>'pending'));
     if(is_wp_error($o))throw new Exception($o->get_error_message());
     $o->update_meta_data('_mdg_protocol_batch',self::OPTION);$o->set_billing_first_name('Denizli Protokol');$o->set_billing_last_name('Davetiyesi');$o->set_payment_method('mdg_protocol');$o->set_payment_method_title('Ücretsiz protokol davetiyesi');$o->save();
     $batch['orders'][$sid]=$o->get_id();update_option(self::OPTION,$batch,false);
     $item_id=$o->add_product(wc_get_product($vid),10,array('subtotal'=>0,'total'=>0));
     if(!$item_id)throw new Exception('Sipariş kalemi oluşturulamadı.');
     $item=$o->get_item($item_id);$item->set_name('Protokol davetiyesi · Ana salon · A2–A11');$item->add_meta_data('Koltuklar',implode(', ',self::seats()));$item->add_meta_data('mdg_session_id',$sid);$item->save();$o->calculate_totals(false);$o->save();
    }else{$o=wc_get_order($batch['orders'][$sid]);}
    if(!$o||$o->get_meta('_mdg_protocol_batch')!==self::OPTION||count($o->get_items())!==1||(float)$o->get_total()!==0.0)throw new Exception('Paket siparişi beklenen yapıda değil.');
    if($o->get_status()!=='completed'){
     // Native pre-payment hook acquires an atomic 10-person hold; completion commits it and issues QR tickets.
     MDG_Live_Sales::classic_order_processed($o->get_id(),array(),$o);
     $o->update_status('completed','Yetkili talep: ana salon A2–A11, seans başına 10 ücretsiz protokol davetiyesi.');
    }
    $ids=self::tickets($o->get_id());if(count($ids)!==10)throw new Exception('Native Bridge 10 bilet üretmedi; mevcut sipariş korunarak işlem durduruldu.');
    foreach($ids as $i=>$id){
     if((int)get_post_meta($id,'ticket_type_id',true)!==$vid||!get_post_meta($id,'ticket_code',true))throw new Exception('Bilet eşleşmesi doğrulanamadı.');
     $seat=self::seats()[$i];update_post_meta($id,'_mdg_protocol_seat',$seat);update_post_meta($id,'_mdg_protocol_session',$sid);update_post_meta($id,'_mdg_protocol_section','Ana salon');update_post_meta($id,'first_name','Protokol · '.$seat);update_post_meta($id,'last_name','Ana salon');
    }
   }
   $batch['ready']=true;update_option(self::OPTION,$batch,false);return self::status();
  }catch(Throwable $e){return new WP_Error('batch_failed',$e->getMessage(),array('status'=>409));}finally{delete_option(self::LOCK);}
 }
 public static function data($id){
  $fields=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Fields');
  $data=$fields::resolve_ticket_data($id);
  $data=MDG_Ticket_Session_Datetime::correct_ticket_data($data,$id);
  $seat=get_post_meta($id,'_mdg_protocol_seat',true);
  $data['attendee_name']='Ana salon · A sırası · Koltuk '.$seat;
  $data['el_tc_ticket_type_element_custom']='PROTOKOL DAVETİYESİ';
  return $data;
 }
 public static function pdf($id){
  $type=(int)get_post_meta($id,'ticket_type_id',true);
  $template_id=(int)get_post_meta($type,'tc_designer_template_id',true);
  if(!$template_id)throw new Exception('Bilet şablonu yok.');
  $t=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Template');$g=MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_PDF_Generator');
  if(function_exists('tickera_ticket_designer_ensure_tcpdf'))tickera_ticket_designer_ensure_tcpdf();
  if(class_exists('MDG_Protocol_PDF'))return MDG_Protocol_PDF::render($template_id,self::data($id));
  return $g::generate(new $t($template_id),self::data($id),'S','davetiye.pdf');
 }
 public static function verify(){
  $b=get_option(self::OPTION,array());if(empty($b['ready']))return new WP_Error('not_ready','Paket hazır değil.');
  $out=array();
  foreach($b['orders'] as $sid=>$oid){foreach(self::tickets($oid) as $id){$data=self::data($id);$pdf=self::pdf($id);$out[]=array('id'=>$id,'session'=>(int)$sid,'seat'=>get_post_meta($id,'_mdg_protocol_seat',true),'datetime'=>$data['event_datetime']??'','label'=>$data['attendee_name'],'pdf_valid'=>is_string($pdf)&&substr($pdf,0,5)==='%PDF-','bytes'=>strlen($pdf),'qr_preserved'=>isset($data['ticket_code'])&&$data['ticket_code']==get_post_meta($id,'ticket_code',true));}}
  return $out;
 }
 public static function page(){
  if(!isset($_GET['mdg_davetiye']))return;
  $b=get_option(self::OPTION,array());$token=is_string($_GET['mdg_davetiye'])?$_GET['mdg_davetiye']:'';
  nocache_headers();header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
  if(empty($b['ready'])||!hash_equals((string)($b['token']??''),$token)||time()>strtotime('2026-10-10 00:00:00 Europe/Istanbul')){status_header(404);exit;}
  $all=array();foreach($b['orders'] as $sid=>$oid){$o=wc_get_order($oid);if(!$o||$o->get_status()!=='completed')continue;foreach(self::tickets($oid) as $id){if(get_post_meta($id,'_mdg_invalidated',true))continue;$all[$id]=(int)$sid;}}
  if(isset($_GET['bilet'])){$id=absint($_GET['bilet']);if(!isset($all[$id])){status_header(404);exit;}$pdf=self::pdf($id);if(!is_string($pdf)||substr($pdf,0,5)!=='%PDF-'){status_header(503);exit;}header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="Denizli-'.$all[$id].'-'.get_post_meta($id,'_mdg_protocol_seat',true).'.pdf"');echo $pdf;exit;}
  echo '<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Denizli Protokol Davetiyeleri</title><style>body{font:18px system-ui;max-width:850px;margin:30px auto;padding:20px;background:#fff9ed;color:#29231c}a{display:inline-block;padding:14px;margin:5px;background:#a63225;color:white;border-radius:8px}h2{margin-top:35px}</style><h1>Denizli Protokol Davetiyeleri</h1><p>8 Ekim 2026 · Özay Gönlüm Salonu<br>Ana salon · A sırası · A2–A11<br>Her davetiye bir kişilik ve yalnız üzerindeki seans için geçerlidir.</p>';
  foreach(array(99=>'17.30',100=>'19.30') as $sid=>$time){echo '<h2>'.$time.' seansı — 10 davetiye</h2>';foreach($all as $id=>$s){if($sid!==$s)continue;$url=add_query_arg(array('mdg_davetiye'=>$token,'bilet'=>$id),home_url('/'));echo '<a href="'.esc_url($url).'">'.esc_html(get_post_meta($id,'_mdg_protocol_seat',true)).' · PDF aç</a>';}}
  echo '<p>QR kodunu girişte okutun. Yeriniz için salon görevlisine koltuk numaranızı gösterin.</p></html>';exit;
 }
}
MDG_Denizli_Invitations::hooks();
