<?php
declare(strict_types=1);

/** Run offline; never touches a Woo site, database, QR or PDF. */
define('ABSPATH', __DIR__);
function absint($v):int { return abs((int)$v); }
class WP_Error {
    private string $code;
    private string $message;
    public function __construct($code,$message='') { $this->code=(string)$code; $this->message=(string)$message; }
    public function get_error_code():string { return $this->code; }
    public function get_error_message():string { return $this->message; }
}
function is_wp_error($v):bool { return $v instanceof WP_Error; }
$target_ids=[6058,6059,6060,6061,6062,6063];
$meta=[];
foreach ($target_ids as $id) {
    $meta[$id]=['tc_designer_template_id'=>29,'_ticket_template'=>'d_29'];
}
$options=[
    'mdg_ticket_template_qr_bindings_v1'=>[
        24=>[
            'event_id'=>24, 'base_template_id'=>28,'clone_template_id'=>29,
            'product_ids'=>[6058,6061], 'variation_ids'=>[6059,6060,6062,6063]
        ]
    ]
];
function get_option($key,$fallback=[]){
    global $options;
    return $options[$key]??$fallback;
}
function get_post_meta($id,$key,$single=true) {
    global $meta;
    return $meta[$id][$key]??'';
}
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-ticket-template-qr-binder.php';
$m=new ReflectionMethod('MDG_Ticket_Template_QR_Binder','base_template_for_existing_binding');
$passes=0;
function check($ok,$name) {
    global $passes;
    if (!$ok) { fwrite(STDERR,"FAIL ".$name."\n"); exit(1); }
    $passes++; echo "PASS ".$name."\n";
}
$product_ids=[6058,6061];
$variation_ids=[6059,6060,6062,6063];
$call=function($event,$detected,$products,$variations)use($m){
    return $m->invoke(null,$event,$detected,$products,$variations);
};
check($call(24,29,$product_ids,$variation_ids)===28,'bound clone 29 correctly resolves original base 28');
check($call(24,28,$product_ids,$variation_ids)===28,'base itself remains permitted when selectors align');
check($call(24,30,$product_ids,$variation_ids) instanceof WP_Error,'unknown clone blocks automatic rebind');
check($call(24,29,[6058],[6059,6060,6062,6063]) instanceof WP_Error,'removed session product blocks automatic rebind');
check($call(24,29,[6058,6061],array_merge($variation_ids,[6200])) instanceof WP_Error,'added ticket variation blocks automatic rebind');
check($call(99,31,$product_ids,$variation_ids)===31,'unbound event keeps existing first-time selection');
$meta[6062]['_ticket_template']='d_28';
$r=$call(24,29,$product_ids,$variation_ids);
check(is_wp_error($r)&&$r->get_error_code()==='mdg_qr_binding_selector_drift','stale PDF selector refuses rebind');
$meta[6062]['_ticket_template']='d_29';
$meta[6059]['tc_designer_template_id']=28;
$r=$call(24,29,$product_ids,$variation_ids);
check(is_wp_error($r)&&$r->get_error_code()==='mdg_qr_binding_selector_drift','stale Designer selector refuses rebind');
$meta[6059]['tc_designer_template_id']=29;
$options['mdg_ticket_template_qr_bindings_v1'][24]['base_template_id']=0;
check(is_wp_error($call(24,29,$product_ids,$variation_ids)),'missing original base refuses rebind');
$options['mdg_ticket_template_qr_bindings_v1'][24]['base_template_id']=28;
check($call(24,29,array_reverse($product_ids),array_reverse($variation_ids))===28,'reordered object list is canonical');
check(count($meta)===6&&count($options)===1,'pure preflight makes no object or setting changes');
echo "ALL $passes offline QR template rebind guard checks passed.\n";
