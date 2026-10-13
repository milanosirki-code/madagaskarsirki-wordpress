<?php
/**
 * Milano existing Woo order resume gate -- STAGING CONTRACT ONLY.
 *
 * Pure read-only validation. The trusted central server must construct the
 * $order array from wc_get_order() + protected SERVER order metadata; NEVER
 * from the Milano/browser request. It must also authenticate the original
 * customer/session separately and provide strict true for $caller_authorized.
 *
 * No WordPress REST route, payment, Woo order creation, MDG hold, or QR hook.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Order_Retry_Verifier {
    /**
     * Fail closed if the existing request registry references a different,
     * deleted, cancelled, failed, refunded or untrusted WooCommerce order.
     * This is order-access eligibility, NOT a payment confirmation.
     *
     * Required central order snapshot shape:
     *   id, source_brand, event_id, session_id, request_id,
     *   intent_payload_hash, currency, status.
     */
    public static function allows( $order, $intent, $expected_order_id, $caller_authorized ) {
        if ( true !== $caller_authorized
            || ! function_exists( 'mcpi_schema_valid' ) || ! mcpi_schema_valid( $intent )
            || ! is_int( $expected_order_id ) || $expected_order_id < 1
            || ! is_array( $order ) ) {
            return false;
        }
        foreach ( array( 'id','source_brand','event_id','session_id',
            'request_id','intent_payload_hash','currency','status' ) as $key ) {
            if ( ! array_key_exists( $key, $order ) ) { return false; }
        }
        if ( ! is_int( $order['id'] ) || $order['id'] !== $expected_order_id
            || 'milano' !== $order['source_brand']
            || ! is_int( $order['event_id'] ) || $order['event_id'] !== $intent['event_id']
            || ! is_int( $order['session_id'] ) || $order['session_id'] !== $intent['session_id']
            || ! is_string( $order['request_id'] )
            || ! hash_equals( $intent['request_id'], $order['request_id'] )
            || 'TRY' !== $order['currency']
            || ! is_string( $order['status'] )
            || ! in_array( $order['status'],
                array( 'pending', 'on-hold', 'processing', 'completed' ), true ) ) {
            return false;
        }

        // An attached order must belong to the exact signed basket/request,
        // not merely the same event and seat time. Hash is created by
        // MCPI_Order_Claim_Registry over the canonical signed payload.
        $encoded = json_encode( $intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $encoded )
            || ! is_string( $order['intent_payload_hash'] )
            || ! preg_match( '/\\A[a-f0-9]{64}\\z/D', $order['intent_payload_hash'] ) ) {
            return false;
        }
        $digest = hash( 'sha256', "milano.checkout-intent.v1\\n" . $encoded );
        return hash_equals( $digest, $order['intent_payload_hash'] );
    }
}
