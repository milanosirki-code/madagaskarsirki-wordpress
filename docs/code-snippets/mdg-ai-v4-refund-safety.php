<?php
/**
 * Madagaskar AI — V4 İade Güvenliği
 *
 * Staging snippet. Gerçek para iadesi yapmaz.
 * Yalnız V4 dry-run/preflight, iade vaka kayıtları ve WooCommerce refund geçmişini
 * WordPress Abilities API üzerinden salt-okunur açar.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_ai_refund_can_run' ) ) {
    function mdg_ai_refund_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_refund_forbidden', 'Bu işlem için WooCommerce yönetim yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MDG_Bilet_Yonetimi_V4' ) || ! function_exists( 'wc_get_order' ) ) {
            return new WP_Error( 'mdg_ai_refund_v4_missing', 'Madagaskar V4 iade servisi kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_refund_v4_private' ) ) {
    function mdg_ai_refund_v4_private( $method, $args = array() ) {
        try {
            $obj = new MDG_Bilet_Yonetimi_V4();
            $ref = new ReflectionMethod( 'MDG_Bilet_Yonetimi_V4', $method );
            if ( method_exists( $ref, 'setAccessible' ) ) { $ref->setAccessible( true ); }
            return $ref->invokeArgs( $obj, $args );
        } catch ( Throwable $e ) {
            return new WP_Error( 'mdg_ai_refund_reflection_failed', $e->getMessage() );
        }
    }
}

if ( ! function_exists( 'mdg_ai_refund_preflight' ) ) {
    function mdg_ai_refund_preflight( $input ) {
        $order_id = absint( $input['order_id'] ?? 0 );
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'mdg_ai_refund_order_missing', 'Sipariş bulunamadı.' );
        }

        $pre = mdg_ai_refund_v4_private( 'refund_preflight', array( $order ) );
        if ( is_wp_error( $pre ) ) { return $pre; }

        $gateway = (array) ( $pre['gateway'] ?? array() );
        $tickets = array();
        foreach ( (array) ( $pre['tickets'] ?? array() ) as $t ) {
            $tickets[] = array(
                'id'             => (int) ( $t['id'] ?? 0 ),
                'post_status'    => (string) ( $t['post_status'] ?? '' ),
                'event_id'       => (int) ( $t['event_id'] ?? 0 ),
                'ticket_type_id' => (int) ( $t['ticket_type_id'] ?? 0 ),
                'has_qr'         => ! empty( $t['ticket_code_hash'] ),
                'invalidated'    => 'yes' === (string) ( $t['invalidated'] ?? '' ),
                'invalid_reason' => (string) ( $t['invalid_reason'] ?? '' ),
            );
        }

        $checks = array(
            'status_ok'                 => ! empty( $pre['status_ok'] ),
            'paid'                      => ! empty( $pre['paid'] ),
            'no_prior_refund'           => ! empty( $pre['no_prior_refund'] ),
            'amount_positive'           => ! empty( $pre['amount_positive'] ),
            'full_amount_matches'       => ! empty( $pre['full_amount_matches'] ),
            'gateway_found'             => ! empty( $gateway['found'] ),
            'gateway_supports_refunds'  => ! empty( $gateway['supports_refunds'] ),
            'tickets_exist'             => ! empty( $pre['tickets_exist'] ),
            'tickets_all_active'        => ! empty( $pre['tickets_all_active'] ),
            'line_items_exist'          => ! empty( $pre['line_items_exist'] ),
            'no_live_case'              => ! empty( $pre['no_live_case'] ),
        );
        $failed = array_keys( array_filter( $checks, static fn( $ok ) => ! $ok ) );

        return array(
            'order' => array(
                'order_id'                => $order_id,
                'status'                  => (string) $order->get_status(),
                'total'                   => (float) $order->get_total(),
                'total_refunded'          => (float) $order->get_total_refunded(),
                'remaining_refund_amount' => (float) $order->get_remaining_refund_amount(),
                'currency'                => (string) $order->get_currency(),
                'payment_method'          => (string) $order->get_payment_method(),
                'payment_title'           => (string) $order->get_payment_method_title(),
            ),
            'ready'         => ! empty( $pre['ready'] ),
            'snapshot_hash' => (string) ( $pre['snapshot_hash'] ?? '' ),
            'gateway'       => array(
                'id'               => (string) ( $gateway['id'] ?? '' ),
                'title'            => (string) ( $gateway['title'] ?? '' ),
                'class'            => (string) ( $gateway['class'] ?? '' ),
                'found'            => ! empty( $gateway['found'] ),
                'supports_refunds' => ! empty( $gateway['supports_refunds'] ),
            ),
            'checks'        => $checks,
            'failed_checks' => $failed,
            'ticket_count'  => count( $tickets ),
            'tickets'       => $tickets,
        );
    }
}

if ( ! function_exists( 'mdg_ai_refund_cases_list' ) ) {
    function mdg_ai_refund_cases_list( $input = array() ) {
        $order_id = absint( $input['order_id'] ?? 0 );
        $state    = sanitize_key( $input['state'] ?? '' );
        $limit    = max( 1, min( 200, absint( $input['limit'] ?? 50 ) ) );

        $meta_query = array();
        if ( $order_id ) {
            $meta_query[] = array(
                'key'     => '_mdg_v4_refund_order_id',
                'value'   => $order_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $state ) {
            $meta_query[] = array(
                'key'     => '_mdg_v4_refund_state',
                'value'   => $state,
                'compare' => '=',
            );
        }

        $q = new WP_Query( array(
            'post_type'      => 'mdg_v4_refund_case',
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta_query,
            'no_found_rows'  => true,
        ) );

        $items = array();
        foreach ( (array) $q->posts as $p ) {
            $items[] = array(
                'case_id'           => (int) $p->ID,
                'order_id'          => absint( get_post_meta( $p->ID, '_mdg_v4_refund_order_id', true ) ),
                'state'             => (string) get_post_meta( $p->ID, '_mdg_v4_refund_state', true ),
                'requester_user_id' => absint( get_post_meta( $p->ID, '_mdg_v4_refund_requester', true ) ),
                'approver_user_id'  => absint( get_post_meta( $p->ID, '_mdg_v4_refund_approver', true ) ),
                'wc_refund_id'      => absint( get_post_meta( $p->ID, '_mdg_v4_refund_wc_refund_id', true ) ),
                'reason'            => (string) get_post_meta( $p->ID, '_mdg_v4_refund_reason', true ),
                'snapshot_hash'     => (string) get_post_meta( $p->ID, '_mdg_v4_refund_snapshot_hash', true ),
                'created_at'        => (string) $p->post_date,
            );
        }
        return array( 'count' => count( $items ), 'items' => $items );
    }
}

if ( ! function_exists( 'mdg_ai_refund_case_get' ) ) {
    function mdg_ai_refund_case_get( $input ) {
        $case_id = absint( $input['case_id'] ?? 0 );
        $p = get_post( $case_id );
        if ( ! $p || 'mdg_v4_refund_case' !== $p->post_type ) {
            return new WP_Error( 'mdg_ai_refund_case_missing', 'V4 iade kaydı bulunamadı.' );
        }

        $order_id = absint( get_post_meta( $case_id, '_mdg_v4_refund_order_id', true ) );
        $order    = $order_id ? wc_get_order( $order_id ) : false;

        return array(
            'case' => array(
                'case_id'           => $case_id,
                'order_id'          => $order_id,
                'state'             => (string) get_post_meta( $case_id, '_mdg_v4_refund_state', true ),
                'requester_user_id' => absint( get_post_meta( $case_id, '_mdg_v4_refund_requester', true ) ),
                'approver_user_id'  => absint( get_post_meta( $case_id, '_mdg_v4_refund_approver', true ) ),
                'reason'            => (string) get_post_meta( $case_id, '_mdg_v4_refund_reason', true ),
                'snapshot_hash'     => (string) get_post_meta( $case_id, '_mdg_v4_refund_snapshot_hash', true ),
                'wc_refund_id'      => absint( get_post_meta( $case_id, '_mdg_v4_refund_wc_refund_id', true ) ),
                'post_status'       => (string) $p->post_status,
                'created_at'        => (string) $p->post_date,
                'updated_at'        => (string) $p->post_modified,
            ),
            'order' => $order ? array(
                'status'                  => (string) $order->get_status(),
                'total'                   => (float) $order->get_total(),
                'total_refunded'          => (float) $order->get_total_refunded(),
                'remaining_refund_amount' => (float) $order->get_remaining_refund_amount(),
                'payment_method'          => (string) $order->get_payment_method(),
                'payment_title'           => (string) $order->get_payment_method_title(),
            ) : array(),
        );
    }
}

if ( ! function_exists( 'mdg_ai_wc_refunds_for_order' ) ) {
    function mdg_ai_wc_refunds_for_order( $input ) {
        $order_id = absint( $input['order_id'] ?? 0 );
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'mdg_ai_refund_order_missing', 'Sipariş bulunamadı.' );
        }

        $items = array();
        foreach ( (array) $order->get_refunds() as $refund ) {
            $items[] = array(
                'refund_id'    => (int) $refund->get_id(),
                'amount'       => (float) $refund->get_amount(),
                'reason'       => (string) $refund->get_reason(),
                'date_created' => $refund->get_date_created() ? $refund->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
                'refunded_by'  => method_exists( $refund, 'get_refunded_by' ) ? (int) $refund->get_refunded_by() : 0,
            );
        }

        return array(
            'order_id'                => $order_id,
            'order_status'            => (string) $order->get_status(),
            'order_total'             => (float) $order->get_total(),
            'total_refunded'          => (float) $order->get_total_refunded(),
            'remaining_refund_amount' => (float) $order->get_remaining_refund_amount(),
            'count'                   => count( $items ),
            'items'                   => $items,
        );
    }
}

add_action( 'wp_abilities_api_categories_init', function() {
    if ( function_exists( 'wp_register_ability_category' ) ) {
        wp_register_ability_category(
            'madagaskar-iade-v4',
            array(
                'label'       => 'Madagaskar V4 İptal / İade',
                'description' => 'V4 tam iade güvenlik önizlemesi, iade vaka kayıtları ve WooCommerce iade geçmişi.',
            )
        );
    }
} );

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) { return; }

    $read = array(
        'annotations' => array(
            'readonly'    => true,
            'destructive' => false,
            'idempotent'  => true,
        ),
        'public' => true,
    );

    wp_register_ability( 'madagaskar/refund-preflight', array(
        'label'       => 'Tam İade Dry-Run Önizlemesi',
        'description' => 'V4 iade motorunun sipariş, gateway, Tickera ticket ve güvenlik kapılarını salt okunur çalıştırır; para iadesi yapmaz.',
        'category'    => 'madagaskar-iade-v4',
        'input_schema'=> array(
            'type' => 'object',
            'properties' => array( 'order_id' => array( 'type'=>'integer','minimum'=>1 ) ),
            'required' => array( 'order_id' ),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_refund_preflight',
        'permission_callback' => 'mdg_ai_refund_can_run',
        'meta'                => $read,
    ) );

    wp_register_ability( 'madagaskar/refund-cases-list', array(
        'label'       => 'V4 İade Vakalarını Listele',
        'description' => 'V4 iki kullanıcı onay akışındaki iade vaka kayıtlarını sipariş ve duruma göre listeler.',
        'category'    => 'madagaskar-iade-v4',
        'input_schema'=> array(
            'type'=>'object',
            'properties'=>array(
                'order_id'=>array('type'=>'integer','minimum'=>1),
                'state'=>array('type'=>'string'),
                'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>200),
            ),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_refund_cases_list',
        'permission_callback' => 'mdg_ai_refund_can_run',
        'meta'                => $read,
    ) );

    wp_register_ability( 'madagaskar/refund-case-get', array(
        'label'       => 'V4 İade Vakasını Getir',
        'description' => 'Tek V4 iade vakasının durum, talep/onay kullanıcıları, snapshot ve WooCommerce refund bağlantısını getirir.',
        'category'    => 'madagaskar-iade-v4',
        'input_schema'=> array(
            'type'=>'object',
            'properties'=>array( 'case_id'=>array('type'=>'integer','minimum'=>1) ),
            'required'=>array('case_id'),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_refund_case_get',
        'permission_callback' => 'mdg_ai_refund_can_run',
        'meta'                => $read,
    ) );

    wp_register_ability( 'madagaskar/order-refunds-history', array(
        'label'       => 'Sipariş İade Geçmişini Getir',
        'description' => 'WooCommerce siparişindeki gerçekleşmiş refund kayıtlarını ve kalan iade tutarını salt okunur getirir.',
        'category'    => 'madagaskar-iade-v4',
        'input_schema'=> array(
            'type'=>'object',
            'properties'=>array( 'order_id'=>array('type'=>'integer','minimum'=>1) ),
            'required'=>array('order_id'),
        ),
        'output_schema'       => array( 'type'=>'object' ),
        'execute_callback'    => 'mdg_ai_wc_refunds_for_order',
        'permission_callback' => 'mdg_ai_refund_can_run',
        'meta'                => $read,
    ) );
} );
