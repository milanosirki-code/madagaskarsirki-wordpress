<?php
// Exercise the actual routing callback in isolated processes because redirects exit.
if (isset($argv[1]) && '--request' === $argv[1]) {
    define('ABSPATH', __DIR__);
    $_SERVER['REQUEST_URI'] = $argv[2];
    $GLOBALS['context'] = $argv[3] ?? 'frontend';
    function is_admin() { return 'admin' === $GLOBALS['context']; }
    function wp_doing_ajax() { return 'ajax' === $GLOBALS['context']; }
    function wp_doing_cron() { return 'cron' === $GLOBALS['context']; }
    function wp_unslash($value) { return $value; }
    function wp_parse_url($value, $component) { return parse_url($value, $component); }
    function home_url($path) { return 'https://madagaskarsirki.com' . $path; }
    function add_action($hook, $callback, $priority) {
        if ('template_redirect' !== $hook || 1 !== $priority) { exit(2); }
        $GLOBALS['callback'] = $callback;
    }
    function wp_safe_redirect($url, $status, $by) {
        echo json_encode(array('url' => $url, 'status' => $status, 'by' => $by));
    }
    require __DIR__ . '/../../wp-content/plugins/madagaskar-legacy-redirects/madagaskar-legacy-redirects.php';
    ($GLOBALS['callback'])();
    echo 'unmatched';
    exit;
}
$cases = array(
    array('/iletisim', '/iletisim-2/'),
    array('/iletisim/', '/iletisim-2/'),
    array('/iletisim/?utm_source=google', '/iletisim-2/'),
    array('/sehirler-2/antalya/', '/sehirler/antalya/'),
    array('/sehirler-2/gaziantep/', '/sehirler/gaziantep/'),
    array('/gosteriler/sahne-arkasi/', '/sahne-arkasi/'),
    array('/madagaskar-blog/cocuklar-ilk-kez-sirke-giderken-bilmeniz-gerekenler/', '/madagaskar-blog/cocuklar-ilk-kez-sirke-giderken-bilmeniz-gereken-10-sey/'),
    array('/ana-sayfa/', '/'),
    array('/galeri/basin-gorselleri/', '/galeri/'),
    array('/shop/', '/bilet-al/'),
    array('/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1200-bileti/', '/bilet-al/'),
    array('/iletisim-2/', null),
    array('/sehirler/antalya/', null),
    array('/wp-json/wc/store/v1/products?related=2997&per_page=10', null),
    array('/odeme/', null),
    array('/sepet/', null),
    array('/etkinlik/madagaskar-sirki-izmir-08-kasim-2026/', null),
    array('/iletisim/', null, 'admin'),
    array('/iletisim/', null, 'ajax'),
    array('/iletisim/', null, 'cron'),
);
foreach ($cases as $case) {
    $process = proc_open(array(PHP_BINARY, __FILE__, '--request', $case[0], $case[2] ?? 'frontend'), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $status = proc_close($process);
    $expected = null === $case[1] ? 'unmatched' : json_encode(array('url' => 'https://madagaskarsirki.com' . $case[1], 'status' => 301, 'by' => 'Madagaskar Legacy Redirects'));
    if (0 !== $status || '' !== $errors || $output !== $expected) {
        fwrite(STDERR, 'FAIL ' . json_encode($case) . ': ' . $output . $errors . "\n"); exit(1);
    }
}
echo 'legacy redirect runtime OK (' . count($cases) . " cases)\n";
