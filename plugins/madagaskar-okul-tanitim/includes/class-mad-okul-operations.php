<?php
if (!defined('ABSPATH')) exit;

final class Mad_Okul_Operations {
    public static function programs_table() {
        global $wpdb;
        return $wpdb->prefix . 'mad_okul_programlar';
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::programs_table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_adi varchar(190) NOT NULL,
            il varchar(100) NOT NULL,
            ilce varchar(100) NOT NULL,
            salon_adi varchar(190) NOT NULL,
            salon_adresi text NOT NULL,
            etkinlik_tarihi date NULL,
            latitude decimal(10,7) NULL,
            longitude decimal(10,7) NULL,
            durum varchar(30) NOT NULL DEFAULT 'Aktif',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY il_ilce (il(40), ilce(40)),
            KEY durum (durum)
        ) $charset;");

        add_role('mad_tanitim_elemani', 'Tanıtım Elemanı', [
            'read' => true,
            'mad_okul_field_access' => true,
        ]);
        $admin = get_role('administrator');
        if ($admin) $admin->add_cap('mad_okul_field_access');
    }

    public static function boot() {
        add_action('admin_menu', [__CLASS__, 'menus'], 20);
        add_action('admin_post_mad_okul_save_program', [__CLASS__, 'save_program']);
        add_action('admin_post_mad_okul_assign_tasks', [__CLASS__, 'assign_tasks']);
        add_action('admin_post_mad_okul_task_status', [__CLASS__, 'task_status']);
        add_action('admin_post_mad_okul_save_settings', [__CLASS__, 'save_settings']);
    }

    public static function menus() {
        add_submenu_page('mad-okul', 'Program ve Salonlar', 'Program ve Salonlar', 'manage_options', 'mad-okul-programs', [__CLASS__, 'programs_page']);
        add_submenu_page('mad-okul', 'Görev Dağıtımı', 'Görev Dağıtımı', 'manage_options', 'mad-okul-assign', [__CLASS__, 'assign_page']);
        add_submenu_page('mad-okul', 'Harita Ayarları', 'Harita Ayarları', 'manage_options', 'mad-okul-settings', [__CLASS__, 'settings_page']);
        add_menu_page('Tanıtım Görevlerim', 'Görevlerim', 'mad_okul_field_access', 'mad-okul-my-tasks', [__CLASS__, 'my_tasks_page'], 'dashicons-location', 28);
    }

    private static function programs() {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::programs_table() . ' ORDER BY etkinlik_tarihi DESC, id DESC');
    }

    public static function programs_page() {
        if (!current_user_can('manage_options')) return;
        $programs = self::programs();
        ?>
        <div class="wrap mad-okul-wrap">
          <h1>Program ve Salonlar</h1>
          <p class="description">Gösteri salonu rota planının başlangıç noktasıdır.</p>
          <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Program kaydedildi.</p></div><?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-upload-box">
            <input type="hidden" name="action" value="mad_okul_save_program">
            <?php wp_nonce_field('mad_okul_save_program'); ?>
            <p><label>Program adı<br><input required class="regular-text" name="program_adi" placeholder="Örn. Mamak – 10 Ekim 2026"></label></p>
            <p><label>İl <input required name="il"></label> <label>İlçe <input required name="ilce"></label> <label>Tarih <input type="date" name="etkinlik_tarihi"></label></p>
            <p><label>Salon adı<br><input required class="regular-text" name="salon_adi"></label></p>
            <p><label>Salon adresi<br><textarea required class="large-text" rows="2" name="salon_adresi"></textarea></label></p>
            <p><label>Enlem <input name="latitude" inputmode="decimal"></label> <label>Boylam <input name="longitude" inputmode="decimal"></label></p>
            <button class="button button-primary">Programı Kaydet</button>
          </form>
          <h2>Kayıtlı Programlar</h2>
          <table class="widefat striped"><thead><tr><th>Program</th><th>İl / İlçe</th><th>Salon</th><th>Tarih</th><th>Harita</th></tr></thead><tbody>
          <?php foreach ($programs as $p): ?>
            <tr><td><?php echo esc_html($p->program_adi); ?></td><td><?php echo esc_html($p->il.' / '.$p->ilce); ?></td><td><?php echo esc_html($p->salon_adi); ?><br><small><?php echo esc_html($p->salon_adresi); ?></small></td><td><?php echo esc_html($p->etkinlik_tarihi ?: '-'); ?></td><td><a target="_blank" href="<?php echo esc_url('https://www.google.com/maps/search/?api=1&query='.rawurlencode($p->salon_adi.', '.$p->salon_adresi)); ?>">Maps</a></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
        <?php
    }

    public static function save_program() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        check_admin_referer('mad_okul_save_program');
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(self::programs_table(), [
            'program_adi' => sanitize_text_field($_POST['program_adi'] ?? ''),
            'il' => sanitize_text_field($_POST['il'] ?? ''),
            'ilce' => sanitize_text_field($_POST['ilce'] ?? ''),
            'salon_adi' => sanitize_text_field($_POST['salon_adi'] ?? ''),
            'salon_adresi' => sanitize_textarea_field($_POST['salon_adresi'] ?? ''),
            'etkinlik_tarihi' => sanitize_text_field($_POST['etkinlik_tarihi'] ?? '') ?: null,
            'latitude' => is_numeric($_POST['latitude'] ?? '') ? (float)$_POST['latitude'] : null,
            'longitude' => is_numeric($_POST['longitude'] ?? '') ? (float)$_POST['longitude'] : null,
            'durum' => 'Aktif', 'created_at' => $now, 'updated_at' => $now,
        ]);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-programs','saved'=>1], admin_url('admin.php'))); exit;
    }

    public static function assign_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $programs = self::programs();
        $program_id = absint($_GET['program_id'] ?? 0);
        $program = $program_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d', $program_id)) : null;
        $users = get_users(['role__in'=>['mad_tanitim_elemani','administrator'],'orderby'=>'display_name']);
        $schools = $program ? $wpdb->get_results($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s ORDER BY kurum_adi', $program->il, $program->ilce)) : [];
        ?>
        <div class="wrap mad-okul-wrap"><h1>Görev Dağıtımı</h1>
          <?php if (!empty($_GET['assigned'])): ?><div class="notice notice-success is-dismissible"><p><?php echo absint($_GET['assigned']); ?> okul personele atandı.</p></div><?php endif; ?>
          <form method="get" class="mad-filter"><input type="hidden" name="page" value="mad-okul-assign"><select name="program_id" required><option value="">Program seçin</option><?php foreach($programs as $p): ?><option value="<?php echo (int)$p->id; ?>" <?php selected($program_id,$p->id); ?>><?php echo esc_html($p->program_adi); ?></option><?php endforeach; ?></select><button class="button">Okulları Getir</button></form>
          <?php if ($program): ?>
          <p><strong><?php echo esc_html($program->salon_adi); ?></strong> başlangıç noktası · <?php echo count($schools); ?> kurum</p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mad_okul_assign_tasks"><input type="hidden" name="program_id" value="<?php echo (int)$program->id; ?>"><?php wp_nonce_field('mad_okul_assign_tasks'); ?>
            <p><label>Tanıtım elemanı <select name="assigned_user_id" required><option value="">Seçin</option><?php foreach($users as $u): ?><option value="<?php echo (int)$u->ID; ?>"><?php echo esc_html($u->display_name); ?></option><?php endforeach; ?></select></label> <label>Rota grubu <input name="route_group" value="A" size="6"></label></p>
            <table class="widefat striped"><thead><tr><th><input type="checkbox" id="mad-assign-all"></th><th>Sıra</th><th>Kurum</th><th>Adres</th></tr></thead><tbody><?php foreach($schools as $i=>$s): ?><tr><td><input type="checkbox" name="school_ids[]" value="<?php echo (int)$s->id; ?>"></td><td><?php echo $i+1; ?></td><td><?php echo esc_html($s->kurum_adi); ?></td><td><?php echo esc_html($s->adres); ?></td></tr><?php endforeach; ?></tbody></table>
            <p><button class="button button-primary">Seçilen Okulları Ata</button></p>
          </form><script>document.getElementById('mad-assign-all').addEventListener('change',function(){document.querySelectorAll('input[name="school_ids[]"]').forEach(x=>x.checked=this.checked);});</script>
          <?php endif; ?>
        </div><?php
    }

    public static function assign_tasks() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        check_admin_referer('mad_okul_assign_tasks');
        global $wpdb;
        $ids = array_values(array_filter(array_map('absint', $_POST['school_ids'] ?? [])));
        $uid = absint($_POST['assigned_user_id'] ?? 0); $pid = absint($_POST['program_id'] ?? 0);
        $group = sanitize_text_field($_POST['route_group'] ?? 'A');
        foreach ($ids as $order=>$id) $wpdb->update(mad_okul_table(), ['program_id'=>$pid,'assigned_user_id'=>$uid,'route_group'=>$group,'route_order'=>$order+1,'durum'=>'Atandı','updated_at'=>current_time('mysql')], ['id'=>$id]);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-assign','program_id'=>$pid,'assigned'=>count($ids)], admin_url('admin.php'))); exit;
    }

    private static function directions_url($destination) {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination) . '&travelmode=driving';
    }

    public static function my_tasks_page() {
        if (!current_user_can('mad_okul_field_access')) return;
        global $wpdb; $uid = get_current_user_id();
        $rows = $wpdb->get_results($wpdb->prepare('SELECT s.*,p.program_adi,p.salon_adi,p.salon_adresi,p.etkinlik_tarihi FROM '.mad_okul_table().' s LEFT JOIN '.self::programs_table().' p ON p.id=s.program_id WHERE s.assigned_user_id=%d ORDER BY p.etkinlik_tarihi DESC,s.route_group,s.route_order', $uid));
        ?><div class="wrap mad-okul-wrap"><h1>Tanıtım Görevlerim</h1><p class="description">Görev sırasına göre ilerleyin ve ziyaret sonucunu kaydedin.</p>
        <table class="widefat striped"><thead><tr><th>Rota</th><th>Okul</th><th>Program</th><th>İşlem</th></tr></thead><tbody><?php foreach($rows as $r): $dest=$r->kurum_adi.', '.$r->adres.', '.$r->ilce.', '.$r->il; ?><tr><td><?php echo esc_html($r->route_group.'-'.$r->route_order); ?></td><td><strong><?php echo esc_html($r->kurum_adi); ?></strong><br><?php echo esc_html($r->adres); ?><br><span class="mad-status"><?php echo esc_html($r->durum); ?></span></td><td><?php echo esc_html($r->program_adi ?: '-'); ?><br><small><?php echo esc_html($r->salon_adi ?: ''); ?></small></td><td><a class="button button-primary" target="_blank" href="<?php echo esc_url(self::directions_url($dest)); ?>">Yol Tarifi</a><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px"><input type="hidden" name="action" value="mad_okul_task_status"><input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"><?php wp_nonce_field('mad_okul_task_status_'.$r->id); ?><select name="durum"><option>Ziyaret Edildi</option><option>Afiş Bırakıldı</option><option>Görüşüldü</option><option>Tekrar Gidilecek</option><option>Olumsuz</option></select><input name="notlar" placeholder="Kısa not"><button class="button">Kaydet</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php
    }

    public static function task_status() {
        if (!current_user_can('mad_okul_field_access')) wp_die('Yetkisiz işlem');
        $id=absint($_POST['id'] ?? 0); check_admin_referer('mad_okul_task_status_'.$id); global $wpdb;
        $owner=(int)$wpdb->get_var($wpdb->prepare('SELECT assigned_user_id FROM '.mad_okul_table().' WHERE id=%d',$id));
        if ($owner!==get_current_user_id() && !current_user_can('manage_options')) wp_die('Bu görev size ait değil');
        $wpdb->update(mad_okul_table(),['durum'=>sanitize_text_field($_POST['durum'] ?? 'Ziyaret Edildi'),'notlar'=>sanitize_textarea_field($_POST['notlar'] ?? ''),'son_ziyaret'=>current_time('Y-m-d'),'updated_at'=>current_time('mysql')],['id'=>$id]);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-my-tasks','updated'=>1],admin_url('admin.php'))); exit;
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return; $has_key=(bool)get_option('mad_okul_google_maps_api_key');
        ?><div class="wrap mad-okul-wrap"><h1>Harita Ayarları</h1><p>Google Maps bağlantıları anahtarsız çalışır. Otomatik koordinatlandırma ve optimum rota için Google Maps Platform anahtarı gereklidir.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mad_okul_save_settings"><?php wp_nonce_field('mad_okul_save_settings'); ?><table class="form-table"><tr><th>Google Maps API anahtarı</th><td><input type="password" class="regular-text" name="api_key" value="" autocomplete="new-password" placeholder="<?php echo $has_key?'Kayıtlı — değiştirmek için yenisini girin':'API anahtarını girin'; ?>"><p class="description">Anahtar ekranda tekrar gösterilmez.</p></td></tr></table><button class="button button-primary">Ayarı Kaydet</button></form></div><?php
    }

    public static function save_settings() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem'); check_admin_referer('mad_okul_save_settings');
        $key=trim(sanitize_text_field($_POST['api_key'] ?? '')); if($key) update_option('mad_okul_google_maps_api_key',$key,false);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-settings','saved'=>1],admin_url('admin.php'))); exit;
    }
}
