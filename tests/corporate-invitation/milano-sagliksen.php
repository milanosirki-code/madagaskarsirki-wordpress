<?php
// Isolated executable unit/contract tests. No WordPress DB, order, gateway or QR writes.
define('ABSPATH',__DIR__);
$count=0;$registry=array();$products=array();$snapshots=array();$ticket_rows=array();$notices=array();
function add_shortcode(...$a){} function add_filter(...$a){} function add_action(...$a){}
function wp_json_encode($v){return json_encode($v);} function wp_salt($v){return 'isolated-test-key';}
function get_option($k,$d=array()){global $registry;return $registry;}
function wp_parse_url($u,$p){return parse_url($u,$p);} function home_url($p='/'){return 'https://madagaskarsirki.com'.$p;}
function is_wp_error($v){return $v instanceof WP_Error;}
function wc_get_product($id){global $products;return $products[$id]??null;}
function WC(){global $wc;return $wc;}
function wc_add_notice($s,$t){global $notices;$notices[]=$s;}
class WP_Error{function __construct(public $code,public $message){} function get_error_message(){return $this->message;}}
class MDG_Sessions{static function ticket_types_by_session($id){global $ticket_rows;return $ticket_rows[$id]??array();}}
class Milano_Shared_Checkout_Handoff{static function snapshot($e,$s){global $snapshots;return $snapshots[$s]??new WP_Error('closed','Seans kapalı.');}}
class Product{public $price;function __construct($p){$this->price=$p;}function set_price($p){$this->price=$p;}}
class Cart{public $cart_contents=array();public $coupons=array();function get_cart(){return $this->cart_contents;}function get_applied_coupons(){return $this->coupons;}}
require __DIR__.'/../../docs/code-snippets/mdg-corporate-campaigns.php';
require __DIR__.'/../../wp-content/plugins/milano-sagliksen/milano-sagliksen.php';
$c='MDG_Corporate_Campaigns_20261005';$mclass='Milano_Sagliksen';
function check($v,$n){global $count;if(!$v){throw new Exception('FAIL '.$n);}$count++;echo 'PASS '.$n."\n";}
$proposal=json_decode(file_get_contents(__DIR__.'/../../integrations/milano/sagliksen-20261018/registry-proposal.json'),true)['value'];
check(!Milano_Sagliksen::enabled($proposal),'shipping registry proposal is disabled');
$record=$proposal;$record['active']=true;$registry=array('sagliksen'=>$record);
check($c::resolve('SAGLIKSEN',array(),$registry,'2026-10-18')===null,'legacy Madagaskar resolver cannot use Milano scoped record');
$manifest=array('schema'=>'milano_bundle_v1','code'=>'SAGLIKSEN','event_id'=>'28','session'=>'181','packages'=>'1','extra_children'=>'0','extra_adults'=>'0');
$base=array('event_id'=>28,'session_id'=>181,'wc_product_id'=>6531,'date'=>'2026-10-21','capacity_snapshot'=>494,'preview'=>false);
$types=array('adult'=>array('variation'=>6533,'parent'=>6531,'type_id'=>405,'price'=>490.0),'child'=>array('variation'=>6532,'parent'=>6531,'type_id'=>404,'price'=>245.0));
function fixture($sid,$s,$types){global $snapshots,$ticket_rows,$products;$snapshots[$sid]=$s;$ticket_rows[$sid]=array();foreach($types as $role=>$t){$ticket_rows[$sid][]=(object)array('code'=>strtoupper($role),'wc_variation_id'=>$t['variation'],'id'=>$t['type_id'],'price'=>$t['price'],'capacity_units'=>1,'is_active'=>1);$products[$t['variation']]=new Product($t['price']);}}
fixture(181,$base,$types);
$one=Milano_Sagliksen::manifest_quote($manifest);
check($one['total']===475.0&&$one['units']===3,'one package 475 TL and three admission units');
check(array_sum(array_column($c::plan($one),'qty'))===3,'existing campaign plan has exactly three one-person tickets');
$two=$manifest;$two['packages']='2';$q=Milano_Sagliksen::manifest_quote($two);
check($q['total']===950.0&&$q['units']===6,'two packages 950 TL and six admission units');
check(array_sum(array_column($c::plan($q),'qty'))===6,'existing campaign plan has exactly six one-person tickets');
$extra=$manifest;$extra['extra_children']='1';$q=Milano_Sagliksen::manifest_quote($extra);
check($q['total']===720.0&&$q['milano_bundle_plan']['paid_child']['qty']===1,'third child charged live 245 TL: 720 TL');
$extra['extra_adults']='1';$q=Milano_Sagliksen::manifest_quote($extra);
check($q['total']===1210.0&&$q['milano_bundle_plan']['free_child']['qty']===2,'extra adult does not create another free-child allowance');
$extra=$manifest;$extra['extra_adults']='1';check(Milano_Sagliksen::manifest_quote($extra)['total']===965.0,'extra adult charged live 490 TL: 965 TL');
$dynamic=$types;$dynamic['child']['price']=260.0;check(Milano_Sagliksen::quote_snapshot(array_merge($manifest,array('extra_children'=>'1')),$base,$dynamic,$record)['total']===735.0,'extra child follows changed canonical child price');
foreach(array('WRONG','','DEMO-OTHER') as $code){$bad=$manifest;$bad['code']=$code;check(isset(Milano_Sagliksen::manifest_quote($bad)['error']),'invalid or other code rejected');}
foreach(array('price','amount','variation_id','product_id','redirect','return_url') as $k){$bad=$manifest;$bad[$k]='1';check(isset(Milano_Sagliksen::manifest_quote($bad)['error']),'untrusted '.$k.' rejected');}
foreach(array('packages'=>array('0','3','-1','1.5','01',array('1')),'extra_children'=>array('-1','1.2','17',array('0')),'extra_adults'=>array('-1','19')) as $k=>$values){foreach($values as $v){$bad=$manifest;$bad[$k]=$v;check(isset(Milano_Sagliksen::manifest_quote($bad)['error']),'malformed or excessive '.$k.' rejected');}}
foreach(array(array('event_id'=>'12'),array('event_id'=>'27'),array('session'=>'999')) as $changes){check(isset(Milano_Sagliksen::manifest_quote(array_merge($manifest,$changes))['error']),'foreign event/session rejected');}
$short=$base;$short['capacity_snapshot']=2;check(isset(Milano_Sagliksen::quote_snapshot($manifest,$short,$types,$record)['error']),'insufficient capacity rejected');
$short=$base;$short['preview']=true;check(isset(Milano_Sagliksen::quote_snapshot($manifest,$short,$types,$record)['error']),'preview cannot be purchased');
$short=$base;$short['date']='2026-10-22';check(isset(Milano_Sagliksen::quote_snapshot($manifest,$short,$types,$record)['error']),'wrong show date rejected');
$snapshots[181]=new WP_Error('closed','Seans kapalı veya geçmiş.');check(isset(Milano_Sagliksen::manifest_quote($manifest)['error']),'canonical closed or past session error propagated');fixture(181,$base,$types);
foreach(array('active'=>false,'brand'=>'madagaskar','bundle_price'=>1) as $k=>$v){$bad=$record;$bad[$k]=$v;check(isset(Milano_Sagliksen::quote_snapshot($manifest,$base,$types,$bad)['error']),'registry '.$k.' cannot bypass gate');}
foreach(Milano_Sagliksen::EVENTS as $eid=>$row){foreach($row['sessions'] as $sid){$s=$base;$s['event_id']=$eid;$s['session_id']=$sid;$s['date']=$row['date'];$m=$manifest;$m['event_id']=(string)$eid;$m['session']=(string)$sid;check(Milano_Sagliksen::quote_snapshot($m,$s,$types,$record)['total']===475.0,'scoped quote accepts canonical event '.$eid.' session '.$sid);}}
$wc=(object)array('cart'=>new Cart());
$canonical=Milano_Sagliksen::selection($manifest);$q=Milano_Sagliksen::manifest_quote($canonical);
foreach($c::plan($q) as $role=>$p){if(!$p['qty'])continue;$t=$p['type'];$wc->cart->cart_contents[$role]=array('mdg_campaign_manifest'=>$canonical,'mdg_campaign_signature'=>$c::sign($canonical),'mdg_campaign_role'=>$role,'quantity'=>$p['qty'],'product_id'=>$t['parent'],'variation_id'=>$t['variation'],'mdg_event_id'=>28,'mdg_session_id'=>181,'mdg_ticket_type_id'=>$t['type_id'],'mdg_capacity_units'=>1,'milano_handoff'=>1,'data'=>new Product($t['price']));}
$valid=$wc->cart->cart_contents;$state=$c::cart_state($wc->cart);check(!isset($state['error']),'signed campaign accepted by actual existing cart_state');
$c::prices($wc->cart);$total=0;foreach($wc->cart->cart_contents as $i){$total+=$i['quantity']*$i['data']->price;}check($total===475.0,'actual existing server-side prices hook yields 475 TL');
foreach(array('quantity'=>3,'variation_id'=>999,'mdg_capacity_units'=>0,'mdg_ticket_type_id'=>999,'mdg_session_id'=>154,'milano_handoff'=>0,'mdg_campaign_signature'=>'bad') as $k=>$v){$wc->cart->cart_contents=$valid;$wc->cart->cart_contents['free_child'][$k]=$v;check(isset($c::cart_state($wc->cart)['error']),'cart tamper '.$k.' blocks checkout');}
$wc->cart->cart_contents=$valid;$wc->cart->cart_contents['free_child']['mdg_campaign_manifest']['packages']='2';check(isset($c::cart_state($wc->cart)['error']),'manifest edit invalidates HMAC');
$wc->cart->cart_contents=$valid;unset($wc->cart->cart_contents['adult']);check(isset($c::cart_state($wc->cart)['error']),'removing paid adult invalidates free children');
$wc->cart->cart_contents=$valid;$wc->cart->coupons=array('other');check(Milano_Sagliksen::coupon(true)===false,'global coupon stacking rejected');
$threw=false;try{Milano_Sagliksen::validate_order(null);}catch(Exception $e){$threw=true;}check($threw,'coupon order rejected');
$wc->cart->coupons=array();$wc->cart->cart_contents['other']=array('quantity'=>1);$threw=false;try{Milano_Sagliksen::validate_order(null);}catch(Exception $e){$threw=true;}check($threw,'mixed ordinary or Madagaskar order rejected');
$wc->cart->cart_contents=$valid;$registry['sagliksen']['active']=false;check(isset($c::cart_state($wc->cart)['error']),'campaign disabled after selection blocks checkout');
$c::prices($wc->cart);check($wc->cart->cart_contents['free_child']['data']->price===245.0,'invalid free line restored to catalog price');
$registry['sagliksen']['active']=true;
foreach(array(array('2','0','0',950.0,6),array('1','1','0',720.0,4),array('1','0','1',965.0,4)) as $case){
 $m=$canonical;$m['packages']=$case[0];$m['extra_children']=$case[1];$m['extra_adults']=$case[2];$q=Milano_Sagliksen::manifest_quote($m);$wc->cart->cart_contents=array();
 foreach($c::plan($q) as $role=>$p){if(!$p['qty'])continue;$t=$p['type'];$wc->cart->cart_contents[$role]=array('mdg_campaign_manifest'=>$m,'mdg_campaign_signature'=>$c::sign($m),'mdg_campaign_role'=>$role,'quantity'=>$p['qty'],'product_id'=>$t['parent'],'variation_id'=>$t['variation'],'mdg_event_id'=>28,'mdg_session_id'=>181,'mdg_ticket_type_id'=>$t['type_id'],'mdg_capacity_units'=>1,'milano_handoff'=>1,'data'=>new Product($t['price']));}
 $c::prices($wc->cart);$total=0;$units=0;foreach($wc->cart->cart_contents as $i){$total+=$i['quantity']*$i['data']->price;$units+=$i['quantity'];}
 check($total===$case[3]&&$units===$case[4],'actual shared pricing hook '.$case[3].' TL and '.$case[4].' admissions');
}
echo "TOTAL $count assertions passed. Payment, real capacity races, PDF/QR and visual QA NOT tested by this harness.\n";
