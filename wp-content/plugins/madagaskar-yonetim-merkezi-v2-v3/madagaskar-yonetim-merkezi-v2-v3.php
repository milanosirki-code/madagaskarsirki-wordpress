<?php
/**
 * Plugin Name: Madagaskar Yönetim Merkezi V2 + V3
 * Description: Madagaskar Sirki için V2 Pazarlama (Meta Ads, Instagram, GA4) ve V3 CRM (Kommo, WhatsApp, reklam→müşteri→satış atıf zinciri) yönetim merkezi.
 * Version: 1.3.2
 * Author: Madagaskar Sirki / Dünya Organizasyon
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Text Domain: madagaskar-yonetim
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MDGY_VERSION', '1.3.2' );
define( 'MDGY_FILE', __FILE__ );
define( 'MDGY_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDGY_URL', plugin_dir_url( __FILE__ ) );

require_once MDGY_DIR . 'includes/class-mdgy-core.php';

register_activation_hook( __FILE__, array( 'MDGY_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MDGY_Core', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'MDGY_Core', 'boot' ), 30 );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), static function( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mdgy-integrations' ) ) . '">Entegrasyonlar</a>' );
    return $links;
} );
