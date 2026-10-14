<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Capacity {
    public static function available( $session_id ) {
        global $wpdb;
        $table = MDG_DB::table( 'sessions' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT capacity_total, held_units, sold_units FROM {$table} WHERE id = %d",
            $session_id
        ) );
        if ( ! $row ) { return 0; }
        return max( 0, (int) $row->capacity_total - (int) $row->held_units - (int) $row->sold_units );
    }

    /**
     * Atomik kapasite kilidi. Custom tablo üzerinde tek UPDATE şartıyla yarış durumunu engeller.
     */
    public static function hold( $session_id, $units, $expires_at, $order_id = 0, $cart_token = '' ) {
        global $wpdb;
        $units = absint( $units );
        if ( $units < 1 ) {
            return new WP_Error( 'mdg_invalid_units', 'Kapasite birimi geçersiz.' );
        }

        $sessions = MDG_DB::table( 'sessions' );
        $holds = MDG_DB::table( 'holds' );
        $now = MDG_DB::now();

        $wpdb->query( 'START TRANSACTION' );
        try {
            $affected = $wpdb->query( $wpdb->prepare(
                "UPDATE {$sessions}
                 SET held_units = held_units + %d, updated_at = %s
                 WHERE id = %d
                   AND status = %s
                   AND (capacity_total - sold_units - held_units) >= %d",
                $units, $now, $session_id, MDG_Status::ONSALE, $units
            ) );

            if ( 1 !== (int) $affected ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'mdg_capacity_full', 'Bu seans için yeterli kapasite kalmadı.' );
            }

            $ok = $wpdb->insert(
                $holds,
                array(
                    'session_id' => $session_id,
                    'order_id'   => $order_id ? absint( $order_id ) : null,
                    'cart_token' => $cart_token ? sanitize_text_field( $cart_token ) : null,
                    'units'      => $units,
                    'status'     => 'held',
                    'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( $expires_at ) ),
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
            );

            if ( false === $ok ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'mdg_hold_insert_failed', 'Kapasite kilidi kaydedilemedi.' );
            }

            $hold_id = (int) $wpdb->insert_id;
            $wpdb->query( 'COMMIT' );
            return $hold_id;
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'mdg_hold_exception', $e->getMessage() );
        }
    }

    /**
     * Payment notifications can repeat and run concurrently.
     *
     * Claim each held row atomically BEFORE increasing sold units. The claim
     * and matching session balance mutation are in the same database transaction,
     * so another callback (or cancellation/expiry worker) can never finalize
     * that hold twice. A stale SELECT is merely advisory, not the authority.
     */
    public static function commit_order( $order_id ) {
        global $wpdb;
        $order_id = absint( $order_id );
        if ( ! $order_id ) { return false; }
        $sessions = MDG_DB::table( 'sessions' );
        $holds = MDG_DB::table( 'holds' );
        $now = MDG_DB::now();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$holds} WHERE order_id = %d AND status = 'held'",
            $order_id
        ) );
        if ( ! is_array( $rows ) ) { return false; }

        $all_ok = true;
        foreach ( $rows as $row ) {
            if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
                $all_ok = false;
                continue;
            }
            try {
                // This UPDATE takes the hold row lock. Exactly one contender
                // can turn held -> sold; a losing contender may NOT touch stock.
                $claimed = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$holds}
                     SET status = 'sold', updated_at = %s
                     WHERE id = %d AND order_id = %d AND session_id = %d
                       AND units = %d AND status = 'held'",
                    $now, $row->id, $order_id, $row->session_id, $row->units
                ) );
                if ( 1 !== (int) $claimed ) {
                    $wpdb->query( 'ROLLBACK' );
                    if ( false === $claimed ) { $all_ok = false; }
                    continue;
                }
                $updated = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$sessions}
                     SET held_units = held_units - %d,
                         sold_units = sold_units + %d, updated_at = %s
                     WHERE id = %d AND held_units >= %d",
                    $row->units, $row->units, $now, $row->session_id, $row->units
                ) );
                if ( 1 !== (int) $updated ) {
                    $wpdb->query( 'ROLLBACK' );
                    $all_ok = false;
                    continue;
                }
                if ( false === $wpdb->query( 'COMMIT' ) ) {
                    $wpdb->query( 'ROLLBACK' );
                    $all_ok = false;
                }
            } catch ( Throwable $e ) {
                $wpdb->query( 'ROLLBACK' );
                $all_ok = false;
            }
        }
        return $all_ok;
    }

    public static function release_order( $order_id ) {
        global $wpdb;
        $order_id = absint( $order_id );
        if ( ! $order_id ) { return false; }
        $holds = MDG_DB::table( 'holds' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$holds} WHERE order_id = %d AND status = 'held'",
            $order_id
        ) );
        foreach ( $rows as $row ) {
            self::release_hold( (int) $row->id, 'released' );
        }
        return true;
    }

    /**
     * Cancellation and expiry share the same atomic hold-claim discipline as
     * payment settlement. No blind subtraction and no stale-read double release.
     */
    public static function release_hold( $hold_id, $status = 'released' ) {
        global $wpdb;
        $hold_id = absint( $hold_id );
        $status = sanitize_key( $status );
        if ( ! $hold_id || ! in_array( $status, array( 'released', 'expired' ), true ) ) {
            return false;
        }
        $holds = MDG_DB::table( 'holds' );
        $sessions = MDG_DB::table( 'sessions' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$holds} WHERE id = %d AND status = 'held'",
            $hold_id
        ) );
        if ( ! $row ) { return false; }
        $now = MDG_DB::now();
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return false; }
        try {
            $claimed = $wpdb->query( $wpdb->prepare(
                "UPDATE {$holds}
                 SET status = %s, updated_at = %s
                 WHERE id = %d AND session_id = %d AND units = %d AND status = 'held'",
                $status, $now, $hold_id, $row->session_id, $row->units
            ) );
            if ( 1 !== (int) $claimed ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$sessions}
                 SET held_units = held_units - %d, updated_at = %s
                 WHERE id = %d AND held_units >= %d",
                $row->units, $now, $row->session_id, $row->units
            ) );
            if ( 1 !== (int) $updated ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }
            if ( false === $wpdb->query( 'COMMIT' ) ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }
            return true;
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
    }

    public static function cleanup_expired() {
        global $wpdb;
        $holds = MDG_DB::table( 'holds' );
        $now = MDG_DB::now();
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$holds} WHERE status = 'held' AND expires_at < %s LIMIT 500",
            $now
        ) );
        foreach ( $ids as $id ) {
            self::release_hold( (int) $id, 'expired' );
        }
    }
}
