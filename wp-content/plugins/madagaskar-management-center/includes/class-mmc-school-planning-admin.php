<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_School_Planning_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_mmc_school_print_policy_save', array( $this, 'save_policy' ) );
        add_action( 'admin_post_mmc_school_print_sync', array( $this, 'sync_plan' ) );
        add_action( 'admin_post_mmc_school_print_rows_save', array( $this, 'save_rows' ) );
    }

    public function menu() {
        add_submenu_page(
            'mmc-dashboard',
            'Okul Veri Merkezi',
            'Okul Veri Merkezi',
            'mmc_manage_field',
            'mmc-school-data',
            array( $this, 'data_page' )
        );
        add_submenu_page(
            'mmc-dashboard',
            'Okul Davetiye / Bilet Baskı Planı',
            'Okul Baskı Planı',
            'mmc_manage_field',
            'mmc-school-print-plan',
            array( $this, 'print_page' )
        );
    }

    private function guard() {
        if ( ! current_user_can( 'mmc_manage_field' ) ) {
            wp_die( 'Bu alan için yetkiniz yok.' );
        }
    }

    private function selected_program() {
        $programs = MMC_Program_Service::all_programs();
        $pid = absint( $_GET['program_id'] ?? 0 );
        if ( ! $pid && $programs ) { $pid = (int) $programs[0]->id; }
        return array( $programs, $pid, $pid ? MMC_Program_Service::get_program( $pid ) : null );
    }

    private function program_picker( $page, $programs, $pid ) {
        ?>
        <div class="mmc-panel mmc-no-print">
            <form method="get" class="mmc-inline-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
                <label>Program
                    <select name="program_id" onchange="this.form.submit()">
                        <option value="">Seçin</option>
                        <?php foreach ( $programs as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $pid, $p->id ); ?>>
                                <?php echo esc_html( $p->program_code . ' — ' . $p->province_name . ' / ' . $p->district_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <noscript><?php submit_button( 'Göster', 'secondary', '', false ); ?></noscript>
            </form>
        </div>
        <?php
    }

    public function data_page() {
        $this->guard();
        list( $programs, $pid, $program ) = $this->selected_program();
        $rows = $program ? MMC_School_Planning_Service::campus_rows( $pid ) : array();
        $source = class_exists( 'MMC_School_Source_Service' ) ? MMC_School_Source_Service::source_info() : array();

        $students = 0;
        $known = 0;
        $unknown = 0;
        $known_school_units = 0;
        $unknown_school_units = 0;
        $school_unit_count = 0;
        $with_site = 0;
        foreach ( $rows as $row ) {
            $components = max( 1, (int) $row->component_school_count );
            $known_units = min( $components, max( 0, (int) $row->student_known_count ) );
            $school_unit_count += $components;
            $known_school_units += $known_units;
            $unknown_school_units += max( 0, $components - $known_units );
            if ( null === $row->student_count_snapshot ) {
                $unknown++;
            } else {
                $known++;
                $students += (int) $row->student_count_snapshot;
            }
            if ( ! empty( $row->website ) ) { $with_site++; }
        }
        ?>
        <div class="wrap mmc-wrap">
            <h1>Okul Veri Merkezi</h1>
            <p class="mmc-lead">Okul Tanıtım ana kaynağını kampüs/ziyaret noktası seviyesinde görüntüler. Aynı kampüsteki kademeler tek ziyaret noktasıdır; öğrenci sayısı bilinmeyen kayıtlar tahmin edilmez.</p>
            <?php $this->notice(); ?>
            <?php $this->program_picker( 'mmc-school-data', $programs, $pid ); ?>

            <?php if ( ! $program ) : ?>
                <div class="notice notice-info"><p>Program seçin.</p></div>
            </div>
            <?php return; endif; ?>

            <div class="mmc-panel">
                <strong>Okul ana kaynağı:</strong>
                <?php echo esc_html( $source['label'] ?? 'Bilinmiyor' ); ?>
                <?php if ( ! empty( $source['menu_url'] ) ) : ?>
                    · <a href="<?php echo esc_url( $source['menu_url'] ); ?>">Okul Tanıtım ana listesini aç</a>
                <?php endif; ?>
                <p class="description">Programa alınacak okul havuzu <a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-field', 'program_id'=>$pid ), admin_url('admin.php') ) ); ?>">Okul / Saha</a> ekranında oluşturulur. Bu sayfa hedefleri fiziksel kampüs düzeyinde toplar.</p>
            </div>

            <div class="mmc-cards mmc-cards-5">
                <div class="mmc-card"><span>Ziyaret Noktası</span><strong><?php echo esc_html( number_format_i18n( count($rows) ) ); ?></strong><small>Kampüs bazlı</small></div>
                <div class="mmc-card"><span>Okul Birimi</span><strong><?php echo esc_html( number_format_i18n( $school_unit_count ) ); ?></strong><small>Anaokulu/ilkokul/ortaokul</small></div>
                <div class="mmc-card"><span>Doğrulanmış Öğrenci</span><strong><?php echo esc_html( number_format_i18n( $students ) ); ?></strong><small><?php echo esc_html( $known_school_units ); ?> okulda sayı var</small></div>
                <div class="mmc-card"><span>Öğrenci Sayısı Bilinmeyen</span><strong><?php echo esc_html( number_format_i18n( $unknown_school_units ) ); ?></strong><small><?php echo esc_html( $unknown ); ?> kampüs etkileniyor · tahmin yok</small></div>
                <div class="mmc-card"><span>Web Sitesi Olan</span><strong><?php echo esc_html( number_format_i18n( $with_site ) ); ?></strong><small>Kampüs</small></div>
            </div>

            <div class="mmc-action-row mmc-no-print">
                <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mad-okul-students', 'mmc_program_id'=>$pid ), admin_url('admin.php') ) ); ?>">Öğrenci Sayılarını Tamamla</a>
                <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-school-print-plan', 'program_id'=>$pid ), admin_url('admin.php') ) ); ?>">Davetiye / Bilet Baskı Planını Aç</a>
                <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-field', 'program_id'=>$pid ), admin_url('admin.php') ) ); ?>">Saha Planını Aç</a>
            </div>

            <div class="mmc-panel">
                <div class="mmc-table-scroll">
                    <table class="widefat striped">
                        <thead><tr><th>Kampüs / Ziyaret Noktası</th><th>Okul Birimleri</th><th>Adres</th><th>Öğrenci</th><th>Veri</th><th>Web / Telefon</th><th>Öncelik</th></tr></thead>
                        <tbody>
                        <?php if ( ! $rows ) : ?>
                            <tr><td colspan="7">Bu program için kampüs verisi yok. Önce Okul / Saha ekranında hedef okulları programa bağlayın.</td></tr>
                        <?php else : foreach ( $rows as $row ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $row->campus_name ); ?></strong><br><small><?php echo esc_html( $row->district_name ); ?></small></td>
                                <td><?php echo esc_html( implode( ' + ', $row->component_names ) ); ?><br><small><?php echo esc_html( implode( ' + ', $row->education_levels ) ); ?></small></td>
                                <td><?php echo esc_html( $row->address ); ?></td>
                                <td><strong><?php echo null === $row->student_count_snapshot ? '—' : esc_html( number_format_i18n( $row->student_count_snapshot ) ); ?></strong><br><small><?php echo esc_html( $row->student_known_count . '/' . $row->component_school_count ); ?> birimde veri</small></td>
                                <td><?php echo esc_html( $row->student_statuses ? implode( ', ', $row->student_statuses ) : ( null === $row->student_count_snapshot ? 'Eksik' : 'Mevcut' ) ); ?></td>
                                <td>
                                    <?php if ( $row->website ) : ?><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $row->website ); ?>">Site</a><?php else : ?>—<?php endif; ?>
                                    <?php if ( $row->phone ) : ?><br><small><?php echo esc_html( $row->phone ); ?></small><?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $row->priority ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function print_page() {
        $this->guard();
        list( $programs, $pid, $program ) = $this->selected_program();
        $policy = $program ? MMC_School_Planning_Service::policy( $pid ) : MMC_School_Planning_Service::default_policy();
        $plans = $program ? MMC_School_Planning_Service::plans( $pid ) : array();
        $summary = $program ? MMC_School_Planning_Service::summary( $pid ) : array();
        ?>
        <div class="wrap mmc-wrap mmc-school-print">
            <div class="mmc-no-print">
                <h1>Okul Davetiye / Bilet Baskı Planı</h1>
                <p class="mmc-lead">Öğrenci sayısından programa özel baskı önerisi üretir. Öneri otomatik baskı yapmaz; planlanan ve fiilen basılan adet ayrıca kaydedilir.</p>
                <?php $this->notice(); ?>
                <?php $this->program_picker( 'mmc-school-print-plan', $programs, $pid ); ?>
            </div>

            <?php if ( ! $program ) : ?>
                <div class="notice notice-info"><p>Program seçin.</p></div>
            </div>
            <?php return; endif; ?>

            <div class="mmc-panel mmc-no-print">
                <h2>Baskı Politikası</h2>
                <p><strong>Formül:</strong> bilinen öğrenci sayısı × dağıtım oranı + yedek oranı; sonuç belirlenen pakete yukarı yuvarlanır. <strong>%100 dağıtım = öğrenci başına 1 adet.</strong> Öğrenci sayısı bilinmeyen kampüslerde varsayılan adet ayrıca belirlenir; varsayılan 0'dır.</p>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <input type="hidden" name="action" value="mmc_school_print_policy_save">
                    <input type="hidden" name="program_id" value="<?php echo esc_attr( $pid ); ?>">
                    <?php wp_nonce_field( 'mmc_school_print_policy_'.$pid ); ?>
                    <div class="mmc-form-grid">
                        <label>Dağıtım oranı %<input type="number" step="0.01" min="0" max="300" name="distribution_percent" value="<?php echo esc_attr( $policy->distribution_percent ); ?>"></label>
                        <label>Yedek oranı %<input type="number" step="0.01" min="0" max="100" name="reserve_percent" value="<?php echo esc_attr( $policy->reserve_percent ); ?>"></label>
                        <label>Yukarı yuvarlama paketi<input type="number" min="1" max="1000" name="round_to" value="<?php echo esc_attr( $policy->round_to ); ?>"></label>
                        <label>Kampüs minimum<input type="number" min="0" name="min_per_campus" value="<?php echo esc_attr( $policy->min_per_campus ); ?>"></label>
                        <label>Kampüs maksimum <small>(0=sınırsız)</small><input type="number" min="0" name="max_per_campus" value="<?php echo esc_attr( $policy->max_per_campus ); ?>"></label>
                        <label>Öğrenci sayısı bilinmeyen kampüs<input type="number" min="0" name="unknown_student_qty" value="<?php echo esc_attr( $policy->unknown_student_qty ); ?>"></label>
                        <label class="mmc-span-2">Not<textarea name="notes" rows="2"><?php echo esc_textarea( $policy->notes ); ?></textarea></label>
                    </div>
                    <?php submit_button( 'Politikayı Kaydet', 'secondary', '', false ); ?>
                </form>
                <hr>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block">
                    <input type="hidden" name="action" value="mmc_school_print_sync">
                    <input type="hidden" name="program_id" value="<?php echo esc_attr( $pid ); ?>">
                    <?php wp_nonce_field( 'mmc_school_print_sync_'.$pid ); ?>
                    <?php submit_button( 'Kampüsleri ve Önerilen Adetleri Güncelle', 'primary', '', false ); ?>
                </form>
                <button type="button" class="button" onclick="window.print()">Baskı Planını Yazdır / PDF</button>
                <a class="button" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-school-data', 'program_id'=>$pid ), admin_url('admin.php') ) ); ?>">Okul Veri Merkezine Dön</a>
            </div>

            <?php if ( $plans ) : ?>
                <div class="mmc-cards mmc-cards-5">
                    <div class="mmc-card"><span>Kampüs / Okul</span><strong><?php echo esc_html( number_format_i18n( $summary['campus_count'] ) ); ?> / <?php echo esc_html( number_format_i18n( $summary['school_unit_count'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $summary['unknown_school_units'] ) ); ?> okulun öğrenci sayısı bilinmiyor</small></div>
                    <div class="mmc-card"><span>Bilinen Öğrenci</span><strong><?php echo esc_html( number_format_i18n( $summary['student_count'] ) ); ?></strong><small>Snapshot</small></div>
                    <div class="mmc-card"><span>Önerilen Baskı</span><strong><?php echo esc_html( number_format_i18n( $summary['suggested_qty'] ) ); ?></strong><small>Politika sonucu</small></div>
                    <div class="mmc-card"><span>Planlanan Baskı</span><strong><?php echo esc_html( number_format_i18n( $summary['planned_qty'] ) ); ?></strong><small>Manuel düzeltilebilir</small></div>
                    <div class="mmc-card"><span>Basılmış</span><strong><?php echo esc_html( number_format_i18n( $summary['printed_qty'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( max(0,$summary['planned_qty']-$summary['printed_qty']) ) ); ?> kalan</small></div>
                </div>
            <?php endif; ?>

            <div class="mmc-panel">
                <h2><?php echo esc_html( $program->program_code . ' — ' . $program->province_name . ' / ' . $program->district_name ); ?></h2>
                <p><strong>Dağıtım:</strong> %<?php echo esc_html( number_format_i18n( (float)$policy->distribution_percent, 2 ) ); ?> · <strong>Yedek:</strong> %<?php echo esc_html( number_format_i18n( (float)$policy->reserve_percent, 2 ) ); ?> · <strong>Yuvarlama:</strong> <?php echo esc_html( number_format_i18n( (int)$policy->round_to ) ); ?></p>

                <?php if ( ! $plans ) : ?>
                    <div class="notice notice-warning inline mmc-no-print"><p>Henüz baskı planı oluşturulmadı. Önce Okul / Saha ekranında hedef okulları bağlayın, ardından “Kampüsleri ve Önerilen Adetleri Güncelle” düğmesini kullanın.</p></div>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                        <input type="hidden" name="action" value="mmc_school_print_rows_save">
                        <input type="hidden" name="program_id" value="<?php echo esc_attr( $pid ); ?>">
                        <?php wp_nonce_field( 'mmc_school_print_rows_'.$pid ); ?>
                        <div class="mmc-table-scroll">
                            <table class="widefat striped">
                                <thead><tr><th>#</th><th>Kampüs</th><th>Okul Birimleri</th><th>Öğrenci</th><th>Öneri</th><th>Plan</th><th>Basılmış</th><th>Dağıtılmış</th><th>Kalan Baskı</th><th>Not</th></tr></thead>
                                <tbody>
                                <?php foreach ( $plans as $i=>$row ) : $remaining=max(0,(int)$row->planned_qty-(int)$row->printed_qty); ?>
                                    <tr>
                                        <td><?php echo esc_html( $i+1 ); ?></td>
                                        <td><strong><?php echo esc_html( $row->campus_name ); ?></strong><br><small><?php echo esc_html( $row->district_name ); ?></small></td>
                                        <td><?php echo esc_html( str_replace( ' | ', ' + ', (string)$row->component_names ) ); ?><br><small><?php echo esc_html( $row->component_school_count ); ?> okul birimi</small></td>
                                        <td><?php if ( null === $row->student_count_snapshot ) : ?><strong>—</strong><br><small><?php echo esc_html( $row->component_school_count ); ?> okulda veri eksik</small><?php else : ?><?php echo esc_html( number_format_i18n( $row->student_count_snapshot ) ); ?><br><small><?php echo esc_html( $row->student_known_count . '/' . $row->component_school_count ); ?> okulda sayı var</small><?php endif; ?></td>
                                        <td><strong><?php echo esc_html( number_format_i18n( $row->suggested_qty ) ); ?></strong></td>
                                        <td><input class="small-text" type="number" min="0" name="rows[<?php echo esc_attr($row->id); ?>][planned_qty]" value="<?php echo esc_attr($row->planned_qty); ?>"></td>
                                        <td><input class="small-text" type="number" min="0" name="rows[<?php echo esc_attr($row->id); ?>][printed_qty]" value="<?php echo esc_attr($row->printed_qty); ?>"></td>
                                        <td><input class="small-text" type="number" min="0" name="rows[<?php echo esc_attr($row->id); ?>][distributed_qty]" value="<?php echo esc_attr($row->distributed_qty); ?>"></td>
                                        <td><strong><?php echo esc_html( number_format_i18n( $remaining ) ); ?></strong></td>
                                        <td><input type="text" name="rows[<?php echo esc_attr($row->id); ?>][notes]" value="<?php echo esc_attr($row->notes); ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot><tr><th colspan="3">TOPLAM</th><th><?php echo esc_html( number_format_i18n( $summary['student_count'] ) ); ?></th><th><?php echo esc_html( number_format_i18n( $summary['suggested_qty'] ) ); ?></th><th><?php echo esc_html( number_format_i18n( $summary['planned_qty'] ) ); ?></th><th><?php echo esc_html( number_format_i18n( $summary['printed_qty'] ) ); ?></th><th><?php echo esc_html( number_format_i18n( $summary['distributed_qty'] ) ); ?></th><th><?php echo esc_html( number_format_i18n( max(0,$summary['planned_qty']-$summary['printed_qty']) ) ); ?></th><th></th></tr></tfoot>
                            </table>
                        </div>
                        <p class="mmc-no-print"><?php submit_button( 'Baskı Adetlerini Kaydet', 'primary', '', false ); ?></p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <style>
        @media print {
            #adminmenumain,#wpadminbar,#wpfooter,.notice,.mmc-no-print{display:none!important}
            #wpcontent{margin:0!important}
            .mmc-school-print{margin:8mm!important}
            .mmc-school-print table{font-size:9px}
            .mmc-school-print input{border:0!important;background:transparent!important;box-shadow:none!important;width:55px!important;padding:0!important}
            .mmc-school-print .mmc-card{break-inside:avoid}
        }
        </style>
        <?php
    }

    public function save_policy() {
        $this->guard();
        $pid = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_school_print_policy_'.$pid );
        $result = MMC_School_Planning_Service::save_policy( $pid, $_POST );
        if ( is_wp_error( $result ) ) {
            $this->redirect( $pid, $result->get_error_message(), 'error' );
        }
        $this->redirect( $pid, 'Baskı politikası kaydedildi. Önerileri yenilemek için kampüs senkronunu çalıştırın.', 'success' );
    }

    public function sync_plan() {
        $this->guard();
        $pid = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_school_print_sync_'.$pid );
        $result = MMC_School_Planning_Service::sync_plan( $pid );
        if ( is_wp_error( $result ) ) {
            $this->redirect( $pid, $result->get_error_message(), 'error' );
        }
        $this->redirect(
            $pid,
            sprintf( '%d kampüs işlendi; %d yeni plan, %d güncelleme.', (int)$result['campuses'], (int)$result['created'], (int)$result['updated'] ),
            'success'
        );
    }

    public function save_rows() {
        $this->guard();
        $pid = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_school_print_rows_'.$pid );
        $count = MMC_School_Planning_Service::save_plan_rows( $pid, $_POST['rows'] ?? array() );
        $this->redirect( $pid, $count . ' kampüsün baskı adedi güncellendi.', 'success' );
    }

    private function notice() {
        if ( empty( $_GET['mmc_notice'] ) ) { return; }
        $type = sanitize_key( $_GET['mmc_type'] ?? 'success' );
        $class = 'error' === $type ? 'notice-error' : ( 'warning' === $type ? 'notice-warning' : 'notice-success' );
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html( wp_unslash($_GET['mmc_notice']) ) . '</p></div>';
    }

    private function redirect( $pid, $message, $type='success' ) {
        wp_safe_redirect( add_query_arg(
            array(
                'page'       => 'mmc-school-print-plan',
                'program_id' => absint($pid),
                'mmc_notice' => $message,
                'mmc_type'   => $type,
            ),
            admin_url('admin.php')
        ) );
        exit;
    }
}
