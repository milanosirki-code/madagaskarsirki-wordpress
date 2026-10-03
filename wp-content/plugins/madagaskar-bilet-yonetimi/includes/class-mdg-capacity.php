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
        foreach ( $rows as $row ) {
            $wpdb->query( 'START TRANSACTION' );
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$sessions}
                 SET held_units = GREATEST(held_units - %d, 0), sold_units = sold_units + %d, updated_at = %s
                 WHERE id = %d",
                $row->units, $row->units, $now, $row->session_id
            ) );
            if ( false === $updated ) {
                $wpdb->query( 'ROLLBACK' );
                continue;
            }
            $wpdb->update( $holds, array( 'status' => 'sold', 'updated_at' => $now ), array( 'id' => $row->id ) );
            $wpdb->query( 'COMMIT' );
        }
        return true;
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

    public static function release_hold( $hold_id, $status = 'released' ) {
        global $wpdb;
        $holds = MDG_DB::table( 'holds' );
        $sessions = MDG_DB::table( 'sessions' );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$holds} WHERE id = %d", $hold_id ) );
        if ( ! $row || 'held' !== $row->status ) { return false; }
        $now = MDG_DB::now();
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$sessions} SET held_units = GREATEST(held_units - %d, 0), updated_at = %s WHERE id = %d",
            $row->units, $now, $row->session_id
        ) );
        $wpdb->update( $holds, array( 'status' => sanitize_key( $status ), 'updated_at' => $now ), array( 'id' => $hold_id ) );
        $wpdb->query( 'COMMIT' );
        return true;
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
