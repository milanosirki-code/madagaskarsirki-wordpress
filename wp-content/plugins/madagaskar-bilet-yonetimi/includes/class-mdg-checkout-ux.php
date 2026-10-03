<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production checkout polish for Madagaskar ticket-only carts.
 *
 * V2.9.9 goals:
 * - Keep the proven ticket-only checkout UX from V2.8.4+.
 * - Add future-proof invoice data fields without touching PayTR / Tickera / capacity.
 * - Individual invoice: optional T.C. identity number.
 * - Corporate invoice: company title, tax number and tax office.
 * - Invoice data is order-only data; it is not pushed to Kommo or shown on tickets.
 * - Mirror sanitized values to private order meta for a future e-invoice integration.
 */
final class MDG_Checkout_UX {
    private static $ticket_only_cache = null;

    const MARKETING_FIELD_ID     = 'mdg-bilet/marketing-opt-in';
    const INVOICE_TYPE_FIELD_ID  = 'mdg-bilet/invoice-type';
    const TCKN_FIELD_ID          = 'mdg-bilet/invoice-tckn';
    const COMPANY_FIELD_ID       = 'mdg-bilet/invoice-company-title';
    const TAX_NUMBER_FIELD_ID    = 'mdg-bilet/invoice-tax-number';
    const TAX_OFFICE_FIELD_ID    = 'mdg-bilet/invoice-tax-office';

    public static function hooks() {
        add_filter( 'woocommerce_cart_needs_shipping', array( __CLASS__, 'cart_needs_shipping' ), 99, 1 );
        add_filter( 'gettext', array( __CLASS__, 'gettext' ), 20, 3 );
        add_action( 'woocommerce_init', array( __CLASS__, 'register_checkout_fields' ), 20 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'record_checkout_audit_data' ), 40, 1 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 50 );
    }

    public static function cart_needs_shipping( $needs_shipping ) {
        if ( self::is_ticket_only_cart() ) { return false; }
        return $needs_shipping;
    }

    public static function gettext( $translated, $text, $domain ) {
        $map = array(
            'Attendee Info' => 'Katılımcı Bilgileri',
            'First name'    => 'Ad',
            'Last name'     => 'Soyad',
            'I would like to receive exclusive emails with discounts and product information' => 'Kampanya ve etkinlik duyurularını e-posta ile almak istiyorum',
        );
        return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
    }

    /**
     * Register first-class WooCommerce Checkout Block fields.
     *
     * All invoice fields live at the "order" location so identity/tax values are
     * tied to the order and are not treated as reusable customer profile data.
     */
    public static function register_checkout_fields() {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) { return; }

        self::register_field_safely( array(
            'id'            => self::MARKETING_FIELD_ID,
            'label'         => 'Kampanya ve etkinlik duyurularını e-posta ile almak istiyorum',
            'optionalLabel' => 'Kampanya ve etkinlik duyurularını e-posta ile almak istiyorum',
            'location'      => 'contact',
            'type'          => 'checkbox',
            'required'      => false,
            'attributes'    => array(
                'data-mdg-marketing-optin' => '1',
            ),
        ) );

        self::register_field_safely( array(
            'id'            => self::INVOICE_TYPE_FIELD_ID,
            'label'         => 'Fatura türü',
            'optionalLabel' => 'Fatura türü',
            'location'      => 'order',
            'type'          => 'select',
            'required'      => false,
            'options'       => array(
                array( 'value' => 'individual', 'label' => 'Bireysel' ),
                array( 'value' => 'corporate',  'label' => 'Kurumsal' ),
            ),
            'attributes'    => array(
                'data-mdg-invoice-field' => 'type',
            ),
        ) );

        self::register_field_safely( array(
            'id'            => self::TCKN_FIELD_ID,
            'label'         => 'T.C. Kimlik No (opsiyonel)',
            'optionalLabel' => 'T.C. Kimlik No (opsiyonel)',
            'location'      => 'order',
            'type'          => 'text',
            'required'      => false,
            'attributes'    => array(
                'data-mdg-invoice-field'  => 'tckn',
            ),
        ) );

        self::register_field_safely( array(
            'id'            => self::COMPANY_FIELD_ID,
            'label'         => 'Firma / Şirket Unvanı',
            'optionalLabel' => 'Firma / Şirket Unvanı',
            'location'      => 'order',
            'type'          => 'text',
            'required'      => false,
            'attributes'    => array(
                'data-mdg-invoice-field'  => 'company',
            ),
        ) );

        self::register_field_safely( array(
            'id'            => self::TAX_NUMBER_FIELD_ID,
            'label'         => 'Vergi Numarası',
            'optionalLabel' => 'Vergi Numarası',
            'location'      => 'order',
            'type'          => 'text',
            'required'      => false,
            'attributes'    => array(
                'data-mdg-invoice-field'  => 'tax-number',
            ),
        ) );

        self::register_field_safely( array(
            'id'            => self::TAX_OFFICE_FIELD_ID,
            'label'         => 'Vergi Dairesi',
            'optionalLabel' => 'Vergi Dairesi',
            'location'      => 'order',
            'type'          => 'text',
            'required'      => false,
            'attributes'    => array(
                'data-mdg-invoice-field'  => 'tax-office',
            ),
        ) );
    }

    private static function register_field_safely( $args ) {
        try {
            woocommerce_register_additional_checkout_field( $args );
        } catch ( Throwable $e ) {
            // Optional checkout enrichment must never break the sales/payment path.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'MDG checkout field registration: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Mirror official WooCommerce additional-field values to private order meta.
     *
     * The original Additional Checkout Fields data remains the canonical checkout
     * record. The private meta keys are a stable adapter for the future invoice API.
     */
    public static function record_checkout_audit_data( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) { return; }

        $fields = self::get_additional_fields_from_order( $order );
        if ( ! is_array( $fields ) ) { $fields = array(); }

        // Marketing consent audit.
        if ( array_key_exists( self::MARKETING_FIELD_ID, $fields ) ) {
            $value    = $fields[ self::MARKETING_FIELD_ID ];
            $opted_in = in_array( $value, array( true, 1, '1', 'yes', 'true', 'on' ), true );
            $order->update_meta_data( '_mdg_marketing_optin', $opted_in ? 'yes' : 'no' );
            $order->update_meta_data( '_mdg_marketing_optin_source', 'woocommerce_checkout' );
            if ( $opted_in ) {
                $order->update_meta_data( '_mdg_marketing_optin_at_utc', gmdate( 'Y-m-d H:i:s' ) );
            } else {
                $order->delete_meta_data( '_mdg_marketing_optin_at_utc' );
            }
        }

        // Invoice adapter data. Missing invoice type safely falls back to individual.
        $invoice_type = sanitize_key( (string) ( $fields[ self::INVOICE_TYPE_FIELD_ID ] ?? 'individual' ) );
        if ( ! in_array( $invoice_type, array( 'individual', 'corporate' ), true ) ) {
            $invoice_type = 'individual';
        }

        $tckn        = self::digits_only( $fields[ self::TCKN_FIELD_ID ] ?? '', 11 );
        $company     = sanitize_text_field( (string) ( $fields[ self::COMPANY_FIELD_ID ] ?? '' ) );
        $tax_number  = self::digits_only( $fields[ self::TAX_NUMBER_FIELD_ID ] ?? '', 10 );
        $tax_office  = sanitize_text_field( (string) ( $fields[ self::TAX_OFFICE_FIELD_ID ] ?? '' ) );

        $order->update_meta_data( '_mdg_invoice_schema_version', '1' );
        $order->update_meta_data( '_mdg_invoice_type', $invoice_type );
        $order->update_meta_data( '_mdg_invoice_source', 'woocommerce_checkout' );

        if ( 'corporate' === $invoice_type ) {
            $order->delete_meta_data( '_mdg_invoice_tckn' );
            self::set_or_delete_meta( $order, '_mdg_invoice_company_title', $company );
            self::set_or_delete_meta( $order, '_mdg_invoice_tax_number', $tax_number );
            self::set_or_delete_meta( $order, '_mdg_invoice_tax_office', $tax_office );
        } else {
            self::set_or_delete_meta( $order, '_mdg_invoice_tckn', $tckn );
            $order->delete_meta_data( '_mdg_invoice_company_title' );
            $order->delete_meta_data( '_mdg_invoice_tax_number' );
            $order->delete_meta_data( '_mdg_invoice_tax_office' );
        }

        if ( method_exists( $order, 'save' ) ) { $order->save(); }
    }

    private static function get_additional_fields_from_order( $order ) {
        try {
            if ( class_exists( '\\Automattic\\WooCommerce\\Blocks\\Package' )
                && class_exists( '\\Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields' ) ) {
                $service = \Automattic\WooCommerce\Blocks\Package::container()->get(
                    \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
                );
                if ( $service && method_exists( $service, 'get_all_fields_from_object' ) ) {
                    $fields = $service->get_all_fields_from_object( $order, 'other' );
                    if ( is_array( $fields ) ) { return $fields; }
                }
            }
        } catch ( Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'MDG checkout field read: ' . $e->getMessage() );
            }
        }
        return array();
    }

    private static function digits_only( $value, $max_length ) {
        $digits = preg_replace( '/\D+/', '', (string) $value );
        if ( ! is_string( $digits ) ) { return ''; }
        return substr( $digits, 0, absint( $max_length ) );
    }

    private static function set_or_delete_meta( $order, $key, $value ) {
        if ( '' === (string) $value ) {
            $order->delete_meta_data( $key );
        } else {
            $order->update_meta_data( $key, $value );
        }
    }

    public static function enqueue() {
        if ( is_admin() ) { return; }

        $is_wc_flow = ( function_exists( 'is_cart' ) && is_cart() )
            || ( function_exists( 'is_checkout' ) && is_checkout() )
            || ( function_exists( 'is_order_received_page' ) && is_order_received_page() );
        if ( ! $is_wc_flow ) { return; }

        wp_enqueue_style(
            'mdg-checkout-ux',
            MDG_BILET_URL . 'assets/checkout-ux.css',
            array(),
            MDG_BILET_VERSION
        );
        wp_enqueue_script(
            'mdg-checkout-ux',
            MDG_BILET_URL . 'assets/checkout-ux.js',
            array(),
            MDG_BILET_VERSION,
            true
        );
        wp_localize_script( 'mdg-checkout-ux', 'MDG_CHECKOUT_UX', array(
            'ticketOnly'       => self::is_ticket_only_cart(),
            'marketingFieldId' => self::MARKETING_FIELD_ID,
            'invoiceFields'    => array(
                'type'      => self::INVOICE_TYPE_FIELD_ID,
                'tckn'      => self::TCKN_FIELD_ID,
                'company'   => self::COMPANY_FIELD_ID,
                'taxNumber' => self::TAX_NUMBER_FIELD_ID,
                'taxOffice' => self::TAX_OFFICE_FIELD_ID,
            ),
        ) );
    }

    private static function is_ticket_only_cart() {
        if ( null !== self::$ticket_only_cache ) { return (bool) self::$ticket_only_cache; }
        self::$ticket_only_cache = false;

        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return false; }
        $items = WC()->cart->get_cart();
        if ( ! is_array( $items ) || ! $items ) { return false; }

        global $wpdb;
        $types_table = MDG_DB::table( 'ticket_types' );
        foreach ( $items as $item ) {
            $variation_id = absint( $item['variation_id'] ?? 0 );
            if ( ! $variation_id && ! empty( $item['data'] ) && is_object( $item['data'] ) && method_exists( $item['data'], 'get_id' ) ) {
                $variation_id = absint( $item['data']->get_id() );
            }
            if ( ! $variation_id ) { return false; }
            $mapped = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$types_table} WHERE wc_variation_id=%d AND is_active=1",
                $variation_id
            ) );
            if ( $mapped < 1 ) { return false; }
        }

        self::$ticket_only_cache = true;
        return true;
    }
}
