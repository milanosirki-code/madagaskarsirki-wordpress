<?php
/**
 * Isolated contractual checks for central Milano /upcoming announcements.
 * No WordPress connection, DB mutation, stock or checkout.
 * Run: php integrations/milano/tests/upcoming-feed-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = isset( $argv[1] ) ? (string) $argv[1] : 'valid';
function check( $ok, $msg ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL {$msg}\n" ); exit( 1 ); }
}
if ( 'disabled' !== $scenario ) {
    define( 'DUNYA_MILANO_UPCOMING_FEED_ENABLED', true );
}
if ( 'missing_ids' !== $scenario ) {
    if ( 'invalid_ids' === $scenario ) {
        define( 'DUNYA_MILANO_ANNOUNCED_EVENT_IDS', array( '16' ) );
    } elseif ( 'duplicate_ids' === $scenario ) {
        define( 'DUNYA_MILANO_ANNOUNCED_EVENT_IDS', array( 16, 16 ) );
    } else {
        define( 'DUNYA_MILANO_ANNOUNCED_EVENT_IDS', array( 16 ) );
    }
}
if ( 'missing_key' !== $scenario ) {
    define( 'DUNYA_MILANO_FEED_TOKEN', str_repeat( 't', 40 ) );
}
class WP_Error {
    private $code;
    private $data;
    public function __construct( $code, $message, $data = array() ) {
        $this->code = $code; $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
    private $key;
    public function __construct( $key ) { $this->key = $key; }
    public function get_header( $name ) { return 'X-Dunya-Milano-Key' === $name ? $this->key : ''; }
}
class WP_REST_Server { const READABLE = 'GET'; }
class StubResponse {
    private $data;
    public $headers = array();
    public function __construct( $data ) { $this->data = $data; }
    public function get_data() { return $this->data; }
    public function header( $key, $value ) { $this->headers[$key] = $value; }
}
function add_action( $name, $fn ) {}
function register_rest_route( $group, $path, $props ) {}
function rest_ensure_response( $data ) { return new StubResponse( $data ); }
function current_time( $format, $gmt = false ) { return '2026-10-13 06:00:00'; }
function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
function dmsf_permission( $request ) {
    if ( ! defined( 'DUNYA_MILANO_FEED_TOKEN' )
        || strlen( DUNYA_MILANO_FEED_TOKEN ) < 32 ) {
        return new WP_Error( 'dmsf_not_configured', 'Unavailable', array( 'status' => 503 ) );
    }
    return hash_equals( DUNYA_MILANO_FEED_TOKEN, $request->get_header( 'X-Dunya-Milano-Key' ) )
        ? true : new WP_Error( 'dmsf_forbidden', 'Forbidden', array( 'status' => 403 ) );
}
$ownersChecked = array();
function dmsf_event_owner_brand( $event_id ) {
    global $scenario, $ownersChecked;
    $ownersChecked[] = $event_id;
    if ( 'wrong_brand' === $scenario ) { return 'madagaskar'; }
    if ( 'unknown_brand' === $scenario ) { return null; }
    return 16 === $event_id ? 'milano' : null;
}
class MDG_Status {
    const DRAFT = 'draft';
    const ONSALE = 'onsale';
}
class MDG_DB {
    public static function table( $name ) {
        global $scenario;
        return ( 'bad_table' === $scenario && 'events' === $name ? 'bad;table' : 'wp_mdg_' . $name );
    }
}
class MDG_Sessions {
    public static function local_parts( $start ) {
        global $scenario;
        if ( 'invalid_local' === $scenario ) { return array( 'bad', '27:42' ); }
        return array( '2030-10-15', '17:30' );
    }
}
class MockDB {
    public $query = '';
    public $args = array();
    public function prepare( $sql, ...$args ) {
        $cnt = substr_count( $sql, '%d' ) + substr_count( $sql, '%s' );
        check( count( $args ) === $cnt, 'SQL placeholder count mismatch' );
        $this->query = $sql;
        $this->args = $args;
        return $sql;
    }
    public function get_results( $query ) {
        global $scenario;
        check( false !== strpos( $query, 'e.id IN (' ), 'no explicit approved IDs in SQL' );
        check( false !== strpos( $query, 'e.status IN (%s,%s)' ), 'query accepts canceled events' );
        check( false !== strpos( $query, 's.status IN (%s,%s)' ), 'query accepts canceled sessions' );
        check( false !== strpos( $query, 's.start_at > %s' ), 'query accepts past sessions' );
        check( false !== strpos( $query, 'LIMIT 120' ), 'unbounded feed' );
        check( $this->args === array( 16, MDG_Status::DRAFT, MDG_Status::ONSALE,
            MDG_Status::DRAFT, MDG_Status::ONSALE, '2026-10-13 06:00:00' ), 'unexpected SQL bind values' );
        if ( 'query_error' === $scenario ) { return null; }
        if ( 'empty' === $scenario ) { return array(); }
        $event = 'unapproved_event' === $scenario ? 17 : 16;
        return array( (object) array(
            'session_id' => 101, 'event_id' => $event,
            'start_at' => '2030-10-15 14:30:00',
            'province_name' => 'html_input' === $scenario ? '<script>x</script>Ankara' : 'Ankara',
            'district' => 'Çankaya',
            'venue_name' => 'empty_venue' === $scenario ? '' : 'Milano Tiyatro Sahnesi',
            'venue_address' => 'Ankara'
        ) );
    }
}
$wpdb = new MockDB();
require dirname( __DIR__ ) . '/dunya-milano-session-feed/dunya-milano-upcoming.php';
$key = 'wrong_key' === $scenario ? 'wrong' : str_repeat( 't', 40 );
$request = new WP_REST_Request( $key );
$permission = dmsf_upcoming_permission( $request );
$blocked = array( 'disabled', 'missing_ids', 'invalid_ids', 'duplicate_ids', 'wrong_key', 'missing_key' );
if ( in_array( $scenario, $blocked, true ) ) {
    check( $permission instanceof WP_Error, 'missing approval or key must refuse connection' );
    check( '' === $wpdb->query, 'blocked request made database query' );
    if ( 'wrong_key' === $scenario ) {
        check( 'dmsf_forbidden' === $permission->get_error_code(), 'wrong key not 403' );
    }
    echo "PASS {$scenario}\n";
    exit( 0 );
}
check( true === $permission, 'valid approval/key must permit request' );
$result = dmsf_get_upcoming( $request );
if ( 'bad_table' === $scenario || 'query_error' === $scenario ) {
    check( $result instanceof WP_Error, 'invalid database state not fail-closed' );
    echo "PASS {$scenario}\n";
    exit( 0 );
}
check( $result instanceof StubResponse, 'expected successful read-only response' );
$data = $result->get_data();
check( 1 === $data['schema_version'] && true === $data['read_only'], 'schema or immutable flag missing' );
check( 'announcement_only' === $data['catalog_mode'], 'commercial feed confused with announcement feed' );
check( ! isset( $data['sales_enabled'], $data['available_units'] ), 'commercial state leaked' );
check( 'private, no-store, max-age=0' === $result->headers['Cache-Control'], 'announcement cache policy incorrect' );
check( 'noindex, nofollow' === $result->headers['X-Robots-Tag'], 'feed indexed' );
$no_rows = array( 'wrong_brand', 'unknown_brand', 'unapproved_event', 'invalid_local', 'empty_venue', 'empty' );
if ( in_array( $scenario, $no_rows, true ) ) {
    check( count( $data['sessions'] ) === 0, 'wrong/unapproved/invalid event publicly announced' );
} else {
    check( count( $data['sessions'] ) === 1, 'valid announcement missing' );
    $x = $data['sessions'][0];
    check( 16 === $x['event_id'] && 'milano' === $x['event_owner_brand'], 'untrusted event owner' );
    check( ! isset( $x['available_units'], $x['price'], $x['ticket_types'], $x['wc_product_id'] ), 'commercial data leak' );
    if ( 'html_input' === $scenario ) {
        check( false === strpos( $x['province'], '<script' ), 'source text not sanitized' );
    }
}
echo "PASS {$scenario}\n";
