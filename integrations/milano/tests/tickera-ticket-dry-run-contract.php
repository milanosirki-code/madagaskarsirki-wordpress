<?php
require __DIR__ . '/tickera-ticket-dry-run.php';
function check_ticket( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit(1); }
}
$a = array( array('id'=>1,'code_hash'=>str_repeat('a',64)), array('id'=>2,'code_hash'=>str_repeat('b',64)) );
check_ticket( milano_ticket_dry_run(array(2),$a)['ok'] === true,'exact quantity');
check_ticket( milano_ticket_dry_run(array(1),$a)['reason'] === 'ticket_count_mismatch','extra issued ticket');
check_ticket( milano_ticket_dry_run(array(3),$a)['reason'] === 'ticket_count_mismatch','missing ticket');
check_ticket( milano_ticket_dry_run(array(2),array($a[0],$a[0]))['reason'] === 'duplicate_ticket_identity','duplicate instance');
check_ticket( milano_ticket_dry_run(array(2),array($a[0],array('id'=>3,'code_hash'=>str_repeat('a',64))))['reason'] === 'duplicate_ticket_identity','duplicate code hash');
check_ticket( milano_ticket_dry_run(array(1),array(array('id'=>1,'code_hash'=>'raw code')))['reason'] === 'invalid_ticket_record','plaintext code rejected');
echo "PASS ticket dry-run identity contract\n";
