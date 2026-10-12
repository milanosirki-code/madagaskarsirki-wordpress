<?php
/**
 * Milano / PayTR callback identity preflight -- REVIEW / STAGING ONLY.
 *
 * No WordPress hooks, REST routes, vendor callback changes, Woo writes, PayTR
 * actions, capacity hold, or Tickera issuance. Not production middleware.
 *
 * The caller MUST build $order from a fresh trusted central WooCommerce order
 * and its own immutable checkout identity, never the callback or browser.
 * A "ready_*" answer does NOT grant permission to change an order: real
 * callback integration also needs an atomic settlement claim and ticket
 * idempotency. Signed PayTR total_amount MAY exceed initial payment_amount
 * on installments; this prototype sends such a case for reconciliation.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_PayTR_Callback_Preflight {
    private static function decision( $status, $reason ) {
        return array( 'status' => $status, 'reason' => $reason );
    }

    private static function cents( $value, $allow_zero = false ) {
        if ( ! is_string( $value ) && ! is_int( $value ) ) { return null; }
        $raw = (string) $value;
        if ( ! preg_match( '/\A(?:0|[1-9][0-9]{0,10})\z/D', $raw ) ) { return null; }
        $num = (int) $raw;
        return ( $num > 0 || ( $allow_zero && 0 === $num ) ) ? $num : null;
    }

    /**
     * $notice: raw PayTR callback fields, with status and total_amount bound by
     *          verified HMAC (payment_amount/currency are NOT hash inputs).
     * $order:  trusted normalized central order snapshot.
     * $key/$salt: injected from protected server-only configuration.
     */
    public static function check( $notice, $order, $key, $salt ) {
        if ( ! is_array( $notice ) || ! is_array( $order )
            || ! is_string( $key ) || strlen( $key ) < 16
            || ! is_string( $salt ) || '' === $salt ) {
            return self::decision( 'rejected', 'invalid_input' );
        }
        foreach ( array( 'merchant_oid', 'status', 'total_amount', 'hash' ) as $field ) {
            if ( ! isset( $notice[$field] ) || ! is_string( $notice[$field] ) ) {
                return self::decision( 'rejected', 'missing_signed_fields' );
            }
        }
        $oid = $notice['merchant_oid'];
        $status = $notice['status'];
        $amount = self::cents( $notice['total_amount'], true );
        if ( ! preg_match( '/\A[0-9]{10,13}PAYTRWOO([1-9][0-9]*)\z/D', $oid, $match )
            || ! in_array( $status, array( 'success', 'failed' ), true )
            || null === $amount || ! preg_match( '/\A[A-Za-z0-9+\/]{43}=\z/D', $notice['hash'] ) ) {
            return self::decision( 'rejected', 'invalid_signed_fields' );
        }

        // Verify before trusting status, paid total, merchant oid or order link.
        $computed = base64_encode( hash_hmac(
            'sha256', $oid . $salt . $status . $notice['total_amount'], $key, true
        ) );
        if ( ! hash_equals( $computed, $notice['hash'] ) ) {
            return self::decision( 'rejected', 'invalid_signature' );
        }

        if ( ! isset( $order['id'], $order['source_brand'], $order['payment_method'],
                        $order['currency'], $order['status'], $order['total_cents'],
                        $order['paytr_pending_order_id'], $order['attempts'] )
            || ! is_int( $order['id'] ) || $order['id'] < 1
            || (string) $order['id'] !== $match[1]
            || 'milano' !== $order['source_brand']
            || 'paytr_payment_gateway' !== $order['payment_method']
            || 'TRY' !== $order['currency']
            || ! is_string( $order['paytr_pending_order_id'] )
            || ! hash_equals( $order['paytr_pending_order_id'], $oid )
            || ! is_array( $order['attempts'] ) ) {
            return self::decision( 'blocked', 'order_identity_or_brand' );
        }

        $original = self::cents( $order['total_cents'] );
        if ( null === $original ) {
            return self::decision( 'blocked', 'invalid_order_total' );
        }
        // Exactly one successful token-generation attempt for this identity.
        $matches = array();
        foreach ( $order['attempts'] as $attempt ) {
            if ( is_array( $attempt ) && isset( $attempt['merchant_oid'] )
                && is_string( $attempt['merchant_oid'] )
                && hash_equals( $oid, $attempt['merchant_oid'] ) ) {
                $matches[] = $attempt;
            }
        }
        if ( 1 !== count( $matches ) ) {
            return self::decision( 'blocked', 'missing_or_duplicate_attempt' );
        }
        $attempt = $matches[0];
        if ( ! isset( $attempt['status'], $attempt['currency'], $attempt['amount'] )
            || 'success' !== $attempt['status']
            || 'TRY' !== $attempt['currency']
            || self::cents( $attempt['amount'] ) !== $original ) {
            return self::decision( 'blocked', 'attempt_not_authorized' );
        }

        // These auxiliary callback fields are NOT signed by the PayTR hash.
        // Comparing them only catches inconsistencies: it cannot establish
        // financial authenticity or replace the original stored amount.
        if ( array_key_exists( 'payment_amount', $notice )
            && self::cents( $notice['payment_amount'] ) !== $original ) {
            return self::decision( 'blocked', 'auxiliary_amount_mismatch' );
        }
        if ( array_key_exists( 'currency', $notice ) && 'TL' !== $notice['currency'] ) {
            return self::decision( 'blocked', 'auxiliary_currency_mismatch' );
        }

        // Paid/refunded/cancelled orders must NEVER be issued tickets again.
        if ( in_array( $order['status'],
            array( 'processing', 'completed', 'refunded', 'cancelled' ), true ) ) {
            return self::decision( 'already_final', 'no_repeat_settlement' );
        }
        if ( 'pending' !== $order['status'] ) {
            return self::decision( 'blocked', 'invalid_order_state' );
        }
        if ( 'failed' === $status ) {
            return self::decision( 'ready_failure', 'matching_signed_failure' );
        }
        if ( $amount < $original ) {
            return self::decision( 'blocked', 'underpayment' );
        }
        if ( $amount > $original ) {
            // May be legitimate PayTR installment difference; NOT auto-reject.
            // Do not calculate fee or change order until provider reconciliation.
            return self::decision( 'manual_review', 'installment_or_amount_difference' );
        }
        return self::decision( 'ready_success', 'matching_signed_success' );
    }
}
