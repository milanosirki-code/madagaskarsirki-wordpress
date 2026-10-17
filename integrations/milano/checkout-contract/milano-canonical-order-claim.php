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
        // A plain INSERT with an actual UNIQUE constraint is the creator
        // election. NEVER use INSERT ... ON DUPLICATE KEY UPDATE no-op and
        // derive first-writer status from affected_rows: MySQL connections
        // with CLIENT_FOUND_ROWS may report 1 for an EXISTING row.
        // Duplicate-key INSERT returns false; read the unique row below and
        // classify it as a replay only if every immutable field matches.
        $inserted = $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . self::table() .
            " (intent_key,quote_sha256,brand,event_id,session_id,amount_minor,capacity_units,state,wc_order_id,created_at,updated_at)
             VALUES (%s,%s,'milano',%d,%d,%d,%d,'claimed',NULL,%s,%s)",
            $intent, $quote, $event_id, $session_id, $amount, $units, $now, $now
        ) );
        if ( false !== $inserted && 1 !== (int) $inserted ) {
            return new WP_Error( 'milano_claim_insert_anomaly', 'Unexpected canonical intent insert count.' );
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
            || ! in_array( (string) $r->state, array( 'claimed', 'bound', 'reserving', 'ready' ), true ) ) {
            return new WP_Error(
                'milano_claim_conflict',
                'This purchase-intent key is already bound to a different canonical request.'
            );
        }
        return array(
            'created' => 1 === (int) $inserted,
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
    /**
     * Read an EXISTING trusted purchase-intent claim, WITHOUT creating one.
     * This must be used at the standard Woo pre-payment hooks: creating a new
     * claim after the Woo order exists would defeat first-writer order election.
     *
     * @return array|WP_Error
     */
    public static function existing( $intent, $quote, $event_id, $session_id, $amount, $units ) {
        global $wpdb;
        if ( ! self::valid( $intent, $quote, $event_id, $session_id, $amount, $units ) ) {
            return new WP_Error( 'milano_claim_invalid', 'Invalid canonical intent lookup.' );
        }
        if ( ! is_object( $wpdb ) ) {
            return new WP_Error( 'milano_claim_db_unavailable', 'Database unavailable.' );
        }
        $r = self::row( $intent );
        if ( ! $r ) {
            return new WP_Error( 'milano_claim_unreserved', 'No prior purchase-intent claim; checkout blocked.' );
        }
        if ( (string) $r->brand !== 'milano'
            || ! hash_equals( $quote, (string) $r->quote_sha256 )
            || (int) $r->event_id !== $event_id
            || (int) $r->session_id !== $session_id
            || (int) $r->amount_minor !== $amount
            || (int) $r->capacity_units !== $units
            || ! in_array( (string) $r->state, array( 'claimed', 'bound', 'reserving', 'ready' ), true ) ) {
            return new WP_Error( 'milano_claim_conflict', 'Canonical intent does not match trusted quote and ownership.' );
        }
        return array(
            'state' => (string) $r->state,
            'wc_order_id' => $r->wc_order_id ? (int) $r->wc_order_id : null,
            'intent_key' => (string) $r->intent_key,
        );
    }


    /**
     * Claim the MDG reservation lifecycle once for one immutable central order.
     *
     * bound -> reserving: only the winning caller may invoke MDG core.
     * reserving -> ready: only after the complete order_map and hold audit.
     *
     * An ambiguous "reserving" state is intentionally NOT retried; reconcile
     * the central Woo/MDG order under administrative control. Never expire a
     * claim just to permit a second ticket, hold or charge.
     */
    public static function reservation_transition( $intent, $woo_order_id, $from, $to ) {
        global $wpdb;
        $allowed = ( 'bound' === $from && 'reserving' === $to )
            || ( 'reserving' === $from && 'ready' === $to );
        if ( ! $allowed
            || ! is_string( $intent )
            || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $intent )
            || ! is_int( $woo_order_id ) || $woo_order_id < 1
            || ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
            return new WP_Error( 'milano_reservation_transition_invalid', 'Invalid canonical reservation transition.' );
        }
        $affected = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() .
            ' SET state = %s, updated_at = %s WHERE intent_key = %s
              AND brand = %s AND wc_order_id = %d AND state = %s',
            $to, gmdate( 'Y-m-d H:i:s' ), $intent, 'milano', $woo_order_id, $from
        ) );
        if ( false === $affected ) {
            return new WP_Error( 'milano_reservation_transition_db', 'Reservation state write failed.' );
        }
        if ( 1 !== (int) $affected ) {
            // A duplicate call is NEVER permitted to re-enter MDG reservation.
            // It must inspect the existing bound order and return safely.
            return new WP_Error( 'milano_reservation_transition_conflict', 'Order is already being prepared or has changed state.' );
        }
        return array( 'state' => $to, 'wc_order_id' => $woo_order_id, 'won_transition' => true );
    }

}
