<?php
/**
 * MMC Hazırlık ekranı nüfus köprüsü.
 *
 * mmc-preparation ekranındaki hedef ilçe seçimlerini, Madagaskar 2025
 * nüfus veri setiyle eşleştirir ve Toplam Nüfus / veri kalitesi / il geneli
 * referans alanlarını canlı olarak günceller.
 */

if (!defined('ABSPATH')) {
    exit;
}

function mmc_population_bridge_is_preparation_page() {
    if (!is_admin()) {
        return false;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return $page === 'mmc-preparation';
}

function mmc_population_bridge_table_exists($table) {
    global $wpdb;
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return $found === $table;
}

function mmc_population_bridge_program_province($program_id) {
    global $wpdb;

    $program_id = absint($program_id);
    if (!$program_id) {
        return '';
    }

    $table = $wpdb->prefix . 'mmc_programs';
    if (!mmc_population_bridge_table_exists($table)) {
        return '';
    }

    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
    if (!$columns) {
        return '';
    }

    $id_column = '';
    foreach (['id', 'program_id'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $id_column = $candidate;
            break;
        }
    }

    if (!$id_column) {
        return '';
    }

    foreach (['province_name', 'il', 'province', 'city_name', 'city'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $sql = $wpdb->prepare(
                "SELECT {$candidate} FROM {$table} WHERE {$id_column} = %d LIMIT 1",
                $program_id
            );
            $value = trim((string) $wpdb->get_var($sql));
            if ($value !== '') {
                return $value;
            }
        }
    }

    $province_fk = '';
    foreach (['province_id', 'il_id', 'city_id', 'province_code', 'il_kodu'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $province_fk = $candidate;
            break;
        }
    }

    if (!$province_fk) {
        return '';
    }

    $fk_value = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT {$province_fk} FROM {$table} WHERE {$id_column} = %d LIMIT 1",
            $program_id
        )
    );

    if ($fk_value === null || $fk_value === '') {
        return '';
    }

    $province_table = $wpdb->prefix . 'mmc_provinces';
    if (!mmc_population_bridge_table_exists($province_table)) {
        return '';
    }

    $province_columns = $wpdb->get_col("SHOW COLUMNS FROM {$province_table}", 0);
    if (!$province_columns) {
        return '';
    }

    $province_name_column = '';
    foreach (['name', 'province_name', 'il', 'city_name'] as $candidate) {
        if (in_array($candidate, $province_columns, true)) {
            $province_name_column = $candidate;
            break;
        }
    }

    $province_id_column = '';
    foreach (['id', 'province_id', 'code', 'province_code', 'il_kodu'] as $candidate) {
        if (in_array($candidate, $province_columns, true)) {
            $province_id_column = $candidate;
            break;
        }
    }

    if (!$province_name_column || !$province_id_column) {
        return '';
    }

    $sql = $wpdb->prepare(
        "SELECT {$province_name_column} FROM {$province_table} WHERE {$province_id_column} = %s LIMIT 1",
        (string) $fk_value
    );

    return trim((string) $wpdb->get_var($sql));
}

function mmc_population_bridge_data_ready() {
    global $wpdb;

    $province_table = mmc_population_table_provinces();
    $district_table = mmc_population_table_districts();

    if (!mmc_population_bridge_table_exists($province_table) || !mmc_population_bridge_table_exists($district_table)) {
        return false;
    }

    $province_count = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$province_table} WHERE data_year = %d", MMC_POPULATION_DATA_YEAR)
    );
    $district_count = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$district_table} WHERE data_year = %d", MMC_POPULATION_DATA_YEAR)
    );

    return $province_count === 81 && $district_count === 973;
}

function mmc_population_bridge_ajax_summary() {
    if (!is_user_logged_in() || !current_user_can('read')) {
        wp_send_json_error(['message' => 'Yetkisiz işlem.'], 403);
    }

    check_ajax_referer('mmc_population_preparation_bridge', 'nonce');

    if (!function_exists('mmc_population_get_target_summary')) {
        wp_send_json_error(['message' => 'Nüfus modülü yüklenemedi.'], 500);
    }

    if (!mmc_population_bridge_data_ready() && current_user_can('manage_options')) {
        try {
            mmc_population_import_2025();
        } catch (Throwable $e) {
            wp_send_json_error([
                'message' => '2025 nüfus verisi otomatik yüklenemedi: ' . $e->getMessage(),
                'data_ready' => false,
            ], 500);
        }
    }

    if (!mmc_population_bridge_data_ready()) {
        wp_send_json_error([
            'message' => '2025 nüfus verisi henüz yüklenmedi.',
            'data_ready' => false,
        ], 409);
    }

    $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
    $province = isset($_POST['province']) ? sanitize_text_field(wp_unslash($_POST['province'])) : '';

    if (!$province) {
        $province = mmc_population_bridge_program_province($program_id);
    }

    $districts = [];
    if (isset($_POST['districts'])) {
        $raw = wp_unslash($_POST['districts']);
        if (is_array($raw)) {
            foreach ($raw as $district) {
                $district = trim(sanitize_text_field($district));
                if ($district !== '') {
                    $districts[] = $district;
                }
            }
        }
    }

    $districts = array_values(array_unique($districts));

    if (!$province) {
        wp_send_json_error([
            'message' => 'Programın ili belirlenemedi.',
            'data_ready' => true,
        ], 422);
    }

    $summary = mmc_population_get_target_summary($province, $districts, MMC_POPULATION_DATA_YEAR);
    $province_row = mmc_population_get_province($province, MMC_POPULATION_DATA_YEAR);

    wp_send_json_success([
        'province' => $province,
        'province_population' => $province_row ? (int) $province_row['population'] : null,
        'data_year' => MMC_POPULATION_DATA_YEAR,
        'target_district_count' => (int) $summary['target_district_count'],
        'covered_district_count' => (int) $summary['covered_district_count'],
        'total_population' => (int) $summary['total_population'],
        'age_0_14' => null,
        'missing_districts' => array_values($summary['missing_districts']),
        'source' => MMC_POPULATION_SOURCE_NAME,
        'data_ready' => true,
    ]);
}
add_action('wp_ajax_mmc_population_target_summary', 'mmc_population_bridge_ajax_summary');

function mmc_population_bridge_enqueue() {
    if (!mmc_population_bridge_is_preparation_page()) {
        return;
    }

    wp_enqueue_script('jquery');

    $config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mmc_population_preparation_bridge'),
        'programId' => isset($_GET['program_id']) ? absint($_GET['program_id']) : 0,
        'year' => MMC_POPULATION_DATA_YEAR,
    ];

    $js = <<<'JS'
(function($){
    'use strict';

    var cfg = window.MMC_POPULATION_BRIDGE || {};
    var timer = null;
    var lastKey = '';

    function normText(v) {
        return String(v || '').replace(/s+/g, ' ').trim();
    }

    function formatNumber(v) {
        var n = Number(v || 0);
        try {
            return new Intl.NumberFormat('tr-TR').format(n);
        } catch (e) {
            return String(n);
        }
    }

    function fieldValue(el) {
        if (!el) return '';
        if (el.tagName === 'SELECT') {
            var opt = el.options[el.selectedIndex];
            return normText(opt ? opt.textContent : el.value);
        }
        return normText(el.value);
    }

    function getProvince() {
        var selectors = [
            '[name="province_name"]',
            '[name="province"]',
            '[name="il"]',
            '#province_name',
            '#province',
            '#il'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector('#wpbody-content ' + selectors[i]);
            var val = fieldValue(el);
            if (val && !/^(seç|sec|tümü|tumu)$/i.test(val)) return val;
        }

        var candidates = document.querySelectorAll('#wpbody-content select, #wpbody-content input');
        for (var j = 0; j < candidates.length; j++) {
            var n = String(candidates[j].name || candidates[j].id || '').toLowerCase();
            if (/(^|[_-])(il|province|city)($|[_-])/.test(n) && !/(ilce|district)/.test(n)) {
                var x = fieldValue(candidates[j]);
                if (x) return x;
            }
        }

        return '';
    }

    function labelTextForCheckbox(cb) {
        var text = '';

        if (cb.dataset) {
            text = cb.dataset.district || cb.dataset.ilce || cb.dataset.name || '';
            if (text) return normText(text);
        }

        if (cb.id) {
            var label = document.querySelector('label[for="' + CSS.escape(cb.id) + '"]');
            if (label) {
                text = label.textContent;
                if (text) return normText(text);
            }
        }

        var parentLabel = cb.closest('label');
        if (parentLabel) {
            text = parentLabel.textContent;
            if (text) return normText(text);
        }

        if (cb.value && !/^(1|on|true|yes)$/i.test(cb.value)) {
            return normText(cb.value);
        }

        var parent = cb.parentElement;
        if (parent) {
            text = parent.textContent;
        }

        return normText(text);
    }

    function cleanDistrictName(v) {
        v = normText(v);
        v = v.replace(/^[✓✔☑-s]+/, '').trim();
        v = v.replace(/s*(s*(seçili|selected)s*)s*$/i, '').trim();

        if (!v || v.length > 100) return '';
        if (/^(tümü|tumunu seç|hepsini seç|seç|sec|all)$/i.test(v)) return '';
        if (/ana ilçe ile birlikte|okul tanıtımı yapılacak|kaydet ve analiz et/i.test(v)) return '';

        return v;
    }

    function getDistricts() {
        var out = [];
        var seen = {};

        var boxes = document.querySelectorAll('#wpbody-content input[type="checkbox"]:checked');
        boxes.forEach(function(cb){
            if (cb.disabled) return;

            var name = String(cb.name || '').toLowerCase();
            var id = String(cb.id || '').toLowerCase();

            if (/(select.?all|tum|all)/.test(name + ' ' + id)) return;

            var text = cleanDistrictName(labelTextForCheckbox(cb));
            if (!text) return;

            var key = text.toLocaleLowerCase('tr-TR');
            if (!seen[key]) {
                seen[key] = true;
                out.push(text);
            }
        });

        if (!out.length) {
            var selects = document.querySelectorAll('#wpbody-content select');
            selects.forEach(function(el){
                var n = String(el.name || el.id || '').toLowerCase();
                if (!/(ilce|district)/.test(n)) return;
                var text = cleanDistrictName(fieldValue(el));
                if (!text) return;
                var key = text.toLocaleLowerCase('tr-TR');
                if (!seen[key]) {
                    seen[key] = true;
                    out.push(text);
                }
            });
        }

        return out;
    }

    function findExactText(root, text) {
        var wanted = normText(text).toLocaleLowerCase('tr-TR');
        var nodes = root.querySelectorAll('div,span,p,small,strong,th,td,label');
        for (var i = 0; i < nodes.length; i++) {
            if (normText(nodes[i].textContent).toLocaleLowerCase('tr-TR') === wanted) {
                return nodes[i];
            }
        }
        return null;
    }

    function closestCard(label) {
        if (!label) return null;
        var el = label;
        while (el && el !== document.body) {
            var cls = String(el.className || '').toLowerCase();
            if (/card|stat|metric|summary|kpi/.test(cls)) return el;
            if (el.children && el.children.length >= 2 && el.offsetWidth > 120 && el.offsetHeight > 45) {
                return el;
            }
            el = el.parentElement;
        }
        return label.parentElement;
    }

    function updateMetricCard(labelText, value, coverageText) {
        var root = document.getElementById('wpbody-content') || document;
        var label = findExactText(root, labelText);
        if (!label) return false;

        var card = closestCard(label);
        if (!card) return false;

        var leaves = card.querySelectorAll('div,span,p,strong,small');
        var valueDone = false;
        var coverageDone = false;

        for (var i = 0; i < leaves.length; i++) {
            var el = leaves[i];
            if (el === label || el.children.length) continue;

            var t = normText(el.textContent);

            if (!valueDone && /^[0-9.s]+$/.test(t)) {
                el.textContent = value;
                el.setAttribute('data-mmc-population-live', '1');
                valueDone = true;
                continue;
            }

            if (!coverageDone && /ilçe verisi/i.test(t)) {
                el.textContent = coverageText;
                coverageDone = true;
            }
        }

        if (!valueDone) {
            var live = document.createElement('div');
            live.style.fontSize = '28px';
            live.style.fontWeight = '700';
            live.style.marginTop = '4px';
            live.textContent = value;
            live.setAttribute('data-mmc-population-live', '1');
            card.appendChild(live);
        }

        if (!coverageDone) {
            var meta = document.createElement('small');
            meta.style.display = 'block';
            meta.style.marginTop = '4px';
            meta.style.color = '#646970';
            meta.textContent = coverageText;
            card.appendChild(meta);
        }

        return true;
    }

    function updateQualityTables(data) {
        var rows = document.querySelectorAll('#wpbody-content tr');
        rows.forEach(function(row){
            var cells = row.querySelectorAll('th,td');
            if (cells.length < 2) return;

            var first = normText(cells[0].textContent).toLocaleLowerCase('tr-TR');
            if (first !== 'nüfus' && first !== 'nufus') return;

            var second = normText(cells[1].textContent);
            var third = cells.length > 2 ? normText(cells[2].textContent) : '';

            if (//s*d+s*ilçe/i.test(second) || /ilçe/i.test(second)) {
                cells[1].textContent = data.covered_district_count + ' / ' + data.target_district_count + ' ilçe';
                if (cells.length > 2) {
                    var complete = data.target_district_count > 0 && data.covered_district_count === data.target_district_count;
                    cells[2].textContent = complete ? '● Veri mevcut' : '● Eksik veri';
                }
                return;
            }

            if ((/veri yok/i.test(second) || second === '-') && (third === '-' || /veri yok/i.test(third))) {
                cells[1].textContent = 'Veri mevcut';
                if (cells.length > 2 && data.province_population !== null) {
                    cells[2].textContent = formatNumber(data.province_population);
                }
            }
        });
    }

    function showStatus(message, type) {
        var id = 'mmc-population-bridge-status';
        var old = document.getElementById(id);
        if (old) old.remove();

        if (!message) return;

        var box = document.createElement('div');
        box.id = id;
        box.className = 'notice ' + (type === 'error' ? 'notice-error' : 'notice-info') + ' inline';
        box.style.margin = '12px 0';
        box.innerHTML = '<p></p>';
        box.querySelector('p').textContent = message;

        var root = document.getElementById('wpbody-content');
        if (root) root.insertBefore(box, root.firstChild);
    }

    function applyData(data) {
        var coverage = data.covered_district_count + '/' + data.target_district_count + ' ilçe verisi';
        updateMetricCard('Toplam Nüfus', formatNumber(data.total_population), coverage);
        updateQualityTables(data);

        if (data.missing_districts && data.missing_districts.length) {
            showStatus('Nüfus verisi bulunamayan ilçe: ' + data.missing_districts.join(', '), 'error');
        } else {
            showStatus('', 'info');
        }
    }

    function refresh() {
        var districts = getDistricts();
        var province = getProvince();
        var key = JSON.stringify([cfg.programId || 0, province, districts]);

        if (key === lastKey) return;
        lastKey = key;

        $.ajax({
            url: cfg.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mmc_population_target_summary',
                nonce: cfg.nonce,
                program_id: cfg.programId || 0,
                province: province,
                districts: districts
            }
        }).done(function(resp){
            if (resp && resp.success && resp.data) {
                applyData(resp.data);
            } else {
                var msg = resp && resp.data && resp.data.message ? resp.data.message : 'Nüfus verisi alınamadı.';
                showStatus(msg, 'error');
            }
        }).fail(function(xhr){
            var msg = 'Nüfus verisi alınamadı.';
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                msg = xhr.responseJSON.data.message;
            }
            showStatus(msg, 'error');
        });
    }

    function scheduleRefresh() {
        window.clearTimeout(timer);
        timer = window.setTimeout(refresh, 180);
    }

    $(function(){
        scheduleRefresh();

        $('#wpbody-content').on('change', 'input[type="checkbox"], select, input[name*="il"], input[name*="province"]', scheduleRefresh);
        $('#wpbody-content').on('click', 'button, input[type="submit"]', function(){
            window.setTimeout(scheduleRefresh, 250);
        });

        var target = document.getElementById('wpbody-content');
        if (target && window.MutationObserver) {
            var observer = new MutationObserver(function(mutations){
                var relevant = mutations.some(function(m){
                    return m.addedNodes && m.addedNodes.length;
                });
                if (relevant) scheduleRefresh();
            });
            observer.observe(target, {childList:true, subtree:true});
        }
    });
})(jQuery);
JS;

    wp_add_inline_script(
        'jquery',
        'window.MMC_POPULATION_BRIDGE = ' . wp_json_encode($config) . ';' . "
" . $js,
        'after'
    );
}
add_action('admin_enqueue_scripts', 'mmc_population_bridge_enqueue', 50);
