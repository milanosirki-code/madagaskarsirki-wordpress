<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC-linked MDG events: Excel is content-only.
 * Canonical program/venue/date/session/capacity/price data always comes from MMC.
 */
final class MMC_Publish_Content_Excel {
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'render_panel' ), 20 );
        add_action( 'admin_post_mmc_download_publish_content_template', array( $this, 'download' ) );
        add_action( 'admin_post_mmc_import_publish_content', array( $this, 'import' ) );
    }

    public function render_panel() {
        if ( ! is_admin() || empty( $_GET['page'] ) || 'mdg-publish' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'mmc_manage_programs' ) ) { return; }
        if ( ! class_exists( 'MMC_MDG_Bridge_Service' ) ) { return; }

        $program_id = absint( $_GET['program_id'] ?? 0 );
        if ( ! $program_id ) { return; }
        $preview = MMC_MDG_Bridge_Service::publish_preview( $program_id );
        if ( ! $preview || empty( $preview['bridge'] ) ) { return; }
        $mdg_event = MMC_MDG_Bridge_Service::get_mdg_event( (int) $preview['bridge']->mdg_event_id );
        if ( ! $mdg_event ) { return; }

        if ( ! empty( $_GET['mmc_content_imported'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Yayın içeriği Excel’den güncellendi.</strong> MMC program verileri değiştirilmedi.</p></div>';
        }

        echo '<div class="notice notice-info" style="border-left-color:#3858e9;padding:0 14px 14px;margin-top:14px">';
        echo '<h2 style="margin:14px 0 6px">Yayın İçeriği Excel — MMC Uyumlu</h2>';
        echo '<p><strong>Program verilerini Excel’e ikinci kez yazmayın.</strong> Program kodu, il/ilçe, kesin salon, tarih, seanslar, ortak kapasite ve bilet fiyatları MMC’den gelir. Excel yalnız yayın metni, SSS, SEO ve isteğe bağlı video URL’sini günceller.</p>';
        echo '<p class="description">Aşağıdaki eski tam etkinlik Excel’i legacy akıştır. MMC’ye bağlı programlarda bu yeni şablonu kullanın.</p>';

        $download = wp_nonce_url(
            add_query_arg( array(
                'action' => 'mmc_download_publish_content_template',
                'program_id' => $program_id,
            ), admin_url( 'admin-post.php' ) ),
            'mmc_download_publish_content_template_' . $program_id
        );
        echo '<p><a class="button button-secondary" href="' . esc_url( $download ) . '">MMC Uyumlu Excel Şablonunu İndir</a></p>';

        if ( 'draft' !== (string) $mdg_event->status ) {
            echo '<p><strong>Canlı etkinlikte Excel ile otomatik içerik güncellemesi kapalıdır.</strong></p></div>';
            return;
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="mmc_import_publish_content">';
        echo '<input type="hidden" name="program_id" value="' . esc_attr( $program_id ) . '">';
        wp_nonce_field( 'mmc_import_publish_content_' . $program_id, 'mmc_nonce' );
        echo '<input type="file" name="content_file" accept=".xlsx" required> ';
        echo '<button class="button button-primary">Yayın İçeriğini Excel’den Uygula</button>';
        echo '<p class="description">Boş hücre mevcut içeriği silmez. Aktif ve dolu SSS satırları varsa SSS listesi güncellenir.</p>';
        echo '</form></div>';
    }

    public function download() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'mmc_manage_programs' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $program_id = absint( $_GET['program_id'] ?? 0 );
        check_admin_referer( 'mmc_download_publish_content_template_' . $program_id );
        $preview = MMC_MDG_Bridge_Service::publish_preview( $program_id );
        if ( ! $preview || empty( $preview['program'] ) ) { wp_die( 'Program bulunamadı.' ); }
        if ( ! class_exists( 'ZipArchive' ) ) { wp_die( 'Excel şablonu için ZipArchive gerekli.' ); }

        $vars = $this->variables( $preview );
        $program = $preview['program'];
        $venue = $preview['venue'];
        $sheets = array(
            'KULLANIM' => array(
                array( 'MADAGASKAR SİRKİ — MMC YAYIN İÇERİĞİ EXCEL ŞABLONU', '', '' ),
                array( 'Program', (string) $program->program_code, 'MMC’den gelir; Excel değiştirmez.' ),
                array( 'Yer', $vars['{{YER}}'], 'İl/ilçe MMC’den gelir.' ),
                array( 'Tarih', $vars['{{TARIH}}'], 'Tarih MMC’den gelir.' ),
                array( 'Salon', $venue ? (string) $venue->venue_name : '', 'Kesin salon MMC’den gelir.' ),
                array( 'Seanslar', $vars['{{SEANSLAR}}'], 'Seans/kapasite MMC’den gelir.' ),
                array( 'Fiyatlar', 'Çocuk ' . $vars['{{COCUK_FIYAT}}'] . ' TL · Yetişkin ' . $vars['{{YETISKIN_FIYAT}}'] . ' TL · Aile 2+2 ' . $vars['{{AILE_FIYATI}}'] . ' TL', 'Fiyatlar MMC’den gelir.' ),
                array( 'KURAL', 'YAYIN_ICERIGI ve SSS sayfalarını düzenleyin.', 'Program/salon/tarih/seans/fiyat tekrar yazılmaz.' ),
                array( 'KURAL', 'Değişkenleri {{...}} biçiminde bırakabilirsiniz.', 'İçe aktarımda MMC verisiyle doldurulur.' ),
                array( 'KURAL', 'Boş içerik hücresi mevcut veriyi silmez.', 'Yalnız bağlı MDG taslağının içeriği güncellenir.' ),
            ),
            'YAYIN_ICERIGI' => array(
                array( 'alan', 'deger', 'aciklama' ),
                array( 'short_description', 'Madagaskar Sirki {{YER}}’de! {{TARIH_UZUN}} günü {{SALON}}’nda {{SEANSLAR}} seanslarıyla. Uluslararası sanatçılar • Hayvansız modern sirk • Ailece eğlence.', 'Kısa açıklama' ),
                array( 'long_description', '{{ETKINLIK_ADI}}, {{TARIH_UZUN}} günü {{SALON}}’nda seyircisiyle buluşuyor. Uluslararası sirk sanatçılarını aynı sahnede buluşturan gösteride akrobasi, denge, jonglörlük, hula hoop, palyaço ve görsel sahne performansları yer alır. Hayvan gösterisi içermeyen modern sirk programı yaklaşık 60 dakikalık aile eğlencesidir. Tarih: {{TARIH_UZUN}}. Seanslar: {{SEANSLAR}}. Salon: {{SALON}}. Adres: {{ADRES}}. Biletler: {{BILET_OZETI}}. Online bilet: {{BILET_LINKI}}. Bilgi hattı: {{BILGI_HATTI}}.', 'Uzun açıklama' ),
                array( 'rules', 'Oturma: {{OTURMA}}. Salon kapıları gösteriden yaklaşık {{KAPI_DK}} dakika önce açılır. Gösteri süresi yaklaşık 60 dakikadır. 0–2 yaş ücretsizdir; 3–12 yaş çocuk, 13 yaş ve üzeri yetişkin bileti kullanır. Çocuklar yetişkin eşliğinde katılır. Aile Paketi 2 yetişkin + 2 çocuktan oluşur. Biletler: {{BILET_OZETI}}. Salon: {{SALON}} — {{ADRES}}. Bilgi hattı: {{BILGI_HATTI}}.', 'Kurallar' ),
                array( 'seo_title', 'Madagaskar Sirki {{YER}} Biletleri | {{TARIH_UZUN}}', 'SEO başlığı' ),
                array( 'seo_description', 'Madagaskar Sirki {{YER}}: {{TARIH_UZUN}}, {{SALON}}. Seanslar: {{SEANSLAR}}. Biletler: {{BILET_OZETI}}.', 'SEO açıklaması' ),
                array( 'video_url', '', 'İsteğe bağlı video URL’si' ),
            ),
            'SSS' => array(
                array( 'sira', 'soru', 'cevap', 'aktif' ),
                array( '1', 'Gösteri ne zaman ve nerede?', '{{TARIH_UZUN}} tarihinde {{SALON}}, {{ADRES}}. Seanslar: {{SEANSLAR}}.', 'Evet' ),
                array( '2', 'Bilet fiyatları nedir?', '{{BILET_OZETI}}. 0–2 yaş ücretsizdir.', 'Evet' ),
                array( '3', 'Aile paketi nedir?', 'Aile Paketi 2 yetişkin + 2 çocuk için geçerlidir. Güncel fiyat: {{AILE_FIYATI}} TL.', 'Evet' ),
                array( '4', 'Oturma düzeni nasıl?', '{{OTURMA}}. Kapılar gösteriden yaklaşık {{KAPI_DK}} dakika önce açılır.', 'Evet' ),
                array( '5', 'Çocuklar tek başına katılabilir mi?', 'Hayır. Çocuklar etkinliğe yetişkin eşliğinde katılır. Bilgi hattı: {{BILGI_HATTI}}.', 'Evet' ),
                array( '6', '', '', 'Hayır' ),
            ),
            'DEGISKENLER' => array(
                array( 'degisken', 'kaynak', 'aciklama' ),
                array( '{{PROGRAM_KODU}}', 'MMC Program', 'Program kodu' ),
                array( '{{IL}}', 'MMC Program', 'İl' ),
                array( '{{ILCE}}', 'MMC Program', 'İlçe' ),
                array( '{{YER}}', 'MMC Program', 'İl / ilçe gösterimi' ),
                array( '{{TARIH}}', 'MMC Etkinlik', 'Etkinlik tarihi' ),
                array( '{{TARIH_UZUN}}', 'MMC Etkinlik', 'Uzun Türkçe tarih' ),
                array( '{{SALON}}', 'Kesin Salon', 'Salon adı' ),
                array( '{{ADRES}}', 'Kesin Salon', 'Salon açık adresi' ),
                array( '{{SEANSLAR}}', 'MMC Seans', 'Seans saatleri' ),
                array( '{{BILET_OZETI}}', 'MMC Bilet', 'Aktif bilet türleri ve fiyat özeti' ),
                array( '{{COCUK_FIYAT}}', 'MMC Bilet', 'Çocuk kendi site fiyatı' ),
                array( '{{YETISKIN_FIYAT}}', 'MMC Bilet', 'Yetişkin kendi site fiyatı' ),
                array( '{{AILE_FIYATI}}', 'MMC Bilet', 'Aile Paketi 2+2 fiyatı' ),
                array( '{{OTURMA}}', 'MMC Etkinlik', 'Oturma düzeni' ),
                array( '{{KAPI_DK}}', 'MMC Etkinlik', 'Kapı açılış dakikası' ),
                array( '{{BILGI_HATTI}}', 'Sistem', 'Dijital bilgi hattı' ),
                array( '{{BILET_LINKI}}', 'Sistem', 'Resmî merkezî bilet sayfası' ),
                array( '{{ETKINLIK_ADI}}', 'MMC Program', 'Kamuya açık etkinlik adı' ),
            ),
        );

        $tmp = wp_tempnam( 'mmc-yayin-icerigi.xlsx' );
        if ( ! $tmp ) { wp_die( 'Geçici dosya oluşturulamadı.' ); }
        $result = $this->write_xlsx( $tmp, $sheets );
        if ( is_wp_error( $result ) ) { @unlink( $tmp ); wp_die( esc_html( $result->get_error_message() ) ); }

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="Madagaskar_Yayin_Icerigi_' . sanitize_file_name( (string) $program->program_code ) . '.xlsx"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        readfile( $tmp );
        @unlink( $tmp );
        exit;
    }

    public function import() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'mmc_manage_programs' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $program_id = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_import_publish_content_' . $program_id, 'mmc_nonce' );
        if ( empty( $_FILES['content_file'] ) || ! is_array( $_FILES['content_file'] ) ) { $this->fail( $program_id, 'Excel dosyasını seçin.' ); }
        $file = $_FILES['content_file'];
        if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { $this->fail( $program_id, 'Excel yüklemesi doğrulanamadı.' ); }
        if ( ! empty( $file['size'] ) && (int) $file['size'] > 5242880 ) { $this->fail( $program_id, 'Excel dosyası 5 MB sınırını aşıyor.' ); }
        if ( 'xlsx' !== strtolower( pathinfo( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) ) ) { $this->fail( $program_id, 'Yalnızca .xlsx dosyası kabul edilir.' ); }

        $preview = MMC_MDG_Bridge_Service::publish_preview( $program_id );
        if ( ! $preview || empty( $preview['bridge'] ) ) { $this->fail( $program_id, 'Önce MMC programını MDG taslağına bağlayın.' ); }
        $event = MMC_MDG_Bridge_Service::get_mdg_event( (int) $preview['bridge']->mdg_event_id );
        if ( ! $event || 'draft' !== (string) $event->status ) { $this->fail( $program_id, 'Yayın içeriği yalnız MDG taslağına otomatik uygulanabilir.' ); }

        $book = $this->read_xlsx( $file['tmp_name'] );
        if ( is_wp_error( $book ) ) { $this->fail( $program_id, $book->get_error_message() ); }
        if ( empty( $book['YAYIN_ICERIGI'] ) ) { $this->fail( $program_id, 'YAYIN_ICERIGI sayfası bulunamadı.' ); }

        $vars = $this->variables( $preview );
        $allowed = array( 'short_description', 'long_description', 'rules', 'seo_title', 'seo_description', 'video_url' );
        $fields = array();
        foreach ( array_slice( $book['YAYIN_ICERIGI'], 1 ) as $row ) {
            $key = sanitize_key( (string) ( $row[0] ?? '' ) );
            $value = trim( (string) ( $row[1] ?? '' ) );
            if ( ! in_array( $key, $allowed, true ) || '' === $value ) { continue; }
            $value = strtr( $value, $vars );
            if ( 'video_url' === $key ) { $fields[ $key ] = esc_url_raw( $value ); }
            elseif ( in_array( $key, array( 'long_description', 'rules', 'seo_description' ), true ) ) { $fields[ $key ] = sanitize_textarea_field( $value ); }
            else { $fields[ $key ] = sanitize_text_field( $value ); }
        }

        $faqs = array();
        foreach ( (array) ( $book['SSS'] ?? array() ) as $i => $row ) {
            if ( 0 === $i ) { continue; }
            $question = trim( (string) ( $row[1] ?? '' ) );
            $answer = trim( (string) ( $row[2] ?? '' ) );
            if ( ! $this->truthy( $row[3] ?? '' ) || '' === $question || '' === $answer ) { continue; }
            $faqs[] = array(
                'question' => sanitize_text_field( strtr( $question, $vars ) ),
                'answer' => sanitize_textarea_field( strtr( $answer, $vars ) ),
            );
            if ( count( $faqs ) >= 30 ) { break; }
        }
        if ( $faqs ) { $fields['faq_json'] = wp_json_encode( $faqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
        if ( ! $fields ) { $this->fail( $program_id, 'Excel’de uygulanabilir dolu yayın içeriği bulunamadı.' ); }

        global $wpdb;
        $fields['updated_at'] = MDG_DB::now();
        $ok = $wpdb->update( MDG_DB::table( 'events' ), $fields, array( 'id' => (int) $event->id, 'status' => 'draft' ) );
        if ( false === $ok ) { $this->fail( $program_id, 'Yayın içeriği MDG taslağına yazılamadı.' ); }

        MMC_Program_Service::add_log(
            $program_id, 'mdg_publish_content_imported', 'mdg_event', (int) $event->id, null,
            array( 'fields' => array_keys( $fields ), 'faq_count' => count( $faqs ) ),
            'Yayın içeriği Excel’den MDG taslağına uygulandı; program verileri değiştirilmedi.'
        );

        wp_safe_redirect( add_query_arg( array(
            'page' => 'mdg-publish',
            'edit' => (int) $event->id,
            'program_id' => $program_id,
            'mmc_content_imported' => 1,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function variables( $preview ) {
        $program = $preview['program'];
        $event = $preview['event'];
        $venue = $preview['venue'];
        $times = array();
        foreach ( (array) $preview['sessions'] as $session ) { $times[] = mysql2date( 'H:i', $session->session_time ); }
        $prices = array( 'child' => '', 'adult' => '', 'family_2_2' => '' );
        $ticket_summary = array();
        foreach ( (array) $preview['tickets'] as $ticket ) {
            if ( ! (int) ( $ticket->is_active ?? 0 ) ) { continue; }
            $code = (string) $ticket->ticket_code;
            $price = number_format_i18n( (float) $ticket->price, 2 );
            if ( isset( $prices[ $code ] ) ) { $prices[ $code ] = $price; }
            $ticket_summary[] = sanitize_text_field( (string) $ticket->ticket_name ) . ' ' . $price . ' TL';
        }
        $district = trim( (string) $program->district_name );
        $place = (string) $program->province_name . ( $district && 'merkez' !== strtolower( remove_accents( $district ) ) ? ' / ' . $district : '' );
        $date_short = $event && $event->event_date ? mysql2date( 'd.m.Y', $event->event_date ) : '';
        $date_long = $event && $event->event_date ? date_i18n( 'j F Y l', strtotime( $event->event_date . ' 12:00:00' ) ) : '';
        $event_name = 'Madagaskar Sirki — ' . (string) $program->province_name;
        if ( $district && 'merkez' !== strtolower( remove_accents( $district ) ) ) { $event_name .= ' / ' . $district; }
        return array(
            '{{PROGRAM_KODU}}' => (string) $program->program_code,
            '{{IL}}' => (string) $program->province_name,
            '{{ILCE}}' => $district,
            '{{YER}}' => $place,
            '{{SALON}}' => $venue ? (string) $venue->venue_name : '',
            '{{ADRES}}' => $venue ? (string) $venue->address : '',
            '{{TARIH}}' => $date_short,
            '{{TARIH_UZUN}}' => $date_long,
            '{{SEANSLAR}}' => implode( ', ', $times ),
            '{{BILET_OZETI}}' => implode( '; ', $ticket_summary ),
            '{{COCUK_FIYAT}}' => $prices['child'],
            '{{YETISKIN_FIYAT}}' => $prices['adult'],
            '{{AILE_FIYATI}}' => $prices['family_2_2'],
            '{{OTURMA}}' => 'numbered' === (string) ( $event->seating_mode ?? '' ) ? 'Numaralı oturma' : 'Numarasız / serbest oturma',
            '{{KAPI_DK}}' => (string) absint( $event->door_open_minutes ?? 30 ),
            '{{BILGI_HATTI}}' => '0312 911 37 10',
            '{{BILET_LINKI}}' => 'https://madagaskarsirki.com/bilet-al/',
            '{{ETKINLIK_ADI}}' => $event_name,
        );
    }

    private function truthy( $value ) {
        return in_array( strtolower( remove_accents( trim( (string) $value ) ) ), array( '1', 'evet', 'yes', 'aktif', 'true', 'x' ), true );
    }

    private function read_xlsx( $path ) {
        if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'mmc_xlsx_zip', 'Excel okumak için ZipArchive gerekli.' ); }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path ) ) { return new WP_Error( 'mmc_xlsx_open', 'Excel dosyası açılamadı.' ); }

        $shared = array();
        $raw = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( false !== $raw ) {
            $xml = @simplexml_load_string( $raw );
            if ( $xml ) {
                $xml->registerXPathNamespace( 'm', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
                foreach ( $xml->xpath( '//m:si' ) as $si ) {
                    $parts = array();
                    foreach ( $si->xpath( './/m:t' ) as $t ) { $parts[] = (string) $t; }
                    $shared[] = implode( '', $parts );
                }
            }
        }

        $workbook_raw = $zip->getFromName( 'xl/workbook.xml' );
        $rels_raw = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
        if ( false === $workbook_raw || false === $rels_raw ) { $zip->close(); return new WP_Error( 'mmc_xlsx_structure', 'Excel çalışma kitabı yapısı okunamadı.' ); }
        $workbook = @simplexml_load_string( $workbook_raw );
        $rels = @simplexml_load_string( $rels_raw );
        if ( ! $workbook || ! $rels ) { $zip->close(); return new WP_Error( 'mmc_xlsx_structure', 'Excel çalışma kitabı yapısı okunamadı.' ); }

        $workbook->registerXPathNamespace( 'm', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
        $rels->registerXPathNamespace( 'r', 'http://schemas.openxmlformats.org/package/2006/relationships' );
        $targets = array();
        foreach ( $rels->xpath( '//r:Relationship' ) as $rel ) { $targets[ (string) $rel['Id'] ] = (string) $rel['Target']; }

        $out = array();
        foreach ( $workbook->xpath( '//m:sheets/m:sheet' ) as $sheet ) {
            $attrs = $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
            $rid = (string) $attrs['id'];
            if ( ! isset( $targets[ $rid ] ) ) { continue; }
            $target = ltrim( $targets[ $rid ], '/' );
            if ( 0 !== strpos( $target, 'xl/' ) ) { $target = 'xl/' . $target; }
            $sheet_raw = $zip->getFromName( $target );
            if ( false === $sheet_raw ) { continue; }
            $sx = @simplexml_load_string( $sheet_raw );
            if ( ! $sx ) { continue; }
            $sx->registerXPathNamespace( 'm', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
            $rows = array();
            foreach ( $sx->xpath( '//m:sheetData/m:row' ) as $row ) {
                $values = array();
                foreach ( $row->xpath( './m:c' ) as $cell ) {
                    preg_match( '/([A-Z]+)(\d+)/', (string) $cell['r'], $match );
                    $col = $this->col_index( $match[1] ?? 'A' );
                    $type = (string) $cell['t'];
                    if ( 'inlineStr' === $type ) {
                        $parts = array();
                        foreach ( $cell->xpath( './/m:t' ) as $t ) { $parts[] = (string) $t; }
                        $value = implode( '', $parts );
                    } else {
                        $v = $cell->xpath( './m:v' );
                        $rawv = $v ? (string) $v[0] : '';
                        $value = 's' === $type && isset( $shared[ (int) $rawv ] ) ? $shared[ (int) $rawv ] : $rawv;
                    }
                    $values[ $col ] = $value;
                }
                if ( $values ) {
                    ksort( $values );
                    $dense = array_fill( 0, max( array_keys( $values ) ) + 1, '' );
                    foreach ( $values as $key => $value ) { $dense[ $key ] = $value; }
                    $rows[] = $dense;
                }
            }
            $out[ (string) $sheet['name'] ] = $rows;
        }
        $zip->close();
        return $out;
    }

    private function write_xlsx( $path, $sheets ) {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return new WP_Error( 'mmc_xlsx_create', 'Excel dosyası oluşturulamadı.' ); }
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $overrides = ''; $sheet_nodes = ''; $rels = ''; $i = 1;
        foreach ( $sheets as $name => $rows ) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheet_nodes .= '<sheet name="' . $this->xml( $name ) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            $zip->addFromString( 'xl/worksheets/sheet' . $i . '.xml', $this->sheet_xml( $rows ) );
            $i++;
        }
        $zip->addFromString( '[Content_Types].xml', $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $overrides . '</Types>' );
        $zip->addFromString( '_rels/.rels', $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
        $zip->addFromString( 'xl/workbook.xml', $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheet_nodes . '</sheets></workbook>' );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
        $zip->addFromString( 'xl/styles.xml', $head . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF3858E9"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>' );
        $zip->close();
        return true;
    }

    private function sheet_xml( $rows ) {
        $body = '';
        foreach ( array_values( $rows ) as $row_index => $row ) {
            $cells = '';
            foreach ( array_values( $row ) as $column_index => $value ) {
                $ref = $this->col_letters( $column_index + 1 ) . ( $row_index + 1 );
                $style = 0 === $row_index ? ' s="1"' : '';
                $cells .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $this->xml( (string) $value ) . '</t></is></c>';
            }
            $body .= '<row r="' . ( $row_index + 1 ) . '">' . $cells . '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="70" customWidth="1"/><col min="3" max="4" width="42" customWidth="1"/></cols><sheetData>' . $body . '</sheetData></worksheet>';
    }

    private function col_index( $letters ) {
        $n = 0;
        foreach ( str_split( strtoupper( $letters ) ) as $char ) { $n = $n * 26 + ( ord( $char ) - 64 ); }
        return max( 0, $n - 1 );
    }

    private function col_letters( $n ) {
        $s = '';
        while ( $n > 0 ) { $n--; $s = chr( 65 + ( $n % 26 ) ) . $s; $n = intdiv( $n, 26 ); }
        return $s;
    }

    private function xml( $value ) {
        return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
    }

    private function fail( $program_id, $message ) {
        wp_safe_redirect( add_query_arg( array(
            'page' => 'mdg-publish',
            'program_id' => absint( $program_id ),
            'mmc_mdg_error' => (string) $message,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
