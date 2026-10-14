<?php
define('ABSPATH',__DIR__);
class WP_Error { public $code; public function __construct($c,$m='',$d=[]){$this->code=$c;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(...$x){}
require __DIR__.'/../../integrations/milano/shared-checkout/milano-shared-checkout-handoff.php';
$base=['event_id'=>'24','session_id'=>'150','children'=>'0','adults'=>'0','families'=>'1'];
function check($yes,$label){if(!$yes){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}echo "PASS ".$label."\n";}
check(Milano_Shared_Checkout_Handoff::selection($base)['families']===1,'family selection');
check(!is_wp_error(Milano_Shared_Checkout_Handoff::selection(array_merge($base,['session_id'=>'151']))),'second session');
foreach([
 'negative'=>['adults'=>'-1'],'fraction'=>['children'=>'0.5'],'array'=>['adults'=>[1]],
 'zero'=>['families'=>'0'],'too_many_families'=>['families'=>'11'],'adult_plus_family_limit'=>['adults'=>'19'],
 'missing_event'=>['event_id'=>'0'],'missing_session'=>['session_id'=>'0'],
 'client_price'=>['price'=>'1'],'client_amount'=>['amount'=>'1'],'client_product'=>['product_id'=>'1'],
 'redirect_injection'=>['redirect'=>'https://evil.invalid'],'return_injection'=>['return_url'=>'https://evil.invalid']
] as $label=>$changes){check(is_wp_error(Milano_Shared_Checkout_Handoff::selection(array_merge($base,$changes))),$label);}
check(Milano_Shared_Checkout_Handoff::family_enabled([24,7],24),'list event enable');
check(!Milano_Shared_Checkout_Handoff::family_enabled(range(1,30),31),'list index is not event enable');
check(Milano_Shared_Checkout_Handoff::family_enabled([24=>1,7=>1],24),'map event enable');
check(!Milano_Shared_Checkout_Handoff::family_enabled([24=>0,7=>1],24),'map disabled');
check(!Milano_Shared_Checkout_Handoff::family_enabled([],24),'empty disabled');
