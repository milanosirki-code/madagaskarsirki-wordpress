<?php
require __DIR__.'/tickera-mapping-audit.php';
function assert_audit($v,$name){if(!$v){fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}}
$expected=array(101=>array('event_id'=>55,'ticket_type_id'=>9));
$tickets=array(array('line_id'=>101,'event_id'=>55,'ticket_type_id'=>9));
assert_audit(milano_ticket_mapping_audit($expected,$tickets)['ok']===true,'correct');
$wrong=$tickets;$wrong[0]['event_id']=56;
assert_audit(milano_ticket_mapping_audit($expected,$wrong)['reason']==='wrong_event_or_ticket_type','wrong event');
$wrong=$tickets;$wrong[0]['ticket_type_id']=10;
assert_audit(milano_ticket_mapping_audit($expected,$wrong)['reason']==='wrong_event_or_ticket_type','wrong type');
$wrong=$tickets;$wrong[0]['line_id']=102;
assert_audit(milano_ticket_mapping_audit($expected,$wrong)['reason']==='unexpected_line','wrong line');
assert_audit(milano_ticket_mapping_audit(array(0=>array('event_id'=>55,'ticket_type_id'=>9)),$tickets)['reason']==='invalid_mapping','invalid mapping');
echo "PASS ticket mapping audit\n";
