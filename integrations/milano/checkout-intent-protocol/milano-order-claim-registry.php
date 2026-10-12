<?php
/**
 * Milano central-checkout request registry — STAGING / REVIEW DRAFT.
 *
 * No plugin header, REST endpoint, WordPress hook, table creation, order,
 * PayTR action or stock mutation. The containing central service must first
 * authenticate the server, validate brand/session/items against MDG/MMC,
 * and then call claim() BEFORE WooCommerce order creation.
 *
 * A UNIQUE request_id and a UNIQUE nullable order_id are REQUIRED in MySQL.
 * A stranded "claimed" row must be reconciled against WooCommerce order
 * metadata and payment records by a trusted operator; NEVER time-expire a
 * claim and blindly create a second order.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Order_Claim_Registry {
    /**
     * Declarative DDL for review: caller must deliberately provision on
     * staging. This method NEVER executes SQL or alters a live schema.
     */
    public static function ddl( $table ) {
        if ( ! self::valid_table( $table ) ) { return null; }
        return "CREATE TABLE {$table} (\n"
            . " request_id char(32) NOT NULL,\n"
            . " payload_hash char(64) NOT NULL,\n"
            . " state varchar(24) NOT NULL,\n"
            . " claim_token char(32) NOT NULL,\n"
            . " order_id bigint(20) unsigned NULL DEFAULT NULL,\n"
            . " created_at datetime NOT NULL,\n"
            . " updated_at datetime NOT NULL,\n"
            . " PRIMARY KEY (request_id),\n"
            . " UNIQUE KEY order_id (order_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    private static function valid_table( $table ) {
        return is_string( $table )
            && (bool) preg_match( '/\A[A-Za-z0-9_]{1,64}\z/D', $table );
    }

    /**
     * Atomic claim; returns one of:
     * claimed (first worker + opaque token),
     * existing_order (same request already bound to order),
     * processing (same request under another worker),
     * conflict (same request ID with different payload),
     * reconcile_required (unrecognized state / malformed row),
     * rejected (bad signature/schema), unavailable (DB/entropy errors).
     *
     * A claim result is NOT a booking, paid order, QR ticket or stock hold.
     */
    public static function claim( $db, $table, $payload, $signature, $secret, $now ) {
        if ( ! self::valid_table( $table ) || ! is_object( $db )
            || ! method_exists( $db, 'insert' ) || ! method_exists( $db, 'get_row' )
            || ! method_exists( $db, 'prepare' )
            || ! function_exists( 'mcpi_verify' )
            || ! mcpi_verify( $payload, $signature, $secret, $now ) ) {
            return array( 'status' => 'rejected' );
        }

        // Hash only canonical signed business identifiers. No PII/price.
        $json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $json ) ) { return array( 'status' => 'rejected' ); }
        $digest = hash( 'sha256', "milano.checkout-intent.v1\n" . $json );
        try { $token = bin2hex( random_bytes( 16 ) ); }
        catch ( Exception $e ) { return array( 'status' => 'unavailable' ); }

        $utc = gmdate( 'Y-m-d H:i:s', $now );
        // InnoDB PRIMARY KEY(request_id) arbitrates simultaneous arrivals.
        // NO SELECT-then-INSERT race and NO INSERT ... ON DUPLICATE UPDATE.
        $inserted = $db->insert(
            $table,
            array(
                'request_id'  => $payload['request_id'],
                'payload_hash'=> $digest,
                'state'       => 'claimed',
                'claim_token' => $token,
                'order_id'    => null,
                'created_at'  => $utc,
                'updated_at'  => $utc,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        if ( 1 === $inserted ) {
            return array( 'status' => 'claimed', 'claim_token' => $token );
        }

        // Fail closed on database problems, or inspect an existing identity.
        // Never retry the insert with a new request ID.
        $row = $db->get_row( $db->prepare(
            "SELECT request_id,payload_hash,state,order_id
             FROM {$table} WHERE request_id=%s LIMIT 1",
            $payload['request_id']
        ) );
        if ( ! is_object( $row ) ) { return array( 'status' => 'unavailable' ); }
        if ( ! isset( $row->payload_hash ) || ! is_string( $row->payload_hash )
            || ! hash_equals( $digest, $row->payload_hash ) ) {
            return array( 'status' => 'conflict' );
        }

        if ( 'ready' === $row->state && isset( $row->order_id )
            && (int) $row->order_id > 0 ) {
            return array( 'status' => 'existing_order', 'order_id' => (int) $row->order_id );
        }
        if ( 'claimed' === $row->state && empty( $row->order_id ) ) {
            return array( 'status' => 'processing' );
        }
        return array( 'status' => 'reconcile_required' );
    }

    /**
     * Link one central WooCommerce order to its originating signed request.
     * Caller MUST create the order with canonical event brand, Woo order-meta
     * request ID, and actual MDG product/price checks already performed.
     * This conditional update cannot overwrite a different worker's claim.
     */
    public static function attach_order( $db, $table, $request_id, $claim_token, $order_id, $now ) {
        if ( ! self::valid_table( $table ) || ! is_object( $db )
            || ! method_exists( $db, 'query' ) || ! method_exists( $db, 'prepare' )
            || ! is_string( $request_id ) || ! preg_match( '/\A[a-f0-9]{32}\z/D', $request_id )
            || ! is_string( $claim_token ) || ! preg_match( '/\A[a-f0-9]{32}\z/D', $claim_token )
            || ! is_int( $order_id ) || $order_id < 1 || ! is_int( $now ) || $now <= 0 ) {
            return false;
        }

        // Unique order_id in schema prevents attaching one Woo order to two
        // independent intent requests.
        $changed = $db->query( $db->prepare(
            "UPDATE {$table}
             SET order_id=%d,state=%s,updated_at=%s
             WHERE request_id=%s AND claim_token=%s AND state=%s AND order_id IS NULL",
            $order_id, 'ready', gmdate( 'Y-m-d H:i:s', $now ),
            $request_id, $claim_token, 'claimed'
        ) );
        return 1 === $changed;
    }
}
