<?php
if ( ! defined('ABSPATH') ) { exit; }

class MMC_Kommo_Admin {
    public function __construct() {
        add_action('admin_menu',array($this,'menu'));
        add_action('admin_post_mmc_kommo_sync_now',array($this,'handle_sync_now'));
        add_action('admin_post_mmc_kommo_mark_refreshed',array($this,'handle_mark_refreshed'));
        add_action('admin_post_mmc_kommo_create_text_source',array($this,'handle_create_text_source'));
        add_action('admin_post_mmc_kommo_save_settings',array($this,'handle_save_settings'));
        add_action('admin_post_mmc_kommo_test',array($this,'handle_test'));
        add_action('admin_post_mmc_kommo_refresh_catalog',array($this,'handle_refresh_catalog'));
        add_action('admin_post_mmc_kommo_install_program_pipeline',array($this,'handle_install_program_pipeline'));
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
            <?php $this->render_settings($pid); ?>
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
        $bridge=MMC_Kommo_Service::status_bridge_preview($program->id);
        $ai_transport=MMC_Kommo_Service::ai_transport_mode($program->id);
        $direct_text=MMC_Kommo_Service::direct_text_source_state($program->id);
        ?>
        <div class="mmc-panel mmc-hero-panel"><div><small><?php echo esc_html($program->program_code); ?></small><h2><?php echo esc_html($program->province_name.' / '.($program->district_name?:'Genel')); ?></h2></div><div><strong>Kommo:</strong> <?php echo MMC_Kommo_Service::configured()?'🟢 API yapılandırıldı':'🟡 API yapılandırma bekliyor'; ?></div></div>

        <div class="mmc-cards">
            <div class="mmc-card"><span>CRM Program Kaydı</span><strong><?php echo esc_html($profile->crm_status); ?></strong><small><?php echo $profile->kommo_lead_id?esc_html('#'.$profile->kommo_lead_id):'ID yok'; ?></small></div>
            <div class="mmc-card"><span>AI Kaynak</span><strong><?php echo esc_html($profile->ai_source_status); ?></strong><small><?php echo $profile->ai_source_id?esc_html('#'.$profile->ai_source_id):'Henüz eklenmedi'; ?></small></div>
            <div class="mmc-card"><span>Kaynak Hash</span><strong><?php echo esc_html(substr($profile->source_hash,0,10)); ?></strong><small>Program verisi değişince değişir</small></div>
            <div class="mmc-card"><span>Son Senkron</span><strong><?php echo esc_html($profile->last_synced_at?:'-'); ?></strong></div>
        </div>

        <?php if(is_wp_error($bridge)): ?>
            <div class="notice notice-warning inline"><p><strong>Kommo Durum Köprüsü:</strong> <?php echo esc_html($bridge->get_error_message()); ?></p></div>
        <?php else:
            $action_labels=array(
                'create'=>'Yeni kart hedef aşamada oluşturulacak',
                'advance'=>'İleri taşı',
                'stay'=>'Aynı aşamada bırak',
                'preserve_ahead'=>'Geri alma engellendi',
                'pipeline_mismatch'=>'Pipeline uyuşmazlığı — otomatik taşıma yok',
                'unknown_current_status'=>'Mevcut status bilinmiyor — otomatik taşıma yok',
                'non_mmc_status'=>'MMC dışı status — otomatik taşıma yok',
                'manual_cancel'=>'İptal — manuel yönetim'
            );
        ?>
            <div class="mmc-panel">
                <h2>Kommo Program Durum Köprüsü</h2>
                <div class="mmc-cards">
                    <div class="mmc-card"><span>MMC Program Durumu</span><strong><?php echo esc_html($bridge['program_status_name']?:$bridge['program_status']); ?></strong><small><?php echo esc_html($bridge['program_status']); ?></small></div>
                    <div class="mmc-card"><span>Kommo Mevcut Aşama</span><strong><?php echo esc_html($bridge['current_stage']?:($bridge['lead_id']?'Bilinmiyor':'Kart yok')); ?></strong><small><?php echo !empty($bridge['current_status_id'])?'#'.(int)$bridge['current_status_id']:'-'; ?></small></div>
                    <div class="mmc-card"><span>Hedef Aşama</span><strong><?php echo esc_html($bridge['desired_stage']?:'Manuel'); ?></strong><small><?php echo !empty($bridge['desired_status_id'])?'#'.(int)$bridge['desired_status_id']:'-'; ?></small></div>
                    <div class="mmc-card"><span>Köprü Kararı</span><strong><?php echo esc_html($action_labels[$bridge['action']]??$bridge['action']); ?></strong><small><?php echo esc_html($bridge['reason']); ?></small></div>
                </div>
                <?php if('advance'===$bridge['action']): ?>
                    <div class="notice notice-info inline"><p>Bir sonraki Kommo senkronunda kart yalnız ileri yönde <strong><?php echo esc_html($bridge['desired_stage']); ?></strong> aşamasına taşınacak.</p></div>
                <?php elseif('preserve_ahead'===$bridge['action']): ?>
                    <div class="notice notice-success inline"><p>Kommo kartı MMC durumundan ileride. Normal senkron kartı geriye çekmeyecek.</p></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mmc-grid-2">
            <?php
            $source_delivery=MMC_Kommo_Service::source_delivery_diagnostics($program->id);
            $source_last_hit=MMC_Kommo_Service::source_delivery_last_hit($program->id);
            ?>
            <div class="mmc-panel">
                <h2>1. Kommo AI Kaynak URL'si</h2>
                <p><?php echo 'text'===$ai_transport?'URL kaynağı tanı/yedek amaçlı tutulur; aktif AI taşıma yöntemi doğrudan metindir.':'Kommo AI bu sabit adresi URL kaynağı olarak kullanabilir. İçerik her zaman MMC’deki güncel Program Dosyasından üretilir.'; ?></p>
                <p><input style="width:100%" readonly value="<?php echo esc_attr($profile->source_url); ?>"></p>
                <p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url($profile->source_url); ?>">Kaynağı Aç</a></p>
                <?php if(!is_wp_error($source_delivery)): ?><p class="description"><strong>URL teslim durumu:</strong> semantik HTML · crawler-okunabilir · <?php echo esc_html($source_delivery['content_type']); ?> · dil <?php echo esc_html($source_delivery['content_language']); ?> · robots engeli yok · tokenlı adres.</p><?php endif; ?>
                <?php if(!empty($source_last_hit['at'])): ?><p class="description"><strong>Son kaynak isteği:</strong> <?php echo esc_html($source_last_hit['at']); ?><?php if(!empty($source_last_hit['user_agent'])): ?> · UA: <code><?php echo esc_html($source_last_hit['user_agent']); ?></code><?php endif; ?></p><?php endif; ?>
                <?php if('url'===$ai_transport && 'refresh_needed'===$profile->ai_source_status): ?>
                    <div class="notice notice-warning inline"><p>MMC verisi Kommo’ya son taratılan URL sürümünden farklı. URL taşımasını kullanacaksanız Kommo’da yeniden tara/güncelle işlemi gerekir.</p></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_kommo_mark_refreshed"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_kommo_mark_refreshed_'.$program->id,'mmc_nonce'); ?><button class="button">Kommo'da Yeniden Tarandı Olarak İşaretle</button></form>
                <?php endif; ?>
            </div>

            <div class="mmc-panel">
                <h2>2. Doğrudan Metin Kaynağı</h2>
                <?php if('text'===$ai_transport && !empty($direct_text['source_id'])): ?>
                    <div class="notice notice-success inline"><p><strong>Aktif taşıma: Direct Text API</strong> · Source #<?php echo esc_html($direct_text['source_id']); ?> · <?php echo esc_html($direct_text['available_function']); ?> · oluşturma <?php echo esc_html($direct_text['created_at']); ?></p></div>
                    <p><strong>Kaynak adı:</strong> <?php echo esc_html($direct_text['source_name']); ?></p>
                    <p><strong>Hash:</strong> <code><?php echo esc_html(substr($direct_text['source_hash'],0,16)); ?></code></p>
                    <?php if($direct_text['source_hash']!==$profile->source_hash): ?>
                        <div class="notice notice-warning inline"><p>MMC Program Dosyası bu direct-text kaynağından sonra değişmiş. Dokümante edilmemiş bir update/delete endpoint kullanılmadığı için otomatik kopya kaynak oluşturulmadı; durum <strong>refresh_needed</strong> olarak tutulur.</p></div>
                    <?php else: ?>
                        <p class="description">MMC kaynağı ile Kommo direct-text hash’i eşleşiyor.</p>
                    <?php endif; ?>
                    <?php if(!empty($direct_text['legacy_url_source_id'])): ?><p class="description">Önceki URL source ID: <code>#<?php echo esc_html($direct_text['legacy_url_source_id']); ?></code>. Direct-text kaynağı Kommo’da görüldükten sonra eski hatalı URL kaynağı Kommo arayüzünden kaldırılabilir.</p><?php endif; ?>
                <?php else: ?>
                    <div class="notice notice-info inline"><p>URL crawler hatasını bypass etmek için MMC Program Dosyası, Kommo’nun resmî <code>/api/v2/sources/text</code> endpoint’ine doğrudan gönderilebilir. Bu işlem yalnız yeni bir text source oluşturur; mevcut URL kaynağını API ile silmez.</p></div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Kommo’da yeni MMC direct-text bilgi kaynağı oluşturulsun mu?');">
                        <input type="hidden" name="action" value="mmc_kommo_create_text_source">
                        <input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>">
                        <?php wp_nonce_field('mmc_kommo_create_text_source_'.$program->id,'mmc_nonce'); ?>
                        <p><label><input type="checkbox" name="confirm_create" value="1" required> Kommo’da bu program için yeni bir doğrudan metin bilgi kaynağı oluşturulacağını onaylıyorum.</label></p>
                        <p><label>Onay metni: <input type="text" name="confirm_text" value="" placeholder="MMC TEXT SOURCE KUR" required></label></p>
                        <button class="button button-primary">Direct Text Source Oluştur</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="mmc-panel"><h2>3. Arama Kelimeleri</h2><p><?php echo esc_html(implode(' • ',$keywords)); ?></p><p class="description">Şehir/ilçe/salon ve Türkçe karakter varyasyonları program verisinden otomatik üretilir.</p></div>
        </div>

        <div class="mmc-panel"><h2>4. AI Kaynak Önizleme</h2><textarea readonly rows="22" style="width:100%;font-family:monospace"><?php echo esc_textarea($source); ?></textarea></div>

        <div class="mmc-panel"><h2>5. Senkronizasyon</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_kommo_sync_now"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_kommo_sync_now_'.$program->id,'mmc_nonce'); ?><button class="button button-primary button-hero">Kommo Senkronunu Şimdi Çalıştır</button></form><?php if($profile->last_error): ?><p class="mmc-error"><strong>Son hata:</strong> <?php echo esc_html($profile->last_error); ?></p><?php endif; ?><p class="description">Program, etkinlik, seans, fiyat, salon veya satış entegrasyonu değiştiğinde senkron işi otomatik kuyruğa alınır.</p></div>

        <div class="mmc-panel"><h2>6. Onaylı WhatsApp Şablonları</h2><table class="widefat striped"><thead><tr><th>Şablon</th><th>Durum</th><th>Kommo/Meta Adı</th><th>Not</th><th></th></tr></thead><tbody><?php foreach($templates as $t): ?><tr><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_kommo_update_template"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><input type="hidden" name="template_id" value="<?php echo esc_attr($t->id); ?>"><?php wp_nonce_field('mmc_kommo_update_template_'.$t->id,'mmc_nonce'); ?><td><strong><?php echo esc_html($t->template_key); ?></strong></td><td><select name="status"><option value="expected" <?php selected($t->status,'expected'); ?>>Beklenen</option><option value="verified" <?php selected($t->status,'verified'); ?>>Doğrulandı</option><option value="inactive" <?php selected($t->status,'inactive'); ?>>Pasif</option><option value="error" <?php selected($t->status,'error'); ?>>Hata</option></select></td><td><input name="external_name" value="<?php echo esc_attr($t->external_name); ?>"></td><td><input name="notes" value="<?php echo esc_attr($t->notes); ?>"></td><td><button class="button button-small">Kaydet</button></td></form></tr><?php endforeach; ?></tbody></table></div>

        <div class="mmc-panel"><h2>7. Son Kommo Kuyruğu</h2><table class="widefat striped"><thead><tr><th>Tarih</th><th>İş</th><th>Durum</th><th>Deneme</th><th>Hata</th></tr></thead><tbody><?php if(!$queue): ?><tr><td colspan="5">Kuyruk boş.</td></tr><?php else: foreach($queue as $q): ?><tr><td><?php echo esc_html($q->created_at); ?></td><td><?php echo esc_html($q->job_type); ?></td><td><?php echo esc_html($q->status); ?></td><td><?php echo esc_html($q->attempts); ?></td><td><?php echo esc_html($q->last_error); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
        <?php
    }

    private function render_settings($current_program_id=0){
        if(!current_user_can('mmc_manage_settings'))return;

        $sub=MMC_Kommo_Service::subdomain();
        $pipeline=absint(get_option('mmc_kommo_pipeline_id',0));
        $status=absint(get_option('mmc_kommo_status_id',0));
        $mode=get_option('mmc_kommo_ai_mode','suggested_reply');
        $force_token_retest=!empty($_GET['mmc_token_retest']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce']??'')),'mmc_kommo_token_retest');
        $migration=MMC_Kommo_Service::token_migration_diagnostics($force_token_retest);
        $token_retest_url=wp_nonce_url(add_query_arg('mmc_token_retest','1'),'mmc_kommo_token_retest');
        $diag=MMC_Kommo_Service::connection_diagnostics($force_token_retest);
        $write_readiness=MMC_Kommo_Service::admin_write_readiness();
        $last_install=MMC_Kommo_Service::last_pipeline_install_result();
        $pipe=MMC_Kommo_Service::pipeline_diagnostics();
        $catalog=MMC_Kommo_Service::pipeline_catalog();
        $catalog_error=is_wp_error($catalog)?$catalog->get_error_message():'';
        if(is_wp_error($catalog))$catalog=array();

        $selected_statuses=array();
        foreach((array)$catalog as $p){
            if((int)$p['id']===$pipeline){
                $selected_statuses=(array)$p['statuses'];
                break;
            }
        }

        $catalog_for_js=array();
        foreach((array)$catalog as $p){
            $catalog_for_js[(string)(int)$p['id']]=array_map(function($s){
                return array('id'=>(int)$s['id'],'name'=>(string)$s['name']);
            },(array)$p['statuses']);
        }
        ?>
        <div class="mmc-panel">
            <h2>Kommo Entegrasyon Ayarları</h2>
            <p><strong>Güvenlik:</strong> erişim tokenı WordPress veritabanına kaydedilmez. Pipeline keşfi salt-okunur Kommo API çağrısıdır; Kommo'da kayıt oluşturmaz veya değiştirmez.</p>

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
                    <span>Keşfedilen Pipeline</span>
                    <strong><?php echo esc_html((string)count($catalog)); ?></strong>
                    <small><?php echo $catalog_error?esc_html($catalog_error):'Kommo API üzerinden salt-okunur'; ?></small>
                </div>
            </div>

            <div style="margin:18px 0;padding:16px;border:1px solid #dcdcde;border-radius:8px;background:#fff">
                <h3 style="margin-top:0">Kommo Token Geçiş Merkezi</h3>
                <p>Token değerleri hiçbir zaman ekranda, veritabanında veya logda gösterilmez. Her kaynak ayrı ayrı Kommo <code>/account</code> çağrısıyla doğrulanır.</p>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin:12px 0">
                    <div style="border:1px solid #dcdcde;border-radius:6px;padding:12px">
                        <strong>MMC_KOMMO_TOKEN</strong><br>
                        <?php if(!empty($migration['mmc']['defined'])): ?>
                            <span><?php echo !empty($migration['mmc']['connected'])?'🟢 Canlı':'🔴 Doğrulanamadı'; ?></span><br>
                            <small><?php echo !empty($migration['mmc']['connected'])
                                ? esc_html(($migration['mmc']['account_name']?:'Kommo').' · Account ID '.(int)$migration['mmc']['account_id'])
                                : esc_html($migration['mmc']['error']?:'API doğrulaması başarısız.'); ?></small>
                        <?php else: ?>
                            <span>⚪ Tanımlı değil</span><br><small>wp-config.php içinde bekleniyor.</small>
                        <?php endif; ?>
                    </div>

                    <div style="border:1px solid #dcdcde;border-radius:6px;padding:12px">
                        <strong>MS_KOMMO_TOKEN (legacy)</strong><br>
                        <?php if(!empty($migration['legacy']['defined'])): ?>
                            <span><?php echo !empty($migration['legacy']['connected'])?'🟢 Canlı':'🔴 Doğrulanamadı'; ?></span><br>
                            <small><?php echo !empty($migration['legacy']['connected'])
                                ? esc_html(($migration['legacy']['account_name']?:'Kommo').' · Account ID '.(int)$migration['legacy']['account_id'])
                                : esc_html($migration['legacy']['error']?:'API doğrulaması başarısız.'); ?></small>
                        <?php else: ?>
                            <span>⚪ Tanımlı değil</span><br><small>Legacy secret yok.</small>
                        <?php endif; ?>
                    </div>

                    <div style="border:1px solid #dcdcde;border-radius:6px;padding:12px">
                        <strong>Aktif Runtime Kaynağı</strong><br>
                        <span><?php echo esc_html($migration['active_source']?:'Yok'); ?></span><br>
                        <small><?php echo esc_html($migration['detail']); ?></small>
                    </div>
                </div>

                <?php
                $mig_state=(string)($migration['state']??'not_configured');
                $mig_class='notice-info';
                if('complete'===$mig_state)$mig_class='notice-success';
                elseif('ready_to_remove_legacy'===$mig_state)$mig_class='notice-success';
                elseif(in_array($mig_state,array('account_mismatch','mmc_invalid','legacy_invalid'),true))$mig_class='notice-error';
                elseif(in_array($mig_state,array('legacy_only','fallback_legacy'),true))$mig_class='notice-warning';
                ?>
                <div class="notice <?php echo esc_attr($mig_class); ?> inline">
                    <p><strong>Geçiş Durumu:</strong> <?php echo esc_html($migration['detail']); ?></p>
                    <?php if('ready_to_remove_legacy'===$mig_state && !empty($migration['same_secret'])): ?>
                        <p>İki sabit aynı secretı temsil ediyor ve aynı Kommo hesabına doğrulandı. Legacy tanımı kaldırdıktan sonra tekrar test edin.</p>
                    <?php elseif('ready_to_remove_legacy'===$mig_state): ?>
                        <p>Yeni token aynı canlı Kommo hesabına doğrulandı. Legacy tanımı kaldırılabilir; kaldırma sonrası tekrar test zorunludur.</p>
                    <?php elseif('account_mismatch'===$mig_state): ?>
                        <p><strong>Eski tokenı kaldırmayın.</strong> Yeni token farklı hesaba gidiyor; önce doğru Kommo tokenını düzeltin.</p>
                    <?php elseif('fallback_legacy'===$mig_state): ?>
                        <p><strong>Eski tokenı kaldırmayın.</strong> MMC güvenli fallback ile legacy tokenı kullanmaya devam ediyor.</p>
                    <?php elseif('legacy_only'===$mig_state): ?>
                        <p><strong>Adım 1:</strong> Mevcut veya yenilenmiş Kommo tokenını güvenli biçimde <code>wp-config.php</code> içine aşağıdaki adla ekleyin. Tokenı sohbet, snippet açıklaması veya WordPress option alanına yazmayın.</p>
                        <pre style="background:#f6f7f7;padding:10px;overflow:auto"><code>define( 'MMC_KOMMO_TOKEN', 'KOMMO_TOKEN_DEGERINI_BURAYA_YAPISTIR' );</code></pre>
                        <p>Bu satırı ekledikten sonra bu ekrana dönün ve <strong>Tokenları Yeniden Test Et</strong> düğmesine basın. Yeni kaynak yeşil olmadan <code>MS_KOMMO_TOKEN</code> tanımını kaldırmayın.</p>
                    <?php endif; ?>
                </div>

                <p>
                    <a class="button" href="<?php echo esc_url($token_retest_url); ?>">Tokenları Yeniden Test Et</a>
                </p>
            </div>

            <?php if(!$pipeline && !empty($diag['legacy_pipeline_id'])): ?>
                <div class="notice notice-info inline">
                    <p><strong>Legacy sipariş pipeline adayı:</strong> <code>#<?php echo (int)$diag['legacy_pipeline_id']; ?></code>. Bu değer otomatik seçilmez; aşağıdaki keşif listesinden adı ve amacı doğrulandıktan sonra MMC Program Pipeline olarak ayrıca seçilebilir.</p>
                </div>
            <?php endif; ?>

            <h3>Pipeline Keşif Merkezi</h3>
            <?php if($catalog_error): ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($catalog_error); ?></p></div>
            <?php elseif(!$catalog): ?>
                <p>Kommo hesabında okunabilir pipeline bulunamadı.</p>
            <?php else: ?>
                <div style="overflow:auto;margin-bottom:16px">
                    <table class="widefat striped">
                        <thead><tr><th>Pipeline</th><th>ID</th><th>Tür</th><th>Statuslar</th></tr></thead>
                        <tbody>
                        <?php foreach($catalog as $p): ?>
                            <tr<?php echo (int)$p['id']===$pipeline?' style="background:#eef7ff"':''; ?>>
                                <td><strong><?php echo esc_html($p['name']?:('Pipeline #'.(int)$p['id'])); ?></strong>
                                    <?php if(!empty($diag['legacy_pipeline_id'])&&(int)$diag['legacy_pipeline_id']===(int)$p['id']): ?>
                                        <br><small>Legacy sipariş pipeline adayı</small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo (int)$p['id']; ?></code></td>
                                <td><?php echo !empty($p['is_main'])?'Ana pipeline':'Pipeline'; ?></td>
                                <td>
                                    <?php
                                    $names=array();
                                    foreach((array)$p['statuses'] as $s){
                                        $names[]=($s['name']?:'Status').' (#'.(int)$s['id'].')';
                                    }
                                    echo esc_html(implode(' · ',$names));
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php
            $blueprint=MMC_Kommo_Service::program_pipeline_blueprint();
            $mmc_pipeline=MMC_Kommo_Service::find_program_pipeline();
            $mmc_pipeline_error=is_wp_error($mmc_pipeline)?$mmc_pipeline->get_error_message():'';
            $expected_names=array();
            foreach((array)$blueprint['stages'] as $bp_stage){
                $expected_names[]=trim((string)$bp_stage['name']);
            }
            $present_names=array();
            if(is_array($mmc_pipeline)){
                foreach((array)($mmc_pipeline['statuses']??array()) as $row){
                    $present_names[]=trim((string)($row['name']??''));
                }
            }
            $missing_names=array_values(array_filter($expected_names,function($name) use ($present_names){
                foreach($present_names as $present){
                    if(remove_accents(strtolower($present))===remove_accents(strtolower($name)))return false;
                }
                return true;
            }));
            $pipeline_ready=is_array($mmc_pipeline)&&!$missing_names;
            ?>

            <div style="margin:20px 0;padding:16px;border:1px solid #dcdcde;border-radius:8px;background:#fff">
                <h3 style="margin-top:0">MMC Program Pipeline Kurulum Merkezi</h3>
                <p>Bu işlem yalnız <strong><?php echo esc_html($blueprint['name']); ?></strong> pipeline'ını ve aşağıdaki MMC program aşamalarını oluşturur/tamamlar. Mevcut satış, WooCommerce, Kurumsal Talepler ve diğer Kommo pipeline'larına dokunmaz.</p>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:12px 0">
                    <div style="border:1px solid #dcdcde;border-radius:6px;padding:10px">
                        <strong>API Okuma</strong><br>
                        <span><?php echo !empty($diag['connected'])?'🟢 Bağlı':'🔴 Bağlantı yok'; ?></span>
                    </div>
                    <div style="border:1px solid #dcdcde;border-radius:6px;padding:10px">
                        <strong>Kommo Yazma Yetkisi Ön Kontrolü</strong><br>
                        <?php if(true===$write_readiness['verified'] && true===$write_readiness['is_admin']): ?>
                            <span>🟢 Yönetici doğrulandı<?php echo !empty($write_readiness['user_name'])?' · '.esc_html($write_readiness['user_name']):''; ?></span>
                        <?php elseif(true===$write_readiness['verified'] && false===$write_readiness['is_admin']): ?>
                            <span>🔴 Yönetici değil</span>
                        <?php else: ?>
                            <span>🟡 Doğrulanamadı</span>
                        <?php endif; ?>
                        <br><small><?php echo esc_html($write_readiness['detail']??''); ?></small>
                    </div>
                </div>

                <?php if(!empty($last_install)): ?>
                    <div class="notice <?php echo 'success'===($last_install['status']??'')?'notice-success':'notice-error'; ?> inline">
                        <p><strong>Son Kurulum Denemesi:</strong>
                            <?php echo esc_html($last_install['at']??''); ?> ·
                            <?php echo esc_html($last_install['message']??''); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if($mmc_pipeline_error): ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html($mmc_pipeline_error); ?></p></div>
                <?php elseif($pipeline_ready): ?>
                    <div class="notice notice-success inline">
                        <p><strong>Kurulu ve doğrulandı:</strong> <?php echo esc_html($mmc_pipeline['name']); ?> (#<?php echo (int)$mmc_pipeline['id']; ?>) · <?php echo count($expected_names); ?>/<?php echo count($expected_names); ?> MMC aşaması mevcut.</p>
                    </div>
                <?php elseif(is_array($mmc_pipeline)): ?>
                    <div class="notice notice-warning inline">
                        <p><strong>Pipeline bulundu ancak eksik:</strong> #<?php echo (int)$mmc_pipeline['id']; ?> · Eksik aşamalar: <?php echo esc_html(implode(', ',$missing_names)); ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-info inline">
                        <p><strong>Henüz kurulmadı.</strong> Aşağıdaki işlem Kommo hesabında yeni bir pipeline oluşturacaktır. Kommo API dokümantasyonuna göre pipeline ve stage ekleme işlemleri yönetici yetkisi gerektirir.</p>
                    </div>
                <?php endif; ?>

                <div style="display:flex;flex-wrap:wrap;gap:6px;margin:12px 0">
                    <?php foreach((array)$blueprint['stages'] as $bp_stage): ?>
                        <span style="display:inline-block;padding:5px 8px;border:1px solid #c3c4c7;border-radius:999px;background:#f6f7f7">
                            <?php echo esc_html($bp_stage['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <?php if(!$pipeline_ready): ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Kommo hesabında MMC Program Yönetimi pipeline kurulumunu başlatmak istediğinize emin misiniz?');">
                        <input type="hidden" name="action" value="mmc_kommo_install_program_pipeline">
                        <input type="hidden" name="program_id" value="<?php echo (int)$current_program_id; ?>">
                        <?php wp_nonce_field('mmc_kommo_install_program_pipeline','mmc_nonce'); ?>
                        <p>
                            <label>
                                <input type="checkbox" name="confirm_install" value="1" required>
                                Mevcut Kommo pipeline'larına dokunulmayacağını, yalnız MMC Program Yönetimi pipeline'ının oluşturulacağını/tamamlanacağını onaylıyorum.
                            </label>
                        </p>
                        <p>
                            <label>Onay metni:
                                <input type="text" name="confirm_text" required placeholder="MMC PROGRAM PIPELINE KUR" style="min-width:280px">
                            </label>
                        </p>
                        <button class="button button-primary" <?php disabled(true===$write_readiness['verified'] && false===$write_readiness['is_admin']); ?>>
                            <?php echo is_array($mmc_pipeline)?'Eksik MMC Aşamalarını Tamamla':'MMC Program Pipeline Kur'; ?>
                        </button>
                        <?php if(true===$write_readiness['verified'] && false===$write_readiness['is_admin']): ?>
                            <p class="description">Buton kapalıdır: Kommo pipeline/stage oluşturma yalnız yönetici yetkili kullanıcıyla çalışır.</p>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="description">Kurulum idempotenttir: aynı isimli MMC pipeline bulunduğunda yeni kopya oluşturulmaz. Eksik aşama yoksa API yazma işlemi yapılmaz.</p>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mmc-form-grid" id="mmc-kommo-settings-form">
                <input type="hidden" name="action" value="mmc_kommo_save_settings">
                <?php wp_nonce_field('mmc_kommo_save_settings','mmc_nonce'); ?>
                <label>Kommo Subdomain
                    <input name="subdomain" value="<?php echo esc_attr($sub); ?>" placeholder="milanosirki">
                </label>
                <label>MMC Program Pipeline
                    <select name="pipeline_id" id="mmc-kommo-pipeline">
                        <option value="0">Program pipeline seçilmedi</option>
                        <?php foreach($catalog as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php selected($pipeline,(int)$p['id']); ?>>
                                <?php echo esc_html(($p['name']?:'Pipeline').' (#'.(int)$p['id'].')'.(!empty($diag['legacy_pipeline_id'])&&(int)$diag['legacy_pipeline_id']===(int)$p['id']?' — legacy sipariş adayı':'')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Varsayılan Program Status
                    <select name="status_id" id="mmc-kommo-status">
                        <option value="0">Status seçilmedi</option>
                        <?php foreach($selected_statuses as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php selected($status,(int)$s['id']); ?>>
                                <?php echo esc_html(($s['name']?:'Status').' (#'.(int)$s['id'].')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>AI Kaynak Kullanımı
                    <select name="ai_mode">
                        <option value="suggested_reply" <?php selected($mode,'suggested_reply'); ?>>Suggested Reply</option>
                        <option value="agent" <?php selected($mode,'agent'); ?>>AI Agent</option>
                    </select>
                </label>
                <div><button class="button button-primary">Seçimi Kaydet</button></div>
            </form>

            <script>
            (function(){
                var catalog=<?php echo wp_json_encode($catalog_for_js); ?>;
                var p=document.getElementById('mmc-kommo-pipeline');
                var s=document.getElementById('mmc-kommo-status');
                if(!p||!s)return;
                p.addEventListener('change',function(){
                    var rows=catalog[String(p.value)]||[];
                    s.innerHTML='';
                    var empty=document.createElement('option');
                    empty.value='0'; empty.textContent='Status seçilmedi'; s.appendChild(empty);
                    rows.forEach(function(row){
                        var o=document.createElement('option');
                        o.value=String(row.id);
                        o.textContent=(row.name||'Status')+' (#'+row.id+')';
                        s.appendChild(o);
                    });
                });
            })();
            </script>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mmc_kommo_refresh_catalog">
                    <?php wp_nonce_field('mmc_kommo_refresh_catalog','mmc_nonce'); ?>
                    <button class="button">Pipeline Listesini Yenile</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mmc_kommo_test">
                    <?php wp_nonce_field('mmc_kommo_test','mmc_nonce'); ?>
                    <button class="button">Kommo Bağlantısını Yeniden Test Et</button>
                </form>
            </div>

            <?php if($pipeline): ?>
                <p style="margin-top:14px"><strong>Seçili Program Pipeline:</strong>
                    <?php echo !empty($pipe['pipeline_name'])?esc_html($pipe['pipeline_name'].' (#'.$pipeline.')'):esc_html('#'.$pipeline); ?>
                    <?php if($status): ?> · <strong>Status:</strong> #<?php echo (int)$status; ?><?php endif; ?>
                </p>
            <?php endif; ?>

            <h3>Güvenli wp-config Geçiş Şablonu</h3>
            <pre>define('MMC_KOMMO_SUBDOMAIN', '<?php echo esc_html($sub?:'milanosirki'); ?>');
define('MMC_KOMMO_TOKEN', 'YENI_UZUN_OMURLU_TOKEN');</pre>
            <p class="description">Tokenı sohbet, ekran görüntüsü veya WordPress option alanına yazmayın.</p>

            <?php if(!empty($diag['checked_at'])): ?>
                <p class="description">Son canlı API testi: <?php echo esc_html($diag['checked_at']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_sync_now(){ $this->guard('mmc_manage_kommo'); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_kommo_sync_now_'.$pid,'mmc_nonce'); MMC_Kommo_Service::sync_now($pid); wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','program_id'=>$pid,'mmc_msg'=>'kommo_sync'),admin_url('admin.php'))); exit; }
    public function handle_create_text_source(){
        $this->guard('mmc_manage_kommo');
        $pid=absint($_POST['program_id']??0);
        check_admin_referer('mmc_kommo_create_text_source_'.$pid,'mmc_nonce');

        $confirmed=!empty($_POST['confirm_create']);
        $text=trim((string)wp_unslash($_POST['confirm_text']??''));
        if(!$confirmed || 'MMC TEXT SOURCE KUR'!==$text){
            $this->redirect($pid,new WP_Error('mmc_text_source_confirmation','Direct Text Source oluşturulmadı. Onay kutusunu işaretleyin ve MMC TEXT SOURCE KUR yazın.'),'text_source_created');
        }

        $r=MMC_Kommo_Service::create_direct_text_source($pid);
        $this->redirect($pid,$r,'text_source_created');
    }

    public function handle_mark_refreshed(){ $this->guard('mmc_manage_kommo'); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_kommo_mark_refreshed_'.$pid,'mmc_nonce'); $r=MMC_Kommo_Service::mark_ai_refreshed($pid); $this->redirect($pid,$r,'ai_refreshed'); }
    public function handle_update_template(){ $this->guard('mmc_manage_kommo'); $id=absint($_POST['template_id']??0); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_kommo_update_template_'.$id,'mmc_nonce'); $r=MMC_Kommo_Service::update_template($id,sanitize_key($_POST['status']??''),wp_unslash($_POST['external_name']??''),wp_unslash($_POST['notes']??'')); $this->redirect($pid,$r,'template_saved'); }
    public function handle_save_settings(){
        $this->guard('mmc_manage_settings');
        check_admin_referer('mmc_kommo_save_settings','mmc_nonce');

        $subdomain=sanitize_title(wp_unslash($_POST['subdomain']??''));
        $pipeline_id=absint($_POST['pipeline_id']??0);
        $status_id=absint($_POST['status_id']??0);
        $mode=sanitize_key($_POST['ai_mode']??'suggested_reply');

        update_option('mmc_kommo_subdomain',$subdomain,false);
        update_option('mmc_kommo_ai_mode',in_array($mode,array('suggested_reply','agent'),true)?$mode:'suggested_reply',false);

        if($pipeline_id){
            $selection=MMC_Kommo_Service::discover_pipeline_selection($pipeline_id,$status_id,true);
            if(is_wp_error($selection)){
                wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','mmc_error'=>$selection->get_error_message()),admin_url('admin.php')));
                exit;
            }
            if(empty($selection['pipeline_valid'])){
                wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','mmc_error'=>'Seçilen Kommo pipeline artık hesapta bulunmuyor.'),admin_url('admin.php')));
                exit;
            }
            if($status_id && empty($selection['status_valid'])){
                wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','mmc_error'=>'Seçilen status bu pipeline içinde bulunmuyor.'),admin_url('admin.php')));
                exit;
            }
        } elseif($status_id){
            wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','mmc_error'=>'Status seçmek için önce Program Pipeline seçin.'),admin_url('admin.php')));
            exit;
        }

        update_option('mmc_kommo_pipeline_id',$pipeline_id,false);
        update_option('mmc_kommo_status_id',$status_id,false);

        wp_safe_redirect(add_query_arg(array('page'=>'mmc-kommo','mmc_msg'=>'settings_saved'),admin_url('admin.php')));
        exit;
    }
    public function handle_test(){ $this->guard('mmc_manage_settings'); check_admin_referer('mmc_kommo_test','mmc_nonce'); $r=MMC_Kommo_Service::connection_diagnostics(true); $args=array('page'=>'mmc-kommo'); if(empty($r['connected']))$args['mmc_error']=$r['error']?:'Kommo API bağlantısı doğrulanamadı.'; else $args['mmc_msg']='connection_ok'; wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit; }

    public function handle_refresh_catalog(){
        $this->guard('mmc_manage_settings');
        check_admin_referer('mmc_kommo_refresh_catalog','mmc_nonce');
        $r=MMC_Kommo_Service::pipeline_catalog(true);
        $args=array('page'=>'mmc-kommo');
        if(is_wp_error($r))$args['mmc_error']=$r->get_error_message();
        else $args['mmc_msg']='catalog_refreshed';
        wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));
        exit;
    }

    public function handle_install_program_pipeline(){
        $this->guard('mmc_manage_settings');
        check_admin_referer('mmc_kommo_install_program_pipeline','mmc_nonce');

        $program_id=absint($_POST['program_id']??0);
        $confirmed=!empty($_POST['confirm_install']);
        $text=trim((string)wp_unslash($_POST['confirm_text']??''));

        $args=array('page'=>'mmc-kommo');
        if($program_id)$args['program_id']=$program_id;

        if(!$confirmed || 'MMC PROGRAM PIPELINE KUR'!==$text){
            $message='Kurulum yapılmadı. Onay kutusunu işaretleyin ve onay metnini tam olarak MMC PROGRAM PIPELINE KUR yazın.';
            MMC_Kommo_Service::record_pipeline_install_result('error',$message,array('program_id'=>$program_id));
            $args['mmc_error']=$message;
            wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));
            exit;
        }

        $readiness=MMC_Kommo_Service::admin_write_readiness(true);
        if(true===$readiness['verified'] && false===$readiness['is_admin']){
            $message='Kommo pipeline kurulumu durduruldu: tokenın bağlı olduğu Kommo kullanıcısı yönetici değil.';
            MMC_Kommo_Service::record_pipeline_install_result('error',$message,array(
                'program_id'=>$program_id,
                'kommo_user_id'=>$readiness['user_id']??0
            ));
            $args['mmc_error']=$message;
            wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));
            exit;
        }

        $r=MMC_Kommo_Service::install_program_pipeline();

        if(is_wp_error($r)){
            $message=$r->get_error_message();
            $data=$r->get_error_data();
            MMC_Kommo_Service::record_pipeline_install_result('error',$message,array(
                'program_id'=>$program_id,
                'error_code'=>$r->get_error_code(),
                'http_status'=>is_array($data)?($data['status']??''):'',
                'endpoint'=>is_array($data)?($data['endpoint']??''):''
            ));
            $args['mmc_error']=$message;
        }else{
            $message='MMC Program Yönetimi pipeline ve aşamaları doğrulandı; Hazırlık varsayılan status olarak MMC’ye bağlandı.';
            MMC_Kommo_Service::record_pipeline_install_result('success',$message,array(
                'program_id'=>$program_id,
                'pipeline_id'=>$r['pipeline_id'],
                'status_id'=>$r['default_status_id'],
                'added_stage_count'=>$r['added_stage_count']
            ));
            $args['mmc_msg']='program_pipeline_installed';
            $args['mmc_pipeline_id']=(int)$r['pipeline_id'];
            $args['mmc_status_id']=(int)$r['default_status_id'];
        }

        wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));
        exit;
    }

    private function redirect($pid,$r,$ok){$args=array('page'=>'mmc-kommo','program_id'=>$pid); if(is_wp_error($r))$args['mmc_error']=$r->get_error_message(); else $args['mmc_msg']=$ok; wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;}
    private function guard($cap){if(!(current_user_can($cap)||current_user_can('mmc_manage_programs')))wp_die('Bu işlemi yapma yetkiniz yok.');}
    private function notice(){ if(!empty($_GET['mmc_error']))echo '<div class="notice notice-error"><p>'.esc_html(wp_unslash($_GET['mmc_error'])).'</p></div>'; if(!empty($_GET['mmc_msg'])){$m=array('kommo_sync'=>'Kommo senkron kuyruğu çalıştırıldı.','ai_refreshed'=>'AI kaynak yeniden tarandı olarak işaretlendi.','template_saved'=>'Şablon durumu güncellendi.','settings_saved'=>'Kommo ayarları kaydedildi.','connection_ok'=>'Kommo API bağlantısı başarılı.','catalog_refreshed'=>'Kommo pipeline/status listesi yenilendi.','program_pipeline_installed'=>'MMC Program Yönetimi pipeline ve aşamaları doğrulandı; Hazırlık varsayılan status olarak MMC’ye bağlandı.','text_source_created'=>'Kommo AI doğrudan metin kaynağı oluşturuldu ve MMC ana AI kaynağı olarak bağlandı.');$k=sanitize_key($_GET['mmc_msg']);echo '<div class="notice notice-success"><p>'.esc_html($m[$k]??'İşlem tamamlandı.').'</p></div>';}}
}
