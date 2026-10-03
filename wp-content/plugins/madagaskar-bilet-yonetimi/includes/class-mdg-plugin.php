<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Plugin {
    private static $instance;
    public static function instance() {
        if ( ! self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    public function boot() {
        $admin = new MDG_Admin();
        $admin->hooks();
        if ( class_exists( 'MDG_Sales_Adopter' ) ) { MDG_Sales_Adopter::hooks(); }
        if ( class_exists( 'MDG_Sales_Cart_Test' ) ) { MDG_Sales_Cart_Test::hooks(); }
        if ( class_exists( 'MDG_Live_Sales' ) ) { MDG_Live_Sales::hooks(); }
        if ( class_exists( 'MDG_Checkout_UX' ) ) { MDG_Checkout_UX::hooks(); }
        if ( class_exists( 'MDG_Production_Publisher' ) ) { MDG_Production_Publisher::hooks(); }
        if ( class_exists( 'MDG_New_Event_Draft_Producer' ) ) { MDG_New_Event_Draft_Producer::hooks(); }
        if ( class_exists( 'MDG_Ticket_Venue_QR' ) ) { MDG_Ticket_Venue_QR::hooks(); }
        if ( class_exists( 'MDG_Ticket_Template_QR_Binder' ) ) { MDG_Ticket_Template_QR_Binder::hooks(); }
        MDG_Public_Event::hooks();
        if ( class_exists( 'MDG_Public_Cities' ) ) { MDG_Public_Cities::hooks(); }
        if ( class_exists( 'MDG_Public_Tickets' ) ) { MDG_Public_Tickets::hooks(); }
        if ( class_exists( 'MDG_Customer_Tickets' ) ) { MDG_Customer_Tickets::hooks(); }
        if ( class_exists( 'MDG_Refund_Preview' ) ) { MDG_Refund_Preview::hooks(); }
        add_action( 'mdg_cleanup_expired_holds', array( 'MDG_Capacity', 'cleanup_expired' ) );
        add_action( 'init', array( $this, 'ensure_scheduler' ), 30 );
        add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
    }

    public function ensure_scheduler() {
        if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_recurring_action' ) ) {
            if ( ! as_has_scheduled_action( 'mdg_cleanup_expired_holds', array(), 'madagaskar' ) ) {
                as_schedule_recurring_action( time() + 300, 300, 'mdg_cleanup_expired_holds', array(), 'madagaskar' );
            }
        }
    }

    public function dependency_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-warning"><p><strong>Madagaskar Bilet Yönetimi:</strong> WooCommerce aktif değil. Salonlar kullanılabilir; satış ve sipariş modülleri WooCommerce olmadan etkinleşmeyecek.</p></div>';
        }
        if ( ! post_type_exists( 'tc_events' ) ) {
            echo '<div class="notice notice-info"><p><strong>Madagaskar Bilet Yönetimi:</strong> Tickera etkinlik tipi henüz algılanmadı. Tickera entegrasyon modülü bağlanırken bu kontrol tekrar yapılacak.</p></div>';
        }
    }
}
