<?php
$path = __DIR__ . '/../../wp-content/plugins/madagaskar-legacy-redirects/madagaskar-legacy-redirects.php';
$src = file_get_contents($path);
if (false === $src) { fwrite(STDERR, "source unreadable\n"); exit(1); }

$required = array(
    "Version: 1.0.1",
    "MDG_LEGACY_REDIRECTS_VERSION', '1.0.1",
    "'/urun/madagaskar-sirki-ankara-26-eylul-2026-1200-bileti' => '/bilet-al/'",
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
