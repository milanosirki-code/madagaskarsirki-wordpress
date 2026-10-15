<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.6.2: Mevcut çalışan WooCommerce/Tickera satış nesnelerini Madagaskar V2 taslağına bağlar.
 * Dış satış nesnelerini DEĞİŞTİRMEZ. Yalnızca MDG oturum/bilet türü mapping kolonlarına ID yazar.
 */
final class MDG_Sales_Adopter {

    public static function hooks() {
        add_action( 'admin_post_mdg_adopt_existing_sales', array( __CLASS__, 'handle_adopt' ) );
        add_action( 'admin_post_mdg_unlink_existing_sales', array( __CLASS__, 'handle_unlink' ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $drafts = class_exists( 'MDG_Events' ) ? MDG_Events::drafts() : array();
        echo '<div class="mdg-panel"><h2>V2.6.2 Mevcut Satış Yapısını V2’ye Bağla</h2>';
        echo '<p><strong>Bu adım yeni WooCommerce ürünü veya Tickera etkinliği oluşturmaz.</strong> Aynı Ankara etkinliği zaten canlı satışta olduğu için mevcut çalışan ürünleri V2 taslağındaki seans ve bilet türlerine bağlamak en güvenli yoldur.</p>';

        if ( isset( $_GET['mdg_adopted'] ) ) {
            echo '<div class="notice notice-success inline"><p>Mevcut WooCommerce/Tickera satış yapısı Madagaskar V2 taslağına başarıyla bağlandı.</p></div>';
        }
        if ( isset( $_GET['mdg_unlinked'] ) ) {
            echo '<div class="notice notice-success inline"><p>V2 eşleştirmesi kaldırıldı. WooCommerce/Tickera tarafında hiçbir kayıt değiştirilmedi.</p></div>';
        }
        if ( ! empty( $_GET['mdg_adopt_error'] ) ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( rawurldecode( wp_unslash( $_GET['mdg_adopt_error'] ) ) ) . '</p></div>';
        }

        if ( ! $drafts ) {
            echo '<div class="notice notice-warning inline"><p>Bağlanacak Madagaskar V2 taslağı bulunamadı.</p></div></div>';
            return;
        }

        $requested = absint( $_GET['mdg_link_event'] ?? $_GET['mdg_dry_event'] ?? 0 );
        $selected = null;
        foreach ( $drafts as $d ) {
            if ( $requested && (int) $d->id === $requested ) { $selected = $d; break; }
        }
        if ( ! $selected ) { $selected = $drafts[0]; }

        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:flex;gap:10px;align-items:end;margin:14px 0 20px">';
        echo '<input type="hidden" name="page" value="mdg-settings">';
        echo '<label><strong>Taslak seç</strong><br><select name="mdg_link_event">';
        foreach ( $drafts as $d ) {
            echo '<option value="' . esc_attr( $d->id ) . '" ' . selected( (int) $selected->id, (int) $d->id, false ) . '>' . esc_html( '#' . $d->id . ' ' . $d->title ) . '</option>';
        }
        echo '</select></label><button class="button button-secondary">Eşleşmeyi Analiz Et</button></form>';

        $current = self::current_mapping( (int) $selected->id );
        if ( $current['is_complete'] ) {
            self::render_current_mapping( $selected, $current );
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Yalnızca Madagaskar V2 eşleştirmesi kaldırılacak. WooCommerce ve Tickera kayıtları silinmeyecek. Devam edilsin mi?\');">';
            echo '<input type="hidden" name="action" value="mdg_unlink_existing_sales"><input type="hidden" name="event_id" value="' . esc_attr( $selected->id ) . '">';
            wp_nonce_field( 'mdg_unlink_existing_sales_' . (int) $selected->id, 'mdg_nonce' );
            echo '<button class="button">V2 Eşleştirmesini Kaldır</button></form></div>';
            return;
        }
        if ( $current['has_partial'] ) {
            echo '<div class="notice notice-error inline"><p><strong>Kısmi/eski eşleştirme bulundu.</strong> Güvenlik nedeniyle otomatik üzerine yazılmayacak. Önce eşleştirmeyi kaldırın veya mevcut mapping kayıtlarını inceleyin.</p></div></div>';
            return;
        }

        $plan = self::build_plan( $selected );
        self::render_plan( $selected, $plan );

        if ( empty( $plan['errors'] ) && ! empty( $plan['tickera_event_id'] ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:18px">';
            echo '<input type="hidden" name="action" value="mdg_adopt_existing_sales"><input type="hidden" name="event_id" value="' . esc_attr( $selected->id ) . '">';
            wp_nonce_field( 'mdg_adopt_existing_sales_' . (int) $selected->id, 'mdg_nonce' );
            echo '<label style="display:block;margin:0 0 12px"><input type="checkbox" name="confirm_adopt" value="1" required> Bu işlemin yeni ürün oluşturmadığını; yalnızca mevcut canlı ürün ID’lerini V2 taslağına bağlayacağını onaylıyorum.</label>';
            echo '<button class="button button-primary" onclick="return confirm(\'Mevcut çalışan Ankara satış ürünleri Madagaskar V2 taslağına bağlansın mı?\');">Mevcut Satışı V2’ye Bağla</button>';
            echo '</form>';
        }
        echo '<div class="notice notice-info inline" style="margin-top:18px"><p>Güvenlik: Bu modül WooCommerce ürün adını, fiyatını, stok durumunu, Tickera etkinliğini, biletleri veya siparişleri değiştirmez. Yalnızca Madagaskar V2 custom tablolarındaki mapping alanlarını günceller.</p></div>';
        echo '</div>';
    }

    public static function handle_adopt() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_admin_referer( 'mdg_adopt_existing_sales_' . $event_id, 'mdg_nonce' );
        if ( empty( $_POST['confirm_adopt'] ) ) { self::redirect_error( $event_id, 'Onay kutusu işaretlenmedi.' ); }

        $event = MDG_Events::get( $event_id );
        if ( ! $event || 'draft' !== (string) $event->status ) { self::redirect_error( $event_id, 'Yalnızca Madagaskar V2 taslağına eşleştirme yapılabilir.' ); }
        $current = self::current_mapping( $event_id );
        if ( $current['is_complete'] ) { self::redirect_error( $event_id, 'Bu etkinlik zaten mevcut satış yapısına bağlı.' ); }
        if ( $current['has_partial'] ) { self::redirect_error( $event_id, 'Kısmi/eski eşleştirme bulundu; güvenlik nedeniyle üzerine yazılmadı.' ); }

        $plan = self::build_plan( $event );
        if ( ! empty( $plan['errors'] ) || empty( $plan['tickera_event_id'] ) ) {
            self::redirect_error( $event_id, implode( ' ', (array) $plan['errors'] ) ?: 'Güvenli eşleştirme planı oluşturulamadı.' );
        }

        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $wpdb->query( 'START TRANSACTION' );
        try {
            foreach ( $plan['sessions'] as $row ) {
                $session_id = absint( $row['session_id'] );
                $product_id = absint( $row['product_id'] );
                $tickera_event_id = absint( $plan['tickera_event_id'] );

                $fresh = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sessions_table} WHERE id=%d AND event_id=%d", $session_id, $event_id ) );
                if ( ! $fresh ) { throw new Exception( 'Seans kaydı bulunamadı.' ); }
                if ( (int) $fresh->wc_product_id && (int) $fresh->wc_product_id !== $product_id ) { throw new Exception( 'Seans başka bir WooCommerce ürününe bağlı.' ); }
                if ( (int) $fresh->tickera_event_id && (int) $fresh->tickera_event_id !== $tickera_event_id ) { throw new Exception( 'Seans başka bir Tickera etkinliğine bağlı.' ); }

                $ok = $wpdb->update( $sessions_table, array(
                    'wc_product_id' => $product_id,
                    'tickera_event_id' => $tickera_event_id,
                    'updated_at' => MDG_DB::now(),
                ), array( 'id' => $session_id, 'event_id' => $event_id ), array( '%d','%d','%s' ), array( '%d','%d' ) );
                if ( false === $ok ) { throw new Exception( 'Seans ürün eşleştirmesi yazılamadı.' ); }

                foreach ( $row['variations'] as $vrow ) {
                    $type_id = absint( $vrow['ticket_type_id'] );
                    $variation_id = absint( $vrow['variation_id'] );
                    $fresh_type = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$types_table} WHERE id=%d AND session_id=%d", $type_id, $session_id ) );
                    if ( ! $fresh_type ) { throw new Exception( 'Bilet türü kaydı bulunamadı.' ); }
                    if ( (int) $fresh_type->wc_variation_id && (int) $fresh_type->wc_variation_id !== $variation_id ) { throw new Exception( 'Bilet türü başka bir varyasyona bağlı.' ); }
                    $ok = $wpdb->update( $types_table, array(
                        'wc_variation_id' => $variation_id,
                        'updated_at' => MDG_DB::now(),
                    ), array( 'id' => $type_id, 'session_id' => $session_id ), array( '%d','%s' ), array( '%d','%d' ) );
                    if ( false === $ok ) { throw new Exception( 'Varyasyon eşleştirmesi yazılamadı.' ); }
                }
            }

            self::audit( 'sales.existing.adopted', $event_id, array(
                'tickera_event_id' => absint( $plan['tickera_event_id'] ),
                'products' => array_map( function( $r ) { return absint( $r['product_id'] ); }, $plan['sessions'] ),
                'source' => 'existing_live_sales',
            ) );
            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_error( $event_id, $e->getMessage() ?: 'Eşleştirme sırasında hata oluştu.' );
        }

        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-settings', 'mdg_link_event'=>$event_id, 'mdg_adopted'=>1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_unlink() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_admin_referer( 'mdg_unlink_existing_sales_' . $event_id, 'mdg_nonce' );
        $event = MDG_Events::get( $event_id );
        if ( ! $event || 'draft' !== (string) $event->status ) { self::redirect_error( $event_id, 'Yalnızca taslak etkinliğin V2 eşleştirmesi kaldırılabilir.' ); }

        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $session_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$sessions_table} WHERE event_id=%d", $event_id ) );
        $wpdb->query( 'START TRANSACTION' );
        try {
            if ( $session_ids ) {
                $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
                $sql = $wpdb->prepare( "UPDATE {$types_table} SET wc_variation_id=NULL, updated_at=%s WHERE session_id IN ({$placeholders})", array_merge( array( MDG_DB::now() ), array_map( 'absint', $session_ids ) ) );
                if ( false === $wpdb->query( $sql ) ) { throw new Exception( 'Bilet türü eşleştirmeleri kaldırılamadı.' ); }
            }
            if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$sessions_table} SET wc_product_id=NULL, tickera_event_id=NULL, updated_at=%s WHERE event_id=%d", MDG_DB::now(), $event_id ) ) ) {
                throw new Exception( 'Seans eşleştirmeleri kaldırılamadı.' );
            }
            self::audit( 'sales.existing.unlinked', $event_id, array( 'source'=>'existing_live_sales' ) );
            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_error( $event_id, $e->getMessage() ?: 'Eşleştirme kaldırılamadı.' );
        }

        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-settings', 'mdg_link_event'=>$event_id, 'mdg_unlinked'=>1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function current_mapping( $event_id ) {
        $sessions = MDG_Sessions::by_event( $event_id );
        $has_any = false; $complete = (bool) $sessions; $rows = array();
        foreach ( $sessions as $s ) {
            $types = MDG_Sessions::ticket_types_by_session( $s->id );
            $row_complete = (bool) ( (int) $s->wc_product_id && (int) $s->tickera_event_id && $types );
            foreach ( $types as $t ) {
                if ( (int) $t->wc_variation_id ) { $has_any = true; } else { $row_complete = false; }
            }
            if ( (int) $s->wc_product_id || (int) $s->tickera_event_id ) { $has_any = true; }
            if ( ! $row_complete ) { $complete = false; }
            $rows[] = array( 'session'=>$s, 'types'=>$types );
        }
        return array( 'has_partial'=>$has_any && ! $complete, 'is_complete'=>$complete, 'rows'=>$rows );
    }

    private static function build_plan( $event ) {
        $out = array( 'tickera_event_id'=>0, 'sessions'=>array(), 'errors'=>array(), 'warnings'=>array(), 'score'=>0 );
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) { $out['errors'][]='WooCommerce hazır değil.'; return $out; }
        if ( ! post_type_exists( 'tc_events' ) ) { $out['errors'][]='Tickera etkinlik tipi aktif değil.'; return $out; }
        $sessions = MDG_Sessions::by_event( $event->id );
        if ( ! $sessions ) { $out['errors'][]='Taslakta seans yok.'; return $out; }

        $groups = self::ticket_product_groups();
        if ( ! $groups ) { $out['errors'][]='Mevcut Tickera/WooCommerce ürün grubu bulunamadı.'; return $out; }

        $best = null;
        foreach ( $groups as $eid => $products ) {
            $candidate = self::match_group( $event, $sessions, absint( $eid ), $products );
            if ( ! $best || $candidate['score'] > $best['score'] ) { $best = $candidate; }
        }
        if ( ! $best || ! empty( $best['errors'] ) || count( $best['sessions'] ) !== count( $sessions ) ) {
            $out['errors'][] = 'Taslağın bütün seanslarını güvenle eşleştiren tek bir mevcut satış grubu bulunamadı.';
            if ( $best && ! empty( $best['errors'] ) ) { $out['errors'] = array_merge( $out['errors'], $best['errors'] ); }
            return $out;
        }
        return $best;
    }

    private static function ticket_product_groups() {
        $q = new WP_Query( array(
            'post_type'=>'product', 'post_status'=>array('publish','private','draft'), 'posts_per_page'=>200,
            'fields'=>'ids', 'no_found_rows'=>true, 'orderby'=>'ID', 'order'=>'DESC',
            'meta_query'=>array( array( 'key'=>'_tc_is_ticket', 'value'=>'yes' ) ),
        ) );
        $groups = array();
        foreach ( (array) $q->posts as $pid ) {
            $eid = absint( get_post_meta( $pid, '_event_name', true ) );
            if ( ! $eid ) { continue; }
            $p = wc_get_product( $pid );
            if ( ! $p || ! $p->is_type( 'variable' ) ) { continue; }
            if ( ! isset( $groups[$eid] ) ) { $groups[$eid]=array(); }
            $groups[$eid][] = $p;
        }
        return $groups;
    }

    private static function match_group( $event, array $sessions, $event_id, array $products ) {
        $result = array( 'tickera_event_id'=>$event_id, 'sessions'=>array(), 'errors'=>array(), 'warnings'=>array(), 'score'=>0 );
        $event_title = get_the_title( $event_id );
        if ( self::norm( $event_title ) && false !== strpos( self::norm( $event_title ), self::norm( $event->province_name ) ) ) { $result['score'] += 8; }
        if ( count( $products ) === count( $sessions ) ) { $result['score'] += 12; }

        $used_products = array();
        foreach ( $sessions as $session ) {
            list( $date, $time ) = MDG_Sessions::local_parts( $session->start_at );
            $matches = array();
            foreach ( $products as $p ) {
                if ( isset( $used_products[ $p->get_id() ] ) ) { continue; }
                $name = (string) $p->get_name();
                if ( false !== strpos( $name, $time ) ) { $matches[] = $p; }
            }
            if ( 1 !== count( $matches ) ) {
                $result['errors'][] = $date . ' ' . $time . ' seansı için tek bir ürün eşleşmesi bulunamadı.';
                continue;
            }
            $p = $matches[0];
            $used_products[ $p->get_id() ] = true;
            $result['score'] += 10;

            if ( 'yes' !== (string) get_post_meta( $p->get_id(), '_tc_is_ticket', true ) ) { $result['errors'][] = '#' . $p->get_id() . ' Tickera bileti değil.'; }
            if ( absint( get_post_meta( $p->get_id(), '_event_name', true ) ) !== $event_id ) { $result['errors'][] = '#' . $p->get_id() . ' farklı Tickera etkinliğine bağlı.'; }

            $types = MDG_Sessions::ticket_types_by_session( $session->id );
            $active_types = array_values( array_filter( $types, function( $t ){ return (int) $t->is_active === 1; } ) );
            $variations = array();
            foreach ( $p->get_children() as $vid ) {
                $v = wc_get_product( $vid );
                if ( $v && 'trash' !== $v->get_status() ) { $variations[] = $v; }
            }
            if ( count( $variations ) !== count( $active_types ) ) {
                $result['errors'][] = '#' . $p->get_id() . ' varyasyon sayısı (' . count($variations) . ') V2 aktif bilet türü sayısıyla (' . count($active_types) . ') eşleşmiyor.';
                continue;
            }

            $mapped_vars = array(); $used_vars = array();
            foreach ( $active_types as $type ) {
                $vmatches = array();
                foreach ( $variations as $v ) {
                    if ( isset( $used_vars[ $v->get_id() ] ) ) { continue; }
                    if ( self::variation_matches_type( $v, $type ) ) { $vmatches[] = $v; }
                }
                if ( 1 !== count( $vmatches ) ) {
                    $result['errors'][] = '#' . $p->get_id() . ' içinde “' . $type->label . '” için tek varyasyon eşleşmesi bulunamadı.';
                    continue;
                }
                $v = $vmatches[0]; $used_vars[ $v->get_id() ] = true;
                $live_price = (float) $v->get_price(); $v2_price = (float) $type->price;
                if ( abs( $live_price - $v2_price ) > 0.009 ) {
                    $result['errors'][] = '#' . $v->get_id() . ' fiyatı ' . wc_price( $live_price ) . '; V2 fiyatı ' . wc_price( $v2_price ) . '. Fiyatlar eşleşmeden bağlanmayacak.';
                }
                $mapped_vars[] = array(
                    'ticket_type_id'=>(int)$type->id, 'label'=>(string)$type->label, 'variation_id'=>(int)$v->get_id(),
                    'sku'=>(string)$v->get_sku(), 'price'=>(string)$v->get_price(),
                );
                $result['score'] += 3;
            }

            $result['sessions'][] = array(
                'session_id'=>(int)$session->id, 'date'=>$date, 'time'=>$time, 'capacity'=>(int)$session->capacity_total,
                'product_id'=>(int)$p->get_id(), 'product_name'=>(string)$p->get_name(), 'product_status'=>(string)$p->get_status(),
                'variations'=>$mapped_vars,
            );
        }
        return $result;
    }

    private static function variation_matches_type( $variation, $type ) {
        $kind = self::ticket_kind( (string)$type->code, (string)$type->label );
        $sku = strtoupper( (string)$variation->get_sku() );
        $attrs = implode( ' ', array_values( (array)$variation->get_attributes() ) );
        $hay = self::norm( $attrs );
        if ( 'child' === $kind ) { return false !== strpos( $hay, 'cocuk' ) || preg_match('/-C$/', $sku); }
        if ( 'adult' === $kind ) { return false !== strpos( $hay, 'yetiskin' ) || preg_match('/-Y$/', $sku); }
        if ( 'family' === $kind ) { return false !== strpos( $hay, 'aile' ) || false !== strpos( $sku, 'A22' ); }
        return self::norm( (string)$type->label ) === $hay;
    }

    private static function ticket_kind( $code, $label ) {
        $x = self::norm( $code . ' ' . $label );
        if ( false !== strpos( $x, 'cocuk' ) ) return 'child';
        if ( false !== strpos( $x, 'yetiskin' ) ) return 'adult';
        if ( false !== strpos( $x, 'aile' ) ) return 'family';
        return 'other';
    }

    private static function norm( $text ) {
        $x = strtolower( remove_accents( wp_strip_all_tags( (string)$text ) ) );
        $x = preg_replace( '/[^a-z0-9]+/u', ' ', $x );
        return trim( preg_replace( '/\s+/', ' ', $x ) );
    }

    private static function render_plan( $event, array $plan ) {
        echo '<h3>Eşleştirme planı: ' . esc_html( $event->title ) . '</h3>';
        if ( ! empty( $plan['errors'] ) ) {
            echo '<div class="notice notice-error inline"><p><strong>Bağlantı yapılmayacak.</strong></p><ul style="list-style:disc;padding-left:20px">';
            foreach ( $plan['errors'] as $e ) { echo '<li>' . wp_kses_post( $e ) . '</li>'; }
            echo '</ul></div>';
        }
        if ( ! empty( $plan['warnings'] ) ) {
            echo '<div class="notice notice-warning inline"><ul style="list-style:disc;padding-left:20px">';
            foreach ( $plan['warnings'] as $w ) { echo '<li>' . esc_html( $w ) . '</li>'; }
            echo '</ul></div>';
        }
        if ( empty( $plan['tickera_event_id'] ) ) { return; }
        echo '<p><strong>Mevcut Tickera etkinliği:</strong> #' . esc_html( $plan['tickera_event_id'] ) . ' ' . esc_html( get_the_title( $plan['tickera_event_id'] ) ) . '</p>';
        echo '<div class="mdg-table-scroll"><table class="widefat striped"><thead><tr><th>V2 seansı</th><th>Mevcut WooCommerce ürünü</th><th>Varyasyon eşleşmeleri</th><th>Durum</th></tr></thead><tbody>';
        foreach ( $plan['sessions'] as $r ) {
            $vt = array();
            foreach ( $r['variations'] as $v ) { $vt[] = $v['label'] . ' → #' . $v['variation_id'] . ( $v['sku'] ? ' (' . $v['sku'] . ')' : '' ) . ' · ' . wc_price( (float)$v['price'] ); }
            echo '<tr><td><strong>' . esc_html( $r['date'] . ' ' . $r['time'] ) . '</strong><br>Kapasite: ' . esc_html( $r['capacity'] ) . '</td>';
            echo '<td>#' . esc_html( $r['product_id'] ) . ' ' . esc_html( $r['product_name'] ) . '<br><small>' . esc_html( $r['product_status'] ) . '</small></td>';
            echo '<td>' . esc_html( implode( ' | ', $vt ) ) . '</td><td>' . ( empty( $plan['errors'] ) ? '<span class="mdg-pill mdg-pill-green">Hazır</span>' : '<span class="mdg-pill mdg-pill-red">Kontrol</span>' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_current_mapping( $event, array $current ) {
        echo '<div class="notice notice-success inline"><p><strong>Bu V2 taslağı mevcut canlı satış yapısına bağlı.</strong> Aşağıdaki ID’ler yalnızca Madagaskar V2 mapping alanlarında tutulur.</p></div>';
        echo '<div class="mdg-table-scroll"><table class="widefat striped"><thead><tr><th>Seans</th><th>Woo ürün</th><th>Tickera etkinliği</th><th>Varyasyonlar</th></tr></thead><tbody>';
        foreach ( $current['rows'] as $row ) {
            $s = $row['session']; list($date,$time)=MDG_Sessions::local_parts($s->start_at); $vars=array();
            foreach ( $row['types'] as $t ) { $vars[] = $t->label . ' → #' . (int)$t->wc_variation_id; }
            echo '<tr><td>' . esc_html($date.' '.$time) . '</td><td>#' . esc_html((int)$s->wc_product_id) . ' ' . esc_html(get_the_title((int)$s->wc_product_id)) . '</td><td>#' . esc_html((int)$s->tickera_event_id) . ' ' . esc_html(get_the_title((int)$s->tickera_event_id)) . '</td><td>' . esc_html(implode(' | ',$vars)) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function audit( $action, $event_id, array $context ) {
        global $wpdb;
        $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
            'user_id'=>get_current_user_id(), 'action_key'=>$action, 'object_type'=>'event', 'object_id'=>absint($event_id),
            'context'=>wp_json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 'created_at'=>MDG_DB::now(),
        ) );
    }

    private static function redirect_error( $event_id, $message ) {
        wp_safe_redirect( add_query_arg( array(
            'page'=>'mdg-settings', 'mdg_link_event'=>absint($event_id), 'mdg_adopt_error'=>rawurlencode((string)$message)
        ), admin_url('admin.php') ) );
        exit;
    }
}
