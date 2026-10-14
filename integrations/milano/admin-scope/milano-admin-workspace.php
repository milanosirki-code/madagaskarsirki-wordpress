<?php
/**
 * Plugin Name: Milano Sirki - Brand Scoped Admin Workspace
 * Description: Separate Milano admin menu over canonical MMC-MDG records. Program creation through the canonical MMC service; separate Milano workspace.
 * Version: 0.2.0
 * Author: Dünya Organizasyon
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class Milano_Brand_Admin_Workspace {
    const BRAND = 'Milano Sirki';
    const MENU = 'milano-brand-workspace';

    public static function boot() {
        add_action('admin_post_milano_create_program', array(__CLASS__, 'create_program'));
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 70);
    }

    public static function register_menu() {
        if (!current_user_can('manage_woocommerce')) { return; }
        add_menu_page('Milano Yönetim', 'Milano', 'manage_woocommerce', self::MENU, array(__CLASS__, 'overview'), 'dashicons-tickets-alt', 27);
        add_submenu_page(self::MENU, 'Milano Genel Bakış', 'Genel Bakış', 'manage_woocommerce', self::MENU, array(__CLASS__, 'overview'));
        add_submenu_page(self::MENU, 'Milano Programları', 'Programlar', 'manage_woocommerce', 'milano-programs', array(__CLASS__, 'programs_page'));
        add_submenu_page(self::MENU, 'Milano Bilet Hazırlığı', 'Bilet Hazırlığı', 'manage_woocommerce', 'milano-tickets', array(__CLASS__, 'tickets_page'));
    }

    private static function guard() {
        if (!current_user_can('manage_woocommerce')) { wp_die(esc_html__('Bu alana erişim yetkiniz bulunmuyor.', 'milano-admin')); }
    }

    private static function programmes() {
        global $wpdb;
        $table=$wpdb->prefix.'mmc_programs';
        $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists!==$table) { return new WP_Error('missing_table','MMC program tablosu bulunamadı.'); }
        $wpdb->last_error='';
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT id,program_code,brand,province_name,district_name,planned_date,status FROM {$table} WHERE brand=%s ORDER BY planned_date DESC,id DESC LIMIT 250",
            self::BRAND
        ));
        if ($wpdb->last_error) { return new WP_Error('db_error','Program sorgusu başarısız.'); }
        return is_array($rows) ? $rows : array();
    }

    private static function verified_event_for_program($program_id) {
        global $wpdb;
        $bridge_table=$wpdb->prefix.'mmc_mdg_event_bridge';
        $progs_table=$wpdb->prefix.'mmc_programs';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$bridge_table))!==$bridge_table) {return null;}
        $links=$wpdb->get_results($wpdb->prepare(
            "SELECT b.mdg_event_id FROM {$bridge_table} b INNER JOIN {$progs_table} p ON p.id=b.program_id ".
            "WHERE b.program_id=%d AND p.brand=%s AND b.is_active=1 LIMIT 2", absint($program_id), self::BRAND
        ));
        if ($wpdb->last_error || !is_array($links) || count($links)!==1) {return null;}
        $event_id=absint($links[0]->mdg_event_id);
        if (!$event_id || !class_exists('MDG_Events')) {return null;}
        $event=MDG_Events::get($event_id);
        if (!$event || false===stripos((string)$event->title, 'Milano')
            || false!==stripos((string)$event->title,'Madagaskar')) {return null;}
        return $event;
    }

    private static function header($title) {
        self::guard();
        echo '<div class="wrap" id="milano-brand-workspace"><h1>'.esc_html($title).'</h1>';
        echo '<p><strong>Milano Sirki</strong> · Ayrı marka yönetimi / ortak merkezî bilet altyapısı</p>';
        if (!empty($_GET['created'])) { echo '<div class="notice notice-success"><p>Milano program dosyası oluşturuldu.</p></div>'; }
        echo '<nav class="nav-tab-wrapper" aria-label="Milano yönetim menüsü">';
        $links=array(
            array(self::MENU,'Genel Bakış'),
            array('milano-programs','Programlar'),
            array('milano-tickets','Bilet Hazırlığı'),
        );
        $current=isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : self::MENU;
        foreach($links as $link) {
            $url=admin_url('admin.php?page='.$link[0]);
            echo '<a class="nav-tab '.($current===$link[0]?'nav-tab-active':'').'" href="'.esc_url($url).'">'.esc_html($link[1]).'</a>';
        }
        echo '</nav><div class="notice notice-info inline" style="margin:18px 0"><p><strong>Güvenli hazırlık modu:</strong> Program dosyası ortak MMC servisinden oluşturulur. Satış ve ödeme, bilet hazırlığı tamamlandıktan sonra açılır.</p></div>';
    }

    private static function footer() { echo '</div>'; }

    public static function overview() {
        self::header('Milano · Yönetim Merkezi');
        $progs=self::programmes();
        if (is_wp_error($progs)) {echo '<div class="notice notice-error"><p>'.esc_html($progs->get_error_message()).'</p></div>';self::footer();return;}
        echo '<div style="display:flex;gap:14px;margin-top:20px;flex-wrap:wrap">';
        echo '<div class="postbox" style="padding:18px;min-width:190px"><strong>Milano programı</strong><p style="font-size:26px;margin:8px 0">'.esc_html(count($progs)).'</p></div>';
        $with_bridge=0;
        foreach($progs as $p){if(self::verified_event_for_program($p->id)){$with_bridge++;}}
        echo '<div class="postbox" style="padding:18px;min-width:190px"><strong>Doğrulanmış MDG bağı</strong><p style="font-size:26px;margin:8px 0">'.esc_html($with_bridge).'</p></div></div>';
        echo '<h2>Marka izolasyonu</h2><p>Yalnız <code>mmc_programs.brand = Milano Sirki</code> olan programlar listelenir. MDG kaydı ancak tek aktif MMC bağlantısı ve Milano etkinlik kimliği doğrulanırsa gösterilir. Madagaskar programları bu menüden yönetilemez.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=milano-programs')).'">Milano programlarını görüntüle</a></p>';
        self::footer();
    }

    public static function programs_page() {
        self::header('Milano · Programlar');
        self::creation_form();
        $progs=self::programmes();
        if(is_wp_error($progs)){echo '<div class="notice notice-error"><p>'.esc_html($progs->get_error_message()).'</p></div>';self::footer();return;}
        echo '<table class="widefat striped" style="margin-top:18px"><thead><tr><th>Program</th><th>Tarih</th><th>İl/İlçe</th><th>Durum</th><th>Kanonik MDG</th></tr></thead><tbody>';
        foreach($progs as $p) {
            $e=self::verified_event_for_program($p->id);
            echo '<tr><td><strong>'.esc_html($p->program_code).'</strong><br><small>#'.esc_html($p->id).'</small><br><a href="'.esc_url(admin_url('admin.php?page=mmc-events&program_id='.(int)$p->id)).'">Etkinlik hazırlığı</a></td>';
            echo '<td>'.esc_html($p->planned_date).'</td><td>'.esc_html($p->province_name.' / '.$p->district_name).'</td>';
            echo '<td>'.esc_html($p->status).'</td><td>'.($e?'#'.esc_html($e->id).' — '.esc_html($e->title):'<span class="dashicons dashicons-warning"></span> Bağ veya kimlik doğrulanamadı').'</td></tr>';
        }
        if(!$progs){echo '<tr><td colspan="5">Milano markalı program bulunamadı.</td></tr>';}
        echo '</tbody></table>';
        self::footer();
    }


    public static function creation_form() {
        echo '<h2>Yeni Milano Programı</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('milano_create_program');
        echo '<input type="hidden" name="action" value="milano_create_program">';
        echo '<p>Marka: <strong>Milano Sirki</strong></p>';
        foreach(array('province_name'=>'İl','district_name'=>'İlçe','planned_date'=>'Tarih') as $key=>$label) {
            echo '<p><label>'.esc_html($label).' <input required name="'.esc_attr($key).'" type="'.($key==='planned_date'?'date':'text').'"></label></p>';
        }
        echo '<button class="button button-primary">Milano Programını Oluştur</button></form>';
    }

    public static function create_program() {
        self::guard();
        check_admin_referer('milano_create_program');
        if (!class_exists('MMC_Program_Service') || (!method_exists('MMC_Program_Service','supports_brand_input') && !method_exists('MMC_Program_Service','normalized_program_brand'))) {
            wp_die('MMC marka oluşturma desteği henüz devreye alınmadı. Kayıt oluşturulmadı.');
        }
        $province=sanitize_text_field(wp_unslash($_POST['province_name']??''));
        $district=sanitize_text_field(wp_unslash($_POST['district_name']??''));
        $date=sanitize_text_field(wp_unslash($_POST['planned_date']??''));
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$province || !$district || !$dt || $dt->format('Y-m-d')!==$date) { wp_die('İl, ilçe ve tarih geçersiz.'); }
        global $wpdb;
        // A lock plus an exact canonical lookup makes refreshes/double submissions idempotent.
        $lock='milano_program_create_'.md5($province.'|'.$district.'|'.$date);
        if (!add_option($lock,time(),'','no')) { wp_die('Bu program için başka bir oluşturma işlemi sürüyor.'); }
        try {
            $province=class_exists('MMC_Region_Service')?MMC_Region_Service::normalize_place_name($province):$province;
            $district=class_exists('MMC_Region_Service')?MMC_Region_Service::normalize_place_name($district):$district;
            $existing=$wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}mmc_programs WHERE brand=%s AND province_name=%s AND district_name=%s AND planned_date=%s LIMIT 2",self::BRAND,$province,$district,$date));
            if ($wpdb->last_error || count((array)$existing)>1) { throw new RuntimeException('Program kimliği belirsiz; kayıt oluşturulmadı.'); }
            if ($existing) { $id=(int)$existing[0]->id; }
            else {
                $id=MMC_Program_Service::create_program(array(
                    'brand'=>self::BRAND,'province_name'=>$province,'district_name'=>$district,
                    'planned_date'=>$date,'plan_year'=>(int)$dt->format('Y'),
                    'notes'=>'Milano sitesinde bilet seçimi; tahsilat ortak Madagaskar ödeme altyapısında.'
                ));
                if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
                $p=MMC_Program_Service::get_program($id);
                if (!$p || $p->brand!==self::BRAND) { throw new RuntimeException('Program markası doğrulanamadı.'); }
            }
        } catch (Throwable $e) {
            delete_option($lock);
            wp_die(esc_html($e->getMessage()));
        }
        delete_option($lock);
        wp_safe_redirect(admin_url('admin.php?page=milano-programs&created='.(int)$id));
        exit;
    }

    public static function tickets_page() {
        self::header('Milano · Bilet Hazırlığı');
        $progs=self::programmes();
        if(is_wp_error($progs)){echo '<div class="notice notice-error"><p>'.esc_html($progs->get_error_message()).'</p></div>';self::footer();return;}
        global $wpdb;
        foreach($progs as $p){
            $e=self::verified_event_for_program($p->id);
            if(!$e){continue;}
            $rows=$wpdb->get_results($wpdb->prepare(
                'SELECT id,start_at,capacity_total,held_units,sold_units,wc_product_id,tickera_event_id FROM '.$wpdb->prefix.'mdg_sessions WHERE event_id=%d ORDER BY start_at ASC LIMIT 50', absint($e->id)
            ));
            echo '<div class="postbox" style="padding:18px;margin-top:18px">';
            echo '<h2>'.esc_html($e->title).' · MDG #'.esc_html($e->id).'</h2>';
            echo '<p>Etkinlik durumu: <strong>'.esc_html($e->status).'</strong> · Kapak #'.esc_html((int)$e->hero_attachment_id).' · Salon QR #'.esc_html((int)$e->venue_qr_attachment_id).'</p>';
            echo '<table class="widefat striped"><thead><tr><th>Seans (Türkiye)</th><th>Kapasite</th><th>Rezerve</th><th>Satılan</th><th>Woo</th><th>Tickera</th></tr></thead><tbody>';
            foreach((array)$rows as $s) {
                $local=(string)$s->start_at;
                try{$local=(new DateTimeImmutable((string)$s->start_at,new DateTimeZone('UTC')))->setTimezone(wp_timezone())->format('d.m.Y H:i');}catch(Throwable $err){}
                echo '<tr><td>'.esc_html($local).'</td><td>'.esc_html($s->capacity_total).'</td><td>'.esc_html($s->held_units).'</td><td>'.esc_html($s->sold_units).'</td><td>'.esc_html($s->wc_product_id?:'Taslak yok').'</td><td>'.esc_html($s->tickera_event_id?:'Taslak yok').'</td></tr>';
            }
            echo '</tbody></table><p><strong>Güvenlik kapısı:</strong> Milano bilet şablonu ve merkezi Madagaskar ödeme akışı doğrulanmadan satış açılmaz.</p></div>';
        }
        self::footer();
    }
}
Milano_Brand_Admin_Workspace::boot();
