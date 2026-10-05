<?php
/** Actual service behavior against an isolated database fixture; never connects to production. */
require __DIR__.'/../mmc-integrity/operations-readonly-121.php';
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
function wp_timezone(){return new DateTimeZone('Europe/Istanbul');}
class MMC_Event_Service {
    public static array $rows=[];
    public static ?object $fixture_event=null;
    public static function event_for_program($id){return self::$fixture_event??(object)['id'=>17];}
    public static function sessions($id){return self::$rows;}
}
class AutomationDB extends OpsDB {
    public array $tasks=[];
    public array $schedule_rows=[];
    public array $overview=[];
    public int $reads=0;
    public bool $locked=false;
    public bool $reject_lock=false;
    public array $locks=[];
    public function prepare($sql,...$args){if(str_starts_with($sql,'COALESCE(')){return str_replace('%s',"'".$args[0]."'",$sql);}return parent::prepare($sql,...$args);}
    private function parts($q){return is_array($q)?[$q['sql'],$q['args']]:[(string)$q,[]];}
    public function get_results($q,$output=OBJECT){
        $this->reads++;[$sql,$args]=$this->parts($q);
        if(str_contains($sql,'FROM wp_mmc_programs p')){return $this->overview;}
        if(str_contains($sql,'mmc_tasks')){return array_map(fn($r)=>clone $r,$this->tasks);}
        if(str_contains($sql,'mmc_operation_schedule')){
            return array_values(array_filter(array_map(fn($r)=>clone $r,$this->schedule_rows),fn($r)=>!str_contains($sql,"LIKE 'session_") || ($r->is_system && str_starts_with($r->source_key,'session_'))));
        }
        return parent::get_results($q,$output);
    }
    public function get_var($q){
        $this->reads++;[$sql,$args]=$this->parts($q);
        if(str_contains($sql,'GET_LOCK')){if($this->reject_lock)return 0;$this->locks[$args[0]]=true;return 1;}
        if(str_contains($sql,'RELEASE_LOCK')){unset($this->locks[$args[0]]);return 1;}
        if(str_contains($sql,'mmc_tasks')){
            foreach($this->tasks as $r){if($r->title===end($args) && ($r->module??'operations')===(count($args)>2?$args[1]:'operations'))return $r->id;}return null;
        }
        if(str_contains($sql,'mmc_operation_schedule')){foreach($this->schedule_rows as $r){if($r->program_id===$args[0] && $r->source_key===$args[1])return $r->id;}return null;}
        return parent::get_var($q);
    }
    public function get_row($q,$output=OBJECT){
        $this->reads++;[$sql,$args]=$this->parts($q);
        if(str_contains($sql,'mmc_operation_schedule')){return isset($this->schedule_rows[$args[0]])?clone $this->schedule_rows[$args[0]]:null;}
        return parent::get_row($q,$output);
    }
    public function insert($table,$data){
        parent::insert($table,$data);
        if(str_ends_with($table,'mmc_tasks')){$this->tasks[]=(object)array_merge(['id'=>$this->insert_id,'due_at'=>null,'assigned_user_id'=>null,'completed_at'=>null],$data);}
        if(str_ends_with($table,'mmc_operation_schedule')){$this->schedule_rows[$this->insert_id]=(object)array_merge(['id'=>$this->insert_id,'assigned_user_id'=>null],$data);}
        return true;
    }
    public function update($table,$data,$where){
        parent::update($table,$data,$where);
        if(str_ends_with($table,'mmc_operation_schedule')){foreach($this->schedule_rows as $row){if($row->id===($where['id']??0)){foreach($data as $k=>$v)$row->$k=$v;}}}
        return 1;
    }
    public function delete($table,$where){
        if(str_ends_with($table,'mmc_operation_schedule')){
            foreach($this->schedule_rows as $id=>$row){$match=true;foreach($where as $k=>$v){if(($row->$k??null)!=$v)$match=false;}if($match){parent::delete($table,$where);unset($this->schedule_rows[$id]);}}
            return 1;
        }
        return parent::delete($table,$where);
    }
    public function snapshot(){return hash('sha256',serialize([$this->plan,$this->checklist,$this->tasks,$this->schedule_rows,$this->writes]));}
}
function new_automation($status='operations'){$GLOBALS['fixture_program_status']=$status;$GLOBALS['wpdb']=new AutomationDB;return $GLOBALS['wpdb'];}
$db=new_automation();
$before=$db->snapshot();
foreach(['mdg_ai_ops_plan_get','mdg_ai_ops_summary','mdg_ai_ops_checklist_list'] as $callback){check(!is_wp_error($callback(['program_id'=>77])),'Missing plan read failed');}
check($db->snapshot()===$before,'Missing plan read mutated state');
$plan=MMC_Operations_Service::ensure_plan(77);
check(!is_wp_error($plan),'Explicit initialize failed');
check(count($db->tasks)===13,'Expected 13 high-level operations tasks');
check(count($db->checklist)===43,'Expected one checklist set');
check(count($db->locks)===0,'Initialization lock leaked');
$before=$db->snapshot();MMC_Operations_Service::ensure_plan(77);
check($db->snapshot()===$before,'Second initialization changed domain state');
$keys=array_map(fn($r)=>json_decode($r->metadata,true)['source_key'],$db->tasks);
check(count(array_unique($keys))===13,'Task source keys duplicate');
foreach($db->tasks as $task){check(!isset($task->due_at) && !isset($task->assigned_user_id),'Unapproved deadline/owner assigned');}
// Closed matching tasks are not revived or duplicated.
$db->tasks[0]->status='cancelled';$db->tasks[0]->due_at='2026-10-01 09:00:00';$db->tasks[0]->assigned_user_id=501;
$before=$db->snapshot();MMC_Operations_Service::ensure_plan(77);check($db->snapshot()===$before,'Cancelled task revived or manual fields changed');
$before=$db->snapshot();foreach(['mdg_ai_ops_plan_get','mdg_ai_ops_summary','mdg_ai_ops_checklist_list'] as $callback){$callback(['program_id'=>77]);}
check($db->snapshot()===$before,'Existing plan read mutated state');
// Explicit plan mode writes: daytrip lodging N/A; overnight/multi-city required.
foreach(['daytrip'=>0,'overnight'=>1,'multi_city'=>1] as $mode=>$required){
    MMC_Operations_Service::save_plan(77,['operation_mode'=>$mode,'status'=>'draft']);
    $lodging=array_values(array_filter($db->checklist,fn($r)=>$r->item_key==='lodging_confirmed'))[0];
    check((int)$lodging->is_required===$required,'Lodging required rule wrong');
    check($required?$lodging->status==='pending':$lodging->status==='not_applicable','Lodging applicability wrong');
}
// Schedule session add/change/delete, preserve manually supplied fields and manual rows.
MMC_Event_Service::$rows=[(object)['id'=>88,'session_time'=>'2026-10-08 18:00:00']];
MMC_Operations_Service::sync_schedule_from_event(77);
check(count($db->schedule_rows)===1,'Session was not added');
$id=array_key_first($db->schedule_rows);$db->schedule_rows[$id]->notes='manual note';$db->schedule_rows[$id]->assigned_name='fixture operator';$db->schedule_rows[$id]->assigned_user_id=501;$db->schedule_rows[$id]->status='ready';
$before=$db->snapshot();MMC_Operations_Service::sync_schedule_from_event(77);check($db->snapshot()===$before,'Unchanged schedule sync wrote state');
MMC_Event_Service::$rows[0]->session_time='2026-10-08 19:00:00';MMC_Operations_Service::sync_schedule_from_event(77);
check($db->schedule_rows[$id]->start_at==='2026-10-08 19:00:00','Changed session not synchronized');
check($db->schedule_rows[$id]->notes==='manual note' && $db->schedule_rows[$id]->assigned_name==='fixture operator' && $db->schedule_rows[$id]->assigned_user_id===501 && $db->schedule_rows[$id]->status==='ready','Manual system-schedule fields lost');
$db->insert('wp_mmc_operation_schedule',['program_id'=>77,'source_key'=>'manual_fixture','is_system'=>0,'title'=>'manual item','start_at'=>null]);
MMC_Event_Service::$rows=[];MMC_Operations_Service::sync_schedule_from_event(77);
check(count($db->schedule_rows)===1 && reset($db->schedule_rows)->source_key==='manual_fixture','Deleted session stale / manual item lost');
// Clearing a plan milestone deletes only that exact system-owned key.
$db->plan->doors_open_at='2026-10-08 17:30:00';MMC_Operations_Service::sync_schedule_from_event(77);check(count($db->schedule_rows)===2,'Plan milestone missing');
$db->plan->doors_open_at=null;MMC_Operations_Service::sync_schedule_from_event(77);check(count($db->schedule_rows)===1,'Cleared milestone stale');
// Cancelled/completed programs: reads stay available but initialization fails without writes.
$GLOBALS['fixture_program_status']='cancelled';$before=$db->snapshot();
check(is_wp_error(MMC_Operations_Service::ensure_plan(77)),'Cancelled program initialized');
MMC_Operations_Service::on_program_logged(77,'event_updated','event',17,null,null,'fixture');
check($db->snapshot()===$before,'Cancelled program hook wrote');
$db=new_automation('cancelled');$before=$db->snapshot();check(is_wp_error(MMC_Operations_Service::ensure_plan(77)),'Missing cancelled plan created');check($db->snapshot()===$before,'Cancelled program changed');
$db=new_automation();$db->reject_lock=true;$before=$db->snapshot();check(is_wp_error(MMC_Operations_Service::ensure_plan(77)),'Busy lock ignored');check($db->snapshot()===$before,'Busy initialization wrote');
// Readiness, bounded query count, no backfill; actual service aggregation and pure interpretation.
$db=new_automation();$db->overview=[['id'=>77,'program_status'=>'operations','program_date'=>'2026-10-08','plan_id'=>1,'venue_id'=>1,'session_count'=>1,'check_total'=>43,'required_total'=>20,'required_done'=>10,'required_pending'=>10,'problems'=>1,'vehicles_required'=>1,'vehicles'=>0,'artists_required'=>1,'artists'=>0,'people_required'=>1,'people'=>0,'equipment_required'=>1,'equipment'=>0,'accommodation_required'=>1,'lodging_name'=>'','high_tasks'=>1,'operation_tasks'=>1]];
$before=$db->snapshot();$reads=$db->reads;$overview=MMC_Operations_Service::readiness_overview();check($db->reads-$reads===1,'Overview performed N+1 queries');check($overview[0]['readiness_percent']===50.0,'Readiness percent wrong');check(count($overview[0]['critical_missing'])>=7,'Evidence-based warnings absent');
$preview=MMC_Operations_Service::backfill_preview();check($preview[0]['would_review_tasks']===true,'Template preview missing');check($db->snapshot()===$before,'Readiness/preview wrote domain state');
// Preparation completion and post-show finance transition preserve existing workflow.
$db=new_automation('preparation');$GLOBALS['allow_fixture_status']=true;MMC_Operations_Service::ensure_plan(77);
foreach($db->checklist as $row){if($row->is_required && in_array($row->phase,['pre_departure','venue_setup'],true))$row->status='done';}
$recalc=new ReflectionMethod(MMC_Operations_Service::class,'recalculate_plan_status');$recalc->invoke(null,77);
check($db->plan->status==='ready' && $GLOBALS['fixture_program_status']==='operations','Ready transition failed');
foreach($db->checklist as $row){if($row->phase==='post_show')$row->status='done';}
$recalc->invoke(null,77);check($db->plan->status==='completed' && $GLOBALS['fixture_program_status']==='financial_close','Post-show transition failed');
$finance=array_filter($db->tasks,fn($r)=>$r->module==='finance');check(count($finance)===2,'Finance close tasks not preserved');
$close=new ReflectionMethod(MMC_Operations_Service::class,'ensure_close_tasks');$close->invoke(null,77);check(count(array_filter($db->tasks,fn($r)=>$r->module==='finance'))===2,'Finance tasks duplicated');
check(count($db->locks)===0,'A lock leaked');
// Admin GET render stays read-only, both missing and existing plan.
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function esc_textarea($s){return esc_html($s);}
function admin_url($s){return '/wp-admin/'.$s;}
function selected($a,$b){if($a==$b)echo 'selected';}
function checked($a,$b){if($a==$b)echo 'checked';}
function wp_nonce_field(...$args){echo '<input name="nonce" value="fixture">';}
function submit_button($s,...$args){echo '<button>'.esc_html($s).'</button>';}
require __DIR__.'/../../wp-content/plugins/madagaskar-management-center/includes/class-mmc-operations-admin.php';
$db=new_automation();$_GET=['program_id'=>77];
$before=$db->snapshot();ob_start();(new MMC_Operations_Admin)->page();$html=ob_get_clean();
check(str_contains($html,'Yaklaşan Operasyonlar') && str_contains($html,'Plan Oluştur / Hazırla'),'Missing-plan admin render missing controls');check($before===$db->snapshot(),'Missing-plan admin GET wrote');
MMC_Operations_Service::ensure_plan(77);
foreach(['next_destination','departure_at','venue_entry_at','setup_start_at','rehearsal_at','doors_open_at','teardown_end_at','return_at','lodging_name','lodging_address','lodging_rooms','lodging_cost','meal_plan','meal_cost','transport_cost','other_cost','notes'] as $key){$db->plan->$key=null;}
$before=$db->snapshot();ob_start();(new MMC_Operations_Admin)->page();$html=ob_get_clean();
check(str_contains($html,'Operasyon / Mali Kapanış Görevleri'),'Task read view missing');
check(str_contains($html,'Operasyon Planını Kaydet') && str_contains($html,'Kontrol Listesini Kaydet'),'Existing-plan admin render missing data');check($before===$db->snapshot(),'Existing-plan admin GET wrote');
echo "PASS {$assertions} assertions: initialization, tasks, modes, schedule, cancellation, readiness, finance, read-only contracts.\n";
