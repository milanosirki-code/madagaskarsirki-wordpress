<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Integrity_Admin {
    public function __construct() {
        add_action( 'admin_init', array( 'MMC_Integrity_Service', 'remember_context' ), 5 );
        add_action( 'admin_init', array( 'MMC_Integrity_Service', 'maybe_redirect_to_context' ), 20 );
        add_action( 'admin_menu', array( $this, 'menu' ), 90 );
        add_action( 'admin_notices', array( $this, 'context_bar' ), 5 );
        add_action( 'admin_post_mmc_repair_school_bridge', array( $this, 'repair_school_bridge' ) );
    }

    public function menu() {
        add_submenu_page( 'mmc-dashboard', 'Program Bütünlük Merkezi', 'Program Bütünlüğü', 'mmc_view_programs', 'mmc-integrity', array( $this, 'page' ) );
    }

    public function context_bar() {
        if ( ! current_user_can( 'mmc_view_programs' ) ) { return; }
        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        if ( 0 !== strpos( $page, 'mmc-' ) && 0 !== strpos( $page, 'mad-okul' ) ) { return; }

        $program_id = MMC_Integrity_Service::active_program_id();
        if ( ! $program_id ) { return; }
        $p = MMC_Program_Service::get_program( $program_id );
        if ( ! $p ) { return; }
        $summary = MMC_Integrity_Service::summary( $program_id );
        ?>
        <div class="notice notice-info inline" style="border-left-color:#4f63e6;padding:10px 14px;margin:10px 0 14px">
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <strong>Aktif Program:</strong>
                <span><?php echo esc_html( $p->program_code ); ?></span>
                <span>MMC ID: <strong><?php echo (int)$program_id; ?></strong></span>
                <span><?php echo esc_html( $p->province_name . ' / ' . ( $p->district_name ?: 'Genel' ) ); ?></span>
                <span><?php echo esc_html( $p->planned_date ?: 'Tarih bekliyor' ); ?></span>
                <span>✅ <?php echo (int)$summary['ok']; ?> · ⚠️ <?php echo (int)$summary['warning']; ?> · ❌ <?php echo (int)$summary['critical']; ?></span>
            </div>
            <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
                <?php foreach ( MMC_Integrity_Service::shortcuts( $program_id ) as $label=>$url ) : ?>
                    <a class="button button-small" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function page() {
        if ( ! current_user_can( 'mmc_view_programs' ) ) { wp_die( 'Yetkisiz işlem.' ); }
        $programs = MMC_Program_Service::all_programs();
        $program_id = MMC_Integrity_Service::active_program_id();
        $program = $program_id ? MMC_Program_Service::get_program( $program_id ) : null;
        ?>
        <div class="wrap mmc-wrap">
            <h1>Program Bütünlük Merkezi</h1>
            <p class="description">Tek gerçek kimlik <strong>MMC Program ID</strong>'dir. Hazırlık, salon, etkinlik, yayın, reklam, okul/rota, satış, Kommo, operasyon ve finans bağlantıları bu kimliğe göre doğrulanır.</p>

            <div class="mmc-panel">
                <form method="get" action="<?php echo esc_url( admin_url('admin.php') ); ?>" class="mmc-inline-form">
                    <input type="hidden" name="page" value="mmc-integrity">
                    <label>Program
                        <select name="program_id" required>
                            <option value="">Program seçin</option>
                            <?php foreach ( $programs as $p ) : ?>
                                <option value="<?php echo (int)$p->id; ?>" <?php selected( $program_id, $p->id ); ?>><?php echo esc_html( $p->program_code . ' — ' . $p->province_name . ' / ' . ( $p->district_name ?: 'Genel' ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary">Kontrol Et</button>
                </form>
            </div>

            <?php if ( $program ) :
                $checks = MMC_Integrity_Service::checks( $program_id );
                $summary = MMC_Integrity_Service::summary( $program_id );
            ?>
            <div class="mmc-panel">
                <h2><?php echo esc_html( $program->program_code ); ?> — <?php echo esc_html( $program->province_name . ' / ' . ( $program->district_name ?: 'Genel' ) ); ?></h2>
                <p><strong>MMC Program ID:</strong> <?php echo (int)$program_id; ?> · <strong>Durum:</strong> <?php echo esc_html( MMC_Program_Service::statuses()[ $program->status ] ?? $program->status ); ?></p>
                <p><strong>Özet:</strong> ✅ <?php echo (int)$summary['ok']; ?> hazır · ⚠️ <?php echo (int)$summary['warning']; ?> uyarı · ❌ <?php echo (int)$summary['critical']; ?> kritik</p>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                <?php foreach ( MMC_Integrity_Service::shortcuts( $program_id ) as $label=>$url ) : ?>
                    <a class="button" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
                </div>
            </div>

            <div class="mmc-panel">
                <h2>Uçtan Uca Program ID Kontrolü</h2>
                <table class="widefat striped">
                    <thead><tr><th style="width:190px">Modül</th><th style="width:90px">Durum</th><th>Bağlantı / Kontrol</th><th style="width:210px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ( $checks as $row ) :
                        $icon = 'ok' === $row['severity'] ? '🟢' : ( 'critical' === $row['severity'] ? '🔴' : ( 'warning' === $row['severity'] ? '🟡' : '⚪' ) );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['label']); ?></strong></td>
                            <td><?php echo esc_html($icon . ' ' . strtoupper($row['severity'])); ?></td>
                            <td><?php echo esc_html($row['detail']); ?></td>
                            <td>
                                <?php if ( ! empty($row['url']) ) : ?><a class="button button-small" href="<?php echo esc_url($row['url']); ?>">Aç</a><?php endif; ?>
                                <?php if ( 'school_bridge' === $row['key'] && ! empty($row['repairable']) ) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                        <input type="hidden" name="action" value="mmc_repair_school_bridge">
                                        <input type="hidden" name="program_id" value="<?php echo (int)$program_id; ?>">
                                        <?php wp_nonce_field( 'mmc_repair_school_bridge_' . $program_id ); ?>
                                        <button class="button button-primary button-small">Bağlantıyı Onar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function repair_school_bridge() {
        if ( ! current_user_can( 'mmc_manage_field' ) && ! current_user_can( 'mmc_manage_programs' ) ) { wp_die( 'Yetkisiz işlem.' ); }
        $program_id = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_repair_school_bridge_' . $program_id );
        $result = MMC_Integrity_Service::repair_school_bridge( $program_id );
        $args = array( 'page'=>'mmc-integrity', 'program_id'=>$program_id, 'mmc_integrity_msg'=> is_wp_error($result) ? 'bridge_error' : 'bridge_ok' );
        if ( is_wp_error($result) ) { $args['mmc_error'] = rawurlencode( $result->get_error_message() ); }
        wp_safe_redirect( add_query_arg( $args, admin_url('admin.php') ) );
        exit;
    }
}
