<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Production_Publisher {
    public static function hooks() {
        add_action( 'admin_post_mdg_publish_event_live', array( __CLASS__, 'publish' ) );
    }

    public static function publish() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_admin_referer( 'mdg_publish_event_live_' . $event_id, 'mdg_nonce' );
        if ( empty( $_POST['confirm_live'] ) ) { self::redirect_error( 'Canlı yayın onay kutusunu işaretlemelisiniz.' ); }

        $event = MDG_Events::get( $event_id );
        if ( ! $event || MDG_Status::DRAFT !== (string) $event->status ) { self::redirect_error( 'Yalnızca taslak etkinlik kontrollü biçimde yayına alınabilir.' ); }
        if ( ! class_exists( 'MDG_Production_Readiness' ) ) { self::redirect_error( 'Hazırlık kontrolü kullanılamıyor.' ); }
        $check = MDG_Production_Readiness::check_event( $event );
        if ( empty( $check['ready'] ) ) { self::redirect_error( 'Etkinlik canlı yayına hazır değil: ' . implode( ', ', (array) $check['errors'] ) ); }
        $sales_plan = ! empty( $check['sales_plan'] ) ? $check['sales_plan'] : MDG_Production_Readiness::sales_publish_plan( $event );
        if ( empty( $sales_plan['ready'] ) ) { self::redirect_error( 'Satış nesneleri canlı yayına hazır değil.' ); }

        // Ödeme testi dahil daha önce ödenmiş MDG siparişlerini ortak kapasiteye işle.
        $reconciled = MDG_Live_Sales::reconcile_event_sales( $event_id );
        if ( is_wp_error( $reconciled ) ) { self::redirect_error( $reconciled->get_error_message() ); }

        $sessions = MDG_Sessions::by_event( $event_id );
        if ( ! $sessions ) { self::redirect_error( 'Seans bulunamadı.' ); }
        $slug = self::unique_slug( $event, $sessions );
        $now = MDG_DB::now();

        // V2.9 tarafından oluşturulan draft/private satış nesneleri varsa önce güvenli
        // biçimde publish et. Ankara gibi zaten publish nesnelere dokunulmaz.
        $snapshot = array();
        if ( ! empty( $sales_plan['managed_generated'] ) ) {
            $snapshot = self::snapshot_sales_statuses( $sales_plan );
            $published = self::publish_generated_sales_objects( $event_id, $sales_plan );
            if ( is_wp_error( $published ) ) {
                self::restore_sales_statuses( $snapshot );
                self::redirect_error( $published->get_error_message() );
            }
        } elseif ( ! empty( $sales_plan['needs_publish'] ) ) {
            self::redirect_error( 'Taslak satış nesneleri MDG üretimi olarak doğrulanamadığı için canlı yayın engellendi.' );
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );
        try {
            $ok = $wpdb->update(
                MDG_DB::table( 'events' ),
                array(
                    'public_slug' => $slug,
                    'status'      => MDG_Status::ONSALE,
                    'sale_start'  => $now,
                    'updated_at'  => $now,
                ),
                array( 'id'=>$event_id, 'status'=>MDG_Status::DRAFT ),
                array( '%s','%s','%s','%s' ),
                array( '%d','%s' )
            );
            if ( 1 !== (int) $ok ) { throw new Exception( 'Etkinlik durumu güncellenemedi.' ); }

            foreach ( $sessions as $session ) {
                $new_status = ( (int) $session->sold_units >= (int) $session->capacity_total ) ? MDG_Status::SOLDOUT : MDG_Status::ONSALE;
                $updated = $wpdb->update(
                    MDG_DB::table( 'sessions' ),
                    array( 'status'=>$new_status, 'sale_start'=>$now, 'updated_at'=>$now ),
                    array( 'id'=>(int)$session->id ),
                    array( '%s','%s','%s' ),
                    array( '%d' )
                );
                if ( false === $updated ) { throw new Exception( 'Seans durumu güncellenemedi.' ); }
            }
            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            if ( $snapshot ) { self::restore_sales_statuses( $snapshot ); }
            self::redirect_error( $e->getMessage() );
        }

        self::audit( $event_id, array(
            'slug'=>$slug,
            'reconciled_sold_units'=>$reconciled,
            'managed_generated'=>! empty( $sales_plan['managed_generated'] ),
            'production_key'=>(string) ( $sales_plan['production_key'] ?? '' ),
            'tickera_event_id'=>(int) ( $sales_plan['tickera_event_id'] ?? 0 ),
            'product_ids'=>(array) ( $sales_plan['product_ids'] ?? array() ),
            'variation_ids'=>(array) ( $sales_plan['variation_ids'] ?? array() ),
        ) );
        update_option( 'mdg_rewrite_version', '', false );
        wp_safe_redirect( add_query_arg( array(
            'page'          => 'mdg-live-events',
            'mdg_published' => 1,
            'event_id'      => $event_id,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function snapshot_sales_statuses( array $plan ) {
        $snapshot = array( 'tickera'=>array(), 'products'=>array(), 'variations'=>array() );
        $tid = absint( $plan['tickera_event_id'] ?? 0 );
        if ( $tid ) { $snapshot['tickera'][ $tid ] = (string) get_post_status( $tid ); }
        foreach ( (array) ( $plan['product_ids'] ?? array() ) as $id ) { $id=absint($id); if($id){ $snapshot['products'][$id]=(string)get_post_status($id); } }
        foreach ( (array) ( $plan['variation_ids'] ?? array() ) as $id ) { $id=absint($id); if($id){ $snapshot['variations'][$id]=(string)get_post_status($id); } }
        return $snapshot;
    }

    private static function publish_generated_sales_objects( $event_id, array $plan ) {
        $key = (string) ( $plan['production_key'] ?? '' );
        if ( ! $key ) { return new WP_Error( 'mdg_publish_key', 'Production key bulunamadı.' ); }

        $ids = array_merge(
            array( absint( $plan['tickera_event_id'] ?? 0 ) ),
            array_map( 'absint', (array) ( $plan['product_ids'] ?? array() ) ),
            array_map( 'absint', (array) ( $plan['variation_ids'] ?? array() ) )
        );
        foreach ( array_filter( $ids ) as $id ) {
            if ( '1' !== (string) get_post_meta( $id, '_mdg_managed', true ) ||
                 (int) get_post_meta( $id, '_mdg_event_id', true ) !== (int) $event_id ||
                 (string) get_post_meta( $id, '_mdg_production_key', true ) !== $key ) {
                return new WP_Error( 'mdg_publish_identity', 'Satış nesnesi #' . $id . ' MDG üretim kimliğiyle eşleşmiyor.' );
            }
        }

        $tickera_id = absint( $plan['tickera_event_id'] ?? 0 );
        if ( ! $tickera_id || 'tc_events' !== get_post_type( $tickera_id ) ) { return new WP_Error( 'mdg_publish_tickera', 'Tickera etkinliği doğrulanamadı.' ); }
        if ( 'publish' !== get_post_status( $tickera_id ) ) {
            $r = wp_update_post( array( 'ID'=>$tickera_id, 'post_status'=>'publish' ), true );
            if ( is_wp_error( $r ) ) { return $r; }
        }

        foreach ( (array) ( $plan['product_ids'] ?? array() ) as $product_id ) {
            $product_id = absint( $product_id );
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
            if ( ! $product || ! $product->is_type( 'variable' ) ) { return new WP_Error( 'mdg_publish_product', 'WooCommerce ürünü #' . $product_id . ' doğrulanamadı.' ); }
            if ( 'publish' !== get_post_status( $product_id ) ) {
                try {
                    $product->set_status( 'publish' );
                    // Ürünler mağaza kataloğunda listelenmez; satış MDG etkinlik sayfasından yapılır.
                    $product->set_catalog_visibility( 'hidden' );
                    $product->save();
                } catch ( Throwable $e ) { return new WP_Error( 'mdg_publish_product_save', $e->getMessage() ); }
            }
            if ( class_exists( 'WC_Product_Variable' ) ) { WC_Product_Variable::sync( $product_id ); }
            if ( function_exists( 'wc_delete_product_transients' ) ) { wc_delete_product_transients( $product_id ); }
        }

        foreach ( (array) ( $plan['variation_ids'] ?? array() ) as $variation_id ) {
            $variation_id = absint( $variation_id );
            if ( 'publish' === get_post_status( $variation_id ) ) { continue; }
            $variation = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : null;
            if ( ! $variation || ! $variation->is_type( 'variation' ) ) { return new WP_Error( 'mdg_publish_variation', 'Varyasyon #' . $variation_id . ' doğrulanamadı.' ); }
            try { $variation->set_status( 'publish' ); $variation->save(); }
            catch ( Throwable $e ) { return new WP_Error( 'mdg_publish_variation_save', $e->getMessage() ); }
        }

        // Son durum kontrolü: yarım publish ile devam etmeyelim.
        if ( 'publish' !== get_post_status( $tickera_id ) ) { return new WP_Error( 'mdg_publish_tickera_final', 'Tickera etkinliği publish edilemedi.' ); }
        foreach ( (array) ( $plan['product_ids'] ?? array() ) as $id ) {
            if ( 'publish' !== get_post_status( absint($id) ) ) { return new WP_Error( 'mdg_publish_product_final', 'WooCommerce ürünü #' . absint($id) . ' publish edilemedi.' ); }
        }
        return true;
    }

    private static function restore_sales_statuses( array $snapshot ) {
        foreach ( (array) ( $snapshot['tickera'] ?? array() ) as $id=>$status ) {
            if ( $status && get_post_status( (int)$id ) !== $status ) { wp_update_post( array( 'ID'=>(int)$id, 'post_status'=>$status ) ); }
        }
        foreach ( (array) ( $snapshot['products'] ?? array() ) as $id=>$status ) {
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( (int)$id ) : null;
            if ( $product && $status && get_post_status( (int)$id ) !== $status ) {
                try { $product->set_status( $status ); $product->save(); } catch ( Throwable $e ) {}
            }
        }
        foreach ( (array) ( $snapshot['variations'] ?? array() ) as $id=>$status ) {
            $variation = function_exists( 'wc_get_product' ) ? wc_get_product( (int)$id ) : null;
            if ( $variation && $status && get_post_status( (int)$id ) !== $status ) {
                try { $variation->set_status( $status ); $variation->save(); } catch ( Throwable $e ) {}
            }
        }
    }

    private static function unique_slug( $event, $sessions ) {
        global $wpdb;
        $first = $sessions[0];
        list( $date ) = MDG_Sessions::local_parts( $first->start_at );
        $date_label = $date ? wp_date( 'd F Y', strtotime( $date . ' 12:00:00' ) ) : '';
        $base = sanitize_title( trim( (string) $event->title . ' ' . $date_label ) );
        if ( ! $base ) { $base = 'etkinlik-' . (int) $event->id; }
        $candidate = $base;
        $i = 2;
        $table = MDG_DB::table( 'events' );
        while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE public_slug=%s AND id<>%d", $candidate, (int)$event->id ) ) > 0 ) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private static function redirect_error( $message ) {
        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-settings', 'mdg_publish_error'=>$message ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function audit( $event_id, $context ) {
        global $wpdb;
        $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
            'user_id'=>get_current_user_id(),
            'action_key'=>'event.publish.live',
            'object_type'=>'event',
            'object_id'=>(int)$event_id,
            'context'=>wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'created_at'=>MDG_DB::now(),
        ) );
    }
}
