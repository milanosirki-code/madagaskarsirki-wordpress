<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tickera 3.6 Ticket Designer için etkinlik-bazlı salon QR şablon bağlayıcı.
 *
 * Yeni Designer paneline özel alan eklemeye çalışmaz. Bunun yerine kullanılan
 * Designer şablonunu etkinlik bazında klonlar, yalnızca sağ-alt statik salon QR
 * görselinin src alanını ilgili salonun MDG QR PNG'siyle değiştirir ve klonu
 * etkinliğe bağlı WooCommerce ürün/varyasyonlarına atar.
 *
 * Böylece aynı temel tasarım korunur; her etkinlik kendi salon QR'ını taşır ve
 * geçmiş etkinliklerin QR'ı başka bir etkinlik açıldığında değişmez.
 */
final class MDG_Ticket_Template_QR_Binder {

    const OPTION_KEY = 'mdg_ticket_template_qr_bindings_v1';

    public static function hooks() {
        add_action( 'admin_post_mdg_bind_event_qr_template', array( __CLASS__, 'handle_bind' ) );
        add_action( 'admin_post_mdg_rollback_event_qr_template', array( __CLASS__, 'handle_rollback' ) );
        add_action( 'mdg_venue_saved', array( __CLASS__, 'maybe_refresh_for_venue' ), 20, 2 );
    }

    public static function render_admin_panel() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }

        $events = self::mapped_events();
        $notice = isset( $_GET['mdg_qr_bind_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['mdg_qr_bind_notice'] ) ) : '';
        $notice_type = isset( $_GET['mdg_qr_bind_type'] ) ? sanitize_key( wp_unslash( $_GET['mdg_qr_bind_type'] ) ) : '';

        echo '<div class="mdg-panel"><h2>Etkinlik Bazlı Salon QR Şablonu</h2>';
        echo '<p><strong>Yeni yöntem:</strong> Ticket Designer paneline özel alan eklemek yerine, mevcut çalışan bilet şablonu her etkinlik için güvenli biçimde klonlanır. Klondaki yalnızca <em>Salon Konumu</em> statik QR görseli salon kaydındaki otomatik QR ile değiştirilir.</p>';
        echo '<p class="description">GİRİŞ QR koduna dokunulmaz. Mevcut temel tasarım silinmez. Her etkinlik kendi klon şablonunu kullandığı için farklı şehirlerin QR kodları birbirine karışmaz.</p>';

        if ( $notice ) {
            $class = ( 'ok' === $notice_type ) ? 'notice-success' : ( 'warn' === $notice_type ? 'notice-warning' : 'notice-error' );
            echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p>' . esc_html( $notice ) . '</p></div>';
        }

        if ( ! $events ) {
            echo '<p>WooCommerce/Tickera mapping tamamlanmış V2 etkinliği bulunamadı.</p></div>';
            return;
        }

        $bindings = self::bindings();
        echo '<table class="widefat striped"><thead><tr><th>V2 Etkinliği</th><th>Salon / QR</th><th>Satış Mapping</th><th>Şablon Durumu</th><th>İşlem</th></tr></thead><tbody>';
        foreach ( $events as $event ) {
            $analysis = self::analyze_event( (int) $event->id );
            $binding = isset( $bindings[ (int) $event->id ] ) ? $bindings[ (int) $event->id ] : array();
            echo '<tr><td><strong>#' . esc_html( (int) $event->id ) . ' ' . esc_html( $event->title ) . '</strong><br>' . esc_html( $event->province_name . ' / ' . $event->district ) . '</td>';
            echo '<td>' . esc_html( $analysis['venue_name'] ?: '—' ) . '<br>';
            if ( $analysis['qr_url'] ) {
                echo '<img src="' . esc_url( $analysis['qr_url'] ) . '" alt="Salon QR" style="width:72px;height:72px;background:#fff;padding:3px;object-fit:contain;margin-top:6px"><br>';
                if ( $analysis['maps_url'] ) { echo '<a href="' . esc_url( $analysis['maps_url'] ) . '" target="_blank" rel="noopener noreferrer">Maps aç ↗</a>'; }
            } else { echo '<span class="mdg-status is-warning">QR eksik</span>'; }
            echo '</td>';
            echo '<td>' . esc_html( count( $analysis['product_ids'] ) ) . ' seans ürünü<br>' . esc_html( count( $analysis['variation_ids'] ) ) . ' varyasyon</td>';
            echo '<td>';
            if ( $binding && ! empty( $binding['clone_template_id'] ) ) {
                echo '<span class="mdg-status is-active">Bağlı</span><br>Designer #' . esc_html( (int) $binding['clone_template_id'] );
            } elseif ( $analysis['base_template_id'] ) {
                echo 'Temel Designer #' . esc_html( (int) $analysis['base_template_id'] );
                if ( ! empty( $analysis['candidate'] ) ) { echo '<br><span class="mdg-status is-active">Salon QR bulundu</span>'; }
                else { echo '<br><span class="mdg-status is-warning">Statik QR adayı bulunamadı</span>'; }
            } else {
                echo '<span class="mdg-status is-warning">Designer şablonu bulunamadı</span>';
            }
            echo '</td><td>';

            if ( $analysis['ready'] ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:8px">';
                wp_nonce_field( 'mdg_bind_event_qr_template_' . (int) $event->id );
                echo '<input type="hidden" name="action" value="mdg_bind_event_qr_template"><input type="hidden" name="event_id" value="' . esc_attr( (int) $event->id ) . '">';
                echo '<button type="submit" class="button button-primary">' . ( $binding ? 'QR Şablonunu Güncelle' : 'Dinamik QR Şablonunu Bağla' ) . '</button></form>';
            } else {
                echo '<span class="description">' . esc_html( implode( ' ', $analysis['errors'] ) ) . '</span>';
            }

            if ( $binding ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'mdg_rollback_event_qr_template_' . (int) $event->id );
                echo '<input type="hidden" name="action" value="mdg_rollback_event_qr_template"><input type="hidden" name="event_id" value="' . esc_attr( (int) $event->id ) . '">';
                echo '<button type="submit" class="button">Önceki Şablona Geri Dön</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description" style="margin-top:12px">İlk bağlamada temel Ticket Designer şablonunun bir kopyası oluşturulur. Aynı etkinlik için tekrar çalıştırılırsa yeni kopya üretmek yerine mevcut MDG klonu güncellenir.</p></div>';
    }

    public static function handle_bind() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        check_admin_referer( 'mdg_bind_event_qr_template_' . $event_id );

        $result = self::bind_event( $event_id );
        self::redirect_notice( $result['ok'] ? 'ok' : 'error', $result['message'] );
    }

    public static function handle_rollback() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        check_admin_referer( 'mdg_rollback_event_qr_template_' . $event_id );

        $result = self::rollback_event( $event_id );
        self::redirect_notice( $result['ok'] ? 'ok' : 'error', $result['message'] );
    }

    private static function redirect_notice( $type, $message ) {
        $url = add_query_arg(
            array(
                'page' => 'mdg-settings',
                'mdg_qr_bind_type' => $type,
                'mdg_qr_bind_notice' => rawurlencode( $message ),
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    public static function bind_event( $event_id ) {
        $analysis = self::analyze_event( $event_id );
        if ( ! $analysis['ready'] ) {
            return array( 'ok' => false, 'message' => 'Bağlama yapılamadı: ' . implode( ' ', $analysis['errors'] ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tickera_ticket_templates';
        $base = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", $analysis['base_template_id'] ) );
        if ( ! $base ) { return array( 'ok'=>false, 'message'=>'Temel Ticket Designer şablonu bulunamadı.' ); }

        $data = json_decode( (string) $base->template_data, true );
        if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
            return array( 'ok'=>false, 'message'=>'Temel şablon JSON verisi okunamadı.' );
        }

        $target_id = (string) $analysis['candidate']['id'];
        $replaced = false;
        foreach ( $data['elements'] as &$element ) {
            if ( isset( $element['id'] ) && (string) $element['id'] === $target_id ) {
                $element['src'] = esc_url_raw( $analysis['qr_url'] );
                unset( $element['dataField'] );
                $element['mdgVenueQr'] = true;
                $element['mdgVenueId'] = (int) $analysis['venue_id'];
                $replaced = true;
                break;
            }
        }
        unset( $element );
        if ( ! $replaced ) { return array( 'ok'=>false, 'message'=>'Salon QR görsel elementi şablonda yeniden bulunamadı; hiçbir kayıt değiştirilmedi.' ); }

        $bindings = self::bindings();
        $existing = isset( $bindings[ $event_id ] ) ? $bindings[ $event_id ] : array();
        $clone_id = ! empty( $existing['clone_template_id'] ) ? absint( $existing['clone_template_id'] ) : 0;
        $clone_name = 'MDG AutoQR | ' . $analysis['event_title'] . ' | ' . $analysis['event_date'];
        $encoded = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! $encoded ) { return array( 'ok'=>false, 'message'=>'Yeni şablon JSON verisi oluşturulamadı.' ); }

        if ( $clone_id ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d", $clone_id ) );
            if ( $exists ) {
                $ok = $wpdb->update( $table, array(
                    'name' => $clone_name,
                    'template_data' => $encoded,
                    'settings' => (string) $base->settings,
                    'status' => 'active',
                    'is_default' => 0,
                    'updated_at' => current_time( 'mysql' ),
                ), array( 'id' => $clone_id ) );
                if ( false === $ok ) { return array( 'ok'=>false, 'message'=>'Mevcut MDG QR şablonu güncellenemedi.' ); }
            } else { $clone_id = 0; }
        }

        if ( ! $clone_id ) {
            $ok = $wpdb->insert( $table, array(
                'name' => $clone_name,
                'template_data' => $encoded,
                'settings' => (string) $base->settings,
                'thumbnail_url' => (string) $base->thumbnail_url,
                'status' => 'active',
                'is_default' => 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ) );
            if ( ! $ok ) { return array( 'ok'=>false, 'message'=>'MDG event QR şablonu oluşturulamadı.' ); }
            $clone_id = (int) $wpdb->insert_id;
        }

        $all_ids = array_values( array_unique( array_merge( $analysis['product_ids'], $analysis['variation_ids'] ) ) );
        if ( ! $all_ids ) { return array( 'ok'=>false, 'message'=>'Bağlanacak WooCommerce ürün/varyasyon bulunamadı.' ); }

        $original = array();
        foreach ( $all_ids as $object_id ) {
            $original[ $object_id ] = self::current_template_selection( $object_id );
            self::save_template_selection( $object_id, $clone_id );
        }

        $bindings[ $event_id ] = array(
            'event_id' => (int) $event_id,
            'base_template_id' => (int) $analysis['base_template_id'],
            'clone_template_id' => (int) $clone_id,
            'venue_id' => (int) $analysis['venue_id'],
            'qr_attachment_id' => (int) $analysis['qr_attachment_id'],
            'qr_url' => (string) $analysis['qr_url'],
            'candidate_element_id' => $target_id,
            'product_ids' => $analysis['product_ids'],
            'variation_ids' => $analysis['variation_ids'],
            'original_selections' => $existing && ! empty( $existing['original_selections'] ) ? $existing['original_selections'] : $original,
            'updated_at' => current_time( 'mysql' ),
        );
        update_option( self::OPTION_KEY, $bindings, false );

        return array( 'ok'=>true, 'message'=>'Etkinlik bazlı salon QR şablonu bağlandı. Designer #' . $clone_id . ' artık bu etkinliğin ürün/varyasyonlarında kullanılacak.' );
    }

    public static function rollback_event( $event_id ) {
        $bindings = self::bindings();
        if ( empty( $bindings[ $event_id ] ) ) { return array( 'ok'=>false, 'message'=>'Bu etkinlik için geri alınacak MDG QR şablon bağlantısı yok.' ); }
        $binding = $bindings[ $event_id ];
        $original = isset( $binding['original_selections'] ) && is_array( $binding['original_selections'] ) ? $binding['original_selections'] : array();
        foreach ( $original as $object_id => $selection ) {
            self::restore_template_selection( absint( $object_id ), $selection );
        }
        unset( $bindings[ $event_id ] );
        update_option( self::OPTION_KEY, $bindings, false );
        return array( 'ok'=>true, 'message'=>'Ürün/varyasyonlar önceki Ticket Designer seçimlerine geri döndürüldü. MDG klon şablonu silinmedi; güvenlik için arşivde bırakıldı.' );
    }

    public static function maybe_refresh_for_venue( $venue_id, $venue ) {
        $bindings = self::bindings();
        if ( ! $bindings ) { return; }
        foreach ( $bindings as $event_id => $binding ) {
            if ( (int) ( $binding['venue_id'] ?? 0 ) !== (int) $venue_id ) { continue; }
            $event = MDG_Events::get( (int) $event_id );
            if ( ! $event ) { continue; }
            $sessions = MDG_Sessions::by_event( (int) $event_id );
            $future = false;
            foreach ( $sessions as $s ) {
                if ( strtotime( (string) $s->start_at . ' UTC' ) > time() ) { $future = true; break; }
            }
            if ( $future ) { self::bind_event( (int) $event_id ); }
        }
    }

    public static function analyze_event( $event_id ) {
        $out = array(
            'ready'=>false, 'errors'=>array(), 'event_id'=>(int)$event_id, 'event_title'=>'', 'event_date'=>'',
            'venue_id'=>0, 'venue_name'=>'', 'maps_url'=>'', 'qr_attachment_id'=>0, 'qr_url'=>'',
            'product_ids'=>array(), 'variation_ids'=>array(), 'base_template_id'=>0, 'candidate'=>array(),
        );
        $event = MDG_Events::get( (int) $event_id );
        if ( ! $event ) { $out['errors'][]='V2 etkinliği bulunamadı.'; return $out; }
        $out['event_title'] = (string) $event->title;
        $out['venue_id'] = (int) $event->venue_id;
        $out['venue_name'] = (string) $event->venue_name;
        $out['maps_url'] = trim( (string) $event->venue_maps_url );
        $out['qr_attachment_id'] = absint( $event->venue_qr_attachment_id );
        $out['qr_url'] = $out['qr_attachment_id'] ? MDG_QR::attachment_url( $out['qr_attachment_id'] ) : '';

        if ( ( ! $out['qr_url'] || ! $out['maps_url'] ) && $out['venue_id'] ) {
            $venue = MDG_Venues::get( $out['venue_id'] );
            if ( $venue ) {
                if ( ! $out['maps_url'] ) { $out['maps_url'] = trim( (string) $venue->maps_url ); }
                if ( ! $out['qr_url'] && $venue->location_qr_attachment_id ) {
                    $out['qr_attachment_id'] = absint( $venue->location_qr_attachment_id );
                    $out['qr_url'] = MDG_QR::attachment_url( $out['qr_attachment_id'] );
                }
                if ( ! $out['venue_name'] ) { $out['venue_name'] = (string) $venue->name; }
            }
        }
        if ( ! $out['qr_url'] ) { $out['errors'][]='Salon otomatik QR kaydı hazır değil.'; }

        $sessions = MDG_Sessions::by_event( (int) $event_id );
        if ( ! $sessions ) { $out['errors'][]='Etkinliğin seansı yok.'; return $out; }
        $dates = array();
        foreach ( $sessions as $session ) {
            if ( ! empty( $session->start_at ) ) { $dates[] = substr( (string) $session->start_at, 0, 10 ); }
            if ( (int) $session->wc_product_id ) { $out['product_ids'][] = (int) $session->wc_product_id; }
            $types = MDG_Sessions::ticket_types_by_session( (int) $session->id );
            foreach ( $types as $type ) { if ( (int) $type->wc_variation_id ) { $out['variation_ids'][] = (int) $type->wc_variation_id; } }
        }
        $out['product_ids'] = array_values( array_unique( $out['product_ids'] ) );
        $out['variation_ids'] = array_values( array_unique( $out['variation_ids'] ) );
        $out['event_date'] = $dates ? min( $dates ) : '';
        if ( ! $out['product_ids'] || ! $out['variation_ids'] ) { $out['errors'][]='WooCommerce mapping eksik.'; }

        $out['base_template_id'] = self::detect_base_template_id( $out['product_ids'], $out['variation_ids'] );
        if ( ! $out['base_template_id'] ) { $out['errors'][]='Kullanılan Ticket Designer şablonu bulunamadı.'; return $out; }

        $out['candidate'] = self::detect_static_qr_candidate( $out['base_template_id'] );
        if ( ! $out['candidate'] ) { $out['errors'][]='Temel şablonun sağ-alt bölümünde statik QR görseli güvenle tespit edilemedi.'; }
        $out['ready'] = empty( $out['errors'] );
        return $out;
    }

    private static function detect_base_template_id( array $product_ids, array $variation_ids ) {
        $ids = array_merge( $variation_ids, $product_ids );
        foreach ( $ids as $id ) {
            $designer = absint( get_post_meta( $id, 'tc_designer_template_id', true ) );
            if ( $designer ) { return $designer; }
            $raw = (string) get_post_meta( $id, '_ticket_template', true );
            if ( preg_match( '/^d_(\d+)$/', $raw, $m ) ) { return absint( $m[1] ); }
            if ( function_exists( 'tickera_ticket_designer_selected_template_value' ) ) {
                $sel = (string) tickera_ticket_designer_selected_template_value( $id, '_ticket_template' );
                if ( preg_match( '/^d_(\d+)$/', $sel, $m ) ) { return absint( $m[1] ); }
            }
        }
        global $wpdb;
        $table = $wpdb->prefix . 'tickera_ticket_templates';
        $id = $wpdb->get_var( "SELECT id FROM {$table} WHERE status='active' AND name LIKE 'Madagaskar Sirki A5 yatay%' ORDER BY id DESC LIMIT 1" );
        return absint( $id );
    }

    private static function detect_static_qr_candidate( $template_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tickera_ticket_templates';
        $json = $wpdb->get_var( $wpdb->prepare( "SELECT template_data FROM {$table} WHERE id=%d", $template_id ) );
        $data = json_decode( (string) $json, true );
        if ( ! is_array( $data ) || empty( $data['elements'] ) ) { return array(); }
        $canvas_w = max( 1, (float) ( $data['width'] ?? 595 ) );
        $canvas_h = max( 1, (float) ( $data['height'] ?? 420 ) );
        $candidates = array();
        foreach ( $data['elements'] as $el ) {
            $type = strtolower( (string) ( $el['type'] ?? '' ) );
            $base = strtolower( (string) ( $el['baseType'] ?? '' ) );
            if ( 'google_map' === $type || 'qrcode' === $base || 'qr_code' === $type ) { continue; }
            if ( ! in_array( $type, array( 'image', 'logo', 'event_image', 'event_logo', 'sponsor_logo' ), true ) && 'image' !== $base ) { continue; }
            $x = (float) ( $el['x'] ?? 0 ); $y = (float) ( $el['y'] ?? 0 );
            $w = (float) ( $el['width'] ?? 0 ); $h = (float) ( $el['height'] ?? 0 );
            if ( $x < $canvas_w * 0.55 || $y < $canvas_h * 0.48 ) { continue; }
            if ( $w < 45 || $h < 45 || $w > 180 || $h > 180 ) { continue; }
            $ratio = $h > 0 ? $w / $h : 0;
            if ( $ratio < 0.65 || $ratio > 1.35 ) { continue; }
            $src = (string) ( $el['src'] ?? '' );
            $score = ( $x / $canvas_w ) + ( $y / $canvas_h ) + ( 1 - min( 1, abs( 1 - $ratio ) ) );
            if ( false !== stripos( $src, 'qr' ) ) { $score += 3; }
            $el['_mdg_score'] = $score;
            $candidates[] = $el;
        }
        if ( ! $candidates ) { return array(); }
        usort( $candidates, function( $a, $b ) { return ( $b['_mdg_score'] <=> $a['_mdg_score'] ); } );
        return $candidates[0];
    }

    private static function current_template_selection( $object_id ) {
        return array(
            'designer' => (string) get_post_meta( $object_id, 'tc_designer_template_id', true ),
            'classic'  => (string) get_post_meta( $object_id, '_ticket_template', true ),
        );
    }

    private static function save_template_selection( $object_id, $designer_id ) {
        if ( function_exists( 'tickera_ticket_designer_save_template_value' ) ) {
            tickera_ticket_designer_save_template_value( $object_id, 'd_' . absint( $designer_id ), '_ticket_template' );
            return;
        }
        update_post_meta( $object_id, 'tc_designer_template_id', absint( $designer_id ) );
    }

    private static function restore_template_selection( $object_id, $selection ) {
        $designer = isset( $selection['designer'] ) ? (string) $selection['designer'] : '';
        $classic  = isset( $selection['classic'] ) ? (string) $selection['classic'] : '';
        if ( $designer ) { update_post_meta( $object_id, 'tc_designer_template_id', absint( $designer ) ); }
        else { delete_post_meta( $object_id, 'tc_designer_template_id' ); }
        if ( '' !== $classic ) { update_post_meta( $object_id, '_ticket_template', $classic ); }
        else { delete_post_meta( $object_id, '_ticket_template' ); }
    }

    private static function bindings() {
        $value = get_option( self::OPTION_KEY, array() );
        return is_array( $value ) ? $value : array();
    }

    private static function mapped_events() {
        global $wpdb;
        $events = MDG_DB::table( 'events' );
        $sessions = MDG_DB::table( 'sessions' );
        return $wpdb->get_results(
            "SELECT DISTINCT e.* FROM {$events} e INNER JOIN {$sessions} s ON s.event_id=e.id WHERE s.wc_product_id IS NOT NULL AND s.tickera_event_id IS NOT NULL ORDER BY e.id DESC LIMIT 50"
        );
    }
}
