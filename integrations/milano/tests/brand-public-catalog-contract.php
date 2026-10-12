<?php
/**
 * Native cities/direct-event ownership regression fixtures.
 * Run: php integrations/milano/tests/brand-public-catalog-contract.php <scenario>
 * No WordPress installation, payment, or live database is accessed.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = isset($argv[1]) ? $argv[1] : 'madagaskar';
if ( 'disabled' !== $scenario ) {
    define( 'DUNYA_BRAND_GUARD_ENABLED', true );
}
function must( $cond, $message ) {
    if ( ! $cond ) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$filterMap = array();
function add_filter( $hook, $callable, $priority = 10, $args = 1 ) {
    global $filterMap;
    $filterMap[$hook][] = $callable;
}
function add_action( $name, $fn, $priority = 10, $args = 1 ) {}
function apply_filters( $hook, $value, ...$args ) {
    global $filterMap;
    foreach( $filterMap[$hook] ?? array() as $callback ) {
        $value = call_user_func_array($callback, array_merge(array($value), $args));
    }
    return $value;
}
function absint($n){return abs((int)$n);}
function home_url($path){global $host;return 'https://'.$host.'/';}
function wp_parse_url($url,$component){return parse_url($url,$component);}
function sanitize_title($v){return strtolower((string)$v);}
function current_time($format,$gmt=false){return '2026-10-12 17:00:00';}
class MDG_DB { public static function table($type){return 'wp_mdg_'.$type;} }
class MDG_Status { const ONSALE = 'onsale'; }
class MMC_Program_Service {
    public static function get_program($id) {
        return $id === 20 ? (object) array('brand'=>'Milano Sirki')
            : ($id === 30 ? (object) array('brand'=>'Madagaskar Sirki') : null);
    }
    public static function normalized_program_brand($name){return $name;}
}
class MMC_MDG_Bridge_Service {
    public static function table(){return 'wp_mmc_mdg_event_bridge';}
    public static function program_for_mdg_event($id){
        must(false,'unsafe legacy mapping called for brand gate');
    }
}
$bridgeLinks = array(22=>array(20),33=>array(30));
class StubDB {
    public $queries=array();
    public function prepare($sql,...$args){
        $this->queries[]=$sql;
        if (false !== strpos($sql,'SELECT program_id')) {
            must(strpos($sql,'is_active=1 LIMIT 2')!==false,'bridge must require one active owner');
            must(count($args)===1,'event lookup requires one identifier');
            return 'BRIDGE:' . $args[0];
        }
        // Table existence checks call prepare("SHOW TABLES LIKE %s", $table).
        // The mock must expand the table argument like WPDB would.
        if ( false !== strpos( $sql, 'SHOW TABLES LIKE %s' ) ) {
            return str_replace( '%s', (string) $args[0], $sql );
        }
        return $sql;
    }
    public function get_col($sql) {
        global $bridgeLinks;
        return 0===strpos($sql,'BRIDGE:') ? ($bridgeLinks[(int)substr($sql,7)]??array()) : array();
    }
    public function get_var($sql) {
        return strpos($sql,'wp_mdg_sessions')!==false?'wp_mdg_sessions':'wp_mdg_events';
    }
    public function get_results($sql){
        $this->queries[]=$sql;
        return array(
            (object)array('id'=>22, 'province_code'=>'06', 'province_name'=>'Ankara', 'next_session_utc'=>'2026-10-15 10:00:00'),
            (object)array('id'=>33, 'province_code'=>'06', 'province_name'=>'Ankara', 'next_session_utc'=>'2026-10-16 10:00:00'),
            (object)array('id'=>99, 'province_code'=>'35', 'province_name'=>'İzmir', 'next_session_utc'=>'2026-10-17 10:00:00')
        );
    }
}
$wpdb=new StubDB();
$host = $scenario==='milano'?'milanosirki.com':'madagaskarsirki.com';
require dirname(__DIR__).'/milano-brand-checkout-guard/milano-brand-checkout-guard.php';
require dirname(__DIR__,3).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-public-cities.php';
$expectMilano = $scenario==='milano';
$disabled = $scenario==='disabled';
must(apply_filters('dunya_brand_event_visible_on_site',false,33,'cities') === false, 'prior denial overridden');
if ($disabled) {
    must(apply_filters('dunya_brand_event_visible_on_site',true,22,'cities') === true, 'disabled plugin must preserve previous catalogue');
    $cities=MDG_Public_Cities::active_cities();
    must(count($cities)===2, 'disabled cities grouping changed');
    must($cities[0]['event_count']===2, 'disabled city grouping changed');
    must((int)$cities[0]['event']->id===22, 'disabled nearest event wrong');
} else {
    $owned=$expectMilano?22:33;
    $other=$expectMilano?33:22;
    must(apply_filters('dunya_brand_event_visible_on_site',true,$owned,'cities')===true, 'own brand not visible');
    must(apply_filters('dunya_brand_event_visible_on_site',true,$other,'event_page')===false, 'wrong brand direct permalink allowed');
    must(apply_filters('dunya_brand_event_visible_on_site',true,99,'event_page')===false, 'unknown brand deep link allowed');
    $cities=MDG_Public_Cities::active_cities();
    must(count($cities)===1, 'wrong-brand or unmapped city exposed');
    must((int)$cities[0]['event']->id===$owned, 'wrong brand representative card');
    must($cities[0]['event_count']===1, 'city event count leaked a hidden brand');
    $bridgeLinks[$owned]=array($expectMilano?20:30, $expectMilano?30:20);
    must(apply_filters('dunya_brand_event_visible_on_site',true,$owned,'cities')===false,
        'two active bridge rows allowed event visibility');
    $bridgeLinks[$owned]=array();
    must(apply_filters('dunya_brand_event_visible_on_site',true,$owned,'cities')===false,
        'missing explicit active bridge fell back to legacy mapping');
}
$sourceCities = file_get_contents(dirname(__DIR__,3).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-public-cities.php');
$sourceEvent = file_get_contents(dirname(__DIR__,3).'/wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-public-event.php');
must(strpos($sourceCities, "'dunya_brand_event_visible_on_site'")!==false, 'cities do not use ownership filter');
must(strpos($sourceEvent, "'dunya_brand_event_visible_on_site'")!==false, 'direct event page missing ownership filter');
echo "PASS {$scenario}\n";
