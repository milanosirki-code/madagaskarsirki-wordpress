<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V3.5.0 read-only customer / ticket list with cascading filters and exports.
 *
 * WooCommerce customer/order data is always read through WC CRUD (HPOS safe).
 * MDG's own order_map/event/session/ticket_type tables are queried directly.
 * Tickera ticket-instance data is discovered read-only through WordPress post APIs.
 */
final class MDG_Customer_Tickets {
    const PER_PAGE = 50;

    public static function hooks() {
        add_action( 'admin_post_mdg_customer_tickets_csv', array( __CLASS__, 'export_csv' ) );
        add_action( 'admin_post_mdg_customer_tickets_excel', array( __CLASS__, 'export_excel' ) );
        add_action( 'admin_post_mdg_customer_tickets_pdf', array( __CLASS__, 'export_pdf' ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-bilet' ) );
        }

        $filters = self::filters();
        $data    = self::query_rows( $filters, true );
        $lookups = self::lookups( $filters );

        echo '<div class="wrap mdg-wrap"><h1>🎪 Müşteri / Bilet Listeleri</h1>';
        echo '<p class="mdg-lead">Sipariş, müşteri, seans ve bilet türü bazında salt-okunur operasyon listesi. WooCommerce siparişleri HPOS uyumlu CRUD API ile okunur; bu ekran sipariş, bilet, kapasite veya check-in kaydına yazma yapmaz.</p>';

        self::render_filters( $filters, $lookups );

        echo '<div class="mdg-cards">';
        self::card( 'Filtreli Sipariş', number_format_i18n( (int) $data['summary']['orders'] ) );
        self::card( 'Bilet Adedi', number_format_i18n( (int) $data['summary']['tickets'] ) );
        self::card( 'Kişi / Kapasite Birimi', number_format_i18n( (int) $data['summary']['units'] ) );
        self::card( 'Ciro', self::money( (float) $data['summary']['revenue'] ) );
        echo '</div>';

        if ( ! $data['rows'] ) {
            echo '<div class="mdg-panel" style="margin-top:18px"><p>Seçili filtrelerde MDG sipariş/bilet kaydı bulunamadı.</p></div></div>';
            return;
        }

        echo '<div class="mdg-panel" style="margin-top:18px">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><h2 style="margin-bottom:4px">Sipariş ve Bilet Satırları</h2><p class="description" style="margin-top:0">Her satır bir WooCommerce sipariş kalemidir. Adet alanı aynı bilet türünden kaç bilet satın alındığını gösterir.</p></div>';
        $csv_url   = self::csv_url( $filters );
        $excel_url = self::excel_url( $filters );
        $pdf_url   = self::pdf_url( $filters );
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="button" href="' . esc_url( $csv_url ) . '">CSV İndir</a>';
        echo '<a class="button" href="' . esc_url( $excel_url ) . '">Excel (.xlsx) İndir</a>';
        echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $pdf_url ) . '">PDF / Yazdır</a>';
        echo '</div></div>';

        echo '<div style="overflow:auto"><table class="widefat striped" style="min-width:1350px"><thead><tr>';
        foreach ( array( 'Sipariş','Müşteri','İletişim','Etkinlik / Seans','Bilet Türü','Adet','Tutar','Ödeme','Tickera Bilet','Check-in','İşlem' ) as $h ) {
            echo '<th>' . esc_html( $h ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $ticket_cache = array();
        foreach ( $data['rows'] as $row ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row->order_id ) : null;
            if ( ! $order ) { continue; }

            $oid = (int) $order->get_id();
            if ( ! array_key_exists( $oid, $ticket_cache ) ) {
                $ticket_cache[ $oid ] = self::ticket_instances_for_order( $oid );
            }
            $tickets = $ticket_cache[ $oid ];
            $ticket_summary = self::ticket_summary( $tickets );

            $name  = trim( (string) $order->get_formatted_billing_full_name() );
            if ( '' === $name ) { $name = trim( (string) $order->get_billing_first_name() . ' ' . (string) $order->get_billing_last_name() ); }
            $email = (string) $order->get_billing_email();
            $phone = (string) $order->get_billing_phone();
            $paid  = $order->get_date_paid();
            $order_date = $paid ?: $order->get_date_created();
            $edit_url = method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $oid );
            $ticket_link = self::ticket_link_from_order( $order );

            echo '<tr>';
            echo '<td><strong>#' . esc_html( $oid ) . '</strong><br><span class="description">' . esc_html( $order_date ? self::date_label( $order_date ) : '—' ) . '</span></td>';
            echo '<td><strong>' . esc_html( $name ?: '—' ) . '</strong>';
            $owners = self::ticket_owner_names( $tickets );
            if ( $owners ) { echo '<br><span class="description">Bilet sahibi: ' . esc_html( implode( ', ', array_slice( $owners, 0, 3 ) ) ) . ( count( $owners ) > 3 ? '…' : '' ) . '</span>'; }
            echo '</td>';
            echo '<td>' . ( $phone ? '<a href="tel:' . esc_attr( preg_replace( '/\s+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '—' ) . '<br>' . ( $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—' ) . '</td>';
            echo '<td><strong>' . esc_html( $row->title ) . '</strong><br><span class="description">' . esc_html( self::session_label( $row->start_at ) ) . ' · ' . esc_html( $row->venue_name ) . '</span></td>';
            echo '<td>' . esc_html( $row->ticket_label ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $row->quantity ) ) . '</td>';
            echo '<td><strong>' . esc_html( self::money( (float) $row->line_total ) ) . '</strong></td>';
            echo '<td><span class="mdg-status ' . esc_attr( self::status_class( (string) $order->get_status() ) ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span><br><span class="description">' . ( $paid ? 'Ödendi ' . esc_html( self::date_label( $paid ) ) : 'Ödeme bekleniyor' ) . '</span></td>';
            echo '<td>';
            if ( $ticket_summary['count'] > 0 ) {
                echo '<strong>' . esc_html( number_format_i18n( $ticket_summary['count'] ) ) . '</strong> bilet';
                if ( $ticket_summary['codes'] ) {
                    echo '<details style="margin-top:4px"><summary style="cursor:pointer">Kodlar</summary><small>' . esc_html( implode( ', ', array_slice( $ticket_summary['codes'], 0, 8 ) ) ) . ( count( $ticket_summary['codes'] ) > 8 ? '…' : '' ) . '</small></details>';
                }
            } else {
                echo '<span class="description">Henüz eşleşme algılanmadı</span>';
            }
            echo '</td>';
            echo '<td>';
            if ( null === $ticket_summary['checkin_known'] ) {
                echo '<span class="description">—</span>';
            } else {
                echo '<strong>' . esc_html( $ticket_summary['checked_in'] . ' / ' . $ticket_summary['count'] ) . '</strong>';
            }
            echo '</td>';
            echo '<td><a class="button button-small" href="' . esc_url( $edit_url ) . '">Siparişi Aç</a>';
            if ( $ticket_link ) { echo '<br><a class="button button-small" style="margin-top:5px" target="_blank" rel="noopener" href="' . esc_url( $ticket_link ) . '">Biletlerim ↗</a>'; }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="description" style="margin-bottom:0">Tickera bilet ve check-in sütunları, mevcut <code>tc_tickets_instances</code> kayıtlarında sipariş bağlantısı ve check-in metası algılanabildiği ölçüde gösterilir. “—” görünmesi satış/bilet hatası anlamına gelmez; yalnızca ilgili Tickera meta anahtarının bu sürümde güvenilir biçimde tespit edilemediğini gösterir.</p>';
        echo '</div>';

        self::pagination( $filters, (int) $data['total_rows'] );
        echo '</div>';
    }

    public static function export_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkisiz işlem.' ); }
        check_admin_referer( 'mdg_customer_tickets_csv', 'mdg_nonce' );

        $filters = self::filters();
        $filters['paged'] = 1;
        $filters['per_page'] = 5000;
        $data = self::query_rows( $filters, false );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="madagaskar-musteri-bilet-listesi-' . gmdate( 'Ymd-His' ) . '.csv"' );
        echo "\xEF\xBB\xBF";
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'Sipariş No','Sipariş Tarihi','Müşteri','Telefon','E-posta','Etkinlik','Şehir','İlçe','Salon','Seans','Bilet Türü','Adet','Kişi/Kapasite','Satır Tutarı','Sipariş Durumu','Ödeme Tarihi','Tickera Bilet Adedi','Check-in' ), ';' );

        $ticket_cache = array();
        foreach ( $data['rows'] as $row ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row->order_id ) : null;
            if ( ! $order ) { continue; }
            $oid = (int) $order->get_id();
            if ( ! array_key_exists( $oid, $ticket_cache ) ) { $ticket_cache[ $oid ] = self::ticket_instances_for_order( $oid ); }
            $ts = self::ticket_summary( $ticket_cache[ $oid ] );
            $paid = $order->get_date_paid();
            $created = $paid ?: $order->get_date_created();
            fputcsv( $out, array(
                $oid,
                $created ? self::date_label( $created ) : '',
                trim( (string) $order->get_formatted_billing_full_name() ),
                (string) $order->get_billing_phone(),
                (string) $order->get_billing_email(),
                (string) $row->title,
                (string) $row->province_name,
                (string) $row->district,
                (string) $row->venue_name,
                self::session_label( $row->start_at ),
                (string) $row->ticket_label,
                (int) $row->quantity,
                (int) $row->units_total,
                number_format( (float) $row->line_total, 2, ',', '' ),
                wc_get_order_status_name( $order->get_status() ),
                $paid ? self::date_label( $paid ) : '',
                (int) $ts['count'],
                null === $ts['checkin_known'] ? '' : $ts['checked_in'] . '/' . $ts['count'],
            ), ';' );
        }
        fclose( $out );
        exit;
    }

    public static function export_excel() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkisiz işlem.' ); }
        check_admin_referer( 'mdg_customer_tickets_excel', 'mdg_nonce' );

        $filters = self::filters();
        $filters['paged'] = 1;
        $filters['per_page'] = 5000;
        $data = self::query_rows( $filters, false );
        $matrix = self::export_matrix( $data );

        if ( ! class_exists( 'ZipArchive' ) ) {
            self::export_excel_legacy( $matrix );
        }

        $tmp = wp_tempnam( 'mdg-customer-tickets.xlsx' );
        if ( ! $tmp ) { wp_die( 'Geçici Excel dosyası oluşturulamadı.' ); }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            @unlink( $tmp );
            wp_die( 'Excel dosyası hazırlanamadı.' );
        }

        $sheet_rows = array();
        foreach ( $matrix as $r => $row ) {
            $cells = array();
            foreach ( array_values( $row ) as $c => $value ) {
                $ref = self::xlsx_col( $c + 1 ) . ( $r + 1 );
                $style = 0 === $r ? ' s="1"' : '';
                if ( 0 !== $r && in_array( $c, array( 11, 12, 13, 16 ), true ) && is_numeric( $value ) ) {
                    $cells[] = '<c r="' . $ref . '"' . $style . '><v>' . esc_html( (string) $value ) . '</v></c>';
                } else {
                    $text = self::xml_text( self::excel_safe_text( (string) $value ) );
                    $cells[] = '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $text . '</t></is></c>';
                }
            }
            $sheet_rows[] = '<row r="' . ( $r + 1 ) . '">' . implode( '', $cells ) . '</row>';
        }

        $last_col = self::xlsx_col( count( $matrix[0] ?? array( 'A' ) ) );
        $last_row = max( 1, count( $matrix ) );
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . self::xlsx_columns( count( $matrix[0] ?? array() ) ) . '</cols>'
            . '<sheetData>' . implode( '', $sheet_rows ) . '</sheetData>'
            . '<autoFilter ref="A1:' . $last_col . $last_row . '"/>'
            . '</worksheet>';

        $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
        $zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
        $zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Müşteri Bilet Listesi" sheetId="1" r:id="rId1"/></sheets></workbook>' );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
        $zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' );
        $zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
        $zip->close();

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="madagaskar-musteri-bilet-listesi-' . gmdate( 'Ymd-His' ) . '.xlsx"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        readfile( $tmp );
        @unlink( $tmp );
        exit;
    }

    public static function export_pdf() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkisiz işlem.' ); }
        check_admin_referer( 'mdg_customer_tickets_pdf', 'mdg_nonce' );

        $filters = self::filters();
        $filters['paged'] = 1;
        $filters['per_page'] = 5000;
        $data = self::query_rows( $filters, false );
        $matrix = self::export_matrix( $data );

        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Madagaskar Müşteri / Bilet Listesi</title><style>';
        echo '@page{size:A4 landscape;margin:10mm}body{font-family:Arial,Helvetica,sans-serif;color:#111;font-size:10px;margin:0}h1{font-size:20px;margin:0 0 8px}.meta{margin-bottom:12px;color:#555}.toolbar{margin:0 0 12px}.toolbar button{padding:8px 12px;margin-right:6px}table{border-collapse:collapse;width:100%;table-layout:auto}th,td{border:1px solid #bbb;padding:4px 5px;vertical-align:top;word-break:break-word}th{background:#eee;font-weight:700}.summary{display:flex;gap:16px;margin:8px 0 12px}.summary b{font-size:13px}@media print{.toolbar{display:none}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}';
        echo '</style></head><body><div class="toolbar"><button onclick="window.print()">PDF Kaydet / Yazdır</button><button onclick="window.close()">Kapat</button></div>';
        echo '<h1>Madagaskar Sirki — Müşteri / Bilet Listesi</h1>';
        echo '<div class="meta">Oluşturulma: ' . esc_html( wp_date( 'd.m.Y H:i', null, wp_timezone() ) ) . ' · Filtreler mevcut liste ile aynıdır.</div>';
        echo '<div class="summary"><div>Sipariş: <b>' . esc_html( number_format_i18n( (int) $data['summary']['orders'] ) ) . '</b></div><div>Bilet: <b>' . esc_html( number_format_i18n( (int) $data['summary']['tickets'] ) ) . '</b></div><div>Kişi/Kapasite: <b>' . esc_html( number_format_i18n( (int) $data['summary']['units'] ) ) . '</b></div><div>Ciro: <b>' . esc_html( self::money( (float) $data['summary']['revenue'] ) ) . '</b></div></div>';
        echo '<table><thead><tr>';
        foreach ( $matrix[0] ?? array() as $h ) { echo '<th>' . esc_html( $h ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( array_slice( $matrix, 1 ) as $row ) {
            echo '<tr>';
            foreach ( $row as $v ) { echo '<td>' . esc_html( (string) $v ) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table><script>window.addEventListener("load",function(){setTimeout(function(){window.print();},250);});</script></body></html>';
        exit;
    }

    private static function export_matrix( $data ) {
        $matrix = array();
        $matrix[] = array( 'Sipariş No','Sipariş Tarihi','Müşteri','Telefon','E-posta','Etkinlik','Şehir','İlçe','Salon','Seans','Bilet Türü','Adet','Kişi/Kapasite','Satır Tutarı (TL)','Sipariş Durumu','Ödeme Tarihi','Tickera Bilet Adedi','Check-in' );
        $ticket_cache = array();
        foreach ( (array) $data['rows'] as $row ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $row->order_id ) : null;
            if ( ! $order ) { continue; }
            $oid = (int) $order->get_id();
            if ( ! array_key_exists( $oid, $ticket_cache ) ) { $ticket_cache[ $oid ] = self::ticket_instances_for_order( $oid ); }
            $ts = self::ticket_summary( $ticket_cache[ $oid ] );
            $paid = $order->get_date_paid();
            $created = $paid ?: $order->get_date_created();
            $matrix[] = array(
                $oid,
                $created ? self::date_label( $created ) : '',
                trim( (string) $order->get_formatted_billing_full_name() ),
                (string) $order->get_billing_phone(),
                (string) $order->get_billing_email(),
                (string) $row->title,
                (string) $row->province_name,
                (string) $row->district,
                (string) $row->venue_name,
                self::session_label( $row->start_at ),
                (string) $row->ticket_label,
                (int) $row->quantity,
                (int) $row->units_total,
                (float) $row->line_total,
                wc_get_order_status_name( $order->get_status() ),
                $paid ? self::date_label( $paid ) : '',
                (int) $ts['count'],
                null === $ts['checkin_known'] ? '' : $ts['checked_in'] . '/' . $ts['count'],
            );
        }
        return $matrix;
    }

    private static function export_excel_legacy( $matrix ) {
        nocache_headers();
        header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="madagaskar-musteri-bilet-listesi-' . gmdate( 'Ymd-His' ) . '.xls"' );
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"></head><body><table border="1">';
        foreach ( $matrix as $i => $row ) {
            echo '<tr>';
            foreach ( $row as $v ) { echo ( 0 === $i ? '<th>' : '<td>' ) . esc_html( self::excel_safe_text( (string) $v ) ) . ( 0 === $i ? '</th>' : '</td>' ); }
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    private static function excel_safe_text( $value ) {
        $value = (string) $value;
        if ( preg_match( '/^[=+\-@]/', ltrim( $value ) ) ) { return "'" . $value; }
        return $value;
    }

    private static function xml_text( $value ) {
        return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

    private static function xlsx_col( $n ) {
        $s = '';
        while ( $n > 0 ) { $n--; $s = chr( 65 + ( $n % 26 ) ) . $s; $n = intdiv( $n, 26 ); }
        return $s ?: 'A';
    }

    private static function xlsx_columns( $count ) {
        $widths = array( 12,18,24,18,28,34,16,18,28,22,24,10,14,18,18,18,14,12 );
        $xml = '';
        for ( $i = 1; $i <= $count; $i++ ) {
            $width = $widths[ $i - 1 ] ?? 18;
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
        }
        return $xml;
    }

    private static function render_filters( $f, $lookups ) {
        echo '<form method="get" class="mdg-panel" style="margin-top:18px" data-mdg-customer-filter-form>';
        echo '<input type="hidden" name="page" value="mdg-customers"><div class="mdg-grid-3">';

        echo '<div><label>Dönem</label><select name="range">';
        foreach ( array( 'today'=>'Bugün','yesterday'=>'Dün','7d'=>'Son 7 Gün','30d'=>'Son 30 Gün','all'=>'Tümü','custom'=>'Özel Tarih' ) as $key=>$label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['range'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></div>';
        echo '<div><label>Başlangıç</label><input type="date" name="date_from" value="' . esc_attr( $f['date_from'] ) . '"></div>';
        echo '<div><label>Bitiş</label><input type="date" name="date_to" value="' . esc_attr( $f['date_to'] ) . '"></div>';

        $province_change = "var e=this.form.querySelector('[name=event_id]'),s=this.form.querySelector('[name=session_id]'),t=this.form.querySelector('[name=ticket_type_id]');if(e)e.value='0';if(s)s.value='0';if(t)t.value='0';this.form.submit();";
        echo '<div><label>Şehir</label><select name="province" onchange="' . esc_attr( $province_change ) . '"><option value="">Tüm şehirler</option>';
        foreach ( $lookups['provinces'] as $p ) echo '<option value="' . esc_attr( $p->province_code ) . '"' . selected( $f['province'], $p->province_code, false ) . '>' . esc_html( $p->province_name ) . '</option>';
        echo '</select></div>';

        $event_change = "var s=this.form.querySelector('[name=session_id]'),t=this.form.querySelector('[name=ticket_type_id]');if(s)s.value='0';if(t)t.value='0';this.form.submit();";
        echo '<div><label>Etkinlik</label><select name="event_id" onchange="' . esc_attr( $event_change ) . '"><option value="0">Tüm etkinlikler</option>';
        foreach ( $lookups['events'] as $e ) echo '<option value="' . esc_attr( (int) $e->id ) . '"' . selected( $f['event_id'], (int) $e->id, false ) . '>' . esc_html( $e->title . ' – ' . $e->province_name ) . '</option>';
        echo '</select><p class="description" style="margin:5px 0 0">Şehir seçildiğinde yalnız o şehrin etkinlikleri gösterilir.</p></div>';

        $session_disabled = ! $f['event_id'];
        $session_change = "var t=this.form.querySelector('[name=ticket_type_id]');if(t)t.value='0';this.form.submit();";
        echo '<div><label>Seans</label><select name="session_id" onchange="' . esc_attr( $session_change ) . '"' . disabled( $session_disabled, true, false ) . '><option value="0">' . ( $session_disabled ? 'Önce etkinlik seçiniz' : 'Tüm seanslar' ) . '</option>';
        foreach ( $lookups['sessions'] as $s ) echo '<option value="' . esc_attr( (int) $s->id ) . '"' . selected( $f['session_id'], (int) $s->id, false ) . '>' . esc_html( self::session_label( $s->start_at ) ) . '</option>';
        echo '</select><p class="description" style="margin:5px 0 0">Etkinlik seçildiğinde yalnız o etkinliğin seansları listelenir.</p></div>';

        $type_disabled = ! $f['session_id'];
        echo '<div><label>Bilet Türü</label><select name="ticket_type_id"' . disabled( $type_disabled, true, false ) . '><option value="0">' . ( $type_disabled ? 'Önce seans seçiniz' : 'Tüm bilet türleri' ) . '</option>';
        foreach ( $lookups['types'] as $t ) echo '<option value="' . esc_attr( (int) $t->id ) . '"' . selected( $f['ticket_type_id'], (int) $t->id, false ) . '>' . esc_html( $t->label ) . '</option>';
        echo '</select></div>';

        echo '<div><label>Sipariş Durumu</label><select name="order_status">';
        foreach ( array( 'paid'=>'Ödenmiş satışlar','all'=>'Tüm durumlar','processing'=>'İşleniyor','completed'=>'Tamamlandı','on-hold'=>'Beklemede','pending'=>'Ödeme bekliyor','refunded'=>'İade edildi','cancelled'=>'İptal edildi','failed'=>'Başarısız' ) as $key=>$label ) {
            echo '<option value="' . esc_attr( $key ) . '"' . selected( $f['order_status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></div>';

        echo '<div><label>Sipariş No</label><input type="number" min="1" name="order_id" value="' . ( $f['order_id'] ? esc_attr( $f['order_id'] ) : '' ) . '" placeholder="Örn: 1728"></div>';
        echo '</div>';
        echo '<p style="margin:16px 0 0"><button class="button button-primary">Listeyi Göster</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=mdg-customers' ) ) . '">Filtreleri Temizle</a></p>';
        echo '</form>';
    }

    private static function query_rows( $f, $paginate ) {
        global $wpdb;
        $m = MDG_DB::table( 'order_map' );
        $e = MDG_DB::table( 'events' );
        $s = MDG_DB::table( 'sessions' );
        $t = MDG_DB::table( 'ticket_types' );

        $where = array( '1=1' );
        $args  = array();
        if ( $f['utc_from'] ) { $where[]='COALESCE(m.paid_at,m.created_at) >= %s'; $args[]=$f['utc_from']; }
        if ( $f['utc_to'] ) { $where[]='COALESCE(m.paid_at,m.created_at) < %s'; $args[]=$f['utc_to']; }
        if ( $f['province'] ) { $where[]='e.province_code=%s'; $args[]=$f['province']; }
        if ( $f['event_id'] ) { $where[]='m.event_id=%d'; $args[]=$f['event_id']; }
        if ( $f['session_id'] ) { $where[]='m.session_id=%d'; $args[]=$f['session_id']; }
        if ( $f['ticket_type_id'] ) { $where[]='m.ticket_type_id=%d'; $args[]=$f['ticket_type_id']; }
        if ( $f['order_id'] ) { $where[]='m.order_id=%d'; $args[]=$f['order_id']; }

        if ( 'paid' === $f['order_status'] ) {
            $where[] = 'm.paid_at IS NOT NULL';
            $where[] = "m.order_status NOT IN ('failed','cancelled','refunded','trash')";
        } elseif ( 'all' !== $f['order_status'] ) {
            $where[] = 'm.order_status=%s';
            $args[]  = $f['order_status'];
        }

        $where_sql = implode( ' AND ', $where );
        $prepare = static function( $sql, $extra=array() ) use ( $wpdb, $args ) {
            $all = array_merge( $args, $extra );
            return $all ? $wpdb->prepare( $sql, $all ) : $sql;
        };

        $summary = $wpdb->get_row( $prepare(
            "SELECT COUNT(DISTINCT m.order_id) orders,COALESCE(SUM(m.quantity),0) tickets,COALESCE(SUM(m.units_total),0) units,COALESCE(SUM(m.line_total),0) revenue
             FROM {$m} m JOIN {$e} e ON e.id=m.event_id WHERE {$where_sql}"
        ), ARRAY_A );
        $summary = array_merge( array( 'orders'=>0,'tickets'=>0,'units'=>0,'revenue'=>0 ), (array) $summary );

        $total_rows = (int) $wpdb->get_var( $prepare( "SELECT COUNT(*) FROM {$m} m JOIN {$e} e ON e.id=m.event_id WHERE {$where_sql}" ) );
        $limit = max( 1, min( 5000, (int) $f['per_page'] ) );
        $offset = $paginate ? max( 0, ( (int) $f['paged'] - 1 ) * $limit ) : 0;

        $rows = $wpdb->get_results( $prepare(
            "SELECT m.*,e.title,e.province_name,e.district,e.venue_name,s.start_at,t.label ticket_label,t.code ticket_code,t.price ticket_price
             FROM {$m} m
             JOIN {$e} e ON e.id=m.event_id
             JOIN {$s} s ON s.id=m.session_id
             JOIN {$t} t ON t.id=m.ticket_type_id
             WHERE {$where_sql}
             ORDER BY COALESCE(m.paid_at,m.created_at) DESC,m.order_id DESC,m.order_item_id DESC
             LIMIT %d OFFSET %d",
            array( $limit, $offset )
        ) );

        return array( 'summary'=>$summary, 'rows'=>$rows, 'total_rows'=>$total_rows );
    }

    private static function lookups( $f ) {
        global $wpdb;
        $e = MDG_DB::table( 'events' );
        $s = MDG_DB::table( 'sessions' );
        $t = MDG_DB::table( 'ticket_types' );

        $provinces = $wpdb->get_results( "SELECT DISTINCT province_code,province_name FROM {$e} ORDER BY province_name" );

        if ( ! empty( $f['province'] ) ) {
            $events = $wpdb->get_results( $wpdb->prepare( "SELECT id,title,province_name FROM {$e} WHERE province_code=%s ORDER BY created_at DESC", $f['province'] ) );
        } else {
            $events = $wpdb->get_results( "SELECT id,title,province_name FROM {$e} ORDER BY created_at DESC" );
        }

        $sessions = array();
        if ( ! empty( $f['event_id'] ) ) {
            $sessions = $wpdb->get_results( $wpdb->prepare( "SELECT id,start_at,event_id FROM {$s} WHERE event_id=%d ORDER BY start_at ASC", $f['event_id'] ) );
        }

        if ( ! empty( $f['session_id'] ) ) {
            $types = $wpdb->get_results( $wpdb->prepare( "SELECT id,label FROM {$t} WHERE is_active=1 AND session_id=%d ORDER BY sort_order,label", $f['session_id'] ) );
        } else {
            $types = array();
        }

        return array( 'provinces'=>$provinces, 'events'=>$events, 'sessions'=>$sessions, 'types'=>$types );
    }

    private static function filters() {
        $range = sanitize_key( $_GET['range'] ?? '30d' );
        if ( ! in_array( $range, array( 'today','yesterday','7d','30d','all','custom' ), true ) ) { $range='30d'; }
        $date_from=sanitize_text_field(wp_unslash($_GET['date_from']??''));
        $date_to=sanitize_text_field(wp_unslash($_GET['date_to']??''));
        $tz=wp_timezone(); $now=new DateTimeImmutable('now',$tz); $start=null; $end=null;
        if ('today'===$range){$start=$now->setTime(0,0,0);$end=$start->modify('+1 day');}
        elseif('yesterday'===$range){$end=$now->setTime(0,0,0);$start=$end->modify('-1 day');}
        elseif('7d'===$range){$end=$now->setTime(0,0,0)->modify('+1 day');$start=$now->setTime(0,0,0)->modify('-6 days');}
        elseif('30d'===$range){$end=$now->setTime(0,0,0)->modify('+1 day');$start=$now->setTime(0,0,0)->modify('-29 days');}
        elseif('custom'===$range){
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from)){$start=new DateTimeImmutable($date_from.' 00:00:00',$tz);}
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to)){$end=(new DateTimeImmutable($date_to.' 00:00:00',$tz))->modify('+1 day');}
        }
        if($start&&!$date_from){$date_from=$start->format('Y-m-d');}
        if($end&&!$date_to){$date_to=$end->modify('-1 day')->format('Y-m-d');}
        $utc=new DateTimeZone('UTC');
        $status=sanitize_key($_GET['order_status']??'paid');
        if(!in_array($status,array('paid','all','processing','completed','on-hold','pending','refunded','cancelled','failed'),true)){$status='paid';}
        return array(
            'range'=>$range,'date_from'=>$date_from,'date_to'=>$date_to,
            'utc_from'=>$start?$start->setTimezone($utc)->format('Y-m-d H:i:s'):'',
            'utc_to'=>$end?$end->setTimezone($utc)->format('Y-m-d H:i:s'):'',
            'province'=>sanitize_text_field(wp_unslash($_GET['province']??'')),
            'event_id'=>absint($_GET['event_id']??0),'session_id'=>absint($_GET['session_id']??0),'ticket_type_id'=>absint($_GET['ticket_type_id']??0),
            'order_status'=>$status,'order_id'=>absint($_GET['order_id']??0),
            'paged'=>max(1,absint($_GET['paged']??1)),'per_page'=>self::PER_PAGE,
        );
    }

    private static function ticket_instances_for_order( $order_id ) {
        if ( ! post_type_exists( 'tc_tickets_instances' ) ) { return array(); }
        $keys = array( 'order_id','_order_id','woocommerce_order_id','_woocommerce_order_id','woo_order_id','_woo_order_id' );
        $meta_query = array( 'relation'=>'OR' );
        foreach ( $keys as $key ) $meta_query[] = array( 'key'=>$key, 'value'=>(string)absint($order_id), 'compare'=>'=' );
        $ids = get_posts( array(
            'post_type'=>'tc_tickets_instances','post_status'=>'any','posts_per_page'=>100,'fields'=>'ids','no_found_rows'=>true,
            'meta_query'=>$meta_query,
        ) );
        return array_map( 'absint', (array) $ids );
    }

    private static function ticket_summary( $ticket_ids ) {
        $codes=array(); $checked=0; $known=false;
        foreach ( (array) $ticket_ids as $id ) {
            $code=self::first_meta($id,array('ticket_code','_ticket_code','code','_code'));
            if($code!==''){$codes[]=(string)$code;}
            $state=self::checkin_state($id);
            if(null!==$state){$known=true;if($state){$checked++;}}
        }
        return array('count'=>count((array)$ticket_ids),'codes'=>array_values(array_unique($codes)),'checked_in'=>$checked,'checkin_known'=>$known?true:null);
    }

    private static function ticket_owner_names( $ticket_ids ) {
        $names=array();
        foreach((array)$ticket_ids as $id){
            $name=self::first_meta($id,array('owner_name','_owner_name','ticket_owner_name','_ticket_owner_name','owner_full_name','_owner_full_name'));
            if(''===$name){
                $first=self::first_meta($id,array('owner_first_name','_owner_first_name','first_name','_first_name'));
                $last=self::first_meta($id,array('owner_last_name','_owner_last_name','last_name','_last_name'));
                $name=trim((string)$first.' '.(string)$last);
            }
            if($name!==''){$names[]=(string)$name;}
        }
        return array_values(array_unique($names));
    }

    private static function first_meta( $post_id, $keys ) {
        foreach($keys as $key){$v=get_post_meta($post_id,$key,true);if(is_scalar($v)&&''!==(string)$v){return (string)$v;}}
        return '';
    }

    private static function checkin_state( $ticket_id ) {
        foreach(array('checked_in','_checked_in','checkin_count','_checkin_count','checkin','_checkin','check_in','_check_in','checkins','_checkins') as $key){
            if(!metadata_exists('post',$ticket_id,$key)){continue;}
            $v=get_post_meta($ticket_id,$key,true);
            if(is_array($v)){return !empty($v);}
            if(is_numeric($v)){return (float)$v>0;}
            $s=strtolower(trim((string)$v));
            if(in_array($s,array('1','yes','true','checked','checked_in','done'),true)){return true;}
            if(in_array($s,array('0','no','false','unchecked',''),true)){return false;}
            if(is_serialized($v)){$u=maybe_unserialize($v);return is_array($u)?!empty($u):(bool)$u;}
            return !empty($s);
        }
        return null;
    }

    private static function ticket_link_from_order( $order ) {
        foreach(array('_mdg_ticket_link','mdg_ticket_link','_bilet_linki','bilet_linki','_ticket_link','ticket_link') as $key){
            $v=(string)$order->get_meta($key,true);
            if($v && wp_http_validate_url($v)){return $v;}
        }
        return '';
    }

    private static function export_url( $action, $nonce_action, $f ) {
        $args = array( 'action'=>$action );
        foreach ( array( 'range','date_from','date_to','province','event_id','session_id','ticket_type_id','order_status','order_id' ) as $key ) {
            if ( isset( $f[$key] ) && '' !== $f[$key] && 0 !== $f[$key] ) { $args[$key] = $f[$key]; }
        }
        $url = add_query_arg( $args, admin_url( 'admin-post.php' ) );
        return wp_nonce_url( $url, $nonce_action, 'mdg_nonce' );
    }

    private static function csv_url( $f ) {
        return self::export_url( 'mdg_customer_tickets_csv', 'mdg_customer_tickets_csv', $f );
    }

    private static function excel_url( $f ) {
        return self::export_url( 'mdg_customer_tickets_excel', 'mdg_customer_tickets_excel', $f );
    }

    private static function pdf_url( $f ) {
        return self::export_url( 'mdg_customer_tickets_pdf', 'mdg_customer_tickets_pdf', $f );
    }

    private static function pagination( $f, $total_rows ) {
        $pages=(int)ceil($total_rows/max(1,(int)$f['per_page']));
        if($pages<=1){return;}
        $base=remove_query_arg('paged');
        echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links(array('base'=>add_query_arg('paged','%#%',$base),'format'=>'','current'=>(int)$f['paged'],'total'=>$pages,'prev_text'=>'‹','next_text'=>'›')) ) . '</div></div>';
    }

    private static function session_label( $utc_value ) {
        if(!$utc_value){return '—';}
        if(class_exists('MDG_Sessions')){list($d,$t)=MDG_Sessions::local_parts((string)$utc_value);if($d&&$t){$ts=strtotime($d.' 12:00:00');return($ts?wp_date('d.m.Y',$ts):$d).' '.$t;}}
        return (string)$utc_value;
    }

    private static function date_label( $date ) {
        if($date instanceof WC_DateTime){try{$date->setTimezone(wp_timezone());}catch(Throwable $e){} return $date->date_i18n('d.m.Y H:i');}
        if($date instanceof DateTimeInterface){return wp_date('d.m.Y H:i',$date->getTimestamp(),wp_timezone());}
        return '—';
    }

    private static function status_class( $status ) {
        if(in_array($status,array('processing','completed'),true)){return 'is-active';}
        if(in_array($status,array('failed','cancelled','refunded'),true)){return 'is-warning';}
        return '';
    }

    private static function card( $label, $value ) { echo '<div class="mdg-card"><div class="mdg-card-value">' . esc_html($value) . '</div><div>' . esc_html($label) . '</div></div>'; }
    private static function money( $amount ) { return function_exists('wc_price')?wp_strip_all_tags(wc_price((float)$amount,array('currency'=>'TRY'))):number_format_i18n((float)$amount,2).' ₺'; }
}
