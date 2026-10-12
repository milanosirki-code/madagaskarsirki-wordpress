<?php
/**
 * Plugin Name: Dünya Organizasyon - Marka Sahipliği Kontrolü (Geliştirme)
 * Description: MDG etkinliklerinin marka sahipliğini tek kaynaktan okur; Madagaskar alan adında yanlış markalı biletin satın alınmasını engeller.
 * Version: 0.1.0
 * Requires PHP: 7.4
 *
 * GITHUB DRAFT ONLY. DO NOT INSTALL/ACTIVATE ON THE LIVE SITE UNTIL ALL
 * EXISTING MDG EVENTS HAVE VERIFIED BRAND ASSIGNMENTS AND TESTS PASS.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class Dunya_Event_Brand_Guard {
    const OPTION = 'dunya_event_owner_brand_map_v1';

    public static function hooks() {
        add_filter( 'dunya_milano_canonical_event_owner', array( __CLASS__, 'owner_filter' ), 10, 2 );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 1, 6 );
        add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart' ), 1 );
        add_action( 'woocommerce_store_api_validate_cart_item', array( __CLASS__, 'validate_store_cart_item' ), 1, 2 );
        add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_cart' ), 1 );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'validate_order' ), 1, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'validate_store_order' ), 1 );
        // Native MDG public URLs bypass the_content(); check before its
        // template_redirect priority=0 handler runs.
        add_action( 'template_redirect', array( __CLASS__, 'protect_mdg_event_url' ), -1 );
    }

    /**
     * Only an explicitly approved event-ID mapping is authoritative.
     * No fallback based on titles, venues, referrers, domains or old labels.
     * The map is read-only in this plugin; assignments need separate review.
     */
    public static function owner_filter( $owner, $event_id ) {
        $id = absint( $event_id );
        if ( ! $id ) { return null; }
        $map = get_option( self::OPTION, array() );
        if ( ! is_array( $map ) ) { return null; }
        $brand = isset( $map[ (string) $id ] ) ? $map[ (string) $id ] : null;
        return in_array( $brand, array( 'milano', 'madagaskar' ), true ) ? $brand : null;
    }

    public static function owner( $event_id ) {
        return self::owner_filter( null, $event_id );
    }

    /**
     * Return null for unrelated products, 0 for ambiguous/incomplete native
     * MDG mapping, or a positive event ID for a canonical MDG ticket.
     */
    public static function resolve_mdg_event( $product_id, $variation_id = 0 ) {
        if ( ! class_exists( 'MDG_DB' ) ) { return null; }
        global $wpdb;
        $product_id = absint( $product_id );
        $variation_id = absint( $variation_id );
        if ( ! $product_id && ! $variation_id ) { return null; }

        $events = array();
        if ( $variation_id ) {
            $s = MDG_DB::table( 'sessions' );
            $t = MDG_DB::table( 'ticket_types' );
            $ids = (array) $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT s.event_id FROM {$t} t
                 INNER JOIN {$s} s ON s.id=t.session_id
                 WHERE t.wc_variation_id=%d LIMIT 3",
                $variation_id
            ) );
            foreach ( $ids as $id ) { $events[(int) $id] = true; }
        }
        // Parent product check prevents choosing an unrecognized variation
        // on a known native MDG product to bypass event ownership.
        if ( $product_id ) {
            $s = MDG_DB::table( 'sessions' );
            $ids = (array) $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT event_id FROM {$s} WHERE wc_product_id=%d LIMIT 3",
                $product_id
            ) );
            foreach ( $ids as $id ) { $events[(int) $id] = true; }
        }
        if ( ! $events ) { return null; }
        if ( count( $events ) !== 1 ) { return 0; }
        $event_id = (int) array_key_first( $events );
        return $event_id > 0 ? $event_id : 0;
    }

    public static function may_sell_on_madagaskar( $event_id ) {
        return is_int( $event_id ) && $event_id > 0 && 'madagaskar' === self::owner( $event_id );
    }

    public static function is_denied_product( $product_id, $variation_id = 0 ) {
        $event = self::resolve_mdg_event( $product_id, $variation_id );
        return null !== $event && ! self::may_sell_on_madagaskar( $event );
    }

    public static function validate_add_to_cart( $passed, $product_id, $qty, $variation_id = 0, $variations = array(), $cart_data = array() ) {
        if ( ! $passed ) { return false; }
        if ( self::is_denied_product( $product_id, $variation_id ) ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( 'Bu etkinlik bu markanın sitesinde satışa açık değil.', 'error' );
            }
            return false;
        }
        return true;
    }

    public static function validate_cart() {
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return; }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( self::is_denied_product( $item['product_id'] ?? 0, $item['variation_id'] ?? 0 ) ) {
                wc_add_notice( 'Sepetinizde bu markadan satılamayan bir etkinlik var.', 'error' );
                return;
            }
        }
    }

    public static function validate_store_cart_item( $product, $item ) {
        if ( self::is_denied_product( $item['product_id'] ?? 0, $item['variation_id'] ?? 0 ) ) {
            self::throw_checkout_error();
        }
    }

    public static function validate_order( $order_id, $posted_data = array(), $order = null ) {
        if ( ! $order && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
        }
        self::validate_store_order( $order );
    }

    public static function validate_store_order( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) { return; }
        foreach ( (array) $order->get_items( 'line_item' ) as $item ) {
            if ( self::is_denied_product( $item->get_product_id(), $item->get_variation_id() ) ) {
                self::throw_checkout_error();
            }
        }
    }

    private static function throw_checkout_error() {
        $message = 'Bu etkinlik farklı bir markaya ait veya marka doğrulaması tamamlanmadı.';
        if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'dunya_wrong_brand', $message, 403 );
        }
        throw new Exception( $message );
    }

    public static function protect_mdg_event_url() {
        if ( ! function_exists( 'get_query_var' ) || ! class_exists( 'MDG_Events' ) ) { return; }
        $slug = sanitize_title( (string) get_query_var( 'mdg_event_slug' ) );
        if ( ! $slug ) { return; }
        $event = MDG_Events::get_by_public_slug( $slug );
        if ( $event && ! self::may_sell_on_madagaskar( (int) $event->id ) ) {
            status_header( 404 );
            nocache_headers();
            exit( 'Bu etkinlik bu markada satışta değildir.' );
        }
    }
}
Dunya_Event_Brand_Guard::hooks();
