<?php
// Isolated metadata analysis only: no database, snippet toggles or domain writes.
if (!defined('ABSPATH')) define('ABSPATH',__DIR__);
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v){return strip_tags((string)$v);} }
if (!function_exists('remove_accents')) { function remove_accents($v){return $v;} }
$class=defined('MMC_INVENTORY_TEST_CLASS')?MMC_INVENTORY_TEST_CLASS:'MMC_Snippet_Inventory_Service';
if(!class_exists($class,false))require dirname(__DIR__,2).'/wp-content/plugins/madagaskar-management-center/includes/class-mmc-snippet-inventory-service.php';
$analyze=new ReflectionMethod($class,'analyze_row');
$conflicts=new ReflectionMethod($class,'apply_conflicts');
$functions=new ReflectionMethod($class,'global_functions');
$checks=0;
$check=function($label,$actual,$expected)use(&$checks){if($actual!==$expected)throw new RuntimeException($label.': '.json_encode(array($actual,$expected)));$checks++;};
$row=function($id,$active,$code)use($analyze){return $analyze->invoke(null,(object)array('id'=>$id,'name'=>'Production source '.$id,'active'=>$active,'code'=>$code));};
foreach(array(0,1,-1,'0','1','-1')as$v){$check('Only1 active: '.(string)$v,$row(1,$v,'')['active'],1===(int)$v);}
$check('Separate class methods are not global',$functions->invoke(null,'class One { public static function render() {} private function plan() {} } class Two { function render() {} }'),array());
$check('Comments and strings are not declarations',$functions->invoke(null,'/* function pretend() {} */ $s="function fake() {}";'),array());
$check('Named global functions retained',$functions->invoke(null,'function real_a() {} if (true) { function real_b() {} }'),array('real_a','real_b'));
$check('Reference return supported',$functions->invoke(null,'function &reference_fn() {}'),array('reference_fn'));
$check('Closure excluded',$functions->invoke(null,'$f=function() {};'),array());
$check('Trait and interface methods excluded',$functions->invoke(null,'trait T { function render() {} } interface I { public function plan(); }'),array());
$check('Class constant does not open class scope',$functions->invoke(null,'$name=One::class; function after_constant() {}'),array('after_constant'));
$check('Nested function remains global',$functions->invoke(null,'class One { function run() { function nested_global() {} } }'),array('nested_global'));
$items=array($row(61,1,'class DateTool { function render() {} function plan() {} }'),$row(124,1,'class Campaign { function render() {} function plan() {} }'),$row(125,1,'class Report { function render() {} }'));
$conflicts->invokeArgs(null,array(&$items));
foreach($items as$item){$check('Class-scoped owner '.$item['id'],$item['risk'],'ok');}
$items=array($row(1,1,'function shared_real() {}'),$row(2,1,'function shared_real() {}'));
$conflicts->invokeArgs(null,array(&$items));
$check('Real duplicate1 still warns',$items[0]['risk'],'warning');
$check('Real duplicate2 still warns',$items[1]['risk'],'warning');
$items=array($row(1,1,"add_shortcode('shared', 'one');"),$row(2,1,"add_shortcode('shared', 'two');"));
$conflicts->invokeArgs(null,array(&$items));
$check('Duplicate shortcode still warns',$items[0]['risk'],'warning');
$items=array($row(1,1,'function shared_real() {}'),$row(122,-1,'function shared_real() {}'));
$conflicts->invokeArgs(null,array(&$items));
$check('Trash does not conflict with active',$items[0]['risk'],'ok');
echo $checks." snippet inventory checks passed\n";
return $checks;
