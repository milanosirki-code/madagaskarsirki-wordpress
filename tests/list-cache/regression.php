<?php
/**
 * Isolated regression for docs/code-snippets/staged-2026-10-05/ms-liste-onbellek-v1.php.txt.
 * No WordPress or network access. Warnings and notices fail the run.
 * Each case runs in its own PHP process because DONOTCACHEPAGE is a constant.
 */
if ( 1 === $argc ) {
    $cases = array(
        'front'    => array( 'front page', true ),
        'sehirler' => array( '/sehirler/', true ),
        'bilet-al' => array( '/bilet-al/', true ),
        'ankara'   => array( '/sehirler/ankara/ (child page)', false ),
        'etkinlik' => array( 'event page', false ),
        'admin'    => array( 'admin screen', false ),
    );
    $passed = 0;
    foreach ( $cases as $key => $case ) {
        $out = array(); $code = 0;
        exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $key ), $out, $code );
        $result = json_decode( implode( '', $out ), true );
        if ( 0 !== $code || ! is_array( $result ) ) { echo "FAIL: {$case[0]}: " . implode( ' ', $out ) . "\n"; exit( 1 ); }
        $expected = array( 'constant' => $case[1], 'nocache' => $case[1] ? 1 : 0, 'meta' => $case[1] );
        $actual   = array( 'constant' => $result['constant'], 'nocache' => $result['nocache'], 'meta' => $result['meta'] );
        if ( $actual !== $expected ) { echo "FAIL: {$case[0]}: " . json_encode( $actual ) . "\n"; exit( 1 ); }
        if ( $case[1] && 1 !== preg_match( '/^<meta name="ms-sayfa-uretim" content="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+03:00">\n$/', $result['html'] ) ) { echo "FAIL: {$case[0]}: meta format " . $result['html'] . "\n"; exit( 1 ); }
        if ( array( 'template_redirect|1', 'wp_head|1' ) !== $result['hooks'] ) { echo "FAIL: {$case[0]}: hooks\n"; exit( 1 ); }
        echo 'PASS: ' . $case[0] . ( $case[1] ? ' is not cached and carries a generation time' : ' is left alone' ) . "\n";
        $passed++;
    }
    echo "\n$passed checks passed\n";
    exit( 0 );
}

define( 'ABSPATH', __DIR__ );
set_error_handler( function ( $no, $str, $file, $line ) {
    if ( in_array( $no, array( E_DEPRECATED, E_USER_DEPRECATED ), true ) ) { return true; }
    throw new ErrorException( $str, 0, $no, $file, $line );
} );
$case = $argv[1];
$hooks = array(); $nocache = 0;
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { global $hooks; $hooks[] = array( $hook, $cb, $priority ); }
function is_admin() { global $case; return 'admin' === $case; }
function is_front_page() { global $case; return 'front' === $case; }
function is_page( $slugs ) { global $case; return in_array( $case, (array) $slugs, true ); }
function nocache_headers() { global $nocache; $nocache++; }
function wp_date( $format ) { $d = new DateTimeImmutable( 'now', new DateTimeZone( 'Europe/Istanbul' ) ); return $d->format( $format ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }

$body = file_get_contents( dirname( __DIR__, 2 ) . '/docs/code-snippets/staged-2026-10-05/ms-liste-onbellek-v1.php.txt' );
$tmp  = tempnam( sys_get_temp_dir(), 'mslo' ) . '.php';
file_put_contents( $tmp, "<?php\n" . $body );
ob_start();
try { require $tmp; } finally { unlink( $tmp ); }
foreach ( $hooks as $h ) { call_user_func( $h[1] ); }
$html = ob_get_clean();
echo json_encode( array(
    'constant' => defined( 'DONOTCACHEPAGE' ),
    'nocache'  => $nocache,
    'meta'     => false !== strpos( $html, 'ms-sayfa-uretim' ),
    'html'     => $html,
    'hooks'    => array_map( function ( $h ) { return $h[0] . '|' . $h[2]; }, $hooks ),
) );
