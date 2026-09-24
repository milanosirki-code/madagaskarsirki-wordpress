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

    /**
     * One-way MMC -> MDG draft synchronization. Never modifies a non-draft
     * event or creates WooCommerce/Tickera objects.
     */
    public static function sync_draft( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! current_user_can( 'mmc_manage_events' ) && ! current_user_can( 'mmc_manage_programs' ) ) {
            return new WP_Error( 'mmc_mdg_permission', 'Etkinlik yönetimi yetkisi gerekli.' );
        }
        if ( ! self::legacy_available() || ! class_exists( 'MDG_Sessions' ) || ! class_exists( 'MDG_Venues' ) ) {
            return new WP_Error( 'mmc_mdg_unavailable', 'MDG taslak motoru kullanılamıyor.' );
        }
        $program = MMC_Program_Service::get_program( $program_id );
        $source = MMC_Event_Service::event_for_program( $program_id );
        if ( ! $program || ! $source || ! $program->program_code ) {
            return new WP_Error( 'mmc_mdg_source', 'Program kodu veya MMC etkinliği eksik.' );
        }
        $venues = MMC_Venue_Service::venues_for_program( $program_id );
        $selected = array_values( array_filter( $venues, function( $v ) { return (int) $v->is_selected === 1; } ) );
        // An unapproved single candidate may be prepared as a draft, never sold.
        $choice = count( $selected ) === 1 ? $selected[0] : ( count( $venues ) === 1 ? $venues[0] : null );
        if ( ! $choice || 'mdg' !== $choice->venue_source ) {
            return new WP_Error( 'mmc_mdg_venue', 'Tek ve MDG ana kaydına bağlı bir salon belirleyin.' );
        }
        $venue = MDG_Venues::get( (int) $choice->venue_id );
        if ( ! $venue || ! (int) $venue->is_active ) {
            return new WP_Error( 'mmc_mdg_venue_missing', 'MDG salon kaydı aktif değil.' );
        }
        $source_sessions = MMC_Event_Service::sessions( (int) $source->id );
        $source_tickets = MMC_Event_Service::ticket_types( (int) $source->id );
        $sessions = array();
        foreach ( $source_sessions as $s ) {
            if ( (int) $s->capacity < 1 ) { return new WP_Error( 'mmc_mdg_capacity', 'Seans kapasitesi eksik.' ); }
            try {
                $local = new DateTimeImmutable( (string) $s->session_time, wp_timezone() );
                $utc = $local->setTimezone( new DateTimeZone( 'UTC' ) );
            } catch ( Exception $e ) { return new WP_Error( 'mmc_mdg_date', 'Seans tarihi geçersiz.' ); }
            $duration = max( 15, (int) $venue->default_duration ?: 60 );
            $sessions[] = array(
                'start_at' => $utc->format( 'Y-m-d H:i:s' ),
                'end_at' => $utc->modify( '+' . $duration . ' minutes' )->format( 'Y-m-d H:i:s' ),
                'capacity_total' => (int) $s->capacity,
            );
        }
        if ( ! $sessions ) { return new WP_Error( 'mmc_mdg_sessions', 'En az bir seans gerekli.' ); }
        $tickets = array();
        foreach ( $source_tickets as $t ) {
            if ( ! (int) $t->is_active ) { continue; }
            // V5 builds its 2+2 family offer from child/adult variations.
            // A separate MDG ticket type would create a conflicting product.
            if ( 'family_2_2' === (string) $t->ticket_code ) { continue; }
            $tickets[] = array(
                'code' => strtoupper( sanitize_key( $t->ticket_code ) ),
                'label' => sanitize_text_field( $t->ticket_name ),
                'price' => number_format( (float) $t->price, 2, '.', '' ),
                'capacity_units' => (int) $t->capacity_units,
                'sort_order' => count( $tickets ) * 10 + 10,
            );
        }
        if ( ! $tickets ) { return new WP_Error( 'mmc_mdg_tickets', 'Aktif bilet türü gerekli.' ); }

        $event_table = MDG_DB::table( 'events' );
        $bridge = self::bridge_for_program( $program_id );
        $uuid_hash = md5( 'mmc-mdg:' . (string) $program->program_code );
        $uuid = substr($uuid_hash,0,8).'-'.substr($uuid_hash,8,4).'-5'.substr($uuid_hash,13,3).'-a'.substr($uuid_hash,17,3).'-'.substr($uuid_hash,20,12);
        $existing = $bridge ? self::get_mdg_event( (int) $bridge->mdg_event_id ) : null;
        if ( $bridge && ! $existing ) { return new WP_Error( 'mmc_mdg_broken_link', 'Önce mevcut köprü bağlantısını inceleyin.' ); }
        if ( ! $existing ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$event_table} WHERE public_uuid=%s LIMIT 1", $uuid ) );
            if ( $existing && self::program_for_mdg_event( (int)$existing->id ) &&
                self::program_for_mdg_event( (int)$existing->id ) !== $program_id ) {
                return new WP_Error( 'mmc_mdg_conflict', 'Program kodu başka bir MDG etkinliğine bağlı.' );
            }
        }
        if ( $existing && 'draft' !== $existing->status ) {
            return new WP_Error( 'mmc_mdg_live', 'Satışa açılmış MDG etkinliği otomatik güncellenmez.' );
        }
        if ( $existing ) {
            foreach ( (array) MDG_Sessions::by_event( (int)$existing->id ) as $old_session ) {
                if ( (int)$old_session->sold_units || (int)$old_session->held_units ||
                    (int)$old_session->wc_product_id || (int)$old_session->tickera_event_id ) {
                    return new WP_Error( 'mmc_mdg_products', 'Satış veya ürün bağlantısı olan taslak otomatik güncellenmez.' );
                }
            }
        }
        $now = MDG_DB::now();
        $data = array(
            'title' => sanitize_text_field( $source->event_title ),
            'venue_id' => (int)$venue->id,
            'province_code' => (string)$venue->province_code,
            'province_name' => (string)$venue->province_name,
            'district' => (string)$venue->district,
            'venue_name' => (string)$venue->name,
            'venue_address' => (string)$venue->address,
            'venue_latitude' => $venue->latitude,
            'venue_longitude' => $venue->longitude,
            'venue_maps_url' => (string)$venue->maps_url,
            'venue_qr_attachment_id' => $venue->location_qr_attachment_id ?: null,
            'venue_default_capacity' => (int)$venue->default_capacity,
            'venue_default_duration' => (int)$venue->default_duration,
            'show_duration' => max(15,(int)$venue->default_duration ?: 60),
            'doors_open_before' => (int)$source->door_open_minutes,
            'seating_type' => 'numbered' === $source->seating_mode ? 'numbered' : 'free',
            'age_info' => '0–2 yaş ücretsiz; 3–12 çocuk, 13 yaş ve üzeri yetişkin bileti.',
            'status' => 'draft',
            'updated_at' => $now,
        );
        $wpdb->query( 'START TRANSACTION' );
        try {
            if ( $existing ) {
                $id = (int)$existing->id;
                if ( false === $wpdb->update( $event_table, $data, array('id'=>$id) ) ) { throw new Exception('MDG taslağı güncellenemedi.'); }
            } else {
                $data['public_uuid'] = $uuid;
                $data['created_by'] = get_current_user_id();
                $data['created_at'] = $now;
                if ( false === $wpdb->insert( $event_table, $data ) ) { throw new Exception('MDG taslağı oluşturulamadı.'); }
                $id = (int)$wpdb->insert_id;
            }
            $result = MDG_Sessions::replace_draft_structure( $id, $sessions, $tickets );
            if ( is_wp_error($result) ) { throw new Exception($result->get_error_message()); }
            $link = self::link( $program_id, $id, 'program_code', 100 );
            if ( is_wp_error($link) ) { throw new Exception($link->get_error_message()); }
            if ( false === $wpdb->query('COMMIT') ) { throw new Exception('Veritabanı işlemi tamamlanamadı.'); }
        } catch ( Throwable $e ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error( 'mmc_mdg_sync', $e->getMessage() );
        }
        return $id;
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
            'stale' => false,
            'bridge' => null,
            'event' => null,
            'candidates' => array(),
            'identity_expected' => 0,
            'identity_matched' => 0,
            'sessions_mmc' => 0,
            'sessions_mdg' => 0,
            'mmc_event_date' => '',
            'mdg_event_dates' => array(),
            'date_match' => false,
            'mmc_venue_name' => '',
            'mdg_venue_name' => '',
            'venue_match' => false,
            'province_match' => false,
            'district_match' => false,
            'session_times_mmc' => array(),
            'session_times_mdg' => array(),
            'session_time_match' => false,
            'missing_session_times_in_mdg' => array(),
            'extra_session_times_in_mdg' => array(),
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
            $result['stale'] = true;
            $result['candidates'] = self::candidates( $program_id );
            return $result;
        }

        $result['linked'] = true;
        $result['bridge'] = $bridge;
        $result['event'] = $event;

        $mmc_event = class_exists('MMC_Event_Service') ? MMC_Event_Service::event_for_program( $program_id ) : null;
        $program = MMC_Program_Service::get_program( $program_id );
        $identity = self::mmc_identity( $program_id );
        $result['identity_expected'] = count($identity['rows']);

        $mmc_sessions = $mmc_event && class_exists('MMC_Event_Service') ? (array)MMC_Event_Service::sessions((int)$mmc_event->id) : array();
        $result['sessions_mmc'] = count($mmc_sessions);
        $result['mmc_event_date'] = $mmc_event && ! empty($mmc_event->event_date) ? (string)$mmc_event->event_date : '';
        $result['mmc_venue_name'] = self::selected_venue_name( $program_id );
        $result['mdg_venue_name'] = (string)($event->venue_name ?? '');
        $result['venue_match'] = $result['mmc_venue_name'] !== '' && self::same_text( $result['mmc_venue_name'], $result['mdg_venue_name'] );
        $result['province_match'] = $program ? self::same_text( $program->province_name, $event->province_name ?? '' ) : false;
        $result['district_match'] = $program ? ( ! $program->district_name || self::same_text( $program->district_name, $event->district ?? '' ) ) : false;

        foreach ( $mmc_sessions as $session ) {
            $local = substr( (string)$session->session_time, 0, 16 );
            if ( $local ) { $result['session_times_mmc'][] = $local; }
        }
        $result['session_times_mmc'] = array_values(array_unique($result['session_times_mmc']));
        sort($result['session_times_mmc']);

        global $wpdb;
        $sessions_table = MDG_DB::table('sessions');
        $types_table = MDG_DB::table('ticket_types');
        $legacy_rows = (array)$wpdb->get_results( $wpdb->prepare(
            "SELECT s.id mdg_session_id,s.start_at,s.wc_product_id,s.tickera_event_id,t.wc_variation_id
             FROM {$sessions_table} s
             LEFT JOIN {$types_table} t ON t.session_id=s.id AND t.is_active=1
             WHERE s.event_id=%d",
            (int)$event->id
        ) );
        $result['sessions_mdg'] = count( array_unique(array_map(function($r){ return (int)$r->mdg_session_id; }, $legacy_rows)) );

        foreach ( $legacy_rows as $legacy ) {
            $local = self::legacy_local_datetime( $legacy->start_at );
            if ( $local ) {
                $result['session_times_mdg'][] = $local;
                $date = substr($local, 0, 10);
                if ( $date ) { $result['mdg_event_dates'][$date] = true; }
            }
        }
        $result['session_times_mdg'] = array_values(array_unique($result['session_times_mdg']));
        sort($result['session_times_mdg']);
        $result['mdg_event_dates'] = array_keys($result['mdg_event_dates']);
        sort($result['mdg_event_dates']);
        $result['date_match'] = $result['mmc_event_date'] !== '' && in_array($result['mmc_event_date'], $result['mdg_event_dates'], true);
        $result['missing_session_times_in_mdg'] = array_values(array_diff($result['session_times_mmc'], $result['session_times_mdg']));
        $result['extra_session_times_in_mdg'] = array_values(array_diff($result['session_times_mdg'], $result['session_times_mmc']));
        $result['session_time_match'] = !$result['missing_session_times_in_mdg'] && !$result['extra_session_times_in_mdg']
            && count($result['session_times_mmc']) === count($result['session_times_mdg']);

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
        $mdg_tickets = 0;
        $mmc_tickets = 0;
        $mdg_units = 0;
        $mmc_units = 0;
        $mdg_revenue_ex_tax = 0.0;
        $mmc_revenue = 0.0;

        foreach ( $mdg_rows as $row ) {
            $mdg_items[(int)$row->order_item_id] = true;
            $mdg_orders[(int)$row->order_id] = true;
            $mdg_tickets += (int)$row->quantity;
            $mdg_units += (int)$row->units_total;
            $mdg_revenue_ex_tax += (float)$row->line_total;
        }
        foreach ( $mmc_rows as $row ) {
            $mmc_items[(int)$row->order_item_id] = true;
            $mmc_orders[(int)$row->order_id] = true;
            $mmc_tickets += (int)$row->quantity;
            $mmc_units += (int)$row->units_total;
            $mmc_revenue += (float)$row->net_amount;
        }

        $missing_in_mmc = array_values(array_diff(array_keys($mdg_items),array_keys($mmc_items)));
        $extra_in_mmc = array_values(array_diff(array_keys($mmc_items),array_keys($mdg_items)));
        $has_sales = count($mdg_items) + count($mmc_items) > 0;
        $ok = !$missing_in_mmc
            && !$extra_in_mmc
            && count($mdg_orders) === count($mmc_orders)
            && $mdg_tickets === $mmc_tickets
            && $mdg_units === $mmc_units;

        return array(
            'has_sales'=>$has_sales,
            'ok'=>$ok,
            'mdg_orders'=>count($mdg_orders),
            'mmc_orders'=>count($mmc_orders),
            'mdg_items'=>count($mdg_items),
            'mmc_items'=>count($mmc_items),
            'mdg_tickets'=>$mdg_tickets,
            'mmc_tickets'=>$mmc_tickets,
            'mdg_units'=>$mdg_units,
            'mmc_units'=>$mmc_units,
            'missing_in_mmc'=>$missing_in_mmc,
            'extra_in_mmc'=>$extra_in_mmc,
            'mdg_revenue_ex_tax'=>round($mdg_revenue_ex_tax,2),
            'mmc_revenue'=>round($mmc_revenue,2),
            'revenue_diff'=>round($mmc_revenue-$mdg_revenue_ex_tax,2),
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
        $local = self::legacy_local_datetime( $utc_datetime );
        return $local ? substr( $local, 0, 10 ) : '';
    }

    private static function legacy_local_datetime( $utc_datetime ) {
        if ( class_exists('MDG_Sessions') && method_exists('MDG_Sessions','local_parts') ) {
            $parts = MDG_Sessions::local_parts( $utc_datetime );
            if ( ! empty($parts[0]) && ! empty($parts[1]) ) {
                return (string)$parts[0] . ' ' . substr((string)$parts[1], 0, 5);
            }
        }
        if ( function_exists('get_date_from_gmt') && $utc_datetime ) {
            return (string)get_date_from_gmt( (string)$utc_datetime, 'Y-m-d H:i' );
        }
        return $utc_datetime ? substr( (string)$utc_datetime, 0, 16 ) : '';
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
