<?php
/**
 * Plugin Name: Madagaskar Sirki – Süper 12
 * Description: Süper 12 (4 yetişkin + 8 çocuk) paketini, aile paketleri gibi satıştaki tüm seanslarda sunar.
 * Version: 0.4.1
 * Requires PHP: 8.0
 */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/class-ms12-rules.php';
require_once __DIR__ . '/includes/class-ms12-pilot.php';
require_once __DIR__ . '/includes/class-ms12-antalya.php';
require_once __DIR__ . '/includes/class-ms12-catalog.php';

final class MS12_Plugin {
    private const VERSION = '0.4.1';

    public static function boot(): void {
        if (!class_exists('MDG_DB') || !function_exists('wc_get_product')) { return; }
        add_action('admin_menu', [self::class, 'menu']);
        if (MS12_Pilot::live_site()) add_action('admin_post_ms12_pilot_switch', [MS12_Pilot::class, 'admin_switch']);
        // On the live host keep validation present even while disabled to catch old marked carts.
        if (!self::lab_enabled() && !MS12_Pilot::live_site()) { return; }
        $adapter = __DIR__ . '/includes/class-ms12-checkout.php';
        if (!is_readable($adapter)) { return; }
        require_once $adapter;
        if (!class_exists('MS12_Checkout') || !is_callable([MS12_Checkout::class, 'boot'])) { return; }
        MS12_Checkout::boot([MS12_Catalog::class, 'checkout_quote']);
        add_action('wp_enqueue_scripts', [self::class, 'styles'], 99);
        add_action('wp_footer', [self::class, 'assets'], 999);
    }

    public static function lab_enabled(): bool {
        if (!defined('MS12_ISOLATED_LAB') || MS12_ISOLATED_LAB !== true
            || !defined('MS12_LAB_HOST') || !is_string(MS12_LAB_HOST)
            || !in_array(wp_get_environment_type(), ['local', 'development', 'staging'], true)) { return false; }
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        return is_string($host) && hash_equals(MS12_LAB_HOST, $host)
            && !in_array(strtolower($host), ['madagaskarsirki.com', 'www.madagaskarsirki.com'], true);
    }

    public static function styles(): void {
        if (!self::sessions_for_mode()) { return; }
        wp_enqueue_style('ms12-public', plugins_url('assets/super12.css', __FILE__), [], self::VERSION);
    }

    private static function sessions_for_mode(): array {
        $event = self::public_event_id();
        if (!$event) return [];
        return self::lab_enabled() ? MS12_Catalog::lab_sessions() : MS12_Pilot::enabled_sessions($event);
    }

    private static function public_event_id(): int {
        static $event_id;
        if ($event_id !== null) return $event_id;
        $event_id = 0;
        // The native MDG rewrite and template redirect both resolve this exact
        // registered query var. All other WordPress frontends skip Woo/MDG lookup.
        if (!empty($_GET['mdg_event_preview']) || !function_exists('get_query_var') || !class_exists('MDG_Events')) return 0;
        $slug = get_query_var('mdg_event_slug');
        if (!is_string($slug) || $slug === '') return 0;
        $event = MDG_Events::get_by_public_slug($slug);
        if (!$event || ($event->status ?? '') !== 'onsale') return 0;
        return $event_id = (int)($event->id ?? 0);
    }

    public static function assets(): void {
        $eid = self::public_event_id();
        if (!$eid) return;
        $offers = [];
        foreach (self::sessions_for_mode() as $sid) {
            $offer = MS12_Catalog::checkout_quote($eid, $sid);
            if ($offer) {
                $item = ['priceCents' => $offer['priceCents'], 'maxBundles' => $offer['maxBundles']];
                // Karttaki tasarruf satırı için seansın internet tekil fiyatları (kuruş).
                $adult = self::cents((string)($offer['adultPrice'] ?? ''));
                $child = self::cents((string)($offer['childPrice'] ?? ''));
                if ($adult && $child) { $item['adultCents'] = $adult; $item['childCents'] = $child; }
                $offers[$eid][$sid] = $item;
            }
        }
        if (!$offers) { return; }
        wp_print_inline_script_tag('window.MDG_SUPER12_OFFERS=' . wp_json_encode(
            $offers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';');
        // The MDG deferred frontend takes one snapshot of the ticket rows.
        // Install ours synchronously only after the native widget/config exists.
        wp_print_script_tag(['src' => add_query_arg('ver', self::VERSION, plugins_url('assets/super12.js', __FILE__))]);
    }

    private static function cents(string $money): ?int {
        if (!preg_match('/\A([0-9]{1,7})(?:\.([0-9]{1,2}))?\z/', $money, $m)) return null;
        $v = (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
        return $v > 0 ? $v : null;
    }

    public static function menu(): void {
        add_management_page('Süper 12 denetimi', 'Madagaskar Süper 12', 'manage_woocommerce', 'madagaskar-super12', [self::class, 'admin']);
    }

    public static function admin(): void {
        if (!current_user_can('manage_woocommerce')) { return; }
        echo '<div class="wrap"><h1>Madagaskar Sirki – Süper 12</h1>';
        echo '<p>Fiyat kuralı: 500 TL → 2.250 TL; 600 TL → 2.500 TL. Onaylı 26 seansta ayrıca 490 TL → 2.250 TL, 580 TL → 2.500 TL.</p>';
        echo '<p>' . esc_html(MS12_Pilot::summary()) . '</p>';
        MS12_Pilot::admin_controls();
        $rows = MS12_Catalog::upcoming();
        if (!$rows) { echo '<p>Okunabilir, satışta ve gelecekte olan seans bulunamadı.</p></div>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Seans (Türkiye)</th><th>Web yetişkin</th><th>Paket / Durum</th><th>Süper 12</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $eid = (int) $row->event_id;
            $sid = (int) $row->session_id;
            $types = MS12_Catalog::types($row);
            $price = $types ? $types['adultPrice'] . ' TL' : 'Doğrulanamadı';
            $package = 'Eşleşme / WooCommerce fiyatı incelenmeli';
            if ($types) {
                try { $cents = MS12_Rules::price_cents($types['adultPrice'], MS12_Catalog::approved_online_tariff($eid, $sid)); $package = number_format($cents / 100, 0, ',', '.') . ' TL · fiyat kuralına uygun'; }
                catch (InvalidArgumentException $e) { $package = 'Tarife onayı gerekli'; }
            }
            $t = new DateTimeImmutable($row->start_at, new DateTimeZone('UTC'));
            $time = $t->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('d.m.Y H:i');
            $blocker = MS12_Catalog::deployment_blocker($eid, $sid);
            if (MS12_Pilot::session_is_stopped($sid)) { $status = 'Durduruldu'; }
            elseif (!MS12_Pilot::globally_on()) { $status = 'Genel kapalı'; }
            elseif ($blocker !== null) { $status = 'Görünmüyor: ' . $blocker; }
            else { $status = 'Açık'; }
            printf('<tr><td>%s (#%d)</td><td>%s (#%d)</td><td>%s</td><td>%s</td><td>%s %s</td></tr>',
                esc_html($row->title), $eid, esc_html($time), $sid, esc_html($price), esc_html($package),
                esc_html($status), MS12_Pilot::session_status_html($eid, $sid));
        }
        echo '</tbody></table></div>';
    }
}

add_action('plugins_loaded', [MS12_Plugin::class, 'boot'], 40);
