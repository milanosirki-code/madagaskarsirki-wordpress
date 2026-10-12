<?php
/**
 * Isolated regression checks for central event owner and direct WooCommerce routes.
 * php integrations/milano/tests/brand-enforcement-contract.php <scenario>
 * NEVER touches live WordPress or payment data.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = $argv[1] ?? 'milano_denied';
function demand( $v, $why ) { if ( ! $v ) { fwrite( STDERR, "FAIL: {$why}\n" ); exit( 1 ); } }
$hookList = array();
function add_filter( $hook, $fn, $priority = 10, $args = 1 ) { global $hookList; $hookList[] = array( $hook, $priority ); }
function add_action( $hook, $fn, $priority = 10, $args = 1 ) { global $hookList; $hookList[] = array( $hook, $priority ); }
function absint( $value ) { return abs( (int) $value ); }
$owners = array( '19' => 'madagaskar', '22' => 'milano' );
function get_option( $name, $fallback = array() ) { global $owners; return $name === 'dunya_event_owner_brand_map_v1' ? $owners : $fallback; }
class MDG_DB {
    public static function table( $table ) { return 'wp_mdg_' . $table; }
}
class StubDb {
    public $product_events = array( 500 => array( 19 ), 501 => array( 22 ), 800 => array( 19, 22 ) );
    public $variation_events = array( 600 => array( 19 ), 601 => array( 22 ), 802 => array( 19, 22 ) );
    public function prepare( $sql, ...$args ) {
        demand( count( $args ) === substr_count( $sql, '%d' ), 'invalid SQL placeholders' );
        return vsprintf( $sql, $args );
    }
    public function get_col( $sql ) {
        if ( false !== strpos( $sql, 'wc_variation_id=' ) ) {
            preg_match( '/wc_variation_id=(\d+)/', $sql, $m );
            return $this->variation_events[ (int) ($m[1] ?? 0) ] ?? array();
        }
        preg_match( '/wc_product_id=(\d+)/', $sql, $m );
        return $this->product_events[ (int) ($m[1] ?? 0) ] ?? array();
    }
}
$wpdb = new StubDb();
$notices = array();
function wc_add_notice( $message, $type ) { global $notices; $notices[] = array( $message, $type ); }
class StubCart { public $cart = array(); public function get_cart() { return $this->cart; } }
class StubWC { public $cart; public function __construct() { $this->cart = new StubCart(); } }
$wc = new StubWC();
function WC() { global $wc; return $wc; }
class StubItem {
    private $product;
    private $variation;
    public function __construct( $p, $v ) { $this->product = $p; $this->variation = $v; }
    public function get_product_id() { return $this->product; }
    public function get_variation_id() { return $this->variation; }
}
class StubOrder {
    public $items = array();
    public function get_items( $kind ) { return $this->items; }
}
require dirname( __DIR__ ) . '/dunya-brand-enforcement/dunya-brand-enforcement.php';
$guard = 'Dunya_Event_Brand_Guard';
demand( count( $hookList ) >= 8, 'missing integration hooks' );
switch ( $scenario ) {
    case 'milano_denied':
        demand( 'milano' === $guard::owner( 22 ), 'Milano event ownership wrong' );
        demand( $guard::is_denied_product( 501, 601 ), 'Milano ticket reached Madagaskar checkout' );
        demand( ! $guard::validate_add_to_cart( true, 501, 1, 601 ), 'Milano cart add not blocked' );
        demand( count( $notices ) === 1, 'cart denial missing WC notice' );
        break;
    case 'madagaskar_allowed':
        demand( $guard::may_sell_on_madagaskar( 19 ), 'Madagaskar event should be allowed' );
        demand( $guard::validate_add_to_cart( true, 500, 1, 600 ), 'Madagaskar ticket blocked' );
        break;
    case 'unrelated_product':
        demand( null === $guard::resolve_mdg_event( 9000, 0 ), 'non-MDG product improperly classified' );
        demand( $guard::validate_add_to_cart( true, 9000, 1, 0 ), 'non-MDG product blocked' );
        break;
    case 'missing_brand':
        unset( $owners['19'] );
        demand( null === $guard::owner( 19 ), 'missing owner must be unknown' );
        demand( ! $guard::validate_add_to_cart( true, 500, 1, 600 ), 'unknown owner wrongly allowed' );
        break;
    case 'invalid_brand':
        $owners['19'] = 'both';
        demand( null === $guard::owner( 19 ), 'unrecognized owner accepted' );
        demand( $guard::is_denied_product( 500, 600 ), 'unknown brand can bypass' );
        break;
    case 'shared_product':
        demand( 0 === $guard::resolve_mdg_event( 800, 0 ), 'product mapping ambiguity not detected' );
        demand( $guard::is_denied_product( 800, 0 ), 'ambiguous MDG product can bypass' );
        break;
    case 'mismatched_variation':
        demand( 0 === $guard::resolve_mdg_event( 500, 601 ), 'mismatched parent/variation undetected' );
        demand( $guard::is_denied_product( 500, 601 ), 'cross-event variation bypass' );
        break;
    case 'direct_parent':
        demand( $guard::is_denied_product( 501, 0 ), 'Milano product by parent bypass' );
        demand( ! $guard::is_denied_product( 500, 0 ), 'Madagaskar product by parent blocked' );
        break;
    case 'existing_cart':
        $wc->cart->cart = array( array( 'product_id'=>501, 'variation_id'=>601 ) );
        $guard::validate_cart();
        demand( count( $notices ) === 1, 'existing cart lacks error notice' );
        break;
    case 'checkout_order':
        $o = new StubOrder();
        $o->items = array( new StubItem(501, 601) );
        try {
            $guard::validate_store_order( $o );
            demand( false, 'checkout order not rejected' );
        } catch ( Exception $e ) {
            demand( false !== strpos( $e->getMessage(), 'farklı bir markaya' ), 'unexpected denial message' );
        }
        break;
    case 'valid_order':
        $o = new StubOrder();
        $o->items = array( new StubItem(500, 600) );
        $guard::validate_store_order( $o );
        break;
    case 'store_cart':
        try {
            $guard::validate_store_cart_item( null, array('product_id'=>501,'variation_id'=>601) );
            demand( false, 'Store API cart not rejected' );
        } catch ( Exception $e ) {
            demand( true, 'correct rejection' );
        }
        break;
    case 'cart_preserve_upstream_error':
        demand( ! $guard::validate_add_to_cart( false, 500, 1, 600 ), 'upstream error incorrectly cleared' );
        break;
    default:
        demand( false, 'unexpected test case ' . $scenario );
}
echo "PASS {$scenario}\n";
