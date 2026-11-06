<?php
define('ABSPATH',__DIR__);
function add_action(){}function add_filter(){}function home_url($p){return 'https://madagaskarsirki.com'.$p;}function esc_url($u){return htmlspecialchars($u,ENT_QUOTES);}
function get_option($k,$d=false){return in_array($k,array('mdg_vp_enabled','mdg_vp_listed'),true)?'1':$d;}
require __DIR__.'/../wp-content/plugins/mdg-visitor-pilot/mdg-visitor-pilot.php';
if(($argv[1]??'')==='entry'){$_SERVER['REQUEST_URI']='/bilet-al/';echo '<!doctype html><html><body><main><div class="mdg-tickets__hero"><h1>Biletinizi Seçin</h1></div><ul id="native-events"><li>Existing event</li></ul></main>';\Madagaskar\Visitor\Page::footer_entry();echo '</body></html>';exit;}
echo '<!doctype html><html lang="tr"><meta charset="utf-8"><body>'.\Madagaskar\Visitor\Page::markup().'<script id="vp-config" type="application/json">{"origin":"https://madagaskarsirki.com","api":"https://madagaskarsirki.com/wp-json/mdg-visitor/v1/"}</script></body></html>';
