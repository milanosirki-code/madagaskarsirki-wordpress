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

        $tickera = self::tickera_status();
        $checks[] = self::check(
            'tickera',
            'Tickera',
            $tickera['detected'] ? 'ok' : ( $tickera['mapped'] ? 'warning' : 'critical' ),
            $tickera['detail'],
            $tickera['action_url'],
            $tickera['action_label']
        );

        $mapping = self::active_program_ticket_mapping();
        if ( $mapping['program_id'] ) {
            $checks[] = self::check(
                'ticket_chain',
                'WooCommerce → MDG → Tickera Zinciri',
                $mapping['severity'],
                $mapping['detail'],
                $mapping['action_url'],
                $mapping['action_label']
            );
        }

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

        if ( class_exists( 'MMC_Kommo_Service' ) ) {
            $kommo = MMC_Kommo_Service::connection_diagnostics();

            if ( ! empty( $kommo['connected'] ) ) {
                $account = ! empty( $kommo['account_name'] ) ? $kommo['account_name'] : $kommo['subdomain'];
                $detail = 'Canlı API bağlantısı başarılı';
                if ( $account ) {
                    $detail .= ': ' . $account;
                }
                if ( ! empty( $kommo['account_id'] ) ) {
                    $detail .= ' · Account ID ' . (int) $kommo['account_id'];
                }
                $detail .= ' · subdomain kaynağı: ' . ( $kommo['subdomain_source'] ?: 'MMC' ) . '.';

                $checks[] = self::check(
                    'kommo',
                    'Kommo API',
                    'ok',
                    $detail,
                    admin_url( 'admin.php?page=mmc-kommo' ),
                    'Kommo Ayarları'
                );
            } else {
                $detail = ! empty( $kommo['error'] )
                    ? 'Kommo bağlantısı doğrulanamadı: ' . $kommo['error']
                    : 'Kommo API henüz yapılandırılmadı. MMC program yönetimi çalışır; ancak otomatik CRM / AI senkronu beklemede kalır.';

                $checks[] = self::check(
                    'kommo',
                    'Kommo API',
                    'warning',
                    $detail,
                    admin_url( 'admin.php?page=mmc-kommo' ),
                    'Kommo Ayarları'
                );
            }

            if ( ! empty( $kommo['uses_legacy_token'] ) ) {
                $checks[] = self::check(
                    'kommo_secret_source',
                    'Kommo Secret Kaynağı',
                    'warning',
                    'Kommo bağlantısı legacy MS_KOMMO_TOKEN sabitinden çalışıyor. Canlı bağlantıyı kesmeden yeni/yenilenmiş tokenı wp-config.php içinde MMC_KOMMO_TOKEN olarak taşıyın; ardından eski snippet içindeki secretı kaldırın.',
                    admin_url( 'admin.php?page=mmc-kommo' ),
                    'Geçiş Bilgisi'
                );
            } elseif ( ! empty( $kommo['connected'] ) ) {
                $checks[] = self::check(
                    'kommo_secret_source',
                    'Kommo Secret Kaynağı',
                    'ok',
                    'Kommo tokenı MMC_KOMMO_TOKEN güvenli sabitinden okunuyor.'
                );
            }

            if ( ! empty( $kommo['connected'] ) && method_exists( 'MMC_Kommo_Service', 'program_pipeline_blueprint' ) ) {
                $blueprint = MMC_Kommo_Service::program_pipeline_blueprint();
                $mmc_pipeline = MMC_Kommo_Service::find_program_pipeline();

                if ( is_wp_error( $mmc_pipeline ) ) {
                    $checks[] = self::check(
                        'kommo_mmc_pipeline_schema',
                        'MMC Program Pipeline Şeması',
                        'warning',
                        'Kommo MMC pipeline şeması doğrulanamadı: ' . $mmc_pipeline->get_error_message(),
                        admin_url( 'admin.php?page=mmc-kommo' ),
                        'Kurulum Merkezi'
                    );
                } elseif ( ! $mmc_pipeline ) {
                    $checks[] = self::check(
                        'kommo_mmc_pipeline_schema',
                        'MMC Program Pipeline Şeması',
                        'info',
                        'MMC — Program Yönetimi pipeline henüz kurulmadı. Mevcut satış/WooCommerce pipeline’ları değiştirilmez.',
                        admin_url( 'admin.php?page=mmc-kommo' ),
                        'Kurulum Merkezi'
                    );
                } else {
                    $present = array();
                    foreach ( (array) ( $mmc_pipeline['statuses'] ?? array() ) as $status_row ) {
                        $present[] = remove_accents( strtolower( trim( (string) ( $status_row['name'] ?? '' ) ) ) );
                    }

                    $missing = array();
                    foreach ( (array) $blueprint['stages'] as $stage ) {
                        $key = remove_accents( strtolower( trim( (string) $stage['name'] ) ) );
                        if ( ! in_array( $key, $present, true ) ) {
                            $missing[] = $stage['name'];
                        }
                    }

                    $checks[] = self::check(
                        'kommo_mmc_pipeline_schema',
                        'MMC Program Pipeline Şeması',
                        $missing ? 'warning' : 'ok',
                        $missing
                            ? 'Pipeline #' . (int) $mmc_pipeline['id'] . ' bulundu; eksik MMC aşamaları: ' . implode( ', ', $missing ) . '.'
                            : 'Pipeline #' . (int) $mmc_pipeline['id'] . ' · ' . count( $blueprint['stages'] ) . '/' . count( $blueprint['stages'] ) . ' MMC program aşaması doğrulandı.',
                        admin_url( 'admin.php?page=mmc-kommo' ),
                        'Kurulum Merkezi'
                    );
                }
            }

            if ( ! empty( $kommo['connected'] ) ) {
                $pipeline = MMC_Kommo_Service::pipeline_diagnostics();

                if ( empty( $pipeline['configured'] ) ) {
                    $detail = 'MMC Program Pipeline ID henüz tanımlanmadı; CRM program lead senkronu atlanır, AI kaynak ve mevcut müşteri/sipariş akışları çalışmaya devam eder.';
                    if ( ! empty( $kommo['legacy_pipeline_id'] ) ) {
                        $detail .= ' Legacy sipariş pipeline adayı: #' . (int) $kommo['legacy_pipeline_id'] . ' (otomatik kullanılmaz).';
                    }
                    $checks[] = self::check(
                        'kommo_pipeline',
                        'Kommo Program Pipeline',
                        'info',
                        $detail,
                        admin_url( 'admin.php?page=mmc-kommo' ),
                        'Kommo Ayarları'
                    );
                } elseif ( empty( $pipeline['valid'] ) ) {
                    $checks[] = self::check(
                        'kommo_pipeline',
                        'Kommo Program Pipeline',
                        'warning',
                        'Tanımlı pipeline doğrulanamadı: ' . ( $pipeline['error'] ?: 'Kommo API yanıtı geçersiz.' ),
                        admin_url( 'admin.php?page=mmc-kommo' ),
                        'Kommo Ayarları'
                    );
                } else {
                    $detail = 'Pipeline #' . (int) $pipeline['pipeline_id'];
                    if ( ! empty( $pipeline['pipeline_name'] ) ) {
                        $detail .= ' · ' . $pipeline['pipeline_name'];
                    }
                    if ( ! empty( $pipeline['status_id'] ) ) {
                        $status_label = ! empty( $pipeline['status_name'] ) ? $pipeline['status_name'] . ' ' : '';
                        $detail .= ' · Status ' . $status_label . '#' . (int) $pipeline['status_id'] . ( false === $pipeline['status_valid'] ? ' (pipeline içinde bulunamadı)' : '' );
                    }
                    $checks[] = self::check(
                        'kommo_pipeline',
                        'Kommo Program Pipeline',
                        false === $pipeline['status_valid'] ? 'warning' : 'ok',
                        $detail,
                        admin_url( 'admin.php?page=mmc-kommo' ),
                        'Kommo Ayarları'
                    );
                }
            }
        }

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

    private static function tickera_status() {
        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $signals = array();
        if ( class_exists( 'TC' ) ) { $signals[] = 'TC sınıfı'; }
        if ( defined( 'TC_VERSION' ) ) { $signals[] = 'TC_VERSION'; }
        if ( function_exists( 'tc_get_ticket_count' ) ) { $signals[] = 'Tickera fonksiyonu'; }
        if ( post_type_exists( 'tc_events' ) || post_type_exists( 'tc_tickets' ) ) { $signals[] = 'Tickera post type'; }

        $plugins = get_plugins();
        $active = (array) get_option( 'active_plugins', array() );
        $active_names = array();

        foreach ( $plugins as $basename => $data ) {
            $is_active = in_array( $basename, $active, true ) || ( is_multisite() && is_plugin_active_for_network( $basename ) );
            if ( ! $is_active ) { continue; }

            $name = (string) ( $data['Name'] ?? '' );
            $looks_tickera = false !== stripos( $name, 'Tickera' ) || false !== stripos( $basename, 'tickera' );
            if ( $looks_tickera ) {
                $active_names[] = $name ?: $basename;
            }
        }

        if ( $active_names ) {
            $signals[] = 'aktif eklenti';
        }

        $mapping = self::active_program_ticket_mapping();
        $mapped = ! empty( $mapping['tickera_ids'] );

        if ( $mapped ) {
            $signals[] = 'aktif program mappingi';
        }

        $detected = ! empty( $signals ) && ( class_exists( 'TC' ) || defined( 'TC_VERSION' ) || function_exists( 'tc_get_ticket_count' ) || post_type_exists( 'tc_events' ) || post_type_exists( 'tc_tickets' ) || ! empty( $active_names ) );

        if ( $detected ) {
            $detail = 'Tickera algılandı';
            if ( $active_names ) {
                $detail .= ': ' . implode( ', ', array_slice( array_unique( $active_names ), 0, 3 ) );
            }
            if ( $mapped ) {
                $detail .= sprintf( ' · aktif programda %d Tickera event ID eşleşmesi var.', count( $mapping['tickera_ids'] ) );
            } else {
                $detail .= '.';
            }
            return array(
                'detected' => true,
                'mapped' => $mapped,
                'detail' => $detail,
                'action_url' => '',
                'action_label' => '',
            );
        }

        if ( $mapped ) {
            return array(
                'detected' => false,
                'mapped' => true,
                'detail' => sprintf( 'Aktif programda %d Tickera event ID eşleşmesi var; ancak Tickera çalışma zamanı sınıfı/eklenti sinyali algılanmadı. Eklenti aktivasyonunu doğrulayın.', count( $mapping['tickera_ids'] ) ),
                'action_url' => admin_url( 'plugins.php' ),
                'action_label' => 'Eklentileri Aç',
            );
        }

        return array(
            'detected' => false,
            'mapped' => false,
            'detail' => 'Tickera çalışma zamanı veya aktif eklenti sinyali algılanmadı ve aktif programda Tickera event eşleşmesi bulunamadı. QR / bilet üretimini doğrulayın.',
            'action_url' => admin_url( 'plugins.php' ),
            'action_label' => 'Eklentileri Aç',
        );
    }

    private static function active_program_ticket_mapping() {
        $program_id = class_exists( 'MMC_Integrity_Service' ) ? MMC_Integrity_Service::active_program_id() : 0;
        $out = array(
            'program_id' => (int) $program_id,
            'severity' => 'info',
            'detail' => '',
            'action_url' => '',
            'action_label' => '',
            'tickera_ids' => array(),
        );

        if ( ! $program_id || ! class_exists( 'MMC_MDG_Bridge_Service' ) ) {
            return $out;
        }

        $status = MMC_MDG_Bridge_Service::status( $program_id );
        if ( empty( $status['available'] ) ) {
            $out['severity'] = 'warning';
            $out['detail'] = 'Aktif program için eski MDG bilet motoru erişilebilir değil; WooCommerce / Tickera zinciri doğrulanamadı.';
            $out['action_url'] = admin_url( 'admin.php?page=mmc-integrity&program_id=' . (int)$program_id );
            $out['action_label'] = 'Bütünlüğü Aç';
            return $out;
        }

        $tickera_ids = array();
        global $wpdb;

        if ( ! empty( $status['event'] ) && class_exists( 'MDG_DB' ) && method_exists( 'MDG_DB', 'table' ) ) {
            $sessions_table = MDG_DB::table( 'sessions' );
            $rows = (array) $wpdb->get_results( $wpdb->prepare(
                "SELECT id,wc_product_id,tickera_event_id FROM {$sessions_table} WHERE event_id=%d ORDER BY id ASC",
                (int) $status['event']->id
            ) );
            foreach ( $rows as $row ) {
                if ( (int) $row->tickera_event_id ) {
                    $tickera_ids[] = (int) $row->tickera_event_id;
                }
            }
        }

        $tickera_ids = array_values( array_unique( $tickera_ids ) );
        $out['tickera_ids'] = $tickera_ids;

        $identity_ok = ! empty( $status['linked'] )
            && (int) $status['identity_expected'] > 0
            && (int) $status['identity_expected'] === (int) $status['identity_matched'];

        $session_ok = ! empty( $status['session_time_match'] )
            && (int) $status['sessions_mmc'] === (int) $status['sessions_mdg'];

        if ( $identity_ok && $session_ok && $tickera_ids ) {
            $out['severity'] = 'ok';
            $out['detail'] = sprintf(
                'MMC Program #%d ↔ MDG Event #%d bağlı · satış nesnesi eşleşmesi %d/%d · seans %d/%d · Tickera event ID: %s.',
                (int) $program_id,
                ! empty( $status['event']->id ) ? (int) $status['event']->id : 0,
                (int) $status['identity_matched'],
                (int) $status['identity_expected'],
                (int) $status['sessions_mdg'],
                (int) $status['sessions_mmc'],
                implode( ', ', $tickera_ids )
            );
        } else {
            $out['severity'] = 'warning';
            $out['detail'] = sprintf(
                'Aktif program zinciri tam doğrulanamadı: köprü %s · satış nesnesi %d/%d · seans %d/%d · Tickera event eşleşmesi %d.',
                ! empty( $status['linked'] ) ? 'bağlı' : 'eksik',
                (int) $status['identity_matched'],
                (int) $status['identity_expected'],
                (int) $status['sessions_mdg'],
                (int) $status['sessions_mmc'],
                count( $tickera_ids )
            );
        }

        $out['action_url'] = admin_url( 'admin.php?page=mmc-integrity&program_id=' . (int)$program_id );
        $out['action_label'] = 'Bütünlüğü Aç';
        return $out;
    }

    private static function active_madagaskar_snippets() {
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return array();
        }

        $columns = (array) $wpdb->get_col( "DESC {$table}", 0 );
        if ( ! in_array( 'name', $columns, true ) || ! in_array( 'code', $columns, true ) ) {
            return array();
        }

        $where_active = in_array( 'active', $columns, true ) ? 'active=1 AND ' : '';
        $like1 = '%' . $wpdb->esc_like( 'Madagaskar V5 Finans Güncellemesi' ) . '%';
        $like2 = '%' . $wpdb->esc_like( 'Web geliri artık program tarihine göre' ) . '%';
        $like3 = '%' . $wpdb->esc_like( 'Madagaskar' ) . '%';

        $sql = $wpdb->prepare(
            "SELECT id,name FROM {$table} WHERE {$where_active} ((name LIKE %s OR code LIKE %s OR code LIKE %s) OR name LIKE %s) ORDER BY id DESC LIMIT 25",
            $like1,
            $like1,
            $like2,
            $like3
        );

        return (array) $wpdb->get_results( $sql );
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
            if ( class_exists( 'MMC_Snippet_Inventory_Service' ) ) {
                $health = MMC_Snippet_Inventory_Service::health_check();
                $out[] = self::check(
                    'code_snippets',
                    'Code Snippets',
                    $health['severity'],
                    $health['detail'],
                    admin_url( 'admin.php?page=mmc-snippets' ),
                    'Snippet Envanteri'
                );
            } else {
                $out[] = self::check(
                    'code_snippets',
                    'Code Snippets',
                    'info',
                    'Code Snippets aktif. Ayrıntılı risk analizi için Snippet Envanteri servisi kullanılamadı.',
                    admin_url( 'admin.php?page=snippets' ),
                    'Snippetleri Aç'
                );
            }
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
