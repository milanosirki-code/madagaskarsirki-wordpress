<?php
/**
 * Plugin Name: Milano Yalova Staging Plan Audit
 * Version: 0.1.0
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
