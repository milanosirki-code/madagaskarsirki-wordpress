<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tek nüfus kaynağı köprüsü.
 *
 * Madagaskar 2025 Nüfus Verisi eklentisi aktifse MMC il/ilçe nüfusunu
 * doğrudan o eklentinin tabloları / yardımcı fonksiyonlarından okur.
 * MMC kendi mmc_region_metrics tablosuna population_total kopyalamaz.
 */
class MMC_Population_Source_Service {

    public static function available() {
        return function_exists( 'mmc_population_get_province' )
            && function_exists( 'mmc_population_get_district' )
            && function_exists( 'mmc_population_get_target_summary' );
    }

    public static function info() {
        global $wpdb;

        $province_table = $wpdb->prefix . 'mmc_population_provinces';
        $district_table = $wpdb->prefix . 'mmc_population_districts';
        $province_exists = self::table_exists( $province_table );
        $district_exists = self::table_exists( $district_table );

        $year = defined( 'MMC_POPULATION_DATA_YEAR' ) ? (int) MMC_POPULATION_DATA_YEAR : 0;
        if ( ! $year && $province_exists ) {
            $year = (int) $wpdb->get_var( "SELECT MAX(data_year) FROM {$province_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        $province_count = 0;
        $district_count = 0;
        if ( $province_exists && $year ) {
            $province_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$province_table} WHERE data_year=%d", $year ) );
        }
        if ( $district_exists && $year ) {
            $district_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$district_table} WHERE data_year=%d", $year ) );
        }

        $source = defined( 'MMC_POPULATION_SOURCE_NAME' ) ? (string) MMC_POPULATION_SOURCE_NAME : 'Madagaskar Nüfus Verisi';
        $ready = self::available() && 81 === $province_count && 973 === $district_count;

        return array(
            'available'      => self::available(),
            'ready'          => $ready,
            'year'           => $year,
            'source'         => $source,
            'province_count' => $province_count,
            'district_count' => $district_count,
            'menu_url'       => admin_url( 'admin.php?page=mmc-population-data' ),
        );
    }

    public static function ready() {
        $info = self::info();
        return ! empty( $info['ready'] );
    }

    public static function province( $province_name ) {
        if ( ! self::available() ) {
            return null;
        }
        $info = self::info();
        $year = (int) ( $info['year'] ?? 0 );
        if ( ! $year ) {
            return null;
        }
        $row = mmc_population_get_province( $province_name, $year );
        return is_array( $row ) ? $row : null;
    }

    public static function district( $province_name, $district_name ) {
        if ( ! self::available() ) {
            return null;
        }
        $info = self::info();
        $year = (int) ( $info['year'] ?? 0 );
        if ( ! $year ) {
            return null;
        }
        $row = mmc_population_get_district( $province_name, $district_name, $year );
        return is_array( $row ) ? $row : null;
    }

    public static function target_summary( $province_name, array $district_names ) {
        $info = self::info();
        if ( empty( $info['ready'] ) ) {
            return array(
                'province'               => $province_name,
                'data_year'              => (int) ( $info['year'] ?? 0 ),
                'target_district_count'  => count( $district_names ),
                'covered_district_count' => 0,
                'total_population'       => 0,
                'missing_districts'      => array_values( $district_names ),
                'districts'              => array(),
            );
        }
        $summary = mmc_population_get_target_summary( $province_name, $district_names, (int) $info['year'] );
        return is_array( $summary ) ? $summary : array();
    }

    public static function districts( $province_name ) {
        global $wpdb;
        $info = self::info();
        if ( empty( $info['ready'] ) ) {
            return array();
        }

        $table = $wpdb->prefix . 'mmc_population_districts';
        $province_key = function_exists( 'mmc_population_normalize_key' )
            ? mmc_population_normalize_key( $province_name )
            : self::normalize_key( $province_name );

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT name FROM {$table} WHERE province_name_key=%s AND data_year=%d ORDER BY name ASC",
                $province_key,
                (int) $info['year']
            )
        );
        return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
    }

    public static function all_districts() {
        global $wpdb;
        $info = self::info();
        if ( empty( $info['ready'] ) ) {
            return array();
        }
        $table = $wpdb->prefix . 'mmc_population_districts';
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT name FROM {$table} WHERE data_year=%d ORDER BY name ASC",
                (int) $info['year']
            )
        );
        return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
    }

    /**
     * Population eklentisinin eski DOM/JS köprüsünü MMC aktifken devreden çıkarır.
     * MMC v1.3.3 nüfusu PHP tarafında doğrudan okuduğu için bu köprü gerekli değildir.
     */
    public static function disable_external_ui_bridge() {
        if ( function_exists( 'mmc_population_bridge_enqueue' ) ) {
            remove_action( 'admin_enqueue_scripts', 'mmc_population_bridge_enqueue', 50 );
        }
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    private static function normalize_key( $value ) {
        $value = remove_accents( trim( (string) $value ) );
        $value = strtolower( $value );
        $value = preg_replace( '/[^a-z0-9]+/', '-', $value );
        return trim( (string) $value, '-' );
    }
}
