<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Okulun kayıtlı web sitesinden öğrenci sayısı için doğrulanabilir aday bulur.
 *
 * Güvenlik / veri ilkesi:
 * - Yalnız okul ana kaydındaki kayıtlı web_adresi taranır.
 * - wp_safe_remote_get kullanılır; özel/yerel ağ hedefleri kabul edilmez.
 * - Aynı host üzerindeki en fazla 4 ilgili sayfa okunur.
 * - Bulunan sayı otomatik kaydedilmez. Yönetici açıkça "Bu Sayıyı Onayla" der.
 * - Hiçbir aday bulunamazsa tahmin yapılmaz.
 */
class Mad_Okul_Student_Research {
    const MAX_PAGES = 4;
    const MAX_BODY_BYTES = 700000;
    const TRANSIENT_TTL = 30 * MINUTE_IN_SECONDS;

    public static function hooks() {
        add_action( 'admin_post_mad_okul_student_research', array( __CLASS__, 'handle_research' ) );
        add_action( 'admin_post_mad_okul_student_candidate_accept', array( __CLASS__, 'handle_accept' ) );
    }

    public static function transient_key( $school_id, $user_id = 0 ) {
        $user_id = $user_id ?: get_current_user_id();
        return 'mad_okul_student_research_' . absint( $user_id ) . '_' . absint( $school_id );
    }

    public static function get_result( $school_id ) {
        $value = get_transient( self::transient_key( $school_id ) );
        return is_array( $value ) ? $value : null;
    }

    public static function handle_research() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetkisiz işlem' ); }

        $school_id = absint( $_POST['id'] ?? 0 );
        check_admin_referer( 'mad_okul_student_research_' . $school_id );

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . mad_okul_table() . ' WHERE id=%d LIMIT 1',
            $school_id
        ) );
        if ( ! $row ) { wp_die( 'Okul bulunamadı.' ); }

        $website = esc_url_raw( (string) ( $row->web_adresi ?? '' ) );
        if ( ! $website || ! self::is_safe_public_url( $website ) ) {
            self::redirect_back( $row, 'Önce okulun geçerli web adresini kaydedin.', 'error' );
        }

        $result = self::research_website( $website );
        $result['school_id'] = $school_id;
        $result['school_name'] = (string) $row->kurum_adi;
        $result['website'] = $website;
        $result['researched_at'] = current_time( 'mysql' );

        set_transient(
            self::transient_key( $school_id ),
            $result,
            self::TRANSIENT_TTL
        );

        if ( empty( $result['candidate'] ) ) {
            self::redirect_back(
                $row,
                'Web sitesinde güvenilir bir öğrenci sayısı adayı bulunamadı. Tahmin yapılmadı.',
                'warning',
                array( 'research_school_id'=>$school_id )
            );
        }

        self::redirect_back(
            $row,
            'Web sitesinde öğrenci sayısı adayı bulundu. Kaydetmeden önce kaynak metni kontrol edin.',
            'success',
            array( 'research_school_id'=>$school_id )
        );
    }

    public static function handle_accept() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetkisiz işlem' ); }

        $school_id = absint( $_POST['id'] ?? 0 );
        check_admin_referer( 'mad_okul_student_candidate_accept_' . $school_id );

        $result = self::get_result( $school_id );
        if ( ! $result || empty( $result['candidate'] ) || empty( $result['source_url'] ) ) {
            wp_die( 'Araştırma adayı bulunamadı veya süresi doldu. Yeniden tarayın.' );
        }

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . mad_okul_table() . ' WHERE id=%d LIMIT 1',
            $school_id
        ) );
        if ( ! $row ) { wp_die( 'Okul bulunamadı.' ); }

        $count = absint( $result['candidate'] );
        if ( $count < 20 || $count > 15000 ) {
            wp_die( 'Aday öğrenci sayısı güvenli aralığın dışında.' );
        }

        $wpdb->update(
            mad_okul_table(),
            array(
                'student_count'            => $count,
                'ogrenci_sayisi'           => $count,
                'ogrenci_sayi_durumu'      => 'tam',
                'ogrenci_kaynak_turu'      => 'Okul web sitesi',
                'ogrenci_kaynak_url'       => esc_url_raw( $result['source_url'] ),
                'ogrenci_dogrulama_tarihi' => current_time( 'mysql' ),
                'updated_at'               => current_time( 'mysql' ),
            ),
            array( 'id'=>$school_id ),
            array( '%d','%s','%s','%s','%s','%s' ),
            array( '%d' )
        );

        delete_transient( self::transient_key( $school_id ) );
        self::redirect_back( $row, 'Öğrenci sayısı ve kaynak bilgisi onaylanarak kaydedildi.', 'success' );
    }

    public static function research_website( $website ) {
        $pages = self::candidate_pages( $website );
        $best = null;
        $scanned = array();
        $errors = array();

        for ( $page_index = 0; $page_index < self::MAX_PAGES && $page_index < count( $pages ); $page_index++ ) {
            $url = $pages[ $page_index ];
            $response = wp_safe_remote_get(
                $url,
                array(
                    'timeout'             => 8,
                    'redirection'         => 3,
                    'limit_response_size' => self::MAX_BODY_BYTES,
                    'user-agent'          => 'MadagaskarOkulTanitim/1.8 (+student-count-verification)',
                    'headers'             => array( 'Accept'=>'text/html,application/xhtml+xml' ),
                )
            );

            if ( is_wp_error( $response ) ) {
                $errors[] = sanitize_text_field( $response->get_error_message() );
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = (string) wp_remote_retrieve_body( $response );
            $scanned[] = esc_url_raw( $url );

            if ( $code < 200 || $code >= 400 || '' === $body ) { continue; }

            $candidate = self::extract_candidate( $body, $url );
            if ( $candidate && ( ! $best || $candidate['score'] > $best['score'] ) ) {
                $best = $candidate;
            }

            // İlk sayfanın içinden kurumsal/hakkımızda benzeri güvenli iç sayfaları bul.
            if ( 0 === $page_index ) {
                foreach ( self::discover_internal_pages( $body, $url ) as $discovered ) {
                    if ( count( $pages ) >= self::MAX_PAGES ) { break; }
                    if ( ! in_array( $discovered, $pages, true ) ) { $pages[] = $discovered; }
                }
            }
        }

        if ( ! $best ) {
            return array(
                'candidate'   => null,
                'source_url'  => '',
                'excerpt'     => '',
                'score'       => 0,
                'scanned_urls'=> $scanned,
                'errors'      => array_slice( array_unique( $errors ), 0, 3 ),
            );
        }

        $best['scanned_urls'] = $scanned;
        $best['errors'] = array_slice( array_unique( $errors ), 0, 3 );
        return $best;
    }

    private static function candidate_pages( $website ) {
        $website = esc_url_raw( $website );
        return $website ? array( $website ) : array();
    }

    private static function discover_internal_pages( $html, $base_url ) {
        if ( ! class_exists( 'DOMDocument' ) ) { return array(); }

        $base_host = strtolower( (string) wp_parse_url( $base_url, PHP_URL_HOST ) );
        if ( ! $base_host ) { return array(); }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();

        $ranked = array();
        foreach ( $dom->getElementsByTagName( 'a' ) as $link ) {
            $href = trim( (string) $link->getAttribute( 'href' ) );
            if ( '' === $href || '#' === $href || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) { continue; }

            $label = self::norm_text( $link->textContent . ' ' . $href );
            $score = 0;
            foreach ( array(
                'sayilarla'=>100, 'istatistik'=>95, 'okulumuz'=>90, 'hakkimizda'=>85,
                'hakkinda'=>80, 'kurumsal'=>70, 'biz kimiz'=>65, 'tanitim'=>60,
            ) as $needle=>$points ) {
                if ( false !== strpos( $label, $needle ) ) { $score = max( $score, $points ); }
            }
            if ( ! $score ) { continue; }

            $url = self::absolute_url( $href, $base_url );
            if ( ! $url || ! self::is_safe_public_url( $url ) ) { continue; }
            if ( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) !== $base_host ) { continue; }

            $ranked[ $url ] = max( $score, $ranked[ $url ] ?? 0 );
        }

        arsort( $ranked, SORT_NUMERIC );
        return array_slice( array_keys( $ranked ), 0, self::MAX_PAGES - 1 );
    }

    private static function extract_candidate( $html, $source_url ) {
        $text = self::visible_text( $html );
        if ( '' === $text ) { return null; }

        $patterns = array(
            array( '/(?:toplam\s+)?öğrenci\s+say(?:ımız|ısı)\s*[:\-]?\s*([0-9][0-9\.\,\s]{1,8})/iu', 120 ),
            array( '/(?:toplam\s+)?([0-9][0-9\.\,\s]{1,8})\s+öğrencimiz\b/iu', 115 ),
            array( '/\böğrencimiz\s*[:\-]?\s*([0-9][0-9\.\,\s]{1,8})/iu', 110 ),
            array( '/\b([0-9][0-9\.\,\s]{1,8})\s+öğrenci(?:miz)?\s+(?:ile|bulunmaktadır|eğitim)/iu', 95 ),
            array( '/\böğrenci\s*[:\-]\s*([0-9][0-9\.\,\s]{1,8})/iu', 90 ),
        );

        $best = null;
        foreach ( $patterns as $pattern ) {
            if ( ! preg_match_all( $pattern[0], $text, $matches, PREG_OFFSET_CAPTURE ) ) { continue; }
            foreach ( $matches[1] as $i=>$match ) {
                $count = self::parse_count( $match[0] );
                if ( null === $count || $count < 20 || $count > 15000 ) { continue; }

                $full_offset = $matches[0][$i][1] ?? $match[1];
                $excerpt = self::excerpt( $text, $full_offset, strlen( $matches[0][$i][0] ?? $match[0] ) );
                $candidate = array(
                    'candidate'  => $count,
                    'source_url' => esc_url_raw( $source_url ),
                    'excerpt'    => $excerpt,
                    'score'      => (int) $pattern[1],
                );
                if ( ! $best || $candidate['score'] > $best['score'] ) { $best = $candidate; }
            }
        }
        return $best;
    }

    private static function visible_text( $html ) {
        $html = preg_replace( '#<(script|style|noscript|svg|template)[^>]*>.*?</\1>#isu', ' ', (string) $html );
        $text = html_entity_decode( wp_strip_all_tags( $html, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return preg_replace( '/\s+/u', ' ', trim( $text ) );
    }

    private static function parse_count( $raw ) {
        $digits = preg_replace( '/[^0-9]/', '', (string) $raw );
        if ( '' === $digits ) { return null; }
        return absint( $digits );
    }

    private static function excerpt( $text, $offset, $length ) {
        $start = max( 0, (int) $offset - 90 );
        $size = min( 260, strlen( $text ) - $start );
        $chunk = substr( $text, $start, $size );
        return sanitize_text_field( trim( $chunk ) );
    }

    private static function norm_text( $value ) {
        $value = remove_accents( wp_strip_all_tags( (string) $value ) );
        $value = strtolower( preg_replace( '/\s+/u', ' ', trim( $value ) ) );
        return $value;
    }

    private static function absolute_url( $href, $base_url ) {
        $href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        if ( preg_match( '#^https?://#i', $href ) ) { return esc_url_raw( $href ); }
        if ( 0 === strpos( $href, '//' ) ) {
            $scheme = wp_parse_url( $base_url, PHP_URL_SCHEME ) ?: 'https';
            return esc_url_raw( $scheme . ':' . $href );
        }

        $parts = wp_parse_url( $base_url );
        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) { return ''; }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if ( ! empty( $parts['port'] ) ) { $origin .= ':' . absint( $parts['port'] ); }

        if ( 0 === strpos( $href, '/' ) ) { return esc_url_raw( $origin . $href ); }

        $path = $parts['path'] ?? '/';
        $dir = trailingslashit( preg_replace( '#/[^/]*$#', '/', $path ) );
        return esc_url_raw( $origin . $dir . ltrim( $href, '/' ) );
    }

    private static function is_safe_public_url( $url ) {
        if ( ! preg_match( '#^https?://#i', (string) $url ) ) { return false; }
        return (bool) wp_http_validate_url( $url );
    }

    private static function redirect_back( $row, $message, $type='success', $extra=array() ) {
        $args = array(
            'page'             => 'mad-okul-students',
            'il'               => (string) $row->il,
            'ilce'             => (string) $row->ilce,
            'research_notice'  => $message,
            'research_type'    => sanitize_key( $type ),
        );
        if ( ! empty( $_POST['mmc_program_id'] ) ) {
            $args['mmc_program_id'] = absint( $_POST['mmc_program_id'] );
        }
        $args = array_merge( $args, $extra );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
