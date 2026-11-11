<?php
/**
 * Read-only canonical pre-payment order audit for a Milano family 2+2.
 *
 * OFFLINE DRAFT CONTRACT ONLY. No hook, REST endpoint, DB migration, PayTR
 * callback, Woo write, email, MDG hold change, QR or ticket is created.
 *
 * IMPORTANT: $spec must be assembled by a trusted SERVER using a verified
 * purchase-intent, canonical MDG session ownership and MMC price. Never take
 * IDs, amounts, or nonce from a browser request without re-verifying.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_Prepayment_Order_Audit {
    private static function fail( $code ) {
        return new WP_Error( 'milano_preflight_' . $code, 'The canonical Milano order is not ready for payment.' );
    }
    private static function canonical_spec( $s ) {
        $keys = array(
            'brand','intent','event_id','session_id','product_id',
            'child_variation_id','adult_variation_id',
            'child_type_id','adult_type_id','amount_minor','units'
        );
        if ( ! is_array( $s ) || count( $s ) !== count( $keys )
            || array_keys( $s ) !== $keys
            || $s['brand'] !== 'milano'
            || ! is_string( $s['intent'] )
            || ! preg_match( '/^[a-f0-9]{64}$/D', $s['intent'] ) ) {
            return false;
        }
        foreach ( array_slice( $keys, 2 ) as $key ) {
            if ( ! is_int( $s[ $key ] ) || $s[ $key ] < 1 ) { return false; }
        }
        return 4 === $s['units'] && 110000 === $s['amount_minor'];
    }
    private static function cents( $n ) {
        if ( ! is_string( $n ) && ! is_int( $n ) && ! is_float( $n ) ) { return -1; }
        $s = (string) $n;
        if ( ! preg_match( '/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/D', $s ) ) { return -1; }
        $parts = explode( '.', $s );
        return (int) $parts[0] * 100
            + ( isset( $parts[1] ) ? (int) str_pad( $parts[1], 2, '0' ) : 0 );
    }

    /**
     * Returns a non-sensitive audit result, never customer data.
     * Does NOT authorize taking money by itself: PayTR merchant/domain and
     * durable callback/sales checks are separate, not yet integrated.
     */
    public static function inspect( $order, $spec, $phase = 'payment' ) {
        if ( ! in_array( $phase, array( 'pre_reservation', 'payment' ), true ) ) {
            return self::fail( 'phase' );
        }
        global $wpdb;
        if ( ! self::canonical_spec( $spec ) ) { return self::fail( 'spec' ); }
        if ( ! is_object( $order )
            || ! method_exists( $order, 'get_id' )
            || ! method_exists( $order, 'get_items' )
            || ! method_exists( $order, 'get_meta' )
            || ! method_exists( $order, 'get_currency' )
            || ! method_exists( $order, 'get_status' )
            || ! method_exists( $order, 'get_total' )
            || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
            return self::fail( 'dependencies' );
        }

        $order_id = (int) $order->get_id();
        if ( $order_id < 1
            || (string) $order->get_meta( '_milano_brand', true ) !== 'milano'
            || (string) $order->get_meta( '_milano_intent_key', true ) !== $spec['intent']
            || (string) $order->get_meta( '_mdg_family22_packages', true ) !== '1'
            || (string) $order->get_status() !== 'pending'
            || (string) $order->get_currency() !== 'TRY'
            || self::cents( $order->get_total() ) !== $spec['amount_minor'] ) {
            return self::fail( 'order_identity_price_or_status' );
        }
        if ( method_exists( $order, 'get_date_paid' ) && $order->get_date_paid() ) {
            return self::fail( 'already_paid' );
        }

        $lines = array_values( (array) $order->get_items( 'line_item' ) );
        if ( count( $lines ) !== 2 ) { return self::fail( 'line_count' ); }
        $line_by_item_id = array();
        $components = array();
        $group = null;
        foreach ( $lines as $item ) {
            if ( ! is_object( $item ) || ! method_exists( $item, 'get_id' )
                || ! method_exists( $item, 'get_meta' )
                || ! method_exists( $item, 'get_quantity' )
                || ! method_exists( $item, 'get_variation_id' )
                || ! method_exists( $item, 'get_product_id' ) ) {
                return self::fail( 'line_type' );
            }
            $line_id = (int) $item->get_id();
            $variation = (int) $item->get_variation_id();
            $component = (string) $item->get_meta( '_mdg_family22_component', true );
            $group_now = (string) $item->get_meta( '_mdg_family22_group', true );
            $quantity = (int) $item->get_quantity();
            $expected_variation = 'child' === $component ? $spec['child_variation_id']
                : ( 'adult' === $component ? $spec['adult_variation_id'] : 0 );
            if ( ! $line_id || isset( $line_by_item_id[ $line_id ] )
                || isset( $components[ $component ] ) || ! $expected_variation
                || $variation !== $expected_variation
                || (int) $item->get_product_id() !== $spec['product_id']
                || $quantity !== 2
                || (string) $item->get_meta( '_mdg_family22', true ) !== '1'
                || (string) $item->get_meta( '_mdg_family22_packages', true ) !== '1'
                || self::cents( $item->get_meta( '_mdg_family22_package_price', true ) ) !== 110000
                || (int) $item->get_meta( '_mdg_family22_event_id', true ) !== $spec['event_id']
                || (int) $item->get_meta( '_mdg_family22_session_id', true ) !== $spec['session_id']
                || ! preg_match( '/^mdg-family22-[A-Za-z0-9_-]{6,180}$/D', $group_now )
                || ( null !== $group && $group_now !== $group ) ) {
                return self::fail( 'family_components' );
            }
            $group = $group_now;
            $line_by_item_id[ $line_id ] = array( 'component'=>$component, 'qty'=>$quantity );
            $components[ $component ] = $quantity;
        }
        if ( ! isset( $components['child'], $components['adult'] ) ) {
            return self::fail( 'family_mix' );
        }
        // The brand/intent/price and exact Woo ticket structure must pass
        // BEFORE invoking the stock-reservation owner. There cannot yet be a
        // hold or order_map at this phase, so it explicitly does NOT authorize
        // gateway initiation. Full MDG audit below is mandatory afterward.
        if ( 'pre_reservation' === $phase ) {
            return array(
                'read_only' => true, 'phase' => 'pre_reservation',
                'canonical_order_id' => $order_id,
                'event_id' => $spec['event_id'], 'session_id' => $spec['session_id'],
                'admission_codes_expected' => 4,
                'amount_minor' => $spec['amount_minor'], 'currency' => 'TRY',
                'may_start_payment_after_separate_gateway_auth' => false,
                'ticket_or_qr_created' => false,
            );
        }

        // MDG_Live_Sales::reserve_or_throw runs only on standard WC
        // checkout hooks. A custom wc_create_order() path MUST NOT bypass it.
        $table = $wpdb->prefix . 'mdg_order_map';
        $map = $wpdb->get_results( $wpdb->prepare(
            "SELECT order_item_id,event_id,session_id,ticket_type_id,quantity,units_per_ticket,units_total
             FROM {$table} WHERE order_id=%d LIMIT 5", $order_id
        ) );
        if ( ! is_array( $map ) || count( $map ) !== 2 ) { return self::fail( 'order_map_missing' ); }
        $mapped = array();
        $total_units = 0;
        foreach ( $map as $entry ) {
            $item_id = (int) $entry->order_item_id;
            if ( ! isset( $line_by_item_id[ $item_id ] ) || isset( $mapped[ $item_id ] ) ) {
                return self::fail( 'order_map_duplicate_or_foreign' );
            }
            $component = $line_by_item_id[ $item_id ]['component'];
            $expected_type = 'child' === $component ? $spec['child_type_id'] : $spec['adult_type_id'];
            if ( (int) $entry->event_id !== $spec['event_id']
                || (int) $entry->session_id !== $spec['session_id']
                || (int) $entry->ticket_type_id !== $expected_type
                || (int) $entry->quantity !== 2
                || (int) $entry->units_per_ticket !== 1
                || (int) $entry->units_total !== 2 ) {
                return self::fail( 'order_map_mismatch' );
            }
            $total_units += (int) $entry->units_total;
            $mapped[ $item_id ] = true;
        }
        if ( $total_units !== $spec['units'] ) { return self::fail( 'units' ); }

        // One central held reservation for these EXACT four admission units
        // must exist BEFORE a gateway can be initiated. An expired hold is
        // NOT eligible even if Woo status still reads pending.
        $table = $wpdb->prefix . 'mdg_holds';
        $holds = $wpdb->get_results( $wpdb->prepare(
            "SELECT session_id,units,status,expires_at FROM {$table}
             WHERE order_id=%d LIMIT 3", $order_id
        ) );
        if ( ! is_array( $holds ) || count( $holds ) !== 1 ) { return self::fail( 'hold_missing_or_duplicated' ); }
        $hold = $holds[0];
        if ( (int) $hold->session_id !== $spec['session_id']
            || (int) $hold->units !== $spec['units']
            || (string) $hold->status !== 'held'
            || ! is_string( $hold->expires_at )
            || $hold->expires_at <= gmdate( 'Y-m-d H:i:s' ) ) {
            return self::fail( 'hold_expired_or_mismatch' );
        }
        return array(
            'read_only' => true, 'canonical_order_id' => $order_id,
            'event_id' => $spec['event_id'], 'session_id' => $spec['session_id'],
            'admission_codes_expected' => 4, 'held_capacity_units' => 4,
            'amount_minor' => $spec['amount_minor'], 'currency' => 'TRY',
            'may_start_payment_after_separate_gateway_auth' => true,
            'ticket_or_qr_created' => false,
        );
    }
}
