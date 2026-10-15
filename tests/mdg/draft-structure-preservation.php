<?php
define('ABSPATH',__DIR__);
function absint($x){return abs((int)$x);}
function wp_json_encode($x){return json_encode($x);}
class WP_Error {public $code;function __construct($c,$m){$this->code=$c;}}
class MDG_DB {static function table($s){return 'mdg_'.$s;}static function now(){return '2026-10-15 08:00:00';}}
class FakeDB {
 public $writes=0;
 function prepare($sql,...$args){return vsprintf(str_replace('%d','%s',$sql),$args);}
 function get_results($sql){return strpos($sql,'mdg_ticket_types')!==false ? $GLOBALS['oldTypes'] : $GLOBALS['oldSessions'];}
 function get_col($sql){return [152];}
 function query($sql){$this->writes++;throw new Exception('Unexpected destructive write: '.$sql);}
 function delete(...$args){$this->writes++;throw new Exception('Unexpected delete');}
 function insert(...$args){$this->writes++;throw new Exception('Unexpected insert');}
}
$wpdb=new FakeDB();
require __DIR__.'/../../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-sessions.php';
$oldSessions=[(object)['id'=>152,'event_id'=>25,'start_at'=>'2026-10-21 14:30:00','end_at'=>'2026-10-21 15:30:00','status'=>'draft','capacity_total'=>500,'held_units'=>0,'sold_units'=>0,'wc_product_id'=>6081,'tickera_event_id'=>6102]];
$oldTypes=[(object)['id'=>346,'session_id'=>152,'code'=>'CHILD','label'=>'Çocuk','price'=>'245.00','capacity_units'=>1,'is_active'=>1,'sort_order'=>10,'wc_variation_id'=>6082],(object)['id'=>347,'session_id'=>152,'code'=>'ADULT','label'=>'Yetişkin','price'=>'490.00','capacity_units'=>1,'is_active'=>1,'sort_order'=>20,'wc_variation_id'=>6083]];
$input=[['start_at'=>'2026-10-21 14:30:00','end_at'=>'2026-10-21 15:30:00','capacity_total'=>500]];
$catalog=array_map(fn($t)=>(array)$t,$oldTypes);
$count=0;
function check($x,$name){global $count;if(!$x){fwrite(STDERR,"FAIL $name\n");exit(1);}$count++;}
$before=serialize([$oldSessions,$oldTypes]);
check(MDG_Sessions::replace_draft_structure(25,$input,$catalog)===true,'content-only save accepted');
check($wpdb->writes===0 && serialize([$oldSessions,$oldTypes])===$before,'stable IDs mappings capacity and rows unchanged');
$reversed=array_reverse($catalog);
$reversed[0]['sort_order']=10;$reversed[1]['sort_order']=20;
check(MDG_Sessions::replace_draft_structure(25,$input,$reversed)===true,'form order normalization preserves stored catalogue');
foreach(['start_at'=>'2026-10-21 16:30:00','end_at'=>'2026-10-21 16:30:00','capacity_total'=>501] as $field=>$value){
 $bad=$input;$bad[0][$field]=$value;
 $r=MDG_Sessions::replace_draft_structure(25,$bad,$catalog);
 check($r instanceof WP_Error && $r->code==='mdg_structure_protected',"protected $field rejected");
}
foreach(['price'=>'250','label'=>'Other','code'=>'OTHER','capacity_units'=>4] as $field=>$value){
 $bad=$catalog;$bad[0][$field]=$value;
 check(MDG_Sessions::replace_draft_structure(25,$input,$bad) instanceof WP_Error,"protected type $field rejected");
}
check(MDG_Sessions::replace_draft_structure(25,[],$catalog) instanceof WP_Error,'session removal rejected');
check(MDG_Sessions::replace_draft_structure(25,$input,[]) instanceof WP_Error,'type removal rejected');
$oldSessions[0]->wc_product_id=0;$oldSessions[0]->tickera_event_id=0;
foreach($oldTypes as $t){$t->wc_variation_id=0;}
$oldSessions[0]->held_units=1;$bad=$input;$bad[0]['capacity_total']=10;
check(MDG_Sessions::replace_draft_structure(25,$bad,$catalog) instanceof WP_Error,'held capacity without mappings still protected');
check($wpdb->writes===0,'all protected and no-op paths perform zero writes');
echo "PASS $count draft structure preservation checks\n";
