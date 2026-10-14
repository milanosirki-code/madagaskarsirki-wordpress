<?php
/** Kommo reminder field hotfix. No message sending; payment delivery stays gated. */
if (!defined('ABSPATH')) { exit; }

function mdg_kr_utc_timestamp($raw) {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string)$raw, new DateTimeZone('UTC'));
    return $dt && $dt->format('Y-m-d H:i:s') === $raw ? $dt->getTimestamp() : 0;
}

function mdg_kr_request($method, $path, $body = null) {
    if (!function_exists('ms_kommo_token_value') || !ms_kommo_token_value()) { return false; }
    $args = array('method'=>$method, 'timeout'=>15, 'headers'=>array('Authorization'=>'Bearer '.ms_kommo_token_value(),'Content-Type'=>'application/json'));
    if ($body !== null) { $args['body']=wp_json_encode($body); }
    $response=wp_remote_request('https://milanosirki.kommo.com/api/v4'.$path,$args);
    if (is_wp_error($response)) { return false; }
    $status=wp_remote_retrieve_response_code($response);
    return $status>=200 && $status<300 ? json_decode(wp_remote_retrieve_body($response),true) : false;
}

function mdg_kr_survey_timestamp($start, $paid, $status, $source_valid, $now) {
    return $source_valid && $paid && in_array($status,array('processing','completed'),true)
        && $start>0 && $start >= $now-7*86400 ? $start : null;
}

function mdg_kr_survey_date($order, $context) {
    if (in_array($context['reason_code'],array('MAPPING_MISSING','EVENT_CANCELLED'),true)
        || count($context['session_ids'])!==1 || count($context['event_ids'])!==1) { return null; }
    global $wpdb;
    $session=$wpdb->get_row($wpdb->prepare('SELECT start_at,status,event_id FROM '.MDG_DB::table('sessions').' WHERE id=%d',(int)$context['session_ids'][0]));
    if ($wpdb->last_error || !$session || (int)$session->event_id!==(int)$context['event_ids'][0]) { return null; }
    $event=MDG_Events::get((int)$session->event_id);
    if (!$event) { return null; }
    $statuses=array((string)$session->status,(string)$event->status);
    foreach ($context['program_ids'] as $program_id) {
        $program=MMC_Program_Service::get_program((int)$program_id);
        if (!$program) { return null; }
        $statuses[]=(string)$program->status;
        $event_ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT event_id FROM {$wpdb->prefix}mmc_sales_mappings WHERE is_active=1 AND program_id=%d",(int)$program_id));
        if ($wpdb->last_error || !$event_ids) { return null; }
        foreach ($event_ids as $event_id) {
            $mmc_event=MMC_Event_Service::get_event((int)$event_id);
            if (!$mmc_event) { return null; }
            $statuses[]=(string)$mmc_event->status;
        }
    }
    foreach ($statuses as $status) {
        if (!$status || in_array($status,array('cancelled','postponed'),true)) { return null; }
    }
    return mdg_kr_survey_timestamp(mdg_kr_utc_timestamp((string)$session->start_at),$order->is_paid(),$order->get_status(),true,time());
}


/** Revalidate authorization without reopening one-send locks or treating a dry-run as delivery. */
function mdg_kr_payment_gate($eligible, $candidate, $previous_state, $previous_link, $locked, $paid_success, $wc_status, $crm_status) {
    $terminal=array('SEND_STARTED','SENT','SEND_ERROR','LOCKED');
    if ($locked || in_array($previous_state,$terminal,true)) {
        return array('state'=>in_array($previous_state,$terminal,true)?$previous_state:'LOCKED','link'=>$previous_link);
    }
    $stage_matches=($wc_status==='pending' && (int)$crm_status===110416823)
        || ($wc_status==='failed' && (int)$crm_status===143);
    if (!$eligible || !$candidate || $paid_success || !$stage_matches) {
        return array('state'=>'EXCLUDED','link'=>null);
    }
    // Only an explicit, still-valid per-order authorization is preserved. Never grant ALLOW_SEND here.
    $state=$previous_state==='ALLOW_SEND' && $previous_link===$candidate ? 'ALLOW_SEND':'PREVIEW_ELIGIBLE';
    return array('state'=>$state,'link'=>$candidate);
}

function mdg_kr_sync($order_id) {
    try {
        if (!function_exists('wc_get_order') || !function_exists('ms_oh_order_context') || !class_exists('MDG_DB')) { return; }
        $order=wc_get_order((int)$order_id);
        if (!$order || !function_exists('ms_kommo_find_order_lead')) { return; }
        $lead_id=ms_kommo_find_order_lead((int)$order_id);
        if (is_wp_error($lead_id) || !$lead_id) { return; }
        $lead=mdg_kr_request('GET','/leads/'.(int)$lead_id);
        if (!$lead || (int)($lead['pipeline_id']??0)!==14297695 || ($lead['name']??'')!=='Order#'.(int)$order_id) { return; }
        $context=ms_oh_order_context($order);
        $fields=array();
        // A single canonical session is required; ambiguous/multi-session orders fail closed.
        $start=0;
        if (!$context['reason_code'] && count($context['session_ids'])===1) {
            global $wpdb;
            $raw=$wpdb->get_var($wpdb->prepare('SELECT start_at FROM '.MDG_DB::table('sessions').' WHERE id=%d',(int)$context['session_ids'][0]));
            if (!$wpdb->last_error) { $start=mdg_kr_utc_timestamp((string)$raw); }
        }
        $current=array();
        foreach (($lead['custom_fields_values']??array()) as $field) { $current[(int)$field['field_id']]=$field['values'][0]['value']??null; }
        // Clearing invalid dates prevents reminders for cancelled or started sessions.
        $date=$order->is_paid() && in_array($order->get_status(),array('processing','completed'),true) && $start>time() ? $start : null;
        if (($current[2460832]??null)!=$date) { $fields[]=array('field_id'=>2460832,'values'=>$date===null?null:array(array('value'=>$date))); }
        $survey_date=mdg_kr_survey_date($order,$context);
        if (($current[2460854]??null)!=$survey_date) { $fields[]=array('field_id'=>2460854,'values'=>$survey_date===null?null:array(array('value'=>$survey_date))); }
        $candidate=null; $eligible=false;
        if (function_exists('ms_oh_dry_run_report') && $order->needs_payment()) {
            // Third argument ignores only dry-run history; paid/replacement/cancel/receipt checks remain.
            $report=ms_oh_dry_run_report($order,null,true);
            if ($report['eligibility']==='ELIGIBLE' && $start>time()) {
                $url=$order->get_checkout_payment_url();
                if (wp_parse_url($url,PHP_URL_SCHEME)==='https' && strtolower((string)wp_parse_url($url,PHP_URL_HOST))===strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))) {
                    $eligible=true; $candidate=$url;
                }
            }
        }
        $tag_names=array();
        foreach (($lead['_embedded']['tags']??array()) as $tag) { $tag_names[]=(string)($tag['name']??''); }
        $locked=(bool)array_intersect($tag_names,array('mdg_odeme_hatirlatma_baslatildi','mdg_odeme_hatirlatma_gonderildi','mdg_odeme_hatirlatma_hata'));
        $decision=mdg_kr_payment_gate($eligible,$candidate,(string)($current[2460836]??''),$current[2460834]??null,$locked,in_array('WooCommerce Ödeme Başarılı',$tag_names,true),$order->get_status(),$lead['status_id']??0);
        $state=$decision['state']; $link=$decision['link'];
        foreach (array(2460834=>$link,2460836=>$state) as $id=>$value) {
            if (($current[$id]??null)!=$value) { $fields[]=array('field_id'=>$id,'values'=>$value===null?null:array(array('value'=>$value))); }
        }
        if ($fields) { mdg_kr_request('PATCH','/leads/'.(int)$lead_id,array('custom_fields_values'=>$fields)); }
    } catch (Throwable $e) { /* No credentials, payment URLs or customer data in logs. */ }
}

function mdg_kr_schedule_order($order_id) {
    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action(time()+30,'mdg_kr_sync_order',array((int)$order_id),'mdg-kommo-reminder-fields',true);
    }
}
add_action('mdg_kr_sync_order','mdg_kr_sync',10,1);
foreach (array('woocommerce_payment_complete','woocommerce_order_status_processing','woocommerce_order_status_completed','woocommerce_order_status_cancelled','woocommerce_order_status_refunded','woocommerce_order_status_failed','woocommerce_order_status_pending') as $hook) {
    add_action($hook,'mdg_kr_schedule_order',99,1);
}

function mdg_kr_batch($page=1) {
    if (!function_exists('wc_get_orders') || !function_exists('as_schedule_single_action')) { return; }
    $ids=wc_get_orders(array('status'=>array('processing','completed','pending','failed'),'date_created'=>'>='. (time()-30*DAY_IN_SECONDS),'limit'=>50,'page'=>(int)$page,'orderby'=>'ID','order'=>'ASC','return'=>'ids'));
    foreach ((array)$ids as $offset=>$id) { as_schedule_single_action(time()+$offset+1,'mdg_kr_sync_order',array((int)$id),'mdg-kommo-reminder-fields',true); }
    if (count((array)$ids)===50 && $page<100) { as_schedule_single_action(time()+65,'mdg_kr_batch',array((int)$page+1),'mdg-kommo-reminder-fields',true); }
}
add_action('mdg_kr_batch','mdg_kr_batch',10,1);
add_action('init',function() {
    if (function_exists('as_schedule_recurring_action') && !as_next_scheduled_action('mdg_kr_batch',array(1),'mdg-kommo-reminder-fields')) {
        as_schedule_recurring_action(time()+15,30*MINUTE_IN_SECONDS,'mdg_kr_batch',array(1),'mdg-kommo-reminder-fields',true);
    }
});

/** Private, read-only current eligibility review; refresh writes only existing CRM fields, never sends. */
add_action('admin_menu',function() {
    add_submenu_page('mmc-dashboard','Ödeme Hatırlatma Kontrolü','Ödeme Hatırlatma Kontrolü','manage_woocommerce','mdg-payment-reminder-review','mdg_kr_review_page');
},90);
function mdg_kr_review_page() {
    if (!current_user_can('manage_woocommerce')) { wp_die('Yetkisiz erişim.'); }
    $notice='';
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['mdg_kr_refresh'])) {
        check_admin_referer('mdg_kr_review_refresh');
        $id=absint($_POST['mdg_kr_refresh']);
        $order=function_exists('wc_get_order')?wc_get_order($id):false;
        if ($order && $order->get_type()==='shop_order' && in_array($order->get_status(),array('pending','failed'),true)) {
            mdg_kr_sync($id);
            $notice='Seçili siparişin CRM alanları yeniden kontrol edildi. Bu işlem gönderim onayı vermez ve mesaj göndermez.';
        }
    }
    echo '<div class="wrap"><h1>Ödeme Hatırlatma Kontrolü</h1><p>Ödeme bekleyen ve başarısız siparişler burada güncel kurallarla değerlendirilir. Deneme geçmişi gerçek gönderim sayılmaz. Daha sonra ödenmiş sipariş, kapanmış seans ve önceki gönderim kilidi korunur.</p>';
    if ($notice) { echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>'; }
    if (!function_exists('ms_oh_dry_run_report') || !function_exists('wc_get_orders')) { echo '<p>Uygunluk kaynağı yüklenemedi.</p></div>'; return; }
    $page=max(1,absint($_GET['review_page']??1));
    $result=wc_get_orders(array('status'=>array('pending','failed'),'date_created'=>'>='.(time()-30*DAY_IN_SECONDS),'limit'=>30,'page'=>$page,'orderby'=>'ID','order'=>'DESC','paginate'=>true));
    $orders=$result->orders??array();
    echo '<p>Son 30 gün: '.esc_html((string)($result->total??0)).' kayıt. Bu sayı mesaj gönderilecek kişi sayısı değildir.</p>';
    echo '<table class="widefat striped"><thead><tr><th>Sipariş</th><th>Durum</th><th>Güncel uygunluk</th><th>Açıklama</th><th>CRM alanları</th></tr></thead><tbody>';
    foreach ($orders as $order) {
        $report=ms_oh_dry_run_report($order,null,true);
        $id=(int)$order->get_id();
        echo '<tr><td><a href="'.esc_url($order->get_edit_order_url()).'">#'.esc_html((string)$id).'</a></td><td>'.esc_html(wc_get_order_status_name($order->get_status())).'</td><td>'.esc_html($report['eligibility']).'</td><td>'.esc_html($report['reason']).'</td><td><form method="post">';
        wp_nonce_field('mdg_kr_review_refresh');
        echo '<button class="button" name="mdg_kr_refresh" value="'.esc_attr((string)$id).'">Alanları yeniden kontrol et</button></form></td></tr>';
    }
    if (!$orders) { echo '<tr><td colspan="5">Kayıt bulunamadı.</td></tr>'; }
    echo '</tbody></table>';
    for ($i=1;$i<=(int)($result->max_num_pages??1);$i++) {
        echo '<a class="button" href="'.esc_url(add_query_arg(array('page'=>'mdg-payment-reminder-review','review_page'=>$i),admin_url('admin.php'))).'">'.esc_html((string)$i).'</a> ';
    }
    echo '<p>İlk gerçek gönderim için doğrulanmış tek sipariş ve mevcut onaylı şablon kullanılmalıdır. Alan yenileme ALLOW_SEND oluşturmaz.</p></div>';
}
