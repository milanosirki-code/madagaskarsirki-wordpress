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
        $state='EXCLUDED'; $link=null;
        if (function_exists('ms_oh_dry_run_report') && $order->needs_payment()) {
            $report=ms_oh_dry_run_report($order);
            if ($report['eligibility']==='ELIGIBLE' && $start>time()) {
                $candidate=$order->get_checkout_payment_url();
                if (wp_parse_url($candidate,PHP_URL_SCHEME)==='https' && strtolower((string)wp_parse_url($candidate,PHP_URL_HOST))===strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))) {
                    // PREVIEW state is deliberately insufficient to authorize Salesbot delivery.
                    $state='PREVIEW_ELIGIBLE'; $link=$candidate;
                }
            }
        }
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
