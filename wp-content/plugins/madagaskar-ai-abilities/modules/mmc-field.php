<?php
/**
 * Madagaskar AI — MMC Saha / Okul Hedefleri
 *
 * Okul ana kaynağını çoğaltmaz. MMC_Field_Service üzerinden program hedefleri,
 * atamalar, rotalar, ziyaret geçmişi ve saha paylaşım bağlantıları yönetilir.
 * Fotoğraflı ziyaret kaydı bu snippet'te otomatikleştirilmez.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_field_can_run' ) ) {
    function mdg_ai_field_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_field_forbidden', 'Yönetici yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Field_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_field_missing', 'MMC saha servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_field_arr' ) ) {
    function mdg_ai_field_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_field_program' ) ) {
    function mdg_ai_field_program( $program_id ) {
        $program=MMC_Program_Service::get_program(absint($program_id));
        return $program?:new WP_Error('mdg_ai_field_program_missing','Program bulunamadı.');
    }
}

if ( ! function_exists( 'mdg_ai_field_sync_targets' ) ) {
    function mdg_ai_field_sync_targets( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $result=MMC_Field_Service::sync_target_schools($program_id);
        if(is_wp_error($result))return $result;

        return array(
            'synced'=>true,
            'result'=>$result,
            'summary'=>MMC_Field_Service::summary($program_id)
        );
    }
}

if ( ! function_exists( 'mdg_ai_field_targets' ) ) {
    function mdg_ai_field_targets( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $args=array(
            'status'=>sanitize_key($input['status']??''),
            'district'=>sanitize_text_field($input['district']??''),
            'assigned_name'=>sanitize_text_field($input['assigned_name']??''),
            'assigned_user_id'=>absint($input['assigned_user_id']??0),
            'search'=>sanitize_text_field($input['search']??''),
            'exclude_skipped'=>!empty($input['exclude_skipped']),
            'limit'=>max(1,min(2000,absint($input['limit']??500)))
        );
        return array(
            'program_id'=>$program_id,
            'statuses'=>MMC_Field_Service::target_statuses(),
            'summary'=>MMC_Field_Service::summary($program_id),
            'items'=>mdg_ai_field_arr(MMC_Field_Service::targets($program_id,$args))
        );
    }
}

if ( ! function_exists( 'mdg_ai_field_summary' ) ) {
    function mdg_ai_field_summary( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;
        return array('program_id'=>$program_id,'summary'=>MMC_Field_Service::summary($program_id));
    }
}

if ( ! function_exists( 'mdg_ai_field_assign' ) ) {
    function mdg_ai_field_assign( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $target_ids=array_values(array_unique(array_filter(array_map('absint',(array)($input['target_ids']??array())))));
        if(count($target_ids)>1000)return new WP_Error('mdg_ai_field_assign_limit','Tek işlemde en fazla 1000 hedef okul atanabilir.');

        $result=MMC_Field_Service::assign_targets(
            $program_id,
            $target_ids,
            absint($input['assigned_user_id']??0),
            sanitize_text_field($input['assigned_name']??'')
        );
        if(is_wp_error($result))return $result;

        return array('updated_count'=>(int)$result,'summary'=>MMC_Field_Service::summary($program_id));
    }
}

if ( ! function_exists( 'mdg_ai_field_bulk_scope' ) ) {
    function mdg_ai_field_bulk_scope( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $target_ids=array_values(array_unique(array_filter(array_map('absint',(array)($input['target_ids']??array())))));
        if(count($target_ids)>1000)return new WP_Error('mdg_ai_field_scope_limit','Tek işlemde en fazla 1000 okul güncellenebilir.');

        $result=MMC_Field_Service::bulk_target_status($program_id,$target_ids,sanitize_key($input['status']??''));
        if(is_wp_error($result))return $result;

        return array('updated_count'=>(int)$result,'summary'=>MMC_Field_Service::summary($program_id));
    }
}

if ( ! function_exists( 'mdg_ai_field_routes' ) ) {
    function mdg_ai_field_routes( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $context=array(
            'assigned_user_id'=>absint($input['assigned_user_id']??0),
            'assigned_name'=>sanitize_text_field($input['assigned_name']??'')
        );
        return array('program_id'=>$program_id,'items'=>mdg_ai_field_arr(MMC_Field_Service::routes($program_id,$context)));
    }
}

if ( ! function_exists( 'mdg_ai_field_route_add' ) ) {
    function mdg_ai_field_route_add( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $result=MMC_Field_Service::add_route($program_id,$input);
        if(is_wp_error($result))return $result;

        return array(
            'created'=>true,
            'route_id'=>(int)$result,
            'items'=>mdg_ai_field_arr(MMC_Field_Service::routes($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_field_visits' ) ) {
    function mdg_ai_field_visits( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;
        $limit=max(1,min(200,absint($input['limit']??30)));

        return array(
            'program_id'=>$program_id,
            'visit_statuses'=>MMC_Field_Service::visit_statuses(),
            'items'=>mdg_ai_field_arr(MMC_Field_Service::recent_visits($program_id,$limit))
        );
    }
}

if ( ! function_exists( 'mdg_ai_field_tokens' ) ) {
    function mdg_ai_field_tokens( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $rows=(array)MMC_Field_Service::active_tokens($program_id);
        $items=array();
        foreach($rows as $row){
            $items[]=array(
                'id'=>(int)$row->id,
                'program_id'=>(int)$row->program_id,
                'token_hint'=>(string)$row->token_hint,
                'assigned_user_id'=>(int)$row->assigned_user_id,
                'assigned_name'=>(string)$row->assigned_name,
                'is_active'=>!empty($row->is_active),
                'expires_at'=>(string)$row->expires_at,
                'created_at'=>(string)$row->created_at,
                'updated_at'=>(string)$row->updated_at
            );
        }
        return array('program_id'=>$program_id,'items'=>$items);
    }
}

if ( ! function_exists( 'mdg_ai_field_token_create' ) ) {
    function mdg_ai_field_token_create( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $result=MMC_Field_Service::create_share_token(
            $program_id,
            sanitize_text_field($input['assigned_name']??''),
            absint($input['assigned_user_id']??0),
            max(1,min(90,absint($input['days']??14)))
        );
        if(is_wp_error($result))return $result;

        return array(
            'created'=>true,
            'share'=>$result,
            'warning'=>'Bu URL saha portalına erişim verir; yalnız ilgili personelle paylaşın.'
        );
    }
}

if ( ! function_exists( 'mdg_ai_field_token_revoke' ) ) {
    function mdg_ai_field_token_revoke( $input ) {
        $program_id=absint($input['program_id']??0);
        $token_id=absint($input['token_id']??0);
        $program=mdg_ai_field_program($program_id);
        if(is_wp_error($program))return $program;

        $ok=MMC_Field_Service::revoke_token($token_id,$program_id);
        if(!$ok)return new WP_Error('mdg_ai_field_token_revoke_failed','Saha bağlantısı pasif edilemedi.');

        return array('revoked'=>true,'token_id'=>$token_id);
    }
}

add_action('wp_abilities_api_categories_init',function(){
    if(function_exists('wp_register_ability_category')){
        wp_register_ability_category('madagaskar-saha',array(
            'label'=>'Madagaskar MMC Saha',
            'description'=>'Programa bağlı hedef okullar, saha personeli atamaları, rotalar, ziyaret geçmişi ve paylaşım bağlantıları.'
        ));
    }
});

add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $sync=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true),'public'=>true);
    $write=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);

    $program_schema=array(
        'type'=>'object',
        'properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),
        'required'=>array('program_id')
    );

    wp_register_ability('madagaskar/field-sync-target-schools',array(
        'label'=>'Program Hedef Okullarını Ana Kaynaktan Yenile',
        'description'=>'Programın hedef ilçelerindeki Okul Tanıtım ana listesini MMC saha cache/target kayıtlarına idempotent bağlar; mevcut snapshotları sessizce ezmez.',
        'category'=>'madagaskar-saha',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_sync_targets',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$sync
    ));

    wp_register_ability('madagaskar/field-targets',array(
        'label'=>'MMC Saha Hedef Okullarını Getir',
        'description'=>'Program hedef okullarını durum, ilçe, personel veya arama metnine göre listeler.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'status'=>array('type'=>'string','enum'=>array('planned','assigned','visited','revisit','unavailable','skipped')),
                'district'=>array('type'=>'string'),
                'assigned_name'=>array('type'=>'string'),
                'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
                'search'=>array('type'=>'string'),
                'exclude_skipped'=>array('type'=>'boolean'),
                'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>2000)
            ),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_targets',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/field-summary',array(
        'label'=>'MMC Saha Özetini Getir',
        'description'=>'Hedef, atanmış, ziyaret, tekrar ziyaret, öğrenci kapsamı ve fotoğraflı ziyaret sayılarını getirir.',
        'category'=>'madagaskar-saha',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_summary',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/field-assign-targets',array(
        'label'=>'MMC Hedef Okulları Saha Personeline Ata',
        'description'=>'Seçilen target ID kayıtlarını kullanıcı veya personel adına atar; ziyaret edilmiş okulun durumunu geriye çekmez.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'target_ids'=>array('type'=>'array','items'=>array('type'=>'integer','minimum'=>1),'minItems'=>1,'maxItems'=>1000),
                'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
                'assigned_name'=>array('type'=>'string')
            ),
            'required'=>array('program_id','target_ids')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_assign',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/field-set-scope',array(
        'label'=>'MMC Okul Saha Kapsamını Toplu Değiştir',
        'description'=>'Seçilen hedef okulları Planlandı veya Kapsam Dışı durumuna getirir. Planlandı seçimi mevcut personel atamasını temizler.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'target_ids'=>array('type'=>'array','items'=>array('type'=>'integer','minimum'=>1),'minItems'=>1,'maxItems'=>1000),
                'status'=>array('type'=>'string','enum'=>array('planned','skipped'))
            ),
            'required'=>array('program_id','target_ids','status')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_bulk_scope',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/field-routes',array(
        'label'=>'MMC Saha Rotalarını Getir',
        'description'=>'Programın aktif rota bağlantılarını personele göre filtreleyerek getirir.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
                'assigned_name'=>array('type'=>'string')
            ),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_routes',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/field-route-add',array(
        'label'=>'MMC Saha Rota Bağlantısı Ekle',
        'description'=>'Programa Google Maps, Circuit, Spoke veya başka rota uygulamasının mevcut dış bağlantısını ekler.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'title'=>array('type'=>'string'),
                'route_app'=>array('type'=>'string'),
                'external_url'=>array('type'=>'string'),
                'district_name'=>array('type'=>'string'),
                'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
                'assigned_name'=>array('type'=>'string'),
                'notes'=>array('type'=>'string')
            ),
            'required'=>array('program_id','title','external_url')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_route_add',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/field-recent-visits',array(
        'label'=>'Son MMC Saha Ziyaretlerini Getir',
        'description'=>'Programın son okul ziyaret kayıtlarını okul ve ilçe bilgisiyle getirir.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>200)
            ),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_visits',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/field-share-links',array(
        'label'=>'Saha Paylaşım Bağlantılarını Getir',
        'description'=>'Program için üretilmiş saha portalı token kayıtlarını yalnız ipucu, personel ve süre bilgisiyle getirir; ham token tekrar gösterilmez.',
        'category'=>'madagaskar-saha',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_tokens',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/field-share-link-create',array(
        'label'=>'Saha Personeli Paylaşım Bağlantısı Oluştur',
        'description'=>'Programa/personel kapsamına özel, 1–90 gün geçerli saha portalı URL’si üretir. URL erişim yetkisi taşıdığı için kritik işlemdir.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'assigned_user_id'=>array('type'=>'integer','minimum'=>1),
                'assigned_name'=>array('type'=>'string'),
                'days'=>array('type'=>'integer','minimum'=>1,'maximum'=>90)
            ),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_token_create',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/field-share-link-revoke',array(
        'label'=>'Saha Paylaşım Bağlantısını İptal Et',
        'description'=>'Seçilen saha portalı tokenını pasif ederek bağlantının erişimini kapatır.',
        'category'=>'madagaskar-saha',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'token_id'=>array('type'=>'integer','minimum'=>1)
            ),
            'required'=>array('program_id','token_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_field_token_revoke',
        'permission_callback'=>'mdg_ai_field_can_run',
        'meta'=>$sync
    ));
});
