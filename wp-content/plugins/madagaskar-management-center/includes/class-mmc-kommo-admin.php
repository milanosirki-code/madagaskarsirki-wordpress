<?php
if ( ! defined('ABSPATH') ) { exit; }

class MMC_Kommo_Admin {
    public function __construct() {
        add_action('admin_menu',array($this,'menu'));
        add_action('admin_post_mmc_kommo_sync_now',array($this,'handle_sync_now'));
        add_action('admin_post_mmc_kommo_mark_refreshed',array($this,'handle_mark_refreshed'));
        add_action('admin_post_mmc_kommo_save_settings',array($this,'handle_save_settings'));
        add_action('admin_post_mmc_kommo_test',array($this,'handle_test'));
        add_action('admin_post_mmc_kommo_update_template',array($this,'handle_update_template'));
    }

    public function menu(){
        add_submenu_page('mmc-dashboard','Kommo & AI','Kommo & AI','mmc_manage_kommo','mmc-kommo',array($this,'page'));
    }

    public function page(){
        $this->guard('mmc_manage_kommo');
        $programs=MMC_Program_Service::all_programs();
        $pid=isset($_GET['program_id'])?absint($_GET['program_id']):0;
        $program=$pid?MMC_Program_Service::get_program($pid):null;
        ?>
        <div class="wrap mmc-wrap"><h1>Kommo & AI</h1><?php $this->notice(); ?>
            <div class="mmc-panel">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="mmc-inline-form"><input type="hidden" name="page" value="mmc-kommo"><label>Program <select name="program_id" required><option value="">Program seçin</option><?php foreach($programs as $p): ?><option value="<?php echo esc_attr($p->id); ?>" <?php selected($pid,$p->id); ?>><?php echo esc_html($p->program_code.' — '.$p->province_name.' / '.($p->district_name?:'Genel')); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Aç</button></form>
            </div>
            <?php if($program)$this->render_program($program); ?>
            <?php $this->render_settings(); ?>
        </div><?php
    }

    private function render_program($program){
        $profile=MMC_Kommo_Service::ensure_profile($program->id);
        if(is_wp_error($profile)){echo '<div class="notice notice-error"><p>'.esc_html($profile->get_error_message()).'</p></div>';return;}
        $event=MMC_Event_Service::event_for_program($program->id);
        $templates=MMC_Kommo_Service::templates($program->id);
        $queue=MMC_Kommo_Service::queue_rows($program->id,12);
        $keywords=MMC_Kommo_Service::search_keywords($program->id);
        $source=MMC_Kommo_Service::build_source_text($program->id);
        ?>
        <div class="mmc-panel mmc-hero-panel"><div><small><?php echo esc_html($program->program_code); ?></small><h2><?php echo esc_html($program->province_name.' / '.($program->district_name?:'Genel')); ?></h2></div><div><strong>Kommo:</strong> <?php echo MMC_Kommo_Service::configured()?'🟢 API yapılandırıldı':'🟡 API yapılandırma bekliyor'; ?></div></div>

        <div class="mmc-cards">
            <div class="mmc-card"><span>CRM Program Kaydı</span><strong><?php echo esc_html($profile->crm_status); ?></strong><small><?php echo $profile->kommo_lead_id?esc_html('#'.$profile->kommo_lead_id):'ID yok'; ?></small></div>
            <div class="mmc-card"><span>AI Kaynak</span><strong><?php echo esc_html($profile->ai_source_status); ?></strong><small><?php echo $profile->ai_source_id?esc_html('#'.$profile->ai_source_id):'Henüz eklenmedi'; ?></small></div>
            <div class="mmc-card"><span>Kaynak Hash</span><strong><?php echo esc_html(substr($profile->source_hash,0,10)); ?></strong><small>Program verisi değişince değişir</small></div>
            <div class="mmc-card"><span>Son Senkron</span><strong><?php echo esc_html($profile->last_synced_at?:'-'); ?></strong></div>
        </div>

        <div class="mmc-grid-2">
            <div class="mmc-panel"><h2>1. Kommo AI Kaynak URL'si</h2><p>Kommo AI bu sabit adresi kaynak olarak kullanır. İçerik her zaman MMC'deki güncel Program Dosyasından üretilir.</p><p><input style="width:100%" readonly value="<?php echo esc_attr($profile->source_url); ?>"></p><p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url($profile->source_url); ?>">Kaynağı Aç</a></p><?php if('refresh_needed'===$profile->ai_source_status): ?><div class="notice notice-warning inline"><p>MMC verisi Kommo'ya son taratılan sürümden farklı. Kommo → Settings → Kommo AI içinde bu URL kaynağını <strong>yeniden tara/güncelle</strong>; sonra aşağıdaki butonla doğrulayın.</p></div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_kommo_mark_refreshed"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_kommo_mark_refreshed_'.$program->id,'mmc_nonce'); ?><button class="button">Kommo'da Yeniden Tarandı Olarak İşaretle</button></form><?php endif; ?></div>
            <div class="mmc-panel"><h2>2. Arama Kelimeleri</h2><p><?php echo esc_html(implode(' • ',$keywords)); ?></p><p class="description">Şehir/ilçe/salon ve Türkçe karakter varyasyonları program verisinden otomatik üretilir.</p></div>
        </div>

        <div class="mmc-panel"><h2>3. AI Kaynak Önizleme</h2><textarea readonly rows="22" style="width:100%;font-family:monospace"><?php echo esc_textarea($source); ?></textarea></div>

        <div class="mmc-panel"><h2>4. Senkronizasyon</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_kommo_sync_now"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_kommo_sync_now_'.$program->id,'mmc_nonce'); ?><button class="button button-primary button-hero">Kommo Senkronunu Şimdi Çalıştır</button></form><?php if($profile->last_error): ?><p class="mmc-error"><strong>Son hata:</strong> <?php echo esc_html($profile->last_error); ?></p><?php endif; ?><p class="description">Program, etkinlik, seans, fiyat, salon veya satış entegrasyonu değiştiğinde senkron işi otomatik kuyruğa alınır.</p></div>

        <div class="mmc-panel"><h2>5. Onaylı WhatsApp Şablonları</h2><table class="widefat striped"><thead><tr><th>Şablon</th><th>Durum</th><th>Kommo/Meta Adı</th><th>Not</th><th></th></tr></thead><tbody><?php foreach($templates as $t): ?><tr><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_kommo_update_template"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><input type="hidden" name="template_id" value="<?php echo esc_attr($t->id); ?>"><?php wp_nonce_field('mmc_kommo_update_template_'.$t->id,'mmc_nonce'); ?><td><strong><?php echo esc_html($t->template_key); ?></strong></td><td><select name="status"><option value="expected" <?php selected($t->status,'expected'); ?>>Beklenen</option><option value="verified" <?php selected($t->status,'verified'); ?>>Doğrulandı</option><option value="inactive" <?php selected($t->status,'inactive'); ?>>Pasif</option><option value="error" <?php selected($t->status,'error'); ?>>Hata</option></select></td><td><input name="external_name" value="<?php echo esc_attr($t->external_name); ?>"></td><td><input name="notes" value="<?php echo esc_attr($t->notes); ?>"></td><td><button class="button button-small">Kaydet</button></td></form></tr><?php endforeach; ?></tbody></table></div>

        <div class="mmc-panel"><h2>6. Son Kommo Kuyruğu</h2><table class="widefat striped"><thead><tr><th>Tarih</th><th>İş</th><th>Durum</th><th>Deneme</th><th>Hata</th></tr></thead><tbody><?php if(!$queue): ?><tr><td colspan="5">Kuyruk boş.</td></tr><?php else: foreach($queue as $q): ?><tr><td><?php echo esc_html($q->created_at); ?></td><td><?php echo esc_html($q->job_type); ?></td><td><?php echo esc_html($q->status); ?></td><td><?php echo esc_html($q->attempts); ?></td><td><?php echo esc_html($q->last_error); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
        <?php
    }

    private function render_settings(){
        if(!current_user_can('mmc_manage_settings'))return;

        $sub=MMC_Kommo_Service::subdomain();
        $pipeline=absint(get_option('mmc_kommo_pipeline_id',0));
        $status=absint(get_option('mmc_kommo_status_id',0));
        $mode=get_option('mmc_kommo_ai_mode','suggested_reply');
        $diag=MMC_Kommo_Service::connection_diagnostics();
        $pipe=MMC_Kommo_Service::pipeline_diagnostics();

        ?>
        <div class="mmc-panel">
            <h2>Kommo Entegrasyon Ayarları</h2>
            <p><strong>Güvenlik:</strong> erişim tokenı WordPress veritabanına kaydedilmez. MMC önce <code>MMC_KOMMO_TOKEN</code>, geçiş döneminde ise mevcut <code>MS_KOMMO_TOKEN</code> sabitini salt-okunur kullanabilir.</p>

            <div class="mmc-cards">
                <div class="mmc-card">
                    <span>API Bağlantısı</span>
                    <strong><?php echo !empty($diag['connected'])?'🟢 Bağlı':'🟡 Bekliyor'; ?></strong>
                    <small><?php echo esc_html(!empty($diag['account_name'])?$diag['account_name']:($diag['error']?:'-')); ?></small>
                </div>
                <div class="mmc-card">
                    <span>Subdomain</span>
                    <strong><?php echo esc_html($diag['subdomain']?:'-'); ?></strong>
                    <small><?php echo esc_html($diag['subdomain_source']?:'Kaynak yok'); ?></small>
                </div>
                <div class="mmc-card">
                    <span>Token Kaynağı</span>
                    <strong><?php echo esc_html($diag['token_source']?:'Tanımlı değil'); ?></strong>
                    <small>Token değeri ekranda gösterilmez.</small>
                </div>
                <div class="mmc-card">
                    <span>Program Pipeline</span>
                    <strong><?php echo $pipeline?esc_html('#'.$pipeline):'Tanımlı değil'; ?></strong>
                    <small><?php echo !empty($pipe['pipeline_name'])?esc_html($pipe['pipeline_name']):'MMC program lead senkronu için opsiyonel'; ?></small>
                </div>
            </div>

            <?php if(!empty($diag['uses_legacy_token'])): ?>
                <div class="notice notice-warning inline">
                    <p><strong>Secret geçişi gerekli:</strong> Canlı Kommo bağlantısı legacy <code>MS_KOMMO_TOKEN</code> üzerinden çalışıyor. Yeni/yenilenmiş tokenı <code>wp-config.php</code> içine <code>MMC_KOMMO_TOKEN</code> olarak taşıdıktan sonra eski snippet içindeki tokenı kaldırın. MMC bu geçiş sırasında bağlantıyı kesmeden fallback yapar.</p>
                </div>
            <?php endif; ?>

            <?php if(!$pipeline && !empty($diag['legacy_pipeline_id'])): ?>
                <div class="notice notice-info inline">
                    <p><strong>Legacy pipeline bulundu:</strong> <code>#<?php echo (int)$diag['legacy_pipeline_id']; ?></code>. Bu değer mevcut WooCommerce/Order kartları için kullanılıyor olabilir; MMC bunu Program Pipeline olarak otomatik kullanmaz.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mmc-form-grid">
                <input type="hidden" name="action" value="mmc_kommo_save_settings">
                <?php wp_nonce_field('mmc_kommo_save_settings','mmc_nonce'); ?>
                <label>Kommo Subdomain
                    <input name="subdomain" value="<?php echo esc_attr($sub); ?>" placeholder="milanosirki">
                </label>
                <label>Program Pipeline ID
                    <input type="number" min="0" name="pipeline_id" value="<?php echo esc_attr($pipeline); ?>">
                </label>
                <label>Varsayılan Status ID
                    <input type="number" min="0" name="status_id" value="<?php echo esc_attr($status); ?>">
                </label>
                <label>AI Kaynak Kullanımı
                    <select name="ai_mode">
                        <option value="suggested_reply" <?php selected($mode,'suggested_reply'); ?>>Suggested Reply</option>
                        <option value="agent" <?php selected($mode,'agent'); ?>>AI Agent</option>
                    </select>
                </label>
                <div><button class="button button-primary">Ayarları Kaydet</button></div>
            </form>

            <h3>Güvenli wp-config Geçiş Şablonu</h3>
            <pre>define('MMC_KOMMO_SUBDOMAIN', '<?php echo esc_html($sub?:'milanosirki'); ?>');
define('MMC_KOMMO_TOKEN', 'YENI_UZUN_OMURLU_TOKEN');</pre>
            <p class="description">Eski tokenı buraya kopyalamak yerine Kommo'dan yeni/yenilenmiş uzun ömürlü token kullanılması önerilir. Tokenı sohbet, ekran görüntüsü veya veritabanı alanına yazmayın.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mmc_kommo_test">
                <?php wp_nonce_field('mmc_kommo_test','mmc_nonce'); ?>
                <button class="button">Kommo Bağlantısını Yeniden Test Et</button>
            </form>

            <?php if(!empty($diag['checked_at'])): ?>
                <p class="description">Son canlı test: <?php echo esc_html($diag['checked_at']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_sync_now(){ $this->guard('mmc_manage_kommo'); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_kommo_sync_now_'.$pid,'mmc_nonce'); MMC_Kommo_Service::sync_now($pid); wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','program_id'=>$pid,'mmc_msg'=>'kommo_sync'),admin_url('admin.php'))); exit; }
    public function handle_mark_refreshed(){ $this->guard('mmc_manage_kommo'); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_kommo_mark_refreshed_'.$pid,'mmc_nonce'); $r=MMC_Kommo_Service::mark_ai_refreshed($pid); $this->redirect($pid,$r,'ai_refreshed'); }
    public function handle_update_template(){ $this->guard('mmc_manage_kommo'); $id=absint($_POST['template_id']??0); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_kommo_update_template_'.$id,'mmc_nonce'); $r=MMC_Kommo_Service::update_template($id,sanitize_key($_POST['status']??''),wp_unslash($_POST['external_name']??''),wp_unslash($_POST['notes']??'')); $this->redirect($pid,$r,'template_saved'); }
    public function handle_save_settings(){ $this->guard('mmc_manage_settings'); check_admin_referer('mmc_kommo_save_settings','mmc_nonce'); update_option('mmc_kommo_subdomain',sanitize_title(wp_unslash($_POST['subdomain']??'')),false); update_option('mmc_kommo_pipeline_id',absint($_POST['pipeline_id']??0),false); update_option('mmc_kommo_status_id',absint($_POST['status_id']??0),false); $mode=sanitize_key($_POST['ai_mode']??'suggested_reply'); update_option('mmc_kommo_ai_mode',in_array($mode,array('suggested_reply','agent'),true)?$mode:'suggested_reply',false); wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','mmc_msg'=>'settings_saved'),admin_url('admin.php'))); exit; }
    public function handle_test(){ $this->guard('mmc_manage_settings'); check_admin_referer('mmc_kommo_test','mmc_nonce'); $r=MMC_Kommo_Service::connection_diagnostics(true); $args=array('page'=>'mmc-kommo'); if(empty($r['connected']))$args['mmc_error']=$r['error']?:'Kommo API bağlantısı doğrulanamadı.'; else $args['mmc_msg']='connection_ok'; wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }

    private function redirect($pid,$r,$ok){$args=array('page'=>'mmc-kommo','program_id'=>$pid); if(is_wp_error($r))$args['mmc_error']=$r->get_error_message(); else $args['mmc_msg']=$ok; wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;}
    private function guard($cap){if(!(current_user_can($cap)||current_user_can('mmc_manage_programs')))wp_die('Bu işlemi yapma yetkiniz yok.');}
    private function notice(){ if(!empty($_GET['mmc_error']))echo '<div class="notice notice-error"><p>'.esc_html(wp_unslash($_GET['mmc_error'])).'</p></div>'; if(!empty($_GET['mmc_msg'])){$m=array('kommo_sync'=>'Kommo senkron kuyruğu çalıştırıldı.','ai_refreshed'=>'AI kaynak yeniden tarandı olarak işaretlendi.','template_saved'=>'Şablon durumu güncellendi.','settings_saved'=>'Kommo ayarları kaydedildi.','connection_ok'=>'Kommo API bağlantısı başarılı.');$k=sanitize_key($_GET['mmc_msg']);echo '<div class="notice notice-success"><p>'.esc_html($m[$k]??'İşlem tamamlandı.').'</p></div>';}}
}
