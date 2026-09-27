<?php
/**
 * Madagaskar AI — MMC ↔ MDG Etkinlik Köprüsü
 *
 * Staging snippet.
 * Aday bulma ve önizleme salt-okunur.
 * Otomatik bağlantı yalnız MMC_MDG_Bridge_Service::auto_link() güvenlik kuralıyla,
 * yani tam bir güçlü aday olduğunda çalışır.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_bridge_can_run' ) ) {
    function mdg_ai_bridge_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_bridge_forbidden', 'WooCommerce yönetim yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_MDG_Bridge_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
            return new WP_Error( 'mdg_ai_bridge_missing', 'MMC ↔ MDG köprü servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_bridge_arr' ) ) {
    function mdg_ai_bridge_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_program' ) ) {
    function mdg_ai_bridge_program( $program_id ) {
        $program = MMC_Program_Service::get_program( absint( $program_id ) );
        return $program ?: new WP_Error( 'mdg_ai_bridge_program_missing', 'MMC programı bulunamadı.' );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_status' ) ) {
    function mdg_ai_bridge_status( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_bridge_program( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        return array(
            'program' => mdg_ai_bridge_arr( $program ),
            'status'  => mdg_ai_bridge_arr( MMC_MDG_Bridge_Service::status( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_candidates' ) ) {
    function mdg_ai_bridge_candidates( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_bridge_program( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $items = (array) MMC_MDG_Bridge_Service::candidates( $program_id );
        $strong = array_values( array_filter( $items, static function( $row ) {
            return ! empty( $row['strong'] );
        } ) );

        return array(
            'program_id'        => $program_id,
            'count'             => count( $items ),
            'strong_count'      => count( $strong ),
            'auto_link_safe'    => 1 === count( $strong ),
            'strong_candidates' => $strong,
            'items'             => $items,
        );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_publish_preview' ) ) {
    function mdg_ai_bridge_publish_preview( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_bridge_program( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $preview = MMC_MDG_Bridge_Service::publish_preview( $program_id );

        return array(
            'program_id' => $program_id,
            'ready'      => ! empty( $preview['ready'] ),
            'errors'     => array_values( (array) ( $preview['errors'] ?? array() ) ),
            'program'    => mdg_ai_bridge_arr( $preview['program'] ?? null ),
            'event'      => mdg_ai_bridge_arr( $preview['event'] ?? null ),
            'venue'      => mdg_ai_bridge_arr( $preview['venue'] ?? null ),
            'mdg_venue'  => mdg_ai_bridge_arr( $preview['mdg_venue'] ?? null ),
            'sessions'   => mdg_ai_bridge_arr( $preview['sessions'] ?? array() ),
            'tickets'    => mdg_ai_bridge_arr( $preview['tickets'] ?? array() ),
            'bridge'     => mdg_ai_bridge_arr( $preview['bridge'] ?? null ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_auto_link' ) ) {
    function mdg_ai_bridge_auto_link( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_bridge_program( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $before = MMC_MDG_Bridge_Service::status( $program_id );
        if ( ! empty( $before['linked'] ) && empty( $before['stale'] ) ) {
            return array(
                'unchanged' => true,
                'reason'    => 'Program zaten geçerli bir MDG etkinliğine bağlı.',
                'status'    => mdg_ai_bridge_arr( $before ),
            );
        }

        $result = MMC_MDG_Bridge_Service::auto_link( $program_id );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'linked' => true,
            'bridge' => mdg_ai_bridge_arr( $result ),
            'status' => mdg_ai_bridge_arr( MMC_MDG_Bridge_Service::status( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_manual_link' ) ) {
    function mdg_ai_bridge_manual_link( $input ) {
        $program_id   = absint( $input['program_id'] ?? 0 );
        $mdg_event_id = absint( $input['mdg_event_id'] ?? 0 );
        $program = mdg_ai_bridge_program( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $candidates = (array) MMC_MDG_Bridge_Service::candidates( $program_id );
        $candidate = null;
        foreach ( $candidates as $row ) {
            if ( (int) ( $row['event_id'] ?? 0 ) === $mdg_event_id ) {
                $candidate = $row;
                break;
            }
        }
        if ( ! $candidate ) {
            return new WP_Error(
                'mdg_ai_bridge_candidate_missing',
                'Seçilen MDG etkinliği bu program için doğrulanmış aday listesinde değil. Manuel bağlantı yapılmadı.'
            );
        }

        $result = MMC_MDG_Bridge_Service::link(
            $program_id,
            $mdg_event_id,
            'manual_ai',
            max( 0, min( 100, absint( $input['confidence'] ?? ( ! empty( $candidate['strong'] ) ? 100 : 80 ) ) ) )
        );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'linked'    => true,
            'candidate' => $candidate,
            'bridge'    => mdg_ai_bridge_arr( $result ),
            'status'    => mdg_ai_bridge_arr( MMC_MDG_Bridge_Service::status( $program_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_bridge_create_draft' ) ) {
    function mdg_ai_bridge_create_draft( $input ) {
        $program_id = absint( $input['program_id'] ?? 0 );
        $program = mdg_ai_bridge_program( $program_id );
        if ( is_wp_error( $program ) ) { return $program; }

        $preview = MMC_MDG_Bridge_Service::publish_preview( $program_id );
        if ( empty( $preview['ready'] ) ) {
            return new WP_Error(
                'mdg_ai_bridge_publish_not_ready',
                implode( ' ', array_values( (array) ( $preview['errors'] ?? array() ) ) )
            );
        }

        $before = MMC_MDG_Bridge_Service::status( $program_id );
        $result = MMC_MDG_Bridge_Service::create_draft_from_program( $program_id );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'created_or_reused' => true,
            'mdg_event_id'      => (int) $result,
            'previous_status'   => mdg_ai_bridge_arr( $before ),
            'status'            => mdg_ai_bridge_arr( MMC_MDG_Bridge_Service::status( $program_id ) ),
        );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-mdg-kopru',
            array(
                'label'       => 'Madagaskar MMC ↔ MDG Köprüsü',
                'description' => 'MMC programlarını canlı MDG etkinlikleriyle güvenli eşleştirme ve taslak üretim katmanı.',
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
    $link = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>true ),
        'public' => true,
    );
    $critical = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>false ),
        'public' => true,
    );

    $program_schema = array(
        'type'=>'object',
        'properties'=>array( 'program_id'=>array('type'=>'integer','minimum'=>1) ),
        'required'=>array('program_id'),
    );

    wp_register_ability( 'madagaskar/mdg-bridge-status', array(
        'label'=>'MMC ↔ MDG Köprü Durumunu Getir',
        'description'=>'Programın MDG etkinlik bağlantısını, tarih/salon/seans uyumunu, kimlik eşleşmesini ve satış mutabakatını getirir.',
        'category'=>'madagaskar-mdg-kopru',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_bridge_status',
        'permission_callback'=>'mdg_ai_bridge_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mdg-bridge-candidates', array(
        'label'=>'MDG Etkinlik Adaylarını Getir',
        'description'=>'WooCommerce ürün/varyasyon, Tickera, il, ilçe, tarih ve salon eşleşmelerine göre MDG etkinlik adaylarını puanlar.',
        'category'=>'madagaskar-mdg-kopru',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_bridge_candidates',
        'permission_callback'=>'mdg_ai_bridge_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mdg-publish-preview', array(
        'label'=>'MDG Taslak Yayın Hazırlığını Kontrol Et',
        'description'=>'Programın tarih, kesin salon, seans ve aktif bilet türleri açısından MDG taslağına aktarılmaya hazır olup olmadığını salt okunur denetler.',
        'category'=>'madagaskar-mdg-kopru',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_bridge_publish_preview',
        'permission_callback'=>'mdg_ai_bridge_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mdg-bridge-auto-link', array(
        'label'=>'MMC Programını Güvenli MDG Adayına Otomatik Bağla',
        'description'=>'Yalnız tek bir güçlü WooCommerce/Tickera kimlik adayı varsa bağlantı kurar; sıfır veya birden fazla güçlü adayda hata verip hiçbir şey değiştirmez.',
        'category'=>'madagaskar-mdg-kopru',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_bridge_auto_link',
        'permission_callback'=>'mdg_ai_bridge_can_run',
        'meta'=>$link,
    ) );

    wp_register_ability( 'madagaskar/mdg-bridge-manual-link', array(
        'label'=>'MMC Programını Seçili MDG Etkinliğine Bağla',
        'description'=>'Yalnız programın doğrulanmış aday listesinde bulunan açık MDG event ID ile manuel bağlantı kurar; başka programa bağlı event çakışmasını ana servis engeller.',
        'category'=>'madagaskar-mdg-kopru',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'program_id'=>array('type'=>'integer','minimum'=>1),
                'mdg_event_id'=>array('type'=>'integer','minimum'=>1),
                'confidence'=>array('type'=>'integer','minimum'=>0,'maximum'=>100),
            ),
            'required'=>array('program_id','mdg_event_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_bridge_manual_link',
        'permission_callback'=>'mdg_ai_bridge_can_run',
        'meta'=>$critical,
    ) );

    wp_register_ability( 'madagaskar/mdg-create-draft-from-program', array(
        'label'=>'MMC Programından MDG Taslağı Oluştur/Garanti Et',
        'description'=>'Hazırlık kontrolü geçen program için mevcut köprüyü veya tam yapısal eşleşmeyi yeniden kullanır; mükerrer yoksa transaction içinde MDG taslağı oluşturur ve köprüler.',
        'category'=>'madagaskar-mdg-kopru',
        'input_schema'=>$program_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_bridge_create_draft',
        'permission_callback'=>'mdg_ai_bridge_can_run',
        'meta'=>$link,
    ) );
} );
