<?php
/**
 * Plugin Name: MDG Finans Yönetici Pilotu (Salt Okunur)
 * Description: V5 ve WooCommerce kayıtlarını değiştirmeyen, ayrı görsel yönetici ekranı. Kesin banka bakiyesi veya kesin finansal kapanış iddiasında bulunmaz.
 * Version: 0.1.1
 * Author: Dünya Organizasyon - Finans Pilot
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class MDG_Finans_Yonetici_Pilot {
    const SLUG = 'mdg-finans-yonetici-pilot';
    const VERSION = '0.1.1';

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 9900);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('admin_head', array(__CLASS__, 'pilot_menu_visibility'), 100000);
    }

    public static function menu() {
        if (!current_user_can('manage_woocommerce')) return;
        // A new menu only: original V5 and MMC pages, options and routes are unchanged.
        add_submenu_page('mdg-dashboard', 'Finans Yönetici Pilotu', 'Finans Pilot (Önizleme)', 'manage_woocommerce', self::SLUG, array(__CLASS__, 'render'));
    }

    /** V5 visually hides unknown submenu slugs. Restore only this pilot entry. */
    public static function pilot_menu_visibility() {
        if (!current_user_can('manage_woocommerce')) return;
        echo '<style id="mdgfp-menu-visibility">#toplevel_page_mdg-dashboard .wp-submenu li:has(> a[href*="page=mdg-finans-yonetici-pilot"]){display:list-item!important}</style>';
    }

    public static function enqueue() {
        if (!current_user_can('manage_woocommerce') || sanitize_key($_GET['page'] ?? '') !== self::SLUG) return;
        wp_enqueue_style('mdg-finance-pilot', plugin_dir_url(__FILE__) . 'assets/pilot.css', array(), self::VERSION);
        wp_enqueue_script('mdg-finance-pilot', plugin_dir_url(__FILE__) . 'assets/pilot.js', array(), self::VERSION, true);
    }

    private static function table($name) { global $wpdb; return $wpdb->prefix . 'mdg_v5_' . $name; }
    private static function is_refund($row) {
        $category = trim((string)($row->category ?? ''));
        return 'program' === ($row->scope ?? '')
            && ('İade / WooCommerce' === $category
                || (15 === (int)($row->event_id ?? 0) && 'İade / Biletinial' === $category));
    }

    /** Read live WooCommerce paid order items. No payment, order, or ticket write. */
    private static function web_revenues($event_ids, &$warnings) {
        global $wpdb;
        $output = array();
        if (!$event_ids) return $output;
        if (!class_exists('MDG_DB') || !function_exists('wc_get_order')) {
            $warnings[] = 'WooCommerce veya MDG kaynak servisi kullanılamıyor: web satış tutarları eksik.';
            return $output;
        }
        $ids = array_values(array_unique(array_map('absint', $event_ids)));
        $holders = implode(',', array_fill(0, count($ids), '%d'));
        $map = MDG_DB::table('order_map');
        $rows = (array)$wpdb->get_results($wpdb->prepare("SELECT event_id,order_id,order_item_id,line_total FROM {$map} WHERE event_id IN ({$holders})", $ids));
        if ($wpdb->last_error) {
            $warnings[] = 'Web satış eşlemesi veritabanından okunamadı.';
            return $output;
        }
        $statuses = array('processing','completed','paid');
        if (function_exists('wc_get_is_paid_statuses')) $statuses = array_unique(array_merge($statuses, (array)wc_get_is_paid_statuses()));
        $orders = array(); $seen = array(); $duplicates = 0; $missing = 0;
        foreach ($rows as $row) {
            $eid = absint($row->event_id); $oid = absint($row->order_id); $iid = absint($row->order_item_id);
            $identity = $eid . '|' . $oid . '|' . $iid;
            if (isset($seen[$identity])) { $duplicates++; continue; }
            $seen[$identity] = true;
            if (!array_key_exists($oid, $orders)) $orders[$oid] = wc_get_order($oid);
            $order = $orders[$oid];
            if (!$order) { $missing++; continue; }
            if (!in_array($order->get_status(), $statuses, true) || !$order->get_date_paid()) continue;
            $item = $order->get_item($iid);
            if (!$item) { $missing++; continue; }
            $refunded = abs((float)$order->get_total_refunded_for_item($iid));
            $output[$eid] = ($output[$eid] ?? 0) + max(0.0, (float)$row->line_total - $refunded);
        }
        if ($duplicates) $warnings[] = $duplicates . ' tekrar eden eşleme satırı tekilleştirildi; V5 ile karşılaştırma gerekiyor.';
        if ($missing) $warnings[] = $missing . ' satış kaleminin güncel WooCommerce karşılığı doğrulanamadı.';
        return $output;
    }

    private static function brands(&$warnings) {
        global $wpdb;
        $map = array();
        $bridge = $wpdb->prefix . 'mmc_mdg_event_bridge';
        $programs = $wpdb->prefix . 'mmc_programs';
        $rows = (array)$wpdb->get_results("SELECT b.mdg_event_id,p.brand FROM {$bridge} b INNER JOIN {$programs} p ON p.id=b.program_id WHERE b.is_active=1");
        if ($wpdb->last_error) {
            $warnings[] = 'MMC marka köprüsü okunamadı; markası doğrulanamayan etkinlikler ayrı gösterildi.';
            return $map;
        }
        foreach ($rows as $row) {
            $id = absint($row->mdg_event_id);
            $brand = trim((string)$row->brand);
            $label = stripos($brand, 'Milano') !== false ? 'milano' : (stripos($brand, 'Madagaskar') !== false ? 'madagaskar' : 'unknown');
            if (isset($map[$id]) && $map[$id] !== $label) $map[$id] = 'unknown';
            else $map[$id] = $label;
        }
        return $map;
    }

    public static function snapshot($month) {
        global $wpdb;
        $warnings = array();
        if (!preg_match('/^20\d{2}-(?:0[1-9]|1[0-2])$/', $month)) $month = wp_date('Y-m');
        if (!class_exists('MDG_V5_Finance') || !class_exists('MDG_V5_Finance_Records') || !class_exists('MDG_DB')) {
            return array('month'=>$month,'rows'=>array(),'warnings'=>array('V5 finans veya MDG servisi etkin değil. Veri üretilmedi.'),'shared'=>null);
        }
        $events = (array)$wpdb->get_results('SELECT * FROM ' . MDG_DB::table('events') . ' ORDER BY id DESC LIMIT 1000');
        $events = MDG_V5_Finance::month_events($events, $month);
        $ids = array_keys($events); $from = $month.'-01'; $to = gmdate('Y-m-t', strtotime($from));
        $income_rows = (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('incomes').' WHERE income_date BETWEEN %s AND %s',$from,$to));
        $expense_rows = (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('expenses').' WHERE expense_date BETWEEN %s AND %s',$from,$to));
        $fixed_rows = (array)$wpdb->get_results($wpdb->prepare('SELECT total_amount FROM '.self::table('fixed_costs').' WHERE cost_month=%s',$month));
        $attendance = (array)$wpdb->get_results('SELECT event_id,include_in_reports FROM '.self::table('attendance'),OBJECT_K);
        if ($wpdb->last_error) $warnings[] = 'Finans kayıtlarının bir kısmında okuma hatası oluştu.';
        $web = self::web_revenues($ids, $warnings); $brand_map = self::brands($warnings);
        $income = array(); $direct = array(); $category = array(); $evidence = array(); $pending = array(); $unknown_income = 0; $refund_count = 0;
        foreach ($income_rows as $row) {
            if (strtolower(trim((string)$row->channel)) === 'woocommerce') continue; // Archived web snapshots are NOT new revenue.
            if (($row->scope ?? '') !== 'program') { $unknown_income += (float)MDG_V5_Finance_Records::collected($row); continue; }
            $id = absint($row->event_id);
            $evidence[$id] = true;
            $settled = (float)MDG_V5_Finance_Records::collected($row);
            $income[$id] = ($income[$id] ?? 0) + $settled;
            $pending[$id] = ($pending[$id] ?? 0) + max(0, (float)$row->net_amount - $settled);
        }
        foreach ($expense_rows as $row) {
            if (self::is_refund($row)) { $refund_count++; continue; }
            if (($row->scope ?? '') !== 'program') continue;
            $id = absint($row->event_id);
            $evidence[$id] = true;
            $direct[$id] = ($direct[$id] ?? 0) + (float)$row->amount;
            $category[$id][$row->category] = ($category[$id][$row->category] ?? 0) + (float)$row->amount;
        }
        $shares = array(); $distributed = 0;
        foreach (MDG_V5_Finance::allocated_rows($expense_rows) as $part) {
            $id = absint($part['event_id']);
            $amt = (float)$part['amount']; $shares[$id] = ($shares[$id] ?? 0) + $amt; $distributed += $amt;
            $label = (string)$part['source']->category . ' · ortak pay';
            $category[$id][$label] = ($category[$id][$label] ?? 0) + $amt;
        }
        $general = 0;
        foreach ($expense_rows as $row) if (($row->scope ?? '') !== 'program' && !self::is_refund($row)) $general += (float)$row->amount;
        $fixed = array_sum(array_map(static function($r){return (float)$r->total_amount;},$fixed_rows));
        $eligible = array_filter($events, static function($e) use ($attendance){$id=(int)$e->id;return !isset($attendance[$id]) || (int)$attendance[$id]->include_in_reports === 1;});
        $counts = (array)get_option('mdg_v5_month_program_counts', array());
        $count = array_key_exists($month,$counts) ? absint($counts[$month]) : count($eligible);
        $fixed_per_program = $count > 0 ? $fixed/$count : 0;
        $rows = array(); $empty_program_count = 0;
        foreach ($events as $id=>$event) {
            $id = absint($id);
            $brand = $brand_map[$id] ?? 'unknown';
            $is_eligible = isset($eligible[$id]);
            // An empty ledger is not evidence of zero actual sales or zero costs.
            $available = !empty($evidence[$id]) || (float)($web[$id] ?? 0) > 0 || (float)($shares[$id] ?? 0) > 0 || ($is_eligible && $fixed_per_program > 0);
            if (!$available) $empty_program_count++;
            $cost = (float)($direct[$id]??0) + (float)($shares[$id]??0) + ($is_eligible ? $fixed_per_program : 0);
            $rev = (float)($web[$id]??0) + (float)($income[$id]??0);
            $rows[] = array(
                'id'=>$id,'name'=>MDG_V5_Finance::event_name($event),
                'date'=>MDG_V5_Finance::event_date($event),'brand'=>$brand,
                'web'=>round((float)($web[$id]??0),2),'other'=>round((float)($income[$id]??0),2),
                'revenue'=>round($rev,2), 'direct'=>round((float)($direct[$id]??0),2),
                'common'=>round((float)($shares[$id]??0),2), 'fixed'=>round($is_eligible?$fixed_per_program:0,2),
                'cost'=>round($cost,2), 'result'=>round($rev-$cost,2),
                'categories'=>($category[$id]??array()),
                'eligible'=>$is_eligible, 'available'=>$available,
                'pending'=>round((float)($pending[$id]??0),2)
            );
        }
        if ($empty_program_count) $warnings[] = $empty_program_count.' programda gelir veya maliyet kaydı bulunamadı; bunlar doğrulanmış sıfır sonuç olarak sunulmuyor.';
        if ($unknown_income > 0) $warnings[] = 'Programa bağlanmamış tahsilatlar (' . number_format_i18n($unknown_income,2) . ' TL) program sonuçlarında gösterilmiyor.';
        if ($general > $distributed + 0.01) $warnings[] = 'Dağıtılmamış ortak gider: ' . number_format_i18n(max(0,$general-$distributed),2) . ' TL. Program kârı kesin değildir.';
        if ($refund_count) $warnings[] = $refund_count.' bilet iadesi nakit hareketi maliyetin tekrarına yol açmamak için hariç tutuldu.';
        if (!$brand_map) $warnings[] = 'Marka bilgileri doğrulanamadı; marka filtresinde bilinmeyenler ayrıdır.';
        return array('month'=>$month,'rows'=>$rows,'warnings'=>array_values(array_unique($warnings)),
            'shared'=>array('general'=>round($general,2),'distributed'=>round($distributed,2),
                'unallocated'=>round(max(0,$general-$distributed),2),'fixed_total'=>round($fixed,2),
                'fixed_count'=>$count),
            'source'=>'Canlı WordPress V5 + WooCommerce, ön değerlendirme; banka mutabakatı değildir');
    }

    public static function render() {
        if (!current_user_can('manage_woocommerce')) wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
        $month = sanitize_text_field(wp_unslash($_GET['month'] ?? wp_date('Y-m')));
        if (!preg_match('/^20\d{2}-(?:0[1-9]|1[0-2])$/', $month)) $month = wp_date('Y-m');
        $snapshot = self::snapshot($month);
        echo '<div class="wrap mdgfp-root"><div class="mdgfp-header"><div><p class="mdgfp-eyebrow">DÜNYA ORGANİZASYON · KARAR DESTEK</p><h1>Finans ve Kârlılık · Yönetici Pilotu</h1><p class="mdgfp-sub">Salt okunur önizleme · V5 kaynakları · '.esc_html($month).' · '.esc_html(wp_date('d.m.Y H:i')).'</p></div><a class="button" href="'.esc_url(admin_url('admin.php?page=mdg-v5-finance')).'">Mevcut finans merkezine dön</a></div>';
        echo '<div class="mdgfp-intro">Bu ekran belge, gelir, gider, sipariş, bilet veya maliyet dağıtımı kaydetmez. Sonuçlar kayıtlı/vergi-bazı farklı olabilen geçici rakamlardır. Kesin banka bakiyesi değildir.</div>';
        echo '<form method="get" class="mdgfp-controls"><input type="hidden" name="page" value="'.esc_attr(self::SLUG).'"><label>Rapor ayı <input type="month" name="month" value="'.esc_attr($month).'"></label><button type="submit" class="button button-primary">Ayı getir</button><label>Marka <select id="mdgfp-brand"><option value="all">Tüm markalar</option><option value="madagaskar">Madagaskar</option><option value="milano">Milano</option><option value="unknown">Markası teyitsiz</option></select></label><label>Program <select id="mdgfp-event"><option value="all">Tüm programlar</option></select></label></form>';
        echo '<div id="mdgfp-alerts" class="mdgfp-warnings"></div><div class="mdgfp-kpis" id="mdgfp-kpis"></div>';
        echo '<div class="mdgfp-grid"><section class="mdgfp-card"><h2>Program bazında gelir – maliyet</h2><p>Kayıtlı web geliri + diğer tahsilat beyanı ile doğrudan, ortak ve sabit maliyet paylarının karşılaştırılması</p><div id="mdgfp-bars"></div></section><section class="mdgfp-card"><h2>Kâr / zarar sıralaması</h2><p>Kesinleşmiş muhasebe kârı değil, seçili kayıtlara göre geçici sonuç</p><div id="mdgfp-ranking"></div></section></div>';
        echo '<div class="mdgfp-grid"><section class="mdgfp-card"><h2>Giderin kaynakları</h2><div id="mdgfp-costs"></div></section><section class="mdgfp-card"><h2>Ortak gider kapsamı</h2><div id="mdgfp-shared"></div></section></div>';
        echo '<div class="mdgfp-card"><h2>Programların finans kayıtları</h2><div id="mdgfp-detail"></div></div>';
        echo '<script id="mdgfp-data" type="application/json">'.wp_json_encode($snapshot,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script></div>';
    }
}
MDG_Finans_Yonetici_Pilot::boot();

