<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_DB {
    public static function table( $name ) {
        global $wpdb;
        $allowed = array( 'venues', 'events', 'sessions', 'ticket_types', 'holds', 'order_map', 'notifications', 'audit_log' );
        if ( ! in_array( $name, $allowed, true ) ) {
            throw new InvalidArgumentException( 'Geçersiz MDG tablo adı.' );
        }
        return $wpdb->prefix . 'mdg_' . $name;
    }

    public static function now() {
        return current_time( 'mysql', true );
    }
}
