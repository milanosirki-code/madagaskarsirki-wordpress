<?php
/**
 * Milano Woo classic / Store API prepayment hook adapter — DRAFT SOURCE ONLY.
 *
 * This file intentionally does NOT call ::register() itself. There is no
 * deployment, REST endpoint, PayTR activation, payment, ticket or email.
 * A future Milano-only central bootstrap must:
 *   - verify signed purchase intent server-to-server;
 *   - recompute canonical brand/event/session and quote with MMC/MDG;
 *   - successfully elect exactly one BEFORE the standard Woo checkout;
 *   - stamp protected Milano meta on that one Woo order before this hook;
 *   - supply a trusted spec resolver using SERVER state, not browser data.
 *
 * MDG_Live_Sales has priority 5 for both checkout hooks and reserves MDG
 * capacity + maps order items there. This gate uses priority 7, before
 * WooCommerce normally initiates the payment gateway.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_Woo_Checkout_Gate {
    private static $server_resolver = null;
    private static $registered = false;

    /**
     * Explicit opt-in by central bootstrap only. Never call with $_POST,
     * request JSON or externally supplied event/product/pricing arrays.
     *
     * Resolver returns:
     *  - null: canonical order has no Milano-owned line items
     *  - WP_Error: cannot resolve order ownership safely
     *  - array with quote_sha256 plus the canonical spec required by
     *    Milano_Prepayment_Order_Audit (spec key order is intentional).
     *
     * Crucially, the resolver must inspect mapped MDG/MMC ownership even if
     * client/checkout neglected or forged the _milano_brand metadata.
     */
    public static function register( $trusted_server_resolver ) {
        if ( ! is_callable( $trusted_server_resolver )
            || ! function_exists( 'add_action' ) ) {
            return new WP_Error( 'milano_gate_not_configured', 'Trusted Milano checkout resolver is missing.' );
        }
        if ( self::$registered ) {
            return new WP_Error( 'milano_gate_already_registered', 'Checkout gate is already registered.' );
        }
        self::$server_resolver = $trusted_server_resolver;
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'classic' ), 7, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'store_api' ), 7, 1 );
        self::$registered = true;
        return true;
    }

    private static function fail( $code ) {
        return new WP_Error( 'milano_gate_' . $code, 'Milano checkout cannot proceed safely.' );
    }

    /**
     * This may attach ONE EXISTING Woo order to a PRE-EXISTING claim, but
     * NEVER creates a Woo order or claim, hold, ticket or gateway charge.
     * Every unsafe state fails before payment starts.
     */
    public static function audit_existing_checkout( $order ) {
        if ( ! is_object( $order )
            || ! method_exists( $order, 'get_id' )
            || ! method_exists( $order, 'get_meta' )
            || ! is_callable( self::$server_resolver ) ) {
            return self::fail( 'not_configured' );
        }
        $brand_meta = (string) $order->get_meta( '_milano_brand', true );
        // Resolve ownership independently of browser-supplied order metadata.
        $resolved = call_user_func( self::$server_resolver, $order );
        if ( is_wp_error( $resolved ) ) {
            return self::fail( 'ownership_resolution' );
        }
        if ( null === $resolved ) {
            if ( 'milano' === $brand_meta ) {
                return self::fail( 'untagged_ownership_conflict' );
            }
            // The trusted resolver guarantees this is NOT a Milano-owned item.
            return array( 'skipped_non_milano' => true );
        }
        if ( ! is_array( $resolved )
            || $brand_meta !== 'milano'
            || ! isset( $resolved['quote_sha256'] )
            || ! is_string( $resolved['quote_sha256'] )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $resolved['quote_sha256'] ) ) {
            return self::fail( 'spec_or_brand' );
        }
        $quote_sha = $resolved['quote_sha256'];
        unset( $resolved['quote_sha256'] );
        if ( ! isset( $resolved['intent'], $resolved['event_id'], $resolved['session_id'],
            $resolved['amount_minor'], $resolved['units'], $resolved['brand'] )
            || $resolved['brand'] !== 'milano'
            || ! class_exists( 'Milano_Canonical_Order_Claim' )
            || ! class_exists( 'Milano_Prepayment_Order_Audit' ) ) {
            return self::fail( 'missing_dependencies' );
        }

        // No INSERT at this stage. A first-writer claim MUST precede any
        // customer Woo order creation, or a replay could create two orders.
        $existing = Milano_Canonical_Order_Claim::existing(
            $resolved['intent'], $quote_sha,
            $resolved['event_id'], $resolved['session_id'],
            $resolved['amount_minor'], $resolved['units']
        );
        if ( is_wp_error( $existing ) ) { return self::fail( 'missing_or_conflicting_claim' ); }
        $order_id = (int) $order->get_id();
        if ( $order_id < 1 ) { return self::fail( 'invalid_order' ); }
        $current_state = (string) ( $existing['state'] ?? '' );
        $existing_order_id = (int) ( $existing['wc_order_id'] ?? 0 );
        // Another worker is in the middle of the MDG hold step. A retry
        // must never initiate a second payment while the outcome is unknown.
        if ( 'reserving' === $current_state ) {
            return self::fail( 'reservation_in_progress_needs_reconciliation' );
        }
        // "ready" belongs to the explicit MDG reservation orchestrator.
        // It must be replayable for the SAME Woo order, not re-bound.
        if ( in_array( $current_state, array( 'bound', 'ready' ), true )
            && $existing_order_id !== $order_id ) {
            return self::fail( 'duplicate_order' );
        }
        if ( 'claimed' !== $current_state
            && 'bound' !== $current_state && 'ready' !== $current_state ) {
            return self::fail( 'unexpected_claim_state' );
        }

        // Runs AFTER MDG checkout reservation hook, ensuring pre-payment
        // canonical order_map and single unexpired four-seat hold exist.
        $audit = Milano_Prepayment_Order_Audit::inspect( $order, $resolved );
        if ( is_wp_error( $audit ) ) {
            return self::fail( 'mdg_preflight' );
        }
        if ( ! is_array( $audit ) || (int) ( $audit['canonical_order_id'] ?? 0 ) !== $order_id
            || (int) ( $audit['held_capacity_units'] ?? 0 ) !== $resolved['units']
            || (int) ( $audit['amount_minor'] ?? 0 ) !== $resolved['amount_minor'] ) {
            return self::fail( 'preflight_result' );
        }

        // One-time bind. A competing Woo order created against this intent
        // fails the atomic order-ID CAS, preventing its gateway initiation.
        if ( 'claimed' === $current_state ) {
            $bound = Milano_Canonical_Order_Claim::attach_order( $resolved['intent'], $order_id );
            if ( is_wp_error( $bound ) || (int) ( $bound['wc_order_id'] ?? 0 ) !== $order_id ) {
                return self::fail( 'order_bind' );
            }
        }
        // For bound/ready replays the CAS state and bound Woo order ID were
        // validated above; under no condition do we initiate a second hold.
        return array(
            'milano_preflight_ok' => true,
            'canonical_order_id' => $order_id,
            'reservation_state' => $current_state,
            'gateway_invoked' => false,
        );
    }

    /** Classic WC checkout, after MDG's priority-5 capacity hold hook. */
    public static function classic( $order_id, $posted_data = array(), $order = null ) {
        if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $order_id );
        }
        $result = self::audit_existing_checkout( $order );
        if ( is_wp_error( $result ) ) {
            throw new RuntimeException( 'Milano checkout blocked: ' . $result->get_error_code() );
        }
        return $result;
    }

    /** Checkout Block / Store API hook, also before gateway processing. */
    public static function store_api( $order ) {
        $result = self::audit_existing_checkout( $order );
        if ( is_wp_error( $result ) ) {
            $code = $result->get_error_code();
            if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    $code,
                    'Milano bilet satışı doğrulaması başarısız. Lütfen destekle iletişime geçiniz.',
                    409
                );
            }
            throw new RuntimeException( 'Milano checkout blocked: ' . $code );
        }
        return $result;
    }
}
