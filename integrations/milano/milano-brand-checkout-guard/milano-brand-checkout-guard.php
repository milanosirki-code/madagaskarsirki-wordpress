<?php
/**
 * Plugin Name: Dünya Organizasyon - Marka Satış Koruması (Taslak)
 * Description: Milano/Madagaskar marka sınırını WooCommerce sepet girişinde doğrular. Varsayılan olarak devre dışı; yalnız test ortamı içindir.
 * Version: 0.2.0
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
