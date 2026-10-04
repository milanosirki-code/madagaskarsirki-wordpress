<?php
/**
 * Plugin Name: Dünya Organizasyon – Fatura Takip
 * Description: WooCommerce siparişlerinde fatura takibi, %10 KDV hesaplama, toplu durum güncelleme, fatura bilgilerini kopyalama ve WhatsApp gönderim takibi.
 * Version: 1.0.1
 * Author: Dünya Organizasyon
 * Text Domain: dunya-fatura-takip
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Dunya_Organizasyon_Fatura_Takip {
    const VERSION = '1.0.1';
    const META_STATUS = '_dof_invoice_status';
    const META_INVOICE_DATE = '_dof_invoice_date';
    const META_WHATSAPP_DATE = '_dof_whatsapp_date';
    const NONCE_ACTION = 'dof_invoice_action';

    public static function init() {
        add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
    }

    public static function declare_hpos_compatibility() {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }

    public static function boot() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( __CLASS__, 'woocommerce_required_notice' ) );
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 60 );
        add_action( 'wp_ajax_dof_set_invoice_status', array( __CLASS__, 'ajax_set_invoice_status' ) );
        add_action( 'wp_ajax_dof_bulk_invoice_status', array( __CLASS__, 'ajax_bulk_invoice_status' ) );
    }

    public static function woocommerce_required_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>Dünya Organizasyon – Fatura Takip</strong> için WooCommerce etkin olmalıdır.</p></div>';
    }

    public static function admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Fatura Takip',
            'Fatura Takip',
            'manage_woocommerce',
            'dunya-fatura-takip',
            array( __CLASS__, 'render_page' )
        );
    }

    private static function vat_rate() {
        return (float) apply_filters( 'dof_vat_rate', 10.0 );
    }

    private static function get_invoice_status( $order ) {
        $status = $order->get_meta( self::META_STATUS, true );
        return in_array( $status, array( 'pending', 'invoiced', 'sent' ), true ) ? $status : 'pending';
    }

    private static function set_invoice_status( $order, $status ) {
        if ( ! in_array( $status, array( 'pending', 'invoiced', 'sent' ), true ) ) {
            return false;
        }

        $now = current_time( 'mysql' );
        $order->update_meta_data( self::META_STATUS, $status );

        if ( 'pending' === $status ) {
            $order->delete_meta_data( self::META_INVOICE_DATE );
            $order->delete_meta_data( self::META_WHATSAPP_DATE );
        } elseif ( 'invoiced' === $status ) {
            if ( ! $order->get_meta( self::META_INVOICE_DATE, true ) ) {
                $order->update_meta_data( self::META_INVOICE_DATE, $now );
            }
            $order->delete_meta_data( self::META_WHATSAPP_DATE );
        } elseif ( 'sent' === $status ) {
            if ( ! $order->get_meta( self::META_INVOICE_DATE, true ) ) {
                $order->update_meta_data( self::META_INVOICE_DATE, $now );
            }
            $order->update_meta_data( self::META_WHATSAPP_DATE, $now );
        }

        $order->save();
        return true;
    }

    public static function ajax_set_invoice_status() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
        $order    = wc_get_order( $order_id );

        if ( ! $order || ! self::set_invoice_status( $order, $status ) ) {
            wp_send_json_error( array( 'message' => 'Sipariş veya durum geçersiz.' ), 400 );
        }

        wp_send_json_success( array(
            'status'        => self::get_invoice_status( $order ),
            'invoice_date'  => $order->get_meta( self::META_INVOICE_DATE, true ),
            'whatsapp_date' => $order->get_meta( self::META_WHATSAPP_DATE, true ),
        ) );
    }

    public static function ajax_bulk_invoice_status() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Bu işlem için yetkiniz yok.' ), 403 );
        }

        $ids    = isset( $_POST['order_ids'] ) ? (array) $_POST['order_ids'] : array();
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
        $count  = 0;

        foreach ( $ids as $id ) {
            $order = wc_get_order( absint( $id ) );
            if ( $order && self::set_invoice_status( $order, $status ) ) {
                $count++;
            }
        }

        wp_send_json_success( array( 'count' => $count ) );
    }

    private static function build_meta_query( $invoice_status ) {
        if ( 'invoiced' === $invoice_status || 'sent' === $invoice_status ) {
            return array(
                array(
                    'key'     => self::META_STATUS,
                    'value'   => $invoice_status,
                    'compare' => '=',
                ),
            );
        }

        if ( 'pending' === $invoice_status ) {
            return array(
                'relation' => 'OR',
                array(
                    'key'     => self::META_STATUS,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => self::META_STATUS,
                    'value'   => '',
                    'compare' => '=',
                ),
                array(
                    'key'     => self::META_STATUS,
                    'value'   => 'pending',
                    'compare' => '=',
                ),
            );
        }

        return array();
    }

    private static function get_filters() {
        $status = isset( $_GET['invoice_status'] ) ? sanitize_key( wp_unslash( $_GET['invoice_status'] ) ) : 'pending';
        if ( ! in_array( $status, array( 'all', 'pending', 'invoiced', 'sent' ), true ) ) {
            $status = 'pending';
        }

        $from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
        $to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
        $payment = isset( $_GET['payment_method'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_method'] ) ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        return array(
            'invoice_status' => $status,
            'date_from'      => preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $from ) ? $from : '',
            'date_to'        => preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $to ) ? $to : '',
            'payment_method' => $payment,
            'search'         => $search,
        );
    }

    private static function date_query_value( $from, $to ) {
        if ( $from && $to ) {
            return $from . '...' . $to;
        }
        if ( $from ) {
            return '>=' . $from;
        }
        if ( $to ) {
            return '<=' . $to . ' 23:59:59';
        }
        return '';
    }

    private static function get_orders( $filters, $limit = 50, $page = 1, $paginate = true ) {
        $args = array(
            'type'     => 'shop_order',
            'limit'    => $limit,
            'page'     => $page,
            'paginate' => $paginate,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'status'   => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
            'return'   => 'objects',
        );

        $meta_query = self::build_meta_query( $filters['invoice_status'] );
        if ( ! empty( $meta_query ) ) {
            $args['meta_query'] = $meta_query;
        }

        $date_created = self::date_query_value( $filters['date_from'], $filters['date_to'] );
        if ( $date_created ) {
            $args['date_created'] = $date_created;
        }

        if ( $filters['payment_method'] ) {
            $args['payment_method'] = $filters['payment_method'];
        }

        if ( $filters['search'] ) {
            $args['search'] = '*' . $filters['search'] . '*';
        }

        return wc_get_orders( $args );
    }

    /**
     * Sipariş toplamını KDV DAHİL kabul ederek matrah ve KDV tutarını hesaplar.
     *
     * Formül (%10 için):
     *   KDV hariç = KDV dahil / 1,10
     *   KDV       = KDV dahil - KDV hariç
     *
     * Hesap kuruş (minor unit) bazında yapılır. Böylece yuvarlama sonrasında
     * her zaman: KDV hariç + KDV = KDV dahil eşitliği korunur.
     */
    private static function amounts( $order ) {
        $decimals = max( 0, (int) wc_get_price_decimals() );
        $factor   = pow( 10, $decimals );
        $rate     = max( 0, (float) self::vat_rate() );

        // WooCommerce sipariş toplamı tahsil edilen KDV dahil tutardır.
        $gross_minor = (int) round( (float) $order->get_total() * $factor );

        if ( $rate <= 0 ) {
            $net_minor = $gross_minor;
            $vat_minor = 0;
        } else {
            // Örn. 1.100,00 TL / 1,10 = 1.000,00 TL.
            $net_minor = (int) round( $gross_minor * 100 / ( 100 + $rate ) );
            $vat_minor = $gross_minor - $net_minor;
        }

        return array(
            'net'   => $net_minor / $factor,
            'vat'   => $vat_minor / $factor,
            'gross' => $gross_minor / $factor,
        );
    }

    private static function format_money( $amount, $order ) {
        return wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) );
    }

    private static function billing_name( $order ) {
        $name = trim( $order->get_formatted_billing_full_name() );
        return $name ? $name : ( $order->get_billing_company() ? $order->get_billing_company() : '—' );
    }

    private static function get_tax_identity( $order ) {
        $keys = apply_filters( 'dof_tax_identity_meta_keys', array(
            '_billing_tc_kimlik_no',
            'billing_tc_kimlik_no',
            '_billing_tckn',
            'billing_tckn',
            '_billing_tax_no',
            'billing_tax_no',
            '_billing_vergi_no',
            'billing_vergi_no',
            '_billing_vkn',
            'billing_vkn',
            '_billing_tax_number',
            'billing_tax_number',
        ) );

        foreach ( $keys as $key ) {
            $value = $order->get_meta( $key, true );
            if ( $value ) {
                return (string) $value;
            }
        }
        return '';
    }

    private static function get_tax_office( $order ) {
        $keys = apply_filters( 'dof_tax_office_meta_keys', array(
            '_billing_tax_office',
            'billing_tax_office',
            '_billing_vergi_dairesi',
            'billing_vergi_dairesi',
        ) );
        foreach ( $keys as $key ) {
            $value = $order->get_meta( $key, true );
            if ( $value ) {
                return (string) $value;
            }
        }
        return '';
    }

    private static function copy_text( $order ) {
        $amounts = self::amounts( $order );
        $lines = array();
        $lines[] = 'Ad Soyad: ' . self::billing_name( $order );
        if ( $order->get_billing_company() ) {
            $lines[] = 'Firma: ' . $order->get_billing_company();
        }
        $tax_id = self::get_tax_identity( $order );
        if ( $tax_id ) {
            $lines[] = 'T.C. / VKN: ' . $tax_id;
        }
        $tax_office = self::get_tax_office( $order );
        if ( $tax_office ) {
            $lines[] = 'Vergi Dairesi: ' . $tax_office;
        }
        $lines[] = 'Telefon: ' . $order->get_billing_phone();
        $lines[] = 'E-posta: ' . $order->get_billing_email();
        $address = trim( wp_strip_all_tags( $order->get_formatted_billing_address() ) );
        if ( $address ) {
            $lines[] = 'Adres: ' . preg_replace( '/\\s+/', ' ', $address );
        }
        $lines[] = 'Sipariş No: #' . $order->get_order_number();
        $lines[] = 'Ödeme Yöntemi: ' . $order->get_payment_method_title();
        $lines[] = '';
        $lines[] = 'Ürün / Biletler:';
        foreach ( $order->get_items() as $item ) {
            $lines[] = '- ' . $item->get_name() . ' x ' . $item->get_quantity();
        }
        $lines[] = '';
        $lines[] = 'KDV Hariç Tutar: ' . self::format_money( $amounts['net'], $order );
        $lines[] = 'KDV Oranı: %' . self::vat_rate();
        $lines[] = 'KDV Hesabı: KDV dahil toplam / 1,' . str_pad( (string) (int) self::vat_rate(), 2, '0', STR_PAD_LEFT ) . ' (dahil fiyattan geriye doğru)';
        $lines[] = 'KDV Tutarı: ' . self::format_money( $amounts['vat'], $order );
        $lines[] = 'KDV Dahil Toplam: ' . self::format_money( $amounts['gross'], $order );

        return implode( "\n", $lines );
    }

    private static function whatsapp_number( $phone ) {
        $digits = preg_replace( '/\\D+/', '', (string) $phone );
        if ( 11 === strlen( $digits ) && '0' === substr( $digits, 0, 1 ) ) {
            return '90' . substr( $digits, 1 );
        }
        if ( 10 === strlen( $digits ) ) {
            return '90' . $digits;
        }
        return $digits;
    }

    private static function whatsapp_url( $order ) {
        $phone = self::whatsapp_number( $order->get_billing_phone() );
        if ( ! $phone ) {
            return '';
        }
        $name = self::billing_name( $order );
        $message = sprintf(
            'Merhaba %s, Madagaskar Sirki bilet alışverişinize ait faturanız hazırlanmıştır. Faturanızı iletiyoruz. Bizi tercih ettiğiniz için teşekkür ederiz. 🎪',
            $name
        );
        return 'https://wa.me/' . rawurlencode( $phone ) . '?text=' . rawurlencode( $message );
    }

    private static function status_badge( $status ) {
        $map = array(
            'pending'  => array( 'Bekliyor', 'dof-badge-pending' ),
            'invoiced' => array( 'Fatura Kesildi', 'dof-badge-invoiced' ),
            'sent'     => array( 'WhatsApp Gönderildi', 'dof-badge-sent' ),
        );
        $item = isset( $map[ $status ] ) ? $map[ $status ] : $map['pending'];
        return '<span class="dof-badge ' . esc_attr( $item[1] ) . '">' . esc_html( $item[0] ) . '</span>';
    }

    private static function payment_gateways() {
        $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
        $options = array();
        foreach ( $gateways as $id => $gateway ) {
            $options[ $id ] = $gateway->get_title();
        }
        return $options;
    }

    private static function render_summary( $filters ) {
        $summary_filters = $filters;
        // Tarih filtresi yoksa performans ve kullanım kolaylığı için içinde bulunulan ayı özetle.
        if ( ! $summary_filters['date_from'] && ! $summary_filters['date_to'] ) {
            $summary_filters['date_from'] = wp_date( 'Y-m-01' );
            $summary_filters['date_to'] = wp_date( 'Y-m-t' );
        }

        $result = self::get_orders( $summary_filters, -1, 1, false );
        $orders = is_array( $result ) ? $result : array();
        $net = 0.0;
        $vat = 0.0;
        $gross = 0.0;

        foreach ( $orders as $order ) {
            $a = self::amounts( $order );
            $net += $a['net'];
            $vat += $a['vat'];
            $gross += $a['gross'];
        }

        $currency = get_woocommerce_currency();
        echo '<div class="dof-summary">';
        echo '<div class="dof-card"><span>Sipariş</span><strong>' . esc_html( count( $orders ) ) . '</strong></div>';
        echo '<div class="dof-card"><span>KDV Hariç</span><strong>' . wp_kses_post( wc_price( $net, array( 'currency' => $currency ) ) ) . '</strong></div>';
        echo '<div class="dof-card"><span>KDV (%' . esc_html( self::vat_rate() ) . ')</span><strong>' . wp_kses_post( wc_price( $vat, array( 'currency' => $currency ) ) ) . '</strong></div>';
        echo '<div class="dof-card"><span>KDV Dahil</span><strong>' . wp_kses_post( wc_price( $gross, array( 'currency' => $currency ) ) ) . '</strong></div>';
        echo '</div>';
        echo '<p class="description dof-summary-note">Özet, seçilen filtrelere göre hesaplanır. Tarih seçilmediyse içinde bulunulan ay kullanılır.</p>';
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Bu sayfayı görüntülemek için yetkiniz yok.' );
        }

        $filters = self::get_filters();
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 50;
        $results = self::get_orders( $filters, $per_page, $page, true );
        $orders = is_object( $results ) && isset( $results->orders ) ? $results->orders : array();
        $max_pages = is_object( $results ) && isset( $results->max_num_pages ) ? (int) $results->max_num_pages : 1;
        $nonce = wp_create_nonce( self::NONCE_ACTION );
        $gateways = self::payment_gateways();
        ?>
        <div class="wrap dof-wrap">
            <h1>Dünya Organizasyon – Fatura Takip</h1>
            <p class="dof-lead">WooCommerce siparişlerinizi tek ekrandan takip edin. KDV oranı: <strong>%<?php echo esc_html( self::vat_rate() ); ?></strong>. Hesaplama KDV dahil sipariş toplamından geriye doğru yapılır.</p>

            <?php self::render_summary( $filters ); ?>

            <form method="get" class="dof-filters">
                <input type="hidden" name="page" value="dunya-fatura-takip">
                <select name="invoice_status">
                    <option value="all" <?php selected( $filters['invoice_status'], 'all' ); ?>>Tüm fatura durumları</option>
                    <option value="pending" <?php selected( $filters['invoice_status'], 'pending' ); ?>>Fatura bekleyenler</option>
                    <option value="invoiced" <?php selected( $filters['invoice_status'], 'invoiced' ); ?>>Fatura kesilenler</option>
                    <option value="sent" <?php selected( $filters['invoice_status'], 'sent' ); ?>>WhatsApp gönderilenler</option>
                </select>
                <select name="payment_method">
                    <option value="">Tüm ödeme yöntemleri</option>
                    <?php foreach ( $gateways as $gateway_id => $gateway_title ) : ?>
                        <option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( $filters['payment_method'], $gateway_id ); ?>><?php echo esc_html( wp_strip_all_tags( $gateway_title ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Başlangıç <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
                <label>Bitiş <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
                <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Sipariş / müşteri ara">
                <button class="button button-primary">Filtrele</button>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=dunya-fatura-takip' ) ); ?>">Temizle</a>
            </form>

            <div class="dof-bulkbar">
                <strong>Seçilenleri:</strong>
                <button type="button" class="button dof-bulk" data-status="invoiced">Fatura Kesildi</button>
                <button type="button" class="button dof-bulk" data-status="sent">WhatsApp Gönderildi</button>
                <button type="button" class="button dof-bulk" data-status="pending">Bekliyor Olarak Sıfırla</button>
            </div>

            <div class="dof-table-wrap">
                <table class="widefat fixed striped dof-table">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="dof-select-all"></td>
                            <th>Sipariş</th>
                            <th>Müşteri / İletişim</th>
                            <th>Fatura Bilgisi</th>
                            <th>Ödeme</th>
                            <th>KDV Hariç</th>
                            <th>KDV</th>
                            <th>KDV Dahil</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $orders ) ) : ?>
                        <tr><td colspan="10"><div class="dof-empty">Bu filtreye uygun sipariş bulunamadı.</div></td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $order ) :
                            $amounts = self::amounts( $order );
                            $invoice_status = self::get_invoice_status( $order );
                            $copy_text = self::copy_text( $order );
                            $whatsapp_url = self::whatsapp_url( $order );
                            $invoice_date = $order->get_meta( self::META_INVOICE_DATE, true );
                            $whatsapp_date = $order->get_meta( self::META_WHATSAPP_DATE, true );
                            $edit_url = $order->get_edit_order_url();
                            ?>
                            <tr data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                                <th class="check-column"><input type="checkbox" class="dof-order-check" value="<?php echo esc_attr( $order->get_id() ); ?>"></th>
                                <td>
                                    <a href="<?php echo esc_url( $edit_url ); ?>"><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></a><br>
                                    <small><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '' ); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( self::billing_name( $order ) ); ?></strong><br>
                                    <a href="tel:<?php echo esc_attr( $order->get_billing_phone() ); ?>"><?php echo esc_html( $order->get_billing_phone() ?: 'Telefon yok' ); ?></a><br>
                                    <small><?php echo esc_html( $order->get_billing_email() ); ?></small>
                                </td>
                                <td>
                                    <button type="button" class="button dof-copy" data-copy="<?php echo esc_attr( $copy_text ); ?>">📋 Kopyala</button>
                                    <?php if ( self::get_tax_identity( $order ) ) : ?>
                                        <br><small>T.C./VKN: <?php echo esc_html( self::get_tax_identity( $order ) ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?><br>
                                    <small><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></small>
                                </td>
                                <td><strong><?php echo wp_kses_post( wc_price( $amounts['net'], array( 'currency' => $order->get_currency() ) ) ); ?></strong></td>
                                <td>%<?php echo esc_html( self::vat_rate() ); ?><br><strong><?php echo wp_kses_post( wc_price( $amounts['vat'], array( 'currency' => $order->get_currency() ) ) ); ?></strong></td>
                                <td><strong><?php echo wp_kses_post( wc_price( $amounts['gross'], array( 'currency' => $order->get_currency() ) ) ); ?></strong></td>
                                <td class="dof-status-cell">
                                    <?php echo self::status_badge( $invoice_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <div class="dof-dates">
                                    <?php if ( $invoice_date ) : ?><small>Fatura: <?php echo esc_html( $invoice_date ); ?></small><?php endif; ?>
                                    <?php if ( $whatsapp_date ) : ?><small>WhatsApp: <?php echo esc_html( $whatsapp_date ); ?></small><?php endif; ?>
                                    </div>
                                </td>
                                <td class="dof-actions">
                                    <?php if ( 'pending' === $invoice_status ) : ?>
                                        <button type="button" class="button button-primary dof-set-status" data-status="invoiced">Fatura Kesildi</button>
                                    <?php else : ?>
                                        <button type="button" class="button dof-set-status" data-status="pending">Sıfırla</button>
                                    <?php endif; ?>
                                    <?php if ( $whatsapp_url ) : ?>
                                        <a class="button dof-whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener">WhatsApp Aç</a>
                                        <button type="button" class="button dof-set-status" data-status="sent">Gönderildi</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( $max_pages > 1 ) :
                $base_args = array(
                    'page' => 'dunya-fatura-takip',
                    'invoice_status' => $filters['invoice_status'],
                    'payment_method' => $filters['payment_method'],
                    'date_from' => $filters['date_from'],
                    'date_to' => $filters['date_to'],
                    's' => $filters['search'],
                    'paged' => '%#%',
                );
                $base = add_query_arg( $base_args, admin_url( 'admin.php' ) );
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array(
                    'base'      => $base,
                    'format'    => '',
                    'current'   => $page,
                    'total'     => $max_pages,
                    'prev_text' => '‹',
                    'next_text' => '›',
                ) ) ) . '</div></div>';
            endif; ?>
        </div>

        <style>
            .dof-wrap{max-width:1600px}.dof-lead{font-size:14px;margin-bottom:16px}.dof-summary{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:12px;margin:16px 0 8px}.dof-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;box-shadow:0 1px 1px rgba(0,0,0,.04)}.dof-card span{display:block;color:#646970;font-size:12px;margin-bottom:6px}.dof-card strong{font-size:20px}.dof-summary-note{margin-bottom:18px}.dof-filters{display:flex;gap:8px;align-items:end;flex-wrap:wrap;background:#fff;border:1px solid #dcdcde;padding:12px;border-radius:8px;margin-bottom:12px}.dof-filters label{display:flex;flex-direction:column;gap:3px;font-size:12px}.dof-bulkbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0}.dof-table-wrap{overflow-x:auto;background:#fff;border:1px solid #dcdcde;border-radius:8px}.dof-table{border:0}.dof-table th,.dof-table td{vertical-align:middle}.dof-table th:nth-child(2){width:90px}.dof-table th:nth-child(3){width:190px}.dof-table th:nth-child(4){width:130px}.dof-table th:nth-child(5){width:130px}.dof-table th:nth-child(6),.dof-table th:nth-child(7),.dof-table th:nth-child(8){width:105px}.dof-table th:nth-child(9){width:165px}.dof-table th:nth-child(10){width:230px}.dof-badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:600}.dof-badge-pending{background:#fce8e6;color:#b3261e}.dof-badge-invoiced{background:#fff4ce;color:#7a4f01}.dof-badge-sent{background:#e6f4ea;color:#137333}.dof-dates{margin-top:5px}.dof-dates small{display:block;color:#646970}.dof-actions{display:flex;gap:5px;flex-wrap:wrap}.dof-whatsapp{border-color:#1f8f4d!important}.dof-empty{padding:30px;text-align:center;color:#646970}.dof-copy.copied{border-color:#137333;color:#137333}.dof-loading{opacity:.55;pointer-events:none}@media(max-width:1000px){.dof-summary{grid-template-columns:repeat(2,1fr)}}
        </style>

        <script>
        jQuery(function($){
            const nonce = <?php echo wp_json_encode( $nonce ); ?>;

            function statusBadge(status){
                const map = {
                    pending: ['Bekliyor','dof-badge-pending'],
                    invoiced: ['Fatura Kesildi','dof-badge-invoiced'],
                    sent: ['WhatsApp Gönderildi','dof-badge-sent']
                };
                const item = map[status] || map.pending;
                return '<span class="dof-badge '+item[1]+'">'+item[0]+'</span>';
            }

            function setRowStatus($row, status){
                $row.addClass('dof-loading');
                return $.post(ajaxurl, {
                    action: 'dof_set_invoice_status',
                    nonce: nonce,
                    order_id: $row.data('order-id'),
                    status: status
                }).done(function(resp){
                    if(!resp.success){ alert((resp.data && resp.data.message) || 'İşlem tamamlanamadı.'); return; }
                    const d = resp.data;
                    let html = statusBadge(d.status)+'<div class="dof-dates">';
                    if(d.invoice_date) html += '<small>Fatura: '+d.invoice_date+'</small>';
                    if(d.whatsapp_date) html += '<small>WhatsApp: '+d.whatsapp_date+'</small>';
                    html += '</div>';
                    $row.find('.dof-status-cell').html(html);
                }).fail(function(){
                    alert('Bağlantı hatası. Lütfen tekrar deneyin.');
                }).always(function(){
                    $row.removeClass('dof-loading');
                });
            }

            $(document).on('click','.dof-set-status',function(){
                setRowStatus($(this).closest('tr'), $(this).data('status'));
            });

            $(document).on('click','.dof-copy',function(){
                const btn = this;
                const text = $(btn).attr('data-copy');
                if(navigator.clipboard && window.isSecureContext){
                    navigator.clipboard.writeText(text).then(function(){
                        $(btn).addClass('copied').text('✓ Kopyalandı');
                        setTimeout(()=>$(btn).removeClass('copied').text('📋 Kopyala'),1500);
                    });
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
                    $(btn).addClass('copied').text('✓ Kopyalandı');
                    setTimeout(()=>$(btn).removeClass('copied').text('📋 Kopyala'),1500);
                }
            });

            $('#dof-select-all').on('change',function(){
                $('.dof-order-check').prop('checked', this.checked);
            });

            $('.dof-bulk').on('click',function(){
                const ids = $('.dof-order-check:checked').map(function(){ return this.value; }).get();
                if(!ids.length){ alert('Önce en az bir sipariş seçin.'); return; }
                const status = $(this).data('status');
                const $btn = $(this).prop('disabled',true);
                $.post(ajaxurl, {
                    action: 'dof_bulk_invoice_status',
                    nonce: nonce,
                    order_ids: ids,
                    status: status
                }).done(function(resp){
                    if(resp.success){ location.reload(); }
                    else alert((resp.data && resp.data.message) || 'İşlem tamamlanamadı.');
                }).fail(function(){ alert('Bağlantı hatası.'); }).always(function(){ $btn.prop('disabled',false); });
            });
        });
        </script>
        <?php
    }
}

Dunya_Organizasyon_Fatura_Takip::init();
