<?php
/**
 * Real MMC/MDG family 2+2 source wiring contract, isolated fixtures only.
 * php integrations/milano/tests/canonical-mmc-family-policy-contract.php CASE
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
function check_mmc($v,$why){if(!$v){fwrite(STDERR,"FAIL ".$why."\n");exit(1);}}

class FamilyMMCDB {
    public $prefix='wp_';
    public $events=array();
    public $links=array();
    public $tickets=array();
    public $queries=array();
    public function prepare($sql,...$args){
        check_mmc(strpos($sql,' UPDATE ')===false&&strpos($sql,' DELETE ')===false
            &&strpos($sql,' INSERT ')===false,'source mutated data');
        check_mmc(strpos($sql,'LIMIT 2')!==false ||
            strpos($sql,'LIMIT 31')!==false,'source query is not bounded');
        check_mmc(count($args)===1,'must use one trusted canonical lookup ID');
        $this->queries[]=array('sql'=>$sql,'args'=>$args);
        return $sql;
    }
    public function get_results($sql){
        if(strpos($sql,'FROM wp_mmc_mdg_event_bridge')!==false)return $this->links;
        if(strpos($sql,'FROM wp_mmc_events')!==false)return $this->events;
        if(strpos($sql,'FROM wp_mmc_ticket_types')!==false)return $this->tickets;
        check_mmc(false,'unexpected untrusted source SQL');
    }
}
$wpdb=new FamilyMMCDB();
$wpdb->events=array((object)array('id'=>301,'mdg_event_id'=>72));
$wpdb->links=array((object)array('mdg_event_id'=>72));
$wpdb->tickets=array(
 (object)array('id'=>401,'ticket_code'=>'child','price'=>'245.00','capacity_units'=>1,'is_active'=>1),
 (object)array('id'=>402,'ticket_code'=>'adult','price'=>'490.00','capacity_units'=>1,'is_active'=>1),
 (object)array('id'=>403,'ticket_code'=>'family_2_2','price'=>'1100.00','capacity_units'=>4,'is_active'=>1)
);
$mdg=array(
 array('ticket_type_id'=>201,'code'=>'CHILD','active'=>true,
    'capacity_units'=>1,'price_cents'=>24500,'woo_price_cents'=>24500,
    'purchasable'=>true,'in_stock'=>true),
 array('ticket_type_id'=>202,'code'=>'ADULT','active'=>true,
    'capacity_units'=>1,'price_cents'=>49000,'woo_price_cents'=>49000,
    'purchasable'=>true,'in_stock'=>true)
);
require dirname(__DIR__).'/checkout-intent-protocol/milano-canonical-mdg-snapshot.php';
require dirname(__DIR__).'/checkout-intent-protocol/milano-canonical-mmc-family-policy.php';
$case=$argv[1]??'valid';
$expected=true;
switch($case){
 case 'valid':break;
 case 'turkish_codes':$mdg[0]['code']='COCUK';$mdg[1]['code']='YETISKIN';break;
 case 'mmc_event_null_mdgevent':$wpdb->events[0]->mdg_event_id=null;break;
 case 'bridge_missing':$wpdb->links=array();$expected=false;break;
 case 'bridge_two_events':
   $wpdb->links[]=(object)array('mdg_event_id'=>73);$expected=false;break;
 case 'bridge_wrong_event':
   $wpdb->links[0]->mdg_event_id=73;$expected=false;break;
 case 'missing_mmc_event':$wpdb->events=array();$expected=false;break;
 case 'duplicate_mmc_event':
   $wpdb->events[]=(object)array('id'=>302,'mdg_event_id'=>72);$expected=false;break;
 case 'wrong_mdg_event':
   $wpdb->events[0]->mdg_event_id=999;$expected=false;break;
 case 'missing_family':
   array_pop($wpdb->tickets);$expected=false;break;
 case 'disabled_family':
   $wpdb->tickets[2]->is_active=0;$expected=false;break;
 case 'duplicate_family':
   $wpdb->tickets[]=(object)array('id'=>405,'ticket_code'=>'family_2_2',
      'price'=>'1100.00','capacity_units'=>4,'is_active'=>1);
   $expected=false;break;
 case 'price_change_mmc':
   $wpdb->tickets[2]->price='1200.00';break;
 case 'price_over_components':
   $wpdb->tickets[2]->price='1470.01';$expected=false;break;
 case 'default_price_fallback_forbidden':
   $wpdb->tickets[2]->price='0.00';$expected=false;break;
 case 'mmc_adult_price_mismatch':
   $wpdb->tickets[1]->price='500.00';$expected=false;break;
 case 'woo_adult_price_mismatch':
   $mdg[1]['woo_price_cents']=50000;$expected=false;break;
 case 'mdg_child_price_mismatch':
   $mdg[0]['price_cents']=25000;$expected=false;break;
 case 'unknown_mdg_role':
   $mdg[0]['code']='JUNIOR';$expected=false;break;
 case 'duplicate_mdg_role':
   $mdg[]=array('ticket_type_id'=>203,'code'=>'COCUK','active'=>true,
     'capacity_units'=>1,'price_cents'=>24500,'woo_price_cents'=>24500,
     'purchasable'=>true,'in_stock'=>true);
   $expected=false;break;
 case 'real_four_unit_type':
   $mdg[]=array('ticket_type_id'=>204,'code'=>'AILE_2_2','active'=>true,
      'capacity_units'=>4,'price_cents'=>110000,'woo_price_cents'=>110000,
      'purchasable'=>true,'in_stock'=>true);
   $expected=false;break;
 case 'unmapped_ticket':
   $mdg[0]['ticket_type_id']=0;$expected=false;break;
 case 'duplicate_ticket_id':
   $mdg[0]['ticket_type_id']=202;$expected=false;break;
 case 'wrong_adult_units':
   $wpdb->tickets[1]->capacity_units=2;$expected=false;break;
 case 'family_units_wrong':
   $wpdb->tickets[2]->capacity_units=1;$expected=false;break;
 case 'inactive_child':
   $mdg[0]['active']=false;$expected=false;break;
 case 'unavailable_adult':
   $mdg[1]['in_stock']=false;$expected=false;break;
 case 'untrusted_program_id':
   $expected=false;break;
 case 'untrusted_event_id':
   $expected=false;break;
 case 'no_db_method':
   $wpdb=new stdClass();$expected=false;break;
 case 'bad_prefix':
   $wpdb->prefix='wp_;DROP';$expected=false;break;
 case 'missing_ticket_rows':
   $wpdb->tickets=array();$expected=false;break;
 case 'inactive_other_type':
   $wpdb->tickets[]=(object)array('id'=>404,'ticket_code'=>'special',
      'price'=>'999.00','capacity_units'=>4,'is_active'=>0);break;
 default:check_mmc(false,'unknown scenario');
}
$pid=$case==='untrusted_program_id'?0:20;
$eventid=$case==='untrusted_event_id'?0:72;
$result=MCPI_Canonical_MMC_Family_Policy::lookup($pid,$eventid,$mdg);
check_mmc($expected===is_array($result),$case.' acceptance mismatch '.json_encode($result));
if($expected){
 check_mmc($result['enabled']===true,'not enabled by matching MMC event');
 check_mmc($result['price_cents']===($case==='price_change_mmc'?120000:110000),
    'price not derived from event');
 check_mmc($result['child_code']===$mdg[0]['code'] &&
  $result['adult_code']===$mdg[1]['code'],'wrong ticket codes');
 check_mmc($result['child_ticket_type_id']===201 && $result['adult_ticket_type_id']===202,
    'ticket IDs must come from exact MDG session');
 check_mmc(count($wpdb->queries)===3,'price lookup not strict and bounded');
 check_mmc($wpdb->queries[0]['args']===array(20),'did not verify inverse active bridge');
 check_mmc($wpdb->queries[1]['args']===array(20),'did not select exact verified MMC program');
 check_mmc($wpdb->queries[2]['args']===array(301),'did not select unique MMC event');
}
echo "PASS {$case}\n";
