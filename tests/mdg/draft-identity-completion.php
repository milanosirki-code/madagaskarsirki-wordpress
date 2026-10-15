<?php
function current_user_can($cap){return $GLOBALS['allow']??true;}
function get_post_status($id){return $GLOBALS['statuses'][$id]??'draft';}
function wp_get_post_parent_id($id){return $GLOBALS['parents'][$id]??0;}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;$GLOBALS['metaWrites']++;return true;}
function clean_post_cache($id){}
function get_current_user_id(){return 1;}
function wp_json_encode($x,$flags=0){return json_encode($x,$flags);}
function is_wp_error($x){return $x instanceof WP_Error;}
class WP_Error {public $code;function __construct($c,$m){$this->code=$c;}function get_error_message(){return $this->code;}}
class MDG_Events {static function get($id){return $GLOBALS['mdg'][$id]??null;}}
class MDG_New_Event_Production_Plan {static function build($e){return ['production_key'=>'MDG-E'.$e->id.'-testcanonical'];}}
class MDG_DB {static function table($s){return 'mdg_'.$s;}static function now(){return '2026-10-15 08:00:00';}}
class IdentityDB {function query($s){return true;}function insert($t,$row){return true;}}
require __DIR__.'/sales-adoption-identity.php';
[$events,$meta,$products,$sessions,$types,$mdg]=unserialize($fixture);
$wpdb=new IdentityDB();$statuses=[];$parents=[];$metaWrites=0;$allow=true;
foreach($mdg as $id=>$e){$e->status='draft';}
foreach($rows as [$id,$tc,$city,$day,$slots]){
 foreach($slots as [$sid,$pid,$time]){
  foreach($sessions[$id] as $s){if($s->id===$sid){$s->status='draft';$s->held_units=0;$s->sold_units=0;$s->wc_product_id=$pid;$s->tickera_event_id=$tc;}}
  foreach($types[$sid] as $i=>$t){$t->wc_variation_id=$pid+$i+1;$parents[$pid+$i+1]=$pid;$statuses[$pid+$i+1]='publish';}
 }
}
$base=serialize([$events,$meta,$products,$sessions,$types,$mdg,$statuses,$parents]);
$start=$count;
foreach([25=>7,26=>7,27=>10] as $eid=>$expected){
 $r=MDG_Sales_Adopter::finalize_draft_identity($eid);
 check(is_array($r)&&count($r['object_ids'])===$expected,'existing objects finalized '.$eid);
 $snapshot=serialize($meta);$again=MDG_Sales_Adopter::finalize_draft_identity($eid);
 check(is_array($again)&&serialize($meta)===$snapshot,'repeat is idempotent '.$eid);
 foreach($r['object_ids'] as $id){check(($meta[$id]['_mdg_adopted_existing']??'')==='1','adopted provenance '.$id);}
}
require __DIR__.'/../../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-new-event-draft-producer.php';
$guard=new ReflectionMethod('MDG_New_Event_Draft_Producer','safe_managed_draft');$guard->setAccessible(true);
check($guard->invoke(null,6081,'MDG-E25-testcanonical')===false,'producer cannot delete adopted objects');
[$events,$meta,$products,$sessions,$types,$mdg,$statuses,$parents]=unserialize($base);
$metaWrites=0;$meta[6082]['_mdg_ticket_type_id']=999;
check(is_wp_error(MDG_Sales_Adopter::finalize_draft_identity(25))&&$metaWrites===0,'foreign metadata rejects all writes');
[$events,$meta,$products,$sessions,$types,$mdg,$statuses,$parents]=unserialize($base);
$sessions[25][0]->sold_units=1;
check(is_wp_error(MDG_Sales_Adopter::finalize_draft_identity(25))&&$metaWrites===0,'sold capacity cannot be reidentified');
[$events,$meta,$products,$sessions,$types,$mdg,$statuses,$parents]=unserialize($base);
$statuses[6081]='publish';
check(is_wp_error(MDG_Sales_Adopter::finalize_draft_identity(25))&&$metaWrites===0,'published product rejected');
[$events,$meta,$products,$sessions,$types,$mdg,$statuses,$parents]=unserialize($base);
$allow=false;
check(is_wp_error(MDG_Sales_Adopter::finalize_draft_identity(25))&&$metaWrites===0,'unauthorized rejected');
echo 'PASS '.($count-$start)." draft identity completion checks\n";
