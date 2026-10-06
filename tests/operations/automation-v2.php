<?php
/** Isolated Phase2 write/security/policy/read regression; zero production connection. */
require __DIR__.'/automation-v1.php';
function get_user_by($field,$id){return in_array((int)$id,array(501,502,503),true)?(object)['ID'=>(int)$id]:false;}
function wp_die($message,...$args){throw new RuntimeException('WP_DIE:'.$message);}
function check_admin_referer($action){if(($_POST['_wpnonce']??'')!=='fixture-'.$action){throw new RuntimeException('NONCE_REJECTED');}}
class Phase2DB extends AutomationDB {
    public bool $race=false;
    private function split($q){return is_array($q)?[$q['sql'],$q['args']]:[(string)$q,[]];}
    public function get_results($q,$output=OBJECT){
        [$sql,$args]=$this->split($q);
        if(str_contains($sql,'FROM wp_mmc_programs p')){return parent::get_results($q,$output);}
        if(str_contains($sql,'mmc_tasks')){
            $this->reads++;
            $rows=$this->tasks;
            if(str_contains($sql,"module='operations'")){$rows=array_values(array_filter($rows,fn($r)=>$r->module==='operations'));}
            if(str_contains($sql,"status='open'")){$rows=array_values(array_filter($rows,fn($r)=>$r->status==='open'));}
            if(str_contains($sql,'INNER JOIN wp_mmc_programs')){
                $program=MMC_Program_Service::get_program(77);$event=MMC_Event_Service::event_for_program(77);$date=$event->event_date??($program->planned_date??null);
                if(!$date || $date<substr(current_time('mysql'),0,10) || in_array($program->status,['cancelled','completed','financial_close','deposit_refund'],true) || ($event->status??'')==='cancelled'){return [];}
            }
            return array_map(fn($r)=>clone $r,$rows);
        }
        return parent::get_results($q,$output);
    }
    public function get_row($q,$output=OBJECT){
        [$sql,$args]=$this->split($q);
        if(str_contains($sql,'mmc_tasks')){foreach($this->tasks as$r){if($r->id===$args[0])return clone $r;}return null;}
        return parent::get_row($q,$output);
    }
    public bool $cancel_during_apply=false;
    public function query($q){
        [$sql,$args]=$this->split($q);
        if(!str_starts_with($sql,'UPDATE wp_mmc_tasks SET ')){throw new RuntimeException('Unexpected SQL write');}
        if($this->cancel_during_apply){$GLOBALS['fixture_program_status']='cancelled';$this->cancel_during_apply=false;}
        $program=MMC_Program_Service::get_program(77);$event=MMC_Event_Service::event_for_program(77);
        if(!in_array($program->status,['operations','show_day'],true) || $event->status==='cancelled'){return 0;}
        $main=explode(' AND EXISTS(',$sql,2)[0];[$set,$condition]=explode(' WHERE ',substr($main,strlen('UPDATE wp_mmc_tasks SET ')),2);
        $data=[];$where=[];$offset=0;
        foreach(explode(',',$set)as$expr){$key=explode('=',$expr,2)[0];$data[$key]=$args[$offset++];}
        foreach(explode(' AND ',$condition)as$expr){$expr=str_replace('CAST(metadata AS BINARY)','metadata',$expr);if($expr==="module='operations'"){$where['module']='operations';continue;}if(str_ends_with($expr,' IS NULL')){$where[substr($expr,0,-8)]=null;}else{$key=explode('=',$expr,2)[0];$where[$key]=$args[$offset++];}}
        if(isset($data['assigned_user_id']) && $data['assigned_user_id']!==($program->owner_user_id??null)){return 0;}
        return $this->update('wp_mmc_tasks',$data,$where);
    }
    public function update($table,$data,$where){
        if(str_ends_with($table,'mmc_tasks')){
            foreach($this->tasks as$row){if($row->id!==($where['id']??0))continue;
                if($this->race){$this->race=false;$row->due_at='2026-10-09 09:00:00';return 0;}
                foreach($where as$k=>$v){if(($row->$k??null)!=$v)return 0;}
                $this->writes[]=['op'=>'update','table'=>$table,'data'=>$data,'where'=>$where];foreach($data as$k=>$v)$row->$k=$v;return 1;
            }return 0;
        }
        return parent::update($table,$data,$where);
    }
}
function phase2_db($status='operations'){
    $GLOBALS['fixture_authorized']=true;$GLOBALS['fixture_program_status']=$status;
    $GLOBALS['fixture_program_fields']=['planned_date'=>'2026-10-08','owner_user_id'=>501];
    MMC_Event_Service::$fixture_event=(object)['id'=>17,'event_date'=>'2026-10-08','status'=>'sales_open','door_open_minutes'=>30];
    MMC_Event_Service::$rows=[(object)['id'=>88,'session_time'=>'2026-10-08 18:00:00','status'=>'active'],(object)['id'=>89,'session_time'=>'2026-10-08 20:00:00','status'=>'active']];
    $GLOBALS['wpdb']=new Phase2DB;$GLOBALS['wpdb']->plan=(object)['id'=>1,'program_id'=>77,'status'=>'draft','operation_mode'=>'undecided','accommodation_required'=>0,'departure_at'=>'2026-10-08 18:00:00'];foreach(['venue_entry_at','setup_start_at','rehearsal_at','doors_open_at','teardown_end_at','return_at','origin_city','next_destination','lodging_address','lodging_cost','lodging_name','lodging_rooms','meal_cost','meal_plan','notes','other_cost','transport_cost'] as $field){$GLOBALS['wpdb']->plan->$field=null;}return $GLOBALS['wpdb'];
}
function fixture_task($db,$key='plan',$override=[]){
    $template=MMC_Operations_Service::task_templates()[$key];
    $data=array_merge(['program_id'=>77,'module'=>'operations','title'=>$template[1],'status'=>'open','priority'=>'normal','due_at'=>null,'assigned_user_id'=>null,'completed_at'=>null,'notes'=>'MANUAL_NOTE','metadata'=>wp_json_encode(['source_key'=>'operations_v1.'.$key,'system_generated'=>true,'phase'=>$template[0],'template_version'=>1]),'created_at'=>'2026-10-01 10:00:00','updated_at'=>'2026-10-01 10:00:00'],$override);
    $db->insert('wp_mmc_tasks',$data);return end($db->tasks);
}
$db=phase2_db();MMC_Operations_Service::ensure_plan(77);
check(count($db->tasks)===13,'New task count changed');
$by_title=[];foreach($db->tasks as$t)$by_title[$t->title]=$t;
$plan_task=$by_title[MMC_Operations_Service::task_templates()['plan'][1]];
check($plan_task->due_at==='2026-10-08 18:00:00' && $plan_task->assigned_user_id===501,'New tasks not enriched');
$meta=json_decode($plan_task->metadata,true);check($meta['due_source']==='plan.departure_at' && $meta['due_managed']==='operations_v2','New deadline provenance missing');
check($by_title[MMC_Operations_Service::task_templates()['box_office'][1]]->due_at==='2026-10-08 17:30:00','Configured door minutes not used');
check($by_title[MMC_Operations_Service::task_templates()['inventory'][1]]->due_at===null,'Invented session duration used');
// Every deterministic anchor/fallback, plus date-only and invalid date behavior.
$ctx=MMC_Operations_Service::task_automation_context(77);$ctx['plan']=(object)['departure_at'=>'2026-10-08 09:00:00','venue_entry_at'=>'2026-10-08 12:00:00','rehearsal_at'=>'2026-10-08 16:00:00','setup_start_at'=>'2026-10-08 14:00:00','doors_open_at'=>'2026-10-08 17:00:00','teardown_end_at'=>'2026-10-08 22:00:00','return_at'=>'2026-10-08 23:00:00'];
$expected=['plan'=>'09:00:00','transport'=>'09:00:00','crew'=>'09:00:00','equipment'=>'09:00:00','venue_entry'=>'12:00:00','handover_in'=>'12:00:00','technical'=>'16:00:00','box_office'=>'17:00:00','briefing'=>'17:00:00','inventory'=>'22:00:00','handover_out'=>'22:00:00','reconcile'=>'22:00:00','return'=>'23:00:00'];
foreach($expected as$key=>$time){check(MMC_Operations_Service::resolve_task_deadline($key,$ctx)['due_at']==='2026-10-08 '.$time,'Anchor wrong:'.$key);}
$ctx['plan']->departure_at=null;check(MMC_Operations_Service::resolve_task_deadline('plan',$ctx)['due_source']==='plan.venue_entry_at','Plan fallback venue wrong');
$ctx['plan']->venue_entry_at=null;check(MMC_Operations_Service::resolve_task_deadline('crew',$ctx)['due_at']===null,'Crew deadline fabricated');
$ctx['plan']->rehearsal_at=null;check(MMC_Operations_Service::resolve_task_deadline('technical',$ctx)['due_source']==='plan.setup_start_at','Technical setup fallback wrong');
$ctx['plan']->setup_start_at=null;check(MMC_Operations_Service::resolve_task_deadline('technical',$ctx)['due_source']==='event.first_session','Technical session fallback wrong');
$ctx['plan']->teardown_end_at=null;$ctx['last_session_end']='2026-10-08 21:45:00';check(MMC_Operations_Service::resolve_task_deadline('inventory',$ctx)['due_at']===$ctx['last_session_end'],'Confirmed end fallback wrong');
$ctx['plan']=null;$ctx['first_session']=null;$ctx['last_session_end']=null;check(MMC_Operations_Service::resolve_task_deadline('plan',$ctx)['due_at']===null,'Program date became midnight deadline');
$ctx['plan']=(object)['departure_at'=>'2026-02-30 09:00:00'];check(MMC_Operations_Service::resolve_task_deadline('plan',$ctx)['due_at']===null,'Invalid date accepted');
// Existing tasks apply only explicitly; preserve unrelated/manual fields.
$db=phase2_db();$task=fixture_task($db);$before=$db->snapshot();$preview=MMC_Operations_Service::task_automation_preview(77);
check($preview['would_update_due']===1 && $preview['would_update_owner']===1,'Preview proposals missing');check($db->snapshot()===$before,'GET preview mutated');
$r=MMC_Operations_Service::apply_task_automation(77);check($r['tasks_updated']===1 && $r['due_updated']===1 && $r['owner_updated']===1,'Explicit apply failed');
check($task->status==='open' && $task->priority==='normal' && $task->notes==='MANUAL_NOTE' && $task->completed_at===null,'Protected columns changed');
$before=$db->snapshot();$r=MMC_Operations_Service::apply_task_automation(77);check($r['tasks_updated']===0 && $db->snapshot()===$before,'Double apply not idempotent');
$db->plan->departure_at='2026-10-08 19:00:00';MMC_Event_Service::$rows[0]->session_time='2026-10-08 19:00:00';$GLOBALS['fixture_program_fields']['owner_user_id']=502;
$r=MMC_Operations_Service::apply_task_automation(77);check($task->due_at==='2026-10-08 19:00:00' && $task->assigned_user_id===502,'Auto-managed canonical update failed');
$db->plan=(object)['id'=>1,'program_id'=>77,'departure_at'=>'2026-10-08 08:00:00'];MMC_Operations_Service::apply_task_automation(77);check($task->due_at==='2026-10-08 08:00:00','Changed plan anchor did not update managed due');
$task->due_at='2026-10-07 10:00:00';$task->assigned_user_id=503;$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Direct manual edit overwritten');
// Even manual clearing without a hook is detected as divergence from the stored auto values.
$task->due_at=null;$task->assigned_user_id=null;$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Manual cleared fields refilled');
// Same-value and clearing manual edits via existing task action/log explicitly revoke management.
$db=phase2_db();$task=fixture_task($db);MMC_Operations_Service::apply_task_automation(77);
MMC_Operations_Service::on_program_logged(77,'task_updated','task',$task->id,null,['due_at'=>$task->due_at,'assigned_user_id'=>$task->assigned_user_id],'fixture manual edit');
$meta=json_decode($task->metadata,true);check($meta['due_manual_override']===true && $meta['assignment_manual_override']===true,'Manual provenance not revoked');
$before=$db->snapshot();$db->plan->departure_at='2026-10-08 19:00:00';MMC_Event_Service::$rows[0]->session_time='2026-10-08 19:00:00';$GLOBALS['fixture_program_fields']['owner_user_id']=502;$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Same-value manual edit lost to canonical change');
// Existing manual values, unmarked/finance/closed tasks and invalid owner are protected.
$db=phase2_db();$manual=fixture_task($db,'plan',['due_at'=>'2026-10-06 12:00:00','assigned_user_id'=>503]);$unmarked=fixture_task($db,'crew',['metadata'=>null]);$finance=fixture_task($db,'technical',['module'=>'finance']);$completed=fixture_task($db,'return',['status'=>'completed','completed_at'=>'2026-10-01 12:00:00']);$cancelled=fixture_task($db,'equipment',['status'=>'cancelled']);
$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Manual/unmarked/finance/closed data changed');
$db=phase2_db();$GLOBALS['fixture_program_fields']['owner_user_id']=99999;$db->plan->departure_at=null;$t=fixture_task($db,'transport');$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Invalid owner or missing transport anchor applied');
foreach(['cancelled','completed','preparation','region_analysis','financial_close'] as$status){$db=phase2_db($status);fixture_task($db);$before=$db->snapshot();check(is_wp_error(MMC_Operations_Service::apply_task_automation(77)),'Stage accepted:'.$status);check($db->snapshot()===$before,'Excluded program updated');}
$db=phase2_db();MMC_Event_Service::$fixture_event->event_date='2026-10-01';fixture_task($db);$before=$db->snapshot();check(is_wp_error(MMC_Operations_Service::apply_task_automation(77)),'Historical program applied');check($db->snapshot()===$before,'Historical state changed');
$db=phase2_db();MMC_Event_Service::$fixture_event->status='cancelled';fixture_task($db);check(is_wp_error(MMC_Operations_Service::apply_task_automation(77)),'Cancelled event accepted');
$db=phase2_db();$task=fixture_task($db);$db->race=true;$r=MMC_Operations_Service::apply_task_automation(77);check($r['stale_skipped']===1 && $task->due_at==='2026-10-09 09:00:00' && $task->assigned_user_id===null,'Concurrent manual edit overwritten');
// Metadata extensions survive; program date alone/closed record/unknown templates stay fail-closed.
$db=phase2_db('show_day');$task=fixture_task($db);$meta=json_decode($task->metadata,true);$meta['custom_note']='KEEP_EXISTING_NOTE';$task->metadata=wp_json_encode($meta);MMC_Operations_Service::apply_task_automation(77);check(json_decode($task->metadata,true)['custom_note']==='KEEP_EXISTING_NOTE','Metadata note lost');
$db=phase2_db();$task=fixture_task($db);$meta=json_decode($task->metadata,true);$meta['source_key']='unknown-template';$task->metadata=wp_json_encode($meta);$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Unknown template managed');
$db=phase2_db();$task=fixture_task($db);$task->completed_at='2026-10-01 10:00:00';$before=$db->snapshot();MMC_Operations_Service::apply_task_automation(77);check($db->snapshot()===$before,'Open task with historical completion modified');
$db=phase2_db();fixture_task($db);$before=$db->snapshot();$db->cancel_during_apply=true;$r=MMC_Operations_Service::apply_task_automation(77);check($r['stale_skipped']===1 && $db->snapshot()===$before,'Concurrent program cancellation wrote task fields');
// Security: service cap, HTTP POST, nonce and no writes on denial.
$db=phase2_db();fixture_task($db);$GLOBALS['fixture_authorized']=false;$before=$db->snapshot();check(is_wp_error(MMC_Operations_Service::apply_task_automation(77)),'Unauthorized service apply accepted');check($db->snapshot()===$before,'Unauthorized service wrote');
$admin=new MMC_Operations_Admin;$GLOBALS['fixture_authorized']=true;
foreach(['GET','NO_NONCE','UNAUTHORIZED'] as$case){$_SERVER['REQUEST_METHOD']=$case==='GET'?'GET':'POST';$_POST=['program_id'=>77];$GLOBALS['fixture_authorized']=$case!=='UNAUTHORIZED';$before=$db->snapshot();$denied=false;try{$admin->apply_task_automation();}catch(RuntimeException$e){$denied=true;}check($denied && $db->snapshot()===$before,'Admin security denial failed:'.$case);}
$GLOBALS['fixture_authorized']=true;$_SERVER['REQUEST_METHOD']='GET';
// Computed time states never alter task status. Cards, filters and AI reads share data.
$db=phase2_db();$overdue=fixture_task($db,'plan',['due_at'=>'2026-10-04 21:00:00','assigned_user_id'=>501,'priority'=>'high']);fixture_task($db,'crew',['due_at'=>'2026-10-04 23:00:00']);fixture_task($db,'equipment',['due_at'=>'2026-10-06 21:00:00']);fixture_task($db,'return');
check(MMC_Operations_Service::task_due_state($overdue)['state']==='OVERDUE','Overdue wrong');check(MMC_Operations_Service::task_due_state($db->tasks[1])['state']==='DUE_TODAY','Today wrong');check(MMC_Operations_Service::task_due_state($db->tasks[3])['state']==='NO_DEADLINE','No-deadline wrong');
check(!MMC_Operations_Service::task_due_state((object)['status'=>'completed','due_at'=>'2026-10-01 10:00:00'])['overdue'],'Completed overdue');
$before=$db->snapshot();$reads=$db->reads;$alerts=MMC_Operations_Service::task_alerts();check($db->reads-$reads===1,'Alerts N+1');check($alerts['counts']['overdue']===1 && $alerts['counts']['due_today']===2 && $alerts['counts']['upcoming_48h']===2 && $alerts['counts']['no_deadline']===1 && $alerts['counts']['mine']===1,'Card counts wrong');
foreach(['overdue'=>1,'today'=>2,'48h'=>2,'mine'=>1,'high'=>1,'no_deadline'=>1]as$filter=>$count){check(count(MMC_Operations_Service::task_alerts(0,$filter)['items'])===$count,'Filter wrong:'.$filter);}
check($alerts['notification_policy_preview']['provider_calls']===0 && $alerts['notification_policy_preview']['notification_log_writes']===0,'External notification side effect');
foreach(['mdg_ai_ops_plan_get','mdg_ai_ops_summary','mdg_ai_ops_checklist_list']as$callback){check(!is_wp_error($callback(['program_id'=>77])),'Read ability failed');}check($db->snapshot()===$before,'Alert/filter/AI GET wrote');
$_GET=['program_id'=>77,'task_filter'=>'mine'];ob_start();$admin->page();$html=ob_get_clean();check(str_contains($html,'Operations Faz 2 Preview') && str_contains($html,'MMC İçi Görev Uyarıları'),'Admin preview/alerts missing');check($db->snapshot()===$before,'Admin GET wrote');
$GLOBALS['fixture_program_status']='cancelled';$before=$db->snapshot();check(MMC_Operations_Service::task_alerts()['counts']['open']===0,'Cancelled in active alerts');check($db->snapshot()===$before,'Cancelled alerts wrote');
check(count($db->locks)===0,'Phase2 lock leaked');
echo "PASS {$assertions} assertions; Phase2 canonical policy, manual protection, explicit apply security/idempotency, alerts/filters/AI GET write-free.\n";
