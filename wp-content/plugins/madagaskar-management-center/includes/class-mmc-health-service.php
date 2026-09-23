<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Health_Service {
    public static function checks() {
        $checks = array();

        $checks[] = self::check(
            'wordpress',
            'WordPress',
            'ok',
            'WordPress ' . get_bloginfo( 'version' ) . ' çalışıyor.'
        );

        $checks[] = self::check(
            'woocommerce',
            'WooCommerce',
            class_exists( 'WooCommerce' ) ? 'ok' : 'critical',
            class_exists( 'WooCommerce' ) ? 'Kurulu ve aktif.' : 'Algılanmadı. Satış senkronu çalışmaz.'
        );

        $tickera = class_exists( 'TC' ) || defined( 'TC_VERSION' );
        $checks[] = self::check(
            'tickera',
            'Tickera',
            $tickera ? 'ok' : 'critical',
            $tickera ? 'Kurulu ve aktif.' : 'Algılanmadı. QR / bilet üretimi çalışmaz.'
        );

        $paytr = class_exists( 'MMC_Sales_Service' ) ? MMC_Sales_Service::paytr_gateway_status() : array( 'detected'=>false );
        $checks[] = self::check(
            'paytr',
            'PayTR',
            ! empty( $paytr['detected'] ) ? 'ok' : 'warning',
            ! empty( $paytr['detected'] ) ? 'WooCommerce ödeme geçidi algılandı.' : 'Henüz doğrulanmadı; ilk canlı/test siparişte kontrol edin.'
        );

        $mdg_venues = class_exists( 'MDG_Venues' );
        $venue_count = 0;
        if ( $mdg_venues ) {
            $venue_count = count( (array) MDG_Venues::all( true ) );
        }
        $checks[] = self::check(
            'venue_source',
            'Salon Ana Kaynağı',
            $mdg_venues ? 'ok' : 'warning',
            $mdg_venues
                ? sprintf( 'Madagaskar → Salonlar tek ana kaynak olarak bağlı. %d aktif salon kaydı okunuyor.', $venue_count )
                : 'Madagaskar → Salonlar kaynağı algılanmadı; MMC yalnız geriye uyumluluk kayıtlarını kullanabilir.',
            $mdg_venues ? admin_url( 'admin.php?page=mdg-venues' ) : '',
            $mdg_venues ? 'Salonları Aç' : ''
        );

        $school_source = class_exists( 'MMC_School_Source_Service' ) ? MMC_School_Source_Service::source_info( true ) : array( 'external'=>false, 'label'=>'Algılanmadı', 'menu_url'=>'' );
        $school_count = class_exists( 'MMC_School_Source_Service' ) ? MMC_School_Source_Service::count_all() : 0;
        $checks[] = self::check(
            'school_source',
            'Okul Ana Kaynağı',
            ! empty( $school_source['external'] ) ? 'ok' : 'warning',
            ! empty( $school_source['external'] )
                ? sprintf( 'Madagaskar → Okul Tanıtım tek ana kaynak olarak bağlı. %d aktif okul kaydı okunabiliyor.', $school_count )
                : "Okul Tanıtım kaynağı otomatik doğrulanamadı. MMC geriye uyumluluk okul cache'ini kullanıyor; okul ana listesini ikinci kez yönetmeyin.",
            ! empty( $school_source['menu_url'] ) ? $school_source['menu_url'] : admin_url( 'admin.php?page=mmc-field' ),
            ! empty( $school_source['menu_url'] ) ? 'Okul Tanıtımı Aç' : 'Okul / Saha'
        );

        $population = class_exists( 'MMC_Population_Source_Service' ) ? MMC_Population_Source_Service::info() : array( 'available'=>false,'ready'=>false,'year'=>0,'province_count'=>0,'district_count'=>0,'source'=>'','menu_url'=>'' );
        $checks[] = self::check(
            'population_source',
            'Nüfus Ana Kaynağı',
            ! empty( $population['ready'] ) ? 'ok' : ( ! empty( $population['available'] ) ? 'warning' : 'critical' ),
            ! empty( $population['ready'] )
                ? sprintf( '%d nüfus verisi hazır: %d/81 il ve %d/973 ilçe. MMC toplam nüfusu doğrudan bu kaynaktan okur.', (int)$population['year'], (int)$population['province_count'], (int)$population['district_count'] )
                : ( ! empty( $population['available'] )
                    ? sprintf( 'Nüfus eklentisi aktif ancak veri seti eksik: %d il, %d ilçe.', (int)$population['province_count'], (int)$population['district_count'] )
                    : 'Madagaskar Nüfus Verisi eklentisi algılanmadı; otomatik il/ilçe nüfusu kullanılamaz.' ),
            ! empty( $population['menu_url'] ) ? $population['menu_url'] : '',
            ! empty( $population['menu_url'] ) ? 'Nüfus Verisini Aç' : ''
        );

        $counts = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::warehouse_counts() : array( 'metrics'=>0,'schools'=>0,'provinces'=>0,'districts'=>0,'population_ready'=>false );
        $has_core_region_source = ! empty( $counts['population_ready'] ) || ! empty( $counts['metrics'] ) || ! empty( $counts['schools'] );
        $region_severity = ! $has_core_region_source ? 'critical' : ( empty( $counts['schools'] ) ? 'warning' : 'ok' );
        $region_detail = ! $has_core_region_source
            ? 'Nüfus, okul ve ek bölge metrikleri için kullanılabilir kaynak bulunamadı.'
            : sprintf( 'Nüfus: %s · Okul: %d kayıt · Ek metrik: %d.', ! empty($counts['population_ready']) ? ((int)$counts['population_year'] . ' otomatik') : 'manuel/eksik', (int)$counts['schools'], (int)$counts['metrics'] );
        $checks[] = self::check(
            'region_data',
            'Bölge Veri Kaynakları',
            $region_severity,
            $region_detail,
            admin_url( 'admin.php?page=mmc-region-data' ),
            'Veri Kaynaklarını Aç'
        );

        $kommo_ok = class_exists( 'MMC_Kommo_Service' ) && MMC_Kommo_Service::configured();
        $checks[] = self::check(
            'kommo',
            'Kommo API',
            $kommo_ok ? 'ok' : 'warning',
            $kommo_ok ? 'API yapılandırıldı.' : 'MMC çalışır; ancak Kommo otomatik senkronu için API yapılandırması bekliyor.',
            admin_url( 'admin.php?page=mmc-kommo' ),
            'Kommo Ayarları'
        );

        $next = class_exists( 'MMC_Report_Service' ) ? MMC_Report_Service::next_run() : false;
        $checks[] = self::check(
            'night_report',
            'Gece Raporu',
            $next ? 'ok' : 'warning',
            $next ? 'Sonraki çalışma: ' . wp_date( 'd.m.Y H:i', $next ) : 'Cron planı algılanmadı. Gece raporu otomatik gönderilmeyebilir.',
            admin_url( 'admin.php?page=mmc-night-reports' ),
            'Gece Raporları'
        );

        $table_check = self::database_tables();
        $checks[] = self::check(
            'database',
            'MMC Veritabanı',
            $table_check['missing'] ? 'critical' : 'ok',
            $table_check['missing'] ? 'Eksik tablolar: ' . implode( ', ', $table_check['missing'] ) : 'Temel MMC tabloları mevcut.'
        );

        foreach ( self::legacy_plugin_checks() as $legacy ) {
            $checks[] = $legacy;
        }

        return $checks;
    }

    public static function summary() {
        $out = array( 'critical'=>0, 'warning'=>0, 'ok'=>0, 'info'=>0 );
        foreach ( self::checks() as $check ) {
            $severity = $check['severity'];
            if ( isset( $out[ $severity ] ) ) {
                $out[ $severity ]++;
            }
        }
        return $out;
    }

    private static function database_tables() {
        global $wpdb;
        $suffixes = array(
            'mmc_programs', 'mmc_tasks', 'mmc_logs', 'mmc_region_metrics', 'mmc_schools',
            'mmc_program_target_districts', 'mmc_venues', 'mmc_program_venues', 'mmc_events',
            'mmc_sessions', 'mmc_ticket_types', 'mmc_finance_entries', 'mmc_sales_ledger',
            'mmc_program_target_schools', 'mmc_operation_plans', 'mmc_financial_closures', 'mmc_report_runs'
        );
        $missing = array();
        foreach ( $suffixes as $suffix ) {
            $table = $wpdb->prefix . $suffix;
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                $missing[] = $suffix;
            }
        }
        return array( 'missing'=>$missing );
    }

    private static function legacy_plugin_checks() {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $active  = (array) get_option( 'active_plugins', array() );
        $out = array();
        $legacy = array();
        $snippet_active = false;
        $custom_forms = null;

        foreach ( $plugins as $basename => $data ) {
            $name = (string) ( $data['Name'] ?? '' );
            $is_active = in_array( $basename, $active, true ) || ( is_multisite() && is_plugin_active_for_network( $basename ) );
            if ( ! $is_active ) {
                if ( false !== stripos( $name, 'Tickera' ) && false !== stripos( $name, 'Custom Forms' ) ) {
                    $custom_forms = array( 'name'=>$name, 'active'=>false );
                }
                continue;
            }

            if ( false !== stripos( $name, 'Code Snippets' ) || false !== stripos( $basename, 'code-snippets' ) ) {
                $snippet_active = true;
            }
            if ( false !== stripos( $name, 'Tickera' ) && false !== stripos( $name, 'Custom Forms' ) ) {
                $custom_forms = array( 'name'=>$name, 'active'=>true );
            }
            $is_mdg_ticket_core = false !== stripos( $name, 'Madagaskar Bilet Yönetimi' ) || class_exists( 'MDG_Venues' ) && false !== stripos( $basename, 'madagaskar' );
            $is_mmc_data_source = false !== stripos( $name, 'Nüfus Verisi' )
                || false !== stripos( $name, 'Nufus Verisi' )
                || false !== stripos( $name, 'Okul Tanıtım' )
                || false !== stripos( $name, 'Okul Tanitim' )
                || false !== stripos( $basename, 'madagaskar-population-data' );
            if ( ! $is_mdg_ticket_core && ! $is_mmc_data_source && ( preg_match( '/madagaskar|milano/i', $name ) || preg_match( '/madagaskar|milano/i', $basename ) ) && false === stripos( $name, 'Management Center' ) ) {
                $legacy[] = $name ?: $basename;
            }
        }

        if ( $legacy ) {
            $out[] = self::check(
                'legacy_plugins',
                'Eski Madagaskar Kodları',
                'warning',
                'MMC dışında aktif Madagaskar/Milano eklentileri bulundu: ' . implode( ', ', array_unique( $legacy ) ) . '. Aynı finans veya etkinlik verisini iki sistemin yazmadığını doğrulayın.'
            );
        } elseif ( $snippet_active ) {
            $out[] = self::check(
                'code_snippets',
                'Code Snippets',
                'warning',
                'Code Snippets aktif. Ekranda görünen “Madagaskar V5 Finans Güncellemesi” gibi eski snippet bildirimleri varsa, ilgili eski Madagaskar snippet’lerini tek tek kontrol edip MMC ile çakışanları test sonrası pasife alın.'
            );
        }

        if ( $custom_forms && empty( $custom_forms['active'] ) ) {
            $out[] = self::check(
                'tickera_custom_forms',
                'Tickera – Custom Forms',
                'info',
                'Eklenti kurulu fakat aktif değil. MMC çekirdeği için zorunlu değildir; yalnız özel katılımcı form alanlarına ihtiyacınız varsa etkinleştirin.'
            );
        }

        return $out;
    }

    private static function check( $id, $label, $severity, $detail, $action_url = '', $action_label = '' ) {
        return array(
            'id'           => sanitize_key( $id ),
            'label'        => sanitize_text_field( $label ),
            'severity'     => in_array( $severity, array( 'critical','warning','ok','info' ), true ) ? $severity : 'info',
            'detail'       => sanitize_text_field( $detail ),
            'action_url'   => esc_url_raw( $action_url ),
            'action_label' => sanitize_text_field( $action_label ),
        );
    }
}
