<?php
/**
 * Milano PayTR callback semantics — OFFLINE, pure functions only.
 *
 * No REST route, no payment gateway, no order or ticket writes.
 * Purely validates PayTR step-2 shape/signature against one canonical
 * merchant_oid supplied by a trusted server-side order lookup.
 * Production integration MUST serialize state transition with a
 * durable DB lock or unique callback ledger; this class does not do so.
 *
 * Official formula: base64_encode(hash_hmac('sha256',
 *   merchant_oid . merchant_salt . status . total_amount, merchant_key, true)).
 * See https://dev.paytr.com/iframe-api/iframe-api-2-adim
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_PayTR_Callback_Contract {
    /**
     * @param array<string,mixed> $post PayTR callback fields, untrusted.
     * @param string $order_oid Exact order ID persisted by the canonical Woo store.
     * @param int $expected_amount_minor Canonical non-installment amount.
     * @param string $merchant_key Merchant private key, read from server-only config.
     * @param string $merchant_salt Merchant private salt, read from server-only config.
     * @return array<string,mixed>|WP_Error
     */
    public static function verify( array $post, $order_oid, $expected_amount_minor, $merchant_key, $merchant_salt ) {
        if ( ! is_string( $order_oid ) || ! preg_match( '/^[a-zA-Z0-9]{1,64}$/D', $order_oid )
            || ! is_int( $expected_amount_minor ) || $expected_amount_minor < 1
            || ! is_string( $merchant_key ) || strlen( $merchant_key ) < 8
            || ! is_string( $merchant_salt ) || strlen( $merchant_salt ) < 8 ) {
            return new WP_Error( 'milano_config', 'Callback verifier is not configured.' );
        }

        foreach ( array( 'merchant_oid', 'status', 'total_amount', 'hash' ) as $field ) {
            if ( ! isset( $post[ $field ] ) || ! is_string( $post[ $field ] ) ) {
                return new WP_Error( 'milano_fields', 'Invalid callback fields.' );
            }
        }
        if ( ! hash_equals( $order_oid, $post['merchant_oid'] )
            || ! in_array( $post['status'], array( 'success', 'failed' ), true )
            || ! preg_match( '/^[0-9]{1,15}$/D', $post['total_amount'] ) ) {
            return new WP_Error( 'milano_canonical_order', 'Invalid merchant order or outcome.' );
        }
        $received = base64_decode( $post['hash'], true );
        if ( ! is_string( $received ) || strlen( $received ) !== 32
            || base64_encode( $received ) !== $post['hash'] ) {
            return new WP_Error( 'milano_signature_format', 'Invalid signature encoding.' );
        }
        $expected = hash_hmac( 'sha256',
            $post['merchant_oid'] . $merchant_salt . $post['status'] . $post['total_amount'],
            $merchant_key, true );
        if ( ! hash_equals( $expected, $received ) ) {
            return new WP_Error( 'milano_bad_hash', 'Payment notification authentication failed.' );
        }

        // PayTR HMAC authenticates ONLY merchant_oid + status + total_amount.
        // currency, payment_amount and payment_type are additional POST fields
        // and are NOT part of the documented callback HMAC input. Never use
        // them to authorize, alter, or reject a canonical Woo order; they may
        // be missing/modified independently. Original currency (TL), amount,
        // merchant/test mode and order ownership MUST be read from the central
        // order and the previously authenticated PayTR iframe request instead.
        // total_amount may be GREATER than the initial amount on installments.
        if ( 'success' === $post['status']
            && (int) $post['total_amount'] < $expected_amount_minor ) {
            return new WP_Error(
                'milano_signed_amount_mismatch',
                'Signed charged amount is below canonical order amount.'
            );
        }
        return array(
            'merchant_oid' => $order_oid,
            'status' => $post['status'],
            'charged_minor' => (int) $post['total_amount'],
            'expected_minor' => $expected_amount_minor,
            'authentication' => 'hmac_sha256_verified_core_fields_only',
            'unsigned_supplemental_fields_ignored' => true,
        );
    }

    /**
     * Side-effect-free transition decision only. THIS IS NOT IDEMPOTENCY LOCKING.
     * Caller MUST read/write canonical payment status under a durable DB lock.
     * @return string apply|ack_only|conflict|invalid
     */
    public static function transition( $current, $incoming ) {
        if ( ! in_array( $incoming, array( 'success', 'failed' ), true ) ) {
            return 'invalid';
        }
        if ( 'pending' === $current ) {
            return 'apply';
        }
        if ( 'success' === $current || 'failed' === $current ) {
            return $current === $incoming ? 'ack_only' : 'conflict';
        }
        return 'invalid';
    }
}
