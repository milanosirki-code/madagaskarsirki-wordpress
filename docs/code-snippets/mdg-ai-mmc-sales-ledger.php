<?php
/**
 * Madagaskar AI — MMC Satış Defteri / Entegrasyon Abilities
 *
 * MMC_Sales_Service doğrudan kullanılır. Amaç WooCommerce/PayTR satışlarının
 * MMC satış defterine güvenli ve idempotent biçimde senkronlanmasıdır.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_mmc_sales_can_run' ) ) {
    function mdg_ai_mmc_sales_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_mmc_sales_forbidden', 'WooCommerce yönetim yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MMC_Sales_Service' ) || ! class_exists( 'MMC_Event_Service' ) ) {
            return new WP_Error( 'mdg_ai_mmc_sales_missing', 'MMC satış servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_arr' ) ) {
    function mdg_ai_mmc_sales_arr( $value ) {
        return json_decode( wp_json_encode( $value ), true );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_event' ) ) {
    function mdg_ai_mmc_sales_event( $event_id ) {
        $event = MMC_Event_Service::get_event( absint( $event_id ) );
        return $event ?: new WP_Error( 'mdg_ai_mmc_sales_event_missing', 'MMC etkinliği bulunamadı.' );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_bridge_verified' ) ) {
    function mdg_ai_mmc_sales_bridge_verified( $event ) {
        if ( ! class_exists( 'MMC_MDG_Bridge_Service' ) ) {
            return new WP_Error( 'mdg_ai_mmc_sales_bridge_missing', 'MMC ↔ MDG köprü servisi kullanılamıyor.' );
        }
        $status = MMC_MDG_Bridge_Service::status( (int) $event->program_id );
        if ( empty( $status['linked'] ) || ! empty( $status['stale'] ) ) {
            return new WP_Error(
                'mdg_ai_mmc_sales_bridge_required',
                'Otomatik satış eşleştirmesi için önce MMC ↔ MDG köprü bağlantısı doğrulanmalıdır.'
            );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_health' ) ) {
    function mdg_ai_mmc_sales_health( $input = array() ) {
        return array(
            'woocommerce_available' => (bool) MMC_Sales_Service::woocommerce_available(),
            'tickera_bridge_detected'=> (bool) MMC_Sales_Service::bridge_detected(),
            'paytr'                  => MMC_Sales_Service::paytr_gateway_status(),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_mappings' ) ) {
    function mdg_ai_mmc_sales_mappings( $input ) {
        $event_id = absint( $input['event_id'] ?? 0 );
        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        return array(
            'event_id' => $event_id,
            'coverage' => MMC_Sales_Service::mapping_coverage( $event_id ),
            'items'    => mdg_ai_mmc_sales_arr( MMC_Sales_Service::mappings( $event_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_mapping_save' ) ) {
    function mdg_ai_mmc_sales_mapping_save( $input ) {
        $event_id       = absint( $input['event_id'] ?? 0 );
        $session_id     = absint( $input['session_id'] ?? 0 );
        $ticket_type_id = absint( $input['ticket_type_id'] ?? 0 );

        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        $external_ids = array(
            absint( $input['wc_product_id'] ?? 0 ),
            absint( $input['wc_variation_id'] ?? 0 ),
            absint( $input['tickera_event_id'] ?? 0 ),
            absint( $input['tickera_ticket_type_id'] ?? 0 ),
        );
        if ( ! array_filter( $external_ids ) ) {
            return new WP_Error(
                'mdg_ai_mmc_sales_mapping_external_required',
                'Satış eşleştirmesi için en az bir WooCommerce veya Tickera kimliği açıkça verilmelidir.'
            );
        }

        $result = MMC_Sales_Service::save_mapping( $event_id, $session_id, $ticket_type_id, array(
            'wc_product_id'          => absint( $input['wc_product_id'] ?? 0 ),
            'wc_variation_id'        => absint( $input['wc_variation_id'] ?? 0 ),
            'tickera_event_id'       => absint( $input['tickera_event_id'] ?? 0 ),
            'tickera_ticket_type_id' => absint( $input['tickera_ticket_type_id'] ?? 0 ),
            'is_active'              => array_key_exists( 'is_active', $input ) ? ! empty( $input['is_active'] ) : true,
        ) );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'saved'      => true,
            'mapping_id' => (int) $result,
            'coverage'   => MMC_Sales_Service::mapping_coverage( $event_id ),
            'items'      => mdg_ai_mmc_sales_arr( MMC_Sales_Service::mappings( $event_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_import_legacy' ) ) {
    function mdg_ai_mmc_sales_import_legacy( $input ) {
        $event_id        = absint( $input['event_id'] ?? 0 );
        $legacy_event_id = absint( $input['legacy_event_id'] ?? 0 );
        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        $bridge_ok = mdg_ai_mmc_sales_bridge_verified( $event );
        if ( is_wp_error( $bridge_ok ) ) { return $bridge_ok; }

        $result = MMC_Sales_Service::import_legacy_mdg_event( $event_id, $legacy_event_id );
        if ( is_wp_error( $result ) ) { return $result; }

        return array(
            'result'   => $result,
            'coverage' => MMC_Sales_Service::mapping_coverage( $event_id ),
            'items'    => mdg_ai_mmc_sales_arr( MMC_Sales_Service::mappings( $event_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_sync_order' ) ) {
    function mdg_ai_mmc_sales_sync_order( $input ) {
        $order_id = absint( $input['order_id'] ?? 0 );
        if ( ! $order_id || ! function_exists( 'wc_get_order' ) || ! wc_get_order( $order_id ) ) {
            return new WP_Error( 'mdg_ai_mmc_sales_order_missing', 'WooCommerce siparişi bulunamadı.' );
        }

        $ok = MMC_Sales_Service::sync_order( $order_id );
        return array( 'synced' => (bool) $ok, 'order_id' => $order_id );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_sync_event' ) ) {
    function mdg_ai_mmc_sales_sync_event( $input ) {
        $event_id = absint( $input['event_id'] ?? 0 );
        $days     = max( 1, min( 1500, absint( $input['lookback_days'] ?? 365 ) ) );
        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        $before = MMC_Sales_Service::summary( $event_id );
        $result = MMC_Sales_Service::sync_event_orders( $event_id, $days );
        if ( is_wp_error( $result ) ) { return $result; }
        $after = MMC_Sales_Service::summary( $event_id );

        return array(
            'event_id' => $event_id,
            'lookback_days' => $days,
            'sync'     => $result,
            'before'   => $before,
            'after'    => $after,
            'coverage' => MMC_Sales_Service::mapping_coverage( $event_id ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_summary' ) ) {
    function mdg_ai_mmc_sales_summary( $input ) {
        $event_id = absint( $input['event_id'] ?? 0 );
        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        return array(
            'event_id'         => $event_id,
            'summary'          => MMC_Sales_Service::summary( $event_id ),
            'session_summary'  => mdg_ai_mmc_sales_arr( MMC_Sales_Service::session_summary( $event_id ) ),
            'mapping_coverage' => MMC_Sales_Service::mapping_coverage( $event_id ),
            'integrations'     => mdg_ai_mmc_sales_arr( MMC_Event_Service::integrations( $event_id ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_recent_orders' ) ) {
    function mdg_ai_mmc_sales_recent_orders( $input ) {
        $event_id = absint( $input['event_id'] ?? 0 );
        $limit    = max( 1, min( 100, absint( $input['limit'] ?? 20 ) ) );
        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        return array(
            'event_id' => $event_id,
            'items'    => mdg_ai_mmc_sales_arr( MMC_Sales_Service::recent_orders( $event_id, $limit ) ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_mmc_sales_refresh_health' ) ) {
    function mdg_ai_mmc_sales_refresh_health( $input ) {
        $event_id = absint( $input['event_id'] ?? 0 );
        $event = mdg_ai_mmc_sales_event( $event_id );
        if ( is_wp_error( $event ) ) { return $event; }

        MMC_Sales_Service::refresh_integration_health( $event_id );

        return array(
            'refreshed'    => true,
            'event_id'     => $event_id,
            'coverage'     => MMC_Sales_Service::mapping_coverage( $event_id ),
            'integrations' => mdg_ai_mmc_sales_arr( MMC_Event_Service::integrations( $event_id ) ),
        );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-mmc-satis',
            array(
                'label'       => 'Madagaskar MMC Satış Defteri',
                'description' => 'WooCommerce/Tickera/PayTR eşleştirme, satış defteri ve doluluk senkronizasyonu.',
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
    $critical = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>false ),
        'public' => true,
    );
    $sync = array(
        'annotations' => array( 'readonly'=>false, 'destructive'=>true, 'idempotent'=>true ),
        'public' => true,
    );

    $event_schema = array(
        'type'=>'object',
        'properties'=>array( 'event_id'=>array('type'=>'integer','minimum'=>1) ),
        'required'=>array('event_id'),
    );

    wp_register_ability( 'madagaskar/mmc-sales-health', array(
        'label'=>'Satış Altyapısı Sağlığını Getir',
        'description'=>'WooCommerce, Tickera Bridge ve PayTR tespit/aktiflik durumunu getirir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>array('type'=>'object','properties'=>array()),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_health',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-mappings', array(
        'label'=>'MMC Satış Eşleştirmelerini Getir',
        'description'=>'Etkinlikteki seans/bilet türlerinin WooCommerce ve Tickera eşleştirmelerini ve kapsam oranını getirir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>$event_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_mappings',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-mapping-save', array(
        'label'=>'MMC Satış Eşleştirmesini Kaydet',
        'description'=>'Etkinlik/seans/bilet türünü WooCommerce ürün/varyasyon ve Tickera etkinliğiyle doğrulayarak eşleştirir. Kritik yazma işlemidir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'event_id'=>array('type'=>'integer','minimum'=>1),
                'session_id'=>array('type'=>'integer','minimum'=>1),
                'ticket_type_id'=>array('type'=>'integer','minimum'=>1),
                'wc_product_id'=>array('type'=>'integer','minimum'=>1),
                'wc_variation_id'=>array('type'=>'integer','minimum'=>1),
                'tickera_event_id'=>array('type'=>'integer','minimum'=>1),
                'tickera_ticket_type_id'=>array('type'=>'integer','minimum'=>1),
                'is_active'=>array('type'=>'boolean'),
            ),
            'required'=>array('event_id','session_id','ticket_type_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_mapping_save',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$critical,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-import-legacy', array(
        'label'=>'Eski MDG Satış Eşleştirmelerini İçe Aktar',
        'description'=>'Eski MDG etkinliğindeki seans/bilet/WooCommerce bağlantılarını saat ve bilet koduna göre MMC etkinliğine aktarır. Kritik yazma işlemidir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'event_id'=>array('type'=>'integer','minimum'=>1),
                'legacy_event_id'=>array('type'=>'integer','minimum'=>1),
            ),
            'required'=>array('event_id','legacy_event_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_import_legacy',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$critical,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-sync-order', array(
        'label'=>'Tek Siparişi MMC Satış Defterine Senkronla',
        'description'=>'WooCommerce sipariş kalemlerini mevcut satış eşleştirmelerine göre MMC satış defterine idempotent olarak işler.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array('order_id'=>array('type'=>'integer','minimum'=>1)),
            'required'=>array('order_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_sync_order',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$sync,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-sync-event', array(
        'label'=>'Etkinlik Satışlarını MMC Defterine Senkronla',
        'description'=>'Seçilen geriye dönük dönemde WooCommerce siparişlerini tarar ve etkinliğin MMC satış defterini eşleştirmelere göre idempotent yeniler. Program durumunu entegrasyon doğrulamasına göre satışta durumuna taşıyabilir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'event_id'=>array('type'=>'integer','minimum'=>1),
                'lookback_days'=>array('type'=>'integer','minimum'=>1,'maximum'=>1500),
            ),
            'required'=>array('event_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_sync_event',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$sync,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-summary', array(
        'label'=>'MMC Satış / Doluluk Özetini Getir',
        'description'=>'Etkinlik toplam sipariş, bilet, kişi kapasitesi, iade, net ciro, seans dolulukları, eşleştirme kapsamı ve entegrasyon durumlarını getirir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>$event_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_summary',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-recent-orders', array(
        'label'=>'MMC Satış Defteri Son Siparişlerini Getir',
        'description'=>'Etkinliğin MMC satış defterindeki yakın sipariş kalemlerini seans ve bilet türüyle getirir.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'event_id'=>array('type'=>'integer','minimum'=>1),
                'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>100),
            ),
            'required'=>array('event_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_recent_orders',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/mmc-sales-refresh-health', array(
        'label'=>'Satış Entegrasyon Sağlığını Yenile',
        'description'=>'WooCommerce eşleştirme kapsamı, Tickera etkinlikleri ve PayTR gateway durumunu yeniden değerlendirir; ileri durumları geriye çekmez.',
        'category'=>'madagaskar-mmc-satis',
        'input_schema'=>$event_schema,
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_mmc_sales_refresh_health',
        'permission_callback'=>'mdg_ai_mmc_sales_can_run',
        'meta'=>$sync,
    ) );
} );
