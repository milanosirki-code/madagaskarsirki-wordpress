<?php
define('ABSPATH','test');
function add_action(){}function is_admin(){return false;}function is_user_logged_in(){return false;}function wp_doing_ajax(){return false;}function is_page(){return false;}function wp_unslash($s){return $s;}function wp_parse_url($s,$component){return parse_url($s,$component);}function trailingslashit($s){return rtrim($s,'/').'/';}
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/hakkimizda/';$_COOKIE=array();$_POST=array();$_GET=array();
require __DIR__.'/../../docs/code-snippets/mdg-safe-cache-protocol-exclusions.php';
if(!ms_v3p_guvenli_onbellek_istegi())throw new Exception('Information page lost cache');
foreach(array('mdg_davetiye','mdg_protokol','anahtar','bilet','kisi','download_ticket','order_key') as $key){$_GET=array($key=>'test');if(ms_v3p_guvenli_onbellek_istegi())throw new Exception('Private key allowed: '.$key);}
$_GET=array();$_SERVER['REQUEST_METHOD']='POST';if(ms_v3p_guvenli_onbellek_istegi())throw new Exception('POST cached');
echo "PASS information cache, 7 private key exclusions and POST exclusion\n";
