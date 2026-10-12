<?php
/** Standalone MDG concurrent payment callback contract, simulated DB. */
define('ABSPATH', __DIR__);
function absint($x){ return abs((int)$x); }
class MDG_DB {
    public static function table($x){return 'mdg_'.$x;}
    public static function now(){return '2026-10-12 20:00:00';}
}
class FakeDB {
    public $state='held', $held=2, $sold=0, $claims=0, $transaction=false;
    public function prepare($sql,...$args){return array('sql'=>$sql,'args'=>$args);}
    public function get_results($q) { return array((object)array('id'=>8,'order_id'=>10,'session_id'=>3,'units'=>2)); }
    public function query($q) {
        if(is_string($q)) {if($q==='START TRANSACTION'){$this->transaction=true;return 1;}if($q==='COMMIT'){$this->transaction=false;return 1;}if($q==='ROLLBACK'){$this->transaction=false;return 1;}return false;}
        $sql=$q['sql'];
        if(strpos($sql,"UPDATE mdg_holds")!==false) {
            if($this->state!=='held')return 0;
            $this->state='sold';$this->claims++;return 1;
        }
        if(strpos($sql,"UPDATE mdg_sessions")!==false){
            if($this->held<2)return 0;
            $this->held-=2;$this->sold+=2;return 1;
        }
        return false;
    }
}
$wpdb=new FakeDB();
require dirname(__DIR__,3).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-capacity.php';
if(!MDG_Capacity::commit_order(10))exit("First callback failed\n");
if(!MDG_Capacity::commit_order(10))exit("Retry callback failed\n");
if($wpdb->claims!==1 || $wpdb->sold!==2 || $wpdb->held!==0)exit("Duplicate callback changed capacity\n");
echo "PASS MDG payment capacity idempotency contract\n";
