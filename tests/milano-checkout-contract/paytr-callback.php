<?php
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
  private string $code;
  public function __construct($code,$message=''){$this->code=(string)$code;}
  public function get_error_code():string{return $this->code;}
}
function is_wp_error($x):bool{return $x instanceof WP_Error;}
require dirname(__DIR__,2).'/integrations/milano/checkout-contract/milano-paytr-callback-contract.php';
$checks=0;
function chk(bool $pass,string $name):void{
    global $checks;
    if(!$pass){fwrite(STDERR,"FAIL $name\n");exit(1);}
    $checks++;echo "PASS $name\n";
}
$oid='MILANO240150000001';$key='demo-test-key-not-a-real-merchant-key';$salt='demo-test-salt-not-a-real-merchant-salt';
$base=[
 'merchant_oid'=>$oid,'status'=>'success','total_amount'=>'110000',
 'payment_amount'=>'110000','currency'=>'TL','payment_type'=>'card',
];
function signed(array $data,string $key,string $salt):array{
    $data['hash']=base64_encode(hash_hmac('sha256',
        $data['merchant_oid'].$salt.$data['status'].$data['total_amount'],$key,true));
    return $data;
}
$normal=signed($base,$key,$salt);
$verified=Milano_PayTR_Callback_Contract::verify($normal,$oid,110000,$key,$salt);
chk(is_array($verified)&&$verified['status']==='success','valid signed PayTR callback');
$installment=$base;$installment['total_amount']='113500';
chk(is_array(Milano_PayTR_Callback_Contract::verify(signed($installment,$key,$salt),$oid,110000,$key,$salt)),'signed installments allowed above canonical amount');
$reject=[
 'tampered_signed_amount'=>array_merge($normal,['total_amount'=>'110001']),
 'bad_hash'=>array_merge($normal,['hash'=>'fake']),
 'wrong_oid'=>signed(array_merge($base,['merchant_oid'=>'MADAGASKAR000001']),$key,$salt),
 'wrong_status'=>signed(array_merge($base,['status'=>'other']),$key,$salt),
 'signed_charge_less_than_expected'=>signed(array_merge($base,['total_amount'=>'108000']),$key,$salt),
];
foreach($reject as $name=>$post) {
  chk(is_wp_error(Milano_PayTR_Callback_Contract::verify($post,$oid,110000,$key,$salt)),
      $name.' rejected');
}
// These optional fields are NOT inputs to the documented PayTR step-2 HMAC,
// so forged values have NO authority and must never become payment truth.
$unsignedChanges=[
 'forged_currency'=>array_merge($normal,['currency'=>'USD']),
 'forged_original_amount'=>array_merge($normal,['payment_amount'=>'1']),
 'forged_method'=>array_merge($normal,['payment_type'=>'other']),
 'omitted_supplementals'=>array_diff_key($normal,[
   'currency'=>null,'payment_amount'=>null,'payment_type'=>null
 ])
];
foreach($unsignedChanges as $name=>$post) {
  $result=Milano_PayTR_Callback_Contract::verify($post,$oid,110000,$key,$salt);
  chk(is_array($result)
       && $result['charged_minor']===110000
       && $result['expected_minor']===110000
       && $result['unsigned_supplemental_fields_ignored']===true
       && !isset($result['currency'])&&!isset($result['payment_amount'])
       && !isset($result['payment_type']),
      $name.' ignored and cannot change authenticated canonical amount');
}
$failed=signed(['merchant_oid'=>$oid,'status'=>'failed','total_amount'=>'0'],$key,$salt);
chk(is_array(Milano_PayTR_Callback_Contract::verify($failed,$oid,110000,$key,$salt)),
    'failed signed notification without success-only fields accepted');
chk(Milano_PayTR_Callback_Contract::transition('pending','success')==='apply','first success must be applied once');
chk(Milano_PayTR_Callback_Contract::transition('success','success')==='ack_only','repeat success cannot issue again');
chk(Milano_PayTR_Callback_Contract::transition('failed','failed')==='ack_only','repeat failure cannot refund again');
chk(Milano_PayTR_Callback_Contract::transition('success','failed')==='conflict','conflicting failure cannot reverse success');
chk(Milano_PayTR_Callback_Contract::transition('failed','success')==='conflict','conflicting success requires human investigation');
chk(Milano_PayTR_Callback_Contract::transition('pending','garbage')==='invalid','unknown outcome rejected');
chk(Milano_PayTR_Callback_Contract::transition('missing','success')==='invalid','unknown order state rejected');
echo "All $checks offline PayTR contract tests passed; no real callback processed.\n";
