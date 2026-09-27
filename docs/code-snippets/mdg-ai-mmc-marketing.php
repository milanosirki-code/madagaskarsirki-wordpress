<?php
/**
 * Madagaskar AI — MMC Pazarlama / Meta
 *
 * Mevcut MMC_Marketing_Service kuralları kullanılır.
 * İçerik paketi üretimi idempotenttir; kaynak değişmemişse yeni sürüm üretmez.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_marketing_can_run' ) ) {
    function mdg_ai_marketing_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_marketing_forbidden', 'WooCommerce yönetim yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Marketing_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_marketing_missing', 'MMC pazarlama servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_marketing_arr' ) ) {
    function mdg_ai_marketing_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_marketing_program' ) ) {
    function mdg_ai_marketing_program( $program_id ) {
        $program = MMC_Program_Service::get_program( absint( $program_id ) );
        return $program ?: new WP_Error( 'mdg_ai_marketing_program_missing', 'Program bulunamadı.' );
    }
}

if ( ! function_exists( 'mdg_ai_marketing_pack_get' ) ) {
    function mdg_ai_marketing_pack_get( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_marketing_program($program_id);
        if(is_wp_error($program))return $program;

        return array(
            'program'=>mdg_ai_marketing_arr($program),
            'item_types'=>MMC_Marketing_Service::item_types(),
            'item_statuses'=>MMC_Marketing_Service::item_statuses(),
            'latest_items'=>mdg_ai_marketing_arr(MMC_Marketing_Service::latest_items($program_id)),
            'all_items'=>mdg_ai_marketing_arr(MMC_Marketing_Service::items($program_id)),
            'meta_plan'=>mdg_ai_marketing_arr(MMC_Marketing_Service::meta_plan($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_marketing_pack_ensure' ) ) {
    function mdg_ai_marketing_pack_ensure( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_marketing_program($program_id);
        if(is_wp_error($program))return $program;

        $force=!empty($input['force']);
        $result=MMC_Marketing_Service::ensure_pack($program_id,$force);
        if(is_wp_error($result))return $result;

        return array(
            'generated_or_current'=>true,
            'forced'=>$force,
            'latest_items'=>mdg_ai_marketing_arr(MMC_Marketing_Service::latest_items($program_id)),
            'meta_plan'=>mdg_ai_marketing_arr(MMC_Marketing_Service::meta_plan($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_marketing_item_get' ) ) {
    function mdg_ai_marketing_item_get( $input ) {
        $id=absint($input['item_id']??0);
        $item=MMC_Marketing_Service::get_item($id);
        if(!$item)return new WP_Error('mdg_ai_marketing_item_missing','Pazarlama içeriği bulunamadı.');
        return array('item'=>mdg_ai_marketing_arr($item),'statuses'=>MMC_Marketing_Service::item_statuses());
    }
}

if ( ! function_exists( 'mdg_ai_marketing_item_update' ) ) {
    function mdg_ai_marketing_item_update( $input ) {
        $id=absint($input['item_id']??0);
        $item=MMC_Marketing_Service::get_item($id);
        if(!$item)return new WP_Error('mdg_ai_marketing_item_missing','Pazarlama içeriği bulunamadı.');

        $data=array(
            'title'=>(string)$item->title,
            'body'=>(string)$item->body,
            'brief'=>(string)$item->brief,
            'status'=>(string)$item->status,
            'scheduled_at'=>(string)($item->scheduled_at?:''),
            'external_url'=>(string)($item->external_url?:'')
        );
        foreach(array_keys($data) as $field){
            if(array_key_exists($field,$input))$data[$field]=$input[$field];
        }

        $result=MMC_Marketing_Service::save_item($id,$data);
        if(is_wp_error($result))return $result;

        return array('updated'=>true,'item'=>mdg_ai_marketing_arr(MMC_Marketing_Service::get_item($id)));
    }
}

if ( ! function_exists( 'mdg_ai_meta_plan_get' ) ) {
    function mdg_ai_meta_plan_get( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_marketing_program($program_id);
        if(is_wp_error($program))return $program;
        $plan=MMC_Marketing_Service::meta_plan($program_id);
        return array('program_id'=>$program_id,'plan'=>mdg_ai_marketing_arr($plan));
    }
}

if ( ! function_exists( 'mdg_ai_meta_plan_update' ) ) {
    function mdg_ai_meta_plan_update( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_marketing_program($program_id);
        if(is_wp_error($program))return $program;

        $plan=MMC_Marketing_Service::meta_plan($program_id);
        if(!$plan){
            $ensure=MMC_Marketing_Service::ensure_pack($program_id,false);
            if(is_wp_error($ensure))return $ensure;
            $plan=MMC_Marketing_Service::meta_plan($program_id);
        }
        if(!$plan)return new WP_Error('mdg_ai_meta_plan_missing','Meta planı oluşturulamadı.');

        $fields=array(
            'campaign_name','geo_summary','audience_notes','daily_budget','total_budget',
            'start_at','end_at','status','external_campaign_id','external_adset_id',
            'external_ad_id','spend','purchases','revenue'
        );
        $data=array();
        foreach($fields as $field){
            $data[$field]=property_exists($plan,$field)?$plan->$field:'';
            if(array_key_exists($field,$input))$data[$field]=$input[$field];
        }

        $result=MMC_Marketing_Service::save_meta_plan($program_id,$data);
        if(is_wp_error($result))return $result;

        return array('updated'=>true,'plan'=>mdg_ai_marketing_arr(MMC_Marketing_Service::meta_plan($program_id)));
    }
}

add_action('wp_abilities_api_categories_init',function(){
    if(function_exists('wp_register_ability_category')){
        wp_register_ability_category('madagaskar-pazarlama',array(
            'label'=>'Madagaskar Pazarlama / Meta',
            'description'=>'Afiş, Instagram, Reels, geri sayım, çekiliş ve Meta reklam planı hazırlık katmanı.'
        ));
    }
});

add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $ensure=array('annotations'=>array('readonly'=>false,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $write=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);

    $program_schema=array(
        'type'=>'object',
        'properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),
        'required'=>array('program_id')
    );

    wp_register_ability('madagaskar/marketing-pack-get',array(
        'label'=>'Pazarlama Paketini Getir',
        'description'=>'Programın son afiş, Instagram post/story, Reels, geri sayım, çekiliş ve Meta reklam metinleriyle Meta planını getirir.',
        'category'=>'madagaskar-pazarlama',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_marketing_pack_get',
        'permission_callback'=>'mdg_ai_marketing_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/marketing-pack-ensure',array(
        'label'=>'Pazarlama Paketini Oluştur/Güncelle',
        'description'=>'Program/salon/seans kaynağı değişmişse yeni içerik sürümleri üretir; kaynak aynıysa mükerrer sürüm oluşturmaz. force=true yalnız açık talepte kullanılmalıdır.',
        'category'=>'madagaskar-pazarlama',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'force'=>array('type'=>'boolean')
            ),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_marketing_pack_ensure',
        'permission_callback'=>'mdg_ai_marketing_can_run',
        'meta'=>$ensure
    ));

    wp_register_ability('madagaskar/marketing-item-get',array(
        'label'=>'Pazarlama İçeriğini Getir',
        'description'=>'Tek pazarlama içerik sürümünün başlık, metin, brief, durum, zamanlama ve dış URL bilgilerini getirir.',
        'category'=>'madagaskar-pazarlama',
        'input_schema'=>array('type'=>'object','properties'=>array('item_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('item_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_marketing_item_get',
        'permission_callback'=>'mdg_ai_marketing_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/marketing-item-update',array(
        'label'=>'Pazarlama İçeriğini Güncelle',
        'description'=>'İçerik başlığı, metni, briefi, durumu, planlanan zamanı veya yayın URL’sini kısmi günceller. approved/published durumları onay ve yayın zamanını kaydeder.',
        'category'=>'madagaskar-pazarlama',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'item_id'=>array('type'=>'integer','minimum'=>1),
                'title'=>array('type'=>'string'),
                'body'=>array('type'=>'string'),
                'brief'=>array('type'=>'string'),
                'status'=>array('type'=>'string','enum'=>array('draft','review','approved','published','needs_update','retired','error')),
                'scheduled_at'=>array('type'=>'string'),
                'external_url'=>array('type'=>'string')
            ),
            'required'=>array('item_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_marketing_item_update',
        'permission_callback'=>'mdg_ai_marketing_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/meta-plan-get',array(
        'label'=>'Meta Reklam Planını Getir',
        'description'=>'Programın Meta kampanya adı, coğrafya, hedef kitle, bütçe, tarih, durum ve performans metriklerini getirir.',
        'category'=>'madagaskar-pazarlama',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_meta_plan_get',
        'permission_callback'=>'mdg_ai_marketing_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/meta-plan-update',array(
        'label'=>'Meta Reklam Planını Güncelle',
        'description'=>'Meta planını kısmi günceller; harcama, satın alma ve gelirden CPA/ROAS değerlerini mevcut servis otomatik yeniden hesaplar.',
        'category'=>'madagaskar-pazarlama',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'campaign_name'=>array('type'=>'string'),
                'geo_summary'=>array('type'=>'string'),
                'audience_notes'=>array('type'=>'string'),
                'daily_budget'=>array('type'=>'number','minimum'=>0),
                'total_budget'=>array('type'=>'number','minimum'=>0),
                'start_at'=>array('type'=>'string'),
                'end_at'=>array('type'=>'string'),
                'status'=>array('type'=>'string','enum'=>array('draft','review','approved','active','paused','stop_required','completed')),
                'external_campaign_id'=>array('type'=>'string'),
                'external_adset_id'=>array('type'=>'string'),
                'external_ad_id'=>array('type'=>'string'),
                'spend'=>array('type'=>'number','minimum'=>0),
                'purchases'=>array('type'=>'integer','minimum'=>0),
                'revenue'=>array('type'=>'number','minimum'=>0)
            ),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_meta_plan_update',
        'permission_callback'=>'mdg_ai_marketing_can_run',
        'meta'=>$write
    ));
});
