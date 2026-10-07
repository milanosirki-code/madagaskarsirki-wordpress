<?php
/** Phase3C guided timeline/read-only suggestion and chronology guards. */
require __DIR__.'/eligibility-v3b.php';

$db=phase2_db('sales_open');
foreach(array_keys(MMC_Operations_Service::operation_timeline_fields()) as $field){$db->plan->$field=null;}
$before=$db->snapshot();
$preview=MMC_Operations_Service::guided_timeline_preview(77);
check($preview['first_session']==='2026-10-08 18:00:00','First session missing from timeline preview');
check($preview['last_session_start']==='2026-10-08 20:00:00','Last session start missing from timeline preview');
check($preview['fields']['doors_open_at']['suggested']==='2026-10-08 17:30:00','Configured door-open suggestion wrong');
check($preview['fields']['doors_open_at']['source']==='event.first_session_minus_door_open_minutes','Door-open provenance wrong');
check($preview['fields']['departure_at']['suggested']===null && $preview['fields']['departure_at']['manager_required']===true,'Departure time was invented');
check($preview['fields']['setup_start_at']['suggested']===null && $preview['fields']['return_at']['suggested']===null,'Unsupported timeline suggestion invented');
check($db->snapshot()===$before,'Timeline GET preview wrote domain data');

$valid=[
    'departure_at'=>'2026-10-08 09:00:00',
    'venue_entry_at'=>'2026-10-08 12:00:00',
    'setup_start_at'=>'2026-10-08 14:00:00',
    'rehearsal_at'=>'2026-10-08 16:00:00',
    'doors_open_at'=>'2026-10-08 17:30:00',
    'teardown_end_at'=>'2026-10-08 20:30:00',
    'return_at'=>'2026-10-08 22:00:00',
];
check(true===MMC_Operations_Service::validate_operation_timeline(77,$valid),'Valid timeline rejected');

$bad=$valid;$bad['departure_at']='2026-10-08 13:00:00';
check(is_wp_error(MMC_Operations_Service::validate_operation_timeline(77,$bad)),'Departure after venue entry accepted');
$bad=$valid;$bad['doors_open_at']='2026-10-08 18:30:00';
check(is_wp_error(MMC_Operations_Service::validate_operation_timeline(77,$bad)),'Doors after first session accepted');
$bad=$valid;$bad['teardown_end_at']='2026-10-08 19:30:00';
check(is_wp_error(MMC_Operations_Service::validate_operation_timeline(77,$bad)),'Teardown before last session start accepted');
$bad=$valid;$bad['return_at']='2026-10-08 20:00:00';$bad['teardown_end_at']='2026-10-08 21:00:00';
check(is_wp_error(MMC_Operations_Service::validate_operation_timeline(77,$bad)),'Return before teardown accepted');
check(true===MMC_Operations_Service::validate_operation_timeline(77,['departure_at'=>'2026-10-08 09:00:00']),'Partial timeline rejected');

$db->plan->doors_open_at='2026-10-08 17:00:00';
$preview=MMC_Operations_Service::guided_timeline_preview(77);
check($preview['fields']['doors_open_at']['current']==='2026-10-08 17:00:00' && $preview['fields']['doors_open_at']['suggested']===null,'Existing door-open time overwritten by suggestion');

$before=$db->snapshot();$_GET=['program_id'=>77,'task_filter'=>'all'];$_SERVER['REQUEST_METHOD']='GET';
$admin=new MMC_Operations_Admin;ob_start();$admin->page();$html=ob_get_clean();
check(str_contains($html,'Operasyon Zaman Çizelgesi Rehberi'),'Timeline guide missing from admin render');
check(str_contains($html,'Yönetici girişi gerekli'),'Manager-required state missing');
check($db->snapshot()===$before,'Timeline admin GET wrote domain data');

echo "PASS {$assertions} assertions; Phase3C guided timeline suggestions, chronology validation and GET write-free.\n";
