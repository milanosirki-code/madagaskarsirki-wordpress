<?php
/**
 * Plugin Name: Milano Launch Read-only Issuance Inspector
 * Description: Admin-only staging inspection of existing Tickera hooks; no order, ticket, payment or metadata writes.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) { exit; }
final class Milano_Launch_Issuance_Inspector {
 const HOST = 'staging-f321-milanosirki-lgajf.wpcomstaging.com';
 public static function allowed() {
  return parse_url(site_url(), PHP_URL_HOST) === self::HOST && current_user_can('manage_woocommerce');
 }
 public static function inspect() {
  if (!self::allowed()) { return new WP_Error('scope', 'Staging administrator required.', array('status'=>403)); }
  global $wpdb, $wp_filter;
  $owners = $wpdb->get_results("SELECT p.id,p.brand FROM {$wpdb->prefix}mmc_mdg_event_bridge b INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=b.program_id WHERE b.mdg_event_id=24 AND b.is_active=1");
  if (count((array)$owners)!==1 || (int)$owners[0]->id!==14 || $owners[0]->brand!=='Milano Sirki') {
   return new WP_Error('owner','Canonical staging owner differs.',array('status'=>409));
  }
  $hooks=array();
  foreach ((array)$wp_filter as $name=>$hook) {
   if (!preg_match('/woocommerce_order_status_(processing|completed)|tickera_order_is_paid|tc_created_order_ticket_instance|tc_.*(ticket|instance|download)|woocommerce_payment_complete/', $name)) { continue; }
   foreach ((array)$hook->callbacks as $priority=>$callbacks) {
    foreach ($callbacks as $entry) {
     $fn=$entry['function'];
     $class=''; $method=''; $ref=null;
     if (is_array($fn) && count($fn)===2) {
      $class=is_object($fn[0])?get_class($fn[0]):(string)$fn[0]; $method=(string)$fn[1];
      try {$ref=new ReflectionMethod($class,$method);} catch (ReflectionException $e) {}
     } elseif (is_string($fn)) {
      $method=$fn; try {$ref=new ReflectionFunction($fn);} catch (ReflectionException $e) {}
     }
     if (!$ref || !$ref->getFileName()) { continue; }
     $file=$ref->getFileName();
     if (strpos($file,WP_CONTENT_DIR.'/plugins/')!==0) { continue; }
     $relative=substr($file,strlen(WP_CONTENT_DIR)+1);
     $hooks[]=array('hook'=>$name,'priority'=>(int)$priority,'class'=>$class,'method'=>$method,'file'=>$relative,'start'=>$ref->getStartLine(),'end'=>$ref->getEndLine());
    }
   }
  }
  $methods=array();
  foreach(get_declared_classes() as $class) {
   if (!preg_match('/TC_WooCommerce_Bridge|TC_Tickets_Instances|TC_Ticket_Template/', $class)) {continue;}
   $rc=new ReflectionClass($class);
   foreach($rc->getMethods() as $m) {
    if (!preg_match('/ticket|instance|download|paid|order/i',$m->name)) {continue;}
    $file=$m->getFileName();
    if(!$file || strpos($file,WP_CONTENT_DIR.'/plugins/')!==0){continue;}
    $methods[]=array('class'=>$class,'method'=>$m->name,'public'=>$m->isPublic(),'static'=>$m->isStatic(),'required_arguments'=>$m->getNumberOfRequiredParameters(),'file'=>substr($file,strlen(WP_CONTENT_DIR)+1),'start'=>$m->getStartLine(),'end'=>$m->getEndLine());
   }
  }
  return rest_ensure_response(array('read_only'=>true,'orders_created'=>false,'tickets_created'=>false,'payments_started'=>false,'hooks'=>$hooks,'methods'=>$methods));
 }
 public static function boot() {
  if(parse_url(site_url(),PHP_URL_HOST)!==self::HOST){return;}
  add_action('rest_api_init',static function(){
   register_rest_route('milano-launch/v1','/issuance-hooks',array('methods'=>'GET','permission_callback'=>array(__CLASS__,'allowed'),'callback'=>array(__CLASS__,'inspect')));
  });
 }
}
Milano_Launch_Issuance_Inspector::boot();
