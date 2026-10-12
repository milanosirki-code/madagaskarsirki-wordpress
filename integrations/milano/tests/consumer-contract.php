<?php
/**
 * Isolated v0.2 Milano consumer contract tests.
 * Run: php integrations/milano/tests/consumer-contract.php <scenario>
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = isset( $argv[1] ) ? $argv[1] : 'valid';
if ( 'no_key' !== $scenario ) {
    define( 'DUNYA_MILANO_FEED_TOKEN', str_repeat( 'm', 40 ) );
}
function expect( $ok, $label ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); }
}
class WP_Error {
    private $code;
    public function __construct( $code, $text ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function add_shortcode( $name, $function ) {}
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
$admin = 'public' !== $scenario;
function current_user_can( $cap ) { global $admin; return $admin; }
$httpCalls = 0;
$capturedUrl = '';
$capturedArgs = array();
$mockCode = 200;
$mockBody = '';
$mockError = false;
function wp_safe_remote_get( $url, $args ) {
    global $httpCalls, $capturedUrl, $capturedArgs, $mockCode, $mockBody, $mockError;
    $httpCalls++;
    $capturedUrl = $url;
    $capturedArgs = $args;
    return $mockError ? new WP_Error( 'mock_connection_failure', 'Down' ) : array( 'status' => $mockCode, 'body' => $mockBody );
}
function wp_remote_retrieve_response_code( $v ) { return $v['status']; }
function wp_remote_retrieve_body( $v ) { return $v['body']; }
$fixture = array(
    'schema_version' => 1,
    'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
    'read_only' => true,
    'timezone' => 'Europe/Istanbul',
    'sessions' => array( array(
        'event_id' => 12,
        'event_owner_brand' => 'milano',
        'session_id' => 102,
        'province' => 'Aydın',
        'district' => 'Efeler',
        'venue' => 'Mimar Sinan Kapalı Spor Salonu',
        'date' => gmdate( 'Y-m-d', time() + 86400 ),
        'time' => '14:00',
        'start_utc' => gmdate( 'Y-m-d H:i:s', time() + 86400 ),
        'available_units' => 40,
        'availability' => 'available',
    ) ),
);
switch ( $scenario ) {
    case 'http_error': $mockCode = 403; break;
    case 'network_error': $mockError = true; break;
    case 'malformed_json': $mockBody = '{invalid JSON'; break;
    case 'wrong_schema': $fixture['schema_version'] = 2; break;
    case 'missing_readonly': unset( $fixture['read_only'] ); break;
    case 'stale': $fixture['generated_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() - 400 ); break;
    case 'over_limit':
        $fixture['sessions'] = array_fill( 0, 121, $fixture['sessions'][0] ); break;
    case 'missing_brand': unset( $fixture['sessions'][0]['event_owner_brand'] ); break;
    case 'wrong_brand': $fixture['sessions'][0]['event_owner_brand'] = 'madagaskar'; break;
    case 'unknown_brand': $fixture['sessions'][0]['event_owner_brand'] = 'other'; break;
    case 'bad_capacity': $fixture['sessions'][0]['available_units'] = -1; break;
    case 'mismatched_soldout': $fixture['sessions'][0]['availability'] = 'sold_out'; break;
    case 'past': $fixture['sessions'][0]['start_utc'] = gmdate( 'Y-m-d H:i:s', time() - 400 ); break;
    case 'sold_out':
        $fixture['sessions'][0]['available_units'] = 0;
        $fixture['sessions'][0]['availability'] = 'sold_out';
        break;
    case 'html_input': $fixture['sessions'][0]['province'] = '<script>bad()</script>Aydın'; break;
}
if ( '' === $mockBody ) { $mockBody = json_encode( $fixture ); }

require dirname( __DIR__ ) . '/milano-shared-events/milano-shared-events.php';
if ( 'public' === $scenario ) {
    $out = mso_events_shortcode();
    expect( false !== strpos( $out, 'hazirlaniyor' ), 'public visitor must get disabled message' );
    expect( $httpCalls === 0, 'public visitor must never call the feed' );
    echo "PASS public\n"; exit( 0 );
}
if ( 'no_key' === $scenario ) {
    $out = mso_events_source();
    expect( is_wp_error( $out ) && 'mso_token_missing' === $out->get_error_code(), 'missing key must fail closed' );
    expect( $httpCalls === 0, 'no token must not trigger upstream request' );
    echo "PASS no_key\n"; exit( 0 );
}
$out = mso_events_source();
$errors = array(
    'http_error', 'network_error', 'malformed_json', 'wrong_schema',
    'missing_readonly', 'stale', 'over_limit', 'bad_capacity', 'mismatched_soldout', 'missing_brand', 'unknown_brand'
);
if ( in_array( $scenario, $errors, true ) ) {
    expect( is_wp_error( $out ), 'invalid feed must fail closed' );
} elseif ( 'wrong_brand' === $scenario ) {
    expect( is_array( $out ) && count( $out ) === 0, 'Madagaskar event leaked to Milano' );
} elseif ( 'past' === $scenario ) {
    expect( is_array( $out ) && count( $out ) === 0, 'past sessions must be filtered' );
} else {
    expect( is_array( $out ) && count( $out ) === 1, 'valid session not accepted' );
    expect( $out[0]['available_units'] === ( 'sold_out' === $scenario ? 0 : 40 ), 'wrong units displayed' );
    if ( 'html_input' === $scenario ) {
        expect( false === strpos( $out[0]['province'], '<script>' ), 'unsafe markup reached display' );
    }
    $html = mso_events_shortcode();
    expect( false !== strpos( $html, 'Yonetici test gorunumu' ), 'admin diagnostic missing' );
    expect( false === strpos( $html, 'href=' ), 'must not expose checkout links' );
}
expect( $capturedUrl === 'https://madagaskarsirki.com/wp-json/dunya-milano/v1/sessions', 'not canonical source' );
expect( 0 === $capturedArgs['redirection'], 'upstream redirects must not be allowed' );
expect( true === $capturedArgs['sslverify'], 'TLS verification disabled' );
expect( $capturedArgs['headers']['X-Dunya-Milano-Key'] === DUNYA_MILANO_FEED_TOKEN, 'secret header missing' );
expect( false === strpos( $mockBody, DUNYA_MILANO_FEED_TOKEN ), 'secret was embedded in response' );
echo "PASS {$scenario}\n";
