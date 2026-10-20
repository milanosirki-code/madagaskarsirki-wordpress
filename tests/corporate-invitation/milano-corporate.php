<?php
// Reuse real engine fixtures and existing campaign regression coverage.
require __DIR__.'/dynamic-catalogue.php';
function is_wp_error($v){return $v instanceof Exception;}
class MDG_Events {static function get($id){global $event_rows;foreach($event_rows as $e)if((int)$e->id===$id)return $e;return null;}}
class Milano_Corporate_Response {function get_data(){global $verified;return array('brand'=>'milano','sessions'=>$verified);}}
class Milano_Shared_Checkout_Handoff {static function catalog(){return new Milano_Corporate_Response();} static function validate_cart_entry($passed,$pid,$qty,$vid=0,$vars=array(),$data=array()){return $passed && $pid===12345;}}
$verified=array(array('event_id'=>12,'session_id'=>120),array('event_id'=>40,'session_id'=>400));
$registry=array('institution'=>array('name'=>'Örnek Kurum','active'=>true,'brand'=>'milano','preview_only'=>true,'cities'=>array(
    'denizli'=>array('province'=>'Denizli','active'=>true,'pricing'=>array('adult_campaign'=>475,'child_campaign'=>250)),
    'ankara'=>array('province'=>'Ankara','active'=>true,'pricing'=>array('adult_campaign'=>475,'child_campaign'=>250))
)));
$_GET=$_POST=array();$_SERVER['REQUEST_METHOD']='GET';
$html=$c::render(array('brand'=>'milano'));
check(str_contains($html,'MİLANO SİRKİ · KURUMSAL KAMPANYALAR'),'generic Milano identity');
check(!str_contains($html,'data-event-id')&&!str_contains($html,'Sağlık-Sen')&&!str_contains($html,'Kocaeli ve Yalova'),'landing reveals no hardcoded institution or city list');
$_GET=array('kod'=>'institution');
$html=$c::render(array('brand'=>'milano'));
check(str_contains($html,'Kampanyanıza uygun gösteriler')&&str_contains($html,'data-event-id="12"'),'code opens canonical Milano shows');
check(str_contains($html,'name="mdg_campaign_birthdates[]"')&&str_contains($html,'Yetişkin sayısı (13 yaş ve üzeri)'),'same native birthdate and adult selectors');
check(!str_contains($html,'value="mdg_campaign_checkout"'),'preview code never exposes checkout action');
check(!str_contains($c::render(),'data-event-id'),'Milano code cannot open default Madagaskar page');
$campaign=$c::resolve('institution',$event_rows,$registry,'2026-10-05');
$cat=$c::catalogue($campaign,$event_rows);
check(isset($cat[12],$cat[40])&&!isset($cat[10]),'city and canonical brand scopes both apply');
$campaign['event_ids']=array(12);
check(array_keys($c::catalogue($campaign,$event_rows))===array(12),'optional configured event scope preserved');
$q=$c::quote($cat,'120','3',array('3','4','5','6','7','8'));
check($q['total']===1425.0&&$q['free_children']===6,'same native allowance beyond two adults');
$q=$c::quote($cat,'120','1',array('2','3','12','13'));
check($q['total']===950.0&&$q['infants']===1&&$q['free_children']===2&&$q['older']===1,'same infant and 13-plus conditions');
$verified=array();
check($c::catalogue($campaign,$event_rows)===array(),'missing verified brand bridge fails closed');
check(isset($c::manifest_quote(array('code'=>'institution','session'=>'120','adults'=>'1','births'=>array()))['error']),'preview code cannot create payable manifest');
echo "Milano corporate redesign regressions passed.\n";

require __DIR__.'/../../wp-content/plugins/milano-corporate-campaigns/milano-corporate-campaigns.php';
$verified=array(array('event_id'=>12,'session_id'=>120),array('event_id'=>40,'session_id'=>400));
$registry['institution']['preview_only']=false;
$m=array('code'=>'institution','session'=>'120','adults'=>'1','births'=>array('2018-01-01','2017-01-01','2016-01-01','2025-01-01'));
$q=$c::manifest_quote($m);
check(!isset($q['error']),'payable Milano manifest uses native quote');
$plan=$c::plan($q);
foreach($plan as $role=>$row){
 if(!$row['qty'])continue;
 $t=$row['type'];
 $data=array('mdg_campaign_manifest'=>$m,'mdg_campaign_signature'=>$c::sign($m),'mdg_campaign_role'=>$role,'mdg_event_id'=>12,'mdg_session_id'=>120,'mdg_ticket_type_id'=>$t['type_id'],'mdg_capacity_units'=>1);
 check(Milano_Corporate_Campaigns::authorize_item($data,$t['parent'],$t['variation'],$row['qty'],12),'signed canonical '.$role.' authorized');
 check(!Milano_Corporate_Campaigns::authorize_item($data,$t['parent'],$t['variation'],$row['qty']+1,12),'changed '.$role.' quantity rejected');
 $bad=$data;$bad['mdg_campaign_manifest']['adults']='2';
 check(!Milano_Corporate_Campaigns::authorize_item($bad,$t['parent'],$t['variation'],$row['qty'],12),'tampered manifest rejected');
 $bad=$data;$bad['mdg_ticket_type_id']++;
 check(!Milano_Corporate_Campaigns::authorize_item($bad,$t['parent'],$t['variation'],$row['qty'],12),'changed ticket type rejected');
 check(!Milano_Corporate_Campaigns::authorize_item($data,$t['parent'],$t['variation']+10,$row['qty'],12),'foreign variation rejected');
 $marked=Milano_Corporate_Campaigns::mark($data,$t['parent'],$t['variation'],$row['qty']);
 check(!empty($marked['milano_handoff']),'only verified campaign item marked');
 $registry['institution']['active']=false;
 check(!Milano_Corporate_Campaigns::authorize_item($data,$t['parent'],$t['variation'],$row['qty'],12),'revoked campaign rejected');
 $registry['institution']['active']=true;
}
check(!Milano_Corporate_Campaigns::validation(false,1,1,2,array(),array()),'prior validation failure preserved');
$legacy=$registry;$legacy['institution']['schema']='milano_bundle_v1';
check($c::resolve('institution',$event_rows,$legacy,'2026-10-05')===null,'fixed bundle record never reinterpreted as native campaign');
echo "Signed Milano cart authorization regressions passed.\n";

function has_filter($hook,$callback){return $callback===array('Milano_Shared_Checkout_Handoff','validate_cart_entry')?90:false;}
function remove_filter($hook,$callback,$priority){global $removed_guard;$removed_guard=array($hook,$callback,$priority);}
Milano_Corporate_Campaigns::boot();
check($removed_guard[1]===array('Milano_Shared_Checkout_Handoff','validate_cart_entry'),'only recognized installed handoff callback wrapped');
check(Milano_Corporate_Campaigns::validation(true,12345,1),'ordinary allowed selection delegates original guard');
check(!Milano_Corporate_Campaigns::validation(true,12346,1),'ordinary blocked selection preserves original guard');
check(!Milano_Corporate_Campaigns::validation(false,12345,1),'another validator failure remains blocked');
$registry['institution']['preview_only']=true;
check(!Milano_Corporate_Campaigns::authorize_item($data,$t['parent'],$t['variation'],$row['qty'],12),'preview-only code cannot authorize cart');
echo "Guard delegation regressions passed.\n";
