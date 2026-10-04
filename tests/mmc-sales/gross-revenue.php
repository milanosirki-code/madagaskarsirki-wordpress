<?php
/** Actual sales service + SQL, isolated synthetic storage. No WordPress writes. */
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
function absint($v) { return abs((int)$v); }
function sanitize_key($v) { return (string)$v; }
function sanitize_text_field($v) { return (string)$v; }
function current_time($format) { return '2026-10-04 12:00:00'; }
function add_action(...$args) {}
function wc_get_orders(...$args) { return []; }
function wc_get_order($id) { return $GLOBALS['orders'][$id] ?? false; }
class MMC_Event_Service {
    static function get_event($id) { return null; }
    static function sessions($id) { return []; }
    static function integrations($id) { return []; }
}
class SyntheticDB {
    public $prefix = 'wp_', $insert_id = 0, $pdo;
    function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE wp_mmc_ticket_types (id INTEGER, capacity_units INTEGER)');
        $this->pdo->exec('INSERT INTO wp_mmc_ticket_types VALUES (1,4)');
        $this->pdo->exec('CREATE TABLE wp_mmc_sales_mappings (id INTEGER, is_active INTEGER, wc_variation_id INTEGER, wc_product_id INTEGER, program_id INTEGER, event_id INTEGER, session_id INTEGER, ticket_type_id INTEGER)');
        $this->pdo->exec('INSERT INTO wp_mmc_sales_mappings VALUES (1,1,801,701,9,9,19,1)');
        $this->pdo->exec('CREATE TABLE wp_mmc_sales_ledger (id INTEGER PRIMARY KEY, program_id INTEGER, event_id INTEGER, session_id INTEGER, ticket_type_id INTEGER, channel TEXT, external_order_id INTEGER, external_order_item_id INTEGER, order_status TEXT, payment_method TEXT, payment_method_title TEXT, quantity INTEGER, refunded_quantity INTEGER, net_quantity INTEGER, capacity_units INTEGER, gross_amount REAL, refunded_amount REAL, net_amount REAL, paid_at TEXT, last_synced_at TEXT, updated_at TEXT, created_at TEXT)');
    }
    function prepare($sql, ...$args) {
        foreach ($args as $arg) $sql = preg_replace('/%[ds]/', is_numeric($arg) ? (string)$arg : $this->pdo->quote($arg), $sql, 1);
        return $sql;
    }
    function get_row($sql, $format = null) {
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $format === ARRAY_A ? $row : ($row ? (object)$row : null);
    }
    function get_var($sql) { return $this->pdo->query($sql)->fetchColumn(); }
    function insert($table, $row) {
        $sql = 'INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')';
        $this->pdo->prepare($sql)->execute(array_values($row));
        $this->insert_id = (int)$this->pdo->lastInsertId();
        return 1;
    }
    function update($table, $row, $where) {
        $sql = 'UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($row))).' WHERE id=?';
        $this->pdo->prepare($sql)->execute([...array_values($row),$where['id']]);
        return 1;
    }
}
class SyntheticDate { function date($format) { return '2026-10-04 12:00:00'; } }
class SyntheticItem {
    function get_product_id() { return 701; }
    function get_variation_id() { return 801; }
    function get_quantity() { return 1; }
    function get_total() { return 1000; }
    function get_total_tax() { return 200; }
}
class SyntheticOrder {
    public $id, $status, $paid, $refund = 0, $refund_qty = 0;
    function __construct($id,$status,$paid) { $this->id=$id; $this->status=$status; $this->paid=$paid; }
    function get_id() { return $this->id; }
    function get_status() { return $this->status; }
    function is_paid() { return in_array($this->status,['processing','completed'],true); }
    function get_date_paid() { return $this->paid ? new SyntheticDate() : null; }
    function get_transaction_id() { return ''; }
    function get_payment_method() { return 'paytr_payment_gateway'; }
    function get_payment_method_title() { return 'PayTR'; }
    function get_items($type) { return [$this->id+10000=>new SyntheticItem()]; }
    function get_qty_refunded_for_item($id) { return -$this->refund_qty; }
    function get_total_refunded_for_item($id) { return -$this->refund; }
    function get_tax_refunded_for_item($id,$type) { return 0; }
}
$wpdb = new SyntheticDB();
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php';
$checks = 0;
function expect($value,$expected,$label) {
    global $checks; $checks++;
    if ((float)$value !== (float)$expected) throw new RuntimeException($label.': '.var_export($value,true));
}
$GLOBALS['orders'] = [];
foreach ([['processing',true],['completed',true],['failed',false],['pending',false],['cancelled',false],['on-hold',false],['checkout-draft',false],['processing',false],['failed',true],['cancelled',true],['pending',true]] as $n=>$case) {
    $wpdb->pdo->exec('DELETE FROM wp_mmc_sales_ledger');
    $o = new SyntheticOrder($n+1,...$case); $GLOBALS['orders'][$o->id]=$o;
    MMC_Sales_Service::sync_order($o->id);
    $s=MMC_Sales_Service::summary(9);
    $collected = $n < 2 ? 1200 : 0;
    expect($s['gross_revenue'],$collected,$case[0].' collected gross');
    expect($s['nominal_order_value'],1200,'nominal preserved');
    expect($s['net_revenue'],$o->paid?1200:0,'existing net semantics');
    expect($s['sold_capacity'],$o->paid?4:0,'family_2_2 capacity');
    expect($s['orders_count'],1,'all-order count preserved');
}
$wpdb->pdo->exec('DELETE FROM wp_mmc_sales_ledger');
$o=new SyntheticOrder(100,'processing',true); $GLOBALS['orders'][100]=$o;
foreach (['processing','completed','processing','completed'] as $status) {
    $o->status=$status; MMC_Sales_Service::sync_order(100); MMC_Sales_Service::sync_order(100);
    $s=MMC_Sales_Service::summary(9);
    expect($s['gross_revenue'],1200,'callback/status retry gross');
    expect($s['sold_capacity'],4,'callback/status retry family');
    expect($wpdb->get_var('SELECT COUNT(*) FROM wp_mmc_sales_ledger'),1,'upsert unique item');
}
$o->refund=300; MMC_Sales_Service::sync_order(100); $s=MMC_Sales_Service::summary(9);
expect($s['gross_revenue'],1200,'partial refund gross'); expect($s['net_revenue'],900,'partial refund net');
$o->status='refunded'; $o->refund=1200; $o->refund_qty=1; MMC_Sales_Service::sync_order(100); $s=MMC_Sales_Service::summary(9);
expect($o->is_paid(),false,'native current paid status differs from historical payment');
expect($s['gross_revenue'],1200,'full refund original collected gross');
expect($s['net_revenue'],0,'full refund net'); expect($s['refunded_amount'],1200,'full refund amount');
expect($s['sold_capacity'],0,'full refund capacity');
$failed=new SyntheticOrder(101,'failed',false); $GLOBALS['orders'][101]=$failed; MMC_Sales_Service::sync_order(101);
$s=MMC_Sales_Service::summary(9);
expect($s['gross_revenue'],1200,'mixed refunded and failed'); expect($s['nominal_order_value'],2400,'mixed nominal');
expect($s['failed_orders'],1,'failed count'); expect($s['orders_count'],2,'mixed total orders');
$empty=MMC_Sales_Service::summary(999); expect($empty['gross_revenue'],0,'empty event');
echo json_encode(['assertions'=>$checks,'result'=>'PASS','storage'=>'synthetic SQLite','gateway_calls'=>0],JSON_PRETTY_PRINT)."\n";
