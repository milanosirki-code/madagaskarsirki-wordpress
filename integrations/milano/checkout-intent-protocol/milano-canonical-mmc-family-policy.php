<?php
/**
 * Milano trusted MMC family-price adapter — REVIEW-ONLY / READ-ONLY.
 *
 * Gets the 2+2 price ONLY from a unique MMC event under the already
 * verified Milano program, with one active bidirectional MDG bridge. A NULL
 * mmc_events.mdg_event_id is tolerated ONLY after that bridge is proven. Unlike the
 * installed legacy family plugin, no global/default/option price fallback.
 *
 * No WordPress hook, route, Woo order, ticket, payment, hold or DB writes.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Canonical_MMC_Family_Policy {
    private static function table( $name ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! is_string( $wpdb->prefix ?? null )
            || ! preg_match( '/\\A[A-Za-z0-9_]{0,48}\\z/D', $wpdb->prefix ) ) {
            return null;
        }
        return $wpdb->prefix . $name;
    }

    /** Null means NOT ELIGIBLE, never insert a guessed family price. */
    public static function lookup( $program_id, $mdg_event_id, $ticket_snapshots ) {
        if ( ! is_int( $program_id ) || $program_id < 1
            || ! is_int( $mdg_event_id ) || $mdg_event_id < 1
            || ! is_array( $ticket_snapshots ) || ! $ticket_snapshots
            || ! class_exists( 'MCPI_Canonical_MDG_Snapshot' ) ) {
            return null;
        }
        global $wpdb;
        $events_table = self::table( 'mmc_events' );
        $types_table = self::table( 'mmc_ticket_types' );
        $bridge_table = self::table( 'mmc_mdg_event_bridge' );
        if ( ! $events_table || ! $types_table || ! $bridge_table
            || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_results' ) ) {
            return null;
        }

        // The live MMC event's mdg_event_id can be NULL even for a genuinely
        // linked MDG event. The authoritative 1:1 identity is the ACTIVE
        // bridge. Check the INVERSE direction too: the program must not own
        // multiple active MDG events. No historical sales mapping fallback.
        $links = $wpdb->get_results( $wpdb->prepare(
            "SELECT mdg_event_id FROM {$bridge_table}
             WHERE program_id=%d AND is_active=1 LIMIT 2", $program_id
        ) );
        if ( ! is_array( $links ) || 1 !== count( $links )
            || ! is_object( $links[0] )
            || (int) ( $links[0]->mdg_event_id ?? 0 ) !== $mdg_event_id ) {
            return null;
        }

        // MUST NOT use MMC_Event_Service::event_for_program() because it
        // chooses the first event; ambiguous MMC identity must fail closed.
        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,mdg_event_id FROM {$events_table} WHERE program_id=%d LIMIT 2",
            $program_id
        ) );
        if ( ! is_array( $events ) || count( $events ) !== 1
            || ! is_object( $events[0] )
            || (int) ( $events[0]->id ?? 0 ) < 1
            || ( null !== ( $events[0]->mdg_event_id ?? null )
                && (int) $events[0]->mdg_event_id !== $mdg_event_id ) ) {
            return null;
        }
        $mmc_event_id = (int) $events[0]->id;
        $types = $wpdb->get_results( $wpdb->prepare(
            "SELECT id,ticket_code,price,capacity_units,is_active
             FROM {$types_table} WHERE event_id=%d LIMIT 31", $mmc_event_id
        ) );
        if ( ! is_array( $types ) || count( $types ) > 30 || ! $types ) {
            return null;
        }

        $mmc = array();
        foreach ( $types as $type ) {
            if ( ! is_object( $type ) ) { return null; }
            if ( 1 !== (int) ( $type->is_active ?? 0 ) ) { continue; }
            $code = $type->ticket_code ?? '';
            if ( ! in_array( $code, array( 'child', 'adult', 'family_2_2' ), true )
                || isset( $mmc[ $code ] ) ) {
                // Unexpected active MMC ticket could invalidate 2+2 tax or
                // capacity semantics; do not guess its relationship.
                return null;
            }
            $cents = MCPI_Canonical_MDG_Snapshot::price_cents( $type->price ?? null );
            $units = (int) ( $type->capacity_units ?? 0 );
            if ( (int) ( $type->id ?? 0 ) < 1 || null === $cents
                || $cents <= 0
                || $units !== ( 'family_2_2' === $code ? 4 : 1 ) ) {
                return null;
            }
            $mmc[$code] = $cents;
        }
        if ( count( $mmc ) !== 3 ) { return null; }
        $component_total = 2 * $mmc['child'] + 2 * $mmc['adult'];
        if ( $mmc['family_2_2'] > $component_total ) { return null; }

        // MDG actual ticket types may be CHILD/ADULT or COCUK/YETISKIN.
        // Never infer roles from display text, request supplied code or
        // unrelated multi-unit virtual variants in another production event.
        $matches = array();
        $seen_ids = array();
        foreach ( $ticket_snapshots as $ticket ) {
            if ( ! is_array( $ticket ) ) { return null; }
            if ( ( $ticket['active'] ?? null ) !== true ) { continue; }
            if ( ( $ticket['capacity_units'] ?? null ) !== 1 ) { return null; }
            $id = $ticket['ticket_type_id'] ?? 0;
            if ( ! is_int( $id ) || $id < 1 || isset( $seen_ids[$id] ) ) {
                return null;
            }
            $seen_ids[$id] = true;
            $code = $ticket['code'] ?? null;
            $kind = in_array( $code, array( 'CHILD', 'COCUK' ), true ) ? 'child'
                : ( in_array( $code, array( 'ADULT', 'YETISKIN' ), true ) ? 'adult' : null );
            if ( null === $kind || isset( $matches[$kind] )
                || ( $ticket['price_cents'] ?? null ) !== $mmc[$kind]
                || ( $ticket['woo_price_cents'] ?? null ) !== $mmc[$kind]
                || ( $ticket['purchasable'] ?? null ) !== true
                || ( $ticket['in_stock'] ?? null ) !== true ) {
                return null;
            }
            $matches[$kind] = array( 'code' => $code, 'id' => $id );
        }
        if ( count( $matches ) !== 2 ) { return null; }

        return array(
            'enabled' => true,
            'price_cents' => $mmc['family_2_2'],
            'adult_code' => $matches['adult']['code'],
            'child_code' => $matches['child']['code'],
            'adult_ticket_type_id' => $matches['adult']['id'],
            'child_ticket_type_id' => $matches['child']['id'],
        );
    }
}
