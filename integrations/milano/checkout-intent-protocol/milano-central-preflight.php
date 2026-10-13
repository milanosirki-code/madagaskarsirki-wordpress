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
    public static function begin( $db, $table, $intent, $signature, $secret, $now, $lookup ) {
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
            return array( 'status' => $prior['status'] ?? 'unavailable' )
                + ( 'existing_order' === ( $prior['status'] ?? '' )
                    && isset( $prior['order_id'] )
                    ? array( 'order_id' => $prior['order_id'] ) : array() );
        }

        try {
            // Fetch only from central MDG/MMC/Woo authoritative tables/methods.
            $snapshot = call_user_func( $lookup, $intent['event_id'], $intent['session_id'] );
        } catch ( Throwable $e ) {
            return array( 'status' => 'unavailable' );
        }
        $quote = MCPI_Central_Eligibility::validate( $intent, $snapshot, $now );
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
            return array( 'status' => $claim['status'] ?? 'unavailable' )
                + ( isset( $claim['order_id'] ) && 'existing_order' === $claim['status']
                    ? array( 'order_id' => $claim['order_id'] ) : array() );
        }
        return array(
            'status' => 'claimed',
            'claim_token' => $claim['claim_token'], // SERVER WORKER ONLY
            'quote' => $quote, // QUOTE ONLY, must be revalidated before order/hold
        );
    }
}
