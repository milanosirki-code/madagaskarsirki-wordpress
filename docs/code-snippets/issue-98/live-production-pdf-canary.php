<?php
// Temporary admin-only PDF verification. No ticket/order writes or global datetime hooks.
add_action('admin_menu', function () {
 add_submenu_page('tools.php','Bilet PDF Seans Kontrolü','Bilet PDF Seans Kontrolü','manage_woocommerce','mdg-pdf-canary-20261003','mdg_pdf_canary_20261003');
});
function mdg_pdf_canary_20261003() {
 if (!current_user_can('manage_woocommerce')) { wp_die('Yetkisiz'); }
 nocache_headers();
 echo '<div class="wrap"><h1>Bilet PDF Seans Kontrolü</h1><p>Salt okunur; sipariş, bilet ve check-in değiştirilmez.</p>';
 if (!class_exists('MDG_Ticket_Session_Datetime')) { echo '<p>Kanonik motor yüklenmedi.</p></div>'; return; }
 global $wp_filter;
 $owners=array();
 foreach(array('tickera_ticket_designer_pre_generate','tickera_ticket_designer_ticket_data','tickera_ticket_designer_resolved_ticket_data','tc_ticket_designer_ticket_data','tc_ticket_designer_resolved_ticket_data') as $hook){
  $canonical=0;$legacy=0;
  if(isset($wp_filter[$hook])){foreach($wp_filter[$hook]->callbacks as $priority=>$callbacks){foreach($callbacks as $cb){$f=$cb['function'];if(is_array($f) && $f[0]==='MDG_Ticket_Session_Datetime'){$canonical++;}if(is_string($f) && strpos($f,'mdg_tdfix_')===0){$legacy++;}}}}
  $owners[$hook]=array('canonical'=>$canonical,'legacy'=>$legacy);
 }
 $runtime=array('canonical_class_loaded'=>true,'class_file_matches_module'=>(new ReflectionClass('MDG_Ticket_Session_Datetime'))->getFileName()===MDG_BILET_DIR.'includes/class-mdg-ticket-session-datetime.php','standalone_active'=>function_exists('mdg_tdfix_pre_generate'),'bootstrap_sha256'=>hash_file('sha256',MDG_BILET_FILE),'module_sha256'=>hash_file('sha256',MDG_BILET_DIR.'includes/class-mdg-ticket-session-datetime.php'),'hook_owners'=>$owners);
 echo '<p>Kanonik seans motoru: '.($runtime['class_file_matches_module']?'YÜKLENDİ':'KONTROL GEREKLİ').'; eski düzeltme: '.($runtime['standalone_active']?'AKTİF':'DEVRE DIŞI').'</p>';
 echo '<pre id="mdg-runtime-evidence">'.esc_html(wp_json_encode($runtime,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';
 $fields_class = MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Fields');
 if (!class_exists($fields_class)) { echo '<p>Designer sınıfı yüklenmedi.</p></div>'; return; }
 $ids = get_posts(array('post_type'=>'tc_tickets_instances','post_status'=>'any','numberposts'=>100,'fields'=>'ids','orderby'=>'ID','order'=>'DESC'));
 $candidates = array();
 foreach ($ids as $id) {
  $data = $fields_class::resolve_ticket_data((int)$id);
  if (!is_array($data)) { continue; }
  $resolved = MDG_Ticket_Session_Datetime::resolve_ids($data,get_post_meta($id),(int)$id);
  $order = wc_get_order($resolved['order_id']);
  if (!$order || !$order->is_paid()) { continue; }
  $fixed = MDG_Ticket_Session_Datetime::correct_ticket_data($data,(int)$id);
  $template = 0;
  foreach (array($resolved['variation_id'],$resolved['product_id']) as $object_id) {
   if (!$object_id) { continue; }
   $template = absint(get_post_meta($object_id,'tc_designer_template_id',true));
   $raw = (string)get_post_meta($object_id,'_ticket_template',true);
   if (!$template && preg_match('/^d_(\d+)$/',$raw,$m)) { $template = absint($m[1]); }
   if ($template) { break; }
  }
  $candidates[$id] = array('ids'=>$resolved,'template'=>$template,'datetime'=>$fixed['event_datetime'] ?? '', 'data'=>$fixed);
 }
 
 global $wpdb;
 $diagnostics = array();
 foreach (array(4202,4069) as $instance_id) {
  if (!isset($candidates[$instance_id])) { continue; }
  $ids_row = $candidates[$instance_id]['ids'];
  $order = wc_get_order($ids_row['order_id']);
  $meta_ids = array();
  foreach (array('ticket_type_id','_ticket_type_id','product_id','_product_id','variation_id','_variation_id','order_item_id','_order_item_id','woocommerce_order_item_id','tc_order_item_id','_tc_order_item_id') as $key) {
   $value = get_post_meta($instance_id,$key,true);
   if (is_numeric($value)) { $meta_ids[$key] = (int)$value; }
  }
  $items = array();
  foreach ($order->get_items('line_item') as $item_id=>$item) {
   $items[] = array('item_id'=>(int)$item_id,'product_id'=>$item->get_product_id(),'variation_id'=>$item->get_variation_id(),'session_id'=>(int)$item->get_meta('mdg_session_id'));
  }
  $maps = MDG_DB::table('order_map'); $sessions = MDG_DB::table('sessions');
  $mapped = $wpdb->get_results($wpdb->prepare("SELECT m.order_item_id,m.event_id,m.session_id,s.start_at,s.end_at FROM {$maps} m JOIN {$sessions} s ON s.id=m.session_id AND s.event_id=m.event_id WHERE m.order_id=%d",$ids_row['order_id']), ARRAY_A);
  $diagnostics[] = array('ticket_id'=>$instance_id,'resolved'=>$ids_row,'identity_meta'=>$meta_ids,'order_items'=>$items,'canonical_mapping'=>$mapped);
 }
 echo '<h2>Salt okunur kimlik eşleşmesi</h2><pre id="mdg-mapping-evidence">'.esc_html(wp_json_encode($diagnostics,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';

 echo '<table class="widefat"><thead><tr><th>Bilet</th><th>Sipariş</th><th>Kalem</th><th>Seans</th><th>Şablon</th></tr></thead><tbody>';
 foreach ($candidates as $id=>$row) {
  echo '<tr><td>'.absint($id).'</td><td>'.absint($row['ids']['order_id']).'</td><td>'.absint($row['ids']['item_id']).'</td><td>'.esc_html($row['datetime']).'</td><td>'.absint($row['template']).'</td></tr>';
 }
 echo '</tbody></table><form method="post">'; wp_nonce_field('mdg_pdf_canary_20261003');
 echo '<p><label>Bilet ID <input name="ticket_id" type="number" required></label> <button class="button button-primary" name="generate" value="1">PDF kontrol et</button></p></form>';
 if (isset($_POST['generate'])) {
  check_admin_referer('mdg_pdf_canary_20261003');
  $id = absint($_POST['ticket_id'] ?? 0);
  if (!isset($candidates[$id]) || !$candidates[$id]['template']) { echo '<p>Ödenmiş bilet veya şablon bulunamadı.</p>'; return; }
  $row = $candidates[$id];
  $before = hash('sha256',serialize(get_post_meta($id)));
  $order_before = wc_get_order($row['ids']['order_id'])->get_status();
  $pdf = apply_filters('tickera_ticket_designer_pre_generate',null,$id,$row['ids']['variation_id'] ?: $row['ids']['product_id'],'d_'.$row['template'],'S','session-canary.pdf');
  $after = hash('sha256',serialize(get_post_meta($id)));
  $unchanged = $before === $after && $order_before === wc_get_order($row['ids']['order_id'])->get_status();
  echo '<p>Bilet/sipariş durumu: '.($unchanged?'DEĞİŞMEDİ':'KONTROL GEREKLİ').'</p>';
  if (is_string($pdf) && strncmp($pdf,'%PDF-',5) === 0) {
   echo '<p>PDF üretildi — '.esc_html($row['datetime']).'</p><a class="button" download="session-canary-'.absint($id).'.pdf" href="data:application/pdf;base64,'.base64_encode($pdf).'">Kontrol PDF indir</a>';
  } else { echo '<p>PDF üretilemedi.</p>'; }
 }
 echo '</div>';
}

