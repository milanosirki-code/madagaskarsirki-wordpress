<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V3.6.3 — Cancellation/refund preview + Tickera ticket invalidation dry-run.
 *
 * SECURITY BOUNDARY:
 * - DOES NOT change event status.
 * - DOES NOT invalidate tickets.
 * - DOES NOT create WooCommerce refunds.
 * - DOES NOT call PayTR Refund API.
 * - The only remote call is PayTR Status Query (read-only), manually triggered by an admin.
 */
final class MDG_Refund_Preview {
    const STATUS_ENDPOINT = 'https://www.paytr.com/odeme/durum-sorgu';

    public static function hooks() {
        add_action( 'admin_post_mdg_paytr_status_query', array( __CLASS__, 'status_query_action' ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-bilet' ) );
        }

        $event_id = absint( $_GET['event_id'] ?? 0 );
        $events   = self::events_with_sales();
        $event    = $event_id ? MDG_Events::get( $event_id ) : null;
        $paytr    = self::paytr_gateway_info();

        echo '<div class="wrap mdg-wrap"><h1>🎪 İptal / Erteleme &amp; PayTR İade Önizleme</h1>';
        echo '<p class="mdg-lead">Etkinlik iptali öncesinde etkilenen ücretli siparişleri, güvenli iade tutarını ve PayTR ödeme durumunu kontrol edin.</p>';
        if ( isset( $_GET['mdg_refund_notice'] ) ) { echo '<div class="notice notice-success inline"><p>' . esc_html( wp_unslash( $_GET['mdg_refund_notice'] ) ) . '</p></div>'; }
        if ( isset( $_GET['mdg_refund_error'] ) ) { echo '<div class="notice notice-error inline"><p>' . esc_html( wp_unslash( $_GET['mdg_refund_error'] ) ) . '</p></div>'; }
        echo '<div class="notice notice-warning inline"><p><strong>V3.6.3 güvenlik modu:</strong> 1 TL gerçek PayTR test iadesi doğrulandıktan sonra finansal işlem düğmesi bu sürümde yeniden kapatılmıştır. Bu ekran <strong>salt okunur Tickera eşlemesi</strong> yapar; etkinlik iptal edilmez, bilet geçersizleştirilmez ve karta yeni para iadesi gönderilmez.</p></div>';

        self::render_paytr_card( $paytr );
        self::render_event_selector( $events, $event_id );

        if ( ! $event ) {
            echo '<div class="mdg-panel"><p>İade önizlemesi için bir etkinlik seçin.</p></div></div>';
            return;
        }

        $data = self::event_refund_preview( $event_id );
        self::render_event_summary( $event, $data );
        self::render_orders( $event, $data, $paytr );
        if ( class_exists( 'MDG_Ticket_Invalidation_Dry_Run' ) ) { MDG_Ticket_Invalidation_Dry_Run::render_panel( $event_id ); }
        self::render_future_flow();
        echo '</div>';
    }

    private static function render_paytr_card( array $paytr ) {
        $ok = ! empty( $paytr['gateway_found'] );
        $native_ok = ! empty( $paytr['native_refund_ready'] );
        echo '<div class="mdg-panel" style="margin-top:18px"><h2>PayTR İade Bağlantı Durumu</h2>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px">';
        self::mini_card( 'PayTR Ağ Geçidi', $ok ? 'Hazır' : 'Kontrol', $paytr['gateway_label'] ?: 'Bulunamadı', $ok );
        self::mini_card( 'WooCommerce İade Desteği', ! empty( $paytr['supports_refunds'] ) ? 'Hazır' : 'Kontrol', ! empty( $paytr['supports_refunds'] ) ? 'Gateway refunds desteğini bildiriyor' : 'refunds desteği algılanamadı', ! empty( $paytr['supports_refunds'] ) );
        self::mini_card( 'PayTR process_refund', ! empty( $paytr['process_refund_callable'] ) ? 'Hazır' : 'Kontrol', ! empty( $paytr['process_refund_callable'] ) ? 'Yerleşik kart iade fonksiyonu kullanılabilir' : 'Fonksiyon algılanamadı', ! empty( $paytr['process_refund_callable'] ) );
        self::mini_card( 'Karta İade Yolu', $native_ok ? 'Hazır' : 'Kapalı', $native_ok ? 'WooCommerce → PayTR yerleşik iade' : 'Gateway yeteneği doğrulanmalı', $native_ok );
        self::mini_card( 'Doğrudan PayTR Durum API', ! empty( $paytr['credentials_complete'] ) ? 'İsteğe Bağlı' : 'Kullanılmıyor', ! empty( $paytr['credentials_complete'] ) ? 'Kimlik bilgileri algılandı' : 'Native iade için gerekli değil', true );
        echo '</div>';
        echo '<p class="description" style="margin-top:10px"><strong>Yeni yaklaşım:</strong> gerçek iadeyi PayTR anahtarlarını ikinci kez saklayarak değil, WooCommerce\'in iade mekanizması üzerinden mevcut PayTR ağ geçidine devredeceğiz. Böylece kart iadesi PayTR eklentisinin kendi <code>process_refund</code> akışıyla yapılır.</p></div>';
    }

    private static function mini_card( $label, $state, $detail, $ok ) {
        echo '<div style="border:1px solid #dcdcde;border-radius:10px;padding:12px;background:#fff">';
        echo '<strong style="display:block;color:' . esc_attr( $ok ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $state ) . '</strong>';
        echo '<span>' . esc_html( $label ) . '</span><small style="display:block;color:#646970;margin-top:4px">' . esc_html( $detail ) . '</small></div>';
    }

    private static function render_event_selector( array $events, $selected ) {
        echo '<form method="get" class="mdg-panel" style="margin-top:18px">';
        echo '<input type="hidden" name="page" value="mdg-cancel">';
        echo '<label style="font-weight:700;display:block;margin-bottom:6px">Etkinlik</label>';
        echo '<select name="event_id" onchange="this.form.submit()" style="min-width:420px;max-width:100%"><option value="0">Etkinlik seçin</option>';
        foreach ( $events as $e ) {
            $label = $e->title . ' — ' . $e->province_name . ' / ' . $e->district . ' — ' . MDG_Status::label( $e->status );
            echo '<option value="' . esc_attr( (int) $e->id ) . '"' . selected( (int) $selected, (int) $e->id, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select><noscript> '; submit_button( 'Göster', 'secondary', 'submit', false ); echo '</noscript></form>';
    }

    private static function render_event_summary( $event, array $data ) {
        echo '<div class="mdg-panel" style="margin-top:18px"><h2>' . esc_html( $event->title ) . '</h2>';
        echo '<p><strong>Konum:</strong> ' . esc_html( $event->province_name . ' / ' . $event->district . ' — ' . $event->venue_name ) . ' &nbsp; <strong>Durum:</strong> ' . esc_html( MDG_Status::label( $event->status ) ) . '</p>';
        echo '<div class="mdg-cards">';
        self::summary_card( 'Etkilenen Ödenmiş Sipariş', $data['summary']['orders'] );
        self::summary_card( 'Bilet Adedi', $data['summary']['tickets'] );
        self::summary_card( 'Kişi / Kapasite', $data['summary']['units'] );
        self::summary_card( 'Etkinliğe Ait Ödenmiş Tutar', self::money( $data['summary']['event_paid'] ) );
        self::summary_card( 'Muhafazakâr İade Üst Sınırı', self::money( $data['summary']['safe_refundable'] ) );
        echo '</div>';
        echo '<p class="description"><strong>Muhafazakâr iade üst sınırı:</strong> siparişin daha önceki WooCommerce iadeleri hesaba katılarak, bu etkinliğin siparişteki bilet satırlarının ödenmiş tutarını aşmayacak şekilde hesaplanır. Karışık sepetlerde başka etkinlik/ürün bedeli iade önizlemesine dahil edilmez.</p></div>';
    }

    private static function summary_card( $label, $value ) {
        echo '<div class="mdg-card"><div class="mdg-card-value">' . esc_html( $value ) . '</div><div>' . esc_html( $label ) . '</div></div>';
    }

    private static function render_orders( $event, array $data, array $paytr ) {
        echo '<div class="mdg-panel" style="margin-top:18px"><h2>İade Önizleme Listesi</h2>';
        if ( empty( $data['rows'] ) ) {
            echo '<p>Bu etkinlik için iade önizlemesine girecek ücretli sipariş bulunamadı.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        foreach ( array( 'Sipariş','Müşteri','Ödeme','Sipariş Toplamı','Etkinlik Tutarı','Mevcut Woo İadesi','Güvenli İade Üst Sınırı','PayTR Merchant OID','Karta İade Yolu','İşlem' ) as $h ) {
            echo '<th>' . esc_html( $h ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ( $data['rows'] as $row ) {
            $order = $row['order'];
            $paytr_method = self::is_paytr_order( $order );
            $native = self::native_refund_capability_for_order( $order );
            echo '<tr>';
            echo '<td><strong>#' . esc_html( $order->get_order_number() ) . '</strong><br><small>' . esc_html( self::date_label( $order->get_date_paid() ?: $order->get_date_created() ) ) . '</small></td>';
            echo '<td>' . esc_html( trim( $order->get_formatted_billing_full_name() ) ?: '—' ) . '<br><small>' . esc_html( $order->get_billing_email() ) . '</small></td>';
            echo '<td>' . esc_html( $order->get_payment_method_title() ?: $order->get_payment_method() ) . '<br><small>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</small></td>';
            echo '<td>' . esc_html( self::money( (float) $order->get_total() ) ) . '</td>';
            echo '<td><strong>' . esc_html( self::money( $row['event_amount'] ) ) . '</strong><br><small>' . esc_html( (int) $row['tickets'] . ' bilet · ' . (int) $row['units'] . ' kişi' ) . '</small></td>';
            echo '<td>' . esc_html( self::money( $row['woo_refunded'] ) ) . '</td>';
            echo '<td><strong>' . esc_html( self::money( $row['safe_refundable'] ) ) . '</strong></td>';
            echo '<td><code>' . esc_html( $row['merchant_oid_display'] ?: 'Belirlenmedi' ) . '</code><br><small>' . esc_html( $row['merchant_oid_source'] ?: '' ) . '</small></td>';
            echo '<td>';
            if ( ! $paytr_method ) {
                echo '<span style="color:#646970">PayTR siparişi değil</span>';
            } elseif ( ! empty( $native['ready'] ) ) {
                echo '<span style="color:#087c2f"><strong>Hazır</strong></span><br><small>WooCommerce → PayTR</small>';
            } else {
                echo '<span style="color:#b32d2e"><strong>Kontrol gerekli</strong></span><br><small>' . esc_html( $native['reason'] ) . '</small>';
            }
            echo '</td>';
            echo '<td>';
            if ( method_exists( $order, 'get_edit_order_url' ) ) {
                $edit = $order->get_edit_order_url();
                if ( $edit ) { echo '<a class="button" href="' . esc_url( $edit ) . '">Siparişi Aç</a>'; }
            }
            echo '<br><small style="display:block;margin-top:6px;color:#646970">Tek sipariş gerçek test iadesi aşağıdaki kontrollü bölümden yapılabilir.</small></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_status_result( $result ) {
        if ( ! is_array( $result ) || empty( $result ) ) { return '<span style="color:#646970">Sorgulanmadı</span>'; }
        if ( empty( $result['ok'] ) ) {
            return '<span style="color:#b32d2e"><strong>Hata</strong></span><br><small>' . esc_html( (string) ( $result['message'] ?? 'Sorgu başarısız' ) ) . '</small>';
        }
        $r = $result['data'];
        $returns = is_array( $r['returns'] ?? null ) ? $r['returns'] : array();
        $returned = self::sum_paytr_returns( $returns );
        $text = '<span style="color:#087c2f"><strong>Başarılı</strong></span>';
        $text .= '<br><small>Ödeme: ' . esc_html( self::money( self::parse_amount( $r['payment_total'] ?? $r['payment_amount'] ?? 0 ) ) ) . ' ' . esc_html( (string) ( $r['currency'] ?? '' ) ) . '</small>';
        if ( ! empty( $r['masked_pan'] ) ) { $text .= '<br><small>Kart: ' . esc_html( (string) $r['masked_pan'] ) . '</small>'; }
        if ( $returned > 0 ) { $text .= '<br><small>PayTR iadeleri: ' . esc_html( self::money( $returned ) ) . '</small>'; }
        if ( ! empty( $result['merchant_oid'] ) ) { $text .= '<br><small>OID: <code>' . esc_html( $result['merchant_oid'] ) . '</code></small>'; }
        return $text;
    }

    private static function render_future_flow() {
        echo '<div class="mdg-panel" style="margin-top:18px"><h2>Bir Sonraki Güvenli Aşama</h2>';
        echo "<ol><li>Tickera dry-run içinde sipariş başına beklenen bilet adedi ile gerçek <code>tc_tickets_instances</code> adedini eşleştir.</li><li>Tüm instance'ların seçili MDG etkinliğine ve doğru WooCommerce siparişine kesin bağlandığını doğrula.</li><li>Check-in yapılmış bilet varsa otomatik tam iade zincirini durdurup manuel incelemeye ayır.</li><li>V3.6.4'te tek test siparişinin kalan tutarını PayTR üzerinden tam iade et.</li><li>Yalnız iade başarılı olduktan sonra doğrulanmış Tickera instance'larını geri izlenebilir soft-invalidation ile geçersizleştir ve audit log'a yaz.</li></ol>";
        echo '<p><strong>V3.6.3’te yeni finansal işlem ve bilet değişikliği yoktur.</strong> Önce gerçek bilet instance kimliklerini kesinleştiriyoruz.</p></div>';
    }

    public static function status_query_action() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkisiz işlem.' ); }
        $event_id = absint( $_GET['event_id'] ?? 0 );
        $order_id = absint( $_GET['order_id'] ?? 0 );
        check_admin_referer( 'mdg_paytr_status_query_' . $order_id );

        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        if ( ! $order || ! self::order_belongs_to_event( $order_id, $event_id ) ) {
            self::redirect_with_message( $event_id, 'Sipariş etkinlik ile eşleşmiyor.', true );
        }
        if ( ! self::is_paytr_order( $order ) ) {
            self::redirect_with_message( $event_id, 'Bu sipariş PayTR ödeme yöntemiyle oluşturulmamış.', true );
        }

        $paytr = self::paytr_gateway_info();
        if ( empty( $paytr['credentials_complete'] ) ) {
            self::redirect_with_message( $event_id, 'PayTR Merchant ID / Key / Salt bilgileri WooCommerce ağ geçidinden algılanamadı.', true );
        }

        $candidates = self::merchant_oid_candidates( $order );
        if ( ! $candidates ) {
            self::redirect_with_message( $event_id, 'PayTR merchant_oid adayı bulunamadı.', true );
        }

        $last = null;
        foreach ( $candidates as $candidate ) {
            $res = self::paytr_status_query( $paytr, $candidate['value'] );
            $last = $res;
            if ( ! empty( $res['ok'] ) ) { break; }
        }
        if ( ! $last ) { $last = array( 'ok'=>false, 'message'=>'Durum sorgusu çalıştırılamadı.' ); }

        $last['queried_at'] = time();
        set_transient( self::status_cache_key( $event_id, $order_id ), $last, 30 * MINUTE_IN_SECONDS );
        self::redirect_with_message( $event_id, ! empty( $last['ok'] ) ? 'PayTR durum sorgusu başarılı.' : ( $last['message'] ?? 'PayTR durum sorgusu başarısız.' ), empty( $last['ok'] ) );
    }

    private static function paytr_status_query( array $paytr, $merchant_oid ) {
        $merchant_oid = sanitize_text_field( (string) $merchant_oid );
        if ( '' === $merchant_oid || strlen( $merchant_oid ) > 64 ) { return array( 'ok'=>false, 'message'=>'Geçersiz merchant_oid.' ); }
        $token = base64_encode( hash_hmac( 'sha256', $paytr['merchant_id'] . $merchant_oid . $paytr['merchant_salt'], $paytr['merchant_key'], true ) );
        $response = wp_remote_post( self::STATUS_ENDPOINT, array(
            'timeout' => 20,
            'redirection' => 0,
            'sslverify' => true,
            'body' => array(
                'merchant_id' => $paytr['merchant_id'],
                'merchant_oid' => $merchant_oid,
                'paytr_token' => $token,
            ),
        ) );
        if ( is_wp_error( $response ) ) { return array( 'ok'=>false, 'message'=>$response->get_error_message(), 'merchant_oid'=>$merchant_oid ); }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $json = json_decode( $body, true );
        if ( 200 !== $code || ! is_array( $json ) ) { return array( 'ok'=>false, 'message'=>'PayTR geçersiz HTTP/JSON yanıtı döndürdü.', 'merchant_oid'=>$merchant_oid ); }
        if ( 'success' !== (string) ( $json['status'] ?? '' ) ) {
            $msg = trim( (string) ( $json['err_msg'] ?? 'PayTR ödeme kaydı bulunamadı.' ) );
            return array( 'ok'=>false, 'message'=>$msg, 'merchant_oid'=>$merchant_oid, 'data'=>$json );
        }
        return array( 'ok'=>true, 'message'=>'success', 'merchant_oid'=>$merchant_oid, 'data'=>$json );
    }

    private static function event_refund_preview( $event_id ) {
        global $wpdb;
        $map = MDG_DB::table( 'order_map' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT order_id, SUM(quantity) tickets, SUM(units_total) units, GROUP_CONCAT(order_item_id ORDER BY order_item_id) item_ids
             FROM {$map}
             WHERE event_id=%d AND paid_at IS NOT NULL AND order_status NOT IN ('failed','cancelled','refunded')
             GROUP BY order_id ORDER BY order_id DESC",
            $event_id
        ) );

        $out = array();
        $summary = array( 'orders'=>0, 'tickets'=>0, 'units'=>0, 'event_paid'=>0.0, 'safe_refundable'=>0.0 );
        foreach ( (array) $rows as $r ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $r->order_id ) : null;
            if ( ! $order || ! $order->get_date_paid() ) { continue; }
            if ( in_array( $order->get_status(), array( 'failed','cancelled','refunded','trash' ), true ) ) { continue; }

            $item_ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $r->item_ids ) ) ) );
            $event_amount = 0.0;
            foreach ( $item_ids as $item_id ) {
                $item = $order->get_item( $item_id );
                if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) { continue; }
                $event_amount += (float) $item->get_total() + (float) $item->get_total_tax();
            }
            if ( $event_amount <= 0 ) { continue; }

            $woo_refunded = abs( (float) $order->get_total_refunded() );
            $order_remaining = max( 0.0, (float) $order->get_total() - $woo_refunded );
            $safe = min( $event_amount, $order_remaining );
            $candidates = self::merchant_oid_candidates( $order );
            $first = $candidates[0] ?? array( 'value'=>'', 'source'=>'' );

            $out[] = array(
                'order' => $order,
                'tickets' => (int) $r->tickets,
                'units' => (int) $r->units,
                'event_amount' => round( $event_amount, 2 ),
                'woo_refunded' => round( $woo_refunded, 2 ),
                'safe_refundable' => round( $safe, 2 ),
                'merchant_oid_display' => (string) $first['value'],
                'merchant_oid_source' => (string) $first['source'],
            );
            $summary['orders']++;
            $summary['tickets'] += (int) $r->tickets;
            $summary['units'] += (int) $r->units;
            $summary['event_paid'] += $event_amount;
            $summary['safe_refundable'] += $safe;
        }
        $summary['event_paid'] = round( $summary['event_paid'], 2 );
        $summary['safe_refundable'] = round( $summary['safe_refundable'], 2 );
        return array( 'rows'=>$out, 'summary'=>$summary );
    }

    private static function events_with_sales() {
        global $wpdb;
        $e = MDG_DB::table( 'events' );
        $m = MDG_DB::table( 'order_map' );
        return $wpdb->get_results(
            "SELECT e.*, COUNT(DISTINCT m.order_id) AS paid_orders
             FROM {$e} e
             LEFT JOIN {$m} m ON m.event_id=e.id AND m.paid_at IS NOT NULL
             WHERE e.status IN ('onsale','closed','soldout','postponed','cancelled','completed')
             GROUP BY e.id
             HAVING paid_orders > 0
             ORDER BY e.updated_at DESC, e.id DESC"
        );
    }

    private static function order_belongs_to_event( $order_id, $event_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MDG_DB::table( 'order_map' ) . ' WHERE order_id=%d AND event_id=%d LIMIT 1', $order_id, $event_id ) );
    }

    private static function paytr_gateway_info() {
        $out = array(
            'gateway_found'=>false, 'gateway_id'=>'', 'gateway_label'=>'',
            'merchant_id'=>'', 'merchant_key'=>'', 'merchant_salt'=>'',
            'has_merchant_id'=>false, 'has_merchant_key'=>false, 'has_merchant_salt'=>false,
            'credentials_complete'=>false,
            'supports_refunds'=>false, 'process_refund_callable'=>false, 'native_refund_ready'=>false,
        );
        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) { return $out; }
        try { $gateways = WC()->payment_gateways()->payment_gateways(); } catch ( Throwable $e ) { return $out; }
        foreach ( (array) $gateways as $id => $gateway ) {
            $haystack = strtolower( (string) $id . ' ' . (string) ( $gateway->method_title ?? '' ) . ' ' . (string) ( $gateway->title ?? '' ) . ' ' . get_class( $gateway ) );
            if ( false === strpos( $haystack, 'paytr' ) ) { continue; }
            $out['gateway_found'] = true;
            $out['gateway_id'] = (string) $id;
            $out['gateway_label'] = trim( (string) ( $gateway->title ?? $gateway->method_title ?? $id ) ) . ' (' . $id . ')';
            $settings = array();
            if ( isset( $gateway->settings ) && is_array( $gateway->settings ) ) { $settings = $gateway->settings; }
            $opt = get_option( 'woocommerce_' . $id . '_settings', array() );
            if ( is_array( $opt ) ) { $settings = array_merge( $opt, $settings ); }

            $out['merchant_id']   = self::setting_value( $settings, array( 'merchant_id','merchantid','merchant_no','merchantno','store_id','magaza_no' ) );
            $out['merchant_key']  = self::setting_value( $settings, array( 'merchant_key','merchantkey','merchant_password','merchant_pass','magaza_parola' ) );
            $out['merchant_salt'] = self::setting_value( $settings, array( 'merchant_salt','merchantsalt','merchant_secret','magaza_gizli_anahtar' ) );

            // Some gateway classes expose these as properties rather than in settings.
            foreach ( array( 'merchant_id','merchant_key','merchant_salt' ) as $k ) {
                if ( empty( $out[ $k ] ) && isset( $gateway->{$k} ) && is_scalar( $gateway->{$k} ) ) { $out[ $k ] = trim( (string) $gateway->{$k} ); }
            }
            $out['supports_refunds'] = method_exists( $gateway, 'supports' ) && $gateway->supports( 'refunds' );
            $out['process_refund_callable'] = is_callable( array( $gateway, 'process_refund' ) );
            $out['native_refund_ready'] = $out['supports_refunds'] && $out['process_refund_callable'];
            break;
        }
        $out['has_merchant_id'] = '' !== $out['merchant_id'];
        $out['has_merchant_key'] = '' !== $out['merchant_key'];
        $out['has_merchant_salt'] = '' !== $out['merchant_salt'];
        $out['credentials_complete'] = $out['has_merchant_id'] && $out['has_merchant_key'] && $out['has_merchant_salt'];
        return $out;
    }

    private static function setting_value( array $settings, array $keys ) {
        $norm = array();
        foreach ( $settings as $k=>$v ) { if ( is_scalar( $v ) ) { $norm[ strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $k ) ) ] = trim( (string) $v ); } }
        foreach ( $keys as $key ) {
            $nk = strtolower( preg_replace( '/[^a-z0-9_]/i', '', $key ) );
            if ( isset( $norm[ $nk ] ) && '' !== $norm[ $nk ] ) { return $norm[ $nk ]; }
        }
        return '';
    }

    private static function merchant_oid_candidates( $order ) {
        $out = array();
        $seen = array();
        $add = function ( $value, $source ) use ( &$out, &$seen ) {
            $value = trim( (string) $value );
            if ( '' === $value || strlen( $value ) > 64 || isset( $seen[ $value ] ) ) { return; }
            if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) { return; }
            $seen[ $value ] = 1; $out[] = array( 'value'=>$value, 'source'=>$source );
        };

        foreach ( array( 'merchant_oid','_merchant_oid','paytr_merchant_oid','_paytr_merchant_oid','paytr_order_id','_paytr_order_id','paytr_oid','_paytr_oid' ) as $key ) {
            $v = $order->get_meta( $key, true );
            if ( is_scalar( $v ) ) { $add( $v, 'Sipariş meta: ' . $key ); }
        }
        // Discover PayTR/OID-like metadata without exposing unrelated sensitive values.
        foreach ( $order->get_meta_data() as $meta ) {
            $key = method_exists( $meta, 'get_data' ) ? (string) ( $meta->get_data()['key'] ?? '' ) : '';
            $lk = strtolower( $key );
            if ( false === strpos( $lk, 'paytr' ) && false === strpos( $lk, 'merchant_oid' ) ) { continue; }
            $value = method_exists( $meta, 'get_data' ) ? ( $meta->get_data()['value'] ?? '' ) : '';
            if ( is_scalar( $value ) ) { $add( $value, 'Sipariş meta: ' . $key ); }
        }
        $add( $order->get_order_number(), 'WooCommerce sipariş numarası' );
        $add( $order->get_id(), 'WooCommerce order ID' );
        return array_slice( $out, 0, 10 );
    }

    private static function is_paytr_order( $order ) {
        $s = strtolower( (string) $order->get_payment_method() . ' ' . (string) $order->get_payment_method_title() );
        return false !== strpos( $s, 'paytr' );
    }

    private static function native_refund_capability_for_order( $order ) {
        $out = array( 'ready'=>false, 'reason'=>'PayTR ağ geçidi bulunamadı.' );
        if ( ! $order || ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) { return $out; }
        $method_id = (string) $order->get_payment_method();
        try { $gateways = WC()->payment_gateways()->payment_gateways(); } catch ( Throwable $e ) { return $out; }
        $gateway = $gateways[ $method_id ] ?? null;
        if ( ! $gateway ) {
            foreach ( (array) $gateways as $id => $g ) {
                $haystack = strtolower( (string) $id . ' ' . (string) ( $g->method_title ?? '' ) . ' ' . (string) ( $g->title ?? '' ) . ' ' . get_class( $g ) );
                if ( false !== strpos( $haystack, 'paytr' ) ) { $gateway = $g; break; }
            }
        }
        if ( ! $gateway ) { return $out; }
        $supports = method_exists( $gateway, 'supports' ) && $gateway->supports( 'refunds' );
        $callable = is_callable( array( $gateway, 'process_refund' ) );
        if ( ! $supports ) { return array( 'ready'=>false, 'reason'=>'Gateway refunds desteğini bildirmiyor.' ); }
        if ( ! $callable ) { return array( 'ready'=>false, 'reason'=>'process_refund fonksiyonu çağrılabilir değil.' ); }
        return array( 'ready'=>true, 'reason'=>'Hazır' );
    }

    private static function cached_status_result( $event_id, $order_id ) {
        $v = get_transient( self::status_cache_key( $event_id, $order_id ) );
        return is_array( $v ) ? $v : array();
    }

    private static function status_cache_key( $event_id, $order_id ) {
        return 'mdg_paytr_status_' . get_current_user_id() . '_' . absint( $event_id ) . '_' . absint( $order_id );
    }

    private static function sum_paytr_returns( array $returns ) {
        $sum = 0.0;
        foreach ( $returns as $r ) { if ( is_array( $r ) ) { $sum += self::parse_amount( $r['return_amount'] ?? 0 ); } }
        return round( $sum, 2 );
    }

    private static function parse_amount( $value ) {
        if ( is_numeric( $value ) ) { return (float) $value; }
        $s = trim( (string) $value );
        if ( '' === $s ) { return 0.0; }
        // PayTR may return localized decimal strings. Normalize conservatively.
        if ( false !== strpos( $s, ',' ) && false === strpos( $s, '.' ) ) { $s = str_replace( ',', '.', $s ); }
        else { $s = str_replace( ',', '', $s ); }
        return is_numeric( $s ) ? (float) $s : 0.0;
    }

    private static function date_label( $date ) {
        if ( ! $date ) { return '—'; }
        try { return $date->date_i18n( 'd.m.Y H:i' ); } catch ( Throwable $e ) { return '—'; }
    }

    private static function money( $amount ) {
        if ( function_exists( 'wc_price' ) ) { return wp_strip_all_tags( wc_price( (float) $amount ) ); }
        return number_format_i18n( (float) $amount, 2 ) . ' TL';
    }

    private static function redirect_with_message( $event_id, $message, $error = false ) {
        $args = array( 'page'=>'mdg-cancel', 'event_id'=>absint( $event_id ) );
        $args[ $error ? 'mdg_refund_error' : 'mdg_refund_notice' ] = rawurlencode( (string) $message );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
