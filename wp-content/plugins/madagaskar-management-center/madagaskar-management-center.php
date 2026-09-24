<?php
/**
 * Plugin Name: Madagaskar Management Center
 * Description: Madagaskar Sirki program yaşam döngüsü, bölge veri ambarı, hazırlık analizi, görev, yetki ve değişiklik geçmişi için yönetim merkezi.
 * Version: 1.3.10
 * Author: Dünya Organizasyon
 * Text Domain: madagaskar-management-center
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MMC_VERSION', '1.3.10' );
define( 'MMC_DB_VERSION', '1.3.7' );
define( 'MMC_FILE', __FILE__ );
define( 'MMC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMC_URL', plugin_dir_url( __FILE__ ) );

require_once MMC_DIR . 'includes/class-mmc-activator.php';
require_once MMC_DIR . 'includes/class-mmc-program-service.php';
require_once MMC_DIR . 'includes/class-mmc-population-source-service.php';
require_once MMC_DIR . 'includes/class-mmc-region-service.php';
require_once MMC_DIR . 'includes/class-mmc-meb-source-service.php';
require_once MMC_DIR . 'includes/class-mmc-school-source-service.php';
require_once MMC_DIR . 'includes/class-mmc-venue-service.php';
require_once MMC_DIR . 'includes/class-mmc-venue-admin.php';
require_once MMC_DIR . 'includes/class-mmc-event-service.php';
require_once MMC_DIR . 'includes/class-mmc-event-admin.php';
require_once MMC_DIR . 'includes/class-mmc-sales-service.php';
require_once MMC_DIR . 'includes/class-mmc-sales-admin.php';
require_once MMC_DIR . 'includes/class-mmc-kommo-service.php';
require_once MMC_DIR . 'includes/class-mmc-kommo-admin.php';
require_once MMC_DIR . 'includes/class-mmc-marketing-service.php';
require_once MMC_DIR . 'includes/class-mmc-marketing-admin.php';
require_once MMC_DIR . 'includes/class-mmc-field-service.php';
require_once MMC_DIR . 'includes/class-mmc-field-admin.php';
require_once MMC_DIR . 'includes/class-mmc-operations-service.php';
require_once MMC_DIR . 'includes/class-mmc-operations-admin.php';
require_once MMC_DIR . 'includes/class-mmc-finance-service.php';
require_once MMC_DIR . 'includes/class-mmc-finance-admin.php';
require_once MMC_DIR . 'includes/class-mmc-dashboard-service.php';
require_once MMC_DIR . 'includes/class-mmc-health-service.php';
require_once MMC_DIR . 'includes/class-mmc-dashboard-admin.php';
require_once MMC_DIR . 'includes/class-mmc-report-service.php';
require_once MMC_DIR . 'includes/class-mmc-report-admin.php';
require_once MMC_DIR . 'includes/class-mmc-mdg-bridge-service.php';
require_once MMC_DIR . 'includes/class-mmc-integrity-service.php';
require_once MMC_DIR . 'includes/class-mmc-integrity-admin.php';
require_once MMC_DIR . 'includes/class-mmc-navigation-admin.php';
require_once MMC_DIR . 'includes/class-mmc-admin.php';

register_activation_hook( __FILE__, array( 'MMC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MMC_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
    if ( class_exists( 'MMC_Population_Source_Service' ) ) {
        MMC_Population_Source_Service::disable_external_ui_bridge();
    }

    $needs_upgrade = get_option( 'mmc_db_version' ) !== MMC_DB_VERSION;
    if ( $needs_upgrade ) {
        MMC_Activator::install_or_upgrade();
        MMC_Activator::install_roles();
        if ( class_exists( 'MMC_Event_Service' ) ) {
            MMC_Event_Service::backfill_existing_confirmed_programs();
        }
        if ( class_exists( 'MMC_Operations_Service' ) ) {
            MMC_Operations_Service::backfill_existing_programs();
        }
        if ( class_exists( 'MMC_Finance_Service' ) ) {
            MMC_Finance_Service::backfill_existing_programs();
        }
        if ( class_exists( 'MMC_MDG_Bridge_Service' ) ) {
            MMC_MDG_Bridge_Service::backfill_existing_programs();
        }
    }

    // MEB 2024/25 il geneli okul/öğrenci verisi eklentiyle birlikte gelir.
    // CSV yüklemeye gerek olmadan Veri Ambarına idempotent olarak senkronlanır.
    if ( class_exists( 'MMC_MEB_Source_Service' ) ) {
        MMC_MEB_Source_Service::maybe_sync();
    }

    // Eski programlarda "efeler" gibi küçük harfli il/ilçe adlarını tek biçime getir.
    if ( class_exists( 'MMC_Region_Service' ) && get_option( 'mmc_place_normalization_version' ) !== MMC_VERSION ) {
        global $wpdb;
        $programs_table = $wpdb->prefix . 'mmc_programs';
        $targets_table  = $wpdb->prefix . 'mmc_program_target_districts';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $programs_table ) ) === $programs_table ) {
            $rows = $wpdb->get_results( "SELECT id, province_name, district_name FROM {$programs_table}" );
            foreach ( (array) $rows as $row ) {
                $province = MMC_Region_Service::normalize_place_name( $row->province_name );
                $district = MMC_Region_Service::normalize_place_name( $row->district_name );
                if ( $province !== $row->province_name || $district !== $row->district_name ) {
                    $wpdb->update(
                        $programs_table,
                        array( 'province_name'=>$province, 'district_name'=>$district, 'updated_at'=>current_time('mysql') ),
                        array( 'id'=>(int)$row->id ),
                        array( '%s','%s','%s' ),
                        array( '%d' )
                    );
                }
            }
        }

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $targets_table ) ) === $targets_table ) {
            $rows = $wpdb->get_results( "SELECT id, province_name, district_name FROM {$targets_table}" );
            foreach ( (array) $rows as $row ) {
                $province = MMC_Region_Service::normalize_place_name( $row->province_name );
                $district = MMC_Region_Service::normalize_place_name( $row->district_name );
                if ( $province !== $row->province_name || $district !== $row->district_name ) {
                    $wpdb->update(
                        $targets_table,
                        array( 'province_name'=>$province, 'district_name'=>$district ),
                        array( 'id'=>(int)$row->id ),
                        array( '%s','%s' ),
                        array( '%d' )
                    );
                }
            }
        }

        if ( class_exists( 'MMC_School_Source_Service' ) ) {
            MMC_School_Source_Service::refresh_detection();
        }
        update_option( 'mmc_place_normalization_version', MMC_VERSION, false );
    }

    if ( is_admin() ) {
        new MMC_Admin();
        new MMC_Venue_Admin();
        new MMC_Event_Admin();
        new MMC_Sales_Admin();
        new MMC_Kommo_Admin();
        new MMC_Marketing_Admin();
        new MMC_Field_Admin();
        new MMC_Operations_Admin();
        new MMC_Finance_Admin();
        new MMC_Report_Admin();
        new MMC_Integrity_Admin();
        new MMC_Navigation_Admin();
    }
} );

MMC_Sales_Service::hooks();
MMC_Kommo_Service::hooks();
MMC_Marketing_Service::hooks();
MMC_Field_Service::hooks();
MMC_Operations_Service::hooks();
MMC_Finance_Service::hooks();
MMC_Report_Service::hooks();
