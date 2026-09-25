<?php
if (!defined('ABSPATH')) exit;

final class Mad_Okul_Operations {
    public static function programs_table() {
        global $wpdb;
        return $wpdb->prefix . 'mad_okul_programlar';
    }

    public static function mmc_program_context($mmc_program_id) {
        global $wpdb;
        $mmc_program_id=absint($mmc_program_id);
        $programs=$wpdb->prefix.'mmc_programs';
        $program_venues=$wpdb->prefix.'mmc_program_venues';
        if(!$mmc_program_id || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$programs))!==$programs){
            return new WP_Error('mad_okul_mmc_missing','MMC program tablosu bulunamadı.');
        }
        $program=$wpdb->get_row($wpdb->prepare("SELECT * FROM $programs WHERE id=%d LIMIT 1",$mmc_program_id));
        if(!$program) return new WP_Error('mad_okul_mmc_program_missing','MMC programı bulunamadı.');

        $venue=null;
        $manual=false;
        if(class_exists('MMC_Venue_Service') && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$program_venues))===$program_venues){
            $approved_id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $program_venues WHERE program_id=%d AND is_selected=1 AND allocation_status='approved' ORDER BY id DESC LIMIT 1",$mmc_program_id));
            if($approved_id) $venue=MMC_Venue_Service::get_program_venue($approved_id);
            if(!$venue){
                $table=self::programs_table();
                $manual_id=(int)$wpdb->get_var($wpdb->prepare("SELECT route_program_venue_id FROM $table WHERE mmc_program_id=%d LIMIT 1",$mmc_program_id));
                if($manual_id){
                    $candidate=MMC_Venue_Service::get_program_venue($manual_id);
                    if(self::venue_matches_program($candidate,$program)){
                        $venue=$candidate;
                        $manual=true;
                    }
                }
            }
        }
        return (object)['program'=>$program,'venue'=>$venue,'manual'=>$manual];
    }

    private static function venue_matches_program($venue,$program){
        return $venue && $program && (int)$venue->program_id===(int)$program->id
            && !empty($venue->venue_name) && !empty($venue->address)
            && mad_okul_place_title($venue->province_name)===mad_okul_place_title($program->province_name)
            && mad_okul_place_title($venue->district_name)===mad_okul_place_title($program->district_name);
    }

    public static function bridge_status($mmc_program_id) {
        global $wpdb;
        $ctx=self::mmc_program_context($mmc_program_id);
        if(is_wp_error($ctx)) return $ctx;
        $table=self::programs_table();
        $linked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE mmc_program_id=%d LIMIT 1",absint($mmc_program_id)));
        if($linked){
            $same_il=mad_okul_place_title($linked->il)===mad_okul_place_title($ctx->program->province_name);
            $same_ilce=mad_okul_place_title($linked->ilce)===mad_okul_place_title($ctx->program->district_name);
            if(!$same_il || !$same_ilce){
                return ['linked'=>false,'ambiguous'=>true,'detail'=>'Bağlı eski okul programının il/ilçesi MMC programıyla uyuşmuyor.'];
            }
            return ['linked'=>true,'ambiguous'=>false,'legacy_program_id'=>(int)$linked->id,'detail'=>'MMC ID '.absint($mmc_program_id).' ↔ Okul Tanıtım legacy #'.(int)$linked->id.' bağlı.'];
        }

        $candidates=self::legacy_candidates($ctx->program);
        if(count($candidates)>1){
            return ['linked'=>false,'ambiguous'=>true,'detail'=>count($candidates).' eski okul programı aynı bölgeyle eşleşiyor; otomatik bağlama için tarih/salon ayrımı gerekiyor.'];
        }
        if(count($candidates)===1){
            return ['linked'=>false,'ambiguous'=>false,'legacy_program_id'=>(int)$candidates[0]->id,'detail'=>'Eşleşebilecek eski okul programı #'.(int)$candidates[0]->id.' bulundu; bağlantı henüz kaydedilmedi.'];
        }
        return ['linked'=>false,'ambiguous'=>false,'detail'=>'Bu MMC programına bağlı eski Okul Tanıtım programı yok; güvenli uyumluluk kaydı oluşturulabilir.'];
    }

    public static function ensure_mmc_bridge($mmc_program_id) {
        global $wpdb;
        $mmc_program_id=absint($mmc_program_id);
        $ctx=self::mmc_program_context($mmc_program_id);
        if(is_wp_error($ctx)) return $ctx;
        $table=self::programs_table();
        $now=current_time('mysql');
        $linked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE mmc_program_id=%d LIMIT 1",$mmc_program_id));

        $payload=[
            'program_adi'=>$ctx->program->program_code.' — '.$ctx->program->province_name.' / '.($ctx->program->district_name ?: 'Genel'),
            'il'=>mad_okul_place_title($ctx->program->province_name),
            'ilce'=>mad_okul_place_title($ctx->program->district_name),
            'etkinlik_tarihi'=>$ctx->program->planned_date ?: null,
            'durum'=>'Aktif',
            'updated_at'=>$now,
        ];
        if($ctx->venue){
            $payload['salon_adi']=(string)$ctx->venue->venue_name;
            $payload['salon_adresi']=(string)$ctx->venue->address;
            $payload['salon_maps_url']=esc_url_raw($ctx->venue->maps_url ?? '');
            // MMC salon tablosu koordinat tutmuyor. Eski ilçe merkezi koordinatını
            // yeni salon için devralmayın; kesin adresle yeniden geocode edilsin.
            if(!$linked || $linked->salon_adi!==$payload['salon_adi'] || $linked->salon_adresi!==$payload['salon_adresi']){
                $payload['latitude']=null;
                $payload['longitude']=null;
            }
            if(is_numeric($ctx->venue->latitude ?? null) && is_numeric($ctx->venue->longitude ?? null)){
                $payload['latitude']=(float)$ctx->venue->latitude;
                $payload['longitude']=(float)$ctx->venue->longitude;
            }
        }else{
            // MMC'de kesin salon yoksa eski ilçe merkezini salon konumu gibi göstermeyin.
            $payload['salon_adi']='';
            $payload['salon_adresi']='';
            $payload['salon_maps_url']='';
            $payload['latitude']=null;
            $payload['longitude']=null;
        }

        if(!$linked){
            $candidates=self::legacy_candidates($ctx->program);
            if(count($candidates)>1){
                return new WP_Error('mad_okul_bridge_ambiguous','Birden fazla eski Okul Tanıtım programı aynı MMC programıyla eşleşiyor. Otomatik onarım durduruldu.');
            }
            if(count($candidates)===1){
                $linked=$candidates[0];
                $payload['mmc_program_id']=$mmc_program_id;
                $wpdb->update($table,$payload,['id'=>(int)$linked->id]);
            }else{
                $payload['mmc_program_id']=$mmc_program_id;
                if(!isset($payload['salon_adi'])) $payload['salon_adi']='';
                if(!isset($payload['salon_adresi'])) $payload['salon_adresi']='';
                $payload['created_at']=$now;
                $wpdb->insert($table,$payload);
                $linked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",(int)$wpdb->insert_id));
            }
        }else{
            $wpdb->update($table,$payload,['id'=>(int)$linked->id]);
        }

        $linked=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE mmc_program_id=%d LIMIT 1",$mmc_program_id));
        if(!$linked) return new WP_Error('mad_okul_bridge_failed','Okul Tanıtım köprüsü oluşturulamadı.');

        $schools=mad_okul_table();
        if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$schools))===$schools){
            $wpdb->query($wpdb->prepare(
                "UPDATE $schools SET mmc_program_id=%d,updated_at=%s WHERE program_id=%d",
                $mmc_program_id,$now,(int)$linked->id
            ));
        }
        return $linked;
    }

    public static function mmc_id_for_legacy($legacy_program_id) {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT mmc_program_id FROM ".self::programs_table()." WHERE id=%d",absint($legacy_program_id)));
    }

    private static function legacy_candidates($mmc_program) {
        global $wpdb;
        $rows=$wpdb->get_results("SELECT * FROM ".self::programs_table()." WHERE mmc_program_id IS NULL OR mmc_program_id=0 ORDER BY id DESC");
        $matches=[];
        $province=mad_okul_place_title($mmc_program->province_name);
        $district=mad_okul_place_title($mmc_program->district_name);
        foreach((array)$rows as $row){
            if(mad_okul_place_title($row->il)!==$province || mad_okul_place_title($row->ilce)!==$district) continue;
            if($mmc_program->planned_date && $row->etkinlik_tarihi && $row->etkinlik_tarihi!==$mmc_program->planned_date) continue;
            $matches[]=$row;
        }
        return $matches;
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::programs_table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            mmc_program_id bigint(20) unsigned DEFAULT NULL,
            program_adi varchar(190) NOT NULL,
            il varchar(100) NOT NULL,
            ilce varchar(100) NOT NULL,
            salon_adi varchar(190) NOT NULL,
            salon_adresi text NOT NULL,
            salon_maps_url text NULL,
            route_program_venue_id bigint(20) unsigned DEFAULT NULL,
            etkinlik_tarihi date NULL,
            latitude decimal(10,7) NULL,
            longitude decimal(10,7) NULL,
            durum varchar(30) NOT NULL DEFAULT 'Aktif',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY mmc_program_id (mmc_program_id),
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
        add_action('admin_post_mad_okul_route_venue_link', [__CLASS__, 'route_venue_link']);
        add_action('admin_post_mad_okul_assign_tasks', [__CLASS__, 'assign_tasks']);
        add_action('admin_post_mad_okul_task_status', [__CLASS__, 'task_status']);
        add_action('admin_post_mad_okul_save_settings', [__CLASS__, 'save_settings']);
        add_action('admin_post_mad_okul_geocode_program', [__CLASS__, 'geocode_program']);
        add_action('admin_post_mad_okul_geocode_schools', [__CLASS__, 'geocode_schools']);
        add_action('admin_post_mad_okul_sort_route', [__CLASS__, 'sort_route']);
        add_action('admin_post_mad_okul_driving_route', [__CLASS__, 'driving_route']);
    }

    public static function menus() {
        add_submenu_page('mad-okul', 'Program ve Salonlar', 'Program ve Salonlar', 'manage_options', 'mad-okul-programs', [__CLASS__, 'programs_page']);
        add_submenu_page('mad-okul', 'Görev Dağıtımı', 'Görev Dağıtımı', 'manage_options', 'mad-okul-assign', [__CLASS__, 'assign_page']);
        add_submenu_page('mad-okul', 'Harita Ayarları', 'Harita Ayarları', 'manage_options', 'mad-okul-settings', [__CLASS__, 'settings_page']);
        add_submenu_page('mad-okul', 'Rota Planı', 'Rota Planı / PDF', 'manage_options', 'mad-okul-route-plan', [__CLASS__, 'route_plan_page']);
        add_menu_page('Tanıtım Görevlerim', 'Görevlerim', 'mad_okul_field_access', 'mad-okul-my-tasks', [__CLASS__, 'my_tasks_page'], 'dashicons-location', 28);
    }

    private static function programs() {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::programs_table() . ' ORDER BY etkinlik_tarihi DESC, id DESC');
    }

    public static function programs_page() {
        if (!current_user_can('manage_options')) return;
        $mmc_program_id=absint($_GET['mmc_program_id'] ?? 0);
        $mmc_program=$mmc_program_id ? self::ensure_mmc_bridge($mmc_program_id) : null;
        $mmc_ctx=$mmc_program_id ? self::mmc_program_context($mmc_program_id) : null;
        global $wpdb;
        $pv_table=$wpdb->prefix.'mmc_program_venues';
        $venue_links=$mmc_program_id && class_exists('MMC_Venue_Service') ? $wpdb->get_col($wpdb->prepare("SELECT id FROM $pv_table WHERE program_id=%d ORDER BY id DESC",$mmc_program_id)) : [];
        $programs = self::programs();
        ?>
        <div class="wrap mad-okul-wrap">
          <h1>Program ve Salonlar</h1>
          <p class="description">Gösteri salonu rota planının başlangıç noktasıdır.</p>
          <?php if($mmc_program_id): ?>
            <?php if(is_wp_error($mmc_program)): ?><div class="notice notice-error inline"><p><?php echo esc_html($mmc_program->get_error_message()); ?></p></div>
            <?php else: ?><div class="notice notice-info inline"><p><strong>Aktif MMC programı:</strong> <?php echo esc_html($mmc_program->program_adi); ?>. Salon ve adres MMC üzerinden yönetilir. <a href="<?php echo esc_url(add_query_arg(['page'=>'mmc-venue-flow','program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">Salon bağlantısını aç</a></p></div>
            <?php if(isset($_GET['venue_linked'])): ?><div class="notice notice-success inline"><p>Rota başlangıç salonu programa bağlandı.</p></div><?php endif; ?>
            <h2>Rota başlangıç salonu</h2>
            <p>Programa bağlı salonu seçin. Bu bağlantı yalnızca Okul Rotası için kullanılır; MMC salon tahsisini onaylamaz.</p>
            <?php if($mmc_ctx && !is_wp_error($mmc_ctx) && $mmc_ctx->venue): ?><p><strong>Mevcut başlangıç:</strong> <?php echo esc_html($mmc_ctx->venue->venue_name.' — '.$mmc_ctx->venue->address); ?><?php echo $mmc_ctx->manual ? ' (rota bağlantısı)' : ' (onaylı salon)'; ?></p><?php endif; ?>
            <?php if($venue_links): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="mad_okul_route_venue_link"><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>">
              <?php wp_nonce_field('mad_okul_route_venue_link_'.$mmc_program_id); ?>
              <label for="route-program-venue">Programa bağlı salon</label>
              <select id="route-program-venue" name="program_venue_id" required><option value="">Salon seçin</option>
              <?php foreach($venue_links as $venue_link_id): $candidate=MMC_Venue_Service::get_program_venue($venue_link_id); if(!self::venue_matches_program($candidate,$mmc_ctx->program)) continue; ?>
                <option value="<?php echo (int)$venue_link_id; ?>" <?php selected((int)$mmc_program->route_program_venue_id,(int)$venue_link_id); ?>><?php echo esc_html($candidate->venue_name.' — '.$candidate->address.' ('.$candidate->allocation_status.')'); ?></option>
              <?php endforeach; ?></select> <button class="button button-primary">Rotaya Bağla</button>
            </form><?php else: ?><div class="notice notice-warning inline"><p>Bu programa bağlı salon bulunamadı. Önce MMC Salon ekranından salonu programa ekleyin.</p></div><?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
          <h2>Bağımsız okul programı oluştur</h2>
          <p class="description">MMC programı için burada tekrar kayıt açmayın; yukarıdaki rota salonu seçimini kullanın.</p>
          <?php if (!empty($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p>Program kaydedildi.</p></div><?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-program-form">
            <input type="hidden" name="action" value="mad_okul_save_program">
            <?php wp_nonce_field('mad_okul_save_program'); ?>
            <label class="mad-field mad-span-2">Program adı<input required name="program_adi" placeholder="Örn. Mamak – 10 Ekim 2026"></label>
            <label class="mad-field">İl<input required name="il"></label><label class="mad-field">İlçe<input required name="ilce"></label><label class="mad-field">Tarih<input type="date" name="etkinlik_tarihi"></label>
            <label class="mad-field mad-span-2">Salon adı<input required name="salon_adi"></label>
            <label class="mad-field mad-span-2">Salon adresi<textarea required rows="3" name="salon_adresi"></textarea></label>
            <label class="mad-field">Enlem <span class="description">İsteğe bağlı</span><input name="latitude" inputmode="decimal"></label><label class="mad-field">Boylam <span class="description">İsteğe bağlı</span><input name="longitude" inputmode="decimal"></label>
            <div class="mad-form-actions"><button class="button button-primary button-large">Programı Kaydet</button></div>
          </form>
          <h2>Kayıtlı Programlar</h2>
          <table class="widefat striped"><thead><tr><th>Program</th><th>İl / İlçe</th><th>Salon</th><th>Tarih</th><th>Koordinat</th><th>Harita</th></tr></thead><tbody>
          <?php foreach ($programs as $p): ?>
            <tr><td><?php echo esc_html($p->program_adi); ?></td><td><?php echo esc_html($p->il.' / '.$p->ilce); ?></td><td><?php echo $p->salon_adi ? esc_html($p->salon_adi) : 'Rota salonu bekleniyor'; ?><br><small><?php echo esc_html($p->salon_adresi); ?></small></td><td><?php echo esc_html($p->etkinlik_tarihi ?: '-'); ?></td><td><?php if($p->latitude && $p->longitude): echo esc_html($p->latitude.', '.$p->longitude); elseif($p->salon_adi && $p->salon_adresi): ?><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mad_okul_geocode_program&program_id='.(int)$p->id),'mad_okul_geocode_program_'.$p->id)); ?>">Koordinat Bul</a><?php else: ?>Salon bekleniyor<?php endif; ?></td><td><?php if($p->salon_adi): ?><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($p->salon_maps_url ?: 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($p->salon_adi.', '.$p->salon_adresi)); ?>">Maps</a><?php else: ?>—<?php endif; ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
        <?php
    }

    public static function route_venue_link(){
        if(!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        $program_id=absint($_POST['mmc_program_id'] ?? 0);
        check_admin_referer('mad_okul_route_venue_link_'.$program_id);
        $ctx=self::mmc_program_context($program_id);
        $venue_id=absint($_POST['program_venue_id'] ?? 0);
        $venue=$venue_id && class_exists('MMC_Venue_Service') ? MMC_Venue_Service::get_program_venue($venue_id) : null;
        if(is_wp_error($ctx) || !self::venue_matches_program($venue,$ctx->program ?? null)) wp_die('Salon bu MMC programına veya il/ilçesine bağlı değil.');
        $bridge=self::ensure_mmc_bridge($program_id);
        if(is_wp_error($bridge)) wp_die(esc_html($bridge->get_error_message()));
        global $wpdb;
        if(false===$wpdb->update(self::programs_table(),['route_program_venue_id'=>$venue_id],['id'=>(int)$bridge->id])) wp_die('Salon bağlantısı kaydedilemedi.');
        self::ensure_mmc_bridge($program_id);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-programs','mmc_program_id'=>$program_id,'venue_linked'=>1],admin_url('admin.php')));
        exit;
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
        $mmc_program_id = absint($_GET['mmc_program_id'] ?? 0);
        $program_id = absint($_GET['program_id'] ?? 0);
        if($mmc_program_id){
            $bridge=self::ensure_mmc_bridge($mmc_program_id);
            if(!is_wp_error($bridge)) $program_id=(int)$bridge->id;
        }
        $program = $program_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d', $program_id)) : null;
        $users = get_users(['role__in'=>['mad_tanitim_elemani','administrator'],'orderby'=>'display_name']);
        $schools = $program ? $wpdb->get_results($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s ORDER BY CASE WHEN route_order>0 THEN 0 ELSE 1 END, route_order, kurum_adi', $program->il, $program->ilce)) : [];
        ?>
        <div class="wrap mad-okul-wrap"><h1>Görev Dağıtımı</h1>
          <?php if (!empty($_GET['assigned'])): ?><div class="notice notice-success is-dismissible"><p><?php echo absint($_GET['assigned']); ?> okul personele atandı.</p></div><?php endif; ?>
          <form method="get" class="mad-filter"><input type="hidden" name="page" value="mad-okul-assign"><?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><strong>MMC Program ID <?php echo (int)$mmc_program_id; ?> · <?php echo esc_html($program ? $program->program_adi : ''); ?></strong><?php else: ?><select name="program_id" required><option value="">Program seçin</option><?php foreach($programs as $p): ?><option value="<?php echo (int)$p->id; ?>" <?php selected($program_id,$p->id); ?>><?php echo esc_html($p->program_adi); ?></option><?php endforeach; ?></select><button class="button">Okulları Getir</button><?php endif; ?></form>
          <?php if ($program): ?>
          <p><strong><?php echo esc_html($program->salon_adi ?: 'Rota salonu bekleniyor'); ?></strong> başlangıç noktası · <?php echo count($schools); ?> kurum</p>
          <?php if($mmc_program_id): ?><div class="notice notice-info inline"><p>Bu form Okul Tanıtım rota gruplarını ve PDF personelini düzenler. Programın saha görevi ve ziyaret durumu <a href="<?php echo esc_url(add_query_arg(['page'=>'mmc-field','program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">MMC Okul / Saha</a> ekranından yönetilir.</p></div><?php endif; ?>
          <?php if(!$program->salon_adi || !$program->salon_adresi): ?><div class="notice notice-warning inline"><p>Rota için önce programın başlangıç salonunu bağlayın. <?php if($mmc_program_id): ?><a href="<?php echo esc_url(add_query_arg(['page'=>'mad-okul-programs','mmc_program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">Program ve Salonlar</a><?php endif; ?></p></div><?php endif; ?>
          <p>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mad_okul_geocode_schools&program_id='.(int)$program->id),'mad_okul_geocode_schools_'.$program->id)); ?>">Eksik Okul Koordinatlarını Bul</a>
            <?php if($program->latitude && $program->longitude): ?>
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mad_okul_sort_route&program_id='.(int)$program->id),'mad_okul_sort_route_'.$program->id)); ?>">Salondan Yakından Uzağa Sırala</a>
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mad_okul_driving_route&program_id='.(int)$program->id),'mad_okul_driving_route_'.$program->id)); ?>">Gerçek Sürüş Mesafesine Göre Sırala</a>
            <?php endif; ?>
            <a class="button" href="<?php echo esc_url(add_query_arg(array_filter(['page'=>'mad-okul-route-plan','program_id'=>(int)$program->id,'mmc_program_id'=>$mmc_program_id]),admin_url('admin.php'))); ?>">Rota Planı / PDF</a>
          </p>
          <?php if(isset($_GET['geo'])): ?><div class="notice notice-success inline"><p><?php echo absint($_GET['geo']); ?> okul koordinatlandırıldı. Kalan: <?php echo absint($_GET['remaining'] ?? 0); ?>.</p></div><?php endif; ?>
          <?php if(isset($_GET['sorted'])): ?><div class="notice notice-success inline"><p><?php echo absint($_GET['sorted']); ?> okul salona kuş uçuşu mesafesine göre sıralandı.</p></div><?php endif; ?>
          <?php if(isset($_GET['driving'])): ?><div class="notice notice-success inline"><p><?php echo absint($_GET['driving']); ?> okul gerçek sürüş mesafesine göre sıralandı.</p></div><?php endif; ?>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mad_okul_assign_tasks"><input type="hidden" name="program_id" value="<?php echo (int)$program->id; ?>"><?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?><?php wp_nonce_field('mad_okul_assign_tasks'); ?>
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
        $uid = absint($_POST['assigned_user_id'] ?? 0); $pid = absint($_POST['program_id'] ?? 0); $mmc_program_id=absint($_POST['mmc_program_id'] ?? 0);
        $program=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d',$pid));
        if(!$program || ($mmc_program_id && (int)$program->mmc_program_id!==$mmc_program_id)) wp_die('Program eşleşmesi doğrulanamadı.');
        if(!$uid || !get_user_by('id',$uid)) wp_die('Geçerli bir personel seçin.');
        $ids=array_values(array_unique($ids));
        if($ids){
            $in=implode(',', $ids);
            $ids=array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT id FROM '.mad_okul_table()." WHERE id IN ($in) AND il=%s AND ilce=%s",$program->il,$program->ilce)));
        }
        if(!$ids) wp_die('Programın ilçesinden en az bir okul seçin.');
        $group = sanitize_text_field($_POST['route_group'] ?? 'A');
        foreach ($ids as $order=>$id) $wpdb->update(mad_okul_table(), ['program_id'=>$pid,'mmc_program_id'=>$mmc_program_id ?: null,'assigned_user_id'=>$uid,'route_group'=>$group,'route_order'=>$order+1,'durum'=>'Atandı','updated_at'=>current_time('mysql')], ['id'=>$id]);
        $args=['page'=>'mad-okul-assign','program_id'=>$pid,'assigned'=>count($ids)]; if($mmc_program_id)$args['mmc_program_id']=$mmc_program_id;
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php'))); exit;
    }

    private static function directions_url($destination) {
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination) . '&travelmode=driving';
    }

    private static function school_address($school) {
        return trim($school->kurum_adi.', '.$school->adres.', '.$school->ilce.', '.$school->il, ', ');
    }

    private static function multi_stop_url($origin, $schools) {
        if (!$schools) return '';
        $points=array_map([__CLASS__,'school_address'],$schools);
        $destination=array_pop($points);
        $url='https://www.google.com/maps/dir/?api=1&origin='.rawurlencode($origin).'&destination='.rawurlencode($destination).'&travelmode=driving';
        if ($points) $url.='&waypoints='.implode('%7C',array_map('rawurlencode',$points));
        return $url;
    }

    private static function grouped_route_links($rows) {
        $groups=[];
        foreach($rows as $r) {
            $key=(int)$r->program_id.'|'.($r->route_group ?: 'A').'|'.(int)$r->assigned_user_id;
            if(!isset($groups[$key])) $groups[$key]=[];
            $groups[$key][]=$r;
        }
        $links=[];
        foreach($groups as $key=>$items) {
            $origin=trim($items[0]->salon_adi.', '.$items[0]->salon_adresi, ', ');
            if (!$items[0]->salon_adi || !$items[0]->salon_adresi) continue;
            foreach(array_chunk($items,8) as $i=>$chunk) {
                $url=self::multi_stop_url($origin,$chunk);
                $links[]=['key'=>$key,'part'=>$i+1,'url'=>$url,'count'=>count($chunk),'row'=>$chunk[0]];
                $origin=self::school_address(end($chunk));
            }
        }
        return $links;
    }

    private static function geocode_address($address) {
        $key = trim((string)get_option('mad_okul_google_maps_api_key'));
        if (!$key || !$address) return new WP_Error('missing_key', 'Google Maps API anahtarı veya adres eksik.');
        $url = add_query_arg(['address'=>$address,'key'=>$key,'region'=>'tr','language'=>'tr'], 'https://maps.googleapis.com/maps/api/geocode/json');
        $response = wp_remote_get($url, ['timeout'=>20]);
        if (is_wp_error($response)) return $response;
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]['geometry']['location'])) return new WP_Error('geocode_failed', sanitize_text_field($data['status'] ?? 'Adres bulunamadı'));
        return $data['results'][0]['geometry']['location'];
    }

    public static function geocode_program() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        $id=absint($_GET['program_id'] ?? 0); check_admin_referer('mad_okul_geocode_program_'.$id); global $wpdb;
        $p=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d',$id));
        if (!$p) wp_die('Program bulunamadı');
        if (!$p->salon_adi || !$p->salon_adresi) wp_die('Önce MMC programına kesin salon ve adres bağlayın. İlçe merkezini salon koordinatı olarak kaydedemeyiz.');
        $loc=self::geocode_address($p->salon_adi.', '.$p->salon_adresi.', '.$p->ilce.', '.$p->il.', Türkiye');
        if (is_wp_error($loc)) wp_die(esc_html($loc->get_error_message()));
        $wpdb->update(self::programs_table(),['latitude'=>(float)$loc['lat'],'longitude'=>(float)$loc['lng'],'updated_at'=>current_time('mysql')],['id'=>$id]);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-programs','geocoded'=>1],admin_url('admin.php'))); exit;
    }

    public static function geocode_schools() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        $pid=absint($_GET['program_id'] ?? 0); check_admin_referer('mad_okul_geocode_schools_'.$pid); global $wpdb;
        $p=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d',$pid));
        if (!$p) wp_die('Program bulunamadı');
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s AND adres<>%s AND (latitude IS NULL OR longitude IS NULL) ORDER BY id LIMIT 20',$p->il,$p->ilce,''));
        $done=0;
        foreach($rows as $s) {
            $loc=self::geocode_address($s->kurum_adi.', '.$s->adres.', '.$s->ilce.', '.$s->il.', Türkiye');
            if (is_wp_error($loc)) continue;
            $wpdb->update(mad_okul_table(),['latitude'=>(float)$loc['lat'],'longitude'=>(float)$loc['lng'],'updated_at'=>current_time('mysql')],['id'=>$s->id]); $done++;
        }
        $remaining=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s AND adres<>%s AND (latitude IS NULL OR longitude IS NULL)',$p->il,$p->ilce,''));
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-assign','program_id'=>$pid,'geo'=>$done,'remaining'=>$remaining],admin_url('admin.php'))); exit;
    }

    private static function distance_km($lat1,$lon1,$lat2,$lon2) {
        $r=6371; $dlat=deg2rad($lat2-$lat1); $dlon=deg2rad($lon2-$lon1);
        $a=sin($dlat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dlon/2)**2;
        return $r*2*atan2(sqrt($a),sqrt(1-$a));
    }

    public static function sort_route() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        $pid=absint($_GET['program_id'] ?? 0); check_admin_referer('mad_okul_sort_route_'.$pid); global $wpdb;
        $p=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d',$pid));
        if (!$p || !$p->latitude || !$p->longitude) wp_die('Önce program salonunun koordinatını bulun.');
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s AND latitude IS NOT NULL AND longitude IS NOT NULL',$p->il,$p->ilce));
        usort($rows,function($a,$b) use($p){ return self::distance_km($p->latitude,$p->longitude,$a->latitude,$a->longitude) <=> self::distance_km($p->latitude,$p->longitude,$b->latitude,$b->longitude); });
        foreach($rows as $i=>$s) $wpdb->update(mad_okul_table(),['route_order'=>$i+1,'updated_at'=>current_time('mysql')],['id'=>$s->id]);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-assign','program_id'=>$pid,'sorted'=>count($rows)],admin_url('admin.php'))); exit;
    }

    public static function driving_route() {
        if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
        $pid=absint($_GET['program_id'] ?? 0); check_admin_referer('mad_okul_driving_route_'.$pid); global $wpdb;
        $key=trim((string)get_option('mad_okul_google_maps_api_key'));
        if (!$key) wp_die('Önce Harita Ayarları bölümüne Google Maps API anahtarı girin.');
        $p=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d',$pid));
        if (!$p || !$p->latitude || !$p->longitude) wp_die('Önce program salonunun koordinatını bulun.');
        $schools=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY id',$p->il,$p->ilce));
        $updated=0;
        foreach(array_chunk($schools,25) as $chunk) {
            $destinations=[];
            foreach($chunk as $s) $destinations[]=['waypoint'=>['location'=>['latLng'=>['latitude'=>(float)$s->latitude,'longitude'=>(float)$s->longitude]]]];
            $body=[
                'origins'=>[['waypoint'=>['location'=>['latLng'=>['latitude'=>(float)$p->latitude,'longitude'=>(float)$p->longitude]]]]],
                'destinations'=>$destinations,
                'travelMode'=>'DRIVE',
                'routingPreference'=>'TRAFFIC_AWARE',
            ];
            $response=wp_remote_post('https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix',[
                'timeout'=>45,
                'headers'=>['Content-Type'=>'application/json','X-Goog-Api-Key'=>$key,'X-Goog-FieldMask'=>'originIndex,destinationIndex,distanceMeters,duration,status,condition'],
                'body'=>wp_json_encode($body),
            ]);
            if (is_wp_error($response)) wp_die(esc_html($response->get_error_message()));
            $code=wp_remote_retrieve_response_code($response); $data=json_decode(wp_remote_retrieve_body($response),true);
            if ($code<200 || $code>=300 || !is_array($data)) wp_die('Google Routes API yanıtı alınamadı. API etkinleştirme ve anahtar kısıtlarını kontrol edin.');
            foreach($data as $item) {
                if (!isset($item['destinationIndex'])) continue;
                $index=absint($item['destinationIndex']);
                if (!isset($chunk[$index]) || ($item['condition'] ?? '')!=='ROUTE_EXISTS') continue;
                $duration=(int)round((float)rtrim((string)($item['duration'] ?? '0'),'s'));
                $wpdb->update(mad_okul_table(),['route_distance_m'=>absint($item['distanceMeters'] ?? 0),'route_duration_s'=>$duration,'updated_at'=>current_time('mysql')],['id'=>$chunk[$index]->id]); $updated++;
            }
        }
        $ranked=$wpdb->get_results($wpdb->prepare('SELECT id FROM '.mad_okul_table().' WHERE il=%s AND ilce=%s AND route_distance_m>0 ORDER BY route_distance_m,kurum_adi',$p->il,$p->ilce));
        foreach($ranked as $i=>$s) $wpdb->update(mad_okul_table(),['route_order'=>$i+1],['id'=>$s->id]);
        wp_safe_redirect(add_query_arg(['page'=>'mad-okul-assign','program_id'=>$pid,'driving'=>$updated],admin_url('admin.php'))); exit;
    }

    public static function route_plan_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb; $pid=absint($_GET['program_id'] ?? 0);
        $mmc_program_id=absint($_GET['mmc_program_id'] ?? 0);
        if($mmc_program_id){
            $linked=self::ensure_mmc_bridge($mmc_program_id);
            if(!is_wp_error($linked)) $pid=(int)$linked->id;
        }
        $programs=self::programs();
        $p=$pid ? $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::programs_table().' WHERE id=%d',$pid)) : null;
        $rows=$p ? $wpdb->get_results($wpdb->prepare('SELECT s.*,u.display_name,p.program_adi,p.salon_adi,p.salon_adresi,p.etkinlik_tarihi FROM '.mad_okul_table().' s LEFT JOIN '.$wpdb->users.' u ON u.ID=s.assigned_user_id LEFT JOIN '.self::programs_table().' p ON p.id=s.program_id WHERE s.program_id=%d ORDER BY s.assigned_user_id,s.route_group,s.route_order,s.kurum_adi',$pid)) : [];
        $route_links=self::grouped_route_links($rows);
        ?><div class="wrap mad-okul-wrap mad-route-print"><div class="mad-no-print"><h1>Rota Planı / PDF</h1><form method="get"><input type="hidden" name="page" value="mad-okul-route-plan"><select name="program_id" required><option value="">Program seçin</option><?php foreach($programs as $x): ?><option value="<?php echo (int)$x->id; ?>" <?php selected($pid,$x->id); ?>><?php echo esc_html($x->program_adi); ?></option><?php endforeach; ?></select> <button class="button">Getir</button><?php if($p): ?> <button type="button" class="button button-primary" onclick="window.print()">Yazdır / PDF Kaydet</button><?php endif; ?></form></div>
        <?php if($p && (!$p->salon_adi || !$p->salon_adresi)): ?><div class="mad-no-print notice notice-warning inline"><p>Rota başlangıç salonu bağlı değil. <?php if($mmc_program_id): ?><a href="<?php echo esc_url(add_query_arg(['page'=>'mad-okul-programs','mmc_program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">Program ve Salonlar ekranından bağlayın</a><?php endif; ?></p></div><?php endif; ?>
        <?php if($p): ?><h1><?php echo esc_html($p->program_adi); ?> — Okul Tanıtım Rota Planı</h1><p><strong>Salon:</strong> <?php echo esc_html($p->salon_adi); ?><br><strong>Adres:</strong> <?php echo esc_html($p->salon_adresi); ?><br><strong>Tarih:</strong> <?php echo esc_html($p->etkinlik_tarihi ?: '-'); ?></p>
        <div class="mad-no-print"><h2>Personele Gönderilecek Rotalar</h2><?php foreach($route_links as $link): $label=($link['row']->display_name ?: 'Atanmamış').' · Grup '.($link['row']->route_group ?: 'A').' · Bölüm '.$link['part']; $message=$p->program_adi.' — '.$label.' ('.$link['count'].' okul) '.$link['url']; ?><p><a class="button button-primary" target="_blank" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($label); ?> Maps</a> <a class="button" target="_blank" href="<?php echo esc_url('https://wa.me/?text='.rawurlencode($message)); ?>">WhatsApp ile Paylaş</a></p><?php endforeach; ?></div>
        <table class="widefat striped"><thead><tr><th>Sıra</th><th>Personel</th><th>Okul ve adres</th><th>Mesafe</th><th>Süre</th><th>Durum</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo esc_html(($r->route_group ?: 'A').'-'.($r->route_order ?: '-')); ?></td><td><?php echo esc_html($r->display_name ?: '-'); ?></td><td><strong><?php echo esc_html($r->kurum_adi); ?></strong><br><?php echo esc_html($r->adres.', '.$r->ilce.'/'.$r->il); ?></td><td><?php echo $r->route_distance_m ? esc_html(number_format_i18n($r->route_distance_m/1000,1).' km') : '-'; ?></td><td><?php echo $r->route_duration_s ? esc_html(round($r->route_duration_s/60).' dk') : '-'; ?></td><td><?php echo esc_html($r->durum); ?></td></tr><?php endforeach; ?></tbody></table>
        <p><small>Oluşturulma: <?php echo esc_html(current_time('d.m.Y H:i')); ?></small></p><?php endif; ?></div>
        <style>@media print{#adminmenumain,#wpadminbar,#wpfooter,.notice,.mad-no-print{display:none!important}#wpcontent{margin:0!important}.mad-route-print{margin:12mm!important}.mad-route-print table{font-size:10px}.mad-route-print h1{font-size:20px}}</style><?php
    }

    public static function my_tasks_page() {
        if (!current_user_can('mad_okul_field_access')) return;
        global $wpdb; $uid = get_current_user_id();
        $rows = $wpdb->get_results($wpdb->prepare('SELECT s.*,p.program_adi,p.salon_adi,p.salon_adresi,p.etkinlik_tarihi FROM '.mad_okul_table().' s LEFT JOIN '.self::programs_table().' p ON p.id=s.program_id WHERE s.assigned_user_id=%d ORDER BY p.etkinlik_tarihi DESC,s.route_group,s.route_order', $uid));
        $mmc_tasks=[];
        if(class_exists('MMC_Field_Service')){
            $targets=$wpdb->prefix.'mmc_program_target_schools';
            $schools=$wpdb->prefix.'mmc_schools';
            $programs=$wpdb->prefix.'mmc_programs';
            $mmc_tasks=$wpdb->get_results($wpdb->prepare(
                "SELECT t.id,t.program_id,t.status,t.assigned_name,s.school_name,s.address,s.district_name,s.province_name,p.program_code,p.planned_date
                 FROM $targets t INNER JOIN $schools s ON s.id=t.school_id INNER JOIN $programs p ON p.id=t.program_id
                 WHERE t.assigned_user_id=%d AND t.status<>'skipped' ORDER BY p.planned_date DESC,s.school_name LIMIT 500",$uid));
        }
        $route_links=self::grouped_route_links($rows);
        ?><div class="wrap mad-okul-wrap"><h1>Tanıtım Görevlerim</h1><p class="description">Görev sırasına göre ilerleyin ve ziyaret sonucunu kaydedin.</p>
        <?php if($mmc_tasks): ?><h2>MMC Program Saha Görevlerim (<?php echo count($mmc_tasks); ?>)</h2><p>Ziyaret ve tabela fotoğrafı, yöneticinizin verdiği süreli MMC saha portalı bağlantısından kaydedilir. Bu liste size atanmış program hedeflerini gösterir.</p>
        <table class="widefat striped"><thead><tr><th>Program</th><th>Okul</th><th>Durum</th><th>Yol Tarifi</th></tr></thead><tbody>
        <?php foreach($mmc_tasks as $task): $destination=trim($task->school_name.', '.$task->address.', '.$task->district_name.', '.$task->province_name,', '); ?>
        <tr><td><?php echo esc_html($task->program_code); ?><br><small><?php echo esc_html($task->planned_date); ?></small></td><td><strong><?php echo esc_html($task->school_name); ?></strong><br><?php echo esc_html($task->address); ?></td><td><?php echo esc_html(MMC_Field_Service::target_statuses()[$task->status] ?? $task->status); ?></td><td><a class="button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url(self::directions_url($destination)); ?>">Yol Tarifi</a></td></tr>
        <?php endforeach; ?></tbody></table><?php endif; ?>
        <?php if($route_links): ?><div class="mad-upload-box"><h2>Hazır Google Maps Rotalarım</h2><?php foreach($route_links as $link): ?><a class="button button-primary" target="_blank" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html(($link['row']->program_adi ?: 'Program').' · Grup '.($link['row']->route_group ?: 'A').' · Bölüm '.$link['part'].' ('.$link['count'].' okul)'); ?></a> <?php endforeach; ?></div><?php endif; ?>
        <?php if($rows): ?><h2>Okul Tanıtım Rota Görevlerim</h2><table class="widefat striped"><thead><tr><th>Rota</th><th>Okul</th><th>Program</th><th>İşlem</th></tr></thead><tbody><?php foreach($rows as $r): $dest=$r->kurum_adi.', '.$r->adres.', '.$r->ilce.', '.$r->il; ?><tr><td><?php echo esc_html($r->route_group.'-'.$r->route_order); ?></td><td><strong><?php echo esc_html($r->kurum_adi); ?></strong><br><?php echo esc_html($r->adres); ?><br><span class="mad-status"><?php echo esc_html($r->durum); ?></span></td><td><?php echo esc_html($r->program_adi ?: '-'); ?><br><small><?php echo esc_html($r->salon_adi ?: ''); ?></small></td><td><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url(self::directions_url($dest)); ?>">Yol Tarifi</a><?php if($r->mmc_program_id): ?><p>MMC ziyareti için süreli saha portalı bağlantısını kullanın.</p><?php else: ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px"><input type="hidden" name="action" value="mad_okul_task_status"><input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"><?php wp_nonce_field('mad_okul_task_status_'.$r->id); ?><select name="durum"><option>Ziyaret Edildi</option><option>Afiş Bırakıldı</option><option>Görüşüldü</option><option>Tekrar Gidilecek</option><option>Olumsuz</option></select><input name="notlar" placeholder="Kısa not"><button class="button">Kaydet</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div><?php
    }

    public static function task_status() {
        if (!current_user_can('mad_okul_field_access')) wp_die('Yetkisiz işlem');
        $id=absint($_POST['id'] ?? 0); check_admin_referer('mad_okul_task_status_'.$id); global $wpdb;
        $owner=(int)$wpdb->get_var($wpdb->prepare('SELECT assigned_user_id FROM '.mad_okul_table().' WHERE id=%d',$id));
        if ($owner!==get_current_user_id() && !current_user_can('manage_options')) wp_die('Bu görev size ait değil');
        $mmc_program_id=(int)$wpdb->get_var($wpdb->prepare('SELECT mmc_program_id FROM '.mad_okul_table().' WHERE id=%d',$id));
        if($mmc_program_id) wp_die('MMC programı ziyaretini süreli Saha Portalı bağlantısından tabela fotoğrafıyla kaydedin.');
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
