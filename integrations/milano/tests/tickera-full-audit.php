<?php
/** Combined synthetic audit: count, unique identity, and event/type ownership. */
require_once __DIR__ . '/tickera-line-reconciliation.php';
require_once __DIR__ . '/tickera-mapping-audit.php';
function milano_full_ticket_audit( $expected_lines, $expected_mapping, $issued ) {
    $count = milano_ticket_line_reconcile( $expected_lines, $issued );
    if ( ! $count['ok'] ) { return array('ok'=>false,'stage'=>'quantity_identity','reason'=>$count['reason']); }
    if ( array_diff_key( $expected_lines, $expected_mapping ) || array_diff_key( $expected_mapping, $expected_lines ) ) {
        return array('ok'=>false,'stage'=>'mapping','reason'=>'mapping_line_set_mismatch');
    }
    $map = milano_ticket_mapping_audit( $expected_mapping, $issued );
    if ( ! $map['ok'] ) { return array('ok'=>false,'stage'=>'mapping','reason'=>$map['reason']); }
    return array('ok'=>true,'lines'=>count($expected_lines),'tickets'=>count($issued));
}
