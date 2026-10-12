<?php
/**
 * Plugin Name: Dünya Organizasyon - Milano Ortak Seans Beslemesi (Hazırlık)
 * Description: MDG'nin kanonik kapasite defterinden salt okunur ve anahtar korumalı seans bilgisi verir. Sipariş veya stok değiştirmez.
 * Version: 0.2.0
 * Requires PHP: 7.4
 * Text Domain: dunya-milano-session-feed
 *
 * Install only on the central Madagaskar WordPress AFTER isolated testing
 * and a separate live-system change approval.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', static function() {
    register_rest_route( 'dunya-milano/v1', '/sessions', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'dmsf_get_sessions',
        'permission_callback' => 'dmsf_permission',
    ) );
} );

/**
 * Fail closed without a long shared secret. Never log or expose the key.
 * Define DUNYA_MILANO_FEED_TOKEN on BOTH sites' server-side configuration
 * (never in Gutenberg, browser JS, GitHub or Google Drive).
 */
function dmsf_permission( WP_REST_Request $request ) {
    if ( ! defined( 'DUNYA_MILANO_FEED_TOKEN' ) ) {
        return new WP_Error( 'dmsf_not_configured', 'Feed unavailable.', array( 'status' => 503 ) );
    }
    $expected = (string) DUNYA_MILANO_FEED_TOKEN;
    if ( strlen( $expected ) < 32 ) {
        return new WP_Error( 'dmsf_not_configured', 'Feed unavailable.', array( 'status' => 503 ) );
    }
    $provided = (string) $request->get_header( 'X-Dunya-Milano-Key' );
    if ( '' === $provided || ! hash_equals( $expected, $provided ) ) {
        return new WP_Error( 'dmsf_forbidden', 'Forbidden.', array( 'status' => 403 ) );
    }
    return true;
}

/** Guard dependency availability without autoloading or mutating any state. */
function dmsf_ready() {
    return class_exists( 'MDG_DB' )
        && class_exists( 'MDG_Status' )
        && class_exists( 'MDG_Sessions' )
        && class_exists( 'MDG_Capacity' )
        && function_exists( 'wc_get_product' );
}

/**
 * Resolve the canonical event owner. No heuristic fallback to Madagaskar.
 * The authoritative MDG/MMC brand adapter must be installed and tested
 * separately before this feed can return any session. An unverified event
 * MUST NEVER be exposed to Milano.
 */
function dmsf_event_owner_brand( $event_id ) {
    // Verified MMC<->MDG bridge is the sole event-to-program identity.
    // Do not guess from title or venue; ambiguous legacy events remain hidden.
    $owner = null;
    if ( class_exists( 'MMC_MDG_Bridge_Service' )
        && class_exists( 'MMC_Program_Service' )
        && method_exists( 'MMC_MDG_Bridge_Service', 'program_for_mdg_event' ) ) {
        $program_id = (int) MMC_MDG_Bridge_Service::program_for_mdg_event( (int) $event_id );
        $program = $program_id > 0 ? MMC_Program_Service::get_program( $program_id ) : null;
        if ( $program && isset( $program->brand ) ) {
            $brand = MMC_Program_Service::normalized_program_brand( $program->brand );
            if ( 'Milano Sirki' === $brand ) { $owner = 'milano'; }
            if ( 'Madagaskar Sirki' === $brand ) { $owner = 'madagaskar'; }
        }
    }
    $owner = apply_filters( 'dunya_milano_canonical_event_owner', $owner, (int) $event_id );
    return in_array( $owner, array( 'milano', 'madagaskar' ), true ) ? $owner : null;
}

/** Only future, on-sale Milano-owned sessions with native mappings. */
function dmsf_get_sessions( WP_REST_Request $request ) {
    if ( ! dmsf_ready() ) {
        return new WP_Error( 'dmsf_dependencies', 'Feed unavailable.', array( 'status' => 503 ) );
    }

    global $wpdb;
    $events_table   = MDG_DB::table( 'events' );
    $sessions_table = MDG_DB::table( 'sessions' );
    $types_table    = MDG_DB::table( 'ticket_types' );
    $now            = current_time( 'mysql', true );
    $limit          = 120;

    // All table names come from MDG_DB::table()'s strict allow-list.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.id AS session_id, s.event_id, s.start_at, s.end_at,
                s.capacity_total, s.wc_product_id, s.tickera_event_id,
                e.province_name, e.district, e.venue_name, e.venue_address
         FROM {$sessions_table} s
         INNER JOIN {$events_table} e ON e.id=s.event_id
         WHERE e.status=%s AND s.status=%s
           AND s.start_at>%s
           AND (e.sale_start IS NULL OR e.sale_start<=%s)
           AND (e.sale_end IS NULL OR e.sale_end>%s)
           AND (s.sale_start IS NULL OR s.sale_start<=%s)
           AND (s.sale_end IS NULL OR s.sale_end>%s)
         ORDER BY s.start_at ASC,s.id ASC LIMIT %d",
        MDG_Status::ONSALE,
        MDG_Status::ONSALE,
        $now, $now, $now, $now, $now, $limit
    ) );

    if ( null === $rows ) {
        return new WP_Error( 'dmsf_db_failure', 'Feed unavailable.', array( 'status' => 503 ) );
    }

    $sessions = array();
    foreach ( (array) $rows as $row ) {
        // Critical brand boundary; no implicit fallback for existing events.
        if ( 'milano' !== dmsf_event_owner_brand( (int) $row->event_id ) ) {
            continue;
        }
        if ( (int) $row->capacity_total < 1
            || (int) $row->wc_product_id < 1
            || (int) $row->tickera_event_id < 1
            || MDG_Sessions::sales_closed_by_time( $row ) ) {
            continue;
        }

        $product = wc_get_product( (int) $row->wc_product_id );
        if ( ! $product || ! $product->is_type( 'variable' )
            || 'publish' !== get_post_status( (int) $row->wc_product_id ) ) {
            continue;
        }

        $types = $wpdb->get_results( $wpdb->prepare(
            "SELECT code,label,price,capacity_units,wc_variation_id
             FROM {$types_table}
             WHERE session_id=%d AND is_active=1
             ORDER BY sort_order ASC,id ASC",
            (int) $row->session_id
        ) );
        if ( ! $types ) {
            continue;
        }
        // Validate variations, current prices and stock. Only canonical IDs
        // from MDG tables are considered; never trust request-supplied IDs.
        $ticket_types = array();
        foreach ( (array) $types as $type ) {
            $variation_id = (int) $type->wc_variation_id;
            $variation = $variation_id ? wc_get_product( $variation_id ) : null;
            if ( ! $variation || ! $variation->is_type( 'variation' )
                || (int) $variation->get_parent_id() !== (int) $product->get_id()
                || ! $variation->is_purchasable() || ! $variation->is_in_stock()
                || abs( round( (float) $type->price, 2 ) - round( (float) $variation->get_price(), 2 ) ) > 0.009 ) {
                continue;
            }
            $ticket_types[] = array(
                'code'           => sanitize_key( (string) $type->code ),
                'label'          => sanitize_text_field( (string) $type->label ),
                'price'          => round( (float) $type->price, 2 ),
                'capacity_units' => max( 1, (int) $type->capacity_units ),
            );
        }
        if ( ! $ticket_types ) {
            continue;
        }

        $local = MDG_Sessions::local_parts( (string) $row->start_at );
        if ( empty( $local[0] ) || empty( $local[1] ) ) {
            continue;
        }
        $available = MDG_Capacity::available( (int) $row->session_id );
        $sessions[] = array(
            'event_id'       => (int) $row->event_id,
            'event_owner_brand' => 'milano',
            'session_id'     => (int) $row->session_id,
            'province'       => sanitize_text_field( (string) $row->province_name ),
            'district'       => sanitize_text_field( (string) $row->district ),
            'venue'          => sanitize_text_field( (string) $row->venue_name ),
            'venue_address'  => sanitize_text_field( (string) $row->venue_address ),
            'date'           => (string) $local[0],
            'time'           => (string) $local[1],
            'start_utc'      => (string) $row->start_at,
            'available_units'=> max( 0, (int) $available ),
            'availability'   => $available > 0 ? 'available' : 'sold_out',
            'ticket_types'   => $ticket_types,
        );
    }

    $response = rest_ensure_response( array(
        'schema_version' => 1,
        'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
        'timezone'       => wp_timezone_string(),
        'read_only'      => true,
        'sessions'       => $sessions,
    ) );
    $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
    $response->header( 'X-Robots-Tag', 'noindex, nofollow' );
    return $response;
}
