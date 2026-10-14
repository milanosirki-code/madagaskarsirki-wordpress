<?php
/** Offline lifecycle contract; it does NOT include or run WooCommerce. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
    public function __construct(public string $code,public string $message=''){}
}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
$hooks=[];
function add_action($name,$cb,$priority=10,$args=1):void {
    global $hooks;
    $hooks[$name]=['cb'=>$cb,'priority'=>$priority,'args'=>$args];
}
final class Milano_Canonical_Order_Claim {
    public static array $seen=[];
    public static array $bound=[];
    public static int $attempts=0;
    public static function claim($intent,$quote,$event,$session,$amount,$units){
        self::$attempts++;
        if(isset(self::$seen[$intent]))return [
            'created'=>false,'state'=>self::$seen[$intent],'wc_order_id'=>self::$bound[$intent]??null
        ];
        self::$seen[$intent]='claimed';
        return ['created'=>true,'state'=>'claimed','wc_order_id'=>null];
    }
    public static function attach_order($intent,$order){
        if(!isset(self::$seen[$intent])||self::$seen[$intent]!=='claimed')return new WP_Error('missing_claim');
        if(isset(self::$bound[$intent])&&self::$bound[$intent]!==$order)return new WP_Error('duplicate');
        self::$bound[$intent]=$order;
        self::$seen[$intent]='bound';
        return ['wc_order_id'=>$order,'replayed'=>false];
    }
}
final class Milano_Prepayment_Order_Audit {
    public static bool $allow=true;
    public static int $calls=0;
    public static function inspect($order,$spec){
        self::$calls++;
        return self::$allow ? [
            'held_capacity_units'=>4,
            'canonical_order_id'=>$order->get_id()
        ] : new WP_Error('mdg_order_map_or_hold_missing');
    }
}
final class TLine {
    public array $meta;
    public function __construct(public int $prod,public int $var,public int $qty,string $component, int $event=24, int $session=151){
        $this->meta=[
            '_mdg_family22'=>'1',
            '_mdg_family22_component'=>$component,
            '_mdg_family22_group'=>'mdg-family22-24-151-fixture-xyz',
            '_mdg_family22_packages'=>'1',
            '_mdg_family22_event_id'=>$event,
            '_mdg_family22_session_id'=>$session
        ];
    }
    public function get_meta($k,$single=true){return $this->meta[$k]??'';}
    public function get_quantity(){return $this->qty;}
    public function get_product_id(){return $this->prod;}
    public function get_variation_id(){return $this->var;}
}
final class TOrder {
    public array $meta=[];
    public int $id=0;
    public string $status='pending';
    public array $lines=[];
    public function __construct(bool $milano=true){
        $this->lines=[
            new TLine($milano?6061:2143,$milano?6062:2144,2,'child',$milano?24:4),
            new TLine($milano?6061:2143,$milano?6063:2145,2,'adult',$milano?24:4)
        ];
    }
    public function get_items($type='line_item'){return $type==='line_item'?$this->lines:[];}
    public function get_id(){return $this->id;}
    public function get_status(){return $this->status;}
    public function get_meta($key,$single=true){return $this->meta[$key]??'';}
    public function update_meta_data($key,$v){$this->meta[$key]=$v;}
}
class MDG_DB {
    public static function table($n):string{return 'wp_mdg_'.$n;}
}
class TDb {
    public int $queries=0;
    public function prepare($fmt,...$a):string{return vsprintf($fmt,$a);}
    public function get_row($sql) {
        $this->queries++;
        if(preg_match('/t\.wc_variation_id=(\d+)/',$sql,$a)) {
            $v=(int)$a[1];
            return (object)['event_id'=>in_array($v,[6062,6063],true)?24:4];
        }
        return null;
    }
}
$wpdb=new TDb();
$existing=[];
function wc_get_order($id){global $existing;return $existing[$id]??null;}
require dirname(__DIR__,2).'/integrations/milano/checkout-contract/milano-woo-classic-checkout-bridge.php';
$passes=0;
function ok($condition,$name){global $passes;if(!$condition){fwrite(STDERR,"FAIL ".$name."\n");exit(1);}++$passes;echo "PASS ".$name."\n";}
function throws($fn):bool{try{$fn();return false;}catch(RuntimeException $e){return true;}}
$intent=str_repeat('a',64);
$context=[
    'spec'=>[
        'brand'=>'milano','intent'=>$intent,'event_id'=>24,'session_id'=>151,
        'product_id'=>6061,'child_variation_id'=>6062,'adult_variation_id'=>6063,
        'child_type_id'=>344,'adult_type_id'=>345,'amount_minor'=>110000,'units'=>4
    ],
    'quote_sha256'=>hash('sha256','yalova-19:30-family-1100'),
    'origin'=>'https://milanosirki.com','aud'=>'milano:central-checkout',
    'iat'=>time(),'exp'=>time()+240
];
$called=0;
$provider=function($order)use(&$context,&$called){$called++;return $context;};
ok(Milano_Woo_Classic_Checkout_Bridge::register(null) instanceof WP_Error,'no unverified provider registration');
ok(Milano_Woo_Classic_Checkout_Bridge::register($provider)===true,'server-only verified provider required');
ok(isset($hooks['woocommerce_checkout_create_order'])&&$hooks['woocommerce_checkout_create_order']['priority']===25,
    'first writer claim happens before Woo order save');
ok(isset($hooks['woocommerce_checkout_order_processed'])&&$hooks['woocommerce_checkout_order_processed']['priority']===25,
    'finalized audit happens after MDG priority-5 prepayment hold');
ok(isset($hooks['woocommerce_store_api_checkout_order_processed']),
    'unapproved Milano Store API branch registered as deny-only');
ok(isset($hooks['woocommerce_resume_order'])&&$hooks['woocommerce_resume_order']['priority']===1,
    'resuming checkout is intercepted BEFORE Woo removes existing order items');
ok(Milano_Woo_Classic_Checkout_Bridge::register($provider) instanceof WP_Error,'cannot register hooks twice');
$old=new TOrder(false);
Milano_Woo_Classic_Checkout_Bridge::claim_before_save($old);
Milano_Woo_Classic_Checkout_Bridge::audit_after_mdg_hold(123,[],$old);
Milano_Woo_Classic_Checkout_Bridge::deny_unapproved_store_api($old);
ok($called===0&&Milano_Canonical_Order_Claim::$attempts===0,'Madagaskar checkout is completely untouched');
$existing[700]=new TOrder(false);
Milano_Woo_Classic_Checkout_Bridge::deny_reused_milano_order(700);
ok(true,'resuming legacy Madagaskar order is allowed');
$existing[701]=new TOrder();
$existing[701]->id=701;
$existing[701]->meta['_milano_brand']='milano';
ok(throws(static function(){Milano_Woo_Classic_Checkout_Bridge::deny_reused_milano_order(701);}),
    'existing Milano order cannot be reset and have its ticket rows deleted');
$missingFamily=new TOrder();
foreach($missingFamily->lines as $line){
    $line->meta['_mdg_family22_event_id']='';
    $line->meta['_mdg_family22']='';
}
$attemptBefore=Milano_Canonical_Order_Claim::$attempts;
ok(throws(static function()use($missingFamily){Milano_Woo_Classic_Checkout_Bridge::claim_before_save($missingFamily);}),
    'actual Milano WC variation remains gated even after family meta is missing');
ok(Milano_Canonical_Order_Claim::$attempts===$attemptBefore,
    'invalid Milano variation cannot claim a new order key');
$pre=new TOrder();
Milano_Woo_Classic_Checkout_Bridge::claim_before_save($pre);
ok($pre->meta['_milano_brand']==='milano'
    &&$pre->meta['_milano_intent_key']===$intent
    &&$pre->meta['_mdg_family22_packages']===1,'first creator marks canonical Woo order before persistence');
ok(Milano_Canonical_Order_Claim::$attempts===1,'exactly one canonical claim attempted');
$pre->id=6009;
Milano_Woo_Classic_Checkout_Bridge::audit_after_mdg_hold(6009,[],$pre);
ok(Milano_Canonical_Order_Claim::$bound[$intent]===6009,'post-MDG capacity check binds exactly one persisted WC order');
ok(Milano_Prepayment_Order_Audit::$calls===1,'strict prepayment map + hold gate invoked');
ok(throws(static function(){Milano_Woo_Classic_Checkout_Bridge::claim_before_save(new TOrder());}),
    'identical double click cannot create second Woo order');
ok(count(Milano_Canonical_Order_Claim::$bound)===1,'no second bound Woo order');
ok(throws(static function()use($pre){Milano_Woo_Classic_Checkout_Bridge::claim_before_save($pre);}),
    'cannot re-claim an already saved Woo order');
ok(throws(static function(){Milano_Woo_Classic_Checkout_Bridge::deny_unapproved_store_api(new TOrder());}),
    'Store API cannot bypass Milano classic-only hold gate');
$bad=new TOrder();$bad->lines[1]->var=6059;
ok(throws(static function()use($bad){Milano_Woo_Classic_Checkout_Bridge::claim_before_save($bad);}),
    'mixed/wrong-session ticket variation refused before DB claim');
$bad=new TOrder();$bad->lines[1]->qty=1;
ok(throws(static function()use($bad){Milano_Woo_Classic_Checkout_Bridge::claim_before_save($bad);}),
    'one-adult family cannot claim four-seat package');
$save=$context;
$context['exp']=time()-1;
ok(throws(static function(){Milano_Woo_Classic_Checkout_Bridge::claim_before_save(new TOrder());}),
    'expired signed request refused before claim');
$context=$save;
$context['origin']='https://madagaskarsirki.com';
ok(throws(static function(){Milano_Woo_Classic_Checkout_Bridge::claim_before_save(new TOrder());}),
    'cross-brand browser origin refused before claim');
$context=$save;
$context['spec']['session_id']=150;
ok(throws(static function(){Milano_Woo_Classic_Checkout_Bridge::claim_before_save(new TOrder());}),
    '18:00 signed claim cannot buy 19:30 product');
$context=$save;
$context['spec']['intent']=str_repeat('c',64);
Milano_Prepayment_Order_Audit::$allow=false;
$nohold=new TOrder();
Milano_Woo_Classic_Checkout_Bridge::claim_before_save($nohold);
$nohold->id=6011;
ok(throws(static function()use($nohold){Milano_Woo_Classic_Checkout_Bridge::audit_after_mdg_hold(6011,[],$nohold);}),
    'missing MDG capacity hold/order map blocks payment');
ok(!isset(Milano_Canonical_Order_Claim::$bound[str_repeat('c',64)]),
    'failed audit never binds Woo order for PayTR');
ok(count(Milano_Canonical_Order_Claim::$bound)===1,
    'only first complete trusted order is bound');
echo "ALL $passes offline lifecycle tests passed, zero actual Woo/PayTR/MDG actions.\n";
