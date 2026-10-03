<?php
define('ABSPATH', __DIR__);
$hooks = array();
function add_filter($hook, $callback, $priority=10, $args=1) { global $hooks; $hooks[] = array($hook,$callback,$priority,$args); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function wp_timezone() { return new DateTimeZone('Europe/Istanbul'); }
function get_post_meta($id, $key='', $single=false) { return $key === '' ? array() : ''; }
function wc_get_product($id) { return false; }
class MDG_DB { public static function table($name) { return 'wp_mdg_'.$name; } }
class FixtureDB {
    public $rows = array();
    public function prepare($sql, ...$ids) { if ($ids !== array(10,11)) { throw new RuntimeException('Order/item identity changed'); } return $sql; }
    public function get_results($sql) { if (strpos($sql, 'order_item_id=%d') === false || strpos($sql, 's.event_id=m.event_id') === false) { throw new RuntimeException('Unsafe mapping scope'); } return $this->rows; }
}
$wpdb = new FixtureDB();
class FixtureItem {
    private $variation;
    public function __construct($variation) { $this->variation=$variation; }
    public function get_name() { return 'Denizli 8 Ekim 2026 '.($this->variation===102?'19:30':'17:30'); }
    public function get_meta_data() { return array(); }
    public function get_product_id() { return 100; }
    public function get_variation_id() { return $this->variation; }
}
class FixtureOrder {
    public function get_item($id) { return $id===11 ? new FixtureItem(102) : null; }
    public function get_items($type) { return array(new FixtureItem(101),new FixtureItem(102)); }
}
function wc_get_order($id) { return $id===10 ? new FixtureOrder() : false; }
function check($name,$actual,$expected) { if ($actual !== $expected) { throw new RuntimeException($name.': '.json_encode(array($actual,$expected))); } echo "PASS: $name\n"; }
$repo = dirname(__DIR__,2);
require $repo.'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-ticket-session-datetime.php';
MDG_Ticket_Session_Datetime::hooks();
check('canonical registers exactly five filters',count($hooks),5);
check('pre-generate priority and args',array_slice($hooks[0],2),array(9,6));
foreach(array_slice($hooks,1) as $hook) { check('ticket-data priority and args '.$hook[0],array_slice($hook,2),array(20,4)); }
$data=array('order_id'=>10,'item_id'=>11,'variation_id'=>102,'product_id'=>100,'product_name'=>'Genel Giriş','event_time'=>'17:30','event_datetime'=>'8 Ekim 2026 17:30 - 18:30');
$wpdb->rows=array((object)array('id'=>2,'start_at'=>'2026-10-08 16:30:00','end_at'=>'2026-10-08 18:00:00'));
$out=MDG_Ticket_Session_Datetime::correct_ticket_data($data);
foreach(array('event_time','event_start_time','session_time','seans') as $field) { check('mapped purchased session '.$field,$out[$field],'19:30'); }
check('canonical duration retained',$out['event_end_time'],'21:00');
$wpdb->rows=array();
$out=MDG_Ticket_Session_Datetime::correct_ticket_data($data);
check('legacy exact order item beats event default',$out['event_time'],'19:30');
check('legacy one-hour fallback preserved',$out['event_end_time'],'20:30');
$variation=$data; $variation['item_id']=0;
$out=MDG_Ticket_Session_Datetime::correct_ticket_data($variation);
check('second variation beats first parent-matched order item',$out['event_time'],'19:30');
$wpdb->rows=array((object)array('id'=>1,'start_at'=>'2026-10-08 14:30:00','end_at'=>'2026-10-08 15:30:00'),(object)array('id'=>2,'start_at'=>'2026-10-08 16:30:00','end_at'=>'2026-10-08 17:30:00'));
check('ambiguous mapping preserves original ticket data',MDG_Ticket_Session_Datetime::correct_ticket_data($data),$data);
$wpdb->rows=array((object)array('id'=>2,'start_at'=>'2026-02-30 16:30:00','end_at'=>'2026-02-30 17:30:00'));
check('invalid UTC date preserves original ticket data',MDG_Ticket_Session_Datetime::correct_ticket_data($data),$data);
$roll=MDG_Ticket_Session_Datetime::format_utc_session('2026-10-03 22:30:00','2026-10-03 23:30:00');
check('Turkey date rollover',$roll['date_text'],'4 October 2026'); check('Turkey hour conversion',$roll['start'],'01:30');
check('invalid duration fails closed',MDG_Ticket_Session_Datetime::format_utc_session('2026-10-03 22:30:00','2026-10-03 21:30:00'),array());
class TC_Ticket_Designer_Template { public function __construct($id) {} public function get_id() { return 7; } }
class TC_Ticket_Designer_Fields { public static function resolve_ticket_data($id) { global $data; return $data; } }
class TC_Ticket_Designer_PDF_Generator { public static function generate($template,$data,$output,$filename) { return json_encode($data); } }
$wpdb->rows=array((object)array('id'=>2,'start_at'=>'2026-10-08 16:30:00','end_at'=>'2026-10-08 17:30:00'));
$pdf_data=json_decode(MDG_Ticket_Session_Datetime::pre_generate(null,44,0,'d_7'),true);
check('PDF generator receives purchased session',$pdf_data['event_time'],'19:30');
check('existing pre-generated response retained',MDG_Ticket_Session_Datetime::pre_generate('existing',44,0,'d_7'),'existing');
check('legacy global designer class fallback',MDG_Ticket_Session_Datetime::designer_class('TC_Ticket_Designer_Fields'),'TC_Ticket_Designer_Fields');
foreach (array('TC_Ticket_Designer_Template','TC_Ticket_Designer_PDF_Generator','TC_Ticket_Designer_Fields') as $cls) {
    class_alias($cls,'Tickera\\\\'.$cls);
    check('live namespaced designer preferred '.$cls,MDG_Ticket_Session_Datetime::designer_class($cls),'Tickera\\\\'.$cls);
}
$pdf_data=json_decode(MDG_Ticket_Session_Datetime::pre_generate(null,44,0,'d_7'),true);
check('namespaced PDF generator receives purchased session',$pdf_data['event_time'],'19:30');
$hooks=array();
require $repo.'/wp-content/plugins/madagaskar-ticket-session-datetime-fix/madagaskar-ticket-session-datetime-fix.php';
MDG_Ticket_Session_Datetime::hooks();
check('active standalone remains sole five-hook owner',count($hooks),5);
echo "ALL TICKET REGRESSIONS PASSED\n";
