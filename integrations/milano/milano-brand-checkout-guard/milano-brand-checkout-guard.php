<?php
/**
 * Plugin Name: Dünya Organizasyon - Marka Satış Koruması (Taslak)
 * Description: Milano/Madagaskar marka sınırını WooCommerce sepet girişinde doğrular. Varsayılan olarak devre dışı; yalnız test ortamı içindir.
 * Version: 0.5.0
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Activation deliberately requires a separately provisioned server constant.
 * NEVER enable in production before mapping and checkout regression tests.
 */
function dmbg_enabled() {
    return defined( 'DUNYA_BRAND_GUARD_ENABLED' ) && true === DUNYA_BRAND_GUARD_ENABLED;
}
function dmbg_site_brand() {
    $host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    $host = strtolower( (string) $host );
    if ( 'milanosirki.com' === $host || 'www.milanosirki.com' === $host ) { return 'milano'; }
    if ( 'madagaskarsirki.com' === $host || 'www.madagaskarsirki.com' === $host ) { return 'madagaskar'; }
    return null;
}

/** Look up a WooCommerce item through canonical MDG ticket mappings, never visitor-controlled metadata. */
function dmbg_product_owner( $product_id, $variation_id = 0 ) {
    if ( ! class_exists( 'MDG_DB' ) || ! class_exists( 'MMC_MDG_Bridge_Service' )
        || ! class_exists( 'MMC_Program_Service' ) ) { return null; }
    global $wpdb;
    $session_table = MDG_DB::table( 'sessions' );
    $type_table = MDG_DB::table( 'ticket_types' );
    $target = absint( $variation_id ? $variation_id : $product_id );
    if ( ! $target ) { return null; }
    $rows = (array) $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT s.event_id FROM {$session_table} s
         INNER JOIN {$type_table} t ON t.session_id=s.id
         WHERE t.wc_variation_id=%d AND s.wc_product_id=%d AND t.is_active=1",
        $target, absint( $product_id )
    ) );
    if ( count( $rows ) !== 1 ) { return null; }
    $pid = (int) MMC_MDG_Bridge_Service::program_for_mdg_event( (int) $rows[0] );
    $program = $pid ? MMC_Program_Service::get_program( $pid ) : null;
    if ( ! $program || ! isset( $program->brand ) ) { return null; }
    $brand = MMC_Program_Service::normalized_program_brand( $program->brand );
    if ( 'Milano Sirki' === $brand ) { return 'milano'; }
    if ( 'Madagaskar Sirki' === $brand ) { return 'madagaskar'; }
    return null;
}

/**
 * Pilot guard: no side effects while disabled.
 * When enabled, only mapped ticket items may be added from their owner site.
 * This does not replace cart, checkout, Store API or PayTR callback protections.
 */
function dmbg_validate_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0 ) {
    if ( ! dmbg_enabled() || ! $passed ) { return $passed; }
    $site = dmbg_site_brand();
    $owner = dmbg_product_owner( $product_id, $variation_id );
    if ( ! $site || ! $owner || $site !== $owner ) {
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( 'Bu etkinlik için yetkili satış kanalını kullanınız.', 'error' );
        }
        return false;
    }
    return true;
}
add_filter( 'woocommerce_add_to_cart_validation', 'dmbg_validate_add_to_cart', 100, 4 );

/**
 * Re-check canonical ownership at cart and checkout, not only add-to-cart.
 * This is an opt-in experimental WooCommerce classic cart/checkout gate.
 */
function dmbg_cart_owners_valid( $cart ) {
    if ( ! $cart || ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) { return false; }
    $site = dmbg_site_brand();
    if ( ! $site ) { return false; }
    foreach ( (array) $cart->get_cart() as $item ) {
        if ( ! is_array( $item ) || empty( $item['product_id'] ) ) { return false; }
        $owner = dmbg_product_owner( (int) $item['product_id'], (int) ( $item['variation_id'] ?? 0 ) );
        if ( $owner !== $site ) { return false; }
    }
    return true;
}
function dmbg_validate_cart() {
    if ( ! dmbg_enabled() ) { return; }
    $cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;
    if ( ! dmbg_cart_owners_valid( $cart ) && function_exists( 'wc_add_notice' ) ) {
        wc_add_notice( 'Sepette farklı markaya ait veya doğrulanamayan bir bilet var. Lütfen yetkili satış kanalını kullanın.', 'error' );
    }
}
function dmbg_validate_checkout( $data, $errors ) {
    if ( ! dmbg_enabled() ) { return; }
    $cart = function_exists( 'WC' ) && WC() ? WC()->cart : null;
    if ( ! dmbg_cart_owners_valid( $cart ) && $errors && method_exists( $errors, 'add' ) ) {
        $errors->add( 'dmbg_brand_mismatch', 'Bu sepet seçilen markanın satış kanalında tamamlanamaz.' );
    }
}
add_action( 'woocommerce_check_cart_items', 'dmbg_validate_cart', 100 );
add_action( 'woocommerce_after_checkout_validation', 'dmbg_validate_checkout', 100, 2 );

/**
 * WooCommerce Store API add-to-cart gate. Core throws errors returned via
 * WP_Error; no notice-only workaround and no user-supplied brand override.
 */
function dmbg_store_api_add_to_cart( $product, $request ) {
    if ( ! dmbg_enabled() ) { return; }
    $product_id = method_exists( $product, 'get_parent_id' ) && (int) $product->get_parent_id()
        ? (int) $product->get_parent_id() : (int) $product->get_id();
    $variation_id = $product_id !== (int) $product->get_id() ? (int) $product->get_id() : 0;
    $owner = dmbg_product_owner( $product_id, $variation_id );
    if ( ! dmbg_site_brand() || $owner !== dmbg_site_brand() ) {
        throw new \Exception( 'Bu etkinliğe yalnız ait olduğu marka üzerinden bilet alınabilir.' );
    }
}
add_action( 'woocommerce_store_api_validate_add_to_cart', 'dmbg_store_api_add_to_cart', 100, 2 );

/**
 * Store API checkout updates must not save a cross-brand order.
 * Product ownership is re-derived from canonical server mappings.
 */
function dmbg_store_api_checkout_order( $order, $request ) {
    if ( ! dmbg_enabled() ) { return; }
    if ( ! dmbg_cart_owners_valid( function_exists( 'WC' ) && WC() ? WC()->cart : null )
        || ! dmbg_order_owners_valid( $order ) ) {
        throw new \Exception( 'Bu sipariş bu marka üzerinden tamamlanamaz.' );
    }
}
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'dmbg_store_api_checkout_order', 100, 2 );

/** Verify saved order lines independently of the cart (draft only). */
function dmbg_order_owners_valid( $order ) {
    if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) { return false; }
    $site = dmbg_site_brand();
    if ( ! $site ) { return false; }
    $items = $order->get_items( 'line_item' );
    if ( ! $items ) { return false; }
    foreach ( $items as $item ) {
        if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' )
            || ! method_exists( $item, 'get_variation_id' ) ) { return false; }
        if ( dmbg_product_owner( (int) $item->get_product_id(), (int) $item->get_variation_id() ) !== $site ) {
            return false;
        }
    }
    return true;
}

/**
 * Gateway-independent payment settlement preflight. This is deliberately
 * NOT attached to a PayTR callback: signature verification, amount checks,
 * idempotency and the gateway's actual hooks must be audited first.
 * Returns false for unknown/mixed-brand orders and cannot trigger payment
 * status changes, ticket or QR generation by itself.
 */
function dmbg_payment_settlement_allowed( $order ) {
    if ( ! dmbg_enabled() ) { return false; }
    if ( ! dmbg_order_owners_valid( $order ) ) { return false; }
    if ( ! method_exists( $order, 'get_currency' ) || 'TRY' !== $order->get_currency() ) { return false; }
    if ( ! method_exists( $order, 'get_total' ) || (float) $order->get_total() <= 0 ) { return false; }
    if ( ! method_exists( $order, 'get_status' )
        || in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed', 'trash' ), true ) ) {
        return false;
    }
    return true;
}
