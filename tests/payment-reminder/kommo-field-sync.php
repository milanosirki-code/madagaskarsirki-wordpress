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
