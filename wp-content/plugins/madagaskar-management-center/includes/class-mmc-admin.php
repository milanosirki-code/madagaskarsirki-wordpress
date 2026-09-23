<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_mmc_create_program', array( $this, 'handle_create_program' ) );
        add_action( 'admin_post_mmc_add_metric', array( $this, 'handle_add_metric' ) );
        add_action( 'admin_post_mmc_import_metrics', array( $this, 'handle_import_metrics' ) );
        add_action( 'admin_post_mmc_import_schools', array( $this, 'handle_import_schools' ) );
        add_action( 'admin_post_mmc_save_targets', array( $this, 'handle_save_targets' ) );
    }

    public function menu() {
        add_menu_page(
            'Madagaskar Yönetim Merkezi',
            'Madagaskar',
            'mmc_view_dashboard',
            'mmc-dashboard',
            array( $this, 'dashboard_page' ),
            'dashicons-tickets-alt',
            3
        );

        add_submenu_page( 'mmc-dashboard', 'Dashboard', 'Dashboard', 'mmc_view_dashboard', 'mmc-dashboard', array( $this, 'dashboard_page' ) );
        add_submenu_page( 'mmc-dashboard', 'Programlar', 'Programlar', 'mmc_view_programs', 'mmc-programs', array( $this, 'programs_page' ) );
        add_submenu_page( 'mmc-dashboard', 'Hazırlık Dashboardu', 'Hazırlık Dashboardu', 'mmc_view_programs', 'mmc-preparation', array( $this, 'preparation_page' ) );
        add_submenu_page( 'mmc-dashboard', 'Bölge Veri Ambarı', 'Bölge Veri Ambarı', 'mmc_manage_region_data', 'mmc-region-data', array( $this, 'region_data_page' ) );
        add_submenu_page( 'mmc-dashboard', 'İş Akışı', 'İş Akışı', 'mmc_view_programs', 'mmc-workflow', array( $this, 'workflow_page' ) );
        add_submenu_page( 'mmc-dashboard', 'Yetkiler', 'Yetkiler', 'mmc_manage_settings', 'mmc-roles', array( $this, 'roles_page' ) );
        add_submenu_page( 'mmc-dashboard', 'Kurulum & Sağlık', 'Kurulum & Sağlık', 'mmc_manage_settings', 'mmc-system', array( $this, 'system_page' ) );
    }

    public function assets( $hook ) {
        if ( false === strpos( $hook, 'mmc-' ) && false === strpos( $hook, 'toplevel_page_mmc-dashboard' ) ) {
            return;
        }
        wp_enqueue_style( 'mmc-admin', MMC_URL . 'assets/admin.css', array(), MMC_VERSION );
    }

    public function dashboard_page() {
        $this->guard( 'mmc_view_dashboard' );
        MMC_Dashboard_Admin::render();
    }

    public function programs_page() {
        $this->guard( 'mmc_view_programs' );
        $programs = MMC_Program_Service::all_programs();
        $statuses = MMC_Program_Service::statuses();
        ?>
        <div class="wrap mmc-wrap">
            <h1>Programlar</h1>
            <?php $this->notice_from_query(); ?>
            <?php $warehouse = MMC_Region_Service::warehouse_counts(); if ( empty( $warehouse['population_ready'] ) && empty( $warehouse['metrics'] ) && empty( $warehouse['schools'] ) ) : ?>
                <div class="notice notice-warning inline mmc-readiness-notice"><p><strong>Bölgesel kaynaklar henüz hazır değil.</strong> Program dosyası açabilirsiniz; ancak nüfus, okul ve öğrenci analizi kaynaklar bağlanana kadar eksik görünür. <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=mmc-region-data' ) ); ?>">Veri Kaynaklarını Aç</a></p></div>
            <?php endif; ?>

            <?php if ( current_user_can( 'mmc_manage_programs' ) ) : ?>
            <div class="mmc-panel">
                <h2>Yeni Program Aç</h2>
                <p>İlk kayıt yalnız program dosyasını açar. Sonraki adımda tanıtım havzası ve bölge analizi yapılır.</p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mmc-form-grid">
                    <input type="hidden" name="action" value="mmc_create_program">
                    <?php wp_nonce_field( 'mmc_create_program', 'mmc_nonce' ); ?>
                    <label>İl
                        <select name="province_name" required>
                            <option value="">İl seçin</option>
                            <?php foreach ( MMC_Region_Service::turkey_provinces() as $province ) : ?>
                                <option value="<?php echo esc_attr( $province ); ?>"><?php echo esc_html( $province ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Ana İlçe <input type="text" name="district_name" list="mmc-known-districts" placeholder="Örn. Sincan" autocomplete="off">
                        <datalist id="mmc-known-districts">
                        <?php foreach ( MMC_Region_Service::all_known_districts() as $district ) : ?>
                            <option value="<?php echo esc_attr( MMC_Region_Service::normalize_place_name( $district ) ); ?>"></option>
                        <?php endforeach; ?>
                        </datalist>
                        <small class="description">İlçeler otomatik nüfus ve okul kaynaklarından önerilir; gerekirse elle de yazabilirsiniz.</small>
                    </label>
                    <label>Plan Yılı <input type="number" name="plan_year" min="2020" max="2100" value="<?php echo esc_attr( wp_date( 'Y' ) ); ?>"></label>
                    <label>Planlanan Tarih <input type="date" name="planned_date"></label>
                    <label class="mmc-span-2">İlk Not <textarea name="notes" rows="3" placeholder="Program hazırlık notu"></textarea></label>
                    <div><button class="button button-primary button-hero">Program Dosyasını Oluştur</button></div>
                </form>
            </div>
            <?php endif; ?>

            <div class="mmc-panel">
                <h2>Program Listesi</h2>
                <table class="widefat striped"><thead><tr><th>Kod</th><th>İl</th><th>Ana İlçe</th><th>Plan Tarihi</th><th>Durum</th><th>İşlemler</th></tr></thead><tbody>
                <?php if ( ! $programs ) : ?><tr><td colspan="6">Henüz program yok.</td></tr><?php else : foreach ( $programs as $p ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $p->program_code ); ?></strong></td>
                        <td><?php echo esc_html( MMC_Region_Service::normalize_place_name( $p->province_name ) ); ?></td>
                        <td><?php echo esc_html( $p->district_name ? MMC_Region_Service::normalize_place_name( $p->district_name ) : 'Genel' ); ?></td>
                        <td><?php echo esc_html( $p->planned_date ?: '-' ); ?></td>
                        <td><?php echo esc_html( $statuses[ $p->status ] ?? $p->status ); ?></td>
                        <td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-preparation', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Hazırlık</a> <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-venue-flow', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Salon</a> <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-events', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Etkinlik</a> <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-kommo', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Kommo</a> <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-field', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Saha</a> <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-operations', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Operasyon</a> <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-finance', 'program_id'=>$p->id ), admin_url( 'admin.php' ) ) ); ?>">Finans</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody></table>
            </div>
        </div>
        <?php
    }

    public function preparation_page() {
        $this->guard( 'mmc_view_programs' );
        $programs = MMC_Program_Service::all_programs();
        $program_id = isset( $_GET['program_id'] ) ? absint( $_GET['program_id'] ) : 0;
        $program = $program_id ? MMC_Program_Service::get_program( $program_id ) : null;
        ?>
        <div class="wrap mmc-wrap">
            <h1>Program Hazırlık Dashboardu</h1>
            <?php $this->notice_from_query(); ?>
            <div class="mmc-panel">
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="mmc-inline-form">
                    <input type="hidden" name="page" value="mmc-preparation">
                    <label>Program <select name="program_id" required>
                        <option value="">Program seçin</option>
                        <?php foreach ( $programs as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $program_id, $p->id ); ?>><?php echo esc_html( $p->program_code . ' — ' . $p->province_name . ' / ' . ( $p->district_name ?: 'Genel' ) ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <button class="button button-primary">Aç</button>
                </form>
            </div>
            <?php if ( $program ) : $this->render_preparation_dashboard( $program ); endif; ?>
        </div>
        <?php
    }

    private function render_preparation_dashboard( $program ) {
        $known = MMC_Region_Service::known_districts( $program->province_name );
        if ( $program->district_name && ! in_array( $program->district_name, $known, true ) ) {
            array_unshift( $known, $program->district_name );
        }
        $selected = MMC_Region_Service::get_program_targets( $program->id );
        $summary = MMC_Region_Service::program_summary( $program->id );
        $totals = $summary['totals'];
        $coverage = $summary['coverage'];
        $n = max( 1, (int) $summary['district_total'] );
        ?>
        <div class="mmc-panel mmc-hero-panel">
            <div><small><?php echo esc_html( $program->program_code ); ?></small><h2><?php echo esc_html( MMC_Region_Service::normalize_place_name( $program->province_name ) . ' / ' . ( $program->district_name ? MMC_Region_Service::normalize_place_name( $program->district_name ) : 'Genel' ) ); ?></h2></div>
            <div><strong>Program aşaması:</strong> <?php echo esc_html( MMC_Program_Service::statuses()[ $program->status ] ?? $program->status ); ?></div>
        </div>

        <?php if ( ! empty( $summary['population_source']['ready'] ) ) : ?>
        <div class="notice notice-info inline mmc-source-notice"><p><strong>Nüfus otomatik kaynaktan geliyor.</strong> <?php echo esc_html( (string)$summary['population_source']['year'] ); ?> il/ilçe nüfusu — <?php echo esc_html( (string)$summary['population_source']['source'] ); ?>. MMC içine ayrıca nüfus CSV'si yüklenmez. Tanıtım havzası kaydedildiğinde ilçe nüfusları Program Dosyasına snapshot olarak alınır. Bu kaynak 0–14 yaş kırılımı içermediği için o alan yalnız doğrulanmış ayrı veri varsa gösterilir.</p></div>
        <?php endif; ?>

        <?php
        $has_any_region_data = array_sum( array_map( 'intval', (array) $coverage ) ) > 0 || ! empty( $summary['school_rows'] );
        if ( ! $has_any_region_data ) :
            $region_url = add_query_arg( array( 'page'=>'mmc-region-data', 'province'=>$program->province_name, 'district'=>$program->district_name ), admin_url( 'admin.php' ) );
        ?>
        <div class="mmc-readiness-box mmc-readiness-critical">
            <div><strong>Bu program için bölge verisi bulunamadı.</strong><br><span><?php echo esc_html( MMC_Region_Service::normalize_place_name( $program->province_name ) . ' / ' . ( $program->district_name ? MMC_Region_Service::normalize_place_name( $program->district_name ) : 'Genel' ) ); ?> için nüfus, okul ve öğrenci verisi yüklenmeden analiz sonuçları karar verisi olarak kullanılmamalı.</span></div>
            <a class="button button-primary" href="<?php echo esc_url( $region_url ); ?>">Bu Bölgenin Verisini Ekle</a>
        </div>
        <?php endif; ?>

        <div class="mmc-panel">
            <h2>1. Tanıtım Havzası</h2>
            <p>Ana ilçe ile birlikte okul tanıtımı yapılacak ilçeleri seçin. İlçeler otomatik nüfus kaynağı ve Okul Tanıtım listesinden gelir; gerekirse yeni ilçe elle eklenebilir.</p>
            <?php if ( current_user_can( 'mmc_manage_programs' ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="mmc_save_targets"><input type="hidden" name="program_id" value="<?php echo esc_attr( $program->id ); ?>">
                <?php wp_nonce_field( 'mmc_save_targets_' . $program->id, 'mmc_nonce' ); ?>
                <div class="mmc-check-grid">
                <?php foreach ( $known as $district ) : ?>
                    <label><input type="checkbox" name="districts[]" value="<?php echo esc_attr( $district ); ?>" <?php checked( in_array( $district, $selected, true ) || ( ! $selected && $district === $program->district_name ) ); ?>> <?php echo esc_html( $district ); ?></label>
                <?php endforeach; ?>
                </div>
                <label class="mmc-block-label">Ek ilçe adları <input type="text" name="extra_districts" placeholder="Örn. Ayaş, Kahramankazan"></label>
                <p><button class="button button-primary">Tanıtım Havzasını Kaydet ve Analiz Et</button></p>
            </form>
            <?php endif; ?>
        </div>

        <h2>2. Hedef Bölge Özeti</h2>
        <div class="mmc-cards mmc-cards-5">
            <div class="mmc-card"><span>Hedef İlçe</span><strong><?php echo esc_html( $summary['district_total'] ); ?></strong></div>
            <div class="mmc-card"><span>Toplam Nüfus</span><strong><?php echo esc_html( $this->fmt( $totals['population_total'] ) ); ?></strong><small><?php echo esc_html( $this->coverage_text( $coverage['population_total'], $n ) ); ?></small></div>
            <div class="mmc-card"><span>0–14 Yaş</span><strong><?php echo esc_html( $this->fmt( $totals['population_0_14'] ) ); ?></strong><small><?php echo esc_html( $this->coverage_text( $coverage['population_0_14'], $n ) ); ?></small></div>
            <div class="mmc-card"><span>Okul Sayısı</span><strong><?php echo esc_html( $this->fmt( $totals['school_count'] ) ); ?></strong><small><?php echo esc_html( $this->coverage_text( $coverage['school_count'], $n ) ); ?></small></div>
            <div class="mmc-card"><span>Öğrenci Sayısı</span><strong><?php echo esc_html( $this->fmt( $totals['student_count'] ) ); ?></strong><small><?php echo esc_html( $this->coverage_text( $coverage['student_count'], $n ) ); ?></small></div>
        </div>

        <div class="mmc-grid-2">
            <div class="mmc-panel">
                <h2>3. Veri Kalitesi</h2>
                <table class="widefat striped"><thead><tr><th>Gösterge</th><th>Kapsama</th><th>Durum</th></tr></thead><tbody>
                    <?php foreach ( array( 'population_total'=>'Nüfus', 'population_0_14'=>'0–14 yaş', 'school_count'=>'Okul', 'student_count'=>'Öğrenci' ) as $key=>$label ) :
                        $c=(int)$coverage[$key]; $state=$c===$summary['district_total'] && $c>0 ? '🟢 Tam' : ( $c>0 ? '🟡 Kısmi' : '🔴 Veri yok' ); ?>
                    <tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( $c . ' / ' . $summary['district_total'] . ' ilçe' ); ?></td><td><?php echo esc_html( $state ); ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td>Okul listesi</td><td><?php echo esc_html( number_format_i18n( $summary['school_rows'] ) . ' okul kaydı' ); ?></td><td><?php echo $summary['school_rows'] ? '🟢 Kayıt var' : '🔴 Veri yok'; ?></td></tr>
                </tbody></table>
                <p class="description">Toplam nüfus otomatik ilçe kaynağından hesaplanır. Kaynakta olmayan yaş/öğrenci kırılımları tahmin edilmez; il toplamı ilçelere dağıtılmaz.</p>
                <?php if ( ! empty($summary['school_source']['external']) ) : ?><p class="description"><strong>Okul listesi kaynağı:</strong> <?php echo esc_html($summary['school_source']['label']); ?>. Okul sayısı bu ana listeden hesaplanır; öğrenci sayısı yalnız kaynakta mevcutsa kullanılır.</p><?php endif; ?>
            </div>
            <div class="mmc-panel">
                <h2>4. İl Geneli Referans</h2>
                <p>Bu alan yalnız karşılaştırma içindir; hedef ilçe toplamına eklenmez.</p>
                <table class="widefat striped"><tbody>
                    <?php foreach ( array( 'population_total'=>'Nüfus', 'school_count'=>'Okul', 'student_count'=>'Öğrenci' ) as $key=>$label ) : $m=$summary['province'][$key]; ?>
                    <tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $m ? $this->fmt( $m->metric_value ) : 'Veri yok' ); ?></td><td><?php echo esc_html( $m ? $m->data_year . ' • ' . $m->source_org : '-' ); ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
        </div>

        <div class="mmc-panel">
            <h2>Sonraki adım</h2>
            <p>Tanıtım havzası ve temel veri kapsaması yeterliyse <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-venue-flow', 'program_id'=>$program->id ), admin_url( 'admin.php' ) ) ); ?>">Salon Araştırması & Tahsise Geç</a></p>
        </div>
        <?php
    }

    public function region_data_page() {
        $this->guard( 'mmc_manage_region_data' );
        $counts = MMC_Region_Service::warehouse_counts();
        $imports = MMC_Region_Service::recent_imports( 10 );
        $labels = MMC_Region_Service::metric_labels();
        $population_source = class_exists('MMC_Population_Source_Service') ? MMC_Population_Source_Service::info() : array('ready'=>false,'year'=>0,'source'=>'','province_count'=>0,'district_count'=>0,'menu_url'=>'');
        if ( ! empty( $population_source['ready'] ) ) {
            unset( $labels['population_total'] );
        }
        $school_source = class_exists('MMC_School_Source_Service') ? MMC_School_Source_Service::source_info() : array('external'=>false,'label'=>'MMC okul cache','menu_url'=>'');
        $meb_source = class_exists('MMC_MEB_Source_Service') ? MMC_MEB_Source_Service::info() : array('ready'=>false,'academic_year'=>'','province_count'=>0,'school_total'=>0,'student_total'=>0,'source_name'=>'');
        $prefill_province = MMC_Region_Service::normalize_place_name( wp_unslash( $_GET['province'] ?? '' ) );
        $prefill_district = MMC_Region_Service::normalize_place_name( wp_unslash( $_GET['district'] ?? '' ) );
        ?>
        <div class="wrap mmc-wrap">
            <h1>Bölge Veri Ambarı</h1>
            <?php $this->notice_from_query(); ?>
            <p class="mmc-lead">İl/ilçe toplam nüfusu Madagaskar Nüfus Verisi kaynağından otomatik okunur. İl geneli okul ve öğrenci referansı MEB 2024/25 paketinden otomatik senkronlanır. Okul ana listesi Madagaskar → Okul Tanıtım'dan gelir. Bu ekran yalnız otomatik kaynakta bulunmayan yaş ve ilçe düzeyi doğrulanmış ek metrikler içindir.</p>
            <div class="mmc-cards">
                <div class="mmc-card"><span>Nüfus Kaynağı</span><strong><?php echo ! empty($population_source['ready']) ? '✅' : '⚠️'; ?></strong><small><?php echo esc_html( ! empty($population_source['year']) ? $population_source['year'] : 'Hazır değil' ); ?></small></div>
                <div class="mmc-card"><span>İl Nüfusu</span><strong><?php echo esc_html( (int)($population_source['province_count'] ?? 0) . ' / 81' ); ?></strong></div>
                <div class="mmc-card"><span>İlçe Nüfusu</span><strong><?php echo esc_html( (int)($population_source['district_count'] ?? 0) . ' / 973' ); ?></strong></div>
                <div class="mmc-card"><span>Okul Kaydı</span><strong><?php echo esc_html( number_format_i18n( $counts['schools'] ) ); ?></strong></div>
                <div class="mmc-card"><span>MEB Eğitim</span><strong><?php echo ! empty($meb_source['ready']) ? '✅' : '⚠️'; ?></strong><small><?php echo esc_html( ! empty($meb_source['academic_year']) ? $meb_source['academic_year'] : 'Hazır değil' ); ?></small></div>
                <div class="mmc-card"><span>Ek Metrik</span><strong><?php echo esc_html( number_format_i18n( $counts['metrics'] ) ); ?></strong></div>
            </div>

            <?php if ( ! empty( $population_source['ready'] ) ) : ?>
            <div class="mmc-panel mmc-source-panel">
                <h2>Otomatik Nüfus Ana Kaynağı</h2>
                <p><strong>✅ <?php echo esc_html( (string)$population_source['source'] ); ?></strong> bağlıdır. <?php echo esc_html( (string)$population_source['year'] ); ?> yılı için 81 il ve 973 ilçe kaydı MMC tarafından doğrudan okunur. Toplam nüfusu bu ekrana veya CSV'ye ikinci kez girmeyin.</p>
                <?php if ( ! empty($population_source['menu_url']) ) : ?><p><a class="button" href="<?php echo esc_url($population_source['menu_url']); ?>">Nüfus Verisi Menüsünü Aç</a></p><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $meb_source['ready'] ) ) : ?>
            <div class="mmc-panel mmc-source-panel">
                <h2>Otomatik MEB Eğitim Kaynağı</h2>
                <p><strong>✅ <?php echo esc_html( (string)$meb_source['source_name'] ); ?></strong> eklentiyle birlikte gelir ve otomatik senkronlanır. <?php echo esc_html( (string)$meb_source['academic_year'] ); ?> için <?php echo esc_html( number_format_i18n( (int)$meb_source['province_count'] ) ); ?> il, <?php echo esc_html( number_format_i18n( (int)$meb_source['school_total'] ) ); ?> okul ve <?php echo esc_html( number_format_i18n( (int)$meb_source['student_total'] ) ); ?> öğrenci referansı hazırdır.</p>
                <p class="description">Bu veriler il geneli referansıdır; hedef ilçelere dağıtılmaz ve ilçe öğrenci sayısı tahmin edilmez.</p>
            </div>
            <?php endif; ?>

            <div class="mmc-grid-2">
                <div class="mmc-panel">
                    <h2>Ek / Eğitim Metriği Ekle</h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mmc-form-grid">
                        <input type="hidden" name="action" value="mmc_add_metric"><?php wp_nonce_field( 'mmc_add_metric', 'mmc_nonce' ); ?>
                        <label>İl <select name="province_name" required><option value="">İl seçin</option><?php foreach ( MMC_Region_Service::turkey_provinces() as $province ) : ?><option value="<?php echo esc_attr($province); ?>" <?php selected( $prefill_province, $province ); ?>><?php echo esc_html($province); ?></option><?php endforeach; ?></select></label><label>İlçe <input name="district_name" value="<?php echo esc_attr( $prefill_district ); ?>" placeholder="Boşsa il geneli"></label>
                        <label>Metrik <select name="metric_key"><?php foreach ( $labels as $key=>$label ) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                        <label>Değer <input type="number" step="0.01" name="metric_value" required></label>
                        <label>Veri Yılı <input name="data_year" required placeholder="2025 veya 2025-2026"></label><label>Kaynak Kurum <input name="source_org" required placeholder="TÜİK / MEB / MEM"></label>
                        <label class="mmc-span-2">Kaynak Adı <input name="source_name" placeholder="ADNKS 2025"></label>
                        <label class="mmc-span-2">Kaynak URL <input type="url" name="source_url"></label>
                        <div><button class="button button-primary">Kaydet</button></div>
                    </form>
                </div>
                <div class="mmc-panel">
                    <h2>Ek Metrikleri CSV ile Yükle</h2>
                    <p><a href="<?php echo esc_url( MMC_URL . 'templates/region-metrics-template.csv' ); ?>">Ek metrik CSV şablonu</a></p>
                    <?php if ( ! empty($population_source['ready']) ) : ?><p class="description"><strong>Not:</strong> Toplam nüfus CSV ile yüklenmez; otomatik nüfus kaynağından gelir.</p><?php endif; ?>
                    <?php if ( ! empty($meb_source['ready']) ) : ?><p class="description"><strong>MEB 2024/25:</strong> İl geneli okul ve öğrenci toplamları otomatik gelir; bu iki il geneli metriği tekrar CSV ile yüklemeyin. CSV alanını yalnız ilçe düzeyi doğrulanmış veya farklı ek metrikler için kullanın.</p><?php endif; ?>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="mmc_import_metrics"><?php wp_nonce_field( 'mmc_import_metrics', 'mmc_nonce' ); ?>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required> <button class="button">Metrikleri İçe Aktar</button>
                    </form>
                    <hr>
                    <?php if ( ! empty($school_source['external']) ) : ?>
                        <h3>Okul Listesi Ana Kaynağı</h3>
                        <p><strong>✅ <?php echo esc_html($school_source['label']); ?></strong> bağlıdır. Okul listesi için MMC'ye ayrıca CSV yüklemeyin; okul adı, il/ilçe, adres, koordinat ve varsa öğrenci sayısı ana kaynaktan okunur.</p>
                        <?php if ( ! empty($school_source['menu_url']) ) : ?><p><a class="button" href="<?php echo esc_url($school_source['menu_url']); ?>">Okul Tanıtım Menüsünü Aç</a></p><?php endif; ?>
                    <?php else : ?>
                        <h3>Okul Listesi — Geriye Uyumluluk</h3>
                        <p class="description">Okul Tanıtım ana kaynağı otomatik algılanana kadar eski CSV içe aktarımı kullanılabilir.</p>
                        <p><a href="<?php echo esc_url( MMC_URL . 'templates/schools-template.csv' ); ?>">Okul listesi CSV şablonu</a></p>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="mmc_import_schools"><?php wp_nonce_field( 'mmc_import_schools', 'mmc_nonce' ); ?>
                            <input type="file" name="csv_file" accept=".csv,text/csv" required> <button class="button">Okulları İçe Aktar</button>
                        </form>
                    <?php endif; ?>
                    <p class="description">İlçe öğrenci sayısı kaynakta yoksa sistem tahmin üretmez; il toplamını ilçelere dağıtmaz.</p>
                </div>
            </div>

            <div class="mmc-panel">
                <h2>Son İçe Aktarımlar</h2>
                <table class="widefat striped"><thead><tr><th>Tarih</th><th>Tür</th><th>Dosya</th><th>Kaynak</th><th>Yıl</th><th>Başarılı</th><th>Hata</th></tr></thead><tbody>
                <?php if ( ! $imports ) : ?><tr><td colspan="7">Henüz içe aktarım yok.</td></tr><?php else : foreach ( $imports as $i ) : ?>
                    <tr><td><?php echo esc_html($i->imported_at); ?></td><td><?php echo esc_html($i->import_type); ?></td><td><?php echo esc_html($i->filename); ?></td><td><?php echo esc_html($i->source_org ?: '-'); ?></td><td><?php echo esc_html($i->data_year ?: '-'); ?></td><td><?php echo esc_html($i->rows_success); ?></td><td><?php echo esc_html($i->rows_error); ?></td></tr>
                <?php endforeach; endif; ?></tbody></table>
            </div>
        </div>
        <?php
    }

    public function workflow_page() {
        $this->guard( 'mmc_view_programs' );
        $statuses = MMC_Program_Service::statuses();
        ?>
        <div class="wrap mmc-wrap"><h1>Program Yaşam Döngüsü</h1>
            <div class="mmc-timeline">
            <?php
            $descriptions = array(
                'preparation'=>'Program dosyası açılır ve hedef bölge belirlenir.',
                'region_analysis'=>'Nüfus, okul, öğrenci ve tanıtım havzası incelenir.',
                'venue_research'=>'Uygun salon alternatifleri araştırılır.',
                'allocation_request'=>'Tahsis talep yazısı hazırlanır ve gönderilir.',
                'allocation_pending'=>'Kurum cevabı ve salon uygunluğu takip edilir.',
                'venue_confirmed'=>'Salon ve program tarihi kesinleşir.',
                'venue_payment'=>'Kira ve teminat ödemeleri tamamlanır.',
                'event_setup'=>'Etkinlik, seans, kapasite ve fiyatlar hazırlanır.',
                'sales_prep'=>'Satış kanalları doğrulanır.',
                'sales_open'=>'Bilet satışları aktif olarak izlenir.',
                'promotion'=>'Sosyal medya, Meta ve saha tanıtımı yürütülür.',
                'operations'=>'Araç, ekip, teknik ve gösteri günü hazırlıkları tamamlanır.',
                'show_day'=>'Gösteri, gişe ve check-in operasyonu yürütülür.',
                'financial_close'=>'Gelir-gider ve fatura mutabakatı tamamlanır.',
                'deposit_refund'=>'Salon teminatının iadesi takip edilir.',
                'completed'=>'Nihai kârlılık oluşturulur ve program arşivlenir.',
            );
            $i=1; foreach ( $statuses as $key => $label ) : if ( 'cancelled' === $key ) continue; ?>
                <div class="mmc-step"><b><?php echo esc_html( $i++ ); ?></b><span><?php echo esc_html( $label ); ?></span><small><?php echo esc_html( $descriptions[$key] ?? '' ); ?></small></div>
            <?php endforeach; ?>
            </div>
            <div class="mmc-panel"><strong>Kapanış kuralı:</strong> Program, gösteri bittiğinde değil; finansal kapanış ve teminat iadesi tamamlandığında “Tamamlandı” olur.</div>
        </div>
        <?php
    }

    public function roles_page() {
        $this->guard( 'mmc_manage_settings' );
        $roles = array(
            'Madagaskar Yönetici' => 'Tüm MMC modülleri ve Bölge Veri Ambarı',
            'Madagaskar Operasyon' => 'Program görüntüleme, salon, etkinlik/seans, Kommo kontrolü, görev ve operasyon',
            'Madagaskar Saha' => 'Program görüntüleme ve saha',
            'Madagaskar Finans' => 'Program görüntüleme, finans ve rapor',
            'Madagaskar Görüntüleyici' => 'Salt okunur dashboard/program/rapor',
        );
        ?>
        <div class="wrap mmc-wrap"><h1>Yetkiler</h1><div class="mmc-panel"><table class="widefat striped"><thead><tr><th>Rol</th><th>Yetki kapsamı</th></tr></thead><tbody>
        <?php foreach ( $roles as $name => $scope ) : ?><tr><td><strong><?php echo esc_html( $name ); ?></strong></td><td><?php echo esc_html( $scope ); ?></td></tr><?php endforeach; ?>
        </tbody></table><p>Kullanıcı bazlı rol ataması WordPress → Kullanıcılar ekranından yapılır.</p></div></div>
        <?php
    }

    public function system_page() {
        $this->guard( 'mmc_manage_settings' );
        $checks = class_exists( 'MMC_Health_Service' ) ? MMC_Health_Service::checks() : array();
        $summary = class_exists( 'MMC_Health_Service' ) ? MMC_Health_Service::summary() : array( 'critical'=>0,'warning'=>0,'ok'=>0,'info'=>0 );
        ?>
        <div class="wrap mmc-wrap">
            <h1>Kurulum & Sağlık Kontrol Merkezi</h1>
            <p class="mmc-lead">Canlı sitede MMC'nin veri, entegrasyon, cron ve eski kodlarla olası çakışma durumunu tek ekranda kontrol eder.</p>

            <div class="mmc-health-summary">
                <div class="mmc-health-count mmc-health-critical"><strong><?php echo esc_html( (int)$summary['critical'] ); ?></strong><span>Kritik</span></div>
                <div class="mmc-health-count mmc-health-warning"><strong><?php echo esc_html( (int)$summary['warning'] ); ?></strong><span>Uyarı</span></div>
                <div class="mmc-health-count mmc-health-ok"><strong><?php echo esc_html( (int)$summary['ok'] ); ?></strong><span>Sağlıklı</span></div>
                <div class="mmc-health-count mmc-health-info"><strong><?php echo esc_html( (int)$summary['info'] ); ?></strong><span>Bilgi</span></div>
            </div>

            <div class="mmc-panel">
                <h2>Sistem Kontrolleri</h2>
                <table class="widefat striped mmc-health-table"><thead><tr><th>Durum</th><th>Kontrol</th><th>Açıklama</th><th>İşlem</th></tr></thead><tbody>
                    <?php foreach ( $checks as $check ) : ?>
                    <tr>
                        <td><span class="mmc-health-pill mmc-health-<?php echo esc_attr( $check['severity'] ); ?>"><?php echo esc_html( $this->health_label( $check['severity'] ) ); ?></span></td>
                        <td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
                        <td><?php echo esc_html( $check['detail'] ); ?></td>
                        <td><?php if ( ! empty( $check['action_url'] ) ) : ?><a class="button button-small" href="<?php echo esc_url( $check['action_url'] ); ?>"><?php echo esc_html( $check['action_label'] ?: 'Aç' ); ?></a><?php else : ?>—<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>

            <div class="mmc-grid-2">
                <div class="mmc-panel">
                    <h2>Canlı Kullanıma Geçmeden Önce</h2>
                    <ol class="mmc-checklist">
                        <li><strong>Bölge verisi:</strong> Madagaskar → Nüfus Verisi ekranında 81 il / 973 ilçe kaydını; Okul Tanıtım ana kaynağında okul listelerini doğrulayın.</li>
                        <li><strong>Eski Madagaskar kodları:</strong> V5 finans / eski etkinlik snippet'lerini MMC ile aynı kaydı yazmadığından emin olun.</li>
                        <li><strong>Satış:</strong> Bir test siparişiyle WooCommerce → PayTR → Tickera → MMC zincirini doğrulayın.</li>
                        <li><strong>E-posta:</strong> Gece Raporları ekranından test e-postası gönderin.</li>
                        <li><strong>Yedek:</strong> Güncelleme öncesi veritabanı + wp-content yedeği alın.</li>
                    </ol>
                </div>
                <div class="mmc-panel">
                    <h2>Bu sürümde düzeltilen ekran sorunları</h2>
                    <ul class="mmc-issues">
                        <li>İl artık 81 il listesinden seçilir; yazım farklılıkları azaltılır.</li>
                        <li>Yer adları yeni kayıtlarda Türkçe başlık düzenine normalize edilir.</li>
                        <li>Bölge verisi yoksa Hazırlık Dashboardu bunu 0 değerlerin üstünde açıkça bildirir.</li>
                        <li>“Program Yaşam Döngüsü” ekranında teknik durum kodları yerine açıklamalar gösterilir.</li>
                        <li>Eski/çakışabilecek Madagaskar eklentileri ve Code Snippets kullanımı sağlık kontrolünde uyarılır.</li>
                        <li>Okul ana listesi Okul Tanıtım menüsünden; il/ilçe toplam nüfusu Madagaskar Nüfus Verisi eklentisinden tek kaynak olarak okunur.</li>
                    </ul>
                </div>
            </div>

            <div class="mmc-panel"><strong>MMC sürümü:</strong> <?php echo esc_html( MMC_VERSION ); ?> &nbsp; <strong>Veri şema sürümü:</strong> <?php echo esc_html( MMC_DB_VERSION ); ?></div>
        </div>
        <?php
    }

    private function health_label( $severity ) {
        $labels = array( 'critical'=>'Kritik', 'warning'=>'Uyarı', 'ok'=>'Sağlıklı', 'info'=>'Bilgi' );
        return $labels[ $severity ] ?? 'Bilgi';
    }

    public function handle_create_program() {
        $this->guard( 'mmc_manage_programs' );
        check_admin_referer( 'mmc_create_program', 'mmc_nonce' );
        $program_id = MMC_Program_Service::create_program( wp_unslash( $_POST ) );
        if ( is_wp_error( $program_id ) ) {
            wp_die( esc_html( $program_id->get_error_message() ) );
        }
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-preparation', 'program_id'=>$program_id, 'mmc_msg'=>'program_created' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_add_metric() {
        $this->guard( 'mmc_manage_region_data' );
        check_admin_referer( 'mmc_add_metric', 'mmc_nonce' );
        $result = MMC_Region_Service::add_metric( wp_unslash( $_POST ) );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-region-data', 'mmc_msg'=>'metric_added' ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_import_metrics() {
        $this->guard( 'mmc_manage_region_data' );
        check_admin_referer( 'mmc_import_metrics', 'mmc_nonce' );
        $result = MMC_Region_Service::import_metrics_csv( $_FILES['csv_file'] ?? array() );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-region-data', 'mmc_msg'=>'import_done', 'ok'=>$result['success'], 'err'=>$result['errors'] ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_import_schools() {
        $this->guard( 'mmc_manage_region_data' );
        check_admin_referer( 'mmc_import_schools', 'mmc_nonce' );
        $result = MMC_Region_Service::import_schools_csv( $_FILES['csv_file'] ?? array() );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-region-data', 'mmc_msg'=>'import_done', 'ok'=>$result['success'], 'err'=>$result['errors'] ), admin_url( 'admin.php' ) ) ); exit;
    }

    public function handle_save_targets() {
        $this->guard( 'mmc_manage_programs' );
        $program_id = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_save_targets_' . $program_id, 'mmc_nonce' );
        $districts = array_map( 'sanitize_text_field', (array) ( $_POST['districts'] ?? array() ) );
        $extra = sanitize_text_field( wp_unslash( $_POST['extra_districts'] ?? '' ) );
        if ( $extra ) {
            $districts = array_merge( $districts, preg_split( '/[,;]+/', $extra ) );
        }
        $result = MMC_Region_Service::set_program_targets( $program_id, $districts );
        if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-preparation', 'program_id'=>$program_id, 'mmc_msg'=>'targets_saved' ), admin_url( 'admin.php' ) ) ); exit;
    }

    private function notice_from_query() {
        $msg = sanitize_key( $_GET['mmc_msg'] ?? '' );
        if ( ! $msg ) return;
        $messages = array(
            'program_created' => 'Program dosyası oluşturuldu. Şimdi tanıtım havzasını belirleyin.',
            'metric_added'    => 'Bölge veri kaydı eklendi.',
            'targets_saved'   => 'Tanıtım havzası kaydedildi ve bölge analizi güncellendi.',
        );
        if ( 'import_done' === $msg ) {
            $text = sprintf( 'İçe aktarım tamamlandı: %d başarılı, %d hata.', absint( $_GET['ok'] ?? 0 ), absint( $_GET['err'] ?? 0 ) );
        } else {
            $text = $messages[ $msg ] ?? '';
        }
        if ( $text ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
    }

    private function fmt( $value ) {
        return number_format_i18n( (float) $value, ( floor( (float) $value ) == (float) $value ? 0 : 2 ) );
    }

    private function coverage_text( $covered, $total ) {
        return sprintf( '%d/%d ilçe verisi', (int) $covered, (int) $total );
    }

    private function guard( $capability ) {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'Bu ekran için yetkiniz yok.', 'madagaskar-management-center' ) );
        }
    }
}
