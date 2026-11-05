<?php
require __DIR__ . '/../../wp-content/plugins/madagaskar-web-assistant-core/includes/class-preview.php';

use Madagaskar\WebAssistant\Catalogue;
use Madagaskar\WebAssistant\Preview;

class WP_REST_Response {
    public $data; public $status; public $headers = array();
    public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
    public function header( $key, $value ) { $this->headers[$key] = $value; }
}
class FixtureRequest {
    private $data; private $raw;
    public function __construct( $data, $raw = null ) { $this->data = $data; $this->raw = $raw ?? json_encode( $data ); }
    public function get_json_params() { return $this->data; }
    public function get_body() { return $this->raw; }
}
$GLOBALS['fixture_routes'] = array(); $GLOBALS['fixture_assets'] = array();
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['fixture_routes'][] = array( $namespace, $route, $args ); }
function plugins_url( $path, $file ) { return 'https://fixture.invalid/' . $path; }
function wp_enqueue_style( ...$args ) { $GLOBALS['fixture_assets'][] = $args; }
function wp_enqueue_script( ...$args ) { $GLOBALS['fixture_assets'][] = $args; }

check( ! Preview::permission(), 'preview disabled on production, even administrator' );
Preview::routes(); check( ! $GLOBALS['fixture_routes'], 'production registers no preview endpoints' );
$b = bundle(); $b['ticket_url'] = Catalogue::STAGING_ORIGIN . '/etkinlik/fixture-2026/';
fails( static function() use ( $b, $now ) { Catalogue::offers( $b, $now ); }, 'UNVERIFIED_URL' );
check( count( Catalogue::offers( $b, $now, Catalogue::STAGING_ORIGIN )['offers'] ) === 1, 'explicit staging origin supported' );
fails( static function() use ( $b, $now ) { Catalogue::offers( $b, $now, 'https://unknown.wpcomstaging.com' ); }, 'UNVERIFIED_ORIGIN' );
$GLOBALS['fixture_home'] = Catalogue::STAGING_ORIGIN;
check( Preview::permission(), 'verified stage administrator permitted' );
Preview::routes(); check( count( $GLOBALS['fixture_routes'] ) === 2, 'two narrow preview endpoints' );
foreach ( $GLOBALS['fixture_routes'] as $route ) { check( $route[2]['permission_callback'] === array( Preview::class, 'permission' ), 'every endpoint uses stage and administrator gate' ); }
Preview::assets( 'dashboard' ); check( ! $GLOBALS['fixture_assets'], 'no unrelated assets' );
Preview::assets( 'tools_page_mdg-web-assistant-preview' ); check( count( $GLOBALS['fixture_assets'] ) === 2, 'preview-only assets' );
$catalogue = Preview::catalogue();
check( $catalogue->status === 200 && count( $catalogue->data['offers'] ) === 1, 'real service exposed through restricted catalogue route' );
check( strpos( $catalogue->headers['Cache-Control'], 'no-store' ) !== false, 'catalogue not cached' );
$offer = $catalogue->data['offers'][0];
$request = array( 'session_id' => 223, 'party' => array( 'adults' => 2, 'children' => 2 ), 'source_revision' => $offer['source_revision'] );
$q = Preview::quote( new FixtureRequest( $request ) );
check( $q->status === 200 && $q->data['recommended']['total_minor'] === 110000, 'route to actual quote engine' );
check( $q->data['ticket_url'] === Catalogue::STAGING_ORIGIN . '/etkinlik/fixture-2026/', 'staging cannot return production checkout link' );
$bad = $request; unset( $bad['source_revision'] );
check( Preview::quote( new FixtureRequest( $bad ) )->status === 400, 'revision mandatory in UI endpoint' );
$bad = $request; $bad['price'] = 1;
check( Preview::quote( new FixtureRequest( $bad ) )->status === 400, 'client cannot submit prices' );
check( Preview::quote( new FixtureRequest( $request, str_repeat( 'x', 2049 ) ) )->status === 413, 'request size bounded' );
$bad = $request; $bad['source_revision'] = str_repeat( '0', 64 );
check( Preview::quote( new FixtureRequest( $bad ) )->data['code'] === 'STALE_SELECTION', 'stale UI selection rejected' );
$GLOBALS['fixture_can_manage'] = false;
check( Preview::catalogue()->status === 403 && Preview::quote( new FixtureRequest( $request ) )->status === 403, 'callbacks independently reject unauthorized users' );
$GLOBALS['fixture_can_manage'] = true; $GLOBALS['fixture_home'] = Catalogue::PRODUCTION_ORIGIN;
check( Preview::quote( new FixtureRequest( $request ) )->status === 403, 'production preview quote refused' );
check( $GLOBALS['wpdb']->writes === 0 && $GLOBALS['fixture_cart_calls'] === 0, 'preview also performs no cart or domain writes' );
