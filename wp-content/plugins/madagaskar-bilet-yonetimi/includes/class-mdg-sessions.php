<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Sessions {
    public static function by_event( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE event_id=%d ORDER BY start_at ASC, id ASC',
            absint( $event_id )
        ) );
    }

    public static function ticket_types_by_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . MDG_DB::table( 'ticket_types' ) . ' WHERE session_id=%d ORDER BY sort_order ASC, id ASC',
            absint( $session_id )
        ) );
    }

    /**
     * Event draft screen uses one ticket catalogue cloned to every session.
     * This keeps the UI simple while the DB remains session-specific for future overrides.
     */
    public static function ticket_catalogue_for_event( $event_id ) {
        $sessions = self::by_event( $event_id );
        if ( ! $sessions ) { return array(); }
        return self::ticket_types_by_session( $sessions[0]->id );
    }

    public static function local_parts( $utc_mysql ) {
        $utc_mysql = trim( (string) $utc_mysql );
        if ( ! $utc_mysql ) { return array( '', '' ); }
        try {
            $dt = new DateTimeImmutable( $utc_mysql, new DateTimeZone( 'UTC' ) );
            $dt = $dt->setTimezone( wp_timezone() );
            return array( $dt->format( 'Y-m-d' ), $dt->format( 'H:i' ) );
        } catch ( Exception $e ) {
            return array( '', '' );
        }
    }

    public static function normalize_from_request( $duration_minutes ) {
        $dates = isset( $_POST['session_date'] ) && is_array( $_POST['session_date'] ) ? wp_unslash( $_POST['session_date'] ) : array();
        $times = isset( $_POST['session_time'] ) && is_array( $_POST['session_time'] ) ? wp_unslash( $_POST['session_time'] ) : array();
        $caps  = isset( $_POST['session_capacity'] ) && is_array( $_POST['session_capacity'] ) ? wp_unslash( $_POST['session_capacity'] ) : array();

        $sessions = array();
        $seen = array();
        $count = min( max( count( $dates ), count( $times ), count( $caps ) ), 50 );

        for ( $i = 0; $i < $count; $i++ ) {
            $date = sanitize_text_field( $dates[ $i ] ?? '' );
            $time = sanitize_text_field( $times[ $i ] ?? '' );
            $cap  = absint( $caps[ $i ] ?? 0 );

            // Completely blank repeater rows are ignored.
            if ( '' === $date && '' === $time && 0 === $cap ) { continue; }
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                return new WP_Error( 'mdg_bad_session_date', 'Her seans için geçerli bir tarih seçmelisiniz.' );
            }
            if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
                return new WP_Error( 'mdg_bad_session_time', 'Her seans için geçerli bir saat seçmelisiniz.' );
            }
            if ( $cap < 1 || $cap > 100000 ) {
                return new WP_Error( 'mdg_bad_session_capacity', 'Seans kapasitesi 1 ile 100.000 arasında olmalıdır.' );
            }

            try {
                $local = new DateTimeImmutable( $date . ' ' . $time . ':00', wp_timezone() );
                // Detect impossible/normalized values such as 2026-02-31.
                if ( $local->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
                    return new WP_Error( 'mdg_bad_session_datetime', 'Geçersiz bir seans tarihi veya saati girildi.' );
                }
                $utc = $local->setTimezone( new DateTimeZone( 'UTC' ) );
                $end = $utc->modify( '+' . absint( $duration_minutes ) . ' minutes' );
            } catch ( Exception $e ) {
                return new WP_Error( 'mdg_bad_session_datetime', 'Seans tarihi veya saati işlenemedi.' );
            }

            $start_at = $utc->format( 'Y-m-d H:i:s' );
            if ( isset( $seen[ $start_at ] ) ) {
                return new WP_Error( 'mdg_duplicate_session', 'Aynı tarih ve saatte iki seans tanımlanamaz.' );
            }
            $seen[ $start_at ] = true;

            $sessions[] = array(
                'start_at'       => $start_at,
                'end_at'         => $end->format( 'Y-m-d H:i:s' ),
                'capacity_total' => $cap,
            );
        }

        if ( ! $sessions ) {
            return new WP_Error( 'mdg_no_sessions', 'En az bir seans eklemelisiniz.' );
        }

        usort( $sessions, function ( $a, $b ) { return strcmp( $a['start_at'], $b['start_at'] ); } );
        return $sessions;
    }

    public static function normalize_ticket_types_from_request() {
        $enabled = isset( $_POST['ticket_enabled'] ) && is_array( $_POST['ticket_enabled'] ) ? wp_unslash( $_POST['ticket_enabled'] ) : array();
        $labels  = isset( $_POST['ticket_label'] ) && is_array( $_POST['ticket_label'] ) ? wp_unslash( $_POST['ticket_label'] ) : array();
        $prices  = isset( $_POST['ticket_price'] ) && is_array( $_POST['ticket_price'] ) ? wp_unslash( $_POST['ticket_price'] ) : array();
        $units   = isset( $_POST['ticket_units'] ) && is_array( $_POST['ticket_units'] ) ? wp_unslash( $_POST['ticket_units'] ) : array();
        $codes   = isset( $_POST['ticket_code'] ) && is_array( $_POST['ticket_code'] ) ? wp_unslash( $_POST['ticket_code'] ) : array();

        $rows = array();
        $seen_codes = array();
        $count = min( max( count( $labels ), count( $prices ), count( $units ), count( $codes ) ), 30 );

        for ( $i = 0; $i < $count; $i++ ) {
            $is_enabled = ! empty( $enabled[ $i ] );
            $label = sanitize_text_field( $labels[ $i ] ?? '' );
            $price_raw = str_replace( ',', '.', trim( (string) ( $prices[ $i ] ?? '' ) ) );
            $capacity_units = absint( $units[ $i ] ?? 1 );
            $code = strtoupper( sanitize_key( $codes[ $i ] ?? '' ) );
            $code = str_replace( '-', '_', $code );

            // Disabled default rows are preserved only in the browser, not in DB.
            if ( ! $is_enabled ) { continue; }
            if ( '' === $label ) {
                return new WP_Error( 'mdg_ticket_label', 'Aktif bilet türlerinin adı boş bırakılamaz.' );
            }
            if ( '' === $price_raw || ! is_numeric( $price_raw ) ) {
                return new WP_Error( 'mdg_ticket_price', $label . ' için geçerli bir fiyat girin.' );
            }
            $price = round( (float) $price_raw, 2 );
            if ( $price < 0 || $price > 1000000 ) {
                return new WP_Error( 'mdg_ticket_price_range', $label . ' için fiyat 0 ile 1.000.000 TL arasında olmalıdır.' );
            }
            if ( $capacity_units < 1 || $capacity_units > 50 ) {
                return new WP_Error( 'mdg_ticket_units', $label . ' için kapasite tüketimi 1 ile 50 kişi arasında olmalıdır.' );
            }
            if ( ! $code ) {
                $code = self::code_from_label( $label, $i + 1 );
            }
            if ( isset( $seen_codes[ $code ] ) ) {
                return new WP_Error( 'mdg_ticket_code_duplicate', 'Bilet türü kodları benzersiz olmalıdır: ' . $code );
            }
            $seen_codes[ $code ] = true;

            $rows[] = array(
                'code'           => substr( $code, 0, 40 ),
                'label'          => $label,
                'price'          => number_format( $price, 2, '.', '' ),
                'capacity_units' => $capacity_units,
                'sort_order'     => ( $i + 1 ) * 10,
            );
        }

        if ( ! $rows ) {
            return new WP_Error( 'mdg_no_ticket_types', 'En az bir bilet türünü aktif etmelisiniz.' );
        }
        return $rows;
    }

    private static function code_from_label( $label, $fallback_no ) {
        $trans = remove_accents( $label );
        $trans = strtoupper( preg_replace( '/[^A-Za-z0-9]+/', '_', $trans ) );
        $trans = trim( $trans, '_' );
        return $trans ? $trans : 'BILET_' . absint( $fallback_no );
    }

    public static function replace_draft_structure( $event_id, array $sessions, array $ticket_types ) {
        global $wpdb;
        $event_id = absint( $event_id );
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $now = MDG_DB::now();

        $session_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$sessions_table} WHERE event_id=%d", $event_id ) );
        if ( $session_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
            $sql = $wpdb->prepare( "DELETE FROM {$types_table} WHERE session_id IN ({$placeholders})", array_map( 'absint', $session_ids ) );
            if ( false === $wpdb->query( $sql ) ) { return new WP_Error( 'mdg_delete_ticket_types', 'Eski bilet türleri temizlenemedi.' ); }
        }
        if ( false === $wpdb->delete( $sessions_table, array( 'event_id' => $event_id ), array( '%d' ) ) ) {
            return new WP_Error( 'mdg_delete_sessions', 'Eski seanslar temizlenemedi.' );
        }

        foreach ( $sessions as $session ) {
            $ok = $wpdb->insert( $sessions_table, array(
                'event_id'       => $event_id,
                'start_at'       => $session['start_at'],
                'end_at'         => $session['end_at'],
                'status'         => 'draft',
                'capacity_total' => absint( $session['capacity_total'] ),
                'held_units'     => 0,
                'sold_units'     => 0,
                'created_at'     => $now,
                'updated_at'     => $now,
            ) );
            if ( false === $ok ) { return new WP_Error( 'mdg_insert_session', 'Seans kaydedilemedi.' ); }
            $session_id = (int) $wpdb->insert_id;

            foreach ( $ticket_types as $type ) {
                $ok = $wpdb->insert( $types_table, array(
                    'session_id'     => $session_id,
                    'code'           => $type['code'],
                    'label'          => $type['label'],
                    'price'          => $type['price'],
                    'capacity_units' => absint( $type['capacity_units'] ),
                    'is_active'      => 1,
                    'sort_order'     => absint( $type['sort_order'] ),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ) );
                if ( false === $ok ) { return new WP_Error( 'mdg_insert_ticket_type', 'Bilet türü kaydedilemedi.' ); }
            }
        }

        return true;
    }
}
