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
 'tampered_amount'=>array_merge($normal,['total_amount'=>'110001']),
 'bad_hash'=>array_merge($normal,['hash'=>'fake']),
 'wrong_oid'=>signed(array_merge($base,['merchant_oid'=>'MADAGASKAR000001']),$key,$salt),
 'wrong_status'=>signed(array_merge($base,['status'=>'other']),$key,$salt),
 'wrong_currency'=>signed(array_merge($base,['currency'=>'USD']),$key,$salt),
 'wrong_family_amount'=>signed(array_merge($base,['payment_amount'=>'109999']),$key,$salt),
 'zero_amount'=>signed(array_merge($base,['payment_amount'=>'0']),$key,$salt),
 'missing_payment_amount'=>signed(array_diff_key($base,['payment_amount'=>null]),$key,$salt),
 'wrong_payment_type'=>signed(array_merge($base,['payment_type'=>'unknown']),$key,$salt),
 'charge_less_than_expected'=>signed(array_merge($base,['total_amount'=>'108000']),$key,$salt),
];
foreach($reject as $name=>$post){
  chk(is_wp_error(Milano_PayTR_Callback_Contract::verify($post,$oid,110000,$key,$salt)),
      $name.' rejected');
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
