<?php
/**
 * Plugin Name: Madagaskar Kommo Automation
 * Description: Kommo bilet linki, konum cevapları ve dinamik aktif etkinlik kaynağını Code Snippets'tan kontrollü plugin modüllerine taşır.
 * Version: 0.1.1
 * Author: Dünya Organizasyon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MDG_KOMMO_AUTOMATION_VERSION', '0.1.1' );
define( 'MDG_KOMMO_AUTOMATION_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Runtime Kommo secret.
 *
 * The secret is intentionally not stored in source control. WordPress.com does
 * not currently allow this site to persist the preferred constant in wp-config,
 * so the migration stores it in a non-autoloaded option and exposes only the
 * preferred MMC constant at plugin bootstrap time.
 */
$mdg_kommo_runtime_token = trim( (string) get_option( 'mmc_kommo_runtime_token', '' ) );
if ( '' !== $mdg_kommo_runtime_token && ! defined( 'MMC_KOMMO_TOKEN' ) ) {
    define( 'MMC_KOMMO_TOKEN', $mdg_kommo_runtime_token );
}
unset( $mdg_kommo_runtime_token );

function mdg_kommo_automation_enabled_modules() {
    $raw = (string) get_option( 'mdg_kommo_automation_modules', '' );
    $items = array_filter( array_map( 'sanitize_key', preg_split( '/\s*,\s*/', $raw ) ?: array() ) );
    return array_values( array_unique( $items ) );
}

function mdg_kommo_automation_module_map() {
    return array(
        'automatic-ticket-link'  => 'modules/automatic-ticket-link.php',
        'location-answers'       => 'modules/location-answers.php',
        'location-menu-fix'      => 'modules/location-menu-fix.php',
        'active-events-source'   => 'modules/active-events-source.php',
    );
}

function mdg_kommo_automation_load_modules() {
    $enabled = mdg_kommo_automation_enabled_modules();
    $map = mdg_kommo_automation_module_map();

    foreach ( $enabled as $slug ) {
        if ( empty( $map[ $slug ] ) ) {
            continue;
        }
        $path = MDG_KOMMO_AUTOMATION_DIR . $map[ $slug ];
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }
}
add_action( 'plugins_loaded', 'mdg_kommo_automation_load_modules', 1 );
