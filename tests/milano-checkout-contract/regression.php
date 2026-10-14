<?php
declare(strict_types=1);

/**
 * Offline CLI contract checks. Never touches WordPress, PayTR or customer records.
 * This tests only signature/claims; no checkout, replay store, or payment validation.
 */
define('ABSPATH', __DIR__ . '/');
function add_action($name,$callback) { return null; }
function wp_salt($purpose) { return hash('sha256', 'offline-fixture-not-a-site-secret:' . $purpose); }
function wp_json_encode($data,$options=0) { return json_encode($data,$options | JSON_THROW_ON_ERROR); }
class WP_Error {
    private string $code;
    public function __construct($code, $message='') { $this->code=(string)$code; }
    public function get_error_code(): string { return $this->code; }
}
function is_wp_error($obj): bool {return $obj instanceof WP_Error;}
require dirname(__DIR__,2) . '/integrations/milano/checkout-contract/milano-checkout-contract-stage.php';

$ref=new ReflectionClass('Milano_Stage_Intent_Contract');
$sign=$ref->getMethod('sign');
$clean=$ref->getMethod('clean');
$fixture=$ref->getMethod('fixture');
$checks=0;
function check(bool $condition, string $name): void {
    global $checks;
    if(!$condition) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    $checks++;
    echo "PASS: $name\n";
}
$now=1_800_000_000;
$base=$fixture->invoke(null,150,$now);
$good=$clean->invoke(null,$sign->invoke(null,$base),$now);
check(is_array($good) && $good['event_id']===24 && $good['session_id']===150, 'canonical Milano fixture');
$token=$sign->invoke(null,$base);
[$body,$sig]=explode('.',$token);
$modified=$base;$modified['amount_minor']=1;
$bodyModified=rtrim(strtr(base64_encode(json_encode($modified,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');
check(is_wp_error($clean->invoke(null,$bodyModified.'.'.$sig,$now)), 'HMAC catches changed amount');
$mutations=[
 'expired'=>['iat'=>$now-400,'exp'=>$now-200],
 'future'=>['iat'=>$now+40,'exp'=>$now+180],
 'foreign brand'=>['brand'=>'madagaskar'],
 'foreign origin'=>['origin'=>'https://madagaskarsirki.com'],
 'wrong session'=>['session_id'=>60],
 'altered family price'=>['amount_minor'=>109900],
 'extra redirect'=>['redirect'=>'https://example.invalid'],
 'overlong lifetime'=>['exp'=>$now+360],
 'wrong audience'=>['aud'=>'other'],
 'wrong event'=>['event_id'=>7],
 'wrong capacity units'=>['units'=>3],
 'wrong quantity'=>['families'=>2],
 'wrong origin with case change'=>['origin'=>'https://MilanoSirki.com']
];
foreach($mutations as $label=>$change) {
    $test=array_merge($base,$change);
    $result=$clean->invoke(null,$sign->invoke(null,$test),$now);
    check(is_wp_error($result), $label.' rejected');
}
check(is_wp_error($clean->invoke(null,$token.'extra',$now)), 'malformed token rejected');
check(is_wp_error($clean->invoke(null,'invalid',$now)), 'unsigned token rejected');
check(!is_wp_error($clean->invoke(null,$sign->invoke(null,$fixture->invoke(null,151,$now)),$now)), 'second Yalova session accepted');

echo "All $checks contract assertions passed. This does NOT certify payments, replay safety, QR issuance or a live checkout.\n";
