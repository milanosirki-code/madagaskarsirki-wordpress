<?php
/**
 * Isolated contract proving draft plugin has no accidental Woo checkout path.
 * Usage: php integrations/milano/tests/milano-sales-freeze-contract.php <scenario>
 * No actual Woo, WP, network, payment, tickets, database or runtime writes.
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
$GLOBALS['host']='milanosirki.com';
$GLOBALS['registered_hooks']=array();
$GLOBALS['notices']=array();
function home_url($path='/'){return 'https://'.$GLOBALS['host'].$path;}
function wp_parse_url($url,$part=PHP_URL_HOST){return parse_url($url,$part);}
function add_filter($name,$callback,$priority=10,$accepted_args=1){
    $GLOBALS['registered_hooks'][$name]=array($callback,$priority,$accepted_args,'filter');
}
function add_action($name,$callback,$priority=10,$accepted_args=1){
    $GLOBALS['registered_hooks'][$name]=array($callback,$priority,$accepted_args,'action');
}
function wc_add_notice($message,$status){$GLOBALS['notices'][]=array($message,$status);}
require dirname(__DIR__).'/milano-sales-freeze/milano-sales-freeze.php';
function chk($yes,$reason){if(!$yes){fwrite(STDERR,"FAIL ".$reason."\n");exit(1);}}
function exception_thrown($fn){
    try{$fn();return false;} catch(Exception $e){return strpos($e->getMessage(),'Milano Sirki')!==false;}
}
class Errors {
    public $items=array();
    public function add($code,$text){$this->items[$code]=$text;}
}
$required=array(
 'woocommerce_add_to_cart_validation','woocommerce_is_purchasable',
 'woocommerce_variation_is_purchasable','woocommerce_check_cart_items',
 'woocommerce_after_checkout_validation','woocommerce_checkout_create_order',
 'woocommerce_store_api_validate_add_to_cart',
 'woocommerce_store_api_checkout_update_order_from_request',
 'woocommerce_available_payment_gateways'
);
foreach($required as $hook){
    chk(isset($GLOBALS['registered_hooks'][$hook]),'missing hook '.$hook);
    chk($GLOBALS['registered_hooks'][$hook][1]===1,'hook too late '.$hook);
}
$case=$argv[1]??'milano_default';
switch($case){
case 'milano_default':
    chk(msf_is_milano_site() && msf_sales_frozen(),'Milano must be frozen by default');
    break;
case 'www':
    $GLOBALS['host']='www.milanosirki.com';
    chk(msf_sales_frozen(),'www bypass');break;
case 'uppercase_host':
    $GLOBALS['host']='MILANOSIRKI.COM';
    chk(msf_sales_frozen(),'case-sensitive bypass');break;
case 'trailing_dot':
    $GLOBALS['host']='milanosirki.com.';
    chk(msf_sales_frozen(),'trailing-dot bypass');break;
case 'host_spoofing':
    $_SERVER['HTTP_HOST']='madagaskarsirki.com';
    chk(msf_sales_frozen(),'request host spoofed server-configured Milano domain');break;
case 'query_switch':
    $_GET['enable_sales']='1';
    $_POST['checkout_enabled']='1';
    chk(msf_sales_frozen(),'query switch bypass');break;
case 'admin_switch':
    $GLOBALS['admin_is_logged_in']=true;
    chk(msf_sales_frozen(),'admin bypass');break;
case 'constant_switch':
    define('MILANO_SALES_ENABLED',true);
    chk(msf_sales_frozen(),'undocumented feature flag bypass');break;
case 'add_cart_true':
    chk(msf_reject_add_to_cart(true)===false,'add-to-cart accepted');
    chk(count($GLOBALS['notices'])===1,'cart lacks customer notice');break;
case 'add_cart_false':
    chk(msf_reject_add_to_cart(false)===false,'upstream denial overridden');break;
case 'purchasable':
    chk(msf_reject_purchasable(true)===false,'product purchasable');break;
case 'variation':
    chk(msf_reject_purchasable(true)===false,'variation purchasable');break;
case 'old_cart':
    msf_block_classic_cart();
    chk(count($GLOBALS['notices'])===1,'old cart was allowed');break;
case 'checkout_validation':
    $e=new Errors();
    msf_block_classic_checkout(array('fake_cart'=>'old'),$e);
    chk(isset($e->items['milano_sales_frozen']),'checkout validation bypass');break;
case 'classic_order':
    chk(exception_thrown(function(){msf_block_order_creation((object)array(),array());}),
        'classic order creation allowed');break;
case 'store_cart':
    chk(exception_thrown(function(){msf_block_store_cart((object)array(),array());}),
        'Store API cart allowed');break;
case 'store_checkout':
    chk(exception_thrown(function(){msf_block_store_checkout((object)array(),array());}),
        'Store API checkout allowed');break;
case 'gateway_hidden':
    chk(msf_hide_payment_gateways(array('paytr'=>(object)array()))===array(),
        'payment gateway still exposed');break;
case 'madagaskar_unchanged':
    $GLOBALS['host']='madagaskarsirki.com';
    chk(!msf_is_milano_site()&&!msf_sales_frozen(),'production Madagaskar blocked');
    chk(msf_reject_add_to_cart(true)===true,'Madagaskar cart blocked');
    chk(msf_reject_purchasable(true)===true,'Madagaskar purchasability changed');
    chk(msf_hide_payment_gateways(array('paytr'=>42))===array('paytr'=>42),'Madagaskar gateways hidden');
    $e=new Errors();msf_block_classic_checkout(array(),$e);
    chk(!$e->items,'Madagaskar checkout rejected');
    chk(!exception_thrown(function(){msf_block_order_creation((object)array(),array());}),
        'Madagaskar order blocked');
    chk(!exception_thrown(function(){msf_block_store_cart((object)array(),array());}),
        'Madagaskar Store API blocked');break;
case 'other_domain_unchanged':
    $GLOBALS['host']='localhost';
    chk(!msf_sales_frozen()&&msf_reject_add_to_cart(true)===true,
        'other domain blocked');break;
case 'lookalike_domain':
    $GLOBALS['host']='milanosirki.com.attacker.test';
    chk(!msf_is_milano_site(),'lookalike host incorrectly treated as Milano');break;
default:chk(false,'unknown scenario '.$case);
}
echo "PASS {$case}\n";
