<?php
/**
 * Plugin Name: Madagaskar Mikro e-Arşiv Taslakları
 * Description: Yeni WooCommerce bilet siparişlerini Mikro'nun e-Arşiv taslak şablonuna aktarır.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/draft.php';
require_once __DIR__ . '/workbook.php';

final class MDG_Mikro_Admin {
    const PAGE = 'mdg-mikro-taslak';
    const START = 'mdg_mikro_taslak_start_v1';

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'mdg_mikro_taslak';
    }

    public static function activate() {
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'ZipArchive' ) || ! class_exists( 'DOMDocument' ) ) {
            wp_die( 'WooCommerce, PHP ZIP ve DOM uzantıları gereklidir.' );
        }
        if ( ! is_readable( __DIR__ . '/assets/' . MDG_Mikro_Workbook::NAME ) ) {
            wp_die( 'Mikro şablonunu içeren kurulum ZIP dosyası gereklidir.' );
        }
        global $wpdb;
        $table = self::table();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE $table (
            order_id bigint(20) unsigned NOT NULL,
            batch char(36) NOT NULL,
            created bigint(20) unsigned NOT NULL,
            series varchar(5) NOT NULL,
            fiscal_year smallint unsigned NOT NULL,
            number int unsigned NOT NULL,
            fingerprint char(64) NOT NULL,
            document varchar(16) DEFAULT NULL,
            PRIMARY KEY  (order_id),
            UNIQUE KEY series_number (series,fiscal_year,number),
            UNIQUE KEY document (document),
            KEY batch (batch)
        ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';' );
        $engine = $wpdb->get_var( $wpdb->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table
        ) );
        if ( 'innodb' !== strtolower( (string) $engine ) ) {
            wp_die( 'Taslak kayıt tablosu hazırlanamadı.' );
        }
        add_option( self::START, time(), '', false );
    }

    public static function menu() {
        add_submenu_page( 'woocommerce', 'Mikro e-Arşiv Taslakları', 'Mikro e-Arşiv Taslakları',
            'manage_woocommerce', self::PAGE, array( __CLASS__, 'screen' ) );
    }

    private static function url( $params = array() ) {
        return add_query_arg( array( 'page' => self::PAGE ) + $params, admin_url( 'admin.php' ) );
    }
    public static function shortcut() {
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->id, array( 'woocommerce_page_mdg-fatura-takip', 'woocommerce_page_dunya-fatura-takip' ), true ) && current_user_can( 'manage_woocommerce' ) ) {
            echo '<div class="notice notice-info"><p><a href="' . esc_url( self::url() ) . '">Yeni siparişlerin Mikro e-Arşiv taslaklarını aç</a></p></div>';
        }
    }
    private static function field( $key ) {
        return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
    }
    private static function form( $action, $day = '' ) {
        echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
        wp_nonce_field( 'mdg_mikro_' . $action );
        echo '<input type="hidden" name="action" value="mdg_mikro_' . esc_attr( $action ) . '"><input type="hidden" name="day" value="' . esc_attr( $day ) . '">';
    }
    private static function bounds( $day ) {
        $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $day, new DateTimeZone( 'Europe/Istanbul' ) );
        if ( ! $date || $date->format( 'Y-m-d' ) !== $day ) { throw new RuntimeException( 'Geçersiz tarih.' ); }
        return array( $date->getTimestamp(), $date->modify( '+1 day' )->getTimestamp() - 1 );
    }
    private static function signature( $doc ) {
        return hash( 'sha256', wp_json_encode( $doc, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ) );
    }
    private static function batch_rows( $id ) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE batch=%s ORDER BY number", $id ) );
    }

    public static function screen() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        global $wpdb;
        $table = self::table();
        $start = (int) get_option( self::START );
        $day = isset( $_GET['day'] ) ? sanitize_text_field( wp_unslash( $_GET['day'] ) ) : MDG_Mikro_Draft::date( 'Y-m-d', time() );
        try { list( $from, $to ) = self::bounds( $day ); } catch ( RuntimeException $e ) { wp_die( esc_html( $e->getMessage() ) ); }
        $page = max( 1, absint( $_GET['pg'] ?? 1 ) );
        echo '<div class="wrap"><h1>Mikro e-Arşiv Taslakları</h1>';
        echo '<p>Başlangıç: ' . esc_html( MDG_Mikro_Draft::date( 'd.m.Y H:i:s', $start ) ) . ' (Türkiye saati). Liste ödeme gününe göredir.</p>';
        if ( ! function_exists( 'wc_get_orders' ) || ! $start ) { echo '<p>WooCommerce veya kurulum kaydı eksik.</p></div>'; return; }
        echo '<form><input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '"><label>Ödeme günü <input type="date" name="day" value="' . esc_attr( $day ) . '" required></label> <button class="button">Listele</button></form>';
        $orders = array();
        $pages = 0;
        if ( $to > $start ) {
            $result = wc_get_orders( array( 'type' => 'shop_order', 'status' => array( 'wc-processing', 'wc-completed' ),
                'date_created' => '>' . $start, 'date_paid' => max( $start + 1, $from ) . '...' . $to,
                'paginate' => true, 'limit' => 100, 'page' => $page, 'orderby' => 'ID', 'order' => 'ASC' ) );
            $orders = $result->orders;
            $pages = $result->max_num_pages;
        }
        self::form( 'export', $day );
        echo '<h2>Ödenen siparişler</h2><table class="widefat striped"><thead><tr><th>Seç</th><th>Sipariş</th><th>Alıcı / fatura adresi</th><th>Tutar</th><th>Durum</th></tr></thead><tbody>';
        $ready = 0;
        foreach ( $orders as $order ) {
            $id = $order->get_id();
            $record = $wpdb->get_row( $wpdb->prepare( "SELECT batch,document FROM $table WHERE order_id=%d", $id ) );
            $status = $record ? ( $record->document ?: 'Excel paketi oluşturuldu' ) : '';
            $selected = false;
            if ( ! $record ) {
                try { MDG_Mikro_Draft::build( $order, $start ); $selected = true; $ready++; $status = 'Taslağa hazır'; }
                catch ( RuntimeException $e ) { $status = $e->getMessage(); }
            }
            echo '<tr><td>';
            if ( $selected ) { echo '<input type="checkbox" name="ids[]" value="' . (int) $id . '" aria-label="Sipariş ' . (int) $id . '" checked>'; }
            echo '</td><td><a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . (int) $id . '</a></td><td>' .
                esc_html( $order->get_formatted_billing_full_name() . ' · ' . $order->get_billing_city() . ' / ' . $order->get_billing_state() ) .
                '</td><td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td><td>' . esc_html( $status ) . '</td></tr>';
        }
        if ( ! $orders ) { echo '<tr><td colspan="5">Bu ödeme gününde kurulum sonrası sipariş yok.</td></tr>'; }
        echo '</tbody></table>';
        if ( $ready ) {
            echo '<p><label>Mikro seri öneki <input name="series" maxlength="5" required></label> <label>Bu dosya için Mikro’daki ilk boş evrak no <input type="number" name="first" min="1" max="999999999" required></label></p>';
            echo '<p><label><input name="review" value="1" type="checkbox" required> Seçili siparişlerin alıcı adreslerini, faturalama durumunu ve bu dosyaya ayırdığım numaraları kontrol ettim.</label></p><button class="button button-primary">Seçili siparişleri Excel’e aktar</button>';
        }
        echo '</form>';
        if ( $page > 1 ) { echo '<a href="' . esc_url( self::url( array( 'day'=>$day, 'pg'=>$page-1 ) ) ) . '">Önceki sayfa</a> '; }
        if ( $page < $pages ) { echo '<a href="' . esc_url( self::url( array( 'day'=>$day, 'pg'=>$page+1 ) ) ) . '">Sonraki sayfa</a>'; }

        echo '<h2>Hazırlanan dosyalar</h2>';
        $batches = $wpdb->get_results( $wpdb->prepare( "SELECT batch,MAX(created) AS moment,COUNT(*) AS qty FROM $table WHERE created BETWEEN %d AND %d GROUP BY batch ORDER BY moment DESC", $from, $to ) );
        echo '<p>Dosyalar oluşturuldukları gün altında saklanır. İndirdiğiniz dosyayı Mikro’da e-Arşiv taslak içe aktarımına yükleyin.</p>';
        foreach ( $batches as $batch ) {
            echo '<details><summary>' . esc_html( MDG_Mikro_Draft::date( 'H:i:s', $batch->moment ) . ' · ' . $batch->qty . ' sipariş' ) . '</summary>';
            self::form( 'download', $day );
            echo '<input type="hidden" name="batch" value="' . esc_attr( $batch->batch ) . '"><button class="button">Aynı dosyayı tekrar indir</button></form>';
            foreach ( self::batch_rows( $batch->batch ) as $r ) {
                self::form( 'confirm', $day );
                echo '<p>#' . (int) $r->order_id . ' · ' . esc_html( $r->series . ' / ' . $r->number ) . ' ';
                if ( $r->document ) { echo esc_html( $r->document ); }
                else {
                    echo '<input type="hidden" name="id" value="' . (int) $r->order_id . '"><label>Mikro’da kesilen belge no <input name="document" maxlength="16" required></label> <button class="button">Kesildi olarak kaydet</button>';
                }
                echo '</p></form>';
            }
            self::form( 'release', $day );
            echo '<input type="hidden" name="batch" value="' . esc_attr( $batch->batch ) . '"><label><input type="checkbox" name="review" value="1" required> Bu dosya Mikro’ya alınmadı veya tüm taslakları orada silindi ve hiçbir belge kesilmedi.</label> <button class="button">Paketi geri al</button></form></details>';
        }
        echo '</div>';
    }

    private static function export_new() {
        global $wpdb;
        $table = self::table();
        $ids = array_unique( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ) );
        sort( $ids, SORT_NUMERIC );
        $series = strtoupper( self::field( 'series' ) );
        $first = self::field( 'first' );
        if ( '1' !== self::field( 'review' ) || ! $ids || count( $ids ) > 100 ||
             ! preg_match( '/^[A-Z0-9]{1,5}$/D', $series ) || ! preg_match( '/^[1-9][0-9]{0,8}$/D', $first ) ||
             (int) $first + count( $ids ) - 1 > 999999999 ) {
            throw new RuntimeException( 'Seçimi, kontrol onayını, seri ve ilk evrak numarasını kontrol edin.' );
        }
        list( $from, $to ) = self::bounds( self::field( 'day' ) );
        $start = (int) get_option( self::START );
        $now = time();
        $batch = wp_generate_uuid4();
        $docs = array();
        foreach ( $ids as $id ) {
            $data = MDG_Mikro_Draft::build( wc_get_order( $id ), $start );
            if ( $data['paid'] < $from || $data['paid'] > $to ) { throw new RuntimeException( 'Seçim ödeme günü ile eşleşmiyor.' ); }
            $docs[] = $data;
        }
        $bytes = MDG_Mikro_Workbook::bytes( $docs, $series, (int) $first, $now );
        self::begin();
        foreach ( $ids as $offset => $id ) {
            if ( false === $wpdb->insert( $table, array( 'order_id'=>$id, 'batch'=>$batch, 'created'=>$now,
                'series'=>$series, 'fiscal_year'=>MDG_Mikro_Draft::date( 'Y', $now ), 'number'=>(int) $first + $offset,
                'fingerprint'=>self::signature( $docs[$offset] ) ) ) ) {
                throw new RuntimeException( 'Sipariş veya evrak numarası başka bir pakette. Listeyi yenileyin.' );
            }
        }
        self::commit();
        return $bytes;
    }

    private static function download() {
        $rows = self::batch_rows( self::field( 'batch' ) );
        if ( ! $rows ) { throw new RuntimeException( 'Paket bulunamadı.' ); }
        $docs = array();
        foreach ( $rows as $r ) {
            if ( $r->document ) { throw new RuntimeException( 'Bu paketten belge kesilmiş. Mikro üzerinde kontrol edin.' ); }
            $doc = MDG_Mikro_Draft::build( wc_get_order( $r->order_id ), (int) get_option( self::START ) );
            if ( ! hash_equals( $r->fingerprint, self::signature( $doc ) ) ) {
                throw new RuntimeException( 'Sipariş paketten sonra değişmiş. Portal ile karşılaştırın.' );
            }
            $docs[] = $doc;
        }
        return MDG_Mikro_Workbook::bytes( $docs, $rows[0]->series, (int) $rows[0]->number, (int) $rows[0]->created );
    }

    private static function begin() {
        global $wpdb;
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Kayıt işlemi başlatılamadı.' ); }
    }
    private static function commit() {
        global $wpdb;
        if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Kayıt tamamlanamadı. Listeyi yenileyin.' ); }
    }

    private static function confirm() {
        global $wpdb;
        $table = self::table();
        $id = absint( self::field( 'id' ) );
        $document = strtoupper( self::field( 'document' ) );
        if ( ! preg_match( '/^[A-Z0-9]{3}[0-9]{13}$/D', $document ) ) { throw new RuntimeException( '16 karakterli Mikro fatura numarasını yazın.' ); }
        self::begin();
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE order_id=%d FOR UPDATE", $id ) );
        $order = $r ? wc_get_order( $id ) : false;
        if ( ! $order || ( $r->document && $r->document !== $document ) ||
             ( $order->get_meta( '_mdg_mikro_document' ) && $order->get_meta( '_mdg_mikro_document' ) !== $document ) ) {
            throw new RuntimeException( 'Sipariş ve belge eşleşmesi kontrol edilmeli.' );
        }
        if ( false === $wpdb->update( $table, array( 'document'=>$document ), array( 'order_id'=>$id ) ) ) {
            throw new RuntimeException( 'Belge numarası kaydedilemedi veya başka siparişte kullanılıyor.' );
        }
        $order->update_meta_data( '_mdg_mikro_document', $document );
        $order->update_meta_data( '_mdg_invoice_status', 'issued' );
        if ( ! $order->get_meta( '_mdg_invoice_date' ) ) { $order->update_meta_data( '_mdg_invoice_date', current_time( 'mysql' ) ); }
        if ( 'sent' !== $order->get_meta( '_dof_invoice_status' ) ) {
            $order->update_meta_data( '_dof_invoice_status', 'invoiced' );
        }
        if ( ! $order->get_meta( '_dof_invoice_date' ) ) { $order->update_meta_data( '_dof_invoice_date', current_time( 'mysql' ) ); }
        $order->save_meta_data();
        self::commit();
    }

    private static function release() {
        global $wpdb;
        $table = self::table();
        $batch = self::field( 'batch' );
        self::begin();
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT document FROM $table WHERE batch=%s FOR UPDATE", $batch ) );
        if ( '1' !== self::field( 'review' ) || ! $rows || array_filter( wp_list_pluck( $rows, 'document' ) ) ) {
            throw new RuntimeException( 'Belge kaydı içeren veya doğrulanmamış paket geri alınamaz.' );
        }
        if ( count( $rows ) !== $wpdb->delete( $table, array( 'batch'=>$batch ) ) ) { throw new RuntimeException( 'Paket geri alınamadı.' ); }
        self::commit();
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_woocommerce' ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) { wp_die( 'Yetkiniz yok.', '', array( 'response'=>403 ) ); }
        $action = self::field( 'action' );
        check_admin_referer( $action );
        global $wpdb;
        try {
            $bytes = null;
            switch ( $action ) {
                case 'mdg_mikro_export': $bytes = self::export_new(); break;
                case 'mdg_mikro_download': $bytes = self::download(); break;
                case 'mdg_mikro_confirm': self::confirm(); break;
                case 'mdg_mikro_release': self::release(); break;
                default: throw new RuntimeException( 'Geçersiz işlem.' );
            }
            if ( null !== $bytes ) {
                nocache_headers();
                header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
                header( 'Content-Disposition: attachment; filename="' . MDG_Mikro_Workbook::NAME . '"' );
                echo $bytes;
            } else { wp_safe_redirect( self::url( array( 'day' => self::field( 'day' ) ) ) ); }
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            wp_die( esc_html( $e instanceof RuntimeException ? $e->getMessage() : 'İşlem tamamlanamadı. Listeyi kontrol edin.' ), '', array( 'back_link'=>true ) );
        }
        exit;
    }
}
register_activation_hook( __FILE__, array( 'MDG_Mikro_Admin', 'activate' ) );
add_action( 'admin_menu', array( 'MDG_Mikro_Admin', 'menu' ), 100 );
add_action( 'admin_notices', array( 'MDG_Mikro_Admin', 'shortcut' ) );
foreach ( array( 'export', 'download', 'confirm', 'release' ) as $mdg_mikro_action ) {
    add_action( 'admin_post_mdg_mikro_' . $mdg_mikro_action, array( 'MDG_Mikro_Admin', 'handle' ) );
}
