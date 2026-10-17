<?php
define('ABSPATH', '/');
function add_action() {}
require dirname(__DIR__) . '/wp-content/plugins/mdg-finans-yonetici-pilot/mdg-finans-yonetici-pilot.php';
$fn = new ReflectionMethod('MDG_Finans_Yonetici_Pilot', 'is_refund');
$cases = array(
    array(15, 'İade / Biletinial', true),
    array(99, 'İade / Biletinial', false),
    array(99, 'İade / WooCommerce', true),
    array(15, 'Salon Kirası', false),
);
foreach ($cases as $case) {
    $got = $fn->invoke(null, (object)array('scope'=>'program','event_id'=>$case[0], 'category'=>$case[1]));
    if ($got !== $case[2]) {fwrite(STDERR, "Refund exception test failed\n"); exit(1);}
}
echo "finance pilot refund exceptions: 4 passed\n";
