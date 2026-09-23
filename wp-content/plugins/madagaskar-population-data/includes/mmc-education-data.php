<?php
if (!defined('ABSPATH')) {
    exit;
}

define('MMC_EDUCATION_ACADEMIC_YEAR', '2024/25');
define('MMC_EDUCATION_SOURCE_NAME', 'MEB Millî Eğitim İstatistikleri - Örgün Eğitim 2024/25');
define('MMC_EDUCATION_SOURCE_TABLE', '1.17');

function mmc_education_table_provinces() {
    global $wpdb;
    return $wpdb->prefix . 'mmc_education_provinces';
}

function mmc_education_install() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $table = mmc_education_table_provinces();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source_code varchar(8) NOT NULL,
        province_name varchar(120) NOT NULL,
        province_name_key varchar(120) NOT NULL,
        academic_year varchar(12) NOT NULL,
        school_count int(10) unsigned NOT NULL DEFAULT 0,
        student_count bigint(20) unsigned NOT NULL DEFAULT 0,
        source_name varchar(190) NOT NULL,
        source_table varchar(30) NOT NULL,
        source_pages varchar(30) NULL,
        imported_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY code_year (source_code, academic_year),
        KEY province_year (province_name_key, academic_year)
    ) {$charset};";

    dbDelta($sql);
    update_option('mmc_education_db_version', MMC_POPULATION_VERSION);
}

function mmc_education_dataset_path() {
    return plugin_dir_path(dirname(__FILE__)) . 'data/meb-2024-25-provinces.json';
}

function mmc_education_load_dataset() {
    $path = mmc_education_dataset_path();

    if (!is_readable($path)) {
        throw new RuntimeException('MEB eğitim veri dosyası bulunamadı.');
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || empty($data['records']) || !is_array($data['records'])) {
        throw new RuntimeException('MEB eğitim veri dosyası geçerli JSON değil.');
    }

    if (($data['academic_year'] ?? '') !== MMC_EDUCATION_ACADEMIC_YEAR) {
        throw new RuntimeException('MEB eğitim veri yılı beklenen dönemle eşleşmiyor.');
    }

    $validation = isset($data['validation']) && is_array($data['validation']) ? $data['validation'] : [];
    if (count($data['records']) !== 81 || (int) ($validation['province_count'] ?? 0) !== 81) {
        throw new RuntimeException('MEB eğitim veri seti 81 il içermiyor.');
    }

    $school_total = 0;
    $student_total = 0;
    foreach ($data['records'] as $row) {
        if (!isset($row['code'], $row['province'], $row['school_count'], $row['student_count'])) {
            throw new RuntimeException('MEB eğitim veri setinde zorunlu alan eksik.');
        }
        $school_total += (int) $row['school_count'];
        $student_total += (int) $row['student_count'];
    }

    if ($school_total !== 74040 || $student_total !== 17956523) {
        throw new RuntimeException('MEB eğitim veri seti ulusal toplam doğrulamasını geçemedi.');
    }

    return $data;
}

function mmc_education_import_2024_25() {
    global $wpdb;

    mmc_education_install();
    $data = mmc_education_load_dataset();
    $table = mmc_education_table_provinces();
    $now = current_time('mysql');

    $wpdb->query('START TRANSACTION');

    try {
        foreach ($data['records'] as $row) {
            $pages = '';
            if (!empty($row['source_pages']) && is_array($row['source_pages'])) {
                $pages = implode('-', array_map('absint', $row['source_pages']));
            }

            $ok = $wpdb->replace(
                $table,
                [
                    'source_code' => sanitize_text_field((string) $row['code']),
                    'province_name' => sanitize_text_field((string) $row['province']),
                    'province_name_key' => mmc_population_normalize_key($row['province']),
                    'academic_year' => MMC_EDUCATION_ACADEMIC_YEAR,
                    'school_count' => (int) $row['school_count'],
                    'student_count' => (int) $row['student_count'],
                    'source_name' => MMC_EDUCATION_SOURCE_NAME,
                    'source_table' => MMC_EDUCATION_SOURCE_TABLE,
                    'source_pages' => $pages,
                    'imported_at' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s']
            );

            if ($ok === false) {
                throw new RuntimeException('MEB eğitim kaydı yazılamadı: ' . $wpdb->last_error);
            }
        }

        $wpdb->query('COMMIT');

        update_option('mmc_education_last_import', [
            'academic_year' => MMC_EDUCATION_ACADEMIC_YEAR,
            'province_count' => 81,
            'school_count_total' => 74040,
            'student_count_total' => 17956523,
            'imported_at' => $now,
            'source' => MMC_EDUCATION_SOURCE_NAME,
            'table' => MMC_EDUCATION_SOURCE_TABLE,
        ], false);

        return [
            'province_count' => 81,
            'school_count_total' => 74040,
            'student_count_total' => 17956523,
            'imported_at' => $now,
        ];
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');
        throw $e;
    }
}

function mmc_education_data_ready() {
    global $wpdb;
    $table = mmc_education_table_provinces();

    if (function_exists('mmc_population_bridge_table_exists') && !mmc_population_bridge_table_exists($table)) {
        return false;
    }

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if ($exists !== $table) {
        return false;
    }

    $count = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE academic_year = %s", MMC_EDUCATION_ACADEMIC_YEAR)
    );

    return $count === 81;
}

function mmc_education_get_province($province_name, $academic_year = MMC_EDUCATION_ACADEMIC_YEAR) {
    global $wpdb;

    if (!mmc_education_data_ready()) {
        return null;
    }

    $table = mmc_education_table_provinces();
    $key = mmc_population_normalize_key($province_name);

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT source_code, province_name, academic_year, school_count, student_count,
                    source_name, source_table, source_pages, imported_at
             FROM {$table}
             WHERE province_name_key = %s AND academic_year = %s
             LIMIT 1",
            $key,
            (string) $academic_year
        ),
        ARRAY_A
    );
}

function mmc_education_maybe_upgrade() {
    if (!is_admin()) {
        return;
    }

    if (get_option('mmc_education_db_version') !== MMC_POPULATION_VERSION || !mmc_education_data_ready()) {
        try {
            mmc_education_import_2024_25();
        } catch (Throwable $e) {
            update_option('mmc_education_last_error', $e->getMessage(), false);
        }
    }
}
add_action('admin_init', 'mmc_education_maybe_upgrade', 20);

function mmc_education_handle_import() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu işlem için yetkiniz yok.');
    }

    check_admin_referer('mmc_education_import_2024_25');

    try {
        $result = mmc_education_import_2024_25();
        set_transient(
            'mmc_education_message_' . get_current_user_id(),
            [
                'success' => true,
                'text' => sprintf(
                    'MEB 2024/25 eğitim verisi güncellendi: %d il, %s okul, %s öğrenci.',
                    (int) $result['province_count'],
                    number_format_i18n((int) $result['school_count_total']),
                    number_format_i18n((int) $result['student_count_total'])
                ),
            ],
            60
        );
    } catch (Throwable $e) {
        set_transient(
            'mmc_education_message_' . get_current_user_id(),
            [
                'success' => false,
                'text' => 'MEB eğitim verisi aktarımı başarısız: ' . $e->getMessage(),
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
add_action('admin_post_mmc_education_import_2024_25', 'mmc_education_handle_import');

function mmc_education_render_admin_section() {
    global $wpdb;

    $table = mmc_education_table_provinces();
    $count = 0;
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE academic_year = %s", MMC_EDUCATION_ACADEMIC_YEAR)
        );
    }

    $last = get_option('mmc_education_last_import', []);
    $message = get_transient('mmc_education_message_' . get_current_user_id());
    if ($message) {
        delete_transient('mmc_education_message_' . get_current_user_id());
    }

    echo '<hr style="margin:32px 0;">';
    echo '<h2>MEB Eğitim Verisi 2024/25</h2>';
    echo '<p>İl geneli okul ve öğrenci sayılarını Veri Ambarı ve Hazırlık ekranındaki <strong>İl Geneli Referans</strong> alanına sağlar. Kaynak tablo il düzeyindedir; ilçe değerlerine dağıtılmaz.</p>';

    if (is_array($message) && !empty($message['text'])) {
        $class = !empty($message['success']) ? 'notice notice-success inline' : 'notice notice-error inline';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message['text']) . '</p></div>';
    }

    echo '<table class="widefat striped" style="max-width:760px;margin:20px 0;">';
    echo '<tbody>';
    echo '<tr><th>Öğretim yılı</th><td>' . esc_html(MMC_EDUCATION_ACADEMIC_YEAR) . '</td></tr>';
    echo '<tr><th>İl kaydı</th><td>' . esc_html($count . ' / 81') . '</td></tr>';
    echo '<tr><th>Türkiye okul toplamı</th><td>' . esc_html(number_format_i18n(74040)) . '</td></tr>';
    echo '<tr><th>Türkiye öğrenci toplamı</th><td>' . esc_html(number_format_i18n(17956523)) . '</td></tr>';
    echo '<tr><th>Kaynak</th><td>' . esc_html(MMC_EDUCATION_SOURCE_NAME . ' — Tablo ' . MMC_EDUCATION_SOURCE_TABLE) . '</td></tr>';
    echo '<tr><th>Son aktarım</th><td>' . esc_html(isset($last['imported_at']) ? (string) $last['imported_at'] : 'Henüz aktarım yapılmadı') . '</td></tr>';
    echo '</tbody></table>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('mmc_education_import_2024_25');
    echo '<input type="hidden" name="action" value="mmc_education_import_2024_25">';
    submit_button('MEB 2024/25 Eğitim Verisini Güncelle', 'secondary', 'submit', false);
    echo '</form>';

    echo '<p style="margin-top:14px;color:#646970;">Not: Bu kaynak 81 il için il geneli okul ve öğrenci toplamını verir. İlçe bazında okul/öğrenci toplamı üretmek için MEBBİS okul listesi veya ayrı ilçe düzeyi kaynak gerekir.</p>';
}
