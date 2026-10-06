<?php
require __DIR__.'/adoption-v3.php';
foreach(MMC_Operations_Service::operational_statuses()as$status){$db=phase2_db($status);$before=$db->snapshot();check(MMC_Operations_Service::is_operationally_eligible(77),'Allowed future lifecycle '.$status);check($db->snapshot()===$before,'Eligibility wrote '.$status);}
foreach(['preparation','region_analysis','venue_research','allocation_request','allocation_pending','venue_payment','cancelled','completed','financial_close','deposit_refund']as$status){phase2_db($status);check(!MMC_Operations_Service::is_operationally_eligible(77),'Rejected lifecycle '.$status);}
$db=phase2_db('sales_open');$db->plan=null;check(!MMC_Operations_Service::is_operationally_eligible(77),'Missing plan');
$db=phase2_db('sales_prep');MMC_Event_Service::$rows=[];check(!MMC_Operations_Service::is_operationally_eligible(77),'Missing sessions');
$db=phase2_db('venue_confirmed');MMC_Event_Service::$fixture_event->id=0;check(!MMC_Operations_Service::is_operationally_eligible(77),'Missing event');
$db=phase2_db('sales_open');MMC_Event_Service::$fixture_event->event_date='2026-10-01';check(!MMC_Operations_Service::is_operationally_eligible(77),'Past event overridden by future plan');
$db=phase2_db('sales_open');$GLOBALS['fixture_program_fields']['planned_date']='2026-10-01';$ctx=MMC_Operations_Service::task_automation_context(77);check($ctx['eligible']&&$ctx['eligibility']['date_drift']&&$ctx['program_date']==='2026-10-08','Canonical date/drift');
MMC_Event_Service::$fixture_event->event_date=null;$GLOBALS['fixture_program_fields']['planned_date']='2026-10-08';$ctx=MMC_Operations_Service::task_automation_context(77);check($ctx['eligible']&&$ctx['eligibility']['date_source']==='program.planned_date','Planned fallback');
MMC_Event_Service::$fixture_event->event_date='2026-02-30';check(!MMC_Operations_Service::is_operationally_eligible(77),'Invalid canonical date bypassed');
$db=phase2_db('sales_open');$db->plan->departure_at=null;$db->tasks=[legacy_task()];$before=$db->snapshot();$p=MMC_Operations_Service::task_adoption_preview(77);$b=$p['items'][0]['backfill_preview'];
check($p['would_adopt']===1&&$p['would_update_due']===0&&$p['would_update_owner']===1,'Pilot candidate');check($b['proposed_due']===null&&in_array('DUE_ANCHOR_INSUFFICIENT',$b['reason'],true),'Plan fallback not removed');check($b['proposed_owner']===501,'Owner not proposed');check($db->snapshot()===$before&&MMC_Program_Service::get_program(77)->status==='sales_open','Preview changed domain/status');
foreach(['mdg_ai_ops_plan_get','mdg_ai_ops_summary','mdg_ai_ops_checklist_list']as$callback){check(!is_wp_error($callback(['program_id'=>77])),'AI read regression');}check($db->snapshot()===$before,'AI read mutation');
echo "PASS {$assertions} isolated assertions; lifecycle-independent eligibility, date drift, insufficient planning anchor and pilot preview.\n";
