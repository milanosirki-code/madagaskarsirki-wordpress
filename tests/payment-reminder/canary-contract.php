<?php
define( 'ABSPATH', __DIR__ );
$GLOBALS['writes'] = 0;
$GLOBALS['sends'] = 0;

function sanitize_key( $v ) { return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)); }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function update_post_meta() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }
function update_option() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }
function wp_remote_request() { $GLOBALS['sends']++; throw new RuntimeException('send forbidden'); }
function wp_remote_post() { $GLOBALS['sends']++; throw new RuntimeException('send forbidden'); }

require dirname(__DIR__,2).'/prototypes/payment-reminder/payment-reminder-canary-contract.php';

$checks=0;
$check=function($name,$actual,$expected)use(&$checks){
    if($actual!==$expected) throw new RuntimeException($name.': '.json_encode([$actual,$expected]));
    $checks++;
};

$preview=[
    'order_id'=>4849,
    'event_ids'=>[12],
    'session_ids'=>[99],
    'program_ids'=>[10],
    'eligibility'=>'ELIGIBLE',
    'exclusion_reason'=>'',
    'payment_link_present'=>true,
    'replacement_paid'=>false,
    'already_reminded'=>false,
    'template_key'=>'madagaskar_odeme_hatirlatma_v1',
    'template_version'=>'DRAFT-1',
];
$provider=['provider_ready'=>false];

$r=mdg_payment_reminder_canary_preview($preview,$provider);
$check('provider blocks canary',$r['canary_block_reason'],'PROVIDER_NOT_READY');
$check('owner approval always required',$r['owner_approval_required'],true);
$check('send remains hard off',$r['send_enabled'],false);
$check('real sends zero',$r['real_send_count'],0);
$check('idempotency hash length',strlen($r['idempotency_key']),64);
$check('snapshot hash length',strlen($r['eligibility_snapshot_hash']),64);
$check('receipt storage proposal',$r['receipt_storage_proposal'],'woocommerce_order_meta_after_provider_acceptance');

$provider=['provider_ready'=>true];
$r2=mdg_payment_reminder_canary_preview($preview,$provider);
$check('ready only for owner review',$r2['canary_ready_for_owner_review'],true);
$check('even ready never enables send',$r2['send_enabled'],false);

$reordered=$preview;
$reordered['event_ids']=[12,12];
$key1=mdg_payment_reminder_idempotency_key(4849,[12],'madagaskar_odeme_hatirlatma_v1','DRAFT-1');
$key2=mdg_payment_reminder_idempotency_key(4849,[12,12],'madagaskar_odeme_hatirlatma_v1','DRAFT-1');
$check('stable duplicate ids',$key1,$key2);

$excluded=$preview;
$excluded['eligibility']='EXCLUDED';
$excluded['exclusion_reason']='EVENT_CANCELLED';
$r3=mdg_payment_reminder_canary_preview($excluded,['provider_ready'=>true]);
$check('eligibility blocks canary',$r3['canary_block_reason'],'NOT_ELIGIBLE');

$noLink=$preview;
$noLink['payment_link_present']=false;
$r4=mdg_payment_reminder_canary_preview($noLink,['provider_ready'=>true]);
$check('missing link blocks canary',$r4['canary_block_reason'],'PAYMENT_LINK_NOT_READY');

$sent=$preview;
$sent['already_reminded']=true;
$r5=mdg_payment_reminder_canary_preview($sent,['provider_ready'=>true]);
$check('already sent blocks canary',$r5['canary_block_reason'],'ALREADY_REMinded');

$encoded=json_encode($r2);
$check('no URL in canary output',strpos($encoded,'https://'),false);
$check('no phone field in canary output',strpos($encoded,'phone'),false);
$check('no writes',$GLOBALS['writes'],0);
$check('no sends',$GLOBALS['sends'],0);

echo $checks." canary/idempotency checks passed; writes0; sends0\n";
