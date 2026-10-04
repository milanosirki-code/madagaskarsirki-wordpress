<?php
/**
 * Isolated regressions for the staged Issue #116 snippets (rule: sales close at session start):
 *   docs/code-snippets/staged-2026-10-04/ms-seans-satis-kilidi-v2.php.txt
 *   docs/code-snippets/staged-2026-10-04/ms-seans-denetimi-v3.php.txt
 *   docs/code-snippets/staged-2026-10-04/snippet-030-seans-baslayinca-v1.php.txt
 *
 * No WordPress, database, gateway or network access. Warnings and notices fail the run.
 * Run a second time with MS_TEST_DELEGATE=1 to check that the lock snippet defers to
 * MDG_Sessions::sales_closed_by_time() once PR #117 is deployed.
 */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
date_default_timezone_set( 'UTC' );
set_error_handler( function ( $no, $str, $file, $line ) {
    if ( in_array( $no, array( E_DEPRECATED, E_USER_DEPRECATED ), true ) ) { return true; }
    throw new ErrorException( $str, 0, $no, $file, $line );
} );
set_exception_handler( function ( $e ) {
    $where = basename( $e->getFile() ) . ':' . $e->getLine();
    echo 'FAIL (PHP ' . PHP_VERSION . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $where . "\n";
    echo '::error title=past-session snippets regression::' . str_replace( array( "\r", "\n" ), ' ', get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $where . ' (PHP ' . PHP_VERSION . ')' ) . "\n";
    exit( 1 );
} );

$delegate = '1' === getenv( 'MS_TEST_DELEGATE' );
if ( $delegate ) {
    final class MDG_Sessions {
        public static $calls = 0;
        public static function sales_closed_by_time( $session, $now_ts = null ) { self::$calls++; return 777 === (int) $session->id; }
    }
}

/* ---------- stubs ---------- */
$hooks = array(); $menus = array(); $is_admin = false; $doing_ajax = false; $can = true; $meta = array(); $products = array(); $orders = array();
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { global $hooks; $hooks[] = array( $hook, $cb, $priority, $args ); }
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { global $hooks; $hooks[] = array( $hook, $cb, $priority, $args ); }
function add_shortcode( $tag, $cb ) { global $shortcodes; $shortcodes[ $tag ] = $cb; }
function add_management_page( $title, $menu, $cap, $slug, $cb ) { global $menus; $menus[] = array( $title, $cap, $slug, $cb ); }
function absint( $n ) { return abs( (int) $n ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Istanbul' ); }
function wp_date( $format, $ts = null ) { $d = new DateTimeImmutable( '@' . (int) $ts ); return $d->setTimezone( wp_timezone() )->format( $format ); }
function is_admin() { global $is_admin; return $is_admin; }
function wp_doing_ajax() { global $doing_ajax; return $doing_ajax; }
function current_user_can( $cap ) { global $can; return $can && 'manage_woocommerce' === $cap; }
class DieCalled extends Exception {}
function wp_die( $message = '', $title = '', $args = array() ) { throw new DieCalled( $message, $args['response'] ?? 500 ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function get_post_meta( $id, $key, $single = false ) {
    global $meta;
    if ( in_array( $key, array( 'ticket_code', 'owner_name', 'first_name', 'last_name', 'owner_email' ), true ) ) { throw new RuntimeException( 'the audit must not read ticket codes or owners' ); }
    return $meta[ $id ][ $key ] ?? '';
}
function post_type_exists( $type ) { return 'tc_tickets_instances' === $type; }
$tickets = array(); $ticket_queries = array();
function get_posts( $args ) {
    global $tickets, $ticket_queries;
    $ticket_queries[] = $args;
    if ( 'tc_tickets_instances' !== ( $args['post_type'] ?? '' ) || 'ids' !== ( $args['fields'] ?? '' ) ) { throw new RuntimeException( 'unexpected get_posts call' ); }
    return $tickets[ (int) $args['post_parent'] ] ?? array();
}
function wc_get_product( $id ) { global $products; return $products[ $id ] ?? false; }
function wc_get_order( $id ) { global $orders; return $orders[ $id ] ?? false; }
function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }

class FakeProduct {
    private $id; private $parent; public $purchasable = true;
    public function __construct( $id, $parent = 0 ) { $this->id = $id; $this->parent = $parent; }
    public function get_id() { return $this->id; }
    public function get_parent_id() { return $this->parent; }
    public function is_purchasable() { return $this->purchasable; }
}
class FakeOrder {
    private $created; private $status; private $paid;
    public function __construct( $created, $status, $paid = 0 ) { $this->created = $created; $this->status = $status; $this->paid = $paid; }
    public function get_date_created() { return new DateTimeImmutable( '@' . $this->created ); }
    public function get_status() { return $this->status; }
    public function get_date_paid() { return $this->paid ? new DateTimeImmutable( '@' . $this->paid ) : null; }
    public function get_billing_phone() { throw new RuntimeException( 'the audit must not read customer data' ); }
    public function get_billing_email() { throw new RuntimeException( 'the audit must not read customer data' ); }
    public function get_formatted_billing_full_name() { throw new RuntimeException( 'the audit must not read customer data' ); }
}
class FakeDB {
    public $prefix = 'wp_';
    public $sessions = array(); public $events = array(); public $order_map = array();
    public $queries = array(); public $writes = 0; public $explode = false;
    public function prepare( $sql, ...$args ) { return preg_replace_callback( '/%[ds]/', function ( $m ) use ( &$args ) { $v = array_shift( $args ); return '%d' === $m[0] ? (string) (int) $v : "'" . $v . "'"; }, $sql ); }
    public function get_var( $sql ) { return preg_match( "/SHOW TABLES LIKE '([^']+)'/", $sql, $m ) ? $m[1] : null; }
    public function get_results( $sql ) {
        if ( $this->explode ) { throw new RuntimeException( 'database unavailable' ); }
        $this->queries[] = $sql;
        if ( preg_match( '/FROM wp_mdg_sessions WHERE wc_product_id = (\d+)/', $sql, $m ) ) {
            return array_values( array_filter( $this->sessions, function ( $s ) use ( $m ) { return (int) $s->wc_product_id === (int) $m[1]; } ) );
        }
        // Snippet #30 session query: only the start-time form is understood here.
        if ( preg_match( '/SELECT id, start_at, end_at\s+FROM wp_mdg_sessions\s+WHERE event_id = (\d+)\s+AND start_at > \'([^\']+)\'/', $sql, $m ) ) {
            return array_values( array_filter( $this->sessions, function ( $s ) use ( $m ) { return (int) $s->event_id === (int) $m[1] && (string) $s->start_at > $m[2]; } ) );
        }
        if ( false !== strpos( $sql, 'FROM wp_mdg_order_map m' ) ) { return $this->order_map; }
        if ( false !== strpos( $sql, 'GROUP BY e.id' ) ) { return $this->events; }
        if ( false !== strpos( $sql, 'FROM wp_mdg_sessions s' ) ) { return array_values( array_filter( $this->sessions, function ( $s ) { return ! empty( $s->listed ); } ) ); }
        throw new RuntimeException( 'unexpected query: ' . $sql );
    }
    public function insert( ...$a ) { $this->writes++; } public function update( ...$a ) { $this->writes++; }
    public function replace( ...$a ) { $this->writes++; } public function delete( ...$a ) { $this->writes++; }
    public function query( ...$a ) { $this->writes++; }
}
$wpdb = new FakeDB();

$passed = 0;
function check( $name, $actual, $expected ) {
    global $passed;
    if ( $actual !== $expected ) { throw new RuntimeException( $name . ': ' . json_encode( array( 'actual' => $actual, 'expected' => $expected ), JSON_UNESCAPED_UNICODE ) ); }
    $passed++;
    echo "PASS: $name\n";
}
function load_snippet( $name ) {
    $body = file_get_contents( dirname( __DIR__, 2 ) . '/docs/code-snippets/staged-2026-10-04/' . $name );
    if ( 0 === strpos( ltrim( $body ), '<?php' ) ) { throw new RuntimeException( $name . ' must not start with a PHP tag' ); }
    $tmp = tempnam( sys_get_temp_dir(), 'mssnip' ) . '.php';
    file_put_contents( $tmp, "<?php\n" . $body );
    try { require $tmp; } finally { unlink( $tmp ); }
}
function utc( $ts ) { return gmdate( 'Y-m-d H:i:s', $ts ); }
function sess( $id, $product, $start, $end, $extra = array() ) { return (object) array_merge( array( 'id' => $id, 'event_id' => 9, 'wc_product_id' => $product, 'start_at' => utc( $start ), 'end_at' => utc( $end ), 'status' => 'onsale', 'title' => 'Test Etkinliği' ), $extra ); }

/* ================= A. Sales lock bridge ================= */
load_snippet( 'ms-seans-satis-kilidi-v2.php.txt' );
$t = time();

if ( $delegate ) {
    $wpdb->sessions = array( sess( 777, 500, $t + 3600, $t + 7200 ), sess( 778, 501, $t - 7200, $t - 3600 ) );
    check( 'delegates to the plugin rule when it exists (closed)', ms_gsk_filtre( true, new FakeProduct( 500 ) ), false );
    check( 'delegates to the plugin rule when it exists (open)', ms_gsk_filtre( true, new FakeProduct( 501 ) ), true );
    check( 'plugin rule was actually consulted', MDG_Sessions::$calls, 2 );
    echo "\n$passed checks passed (delegate mode)\n";
    exit( 0 );
}

$registered = array_values( array_filter( $hooks, function ( $h ) { return 'ms_gsk_filtre' === $h[1]; } ) );
check( 'lock registers exactly two filters', count( $registered ), 2 );
check( 'lock hooks product purchasability at priority 98', array( $registered[0][0], $registered[0][2], $registered[0][3] ), array( 'woocommerce_is_purchasable', 98, 2 ) );
check( 'lock hooks variation purchasability at priority 98', array( $registered[1][0], $registered[1][2], $registered[1][3] ), array( 'woocommerce_variation_is_purchasable', 98, 2 ) );

// Fixed clock: 4 Ekim 2026 15:25 Europe/Istanbul.
$now = gmmktime( 12, 25, 0, 10, 4, 2026 );
$s = function ( $start, $end ) { return (object) array( 'start_at' => $start, 'end_at' => $end ); };
check( 'rule: 14:00 session that ended at 15:00 is closed', ms_gsk_seans_kapali_mi( $s( '2026-10-04 11:00:00', '2026-10-04 12:00:00' ), $now ), true );
check( 'rule: 16:00 session later today is open', ms_gsk_seans_kapali_mi( $s( '2026-10-04 13:00:00', '2026-10-04 14:00:00' ), $now ), false );
check( 'rule: session in progress is closed', ms_gsk_seans_kapali_mi( $s( '2026-10-04 12:00:00', '2026-10-04 13:00:00' ), $now ), true );
check( 'rule: session starting exactly now is closed', ms_gsk_seans_kapali_mi( $s( '2026-10-04 12:25:00', '2026-10-04 13:25:00' ), $now ), true );
check( 'rule: session starting in one minute is open', ms_gsk_seans_kapali_mi( $s( '2026-10-04 12:26:00', '2026-10-04 13:26:00' ), $now ), false );
check( 'rule: yesterday is closed', ms_gsk_seans_kapali_mi( $s( '2026-10-03 13:00:00', '2026-10-03 14:00:00' ), $now ), true );
check( 'rule: stale end_at with a past start is closed', ms_gsk_seans_kapali_mi( $s( '2026-09-26 09:00:00', '2027-01-01 00:00:00' ), $now ), true );
check( 'rule: future start with a wrongly past end_at stays open', ms_gsk_seans_kapali_mi( $s( '2026-10-04 13:00:00', '2026-10-04 10:00:00' ), $now ), false );
check( 'rule: unreadable start with a past end is closed', ms_gsk_seans_kapali_mi( $s( 'bad', '2026-10-04 12:00:00' ), $now ), true );
check( 'rule: unreadable start with a future end stays open', ms_gsk_seans_kapali_mi( $s( 'bad', '2026-10-04 14:00:00' ), $now ), false );
check( 'rule: unreadable times never close', ms_gsk_seans_kapali_mi( $s( 'bad', '0000-00-00 00:00:00' ), $now ), false );
check( 'rule: impossible calendar date never closes', ms_gsk_seans_kapali_mi( $s( '2026-13-40 09:00:00', '2026-13-40 10:00:00' ), $now ), false );

$wpdb->sessions = array(
    sess( 1, 100, $t - 3 * 3600, $t - 2 * 3600 ),   // ended
    sess( 2, 200, $t + 2 * 3600, $t + 3 * 3600 ),   // future
    sess( 3, 300, $t - 3 * 3600, $t - 2 * 3600 ),   // ended ...
    sess( 4, 300, $t + 2 * 3600, $t + 3 * 3600 ),   // ... but the same product also has an open session
    (object) array( 'id' => 5, 'wc_product_id' => 400, 'start_at' => 'bad', 'end_at' => '' ),
    sess( 6, 500, $t - 10 * 60, $t + 50 * 60 ),     // in progress
);
check( 'ended-session product is not purchasable', ms_gsk_filtre( true, new FakeProduct( 100 ) ), false );
check( 'variation of an ended-session product is not purchasable', ms_gsk_filtre( true, new FakeProduct( 101, 100 ) ), false );
check( 'product of a session in progress is not purchasable', ms_gsk_filtre( true, new FakeProduct( 500 ) ), false );
check( 'future-session product stays purchasable', ms_gsk_filtre( true, new FakeProduct( 200 ) ), true );
check( 'variation of a future-session product stays purchasable', ms_gsk_filtre( true, new FakeProduct( 201, 200 ) ), true );
check( 'product with one open session stays purchasable', ms_gsk_filtre( true, new FakeProduct( 300 ) ), true );
check( 'product with unreadable session times stays purchasable', ms_gsk_filtre( true, new FakeProduct( 400 ) ), true );
check( 'product without an MDG session stays purchasable', ms_gsk_filtre( true, new FakeProduct( 999 ) ), true );
check( 'non-object product is left alone', ms_gsk_filtre( true, null ), true );

$before = count( $wpdb->queries );
check( 'an already-closed product stays closed', ms_gsk_filtre( false, new FakeProduct( 200 ) ), false );
check( 'an already-closed product costs no query', count( $wpdb->queries ), $before );
ms_gsk_filtre( true, new FakeProduct( 100 ) );
ms_gsk_filtre( true, new FakeProduct( 102, 100 ) );
check( 'repeat lookups are served from the request cache', count( $wpdb->queries ), $before );

$is_admin = true; $doing_ajax = false;
check( 'admin screens are not affected', ms_gsk_filtre( true, new FakeProduct( 100 ) ), true );
$doing_ajax = true;
check( 'admin-ajax add-to-cart is affected', ms_gsk_filtre( true, new FakeProduct( 100 ) ), false );
$is_admin = false; $doing_ajax = false;

$GLOBALS['ms_gsk_onbellek'] = array();
$wpdb->explode = true;
check( 'database failure never closes sales', ms_gsk_filtre( true, new FakeProduct( 100 ) ), true );
$wpdb->explode = false;
$GLOBALS['ms_gsk_onbellek'] = array();
check( 'lock snippet wrote nothing', $wpdb->writes, 0 );

/* ================= B. Read-only audit ================= */
$hooks = array();
load_snippet( 'ms-seans-denetimi-v3.php.txt' );
check( 'audit registers only an admin_menu action', array_map( function ( $h ) { return $h[0]; }, $hooks ), array( 'admin_menu' ) );
$hooks[0][1]();
check( 'audit page requires manage_woocommerce', array( $menus[0][1], $menus[0][2] ), array( 'manage_woocommerce', 'ms-seans-denetimi' ) );

$can = false;
$code = 0;
try { ob_start(); ms_gsd_sayfa(); } catch ( DieCalled $e ) { $code = $e->getCode(); } finally { ob_end_clean(); }
check( 'audit page refuses users without the capability', $code, 403 );
$can = true;

$ended_start = $t - 26 * 3600; $ended_end = $t - 25 * 3600;
$wpdb->events = array( (object) array( 'id' => 8, 'title' => 'Madagaskar Sirki – Sincan', 'public_slug' => 'madagaskar-sirki-sincan', 'province_name' => 'Ankara', 'district' => 'Sincan', 'son_baslangic' => utc( $ended_start ), 'seans_sayisi' => 3 ) );
$wpdb->sessions = array(
    sess( 21, 610, $ended_start, $ended_end, array( 'listed' => true, 'event_id' => 8 ) ),
    sess( 22, 620, $ended_start, $ended_end, array( 'listed' => true, 'event_id' => 8 ) ),
);
$meta[620]['_mdg_v371_sales_closed'] = 'yes';
$products[610] = new FakeProduct( 610 );
$closed_product = new FakeProduct( 620 ); $closed_product->purchasable = false; $products[620] = $closed_product;
$row = function ( $order_id, $qty, $total ) use ( $ended_start, $ended_end ) {
    return (object) array( 'order_id' => $order_id, 'session_id' => 21, 'quantity' => $qty, 'line_total' => $total, 'kayit_at' => utc( time() ), 'start_at' => utc( $ended_start ), 'end_at' => utc( $ended_end ), 'title' => 'Madagaskar Sirki – Sincan' );
};
$wpdb->order_map = array( $row( 9001, 2, '500.00' ), $row( 9001, 1, '250.00' ), $row( 9002, 1, '250.00' ), $row( 9003, 4, '1100.00' ), $row( 9004, 1, '250.00' ), $row( 9005, 1, '250.00' ) );
$orders[9005] = new FakeOrder( $ended_start - 60, 'processing', $ended_start + 120 );          // ordered a minute before start, paid after: allowed
$orders[9001] = new FakeOrder( $ended_start + 20 * 60, 'processing', $ended_start + 25 * 60 ); // paid, 20 min after the session started
$orders[9002] = new FakeOrder( $ended_start, 'failed' );                                        // unpaid attempt at the exact start
$orders[9003] = new FakeOrder( $ended_start - 3 * 3600, 'processing', $ended_start - 3 * 3600 ); // bought in time
// 9004: order no longer exists.

// Tickets: order 9001 has 3 tickets, 1 scanned at the door; order 9002 is unpaid.
$tickets[9001] = array( 70001, 70002, 70003 );
$meta[70001]['tc_checkins'] = array( array( 'date_checked' => 1, 'status' => 'Pass' ) );
$meta[70002]['tc_checkins'] = array( array( 'date_checked' => 1, 'status' => 'Fail' ) ); // refused scan is not a use
$tickets[9002] = array( 70004 );
// Order 9006: paid late, both tickets scanned -> attended, nothing to transfer.
$wpdb->order_map[] = $row( 9006, 2, '500.00' );
$orders[9006] = new FakeOrder( $ended_start + 5 * 60, 'completed', $ended_start + 6 * 60 );
$tickets[9006] = array( 70005, 70006 );
$meta[70005]['tc_checkins'] = array( array( 'status' => 'pass' ) );
$meta[70006]['tc_checkins'] = array( array( 'x' => 1 ) ); // record without a status still counts as a scan
// Order 9007: paid late, ticket already invalidated (e.g. refunded) -> nothing to transfer.
$wpdb->order_map[] = $row( 9007, 1, '250.00' );
$orders[9007] = new FakeOrder( $ended_start + 5 * 60, 'processing', $ended_start + 6 * 60 );
$tickets[9007] = array( 70007 );
$meta[70007]['_mdg_invalidated'] = 'yes';
// Order 9008: paid late, no ticket instance found.
$wpdb->order_map[] = $row( 9008, 1, '250.00' );
$orders[9008] = new FakeOrder( $ended_start + 5 * 60, 'processing', $ended_start + 6 * 60 );

$wpdb->queries = array();
$_GET = array();
ob_start(); ms_gsd_sayfa(); $html = ob_get_clean();

check( 'order with unused tickets is a transfer candidate with the unused count', 1 === preg_match( '/#9001<\/td>.*?<td>3<\/td><td>1<\/td><td>0<\/td><td><strong>AKTARIM ADAYI \(2 bilet\)<\/strong>/s', $html ), true );
check( 'order whose tickets were all scanned needs no action', 1 === preg_match( '/#9006<\/td>.*?<td>2<\/td><td>2<\/td><td>0<\/td><td>Kullanılmış; işlem yok/s', $html ), true );
check( 'order whose ticket is invalidated needs no action', 1 === preg_match( '/#9007<\/td>.*?<td>1<\/td><td>0<\/td><td>1<\/td><td>Geçersiz kılınmış; işlem yok/s', $html ), true );
check( 'paid order without ticket instances is flagged for manual review', 1 === preg_match( '/#9008<\/td>.*?<td>0<\/td><td>0<\/td><td>0<\/td><td>Bilet bulunamadı; elle incele/s', $html ), true );
check( 'unpaid late order is never a candidate', 1 === preg_match( '/#9002<\/td>.*?failed<\/td><td>hayır<\/td>.*?<td>—<\/td>/s', $html ), true );
check( 'audit counts exactly one transfer candidate order', false !== strpos( $html, 'Aktarım adayı sipariş: <strong>1</strong>' ), true );
check( 'tickets are looked up by order parent, read-only, ids only', array_values( array_unique( array_map( function ( $q ) { return $q['post_type'] . '|' . $q['fields']; }, $ticket_queries ) ) ), array( 'tc_tickets_instances|ids' ) );
check( 'each late order is looked up once', count( $ticket_queries ), 5 );

check( 'audit lists the ended event that is still on sale', false !== strpos( $html, 'hâlâ "satışta" etkinlikler: 1' ) && false !== strpos( $html, '#8' ), true );
check( 'audit links the event page', false !== strpos( $html, 'https://example.test/etkinlik/madagaskar-sirki-sincan/' ), true );
check( 'audit lists both started sessions', false !== strpos( $html, 'başlamış seansları: 2' ), true );
check( 'audit shows the product that is still purchasable', 1 === preg_match( '/#610<\/td><td>yok<\/td><td><strong>EVET<\/strong>/', $html ), true );
check( 'audit shows the product already locked by V4', 1 === preg_match( '/#620<\/td><td>var<\/td><td>hayır/', $html ), true );
check( 'audit counts late order lines', false !== strpos( $html, 'sipariş kalemleri: 6' ), true );
check( 'audit counts paid late orders and their amount', false !== strpos( $html, 'Ödenmiş sipariş: <strong>4</strong>' ) && false !== strpos( $html, '1.750,00 TL' ), true );
check( 'audit reports the delay after the start in minutes', false !== strpos( $html, '20 dk' ) && false !== strpos( $html, '>0 dk' ), true );
check( 'audit marks the unpaid late order as unpaid', 1 === preg_match( '/#9002<\/td>.*?failed<\/td><td>hayır/s', $html ), true );
check( 'order placed before the session is not listed', false === strpos( $html, '#9003' ), true );
check( 'order placed just before the start and paid after is not listed', false === strpos( $html, '#9005' ), true );
check( 'audit states the rule it applies', false !== strpos( $html, 'satış seans başladığında kapanır' ), true );
check( 'missing order is skipped without error', false === strpos( $html, '#9004' ), true );
check( 'default look-back is 7 days', false !== strpos( $html, 'son 7 gün' ), true );
check( 'every audit query is a SELECT', array_values( array_unique( array_map( function ( $q ) { return strtoupper( substr( ltrim( $q ), 0, 6 ) ); }, $wpdb->queries ) ) ), array( 'SELECT' ) );
check( 'audit ran exactly three queries', count( $wpdb->queries ), 3 );
check( 'audit wrote nothing', $wpdb->writes, 0 );

$_GET = array( 'gun' => '500' );
ob_start(); ms_gsd_sayfa(); $html = ob_get_clean();
check( 'look-back is capped at 60 days', false !== strpos( $html, 'son 60 gün' ), true );
$_GET = array( 'gun' => array( 'x' ) );
ob_start(); ms_gsd_sayfa(); $html = ob_get_clean();
check( 'array input for the look-back falls back to the default', false !== strpos( $html, 'son 7 gün' ), true );

$wpdb->events = array(); $wpdb->sessions = array(); $wpdb->order_map = array();
$_GET = array();
ob_start(); ms_gsd_sayfa(); $html = ob_get_clean();
check( 'empty result renders three empty sections', substr_count( $html, '<p>Yok.</p>' ), 3 );

/* ================= C. Snippet #30 with the start rule ================= */
$prod   = file( dirname( __DIR__, 2 ) . '/docs/code-snippets/production/snippet-030.php.txt' );
$staged = file( dirname( __DIR__, 2 ) . '/docs/code-snippets/staged-2026-10-04/snippet-030-seans-baslayinca-v1.php.txt' );
$removed = array_values( array_map( 'trim', array_diff( $prod, $staged ) ) );
$added   = array_values( array_map( 'trim', array_diff( $staged, $prod ) ) );
check( 'snippet #30 change removes exactly the two end-time conditions', $removed, array( 'AND end_at >= %s', 'AND s.end_at >= %s' ) );
check( 'snippet #30 change adds the two start-time conditions and a header note', array_values( array_filter( $added, function ( $l ) { return 0 === strpos( $l, 'AND' ); } ) ), array( 'AND start_at > %s', 'AND s.start_at > %s' ) );
check( 'snippet #30 change adds nothing but conditions and comment lines', count( array_filter( $added, function ( $l ) { return 0 !== strpos( $l, 'AND' ) && 0 !== strpos( $l, '*' ); } ) ), 0 );
check( 'snippet #30 line count grows only by the header note', count( $staged ) - count( $prod ), 4 );

if ( ! class_exists( 'MDG_DB' ) ) { final class MDG_DB { public static function table( $name ) { return 'wp_mdg_' . $name; } } }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
$hooks = array(); $shortcodes = array();
load_snippet( 'snippet-030-seans-baslayinca-v1.php.txt' );
$t = time();
$wpdb->sessions = array(
    sess( 31, 0, $t - 3 * 3600, $t - 2 * 3600, array( 'event_id' => 12 ) ),   // ended
    sess( 32, 0, $t - 10 * 60, $t + 50 * 60, array( 'event_id' => 12 ) ),     // in progress
    sess( 33, 0, $t + 26 * 3600, $t + 27 * 3600, array( 'event_id' => 12 ) ), // tomorrow
    sess( 34, 0, $t + 26 * 3600, $t + 27 * 3600, array( 'event_id' => 13 ) ), // other event
);
$wpdb->queries = array();
$listed = ms_city_v2_event_sessions( 12 );
check( 'snippet #30 lists only the session that has not started', array_column( $listed, 'id' ), array( 33 ) );
check( 'snippet #30 session query filters on start_at', 1 === preg_match( '/AND start_at > \'\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\'/', end( $wpdb->queries ) ), true );
check( 'snippet #30 wrote nothing', $wpdb->writes, 0 );

echo "\n$passed checks passed\n";
