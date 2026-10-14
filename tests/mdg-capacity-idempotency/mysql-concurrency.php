<?php
/**
 * REAL MySQL/InnoDB concurrency integration test for PR258.
 * Runs ONLY in an isolated GitHub Actions MySQL service, never a site database.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
function absint($x):int{return abs((int)$x);}
function sanitize_key($v):string{return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v));}
class WP_Error {public function __construct($code,$message=''){}}
class MDG_DB {
    public static function table($t):string{return 'wp_mdg_'.$t;}
    public static function now():string{return gmdate('Y-m-d H:i:s');}
}
final class MysqlWpdb {
    public string $prefix='wp_';
    public PDO $pdo;
    public string $barrier='';
    public string $participant='';
    public string $match='';
    public function __construct() {
        $this->pdo=new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=mdg_test;charset=utf8mb4',
            'root','test-only-root',[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES=>false
            ]
        );
    }
    public function prepare($fmt,...$values):string {
        $translated=array_map(function($v){
            return is_string($v)?$this->pdo->quote($v):(int)$v;
        },$values);
        return vsprintf($fmt,$translated);
    }
    public function query($sql) {
        try {return $this->pdo->exec($sql);}catch(Throwable $e) {return false;}
    }
    public function get_results($sql):array {
        $rows=$this->pdo->query($sql)->fetchAll(PDO::FETCH_OBJ);
        $this->sync_read($sql);
        return $rows;
    }
    public function get_row($sql) {
        $res=$this->pdo->query($sql)->fetch(PDO::FETCH_OBJ);
        $this->sync_read($sql);
        return $res?:null;
    }
    private function sync_read($sql):void {
        // Synchronize BOTH stale pre-transaction reads, ensuring the race
        // actually happens (it is not just sequential double execution).
        if($this->barrier==='' || $this->match===''||strpos($sql,$this->match)===false)return;
        file_put_contents($this->barrier.'.'.$this->participant,'read');
        $other=$this->participant==='pay'?'release':'pay';
        $deadline=microtime(true)+8.0;
        while(!file_exists($this->barrier.'.'.$other)) {
            if(microtime(true)>$deadline)throw new RuntimeException('Race reader barrier timed out');
            usleep(10000);
        }
    }
}
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-capacity.php';
function eq($yes,$message):void {
    if(!$yes){fwrite(STDERR,"FAIL $message\n");exit(1);}
    echo "PASS $message\n";
}
function ensure_schema(MysqlWpdb $db):void {
    $db->pdo->exec("DROP TABLE IF EXISTS wp_mdg_holds");
    $db->pdo->exec("DROP TABLE IF EXISTS wp_mdg_sessions");
    $db->pdo->exec("CREATE TABLE wp_mdg_sessions (
      id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      held_units INT NOT NULL DEFAULT 0,
      sold_units INT NOT NULL DEFAULT 0,
      updated_at DATETIME NULL
    ) ENGINE=InnoDB");
    $db->pdo->exec("CREATE TABLE wp_mdg_holds (
      id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      order_id BIGINT UNSIGNED NOT NULL,
      session_id BIGINT UNSIGNED NOT NULL,
      units INT NOT NULL,
      status VARCHAR(30) NOT NULL,
      updated_at DATETIME NULL,
      INDEX idx_order_status (order_id,status)
    ) ENGINE=InnoDB");
}
function seed(MysqlWpdb $db,int $id,int $order,int $session,int $units):void {
    $db->pdo->exec("INSERT INTO wp_mdg_sessions (id,held_units,sold_units) VALUES ($session,$units,0)");
    $db->pdo->exec("INSERT INTO wp_mdg_holds (id,order_id,session_id,units,status)
        VALUES ($id,$order,$session,$units,'held')");
}
function state(MysqlWpdb $db,int $id,int $session):array {
    $held=$db->pdo->query("SELECT status FROM wp_mdg_holds WHERE id=$id")->fetchColumn();
    $s=$db->pdo->query("SELECT held_units,sold_units FROM wp_mdg_sessions WHERE id=$session")->fetch(PDO::FETCH_ASSOC);
    return [$held,(int)$s['held_units'],(int)$s['sold_units']];
}
function fork_pair(string $left,string $right,int $oid,int $hid,string $match):void {
    $barrier=sys_get_temp_dir().'/mdg-cas-'.bin2hex(random_bytes(8));
    $child=pcntl_fork();
    if($child===-1)throw new RuntimeException('Cannot fork for real race');
    if($child===0) {
        global $wpdb;
        $wpdb=new MysqlWpdb();
        $wpdb->barrier=$barrier;$wpdb->participant='release';$wpdb->match=$match;
        // A duplicate settlement acts as the other contender when needed.
        $ok=$right==='release'?MDG_Capacity::release_hold($hid):MDG_Capacity::commit_order($oid);
        exit($ok||$right==='release'?0:2);
    }
    global $wpdb;
    $wpdb=new MysqlWpdb();
    $wpdb->barrier=$barrier;$wpdb->participant='pay';$wpdb->match=$match;
    $parentOk=$left==='pay'?MDG_Capacity::commit_order($oid):MDG_Capacity::release_hold($hid);
    pcntl_waitpid($child,$status);
    @unlink($barrier.'.pay');@unlink($barrier.'.release');
    eq(pcntl_wifexited($status)&&pcntl_wexitstatus($status)===0,'concurrent worker exited');
    eq($parentOk===true,'first worker returns safely');
}
$wpdb=new MysqlWpdb();
ensure_schema($wpdb);
seed($wpdb,1,44,150,4);
fork_pair('pay','pay',44,1,'FROM wp_mdg_holds WHERE order_id = 44');
$wpdb=new MysqlWpdb();
eq(state($wpdb,1,150)===['sold',0,4],
  'two authenticated payment attempts reading same held row settle EXACTLY four units');
seed($wpdb,2,45,151,4);
// With a mismatching pre-read query, force both participants to reach the
// barrier on their own query forms via broad table-name match.
fork_pair('pay','release',45,2,'FROM wp_mdg_holds');
$wpdb=new MysqlWpdb();
$s=state($wpdb,2,151);
eq($s===['sold',0,4] || $s===['released',0,0],
   'release/payment race chooses exactly one terminal state, never double counts');
echo "All REAL MySQL concurrency assertions passed. Test-only database mdg_test.\n";
