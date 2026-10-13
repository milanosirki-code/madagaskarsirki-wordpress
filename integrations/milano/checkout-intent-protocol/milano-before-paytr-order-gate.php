<?php
/**
 * Milano central Woo order & MDG hold -> PayTR token gate.
 *
 * REVIEW/STAGING ONLY. PURE CHECK: no WP plugin, REST endpoint, Woo order
 * creation, MDG stock mutation, iframe token, gateway/QR or ticket issuance.
 *
 * All inputs MUST come from the authenticated central worker's trusted
 * server-side reads. Never pass browser snapshots/order metadata directly.
 * PayTR's payment_amount is a 100x integer value; only the proven canonical
 * Woo total may become that value AFTER this gate passes.
 *
 * Tax policy: explicitly supported only when Woo has tax calculation
 * disabled and trusted VAT/tax order totals are 0. This says NOTHING about
 * the legal KDV invoice obligation; enable taxation only after a separately
 * audited per-line inclusive/exclusive VAT model.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Before_PayTR_Order_Gate {
    private static function reject( $reason ) {
        return array( 'status' => 'blocked', 'reason' => $reason );
    }

    private static function positive_id( $id ) {
        return is_int( $id ) && $id > 0;
    }

    private static function cents( $value, $zero = false ) {
        return is_int( $value ) && ( $zero ? $value >= 0 : $value > 0 )
            && $value <= 100000000;
    }

    /** Fail closed before requesting PayTR iFrame token. */
    public static function check( $quote, $order, $hold, $tax_calculation_enabled ) {
        if ( ! is_array( $quote ) || ! is_array( $order )
            || ! is_array( $hold ) || ! is_bool( $tax_calculation_enabled )
            || ! isset( $quote['owner_brand'], $quote['event_id'],
                $quote['session_id'], $quote['wc_product_id'],
                $quote['currency'], $quote['total_cents'],
                $quote['admission_units'], $quote['lines'],
                $quote['quote_only'] )
            || ! isset( $order['id'], $order['source_brand'], $order['event_id'],
                $order['session_id'], $order['wc_product_id'], $order['currency'],
                $order['status'], $order['payment_method'],
                $order['total_cents'], $order['total_tax_cents'],
                $order['lines'], $order['fees'], $order['checkout_request_id'] )
            || ! isset( $hold['order_id'], $hold['event_id'],
                $hold['session_id'], $hold['units'], $hold['state'],
                $hold['owner_brand'] )
            || 'milano' !== $quote['owner_brand']
            || 'milano' !== $order['source_brand']
            || 'milano' !== $hold['owner_brand']
            || 'TRY' !== $quote['currency'] || 'TRY' !== $order['currency']
            || 'pending' !== $order['status']
            || 'paytr_payment_gateway' !== $order['payment_method']
            || true !== $quote['quote_only']
            || ! self::positive_id( $order['id'] )
            || ! self::positive_id( $quote['event_id'] )
            || ! self::positive_id( $quote['session_id'] )
            || ! self::positive_id( $quote['wc_product_id'] )
            || $order['event_id'] !== $quote['event_id']
            || $order['session_id'] !== $quote['session_id']
            || $order['wc_product_id'] !== $quote['wc_product_id']
            || $hold['order_id'] !== $order['id']
            || $hold['event_id'] !== $quote['event_id']
            || $hold['session_id'] !== $quote['session_id']
            || 'held' !== $hold['state']
            || ! self::cents( $quote['total_cents'] )
            || ! self::cents( $order['total_cents'] )
            || ! self::cents( $order['total_tax_cents'], true )
            || ! is_int( $quote['admission_units'] )
            || $quote['admission_units'] < 1
            || $quote['admission_units'] > 100
            || $hold['units'] !== $quote['admission_units']
            || ! is_string( $order['checkout_request_id'] )
            || ! preg_match( '/\\A[a-f0-9]{32}\\z/D', $order['checkout_request_id'] )
            || ! is_array( $quote['lines'] ) || ! is_array( $order['lines'] )
            || ! is_array( $order['fees'] ) || ! $quote['lines'] ) {
            return self::reject( 'wrong_order_identity_or_hold' );
        }

        if ( $tax_calculation_enabled || $order['total_tax_cents'] !== 0 ) {
            return self::reject( 'unreviewed_tax_mode' );
        }

        $want = array();
        $admissions = 0;
        $sub_total = 0;
        foreach ( $quote['lines'] as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['code'], $item['quantity'],
                $item['variation_id'], $item['line_total_cents'],
                $item['line_admission_units'], $item['capacity_units_per_ticket_type'] )
                || ! is_string( $item['code'] )
                || ! preg_match( '/\\A[A-Z0-9_-]{1,40}\\z/D', $item['code'] )
                || ! self::positive_id( $item['variation_id'] )
                || ! is_int( $item['quantity'] ) || $item['quantity'] < 1
                || $item['quantity'] > 20
                || ! self::cents( $item['line_total_cents'] )
                || ! is_int( $item['line_admission_units'] )
                || ! is_int( $item['capacity_units_per_ticket_type'] )
                || $item['capacity_units_per_ticket_type'] !== 1
                || $item['line_admission_units'] !== $item['quantity']
                || isset( $want[$item['code']] ) ) {
                return self::reject( 'invalid_canonical_lines' );
            }
            $want[$item['code']] = $item;
            $sub_total += $item['line_total_cents'];
            $admissions += $item['line_admission_units'];
        }
        if ( $admissions !== $quote['admission_units']
            || count( $want ) !== count( $order['lines'] )
            || $sub_total > 100000000 ) {
            return self::reject( 'missing_or_wrong_admission_units' );
        }

        $seen = array();
        $wc_subtotal = 0;
        foreach ( $order['lines'] as $line ) {
            if ( ! is_array( $line ) || ! isset( $line['id'], $line['code'],
                $line['variation_id'], $line['quantity'], $line['subtotal_cents'],
                $line['total_cents'], $line['total_tax_cents'],
                $line['session_id'] )
                || ! self::positive_id( $line['id'] )
                || ! is_string( $line['code'] )
                || ! isset( $want[$line['code']] )
                || isset( $seen[$line['code']] )
                || ! self::cents( $line['subtotal_cents'] )
                || ! self::cents( $line['total_cents'] )
                || $line['total_tax_cents'] !== 0
                || $line['variation_id'] !== $want[$line['code']]['variation_id']
                || $line['quantity'] !== $want[$line['code']]['quantity']
                || $line['subtotal_cents'] !== $want[$line['code']]['line_total_cents']
                || $line['total_cents'] !== $line['subtotal_cents']
                || $line['session_id'] !== $quote['session_id'] ) {
                return self::reject( 'woo_lines_differ_from_canonical_quote' );
            }
            $seen[$line['code']] = $line['id'];
            $wc_subtotal += $line['total_cents'];
        }
        if ( $wc_subtotal !== $sub_total || count( $seen ) !== count( $want )
            || count( array_unique( array_values( $seen ) ) ) !== count( $seen ) ) {
            return self::reject( 'woo_lines_inconsistent' );
        }

        $discount = $quote['family_discount_cents'] ?? 0;
        if ( ! self::cents( $discount, true )
            || ( isset( $quote['component_subtotal_cents'] )
                && $quote['component_subtotal_cents'] !== $sub_total )
            || $discount > $sub_total
            || $sub_total - $discount !== $quote['total_cents'] ) {
            return self::reject( 'canonical_family_math_mismatch' );
        }

        // Exactly one family fee line for a discounted 2+2 basket. Never
        // allow arbitrary fees, duplicate coupons or client-provided discounts.
        if ( 0 === $discount && $order['fees'] ) {
            return self::reject( 'unexpected_woo_fee' );
        }
        if ( $discount > 0 ) {
            if ( count( $order['fees'] ) !== 1
                || ! is_array( $order['fees'][0] )
                || ( $order['fees'][0]['code'] ?? null ) !== 'MILANO_FAMILY22_DISCOUNT'
                || ( $order['fees'][0]['total_cents'] ?? null ) !== -$discount
                || ( $order['fees'][0]['tax_cents'] ?? null ) !== 0 ) {
                return self::reject( 'family_discount_fee_mismatch' );
            }
        }
        if ( $order['total_cents'] !== $quote['total_cents']
            || $order['total_cents'] !== $wc_subtotal - $discount ) {
            return self::reject( 'order_payment_total_mismatch' );
        }

        // Do not compute a PayTR token or silently settle an order here.
        return array(
            'status' => 'eligible',
            'payment_amount_cents' => $order['total_cents'],
            'currency' => 'TRY',
            'admission_units' => $admissions,
            'family_discount_cents' => $discount,
            'order_id' => $order['id'], // FOR TRUSTED CENTRAL WORKER ONLY
            'token_requested' => false,
        );
    }
}
