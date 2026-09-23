<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Event_Service {
    public static function event_statuses() {
        return array(
            'draft'       => 'Taslak',
            'configured'  => 'Yapılandırıldı',
            'sales_ready' => 'Satışa Hazır',
            'sales_open'  => 'Satışta',
            'closed'      => 'Kapandı',
            'cancelled'   => 'İptal',
        );
    }

    public static function integration_channels() {
        return array(
            'woocommerce' => 'WooCommerce',
            'tickera'     => 'Tickera',
            'paytr'       => 'PayTR',
            'biletinial'  => 'Biletinial',
        );
    }

    public static function ensure_event_for_program( $program_id, $program_venue_id = 0 ) {
        global $wpdb;
        $events = $wpdb->prefix . 'mmc_events';
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $events WHERE program_id=%d ORDER BY id ASC LIMIT 1", absint( $program_id ) ) );
        if ( $existing ) {
            if ( $program_venue_id && (int) $existing->program_venue_id !== (int) $program_venue_id ) {
                $wpdb->update(
                    $events,
                    array( 'program_venue_id' => absint( $program_venue_id ), 'updated_at' => current_time( 'mysql' ) ),
                    array( 'id' => (int) $existing->id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
            }
            self::ensure_integration_rows( $program_id, $existing->id );
            return (int) $existing->id;
        }

        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) {
            return new WP_Error( 'mmc_event_program_missing', 'Program bulunamadı.' );
        }

        $venue = null;
        if ( $program_venue_id && class_exists( 'MMC_Venue_Service' ) ) {
            $venue = MMC_Venue_Service::get_program_venue( $program_venue_id );
        }
        if ( ! $venue && class_exists( 'MMC_Venue_Service' ) ) {
            $rows = MMC_Venue_Service::venues_for_program( $program_id );
            foreach ( $rows as $row ) {
                if ( (int) $row->is_selected === 1 ) {
                    $venue = $row;
                    $program_venue_id = (int) $row->id;
                    break;
                }
            }
        }

        $date = $venue && ! empty( $venue->requested_date ) ? $venue->requested_date : $program->planned_date;
        $event_code = self::generate_event_code( $program, $date );
        $now = current_time( 'mysql' );
        $title = 'Madagaskar Sirki — ' . ( $program->district_name ?: $program->province_name );

        $ok = $wpdb->insert(
            $events,
            array(
                'program_id'         => absint( $program_id ),
                'program_venue_id'   => $program_venue_id ? absint( $program_venue_id ) : null,
                'event_code'         => $event_code,
                'event_title'        => $title,
                'event_date'         => $date ?: null,
                'seating_mode'       => 'free',
                'door_open_minutes'  => 30,
                'status'             => 'draft',
                'created_by'         => get_current_user_id() ?: null,
                'created_at'         => $now,
                'updated_at'         => $now,
            )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mmc_event_insert_failed', 'Etkinlik kaydı oluşturulamadı.' );
        }

        $event_id = (int) $wpdb->insert_id;
        self::seed_default_ticket_types( $event_id );
        self::ensure_integration_rows( $program_id, $event_id );
        MMC_Program_Service::add_log( $program_id, 'event_draft_created', 'event', $event_id, null, array( 'event_code' => $event_code ), 'Salon kesinleşmesi sonrası etkinlik taslağı oluşturuldu.' );
        return $event_id;
    }

    public static function get_event( $event_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_events WHERE id=%d LIMIT 1", absint( $event_id ) ) );
    }

    public static function event_for_program( $program_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_events WHERE program_id=%d ORDER BY id ASC LIMIT 1", absint( $program_id ) ) );
    }

    public static function save_event( $event_id, $data ) {
        global $wpdb;
        $event = self::get_event( $event_id );
        if ( ! $event ) return new WP_Error( 'mmc_event_missing', 'Etkinlik bulunamadı.' );

        $date = self::date_or_null( $data['event_date'] ?? '' );
        $title = sanitize_text_field( $data['event_title'] ?? $event->event_title );
        $seating = sanitize_key( $data['seating_mode'] ?? 'free' );
        if ( ! in_array( $seating, array( 'free', 'numbered' ), true ) ) $seating = 'free';
        $door = max( 0, min( 180, absint( $data['door_open_minutes'] ?? 30 ) ) );
        $notes = sanitize_textarea_field( $data['notes'] ?? '' );

        $ok = $wpdb->update(
            $wpdb->prefix . 'mmc_events',
            array(
                'event_title' => $title,
                'event_date' => $date,
                'seating_mode' => $seating,
                'door_open_minutes' => $door,
                'notes' => $notes,
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => absint( $event_id ) )
        );
        if ( false === $ok ) return new WP_Error( 'mmc_event_update_failed', 'Etkinlik güncellenemedi.' );
        MMC_Program_Service::add_log( $event->program_id, 'event_updated', 'event', $event_id, null, null, 'Etkinlik temel bilgileri güncellendi.' );
        self::refresh_configured_status( $event_id );
        return true;
    }

    public static function sessions( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_sessions WHERE event_id=%d ORDER BY session_time ASC", absint( $event_id ) ) );
    }

    public static function add_session( $event_id, $session_time, $capacity, $notes = '' ) {
        global $wpdb;
        $event = self::get_event( $event_id );
        if ( ! $event ) return new WP_Error( 'mmc_session_event_missing', 'Etkinlik bulunamadı.' );
        $dt = self::datetime_or_null( $session_time );
        $capacity = absint( $capacity );
        if ( ! $dt || $capacity < 1 ) return new WP_Error( 'mmc_session_invalid', 'Seans tarihi/saat ve kapasite zorunludur.' );

        $table = $wpdb->prefix . 'mmc_sessions';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE event_id=%d AND session_time=%s LIMIT 1", $event_id, $dt ) );
        if ( $exists ) return new WP_Error( 'mmc_session_exists', 'Bu saat için seans zaten kayıtlı.' );
        $now = current_time( 'mysql' );
        $ok = $wpdb->insert( $table, array(
            'event_id' => $event_id,
            'session_time' => $dt,
            'capacity' => $capacity,
            'status' => 'active',
            'notes' => sanitize_textarea_field( $notes ),
            'created_at' => $now,
            'updated_at' => $now,
        ) );
        if ( false === $ok ) return new WP_Error( 'mmc_session_insert_failed', 'Seans eklenemedi.' );
        MMC_Program_Service::add_log( $event->program_id, 'session_created', 'session', (int) $wpdb->insert_id, null, array( 'session_time' => $dt, 'capacity' => $capacity ), 'Yeni seans oluşturuldu.' );
        self::refresh_configured_status( $event_id );
        return (int) $wpdb->insert_id;
    }

    public static function delete_session( $session_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_sessions';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d LIMIT 1", absint( $session_id ) ) );
        if ( ! $row ) return new WP_Error( 'mmc_session_missing', 'Seans bulunamadı.' );
        $event = self::get_event( $row->event_id );
        $wpdb->delete( $table, array( 'id' => absint( $session_id ) ), array( '%d' ) );
        if ( $event ) MMC_Program_Service::add_log( $event->program_id, 'session_deleted', 'session', $session_id, $row, null, 'Seans silindi.' );
        self::refresh_configured_status( $row->event_id );
        return true;
    }

    public static function ticket_types( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_ticket_types WHERE event_id=%d ORDER BY sort_order ASC,id ASC", absint( $event_id ) ) );
    }

    public static function update_ticket_type( $ticket_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_ticket_types';
        $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d LIMIT 1", absint( $ticket_id ) ) );
        if ( ! $ticket ) return new WP_Error( 'mmc_ticket_missing', 'Bilet türü bulunamadı.' );
        $event = self::get_event( $ticket->event_id );
        $price = self::money( $data['price'] ?? 0 );
        $units = max( 1, absint( $data['capacity_units'] ?? 1 ) );
        if ( 'family_2_2' === $ticket->ticket_code ) $units = 4;
        $active = ! empty( $data['is_active'] ) ? 1 : 0;
        $ok = $wpdb->update( $table, array(
            'ticket_name' => sanitize_text_field( $data['ticket_name'] ?? $ticket->ticket_name ),
            'price' => $price,
            'capacity_units' => $units,
            'is_active' => $active,
            'updated_at' => current_time( 'mysql' ),
        ), array( 'id' => absint( $ticket_id ) ) );
        if ( false === $ok ) return new WP_Error( 'mmc_ticket_update_failed', 'Bilet türü güncellenemedi.' );
        if ( $event ) MMC_Program_Service::add_log( $event->program_id, 'ticket_type_updated', 'ticket_type', $ticket_id, null, array( 'price' => $price, 'capacity_units' => $units, 'active' => $active ), $ticket->ticket_name );
        self::refresh_configured_status( $ticket->event_id );
        return true;
    }

    public static function sales_readiness( $event_id ) {
        $event = self::get_event( $event_id );
        $sessions = self::sessions( $event_id );
        $tickets = self::ticket_types( $event_id );
        $active_tickets = array_filter( $tickets, function( $t ){ return (int) $t->is_active === 1 && (float) $t->price >= 0; } );
        $issues = array();
        if ( ! $event || ! $event->event_date ) $issues[] = 'Etkinlik tarihi eksik.';
        if ( ! $event || ! $event->program_venue_id ) $issues[] = 'Kesin salon bağlantısı eksik.';
        if ( count( $sessions ) < 1 ) $issues[] = 'En az bir seans gerekli.';
        foreach ( $sessions as $s ) if ( (int) $s->capacity < 1 ) $issues[] = 'Seans kapasitesi eksik.';
        if ( count( $active_tickets ) < 1 ) $issues[] = 'En az bir aktif bilet türü gerekli.';
        return array( 'ready' => empty( $issues ), 'issues' => array_values( array_unique( $issues ) ) );
    }

    public static function mark_sales_ready( $event_id ) {
        global $wpdb;
        $event = self::get_event( $event_id );
        if ( ! $event ) return new WP_Error( 'mmc_event_missing', 'Etkinlik bulunamadı.' );
        $check = self::sales_readiness( $event_id );
        if ( ! $check['ready'] ) return new WP_Error( 'mmc_sales_not_ready', implode( ' ', $check['issues'] ) );

        $wpdb->update( $wpdb->prefix . 'mmc_events', array( 'status' => 'sales_ready', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $event_id ) );
        self::ensure_integration_rows( $event->program_id, $event_id );
        self::ensure_task( $event->program_id, 'sales', 'WooCommerce satış ürünlerini/bağlantılarını oluştur ve doğrula', 'high' );
        self::ensure_task( $event->program_id, 'sales', 'Tickera bilet/QR yapılandırmasını oluştur ve doğrula', 'high' );
        self::ensure_task( $event->program_id, 'sales', 'PayTR ödeme akışını test et', 'high' );
        self::ensure_task( $event->program_id, 'sales', 'Biletinial etkinlik bilgisini hazırla / yayına al', 'normal' );
        MMC_Program_Service::set_status( $event->program_id, 'sales_prep', 'Etkinlik/seans/bilet yapısı doğrulandı; satış entegrasyonları hazırlanıyor.' );
        MMC_Program_Service::add_log( $event->program_id, 'sales_readiness_passed', 'event', $event_id, null, null, 'Etkinlik satışa hazırlık doğrulamasını geçti.' );
        return true;
    }

    public static function channel_prices( $event_id, $channel = 'biletinial' ) {
        global $wpdb;
        $tickets = $wpdb->prefix . 'mmc_ticket_types';
        $prices = $wpdb->prefix . 'mmc_channel_prices';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id ticket_type_id,t.ticket_code,t.ticket_name,t.price primary_price,p.id price_id,p.price channel_price,p.is_active channel_active
             FROM $tickets t LEFT JOIN $prices p ON p.ticket_type_id=t.id AND p.channel=%s
             WHERE t.event_id=%d ORDER BY t.sort_order ASC,t.id ASC",
            sanitize_key( $channel ), absint( $event_id )
        ) );
    }

    public static function update_channel_price( $event_id, $ticket_type_id, $channel, $price, $active = 1 ) {
        global $wpdb;
        $event = self::get_event( $event_id );
        if ( ! $event ) return new WP_Error( 'mmc_channel_price_event', 'Etkinlik bulunamadı.' );
        $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_ticket_types WHERE id=%d AND event_id=%d LIMIT 1", absint($ticket_type_id), absint($event_id) ) );
        if ( ! $ticket ) return new WP_Error( 'mmc_channel_price_ticket', 'Bilet türü bulunamadı.' );
        $channel = sanitize_key( $channel );
        if ( ! in_array( $channel, array( 'biletinial','website' ), true ) ) return new WP_Error( 'mmc_channel_price_channel', 'Geçersiz kanal.' );
        $price = self::money( $price );
        $table = $wpdb->prefix . 'mmc_channel_prices';
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE ticket_type_id=%d AND channel=%s LIMIT 1", $ticket_type_id, $channel ) );
        $now = current_time('mysql');
        if ( $existing ) {
            $ok=$wpdb->update($table,array('price'=>$price,'is_active'=>!empty($active)?1:0,'updated_at'=>$now),array('id'=>(int)$existing));
        } else {
            $ok=$wpdb->insert($table,array('event_id'=>$event_id,'ticket_type_id'=>$ticket_type_id,'channel'=>$channel,'price'=>$price,'is_active'=>!empty($active)?1:0,'created_at'=>$now,'updated_at'=>$now));
        }
        if ( false === $ok ) return new WP_Error( 'mmc_channel_price_save', 'Kanal fiyatı kaydedilemedi.' );
        MMC_Program_Service::add_log( $event->program_id, 'channel_price_updated', 'ticket_type', $ticket_type_id, null, array('channel'=>$channel,'price'=>$price), $ticket->ticket_name );
        return true;
    }

    public static function integrations( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_integration_status WHERE event_id=%d ORDER BY id ASC", absint( $event_id ) ) );
    }

    public static function update_integration( $id, $status, $external_id = '', $external_url = '', $notes = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_integration_status';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d LIMIT 1", absint( $id ) ) );
        if ( ! $row ) return new WP_Error( 'mmc_integration_missing', 'Entegrasyon kaydı bulunamadı.' );
        $allowed = array( 'pending','prepared','verified','live','error','not_used' );
        $status = sanitize_key( $status );
        if ( ! in_array( $status, $allowed, true ) ) return new WP_Error( 'mmc_integration_status', 'Geçersiz entegrasyon durumu.' );
        $ok = $wpdb->update( $table, array(
            'status' => $status,
            'external_id' => sanitize_text_field( $external_id ),
            'external_url' => esc_url_raw( $external_url ),
            'notes' => sanitize_textarea_field( $notes ),
            'last_synced_at' => in_array( $status, array( 'verified','live' ), true ) ? current_time( 'mysql' ) : $row->last_synced_at,
            'updated_at' => current_time( 'mysql' ),
        ), array( 'id' => absint( $id ) ) );
        if ( false === $ok ) return new WP_Error( 'mmc_integration_update_failed', 'Entegrasyon kaydı güncellenemedi.' );
        MMC_Program_Service::add_log( $row->program_id, 'sales_integration_updated', 'integration', $id, $row->status, $status, $row->channel );
        self::maybe_mark_sales_open( $row->event_id );
        return true;
    }

    private static function maybe_mark_sales_open( $event_id ) {
        global $wpdb;
        $event = self::get_event( $event_id );
        if ( ! $event ) return;
        $rows = self::integrations( $event_id );
        $required = array( 'woocommerce','tickera','paytr' );
        $ok = true;
        foreach ( $required as $channel ) {
            $found = false;
            foreach ( $rows as $row ) {
                if ( $row->channel === $channel ) {
                    $found = true;
                    if ( ! in_array( $row->status, array( 'verified','live' ), true ) ) $ok = false;
                    break;
                }
            }
            if ( ! $found ) $ok = false;
        }
        if ( $ok ) {
            $wpdb->update( $wpdb->prefix . 'mmc_events', array( 'status'=>'sales_open', 'updated_at'=>current_time('mysql') ), array( 'id'=>$event_id ) );
            MMC_Program_Service::set_status( $event->program_id, 'sales_open', 'WooCommerce, Tickera ve PayTR doğrulandı; program satışta olarak işaretlendi.' );
        }
    }

    private static function refresh_configured_status( $event_id ) {
        global $wpdb;
        $event = self::get_event( $event_id );
        if ( ! $event || in_array( $event->status, array( 'sales_ready','sales_open','closed','cancelled' ), true ) ) return;
        $sessions = self::sessions( $event_id );
        $tickets = self::ticket_types( $event_id );
        $active = array_filter( $tickets, function( $t ){ return (int)$t->is_active === 1; } );
        $status = ( $event->event_date && count( $sessions ) > 0 && count( $active ) > 0 ) ? 'configured' : 'draft';
        $wpdb->update( $wpdb->prefix . 'mmc_events', array( 'status'=>$status, 'updated_at'=>current_time('mysql') ), array( 'id'=>$event_id ) );
    }

    private static function seed_default_ticket_types( $event_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_ticket_types';
        $now = current_time( 'mysql' );
        $defaults = array(
            array( 'child', 'Çocuk', 3, 12, 250, 1, 10, 1 ),
            array( 'adult', 'Yetişkin', 13, null, 500, 1, 20, 1 ),
            array( 'family_2_2', 'Aile Paketi 2+2', null, null, 1100, 4, 30, 1 ),
        );
        $ids = array();
        foreach ( $defaults as $d ) {
            $wpdb->insert( $table, array(
                'event_id'=>$event_id,'ticket_code'=>$d[0],'ticket_name'=>$d[1],
                'age_min'=>$d[2],'age_max'=>$d[3],'price'=>$d[4],'capacity_units'=>$d[5],
                'sort_order'=>$d[6],'is_active'=>$d[7],'created_at'=>$now,'updated_at'=>$now,
            ) );
            $ids[$d[0]] = (int) $wpdb->insert_id;
        }
        foreach ( $ids as $code => $ticket_id ) {
            $biletinial_price = 'family_2_2' === $code ? 1200 : ( 'adult' === $code ? 500 : 250 );
            $wpdb->insert( $wpdb->prefix . 'mmc_channel_prices', array(
                'event_id'=>$event_id,'ticket_type_id'=>$ticket_id,'channel'=>'biletinial','price'=>$biletinial_price,'is_active'=>1,'created_at'=>$now,'updated_at'=>$now,
            ) );
        }
    }

    private static function ensure_integration_rows( $program_id, $event_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_integration_status';
        foreach ( array_keys( self::integration_channels() ) as $channel ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE program_id=%d AND event_id=%d AND channel=%s LIMIT 1", $program_id, $event_id, $channel ) );
            if ( $exists ) continue;
            $now = current_time( 'mysql' );
            $wpdb->insert( $table, array(
                'program_id'=>$program_id,'event_id'=>$event_id,'channel'=>$channel,'status'=>'pending',
                'created_at'=>$now,'updated_at'=>$now,
            ) );
        }
    }

    private static function ensure_task( $program_id, $module, $title, $priority = 'normal' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_tasks';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE program_id=%d AND module=%s AND title=%s AND status<>'cancelled' LIMIT 1", $program_id, $module, $title ) );
        if ( $exists ) return;
        $now = current_time( 'mysql' );
        $wpdb->insert( $table, array(
            'program_id'=>$program_id,'module'=>$module,'title'=>$title,'status'=>'open','priority'=>$priority,
            'created_by'=>get_current_user_id() ?: null,'created_at'=>$now,'updated_at'=>$now,
        ) );
    }

    public static function backfill_existing_confirmed_programs() {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT program_id,id FROM {$wpdb->prefix}mmc_program_venues WHERE is_selected=1 AND allocation_status='approved'" );
        foreach ( $rows as $row ) {
            self::ensure_event_for_program( (int)$row->program_id, (int)$row->id );
        }
    }

    private static function generate_event_code( $program, $date ) {
        $province = self::slug_code( $program->province_name, 3 );
        $district = self::slug_code( $program->district_name ?: 'GEN', 4 );
        $date_code = $date ? str_replace( '-', '', $date ) : (string) $program->plan_year;
        return 'EVT-' . $date_code . '-' . $province . '-' . $district . '-' . absint( $program->id );
    }

    private static function slug_code( $value, $len ) {
        $value = remove_accents( (string) $value );
        $value = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $value ) );
        return substr( $value ?: 'XXX', 0, $len );
    }

    private static function date_or_null( $value ) {
        $value = sanitize_text_field( (string) $value );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
    }

    private static function datetime_or_null( $value ) {
        $value = sanitize_text_field( (string) $value );
        if ( ! $value ) return null;
        $value = str_replace( 'T', ' ', $value );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $value ) ) return substr( $value, 0, 16 ) . ':00';
        return null;
    }

    private static function money( $value ) {
        if ( is_string( $value ) ) {
            $value = trim( $value );
            if ( false !== strpos( $value, ',' ) ) {
                $value = str_replace( '.', '', $value );
                $value = str_replace( ',', '.', $value );
            }
        }
        return round( (float) $value, 2 );
    }
}
