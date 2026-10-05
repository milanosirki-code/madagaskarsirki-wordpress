<?php
/** Issue #121 regression: operations read abilities must never initialize domain state. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('OBJECT', 'OBJECT');
define('OBJECT_K', 'OBJECT_K');

class WP_Error {
    public function __construct(public $code, public $message='') {}
    public function get_error_message(){ return $this->message; }
}
function is_wp_error($x){ return $x instanceof WP_Error; }
function absint($x){ return abs((int)$x); }
function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); }
function sanitize_text_field($v){ return (string)$v; }
function sanitize_textarea_field($v){ return (string)$v; }
function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); }
function current_time($type){ return '2026-10-04 22:00:00'; }
function get_current_user_id(){ return 501; }
function current_user_can($cap){ return true; }
function add_action(...$args){ return true; }

class MMC_Program_Service {
    public static function all_programs(){return array(self::get_program(77));}
    public static function get_program($id){
        if ((int)$id !== 77) return null;
        return (object)[
            'id'=>77,
            'program_code'=>'ISSUE121',
            'province_name'=>'Synthetic',
            'district_name'=>'Fixture',
            'status'=>$GLOBALS['fixture_program_status']??'preparation',
        ];
    }
    public static function add_log($program_id,$action,$entity_type,$entity_id,$old_value,$new_value,$note){
        $GLOBALS['wpdb']->record_external_write('wp_mmc_logs',[
            'program_id'=>$program_id,
            'action'=>$action,
            'entity_type'=>$entity_type,
            'entity_id'=>$entity_id,
            'note'=>$note,
        ]);
        return true;
    }
    public static function set_status(...$args){
        if(empty($GLOBALS['allow_fixture_status'])){throw new RuntimeException('UNEXPECTED_PROGRAM_STATUS_WRITE');}
        $GLOBALS['fixture_program_status']=$args[1];
        self::add_log($args[0],'program_status_changed','program',$args[0],null,$args[1],$args[2]??'');
        return true;
    }
}

class OpsDB {
    public string $prefix='wp_';
    public int $insert_id=0;
    public array $writes=[];
    public ?object $plan=null;
    public array $checklist=[];
    public ?object $task=null;
    public array $resources=[];

    private function unpack($q){
        if (is_array($q) && isset($q['sql'])) return [$q['sql'],$q['args']];
        return [(string)$q,[]];
    }
    public function prepare($sql,...$args){ return ['sql'=>$sql,'args'=>$args]; }

    public function get_row($q,$output=OBJECT){
        [$sql,$args]=$this->unpack($q);
        if (str_contains($sql,'mmc_operation_plans')) {
            return $this->plan ? clone $this->plan : null;
        }
        if (str_contains($sql,'mmc_operation_checklist') && str_contains($sql,"item_key='lodging_confirmed'")) {
            foreach ($this->checklist as $row) {
                if ($row->item_key === 'lodging_confirmed') return clone $row;
            }
            return null;
        }
        if (str_contains($sql,'COUNT(*) total') && str_contains($sql,'mmc_operation_checklist')) {
            $rows=$this->checklist;
            $rows=array_values(array_filter($rows,function($r)use($sql){
                if ($r->status === 'not_applicable') return false;
                if (str_contains($sql,"phase IN ('pre_departure','venue_setup')")) {
                    return in_array($r->phase,['pre_departure','venue_setup'],true) && (int)$r->is_required===1;
                }
                if (str_contains($sql,"phase='post_show'")) {
                    return $r->phase==='post_show' && (int)$r->is_required===1;
                }
                return true;
            }));
            $a=[
                'total'=>count($rows),
                'done_count'=>count(array_filter($rows,fn($r)=>$r->status==='done')),
                'problems'=>count(array_filter($rows,fn($r)=>$r->status==='problem')),
            ];
            return $output===ARRAY_A ? $a : (object)$a;
        }
        return null;
    }

    public function get_results($q,$output=OBJECT){
        [$sql,$args]=$this->unpack($q);
        if (str_contains($sql,'mmc_operation_checklist') && str_contains($sql,'SELECT *')) {
            $rows=$this->checklist;
            if (str_contains($sql,'phase=%s') && isset($args[1])) {
                $rows=array_values(array_filter($rows,fn($r)=>$r->phase===$args[1]));
            }
            return array_map(fn($r)=>clone $r,$rows);
        }
        if (str_contains($sql,'mmc_program_resources') && str_contains($sql,'GROUP BY resource_type')) {
            $counts=[];
            foreach ($this->resources as $r) {
                if (($r->status??'planned')==='cancelled') continue;
                $counts[$r->resource_type]=($counts[$r->resource_type]??0)+1;
            }
            $out=[];
            foreach ($counts as $type=>$total) $out[$type]=(object)['resource_type'=>$type,'total'=>$total];
            return $out;
        }
        return [];
    }

    public function get_var($q){
        [$sql,$args]=$this->unpack($q);
        if (str_contains($sql,'GET_LOCK') || str_contains($sql,'RELEASE_LOCK')) return 1;
        if (str_contains($sql,'mmc_operation_checklist') && str_contains($sql,'SELECT id')) {
            $key=$args[1]??null;
            foreach ($this->checklist as $row) if ($row->item_key===$key) return $row->id;
            return null;
        }
        if (str_contains($sql,'mmc_tasks') && str_contains($sql,'SELECT id')) {
            return $this->task ? $this->task->id : null;
        }
        return null;
    }

    public function insert($table,$data){
        $this->insert_id++;
        $id=$this->insert_id;
        $this->writes[]=['op'=>'insert','table'=>$table,'data'=>$data];
        if ($table===$this->prefix.'mmc_operation_plans') {
            $this->plan=(object)array_merge([
                'id'=>$id,
                'program_id'=>77,
                'accommodation_required'=>0,
                'operation_mode'=>'undecided',
                'origin_city'=>'Ankara',
                'status'=>'draft',
                'updated_at'=>'2026-10-04 22:00:00',
            ],$data);
        } elseif ($table===$this->prefix.'mmc_operation_checklist') {
            $this->checklist[]=(object)array_merge([
                'id'=>$id,'assigned_name'=>'','notes'=>'','completed_at'=>null
            ],$data);
        } elseif ($table===$this->prefix.'mmc_tasks') {
            $this->task=(object)array_merge(['id'=>$id],$data);
        }
        return true;
    }

    public function update($table,$data,$where){
        $this->writes[]=['op'=>'update','table'=>$table,'data'=>$data,'where'=>$where];
        if ($table===$this->prefix.'mmc_operation_checklist') {
            foreach ($this->checklist as $row) {
                if ((int)$row->id===(int)($where['id']??0)) foreach ($data as $k=>$v) $row->$k=$v;
            }
        } elseif ($table===$this->prefix.'mmc_operation_plans' && $this->plan) {
            foreach ($data as $k=>$v) $this->plan->$k=$v;
        }
        return 1;
    }
    public function delete($table,$where){
        $this->writes[]=['op'=>'delete','table'=>$table,'where'=>$where];
        return 1;
    }
    public function record_external_write($table,$data){
        $this->writes[]=['op'=>'insert','table'=>$table,'data'=>$data];
    }
    public function write_count(){ return count($this->writes); }
    public function table_write_count($table){ return count(array_filter($this->writes,fn($w)=>$w['table']===$table)); }
    public function domain_hash(){
        return hash('sha256',serialize([
            'plan'=>$this->plan,
            'checklist'=>$this->checklist,
            'task'=>$this->task,
            'resources'=>$this->resources,
        ]));
    }
}

function check($condition,$message){
    $GLOBALS['assertions']++;
    if (!$condition) throw new RuntimeException($message);
}
function fresh_db(){ $GLOBALS['wpdb']=new OpsDB; return $GLOBALS['wpdb']; }

$assertions=0;
require __DIR__.'/../../wp-content/plugins/madagaskar-management-center/includes/class-mmc-operations-service.php';
require __DIR__.'/../../wp-content/plugins/madagaskar-ai-abilities/modules/mmc-operations.php';

/* Scenario A: no plan/checklist/task/log. Three read abilities must stay write-free. */
$db=fresh_db();
$before=$db->domain_hash();
$r1=mdg_ai_ops_plan_get(['program_id'=>77]);
$r2=mdg_ai_ops_summary(['program_id'=>77]);
$r3=mdg_ai_ops_checklist_list(['program_id'=>77]);
check($db->write_count()===0,'Missing-plan reads wrote domain state');
check($db->domain_hash()===$before,'Missing-plan domain hash changed');
check($r1['plan']===null,'Plan-get should return null for missing plan');
check($r1['summary']['plan_exists']===false,'Plan-get summary should report missing plan');
check($r1['summary']['check_total']===0,'Missing-plan summary should be empty');
check($r2['summary']['plan_exists']===false,'Operations-summary should report missing plan');
check($r2['summary']['pre_total']===0 && $r2['summary']['pre_percent']===0,'Missing-plan preparation summary should be zero');
check($r3['items']===[],'Checklist-list should return [] when no checklist exists');
check($r3['summary']['plan_exists']===false,'Checklist-list summary should report missing plan');

/* Scenario B: existing plan and rows. Reads must return data without touching updated_at/state. */
$db=fresh_db();
$db->plan=(object)[
    'id'=>11,'program_id'=>77,'operation_mode'=>'daytrip','origin_city'=>'Ankara',
    'status'=>'planned','accommodation_required'=>0,'updated_at'=>'2026-09-30 10:00:00'
];
$db->checklist=[
    (object)['id'=>21,'program_id'=>77,'phase'=>'pre_departure','item_key'=>'vehicles_ready','title'=>'Araçlar','is_required'=>1,'sort_order'=>10,'status'=>'done','created_at'=>'2026-09-30 10:00:00','updated_at'=>'2026-09-30 10:00:00'],
    (object)['id'=>22,'program_id'=>77,'phase'=>'venue_setup','item_key'=>'sound_ready','title'=>'Ses','is_required'=>1,'sort_order'=>20,'status'=>'problem','created_at'=>'2026-09-30 10:00:00','updated_at'=>'2026-09-30 10:00:00'],
];
$db->task=(object)['id'=>31,'program_id'=>77,'module'=>'operations','status'=>'open','updated_at'=>'2026-09-30 10:00:00'];
$db->resources=[
    (object)['resource_type'=>'vehicle','status'=>'planned'],
    (object)['resource_type'=>'artist','status'=>'confirmed'],
    (object)['resource_type'=>'person','status'=>'planned'],
    (object)['resource_type'=>'equipment','status'=>'planned'],
];
$before=$db->domain_hash();
$updated=$db->plan->updated_at;
$r1=mdg_ai_ops_plan_get(['program_id'=>77]);
$r2=mdg_ai_ops_summary(['program_id'=>77]);
$r3=mdg_ai_ops_checklist_list(['program_id'=>77]);
check($db->write_count()===0,'Existing-plan reads wrote domain state');
check($db->domain_hash()===$before,'Existing-plan domain hash changed');
check($db->plan->updated_at===$updated,'Existing-plan updated_at changed');
check($r1['summary']['plan_exists']===true,'Existing plan not reported');
check($r1['summary']['check_total']===2 && $r1['summary']['check_done']===1 && $r1['summary']['problems']===1,'Existing checklist summary changed');
check($r2['summary']['pre_total']===2 && $r2['summary']['pre_done']===1 && $r2['summary']['pre_percent']===50.0,'Existing preparation summary changed');
check($r2['summary']['vehicles']===1 && $r2['summary']['artists']===1 && $r2['summary']['people']===1 && $r2['summary']['equipment']===1,'Resource counts changed');
check(count($r3['items'])===2,'Existing checklist rows not returned');

/* Scenario C: explicit initialize remains a write and stays idempotent. */
$db=fresh_db();
$first=mdg_ai_ops_plan_ensure(['program_id'=>77]);
check(!is_wp_error($first) && $first['ensured']===true,'Explicit ensure failed');
check($db->plan!==null,'Explicit ensure did not create plan');
check($db->table_write_count('wp_mmc_operation_plans')===1,'Explicit ensure plan insert count changed');
check($db->table_write_count('wp_mmc_operation_checklist')>0,'Explicit ensure did not seed checklist');
check($db->table_write_count('wp_mmc_tasks')===1,'Explicit ensure did not create operations task');
check($db->table_write_count('wp_mmc_logs')===1,'Explicit ensure did not create audit log');
$writes=$db->write_count();
$hash=$db->domain_hash();
$second=mdg_ai_ops_plan_ensure(['program_id'=>77]);
check(!is_wp_error($second) && $second['ensured']===true,'Second explicit ensure failed');
check($db->table_write_count('wp_mmc_operation_plans')===1,'Second ensure duplicated plan');
check($db->table_write_count('wp_mmc_tasks')===1,'Second ensure duplicated task');
check($db->table_write_count('wp_mmc_logs')===1,'Second ensure duplicated creation log');
check($db->write_count()===$writes,'Second ensure produced a new domain write');
check($db->domain_hash()===$hash,'Second ensure changed initialized domain state');

echo "PASS {$assertions} assertions; missing/existing reads write-free; explicit initialize preserved and idempotent.\n";
