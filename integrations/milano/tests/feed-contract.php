<?php
/**
 * Standalone contract smoke tests for the Milano canonical session feed.
 * Run: php integrations/milano/tests/feed-contract.php <scenario>
 * No WordPress or MySQL instance is used; read-only stubs only.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = isset( $argv[1] ) ? (string) $argv[1] : '';
$key = str_repeat( 'k', 40 );
if ( 'short_key' === $scenario ) {
    define( 'DUNYA_MILANO_FEED_TOKEN', 'too-short' );
} elseif ( 'no_key' !== $scenario ) {
    define( 'DUNYA_MILANO_FEED_TOKEN', $key );
}

function fail_test( $message ) {
    fwrite( STDERR, "FAIL {$message}\n" );
    exit( 1 );
}
function expect( $condition, $message ) {
    if ( ! $condition ) { fail_test( $message ); }
}
class WP_Error {
    private $code;
    private $data;
    public function __construct( $code, $message, $data = array() ) {
        $this->code = $code;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
    private $headers;
    public function __construct( $headers = array() ) { $this->headers = $headers; }
    public function get_header( $key ) { return isset( $this->headers[$key] ) ? $this->headers[$key] : ''; }
}
class WP_REST_Server { const READABLE = 'GET'; }
class MockResponse {
    private $data;
    public $headers = array();
    public function __construct( $data ) { $this->data = $data; }
    public function header( $key, $value ) { $this->headers[$key] = $value; }
    public function get_data() { return $this->data; }
}
function add_action( $name, $callback ) { /* registered on the real WP instance */ }
function apply_filters( $name, $value, $event_id ) {
    global $scenario;
    if ( 'dunya_milano_canonical_event_owner' !== $name || 16 !== $event_id ) {
        return $value;
    }
    if ( 'missing_brand' === $scenario || 'filter_reject' === $scenario ) { return null; }
    if ( 'filter_promote' === $scenario || 'filter_promote_no_bridge' === $scenario ) { return 'milano'; }
    if ( 'filter_cross_brand' === $scenario ) { return 'madagaskar'; }
    if ( 'invalid_brand' === $scenario ) { return 'unknown'; }
    return $value;
}
function register_rest_route( $namespace, $path, $definition ) {}
function rest_ensure_response( $data ) { return new MockResponse( $data ); }
function current_time( $format, $gmt = false ) { return '2026-10-12 18:30:00'; }
function wp_timezone_string() { return 'Europe/Istanbul'; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_key( $text ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $text ) ); }
function get_post_status( $id ) { return 'publish'; }
// Only a single *active* MMC bridge may resolve a canonical owner.
$programLinks = array( 16 => array( 401 ) );
$programBrands = array( 401 => 'Milano Sirki', 402 => 'Madagaskar Sirki' );
$bridgeTable = 'wp_mmc_mdg_event_bridge';
class MMC_MDG_Bridge_Service {
    public static $legacy_calls = 0;
    public static function table() { return $GLOBALS['bridgeTable']; }
    public static function program_for_mdg_event( $event_id ) {
        ++self::$legacy_calls;
        return 401; // deliberately unsafe legacy fallback: MUST NEVER execute
    }
}
class MMC_Program_Service {
    public static function get_program( $id ) {
        $brands = $GLOBALS['programBrands'];
        return isset( $brands[$id] ) ? (object) array( 'brand' => $brands[$id] ) : null;
    }
    public static function normalized_program_brand( $brand ) {
        return in_array( $brand, array( 'Milano Sirki', 'Madagaskar Sirki' ), true ) ? $brand : '';
    }
}
class MDG_DB {
    public static function table( $name ) { return 'wp_mdg_' . $name; }
}
class MDG_Status { const ONSALE = 'onsale'; }
class MDG_Sessions {
    public static $closed = false;
    public static function sales_closed_by_time( $row ) { return self::$closed; }
    public static function local_parts( $utc ) { return array( '2026-10-15', '17:30' ); }
}
class MDG_Capacity {
    public static $available = 42;
    public static function available( $id ) { return self::$available; }
}
class MockProduct {
    public $id;
    public $parent;
    public $kind;
    public $price;
    public $stock = true;
    public function __construct( $id, $kind, $parent = 0, $price = 245 ) {
        $this->id = $id; $this->kind = $kind; $this->parent = $parent; $this->price = $price;
    }
    public function is_type( $kind ) { return $this->kind === $kind; }
    public function get_id() { return $this->id; }
    public function get_parent_id() { return $this->parent; }
    public function is_purchasable() { return true; }
    public function is_in_stock() { return $this->stock; }
    public function get_price() { return $this->price; }
}
$products = array( 501 => new MockProduct( 501, 'variable' ), 602 => new MockProduct( 602, 'variation', 501 ) );
function wc_get_product( $id ) { global $products; return isset( $products[$id] ) ? $products[$id] : false; }
class MockDB {
    public $events;
    public $ticketTypes;
    public $queries = array();
    public $lastArgs = array();
    public function prepare( $sql, ...$args ) {
        // Avoid implementing SQL: query strings and parameter counts are separately asserted.
        $count = substr_count( $sql, '%s' ) + substr_count( $sql, '%d' );
        expect( $count === count( $args ), 'SQL placeholder count mismatch' );
        $this->lastArgs = $args;
        $this->queries[] = $sql;
        return $sql;
    }
    public function get_col( $sql ) {
        global $programLinks;
        expect( false !== strpos( $sql, 'SELECT program_id' ), 'non-authoritative SQL owner lookup' );
        expect( false !== strpos( $sql, 'is_active=1 LIMIT 2' ), 'bridge ownership must detect duplicates' );
        expect( false === strpos( $sql, 'program_for_mdg_event' ), 'legacy fallback SQL forbidden' );
        $event_id = (int) ( $this->lastArgs[0] ?? 0 );
        return $programLinks[$event_id] ?? array();
    }
    public function get_results( $sql ) {
        $this->queries[] = $sql;
        return false !== strpos( $sql, 'FROM wp_mdg_ticket_types' ) ? $this->ticketTypes : $this->events;
    }
}
$wpdb = new MockDB();
$wpdb->events = array( (object) array(
    'session_id' => 107, 'event_id' => 16, 'start_at' => '2026-10-15 14:30:00',
    'end_at' => '2026-10-15 15:30:00', 'capacity_total' => 100,
    'wc_product_id' => 501, 'tickera_event_id' => 901,
    'province_name' => 'Uşak', 'district' => 'Merkez',
    'venue_name' => 'Merkez Spor Salonu', 'venue_address' => 'Uşak Merkez',
) );
$wpdb->ticketTypes = array( (object) array(
    'code' => 'CHILD', 'label' => 'Çocuk', 'price' => '245.00',
    'capacity_units' => 1, 'wc_variation_id' => 602,
) );
$source = dirname( __DIR__ ) . '/dunya-milano-session-feed/dunya-milano-session-feed.php';
expect( is_file( $source ), 'feed file not found' );
require $source;
$request = new WP_REST_Request( array( 'X-Dunya-Milano-Key' => 'wrong' === $scenario ? 'incorrect' : $key ) );

if ( in_array( $scenario, array( 'no_key', 'short_key', 'wrong', 'auth_success' ), true ) ) {
    $result = dmsf_permission( $request );
    if ( 'auth_success' === $scenario ) {
        expect( true === $result, 'valid server key should be accepted' );
    } else {
        $status = 'wrong' === $scenario ? 403 : 503;
        expect( $result instanceof WP_Error, 'missing/invalid key must fail closed' );
        expect( $result->get_error_data()['status'] === $status, 'wrong error status' );
    }
    echo "PASS {$scenario}\n";
    exit( 0 );
}
expect( true === dmsf_permission( $request ), 'test key refused' );

if ( 'db_error' === $scenario ) { $wpdb->events = null; }
if ( 'empty' === $scenario ) { $wpdb->events = array(); }
if ( 'sold_out' === $scenario ) { MDG_Capacity::$available = 0; }
if ( 'closed' === $scenario ) { MDG_Sessions::$closed = true; }
if ( 'zero_capacity' === $scenario ) { $wpdb->events[0]->capacity_total = 0; }
if ( 'no_ticket_event' === $scenario ) { $wpdb->events[0]->tickera_event_id = 0; }
if ( 'missing_product' === $scenario ) { unset( $products[501] ); }
if ( 'wrong_parent' === $scenario ) { $products[602]->parent = 222; }
if ( 'price_mismatch' === $scenario ) { $products[602]->price = 999; }
if ( 'variation_out_of_stock' === $scenario ) { $products[602]->stock = false; }
if ( 'no_ticket_types' === $scenario ) { $wpdb->ticketTypes = array(); }
if ( 'other_brand' === $scenario || 'filter_promote' === $scenario ) {
    $programLinks[16] = array( 402 );
}
if ( 'invalid_brand' === $scenario ) { $programBrands[401] = 'both'; }
if ( 'no_bridge' === $scenario || 'legacy_fallback' === $scenario
    || 'filter_promote_no_bridge' === $scenario ) {
    $programLinks[16] = array();
}
if ( 'two_bridges' === $scenario ) { $programLinks[16] = array( 401, 402 ); }
if ( 'orphan_bridge' === $scenario ) { $programLinks[16] = array( 999 ); }
if ( 'bad_bridge_table' === $scenario ) { $bridgeTable = 'invalid; DROP TABLE'; }
if ( 'no_db_owner' === $scenario ) {
    $original_db = $wpdb; $wpdb = null;
    expect( null === dmsf_event_owner_brand( 16 ), 'missing db must deny owner' );
    $wpdb = $original_db;
}
if ( 'zero_event_owner' === $scenario ) {
    expect( null === dmsf_event_owner_brand( 0 ), 'invalid event ID must deny' );
}
if ( 'filter_promote_no_bridge' === $scenario ) {
    expect( null === dmsf_event_owner_brand( 16 ), 'untrusted filter must not create ownership' );
}
if ( 'legacy_fallback' === $scenario ) {
    expect( null === dmsf_event_owner_brand( 16 ), 'legacy fallback must not grant Milano ownership' );
    expect( 0 === MMC_MDG_Bridge_Service::$legacy_calls, 'first-match legacy helper was called' );
}

$result = dmsf_get_sessions( $request );
if ( 'db_error' === $scenario ) {
    expect( $result instanceof WP_Error, 'database failure must fail closed' );
    expect( 503 === $result->get_error_data()['status'], 'bad database error status' );
    echo "PASS {$scenario}\n"; exit( 0 );
}
expect( $result instanceof MockResponse, 'expected REST response' );
$data = $result->get_data();
expect( 1 === $data['schema_version'], 'schema version' );
expect( true === $data['read_only'], 'read only flag' );
expect( false !== strpos( $result->headers['Cache-Control'], 'no-store' ), 'must not cache available inventory' );
$eventSql = $wpdb->queries[0];
foreach ( array( 'e.status=%s', 's.status=%s', 's.start_at>%s', 'e.sale_start', 'e.sale_end', 's.sale_start', 's.sale_end', 'LIMIT %d' ) as $requiredSql ) {
    expect( false !== strpos( $eventSql, $requiredSql ), 'missing mandatory status/time gate ' . $requiredSql );
}
$shouldBeHidden = in_array( $scenario, array( 'empty', 'closed', 'zero_capacity', 'no_ticket_event', 'missing_product', 'wrong_parent', 'price_mismatch', 'variation_out_of_stock', 'no_ticket_types', 'missing_brand', 'other_brand', 'invalid_brand',
    'no_bridge', 'two_bridges', 'orphan_bridge', 'bad_bridge_table',
    'filter_promote', 'filter_cross_brand', 'filter_reject',
    'filter_promote_no_bridge', 'legacy_fallback' ), true );
if ( $shouldBeHidden ) {
    expect( count( $data['sessions'] ) === 0, 'invalid session was exposed' );
} else {
    expect( count( $data['sessions'] ) === 1, 'valid session missing' );
    $session = $data['sessions'][0];
    expect( $session['session_id'] === 107, 'wrong session identity' );
    expect( $session['event_owner_brand'] === 'milano', 'brand owner missing from feed' );
    expect( $session['available_units'] === ( 'sold_out' === $scenario ? 0 : 42 ), 'wrong capacity count' );
    expect( $session['availability'] === ( 'sold_out' === $scenario ? 'sold_out' : 'available' ), 'wrong availability flag' );
    expect( count( $session['ticket_types'] ) === 1, 'wrong ticket catalogue' );
    expect( $session['ticket_types'][0]['price'] === 245.0, 'price inconsistent' );
    expect( $session['time'] === '17:30', 'timezone conversion missing' );
    expect( ! isset( $session['order_id'] ) && ! isset( $session['customer'] ), 'private order data exposed' );
}
echo "PASS {$scenario}\n";
