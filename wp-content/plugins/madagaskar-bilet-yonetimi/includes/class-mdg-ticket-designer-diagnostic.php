<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only diagnostic for Tickera 3.6 Ticket Designer extension points.
 * It does not alter Tickera, ticket templates, products, orders or tickets.
 */
final class MDG_Ticket_Designer_Diagnostic {

    const ACTION = 'mdg_ticket_designer_diagnostic';

    public static function hooks() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'download' ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=' . self::ACTION ),
            self::ACTION,
            '_mdg_diag_nonce'
        );
        echo '<div class="mdg-panel"><h2>V2.7.2 Ticket Designer Tanı</h2>';
        echo '<p><strong>Önemli:</strong> V2.7.1 ile <em>Madagaskar Salon Konumu QR</em> alanı Tickera 3.6 Designer kenar çubuğunda görünmedi. Bu tanı aracı yalnızca kaynak kodu, kayıtlı hookları ve Designer veritabanı şemasını okur; hiçbir şablonu veya bileti değiştirmez.</p>';
        echo '<p>Dosya, sitenizdeki gerçek Tickera 3.6.0.2 ve ilgili add-on kodundan hangi entegrasyon yolunun kullanıldığını bulmamızı sağlar.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">Ticket Designer Tanı Dosyasını İndir</a></p>';
        echo '<p class="description">İndirilen <code>mdg-ticket-designer-diagnostic.txt</code> dosyasını bu sohbete yükleyin. Statik Salon Konumu QR görselini şimdilik silmeyin.</p></div>';
    }

    public static function download() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'madagaskar-bilet' ) );
        }
        check_admin_referer( self::ACTION, '_mdg_diag_nonce' );

        $text = self::build_report();
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="mdg-ticket-designer-diagnostic.txt"' );
        echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text diagnostic download.
        exit;
    }

    private static function build_report() {
        global $wpdb, $wp_filter;
        $out = array();
        $out[] = 'MADAGASKAR BILET YONETIMI - TICKERA 3.6 DESIGNER TANI';
        $out[] = 'Olusturma: ' . current_time( 'mysql' );
        $out[] = 'MDG surum: ' . ( defined( 'MDG_BILET_VERSION' ) ? MDG_BILET_VERSION : 'unknown' );
        $out[] = 'WordPress: ' . get_bloginfo( 'version' );
        $out[] = 'PHP: ' . PHP_VERSION;
        $out[] = '';

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        $active  = (array) get_option( 'active_plugins', array() );

        $out[] = '=== AKTIF TICKERA / ILGILI EKLENTILER ===';
        foreach ( $active as $plugin_file ) {
            $data = isset( $plugins[ $plugin_file ] ) ? $plugins[ $plugin_file ] : array();
            $hay  = strtolower( $plugin_file . ' ' . ( $data['Name'] ?? '' ) );
            if ( false !== strpos( $hay, 'tickera' ) || false !== strpos( $hay, 'bridge-for-woocommerce' ) || false !== strpos( $hay, 'custom-template' ) || false !== strpos( $hay, 'ticket' ) ) {
                $out[] = sprintf( '- %s | %s | %s', $plugin_file, $data['Name'] ?? '', $data['Version'] ?? '' );
            }
        }
        $out[] = '';

        $out[] = '=== LEGACY TEMPLATE API DURUMU ===';
        $out[] = 'tc_register_template_element: ' . ( function_exists( 'tc_register_template_element' ) ? 'VAR' : 'YOK' );
        $out[] = 'TC_Ticket_Template_Elements: ' . ( class_exists( 'TC_Ticket_Template_Elements' ) ? 'VAR' : 'YOK' );
        $out[] = 'Tickera\\TC_Ticket_Template_Elements: ' . ( class_exists( '\\Tickera\\TC_Ticket_Template_Elements' ) ? 'VAR' : 'YOK' );
        $out[] = 'MDG element class: ' . ( class_exists( 'tc_mdg_venue_qr_element' ) ? 'VAR' : 'YOK' );
        $out[] = '';

        $out[] = '=== DESIGNER DB TABLOLARI ===';
        foreach ( array( 'tickera_ticket_templates', 'tickera_ticket_template_assignments' ) as $suffix ) {
            $table = $wpdb->prefix . $suffix;
            $out[] = '-- ' . $table;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
            if ( $cols ) {
                foreach ( $cols as $col ) {
                    $out[] = '  ' . implode( ' | ', array_map( 'strval', $col ) );
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY 1 DESC LIMIT 3", ARRAY_A );
                $out[] = '  SAMPLE ROWS:';
                foreach ( (array) $rows as $row ) {
                    $clean = array();
                    foreach ( $row as $k => $v ) {
                        $v = is_scalar( $v ) || null === $v ? (string) $v : wp_json_encode( $v );
                        if ( strlen( $v ) > 3000 ) { $v = substr( $v, 0, 3000 ) . '...<TRUNCATED>'; }
                        $clean[ $k ] = $v;
                    }
                    $out[] = wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                }
            } else {
                $out[] = '  TABLO YOK / OKUNAMADI: ' . $wpdb->last_error;
            }
            $out[] = '';
        }

        $out[] = '=== ILGILI WORDPRESS HOOKLARI ===';
        if ( is_array( $wp_filter ) || $wp_filter instanceof Traversable ) {
            foreach ( $wp_filter as $tag => $hook ) {
                if ( preg_match( '/ticket|designer|template|addon/i', (string) $tag ) ) {
                    $callbacks = array();
                    if ( is_object( $hook ) && isset( $hook->callbacks ) ) {
                        foreach ( (array) $hook->callbacks as $priority => $items ) {
                            foreach ( (array) $items as $item ) {
                                $fn = isset( $item['function'] ) ? $item['function'] : null;
                                $callbacks[] = $priority . ':' . self::callback_name( $fn );
                            }
                        }
                    }
                    $out[] = $tag . ( $callbacks ? ' => ' . implode( ', ', array_slice( $callbacks, 0, 30 ) ) : '' );
                }
            }
        }
        $out[] = '';

        $out[] = '=== KAYNAK KOD ARAMA ===';
        $needles = array(
            'ADD-ON FIELDS',
            'Ticket Type (Custom)',
            'WooCommerce Billing Info',
            'tc_register_template_element',
            'tc-ticket-designer',
            'ticket_designer',
            'ticket designer',
            'addon_fields',
            'add-on fields',
            'tickera_ticket_templates',
            'ticket_template_assignments',
        );
        $dirs = self::candidate_plugin_dirs( $plugins, $active );
        foreach ( $dirs as $label => $dir ) {
            $out[] = '-- SCAN: ' . $label . ' => ' . $dir;
            $matches = self::scan_dir( $dir, $needles, 180 );
            if ( ! $matches ) {
                $out[] = '  Eslesme bulunamadi.';
            } else {
                foreach ( $matches as $match ) {
                    $out[] = $match;
                }
            }
            $out[] = '';
        }

        return implode( "\n", $out ) . "\n";
    }

    private static function callback_name( $fn ) {
        if ( is_string( $fn ) ) { return $fn; }
        if ( is_array( $fn ) && 2 === count( $fn ) ) {
            $left = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
            return $left . '::' . $fn[1];
        }
        if ( $fn instanceof Closure ) { return 'Closure'; }
        if ( is_object( $fn ) ) { return get_class( $fn ); }
        return 'unknown';
    }

    private static function candidate_plugin_dirs( $plugins, $active ) {
        $dirs = array();
        foreach ( $active as $plugin_file ) {
            $data = isset( $plugins[ $plugin_file ] ) ? $plugins[ $plugin_file ] : array();
            $hay = strtolower( $plugin_file . ' ' . ( $data['Name'] ?? '' ) );
            if ( false !== strpos( $hay, 'tickera' ) || false !== strpos( $hay, 'bridge-for-woocommerce' ) || false !== strpos( $hay, 'custom-template' ) || false !== strpos( $hay, 'ticket' ) ) {
                $base = dirname( $plugin_file );
                $dir  = '.' === $base ? WP_PLUGIN_DIR : WP_PLUGIN_DIR . '/' . $base;
                if ( is_dir( $dir ) ) { $dirs[ $plugin_file ] = $dir; }
            }
        }
        return $dirs;
    }

    private static function scan_dir( $dir, $needles, $limit = 180 ) {
        $results = array();
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
            );
            foreach ( $it as $file ) {
                if ( count( $results ) >= $limit ) { break; }
                if ( ! $file->isFile() ) { continue; }
                $ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, array( 'php', 'js', 'jsx', 'ts', 'tsx', 'json' ), true ) ) { continue; }
                if ( $file->getSize() > 2000000 ) { continue; }
                if ( preg_match( '/\.min\.js$/i', $file->getFilename() ) ) { continue; }
                $lines = @file( $file->getPathname(), FILE_IGNORE_NEW_LINES );
                if ( ! is_array( $lines ) ) { continue; }
                foreach ( $lines as $idx => $line ) {
                    $found = false;
                    foreach ( $needles as $needle ) {
                        if ( false !== stripos( $line, $needle ) ) { $found = true; break; }
                    }
                    if ( ! $found ) { continue; }
                    $start = max( 0, $idx - 2 );
                    $end   = min( count( $lines ) - 1, $idx + 2 );
                    $rel   = ltrim( str_replace( wp_normalize_path( $dir ), '', wp_normalize_path( $file->getPathname() ) ), '/' );
                    $buf   = array();
                    for ( $i = $start; $i <= $end; $i++ ) {
                        $buf[] = sprintf( '%5d | %s', $i + 1, $lines[ $i ] );
                    }
                    $results[] = sprintf( "[%s:%d]\n%s", $rel, $idx + 1, implode( "\n", $buf ) );
                    if ( count( $results ) >= $limit ) { break 2; }
                }
            }
        } catch ( Exception $e ) {
            $results[] = 'SCAN ERROR: ' . $e->getMessage();
        }
        return $results;
    }
}
