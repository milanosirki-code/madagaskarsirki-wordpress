<?php
/**
 * Madagaskar AI — MMC Bölge / Nüfus Analizi
 *
 * Nüfus için tek kaynak kuralı korunur: MMC_Population_Source_Service hazırsa
 * population_total manuel eklenemez.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_region_can_run' ) ) {
    function mdg_ai_region_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_region_forbidden', 'Yönetici yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Region_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_region_missing', 'MMC bölge servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_region_arr' ) ) {
    function mdg_ai_region_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_region_sources' ) ) {
    function mdg_ai_region_sources( $input = array() ) {
        return array(
            'warehouse'  => MMC_Region_Service::warehouse_counts(),
            'population' => class_exists('MMC_Population_Source_Service') ? MMC_Population_Source_Service::info() : array('available'=>false,'ready'=>false),
            'school'     => class_exists('MMC_School_Source_Service') ? MMC_School_Source_Service::source_info() : array('external'=>false),
            'metric_labels'=>MMC_Region_Service::metric_labels(),
        );
    }
}

if ( ! function_exists( 'mdg_ai_region_districts' ) ) {
    function mdg_ai_region_districts( $input ) {
        $province=sanitize_text_field($input['province']??'');
        if(!$province)return new WP_Error('mdg_ai_region_province_required','İl adı zorunludur.');
        return array(
            'province'=>MMC_Region_Service::normalize_place_name($province),
            'districts'=>MMC_Region_Service::known_districts($province)
        );
    }
}

if ( ! function_exists( 'mdg_ai_region_program_summary' ) ) {
    function mdg_ai_region_program_summary( $input ) {
        $program_id=absint($input['program_id']??0);
        if(!MMC_Program_Service::get_program($program_id))return new WP_Error('mdg_ai_region_program_missing','Program bulunamadı.');
        return array(
            'summary'=>mdg_ai_region_arr(MMC_Region_Service::program_summary($program_id)),
            'target_rows'=>mdg_ai_region_arr(MMC_Region_Service::get_program_target_rows($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_region_set_targets' ) ) {
    function mdg_ai_region_set_targets( $input ) {
        $program_id=absint($input['program_id']??0);
        if(!MMC_Program_Service::get_program($program_id))return new WP_Error('mdg_ai_region_program_missing','Program bulunamadı.');

        $districts=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($input['districts']??array())))));
        $result=MMC_Region_Service::set_program_targets($program_id,$districts);
        if(is_wp_error($result))return $result;

        return array(
            'updated'=>true,
            'districts'=>$result,
            'summary'=>mdg_ai_region_arr(MMC_Region_Service::program_summary($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_region_ensure_primary_district' ) ) {
    function mdg_ai_region_ensure_primary_district( $input ) {
        global $wpdb;

        $program_id=absint($input['program_id']??0);
        $program=MMC_Program_Service::get_program($program_id);
        if(!$program)return new WP_Error('mdg_ai_region_program_missing','Program bulunamadı.');

        $district=MMC_Region_Service::normalize_place_name((string)$program->district_name);
        if(!$district)return new WP_Error('mdg_ai_region_primary_missing','Programın ana ilçesi bulunamadı.');

        $known=array_map(array('MMC_Region_Service','normalize_place_name'),(array)MMC_Region_Service::known_districts($program->province_name));
        if($known && !in_array($district,$known,true)){
            return new WP_Error('mdg_ai_region_primary_unknown','Programın ana ilçesi bölge kaynaklarında doğrulanamadı.');
        }

        $table=$wpdb->prefix.'mmc_program_target_districts';
        $existing=$wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE program_id=%d AND district_name=%s ORDER BY id ASC LIMIT 1",
            $program_id,$district
        ));
        if($existing){
            return array(
                'created'=>false,
                'program_status'=>(string)$program->status,
                'target'=>mdg_ai_region_arr($existing),
                'summary'=>mdg_ai_region_arr(MMC_Region_Service::program_summary($program_id))
            );
        }

        $population=class_exists('MMC_Population_Source_Service') && MMC_Population_Source_Service::ready()
            ? MMC_Population_Source_Service::district($program->province_name,$district)
            : null;
        $source_info=class_exists('MMC_Population_Source_Service') ? MMC_Population_Source_Service::info() : array();
        $now=current_time('mysql');

        $ok=$wpdb->insert($table,array(
            'program_id'=>$program_id,
            'province_name'=>(string)$program->province_name,
            'district_name'=>$district,
            'is_primary'=>1,
            'is_selected'=>1,
            'population_snapshot'=>$population ? (int)$population['population'] : null,
            'population_year_snapshot'=>$population ? (int)$population['data_year'] : null,
            'population_source_snapshot'=>$population ? (string)($population['source_name']??($source_info['source']??'')) : '',
            'population_snapshot_at'=>$population ? $now : null,
            'created_by'=>get_current_user_id() ?: (int)$program->created_by,
            'created_at'=>$now
        ),array('%d','%s','%s','%d','%d','%d','%d','%s','%s','%d','%s'));

        if(false===$ok)return new WP_Error('mdg_ai_region_primary_insert','Ana hedef ilçe kaydı oluşturulamadı.');

        $id=(int)$wpdb->insert_id;
        MMC_Program_Service::add_log(
            $program_id,
            'target_primary_district_ensured',
            'program',
            $program_id,
            null,
            array('district'=>$district,'target_row_id'=>$id),
            'Eksik ana hedef ilçe kaydı program yaşam döngüsü değiştirilmeden tamamlandı.'
        );

        return array(
            'created'=>true,
            'program_status'=>(string)MMC_Program_Service::get_program($program_id)->status,
            'target'=>mdg_ai_region_arr($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id))),
            'summary'=>mdg_ai_region_arr(MMC_Region_Service::program_summary($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_region_metric_add' ) ) {
    function mdg_ai_region_metric_add( $input ) {
        $result=MMC_Region_Service::add_metric($input);
        if(is_wp_error($result))return $result;
        return array('created'=>true,'metric_id'=>(int)$result);
    }
}

if ( ! function_exists( 'mdg_ai_population_lookup' ) ) {
    function mdg_ai_population_lookup( $input ) {
        if(!class_exists('MMC_Population_Source_Service')||!MMC_Population_Source_Service::available()){
            return new WP_Error('mdg_ai_population_unavailable','Madagaskar Nüfus Verisi kaynağı kullanılamıyor.');
        }
        $province=sanitize_text_field($input['province']??'');
        $district=sanitize_text_field($input['district']??'');
        if(!$province)return new WP_Error('mdg_ai_population_province_required','İl adı zorunludur.');

        return array(
            'source'=>MMC_Population_Source_Service::info(),
            'province'=>MMC_Population_Source_Service::province($province),
            'district'=>$district?MMC_Population_Source_Service::district($province,$district):null,
            'districts'=>$district?array():MMC_Population_Source_Service::districts($province)
        );
    }
}

if ( ! function_exists( 'mdg_ai_population_targets' ) ) {
    function mdg_ai_population_targets( $input ) {
        if(!class_exists('MMC_Population_Source_Service')||!MMC_Population_Source_Service::ready()){
            return new WP_Error('mdg_ai_population_not_ready','Nüfus kaynağı hazır değil.');
        }
        $province=sanitize_text_field($input['province']??'');
        $districts=array_values(array_unique(array_filter(array_map('sanitize_text_field',(array)($input['districts']??array())))));
        if(!$province||!$districts)return new WP_Error('mdg_ai_population_targets_required','İl ve en az bir ilçe zorunludur.');
        return array(
            'summary'=>MMC_Population_Source_Service::target_summary($province,$districts)
        );
    }
}

if ( ! function_exists( 'mdg_ai_region_recent_imports' ) ) {
    function mdg_ai_region_recent_imports( $input = array() ) {
        $limit=max(1,min(50,absint($input['limit']??10)));
        return array('items'=>mdg_ai_region_arr(MMC_Region_Service::recent_imports($limit)));
    }
}

add_action('wp_abilities_api_categories_init',function(){
    if(function_exists('wp_register_ability_category')){
        wp_register_ability_category('madagaskar-bolge-nufus',array(
            'label'=>'Madagaskar Bölge / Nüfus',
            'description'=>'Tanıtım havzası, nüfus, okul ve doğrulanmış bölge metrikleri.'
        ));
    }
});

add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $write=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);
    $create=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);

    wp_register_ability('madagaskar/region-sources',array(
        'label'=>'Bölge Veri Kaynaklarını Getir',
        'description'=>'Nüfus, okul ve ek metrik veri ambarının hazır olma ve kapsam durumunu getirir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array('type'=>'object','properties'=>array()),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_sources',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/region-districts',array(
        'label'=>'İlin Bilinen İlçelerini Getir',
        'description'=>'Metrik, okul ve nüfus kaynaklarını birleştirerek il için bilinen ilçeleri getirir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array('type'=>'object','properties'=>array('province'=>array('type'=>'string')),'required'=>array('province')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_districts',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/region-program-summary',array(
        'label'=>'Program Bölge Analizini Getir',
        'description'=>'Programın hedef ilçelerini, nüfus snapshotlarını, okul/öğrenci kapsamını ve doğrulanmış bölge metriklerini getirir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_program_summary',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/region-set-target-districts',array(
        'label'=>'Program Tanıtım Havzasını Güncelle',
        'description'=>'Programın hedef ilçe listesini tamamen yeniler, nüfus snapshotlarını alır ve programı Bölge Analizi durumuna geçirir. Kritik yazma işlemidir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'districts'=>array('type'=>'array','items'=>array('type'=>'string'),'maxItems'=>100)
            ),
            'required'=>array('program_id','districts')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_set_targets',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$write
    ));

    wp_register_ability('madagaskar/region-ensure-primary-district',array(
        'label'=>'Program Ana Hedef İlçesini Tamamla',
        'description'=>'Programın kendi ilçesi için eksik hedef-ilçe kaydını nüfus snapshotıyla idempotent oluşturur; mevcut hedefleri silmez ve program yaşam döngüsü durumunu değiştirmez.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),
            'required'=>array('program_id')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_ensure_primary_district',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>array(
            'annotations'=>array('readonly'=>false,'destructive'=>false,'idempotent'=>true),
            'public'=>true
        )
    ));

    wp_register_ability('madagaskar/region-add-metric',array(
        'label'=>'Doğrulanmış Bölge Metriği Ekle',
        'description'=>'Kaynak kurum/yıl bilgisiyle desteklenen ek bölge metriği ekler. Nüfus ana kaynağı hazırsa population_total ikinci kez eklenemez.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'province_name'=>array('type'=>'string'),
                'district_name'=>array('type'=>'string'),
                'metric_key'=>array('type'=>'string'),
                'metric_value'=>array('type'=>'number'),
                'unit'=>array('type'=>'string'),
                'data_year'=>array('type'=>'string'),
                'source_org'=>array('type'=>'string'),
                'source_name'=>array('type'=>'string'),
                'source_url'=>array('type'=>'string'),
                'verified_at'=>array('type'=>'string')
            ),
            'required'=>array('province_name','metric_key','metric_value','data_year','source_org')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_metric_add',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$create
    ));

    wp_register_ability('madagaskar/population-lookup',array(
        'label'=>'Nüfus Verisini Getir',
        'description'=>'Madagaskar Nüfus Verisi ana kaynağından il veya ilçe nüfusunu ve kaynak yılını getirir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array('province'=>array('type'=>'string'),'district'=>array('type'=>'string')),
            'required'=>array('province')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_population_lookup',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/population-target-summary',array(
        'label'=>'Hedef İlçelerin Nüfusunu Topla',
        'description'=>'Seçilen tanıtım ilçelerinin nüfus toplamını, kapsanan ilçe sayısını, yılı ve kaynağı getirir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'province'=>array('type'=>'string'),
                'districts'=>array('type'=>'array','items'=>array('type'=>'string'),'minItems'=>1,'maxItems'=>100)
            ),
            'required'=>array('province','districts')
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_population_targets',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/region-recent-imports',array(
        'label'=>'Son Bölge Veri İçe Aktarımlarını Getir',
        'description'=>'MMC bölge veri ambarındaki son CSV içe aktarma kayıtlarını salt okunur getirir.',
        'category'=>'madagaskar-bolge-nufus',
        'input_schema'=>array('type'=>'object','properties'=>array('limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>50))),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_region_recent_imports',
        'permission_callback'=>'mdg_ai_region_can_run',
        'meta'=>$read
    ));
});
