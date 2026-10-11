<?php
if (!defined('ABSPATH')) { exit; }
// Temporary text-only migration. No ticket/order/session/identity writes.
add_action('rest_api_init', function () {
 register_rest_route('mdg-price-change/v1','/text-apply', array(
  'methods'=>'POST',
  'permission_callback'=>function(){return current_user_can('manage_options') && current_user_can('manage_woocommerce');},
  'callback'=>function(){
   global $wpdb;
   $ids=array(10,13,19,21,22,23); $fields=array('short_description','long_description','faq_json','seo_description','rules');
   $before=array(); $updates=array();
   foreach($ids as $id) {
    $e=MDG_Events::get($id);
    if (!$e || 'onsale' !== $e->status) { return new WP_Error('scope','Event status changed',array('status'=>409)); }
    $data=array();
    foreach($fields as $field) {
     $old=(string)$e->$field; $before[$id][$field]=$old;
     $map=10===$id ? array('300 TL'=>'290 TL','600 TL'=>'585 TL') : array('250 TL'=>'245 TL','500 TL'=>'490 TL');
     $new=strtr($old,$map);
     if($new!==$old) { $data[$field]=$new; }
    }
    $updates[$id]=$data;
   }
   $key='mdg_price_text_20261011_backup';
   if(!get_option($key)) { add_option($key,$before,'',false); }
   $wpdb->query('START TRANSACTION');
   foreach($updates as $id=>$data) {
    if($data && false===$wpdb->update(MDG_DB::table('events'),$data,array('id'=>$id,'status'=>'onsale'))) {
     $wpdb->query('ROLLBACK'); return new WP_Error('write','Text update failed',array('status'=>500));
    }
   }
   $wpdb->query('COMMIT');
   return array('updated_fields'=>array_map('array_keys',$updates),'backup_option'=>$key);
  }
 ));
});
