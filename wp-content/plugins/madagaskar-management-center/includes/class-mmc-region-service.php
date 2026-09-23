<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Region_Service {

    public static function turkey_provinces() {
        return array(
            'Adana','Adıyaman','Afyonkarahisar','Ağrı','Aksaray','Amasya','Ankara','Antalya','Ardahan','Artvin','Aydın',
            'Balıkesir','Bartın','Batman','Bayburt','Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı',
            'Çorum','Denizli','Diyarbakır','Düzce','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun',
            'Gümüşhane','Hakkâri','Hatay','Iğdır','Isparta','İstanbul','İzmir','Kahramanmaraş','Karabük','Karaman','Kars',
            'Kastamonu','Kayseri','Kırıkkale','Kırklareli','Kırşehir','Kilis','Kocaeli','Konya','Kütahya','Malatya','Manisa',
            'Mardin','Mersin','Muğla','Muş','Nevşehir','Niğde','Ordu','Osmaniye','Rize','Sakarya','Samsun','Siirt','Sinop',
            'Sivas','Şanlıurfa','Şırnak','Tekirdağ','Tokat','Trabzon','Tunceli','Uşak','Van','Yalova','Yozgat','Zonguldak'
        );
    }

    public static function normalize_place_name( $value ) {
        $value = trim( sanitize_text_field( (string) $value ) );
        if ( '' === $value ) {
            return '';
        }
        if ( function_exists( 'mb_convert_case' ) ) {
            $value = mb_convert_case( mb_strtolower( $value, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
        } else {
            $value = ucwords( strtolower( $value ) );
        }
        $fix = array(
            'Istanbul'=>'İstanbul','Izmir'=>'İzmir','Igdır'=>'Iğdır','Iğdır'=>'Iğdır','Sanlıurfa'=>'Şanlıurfa','Diyarbakır'=>'Diyarbakır',
            'Kahramanmaras'=>'Kahramanmaraş','Eskisehir'=>'Eskişehir','Kutahya'=>'Kütahya','Gumushane'=>'Gümüşhane','Cankırı'=>'Çankırı',
            'Corum'=>'Çorum','Canakkale'=>'Çanakkale','Kirikkale'=>'Kırıkkale','Kirklareli'=>'Kırklareli','Kirsehir'=>'Kırşehir',
            'Mugla'=>'Muğla','Mus'=>'Muş','Nigde'=>'Niğde','Sirnak'=>'Şırnak','Usak'=>'Uşak','Agri'=>'Ağrı','Aydin'=>'Aydın',
            'Bingol'=>'Bingöl','Elazig'=>'Elazığ','Duzce'=>'Düzce','Hakkari'=>'Hakkâri'
        );
        return $fix[ $value ] ?? $value;
    }

    public static function known_provinces() {
        global $wpdb;
        $metrics = $wpdb->prefix . 'mmc_region_metrics';
        $schools = $wpdb->prefix . 'mmc_schools';
        $programs = $wpdb->prefix . 'mmc_programs';
        $values = array();
        foreach ( array(
            "SELECT DISTINCT province_name FROM $metrics WHERE province_name<>''",
            "SELECT DISTINCT province_name FROM $schools WHERE province_name<>''",
            "SELECT DISTINCT province_name FROM $programs WHERE province_name<>''"
        ) as $sql ) {
            $values = array_merge( $values, (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $values = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_place_name' ), $values ) ) ) );
        natcasesort( $values );
        return array_values( $values );
    }

    public static function metric_labels() {
        return array(
            'population_total' => 'Toplam nüfus',
            'population_0_14'  => '0–14 yaş nüfusu',
            'population_15_24' => '15–24 yaş nüfusu',
            'school_count'     => 'Okul sayısı',
            'student_count'    => 'Öğrenci sayısı',
            'preschool_count'  => 'Okul öncesi öğrenci',
            'primary_count'    => 'İlkokul öğrenci',
            'middle_count'     => 'Ortaokul öğrenci',
            'high_count'       => 'Lise öğrenci',
        );
    }

    public static function warehouse_counts() {
        global $wpdb;
        $metrics = $wpdb->prefix . 'mmc_region_metrics';
        $schools = $wpdb->prefix . 'mmc_schools';
        $school_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $schools WHERE is_active=1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ( class_exists( 'MMC_School_Source_Service' ) ) {
            $src = MMC_School_Source_Service::source_info();
            if ( ! empty( $src['external'] ) ) {
                $school_count = MMC_School_Source_Service::count_all();
            }
        }

        $population = class_exists( 'MMC_Population_Source_Service' )
            ? MMC_Population_Source_Service::info()
            : array( 'ready'=>false, 'province_count'=>0, 'district_count'=>0, 'year'=>0, 'source'=>'' );

        $metric_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $metrics" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $metric_provinces = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT province_name) FROM $metrics" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $metric_districts = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT CONCAT(province_name,'|',district_name)) FROM $metrics WHERE district_name<>''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array(
            'metrics'              => $metric_count,
            'schools'              => $school_count,
            'provinces'            => ! empty( $population['ready'] ) ? (int) $population['province_count'] : $metric_provinces,
            'districts'            => ! empty( $population['ready'] ) ? (int) $population['district_count'] : $metric_districts,
            'population_ready'     => ! empty( $population['ready'] ),
            'population_year'      => (int) ( $population['year'] ?? 0 ),
            'population_source'    => (string) ( $population['source'] ?? '' ),
            'population_provinces' => (int) ( $population['province_count'] ?? 0 ),
            'population_districts' => (int) ( $population['district_count'] ?? 0 ),
        );
    }

    public static function add_metric( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_region_metrics';
        $province = self::normalize_place_name( $data['province_name'] ?? '' );
        $district = self::normalize_place_name( $data['district_name'] ?? '' );
        $key = sanitize_key( $data['metric_key'] ?? '' );
        $year = sanitize_text_field( $data['data_year'] ?? '' );
        $source_org = sanitize_text_field( $data['source_org'] ?? '' );
        if ( ! $province || ! $key || ! $year || ! $source_org ) {
            return new WP_Error( 'mmc_metric_required', 'İl, metrik, veri yılı ve kaynak kurum zorunludur.' );
        }
        if ( ! array_key_exists( $key, self::metric_labels() ) ) {
            return new WP_Error( 'mmc_metric_key', 'Desteklenmeyen metrik anahtarı.' );
        }
        if ( 'population_total' === $key && class_exists( 'MMC_Population_Source_Service' ) && MMC_Population_Source_Service::ready() ) {
            return new WP_Error( 'mmc_population_single_source', 'Toplam nüfus Madagaskar Nüfus Verisi eklentisinden otomatik gelir; MMC içine ikinci kez nüfus kaydı eklemeyin.' );
        }
        $now = current_time( 'mysql' );
        $verified = self::normalize_datetime( $data['verified_at'] ?? '' );
        $inserted = $wpdb->insert( $table, array(
            'province_name' => $province,
            'district_name' => $district,
            'metric_key'    => $key,
            'metric_value'  => self::number( $data['metric_value'] ?? 0 ),
            'unit'          => sanitize_text_field( $data['unit'] ?? 'adet' ),
            'data_year'     => $year,
            'source_org'    => $source_org,
            'source_name'   => sanitize_text_field( $data['source_name'] ?? '' ),
            'source_url'    => esc_url_raw( $data['source_url'] ?? '' ),
            'data_level'    => $district ? 'district' : 'province',
            'verified_at'   => $verified ?: null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ) );
        if ( false === $inserted ) {
            return new WP_Error( 'mmc_metric_insert', 'Metrik kaydı eklenemedi.' );
        }
        return (int) $wpdb->insert_id;
    }

    public static function known_districts( $province ) {
        global $wpdb;
        $metrics = $wpdb->prefix . 'mmc_region_metrics';
        $schools = $wpdb->prefix . 'mmc_schools';
        $province = self::normalize_place_name( $province );
        $a = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT district_name FROM $metrics WHERE province_name=%s AND district_name<>''", $province ) );
        $b = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT district_name FROM $schools WHERE province_name=%s AND district_name<>'' AND is_active=1", $province ) );
        $external = class_exists( 'MMC_School_Source_Service' ) ? MMC_School_Source_Service::known_districts( $province ) : array();
        $population = class_exists( 'MMC_Population_Source_Service' ) ? MMC_Population_Source_Service::districts( $province ) : array();
        $out = array_values( array_unique( array_filter( array_merge( $a, $b, $external, $population ) ) ) );
        natcasesort( $out );
        return array_values( $out );
    }

    public static function all_known_districts() {
        global $wpdb;
        $metrics = $wpdb->prefix . 'mmc_region_metrics';
        $schools = $wpdb->prefix . 'mmc_schools';
        $values = array();
        if ( class_exists( 'MMC_Population_Source_Service' ) ) {
            $values = array_merge( $values, MMC_Population_Source_Service::all_districts() );
        }
        $values = array_merge(
            $values,
            (array) $wpdb->get_col( "SELECT DISTINCT district_name FROM $metrics WHERE district_name<>''" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            (array) $wpdb->get_col( "SELECT DISTINCT district_name FROM $schools WHERE district_name<>'' AND is_active=1" ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        $out = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_place_name' ), $values ) ) ) );
        natcasesort( $out );
        return array_values( $out );
    }

    public static function set_program_targets( $program_id, $districts ) {
        global $wpdb;
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) {
            return new WP_Error( 'mmc_program_missing', 'Program bulunamadı.' );
        }
        $table = $wpdb->prefix . 'mmc_program_target_districts';
        $wpdb->delete( $table, array( 'program_id' => $program_id ), array( '%d' ) );
        $clean = array();
        foreach ( (array) $districts as $district ) {
            $district = self::normalize_place_name( $district );
            if ( $district ) {
                $clean[] = $district;
            }
        }
        if ( ! $clean && $program->district_name ) {
            $clean[] = $program->district_name;
        }
        $clean = array_values( array_unique( $clean ) );
        $now = current_time( 'mysql' );
        foreach ( $clean as $district ) {
            $population = class_exists( 'MMC_Population_Source_Service' ) && MMC_Population_Source_Service::ready()
                ? MMC_Population_Source_Service::district( $program->province_name, $district )
                : null;
            $source_info = class_exists( 'MMC_Population_Source_Service' ) ? MMC_Population_Source_Service::info() : array();
            $wpdb->insert( $table, array(
                'program_id'                 => $program_id,
                'province_name'              => $program->province_name,
                'district_name'              => $district,
                'is_primary'                 => (int) ( 0 === strcasecmp( $district, $program->district_name ) ),
                'population_snapshot'        => $population ? (int) $population['population'] : null,
                'population_year_snapshot'   => $population ? (int) $population['data_year'] : null,
                'population_source_snapshot' => $population ? (string) ( $population['source_name'] ?? ( $source_info['source'] ?? '' ) ) : '',
                'population_snapshot_at'     => $population ? $now : null,
                'created_by'                 => get_current_user_id(),
                'created_at'                 => $now,
            ) );
        }
        MMC_Program_Service::set_status( $program_id, 'region_analysis', 'Tanıtım havzası güncellendi; bölge analizi başladı.' );
        MMC_Program_Service::add_log( $program_id, 'target_districts_updated', 'program', $program_id, null, $clean, 'Tanıtım havzası ilçeleri güncellendi.' );
        return $clean;
    }

    public static function get_program_targets( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_program_target_districts';
        return $wpdb->get_col( $wpdb->prepare( "SELECT district_name FROM $table WHERE program_id=%d ORDER BY is_primary DESC,district_name ASC", $program_id ) );
    }

    public static function get_program_target_rows( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_program_target_districts';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE program_id=%d ORDER BY is_primary DESC,district_name ASC", $program_id ) );
    }

    public static function program_summary( $program_id ) {
        global $wpdb;
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) {
            return array();
        }
        $districts = self::get_program_targets( $program_id );
        if ( ! $districts && $program->district_name ) {
            $districts = array( $program->district_name );
        }

        $keys = array( 'population_total', 'population_0_14', 'population_15_24', 'school_count', 'student_count' );
        $totals = array_fill_keys( $keys, 0 );
        $coverage = array_fill_keys( $keys, 0 );
        $latest_years = array_fill_keys( $keys, array() );

        // Toplam nüfusun tek kaynağı: Madagaskar Nüfus Verisi eklentisi.
        // Program hedefi kaydedildiğinde nüfus snapshot'ı da tutulur; eski programlarda
        // snapshot yoksa canlı otomatik kaynak okunur.
        $population_source = array();
        $population_snapshot_used = false;
        $auto_population = class_exists( 'MMC_Population_Source_Service' ) && MMC_Population_Source_Service::ready();
        $target_rows = self::get_program_target_rows( $program_id );
        if ( $districts && $target_rows ) {
            $snapshot_total = 0;
            $snapshot_coverage = 0;
            $snapshot_years = array();
            foreach ( $target_rows as $target_row ) {
                if ( null !== $target_row->population_snapshot && '' !== (string) $target_row->population_snapshot ) {
                    $snapshot_total += (int) $target_row->population_snapshot;
                    $snapshot_coverage++;
                    if ( ! empty( $target_row->population_year_snapshot ) ) {
                        $snapshot_years[] = (string) $target_row->population_year_snapshot;
                    }
                }
            }
            if ( $snapshot_coverage > 0 ) {
                $totals['population_total'] = (float) $snapshot_total;
                $coverage['population_total'] = $snapshot_coverage;
                $latest_years['population_total'] = $snapshot_years;
                $population_snapshot_used = true;
            }
        }
        if ( ! $population_snapshot_used && $auto_population && $districts ) {
            $population_source = MMC_Population_Source_Service::target_summary( $program->province_name, $districts );
            $totals['population_total'] = (float) ( $population_source['total_population'] ?? 0 );
            $coverage['population_total'] = (int) ( $population_source['covered_district_count'] ?? 0 );
            $year = (string) ( $population_source['data_year'] ?? '' );
            if ( $year && $coverage['population_total'] ) {
                $latest_years['population_total'] = array_fill( 0, $coverage['population_total'], $year );
            }
        }

        // 0–14, 15–24 ve eğitim metrikleri yalnız doğrulanmış ek kaynaklardan gelir.
        foreach ( $districts as $district ) {
            foreach ( $keys as $key ) {
                if ( $auto_population && 'population_total' === $key ) {
                    continue;
                }
                $metric = self::latest_metric( $program->province_name, $district, $key );
                if ( $metric ) {
                    $totals[ $key ] += (float) $metric->metric_value;
                    $coverage[ $key ]++;
                    $latest_years[ $key ][] = $metric->data_year;
                }
            }
        }

        $schools_table = $wpdb->prefix . 'mmc_schools';
        $school_rows = 0;
        $school_source = null;
        if ( $districts && class_exists( 'MMC_School_Source_Service' ) ) {
            $src = MMC_School_Source_Service::source_info();
            if ( ! empty( $src['external'] ) ) {
                $school_source = MMC_School_Source_Service::area_stats( $program->province_name, $districts );
                $school_rows = (int) $school_source['school_count'];
                // Okul listesi için tek ana kaynak Okul Tanıtım'dır.
                $totals['school_count'] = (float) $school_source['school_count'];
                $coverage['school_count'] = (int) $school_source['district_school_coverage'];
                if ( ! empty( $school_source['student_known_rows'] ) ) {
                    $totals['student_count'] = (float) $school_source['student_count'];
                    $coverage['student_count'] = (int) $school_source['district_student_coverage'];
                }
            }
        }
        if ( ! $school_source && $districts ) {
            $placeholders = implode( ',', array_fill( 0, count( $districts ), '%s' ) );
            $params = array_merge( array( $program->province_name ), $districts );
            $school_rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $schools_table WHERE province_name=%s AND district_name IN ($placeholders) AND is_active=1", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $province = array();
        foreach ( $keys as $key ) {
            if ( $auto_population && 'population_total' === $key ) {
                $row = MMC_Population_Source_Service::province( $program->province_name );
                $info = MMC_Population_Source_Service::info();
                $province[ $key ] = $row ? (object) array(
                    'metric_value' => (float) $row['population'],
                    'data_year'    => (string) $row['data_year'],
                    'source_org'   => (string) ( $row['source_name'] ?? $info['source'] ),
                ) : null;
            } else {
                $province[ $key ] = self::latest_metric( $program->province_name, '', $key );
            }
        }

        return array(
            'program'           => $program,
            'districts'         => $districts,
            'totals'            => $totals,
            'coverage'          => $coverage,
            'district_total'    => count( $districts ),
            'years'             => $latest_years,
            'school_rows'       => $school_rows,
            'school_source'     => class_exists('MMC_School_Source_Service') ? MMC_School_Source_Service::source_info() : array(),
            'population_source' => $auto_population ? MMC_Population_Source_Service::info() : array( 'ready'=>false ),
            'population_detail' => $population_source,
            'population_snapshot_used' => $population_snapshot_used,
            'province'          => $province,
        );
    }

    public static function recent_imports( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_imports';
        $limit = max( 1, min( 50, absint( $limit ) ) );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY imported_at DESC,id DESC LIMIT %d", $limit ) );
    }

    public static function import_metrics_csv( $file ) {
        return self::import_csv( $file, 'region_metrics' );
    }

    public static function import_schools_csv( $file ) {
        return self::import_csv( $file, 'schools' );
    }

    private static function import_csv( $file, $type ) {
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'mmc_upload_missing', 'CSV dosyası bulunamadı.' );
        }
        $ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
        if ( 'csv' !== $ext ) {
            return new WP_Error( 'mmc_upload_type', 'Yalnız CSV dosyası kabul edilir.' );
        }
        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'mmc_upload_read', 'CSV dosyası okunamadı.' );
        }
        $first = fgets( $handle );
        if ( false === $first ) {
            fclose( $handle );
            return new WP_Error( 'mmc_upload_empty', 'CSV dosyası boş.' );
        }
        $delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';
        rewind( $handle );
        $headers = fgetcsv( $handle, 0, $delimiter );
        $headers = array_map( array( __CLASS__, 'clean_header' ), (array) $headers );
        $total = $success = $errors = 0;
        $error_log = array();
        $source_org = '';
        $data_year = '';
        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            if ( count( array_filter( $row, 'strlen' ) ) === 0 ) {
                continue;
            }
            $total++;
            $row = array_pad( $row, count( $headers ), '' );
            $data = array_combine( $headers, array_slice( $row, 0, count( $headers ) ) );
            if ( ! is_array( $data ) ) {
                $errors++;
                $error_log[] = "Satır $total: kolon eşleşmesi yapılamadı.";
                continue;
            }
            $source_org = $source_org ?: sanitize_text_field( $data['source_org'] ?? '' );
            $data_year  = $data_year ?: sanitize_text_field( $data['data_year'] ?? '' );
            $result = ( 'region_metrics' === $type ) ? self::add_metric( $data ) : self::add_school( $data );
            if ( is_wp_error( $result ) ) {
                $errors++;
                if ( count( $error_log ) < 50 ) {
                    $error_log[] = "Satır $total: " . $result->get_error_message();
                }
            } else {
                $success++;
            }
        }
        fclose( $handle );
        self::record_import( $type, $file['name'] ?? '', $source_org, $data_year, $total, $success, $errors, $error_log );
        return array( 'total' => $total, 'success' => $success, 'errors' => $errors );
    }

    private static function add_school( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_schools';
        $province = self::normalize_place_name( $data['province_name'] ?? '' );
        $district = self::normalize_place_name( $data['district_name'] ?? '' );
        $name = sanitize_text_field( $data['school_name'] ?? '' );
        if ( ! $province || ! $district || ! $name ) {
            return new WP_Error( 'mmc_school_required', 'İl, ilçe ve okul adı zorunludur.' );
        }
        $code = sanitize_text_field( $data['institution_code'] ?? '' );
        $now = current_time( 'mysql' );
        $record = array(
            'institution_code' => $code,
            'province_name'    => $province,
            'district_name'    => $district,
            'school_name'      => $name,
            'school_type'      => sanitize_text_field( $data['school_type'] ?? '' ),
            'education_level'  => sanitize_text_field( $data['education_level'] ?? '' ),
            'ownership'        => sanitize_text_field( $data['ownership'] ?? '' ),
            'address'          => sanitize_textarea_field( $data['address'] ?? '' ),
            'latitude'         => self::nullable_number( $data['latitude'] ?? '' ),
            'longitude'        => self::nullable_number( $data['longitude'] ?? '' ),
            'student_count'    => '' === trim( (string) ( $data['student_count'] ?? '' ) ) ? null : absint( round( self::number( $data['student_count'] ) ) ),
            'data_year'        => sanitize_text_field( $data['data_year'] ?? '' ),
            'source_org'       => sanitize_text_field( $data['source_org'] ?? 'MEB' ),
            'source_url'       => esc_url_raw( $data['source_url'] ?? '' ),
            'verified_at'      => self::normalize_datetime( $data['verified_at'] ?? '' ) ?: null,
            'is_active'        => isset( $data['active'] ) ? (int) ! in_array( strtolower( trim( (string) $data['active'] ) ), array( '0','false','hayır','hayir','no' ), true ) : 1,
            'updated_at'       => $now,
        );
        if ( $code ) {
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE institution_code=%s LIMIT 1", $code ) );
        } else {
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE province_name=%s AND district_name=%s AND school_name=%s LIMIT 1", $province, $district, $name ) );
        }
        if ( $existing ) {
            $ok = $wpdb->update( $table, $record, array( 'id' => (int) $existing ) );
            return false === $ok ? new WP_Error( 'mmc_school_update', 'Okul kaydı güncellenemedi.' ) : (int) $existing;
        }
        $record['created_at'] = $now;
        $ok = $wpdb->insert( $table, $record );
        return false === $ok ? new WP_Error( 'mmc_school_insert', 'Okul kaydı eklenemedi.' ) : (int) $wpdb->insert_id;
    }

    private static function latest_metric( $province, $district, $key ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_region_metrics';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table WHERE province_name=%s AND district_name=%s AND metric_key=%s ORDER BY data_year DESC, COALESCE(verified_at,updated_at) DESC,id DESC LIMIT 1",
            $province, $district, $key
        ) );
    }

    private static function record_import( $type, $filename, $source_org, $data_year, $total, $success, $errors, $error_log ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_imports';
        $wpdb->insert( $table, array(
            'import_type'  => $type,
            'filename'     => sanitize_file_name( $filename ),
            'source_org'   => sanitize_text_field( $source_org ),
            'data_year'    => sanitize_text_field( $data_year ),
            'rows_total'   => $total,
            'rows_success' => $success,
            'rows_error'   => $errors,
            'error_log'    => $error_log ? wp_json_encode( $error_log, JSON_UNESCAPED_UNICODE ) : null,
            'imported_by'  => get_current_user_id() ?: null,
            'imported_at'  => current_time( 'mysql' ),
        ) );
    }

    private static function clean_header( $header ) {
        $header = trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header ) );
        return sanitize_key( $header );
    }

    private static function number( $value ) {
        $value = str_replace( array( ' ', "\xc2\xa0" ), '', (string) $value );
        if ( false !== strpos( $value, ',' ) && false === strpos( $value, '.' ) ) {
            $value = str_replace( ',', '.', $value );
        } else {
            $value = str_replace( ',', '', $value );
        }
        return is_numeric( $value ) ? (float) $value : 0;
    }

    private static function nullable_number( $value ) {
        return '' === trim( (string) $value ) ? null : self::number( $value );
    }

    private static function normalize_datetime( $value ) {
        $value = trim( (string) $value );
        if ( ! $value ) {
            return '';
        }
        $ts = strtotime( $value );
        return $ts ? wp_date( 'Y-m-d H:i:s', $ts ) : '';
    }
}
