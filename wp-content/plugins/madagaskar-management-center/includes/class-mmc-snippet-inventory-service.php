<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Snippet_Inventory_Service {

    public static function available() {
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    public static function inventory() {
        global $wpdb;
        $table = $wpdb->prefix . 'snippets';

        if ( ! self::available() ) {
            return array();
        }

        $columns = (array) $wpdb->get_col( "DESC {$table}", 0 );
        $wanted = array( 'id','name','description','code','active','scope','priority','modified','tags' );
        $select = array_values( array_intersect( $wanted, $columns ) );
        if ( ! in_array( 'id', $select, true ) || ! in_array( 'name', $select, true ) || ! in_array( 'code', $select, true ) ) {
            return array();
        }

        $rows = (array) $wpdb->get_results(
            "SELECT " . implode( ',', $select ) .
            " FROM {$table} ORDER BY " . ( in_array( 'active', $select, true ) ? 'active DESC,' : '' ) . " id DESC"
        );

        $items = array();
        foreach ( $rows as $row ) {
            $items[] = self::analyze_row( $row );
        }

        self::apply_conflicts( $items );
        return $items;
    }

    public static function summary() {
        $items = self::inventory();
        $summary = array(
            'total' => count( $items ),
            'active' => 0,
            'production' => 0,
            'test' => 0,
            'temporary' => 0,
            'legacy' => 0,
            'review' => 0,
            'conflicts' => 0,
            'warnings' => 0,
        );

        foreach ( $items as $item ) {
            if ( $item['active'] ) { $summary['active']++; }
            if ( isset( $summary[ $item['class'] ] ) ) { $summary[ $item['class'] ]++; }
            if ( ! empty( $item['conflicts'] ) ) { $summary['conflicts']++; }
            if ( in_array( $item['risk'], array( 'warning','critical' ), true ) ) { $summary['warnings']++; }
        }

        return $summary;
    }

    public static function health_check() {
        if ( ! self::available() ) {
            return array(
                'severity' => 'info',
                'detail' => 'Code Snippets tablosu algılanmadı.',
            );
        }

        $items = self::inventory();
        $active = array_values( array_filter( $items, function( $x ){ return ! empty( $x['active'] ); } ) );
        $risk = array_values( array_filter( $active, function( $x ){ return in_array( $x['risk'], array( 'warning','critical' ), true ); } ) );

        if ( ! $risk ) {
            return array(
                'severity' => 'ok',
                'detail' => sprintf( 'Code Snippets aktif. %d aktif snippet analiz edildi; aktif test/tanı veya güçlü çakışma adayı bulunmadı.', count( $active ) ),
            );
        }

        $labels = array();
        foreach ( array_slice( $risk, 0, 6 ) as $item ) {
            $labels[] = '#' . (int)$item['id'] . ' ' . $item['name'] . ' (' . self::class_label( $item['class'] ) . ')';
        }

        return array(
            'severity' => 'warning',
            'detail' => sprintf(
                '%d aktif snippet içinde %d inceleme adayı bulundu: %s. Otomatik kapatma yapılmaz; Snippet Envanteri ekranında kod/hook/çakışma ayrıntılarını doğrulayın.',
                count( $active ),
                count( $risk ),
                implode( ' · ', $labels )
            ),
        );
    }

    public static function class_label( $class ) {
        $labels = array(
            'production' => 'Üretim',
            'test' => 'Test / Tanı',
            'temporary' => 'Geçici',
            'legacy' => 'Legacy',
            'review' => 'İncele',
        );
        return $labels[ $class ] ?? 'İncele';
    }

    private static function analyze_row( $row ) {
        $name = trim( (string) ( $row->name ?? '' ) );
        $code = (string) ( $row->code ?? '' );
        $active = ! empty( $row->active );

        $hooks = self::matches( $code, '/add_(?:action|filter)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i' );
        $functions = self::matches( $code, '/(?:^|[^a-z0-9_])function\s+([a-z_][a-z0-9_]*)\s*\(/im' );
        $shortcodes = self::matches( $code, '/add_shortcode\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i' );

        $class = self::classify( $name, $code, $active );
        $risk = 'ok';
        $reason = '';

        if ( $active && 'test' === $class ) {
            $risk = 'warning';
            $reason = 'Aktif test/tanı snippet’i.';
        }

        if ( $active && false !== stripos( $code, 'Madagaskar V5 Finans Güncellemesi' ) ) {
            $class = 'legacy';
            $risk = 'warning';
            $reason = 'Eski V5 finans bildirimi üretiyor; MMC finans akışıyla çakışma ihtimali incelenmeli.';
        }

        if ( $active && self::has_mutating_patterns( $code ) && 'test' === $class ) {
            $risk = 'warning';
            $reason = 'Aktif test snippet’i veri/satış durumunu değiştirebilecek çağrılar içeriyor.';
        }

        return array(
            'id' => (int) ( $row->id ?? 0 ),
            'name' => $name ?: '(adsız snippet)',
            'description' => (string) ( $row->description ?? '' ),
            'active' => $active,
            'scope' => (string) ( $row->scope ?? '' ),
            'priority' => isset( $row->priority ) ? (int)$row->priority : null,
            'modified' => (string) ( $row->modified ?? '' ),
            'class' => $class,
            'risk' => $risk,
            'risk_reason' => $reason,
            'hooks' => array_values( array_unique( $hooks ) ),
            'functions' => array_values( array_unique( $functions ) ),
            'shortcodes' => array_values( array_unique( $shortcodes ) ),
            'mutating' => self::has_mutating_patterns( $code ),
            'conflicts' => array(),
            'recommendation' => self::recommendation( $class, $active ),
            'code_hash' => substr( hash( 'sha256', $code ), 0, 12 ),
            'code_preview' => self::preview( $code ),
        );
    }

    private static function classify( $name, $code, $active ) {
        $n = self::norm( $name );

        if ( preg_match( '/\b(test|diagnostic|debug|deneme)\b/u', $n ) ) {
            return 'test';
        }

        if ( preg_match( '/(iptal duyuru|afis guncelle|tarihi duzenleme|adres fix|cache temizleme)/u', $n ) ) {
            return 'temporary';
        }

        if ( ! $active && preg_match( '/\bv[0-9]+\b/u', $n ) ) {
            return 'legacy';
        }

        if ( false !== stripos( $code, 'Madagaskar V5 Finans Güncellemesi' ) ) {
            return 'legacy';
        }

        $production = array(
            'bilet tipi kisaltma',
            'woocommerce telefon zorunlu',
            'kommo otomatik bilet linki',
            'ozel sayfalar noindex',
            'eski sayfa yonlendirmeleri',
            'seo ayarlari',
            'sehir url',
            'anasayfa',
            'gosteri detay',
            'galeri',
            'hakkimizda',
            'kurumsal',
            'sss sayfasi',
            'iletisim sayfasi',
            'blog sayfasi',
            'bilet al sayfasi',
            'paytr bekleme kilidi',
            'whatsapp',
            'hukuki sayfalar'
        );
        foreach ( $production as $needle ) {
            if ( false !== strpos( $n, $needle ) ) {
                return 'production';
            }
        }

        return $active ? 'review' : 'legacy';
    }

    private static function recommendation( $class, $active ) {
        if ( ! $active ) {
            return 'Pasif. Geri dönüş ihtiyacı yoksa Export alındıktan sonra arşiv/Trash adayı.';
        }
        switch ( $class ) {
            case 'production':
                return 'Şimdilik koru. Canlı işlev taşıyor olabilir; kod karşılığı MMC/tema/eklentiye taşınmadan kapatma.';
            case 'test':
                return 'Öncelikli incele. Test amacı doğrulanırsa Export sonrası pasife alma adayı.';
            case 'temporary':
                return 'Geçerlilik/tarih ve canlı işlevi doğrula; görevi bittiyse Export sonrası pasife al.';
            case 'legacy':
                return 'MMC/MDG’de karşılığı varsa test ederek pasife alma adayı.';
            default:
                return 'Manuel kod incelemesi gerekli; otomatik kapatma önerilmez.';
        }
    }

    private static function apply_conflicts( &$items ) {
        $function_map = array();
        $shortcode_map = array();
        $domain_map = array();

        foreach ( $items as $i => $item ) {
            if ( empty( $item['active'] ) ) { continue; }

            foreach ( $item['functions'] as $fn ) {
                $function_map[ strtolower( $fn ) ][] = $i;
            }
            foreach ( $item['shortcodes'] as $sc ) {
                $shortcode_map[ strtolower( $sc ) ][] = $i;
            }

            $domain = self::domain( $item['name'] );
            if ( $domain ) {
                $domain_map[ $domain ][] = $i;
            }
        }

        foreach ( $function_map as $fn => $idxs ) {
            if ( count( $idxs ) < 2 ) { continue; }
            foreach ( $idxs as $i ) {
                $items[$i]['conflicts'][] = 'Aynı fonksiyon: ' . $fn;
                $items[$i]['risk'] = 'warning';
                $items[$i]['risk_reason'] = 'Başka aktif snippet ile aynı global fonksiyonu tanımlıyor.';
            }
        }

        foreach ( $shortcode_map as $sc => $idxs ) {
            if ( count( $idxs ) < 2 ) { continue; }
            foreach ( $idxs as $i ) {
                $items[$i]['conflicts'][] = 'Aynı shortcode: [' . $sc . ']';
                $items[$i]['risk'] = 'warning';
                $items[$i]['risk_reason'] = 'Başka aktif snippet ile aynı shortcode adını kullanıyor.';
            }
        }

        foreach ( $domain_map as $domain => $idxs ) {
            if ( count( $idxs ) < 2 ) { continue; }
            foreach ( $idxs as $i ) {
                $others = array();
                foreach ( $idxs as $j ) {
                    if ( $j !== $i ) { $others[] = '#' . $items[$j]['id'] . ' ' . $items[$j]['name']; }
                }
                if ( $others ) {
                    $items[$i]['conflicts'][] = 'Aynı işlev alanı (' . $domain . '): ' . implode( ', ', array_slice( $others, 0, 4 ) );
                }
            }
        }

        foreach ( $items as &$item ) {
            $item['conflicts'] = array_values( array_unique( $item['conflicts'] ) );
        }
        unset( $item );
    }

    private static function domain( $name ) {
        $n = self::norm( $name );
        $map = array(
            'sosyal paylaşım' => array( 'sosyal paylasim', 'global sosyal' ),
            'hukuk/iletişim' => array( 'hukuki', 'iletisim resmi adres', 'hukuki sayfalar iletisim' ),
            'bilet al' => array( 'bilet al sayfasi' ),
            'biletlerim' => array( 'biletlerim', 'whatsapp ve biletlerim' ),
            'kommo' => array( 'kommo' ),
            'seo' => array( 'seo', 'noindex', 'sitemap', 'sehir url' ),
            'gösteri detay' => array( 'gosteri detay' ),
            'sss' => array( 'sss sayfasi' ),
            'blog' => array( 'blog sayfasi', 'blog tekli' ),
            'etkinlik' => array( 'etkinlik tarihi', 'etkinlik kapat', 'iptal duyuru' )
        );
        foreach ( $map as $domain => $needles ) {
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $n, $needle ) ) { return $domain; }
            }
        }
        return '';
    }

    private static function has_mutating_patterns( $code ) {
        return (bool) preg_match(
            '/update_post_meta|delete_post_meta|wp_update_post|wp_delete_post|set_status\s*\(|update_status\s*\(|woocommerce_is_purchasable|woocommerce_variation_is_purchasable|\$wpdb->(?:update|delete|insert)|wp_remote_(?:post|request)/i',
            $code
        );
    }

    private static function matches( $text, $pattern ) {
        if ( ! preg_match_all( $pattern, $text, $m ) || empty( $m[1] ) ) {
            return array();
        }
        return array_map( 'strval', $m[1] );
    }

    private static function preview( $code ) {
        $code = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $code ) ) );
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $code, 0, 280 );
        }
        return substr( $code, 0, 280 );
    }

    private static function norm( $text ) {
        $text = strtolower( remove_accents( wp_strip_all_tags( (string)$text ) ) );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
        return trim( preg_replace( '/\s+/', ' ', $text ) );
    }
}
