<?php
/**
 * Plugin Name: Madagaskar Veri Ambarı - Nüfus ve Eğitim
 * Description: 2025 il/ilçe nüfus, MEB 2024/25 il geneli eğitim ve Okul Tanıtım ilçe okul verilerini Madagaskar Veri Ambarına bağlar.
 * Version: 1.3.0
 * Author: Madagaskar Sirki
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MMC_POPULATION_VERSION', '1.3.0');
define('MMC_POPULATION_DATA_YEAR', 2025);
define('MMC_POPULATION_SOURCE_NAME', 'TurkiyeAPI / TÜİK MEDAS');
define('MMC_POPULATION_PROVINCES_URL', 'https://raw.githubusercontent.com/ubeydeozdmr/turkiye-api/main/datasets/2025/provinces.json');
define('MMC_POPULATION_DISTRICTS_URL', 'https://raw.githubusercontent.com/ubeydeozdmr/turkiye-api/main/datasets/2025/districts.json');

require_once plugin_dir_path(__FILE__) . 'includes/mmc-preparation-bridge.php';
require_once plugin_dir_path(__FILE__) . 'includes/mmc-education-data.php';

function mmc_population_table_provinces() {
    global $wpdb;
    return $wpdb->prefix . 'mmc_population_provinces';
}

function mmc_population_table_districts() {
    global $wpdb;
    return $wpdb->prefix . 'mmc_population_districts';
}

function mmc_population_normalize_key($value) {
    $value = trim((string) $value);
    $map = [
        'Ç' => 'c', 'ç' => 'c',
        'Ğ' => 'g', 'ğ' => 'g',
        'İ' => 'i', 'I' => 'i', 'ı' => 'i',
        'Ö' => 'o', 'ö' => 'o',
        'Ş' => 's', 'ş' => 's',
        'Ü' => 'u', 'ü' => 'u',
    ];
    $value = strtr($value, $map);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
    return trim((string) $value, '-');
}

function mmc_population_install() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $provinces = mmc_population_table_provinces();
    $districts = mmc_population_table_districts();

    $sql_provinces = "CREATE TABLE {$provinces} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source_id int(10) unsigned NOT NULL,
        name varchar(120) NOT NULL,
        name_key varchar(120) NOT NULL,
        slug varchar(120) NOT NULL,
        population bigint(20) unsigned NOT NULL DEFAULT 0,
        region varchar(120) NULL,
        data_year smallint(5) unsigned NOT NULL,
        source_name varchar(190) NOT NULL,
        source_url text NULL,
        imported_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_year (source_id, data_year),
        KEY name_year (name_key, data_year)
    ) {$charset};";

    $sql_districts = "CREATE TABLE {$districts} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source_id int(10) unsigned NOT NULL,
        province_source_id int(10) unsigned NOT NULL,
        province_name_key varchar(120) NOT NULL,
        name varchar(120) NOT NULL,
        name_key varchar(120) NOT NULL,
        slug varchar(120) NOT NULL,
        population bigint(20) unsigned NOT NULL DEFAULT 0,
        area_km2 decimal(12,2) NULL,
        data_year smallint(5) unsigned NOT NULL,
        source_name varchar(190) NOT NULL,
        source_url text NULL,
        imported_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_year (source_id, data_year),
        KEY province_year (province_source_id, data_year),
        KEY province_name_year (province_name_key, name_key, data_year)
    ) {$charset};";

    dbDelta($sql_provinces);
    dbDelta($sql_districts);

    update_option('mmc_population_db_version', MMC_POPULATION_VERSION);
}
register_activation_hook(__FILE__, 'mmc_population_install');

function mmc_population_fetch_json($url) {
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'redirection' => 3,
        'headers' => [
            'Accept' => 'application/json',
            'User-Agent' => 'MadagaskarSirkiPopulationImporter/' . MMC_POPULATION_VERSION,
        ],
    ]);

    if (is_wp_error($response)) {
        throw new RuntimeException($response->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        throw new RuntimeException('Veri kaynağı HTTP ' . $code . ' döndürdü.');
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) {
        throw new RuntimeException('Veri kaynağı geçerli JSON döndürmedi.');
    }

    return $data;
}

function mmc_population_import_2025() {
    global $wpdb;

    if (get_option('mmc_population_db_version') !== MMC_POPULATION_VERSION) {
        mmc_population_install();
    }

    $provinces = mmc_population_fetch_json(MMC_POPULATION_PROVINCES_URL);
    $districts = mmc_population_fetch_json(MMC_POPULATION_DISTRICTS_URL);

    if (count($provinces) !== 81) {
        throw new RuntimeException('İl veri seti beklenen 81 kaydı içermiyor.');
    }

    if (count($districts) !== 973) {
        throw new RuntimeException('İlçe veri seti beklenen 973 kaydı içermiyor.');
    }

    $province_names = [];
    foreach ($provinces as $row) {
        if (!isset($row['id'], $row['name'], $row['population'])) {
            throw new RuntimeException('İl veri setinde zorunlu alan eksik.');
        }
        $province_names[(int) $row['id']] = (string) $row['name'];
    }

    foreach ($districts as $row) {
        if (!isset($row['id'], $row['provinceId'], $row['name'], $row['population'])) {
            throw new RuntimeException('İlçe veri setinde zorunlu alan eksik.');
        }
        if (!isset($province_names[(int) $row['provinceId']])) {
            throw new RuntimeException('İlçe kaydının bağlı olduğu il bulunamadı: ' . (string) $row['name']);
        }
    }

    $now = current_time('mysql');
    $province_table = mmc_population_table_provinces();
    $district_table = mmc_population_table_districts();

    $wpdb->query('START TRANSACTION');

    try {
        foreach ($provinces as $row) {
            $region = '';
            if (isset($row['region']['tr'])) {
                $region = (string) $row['region']['tr'];
            }

            $ok = $wpdb->replace(
                $province_table,
                [
                    'source_id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'name_key' => mmc_population_normalize_key($row['name']),
                    'slug' => isset($row['slug']) ? (string) $row['slug'] : mmc_population_normalize_key($row['name']),
                    'population' => (int) $row['population'],
                    'region' => $region,
                    'data_year' => MMC_POPULATION_DATA_YEAR,
                    'source_name' => MMC_POPULATION_SOURCE_NAME,
                    'source_url' => MMC_POPULATION_PROVINCES_URL,
                    'imported_at' => $now,
                ],
                ['%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
            );

            if ($ok === false) {
                throw new RuntimeException('İl kaydı yazılamadı: ' . $wpdb->last_error);
            }
        }

        foreach ($districts as $row) {
            $province_id = (int) $row['provinceId'];
            $area = null;
            if (isset($row['area']['value']) && is_numeric($row['area']['value'])) {
                $area = (float) $row['area']['value'];
            }

            $data = [
                'source_id' => (int) $row['id'],
                'province_source_id' => $province_id,
                'province_name_key' => mmc_population_normalize_key($province_names[$province_id]),
                'name' => (string) $row['name'],
                'name_key' => mmc_population_normalize_key($row['name']),
                'slug' => isset($row['slug']) ? (string) $row['slug'] : mmc_population_normalize_key($row['name']),
                'population' => (int) $row['population'],
                'area_km2' => $area,
                'data_year' => MMC_POPULATION_DATA_YEAR,
                'source_name' => MMC_POPULATION_SOURCE_NAME,
                'source_url' => MMC_POPULATION_DISTRICTS_URL,
                'imported_at' => $now,
            ];

            $ok = $wpdb->replace(
                $district_table,
                $data,
                ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%s', '%s', '%s']
            );

            if ($ok === false) {
                throw new RuntimeException('İlçe kaydı yazılamadı: ' . $wpdb->last_error);
            }
        }

        $wpdb->query('COMMIT');

        update_option('mmc_population_last_import', [
            'year' => MMC_POPULATION_DATA_YEAR,
            'province_count' => count($provinces),
            'district_count' => count($districts),
            'imported_at' => $now,
            'source' => MMC_POPULATION_SOURCE_NAME,
        ], false);

        return [
            'province_count' => count($provinces),
            'district_count' => count($districts),
            'imported_at' => $now,
        ];
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}

function mmc_population_get_province($province_name, $year = MMC_POPULATION_DATA_YEAR) {
    global $wpdb;

    $table = mmc_population_table_provinces();
    $key = mmc_population_normalize_key($province_name);

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT source_id, name, slug, population, region, data_year, source_name, imported_at
             FROM {$table}
             WHERE name_key = %s AND data_year = %d
             LIMIT 1",
            $key,
            (int) $year
        ),
        ARRAY_A
    );
}

function mmc_population_get_district($province_name, $district_name, $year = MMC_POPULATION_DATA_YEAR) {
    global $wpdb;

    $table = mmc_population_table_districts();
    $province_key = mmc_population_normalize_key($province_name);
    $district_key = mmc_population_normalize_key($district_name);

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT source_id, province_source_id, name, slug, population, area_km2, data_year, source_name, imported_at
             FROM {$table}
             WHERE province_name_key = %s AND name_key = %s AND data_year = %d
             LIMIT 1",
            $province_key,
            $district_key,
            (int) $year
        ),
        ARRAY_A
    );
}

function mmc_population_get_target_summary($province_name, array $district_names, $year = MMC_POPULATION_DATA_YEAR) {
    $total = 0;
    $covered = 0;
    $missing = [];
    $districts = [];

    foreach (array_values(array_unique(array_filter(array_map('trim', $district_names)))) as $district_name) {
        $row = mmc_population_get_district($province_name, $district_name, $year);

        if (!$row) {
            $missing[] = $district_name;
            continue;
        }

        $covered++;
        $total += (int) $row['population'];
        $districts[] = $row;
    }

    return [
        'province' => $province_name,
        'data_year' => (int) $year,
        'target_district_count' => count($district_names),
        'covered_district_count' => $covered,
        'total_population' => $total,
        'age_0_14' => null,
        'missing_districts' => $missing,
        'districts' => $districts,
    ];
}

function mmc_population_register_admin_page() {
    global $menu, $submenu;

    $parent_slug = '';

    /*
     * Öncelik: "Veri Ambarı" alt menüsünün bağlı olduğu Madagaskar ana menüsünü bul.
     * Böylece Nüfus Verisi aynı yönetim grubunda, Veri Ambarı satırının hemen altında görünür.
     */
    if (is_array($submenu)) {
        foreach ($submenu as $candidate_parent => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $label = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '';
                if (stripos($label, 'Veri Ambar') !== false) {
                    $parent_slug = (string) $candidate_parent;
                    break 2;
                }
            }
        }
    }

    /*
     * Veri Ambarı bulunamazsa Madagaskar ana menüsünü bul.
     */
    if (!$parent_slug && is_array($menu)) {
        foreach ($menu as $item) {
            $label = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '';
            $slug = isset($item[2]) ? (string) $item[2] : '';
            if ($slug && stripos($label, 'Madagaskar') !== false) {
                $parent_slug = $slug;
                break;
            }
        }
    }

    if ($parent_slug) {
        add_submenu_page(
            $parent_slug,
            'Nüfus ve Eğitim Verisi',
            'Nüfus ve Eğitim Verisi',
            'manage_options',
            'mmc-population-data',
            'mmc_population_render_admin_page'
        );

        /*
         * WordPress iç içe üçüncü seviye menü desteklemediği için "Veri Ambarı"
         * ile "Nüfus Verisi" aynı alt menü seviyesinde tutulur. Nüfus Verisi,
         * görsel olarak Veri Ambarı satırının hemen arkasına taşınır.
         */
        if (isset($submenu[$parent_slug]) && is_array($submenu[$parent_slug])) {
            $population_item = null;
            $population_key = null;
            $warehouse_key = null;

            foreach ($submenu[$parent_slug] as $key => $item) {
                $label = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '';
                $slug = isset($item[2]) ? (string) $item[2] : '';

                if ($slug === 'mmc-population-data') {
                    $population_item = $item;
                    $population_key = $key;
                }

                if ($warehouse_key === null && stripos($label, 'Veri Ambar') !== false) {
                    $warehouse_key = $key;
                }
            }

            if ($population_item !== null && $population_key !== null && $warehouse_key !== null) {
                unset($submenu[$parent_slug][$population_key]);

                $ordered = [];
                foreach ($submenu[$parent_slug] as $key => $item) {
                    $ordered[$key] = $item;
                    if ((string) $key === (string) $warehouse_key) {
                        $ordered['mmc_population_after_warehouse'] = $population_item;
                    }
                }

                $submenu[$parent_slug] = array_values($ordered);
            }
        }
    } else {
        add_management_page(
            'Madagaskar Veri Ambarı - Nüfus ve Eğitim',
            'Nüfus ve Eğitim Verisi',
            'manage_options',
            'mmc-population-data',
            'mmc_population_render_admin_page'
        );
    }
}
add_action('admin_menu', 'mmc_population_register_admin_page', 99999);

function mmc_population_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $province_table = mmc_population_table_provinces();
    $district_table = mmc_population_table_districts();

    $province_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$province_table} WHERE data_year = %d",
        MMC_POPULATION_DATA_YEAR
    ));
    $district_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$district_table} WHERE data_year = %d",
        MMC_POPULATION_DATA_YEAR
    ));

    $last = get_option('mmc_population_last_import', []);
    $message = get_transient('mmc_population_message_' . get_current_user_id());
    if ($message) {
        delete_transient('mmc_population_message_' . get_current_user_id());
    }

    echo '<div class="wrap">';
    echo '<h1>Madagaskar Veri Ambarı – Nüfus ve Eğitim Verileri</h1>';

    if (is_array($message) && !empty($message['text'])) {
        $class = !empty($message['success']) ? 'notice notice-success' : 'notice notice-error';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message['text']) . '</p></div>';
    }

    echo '<p>2025 il ve ilçe nüfus verilerini Madagaskar veri ambarına aktarır. 0–14 yaş verisi bu kaynakta bulunmadığı için tahmin edilmez.</p>';
    echo '<table class="widefat striped" style="max-width:760px;margin:20px 0;">';
    echo '<tbody>';
    echo '<tr><th>Veri yılı</th><td>' . esc_html((string) MMC_POPULATION_DATA_YEAR) . '</td></tr>';
    echo '<tr><th>İl</th><td>' . esc_html($province_count . ' / 81') . '</td></tr>';
    echo '<tr><th>İlçe</th><td>' . esc_html($district_count . ' / 973') . '</td></tr>';
    echo '<tr><th>Kaynak</th><td>' . esc_html(MMC_POPULATION_SOURCE_NAME) . '</td></tr>';
    echo '<tr><th>Son aktarım</th><td>' . esc_html(isset($last['imported_at']) ? (string) $last['imported_at'] : 'Henüz aktarım yapılmadı') . '</td></tr>';
    echo '</tbody></table>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('mmc_population_import_2025');
    echo '<input type="hidden" name="action" value="mmc_population_import_2025">';
    submit_button('2025 Nüfus Verisini Getir ve Güncelle', 'primary', 'submit', false);
    echo '</form>';

    echo '<p style="margin-top:18px;color:#646970;">Kaynak dosyalar doğrulanmadan veritabanına yazılmaz. Beklenen kayıt sayısı: 81 il, 973 ilçe.</p>';

    if (function_exists('mmc_education_render_admin_section')) {
        mmc_education_render_admin_section();
    }

    echo '</div>';
}

function mmc_population_handle_import() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu işlem için yetkiniz yok.');
    }

    check_admin_referer('mmc_population_import_2025');

    try {
        $result = mmc_population_import_2025();
        set_transient(
            'mmc_population_message_' . get_current_user_id(),
            [
                'success' => true,
                'text' => sprintf(
                    '%d il ve %d ilçe başarıyla güncellendi.',
                    (int) $result['province_count'],
                    (int) $result['district_count']
                ),
            ],
            60
        );
    } catch (Throwable $e) {
        set_transient(
            'mmc_population_message_' . get_current_user_id(),
            [
                'success' => false,
                'text' => 'Aktarım başarısız: ' . $e->getMessage(),
            ],
            60
        );
    }

    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = admin_url('admin.php?page=mmc-population-data');
    }

    wp_safe_redirect($redirect);
    exit;
}
add_action('admin_post_mmc_population_import_2025', 'mmc_population_handle_import');
