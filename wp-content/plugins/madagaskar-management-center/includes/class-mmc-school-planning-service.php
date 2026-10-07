<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Program bazlı okul kampüsü ve davetiye/bilet baskı planı.
 *
 * Okul ana verisi Okul Tanıtım kaynağında kalır. Bu servis yalnız MMC program
 * hedeflerini kampüs seviyesinde gruplar ve programa özel baskı snapshot'ını tutar.
 * Salt-okunur çağrılar tabloya veri yazmaz.
 */
class MMC_School_Planning_Service {

    public static function policy_table() {
        global $wpdb;
        return $wpdb->prefix . 'mmc_school_print_policies';
    }

    public static function plan_table() {
        global $wpdb;
        return $wpdb->prefix . 'mmc_school_print_plans';
    }

    public static function default_policy() {
        return (object) array(
            'program_id'           => 0,
            'distribution_percent' => 100.00,
            'reserve_percent'      => 0.00,
            'round_to'             => 10,
            'min_per_campus'       => 0,
            'max_per_campus'       => 0,
            'unknown_student_qty'  => 0,
            'notes'                => '',
        );
    }

    public static function policy( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! $program_id ) { return self::default_policy(); }
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::policy_table() . ' WHERE program_id=%d LIMIT 1',
            $program_id
        ) );
        if ( $row ) { return $row; }
        $default = self::default_policy();
        $default->program_id = $program_id;
        return $default;
    }

    public static function save_policy( $program_id, $data ) {
        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! MMC_Program_Service::get_program( $program_id ) ) {
            return new WP_Error( 'mmc_school_plan_program_missing', 'Program bulunamadı.' );
        }

        $now = current_time( 'mysql' );
        $record = array(
            'program_id'           => $program_id,
            'distribution_percent' => self::decimal_range( $data['distribution_percent'] ?? 100, 0, 300 ),
            'reserve_percent'      => self::decimal_range( $data['reserve_percent'] ?? 0, 0, 100 ),
            'round_to'             => self::int_range( $data['round_to'] ?? 10, 1, 1000 ),
            'min_per_campus'       => self::int_range( $data['min_per_campus'] ?? 0, 0, 100000 ),
            'max_per_campus'       => self::int_range( $data['max_per_campus'] ?? 0, 0, 100000 ),
            'unknown_student_qty'  => self::int_range( $data['unknown_student_qty'] ?? 0, 0, 100000 ),
            'notes'                => sanitize_textarea_field( wp_unslash( $data['notes'] ?? '' ) ),
            'updated_at'           => $now,
        );
        $existing = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM ' . self::policy_table() . ' WHERE program_id=%d LIMIT 1',
            $program_id
        ) );
        if ( $existing ) {
            $ok = $wpdb->update( self::policy_table(), $record, array( 'id'=>$existing ) );
        } else {
            $record['created_by'] = get_current_user_id() ?: null;
            $record['created_at'] = $now;
            $ok = $wpdb->insert( self::policy_table(), $record );
        }
        if ( false === $ok ) {
            return new WP_Error( 'mmc_school_plan_policy_save', 'Baskı politikası kaydedilemedi.' );
        }

        MMC_Program_Service::add_log(
            $program_id,
            'school_print_policy_saved',
            'school_print',
            $program_id,
            null,
            $record,
            'Okul davetiye/bilet baskı politikası güncellendi.'
        );
        return self::policy( $program_id );
    }

    /**
     * Programa bağlı okul hedeflerini fiziksel kampüs/ziyaret noktası seviyesinde gruplar.
     * Burada hiçbir veri yazılmaz.
     */
    public static function campus_rows( $program_id ) {
        $program_id = absint( $program_id );
        if ( ! $program_id || ! class_exists( 'MMC_Field_Service' ) ) { return array(); }

        $targets = MMC_Field_Service::targets( $program_id, array(
            'exclude_skipped' => true,
            'limit'            => 2000,
        ) );

        $groups = array();
        foreach ( (array) $targets as $row ) {
            $key = self::row_campus_key( $row );
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array(
                    'campus_key'             => $key,
                    'campus_name'            => self::row_campus_name( $row ),
                    'province_name'           => (string) $row->province_name,
                    'district_name'           => (string) $row->district_name,
                    'address'                 => (string) $row->address,
                    'phone'                   => (string) ( $row->phone ?? '' ),
                    'website'                 => (string) ( $row->website ?? '' ),
                    'component_names'         => array(),
                    'education_levels'        => array(),
                    'target_ids'              => array(),
                    'component_school_count'  => 0,
                    'student_count_snapshot'  => 0,
                    'student_known_count'     => 0,
                    'student_statuses'        => array(),
                    'student_source_urls'     => array(),
                    'priority'                => (string) ( $row->school_priority ?? $row->priority ?? '' ),
                );
            }
            $g =& $groups[ $key ];
            $g['component_school_count']++;
            $g['component_names'][] = (string) $row->school_name;
            $level = trim( (string) ( $row->education_level ?: $row->school_type ) );
            if ( $level ) { $g['education_levels'][] = $level; }
            $g['target_ids'][] = (int) $row->id;

            if ( null !== $row->student_count_snapshot && '' !== (string) $row->student_count_snapshot ) {
                $g['student_count_snapshot'] += absint( $row->student_count_snapshot );
                $g['student_known_count']++;
            }
            $status = sanitize_key( (string) ( $row->student_count_status ?? '' ) );
            if ( $status ) { $g['student_statuses'][] = $status; }
            $source_url = esc_url_raw( (string) ( $row->student_source_url ?? '' ) );
            if ( $source_url ) { $g['student_source_urls'][] = $source_url; }

            if ( ! $g['website'] && ! empty( $row->website ) ) { $g['website'] = (string) $row->website; }
            if ( ! $g['phone'] && ! empty( $row->phone ) ) { $g['phone'] = (string) $row->phone; }
            unset( $g );
        }

        $out = array();
        foreach ( $groups as $group ) {
            $group['component_names'] = array_values( array_unique( array_filter( $group['component_names'] ) ) );
            $group['education_levels'] = array_values( array_unique( array_filter( $group['education_levels'] ) ) );
            $group['student_statuses'] = array_values( array_unique( array_filter( $group['student_statuses'] ) ) );
            $group['student_source_urls'] = array_values( array_unique( array_filter( $group['student_source_urls'] ) ) );
            $group['target_ids'] = array_values( array_unique( array_filter( $group['target_ids'] ) ) );
            if ( 0 === $group['student_known_count'] ) {
                $group['student_count_snapshot'] = null;
            }
            if ( $group['component_school_count'] > 1 && false === stripos( $group['campus_name'], 'kamp' ) ) {
                $group['campus_name'] .= ' Kampüsü';
            }
            $out[] = (object) $group;
        }

        usort( $out, static function( $a, $b ) {
            $d = strnatcasecmp( $a->district_name, $b->district_name );
            return 0 !== $d ? $d : strnatcasecmp( $a->campus_name, $b->campus_name );
        } );
        return $out;
    }

    public static function sync_plan( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! MMC_Program_Service::get_program( $program_id ) ) {
            return new WP_Error( 'mmc_school_plan_program_missing', 'Program bulunamadı.' );
        }

        $policy = self::policy( $program_id );
        $campuses = self::campus_rows( $program_id );
        if ( ! $campuses ) {
            return new WP_Error( 'mmc_school_plan_no_targets', 'Önce Okul / Saha ekranında program hedef okullarını oluşturun.' );
        }

        $table = self::plan_table();
        $now = current_time( 'mysql' );
        $created = 0;
        $updated = 0;

        foreach ( $campuses as $campus ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM $table WHERE program_id=%d AND campus_key=%s LIMIT 1",
                $program_id,
                $campus->campus_key
            ) );
            $suggested = self::suggested_qty( $campus->student_count_snapshot, $policy );

            $record = array(
                'program_id'            => $program_id,
                'campus_key'            => $campus->campus_key,
                'campus_name'           => $campus->campus_name,
                'province_name'         => $campus->province_name,
                'district_name'         => $campus->district_name,
                'component_school_count'=> $campus->component_school_count,
                'component_names'       => implode( ' | ', $campus->component_names ),
                'student_count_snapshot'=> null === $campus->student_count_snapshot ? null : absint( $campus->student_count_snapshot ),
                'student_known_count'   => absint( $campus->student_known_count ),
                'suggested_qty'         => $suggested,
                'updated_at'            => $now,
            );

            if ( $existing ) {
                // Kullanıcı planned_qty alanını elle değiştirdiyse yeni öğrenci verisi
                // senkronunda bu manuel karar ezilmez. Eski plan öneriye eşitse otomatik izler.
                if ( (int) $existing->planned_qty === (int) $existing->suggested_qty ) {
                    $record['planned_qty'] = $suggested;
                }
                $ok = $wpdb->update( $table, $record, array( 'id'=>(int)$existing->id ) );
                if ( false !== $ok ) { $updated++; }
            } else {
                $record['planned_qty'] = $suggested;
                $record['printed_qty'] = 0;
                $record['distributed_qty'] = 0;
                $record['status'] = 'draft';
                $record['notes'] = '';
                $record['created_by'] = get_current_user_id() ?: null;
                $record['created_at'] = $now;
                $ok = $wpdb->insert( $table, $record );
                if ( false !== $ok ) { $created++; }
            }
        }

        MMC_Program_Service::add_log(
            $program_id,
            'school_print_plan_synced',
            'school_print',
            $program_id,
            null,
            array( 'campuses'=>count($campuses), 'created'=>$created, 'updated'=>$updated ),
            'Okul kampüsleri baskı planına senkronlandı.'
        );

        return array( 'campuses'=>count($campuses), 'created'=>$created, 'updated'=>$updated );
    }

    public static function plans( $program_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::plan_table() . ' WHERE program_id=%d ORDER BY district_name,campus_name',
            absint( $program_id )
        ) );
    }

    public static function save_plan_rows( $program_id, $rows ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $table = self::plan_table();
        $now = current_time( 'mysql' );
        $count = 0;

        foreach ( (array) $rows as $id => $data ) {
            $id = absint( $id );
            if ( ! $id || ! is_array( $data ) ) { continue; }
            $belongs = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE id=%d AND program_id=%d",
                $id,
                $program_id
            ) );
            if ( ! $belongs ) { continue; }

            $planned = self::int_range( $data['planned_qty'] ?? 0, 0, 1000000 );
            $printed = self::int_range( $data['printed_qty'] ?? 0, 0, 1000000 );
            $distributed = self::int_range( $data['distributed_qty'] ?? 0, 0, 1000000 );
            $status = 'draft';
            if ( $planned > 0 ) { $status = 'planned'; }
            if ( $printed > 0 ) { $status = 'printed'; }
            if ( $distributed > 0 ) { $status = 'distributed'; }

            $ok = $wpdb->update(
                $table,
                array(
                    'planned_qty'     => $planned,
                    'printed_qty'     => $printed,
                    'distributed_qty' => $distributed,
                    'status'          => $status,
                    'notes'           => sanitize_textarea_field( wp_unslash( $data['notes'] ?? '' ) ),
                    'updated_at'      => $now,
                ),
                array( 'id'=>$id, 'program_id'=>$program_id ),
                array( '%d','%d','%d','%s','%s','%s' ),
                array( '%d','%d' )
            );
            if ( false !== $ok ) { $count++; }
        }

        MMC_Program_Service::add_log(
            $program_id,
            'school_print_plan_updated',
            'school_print',
            $program_id,
            null,
            array( 'rows'=>$count ),
            'Okul davetiye/bilet baskı adetleri güncellendi.'
        );
        return $count;
    }

    public static function summary( $program_id ) {
        $plans = self::plans( $program_id );
        $out = array(
            'campus_count'    => count( $plans ),
            'student_count'   => 0,
            'known_campuses'  => 0,
            'unknown_campuses'=> 0,
            'suggested_qty'   => 0,
            'planned_qty'     => 0,
            'printed_qty'     => 0,
            'distributed_qty' => 0,
        );
        foreach ( $plans as $row ) {
            if ( null === $row->student_count_snapshot || '' === (string)$row->student_count_snapshot ) {
                $out['unknown_campuses']++;
            } else {
                $out['known_campuses']++;
                $out['student_count'] += (int) $row->student_count_snapshot;
            }
            $out['suggested_qty'] += (int) $row->suggested_qty;
            $out['planned_qty'] += (int) $row->planned_qty;
            $out['printed_qty'] += (int) $row->printed_qty;
            $out['distributed_qty'] += (int) $row->distributed_qty;
        }
        return $out;
    }

    public static function suggested_qty( $student_count, $policy ) {
        if ( null === $student_count || '' === (string) $student_count ) {
            return absint( $policy->unknown_student_qty ?? 0 );
        }
        $students = absint( $student_count );
        $percent = (float) ( $policy->distribution_percent ?? 100 ) + (float) ( $policy->reserve_percent ?? 0 );
        $qty = $students * max( 0, $percent ) / 100;
        $round_to = max( 1, absint( $policy->round_to ?? 10 ) );
        $qty = (int) ( ceil( $qty / $round_to ) * $round_to );
        $min = absint( $policy->min_per_campus ?? 0 );
        $max = absint( $policy->max_per_campus ?? 0 );
        if ( $min > 0 ) { $qty = max( $qty, $min ); }
        if ( $max > 0 ) { $qty = min( $qty, $max ); }
        return max( 0, $qty );
    }

    private static function row_campus_key( $row ) {
        $existing = sanitize_key( (string) ( $row->campus_key ?? '' ) );
        if ( $existing ) { return $existing; }
        $base = self::base_school_name( (string) $row->school_name );
        $address = self::norm( (string) $row->address );
        return 'cmp-' . substr( sha1( self::norm($row->province_name) . '|' . self::norm($row->district_name) . '|' . self::norm($base) . '|' . $address ), 0, 32 );
    }

    private static function row_campus_name( $row ) {
        $name = trim( (string) ( $row->campus_name ?? '' ) );
        return $name ?: self::base_school_name( (string) $row->school_name );
    }

    private static function base_school_name( $name ) {
        $name = trim( wp_strip_all_tags( $name ) );
        $patterns = array(
            '/\bANAOKULU\b/iu',
            '/\bİLKOKULU\b/iu',
            '/\bORTAOKULU\b/iu',
            '/\bİMAM\s+HATİP\b/iu',
            '/\bÖZEL\s+EĞİTİM\b/iu',
            '/\bGÜNDÜZ\s+BAKIMEVİ\b/iu',
            '/\bKREŞ(?:İ)?\b/iu',
        );
        $base = preg_replace( $patterns, ' ', $name );
        $base = trim( preg_replace( '/\s+/u', ' ', (string)$base ), " -–—,/" );
        return $base ?: $name;
    }

    private static function norm( $value ) {
        $value = remove_accents( strtolower( trim( (string)$value ) ) );
        return preg_replace( '/[^a-z0-9]+/', '', $value );
    }

    private static function decimal_range( $value, $min, $max ) {
        $value = str_replace( ',', '.', (string) $value );
        $n = is_numeric( $value ) ? (float) $value : 0;
        return min( $max, max( $min, $n ) );
    }

    private static function int_range( $value, $min, $max ) {
        return min( $max, max( $min, absint( $value ) ) );
    }
}
