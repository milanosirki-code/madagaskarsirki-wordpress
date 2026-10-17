<?php
define('ABSPATH','/');
$opts=[]; $calls=[]; $eligible=true; $paid=false; $after_patch_paid=false; $launch_status=202; $age=1800; $tags=[]; $crmstate='PREVIEW_ELIGIBLE'; $created_via='checkout';
function add_action(...$a){}
function get_option($k,$d=false){global $opts;return $opts[$k]??$d;}
function add_option($k,$v,...$rest){global $opts;if(array_key_exists($k,$opts))return false;$opts[$k]=$v;return true;}
function update_option($k,$v,...$rest){global $opts;$opts[$k]=$v;}
function ms_kommo_token_value(){return 'test';}
function wp_json_encode($x){return json_encode($x);}
function is_wp_error($x){return false;}
function wp_remote_retrieve_response_code($x){return $x['status'];}
function wp_remote_retrieve_body($x){return json_encode($x['data']??null);}
function home_url($x){return 'https://example.test/';}
function wp_parse_url($url,$key){return parse_url($url,$key);}
function mdg_kr_sync($id){}
function ms_kommo_find_order_lead($id){return 100;}
class Order {
 function get_type(){return 'shop_order';}
 function get_date_created(){global $age;return new DateTime('@'.(time()-$age));}
 function get_status(){global $paid;return $paid?'processing':'failed';}
 function needs_payment(){global $paid;return !$paid;}
 function get_checkout_payment_url(){return 'https://example.test/pay';}
}
function wc_get_order($id){return new Order;}
function ms_oh_dry_run_report(...$a){global $eligible,$paid;return ['eligibility'=>$eligible&&!$paid?'ELIGIBLE':'EXCLUDED','exclusion_reason'=>$paid?'PAYMENT_NOT_ELIGIBLE':'SESSION_STARTED'];}
function wp_remote_request($url,$args){
 global $calls,$tags,$crmstate,$launch_status,$paid,$after_patch_paid;
 $calls[]=$args['method'].' '.$url;
 if($args['method']==='GET')return ['status'=>200,'data'=>['name'=>'Order#1','pipeline_id'=>14297695,'status_id'=>143,'custom_fields_values'=>[['field_id'=>2460836,'values'=>[['value'=>$crmstate]]]],'_embedded'=>['tags'=>array_map(fn($n)=>['name'=>$n],$tags)]]];
 if($args['method']==='PATCH'){$paid=$after_patch_paid;return ['status'=>200];}
 return ['status'=>$launch_status];
}
require __DIR__.'/payment-auto.php';
function reset_case(){global $opts,$calls,$eligible,$paid,$after_patch_paid,$launch_status,$age,$tags,$crmstate;$opts=['mdg_pa_enabled_at'=>time()-3600];$calls=[];$eligible=true;$paid=false;$after_patch_paid=false;$launch_status=202;$age=1800;$tags=[];$crmstate='PREVIEW_ELIGIBLE';}
function check($yes,$name){if(!$yes)throw new Exception($name);echo "PASS $name\n";}
function launches(){global $calls;return count(array_filter($calls,fn($c)=>strpos($c,'POST ')===0));}
reset_case();check(mdg_pa_dispatch(1)['result']==='LAUNCH_ACCEPTED','eligible accepted');check(launches()===1,'one request');mdg_pa_dispatch(1);check(launches()===1,'repeat blocked');
reset_case();$age=1199;check(mdg_pa_dispatch(1)['reason']==='WAITING'&&launches()===0,'20 minutes enforced');
reset_case();$age=4000;check(mdg_pa_dispatch(1)['reason']==='BEFORE_ACTIVATION'&&launches()===0,'old backlog excluded');
reset_case();$paid=true;check(mdg_pa_dispatch(1)['reason']==='PAYMENT_NOT_ELIGIBLE'&&launches()===0,'paid excluded');
reset_case();$eligible=false;check(mdg_pa_dispatch(1)['reason']==='SESSION_STARTED'&&launches()===0,'domain exclusion honored');
foreach(['mdg_odeme_hatirlatma_baslatildi','mdg_odeme_hatirlatma_gonderildi','mdg_odeme_hatirlatma_hata','WooCommerce Ödeme Başarılı'] as $tag){reset_case();$tags=[$tag];check(mdg_pa_dispatch(1)['reason']==='CRM_LOCK'&&launches()===0,'tag lock '.$tag);}
reset_case();$after_patch_paid=true;check(mdg_pa_dispatch(1)['result']==='PAYMENT_CHANGED'&&launches()===0,'payment changes during IO');
reset_case();$launch_status=0;check(mdg_pa_dispatch(1)['result']==='LAUNCH_ERROR','timeout recorded');mdg_pa_dispatch(1);check(launches()===1,'unknown outcome never resent');
reset_case();$age=4000;check(mdg_pa_dispatch(1,true)['result']==='LAUNCH_ACCEPTED','explicit single pilot permits old eligible order');
