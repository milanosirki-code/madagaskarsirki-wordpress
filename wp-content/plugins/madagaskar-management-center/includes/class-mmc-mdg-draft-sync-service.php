<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC programını güvenli biçimde MDG TASLAK yapısına senkronize eder.
 * Canlı WooCommerce/Tickera/PayTR nesnesi oluşturmaz veya değiştirmez.
 * Aile Paketi 2+2, gerçek Çocuk + Yetişkin biletlerinden sanal paket olarak üretilir;
 * MDG ticket_types içinde capacity_units=4 olan üçüncü gerçek aile bileti oluşturulmaz.
 */
final class MMC_MDG_Draft_Sync_Service {
    public static function sync( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );

        if ( ! current_user_can( 'mmc_manage_events' ) && ! current_user_can( 'mmc_manage_programs' ) ) {
            return new WP_Error( 'mmc_mdg_permission', 'Etkinlik yönetimi yetkisi gerekli.' );
        }
        if ( ! class_exists( 'MMC_MDG_Bridge_Service' )
            || ! MMC_MDG_Bridge_Service::legacy_available()
            || ! class_exists( 'MDG_Sessions' )
            || ! class_exists( 'MDG_Venues' ) ) {
            return new WP_Error( 'mmc_mdg_unavailable', 'MDG taslak motoru kullanılamıyor.' );
        }

        $program = MMC_Program_Service::get_program( $program_id );
        $source  = MMC_Event_Service::event_for_program( $program_id );
        if ( ! $program || ! $source || ! $program->program_code ) {
            return new WP_Error( 'mmc_mdg_source', 'Program kodu veya MMC etkinliği eksik.' );
        }

        $venues   = MMC_Venue_Service::venues_for_program( $program_id );
        $selected = array_values( array_filter( $venues, function( $v ) { return (int) $v->is_selected === 1; } ) );
        $choice = count( $selected ) === 1 ? $selected[0] : ( count( $venues ) === 1 ? $venues[0] : null );
        if ( ! $choice || 'mdg' !== (string) $choice->venue_source ) {
            return new WP_Error( 'mmc_mdg_venue', 'Tek ve MDG ana kaydına bağlı bir salon belirleyin.' );
        }

        $venue = MDG_Venues::get( (int) $choice->venue_id );
        if ( ! $venue || ! (int) $venue->is_active ) {
            return new WP_Error( 'mmc_mdg_venue_missing', 'MDG salon kaydı aktif değil.' );
        }

        $source_sessions = MMC_Event_Service::sessions( (int) $source->id );
        $source_tickets  = MMC_Event_Service::ticket_types( (int) $source->id );
        $sessions = array();
        foreach ( $source_sessions as $s ) {
            if ( (int) $s->capacity < 1 ) {
                return new WP_Error( 'mmc_mdg_capacity', 'Seans kapasitesi eksik.' );
            }
            try {
                $local = new DateTimeImmutable( (string) $s->session_time, wp_timezone() );
                $utc   = $local->setTimezone( new DateTimeZone( 'UTC' ) );
            } catch ( Exception $e ) {
                return new WP_Error( 'mmc_mdg_date', 'Seans tarihi geçersiz.' );
            }
            $duration = max( 15, (int) $venue->default_duration ?: 60 );
            $sessions[] = array(
                'start_at'       => $utc->format( 'Y-m-d H:i:s' ),
                'end_at'         => $utc->modify( '+' . $duration . ' minutes' )->format( 'Y-m-d H:i:s' ),
                'capacity_total' => (int) $s->capacity,
            );
        }
        if ( ! $sessions ) {
            return new WP_Error( 'mmc_mdg_sessions', 'En az bir seans gerekli.' );
        }

        $tickets = array();
        foreach ( $source_tickets as $t ) {
            if ( ! (int) $t->is_active ) { continue; }
            if ( 'family_2_2' === (string) $t->ticket_code ) { continue; }
            $tickets[] = array(
                'code'           => strtoupper( sanitize_key( $t->ticket_code ) ),
                'label'          => sanitize_text_field( $t->ticket_name ),
                'price'          => number_format( (float) $t->price, 2, '.', '' ),
                'capacity_units' => max( 1, (int) $t->capacity_units ),
                'sort_order'     => count( $tickets ) * 10 + 10,
            );
        }
        if ( ! $tickets ) {
            return new WP_Error( 'mmc_mdg_tickets', 'Aktif Çocuk/Yetişkin bilet türü gerekli.' );
        }

        $event_table = MDG_DB::table( 'events' );
        $bridge      = MMC_MDG_Bridge_Service::bridge_for_program( $program_id );
        $uuid        = self::program_uuid( (string) $program->program_code );
        $existing    = $bridge ? MMC_MDG_Bridge_Service::get_mdg_event( (int) $bridge->mdg_event_id ) : null;

        if ( $bridge && ! $existing ) {
            return new WP_Error( 'mmc_mdg_broken_link', 'Önce mevcut köprü bağlantısını inceleyin.' );
        }
        if ( ! $existing ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$event_table} WHERE public_uuid=%s LIMIT 1", $uuid ) );
            $linked_program = $existing ? MMC_MDG_Bridge_Service::program_for_mdg_event( (int) $existing->id ) : 0;
            if ( $existing && $linked_program && $linked_program !== $program_id ) {
                return new WP_Error( 'mmc_mdg_conflict', 'Program kodu başka bir MDG etkinliğine bağlı.' );
            }
        }
        if ( $existing && 'draft' !== (string) $existing->status ) {
            return new WP_Error( 'mmc_mdg_live', 'Satışa açılmış MDG etkinliği otomatik güncellenmez.' );
        }
        if ( $existing ) {
            foreach ( (array) MDG_Sessions::by_event( (int) $existing->id ) as $old_session ) {
                if ( (int) $old_session->sold_units || (int) $old_session->held_units
                    || (int) $old_session->wc_product_id || (int) $old_session->tickera_event_id ) {
                    return new WP_Error( 'mmc_mdg_products', 'Satış veya ürün bağlantısı olan taslak otomatik güncellenmez.' );
                }
            }
        }

        $now  = MDG_DB::now();
        $data = array(
            'title'                    => self::public_event_title( $program ),
            'venue_id'                 => (int) $venue->id,
            'province_code'            => (string) $venue->province_code,
            'province_name'            => (string) $venue->province_name,
            'district'                 => (string) $venue->district,
            'venue_name'               => (string) $venue->name,
            'venue_address'            => (string) $venue->address,
            'venue_latitude'           => $venue->latitude,
            'venue_longitude'          => $venue->longitude,
            'venue_maps_url'           => (string) $venue->maps_url,
            'venue_qr_attachment_id'   => $venue->location_qr_attachment_id ?: null,
            'venue_default_capacity'   => (int) $venue->default_capacity,
            'venue_default_duration'   => (int) $venue->default_duration,
            'show_duration'            => max( 15, (int) $venue->default_duration ?: 60 ),
            'doors_open_before'        => (int) $source->door_open_minutes,
            'seating_type'             => 'numbered' === (string) $source->seating_mode ? 'numbered' : 'free',
            'age_info'                 => '0–2 yaş ücretsiz; 3–12 çocuk, 13 yaş ve üzeri yetişkin bileti.',
            'status'                   => 'draft',
            'updated_at'               => $now,
        );

        $wpdb->query( 'START TRANSACTION' );
        try {
            if ( $existing ) {
                $id = (int) $existing->id;
                if ( false === $wpdb->update( $event_table, $data, array( 'id' => $id ) ) ) {
                    throw new Exception( 'MDG taslağı güncellenemedi.' );
                }
            } else {
                $data['public_uuid'] = $uuid;
                $data['created_by']  = get_current_user_id();
                $data['created_at']  = $now;
                if ( false === $wpdb->insert( $event_table, $data ) ) {
                    throw new Exception( 'MDG taslağı oluşturulamadı.' );
                }
                $id = (int) $wpdb->insert_id;
            }

            $structure = MDG_Sessions::replace_draft_structure( $id, $sessions, $tickets );
            if ( is_wp_error( $structure ) ) {
                throw new Exception( $structure->get_error_message() );
            }

            $link = MMC_MDG_Bridge_Service::link( $program_id, $id, 'program_code', 100 );
            if ( is_wp_error( $link ) ) {
                throw new Exception( $link->get_error_message() );
            }

            if ( false === $wpdb->query( 'COMMIT' ) ) {
                throw new Exception( 'Veritabanı işlemi tamamlanamadı.' );
            }
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'mmc_mdg_sync', $e->getMessage() );
        }

        return $id;
    }

    private static function program_uuid( $program_code ) {
        $hash = md5( 'mmc-mdg:' . (string) $program_code );
        return substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-5' . substr( $hash, 13, 3 ) . '-a' . substr( $hash, 17, 3 ) . '-' . substr( $hash, 20, 12 );
    }

    private static function public_event_title( $program ) {
        if ( ! $program ) { return 'Madagaskar Sirki'; }
        $title = 'Madagaskar Sirki — ' . (string) $program->province_name;
        $district = trim( (string) $program->district_name );
        if ( $district && 'merkez' !== self::normalize_text( $district ) ) {
            $title .= ' / ' . $district;
        }
        return $title;
    }

    private static function normalize_text( $value ) {
        $value = remove_accents( strtolower( trim( (string) $value ) ) );
        return preg_replace( '/[^a-z0-9]+/', '', $value );
    }
}
