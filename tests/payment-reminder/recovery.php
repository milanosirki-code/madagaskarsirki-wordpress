<?php
require __DIR__.'/regression.php';
require dirname(__DIR__,2).'/prototypes/payment-reminder/kommo-reminder-field-sync.php';
$reset(); $markers['ms_oh_yazildi_1001']='gonderilirdi';
$check('Recovery ignores dry-run history',ms_oh_dry_run_report($order,null,true)['eligibility'],'ELIGIBLE');
$order->meta['_ms_oh_reminder_sent_at']='accepted';
$check('Recovery retains durable receipt',ms_oh_dry_run_report($order,null,true)['exclusion_reason'],'ALREADY_REMINDED');
$order->meta=array();$reset();
$paid=new FixtureOrder(1002);$paid->created=$order->created+60;$paid->status='processing';$paid_orders=array($paid);
$markers['ms_oh_yazildi_1001']='gonderilirdi';
$check('Recovery still detects later payment',ms_oh_dry_run_report($order,null,true)['exclusion_reason'],'REPLACEMENT_PAID');
$reset();$wpdb->sessions[201][0]->start_at=gmdate('Y-m-d H:i:s',time()-60);
$check('Recovery still excludes started session',ms_oh_dry_run_report($order,null,true)['exclusion_reason'],'SESSION_STARTED');

$reset();$order->modified=time();
$check('Metadata refresh does not restart checkout wait',ms_oh_dry_run_report($order,null,true)['eligibility'],'ELIGIBLE');
$order->modified=time()+120;
$check('Future metadata timestamp does not defer old checkout',ms_oh_dry_run_report($order,null,true)['eligibility'],'ELIGIBLE');
$reset();$order->created=time()-60;
$check('Fresh checkout still waits',ms_oh_dry_run_report($order,null,true)['exclusion_reason'],'WAITING');
$reset();$order->created=null;
$check('Missing checkout timestamp remains excluded',ms_oh_dry_run_report($order,null,true)['exclusion_reason'],'DATE_MISSING');

$url='https://example.test/checkout/order-pay/1/?key=fixture';
$gate=function($eligible=true,$previous='',$old=null,$lock=false,$paid=false,$wc='failed',$crm=143)use($url){return mdg_kr_payment_gate($eligible,$url,$previous,$old,$lock,$paid,$wc,$crm);};
$check('Failed lost can preview',$gate()['state'],'PREVIEW_ELIGIBLE');
$check('Pending matched can preview',$gate(true,'',null,false,false,'pending',110416823)['state'],'PREVIEW_ELIGIBLE');
$check('No automatic authorization',$gate()['state']==='ALLOW_SEND',false);
$check('Matching explicit approval preserved',$gate(true,'ALLOW_SEND',$url)['state'],'ALLOW_SEND');
$check('Changed payment URL invalidates approval',$gate(true,'ALLOW_SEND','https://example.test/old')['state'],'PREVIEW_ELIGIBLE');
$check('Payment tag invalidates approval',$gate(true,'ALLOW_SEND',$url,false,true)['state'],'EXCLUDED');
$check('Ineligible invalidates approval',$gate(false,'ALLOW_SEND',$url)['state'],'EXCLUDED');
$check('Cancelled lost excluded',$gate(true,'ALLOW_SEND',$url,false,false,'cancelled',143)['state'],'EXCLUDED');
$check('Paid processing excluded',$gate(true,'ALLOW_SEND',$url,false,false,'processing',110416827)['state'],'EXCLUDED');
$check('Mismatched order and CRM stage excluded',$gate(true,'ALLOW_SEND',$url,false,false,'failed',110416823)['state'],'EXCLUDED');
$check('Unknown CRM stage excluded',$gate(true,'ALLOW_SEND',$url,false,false,'failed',0)['state'],'EXCLUDED');
foreach(array('SEND_STARTED','SENT','SEND_ERROR','LOCKED')as$state){
    $check('Terminal state '.$state.' retained',$gate(true,$state,$url)['state'],$state);
    $check('Terminal state '.$state.' keeps dispatched payload',$gate(true,$state,$url)['link'],$url);
}
$check('Existing start tag prevents reopening',$gate(true,'ALLOW_SEND',$url,true)['state'],'LOCKED');
$check('No domain writes',$domain_writes,0);$check('No message sends',$sends,0);
echo $checks." total recovery and eligibility checks passed; sends0\n";
