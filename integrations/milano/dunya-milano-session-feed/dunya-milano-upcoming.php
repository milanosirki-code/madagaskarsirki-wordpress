<?php
/**
 * Milano forthcoming show announcements, NOT a ticket-sales API.
 *
 * Server-only opt-ins (both required on central Madagaskar):
 * DUNYA_MILANO_UPCOMING_FEED_ENABLED === true
 * DUNYA_MILANO_ANNOUNCED_EVENT_IDS === array(verified_mdg_event_id, ...)
 *
 * The ordinary DUNYA_MILANO_FEED_TOKEN remains mandatory for every request.
 * No key, list of event IDs or enabling flag is read from an HTTP parameter.
 * DO NOT deploy before staging review and separate live change approval.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'rest_api_init', static function() {
    register_rest_route( 'dunya-milano/v1', '/upcoming', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'dmsf_get_upcoming',
        'permission_callback' => 'dmsf_upcoming_permission',
    ) );
} );

function dmsf_upcoming_approved_ids() {
    if ( ! defined( 'DUNYA_MILANO_UPCOMING_FEED_ENABLED' )
        || true !== DUNYA_MILANO_UPCOMING_FEED_ENABLED
        || ! defined( 'DUNYA_MILANO_ANNOUNCED_EVENT_IDS' )
        || ! is_array( DUNYA_MILANO_ANNOUNCED_EVENT_IDS )
        || count( DUNYA_MILANO_ANNOUNCED_EVENT_IDS ) < 1
        || count( DUNYA_MILANO_ANNOUNCED_EVENT_IDS ) > 50 ) {
        return null;
    }
    $ids = array();
    foreach ( DUNYA_MILANO_ANNOUNCED_EVENT_IDS as $id ) {
        // Integers only; a typed, manually reviewed server-side allowlist.
        if ( ! is_int( $id ) || $id < 1 || isset( $ids[ $id ] ) ) {
            return null;
        }
        $ids[ $id ] = $id;
    }
    return array_values( $ids );
}

function dmsf_upcoming_permission( WP_REST_Request $request ) {
    $token_check = dmsf_permission( $request );
    if ( true !== $token_check ) {
        return $token_check;
    }
    if ( null === dmsf_upcoming_approved_ids() ) {
        return new WP_Error( 'dmsf_upcoming_not_approved', 'Feed unavailable.', array( 'status' => 503 ) );
    }
    return true;
}

/**
 * Publicable only after *explicit* event-ID approval and *canonical* Milano
 * ownership. Draft/onsale here refers to date announcements, never checkout.
 */
function dmsf_get_upcoming( WP_REST_Request $request ) {
    $approved = dmsf_upcoming_permission( $request );
    if ( true !== $approved ) { return $approved; }
    if ( ! class_exists( 'MDG_DB' ) || ! class_exists( 'MDG_Status' )
        || ! class_exists( 'MDG_Sessions' )
        || ! method_exists( 'MDG_DB', 'table' )
        || ! method_exists( 'MDG_Sessions', 'local_parts' )
        || ! function_exists( 'dmsf_event_owner_brand' ) ) {
        return new WP_Error( 'dmsf_upcoming_dependencies', 'Feed unavailable.', array( 'status' => 503 ) );
    }

    global $wpdb;
    if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' )
        || ! method_exists( $wpdb, 'get_results' ) ) {
        return new WP_Error( 'dmsf_upcoming_database', 'Feed unavailable.', array( 'status' => 503 ) );
    }
    $approved_ids = dmsf_upcoming_approved_ids();
    $events = MDG_DB::table( 'events' );
    $sessions = MDG_DB::table( 'sessions' );
    if ( ! preg_match( '/\A[A-Za-z0-9_]+\z/D', (string) $events )
        || ! preg_match( '/\A[A-Za-z0-9_]+\z/D', (string) $sessions ) ) {
        return new WP_Error( 'dmsf_upcoming_table', 'Feed unavailable.', array( 'status' => 503 ) );
    }
    $placeholders = implode( ',', array_fill( 0, count( $approved_ids ), '%d' ) );
    $now = current_time( 'mysql', true );
    $query = "SELECT s.id AS session_id, s.event_id, s.start_at,
                  e.province_name, e.district, e.venue_name, e.venue_address
              FROM {$sessions} s
              INNER JOIN {$events} e ON e.id=s.event_id
              WHERE e.id IN ({$placeholders})
                AND e.status IN (%s,%s)
                AND s.status IN (%s,%s)
                AND s.start_at > %s
              ORDER BY s.start_at ASC, s.id ASC
              LIMIT 120";
    $args = array_merge( $approved_ids, array(
        MDG_Status::DRAFT, MDG_Status::ONSALE,
        MDG_Status::DRAFT, MDG_Status::ONSALE, $now,
    ) );
    $rows = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );
    if ( null === $rows ) {
        return new WP_Error( 'dmsf_upcoming_query', 'Feed unavailable.', array( 'status' => 503 ) );
    }

    $approved_set = array_fill_keys( $approved_ids, true );
    $result = array();
    foreach ( (array) $rows as $row ) {
        $event_id = (int) $row->event_id;
        // Defense in depth: never trust SQL alone for time eligibility.
        if ( ! isset( $row->start_at ) || ! is_string( $row->start_at )
            || ! strtotime( $row->start_at . ' UTC' )
            || strtotime( $row->start_at . ' UTC' ) <= time() ) {
            continue;
        }
        if ( ! isset( $approved_set[ $event_id ] )
            || 'milano' !== dmsf_event_owner_brand( $event_id ) ) {
            continue;
        }
        $province = sanitize_text_field( (string) $row->province_name );
        $venue = sanitize_text_field( (string) $row->venue_name );
        if ( '' === $province || '' === $venue ) { continue; }
        $local = MDG_Sessions::local_parts( (string) $row->start_at );
        if ( ! is_array( $local ) || ! isset( $local[0], $local[1] )
            || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $local[0] )
            || ! preg_match( '/^\d{2}:\d{2}$/', (string) $local[1] ) ) {
            continue;
        }
        $result[] = array(
            'event_id'         => $event_id,
            'event_owner_brand'=> 'milano',
            'session_id'       => (int) $row->session_id,
            'province'         => $province,
            'district'         => sanitize_text_field( (string) $row->district ),
            'venue'            => $venue,
            'venue_address'    => sanitize_text_field( (string) $row->venue_address ),
            'date'             => (string) $local[0],
            'time'             => (string) $local[1],
            'start_utc'        => (string) $row->start_at,
        );
    }
    $response = rest_ensure_response( array(
        'schema_version' => 1,
        'generated_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
        'read_only'      => true,
        'catalog_mode'   => 'announcement_only',
        'sessions'       => $result,
    ) );
    $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
    $response->header( 'X-Robots-Tag', 'noindex, nofollow' );
    return $response;
}
