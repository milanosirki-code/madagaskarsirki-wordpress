<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Okul ana kaynağı köprüsü.
 *
 * Amaç: MMC'nin ayrı bir "okul ana veritabanı" yönetmesini önlemek. Var olan
 * "Okul Tanıtım" menüsündeki okul listesi mümkün olduğunda tek ana kaynak kabul
 * edilir. MMC yalnız saha/Program modülleri için salt-okunur cache ve program
 * snapshot'ı tutar.
 *
 * Kaynak sırası:
 * 1) `mmc_school_source_config` filtresi ile açık entegrasyon
 * 2) Önceden doğrulanmış option ayarı
 * 3) WordPress veritabanında güvenli otomatik tablo tespiti
 * 4) Geriye uyumluluk: wp_mmc_schools
 */
class MMC_School_Source_Service {
    const OPTION = 'mmc_school_source_config_v1';
    const TRANSIENT = 'mmc_school_source_detect_v1';

    public static function source_info( $refresh = false ) {
        $filtered = apply_filters( 'mmc_school_source_config', null );
        if ( is_array( $filtered ) && self::valid_config( $filtered ) ) {
            $filtered['external'] = true;
            $filtered['label'] = $filtered['label'] ?? 'Okul Tanıtım';
            $filtered['menu_url'] = $filtered['menu_url'] ?? self::school_menu_url();
            return $filtered;
        }

        $saved = get_option( self::OPTION, array() );
        if ( is_array( $saved ) && self::valid_config( $saved ) ) {
            $saved['external'] = true;
            $saved['label'] = $saved['label'] ?? 'Okul Tanıtım';
            $saved['menu_url'] = $saved['menu_url'] ?? self::school_menu_url();
            return $saved;
        }

        if ( ! $refresh ) {
            $cached = get_transient( self::TRANSIENT );
            if ( is_array( $cached ) && ! empty( $cached['type'] ) ) {
                return $cached;
            }
        }

        $detected = self::autodetect_table_source();
        if ( $detected ) {
            set_transient( self::TRANSIENT, $detected, 10 * MINUTE_IN_SECONDS );
            return $detected;
        }

        global $wpdb;
        $legacy = array(
            'type'       => 'legacy',
            'external'   => false,
            'label'      => 'MMC okul cache (geriye uyumluluk)',
            'table'      => $wpdb->prefix . 'mmc_schools',
            'mapping'    => self::legacy_mapping(),
            'menu_url'   => self::school_menu_url(),
            'confidence' => 0,
        );
        set_transient( self::TRANSIENT, $legacy, 10 * MINUTE_IN_SECONDS );
        return $legacy;
    }

    public static function refresh_detection() {
        delete_transient( self::TRANSIENT );
        return self::source_info( true );
    }

    public static function school_menu_url() {
        global $submenu, $menu;
        $patterns = array( '/okul\s*tan[ıi]t[ıi]m/iu', '/okul\s*y[oö]net/iu', '/okul\s*liste/iu' );
        if ( is_array( $submenu ) ) {
            foreach ( $submenu as $parent => $rows ) {
                foreach ( (array) $rows as $row ) {
                    $label = isset( $row[0] ) ? wp_strip_all_tags( (string) $row[0] ) : '';
                    $slug  = isset( $row[2] ) ? (string) $row[2] : '';
                    if ( ! $slug ) { continue; }
                    foreach ( $patterns as $pattern ) {
                        if ( preg_match( $pattern, $label ) ) {
                            return self::admin_slug_url( $slug );
                        }
                    }
                }
            }
        }
        if ( is_array( $menu ) ) {
            foreach ( $menu as $row ) {
                $label = isset( $row[0] ) ? wp_strip_all_tags( (string) $row[0] ) : '';
                $slug  = isset( $row[2] ) ? (string) $row[2] : '';
                foreach ( $patterns as $pattern ) {
                    if ( $slug && preg_match( $pattern, $label ) ) {
                        return self::admin_slug_url( $slug );
                    }
                }
            }
        }
        return '';
    }

    private static function admin_slug_url( $slug ) {
        if ( preg_match( '#^https?://#i', $slug ) ) { return $slug; }
        if ( false !== strpos( $slug, '.php' ) ) { return admin_url( ltrim( $slug, '/' ) ); }
        return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
    }

    public static function area_stats( $province, $districts ) {
        $rows = self::schools_for_area( $province, $districts );
        $stats = array(
            'school_count' => 0,
            'student_count' => 0,
            'student_known_rows' => 0,
            'district_school_coverage' => 0,
            'district_student_coverage' => 0,
            'district_counts' => array(),
            'district_student_rows' => array(),
        );
        foreach ( $rows as $row ) {
            if ( empty( $row->is_active ) ) { continue; }
            $d = (string) $row->district_name;
            $stats['school_count']++;
            $stats['district_counts'][ $d ] = ( $stats['district_counts'][ $d ] ?? 0 ) + 1;
            if ( null !== $row->student_count && '' !== (string) $row->student_count ) {
                $stats['student_count'] += (int) $row->student_count;
                $stats['student_known_rows']++;
                $stats['district_student_rows'][ $d ] = ( $stats['district_student_rows'][ $d ] ?? 0 ) + 1;
            }
        }
        $stats['district_school_coverage'] = count( $stats['district_counts'] );
        $stats['district_student_coverage'] = count( $stats['district_student_rows'] );
        return $stats;
    }

    public static function count_all() {
        $source = self::source_info();
        if ( empty( $source['external'] ) ) {
            global $wpdb;
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_schools WHERE is_active=1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $rows = self::rows_from_source( $source, '', array(), 50000 );
        return count( array_filter( $rows, function( $r ){ return ! empty( $r->is_active ); } ) );
    }

    public static function known_districts( $province ) {
        $province = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $province ) : sanitize_text_field( $province );
        if ( ! $province ) { return array(); }
        $source = self::source_info();
        if ( empty( $source['external'] ) ) { return array(); }
        $rows = self::rows_from_source( $source, $province, array(), 50000 );
        $out = array();
        foreach ( $rows as $row ) {
            if ( ! empty( $row->is_active ) && $row->district_name ) { $out[] = $row->district_name; }
        }
        $out = array_values( array_unique( array_filter( $out ) ) );
        natcasesort( $out );
        return array_values( $out );
    }

    public static function schools_for_area( $province, $districts ) {
        $province = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $province ) : sanitize_text_field( $province );
        $districts = array_values( array_filter( array_map( function( $v ) {
            return class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $v ) : sanitize_text_field( $v );
        }, (array) $districts ) ) );
        $source = self::source_info();
        return self::rows_from_source( $source, $province, $districts, 10000 );
    }

    /**
     * Okul Tanıtım kaynağını MMC'nin salt-okunur uyumluluk cache'ine yansıtır.
     * Bu cache yönetim kaynağı değildir; düzenleme yapılmaz. Saha modülünün mevcut
     * foreign-key benzeri okul_id ilişkisini bozmadan tek-kaynak ilkesini sağlar.
     */
    public static function refresh_cache_for_area( $province, $districts ) {
        global $wpdb;
        $source = self::source_info();
        if ( empty( $source['external'] ) ) {
            return array( 'source'=>$source, 'read'=>0, 'inserted'=>0, 'updated'=>0, 'external'=>false );
        }
        $rows = self::schools_for_area( $province, $districts );
        $table = $wpdb->prefix . 'mmc_schools';
        $now = current_time( 'mysql' );
        $inserted = $updated = 0;
        foreach ( $rows as $row ) {
            $stable = (string) ( $row->source_ref ?: $row->institution_code ?: ( $row->province_name . '|' . $row->district_name . '|' . $row->school_name ) );
            $cache_code = 'OKT-' . strtoupper( substr( sha1( (string)$source['table'] . '|' . $stable ), 0, 36 ) );
            $record = array(
                'institution_code' => $cache_code,
                'province_name'    => $row->province_name,
                'district_name'    => $row->district_name,
                'school_name'      => $row->school_name,
                'school_type'      => $row->school_type,
                'education_level'  => $row->education_level,
                'ownership'        => $row->ownership,
                'address'          => $row->address,
                'latitude'         => $row->latitude,
                'longitude'        => $row->longitude,
                'student_count'    => $row->student_count,
                'data_year'        => $row->data_year,
                'source_org'       => 'Okul Tanıtım',
                'source_url'       => $source['menu_url'] ?: '',
                'verified_at'      => $now,
                'is_active'        => ! empty( $row->is_active ) ? 1 : 0,
                'updated_at'       => $now,
            );
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE institution_code=%s LIMIT 1", $cache_code ) );
            if ( $existing ) {
                $ok = $wpdb->update( $table, $record, array( 'id'=>(int)$existing ) );
                if ( false !== $ok ) { $updated++; }
            } else {
                $record['created_at'] = $now;
                $ok = $wpdb->insert( $table, $record );
                if ( false !== $ok ) { $inserted++; }
            }
        }
        return array( 'source'=>$source, 'read'=>count($rows), 'inserted'=>$inserted, 'updated'=>$updated, 'external'=>true );
    }

    private static function rows_from_source( $source, $province, $districts, $limit ) {
        global $wpdb;
        if ( empty( $source['table'] ) || empty( $source['mapping'] ) ) { return array(); }
        $table = self::quote_identifier( $source['table'] );
        if ( ! $table ) { return array(); }
        $m = $source['mapping'];
        $select = array();
        foreach ( array( 'id','institution_code','province_name','district_name','school_name','school_type','education_level','ownership','address','latitude','longitude','student_count','data_year','active' ) as $key ) {
            if ( ! empty( $m[$key] ) ) {
                $col = self::quote_identifier( $m[$key] );
                if ( $col ) { $select[] = "$col AS `" . esc_sql( $key ) . "`"; }
            }
        }
        if ( empty( $m['school_name'] ) || empty( $m['province_name'] ) || empty( $m['district_name'] ) ) { return array(); }
        if ( ! $select ) { return array(); }

        $where = array();
        $params = array();
        if ( $province ) {
            $where[] = self::quote_identifier( $m['province_name'] ) . '=%s';
            $params[] = $province;
        }
        if ( $districts ) {
            $ph = implode( ',', array_fill( 0, count( $districts ), '%s' ) );
            $where[] = self::quote_identifier( $m['district_name'] ) . " IN ($ph)";
            $params = array_merge( $params, $districts );
        }
        $sql = 'SELECT ' . implode( ',', $select ) . " FROM $table" . ( $where ? ' WHERE ' . implode( ' AND ', $where ) : '' ) . ' LIMIT ' . max( 1, min( 50000, absint( $limit ) ) );
        if ( $params ) { $sql = $wpdb->prepare( $sql, $params ); }
        $raw = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $out = array();
        foreach ( (array) $raw as $r ) {
            $name = sanitize_text_field( $r->school_name ?? '' );
            $prov = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $r->province_name ?? '' ) : sanitize_text_field( $r->province_name ?? '' );
            $dist = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $r->district_name ?? '' ) : sanitize_text_field( $r->district_name ?? '' );
            if ( ! $name || ! $prov || ! $dist ) { continue; }
            $active = self::active_value( property_exists( $r, 'active' ) ? $r->active : 1 );
            $out[] = (object) array(
                'source_ref'       => sanitize_text_field( $r->id ?? '' ),
                'institution_code' => sanitize_text_field( $r->institution_code ?? '' ),
                'province_name'    => $prov,
                'district_name'    => $dist,
                'school_name'      => $name,
                'school_type'      => sanitize_text_field( $r->school_type ?? '' ),
                'education_level'  => sanitize_text_field( $r->education_level ?? '' ),
                'ownership'        => sanitize_text_field( $r->ownership ?? '' ),
                'address'          => sanitize_textarea_field( $r->address ?? '' ),
                'latitude'         => self::nullable_float( $r->latitude ?? null ),
                'longitude'        => self::nullable_float( $r->longitude ?? null ),
                'student_count'    => self::nullable_int( $r->student_count ?? null ),
                'data_year'        => sanitize_text_field( $r->data_year ?? '' ),
                'is_active'        => $active ? 1 : 0,
            );
        }
        return $out;
    }

    private static function autodetect_table_source() {
        global $wpdb;
        $tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $best = null;
        foreach ( (array) $tables as $table ) {
            if ( 0 !== strpos( $table, $wpdb->prefix ) ) { continue; }
            if ( false !== strpos( $table, $wpdb->prefix . 'mmc_' ) ) { continue; }
            $name_norm = self::norm( $table );
            $name_bonus = preg_match( '/okul|school|kurum|tanitim/', $name_norm ) ? 4 : 0;
            if ( ! $name_bonus ) { continue; }
            $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::quote_identifier( $table ), 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( ! $columns ) { continue; }
            $mapping = self::map_columns( $columns );
            if ( empty( $mapping['school_name'] ) || empty( $mapping['province_name'] ) || empty( $mapping['district_name'] ) ) { continue; }
            $score = $name_bonus + 12;
            foreach ( array( 'id','institution_code','address','latitude','longitude','student_count','data_year','active','school_type','education_level' ) as $k ) {
                if ( ! empty( $mapping[$k] ) ) { $score++; }
            }
            if ( ! $best || $score > $best['confidence'] ) {
                $best = array(
                    'type'       => 'table',
                    'external'   => true,
                    'label'      => 'Okul Tanıtım',
                    'table'      => $table,
                    'mapping'    => $mapping,
                    'menu_url'   => self::school_menu_url(),
                    'confidence' => $score,
                );
            }
        }
        return $best;
    }

    private static function map_columns( $columns ) {
        $aliases = array(
            'id'               => array( 'id','schoolid','okulid','kurumid','recordid' ),
            'institution_code' => array( 'institutioncode','kurumkodu','mebkodu','schoolcode','kurumno' ),
            'province_name'    => array( 'provincename','province','il','iladi','city','sehir','sehiradi' ),
            'district_name'    => array( 'districtname','district','ilce','ilceadi' ),
            'school_name'      => array( 'schoolname','okuladi','kurumadi','school','okul','kurum','name' ),
            'school_type'      => array( 'schooltype','okulturu','kurumturu','type','tur' ),
            'education_level'  => array( 'educationlevel','kademe','okulkademesi','level','egitimkademesi' ),
            'ownership'        => array( 'ownership','resmiozel','kurumtipi','ownershiptype','mulkiyet' ),
            'address'          => array( 'address','adres','acikadres','adresbilgisi' ),
            'latitude'         => array( 'latitude','lat','enlem' ),
            'longitude'        => array( 'longitude','lng','lon','boylam' ),
            'student_count'    => array( 'studentcount','students','ogrencisayisi','ogrenci','ogrenciadedi' ),
            'data_year'        => array( 'datayear','veriyili','yil','egitimogretimyili','donem' ),
            'active'           => array( 'isactive','active','aktif','durum','status' ),
        );
        $normalized = array();
        foreach ( (array) $columns as $col ) { $normalized[ self::norm( $col ) ] = $col; }
        $out = array();
        foreach ( $aliases as $key => $list ) {
            foreach ( $list as $alias ) {
                if ( isset( $normalized[ $alias ] ) ) { $out[ $key ] = $normalized[ $alias ]; break; }
            }
        }
        return $out;
    }

    private static function valid_config( $config ) {
        if ( empty( $config['table'] ) || empty( $config['mapping'] ) || ! is_array( $config['mapping'] ) ) { return false; }
        foreach ( array( 'school_name','province_name','district_name' ) as $key ) {
            if ( empty( $config['mapping'][$key] ) ) { return false; }
        }
        global $wpdb;
        $table = (string) $config['table'];
        if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) { return false; }
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    private static function legacy_mapping() {
        return array(
            'id'=>'id','institution_code'=>'institution_code','province_name'=>'province_name','district_name'=>'district_name',
            'school_name'=>'school_name','school_type'=>'school_type','education_level'=>'education_level','ownership'=>'ownership',
            'address'=>'address','latitude'=>'latitude','longitude'=>'longitude','student_count'=>'student_count','data_year'=>'data_year','active'=>'is_active',
        );
    }

    private static function quote_identifier( $identifier ) {
        $identifier = (string) $identifier;
        if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) { return ''; }
        return '`' . $identifier . '`';
    }

    private static function norm( $value ) {
        $value = remove_accents( strtolower( (string) $value ) );
        return preg_replace( '/[^a-z0-9]+/', '', $value );
    }

    private static function active_value( $value ) {
        $v = self::norm( (string) $value );
        if ( '' === $v ) { return true; }
        return ! in_array( $v, array( '0','false','hayir','no','inactive','pasif','silindi','arsiv','arsivde','closed' ), true );
    }

    private static function nullable_float( $value ) {
        if ( null === $value || '' === trim( (string) $value ) ) { return null; }
        $v = str_replace( ',', '.', trim( (string) $value ) );
        return is_numeric( $v ) ? (float) $v : null;
    }

    private static function nullable_int( $value ) {
        if ( null === $value || '' === trim( (string) $value ) ) { return null; }
        $v = preg_replace( '/[^0-9]/', '', (string) $value );
        return '' === $v ? null : absint( $v );
    }
}
