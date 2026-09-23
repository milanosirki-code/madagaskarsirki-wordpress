<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Field_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_mmc_field_sync_targets', array( $this, 'sync_targets' ) );
        add_action( 'admin_post_mmc_field_assign', array( $this, 'assign' ) );
        add_action( 'admin_post_mmc_field_add_route', array( $this, 'add_route' ) );
        add_action( 'admin_post_mmc_field_create_token', array( $this, 'create_token' ) );
        add_action( 'admin_post_mmc_field_revoke_token', array( $this, 'revoke_token' ) );
        add_action( 'admin_post_mmc_field_export_kml', array( $this, 'export_kml' ) );
    }

    public function menu() {
        add_submenu_page( 'mmc-dashboard', 'Okul / Saha Tanıtımı', 'Okul / Saha', 'mmc_manage_field', 'mmc-field', array( $this, 'page' ) );
    }

    private function guard() {
        if ( ! current_user_can( 'mmc_manage_field' ) ) { wp_die( 'Bu alan için yetkiniz yok.' ); }
    }

    public function page() {
        $this->guard();
        $programs = MMC_Program_Service::all_programs();
        $pid = absint( $_GET['program_id'] ?? 0 );
        if ( ! $pid && $programs ) { $pid = (int) $programs[0]->id; }
        $program = $pid ? MMC_Program_Service::get_program( $pid ) : null;
        $status_filter = sanitize_key( $_GET['field_status'] ?? '' );
        $district_filter = sanitize_text_field( wp_unslash( $_GET['field_district'] ?? '' ) );
        $search = sanitize_text_field( wp_unslash( $_GET['field_search'] ?? '' ) );
        $summary = $pid ? MMC_Field_Service::summary( $pid ) : array();
        $targets = $pid ? MMC_Field_Service::targets( $pid, array( 'status'=>$status_filter, 'district'=>$district_filter, 'search'=>$search, 'limit'=>1000 ) ) : array();
        $routes = $pid ? MMC_Field_Service::routes( $pid ) : array();
        $visits = $pid ? MMC_Field_Service::recent_visits( $pid, 30 ) : array();
        $tokens = $pid ? MMC_Field_Service::active_tokens( $pid ) : array();
        $statuses = MMC_Field_Service::target_statuses();
        $districts = $pid && class_exists('MMC_Region_Service') ? MMC_Region_Service::get_program_targets( $pid ) : array();
        $users = get_users( array( 'orderby'=>'display_name', 'order'=>'ASC', 'fields'=>array('ID','display_name','user_login') ) );
        $school_source = class_exists('MMC_School_Source_Service') ? MMC_School_Source_Service::source_info() : array('external'=>false,'label'=>'MMC okul cache','menu_url'=>'');
        ?>
        <div class="wrap mmc-wrap">
            <h1>Okul & Saha Tanıtım Modülü</h1>
            <p class="mmc-lead">Okul ana listesi Okul Tanıtım menüsünden okunur; MMC yalnız Program hedefi, personel, KML/rota, ziyaret, tabela fotoğrafı ve öğrenci erişimini izler.</p>
            <?php $this->notice(); ?>

            <div class="mmc-panel"><form method="get" class="mmc-inline-form"><input type="hidden" name="page" value="mmc-field"><label>Program<select name="program_id" onchange="this.form.submit()"><option value="">Seçin</option><?php foreach($programs as $p):?><option value="<?php echo esc_attr($p->id);?>" <?php selected($pid,$p->id);?>><?php echo esc_html($p->program_code.' — '.$p->province_name.' / '.$p->district_name);?></option><?php endforeach;?></select></label><noscript><?php submit_button('Göster','secondary','',false);?></noscript></form></div>

            <?php if ( ! $program ) : ?><div class="notice notice-info"><p>Program seçin.</p></div></div><?php return; endif; ?>

            <div class="mmc-panel">
                <h2>Okul Ana Kaynağı</h2>
                <?php if ( ! empty($school_source['external']) ) : ?>
                    <p><strong>✅ <?php echo esc_html($school_source['label']); ?></strong> tek okul ana kaynağı olarak bağlıdır. MMC okul adını/adresini burada ayrıca yönetmez; Programa hedef seçildiğinde tarihsel snapshot saklanır.</p>
                    <?php if ( ! empty($school_source['menu_url']) ) : ?><a class="button" href="<?php echo esc_url($school_source['menu_url']); ?>">Okul Tanıtım Menüsünü Aç</a><?php endif; ?>
                <?php else : ?>
                    <p><strong>🟡 Okul Tanıtım ana kaynağı henüz otomatik doğrulanamadı.</strong> Geriye uyumluluk için mevcut MMC okul cache'i kullanılabilir; Kurulum & Sağlık ekranı kaynak durumunu gösterir.</p>
                    <?php if ( ! empty($school_source['menu_url']) ) : ?><a class="button" href="<?php echo esc_url($school_source['menu_url']); ?>">Okul Tanıtım Menüsünü Aç</a><?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="mmc-cards mmc-cards-5">
                <div class="mmc-card"><span>Hedef Okul</span><strong><?php echo esc_html(number_format_i18n($summary['target_schools']));?></strong><small><?php echo esc_html($summary['districts']);?> ilçe</small></div>
                <div class="mmc-card"><span>Ziyaret Edildi</span><strong><?php echo esc_html(number_format_i18n($summary['visited_schools']));?></strong><small>%<?php echo esc_html($summary['visit_percent']);?> okul</small></div>
                <div class="mmc-card"><span>Hedef Öğrenci</span><strong><?php echo esc_html(number_format_i18n($summary['target_students']));?></strong><small>Program snapshot</small></div>
                <div class="mmc-card"><span>Ziyaret Edilen Okul Havuzu</span><strong><?php echo esc_html(number_format_i18n($summary['covered_students']));?></strong><small>%<?php echo esc_html($summary['coverage_percent']);?> hedef öğrenci</small></div>
                <div class="mmc-card"><span>Fiilen Bildirilen Erişim</span><strong><?php echo esc_html(number_format_i18n($summary['reported_students']));?></strong><small><?php echo esc_html($summary['photo_visits']);?> fotoğraflı ziyaret</small></div>
            </div>

            <div class="mmc-grid-2">
                <div class="mmc-panel">
                    <h2>1. Hedef Okul Havuzunu Oluştur</h2>
                    <p>Hazırlık Dashboardunda seçilen ilçelerdeki okulları <strong>Okul Tanıtım ana listesinden</strong> Programa bağlar. Okul Tanıtım kaydı daha sonra değişse bile programın ilk hedef öğrenci snapshotı korunur.</p>
                    <p><strong>Tanıtım ilçeleri:</strong> <?php echo esc_html( $districts ? implode(', ',$districts) : 'Henüz seçilmedi' );?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_field_sync_targets"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_field_sync_'.$pid); submit_button('Okul Tanıtım Listesinden Hedef Okulları Programa Bağla','primary','',false);?></form>
                </div>
                <div class="mmc-panel">
                    <h2>KML / Harita</h2>
                    <p>Hedef okul havuzunu Google Earth / My Maps ve benzeri uygulamalara aktarılabilecek KML olarak üretir. Koordinatı olan okullar nokta, diğerleri adres olarak dosyaya girer.</p>
                    <?php $kml = wp_nonce_url( add_query_arg(array('action'=>'mmc_field_export_kml','program_id'=>$pid),admin_url('admin-post.php')), 'mmc_field_kml_'.$pid );?>
                    <a class="button button-primary" href="<?php echo esc_url($kml);?>">KML Dosyasını Al</a>
                </div>
            </div>

            <div class="mmc-panel">
                <h2>2. Okul Havuzu & Personel Dağıtımı</h2>
                <form method="get" class="mmc-inline-form"><input type="hidden" name="page" value="mmc-field"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><label>İlçe<select name="field_district"><option value="">Tümü</option><?php foreach($districts as $d):?><option value="<?php echo esc_attr($d);?>" <?php selected($district_filter,$d);?>><?php echo esc_html($d);?></option><?php endforeach;?></select></label><label>Durum<select name="field_status"><option value="">Tümü</option><?php foreach($statuses as $k=>$v):?><option value="<?php echo esc_attr($k);?>" <?php selected($status_filter,$k);?>><?php echo esc_html($v);?></option><?php endforeach;?></select></label><label>Ara<input type="search" name="field_search" value="<?php echo esc_attr($search);?>" placeholder="Okul adı / adres"></label><?php submit_button('Filtrele','secondary','',false);?></form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_field_assign"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_field_assign_'.$pid);?>
                    <div class="mmc-action-row"><label>WordPress kullanıcısı <select name="assigned_user_id"><option value="0">—</option><?php foreach($users as $u):?><option value="<?php echo esc_attr($u->ID);?>"><?php echo esc_html($u->display_name.' ('.$u->user_login.')');?></option><?php endforeach;?></select></label><label>veya personel adı <input type="text" name="assigned_name" placeholder="Örn. Metin Uçar"></label><button class="button button-primary" name="field_action" value="assign">Seçili Okulları Ata</button><button class="button" name="field_action" value="skip">Kapsam Dışı Yap</button><button class="button" name="field_action" value="plan">Planlanana Döndür</button></div>
                    <div class="mmc-table-scroll"><table class="widefat striped"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.mmc-school-check').forEach(x=>x.checked=this.checked)"></th><th>Okul</th><th>İlçe</th><th>Kademe</th><th>Öğrenci</th><th>Personel</th><th>Durum</th><th>Son Ziyaret</th></tr></thead><tbody><?php if(!$targets):?><tr><td colspan="8">Saha havuzunda okul yok. Önce üstteki aktarım işlemini yapın.</td></tr><?php else:foreach($targets as $t):?><tr><td><input class="mmc-school-check" type="checkbox" name="target_ids[]" value="<?php echo esc_attr($t->id);?>"></td><td><strong><?php echo esc_html($t->school_name);?></strong><br><small><?php echo esc_html($t->address);?></small></td><td><?php echo esc_html($t->district_name);?></td><td><?php echo esc_html($t->education_level?:$t->school_type);?></td><td><?php echo esc_html(null===$t->student_count_snapshot?'-':number_format_i18n($t->student_count_snapshot));?></td><td><?php echo esc_html($t->assigned_name?:'-');?></td><td><span class="mmc-badge"><?php echo esc_html($statuses[$t->status]??$t->status);?></span></td><td><?php echo esc_html($t->last_visit_at?:'-');?></td></tr><?php endforeach;endif;?></tbody></table></div>
                </form>
            </div>

            <div class="mmc-grid-2">
                <div class="mmc-panel"><h2>3. Circuit / Spoke / Diğer Rota Bağlantısı</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_field_add_route"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_field_route_'.$pid);?><div class="mmc-form-grid"><label>Başlık<input name="title" required placeholder="Sincan 1. Bölge Rotası"></label><label>Uygulama<select name="route_app"><option value="circuit">Circuit</option><option value="spoke">Spoke</option><option value="google_maps">Google Maps</option><option value="custom">Diğer</option></select></label><label class="mmc-span-2">Rota bağlantısı<input type="url" name="external_url" required></label><label>İlçe<input name="district_name"></label><label>Personel adı<input name="assigned_name"></label><label class="mmc-span-2">Not<textarea name="notes" rows="2"></textarea></label></div><?php submit_button('Rota Bağlantısını Kaydet','secondary','',false);?></form><?php if($routes):?><hr><ul><?php foreach($routes as $r):?><li><strong><?php echo esc_html($r->title);?></strong> · <?php echo esc_html(strtoupper($r->route_app));?><?php if($r->assigned_name):?> · <?php echo esc_html($r->assigned_name);?><?php endif;?> — <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($r->external_url);?>">Aç</a></li><?php endforeach;?></ul><?php endif;?></div>

                <div class="mmc-panel"><h2>4. Personel Saha Portalı Paylaşım Bağlantısı</h2><p>İlk testlerde personele WordPress hesabı açmak zorunda değilsiniz. Program veya personel adına süreli bir güvenli bağlantı oluşturabilirsiniz. Ham bağlantı yalnız oluşturulduğu anda gösterilir; sistem yalnız token özetini saklar.</p>
                <?php if(!empty($_GET['mmc_new_token'])): $raw=sanitize_text_field(wp_unslash($_GET['mmc_new_token'])); $share=MMC_Field_Service::portal_url($raw);?><div class="notice notice-success inline"><p><strong>Yeni saha bağlantısı:</strong></p><p><input style="width:100%" readonly value="<?php echo esc_attr($share);?>"></p><p>Bu bağlantıyı şimdi kopyalayın. Güvenlik nedeniyle ham token tekrar görüntülenemez.</p></div><?php endif;?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_field_create_token"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><?php wp_nonce_field('mmc_field_token_'.$pid);?><div class="mmc-form-grid"><label>Personel adı (boşsa program geneli)<input name="assigned_name"></label><label>Geçerlilik (gün)<input type="number" name="days" min="1" max="90" value="14"></label></div><?php submit_button('Paylaşım Bağlantısı Oluştur','primary','',false);?></form>
                <?php if($tokens):?><table class="widefat striped"><thead><tr><th>Token</th><th>Personel</th><th>Son Kullanım</th><th>Son Erişim</th><th></th></tr></thead><tbody><?php foreach($tokens as $tok):?><tr><td><code><?php echo esc_html($tok->token_hint);?></code></td><td><?php echo esc_html($tok->assigned_name?:'Program geneli');?></td><td><?php echo esc_html($tok->expires_at?:'-');?></td><td><?php echo esc_html($tok->last_used_at?:'-');?></td><td><?php if($tok->is_active):?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="mmc_field_revoke_token"><input type="hidden" name="program_id" value="<?php echo esc_attr($pid);?>"><input type="hidden" name="token_id" value="<?php echo esc_attr($tok->id);?>"><?php wp_nonce_field('mmc_field_revoke_'.$tok->id);?><button class="button button-small">İptal Et</button></form><?php else:?>Pasif<?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?></div>
            </div>

            <div class="mmc-panel"><h2>Son Saha Ziyaretleri</h2><table class="widefat striped"><thead><tr><th>Tarih</th><th>Okul</th><th>İlçe</th><th>Personel</th><th>Sonuç</th><th>Öğrenci</th><th>Materyal</th><th>Fotoğraf</th><th>Not</th></tr></thead><tbody><?php if(!$visits):?><tr><td colspan="9">Henüz saha ziyareti yok.</td></tr><?php else:foreach($visits as $v):?><tr><td><?php echo esc_html($v->visited_at);?></td><td><?php echo esc_html($v->school_name);?></td><td><?php echo esc_html($v->district_name);?></td><td><?php echo esc_html($v->visitor_name);?></td><td><?php echo esc_html(MMC_Field_Service::visit_statuses()[$v->visit_status]??$v->visit_status);?></td><td><?php echo esc_html(number_format_i18n($v->students_reached));?></td><td><?php echo esc_html(number_format_i18n($v->materials_delivered));?></td><td><?php if($v->photo_attachment_id):?><a target="_blank" href="<?php echo esc_url(wp_get_attachment_url($v->photo_attachment_id));?>">Fotoğraf</a><?php else:?>—<?php endif;?></td><td><?php echo esc_html($v->notes);?></td></tr><?php endforeach;endif;?></tbody></table></div>
        </div>
        <?php
    }

    public function sync_targets() {
        $this->guard(); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_field_sync_'.$pid);
        $r=MMC_Field_Service::sync_target_schools($pid);
        if(is_wp_error($r))$this->redirect($pid,$r->get_error_message(),'error');
        $this->redirect($pid,sprintf('%s kaynağından %d yeni okul bağlandı; %d mevcut hedef korundu.',$r['source']??'Okul Tanıtım',$r['created'],$r['updated']),'success');
    }

    public function assign() {
        $this->guard(); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_field_assign_'.$pid);
        $field_action=sanitize_key($_POST['field_action']??'assign');
        if('skip'===$field_action || 'plan'===$field_action){
            $r=MMC_Field_Service::bulk_target_status($pid,$_POST['target_ids']??array(),'skip'===$field_action?'skipped':'planned');
            $this->redirect($pid,is_wp_error($r)?$r->get_error_message():$r.' okul kapsam durumu güncellendi.',is_wp_error($r)?'error':'success');
        }
        $r=MMC_Field_Service::assign_targets($pid,$_POST['target_ids']??array(),absint($_POST['assigned_user_id']??0),sanitize_text_field(wp_unslash($_POST['assigned_name']??'')));
        $this->redirect($pid,is_wp_error($r)?$r->get_error_message():$r.' okul personele atandı.',is_wp_error($r)?'error':'success');
    }

    public function add_route() {
        $this->guard(); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_field_route_'.$pid);
        $r=MMC_Field_Service::add_route($pid,$_POST);
        $this->redirect($pid,is_wp_error($r)?$r->get_error_message():'Rota bağlantısı kaydedildi.',is_wp_error($r)?'error':'success');
    }

    public function create_token() {
        $this->guard(); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_field_token_'.$pid);
        $r=MMC_Field_Service::create_share_token($pid,sanitize_text_field(wp_unslash($_POST['assigned_name']??'')),0,absint($_POST['days']??14));
        if(is_wp_error($r))$this->redirect($pid,$r->get_error_message(),'error');
        wp_safe_redirect(add_query_arg(array('page'=>'mmc-field','program_id'=>$pid,'mmc_notice'=>'Paylaşım bağlantısı oluşturuldu.','mmc_type'=>'success','mmc_new_token'=>$r['token']),admin_url('admin.php'))); exit;
    }

    public function revoke_token() {
        $this->guard(); $pid=absint($_POST['program_id']??0); $tid=absint($_POST['token_id']??0); check_admin_referer('mmc_field_revoke_'.$tid);
        MMC_Field_Service::revoke_token($tid,$pid); $this->redirect($pid,'Saha paylaşım bağlantısı iptal edildi.','success');
    }

    public function export_kml() {
        $this->guard(); $pid=absint($_GET['program_id']??0); check_admin_referer('mmc_field_kml_'.$pid);
        MMC_Field_Service::output_kml($pid);
    }

    private function notice() {
        if(empty($_GET['mmc_notice']))return; $type=sanitize_key($_GET['mmc_type']??'success'); $class='error'===$type?'notice-error':('warning'===$type?'notice-warning':'notice-success');
        echo '<div class="notice '.esc_attr($class).' is-dismissible"><p>'.esc_html(wp_unslash($_GET['mmc_notice'])).'</p></div>';
    }

    private function redirect($pid,$message,$type='success') {
        wp_safe_redirect(add_query_arg(array('page'=>'mmc-field','program_id'=>$pid,'mmc_notice'=>$message,'mmc_type'=>$type),admin_url('admin.php'))); exit;
    }
}
