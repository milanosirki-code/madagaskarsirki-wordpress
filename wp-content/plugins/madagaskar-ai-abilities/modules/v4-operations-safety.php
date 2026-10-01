<?php
/**
 * Madagaskar AI — V4 Satış / Erteleme / Aktarım Güvenliği
 *
 * Salt-okunur abilities. V4 yazma handler'larını çağırmaz.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_v4_ops_can_run' ) ) {
    function mdg_ai_v4_ops_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_v4_ops_forbidden', 'WooCommerce yönetim yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MDG_Bilet_Yonetimi_V4' ) ) {
            return new WP_Error( 'mdg_ai_v4_ops_missing', 'Madagaskar Bilet Yönetimi V4 kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_v4_ops_invoke' ) ) {
    function mdg_ai_v4_ops_invoke( $method, $args = array() ) {
        try {
            $rc = new ReflectionClass( 'MDG_Bilet_Yonetimi_V4' );
            if ( ! $rc->hasMethod( $method ) ) {
                return new WP_Error( 'mdg_ai_v4_method_missing', sprintf( 'V4::%s bulunamadı.', $method ) );
            }
            $obj = $rc->newInstanceWithoutConstructor();
            $rm  = $rc->getMethod( $method );
            if ( method_exists( $rm, 'setAccessible' ) ) { $rm->setAccessible( true ); }
            return $rm->invokeArgs( $obj, $args );
        } catch ( Throwable $e ) {
            return new WP_Error( 'mdg_ai_v4_invoke_failed', $e->getMessage() );
        }
    }
}

if ( ! function_exists( 'mdg_ai_v4_ops_sanitize' ) ) {
    function mdg_ai_v4_ops_sanitize( $value ) {
        $value = json_decode( wp_json_encode( $value ), true );
        $walk = function( $v ) use ( &$walk ) {
            if ( ! is_array( $v ) ) { return $v; }
            $out = array();
            foreach ( $v as $k => $item ) {
                $ks = strtolower( (string) $k );
                $sensitive = false;
                foreach ( array( 'email', 'phone', 'address', 'token', 'secret', 'authorization', 'nonce', 'order_key', 'customer', 'ticket_code', 'qr' ) as $needle ) {
                    if ( false !== strpos( $ks, $needle ) ) { $sensitive = true; break; }
                }
                $out[ $k ] = $sensitive ? '[redacted]' : $walk( $item );
            }
            return $out;
        };
        return $walk( $value );
    }
}

if ( ! function_exists( 'mdg_ai_v4_sales_product_status' ) ) {
    function mdg_ai_v4_sales_product_status( $input ) {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return new WP_Error( 'mdg_ai_v4_wc_missing', 'WooCommerce ürün servisi kullanılamıyor.' );
        }
        $product_id = absint( $input['product_id'] ?? 0 );
        $product = $product_id ? wc_get_product( $product_id ) : false;
        if ( ! $product ) {
            return new WP_Error( 'mdg_ai_v4_product_missing', 'Ürün bulunamadı.' );
        }
        $closed = mdg_ai_v4_ops_invoke( 'sales_closed_for_product', array( $product ) );
        if ( is_wp_error( $closed ) ) { return $closed; }

        return array(
            'product_id'     => $product_id,
            'parent_id'      => (int) $product->get_parent_id(),
            'type'           => (string) $product->get_type(),
            'status'         => (string) $product->get_status(),
            'sales_closed'   => (bool) $closed,
            'purchasable'    => (bool) $product->is_purchasable(),
        );
    }
}

if ( ! function_exists( 'mdg_ai_v4_postponement_mappings' ) ) {
    function mdg_ai_v4_postponement_mappings( $input = array() ) {
        $rows = mdg_ai_v4_ops_invoke( 'mapping_records', array() );
        if ( is_wp_error( $rows ) ) { return $rows; }
        $safe = mdg_ai_v4_ops_sanitize( $rows );
        return array( 'count' => is_array( $safe ) ? count( $safe ) : 0, 'items' => $safe );
    }
}

if ( ! function_exists( 'mdg_ai_v4_postponement_map_get' ) ) {
    function mdg_ai_v4_postponement_map_get( $input ) {
        $map_id = absint( $input['map_id'] ?? 0 );
        if ( ! $map_id ) { return new WP_Error( 'mdg_ai_v4_map_required', 'map_id zorunludur.' ); }
        $row = mdg_ai_v4_ops_invoke( 'parse_mapping', array( $map_id ) );
        if ( is_wp_error( $row ) ) { return $row; }
        return array( 'map_id' => $map_id, 'mapping' => mdg_ai_v4_ops_sanitize( $row ) );
    }
}

if ( ! function_exists( 'mdg_ai_v4_postponement_preflight' ) ) {
    function mdg_ai_v4_postponement_preflight( $input ) {
        $source_id = absint( $input['source_event_id'] ?? 0 );
        $new_date  = sanitize_text_field( $input['new_date'] ?? '' );
        if ( ! $source_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_date ) ) {
            return new WP_Error( 'mdg_ai_v4_postpone_input', 'source_event_id ve YYYY-MM-DD new_date zorunludur.' );
        }
        $row = mdg_ai_v4_ops_invoke( 'postpone_preflight', array( $source_id, $new_date ) );
        if ( is_wp_error( $row ) ) { return $row; }
        return array(
            'source_event_id' => $source_id,
            'new_date'        => $new_date,
            'preflight'       => mdg_ai_v4_ops_sanitize( $row ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_v4_transfer_scan' ) ) {
    function mdg_ai_v4_transfer_scan( $input ) {
        $map_id = absint( $input['map_id'] ?? 0 );
        if ( ! $map_id ) { return new WP_Error( 'mdg_ai_v4_map_required', 'map_id zorunludur.' ); }
        $row = mdg_ai_v4_ops_invoke( 'scan_mapping', array( $map_id ) );
        if ( is_wp_error( $row ) ) { return $row; }
        return array( 'map_id' => $map_id, 'scan' => mdg_ai_v4_ops_sanitize( $row ) );
    }
}

if ( ! function_exists( 'mdg_ai_v4_transfer_last' ) ) {
    function mdg_ai_v4_transfer_last( $input ) {
        $map_id = absint( $input['map_id'] ?? 0 );
        if ( ! $map_id ) { return new WP_Error( 'mdg_ai_v4_map_required', 'map_id zorunludur.' ); }
        $row = mdg_ai_v4_ops_invoke( 'last_transfer_for_map', array( $map_id ) );
        if ( is_wp_error( $row ) ) { return $row; }
        return array( 'map_id' => $map_id, 'transfer' => mdg_ai_v4_ops_sanitize( $row ) );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category( 'madagaskar-v4-operasyon-guvenligi', array(
            'label' => 'Madagaskar V4 Satış / Erteleme / Aktarım',
            'description' => 'V4 satış kilidi, erteleme mapping/preflight ve aktarım planını salt okunur denetler.',
        ) );
    }
} );

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) { return; }

    $read = array(
        'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
        'public' => true,
    );

    wp_register_ability( 'madagaskar/v4-sales-product-status', array(
        'label' => 'V4 Ürün Satış Kilidi Durumu',
        'description' => 'WooCommerce ürün/varyasyonunun V4 satış kapatma durumunu salt okunur getirir.',
        'category' => 'madagaskar-v4-operasyon-guvenligi',
        'input_schema' => array(
            'type'=>'object',
            'properties'=>array( 'product_id'=>array('type'=>'integer','minimum'=>1) ),
            'required'=>array('product_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_v4_sales_product_status',
        'permission_callback'=>'mdg_ai_v4_ops_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/v4-postponement-mappings', array(
        'label' => 'V4 Erteleme Eşlemelerini Getir',
        'description' => 'V4 erteleme mapping kayıtlarını salt okunur getirir.',
        'category' => 'madagaskar-v4-operasyon-guvenligi',
        'input_schema'=>array('type'=>'object','properties'=>array()),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_v4_postponement_mappings',
        'permission_callback'=>'mdg_ai_v4_ops_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/v4-postponement-map-get', array(
        'label' => 'V4 Erteleme Eşlemesini Getir',
        'description' => 'Tek V4 erteleme mapping kaydını salt okunur getirir.',
        'category' => 'madagaskar-v4-operasyon-guvenligi',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array('map_id'=>array('type'=>'integer','minimum'=>1)),
            'required'=>array('map_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_v4_postponement_map_get',
        'permission_callback'=>'mdg_ai_v4_ops_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/v4-postponement-preflight', array(
        'label' => 'V4 Erteleme Preflight',
        'description' => 'Kaynak Tickera etkinliği ve yeni tarih için V4 erteleme güvenlik önizlemesini salt okunur çalıştırır.',
        'category' => 'madagaskar-v4-operasyon-guvenligi',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array(
                'source_event_id'=>array('type'=>'integer','minimum'=>1),
                'new_date'=>array('type'=>'string'),
            ),
            'required'=>array('source_event_id','new_date'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_v4_postponement_preflight',
        'permission_callback'=>'mdg_ai_v4_ops_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/v4-transfer-scan', array(
        'label' => 'V4 Bilet Aktarım Planını Tara',
        'description' => 'Erteleme mappingi için V4 aktarım taramasını salt okunur çalıştırır; ticket değiştirmez.',
        'category' => 'madagaskar-v4-operasyon-guvenligi',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array('map_id'=>array('type'=>'integer','minimum'=>1)),
            'required'=>array('map_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_v4_transfer_scan',
        'permission_callback'=>'mdg_ai_v4_ops_can_run',
        'meta'=>$read,
    ) );

    wp_register_ability( 'madagaskar/v4-transfer-last', array(
        'label' => 'V4 Son Bilet Aktarımını Getir',
        'description' => 'Erteleme mappingi için son V4 aktarım kaydını salt okunur getirir.',
        'category' => 'madagaskar-v4-operasyon-guvenligi',
        'input_schema'=>array(
            'type'=>'object',
            'properties'=>array('map_id'=>array('type'=>'integer','minimum'=>1)),
            'required'=>array('map_id'),
        ),
        'output_schema'=>array('type'=>'object'),
        'execute_callback'=>'mdg_ai_v4_transfer_last',
        'permission_callback'=>'mdg_ai_v4_ops_can_run',
        'meta'=>$read,
    ) );
} );
