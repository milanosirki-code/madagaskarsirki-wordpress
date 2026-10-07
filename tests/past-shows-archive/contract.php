<?php
$source_path = __DIR__ . '/../../docs/code-snippets/ms-gosteri-arsivi-v1.php.txt';
$source = file_get_contents($source_path);
if (false === $source) { fwrite(STDERR, "archive source unreadable\n"); exit(1); }

$checks = array(
    "HAVING MAX(s.end_at) < %s" => "archive waits until all sessions have ended",
    "e.status NOT IN ('cancelled','postponed','draft')" => "non-playable statuses excluded",
    "AND e.id <> 6" => "legacy Eskişehir no-show excluded",
    "AND e.id <> 15" => "legacy Kırıkkale no-show excluded",
    "add_shortcode(" => "archive shortcode registered",
    "'ms_gosteri_arsivi'" => "expected shortcode name present",
);
foreach ($checks as $needle => $label) {
    if (false === strpos($source, $needle)) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
$forbidden = array('$wpdb->insert(', '$wpdb->update(', '$wpdb->delete(', 'wp_insert_post(', 'wp_update_post(');
foreach ($forbidden as $needle) {
    if (false !== strpos($source, $needle)) {
        fwrite(STDERR, "FAIL: write primitive found: {$needle}\n");
        exit(1);
    }
}
echo "past-shows archive contract OK\n";
