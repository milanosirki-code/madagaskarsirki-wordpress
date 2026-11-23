<?php
// php tests/pilot-040.php <plugin_dir>
declare(strict_types=1);
define('ABSPATH', '/');
$dir = $argv[1];
$OPT = [];
function get_option($k, $d = false) { global $OPT; return $OPT[$k] ?? $d; }
function update_option($k, $v, $a = null) { global $OPT; $OPT[$k] = $v; return true; }
function delete_option($k) { global $OPT; unset($OPT[$k]); }
function wp_get_environment_type() { return 'production'; }
function home_url($p = '') { return 'https://madagaskarsirki.com/'; }
function wc_get_page_id($x) { return 1251; }
function has_block($b, $c) { return true; }
function get_post($id) { return (object)['ID'=>1251,'post_type'=>'page','post_status'=>'publish','post_content'=>'<!-- wp:woocommerce/checkout --> wp-block-tickera-checkout-fields']; }
function get_woocommerce_currency() { return 'TRY'; }
function wc_tax_enabled() { return false; }
define('WC_VERSION', '11.3.1');
define('MDG_BILET_VERSION', '3.6.3-ticket-invalidation-dry-run');
class MDG_Bilet_Yonetimi_V4 { const VERSION = '4.0.15-transition'; }
class Paytr_Payment_Gateway {}
class TC_WooCommerce_Bridge {}
eval('namespace Tickera; class TC {}');
final class MS12_Catalog {
    public static $rows = [];
    public static function upcoming(): array { return self::$rows; }
    public static function session(int $sid): ?object { foreach (self::$rows as $r) if ((int)$r->session_id === $sid) return $r; return null; }
}
require $dir . '/includes/class-ms12-pilot.php';
$fail = 0; $n = 0;
function ok($c, $m) { global $fail, $n; $n++; if (!$c) { $fail++; echo "FAIL: $m\n"; } }
$r = fn($e, $s) => (object)['event_id'=>$e, 'session_id'=>$s];
MS12_Catalog::$rows = [$r(59,309), $r(59,310), $r(60,311), $r(63,319), $r(64,400)];

ok(MS12_Pilot::dependencies(), 'bağımlılıklar geçer');
ok(MS12_Pilot::globally_on(), 'varsayılan genel açık');
ok(MS12_Pilot::enabled_sessions() === [309,310,311,319,400], 'satıştaki tüm seanslar açık (opt-in yok)');
ok(MS12_Pilot::enabled_sessions(59) === [309,310], 'etkinlik filtresi');
ok(MS12_Pilot::available(60, 311), 'Burhaniye 311 salon teyidi olmadan açık');
ok(MS12_Pilot::available(63, 319), 'Antalya 319 ek kanıt olmadan açık');
ok(MS12_Pilot::available(64, 400), 'listede olmayan yeni etkinlik de açık');
ok(!MS12_Pilot::available(59, 311), 'yanlış etkinlik-seans eşleşmesi kapalı');
ok(!MS12_Pilot::available(59, 999), 'satışta olmayan seans kapalı');
update_option('ms12_stopped_sessions', [310]);
ok(!MS12_Pilot::available(59, 310), 'durdurulan seans kapalı');
ok(MS12_Pilot::enabled_sessions(59) === [309], 'durdurulan seans listeden çıkar');
update_option('ms12_auto_all', 'off');
ok(MS12_Pilot::enabled_sessions() === [] && !MS12_Pilot::available(59,309), 'genel kapalıyken hiçbir seans açık değil');
update_option('ms12_auto_all', 'on');
ok(MS12_Pilot::available(59,309), 'genel yeniden açık');
$src = file_get_contents($dir . '/includes/class-ms12-pilot.php');
ok(strpos($src, 'written-hours-verified') === false && strpos($src, 'paid-reconciled') === false, 'salon teyidi ve pilot kutuları kodda yok');
$cat = file_get_contents($dir . '/includes/class-ms12-catalog.php');
ok(strpos($cat, 'MS12_Pilot::matches') === false && strpos($cat, 'canonical_payment_ready') === false, 'profil ve Antalya ek şartı kaldırıldı');
ok(strpos($cat, "MS12_Rules::price_cents") !== false && strpos($cat, 'self::types(') !== false, 'fiyat kuralı ve ürün eşleşmesi korunuyor');
echo "$n kontrol, $fail hata\n";
exit($fail ? 1 : 0);
