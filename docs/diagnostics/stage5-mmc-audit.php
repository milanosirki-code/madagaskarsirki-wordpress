<?php
/**
 * Temporary admin-only Stage 4 source reader. Not a production module.
 * No checkout, payment, order, ticket, CRM or data-write operations.
 */
if (!defined('ABSPATH')) { exit; }
function mdg_stage4_source_read($input) {
    $slugs = array('madagaskar-management-center','madagaskar-bilet-yonetimi','madagaskar-bilet-yonetimi-v4','madagaskar-ai-abilities','madagaskar-checkout-customizations','madagaskar-kommo-automation','madagaskar-aile-paketi-22','madagaskar-legacy-redirects','madagaskar-okul-tanitim','madagaskar-population-data','madagaskar-fatura-takip','madagaskar-v5-finans-guncellemesi','madagaskar-v5-sabit-gider-turleri','madagaskar-menu-duzenleyici','madagaskar-yonetim-merkezi-v2-v3','madagaskar-yonetim-merkezi-v5','madagaskar-cekilis','dunya-fatura-takip');
    $slug = (string)($input['slug'] ?? '');
    if (!in_array($slug,$slugs,true)) { return new WP_Error('invalid_slug','Plugin not whitelisted.'); }
    $base = realpath(WP_PLUGIN_DIR.'/'.$slug);
    if (!$base || !is_dir($base)) { return new WP_Error('missing_plugin','Plugin unavailable.'); }
    if (($input['mode'] ?? '') === 'hashes') {
        $rows=array();
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
        foreach($it as $f) {
            if($f->isLink() || !$f->isFile() || !in_array(strtolower($f->getExtension()),array('php','js','css','md','txt','csv','json'),true)) { continue; }
            $raw=file_get_contents($f->getPathname());
            $rows[]=array('path'=>'wp-content/plugins/'.$slug.'/'.substr($f->getPathname(),strlen($base)+1),'sha256'=>hash('sha256',$raw),'git_blob_sha'=>sha1('blob '.strlen($raw).chr(0).$raw),'bytes'=>strlen($raw));
        }
        return array('slug'=>$slug,'files'=>$rows);
    }
    $path=(string)($input['path'] ?? '');
    if(strpos($path,'..')!==false || !preg_match('/^[a-zA-Z0-9_\/. -]+\.(php|js|css|md|txt)$/D',$path)) { return new WP_Error('invalid_path','Code or readme path required.'); }
    $full=realpath($base.'/'.$path);
    if(!$full || strpos($full,$base.DIRECTORY_SEPARATOR)!==0 || !is_file($full) || !is_readable($full)) { return new WP_Error('missing_source','Whitelisted source unavailable.'); }
    $raw=file_get_contents($full);
    $offset=max(0,(int)($input['offset'] ?? 0));
    $length=min(20000,max(1,(int)($input['length'] ?? 20000)));
    return array('path'=>'wp-content/plugins/'.$slug.'/'.$path,'sha256'=>hash('sha256',$raw),'bytes'=>strlen($raw),'offset'=>$offset,'content'=>substr($raw,$offset,$length));
}

function mdg_stage5_admin_pages() {
    static $cached=null; if($cached!==null)return $cached;
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    require_once ABSPATH.'wp-admin/includes/template.php';
    require_once ABSPATH.'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH.'wp-admin/includes/screen.php';
    if (function_exists('set_current_screen')) { set_current_screen('dashboard'); }
    global $menu,$submenu,$admin_page_hooks,$_registered_pages,$_parent_pages,$wp_filter;
    $menu=is_array($menu)?$menu:array(); $submenu=is_array($submenu)?$submenu:array();
    $admin_page_hooks=is_array($admin_page_hooks)?$admin_page_hooks:array();
    $_registered_pages=is_array($_registered_pages)?$_registered_pages:array();
    $_parent_pages=is_array($_parent_pages)?$_parent_pages:array();
    foreach(array('MMC_Admin','MMC_Venue_Admin','MMC_Event_Admin','MMC_Sales_Admin','MMC_Kommo_Admin','MMC_Marketing_Admin','MMC_Field_Admin','MMC_Operations_Admin','MMC_Finance_Admin','MMC_Report_Admin','MMC_Integrity_Admin','MMC_MDG_Publish_Admin','MMC_Snippet_Inventory_Admin','MMC_Navigation_Admin') as $class) {
        if(class_exists($class)) { new $class(); }
    }
    $before=array();
    $capture=function()use(&$before){ global $menu,$submenu; $before=array('menu'=>$menu,'submenu'=>$submenu); };
    add_action('admin_menu',$capture,999998);
    do_action('admin_menu');
    remove_action('admin_menu',$capture,999998);
    $entries=array();
    foreach(array($before,array('menu'=>$menu,'submenu'=>$submenu)) as $phase=>$data) {
        foreach((array)($data['menu']??array()) as $m) {
            if(isset($m[2])&&preg_match('/^(mmc-|mad-okul|mdg-|madagaskar-v4)/',(string)$m[2])) {$entries[(string)$m[2]]=array('name'=>wp_strip_all_tags($m[0]),'slug'=>(string)$m[2],'capability'=>$m[1],'parent'=>'','visible'=>$phase===1);}
        }
        foreach((array)($data['submenu']??array()) as $parent=>$rows){
            if(!preg_match('/^(mmc-|mad-okul|mdg-|madagaskar-v4)/',(string)$parent))continue;
            foreach($rows as $m){if(!isset($m[2])||strpos($m[2],'.php')!==false)continue; $slug=(string)$m[2];
                $entry=array('name'=>wp_strip_all_tags($m[0]),'slug'=>$slug,'capability'=>$m[1],'parent'=>$parent,'visible'=>$phase===1 && $parent==='mmc-dashboard');
                if(isset($entries[$slug])&&!empty($entries[$slug]['visible']))$entry['visible']=true;
                $entries[$slug]=$entry;
            }
        }
    }
    foreach($entries as $slug=>&$entry) {
        $hook=get_plugin_page_hook($slug,$entry['parent']);
        $entry['hook']=$hook; $entry['callbacks']=array();
        if($hook&&isset($wp_filter[$hook])) foreach($wp_filter[$hook]->callbacks as $priority=>$group) foreach($group as $row) {
            $cb=$row['function']; try {
                $ref=is_array($cb)?new ReflectionMethod($cb[0],$cb[1]):new ReflectionFunction($cb);
                $entry['callbacks'][]=array('callback'=>is_array($cb)?(is_object($cb[0])?get_class($cb[0]):$cb[0]).'::'.$cb[1]:(is_string($cb)?$cb:'closure'),'file'=>str_replace(ABSPATH,'',$ref->getFileName()),'line'=>$ref->getStartLine(),'priority'=>$priority,'callable'=>is_callable($cb));
            }catch(Throwable $e){$entry['callbacks'][]=array('callback'=>'unresolved','callable'=>false);}
        }
        $entry['permission']=current_user_can($entry['capability']);
    }
    unset($entry);
    $cached=array_values($entries); return $cached;
}
function mdg_stage5_probe($input) {
    if(($input['mode']??'')==='batch'){ $items=array(); foreach(array_slice((array)($input['slugs']??array()),0,12) as $slug)$items[]=mdg_stage5_probe(array('mode'=>'render','slug'=>$slug,'program_id'=>$input['program_id']??0)); return array('items'=>$items); }
    if(($input['mode']??'')==='ability-info') {
        $out=array();foreach((array)($input['names']??array()) as $name) {
            if(strpos($name,'madagaskar/')!==0)continue;
            $a=function_exists('wp_get_ability')?wp_get_ability($name):null;$row=array('name'=>$name,'registered'=>(bool)$a);
            if($a){foreach((new ReflectionObject($a))->getProperties() as $p) {
                if(!in_array($p->getName(),array('execute_callback','permission_callback'),true))continue;
                $p->setAccessible(true);$cb=$p->getValue($a);$key=$p->getName();$row[$key]=array('callable'=>is_callable($cb));
                if(is_callable($cb)){ $ref=is_array($cb)?new ReflectionMethod($cb[0],$cb[1]):new ReflectionFunction($cb);
                    $row[$key]['file']=str_replace(ABSPATH,'',$ref->getFileName());$row[$key]['line']=$ref->getStartLine();
                    $row[$key]['callback']=is_array($cb)?(is_object($cb[0])?get_class($cb[0]):$cb[0]).'::'.$cb[1]:(is_string($cb)?$cb:'closure');
                }
            }}
            $out[]=$row;
        }return array('items'=>$out);
    }
    if(($input['mode']??'')==='hashes')return mdg_stage4_source_read($input);
    $guard=function($sql){if(!preg_match('/^\\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\\b/i',$sql))throw new RuntimeException('AUDIT_SQL_WRITE_BLOCKED');return $sql;};
    add_filter('query',$guard,PHP_INT_MAX);
    $die=function(){return function(){throw new RuntimeException('AUDIT_WP_DIE');};};
    add_filter('wp_die_handler',$die);
    add_filter('wp_die_ajax_handler',$die);
    $warnings=array();
    set_error_handler(function($level,$message,$file,$line)use(&$warnings){if(error_reporting()&$level)$warnings[]=array('level'=>$level,'file'=>str_replace(ABSPATH,'',$file),'line'=>$line);return true;});
    $level=ob_get_level(); ob_start();
    $oldget=$_GET;$oldpost=$_POST;$oldrequest=$_REQUEST;$oldmethod=$_SERVER['REQUEST_METHOD']??'GET';
    try{
        $pages=mdg_stage5_admin_pages();
        if(($input['mode']??'')==='inventory') {
            $out=array('version'=>defined('MMC_VERSION')?MMC_VERSION:null,'pages'=>$pages,'modules'=>function_exists('mdg_ai_abilities_status')?mdg_ai_abilities_status():null);
        } else {
            $slug=(string)($input['slug']??'');$page=null;
            foreach($pages as $p)if($p['slug']===$slug){$page=$p;break;}
            if(!$page||!$page['hook']||!$page['callbacks'])return new WP_Error('audit_missing_page','Registered render callback not found.');
            $_GET=array('page'=>$slug);
            if(strpos($slug,'mmc-')===0 && !empty($input['program_id']))$_GET['program_id']=(int)$input['program_id'];
            $_POST=array();$_REQUEST=$_GET;$_SERVER['REQUEST_METHOD']='GET';
            ob_clean();do_action($page['hook']);$html=ob_get_contents();
            $out=array('slug'=>$slug,'bytes'=>strlen($html),'forms'=>substr_count($html,'<form'),'tables'=>substr_count($html,'<table'),'rows'=>substr_count($html,'<tr'),'contains_heading'=>(bool)preg_match('/<h[123]\\b/i',$html),'error_notice_count'=>substr_count($html,'notice-error'),'callback'=>$page['callbacks'],'permission'=>$page['permission']);
        }
        $out['warnings']=$warnings;return $out;
    }catch(Throwable $e){return array('slug'=>$input['slug']??'','status'=>$e->getMessage()==='AUDIT_SQL_WRITE_BLOCKED'?'READ_ONLY_WRITE_BLOCKED':'PHP_ERROR','exception'=>get_class($e),'reason'=>in_array($e->getMessage(),array('AUDIT_SQL_WRITE_BLOCKED','AUDIT_WP_DIE'),true)?$e->getMessage():'exception_message_redacted','file'=>str_replace(ABSPATH,'',$e->getFile()),'line'=>$e->getLine(),'warnings'=>$warnings,'trace'=>array_map(function($t){return array('class'=>$t['class']??'','method'=>$t['function']??'','file'=>isset($t['file'])?str_replace(ABSPATH,'',$t['file']):'','line'=>$t['line']??0);},array_slice($e->getTrace(),0,12)));}
    finally{
        while(ob_get_level()>$level)ob_end_clean();
        $_GET=$oldget;$_POST=$oldpost;$_REQUEST=$oldrequest;$_SERVER['REQUEST_METHOD']=$oldmethod;
        restore_error_handler();remove_filter('query',$guard,PHP_INT_MAX);remove_filter('wp_die_handler',$die);remove_filter('wp_die_ajax_handler',$die);
    }
}
add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;
    wp_register_ability('madagaskar/stage5-mmc-probe',array(
        'label'=>'Temporary read-only MMC audit','description'=>'Admin-only menu/render audit with SQL mutation blocking; never submits forms.',
        'category'=>'madagaskar-saglik','input_schema'=>array('type'=>'object','properties'=>array('mode'=>array('type'=>'string','enum'=>array('inventory','render','hashes','batch','ability-info')),'slug'=>array('type'=>'string'),'names'=>array('type'=>'array','items'=>array('type'=>'string')),'slugs'=>array('type'=>'array','items'=>array('type'=>'string'),'maxItems'=>12),'program_id'=>array('type'=>'integer'),'path'=>array('type'=>'string')),'required'=>array('mode')),
        'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_stage5_probe','permission_callback'=>function(){return current_user_can('manage_options');},
        'meta'=>array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'show_in_rest'=>true)
    ));
});
