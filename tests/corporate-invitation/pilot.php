<?php
define('ABSPATH', __DIR__);
$hooks = array();
$products = array();
$locked = false;
$nonce_valid = true;
function add_shortcode($name, $callback) { global $hooks; $hooks['shortcode'][]=$name; }
function add_filter($name, $callback) { global $hooks; $hooks['filter'][]=$name; }
function wc_get_product($id) { global $products; return $products[$id] ?? null; }
function get_post() { return (object) array('post_content'=>'[mdg_corporate_invitation_pilot]'); }
function post_password_required($p) { global $locked; return $locked; }
function is_singular($type) { return true; }
function has_shortcode($text,$tag) { return strpos($text,'['.$tag.']') !== false; }
function sanitize_text_field($s) { return strip_tags($s); }
function wp_unslash($s) { return $s; }
function wp_verify_nonce($n,$action) { global $nonce_valid; return $nonce_valid; }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_html($s); }
function wp_kses_post($s) { return $s; }
function wc_price($p) { return number_format($p,2).' TL'; }
function get_permalink($p) { return 'https://example.test/pilot/'; }
function wp_nonce_field($a,$n,$r) { echo '<input name="'.$n.'" value="test">'; }
function selected($a,$b) { if ((string)$a===(string)$b) echo 'selected'; }
class Product {
    public $status='publish'; public $parent=0; public $meta=array(); public $price='500';
    function get_status(){return $this->status;}
    function get_parent_id(){return $this->parent;}
    function is_type($t){return $this->parent>0 && $t==='variation';}
    function get_meta($k){return $this->meta[$k]??'';}
    function get_price(){return $this->price;}
}
require __DIR__.'/../../docs/code-snippets/mdg-corporate-invitation-pilot.php';
function check($pass,$label) { if (!$pass) throw new Exception($label); echo "PASS $label\n"; }
$class='MDG_Corporate_Invitation_Pilot_20261005';
check($hooks===array('shortcode'=>array($class::SHORTCODE),'filter'=>array('wp_robots')), 'no cart checkout order payment or scanner hooks');
foreach ($class::sessions() as $s) {
    $products[$s['parent']]=new Product();
    foreach(array('adult','child') as $role) {
        $v=new Product(); $v->parent=$s['parent']; $v->meta=array('_mdg_event_id'=>12,'_mdg_session_id'=>$s['session']);
        $products[$s[$role]]=$v;
    }
}
foreach(array('1730','1930') as $session) {
    $q=$class::quote('test-denizli',$session,'1','2',$class::price($session));
    check($q['total']===500.0 && $q['capacity_units']===3 && $q['test_only']===true, $session.' one adult two children');
    $q=$class::quote($class::CODE,$session,'2','4',600);
    check($q['total']===1200.0 && $q['child_total']===0, $session.' dynamic price two adults four children');
}
foreach(array(
    array('INVALID','1730','1','2',500), array($class::CODE,'bad','1','2',500),
    array($class::CODE,'1730','0','2',500),array($class::CODE,'1730','1','3',500),
    array($class::CODE,'1730','3','4',500),array($class::CODE,'1730','1.5','2',500),
    array($class::CODE,'1730','-1','2',500),array($class::CODE,'1730',array(1),'2',500),
    array($class::CODE,'1730','1','0',500),array($class::CODE,'1730','1','2',null),
    array($class::CODE,'1730','1','2',INF),array($class::CODE,'1730','1','2',0)
) as $i=>$args) check(isset($class::quote(...$args)['error']), 'invalid campaign/count/price '.$i);
$products[2427]->meta['_mdg_session_id']=100;
check(null===$class::price('1730'),'wrong session fails closed');
$products[2427]->meta['_mdg_session_id']=99;
$products[2425]->status='draft';
check(null===$class::price('1730'),'unpublished parent fails closed');
$products[2425]->status='publish';
check($class::robots(array('index'=>true,'follow'=>true))===array('noindex'=>true,'nofollow'=>true),'pilot noindex only');
$locked=true;
check(''===$class::render(),'password gate even on indirect rendering');
$locked=false;
$_SERVER['REQUEST_METHOD']='POST';
$_POST=array('mdg_corporate_pilot_submit'=>'1','mdg_corporate_pilot_nonce'=>'test', 'mdg_pilot_code'=>$class::CODE,'mdg_pilot_session'=>'1730','mdg_pilot_adults'=>'1','mdg_pilot_children'=>'2');
$html=$class::render();
check(strpos($html,'500.00 TL')!==false && strpos($html,'Bu özet bilet veya rezervasyon değildir')!==false,'rendered successful quote clearly test only');
$nonce_valid=false;
check(strpos($class::render(),'Sayfanın süresi doldu')!==false,'CSRF fails closed');
$nonce_valid=true;
$_POST['mdg_pilot_children']=array('2');
check(strpos($class::render(),'tam sayı')!==false,'array POST safely rejected');
echo "All corporate invitation interaction pilot tests passed.\n";
