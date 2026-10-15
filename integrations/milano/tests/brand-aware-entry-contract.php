<?php
/**
 * Read-only file-level contract for safe Milano programme ownership.
 * Requires no WP DB, plugins, payment operations or user records.
 */
$root = dirname(__DIR__,3);
$admin = file_get_contents($root.'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php');
$ability = file_get_contents($root.'/wp-content/plugins/madagaskar-ai-abilities/modules/program-venue-event-sales.php');
$service = file_get_contents($root.'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-program-service.php');
function verify_brand_contract($condition,$message) {
    if (!$condition) {fwrite(STDERR, 'FAIL '.$message."\n"); exit(1);}
}
verify_brand_contract(strpos($admin, '<select name="brand" required>') !== false, 'human admin explicitly selects brand');
verify_brand_contract(strpos($admin, '<option value="Milano Sirki">Milano Sirki</option>') !== false, 'Milano option visible');
verify_brand_contract(strpos($admin, '<option value="Madagaskar Sirki">Madagaskar Sirki</option>') !== false, 'Madagaskar option preserved');
verify_brand_contract(strpos($ability, "'brand'         => sanitize_text_field( \$input['brand'] ?? 'Madagaskar Sirki' )") !== false, 'AI callback forwards explicit brand and preserves legacy');
verify_brand_contract(strpos($ability, "'brand'         => array( 'type' => 'string', 'enum' => array( 'Madagaskar Sirki', 'Milano Sirki' ) )") !== false, 'AI registered schema validates brand');
verify_brand_contract(strpos($service, "'brand'         => $brand") !== false, 'MMC service persists brand');
verify_brand_contract(strpos($service, "in_array( $brand, array( 'Madagaskar Sirki', 'Milano Sirki' ), true )") !== false, 'MMC rejects unknown brand');
echo "PASS brand-aware programme entry contract\n";
