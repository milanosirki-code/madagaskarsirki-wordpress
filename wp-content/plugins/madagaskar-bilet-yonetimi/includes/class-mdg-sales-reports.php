<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only sales reporting for MDG managed ticket sales.
 *
 * Source of truth:
 * - MDG order_map: WooCommerce order line mirror written via Woo CRUD hooks.
 * - MDG sessions: shared/canonical capacity and current sold units.
 *
 * No WooCommerce order tables are queried directly; HPOS compatibility is preserved.
 */
final class MDG_Sales_Reports {
    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-bilet' ) ); }

        $filters = self::filters();
        $data    = self::query( $filters );

        echo '<div class="wrap mdg-wrap"><h1>🎪 Satış Raporları</h1>';
        echo '<p class="mdg-lead">Etkinlik, seans ve bilet türü bazında salt-okunur satış görünümü. Raporlar MDG sipariş eşleme tablosu ve ortak seans kapasitesinden üretilir; WooCommerce HPOS sipariş tablolarına doğrudan SQL yazılmaz.</p>';

        self::render_filters( $filters, $data['lookups'] );

        echo '<div class="mdg-cards">';
        self::card( 'Sipariş', number_format_i18n( $data['summary']['orders'] ) );
        self::card( 'Satılan Bilet', number_format_i18n( $data['summary']['tickets'] ) );
        self::card( 'Kişi / Kapasite Birimi', number_format_i18n( $data['summary']['units'] ) );
        self::card( 'Ciro', self::money( $data['summary']['revenue'] ) );
        self::card( 'Ortalama Sipariş', self::money( $data['summary']['avg_order'] ) );
        echo '</div>';

        if ( empty( $data['summary']['tickets'] ) ) {
            echo '<div class="mdg-panel"><p>Seçili filtrelerde ücretli MDG bilet satışı bulunamadı.</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="mdg-panel" style="margin-top:18px"><h2>Etkinlik Bazında</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Konum</th><th>Sipariş</th><th>Bilet</th><th>Kişi</th><th>Ciro</th><th>Toplam Doluluk</th></tr></thead><tbody>';
        foreach ( $data['events'] as $row ) {
            $occ = (int) $row->capacity_total > 0 ? round( ( (int) $row->sold_units / (int) $row->capacity_total ) * 100, 1 ) : 0;
            echo '<tr>';
            echo '<td><strong>' . esc_html( $row->title ) . '</strong></td>';
            echo '<td>' . esc_html( $row->province_name . ' / ' . $row->district . ' – ' . $row->venue_name ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->orders ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->tickets ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->units ) ) . '</td>';
            echo '<td><strong>' . esc_html( self::money( (float) $row->revenue ) ) . '</strong></td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->sold_units ) . ' / ' . number_format_i18n( (int) $row->capacity_total ) . ' (%' . number_format_i18n( $occ, 1 ) . ')' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="mdg-panel" style="margin-top:18px"><h2>Seans Bazında</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Seans</th><th>Sipariş</th><th>Bilet</th><th>Kişi</th><th>Ciro</th><th>Doluluk</th></tr></thead><tbody>';
        foreach ( $data['sessions'] as $row ) {
            $occ = (int) $row->capacity_total > 0 ? round( ( (int) $row->sold_units / (int) $row->capacity_total ) * 100, 1 ) : 0;
            echo '<tr>';
            echo '<td>' . esc_html( $row->title ) . '</td>';
            echo '<td><strong>' . esc_html( self::session_label( $row->start_at ) ) . '</strong></td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->orders ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->tickets ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->units ) ) . '</td>';
            echo '<td>' . esc_html( self::money( (float) $row->revenue ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->sold_units ) . ' / ' . number_format_i18n( (int) $row->capacity_total ) . ' (%' . number_format_i18n( $occ, 1 ) . ')' ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="mdg-panel" style="margin-top:18px"><h2>Bilet Türü Bazında</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Seans</th><th>Bilet Türü</th><th>Adet</th><th>Kişi</th><th>Birim Fiyat</th><th>Ciro</th></tr></thead><tbody>';
        foreach ( $data['types'] as $row ) {
            echo '<tr>';
            echo '<td>' . esc_html( $row->title ) . '</td>';
            echo '<td>' . esc_html( self::session_label( $row->start_at ) ) . '</td>';
            echo '<td><strong>' . esc_html( $row->label ) . '</strong></td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->tickets ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->units ) ) . '</td>';
            echo '<td>' . esc_html( self::money( (float) $row->price ) ) . '</td>';
            echo '<td>' . esc_html( self::money( (float) $row->revenue ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="notice notice-info inline" style="margin:18px 0 0"><p><strong>V3.3.0 rapor kuralı:</strong> Yalnızca <code>paid_at</code> kaydı bulunan ve iptal/başarısız/iade durumunda görünmeyen MDG sipariş satırları filtreli satış toplamına girer. Doluluk ise seansın güncel ortak kapasite sayaçlarını gösterir. Müşteri adı, telefon, e-posta ve check-in bilgileri ayrı “Müşteri / Bilet Listeleri” modülünde açılacaktır.</p></div>';
        echo '</div>';
    }

    private static function render_filters( $f, $lookups ) {
        echo '<form method="get" class="mdg-panel" style="margin-top:18px">';
        echo '<input type="hidden" name="page" value="mdg-reports">';
        echo '<div class="mdg-grid-3">';

        echo '<div><label>Dönem</label><select name="range">';
        $ranges = array( 'today'=>'Bugün', 'yesterday'=>'Dün', '7d'=>'Son 7 Gün', '30d'=>'Son 30 Gün', 'all'=>'Tümü', 'custom'=>'Özel Tarih' );
        foreach ( $ranges as $key=>$label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['range'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        echo '</select></div>';

        echo '<div><label>Başlangıç</label><input type="date" name="date_from" value="' . esc_attr( $f['date_from'] ) . '"></div>';
        echo '<div><label>Bitiş</label><input type="date" name="date_to" value="' . esc_attr( $f['date_to'] ) . '"></div>';

        echo '<div><label>Şehir</label><select name="province"><option value="">Tüm şehirler</option>';
        foreach ( $lookups['provinces'] as $p ) echo '<option value="' . esc_attr( $p->province_code ) . '"' . selected( $f['province'], $p->province_code, false ) . '>' . esc_html( $p->province_name ) . '</option>';
        echo '</select></div>';

        echo '<div><label>Etkinlik</label><select name="event_id"><option value="0">Tüm etkinlikler</option>';
        foreach ( $lookups['events'] as $e ) echo '<option value="' . esc_attr( (int) $e->id ) . '"' . selected( $f['event_id'], (int) $e->id, false ) . '>' . esc_html( $e->title . ' – ' . $e->province_name ) . '</option>';
        echo '</select></div>';

        echo '<div><label>Seans</label><select name="session_id"><option value="0">Tüm seanslar</option>';
        foreach ( $lookups['sessions'] as $s ) echo '<option value="' . esc_attr( (int) $s->id ) . '"' . selected( $f['session_id'], (int) $s->id, false ) . '>' . esc_html( $s->title . ' – ' . self::session_label( $s->start_at ) ) . '</option>';
        echo '</select></div>';

        echo '<div><label>Bilet Türü</label><select name="ticket_type_id"><option value="0">Tüm bilet türleri</option>';
        foreach ( $lookups['types'] as $t ) echo '<option value="' . esc_attr( (int) $t->id ) . '"' . selected( $f['ticket_type_id'], (int) $t->id, false ) . '>' . esc_html( $t->label ) . '</option>';
        echo '</select></div>';
        echo '</div>';
        echo '<p style="margin:16px 0 0"><button class="button button-primary">Raporu Göster</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=mdg-reports' ) ) . '">Filtreleri Temizle</a></p>';
        echo '</form>';
    }

    private static function query( $f ) {
        global $wpdb;
        $m = MDG_DB::table( 'order_map' );
        $e = MDG_DB::table( 'events' );
        $s = MDG_DB::table( 'sessions' );
        $t = MDG_DB::table( 'ticket_types' );

        $where = array( 'm.paid_at IS NOT NULL', "m.order_status NOT IN ('failed','cancelled','refunded','trash')" );
        $args  = array();

        if ( $f['utc_from'] ) { $where[] = 'm.paid_at >= %s'; $args[] = $f['utc_from']; }
        if ( $f['utc_to'] )   { $where[] = 'm.paid_at < %s';  $args[] = $f['utc_to']; }
        if ( $f['province'] ) { $where[] = 'e.province_code = %s'; $args[] = $f['province']; }
        if ( $f['event_id'] ) { $where[] = 'm.event_id = %d'; $args[] = $f['event_id']; }
        if ( $f['session_id'] ) { $where[] = 'm.session_id = %d'; $args[] = $f['session_id']; }
        if ( $f['ticket_type_id'] ) { $where[] = 'm.ticket_type_id = %d'; $args[] = $f['ticket_type_id']; }

        $where_sql = implode( ' AND ', $where );
        $prepare = static function( $sql ) use ( $wpdb, $args ) { return $args ? $wpdb->prepare( $sql, $args ) : $sql; };

        $summary = $wpdb->get_row( $prepare(
            "SELECT COUNT(DISTINCT m.order_id) orders, COALESCE(SUM(m.quantity),0) tickets, COALESCE(SUM(m.units_total),0) units, COALESCE(SUM(m.line_total),0) revenue
             FROM {$m} m JOIN {$e} e ON e.id=m.event_id WHERE {$where_sql}"
        ), ARRAY_A );
        $summary = array_map( static function( $v ){ return is_numeric( $v ) ? $v + 0 : $v; }, (array) $summary );
        $summary['avg_order'] = ! empty( $summary['orders'] ) ? (float) $summary['revenue'] / (int) $summary['orders'] : 0;

        $events = $wpdb->get_results( $prepare(
            "SELECT e.id,e.title,e.province_name,e.district,e.venue_name,
                    COUNT(DISTINCT m.order_id) orders,SUM(m.quantity) tickets,SUM(m.units_total) units,SUM(m.line_total) revenue,
                    (SELECT COALESCE(SUM(sx.capacity_total),0) FROM {$s} sx WHERE sx.event_id=e.id) capacity_total,
                    (SELECT COALESCE(SUM(sx.sold_units),0) FROM {$s} sx WHERE sx.event_id=e.id) sold_units
             FROM {$m} m JOIN {$e} e ON e.id=m.event_id
             WHERE {$where_sql}
             GROUP BY e.id,e.title,e.province_name,e.district,e.venue_name
             ORDER BY revenue DESC,e.title ASC"
        ) );

        $sessions = $wpdb->get_results( $prepare(
            "SELECT s.id,s.start_at,s.capacity_total,s.sold_units,e.title,
                    COUNT(DISTINCT m.order_id) orders,SUM(m.quantity) tickets,SUM(m.units_total) units,SUM(m.line_total) revenue
             FROM {$m} m JOIN {$e} e ON e.id=m.event_id JOIN {$s} s ON s.id=m.session_id
             WHERE {$where_sql}
             GROUP BY s.id,s.start_at,s.capacity_total,s.sold_units,e.title
             ORDER BY s.start_at ASC"
        ) );

        $types = $wpdb->get_results( $prepare(
            "SELECT t.id,t.label,t.price,s.start_at,e.title,SUM(m.quantity) tickets,SUM(m.units_total) units,SUM(m.line_total) revenue
             FROM {$m} m JOIN {$e} e ON e.id=m.event_id JOIN {$s} s ON s.id=m.session_id JOIN {$t} t ON t.id=m.ticket_type_id
             WHERE {$where_sql}
             GROUP BY t.id,t.label,t.price,s.start_at,e.title
             ORDER BY s.start_at ASC,t.sort_order ASC,t.label ASC"
        ) );

        $lookups = array(
            'provinces' => $wpdb->get_results( "SELECT DISTINCT province_code,province_name FROM {$e} ORDER BY province_name" ),
            'events'    => $wpdb->get_results( "SELECT id,title,province_name FROM {$e} ORDER BY created_at DESC" ),
            'sessions'  => $wpdb->get_results( "SELECT s.id,s.start_at,e.title FROM {$s} s JOIN {$e} e ON e.id=s.event_id ORDER BY s.start_at DESC LIMIT 500" ),
            'types'     => $wpdb->get_results( "SELECT DISTINCT t.id,t.label FROM {$t} t WHERE t.is_active=1 ORDER BY t.label" ),
        );

        return compact( 'summary', 'events', 'sessions', 'types', 'lookups' );
    }

    private static function filters() {
        $range = sanitize_key( $_GET['range'] ?? '30d' );
        if ( ! in_array( $range, array( 'today','yesterday','7d','30d','all','custom' ), true ) ) { $range = '30d'; }
        $date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
        $date_to   = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) );

        $tz = wp_timezone();
        $now = new DateTimeImmutable( 'now', $tz );
        $start = null; $end = null;
        if ( 'today' === $range ) {
            $start = $now->setTime( 0, 0, 0 ); $end = $start->modify( '+1 day' );
        } elseif ( 'yesterday' === $range ) {
            $end = $now->setTime( 0, 0, 0 ); $start = $end->modify( '-1 day' );
        } elseif ( '7d' === $range ) {
            $end = $now->setTime( 0, 0, 0 )->modify( '+1 day' ); $start = $now->setTime( 0, 0, 0 )->modify( '-6 days' );
        } elseif ( '30d' === $range ) {
            $end = $now->setTime( 0, 0, 0 )->modify( '+1 day' ); $start = $now->setTime( 0, 0, 0 )->modify( '-29 days' );
        } elseif ( 'custom' === $range ) {
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) { $start = new DateTimeImmutable( $date_from . ' 00:00:00', $tz ); }
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) { $end = ( new DateTimeImmutable( $date_to . ' 00:00:00', $tz ) )->modify( '+1 day' ); }
        }

        if ( $start && ! $date_from ) { $date_from = $start->format( 'Y-m-d' ); }
        if ( $end && ! $date_to ) { $date_to = $end->modify( '-1 day' )->format( 'Y-m-d' ); }

        $utc = new DateTimeZone( 'UTC' );
        return array(
            'range'          => $range,
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'utc_from'       => $start ? $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) : '',
            'utc_to'         => $end ? $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) : '',
            'province'       => sanitize_text_field( wp_unslash( $_GET['province'] ?? '' ) ),
            'event_id'       => absint( $_GET['event_id'] ?? 0 ),
            'session_id'     => absint( $_GET['session_id'] ?? 0 ),
            'ticket_type_id' => absint( $_GET['ticket_type_id'] ?? 0 ),
        );
    }

    private static function session_label( $utc_value ) {
        if ( ! $utc_value ) { return '—'; }
        if ( class_exists( 'MDG_Sessions' ) ) {
            list( $date, $time ) = MDG_Sessions::local_parts( (string) $utc_value );
            if ( $date && $time ) {
                $ts = strtotime( $date . ' 12:00:00' );
                return ( $ts ? wp_date( 'd.m.Y', $ts ) : $date ) . ' ' . $time;
            }
        }
        return (string) $utc_value;
    }

    private static function card( $label, $value ) {
        echo '<div class="mdg-card"><div class="mdg-card-value">' . esc_html( $value ) . '</div><div>' . esc_html( $label ) . '</div></div>';
    }

    private static function money( $amount ) {
        if ( function_exists( 'wc_price' ) ) { return wp_strip_all_tags( wc_price( (float) $amount, array( 'currency'=>'TRY' ) ) ); }
        return number_format_i18n( (float) $amount, 2 ) . ' ₺';
    }
}
