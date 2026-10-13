<?php
/**
 * Milano canonical MDG/MMC/Woo snapshot adapter (STAGING SOURCE ONLY).
 *
 * IMPORTANT: No plugin header, REST route, Woo hook or write query. This
 * adapter is intentionally NOT installed on either live WordPress site.
 *
 * May run only on the trusted Madagaskar central WordPress process, AFTER
 * server-to-server authentication. Caller-supplied IDs do not establish an
 * entitlement; the adapter resolves ALL ownership and pricing on the server.
 *
 * A snapshot is not a price guarantee, capacity hold or authorization to pay.
 * Recheck and atomically hold within the future central order worker.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Canonical_MDG_Snapshot {
    /** Fail closed if any authoritative dependency is not loaded. */
    private static function ready() {
        return class_exists( 'MDG_DB' ) &&
            class_exists( 'MDG_Events' ) &&
            class_exists( 'MDG_Sessions' ) &&
            class_exists( 'MDG_Capacity' ) &&
            class_exists( 'MDG_Status' ) &&
            class_exists( 'MMC_Program_Service' ) &&
            class_exists( 'MMC_MDG_Bridge_Service' ) &&
            function_exists( 'wc_get_product' ) &&
            function_exists( 'get_woocommerce_currency' ) &&
            function_exists( 'get_post_status' );
    }

    /**
     * MySQL UTC DATETIME parsing is strict; any ambiguous/invalid timestamp
     * must block checkout instead of silently becoming the Unix epoch.
     */
    private static function utc( $value ) {
        if ( ! is_string( $value )
            || ! preg_match( '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', $value ) ) {
            return false;
        }
        $dt = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone('UTC') );
        return $dt && $dt->format( 'Y-m-d H:i:s' ) === $value
            ? $dt->getTimestamp() : false;
    }

    /** Nullable sale windows, strict time parsing otherwise. */
    private static function sale_window( $row, $name ) {
        if ( ! is_object( $row ) || ! property_exists( $row, $name ) ) { return false; }
        if ( null === $row->$name || '' === $row->$name ) { return null; }
        return self::utc( $row->$name );
    }

    /**
     * Decimal strings into integer cents, without binary floating point.
     * Accept trailing precision zeros only (490.0000 => 49000 cents);
     * reject nonzero fractional sub-cents, negatives, scientific notation.
     */
    public static function price_cents( $price ) {
        if ( ! is_string( $price ) && ! is_int( $price ) ) { return null; }
        $price = (string) $price;
        if ( ! preg_match('/\A(0|[1-9]\d{0,6})(?:\.(\d{1,6}))?\z/D', $price, $matches ) ) {
            return null;
        }
        $fraction = $matches[2] ?? '';
        if ( strlen( $fraction ) > 2 && trim( substr( $fraction, 2 ), '0' ) !== '' ) {
            return null;
        }
        return (int)$matches[1] * 100 + (int)substr( str_pad( $fraction, 2, '0' ), 0, 2 );
    }

    /** Only a single explicit active MMC event bridge is eligible. */
    private static function canonical_owner( $event_id ) {
        global $wpdb;
        if ( ! method_exists( 'MMC_MDG_Bridge_Service', 'table' )
            || ! method_exists( 'MMC_Program_Service', 'get_program' )
            || ! method_exists( 'MMC_Program_Service', 'normalized_program_brand' ) ) {
            return null;
        }
        $table = MMC_MDG_Bridge_Service::table();
        if ( ! is_string( $table ) || ! preg_match( '/\A[A-Za-z0-9_]+\z/D', $table ) ) {
            return null;
        }
        // LIMIT 2, never LIMIT 1: an ambiguous brand bridge MUST be rejected.
        $pids = $wpdb->get_col( $wpdb->prepare(
            "SELECT program_id FROM {$table}
             WHERE mdg_event_id=%d AND is_active=1 LIMIT 2",
            $event_id
        ) );
        if ( ! is_array( $pids ) || count( $pids ) !== 1
            || (int) $pids[0] < 1 ) { return null; }
        $program = MMC_Program_Service::get_program( (int) $pids[0] );
        if ( ! is_object( $program ) || ! isset( $program->brand ) ) { return null; }
        $brand = MMC_Program_Service::normalized_program_brand( $program->brand );
        if ( 'Milano Sirki' === $brand ) {
            return array( 'brand' => 'milano', 'program_id' => (int) $pids[0] );
        }
        if ( 'Madagaskar Sirki' === $brand ) {
            return array( 'brand' => 'madagaskar', 'program_id' => (int) $pids[0] );
        }
        return null;
    }

    /**
     * Read-only authoritative on-site data. Null == fail closed.
     * This adapter NEVER reads prices or owner names from the Milano site.
     */
    public static function lookup( $event_id, $session_id, $now = null ) {
        if ( ! self::ready() || ! is_int( $event_id ) || $event_id < 1
            || ! is_int( $session_id ) || $session_id < 1
            || ( null !== $now && ( ! is_int( $now ) || $now <= 0 ) ) ) {
            return null;
        }
        $now = null === $now ? time() : $now;
        $ownership = self::canonical_owner( $event_id );
        if ( ! is_array( $ownership ) || ( $ownership['brand'] ?? null ) !== 'milano'
            || ! is_int( $ownership['program_id'] ?? null )
            || $ownership['program_id'] < 1 ) { return null; }

        $event = MDG_Events::get( $event_id );
        if ( ! is_object( $event ) || ! isset( $event->id, $event->status ) ||
            (int) $event->id !== $event_id ) { return null; }

        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$sessions_table} WHERE id=%d AND event_id=%d LIMIT 1",
            $session_id, $event_id
        ) );
        if ( ! is_object( $session ) || ! isset( $session->id, $session->event_id )
            || ! isset( $session->status, $session->start_at )
            || (int) $session->id !== $session_id || (int) $session->event_id !== $event_id ) {
            return null;
        }
        $starts = self::utc( (string) $session->start_at );
        $e_start = self::sale_window( $event, 'sale_start' );
        $e_end = self::sale_window( $event, 'sale_end' );
        $s_start = self::sale_window( $session, 'sale_start' );
        $s_end = self::sale_window( $session, 'sale_end' );
        if ( false === $starts || false === $e_start || false === $e_end
            || false === $s_start || false === $s_end ) { return null; }

        $product_id = (int) ( $session->wc_product_id ?? 0 );
        $tickera_id = (int) ( $session->tickera_event_id ?? 0 );
        if ( $product_id < 1 || $tickera_id < 1 ) { return null; }
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ||
            'publish' !== get_post_status( $product_id ) ||
            'TRY' !== get_woocommerce_currency() ) { return null; }

        $raw_types = MDG_Sessions::ticket_types_by_session( $session_id );
        if ( ! is_array( $raw_types ) || ! $raw_types || count( $raw_types ) > 30 ) { return null; }

        $tickets = array();
        $codes_seen = array();
        foreach ( $raw_types as $type ) {
            if ( ! is_object( $type ) || ! isset( $type->code ) ||
                ! is_string( $type->code ) ||
                ! preg_match( '/\A[A-Z0-9_-]{1,24}\z/D', $type->code ) ||
                isset( $codes_seen[ $type->code ] ) ) {
                return null;
            }
            $codes_seen[ $type->code ] = true;
            // Inactive rows remain in the central snapshot with active=false.
            $variation_id = (int) ( $type->wc_variation_id ?? 0 );
            $variation = $variation_id > 0 ? wc_get_product( $variation_id ) : false;
            $mdg_price = self::price_cents( $type->price ?? null );
            $woo_price = $variation ? self::price_cents( $variation->get_price() ) : null;
            if ( null === $mdg_price || ( $variation && null === $woo_price ) ) { return null; }
            $tickets[] = array(
                'code' => $type->code,
                'ticket_type_id' => (int) ( $type->id ?? 0 ),
                'active' => (int) ( $type->is_active ?? 0 ) === 1,
                'variation_id' => $variation_id,
                'variation_parent_id' => $variation ? (int) $variation->get_parent_id() : 0,
                'purchasable' => $variation && $variation->is_type( 'variation' ) && $variation->is_purchasable(),
                'in_stock' => $variation && $variation->is_in_stock(),
                'capacity_units' => (int) ( $type->capacity_units ?? 0 ),
                'price_cents' => $mdg_price,
                'woo_price_cents' => $woo_price,
            );
        }

        $capacity_total = (int) ( $session->capacity_total ?? 0 );
        $available = MDG_Capacity::available( $session_id );
        if ( $capacity_total < 1 || ! is_int( $available ) || $available < 0
            || $available > $capacity_total ) { return null; }

        // Family pricing must originate from the one verified matching MMC
        // event, not the legacy global default/option fallback. Absence means
        // the family offer is NOT eligible; normal adult/child sales remain.
        $family = class_exists( 'MCPI_Canonical_MMC_Family_Policy' )
            ? MCPI_Canonical_MMC_Family_Policy::lookup(
                $ownership['program_id'], $event_id, $tickets
            ) : null;

        $snapshot = array(
            'event' => array(
                'id' => $event_id,
                'owner_brand' => 'milano',
                'status' => (string) $event->status,
                'sale_start_utc' => $e_start,
                'sale_end_utc' => $e_end,
            ),
            'session' => array(
                'id' => $session_id,
                'event_id' => (int) $session->event_id,
                'status' => (string) $session->status,
                'start_utc' => $starts,
                'closed_by_time' => (bool) MDG_Sessions::sales_closed_by_time( $session, $now ),
                'wc_product_id' => $product_id,
                'tickera_event_id' => $tickera_id,
                'capacity_total' => $capacity_total,
                'available_units' => $available,
                'sale_start_utc' => $s_start,
                'sale_end_utc' => $s_end,
            ),
            'product' => array(
                'id' => $product_id,
                'published' => true,
                'variable' => true,
                'currency' => 'TRY',
            ),
            'tickets' => $tickets,
        );
        if ( is_array( $family ) ) { $snapshot['family'] = $family; }
        return $snapshot;
    }
}
