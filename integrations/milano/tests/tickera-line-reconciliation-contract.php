<?php
require __DIR__ . '/tickera-line-reconciliation.php';
function verify_line( $ok, $case ) { if (!$ok) { fwrite(STDERR, "FAIL: ".$case."\n"); exit(1); } }
$expected = array('101'=>2,'102'=>1);
$issued = array(
    array('line_id'=>'101','id'=>51,'code_hash'=>str_repeat('a',64)),
    array('line_id'=>'101','id'=>52,'code_hash'=>str_repeat('b',64)),
    array('line_id'=>'102','id'=>53,'code_hash'=>str_repeat('c',64)),
);
verify_line( milano_ticket_line_reconcile($expected,$issued)['ok'], 'valid two line order' );
$dup = $issued; $dup[2]['code_hash'] = str_repeat('a',64);
verify_line( milano_ticket_line_reconcile($expected,$dup)['reason']==='duplicate_ticket_identity','duplicate hash');
$extra = $issued; $extra[2]['line_id']='103';
verify_line( milano_ticket_line_reconcile($expected,$extra)['reason']==='unexpected_order_line','unexpected line');
$wrong = $issued; $wrong[1]['line_id']='102';
verify_line( milano_ticket_line_reconcile($expected,$wrong)['reason']==='line_count_mismatch','same total wrong allocation');
$missing=$issued; array_pop($missing);
verify_line( milano_ticket_line_reconcile($expected,$missing)['reason']==='line_count_mismatch','missing ticket');
$plaintext=$issued; $plaintext[0]['code_hash']='raw';
verify_line( milano_ticket_line_reconcile($expected,$plaintext)['reason']==='invalid_ticket_record','no raw code');
verify_line( milano_ticket_line_reconcile(array('0'=>1),array())['reason']==='invalid_expected_line','zero expected line denied');
$bad_line = $issued; $bad_line[0]['line_id']='0';
verify_line( milano_ticket_line_reconcile($expected,$bad_line)['reason']==='invalid_ticket_record','zero issued line denied');
echo "PASS per-line ticket reconciliation\n";
