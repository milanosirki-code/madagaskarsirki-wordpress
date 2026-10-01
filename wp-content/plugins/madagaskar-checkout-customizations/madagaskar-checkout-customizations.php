<?php
/**
 * Plugin Name: Madagaskar Checkout Customizations
 * Description: Bilet Al, checkout, Tickera PDF ve Biletlerim davranışlarını Code Snippets'tan kontrollü plugin modüllerine taşır.
 * Version: 0.1.0
 * Author: Dünya Organizasyon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MDG_CHECKOUT_CUSTOMIZATIONS_VERSION', '0.1.0' );
define( 'MDG_CHECKOUT_CUSTOMIZATIONS_DIR', plugin_dir_path( __FILE__ ) );

function mdg_checkout_customizations_enabled_modules() {
    $raw = (string) get_option( 'mdg_checkout_customizations_modules', '' );
    $items = array_filter( array_map( 'sanitize_key', preg_split( '/\s*,\s*/', $raw ) ?: array() ) );
    return array_values( array_unique( $items ) );
}

function mdg_checkout_customizations_module_map() {
    return array(
        'ticket-type-short'            => 'modules/ticket-type-short.php',
        'checkout-phone-required'      => 'modules/checkout-phone-required.php',
        'ticket-buy-cache-clear'       => 'modules/ticket-buy-cache-clear.php',
        'ticket-pdf-address-fix'       => 'modules/ticket-pdf-address-fix.php',
        'whatsapp-ticket-access-fix'   => 'modules/whatsapp-ticket-access-fix.php',
        'ticket-buy-page-v4'           => 'modules/ticket-buy-page-v4.php',
    );
}

function mdg_checkout_customizations_load_modules() {
    $enabled = mdg_checkout_customizations_enabled_modules();
    $map = mdg_checkout_customizations_module_map();

    foreach ( $enabled as $slug ) {
        if ( empty( $map[ $slug ] ) ) {
            continue;
        }
        $path = MDG_CHECKOUT_CUSTOMIZATIONS_DIR . $map[ $slug ];
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }
}
add_action( 'plugins_loaded', 'mdg_checkout_customizations_load_modules', 1 );
