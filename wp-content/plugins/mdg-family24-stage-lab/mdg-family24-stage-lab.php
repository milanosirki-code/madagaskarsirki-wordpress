<?php
/**
 * Plugin Name: MDG Family24 Private Staging Lab
 * Description: Restricted staging-only native cart tests with an in-memory Woo session; never runs a gateway.
 * Version: 0.1.1
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Family24_Lab_Stop extends RuntimeException {}
final class MDG_Family24_Lab {
    const HOST = 'staging-f321-milanosirki-lgajf.wpcomstaging.com';
    public static function permission() { return wp_parse_url( home_url(), PHP_URL_HOST ) === self::HOST && current_user_can( 'manage_woocommerce' ); }
    public static function routes() {
        if ( wp_parse_url( home_url(), PHP_URL_HOST ) !== self::HOST ) { return; }
        register_rest_route( 'mdg-family24-lab/v1', '/inventory', array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'permission' ), 'callback' => array( __CLASS__, 'inventory' ) ) );
        register_rest_route( 'mdg-family24-lab/v1', '/cart', array( 'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'permission' ), 'callback' => array( __CLASS__, 'cart' ) ) );
        register_rest_route( 'mdg-family24-lab/v1', '/restore-cart', array( 'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'permission' ), 'callback' => array( __CLASS__, 'restore_cart' ) ) );
    }
    private static function response( $data, $status = 200 ) {
        status_header( $status );
        $r = new WP_REST_Response( $data, $status ); $r->header( 'Cache-Control', 'private, no-store' ); return $r;
    }
    public static function inventory() {
        if ( ! self::permission() ) { return self::response( array( 'code' => 'FORBIDDEN' ), 403 ); }
        global $wp_filter;
        $out = array();
        foreach ( array( 'woocommerce_new_order', 'woocommerce_checkout_create_order', 'woocommerce_checkout_order_processed', 'woocommerce_order_status_processing', 'woocommerce_order_status_changed', 'woocommerce_payment_complete', 'tc_created_order_ticket_instance' ) as $hook ) {
            foreach ( isset( $wp_filter[$hook] ) ? $wp_filter[$hook]->callbacks : array() as $priority => $callbacks ) {
                foreach ( $callbacks as $row ) {
                    $f = $row['function'];
                    $name = is_string( $f ) ? $f : ( is_array( $f ) ? ( is_object( $f[0] ) ? get_class( $f[0] ) : $f[0] ) . '::' . $f[1] : get_class( $f ) );
                    $out[$hook][] = array( 'priority' => $priority, 'callback' => $name );
                }
            }
        }
        $bridge = array();
        foreach ( $GLOBALS as $name => $value ) {
            if ( is_object( $value ) && is_a( $value, 'TC_WooCommerce_Bridge' ) ) { $bridge[] = array( 'global' => $name, 'methods' => array_values( array_intersect( get_class_methods( $value ), array( 'tc_order_created', 'create_order_ticket_instances', 'check_tickets_action', 'check_order_tickets' ) ) ) ); }
        }
        $saved = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
        $summary = array();
        foreach ( (array) ( $saved['cart'] ?? array() ) as $item ) { $summary[] = array( 'variation' => (int) ( $item['variation_id'] ?? 0 ), 'qty' => (int) ( $item['quantity'] ?? 0 ), 'family24' => ! empty( $item['mdg_family24'] ) ); }
        return self::response( array( 'hooks' => $out, 'bridge' => $bridge, 'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : null, 'persistent_cart' => $summary, 'orders_created' => false ) );
    }
    public static function restore_cart() {
        if ( ! self::permission() ) { return self::response( array( 'code' => 'FORBIDDEN' ), 403 ); }
        $key = '_woocommerce_persistent_cart_' . get_current_blog_id();
        $saved = get_user_meta( get_current_user_id(), $key, true );
        $cart = (array) ( $saved['cart'] ?? array() ); $actual = array();
        foreach ( $cart as $item ) { $actual[(int) ( $item['variation_id'] ?? 0 )] = (int) ( $item['quantity'] ?? 0 ); }
        ksort( $actual ); $expected = array( 2388 => 4, 2389 => 2, 6082 => 2, 6083 => 1 );
        if ( $actual !== $expected || count( $cart ) !== 4 ) { return self::response( array( 'restored' => false, 'reason' => 'No exact match to the first lab result; no write.' ) ); }
        $removed = 0; $groups = array();
        foreach ( $cart as $id => $item ) {
            if ( ! in_array( (int) $item['variation_id'], array( 2388, 2389 ), true ) ) { continue; }
            $meta = $item['mdg_family24'] ?? null;
            if ( ! is_array( $meta ) || (int) ( $meta['event'] ?? 0 ) !== 10 || (int) ( $meta['session'] ?? 0 ) !== 88 ) { return self::response( array( 'restored' => false, 'reason' => 'Group differs; no write.' ) ); }
            $groups[] = $meta['group']; unset( $cart[$id] ); $removed++;
        }
        if ( $removed !== 2 || count( array_unique( $groups ) ) !== 1 ) { return self::response( array( 'restored' => false, 'reason' => 'Ambiguous group; no write.' ) ); }
        $saved['cart'] = $cart; update_user_meta( get_current_user_id(), $key, $saved );
        return self::response( array( 'restored' => true, 'remaining_items' => count( $cart ), 'removed_lab_components' => 2 ) );
    }
    private static function snapshot() {
        global $wpdb;
        return $wpdb->get_row( 'SELECT id,capacity_total,sold_units,held_units FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE id=88', ARRAY_A );
    }
    public static function cart( $request ) {
        if ( ! self::permission() ) { return self::response( array( 'code' => 'FORBIDDEN' ), 403 ); }
        $input = $request->get_json_params();
        if ( ! is_array( $input ) || array_keys( $input ) !== array( 'case' ) || ! in_array( $input['case'], array( 'family24', 'family22', 'mixed', 'bad_nonce' ), true ) ) { return self::response( array( 'code' => 'INVALID_INPUT' ), 400 ); }
        if ( ! class_exists( 'MDG_Family_Package_24' ) || ! class_exists( 'MDG_Live_Sales' ) || ! class_exists( 'WC_Cart' ) ) { return self::response( array( 'code' => 'MISSING_DEPENDENCY' ), 503 ); }
        $wc = WC();
        $original = array( $wc->cart, $wc->session, $wc->customer, $_POST, $_REQUEST );
        $original_user = get_current_user_id();
        $persistent_before = get_user_meta( $original_user, '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
        $before = self::snapshot();
        $mail = static function() { return true; };
        $http = static function() { return new WP_Error( 'family24_lab_no_network', 'External HTTP is disabled in this staging test.' ); };
        $die = static function() { return static function( $message = '' ) { if ( is_scalar( $message ) ) { echo $message; } throw new MDG_Family24_Lab_Stop(); }; };
        add_filter( 'pre_wp_mail', $mail, PHP_INT_MAX );
        add_filter( 'pre_http_request', $http, PHP_INT_MAX );
        add_filter( 'wp_die_ajax_handler', $die, PHP_INT_MAX );
        add_filter( 'woocommerce_persistent_cart_enabled', '__return_false', PHP_INT_MAX );
        if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
        $buffer = ob_get_level();
        try {
            wp_set_current_user( 0 );
            // This session has no persistence methods, cookie or logged-in customer's cart.
            $wc->session = new class extends WC_Session {
                public function __construct() { $this->_customer_id = 'family24-lab'; $this->_data = array(); }
                public function init() {}
                public function has_session() { return true; }
                public function get_session( $customer_id, $default = false ) { return $default; }
                public function save_data( $old_session_key = 0 ) {}
                public function destroy_session() {}
                public function set_customer_session_cookie( $set ) {}
            };
            $wc->customer = new WC_Customer( 0 );
            $wc->cart = new WC_Cart();
            $lines = array();
            if ( $input['case'] !== 'family22' ) { $lines[] = array( 'code' => 'MDG_FAMILY_2_4', 'qty' => 1 ); }
            if ( in_array( $input['case'], array( 'family22', 'mixed' ), true ) ) { $lines[] = array( 'code' => 'MDG_FAMILY_2_2', 'qty' => 1 ); }
            $_POST = array( 'action' => 'mdg_live_add_to_cart', 'event_id' => 10, 'session_id' => 88, 'lines' => wp_json_encode( $lines ), 'nonce' => $input['case'] === 'bad_nonce' ? 'deliberately-invalid' : wp_create_nonce( 'mdg_live_cart_10' ) );
            $_REQUEST = $_POST;
            ob_start();
            try { do_action( 'wp_ajax_mdg_live_add_to_cart' ); } catch ( MDG_Family24_Lab_Stop $e ) {}
            $body = ob_get_clean();
            $payload = json_decode( $body, true );
            $items = array(); $units = 0;
            foreach ( $wc->cart->get_cart() as $item ) {
                $qty = (int) $item['quantity'];
                $units += $qty * (int) ( $item['mdg_capacity_units'] ?? 1 );
                $items[] = array( 'variation_id' => (int) $item['variation_id'], 'quantity' => $qty, 'line_total' => (float) $item['line_total'], 'has_family24_meta' => ! empty( $item['mdg_family24'] ), 'has_family22_meta' => ! empty( $item['mdg_family22'] ) );
            }
            $fees = array();
            foreach ( $wc->cart->get_fees() as $fee ) { $fees[] = array( 'name' => $fee->name, 'total' => (float) $fee->total ); }
            $out = array( 'case' => $input['case'], 'native_success' => is_array( $payload ) ? (bool) ( $payload['success'] ?? false ) : false, 'nonce_rejected' => trim( $body ) === '-1', 'items' => $items, 'fees' => $fees, 'total' => (float) $wc->cart->get_total( 'edit' ), 'capacity_units' => $units, 'capacity_unchanged' => $before === self::snapshot(), 'persistent_cart_unchanged' => $persistent_before === get_user_meta( $original_user, '_woocommerce_persistent_cart_' . get_current_blog_id(), true ), 'session_persisted' => false, 'order_created' => false, 'payment_called' => false );
            return self::response( $out );
        } catch ( Throwable $e ) {
            return self::response( array( 'code' => 'LAB_FAILED', 'message' => $e->getMessage(), 'class' => get_class( $e ), 'capacity_unchanged' => $before === self::snapshot() ), 500 );
        } finally {
            while ( ob_get_level() > $buffer ) { ob_end_clean(); }
            list( $wc->cart, $wc->session, $wc->customer, $_POST, $_REQUEST ) = $original;
            wp_set_current_user( $original_user );
            remove_filter( 'pre_wp_mail', $mail, PHP_INT_MAX );
            remove_filter( 'pre_http_request', $http, PHP_INT_MAX );
            remove_filter( 'wp_die_ajax_handler', $die, PHP_INT_MAX );
            remove_filter( 'woocommerce_persistent_cart_enabled', '__return_false', PHP_INT_MAX );
        }
    }
}
add_action( 'rest_api_init', array( 'MDG_Family24_Lab', 'routes' ) );
