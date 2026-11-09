<?php
// Isolated contract tests: every network and WordPress write is a local stub.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('MS_KOMMO_BASE_URL', 'https://milanosirki.kommo.com/api/v4');
define('MS_KOMMO_PIPELINE_ID', 14297695);
$registered_hooks = array();
function add_filter(...$args) { $GLOBALS['registered_hooks'][] = $args[0]; }
function add_action(...$args) { $GLOBALS['registered_hooks'][] = $args[0]; }
function is_wp_error($value) { return $value instanceof Exception; }
function wp_parse_url($value) { return parse_url($value); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value) { return json_encode($value); }
function ms_kommo_token_value() { return 'synthetic-local-test'; }
function wc_get_order($id) {
    $GLOBALS['order_reads']++;
    if ($GLOBALS['refund_on_read'] === $GLOBALS['order_reads']) { $GLOBALS['order']->status = 'refunded'; }
    return $GLOBALS['order'];
}
function wc_get_orders($query) { return $GLOBALS['orders']; }
function add_option($key, $value, ...$args) {
    if (isset($GLOBALS['options'][$key])) { return false; }
    $GLOBALS['options'][$key] = $value; return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function wp_schedule_single_event(...$args) { return $GLOBALS['schedule_ok']; }
function ms_biletlerim_url($id) { return $GLOBALS['ticket_url']; }
function ms_kommo_write_bilet_url($lead, $url) {
    $GLOBALS['writes'][] = array($lead, $url); return true;
}
function wp_remote_request($url, $args) {
    $GLOBALS['requests'][] = array($url, $args['method']);
    if (str_contains($url, '/leads/')) { return array('code' => 200, 'body' => json_encode($GLOBALS['lead'])); }
    if (str_contains($url, '/contacts/')) { return array('code' => 200, 'body' => json_encode($GLOBALS['contact'])); }
    if (str_contains($url, '/bots/110115/run')) {
        $GLOBALS['posts']++;
        $body = json_decode($args['body'], true);
        check($body === array('entity_id' => 50, 'entity_type' => 'leads'), 'Bot target is the verified lead');
        return array('code' => $GLOBALS['bot_status'], 'body' => '');
    }
    throw new Exception('Unexpected local test route');
}
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
class OrderFixture {
    public $id = 42;
    public $status = 'processing';
    public $total = 1100;
    public $phone = '+905550000000';
    public function get_id() { return $this->id; }
    public function get_type() { return 'shop_order'; }
    public function has_status($statuses) { return in_array($this->status, $statuses, true); }
    public function is_paid() { return in_array($this->status, array('processing', 'completed'), true); }
    public function get_total() { return $this->total; }
    public function get_billing_phone() { return $this->phone; }
    public function get_meta($name) { return $name === '_ms_kommo_lead_id' ? 50 : ''; }
}
function reset_fixture() {
    $GLOBALS['order'] = new OrderFixture();
    $GLOBALS['orders'] = array($GLOBALS['order']);
    $GLOBALS['lead'] = array('name' => 'Order#42', 'pipeline_id' => MS_KOMMO_PIPELINE_ID,
        '_embedded' => array('contacts' => array(array('id' => 60, 'is_main' => true))));
    $GLOBALS['contact'] = array('id' => 60, 'custom_fields_values' => array(array('field_code' => 'PHONE',
        'values' => array(array('value' => '+90 555 000 00 00')))));
    $GLOBALS['options'] = $GLOBALS['writes'] = $GLOBALS['requests'] = array();
    $GLOBALS['posts'] = 0;
    $GLOBALS['schedule_ok'] = true;
    $GLOBALS['order_reads'] = 0;
    $GLOBALS['refund_on_read'] = 0;
    $GLOBALS['bot_status'] = 202;
    $GLOBALS['ticket_url'] = 'https://madagaskarsirki.com/biletlerim/?order_id=42&token=' . str_repeat('a', 64);
}
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    $checks++;
}
require __DIR__ . '/../docs/code-snippets/mdg-ticket-recovery-20261109.php';
$class = 'MDG_Ticket_Recovery_20261109';
check(count($registered_hooks) === 6, 'Loading the snippet registers all six hooks');
require __DIR__ . '/../docs/code-snippets/mdg-ticket-recovery-20261109.php';
check(count($registered_hooks) === 6, 'Loading twice does not duplicate hooks');
foreach (array('05550000000', '+90 555 000 00 00', '00905550000000', '5550000000') as $number) {
    check($class::phone($number) === '5550000000', 'TR mobile variants normalize');
}
foreach (array('123', '+15550000000', '555000000000000', array(), '03129113710') as $number) {
    if (is_array($number)) { continue; } // POST array inputs are rejected before normalization.
    check($class::phone($number) === '', 'Invalid/non-TR/non-mobile numbers are rejected');
}
$raw = 'https://madagaskarsirki.com/?download_ticket=1&nonce=synthetic';
check($class::label($raw) === $raw, 'Raw download URL is not rewritten');
check($class::label('<a href="' . $raw . '">Download</a>') === '<a href="' . $raw . '">QR Biletimi Aç / İndir</a>', 'Only label changes; signed href is preserved');
check($class::label('<a href="x">Bileti Görüntüle / İndir</a>') === '<a href="x">Bileti Görüntüle / İndir</a>', 'Existing custom label is preserved');
reset_fixture();
check($class::send(42) === true, 'Verified paid order queues approved Salesbot');
check(count($GLOBALS['writes']) === 1 && $GLOBALS['posts'] === 1, 'Single ticket field write and send');
check($class::send(42) === false && $GLOBALS['posts'] === 1, 'Duplicate job cannot resend during lease');
$class::unlock(42);
check($class::send(42) === true && $GLOBALS['posts'] === 2, 'Explicit cooldown expiry allows a later retry');
foreach (array('pending', 'failed', 'cancelled', 'refunded', 'on-hold') as $status) {
    reset_fixture(); $GLOBALS['order']->status = $status;
    check($class::send(42) === false && count($GLOBALS['requests']) === 0, 'Unpaid/refunded/cancelled orders never contact Kommo');
}
reset_fixture(); $GLOBALS['order']->total = 0;
check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Free order is not resent by the paid-order recovery flow');
foreach (array(2, 3) as $read) {
    reset_fixture(); $GLOBALS['refund_on_read'] = $read;
    check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Refund during remote verification blocks bot launch');
}
reset_fixture(); $GLOBALS['contact']['custom_fields_values'][0]['values'][0]['value'] = '+905551111111';
check($class::send(42) === false && $GLOBALS['posts'] === 0 && count($GLOBALS['writes']) === 0, 'Wrong main-contact phone blocks all writes');
reset_fixture(); $GLOBALS['lead']['name'] = 'Order#43';
check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Different order name blocks send');
reset_fixture(); $GLOBALS['lead']['pipeline_id'] = 1;
check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Wrong pipeline blocks send');
reset_fixture(); $GLOBALS['lead']['_embedded']['contacts'][] = array('id' => 61, 'is_main' => true);
check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Ambiguous main contact blocks send');
reset_fixture(); $GLOBALS['ticket_url'] = 'https://untrusted.example/biletlerim/?order_id=42&token=' . str_repeat('a', 64);
check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Off-site ticket URL is rejected');
reset_fixture(); $GLOBALS['ticket_url'] = str_replace('order_id=42', 'order_id=43', $GLOBALS['ticket_url']);
check($class::send(42) === false && $GLOBALS['posts'] === 0, 'Other order ticket URL is rejected');
reset_fixture(); $GLOBALS['schedule_ok'] = false;
check($class::send(42) === false && count($GLOBALS['writes']) === 0 && count($GLOBALS['options']) === 0, 'Failed lease cleanup scheduling blocks send and releases lock');
reset_fixture(); $GLOBALS['bot_status'] = 500;
check($class::send(42) === false && $GLOBALS['posts'] === 1, 'Kommo failure is not reported as queue success');
reset_fixture();
$other = new OrderFixture(); $other->id = 43; $other->phone = '+905551111111';
$refund = new OrderFixture(); $refund->id = 44; $refund->status = 'refunded';
$GLOBALS['orders'] = array($other, $refund, $GLOBALS['order'], $GLOBALS['order']);
$found = $class::find_orders('5550000000');
check(count($found) === 1 && $found[0]->get_id() === 42, 'Lookup rechecks exact normalized billing phone/status and deduplicates');
echo "PASS: $checks recovery checks; no real network or messages.\n";
