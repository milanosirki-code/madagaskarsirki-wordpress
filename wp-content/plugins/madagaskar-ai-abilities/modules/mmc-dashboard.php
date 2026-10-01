<?php
/**
 * Madagaskar AI — MMC Genel Bakış / Kritik Uyarılar
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_dashboard_can_run' ) ) {
    function mdg_ai_dashboard_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_dashboard_forbidden', 'Yönetici yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Dashboard_Service' ) ) {
            return new WP_Error( 'mdg_ai_dashboard_missing', 'MMC dashboard servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_dashboard_arr' ) ) {
    function mdg_ai_dashboard_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_dashboard_overview' ) ) {
    function mdg_ai_dashboard_overview( $input = array() ) {
        return array(
            'overview' => mdg_ai_dashboard_arr( MMC_Dashboard_Service::overview() ),
            'critical_alerts' => MMC_Dashboard_Service::critical_alerts( max( 1, min( 50, absint( $input['alert_limit'] ?? 12 ) ) ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_dashboard_program_rows' ) ) {
    function mdg_ai_dashboard_program_rows( $input = array() ) {
        $filters=array(
            'status'=>sanitize_key($input['status']??''),
            'province'=>sanitize_text_field($input['province']??''),
            'q'=>sanitize_text_field($input['search']??''),
            'include_closed'=>!empty($input['include_closed'])
        );
        return array(
            'provinces'=>MMC_Dashboard_Service::provinces(),
            'items'=>mdg_ai_dashboard_arr(MMC_Dashboard_Service::program_rows($filters))
        );
    }
}

if ( ! function_exists( 'mdg_ai_dashboard_alerts' ) ) {
    function mdg_ai_dashboard_alerts( $input = array() ) {
        $limit=max(1,min(100,absint($input['limit']??20)));
        return array('items'=>MMC_Dashboard_Service::critical_alerts($limit));
    }
}

add_action('wp_abilities_api_categories_init',function(){
    if(function_exists('wp_register_ability_category')){
        wp_register_ability_category('madagaskar-genel-bakis',array(
            'label'=>'Madagaskar Genel Bakış',
            'description'=>'Günlük satış, Meta, Kommo, görevler, yaklaşan gösteri ve kritik program uyarıları.'
        ));
    }
});

add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);

    wp_register_ability('madagaskar/dashboard-overview',array(
        'label'=>'Madagaskar Genel Bakışı Getir',
        'description'=>'MMC satış defteri, iadeler, Meta performansı, Kommo lead sayısı, yaklaşan gösteri ve açık/geciken görevleri tek özet halinde getirir.',
        'category'=>'madagaskar-genel-bakis',
        'input_schema'=>array('type'=>'object','properties'=>array('alert_limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>50))),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_dashboard_overview',
        'permission_callback'=>'mdg_ai_dashboard_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/dashboard-programs',array(
        'label'=>'Genel Bakış Programlarını Getir',
        'description'=>'Programları durum, il veya arama metnine göre dashboard risk ve performans alanlarıyla getirir.',
        'category'=>'madagaskar-genel-bakis',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'status'=>array('type'=>'string'),
            'province'=>array('type'=>'string'),
            'search'=>array('type'=>'string'),
            'include_closed'=>array('type'=>'boolean')
        )),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_dashboard_program_rows',
        'permission_callback'=>'mdg_ai_dashboard_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/dashboard-critical-alerts',array(
        'label'=>'Kritik Program Uyarılarını Getir',
        'description'=>'Aktif programlardaki kritik/yüksek riskleri tarihe ve önem düzeyine göre sıralar.',
        'category'=>'madagaskar-genel-bakis',
        'input_schema'=>array('type'=>'object','properties'=>array('limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>100))),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_dashboard_alerts',
        'permission_callback'=>'mdg_ai_dashboard_can_run',
        'meta'=>$read
    ));
});
