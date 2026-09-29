<?php
/**
 * Madagaskar AI — Sistem Sağlığı ve Program Bütünlüğü
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_health_can_run' ) ) {
    function mdg_ai_health_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'mdg_ai_health_forbidden', 'Yönetici yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Health_Service' ) || ! class_exists( 'MMC_Integrity_Service' ) ) {
            return new WP_Error( 'mdg_ai_health_missing', 'MMC sağlık/bütünlük servisleri kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_health_checks' ) ) {
    function mdg_ai_health_checks( $input = array() ) {
        return array(
            'summary' => MMC_Health_Service::summary(),
            'checks'  => MMC_Health_Service::checks(),
        );
    }
}

if ( ! function_exists( 'mdg_ai_integrity_selected_venue_resolved' ) ) {
    function mdg_ai_integrity_selected_venue_resolved( $program_id ) {
        $venue = MMC_Integrity_Service::selected_venue( $program_id );
        if ( $venue || ! class_exists( 'MMC_Venue_Service' ) || ! method_exists( 'MMC_Venue_Service', 'venues_for_program' ) ) {
            return $venue;
        }

        foreach ( (array) MMC_Venue_Service::venues_for_program( $program_id ) as $candidate ) {
            if ( (int) ( $candidate->is_selected ?? 0 ) === 1 ) {
                return $candidate;
            }
        }
        return null;
    }
}

if ( ! function_exists( 'mdg_ai_integrity_checks' ) ) {
    function mdg_ai_integrity_checks( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        if ( ! $program_id || ! class_exists( 'MMC_Program_Service' ) || ! MMC_Program_Service::get_program( $program_id ) ) {
            return new WP_Error( 'mdg_ai_integrity_program_missing', 'Program bulunamadı.' );
        }

        $checks = MMC_Integrity_Service::checks( $program_id );
        $venue  = mdg_ai_integrity_selected_venue_resolved( $program_id );

        if ( $venue ) {
            foreach ( $checks as &$row ) {
                if ( 'venue' !== (string) ( $row['key'] ?? '' ) ) { continue; }
                $row['severity'] = 'approved' === (string) ( $venue->allocation_status ?? '' ) ? 'ok' : 'warning';
                $row['detail']   = (string) ( $venue->venue_name ?? 'Salon' ) .
                    ' · Tahsis: ' . (string) ( $venue->allocation_status ?? '' ) .
                    ' · program_id=' . $program_id;
                break;
            }
            unset( $row );
        }

        $summary = array( 'ok'=>0, 'warning'=>0, 'critical'=>0, 'info'=>0 );
        foreach ( $checks as $row ) {
            $severity = (string) ( $row['severity'] ?? '' );
            if ( isset( $summary[ $severity ] ) ) { $summary[ $severity ]++; }
        }

        return array(
            'program_id' => $program_id,
            'summary'    => $summary,
            'checks'     => $checks,
        );
    }
}

if ( ! function_exists( 'mdg_ai_integrity_selected_venue' ) ) {
    function mdg_ai_integrity_selected_venue( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        if ( ! $program_id ) {
            return new WP_Error( 'mdg_ai_integrity_program_required', 'program_id zorunludur.' );
        }
        $venue = mdg_ai_integrity_selected_venue_resolved( $program_id );
        return array(
            'program_id' => $program_id,
            'venue'      => json_decode( wp_json_encode( $venue ), true ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_integrity_repair_school_bridge' ) ) {
    function mdg_ai_integrity_repair_school_bridge( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        if ( ! $program_id ) {
            return new WP_Error( 'mdg_ai_integrity_program_required', 'program_id zorunludur.' );
        }

        $result = MMC_Integrity_Service::repair_school_bridge( $program_id );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'repaired'   => true,
            'program_id' => $program_id,
            'bridge'     => json_decode( wp_json_encode( $result ), true ),
            'summary'    => MMC_Integrity_Service::summary( $program_id ),
            'checks'     => MMC_Integrity_Service::checks( $program_id ),
        );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-saglik',
            array(
                'label'       => 'Madagaskar Sistem Sağlığı',
                'description' => 'WordPress/WooCommerce/Tickera/PayTR, veri kaynakları ve program zinciri bütünlük kontrolleri.',
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
    $repair = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>true ),
        'public' => true,
    );

    wp_register_ability( 'madagaskar/system-health-checks', array(
        'label'       => 'Sistem Sağlığını Denetle',
        'description' => 'WordPress, WooCommerce, Tickera, PayTR, salon/okul/nüfus kaynakları, Kommo, gece raporu ve MMC veritabanını salt okunur denetler.',
        'category'    => 'madagaskar-saglik',
        'input_schema'=> array( 'type'=>'object','properties'=>array() ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_health_checks',
        'permission_callback' => 'mdg_ai_health_can_run',
        'meta'                => $read,
    ) );

    wp_register_ability( 'madagaskar/program-integrity-checks', array(
        'label'       => 'Program Bütünlüğünü Denetle',
        'description' => 'Tek programın etkinlik/salon, MDG köprüsü, satış mutabakatı, okul/saha, Kommo, operasyon ve finans zincirini salt okunur denetler.',
        'category'    => 'madagaskar-saglik',
        'input_schema'=> array(
            'type'=>'object',
            'properties'=>array( 'program_id'=>array('type'=>'integer','minimum'=>1) ),
            'required'=>array('program_id'),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_integrity_checks',
        'permission_callback' => 'mdg_ai_health_can_run',
        'meta'                => $read,
    ) );

    wp_register_ability( 'madagaskar/program-selected-venue-check', array(
        'label'       => 'Program Kesin Salon Kaydını Kontrol Et',
        'description' => 'Program bütünlük servisinin gördüğü kesin salon kaydını salt okunur döndürür.',
        'category'    => 'madagaskar-saglik',
        'input_schema'=> array(
            'type'=>'object',
            'properties'=>array( 'program_id'=>array('type'=>'integer','minimum'=>1) ),
            'required'=>array('program_id'),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_integrity_selected_venue',
        'permission_callback' => 'mdg_ai_health_can_run',
        'meta'                => $read,
    ) );

    wp_register_ability( 'madagaskar/program-repair-school-bridge', array(
        'label'       => 'Program–Okul Tanıtım Köprüsünü Onar',
        'description' => 'Mevcut MMC bütünlük servisinin güvenli onarım metoduyla Okul Tanıtım program köprüsünü oluşturur/onarır ve yeniden denetler.',
        'category'    => 'madagaskar-saglik',
        'input_schema'=> array(
            'type'=>'object',
            'properties'=>array( 'program_id'=>array('type'=>'integer','minimum'=>1) ),
            'required'=>array('program_id'),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_integrity_repair_school_bridge',
        'permission_callback' => 'mdg_ai_health_can_run',
        'meta'                => $repair,
    ) );
} );
