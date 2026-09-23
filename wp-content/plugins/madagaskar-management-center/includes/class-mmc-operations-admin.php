<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Operations_Admin {
    public function __construct() {
        add_action( 'admin_menu', array($this,'menu') );
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

    public function page() {
        $this->guard();
        $programs=MMC_Program_Service::all_programs(); $pid=absint($_GET['program_id']??0); if(!$pid&&$programs){$pid=(int)$programs[0]->id;}
        $program=$pid?MMC_Program_Service::get_program($pid):null;
        $plan=$program?MMC_Operations_Service::ensure_plan($pid):null;
        if(is_wp_error($plan)){$plan=null;}
        $summary=$program?MMC_Operations_Service::summary($pid):array();
        $resources=MMC_Operations_Service::resources();
        $assigned=$program?MMC_Operations_Service::program_resources($pid):array();
        $checklist=$program?MMC_Operations_Service::checklist($pid):array();
        $schedule=$program?MMC_Operations_Service::schedule($pid):array();
        $event=$program&&class_exists('MMC_Event_Service')?MMC_Event_Service::event_for_program($pid):null;
        $session_sales=$event&&class_exists('MMC_Sales_Service')?MMC_Sales_Service::session_summary($event->id):array();
        ?>
        <div class="wrap mmc-wrap">
            <h1>Operasyon & Lojistik Modülü</h1>
            <p class="mmc-lead">Programın hareket öncesinden salon teslimine kadar araç, ekip, sanatçı, ekipman, konaklama, yemek, teknik kurulum, gişe/check-in ve gösteri sonrası kapanışını tek dosyada yönetir.</p>
            <?php $this->notice(); ?>
            <div class="mmc-panel"><form method="get" class="mmc-inline-form"><input type="hidden" name="page" value="mmc-operations"><label>Program<select name="program_id" onchange="this.form.submit()"><option value="">Seçin</option><?php foreach($programs as $p):?><option value="<?php echo esc_attr($p->id);?>" <?php selected($pid,$p->id);?>><?php echo esc_html($p->program_code.' — '.$p->province_name.' / '.$p->district_name);?></option><?php endforeach;?></select></label></form></div>
            <?php if(!$program||!$plan):?><div class="notice notice-info"><p>Program seçin.</p></div></div><?php return;endif;?>

            <div class="mmc-cards mmc-cards-5">
                <div class="mmc-card"><span>Operasyon Tipi</span><strong style="font-size:20px"><?php echo esc_html(MMC_Operations_Service::operation_modes()[$plan->operation_mode]??$plan->operation_mode);?></strong><small><?php echo esc_html(MMC_Operations_Service::plan_statuses()[$plan->status]??$plan->status);?></small></div>
                <div class="mmc-card"><span>Hareket Öncesi Hazırlık</span><strong>%<?php echo esc_html($summary['pre_percent']);?></strong><small><?php echo esc_html($summary['pre_done'].' / '.$summary['pre_total']);?> zorunlu kontrol</small></div>
                <div class="mmc-card"><span>Sorun</span><strong><?php echo esc_html($summary['problems']);?></strong><small>Kontrol listesi</small></div>
                <div class="mmc-card"><span>Ekip</span><strong><?php echo esc_html($summary['artists']+$summary['people']);?></strong><small><?php echo esc_html($summary['artists']);?> sanatçı · <?php echo esc_html($summary['people']);?> personel</small></div>
                <div class="mmc-card"><span>Lojistik</span><strong><?php echo esc_html($summary['vehicles']);?> araç</strong><small><?php echo esc_html($summary['equipment']);?> ekipman kaydı</small></div>
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
                        <label class="mmc-span-2">Konaklama adresi<textarea name="lodging_address"><?php echo esc_textarea($plan->lodging_address);?></textarea></label>
                        <label>Oda sayısı<input type="number" min="0" name="lodging_rooms" value="<?php echo esc_attr($plan->lodging_rooms);?>"></label>
                        <label>Konaklama bütçesi<input type="number" step="0.01" min="0" name="lodging_cost" value="<?php echo esc_attr($plan->lodging_cost);?>"></label>
                        <label class="mmc-span-2">Yemek planı<textarea name="meal_plan" placeholder="Öğle / akşam, kişi sayısı, tedarikçi..."><?php echo esc_textarea($plan->meal_plan);?></textarea></label>
                        <label>Yemek bütçesi<input type="number" step="0.01" min="0" name="meal_cost" value="<?php echo esc_attr($plan->meal_cost);?>"></label>
                        <label>Ulaşım / yakıt bütçesi<input type="number" step="0.01" min="0" name="transport_cost" value="<?php echo esc_attr($plan->transport_cost);?>"></label>
                        <label>Diğer operasyon gideri<input type="number" step="0.01" min="0" name="other_cost" value="<?php echo esc_attr($plan->other_cost);?>"></label>
                        <label class="mmc-span-2">Notlar<textarea name="notes"><?php echo esc_textarea($plan->notes);?></textarea></label>
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
                <div class="mmc-panel"><h2>5. Gün / Akış Planı</h2><p>Seanslar ve plan saatleri sistemden otomatik gelir. Manuel kalem de ekleyebilirsiniz.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_sync_schedule"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_sync_'.$pid);?><button class="button">Etkinlik / Seanslardan Akışı Yenile</button></form><div class="mmc-table-scroll" style="margin-top:12px"><table class="widefat striped"><thead><tr><th>Saat</th><th>İş</th><th>Durum</th></tr></thead><tbody><?php if(!$schedule):?><tr><td colspan="3">Akış kalemi yok.</td></tr><?php else:foreach($schedule as $s):?><tr><td><?php echo esc_html($s->start_at?wp_date('d.m.Y H:i',strtotime($s->start_at)):'—');?></td><td><strong><?php echo esc_html($s->title);?></strong><?php if($s->assigned_name):?><br><small><?php echo esc_html($s->assigned_name);?></small><?php endif;?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_schedule_status"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><input type="hidden" name="schedule_id" value="<?php echo esc_attr($s->id);?>"><?php wp_nonce_field('mmc_ops_sched_'.$pid.'_'.$s->id);?><select name="status"><option value="planned" <?php selected($s->status,'planned');?>>Planlandı</option><option value="ready" <?php selected($s->status,'ready');?>>Hazır</option><option value="done" <?php selected($s->status,'done');?>>Tamam</option><option value="cancelled" <?php selected($s->status,'cancelled');?>>İptal</option></select><button class="button">Kaydet</button></form></td></tr><?php endforeach;endif;?></tbody></table></div></div>
                <div class="mmc-panel"><h2>Manuel Akış Kalemi Ekle</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_ops_add_schedule"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_ops_add_sched_'.$pid);?><div class="mmc-form-grid"><label>Başlık<input name="title" required placeholder="Öğle yemeği / sanatçı transferi"></label><label>Tür<select name="item_type"><option value="transfer">Transfer</option><option value="meal">Yemek</option><option value="rehearsal">Prova</option><option value="setup">Kurulum</option><option value="other">Diğer</option></select></label><label>Başlangıç<input type="datetime-local" name="start_at"></label><label>Bitiş<input type="datetime-local" name="end_at"></label><label>Sorumlu<input name="assigned_name"></label><label>Not<input name="notes"></label></div><?php submit_button('Akışa Ekle','secondary');?></form></div>
            </div>

            <div class="mmc-panel"><h2>6. Seans Satış / Doluluk Kontrolü</h2><?php if(!$event):?><p>Etkinlik kaydı henüz oluşmadı.</p><?php elseif(!$session_sales):?><p>Seans yok.</p><?php else:?><table class="widefat striped"><thead><tr><th>Seans</th><th>Kapasite</th><th>Satılan Kişi</th><th>Kalan</th><th>Doluluk</th><th>Ciro</th></tr></thead><tbody><?php foreach($session_sales as $row):?><tr><td><?php echo esc_html(wp_date('d.m.Y H:i',strtotime($row['session']->session_time)));?></td><td><?php echo esc_html(number_format_i18n($row['session']->capacity));?></td><td><?php echo esc_html(number_format_i18n($row['sold_capacity']));?></td><td><?php echo esc_html(number_format_i18n($row['remaining']));?></td><td>%<?php echo esc_html($row['occupancy']);?></td><td><?php echo esc_html(number_format_i18n($row['revenue'],2));?> TL</td></tr><?php endforeach;?></tbody></table><?php endif;?></div>
        </div>
        <?php
    }

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
    private function dt_local($value){if(!$value)return '';return wp_date('Y-m-d\\TH:i',strtotime($value));}
}
