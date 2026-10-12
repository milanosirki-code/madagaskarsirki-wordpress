<?php
/**
 * Pure per-order-line reconciliation: accepts synthetic, privacy-safe records.
 * No WordPress calls and no ticket issuance. Never accepts raw QR codes.
 */
function milano_ticket_line_reconcile( $expected, $issued ) {
    $counts = array();
    $ids = array();
    $hashes = array();
    foreach ( $expected as $line => $count ) {
        if ( ! is_string( $line ) || ! preg_match( '/^[0-9]+$/', $line )
            || ! is_int( $count ) || $count < 1 ) {
            return array( 'ok'=>false, 'reason'=>'invalid_expected_line' );
        }
        $counts[ $line ] = 0;
    }
    if ( ! $counts ) { return array( 'ok'=>false, 'reason'=>'empty_expectation' ); }
    foreach ( $issued as $ticket ) {
        if ( ! is_array( $ticket ) || ! isset( $ticket['line_id'], $ticket['id'], $ticket['code_hash'] )
            || ! ctype_digit( (string) $ticket['line_id'] )
            || ! is_int( $ticket['id'] ) || $ticket['id'] < 1
            || ! is_string( $ticket['code_hash'] )
            || ! preg_match( '/^[a-f0-9]{64}$/D', $ticket['code_hash'] ) ) {
            return array( 'ok'=>false, 'reason'=>'invalid_ticket_record' );
        }
        $line = (string) $ticket['line_id'];
        if ( ! array_key_exists( $line, $counts ) ) {
            return array( 'ok'=>false, 'reason'=>'unexpected_order_line' );
        }
        if ( isset( $ids[ $ticket['id'] ] ) || isset( $hashes[ $ticket['code_hash'] ] ) ) {
            return array( 'ok'=>false, 'reason'=>'duplicate_ticket_identity' );
        }
        $ids[$ticket['id']] = true;
        $hashes[$ticket['code_hash']] = true;
        $counts[$line]++;
    }
    foreach ( $counts as $line => $actual ) {
        if ( $actual !== $expected[$line] ) {
            return array( 'ok'=>false, 'reason'=>'line_count_mismatch', 'line_id'=>$line,
                'expected'=>$expected[$line], 'found'=>$actual );
        }
    }
    return array( 'ok'=>true, 'line_count'=>count($counts), 'ticket_count'=>count($issued) );
}
