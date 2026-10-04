<?php
/** Issue #114 bounded one-file deployment. Temporary snippet119; restore original/passive afterwards.
 * No orders, ledger, mappings, family, payment or profile writes.
 * Apply/rollback require an administrator and exact Git blob compare-and-swap.
 */
if (!defined('ABSPATH')) { exit; }
add_action('rest_api_init', function () {
    register_rest_route('mmc-issue114/v1','/source',[
        'methods'=>['GET','POST'],
        'permission_callback'=>function(){return current_user_can('manage_options');},
        'callback'=>function($request){
            global $wpdb;
            $path=WP_PLUGIN_DIR.'/madagaskar-management-center/includes/class-mmc-sales-service.php';
            $blob=function($s){return sha1('blob '.strlen($s).chr(0).$s);};
            $raw=file_get_contents($path);
            if($raw===false){return new WP_Error('read_failed','Sales source could not be read.');}
            $hash=$blob($raw);
            $old='1af29700f0281f64c1d490fc7398b0408b812684'; $new='8d635658d0feebc5d63a2ba1d937eeec6cd2dda7';
            $out=['utc'=>gmdate('c'),'file'=>'wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php','before_blob'=>$hash,'before_sha256'=>hash('sha256',$raw)];
            if($request->get_method()==='POST'){
                $action=$request->get_param('action');
                if(!in_array($action,['apply','rollback'],true)){return new WP_Error('invalid_action','Use apply or rollback.');}
                $expected=$action==='apply'?$old:$new;
                $wanted=$action==='apply'?$new:$old;
                if($hash!==$expected){return new WP_Error('source_drift','Source hash differs; nothing changed.',$out);}
                $pairs=[
                    ['COALESCE(SUM(gross_amount),0) gross_revenue,','COALESCE(SUM(CASE WHEN paid_at IS NOT NULL
                        AND order_status NOT IN (\'failed\',\'cancelled\',\'pending\',\'checkout-draft\')
                        THEN gross_amount ELSE 0 END),0) gross_revenue,
                    COALESCE(SUM(gross_amount),0) nominal_order_value,'],
                    ['    public static function summary( $event_id ) {','    /**
     * Collected gross is the original mapped line amount for a recorded payment,
     * before refunds. paid_at is captured from WC_Order::get_date_paid(); unlike
     * is_paid(), it retains payment history after a full refund. Nominal values
     * remain in the ledger and are exposed separately, never as collected sales.
     */
    public static function summary( $event_id ) {']
                ];
                $updated=$raw;
                foreach($pairs as $pair){
                    $from=$action==='apply'?$pair[0]:$pair[1]; $to=$action==='apply'?$pair[1]:$pair[0];
                    if(substr_count($updated,$from)!==1){return new WP_Error('match_failed','Replacement must match once.');}
                    $updated=str_replace($from,$to,$updated);
                }
                if($blob($updated)!==$wanted){return new WP_Error('payload_mismatch','Result differs from CI-validated source.');}
                $out['snapshot']=$wpdb->get_row("SELECT COUNT(DISTINCT external_order_id) orders_count, SUM(gross_amount) old_gross, SUM(CASE WHEN paid_at IS NOT NULL AND order_status NOT IN ('failed','cancelled','pending','checkout-draft') THEN gross_amount ELSE 0 END) corrected_gross, SUM(net_amount) net_revenue, SUM(capacity_units) capacity, SUM(refunded_amount) refunds FROM {$wpdb->prefix}mmc_sales_ledger WHERE event_id=9",ARRAY_A);
                $temp=tempnam(dirname($path),'.issue114-');
                if(!$temp){return new WP_Error('temp_failed','Atomic staging unavailable.');}
                $mode=fileperms($path)&0777;
                $ok=file_put_contents($temp,$updated)===strlen($updated) && chmod($temp,$mode);
                if(!$ok || $blob(file_get_contents($path))!==$expected || !rename($temp,$path)){
                    if(is_file($temp))unlink($temp);
                    return new WP_Error('write_failed','Atomic replacement failed; inspect source hash.');
                }
                if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
                clearstatcache(true,$path);
                $readback=file_get_contents($path);
                $out['action']=$action;
                $out['after_blob']=$blob($readback);
                $out['after_sha256']=hash('sha256',$readback);
                $out['readback_matches']=$out['after_blob']===$wanted;
                return $out;
            }
            $warnings=[];
            set_error_handler(function($severity,$message,$file,$line)use(&$warnings){
                if(error_reporting()&$severity)$warnings[]=['severity'=>$severity,'file'=>basename($file),'line'=>$line];
                return false;
            });
            try{
                $out['summary']=MMC_Sales_Service::summary(9);
                $out['ability']=mdg_ai_mmc_sales_summary(['event_id'=>9]);
                $out['health']=MMC_Health_Service::summary();
                $out['snippet_health']=MMC_Snippet_Inventory_Service::summary();
                foreach(MMC_Dashboard_Service::program_rows(['include_closed'=>true]) as $row){
                    if((int)$row['program']->id===9)$out['dashboard_sales']=$row['sales'];
                }
                $saved=$_GET; $_GET=['program_id'=>9,'page'=>'mmc-sales'];
                ob_start();
                try{(new MMC_Sales_Admin())->page();$html=ob_get_contents();}
                finally{ob_end_clean();$_GET=$saved;}
                $out['sales_ui']=['rendered'=>strlen($html)>0,'net_value_present'=>strpos($html,number_format_i18n((float)$out['summary']['net_revenue'],2))!==false,'bytes'=>strlen($html)];
                $out['woo_payment_states']=[];
                $ids=$wpdb->get_col("SELECT DISTINCT external_order_id FROM {$wpdb->prefix}mmc_sales_ledger WHERE event_id=9");
                foreach($ids as $id){
                    $order=wc_get_order($id);if(!$order)continue;
                    $key=$order->get_status().'|paid='.(int)$order->is_paid().'|date='.(int)(bool)$order->get_date_paid().'|tx='.(int)(bool)$order->get_transaction_id();
                    $out['woo_payment_states'][$key]=($out['woo_payment_states'][$key]??0)+1;
                }
            }finally{restore_error_handler();}
            $out['php_warnings']=$warnings;
            return $out;
        }
    ]);
});
