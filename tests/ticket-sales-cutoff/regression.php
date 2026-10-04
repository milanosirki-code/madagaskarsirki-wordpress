<?php
/**
 * Isolated regressions for the session sales cut-off (Issue #116).
 *
 * Loads the real MDG_Sessions, MDG_Live_Sales and MDG_Public_Event sources with
 * stubbed WordPress/WooCommerce functions. No WordPress, database, gateway or
 * network access. Any PHP warning or notice fails the run.
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MDG_BILET_URL', 'https://example.test/wp-content/plugins/madagaskar-bilet-yonetimi/' );
define( 'MDG_BILET_VERSION', 'test' );
date_default_timezone_set( 'UTC' );
// Warnings and notices fail the run. Deprecations are reported but do not fail it,
// so a newer PHP on the CI runner cannot turn an unrelated deprecation into a red build.
set_error_handler( function ( $no, $str, $file, $line ) {
    if ( in_array( $no, array( E_DEPRECATED, E_USER_DEPRECATED ), true ) ) {
        echo 'DEPRECATED (PHP ' . PHP_VERSION . '): ' . $str . ' in ' . basename( $file ) . ':' . $line . "\n";
        return true;
    }
    throw new ErrorException( $str, 0, $no, $file, $line );
} );
// Surface the reason in the GitHub check annotations as well as the log.
set_exception_handler( function ( $e ) {
    $where = basename( $e->getFile() ) . ':' . $e->getLine();
    echo 'FAIL (PHP ' . PHP_VERSION . '): ' . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $where . "\n";
    echo '::error title=ticket-sales-cutoff regression::' . str_replace( array( "\r", "\n" ), ' ', get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $where . ' (PHP ' . PHP_VERSION . ')' ) . "\n";
    exit( 1 );
} );

/* ---------- WordPress stubs ---------- */
$filters = array();
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { global $filters; $filters[ $hook ][] = $cb; }
function add_action( $hook, $cb, $priority = 10, $args = 1 ) {}
function apply_filters( $hook, $value, ...$args ) { global $filters; foreach ( $filters[ $hook ] ?? array() as $cb ) { $value = $cb( $value, ...$args ); } return $value; }
function wp_timezone() { return new DateTimeZone( 'Europe/Istanbul' ); }
function wp_date( $format, $ts = null ) { $d = new DateTimeImmutable( '@' . ( null === $ts ? time() : (int) $ts ) ); return $d->setTimezone( wp_timezone() )->format( $format ); }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $k ) ); }
function wp_unslash( $v ) { return $v; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function check_ajax_referer( $action, $key ) { return true; }
function current_user_can( $cap ) { return false; }
function language_attributes() { echo 'lang="tr"'; }
function bloginfo( $what ) { echo 'UTF-8'; }
function wp_head() {}
function wp_footer() {}
function wp_body_open() {}
function wp_get_attachment_image_url( $id, $size ) { return ''; }
function wp_get_attachment_metadata( $id ) { return array(); }
function wp_get_attachment_image( $id, $size, $icon, $attr ) { return ''; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_kses_post( $s ) { return $s; }
function wpautop( $s ) { return '<p>' . $s . '</p>'; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function number_format_i18n( $n, $decimals = 0 ) { return number_format( (float) $n, $decimals, ',', '.' ); }
function wc_get_cart_url() { return 'https://example.test/sepet/'; }
function get_post_status( $id ) { return 'publish'; }
function is_wp_error( $v ) { return false; }

class JsonError extends Exception {}
function wp_send_json_error( $data, $status = 400 ) { throw new JsonError( $data['message'], $status ); }
function wp_send_json_success( $data ) { throw new RuntimeException( 'unexpected success' ); }

/* ---------- WooCommerce / MDG collaborators ---------- */
class WooCommerce {}
$products = array();
function wc_get_product( $id ) { global $products; return $products[ $id ] ?? false; }

class MDG_Events {
    public static $event;
    public static function get( $id ) { return self::$event; }
    public static function gallery_ids( $event ) { return array(); }
    public static function faq_items( $event ) { return array(); }
}
class MDG_Capacity {
    public static $holds = array();
    public static function available( $session_id ) { return 100; }
    public static function hold( $session_id, $units, $expires_at, $order_id = 0, $cart_token = '' ) { self::$holds[] = array( (int) $session_id, (int) $units ); return count( self::$holds ); }
    public static function release_hold( $id, $status = 'released' ) {}
}
class FakeItem {
    private $variation;
    public function __construct( $variation ) { $this->variation = $variation; }
    public function get_variation_id() { return $this->variation; }
    public function get_quantity() { return 2; }
}
class WC_Order {
    public $variations = array();
    public function get_id() { return 5001; }
    public function get_items( $type = 'line_item' ) { return array_map( function ( $v ) { return new FakeItem( $v ); }, $this->variations ); }
}

/** Minimal read-only $wpdb: canned rows for the two MDG tables the code reads. */
class FakeDB {
    public $prefix = 'wp_';
    public $sessions = array();
    public $types = array();
    public $writes = 0;
    public function prepare( $sql, ...$args ) {
        if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
        return preg_replace_callback( '/%[ds]/', function () use ( &$args ) { return (string) array_shift( $args ); }, $sql );
    }
    private function match_sessions( $sql ) {
        $rows = array_values( $this->sessions );
        if ( preg_match( '/event_id=(\d+)/', $sql, $m ) ) { $rows = array_values( array_filter( $rows, function ( $r ) use ( $m ) { return (int) $r->event_id === (int) $m[1]; } ) ); }
        if ( preg_match( '/WHERE id=(\d+)/', $sql, $m ) ) { $rows = array_values( array_filter( $rows, function ( $r ) use ( $m ) { return (int) $r->id === (int) $m[1]; } ) ); }
        return $rows;
    }
    private function match_types( $sql ) {
        $rows = array_values( $this->types );
        if ( preg_match( '/session_id=(\d+)/', $sql, $m ) ) { $rows = array_values( array_filter( $rows, function ( $r ) use ( $m ) { return (int) $r->session_id === (int) $m[1]; } ) ); }
        if ( preg_match( '/wc_variation_id=(\d+)/', $sql, $m ) ) { $rows = array_values( array_filter( $rows, function ( $r ) use ( $m ) { return (int) $r->wc_variation_id === (int) $m[1]; } ) ); }
        return $rows;
    }
    public function get_results( $sql ) {
        if ( false !== strpos( $sql, 'wp_mdg_sessions' ) ) { return $this->match_sessions( $sql ); }
        if ( false !== strpos( $sql, 'wp_mdg_ticket_types' ) ) { return $this->match_types( $sql ); }
        return array();
    }
    public function get_row( $sql ) { $rows = $this->get_results( $sql ); return $rows ? $rows[0] : null; }
    public function get_var( $sql ) { return 0; }
    public function insert( ...$a ) { $this->writes++; return 1; }
    public function update( ...$a ) { $this->writes++; return 1; }
    public function replace( ...$a ) { $this->writes++; return 1; }
    public function delete( ...$a ) { $this->writes++; return 1; }
    public function query( ...$a ) { $this->writes++; return 1; }
}
$wpdb = new FakeDB();

$repo = dirname( __DIR__, 2 ) . '/wp-content/plugins/madagaskar-bilet-yonetimi/includes/';
require $repo . 'class-mdg-db.php';
require $repo . 'class-mdg-status.php';
require $repo . 'class-mdg-sessions.php';
require $repo . 'class-mdg-live-sales.php';
require $repo . 'class-mdg-public-event.php';

$passed = 0;
function check( $name, $actual, $expected ) {
    global $passed;
    if ( $actual !== $expected ) { throw new RuntimeException( $name . ': ' . json_encode( array( 'actual' => $actual, 'expected' => $expected ), JSON_UNESCAPED_UNICODE ) ); }
    $passed++;
    echo "PASS: $name\n";
}
function utc( $ts ) { return gmdate( 'Y-m-d H:i:s', $ts ); }
function session_row( $id, $start_ts, $end_ts, $extra = array() ) {
    return (object) array_merge( array(
        'id' => $id, 'event_id' => 9, 'start_at' => utc( $start_ts ), 'end_at' => utc( $end_ts ), 'status' => 'onsale',
        'capacity_total' => 500, 'wc_product_id' => 700 + $id, 'tickera_event_id' => 900,
    ), $extra );
}

/* ================= 1. Decision rule, fixed clock ================= */
// 4 Ekim 2026 15:25 Europe/Istanbul = 12:25 UTC: the moment the problem was observed live.
$now = gmmktime( 12, 25, 0, 10, 4, 2026 );
$s = function ( $start, $end ) { return (object) array( 'start_at' => $start, 'end_at' => $end ); };

check( '12:00 session, ended 13:00 local, is closed', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 09:00:00', '2026-10-04 10:00:00' ), $now ), true );
check( '14:00 session, ended 15:00 local, is closed', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 11:00:00', '2026-10-04 12:00:00' ), $now ), true );
check( '16:00 session later today stays open', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 13:00:00', '2026-10-04 14:00:00' ), $now ), false );
check( 'session in progress stays open until it ends', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 12:00:00', '2026-10-04 13:00:00' ), $now ), false );
check( 'session ending exactly now is still open (end_at >= now)', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 11:25:00', '2026-10-04 12:25:00' ), $now ), false );
check( 'yesterday (Sincan 3 Ekim) is closed', MDG_Sessions::sales_closed_by_time( $s( '2026-10-03 13:00:00', '2026-10-03 14:00:00' ), $now ), true );
check( 'next week stays open', MDG_Sessions::sales_closed_by_time( $s( '2026-10-10 09:00:00', '2026-10-10 10:00:00' ), $now ), false );
check( 'stale far-future end_at with a past start day is closed', MDG_Sessions::sales_closed_by_time( $s( '2026-09-26 09:00:00', '2027-01-01 00:00:00' ), $now ), true );
check( 'local day, not UTC day, decides: 00:30 local today with stale end_at stays open', MDG_Sessions::sales_closed_by_time( $s( '2026-10-03 21:30:00', '2027-01-01 00:00:00' ), $now ), false );
check( 'missing end_at with a future start stays open', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 13:00:00', '' ), $now ), false );
check( 'missing end_at with a past start day is closed', MDG_Sessions::sales_closed_by_time( $s( '2026-10-03 13:00:00', null ), $now ), true );
check( 'empty times never close sales', MDG_Sessions::sales_closed_by_time( $s( '', '' ), $now ), false );
check( 'zero dates never close sales', MDG_Sessions::sales_closed_by_time( $s( '0000-00-00 00:00:00', '0000-00-00 00:00:00' ), $now ), false );
check( 'unreadable times never close sales', MDG_Sessions::sales_closed_by_time( $s( 'not-a-date', 'also-bad' ), $now ), false );
check( 'impossible calendar date never closes sales', MDG_Sessions::sales_closed_by_time( $s( '2026-13-40 09:00:00', '2026-13-40 10:00:00' ), $now ), false );
check( 'impossible clock time never closes sales', MDG_Sessions::sales_closed_by_time( $s( '2026-10-03 25:00:00', '2026-10-03 26:00:00' ), $now ), false );
check( 'fractional seconds are read', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 09:00:00.000000', '2026-10-04 10:00:00.000000' ), $now ), true );
check( 'ISO T separator and missing seconds are read', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04T09:00', '2026-10-04T10:00' ), $now ), true );
check( 'object without time fields never closes sales', MDG_Sessions::sales_closed_by_time( new stdClass(), $now ), false );
check( 'array input is accepted', MDG_Sessions::sales_closed_by_time( array( 'start_at' => '2026-10-04 09:00:00', 'end_at' => '2026-10-04 10:00:00' ), $now ), true );

add_filter( 'mdg_session_sales_closed_by_time', function ( $closed, $session, $now_ts ) {
    // Example override: close at start time instead of end time.
    return $closed || ( strtotime( $session->start_at . ' UTC' ) <= $now_ts );
} );
check( 'filter can tighten the rule (close at start)', MDG_Sessions::sales_closed_by_time( $s( '2026-10-04 12:00:00', '2026-10-04 13:00:00' ), $now ), true );
$filters = array();

/* ================= 2. Server guard: add to cart ================= */
$t = time();
$past   = session_row( 1, $t - 3 * 3600, $t - 2 * 3600 );
$future = session_row( 2, $t + 2 * 3600, $t + 3 * 3600 );
MDG_Events::$event = (object) array( 'id' => 9, 'status' => 'onsale' );
$wpdb->sessions = array( $past, $future );

$add = function ( $session_id ) {
    $_POST = array( 'event_id' => '9', 'session_id' => (string) $session_id, 'nonce' => 'x', 'lines' => json_encode( array( array( 'code' => 'CHILD', 'qty' => 1 ) ) ) );
    try { MDG_Live_Sales::add_to_cart(); } catch ( JsonError $e ) { return array( $e->getMessage(), $e->getCode() ); }
    return array( 'no error', 0 );
};
$r = $add( 1 );
check( 'add to cart for an ended session is refused with 409', $r[1], 409 );
check( 'refusal names the reason', false !== strpos( $r[0], 'bilet satışı sona erdi' ), true );
$r = $add( 2 );
// No product is registered in this fixture, so an open session must reach the product check.
check( 'open session passes the time guard and reaches the product check', $r[0], 'Seans ürünü satışa uygun değil.' );

/* ================= 3. Server guard: order processed, before payment ================= */
$wpdb->types = array(
    (object) array( 'id' => 31, 'session_id' => 1, 'wc_variation_id' => 801, 'capacity_units' => 1, 'is_active' => 1 ),
    (object) array( 'id' => 32, 'session_id' => 2, 'wc_variation_id' => 802, 'capacity_units' => 1, 'is_active' => 1 ),
);
$reserve = new ReflectionMethod( 'MDG_Live_Sales', 'reserve_or_throw' );
if ( PHP_VERSION_ID < 80100 ) { $reserve->setAccessible( true ); }
$order_for = function ( $variation ) { $o = new WC_Order(); $o->variations = array( $variation ); return $o; };

MDG_Capacity::$holds = array();
$message = '';
try { $reserve->invoke( null, $order_for( 801 ) ); } catch ( Exception $e ) { $message = $e->getMessage(); }
check( 'order for an ended session is stopped before payment', false !== strpos( $message, 'bilet satışı sona erdi' ), true );
check( 'no capacity is held for an ended session', MDG_Capacity::$holds, array() );
check( 'nothing is written for an ended session', $wpdb->writes, 0 );

// The open-session path continues into the existing order-map bookkeeping, which this
// fixture does not model; only the guard outcome is asserted here.
MDG_Capacity::$holds = array();
$failed = '';
try { $reserve->invoke( null, $order_for( 802 ) ); } catch ( Throwable $e ) { $failed = $e->getMessage(); }
check( 'order for an open session still reserves capacity', MDG_Capacity::$holds, array( array( 2, 2 ) ) );
check( 'open-session order raises no time error', false === strpos( $failed, 'sona erdi' ), true );
$wpdb->writes = 0;

/* ================= 4. Event page markup ================= */
$render = new ReflectionMethod( 'MDG_Public_Event', 'render' );
if ( PHP_VERSION_ID < 80100 ) { $render->setAccessible( true ); }
$event = (object) array(
    'id' => 9, 'status' => 'onsale', 'title' => 'Madagaskar Sirki – Test', 'seo_title' => '', 'seo_description' => '',
    'short_description' => 'Kısa açıklama', 'long_description' => 'Uzun açıklama', 'hero_attachment_id' => 0,
    'province_name' => 'Ankara', 'district' => 'Yenimahalle', 'venue_name' => 'Test Salonu', 'venue_address' => 'Adres',
    'venue_maps_url' => '', 'organizer_name' => 'Dünya Organizasyon', 'public_slug' => 'test', 'public_uuid' => 'u-1',
    'video_url' => '', 'show_duration' => 60, 'doors_open_before' => 30, 'seating_type' => 'free', 'age_info' => '', 'rules' => "Kural 1\nKural 2",
);
$page = function ( $sessions, $is_preview = false ) use ( $render, $event, $wpdb ) {
    $wpdb->sessions = $sessions;
    $wpdb->types = array();
    foreach ( $sessions as $row ) {
        $wpdb->types[] = (object) array( 'id' => 40 + $row->id, 'session_id' => $row->id, 'code' => 'CHILD', 'label' => 'Çocuk', 'price' => '250.00', 'capacity_units' => 1, 'is_active' => 1, 'wc_variation_id' => 800 + $row->id, 'sort_order' => 10 );
    }
    ob_start();
    try { $render->invoke( null, $event, $is_preview ); } finally { $html = ob_get_clean(); }
    return $html;
};
$time_label = function ( $ts ) { return wp_date( 'H:i', $ts ); };
$has_session = function ( $html, $id ) { return false !== strpos( $html, 'data-session-id="' . $id . '"' ); };
$sales_config = function ( $html ) { preg_match( '/window\.MDG_EVENT_SALES=(\{.*?\});/', $html, $m ); return json_decode( $m[1], true ); };

// 4a. One ended and two open sessions on the same event.
$mixed = array( session_row( 1, $t - 3 * 3600, $t - 2 * 3600 ), session_row( 2, $t + 2 * 3600, $t + 3 * 3600 ), session_row( 3, $t + 4 * 3600, $t + 5 * 3600 ) );
$html = $page( $mixed );
check( 'ended session is not offered', $has_session( $html, 1 ), false );
check( 'first open session is offered', $has_session( $html, 2 ), true );
check( 'second open session is offered', $has_session( $html, 3 ), true );
check( 'exactly one session is pre-selected', substr_count( $html, 'mdg-session-option is-selected' ), 1 );
check( 'the pre-selected session is the first open one', 1 === preg_match( '/mdg-session-option is-selected" data-session-id="2"/', $html ), true );
check( 'page still says Satışta', false !== strpos( $html, '>Satışta<' ), true );
check( 'ticket rows are shown', false !== strpos( $html, 'data-mdg-ticket-options' ), true );
check( 'cart is enabled for the open sessions', $sales_config( $html )['enabled'], true );
check( 'sticky summary shows the first open session time', false !== strpos( $html, '· ' . $time_label( $t + 2 * 3600 ) ), true );
check( 'no ended notice while a session is open', false === strpos( $html, 'bilet satışı sona erdi' ), true );
check( 'structured data still offers tickets', false !== strpos( $html, 'AggregateOffer' ), true );

// 4b. Every session has ended (the Sincan 3 Ekim case).
$ended = array( session_row( 1, $t - 27 * 3600, $t - 26 * 3600 ), session_row( 2, $t - 25 * 3600, $t - 24 * 3600 ) );
$html = $page( $ended );
check( 'ended event shows the notice', false !== strpos( $html, 'Bu gösterinin bilet satışı sona erdi.' ), true );
check( 'ended event offers no session', false === strpos( $html, 'data-session-id=' ), true );
check( 'ended event shows no ticket rows', false === strpos( $html, 'data-mdg-ticket-options' ), true );
check( 'ended event shows no checkout button', false === strpos( $html, 'data-mdg-checkout-preview' ), true );
check( 'ended event does not say Satışta', false === strpos( $html, '>Satışta<' ), true );
check( 'ended event disables the cart', $sales_config( $html )['enabled'], false );
check( 'ended event issues no cart nonce', false === strpos( $html, 'nonce-mdg_live_cart_' ), true );
check( 'ended event links to current shows', substr_count( $html, 'https://example.test/bilet-al/' ) >= 3, true );
check( 'ended event no longer links to the ticket picker', false === strpos( $html, 'href="#bilet-secimi"' ), true );
check( 'ended event drops the in-stock offer from structured data', false === strpos( $html, 'AggregateOffer' ), true );

// 4c. All sessions open: unchanged behaviour.
$open = array( session_row( 1, $t + 2 * 3600, $t + 3 * 3600 ), session_row( 2, $t + 4 * 3600, $t + 5 * 3600 ) );
$html = $page( $open );
check( 'future event offers every session', $has_session( $html, 1 ) && $has_session( $html, 2 ), true );
check( 'future event keeps the ticket picker links', substr_count( $html, 'href="#bilet-secimi"' ), 3 );
check( 'future event has no link to /bilet-al/', false === strpos( $html, '/bilet-al/' ), true );
check( 'future event cart is enabled', $sales_config( $html )['enabled'], true );

// 4d. Admin draft preview is not filtered by time.
$html = $page( $ended, true );
check( 'draft preview still lists past-dated sessions', $has_session( $html, 1 ) && $has_session( $html, 2 ), true );
check( 'draft preview shows no ended notice', false === strpos( $html, 'bilet satışı sona erdi' ), true );

// 4e. Event without sessions: unchanged message.
$html = $page( array() );
check( 'event without sessions keeps the original message', false !== strpos( $html, 'Henüz seans tanımlanmadı.' ), true );

check( 'rendering the event page writes nothing', $wpdb->writes, 0 );
echo "\n$passed checks passed\n";
