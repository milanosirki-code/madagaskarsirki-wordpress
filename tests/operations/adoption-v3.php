<?php
/** Pure classification and real admin/read regression; synthetic fixtures only. */
require __DIR__.'/automation-v2.php';
$db=phase2_db();$program=MMC_Program_Service::get_program(77);
function legacy_task($key='plan',$fields=[]){return (object)array_merge(['id'=>1,'program_id'=>77,'module'=>'operations','title'=>MMC_Operations_Service::task_templates()[$key][1],'status'=>'open','metadata'=>null,'notes'=>'','due_at'=>null,'assigned_user_id'=>null,'completed_at'=>null,'priority'=>'high','created_at'=>'2026-10-01 10:00:00','updated_at'=>'2026-10-01 10:00:00'],$fields);}
foreach(MMC_Operations_Service::task_templates() as$key=>$template){$r=MMC_Operations_Service::legacy_task_classification(legacy_task($key),$program,1);check($r['adoptable']&&$r['template_key']===$key,'Exact template '.$key);}
foreach([
 ['title'=>'Operasyon ve lojistik planını tamamla '],['title'=>'operasyon ve lojistik planını tamamla'],['title'=>'Custom task'],
 ['metadata'=>'{"custom":true}'],['metadata'=>'{"notes":"manual"}'],['notes'=>'custom note']
]as$fields){check(MMC_Operations_Service::legacy_task_classification(legacy_task('plan',$fields),$program,1)['classification']==='CUSTOM','Fuzzy/custom accepted');}
check(MMC_Operations_Service::legacy_task_classification(legacy_task(),$program,2)['classification']==='DUPLICATE','Duplicate adopted');
check(MMC_Operations_Service::legacy_task_classification(legacy_task('plan',['module'=>'finance']),$program,1)['classification']==='WRONG_MODULE','Finance adopted');
check(MMC_Operations_Service::legacy_task_classification(legacy_task('plan',['completed_at'=>'2026-10-01 10:00:00']),$program,1)['classification']==='CLOSED','Completed adopted');
check(MMC_Operations_Service::legacy_task_classification(legacy_task('plan',['metadata'=>'not json']),$program,1)['classification']==='AMBIGUOUS','Invalid metadata adopted');
check(!MMC_Operations_Service::legacy_task_classification(legacy_task(),null,1)['adoptable'],'Orphan adopted');
$cancelled=clone$program;$cancelled->status='cancelled';check(!MMC_Operations_Service::legacy_task_classification(legacy_task(),$cancelled,1)['adoptable'],'Cancelled adopted');
$benign=['phase'=>'pre_departure','template_version'=>1,'system_generated'=>false,'source_key'=>'operations_v1.plan'];
check(MMC_Operations_Service::legacy_task_classification(legacy_task('plan',['metadata'=>wp_json_encode($benign)]),$program,1)['adoptable'],'Safe provenance rejected');
$db->tasks=[legacy_task()];$before=$db->snapshot();$p=MMC_Operations_Service::task_adoption_preview(77);
check($p['would_adopt']===1&&$p['would_update_due']===1&&$p['would_update_owner']===1,'Hypothetical post-adoption proposal');
check($db->snapshot()===$before,'GET preview mutated');
$admin=new MMC_Operations_Admin;
foreach(['GET','INVALID_NONCE','UNAUTHORIZED']as$case){$_SERVER['REQUEST_METHOD']=$case==='GET'?'GET':'POST';$_POST=['program_id'=>77,'task_ids'=>[1],'snapshot'=>$p['snapshot']];$GLOBALS['fixture_authorized']=$case!=='UNAUTHORIZED';$denied=false;try{$admin->adopt_legacy_tasks();}catch(RuntimeException$e){$denied=true;}check($denied&&$db->snapshot()===$before,'POST denial '.$case);}
$GLOBALS['fixture_authorized']=false;check(is_wp_error(MMC_Operations_Service::adopt_legacy_tasks(77,[1],$p['snapshot'])),'Unauthorized service');
$GLOBALS['fixture_authorized']=true;$_SERVER['REQUEST_METHOD']='GET';$_GET=['program_id'=>77];ob_start();$admin->page();$html=ob_get_clean();
check(str_contains($html,'Legacy Görev Preview')&&str_contains($html,'Adım A'),'Adoption panel absent');check($db->snapshot()===$before,'Admin GET mutated');
foreach(['mdg_ai_ops_plan_get','mdg_ai_ops_summary','mdg_ai_ops_checklist_list']as$callback){check(!is_wp_error($callback(['program_id'=>77])),'Read ability regression');}check($db->snapshot()===$before,'Read ability mutation');
echo "PASS {$assertions} isolated assertions; exact adoption classification/security, post-adoption simulation and all read paths write-free.\n";
