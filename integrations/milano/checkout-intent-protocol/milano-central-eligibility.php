<?php
/**
 * Milano checkout eligibility / pricing — PURE CENTRAL CONTRACT.
 * Staging development only, NOT an installed WordPress plugin.
 *
 * Does not register routes or hooks, query WordPress, create/alter a Woo order,
 * reserve MDG capacity, call PayTR or issue Tickera tickets.
 *
 * TRUST BOUNDARY:
 * $intent is HMAC verified by mcpi_verify() BEFORE calling this class.
 * $snapshot MUST come from central server's MDG/MMC/Woo adapters, NOT the
 * client, Milano frontend, request body, headers, browser, or cached feed.
 * Revalidate all conditions again in the actual central order+hold transaction.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Central_Eligibility {
    private static function blocked( $reason ) {
        return array( 'status' => 'blocked', 'reason' => $reason );
    }

    private static function valid_window( $start, $end, $now ) {
        if ( null !== $start && ( ! is_int( $start ) || $start > $now ) ) { return false; }
        if ( null !== $end && ( ! is_int( $end ) || $end <= $now ) ) { return false; }
        if ( null !== $start && null !== $end && $end <= $start ) { return false; }
        return true;
    }

    /** Return an untrusted-for-settlement snapshot of CENTRAL prices/units. */
    public static function validate( $intent, $snapshot, $now ) {
        if ( ! function_exists( 'mcpi_schema_valid' )
            || ! mcpi_schema_valid( $intent )
            || ! is_int( $now ) || $now <= 0
            || ! is_array( $snapshot )
            || ! isset( $snapshot['event'], $snapshot['session'], $snapshot['product'], $snapshot['tickets'] )
            || ! is_array( $snapshot['event'] ) || ! is_array( $snapshot['session'] )
            || ! is_array( $snapshot['product'] ) || ! is_array( $snapshot['tickets'] ) ) {
            return self::blocked( 'invalid_contract' );
        }

        $e = $snapshot['event'];
        $s = $snapshot['session'];
        $p = $snapshot['product'];

        if ( ! isset( $e['id'], $e['owner_brand'], $e['status'] )
            || ! is_int( $e['id'] ) || $e['id'] !== $intent['event_id']
            || 'milano' !== $e['owner_brand'] || 'onsale' !== $e['status'] ) {
            return self::blocked( 'event_not_owned_or_open' );
        }

        if ( ! isset( $s['id'], $s['event_id'], $s['status'], $s['start_utc'],
                        $s['closed_by_time'], $s['wc_product_id'], $s['tickera_event_id'],
                        $s['capacity_total'], $s['available_units'] )
            || ! is_int( $s['id'] ) || $s['id'] !== $intent['session_id']
            || ! is_int( $s['event_id'] ) || $s['event_id'] !== $e['id']
            || 'onsale' !== $s['status']
            || ! is_int( $s['start_utc'] ) || $s['start_utc'] <= $now
            || false !== $s['closed_by_time']
            || ! is_int( $s['wc_product_id'] ) || $s['wc_product_id'] <= 0
            || ! is_int( $s['tickera_event_id'] ) || $s['tickera_event_id'] <= 0
            || ! is_int( $s['capacity_total'] ) || $s['capacity_total'] < 1
            || ! is_int( $s['available_units'] ) || $s['available_units'] < 0
            || $s['available_units'] > $s['capacity_total'] ) {
            return self::blocked( 'session_not_available' );
        }

        foreach ( array( $e, $s ) as $record ) {
            if ( ! array_key_exists( 'sale_start_utc', $record )
                || ! array_key_exists( 'sale_end_utc', $record )
                || ! self::valid_window( $record['sale_start_utc'], $record['sale_end_utc'], $now ) ) {
                return self::blocked( 'sale_window_closed' );
            }
        }

        if ( ! isset( $p['id'], $p['published'], $p['variable'], $p['currency'] )
            || ! is_int( $p['id'] ) || $p['id'] !== $s['wc_product_id']
            || true !== $p['published'] || true !== $p['variable']
            || 'TRY' !== $p['currency'] ) {
            return self::blocked( 'product_not_usable' );
        }

        $types = array();
        foreach ( $snapshot['tickets'] as $type ) {
            if ( ! is_array( $type ) || ! isset( $type['code'] )
                || ! is_string( $type['code'] ) || isset( $types[$type['code']] ) ) {
                return self::blocked( 'ticket_catalogue_invalid' );
            }
            $types[$type['code']] = $type;
        }

        $lines = array();
        $total_cents = 0;
        $total_units = 0;
        foreach ( $intent['items'] as $requested ) {
            $code = $requested['code'];
            if ( ! isset( $types[$code] ) ) { return self::blocked( 'ticket_type_missing' ); }
            $t = $types[$code];
            if ( ! isset( $t['active'], $t['variation_id'], $t['variation_parent_id'],
                            $t['purchasable'], $t['in_stock'], $t['capacity_units'],
                            $t['price_cents'], $t['woo_price_cents'] )
                || true !== $t['active'] || true !== $t['purchasable'] || true !== $t['in_stock']
                || ! is_int( $t['variation_id'] ) || $t['variation_id'] < 1
                || ! is_int( $t['variation_parent_id'] ) || $t['variation_parent_id'] !== $p['id']
                || ! is_int( $t['capacity_units'] ) || $t['capacity_units'] < 1 || $t['capacity_units'] > 50
                || ! is_int( $t['price_cents'] ) || $t['price_cents'] < 0
                || ! is_int( $t['woo_price_cents'] ) || $t['woo_price_cents'] !== $t['price_cents'] ) {
                return self::blocked( 'ticket_product_mismatch' );
            }
            $qty = $requested['quantity'];
            $line_units = $t['capacity_units'] * $qty;
            $line_cents = $t['price_cents'] * $qty;
            if ( $line_cents > 100000000 || $total_cents + $line_cents > 100000000 ) {
                return self::blocked( 'amount_over_limit' );
            }
            $total_units += $line_units;
            $total_cents += $line_cents;
            $lines[] = array(
                'code' => $code,
                'quantity' => $qty,
                'variation_id' => $t['variation_id'],
                'unit_price_cents' => $t['price_cents'],
                'line_total_cents' => $line_cents,
                'capacity_units_per_ticket_type' => $t['capacity_units'],
                'line_admission_units' => $line_units,
            );
        }
        if ( $total_units < 1 || $total_units > 100 ) {
            return self::blocked( 'admission_limit' );
        }
        if ( $s['available_units'] < $total_units ) {
            return self::blocked( 'insufficient_capacity' );
        }
        // This pilot covers paid PayTR checkout only; complimentary tickets
        // require a separate approved brand-specific issuance workflow.
        if ( $total_cents <= 0 ) { return self::blocked( 'no_payable_total' ); }

        return array(
            'status' => 'eligible',
            'owner_brand' => 'milano',
            'event_id' => $e['id'],
            'session_id' => $s['id'],
            'wc_product_id' => $p['id'],
            'tickera_event_id' => $s['tickera_event_id'],
            'currency' => 'TRY',
            'total_cents' => $total_cents,
            'admission_units' => $total_units,
            'lines' => $lines,
            // IMPORTANT: no WooCommerce order, PayTR token, MDG hold or QR.
            'quote_only' => true,
        );
    }
}
