<?php
// Exercise legacy brand rejection before any event, preview or database access.
define('ABSPATH', __DIR__);
class WP_Error {
    private $code;
    public function __construct($code, $message) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->code; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int)$v); }
$schemas = array();
function add_action($hook, $callback) { $callback(); }
function wp_register_ability($name, $definition) { global $schemas; $schemas[$name] = $definition['input_schema']; }
class MMC_Program_Service {
    public static function normalized_program_brand($v) {
        return in_array($v, array('Madagaskar Sirki', 'Milano Sirki'), true) ? $v : '';
    }
}
class MMC_MDG_Bridge_Service {
    public static $reads = 0;
    public static function get_mdg_event($id) { self::$reads++; return null; }
}
require dirname(__DIR__, 3) . '/wp-content/plugins/madagaskar-ai-abilities/modules/legacy-mdg-mmc-migrate.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
foreach (array('', 'Milano Sirki', 'both') as $brand) {
    $r = mdg_ai_legacy_migrate_one(array('mdg_event_id'=>1,'brand'=>$brand,'confirmation'=>'MIGRATE_LEGACY_MDG_TO_MMC'));
    check(is_wp_error($r) && $r->get_error_code() === 'mdg_ai_legacy_brand_required', 'Single migration accepted unsafe brand');
    $r = mdg_ai_legacy_migrate_one_internal(1, $brand);
    check(is_wp_error($r) && $r->get_error_code() === 'mdg_ai_legacy_brand_required', 'Internal migration accepted unsafe brand');
}
check(MMC_MDG_Bridge_Service::$reads === 0, 'Rejected request touched event data');
$r = mdg_ai_legacy_migrate_one(array('mdg_event_id'=>1,'brand'=>'Madagaskar Sirki','confirmation'=>'MIGRATE_LEGACY_MDG_TO_MMC'));
check(is_wp_error($r) && $r->get_error_code() === 'mdg_ai_legacy_event_missing', 'Explicit single brand did not reach normal preflight');
$r = mdg_ai_legacy_migrate_batch(array('mdg_event_ids'=>array(1,2),'brand'=>'Madagaskar Sirki','confirmation'=>'MIGRATE_LEGACY_MDG_TO_MMC'));
check($r['failed'] === 2 && MMC_MDG_Bridge_Service::$reads === 3, 'Batch brand not forwarded');
foreach ($schemas as $schema) {
    check(in_array('brand', $schema['required'], true), 'Ability schema omitted required brand');
    check($schema['properties']['brand']['enum'] === array('Madagaskar Sirki'), 'Legacy schema allowed Milano');
}
echo "PASS legacy brand guards, single/batch forwarding and schemas\n";
