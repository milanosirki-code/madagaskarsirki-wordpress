<?php
$path = __DIR__ . '/../../wp-content/plugins/madagaskar-legacy-redirects/madagaskar-legacy-redirects.php';
$src = file_get_contents($path);
if (false === $src) { fwrite(STDERR, "source unreadable\n"); exit(1); }

$required = array(
    "Version: 1.0.3",
    "MDG_LEGACY_REDIRECTS_VERSION', '1.0.3",
    "'/shop' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-26-eylul-2026-1200-bileti' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-26-eylul-2026-1400-bileti' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-26-eylul-2026-1600-bileti' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1200-bileti' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1400-bileti' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1600-bileti' => '/bilet-al/'",
    "'/ana-sayfa' => '/'",
    "'/gosteriler__trashed/sahne-arkasi' => '/sahne-arkasi/'",
    "'/sehirler__trashed-2/gaziantep' => '/sehirler/gaziantep/'",
    "'/galeri/basin-gorselleri' => '/galeri/'",
    "'/gosteriler__trashed/akrobasi' => '/gosteriler/akrobasi/'",
    "'/galeri__trashed/basin-gorselleri' => '/galeri/'",
    "'/galeri/fotograflar' => '/galeri/'",
    "'/gosteriler/jonglor' => '/jonglor/'",
    "'/sehirler-2' => '/sehirler/'",
    "'/sehirler-2/mersin' => '/sehirler/mersin/'",
    "'/festival-organizasyonlari' => '/kurumsal/festival-organizasyonlari/'",
    "'/sehirler-2/ankara' => '/sehirler/ankara/'",
    "'/sehirler-2/konya' => '/sehirler/konya/'",
    "'/galeri-2' => '/galeri/'",
    "'/gosteriler-2' => '/gosteriler/'",
    "wp_safe_redirect(",
    "301,",
);
foreach ($required as $needle) {
    if (false === strpos($src, $needle)) {
        fwrite(STDERR, "missing contract: {$needle}\n");
        exit(1);
    }
}
if (false !== strpos($src, "wp_redirect(")) {
    fwrite(STDERR, "unsafe redirect helper present\n");
    exit(1);
}
echo "legacy redirect contract OK\n";
