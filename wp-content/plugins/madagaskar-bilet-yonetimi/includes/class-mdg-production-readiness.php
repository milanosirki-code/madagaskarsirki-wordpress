<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Production_Readiness {
    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $events = class_exists( 'MDG_Events' ) ? MDG_Events::drafts() : array();

        echo '<div class="mdg-panel"><h2>Canlı Yayın Hazırlık Kontrolü</h2>';
        echo '<p>Bu ekran içerik, salon, QR, WooCommerce, Tickera, PayTR ve ortak kapasite zincirini doğrular. Tüm kontroller geçtiğinde kontrollü canlı yayın düğmesi açılır.</p>';
        if ( isset( $_GET['mdg_publish_error'] ) ) { echo '<div class="notice notice-error inline"><p>' . esc_html( wp_unslash( $_GET['mdg_publish_error'] ) ) . '</p></div>'; }

        $infra = self::infrastructure();
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin:16px 0">';
        foreach ( $infra as $item ) {
            $ok = ! empty( $item['ok'] );
            echo '<div style="border:1px solid #dcdcde;border-radius:10px;padding:12px;background:#fff">';
            echo '<strong style="display:block;color:' . esc_attr( $ok ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $ok ? 'Hazır' : 'Kontrol' ) . '</strong>';
            echo '<span>' . esc_html( $item['label'] ) . '</span>';
            if ( ! empty( $item['detail'] ) ) { echo '<small style="display:block;margin-top:4px;color:#646970">' . esc_html( $item['detail'] ) . '</small>'; }
            echo '</div>';
        }
        echo '</div>';

        if ( self::duplicate_plugin_copies() ) {
            echo '<div class="notice notice-warning inline"><p><strong>Eski eklenti kopyası algılandı.</strong> Canlı yayından önce pasif eski “Madagaskar Bilet Yönetimi” kopyalarını Eklentiler ekranından silmek iyi olur. Aktif sürümü silmeyin.</p></div>';
        }

        if ( ! $events ) {
            echo '<p>Kontrol edilecek taslak etkinlik bulunamadı.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Etkinlik</th><th>İçerik</th><th>Salon / QR</th><th>Satış Mapping</th><th>Bilet Şablonu</th><th>Sonuç</th><th>Önizleme</th></tr></thead><tbody>';
        foreach ( $events as $event ) {
            $check = self::check_event( $event );
            echo '<tr>';
            echo '<td><strong>#' . esc_html( (int) $event->id ) . ' ' . esc_html( $event->title ) . '</strong><br><small>' . esc_html( $event->province_name . ' / ' . $event->district ) . '</small></td>';
            self::cell( $check['content'] );
            self::cell( $check['venue'] );
            self::cell( $check['sales'] );
            self::cell( $check['template'] );
            echo '<td>';
            if ( $check['ready'] ) {
                echo '<span class="mdg-status is-active">CANLI YAYINA HAZIR</span>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px" onsubmit="return confirm(&quot;Bu etkinliği halka açık satışa almak istediğinizi onaylıyor musunuz?&quot;);">';
                echo '<input type="hidden" name="action" value="mdg_publish_event_live"><input type="hidden" name="event_id" value="' . esc_attr( (int) $event->id ) . '">';
                wp_nonce_field( 'mdg_publish_event_live_' . (int) $event->id, 'mdg_nonce' );
                echo '<label style="display:block;margin:8px 0"><input type="checkbox" name="confirm_live" value="1" required> Mevcut WooCommerce/Tickera satış eşleşmelerini kullanarak bu etkinliği halka açmayı onaylıyorum.</label>';
                submit_button( 'Etkinliği Canlı Yayına Al', 'primary', 'submit', false );
                echo '</form>';
            } else {
                echo '<span class="mdg-status" style="background:#fff2cc;color:#664d03">EKSİK VAR</span><br><small>' . esc_html( implode( ' • ', $check['errors'] ) ) . '</small>';
            }
            echo '</td>';
            $url = class_exists( 'MDG_Public_Event' ) ? MDG_Public_Event::preview_url( $event ) : '';
            echo '<td>' . ( $url ? '<a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Müşteri Sayfasını Önizle ↗</a>' : '—' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo "<p class=\"description\" style=\"margin-top:12px\">V3.0 kontrollü yayında mevcut publish eşleşmeleri aynen kullanılabilir; V2.9 tarafından güvenli production key ile üretilmiş taslak satış nesneleri ise tek işlemde publish edilir. Yeni kopya oluşturulmaz, fiyat değiştirilmez ve ortak seans kapasitesi korunur.</p>";
        echo '</div>';
    }

    private static function cell( array $item ) {
        $ok = ! empty( $item['ok'] );
        echo '<td><strong style="color:' . esc_attr( $ok ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $ok ? 'Hazır' : 'Eksik' ) . '</strong>';
        if ( ! empty( $item['detail'] ) ) { echo '<br><small>' . esc_html( $item['detail'] ) . '</small>'; }
        echo '</td>';
    }

    public static function infrastructure() {
        $woo = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
        $tickera = post_type_exists( 'tc_events' ) && post_type_exists( 'tc_tickets_instances' );
        $bridge = self::plugin_active_contains( 'bridge-for-woocommerce/bridge-for-woocommerce.php' );
        $paytr = false;
        $paytr_detail = 'Etkin ağ geçidi bulunamadı';

        if ( $woo && function_exists( 'WC' ) ) {
            try {
                $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
                foreach ( (array) $gateways as $id => $gateway ) {
                    $haystack = strtolower( (string) $id . ' ' . (string) ( $gateway->method_title ?? '' ) . ' ' . (string) ( $gateway->title ?? '' ) );
                    if ( false !== strpos( $haystack, 'paytr' ) ) {
                        $paytr = isset( $gateway->enabled ) ? ( 'yes' === $gateway->enabled ) : true;
                        $paytr_detail = $paytr ? 'Etkin' : 'Kurulu fakat pasif';
                        break;
                    }
                }
            } catch ( Throwable $e ) {
                $paytr_detail = 'Kontrol edilemedi';
            }
        }

        $capacity = class_exists( 'MDG_Capacity' ) && class_exists( 'MDG_Live_Sales' );
        return array(
            array( 'label'=>'WooCommerce', 'ok'=>$woo, 'detail'=>$woo && defined( 'WC_VERSION' ) ? WC_VERSION : '' ),
            array( 'label'=>'Tickera', 'ok'=>$tickera, 'detail'=>$tickera ? 'Etkinlik + bilet instance hazır' : '' ),
            array( 'label'=>'Bridge for WooCommerce', 'ok'=>$bridge, 'detail'=>$bridge ? 'Aktif' : 'Aktif değil' ),
            array( 'label'=>'PayTR', 'ok'=>$paytr, 'detail'=>$paytr_detail ),
            array( 'label'=>'Ortak Seans Kapasitesi', 'ok'=>$capacity, 'detail'=>$capacity ? 'Atomik ödeme öncesi kapasite kilidi hazır' : 'Kapasite motoru eksik' ),
        );
    }

    public static function check_event( $event ) {
        $errors = array();

        $content_ok = ! empty( $event->title ) && ! empty( $event->hero_attachment_id ) && ( ! empty( $event->short_description ) || ! empty( $event->long_description ) );
        if ( ! $content_ok ) { $errors[] = 'İçerik/görsel'; }
        $content_detail = $content_ok ? 'Başlık, hero ve tanıtım hazır' : 'Başlık + hero + tanıtım metni gerekli';

        $venue_ok = ! empty( $event->venue_id ) && ! empty( $event->venue_address ) && ! empty( $event->venue_maps_url ) && ! empty( $event->venue_qr_attachment_id );
        if ( ! $venue_ok ) { $errors[] = 'Salon/Maps/QR'; }
        $venue_detail = $venue_ok ? $event->venue_name . ' • Maps + QR hazır' : 'Adres, Maps ve otomatik QR tamamlanmalı';

        $sales_plan = self::sales_publish_plan( $event );
        $sales_ok = ! empty( $sales_plan['ready'] );
        if ( ! $sales_ok ) { $errors[] = 'Satış mapping'; }
        if ( $sales_ok ) {
            $draft_note = ! empty( $sales_plan['needs_publish'] ) ? ' • kontrollü publish bekliyor' : '';
            $sales_detail = (int) $sales_plan['session_count'] . ' seans • ' . count( $sales_plan['product_ids'] ) . ' ürün • ' . count( $sales_plan['variation_ids'] ) . ' varyasyon' . $draft_note;
        } else {
            $sales_detail = ! empty( $sales_plan['errors'] ) ? implode( ' • ', array_slice( $sales_plan['errors'], 0, 3 ) ) : 'Seans/ürün/varyasyon veya fiyat eşleşmesi eksik';
        }

        global $wpdb;
        $bindings = get_option( 'mdg_ticket_template_qr_bindings_v1', array() );
        $binding = is_array( $bindings ) && ! empty( $bindings[ (int) $event->id ] ) ? $bindings[ (int) $event->id ] : array();
        $template_ok = ! empty( $binding['clone_template_id'] ) && ! empty( $binding['qr_attachment_id'] );
        if ( $template_ok ) {
            $tt = $wpdb->prefix . 'tickera_ticket_templates';
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tt} WHERE id=%d AND status='active'", (int) $binding['clone_template_id'] ) );
            $template_ok = (bool) $exists;
        }
        if ( ! $template_ok ) { $errors[] = 'Dinamik salon QR şablonu'; }
        $template_detail = $template_ok ? 'Designer #' . (int) $binding['clone_template_id'] . ' bağlı' : 'Etkinlik bazlı QR şablonu bağlanmalı';

        foreach ( self::infrastructure() as $i ) {
            if ( empty( $i['ok'] ) ) { $errors[] = $i['label']; }
        }

        return array(
            'ready' => empty( $errors ),
            'errors' => array_values( array_unique( $errors ) ),
            'content' => array( 'ok'=>$content_ok, 'detail'=>$content_detail ),
            'venue' => array( 'ok'=>$venue_ok, 'detail'=>$venue_detail ),
            'sales' => array( 'ok'=>$sales_ok, 'detail'=>$sales_detail ),
            'template' => array( 'ok'=>$template_ok, 'detail'=>$template_detail ),
            'sales_plan' => $sales_plan,
        );
    }

    /**
     * Canlı yayın öncesi satış nesnelerini doğrular.
     *
     * İki güvenli senaryo desteklenir:
     * 1) Ankara gibi zaten publish durumundaki mevcut eşleşmeler.
     * 2) V2.9 üreticisinin aynı MDG etkinliği + aynı production key ile oluşturduğu
     *    draft/private ürünler ve Tickera etkinliği. Bunlar kontrollü publish sırasında
     *    halka açılır. Başka taslak ürünler asla kabul edilmez.
     */
    public static function sales_publish_plan( $event ) {
        global $wpdb;
        $out = array(
            'ready' => false,
            'errors' => array(),
            'session_count' => 0,
            'product_ids' => array(),
            'variation_ids' => array(),
            'tickera_event_id' => 0,
            'production_key' => '',
            'managed_generated' => false,
            'needs_publish' => false,
        );
        if ( ! $event || empty( $event->id ) ) { $out['errors'][] = 'MDG etkinliği bulunamadı.'; return $out; }

        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $sessions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$sessions_table} WHERE event_id=%d ORDER BY start_at ASC, id ASC", (int) $event->id ) );
        if ( ! $sessions ) { $out['errors'][] = 'Seans bulunamadı.'; return $out; }
        $out['session_count'] = count( $sessions );

        $future = 0;
        $all_managed = true;
        $keys = array();
        $allowed_parent_status = array( 'publish','draft','private','pending' );
        $allowed_tickera_status = array( 'publish','draft','private','pending' );

        foreach ( $sessions as $session ) {
            if ( strtotime( (string) $session->start_at . ' UTC' ) > time() ) { $future++; }
            $product_id = (int) $session->wc_product_id;
            $tickera_id = (int) $session->tickera_event_id;
            if ( ! $product_id || ! $tickera_id ) { $out['errors'][] = 'Seans #' . (int)$session->id . ' satış nesnesine bağlı değil.'; continue; }

            if ( ! $out['tickera_event_id'] ) { $out['tickera_event_id'] = $tickera_id; }
            elseif ( $out['tickera_event_id'] !== $tickera_id ) { $out['errors'][] = 'Seanslar farklı Tickera etkinliklerine bağlı.'; }

            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
            if ( ! $product || ! $product->is_type( 'variable' ) ) { $out['errors'][] = 'WooCommerce variable ürün #' . $product_id . ' bulunamadı.'; continue; }
            $pstatus = (string) get_post_status( $product_id );
            if ( ! in_array( $pstatus, $allowed_parent_status, true ) ) { $out['errors'][] = 'Ürün #' . $product_id . ' durumu canlı yayına uygun değil: ' . $pstatus; }
            if ( 'publish' !== $pstatus ) { $out['needs_publish'] = true; }
            $out['product_ids'][] = $product_id;

            $pm = self::managed_identity( $product_id );
            if ( ! $pm['managed'] || (int)$pm['event_id'] !== (int)$event->id || ! $pm['production_key'] ) { $all_managed = false; }
            else { $keys[] = $pm['production_key']; }

            $types = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$types_table} WHERE session_id=%d AND is_active=1 ORDER BY sort_order ASC,id ASC", (int) $session->id ) );
            if ( ! $types ) { $out['errors'][] = 'Seans #' . (int)$session->id . ' aktif bilet türü içermiyor.'; }
            foreach ( (array) $types as $type ) {
                $variation_id = (int) $type->wc_variation_id;
                if ( ! $variation_id ) { $out['errors'][] = 'Bilet türü #' . (int)$type->id . ' varyasyona bağlı değil.'; continue; }
                $variation = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : null;
                if ( ! $variation || ! $variation->is_type( 'variation' ) || (int)$variation->get_parent_id() !== $product_id ) {
                    $out['errors'][] = 'Varyasyon #' . $variation_id . ' ürün eşleşmesi geçersiz.'; continue;
                }
                if ( abs( round( (float)$variation->get_price(), 2 ) - round( (float)$type->price, 2 ) ) > 0.009 ) {
                    $out['errors'][] = 'Varyasyon #' . $variation_id . ' fiyatı MDG fiyatıyla eşleşmiyor.';
                }
                $out['variation_ids'][] = $variation_id;
                $vm = self::managed_identity( $variation_id );
                if ( ! $vm['managed'] || (int)$vm['event_id'] !== (int)$event->id || ! $vm['production_key'] ) { $all_managed = false; }
                else { $keys[] = $vm['production_key']; }
            }
        }

        if ( ! $future ) { $out['errors'][] = 'Gelecekte satışa açık seans yok.'; }

        $tickera_id = (int) $out['tickera_event_id'];
        if ( $tickera_id ) {
            if ( 'tc_events' !== get_post_type( $tickera_id ) ) { $out['errors'][] = 'Tickera etkinlik kaydı geçersiz.'; }
            $tstatus = (string) get_post_status( $tickera_id );
            if ( ! in_array( $tstatus, $allowed_tickera_status, true ) ) { $out['errors'][] = 'Tickera etkinlik durumu canlı yayına uygun değil: ' . $tstatus; }
            if ( 'publish' !== $tstatus ) { $out['needs_publish'] = true; }
            $tm = self::managed_identity( $tickera_id );
            if ( ! $tm['managed'] || (int)$tm['event_id'] !== (int)$event->id || ! $tm['production_key'] ) { $all_managed = false; }
            else { $keys[] = $tm['production_key']; }
        }

        $out['product_ids'] = array_values( array_unique( array_map( 'absint', $out['product_ids'] ) ) );
        $out['variation_ids'] = array_values( array_unique( array_map( 'absint', $out['variation_ids'] ) ) );
        $keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );

        // Eğer taslak/private herhangi bir nesne varsa yalnızca V2.9 tarafından aynı
        // production key ile oluşturulmuş tam set kabul edilir.
        if ( $out['needs_publish'] ) {
            if ( ! $all_managed || 1 !== count( $keys ) ) {
                $out['errors'][] = 'Taslak satış nesneleri MDG production key ile güvenli biçimde doğrulanamadı.';
            } else {
                $out['managed_generated'] = true;
                $out['production_key'] = $keys[0];
            }
        } elseif ( $all_managed && 1 === count( $keys ) ) {
            // Daha önce kısmen/tamamen publish edilmiş MDG üretimini idempotent biçimde tanı.
            $out['managed_generated'] = true;
            $out['production_key'] = $keys[0];
        }

        $out['ready'] = empty( $out['errors'] );
        return $out;
    }

    private static function managed_identity( $post_id ) {
        return array(
            'managed' => '1' === (string) get_post_meta( (int)$post_id, '_mdg_managed', true ),
            'event_id' => (int) get_post_meta( (int)$post_id, '_mdg_event_id', true ),
            'production_key' => (string) get_post_meta( (int)$post_id, '_mdg_production_key', true ),
        );
    }

    private static function plugin_active_contains( $basename ) {
        $active = (array) get_option( 'active_plugins', array() );
        if ( in_array( $basename, $active, true ) ) { return true; }
        if ( is_multisite() ) {
            $network = (array) get_site_option( 'active_sitewide_plugins', array() );
            if ( isset( $network[ $basename ] ) ) { return true; }
        }
        return false;
    }

    private static function duplicate_plugin_copies() {
        if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $hits = array();
        foreach ( get_plugins() as $basename => $data ) {
            if ( 'Madagaskar Bilet Yönetimi' === (string) ( $data['Name'] ?? '' ) ) { $hits[] = $basename; }
        }
        return count( $hits ) > 1 ? $hits : array();
    }
}
