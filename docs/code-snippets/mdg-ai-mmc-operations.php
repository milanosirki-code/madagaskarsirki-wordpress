<?php
/**
 * Madagaskar AI — MMC Operasyon & Lojistik Abilities
 *
 * Staging snippet. MMC_Operations_Service iş kurallarını doğrudan kullanır.
 * Plan güncellemesi ve kaynak atama güncellemeleri mevcut veriyi merge ederek
 * kısmi güncelleme yapar; eksik alanları sıfırlamaz.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_operations_can_run' ) ) {
    function mdg_ai_operations_can_run( $input = null ) {
        if ( ! current_user_can( 'mmc_manage_operations' ) ) {
            return new WP_Error( 'mdg_ai_ops_forbidden', 'Operasyon & Lojistik yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Operations_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_ops_missing', 'MMC operasyon servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_ops_arr' ) ) {
    function mdg_ai_ops_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_ops_program_exists' ) ) {
    function mdg_ai_ops_program_exists( $program_id ) {
        $program = MMC_Program_Service::get_program( absint( $program_id ) );
        return $program ?: new WP_Error( 'mdg_ai_ops_program_missing', 'Program bulunamadı.' );
    }
}

if ( ! function_exists( 'mdg_ai_ops_plan_get' ) ) {
    function mdg_ai_ops_plan_get( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        return array(
            'program'  => mdg_ai_ops_arr( $program ),
            'plan'     => mdg_ai_ops_arr( MMC_Operations_Service::get_plan( $program_id ) ),
            'summary'  => MMC_Operations_Service::summary( $program_id ),
            'modes'    => MMC_Operations_Service::operation_modes(),
            'statuses' => MMC_Operations_Service::plan_statuses(),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_plan_ensure' ) ) {
    function mdg_ai_ops_plan_ensure( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $plan = MMC_Operations_Service::ensure_plan( $program_id );
        if ( is_wp_error( $plan ) ) { return $plan; }

        return array(
            'ensured'  => true,
            'program'  => mdg_ai_ops_arr( $program ),
            'plan'     => mdg_ai_ops_arr( $plan ),
            'summary'  => MMC_Operations_Service::summary( $program_id ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_plan_update' ) ) {
    function mdg_ai_ops_plan_update( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $plan = MMC_Operations_Service::ensure_plan( $program_id );
        if ( is_wp_error( $plan ) ) { return $plan; }

        $fields = array(
            'operation_mode','status','origin_city','next_destination',
            'departure_at','venue_entry_at','setup_start_at','rehearsal_at',
            'doors_open_at','teardown_end_at','return_at',
            'accommodation_required','lodging_name','lodging_address','lodging_rooms',
            'lodging_cost','meal_plan','meal_cost','transport_cost','other_cost','notes',
        );

        $data = array();
        foreach ( $fields as $field ) {
            $data[ $field ] = property_exists( $plan, $field ) ? $plan->$field : '';
            if ( array_key_exists( $field, $input ) ) {
                $data[ $field ] = $input[ $field ];
            }
        }

        $result = MMC_Operations_Service::save_plan( $program_id, $data );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'updated' => true,
            'plan'    => mdg_ai_ops_arr( MMC_Operations_Service::get_plan( $program_id ) ),
            'summary' => MMC_Operations_Service::summary( $program_id ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_summary' ) ) {
    function mdg_ai_ops_summary( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        return array(
            'program_id' => $program_id,
            'summary'    => MMC_Operations_Service::summary( $program_id ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_resources_list' ) ) {
    function mdg_ai_ops_resources_list( $input = array() ) {
        $type = sanitize_key( $input['resource_type'] ?? '' );
        return array(
            'types' => MMC_Operations_Service::resource_types(),
            'items' => mdg_ai_ops_arr( MMC_Operations_Service::resources( $type ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_resource_add' ) ) {
    function mdg_ai_ops_resource_add( $input ) {
        $result = MMC_Operations_Service::add_resource( $input );
        if ( is_wp_error( $result ) ) { return $result; }
        return array( 'created_or_existing' => true, 'resource_id' => (int) $result );
    }
}

if ( ! function_exists( 'mdg_ai_ops_program_resources' ) ) {
    function mdg_ai_ops_program_resources( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        return array(
            'program_id' => $program_id,
            'statuses'   => MMC_Operations_Service::assignment_statuses(),
            'items'      => mdg_ai_ops_arr( MMC_Operations_Service::program_resources( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_resource_assign' ) ) {
    function mdg_ai_ops_resource_assign( $input ) {
        $program_id  = absint( $input['program_id'] ?? 0 );
        $resource_id = absint( $input['resource_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $result = MMC_Operations_Service::assign_resource( $program_id, $resource_id, $input );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'assigned'      => true,
            'assignment_id' => (int) $result,
            'items'         => mdg_ai_ops_arr( MMC_Operations_Service::program_resources( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_assignment_update' ) ) {
    function mdg_ai_ops_assignment_update( $input ) {
        global $wpdb;

        $program_id    = absint( $input['program_id'] ?? 0 );
        $assignment_id = absint( $input['assignment_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $current = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mmc_program_resources WHERE id=%d AND program_id=%d LIMIT 1",
            $assignment_id,
            $program_id
        ) );
        if ( ! $current ) {
            return new WP_Error( 'mdg_ai_ops_assignment_missing', 'Kaynak ataması bulunamadı.' );
        }

        $data = array(
            'role_name'    => (string) $current->role_name,
            'quantity'     => (int) $current->quantity,
            'status'       => (string) $current->status,
            'notes'        => (string) $current->notes,
            'check_in_at'  => (string) ( $current->check_in_at ?: '' ),
            'check_out_at' => (string) ( $current->check_out_at ?: '' ),
        );
        foreach ( array_keys( $data ) as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $data[ $field ] = $input[ $field ];
            }
        }

        $result = MMC_Operations_Service::update_assignment( $program_id, $assignment_id, $data );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'updated' => true,
            'items'   => mdg_ai_ops_arr( MMC_Operations_Service::program_resources( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_checklist_list' ) ) {
    function mdg_ai_ops_checklist_list( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $phase      = sanitize_key( $input['phase'] ?? '' );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        return array(
            'program_id' => $program_id,
            'phases'     => MMC_Operations_Service::phases(),
            'statuses'   => MMC_Operations_Service::checklist_statuses(),
            'items'      => mdg_ai_ops_arr( MMC_Operations_Service::checklist( $program_id, $phase ) ),
            'summary'    => MMC_Operations_Service::summary( $program_id ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_checklist_update' ) ) {
    function mdg_ai_ops_checklist_update( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $rows = array();
        foreach ( (array) ( $input['rows'] ?? array() ) as $row ) {
            $id = absint( $row['id'] ?? 0 );
            if ( ! $id ) { continue; }
            $rows[ $id ] = array(
                'status'        => sanitize_key( $row['status'] ?? 'pending' ),
                'assigned_name' => sanitize_text_field( $row['assigned_name'] ?? '' ),
                'notes'         => sanitize_textarea_field( $row['notes'] ?? '' ),
            );
        }
        if ( ! $rows ) {
            return new WP_Error( 'mdg_ai_ops_checklist_rows', 'Güncellenecek kontrol satırı verilmedi.' );
        }

        $result = MMC_Operations_Service::save_checklist_rows( $program_id, $rows );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'updated' => true,
            'items'   => mdg_ai_ops_arr( MMC_Operations_Service::checklist( $program_id ) ),
            'summary' => MMC_Operations_Service::summary( $program_id ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_schedule_list' ) ) {
    function mdg_ai_ops_schedule_list( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        return array(
            'program_id' => $program_id,
            'items'      => mdg_ai_ops_arr( MMC_Operations_Service::schedule( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_schedule_sync' ) ) {
    function mdg_ai_ops_schedule_sync( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $result = MMC_Operations_Service::sync_schedule_from_event( $program_id );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'synced' => true,
            'items'  => mdg_ai_ops_arr( MMC_Operations_Service::schedule( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_schedule_add' ) ) {
    function mdg_ai_ops_schedule_add( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $result = MMC_Operations_Service::add_schedule_item( $program_id, $input );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'created'     => true,
            'schedule_id' => (int) $result,
            'items'       => mdg_ai_ops_arr( MMC_Operations_Service::schedule( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_ops_schedule_status' ) ) {
    function mdg_ai_ops_schedule_status( $input ) {
        $program_id  = absint( $input['program_id'] ?? 0 );
        $schedule_id = absint( $input['schedule_id'] ?? 0 );
        $status      = sanitize_key( $input['status'] ?? 'planned' );
        $program = mdg_ai_ops_program_exists( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $result = MMC_Operations_Service::update_schedule_status( $program_id, $schedule_id, $status );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'updated' => true,
            'items'   => mdg_ai_ops_arr( MMC_Operations_Service::schedule( $program_id ) ),
        );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-operasyon',
            array(
                'label'       => 'Madagaskar Operasyon & Lojistik',
                'description' => 'MMC operasyon planı, kaynaklar, kontrol listesi ve gösteri günü akışı.',
            )
        );
    }
} );

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) { return; }

    $read = array(
        'annotations' => array( 'readonly'=>true, 'destructive'=>false, 'idempotent'=>true ),
        'public' => true,
    );
    $ensure = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>true ),
        'public' => true,
    );
    $write = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>false ),
        'public' => true,
    );
    $write_idempotent = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>true ),
        'public' => true,
    );

    $program_schema = array(
        'type'=>'object',
        'properties'=>array( 'program_id'=>array('type'=>'integer','minimum'=>1) ),
        'required'=>array('program_id'),
    );

    wp_register_ability( 'madagaskar/operations-plan-get', array(
        'label'=>'Operasyon Planını Getir',
        'description'=>'Programın operasyon planı, durum seçenekleri ve hazırlık özetini getirir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_plan_get',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/operations-plan-ensure', array(
        'label'=>'Operasyon Planını Oluştur/Garanti Et',
        'description'=>'Program için operasyon planı, varsayılan kontrol listesi ve operasyon görevini idempotent biçimde oluşturur.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_plan_ensure',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$ensure,
    ) );

    wp_register_ability( 'madagaskar/operations-plan-update', array(
        'label'=>'Operasyon Planını Güncelle',
        'description'=>'Operasyon tipi, hareket/salon/prova saatleri, konaklama, yemek ve bütçeleri kısmi günceller. Kritik yazma işlemidir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'operation_mode'=>array('type'=>'string','enum'=>array('undecided','daytrip','overnight','multi_city')),
                'status'=>array('type'=>'string','enum'=>array('draft','planned','ready','in_progress','completed','cancelled')),
                'origin_city'=>array('type'=>'string'),
                'next_destination'=>array('type'=>'string'),
                'departure_at'=>array('type'=>'string'),
                'venue_entry_at'=>array('type'=>'string'),
                'setup_start_at'=>array('type'=>'string'),
                'rehearsal_at'=>array('type'=>'string'),
                'doors_open_at'=>array('type'=>'string'),
                'teardown_end_at'=>array('type'=>'string'),
                'return_at'=>array('type'=>'string'),
                'accommodation_required'=>array('type'=>'boolean'),
                'lodging_name'=>array('type'=>'string'),
                'lodging_address'=>array('type'=>'string'),
                'lodging_rooms'=>array('type'=>'integer','minimum'=>0),
                'lodging_cost'=>array('type'=>'number','minimum'=>0),
                'meal_plan'=>array('type'=>'string'),
                'meal_cost'=>array('type'=>'number','minimum'=>0),
                'transport_cost'=>array('type'=>'number','minimum'=>0),
                'other_cost'=>array('type'=>'number','minimum'=>0),
                'notes'=>array('type'=>'string'),
            ),
            'required'=>array('program_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_plan_update',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write,
    ) );

    wp_register_ability( 'madagaskar/operations-summary', array(
        'label'=>'Operasyon Hazırlık Özetini Getir',
        'description'=>'Kontrol listesi tamamlanma oranı, sorun, araç, sanatçı, personel ve ekipman sayılarını getirir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_summary',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/operations-resources-list', array(
        'label'=>'Operasyon Kaynaklarını Listele',
        'description'=>'Aktif araç, personel, sanatçı, ekipman ve hizmet kaynaklarını listeler.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'resource_type'=>array('type'=>'string','enum'=>array('vehicle','person','artist','equipment','service')),
            ),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_resources_list',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/operations-resource-add', array(
        'label'=>'Operasyon Kaynağı Ekle',
        'description'=>'Kaynak ana kaydına araç, personel, sanatçı, ekipman veya hizmet ekler; mükerrerse mevcut kaydı döndürür.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'resource_type'=>array('type'=>'string','enum'=>array('vehicle','person','artist','equipment','service')),
                'resource_name'=>array('type'=>'string'),
                'subtype'=>array('type'=>'string'),
                'identifier'=>array('type'=>'string'),
                'country'=>array('type'=>'string'),
                'notes'=>array('type'=>'string'),
            ),
            'required'=>array('resource_type','resource_name'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_resource_add',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$ensure,
    ) );

    wp_register_ability( 'madagaskar/operations-program-resources', array(
        'label'=>'Programa Atanan Kaynakları Getir',
        'description'=>'Programdaki araç, personel, sanatçı, ekipman ve hizmet atamalarını getirir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_program_resources',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/operations-resource-assign', array(
        'label'=>'Operasyon Kaynağını Programa Ata',
        'description'=>'Mevcut kaynak ana kaydını programa görev/adet/durum ile atar veya mevcut atamayı günceller. Kritik yazma işlemidir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'resource_id'=>array('type'=>'integer','minimum'=>1),
                'role_name'=>array('type'=>'string'),
                'quantity'=>array('type'=>'integer','minimum'=>1),
                'status'=>array('type'=>'string','enum'=>array('planned','confirmed','checked_in','completed','cancelled')),
                'notes'=>array('type'=>'string'),
            ),
            'required'=>array('program_id','resource_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_resource_assign',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write,
    ) );

    wp_register_ability( 'madagaskar/operations-assignment-update', array(
        'label'=>'Operasyon Kaynak Atamasını Güncelle',
        'description'=>'Kaynak atamasını kısmi olarak günceller; belirtilmeyen görev/adet/not/giriş-çıkış alanlarını korur.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'assignment_id'=>array('type'=>'integer','minimum'=>1),
                'role_name'=>array('type'=>'string'),
                'quantity'=>array('type'=>'integer','minimum'=>1),
                'status'=>array('type'=>'string','enum'=>array('planned','confirmed','checked_in','completed','cancelled')),
                'notes'=>array('type'=>'string'),
                'check_in_at'=>array('type'=>'string'),
                'check_out_at'=>array('type'=>'string'),
            ),
            'required'=>array('program_id','assignment_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_assignment_update',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write,
    ) );

    wp_register_ability( 'madagaskar/operations-checklist-list', array(
        'label'=>'Operasyon Kontrol Listesini Getir',
        'description'=>'Hareket öncesi, salon kurulumu, gösteri günü ve gösteri sonrası kontrol maddelerini getirir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'phase'=>array('type'=>'string','enum'=>array('pre_departure','venue_setup','show_day','post_show')),
            ),
            'required'=>array('program_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_checklist_list',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/operations-checklist-update', array(
        'label'=>'Operasyon Kontrol Listesini Güncelle',
        'description'=>'Açıkça verilen kontrol maddelerinin durum, sorumlu ve not alanlarını günceller. Tamamlanma program durumunu tetikleyebilir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'rows'=>array(
                    'type'=>'array','minItems'=>1,'maxItems'=>100,
                    'items'=>array(
                        'type'=>'object',
                        'properties'=>array(
                            'id'=>array('type'=>'integer','minimum'=>1),
                            'status'=>array('type'=>'string','enum'=>array('pending','ready','done','not_applicable','problem')),
                            'assigned_name'=>array('type'=>'string'),
                            'notes'=>array('type'=>'string'),
                        ),
                        'required'=>array('id','status'),
                    ),
                ),
            ),
            'required'=>array('program_id','rows'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_checklist_update',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write,
    ) );

    wp_register_ability( 'madagaskar/operations-schedule-list', array(
        'label'=>'Operasyon Akış Planını Getir',
        'description'=>'Hareket, giriş, kurulum, prova, seans, söküm ve dönüş akışını getirir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_schedule_list',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/operations-schedule-sync', array(
        'label'=>'Operasyon Akışını Etkinlikten Yenile',
        'description'=>'Etkinlik/seans ve operasyon planı saatlerinden sistem akışını idempotent biçimde yeniler.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_schedule_sync',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write_idempotent,
    ) );

    wp_register_ability( 'madagaskar/operations-schedule-add', array(
        'label'=>'Manuel Operasyon Akış Kalemi Ekle',
        'description'=>'Transfer, yemek, prova, kurulum veya diğer manuel akış kalemini ekler.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'title'=>array('type'=>'string'),
                'item_type'=>array('type'=>'string'),
                'start_at'=>array('type'=>'string'),
                'end_at'=>array('type'=>'string'),
                'assigned_name'=>array('type'=>'string'),
                'notes'=>array('type'=>'string'),
            ),
            'required'=>array('program_id','title'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_schedule_add',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write,
    ) );

    wp_register_ability( 'madagaskar/operations-schedule-status', array(
        'label'=>'Operasyon Akış Durumunu Güncelle',
        'description'=>'Tek akış kalemini planlandı, hazır, tamam veya iptal durumuna getirir.',
        'category'=>'madagaskar-operasyon',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'schedule_id'=>array('type'=>'integer','minimum'=>1),
                'status'=>array('type'=>'string','enum'=>array('planned','ready','done','cancelled')),
            ),
            'required'=>array('program_id','schedule_id','status'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_ops_schedule_status',
        'permission_callback'=>'mdg_ai_operations_can_run',
        'meta'=>$write,
    ) );
} );
