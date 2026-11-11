<?php
/**
 * Plugin Name: Milano MDG Plan Narrow Deployment
 * Version: 0.1.0
 * Description: Exact-blob-guarded single-file MDG brand plan deployment. No event, order, payment or ticket writes.
 */
if (!defined('ABSPATH')) { exit; }
final class Milano_MDG_Plan_Deploy {
 const OLD_BLOB='a6f873b870a644b1ad45faefc2d04a2e7981a0c4';
 const NEW_BLOB='eee42cd0ddccd8c3435eae4981eef99d03e05fb8';
 static function blob($s){return sha1('blob '.strlen($s)."\0".$s);}
 static function path(){return WP_PLUGIN_DIR.'/madagaskar-bilet-yonetimi/includes/class-mdg-new-event-production-plan.php';}
 static function apply(){
  $host=strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));
  if($host!=='madagaskarsirki.com'){wp_die('Only the verified central live host is supported.');}
  $file=self::path();$old=is_file($file)?file_get_contents($file):false;
  if(!is_string($old)){wp_die('MMC source unavailable; nothing changed.');}
  if(self::blob($old)===self::NEW_BLOB){return;}
  if(self::blob($old)!==self::OLD_BLOB){wp_die('MMC source has changed; refusing overwrite.');}
  $target=file_get_contents(__DIR__.'/class-mdg-new-event-production-plan.php');
  if(!is_string($target)||self::blob($target)!==self::NEW_BLOB){wp_die('Deployment payload mismatch.');}
  if(!is_file(__DIR__.'/baseline.php')&&file_put_contents(__DIR__.'/baseline.php',$old,LOCK_EX)!==strlen($old)){wp_die('Backup failed; refusing write.');}
  $tmp=$file.'.milano-'.wp_generate_password(12,false,false).'.tmp';
  if(file_put_contents($tmp,$target,LOCK_EX)!==strlen($target)||self::blob(file_get_contents($file))!==self::OLD_BLOB||!rename($tmp,$file)){
   @unlink($tmp);wp_die('Source write failed.');}
  if(function_exists('opcache_invalidate')){opcache_invalidate($file,true);}
  update_option('milano_mdg_plan_deployment',array('state'=>'applied','old'=>self::OLD_BLOB,'new'=>self::NEW_BLOB,'at'=>current_time('mysql')),'no');
 }
 static function boot(){
  add_action('admin_menu',static function(){add_management_page('Milano MDG Plan Deployment','Milano MDG Plan Deployment','manage_options','milano-mdg-plan-deploy',array(__CLASS__,'page'));});
  add_action('admin_post_milano_mdg_plan_rollback',array(__CLASS__,'rollback'));
 }
 static function page(){
  if(!current_user_can('manage_options')){return;}
  $s=is_file(self::path())?file_get_contents(self::path()):'';
  echo '<div class="wrap"><h1>Milano MDG Plan Deployment</h1><p>Installed blob: <code>'.esc_html(self::blob($s)).'</code></p>';
  echo '<p>Plan service loaded: '.(method_exists('MDG_New_Event_Production_Plan','build')?'yes':'no').'</p>';
  echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('milano_mdg_plan_rollback');echo '<input type="hidden" name="action" value="milano_mdg_plan_rollback"><button class="button">Restore verified previous MMC source</button></form></div>';
 }
 static function rollback(){
  if(!current_user_can('manage_options')){wp_die('Forbidden');}
  check_admin_referer('milano_mdg_plan_rollback');
  $file=self::path();$old=file_get_contents(__DIR__.'/baseline.php');
  if(self::blob(file_get_contents($file))!==self::NEW_BLOB||self::blob($old)!==self::OLD_BLOB){wp_die('Source changed; rollback refused.');}
  if(file_put_contents($file,$old,LOCK_EX)!==strlen($old)){wp_die('Rollback write failed.');}
  if(function_exists('opcache_invalidate')){opcache_invalidate($file,true);}
  update_option('milano_mdg_plan_deployment',array('state'=>'rolled_back'),'no');
  wp_safe_redirect(admin_url('tools.php?page=milano-mdg-plan-deploy'));exit;
 }
}
register_activation_hook(__FILE__,array('Milano_MDG_Plan_Deploy','apply'));
Milano_MDG_Plan_Deploy::boot();
