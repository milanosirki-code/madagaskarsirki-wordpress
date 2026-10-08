<?php
/**
 * Regression: MMC sales sync must skip refund-like objects and event-wide
 * scans must request normal shop orders only.
 */
define('ABSPATH', __DIR__);
function absint($v) { return abs((int)$v); }
function sanitize_key($v) { return (string)$v; }
function sanitize_text_field($v) { return (string)$v; }
function current_time($format) { return '2026-10-08 22:50:00'; }
function add_action(...$args) {}

class SyntheticRefund {
    private $id;
    function __construct($id) { $this->id = $id; }
    function get_id() { return $this->id; }
    function get_items($type) { return array(); }
    // Deliberately no payment-method accessors.
}
$GLOBALS['orders'] = array(200 => new SyntheticRefund(200));
function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? false; }
function wc_get_orders($args) { throw new RuntimeException('event scan must not run in this isolated guard test'); }

class MMC_Event_Service {
    static function get_event($id) { return null; }
    static function sessions($id) { return array(); }
    static function integrations($id) { return array(); }
}
class FakeDB {
    public $prefix='wp_';
    function get_var($sql) { return 0; }
    function get_row($sql,$format=null) { return null; }
    function update(...$args) { throw new RuntimeException('unexpected write'); }
    function insert(...$args) { throw new RuntimeException('unexpected write'); }
    function prepare($sql,...$args) { return $sql; }
}
$wpdb = new FakeDB();

$service = dirname(__DIR__,2).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php';
require $service;

$checks=0;
function expect_same($actual,$expected,$label){
    global $checks; $checks++;
    if($actual!==$expected) throw new RuntimeException($label.': '.var_export(array($actual,$expected),true));
}

expect_same(MMC_Sales_Service::sync_order(200),false,'refund-like object skipped safely');

$source=file_get_contents($service);
expect_same(strpos($source,"'type'         => 'shop_order'")!==false,true,'event scan filters shop_order');
expect_same(strpos($source,"method_exists( \$order, 'get_payment_method' )")!==false,true,'payment method guard present');
expect_same(strpos($source,"method_exists( \$order, 'get_payment_method_title' )")!==false,true,'payment title guard present');

echo json_encode(array(
    'assertions'=>$checks,
    'result'=>'PASS',
    'refund_like_skipped'=>true,
    'event_query_type'=>'shop_order',
),JSON_PRETTY_PRINT)."\n";
