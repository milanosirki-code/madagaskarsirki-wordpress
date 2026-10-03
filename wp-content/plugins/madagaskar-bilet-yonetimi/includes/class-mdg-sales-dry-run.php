<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.6.1: Salt-okunur satış üretim planı.
 * Hiçbir WooCommerce/Tickera nesnesine yazma yapmaz.
 */
final class MDG_Sales_Dry_Run {

    public static function render() {
        $drafts = class_exists( 'MDG_Events' ) ? MDG_Events::drafts() : array();
        echo '<div class="mdg-panel"><h2>V2.6.1 Satış Üretim Dry-Run</h2>';
        echo '<p>Bu ekran <strong>yalnızca okur ve plan üretir</strong>. WooCommerce ürünü, varyasyon, Tickera etkinliği, bilet veya sipariş oluşturmaz.</p>';

        if ( ! $drafts ) {
            echo '<div class="notice notice-warning inline"><p>Dry-run yapılacak Madagaskar V2 etkinlik taslağı bulunamadı.</p></div></div>';
            return;
        }

        $requested = absint( $_GET['mdg_dry_event'] ?? 0 );
        $selected = null;
        foreach ( $drafts as $d ) {
            if ( $requested && (int) $d->id === $requested ) { $selected = $d; break; }
        }
        if ( ! $selected ) { $selected = $drafts[0]; }

        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:flex;gap:10px;align-items:end;margin:14px 0 20px">';
        echo '<input type="hidden" name="page" value="mdg-settings">';
        echo '<label><strong>Taslak seç</strong><br><select name="mdg_dry_event">';
        foreach ( $drafts as $d ) {
            echo '<option value="' . esc_attr( $d->id ) . '" ' . selected( (int) $selected->id, (int) $d->id, false ) . '>' . esc_html( '#' . $d->id . ' ' . $d->title ) . '</option>';
        }
        echo '</select></label><button class="button button-secondary">Dry-Run Planını Göster</button></form>';

        $reference = self::reference_pattern();
        self::render_reference( $reference );

        $plan = self::plan_for_event( $selected, $reference );
        self::render_plan( $selected, $plan );

        echo '<div class="notice notice-info inline" style="margin-top:20px"><p><strong>V2.6.1 güvenlik sınırı:</strong> Bu sürümde yazma kodu yoktur. Bir sonraki sürümde üretim yapılacaksa yalnızca bu dry-run planı onaylandıktan sonra, nonce + yetki + tekrar-çalıştırma koruması + rollback/temizleme yaklaşımı ile hazırlanmalıdır.</p></div>';
        echo '</div>';
    }

    private static function reference_pattern() {
        $out = array(
            'event_id' => 0,
            'event_title' => '',
            'event_meta' => array(),
            'products' => array(),
            'same_event' => false,
            'warnings' => array(),
        );
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
            $out['warnings'][] = 'WooCommerce aktif değil.';
            return $out;
        }

        $q = new WP_Query( array(
            'post_type' => 'product',
            'post_status' => array( 'publish', 'private', 'draft' ),
            'posts_per_page' => 12,
            'orderby' => 'ID',
            'order' => 'DESC',
            'fields' => 'ids',
            'meta_query' => array( array( 'key' => '_tc_is_ticket', 'value' => 'yes' ) ),
            'no_found_rows' => true,
        ) );

        $groups = array();
        foreach ( (array) $q->posts as $pid ) {
            $eid = absint( get_post_meta( $pid, '_event_name', true ) );
            if ( ! $eid ) { continue; }
            if ( ! isset( $groups[ $eid ] ) ) { $groups[ $eid ] = array(); }
            $groups[ $eid ][] = $pid;
        }
        if ( ! $groups ) {
            $out['warnings'][] = 'Referans alınacak _tc_is_ticket=yes ürün grubu bulunamadı.';
            return $out;
        }
        uasort( $groups, function ( $a, $b ) { return count( $b ) <=> count( $a ); } );
        $event_id = (int) array_key_first( $groups );
        $product_ids = $groups[ $event_id ];

        $out['event_id'] = $event_id;
        $out['event_title'] = get_the_title( $event_id );
        $keys = array(
            'event_date_time', 'event_end_date_time', 'event_location', 'event_terms',
            'event_logo_file_url', 'event_presentation_page', 'hide_event_after_expiration',
            'limit_level', 'limit_level_value', 'show_tickets_automatically'
        );
        foreach ( $keys as $key ) {
            $val = get_post_meta( $event_id, $key, true );
            if ( is_scalar( $val ) ) { $out['event_meta'][ $key ] = (string) $val; }
        }

        foreach ( $product_ids as $pid ) {
            $p = wc_get_product( $pid );
            if ( ! $p ) { continue; }
            $row = array(
                'id' => (int) $pid,
                'name' => $p->get_name(),
                'status' => $p->get_status(),
                'type' => $p->get_type(),
                'sku' => $p->get_sku(),
                'event_id' => absint( get_post_meta( $pid, '_event_name', true ) ),
                'tc_is_ticket' => (string) get_post_meta( $pid, '_tc_is_ticket', true ),
                'image_id' => (int) $p->get_image_id(),
                'categories' => wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) ),
                'variations' => array(),
            );
            if ( $p->is_type( 'variable' ) ) {
                foreach ( $p->get_children() as $vid ) {
                    $v = wc_get_product( $vid );
                    if ( ! $v ) { continue; }
                    $row['variations'][] = array(
                        'id' => (int) $vid,
                        'sku' => $v->get_sku(),
                        'price' => $v->get_price(),
                        'regular_price' => $v->get_regular_price(),
                        'attributes' => $v->get_attributes(),
                        'manage_stock' => $v->get_manage_stock(),
                        'stock_status' => $v->get_stock_status(),
                    );
                }
            }
            $out['products'][] = $row;
        }

        $out['same_event'] = count( array_unique( array_map( function ( $p ) { return (int) $p['event_id']; }, $out['products'] ) ) ) <= 1;
        usort( $out['products'], function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
        return $out;
    }

    private static function plan_for_event( $event, array $reference ) {
        $sessions = MDG_Sessions::by_event( $event->id );
        $catalogue = MDG_Sessions::ticket_catalogue_for_event( $event->id );
        $city_code = self::city_code( $event->province_name );
        $products = array();
        $warnings = array();

        if ( 'draft' !== (string) $event->status ) { $warnings[] = 'Seçili etkinlik taslak durumunda değil.'; }
        if ( ! $sessions ) { $warnings[] = 'Etkinlikte seans yok.'; }
        if ( ! $catalogue ) { $warnings[] = 'Etkinlikte aktif bilet türü yok.'; }
        if ( empty( trim( (string) $event->venue_address ) ) ) { $warnings[] = 'Salon açık adresi eksik.'; }
        if ( empty( trim( (string) $event->venue_maps_url ) ) ) { $warnings[] = 'Salon Maps bağlantısı eksik; satış motorunu engellemez fakat bilet konum QR akışı tamamlanamaz.'; }

        foreach ( $sessions as $session ) {
            list( $date, $time ) = MDG_Sessions::local_parts( $session->start_at );
            $human = self::human_date_tr( $date );
            $hhmm = str_replace( ':', '', $time );
            $ymd = str_replace( '-', '', $date );
            $name = 'Madagaskar Sirki - ' . $event->province_name . ' - ' . $human . ' -' . $time . ' Bileti';
            $slug = sanitize_title( 'madagaskar-sirki-' . $event->province_name . '-' . $date . '-' . $hhmm . '-bileti' );
            $vars = array();
            foreach ( $catalogue as $type ) {
                $suffix = self::ticket_suffix( $type->code, $type->label );
                $vars[] = array(
                    'ticket_type_id' => (int) $type->id,
                    'code' => (string) $type->code,
                    'label' => (string) $type->label,
                    'price' => number_format( (float) $type->price, 2, '.', '' ),
                    'capacity_units' => (int) $type->capacity_units,
                    'sku' => $city_code . '-' . $ymd . '-' . $hhmm . '-' . $suffix,
                );
            }
            $products[] = array(
                'session_id' => (int) $session->id,
                'start_at' => (string) $session->start_at,
                'date' => $date,
                'time' => $time,
                'capacity' => (int) $session->capacity_total,
                'name' => $name,
                'slug' => $slug,
                'parent_sku' => '',
                'variations' => $vars,
            );
        }

        return array(
            'city_code' => $city_code,
            'sessions' => $sessions,
            'catalogue' => $catalogue,
            'products' => $products,
            'tickera' => array(
                'strategy' => ! empty( $reference['same_event'] ) ? 'one_event_many_session_products' : 'pending',
                'title' => (string) $event->title,
                'location' => (string) $event->venue_name,
                'first_start' => $sessions ? (string) $sessions[0]->start_at : '',
            ),
            'warnings' => $warnings,
        );
    }

    private static function render_reference( array $r ) {
        echo '<h3>1. Canlı sistemden doğrulanan referans desen</h3>';
        if ( $r['warnings'] ) {
            echo '<div class="notice notice-warning inline"><ul style="list-style:disc;padding-left:20px">';
            foreach ( $r['warnings'] as $w ) echo '<li>' . esc_html( $w ) . '</li>';
            echo '</ul></div>';
        }
        if ( ! $r['event_id'] ) { return; }
        echo '<p><strong>Tickera etkinliği:</strong> #' . esc_html( $r['event_id'] ) . ' ' . esc_html( $r['event_title'] ) . '</p>';
        echo '<div class="mdg-table-scroll"><table class="widefat striped"><thead><tr><th>Woo ürün</th><th>Bridge bağı</th><th>Varyasyonlar</th></tr></thead><tbody>';
        foreach ( $r['products'] as $p ) {
            $vtxt = array();
            foreach ( $p['variations'] as $v ) {
                $attrs = array();
                foreach ( (array) $v['attributes'] as $k => $val ) $attrs[] = $k . '=' . $val;
                $vtxt[] = '#' . $v['id'] . ' · ' . ( $v['sku'] ?: 'SKU yok' ) . ' · ' . wc_price( (float) $v['price'] ) . ' · ' . implode( ', ', $attrs );
            }
            echo '<tr><td><strong>#' . esc_html( $p['id'] ) . ' ' . esc_html( $p['name'] ) . '</strong><br><small>' . esc_html( $p['type'] . ' / ' . $p['status'] ) . '</small></td>';
            echo '<td><code>_tc_is_ticket=' . esc_html( $p['tc_is_ticket'] ) . '</code><br><code>_event_name=' . esc_html( $p['event_id'] ) . '</code></td>';
            echo '<td>' . implode( '<br>', array_map( 'esc_html', $vtxt ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ( count( $r['products'] ) >= 2 && $r['same_event'] ) {
            echo '<div class="notice notice-success inline"><p><strong>Referans desen doğrulandı:</strong> Bir Tickera etkinliğine birden fazla seans ürünü bağlanıyor; her seans WooCommerce tarafında ayrı variable product.</p></div>';
        }
        if ( $r['event_meta'] ) {
            echo '<details style="margin-top:12px"><summary><strong>Referans Tickera etkinlik metalarını göster</strong></summary><table class="widefat striped" style="margin-top:10px"><tbody>';
            foreach ( $r['event_meta'] as $k => $v ) echo '<tr><th style="width:240px"><code>' . esc_html( $k ) . '</code></th><td>' . esc_html( $v ) . '</td></tr>';
            echo '</tbody></table></details>';
        }
    }

    private static function render_plan( $event, array $plan ) {
        echo '<h3 style="margin-top:28px">2. V2 taslağı için üretim planı</h3>';
        echo '<p><strong>Taslak:</strong> #' . esc_html( $event->id ) . ' ' . esc_html( $event->title ) . ' · ' . esc_html( $event->province_name . ' / ' . $event->district . ' · ' . $event->venue_name ) . '</p>';
        echo '<p><strong>Otomatik şehir kodu:</strong> <code>' . esc_html( $plan['city_code'] ) . '</code> · <strong>Tickera stratejisi:</strong> ' . esc_html( 'one_event_many_session_products' === $plan['tickera']['strategy'] ? '1 Tickera etkinliği + seans başına 1 Woo variable product' : 'Henüz kesinleşmedi' ) . '</p>';

        if ( $plan['warnings'] ) {
            echo '<div class="notice notice-warning inline"><p><strong>Üretim öncesi notlar:</strong></p><ul style="list-style:disc;padding-left:20px">';
            foreach ( $plan['warnings'] as $w ) echo '<li>' . esc_html( $w ) . '</li>';
            echo '</ul></div>';
        }

        echo '<div class="mdg-table-scroll"><table class="widefat striped"><thead><tr><th>Seans</th><th>Planlanan Woo ürünü</th><th>Ortak kapasite</th><th>Planlanan varyasyonlar / SKU</th></tr></thead><tbody>';
        foreach ( $plan['products'] as $p ) {
            $vs = array();
            foreach ( $p['variations'] as $v ) {
                $vs[] = esc_html( $v['label'] ) . ' · ' . wp_kses_post( wc_price( (float) $v['price'] ) ) . ' · <code>' . esc_html( $v['sku'] ) . '</code> · kapasite ' . esc_html( $v['capacity_units'] );
            }
            echo '<tr><td><strong>' . esc_html( $p['date'] . ' ' . $p['time'] ) . '</strong><br><small>MDG session #' . esc_html( $p['session_id'] ) . '</small></td>';
            echo '<td><strong>' . esc_html( $p['name'] ) . '</strong><br><code>' . esc_html( $p['slug'] ) . '</code><br><small>Parent SKU: boş (canlı referansla uyumlu)</small></td>';
            echo '<td><strong>' . esc_html( $p['capacity'] ) . ' kişi</strong><br><small>Woo varyasyon stoğu kapasite kaynağı olmayacak; gerçek kaynak MDG seansı.</small></td>';
            echo '<td>' . implode( '<br>', $vs ) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<h4 style="margin-top:18px">3. Gerçek üretimde yazılması planlanan temel bağlantılar</h4>';
        echo '<ul style="list-style:disc;padding-left:22px">';
        echo '<li>Tek Tickera <code>tc_events</code> kaydı: <strong>' . esc_html( $event->title ) . '</strong>.</li>';
        echo '<li>Her seans için bir WooCommerce <strong>variable product</strong>.</li>';
        echo '<li>Parent product: <code>_tc_is_ticket=yes</code> ve <code>_event_name=&lt;yeni Tickera event ID&gt;</code>.</li>';
        echo '<li>Aktif her bilet türü için bir variation; fiyat ve deterministik SKU yukarıdaki plandan alınacak.</li>';
        echo '<li>Üretim tamamlanırsa MDG session <code>wc_product_id</code>/<code>tickera_event_id</code> ve ticket type <code>wc_variation_id</code> alanları yazılacak.</li>';
        echo '<li>İlk üretim <strong>taslak/private satış nesneleri</strong> ile yapılacak; otomatik publish edilmeyecek.</li>';
        echo '<li>Mevcut canlı #1451 / #1455 / #1594 / #1597 nesnelerine hiçbir yazma yapılmayacak.</li>';
        echo '</ul>';

        echo '<div class="notice notice-success inline"><p><strong>Dry-run sonucu:</strong> Bu plan herhangi bir satış nesnesi oluşturmadı. Ürün adları, seanslar, ortak kapasite, fiyatlar ve SKU planı canlı Bridge desenine göre hazırlandı.</p></div>';
    }

    private static function city_code( $province ) {
        $map = array(
            'Ankara'=>'ANK','İstanbul'=>'IST','İzmir'=>'IZM','Eskişehir'=>'ESK','Bursa'=>'BUR','Antalya'=>'ANT','Adana'=>'ADA','Konya'=>'KON','Gaziantep'=>'GAZ','Mersin'=>'MER','Kayseri'=>'KAY','Kocaeli'=>'KOC','Sakarya'=>'SAK','Manisa'=>'MAN','Aydın'=>'AYD','Muğla'=>'MUG','Denizli'=>'DEN','Balıkesir'=>'BAL','Sivas'=>'SIV','Elazığ'=>'ELA','Diyarbakır'=>'DIY','Mardin'=>'MAR','Van'=>'VAN','Muş'=>'MUS','Bitlis'=>'BIT','Ağrı'=>'AGR','Hatay'=>'HAT','Kahramanmaraş'=>'KMR','Kilis'=>'KIL','Çorum'=>'COR','Çankırı'=>'CKR','Bilecik'=>'BIL','Bolu'=>'BOL','Bartın'=>'BAR','Zonguldak'=>'ZON','Kastamonu'=>'KAS','Tokat'=>'TOK','Erzurum'=>'ERZ','Isparta'=>'ISP','Burdur'=>'BRD','Afyonkarahisar'=>'AFY','Uşak'=>'USK','Çanakkale'=>'CAN','Aksaray'=>'AKS','Adıyaman'=>'ADI','Siirt'=>'SII','Kütahya'=>'KUT','Karaman'=>'KAR','Tekirdağ'=>'TEK','Kırklareli'=>'KIR','Edirne'=>'EDI','Amasya'=>'AMA','Yalova'=>'YAL'
        );
        if ( isset( $map[ $province ] ) ) return $map[ $province ];
        $x = strtoupper( remove_accents( (string) $province ) );
        $x = preg_replace( '/[^A-Z]/', '', $x );
        return substr( str_pad( $x, 3, 'X' ), 0, 3 );
    }

    private static function ticket_suffix( $code, $label ) {
        $c = strtoupper( (string) $code );
        if ( false !== strpos( $c, 'COCUK' ) || false !== strpos( strtoupper( remove_accents( $label ) ), 'COCUK' ) ) return 'C';
        if ( false !== strpos( $c, 'YETISKIN' ) || false !== strpos( strtoupper( remove_accents( $label ) ), 'YETISKIN' ) ) return 'Y';
        if ( false !== strpos( $c, 'AILE' ) ) return 'A22';
        $x = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', remove_accents( (string) $code ) ) );
        return substr( $x ?: 'B', 0, 6 );
    }

    private static function human_date_tr( $ymd ) {
        $months = array( 1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık' );
        $parts = explode( '-', (string) $ymd );
        if ( 3 !== count( $parts ) ) return (string) $ymd;
        $m = absint( $parts[1] );
        return absint( $parts[2] ) . ' ' . ( $months[ $m ] ?? $parts[1] ) . ' ' . absint( $parts[0] );
    }
}
