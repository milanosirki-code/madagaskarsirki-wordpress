<?php
/**
 * Milano central order preflight COMPOSITION — DEVELOPMENT ONLY.
 *
 * No WordPress hooks, REST routes, Woo order calls, MDG holds, PayTR or QR.
 * The snapshot provider is injected by the future authenticated CENTRAL
 * server. Never use browser-supplied or Milano-cached event/price/stock.
 *
 * Valid signature -> existing request inspect -> fresh central snapshot ->
 * eligibility -> atomic claim. Duplicate retry cannot create an extra order.
 * Claim alone is NOT a booking or permission to use a previously shown quote.
 * The future order-worker must revalidate in its own central hold transaction.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Central_Preflight {
    /**
     * Validate a previously attached order on the trusted central server.
     * No public caller gets a raw central order_id merely for knowing a
     * server-side request ID or being able to resubmit an old signed intent.
     */
    private static function resume( $candidate, $intent, $order_lookup, $caller_authorized ) {
        $status = $candidate['status'] ?? 'unavailable';
        if ( 'existing_order' !== $status ) {
            return array( 'status' => $status );
        }
        $order_id = $candidate['order_id'] ?? null;
        if ( ! is_int( $order_id ) || $order_id < 1
            || ! is_callable( $order_lookup )
            || ! class_exists( 'MCPI_Order_Retry_Verifier' )
            || true !== $caller_authorized ) {
            return array( 'status' => 'reconcile_required' );
        }
        try {
            $order = call_user_func( $order_lookup, $order_id );
        } catch ( Throwable $e ) {
            return array( 'status' => 'unavailable' );
        }
        if ( ! MCPI_Order_Retry_Verifier::allows(
            $order, $intent, $order_id, $caller_authorized
        ) ) {
            return array( 'status' => 'reconcile_required' );
        }
        // SERVER INTERNAL only: the public API must not return raw order_id.
        return array( 'status' => 'existing_order', 'order_id' => $order_id );
    }

    public static function begin( $db, $table, $intent, $signature, $secret,
        $now, $lookup, $order_lookup = null, $caller_authorized = false ) {
        if ( ! function_exists( 'mcpi_verify' )
            || ! mcpi_verify( $intent, $signature, $secret, $now )
            || ! is_callable( $lookup ) || ! class_exists( 'MCPI_Central_Eligibility' )
            || ! class_exists( 'MCPI_Order_Claim_Registry' ) ) {
            return array( 'status' => 'rejected' );
        }

        // Authenticated retries recover the SAME central Woo order even when
        // a session's current stock/price/window has changed. Read-only
        // inspection is not order access authorization for the end customer.
        $prior = MCPI_Order_Claim_Registry::inspect(
            $db, $table, $intent, $signature, $secret, $now
        );
        if ( 'absent' !== ( $prior['status'] ?? '' ) ) {
            return self::resume(
                $prior, $intent, $order_lookup, $caller_authorized
            );
        }

        try {
            // Fetch only from central MDG/MMC/Woo authoritative tables/methods.
            $snapshot = call_user_func( $lookup, $intent['event_id'], $intent['session_id'] );
        } catch ( Throwable $e ) {
            return array( 'status' => 'unavailable' );
        }
        $family_requested = false;
        foreach ( $intent['items'] as $item ) {
            if ( ( $item['code'] ?? null ) === 'FAMILY22' ) {
                $family_requested = true;
                break;
            }
        }
        if ( $family_requested ) {
            // FAMILY22 is NEVER a real four-seat variation. The central
            // trusted family price must be normalized to 2+2 real units.
            $quote = class_exists( 'MCPI_Milano_Central_Family_Quote' )
                ? MCPI_Milano_Central_Family_Quote::validate( $intent, $snapshot, $now )
                : array( 'status' => 'blocked', 'reason' => 'family_policy_unavailable' );
        } else {
            $quote = MCPI_Central_Eligibility::validate( $intent, $snapshot, $now );
        }
        if ( ! isset( $quote['status'] ) || 'eligible' !== $quote['status'] ) {
            return array(
                'status' => 'blocked',
                'reason' => $quote['reason'] ?? 'invalid_quote',
            );
        }
        $claim = MCPI_Order_Claim_Registry::claim( $db, $table, $intent, $signature, $secret, $now );
        if ( 'claimed' !== ( $claim['status'] ?? '' ) ) {
            // Never disclose the central pricing snapshot on a duplicate,
            // failed claim, stale state or conflict.
            return self::resume(
                $claim, $intent, $order_lookup, $caller_authorized
            );
        }
        return array(
            'status' => 'claimed',
            'claim_token' => $claim['claim_token'], // SERVER WORKER ONLY
            'quote' => $quote, // QUOTE ONLY, must be revalidated before order/hold
        );
    }
}
