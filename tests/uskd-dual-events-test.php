<?php
// Isolated WordPress stubs: exercises the real production parsers and renderer.
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error { public function __construct( ...$args ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_shortcode( ...$args ) {}
function apply_filters( $key, $value ) { return $value; }
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['stored'][ $key ] = $value; }
function home_url( $path ) { return 'https://uskdernegi.org' . $path; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function shortcode_atts( $defaults, $atts, $tag ) { return array_merge( $defaults, $atts ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_safe_remote_get( $url, $args ) {
	$key = strpos( $url, 'milano-handoff' ) !== false ? 'milano' : 'madagaskar';
	return $GLOBALS['responses'][ $key ];
}
require __DIR__ . '/../integrations/uskd/uskd-madagaskar-events/uskd-madagaskar-events.php';
function check( $condition, $label ) {
	if ( ! $condition ) { throw new RuntimeException( $label ); }
	echo "PASS: $label\n";
}
function row( $city, $date, $time, $extra = array() ) {
	return array_merge( array( 'event_id' => 31, 'title' => 'Milano Sirki — ' . $city, 'venue' => 'Salon', 'date' => $date, 'time' => $time, 'preview' => false, 'children_price' => 999, 'capacity_snapshot' => 500 ), $extra );
}
$payload = array( 'brand' => 'milano', 'sessions' => array(
	row( 'Menemen', '2026-10-31', '14:00' ), row( 'Menemen', '2026-10-31', '12:00' ),
	row( 'Menemen', '2026-10-31', '14:00' ), row( 'Yalova', '2026-10-22', '18:00' ),
	row( 'Hidden', '2026-10-31', '12:00', array( 'preview' => true ) ),
	row( 'Invalid', '2026-02-31', '12:00' ),
	row( 'Invalid', '2026-10-31', '24:01' ),
	row( 'Wrong brand', '2026-10-31', '12:00', array( 'title' => 'Madagaskar Sirki — İzmir' ) ),
) );
$milano = uskd_events_milano_parse( $payload );
check( count( $milano ) === 2, 'Milano grouping, duplicate sessions, invalid dates/time/brand/preview excluded' );
check( count( $milano[0]['sessions'] ) === 2 && ! isset( $milano[0]['children_price'], $milano[0]['capacity_snapshot'] ), 'Only calendar fields retained' );
check( is_wp_error( uskd_events_milano_parse( array( 'brand' => 'other', 'sessions' => array() ) ) ), 'Wrong source rejected' );
check( uskd_events_milano_parse( array( 'brand' => 'milano', 'sessions' => array() ) ) === array(), 'Valid empty source supported' );
check( uskd_events_date_iso( '8 Kasım 2026' ) === '2026-11-08' && uskd_events_date_iso( '31 Şubat 2026' ) === '', 'Turkish date parsing is strict' );
$mdg = array(
	array( 'city' => 'İzmir', 'date' => '8 Kasım 2026', 'venue' => 'Halkapınar', 'sessions' => array( '16:00', '12:00' ) ),
	array( 'city' => 'Manisa', 'date' => '18 Ekim 2026', 'venue' => 'Salon', 'sessions' => array( '12:00' ) ),
	array( 'city' => 'Bugün', 'date' => '21 Ekim 2026', 'venue' => 'Salon', 'sessions' => array( '11:00', '12:00', '18:00' ) ),
);
$sources = array( 'Madagaskar Sirki' => $mdg, 'Milano Sirki' => $milano );
$now = new DateTimeImmutable( '2026-10-21 12:00:00', uskd_events_timezone() );
$merged = uskd_events_merge( $sources, $now );
check( array_column( $merged, 'city' ) === array( 'Bugün', 'Yalova', 'Menemen', 'İzmir' ), 'Both brands ordered by date and past events excluded' );
check( $merged[0]['sessions'] === array( '18:00' ) && $merged[2]['sessions'] === array( '12:00', '14:00' ), 'Elapsed same-day sessions removed and times sorted' );
$utc = new DateTimeImmutable( '2026-10-20 22:00:00', new DateTimeZone( 'UTC' ) );
check( uskd_events_merge( array( 'Milano Sirki' => array( array( 'city'=>'Past', 'date'=>'2026-10-20', 'venue'=>'Salon', 'sessions'=>array('23:30') ) ) ), $utc ) === array(), 'Istanbul midnight boundary respected' );
check( count( uskd_events_merge( array( 'Madagaskar Sirki' => new WP_Error(), 'Milano Sirki' => $milano ), $now ) ) === 2, 'Madagaskar outage leaves Milano available' );
check( count( uskd_events_merge( array( 'Madagaskar Sirki' => $mdg, 'Milano Sirki' => new WP_Error() ), $now ) ) === 2, 'Milano outage leaves Madagaskar available' );
$html = '<div><article class="msc-live-card"><h3>İzmir</h3><p class="msc-live-date">8 Kasım 2099</p><p class="msc-live-venue">Halkapınar</p><div class="msc-session-row"><span>12:00</span><span>16:00</span></div></article></div>';
check( uskd_mdg_events_parse( $html )[0]['venue'] === 'Halkapınar', 'Existing Madagaskar HTML parser preserved' );
$payload['sessions'] = array( row( '<script>alert(1)</script>Yalova', '2099-10-22', '18:00' ) );
$GLOBALS['responses'] = array(
	'madagaskar' => array( 'status' => 200, 'body' => json_encode( array( 'content' => array( 'rendered' => $html ) ) ) ),
	'milano' => array( 'status' => 200, 'body' => json_encode( $payload ) ),
);
$rendered = uskd_mdg_events_shortcode();
check( substr_count( $rendered, '<article ' ) === 2 && strpos( $rendered, 'Milano Sirki' ) !== false && strpos( $rendered, 'Madagaskar Sirki' ) !== false, 'Both brands render' );
check( strpos( $rendered, '<script>' ) === false && strpos( $rendered, '999' ) === false && strpos( $rendered, 'checkout' ) === false, 'No source script, price or checkout exposed' );
check( substr_count( uskd_mdg_events_shortcode( array( 'limit' => 1 ) ), '<article ' ) === 1, 'Homepage limit applied after merge' );
check( ! isset( $GLOBALS['stored']['uskd_milano_calendar_v030'][0]['children_price'] ), 'Milano cache excludes commercial fields' );
$GLOBALS['responses']['milano'] = new WP_Error();
check( substr_count( uskd_mdg_events_shortcode(), '<article ' ) === 1, 'Renderer survives partial outage' );
$GLOBALS['responses']['madagaskar'] = new WP_Error();
check( strpos( uskd_mdg_events_shortcode(), 'Milano Sirki</a>' ) !== false, 'Complete outage offers both source links' );
echo "All dual calendar tests passed.\n";
