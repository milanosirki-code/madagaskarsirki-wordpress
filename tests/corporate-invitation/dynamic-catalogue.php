<?php
define('ABSPATH',__DIR__);
$hooks=array(); $products=array(); $event_rows=array(); $session_rows=array(); $ticket_rows=array(); $registry=array(); $stock=array();
function add_shortcode($k,$v){global $hooks;$hooks['shortcode'][]=$k;}
function add_filter($k,$v){global $hooks;$hooks['filter'][]=$k;}
function add_action($k,$v){global $hooks;$hooks['action'][]=$k;}
function wp_json_encode($v){return json_encode($v);}
function wp_salt($v){return 'test-secret';}
function get_option($k,$default){global $registry;return $registry;}
function current_time($f,$utc=false){return $f==='Y-m-d'?'2026-10-05':'2026-10-05 07:40:00';}
function wc_get_product($id){global $products;return $products[$id]??null;}
function get_post(){return (object)array('post_content'=>'[mdg_corporate_campaigns]');}
function post_password_required($p){return false;}
function wp_verify_nonce($n,$action){return $n==='valid';}
function sanitize_text_field($s){return strip_tags($s);}
function wp_unslash($s){return $s;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function wp_kses_post($s){return $s;}
function get_permalink($p){return 'https://example.test/campaign/';}
function wp_nonce_field($a,$n,$r=false){echo '<input name="'.$n.'" value="valid">';}
function number_format_i18n($n,$d){return number_format($n,$d);}
function wc_price($p){return number_format($p,2).' TL';}
class MDG_Public_Tickets{static function active_events(){global $event_rows;return $event_rows;}}
class MDG_Sessions {
 static function by_event($id){global $session_rows;return $session_rows[$id]??array();}
 static function ticket_types_by_session($id){global $ticket_rows;return $ticket_rows[$id]??array();}
 static function local_parts($s){$d=new DateTimeImmutable($s,new DateTimeZone('UTC'));$d=$d->setTimezone(new DateTimeZone('Europe/Istanbul'));return array($d->format('Y-m-d'),$d->format('H:i'));}
}
class MDG_Capacity{static function available($id){global $stock;return $stock[$id]??100;}}
class Product {
 public $status='publish';public $parent=0;public $meta=array();public $price='500';public $stock=true;
 function get_status(){return $this->status;}
 function get_parent_id(){return $this->parent;}
 function is_type($t){return $this->parent? $t==='variation':$t==='variable';}
 function is_in_stock(){return $this->stock;}
 function is_purchasable(){return true;}
 function get_meta($k){return $this->meta[$k]??'';}
 function get_price(){return $this->price;}
}
function event_fixture($id,$city,$time,$adult=500,$english=false){
 global $event_rows,$session_rows,$ticket_rows,$products;
 $event_rows[]=(object)array('id'=>$id,'province_name'=>$city,'district'=>'Merkez','status'=>'onsale','title'=>$city.' gösterisi '.$id,'venue_name'=>'Salon '.$id);
 $s=$id*10;$p=$id*100;
 $session_rows[$id]=array((object)array('id'=>$s,'event_id'=>$id,'status'=>'onsale','start_at'=>$time,'wc_product_id'=>$p));
 $products[$p]=new Product();$ticket_rows[$s]=array();
 foreach(array('child','adult') as $i=>$role){
  $v=new Product();$v->parent=$p;$v->price=$role==='adult'?(string)$adult:'250';$v->meta=array('_mdg_event_id'=>$id,'_mdg_session_id'=>$s);$products[$p+$i+1]=$v;
  $ticket_rows[$s][]=(object)array('code'=>$english?strtoupper($role):($role==='adult'?'YETISKIN':'COCUK'),'wc_variation_id'=>$p+$i+1,'is_active'=>1,'capacity_units'=>1,'id'=>$p+$i+1);
 }
}
require __DIR__.'/../../docs/code-snippets/mdg-corporate-campaigns.php';
$c='MDG_Corporate_Campaigns_20261005';$count=0;
function check($pass,$name){global $count;if(!$pass)throw new Exception($name);$count++;echo "PASS $name\n";}
check(!in_array('woocommerce_payment_complete',$hooks['action'],true),'existing payment and ticket generation hooks preserved');
foreach(array('TEST-DENIZLI','test-denizli','TeSt-DeNiZlI','TEST-DENİZLİ','test-denızlı',' TEST Denizli ','TEST_DENIZLI') as $code)check($c::normalize($code)==='test-denizli','case/Turkish/separator normalization '.$code);
check($c::normalize('TEST-İZMİR')==='test-izmir','Turkish uppercase dotted I');
check($c::normalize('test-us\'ak')==='','invalid punctuation rejected');
check($c::normalize(array('bad'))==='','array code rejected');
event_fixture(12,'Denizli','2026-10-08 14:30:00');
event_fixture(10,'İzmir','2026-11-08 09:00:00',600);
event_fixture(23,'Manisa','2026-10-18 09:00:00',500,true);
$campaign=$c::resolve('test-denizli',$event_rows,array(),'2026-10-05');
$cat=$c::catalogue($campaign,$event_rows);
check(array_keys($cat)===array(12),'code reveals only its city');
check($cat[12]['sessions'][120]['time']==='17:30','UTC converted to Turkey session time');
$custom=array('kurum-denizli'=>array('province'=>'Denizli','name'=>'Kurum','active'=>true,'end_date'=>''));
check($c::resolve('KURUM-DENİZLİ',$event_rows,$custom,'2026-10-05')['name']==='Kurum','custom code normalized');
event_fixture(30,'Denizli','2026-10-22 14:30:00');
$cat=$c::catalogue($campaign,$c::source_events());
check(array_keys($cat)===array(12,30),'new same-city event automatically appears without code edit');
$event_rows[3]->status='cancelled';
check(!isset($c::catalogue($campaign,$event_rows)[30]),'cancelled event immediately hidden');
$event_rows[3]->status='onsale';
$session_rows[30][0]->start_at='2026-10-01 09:00:00';
check(!isset($c::catalogue($campaign,$event_rows)[30]),'past session immediately hidden');
$session_rows[30][0]->start_at='2026-10-22 14:30:00';
$session_rows[30][0]->status='closed';
check(!isset($c::catalogue($campaign,$event_rows)[30]),'closed session hidden');
$session_rows[30][0]->status='onsale';
$stock[300]=0;
check(!isset($c::catalogue($campaign,$event_rows)[30]),'sold out shared capacity hidden');
$stock[300]=100;
$products[1202]->meta['_mdg_session_id']=99;
check(!isset($c::catalogue($campaign,$event_rows)[12]),'bad mapping fails closed');
$products[1202]->meta['_mdg_session_id']=120;
$cat=$c::catalogue($campaign,$event_rows);
check($c::quote($cat,'120','1',array('5','12'))['total']===500.0,'one adult plus two free children');
$q=$c::quote($cat,'120','1',array('5','7','12'));
check($q['total']===750.0 && $q['paid_children']===1,'third child charged normal child price');
check(isset($c::quote($cat,'100','1',array('5','7'))['error']),'cross-city session tampering blocked');
check(isset($c::quote($cat,'120','0',array('5','7'))['error']),'child-only blocked');
check(isset($c::quote($cat,'120','-1',array('5','7'))['error']),'negative adults blocked');
check(isset($c::quote($cat,'120','1.5',array('5','7'))['error']),'decimal adults blocked');
$stock[120]=2;
$cat=$c::catalogue($campaign,$event_rows);
check(isset($c::quote($cat,'120','1',array('5','7'))['error']),'insufficient shared capacity blocked');
$stock[120]=100;
$izmir=$c::resolve('TEST-İZMİR',$event_rows,array(),'2026-10-05');
check($c::quote($c::catalogue($izmir,$event_rows),'100','2',array('5','7','10','12'))['total']===1200.0,'Izmir-specific current adult price');
$manisa=$c::resolve('TEST-MANİSA',$event_rows,array(),'2026-10-05');
check(isset($c::catalogue($manisa,$event_rows)[23]),'English ADULT/CHILD supported');
$custom['kurum-denizli']['active']=false;
check(null===$c::resolve('kurum-denizli',$event_rows,$custom,'2026-10-05'),'disabled code rejected');
$custom['kurum-denizli']['active']=true;$custom['kurum-denizli']['end_date']='2026-10-04';
check(null===$c::resolve('kurum-denizli',$event_rows,$custom,'2026-10-05'),'expired code rejected');
$custom['kurum-denizli']['end_date']='2026-10-05';
check(null!==$c::resolve('kurum-denizli',$event_rows,$custom,'2026-10-05'),'expiry inclusive final day');
check(null===$c::resolve('random',$event_rows,array(),'2026-10-05'),'unknown code rejected');
$_GET=array();$_POST=array();$_SERVER['REQUEST_METHOD']='GET';
$h=$c::render();
check(strpos($h,'data-event-id')===false && strpos($h,'mdg_campaign_code')!==false,'landing reveals no events before code');
$_GET=array('kod'=>'test-izmir');$h=$c::render();
check(strpos($h,'data-event-id="10"')!==false && strpos($h,'data-event-id="12"')===false,'code-prefilled link only selected city');
$_GET=array();$_POST=array('mdg_campaign_action'=>'open','mdg_campaign_code'=>'TEST-DENIZLI','mdg_campaign_nonce'=>'invalid');$_SERVER['REQUEST_METHOD']='POST';
$h=$c::render();
check(strpos($h,'Sayfanın süresi doldu')!==false && strpos($h,'data-event-id')===false,'invalid nonce does not open catalogue');
$_POST['mdg_campaign_nonce']='valid';$h=$c::render();
check(strpos($h,'data-event-id="12"')!==false && strpos($h,'data-event-id="10"')===false,'valid POST opens correct city');
$cat=$c::catalogue($campaign,$event_rows);
$q=$c::quote($cat,'120','2',array('3','4','5'));
check($q['free_children']===3 && $q['paid_children']===0 && $q['total']===1000.0,'two adults unlock third free child');
$q=$c::quote($cat,'120','2',array('3','4','5','6','12'));
check($q['free_children']===4 && $q['paid_children']===1 && $q['total']===1250.0,'fifth child charged with two adults');
$q=$c::quote($cat,'120','3',array('3','4','5','6','11','12','8'));
check($q['free_children']===6 && $q['paid_children']===1 && $q['total']===1750.0,'free allowance scales beyond two adults');
$q=$c::quote($cat,'120','1',array('0','2','3','12'));
check($q['infants']===2 && $q['free_children']===2 && $q['paid_children']===0 && $q['total']===500.0,'0 and 2 do not consume free-child allowance');
$q=$c::quote($cat,'120','1',array('13','3','5','12'));
check($q['adult_tickets']===2 && $q['older']===1 && $q['free_children']===3 && $q['total']===1000.0,'age 13 adult fare and adult-ticket allowance');
$q=$c::quote($cat,'120','1',array('12','13','14'));
check($q['adult_tickets']===3 && $q['free_children']===1 && $q['details'][1]['kind']==='adult' && $q['total']===1500.0,'12 and 13 boundary');
check($c::quote($cat,'120','1',array())['total']===500.0,'adult-only allowed');
foreach(array(array(''),array('-1'),array('3.5'),array('121'),array(array('5')),array('x'),array('00'),array('01'),array(5)) as $i=>$ages){check(isset($c::quote($cat,'120','1',$ages)['error']),'bad ages rejected '.$i);}
check(isset($c::quote($cat,'120','1',array(1=>'5'))['error']),'sparse age array rejected');
check(isset($c::quote($cat,'120','1',array_fill(0,21,'5'))['error']),'excess input count bounded');
$_POST=array('mdg_campaign_action'=>'quote','mdg_campaign_code'=>'TEST-DENIZLI','mdg_campaign_nonce'=>'valid','mdg_campaign_event'=>'12','mdg_campaign_session'=>'120','mdg_campaign_adults'=>'1','mdg_campaign_children'=>'3','mdg_campaign_birthdates'=>array('2021-01-01','2019-01-01','2014-01-01'));
$h=$c::render();
check(strpos($h,'750.00 TL')!==false && strpos($h,'Normal çocuk bileti')!==false && strpos($h,'value="2014-01-01" required data-child-birthdate')!==false,'server result and ages preserved');
$_POST['mdg_campaign_children']='4';
check(strpos($c::render(),'çocuk sayısı kadar doğum tarihi')!==false,'age count mismatch blocked');
check($c::ages_from_birthdates(array('2013-10-08','2013-10-09','2023-10-09'),'2026-10-08','2026-10-05')===array('13','12','2'),'birthday and completed years at show date');
check($c::ages_from_birthdates(array('2013-10-20'),'2026-11-08','2026-10-05')===array('13'),'birthday before later show becomes adult');
check($c::ages_from_birthdates(array('2024-02-29'),'2026-10-08','2026-10-05')===array('2'),'valid leap-day birth');
foreach(array(array('2023-02-29'),array('2026-10-06'),array('2013-13-01'),array(''),array(array('2013-01-01')),array('1900-01-01')) as $i=>$dates){check(isset($c::ages_from_birthdates($dates,'2026-10-08','2026-10-05')['error']),'invalid birthdates rejected '.$i);}
check($c::quote_birthdates($cat,'120','1',array('2021-01-01','2019-01-01','2014-01-01'),'2026-10-05')['total']===750.0,'birthdate priced quote');
// Campaign cart: quantities and signatures cannot be used to preserve free lines alone.
$registry=$custom;
$m=array('code'=>'kurum-denizli','session'=>'120','adults'=>'1','births'=>array('2021-01-01','2019-01-01','2014-01-01'));
$q=$c::manifest_quote($m);$plan=$c::plan($q);
check($plan['adult']['qty']===1 && $plan['free_child']['qty']===2 && $plan['paid_child']['qty']===1,'cart plan includes free and paid child tickets');
class CartFixture{public $cart_contents=array();function get_cart(){return $this->cart_contents;}}
$cart=new CartFixture();
foreach($plan as $role=>$row){if(!$row['qty'])continue;$cart->cart_contents[$role]=array('mdg_campaign_manifest'=>$m,'mdg_campaign_signature'=>$c::sign($m),'mdg_campaign_role'=>$role,'variation_id'=>$row['type']['variation'],'product_id'=>$row['type']['parent'],'quantity'=>$row['qty']);}
$state=$c::cart_state($cart);
check($state['prices']===array('adult'=>500.0,'paid_child'=>250.0,'free_child'=>0),'server-approved cart prices');
$old=$cart->cart_contents;
unset($cart->cart_contents['adult']);check(isset($c::cart_state($cart)['error']),'removing adult blocks free children');
$cart->cart_contents=$old;$cart->cart_contents['free_child']['quantity']=3;check(isset($c::cart_state($cart)['error']),'increasing free quantity blocked');
$cart->cart_contents=$old;$cart->cart_contents['free_child']['variation_id']=1001;check(isset($c::cart_state($cart)['error']),'cross-city variation blocked');
$cart->cart_contents=$old;$cart->cart_contents['free_child']['mdg_campaign_manifest']['adults']='10';check(isset($c::cart_state($cart)['error']),'modified unsigned manifest blocked');
$cart->cart_contents=$old;$registry['kurum-denizli']['active']=false;check(isset($c::cart_state($cart)['error']),'disabled code blocks checkout');
$registry=$custom;$products[1202]->price='550';$state=$c::cart_state($cart);check($state['prices']['adult']===550.0,'current price recomputed at checkout');$products[1202]->price='500';
check(isset($c::manifest_quote(array_merge($m,array('code'=>'test-denizli')))['error']),'public test code cannot checkout');
$cart->cart_contents=array('ordinary'=>array('variation_id'=>1201,'quantity'=>1));check($c::cart_state($cart)===array('prices'=>array()),'ordinary cart untouched');
check($c::coupon_product(true,null,null,array('mdg_campaign_manifest'=>$m))===false,'campaign coupon stacking blocked');
check($c::coupon_product(true,null,null,array())===true,'ordinary coupon preserved');
echo "All $count dynamic catalogue checks passed.\n";
