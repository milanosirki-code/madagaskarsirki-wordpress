<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V3.6.3 — Tickera ticket invalidation DRY-RUN.
 *
 * SAFETY BOUNDARY:
 * - Read-only. No ticket/post/meta/status is changed.
 * - No refund/payment request is sent.
 * - Discovers Tickera ticket instances for paid MDG orders and verifies order/event linkage.
 * - Prepares the exact ticket set that a later controlled full-refund flow may invalidate.
 */
final class MDG_Ticket_Invalidation_Dry_Run {

    public static function render_panel( $event_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $event_id = absint( $event_id );
        if ( ! $event_id ) { return; }

        $event = MDG_Events::get( $event_id );
        if ( ! $event ) { return; }

        $analysis = self::analyze_event( $event_id );

        echo '<div class="mdg-panel" style="margin-top:18px;border:2px solid #2271b1">';
        echo '<h2>Tickera Bilet Geçersizleştirme — Dry-Run</h2>';
        echo '<div class="notice notice-info inline"><p><strong>V3.6.3 salt-okunur:</strong> Bu bölüm yalnızca hangi Tickera biletlerinin hangi WooCommerce siparişine ve seçili Madagaskar etkinliğine bağlı olduğunu doğrular. <strong>Bilet silmez, çöpe taşımaz, QR geçerliliğini değiştirmez ve para iadesi göndermez.</strong></p></div>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:14px 0">';
        self::mini_card( 'Sipariş', (int) $analysis['summary']['orders'], true );
        self::mini_card( 'Beklenen Bilet', (int) $analysis['summary']['expected_tickets'], true );
        self::mini_card( 'Bulunan Tickera Bileti', (int) $analysis['summary']['found_tickets'], (int)$analysis['summary']['expected_tickets'] === (int)$analysis['summary']['found_tickets'] );
        self::mini_card( 'Kesin Etkinlik Eşleşmesi', (int) $analysis['summary']['matched_tickets'], (int)$analysis['summary']['matched_tickets'] === (int)$analysis['summary']['found_tickets'] );
        self::mini_card( 'Check-in Yapılmış', (int) $analysis['summary']['checked_in_tickets'], 0 === (int)$analysis['summary']['checked_in_tickets'] );
        echo '</div>';

        echo '<p class="description"><strong>Bulma sırası:</strong> Tickera Bridge için birinci güvenilir bağ <code>tc_tickets_instances.post_parent = WooCommerce order_id</code> olarak kontrol edilir; ayrıca eski/farklı kurulumlar için order-id meta anahtarları yedek olarak taranır. Etkinlik çözümü bilet instance metası → ticket type / WooCommerce ürün metası → MDG Tickera etkinlik eşlemesi zinciriyle doğrulanır.</p>';

        if ( ! post_type_exists( 'tc_tickets_instances' ) ) {
            echo '<div class="notice notice-error inline"><p><strong>Tickera bilet instance post tipi algılanmadı.</strong> Geçersizleştirme tasarımı açılmamalı.</p></div></div>';
            return;
        }

        self::render_runtime_capability();

        if ( empty( $analysis['orders'] ) ) {
            echo '<p>Bu etkinlik için analiz edilecek ücretli MDG siparişi bulunamadı.</p></div>';
            return;
        }

        foreach ( $analysis['orders'] as $row ) {
            self::render_order( $event_id, $row );
        }

        echo '<div style="margin-top:16px;padding:14px;border-left:4px solid #2271b1;background:#f6f7f7">';
        echo '<strong>V3.6.4 için planlanan güvenlik zinciri:</strong>';
        echo '<ol style="margin-bottom:0"><li>Tek test siparişinin kalan iade tutarı son kez hesaplanır.</li><li>PayTR/WooCommerce tam iadesi başarıyla tamamlanmadan hiçbir bilete dokunulmaz.</li><li>Yalnız bu dry-run ile kesin eşleşmiş Tickera instance ID’leri işleme alınır.</li><li>Hard-delete yerine geri izlenebilir <strong>soft invalidation / trash</strong> yaklaşımı tercih edilir; mevcut Tickera sürümünün davranışı ayrıca doğrulanır.</li><li>Sipariş notu + MDG audit log’a iade ve bilet instance ID’leri birlikte yazılır.</li></ol>';
        echo '</div></div>';
    }

    private static function render_runtime_capability() {
        $class_exists = class_exists( 'TC_Ticket_Instance' );
        $method_exists = $class_exists && method_exists( 'TC_Ticket_Instance', 'delete_ticket_instance' );
        $trash_exists = function_exists( 'wp_trash_post' );

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:12px 0">';
        self::status_box( 'Tickera TC_Ticket_Instance', $class_exists ? 'Algılandı' : 'Algılanmadı', $class_exists );
        self::status_box( 'delete_ticket_instance()', $method_exists ? 'Algılandı' : 'Algılanmadı', $method_exists );
        self::status_box( 'WordPress soft-trash', $trash_exists ? 'Kullanılabilir' : 'Algılanmadı', $trash_exists );
        echo '</div>';
        echo '<p class="description">Bu kontroller yalnızca çalışma zamanındaki yetenekleri okur. V3.6.3 hiçbir silme/çöpe taşıma metodunu çağırmaz.</p>';
    }

    private static function render_order( $event_id, array $row ) {
        $order = $row['order'];
        if ( ! $order ) { return; }

        $status_ok = ! empty( $row['safe'] );
        $color = $status_ok ? '#087c2f' : '#b32d2e';
        echo '<div style="border:1px solid #dcdcde;border-radius:10px;margin-top:16px;overflow:hidden;background:#fff">';
        echo '<div style="padding:12px 14px;background:#f6f7f7;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">';
        echo '<div><strong>Sipariş #' . esc_html( $order->get_order_number() ) . '</strong> — ' . esc_html( trim( $order->get_formatted_billing_full_name() ) ?: 'Müşteri adı yok' ) . '<br><small>' . esc_html( $order->get_billing_email() ) . '</small></div>';
        echo '<div style="text-align:right"><strong style="color:' . esc_attr( $color ) . '">' . esc_html( $status_ok ? 'DRY-RUN HAZIR' : 'KONTROL GEREKLİ' ) . '</strong><br><small>Beklenen ' . esc_html( (int)$row['expected_tickets'] ) . ' • Bulunan ' . esc_html( (int)$row['found_tickets'] ) . '</small></div>';
        echo '</div>';

        if ( ! empty( $row['warnings'] ) ) {
            echo '<div style="padding:10px 14px;background:#fff8e5"><strong>Uyarılar:</strong><ul style="margin-bottom:0">';
            foreach ( $row['warnings'] as $warning ) { echo '<li>' . esc_html( $warning ) . '</li>'; }
            echo '</ul></div>';
        }

        if ( empty( $row['tickets'] ) ) {
            echo '<div style="padding:14px">Bu sipariş için Tickera instance bulunamadı.</div></div>';
            return;
        }

        echo '<div style="overflow:auto"><table class="widefat striped" style="min-width:1200px"><thead><tr>';
        foreach ( array( 'Instance','Sipariş Bağı','Bilet Kodu','Ticket Type / Ürün','Tickera Etkinliği','MDG Etkinliği','Seans','Post Durumu','Check-in','Dry-Run Kararı' ) as $h ) {
            echo '<th>' . esc_html( $h ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ( $row['tickets'] as $t ) {
            $decision_ok = ! empty( $t['matched_event'] ) && ! empty( $t['order_link_ok'] ) && 'trash' !== $t['post_status'];
            $decision = $decision_ok ? 'Geçersizleştirmeye aday' : ( 'trash' === $t['post_status'] ? 'Zaten trash' : 'Blokla / incele' );
            echo '<tr>';
            echo '<td><strong>#' . esc_html( $t['id'] ) . '</strong></td>';
            echo '<td>' . esc_html( $t['order_link_source'] ?: 'Bulunamadı' ) . '<br><small>' . ( $t['order_link_ok'] ? 'Sipariş eşleşti' : 'Sipariş eşleşmedi' ) . '</small></td>';
            echo '<td><code>' . esc_html( $t['ticket_code'] ?: '—' ) . '</code></td>';
            echo '<td>' . ( $t['ticket_type_id'] ? '#' . esc_html( $t['ticket_type_id'] ) : '—' );
            if ( $t['product_title'] ) { echo '<br><small>' . esc_html( $t['product_title'] ) . '</small>'; }
            echo '</td>';
            echo '<td>' . ( $t['tickera_event_id'] ? '#' . esc_html( $t['tickera_event_id'] ) . ' · ' . esc_html( $t['tickera_event_title'] ?: 'Başlık yok' ) : 'Çözümlenemedi' ) . '</td>';
            echo '<td>' . ( $t['resolved_mdg_event_id'] ? '#' . esc_html( $t['resolved_mdg_event_id'] ) : '—' ) . '<br><small>' . esc_html( $t['matched_event'] ? 'Seçili etkinlik ile eşleşti' : 'EŞLEŞMEDİ' ) . '</small></td>';
            echo '<td>' . esc_html( $t['session_label'] ?: '—' ) . '</td>';
            echo '<td><code>' . esc_html( $t['post_status'] ?: '—' ) . '</code></td>';
            echo '<td>';
            if ( null === $t['checkins'] ) { echo 'Algılanamadı'; }
            elseif ( $t['checkins'] > 0 ) { echo '<strong style="color:#b32d2e">' . esc_html( $t['checkins'] ) . ' check-in</strong>'; }
            else { echo '<span style="color:#087c2f">Yok</span>'; }
            echo '</td>';
            echo '<td><strong style="color:' . esc_attr( $decision_ok ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $decision ) . '</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function analyze_event( $event_id ) {
        global $wpdb;
        $map = MDG_DB::table( 'order_map' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT order_id,SUM(quantity) expected_tickets FROM {$map} WHERE event_id=%d AND paid_at IS NOT NULL GROUP BY order_id ORDER BY order_id DESC LIMIT 200",
            $event_id
        ) );

        $out = array(
            'summary' => array( 'orders'=>0, 'expected_tickets'=>0, 'found_tickets'=>0, 'matched_tickets'=>0, 'checked_in_tickets'=>0 ),
            'orders'  => array(),
        );
        foreach ( (array) $rows as $r ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $r->order_id ) ) : null;
            if ( ! $order || ! $order->get_date_paid() ) { continue; }
            if ( in_array( $order->get_status(), array( 'failed','cancelled','trash' ), true ) ) { continue; }

            $ticket_ids = self::ticket_instances_for_order( $order->get_id() );
            $tickets = array();
            $warnings = array();
            $matched = 0;
            $checked = 0;
            foreach ( $ticket_ids as $ticket_id ) {
                $t = self::analyze_ticket( $ticket_id, $order->get_id(), $event_id );
                if ( ! empty( $t['matched_event'] ) ) { $matched++; }
                if ( is_int( $t['checkins'] ) && $t['checkins'] > 0 ) { $checked++; }
                $tickets[] = $t;
            }

            $expected = (int) $r->expected_tickets;
            $found = count( $tickets );
            if ( $expected !== $found ) { $warnings[] = 'MDG sipariş haritasında beklenen bilet adedi (' . $expected . ') ile Tickera instance adedi (' . $found . ') eşleşmiyor.'; }
            if ( $found && $matched !== $found ) { $warnings[] = 'Bazı Tickera biletleri seçili MDG etkinliğine kesin olarak çözümlenemedi.'; }
            if ( $checked > 0 ) { $warnings[] = $checked . ' bilette check-in kaydı bulundu; tam iade öncesinde manuel operasyon kontrolü gerekir.'; }
            if ( ! $found ) { $warnings[] = 'Tickera ticket instance bulunamadı; bilet geçersizleştirme zinciri çalıştırılmamalı.'; }

            $safe = $found > 0 && $expected === $found && $matched === $found;
            $out['orders'][] = array(
                'order' => $order,
                'expected_tickets' => $expected,
                'found_tickets' => $found,
                'matched_tickets' => $matched,
                'checked_in_tickets' => $checked,
                'tickets' => $tickets,
                'warnings' => $warnings,
                'safe' => $safe,
            );
            $out['summary']['orders']++;
            $out['summary']['expected_tickets'] += $expected;
            $out['summary']['found_tickets'] += $found;
            $out['summary']['matched_tickets'] += $matched;
            $out['summary']['checked_in_tickets'] += $checked;
        }
        return $out;
    }

    private static function ticket_instances_for_order( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id || ! post_type_exists( 'tc_tickets_instances' ) ) { return array(); }

        $ids = get_posts( array(
            'post_type'      => 'tc_tickets_instances',
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'post_parent'    => $order_id,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $keys = array( 'order_id','_order_id','woocommerce_order_id','_woocommerce_order_id','woo_order_id','_woo_order_id' );
        $meta_query = array( 'relation'=>'OR' );
        foreach ( $keys as $key ) { $meta_query[] = array( 'key'=>$key, 'value'=>(string)$order_id, 'compare'=>'=' ); }
        $meta_ids = get_posts( array(
            'post_type'      => 'tc_tickets_instances',
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => $meta_query,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        return array_values( array_unique( array_map( 'absint', array_merge( (array)$ids, (array)$meta_ids ) ) ) );
    }

    private static function analyze_ticket( $ticket_id, $order_id, $event_id ) {
        $ticket_id = absint( $ticket_id );
        $post = get_post( $ticket_id );
        $source = '';
        $order_link_ok = false;
        if ( $post && absint( $post->post_parent ) === absint( $order_id ) ) {
            $source = 'post_parent #' . absint( $post->post_parent );
            $order_link_ok = true;
        } else {
            foreach ( array( 'order_id','_order_id','woocommerce_order_id','_woocommerce_order_id','woo_order_id','_woo_order_id' ) as $key ) {
                $v = absint( get_post_meta( $ticket_id, $key, true ) );
                if ( $v ) {
                    $source = $key . ' #' . $v;
                    if ( $v === absint( $order_id ) ) { $order_link_ok = true; }
                    break;
                }
            }
        }

        $ticket_type_id = 0;
        foreach ( array( 'ticket_type_id','_ticket_type_id','ticket_id','_ticket_id' ) as $key ) {
            $ticket_type_id = absint( get_post_meta( $ticket_id, $key, true ) );
            if ( $ticket_type_id ) { break; }
        }

        $product_title = '';
        if ( $ticket_type_id ) {
            $product_title = get_the_title( $ticket_type_id );
            if ( function_exists( 'wc_get_product' ) ) {
                $p = wc_get_product( $ticket_type_id );
                if ( $p ) { $product_title = $p->get_name(); }
            }
        }

        $resolved = class_exists( 'MDG_Ticket_Venue_QR' ) ? MDG_Ticket_Venue_QR::resolve_for_ticket_instance( $ticket_id ) : array();
        $tickera_event_id = absint( $resolved['tickera_event_id'] ?? 0 );
        $resolved_mdg_event_id = absint( $resolved['mdg_event_id'] ?? 0 );

        if ( ! $tickera_event_id ) {
            $tickera_event_id = self::ticket_event_id_fallback( $ticket_id, $ticket_type_id );
            if ( $tickera_event_id ) { $resolved_mdg_event_id = self::mdg_event_from_tickera( $tickera_event_id ); }
        }

        $session_label = self::session_label_for_ticket_type( $ticket_type_id, $event_id );
        $checkins = self::checkin_count( $ticket_id );

        return array(
            'id' => $ticket_id,
            'post_status' => $post ? (string)$post->post_status : '',
            'order_link_source' => $source,
            'order_link_ok' => $order_link_ok,
            'ticket_code' => self::first_meta( $ticket_id, array( 'ticket_code','_ticket_code','code','_code' ) ),
            'ticket_type_id' => $ticket_type_id,
            'product_title' => $product_title,
            'tickera_event_id' => $tickera_event_id,
            'tickera_event_title' => $tickera_event_id ? get_the_title( $tickera_event_id ) : '',
            'resolved_mdg_event_id' => $resolved_mdg_event_id,
            'matched_event' => absint( $event_id ) === $resolved_mdg_event_id,
            'session_label' => $session_label,
            'checkins' => $checkins,
        );
    }

    private static function ticket_event_id_fallback( $ticket_id, $ticket_type_id ) {
        foreach ( array( 'event_id','_event_id','event_name','_event_name' ) as $key ) {
            $v = absint( get_post_meta( $ticket_id, $key, true ) );
            if ( $v ) { return $v; }
        }
        if ( $ticket_type_id ) {
            foreach ( array( '_event_name','event_name','event_id','_event_id' ) as $key ) {
                $v = absint( get_post_meta( $ticket_type_id, $key, true ) );
                if ( $v ) { return $v; }
            }
            if ( function_exists( 'wc_get_product' ) ) {
                $p = wc_get_product( $ticket_type_id );
                if ( $p && method_exists( $p, 'get_parent_id' ) && $p->get_parent_id() ) {
                    foreach ( array( '_event_name','event_name','event_id','_event_id' ) as $key ) {
                        $v = absint( get_post_meta( $p->get_parent_id(), $key, true ) );
                        if ( $v ) { return $v; }
                    }
                }
            }
        }
        return 0;
    }

    private static function mdg_event_from_tickera( $tickera_event_id ) {
        global $wpdb;
        return absint( $wpdb->get_var( $wpdb->prepare(
            'SELECT event_id FROM ' . MDG_DB::table( 'sessions' ) . ' WHERE tickera_event_id=%d ORDER BY id ASC LIMIT 1',
            absint( $tickera_event_id )
        ) ) );
    }

    private static function session_label_for_ticket_type( $ticket_type_id, $event_id ) {
        global $wpdb;
        $ticket_type_id = absint( $ticket_type_id );
        $event_id = absint( $event_id );
        if ( ! $ticket_type_id || ! $event_id ) { return ''; }

        $types = MDG_DB::table( 'ticket_types' );
        $sessions = MDG_DB::table( 'sessions' );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.start_at FROM {$types} t INNER JOIN {$sessions} s ON s.id=t.session_id WHERE t.wc_variation_id=%d AND s.event_id=%d LIMIT 1",
            $ticket_type_id, $event_id
        ) );
        if ( ! $row && function_exists( 'wc_get_product' ) ) {
            $p = wc_get_product( $ticket_type_id );
            $parent_id = $p && method_exists( $p, 'get_parent_id' ) ? absint( $p->get_parent_id() ) : 0;
            $candidate = $parent_id ?: $ticket_type_id;
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT start_at FROM {$sessions} WHERE wc_product_id=%d AND event_id=%d LIMIT 1",
                $candidate, $event_id
            ) );
        }
        if ( ! $row || empty( $row->start_at ) ) { return ''; }
        $ts = strtotime( (string)$row->start_at . ' UTC' );
        if ( ! $ts ) { return (string)$row->start_at; }
        return wp_date( 'd.m.Y H:i', $ts, wp_timezone() );
    }

    private static function checkin_count( $ticket_id ) {
        if ( class_exists( 'TC_Ticket_Instance' ) ) {
            try {
                $instance = new TC_Ticket_Instance( absint( $ticket_id ) );
                if ( method_exists( $instance, 'get_number_of_checkins' ) ) {
                    return (int) $instance->get_number_of_checkins( 'pass' );
                }
            } catch ( Throwable $e ) {}
        }

        if ( metadata_exists( 'post', $ticket_id, 'tc_checkins' ) ) {
            $raw = get_post_meta( $ticket_id, 'tc_checkins', true );
            if ( is_array( $raw ) ) {
                $count = 0;
                foreach ( $raw as $checkin ) {
                    if ( is_array( $checkin ) && isset( $checkin['status'] ) && 'pass' === strtolower( (string)$checkin['status'] ) ) { $count++; }
                }
                return $count;
            }
            return 0;
        }
        return null;
    }

    private static function first_meta( $post_id, array $keys ) {
        foreach ( $keys as $key ) {
            $v = get_post_meta( $post_id, $key, true );
            if ( is_scalar( $v ) && '' !== (string)$v ) { return (string)$v; }
        }
        return '';
    }

    private static function mini_card( $label, $value, $ok ) {
        echo '<div style="border:1px solid #dcdcde;border-radius:10px;padding:12px;background:#fff">';
        echo '<strong style="display:block;font-size:22px;color:' . esc_attr( $ok ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $value ) . '</strong>';
        echo '<span>' . esc_html( $label ) . '</span></div>';
    }

    private static function status_box( $label, $state, $ok ) {
        echo '<div style="border:1px solid #dcdcde;border-radius:10px;padding:12px;background:#fff">';
        echo '<strong style="display:block;color:' . esc_attr( $ok ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $state ) . '</strong>';
        echo '<span>' . esc_html( $label ) . '</span></div>';
    }
}
