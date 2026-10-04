<?php
/** Exact captured native methods, isolated synthetic persistence; no HTTP/PayTR/customer data. */
namespace {
    define('ABSPATH', __DIR__);
    $GLOBALS['orders']=[]; $GLOBALS['items']=[]; $GLOBALS['tickets']=[]; $GLOBALS['meta']=[];
    $GLOBALS['filters']=[]; $GLOBALS['timeline']=[]; $GLOBALS['checks']=0;
    function expect($ok,$message){$GLOBALS['checks']++;if(!$ok)throw new \RuntimeException($message);}
    function apply_filters($hook,$value,...$args){foreach($GLOBALS['filters'][$hook]??[] as $cb)$value=$cb($value,...$args);return $value;}
    function add_filter($h,$cb,...$rest){$GLOBALS['filters'][$h][]=$cb;}
    function add_action(...$args){} function tickera_do_action(...$args){}
    function do_action(...$args){} function is_plugin_active($name){return false;}
    function __( $s,$domain=''){return $s;} function sanitize_text_field($s){return is_scalar($s)?(string)$s:'';}
    function tickera_sanitize_array($a,...$args){return $a;} function get_option(...$args){return [];} function get_current_user_id(){return 0;}
    function get_post_type($id){return isset($GLOBALS['orders'][$id])?'shop_order_placehold':'tc_tickets_instances';}
    function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
    function add_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
    function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
    function wp_insert_post($data,$error=false){$id=50000+count($GLOBALS['tickets']);$GLOBALS['tickets'][$id]=$data;$GLOBALS['timeline'][]=['T1','native_instance_created',$id];return $id;}
    function wc_get_order($id){return $GLOBALS['orders'][$id]??false;}
    function tc_wb_parse_meta_value($v){return is_array($v)?$v:[];}
    function tickera_timestamp_to_local($v=null){return $v??1700000000;}
    function tickera_format_date($v,...$rest){return (string)$v;}
    function tickera_ticket_code_to_id($code){foreach($GLOBALS['tickets'] as $id=>$p)if(get_post_meta($id,'ticket_code',true)===$code)return $id;return false;}
    function absint($v){return abs((int)$v);} function tickera_do_timestamp(){return 0;}
    class WC_Order {
        public $id,$status='pending',$paid=null,$items=[],$meta=[];
        function __construct($id){$this->id=$id;}
        function get_id(){return $this->id;} function get_status(){return $this->status;}
        function is_paid(){return in_array($this->status,['processing','completed'],true);}
        function get_date_paid(){return $this->paid;} function get_items($type='line_item'){return $this->items;}
        function get_meta($k){return $this->meta[$k]??[];} function update_meta_data($k,$v){$this->meta[$k]=$v;}
        function save(){} function get_transaction_id(){return $this->paid?'SYNTHETIC-TX':'';}
    }
    class WC_Order_Item_Product {
        public $id,$product=701,$variation=801,$qty=1;
        function __construct($id){$this->id=$id;if(isset($GLOBALS['items'][$id]))foreach(['product','variation','qty'] as $k)$this->$k=$GLOBALS['items'][$id]->$k;}
        function get_variation_id(){return $this->variation;} function get_product_id(){return $this->product;}
        function get_quantity(){return $this->qty;}
    }
    class MDG_DB {static function table($s){return 'synthetic_'.$s;}static function now(){return '2000-01-01 00:00:00';}}
    class MDG_Capacity {static function commit_order($id){$GLOBALS['timeline'][]=['T4','synthetic_capacity_commit',$id];}}
    class MDG_Status {const ONSALE='onsale';}
    class MDG_Events {static function get($id){return(object)['status'=>'onsale'];}}
    class FakeDB {
        function prepare($query,...$args){return [$query,$args];}
        function get_row($q){[$sql,$args]=$q;return str_contains($sql,'ticket_types')?(object)['session_id'=>600,'capacity_units'=>($args[0]===803?4:1)]:(object)['id'=>600,'event_id'=>700];}
        function update(...$args){return 1;}
    }
    $wpdb=new FakeDB();
}
namespace Tickera {
    class TC_Cart_Form {function __construct($id){}function get_owner_info_fields(){return [];}}
    class TC_Orders {static function get_tickets_ids($order,$status='',$sort='ASC'){return array_keys(array_filter($GLOBALS['tickets'],fn($p)=>$p['post_parent']===$order));}}
    class TC_Order {
        public $details;
        function __construct($id){$this->details=(object)['ID'=>$id,'post_status'=>wc_get_order($id)->get_status(),'tc_order_date'=>1700000000,'tc_cart_info'=>[]];}
    }
    class TC_Ticket_Instance {
        public $details,$id;
        function __construct($id){$this->id=$id;$p=$GLOBALS['tickets'][$id];$m=$GLOBALS['meta'][$id];$this->details=(object)array_merge(['ID'=>$id,'ticket_type_id'=>801,'first_name'=>'Synthetic','last_name'=>'Attendee','address'=>'','city'=>'','state'=>'','country'=>''],$p,$m);}
        function get_ticket_checkins(){return get_post_meta($this->id,'tc_checkins',true);}
        static function sort_attendance_records(&$a){}
    }
    class TC_Ticket {
        public $details;
        function __construct($id){$this->details=(object)['ID'=>$id,'post_title'=>'Synthetic ticket'];}
        function get_ticket_event($id){return 700;}static function is_checkin_available(...$args){return true;}
    }
}
namespace {
    $fixture=json_decode(file_get_contents(__DIR__.'/native-excerpts.json'),true,512,JSON_THROW_ON_ERROR);
    eval($fixture['functions']['tickera_apply_filters']);
    eval('class NativeBridge { public static function get_woo_order_types(){return ["tc_orders","shop_order","shop_order_placehold"];} '.implode("\n",$fixture['bridge']).'}');
    eval('namespace Tickera; class TC_Checkin_API {public $ticket_code="";function get_api_key_id(){return 1;}function get_api_event(){return [700];}static function maybe_format_event_ids_array($ids){return $ids;}static function get_number_of_allowed_checkins_for_ticket_instance(...$args){return 1;}static function get_next_attendance_direction($id){return "in";}'. $fixture['api']['ticket_checkin'].'}');
    require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-live-sales.php';
    $bridge=new NativeBridge();add_filter('tc_order_is_paid',[$bridge,'tc_modify_order_is_paid']);
    function make_order($id,$qty=1,$variation=801){
        $o=new WC_Order($id);$GLOBALS['orders'][$id]=$o;
        $item=new WC_Order_Item_Product($id+1);$item->qty=$qty;$item->variation=$variation;
        $GLOBALS['items'][$item->id]=$item;$o->items[$item->id]=$item;
        $GLOBALS['meta'][701]['_tc_is_ticket']='yes';$GLOBALS['meta'][701]['_event_name']=700;
        $GLOBALS['timeline'][]=['T0','synthetic_order_created',$id];return [$o,$item];
    }
    function create_native($bridge,$o,$item){
        $payload=['owner_data_first_name_post_meta'=>[$item->variation=>array_fill(0,$item->qty,'Synthetic')],'owner_data_last_name_post_meta'=>[$item->variation=>array_fill(0,$item->qty,'Attendee')]];
        $bridge->create_order_ticket_instances($item->id,null,$o->id,$payload,true,$o);
    }
    function verify($ticket){$api=new \Tickera\TC_Checkin_API();$api->ticket_code=get_post_meta($ticket,'ticket_code',true);return $api->ticket_checkin(false);}
    $cases=[];
    foreach(['pending','failed','cancelled'] as $n=>$status){
        [$o,$item]=make_order(900000+$n*10);create_native($bridge,$o,$item);
        $o->status=$status;$tickets=\Tickera\TC_Orders::get_tickets_ids($o->id);
        expect(count($tickets)===1,'Native pre-payment instance expected');
        expect(get_post_meta($tickets[0],'ticket_code',true)!=='','Native ticket code expected');
        $before=$GLOBALS['meta'];expect(verify($tickets[0])===11,$status.' rejected by exact native verifier');
        expect($before===$GLOBALS['meta'],'Unpaid verifier must not write history');
        $GLOBALS['timeline'][]=['T2/T3','gateway_not_called_or_synthetic_failed',$o->id];
        $cases[]=['scenario'=>$status,'instances'=>1,'code_bearing'=>1,'verifier_accepted'=>0,'history_writes'=>0];
    }
    [$o,$item]=make_order(901000);create_native($bridge,$o,$item);
    $tickets=\Tickera\TC_Orders::get_tickets_ids($o->id);$o->status='processing';$o->paid=new \DateTimeImmutable('2000-01-01T00:00:00Z');
    MDG_Live_Sales::payment_complete($o->id);
    expect(verify($tickets[0])['status']===true,'Paid processing may check in in isolated fixture');
    foreach(['processing','completed','processing','completed'] as $status){$o->status=$status;MDG_Live_Sales::payment_complete($o->id);expect(count(\Tickera\TC_Orders::get_tickets_ids($o->id))===1,'Repeated paid callback/status must not create duplicate');}
    expect(tickera_apply_filters('tickera_order_is_paid',false,$o->id)===true,'Existing paid/completed ticket remains valid');
    $cases[]=['scenario'=>'synthetic_paid_processing_completed_callback_retries','instances'=>1,'verifier_accepted'=>1,'duplicate_instances'=>0];
    [$family,$child]=make_order(902000,2,801);$adult=new WC_Order_Item_Product(902002);$adult->qty=2;$adult->variation=802;$GLOBALS['items'][$adult->id]=$adult;$family->items[$adult->id]=$adult;
    create_native($bridge,$family,$child);create_native($bridge,$family,$adult);
    $capacity=new \ReflectionMethod('MDG_Live_Sales','order_capacity_groups');$capacity->setAccessible(true);
    expect($capacity->invoke(null,$family)===[600=>4],'Family components consume four actual MDG units');
    expect(count(\Tickera\TC_Orders::get_tickets_ids($family->id))===4,'Family two child plus two adult produce four instance codes');
    foreach(\Tickera\TC_Orders::get_tickets_ids($family->id) as $tid)expect(verify($tid)===11,'Unpaid family verifier must reject');
    [$packed,$packedItem]=make_order(903000,1,803);expect($capacity->invoke(null,$packed)===[600=>4],'Canonical family_2_2 unit4 mapping preserved');
    $cases[]=['scenario'=>'family_2_2','capacity_units'=>4,'component_instances'=>4,'unpaid_verifier_accepted'=>0];
    echo json_encode(['checks'=>$GLOBALS['checks'],'cases'=>$cases,'timeline'=>$GLOBALS['timeline'],'successful_paytr_reproduction'=>'successful-payment reproduction unavailable without real charge','scope'=>'Exact native methods with synthetic WP/Woo storage; no live PayTR network, no customer order or real check-in'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}
