<?php
/** Real SQL regression, only against the ephemeral CI operations_fixture database. */
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');
if(getenv('OPS_FIXTURE_DB')!=='operations_fixture'){throw new RuntimeException('Synthetic database guard');}
class WP_Error{public function __construct(public $code,public $message='') {}}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function current_time($v){return '2026-10-05 12:00:00';}
function wp_timezone(){return new DateTimeZone('Europe/Istanbul');}
function current_user_can($v){return true;}
function get_current_user_id(){return 501;}
function wp_json_encode($v){return json_encode($v);}
function get_user_by($field,$id){return $GLOBALS['wpdb']->get_row('SELECT ID FROM wp_users WHERE ID='.(int)$id);}
class FixtureDB{
    public string $prefix='wp_';public bool $cancel_race=false;public bool $manual_race=false;
    public mysqli $db;
    public function __construct(){$this->db=new mysqli('127.0.0.1','root','',getenv('OPS_FIXTURE_DB'),3306);$this->db->set_charset('utf8mb4');}
    public function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:"'".$this->db->real_escape_string((string)$v)."'";},$sql);}
    public function get_results($sql,$type=null){$r=$this->db->query($sql);$a=$r->fetch_all(MYSQLI_ASSOC);return $type===ARRAY_A?$a:array_map(fn($r)=>(object)$r,$a);}
    public function get_row($sql,$type=null){return $this->get_results($sql,$type)[0]??null;}
    public function get_var($sql){$r=$this->db->query($sql)->fetch_row();return $r[0]??null;}
    public function query($sql){
        if(str_starts_with($sql,'UPDATE wp_mmc_tasks SET')){
            if($this->cancel_race){$this->cancel_race=false;$this->db->query("UPDATE wp_mmc_programs SET status='cancelled' WHERE id=77");}
            if($this->manual_race){$this->manual_race=false;$this->db->query("UPDATE wp_mmc_tasks SET due_at='2026-10-07 15:00:00' WHERE id=1");}
        }
        $r=$this->db->query($sql);return $r===false?false:$this->db->affected_rows;
    }
    public function insert($table,$data){$keys=array_keys($data);$values=array_map(fn($v)=>$v===null?'NULL':"'".$this->db->real_escape_string((string)$v)."'",$data);return $this->query('INSERT INTO '.$table.' ('.implode(',',$keys).') VALUES ('.implode(',',$values).')');}
}
class MMC_Program_Service{
    public static function get_program($id){return $GLOBALS['wpdb']->get_row('SELECT * FROM wp_mmc_programs WHERE id='.(int)$id);}
    public static function add_log($pid,$action,$type,$id,$old,$new,$note){return $GLOBALS['wpdb']->insert('wp_mmc_logs',['program_id'=>$pid,'action'=>$action,'payload'=>json_encode($new)]);}
}
class MMC_Event_Service{
    public static function event_for_program($id){return $GLOBALS['wpdb']->get_row('SELECT * FROM wp_mmc_events WHERE program_id='.(int)$id.' ORDER BY id LIMIT 1');}
    public static function sessions($id){return $GLOBALS['wpdb']->get_results('SELECT * FROM wp_mmc_sessions WHERE event_id='.(int)$id);}
}
$db=new FixtureDB;$GLOBALS['wpdb']=$db;
foreach([
 'wp_users'=>'ID bigint PRIMARY KEY',
 'wp_mmc_programs'=>'id bigint PRIMARY KEY,status varchar(30),planned_date date,owner_user_id bigint',
 'wp_mmc_events'=>'id bigint PRIMARY KEY,program_id bigint,event_date date,status varchar(30),door_open_minutes int',
 'wp_mmc_sessions'=>'id bigint PRIMARY KEY,event_id bigint,session_time datetime,status varchar(30)',
 'wp_mmc_operation_plans'=>'id bigint PRIMARY KEY,program_id bigint,departure_at datetime,venue_entry_at datetime',
 'wp_mmc_tasks'=>'id bigint PRIMARY KEY,program_id bigint,module varchar(30),title varchar(255),status varchar(30),priority varchar(30),due_at datetime NULL,assigned_user_id bigint NULL,completed_at datetime NULL,metadata longtext NULL,notes text,updated_at datetime',
 'wp_mmc_logs'=>'id bigint PRIMARY KEY AUTO_INCREMENT,program_id bigint,action varchar(100),payload longtext'
]as$table=>$schema){$db->query('DROP TABLE IF EXISTS '.$table);$db->query('CREATE TABLE '.$table.' ('.$schema.') ENGINE=InnoDB');}
$db->insert('wp_users',['ID'=>501]);$db->insert('wp_users',['ID'=>502]);
$db->insert('wp_mmc_programs',['id'=>77,'status'=>'operations','planned_date'=>'2026-10-08','owner_user_id'=>501]);
$db->insert('wp_mmc_events',['id'=>17,'program_id'=>77,'event_date'=>'2026-10-08','status'=>'sales_open','door_open_minutes'=>30]);
$db->insert('wp_mmc_sessions',['id'=>88,'event_id'=>17,'session_time'=>'2026-10-08 18:00:00','status'=>'active']);
$db->insert('wp_mmc_operation_plans',['id'=>1,'program_id'=>77,'departure_at'=>'2026-10-08 09:00:00','venue_entry_at'=>null]);
require __DIR__.'/../../wp-content/plugins/madagaskar-management-center/includes/class-mmc-operations-service.php';
$assertions=0;function test($v,$message){$GLOBALS['assertions']++;if(!$v)throw new RuntimeException($message);}
function reset_task(){global$db;$db->query('DELETE FROM wp_mmc_tasks');$db->query('DELETE FROM wp_mmc_logs');$db->query("UPDATE wp_mmc_programs SET status='operations',owner_user_id=501");$db->insert('wp_mmc_tasks',['id'=>1,'program_id'=>77,'module'=>'operations','title'=>'synthetic planning','status'=>'open','priority'=>'high','due_at'=>null,'assigned_user_id'=>null,'completed_at'=>null,'metadata'=>json_encode(['source_key'=>'operations_v1.plan','system_generated'=>true]),'notes'=>'KEEP_NOTE','updated_at'=>'2026-10-01 10:00:00']);}
reset_task();$before=$db->get_var('SELECT SHA2(CONCAT_WS("|",metadata,notes,updated_at),256) FROM wp_mmc_tasks');$preview=MMC_Operations_Service::task_automation_preview(77);test($preview['would_update_due']===1 && $preview['would_update_owner']===1,'Real preview');test($before===$db->get_var('SELECT SHA2(CONCAT_WS("|",metadata,notes,updated_at),256) FROM wp_mmc_tasks'),'Preview write');
$r=MMC_Operations_Service::apply_task_automation(77);test($r['tasks_updated']===1,'Real SQL apply');$task=$db->get_row('SELECT * FROM wp_mmc_tasks');test($task->due_at==='2026-10-08 09:00:00' && (int)$task->assigned_user_id===501,'Real values');test($task->priority==='high' && $task->notes==='KEEP_NOTE' && $task->status==='open' && $task->completed_at===null,'Protected real columns');
$r=MMC_Operations_Service::apply_task_automation(77);test($r['tasks_updated']===0 && (int)$db->get_var('SELECT COUNT(*) FROM wp_mmc_logs')===1,'Real idempotency');
$db->query("UPDATE wp_mmc_operation_plans SET departure_at='2026-10-08 10:00:00'");$db->query('UPDATE wp_mmc_programs SET owner_user_id=502');$r=MMC_Operations_Service::apply_task_automation(77);test($r['due_updated']===1 && $r['owner_updated']===1,'Real managed update');
$db->query("UPDATE wp_mmc_tasks SET due_at='2026-10-07 10:00:00',assigned_user_id=501");$r=MMC_Operations_Service::apply_task_automation(77);test($r['tasks_updated']===0,'Real manual preservation');
reset_task();$db->manual_race=true;$r=MMC_Operations_Service::apply_task_automation(77);test($r['stale_skipped']===1 && $db->get_var('SELECT due_at FROM wp_mmc_tasks')==='2026-10-07 15:00:00','Real CAS race');
reset_task();$db->cancel_race=true;$r=MMC_Operations_Service::apply_task_automation(77);test($r['stale_skipped']===1 && $db->get_var('SELECT due_at FROM wp_mmc_tasks')===null,'Real cancellation scope guard');
test((int)$db->get_var('SELECT COUNT(*) FROM wp_mmc_logs')===0,'No stale-apply log');
echo "PASS {$assertions} real MySQL assertions; ephemeral fixture only.\n";
