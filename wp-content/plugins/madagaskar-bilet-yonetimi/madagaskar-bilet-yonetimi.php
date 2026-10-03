<?php
/**
 * Plugin Name: Madagaskar Bilet Yönetimi
 * Description: Madagaskar Sirki için salon, etkinlik, seans, ortak kapasite, raporlama ve entegrasyon çekirdeği.
 * Version: 3.6.3-ticket-invalidation-dry-run
 * Author: Dünya Organizasyon
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 9.0
 * Text Domain: madagaskar-bilet
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MDG_BILET_VERSION', '3.6.3-ticket-invalidation-dry-run' );
define( 'MDG_BILET_FILE', __FILE__ );
define( 'MDG_BILET_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDG_BILET_URL', plugin_dir_url( __FILE__ ) );

require_once MDG_BILET_DIR . 'includes/class-mdg-db.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-activator.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-status.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-capacity.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-qr.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-ticket-venue-qr.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-ticket-designer-diagnostic.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-ticket-template-qr-binder.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-venues.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-venue-importer.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-event-importer.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-sessions.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-events.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-public-event.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-public-cities.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-public-tickets.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-sales-audit.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-sales-dry-run.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-sales-adopter.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-sales-cart-test.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-live-sales.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-checkout-ux.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-production-readiness.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-production-publisher.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-new-event-production-plan.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-new-event-draft-producer.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-sales-reports.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-customer-tickets.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-refund-preview.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-refund-test.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-ticket-invalidation-dry-run.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-admin.php';
require_once MDG_BILET_DIR . 'includes/class-mdg-plugin.php';


register_activation_hook( __FILE__, array( 'MDG_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MDG_Activator', 'deactivate' ) );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    MDG_Activator::maybe_upgrade();
    MDG_Plugin::instance()->boot();
} );
