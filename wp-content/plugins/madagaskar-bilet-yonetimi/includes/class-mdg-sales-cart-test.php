<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.7: Yetkili taslak önizlemesindeki seçimi mevcut eşlenmiş WooCommerce
 * ürün/varyasyonlarına ekler. Sipariş/ödeme oluşturmaz; başarıda sepet sayfasına yönlendirir.
 *
 * Güvenlik sınırı:
 * - yalnızca giriş yapmış manage_woocommerce yetkili kullanıcı,
 * - yalnızca draft MDG etkinliği,
 * - yalnızca etkinliğe ait eşlenmiş session + ticket type,
 * - canlı WooCommerce/Tickera nesnelerini değiştirmez,
 * - başka ürün bulunan sepeti otomatik temizlemez.
 */
final class MDG_Sales_Cart_Test {

    const CART_FLAG = 'mdg_preview_cart_test';

    /**
     * V2.9.6 preview-only purchasability override. WooCommerce correctly treats a
     * variation whose parent variable product is draft/private as not purchasable.
     * That is desirable for the public store, but it prevents our administrator-only
     * pre-publication cart test. The allow-list below exists only for the lifetime of
     * the current AJAX request and only for MDG-managed objects of the same event.
     */
    private static $preview_event_id = 0;
    private static $preview_purchasable_ids = array();
    private static $preview_filters_enabled = false;

    public static function hooks() {
        add_action( 'wp_ajax_mdg_preview_add_to_cart', array( __CLASS__, 'handle_add_to_cart' ) );

        // V2.9.7: Preview cart items are intentionally backed by draft/private products.
        // The AJAX request can add them safely, but WooCommerce re-validates every cart
        // line when the next /cart or /checkout request restores the session. Without
        // this narrowly-scoped filter WooCommerce removes the preview items as
        // non-purchasable and the administrator sees an empty cart.
        add_filter( 'woocommerce_cart_item_is_purchasable', array( __CLASS__, 'filter_preview_cart_item_purchasable' ), 999, 4 );

        // V2.9.8: WC_Cart::check_cart_items() and some Checkout/Blocks paths call
        // WC_Product::is_purchasable() directly instead of the cart-item filter above.
        // Keep the exact same administrator-only preview exception alive on normal
        // cart/checkout requests, but only for products that are already present in
        // the current cart with our signed MDG preview marker.
        add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_preview_session_purchasable' ), 999, 2 );
        add_filter( 'woocommerce_variation_is_purchasable', array( __CLASS__, 'filter_preview_session_purchasable' ), 999, 2 );
    }

    public static function handle_add_to_cart() {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Bu test yalnızca yetkili yönetici tarafından kullanılabilir.' ), 403 );
        }
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce hazır değil.' ), 503 );
        }

        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_ajax_referer( 'mdg_preview_cart_' . $event_id, 'nonce' );

        $event = class_exists( 'MDG_Events' ) ? MDG_Events::get( $event_id ) : null;
        if ( ! $event || 'draft' !== (string) $event->status ) {
            wp_send_json_error( array( 'message' => 'Yalnızca Madagaskar V2 taslağında sepet testi yapılabilir.' ), 400 );
        }

        $session_id = absint( $_POST['session_id'] ?? 0 );
        $lines_raw  = isset( $_POST['lines'] ) ? wp_unslash( $_POST['lines'] ) : '';
        $lines      = json_decode( (string) $lines_raw, true );
        if ( ! $session_id || ! is_array( $lines ) ) {
            wp_send_json_error( array( 'message' => 'Seans veya bilet seçimi geçersiz.' ), 400 );
        }

        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table    = MDG_DB::table( 'ticket_types' );
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$sessions_table} WHERE id=%d AND event_id=%d",
            $session_id,
            $event_id
        ) );
        if ( ! $session ) {
            wp_send_json_error( array( 'message' => 'Seçilen seans bu etkinliğe ait değil.' ), 400 );
        }
        if ( ! (int) $session->wc_product_id || ! (int) $session->tickera_event_id ) {
            wp_send_json_error( array( 'message' => 'Bu seans henüz mevcut satış yapısına bağlanmamış.' ), 409 );
        }

        $product = wc_get_product( (int) $session->wc_product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            wp_send_json_error( array( 'message' => 'Eşlenmiş WooCommerce seans ürünü bulunamadı veya variable product değil.' ), 409 );
        }

        // V2.9.5: Yeni etkinlik üretiminde WooCommerce nesneleri kasıtlı olarak draft/private
        // oluşturulur. Yönetici önizleme testi bu nesneleri sepete ekleyebilmelidir; halka açık
        // satış endpoint'i ise yalnızca publish ürünleri kabul etmeye devam eder. Bu istisna
        // yalnızca MDG tarafından üretilmiş ve mevcut V2 etkinliğine ait ürünler için geçerlidir.
        $product_status = (string) get_post_status( $product->get_id() );
        $preview_status_ok = in_array( $product_status, array( 'publish', 'draft', 'private', 'pending' ), true );
        $managed_ok = '1' === (string) get_post_meta( $product->get_id(), '_mdg_managed', true );
        $managed_event_id = absint( get_post_meta( $product->get_id(), '_mdg_event_id', true ) );
        if ( ! $preview_status_ok || ! $managed_ok || $managed_event_id !== $event_id ) {
            wp_send_json_error( array(
                'message' => 'Eşlenmiş WooCommerce seans ürünü güvenli taslak sepet testine uygun değil.',
            ), 409 );
        }

        // WooCommerce marks children of a draft/private variable product as non-purchasable.
        // For this authenticated preview request only, allow the exact MDG parent product;
        // child variation IDs are appended after their ownership checks below.
        self::enable_preview_purchasability( $event_id, array( (int) $product->get_id() ) );

        // Validate all requested lines first. No cart changes until every line is safe.
        $validated   = array();
        $total_units = 0;
        $seen_codes  = array();
        foreach ( $lines as $line ) {
            if ( ! is_array( $line ) ) { continue; }
            $code = strtoupper( sanitize_key( $line['code'] ?? '' ) );
            $code = str_replace( '-', '_', $code );
            $qty  = absint( $line['qty'] ?? 0 );
            if ( ! $code || $qty < 1 ) { continue; }
            if ( $qty > 20 ) {
                wp_send_json_error( array( 'message' => 'Bir bilet türünden tek işlemde en fazla 20 adet seçilebilir.' ), 400 );
            }
            if ( isset( $seen_codes[ $code ] ) ) {
                wp_send_json_error( array( 'message' => 'Aynı bilet türü birden fazla kez gönderildi.' ), 400 );
            }
            $seen_codes[ $code ] = true;

            $type = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$types_table} WHERE session_id=%d AND code=%s AND is_active=1",
                $session_id,
                $code
            ) );
            if ( ! $type || ! (int) $type->wc_variation_id ) {
                wp_send_json_error( array( 'message' => 'Bilet türü mevcut WooCommerce varyasyonuna bağlı değil: ' . $code ), 409 );
            }

            $variation = wc_get_product( (int) $type->wc_variation_id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== (int) $product->get_id() ) {
                wp_send_json_error( array( 'message' => 'Varyasyon eşleşmesi güvenlik kontrolünden geçmedi: ' . $type->label ), 409 );
            }
            $variation_managed = '1' === (string) get_post_meta( $variation->get_id(), '_mdg_managed', true );
            $variation_event_id = absint( get_post_meta( $variation->get_id(), '_mdg_event_id', true ) );
            if ( ! $variation_managed || $variation_event_id !== $event_id ) {
                wp_send_json_error( array( 'message' => 'Varyasyon bu MDG etkinliğine ait güvenli taslak nesnesi değil: ' . $type->label ), 409 );
            }

            // Add only the already-validated child variation to the preview allow-list.
            self::enable_preview_purchasability( $event_id, array( (int) $variation->get_id() ) );

            if ( ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
                wp_send_json_error( array( 'message' => $type->label . ' şu anda satın alınabilir değil.' ), 409 );
            }

            $mdg_price = round( (float) $type->price, 2 );
            $wc_price  = round( (float) $variation->get_price(), 2 );
            if ( abs( $mdg_price - $wc_price ) > 0.009 ) {
                wp_send_json_error( array( 'message' => $type->label . ' fiyatı V2 ile WooCommerce arasında farklı. İşlem durduruldu.' ), 409 );
            }

            $units = max( 1, (int) $type->capacity_units );
            $total_units += $qty * $units;
            $validated[] = array(
                'type'       => $type,
                'variation'  => $variation,
                'qty'        => $qty,
                'units'      => $units,
            );
        }

        if ( ! $validated || $total_units < 1 ) {
            wp_send_json_error( array( 'message' => 'En az bir bilet seçmelisiniz.' ), 400 );
        }
        if ( $total_units > (int) $session->capacity_total ) {
            wp_send_json_error( array( 'message' => 'Seçilen biletlerin kişi kapasitesi bu seansın toplam kapasitesini aşıyor.' ), 409 );
        }

        if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
            wc_load_cart();
        }
        if ( ! WC()->cart ) {
            wp_send_json_error( array( 'message' => 'WooCommerce sepeti başlatılamadı.' ), 503 );
        }

        // This endpoint is an isolated administrator-only preview sale flow. Clear stale WooCommerce
        // notices left from older failed cart attempts before building the fresh selection.
        if ( function_exists( 'wc_clear_notices' ) ) {
            wc_clear_notices();
        }

        // Never clear unrelated cart contents silently. The admin may explicitly
        // confirm a server-side cart reset if WooCommerce still holds a stale session item.
        $force_clear = ! empty( $_POST['force_clear'] );
        $has_unrelated = false;
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $is_same_preview = ! empty( $cart_item[ self::CART_FLAG ] ) && (int) ( $cart_item['mdg_event_id'] ?? 0 ) === $event_id;
            if ( ! $is_same_preview ) {
                $has_unrelated = true;
                break;
            }
        }

        if ( $has_unrelated && ! $force_clear ) {
            wp_send_json_error( array(
                'code'    => 'cart_has_other_items',
                'message' => 'WooCommerce oturumunda bu test etkinliğine ait olmayan ürün kaldı. Test için sepeti sunucu tarafında temizleyip yalnızca bu seçimi ekleyebilirsiniz.'
            ), 409 );
        }

        if ( $has_unrelated && $force_clear ) {
            WC()->cart->empty_cart( true );
            if ( method_exists( WC()->cart, 'set_session' ) ) { WC()->cart->set_session(); }
            self::audit( 'sales.preview_cart.forced_clear', $event_id, array(
                'session_id' => $session_id,
                'reason'     => 'explicit_admin_confirmation',
            ) );
        }

        // Replace older preview selection for the same event instead of accumulating duplicates.
        foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
            if ( ! empty( $cart_item[ self::CART_FLAG ] ) && (int) ( $cart_item['mdg_event_id'] ?? 0 ) === $event_id ) {
                WC()->cart->remove_cart_item( $key );
            }
        }

        $added_keys = array();
        try {
            foreach ( $validated as $row ) {
                /** @var WC_Product_Variation $variation */
                $variation = $row['variation'];
                $type      = $row['type'];
                // Use the exact variation meta stored by WooCommerce as the source of truth.
                // Variable products require both variation_id and the selected attribute map.
                $attrs = function_exists( 'wc_get_product_variation_attributes' )
                    ? wc_get_product_variation_attributes( (int) $variation->get_id() )
                    : $variation->get_variation_attributes();
                $attrs = is_array( $attrs ) ? array_filter( $attrs, static function( $value ) {
                    return '' !== (string) $value;
                } ) : array();
                if ( empty( $attrs ) ) {
                    throw new Exception( $type->label . ' varyasyon seçenekleri WooCommerce üzerinden okunamadı.' );
                }

                $cart_data = array(
                    self::CART_FLAG      => 1,
                    'mdg_event_id'       => $event_id,
                    'mdg_session_id'     => $session_id,
                    'mdg_ticket_type_id' => (int) $type->id,
                    'mdg_capacity_units' => (int) $row['units'],
                    'mdg_preview_uuid'   => wp_generate_uuid4(),
                );
                $key = WC()->cart->add_to_cart(
                    (int) $product->get_id(),
                    (int) $row['qty'],
                    (int) $variation->get_id(),
                    $attrs,
                    $cart_data
                );
                if ( ! $key ) {
                    throw new Exception( $type->label . ' sepete eklenemedi.' );
                }
                $added_keys[] = $key;
            }
            WC()->cart->calculate_totals();
            if ( method_exists( WC()->cart, 'set_session' ) ) { WC()->cart->set_session(); }

            // A successful test selection supersedes any stale variable-product notice
            // that may have been stored in the WooCommerce session by an earlier attempt.
            if ( function_exists( 'wc_clear_notices' ) ) {
                wc_clear_notices();
            }
        } catch ( Throwable $e ) {
            foreach ( $added_keys as $key ) { WC()->cart->remove_cart_item( $key ); }
            WC()->cart->calculate_totals();
            wp_send_json_error( array( 'message' => $e->getMessage() ?: 'Sepet testi sırasında hata oluştu.' ), 500 );
        }

        self::audit( 'sales.preview_cart.created', $event_id, array(
            'session_id'        => $session_id,
            'wc_product_id'     => (int) $product->get_id(),
            'tickera_event_id'  => (int) $session->tickera_event_id,
            'total_units'       => $total_units,
            'cart_item_count'   => count( $added_keys ),
        ) );

        wp_send_json_success( array(
            'message'  => 'Seçim gerçek WooCommerce sepetine aktarıldı. Henüz sipariş oluşturulmadı.',
            'cart_url' => wc_get_cart_url(),
        ) );
    }


    /**
     * Keep an administrator-only MDG preview cart item alive when WooCommerce restores
     * the cart from the session on a normal page request.
     *
     * This does NOT make draft products publicly purchasable. The override applies only
     * when all of the following are true:
     * - current user is logged in and can manage WooCommerce,
     * - the stored cart line carries the MDG preview flag,
     * - the referenced MDG event still exists and is draft,
     * - product/variation is MDG-managed and belongs to that exact event,
     * - parent/child post statuses are within the controlled pre-publication set.
     */
    public static function filter_preview_cart_item_purchasable( $purchasable, $cart_item_key, $values, $product ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) { return $purchasable; }
        if ( ! is_array( $values ) || empty( $values[ self::CART_FLAG ] ) ) { return $purchasable; }
        if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) { return $purchasable; }

        $event_id = absint( $values['mdg_event_id'] ?? 0 );
        if ( ! $event_id ) { return $purchasable; }

        $event = class_exists( 'MDG_Events' ) ? MDG_Events::get( $event_id ) : null;
        if ( ! $event || 'draft' !== (string) $event->status ) { return $purchasable; }

        $product_id = absint( $product->get_id() );
        if ( ! $product_id ) { return $purchasable; }
        if ( '1' !== (string) get_post_meta( $product_id, '_mdg_managed', true ) ) { return $purchasable; }
        if ( absint( get_post_meta( $product_id, '_mdg_event_id', true ) ) !== $event_id ) { return $purchasable; }

        $allowed_parent_statuses = array( 'publish', 'draft', 'private', 'pending' );
        $allowed_child_statuses  = array( 'publish', 'draft', 'private', 'pending' );
        $status = (string) get_post_status( $product_id );

        if ( $product->is_type( 'variation' ) ) {
            $parent_id = absint( $product->get_parent_id() );
            if ( ! $parent_id ) { return $purchasable; }
            if ( '1' !== (string) get_post_meta( $parent_id, '_mdg_managed', true ) ) { return $purchasable; }
            if ( absint( get_post_meta( $parent_id, '_mdg_event_id', true ) ) !== $event_id ) { return $purchasable; }
            if ( ! in_array( (string) get_post_status( $parent_id ), $allowed_parent_statuses, true ) ) { return $purchasable; }
            if ( ! in_array( $status, $allowed_child_statuses, true ) ) { return $purchasable; }
            return true;
        }

        if ( ! in_array( $status, $allowed_parent_statuses, true ) ) { return $purchasable; }
        return true;
    }

    /**
     * V2.9.8: Keep draft/private MDG preview products purchasable while the authorized
     * administrator moves from the preview AJAX request to the real WooCommerce cart
     * and checkout pages.
     *
     * Security boundary is deliberately narrow:
     * - logged-in user must have manage_woocommerce,
     * - the MDG event must still be draft,
     * - product and parent (for variations) must be MDG-managed and belong to that event,
     * - the exact product/variation must already exist in the current WooCommerce cart
     *   on a line carrying mdg_preview_cart_test=1 for the same event,
     * - only controlled pre-publication post statuses are accepted.
     *
     * Therefore this cannot make arbitrary draft WooCommerce products publicly buyable.
     */
    public static function filter_preview_session_purchasable( $purchasable, $product ) {
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) { return $purchasable; }
        if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) { return $purchasable; }
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) { return $purchasable; }

        $product_id = absint( $product->get_id() );
        if ( ! $product_id ) { return $purchasable; }

        $parent_id = $product->is_type( 'variation' ) ? absint( $product->get_parent_id() ) : 0;
        $owner_id  = $parent_id ?: $product_id;

        if ( '1' !== (string) get_post_meta( $owner_id, '_mdg_managed', true ) ) { return $purchasable; }
        $event_id = absint( get_post_meta( $owner_id, '_mdg_event_id', true ) );
        if ( ! $event_id ) { return $purchasable; }

        $event = class_exists( 'MDG_Events' ) ? MDG_Events::get( $event_id ) : null;
        if ( ! $event || 'draft' !== (string) $event->status ) { return $purchasable; }

        if ( $parent_id ) {
            if ( '1' !== (string) get_post_meta( $product_id, '_mdg_managed', true ) ) { return $purchasable; }
            if ( absint( get_post_meta( $product_id, '_mdg_event_id', true ) ) !== $event_id ) { return $purchasable; }
            if ( ! in_array( (string) get_post_status( $parent_id ), array( 'publish', 'draft', 'private', 'pending' ), true ) ) { return $purchasable; }
            if ( ! in_array( (string) get_post_status( $product_id ), array( 'publish', 'draft', 'private', 'pending' ), true ) ) { return $purchasable; }
        } else {
            if ( ! in_array( (string) get_post_status( $product_id ), array( 'publish', 'draft', 'private', 'pending' ), true ) ) { return $purchasable; }
        }

        // The cart line is the capability token for this normal-page exception.
        // Match both the parent product ID and the child variation ID so neither can
        // be made purchasable merely by knowing its database ID.
        foreach ( WC()->cart->get_cart() as $values ) {
            if ( ! is_array( $values ) || empty( $values[ self::CART_FLAG ] ) ) { continue; }
            if ( absint( $values['mdg_event_id'] ?? 0 ) !== $event_id ) { continue; }

            $line_parent    = absint( $values['product_id'] ?? 0 );
            $line_variation = absint( $values['variation_id'] ?? 0 );

            if ( $parent_id ) {
                if ( $line_parent === $parent_id && $line_variation === $product_id ) { return true; }
            } elseif ( $line_parent === $product_id ) {
                return true;
            }
        }

        return $purchasable;
    }

    /**
     * Enable a narrowly scoped WooCommerce purchasability override for the current
     * administrator preview AJAX request. Public/live requests never enter this path.
     */
    private static function enable_preview_purchasability( $event_id, array $product_ids ) {
        $event_id = absint( $event_id );
        if ( ! $event_id ) { return; }

        if ( self::$preview_event_id && self::$preview_event_id !== $event_id ) {
            return;
        }
        self::$preview_event_id = $event_id;

        foreach ( $product_ids as $product_id ) {
            $product_id = absint( $product_id );
            if ( $product_id ) { self::$preview_purchasable_ids[ $product_id ] = true; }
        }

        if ( ! self::$preview_filters_enabled ) {
            add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_preview_purchasable' ), 999, 2 );
            add_filter( 'woocommerce_variation_is_purchasable', array( __CLASS__, 'filter_preview_purchasable' ), 999, 2 );
            self::$preview_filters_enabled = true;
        }
    }

    /**
     * Return true only for the exact MDG-managed parent/variation IDs that were
     * validated earlier in this same request. All other products keep WooCommerce's
     * original purchasability result.
     */
    public static function filter_preview_purchasable( $purchasable, $product ) {
        if ( ! self::$preview_filters_enabled || ! self::$preview_event_id ) { return $purchasable; }
        if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) { return $purchasable; }
        if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) { return $purchasable; }
        if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) { return $purchasable; }

        $product_id = absint( $product->get_id() );
        if ( ! $product_id || empty( self::$preview_purchasable_ids[ $product_id ] ) ) { return $purchasable; }

        if ( '1' !== (string) get_post_meta( $product_id, '_mdg_managed', true ) ) { return $purchasable; }
        if ( absint( get_post_meta( $product_id, '_mdg_event_id', true ) ) !== self::$preview_event_id ) { return $purchasable; }

        $status = (string) get_post_status( $product_id );
        if ( $product->is_type( 'variation' ) ) {
            $parent_id = absint( $product->get_parent_id() );
            if ( ! $parent_id || empty( self::$preview_purchasable_ids[ $parent_id ] ) ) { return $purchasable; }
            if ( '1' !== (string) get_post_meta( $parent_id, '_mdg_managed', true ) ) { return $purchasable; }
            if ( absint( get_post_meta( $parent_id, '_mdg_event_id', true ) ) !== self::$preview_event_id ) { return $purchasable; }
            $parent_status = (string) get_post_status( $parent_id );
            if ( ! in_array( $parent_status, array( 'publish', 'draft', 'private', 'pending' ), true ) ) { return $purchasable; }
            if ( ! in_array( $status, array( 'publish', 'private', 'pending' ), true ) ) { return $purchasable; }
            return true;
        }

        if ( ! in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ) { return $purchasable; }
        return true;
    }

    private static function audit( $action, $object_id, array $context ) {
        global $wpdb;
        try {
            $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
                'user_id'     => get_current_user_id() ?: null,
                'action_key'  => sanitize_key( $action ),
                'object_type' => 'event',
                'object_id'   => absint( $object_id ),
                'context'     => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                'created_at'  => MDG_DB::now(),
            ) );
        } catch ( Throwable $e ) {
            // Audit must never block the cart test.
        }
    }
}
