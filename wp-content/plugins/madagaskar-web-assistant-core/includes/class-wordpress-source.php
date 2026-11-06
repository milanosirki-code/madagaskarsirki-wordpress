<?php
namespace Madagaskar\WebAssistant;

/** Bounded, read-only adapter over existing domain services. Never calls ensure/sync. */
final class WordPressSource {
    public function __construct() {
        foreach ( array( 'MMC_Program_Service', 'MMC_Event_Service', 'MMC_Venue_Service', 'MDG_Events', 'MDG_Sessions', 'MDG_Capacity', 'MDG_Public_Event', 'MDG_DB' ) as $class ) {
            if ( ! class_exists( $class ) ) { throw new Failure( 'DEPENDENCY_UNAVAILABLE' ); }
        }
        if ( ! function_exists( 'wc_get_product' ) || wp_timezone_string() !== 'Europe/Istanbul' ) {
            throw new Failure( 'DEPENDENCY_OR_TIMEZONE_UNAVAILABLE' );
        }
    }

    public function programs() {
        $rows = \MMC_Program_Service::all_programs();
        if ( ! is_array( $rows ) || count( $rows ) > 500 ) { throw new Failure( 'SOURCE_INCOMPLETE' ); }
        return $rows;
    }

    private function bridges( $column, $id ) {
        global $wpdb;
        if ( ! in_array( $column, array( 'program_id', 'mdg_event_id' ), true ) ) { throw new Failure( 'INVALID_BRIDGE_COLUMN' ); }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT program_id,mmc_event_id,mdg_event_id FROM {$wpdb->prefix}mmc_mdg_event_bridge WHERE {$column}=%d AND is_active=1 ORDER BY id ASC",
            $id
        ) );
        if ( ! is_array( $rows ) ) { throw new Failure( 'SOURCE_UNAVAILABLE' ); }
        return array_map( static function( $r ) {
            return array( 'program_id' => (int) $r->program_id, 'mmc_event_id' => (int) $r->mmc_event_id, 'mdg_event_id' => (int) $r->mdg_event_id );
        }, $rows );
    }

    public function bundle_for_session( $session_id ) {
        Value::integer( $session_id, 1, PHP_INT_MAX );
        global $wpdb;
        $event_id = $wpdb->get_var( $wpdb->prepare(
            'SELECT event_id FROM ' . \MDG_DB::table( 'sessions' ) . ' WHERE id=%d', $session_id
        ) );
        if ( ! $event_id ) { throw new Failure( 'SESSION_NOT_FOUND' ); }
        $bridges = $this->bridges( 'mdg_event_id', (int) $event_id );
        if ( count( $bridges ) !== 1 ) { throw new Failure( 'AMBIGUOUS_BRIDGE' ); }
        return $this->bundle( $bridges[0]['program_id'] );
    }

    public function bundle( $program_id ) {
        Value::integer( $program_id, 1, PHP_INT_MAX );
        $program = \MMC_Program_Service::get_program( $program_id );
        $mmc = \MMC_Event_Service::event_for_program( $program_id );
        $forward = $this->bridges( 'program_id', $program_id );
        if ( ! $program || ! $mmc ) { throw new Failure( 'PROGRAM_NOT_FOUND' ); }
        if ( count( $forward ) !== 1 ) { throw new Failure( 'AMBIGUOUS_BRIDGE' ); }
        $mdg = \MDG_Events::get( $forward[0]['mdg_event_id'] );
        if ( ! $mdg ) { throw new Failure( 'EVENT_NOT_FOUND' ); }
        $reverse = $this->bridges( 'mdg_event_id', (int) $mdg->id );
        $venue = \MMC_Venue_Service::get_program_venue( (int) $mmc->program_venue_id );
        $mmc_times = array();
        foreach ( (array) \MMC_Event_Service::sessions( (int) $mmc->id ) as $s ) {
            if ( (string) $s->status === 'active' ) { $mmc_times[] = (string) $s->session_time; }
        }
        $session_rows = \MDG_Sessions::by_event( (int) $mdg->id );
        if ( ! is_array( $session_rows ) || count( $session_rows ) > 100 ) { throw new Failure( 'SOURCE_INCOMPLETE' ); }
        $sessions = array(); $family_conflict = false;
        foreach ( $session_rows as $s ) {
            $product = wc_get_product( (int) $s->wc_product_id );
            $parent_max = $product ? (int) $product->get_max_purchase_quantity() : 0;
            $rows = \MDG_Sessions::ticket_types_by_session( (int) $s->id );
            if ( ! is_array( $rows ) || count( $rows ) > 30 ) { throw new Failure( 'SOURCE_INCOMPLETE' ); }
            $tickets = array();
            foreach ( $rows as $t ) {
                if ( (int) $t->is_active && (int) $t->capacity_units > 1 ) { $family_conflict = true; }
                $variation = (int) $t->wc_variation_id ? wc_get_product( (int) $t->wc_variation_id ) : false;
                $ready = $variation && $variation->is_type( 'variation' )
                    && (int) $variation->get_parent_id() === (int) $s->wc_product_id
                    && $variation->is_purchasable() && $variation->is_in_stock()
                    && $variation->get_status() === 'publish';
                $max = $ready ? (int) $variation->get_max_purchase_quantity() : 0;
                $tickets[] = array(
                    'code' => (string) $t->code, 'active' => (int) $t->is_active === 1,
                    'price' => (string) $t->price, 'capacity_units' => (int) $t->capacity_units,
                    'variation_id' => (int) $t->wc_variation_id, 'variation_ready' => (bool) $ready,
                    'wc_price' => $ready ? (string) $variation->get_price() : '0',
                    'max_quantity' => $ready ? ( $max < 0 ? 20 : min( 20, $max ) ) : 0,
                );
            }
            $sessions[] = array(
                'id' => (int) $s->id, 'event_id' => (int) $s->event_id, 'status' => (string) $s->status,
                'start_at' => (string) $s->start_at, 'sale_start' => $s->sale_start, 'sale_end' => $s->sale_end,
                'native_closed' => (bool) \MDG_Sessions::sales_closed_by_time( $s ),
                'available_units' => (int) \MDG_Capacity::available( (int) $s->id ),
                'tickera_event_id' => (int) $s->tickera_event_id,
                'product_ready' => (bool) ( $product && $product->is_type( 'variable' ) && $product->get_status() === 'publish' ),
                'parent_max_quantity' => $parent_max < 0 ? 40 : min( 40, $parent_max ),
                'tickets' => $tickets,
            );
        }
        return array(
            'program' => array( 'id' => (int) $program->id, 'brand' => (string) ( $program->brand ?? '' ),
                'status' => (string) $program->status, 'planned_date' => (string) $program->planned_date,
                'city' => (string) $program->province_name, 'district' => (string) $program->district_name ),
            'mmc_event' => array( 'id' => (int) $mmc->id, 'program_id' => (int) $mmc->program_id,
                'status' => (string) $mmc->status, 'event_date' => (string) $mmc->event_date ),
            'mdg_event' => array( 'id' => (int) $mdg->id, 'title' => (string) $mdg->title,
                'status' => (string) $mdg->status, 'sale_start' => $mdg->sale_start, 'sale_end' => $mdg->sale_end ),
            'program_bridges' => $forward, 'event_bridges' => $reverse,
            'venue_matches' => (bool) ( $venue && (string) $venue->venue_source === 'mdg'
                && (int) $venue->venue_id === (int) $mdg->venue_id && (int) $venue->program_id === $program_id
                && (int) $venue->is_selected === 1 ),
            'venue' => array( 'name' => (string) $mdg->venue_name, 'address' => (string) $mdg->venue_address ),
            'ticket_url' => \MDG_Public_Event::live_url( $mdg ), 'sessions' => $sessions,
            'mmc_session_times' => $mmc_times, 'family_conflict' => $family_conflict,
            'family' => $this->family( (int) $mdg->id, (int) $mmc->id ),
        );
    }

    /** Mirrors native plugin enablement and price precedence, without invoking its private methods. */
    private function family( $mdg_id, $mmc_id ) {
        $off = array( 'available' => false, 'price_minor' => null, 'reason' => 'FAMILY_NOT_ENABLED' );
        if ( ! class_exists( 'MDG_Family_Package_22_V1' ) ) { return $off; }
        $settings = get_option( 'mdg_family_package_22_v1', array() );
        if ( ! is_array( $settings ) || ! is_array( $settings['events'] ?? null ) ) { return $off; }
        $events = $settings['events'];
        $is_list = array_keys( $events ) === range( 0, count( $events ) - 1 );
        $enabled = $is_list ? $events : array_keys( array_filter( $events ) );
        if ( ! in_array( $mdg_id, array_map( 'intval', $enabled ), true ) ) { return $off; }
        $active = array();
        foreach ( (array) \MMC_Event_Service::ticket_types( $mmc_id ) as $t ) {
            if ( (string) $t->ticket_code === 'family_2_2' && (int) $t->is_active === 1 ) { $active[] = $t; }
        }
        try {
            if ( count( $active ) > 1 ) { throw new Failure( 'AMBIGUOUS_FAMILY_PRICE' ); }
            $price = $active ? Value::minor( (string) $active[0]->price ) : 0;
            $source = 'active_mmc_family';
            if ( ! $price ) {
                $source = 'event_family_setting';
                $value = $settings['event_prices'][ $mdg_id ] ?? $settings['price'] ?? null;
                $price = Value::minor( (string) $value );
            }
            if ( ! $price ) { throw new Failure( 'INVALID_FAMILY_PRICE' ); }
            return array( 'available' => true, 'price_minor' => $price, 'source' => $source, 'capacity_units' => 4 );
        } catch ( Failure $e ) {
            return array( 'available' => false, 'price_minor' => null, 'reason' => $e->getMessage() );
        }
    }
}
