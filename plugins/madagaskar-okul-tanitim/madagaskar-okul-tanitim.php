<?php
/**
 * Plugin Name: Madagaskar Okul Tanıtım Yönetimi
 * Description: Madagaskar Sirki okul tanıtım listelerini tek merkezde yönetir. MEBBİS XLS/CSV aktarımı, ziyaret durumu, personel/etkinlik/not takibi ve Google Maps rota bağlantıları sağlar.
 * Version: 1.0.0
 * Author: Dünya Organizasyon
 * Text Domain: madagaskar-okul-tanitim
 */

if (!defined('ABSPATH')) exit;

define('MAD_OKUL_VERSION', '1.0.0');
define('MAD_OKUL_FILE', __FILE__);
define('MAD_OKUL_DIR', plugin_dir_path(__FILE__));

function mad_okul_table() {
    global $wpdb;
    return $wpdb->prefix . 'mad_okul_tanitim';
}

function mad_okul_norm($s) {
    $s = trim((string)$s);
    if (function_exists('mb_strtoupper')) $s = mb_strtoupper($s, 'UTF-8');
    else $s = strtoupper($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

function mad_okul_hash($il, $ilce, $kurum, $adres) {
    return md5(mad_okul_norm($il).'|'.mad_okul_norm($ilce).'|'.mad_okul_norm($kurum).'|'.mad_okul_norm($adres));
}

function mad_okul_maps_url($r) {
    $q = trim($r->kurum_adi . ', ' . $r->adres . ', ' . $r->ilce . ', ' . $r->il);
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

function mad_okul_create_table() {
    global $wpdb;
    $table = mad_okul_table();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        il varchar(100) NOT NULL,
        ilce varchar(100) NOT NULL,
        kurum_adi text NOT NULL,
        adres text NOT NULL,
        durum varchar(50) NOT NULL DEFAULT 'Bekliyor',
        personel varchar(190) NOT NULL DEFAULT '',
        etkinlik varchar(190) NOT NULL DEFAULT '',
        son_ziyaret date NULL,
        notlar text NULL,
        dedupe_hash char(32) NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY dedupe_hash (dedupe_hash),
        KEY il_ilce (il(40), ilce(40)),
        KEY durum (durum)
    ) $charset;";
    dbDelta($sql);
}

function mad_okul_insert_school($il, $ilce, $kurum, $adres) {
    global $wpdb;
    $il = sanitize_text_field($il);
    $ilce = sanitize_text_field($ilce);
    $kurum = sanitize_text_field($kurum);
    $adres = sanitize_textarea_field($adres);
    if (!$il || !$ilce || !$kurum) return false;

    $hash = mad_okul_hash($il, $ilce, $kurum, $adres);
    $now = current_time('mysql');
    $sql = $wpdb->prepare(
        "INSERT IGNORE INTO ".mad_okul_table()."
        (il, ilce, kurum_adi, adres, durum, personel, etkinlik, son_ziyaret, notlar, dedupe_hash, created_at, updated_at)
        VALUES (%s,%s,%s,%s,'Bekliyor','','',NULL,'',%s,%s,%s)",
        $il, $ilce, $kurum, $adres, $hash, $now, $now
    );
    return $wpdb->query($sql);
}

function mad_okul_seed() {
    if (get_option('mad_okul_seeded_version') === MAD_OKUL_VERSION) return;
    $file = MAD_OKUL_DIR . 'data/schools.csv';
    if (!file_exists($file)) return;

    $fh = fopen($file, 'r');
    if (!$fh) return;
    $header = fgetcsv($fh);
    $count = 0;
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) < 4) continue;
        if (mad_okul_insert_school($row[0], $row[1], $row[2], $row[3]) !== false) $count++;
    }
    fclose($fh);
    update_option('mad_okul_seeded_version', MAD_OKUL_VERSION);
    update_option('mad_okul_seed_count', $count);
}

function mad_okul_activate() {
    mad_okul_create_table();
    mad_okul_seed();
}
register_activation_hook(__FILE__, 'mad_okul_activate');

add_action('plugins_loaded', function() {
    if (get_option('mad_okul_db_version') !== MAD_OKUL_VERSION) {
        mad_okul_create_table();
        update_option('mad_okul_db_version', MAD_OKUL_VERSION);
    }
});

add_action('admin_menu', function() {
    add_menu_page(
        'Okul Tanıtım Yönetimi',
        'Okul Tanıtım',
        'manage_options',
        'mad-okul',
        'mad_okul_dashboard',
        'dashicons-location-alt',
        27
    );
    add_submenu_page('mad-okul','Tüm Okullar','Tüm Okullar','manage_options','mad-okul-list','mad_okul_list_page');
    add_submenu_page('mad-okul','MEBBİS İçe Aktar','MEBBİS İçe Aktar','manage_options','mad-okul-import','mad_okul_import_page');
    add_submenu_page('mad-okul','Google Maps Rota','Google Maps Rota','manage_options','mad-okul-route','mad_okul_route_page');
});

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'mad-okul') === false) return;
    wp_enqueue_style('mad-okul-admin', plugins_url('assets/admin.css', __FILE__), [], MAD_OKUL_VERSION);
});

function mad_okul_filter_options() {
    global $wpdb;
    $table = mad_okul_table();
    $ils = $wpdb->get_col("SELECT DISTINCT il FROM $table ORDER BY il");
    $ilceler = $wpdb->get_col("SELECT DISTINCT ilce FROM $table ORDER BY ilce");
    return [$ils, $ilceler];
}

function mad_okul_dashboard() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = mad_okul_table();
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
    $visited = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE durum=%s",'Ziyaret Edildi'));
    $pending = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE durum=%s",'Bekliyor'));
    $districts = (int)$wpdb->get_var("SELECT COUNT(DISTINCT CONCAT(il,'|',ilce)) FROM $table");

    $summary = $wpdb->get_results("SELECT il, ilce, COUNT(*) toplam,
        SUM(durum='Ziyaret Edildi') ziyaret,
        SUM(durum='Bekliyor') bekliyor
        FROM $table GROUP BY il, ilce ORDER BY il, ilce");
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Madagaskar Okul Tanıtım Yönetimi</h1>
      <p class="description">Okul tanıtım operasyonunu şehir, ilçe, personel ve etkinlik bazında tek merkezden yönetin.</p>
      <div class="mad-cards">
        <div class="mad-card"><strong><?php echo number_format_i18n($total); ?></strong><span>Toplam Okul</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($districts); ?></strong><span>İlçe</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($visited); ?></strong><span>Ziyaret Edildi</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($pending); ?></strong><span>Bekliyor</span></div>
      </div>

      <div class="mad-actions">
        <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mad-okul-list')); ?>">Okulları Aç</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mad-okul-import')); ?>">MEBBİS Listesi Yükle</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mad-okul-route')); ?>">Google Maps Rota</a>
      </div>

      <table class="widefat striped mad-summary">
        <thead><tr><th>İl</th><th>İlçe</th><th>Toplam</th><th>Ziyaret</th><th>Bekliyor</th></tr></thead>
        <tbody>
        <?php foreach ($summary as $r): ?>
          <tr>
            <td><?php echo esc_html($r->il); ?></td>
            <td><?php echo esc_html($r->ilce); ?></td>
            <td><?php echo (int)$r->toplam; ?></td>
            <td><?php echo (int)$r->ziyaret; ?></td>
            <td><?php echo (int)$r->bekliyor; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

function mad_okul_get_filters() {
    return [
        'il' => isset($_GET['il']) ? sanitize_text_field(wp_unslash($_GET['il'])) : '',
        'ilce' => isset($_GET['ilce']) ? sanitize_text_field(wp_unslash($_GET['ilce'])) : '',
        'durum' => isset($_GET['durum']) ? sanitize_text_field(wp_unslash($_GET['durum'])) : '',
        's' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
    ];
}

function mad_okul_build_where($filters, &$params) {
    global $wpdb;
    $w = ['1=1'];
    if ($filters['il']) { $w[]='il=%s'; $params[]=$filters['il']; }
    if ($filters['ilce']) { $w[]='ilce=%s'; $params[]=$filters['ilce']; }
    if ($filters['durum']) { $w[]='durum=%s'; $params[]=$filters['durum']; }
    if ($filters['s']) {
        $like = '%'.$wpdb->esc_like($filters['s']).'%';
        $w[]='(kurum_adi LIKE %s OR adres LIKE %s)';
        $params[]=$like; $params[]=$like;
    }
    return implode(' AND ', $w);
}

function mad_okul_list_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = mad_okul_table();
    $filters = mad_okul_get_filters();
    $params = [];
    $where = mad_okul_build_where($filters, $params);

    $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $per = 50;
    $offset = ($paged-1)*$per;

    $count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
    $total = $params ? (int)$wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int)$wpdb->get_var($count_sql);

    $qparams = $params;
    $qparams[]=$per; $qparams[]=$offset;
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY il, ilce, kurum_adi LIMIT %d OFFSET %d", $qparams));
    [$ils,$ilceler] = mad_okul_filter_options();

    $statuses = ['Bekliyor','Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek'];
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Tüm Okullar</h1>
      <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Kayıt güncellendi.</p></div><?php endif; ?>
      <form method="get" class="mad-filter">
        <input type="hidden" name="page" value="mad-okul-list">
        <select name="il"><option value="">Tüm İller</option><?php foreach($ils as $x): ?><option <?php selected($filters['il'],$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="ilce"><option value="">Tüm İlçeler</option><?php foreach($ilceler as $x): ?><option <?php selected($filters['ilce'],$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="durum"><option value="">Tüm Durumlar</option><?php foreach($statuses as $x): ?><option <?php selected($filters['durum'],$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <input type="search" name="s" value="<?php echo esc_attr($filters['s']); ?>" placeholder="Okul veya adres ara">
        <button class="button">Filtrele</button>
      </form>

      <p><strong><?php echo number_format_i18n($total); ?></strong> kayıt bulundu.</p>
      <table class="widefat striped mad-schools">
        <thead><tr><th>İl / İlçe</th><th>Kurum</th><th>Adres</th><th>Durum</th><th>Personel / Etkinlik</th><th>İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?php echo esc_html($r->il); ?></strong><br><?php echo esc_html($r->ilce); ?></td>
            <td><?php echo esc_html($r->kurum_adi); ?></td>
            <td><?php echo esc_html($r->adres); ?></td>
            <td><span class="mad-status"><?php echo esc_html($r->durum); ?></span></td>
            <td><?php echo esc_html($r->personel ?: '-'); ?><br><small><?php echo esc_html($r->etkinlik ?: '-'); ?></small></td>
            <td class="mad-actions-cell">
              <a class="button button-small" target="_blank" href="<?php echo esc_url(mad_okul_maps_url($r)); ?>">Maps</a>
              <button type="button" class="button button-small mad-edit-btn" data-id="<?php echo (int)$r->id; ?>">Düzenle</button>
            </td>
          </tr>
          <tr class="mad-edit-row" id="mad-edit-<?php echo (int)$r->id; ?>" style="display:none">
            <td colspan="6">
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-edit-form">
                <input type="hidden" name="action" value="mad_okul_update">
                <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                <?php wp_nonce_field('mad_okul_update_'.$r->id); ?>
                <label>Durum
                  <select name="durum"><?php foreach($statuses as $x): ?><option <?php selected($r->durum,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
                </label>
                <label>Personel <input name="personel" value="<?php echo esc_attr($r->personel); ?>"></label>
                <label>Etkinlik <input name="etkinlik" value="<?php echo esc_attr($r->etkinlik); ?>"></label>
                <label>Son Ziyaret <input type="date" name="son_ziyaret" value="<?php echo esc_attr($r->son_ziyaret); ?>"></label>
                <label class="mad-note">Not <textarea name="notlar" rows="2"><?php echo esc_textarea($r->notlar); ?></textarea></label>
                <button class="button button-primary">Kaydet</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php
      $pages = max(1, ceil($total/$per));
      echo '<div class="tablenav"><div class="tablenav-pages">';
      echo paginate_links(['base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$paged,'total'=>$pages]);
      echo '</div></div>';
      ?>
    </div>
    <script>
      document.addEventListener('click', function(e){
        const b=e.target.closest('.mad-edit-btn'); if(!b) return;
        const row=document.getElementById('mad-edit-'+b.dataset.id);
        row.style.display = row.style.display==='none' ? 'table-row' : 'none';
      });
    </script>
    <?php
}

add_action('admin_post_mad_okul_update', function() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
    $id = absint($_POST['id'] ?? 0);
    check_admin_referer('mad_okul_update_'.$id);
    global $wpdb;
    $data = [
        'durum' => sanitize_text_field($_POST['durum'] ?? 'Bekliyor'),
        'personel' => sanitize_text_field($_POST['personel'] ?? ''),
        'etkinlik' => sanitize_text_field($_POST['etkinlik'] ?? ''),
        'son_ziyaret' => !empty($_POST['son_ziyaret']) ? sanitize_text_field($_POST['son_ziyaret']) : null,
        'notlar' => sanitize_textarea_field($_POST['notlar'] ?? ''),
        'updated_at' => current_time('mysql'),
    ];
    $wpdb->update(mad_okul_table(), $data, ['id'=>$id]);
    wp_safe_redirect(add_query_arg(['page'=>'mad-okul-list','updated'=>1], admin_url('admin.php')));
    exit;
});

function mad_okul_should_include($row) {
    $name = mad_okul_norm($row['KURUM_ADI'] ?? '');
    $type = trim($row['KURUM_TUR_ADI'] ?? '');

    if (strpos($name,'KREŞ') !== false || strpos($name,'GÜNDÜZ BAK') !== false) return true;

    $official = ['Anaokulu','İlkokul','Ortaokul','İmam Hatip Ortaokulu','Yatılı Bölge Ortaokulu'];
    $private  = ['Özel Türk Okul Öncesi Kurumu','Özel Türk İlkokulu','Özel Türk Ortaokulu'];
    return in_array($type, $official, true) || in_array($type, $private, true);
}

function mad_okul_is_rural($name, $address) {
    $t = ' ' . mad_okul_norm($name.' '.$address) . ' ';
    $patterns = [
        '/\bKÖYÜ\b/u',
        '/\bKÖY\b/u',
        '/\bBELDESİ\b/u',
        '/\bBELDE\b/u',
        '/KÜME EVLERİ/u',
        '/KÜME KÜME EVLERİ/u',
        '/\bKÖYİÇİ\b/u',
    ];
    foreach ($patterns as $p) if (preg_match($p, $t)) return true;
    return false;
}

function mad_okul_html_xls_rows($path) {
    $html = file_get_contents($path);
    if ($html === false) return [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $all = [];
    foreach ($dom->getElementsByTagName('tr') as $tr) {
        $cells = [];
        foreach ($tr->childNodes as $node) {
            if (!in_array(strtolower($node->nodeName), ['td','th'], true)) continue;
            $cells[] = trim(preg_replace('/\s+/u',' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        if ($cells) $all[] = $cells;
    }

    $hi = null;
    foreach ($all as $i=>$r) if (in_array('KURUM_ADI',$r,true)) { $hi=$i; break; }
    if ($hi === null) return [];
    $headers = $all[$hi];
    $out = [];
    for ($i=$hi+1; $i<count($all); $i++) {
        $r = array_pad($all[$i], count($headers), '');
        $assoc = array_combine($headers, array_slice($r,0,count($headers)));
        if (!empty($assoc['KURUM_ADI'])) $out[]=$assoc;
    }
    return $out;
}

function mad_okul_csv_rows($path) {
    $fh = fopen($path,'r'); if(!$fh) return [];
    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); return []; }
    $header = array_map(function($x){ return trim(preg_replace('/^\xEF\xBB\xBF/','',$x)); }, $header);
    $out = [];
    while (($r=fgetcsv($fh))!==false) {
        $r=array_pad($r,count($header),'');
        $assoc=array_combine($header,array_slice($r,0,count($header)));
        if (!empty($assoc['KURUM_ADI'])) $out[]=$assoc;
    }
    fclose($fh); return $out;
}

function mad_okul_import_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>MEBBİS Listesi İçe Aktar</h1>
      <p>MEBBİS'ten indirdiğiniz <strong>.xls</strong> dosyalarını aynı anda yükleyebilirsiniz. Sistem kreş/gündüz bakımevi, anaokulu, ilkokul ve ortaokulları alır; kırsal açık adresleri ve mükerrerleri dışarıda bırakır.</p>
      <?php if (!empty($_GET['imported'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo (int)$_GET['imported']; ?> yeni kurum eklendi. <?php echo (int)($_GET['skipped'] ?? 0); ?> kayıt atlandı.</p></div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-upload-box">
        <input type="hidden" name="action" value="mad_okul_import">
        <?php wp_nonce_field('mad_okul_import'); ?>
        <input type="file" name="files[]" accept=".xls,.csv" multiple required>
        <button class="button button-primary button-hero">Dosyaları İşle ve Ekle</button>
      </form>
      <p><small>Not: Mevcut okul verileri eklenti ile birlikte gelir. Bu ekran yeni şehir/ilçe MEBBİS listelerini sonraki dönemlerde eklemek içindir.</small></p>
    </div>
    <?php
}

add_action('admin_post_mad_okul_import', function() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
    check_admin_referer('mad_okul_import');

    $imported=0; $skipped=0;
    $names = $_FILES['files']['name'] ?? [];
    $tmps  = $_FILES['files']['tmp_name'] ?? [];
    if (!is_array($names)) { $names=[$names]; $tmps=[$tmps]; }

    foreach ($names as $i=>$name) {
        if (empty($tmps[$i]) || !is_uploaded_file($tmps[$i])) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $rows = $ext === 'csv' ? mad_okul_csv_rows($tmps[$i]) : mad_okul_html_xls_rows($tmps[$i]);

        foreach ($rows as $r) {
            $il=$r['IL_ADI'] ?? ''; $ilce=$r['ILCE_ADI'] ?? '';
            $kurum=$r['KURUM_ADI'] ?? ''; $adres=$r['ADRES'] ?? '';
            if (!mad_okul_should_include($r) || mad_okul_is_rural($kurum,$adres)) { $skipped++; continue; }
            $res=mad_okul_insert_school($il,$ilce,$kurum,$adres);
            if ($res) $imported++; else $skipped++;
        }
    }

    wp_safe_redirect(add_query_arg(['page'=>'mad-okul-import','imported'=>$imported,'skipped'=>$skipped], admin_url('admin.php')));
    exit;
});

function mad_okul_route_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table=mad_okul_table();
    [$ils,$ilceler]=mad_okul_filter_options();

    $il=sanitize_text_field($_GET['il'] ?? '');
    $ilce=sanitize_text_field($_GET['ilce'] ?? '');
    $durum=sanitize_text_field($_GET['durum'] ?? 'Bekliyor');
    $params=[]; $w=['1=1'];
    if($il){$w[]='il=%s';$params[]=$il;}
    if($ilce){$w[]='ilce=%s';$params[]=$ilce;}
    if($durum){$w[]='durum=%s';$params[]=$durum;}
    $where=implode(' AND ',$w);
    $sql="SELECT * FROM $table WHERE $where ORDER BY kurum_adi LIMIT 120";
    $rows=$params ? $wpdb->get_results($wpdb->prepare($sql,$params)) : $wpdb->get_results($sql);

    $selected=array_map('absint', $_POST['school_ids'] ?? []);
    $start=sanitize_text_field($_POST['start_address'] ?? '');
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Google Maps Rota Oluştur</h1>
      <p>İl/ilçe ve durum seçin, rotaya girecek okulları işaretleyin. Sistem seçilen noktaları Google Maps bağlantılarına böler.</p>
      <form method="get" class="mad-filter">
        <input type="hidden" name="page" value="mad-okul-route">
        <select name="il"><option value="">İl Seç</option><?php foreach($ils as $x): ?><option <?php selected($il,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="ilce"><option value="">İlçe Seç</option><?php foreach($ilceler as $x): ?><option <?php selected($ilce,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="durum">
          <?php foreach(['Bekliyor','Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek'] as $x): ?><option <?php selected($durum,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?>
        </select>
        <button class="button">Listeyi Getir</button>
      </form>

      <form method="post">
        <?php wp_nonce_field('mad_okul_route'); ?>
        <div class="mad-route-start">
          <label><strong>Başlangıç / Gösteri Salonu</strong><input type="text" name="start_address" value="<?php echo esc_attr($start); ?>" placeholder="Örn. Porsuk Kapalı Spor Salonu, Eskişehir"></label>
        </div>
        <table class="widefat striped">
          <thead><tr><th><input type="checkbox" id="mad-all"></th><th>Kurum</th><th>Adres</th><th>Maps</th></tr></thead>
          <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><input type="checkbox" name="school_ids[]" value="<?php echo (int)$r->id; ?>" <?php checked(in_array((int)$r->id,$selected,true)); ?>></td>
              <td><?php echo esc_html($r->kurum_adi); ?></td>
              <td><?php echo esc_html($r->adres); ?></td>
              <td><a target="_blank" href="<?php echo esc_url(mad_okul_maps_url($r)); ?>">Aç</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p><button class="button button-primary button-hero">Seçilenlerden Rota Oluştur</button></p>
      </form>

      <?php
      if ($_SERVER['REQUEST_METHOD']==='POST' && $selected) {
          check_admin_referer('mad_okul_route');
          $ids=implode(',',array_map('absint',$selected));
          $chosen=$wpdb->get_results("SELECT * FROM $table WHERE id IN ($ids) ORDER BY FIELD(id,$ids)");
          if ($chosen) {
              echo '<div class="mad-routes"><h2>Oluşturulan Rotalar</h2>';
              $chunks=array_chunk($chosen,8);
              foreach($chunks as $n=>$chunk) {
                  $origin=$start ?: ($chunk[0]->adres.', '.$chunk[0]->ilce.', '.$chunk[0]->il);
                  $dest=end($chunk);
                  $middle=$chunk; array_pop($middle);
                  $waypoints=[];
                  foreach($middle as $m) $waypoints[]=$m->adres.', '.$m->ilce.', '.$m->il;
                  $url='https://www.google.com/maps/dir/?api=1&origin='.rawurlencode($origin).
                       '&destination='.rawurlencode($dest->adres.', '.$dest->ilce.', '.$dest->il).
                       '&travelmode=driving';
                  if($waypoints) $url.='&waypoints='.implode('%7C',array_map('rawurlencode',$waypoints));
                  echo '<p><a class="button button-primary" target="_blank" href="'.esc_url($url).'">Rota '.($n+1).' — '.count($chunk).' okul Google Maps’te Aç</a></p>';
              }
              echo '<p class="description">Bu sürüm seçilen okulları gruplandırır. Trafik/sürüş süresine göre otomatik optimum sıralama Google Routes API entegrasyonu ile ikinci aşamada eklenebilir.</p></div>';
          }
      }
      ?>
    </div>
    <script>
      document.addEventListener('change',function(e){
        if(e.target.id!=='mad-all') return;
        document.querySelectorAll('input[name="school_ids[]"]').forEach(x=>x.checked=e.target.checked);
      });
    </script>
    <?php
}
