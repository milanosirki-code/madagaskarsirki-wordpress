<?php
/**
 * Milano WooCommerce CLASSIC checkout lifecycle bridge, 2026-10-14.
 *
 * CODE ONLY. This file has NO automatic boot/hook registration and MUST NOT
 * be activated on staging/live yet. The outer deployment must supply a
 * server-side authenticated signed-intent verifier callback plus separately
 * approved merchant-domain/payment routing, after staging QR+PDF QA.
 *
 * Reuses MDG_Live_Sales's already registered classic checkout pre-payment
 * reservation (priority 5). NEVER call wc_create_order and immediately pay.
 * On duplicate/ambiguous requests, fail closed; NEVER mint a second Woo order.
 * Store API checkout for Milano is unsupported and fails closed here.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_Woo_Classic_Checkout_Bridge {
    private static $trusted_provider = null;
    private static $registered = false;

    /**
     * Registration is EXPLICIT, never booted by including the file.
     * The callable MUST do real HMAC validation and SERVER-side quote/brand/
     * session repricing. A boolean "verified" from the browser is NOT proof.
     */
    public static function register( $server_verified_spec_provider ) {
        if ( self::$registered || ! is_callable( $server_verified_spec_provider ) ) {
            return new WP_Error( 'milano_bridge_provider', 'Authenticated server intent provider is required.' );
        }
        if ( ! function_exists( 'add_action' ) || ! class_exists( 'Milano_Canonical_Order_Claim' )
            || ! class_exists( 'Milano_Prepayment_Order_Audit' ) ) {
            return new WP_Error( 'milano_bridge_dependencies', 'Required checkout ledger and audit are absent.' );
        }
        self::$trusted_provider = $server_verified_spec_provider;
        // WC_Checkout may resume an existing pending order by removing its
        // old order items BEFORE woocommerce_checkout_create_order fires.
        // Stop Milano resumes before any existing Woo ticket data is altered.
        add_action( 'woocommerce_resume_order', array( __CLASS__, 'deny_reused_milano_order' ), 1, 1 );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'claim_before_save' ), 25, 2 );
        // MDG_Live_Sales reserves stock and records order map at priority 5.
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'audit_after_mdg_hold' ), 25, 3 );
        // No safe independent Store API path is wired yet. Block it for Milano.
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'deny_unapproved_store_api' ), 25, 1 );
        self::$registered = true;
        return true;
    }

    /** Recognize Milano by saved brand, family metadata or canonical MDG mapping. */
    private static function touches_milano( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
            return false;
        }
        if ( method_exists( $order, 'get_meta' )
            && 'milano' === (string) $order->get_meta( '_milano_brand', true ) ) {
            return true;
        }
        foreach ( (array) $order->get_items( 'line_item' ) as $item ) {
            if ( ! is_object( $item ) ) { continue; }
            if ( method_exists( $item, 'get_meta' )
                && '24' === (string) $item->get_meta( '_mdg_family22_event_id', true ) ) {
                return true;
            }
            // Even if the family line metadata is missing/tampered with,
            // the actual WC variation still maps to the Milano MDG event.
            global $wpdb;
            if ( ! method_exists( $item, 'get_variation_id' )
                || ! isset( $wpdb ) || ! is_object( $wpdb )
                || ! class_exists( 'MDG_DB' )
                || ! method_exists( $wpdb, 'get_row' ) ) {
                continue;
            }
            $vid = (int) $item->get_variation_id();
            if ( ! $vid ) { continue; }
            $types = MDG_DB::table( 'ticket_types' );
            $sessions = MDG_DB::table( 'sessions' );
            $mapped = $wpdb->get_row( $wpdb->prepare(
                "SELECT s.event_id FROM {$types} t INNER JOIN {$sessions} s
                 ON s.id=t.session_id WHERE t.wc_variation_id=%d LIMIT 2",
                $vid
            ) );
            if ( $mapped && (int) $mapped->event_id === 24 ) { return true; }
        }
        return false;
    }

    /** WC core calls this BEFORE removing existing order items on a resumed checkout. */
    public static function deny_reused_milano_order( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) { return; }
        $existing = wc_get_order( (int) $order_id );
        if ( self::touches_milano( $existing ) ) {
            throw new RuntimeException(
                'An existing Milano Woo order cannot be reset by a resumed checkout.'
            );
        }
    }

    /**
     * Immutable short-lived, INTERNAL spec from server HMAC verifier. The
     * trusted provider MUST NOT read unverified browser cart JSON into $spec.
     */
    private static function resolve( $order ) {
        if ( ! is_callable( self::$trusted_provider ) ) {
            throw new RuntimeException( 'Milano signed order provider is not configured.' );
        }
        $context = call_user_func( self::$trusted_provider, $order );
        $required = array( 'brand','intent','event_id','session_id','product_id',
            'child_variation_id','adult_variation_id','child_type_id','adult_type_id',
            'amount_minor','units' );
        if ( ! is_array( $context ) || ! isset( $context['spec'] ) || ! is_array( $context['spec'] )
            || array_keys( $context['spec'] ) !== $required
            || ! is_string( $context['spec']['intent'] ?? null )
            || ! preg_match( '/^[a-f0-9]{64}$/D', $context['spec']['intent'] )
            || ! is_int( $context['spec']['event_id'] ?? null )
            || ! is_int( $context['spec']['session_id'] ?? null )
            || ! is_int( $context['spec']['product_id'] ?? null )
            || ! is_int( $context['spec']['child_variation_id'] ?? null )
            || ! is_int( $context['spec']['adult_variation_id'] ?? null )
            || ! is_int( $context['spec']['child_type_id'] ?? null )
            || ! is_int( $context['spec']['adult_type_id'] ?? null )
            || ! is_int( $context['spec']['amount_minor'] ?? null )
            || ! is_int( $context['spec']['units'] ?? null )
            || min( $context['spec']['product_id'], $context['spec']['child_variation_id'],
                    $context['spec']['adult_variation_id'], $context['spec']['child_type_id'],
                    $context['spec']['adult_type_id'] ) < 1
            || ! isset( $context['quote_sha256'] ) || ! is_string( $context['quote_sha256'] )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $context['quote_sha256'] )
            || ( $context['origin'] ?? null ) !== 'https://milanosirki.com'
            || ( $context['aud'] ?? null ) !== 'milano:central-checkout'
            || ! is_int( $context['iat'] ?? null )
            || ! is_int( $context['exp'] ?? null )
            || $context['iat'] > time() + 30 || $context['exp'] <= time()
            || $context['exp'] - $context['iat'] > 300
            || $context['exp'] - $context['iat'] < 1
            || ( $context['spec']['brand'] ?? null ) !== 'milano'
            || ( $context['spec']['event_id'] ?? null ) !== 24
            || ! in_array( $context['spec']['session_id'] ?? null, array( 150, 151 ), true )
            || ( $context['spec']['amount_minor'] ?? null ) !== 110000
            || ( $context['spec']['units'] ?? null ) !== 4 ) {
            throw new RuntimeException( 'Milano signed intent/brand/session context is invalid or expired.' );
        }
        return $context;
    }

    /** Pre-save classic hook: first-writer claim MUST precede Woo order save. */
    public static function claim_before_save( $order, $checkout_data = array() ) {
        if ( ! self::touches_milano( $order ) ) { return; }
        $ctx = self::resolve( $order );
        $spec = $ctx['spec'];
        if ( ! method_exists( $order, 'get_id' ) || ! method_exists( $order, 'get_status' )
            || ! method_exists( $order, 'update_meta_data' )
            || (int) $order->get_id() > 0 || (string) $order->get_status() !== 'pending'
            || ! self::has_exact_family_items( $order, $spec ) ) {
            throw new RuntimeException( 'Milano checkout cannot reuse an existing order or mixed cart.' );
        }
        $claim = Milano_Canonical_Order_Claim::claim(
            $spec['intent'], $ctx['quote_sha256'], $spec['event_id'],
            $spec['session_id'], $spec['amount_minor'], $spec['units']
        );
        if ( is_wp_error( $claim ) || ! is_array( $claim )
            || ( $claim['created'] ?? false ) !== true
            || ( $claim['state'] ?? '' ) !== 'claimed'
            || ! empty( $claim['wc_order_id'] ) ) {
            // A retry NEVER gets another WC order, even if the original WC
            // order has not finished binding. Manual recovery is required.
            throw new RuntimeException( 'Purchase intent already used or pending reconciliation.' );
        }
        $order->update_meta_data( '_milano_brand', 'milano' );
        $order->update_meta_data( '_milano_intent_key', $spec['intent'] );
        // Existing Madagaskar Family 2+2 stores this same package count.
        $order->update_meta_data( '_mdg_family22_packages', 1 );
    }

    /** Validate components while order is in-memory (Woo line IDs not saved). */
    private static function has_exact_family_items( $order, $spec ) {
        if ( ! method_exists( $order, 'get_items' ) ) { return false; }
        $lines = array_values( (array) $order->get_items( 'line_item' ) );
        if ( count( $lines ) !== 2 ) { return false; }
        $components = array();
        $family_group = null;
        foreach ( $lines as $item ) {
            if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' )
                || ! method_exists( $item, 'get_quantity' )
                || ! method_exists( $item, 'get_product_id' )
                || ! method_exists( $item, 'get_variation_id' ) ) { return false; }
            $component = (string) $item->get_meta( '_mdg_family22_component', true );
            $group = (string) $item->get_meta( '_mdg_family22_group', true );
            $variation = 'child' === $component ? $spec['child_variation_id']
                : ( 'adult' === $component ? $spec['adult_variation_id'] : 0 );
            if ( ! $variation || isset( $components[ $component ] )
                || (int) $item->get_product_id() !== $spec['product_id']
                || (int) $item->get_variation_id() !== $variation
                || (int) $item->get_quantity() !== 2
                || (string) $item->get_meta( '_mdg_family22', true ) !== '1'
                || (int) $item->get_meta( '_mdg_family22_packages', true ) !== 1
                || (int) $item->get_meta( '_mdg_family22_session_id', true ) !== $spec['session_id']
                || (int) $item->get_meta( '_mdg_family22_event_id', true ) !== $spec['event_id']
                || ! is_string( $group ) || strlen( $group ) < 12
                || ( null !== $family_group && $family_group !== $group ) ) {
                return false;
            }
            $components[ $component ] = true;
            $family_group = $group;
        }
        return isset( $components['child'], $components['adult'] );
    }

    /** Priority 25: called AFTER MDG_Live_Sales priority-5 stock hold/map. */
    public static function audit_after_mdg_hold( $order_id, $checkout_data, $order ) {
        if ( ! self::touches_milano( $order ) ) { return; }
        $ctx = self::resolve( $order );
        $spec = $ctx['spec'];
        if ( (string) $order->get_meta( '_milano_intent_key', true ) !== $spec['intent']
            || (int) $order->get_id() !== (int) $order_id ) {
            throw new RuntimeException( 'Milano Woo order identity differs from signed intent.' );
        }
        // It is forbidden to initiate payment without this explicit proof of
        // the actual reserved MDG stock and two canonical order map rows.
        $audit = Milano_Prepayment_Order_Audit::inspect( $order, $spec );
        if ( is_wp_error( $audit ) || ! is_array( $audit )
            || ( $audit['held_capacity_units'] ?? null ) !== 4
            || ( $audit['canonical_order_id'] ?? null ) !== (int) $order_id ) {
            throw new RuntimeException( 'Milano canonical MDG hold/order map incomplete; payment blocked.' );
        }
        $bind = Milano_Canonical_Order_Claim::attach_order( $spec['intent'], (int) $order_id );
        if ( is_wp_error( $bind ) || ! is_array( $bind )
            || ( $bind['wc_order_id'] ?? null ) !== (int) $order_id ) {
            throw new RuntimeException( 'Milano canonical Woo order binding failed; payment blocked.' );
        }
        // Only now may the OUTER, separately authorized Woo gateway be asked
        // for payment. This class NEVER invokes PayTR or creates a ticket.
    }

    /** Checkout Block Store API is blocked until separately integrated. */
    public static function deny_unapproved_store_api( $order ) {
        if ( self::touches_milano( $order ) ) {
            throw new RuntimeException( 'Milano Store API checkout is disabled pending dedicated integration.' );
        }
    }
}
