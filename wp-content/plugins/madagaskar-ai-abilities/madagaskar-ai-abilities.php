<?php
/**
 * Plugin Name: Madagaskar AI Abilities
 * Description: Madagaskar operasyon AI ability modüllerini Code Snippets katmanından kontrollü biçimde kalıcı plugin koduna taşır.
 * Version: 0.6.1
 * Author: Dünya Organizasyon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MDG_AI_ABILITIES_VERSION', '0.6.1' );
define( 'MDG_AI_ABILITIES_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Enabled module slugs.
 *
 * Default is intentionally empty. Migration is performed one module at a time
 * after the matching Code Snippets record is deactivated and a smoke test is ready.
 *
 * @return string[]
 */
function mdg_ai_abilities_enabled_modules() {
    $raw = (string) get_option( 'mdg_ai_abilities_modules', '' );
    $items = array_filter( array_map( 'sanitize_key', preg_split( '/\s*,\s*/', $raw ) ?: array() ) );
    return array_values( array_unique( $items ) );
}

/**
 * Supported module map.
 *
 * @return array<string,string>
 */
function mdg_ai_abilities_module_map() {
    return array(
        'system-health-integrity' => 'modules/system-health-integrity.php',
        'mmc-sales-ledger'        => 'modules/mmc-sales-ledger.php',
        'mmc-dashboard'           => 'modules/mmc-dashboard.php',
        'mmc-tasks'               => 'modules/mmc-tasks.php',
        'v4-refund-safety'        => 'modules/v4-refund-safety.php',
        'mmc-region-population'   => 'modules/mmc-region-population.php',
        'mmc-field'               => 'modules/mmc-field.php',
        'mmc-operations'          => 'modules/mmc-operations.php',
        'mmc-marketing'           => 'modules/mmc-marketing.php',
        'mmc-kommo'               => 'modules/mmc-kommo.php',
        'mmc-mdg-bridge'          => 'modules/mmc-mdg-bridge.php',
        'reporting-customer'      => 'modules/reporting-customer.php',
        'v4-operations-safety'    => 'modules/v4-operations-safety.php',
        'invoice-tracking'        => 'modules/invoice-tracking.php',
        'school-promotion'        => 'modules/school-promotion.php',
        'v5-finance'              => 'modules/v5-finance.php',
        'program-venue-event-sales'=> 'modules/program-venue-event-sales.php',
        'legacy-mdg-mmc-preview'      => 'modules/legacy-mdg-mmc-preview.php',
    );
}

function mdg_ai_abilities_load_modules() {
    $enabled = mdg_ai_abilities_enabled_modules();
    $map     = mdg_ai_abilities_module_map();

    foreach ( $enabled as $slug ) {
        if ( empty( $map[ $slug ] ) ) {
            continue;
        }

        $path = MDG_AI_ABILITIES_DIR . $map[ $slug ];
        if ( is_readable( $path ) ) {
            require_once $path;
        }
    }
}
add_action( 'plugins_loaded', 'mdg_ai_abilities_load_modules', 1 );

/**
 * Read-only migration status for administrators.
 *
 * @return array<string,mixed>
 */
function mdg_ai_abilities_status() {
    return array(
        'version'           => MDG_AI_ABILITIES_VERSION,
        'enabled_modules'   => mdg_ai_abilities_enabled_modules(),
        'supported_modules' => array_keys( mdg_ai_abilities_module_map() ),
    );
}
