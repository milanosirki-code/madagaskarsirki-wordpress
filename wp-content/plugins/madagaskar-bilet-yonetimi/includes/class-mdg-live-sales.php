<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.8.1 live sales bridge.
 *
 * Public V2 page -> mapped WooCommerce variations -> shared MDG session capacity.
 * Capacity is reserved atomically after the WooCommerce order is created and
 * immediately before payment processing. Paid orders convert holds to sold units.
 */
final class MDG_Live_Sales {
    const CART_FLAG = 'mdg_live_sale';
    const HOLD_MINUTES = 45;

    public static function hooks() {
        add_action( 'wp_ajax_mdg_live_add_to_cart', array( __CLASS__, 'add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_mdg_live_add_to_cart', array( __CLASS__, 'add_to_cart' ) );

        // Classic checkout: order exists, payment has not been processed yet.
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'classic_order_processed' ), 5, 3 );
        // Cart/Checkout Block Store API: documented pre-payment order hook.
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'store_api_order_processed' ), 5, 1 );

        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'payment_complete' ), 5, 1 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'payment_complete' ), 5, 1 );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'payment_complete' ), 5, 1 );
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'release_order' ), 5, 1 );
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'release_order' ), 5, 1 );
    }

    public static function add_to_cart() {
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
            wp_send_json_error( array( 'message' => 'Bilet satış sistemi geçici olarak kullanılamıyor.' ), 503 );
        }

        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_ajax_referer( 'mdg_live_cart_' . $event_id, 'nonce' );
        $event = class_exists( 'MDG_Events' ) ? MDG_Events::get( $event_id ) : null;
        if ( ! $event || MDG_Status::ONSALE !== (string) $event->status ) {
            wp_send_json_error( array( 'message' => 'Bu etkinlik şu anda satışta değil.' ), 409 );
        }

        $session_id = absint( $_POST['session_id'] ?? 0 );
        $lines = json_decode( (string) wp_unslash( $_POST['lines'] ?? '' ), true );
        if ( ! $session_id || ! is_array( $lines ) ) {
            wp_send_json_error( array( 'message' => 'Seans veya bilet seçimi geçersiz.' ), 400 );
        }

        global $wpdb;
        $session = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE id=%d AND event_id=%d LIMIT 1',
            $session_id,
            $event_id
        ) );
        if ( ! $session || MDG_Status::ONSALE !== (string) $session->status ) {
            wp_send_json_error( array( 'message' => 'Seçtiğiniz seans şu anda satışa açık değil.' ), 409 );
        }
        if ( ! (int) $session->wc_product_id || ! (int) $session->tickera_event_id ) {
            wp_send_json_error( array( 'message' => 'Seans satış bağlantısı hazır değil.' ), 409 );
        }

        $product = wc_get_product( (int) $session->wc_product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) || 'publish' !== get_post_status( $product->get_id() ) ) {
            wp_send_json_error( array( 'message' => 'Seans ürünü satışa uygun değil.' ), 409 );
        }

        $validated = array();
        $requested_units = 0;
        $seen = array();
        foreach ( $lines as $line ) {
            if ( ! is_array( $line ) ) { continue; }
            $code = strtoupper( sanitize_key( $line['code'] ?? '' ) );
            $code = str_replace( '-', '_', $code );
            $qty = absint( $line['qty'] ?? 0 );
            if ( ! $code || $qty < 1 ) { continue; }
            if ( $qty > 20 || isset( $seen[ $code ] ) ) {
                wp_send_json_error( array( 'message' => 'Bilet adedi geçersiz.' ), 400 );
            }
            $seen[ $code ] = true;
            $type = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . MDG_DB::table( 'ticket_types' ) . ' WHERE session_id=%d AND code=%s AND is_active=1 LIMIT 1',
                $session_id,
                $code
            ) );
            if ( ! $type || ! (int) $type->wc_variation_id ) {
                wp_send_json_error( array( 'message' => 'Seçilen bilet türü satışa bağlı değil.' ), 409 );
            }
            $variation = wc_get_product( (int) $type->wc_variation_id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $product->get_id() ) {
                wp_send_json_error( array( 'message' => 'Bilet varyasyonu doğrulanamadı.' ), 409 );
            }
            if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
                wp_send_json_error( array( 'message' => $type->label . ' şu anda satın alınamıyor.' ), 409 );
            }
            if ( abs( round( (float) $type->price, 2 ) - round( (float) $variation->get_price(), 2 ) ) > 0.009 ) {
                wp_send_json_error( array( 'message' => 'Bilet fiyatı güncelleniyor. Lütfen sayfayı yenileyin.' ), 409 );
            }
            $units = max( 1, (int) $type->capacity_units );
            $requested_units += $qty * $units;
            $validated[] = array( 'type'=>$type, 'variation'=>$variation, 'qty'=>$qty, 'units'=>$units );
        }

        if ( ! $validated || $requested_units < 1 ) {
            wp_send_json_error( array( 'message' => 'En az bir bilet seçmelisiniz.' ), 400 );
        }
        $available = MDG_Capacity::available( $session_id );
        if ( $requested_units > $available ) {
            wp_send_json_error( array( 'message' => $available > 0 ? 'Bu seans için yalnızca ' . $available . ' kişilik kapasite kaldı.' : 'Bu seans için biletler tükenmiştir.' ), 409 );
        }

        if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) { wc_load_cart(); }
        if ( ! WC()->cart ) { wp_send_json_error( array( 'message' => 'Sepet başlatılamadı.' ), 503 ); }
        if ( function_exists( 'wc_clear_notices' ) ) { wc_clear_notices(); }

        // The V2 event page represents one selected session. Re-adding the same
        // event replaces its previous V2 selection but never touches unrelated cart items.
        foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
            if ( ! empty( $cart_item[ self::CART_FLAG ] ) && (int) ( $cart_item['mdg_event_id'] ?? 0 ) === $event_id ) {
                WC()->cart->remove_cart_item( $key );
            }
        }

        $added = array();
        try {
            foreach ( $validated as $row ) {
                $variation = $row['variation'];
                $type = $row['type'];
                $attrs = function_exists( 'wc_get_product_variation_attributes' )
                    ? wc_get_product_variation_attributes( (int) $variation->get_id() )
                    : $variation->get_variation_attributes();
                $attrs = is_array( $attrs ) ? array_filter( $attrs, static function( $v ){ return '' !== (string) $v; } ) : array();
                if ( ! $attrs ) { throw new Exception( 'Bilet seçeneği okunamadı.' ); }
                $key = WC()->cart->add_to_cart(
                    (int) $product->get_id(),
                    (int) $row['qty'],
                    (int) $variation->get_id(),
                    $attrs,
                    array(
                        self::CART_FLAG      => 1,
                        'mdg_event_id'       => $event_id,
                        'mdg_session_id'     => $session_id,
                        'mdg_ticket_type_id' => (int) $type->id,
                        'mdg_capacity_units' => (int) $row['units'],
                    )
                );
                if ( ! $key ) { throw new Exception( $type->label . ' sepete eklenemedi.' ); }
                $added[] = $key;
            }
            if ( method_exists( WC()->cart, 'calculate_totals' ) ) { WC()->cart->calculate_totals(); }
            if ( method_exists( WC()->cart, 'set_session' ) ) { WC()->cart->set_session(); }
        } catch ( Throwable $e ) {
            foreach ( $added as $key ) { WC()->cart->remove_cart_item( $key ); }
            wp_send_json_error( array( 'message' => $e->getMessage() ), 409 );
        }

        wp_send_json_success( array(
            'message'  => 'Biletler sepete eklendi.',
            'cart_url' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
        ) );
    }

    public static function classic_order_processed( $order_id, $posted_data = array(), $order = null ) {
        if ( ! $order && function_exists( 'wc_get_order' ) ) { $order = wc_get_order( $order_id ); }
        self::reserve_or_throw( $order );
    }

    public static function store_api_order_processed( $order ) {
        self::reserve_or_throw( $order );
    }

    private static function reserve_or_throw( $order ) {
        if ( ! $order instanceof WC_Order ) { return; }
        $groups = self::order_capacity_groups( $order );
        if ( ! $groups ) { return; }

        global $wpdb;
        $holds_table = MDG_DB::table( 'holds' );
        $existing = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$holds_table} WHERE order_id=%d AND status IN ('held','sold')",
            $order->get_id()
        ) );
        if ( $existing > 0 ) { self::sync_order_map( $order ); return; }

        $created = array();
        foreach ( $groups as $session_id => $units ) {
            $session = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE id=%d', $session_id ) );
            if ( ! $session || MDG_Status::ONSALE !== (string) $session->status ) {
                self::release_created( $created );
                self::throw_capacity_error( 'Seçtiğiniz seans artık satışa açık değil.' );
            }
            $hold = MDG_Capacity::hold(
                (int) $session_id,
                (int) $units,
                gmdate( 'Y-m-d H:i:s', time() + self::HOLD_MINUTES * MINUTE_IN_SECONDS ),
                (int) $order->get_id(),
                ''
            );
            if ( is_wp_error( $hold ) ) {
                self::release_created( $created );
                self::throw_capacity_error( 'Bu seans için yeterli kapasite kalmadı. Lütfen sepetinizi güncelleyip tekrar deneyin.' );
            }
            $created[] = (int) $hold;
        }
        self::sync_order_map( $order );
    }

    private static function throw_capacity_error( $message ) {
        if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'mdg_capacity', $message, 409 );
        }
        throw new Exception( $message );
    }

    private static function release_created( $ids ) {
        foreach ( (array) $ids as $id ) { MDG_Capacity::release_hold( (int) $id, 'released' ); }
    }

    private static function order_capacity_groups( $order ) {
        global $wpdb;
        $groups = array();
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $variation_id = (int) $item->get_variation_id();
            if ( ! $variation_id ) { continue; }
            $type = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . MDG_DB::table( 'ticket_types' ) . ' WHERE wc_variation_id=%d LIMIT 1',
                $variation_id
            ) );
            if ( ! $type ) { continue; }
            $session = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE id=%d LIMIT 1', (int) $type->session_id ) );
            if ( ! $session ) { continue; }
            $event = MDG_Events::get( (int) $session->event_id );
            if ( ! $event || MDG_Status::ONSALE !== (string) $event->status ) { continue; }
            $units = max( 1, (int) $type->capacity_units ) * max( 1, (int) $item->get_quantity() );
            if ( ! isset( $groups[ (int) $session->id ] ) ) { $groups[ (int) $session->id ] = 0; }
            $groups[ (int) $session->id ] += $units;
        }
        return $groups;
    }

    public static function payment_complete( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) { return; }
        MDG_Capacity::commit_order( $order_id );
        self::sync_order_map_status( $order_id, true );
    }

    public static function release_order( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id ) { return; }
        MDG_Capacity::release_order( $order_id );
        self::sync_order_map_status( $order_id, false );
    }

    public static function reconcile_event_sales( $event_id ) {
        if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_is_paid_statuses' ) ) {
            return new WP_Error( 'mdg_woo_missing', 'WooCommerce sipariş sorgusu kullanılamıyor.' );
        }
        global $wpdb;
        $event_id = absint( $event_id );
        $sessions = MDG_Sessions::by_event( $event_id );
        if ( ! $sessions ) { return new WP_Error( 'mdg_no_sessions', 'Etkinlikte seans bulunamadı.' ); }

        $variation_map = array();
        $totals = array();
        foreach ( $sessions as $session ) {
            $totals[ (int) $session->id ] = 0;
            foreach ( MDG_Sessions::ticket_types_by_session( (int) $session->id ) as $type ) {
                if ( ! (int) $type->wc_variation_id || ! (int) $type->is_active ) { continue; }
                $variation_map[ (int) $type->wc_variation_id ] = array(
                    'session_id' => (int) $session->id,
                    'type_id'    => (int) $type->id,
                    'units'      => max( 1, (int) $type->capacity_units ),
                    'event_id'   => $event_id,
                );
            }
        }
        if ( ! $variation_map ) { return new WP_Error( 'mdg_no_mapping', 'WooCommerce varyasyon eşleşmesi bulunamadı.' ); }

        $page = 1;
        do {
            $result = wc_get_orders( array(
                'status'   => wc_get_is_paid_statuses(),
                'limit'    => 100,
                'page'     => $page,
                'paginate' => true,
                'orderby'  => 'date',
                'order'    => 'ASC',
                'return'   => 'objects',
            ) );
            $orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : array();
            foreach ( $orders as $order ) {
                foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
                    $vid = (int) $item->get_variation_id();
                    if ( ! isset( $variation_map[ $vid ] ) ) { continue; }
                    $m = $variation_map[ $vid ];
                    $qty = max( 1, (int) $item->get_quantity() );
                    $totals[ $m['session_id'] ] += $qty * $m['units'];
                    self::upsert_order_map_row( $order, $item_id, $item, $m );
                }
            }
            $max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? (int) $result->max_num_pages : 1;
            $page++;
        } while ( $page <= $max_pages );

        foreach ( $totals as $session_id => $sold_units ) {
            $wpdb->update(
                MDG_DB::table( 'sessions' ),
                array( 'sold_units'=>(int)$sold_units, 'updated_at'=>MDG_DB::now() ),
                array( 'id'=>(int)$session_id ),
                array( '%d','%s' ),
                array( '%d' )
            );
        }
        return $totals;
    }

    private static function sync_order_map( $order ) {
        global $wpdb;
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $vid = (int) $item->get_variation_id();
            if ( ! $vid ) { continue; }
            $type = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'ticket_types' ) . ' WHERE wc_variation_id=%d LIMIT 1', $vid ) );
            if ( ! $type ) { continue; }
            $session = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE id=%d LIMIT 1', (int) $type->session_id ) );
            if ( ! $session ) { continue; }
            $m = array( 'session_id'=>(int)$session->id, 'type_id'=>(int)$type->id, 'units'=>max(1,(int)$type->capacity_units), 'event_id'=>(int)$session->event_id );
            self::upsert_order_map_row( $order, $item_id, $item, $m );
        }
    }

    private static function upsert_order_map_row( $order, $item_id, $item, $m ) {
        global $wpdb;
        $table = MDG_DB::table( 'order_map' );
        $qty = max( 1, (int) $item->get_quantity() );
        $paid = $order->get_date_paid();
        $data = array(
            'order_id'           => (int) $order->get_id(),
            'order_item_id'      => (int) $item_id,
            'event_id'           => (int) $m['event_id'],
            'session_id'         => (int) $m['session_id'],
            'ticket_type_id'     => (int) $m['type_id'],
            'quantity'           => $qty,
            'units_per_ticket'   => (int) $m['units'],
            'units_total'        => $qty * (int) $m['units'],
            'line_total'         => (float) $item->get_total(),
            'order_status'       => (string) $order->get_status(),
            'paid_at'            => $paid ? gmdate( 'Y-m-d H:i:s', $paid->getTimestamp() ) : null,
            'updated_at'         => MDG_DB::now(),
        );
        $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_item_id=%d", (int) $item_id ) );
        if ( $exists ) {
            $wpdb->update( $table, $data, array( 'id'=>$exists ) );
        } else {
            $data['created_at'] = MDG_DB::now();
            $wpdb->insert( $table, $data );
        }
    }

    private static function sync_order_map_status( $order_id, $paid ) {
        if ( ! function_exists( 'wc_get_order' ) ) { return; }
        $order = wc_get_order( $order_id );
        if ( ! $order ) { return; }
        global $wpdb;
        $date_paid = $order->get_date_paid();
        $wpdb->update(
            MDG_DB::table( 'order_map' ),
            array(
                'order_status'=>(string)$order->get_status(),
                'paid_at'=> $paid && $date_paid ? gmdate( 'Y-m-d H:i:s', $date_paid->getTimestamp() ) : null,
                'updated_at'=>MDG_DB::now(),
            ),
            array( 'order_id'=>(int)$order_id )
        );
    }
}
