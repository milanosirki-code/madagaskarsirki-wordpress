<?php
// Isolated service tests. Fixtures are synthetic, never live financial records.
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');
$options=array();$transients=array();$remote=array();$hooks=array();$responses=array();
function add_action($hook,$callback,$priority=10){global$hooks;$hooks[$hook][$priority]=$callback;}
function add_submenu_page($parent,$title,$menu,$cap,$slug,$callback){global$registered_menu;$registered_menu=compact('parent','cap','slug');}
function register_activation_hook($f,$cb){}function register_deactivation_hook($f,$cb){}
function get_option($k,$d=false){global$options;return$options[$k]??$d;}
function update_option($k,$v,$autoload=false){global$options;$options[$k]=$v;return true;}
function get_transient($k){global$transients;return$transients[$k]??false;}
function set_transient($k,$v,$ttl){global$transients;$transients[$k]=$v;}
function delete_transient($k){global$transients;unset($transients[$k]);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function current_time($f){return '2026-10-26 13:00:00';}
function get_current_user_id(){return 5;}function sanitize_text_field($v){return strip_tags($v);}
function sanitize_key($v){return$v;}function wp_unslash($v){return$v;}function absint($v){return abs((int)$v);}
function current_user_can($cap){return false;}function wp_die($v){throw new RuntimeException($v);}
function wp_salt($v){return 'test-only-salt';}function is_wp_error($v){return false;}
function wp_remote_post($url,$args){global$remote,$responses;$remote[]=array($url,$args);return $responses?array_shift($responses):array('code'=>403,'body'=>'secret-must-not-leak');}
function wp_remote_retrieve_response_code($r){return$r['code'];}function wp_remote_retrieve_body($r){return$r['body'];}
class MDG_DB{static function table($n){return'wp_mdg_'.$n;}}
class TestDB {
    public $prefix='wp_',$insert_id=0,$tables=array(),$fail_history=false,$engine='InnoDB';private $snapshot;
    function prepare($sql,...$args){return array($sql,$args);}
    function get_row($q){
        if(is_array($q)){list($sql,$a)=$q;}else{$sql=$q;$a=array();}
        if(strpos($sql,'SHOW TABLE STATUS')===0)return(object)array('Engine'=>$this->engine);
        if(strpos($sql,'mdg_gads_daily')!==false){foreach($this->tables['daily']??array()as$r)if($r['customer_id']===$a[0]&&$r['campaign_id']===$a[1]&&$r['metric_date']===$a[2])return(object)$r;return null;}
        if(strpos($sql,'mdg_v5_expenses')!==false)return isset($this->tables['expenses'][$a[0]])?(object)$this->tables['expenses'][$a[0]]:null;
        return null;
    }
    function get_results($q,$mode=null){return array((object)array('id'=>41,'public_uuid'=>'11111111-1111-1111-1111-111111111111','status'=>'onsale','province_name'=>'Test'));}
    function get_var($q){return null;}
    function query($q){if($q==='START TRANSACTION')$this->snapshot=$this->tables;if($q==='ROLLBACK')$this->tables=$this->snapshot;return 1;}
    function kind($t){if(strpos($t,'finance_history')!==false)return'history';if(strpos($t,'gads_daily')!==false)return'daily';return'expenses';}
    function insert($t,$data){$k=$this->kind($t);if($k==='history'&&$this->fail_history)return false;$id=count($this->tables[$k]??array())+1;$data['id']=$id;$this->tables[$k][$id]=$data;$this->insert_id=$id;return 1;}
    function update($t,$data,$where){$k=$this->kind($t);$id=$where['id'];$this->tables[$k][$id]=array_merge($this->tables[$k][$id],$data);return 1;}
}
$wpdb=new TestDB();
require __DIR__.'/../../wp-content/plugins/mdg-google-ads-finance/mdg-google-ads-finance.php';
$tests=0;
function ok($x,$label){global$tests;if(!$x)throw new RuntimeException('FAIL: '.$label);$tests++;echo 'PASS '.$label."\n";}
function rejects($cb,$label){$thrown=false;try{$cb();}catch(Throwable$e){$thrown=true;}ok($thrown,$label);}
ok(MDG_GAds_Finance::cents('123456789')===12346,'micro to kuruş exact rounding');
ok(MDG_GAds_Finance::cents('4999')===0&&MDG_GAds_Finance::cents('5000')===1,'half kuruş boundary');
foreach(array('-1','1.2','1e6','9999999999999999')as$v)rejects(function()use($v){MDG_GAds_Finance::cents($v);},'invalid micros '.$v);
ok(!MDG_GAds_Finance::date_ok('2026-02-30')&&MDG_GAds_Finance::date_ok('2028-02-29'),'calendar dates');
$uuid='11111111-1111-1111-1111-111111111111';$customer='1234567890';
$options[MDG_GAds_Finance::OPTION]=array('customer'=>$customer,'start'=>'2026-10-01','maps'=>array('123'=>$uuid),'enabled'=>false);
$r=array('customer'=>array('id'=>$customer,'currencyCode'=>'TRY'),'campaign'=>array('id'=>'123','name'=>'Synthetic test'),'segments'=>array('date'=>'2026-10-22'),'metrics'=>array('costMicros'=>'123456789','impressions'=>'200','clicks'=>'4'));
$rows=MDG_GAds_Finance::normalize(array($r),$customer,'2026-10-01','2026-10-26');ok(count($rows)===1,'API fields normalized');
rejects(function()use($r,$customer){MDG_GAds_Finance::normalize(array($r,$r),$customer,'2026-10-01','2026-10-26');},'duplicate API day rejected');
foreach(array('USD','EUR')as$cur){$bad=$r;$bad['customer']['currencyCode']=$cur;rejects(function()use($bad,$customer){MDG_GAds_Finance::normalize(array($bad),$customer,'2026-10-01','2026-10-26');},'foreign currency '.$cur);}
$bad=$r;$bad['customer']['id']='0000000000';rejects(function()use($bad,$customer){MDG_GAds_Finance::normalize(array($bad),$customer,'2026-10-01','2026-10-26');},'wrong account rejected');
ok(MDG_GAds_Finance::apply($rows)===1,'first insert');$expense=$wpdb->tables['expenses'][1];
ok($expense['payment_method']==='Ödenmedi'&&$expense['amount']==='123.46','unpaid realized cost');
ok($expense['event_id']===41&&strpos($expense['document_no'],'GADS-')===0,'UUID resolved to canonical MDG event');
ok(MDG_GAds_Finance::apply($rows)===0&&count($wpdb->tables['expenses'])===1&&count($wpdb->tables['history'])===1,'rerun no duplicate expense or audit');
$changed=$rows;$key=key($changed);$changed[$key]['cost_micros']='124000000';
ok(MDG_GAds_Finance::apply($changed)===1&&$wpdb->tables['expenses'][1]['amount']==='124.00','late revision updates original row');
$wpdb->tables['payments']=array(array('record_id'=>1,'amount'=>'100.00','status'=>'posted'));
$payments=$wpdb->tables['payments'];$zero=$changed;$zero[$key]['cost_micros']='0';MDG_GAds_Finance::apply($zero);
ok($wpdb->tables['expenses'][1]['amount']==='0.00'&&$wpdb->tables['payments']===$payments,'downward zero correction preserves bank payments');
$wpdb->tables['expenses'][1]['description']='Concurrent manual change';
rejects(function()use($changed){MDG_GAds_Finance::apply($changed);},'manual concurrent expense change protected');
ok($wpdb->tables['expenses'][1]['description']==='Concurrent manual change','concurrent edit retained');
$wpdb=new TestDB();$wpdb->fail_history=true;
rejects(function()use($rows){MDG_GAds_Finance::apply($rows);},'audit write failure');
ok(empty($wpdb->tables['expenses'])&&empty($wpdb->tables['daily']),'audit failure rolls back expense and ledger');
$wpdb=new TestDB();$wpdb->engine='MyISAM';rejects(function()use($rows){MDG_GAds_Finance::apply($rows);},'nontransactional table rejected');
$wpdb=new TestDB();$unmapped=$rows;$unmapped[$key]['campaign_id']='999';MDG_GAds_Finance::apply($unmapped);
ok(empty($wpdb->tables['expenses'])&&count($wpdb->tables['daily'])===1,'unmapped campaign queued without financial cost');
$options[MDG_GAds_Finance::OPTION]['maps']['999']=$uuid;MDG_GAds_Finance::apply($unmapped);
ok(count($wpdb->tables['expenses'])===1,'queue promotes after explicit UUID map');
$options[MDG_GAds_Finance::OPTION]['maps']['999']='22222222-2222-2222-2222-222222222222';
rejects(function()use($unmapped){MDG_GAds_Finance::apply($unmapped);},'historical reassignment blocked');
$before=$options;rejects(function(){MDG_GAds_Finance::admin_action();},'unauthorized admin blocked');ok($before===$options,'unauthorized request no writes');
$m=new ReflectionMethod('MDG_GAds_Finance','access_token');$m->setAccessible(true);rejects(function()use($m){$m->invoke(null);},'missing OAuth fails closed');
$m=new ReflectionMethod('MDG_GAds_Finance','token_request');$m->setAccessible(true);try{$m->invoke(null,array());}catch(Throwable$e){ok(strpos($e->getMessage(),'secret-must-not-leak')===false,'OAuth error body is not leaked');}
ok(isset($hooks['admin_post_mdg_v5_finance_record'][1]),'source ownership guard registered before native writes');
ok(!isset($hooks['woocommerce_checkout_create_order'])&&!isset($hooks['mdgy_meta_sync']),'no checkout or Meta hooks');
$hooks['admin_menu'][10]();ok($registered_menu===array('parent'=>'tools.php','cap'=>'manage_options','slug'=>'mdg-gads-finance'),'admin page uses registered WordPress Tools parent');
$m=new ReflectionMethod('MDG_GAds_Finance','secret');$m->setAccessible(true);
$secrets=array('client_id'=>'test-client','client_secret'=>'synthetic-secret','refresh_token'=>'synthetic-refresh');$m->invoke(null,$secrets);
ok($m->invoke(null)===$secrets&&strpos($options[MDG_GAds_Finance::SECRET],'synthetic')===false,'encrypted credentials round trip without plaintext options');
$options[MDG_GAds_Finance::SECRET]=base64_encode('tampered payload with enough bytes');rejects(function()use($m){$m->invoke(null);},'tampered credentials fail closed');
$options[MDG_GAds_Finance::OPTION]=array('customer'=>$customer,'start'=>'2026-10-01','maps'=>array('123'=>$uuid),'version'=>'v26');
$m=new ReflectionMethod('MDG_GAds_Finance','query');$m->setAccessible(true);
$responses=array(array('code'=>200,'body'=>json_encode(array('results'=>array(array('x'=>1)),'nextPageToken'=>'page2'))),array('code'=>200,'body'=>json_encode(array('results'=>array(array('x'=>2))))));
$result=$m->invoke(null,'SELECT campaign.id FROM campaign','synthetic-token');ok(count($result)===2,'all API pages consumed');
ok(strpos($remote[count($remote)-1][0],'https://googleads.googleapis.com/v26/customers/'.$customer.'/googleAds:search')===0,'fixed Google API destination');
$responses=array(array('code'=>200,'body'=>json_encode(array('results'=>array(array('x'=>1)),'nextPageToken'=>'page2'))),array('code'=>429,'body'=>'private-google-error'));
rejects(function()use($m){$m->invoke(null,'SELECT campaign.id FROM campaign','synthetic-token');},'second-page error aborts entire fetch');
$before=$wpdb->tables;MDG_GAds_Finance::preview(array('rows'=>$rows,'campaigns'=>array('123'=>array('id'=>'123')),'from'=>'2026-10-01','to'=>'2026-10-26','timezone'=>'Europe/Istanbul'));
ok($before===$wpdb->tables,'read-only preview writes no financial records');
echo $tests." assertions passed\n";
