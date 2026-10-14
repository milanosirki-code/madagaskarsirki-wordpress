<?php
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
    public string $code;
    public function __construct($code,$message=''){ $this->code=(string)$code; }
}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
class FakeDB {
    public string $prefix='wp_';
    public array $map=[];
    public array $holds=[];
    public int $reads=0;
    public int $writes=0;
    public function prepare($s,...$args):string{
        return vsprintf($s,array_map(static fn($v)=>is_string($v)?"'".$v."'":$v,$args));
    }
    public function get_results($sql):array{
        if(strpos($sql,'SELECT ')!==0)throw new RuntimeException('Not SELECT');
        ++$this->reads;
        if(strpos($sql,'FROM wp_mdg_order_map')!==false)return array_map(static fn($m)=>(object)$m,$this->map);
        if(strpos($sql,'FROM wp_mdg_holds')!==false)return array_map(static fn($m)=>(object)$m,$this->holds);
        throw new RuntimeException('Unexpected table: '.$sql);
    }
    public function query($sql){++$this->writes;throw new RuntimeException('Write forbidden');}
}
class TicketLine {
    public array $m;
    public int $id;
    public int $variation;
    public int $parent;
    public int $qty;
    public function __construct(int $id,int $variation,int $parent,int $qty,string $component,string $group) {
        $this->id=$id;$this->variation=$variation;$this->parent=$parent;$this->qty=$qty;
        $this->m=[
          '_mdg_family22'=>'1',
          '_mdg_family22_group'=>$group,
          '_mdg_family22_component'=>$component,
          '_mdg_family22_packages'=>'1',
          '_mdg_family22_package_price'=>'1100',
          '_mdg_family22_event_id'=>24,
          '_mdg_family22_session_id'=>151
        ];
    }
    public function get_id(){return $this->id;}
    public function get_meta($name,$single=true){return $this->m[$name]??'';}
    public function get_quantity(){return $this->qty;}
    public function get_variation_id(){return $this->variation;}
    public function get_product_id(){return $this->parent;}
}
class MockOrder {
    public string $status='pending';
    public string $currency='TRY';
    public string $total='1100.00';
    public bool $paid=false;
    public int $id=704;
    public array $lines=[];
    public array $meta=[];
    public function __construct($intent){
      $group='mdg-family22-24-151-fixture-123';
      $this->lines=[new TicketLine(8001,6062,6061,2,'child',$group),
                    new TicketLine(8002,6063,6061,2,'adult',$group)];
      $this->meta=['_milano_brand'=>'milano',
                   '_milano_intent_key'=>$intent,
                   '_mdg_family22_packages'=>'1'];
    }
    public function get_id(){return $this->id;}
    public function get_meta($key,$single=true){return $this->meta[$key]??'';}
    public function get_status(){return $this->status;}
    public function get_currency(){return $this->currency;}
    public function get_total(){return $this->total;}
    public function get_date_paid(){return $this->paid?'2026-10-14':null;}
    public function get_items($type='line_item'){return $type==='line_item'?$this->lines:[];}
}
require dirname(__DIR__,2).'/integrations/milano/checkout-contract/milano-prepayment-order-audit.php';
$pass=0;
function check($yes,$label):void {
    global $pass;
    if(!$yes){fwrite(STDERR,"FAIL $label\n");exit(1);}
    ++$pass;echo "PASS $label\n";
}
$intent=str_repeat('a',64);
$spec=[
  'brand'=>'milano','intent'=>$intent,
  'event_id'=>24,'session_id'=>151,'product_id'=>6061,
  'child_variation_id'=>6062,'adult_variation_id'=>6063,
  'child_type_id'=>344,'adult_type_id'=>345,
  'amount_minor'=>110000,'units'=>4
];
$wpdb=new FakeDB();
$wpdb->map=[
 ['order_item_id'=>8001,'event_id'=>24,'session_id'=>151,
  'ticket_type_id'=>344,'quantity'=>2,'units_per_ticket'=>1,'units_total'=>2],
 ['order_item_id'=>8002,'event_id'=>24,'session_id'=>151,
  'ticket_type_id'=>345,'quantity'=>2,'units_per_ticket'=>1,'units_total'=>2]
];
$wpdb->holds=[['session_id'=>151,'units'=>4,'status'=>'held','expires_at'=>'2027-10-14 12:00:00']];
$o=new MockOrder($intent);
function run_test($name,$mutate,$expected):void {
    global $wpdb,$spec,$intent,$o;
    $wpdb=new FakeDB();
    $wpdb->map=[
     ['order_item_id'=>8001,'event_id'=>24,'session_id'=>151,'ticket_type_id'=>344,'quantity'=>2,'units_per_ticket'=>1,'units_total'=>2],
     ['order_item_id'=>8002,'event_id'=>24,'session_id'=>151,'ticket_type_id'=>345,'quantity'=>2,'units_per_ticket'=>1,'units_total'=>2]
    ];
    $wpdb->holds=[['session_id'=>151,'units'=>4,'status'=>'held','expires_at'=>'2027-10-14 12:00:00']];
    $o=new MockOrder($intent);
    $s=$spec;
    $mutate($o,$wpdb,$s);
    $r=Milano_Prepayment_Order_Audit::inspect($o,$s);
    check(($expected==='pass'&&is_array($r)&&$r['held_capacity_units']===4)
       || ($expected!=='pass'&&is_wp_error($r)&&$r->code==='milano_preflight_'.$expected),
       $name);
    check($wpdb->writes===0,'no DB writes: '.$name);
}
run_test('valid canonical 2+2 order with one hold',static function(){},'pass');
run_test('different brand refused',static function($o){$o->meta['_milano_brand']='madagaskar';},'order_identity_price_or_status');
run_test('different signed intent refused',static function($o){$o->meta['_milano_intent_key']=str_repeat('f',64);},'order_identity_price_or_status');
run_test('failed Woo order refused',static function($o){$o->status='failed';},'order_identity_price_or_status');
run_test('already paid refused',static function($o){$o->paid=true;},'already_paid');
run_test('foreign currency refused',static function($o){$o->currency='USD';},'order_identity_price_or_status');
run_test('amount mismatch refused',static function($o){$o->total='1099.99';},'order_identity_price_or_status');
run_test('extra line refused',static function($o){$o->lines[]=new TicketLine(8003,6062,6061,1,'child','mdg-family22-24-151-fixture-123');},'line_count');
run_test('wrong parent product refused',static function($o){$o->lines[0]->parent=6058;},'family_components');
run_test('wrong child variation refused',static function($o){$o->lines[0]->variation=6059;},'family_components');
run_test('same-type duplicate refused',static function($o){$o->lines[1]->m['_mdg_family22_component']='child';},'family_components');
run_test('cross-session family metadata refused',static function($o){$o->lines[0]->m['_mdg_family22_session_id']=150;},'family_components');
run_test('different family discount groups refused',static function($o){$o->lines[1]->m['_mdg_family22_group']='mdg-family22-24-150-other';},'family_components');
run_test('wrong family item quantity refused',static function($o){$o->lines[1]->qty=1;},'family_components');
run_test('missing canonical MDG order_map refused',static function($o,$db){$db->map=[];},'order_map_missing');
run_test('same order line twice in map refused',static function($o,$db){$db->map[1]['order_item_id']=8001;},'order_map_duplicate_or_foreign');
run_test('cross-session map refused',static function($o,$db){$db->map[1]['session_id']=150;},'order_map_mismatch');
run_test('wrong map type refused',static function($o,$db){$db->map[1]['ticket_type_id']=344;},'order_map_mismatch');
run_test('wrong MDG unit math refused',static function($o,$db){$db->map[1]['units_total']=4;},'order_map_mismatch');
run_test('missing hold refused',static function($o,$db){$db->holds=[];},'hold_missing_or_duplicated');
run_test('duplicate holds refused',static function($o,$db){$db->holds[]=$db->holds[0];},'hold_missing_or_duplicated');
run_test('expired hold refused',static function($o,$db){$db->holds[0]['expires_at']='2025-01-01 00:00:00';},'hold_expired_or_mismatch');
run_test('sold hold refused pre-payment',static function($o,$db){$db->holds[0]['status']='sold';},'hold_expired_or_mismatch');
run_test('wrong hold session refused',static function($o,$db){$db->holds[0]['session_id']=150;},'hold_expired_or_mismatch');
run_test('wrong hold units refused',static function($o,$db){$db->holds[0]['units']=2;},'hold_expired_or_mismatch');
run_test('client inflated capacity units spec refused',static function($o,$db,&$s){$s['units']=8;},'spec');
run_test('foreign event spec refused',static function($o,$db,&$s){$s['brand']='madagaskar';},'spec');
echo "PASS count=".$pass." all checks. No order, payment, ticket, QR or email created.\n";
