<?php
/**
 * Fully isolated Milano upcoming announcement feed contract, no WP/database.
 * Run: php integrations/milano/tests/upcoming-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = $argv[1] ?? 'approved_draft';
if ( ! in_array( $scenario, array( 'no_key' ), true ) ) {
    define( 'DUNYA_MILANO_FEED_TOKEN', 'short_key' === $scenario ? 'tiny' : str_repeat( 'z', 40 ) );
}
if ( 'disabled' !== $scenario ) {
    define( 'DUNYA_MILANO_UPCOMING_FEED_ENABLED', true );
}
if ( 'no_allowlist' !== $scenario ) {
    if ( 'empty_allowlist' === $scenario ) { $ids = array(); }
    elseif ( 'duplicate_allowlist' === $scenario ) { $ids = array( 16, 16 ); }
    elseif ( 'string_allowlist' === $scenario ) { $ids = array( '16' ); }
    elseif ( 'oversize_allowlist' === $scenario ) { $ids = range( 1, 51 ); }
    else { $ids = array( 16 ); }
    define( 'DUNYA_MILANO_ANNOUNCED_EVENT_IDS', $ids );
}
function check( $ok, $why ) { if ( ! $ok ) { fwrite( STDERR, "FAIL {$why}\n" ); exit( 1 ); } }
class WP_Error {
    private $code; private $data;
    public function __construct( $code, $message, $data = array() ) { $this->code = $code; $this->data = $data; }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {
    private $headers;
    public function __construct( $headers ) { $this->headers = $headers; }
    public function get_header( $k ) { return $this->headers[$k] ?? ''; }
}
class Resp {
    public $headers = array(); private $value;
    public function __construct( $value ) { $this->value = $value; }
    public function header( $key, $value ) { $this->headers[$key] = $value; }
    public function get_data() { return $this->value; }
}
$routes = array();
function add_action( $hook, $cb ) { if ( $hook === 'rest_api_init' ) { $cb(); } }
function register_rest_route( $ns, $path, $args ) { $GLOBALS['routes'][$path] = $args; }
function rest_ensure_response( $data ) { return new Resp( $data ); }
function current_time( $fmt, $gmt = false ) { return '2026-10-13 04:00:00'; }
function wp_timezone_string() { return 'Europe/Istanbul'; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function apply_filters( $name, $v, $id ) { return $v; }
$programLinks = array( 16 => array( 40 ) );
$programs = array( 40 => 'Milano Sirki', 41 => 'Madagaskar Sirki' );
class MMC_MDG_Bridge_Service {
    public static function table() { return 'wp_mmc_mdg_event_bridge'; }
}
class MMC_Program_Service {
    public static function get_program( $id ) {
        return isset( $GLOBALS['programs'][$id] )
            ? (object) array( 'brand' => $GLOBALS['programs'][$id] ) : null;
    }
    public static function normalized_program_brand( $brand ) {
        return in_array( $brand, array( 'Milano Sirki', 'Madagaskar Sirki' ), true ) ? $brand : '';
    }
}
class MDG_DB {
    public static function table( $table ) { return 'wp_mdg_' . $table; }
}
class MDG_Status { const DRAFT = 'draft'; const ONSALE = 'onsale'; }
class MDG_Sessions {
    public static $local = array( '2027-01-10', '14:00' );
    public static function local_parts( $datetime ) { return self::$local; }
}
class FakeDb {
    public $rows, $sql = '', $queries = array(), $params = array();
    public function prepare( $sql, ...$args ) {
        check( substr_count( $sql, '%d' ) + substr_count( $sql, '%s' ) === count( $args ), 'SQL placeholder count' );
        $this->sql = $sql; $this->queries[] = $sql; $this->params = $args;
        return $sql;
    }
    public function get_results( $sql ) { return $this->rows; }
    public function get_col( $sql ) {
        check( false !== strpos( $sql, 'is_active=1 LIMIT 2' ), 'owner must use exclusive bridge' );
        $event_id = (int) ( $this->params[0] ?? 0 );
        return $GLOBALS['programLinks'][$event_id] ?? array();
    }
}
$wpdb = new FakeDb();
$wpdb->rows = array( (object) array(
    'session_id' => 2001, 'event_id' => 16,
    'start_at' => '2027-01-10 11:00:00', 'province_name' => 'Ankara',
    'district' => 'Çankaya', 'venue_name' => 'Örnek Onaylı Salon',
    'venue_address' => 'Merkez / Ankara',
) );
require dirname( __DIR__ ) . '/dunya-milano-session-feed/dunya-milano-session-feed.php';
check( isset( $routes['/sessions'], $routes['/upcoming'] ), 'both endpoints must register' );
check( $routes['/upcoming']['permission_callback'] === 'dmsf_upcoming_permission', 'route missing secure permission callback' );
$request = new WP_REST_Request( array(
    'X-Dunya-Milano-Key' => 'wrong_key' === $scenario ? 'incorrect' : str_repeat( 'z', 40 ),
) );
$auth = dmsf_upcoming_permission( $request );
if ( in_array( $scenario, array(
    'disabled', 'no_key', 'short_key', 'wrong_key', 'no_allowlist',
    'empty_allowlist', 'duplicate_allowlist', 'string_allowlist', 'oversize_allowlist',
), true ) ) {
    check( $auth instanceof WP_Error, 'unapproved feed should reject' );
    check( in_array( $auth->get_error_data()['status'], array( 403, 503 ), true ), 'unapproved wrong status' );
    echo "PASS {$scenario}\n"; exit( 0 );
}
check( true === $auth, 'approved feed must authenticate' );

if ( 'other_brand' === $scenario ) { $programLinks[16] = array( 41 ); }
if ( 'missing_bridge' === $scenario ) { $programLinks[16] = array(); }
if ( 'two_bridges' === $scenario ) { $programLinks[16] = array( 40, 41 ); }
if ( 'orphan_bridge' === $scenario ) { $programLinks[16] = array( 999 ); }
if ( 'unapproved_event' === $scenario ) { $wpdb->rows[0]->event_id = 17; }
if ( 'missing_venue' === $scenario ) { $wpdb->rows[0]->venue_name = ''; }
if ( 'missing_province' === $scenario ) { $wpdb->rows[0]->province_name = ''; }
if ( 'invalid_local' === $scenario ) { MDG_Sessions::$local = array( 'bad-date', '14:00' ); }
if ( 'expired_row' === $scenario ) { $wpdb->rows[0]->start_at = '2020-01-01 11:00:00'; }
if ( 'empty' === $scenario ) { $wpdb->rows = array(); }
if ( 'db_error' === $scenario ) { $wpdb->rows = null; }

$resp = dmsf_get_upcoming( $request );
if ( 'db_error' === $scenario ) {
    check( $resp instanceof WP_Error, 'db error must fail closed' );
    echo "PASS {$scenario}\n"; exit( 0 );
}
check( $resp instanceof Resp, 'expected safe response' );
$data = $resp->get_data();
check( $data['catalog_mode'] === 'announcement_only' && $data['read_only'] === true, 'bad publication contract' );
check( $resp->headers['Cache-Control'] === 'private, no-store, max-age=0', 'cache must be private and non-stored' );
check( $resp->headers['X-Robots-Tag'] === 'noindex, nofollow', 'server-to-server endpoint must not index' );
$one = 'approved_draft' === $scenario;
check( count( $data['sessions'] ) === ( $one ? 1 : 0 ), 'unapproved/cross-brand event exposed' );
$announcement_query = '';
foreach ( (array) $wpdb->queries as $issued_sql ) {
    if ( false !== strpos( $issued_sql, 'e.id IN (%d)' ) ) {
        $announcement_query = $issued_sql;
        break;
    }
}
check( '' !== $announcement_query, 'DB SQL not bounded to explicit event allowlist' );
check( false !== strpos( $announcement_query, 'e.status IN (%s,%s)' ), 'draft events must not be published without status constraints' );
check( false !== strpos( $announcement_query, 's.status IN (%s,%s)' ), 'draft sessions must not be published without status constraints' );
check( false !== strpos( $announcement_query, 's.start_at > %s' ), 'time filter missing' );
if ( $one ) {
    check( $data['sessions'][0]['event_owner_brand'] === 'milano', 'wrong brand emitted' );
    check( $data['sessions'][0]['venue'] === 'Örnek Onaylı Salon', 'wrong approved venue' );
}
$json = json_encode( $data );
foreach ( array( 'price', 'ticket_types', 'available_units', 'availability', 'wc_product_id', 'merchant_oid', 'paytr' ) as $forbidden ) {
    check( false === strpos( $json, '"' . $forbidden . '"' ), 'commercial information leaked: ' . $forbidden );
}
echo "PASS {$scenario}\n";
