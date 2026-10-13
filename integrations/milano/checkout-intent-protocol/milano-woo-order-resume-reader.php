<?php
/**
 * Milano trusted Woo order resume reader -- REVIEW / STAGING ONLY.
 *
 * Uses wc_get_order() and Woo CRUD/HPOS methods, not direct wp_postmeta SQL.
 * Never trusts user-submitted order metadata, browser customer_id, Origin,
 * Referer or an order ID by itself. This is read-only; no plugin hooks.
 *
 * The trusted_customer_id MUST be obtained from authenticated CENTRAL
 * WordPress identity, not from a client parameter or a signed intent.
 * Guest orders fail closed until a separately reviewed email/OTP ownership
 * grant is implemented. This restricts recovery ONLY, not initial checkout.
 *
 * RESERVED meta contract for a FUTURE central order worker (NOT INSTALLED):
 *   _mcpi_source_brand = milano
 *   _mcpi_event_id = canonical MDG event ID
 *   _mcpi_session_id = canonical MDG session ID
 *   _mcpi_request_id = signed request ID
 *   _mcpi_intent_payload_hash = SHA256 over canonical v1 signed intent
 *
 * No current order is assumed to possess these metadata keys. Missing or
 * malformed metadata fail closed; legacy Madagaskar orders are unchanged.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Woo_Order_Resume_Reader {
    private static function positive_id( $value ) {
        if ( is_int( $value ) ) {
            return $value > 0 && $value <= PHP_INT_MAX ? $value : null;
        }
        if ( ! is_string( $value )
            || ! preg_match( '/\A[1-9][0-9]{0,17}\z/D', $value ) ) {
            return null;
        }
        // Reject silent overflow, zero, negative, decimals, scientific forms.
        $n = filter_var( $value, FILTER_VALIDATE_INT,
            array( 'options' => array( 'min_range' => 1 ) ) );
        return false === $n ? null : $n;
    }

    /**
     * Read a central WooCommerce order and test a VERIFIED customer's ID.
     * Null means no release. The returned snapshot contains no contact PII,
     * payment credentials, URLs or buyer identity. It is NOT payment proof.
     */
    public static function lookup( $order_id, $trusted_customer_id ) {
        $id = self::positive_id( $order_id );
        $customer = self::positive_id( $trusted_customer_id );
        if ( null === $id || null === $customer
            || ! function_exists( 'wc_get_order' ) ) {
            return null;
        }

        try {
            $order = wc_get_order( $id );
            if ( ! is_object( $order ) ) { return null; }
            foreach ( array( 'get_id', 'get_customer_id', 'get_meta',
                'get_currency', 'get_status' ) as $method ) {
                if ( ! method_exists( $order, $method ) ) { return null; }
            }

            if ( self::positive_id( $order->get_id() ) !== $id
                || self::positive_id( $order->get_customer_id() ) !== $customer ) {
                return null;
            }

            // Strict, private, provenance-specific order metadata. Existing
            // Madagaskar/legacy orders lack these keys and are not resumed.
            $brand = $order->get_meta( '_mcpi_source_brand', true, 'edit' );
            $event_id = self::positive_id(
                $order->get_meta( '_mcpi_event_id', true, 'edit' ) );
            $session_id = self::positive_id(
                $order->get_meta( '_mcpi_session_id', true, 'edit' ) );
            $request_id = $order->get_meta( '_mcpi_request_id', true, 'edit' );
            $payload_hash = $order->get_meta(
                '_mcpi_intent_payload_hash', true, 'edit' );
            $currency = $order->get_currency();
            $status = $order->get_status();

            if ( 'milano' !== $brand
                || null === $event_id || null === $session_id
                || ! is_string( $request_id )
                || ! preg_match( '/\A[a-f0-9]{32}\z/D', $request_id )
                || ! is_string( $payload_hash )
                || ! preg_match( '/\A[a-f0-9]{64}\z/D', $payload_hash )
                || 'TRY' !== $currency
                || ! is_string( $status )
                || ! in_array( $status,
                    array( 'pending', 'on-hold', 'processing', 'completed' ), true ) ) {
                return null;
            }

            return array(
                'id' => $id,
                'source_brand' => 'milano',
                'event_id' => $event_id,
                'session_id' => $session_id,
                'request_id' => $request_id,
                'intent_payload_hash' => $payload_hash,
                'currency' => 'TRY',
                'status' => $status,
            );
        } catch ( Throwable $e ) {
            // Don't leak Woo/customer internals or pretend availability.
            return null;
        }
    }
}
