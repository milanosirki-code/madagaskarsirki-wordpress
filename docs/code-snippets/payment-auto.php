<?php
/** Payment reminder production dispatcher. Dependencies: snippets 103 and 177.
 * No checkout requests to CRM. New orders only; atomic at-most-once launch.
 * Rollback: deactivate this snippet; keep durable launch locks.
 */
if (!defined('ABSPATH')) { exit; }

function mdg_pa_http($method, $path, $body = null) {
    if (!function_exists('ms_kommo_token_value') || !ms_kommo_token_value()) { return array('status'=>0); }
    $args = array('method'=>$method,'timeout'=>15,'headers'=>array('Authorization'=>'Bearer '.ms_kommo_token_value(),'Content-Type'=>'application/json'));
    if ($body !== null) { $args['body']=wp_json_encode($body); }
    $r=wp_remote_request('https://milanosirki.kommo.com/api/v4'.$path,$args);
    if (is_wp_error($r)) { return array('status'=>0); }
    return array('status'=>(int)wp_remote_retrieve_response_code($r),'data'=>json_decode(wp_remote_retrieve_body($r),true));
}

function mdg_pa_preview($id, $manual = false) {
    foreach (array('wc_get_order','ms_oh_dry_run_report','ms_kommo_find_order_lead','mdg_kr_sync') as $fn) {
        if (!function_exists($fn)) { return array('ready'=>false,'reason'=>'DEPENDENCY_MISSING'); }
    }
    $order=wc_get_order((int)$id);
    if (!$order || $order->get_type()!=='shop_order') { return array('ready'=>false,'reason'=>'ORDER_MISSING'); }
    $created=$order->get_date_created();
    if (!$created || (!$manual && $created->getTimestamp()<(int)get_option('mdg_pa_enabled_at',PHP_INT_MAX))) { return array('ready'=>false,'reason'=>'BEFORE_ACTIVATION'); }
    if (time()-$created->getTimestamp()<1200) { return array('ready'=>false,'reason'=>'WAITING'); }
    if (get_option('mdg_pa_launch_'.(int)$id,false)!==false) { return array('ready'=>false,'reason'=>'LAUNCH_LOCK'); }
    $report=ms_oh_dry_run_report($order,null,true);
    if ($report['eligibility']!=='ELIGIBLE') { return array('ready'=>false,'reason'=>$report['exclusion_reason']?:'INELIGIBLE'); }
    $lead_id=ms_kommo_find_order_lead((int)$id);
    if (is_wp_error($lead_id) || !$lead_id) { return array('ready'=>false,'reason'=>'CRM_MISSING'); }
    $response=mdg_pa_http('GET','/leads/'.(int)$lead_id);
    $lead=$response['data']??array();
    if ($response['status']!==200 || ($lead['name']??'')!=='Order#'.(int)$id || (int)($lead['pipeline_id']??0)!==14297695) { return array('ready'=>false,'reason'=>'CRM_IDENTITY'); }
    $fields=array(); foreach (($lead['custom_fields_values']??array()) as $f) { $fields[(int)$f['field_id']]=$f['values'][0]['value']??null; }
    $tags=array_column($lead['_embedded']['tags']??array(),'name');
    $locked=array('WooCommerce Ödeme Başarılı','mdg_odeme_hatirlatma_baslatildi','mdg_odeme_hatirlatma_gonderildi','mdg_odeme_hatirlatma_hata');
    if (array_intersect($tags,$locked) || in_array($fields[2460836]??'',array('SEND_STARTED','SENT','SEND_ERROR','LOCKED'),true)) { return array('ready'=>false,'reason'=>'CRM_LOCK'); }
    $stage=($order->get_status()==='pending' && (int)$lead['status_id']===110416823) || ($order->get_status()==='failed' && (int)$lead['status_id']===143);
    $url=$order->get_checkout_payment_url();
    if (!$stage || !$order->needs_payment() || wp_parse_url($url,PHP_URL_SCHEME)!=='https' || strtolower((string)wp_parse_url($url,PHP_URL_HOST))!==strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))) { return array('ready'=>false,'reason'=>'STAGE_OR_LINK'); }
    return array('ready'=>true,'reason'=>'ELIGIBLE','lead_id'=>(int)$lead_id,'link'=>$url);
}

function mdg_pa_dispatch($id, $manual = false) {
    $id=(int)$id;
    try {
        $p=mdg_pa_preview($id,$manual);
        if (!$p['ready']) { return array('result'=>'SKIPPED','reason'=>$p['reason']); }
        // Atomic unique option: a timeout is never automatically retried.
        $key='mdg_pa_launch_'.$id;
        if (!add_option($key,array('state'=>'CLAIMED','at'=>time()),'',false)) { return array('result'=>'LOCKED'); }
        $r=mdg_pa_http('PATCH','/leads/'.$p['lead_id'],array('custom_fields_values'=>array(
            array('field_id'=>2460834,'values'=>array(array('value'=>$p['link']))),
            array('field_id'=>2460836,'values'=>array(array('value'=>'ALLOW_SEND')))
        )));
        $state='FIELD_ERROR'; $status=$r['status'];
        if ($status>=200 && $status<300) {
            // Re-read Woo after CRM I/O; never knowingly launch a now-paid order.
            $fresh=wc_get_order($id);
            $report=$fresh?ms_oh_dry_run_report($fresh,null,true):array();
            if (($report['eligibility']??'')==='ELIGIBLE' && $fresh->needs_payment()) {
                $r=mdg_pa_http('POST','/bots/run',array(array('bot_id'=>118588,'entity_id'=>$p['lead_id'],'entity_type'=>'leads')));
                $status=$r['status']; $state=$status===202?'LAUNCH_ACCEPTED':'LAUNCH_ERROR';
            } else { $state='PAYMENT_CHANGED'; }
        }
        if ($state!=='LAUNCH_ACCEPTED') {
            mdg_pa_http('PATCH','/leads/'.$p['lead_id'],array('custom_fields_values'=>array(array('field_id'=>2460836,'values'=>array(array('value'=>'LOCKED'))))));
        }
        update_option($key,array('state'=>$state,'at'=>time(),'http_status'=>$status),false);
        update_option('mdg_pa_last_result',array('order_id'=>$id,'state'=>$state,'at'=>time(),'http_status'=>$status),false);
        return array('result'=>$state,'http_status'=>$status);
    } catch (Throwable $e) { return array('result'=>'ERROR_REVIEW_REQUIRED'); }
}

function mdg_pa_schedule($id) {
    try {
        if (!function_exists('wc_get_order') || !function_exists('as_schedule_single_action')) { return; }
        $o=wc_get_order((int)$id); $d=$o?$o->get_date_created():false;
        if (!$d || $d->getTimestamp()<(int)get_option('mdg_pa_enabled_at',PHP_INT_MAX)) { return; }
        as_schedule_single_action(max(time()+5,$d->getTimestamp()+1200),'mdg_pa_due',array((int)$id),'mdg-payment-production',true);
    } catch (Throwable $e) { /* Never interrupt checkout. */ }
}
foreach (array('woocommerce_new_order','woocommerce_order_status_pending','woocommerce_order_status_failed') as $hook) { add_action($hook,'mdg_pa_schedule',100,1); }
add_action('mdg_pa_due','mdg_pa_dispatch',10,1);
function mdg_pa_sweep() {
    if (!function_exists('wc_get_orders')) { return; }
    // Recover missed hooks / CRM lag; existing durable launch claims remain closed.
    $cutoff=max((int)get_option('mdg_pa_enabled_at',time()),time()-86400);
    $page=1;
    do {
        $ids=wc_get_orders(array('status'=>array('pending','failed'),'date_created'=>'>='.$cutoff,'limit'=>100,'page'=>$page,'return'=>'ids','orderby'=>'ID','order'=>'ASC'));
        foreach ($ids as $id) {
            if (get_option('mdg_pa_launch_'.(int)$id,false)===false) { mdg_pa_schedule($id); }
        }
        $page++;
    } while (count($ids)===100 && $page<=20);
}
add_action('mdg_pa_sweep','mdg_pa_sweep');
add_action('init',function() {
    add_option('mdg_pa_enabled_at',time(),'',false);
    if (function_exists('as_schedule_recurring_action') && !as_next_scheduled_action('mdg_pa_sweep',array(),'mdg-payment-production')) {
        as_schedule_recurring_action(time()+60,300,'mdg_pa_sweep',array(),'mdg-payment-production',true);
    }
});
add_action('rest_api_init',function() {
    register_rest_route('mdg-payment-production/v1','/status',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_woocommerce');},'callback'=>function(){
        $rows=array();
        foreach (wc_get_orders(array('status'=>array('pending','failed'),'limit'=>10,'orderby'=>'date','order'=>'DESC','return'=>'ids')) as $id) {
            $p=mdg_pa_preview($id,true); unset($p['link'],$p['lead_id']); $rows[]=array('order_id'=>(int)$id,'preflight'=>$p);
        }
        return array('enabled_at'=>(int)get_option('mdg_pa_enabled_at',0),'last_result'=>get_option('mdg_pa_last_result',null),'recent'=>$rows);
    }));
    register_rest_route('mdg-payment-production/v1','/dispatch',array('methods'=>'POST','permission_callback'=>function(){return current_user_can('manage_woocommerce');},'args'=>array('order_id'=>array('type'=>'integer','required'=>true,'minimum'=>1)),'callback'=>function($request){return mdg_pa_dispatch((int)$request['order_id'],true);}));
});
