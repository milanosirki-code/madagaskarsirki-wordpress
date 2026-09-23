<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Program_Service {
    public static function statuses() {
        return array(
            'preparation'            => 'Hazırlık',
            'region_analysis'        => 'Bölge Analizi',
            'venue_research'         => 'Salon Araştırması',
            'allocation_request'     => 'Tahsis Talebi',
            'allocation_pending'     => 'Tahsis Cevabı Bekleniyor',
            'venue_confirmed'        => 'Salon Kesinleşti',
            'venue_payment'          => 'Salon Ödemeleri',
            'event_setup'            => 'Etkinlik Hazırlığı',
            'sales_prep'             => 'Satışa Hazırlanıyor',
            'sales_open'             => 'Satışta',
            'promotion'              => 'Tanıtım / Reklam',
            'operations'             => 'Operasyon Hazırlığı',
            'show_day'               => 'Gösteri Günü',
            'financial_close'        => 'Finansal Kapanış',
            'deposit_refund'         => 'Teminat İadesi Bekleniyor',
            'completed'              => 'Tamamlandı',
            'cancelled'              => 'İptal',
        );
    }

    public static function modules() {
        return array(
            'system'      => 'Sistem',
            'region'      => 'Bölge Analizi',
            'venue'       => 'Salon / Tahsis',
            'finance'     => 'Finans',
            'event'       => 'Etkinlik / Seans',
            'sales'       => 'Satış',
            'kommo'       => 'Kommo / AI',
            'marketing'   => 'Afiş / Sosyal / Meta',
            'field'       => 'Okul / Saha',
            'operations'  => 'Operasyon',
            'reporting'   => 'Raporlama',
        );
    }

    public static function create_program( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_programs';

        $province = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $data['province_name'] ?? '' ) : sanitize_text_field( $data['province_name'] ?? '' );
        $district = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $data['district_name'] ?? '' ) : sanitize_text_field( $data['district_name'] ?? '' );
        $year     = absint( $data['plan_year'] ?? wp_date( 'Y' ) );
        $date     = sanitize_text_field( $data['planned_date'] ?? '' );
        $notes    = sanitize_textarea_field( $data['notes'] ?? '' );

        if ( '' === $province ) {
            return new WP_Error( 'mmc_missing_province', 'İl alanı zorunludur.' );
        }

        $code = self::generate_program_code( $province, $district, $year );
        $now  = current_time( 'mysql' );
        $uid  = get_current_user_id();

        $result = $wpdb->insert(
            $table,
            array(
                'program_code'  => $code,
                'brand'         => 'Madagaskar Sirki',
                'province_name' => $province,
                'district_name' => $district,
                'plan_year'     => $year,
                'planned_date'  => $date ?: null,
                'status'        => 'preparation',
                'owner_user_id' => $uid ?: null,
                'notes'         => $notes ?: null,
                'created_by'    => $uid,
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array( '%s','%s','%s','%s','%d','%s','%s','%d','%s','%d','%s','%s' )
        );

        if ( false === $result ) {
            return new WP_Error( 'mmc_insert_failed', 'Program kaydı oluşturulamadı.' );
        }

        $program_id = (int) $wpdb->insert_id;
        self::add_log( $program_id, 'program_created', 'program', $program_id, null, array(
            'program_code' => $code,
            'province'     => $province,
            'district'     => $district,
            'status'       => 'preparation',
        ), 'Yeni program dosyası oluşturuldu.' );

        self::seed_initial_tasks( $program_id );

        return $program_id;
    }


    public static function get_program( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_programs';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d LIMIT 1", absint( $program_id ) ) );
    }

    public static function set_status( $program_id, $new_status, $note = '' ) {
        global $wpdb;
        $statuses = self::statuses();
        if ( ! isset( $statuses[ $new_status ] ) ) {
            return new WP_Error( 'mmc_invalid_status', 'Geçersiz program durumu.' );
        }
        $program = self::get_program( $program_id );
        if ( ! $program ) {
            return new WP_Error( 'mmc_program_missing', 'Program bulunamadı.' );
        }
        if ( $program->status === $new_status ) {
            return true;
        }
        $table = $wpdb->prefix . 'mmc_programs';
        $ok = $wpdb->update(
            $table,
            array( 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => absint( $program_id ) ),
            array( '%s','%s' ),
            array( '%d' )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mmc_status_update', 'Program durumu güncellenemedi.' );
        }
        self::add_log( $program_id, 'program_status_changed', 'program', $program_id, $program->status, $new_status, $note );
        return true;
    }

    public static function all_programs() {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_programs';
        return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC, id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function counts_by_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_programs';
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM $table GROUP BY status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $out = array();
        foreach ( self::statuses() as $key => $label ) {
            $out[ $key ] = isset( $rows[ $key ] ) ? (int) $rows[ $key ]->total : 0;
        }
        return $out;
    }

    public static function recent_logs( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_logs';
        $limit = max( 1, min( 50, absint( $limit ) ) );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC, id DESC LIMIT %d", $limit ) );
    }

    public static function add_log( $program_id, $action, $entity_type, $entity_id, $old_value, $new_value, $note = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_logs';
        $result = $wpdb->insert( $table, array(
            'program_id'  => $program_id ?: null,
            'action'      => sanitize_key( $action ),
            'entity_type' => sanitize_key( $entity_type ),
            'entity_id'   => $entity_id ?: null,
            'old_value'   => null === $old_value ? null : wp_json_encode( $old_value, JSON_UNESCAPED_UNICODE ),
            'new_value'   => null === $new_value ? null : wp_json_encode( $new_value, JSON_UNESCAPED_UNICODE ),
            'note'        => sanitize_text_field( $note ),
            'user_id'     => get_current_user_id() ?: null,
            'created_at'  => current_time( 'mysql' ),
        ) );
        if ( $result ) {
            do_action( 'mmc_program_logged', $program_id, sanitize_key($action), sanitize_key($entity_type), $entity_id, $old_value, $new_value, $note );
        }
        return $result;
    }

    private static function seed_initial_tasks( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_tasks';
        $now = current_time( 'mysql' );
        $uid = get_current_user_id();
        $tasks = array(
            array( 'region', 'İl / ilçe hedef bölge analizini tamamla', 'high' ),
            array( 'region', 'Tanıtım havzası ilçelerini belirle', 'high' ),
            array( 'venue', 'Salon alternatiflerini araştır', 'high' ),
        );
        foreach ( $tasks as $task ) {
            $wpdb->insert( $table, array(
                'program_id'   => $program_id,
                'module'       => $task[0],
                'title'        => $task[1],
                'status'       => 'open',
                'priority'     => $task[2],
                'created_by'   => $uid,
                'created_at'   => $now,
                'updated_at'   => $now,
            ) );
        }
    }

    private static function generate_program_code( $province, $district, $year ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_programs';

        $p = self::token( $province, 3 );
        $d = self::token( $district ?: 'GENEL', 6 );
        $prefix = sprintf( 'PRG-%d-%s-%s-', $year, $p, $d );

        $like = $wpdb->esc_like( $prefix ) . '%';
        $last = $wpdb->get_var( $wpdb->prepare( "SELECT program_code FROM $table WHERE program_code LIKE %s ORDER BY id DESC LIMIT 1", $like ) );
        $seq = 1;
        if ( $last && preg_match( '/-(\d{3})$/', $last, $m ) ) {
            $seq = (int) $m[1] + 1;
        }
        return $prefix . str_pad( (string) $seq, 3, '0', STR_PAD_LEFT );
    }

    private static function token( $text, $length ) {
        $map = array('Ç'=>'C','Ğ'=>'G','İ'=>'I','Ö'=>'O','Ş'=>'S','Ü'=>'U','ç'=>'C','ğ'=>'G','ı'=>'I','i'=>'I','ö'=>'O','ş'=>'S','ü'=>'U');
        $text = strtr( $text, $map );
        $text = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $text ) );
        return substr( $text ?: 'X', 0, $length );
    }
}
