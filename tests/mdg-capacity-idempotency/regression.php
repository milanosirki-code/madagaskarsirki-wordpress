<?php
/**
 * Offline regression for atomic MDG hold claim under duplicate payment callbacks
 * and cancellation/expiry races. FakeWpdb models one transactional connection
 * and a concurrent winner by delivering stale SELECT rows.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
function absint($v):int{return abs((int)$v);}
function sanitize_key($s):string{return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$s));}
class WP_Error {
    public function __construct($code,$message=''){}
}
function is_wp_error($v):bool{return $v instanceof WP_Error;}
class MDG_DB {
    static function table($s):string{return 'wp_mdg_'.$s;}
    static function now():string{return '2026-10-14 10:00:00';}
}
class FakeWpdb {
    public string $prefix='wp_';
    public array $holds=[];
    public array $sessions=[];
    public bool $fail_session_update=false;
    public array $forced_stale_rows=[];
    private ?array $snapshot=null;
    public int $session_updates=0;
    public int $claims=0;
    public int $rollbacks=0;
    public function prepare($fmt,...$args):string {
        return vsprintf($fmt,array_map(static function($a){
            return is_string($a)?"'".str_replace("'","''",$a)."'":$a;
        },$args));
    }
    public function get_results($sql):array {
        if(!preg_match("/FROM wp_mdg_holds WHERE order_id = (\d+) AND status = 'held'/",$sql,$m))
            throw new RuntimeException('Unexpected get_results: '.$sql);
        if($this->forced_stale_rows) {
            $rows=$this->forced_stale_rows;
            $this->forced_stale_rows=[];
            return array_map(static fn($r)=>(object)$r,$rows);
        }
        return array_map(static fn($r)=>(object)$r,array_values(array_filter(
            $this->holds,static fn($r)=>(int)$r['order_id']===(int)$m[1]&&$r['status']==='held')));
    }
    public function get_row($sql) {
        if(!preg_match("/FROM wp_mdg_holds WHERE id = (\d+) AND status = 'held'/",$sql,$m))
            throw new RuntimeException('Unexpected get_row: '.$sql);
        $r=$this->holds[(int)$m[1]]??null;
        return $r&&$r['status']==='held'?(object)$r:null;
    }
    public function query($sql) {
        $sql=trim($sql);
        if($sql==='START TRANSACTION') {
            if($this->snapshot!==null)throw new RuntimeException('Unexpected nested transaction');
            $this->snapshot=[$this->holds,$this->sessions];
            return 0;
        }
        if($sql==='ROLLBACK') {
            if($this->snapshot!==null) {
                [$this->holds,$this->sessions]=$this->snapshot;
                $this->snapshot=null;
            }
            ++$this->rollbacks;
            return 0;
        }
        if($sql==='COMMIT') {
            if($this->snapshot===null)throw new RuntimeException('COMMIT without transaction');
            $this->snapshot=null;return 0;
        }
        if(strpos($sql,'UPDATE wp_mdg_holds')===0) {
            $sold=strpos($sql,"status = 'sold'")!==false;
            if($sold) {
                if(!preg_match("/WHERE id = (\d+) AND order_id = (\d+) AND session_id = (\d+)\s+AND units = (\d+) AND status = 'held'/s",$sql,$m))
                    throw new RuntimeException('Missing atomic sold guard: '.$sql);
                [$id,$order,$session,$units]=array_map('intval',array_slice($m,1));
            } else {
                if(!preg_match("/SET status = '([a-z]+)', updated_at = '[^']+'\s+WHERE id = (\d+) AND session_id = (\d+) AND units = (\d+) AND status = 'held'/s",$sql,$m))
                    throw new RuntimeException('Missing atomic release guard: '.$sql);
                $state=$m[1];[$id,$session,$units]=array_map('intval',array_slice($m,2));
            }
            $r=$this->holds[$id]??null;
            if(!$r||$r['status']!=='held'||(int)$r['session_id']!==$session
                ||(int)$r['units']!==$units||($sold&&(int)$r['order_id']!==$order))return 0;
            $this->holds[$id]['status']=$sold?'sold':$state;
            ++$this->claims;
            return 1;
        }
        if(strpos($sql,'UPDATE wp_mdg_sessions')===0) {
            if(!preg_match('/SET held_units = held_units - (\d+)/',$sql,$a)
                || !preg_match('/WHERE id = (\d+) AND held_units >= (\d+)/',$sql,$b)) {
                throw new RuntimeException('Unsafe session mutation: '.$sql);
            }
            $units=(int)$a[1];$session=(int)$b[1];$needed=(int)$b[2];
            if($this->fail_session_update)return false;
            if(!isset($this->sessions[$session])||$this->sessions[$session]['held_units']<$needed)
                return 0;
            $this->sessions[$session]['held_units']-=$units;
            if(strpos($sql,'sold_units = sold_units + ')!==false) {
                if(!preg_match('/sold_units = sold_units \+ (\d+)/',$sql,$c)||(int)$c[1]!==$units)
                    throw new RuntimeException('Sold mutation must equal held release');
                $this->sessions[$session]['sold_units']+=$units;
            }
            ++$this->session_updates;
            return 1;
        }
        throw new RuntimeException('Unrecognized SQL: '.$sql);
    }
}
function test(bool $cond,string $label):void {
    global $passed;
    if(!$cond){fwrite(STDERR,"FAIL: ".$label."\n");exit(1);}
    ++$passed;
    echo "PASS: ".$label."\n";
}
require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-capacity.php';
$passed=0;
$wpdb=new FakeWpdb();
$wpdb->sessions=[
    150=>['held_units'=>4,'sold_units'=>0],
    151=>['held_units'=>2,'sold_units'=>0],
];
$wpdb->holds=[
    1=>['id'=>1,'order_id'=>44,'session_id'=>150,'units'=>4,'status'=>'held'],
    2=>['id'=>2,'order_id'=>45,'session_id'=>151,'units'=>2,'status'=>'held'],
];
test(MDG_Capacity::commit_order(44)===true,'first paid order commits');
test($wpdb->sessions[150]['held_units']===0
    && $wpdb->sessions[150]['sold_units']===4
    && $wpdb->holds[1]['status']==='sold','first 2+2 sells exactly four units');
test(MDG_Capacity::commit_order(44)===true
    && $wpdb->sessions[150]['sold_units']===4,'identical callback does not double-sell');
$wpdb->forced_stale_rows=[['id'=>1,'order_id'=>44,'session_id'=>150,'units'=>4,'status'=>'held']];
test(MDG_Capacity::commit_order(44)===true
    && $wpdb->sessions[150]['sold_units']===4,'stale parallel read loses CAS race');
test(MDG_Capacity::release_hold(1)===false
    && $wpdb->sessions[150]['sold_units']===4,'cancellation cannot undo already settled sale');
test(MDG_Capacity::commit_order(45)===true
    && $wpdb->sessions[151]['sold_units']===2,'other session remains independent');
$wpdb->sessions[150]['held_units']=3;
$wpdb->holds[3]=['id'=>3,'order_id'=>46,'session_id'=>150,'units'=>3,'status'=>'held'];
test(MDG_Capacity::release_hold(3,'expired')===true
    && $wpdb->sessions[150]['held_units']===0,'expiration decrements held once');
test(MDG_Capacity::release_hold(3,'expired')===false
    && $wpdb->sessions[150]['held_units']===0,'repeated expiry is idempotent');
$wpdb->forced_stale_rows=[['id'=>3,'order_id'=>46,'session_id'=>150,'units'=>3,'status'=>'held']];
test(MDG_Capacity::commit_order(46)===true
    && $wpdb->sessions[150]['sold_units']===4,'late paid callback cannot settle expired capacity (manual reconciliation required)');
$wpdb->sessions[150]['held_units']=2;
$wpdb->holds[4]=['id'=>4,'order_id'=>47,'session_id'=>150,'units'=>2,'status'=>'held'];
$wpdb->fail_session_update=true;
test(MDG_Capacity::commit_order(47)===false,'session DB failure is surfaced');
test($wpdb->holds[4]['status']==='held'
    && $wpdb->sessions[150]['held_units']===2,'failed settlement rolls back atomic hold claim');
test(MDG_Capacity::release_hold(4)===false
    && $wpdb->holds[4]['status']==='held','failed release also rolls back claim');
$wpdb->fail_session_update=false;
test(MDG_Capacity::release_hold(4)===true
    && $wpdb->sessions[150]['held_units']===0,'release succeeds after DB recovery');
test(MDG_Capacity::release_hold(4,'sold')===false,'release cannot fabricate sold status');
test(MDG_Capacity::commit_order(0)===false
    && MDG_Capacity::commit_order(999)===true,'zero/unknown orders do not alter capacity');
test($wpdb->sessions[150]['sold_units']===4
    && $wpdb->sessions[151]['sold_units']===2,'all unit accounting remains conserved');

echo "ALL $passed offline MDG hold concurrency tests passed. No real Woo/MDG data was touched.\n";
