<?php
/**
 * Milano-only, read-only pre-sale schedule announcements.
 *
 * Not a checkout source, inventory feed, or indication that tickets can be
 * purchased. Requires two independent server-side approvals: the private feed
 * token (handled by dmsf_permission) and a strict program ID allowlist.
 * The default (no configured approved program IDs) is an empty schedule.
 *
 * WARNING: staging / draft. Do not deploy directly to production.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', static function() {
    register_rest_route( 'dunya-milano/v1', '/announcements', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'dmsf_get_announcements',
        'permission_callback' => 'dmsf_permission',
    ) );
} );

/**
 * Set only on the central server, not in JS, posts, GitHub or Drive:
 * define( 'DUNYA_MILANO_ANNOUNCEMENT_APPROVED_PROGRAM_IDS', array( 123 ) );
 * No implicit approvals from programme status or product publication.
 */
function dmsf_announcement_approved_ids() {
    if ( ! defined( 'DUNYA_MILANO_ANNOUNCEMENT_APPROVED_PROGRAM_IDS' ) ) {
        return array();
    }
    $ids = DUNYA_MILANO_ANNOUNCEMENT_APPROVED_PROGRAM_IDS;
    if ( ! is_array( $ids ) || count( $ids ) > 25 ) {
        return new WP_Error( 'dmsf_announcement_config_invalid', 'Feed unavailable.', array( 'status' => 503 ) );
    }
    $out = array();
    foreach ( $ids as $id ) {
        if ( ! is_int( $id ) || $id < 1 || isset( $out[ $id ] ) ) {
            return new WP_Error( 'dmsf_announcement_config_invalid', 'Feed unavailable.', array( 'status' => 503 ) );
        }
        $out[ $id ] = true;
    }
    return $out;
}

function dmsf_get_announcements( WP_REST_Request $request ) {
    $approved = dmsf_announcement_approved_ids();
    if ( is_wp_error( $approved ) ) {
        return $approved;
    }
    if ( $approved && (
        ! class_exists( 'MMC_Program_Service' ) ||
        ! class_exists( 'MMC_Event_Service' ) ||
        ! class_exists( 'MMC_Venue_Service' ) ||
        ! method_exists( 'MMC_Program_Service', 'all_programs' ) ||
        ! method_exists( 'MMC_Program_Service', 'normalized_program_brand' ) ||
        ! method_exists( 'MMC_Event_Service', 'event_for_program' ) ||
        ! method_exists( 'MMC_Event_Service', 'sessions' ) ||
        ! method_exists( 'MMC_Venue_Service', 'venues_for_program' )
    ) ) {
        return new WP_Error( 'dmsf_announcement_dependencies', 'Feed unavailable.', array( 'status' => 503 ) );
    }

    $rows = array();
    $limit = 120;
    $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Europe/Istanbul' );

    if ( $approved ) {
        // This is NOT a list of public events. Every approved programme is
        // rechecked against its authoritative MMC brand and event/venue.
        $programs = MMC_Program_Service::all_programs();
        if ( ! is_array( $programs ) ) {
            return new WP_Error( 'dmsf_announcement_source', 'Feed unavailable.', array( 'status' => 503 ) );
        }
        foreach ( $programs as $p ) {
            if ( ! is_object( $p ) || ! isset( $p->id ) || ! isset( $approved[ (int) $p->id ] ) ) {
                continue;
            }
            if ( 'Milano Sirki' !== MMC_Program_Service::normalized_program_brand( $p->brand ?? '' ) ||
                in_array( (string) ( $p->status ?? '' ), array( 'cancelled', 'completed', 'financial_close' ), true ) ) {
                continue;
            }

            $event = MMC_Event_Service::event_for_program( (int) $p->id );
            if ( ! is_object( $event ) || (int) ( $event->program_id ?? 0 ) !== (int) $p->id ||
                ! in_array( (string) ( $event->status ?? '' ), array( 'configured', 'sales_ready', 'sales_open' ), true ) ) {
                continue;
            }

            $venues = MMC_Venue_Service::venues_for_program( (int) $p->id );
            if ( ! is_array( $venues ) ) {
                continue;
            }
            $selected = array_values( array_filter( $venues, static function( $venue ) use ( $event, $p ) {
                return is_object( $venue ) && (int) ( $venue->is_selected ?? 0 ) === 1 &&
                    (int) ( $venue->program_id ?? 0 ) === (int) $p->id &&
                    (int) ( $venue->id ?? 0 ) === (int) ( $event->program_venue_id ?? 0 ) &&
                    'approved' === (string) ( $venue->allocation_status ?? '' ) &&
                    '' !== trim( (string) ( $venue->venue_name ?? '' ) );
            } ) );
            if ( count( $selected ) !== 1 ) {
                continue;
            }
            // Any second selected venue means the canonical venue is ambiguous.
            $all_selected = array_filter( $venues, static function( $venue ) {
                return is_object( $venue ) && (int) ( $venue->is_selected ?? 0 ) === 1;
            } );
            if ( count( $all_selected ) !== 1 ) {
                continue;
            }
            $venue = $selected[0];

            $sessions = MMC_Event_Service::sessions( (int) $event->id );
            if ( ! is_array( $sessions ) ) {
                continue;
            }
            foreach ( $sessions as $session ) {
                if ( ! is_object( $session ) || (int) ( $session->event_id ?? 0 ) !== (int) $event->id ||
                    'active' !== (string) ( $session->status ?? '' ) ) {
                    continue;
                }
                $raw_time = (string) ( $session->session_time ?? '' );
                if ( ! preg_match( '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/D', $raw_time ) ) {
                    continue;
                }
                $start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw_time, $tz );
                if ( ! $start || $start->format( 'Y-m-d H:i:s' ) !== $raw_time ||
                    $start->getTimestamp() <= time() ) {
                    continue;
                }

                $rows[] = array(
                    'event_id' => (int) $event->id,
                    'session_id' => (int) $session->id,
                    'event_owner_brand' => 'milano',
                    'province' => sanitize_text_field( (string) $p->province_name ),
                    'district' => sanitize_text_field( (string) $p->district_name ),
                    'venue' => sanitize_text_field( (string) $venue->venue_name ),
                    'date' => $start->format( 'Y-m-d' ),
                    'time' => $start->format( 'H:i' ),
                    'start_utc' => gmdate( 'Y-m-d H:i:s', $start->getTimestamp() ),
                );
                if ( count( $rows ) > $limit ) {
                    return new WP_Error( 'dmsf_announcement_limit', 'Feed unavailable.', array( 'status' => 503 ) );
                }
            }
        }
    }

    usort( $rows, static function( $a, $b ) {
        return strcmp( $a['start_utc'], $b['start_utc'] ) ?: ( $a['session_id'] <=> $b['session_id'] );
    } );
    $response = rest_ensure_response( array(
        'schema_version' => 1,
        'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
        'timezone' => wp_timezone_string(),
        'read_only' => true,
        'sales_enabled' => false,
        'sessions' => $rows,
    ) );
    $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
    $response->header( 'X-Robots-Tag', 'noindex, nofollow' );
    return $response;
}
