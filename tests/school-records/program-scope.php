<?php
define('ABSPATH',__DIR__.'/');
class WP_Error {}
function is_wp_error($v){return $v instanceof WP_Error;}
function mad_okul_place_title($v){return trim($v);}
class Mad_Okul_Operations {
    public static $context;
    public static function mmc_program_context($id){return self::$context;}
}
class MMC_Region_Service {
    public static $targets=[];
    public static function get_program_targets($id){return self::$targets;}
}
class ScopeDatabase {
    public function prepare($sql,$args){
        foreach($args as $arg) $sql=preg_replace('/%s/',"'".str_replace("'","''",$arg)."'",$sql,1);
        return $sql;
    }
}
$wpdb=new ScopeDatabase();
require __DIR__.'/../../wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-records.php';
$method=new ReflectionMethod('Mad_Okul_Records','student_scope_where');
function verify($condition,$message){if(!$condition)throw new RuntimeException($message);}
Mad_Okul_Operations::$context=(object)['program'=>(object)['province_name'=>'Manisa','district_name'=>'Şehzadeler']];
MMC_Region_Service::$targets=['Şehzadeler',' Yunusemre ','Yunusemre',''];
$history=$method->invoke(null,7,'s');
$export=$method->invoke(null,7);
verify($history===" WHERE s.il='Manisa' AND s.ilce IN ('Şehzadeler','Yunusemre')",'History must include both districts once');
verify($export===" WHERE il='Manisa' AND ilce IN ('Şehzadeler','Yunusemre')",'Export must have the same geographic scope');
MMC_Region_Service::$targets=[];
verify($method->invoke(null,7)===" WHERE il='Manisa' AND ilce IN ('Şehzadeler')",'Single-district fallback');
verify($method->invoke(null,0)==='','Global view remains global');
Mad_Okul_Operations::$context=new WP_Error();
verify(is_wp_error($method->invoke(null,7)),'Invalid program must not become global');
Mad_Okul_Operations::$context=(object)['program'=>(object)['province_name'=>'Manisa','district_name'=>'']];
verify(is_wp_error($method->invoke(null,7)),'Missing geography must fail closed');
echo "PASS: multi-district history/export, normalization, single district, global view, invalid scope\n";
