<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Field_Service {
    const MAX_UPLOAD_BYTES = 8388608; // 8 MB

    public static function hooks() {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render_portal' ), 1 );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_export_public_kml' ), 0 );
        add_action( 'admin_post_nopriv_mmc_field_portal_visit', array( __CLASS__, 'handle_portal_visit' ) );
        add_action( 'admin_post_mmc_field_portal_visit', array( __CLASS__, 'handle_portal_visit' ) );
        add_action( 'mmc_program_logged', array( __CLASS__, 'on_program_log' ), 30, 7 );
    }

    public static function target_statuses() {
        return array(
            'planned'     => 'Planlandı',
            'assigned'    => 'Personele Atandı',
            'visited'     => 'Ziyaret Edildi',
            'revisit'     => 'Tekrar Ziyaret',
            'unavailable' => 'Ulaşılamadı / Kapalı',
            'skipped'     => 'Kapsam Dışı',
        );
    }

    public static function visit_statuses() {
        return array(
            'visited'     => 'Ziyaret Edildi',
            'revisit'     => 'Tekrar Ziyaret Gerekli',
            'unavailable' => 'Ulaşılamadı / Kapalı',
            'refused'     => 'Tanıtım Kabul Edilmedi',
        );
    }

    public static function sync_target_schools( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { return new WP_Error( 'mmc_field_program_missing', 'Program bulunamadı.' ); }

        $districts = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::get_program_targets( $program_id ) : array();
        if ( ! $districts && $program->district_name ) { $districts = array( $program->district_name ); }
        $districts = array_values( array_filter( array_map( 'sanitize_text_field', (array) $districts ) ) );
        if ( ! $districts ) { return new WP_Error( 'mmc_field_no_districts', 'Önce Hazırlık Dashboardunda tanıtım ilçelerini seçin.' ); }

        // Okul ana kaynağı varsa (Madagaskar → Okul Tanıtım), önce yalnız ilgili
        // il/ilçeleri MMC'nin uyumluluk cache'ine yenile. Cache düzenleme kaynağı değildir.
        $school_source_sync = class_exists( 'MMC_School_Source_Service' )
            ? MMC_School_Source_Service::refresh_cache_for_area( $program->province_name, $districts )
            : array( 'external'=>false, 'source'=>array( 'label'=>'MMC okul cache' ) );

        $schools_table = $wpdb->prefix . 'mmc_schools';
        $targets_table = $wpdb->prefix . 'mmc_program_target_schools';
        $placeholders = implode( ',', array_fill( 0, count( $districts ), '%s' ) );
        $params = array_merge( array( $program->province_name ), $districts );
        $schools = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $schools_table WHERE province_name=%s AND district_name IN ($placeholders) AND is_active=1 ORDER BY district_name,school_name",
            $params
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $now = current_time( 'mysql' );
        $uid = get_current_user_id();
        $created = 0;
        $updated = 0;
        foreach ( (array) $schools as $school ) {
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $targets_table WHERE program_id=%d AND school_id=%d LIMIT 1", $program_id, $school->id ) );
            if ( $existing ) {
                // Snapshot ilk hedefleme anını temsil eder. Ana okul kaynağı sonradan
                // değişse bile geçmiş Program Dosyasının öğrenci hedefini sessizce ezme.
                $update = array( 'updated_at'=>$now );
                $formats = array( '%s' );
                if ( null === $existing->student_count_snapshot && null !== $school->student_count ) {
                    $update['student_count_snapshot'] = absint( $school->student_count );
                    $formats[] = '%d';
                }
                if ( empty( $existing->data_year_snapshot ) && ! empty( $school->data_year ) ) {
                    $update['data_year_snapshot'] = (string) $school->data_year;
                    $formats[] = '%s';
                }
                $wpdb->update( $targets_table, $update, array( 'id'=>absint($existing->id) ), $formats, array('%d') );
                $updated++;
            } else {
                $wpdb->insert(
                    $targets_table,
                    array(
                        'program_id'             => $program_id,
                        'school_id'              => absint( $school->id ),
                        'priority'               => 'normal',
                        'status'                 => 'planned',
                        'student_count_snapshot' => null === $school->student_count ? null : absint( $school->student_count ),
                        'data_year_snapshot'     => (string) $school->data_year,
                        'created_by'             => $uid ?: null,
                        'created_at'             => $now,
                        'updated_at'             => $now,
                    ),
                    array( '%d','%d','%s','%s','%d','%s','%d','%s','%s' )
                );
                if ( $wpdb->insert_id ) { $created++; }
            }
        }

        MMC_Program_Service::add_log(
            $program_id,
            'field_target_schools_synced',
            'field',
            $program_id,
            null,
            array(
                'districts'=>$districts,
                'created'=>$created,
                'updated'=>$updated,
                'available'=>count( $schools ),
                'school_source'=>sanitize_text_field( $school_source_sync['source']['label'] ?? 'MMC okul cache' ),
                'source_external'=>! empty( $school_source_sync['external'] ),
            ),
            ! empty( $school_source_sync['external'] )
                ? 'Okul Tanıtım ana listesindeki hedef ilçeler Programa bağlandı.'
                : 'Hedef ilçelerdeki uyumluluk okul cache kaydı saha havuzuna aktarıldı.'
        );

        self::ensure_field_tasks( $program_id );
        return array(
            'created'=>$created,
            'updated'=>$updated,
            'available'=>count( $schools ),
            'source'=>$school_source_sync['source']['label'] ?? 'MMC okul cache',
            'external'=>! empty( $school_source_sync['external'] ),
        );
    }

    public static function targets( $program_id, $args = array() ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $targets = $wpdb->prefix . 'mmc_program_target_schools';
        $schools = $wpdb->prefix . 'mmc_schools';

        $where = array( 't.program_id=%d' );
        $params = array( $program_id );
        if ( ! empty( $args['status'] ) ) { $where[] = 't.status=%s'; $params[] = sanitize_key( $args['status'] ); }
        if ( ! empty( $args['district'] ) ) { $where[] = 's.district_name=%s'; $params[] = sanitize_text_field( $args['district'] ); }
        if ( ! empty( $args['assigned_name'] ) ) { $where[] = 't.assigned_name=%s'; $params[] = sanitize_text_field( $args['assigned_name'] ); }
        if ( ! empty( $args['assigned_user_id'] ) ) { $where[] = 't.assigned_user_id=%d'; $params[] = absint( $args['assigned_user_id'] ); }
        if ( ! empty( $args['search'] ) ) { $where[] = '(s.school_name LIKE %s OR s.address LIKE %s)'; $like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%'; $params[]=$like; $params[]=$like; }
        if ( ! empty( $args['exclude_skipped'] ) ) { $where[] = "t.status<>'skipped'"; }

        $limit = isset( $args['limit'] ) ? min( 2000, max( 1, absint( $args['limit'] ) ) ) : 1000;
        $sql = "SELECT t.*, s.school_name,s.province_name,s.district_name,s.school_type,s.education_level,s.address,s.latitude,s.longitude,s.student_count,s.data_year,s.institution_code
                FROM $targets t INNER JOIN $schools s ON s.id=t.school_id
                WHERE " . implode( ' AND ', $where ) . " ORDER BY s.district_name,s.school_name LIMIT $limit";
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function target( $target_id ) {
        global $wpdb;
        $targets = $wpdb->prefix . 'mmc_program_target_schools';
        $schools = $wpdb->prefix . 'mmc_schools';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, s.school_name,s.province_name,s.district_name,s.school_type,s.education_level,s.address,s.latitude,s.longitude,s.student_count,s.data_year,s.institution_code
             FROM $targets t INNER JOIN $schools s ON s.id=t.school_id WHERE t.id=%d LIMIT 1",
            absint( $target_id )
        ) );
    }

    public static function summary( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $targets = $wpdb->prefix . 'mmc_program_target_schools';
        $visits = $wpdb->prefix . 'mmc_field_visits';
        $schools = $wpdb->prefix . 'mmc_schools';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) target_schools,
                    SUM(CASE WHEN t.status IN ('assigned','visited','revisit','unavailable') THEN 1 ELSE 0 END) assigned_schools,
                    SUM(CASE WHEN t.status='visited' THEN 1 ELSE 0 END) visited_schools,
                    SUM(CASE WHEN t.status='revisit' THEN 1 ELSE 0 END) revisit_schools,
                    SUM(COALESCE(t.student_count_snapshot,0)) target_students,
                    SUM(CASE WHEN t.status='visited' THEN COALESCE(t.student_count_snapshot,0) ELSE 0 END) covered_students
             FROM $targets t WHERE t.program_id=%d",
            $program_id
        ) );
        $reported = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(students_reached),0) FROM $visits WHERE program_id=%d", $program_id ) );
        $photos = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $visits WHERE program_id=%d AND photo_attachment_id IS NOT NULL AND photo_attachment_id>0", $program_id ) );
        $districts = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT s.district_name) FROM $targets t INNER JOIN $schools s ON s.id=t.school_id WHERE t.program_id=%d",
            $program_id
        ) );

        $target_schools = (int) ( $row->target_schools ?? 0 );
        $visited_schools = (int) ( $row->visited_schools ?? 0 );
        $target_students = (int) ( $row->target_students ?? 0 );
        $covered_students = (int) ( $row->covered_students ?? 0 );
        return array(
            'target_schools'    => $target_schools,
            'assigned_schools'  => (int) ( $row->assigned_schools ?? 0 ),
            'visited_schools'   => $visited_schools,
            'revisit_schools'   => (int) ( $row->revisit_schools ?? 0 ),
            'target_students'   => $target_students,
            'covered_students'  => $covered_students,
            'reported_students' => $reported,
            'photo_visits'      => $photos,
            'districts'         => $districts,
            'visit_percent'     => $target_schools ? round( 100 * $visited_schools / $target_schools, 1 ) : 0,
            'coverage_percent'  => $target_students ? round( 100 * $covered_students / $target_students, 1 ) : 0,
        );
    }

    public static function assign_targets( $program_id, $target_ids, $assigned_user_id = 0, $assigned_name = '' ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $assigned_user_id = absint( $assigned_user_id );
        $assigned_name = sanitize_text_field( $assigned_name );
        if ( $assigned_user_id ) {
            $u = get_userdata( $assigned_user_id );
            if ( $u && ! $assigned_name ) { $assigned_name = $u->display_name; }
        }
        if ( ! $assigned_name && ! $assigned_user_id ) { return new WP_Error( 'mmc_field_assignee_missing', 'Personel adı veya kullanıcı seçin.' ); }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $target_ids ) ) ) );
        if ( ! $ids ) { return new WP_Error( 'mmc_field_targets_missing', 'Atanacak okul seçilmedi.' ); }

        $table = $wpdb->prefix . 'mmc_program_target_schools';
        $now = current_time( 'mysql' );
        $count = 0;
        foreach ( $ids as $id ) {
            $current_status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id=%d AND program_id=%d LIMIT 1", $id, $program_id ) );
            $next_status = 'visited' === $current_status ? 'visited' : 'assigned';
            $ok = $wpdb->update(
                $table,
                array(
                    'assigned_user_id' => $assigned_user_id ?: null,
                    'assigned_name'    => $assigned_name,
                    'status'           => $next_status,
                    'updated_at'       => $now,
                ),
                array( 'id'=>$id, 'program_id'=>$program_id ),
                array( '%d','%s','%s','%s' ),
                array( '%d','%d' )
            );
            if ( false !== $ok ) { $count++; }
        }
        MMC_Program_Service::add_log( $program_id, 'field_schools_assigned', 'field', $program_id, null, array( 'count'=>$count, 'assigned_name'=>$assigned_name, 'user_id'=>$assigned_user_id ), 'Okullar saha personeline atandı.' );
        return $count;
    }

    public static function bulk_target_status( $program_id, $target_ids, $status ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $allowed = array( 'planned','skipped' );
        $status = sanitize_key( $status );
        if ( ! in_array( $status, $allowed, true ) ) { return new WP_Error( 'mmc_field_bulk_status', 'Toplu durum geçersiz.' ); }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $target_ids ) ) ) );
        if ( ! $ids ) { return new WP_Error( 'mmc_field_targets_missing', 'Okul seçilmedi.' ); }
        $table = $wpdb->prefix . 'mmc_program_target_schools';
        $now = current_time( 'mysql' );
        $count = 0;
        foreach ( $ids as $id ) {
            $data = array( 'status'=>$status, 'updated_at'=>$now );
            $format = array( '%s','%s' );
            if ( 'planned' === $status ) {
                $data['assigned_user_id'] = null;
                $data['assigned_name'] = '';
                $format[] = '%d'; $format[] = '%s';
            }
            $ok = $wpdb->update( $table, $data, array( 'id'=>$id, 'program_id'=>$program_id ), $format, array('%d','%d') );
            if ( false !== $ok ) { $count++; }
        }
        MMC_Program_Service::add_log( $program_id, 'field_school_scope_changed', 'field', $program_id, null, array( 'status'=>$status,'count'=>$count ), 'Okul saha kapsam durumu toplu güncellendi.' );
        return $count;
    }

    public static function add_route( $program_id, $data ) {
        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! MMC_Program_Service::get_program( $program_id ) ) { return new WP_Error( 'mmc_field_program_missing', 'Program bulunamadı.' ); }
        $title = sanitize_text_field( $data['title'] ?? '' );
        $url = esc_url_raw( $data['external_url'] ?? '' );
        if ( ! $title || ! $url ) { return new WP_Error( 'mmc_field_route_missing', 'Rota başlığı ve bağlantısı zorunludur.' ); }
        $table = $wpdb->prefix . 'mmc_field_routes';
        $now = current_time( 'mysql' );
        $wpdb->insert( $table, array(
            'program_id'=>$program_id,
            'title'=>$title,
            'route_app'=>sanitize_key( $data['route_app'] ?? 'custom' ),
            'external_url'=>$url,
            'district_name'=>sanitize_text_field( $data['district_name'] ?? '' ),
            'assigned_user_id'=>absint( $data['assigned_user_id'] ?? 0 ) ?: null,
            'assigned_name'=>sanitize_text_field( $data['assigned_name'] ?? '' ),
            'notes'=>sanitize_textarea_field( $data['notes'] ?? '' ),
            'is_active'=>1,
            'created_by'=>get_current_user_id() ?: null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ) );
        if ( ! $wpdb->insert_id ) { return new WP_Error( 'mmc_field_route_insert', 'Rota kaydedilemedi.' ); }
        MMC_Program_Service::add_log( $program_id, 'field_route_added', 'field_route', $wpdb->insert_id, null, array( 'title'=>$title, 'url'=>$url ), 'Saha rota bağlantısı eklendi.' );
        return (int) $wpdb->insert_id;
    }

    public static function routes( $program_id, $context = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_field_routes';
        $where = array( 'program_id=%d', 'is_active=1' );
        $params = array( absint( $program_id ) );
        $name = sanitize_text_field( $context['assigned_name'] ?? '' );
        $uid  = absint( $context['assigned_user_id'] ?? 0 );
        if ( $uid || $name ) {
            $pieces = array( "(assigned_user_id IS NULL OR assigned_user_id=0 AND assigned_name='')" );
            if ( $uid ) { $pieces[] = 'assigned_user_id=%d'; $params[]=$uid; }
            if ( $name ) { $pieces[] = 'assigned_name=%s'; $params[]=$name; }
            $where[] = '(' . implode( ' OR ', $pieces ) . ')';
        }
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . " ORDER BY id DESC", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function recent_visits( $program_id, $limit = 30 ) {
        global $wpdb;
        $visits = $wpdb->prefix . 'mmc_field_visits';
        $schools = $wpdb->prefix . 'mmc_schools';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT v.*,s.school_name,s.district_name FROM $visits v INNER JOIN $schools s ON s.id=v.school_id WHERE v.program_id=%d ORDER BY v.visited_at DESC,v.id DESC LIMIT %d",
            absint( $program_id ), min( 200, max( 1, absint( $limit ) ) )
        ) );
    }

    public static function create_share_token( $program_id, $assigned_name = '', $assigned_user_id = 0, $days = 14 ) {
        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! MMC_Program_Service::get_program( $program_id ) ) { return new WP_Error( 'mmc_field_program_missing', 'Program bulunamadı.' ); }
        $assigned_name = sanitize_text_field( $assigned_name );
        $assigned_user_id = absint( $assigned_user_id );
        if ( $assigned_user_id && ! $assigned_name ) {
            $u = get_userdata( $assigned_user_id );
            if ( $u ) { $assigned_name = $u->display_name; }
        }
        $raw = wp_generate_password( 48, false, false );
        $hash = hash( 'sha256', $raw );
        $hint = substr( $raw, 0, 6 ) . '…' . substr( $raw, -4 );
        $days = min( 90, max( 1, absint( $days ) ) );
        $now = current_time( 'mysql' );
        $expires = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + DAY_IN_SECONDS * $days );
        $table = $wpdb->prefix . 'mmc_field_tokens';
        $wpdb->insert( $table, array(
            'program_id'=>$program_id,
            'token_hash'=>$hash,
            'token_hint'=>$hint,
            'assigned_user_id'=>$assigned_user_id ?: null,
            'assigned_name'=>$assigned_name,
            'is_active'=>1,
            'expires_at'=>$expires,
            'created_by'=>get_current_user_id() ?: null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ) );
        if ( ! $wpdb->insert_id ) { return new WP_Error( 'mmc_field_token_insert', 'Saha bağlantısı oluşturulamadı.' ); }
        MMC_Program_Service::add_log( $program_id, 'field_share_link_created', 'field_token', $wpdb->insert_id, null, array( 'assigned_name'=>$assigned_name, 'expires_at'=>$expires ), 'Saha personeli paylaşım bağlantısı oluşturuldu.' );
        return array(
            'id'=>(int)$wpdb->insert_id,
            'token'=>$raw,
            'url'=>self::portal_url( $raw ),
            'expires_at'=>$expires,
            'assigned_name'=>$assigned_name,
        );
    }

    public static function portal_url( $token ) {
        return add_query_arg( array( 'mmc_field_portal'=>1, 'token'=>$token ), home_url( '/' ) );
    }

    public static function validate_token( $raw ) {
        global $wpdb;
        $raw = sanitize_text_field( (string) $raw );
        if ( strlen( $raw ) < 20 ) { return false; }
        $table = $wpdb->prefix . 'mmc_field_tokens';
        $hash = hash( 'sha256', $raw );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE token_hash=%s AND is_active=1 LIMIT 1", $hash ) );
        if ( ! $row ) { return false; }
        if ( $row->expires_at && strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) { return false; }
        $wpdb->update( $table, array( 'last_used_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql') ), array( 'id'=>$row->id ), array( '%s','%s' ), array( '%d' ) );
        return $row;
    }

    public static function active_tokens( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_field_tokens';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE program_id=%d ORDER BY is_active DESC,id DESC", absint( $program_id ) ) );
    }

    public static function revoke_token( $token_id, $program_id ) {
        global $wpdb;
        return false !== $wpdb->update(
            $wpdb->prefix . 'mmc_field_tokens',
            array( 'is_active'=>0, 'updated_at'=>current_time('mysql') ),
            array( 'id'=>absint($token_id), 'program_id'=>absint($program_id) ),
            array( '%d','%s' ), array( '%d','%d' )
        );
    }

    public static function save_visit( $target_id, $data, $file = null, $context = array() ) {
        global $wpdb;
        $target = self::target( $target_id );
        if ( ! $target ) { return new WP_Error( 'mmc_field_target_missing', 'Okul hedef kaydı bulunamadı.' ); }
        if ( ! empty( $context['program_id'] ) && absint( $context['program_id'] ) !== absint( $target->program_id ) ) {
            return new WP_Error( 'mmc_field_target_forbidden', 'Bu bağlantı bu okul için geçerli değil.' );
        }
        if ( ! empty( $context['assigned_user_id'] ) && absint( $target->assigned_user_id ) && absint( $context['assigned_user_id'] ) !== absint( $target->assigned_user_id ) ) {
            return new WP_Error( 'mmc_field_target_forbidden', 'Okul başka personele atanmış.' );
        }
        if ( ! empty( $context['assigned_name'] ) && $target->assigned_name && 0 !== strcasecmp( trim($context['assigned_name']), trim($target->assigned_name) ) ) {
            return new WP_Error( 'mmc_field_target_forbidden', 'Okul başka personele atanmış.' );
        }

        $visit_status = sanitize_key( $data['visit_status'] ?? 'visited' );
        if ( ! isset( self::visit_statuses()[ $visit_status ] ) ) { return new WP_Error( 'mmc_field_bad_status', 'Geçersiz ziyaret durumu.' ); }
        $visitor_name = sanitize_text_field( $data['visitor_name'] ?? '' );
        $visitor_user_id = get_current_user_id();
        if ( ! $visitor_name && ! empty( $context['assigned_name'] ) ) { $visitor_name = sanitize_text_field( $context['assigned_name'] ); }
        if ( ! $visitor_name && $visitor_user_id ) {
            $u = get_userdata( $visitor_user_id ); if ( $u ) { $visitor_name = $u->display_name; }
        }
        if ( ! $visitor_name ) { return new WP_Error( 'mmc_field_visitor_missing', 'Ziyareti yapan personelin adını girin.' ); }

        $photo_id = 0;
        if ( $file && ! empty( $file['name'] ) ) {
            $photo_id = self::handle_photo_upload( $file, $target );
            if ( is_wp_error( $photo_id ) ) { return $photo_id; }
        }
        if ( 'visited' === $visit_status && ! $photo_id ) {
            return new WP_Error( 'mmc_field_photo_required', 'Ziyaret edildi kaydı için okul tabela fotoğrafı zorunludur.' );
        }

        $visited_at = self::normalize_datetime( $data['visited_at'] ?? '' );
        if ( ! $visited_at ) { $visited_at = current_time( 'mysql' ); }
        $students_reached = max( 0, absint( $data['students_reached'] ?? 0 ) );
        $materials = max( 0, absint( $data['materials_delivered'] ?? 0 ) );
        $notes = sanitize_textarea_field( $data['notes'] ?? '' );
        $now = current_time( 'mysql' );

        $visits = $wpdb->prefix . 'mmc_field_visits';
        $ok = $wpdb->insert( $visits, array(
            'program_id'=>absint($target->program_id),
            'target_school_id'=>absint($target->id),
            'school_id'=>absint($target->school_id),
            'visitor_user_id'=>$visitor_user_id ?: null,
            'visitor_name'=>$visitor_name,
            'visit_status'=>$visit_status,
            'visited_at'=>$visited_at,
            'students_reached'=>$students_reached,
            'materials_delivered'=>$materials,
            'photo_attachment_id'=>$photo_id ?: null,
            'notes'=>$notes,
            'created_at'=>$now,
            'updated_at'=>$now,
        ) );
        if ( false === $ok ) { return new WP_Error( 'mmc_field_visit_insert', 'Ziyaret kaydedilemedi.' ); }

        $target_status = 'visited' === $visit_status ? 'visited' : ( 'revisit' === $visit_status ? 'revisit' : 'unavailable' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}mmc_program_target_schools SET status=%s,visit_count=visit_count+1,last_visit_at=%s,updated_at=%s WHERE id=%d",
            $target_status, $visited_at, $now, absint($target->id)
        ) );
        MMC_Program_Service::add_log( absint($target->program_id), 'field_visit_saved', 'school', absint($target->school_id), null, array( 'school'=>$target->school_name, 'status'=>$visit_status, 'visitor'=>$visitor_name, 'photo_id'=>$photo_id, 'students_reached'=>$students_reached ), 'Saha okul ziyareti kaydedildi.' );
        return (int) $wpdb->insert_id;
    }

    private static function handle_photo_upload( $file, $target ) {
        if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) { return new WP_Error( 'mmc_field_upload_error', 'Fotoğraf yüklenemedi.' ); }
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { return new WP_Error( 'mmc_field_upload_missing', 'Fotoğraf dosyası bulunamadı.' ); }
        if ( ! empty( $file['size'] ) && (int)$file['size'] > self::MAX_UPLOAD_BYTES ) { return new WP_Error( 'mmc_field_upload_large', 'Fotoğraf en fazla 8 MB olabilir.' ); }
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array('jpg','jpeg','png','webp'), true ) ) { return new WP_Error( 'mmc_field_upload_type', 'Yalnız JPG, PNG veya WEBP fotoğraf yüklenebilir.' ); }
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        $allowed_mimes = array( 'image/jpeg','image/png','image/webp' );
        if ( empty( $checked['type'] ) || ! in_array( $checked['type'], $allowed_mimes, true ) ) { return new WP_Error( 'mmc_field_upload_type', 'Dosya geçerli bir fotoğraf değil.' ); }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $_FILES['mmc_school_photo_upload'] = $file;
        $id = media_handle_upload( 'mmc_school_photo_upload', 0, array(
            'post_title' => 'MMC Saha - ' . sanitize_text_field( $target->school_name ) . ' - ' . wp_date( 'Y-m-d H:i' ),
            'post_content' => 'Madagaskar Management Center saha ziyaret doğrulama fotoğrafı.',
        ), array( 'test_form'=>false ) );
        unset( $_FILES['mmc_school_photo_upload'] );
        return $id;
    }

    public static function maybe_render_portal() {
        if ( empty( $_GET['mmc_field_portal'] ) ) { return; }
        $raw = isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '';
        $ctx = self::validate_token( $raw );
        if ( ! $ctx ) { self::render_portal_error( 'Bağlantı geçersiz veya süresi dolmuş.' ); }
        $program = MMC_Program_Service::get_program( $ctx->program_id );
        if ( ! $program ) { self::render_portal_error( 'Program bulunamadı.' ); }

        $filters = array( 'limit'=>1500, 'exclude_skipped'=>1 );
        if ( $ctx->assigned_user_id ) { $filters['assigned_user_id'] = $ctx->assigned_user_id; }
        if ( $ctx->assigned_name ) { $filters['assigned_name'] = $ctx->assigned_name; }
        $targets = self::targets( $ctx->program_id, $filters );
        $routes = self::routes( $ctx->program_id, array( 'assigned_user_id'=>$ctx->assigned_user_id, 'assigned_name'=>$ctx->assigned_name ) );
        $summary = self::summary( $ctx->program_id );
        self::render_portal( $raw, $ctx, $program, $targets, $routes, $summary );
    }

    public static function handle_portal_visit() {
        $raw = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        $ctx = self::validate_token( $raw );
        if ( ! $ctx ) { wp_die( 'Saha bağlantısı geçersiz veya süresi dolmuş.' ); }
        $target_id = absint( $_POST['target_id'] ?? 0 );
        check_admin_referer( 'mmc_field_visit_' . $target_id, 'mmc_nonce' );
        $file = isset( $_FILES['school_photo'] ) ? $_FILES['school_photo'] : null;
        $result = self::save_visit( $target_id, $_POST, $file, array(
            'program_id'=>$ctx->program_id,
            'assigned_user_id'=>$ctx->assigned_user_id,
            'assigned_name'=>$ctx->assigned_name,
        ) );
        $args = array( 'mmc_field_portal'=>1, 'token'=>$raw );
        if ( is_wp_error( $result ) ) { $args['mmc_error']=$result->get_error_message(); }
        else { $args['mmc_saved']=1; }
        wp_safe_redirect( add_query_arg( $args, home_url( '/' ) ) );
        exit;
    }

    public static function maybe_export_public_kml() {
        if ( empty( $_GET['mmc_field_kml'] ) ) { return; }
        $raw = isset( $_GET['token'] ) ? wp_unslash( $_GET['token'] ) : '';
        $ctx = self::validate_token( $raw );
        if ( ! $ctx ) { status_header(403); exit('Geçersiz bağlantı.'); }
        $filters = array( 'limit'=>2000, 'exclude_skipped'=>1 );
        if ( $ctx->assigned_user_id ) { $filters['assigned_user_id']=$ctx->assigned_user_id; }
        if ( $ctx->assigned_name ) { $filters['assigned_name']=$ctx->assigned_name; }
        self::output_kml( $ctx->program_id, $filters );
    }

    public static function output_kml( $program_id, $filters = array() ) {
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { status_header(404); exit; }
        $targets = self::targets( $program_id, array_merge( array( 'limit'=>2000, 'exclude_skipped'=>1 ), $filters ) );
        $filename = sanitize_file_name( $program->program_code . '-okullar.kml' );
        nocache_headers();
        header( 'Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<kml xmlns="http://www.opengis.net/kml/2.2"><Document>';
        echo '<name>' . self::xml( $program->program_code . ' Okul Saha Listesi' ) . '</name>';
        foreach ( (array) $targets as $t ) {
            echo '<Placemark><name>' . self::xml( $t->school_name ) . '</name>';
            $desc = $t->district_name . ' | ' . ( $t->education_level ?: $t->school_type ) . ' | Durum: ' . ( self::target_statuses()[ $t->status ] ?? $t->status );
            if ( $t->assigned_name ) { $desc .= ' | Personel: ' . $t->assigned_name; }
            echo '<description>' . self::xml( $desc ) . '</description>';
            if ( $t->address ) { echo '<address>' . self::xml( $t->address . ', ' . $t->district_name . ', ' . $t->province_name ) . '</address>'; }
            if ( null !== $t->longitude && '' !== $t->longitude && null !== $t->latitude && '' !== $t->latitude ) {
                echo '<Point><coordinates>' . self::xml( $t->longitude . ',' . $t->latitude . ',0' ) . '</coordinates></Point>';
            }
            echo '</Placemark>';
        }
        echo '</Document></kml>';
        exit;
    }

    public static function on_program_log( $program_id, $action, $entity_type, $entity_id, $old, $new, $note ) {
        if ( in_array( $action, array( 'target_districts_updated' ), true ) ) {
            self::ensure_field_tasks( absint( $program_id ) );
        }
    }

    private static function ensure_field_tasks( $program_id ) {
        global $wpdb;
        $tasks = $wpdb->prefix . 'mmc_tasks';
        $existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tasks WHERE program_id=%d AND module='field' AND status<>'completed'", absint($program_id) ) );
        if ( $existing ) { return; }
        $now = current_time('mysql');
        $uid = get_current_user_id();
        foreach ( array(
            'Hedef okul havuzunu doğrula ve saha personeline dağıt',
            'KML / harita ve rota bağlantılarını hazırla',
            'Saha ziyaretlerini ve tabela fotoğraflarını takip et',
        ) as $title ) {
            $wpdb->insert( $tasks, array(
                'program_id'=>absint($program_id),'module'=>'field','title'=>$title,'status'=>'open','priority'=>'normal','created_by'=>$uid,'created_at'=>$now,'updated_at'=>$now
            ) );
        }
    }

    private static function render_portal( $raw, $ctx, $program, $targets, $routes, $summary ) {
        status_header(200); nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
        header( 'Referrer-Policy: no-referrer', true );
        $saved = ! empty( $_GET['mmc_saved'] );
        $error = isset( $_GET['mmc_error'] ) ? sanitize_text_field( wp_unslash( $_GET['mmc_error'] ) ) : '';
        $kml_url = add_query_arg( array( 'mmc_field_kml'=>1, 'token'=>$raw ), home_url( '/' ) );
        ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saha Portalı — <?php echo esc_html($program->program_code);?></title>
        <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;background:#f4f6f8;color:#17212b}.top{background:#102a43;color:#fff;padding:18px}.top h1{font-size:21px;margin:0}.top p{margin:5px 0 0;opacity:.85}.wrap{max-width:900px;margin:auto;padding:14px}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.card,.school,.route,.notice{background:#fff;border:1px solid #d8dee5;border-radius:12px;padding:14px;margin:10px 0}.card strong{font-size:24px;display:block}.card span{color:#68737e;font-size:13px}.school h2{font-size:17px;margin:0 0 5px}.meta{font-size:13px;color:#68737e}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#e9eef4;font-size:12px;font-weight:700}.btn{display:inline-block;background:#2271b1;color:#fff;text-decoration:none;border:0;border-radius:8px;padding:10px 13px;font-weight:700;cursor:pointer}.btn.alt{background:#44546a}.visit{margin-top:12px;padding-top:12px;border-top:1px solid #eee}.visit label{display:block;margin:8px 0;font-size:13px;font-weight:700}.visit input,.visit select,.visit textarea{width:100%;box-sizing:border-box;padding:9px;border:1px solid #c8d0d9;border-radius:7px;margin-top:4px}.notice.ok{border-left:5px solid #00a32a}.notice.err{border-left:5px solid #d63638}.route a{word-break:break-word}.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}@media(max-width:650px){.cards{grid-template-columns:1fr}.wrap{padding:10px}}</style></head><body>
        <div class="top"><h1>Madagaskar Saha Portalı</h1><p><?php echo esc_html($program->program_code.' · '.$program->province_name.' / '.$program->district_name);?><?php if($ctx->assigned_name):?> · <?php echo esc_html($ctx->assigned_name);?><?php endif;?></p></div><main class="wrap">
        <?php if($saved):?><div class="notice ok">Ziyaret kaydedildi.</div><?php endif;?><?php if($error):?><div class="notice err"><?php echo esc_html($error);?></div><?php endif;?>
        <div class="cards"><div class="card"><span>Hedef okul</span><strong><?php echo esc_html(count($targets));?></strong></div><div class="card"><span>Program ziyaret</span><strong><?php echo esc_html($summary['visited_schools']);?></strong></div><div class="card"><span>İlerleme</span><strong>%<?php echo esc_html($summary['visit_percent']);?></strong></div></div>
        <div class="toolbar"><a class="btn" href="<?php echo esc_url($kml_url);?>">KML Okul Listesini Al</a></div>
        <?php if($routes):?><section><h2>Rota Bağlantıları</h2><?php foreach($routes as $r):?><div class="route"><strong><?php echo esc_html($r->title);?></strong><div class="meta"><?php echo esc_html(strtoupper($r->route_app));?><?php if($r->district_name):?> · <?php echo esc_html($r->district_name);?><?php endif;?></div><p><a class="btn alt" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($r->external_url);?>">Rotayı Aç</a></p></div><?php endforeach;?></section><?php endif;?>
        <section><h2>Okullar</h2><?php if(!$targets):?><div class="notice">Bu bağlantıya atanmış okul bulunmuyor.</div><?php endif;?>
        <?php foreach($targets as $t):?><article class="school"><h2><?php echo esc_html($t->school_name);?></h2><div class="meta"><?php echo esc_html($t->district_name.' · '.($t->education_level?:$t->school_type));?><?php if($t->student_count_snapshot):?> · <?php echo esc_html(number_format_i18n($t->student_count_snapshot));?> öğrenci<?php endif;?></div><p><?php echo esc_html($t->address);?></p><span class="badge"><?php echo esc_html(self::target_statuses()[$t->status]??$t->status);?></span>
        <?php if($t->assigned_name):?> <span class="badge"><?php echo esc_html($t->assigned_name);?></span><?php endif;?>
        <form class="visit" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_field_portal_visit"><input type="hidden" name="token" value="<?php echo esc_attr($raw);?>"><input type="hidden" name="target_id" value="<?php echo esc_attr($t->id);?>"><?php wp_nonce_field('mmc_field_visit_'.$t->id,'mmc_nonce');?><label>Personel adı<input name="visitor_name" required value="<?php echo esc_attr($ctx->assigned_name);?>"></label><label>Sonuç<select name="visit_status"><option value="visited">Ziyaret edildi</option><option value="revisit">Tekrar ziyaret gerekli</option><option value="unavailable">Ulaşılamadı / kapalı</option><option value="refused">Tanıtım kabul edilmedi</option></select></label><label>Ziyaret tarihi/saat<input type="datetime-local" name="visited_at" value="<?php echo esc_attr(wp_date('Y-m-d\TH:i'));?>"></label><label>Fiilen ulaşılan öğrenci (varsa)<input type="number" min="0" name="students_reached" value="0"></label><label>Dağıtılan materyal adedi<input type="number" min="0" name="materials_delivered" value="0"></label><label>Okul tabela fotoğrafı <small>(Ziyaret edildi için zorunlu)</small><input type="file" name="school_photo" accept="image/jpeg,image/png,image/webp" capture="environment"></label><label>Not<textarea name="notes" rows="3"></textarea></label><button class="btn" type="submit">Ziyareti Kaydet</button></form></article><?php endforeach;?></section></main></body></html><?php
        exit;
    }

    private static function render_portal_error( $message ) {
        status_header(403); nocache_headers(); header('X-Robots-Tag: noindex, nofollow',true); ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saha Portalı</title></head><body style="font-family:system-ui;padding:30px;background:#f4f6f8"><div style="max-width:650px;margin:auto;background:#fff;padding:25px;border-radius:12px"><h1>Saha Portalı</h1><p><?php echo esc_html($message);?></p></div></body></html><?php exit;
    }

    private static function normalize_datetime( $v ) {
        $v = trim( sanitize_text_field( (string) $v ) );
        if ( ! $v ) { return null; }
        $ts = strtotime( $v );
        return $ts ? wp_date( 'Y-m-d H:i:s', $ts ) : null;
    }

    private static function xml( $v ) { return htmlspecialchars( (string)$v, ENT_XML1 | ENT_COMPAT, 'UTF-8' ); }
}
