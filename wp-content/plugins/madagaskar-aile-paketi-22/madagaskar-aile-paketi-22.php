<?php
/**
 * Plugin Name: Madagaskar Aile Paketi 2+2
 * Description: Madagaskar Sirki için 2 yetişkin + 2 çocuk / 1.100 TL aile paketini, mevcut çocuk-yetişkin Tickera biletlerini kullanarak 4 ayrı QR ve 4 kişilik kapasite tüketimiyle güvenli biçimde uygular.
 * Version: 1.1.3
 * Author: Dünya Organizasyon
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Text Domain: madagaskar-aile-22
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Family_Package_22_V1 {

    const VERSION        = '1.1.3';
    const OPTION_KEY     = 'mdg_family_package_22_v1';
    const AJAX_CODE      = 'MDG_FAMILY_2_2';
    const DEFAULT_LABEL  = 'Aile Paketi 2+2';
    const DEFAULT_PRICE  = 1100.00;
    const DEFAULT_CHILD  = 2;
    const DEFAULT_ADULT  = 2;

    /** @var array|null Request-local context used while the MDG core adds cart lines. */
    private static $request_context = null;

    public static function boot() {
        // Admin.
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 99 );
        add_action( 'admin_post_mdg_family22_save', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_mdg_family22_retire_kirikkale', array( __CLASS__, 'retire_kirikkale_legacy' ) );
        add_action( 'admin_notices', array( __CLASS__, 'admin_notice_on_publish_page' ) );

        // Public/preview UI. MDG public-event.js is defer-loaded; this footer script
        // runs first and inserts the family row before the core JS scans ticket rows.
        add_action( 'wp_footer', array( __CLASS__, 'inject_public_row' ), 5 );

        // Pre-process only the virtual family line. The original MDG AJAX handler stays
        // intact and performs its own nonce, event, session, mapping, price and capacity checks.
        add_action( 'wp_ajax_mdg_live_add_to_cart', array( __CLASS__, 'preprocess_family_line' ), 1 );
        add_action( 'wp_ajax_nopriv_mdg_live_add_to_cart', array( __CLASS__, 'preprocess_family_line' ), 1 );
        add_action( 'wp_ajax_mdg_preview_add_to_cart', array( __CLASS__, 'preprocess_family_line' ), 1 );

        // Mark the child/adult component lines created by the existing MDG cart handler.
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'mark_family_components' ), 50, 4 );

        // 2 child + 2 adult = 1,500 TL at the standard prices. Keep the actual ticket
        // quantities/variations intact and apply only the package discount to reach 1,100 TL.
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_family_discount' ), 20, 1 );

        // Customer/admin clarity and durable order trace.
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_family_item_data' ), 20, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'store_order_line_meta' ), 20, 4 );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'store_order_meta' ), 20, 2 );
    }

    private static function defaults() {
        return array(
            'label'  => self::DEFAULT_LABEL,
            'price'  => self::DEFAULT_PRICE,
            'events' => array(),
            'event_prices' => array(),
        );
    }

    private static function settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) { $saved = array(); }
        $settings = wp_parse_args( $saved, self::defaults() );
        $settings['label'] = sanitize_text_field( (string) $settings['label'] );
        if ( '' === $settings['label'] ) { $settings['label'] = self::DEFAULT_LABEL; }
        $settings['price'] = round( max( 0, (float) $settings['price'] ), 2 );
        if ( $settings['price'] <= 0 ) { $settings['price'] = self::DEFAULT_PRICE; }
        if ( is_array( $settings['events'] ) ) {
            $event_keys = array_keys( array_filter( $settings['events'] ) );
            $is_list = ( array_keys( $settings['events'] ) === range( 0, count( $settings['events'] ) - 1 ) );
            $settings['events'] = array_values( array_filter( array_map( 'absint', $is_list ? $settings['events'] : $event_keys ) ) );
        } else { $settings['events'] = array(); }
        $event_prices = array();
        if ( ! empty( $settings['event_prices'] ) && is_array( $settings['event_prices'] ) ) {
            foreach ( $settings['event_prices'] as $event_id => $event_price ) {
                $event_id = absint( $event_id );
                $event_price = round( max( 0, (float) str_replace( ',', '.', (string) $event_price ) ), 2 );
                if ( $event_id && $event_price > 0 ) { $event_prices[ $event_id ] = $event_price; }
            }
        }
        foreach ( $settings['events'] as $event_id ) { if ( empty( $event_prices[ $event_id ] ) ) { $event_prices[ $event_id ] = $settings['price']; } }
        $settings['event_prices'] = $event_prices;
        return $settings;
    }

    /**
     * Tek fiyat kaynağı kuralı:
     * MMC'ye bağlı MDG etkinliğinde family_2_2 fiyatı varsa onu kullan.
     * Eski/bağsız etkinliklerde eklenti içindeki etkinlik fiyatı ve varsayılan fiyat yedektir.
     */
    private static function price_for_event( $event_id ) {
        $event_id = absint( $event_id );
        $mmc_price = self::mmc_family_price_for_event( $event_id );
        if ( $mmc_price > 0 ) { return $mmc_price; }

        $settings = self::settings();
        return ( $event_id && ! empty( $settings['event_prices'][ $event_id ] ) )
            ? round( (float) $settings['event_prices'][ $event_id ], 2 )
            : round( (float) $settings['price'], 2 );
    }

    /**
     * MDG event -> MMC program bridge -> MMC event -> family_2_2 ticket price.
     * No data is written; this is a read-only price lookup.
     */
    private static function mmc_family_price_for_event( $event_id ) {
        $event_id = absint( $event_id );
        if ( ! $event_id
            || ! class_exists( 'MMC_MDG_Bridge_Service' )
            || ! class_exists( 'MMC_Event_Service' )
            || ! method_exists( 'MMC_MDG_Bridge_Service', 'program_for_mdg_event' )
            || ! method_exists( 'MMC_Event_Service', 'event_for_program' )
            || ! method_exists( 'MMC_Event_Service', 'ticket_types' ) ) {
            return 0.0;
        }

        $program_id = absint( MMC_MDG_Bridge_Service::program_for_mdg_event( $event_id ) );
        if ( ! $program_id ) { return 0.0; }

        $mmc_event = MMC_Event_Service::event_for_program( $program_id );
        if ( ! $mmc_event || empty( $mmc_event->id ) ) { return 0.0; }

        foreach ( (array) MMC_Event_Service::ticket_types( (int) $mmc_event->id ) as $ticket ) {
            if ( 'family_2_2' !== (string) ( $ticket->ticket_code ?? '' ) ) { continue; }
            if ( isset( $ticket->is_active ) && 1 !== (int) $ticket->is_active ) { continue; }
            $price = round( max( 0, (float) ( $ticket->price ?? 0 ) ), 2 );
            if ( $price > 0 ) { return $price; }
        }

        return 0.0;
    }

    private static function event_enabled( $event_id ) {
        $event_id = absint( $event_id );
        if ( ! $event_id ) { return false; }
        $settings = self::settings();
        return in_array( $event_id, $settings['events'], true );
    }

    private static function mdg_ready() {
        return class_exists( 'MDG_DB' ) && class_exists( 'MDG_Events' );
    }

    private static function types_table() {
        return self::mdg_ready() ? MDG_DB::table( 'ticket_types' ) : '';
    }

    private static function sessions_table() {
        return self::mdg_ready() ? MDG_DB::table( 'sessions' ) : '';
    }

    private static function normalize_text( $text ) {
        $text = remove_accents( wp_strip_all_tags( (string) $text ) );
        $text = strtolower( $text );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
        return trim( (string) $text );
    }

    private static function classify_type( $type ) {
        if ( ! is_object( $type ) ) { return ''; }
        $label = self::normalize_text( isset( $type->label ) ? $type->label : '' );
        $code  = self::normalize_text( isset( $type->code ) ? $type->code : '' );
        $text  = trim( $label . ' ' . $code );

        if ( false !== strpos( $text, 'cocuk' ) || false !== strpos( $text, 'child' ) ) { return 'child'; }
        if ( false !== strpos( $text, 'yetiskin' ) || false !== strpos( $text, 'adult' ) ) { return 'adult'; }

        // Production plans have historically used C / Y suffixes. Accept exact short
        // codes as a fallback, but never broad substring matches.
        $raw_code = strtoupper( preg_replace( '/[^A-Z0-9_]/', '', remove_accents( (string) ( isset( $type->code ) ? $type->code : '' ) ) ) );
        if ( in_array( $raw_code, array( 'C', 'COCUK', 'CHILD' ), true ) ) { return 'child'; }
        if ( in_array( $raw_code, array( 'Y', 'YETISKIN', 'ADULT' ), true ) ) { return 'adult'; }

        return '';
    }

    /**
     * Return exactly one active child and one active adult type for a session.
     * Family-package safety intentionally fails closed if the catalogue is ambiguous.
     */
    private static function child_adult_types( $session_id, $require_mapping = true ) {
        global $wpdb;
        $session_id = absint( $session_id );
        if ( ! $session_id || ! self::mdg_ready() ) {
            return new WP_Error( 'mdg_family22_not_ready', 'Madagaskar bilet çekirdeği hazır değil.' );
        }

        $table = self::types_table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id=%d AND is_active=1 ORDER BY sort_order ASC, id ASC",
            $session_id
        ) );

        $child = array();
        $adult = array();
        foreach ( (array) $rows as $row ) {
            $kind = self::classify_type( $row );
            if ( 'child' === $kind ) { $child[] = $row; }
            if ( 'adult' === $kind ) { $adult[] = $row; }
        }

        if ( 1 !== count( $child ) || 1 !== count( $adult ) ) {
            return new WP_Error(
                'mdg_family22_catalogue',
                'Aile Paketi için her seansta tam olarak bir aktif Çocuk ve bir aktif Yetişkin bilet türü bulunmalıdır.'
            );
        }

        if ( $require_mapping ) {
            if ( ! (int) $child[0]->wc_variation_id || ! (int) $adult[0]->wc_variation_id ) {
                return new WP_Error( 'mdg_family22_mapping', 'Çocuk/Yetişkin WooCommerce varyasyon eşleşmesi henüz hazır değil.' );
            }
        }

        return array( 'child' => $child[0], 'adult' => $adult[0] );
    }

    private static function event_sessions( $event_id ) {
        global $wpdb;
        $event_id = absint( $event_id );
        if ( ! $event_id || ! self::mdg_ready() ) { return array(); }
        $table = self::sessions_table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_id=%d ORDER BY start_at ASC, id ASC",
            $event_id
        ) );
    }

    private static function event_has_multi_unit_type( $event_id ) {
        global $wpdb;
        $event_id = absint( $event_id );
        if ( ! $event_id || ! self::mdg_ready() ) { return false; }
        $sessions = self::event_sessions( $event_id );
        if ( ! $sessions ) { return false; }
        $ids = array_map( static function( $s ) { return absint( $s->id ); }, $sessions );
        $ids = array_values( array_filter( $ids ) );
        if ( ! $ids ) { return false; }
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = "SELECT COUNT(*) FROM " . self::types_table() . " WHERE is_active=1 AND capacity_units>1 AND session_id IN ({$placeholders})";
        $count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $ids ) );
        return $count > 0;
    }

    /* ---------------------------------------------------------------------
     * Admin
     * ------------------------------------------------------------------ */

    public static function admin_menu() {
        add_management_page(
            'Madagaskar Aile Paketi 2+2',
            'Madagaskar Aile Paketi',
            'manage_woocommerce',
            'mdg-family-package-22',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_family22_save_action', 'mdg_family22_nonce' );

        $label = sanitize_text_field( wp_unslash( $_POST['label'] ?? self::DEFAULT_LABEL ) );
        if ( '' === $label ) { $label = self::DEFAULT_LABEL; }

        $price_raw = isset( $_POST['price'] ) ? str_replace( ',', '.', (string) wp_unslash( $_POST['price'] ) ) : (string) self::DEFAULT_PRICE;
        $price = round( (float) $price_raw, 2 );
        if ( $price <= 0 ) { $price = self::DEFAULT_PRICE; }

        $events = array();
        foreach ( (array) ( $_POST['events'] ?? array() ) as $event_id ) {
            $event_id = absint( $event_id );
            if ( $event_id ) { $events[ $event_id ] = 1; }
        }

        $event_prices = array();
        $posted_prices = isset( $_POST['event_prices'] ) && is_array( $_POST['event_prices'] ) ? wp_unslash( $_POST['event_prices'] ) : array();
        foreach ( $events as $event_id => $active ) {
            // MMC bağlantılı etkinliklerde fiyatın tek kaynağı MMC'deki family_2_2 kaydıdır.
            // Burada ikinci bir fiyat kopyası saklamayız; MMC yoksa legacy manuel fiyatı koruruz.
            if ( self::mmc_family_price_for_event( $event_id ) > 0 ) { continue; }
            $raw = isset( $posted_prices[ $event_id ] ) ? str_replace( ',', '.', (string) $posted_prices[ $event_id ] ) : (string) $price;
            $event_price = round( (float) $raw, 2 );
            if ( $event_price <= 0 ) { $event_price = $price; }
            $event_prices[ $event_id ] = $event_price;
        }
        update_option( self::OPTION_KEY, array(
            'label' => $label, 'price' => $price, 'events' => $events, 'event_prices' => $event_prices,
        ), false );

        wp_safe_redirect( add_query_arg( array(
            'page'      => 'mdg-family-package-22',
            'mdg_saved' => 1,
        ), admin_url( 'tools.php' ) ) );
        exit;
    }

    /** Disable only Kırıkkale's old multi-unit family types, preserving rows and orders. */
    public static function retire_kirikkale_legacy() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_family22_retire_kirikkale' );
        global $wpdb;
        $event_id = 15;
        if ( ! self::mdg_ready() || ! self::event_enabled( $event_id ) ) { wp_die( 'Etkinlik veya yeni aile paketi hazır değil.' ); }
        $event = $wpdb->get_row( $wpdb->prepare( 'SELECT id,title,district,status FROM ' . MDG_DB::table( 'events' ) . ' WHERE id=%d', $event_id ) );
        if ( ! $event || 'onsale' !== $event->status || false === strpos( self::normalize_text( $event->title . ' ' . $event->district ), 'kirikkale' ) ) {
            wp_die( 'Kırıkkale satış etkinliği doğrulanamadı.' );
        }
        $sessions = self::event_sessions( $event_id );
        if ( count( $sessions ) !== 2 ) { wp_die( 'Beklenen iki Kırıkkale seansı bulunamadı.' ); }
        $table = self::types_table();
        $ids = array();
        foreach ( $sessions as $session ) {
            if ( is_wp_error( self::child_adult_types( $session->id, true ) ) ) { wp_die( 'Çocuk ve yetişkin bilet eşleşmeleri hazır değil.' ); }
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,label,code,capacity_units FROM {$table} WHERE session_id=%d AND is_active=1 AND capacity_units>1", $session->id ) );
            if ( count( $rows ) !== 1 || false === strpos( self::normalize_text( $rows[0]->label . ' ' . $rows[0]->code ), 'aile' ) || (int) $rows[0]->capacity_units !== 4 ) {
                wp_die( 'Eski aile bileti her seansta tek ve dört kişilik olarak doğrulanamadı.' );
            }
            $ids[] = (int) $rows[0]->id;
        }
        $wpdb->query( 'START TRANSACTION' );
        foreach ( $ids as $id ) {
            if ( 1 !== $wpdb->update( $table, array( 'is_active' => 0 ), array( 'id' => $id, 'is_active' => 1 ), array( '%d' ), array( '%d', '%d' ) ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_die( 'Eski biletler pasifleştirilemedi; işlem geri alındı.' );
            }
        }
        $wpdb->query( 'COMMIT' );
        wp_safe_redirect( add_query_arg( array( 'page' => 'mdg-family-package-22', 'mdg_kirikkale_retired' => 1 ), admin_url( 'tools.php' ) ) );
        exit;
    }

    private static function admin_events() {
        global $wpdb;
        if ( ! self::mdg_ready() ) { return array(); }
        $table = MDG_DB::table( 'events' );
        return $wpdb->get_results(
            "SELECT id,title,province_name,district,status,updated_at FROM {$table} WHERE status IN ('draft','onsale') ORDER BY id DESC LIMIT 100"
        );
    }

    private static function readiness_for_event( $event_id ) {
        $event_id = absint( $event_id );
        if ( self::event_has_multi_unit_type( $event_id ) ) {
            return array( 'ok' => false, 'text' => '6. Bilet Türleri bölümünde kapasite tüketimi 1’den büyük aktif satır var. Aile paketini oraya eklemeyin; bu modül sanal paket olarak üretir.' );
        }

        $sessions = self::event_sessions( $event_id );
        if ( ! $sessions ) { return array( 'ok' => false, 'text' => 'Seans bulunamadı.' ); }

        $mapped = true;
        foreach ( $sessions as $session ) {
            $types = self::child_adult_types( (int) $session->id, false );
            if ( is_wp_error( $types ) ) { return array( 'ok' => false, 'text' => $types->get_error_message() ); }
            if ( ! (int) $types['child']->wc_variation_id || ! (int) $types['adult']->wc_variation_id || ! (int) $session->wc_product_id ) {
                $mapped = false;
            }
        }

        if ( ! $mapped ) {
            return array( 'ok' => true, 'text' => 'Katalog uygun. Önce normal Çocuk/Yetişkin taslak satış nesnelerini üretin; mapping oluşunca paket önizlemede çalışır.' );
        }

        return array( 'ok' => true, 'text' => 'HAZIR — Paket 1 adet seçildiğinde 2 Çocuk + 2 Yetişkin gerçek bilet satırı oluşturur.' );
    }

    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $settings = self::settings();
        $events = self::admin_events();
        $enabled = array_fill_keys( $settings['events'], true );

        echo '<div class="wrap">';
        echo '<h1>🎪 Madagaskar – Aile Paketi 2+2</h1>';

        if ( ! self::mdg_ready() ) {
            echo '<div class="notice notice-error"><p><strong>Madagaskar Bilet Yönetimi çekirdeği algılanamadı.</strong> Bu modülü kullanmayın.</p></div></div>';
            return;
        }

        if ( ! empty( $_GET['mdg_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Aile Paketi ayarları kaydedildi.</strong></p></div>';
        }
        if ( ! empty( $_GET['mdg_kirikkale_retired'] ) ) {
            echo '<div class="notice notice-success"><p><strong>Kırıkkale eski aile biletleri iki seansta pasifleştirildi; kayıtlar ve eski siparişler korundu.</strong></p></div>';
        }

        echo '<div style="max-width:1100px;background:#fff;border:1px solid #dcdcde;padding:22px;margin-top:18px">';
        echo '<h2>Çalışma modeli</h2>';
        echo '<p><strong>1 Aile Paketi 2+2</strong> müşteriye tek seçenek olarak görünür; sepette mevcut MDG Çocuk ve Yetişkin varyasyonlarına <strong>2 + 2 adet</strong> dönüştürülür. Böylece Tickera dört ayrı gerçek QR üretir ve MDG kapasitesinden dört kişi düşer. Aile paketi için MDG “Bilet Türleri” bölümünde kapasite 4 olan üçüncü satır oluşturulmaz.</p>';
        echo '<p><strong>Standart fiyat hesabı:</strong> 2 × 250 TL çocuk + 2 × 500 TL yetişkin = 1.500 TL; paket indirimi 400 TL; tahsilat 1.100 TL.</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="mdg_family22_save">';
        wp_nonce_field( 'mdg_family22_save_action', 'mdg_family22_nonce' );

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="mdg-family22-label">Paket adı</label></th><td><input id="mdg-family22-label" name="label" type="text" class="regular-text" value="' . esc_attr( $settings['label'] ) . '"></td></tr>';
        echo '<tr><th scope="row"><label for="mdg-family22-price">Varsayılan paket fiyatı</label></th><td><input id="mdg-family22-price" name="price" type="number" min="1" step="0.01" value="' . esc_attr( number_format( (float) $settings['price'], 2, '.', '' ) ) . '"> TL <p class="description">Yeni etkinliklerde başlangıç fiyatıdır. Her etkinliğin fiyatı aşağıda ayrı kaydedilir.</p></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Etkinliklerde etkinleştir</h2>';
        echo '<table class="widefat striped"><thead><tr><th style="width:80px">Aktif</th><th>Etkinlik</th><th style="width:150px">Paket fiyatı</th><th>Durum</th><th>Aile Paketi kontrolü</th></tr></thead><tbody>';
        if ( ! $events ) {
            echo '<tr><td colspan="5">Taslak veya satıştaki etkinlik bulunamadı.</td></tr>';
        } else {
            foreach ( $events as $event ) {
                $event_id = absint( $event->id );
                $ready = self::readiness_for_event( $event_id );
                $status_label = 'onsale' === (string) $event->status ? 'Satışta' : 'Taslak';
                $status_color = ! empty( $ready['ok'] ) ? '#008a20' : '#b32d2e';
                echo '<tr>';
                echo '<td><label><input type="checkbox" name="events[]" value="' . $event_id . '" ' . checked( isset( $enabled[ $event_id ] ), true, false ) . '> #' . $event_id . '</label></td>';
                echo '<td><strong>' . esc_html( $event->title ) . '</strong><br><small>' . esc_html( trim( (string) $event->province_name . ' / ' . (string) $event->district ) ) . '</small></td>';
                $mmc_price = self::mmc_family_price_for_event( $event_id );
                if ( $mmc_price > 0 ) {
                    echo '<td><strong>' . esc_html( number_format_i18n( $mmc_price, 2 ) ) . ' TL</strong><br><small style="color:#008a20">MMC program fiyatı · otomatik</small></td>';
                } else {
                    $event_price = isset( $settings['event_prices'][ $event_id ] ) ? (float) $settings['event_prices'][ $event_id ] : (float) $settings['price'];
                    echo '<td><input name="event_prices[' . $event_id . ']" type="number" min="1" step="0.01" style="width:110px" value="' . esc_attr( number_format( $event_price, 2, '.', '' ) ) . '"> TL<br><small>MMC bağlantısı yok · manuel</small></td>';
                }
                echo '<td><code>' . esc_html( $status_label ) . '</code></td>';
                echo '<td><span style="color:' . esc_attr( $status_color ) . ';font-weight:600">' . esc_html( $ready['text'] ) . '</span></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        submit_button( 'Aile Paketi Ayarlarını Kaydet' );
        echo '</form>';
        if ( self::event_enabled( 15 ) && self::event_has_multi_unit_type( 15 ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:18px;padding:14px;border:1px solid #d63638">';
            echo '<input type="hidden" name="action" value="mdg_family22_retire_kirikkale">';
            wp_nonce_field( 'mdg_family22_retire_kirikkale' );
            echo '<strong>Kırıkkale #15:</strong> İki seansta bulunan eski dört kişilik aile biletlerini pasifleştir. Çocuk/Yetişkin biletleri, ürünleri ve geçmiş siparişler korunur.';
            submit_button( 'Kırıkkale Eski Aile Biletlerini Pasifleştir', 'secondary', 'submit', false );
            echo '</form>';
        }
        echo '<hr style="margin:28px 0">';
        echo '<p><strong>Güvenlik sınırı:</strong> Bu eklenti Tickera biletlerini elle çoğaltmaz, mevcut ticket instance/QR kayıtlarını değiştirmez ve MDG üretim blokajını devre dışı bırakmaz. Dört QR, mevcut Çocuk ve Yetişkin varyasyonlarının gerçek WooCommerce miktarlarından Tickera tarafından normal akışta üretilir.</p>';
        echo '</div></div>';
    }

    public static function admin_notice_on_publish_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $page = sanitize_key( $_GET['page'] ?? '' );
        if ( 'mdg-publish' !== $page ) { return; }
        $event_id = absint( $_GET['edit'] ?? 0 );
        if ( ! $event_id || ! self::event_enabled( $event_id ) ) { return; }

        $msg = 'Aile Paketi 2+2 bu etkinlik için modül üzerinden etkin. 6. Bilet Türleri bölümüne ayrıca “Aile Paketi / kapasite 4” satırı eklemeyin; yalnız Çocuk ve Yetişkin biletleri üretim planında kalmalıdır.';
        $class = self::event_has_multi_unit_type( $event_id ) ? 'notice notice-error' : 'notice notice-info';
        echo '<div class="' . esc_attr( $class ) . '"><p><strong>Madagaskar Aile Paketi:</strong> ' . esc_html( $msg ) . '</p></div>';
    }

    /* ---------------------------------------------------------------------
     * Public UI
     * ------------------------------------------------------------------ */

    public static function inject_public_row() {
        $settings = self::settings();
        if ( empty( $settings['events'] ) ) { return; }

        $config = array(
            'events' => array_values( array_map( 'absint', $settings['events'] ) ),
            'code'   => self::AJAX_CODE,
            'label'  => $settings['label'],
            'price'  => (float) $settings['price'],
            'prices' => array_map( 'floatval', $settings['event_prices'] ),
            'units'  => 4,
        );
        ?>
<style id="mdg-family22-style-v1">
.mdg-ticket-option.mdg-family22-option > div:first-child span{display:block;margin-top:4px;font-size:12px;opacity:.72}
.mdg-ticket-option.mdg-family22-option{box-shadow:inset 3px 0 0 rgba(218,165,32,.8)}
</style>
<script id="mdg-family22-ui-v1">
(function(){
    'use strict';
    var cfg=<?php echo wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
    var sales=window.MDG_EVENT_SALES||{};
    var eventId=parseInt(sales.eventId||0,10)||0;
    if(!eventId||cfg.events.indexOf(eventId)===-1)return;
    cfg.price=parseFloat((cfg.prices&&cfg.prices[eventId])||cfg.price||0);
    var box=document.querySelector('[data-mdg-ticket-options]');
    if(!box||box.querySelector('[data-mdg-family22="1"]'))return;
    var row=document.createElement('div');
    row.className='mdg-ticket-option mdg-family22-option';
    row.setAttribute('data-mdg-family22','1');
    row.setAttribute('data-ticket-code',cfg.code);
    row.setAttribute('data-ticket-price',String(cfg.price));
    row.setAttribute('data-ticket-units','4');
    var priceText='';
    try{priceText=new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY',maximumFractionDigits:Number.isInteger(cfg.price)?0:2}).format(cfg.price);}catch(e){priceText=String(cfg.price)+' ₺';}
    row.innerHTML='<div><strong></strong><span>2 Yetişkin + 2 Çocuk · 4 ayrı giriş QR</span></div><div class="mdg-ticket-price"></div><div class="mdg-qty"><button type="button" data-minus aria-label="Azalt">−</button><input type="number" value="0" min="0" max="10" readonly autocomplete="off" inputmode="numeric"><button type="button" data-plus aria-label="Artır">+</button></div>';
    row.querySelector('strong').textContent=cfg.label;
    row.querySelector('.mdg-ticket-price').textContent=priceText;
    box.appendChild(row);
})();
</script>
        <?php
    }

    /* ---------------------------------------------------------------------
     * AJAX adapter: virtual package -> 2 child + 2 adult lines
     * ------------------------------------------------------------------ */

    public static function preprocess_family_line() {
        self::$request_context = null;

        $event_id = absint( $_POST['event_id'] ?? 0 );
        if ( ! $event_id || ! self::event_enabled( $event_id ) ) { return; }

        $session_id = absint( $_POST['session_id'] ?? 0 );
        $raw = isset( $_POST['lines'] ) ? wp_unslash( $_POST['lines'] ) : '';
        $lines = json_decode( (string) $raw, true );
        if ( ! $session_id || ! is_array( $lines ) ) { return; } // Core handler returns the canonical error.

        $family_qty = 0;
        $normal = array();
        foreach ( $lines as $line ) {
            if ( ! is_array( $line ) ) { continue; }
            $code = strtoupper( sanitize_key( $line['code'] ?? '' ) );
            $code = str_replace( '-', '_', $code );
            $qty = absint( $line['qty'] ?? 0 );
            if ( $code === self::AJAX_CODE ) {
                $family_qty += $qty;
                continue;
            }
            $normal[] = array( 'code' => (string) ( $line['code'] ?? '' ), 'qty' => $qty );
        }

        if ( $family_qty < 1 ) { return; }
        if ( $family_qty > 10 ) {
            wp_send_json_error( array( 'message' => 'Tek işlemde en fazla 10 Aile Paketi 2+2 seçilebilir.' ), 400 );
        }

        // Fail closed if somebody also added a real capacity_units>1 ticket type.
        // This module intentionally works only by decomposing into real child/adult tickets.
        if ( self::event_has_multi_unit_type( $event_id ) ) {
            wp_send_json_error( array(
                'message' => 'Aile Paketi modülü için etkinlikte kapasite tüketimi 4 olan ayrı bir bilet türü bulunmamalıdır. Yönetimde bu satırı kaldırın; yalnız Çocuk ve Yetişkin biletleri kalsın.'
            ), 409 );
        }

        $types = self::child_adult_types( $session_id, true );
        if ( is_wp_error( $types ) ) {
            wp_send_json_error( array( 'message' => $types->get_error_message() ), 409 );
        }

        $settings = self::settings();
        $base_total = 2 * (float) $types['child']->price + 2 * (float) $types['adult']->price;
        $package_price = self::price_for_event( $event_id );
        $discount_per = round( $base_total - $package_price, 2 );
        if ( $discount_per < 0 ) {
            wp_send_json_error( array( 'message' => 'Aile Paketi fiyatı iki çocuk + iki yetişkin normal toplamından yüksek. Paket ayarını kontrol edin.' ), 409 );
        }

        $qty_by_code = array();
        foreach ( $normal as $line ) {
            $code = strtoupper( sanitize_key( $line['code'] ?? '' ) );
            $code = str_replace( '-', '_', $code );
            $qty = absint( $line['qty'] ?? 0 );
            if ( ! $code || $qty < 1 ) { continue; }
            if ( ! isset( $qty_by_code[ $code ] ) ) { $qty_by_code[ $code ] = 0; }
            $qty_by_code[ $code ] += $qty;
        }

        $child_code = strtoupper( sanitize_key( (string) $types['child']->code ) );
        $child_code = str_replace( '-', '_', $child_code );
        $adult_code = strtoupper( sanitize_key( (string) $types['adult']->code ) );
        $adult_code = str_replace( '-', '_', $adult_code );

        if ( ! isset( $qty_by_code[ $child_code ] ) ) { $qty_by_code[ $child_code ] = 0; }
        if ( ! isset( $qty_by_code[ $adult_code ] ) ) { $qty_by_code[ $adult_code ] = 0; }
        $qty_by_code[ $child_code ] += self::DEFAULT_CHILD * $family_qty;
        $qty_by_code[ $adult_code ] += self::DEFAULT_ADULT * $family_qty;

        // The MDG core caps each real ticket type at 20 per request.
        if ( $qty_by_code[ $child_code ] > 20 || $qty_by_code[ $adult_code ] > 20 ) {
            wp_send_json_error( array( 'message' => 'Aile Paketi ve tekil biletlerin toplamı, tek işlemde bilet türü başına 20 adet sınırını aşıyor.' ), 400 );
        }

        $rewritten = array();
        foreach ( $qty_by_code as $code => $qty ) {
            if ( $qty > 0 ) { $rewritten[] = array( 'code' => $code, 'qty' => $qty ); }
        }

        self::$request_context = array(
            'event_id'       => $event_id,
            'session_id'     => $session_id,
            'family_qty'     => $family_qty,
            'child_type_id'  => (int) $types['child']->id,
            'adult_type_id'  => (int) $types['adult']->id,
            'discount_per'   => $discount_per,
            'package_price'  => $package_price,
            'label'          => (string) $settings['label'],
            'group_key'      => 'mdg-family22-' . $event_id . '-' . $session_id . '-' . wp_generate_uuid4(),
        );

        // Preserve the core security path: only the virtual line is rewritten; the
        // original MDG handler now validates the real child/adult codes and quantities.
        $_POST['lines'] = wp_json_encode( $rewritten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    public static function mark_family_components( $cart_item_data, $product_id, $variation_id, $quantity ) {
        if ( empty( self::$request_context ) || ! is_array( self::$request_context ) ) { return $cart_item_data; }
        $ctx = self::$request_context;
        $type_id = absint( $cart_item_data['mdg_ticket_type_id'] ?? 0 );
        if ( ! $type_id ) { return $cart_item_data; }

        $component = '';
        if ( $type_id === (int) $ctx['child_type_id'] ) { $component = 'child'; }
        if ( $type_id === (int) $ctx['adult_type_id'] ) { $component = 'adult'; }
        if ( ! $component ) { return $cart_item_data; }

        $cart_item_data['mdg_family22']             = 1;
        $cart_item_data['mdg_family22_group']       = sanitize_text_field( (string) $ctx['group_key'] );
        $cart_item_data['mdg_family22_component']   = $component;
        $cart_item_data['mdg_family22_packages']    = absint( $ctx['family_qty'] );
        $cart_item_data['mdg_family22_discount_per']= (float) $ctx['discount_per'];
        $cart_item_data['mdg_family22_package_price']= (float) $ctx['package_price'];
        $cart_item_data['mdg_family22_label']       = sanitize_text_field( (string) $ctx['label'] );
        $cart_item_data['mdg_family22_event_id']    = absint( $ctx['event_id'] );
        $cart_item_data['mdg_family22_session_id']  = absint( $ctx['session_id'] );

        return $cart_item_data;
    }

    /**
     * Compute effective package counts from actual cart quantities so a customer cannot
     * keep a full package discount after manually reducing one of the component lines.
     */
    private static function cart_family_groups( $cart ) {
        $groups = array();
        if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) { return $groups; }

        foreach ( $cart->get_cart() as $key => $item ) {
            if ( empty( $item['mdg_family22'] ) || empty( $item['mdg_family22_group'] ) ) { continue; }
            $group = sanitize_text_field( (string) $item['mdg_family22_group'] );
            if ( ! isset( $groups[ $group ] ) ) {
                $groups[ $group ] = array(
                    'declared'     => absint( $item['mdg_family22_packages'] ?? 0 ),
                    'child_qty'    => 0,
                    'adult_qty'    => 0,
                    'discount_per' => max( 0, (float) ( $item['mdg_family22_discount_per'] ?? 0 ) ),
                    'label'        => sanitize_text_field( (string) ( $item['mdg_family22_label'] ?? self::DEFAULT_LABEL ) ),
                );
            }
            $qty = max( 0, (int) ( $item['quantity'] ?? 0 ) );
            if ( 'child' === (string) ( $item['mdg_family22_component'] ?? '' ) ) { $groups[ $group ]['child_qty'] += $qty; }
            if ( 'adult' === (string) ( $item['mdg_family22_component'] ?? '' ) ) { $groups[ $group ]['adult_qty'] += $qty; }
        }

        return $groups;
    }

    public static function apply_family_discount( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
        if ( ! is_object( $cart ) || ! method_exists( $cart, 'add_fee' ) ) { return; }

        $groups = self::cart_family_groups( $cart );
        if ( ! $groups ) { return; }

        $discount = 0.0;
        $package_count = 0;
        foreach ( $groups as $group ) {
            $effective = min(
                absint( $group['declared'] ),
                (int) floor( max( 0, (int) $group['child_qty'] ) / self::DEFAULT_CHILD ),
                (int) floor( max( 0, (int) $group['adult_qty'] ) / self::DEFAULT_ADULT )
            );
            if ( $effective < 1 ) { continue; }
            $discount += $effective * max( 0, (float) $group['discount_per'] );
            $package_count += $effective;
        }

        $discount = round( $discount, wc_get_price_decimals() );
        if ( $discount <= 0 ) { return; }

        $label = self::DEFAULT_LABEL . ' İndirimi';
        if ( 1 !== $package_count ) { $label .= ' (' . $package_count . ' paket)'; }
        $cart->add_fee( $label, -1 * $discount, false );
    }

    public static function display_family_item_data( $data, $cart_item ) {
        if ( empty( $cart_item['mdg_family22'] ) || 'child' !== (string) ( $cart_item['mdg_family22_component'] ?? '' ) ) { return $data; }
        $count = absint( $cart_item['mdg_family22_packages'] ?? 0 );
        if ( $count < 1 ) { return $data; }
        $data[] = array(
            'key'   => sanitize_text_field( (string) ( $cart_item['mdg_family22_label'] ?? self::DEFAULT_LABEL ) ),
            'value' => $count . ' paket · paket başına 2 çocuk + 2 yetişkin',
        );
        return $data;
    }

    public static function store_order_line_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['mdg_family22'] ) ) { return; }
        $item->add_meta_data( '_mdg_family22', '1', true );
        $item->add_meta_data( '_mdg_family22_group', sanitize_text_field( (string) ( $values['mdg_family22_group'] ?? '' ) ), true );
        $item->add_meta_data( '_mdg_family22_component', sanitize_key( (string) ( $values['mdg_family22_component'] ?? '' ) ), true );
        $item->add_meta_data( '_mdg_family22_packages', absint( $values['mdg_family22_packages'] ?? 0 ), true );
        $item->add_meta_data( '_mdg_family22_package_price', (float) ( $values['mdg_family22_package_price'] ?? 0 ), true );
        $item->add_meta_data( '_mdg_family22_event_id', absint( $values['mdg_family22_event_id'] ?? 0 ), true );
        $item->add_meta_data( '_mdg_family22_session_id', absint( $values['mdg_family22_session_id'] ?? 0 ), true );
    }

    public static function store_order_meta( $order, $data ) {
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return; }
        $groups = self::cart_family_groups( WC()->cart );
        if ( ! $groups ) { return; }

        $total_packages = 0;
        foreach ( $groups as $group ) {
            $effective = min(
                absint( $group['declared'] ),
                (int) floor( max( 0, (int) $group['child_qty'] ) / self::DEFAULT_CHILD ),
                (int) floor( max( 0, (int) $group['adult_qty'] ) / self::DEFAULT_ADULT )
            );
            $total_packages += max( 0, $effective );
        }
        if ( $total_packages < 1 ) { return; }
        $settings = self::settings();
        $order->update_meta_data( '_mdg_family22_packages', $total_packages );
        $order->update_meta_data( '_mdg_family22_label', sanitize_text_field( (string) $settings['label'] ) );
        $order->update_meta_data( '_mdg_family22_price', (float) $settings['price'] );
    }
}

add_action( 'plugins_loaded', array( 'MDG_Family_Package_22_V1', 'boot' ), 30 );