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

    /**
     * MDG Etkinlik Yayınla ekranı için MMC program özetini hazırlar.
     * Bu metot veri yazmaz; yalnız köprü oluşturma öncesi doğrulama yapar.
     */
    public static function publish_preview( $program_id ) {
        $program_id = absint( $program_id );
        $program = class_exists( 'MMC_Program_Service' ) ? MMC_Program_Service::get_program( $program_id ) : null;
        $event = $program && class_exists( 'MMC_Event_Service' ) ? MMC_Event_Service::event_for_program( $program_id ) : null;
        $sessions = $event && class_exists( 'MMC_Event_Service' ) ? (array) MMC_Event_Service::sessions( (int) $event->id ) : array();
        $tickets = $event && class_exists( 'MMC_Event_Service' ) ? (array) MMC_Event_Service::ticket_types( (int) $event->id ) : array();
        $active_tickets = array_values( array_filter( $tickets, function( $ticket ) {
            return (int) ( $ticket->is_active ?? 0 ) === 1;
        } ) );

        $venue = null;
        if ( $event && ! empty( $event->program_venue_id ) && class_exists( 'MMC_Venue_Service' ) ) {
            $venue = MMC_Venue_Service::get_program_venue( (int) $event->program_venue_id );
        }

        $mdg_venue = $venue ? self::resolve_mdg_venue( $venue ) : null;
        $bridge = self::bridge_for_program( $program_id );
        $errors = array();

        if ( ! $program ) { $errors[] = 'MMC program kaydı bulunamadı.'; }
        if ( $program && 'cancelled' === (string) $program->status ) { $errors[] = 'İptal edilmiş program MDG taslağına aktarılamaz.'; }
        if ( ! $event ) { $errors[] = 'MMC etkinlik kaydı bulunamadı.'; }
        if ( $event && empty( $event->event_date ) ) { $errors[] = 'Etkinlik tarihi eksik.'; }
        if ( ! $venue ) { $errors[] = 'Kesin salon bağlantısı eksik.'; }
        if ( $venue && ! $mdg_venue ) { $errors[] = 'Kesin salon MDG Salonlar ana kaydında eşleştirilemedi.'; }
        if ( ! $sessions ) { $errors[] = 'En az bir MMC seansı gerekli.'; }
        foreach ( $sessions as $session ) {
            if ( empty( $session->session_time ) || (int) $session->capacity < 1 ) {
                $errors[] = 'Seans tarihi/saat veya kapasite eksik.';
                break;
            }
        }
        if ( ! $active_tickets ) { $errors[] = 'En az bir aktif MMC bilet türü gerekli.'; }
        if ( ! self::legacy_available() || ! class_exists( 'MDG_Venues' ) || ! class_exists( 'MDG_Sessions' ) ) {
            $errors[] = 'Madagaskar Bilet Yönetimi motoru aktif/erişilebilir değil.';
        }

        return array(
            'program'        => $program,
            'event'          => $event,
            'venue'          => $venue,
            'mdg_venue'      => $mdg_venue,
            'sessions'       => $sessions,
            'tickets'        => $active_tickets,
            'bridge'         => $bridge,
            'ready'          => empty( $errors ),
            'errors'         => array_values( array_unique( $errors ) ),
        );
    }

    /**
     * Etkinlik Yayınla ekranında gösterilecek MMC programlarını döndürür.
     * İptal programları liste dışıdır; eksikleri olan programlar UI'da uyarı ile gösterilebilir.
     */
    public static function publish_programs() {
        if ( ! class_exists( 'MMC_Program_Service' ) ) { return array(); }
        $rows = array();
        foreach ( (array) MMC_Program_Service::all_programs() as $program ) {
            if ( 'cancelled' === (string) $program->status ) { continue; }
            $preview = self::publish_preview( (int) $program->id );
            if ( empty( $preview['event'] ) ) { continue; }
            $rows[] = $preview;
        }
        usort( $rows, function( $a, $b ) {
            $ad = ! empty( $a['event']->event_date ) ? (string) $a['event']->event_date : '9999-12-31';
            $bd = ! empty( $b['event']->event_date ) ? (string) $b['event']->event_date : '9999-12-31';
            if ( $ad === $bd ) { return (int) $a['program']->id <=> (int) $b['program']->id; }
            return strcmp( $ad, $bd );
        } );
        return $rows;
    }

    /**
     * MMC programından güvenli MDG taslağı oluşturur.
     * Canlı WooCommerce/Tickera nesnesi oluşturmaz; yalnız MDG draft + seans + bilet katalog yapısını hazırlar.
     */
    public static function create_draft_from_program( $program_id ) {
        $program_id = absint( $program_id );
        $preview = self::publish_preview( $program_id );
        if ( ! empty( $preview['bridge'] ) ) {
            $existing = self::get_mdg_event( (int) $preview['bridge']->mdg_event_id );
            if ( $existing ) { return (int) $existing->id; }
        }
        if ( empty( $preview['ready'] ) ) {
            return new WP_Error( 'mmc_mdg_publish_not_ready', implode( ' ', (array) $preview['errors'] ) );
        }

        // Aynı yapıya sahip mevcut MDG etkinliği varsa ikinci kayıt üretme.
        $exact = array_values( array_filter( self::candidates( $program_id ), function( $row ) use ( $preview ) {
            return ! empty( $row['province_match'] )
                && ! empty( $row['district_match'] )
                && ! empty( $row['date_match'] )
                && ! empty( $row['venue_match'] )
                && (int) $row['session_count'] === count( $preview['sessions'] );
        } ) );
        if ( 1 === count( $exact ) ) {
            $linked = self::link( $program_id, (int) $exact[0]['event_id'], 'structure', 90 );
            return is_wp_error( $linked ) ? $linked : (int) $exact[0]['event_id'];
        }
        if ( count( $exact ) > 1 ) {
            return new WP_Error( 'mmc_mdg_publish_ambiguous', 'Aynı tarih/salon yapısında birden fazla MDG etkinliği bulundu; yeni taslak oluşturulmadı.' );
        }

        global $wpdb;
        $program = $preview['program'];
        $event = $preview['event'];
        $venue = $preview['mdg_venue'];
        $duration = max( 15, min( 360, (int) ( $venue->default_duration ?? 60 ) ) );
        $now = class_exists( 'MDG_DB' ) && method_exists( 'MDG_DB', 'now' ) ? MDG_DB::now() : current_time( 'mysql' );
        $import_code = substr( 'MMC-' . (string) $program->program_code, 0, 80 );

        $events_table = MDG_DB::table( 'events' );
        $existing_import = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$events_table} WHERE import_code=%s LIMIT 1",
            $import_code
        ) );
        if ( $existing_import ) {
            $linked = self::link( $program_id, (int) $existing_import->id, 'import_code', 100 );
            return is_wp_error( $linked ) ? $linked : (int) $existing_import->id;
        }

        $session_rows = array();
        foreach ( $preview['sessions'] as $session ) {
            $local_start = substr( (string) $session->session_time, 0, 19 );
            $start_utc = function_exists( 'get_gmt_from_date' )
                ? get_gmt_from_date( $local_start, 'Y-m-d H:i:s' )
                : $local_start;
            try {
                $start_dt = new DateTimeImmutable( $start_utc, new DateTimeZone( 'UTC' ) );
                $end_utc = $start_dt->modify( '+' . $duration . ' minutes' )->format( 'Y-m-d H:i:s' );
            } catch ( Exception $e ) {
                return new WP_Error( 'mmc_mdg_session_time', 'Seans tarihi MDG biçimine dönüştürülemedi.' );
            }
            $session_rows[] = array(
                'start_at'       => $start_utc,
                'end_at'         => $end_utc,
                'capacity_total' => max( 1, (int) $session->capacity ),
            );
        }

        $ticket_rows = array();
        foreach ( $preview['tickets'] as $ticket ) {
            $code = self::mdg_ticket_code( (string) $ticket->ticket_code );
            $label = (string) $ticket->ticket_name;
            if ( 'COCUK' === $code && isset( $ticket->age_min, $ticket->age_max ) ) {
                $label = 'Çocuk ' . (int) $ticket->age_min . '–' . (int) $ticket->age_max . ' Yaş';
            } elseif ( 'YETISKIN' === $code && isset( $ticket->age_min ) ) {
                $label = 'Yetişkin ' . (int) $ticket->age_min . ' Yaş ve üzeri';
            }
            $ticket_rows[] = array(
                'code'           => $code,
                'label'          => $label,
                'price'          => number_format( (float) $ticket->price, 2, '.', '' ),
                'capacity_units' => max( 1, (int) $ticket->capacity_units ),
                'sort_order'     => (int) ( $ticket->sort_order ?? 10 ),
            );
        }

        $short = 'Uluslararası sanatçılarla hazırlanan, tamamen hayvansız, ailelere uygun canlı sirk deneyimi.';
        $long  = ! empty( $event->notes ) ? (string) $event->notes : $short;
        $data = array(
            'public_uuid'                   => wp_generate_uuid4(),
            'import_code'                   => $import_code,
            'title'                         => (string) $event->event_title,
            'venue_id'                      => (int) $venue->id,
            'province_code'                 => (string) $venue->province_code,
            'province_name'                 => (string) $venue->province_name,
            'district'                      => (string) $venue->district,
            'venue_name'                    => (string) $venue->name,
            'venue_address'                 => (string) $venue->address,
            'venue_latitude'                => null !== $venue->latitude ? $venue->latitude : null,
            'venue_longitude'               => null !== $venue->longitude ? $venue->longitude : null,
            'venue_maps_url'                => (string) $venue->maps_url,
            'venue_qr_attachment_id'        => ! empty( $venue->location_qr_attachment_id ) ? (int) $venue->location_qr_attachment_id : null,
            'venue_default_capacity'        => (int) $venue->default_capacity,
            'venue_default_duration'        => $duration,
            'short_description'             => $short,
            'long_description'              => $long,
            'hero_attachment_id'            => null,
            'gallery_attachment_ids'        => wp_json_encode( array() ),
            'video_url'                     => null,
            'age_info'                      => '0–2 yaş ücretsiz; 3–12 yaş çocuk; 13 yaş ve üzeri yetişkin.',
            'show_duration'                 => $duration,
            'doors_open_before'             => max( 0, min( 180, (int) $event->door_open_minutes ) ),
            'seating_type'                  => in_array( (string) $event->seating_mode, array( 'free', 'numbered' ), true ) ? (string) $event->seating_mode : 'free',
            'rules'                         => '',
            'organizer_name'                => 'Dünya Organizasyon Medya Turizm Eğitim Danışmanlık Reklam Seyahat Acenteliği Ltd. Şti.',
            'faq_json'                      => wp_json_encode( array() ),
            'seo_title'                     => (string) $event->event_title,
            'seo_description'               => $short,
            'status'                        => 'draft',
            'created_by'                    => get_current_user_id() ?: null,
            'created_at'                    => $now,
            'updated_at'                    => $now,
        );

        $wpdb->query( 'START TRANSACTION' );
        try {
            if ( false === $wpdb->insert( $events_table, $data ) ) {
                throw new Exception( 'MDG etkinlik taslağı oluşturulamadı.' );
            }
            $mdg_event_id = (int) $wpdb->insert_id;

            $structure = MDG_Sessions::replace_draft_structure( $mdg_event_id, $session_rows, $ticket_rows );
            if ( is_wp_error( $structure ) ) {
                throw new Exception( $structure->get_error_message() );
            }

            $linked = self::link( $program_id, $mdg_event_id, 'mmc_publish', 100 );
            if ( is_wp_error( $linked ) ) {
                throw new Exception( $linked->get_error_message() );
            }

            $audit = MDG_DB::table( 'audit_log' );
            if ( self::table_exists( $audit ) ) {
                $wpdb->insert( $audit, array(
                    'user_id'     => get_current_user_id(),
                    'action_key'  => 'event.draft.created_from_mmc',
                    'object_type' => 'event',
                    'object_id'   => $mdg_event_id,
                    'context'     => wp_json_encode( array(
                        'program_id'   => $program_id,
                        'program_code' => (string) $program->program_code,
                        'session_count'=> count( $session_rows ),
                        'ticket_count' => count( $ticket_rows ),
                    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                    'created_at'  => $now,
                ) );
            }

            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'mmc_mdg_publish_create', $e->getMessage() ?: 'MDG taslağı oluşturulurken hata oluştu.' );
        }

        MMC_Program_Service::add_log(
            $program_id,
            'mdg_draft_created_from_program',
            'mdg_event',
            $mdg_event_id,
            null,
            array(
                'program_code' => (string) $program->program_code,
                'venue'        => (string) $venue->name,
                'sessions'     => count( $session_rows ),
                'tickets'      => count( $ticket_rows ),
            ),
            'MMC programı Etkinlik Yayınla için MDG taslağına aktarıldı.'
        );

        return $mdg_event_id;
    }

    private static function resolve_mdg_venue( $program_venue ) {
        if ( ! $program_venue || ! class_exists( 'MDG_Venues' ) ) { return null; }

        if ( 'mdg' === (string) ( $program_venue->venue_source ?? '' ) ) {
            $venue = MDG_Venues::get( (int) $program_venue->venue_id );
            if ( $venue && (int) $venue->is_active === 1 ) { return $venue; }
        }

        foreach ( (array) MDG_Venues::all( true ) as $candidate ) {
            if ( self::same_text( $candidate->province_name ?? '', $program_venue->province_name ?? '' )
                && self::same_text( $candidate->district ?? '', $program_venue->district_name ?? '' )
                && self::same_text( $candidate->name ?? '', $program_venue->venue_name ?? '' ) ) {
                return $candidate;
            }
        }
        return null;
    }

    private static function mdg_ticket_code( $code ) {
        $code = sanitize_key( $code );
        $map = array(
            'child'       => 'COCUK',
            'adult'       => 'YETISKIN',
            'family_2_2'  => 'AILE_2_2',
        );
        if ( isset( $map[ $code ] ) ) { return $map[ $code ]; }
        $raw = strtoupper( remove_accents( $code ) );
        $raw = preg_replace( '/[^A-Z0-9_]+/', '_', $raw );
        return substr( trim( $raw, '_' ) ?: 'BILET', 0, 40 );
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
        if ( class_exists( 'MMC_Event_Service' ) && class_exists( 'MMC_Venue_Service' ) ) {
            $event = MMC_Event_Service::event_for_program( absint( $program_id ) );
            if ( $event && ! empty( $event->program_venue_id ) ) {
                $venue = MMC_Venue_Service::get_program_venue( (int) $event->program_venue_id );
                if ( $venue && ! empty( $venue->venue_name ) ) {
                    return (string) $venue->venue_name;
                }
            }
        }

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
