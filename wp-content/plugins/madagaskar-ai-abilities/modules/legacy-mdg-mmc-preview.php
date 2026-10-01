<?php
/**
 * Madagaskar AI — Legacy MDG → MMC Migration Preview
 *
 * Read-only inventory and migration planning for legacy MDG events that are
 * not yet represented in the MMC program/bridge chain.
 *
 * IMPORTANT: This module never creates or updates programs, venues, events,
 * sessions, ticket types, mappings or bridges.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function mdg_ai_legacy_preview_can_run( $input = null ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return new WP_Error( 'mdg_ai_legacy_preview_forbidden', 'WooCommerce yönetim yetkisi gerekir.' );
    }
    if ( ! class_exists( 'MDG_DB' ) || ! class_exists( 'MMC_Program_Service' ) || ! class_exists( 'MMC_MDG_Bridge_Service' ) ) {
        return new WP_Error( 'mdg_ai_legacy_preview_missing', 'MDG/MMC servisleri kullanılamıyor.' );
    }
    return true;
}

function mdg_ai_legacy_preview_place_key( $value ) {
    $value = strtolower( remove_accents( trim( (string) $value ) ) );
    return (string) preg_replace( '/[^a-z0-9]+/', '', $value );
}

function mdg_ai_legacy_preview_local_datetime( $utc_datetime ) {
    if ( class_exists( 'MDG_Sessions' ) && method_exists( 'MDG_Sessions', 'local_parts' ) ) {
        $parts = MDG_Sessions::local_parts( $utc_datetime );
        if ( ! empty( $parts[0] ) && ! empty( $parts[1] ) ) {
            return (string) $parts[0] . ' ' . substr( (string) $parts[1], 0, 5 );
        }
    }
    if ( function_exists( 'get_date_from_gmt' ) && $utc_datetime ) {
        return (string) get_date_from_gmt( (string) $utc_datetime, 'Y-m-d H:i' );
    }
    return $utc_datetime ? substr( (string) $utc_datetime, 0, 16 ) : '';
}

function mdg_ai_legacy_preview_program_matches( $province, $district, $date ) {
    global $wpdb;
    $table = $wpdb->prefix . 'mmc_programs';
    $rows = (array) $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id,program_code,province_name,district_name,planned_date,status
             FROM {$table}
             WHERE planned_date=%s
             ORDER BY id ASC",
            $date
        )
    );

    $province_key = mdg_ai_legacy_preview_place_key( $province );
    $district_key = mdg_ai_legacy_preview_place_key( $district );

    return array_values( array_filter( $rows, static function( $row ) use ( $province_key, $district_key ) {
        if ( mdg_ai_legacy_preview_place_key( $row->province_name ) !== $province_key ) {
            return false;
        }
        if ( $district_key && mdg_ai_legacy_preview_place_key( $row->district_name ) !== $district_key ) {
            return false;
        }
        return true;
    } ) );
}

function mdg_ai_legacy_preview_event_row( $event ) {
    global $wpdb;

    $sessions_table = MDG_DB::table( 'sessions' );
    $types_table    = MDG_DB::table( 'ticket_types' );

    $sessions = (array) $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id,start_at,wc_product_id,tickera_event_id
             FROM {$sessions_table}
             WHERE event_id=%d
             ORDER BY start_at ASC,id ASC",
            (int) $event->id
        )
    );

    $session_ids = array_map( static function( $row ) { return (int) $row->id; }, $sessions );
    $ticket_rows = array();

    if ( $session_ids ) {
        $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
        $sql = $wpdb->prepare(
            "SELECT id,session_id,wc_variation_id,is_active
             FROM {$types_table}
             WHERE session_id IN ({$placeholders})
             ORDER BY session_id,id",
            $session_ids
        );
        $ticket_rows = (array) $wpdb->get_results( $sql );
    }

    $venue_exists = false;
    $venue = null;
    $default_capacity = 0;
    if ( ! empty( $event->venue_id ) && class_exists( 'MDG_Venues' ) && method_exists( 'MDG_Venues', 'get' ) ) {
        $venue = MDG_Venues::get( (int) $event->venue_id );
        $venue_exists = (bool) $venue;
        if ( $venue && isset( $venue->default_capacity ) ) {
            $default_capacity = max( 0, (int) $venue->default_capacity );
        }
    }

    $dates = array();
    $session_plan = array();
    foreach ( $sessions as $session ) {
        $local = mdg_ai_legacy_preview_local_datetime( $session->start_at );
        $date  = $local ? substr( $local, 0, 10 ) : '';
        if ( $date ) { $dates[$date] = true; }

        $session_plan[] = array(
            'mdg_session_id'  => (int) $session->id,
            'local_datetime'  => $local,
            'capacity'        => $default_capacity,
            'status'          => 'active',
            'wc_product_id'   => (int) $session->wc_product_id,
            'tickera_event_id'=> (int) $session->tickera_event_id,
        );
    }

    $dates = array_keys( $dates );
    sort( $dates );

    $planned_date = 1 === count( $dates ) ? $dates[0] : '';
    $program_matches = $planned_date
        ? mdg_ai_legacy_preview_program_matches( $event->province_name, $event->district, $planned_date )
        : array();

    $bridge_program_id = (int) MMC_MDG_Bridge_Service::program_for_mdg_event( (int) $event->id );

    $warnings = array();
    if ( ! $sessions ) { $warnings[] = 'MDG etkinliğinde seans bulunamadı.'; }
    if ( count( $dates ) > 1 ) { $warnings[] = 'MDG seansları birden fazla yerel tarihe yayılıyor.'; }
    if ( ! $planned_date ) { $warnings[] = 'Tekil plan tarihi güvenle belirlenemedi.'; }
    if ( empty( $event->venue_id ) || ! $venue_exists ) { $warnings[] = 'MDG salon ana kaydı doğrulanamadı.'; }
    if ( $venue_exists && $default_capacity < 1 ) { $warnings[] = 'MDG salon varsayılan kapasitesi eksik.'; }
    if ( count( $program_matches ) > 1 ) { $warnings[] = 'Aynı il/ilçe/tarihte birden fazla MMC programı bulundu.'; }

    if ( $bridge_program_id ) {
        $action = 'already_bridged';
    } elseif ( 1 === count( $program_matches ) ) {
        $action = 'bridge_existing_program';
    } elseif ( 0 === count( $program_matches ) && $planned_date && $venue_exists && $sessions ) {
        $action = 'create_program_then_bridge';
    } else {
        $action = 'conflict_or_incomplete';
    }

    $tickets = array_map( static function( $row ) {
        return array(
            'mdg_ticket_type_id' => (int) $row->id,
            'mdg_session_id'     => (int) $row->session_id,
            'wc_variation_id'    => (int) $row->wc_variation_id,
            'is_active'          => (bool) $row->is_active,
        );
    }, $ticket_rows );

    return array(
        'mdg_event' => array(
            'id'            => (int) $event->id,
            'title'         => (string) $event->title,
            'province_name' => (string) $event->province_name,
            'district'      => (string) $event->district,
            'venue_id'      => (int) $event->venue_id,
            'venue_name'    => (string) $event->venue_name,
            'status'        => (string) $event->status,
        ),
        'derived' => array(
            'planned_date' => $planned_date,
            'local_dates'  => $dates,
            'plan_year'    => $planned_date ? (int) substr( $planned_date, 0, 4 ) : 0,
        ),
        'venue' => array(
            'source' => 'mdg',
            'exists' => $venue_exists,
            'id'     => (int) $event->venue_id,
            'name'   => (string) $event->venue_name,
        ),
        'sessions' => $session_plan,
        'ticket_types' => $tickets,
        'existing_bridge_program_id' => $bridge_program_id,
        'matching_mmc_programs' => array_map( static function( $row ) {
            return array(
                'id'            => (int) $row->id,
                'program_code'  => (string) $row->program_code,
                'province_name' => (string) $row->province_name,
                'district_name' => (string) $row->district_name,
                'planned_date'  => (string) $row->planned_date,
                'status'        => (string) $row->status,
            );
        }, $program_matches ),
        'suggested_action' => $action,
        'safe_for_later_write' => in_array( $action, array( 'bridge_existing_program', 'create_program_then_bridge' ), true ) && ! $warnings,
        'warnings' => $warnings,
        'no_write' => true,
    );
}

function mdg_ai_legacy_mdg_mmc_migration_preview( $input ) {
    global $wpdb;

    $events_table = MDG_DB::table( 'events' );
    $requested = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['mdg_event_ids'] ?? array() ) ) ) ) );
    $future_only = ! array_key_exists( 'future_only', $input ) || ! empty( $input['future_only'] );
    $unbridged_only = ! array_key_exists( 'unbridged_only', $input ) || ! empty( $input['unbridged_only'] );

    if ( $requested ) {
        $placeholders = implode( ',', array_fill( 0, count( $requested ), '%d' ) );
        $events = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$events_table} WHERE id IN ({$placeholders}) ORDER BY id ASC",
                $requested
            )
        );
    } else {
        $events = (array) $wpdb->get_results(
            "SELECT * FROM {$events_table} WHERE status='onsale' ORDER BY id ASC"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    $today = wp_date( 'Y-m-d' );
    $items = array();

    foreach ( $events as $event ) {
        $row = mdg_ai_legacy_preview_event_row( $event );

        if ( $future_only ) {
            $date = (string) ( $row['derived']['planned_date'] ?? '' );
            if ( ! $date || $date < $today ) { continue; }
        }

        if ( $unbridged_only && ! empty( $row['existing_bridge_program_id'] ) ) {
            continue;
        }

        $items[] = $row;
    }

    $summary = array(
        'count' => count( $items ),
        'create_program_then_bridge' => 0,
        'bridge_existing_program' => 0,
        'already_bridged' => 0,
        'conflict_or_incomplete' => 0,
        'safe_for_later_write' => 0,
    );

    foreach ( $items as $row ) {
        $action = (string) $row['suggested_action'];
        if ( isset( $summary[$action] ) ) { $summary[$action]++; }
        if ( ! empty( $row['safe_for_later_write'] ) ) { $summary['safe_for_later_write']++; }
    }

    return array(
        'readonly' => true,
        'generated_at' => current_time( 'mysql' ),
        'filters' => array(
            'mdg_event_ids' => $requested,
            'future_only' => $future_only,
            'unbridged_only' => $unbridged_only,
        ),
        'summary' => $summary,
        'items' => $items,
    );
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-legacy-migration-preview',
            array(
                'label' => 'Madagaskar Legacy MDG → MMC Preview',
                'description' => 'Legacy MDG etkinliklerinin MMC program zincirine taşınmasını salt-okunur önizler.',
            )
        );
    }
} );

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) { return; }

    wp_register_ability( 'madagaskar/legacy-mdg-mmc-migration-preview', array(
        'label' => 'Legacy MDG → MMC Migration Önizlemesi',
        'description' => 'MDG etkinliklerini MMC program/salon/seans/bridge zinciri açısından salt-okunur analiz eder; hiçbir kayıt yazmaz.',
        'category' => 'madagaskar-legacy-migration-preview',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'mdg_event_ids' => array(
                    'type' => 'array',
                    'items' => array( 'type' => 'integer', 'minimum' => 1 ),
                ),
                'future_only' => array( 'type' => 'boolean' ),
                'unbridged_only' => array( 'type' => 'boolean' ),
            ),
        ),
        'output_schema' => array( 'type' => 'object' ),
        'execute_callback' => 'mdg_ai_legacy_mdg_mmc_migration_preview',
        'permission_callback' => 'mdg_ai_legacy_preview_can_run',
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true,
            ),
            'public' => true,
        ),
    ) );
} );
