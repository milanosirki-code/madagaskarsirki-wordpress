<?php
/** Admin-only canonical source freshness monitor. Does not write to Kommo. */
if (!defined('ABSPATH')) { exit; }

function mdg_kf_hashes($program, $locations) {
    return array('program'=>hash('sha256', (string)$program), 'locations'=>hash('sha256', (string)$locations));
}
function mdg_kf_matches($current, $verified) {
    foreach (array('program', 'locations') as $key) {
        if (empty($current[$key]) || empty($verified[$key]) || !hash_equals((string)$current[$key], (string)$verified[$key])) { return false; }
    }
    return true;
}
function mdg_kf_snapshot() {
    if (!function_exists('mdg_kommo_unified_v2_collect') || !function_exists('mdg_kommo_unified_v2_compact_text') || !function_exists('mdg_kommo_unified_v2_locations_text')) {
        return new WP_Error('source', 'Kanonik Kommo kaynak yardımcısı bulunamadı.');
    }
    $rows = mdg_kommo_unified_v2_collect();
    $program = mdg_kommo_unified_v2_compact_text($rows);
    $locations = mdg_kommo_unified_v2_locations_text($rows);
    return array('count'=>count($rows), 'program'=>$program, 'locations'=>$locations, 'hashes'=>mdg_kf_hashes($program, $locations));
}
add_action('admin_menu', function() {
    add_submenu_page('mmc-dashboard', 'Kommo Kaynak Kontrolü', 'Kommo Kaynak Kontrolü', 'manage_options', 'mdg-kommo-freshness', 'mdg_kf_page');
}, 99);
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) { return; }
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (strpos($page, 'mmc-') !== 0 && strpos($page, 'mdg-kommo') !== 0) { return; }
    $snapshot = mdg_kf_snapshot();
    $verified = get_option('mdg_kommo_canonical_verified_v1', array());
    if (is_wp_error($snapshot) || !mdg_kf_matches($snapshot['hashes'], $verified)) {
        echo '<div class="notice notice-warning"><p>Kommo AI kaynak güncelliği doğrulanmalı. Program, fiyat veya konum metni son doğrulama ile eşleşmiyor. <a href="'.esc_url(admin_url('admin.php?page=mdg-kommo-freshness')).'">Kaynak kontrolünü aç</a></p></div>';
    }
});
function mdg_kf_page() {
    if (!current_user_can('manage_options')) { wp_die('Yetkisiz erişim.'); }
    $snapshot = mdg_kf_snapshot();
    echo '<div class="wrap"><h1>Kommo Kaynak Kontrolü</h1>';
    if (is_wp_error($snapshot)) { echo '<p>'.esc_html($snapshot->get_error_message()).'</p></div>'; return; }
    if (isset($_POST['mdg_kf_confirm'])) {
        check_admin_referer('mdg_kf_confirm');
        $posted = array();
        foreach (array('program', 'locations') as $key) { $posted[$key] = isset($_POST[$key.'_hash']) ? sanitize_text_field(wp_unslash($_POST[$key.'_hash'])) : ''; }
        if (empty($_POST['verified_program']) || empty($_POST['verified_locations']) || !mdg_kf_matches($snapshot['hashes'], $posted)) {
            echo '<div class="notice notice-error"><p>İki kaynak doğrulanmalı. Veriler değiştiyse güncel metinleri yeniden kontrol edin.</p></div>';
        } else {
            update_option('mdg_kommo_canonical_verified_v1', array_merge($snapshot['hashes'], array('verified_at'=>current_time('mysql'))), false);
            echo '<div class="notice notice-success"><p>Bu metinlerin elle doğrulama kaydı güncellendi.</p></div>';
        }
    }
    $verified = get_option('mdg_kommo_canonical_verified_v1', array());
    $matches = mdg_kf_matches($snapshot['hashes'], $verified);
    echo '<h2>'.($matches ? 'Son doğrulanan metinlerle eşleşiyor' : 'Güncelleme / doğrulama gerekiyor').'</h2>';
    echo '<p>Aktif program: '.(int)$snapshot['count'].' · Son elle doğrulama: '.esc_html($verified['verified_at'] ?? 'Henüz yok').'</p>';
    echo '<p>Bu ekran WordPress metnini son elle doğrulanan metinle karşılaştırır. Kommo’yu arka planda sorgulamaz veya güncellemez. Kommo’da yapılan bağımsız değişiklikleri tespit etmez. Program, fiyat, tarih, seans veya adres değişince doğrulama yeniden gerekir.</p>';
    foreach (array('program'=>'Program kaynağı', 'locations'=>'Konum kaynağı') as $key=>$label) {
        echo '<h3>'.esc_html($label).'</h3><textarea readonly style="width:100%;min-height:180px" aria-label="'.esc_attr($label).'">'.esc_textarea($snapshot[$key]).'</textarea>';
    }
    echo '<p>Kommo → AI temsilcisi → Kaynaklar bölümünde mevcut program ve konum metinlerini güncelleyin. Önizlemede şehir, seans, fiyat ve adres yanıtlarını kontrol edin. Yeni kaynak oluşturmadan mevcut metinleri düzenleyin.</p><form method="post">';
    wp_nonce_field('mdg_kf_confirm');
    foreach (array('program'=>'Program metnini Kommo’da doğruladım', 'locations'=>'Konum metnini Kommo’da doğruladım') as $key=>$label) {
        echo '<input type="hidden" name="'.esc_attr($key.'_hash').'" value="'.esc_attr($snapshot['hashes'][$key]).'"><p><label><input type="checkbox" name="verified_'.esc_attr($key).'" value="1" required> '.esc_html($label).'</label></p>';
    }
    echo '<button class="button button-primary" name="mdg_kf_confirm" value="1">Elle doğrulamayı kaydet</button></form></div>';
}
