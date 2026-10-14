<?php
/**
 * Offline contract for Classic + Store API pre-payment Woo gate.
 * All Woo, MDG, ticket, gateway and ledger objects are stubbed;
 * no WordPress request, customer, transaction or notification is touched.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
    private string $code;
    public function __construct($code,$text=''){$this->code=$code;}
    public function get_error_code(){return $this->code;}
}
function is_wp_error($v):bool {return $v instanceof WP_Error;}
$hooks=[];
function add_action($name,$cb,$priority=10,$accepted=1){
    global $hooks;
    $hooks[$name][$priority]=['cb'=>$cb,'priority'=>$priority,'accepted'=>$accepted];
}
class FakeOrder {
    public int $id=2001;
    public string $brand='milano';
    public string $ownership='milano';
    public array $spec=[];
    public bool $resolver_error=false;
    public function get_id():int {return $this->id;}
    public function get_meta($key,$single=true) {return $key==='_milano_brand'?$this->brand:'';}
}
class Milano_Canonical_Order_Claim {
    public static bool $exists=true;
    public static string $state='claimed';
    public static ?int $order_id=null;
    public static bool $bind_fail=false;
    public static int $existing_calls=0;
    public static int $bind_calls=0;
    public static function existing($intent,$quote,$event,$session,$amount,$units){
        self::$existing_calls++;
        if(!self::$exists)return new WP_Error('missing');
        if($event!==24||$session!==151||$amount!==110000||$units!==4
            ||$intent!==str_repeat('a',64)||$quote!==str_repeat('b',64))return new WP_Error('foreign');
        return ['state'=>self::$state,'wc_order_id'=>self::$order_id,'intent_key'=>$intent];
    }
    public static function attach_order($intent,$id){
        self::$bind_calls++;
        if(self::$bind_fail)return new WP_Error('bind-failed');
        if(self::$state==='claimed'){
            self::$state='bound';self::$order_id=$id;
            return ['wc_order_id'=>$id,'replayed'=>false];
        }
        if(self::$order_id===$id)return ['wc_order_id'=>$id,'replayed'=>true];
        return new WP_Error('conflict');
    }
}
class Milano_Prepayment_Order_Audit {
    public static bool $pass=true;
    public static int $calls=0;
    public static function inspect($order,$spec){
        self::$calls++;
        if(!self::$pass)return new WP_Error('bad_order_map_or_hold');
        return ['canonical_order_id'=>$order->get_id(),
                'held_capacity_units'=>4,'amount_minor'=>110000];
    }
}
require dirname(__DIR__,2).'/integrations/milano/checkout-contract/milano-woo-checkout-gate.php';
$passes=0;
function test($condition,$name):void {
    global $passes;
    if(!$condition){fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}
    $passes++;echo "PASS: ".$name."\n";
}
function reset_fake():void {
    Milano_Canonical_Order_Claim::$exists=true;
    Milano_Canonical_Order_Claim::$state='claimed';
    Milano_Canonical_Order_Claim::$order_id=null;
    Milano_Canonical_Order_Claim::$bind_fail=false;
    Milano_Canonical_Order_Claim::$bind_calls=0;
    Milano_Canonical_Order_Claim::$existing_calls=0;
    Milano_Prepayment_Order_Audit::$pass=true;
    Milano_Prepayment_Order_Audit::$calls=0;
}
$canonical_spec=[
    'brand'=>'milano','intent'=>str_repeat('a',64),'event_id'=>24,
    'session_id'=>151,'product_id'=>6061,'child_variation_id'=>6062,
    'adult_variation_id'=>6063,'child_type_id'=>344,'adult_type_id'=>345,
    'amount_minor'=>110000,'units'=>4,'quote_sha256'=>str_repeat('b',64)
];
test(is_wp_error(Milano_Woo_Checkout_Gate::register(null)),'refuse hook setup without trusted server resolver');
$registered=Milano_Woo_Checkout_Gate::register(
    function(FakeOrder $order)use($canonical_spec) {
        if($order->resolver_error)return new WP_Error('owner-unavailable');
        if($order->ownership==='madagaskar')return null;
        return $order->spec?:$canonical_spec;
    }
);
test($registered===true,'explicit trusted resolver registers once');
test(isset($hooks['woocommerce_checkout_order_processed'][4])
    && $hooks['woocommerce_checkout_order_processed'][4]['accepted']===3,
    'Classic Woo priority-4 validates order before MDG reserves seats');
test(isset($hooks['woocommerce_checkout_order_processed'][7])
    && $hooks['woocommerce_checkout_order_processed'][7]['accepted']===3,
    'Classic Woo priority-7 validates exact MDG hold before gateway');
test(isset($hooks['woocommerce_store_api_checkout_order_processed'][4])
    && isset($hooks['woocommerce_store_api_checkout_order_processed'][7]),
    'Store API has both priority-4 pre-MDG and priority-7 post-MDG guards');
test(is_wp_error(Milano_Woo_Checkout_Gate::register(static fn()=>null)),
    'cannot silently replace configured trusted resolver');
reset_fake();$o=new FakeOrder();
$early=Milano_Woo_Checkout_Gate::audit_before_reservation($o);
test(is_array($early) && ($early['milano_precheck_ok']??false)
    && $early['stock_reserved']===false
    && $early['payment_initiated']===false,
    'pre-MDG gate allows only existing validated family order without holding seats');
test(Milano_Canonical_Order_Claim::$bind_calls===0,
    'early gate never binds/creates canonical WC order');
reset_fake();$o=new FakeOrder();Milano_Canonical_Order_Claim::$exists=false;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_before_reservation($o))
    && Milano_Canonical_Order_Claim::$bind_calls===0,
    'missing intent is denied before any MDG hold');
reset_fake();$o=new FakeOrder();Milano_Canonical_Order_Claim::$state='reserving';
Milano_Canonical_Order_Claim::$order_id=2001;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_before_reservation($o)),
    'ambiguous reserving state denied before stock reservation');
reset_fake();$o=new FakeOrder();$o->ownership='madagaskar';$o->brand='';
test((Milano_Woo_Checkout_Gate::audit_before_reservation($o)['skipped_non_milano']??false)===true
    && Milano_Canonical_Order_Claim::$existing_calls===0,
    'early check never places Madagaskar orders in Milano ledger');
reset_fake();$o=new FakeOrder();
$earlyClassic=Milano_Woo_Checkout_Gate::classic_pre(2001,[],$o);
$earlyStore=Milano_Woo_Checkout_Gate::store_api_pre($o);
test(is_array($earlyClassic)&&is_array($earlyStore),
    'classic/store API pre-MDG hooks accept a valid prepared canonical order');
reset_fake();$o=new FakeOrder();Milano_Canonical_Order_Claim::$exists=false;
$earlyThrew=false;
try { Milano_Woo_Checkout_Gate::classic_pre(2001,[],$o); } catch (RuntimeException $e) {$earlyThrew=true;}
test($earlyThrew,'classic early guard throws before MDG hold when intent missing');
$earlyThrew=false;
try { Milano_Woo_Checkout_Gate::store_api_pre($o); } catch (RuntimeException $e) {$earlyThrew=true;}
test($earlyThrew,'store early guard throws before MDG hold when intent missing');
reset_fake();$o=new FakeOrder();
$result=Milano_Woo_Checkout_Gate::audit_existing_checkout($o);
test(is_array($result)&&($result['milano_preflight_ok']??false)===true,
    'canonical order passes once with valid MDG audit');
test(Milano_Canonical_Order_Claim::$bind_calls===1
    &&Milano_Prepayment_Order_Audit::$calls===1,
    'single prepayment Woo bind after MDG audit, no gateway action');
$result=Milano_Woo_Checkout_Gate::audit_existing_checkout($o);
test(is_array($result)&&Milano_Canonical_Order_Claim::$bind_calls===1,
    'repeat checkout hook reuses same Woo order and does not rebind');
$another=new FakeOrder();$another->id=2002;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout($another))
    &&Milano_Canonical_Order_Claim::$bind_calls===1,
    'competing Woo order for same intent blocked before gateway');
reset_fake();$other=new FakeOrder();$other->ownership='madagaskar';$other->brand='';
$r=Milano_Woo_Checkout_Gate::audit_existing_checkout($other);
test(is_array($r)&&($r['skipped_non_milano']??false),
    'existing Madagaskar orders bypass Milano adapter');
test(Milano_Prepayment_Order_Audit::$calls===0
    &&Milano_Canonical_Order_Claim::$existing_calls===0,
    'Madagaskar orders never enter Milano ledger');
reset_fake();$spoof=new FakeOrder();$spoof->brand='milano';$spoof->ownership='madagaskar';
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout($spoof)),
    'claimed Milano tag without canonical ownership denied');
reset_fake();$untagged=new FakeOrder();$untagged->brand='';
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout($untagged)),
    'actual Milano-owned Woo items without Milano brand tag denied');
reset_fake();$o=new FakeOrder();$o->resolver_error=true;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout($o)),
    'canonical owner lookup errors fail closed');
reset_fake();$o=new FakeOrder();$o->spec=$canonical_spec;$o->spec['quote_sha256']='INVALID';
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout($o)),
    'invalid signed immutable quote digest is rejected');
reset_fake();Milano_Canonical_Order_Claim::$exists=false;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder()))
    &&Milano_Prepayment_Order_Audit::$calls===0,
    'missing pre-claimed intent cannot be minted from checkout hook');
reset_fake();Milano_Prepayment_Order_Audit::$pass=false;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder()))
    &&Milano_Canonical_Order_Claim::$bind_calls===0,
    'no MDG four-seat hold or map means no order bind');
reset_fake();Milano_Canonical_Order_Claim::$bind_fail=true;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder())),
    'failed atomic bind stops gateway');
reset_fake();Milano_Canonical_Order_Claim::$state='bound';
Milano_Canonical_Order_Claim::$order_id=2001;
test(is_array(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder()))
    &&Milano_Canonical_Order_Claim::$bind_calls===0,
    'same already-bound Woo order remains idempotent');
reset_fake();Milano_Canonical_Order_Claim::$state='ready';
Milano_Canonical_Order_Claim::$order_id=2001;
$r=Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder());
test(is_array($r) && ($r['milano_preflight_ok']??false)
    && ($r['reservation_state']??'')==='ready'
    && Milano_Canonical_Order_Claim::$bind_calls===0
    && Milano_Prepayment_Order_Audit::$calls===1,
    'pre-reserved ready state replays on SAME order without a new bind/hold');
$another=new FakeOrder();$another->id=2002;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout($another))
    && Milano_Canonical_Order_Claim::$bind_calls===0,
    'ready state cannot authorize a second Woo order');
reset_fake();Milano_Canonical_Order_Claim::$state='reserving';
Milano_Canonical_Order_Claim::$order_id=2001;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder()))
    && Milano_Prepayment_Order_Audit::$calls===0
    && Milano_Canonical_Order_Claim::$bind_calls===0,
    'ambiguous reserving state blocks checkout BEFORE payment audit');
reset_fake();Milano_Canonical_Order_Claim::$state='ready';
Milano_Canonical_Order_Claim::$order_id=2001;
Milano_Prepayment_Order_Audit::$pass=false;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder()))
    && Milano_Canonical_Order_Claim::$bind_calls===0,
    'ready state with expired/missing hold cannot enter gateway');
reset_fake();Milano_Canonical_Order_Claim::$state='bound';
Milano_Canonical_Order_Claim::$order_id=2002;
test(is_wp_error(Milano_Woo_Checkout_Gate::audit_existing_checkout(new FakeOrder()))
    && Milano_Prepayment_Order_Audit::$calls===0,
    'bound state belonging to OTHER order fails closed');
reset_fake();$o=new FakeOrder();
$r=Milano_Woo_Checkout_Gate::classic(2001,[],$o);
test(is_array($r)&&($r['milano_preflight_ok']??false),
    'Classic Woo entrypoint accepts a valid prepayment order');
reset_fake();$o=new FakeOrder();
$r=Milano_Woo_Checkout_Gate::store_api($o);
test(is_array($r)&&($r['milano_preflight_ok']??false),
    'Store API Woo entrypoint accepts a valid prepayment order');
reset_fake();$o=new FakeOrder();Milano_Canonical_Order_Claim::$exists=false;
$thrown=false;
try{Milano_Woo_Checkout_Gate::classic(2001,[],$o);}catch(RuntimeException $e){$thrown=true;}
test($thrown,'Classic checkout raises pre-payment failure, not silent success');
$thrown=false;
try{Milano_Woo_Checkout_Gate::store_api($o);}catch(RuntimeException $e){$thrown=true;}
test($thrown,'Store API checkout raises pre-payment failure, not silent success');
test(Milano_Canonical_Order_Claim::$bind_calls===0,
    'failed checkout cannot create a binding/order/payment/ticket');
echo "ALL $passes Woo adapter contract assertions PASS — offline only.\n";
