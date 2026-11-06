<?php
define('ABSPATH',__DIR__);
function add_action(){}function add_filter(){}function home_url($p){return 'https://madagaskarsirki.com'.$p;}function esc_url($u){return htmlspecialchars($u,ENT_QUOTES);}
require __DIR__.'/../wp-content/plugins/mdg-visitor-pilot/mdg-visitor-pilot.php';
echo '<!doctype html><html lang="tr"><meta charset="utf-8"><body>'.\Madagaskar\Visitor\Page::markup().'<script id="vp-config" type="application/json">{"origin":"https://madagaskarsirki.com","api":"https://madagaskarsirki.com/wp-json/mdg-visitor/v1/"}</script></body></html>';
