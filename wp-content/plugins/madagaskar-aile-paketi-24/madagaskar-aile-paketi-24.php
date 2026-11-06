<?php
/**
 * Plugin Name: Madagaskar Aile Paketi 2
 * Description: 2 yetişkin + 4 çocuk sanal paketi; mevcut 2+2 paketini korur. Altı gerçek bilet, altı kapasite birimi.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: Dünya Organizasyon
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/pricing.php';

final class MDG_Family_Package_24 {
    const OPTION = 'mdg_family_package_24_v1';
    const CODE = 'MDG_FAMILY_2_4';
    const LABEL = 'Aile Paketi 2 (2 Yetişkin + 4 Çocuk)';
    private static $context = null;

    public static function boot() {
        if ( ! class_exists( 'MDG_Family_Package_22_V1' ) ) { return; }
        // Run before 2+2. Only requests containing 2+4 are adapted here. The old
        // handler then resets its context and sees only native child/adult lines.
        foreach ( array( 'wp_ajax_mdg_live_add_to_cart', 'wp_ajax_nopriv_mdg_live_add_to_cart', 'wp_ajax_mdg_preview_add_to_cart' ) as $hook ) {
            add_action( $hook, array( __CLASS__, 'preprocess' ), 0 );
        }
        add_action( 'wp_footer', array( __CLASS__, 'public_row' ), 6 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'mark' ), 60, 4 );
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'fees' ), 21 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 21, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line' ), 21, 4 );
        add_action( 'woocommerce_checkout_create_order_fee_item', array( __CLASS__, 'order_fee' ), 21, 4 );
        foreach ( array( 'woocommerce_payment_complete', 'woocommerce_order_status_changed', 'woocommerce_order_refunded' ) as $hook ) {
            add_action( $hook, array( __CLASS__, 'reconcile' ), 80, 1 );
        }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 100 );
        add_action( 'admin_post_mdg_family24_save', array( __CLASS__, 'save' ) );
        if ( 'staging-f321-milanosirki-lgajf.wpcomstaging.com' === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
            add_action( 'rest_api_init', array( __CLASS__, 'audit_route' ) );
        }
    }

    private static function settings() {
        $saved = get_option( self::OPTION, array() );
        return is_array( $saved ) && isset( $saved['events'] ) && is_array( $saved['events'] ) ? $saved['events'] : array();
    }

    /** No city-name inference or automatic opt-in: each event has an explicit price. */
    public static function offer( $event_id ) {
        $prices = self::settings();
        $event_id = absint( $event_id );
        $price = round( (float) ( $prices[ $event_id ] ?? 0 ), 2 );
        if ( $price <= 0 || ! class_exists( 'MDG_Events' ) || ! class_exists( 'MMC_MDG_Bridge_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) { return null; }
        $event = MDG_Events::get( $event_id );
        $program_id = MMC_MDG_Bridge_Service::program_for_mdg_event( $event_id );
        $program = $program_id ? MMC_Program_Service::get_program( $program_id ) : null;
        if ( ! $event || ! $program || 'Madagaskar Sirki' !== (string) $program->brand
            || ! in_array( (string) $event->status, array( 'draft', 'onsale' ), true )
            || ( 'onsale' === (string) $event->status && 'sales_open' !== (string) $program->status ) ) { return null; }
        // The established 2+2 event allowlist remains a prerequisite.
        $old = get_option( 'mdg_family_package_22_v1', array() );
        $enabled = (array) ( $old['events'] ?? array() );
        $is_list = array_keys( $enabled ) === range( 0, count( $enabled ) - 1 );
        if ( ! in_array( $event_id, array_map( 'absint', $is_list ? $enabled : array_keys( array_filter( $enabled ) ) ), true ) ) { return null; }
        return array( 'code' => 'family_2_4', 'name' => self::LABEL, 'price' => $price, 'capacity_units' => 6, 'adults' => 2, 'children' => 4 );
    }

    private static function family22_price( $event_id ) {
        $program_id = MMC_MDG_Bridge_Service::program_for_mdg_event( $event_id );
        $event = $program_id && class_exists( 'MMC_Event_Service' ) ? MMC_Event_Service::event_for_program( $program_id ) : null;
        foreach ( $event ? (array) MMC_Event_Service::ticket_types( (int) $event->id ) : array() as $ticket ) {
            if ( 'family_2_2' === (string) $ticket->ticket_code && 1 === (int) $ticket->is_active && (float) $ticket->price > 0 ) { return round( (float) $ticket->price, 2 ); }
        }
        $settings = get_option( 'mdg_family_package_22_v1', array() );
        return round( (float) ( $settings['event_prices'][ $event_id ] ?? $settings['price'] ?? 1100 ), 2 );
    }

    /** Return only one mapped native type per role, all consuming one person. */
    private static function types( $event_id, $session_id ) {
        global $wpdb;
        $session = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE id=%d AND event_id=%d', $session_id, $event_id ) );
        if ( ! $session ) { throw new RuntimeException( 'Seans ve etkinlik eşleşmiyor.' ); }
        $sessions = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE event_id=%d', $event_id ) );
        $multi = $sessions ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MDG_DB::table( 'ticket_types' ) . ' WHERE is_active=1 AND capacity_units>1 AND session_id IN (' . implode( ',', array_map( 'absint', $sessions ) ) . ')' ) : 1;
        if ( $multi ) { throw new RuntimeException( 'Aile paketleri sanal seçenektir; gerçek çok kişilik bilet türü ile birlikte kullanılamaz.' ); }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'ticket_types' ) . ' WHERE session_id=%d AND is_active=1', $session_id ) );
        $types = array();
        foreach ( $rows as $row ) {
            $text = strtolower( remove_accents( $row->label . ' ' . $row->code ) );
            $role = ( false !== strpos( $text, 'cocuk' ) || false !== strpos( $text, 'child' ) ) ? 'child' : ( ( false !== strpos( $text, 'yetiskin' ) || false !== strpos( $text, 'adult' ) ) ? 'adult' : '' );
            if ( ! $role && in_array( strtoupper( $row->code ), array( 'C', 'Y' ), true ) ) { $role = 'C' === strtoupper( $row->code ) ? 'child' : 'adult'; }
            if ( ! $role ) { continue; }
            if ( isset( $types[ $role ] ) || 1 !== (int) $row->capacity_units || ! (int) $row->wc_variation_id ) { throw new RuntimeException( 'Tekil çocuk/yetişkin bilet eşleşmesi hazır değil.' ); }
            $types[ $role ] = $row;
        }
        if ( count( $types ) !== 2 ) { throw new RuntimeException( 'Çocuk ve yetişkin biletleri gerekli.' ); }
        return $types;
    }

    public static function preprocess() {
        self::$context = null;
        $raw = wp_unslash( $_POST['lines'] ?? '' );
        if ( ! is_string( $raw ) || strlen( $raw ) > 12000 ) { return; }
        $lines = json_decode( $raw, true );
        if ( ! is_array( $lines ) ) { return; }
        $has24 = false;
        foreach ( $lines as $line ) {
            if ( is_array( $line ) && isset( $line['code'] ) && is_string( $line['code'] ) && self::CODE === strtoupper( $line['code'] ) ) { $has24 = true; }
        }
        if ( ! $has24 ) { return; }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        $session_id = absint( $_POST['session_id'] ?? 0 );
        try {
            $offer = self::offer( $event_id );
            if ( ! $offer ) { throw new RuntimeException( 'Aile Paketi 2 bu etkinlikte satışa açık değil.' ); }
            $types = self::types( $event_id, $session_id );
            $selection = MDG_Family24_Pricing::expand( $lines, strtoupper( $types['child']->code ), strtoupper( $types['adult']->code ) );
            $packages = array(
                'family_2_4' => array( 'qty' => $selection['packages']['family_2_4'], 'price' => $offer['price'] ),
                'family_2_2' => array( 'qty' => $selection['packages']['family_2_2'], 'price' => self::family22_price( $event_id ) ),
            );
            foreach ( array( 'family_2_4' => 4, 'family_2_2' => 2 ) as $code => $children ) {
                if ( $packages[ $code ]['qty'] && ( $packages[ $code ]['price'] <= 0 || $packages[ $code ]['price'] > round( $children * $types['child']->price + 2 * $types['adult']->price, 2 ) ) ) { throw new RuntimeException( 'Paket fiyatı normal bilet toplamına uygun değil.' ); }
            }
            self::$context = array( 'event' => $event_id, 'session' => $session_id, 'types' => $types, 'packages' => $packages, 'group' => wp_generate_uuid4() );
            $_POST['lines'] = wp_json_encode( $selection['lines'] );
        } catch ( Throwable $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ), 409 );
        }
        // Native MDG still checks its nonce, sale status, session, Woo mapping,
        // exact current prices, stock and total capacity before any cart mutation.
    }

    public static function mark( $data, $product_id, $variation_id, $quantity ) {
        $ctx = self::$context;
        if ( ! $ctx || (int) ( $data['mdg_event_id'] ?? 0 ) !== $ctx['event'] || (int) ( $data['mdg_session_id'] ?? 0 ) !== $ctx['session'] ) { return $data; }
        foreach ( $ctx['types'] as $role => $type ) {
            if ( (int) ( $data['mdg_ticket_type_id'] ?? 0 ) !== (int) $type->id || (int) $variation_id !== (int) $type->wc_variation_id ) { continue; }
            $data['mdg_family24'] = array( 'group' => $ctx['group'], 'role' => $role, 'packages' => $ctx['packages'], 'event' => $ctx['event'], 'session' => $ctx['session'] );
        }
        return $data;
    }

    public static function groups( $cart ) {
        $groups = array();
        foreach ( $cart->get_cart() as $item ) {
            $meta = $item['mdg_family24'] ?? null;
            if ( ! is_array( $meta ) || empty( $meta['group'] ) || ! in_array( $meta['role'] ?? '', array( 'child', 'adult' ), true ) ) { continue; }
            $key = $meta['group'];
            if ( ! isset( $groups[ $key ] ) ) { $groups[ $key ] = array( 'packages' => $meta['packages'], 'child' => 0, 'adult' => 0, 'prices' => array(), 'valid' => true ); }
            $price = (float) $item['data']->get_price();
            $g =& $groups[ $key ];
            if ( isset( $g['prices'][ $meta['role'] ] ) && abs( $g['prices'][ $meta['role'] ] - $price ) > 0.009 ) { $g['valid'] = false; }
            $g['prices'][ $meta['role'] ] = $price;
            $g[ $meta['role'] ] += max( 0, (int) $item['quantity'] );
            unset( $g );
        }
        return $groups;
    }

    public static function fees( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
        foreach ( self::groups( $cart ) as $key => $group ) {
            if ( ! $group['valid'] || count( $group['prices'] ) !== 2 ) { continue; }
            $result = MDG_Family24_Pricing::discount( $group['packages'], $group['child'], $group['adult'], $group['prices']['child'], $group['prices']['adult'] );
            if ( $result['amount'] > 0 ) {
                $cart->fees_api()->add_fee( array( 'id' => 'mdg-family24-' . $key, 'name' => 'Aile Paketi 2 / Aile Paketleri İndirimi', 'amount' => -$result['amount'], 'taxable' => false ) );
            }
        }
    }

    public static function item_data( $data, $item ) {
        $meta = $item['mdg_family24'] ?? null;
        if ( is_array( $meta ) && 'child' === $meta['role'] ) {
            $data[] = array( 'key' => 'Aile paketi seçimi', 'value' => sprintf( '%d × 2 yetişkin + 4 çocuk; %d × 2 yetişkin + 2 çocuk. İndirim tam paket adedine göre hesaplanır.', $meta['packages']['family_2_4']['qty'], $meta['packages']['family_2_2']['qty'] ) );
        }
        return $data;
    }

    public static function order_line( $item, $key, $values, $order ) {
        if ( ! empty( $values['mdg_family24'] ) ) { $item->add_meta_data( '_mdg_family24', $values['mdg_family24'], true ); }
    }

    public static function order_fee( $item, $key, $fee, $order ) {
        if ( 0 === strpos( (string) $key, 'mdg-family24-' ) ) { $item->add_meta_data( '_mdg_family24_group', substr( $key, 13 ), true ); }
    }

    /** Run after the existing MMC and MDG ledger owners; never modify order/ticket data. */
    public static function reconcile( $order_id ) {
        $order = wc_get_order( absint( $order_id ) );
        if ( ! $order ) { return; }
        $discounts = array();
        foreach ( $order->get_items( 'fee' ) as $fee ) {
            $group = (string) $fee->get_meta( '_mdg_family24_group', true );
            if ( $group ) { $discounts[ $group ] = ( $discounts[ $group ] ?? 0 ) + max( 0, -( (float) $fee->get_total() + (float) $fee->get_total_tax() ) ); }
        }
        $groups = array();
        foreach ( $order->get_items( 'line_item' ) as $id => $item ) {
            $meta = $item->get_meta( '_mdg_family24', true );
            if ( is_array( $meta ) && ! empty( $meta['group'] ) ) { $groups[ $meta['group'] ][ $id ] = $item; }
        }
        global $wpdb;
        foreach ( $groups as $group => $items ) {
            $gross = array();
            foreach ( $items as $id => $item ) { $gross[ $id ] = (float) $item->get_total() + (float) $item->get_total_tax(); }
            $net = MDG_Family24_Pricing::allocate( $gross, $discounts[ $group ] ?? 0 );
            foreach ( $net as $id => $amount ) {
                $refund = min( $amount, abs( (float) $order->get_total_refunded_for_item( $id ) ) + abs( (float) $order->get_tax_refunded_for_item( $id, 'line_item' ) ) );
                $wpdb->update( $wpdb->prefix . 'mmc_sales_ledger', array( 'gross_amount' => $amount, 'refunded_amount' => round( $refund, 2 ), 'net_amount' => $order->get_date_paid() ? round( $amount - $refund, 2 ) : 0, 'updated_at' => current_time( 'mysql' ) ), array( 'channel' => 'woocommerce', 'external_order_item_id' => (int) $id, 'external_order_id' => (string) $order->get_id() ) );
                $wpdb->update( $wpdb->prefix . 'mdg_order_map', array( 'line_total' => $amount, 'updated_at' => current_time( 'mysql' ) ), array( 'order_item_id' => (int) $id, 'order_id' => (int) $order->get_id() ) );
            }
        }
    }

    public static function public_row() {
        $offers = array();
        foreach ( self::settings() as $id => $price ) { $offer = self::offer( $id ); if ( $offer ) { $offers[ $id ] = $offer; } }
        if ( ! $offers ) { return; }
        echo '<script id="mdg-family24-config">window.MDG_FAMILY24_OFFERS=' . wp_json_encode( $offers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';</script>';
        echo '<script src="' . esc_url( plugins_url( 'public-row.js', __FILE__ ) ) . '?ver=0.1.0"></script>';
    }

    public static function menu() {
        add_management_page( 'Aile Paketi 2', 'Madagaskar Aile Paketi 2', 'manage_woocommerce', 'mdg-family-package-24', array( __CLASS__, 'admin' ) );
    }

    public static function admin() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $events = self::settings();
        echo '<div class="wrap"><h1>Aile Paketi 2 — 2 Yetişkin + 4 Çocuk</h1><p>Mevcut 2+2 paketi korunur. Paket altı gerçek bilet ve altı kapasite birimi kullanır. Yeni programlar otomatik etkinleştirilmez. İzmir merkez 1.500 TL; diğer programlar 1.250 TL.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mdg_family24_save">';
        wp_nonce_field( 'mdg_family24_save' );
        echo '<p>Her satır MDG etkinlik numarası ve fiyat: <code>10=1500</code>. Bir satırı kaldırmak yalnız yeni paketi kapatır.</p><textarea name="events" rows="14" cols="50">';
        foreach ( $events as $id => $price ) { echo esc_textarea( $id . '=' . $price . "\n" ); }
        echo '</textarea>';
        submit_button( 'Paket ayarlarını kaydet' );
        echo '</form></div>';
    }

    public static function save() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_family24_save' );
        $raw = wp_unslash( $_POST['events'] ?? '' );
        if ( ! is_string( $raw ) || strlen( $raw ) > 12000 ) { wp_die( 'Geçersiz ayar.' ); }
        $events = array();
        foreach ( preg_split( '/\R/', trim( $raw ) ) as $line ) {
            if ( '' === trim( $line ) ) { continue; }
            if ( ! preg_match( '/^([1-9][0-9]*)\s*=\s*([1-9][0-9]*(?:[.,][0-9]{1,2})?)$/D', trim( $line ), $m ) ) { wp_die( 'Her satır etkinlik=fiyat biçiminde olmalıdır.' ); }
            $events[ (int) $m[1] ] = round( (float) str_replace( ',', '.', $m[2] ), 2 );
        }
        update_option( self::OPTION, array( 'events' => $events ), false );
        wp_safe_redirect( admin_url( 'tools.php?page=mdg-family-package-24' ) );
        exit;
    }

    /** Authorized, non-purchasing smoke check on the identified private staging only. */
    public static function audit_route() {
        register_rest_route( 'mdg-family24/v1', '/audit', array(
            'methods' => 'GET',
            'permission_callback' => static function() { return current_user_can( 'manage_woocommerce' ); },
            'callback' => static function( $request ) {
                $event_id = absint( $request->get_param( 'event_id' ) );
                $session_id = absint( $request->get_param( 'session_id' ) );
                $before = $_POST;
                try {
                    $offer = self::offer( $event_id );
                    if ( ! $offer ) { return new WP_Error( 'family24_disabled', 'Paket bu etkinlikte etkin değil.', array( 'status' => 409 ) ); }
                    $types = self::types( $event_id, $session_id );
                    $expanded = MDG_Family24_Pricing::expand( array( array( 'code' => self::CODE, 'qty' => 1 ) ), strtoupper( $types['child']->code ), strtoupper( $types['adult']->code ) );
                    $packages = array( 'family_2_4' => array( 'qty' => 1, 'price' => $offer['price'] ) );
                    $result = MDG_Family24_Pricing::discount( $packages, 4, 2, (float) $types['child']->price, (float) $types['adult']->price );
                    return array( 'event_id' => $event_id, 'session_id' => $session_id, 'offer' => $offer, 'native_lines' => $expanded['lines'], 'child_variation' => (int) $types['child']->wc_variation_id, 'adult_variation' => (int) $types['adult']->wc_variation_id, 'normal_total' => round( 4 * $types['child']->price + 2 * $types['adult']->price, 2 ), 'discount' => $result['amount'], 'requested_units' => 6, 'available_units' => MDG_Capacity::available( $session_id ), 'reservation' => false, 'order_created' => false, 'tickets_created' => false );
                } catch ( Throwable $e ) {
                    return new WP_Error( 'family24_audit', $e->getMessage(), array( 'status' => 409 ) );
                } finally { $_POST = $before; }
            },
        ) );
    }
}
add_action( 'plugins_loaded', array( 'MDG_Family_Package_24', 'boot' ), 40 );
