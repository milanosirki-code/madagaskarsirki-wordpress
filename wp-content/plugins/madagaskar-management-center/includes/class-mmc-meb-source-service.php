<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Bundled MEB 2024/25 province-level education source.
 *
 * The source file contains 81 provinces × 2 metrics (school_count/student_count).
 * District names are intentionally empty: province totals are reference values only
 * and are never distributed to districts.
 */
class MMC_MEB_Source_Service {
    const DATA_VERSION  = '2024-25-v1';
    const ACADEMIC_YEAR = '2024-2025';
    const SOURCE_ORG    = 'MEB';
    const SOURCE_NAME   = 'Millî Eğitim İstatistikleri, Örgün Eğitim 2024/25 - Tablo 1.17';
    const SOURCE_URL    = 'https://sgb.meb.gov.tr/';
    const DATASET_URL   = 'https://raw.githubusercontent.com/milanosirki-code/madagaskarsirki-wordpress/main/data-imports/meb-2024-25-province-metrics.csv';

    public static function dataset_path() {
        return MMC_DIR . 'data/meb-2024-25-province-metrics.csv';
    }

    public static function info() {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_region_metrics';
        $out = array(
            'ready'          => false,
            'academic_year'  => self::ACADEMIC_YEAR,
            'source_org'     => self::SOURCE_ORG,
            'source_name'    => self::SOURCE_NAME,
            'source_url'     => self::SOURCE_URL,
            'province_count' => 0,
            'metric_count'   => 0,
            'school_total'   => 0,
            'student_total'  => 0,
            'synced_at'      => '',
        );

        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return $out;
        }

        $where = $wpdb->prepare(
            "district_name='' AND data_year=%s AND source_org=%s AND source_name=%s AND metric_key IN ('school_count','student_count')",
            self::ACADEMIC_YEAR,
            self::SOURCE_ORG,
            self::SOURCE_NAME
        );

        $out['metric_count'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT CONCAT(province_name,'|',metric_key)) FROM $table WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out['province_count'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT province_name) FROM $table WHERE $where" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out['school_total'] = (int) round( (float) $wpdb->get_var( "SELECT SUM(metric_value) FROM $table WHERE $where AND metric_key='school_count'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out['student_total'] = (int) round( (float) $wpdb->get_var( "SELECT SUM(metric_value) FROM $table WHERE $where AND metric_key='student_count'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out['synced_at'] = (string) get_option( 'mmc_meb_source_synced_at', '' );
        $out['ready'] = 162 === $out['metric_count']
            && 81 === $out['province_count']
            && 74040 === $out['school_total']
            && 17956523 === $out['student_total'];

        return $out;
    }

    public static function ready() {
        $info = self::info();
        return ! empty( $info['ready'] );
    }

    public static function maybe_sync() {
        $version = (string) get_option( 'mmc_meb_source_version', '' );
        if ( self::DATA_VERSION === $version && self::ready() ) {
            return self::info();
        }
        return self::sync();
    }

    public static function sync() {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_region_metrics';
        $imports = $wpdb->prefix . 'mmc_imports';

        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return new WP_Error( 'mmc_meb_table', 'Bölge Veri Ambarı tablosu henüz hazır değil.' );
        }

        $rows = self::load_dataset();
        if ( is_wp_error( $rows ) ) {
            update_option( 'mmc_meb_source_last_error', $rows->get_error_message(), false );
            return $rows;
        }

        $now = current_time( 'mysql' );
        $created = 0;
        $updated = 0;
        $errors = array();
        $wpdb->query( 'START TRANSACTION' );

        try {
            foreach ( $rows as $row ) {
                $province = MMC_Region_Service::normalize_place_name( $row['province_name'] );
                $key = sanitize_key( $row['metric_key'] );
                $value = (float) $row['metric_value'];
                $unit = sanitize_text_field( $row['unit'] );

                $ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT id FROM $table
                     WHERE province_name=%s AND district_name='' AND metric_key=%s AND data_year=%s AND source_org=%s AND source_name=%s
                     ORDER BY id ASC",
                    $province,
                    $key,
                    self::ACADEMIC_YEAR,
                    self::SOURCE_ORG,
                    self::SOURCE_NAME
                ) );

                $record = array(
                    'province_name' => $province,
                    'district_name' => '',
                    'metric_key'    => $key,
                    'metric_value'  => $value,
                    'unit'          => $unit,
                    'data_year'     => self::ACADEMIC_YEAR,
                    'source_org'    => self::SOURCE_ORG,
                    'source_name'   => self::SOURCE_NAME,
                    'source_url'    => self::SOURCE_URL,
                    'data_level'    => 'province',
                    'verified_at'   => null,
                    'updated_at'    => $now,
                );

                if ( $ids ) {
                    $id = (int) array_shift( $ids );
                    $ok = $wpdb->update( $table, $record, array( 'id' => $id ) );
                    if ( false === $ok ) {
                        throw new RuntimeException( 'MEB metriği güncellenemedi: ' . $province . ' / ' . $key );
                    }
                    $updated++;

                    if ( $ids ) {
                        $id_list = implode( ',', array_map( 'absint', $ids ) );
                        $wpdb->query( "DELETE FROM $table WHERE id IN ($id_list)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    }
                } else {
                    $record['created_at'] = $now;
                    $ok = $wpdb->insert( $table, $record );
                    if ( false === $ok ) {
                        throw new RuntimeException( 'MEB metriği eklenemedi: ' . $province . ' / ' . $key );
                    }
                    $created++;
                }
            }

            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            $errors[] = $e->getMessage();
            update_option( 'mmc_meb_source_last_error', $e->getMessage(), false );
            return new WP_Error( 'mmc_meb_sync', $e->getMessage() );
        }

        update_option( 'mmc_meb_source_version', self::DATA_VERSION, false );
        update_option( 'mmc_meb_source_synced_at', $now, false );
        delete_option( 'mmc_meb_source_last_error' );

        $imports_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $imports ) );
        if ( $imports_exists === $imports ) {
            $wpdb->insert( $imports, array(
                'import_type'  => 'meb_auto',
                'filename'     => basename( self::dataset_path() ),
                'source_org'   => self::SOURCE_ORG,
                'data_year'    => self::ACADEMIC_YEAR,
                'rows_total'   => count( $rows ),
                'rows_success' => count( $rows ),
                'rows_error'   => 0,
                'error_log'    => null,
                'imported_by'  => get_current_user_id() ?: null,
                'imported_at'  => $now,
            ) );
        }

        return array_merge( self::info(), array(
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ) );
    }

    private static function load_dataset() {
        $path = self::dataset_path();
        $handle = false;

        if ( is_readable( $path ) ) {
            $handle = fopen( $path, 'r' );
        } else {
            $response = wp_remote_get( self::DATASET_URL, array(
                'timeout'     => 25,
                'redirection' => 3,
                'headers'     => array( 'Accept' => 'text/csv' ),
            ) );
            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'mmc_meb_remote', 'Paketlenmiş MEB veri dosyası bulunamadı ve GitHub kaynağına erişilemedi: ' . $response->get_error_message() );
            }
            if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                return new WP_Error( 'mmc_meb_remote_http', 'GitHub MEB veri kaynağı HTTP ' . (int) wp_remote_retrieve_response_code( $response ) . ' döndürdü.' );
            }
            $body = (string) wp_remote_retrieve_body( $response );
            if ( '' === trim( $body ) ) {
                return new WP_Error( 'mmc_meb_remote_empty', 'GitHub MEB veri kaynağı boş döndü.' );
            }
            $handle = fopen( 'php://temp', 'r+' );
            if ( $handle ) {
                fwrite( $handle, $body );
                rewind( $handle );
            }
        }

        if ( ! $handle ) {
            return new WP_Error( 'mmc_meb_read', 'MEB veri dosyası okunamadı.' );
        }

        $headers = fgetcsv( $handle );
        if ( ! $headers ) {
            fclose( $handle );
            return new WP_Error( 'mmc_meb_header', 'MEB veri dosyası başlığı okunamadı.' );
        }
        $headers = array_map( 'sanitize_key', $headers );

        $rows = array();
        $provinces = array();
        $school_total = 0;
        $student_total = 0;

        while ( ( $values = fgetcsv( $handle ) ) !== false ) {
            if ( count( $values ) < count( $headers ) ) {
                $values = array_pad( $values, count( $headers ), '' );
            }
            $row = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
            if ( ! $row ) {
                continue;
            }

            $province = MMC_Region_Service::normalize_place_name( $row['province_name'] ?? '' );
            $district = trim( (string) ( $row['district_name'] ?? '' ) );
            $key = sanitize_key( $row['metric_key'] ?? '' );
            $year = sanitize_text_field( $row['data_year'] ?? '' );
            $org = sanitize_text_field( $row['source_org'] ?? '' );
            $source_name = sanitize_text_field( $row['source_name'] ?? '' );
            $value = is_numeric( $row['metric_value'] ?? null ) ? (int) round( (float) $row['metric_value'] ) : -1;

            if ( ! $province || '' !== $district || ! in_array( $key, array( 'school_count', 'student_count' ), true ) ) {
                fclose( $handle );
                return new WP_Error( 'mmc_meb_shape', 'MEB veri setinde beklenmeyen ilçe veya metrik kaydı bulundu.' );
            }
            if ( self::ACADEMIC_YEAR !== $year || self::SOURCE_ORG !== $org || self::SOURCE_NAME !== $source_name || $value < 0 ) {
                fclose( $handle );
                return new WP_Error( 'mmc_meb_source', 'MEB veri seti kaynak/yıl doğrulamasını geçemedi.' );
            }

            $rows[] = array(
                'province_name' => $province,
                'metric_key'    => $key,
                'metric_value'  => $value,
                'unit'          => sanitize_text_field( $row['unit'] ?? ( 'school_count' === $key ? 'adet' : 'kisi' ) ),
            );
            $provinces[ $province ] = true;
            if ( 'school_count' === $key ) {
                $school_total += $value;
            } else {
                $student_total += $value;
            }
        }
        fclose( $handle );

        if ( 162 !== count( $rows ) || 81 !== count( $provinces ) || 74040 !== $school_total || 17956523 !== $student_total ) {
            return new WP_Error( 'mmc_meb_validation', 'MEB 2024/25 veri seti 81 il / ulusal toplam doğrulamasını geçemedi.' );
        }

        return $rows;
    }
}
