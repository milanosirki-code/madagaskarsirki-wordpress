<?php
/**
 * Milano canonical Woo order idempotency ledger — OFFLINE CONTRACT ONLY.
 *
 * NO automatic table installation, REST route, Woo order creation, PayTR
 * payment or ticket issuance. This class must not be loaded in live checkout
 * until brand, quote, nonce, gateway and customer-message QA are complete.
 *
 * One unique client purchase-intent nonce -> one immutable server quote and,
 * after the future Woo adapter binds it, at most one canonical Woo order.
 * Any ambiguous/crashed claim is BLOCKED for manual reconciliation rather than
 * generating another charge/order/hold.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_Canonical_Order_Claim {
    const TABLE = 'milano_order_claims_v1';

    /** Schema is a reference for an explicit, separately approved migration. */
    public static function schema_sql( $prefix ) {
        if ( ! is_string( $prefix ) || ! preg_match( '/^[A-Za-z0-9_]+$/D', $prefix ) ) {
            return new WP_Error( 'milano_schema_prefix', 'Invalid WordPress table prefix.' );
        }
        $table = $prefix . self::TABLE;
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            intent_key char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            quote_sha256 char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            brand varchar(16) NOT NULL DEFAULT 'milano',
            event_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned NOT NULL,
            amount_minor bigint(20) unsigned NOT NULL,
            capacity_units int(10) unsigned NOT NULL,
            state varchar(16) NOT NULL DEFAULT 'claimed',
            wc_order_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY intent_key (intent_key),
            UNIQUE KEY wc_order_id (wc_order_id),
            KEY event_session (event_id,session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }

    /** Validate only trusted, pre-authorized / pre-repriced server-side fields. */
    private static function valid( $intent, $quote, $event_id, $session_id, $amount, $units ) {
        return is_string( $intent ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $intent )
            && is_string( $quote ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $quote )
            && is_int( $event_id ) && $event_id > 0
            && is_int( $session_id ) && $session_id > 0
            && is_int( $amount ) && $amount > 0
            && is_int( $units ) && $units >= 1 && $units <= 20;
    }

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    private static function row( $intent ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE intent_key = %s LIMIT 1',
            $intent
        ) );
    }

    /**
     * Atomic, durable first-writer-wins claim. NEVER create a second Woo order
     * if result.state is claimed; another request may be creating it now.
     * Returns array with 'created' bool or WP_Error for mismatched reuse.
     *
     * IMPORTANT: The caller MUST previously enforce Milano-only event/brand,
     * signed short-lived intent, correct quote/availability and session mapping.
     */
    public static function claim( $intent, $quote, $event_id, $session_id, $amount, $units ) {
        global $wpdb;
        if ( ! self::valid( $intent, $quote, $event_id, $session_id, $amount, $units ) ) {
            return new WP_Error( 'milano_claim_invalid', 'Invalid canonical order claim.' );
        }
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
            return new WP_Error( 'milano_claim_db_unavailable', 'Database unavailable.' );
        }
        $now = gmdate( 'Y-m-d H:i:s' );
        $affected = $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() .
            " (intent_key,quote_sha256,brand,event_id,session_id,amount_minor,capacity_units,state,wc_order_id,created_at,updated_at)
             VALUES (%s,%s,'milano',%d,%d,%d,%d,'claimed',NULL,%s,%s)
             ON DUPLICATE KEY UPDATE intent_key = intent_key",
            $intent, $quote, $event_id, $session_id, $amount, $units, $now, $now
        ) );
        if ( false === $affected ) {
            return new WP_Error( 'milano_claim_db_error', 'Canonical intent could not be reserved.' );
        }
        $r = self::row( $intent );
        if ( ! $r ) {
            return new WP_Error( 'milano_claim_missing', 'Intent claim readback failed.' );
        }
        if ( (string) $r->brand !== 'milano'
            || ! hash_equals( $quote, (string) $r->quote_sha256 )
            || (int) $r->event_id !== $event_id
            || (int) $r->session_id !== $session_id
            || (int) $r->amount_minor !== $amount
            || (int) $r->capacity_units !== $units
            || ! in_array( (string) $r->state, array( 'claimed', 'bound' ), true ) ) {
            return new WP_Error(
                'milano_claim_conflict',
                'This purchase-intent key is already bound to a different canonical request.'
            );
        }
        return array(
            'created' => 1 === (int) $affected,
            'state' => (string) $r->state,
            'wc_order_id' => $r->wc_order_id ? (int) $r->wc_order_id : null,
            // No customer PII, gateway credentials or signature is stored.
            'intent_key' => (string) $r->intent_key,
        );
    }

    /**
     * Attach the one real Woo order only AFTER its creation has been audited.
     * Atomic compare-and-swap, with UNIQUE wc_order_id. If a Woo create succeeds
     * but the worker crashes before attach, leave claim in 'claimed'; never
     * retry blindly. Reconcile Woo orders by protected intent meta manually.
     */
    public static function attach_order( $intent, $woo_order_id ) {
        global $wpdb;
        if ( ! is_string( $intent ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $intent )
            || ! is_int( $woo_order_id ) || $woo_order_id < 1 ) {
            return new WP_Error( 'milano_order_bind_invalid', 'Invalid order bind.' );
        }
        $now = gmdate( 'Y-m-d H:i:s' );
        $affected = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() .
            " SET wc_order_id = %d, state = 'bound', updated_at = %s
              WHERE intent_key = %s AND brand = 'milano'
                AND state = 'claimed' AND wc_order_id IS NULL",
            $woo_order_id, $now, $intent
        ) );
        if ( false === $affected ) {
            return new WP_Error( 'milano_order_bind_conflict', 'Order binding failed, possibly duplicate WC order ID.' );
        }
        $r = self::row( $intent );
        if ( $r && 'milano' === (string) $r->brand
            && 'bound' === (string) $r->state && (int) $r->wc_order_id === $woo_order_id ) {
            return array( 'wc_order_id' => $woo_order_id, 'replayed' => 1 !== (int) $affected );
        }
        return new WP_Error( 'milano_order_bind_conflict', 'Intent already bound to another order or missing.' );
    }
}
