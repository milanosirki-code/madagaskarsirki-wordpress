<?php
/**
 * Central Milano canonical Woo/MDG reservation orchestrator — OFFLINE ONLY.
 *
 * No REST API, hooks, table install, Woo order creation, PayTR payment or
 * Tickera issuance. This class is not installed on either live site or staging.
 *
 * The caller must have created exactly one pending Woo order from VERIFIED
 * server-side intent. Never use raw browser claims to create the order.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_Central_Checkout_Reservation {
    private static function fail( $code ) {
        return new WP_Error( 'milano_checkout_' . $code, 'Milano central order requires review before payment.' );
    }

    /**
     * Reserve exactly once on the existing MDG checkout owner.
     *
     * The normal WC checkout invokes MDG_Live_Sales::classic_order_processed
     * BEFORE a gateway; a custom central server adapter needs this same public
     * MDG method instead of calling wc_create_order() and trusting WC stock.
     *
     * If any step fails after state->reserving, leave the intent locked for
     * human reconciliation: a blind retry could duplicate a held seat or
     * Tickera admission. There is intentionally no auto-recovery.
     */
    public static function prepare_unpaid_order( $order, $spec, $quote_sha256 ) {
        if ( ! class_exists( 'Milano_Canonical_Order_Claim' )
            || ! class_exists( 'Milano_Prepayment_Order_Audit' )
            || ! class_exists( 'MDG_Live_Sales' )
            || ! is_object( $order ) || ! method_exists( $order, 'get_id' )
            || ! is_array( $spec ) || ! isset( $spec['intent'], $spec['event_id'], $spec['session_id'], $spec['amount_minor'], $spec['units'] )
            || ! is_string( $quote_sha256 )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $quote_sha256 ) ) {
            return self::fail( 'dependencies_or_quote' );
        }

        $preflight = Milano_Prepayment_Order_Audit::inspect( $order, $spec, 'pre_reservation' );
        if ( is_wp_error( $preflight ) || ! is_array( $preflight )
            || ! empty( $preflight['may_start_payment_after_separate_gateway_auth'] ) ) {
            return self::fail( 'invalid_order_structure' );
        }

        $order_id = (int) $order->get_id();
        $claim = Milano_Canonical_Order_Claim::claim(
            $spec['intent'], $quote_sha256, $spec['event_id'],
            $spec['session_id'], $spec['amount_minor'], $spec['units']
        );
        if ( is_wp_error( $claim ) ) { return $claim; }
        if ( ! is_array( $claim )
            || (int) ( $claim['wc_order_id'] ?? 0 ) !== $order_id
            || ! empty( $claim['created'] ) ) {
            // This may NOT mint an order or bind an unclaimed order.
            return self::fail( 'order_not_bound' );
        }

        if ( 'ready' === $claim['state'] ) {
            // A replay can return the same order only if its hold is STILL
            // valid. No MDG call and no gateway initiation happens here.
            $audit = Milano_Prepayment_Order_Audit::inspect( $order, $spec );
            return is_wp_error( $audit ) ? self::fail( 'previous_reservation_invalid' )
                : array(
                    'order_id' => $order_id,
                    'state' => 'ready',
                    'replayed' => true,
                    'gateway_started' => false,
                    'held_units' => 4,
                    'admission_qr_created' => false,
                );
        }
        if ( 'bound' !== $claim['state'] ) {
            return self::fail( 'already_in_progress_needs_review' );
        }

        $lock = Milano_Canonical_Order_Claim::reservation_transition(
            $spec['intent'], $order_id, 'bound', 'reserving'
        );
        if ( is_wp_error( $lock ) ) {
            return self::fail( 'reservation_already_claimed' );
        }

        try {
            // The EXISTING canonical MDG owner calls MDG_Capacity::hold()
            // and writes mdg_order_map. This is not a WC gateway call.
            MDG_Live_Sales::classic_order_processed( $order_id, array(), $order );
        } catch ( Throwable $e ) {
            // Deliberately never rollback the ledger state. A partially
            // committed MDG hold/order map needs manual reconciliation.
            return self::fail( 'mdg_reservation_ambiguous_needs_review' );
        }

        $audit = Milano_Prepayment_Order_Audit::inspect( $order, $spec );
        if ( is_wp_error( $audit )
            || ! is_array( $audit )
            || empty( $audit['may_start_payment_after_separate_gateway_auth'] ) ) {
            // No ready transition, no ticket or payment; investigate.
            return self::fail( 'mdg_reservation_audit_failed' );
        }

        $ready = Milano_Canonical_Order_Claim::reservation_transition(
            $spec['intent'], $order_id, 'reserving', 'ready'
        );
        if ( is_wp_error( $ready ) ) {
            return self::fail( 'ready_transition_ambiguous_needs_review' );
        }

        return array(
            'order_id' => $order_id, 'state' => 'ready',
            'replayed' => false, 'gateway_started' => false,
            'held_units' => 4, 'admission_qr_created' => false,
        );
    }
}
