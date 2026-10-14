<?php
/** Offline orchestrator state machine regression: NO Woo/site/PayTR side effects. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
    public string $code;
    public function __construct($code,$message=''){$this->code=(string)$code;}
}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
class FakeOrder {
    public function get_id():int{return 6001;}
}
final class Milano_Canonical_Order_Claim {
    public static string $state='bound';
    public static int $order_id=6001;
    public static int $enter_count=0;
    public static int $finish_count=0;
    public static bool $fail_finish=false;
    public static string $expected_quote='';
    public static function claim($intent,$quote,$event,$session,$amount,$units) {
        if($quote!==self::$expected_quote
           || $event!==24||$session!==151||$amount!==110000||$units!==4
           ||$intent!==str_repeat('a',64))return new WP_Error('bad_claim');
        return ['state'=>self::$state,'wc_order_id'=>self::$order_id,'created'=>false];
    }
    public static function reservation_transition($intent,$order,$from,$to) {
        if(self::$state!==$from||$order!==self::$order_id)return new WP_Error('state_conflict');
        if($from==='bound' && $to==='reserving') {
            self::$enter_count++;
        } elseif($from==='reserving' && $to==='ready') {
            if(self::$fail_finish)return new WP_Error('db_write_failure');
            self::$finish_count++;
        } else return new WP_Error('illegal_state');
        self::$state=$to;
        return ['won_transition'=>true,'state'=>$to];
    }
}
final class Milano_Prepayment_Order_Audit {
    public static bool $structure_valid=true;
    public static bool $hold_valid=false;
    public static int $pre_calls=0;
    public static int $post_calls=0;
    public static function inspect($order,$spec,$phase='payment') {
        if($phase==='pre_reservation') {
            self::$pre_calls++;
            return self::$structure_valid
                ? ['read_only'=>true,'may_start_payment_after_separate_gateway_auth'=>false]
                : new WP_Error('invalid_family');
        }
        self::$post_calls++;
        return self::$hold_valid
            ? ['read_only'=>true,'may_start_payment_after_separate_gateway_auth'=>true]
            : new WP_Error('wrong_hold');
    }
}
final class MDG_Live_Sales {
    public static int $reservation_calls=0;
    public static bool $should_throw=false;
    public static bool $write_hold=true;
    public static function classic_order_processed($order_id,$posted_data=[],$order=null) {
        self::$reservation_calls++;
        if(self::$should_throw)throw new RuntimeException('MDG hold was ambiguous');
        if(self::$write_hold)Milano_Prepayment_Order_Audit::$hold_valid=true;
    }
}
require dirname(__DIR__,2).'/integrations/milano/checkout-contract/milano-central-checkout-reservation.php';
$spec=[
  'brand'=>'milano','intent'=>str_repeat('a',64),
  'event_id'=>24,'session_id'=>151,'product_id'=>6061,
  'child_variation_id'=>6062,'adult_variation_id'=>6063,
  'child_type_id'=>344,'adult_type_id'=>345,
  'amount_minor'=>110000,'units'=>4
];
$quote=hash('sha256','immutable-trusted-yalova-19-30-2plus2');
Milano_Canonical_Order_Claim::$expected_quote=$quote;
$passes=0;
function check($ok,$name):void {
    global $passes;
    if(!$ok){fwrite(STDERR,"FAIL $name\n");exit(1);}
    ++$passes;echo "PASS $name\n";
}
function reset_test():void {
    Milano_Canonical_Order_Claim::$state='bound';
    Milano_Canonical_Order_Claim::$order_id=6001;
    Milano_Canonical_Order_Claim::$enter_count=0;
    Milano_Canonical_Order_Claim::$finish_count=0;
    Milano_Canonical_Order_Claim::$fail_finish=false;
    Milano_Prepayment_Order_Audit::$structure_valid=true;
    Milano_Prepayment_Order_Audit::$hold_valid=false;
    Milano_Prepayment_Order_Audit::$pre_calls=0;
    Milano_Prepayment_Order_Audit::$post_calls=0;
    MDG_Live_Sales::$reservation_calls=0;
    MDG_Live_Sales::$should_throw=false;
    MDG_Live_Sales::$write_hold=true;
}
function is_fail($v):bool{return is_wp_error($v);}
$o=new FakeOrder();
reset_test();
$r=Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote);
check(is_array($r)&&$r['state']==='ready'&&$r['held_units']===4
    &&$r['gateway_started']===false&&$r['admission_qr_created']===false,
    'one approved reservation yields four held seats but no payment/QR');
check(MDG_Live_Sales::$reservation_calls===1
    &&Milano_Canonical_Order_Claim::$enter_count===1
    &&Milano_Canonical_Order_Claim::$finish_count===1,
    'MDG core called exactly once and ledger went bound/reserving/ready');
$r=Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote);
check(is_array($r)&&$r['replayed']===true
    &&MDG_Live_Sales::$reservation_calls===1,'replay reuses first order and never repeats hold');
reset_test();Milano_Prepayment_Order_Audit::$structure_valid=false;
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
   &&MDG_Live_Sales::$reservation_calls===0
   &&Milano_Canonical_Order_Claim::$state==='bound','invalid Woo family stopped BEFORE stock hold');
reset_test();Milano_Canonical_Order_Claim::$order_id=7001;
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
    &&MDG_Live_Sales::$reservation_calls===0,'another central order ID never receives hold');
reset_test();MDG_Live_Sales::$should_throw=true;
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
   &&Milano_Canonical_Order_Claim::$state==='reserving','MDG ambiguous error remains locked');
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
   &&MDG_Live_Sales::$reservation_calls===1,'retry after ambiguous MDG error forbidden');
reset_test();MDG_Live_Sales::$write_hold=false;
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
    &&Milano_Canonical_Order_Claim::$state==='reserving'
    &&Milano_Canonical_Order_Claim::$finish_count===0,'missing MDG hold never advances to ready');
reset_test();Milano_Canonical_Order_Claim::$fail_finish=true;
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
   &&Milano_Canonical_Order_Claim::$state==='reserving','DB ready transition failure blocks payment');
reset_test();Milano_Canonical_Order_Claim::$state='ready';
Milano_Prepayment_Order_Audit::$hold_valid=false;
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
   &&MDG_Live_Sales::$reservation_calls===0,'expired reserved hold cannot be reissued or used for payment');
reset_test();Milano_Canonical_Order_Claim::$state='claimed';
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,$quote))
   &&MDG_Live_Sales::$reservation_calls===0,'unbound claimed intent cannot reserve');
reset_test();
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,str_repeat('0',64)))
   &&MDG_Live_Sales::$reservation_calls===0,'tampered server quote blocked');
reset_test();
check(is_fail(Milano_Central_Checkout_Reservation::prepare_unpaid_order($o,$spec,'invalid'))
   &&MDG_Live_Sales::$reservation_calls===0,'non-cryptographic quote blocked');
echo "ALL $passes offline canonical reservation state-machine checks passed.\n";
