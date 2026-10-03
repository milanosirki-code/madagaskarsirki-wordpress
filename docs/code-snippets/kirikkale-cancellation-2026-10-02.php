<?php
/**
 * Kırıkkale cancellation operation, 2026-10-02.
 * Only MMC Program 2 and the exact Kırıkkale public event URL.
 * No refund, order, ticket or WooCommerce writes.
 */
add_action('admin_menu', function () {
    add_management_page('Kırıkkale İptal', 'Kırıkkale İptal', 'manage_options', 'mdg-kirikkale-cancel-20261002', function () {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>Kırıkkale — Program #2 İptal</h1>';
        echo '<p>PRG-2026-KIR-MERKEZ-001 · 02.10.2026 · Ürünler #2848 / #2852</p>';
        echo '<p>Kimlik ve mevcut satış kilitleri doğrulanır. Yalnız MMC program durumu mevcut servisle iptal edilir. İade talepleri değiştirilmez.</p>';
        if (!class_exists('MMC_Program_Service')) { echo '<p>MMC servisi bulunamadı.</p></div>'; return; }
        $program = MMC_Program_Service::get_program(2);
        echo '<p>Mevcut MMC durumu: <strong>' . esc_html($program ? $program->status : 'missing') . '</strong></p>';
        if ($program && 'cancelled' !== $program->status) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="mdg_kirikkale_cancel_20261002">';
            wp_nonce_field('mdg_kirikkale_cancel_20261002');
            submit_button('Yalnız Kırıkkale Program #2 iptal durumunu kaydet');
            echo '</form>';
        }
        echo '</div>';
    });
});
add_action('admin_post_mdg_kirikkale_cancel_20261002', function () {
    if (!current_user_can('manage_options')) { wp_die('Yetki yok.', '', array('response' => 403)); }
    check_admin_referer('mdg_kirikkale_cancel_20261002');
    if (!class_exists('MMC_Program_Service') || !function_exists('wc_get_product')) { wp_die('Gerekli servis yok.'); }
    $program = MMC_Program_Service::get_program(2);
    if (!$program || 'PRG-2026-KIR-MERKEZ-001' !== $program->program_code
        || 'Kırıkkale' !== $program->province_name || 'Merkez' !== $program->district_name
        || '2026-10-02' !== $program->planned_date) { wp_die('Program kimliği eşleşmedi; değişiklik yapılmadı.'); }
    $map = array(2848 => array(2849, 2850, 2851), 2852 => array(2853, 2854, 2855));
    foreach ($map as $parent_id => $ids) {
        $parent = wc_get_product($parent_id);
        if (!$parent || !$parent->is_type('variable') || false === strpos($parent->get_name(), 'Kırıkkale') || $parent->is_purchasable()) {
            wp_die('Ana ürün kimliği veya satış kilidi doğrulanamadı; değişiklik yapılmadı.');
        }
        $children = array_map('intval', $parent->get_children());
        sort($children); sort($ids);
        if ($children !== $ids) { wp_die('Varyasyon eşleşmedi; değişiklik yapılmadı.'); }
        foreach ($ids as $id) {
            $variation = wc_get_product($id);
            if (!$variation || (int) $variation->get_parent_id() !== $parent_id || $variation->is_purchasable()) {
                wp_die('Varyasyon kimliği veya satış kilidi doğrulanamadı; değişiklik yapılmadı.');
            }
        }
    }
    $result = MMC_Program_Service::set_status(2, 'cancelled', 'Kullanıcı talimatı: Kırıkkale 02.10.2026 iptal. V4 satış kilitleri doğrulandı; iptal kararı geçerlidir.');
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
    $after = MMC_Program_Service::get_program(2);
    if (!$after || 'cancelled' !== $after->status) { wp_die('Durum kaydı doğrulanamadı; manuel inceleme gerekli.'); }
    wp_safe_redirect(admin_url('tools.php?page=mdg-kirikkale-cancel-20261002'));
    exit;
});
add_action('template_redirect', function () {
    $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
    if ('/etkinlik/madagaskar-sirki-kirikkale-02-ekim-2026' !== untrailingslashit((string) $path)) { return; }
    // Explicit organizer cancellation: never depend on a mutable integration status.
    nocache_headers();
    wp_die('<div style="max-width:680px;margin:40px auto;text-align:center;padding:24px"><p>MADAGASKAR SİRKİ</p><h1>Kırıkkale gösterileri iptal edilmiştir</h1><p>2 Ekim 2026 · 17 Ağustos Spor Salonu<br>17:30 ve 19:00 seansları</p><p>Bu etkinlik için bilet satışı kapatılmıştır.</p><p>Bilet bedeli iadeleri başlatılmıştır. Tutarın kartınıza yansıması bankanıza göre birkaç iş günü sürebilir.</p><p><a href="https://madagaskarsirki.com/">Ana Sayfaya Dön</a> · <a href="https://wa.me/903129113710">WhatsApp Destek</a></p></div>', 'Kırıkkale Gösterisi İptal Edildi — Madagaskar Sirki', array('response' => 200));
}, -100);

/**
 * The integration verifier reset cancelled to sales_open (MMC log #424).
 * Preserve the organizer's cancellation for this one program only.
 * Remove this guard only after a separately authorized reopening decision.
 */
add_action('mmc_program_logged', function ($program_id, $action, $entity_type, $entity_id, $old_value, $new_value) {
    if (2 !== (int) $program_id || 'program_status_changed' !== $action
        || 'program' !== $entity_type || 2 !== (int) $entity_id
        || 'sales_open' !== $new_value || !class_exists('MMC_Program_Service')) { return; }
    $program = MMC_Program_Service::get_program(2);
    if (!$program || 'PRG-2026-KIR-MERKEZ-001' !== $program->program_code
        || 'Kırıkkale' !== $program->province_name || 'Merkez' !== $program->district_name
        || '2026-10-02' !== $program->planned_date || 'sales_open' !== $program->status) { return; }
    MMC_Program_Service::set_status(2, 'cancelled', 'Kırıkkale iptal koruması: entegrasyon kontrolü organizatörün iptal kararını değiştiremez.');
}, 1000, 6);
