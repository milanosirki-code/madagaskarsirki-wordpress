<?php
/**
 * Plugin Name: Milano - Ortak Madagaskar Odeme Gecisi
 * Description: Milano secimini mevcut merkezi MDG sepetine aktarir. Ayri siparis, POS, kapasite veya QR defteri olusturmaz.
 * Version: 0.2.2
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
final class Milano_Shared_Checkout_Handoff {
 const STAGE='staging-f321-milanosirki-lgajf.wpcomstaging.com';
 const CENTRAL='madagaskarsirki.com';
 const FRONT='milanosirki.com';
 private static $context=null;
 public static function selection($input) {
  $out=array();
  foreach(array('event_id','session_id','children','adults','families') as $key){
   $v=$input[$key]??'0';
   if(!is_scalar($v)||!preg_match('/^[0-9]{1,8}$/D',(string)$v)){return new WP_Error('selection','Bilet secimi gecersiz.');}
   $out[$key]=(int)$v;
  }
  if(!$out['event_id']||!$out['session_id']||$out['families']>10
   ||$out['children']+2*$out['families']>20||$out['adults']+2*$out['families']>20
   ||$out['children']+$out['adults']+4*$out['families']<1){
   return new WP_Error('quantity','En az bir bilet secin; bilet adedi sinirini asmayin.');
  }
  // Only quantities and canonical IDs are accepted. Never accept a visitor price, variation or redirect URL.
  foreach(array('price','amount','product_id','variation_id','redirect','return_url') as $key){
   if(isset($input[$key])){return new WP_Error('untrusted_field','Fiyat ve odeme hedefi sunucuda belirlenir.');}
  }
  return $out;
 }
 public static function family_enabled($list,$eid){
  if(!is_array($list)){return false;}
  if(!$list){return false;}
  $isList=array_keys($list)===range(0,count($list)-1);
  return $isList?in_array((int)$eid,array_map('intval',$list),true):!empty($list[(int)$eid]);
 }
 public static function selector_page(){
  if(empty($_GET['milano_bilet_sec'])||!self::central()){return;}
  if(!current_user_can('manage_woocommerce')){wp_die('Yetkili prova kullanicisi gerekli.','Milano',array('response'=>403));}
  nocache_headers();get_header();echo '<style>body{background:#f4f1e8!important;color:#17243a!important;font-family:system-ui,sans-serif!important}body>header{padding:18px 24px;border-bottom:1px solid #ded8c6}body>header h1{font-size:24px!important;line-height:1.2!important;margin:8px 0!important}body>header a{color:#17243a!important}main{background:#fffdf7;border:1px solid #e4dcc8;border-radius:22px;box-shadow:0 12px 44px #15213b12;box-sizing:border-box}main h1{font-size:clamp(26px,4vw,36px)!important;line-height:1.2!important;letter-spacing:-.02em;margin:0 0 24px!important;color:#14233f!important}main h2{font-size:23px!important;line-height:1.35!important;margin:24px 0 12px!important;color:#14233f!important}main p{font-size:16px!important;line-height:1.65!important;margin:14px 0!important;color:#263a54!important}main strong{color:#14233f}main button{background:#14233f;color:#fff9e7;border:0;border-radius:10px;padding:15px 22px;font:700 16px system-ui,sans-serif;cursor:pointer}main button:disabled{opacity:.6;cursor:wait}main [role=alert]{color:#a72b22!important}main form{padding:22px 0;border-top:1px solid #e4dcc8}main form label{display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 16px 10px 0;font-size:15px}main input[type=number]{width:64px;min-height:42px;padding:8px;border:1px solid #a9b5c5;border-radius:8px;font:inherit}@media(max-width:640px){main{margin:18px 12px!important;padding:22px!important}main button{width:100%}}</style><main style="max-width:980px;margin:36px auto;padding:24px"><h1>Milano Sirki — Bilet Seçimi</h1>'.self::shortcode().'</main>';get_footer();exit;
 }
 public static function product_event($pid,$vid){
  if(!class_exists('MDG_DB')){return 0;}
  global $wpdb;
  $ids=$wpdb->get_col($wpdb->prepare('SELECT DISTINCT s.event_id FROM '.MDG_DB::table('sessions').' s INNER JOIN '.MDG_DB::table('ticket_types').' t ON t.session_id=s.id WHERE s.wc_product_id=%d AND t.wc_variation_id=%d AND t.is_active=1 LIMIT 2',(int)$pid,(int)$vid));
  return !$wpdb->last_error&&count((array)$ids)===1?(int)$ids[0]:0;
 }
 public static function validate_cart_entry($passed,$pid,$qty,$vid=0,$variations=array(),$data=array()){
  if(!$passed||!self::central()){return $passed;}
  $eid=self::product_event($pid,$vid);if(!$eid||!self::owner($eid)){return $passed;}
  if(!self::$context||self::$context['event_id']!==$eid){
   wc_add_notice('Milano biletini Milano bilet secimi ve ortak odeme baglantisi uzerinden ekleyiniz.','error');return false;
  }
  return $passed;
 }
 public static function check_milano_cart(){
  if(!function_exists('WC')||!WC()->cart){return;}
  $milano=false;$other=false;
  foreach(WC()->cart->get_cart() as $i){
   $eid=self::product_event((int)$i['product_id'],(int)($i['variation_id']??0));
   if($eid&&self::owner($eid)){
    $milano=true;
    if(empty($i['milano_handoff'])){wc_add_notice('Milano bilet secimi dogrulanamadi.','error');}
    $s=self::snapshot($eid,(int)($i['mdg_session_id']??0));if(is_wp_error($s)){wc_add_notice($s->get_error_message(),'error');}
   }else{$other=true;}
  }
  if($milano&&$other){wc_add_notice('Milano ve diger secimler ayni sepette odemeye alinamaz.','error');}
 }
 private static function host(){return strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));}
 private static function central(){return in_array(self::host(),array(self::CENTRAL,self::STAGE),true);}
 private static function owner($eid){
  global $wpdb;
  $rows=$wpdb->get_results($wpdb->prepare("SELECT b.mmc_event_id,p.id,p.brand FROM {$wpdb->prefix}mmc_mdg_event_bridge b INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=b.program_id WHERE b.mdg_event_id=%d AND b.is_active=1 LIMIT 2",$eid));
  return !$wpdb->last_error&&count((array)$rows)===1&&$rows[0]->brand==='Milano Sirki'?$rows[0]:null;
 }
 private static function snapshot($eid,$sid,$preview=false){
  if(!self::central()||!class_exists('MDG_Events')||!class_exists('MDG_Capacity')||!class_exists('MDG_Sessions')||!function_exists('wc_get_product')){
   return new WP_Error('dependencies','Merkezi bilet sistemi hazir degil.');
  }
  $owner=self::owner($eid);
  if(!$owner){return new WP_Error('brand','Dogrulanmis Milano etkinligi bulunamadi.');}
  $event=MDG_Events::get($eid);
  if(!$event||stripos($event->title,'Milano')===false||stripos($event->title,'Madagaskar')!==false){
   return new WP_Error('identity','Milano etkinlik kimligi uyusmuyor.');
  }
  $canPreview=$preview&&self::host()===self::STAGE&&current_user_can('manage_woocommerce');
  if(!$canPreview&&self::host()===self::CENTRAL&&true!==get_option('milano_handoff_sales_enabled_v1',false)&&!current_user_can('manage_woocommerce')){
   return new WP_Error('launch_closed','Milano bilet satışı son ödeme kontrolünün ardından açılacaktır.');
  }
  if(!$canPreview&&$event->status!=='onsale'){return new WP_Error('closed','Bu etkinligin bilet satisi henuz acilmadi.');}
  global $wpdb;
  $s=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mdg_sessions WHERE id=%d AND event_id=%d LIMIT 1",$sid,$eid));
  if(!$s||(!$canPreview&&($s->status!=='onsale'||MDG_Sessions::sales_closed_by_time($s)))){
   return new WP_Error('session','Seans satisina ulasilamiyor.');
  }
  $now=current_time('mysql',true);
  foreach(array($event,$s) as $row){
   if(!$canPreview&&((!empty($row->sale_start)&&$row->sale_start>$now)||(!empty($row->sale_end)&&$row->sale_end<=$now))){return new WP_Error('window','Seans satis penceresi kapali.');}
  }
  if($s->start_at<=$now){return new WP_Error('past','Seans sona erdi.');}
  $pid=(int)$s->wc_product_id; $p=wc_get_product($pid);
  $key=(string)get_post_meta($pid,'_mdg_production_key',true);
  $tid=(int)$s->tickera_event_id; $tp=get_post($tid);
  $designer=(int)get_post_meta($pid,'tc_designer_template_id',true);
  $design=$wpdb->get_row($wpdb->prepare("SELECT name,status FROM {$wpdb->prefix}tickera_ticket_templates WHERE id=%d",$designer));
  if(!$p||!$p->is_type('variable')||!$tp||$tp->post_type!=='tc_events'
   ||get_post_meta($pid,'_tc_is_ticket',true)!=='yes'
   ||(int)get_post_meta($pid,'_mdg_event_id',true)!==$eid
   ||(int)get_post_meta($pid,'_mdg_session_id',true)!==$sid||$key===''
   ||(int)get_post_meta($tid,'_mdg_event_id',true)!==$eid
   ||get_post_meta($tid,'_mdg_production_key',true)!==$key
   ||!$design||$design->status!=='active'||stripos($design->name,'Milano')===false
   ||get_post_meta($pid,'_ticket_template',true)!=='d_'.$designer
   ||(!$canPreview&&(get_post_status($pid)!=='publish'||$tp->post_status!=='publish'||get_post_meta($pid,'_allow_ticket_checkout',true)!=='yes'))){
   return new WP_Error('mapping','Bilet ve Milano sablon baglantisi hazir degil.');
  }
  $types=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mdg_ticket_types WHERE session_id=%d AND is_active=1 ORDER BY id",$sid));
  $mapped=array();
  foreach((array)$types as $t){
   $kind=strtoupper($t->code);
   if(!in_array($kind,array('CHILD','ADULT'),true)||isset($mapped[$kind])||(int)$t->capacity_units!==1){return new WP_Error('types','Bilet turleri belirsiz.');}
   $v=wc_get_product((int)$t->wc_variation_id);
   if(!$v||!$v->is_type('variation')||(int)$v->get_parent_id()!==$pid
    ||abs((float)$v->get_price()-(float)$t->price)>0.009
    ||(int)get_post_meta($v->get_id(),'tc_designer_template_id',true)!==$designer
    ||get_post_meta($v->get_id(),'_ticket_template',true)!=='d_'.$designer
    ||(!$canPreview&&(!$v->is_purchasable()||!$v->is_in_stock()))){return new WP_Error('variation','Bilet fiyati veya varyasyonu hazir degil.');}
   $mapped[$kind]=$t;
  }
  if(count($mapped)!==2){return new WP_Error('types','Cocuk ve yetiskin bileti gerekli.');}
  $family=$wpdb->get_results($wpdb->prepare("SELECT price,capacity_units FROM {$wpdb->prefix}mmc_ticket_types WHERE event_id=%d AND ticket_code='family_2_2' AND is_active=1 LIMIT 2",(int)$owner->mmc_event_id));
  $settings=get_option('mdg_family_package_22_v1',array()); $list=$settings['events']??array();
  $familyReady=count((array)$family)===1&&(int)$family[0]->capacity_units===4
   &&(float)$family[0]->price>0&&(float)$family[0]->price<=2*((float)$mapped['CHILD']->price+(float)$mapped['ADULT']->price)
   &&self::family_enabled($list,$eid);
  $local=MDG_Sessions::local_parts($s->start_at);
  return array('event_id'=>$eid,'session_id'=>$sid,'title'=>(string)$event->title,'venue'=>(string)($event->venue_name??''),
   'date'=>$local[0],'time'=>$local[1],'children_price'=>(float)$mapped['CHILD']->price,'adults_price'=>(float)$mapped['ADULT']->price,
   'family_price'=>$familyReady?(float)$family[0]->price:null,'capacity_snapshot'=>(int)MDG_Capacity::available($sid),
   'child_code'=>(string)$mapped['CHILD']->code,'adult_code'=>(string)$mapped['ADULT']->code,'preview'=>$canPreview);
 }
 public static function catalog(){
  if(!self::central()){return new WP_Error('host','Merkezi kaynak gerekli.',array('status'=>404));}
  global $wpdb;
  $rows=$wpdb->get_results("SELECT s.id,s.event_id FROM {$wpdb->prefix}mdg_sessions s INNER JOIN {$wpdb->prefix}mdg_events e ON e.id=s.event_id WHERE s.status='onsale' AND e.status='onsale' ORDER BY s.start_at");
  $out=array();foreach((array)$rows as $r){if(!self::owner((int)$r->event_id)){continue;}$v=self::snapshot((int)$r->event_id,(int)$r->id);if(!is_wp_error($v)){$out[]=$v;}}
  return rest_ensure_response(array('brand'=>'milano','sessions'=>$out));
 }
 public static function shortcode(){
  $base='https://'.self::CENTRAL;
  if(self::host()===self::STAGE){
   if(!current_user_can('manage_woocommerce')){return '<p>Bu bilet provasi yalniz yoneticiye aciktir.</p>';}
   $base=home_url();$rows=array();foreach(array(150,151) as $sid){$r=self::snapshot(24,$sid,true);if(!is_wp_error($r)){$rows[]=$r;}}
  }elseif(self::host()===self::CENTRAL&&current_user_can('manage_woocommerce')){
   $catalog=self::catalog();$data=is_wp_error($catalog)?array():$catalog->get_data();$rows=$data['sessions']??array();
  }else{
   $res=wp_remote_get($base.'/wp-json/milano-handoff/v1/catalog',array('timeout'=>8,'redirection'=>0));
   $data=!is_wp_error($res)&&wp_remote_retrieve_response_code($res)===200?json_decode(wp_remote_retrieve_body($res),true):null;
   $rows=is_array($data)&&($data['brand']??'')==='milano'&&is_array($data['sessions']??null)?$data['sessions']:array();
  }
  if(!$rows){return '<p>Milano Sirki bilet satışı son ödeme kontrolünün ardından açılacaktır. Güncel duyurular için bizi takip edin.</p>';}
  $html='<div class="milano-ticket-selector"><p>Ödemeniz Dünya Organizasyon\'un ortak bilet altyapisi olan madagaskarsirki.com üzerinden güvenli şekilde tamamlanacaktır.</p>';
  foreach($rows as $row){
   $html.='<form method="get" action="'.esc_url($base.'/').'"><input type="hidden" name="milano_bilet_odeme" value="1"><input type="hidden" name="event_id" value="'.(int)$row['event_id'].'"><input type="hidden" name="session_id" value="'.(int)$row['session_id'].'">';
   if(!empty($row['preview'])){$html.='<input type="hidden" name="milano_preview" value="1">';}
   $html.='<h3>'.esc_html($row['title'].' — '.$row['date'].' '.$row['time']).'</h3>';
   foreach(array('adults'=>'Yetişkin','children'=>'Çocuk','families'=>'Aile 2+2') as $key=>$label){
    $price=$key==='families'?$row['family_price']:$row[$key.'_price'];
    if($price===null){continue;}
    $html.='<label>'.esc_html($label.' — '.number_format((float)$price,2,',','.').' TL').' <input type="number" name="'.esc_attr($key).'" min="0" max="'.($key==='families'?'10':'20').'" value="0"></label> ';
   }
   $html.='<button type="submit">'.(!empty($row['preview'])?'Secimi provada kontrol et':'Bilet al — ortak ödemeye geç').'</button></form>';
  }
  return $html.'</div>';
 }
 public static function landing(){
  if(!self::central()||empty($_GET['milano_bilet_odeme'])){return;}
  nocache_headers();
  $sel=self::selection($_GET);$snap=is_wp_error($sel)?$sel:self::snapshot($sel['event_id'],$sel['session_id'],!empty($_GET['milano_preview']));
  if(is_wp_error($snap)){wp_die(esc_html($snap->get_error_message()),'Milano bilet secimi',array('response'=>409));}
  if($sel['families']&&$snap['family_price']===null){wp_die('Aile paketi hazir degil.','Milano',array('response'=>409));}
  $units=$sel['children']+$sel['adults']+4*$sel['families'];
  if($units>$snap['capacity_snapshot']){wp_die('Secilen kisi sayisi kalan kapasiteyi asiyor.','Milano',array('response'=>409));}
  $total=$sel['children']*$snap['children_price']+$sel['adults']*$snap['adults_price']+$sel['families']*(float)$snap['family_price'];
  get_header();echo '<style>body{background:#f4f1e8!important;color:#17243a!important;font-family:system-ui,sans-serif!important}body>header{padding:18px 24px;border-bottom:1px solid #ded8c6}body>header h1{font-size:24px!important;line-height:1.2!important;margin:8px 0!important}body>header a{color:#17243a!important}main{background:#fffdf7;border:1px solid #e4dcc8;border-radius:22px;box-shadow:0 12px 44px #15213b12;box-sizing:border-box}main h1{font-size:clamp(26px,4vw,36px)!important;line-height:1.2!important;letter-spacing:-.02em;margin:0 0 24px!important;color:#14233f!important}main h2{font-size:23px!important;line-height:1.35!important;margin:24px 0 12px!important;color:#14233f!important}main p{font-size:16px!important;line-height:1.65!important;margin:14px 0!important;color:#263a54!important}main strong{color:#14233f}main button{background:#14233f;color:#fff9e7;border:0;border-radius:10px;padding:15px 22px;font:700 16px system-ui,sans-serif;cursor:pointer}main button:disabled{opacity:.6;cursor:wait}main [role=alert]{color:#a72b22!important}main form{padding:22px 0;border-top:1px solid #e4dcc8}main form label{display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 16px 10px 0;font-size:15px}main input[type=number]{width:64px;min-height:42px;padding:8px;border:1px solid #a9b5c5;border-radius:8px;font:inherit}@media(max-width:640px){main{margin:18px 12px!important;padding:22px!important}main button{width:100%}}</style><main style="max-width:820px;margin:40px auto;padding:24px"><h1>Milano Sirki — Ortak Ödeme</h1><p>Bu ekran Dünya Organizasyon\'un madagaskarsirki.com bilet altyapısını kullanır. Biletiniz Milano Sirki etkinliği içindir.</p><h2>'.esc_html($snap['title']).'</h2><p>'.esc_html($snap['date'].' '.$snap['time']).' · '.(int)$units.' kişi</p><p>Toplam: <strong>'.esc_html(number_format($total,2,',','.')).' TL</strong></p>';
  if($snap['preview']){echo '<p><strong>PROVA: Satis kapali. Siparis, kapasite rezervasyonu veya odeme olusturulmadi.</strong></p>';}
  else{
   $nonce=wp_create_nonce('milano_handoff_'.$sel['event_id'].'_'.$sel['session_id']);
   echo '<button id="milano-handoff-continue">Güvenli ödeme ekranına devam et</button><p id="milano-handoff-error" role="alert"></p>';
   $cfg=array('ajax'=>admin_url('admin-ajax.php'),'selection'=>$sel,'nonce'=>$nonce,'checkout'=>wc_get_checkout_url());
   echo '<script>(function(){const c='.wp_json_encode($cfg).';const b=document.getElementById("milano-handoff-continue");b.addEventListener("click",async function(){b.disabled=true;const d=new URLSearchParams(c.selection);d.set("action","milano_handoff_cart");d.set("nonce",c.nonce);try{const r=await fetch(c.ajax,{method:"POST",credentials:"same-origin",body:d});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.data?.message||"Biletler sepete eklenemedi.");window.location.assign(c.checkout);}catch(e){document.getElementById("milano-handoff-error").textContent=e.message;b.disabled=false;}});})();</script>';
  }
  echo '</main>';get_footer();exit;
 }
 public static function cart(){
  if(!self::central()){wp_send_json_error(array('message'=>'Odeme hedefi gecersiz.'),403);}
  $sel=self::selection($_POST);if(is_wp_error($sel)){wp_send_json_error(array('message'=>$sel->get_error_message()),400);}
  check_ajax_referer('milano_handoff_'.$sel['event_id'].'_'.$sel['session_id'],'nonce');
  $snap=self::snapshot($sel['event_id'],$sel['session_id']);
  if(is_wp_error($snap)){wp_send_json_error(array('message'=>$snap->get_error_message()),409);}
  if($sel['families']&&($snap['family_price']===null||!class_exists('MDG_Family_Package_22_V1'))){wp_send_json_error(array('message'=>'Aile paketi hazir degil.'),409);}
  if(!class_exists('MDG_Live_Sales')){wp_send_json_error(array('message'=>'Merkezi sepet hazir degil.'),503);}
  if(!WC()->cart||!WC()->session){wc_load_cart();}
  foreach(WC()->cart->get_cart() as $item){
   if(empty($item['mdg_live_sale'])||(int)($item['mdg_event_id']??0)!==$sel['event_id']){
    wp_send_json_error(array('message'=>'Sepetinizde başka bir seçim var. Önce mevcut sepetinizi kontrol edin; biletleriniz değiştirilmedi.'),409);
   }
  }
  $lines=array();foreach(array('children'=>$snap['child_code'],'adults'=>$snap['adult_code'],'families'=>'MDG_FAMILY_2_2') as $key=>$code){if($sel[$key]){$lines[]=array('code'=>$code,'qty'=>$sel[$key]);}}
  self::$context=$sel;
  $_POST['lines']=wp_json_encode($lines);$_POST['event_id']=$sel['event_id'];$_POST['session_id']=$sel['session_id'];
  $_POST['nonce']=wp_create_nonce('mdg_live_cart_'.$sel['event_id']);
  // WordPress check_ajax_referer reads REQUEST, not POST; keep the validated delegation nonce in sync.
  $_REQUEST['nonce']=$_POST['nonce'];
  if($sel['families']){MDG_Family_Package_22_V1::preprocess_family_line();}
  // Reuse the existing engine's validation, mapping, prices and capacity checks.
  // The engine replies with its existing JSON response; no Woo order or payment is created here.
  MDG_Live_Sales::add_to_cart();
 }
 public static function mark($data,$pid,$vid,$qty){
  if(!self::$context||(int)($data['mdg_event_id']??0)!==self::$context['event_id']){return $data;}
  $data['milano_handoff']=1;return $data;
 }
 public static function order_meta($order,$data){
  if(!function_exists('WC')||!WC()->cart){return;}
  $items=WC()->cart->get_cart();if(!$items){return;}
  foreach($items as $i){if(empty($i['milano_handoff'])||!self::owner((int)($i['mdg_event_id']??0))){return;}}
  $order->update_meta_data('_milano_brand','milano');$order->update_meta_data('_milano_source','https://milanosirki.com');
 }

 public static function public_catalog($rows){
  return array_values(array_filter((array)$rows,static function($row){
   return !self::owner((int)$row->id)&&stripos((string)$row->title,'Milano')===false;
  }));
 }
 public static function public_entry(){
  if(self::host()!==self::CENTRAL||!class_exists('MDG_Events')){return;}
  $slug=sanitize_title((string)get_query_var('mdg_event_slug'));if(!$slug){return;}
  $event=MDG_Events::get_by_public_slug($slug);
  if($event&&self::owner((int)$event->id)){
   nocache_headers();wp_redirect('https://milanosirki.com/bilet-al/',302);exit;
  }
 }

 public static function boot(){
  $h=self::host();if(!in_array($h,array(self::CENTRAL,self::STAGE,self::FRONT),true)){return;}
  add_shortcode('milano_merkezi_bilet',array(__CLASS__,'shortcode'));
  if(!self::central()){return;}
  add_filter('mdg_public_ticket_events',array(__CLASS__,'public_catalog'));
  add_filter('mdg_public_city_events',array(__CLASS__,'public_catalog'));
  add_action('template_redirect',array(__CLASS__,'public_entry'),-2);
  add_action('rest_api_init',static function(){register_rest_route('milano-handoff/v1','/catalog',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'catalog')));});
  add_action('template_redirect',array(__CLASS__,'selector_page'),1);
  add_action('template_redirect',array(__CLASS__,'landing'),2);
  add_filter('woocommerce_add_to_cart_validation',array(__CLASS__,'validate_cart_entry'),90,6);
  add_action('woocommerce_check_cart_items',array(__CLASS__,'check_milano_cart'),90);
  add_action('wp_ajax_milano_handoff_cart',array(__CLASS__,'cart'));
  add_action('wp_ajax_nopriv_milano_handoff_cart',array(__CLASS__,'cart'));
  add_filter('woocommerce_add_cart_item_data',array(__CLASS__,'mark'),60,4);
  add_action('woocommerce_checkout_create_order',array(__CLASS__,'order_meta'),15,2);
 }
}
add_action('plugins_loaded',array('Milano_Shared_Checkout_Handoff','boot'),40);
