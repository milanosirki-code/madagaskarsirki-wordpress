<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Report_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 60 );
        add_action( 'admin_post_mmc_report_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_mmc_report_send_test', array( $this, 'send_test' ) );
        add_action( 'admin_post_mmc_report_send_now', array( $this, 'send_now' ) );
    }

    public function menu() {
        add_submenu_page( 'mmc-dashboard', 'Gece Raporları', 'Gece Raporları', 'mmc_view_reports', 'mmc-night-reports', array( $this, 'page' ) );
    }

    public function page() {
        if ( ! current_user_can( 'mmc_view_reports' ) ) wp_die( 'Yetkiniz yok.' );
        $runs = MMC_Report_Service::recent_runs( 30 );
        $next = MMC_Report_Service::next_run();
        $view = !empty($_GET['run_id']) ? MMC_Report_Service::get_run( absint($_GET['run_id']) ) : null;
        ?>
        <div class="wrap mmc-wrap"><h1>Gece Rapor Motoru</h1>
        <?php $this->notice(); ?>
        <p class="mmc-lead">MMC her gece önceki günün satış, reklam, saha, operasyon, finans ve risk verilerini tek e-postada toplar. Sabah ChatGPT otomasyonu bu e-postayı okuyabilir.</p>
        <div class="mmc-cards">
            <div class="mmc-card"><span>Alıcı</span><strong style="font-size:16px"><?php echo esc_html(MMC_Report_Service::recipient()); ?></strong></div>
            <div class="mmc-card"><span>Gece Çalışma Saati</span><strong><?php echo esc_html(sprintf('%02d:00',MMC_Report_Service::report_hour())); ?></strong></div>
            <div class="mmc-card"><span>Sonraki Planlı Çalışma</span><strong style="font-size:16px"><?php echo $next?esc_html(wp_date('d.m.Y H:i',$next)):'Planlanmadı'; ?></strong></div>
            <div class="mmc-card"><span>WP-Cron</span><strong><?php echo wp_next_scheduled(MMC_Report_Service::CRON_HOOK)?'Aktif':'Bekliyor'; ?></strong></div>
        </div>

        <?php if(current_user_can('mmc_manage_settings')): ?>
        <div class="mmc-grid-2">
          <div class="mmc-panel"><h2>Ayarlar</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mmc_report_save_settings"><?php wp_nonce_field('mmc_report_save_settings','mmc_nonce'); ?>
            <p><label>Rapor alıcısı<br><input type="email" class="regular-text" name="report_email" value="<?php echo esc_attr(MMC_Report_Service::recipient()); ?>" required></label></p>
            <p><label>Gece raporu saati<br><select name="report_hour"><?php for($h=0;$h<24;$h++): ?><option value="<?php echo esc_attr($h); ?>" <?php selected(MMC_Report_Service::report_hour(),$h); ?>><?php echo esc_html(sprintf('%02d:00',$h)); ?></option><?php endfor; ?></select></label></p>
            <p><button class="button button-primary">Ayarları Kaydet ve Yeniden Planla</button></p>
          </form></div>
          <div class="mmc-panel"><h2>Test / Manuel Çalıştırma</h2>
            <p><strong>E-posta testi</strong> yalnız kanalın çalışmasını kontrol eder; konusu “MADAGASKAR GECE RAPORU” ile başlamaz.</p>
            <form style="display:inline-block;margin-right:8px" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_report_send_test"><?php wp_nonce_field('mmc_report_send_test','mmc_nonce'); ?><button class="button">Test E-postası Gönder</button></form>
            <form style="display:inline-block" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Dünün gerçek gece raporu şimdi oluşturulup gönderilsin mi?');"><input type="hidden" name="action" value="mmc_report_send_now"><?php wp_nonce_field('mmc_report_send_now','mmc_nonce'); ?><button class="button button-primary">Dünün Raporunu Şimdi Gönder</button></form>
            <p class="description">Not: WP-Cron, site trafiği yoksa 02:00’den sonra ilk ziyaret geldiğinde çalışabilir. Sunucu cron’u ile wp-cron.php düzenli çağrılırsa zamanlama daha kesin olur.</p>
          </div>
        </div>
        <?php endif; ?>

        <div class="mmc-panel"><h2>Rapor Geçmişi</h2><div class="mmc-table-scroll"><table class="widefat striped"><thead><tr><th>Rapor Tarihi</th><th>Oluşturma</th><th>Gönderim</th><th>Alıcı</th><th>Durum</th><th>Tetik</th><th></th></tr></thead><tbody>
        <?php if(!$runs): ?><tr><td colspan="7">Henüz rapor üretilmedi.</td></tr><?php else: foreach($runs as $r): ?><tr><td><strong><?php echo esc_html(wp_date('d.m.Y',strtotime($r->report_date))); ?></strong></td><td><?php echo esc_html($r->generated_at); ?></td><td><?php echo esc_html($r->sent_at?:'—'); ?></td><td><?php echo esc_html($r->recipient); ?></td><td><?php echo esc_html($r->status); ?><?php if($r->error_message): ?><br><small class="mmc-text-danger"><?php echo esc_html($r->error_message); ?></small><?php endif; ?></td><td><?php echo esc_html($r->trigger_type); ?></td><td><a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'mmc-night-reports','run_id'=>$r->id),admin_url('admin.php'))); ?>">Görüntüle</a></td></tr><?php endforeach; endif; ?>
        </tbody></table></div></div>

        <?php if($view): ?><div class="mmc-panel"><h2><?php echo esc_html($view->subject); ?></h2><p><strong>Durum:</strong> <?php echo esc_html($view->status); ?> · <strong>Alıcı:</strong> <?php echo esc_html($view->recipient); ?></p><div style="border:1px solid #ddd;background:#fff;padding:10px;overflow:auto"><?php echo wp_kses_post($view->body_html); ?></div></div><?php endif; ?>
        </div>
        <?php
    }

    public function save_settings() {
        $this->guard_settings(); check_admin_referer('mmc_report_save_settings','mmc_nonce');
        $email=sanitize_email(wp_unslash($_POST['report_email']??'')); if(!$email) wp_die('Geçerli e-posta girin.');
        $hour=max(0,min(23,absint($_POST['report_hour']??2)));
        update_option('mmc_nightly_report_email',$email,false); update_option('mmc_nightly_report_hour',$hour,false); MMC_Report_Service::reschedule();
        $this->redirect('settings_saved');
    }
    public function send_test() { $this->guard_settings(); check_admin_referer('mmc_report_send_test','mmc_nonce'); $r=MMC_Report_Service::send_test_email(); $this->redirect(is_wp_error($r)?'test_failed':'test_sent',is_wp_error($r)?$r->get_error_message():''); }
    public function send_now() { $this->guard_settings(); check_admin_referer('mmc_report_send_now','mmc_nonce'); $r=MMC_Report_Service::generate_and_send(MMC_Report_Service::yesterday_date(),'manual',true); $this->redirect(is_wp_error($r)?'send_failed':'report_sent',is_wp_error($r)?$r->get_error_message():''); }
    private function guard_settings(){ if(!current_user_can('mmc_manage_settings')) wp_die('Yetkiniz yok.'); }
    private function redirect($msg,$error=''){wp_safe_redirect(add_query_arg(array('page'=>'mmc-night-reports','mmc_report_msg'=>$msg,'mmc_report_error'=>$error),admin_url('admin.php')));exit;}
    private function notice(){ $m=sanitize_key(wp_unslash($_GET['mmc_report_msg']??'')); if(!$m)return; $map=array('settings_saved'=>array('success','Ayarlar kaydedildi ve gece görevi yeniden planlandı.'),'test_sent'=>array('success','Test e-postası wp_mail() tarafından kabul edildi.'),'test_failed'=>array('error','Test e-postası gönderilemedi.'),'report_sent'=>array('success','Gece raporu oluşturuldu ve gönderim isteği kabul edildi.'),'send_failed'=>array('error','Gece raporu gönderilemedi.')); if(!isset($map[$m]))return; list($type,$text)=$map[$m]; $err=sanitize_text_field(wp_unslash($_GET['mmc_report_error']??'')); echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($text.($err?' '.$err:'')).'</p></div>'; }
}
