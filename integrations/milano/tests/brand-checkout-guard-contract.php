<?php
/**
 * Standalone brand add-to-cart safety contract; no WordPress database needed.
 * php integrations/milano/tests/brand-checkout-guard-contract.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'DUNYA_BRAND_GUARD_ENABLED', true );
function expect_guard( $ok, $label ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); } }
function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function absint( $v ) { return abs( (int) $v ); }
function home_url( $v ) { return 'https://milanosirki.com/'; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function wc_add_notice( $msg, $type ) {}
class MDG_DB { public static function table( $name ) { return 'wp_mdg_' . $name; } }
class MMC_Program_Service {
    public static function get_program( $id ) {
        if ( 20 === $id ) { return (object) array( 'brand' => 'Milano Sirki' ); }
        if ( 30 === $id ) { return (object) array( 'brand' => 'Madagaskar Sirki' ); }
        return null;
    }
    public static function normalized_program_brand( $v ) { return $v; }
}
class MMC_MDG_Bridge_Service {
    public static function table() { return 'wp_mmc_mdg_event_bridge'; }
    public static function program_for_mdg_event( $event_id ) {
        expect_guard( false, 'legacy fallback helper must never grant brand ownership' );
    }
}
$bridgeLinks = array( 22 => array( 20 ), 33 => array( 30 ) );
class GuardMockDB {
    public function prepare( $sql, ...$args ) {
        if ( false !== strpos( $sql, 'SELECT program_id' ) ) {
            expect_guard( 1 === count( $args ), 'brand lookup requires event id' );
            expect_guard( false !== strpos( $sql, 'is_active=1 LIMIT 2' ), 'brand lookup must reject multiple owners' );
            return 'BRIDGE:' . $args[0];
        }
        expect_guard( 2 === count( $args ), 'two product identifiers required' );
        return implode( ':', $args );
    }
    public function get_col( $prepared ) {
        global $bridgeLinks;
        if ( 0 === strpos( $prepared, 'BRIDGE:' ) ) {
            return $bridgeLinks[ (int) substr( $prepared, 7 ) ] ?? array();
        }
        if ( '202:101' === $prepared ) { return array( 22 ); }
        if ( '303:101' === $prepared ) { return array( 33 ); }
        if ( '404:101' === $prepared ) { return array( 22, 33 ); }
        return array();
    }
}
$wpdb = new GuardMockDB();
require dirname( __DIR__ ) . '/milano-brand-checkout-guard/milano-brand-checkout-guard.php';
expect_guard( 'milano' === dmbg_site_brand(), 'host to brand' );
expect_guard( 'milano' === dmbg_product_owner( 101, 202 ), 'Milano ticket mapping' );
$bridgeLinks[22] = array( 20, 30 );
expect_guard( null === dmbg_event_owner( 22 ), 'ambiguous active bridges must deny owner' );
expect_guard( null === dmbg_product_owner( 101, 202 ), 'ambiguous active bridges must deny product' );
$bridgeLinks[22] = array();
expect_guard( null === dmbg_event_owner( 22 ), 'missing active bridge must not use legacy fallback' );
$bridgeLinks[22] = array( 20 );
expect_guard( 'madagaskar' === dmbg_product_owner( 101, 303 ), 'Madagaskar ticket mapping' );
expect_guard( null === dmbg_product_owner( 101, 404 ), 'ambiguous product mapping must fail' );
expect_guard( null === dmbg_product_owner( 101, 999 ), 'unmapped product must fail' );
expect_guard( true === dmbg_validate_add_to_cart( true, 101, 1, 202 ), 'Milano on Milano' );
expect_guard( false === dmbg_validate_add_to_cart( true, 101, 1, 303 ), 'Madagaskar on Milano blocked' );
expect_guard( false === dmbg_validate_add_to_cart( true, 101, 1, 404 ), 'ambiguous blocked' );
expect_guard( false === dmbg_validate_add_to_cart( true, 101, 1, 999 ), 'unmapped blocked' );
expect_guard( false === dmbg_validate_add_to_cart( false, 101, 1, 202 ), 'preserve earlier denial' );
class GuardProduct {
    private $id;
    private $parent;
    public function __construct( $id, $parent ) { $this->id = $id; $this->parent = $parent; }
    public function get_id() { return $this->id; }
    public function get_parent_id() { return $this->parent; }
}
dmbg_store_api_add_to_cart( new GuardProduct( 202, 101 ), null );
$store_denied = false;
try { dmbg_store_api_add_to_cart( new GuardProduct( 303, 101 ), null ); }
catch ( Exception $ex ) { $store_denied = true; }
expect_guard( $store_denied, 'Store API cross brand must throw' );
$store_unknown = false;
try { dmbg_store_api_add_to_cart( new GuardProduct( 999, 101 ), null ); }
catch ( Exception $ex ) { $store_unknown = true; }
expect_guard( $store_unknown, 'Store API unmapped must throw' );
class GuardCart { private $items; public function __construct( $items ) { $this->items = $items; } public function get_cart() { return $this->items; } }
class GuardWC { public $cart; public function __construct( $cart ) { $this->cart = $cart; } }
$guard_wc = new GuardWC( new GuardCart( array( array( 'product_id'=>101, 'variation_id'=>202 ) ) ) );
function WC() { global $guard_wc; return $guard_wc; }
class GuardOrderItem {
    private $product; private $variation;
    public function __construct( $product, $variation ) { $this->product=$product; $this->variation=$variation; }
    public function get_product_id() { return $this->product; }
    public function get_variation_id() { return $this->variation; }
}
class GuardOrder {
    private $items;
    public function __construct( $items ) { $this->items=$items; }
    public function get_items( $type = 'line_item' ) { return $this->items; }
}
$valid_order = new GuardOrder( array( new GuardOrderItem( 101, 202 ) ) );
$invalid_order = new GuardOrder( array( new GuardOrderItem( 101, 303 ) ) );
expect_guard( true === dmbg_order_owners_valid( $valid_order ), 'matching order lines accepted' );
expect_guard( false === dmbg_order_owners_valid( $invalid_order ), 'cross-brand order lines refused' );
dmbg_store_api_checkout_order( $valid_order, null );
$wrong_order_denied = false;
try { dmbg_store_api_checkout_order( $invalid_order, null ); }
catch ( Exception $ex ) { $wrong_order_denied = true; }
expect_guard( $wrong_order_denied, 'valid cart cannot override wrong order lines' );
$guard_wc->cart = new GuardCart( array( array( 'product_id'=>101, 'variation_id'=>303 ) ) );
$checkout_denied = false;
try { dmbg_store_api_checkout_order( null, null ); }
catch ( Exception $ex ) { $checkout_denied = true; }
expect_guard( $checkout_denied, 'Store API checkout wrong brand must throw' );
$guard_wc->cart = new GuardCart( array( array( 'product_id'=>101, 'variation_id'=>202 ), array( 'product_id'=>101, 'variation_id'=>303 ) ) );
$mixed_denied = false;
try { dmbg_store_api_checkout_order( null, null ); }
catch ( Exception $ex ) { $mixed_denied = true; }
expect_guard( $mixed_denied, 'Store API checkout mixed-brand cart must throw' );
$guard_wc->cart = new GuardCart( array( array( 'product_id'=>101, 'variation_id'=>999 ) ) );
$unmapped_denied = false;
try { dmbg_store_api_checkout_order( null, null ); }
catch ( Exception $ex ) { $unmapped_denied = true; }
expect_guard( $unmapped_denied, 'Store API checkout unmapped cart must throw' );
class GuardPaidOrder extends GuardOrder {
    private $currency;
    private $total;
    private $status;
    public function __construct( $items, $currency='TRY', $total=490, $status='pending' ) {
        parent::__construct( $items );
        $this->currency=$currency; $this->total=$total; $this->status=$status;
    }
    public function get_currency() { return $this->currency; }
    public function get_total() { return $this->total; }
    public function get_status() { return $this->status; }
}
$milano_line = array( new GuardOrderItem( 101, 202 ) );
$wrong_line = array( new GuardOrderItem( 101, 303 ) );
expect_guard( true === dmbg_payment_settlement_allowed( new GuardPaidOrder( $milano_line ) ), 'owned TRY order can pass gateway preflight' );
expect_guard( false === dmbg_payment_settlement_allowed( new GuardPaidOrder( $wrong_line ) ), 'other brand cannot settle' );
expect_guard( false === dmbg_payment_settlement_allowed( new GuardPaidOrder( $milano_line, 'USD' ) ), 'wrong currency rejected' );
expect_guard( false === dmbg_payment_settlement_allowed( new GuardPaidOrder( $milano_line, 'TRY', 0 ) ), 'zero amount rejected' );
expect_guard( false === dmbg_payment_settlement_allowed( new GuardPaidOrder( $milano_line, 'TRY', 490, 'refunded' ) ), 'refunded order rejected' );
dmbg_classic_checkout_create_order( $valid_order, array() );
$classic_wrong_denied = false;
try { dmbg_classic_checkout_create_order( $invalid_order, array() ); }
catch ( Exception $ex ) { $classic_wrong_denied = true; }
expect_guard( $classic_wrong_denied, 'classic WooCommerce order creation must reject wrong brand before save' );
echo "PASS brand-checkout-guard contract\n";
