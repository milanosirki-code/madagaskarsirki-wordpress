<?php
define('ABSPATH', __DIR__);
$state = [];
function is_admin(){return !empty($GLOBALS['state']['admin']);}
function is_user_logged_in(){return !empty($GLOBALS['state']['logged_in']);}
function wp_doing_ajax(){return !empty($GLOBALS['state']['ajax']);}
function is_front_page(){return !empty($GLOBALS['state']['front']);}
function is_page($ids){return in_array($GLOBALS['state']['page'] ?? '', (array)$ids, true);}
function is_cart(){return !empty($GLOBALS['state']['cart']);}
function is_checkout(){return !empty($GLOBALS['state']['checkout']);}
function is_account_page(){return !empty($GLOBALS['state']['account']);}
function is_product(){return !empty($GLOBALS['state']['product']);}
function is_product_category(){return !empty($GLOBALS['state']['product_category']);}
function wp_unslash($v){return $v;}
function wp_parse_url($v,$part){return parse_url($v,$part);}
function trailingslashit($v){return rtrim($v,'/').'/';}
function add_action(...$args){}
require __DIR__.'/../docs/code-snippets/snippet-20-safe-cache.php';
function check_case($name,$expected,$state=[],$path='/hakkimizda/',$get=[],$cookies=[],$method='GET'){
    $GLOBALS['state']=$state; $_SERVER=['REQUEST_URI'=>$path,'REQUEST_METHOD'=>$method]; $_GET=$get; $_POST=[]; $_COOKIE=$cookies;
    if(ms_v3p_guvenli_onbellek_istegi() !== $expected){throw new RuntimeException($name);}
    echo "PASS: $name\n";
}
check_case('informational page remains cacheable',true);
check_case('front page',false,['front'=>true],'/');
foreach(['sehirler','bilet-al'] as $slug){
    check_case($slug.' queried page',false,['page'=>$slug]);
    check_case($slug.' path with UTM',false,[],'/'.$slug.'/?utm_source=instagram');
}
check_case('event path',false,[],'/etkinlik/test/?utm_source=instagram');
foreach(['cart','checkout','account','product','product_category','admin','logged_in','ajax'] as $flag){check_case($flag,false,[$flag=>true]);}
foreach(['sepet','odeme','biletlerim','order-pay','order-received','wc-api'] as $slug){check_case($slug,false,[],'/'.$slug.'/test');}
check_case('ticket download',false,[],'/hakkimizda/',['download_ticket'=>'x']);
check_case('cart cookie',false,[],'/hakkimizda/',[],['wp_woocommerce_session_test'=>'x']);
check_case('POST',false,[],'/hakkimizda/',[],[],'POST');
check_case('before later no-cache decision',true);
define('DONOTCACHEPAGE',true);
if(ms_v3p_son_cikti_duzenle('<p>original</p>') !== '<p>original</p>'){throw new RuntimeException('output changed');}
check_case('late no-cache decision respected',false);
