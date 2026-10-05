<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Operations_Admin {
    public function __construct() {
        add_action('admin_post_mmc_ops_apply_task_automation',array($this,'apply_task_automation'));
        add_action( 'admin_menu', array($this,'menu') );
        add_action( 'admin_post_mmc_ops_ensure_plan', array($this,'ensure_plan') );
        add_action( 'admin_post_mmc_ops_save_plan', array($this,'save_plan') );
        add_action( 'admin_post_mmc_ops_add_resource', array($this,'add_resource') );
        add_action( 'admin_post_mmc_ops_assign_resource', array($this,'assign_resource') );
        add_action( 'admin_post_mmc_ops_update_assignment', array($this,'update_assignment') );
        add_action( 'admin_post_mmc_ops_save_checklist', array($this,'save_checklist') );
        add_action( 'admin_post_mmc_ops_add_schedule', array($this,'add_schedule') );
        add_action( 'admin_post_mmc_ops_schedule_status', array($this,'schedule_status') );
        add_action( 'admin_post_mmc_ops_sync_schedule', array($this,'sync_schedule') );
    }

    public function menu() {
        add_submenu_page( 'mmc-dashboard', 'Operasyon & Lojistik', 'Operasyon & Lojistik', 'mmc_manage_operations', 'mmc-operations', array($this,'page') );
    }

    private function guard(){if(!current_user_can('mmc_manage_operations')){wp_die('Bu alan için yetkiniz yok.');}}

    private function readiness_dashboard( $rows, $archive ) {
        ?>
        <div class="mmc-panel">
            <h2><?php echo $archive?'Operasyon Arşivi':'Yaklaşan Operasyonlar';?></h2>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=mmc-operations&archive='.($archive?0:1)));?>"><?php echo $archive?'Yaklaşan programlar':'Arşivi göster';?></a> · Hazırlık yüzdesi: zorunlu hareket öncesi / salon checklist kontrolleri. Tarih/sorumlu önerileri yalnız gerçek saatler ve program sahibinden türetilir; mevcut görevler açık POST olmadan değiştirilmez.</p>
            <table class="widefat striped"><thead><tr><th>Program / tarih</th><th>Şehir / salon</th><th>İlk / sıradaki seans</th><th>Plan / tip</th><th>Hazırlık</th><th>Ekip / araç</th><th>Görev</th><th>Kritik eksik / sonraki adım</th></tr></thead><tbody>
            <?php foreach($rows as $row):?>
                <tr><td><a href="<?php echo esc_url(admin_url('admin.php?page=mmc-operations&program_id='.(int)$row['id'].'&archive='.($archive?1:0)));?>"><?php echo esc_html($row['program_code']);?></a><br><?php echo esc_html($row['program_date']);?><?php if($row['show_day']):?><br><strong>GÖSTERİ GÜNÜ</strong><?php if($row['seconds_to_next_session']!==null):?><br><?php echo esc_html((int)ceil($row['seconds_to_next_session']/60));?> dakika kaldı<?php endif;?><?php endif;?></td>
                <td><?php echo esc_html($row['province_name'].' / '.$row['district_name']);?><br><?php echo esc_html($row['venue_name']?:'Salon yok');?></td>
                <td><?php echo esc_html($row['first_session']?:'Seans yok');?><br><?php echo esc_html($row['next_session']?:'Gelecek seans yok');?><br>Kapı: <?php echo esc_html($row['doors_open_at']?:'Belirlenmedi');?></td>
                <td><?php echo esc_html(self::label(MMC_Operations_Service::plan_statuses(),$row['plan_status'],'Plan yok'));?><br><?php echo esc_html(self::label(MMC_Operations_Service::operation_modes(),$row['operation_mode'],'Belirlenmedi'));?></td>
                <td>%<?php echo esc_html($row['readiness_percent']);?><br><?php echo esc_html($row['problems']);?> sorun</td>
                <td><?php echo esc_html($row['artists'].' sanatçı / '.$row['people'].' personel / '.$row['vehicles'].' araç / '.$row['equipment'].' ekipman');?></td>
                <td><?php echo esc_html($row['open_tasks']);?> açık<br><?php echo esc_html($row['overdue_tasks']);?> gecikmiş</td>
                <td><?php echo esc_html(implode(' · ',$row['critical_missing']));?><br><strong><?php echo esc_html($row['next_action']);?></strong></td></tr>
            <?php endforeach;?>
            <?php if(!$rows):?><tr><td colspan="8">Bu kapsamda program yok.</td></tr><?php endif;?>
            </tbody></table>
        </div>
        <?php
    }

    private static function label( $labels, $key, $fallback ) {
        return isset($labels[$key??''])?$labels[$key]:$fallback;
    }

    private function task_alert_dashboard( $alerts, $filter, $program_id, $archive ) {
        $labels=array('all'=>'Tümü','overdue'=>'Geciken','today'=>'Bugün','48h'=>'48 saat','mine'=>'Bana atanan','high'=>'Yüksek öncelik','no_deadline'=>'Tarihsiz');
        ?>
        <div class="mmc-panel"><h2>MMC İçi Görev Uyarıları</h2>
            <p>Yalnız aktif/gelecek programların açık Operations görevleri. Bugün ve 48 saat sayaçları örtüşebilir; geçmiş/iptal ve finance görevleri kapsam dışıdır. Harici mesaj veya bildirim logu üretilmez.</p>
            <div class="mmc-cards mmc-cards-5">
                <?php foreach(array('overdue'=>'Geciken','due_today'=>'Bugün yapılacak','upcoming_48h'=>'Önümüzdeki 48 saat','no_deadline'=>'Tarihsiz','mine'=>'Bana atanan açık görev') as $key=>$label):?>
                <div class="mmc-card"><span><?php echo esc_html($label);?></span><strong><?php echo esc_html($alerts['counts'][$key]);?></strong></div><?php endforeach;?>
            </div>
            <p><?php echo esc_html($alerts['counts']['high']);?> yüksek/ kritik öncelikli açık görev.</p>
            <form method="get"><input type="hidden" name="page" value="mmc-operations"><input type="hidden" name="program_id" value="<?php echo esc_attr($program_id);?>"><input type="hidden" name="archive" value="<?php echo $archive?1:0;?>">
                <label>Görev filtresi <select name="task_filter" onchange="this.form.submit()">
                <?php foreach($labels as$key=>$label):?><option value="<?php echo esc_attr($key);?>" <?php selected($filter,$key);?>><?php echo esc_html($label);?></option><?php endforeach;?></select></label>
            </form>
            <table class="widefat striped"><thead><tr><th>Program / şehir / tarih</th><th>Görev / phase</th><th>Öncelik</th><th>Son tarih</th><th>Sorumlu</th><th>Hesaplanan durum</th></tr></thead><tbody>
            <?php foreach($alerts['items'] as$task):?><tr>
                <td><?php echo esc_html(($task['program_code']??'').' / '.($task['province_name']??'').' / '.($task['district_name']??''));?><br><?php echo esc_html($task['program_date']??'');?></td>
                <td><?php echo esc_html($task['title']);?><br><?php echo esc_html($task['phase']);?></td><td><?php echo esc_html($task['priority']);?></td>
                <td><?php echo esc_html($task['due_at']??'Belirlenmedi');?></td><td><?php echo esc_html($task['assigned_user_id']??'Atanmadı');?></td><td><?php echo esc_html($task['computed_state']);?></td>
            </tr><?php endforeach;?><?php if(!$alerts['items']):?><tr><td colspan="6">Bu filtrede açık Operations görevi yok.</td></tr><?php endif;?></tbody></table>
        </div>
        <?php
    }

    private function task_automation_preview_panel( $preview ) {
        ?>
        <div class="mmc-panel"><h2>Operations Faz 2 Preview</h2>
            <p>GET yalnız okur. Manuel alanlar, işaretlenmemiş görevler, tamamlanmış/iptal görevler ve geçmiş/kapalı programlar korunur. Öneri: <?php echo esc_html($preview['would_update_due']);?> tarih / <?php echo esc_html($preview['would_update_owner']);?> sorumlu.</p>
            <table class="widefat striped"><thead><tr><th>Görev / system-generated</th><th>Mevcut → önerilen tarih</th><th>Kaynak</th><th>Mevcut → önerilen sorumlu</th><th>Değişiklik / neden</th></tr></thead><tbody>
            <?php foreach($preview['items'] as$item):?><tr><td><?php echo esc_html($item['task']);?><br><?php echo $item['system_generated']?'Evet':'Hayır / işaretlenmemiş';?></td>
                <td><?php echo esc_html(($item['current_due']?:'NULL').' → '.($item['proposed_due']?:'NULL'));?></td><td><?php echo esc_html($item['due_source']??'Doğrulanmış anchor yok');?></td>
                <td><?php echo esc_html(($item['current_owner']?:'NULL').' → '.($item['proposed_owner']?:'NULL'));?></td>
                <td><?php echo esc_html(($item['would_leave_unchanged']?'Korunur':'Tarih: '.(int)$item['would_update_due'].' / sorumlu: '.(int)$item['would_update_owner']).' · '.implode(', ',$item['reason']));?></td></tr>
            <?php endforeach;?><?php if(!$preview['items']):?><tr><td colspan="5">Programda görev yok.</td></tr><?php endif;?></tbody></table>
            <?php if($preview['eligible'] && ($preview['would_update_due'] || $preview['would_update_owner'])):?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_apply_task_automation"><input type="hidden" name="program_id" value="<?php echo esc_attr($preview['program_id']);?>">
                <?php wp_nonce_field('mmc_ops_automation_apply_'.$preview['program_id']);submit_button('Otomasyon Eksiklerini Uygula','secondary');?>
            </form><p>Yalnız bu programdaki boş veya doğrulanmış otomasyon yönetimli alanlar uygulanır. Manuel değerler korunur.</p>
            <?php else:?><p>Bu programda uygulanabilir değişiklik yok; canlı backfill çalıştırılmaz.</p><?php endif;?>
        </div>
        <?php
    }

    public function apply_task_automation() {
        if('POST'!==($_SERVER['REQUEST_METHOD']??'')){wp_die('Bu işlem POST gerektirir.','',array('response'=>405));}
        $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_automation_apply_'.$pid);
        $result=MMC_Operations_Service::apply_task_automation($pid);
        $this->redirect($pid,$result,'Program bazlı otomasyon eksikleri uygulandı.');
    }

    public function page() {
        $this->guard();
        $archive=!empty($_GET['archive']);
        $overview=MMC_Operations_Service::readiness_overview($archive);
        $programs=MMC_Program_Service::all_programs();
        $visible_ids=array_column($overview,'id');
        if(!$archive){$programs=array_values(array_filter($programs,static function($p)use($visible_ids){return in_array($p->id,$visible_ids); }));}
        $pid=absint($_GET['program_id']??0); if(!$pid&&$overview){$pid=(int)$overview[0]['id'];}
        $filter=is_string($_GET['task_filter']??'all')?sanitize_key($_GET['task_filter']??'all'):'all';
        if(!in_array($filter,array('all','overdue','today','48h','mine','high','no_deadline'),true)){$filter='all';}
        $alerts=MMC_Operations_Service::task_alerts(0,$filter);
        $program=$pid?MMC_Program_Service::get_program($pid):null;
        $automation_preview=$program?MMC_Operations_Service::task_automation_preview($pid):null;
        $plan=$program?MMC_Operations_Service::get_plan($pid):null;
        $summary=$program?MMC_Operations_Service::summary($pid):array();
        $resources=MMC_Operations_Service::resources();
        $assigned=$program?MMC_Operations_Service::program_resources($pid):array();
        $checklist=$program?MMC_Operations_Service::checklist($pid):array();
        $schedule=$program?MMC_Operations_Service::schedule($pid):array();
        $tasks=$program?MMC_Operations_Service::operation_tasks($pid):array();
        $event=$program&&class_exists('MMC_Event_Service')?MMC_Event_Service::event_for_program($pid):null;
        $session_sales=$event&&class_exists('MMC_Sales_Service')?MMC_Sales_Service::session_summary($event->id):array();
        ?>
        <div class="wrap mmc-wrap">
            <h1>Operasyon & Lojistik Modülü</h1>
            <p class="mmc-lead">Programın hareket öncesinden salon teslimine kadar araç, ekip, sanatçı, ekipman, konaklama, yemek, teknik kurulum, gişe/check-in ve gösteri sonrası kapanışını tek dosyada yönetir.</p>
            <?php $this->notice();$this->task_alert_dashboard($alerts,$filter,$pid,$archive);$this->readiness_dashboard($overview,$archive); ?>
            <div class="mmc-panel"><form method="get" class="mmc-inline-form"><input type="hidden" name="page" value="mmc-operations"><input type="hidden" name="archive" value="<?php echo $archive?1:0;?>"><label>Program<select name="program_id" onchange="this.form.submit()"><option value="">Seçin</option><?php foreach($programs as $p):?><option value="<?php echo esc_attr($p->id);?>" <?php selected($pid,$p->id);?>><?php echo esc_html($p->program_code.' — '.$p->province_name.' / '.$p->district_name);?></option><?php endforeach;?></select></label></form></div>
            <?php if(!$program):?><div class="notice notice-info"><p>Program seçin.</p></div></div><?php return;endif;?>
            <?php if($automation_preview){$this->task_automation_preview_panel($automation_preview);}?>
            <?php if(!$plan):?>
                <div class="notice notice-info"><p>Bu program için operasyon planı henüz oluşturulmamış. Bu ekran mevcut veriyi yalnızca okur; plan ve varsayılan kontrol listesi ancak aşağıdaki açık işlemle oluşturulur.</p></div>
                <div class="mmc-panel">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
                        <input type="hidden" name="action" value="mmc_ops_ensure_plan">
                        <input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>">
                        <?php wp_nonce_field('mmc_ops_ensure_'.$pid);?>
                        <?php submit_button('Plan Oluştur / Hazırla','primary');?>
                    </form>
                </div>
            </div>
            <?php return;endif;?>

            <?php if(!in_array($program->status,array('cancelled','completed','financial_close','deposit_refund'),true)):?>
            <div class="mmc-panel"><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>">
                <input type="hidden" name="action" value="mmc_ops_ensure_plan"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>">
                <?php wp_nonce_field('mmc_ops_ensure_'.$pid);submit_button('Eksik Operasyon Hazırlığını Tamamla','secondary');?>
                <p>Mevcut notlar, görev sorumluları ve tarihler korunur. Geniş görev seti yalnız operasyon / gösteri günü aşamasında hazırlanır.</p>
            </form></div>
            <?php endif;?>
            <div class="mmc-cards mmc-cards-5">
                <div class="mmc-card"><span>Operasyon Tipi</span><strong style="font-size:20px"><?php echo esc_html(MMC_Operations_Service::operation_modes()[$plan->operation_mode]??$plan->operation_mode);?></strong><small><?php echo esc_html(MMC_Operations_Service::plan_statuses()[$plan->status]??$plan->status);?></small></div>
                <div class="mmc-card"><span>Hareket Öncesi Hazırlık</span><strong>%<?php echo esc_html($summary['pre_percent']);?></strong><small><?php echo esc_html($summary['pre_done'].' / '.$summary['pre_total']);?> zorunlu kontrol</small></div>
                <div class="mmc-card"><span>Sorun</span><strong><?php echo esc_html($summary['problems']);?></strong><small>Kontrol listesi</small></div>
                <div class="mmc-card"><span>Ekip</span><strong><?php echo esc_html($summary['artists']+$summary['people']);?></strong><small><?php echo esc_html($summary['artists']);?> sanatçı · <?php echo esc_html($summary['people']);?> personel</small></div>
                <div class="mmc-card"><span>Lojistik</span><strong><?php echo esc_html($summary['vehicles']);?> araç</strong><small><?php echo esc_html($summary['equipment']);?> ekipman kaydı</small></div>
            </div>

            <div class="mmc-panel"><h2>Operasyon / Mali Kapanış Görevleri</h2>
                <p>Görevler mevcut MMC görev sisteminden okunur. Sorumlu ve tarih ataması yapılmamışsa açıkça belirtilir.</p>
                <table class="widefat striped"><thead><tr><th>Görev</th><th>Durum / öncelik</th><th>Son tarih</th><th>Sorumlu kullanıcı</th><th>Tamamlanma</th></tr></thead><tbody>
                <?php foreach($tasks as $task):?><tr><td><?php echo esc_html($task->title);?></td><td><?php echo esc_html($task->status.' / '.$task->priority);?></td><td><?php echo esc_html($task->due_at??'Belirlenmedi');?></td><td><?php echo esc_html($task->assigned_user_id??'Atanmadı');?></td><td><?php echo esc_html($task->completed_at??'—');?></td></tr><?php endforeach;?>
                <?php if(!$tasks):?><tr><td colspan="5">Bu kapsamda görev yok.</td></tr><?php endif;?></tbody></table>
            </div>
            <div class="mmc-panel">
                <h2>1. Operasyon Planı</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_save_plan"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_plan_'.$pid);?>
                    <div class="mmc-form-grid">
                        <label>Operasyon tipi<select name="operation_mode"><?php foreach(MMC_Operations_Service::operation_modes() as $k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected($plan->operation_mode,$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></label>
                        <label>Durum<select name="status"><?php foreach(MMC_Operations_Service::plan_statuses() as $k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected($plan->status,$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></label>
                        <label>Çıkış şehri<input name="origin_city" value="<?php echo esc_attr($plan->origin_city);?>"></label>
                        <label>Sonraki hedef / dönüş<input name="next_destination" value="<?php echo esc_attr($plan->next_destination);?>" placeholder="Ankara / sonraki şehir"></label>
                        <?php foreach(array('departure_at'=>'Hareket','venue_entry_at'=>'Salon Giriş','setup_start_at'=>'Kurulum Başlangıç','rehearsal_at'=>'Prova','doors_open_at'=>'Kapı Açılış','teardown_end_at'=>'Söküm Bitiş','return_at'=>'Dönüş / Hareket') as $f=>$lab):?><label><?php echo esc_html($lab);?><input type="datetime-local" name="<?php echo esc_attr($f);?>" value="<?php echo esc_attr($this->dt_local($plan->$f));?>"></label><?php endforeach;?>
                        <label><input type="checkbox" name="accommodation_required" value="1" <?php checked($plan->accommodation_required,1);?>> Konaklama gerekli</label>
                        <label>Otel / konaklama<input name="lodging_name" value="<?php echo esc_attr($plan->lodging_name);?>"></label>
                        <label class="mmc-span-2">Konaklama adresi<textarea name="lodging_address"><?php echo esc_textarea((string)($plan->lodging_address??''));?></textarea></label>
                        <label>Oda sayısı<input type="number" min="0" name="lodging_rooms" value="<?php echo esc_attr($plan->lodging_rooms);?>"></label>
                        <label>Konaklama bütçesi<input type="number" step="0.01" min="0" name="lodging_cost" value="<?php echo esc_attr($plan->lodging_cost);?>"></label>
                        <label class="mmc-span-2">Yemek planı<textarea name="meal_plan" placeholder="Öğle / akşam, kişi sayısı, tedarikçi..."><?php echo esc_textarea((string)($plan->meal_plan??''));?></textarea></label>
                        <label>Yemek bütçesi<input type="number" step="0.01" min="0" name="meal_cost" value="<?php echo esc_attr($plan->meal_cost);?>"></label>
                        <label>Ulaşım / yakıt bütçesi<input type="number" step="0.01" min="0" name="transport_cost" value="<?php echo esc_attr($plan->transport_cost);?>"></label>
                        <label>Diğer operasyon gideri<input type="number" step="0.01" min="0" name="other_cost" value="<?php echo esc_attr($plan->other_cost);?>"></label>
                        <label class="mmc-span-2">Notlar<textarea name="notes"><?php echo esc_textarea((string)($plan->notes??''));?></textarea></label>
                    </div>
                    <?php submit_button('Operasyon Planını Kaydet');?>
                </form>
            </div>

            <div class="mmc-grid-2">
                <div class="mmc-panel"><h2>2. Kaynak Ana Kaydı</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_add_resource"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_resource_'.$pid);?><div class="mmc-form-grid"><label>Tür<select name="resource_type"><?php foreach(MMC_Operations_Service::resource_types() as $k=>$v):?><option value="<?php echo esc_attr($k);?>"><?php echo esc_html($v);?></option><?php endforeach;?></select></label><label>Ad / Tanım<input name="resource_name" required placeholder="Fiat Doblo 06... / Sanatçı adı / Ses sistemi"></label><label>Alt tür / Branş<input name="subtype" placeholder="Sürücü, akrobat, POS, kostüm..."></label><label>Plaka / Kod<input name="identifier"></label><label>Ülke<input name="country"></label><label class="mmc-span-2">Not<textarea name="notes"></textarea></label></div><?php submit_button('Kaynağı Ekle','secondary');?></form></div>
                <div class="mmc-panel"><h2>3. Programa Kaynak Ata</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_assign_resource"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_assign_'.$pid);?><label class="mmc-block-label">Kaynak<select name="resource_id" required><option value="">Seçin</option><?php foreach($resources as $r):?><option value="<?php echo esc_attr($r->id);?>"><?php echo esc_html((MMC_Operations_Service::resource_types()[$r->resource_type]??$r->resource_type).' — '.$r->resource_name.($r->identifier?' · '.$r->identifier:''));?></option><?php endforeach;?></select></label><div class="mmc-form-grid"><label>Görevi<input name="role_name" placeholder="Araç 1 / Ekip lideri / Hula Hoop"></label><label>Adet<input type="number" min="1" name="quantity" value="1"></label><label>Durum<select name="status"><?php foreach(MMC_Operations_Service::assignment_statuses() as $k=>$v):?><option value="<?php echo esc_attr($k);?>"><?php echo esc_html($v);?></option><?php endforeach;?></select></label><label>Not<input name="notes"></label></div><?php submit_button('Programa Ata','primary');?></form></div>
            </div>

            <div class="mmc-panel"><h2>Program Kaynakları</h2><div class="mmc-table-scroll"><table class="widefat striped"><thead><tr><th>Tür</th><th>Kaynak</th><th>Görev</th><th>Adet</th><th>Durum</th><th>Giriş / Çıkış</th><th>Güncelle</th></tr></thead><tbody><?php if(!$assigned):?><tr><td colspan="7">Henüz kaynak atanmadı.</td></tr><?php else:foreach($assigned as $a):?><tr><td><?php echo esc_html(MMC_Operations_Service::resource_types()[$a->resource_type]??$a->resource_type);?></td><td><strong><?php echo esc_html($a->resource_name);?></strong></td><td><?php echo esc_html($a->role_name?:'—');?></td><td><?php echo esc_html($a->quantity);?></td><td><?php echo esc_html(MMC_Operations_Service::assignment_statuses()[$a->status]??$a->status);?></td><td><?php echo esc_html($a->check_in_at?:'—');?><br><?php echo esc_html($a->check_out_at?:'—');?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_update_assignment"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><input type="hidden" name="assignment_id" value="<?php echo esc_attr($a->id);?>"><input type="hidden" name="role_name" value="<?php echo esc_attr($a->role_name);?>"><input type="hidden" name="quantity" value="<?php echo esc_attr($a->quantity);?>"><input type="hidden" name="notes" value="<?php echo esc_attr($a->notes);?>"><?php wp_nonce_field('mmc_ops_assignment_'.$pid.'_'.$a->id);?><select name="status"><?php foreach(MMC_Operations_Service::assignment_statuses() as $k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected($a->status,$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select><input type="datetime-local" name="check_in_at" value="<?php echo esc_attr($this->dt_local($a->check_in_at));?>"><input type="datetime-local" name="check_out_at" value="<?php echo esc_attr($this->dt_local($a->check_out_at));?>"><button class="button">Kaydet</button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div>

            <div class="mmc-panel"><h2>4. Operasyon Kontrol Listesi</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_save_checklist"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_checklist_'.$pid);?><?php $by=array();foreach($checklist as $c){$by[$c->phase][]=$c;}foreach(MMC_Operations_Service::phases() as $phase=>$label):?><h3><?php echo esc_html($label);?></h3><div class="mmc-table-scroll"><table class="widefat striped"><thead><tr><th>Kontrol</th><th>Zorunlu</th><th>Durum</th><th>Sorumlu</th><th>Not</th></tr></thead><tbody><?php foreach(($by[$phase]??array()) as $c):?><tr><td><?php echo esc_html($c->title);?></td><td><?php echo $c->is_required?'Evet':'Hayır';?></td><td><select name="rows[<?php echo esc_attr($c->id);?>][status]"><?php foreach(MMC_Operations_Service::checklist_statuses() as $k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected($c->status,$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></td><td><input name="rows[<?php echo esc_attr($c->id);?>][assigned_name]" value="<?php echo esc_attr($c->assigned_name);?>"></td><td><input name="rows[<?php echo esc_attr($c->id);?>][notes]" value="<?php echo esc_attr($c->notes);?>"></td></tr><?php endforeach;?></tbody></table></div><?php endforeach;?><?php submit_button('Kontrol Listesini Kaydet');?></form></div>

            <div class="mmc-grid-2">
                <div class="mmc-panel"><h2>5. Gün / Akış Planı</h2><p>Seanslar ve plan saatleri sistemden otomatik gelir. Manuel kalem de ekleyebilirsiniz.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_sync_schedule"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_sync_'.$pid);?><button class="button">Etkinlik / Seanslardan Akışı Yenile</button></form><div class="mmc-table-scroll" style="margin-top:12px"><table class="widefat striped"><thead><tr><th>Saat</th><th>İş</th><th>Durum</th></tr></thead><tbody><?php if(!$schedule):?><tr><td colspan="3">Akış kalemi yok.</td></tr><?php else:foreach($schedule as $s):?><tr><td><?php echo esc_html($s->start_at?$this->display_datetime($s->start_at):'—');?></td><td><strong><?php echo esc_html($s->title);?></strong><?php if($s->assigned_name):?><br><small><?php echo esc_html($s->assigned_name);?></small><?php endif;?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_schedule_status"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><input type="hidden" name="schedule_id" value="<?php echo esc_attr($s->id);?>"><?php wp_nonce_field('mmc_ops_sched_'.$pid.'_'.$s->id);?><select name="status"><option value="planned" <?php selected($s->status,'planned');?>>Planlandı</option><option value="ready" <?php selected($s->status,'ready');?>>Hazır</option><option value="done" <?php selected($s->status,'done');?>>Tamam</option><option value="cancelled" <?php selected($s->status,'cancelled');?>>İptal</option></select><button class="button">Kaydet</button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div>
                <div class="mmc-panel"><h2>Manuel Akış Kalemi Ekle</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_add_schedule"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_add_sched_'.$pid);?><div class="mmc-form-grid"><label>Başlık<input name="title" required placeholder="Öğle yemeği / sanatçı transferi"></label><label>Tür<select name="item_type"><option value="transfer">Transfer</option><option value="meal">Yemek</option><option value="rehearsal">Prova</option><option value="setup">Kurulum</option><option value="other">Diğer</option></select></label><label>Başlangıç<input type="datetime-local" name="start_at"></label><label>Bitiş<input type="datetime-local" name="end_at"></label><label>Sorumlu<input name="assigned_name"></label><label>Not<input name="notes"></label></div><?php submit_button('Akışa Ekle','secondary');?></form></div>
            </div>

            <div class="mmc-panel"><h2>6. Seans Satış / Doluluk Kontrolü</h2><?php if(!$event):?><p>Etkinlik kaydı henüz oluşmadı.</p><?php elseif(!$session_sales):?><p>Seans yok.</p><?php else:?><table class="widefat striped"><thead><tr><th>Seans</th><th>Kapasite</th><th>Satılan Kişi</th><th>Kalan</th><th>Doluluk</th><th>Ciro</th></tr></thead><tbody><?php foreach($session_sales as $row):?><tr><td><?php echo esc_html($this->display_datetime($row['session']->session_time));?></td><td><?php echo esc_html(number_format_i18n($row['session']->capacity));?></td><td><?php echo esc_html(number_format_i18n($row['sold_capacity']));?></td><td><?php echo esc_html(number_format_i18n($row['remaining']));?></td><td>%<?php echo esc_html($row['occupancy']);?></td><td><?php echo esc_html(number_format_i18n($row['revenue'],2));?> TL</td></tr><?php endforeach;?></tbody></table><?php endif;?></div>
        </div>
        <?php
    }

    public function ensure_plan(){ if('POST'!==($_SERVER['REQUEST_METHOD']??'')){wp_die('Bu işlem POST gerektirir.', '', array('response'=>405));}$this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_ensure_'.$pid);$r=MMC_Operations_Service::ensure_plan($pid);$this->redirect($pid,$r,'Operasyon planı oluşturuldu / hazırlandı.'); }
    public function save_plan(){ $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_plan_'.$pid);$r=MMC_Operations_Service::save_plan($pid,wp_unslash($_POST));$this->redirect($pid,$r,'Operasyon planı kaydedildi.'); }
    public function add_resource(){ $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_resource_'.$pid);$r=MMC_Operations_Service::add_resource(wp_unslash($_POST));$this->redirect($pid,$r,'Kaynak ana kaydı eklendi.'); }
    public function assign_resource(){ $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_assign_'.$pid);$r=MMC_Operations_Service::assign_resource($pid,absint($_POST['resource_id']??0),wp_unslash($_POST));$this->redirect($pid,$r,'Kaynak programa atandı.'); }
    public function update_assignment(){ $this->guard();$pid=absint($_POST['program_id']??0);$aid=absint($_POST['assignment_id']??0);check_admin_referer('mmc_ops_assignment_'.$pid.'_'.$aid);$r=MMC_Operations_Service::update_assignment($pid,$aid,wp_unslash($_POST));$this->redirect($pid,$r,'Kaynak ataması güncellendi.'); }
    public function save_checklist(){ $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_checklist_'.$pid);$r=MMC_Operations_Service::save_checklist_rows($pid,wp_unslash($_POST['rows']??array()));$this->redirect($pid,$r,'Kontrol listesi kaydedildi.'); }
    public function add_schedule(){ $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_add_sched_'.$pid);$r=MMC_Operations_Service::add_schedule_item($pid,wp_unslash($_POST));$this->redirect($pid,$r,'Akış kalemi eklendi.'); }
    public function schedule_status(){ $this->guard();$pid=absint($_POST['program_id']??0);$sid=absint($_POST['schedule_id']??0);check_admin_referer('mmc_ops_sched_'.$pid.'_'.$sid);$r=MMC_Operations_Service::update_schedule_status($pid,$sid,sanitize_key($_POST['status']??''));$this->redirect($pid,$r,'Akış durumu güncellendi.'); }
    public function sync_schedule(){ $this->guard();$pid=absint($_POST['program_id']??0);check_admin_referer('mmc_ops_sync_'.$pid);$r=MMC_Operations_Service::sync_schedule_from_event($pid);$this->redirect($pid,$r,'Etkinlik ve seans akışı yenilendi.'); }

    private function redirect($pid,$result,$ok){$type=is_wp_error($result)?'error':'success';$msg=is_wp_error($result)?$result->get_error_message():$ok;wp_safe_redirect(add_query_arg(array('page'=>'mmc-operations','program_id'=>$pid,'mmc_notice'=>rawurlencode($msg),'mmc_notice_type'=>$type),admin_url('admin.php')));exit;}
    private function notice(){if(empty($_GET['mmc_notice']))return;$type=sanitize_key($_GET['mmc_notice_type']??'success');$class='error'===$type?'notice-error':'notice-success';echo '<div class="notice '.esc_attr($class).' is-dismissible"><p>'.esc_html(rawurldecode(wp_unslash($_GET['mmc_notice']))).'</p></div>';}
    private function display_datetime($value){return substr($value,8,2).'.'.substr($value,5,2).'.'.substr($value,0,4).' '.substr($value,11,5);}
    private function dt_local($value){if(!$value)return '';return str_replace(' ','T',substr((string)$value,0,16));}
}
