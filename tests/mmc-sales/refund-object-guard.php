<?php
/**
 * Regression: event-wide MMC sales sync must not treat WC_Order_Refund-like
 * objects as parent orders. No WordPress or WooCommerce writes are performed.
 */
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);

function absint($v) { return abs((int)$v); }
function sanitize_key($v) { return (string)$v; }
function sanitize_text_field($v) { return (string)$v; }
function current_time($format) { return '2026-10-08 22:50:00'; }
function add_action(...$args) {}
function wp_json_encode($v) { return json_encode($v); }

class FakeResult {
    public $orders = array();
    public $max_num_pages = 1;
}
class SyntheticRefund {
    private $id;
    function __construct($id) { $this->id = $id; }
    function get_id() { return $this->id; }
    function get_items($type) { return array(); }
    // Deliberately no get_payment_method()/get_payment_method_title().
}
class SyntheticOrder {
    private $id;
    function __construct($id) { $this->id = $id; }
    function get_id() { return $this->id; }
    function get_items($type) { return array(); }
    function get_payment_method() { return 'paytr_payment_gateway'; }
    function get_payment_method_title() { return 'PayTR'; }
}
$GLOBALS['orders'] = array(
    100 => new SyntheticOrder(100),
    200 => new SyntheticRefund(200),
);
$GLOBALS['query_args'] = array();

function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? false; }
function wc_get_orders($args) {
    $GLOBALS['query_args'] = $args;
    $r = new FakeResult();
    // Return both objects anyway to prove sync_order() is defensively safe
    // even if a provider/runtime ignores the type filter.
    $r->orders = array($GLOBALS['orders'][100], $GLOBALS['orders'][200]);
    return $r;
}

class MMC_Event_Service {
    static function get_event($id) { return (object)array('id'=>$id,'program_id'=>7); }
    static function integrations($id) { return array(); }
}
class MMC_Program_Service {
    static function add_log(...$args) {}
    static function get_program($id) { return (object)array('id'=>$id,'status'=>'sales_open'); }
    static function set_status(...$args) {}
}
class FakeDB {
    public $prefix='wp_';
    function get_var($sql) { return 0; }
    function get_row($sql,$format=null) { return null; }
    function get_results($sql,$format=null) { return array(); }
    function update(...$args) { throw new RuntimeException('unexpected write'); }
    function insert(...$args) { throw new RuntimeException('unexpected write'); }
    function prepare($sql,...$args) {
        foreach($args as $arg) $sql=preg_replace('/%[ds]/',is_numeric($arg)?(string)$arg:"'x'",$sql,1);
        return $sql;
    }
}
$wpdb = new FakeDB();

require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php';

$checks=0;
function expect_same($actual,$expected,$label){
    global $checks; $checks++;
    if($actual!==$expected) throw new RuntimeException($label.': '.var_export([$actual,$expected],true));
}

expect_same(MMC_Sales_Service::sync_order(200),false,'refund-like object skipped');

$result=MMC_Sales_Service::sync_event_orders(7,30);
if(is_object($result) && method_exists($result,'get_error_code')) {
    throw new RuntimeException('sync_event_orders returned error');
}
expect_same($GLOBALS['query_args']['type'] ?? null,'shop_order','event scan filters shop_order');
expect_same($result['orders_scanned'] ?? null,2,'scan continues across refund-like object');

echo json_encode(array(
    'assertions'=>$checks,
    'result'=>'PASS',
    'refund_like_skipped'=>true,
    'event_query_type'=>$GLOBALS['query_args']['type'] ?? null,
),JSON_PRETTY_PRINT)."\n";
