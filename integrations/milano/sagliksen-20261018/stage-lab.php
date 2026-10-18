<?php
/**
 * Plugin Name: Milano Sağlık-Sen İzole Prova Laboratuvarı
 * Version: 0.1.0
 * Description: Yalnız belirtilen staging hostunda; Code Snippets otomasyonlarını etkinleştirmeden kampanya kaynaklarını test eder.
 */
if(!defined('ABSPATH')){exit;}
if(strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST))!=='staging-f321-milanosirki-lgajf.wpcomstaging.com'){return;}
require_once __DIR__.'/corporate.php';
require_once __DIR__.'/milano-sagliksen.php';
add_action('rest_api_init',static function(){
    register_rest_route('milano-stage-lab/v1','/preflight',array('methods'=>'GET','permission_callback'=>static function(){return current_user_can('manage_woocommerce');},'callback'=>static function(){
        $out=array('host'=>wp_parse_url(home_url('/'),PHP_URL_HOST),'paytr_test'=>get_option('woocommerce_paytr_payment_gateway_settings',array())['test']??null,'events'=>array());
        foreach(Milano_Sagliksen::STAGE_EVENTS as $eid=>$scope){
            $e=MDG_Events::get($eid);$r=MDG_Production_Readiness::check_event($e);
            $out['events'][]=array('id'=>$eid,'title'=>$e->title??null,'status'=>$e->status??null,'ready'=>$r['ready']??false,'errors'=>$r['errors']??array(),'sales_plan'=>$r['sales_plan']??null);
        }
        return rest_ensure_response($out);
    }));
});
