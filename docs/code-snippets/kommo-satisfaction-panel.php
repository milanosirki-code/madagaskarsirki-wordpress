<?php
/** Private, read-only Kommo satisfaction dashboard; operational Code Snippets bridge. */
if (!defined('ABSPATH')) { exit; }

function mdg_sp_result($lead) {
    $value = '';
    foreach (($lead['custom_fields_values'] ?? array()) as $field) {
        if ((int)$field['field_id'] === 2460856) {
            $value = trim((string)($field['values'][0]['value'] ?? ''));
        }
    }
    $allowed = array('Anket: Çok memnun', 'Anket: Memnun', 'Anket: Takip gerekli', 'Anket: Serbest yanıt');
    return in_array($value, $allowed, true) ? $value : ($value === '' ? '' : 'Tanımsız yanıt');
}

function mdg_sp_fetch() {
    if (!function_exists('mdg_kr_request')) { return new WP_Error('source', 'Kommo bağlantı yardımcısı bulunamadı.'); }
    $rows = array();
    $complete = false;
    for ($page = 1; $page <= 4; $page++) {
        $data = mdg_kr_request('GET', '/leads?' . http_build_query(array('limit'=>250, 'page'=>$page, 'filter'=>array('pipeline_id'=>array(14297695)), 'order'=>array('id'=>'desc'))));
        if (!is_array($data) || !isset($data['_embedded']['leads'])) {
            return new WP_Error('source', 'Kommo verisi alınamadı; toplamlar gösterilmiyor.');
        }
        foreach ($data['_embedded']['leads'] as $lead) {
            $tags = array_column($lead['_embedded']['tags'] ?? array(), 'name');
            $result = mdg_sp_result($lead);
            $started = in_array('madagaskar_anket_baslatildi', $tags, true);
            if (!$started && $result === '') { continue; }
            $order = preg_match('/^Order#(\d+)\b/', (string)$lead['name'], $match) ? (int)$match[1] : 0;
            $rows[(int)$lead['id']] = array('lead_id'=>(int)$lead['id'], 'order_id'=>$order, 'started'=>$started, 'result'=>$result, 'city'=>'Eşleşmeyen', 'venue'=>'Eşleşmeyen', 'session'=>'Eşleşmeyen');
        }
        if (empty($data['_links']['next'])) { $complete = true; break; }
    }
    global $wpdb;
    if (class_exists('MDG_DB')) {
        foreach ($rows as &$row) {
            if (!$row['order_id']) { continue; }
            $m = MDG_DB::table('order_map'); $e = MDG_DB::table('events'); $s = MDG_DB::table('sessions');
            $matches = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT m.event_id,m.session_id,e.province_name,e.venue_name,s.start_at FROM {$m} m JOIN {$e} e ON e.id=m.event_id JOIN {$s} s ON s.id=m.session_id AND s.event_id=m.event_id WHERE m.order_id=%d", $row['order_id']), ARRAY_A);
            if (count((array)$matches) === 1) {
                $row['city'] = $matches[0]['province_name'] ?: 'Eşleşmeyen';
                $row['venue'] = $matches[0]['venue_name'] ?: 'Eşleşmeyen';
                $row['session'] = get_date_from_gmt($matches[0]['start_at'], 'Y-m-d H:i');
            } elseif (count((array)$matches) > 1) {
                $row['city'] = $row['venue'] = $row['session'] = 'Birden çok seans';
            }
        }
        unset($row);
    }
    return array('rows'=>array_values($rows), 'complete'=>$complete, 'updated'=>current_time('mysql'));
}

add_action('admin_menu', function() {
    add_submenu_page('mmc-dashboard', 'Müşteri Memnuniyeti', 'Müşteri Memnuniyeti', 'manage_options', 'mdg-satisfaction', 'mdg_sp_page');
}, 99);

function mdg_sp_page() {
    if (!current_user_can('manage_options')) { wp_die('Yetkisiz erişim.'); }
    if (isset($_POST['mdg_sp_refresh'])) {
        check_admin_referer('mdg_sp_refresh');
        delete_transient('mdg_sp_dashboard_v1');
    }
    $data = get_transient('mdg_sp_dashboard_v1');
    if ($data === false) {
        $data = mdg_sp_fetch();
        if (!is_wp_error($data)) { set_transient('mdg_sp_dashboard_v1', $data, 10 * MINUTE_IN_SECONDS); }
    }
    echo '<div class="wrap"><h1>Müşteri Memnuniyeti</h1>';
    echo '<p>Kommo’daki son kayıtlı yanıt esas alınır. Her CRM kaydı bir kez sayılır. Bot başlatılması WhatsApp teslim kanıtı değildir. Üç seçenekli anketten 5 üzerinden puan hesaplanmaz.</p>';
    echo '<form method="post">'; wp_nonce_field('mdg_sp_refresh');
    echo '<button class="button" name="mdg_sp_refresh" value="1">Verileri yenile</button></form>';
    if (is_wp_error($data)) { echo '<div class="notice notice-error"><p>'.esc_html($data->get_error_message()).'</p></div></div>'; return; }
    echo '<p>Güncelleme: '.esc_html($data['updated']).' — Kaynak: WooCommerce Kommo satış hattı.</p>';
    if (!$data['complete']) { echo '<div class="notice notice-warning"><p>Kısmi kapsam: en yeni 1.000 CRM kaydı tarandı. Rakamlar tüm müşteri geçmişini kapsamaz.</p></div>'; }
    $filters = array();
    echo '<form method="get"><input type="hidden" name="page" value="mdg-satisfaction">';
    foreach (array('city'=>'Şehir', 'venue'=>'Salon', 'session'=>'Seans', 'result'=>'Yanıt') as $key=>$label) {
        $filters[$key] = isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        $options = array_unique(array_column($data['rows'], $key)); sort($options);
        echo '<label style="margin-right:12px">'.esc_html($label).' <select name="'.esc_attr($key).'"><option value="">Tümü</option>';
        foreach ($options as $option) {
            if ($option === '') { continue; }
            echo '<option value="'.esc_attr($option).'" '.selected($filters[$key], $option, false).'>'.esc_html($option).'</option>';
        }
        echo '</select></label>';
    }
    echo '<button class="button">Filtrele</button></form>';
    $rows = array_filter($data['rows'], function($row) use ($filters) {
        foreach ($filters as $key=>$value) { if ($value !== '' && $row[$key] !== $value) { return false; } }
        return true;
    });
    $started = 0; $answered = 0; $counts = array();
    foreach ($rows as $row) { $started += (int)$row['started']; if ($row['result'] !== '') { $answered++; $counts[$row['result']] = ($counts[$row['result']] ?? 0) + 1; } }
    echo '<h2>Bot başlatılan: '.(int)$started.' · Yanıtı kayıtlı: '.(int)$answered.'</h2><p>';
    foreach ($counts as $label=>$count) { echo esc_html($label).': '.(int)$count.' &nbsp; '; }
    echo '</p><table class="widefat striped"><thead><tr><th>Sipariş</th><th>Şehir</th><th>Salon</th><th>Seans</th><th>Son yanıt</th><th>Kommo</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>'.($row['order_id'] ? '#'.(int)$row['order_id'] : 'Eşleşmeyen').'</td>';
        foreach (array('city','venue','session','result') as $key) { echo '<td>'.esc_html($row[$key] ?: 'Yanıt bekleniyor').'</td>'; }
        echo '<td><a href="'.esc_url('https://milanosirki.kommo.com/leads/detail/'.(int)$row['lead_id']).'" target="_blank" rel="noopener noreferrer">Kaydı aç</a></td></tr>';
    }
    if (!$rows) { echo '<tr><td colspan="6">Bu kapsamda kayıt bulunamadı.</td></tr>'; }
    echo '</tbody></table></div>';
}
