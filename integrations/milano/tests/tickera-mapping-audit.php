<?php
/**
 * Pure ticket-to-order-line mapping audit. No WordPress reads or ticket changes.
 * Expected: line_id => ['event_id'=>positive int,'ticket_type_id'=>positive int].
 * Actual tickets: line_id, event_id, ticket_type_id.
 * Counts and unique ticket-code checks remain in tickera-line-reconciliation.php.
 */
function milano_ticket_mapping_audit( $expected, $tickets ) {
    if ( ! is_array($expected) || ! $expected || ! is_array($tickets) ) {
        return array('ok'=>false,'reason'=>'invalid_input');
    }
    foreach ($expected as $line => $map) {
        if (!ctype_digit((string)$line) || (int)$line < 1 || !is_array($map)
            || !isset($map['event_id'],$map['ticket_type_id'])
            || !is_int($map['event_id']) || $map['event_id'] < 1
            || !is_int($map['ticket_type_id']) || $map['ticket_type_id'] < 1) {
            return array('ok'=>false,'reason'=>'invalid_mapping');
        }
    }
    foreach ($tickets as $ticket) {
        if (!is_array($ticket) || !isset($ticket['line_id'],$ticket['event_id'],$ticket['ticket_type_id'])
            || !ctype_digit((string)$ticket['line_id']) || (int)$ticket['line_id'] < 1
            || !is_int($ticket['event_id']) || !is_int($ticket['ticket_type_id'])) {
            return array('ok'=>false,'reason'=>'invalid_ticket');
        }
        $line=(string)$ticket['line_id'];
        if (!isset($expected[$line])) { return array('ok'=>false,'reason'=>'unexpected_line'); }
        if ($ticket['event_id'] !== $expected[$line]['event_id']
            || $ticket['ticket_type_id'] !== $expected[$line]['ticket_type_id']) {
            return array('ok'=>false,'reason'=>'wrong_event_or_ticket_type');
        }
    }
    return array('ok'=>true,'checked'=>count($tickets));
}
