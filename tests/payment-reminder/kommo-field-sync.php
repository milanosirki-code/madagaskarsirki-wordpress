<?php
define('ABSPATH',__DIR__);
function add_action(...$args) {}
require __DIR__.'/../../prototypes/payment-reminder/kommo-reminder-field-sync.php';
foreach (array('2026-10-11 09:00:00'=>1791709200,'2026-02-31 09:00:00'=>0,'2026-10-11 25:00:00'=>0,''=>0) as $raw=>$expected) {
    if (mdg_kr_utc_timestamp($raw)!==$expected) { throw new RuntimeException('Datetime test failed: '.$raw); }
}
$source=file_get_contents(__DIR__.'/../../prototypes/payment-reminder/kommo-reminder-field-sync.php');
foreach (array("'PREVIEW_ELIGIBLE'","ms_oh_dry_run_report",'needs_payment()',"$"."start>time()",'custom_fields_values') as $guard) {
    if (strpos($source,$guard)===false) { throw new RuntimeException('Missing guard'); }
}
if (strpos($source,'send_message')!==false || strpos($source,'/salesbot/run')!==false) { throw new RuntimeException('Sync must not send messages'); }
echo "Kommo reminder datetime and delivery gate tests passed\n";

$now=1791792000;
foreach (array(
    array($now-36*3600,true,'processing',true,$now-36*3600),
    array($now+86400,true,'completed',true,$now+86400),
    array($now-8*86400,true,'processing',true,null),
    array($now+86400,false,'pending',true,null),
    array($now+86400,true,'refunded',true,null),
    array($now+86400,true,'processing',false,null),
    array(0,true,'processing',true,null)
) as $case) {
    if (mdg_kr_survey_timestamp($case[0],$case[1],$case[2],$case[3],$now)!==$case[4]) { throw new RuntimeException('Survey eligibility failed'); }
}
echo "Survey past-session retention and exclusions passed\n";
