<?php
/**
 * Plugin Name: Milano Yalova Staging Plan Audit
 * Version: 0.3.0
 * Description: Authenticated read-only production-plan audit for MDG24 on the isolated staging hostname only.
 * Author: Dünya Organizasyon
 */
if (!defined('ABSPATH')) {exit;}
add_action('rest_api_init',function(){
    if (parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com') {return;}
    register_rest_route('milano-staging/v1','/yalova-plan',array(
        'methods'=>'GET',
        'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
        'callback'=>static function() {
            if (!class_exists('MDG_Events')||!class_exists('MDG_New_Event_Production_Plan')) {
                return new WP_Error('dependencies_missing','MDG planner unavailable.',array('status'=>503));
            }
            $event=MDG_Events::get(24);
            if (!$event || $event->status!=='draft' || (string)$event->title!=='Milano Sirki — Yalova') {
                return new WP_Error('wrong_event','Staging canonical Milano draft unavailable.',array('status'=>409));
            }
            $plan=MDG_New_Event_Production_Plan::build($event);
            $products=array();
            foreach((array)($plan['products']??array()) as $p){
                $products[]=array(
                    'session_id'=>$p['session_id']??null,
                    'date'=>$p['date']??null,
                    'time'=>$p['time']??null,
                    'product_name'=>$p['product_name']??null,
                    'product_slug'=>$p['product_slug']??null,
                    'capacity_total'=>$p['capacity_total']??null,
                    'variations'=>array_map(static function($v){return array(
                        'label'=>$v['label']??'', 'price'=>$v['price']??null,
                        'capacity_units'=>$v['capacity_units']??null,
                    );},(array)($p['variations']??array()))
                );
            }
            return rest_ensure_response(array(
                'ready'=>(bool)($plan['ready']??false),
                'event_id'=>$plan['event_id']??null,
                'brand'=>$plan['brand']??null,
                'session_count'=>$plan['session_count']??null,
                'errors'=>$plan['errors']??array(),
                'warnings'=>$plan['warnings']??array(),
                'template'=>$plan['template']??null,
                'tickera'=>$plan['tickera']??null,
                'products'=>$products,
                'note'=>'Readonly MDG production plan; no Woo, Tickera, QR or PayTR writes.'
            ));
        }
    ));
});


/**
 * Staging-only controlled invocation of the actual MDG draft producer.
 * Never creates payment, orders, live tickets or publish-state products.
 * This endpoint is intentionally outside the public Milano customer site.
 */
add_action('rest_api_init',static function() {
    if (parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com') {return;}
    register_rest_route('milano-staging/v1','/yalova-draft-state',array(
        'methods'=>'GET',
        'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
        'callback'=>static function() {
            if (!class_exists('MDG_Events')||!class_exists('MDG_New_Event_Production_Plan')||!class_exists('MDG_New_Event_Draft_Producer')) {
                return new WP_Error('missing_producer','MDG class unavailable',array('status'=>503));
            }
            $event=MDG_Events::get(24);
            if (!$event || (string)$event->title!=='Milano Sirki — Yalova') {
                return new WP_Error('unverified_event','Yalova event not found',array('status'=>409));
            }
            $plan=MDG_New_Event_Production_Plan::build($event);
            return rest_ensure_response(array(
                'event_id'=>24, 'brand'=>$plan['brand']??null, 'ready'=>$plan['ready']??false,
                'state'=>MDG_New_Event_Draft_Producer::state(24,(string)($plan['production_key']??'')),
                'errors'=>$plan['errors']??array()
            ));
        }
    ));
    register_rest_route('milano-staging/v1','/yalova-produce-drafts',array(
        'methods'=>'POST',
        'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
        'callback'=>static function() {
            if (!class_exists('MDG_Events')||!class_exists('MDG_New_Event_Production_Plan')||!class_exists('MDG_New_Event_Draft_Producer')) {
                return new WP_Error('missing_producer','MDG draft producer unavailable',array('status'=>503));
            }
            $event=MDG_Events::get(24);
            if (!$event || (string)$event->status!=='draft' || (string)$event->title!=='Milano Sirki — Yalova'
                || (int)$event->venue_id!==181 || (int)$event->hero_attachment_id!==6048
                || (int)$event->venue_qr_attachment_id!==6047) {
                return new WP_Error('event_guard','Yalova identity/assets no longer match',array('status'=>409));
            }
            $plan=MDG_New_Event_Production_Plan::build($event);
            $brand=$plan['brand']??array();
            $tpl=$plan['template']??array();
            if (empty($plan['ready']) || (int)($brand['program_id']??0)!==14
                || ($brand['key']??'')!=='milano'
                || (int)($tpl['base_template_id']??0)!==28
                || ($tpl['candidate_id']??'')!=='milano_yalova_venue_qr_v1'
                || (int)($plan['session_count']??0)!==2
                || array_map('intval',(array)($plan['session_ids']??array()))!==array(150,151)) {
                return new WP_Error('plan_guard','Staging plan changed; no draft production',array('status'=>409));
            }
            global $wpdb;
            $sha=$wpdb->get_var("SELECT SHA2(template_data,256) FROM {$wpdb->prefix}tickera_ticket_templates WHERE id=28 AND status='active' LIMIT 1");
            if (!hash_equals('8ce4f316026e6ba707561b408e87d16e51ee71c91dd67d31b7695959741525cc',(string)$sha)) {
                return new WP_Error('designer_changed','Designer content hash changed',array('status'=>409));
            }
            $sessions=$wpdb->get_results("SELECT id,start_at,capacity_total,held_units,sold_units,wc_product_id,tickera_event_id FROM {$wpdb->prefix}mdg_sessions WHERE event_id=24 ORDER BY id");
            if (count((array)$sessions)!==2) {return new WP_Error('session_count','Expected exactly two sessions',array('status'=>409));}
            foreach($sessions as $i=>$s) {
                if ((int)$s->id!==array(150,151)[$i] || (int)$s->capacity_total!==500
                    || (int)$s->sold_units!==0 || (int)$s->held_units!==0
                    || (int)$s->wc_product_id!==0 || (int)$s->tickera_event_id!==0) {
                    return new WP_Error('session_changed','Existing capacity or mapping prevents draft create',array('status'=>409));
                }
            }
            $state=MDG_New_Event_Draft_Producer::state(24,(string)$plan['production_key']);
            if (($state['status']??'')!=='clean') {
                return new WP_Error('not_clean','Producer state not clean; no repeat production',array('status'=>409));
            }
            // The owned producer retains its WP capability, nonce, state,
            // create, managed-object rollback, audit, mapping and idempotency guards.
            $nonce=wp_create_nonce('mdg_create_new_event_sales_draft_24');
            $_POST['event_id']=24;
            $_POST['confirm_create']='1';
            $_POST['mdg_nonce']=$nonce;
            $_REQUEST['event_id']=24;
            $_REQUEST['mdg_nonce']=$nonce;
            MDG_New_Event_Draft_Producer::handle_create();
            return new WP_Error('unreachable','Native producer unexpectedly returned without redirect',array('status'=>500));
        }
    ));
});


/** Read-only parser contract for the already-existing session datetime PDF fix. */
add_action('rest_api_init',static function(){
 if(parse_url(site_url(),PHP_URL_HOST)!=='staging-f321-milanosirki-lgajf.wpcomstaging.com'){return;}
 register_rest_route('milano-staging/v1','/yalova-datetime-smoke',array(
  'methods'=>'GET',
  'permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
  'callback'=>static function(){
   if(!function_exists('mdg_tdfix_correct_ticket_data')){return new WP_Error('fix_inactive','Existing Tickera datetime fix is not active.',array('status'=>503));}
   $pairs=array(array(6058,'18:00','19:00'),array(6061,'19:30','20:30'));
   $results=array();
   foreach($pairs as $pair){
    $post=get_post($pair[0]);
    if(!$post||$post->post_type!=='product'||$post->post_status!=='draft'
       ||false===stripos((string)$post->post_title,'Milano Sirki')
       ||(int)get_post_meta((int)$post->ID,'_mdg_event_id',true)!==24
       ||(string)get_post_meta((int)$post->ID,'_mdg_production_key',true)!=='MDG-E24-618c401e2640'){
      return new WP_Error('wrong_product','Canonical staging ticket product mismatch.',array('status'=>409));
    }
    $ticket_data=array(
     'event_name'=>'Milano Sirki — Yalova',
     'event_datetime'=>'22 Ekim 2026 18:00 - 20:30',
     'product_name'=>(string)$post->post_title
    );
    $out=mdg_tdfix_correct_ticket_data($ticket_data,0);
    $str=(string)($out['event_datetime']??'');
    $ok=($out['event_start_time']??'')===$pair[1]&&($out['event_end_time']??'')===$pair[2]
     &&false!==strpos($str,'22')&&false!==strpos($str,$pair[1]);
    $results[]=array('product_id'=>$pair[0],'expected_start'=>$pair[1],
     'expected_end'=>$pair[2],'resolved'=>$str,'pass'=>$ok);
   }
   return rest_ensure_response(array(
    'read_only'=>true,
    'type'=>'synthetic parser smoke; NOT a real Tickera PDF or real ticket instance',
    'all_pass'=>count(array_filter($results,static function($r){return $r['pass'];}))===count($results),
    'results'=>$results,
    'hook_attached'=>(bool)has_filter('tickera_ticket_designer_pre_generate','mdg_tdfix_pre_generate')
   ));
  }
 ));
});
