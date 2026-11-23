<?php
define('ABSPATH', 'test');
function add_action() {}
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function remove_accents($s) { return strtr($s, array('ö'=>'o','ü'=>'u','ı'=>'i','ğ'=>'g','ş'=>'s','ç'=>'c','İ'=>'I','Ö'=>'O','Ü'=>'U','Ş'=>'S','Ç'=>'C','Ğ'=>'G')); }
require __DIR__ . '/../../wp-content/plugins/madagaskar-basin-bileti/madagaskar-basin-bileti.php';
$n = 0;
function check($ok, $label) { global $n; if (!$ok) throw new Exception('FAIL ' . $label); $n++; echo "PASS $label\n"; }
function rejects($cb, $label) { try { $cb(); } catch (Exception $e) { check(true, $label); return; } throw new Exception('Expected rejection: ' . $label); }
$c = 'MDG_Press_Tickets';

check($c::org_key('Gazete Ayvalık') === $c::org_key('Ayvalık Gazetesi'), 'org key ignores gazete word and order of suffix');
check($c::org_key('Körfez Star Gazetesi') === $c::org_key('KÖRFEZ STAR'), 'org key case and suffix');
check($c::org_key('Gündem Fethiye') !== $c::org_key('Fethiye TV'), 'different orgs differ');

$k = str_repeat('a', 48);
check($c::valid_key($k, $k), 'correct access key');
check(!$c::valid_key('x', $k) && !$c::valid_key($k, '') && !$c::valid_key(null, $k), 'wrong/empty access keys rejected');

$p = $c::people(array('Ali Veli', 'Ayşe Kaya'), array('Muhabir', 'Kameraman'));
check(count($p) === 2 && $p[1]['role'] === 'Kameraman', 'two people accepted');
$p = $c::people(array('Ali Veli', ''), array('Muhabir', 'Kameraman'));
check(count($p) === 1, 'empty second row ignored');
rejects(function () use ($c) { $c::people(array('', ''), array('Muhabir', 'Muhabir')); }, 'no people rejected');
rejects(function () use ($c) { $c::people(array('Ali Veli', 'B C D', 'E F G'), array('Muhabir', 'Muhabir', 'Muhabir')); }, 'three people rejected');
rejects(function () use ($c) { $c::people(array('Ali Veli'), array('Yönetici')); }, 'unknown role rejected');
rejects(function () use ($c) { $c::people(array('Al'), array('Muhabir')); }, 'too short name rejected');

$reqs = array(
	array('id' => 'a', 'session' => 5, 'state' => 'pending', 'org_key' => 'ayvalik', 'email' => 'haber@x.com', 'people' => array(1)),
	array('id' => 'b', 'session' => 5, 'state' => 'rejected', 'org_key' => 'ayvalik', 'email' => 'haber@x.com', 'people' => array(1, 2)),
	array('id' => 'c', 'session' => 6, 'state' => 'approved', 'org_key' => 'ayvalik', 'email' => 'haber@x.com', 'people' => array(1, 2)),
	array('id' => 'd', 'session' => 5, 'state' => 'approved', 'org_key' => 'baska', 'email' => 'HABER@x.com', 'people' => array(1)),
);
check($c::used_for($reqs, 5, 'ayvalik', 'haber@x.com') === 2, 'counts same org + same email, skips rejected and other sessions');
rejects(function () use ($c, $reqs) { $c::check_limit($reqs, 5, 'ayvalik', 'haber@x.com', 1); }, 'limit blocks third ticket');
$c::check_limit($reqs, 6, 'yeni', 'yeni@y.com', 2); check(true, 'new org gets 2');
$c::check_limit($reqs, 5, 'ayvalik', 'haber@x.com', 1, 'a'); check(true, 're-check ignores own request');
rejects(function () use ($c, $reqs) { $c::check_limit($reqs, 6, 'ayvalik', 'z@z.com', 1); }, 'approved org blocked by org key even with new email');

$r = array('id' => str_repeat('f', 32), 'org' => 'Gazete Ayvalık', 'phone' => '905320000000', 'event' => 12, 'session' => 5, 'people' => array(array('name' => 'Ali Veli', 'role' => 'Muhabir'), array('name' => 'Ayşe Kaya', 'role' => 'Kameraman')));
$b = $c::batch_from_request($r, array('id' => 5, 'variation' => 9), 'Madagaskar Sirki — Ayvalık', 'Salon', 3, str_repeat('e', 32));
check($b['kind'] === 'protocol' && $b['mode'] === 'named' && $b['state'] === 'preview' && $b['user'] === 3, 'batch uses protocol console contract');
check(array_keys($b['sessions']) === array(5) && count($b['people']) === 2 && $b['orders'] === array(), 'single session, two people, no orders yet');
check(strpos($b['people'][0]['name'], 'Basın · Muhabir · Gazete Ayvalık') !== false && $b['people'][0]['seat'] === '', 'ticket label shows press role and org, unnumbered');
check($b['press_request'] === $r['id'] && $b['title'] === 'Basın — Gazete Ayvalık', 'batch linked to request');

$open = array(array('id' => 'x1', 'session' => 7, 'state' => 'pending', 'email' => 'a@b.com', 'people' => array(array('name' => 'Ali Veli'))));
check($c::duplicate_of($open, 7, 'A@B.com', array(array('name' => 'ali veli'))) === 'x1', 'duplicate detected case-insensitively');
check($c::duplicate_of($open, 7, 'a@b.com', array(array('name' => 'Ayşe Kaya'))) === '', 'different person is not duplicate');
$open[0]['state'] = 'rejected'; check($c::duplicate_of($open, 7, 'a@b.com', array(array('name' => 'Ali Veli'))) === '', 'rejected request does not block resubmission');
echo "All $n press ticket checks passed.\n";
