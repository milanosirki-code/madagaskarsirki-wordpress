<?php
define('ABSPATH', __DIR__);
function add_action($hook, $callback, $priority = 10) {}
require __DIR__.'/../docs/code-snippets/kommo-source-freshness.php';
$current = mdg_kf_hashes('program-current', 'locations-current');
$cases = array(
    array($current, true),
    array(array(), false),
    array(array('program'=>$current['program']), false),
    array(mdg_kf_hashes('old-price', 'locations-current'), false),
    array(mdg_kf_hashes('program-current', 'old-address'), false),
    array(mdg_kf_hashes('old-program', 'old-address'), false),
);
foreach ($cases as $i=>$case) {
    if (mdg_kf_matches($current, $case[0]) !== $case[1]) { throw new RuntimeException('Freshness case '.$i.' failed'); }
}
// A form rendered before a program edit cannot attest to the new content.
$changed = mdg_kf_hashes('program-edited-after-render', 'locations-current');
if (mdg_kf_matches($changed, $current)) { throw new RuntimeException('Stale confirmation accepted'); }
echo "7 freshness cases passed\n";
