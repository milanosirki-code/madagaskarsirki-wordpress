<?php
// Simulated WordPress flow: form render, submission, limit, approval and e-mail, with stubs.
define('ABSPATH','test');define('HOUR_IN_SECONDS',3600);
$GLOBALS['opt']=array();$GLOBALS['mail']=array();$GLOBALS['tr']=array();
function add_action(){}function apply_filters($t,$v){return $v;}
function get_option($k,$d=false){return $GLOBALS['opt'][$k]??$d;}function update_option($k,$v){$GLOBALS['opt'][$k]=$v;return true;}function add_option($k,$v){if(isset($GLOBALS['opt'][$k]))return false;$GLOBALS['opt'][$k]=$v;return true;}function delete_option($k){unset($GLOBALS['opt'][$k]);}
function get_transient($k){return $GLOBALS['tr'][$k]??false;}function set_transient($k,$v){$GLOBALS['tr'][$k]=$v;}function wp_salt(){return 's';}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}function sanitize_textarea_field($s){return trim(strip_tags((string)$s));}function sanitize_email($s){return trim((string)$s);}function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));}
function is_email($s){return (bool)filter_var($s,FILTER_VALIDATE_EMAIL);}function esc_url_raw($s){return (string)$s;}function esc_url($s){return htmlspecialchars((string)$s);}function esc_attr($s){return htmlspecialchars((string)$s);}function esc_html($s){return htmlspecialchars((string)$s);}function esc_textarea($s){return htmlspecialchars((string)$s);}
function wp_unslash($s){return $s;}function absint($s){return abs((int)$s);}function remove_accents($s){return strtr($s,array('ı'=>'i','ö'=>'o','ü'=>'u','ş'=>'s','ç'=>'c','ğ'=>'g'));}
function wp_create_nonce($a){return 'n-'.$a;}function wp_verify_nonce($n,$a){return $n==='n-'.$a;}function selected($a,$b,$e=true){return $a==$b?' selected':'';}
function home_url($p){return 'https://example.test'.$p;}function admin_url($p){return 'https://example.test/wp-admin/'.$p;}function add_query_arg($a,$u,$x=null){if($x!==null){$a=array($a=>$u);$u=$x;}return $u.(strpos($u,'?')===false?'?':'&').http_build_query($a);}
function wp_mail($to,$s,$h){$GLOBALS['mail'][]=array($to,$s,$h);return true;}function get_current_user_id(){return 7;}function wp_date($f,$t){return date($f,$t);}
class MDG_Protocol_Tickets{const PAGE='mdg-protocol-tickets';static $saved=array();
 static function catalogue(){return array(12=>array('event'=>(object)array('title'=>'Madagaskar Sirki — Ayvalık','venue_name'=>'Ayvalık Kapalı Spor Salonu'),'sessions'=>array(55=>array('id'=>55,'time'=>'26.11.2026 18:00','available'=>400,'variation'=>9))));}
 static function phone($p){$d=preg_replace('/\D/','',$p);if($d==='')return '';if(preg_match('/^05[0-9]{9}$/',$d))return '90'.substr($d,1);throw new Exception('Cep telefonu geçersiz.');}
 static function save($b){self::$saved[$b['id']]=$b;}
 static function issue_batch($id){$b=self::$saved[$id];if($b['user']!==get_current_user_id())throw new Exception('user');$b['state']='ready';$b['token']=str_repeat('t',48);self::$saved[$id]=$b;return $b;}
 static function package_url($b){return home_url('/').'?mdg_protokol='.$b['id'].'&anahtar='.$b['token'];}
 static function person_url($b,$i){return home_url('/').'?mdg_protokol='.$b['id'].'&kisi='.$i.'&anahtar=x';}}
require __DIR__.'/../../wp-content/plugins/madagaskar-basin-bileti/madagaskar-basin-bileti.php';
$c='MDG_Press_Tickets';$n=0;function check($ok,$l){global $n;if(!$ok)throw new Exception('FAIL '.$l);$n++;echo "PASS $l\n";}
$key=$c::access_key();check(strlen($key)===48,'access key generated');check(strpos($c::form_url(),'mdg_basin=talep')!==false&&strpos($c::form_url(),$key)!==false,'form url contains key');
$_SERVER['REQUEST_METHOD']='GET';$_POST=array();ob_start();$c::render_form($key,'',false);$h=ob_get_clean();check(strpos($h,'Ayvalık Kapalı Spor Salonu — 26.11.2026 18:00')!==false&&strpos($h,'noindex')!==false&&substr_count($h,'name="names[]"')===2,'form renders sessions, noindex and 2 people');
$post=array('_mdg_press'=>'n-mdg_press_submit','website'=>'','org'=>'Gazete Ayvalık','web'=>'https://gazeteayvalik.com','email'=>'haber@gazeteayvalik.com','phone'=>'0532 000 00 00','consent'=>'1','names'=>array('Ali Veli','Ayşe Kaya'),'roles'=>array('Muhabir','Kameraman'),'session'=>'12:55','note'=>'');
$_POST=$post;$_SERVER['REMOTE_ADDR']='1.2.3.4';$c::submit();$all=$c::all_requests();$r=reset($all);check(count($all)===1&&$r['state']==='pending'&&count($r['people'])===2&&$r['phone']==='905320000000','request stored pending');
check(count($GLOBALS['mail'])===2&&$GLOBALS['mail'][0][0]==='madagaskarsirkiturkiye@gmail.com'&&$GLOBALS['mail'][1][0]==='haber@gazeteayvalik.com','admin and applicant notified');
$_POST=$post;$_POST['names']=array('Üçüncü Kişi','');$_POST['email']='baska@gazeteayvalik.com';try{$c::submit();check(false,'limit');}catch(Exception $e){check(strpos($e->getMessage(),'hakkını kullandı')!==false,'third ticket for same org blocked');}
$_POST=$post;$_POST['website']='spam';try{$c::submit();check(false,'hp');}catch(Exception $e){check(true,'honeypot blocks bots');}
$_POST=$post;$_POST['_mdg_press']='bad';try{$c::submit();check(false,'nonce');}catch(Exception $e){check(true,'bad nonce rejected');}
$_POST=$post;$_POST['session']='12:99';$_POST['org']='Yeni Kurum';$_POST['email']='y@y.com';try{$c::submit();check(false,'session');}catch(Exception $e){check(true,'unknown session rejected');}
$msg=$c::approve($r['id']);$r=$c::load($r['id']);check($r['state']==='approved'&&!empty($r['batch'])&&!empty($r['mailed']),'approval issues batch and mails');
$b=MDG_Protocol_Tickets::$saved[$r['batch']];check($b['state']==='ready'&&$b['kind']==='protocol'&&count($b['people'])===2,'protocol batch ready with 2 tickets');
$last=end($GLOBALS['mail']);check($last[0]==='haber@gazeteayvalik.com'&&strpos($last[2],'kisi=0')!==false&&strpos($last[2],'kisi=1')!==false&&strpos($last[2],'toplu=pdf')!==false,'ticket mail has personal links and PDF');
try{$c::approve($r['id']);check(false,'re');}catch(Exception $e){check(true,'double approval blocked');}
for($i=0;$i<6;$i++){$_POST=$post;$_POST['org']='Kurum '.$i;$_POST['email']="k$i@k.com";try{$c::submit();}catch(Exception $e){}}
$_POST=$post;$_POST['org']='Son';$_POST['email']='son@k.com';try{$c::submit();check(false,'rl');}catch(Exception $e){check(strpos($e->getMessage(),'Çok fazla')!==false,'rate limit per IP');}
echo "All $n press flow checks passed.\n";
