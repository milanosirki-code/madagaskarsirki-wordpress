<?php
/**
 * Plugin Name: Madagaskar Okul Tanıtım Yönetimi
 * Description: Madagaskar Sirki okul tanıtım listelerini tek merkezde yönetir. MEBBİS XLS/CSV aktarımı, ziyaret durumu, personel/etkinlik/not takibi ve Google Maps rota bağlantıları sağlar.
 * Version: 1.8.2
 * Author: Dünya Organizasyon
 * Text Domain: madagaskar-okul-tanitim
 */

if (!defined('ABSPATH')) exit;

define('MAD_OKUL_VERSION', '1.8.2');
define('MAD_OKUL_FILE', __FILE__);
define('MAD_OKUL_DIR', plugin_dir_path(__FILE__));

require_once MAD_OKUL_DIR . 'includes/class-mad-okul-operations.php';
require_once MAD_OKUL_DIR . 'includes/class-mad-okul-student-research.php';
Mad_Okul_Student_Research::hooks();
require_once MAD_OKUL_DIR . 'includes/class-mad-okul-records.php';
Mad_Okul_Records::hooks();

function mad_okul_table() {
    global $wpdb;
    return $wpdb->prefix . 'mad_okul_tanitim';
}

function mad_okul_excluded_table() {
    global $wpdb;
    return $wpdb->prefix . 'mad_okul_kirsal_cikarilanlar';
}

function mad_okul_norm($s) {
    $s = trim((string)$s);
    if (function_exists('mb_strtoupper')) $s = mb_strtoupper($s, 'UTF-8');
    else $s = strtoupper($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

function mad_okul_place_title($s) {
    $s = trim((string)$s);
    if ($s === '') return '';

    $s = preg_replace('/\s+/u', ' ', $s);
    $s = strtr($s, ['I'=>'ı', 'İ'=>'i']);

    if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8');
    else $s = strtolower($s);

    $parts = preg_split('/([\s-]+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as &$part) {
        if ($part === '' || preg_match('/^[\s-]+$/u', $part)) continue;
        if (function_exists('mb_substr')) {
            $first = mb_substr($part, 0, 1, 'UTF-8');
            $rest  = mb_substr($part, 1, null, 'UTF-8');
        } else {
            $first = substr($part, 0, 1);
            $rest  = substr($part, 1);
        }
        $first = strtr($first, [
            'i'=>'İ','ı'=>'I','ç'=>'Ç','ğ'=>'Ğ','ö'=>'Ö','ş'=>'Ş','ü'=>'Ü',
            'a'=>'A','b'=>'B','c'=>'C','d'=>'D','e'=>'E','f'=>'F','g'=>'G','h'=>'H',
            'j'=>'J','k'=>'K','l'=>'L','m'=>'M','n'=>'N','o'=>'O','p'=>'P','q'=>'Q',
            'r'=>'R','s'=>'S','t'=>'T','u'=>'U','v'=>'V','w'=>'W','x'=>'X','y'=>'Y','z'=>'Z'
        ]);
        $part = $first . $rest;
    }
    unset($part);
    return implode('', $parts);
}

function mad_okul_normalize_existing_places() {
    global $wpdb;
    foreach ([mad_okul_table(), mad_okul_excluded_table()] as $target) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $target));
        if ($exists !== $target) continue;

        $pairs = $wpdb->get_results("SELECT DISTINCT il, ilce FROM $target");
        foreach ((array)$pairs as $pair) {
            $new_il = mad_okul_place_title($pair->il);
            $new_ilce = mad_okul_place_title($pair->ilce);
            if ($new_il === $pair->il && $new_ilce === $pair->ilce) continue;

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $target SET il=%s, ilce=%s WHERE il=%s AND ilce=%s",
                    $new_il,
                    $new_ilce,
                    $pair->il,
                    $pair->ilce
                )
            );
        }
    }
    update_option('mad_okul_place_normalized_version', MAD_OKUL_VERSION, false);
}

function mad_okul_hash($il, $ilce, $kurum, $adres) {
    return md5(mad_okul_norm($il).'|'.mad_okul_norm($ilce).'|'.mad_okul_norm($kurum).'|'.mad_okul_norm($adres));
}

function mad_okul_maps_url($r) {
    $q = trim($r->kurum_adi . ', ' . $r->adres . ', ' . $r->ilce . ', ' . $r->il);
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

function mad_okul_nullable_int($value) {
    if ($value === null || trim((string)$value) === '') return null;
    $digits = preg_replace('/[^0-9]/', '', (string)$value);
    return $digits === '' ? null : absint($digits);
}

function mad_okul_student_count_value($value) {
    return mad_okul_nullable_int($value);
}

function mad_okul_normalize_datetime($value) {
    $value = trim((string)$value);
    if ($value === '') return null;

    // Excel seri tarihleri (1900 date system).
    if (is_numeric($value)) {
        $serial = (float)$value;
        if ($serial > 20000 && $serial < 90000) {
            $unix = (int)round(($serial - 25569) * 86400);
            return gmdate('Y-m-d H:i:s', $unix);
        }
    }

    foreach (['Y-m-d H:i:s','Y-m-d','d.m.Y','d/m/Y','d-m-Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) return $dt->format('Y-m-d H:i:s');
    }
    $ts = strtotime($value);
    return $ts ? wp_date('Y-m-d H:i:s', $ts) : null;
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
        kurum_turu varchar(190) NOT NULL DEFAULT '',
        egitim_kademesi varchar(120) NOT NULL DEFAULT '',
        adres text NOT NULL,
        telefon varchar(80) NOT NULL DEFAULT '',
        web_adresi text NULL,
        campus_key varchar(64) NOT NULL DEFAULT '',
        campus_name varchar(255) NOT NULL DEFAULT '',
        student_count int unsigned NULL,
        ogrenci_sayisi int unsigned NULL,
        ogrenci_sayi_durumu varchar(30) NOT NULL DEFAULT '',
        ogrenci_kaynak_turu varchar(50) NOT NULL DEFAULT '',
        ogrenci_kaynak_url text NULL,
        ogrenci_dogrulama_tarihi datetime NULL,
        oncelik varchar(20) NOT NULL DEFAULT '',
        veri_yili varchar(20) NOT NULL DEFAULT '',
        durum varchar(50) NOT NULL DEFAULT 'Bekliyor',
        personel varchar(190) NOT NULL DEFAULT '',
        etkinlik varchar(190) NOT NULL DEFAULT '',
        son_ziyaret date NULL,
        notlar text NULL,
        latitude decimal(10,7) NULL,
        longitude decimal(10,7) NULL,
        program_id bigint(20) unsigned NOT NULL DEFAULT 0,
        mmc_program_id bigint(20) unsigned DEFAULT NULL,
        assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        route_group varchar(50) NOT NULL DEFAULT '',
        route_order int unsigned NOT NULL DEFAULT 0,
        route_distance_m int unsigned NOT NULL DEFAULT 0,
        route_duration_s int unsigned NOT NULL DEFAULT 0,
        dedupe_hash char(32) NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY dedupe_hash (dedupe_hash),
        KEY il_ilce (il(40), ilce(40)),
        KEY campus_key (campus_key),
        KEY ogrenci_sayi_durumu (ogrenci_sayi_durumu),
        KEY durum (durum),
        KEY program_assignment (program_id, assigned_user_id, route_group(20), route_order),
        KEY mmc_program_id (mmc_program_id)
    ) $charset;";
    dbDelta($sql);
    // 1.8.0 (#195) student_count alanını 1.8.2 zengin öğrenci kaydıyla
    // çift yönlü ve idempotent tut; eski canlı veriyi kaybetme.
    delete_transient('mmc_school_source_detect_v1');
    $wpdb->query("UPDATE $table SET ogrenci_sayisi = student_count WHERE ogrenci_sayisi IS NULL AND student_count IS NOT NULL");
    $wpdb->query("UPDATE $table SET student_count = ogrenci_sayisi WHERE student_count IS NULL AND ogrenci_sayisi IS NOT NULL");
    $excluded = mad_okul_excluded_table();
    dbDelta("CREATE TABLE $excluded (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        il varchar(100) NOT NULL,
        ilce varchar(100) NOT NULL,
        kurum_adi text NOT NULL,
        adres text NOT NULL,
        cikarilma_nedeni varchar(190) NOT NULL,
        dedupe_hash char(32) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY dedupe_hash (dedupe_hash),
        KEY il_ilce (il(40), ilce(40))
    ) $charset;");
}

function mad_okul_insert_school($il, $ilce, $kurum, $adres, $meta = []) {
    global $wpdb;
    $il = mad_okul_place_title(sanitize_text_field($il));
    $ilce = mad_okul_place_title(sanitize_text_field($ilce));
    $kurum = sanitize_text_field($kurum);
    $adres = sanitize_textarea_field($adres);
    if (!$il || !$ilce || !$kurum) return false;

    $hash = mad_okul_hash($il, $ilce, $kurum, $adres);
    $now = current_time('mysql');
    $student_count = mad_okul_nullable_int($meta['ogrenci_sayisi'] ?? null);
    $verified_at = mad_okul_normalize_datetime($meta['ogrenci_dogrulama_tarihi'] ?? '');

    $data = [
        'il'                        => $il,
        'ilce'                      => $ilce,
        'kurum_adi'                 => $kurum,
        'kurum_turu'                => sanitize_text_field($meta['kurum_turu'] ?? ''),
        'egitim_kademesi'           => sanitize_text_field($meta['egitim_kademesi'] ?? ''),
        'adres'                     => $adres,
        'telefon'                   => sanitize_text_field($meta['telefon'] ?? ''),
        'web_adresi'                => esc_url_raw($meta['web_adresi'] ?? ''),
        'campus_key'                => sanitize_key($meta['campus_key'] ?? ''),
        'campus_name'               => sanitize_text_field($meta['campus_name'] ?? ''),
        'student_count'             => $student_count,
        'ogrenci_sayisi'            => $student_count,
        'ogrenci_sayi_durumu'       => sanitize_key($meta['ogrenci_sayi_durumu'] ?? ''),
        'ogrenci_kaynak_turu'       => sanitize_text_field($meta['ogrenci_kaynak_turu'] ?? ''),
        'ogrenci_kaynak_url'        => esc_url_raw($meta['ogrenci_kaynak_url'] ?? ''),
        'ogrenci_dogrulama_tarihi'  => $verified_at,
        'oncelik'                   => sanitize_key($meta['oncelik'] ?? ''),
        'veri_yili'                 => sanitize_text_field($meta['veri_yili'] ?? ''),
        'durum'                     => 'Bekliyor',
        'personel'                  => '',
        'etkinlik'                  => '',
        'son_ziyaret'               => null,
        'notlar'                    => '',
        'dedupe_hash'               => $hash,
        'created_at'                => $now,
        'updated_at'                => $now,
    ];

    $inserted = $wpdb->insert(mad_okul_table(), $data);
    if ($inserted) return $inserted;

    // Aynı okul/adres tekrar içe aktarılırsa okul ana kaydını güncel MEBBİS/web
    // metadatasıyla zenginleştir; saha durumunu/personeli ezme.
    $existing_id = (int)$wpdb->get_var($wpdb->prepare(
        'SELECT id FROM '.mad_okul_table().' WHERE dedupe_hash=%s LIMIT 1',
        $hash
    ));
    if (!$existing_id) return false;

    // Basit MEBBİS dosyası sonradan tekrar yüklendiğinde elle/web araştırmasıyla
    // zenginleştirilmiş alanları boş değerlerle silme. Gelen dolu alanlar güncellenir.
    $update = [
        'adres'      => $adres,
        'updated_at' => $now,
    ];
    foreach ([
        'kurum_turu',
        'egitim_kademesi',
        'telefon',
        'web_adresi',
        'campus_key',
        'campus_name',
        'oncelik',
        'veri_yili',
    ] as $optional_key) {
        if (isset($data[$optional_key]) && trim((string)$data[$optional_key]) !== '') {
            $update[$optional_key] = $data[$optional_key];
        }
    }
    if (null !== $student_count) {
        $update['student_count'] = $student_count;
        $update['ogrenci_sayisi'] = $student_count;
        $update['ogrenci_sayi_durumu'] = $data['ogrenci_sayi_durumu'];
        $update['ogrenci_kaynak_turu'] = $data['ogrenci_kaynak_turu'];
        $update['ogrenci_kaynak_url'] = $data['ogrenci_kaynak_url'];
        $update['ogrenci_dogrulama_tarihi'] = $verified_at;
    }
    return $wpdb->update(mad_okul_table(), $update, ['id'=>$existing_id]);
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
    Mad_Okul_Operations::activate();
    mad_okul_seed();
}
register_activation_hook(__FILE__, 'mad_okul_activate');

add_action('plugins_loaded', function() {
    if (get_option('mad_okul_db_version') !== MAD_OKUL_VERSION) {
        mad_okul_create_table();
        Mad_Okul_Operations::activate();
        update_option('mad_okul_db_version', MAD_OKUL_VERSION);
    }
    if (get_option('mad_okul_place_normalized_version') !== MAD_OKUL_VERSION) {
        mad_okul_normalize_existing_places();
    }
    Mad_Okul_Operations::boot();
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
    add_submenu_page('mad-okul','Öğrenci Sayıları','Öğrenci Sayıları','manage_options','mad-okul-students','mad_okul_students_page');
    add_submenu_page('mad-okul','MEBBİS İçe Aktar','MEBBİS İçe Aktar','manage_options','mad-okul-import','mad_okul_import_page');
    add_submenu_page('mad-okul','Adresi Eksik Kurumlar','Adresi Eksik','manage_options','mad-okul-missing','mad_okul_missing_page');
    add_submenu_page('mad-okul','Kırsal Çıkarılanlar','Kırsal Çıkarılanlar','manage_options','mad-okul-rural','mad_okul_rural_page');
    add_submenu_page('mad-okul','Google Maps Rota','Google Maps Rota','manage_options','mad-okul-route','mad_okul_route_page');
});

add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'mad-okul') === false) return;
    wp_enqueue_style('mad-okul-admin', plugins_url('assets/admin.css', __FILE__), [], MAD_OKUL_VERSION);
});

function mad_okul_filter_options($selected_il = '') {
    global $wpdb;
    $table = mad_okul_table();
    $ils = $wpdb->get_col("SELECT DISTINCT il FROM $table ORDER BY il");

    if ($selected_il !== '') {
        $ilceler = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT ilce FROM $table WHERE il=%s ORDER BY ilce",
                $selected_il
            )
        );
    } else {
        $ilceler = $wpdb->get_col("SELECT DISTINCT ilce FROM $table ORDER BY ilce");
    }

    return [$ils, $ilceler];
}

function mad_okul_dashboard() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = mad_okul_table();
    $mmc_program_id=absint($_GET['mmc_program_id'] ?? 0);
    $ctx=$mmc_program_id ? Mad_Okul_Operations::mmc_program_context($mmc_program_id) : null;
    if(is_wp_error($ctx)){echo '<div class="notice notice-error"><p>'.esc_html($ctx->get_error_message()).'</p></div>';return;}
    $where=$ctx ? $wpdb->prepare('il=%s AND ilce=%s',mad_okul_place_title($ctx->program->province_name),mad_okul_place_title($ctx->program->district_name)) : '1=1';
    $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
    $visited = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where AND durum='Ziyaret Edildi'");
    $pending = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where AND durum='Bekliyor'");
    $districts = (int)$wpdb->get_var("SELECT COUNT(DISTINCT CONCAT(il,'|',ilce)) FROM $table WHERE $where");
    $field_summary = $ctx && class_exists('MMC_Field_Service') ? MMC_Field_Service::summary($mmc_program_id) : null;

    $summary = $wpdb->get_results("SELECT il, ilce, COUNT(*) toplam,
        SUM(durum='Ziyaret Edildi') ziyaret,
        SUM(durum='Bekliyor') bekliyor
        FROM $table WHERE $where GROUP BY il, ilce ORDER BY il, ilce");
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Madagaskar Okul Tanıtım Yönetimi</h1>
      <?php if($ctx): ?><p><strong><?php echo esc_html($ctx->program->program_code.' · '.$ctx->program->province_name.' / '.$ctx->program->district_name); ?></strong> · Programın görev ve ziyaret durumu <a href="<?php echo esc_url(add_query_arg(['page'=>'mmc-field','program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">MMC Okul / Saha</a> kaydından gelir. Alttaki okul listesi ana kayıttır.</p><?php endif; ?>
      <p class="description">Okul tanıtım operasyonunu şehir, ilçe, personel ve etkinlik bazında tek merkezden yönetin.</p>
      <div class="mad-cards">
        <div class="mad-card"><strong><?php echo number_format_i18n($total); ?></strong><span>Toplam Okul</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($districts); ?></strong><span>İlçe</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($field_summary ? $field_summary['visited_schools'] : $visited); ?></strong><span><?php echo $field_summary ? 'MMC Ziyaret Edildi' : 'Ziyaret Edildi'; ?></span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($field_summary ? $field_summary['assigned_schools'] : $pending); ?></strong><span><?php echo $field_summary ? 'MMC Personele Atandı' : 'Bekliyor'; ?></span></div>
      </div>

      <div class="mad-actions">
        <a class="button button-primary" href="<?php echo esc_url(add_query_arg(array_filter(['page'=>'mad-okul-list','mmc_program_id'=>$mmc_program_id]),admin_url('admin.php'))); ?>">Okulları Aç</a>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mad-okul-import')); ?>">MEBBİS Listesi Yükle</a>
        <a class="button" href="<?php echo esc_url(add_query_arg(array_filter(['page'=>'mad-okul-route','mmc_program_id'=>$mmc_program_id]),admin_url('admin.php'))); ?>">Google Maps Rota</a>
      </div>

      <table class="widefat striped mad-summary">
        <thead><tr><th>İl</th><th>İlçe</th><th>Toplam</th><th><?php echo $ctx ? 'Ana kayıt ziyareti' : 'Ziyaret'; ?></th><th><?php echo $ctx ? 'Ana kayıt bekliyor' : 'Bekliyor'; ?></th></tr></thead>
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

// MMC'nin okul cache'i ana okul ID'sini kaynak tablo adıyla hash'ler.
// Aynı ana kaydı eşleştirmeden saha durumunu başka bir okula taşımayın.
function mad_okul_mmc_field_map($program_id) {
    if (!$program_id || !class_exists('MMC_Field_Service') || !class_exists('MMC_School_Source_Service')) return [];
    $source = MMC_School_Source_Service::source_info();
    if (empty($source['external']) || empty($source['table']) || ($source['mapping']['id'] ?? '') !== 'id'
        || $source['table'] !== mad_okul_table()) return [];
    $out = [];
    foreach ((array) MMC_Field_Service::targets($program_id, ['limit'=>2000]) as $target) {
        $code = (string)($target->institution_code ?? '');
        if ($code) $out[$code] = $target;
    }
    return $out;
}

function mad_okul_list_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = mad_okul_table();
    $filters = mad_okul_get_filters();
    $mmc_program_id = absint($_GET['mmc_program_id'] ?? 0);
    if ($mmc_program_id) {
        $ctx = Mad_Okul_Operations::mmc_program_context($mmc_program_id);
        if (is_wp_error($ctx)) {
            echo '<div class="wrap"><div class="notice notice-error"><p>'.esc_html($ctx->get_error_message()).'</p></div></div>';
            return;
        }
        $filters['il'] = mad_okul_place_title($ctx->program->province_name);
        $filters['ilce'] = mad_okul_place_title($ctx->program->district_name);
    }
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
    $field_map = $mmc_program_id ? mad_okul_mmc_field_map($mmc_program_id) : [];
    $field_statuses = class_exists('MMC_Field_Service') ? MMC_Field_Service::target_statuses() : [];
    [$ils,$ilceler] = mad_okul_filter_options($filters['il']);

    $statuses = ['Bekliyor','Adres Eksik','Atandı','Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek'];
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Tüm Okullar</h1>
      <?php if ($mmc_program_id): ?><p><strong><?php echo esc_html($ctx->program->program_code.' · '.$filters['il'].' / '.$filters['ilce']); ?></strong> · MMC ID <?php echo (int)$mmc_program_id; ?>. Program personeli ve ziyareti <a href="<?php echo esc_url(add_query_arg(['page'=>'mmc-field','program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">MMC Okul / Saha</a> sütununda gösterilir. Okul ana kayıt durumu ayrı tutulur.</p><?php endif; ?>
      <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success is-dismissible"><p>Kayıt güncellendi.</p></div><?php endif; ?>
      <form method="get" class="mad-filter">
        <input type="hidden" name="page" value="mad-okul-list">
        <?php if ($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><strong><?php echo esc_html($filters['il'].' / '.$filters['ilce']); ?></strong><?php else: ?>
        <select name="il"><option value="">Tüm İller</option><?php foreach($ils as $x): ?><option <?php selected($filters['il'],$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="ilce"><option value="">Tüm İlçeler</option><?php foreach($ilceler as $x): ?><option <?php selected($filters['ilce'],$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select><?php endif; ?>
        <select name="durum"><option value="">Tüm Durumlar</option><?php foreach($statuses as $x): ?><option <?php selected($filters['durum'],$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <input type="search" name="s" value="<?php echo esc_attr($filters['s']); ?>" placeholder="Okul veya adres ara">
        <button class="button">Filtrele</button>
      </form>

      <p><strong><?php echo number_format_i18n($total); ?></strong> kayıt bulundu. <?php if ($mmc_program_id): ?>Durum filtresi okul ana kaydını süzer; program saha görevi MMC sütununda görünür.<?php endif; ?></p>
      <table class="widefat striped mad-schools">
        <thead><tr><th>İl / İlçe</th><th>Kurum</th><th>Adres</th><th>Öğrenci</th><th><?php echo $mmc_program_id ? 'Okul kaydı durumu' : 'Durum'; ?></th><?php if ($mmc_program_id): ?><th>MMC saha görevi</th><?php endif; ?><th>Personel / Etkinlik</th><th>İşlem</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
          $field_code='OKT-'.strtoupper(substr(sha1($table.'|'.$r->id),0,36));
          $field_target=$field_map[$field_code] ?? null;
        ?>
          <tr>
            <td><strong><?php echo esc_html($r->il); ?></strong><br><?php echo esc_html($r->ilce); ?></td>
            <td><?php echo esc_html($r->kurum_adi); ?></td>
            <td><?php echo esc_html($r->adres); ?></td>
            <td><?php echo null !== $r->student_count ? esc_html(number_format_i18n((int)$r->student_count)) : '<span class="description">Eksik</span>'; ?></td>
            <td><span class="mad-status"><?php echo esc_html($r->durum); ?></span></td>
            <?php if ($mmc_program_id): ?><td><?php echo $field_target ? esc_html($field_statuses[$field_target->status] ?? $field_target->status) : 'Hedef kaydı yok'; ?><?php if($field_target && ($field_target->assigned_name || $field_target->assigned_user_id)): ?><br><small><?php echo esc_html($field_target->assigned_name ?: ((get_userdata($field_target->assigned_user_id)->display_name ?? ''))); ?></small><?php endif; ?></td><?php endif; ?>
            <td><?php echo esc_html($r->personel ?: '-'); ?><br><small><?php echo esc_html($r->etkinlik ?: '-'); ?></small></td>
            <td class="mad-actions-cell">
              <a class="button button-small" target="_blank" href="<?php echo esc_url(mad_okul_maps_url($r)); ?>">Maps</a>
              <button type="button" class="button button-small mad-edit-btn" data-id="<?php echo (int)$r->id; ?>">Düzenle</button>
            </td>
          </tr>
          <tr class="mad-edit-row" id="mad-edit-<?php echo (int)$r->id; ?>" style="display:none">
            <td colspan="<?php echo $mmc_program_id ? 8 : 7; ?>">
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-edit-form">
                <input type="hidden" name="action" value="mad_okul_update">
                <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                <?php if ($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?>
                <?php wp_nonce_field('mad_okul_update_'.$r->id); ?>
                <label>Durum
                  <select name="durum"><?php foreach($statuses as $x): ?><option <?php selected($r->durum,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
                </label>
                <label>Personel <input name="personel" value="<?php echo esc_attr($r->personel); ?>"></label>
                <label>Etkinlik <input name="etkinlik" value="<?php echo esc_attr($r->etkinlik); ?>"></label>
                <label>Öğrenci Sayısı <input type="number" min="0" step="1" name="student_count" value="<?php echo null !== $r->student_count ? esc_attr((int)$r->student_count) : ''; ?>" placeholder="Eksik"></label>
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

      const provinceSelect = document.querySelector('.mad-filter select[name="il"]');
      if (provinceSelect) {
        provinceSelect.addEventListener('change', function(){
          const districtSelect = document.querySelector('.mad-filter select[name="ilce"]');
          if (districtSelect) districtSelect.value = '';
          this.form.submit();
        });
      }
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
        'student_count' => mad_okul_student_count_value($_POST['student_count'] ?? ''),
        'ogrenci_sayisi' => mad_okul_student_count_value($_POST['student_count'] ?? ''),
        'son_ziyaret' => !empty($_POST['son_ziyaret']) ? sanitize_text_field($_POST['son_ziyaret']) : null,
        'notlar' => sanitize_textarea_field($_POST['notlar'] ?? ''),
        'updated_at' => current_time('mysql'),
    ];
    $wpdb->update(mad_okul_table(), $data, ['id'=>$id]);
    $args=['page'=>'mad-okul-list','updated'=>1];
    if (!empty($_POST['mmc_program_id'])) $args['mmc_program_id']=absint($_POST['mmc_program_id']);
    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    exit;
});

function mad_okul_students_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = mad_okul_table();
    $mmc_program_id = absint($_GET['mmc_program_id'] ?? 0);
    $ctx = $mmc_program_id ? Mad_Okul_Operations::mmc_program_context($mmc_program_id) : null;
    if (is_wp_error($ctx)) {
        echo '<div class="wrap"><div class="notice notice-error"><p>'.esc_html($ctx->get_error_message()).'</p></div></div>';
        return;
    }

    $il = $ctx ? mad_okul_place_title($ctx->program->province_name) : mad_okul_place_title(sanitize_text_field(wp_unslash($_GET['il'] ?? '')));
    $program_districts = [];
    if ($ctx) {
        $program_districts = class_exists('MMC_Region_Service') ? MMC_Region_Service::get_program_targets($mmc_program_id) : [];
        if (!$program_districts && !empty($ctx->program->district_name)) $program_districts = [$ctx->program->district_name];
        $program_districts = array_values(array_unique(array_filter(array_map('mad_okul_place_title',(array)$program_districts))));
    }
    $ilce = $ctx ? (count($program_districts)===1 ? $program_districts[0] : '') : mad_okul_place_title(sanitize_text_field(wp_unslash($_GET['ilce'] ?? '')));
    $unknown_only = !empty($_GET['unknown_only']);

    [$ils,$ilceler] = mad_okul_filter_options($il);
    $where = ["1=1", "UPPER(CONCAT(kurum_adi,' ',kurum_turu)) NOT LIKE '%İMAM HATİP ORTAOKULU%'"];
    $params = [];
    if ($il) { $where[]='il=%s'; $params[]=$il; }
    if ($program_districts) {
        $where[]='ilce IN ('.implode(',',array_fill(0,count($program_districts),'%s')).')';
        $params=array_merge($params,$program_districts);
    } elseif ($ilce) { $where[]='ilce=%s'; $params[]=$ilce; }
    if ($unknown_only) { $where[]='ogrenci_sayisi IS NULL'; }
    $where_sql = implode(' AND ', $where);

    $total_where = ["1=1", "UPPER(CONCAT(kurum_adi,' ',kurum_turu)) NOT LIKE '%İMAM HATİP ORTAOKULU%'"];
    $total_params = [];
    if ($il) { $total_where[]='il=%s'; $total_params[]=$il; }
    if ($program_districts) {
        $total_where[]='ilce IN ('.implode(',',array_fill(0,count($program_districts),'%s')).')';
        $total_params=array_merge($total_params,$program_districts);
    } elseif ($ilce) { $total_where[]='ilce=%s'; $total_params[]=$ilce; }
    $total_sql_where = implode(' AND ', $total_where);

    $total_sql = "SELECT COUNT(*) FROM $table WHERE $total_sql_where";
    $known_sql = "SELECT COUNT(*) FROM $table WHERE $total_sql_where AND ogrenci_sayisi IS NOT NULL";
    $students_sql = "SELECT COALESCE(SUM(ogrenci_sayisi),0) FROM $table WHERE $total_sql_where AND ogrenci_sayisi IS NOT NULL";
    $website_sql = "SELECT COUNT(*) FROM $table WHERE $total_sql_where AND web_adresi<>''";
    $total = $total_params ? (int)$wpdb->get_var($wpdb->prepare($total_sql,$total_params)) : (int)$wpdb->get_var($total_sql);
    $known = $total_params ? (int)$wpdb->get_var($wpdb->prepare($known_sql,$total_params)) : (int)$wpdb->get_var($known_sql);
    $student_sum = $total_params ? (int)$wpdb->get_var($wpdb->prepare($students_sql,$total_params)) : (int)$wpdb->get_var($students_sql);
    $website_known = $total_params ? (int)$wpdb->get_var($wpdb->prepare($website_sql,$total_params)) : (int)$wpdb->get_var($website_sql);
    $unknown = max(0,$total-$known);

    $query = "SELECT * FROM $table WHERE $where_sql ORDER BY (ogrenci_sayisi IS NULL) DESC, il, ilce, kurum_adi LIMIT 1000";
    $rows = $params ? $wpdb->get_results($wpdb->prepare($query,$params)) : $wpdb->get_results($query);
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Öğrenci Sayıları</h1>
      <p class="description">Okul internet sitesi veya doğrulanabilir kaynaktan bulunan öğrenci sayısını burada saklayın. <strong>Bulunamayan okul için tahmin girmeyin.</strong></p>
      <?php if (!empty($_GET['student_saved'])): ?><div class="notice notice-success is-dismissible"><p>Öğrenci verisi güncellendi.</p></div><?php endif; ?>
      <?php
        $research_notice = sanitize_text_field(wp_unslash($_GET['research_notice'] ?? ''));
        $research_type = sanitize_key($_GET['research_type'] ?? 'success');
        if ($research_notice):
          $research_class = $research_type === 'error' ? 'notice-error' : ($research_type === 'warning' ? 'notice-warning' : 'notice-success');
      ?>
        <div class="notice <?php echo esc_attr($research_class); ?> is-dismissible"><p><?php echo esc_html($research_notice); ?></p></div>
      <?php endif; ?>

      <?php
        $research_school_id = absint($_GET['research_school_id'] ?? 0);
        $research_result = $research_school_id && class_exists('Mad_Okul_Student_Research')
          ? Mad_Okul_Student_Research::get_result($research_school_id)
          : null;
        if ($research_result):
      ?>
        <div class="notice notice-info inline" style="padding:12px 16px;margin:12px 0">
          <p><strong>Web araştırma sonucu — <?php echo esc_html($research_result['school_name'] ?? 'Okul'); ?></strong></p>
          <?php if(!empty($research_result['candidate'])): ?>
            <p><strong>Aday öğrenci sayısı: <?php echo number_format_i18n((int)$research_result['candidate']); ?></strong></p>
            <p><?php echo esc_html($research_result['excerpt'] ?? ''); ?></p>
            <p><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($research_result['source_url']); ?>">Kaynak sayfayı aç</a></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="mad_okul_student_candidate_accept">
              <input type="hidden" name="id" value="<?php echo (int)$research_school_id; ?>">
              <?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?>
              <?php wp_nonce_field('mad_okul_student_candidate_accept_'.$research_school_id); ?>
              <button class="button button-primary">Bu Sayıyı Onayla ve Kaydet</button>
            </form>
          <?php else: ?>
            <p>Güvenilir sayı adayı bulunamadı. Tahmin yapılmadı.</p>
            <?php if(!empty($research_result['scanned_urls'])): ?><p><small>Taranan sayfa: <?php echo esc_html(implode(' · ',(array)$research_result['scanned_urls'])); ?></small></p><?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="mad-cards">
        <div class="mad-card"><strong><?php echo number_format_i18n($total); ?></strong><span>Toplam Okul Birimi</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($known); ?></strong><span>Öğrenci Sayısı Bilinen</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($unknown); ?></strong><span>Öğrenci Sayısı Bilinmeyen</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($student_sum); ?></strong><span>Doğrulanmış Öğrenci</span></div>
        <div class="mad-card"><strong><?php echo number_format_i18n($website_known); ?></strong><span>Web Sitesi Kayıtlı</span></div>
      </div>

      <form method="get" class="mad-filter">
        <input type="hidden" name="page" value="mad-okul-students">
        <?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><strong><?php echo esc_html($il.' / '.implode(', ',$program_districts)); ?></strong><?php else: ?>
        <select name="il"><option value="">Tüm İller</option><?php foreach($ils as $x): ?><option <?php selected($il,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="ilce"><option value="">Tüm İlçeler</option><?php foreach($ilceler as $x): ?><option <?php selected($ilce,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select><?php endif; ?>
        <label><input type="checkbox" name="unknown_only" value="1" <?php checked($unknown_only); ?>> Sadece öğrenci sayısı bilinmeyenler</label>
        <button class="button">Filtrele</button>
      </form>

      <p><strong><?php echo number_format_i18n($unknown); ?></strong> okul biriminin öğrenci sayısı bilinmiyor. Bu sayı sıfırlanmadan baskı planında eksik veri uyarısı devam eder.</p>

      <table class="widefat striped">
        <thead><tr><th>Okul</th><th>Adres</th><th>Web</th><th>Öğrenci</th><th>Durum</th><th>Kaynak / Veri yılı</th><th>Doğrulama</th><th>Öncelik</th><th>Kaydet</th></tr></thead>
        <tbody>
        <?php if(!$rows): ?><tr><td colspan="9">Kayıt bulunamadı.</td></tr><?php else: foreach($rows as $r): $form_id='mad-student-'.(int)$r->id; ?>
          <tr>
              <td>
                <form id="<?php echo esc_attr($form_id); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                  <input type="hidden" name="action" value="mad_okul_student_update">
                  <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                  <?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?>
                  <?php wp_nonce_field('mad_okul_student_update_'.$r->id); ?>
                </form>
                <strong><?php echo esc_html($r->kurum_adi); ?></strong><br><small><?php echo esc_html(trim($r->kurum_turu.' · '.$r->egitim_kademesi,' ·')); ?></small>
              </td>
              <td><?php echo esc_html($r->adres); ?></td>
              <td>
                <input form="<?php echo esc_attr($form_id); ?>" style="width:210px" type="url" name="web_adresi" value="<?php echo esc_attr($r->web_adresi); ?>" placeholder="https://okul...">
                <?php if($r->web_adresi): ?>
                  <p style="margin:4px 0"><a href="<?php echo esc_url($r->web_adresi); ?>" target="_blank" rel="noopener noreferrer">Siteyi Aç</a></p>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:4px">
                    <input type="hidden" name="action" value="mad_okul_student_research">
                    <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>">
                    <?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?>
                    <?php wp_nonce_field('mad_okul_student_research_'.$r->id); ?>
                    <button class="button button-small">Siteden Öğrenci Sayısını Ara</button>
                  </form>
                <?php else: ?>
                  <small>Önce web adresini yazıp Kaydet.</small>
                <?php endif; ?>
              </td>
              <td><input form="<?php echo esc_attr($form_id); ?>" style="width:90px" type="number" min="0" name="ogrenci_sayisi" value="<?php echo esc_attr(null===$r->ogrenci_sayisi?'':$r->ogrenci_sayisi); ?>" placeholder="Bilinmiyor"></td>
              <td><select form="<?php echo esc_attr($form_id); ?>" name="ogrenci_sayi_durumu">
                <?php foreach([''=>'—','tam'=>'Tam','kismi'=>'Kısmi','ikincil'=>'İkincil','bulunamadi'=>'Bulunamadı'] as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($r->ogrenci_sayi_durumu,$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
              </select></td>
              <td><input form="<?php echo esc_attr($form_id); ?>" style="width:120px" name="ogrenci_kaynak_turu" value="<?php echo esc_attr($r->ogrenci_kaynak_turu); ?>" placeholder="MEB resmî"><br><input form="<?php echo esc_attr($form_id); ?>" style="width:180px" type="url" name="ogrenci_kaynak_url" value="<?php echo esc_attr($r->ogrenci_kaynak_url); ?>" placeholder="Kaynak URL"><br><label>Veri yılı <input form="<?php echo esc_attr($form_id); ?>" type="number" min="2000" max="2099" name="student_data_year" value="<?php echo esc_attr($r->student_data_year ?: wp_date('Y')); ?>" style="width:85px"></label><br><input form="<?php echo esc_attr($form_id); ?>" name="student_source_note" value="<?php echo esc_attr($r->student_source_note ?? ''); ?>" placeholder="MEB müdürlüğü / evrak no / kaynak notu"></td>
              <td><input form="<?php echo esc_attr($form_id); ?>" type="date" name="ogrenci_dogrulama_tarihi" value="<?php echo esc_attr($r->ogrenci_dogrulama_tarihi ? substr($r->ogrenci_dogrulama_tarihi,0,10) : ''); ?>"></td>
              <td><select form="<?php echo esc_attr($form_id); ?>" name="oncelik"><?php foreach([''=>'—','cok_yuksek'=>'Çok Yüksek','yuksek'=>'Yüksek','orta'=>'Orta','dusuk'=>'Düşük'] as $k=>$v): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($r->oncelik,$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></td>
              <td><button form="<?php echo esc_attr($form_id); ?>" class="button button-primary">Kaydet</button></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <script>
      const p=document.querySelector('select[name="il"]');
      if(p) p.addEventListener('change',function(){ const d=document.querySelector('select[name="ilce"]'); if(d)d.value=''; this.form.submit(); });
    </script>
    <?php
}

add_action('admin_post_mad_okul_student_update', function() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
    global $wpdb;
    $id=absint($_POST['id'] ?? 0);
    check_admin_referer('mad_okul_student_update_'.$id);
    $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE id=%d LIMIT 1',$id));
    if(!$row) wp_die('Okul bulunamadı.');

    $raw_count = trim((string)($_POST['ogrenci_sayisi'] ?? ''));
    $count = Mad_Okul_Records::count_value($raw_count);
    if (is_wp_error($count)) wp_die(esc_html($count->get_error_message()));
    $date = sanitize_text_field($_POST['ogrenci_dogrulama_tarihi'] ?? '');
    $verified_at = $date ? $date.' 00:00:00' : null;
    $status = sanitize_key($_POST['ogrenci_sayi_durumu'] ?? '');
    if (null === $count && !$status) $status='bulunamadi';

    $result = Mad_Okul_Records::save_student($row,[
        'web_adresi'=>esc_url_raw($_POST['web_adresi'] ?? ''),
        'student_count'=>$count,
        'ogrenci_sayisi'=>$count,
        'ogrenci_sayi_durumu'=>$status,
        'ogrenci_kaynak_turu'=>sanitize_text_field($_POST['ogrenci_kaynak_turu'] ?? ''),
        'ogrenci_kaynak_url'=>esc_url_raw($_POST['ogrenci_kaynak_url'] ?? ''),
        'ogrenci_dogrulama_tarihi'=>$verified_at,
        'oncelik'=>sanitize_key($_POST['oncelik'] ?? ''),
        'updated_at'=>current_time('mysql'),
    ],$_POST['student_data_year'] ?? wp_date('Y'),wp_unslash($_POST['student_source_note'] ?? ''));
    if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));

    $args=['page'=>'mad-okul-students','student_saved'=>1,'il'=>$row->il,'ilce'=>$row->ilce];
    if(!empty($_POST['mmc_program_id'])) $args['mmc_program_id']=absint($_POST['mmc_program_id']);
    wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));
    exit;
});


function mad_okul_is_imam_hatip_middle($row) {
    $name = mad_okul_norm($row['KURUM_ADI'] ?? '');
    $type = mad_okul_norm($row['KURUM_TUR_ADI'] ?? '');
    return strpos(mad_okul_norm($name.' '.$type), 'İMAM HATİP ORTAOKULU') !== false;
}

function mad_okul_should_include($row) {
    $name = mad_okul_norm($row['KURUM_ADI'] ?? '');
    $type = trim($row['KURUM_TUR_ADI'] ?? '');

    $blocked = ['LİSE','MESLEKİ EĞİTİM','ÖZEL EĞİTİM','REHABİLİTASYON','KURS','SÜRÜCÜ','MOTORLU TAŞIT','KİŞİSEL GELİŞİM','HALK EĞİTİM','BİLİM VE SANAT','BİLSEM','REHBERLİK VE ARAŞTIRMA','YURT','ÖĞRETMENEVİ','MİLLİ EĞİTİM MÜDÜRLÜĞÜ','MİLLÎ EĞİTİM MÜDÜRLÜĞÜ'];
    $haystack = mad_okul_norm($name.' '.$type);

    // Operasyon kuralı: İmam Hatip Ortaokulları okul tanıtım/rota havuzuna alınmaz.
    if (mad_okul_is_imam_hatip_middle($row)) return false;
    foreach ($blocked as $keyword) if (strpos($haystack, $keyword)!==false) return false;
    if (preg_match('/\bRAM\b/u',$haystack)) return false;

    foreach (['KREŞ','GÜNDÜZ BAK','ANAOKULU','İLKOKULU','ORTAOKULU'] as $keyword) {
        if (strpos($name, $keyword) !== false) return true;
    }

    $official = ['Anaokulu','İlkokul','Ortaokul','Yatılı Bölge Ortaokulu'];
    $private  = ['Özel Türk Okul Öncesi Kurumu','Özel Türk İlkokulu','Özel Türk Ortaokulu'];
    return in_array($type, $official, true) || in_array($type, $private, true);
}

function mad_okul_store_rural($il,$ilce,$kurum,$adres,$reason) {
    global $wpdb; $hash=mad_okul_hash($il,$ilce,$kurum,$adres);
    return $wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.mad_okul_excluded_table().' (il,ilce,kurum_adi,adres,cikarilma_nedeni,dedupe_hash,created_at) VALUES (%s,%s,%s,%s,%s,%s,%s)',sanitize_text_field($il),sanitize_text_field($ilce),sanitize_text_field($kurum),sanitize_textarea_field($adres),sanitize_text_field($reason),$hash,current_time('mysql')));
}

function mad_okul_missing_page() {
    if (!current_user_can('manage_options')) return; global $wpdb;
    $mmc_program_id=absint($_GET['mmc_program_id'] ?? 0);
    $where="(adres='' OR durum='Adres Eksik')";
    if($mmc_program_id){
        $ctx=Mad_Okul_Operations::mmc_program_context($mmc_program_id);
        if(is_wp_error($ctx)){echo '<div class="notice notice-error"><p>'.esc_html($ctx->get_error_message()).'</p></div>';return;}
        $where=$wpdb->prepare($where.' AND il=%s AND ilce=%s',mad_okul_place_title($ctx->program->province_name),mad_okul_place_title($ctx->program->district_name));
    }
    $rows=$wpdb->get_results('SELECT * FROM '.mad_okul_table()." WHERE $where ORDER BY il,ilce,kurum_adi LIMIT 1000");
    ?><div class="wrap mad-okul-wrap"><h1>Adresi Eksik Kurumlar</h1><?php if(!empty($_GET['saved'])): ?><div class="notice notice-success inline"><p>Adres kaydedildi.</p></div><?php endif; ?><p><?php echo count($rows); ?> kayıt listeleniyor. Adresi buradan tamamlayınca kurum rota işlemlerine hazır hâle gelir.</p><table class="widefat striped"><thead><tr><th>İl</th><th>İlçe</th><th>Kurum</th><th>Adres Gir</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo esc_html($r->il); ?></td><td><?php echo esc_html($r->ilce); ?></td><td><?php echo esc_html($r->kurum_adi); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-address-form"><input type="hidden" name="action" value="mad_okul_save_address"><input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"><?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?><?php wp_nonce_field('mad_okul_save_address_'.$r->id); ?><input required class="regular-text" name="adres" placeholder="Açık adresi yazın"><button class="button button-primary">Adresi Kaydet</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php
}

add_action('admin_post_mad_okul_save_address', function() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
    $id=absint($_POST['id'] ?? 0); check_admin_referer('mad_okul_save_address_'.$id); global $wpdb;
    $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.mad_okul_table().' WHERE id=%d',$id));
    $adres=sanitize_textarea_field($_POST['adres'] ?? '');
    if (!$row || !$adres) wp_die('Kurum veya adres bulunamadı.');
    $wpdb->update(mad_okul_table(),['adres'=>$adres,'durum'=>'Bekliyor','dedupe_hash'=>mad_okul_hash($row->il,$row->ilce,$row->kurum_adi,$adres),'updated_at'=>current_time('mysql')],['id'=>$id]);
    $args=['page'=>'mad-okul-missing','saved'=>1]; if(!empty($_POST['mmc_program_id'])) $args['mmc_program_id']=absint($_POST['mmc_program_id']);
    wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit;
});

function mad_okul_rural_page() {
    if (!current_user_can('manage_options')) return; global $wpdb;
    $mmc_program_id=absint($_GET['mmc_program_id'] ?? 0);
    $where='1=1';
    if($mmc_program_id){
        $ctx=Mad_Okul_Operations::mmc_program_context($mmc_program_id);
        if(is_wp_error($ctx)){echo '<div class="notice notice-error"><p>'.esc_html($ctx->get_error_message()).'</p></div>';return;}
        $where=$wpdb->prepare('il=%s AND ilce=%s',mad_okul_place_title($ctx->program->province_name),mad_okul_place_title($ctx->program->district_name));
    }
    $rows=$wpdb->get_results('SELECT * FROM '.mad_okul_excluded_table()." WHERE $where ORDER BY il,ilce,kurum_adi LIMIT 2000");
    ?><div class="wrap mad-okul-wrap"><h1>Kırsal Çıkarılanlar</h1><p>Burada yalnız hedef kurum türünde olduğu hâlde açık kırsal ifade nedeniyle ana listeden çıkarılan kayıtlar bulunur.</p><table class="widefat striped"><thead><tr><th>İl</th><th>İlçe</th><th>Kurum</th><th>Adres</th><th>Çıkarılma Nedeni</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?php echo esc_html($r->il); ?></td><td><?php echo esc_html($r->ilce); ?></td><td><?php echo esc_html($r->kurum_adi); ?></td><td><?php echo esc_html($r->adres); ?></td><td><?php echo esc_html($r->cikarilma_nedeni); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
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

function mad_okul_header_key($value) {
    $value = mad_okul_norm($value);
    $value = strtr($value, ['İ'=>'I','Ş'=>'S','Ğ'=>'G','Ü'=>'U','Ö'=>'O','Ç'=>'C']);
    return preg_replace('/[^A-Z0-9]+/', '_', trim($value));
}

function mad_okul_canonical_row($row) {
    $aliases = [
        'IL_ADI' => ['IL_ADI','IL','SEHIR'],
        'ILCE_ADI' => ['ILCE_ADI','ILCE'],
        'KURUM_ADI' => ['KURUM_ADI','OKUL_ADI','KURUM','OKUL','KURUM_ADLARI'],
        'KURUM_TUR_ADI' => ['KURUM_TUR_ADI','KURUM_TURU','OKUL_TURU','TUR','KAYNAK_TURU'],
        'EGITIM_KADEMESI' => ['EGITIM_KADEMESI','KADEMELER','KADEME'],
        'ADRES' => ['ADRES','ACIK_ADRES','KURUM_ADRESI','OKUL_ADRESI'],
        'TEL' => ['TEL','TELEFON','PHONE'],
        'WEB_ADRES' => ['WEB_ADRES','WEB_ADRESI','WEB_SITESI','WEB_SITELERI','WEBSITE'],
        'CAMPUS_KEY' => ['CAMPUS_KEY','KAMPUS_KEY','KAMPUS_ANAHTARI'],
        'CAMPUS_NAME' => ['CAMPUS_NAME','KAMPUS_ADI','ZIYARET_NOKTASI_KAMPUS','ZIYARET_NOKTASI'],
        'OGRENCI_SAYISI' => ['OGRENCI_SAYISI','OGRENCI_ADEDI','OGRENCI','STUDENT_COUNT','STUDENTS','OGRENCI_SAYISI_WEB','KAMPUS_TOPLAM_OGRENCI'],
        'OGRENCI_SAYI_DURUMU' => ['OGRENCI_SAYI_DURUMU','SAYI_DURUMU','OGRENCI_VERI_DURUMU'],
        'OGRENCI_KAYNAK_TURU' => ['OGRENCI_KAYNAK_TURU','KAYNAK_TURU'],
        'OGRENCI_KAYNAK_URL' => ['OGRENCI_KAYNAK_URL','OGRENCI_SAYISI_KAYNAGI','KAYNAK_ERISIM'],
        'OGRENCI_DOGRULAMA_TARIHI' => ['OGRENCI_DOGRULAMA_TARIHI','ERISIM_TARIHI'],
        'ONCELIK' => ['ONCELIK','PRIORITY'],
        'VERI_YILI' => ['VERI_YILI','DATA_YEAR','YIL'],
    ];
    $normalized = [];
    foreach ($row as $key=>$value) $normalized[mad_okul_header_key($key)] = trim((string)$value);
    $out = [];
    foreach ($aliases as $target=>$keys) {
        $out[$target] = '';
        foreach ($keys as $key) if (isset($normalized[$key]) && $normalized[$key] !== '') { $out[$target]=$normalized[$key]; break; }
    }
    return $out;
}

function mad_okul_xlsx_rows($path) {
    if (!class_exists('ZipArchive')) return new WP_Error('xlsx_zip', 'Sunucuda ZIP desteği olmadığı için XLSX açılamadı. Dosyayı CSV olarak kaydedip yükleyin.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return new WP_Error('xlsx_open', 'XLSX dosyası açılamadı.');
    $shared = [];
    $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($shared_xml) {
        $xml = simplexml_load_string($shared_xml);
        if ($xml) foreach ($xml->si as $si) {
            $parts=[]; foreach ($si->xpath('.//t') as $t) $parts[]=(string)$t;
            $shared[]=implode('', $parts);
        }
    }
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheet_xml) return new WP_Error('xlsx_sheet', 'XLSX içinde ilk çalışma sayfası bulunamadı.');
    $xml = simplexml_load_string($sheet_xml);
    if (!$xml) return new WP_Error('xlsx_xml', 'XLSX çalışma sayfası okunamadı.');
    $grid=[];
    foreach ($xml->sheetData->row as $row) {
        $values=[];
        foreach ($row->c as $cell) {
            $ref=(string)$cell['r']; preg_match('/^[A-Z]+/', $ref, $m);
            $letters=$m[0] ?? 'A'; $index=0;
            for($i=0;$i<strlen($letters);$i++) $index=$index*26+(ord($letters[$i])-64);
            $type=(string)$cell['t'];
            if ($type==='inlineStr') $value=(string)$cell->is->t;
            else { $raw=(string)$cell->v; $value=$type==='s' ? ($shared[(int)$raw] ?? '') : $raw; }
            $values[$index-1]=trim($value);
        }
        if ($values) { ksort($values); $grid[]=$values; }
    }
    $header_index=null; $headers=[];
    foreach ($grid as $i=>$row) foreach ($row as $value) if (in_array(mad_okul_header_key($value), ['KURUM_ADI','OKUL_ADI'], true)) { $header_index=$i; break 2; }
    if ($header_index===null) return new WP_Error('xlsx_header', 'Kurum/okul adı sütunu bulunamadı.');
    $max=max(array_keys($grid[$header_index]));
    for($i=0;$i<=$max;$i++) $headers[$i]=$grid[$header_index][$i] ?? '';
    $out=[];
    for($i=$header_index+1;$i<count($grid);$i++) {
        $assoc=[]; foreach($headers as $n=>$header) if($header!=='') $assoc[$header]=$grid[$i][$n] ?? '';
        $canon=mad_okul_canonical_row($assoc); if($canon['KURUM_ADI']!=='') $out[]=$canon;
    }
    return $out;
}

function mad_okul_import_page() {
    if (!current_user_can('manage_options')) return;
    $errors = get_transient('mad_okul_import_errors_'.get_current_user_id());
    delete_transient('mad_okul_import_errors_'.get_current_user_id());
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>MEBBİS Listesi İçe Aktar</h1>
      <p>MEBBİS'ten indirdiğiniz <strong>.xls, .xlsx veya .csv</strong> dosyalarını aynı anda yükleyebilirsiniz. Sistem kreş/gündüz bakımevi, anaokulu, ilkokul ve ortaokulları alır; <strong>İmam Hatip Ortaokullarını</strong>, kırsal açık adresleri, hedef dışı kurumları ve mükerrerleri dışarıda bırakır. Dosyada telefon, web sitesi veya öğrenci sayısı alanı varsa bunlar da okul ana kaydına işlenir.</p>
      <?php if (isset($_GET['raw'])): ?>
        <div class="notice notice-success is-dismissible"><p>
          Ham: <?php echo (int)($_GET['raw'] ?? 0); ?> ·
          İşlenen/eklenen: <?php echo (int)($_GET['imported'] ?? 0); ?> ·
          <strong>İmam Hatip Ortaokulu çıkarılan: <?php echo (int)($_GET['imam_hatip'] ?? 0); ?></strong> ·
          Kırsal çıkarılan: <?php echo (int)($_GET['rural'] ?? 0); ?> ·
          Diğer hedef dışı: <?php echo (int)($_GET['non_target'] ?? 0); ?> ·
          Adresi eksik: <?php echo (int)($_GET['missing'] ?? 0); ?> ·
          Tahmini fiziksel ziyaret noktası: <?php echo (int)($_GET['visit_points'] ?? 0); ?>.
        </p></div>
      <?php endif; ?>
      <?php if ($errors): ?><div class="notice notice-error"><p><?php echo esc_html(implode(' ', (array)$errors)); ?></p></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mad-upload-box">
        <input type="hidden" name="action" value="mad_okul_import">
        <?php wp_nonce_field('mad_okul_import'); ?>
        <input type="file" name="files[]" accept=".xls,.xlsx,.csv" multiple required>
        <button class="button button-primary button-hero">Dosyaları İşle ve Ekle</button>
      </form>
      <p><small>Not: Mevcut okul verileri eklenti ile birlikte gelir. Bu ekran yeni şehir/ilçe MEBBİS listelerini sonraki dönemlerde eklemek içindir.</small></p>
    </div>
    <?php
}

add_action('admin_post_mad_okul_import', function() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz işlem');
    check_admin_referer('mad_okul_import');

    $imported=0; $skipped=0; $missing=0; $rural=0; $non_target=0; $imam_hatip=0; $raw=0; $errors=[]; $visit_keys=[];
    $names = $_FILES['files']['name'] ?? [];
    $tmps  = $_FILES['files']['tmp_name'] ?? [];
    if (!is_array($names)) { $names=[$names]; $tmps=[$tmps]; }

    foreach ($names as $i=>$name) {
        if (empty($tmps[$i]) || !is_uploaded_file($tmps[$i])) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext==='csv') $rows=mad_okul_csv_rows($tmps[$i]);
        elseif ($ext==='xlsx') $rows=mad_okul_xlsx_rows($tmps[$i]);
        else $rows=mad_okul_html_xls_rows($tmps[$i]);
        if (is_wp_error($rows)) { $errors[]=$rows->get_error_message(); continue; }

        foreach ($rows as $r) {
            $raw++;
            $r=mad_okul_canonical_row($r);
            $il=$r['IL_ADI']; $ilce=$r['ILCE_ADI'];
            $kurum=$r['KURUM_ADI']; $adres=$r['ADRES'];
            if (mad_okul_is_imam_hatip_middle($r)) { $skipped++; $imam_hatip++; continue; }
            if (!mad_okul_should_include($r)) { $skipped++; $non_target++; continue; }
            if (mad_okul_is_rural($kurum,$adres)) { mad_okul_store_rural($il,$ilce,$kurum,$adres,'Açık kırsal adres ifadesi'); $skipped++; $rural++; continue; }
            $visit_key = $adres
                ? mad_okul_norm($il).'|'.mad_okul_norm($ilce).'|ADDR|'.mad_okul_norm($adres)
                : mad_okul_norm($il).'|'.mad_okul_norm($ilce).'|SCHOOL|'.mad_okul_norm($kurum);
            $visit_keys[$visit_key] = true;
            $res=mad_okul_insert_school($il,$ilce,$kurum,$adres,[
                'kurum_turu'               => $r['KURUM_TUR_ADI'] ?? '',
                'egitim_kademesi'           => $r['EGITIM_KADEMESI'] ?? '',
                'telefon'                   => $r['TEL'] ?? '',
                'web_adresi'                => $r['WEB_ADRES'] ?? '',
                'campus_key'                => $r['CAMPUS_KEY'] ?? '',
                'campus_name'               => $r['CAMPUS_NAME'] ?? '',
                'ogrenci_sayisi'            => $r['OGRENCI_SAYISI'] ?? '',
                'ogrenci_sayi_durumu'       => $r['OGRENCI_SAYI_DURUMU'] ?? '',
                'ogrenci_kaynak_turu'       => $r['OGRENCI_KAYNAK_TURU'] ?? '',
                'ogrenci_kaynak_url'        => $r['OGRENCI_KAYNAK_URL'] ?? '',
                'ogrenci_dogrulama_tarihi'  => $r['OGRENCI_DOGRULAMA_TARIHI'] ?? '',
                'oncelik'                   => $r['ONCELIK'] ?? '',
                'veri_yili'                 => $r['VERI_YILI'] ?? '',
            ]);
            if ($res) {
                $imported++;
                if (!$adres) {
                    $missing++;
                    global $wpdb;
                    $wpdb->update(mad_okul_table(), ['durum'=>'Adres Eksik','updated_at'=>current_time('mysql')], ['dedupe_hash'=>mad_okul_hash($il,$ilce,$kurum,$adres)]);
                }
            } else $skipped++;
        }
    }

    if ($errors) set_transient('mad_okul_import_errors_'.get_current_user_id(), $errors, 120);
    wp_safe_redirect(add_query_arg([
        'page'=>'mad-okul-import',
        'raw'=>$raw,
        'imported'=>$imported,
        'skipped'=>$skipped,
        'missing'=>$missing,
        'rural'=>$rural,
        'non_target'=>$non_target,
        'imam_hatip'=>$imam_hatip,
        'visit_points'=>count($visit_keys),
    ], admin_url('admin.php')));
    exit;
});

function mad_okul_route_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table=mad_okul_table();
    $mmc_program_id=absint($_GET['mmc_program_id'] ?? $_POST['mmc_program_id'] ?? 0);
    $mmc_ctx=null;
    $route_program=null;
    if($mmc_program_id && class_exists('Mad_Okul_Operations') && method_exists('Mad_Okul_Operations','mmc_program_context')){
        $mmc_ctx=Mad_Okul_Operations::mmc_program_context($mmc_program_id);
        if(!is_wp_error($mmc_ctx) && method_exists('Mad_Okul_Operations','ensure_mmc_bridge')){
            $linked_program=Mad_Okul_Operations::ensure_mmc_bridge($mmc_program_id);
            if(!is_wp_error($linked_program)) $route_program=$linked_program;
        }
    }

    // MMC Program ID varsa il/ilçe tek kaynaktan gelir; yoksa eski bağımsız filtre korunur.
    if($mmc_ctx && !is_wp_error($mmc_ctx)){
        $il=mad_okul_place_title($mmc_ctx->program->province_name);
        $ilce=mad_okul_place_title($mmc_ctx->program->district_name);
    } else {
        $il=mad_okul_place_title(sanitize_text_field(wp_unslash($_GET['il'] ?? '')));
        $ilce=mad_okul_place_title(sanitize_text_field(wp_unslash($_GET['ilce'] ?? '')));
    }
    [$ils,$ilceler]=mad_okul_filter_options($il);

    // İl değiştiyse eski/uyumsuz ilçe seçimini taşımayalım.
    if(!$mmc_program_id && $ilce && !in_array($ilce,$ilceler,true)) $ilce='';

    // Seçilen ilde tek ilçe varsa (ör. Kırıkkale/Merkez) otomatik seç.
    if($il && !$ilce && count($ilceler)===1) $ilce=(string)$ilceler[0];

    $durum=sanitize_text_field(wp_unslash($_GET['durum'] ?? ''));
    $params=[]; $w=['1=1'];
    if($il){$w[]='il=%s';$params[]=$il;}
    if($ilce){$w[]='ilce=%s';$params[]=$ilce;}
    if($durum){$w[]='durum=%s';$params[]=$durum;}
    $where=implode(' AND ',$w);
    $rows=[];
    if($il && $ilce){
        $sql="SELECT * FROM $table WHERE $where ORDER BY kurum_adi LIMIT 120";
        $rows=$params ? $wpdb->get_results($wpdb->prepare($sql,$params)) : $wpdb->get_results($sql);
    }

    $selected=array_map('absint', $_POST['school_ids'] ?? []);
    $start=sanitize_text_field(wp_unslash($_POST['start_address'] ?? ''));

    // MMC programında doğrulanmış rota salonu her zaman başlangıç kaynağıdır.
    if($mmc_ctx && !is_wp_error($mmc_ctx) && !empty($mmc_ctx->venue)){
        $start=trim($mmc_ctx->venue->venue_name.', '.$mmc_ctx->venue->address,', ');
    }

    // Geriye uyumluluk: önce Okul Tanıtım programı, yoksa MMC kesin salonu.
    if(!$mmc_program_id && $il && $ilce){
        $programs_table=Mad_Okul_Operations::programs_table();
        $programs_exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$programs_table));
        if($programs_exists===$programs_table){
            $p=$wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $programs_table
                 WHERE il=%s AND ilce=%s AND durum='Aktif'
                 ORDER BY CASE WHEN etkinlik_tarihi IS NULL THEN 2 WHEN etkinlik_tarihi>=CURDATE() THEN 0 ELSE 1 END,
                          ABS(DATEDIFF(COALESCE(etkinlik_tarihi,CURDATE()),CURDATE())), id DESC
                 LIMIT 1",
                $il,$ilce
            ));
            if($p) {
                $route_program=$p;
                if(!$start) $start=trim($p->salon_adi.', '.$p->salon_adresi,', ');
            }
        }

        if(!$start){
            $mmc_programs=$wpdb->prefix.'mmc_programs';
            $mmc_program_venues=$wpdb->prefix.'mmc_program_venues';
            $mmc_venues=$wpdb->prefix.'mmc_venues';
            $tables_ok=true;
            foreach([$mmc_programs,$mmc_program_venues,$mmc_venues] as $t){
                if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t))!==$t){$tables_ok=false;break;}
            }
            if($tables_ok){
                $p=$wpdb->get_row($wpdb->prepare(
                    "SELECT v.venue_name,v.address
                     FROM $mmc_programs p
                     INNER JOIN $mmc_program_venues pv ON pv.program_id=p.id
                     INNER JOIN $mmc_venues v ON v.id=pv.venue_id
                     WHERE p.province_name=%s AND p.district_name=%s
                       AND pv.is_selected=1 AND pv.allocation_status='approved'
                       AND p.status<>'cancelled'
                     ORDER BY CASE WHEN p.planned_date IS NULL THEN 2 WHEN p.planned_date>=CURDATE() THEN 0 ELSE 1 END,
                              ABS(DATEDIFF(COALESCE(p.planned_date,CURDATE()),CURDATE())), p.id DESC
                     LIMIT 1",
                    $il,$ilce
                ));
                if($p) $start=trim($p->venue_name.', '.$p->address,', ');
            }
        }
    }
    $route_links=[];
    $route_error='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_admin_referer('mad_okul_route');
        $available_ids=array_map(function($row){ return (int)$row->id; },(array)$rows);
        $selected=array_values(array_intersect($selected,$available_ids));
        if(!$selected){
            $route_error='Rotaya eklenecek en az bir okul seçin.';
        }elseif(!$start){
            $route_error='Rota başlangıç salonu bağlı değil. Program ve Salonlar menüsünde bu programa bağlı salonu seçin.';
        }else{
            $ids=implode(',',$selected);
            if($mmc_program_id && $mmc_ctx && !is_wp_error($mmc_ctx)){
                $linked=Mad_Okul_Operations::ensure_mmc_bridge($mmc_program_id);
                if(is_wp_error($linked)){
                    $route_error=$linked->get_error_message();
                }else{
                    $route_program=$linked;
                }
            }
            if(!$route_error){
                $chosen=$wpdb->get_results("SELECT * FROM $table WHERE id IN ($ids) ORDER BY FIELD(id,$ids)");

                // Rota Planı/PDF ekranı program_id üzerinden okur. Seçimi aynı programa bağla
                // ve seçili sırayı kaydet; personel/durum gibi saha alanlarına dokunma.
                if($route_program){
                    foreach((array)$chosen as $order=>$school){
                        $payload=[
                            'program_id'=>(int)$route_program->id,
                            'route_order'=>$order+1,
                            'updated_at'=>current_time('mysql'),
                        ];
                        if(!$school->route_group) $payload['route_group']='A';
                        if($mmc_program_id) $payload['mmc_program_id']=$mmc_program_id;
                        $wpdb->update($table,$payload,['id'=>(int)$school->id]);
                    }
                }

                foreach(array_chunk((array)$chosen,8) as $n=>$chunk){
                    $dest=end($chunk);
                    $middle=$chunk; array_pop($middle);
                    $waypoints=[];
                    foreach($middle as $school) $waypoints[]=$school->adres.', '.$school->ilce.', '.$school->il;
                    $url='https://www.google.com/maps/dir/?api=1&origin='.rawurlencode($start).
                         '&destination='.rawurlencode($dest->adres.', '.$dest->ilce.', '.$dest->il).
                         '&travelmode=driving';
                    if($waypoints) $url.='&waypoints='.implode('%7C',array_map('rawurlencode',$waypoints));
                    $route_links[]=['url'=>$url,'number'=>$n+1,'count'=>count($chunk)];
                }
            }
        }
    }
    ?>
    <div class="wrap mad-okul-wrap">
      <h1>Google Maps Rota Oluştur</h1>
      <p>İl/ilçe ve durum seçin, rotaya girecek okulları işaretleyin. Sistem seçilen noktaları Google Maps bağlantılarına böler.</p>
      <?php if($mmc_ctx && !is_wp_error($mmc_ctx)): ?>
        <div class="notice notice-info inline"><p><strong>Aktif MMC Programı:</strong> <?php echo esc_html($mmc_ctx->program->program_code.' · '.$il.' / '.$ilce); ?> · MMC ID <?php echo (int)$mmc_program_id; ?></p></div>
      <?php endif; ?>
      <form method="get" class="mad-filter">
        <input type="hidden" name="page" value="mad-okul-route">
        <?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?>
        <?php if($mmc_program_id): ?>
          <input type="hidden" name="il" value="<?php echo esc_attr($il); ?>">
          <input type="hidden" name="ilce" value="<?php echo esc_attr($ilce); ?>">
        <?php endif; ?>
        <select name="il" id="mad-route-il" <?php disabled($mmc_program_id); ?>><option value="">İl Seç</option><?php foreach($ils as $x): ?><option <?php selected($il,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="ilce" id="mad-route-ilce" <?php disabled(!$il || $mmc_program_id); ?>><option value=""><?php echo $il ? 'İlçe Seç' : 'Önce İl Seç'; ?></option><?php foreach($ilceler as $x): ?><option <?php selected($ilce,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?></select>
        <select name="durum">
          <option value="" <?php selected($durum,''); ?>>Tüm Durumlar</option>
          <?php foreach(['Bekliyor','Adres Eksik','Atandı','Ziyaret Edildi','Afiş Bırakıldı','Görüşüldü','Tekrar Gidilecek'] as $x): ?><option <?php selected($durum,$x); ?>><?php echo esc_html($x); ?></option><?php endforeach; ?>
        </select>
        <button class="button">Listeyi Getir</button>
      </form>

      <?php if(!$il): ?><div class="notice notice-info inline"><p>Rota listesini görmek için önce il seçin.</p></div>
      <?php elseif(!$ilceler): ?><div class="notice notice-warning inline"><p><?php echo esc_html($il); ?> için okul/ilçe kaydı bulunamadı. Önce MEBBİS listesini içe aktarın.</p></div>
      <?php elseif(!$ilce): ?><div class="notice notice-info inline"><p><?php echo esc_html($il); ?> için ilçe seçin.</p></div>
      <?php elseif(!$rows): ?><div class="notice notice-warning inline"><p><?php echo esc_html($il.' / '.$ilce); ?> için seçili durumda okul bulunamadı.</p></div><?php endif; ?>

      <?php if($mmc_ctx && !is_wp_error($mmc_ctx) && !$mmc_ctx->venue): ?>
        <div class="notice notice-warning inline"><p>Bu programa kesin salon bağlanmamış. Eski koordinat başlangıç noktası olarak kullanılmıyor; rota için salonun tam adresini girin. <a href="<?php echo esc_url(add_query_arg(['page'=>'mmc-venue-flow','program_id'=>$mmc_program_id],admin_url('admin.php'))); ?>">MMC Salon bağlantısını aç</a></p></div>
      <?php endif; ?>
      <?php if($route_error): ?><div id="mad-route-result" class="notice notice-error inline"><p><?php echo esc_html($route_error); ?></p></div><?php endif; ?>
      <?php if($route_links): ?><div id="mad-route-result" class="mad-routes"><h2>Oluşturulan Rotalar</h2>
        <?php foreach($route_links as $route): ?><p><a class="button button-primary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($route['url']); ?>">Rota <?php echo (int)$route['number']; ?> — <?php echo (int)$route['count']; ?> okul Google Maps’te Aç</a></p><?php endforeach; ?>
        <?php if($route_program): ?>
          <p><a class="button" href="<?php echo esc_url(Mad_Okul_Records::export_url('route',$mmc_program_id,(int)$route_program->id)); ?>">Rota Excel İndir</a> <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(array_filter(['page'=>'mad-okul-route-plan','program_id'=>(int)$route_program->id,'mmc_program_id'=>$mmc_program_id]),admin_url('admin.php'))); ?>">Rota Planını / PDF Olarak Getir</a></p>
          <p class="description">PDF kaynağı: <?php echo esc_html($route_program->program_adi); ?>. Seçtiğiniz kurumlar bu programa bağlandı.</p>
        <?php else: ?>
          <div class="notice notice-warning inline"><p>Google Maps rotası oluşturuldu; ancak PDF için eşleşen bir program bulunamadı. Önce Program ve Salonlar ekranında bu il/ilçe için program oluşturun veya MMC programından gelin.</p></div>
        <?php endif; ?>
        <p class="description">Bağlantıya tıklayarak rotayı Google Maps’te açın.</p></div><?php endif; ?>

      <form method="post">
        <?php wp_nonce_field('mad_okul_route'); ?>
        <?php if($mmc_program_id): ?><input type="hidden" name="mmc_program_id" value="<?php echo (int)$mmc_program_id; ?>"><?php endif; ?>
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

    </div>
    <script>
      document.getElementById('mad-route-result')?.scrollIntoView({block:'center'});
      document.addEventListener('change',function(e){
        if(e.target.id==='mad-all'){
          document.querySelectorAll('input[name="school_ids[]"]').forEach(x=>x.checked=e.target.checked);
          return;
        }
        if(e.target.id==='mad-route-il'){
          const form=e.target.form;
          const ilce=form ? form.querySelector('#mad-route-ilce') : null;
          if(ilce) ilce.value='';
          if(form) form.submit();
          return;
        }
        if(e.target.id==='mad-route-ilce' && e.target.value && e.target.form){
          e.target.form.submit();
        }
      });
    </script>
    <?php
}
