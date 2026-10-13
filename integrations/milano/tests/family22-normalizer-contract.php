<?php
/** Pure fixtures for Milano virtual family -> real Tickera/Woo child+adult lines. */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/checkout-intent-protocol/milano-family22-normalizer.php';
function check_family($yes,$why){if(!$yes){fwrite(STDERR,"FAIL ".$why."\n");exit(1);}}
$case=$argv[1]??'family_one';
$request=array('event_id'=>72,'session_id'=>93,'family_quantity'=>1,'normal_lines'=>array());
$central=array(
 'event_id'=>72,'session_id'=>93,'owner_brand'=>'milano','order_brand'=>'milano',
 'family_price_cents'=>110000,
 'types'=>array(
   array('kind'=>'child','code'=>'CHILD','price_cents'=>24500,
         'capacity_units'=>1,'ticket_type_id'=>200,'variation_id'=>301,'active'=>true),
   array('kind'=>'adult','code'=>'ADULT','price_cents'=>49000,
         'capacity_units'=>1,'ticket_type_id'=>201,'variation_id'=>302,'active'=>true)
 ));
$ok=true;$expected_units=4;$expected_total=110000;
switch($case){
 case 'family_one': break;
 case 'two_families':$request['family_quantity']=2;$expected_units=8;$expected_total=220000;break;
 case 'ten_families':$request['family_quantity']=10;$expected_units=40;$expected_total=1100000;break;
 case 'extra_child':
    $request['normal_lines'][]=array('code'=>'CHILD','quantity'=>1);
    $expected_units=5;$expected_total=134500;break;
 case 'extra_adult':
    $request['normal_lines'][]=array('code'=>'ADULT','quantity'=>1);
    $expected_units=5;$expected_total=159000;break;
 case 'extra_both':
    $request['normal_lines']=array(array('code'=>'CHILD','quantity'=>1),array('code'=>'ADULT','quantity'=>1));
    $expected_units=6;$expected_total=183500;break;
 case 'combined_two':
    $request['family_quantity']=2;
    $request['normal_lines']=array(array('code'=>'CHILD','quantity'=>2),array('code'=>'ADULT','quantity'=>3));
    $expected_units=13;$expected_total=416000;break;
 case 'no_discount':
    $central['family_price_cents']=147000;
    $expected_total=147000;break;
 case 'nonstandard_event_price':
    $central['family_price_cents']=130000;
    $expected_total=130000;break;
 case 'child_zero_price':
    $central['types'][0]['price_cents']=0;
    $central['family_price_cents']=90000;
    $expected_total=90000;break;
 case 'different_catalogue_codes':
    $central['types'][0]['code']='COCUK';
    $central['types'][1]['code']='YETISKIN';
    break;
 case 'inactive_other_type':
    $central['types'][]=array('kind'=>'family','code'=>'OTHER',
       'price_cents'=>110000,'capacity_units'=>4,
       'ticket_type_id'=>202,'variation_id'=>303,'active'=>false);
    break;
 case 'wrong_source_brand':$central['order_brand']='madagaskar';$ok=false;break;
 case 'wrong_owner_brand':$central['owner_brand']='madagaskar';$ok=false;break;
 case 'event_mismatch':$request['event_id']=73;$ok=false;break;
 case 'session_mismatch':$request['session_id']=94;$ok=false;break;
 case 'zero_families':$request['family_quantity']=0;$ok=false;break;
 case 'eleven_families':$request['family_quantity']=11;$ok=false;break;
 case 'fractional_family':$request['family_quantity']=1.5;$ok=false;break;
 case 'child_unknown':
    $request['normal_lines'][]=array('code'=>'VIP','quantity'=>1);$ok=false;break;
 case 'virtual_family_as_real_type':
    $request['normal_lines'][]=array('code'=>'FAMILY4','quantity'=>1);$ok=false;break;
 case 'real_capacity_four':
    $central['types'][]=array('kind'=>'family','code'=>'FAMILY4',
       'price_cents'=>110000,'capacity_units'=>4,
       'ticket_type_id'=>202,'variation_id'=>303,'active'=>true);
    $ok=false;break;
 case 'adult_multi_unit':$central['types'][1]['capacity_units']=4;$ok=false;break;
 case 'two_children':
    $central['types'][]=array('kind'=>'child','code'=>'CHILD2',
       'price_cents'=>24500,'capacity_units'=>1,
       'ticket_type_id'=>202,'variation_id'=>303,'active'=>true);
    $ok=false;break;
 case 'missing_adult':array_pop($central['types']);$ok=false;break;
 case 'duplicate_type_code':$central['types'][1]['code']='CHILD';$ok=false;break;
 case 'inactive_child':$central['types'][0]['active']=false;$ok=false;break;
 case 'expensive_family':$central['family_price_cents']=147001;$ok=false;break;
 case 'free_family':$central['family_price_cents']=0;$ok=false;break;
 case 'too_many_child':
    $request['normal_lines'][]=array('code'=>'CHILD','quantity'=>19);$ok=false;break;
 case 'too_many_adult':
    $request['normal_lines'][]=array('code'=>'ADULT','quantity'=>19);$ok=false;break;
 case 'full_child_20':
    $request['normal_lines'][]=array('code'=>'CHILD','quantity'=>18);
    $expected_units=22;$expected_total=551000;break;
 case 'wrong_price_type':$central['types'][0]['price_cents']='24500';$ok=false;break;
 case 'wrong_qty_type':
    $request['normal_lines'][]=array('code'=>'CHILD','quantity'=>'1');$ok=false;break;
 case 'missing_variation':$central['types'][1]['variation_id']=0;$ok=false;break;
 case 'invalid_role':$central['types'][0]['kind']='unknown';$ok=false;break;
 case 'duplicate_normal_lines':
    $request['normal_lines']=array(array('code'=>'CHILD','quantity'=>1),array('code'=>'CHILD','quantity'=>1));
    $expected_units=6;$expected_total=159000;break;
 case 'missing_request':unset($request['normal_lines']);$ok=false;break;
 case 'unknown_metadata':
    $request['client_discount_cents']=999999;
    $request['client_brand']='madagaskar';break;
 case 'injected_code':$central['types'][0]['code']='<img>';$ok=false;break;
 default:check_family(false,'unknown case '.$case);
}
$r=MCPI_Milano_Family22_Normalizer::normalize($request,$central);
check_family($r['ok']===$ok,$case.' expected '.($ok?'ok':'block').' got '.json_encode($r));
if(!$ok){
    check_family(!isset($r['payable_cents'],$r['lines']),'blocked request leaked payable plan');
    echo "PASS {$case}\n";exit;
}
check_family($r['expected_admission_units']===$expected_units,'wrong admission units '.$case);
check_family($r['expected_ticket_instances']===$expected_units,'wrong ticket count '.$case);
check_family($r['payable_cents']===$expected_total,'wrong payable '.$case.' got '.$r['payable_cents']);
check_family(count($r['lines'])===2,'family must resolve to TWO real Woo lines');
check_family($r['lines'][0]['code']!=='FAMILY4'&&$r['lines'][1]['code']!=='FAMILY4','fake family variation entered Woo');
check_family($r['lines'][0]['capacity_units_per_ticket_type']===1&&$r['lines'][1]['capacity_units_per_ticket_type']===1,'wrong capacity');
check_family($r['lines'][0]['quantity']+$r['lines'][1]['quantity']===$expected_units,'wrong physical tickets');
check_family($r['component_subtotal_cents']-$r['discount_fee_cents']===$r['payable_cents'],'fee arithmetic mismatch');
check_family(!isset($r['order_id'])&&!isset($r['qr'])&&!isset($r['payment_confirmation']),'normalizer had side effect');
echo "PASS {$case}\n";
