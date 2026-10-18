<?php
// Execute the actual guarded fixture repair against a database double.
define('ABSPATH',__DIR__);
$host='staging-f321-milanosirki-lgajf.wpcomstaging.com';$allowed=true;$test='yes';$options=array();$writes=0;
function home_url($p='/'){global $host;return 'https://'.$host.$p;}
function wp_parse_url($u,$p){return parse_url($u,$p);}
function current_user_can($c){global $allowed;return $allowed;}
function add_action(...$a){} function add_filter(...$a){}
function wp_json_encode($v){return json_encode($v);}
function get_option($k,$d=null){global $options,$test;return $k==='woocommerce_paytr_payment_gateway_settings'?array('test'=>$test):($options[$k]??$d);}
function add_option($k,$v,...$a){global $options;if(isset($options[$k]))return false;$options[$k]=$v;return true;}
class WP_Error {function __construct(public $code,public $message){}}
class Milano_Sagliksen {const STAGE='staging-f321-milanosirki-lgajf.wpcomstaging.com';}
class MDG_Events {static function get($id){global $event;return clone $event;}}
class MDG_Sessions {static function by_event($id){global $sessions;return $sessions;}}
class MDG_DB {static function table($n){return 'wp_mdg_'.$n;}}
class DB {function update($table,$data,$where){global $event,$writes,$lastData,$lastWhere;$writes++;$lastData=$data;$lastWhere=$where;if($table!=='wp_mdg_events')throw new Exception('Wrong table');foreach($where as $k=>$v){if((string)$event->$k!==(string)$v)return 0;}foreach($data as $k=>$v)$event->$k=$v;return 1;}}
$wpdb=new DB();$event=(object)array('id'=>24,'title'=>'Milano Sirki — Yalova','status'=>'draft','hero_attachment_id'=>6048,'short_description'=>'','long_description'=>'','updated_at'=>'2026-10-18 10:00:00');
$sessions=array((object)array('id'=>150,'wc_product_id'=>6058),(object)array('id'=>151,'wc_product_id'=>6061));
$temp=sys_get_temp_dir().'/milano-content-'.bin2hex(random_bytes(4));mkdir($temp);
file_put_contents($temp.'/corporate.php','<?php');file_put_contents($temp.'/milano-sagliksen.php','<?php');
copy(__DIR__.'/../../integrations/milano/sagliksen-20261018/stage-lab.php',$temp.'/lab.php');require $temp.'/lab.php';
$n=0;function check($v,$name){global $n;if(!$v)throw new Exception('FAIL '.$name);$n++;echo "PASS $name\n";}
$host='milanosirki.com';check(Milano_Sagliksen_Stage_Content::repair() instanceof WP_Error&&$writes===0,'production host cannot write');
$host=Milano_Sagliksen::STAGE;$allowed=false;check(Milano_Sagliksen_Stage_Content::repair() instanceof WP_Error&&$writes===0,'permissions required');$allowed=true;
$test='no';check(Milano_Sagliksen_Stage_Content::repair() instanceof WP_Error&&$writes===0,'test gateway mode required');$test='yes';
$event->status='onsale';check(Milano_Sagliksen_Stage_Content::repair() instanceof WP_Error&&$writes===0,'published event cannot be edited');$event->status='draft';
$event->short_description='Existing';check(Milano_Sagliksen_Stage_Content::repair() instanceof WP_Error&&$writes===0,'existing content is preserved');$event->short_description='';
$sessions[0]->id=999;check(Milano_Sagliksen_Stage_Content::repair() instanceof WP_Error&&$writes===0,'different session identity cannot write');$sessions[0]->id=150;
$before=serialize($sessions);$result=Milano_Sagliksen_Stage_Content::repair();
check($result['status']==='complete','empty description completed');
check(array_keys($lastData)===array('short_description'),'only description field written');
check($before===serialize($sessions),'session identities and product mappings preserved');
check(isset($lastWhere['status'],$lastWhere['updated_at'],$lastWhere['short_description'],$lastWhere['long_description']),'concurrent content and status guarded');
check(isset($options['milano_stage_yalova_content_before_v2']),'original values backed up');
check(Milano_Sagliksen_Stage_Content::repair()['status']==='already_complete'&&$writes===1,'repeat is idempotent');
$event->short_description=null;$event->long_description=null;$options=array();
check(Milano_Sagliksen_Stage_Content::repair()['status']==='complete','nullable empty database fields supported');
check($lastWhere['short_description']===null&&$lastWhere['long_description']===null,'NULL predicates preserve SQL IS NULL semantics');
foreach(glob($temp.'/*') as $f)unlink($f);rmdir($temp);echo "TOTAL $n assertions\n";
