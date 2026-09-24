<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC <-> Madagaskar Bilet Yönetimi bridge.
 *
 * The legacy MDG ticket engine remains the source of truth for its own
 * event/session/order-map tables. MMC stores only the cross-system identity.
 */
class MMC_MDG_Bridge_Service {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'mmc_mdg_event_bridge';
    }

    public static function legacy_available() {
        if ( ! class_exists( 'MDG_DB' ) || ! method_exists( 'MDG_DB', 'table' ) ) { return false; }
        foreach ( array( 'events', 'sessions', 'ticket_types', 'order_map' ) as $suffix ) {
            if ( ! self::table_exists( MDG_DB::table( $suffix ) ) ) { return false; }
        }
        return true;
    }

    public static function engine_version() {
        return defined( 'MDG_BILET_VERSION' ) ? (string) MDG_BILET_VERSION : '';
    }

    public static function bridge_for_program( $program_id ) {
        global $wpdb;
        $table = self::table();
        if ( ! self::table_exists( $table ) ) { return null; }
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE program_id=%d AND is_active=1 ORDER BY id DESC LIMIT 1",
            absint( $program_id )
        ) );
    }

    public static function program_for_mdg_event( $mdg_event_id ) {
        global $wpdb;
        $table = self::table();
        if ( ! self::table_exists( $table ) ) { return 0; }
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT program_id FROM {$table} WHERE mdg_event_id=%d AND is_active=1 LIMIT 1",
            absint( $mdg_event_id )
        ) );
    }

    public static function get_mdg_event( $mdg_event_id ) {
        if ( ! self::legacy_available() ) { return null; }
        global $wpdb;
        $events = MDG_DB::table( 'events' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$events} WHERE id=%d LIMIT 1", absint( $mdg_event_id ) ) );
    }

    public static function admin_url( $program_id ) {
        $bridge = self::bridge_for_program( $program_id );
        $args = array( 'page'=>'mdg-dashboard', 'mmc_program_id'=>absint($program_id) );
        if ( $bridge && self::get_mdg_event( (int)$bridge->mdg_event_id ) ) {
            $args['page'] = 'mdg-publish';
            $args['edit'] = (int)$bridge->mdg_event_id;
        }
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    public static function candidates( $program_id ) {
        if ( ! self::legacy_available() ) { return array(); }
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { return array(); }

        global $wpdb;
        $events_table = MDG_DB::table( 'events' );
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $events = (array) $wpdb->get_results( "SELECT * FROM {$events_table} ORDER BY id DESC LIMIT 500" );
        $identity = self::mmc_identity( $program_id );
        $venue_name = self::selected_venue_name( $program_id );
        $out = array();

        foreach ( $events as $event ) {
            $sessions = (array) $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$sessions_table} WHERE event_id=%d ORDER BY start_at ASC,id ASC",
                (int)$event->id
            ) );
            if ( ! $sessions ) { continue; }

            $mdg_products = array();
            $mdg_tickera = array();
            $mdg_variations = array();
            $dates = array();
            $session_ids = array();

            foreach ( $sessions as $session ) {
                $session_ids[] = (int)$session->id;
                if ( (int)$session->wc_product_id ) { $mdg_products[] = (int)$session->wc_product_id; }
                if ( (int)$session->tickera_event_id ) { $mdg_tickera[] = (int)$session->tickera_event_id; }
                $date = self::legacy_local_date( $session->start_at );
                if ( $date ) { $dates[$date] = true; }
            }
            if ( $session_ids ) {
                $placeholders = implode( ',', array_fill( 0, count($session_ids), '%d' ) );
                $sql = $wpdb->prepare(
                    "SELECT wc_variation_id FROM {$types_table} WHERE session_id IN ({$placeholders}) AND is_active=1",
                    $session_ids
                );
                foreach ( (array)$wpdb->get_col( $sql ) as $variation_id ) {
                    if ( (int)$variation_id ) { $mdg_variations[] = (int)$variation_id; }
                }
            }

            $product_overlap = count( array_intersect( $identity['products'], array_values(array_unique($mdg_products)) ) );
            $variation_overlap = count( array_intersect( $identity['variations'], array_values(array_unique($mdg_variations)) ) );
            $tickera_overlap = count( array_intersect( $identity['tickera'], array_values(array_unique($mdg_tickera)) ) );
            $identity_overlap = $product_overlap + $variation_overlap + $tickera_overlap;

            $province_match = self::same_text( $program->province_name, $event->province_name );
            $district_match = ! $program->district_name || self::same_text( $program->district_name, $event->district );
            $date_match = ! empty($program->planned_date) && isset( $dates[(string)$program->planned_date] );
            $venue_match = $venue_name && self::same_text( $venue_name, $event->venue_name );

            $score = ( $variation_overlap * 500 ) + ( $product_overlap * 300 ) + ( $tickera_overlap * 200 );
            if ( $province_match ) { $score += 40; }
            if ( $district_match ) { $score += 30; }
            if ( $date_match ) { $score += 20; }
            if ( $venue_match ) { $score += 10; }

            if ( $score <= 0 ) { continue; }
            $out[] = array(
                'event_id'          => (int)$event->id,
                'title'             => (string)$event->title,
                'province_name'     => (string)$event->province_name,
                'district'          => (string)$event->district,
                'venue_name'        => (string)$event->venue_name,
                'status'            => (string)$event->status,
                'session_count'     => count($sessions),
                'dates'             => array_keys($dates),
                'score'             => $score,
                'strong'            => $identity_overlap > 0,
                'identity_overlap'  => $identity_overlap,
                'product_overlap'   => $product_overlap,
                'variation_overlap' => $variation_overlap,
                'tickera_overlap'   => $tickera_overlap,
                'province_match'    => $province_match,
                'district_match'    => $district_match,
                'date_match'        => $date_match,
                'venue_match'       => $venue_match,
            );
        }

        usort( $out, function( $a, $b ) {
            if ( (int)$a['score'] === (int)$b['score'] ) { return (int)$b['event_id'] <=> (int)$a['event_id']; }
            return (int)$b['score'] <=> (int)$a['score'];
        } );
        return $out;
    }

    public static function auto_link( $program_id ) {
        if ( ! self::legacy_available() ) {
            return new WP_Error( 'mmc_mdg_missing', 'Madagaskar Bilet Yönetimi motoru aktif/erişilebilir değil.' );
        }
        if ( self::bridge_for_program( $program_id ) ) { return self::bridge_for_program( $program_id ); }

        $strong = array_values( array_filter( self::candidates($program_id), function($row){ return ! empty($row['strong']); } ) );
        if ( 1 !== count($strong) ) {
            return new WP_Error(
                'mmc_mdg_ambiguous',
                $strong ? 'Birden fazla güçlü MDG etkinlik adayı bulundu; otomatik bağlantı yapılmadı.' : 'WooCommerce/Tickera kimlikleriyle kesin MDG etkinlik adayı bulunamadı.'
            );
        }
        return self::link( $program_id, (int)$strong[0]['event_id'], 'identity', 100 );
    }

    public static function link( $program_id, $mdg_event_id, $method = 'manual', $confidence = 100 ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $mdg_event_id = absint( $mdg_event_id );
        $program = MMC_Program_Service::get_program( $program_id );
        $mmc_event = class_exists('MMC_Event_Service') ? MMC_Event_Service::event_for_program( $program_id ) : null;
        $mdg_event = self::get_mdg_event( $mdg_event_id );
        if ( ! $program || ! $mmc_event ) { return new WP_Error( 'mmc_mdg_program', 'MMC program/etkinlik kaydı bulunamadı.' ); }
        if ( ! $mdg_event ) { return new WP_Error( 'mmc_mdg_event', 'MDG etkinlik kaydı bulunamadı.' ); }

        $table = self::table();
        if ( ! self::table_exists($table) ) { return new WP_Error( 'mmc_mdg_table', 'MDG köprü tablosu bulunamadı; MMC veritabanı yükseltmesini çalıştırın.' ); }

        $conflict = (int)$wpdb->get_var( $wpdb->prepare(
            "SELECT program_id FROM {$table} WHERE mdg_event_id=%d AND program_id<>%d AND is_active=1 LIMIT 1",
            $mdg_event_id, $program_id
        ) );
        if ( $conflict ) {
            return new WP_Error( 'mmc_mdg_conflict', 'Bu MDG etkinliği başka bir MMC Program ID ile bağlı.' );
        }

        $now = current_time('mysql');
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE program_id=%d LIMIT 1", $program_id ) );
        $data = array(
            'mmc_event_id' => (int)$mmc_event->id,
            'mdg_event_id' => $mdg_event_id,
            'match_method' => sanitize_key($method) ?: 'manual',
            'confidence'   => max(0,min(100,absint($confidence))),
            'is_active'    => 1,
            'linked_by'    => get_current_user_id() ?: null,
            'updated_at'   => $now,
        );
        if ( $existing ) {
            $ok = $wpdb->update( $table, $data, array('id'=>(int)$existing->id) );
            $bridge_id = (int)$existing->id;
        } else {
            $data['program_id'] = $program_id;
            $data['created_at'] = $now;
            $ok = $wpdb->insert( $table, $data );
            $bridge_id = (int)$wpdb->insert_id;
        }
        if ( false === $ok ) { return new WP_Error( 'mmc_mdg_save', 'MDG köprüsü kaydedilemedi.' ); }

        MMC_Program_Service::add_log(
            $program_id,
            'mdg_ticket_bridge_linked',
            'mdg_event_bridge',
            $bridge_id,
            null,
            array('mmc_event_id'=>(int)$mmc_event->id,'mdg_event_id'=>$mdg_event_id,'match_method'=>$data['match_method']),
            'MMC Program ID ile Madagaskar Bilet Yönetimi etkinliği eşleştirildi.'
        );
        return self::bridge_for_program( $program_id );
    }

    public static function status( $program_id ) {
        $result = array(
            'available' => self::legacy_available(),
            'version' => self::engine_version(),
            'linked' => false,
            'bridge' => null,
            'event' => null,
            'candidates' => array(),
            'identity_expected' => 0,
            'identity_matched' => 0,
            'sessions_mmc' => 0,
            'sessions_mdg' => 0,
            'sales' => array(),
        );
        if ( ! $result['available'] ) { return $result; }

        $bridge = self::bridge_for_program( $program_id );
        if ( ! $bridge ) {
            $result['candidates'] = self::candidates( $program_id );
            return $result;
        }
        $event = self::get_mdg_event( (int)$bridge->mdg_event_id );
        if ( ! $event ) {
            $result['bridge'] = $bridge;
            return $result;
        }

        $result['linked'] = true;
        $result['bridge'] = $bridge;
        $result['event'] = $event;

        $mmc_event = class_exists('MMC_Event_Service') ? MMC_Event_Service::event_for_program( $program_id ) : null;
        $identity = self::mmc_identity( $program_id );
        $result['identity_expected'] = count($identity['rows']);
        $result['sessions_mmc'] = $mmc_event && class_exists('MMC_Event_Service') ? count(MMC_Event_Service::sessions((int)$mmc_event->id)) : 0;

        global $wpdb;
        $sessions_table = MDG_DB::table('sessions');
        $types_table = MDG_DB::table('ticket_types');
        $legacy_rows = (array)$wpdb->get_results( $wpdb->prepare(
            "SELECT s.id mdg_session_id,s.wc_product_id,s.tickera_event_id,t.wc_variation_id
             FROM {$sessions_table} s
             LEFT JOIN {$types_table} t ON t.session_id=s.id AND t.is_active=1
             WHERE s.event_id=%d",
            (int)$event->id
        ) );
        $result['sessions_mdg'] = count( array_unique(array_map(function($r){ return (int)$r->mdg_session_id; }, $legacy_rows)) );

        foreach ( $identity['rows'] as $map ) {
            foreach ( $legacy_rows as $legacy ) {
                if ( (int)$map->wc_product_id === (int)$legacy->wc_product_id
                    && (int)$map->wc_variation_id === (int)$legacy->wc_variation_id
                    && (int)$map->tickera_event_id === (int)$legacy->tickera_event_id ) {
                    $result['identity_matched']++;
                    break;
                }
            }
        }

        $result['sales'] = self::sales_reconciliation( $program_id, (int)$event->id );
        return $result;
    }

    public static function backfill_existing_programs() {
        if ( ! self::legacy_available() || ! self::table_exists(self::table()) ) { return; }
        global $wpdb;
        $programs = (array)$wpdb->get_col( "SELECT id FROM {$wpdb->prefix}mmc_programs WHERE status<>'cancelled' ORDER BY id ASC" );
        foreach ( $programs as $program_id ) {
            if ( self::bridge_for_program((int)$program_id) ) { continue; }
            self::auto_link( (int)$program_id );
        }
    }

    private static function sales_reconciliation( $program_id, $mdg_event_id ) {
        global $wpdb;
        $mdg_order_map = MDG_DB::table('order_map');
        $mmc_ledger = $wpdb->prefix . 'mmc_sales_ledger';

        $mdg_rows = (array)$wpdb->get_results( $wpdb->prepare(
            "SELECT order_id,order_item_id,quantity,units_total,line_total
             FROM {$mdg_order_map}
             WHERE event_id=%d AND paid_at IS NOT NULL
               AND order_status NOT IN ('failed','cancelled','refunded','trash')",
            $mdg_event_id
        ) );
        $mmc_rows = self::table_exists($mmc_ledger) ? (array)$wpdb->get_results( $wpdb->prepare(
            "SELECT external_order_id order_id,external_order_item_id order_item_id,net_quantity quantity,capacity_units units_total,net_amount
             FROM {$mmc_ledger}
             WHERE program_id=%d AND channel='woocommerce' AND paid_at IS NOT NULL
               AND order_status NOT IN ('failed','cancelled','refunded','trash')",
            absint($program_id)
        ) ) : array();

        $mdg_items = array();
        $mmc_items = array();
        $mdg_orders = array();
        $mmc_orders = array();
        $mdg_units = 0;
        $mmc_units = 0;
        $mdg_revenue_ex_tax = 0.0;
        $mmc_revenue = 0.0;

        foreach ( $mdg_rows as $row ) {
            $mdg_items[(int)$row->order_item_id] = true;
            $mdg_orders[(int)$row->order_id] = true;
            $mdg_units += (int)$row->units_total;
            $mdg_revenue_ex_tax += (float)$row->line_total;
        }
        foreach ( $mmc_rows as $row ) {
            $mmc_items[(int)$row->order_item_id] = true;
            $mmc_orders[(int)$row->order_id] = true;
            $mmc_units += (int)$row->units_total;
            $mmc_revenue += (float)$row->net_amount;
        }

        $missing_in_mmc = array_values(array_diff(array_keys($mdg_items),array_keys($mmc_items)));
        $extra_in_mmc = array_values(array_diff(array_keys($mmc_items),array_keys($mdg_items)));
        $has_sales = count($mdg_items) + count($mmc_items) > 0;
        $ok = !$missing_in_mmc && !$extra_in_mmc && count($mdg_orders)===count($mmc_orders) && $mdg_units===$mmc_units;

        return array(
            'has_sales'=>$has_sales,
            'ok'=>$ok,
            'mdg_orders'=>count($mdg_orders),
            'mmc_orders'=>count($mmc_orders),
            'mdg_items'=>count($mdg_items),
            'mmc_items'=>count($mmc_items),
            'mdg_units'=>$mdg_units,
            'mmc_units'=>$mmc_units,
            'missing_in_mmc'=>$missing_in_mmc,
            'extra_in_mmc'=>$extra_in_mmc,
            'mdg_revenue_ex_tax'=>round($mdg_revenue_ex_tax,2),
            'mmc_revenue'=>round($mmc_revenue,2),
        );
    }

    private static function mmc_identity( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_sales_mappings';
        $rows = self::table_exists($table) ? (array)$wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE program_id=%d AND is_active=1 ORDER BY id ASC",
            absint($program_id)
        ) ) : array();
        $products = array();
        $variations = array();
        $tickera = array();
        foreach ( $rows as $row ) {
            if ( (int)$row->wc_product_id ) { $products[]=(int)$row->wc_product_id; }
            if ( (int)$row->wc_variation_id ) { $variations[]=(int)$row->wc_variation_id; }
            if ( (int)$row->tickera_event_id ) { $tickera[]=(int)$row->tickera_event_id; }
        }
        return array(
            'rows'=>$rows,
            'products'=>array_values(array_unique($products)),
            'variations'=>array_values(array_unique($variations)),
            'tickera'=>array_values(array_unique($tickera)),
        );
    }

    private static function selected_venue_name( $program_id ) {
        global $wpdb;
        $pv = $wpdb->prefix . 'mmc_program_venues';
        $v = $wpdb->prefix . 'mmc_venues';
        if ( ! self::table_exists($pv) || ! self::table_exists($v) ) { return ''; }
        return (string)$wpdb->get_var( $wpdb->prepare(
            "SELECT v.venue_name FROM {$pv} pv INNER JOIN {$v} v ON v.id=pv.venue_id
             WHERE pv.program_id=%d AND pv.is_selected=1 ORDER BY pv.id DESC LIMIT 1",
            absint($program_id)
        ) );
    }

    private static function legacy_local_date( $utc_datetime ) {
        if ( class_exists('MDG_Sessions') && method_exists('MDG_Sessions','local_parts') ) {
            $parts = MDG_Sessions::local_parts( $utc_datetime );
            return ! empty($parts[0]) ? (string)$parts[0] : '';
        }
        return $utc_datetime ? substr( (string)$utc_datetime, 0, 10 ) : '';
    }

    private static function same_text( $a, $b ) {
        return self::normalize_text($a) !== '' && self::normalize_text($a) === self::normalize_text($b);
    }

    private static function normalize_text( $value ) {
        $value = strtolower( remove_accents( trim((string)$value) ) );
        return preg_replace( '/[^a-z0-9]+/', '', $value );
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return $table && $wpdb->get_var( $wpdb->prepare('SHOW TABLES LIKE %s',$table) ) === $table;
    }
}
