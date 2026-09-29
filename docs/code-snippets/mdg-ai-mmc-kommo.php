<?php
/**
 * Madagaskar AI — MMC Kommo Güvenli Köprü
 *
 * Secret/token değerlerini hiçbir ability döndürmez.
 * AI kaynak yazma işlemleri, aile paketi standardı tutarsızsa bloke edilir.
 * Doğru standart: family_2_2 = 2 yetişkin + 2 çocuk, kapasite 4.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Secret-source compatibility bridge.
 * Keep the legacy constants untouched, but expose the preferred MMC names
 * without reading, logging or persisting the secret value anywhere else.
 */
if ( ! defined( 'MMC_KOMMO_TOKEN' ) && defined( 'MS_KOMMO_TOKEN' ) ) {
    define( 'MMC_KOMMO_TOKEN', MS_KOMMO_TOKEN );
}
if ( ! defined( 'MMC_KOMMO_BASE_URL' ) && defined( 'MS_KOMMO_BASE_URL' ) ) {
    define( 'MMC_KOMMO_BASE_URL', MS_KOMMO_BASE_URL );
}

if ( ! function_exists( 'mdg_ai_kommo_can_run' ) ) {
    function mdg_ai_kommo_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_kommo_forbidden', 'Yönetici yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Kommo_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_kommo_missing', 'MMC Kommo servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_kommo_program' ) ) {
    function mdg_ai_kommo_program( $program_id ) {
        $program=MMC_Program_Service::get_program(absint($program_id));
        return $program?:new WP_Error('mdg_ai_kommo_program_missing','Program bulunamadı.');
    }
}

if ( ! function_exists( 'mdg_ai_kommo_safe_profile' ) ) {
    function mdg_ai_kommo_safe_profile( $profile ) {
        if(!$profile)return array();
        return array(
            'id'=>(int)$profile->id,
            'program_id'=>(int)$profile->program_id,
            'event_id'=>(int)$profile->event_id,
            'source_hash'=>(string)$profile->source_hash,
            'ai_synced_hash'=>(string)$profile->ai_synced_hash,
            'search_keywords'=>(string)$profile->search_keywords,
            'ai_source_status'=>(string)$profile->ai_source_status,
            'ai_source_id'=>(string)$profile->ai_source_id,
            'crm_status'=>(string)$profile->crm_status,
            'kommo_lead_id'=>(string)$profile->kommo_lead_id,
            'last_synced_at'=>(string)($profile->last_synced_at?:''),
            'last_error'=>(string)($profile->last_error?:''),
            'created_at'=>(string)$profile->created_at,
            'updated_at'=>(string)$profile->updated_at
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_source_session_time_hotfix' ) ) {
    function mdg_ai_kommo_source_session_time_hotfix( $date, $format, $timestamp, $timezone ) {
        if ( 'H:i' !== (string) $format ) {
            return $date;
        }

        foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $frame ) {
            if ( 'MMC_Kommo_Service' === (string) ( $frame['class'] ?? '' )
                && 'build_source_text' === (string) ( $frame['function'] ?? '' ) ) {
                return gmdate( 'H:i', (int) $timestamp );
            }
        }

        return $date;
    }
    add_filter( 'wp_date', 'mdg_ai_kommo_source_session_time_hotfix', 99, 4 );
}

if ( ! function_exists( 'mdg_ai_kommo_source_guard' ) ) {
    function mdg_ai_kommo_source_guard( $program_id ) {
        $program=mdg_ai_kommo_program($program_id);
        if(is_wp_error($program))return $program;

        $text=MMC_Kommo_Service::build_source_text($program_id);
        $normalized_text=remove_accents($text);
        $issues=array();
        $family_detected=false;
        $family_capacity_units=null;
        $family_text_ok=false!==stripos($normalized_text,'2 yetiskin + 2 cocuk');

        if(false!==stripos($normalized_text,'1 yetiskin + 2 cocuk')){
            $issues[]='Kommo kaynak metninde eski "1 yetişkin + 2 çocuk" aile paketi kuralı bulunuyor.';
        }

        if(class_exists('MMC_Event_Service')){
            $event=MMC_Event_Service::event_for_program($program_id);
            if($event){
                foreach((array)MMC_Event_Service::ticket_types($event->id) as $ticket){
                    if((string)$ticket->ticket_code==='family_2_2' && !empty($ticket->is_active)){
                        $family_detected=true;
                        $family_capacity_units=(int)$ticket->capacity_units;
                        if(4!==$family_capacity_units){
                            $issues[]='Aktif family_2_2 aile paketi kapasite tüketimi 4 olmalıdır.';
                        }
                        break;
                    }
                }
            }
        }

        if($family_detected && !$family_text_ok){
            $issues[]='Kommo kaynak metninde "Aile Paketi: 2 yetişkin + 2 çocuk" tanımı bulunmalıdır.';
        }

        $session_times=array();
        $session_times_ok=true;
        if(class_exists('MMC_Event_Service')){
            $event=MMC_Event_Service::event_for_program($program_id);
            if($event){
                foreach((array)MMC_Event_Service::sessions($event->id) as $session){
                    $expected=substr((string)$session->session_time,11,5);
                    if(!$expected)continue;
                    $session_times[]=$expected;
                    if(false===strpos($text,'- '.$expected.' |')){
                        $session_times_ok=false;
                        $issues[]='Kommo kaynak metnindeki seans saatleri MMC etkinlik saatleriyle uyuşmuyor: '.$expected.' bekleniyor.';
                    }
                }
            }
        }

        return array(
            'safe'=>empty($issues),
            'issues'=>$issues,
            'family_package'=>array(
                'ticket_code'=>'family_2_2',
                'expected_definition'=>'2 yetişkin + 2 çocuk',
                'expected_capacity_units'=>4,
                'detected'=>$family_detected,
                'detected_capacity_units'=>$family_capacity_units,
                'source_text_has_definition'=>$family_text_ok
            ),
            'session_times'=>array(
                'expected'=>$session_times,
                'source_ok'=>$session_times_ok
            ),
            'source_hash'=>hash('sha256',$text),
            'source_text'=>$text,
            'transport'=>MMC_Kommo_Service::ai_transport_mode($program_id),
            'keywords'=>MMC_Kommo_Service::search_keywords($program_id)
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_config' ) ) {
    function mdg_ai_kommo_config( $input = array() ) {
        $cfg=MMC_Kommo_Service::configuration_status();
        return array(
            'configured'=>!empty($cfg['configured']),
            'subdomain'=>(string)($cfg['subdomain']??''),
            'subdomain_source'=>(string)($cfg['subdomain_source']??''),
            'token_source'=>(string)($cfg['token_source']??''),
            'uses_legacy_token'=>!empty($cfg['uses_legacy_token']),
            'pipeline_id'=>(int)($cfg['pipeline_id']??0),
            'status_id'=>(int)($cfg['status_id']??0),
            'legacy_pipeline_id'=>(int)($cfg['legacy_pipeline_id']??0),
            'legacy_bilet_field_id'=>(int)($cfg['legacy_bilet_field_id']??0)
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_connection' ) ) {
    function mdg_ai_kommo_connection( $input = array() ) {
        $force=!empty($input['force']);
        return MMC_Kommo_Service::connection_diagnostics($force);
    }
}

if ( ! function_exists( 'mdg_ai_kommo_pipeline' ) ) {
    function mdg_ai_kommo_pipeline( $input = array() ) {
        $force=!empty($input['force']);
        return array(
            'pipeline'=>MMC_Kommo_Service::pipeline_diagnostics($force),
            'blueprint'=>MMC_Kommo_Service::program_pipeline_blueprint(),
            'last_install'=>MMC_Kommo_Service::last_pipeline_install_result()
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_program_status' ) ) {
    function mdg_ai_kommo_program_status( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_kommo_program($program_id);
        if(is_wp_error($program))return $program;

        return array(
            'program_id'=>$program_id,
            'profile'=>mdg_ai_kommo_safe_profile(MMC_Kommo_Service::get_profile($program_id)),
            'queue'=>json_decode(wp_json_encode(MMC_Kommo_Service::queue_health($program_id)),true),
            'transport'=>MMC_Kommo_Service::ai_transport_mode($program_id),
            'direct_text_state'=>MMC_Kommo_Service::direct_text_source_state($program_id)
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_source_check' ) ) {
    function mdg_ai_kommo_source_check( $input ) {
        $program_id=absint($input['program_id']??0);
        return mdg_ai_kommo_source_guard($program_id);
    }
}

if ( ! function_exists( 'mdg_ai_kommo_stage_preview' ) ) {
    function mdg_ai_kommo_stage_preview( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_kommo_program($program_id);
        if(is_wp_error($program))return $program;

        $result=MMC_Kommo_Service::status_bridge_preview($program_id,!empty($input['force']));
        if(is_wp_error($result))return $result;
        return array('program_id'=>$program_id,'preview'=>$result);
    }
}

if ( ! function_exists( 'mdg_ai_kommo_sync_lead' ) ) {
    function mdg_ai_kommo_sync_lead( $input ) {
        $program_id=absint($input['program_id']??0);
        $program=mdg_ai_kommo_program($program_id);
        if(is_wp_error($program))return $program;

        $preview=MMC_Kommo_Service::status_bridge_preview($program_id,true);
        if(is_wp_error($preview))return $preview;
        if(in_array((string)($preview['action']??''),array('pipeline_mismatch','unknown_current_status','non_mmc_status','manual_cancel'),true)){
            return new WP_Error('mdg_ai_kommo_lead_guard','Kommo aşama güvenlik kapısı yazmaya izin vermiyor: '.(string)($preview['reason']??''));
        }

        $result=MMC_Kommo_Service::sync_program_lead($program_id);
        if(is_wp_error($result))return $result;

        return array(
            'synced'=>true,
            'preview_before'=>$preview,
            'profile'=>mdg_ai_kommo_safe_profile(MMC_Kommo_Service::get_profile($program_id))
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_create_text_source' ) ) {
    function mdg_ai_kommo_create_text_source( $input ) {
        $program_id=absint($input['program_id']??0);
        $guard=mdg_ai_kommo_source_guard($program_id);
        if(is_wp_error($guard))return $guard;
        if(empty($guard['safe'])){
            return new WP_Error('mdg_ai_kommo_source_blocked',implode(' ',$guard['issues']));
        }

        $result=MMC_Kommo_Service::create_direct_text_source($program_id);
        if(is_wp_error($result))return $result;

        return array(
            'created_or_current'=>true,
            'result'=>$result,
            'profile'=>mdg_ai_kommo_safe_profile(MMC_Kommo_Service::get_profile($program_id)),
            'direct_text_state'=>MMC_Kommo_Service::direct_text_source_state($program_id)
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_safe_text_source_state_sync' ) ) {
    function mdg_ai_kommo_safe_text_source_state_sync( $program_id ) {
        global $wpdb;

        $program_id=absint($program_id);
        $profile=MMC_Kommo_Service::ensure_profile($program_id);
        if(is_wp_error($profile))return $profile;

        if('text'!==MMC_Kommo_Service::ai_transport_mode($program_id)){
            return MMC_Kommo_Service::sync_ai_source($program_id);
        }

        $state=MMC_Kommo_Service::direct_text_source_state($program_id);
        $table=$wpdb->prefix.'mmc_kommo_profiles';
        $now=current_time('mysql');

        if(empty($state['source_id'])){
            $wpdb->update($table,array(
                'ai_source_status'=>'direct_pending',
                'last_error'=>'',
                'updated_at'=>$now
            ),array('id'=>(int)$profile->id));
            return array('status'=>'direct_pending','external_write'=>false);
        }

        if(hash_equals((string)$state['source_hash'],(string)$profile->source_hash)){
            $wpdb->update($table,array(
                'ai_source_id'=>(string)$state['source_id'],
                'ai_source_status'=>'synced',
                'ai_synced_hash'=>(string)$state['source_hash'],
                'last_error'=>'',
                'updated_at'=>$now
            ),array('id'=>(int)$profile->id));
            return array('status'=>'synced','external_write'=>false);
        }

        $wpdb->update($table,array(
            'ai_source_id'=>(string)$state['source_id'],
            'ai_source_status'=>'refresh_needed',
            'last_error'=>'Kommo AI text source güncellemesi manuel yenileme gerektiriyor; duplicate kaynak oluşturulmadı.',
            'updated_at'=>$now
        ),array('id'=>(int)$profile->id));

        return array(
            'status'=>'refresh_needed',
            'external_write'=>false,
            'source_id'=>(string)$state['source_id']
        );
    }
}

if ( ! function_exists( 'mdg_ai_kommo_preprocess_text_queue' ) ) {
    function mdg_ai_kommo_preprocess_text_queue() {
        global $wpdb;

        if(!class_exists('MMC_Kommo_Service'))return;

        $table=$wpdb->prefix.'mmc_kommo_queue';
        $rows=$wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE job_type='ai_source_sync' AND status='queued' AND available_at<=%s ORDER BY id ASC LIMIT 25",
            current_time('mysql')
        ));

        foreach((array)$rows as $job){
            $program_id=absint($job->program_id);
            if('text'!==MMC_Kommo_Service::ai_transport_mode($program_id))continue;

            $wpdb->update($table,array(
                'status'=>'running',
                'attempts'=>(int)$job->attempts+1,
                'updated_at'=>current_time('mysql')
            ),array('id'=>(int)$job->id));

            $result=mdg_ai_kommo_safe_text_source_state_sync($program_id);
            if(is_wp_error($result)){
                $wpdb->update($table,array(
                    'status'=>'error',
                    'last_error'=>$result->get_error_message(),
                    'processed_at'=>current_time('mysql'),
                    'updated_at'=>current_time('mysql')
                ),array('id'=>(int)$job->id));
                continue;
            }

            $wpdb->update($table,array(
                'status'=>'done',
                'last_error'=>'',
                'processed_at'=>current_time('mysql'),
                'updated_at'=>current_time('mysql')
            ),array('id'=>(int)$job->id));
        }
    }

    add_action('mmc_kommo_process_queue','mdg_ai_kommo_preprocess_text_queue',1);
    add_action('mmc_kommo_process_queue_fast','mdg_ai_kommo_preprocess_text_queue',1);
}

if ( ! function_exists( 'mdg_ai_kommo_sync_ai_source' ) ) {
    function mdg_ai_kommo_sync_ai_source( $input ) {
        $program_id=absint($input['program_id']??0);
        $guard=mdg_ai_kommo_source_guard($program_id);
        if(is_wp_error($guard))return $guard;
        if(empty($guard['safe'])){
            return new WP_Error('mdg_ai_kommo_source_blocked',implode(' ',$guard['issues']));
        }

        $result=mdg_ai_kommo_safe_text_source_state_sync($program_id);
        if(is_wp_error($result))return $result;

        return array(
            'synced_or_marked'=>true,
            'result'=>$result,
            'profile'=>mdg_ai_kommo_safe_profile(MMC_Kommo_Service::get_profile($program_id)),
            'transport'=>MMC_Kommo_Service::ai_transport_mode($program_id)
        );
    }
}

add_action('wp_abilities_api_categories_init',function(){
    if(function_exists('wp_register_ability_category')){
        wp_register_ability_category('madagaskar-kommo',array(
            'label'=>'Madagaskar Kommo',
            'description'=>'Kommo bağlantı tanısı, program lead aşama köprüsü ve güvenlik kapılı AI kaynak senkronu.'
        ));
    }
});

add_action('wp_abilities_api_init',function(){
    if(!function_exists('wp_register_ability'))return;

    $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $inspect=array('annotations'=>array('readonly'=>false,'destructive'=>false,'idempotent'=>true),'public'=>true);
    $external=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true),'public'=>true);

    wp_register_ability('madagaskar/kommo-configuration',array(
        'label'=>'Kommo Yapılandırma Durumunu Getir',
        'description'=>'Subdomain, secret kaynağı türü ve pipeline/status kimliklerini getirir; token değerini asla döndürmez.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array()),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_config',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/kommo-connection-diagnostics',array(
        'label'=>'Kommo Bağlantısını Denetle',
        'description'=>'Kommo hesabına salt-okunur bağlantı testi yapar ve hesap/pipeline tanı bilgilerini döndürür; secret değerini döndürmez.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('force'=>array('type'=>'boolean'))),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_connection',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/kommo-pipeline-diagnostics',array(
        'label'=>'Kommo Pipeline Yapısını Denetle',
        'description'=>'Yapılandırılmış pipeline/status ile MMC program aşama blueprint uyumunu salt-okunur denetler.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('force'=>array('type'=>'boolean'))),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_pipeline',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/kommo-program-status',array(
        'label'=>'Program Kommo Durumunu Getir',
        'description'=>'Program profilinin CRM/AI durumunu, kuyruk sağlığını ve AI taşıma modunu getirir; kaynak tokenı ve tokenlı URL gösterilmez.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_program_status',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/kommo-source-consistency-check',array(
        'label'=>'Kommo AI Kaynak Metnini Denetle',
        'description'=>'Programdan üretilecek Kommo AI metnini önizler; family_2_2 = 2 yetişkin + 2 çocuk ve kapasite 4 standardının tutarlı olduğunu denetler.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_source_check',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$read
    ));

    wp_register_ability('madagaskar/kommo-stage-preview',array(
        'label'=>'Program → Kommo Aşama Hareketini Önizle',
        'description'=>'Program durumu için hedef Kommo aşamasını ve mevcut lead’in ileri/stay/ileride-koru/pipeline-uyuşmazlığı davranışını önizler. Geriye otomatik taşıma yoktur.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array(
            'program_id'=>array('type'=>'integer','minimum'=>1),
            'force'=>array('type'=>'boolean')
        ),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_stage_preview',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$inspect
    ));

    wp_register_ability('madagaskar/kommo-sync-program-lead',array(
        'label'=>'Program Kartını Kommo’ya Senkronla',
        'description'=>'Kommo lead oluşturur/günceller; yalnız güvenli ileri aşama hareketine izin verir, pipeline uyuşmazlığı veya iptalde otomatik yazmaz. Harici CRM yazma işlemidir.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_sync_lead',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$external
    ));

    wp_register_ability('madagaskar/kommo-create-text-source',array(
        'label'=>'Kommo AI Doğrudan Metin Kaynağı Oluştur',
        'description'=>'Kaynak tutarlılık kapısı geçerse Kommo AI text source oluşturur; mevcut güncel kaynak varsa yeniden oluşturmaz. Aile paketi standardı tutarsızsa bloke edilir.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_create_text_source',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$external
    ));

    wp_register_ability('madagaskar/kommo-sync-ai-source',array(
        'label'=>'Kommo AI Kaynak Durumunu Senkronla',
        'description'=>'Kaynak tutarlılık kapısı geçerse URL/text taşıma moduna göre Kommo AI kaynağını oluşturur veya yenileme durumunu işler. Aile paketi standardı tutarsızsa bloke edilir.',
        'category'=>'madagaskar-kommo',
        'input_schema'=>array('type'=>'object','properties'=>array('program_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('program_id')),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_kommo_sync_ai_source',
        'permission_callback'=>'mdg_ai_kommo_can_run',
        'meta'=>$external
    ));
});
