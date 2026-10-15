<?php
require __DIR__.'/tickera-full-audit.php';
function ensure_full($v,$label){if(!$v){fwrite(STDERR,"FAIL ".$label."\n");exit(1);}}
$lines=array(101=>2,102=>1);
$maps=array(101=>array('event_id'=>20,'ticket_type_id'=>5),102=>array('event_id'=>20,'ticket_type_id'=>6));
$issued=array(
 array('line_id'=>101,'id'=>1,'code_hash'=>str_repeat('a',64),'event_id'=>20,'ticket_type_id'=>5),
 array('line_id'=>101,'id'=>2,'code_hash'=>str_repeat('b',64),'event_id'=>20,'ticket_type_id'=>5),
 array('line_id'=>102,'id'=>3,'code_hash'=>str_repeat('c',64),'event_id'=>20,'ticket_type_id'=>6));
ensure_full(milano_full_ticket_audit($lines,$maps,$issued)['ok']===true,'valid combined audit');
$wrong=$issued;$wrong[2]['ticket_type_id']=5;
ensure_full(milano_full_ticket_audit($lines,$maps,$wrong)['reason']==='wrong_event_or_ticket_type','wrong type despite correct count');
$wrong=$issued;$wrong[2]['line_id']=101;
ensure_full(milano_full_ticket_audit($lines,$maps,$wrong)['reason']==='line_count_mismatch','wrong line');
$wrong=$issued;$wrong[2]['code_hash']=str_repeat('a',64);
ensure_full(milano_full_ticket_audit($lines,$maps,$wrong)['reason']==='duplicate_ticket_identity','duplicate hash');
$bad=$maps;unset($bad[102]);
ensure_full(milano_full_ticket_audit($lines,$bad,$issued)['reason']==='mapping_line_set_mismatch','missing mapping');
echo "PASS combined ticket audit\n";
