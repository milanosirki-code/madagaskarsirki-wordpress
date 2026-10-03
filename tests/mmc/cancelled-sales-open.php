<?php
define('ABSPATH', __DIR__);
function absint($n) { return abs((int)$n); }
function current_time($format) { return '2026-10-03 13:30:00'; }
class MMC_Event_Service {
    public static $event;
    public static $rows = array();
    public static function get_event($id) { return self::$event; }
    public static function integrations($id) { return self::$rows; }
}
class MMC_Program_Service {
    public static $program;
    public static $writes = array();
    public static function get_program($id) { return self::$program; }
    public static function set_status($id, $status, $reason) { self::$writes[] = $status; }
}
class FakeDB {
    public $prefix = 'wp_';
    public $writes = array();
    public function update($table, $data, $where) { $this->writes[] = $data; }
}
require dirname(__DIR__, 2) . '/wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php';
$wpdb = new FakeDB();
$method = new ReflectionMethod('MMC_Sales_Service', 'evaluate_sales_open');
$method->setAccessible(true);
$verified = array_map(function($c) { return (object)array('channel'=>$c, 'status'=>'verified'); }, array('woocommerce','tickera','paytr'));
$cases = array(
    'cancelled with healthy integrations' => array('cancelled', $verified, false),
    'missing program with healthy integrations' => array(null, $verified, false),
    'ready with healthy integrations' => array('ready', $verified, true),
    'ready with missing PayTR' => array('ready', array_slice($verified, 0, 2), false),
);
foreach ($cases as $name => $case) {
    MMC_Event_Service::$event = (object)array('id'=>2, 'program_id'=>2);
    MMC_Event_Service::$rows = $case[1];
    MMC_Program_Service::$program = $case[0] === null ? null : (object)array('status'=>$case[0]);
    MMC_Program_Service::$writes = array(); $wpdb->writes = array();
    $method->invoke(null, 2);
    $opened = count($wpdb->writes) === 1 && MMC_Program_Service::$writes === array('sales_open');
    if ($opened !== $case[2] || (!$case[2] && ($wpdb->writes || MMC_Program_Service::$writes))) { throw new RuntimeException($name); }
    echo "PASS: $name\n";
}
