<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V3.6.2 — Controlled REAL single-order PayTR refund test.
 *
 * SAFETY BOUNDARY:
 * - One order per request only.
 * - Real-money test refund is capped at 10.00 TRY per execution.
 * - Only paid PayTR orders that contain exclusively the selected MDG event are eligible.
 * - Does NOT cancel/postpone the event.
 * - Does NOT invalidate Tickera tickets.
 * - Does NOT restock capacity/items.
 * - Uses WooCommerce wc_create_refund() with refund_payment=true so the installed gateway handles the card refund.
 * - One-time form token + execution lock reduce accidental duplicate submissions.
 */
final class MDG_Refund_Test {
    const MAX_TEST_REFUND = 10.00;
    const TOKEN_TTL       = 30 * MINUTE_IN_SECONDS;
    const LOCK_TTL        = 10 * MINUTE_IN_SECONDS;

    public static function hooks() {
        add_action( 'admin_post_mdg_single_refund_test', array( __CLASS__, 'refund_action' ) );
    }

    public static function render_panel( $event_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $event_id = absint( $event_id );
        if ( ! $event_id ) { return; }

        $eligible = self::eligible_orders( $event_id );
        $token    = wp_generate_uuid4();
        set_transient(
            self::token_key( $token ),
            array( 'user_id' => get_current_user_id(), 'event_id' => $event_id ),
            self::TOKEN_TTL
        );

        echo '<div class="mdg-panel" style="margin-top:18px;border:2px solid #d63638">';
        echo '<h2 style="color:#b32d2e">Gerçek Karta İade — Tek Sipariş Testi</h2>';
        echo '<div class="notice notice-error inline"><p><strong>DİKKAT:</strong> Bu bölüm gerçek para hareketi yapar. V3.6.2 yalnızca tek siparişte ve işlem başına en fazla <strong>' . esc_html( self::money( self::MAX_TEST_REFUND ) ) . '</strong> iade gönderir. Etkinlik durumu ve bilet geçerliliği bu testte değişmez.</p></div>';
        echo '<p>İlk doğrulama için <strong>1,00 TL</strong> önerilir. Başarılı olursa WooCommerce iade kaydı oluşur ve PayTR ağ geçidi kart iadesini işler. Tam etkinlik/toplu iade bu sürümde kapalıdır.</p>';

        if ( empty( $eligible ) ) {
            echo '<p><strong>Bu etkinlikte test için uygun tek-etkinlik PayTR siparişi bulunamadı.</strong> Karışık sepetler ve başka etkinlik/ürün içeren siparişler güvenlik nedeniyle bu sürümde seçilemez.</p></div>';
            return;
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'GERÇEK para iadesi gönderilecek. Sipariş, tutar ve onay metnini kontrol ettiniz mi?\');">';
        echo '<input type="hidden" name="action" value="mdg_single_refund_test">';
        echo '<input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
        echo '<input type="hidden" name="execution_token" value="' . esc_attr( $token ) . '">';
        wp_nonce_field( 'mdg_single_refund_test_' . $event_id );

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="mdg_refund_order_id">Sipariş</label></th><td>';
        echo '<select name="order_id" id="mdg_refund_order_id" required style="min-width:420px;max-width:100%">';
        echo '<option value="">Sipariş seçin</option>';
        foreach ( $eligible as $row ) {
            $o = $row['order'];
            $label = '#' . $o->get_order_number() . ' — ' . trim( $o->get_formatted_billing_full_name() ) . ' — kalan ' . self::money( $row['safe_refundable'] );
            echo '<option value="' . esc_attr( $o->get_id() ) . '" data-max="' . esc_attr( min( self::MAX_TEST_REFUND, $row['safe_refundable'] ) ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select><p class="description">Yalnızca seçili etkinliği içeren ve PayTR ile ödenmiş siparişler listelenir.</p></td></tr>';

        echo '<tr><th scope="row"><label for="mdg_refund_amount">Gerçek iade tutarı</label></th><td>';
        echo '<input type="number" name="amount" id="mdg_refund_amount" value="1.00" min="0.01" max="' . esc_attr( number_format( self::MAX_TEST_REFUND, 2, '.', '' ) ) . '" step="0.01" required> TL';
        echo '<p class="description">V3.6.2 güvenlik limiti: işlem başına en fazla ' . esc_html( self::money( self::MAX_TEST_REFUND ) ) . '.</p></td></tr>';

        echo '<tr><th scope="row"><label for="mdg_refund_reason">İade notu</label></th><td>';
        echo '<input type="text" name="reason" id="mdg_refund_reason" value="Madagaskar kontrollü PayTR gerçek iade testi" maxlength="190" class="regular-text">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="mdg_refund_confirm">Yazılı onay</label></th><td>';
        echo '<input type="text" name="confirmation" id="mdg_refund_confirm" placeholder="IADE 1728" autocomplete="off" required class="regular-text">';
        echo '<p class="description">Seçtiğiniz sipariş için tam olarak <code>IADE SİPARİŞNO</code> yazın. Örnek: <code>IADE 1728</code>.</p></td></tr>';

        echo '<tr><th scope="row">Son güvenlik onayları</th><td>';
        echo '<label style="display:block;margin-bottom:8px"><input type="checkbox" name="real_money_ack" value="1" required> Bunun <strong>gerçek kredi/banka kartına para iadesi</strong> olduğunu onaylıyorum.</label>';
        echo '<label style="display:block"><input type="checkbox" name="ticket_unchanged_ack" value="1" required> Bu testte etkinliğin iptal edilmeyeceğini ve biletin otomatik geçersizleşmeyeceğini biliyorum.</label>';
        echo '</td></tr>';
        echo '</tbody></table>';

        submit_button( 'GERÇEK TEST İADESİNİ GÖNDER', 'delete', 'submit', false );
        echo ' <span class="description">Toplu iade kapalı • kapasiteye dokunulmaz • bilet geçerliliği değişmez</span>';
        echo '</form></div>';
    }

    public static function refund_action() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'Geçersiz istek yöntemi.' ); }
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkisiz işlem.' ); }

        $event_id = absint( $_POST['event_id'] ?? 0 );
        $order_id = absint( $_POST['order_id'] ?? 0 );
        check_admin_referer( 'mdg_single_refund_test_' . $event_id );

        $token = sanitize_text_field( wp_unslash( $_POST['execution_token'] ?? '' ) );
        $token_data = $token ? get_transient( self::token_key( $token ) ) : false;
        if ( ! is_array( $token_data ) || (int) ( $token_data['user_id'] ?? 0 ) !== get_current_user_id() || (int) ( $token_data['event_id'] ?? 0 ) !== $event_id ) {
            self::redirect( $event_id, 'İade formu süresi dolmuş veya daha önce kullanılmış. Sayfayı yenileyip tekrar deneyin.', true );
        }

        if ( empty( $_POST['real_money_ack'] ) || empty( $_POST['ticket_unchanged_ack'] ) ) {
            self::redirect( $event_id, 'Gerçek para iadesi için iki güvenlik onayı da zorunludur.', true );
        }

        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        if ( ! $order ) { self::redirect( $event_id, 'Sipariş bulunamadı.', true ); }

        $eligibility = self::eligibility_for_order( $order, $event_id );
        if ( empty( $eligibility['eligible'] ) ) {
            self::redirect( $event_id, (string) ( $eligibility['reason'] ?? 'Sipariş gerçek iade testine uygun değil.' ), true );
        }

        $confirmation = trim( sanitize_text_field( wp_unslash( $_POST['confirmation'] ?? '' ) ) );
        $expected     = 'IADE ' . (string) $order->get_order_number();
        if ( $confirmation !== $expected ) {
            self::redirect( $event_id, 'Yazılı onay eşleşmiyor. Beklenen: ' . $expected, true );
        }

        $amount_raw = wc_format_decimal( wp_unslash( $_POST['amount'] ?? '' ), wc_get_price_decimals() );
        $amount     = round( (float) $amount_raw, 2 );
        $max        = min( self::MAX_TEST_REFUND, (float) $eligibility['safe_refundable'] );
        if ( $amount <= 0 || $amount > $max + 0.0001 ) {
            self::redirect( $event_id, 'İade tutarı güvenli test sınırının dışında. Bu sipariş için azami: ' . self::money( $max ), true );
        }

        $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
        if ( '' === $reason ) { $reason = 'Madagaskar kontrollü PayTR gerçek iade testi'; }

        $gateway_check = self::gateway_for_order( $order );
        if ( empty( $gateway_check['ready'] ) ) {
            self::redirect( $event_id, (string) ( $gateway_check['reason'] ?? 'PayTR ağ geçidi iade için hazır değil.' ), true );
        }

        // Consume the one-time token only after every pre-flight validation passes.
        delete_transient( self::token_key( $token ) );

        if ( ! self::acquire_lock( $order_id ) ) {
            self::redirect( $event_id, 'Bu sipariş için başka bir iade işlemi şu anda devam ediyor. İşlem durumunu kontrol edin.', true );
        }

        self::audit( 'refund_test_started', $event_id, $order_id, array(
            'amount' => $amount,
            'order_number' => $order->get_order_number(),
            'payment_method' => $order->get_payment_method(),
        ) );

        try {
            if ( ! function_exists( 'wc_create_refund' ) ) {
                throw new Exception( 'WooCommerce iade fonksiyonu kullanılamıyor.' );
            }

            $refund = wc_create_refund( array(
                'amount'         => $amount,
                'reason'         => $reason,
                'order_id'       => $order_id,
                'line_items'     => array(),
                'refund_payment' => true,
                'restock_items'  => false,
            ) );

            if ( is_wp_error( $refund ) ) {
                $message = $refund->get_error_message();
                self::audit( 'refund_test_failed', $event_id, $order_id, array( 'amount'=>$amount, 'error'=>$message ) );
                $order->add_order_note( 'Madagaskar gerçek test iadesi BAŞARISIZ: ' . $message );
                self::release_lock( $order_id );
                self::redirect( $event_id, 'PayTR/WooCommerce iadesi başarısız: ' . $message . ' Yeniden denemeden önce PayTR ve sipariş notlarını kontrol edin.', true );
            }

            if ( ! $refund || ! is_a( $refund, 'WC_Order_Refund' ) ) {
                throw new Exception( 'WooCommerce geçerli bir iade kaydı döndürmedi.' );
            }

            $refund->update_meta_data( '_mdg_event_id', $event_id );
            $refund->update_meta_data( '_mdg_refund_mode', 'single_real_test' );
            $refund->update_meta_data( '_mdg_refunded_by_user_id', get_current_user_id() );
            $refund->update_meta_data( '_mdg_plugin_version', defined( 'MDG_BILET_VERSION' ) ? MDG_BILET_VERSION : '3.6.2' );
            $refund->save();

            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->update_meta_data( '_mdg_last_real_refund_test_at', current_time( 'mysql', true ) );
                $order->update_meta_data( '_mdg_last_real_refund_test_amount', $amount );
                $order->save();
                $order->add_order_note( sprintf( 'Madagaskar gerçek PayTR test iadesi başarılı. İade #%d • Tutar: %s • Etkinlik #%d. Bilet/etkinlik durumu değiştirilmedi.', $refund->get_id(), self::money( $amount ), $event_id ) );
            }

            self::audit( 'refund_test_success', $event_id, $order_id, array(
                'amount' => $amount,
                'refund_id' => $refund->get_id(),
                'refunded_payment' => method_exists( $refund, 'get_refunded_payment' ) ? (bool) $refund->get_refunded_payment() : null,
            ) );
            self::release_lock( $order_id );
            self::redirect( $event_id, 'GERÇEK test iadesi başarılı. Sipariş #' . $order->get_order_number() . ' için ' . self::money( $amount ) . ' karta iade edildi. WooCommerce iade no: #' . $refund->get_id() . '.', false );

        } catch ( Throwable $e ) {
            self::audit( 'refund_test_exception', $event_id, $order_id, array( 'amount'=>$amount, 'error'=>$e->getMessage() ) );
            if ( $order ) { $order->add_order_note( 'Madagaskar gerçek test iadesi istisna ile durdu: ' . $e->getMessage() ); }
            self::release_lock( $order_id );
            self::redirect( $event_id, 'İade işlemi tamamlanamadı: ' . $e->getMessage() . ' Tekrar denemeden önce sipariş ve PayTR durumunu kontrol edin.', true );
        }
    }

    private static function eligible_orders( $event_id ) {
        global $wpdb;
        $map = MDG_DB::table( 'order_map' );
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT order_id FROM {$map} WHERE event_id=%d AND paid_at IS NOT NULL ORDER BY order_id DESC LIMIT 100",
            $event_id
        ) );
        $out = array();
        foreach ( (array) $ids as $id ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $id ) ) : null;
            if ( ! $order ) { continue; }
            $e = self::eligibility_for_order( $order, $event_id );
            if ( ! empty( $e['eligible'] ) ) {
                $e['order'] = $order;
                $out[] = $e;
            }
        }
        return $out;
    }

    private static function eligibility_for_order( $order, $event_id ) {
        $out = array( 'eligible'=>false, 'reason'=>'Sipariş uygun değil.', 'safe_refundable'=>0.0 );
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) { return $out; }
        if ( ! $order->get_date_paid() ) { return array( 'eligible'=>false, 'reason'=>'Sipariş ödenmiş değil.', 'safe_refundable'=>0.0 ); }
        if ( in_array( $order->get_status(), array( 'failed','cancelled','refunded','trash' ), true ) ) {
            return array( 'eligible'=>false, 'reason'=>'Sipariş durumu gerçek iade testine uygun değil.', 'safe_refundable'=>0.0 );
        }
        if ( false === strpos( strtolower( $order->get_payment_method() . ' ' . $order->get_payment_method_title() ), 'paytr' ) ) {
            return array( 'eligible'=>false, 'reason'=>'Sipariş PayTR ile ödenmemiş.', 'safe_refundable'=>0.0 );
        }

        global $wpdb;
        $map = MDG_DB::table( 'order_map' );
        $mapped = $wpdb->get_results( $wpdb->prepare(
            "SELECT order_item_id,event_id FROM {$map} WHERE order_id=%d",
            $order->get_id()
        ) );
        if ( empty( $mapped ) ) { return array( 'eligible'=>false, 'reason'=>'Sipariş MDG satış eşlemesinde bulunamadı.', 'safe_refundable'=>0.0 ); }

        $mapped_item_ids = array();
        $event_ids = array();
        foreach ( $mapped as $r ) {
            $mapped_item_ids[ absint( $r->order_item_id ) ] = true;
            $event_ids[ absint( $r->event_id ) ] = true;
        }
        if ( count( $event_ids ) !== 1 || ! isset( $event_ids[ absint( $event_id ) ] ) ) {
            return array( 'eligible'=>false, 'reason'=>'Sipariş birden fazla etkinlik içeriyor; V3.6.2 karışık sepetlerde gerçek iade yapmaz.', 'safe_refundable'=>0.0 );
        }

        // Reject any product line that is not mapped to this exact MDG event.
        $event_amount = 0.0;
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! isset( $mapped_item_ids[ absint( $item_id ) ] ) ) {
                return array( 'eligible'=>false, 'reason'=>'Siparişte MDG eşlemesi dışında ürün satırı var; güvenlik nedeniyle test iadesi kapalı.', 'safe_refundable'=>0.0 );
            }
            $event_amount += (float) $item->get_total() + (float) $item->get_total_tax();
        }

        $remaining = max( 0.0, (float) $order->get_remaining_refund_amount() );
        $safe = round( min( max( 0.0, $event_amount ), $remaining ), 2 );
        if ( $safe <= 0 ) { return array( 'eligible'=>false, 'reason'=>'Siparişte iade edilebilir bakiye kalmamış.', 'safe_refundable'=>0.0 ); }

        $gateway = self::gateway_for_order( $order );
        if ( empty( $gateway['ready'] ) ) {
            return array( 'eligible'=>false, 'reason'=>(string) $gateway['reason'], 'safe_refundable'=>$safe );
        }

        return array( 'eligible'=>true, 'reason'=>'Hazır', 'safe_refundable'=>$safe, 'event_amount'=>round($event_amount,2) );
    }

    private static function gateway_for_order( $order ) {
        $out = array( 'ready'=>false, 'reason'=>'Ödeme ağ geçidi bulunamadı.' );
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) { return $out; }
        try { $gateways = WC()->payment_gateways()->payment_gateways(); } catch ( Throwable $e ) { return $out; }
        $gateway = $gateways[ (string) $order->get_payment_method() ] ?? null;
        if ( ! $gateway ) { return $out; }
        if ( ! method_exists( $gateway, 'supports' ) || ! $gateway->supports( 'refunds' ) ) {
            return array( 'ready'=>false, 'reason'=>'Bu siparişin PayTR ağ geçidi refunds desteğini bildirmiyor.' );
        }
        if ( ! is_callable( array( $gateway, 'process_refund' ) ) ) {
            return array( 'ready'=>false, 'reason'=>'Bu siparişin PayTR process_refund fonksiyonu kullanılamıyor.' );
        }
        return array( 'ready'=>true, 'reason'=>'Hazır', 'gateway'=>$gateway );
    }

    private static function acquire_lock( $order_id ) {
        $key = self::lock_key( $order_id );
        $existing = get_option( $key, false );
        if ( is_array( $existing ) && ! empty( $existing['time'] ) && ( time() - (int) $existing['time'] ) > self::LOCK_TTL ) {
            delete_option( $key );
            $existing = false;
        }
        if ( false !== $existing ) { return false; }
        return add_option( $key, array( 'time'=>time(), 'user_id'=>get_current_user_id() ), '', false );
    }

    private static function release_lock( $order_id ) {
        delete_option( self::lock_key( $order_id ) );
    }

    private static function lock_key( $order_id ) {
        return 'mdg_refund_exec_lock_' . absint( $order_id );
    }

    private static function token_key( $token ) {
        return 'mdg_refund_once_' . md5( (string) $token );
    }

    private static function audit( $action, $event_id, $order_id, array $context ) {
        global $wpdb;
        $context['order_id'] = absint( $order_id );
        $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
            'user_id'     => get_current_user_id(),
            'action_key'  => sanitize_key( $action ),
            'object_type' => 'event',
            'object_id'   => absint( $event_id ),
            'context'     => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'created_at'  => MDG_DB::now(),
        ) );
    }

    private static function money( $amount ) {
        if ( function_exists( 'wc_price' ) ) { return wp_strip_all_tags( wc_price( (float) $amount ) ); }
        return number_format_i18n( (float) $amount, 2 ) . ' TL';
    }

    private static function redirect( $event_id, $message, $error = false ) {
        $args = array( 'page'=>'mdg-cancel', 'event_id'=>absint( $event_id ) );
        $args[ $error ? 'mdg_refund_error' : 'mdg_refund_notice' ] = rawurlencode( (string) $message );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
