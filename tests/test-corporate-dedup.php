<?php
const DAY_IN_SECONDS = 86400;
$options = array(); $valid_nonce = true; $scheduled = array();
function add_action(...$args) {}
function mdg_handle_corporate_quote_v2() {}
function absint($v) { return abs((int)$v); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return $v; }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function sanitize_textarea_field($v) { return trim(strip_tags($v)); }
function wp_verify_nonce(...$v) { global $valid_nonce; return $valid_nonce; }
function wp_generate_uuid4() { return 'owner-test'; }
function get_option($key, $default=false) { global $options; return $options[$key] ?? $default; }
function add_option($key,$value,...$args) { global $options; if(isset($options[$key])) return false; $options[$key]=$value; return true; }
function update_option($key,$value,...$args) { global $options; $options[$key]=$value; }
function get_post_type($id) { return 'mdg_corp_lead'; }
function update_post_meta(...$args) {}
function wp_schedule_single_event(...$args) { global $scheduled; $scheduled[]=$args; }
function wp_cache_delete(...$args) {}
function maybe_serialize($v) { return serialize($v); }
function add_query_arg($k,$v,$url) { return $url.'?'.$k.'='.$v; }
function home_url($path) { return 'https://example.test'.$path; }
function wp_safe_redirect($url) { throw new RuntimeException($url); }
class TestDB {
    public $options = 'wp_options';
    function prepare($sql,$key,$value) { return array($key,$value); }
    function query($args) { global $options; [$key,$value]=$args; if(isset($options[$key]) && serialize($options[$key])===$value) { unset($options[$key]); return 1; } return 0; }
}
$wpdb = new TestDB();
require __DIR__.'/../docs/code-snippets/kommo-corporate-dedup.php';
function verify($condition,$label) { if(!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
$data = array('company'=>'School','contact_name'=>'Test','phone'=>'+90 555 000 0000','city'=>'Ankara','event_date'=>'2026-12-01','event_type'=>'Okul','audience'=>'20','note'=>'Offer');
$hash = MDG_Corporate_Dedup_20261013::fingerprint($data);
$same=$data; $same['phone']='+90 (555) 000-0000'; $same['company']=' School ';
verify($hash===MDG_Corporate_Dedup_20261013::fingerprint($same),'Formatting repeats match');
$changed=$data; $changed['note']='Different request';
verify($hash!==MDG_Corporate_Dedup_20261013::fingerprint($changed),'Changed request accepted');
$_POST=$data+array('mdg_corporate_quote_nonce'=>'test','contact_consent'=>1);
$valid_nonce=false; MDG_Corporate_Dedup_20261013::begin(); verify(!$options,'Invalid nonce cannot reserve');
$valid_nonce=true; MDG_Corporate_Dedup_20261013::begin(); $key='mdg_corp_dedup_'.$hash;
verify(isset($options[$key]) && !$options[$key]['lead_id'],'First request reserved');
try { MDG_Corporate_Dedup_20261013::begin(); throw new LogicException('Duplicate not blocked'); }
catch(RuntimeException $e) { verify(str_contains($e->getMessage(),'mdg_teklif=rate'),'Concurrent duplicate blocked'); }
MDG_Corporate_Dedup_20261013::recorded(1,123,'_mdg_contact_consent',1);
MDG_Corporate_Dedup_20261013::shutdown(); verify($options[$key]['lead_id']===123,'Saved lead survives shutdown');
try { MDG_Corporate_Dedup_20261013::begin(); throw new LogicException('Repeat not blocked'); }
catch(RuntimeException $e) { verify(str_contains($e->getMessage(),'mdg_teklif=ok'),'Completed repeat returns success'); }
$newkey='pending'; $options[$newkey]=array('expires'=>time()-1,'owner'=>'new');
verify(!MDG_Corporate_Dedup_20261013::release($newkey,array('owner'=>'old')),'Other owner preserved');
MDG_Corporate_Dedup_20261013::cleanup($newkey); verify(!isset($options[$newkey]),'Expired reservation cleaned');
