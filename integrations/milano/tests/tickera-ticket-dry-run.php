<?php
/**
 * Pure dry-run Tickera ticket count verifier. Does not create, revoke, or expose
 * admission codes. Inputs must come from an authorized staging diagnostic.
 */
function milano_ticket_dry_run( $expected_quantities, $tickets ) {
    $expected = 0;
    foreach ( $expected_quantities as $qty ) {
        if ( ! is_int( $qty ) || $qty < 1 ) {
            return array( 'ok' => false, 'reason' => 'invalid_expected_quantity' );
        }
        $expected += $qty;
    }
    $seen_ids = array();
    $seen_hashes = array();
    foreach ( $tickets as $ticket ) {
        if ( ! isset( $ticket['id'], $ticket['code_hash'] ) || ! is_int( $ticket['id'] )
            || $ticket['id'] < 1 || ! preg_match( '/^[a-f0-9]{64}$/', (string) $ticket['code_hash'] ) ) {
            return array( 'ok' => false, 'reason' => 'invalid_ticket_record' );
        }
        if ( isset( $seen_ids[ $ticket['id'] ] ) || isset( $seen_hashes[ $ticket['code_hash'] ] ) ) {
            return array( 'ok' => false, 'reason' => 'duplicate_ticket_identity' );
        }
        $seen_ids[ $ticket['id'] ] = true;
        $seen_hashes[ $ticket['code_hash'] ] = true;
    }
    if ( count( $tickets ) !== $expected ) {
        return array( 'ok' => false, 'reason' => 'ticket_count_mismatch', 'expected' => $expected, 'found' => count( $tickets ) );
    }
    return array( 'ok' => true, 'expected' => $expected, 'found' => count( $tickets ) );
}
