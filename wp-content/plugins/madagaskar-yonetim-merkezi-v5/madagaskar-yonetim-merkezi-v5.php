<?php
/**
 * Plugin Name: Madagaskar Yönetim Merkezi V5
 * Description: Mevcut Madagaskar Bilet Yönetimi, V4 ve Aile Paketi motorlarına dokunmadan satış, etkinlik, gider ve kârlılık yönetimini tek merkezde toplar.
 * Version: 5.7.4
 * Author: Dünya Organizasyon
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 9.0
 * Text Domain: madagaskar-v5
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/includes/class-mdg-v5-finance.php';

final class MDG_Yonetim_Merkezi_V5 {
    const VERSION = '5.7.4';
    const CAP = 'manage_woocommerce';
    const PARENT = 'mdg-dashboard';
    const FAMILY_OPTION = 'mdg_family_package_22_v1';
    const CAPACITY_OPTION = 'mdg_v5_session_capacities';

    private static $hidden_tools = array();
    private static $hidden_v4 = array();
    private static $legacy_menu = array();

    public static function boot() {
        MDG_V5_Finance::boot();
        add_action( 'admin_menu', array( __CLASS__, 'register_pages' ), 9000 );
        add_action( 'admin_menu', array( __CLASS__, 'simplify_menu' ), 99999 );
        add_action( 'admin_head', array( __CLASS__, 'hide_visual_menus' ), 99999 );
        add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_dashboard' ), 20 );
        add_action( 'admin_init', array( __CLASS__, 'repair_family_events_once' ), 5 );
        add_action( 'admin_notices', array( __CLASS__, 'publish_page_family_box' ) );
        add_action( 'admin_post_mdg_v5_family_toggle', array( __CLASS__, 'save_family_toggle' ) );
        add_action( 'admin_post_mdg_v5_save_capacities', array( __CLASS__, 'save_capacities' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'plugin_links' ) );
    }

    public static function plugin_links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mdg-v5-center' ) ) . '">Yönetim Merkezi</a>' );
        return $links;
    }

    public static function register_pages() {
        if ( ! current_user_can( self::CAP ) ) { return; }

        add_submenu_page(
            self::PARENT,
            'Madagaskar Yönetim Merkezi V5',
            'Yönetim Merkezi',
            self::CAP,
            'mdg-v5-center',
            array( __CLASS__, 'render_center' )
        );

        add_submenu_page(
            self::PARENT,
            'Etkinlikler',
            'Etkinlikler',
            self::CAP,
            'mdg-v5-events',
            array( __CLASS__, 'render_events' )
        );

        add_submenu_page(
            self::PARENT,
            'Satışlar ve Biletler',
            'Satışlar ve Biletler',
            self::CAP,
            'mdg-v5-sales',
            array( __CLASS__, 'render_sales' )
        );

        add_submenu_page(
            self::PARENT,
            'Gider ve Kârlılık',
            'Gider ve Kârlılık',
            self::CAP,
            'mdg-v5-finance',
            array( 'MDG_V5_Finance', 'render' )
        );

        add_submenu_page(
            self::PARENT,
            'Geliştirici Araçları',
            'Geliştirici Araçları',
            self::CAP,
            'mdg-v5-dev',
            array( __CLASS__, 'render_dev' )
        );
    }

    private static function norm( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = trim( preg_replace( '/\s+/u', ' ', $text ) );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
    }

    public static function simplify_menu() {
        global $submenu;

        if ( ! is_array( $submenu ) ) { return; }

        /*
         * V5.0.1: erişim güvenliği.
         * WordPress bazı eklenti sayfalarında erişim kontrolünü canlı $submenu
         * kaydı üzerinden de yapar. Bu nedenle eski sürümdeki gibi
         * remove_submenu_page()/remove_menu_page() veya $submenu dizisini
         * küçültmek doğrudan URL ile açılan sayfalarda "erişiminize izin
         * verilmiyor" hatasına yol açabiliyordu.
         *
         * Burada artık HİÇBİR kayıt silinmez. Sadece hangi ekranların
         * geliştirici alanında gösterileceği kaydedilir; görsel sadeleştirme
         * admin_head içindeki CSS/JS ile yapılır.
         */

        self::$hidden_tools = array();
        if ( ! empty( $submenu['tools.php'] ) && is_array( $submenu['tools.php'] ) ) {
            foreach ( $submenu['tools.php'] as $row ) {
                $label = isset( $row[0] ) ? wp_strip_all_tags( $row[0] ) : '';
                $slug  = isset( $row[2] ) ? (string) $row[2] : '';
                if ( ! $slug ) { continue; }
                $is_mdg = false !== stripos( $label, 'Madagaskar' )
                    || 0 === strpos( $slug, 'mdg-' )
                    || 0 === strpos( $slug, 'ms-paytr-' );
                if ( $is_mdg ) {
                    self::$hidden_tools[] = array( 'label'=>$label, 'slug'=>$slug );
                }
            }
        }

        self::$hidden_v4 = array();
        if ( ! empty( $submenu['madagaskar-v4'] ) && is_array( $submenu['madagaskar-v4'] ) ) {
            foreach ( $submenu['madagaskar-v4'] as $row ) {
                self::$hidden_v4[] = array(
                    'label' => isset( $row[0] ) ? wp_strip_all_tags( $row[0] ) : '',
                    'slug'  => isset( $row[2] ) ? (string) $row[2] : '',
                );
            }
        }

        self::$legacy_menu = array();
        if ( ! empty( $submenu[ self::PARENT ] ) && is_array( $submenu[ self::PARENT ] ) ) {
            foreach ( $submenu[ self::PARENT ] as $idx => $row ) {
                $slug = isset( $row[2] ) ? (string) $row[2] : '';
                if ( $slug ) {
                    self::$legacy_menu[] = array(
                        'label' => isset( $row[0] ) ? wp_strip_all_tags( $row[0] ) : '',
                        'slug'  => $slug,
                    );
                }

                // Günlük görünen öğelerin adlarını sade tut.
                $rename = array(
                    'mdg-v5-center' => 'Genel Bakış',
                    'mdg-publish'   => 'Etkinlik Yayınla',
                    'mdg-v5-events' => 'Etkinlikler',
                    'mdg-v5-sales'  => 'Satışlar ve Biletler',
                    'mdg-v5-finance'=> 'Gider ve Kârlılık',
                    'mdgy-marketing'=> 'V2 – Pazarlama',
                    'mdgy-crm'      => 'V3 – CRM',
                    'mdgy-integrations' => 'Entegrasyonlar',
                    'mdg-settings'  => 'Ayarlar',
                    'mdg-v5-dev'    => 'Geliştirici Araçları',
                );
                if ( isset( $rename[ $slug ] ) ) {
                    $submenu[ self::PARENT ][ $idx ][0] = $rename[ $slug ];
                }
            }
        }
    }

    public static function hide_visual_menus() {
        if ( ! current_user_can( self::CAP ) ) { return; }

        $allowed_mdg = array(
            'mdg-v5-center',
            'mdg-publish',
            'mdg-v5-events',
            'mdg-v5-sales',
            'mdg-v5-finance',
            'mdgy-marketing',
            'mdgy-crm',
            'mdgy-integrations',
            'mdg-settings',
            'mdg-v5-dev',
        );

        $tool_slugs = array();
        foreach ( (array) self::$hidden_tools as $item ) {
            if ( ! empty( $item['slug'] ) ) { $tool_slugs[] = (string) $item['slug']; }
        }

        echo '<style id="mdgv5-menu-hide">#toplevel_page_madagaskar-v4{display:none!important;}</style>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var allowed = <?php echo wp_json_encode( array_values( $allowed_mdg ) ); ?>;
            var toolSlugs = <?php echo wp_json_encode( array_values( $tool_slugs ) ); ?>;

            function getPage(href){
                try {
                    var u = new URL(href, window.location.href);
                    return u.searchParams.get('page') || '';
                } catch(e) { return ''; }
            }

            // Madagaskar ana menüsünde yalnız günlük altı öğeyi göster.
            document.querySelectorAll('#toplevel_page_mdg-dashboard .wp-submenu a').forEach(function(a){
                var page = getPage(a.getAttribute('href') || '');
                var label = (a.textContent || '').toLocaleLowerCase('tr-TR').trim();
                var isSalonMenu = label.indexOf('salon') !== -1;
                if ( page && allowed.indexOf(page) === -1 && !isSalonMenu ) {
                    var li = a.closest('li');
                    if (li) li.style.display = 'none';
                }
            });

            // Araçlar altındaki Madagaskar bakım/hotfix ekranlarını görselden gizle.
            document.querySelectorAll('#menu-tools .wp-submenu a, #toplevel_page_tools-php .wp-submenu a').forEach(function(a){
                var page = getPage(a.getAttribute('href') || '');
                if ( page && toolSlugs.indexOf(page) !== -1 ) {
                    var li = a.closest('li');
                    if (li) li.style.display = 'none';
                }
            });
            if (getPage(window.location.href) === 'mdg-v5-center') {
                var hideTickera=function(){document.querySelectorAll('a').forEach(function(a){if((a.textContent||'').indexOf('Tickera - Custom Forms')!==-1){var box=a.closest('.notice,.updated,.error')||a.parentElement;if(box)box.style.display='none';}});};
                hideTickera(); setTimeout(hideTickera,500); setTimeout(hideTickera,1500);
            }
        });
        </script>
        <?php
    }

    public static function redirect_legacy_dashboard() {
        if ( ! is_admin() || ! current_user_can( self::CAP ) ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( self::PARENT === $page && empty( $_GET['legacy'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=mdg-v5-center' ) );
            exit;
        }
    }

    public static function css() {
        echo '<style>
        .mdgv5{max-width:1240px}.mdgv5 h1{display:flex;align-items:center;gap:8px}
        .mdgv5-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin:18px 0}
        .mdgv5-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
        .mdgv5-card h2,.mdgv5-card h3{margin-top:0}.mdgv5-kpi{font-size:32px;font-weight:800;line-height:1}
        .mdgv5-ok{color:#008a20;font-weight:700}.mdgv5-bad{color:#b32d2e;font-weight:700}.mdgv5-warn{color:#996800;font-weight:700}
        .mdgv5-note{background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:14px 0}
        .mdgv5-safe{background:#edfaef;border-left:4px solid #00a32a;padding:12px 14px;margin:14px 0}
        .mdgv5-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.mdgv5-actions form{margin:0}
        .mdgv5-table td,.mdgv5-table th{vertical-align:middle}.mdgv5-flow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:12px}
        .mdgv5-step{background:#f6f7f7;border-radius:999px;padding:7px 11px;font-weight:600}.mdgv5-arrow{color:#787c82}
        .mdgv5-family{background:#fff8e5;border-left:4px solid #dba617;padding:12px 14px;margin:12px 0}
        .mdgv5-filter{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:16px 0}.mdgv5-filter a{text-decoration:none}
        .mdgv5-filter .current{background:#1d2327;color:#fff;border-color:#1d2327}.mdgv5-sub{font-size:12px;color:#646970}
        .mdgv5-bars{display:grid;grid-template-columns:repeat(auto-fit,minmax(42px,1fr));gap:8px;align-items:end;height:190px;padding-top:14px}
        .mdgv5-barcol{display:flex;flex-direction:column;justify-content:flex-end;text-align:center;height:100%;min-width:0}.mdgv5-bar{background:linear-gradient(180deg,#d4af37,#8b1a1a);border-radius:6px 6px 2px 2px;min-height:3px}.mdgv5-barval,.mdgv5-barlab{font-size:10px;color:#646970}
        .mdgv5-two{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.55fr);gap:16px;margin:18px 0}@media(max-width:900px){.mdgv5-two{grid-template-columns:1fr}}
        .mdgv5-progress{width:130px;max-width:100%;height:9px;background:#e5e7eb;border-radius:99px;overflow:hidden;margin:5px 0}.mdgv5-progress span{display:block;height:100%;background:#2271b1}.mdgv5-progress .warn{background:#dba617}.mdgv5-progress .danger{background:#b32d2e}
        .mdgv5-cap{width:90px}.mdgv5-speed{display:inline-block;border-radius:99px;padding:3px 8px;background:#f0f0f1;font-size:11px;font-weight:700}.mdgv5-speed.fast{background:#edfaef;color:#008a20}.mdgv5-speed.slow{background:#fff8e5;color:#996800}
        .mdgv5-finance-form{display:grid;grid-template-columns:repeat(2,minmax(240px,1fr));gap:14px 18px}.mdgv5-finance-form label{display:flex;flex-direction:column;gap:6px}.mdgv5-finance-form input,.mdgv5-finance-form select,.mdgv5-finance-form textarea{width:100%;max-width:none}.mdgv5-finance-form .mdgv5-wide{grid-column:1/-1}.mdgv5 .nav-tab-wrapper{display:flex;flex-wrap:wrap;gap:0}.mdgv5-card{overflow-x:auto}.mdgv5-attendance-row{display:grid;grid-template-columns:130px 90px 120px 80px 90px 75px;gap:10px;align-items:center}.mdgv5-attendance-row input{width:100%}.mdgv5-attendance-row .mdgv5-check{display:flex;gap:5px;align-items:center;white-space:nowrap}.mdgv5-attendance-row .mdgv5-check input{width:auto}.mdgv5-excluded{opacity:.55;background:#f6f7f7!important}@media(max-width:782px){.mdgv5-finance-form{grid-template-columns:1fr}.mdgv5-kpi{font-size:27px}.mdgv5 .nav-tab{margin-bottom:0;flex:1 1 auto;text-align:center}.mdgv5-table,.mdgv5-card table{min-width:760px}}
        </style>';
    }

    private static function mdg_ready() {
        return class_exists( 'MDG_DB' ) && class_exists( 'MDG_Events' ) && class_exists( 'MDG_Sessions' );
    }

    private static function events( $limit = 100 ) {
        global $wpdb;
        if ( ! self::mdg_ready() ) { return array(); }
        $table = MDG_DB::table( 'events' );
        $limit = max( 1, min( 500, absint( $limit ) ) );
        return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}" );
    }

    private static function status_label( $status ) {
        $map = array(
            'draft'   => 'Taslak',
            'onsale'  => 'Satışta',
            'closed'  => 'Satış Kapalı',
            'soldout' => 'Tükendi',
            'past'    => 'Süresi Doldu',
        );
        return isset( $map[ $status ] ) ? $map[ $status ] : $status;
    }

    private static function family_settings() {
        $saved = get_option( self::FAMILY_OPTION, array() );
        if ( ! is_array( $saved ) ) { $saved = array(); }
        if ( empty( $saved['label'] ) ) { $saved['label'] = 'Aile Paketi 2+2'; }
        if ( empty( $saved['price'] ) ) { $saved['price'] = 1100; }
        if ( empty( $saved['events'] ) || ! is_array( $saved['events'] ) ) { $saved['events'] = array(); }
        return $saved;
    }

    private static function family_event_map() {
        $settings = self::family_settings();
        $map = array();
        $events = (array) $settings['events'];
        $keys = array_keys( $events );
        $is_list = empty( $events ) || $keys === range( 0, count( $events ) - 1 );
        foreach ( $events as $k => $v ) {
            $event_id = $is_list ? absint( $v ) : absint( $k );
            if ( $event_id > 0 && ( $is_list || $v ) ) { $map[ $event_id ] = 1; }
        }
        return $map;
    }

    public static function repair_family_events_once() {
        if ( get_option( 'mdg_v5_family_events_repaired_573' ) ) { return; }
        if ( ! current_user_can( self::CAP ) || ! self::mdg_ready() ) { return; }

        $settings = self::family_settings();
        $events = (array) $settings['events'];
        $looks_corrupted = isset( $events[0] ) && 1 === absint( $events[0] );

        if ( $looks_corrupted ) {
            global $wpdb;
            $table = MDG_DB::table( 'events' );
            $active_ids = (array) $wpdb->get_col( "SELECT id FROM {$table} WHERE status = 'onsale'" );
            foreach ( $events as $k => $v ) {
                if ( is_numeric( $k ) && absint( $k ) > 1 && $v ) { $active_ids[] = absint( $k ); }
            }
            $active_ids = array_values( array_unique( array_filter( array_map( 'absint', $active_ids ) ) ) );
            $settings['events'] = $active_ids;
            update_option( self::FAMILY_OPTION, $settings, false );
            update_option( 'mdg_v5_family_events_repair_notice_573', 1, false );
        }

        update_option( 'mdg_v5_family_events_repaired_573', 1, false );
    }

    private static function family_enabled( $event_id ) {
        $map = self::family_event_map();
        return isset( $map[ absint( $event_id ) ] );
    }

    private static function family_plugin_active() {
        return class_exists( 'MDG_Family_Package_22_V1' );
    }

    private static function family_form( $event_id, $compact = false ) {
        $event_id = absint( $event_id );
        if ( ! $event_id ) { return; }
        $enabled = self::family_enabled( $event_id );
        $settings = self::family_settings();

        if ( ! self::family_plugin_active() ) {
            echo '<span class="mdgv5-bad">Aile Paketi eklentisi aktif değil</span>';
            return;
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap">';
        echo '<input type="hidden" name="action" value="mdg_v5_family_toggle">';
        echo '<input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
        echo '<input type="hidden" name="return" value="' . esc_attr( self::current_admin_url() ) . '">';
        wp_nonce_field( 'mdg_v5_family_toggle_' . $event_id, 'mdg_v5_nonce' );
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked( $enabled, true, false ) . '> <strong>' . esc_html( $settings['label'] ) . '</strong></label>';
        echo '<label><span class="screen-reader-text">Aile paketi fiyatı</span><input type="number" name="family_price" value="' . esc_attr( isset( $settings['event_prices'][ $event_id ] ) && (float) $settings['event_prices'][ $event_id ] > 0 ? (float) $settings['event_prices'][ $event_id ] : (float) $settings['price'] ) . '" min="1" step="0.01" inputmode="decimal" style="width:120px" required> <strong>TL</strong></label>';
        submit_button( $compact ? 'Kaydet' : 'Aile Paketi Ayarını Kaydet', 'secondary', 'submit', false );
        echo '</form>';
    }

    private static function current_admin_url() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        if ( $host && $uri ) { return $scheme . $host . $uri; }
        return admin_url( 'admin.php?page=mdg-v5-events' );
    }

    public static function save_family_toggle() {
        if ( ! current_user_can( self::CAP ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_admin_referer( 'mdg_v5_family_toggle_' . $event_id, 'mdg_v5_nonce' );
        if ( ! $event_id ) { wp_die( 'Etkinlik bulunamadı.' ); }

        $settings = self::family_settings();
        $map = self::family_event_map();
        $raw_price = isset( $_POST['family_price'] ) ? wp_unslash( $_POST['family_price'] ) : '';
        $price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $raw_price ) : str_replace( ',', '.', sanitize_text_field( $raw_price ) );
        $price = (float) $price;
        if ( $price <= 0 ) { wp_die( 'Aile paketi fiyatı geçerli bir tutar olmalıdır.' ); }
        if ( ! isset( $settings['event_prices'] ) || ! is_array( $settings['event_prices'] ) ) { $settings['event_prices'] = array(); }
        $settings['event_prices'][ $event_id ] = round( $price, 2 );
        if ( ! empty( $_POST['enabled'] ) ) { $map[ $event_id ] = 1; }
        else { unset( $map[ $event_id ] ); }
        // Aile Paketi motoru etkinlikleri sıralı kimlik listesi olarak okur.
        // Sadece düzenlenen etkinliği değiştir; diğer seçimleri aynen koru.
        $settings['events'] = array_values( array_map( 'absint', array_keys( $map ) ) );
        update_option( self::FAMILY_OPTION, $settings, false );

        $return = isset( $_POST['return'] ) ? esc_url_raw( wp_unslash( $_POST['return'] ) ) : '';
        if ( ! $return || 0 !== strpos( $return, admin_url() ) ) {
            $return = admin_url( 'admin.php?page=mdg-v5-events' );
        }
        $return = add_query_arg( 'mdg_v5_family_saved', 1, $return );
        wp_safe_redirect( $return );
        exit;
    }

    public static function publish_page_family_box() {
        if ( ! is_admin() || ! current_user_can( self::CAP ) ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $event_id = absint( $_GET['edit'] ?? 0 );
        if ( 'mdg-publish' !== $page || ! $event_id ) { return; }

        if ( get_option( 'mdg_v5_family_events_repair_notice_573' ) ) {
            delete_option( 'mdg_v5_family_events_repair_notice_573' );
            echo '<div class="notice notice-warning is-dismissible"><p><strong>Önceki kayıtta kapanan satıştaki aile paketleri yeniden etkinleştirildi.</strong> Etkinlikler ekranından seçimleri kontrol edebilirsiniz.</p></div>';
        }
        if ( ! empty( $_GET['mdg_v5_family_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Aile paketi ayarı ve fiyatı kaydedildi.</strong></p></div>';
        }
        echo '<div class="notice notice-info" style="padding:12px 14px"><p style="margin:0 0 8px"><strong>V5 Hızlı Ayar — Aile Paketi 2+2</strong></p>';
        echo '<p style="margin:0 0 10px">Aile paketini artık 6. Bilet Türleri bölümüne eklemeyin. Buradan açıldığında mevcut 2 Çocuk + 2 Yetişkin varyasyonunu kullanır; 4 ayrı QR üretir.</p>';
        self::family_form( $event_id, false );
        echo '</div>';
    }

    private static function counts() {
        $counts = array( 'all'=>0, 'draft'=>0, 'onsale'=>0, 'closed'=>0 );
        foreach ( self::events( 500 ) as $event ) {
            $counts['all']++;
            $s = (string) $event->status;
            if ( isset( $counts[ $s ] ) ) { $counts[ $s ]++; }
        }
        return $counts;
    }

    private static function selected_days() {
        $days = isset( $_GET['mdg_days'] ) ? absint( $_GET['mdg_days'] ) : 7;
        return in_array( $days, array( 1, 7, 30, 90 ), true ) ? $days : 7;
    }

    private static function money( $amount ) {
        return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( (float) $amount ) ) : number_format_i18n( (float) $amount, 2 ) . ' TL';
    }

    private static function sales_snapshot( $days ) {
        $data = array( 'available'=>false, 'orders'=>0, 'revenue'=>0, 'tickets'=>0, 'average'=>0, 'adult'=>0, 'child'=>0, 'family'=>0, 'refund'=>0, 'pending'=>0, 'failed'=>0, 'daily'=>array(), 'items'=>array(), 'events'=>array(), 'recent'=>array() );
        if ( ! function_exists( 'wc_get_orders' ) ) { return $data; }
        $data['available'] = true;
        $from = strtotime( '-' . max( 0, $days - 1 ) . ' days', current_time( 'timestamp' ) );
        $from_day = wp_date( 'Y-m-d 00:00:00', $from, wp_timezone() );
        $paid = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
        $orders = wc_get_orders( array( 'limit'=>500, 'status'=>$paid, 'date_created'=>'>=' . $from_day, 'orderby'=>'date', 'order'=>'DESC', 'return'=>'objects' ) );
        $other = wc_get_orders( array( 'limit'=>500, 'status'=>array( 'pending','failed','cancelled','refunded' ), 'date_created'=>'>=' . $from_day, 'return'=>'objects' ) );
        for ( $i=0; $i<$days; $i++ ) {
            $key = wp_date( 'Y-m-d', strtotime( '+' . $i . ' days', $from ), wp_timezone() );
            $data['daily'][ $key ] = array( 'revenue'=>0, 'tickets'=>0 );
        }
        foreach ( $orders as $order ) {
            if ( ! is_a( $order, 'WC_Order' ) ) { continue; }
            $data['orders']++;
            $net = max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() );
            $data['revenue'] += $net; $data['refund'] += (float) $order->get_total_refunded();
            $created = $order->get_date_created(); $day = $created ? $created->date_i18n( 'Y-m-d' ) : ''; $order_tickets = 0;
            foreach ( $order->get_items( 'line_item' ) as $item ) {
                $qty=max( 0, (int) $item->get_quantity() ); $name=trim( (string) $item->get_name() );
                $kind = self::norm( $name );
                $is_family = false !== strpos( $kind, 'aile' );
                $ticket_units = $is_family ? $qty * 4 : $qty;
                $order_tickets += $ticket_units; $data['tickets'] += $ticket_units;
                if ( $is_family ) { $data['family'] += $qty; $data['adult'] += $qty * 2; $data['child'] += $qty * 2; }
                elseif ( false !== strpos( $kind, 'çocuk' ) || false !== strpos( $kind, 'cocuk' ) ) { $data['child'] += $qty; }
                elseif ( false !== strpos( $kind, 'yetişkin' ) || false !== strpos( $kind, 'yetiskin' ) ) { $data['adult'] += $qty; }
                if ( ! isset( $data['items'][ $name ] ) ) { $data['items'][ $name ]=array( 'qty'=>0, 'revenue'=>0 ); }
                $line_revenue = max( 0, (float) $item->get_total() );
                $data['items'][ $name ]['qty'] += $ticket_units; $data['items'][ $name ]['revenue'] += $line_revenue;
                $parsed = self::parse_ticket_name( $name );
                $event_key = $parsed['event']; $session_key = $parsed['session']; $type_key = $is_family ? 'Aile Paketi' : $parsed['type'];
                if ( ! isset( $data['events'][ $event_key ] ) ) $data['events'][ $event_key ]=array( 'tickets'=>0, 'revenue'=>0, 'sessions'=>array() );
                if ( ! isset( $data['events'][ $event_key ]['sessions'][ $session_key ] ) ) $data['events'][ $event_key ]['sessions'][ $session_key ]=array( 'tickets'=>0, 'revenue'=>0, 'types'=>array() );
                if ( ! isset( $data['events'][ $event_key ]['sessions'][ $session_key ]['types'][ $type_key ] ) ) $data['events'][ $event_key ]['sessions'][ $session_key ]['types'][ $type_key ]=0;
                $data['events'][ $event_key ]['tickets'] += $ticket_units; $data['events'][ $event_key ]['revenue'] += $line_revenue;
                $data['events'][ $event_key ]['sessions'][ $session_key ]['tickets'] += $ticket_units; $data['events'][ $event_key ]['sessions'][ $session_key ]['revenue'] += $line_revenue;
                $data['events'][ $event_key ]['sessions'][ $session_key ]['types'][ $type_key ] += $ticket_units;
            }
            if ( isset( $data['daily'][ $day ] ) ) { $data['daily'][ $day ]['revenue'] += $net; $data['daily'][ $day ]['tickets'] += $order_tickets; }
            if ( count( $data['recent'] ) < 8 ) $data['recent'][]=array( 'id'=>$order->get_id(), 'date'=>$created?$created->date_i18n('d.m.Y H:i'):'—', 'customer'=>trim($order->get_formatted_billing_full_name())?:'Misafir', 'tickets'=>$order_tickets, 'total'=>$net, 'status'=>wc_get_order_status_name($order->get_status()) );
        }
        foreach ( $other as $order ) if ( is_a( $order, 'WC_Order' ) ) {
            if ( 'pending' === $order->get_status() ) $data['pending']++;
            if ( in_array( $order->get_status(), array('failed','cancelled'), true ) ) $data['failed']++;
            if ( 'refunded' === $order->get_status() ) $data['refund'] += (float) $order->get_total();
        }
        self::merge_all_live_sessions( $data );
        $data['average']=$data['orders'] ? $data['revenue']/$data['orders'] : 0;
        uasort( $data['items'], function($a,$b){ return $b['qty'] <=> $a['qty']; } );
        return $data;
    }

    private static function session_time( $session ) {
        $vars=is_object($session)?get_object_vars($session):(array)$session;
        foreach(array('session_time','show_time','time') as $field){
            if(empty($vars[$field])||!is_scalar($vars[$field]))continue;
            if(preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u',(string)$vars[$field],$m))return str_pad($m[1],2,'0',STR_PAD_LEFT).':'.$m[2];
        }
        foreach(array('starts_at','start_at','start_datetime','datetime') as $field){
            if(empty($vars[$field])||!is_scalar($vars[$field]))continue;
            $raw=(string)$vars[$field];
            if(preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}/',$raw))return get_date_from_gmt(str_replace('T',' ',substr($raw,0,19)),'H:i');
        }
        if(!empty($vars['start_time'])&&is_scalar($vars['start_time'])){
            $raw=(string)$vars['start_time'];
            if(preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}/',$raw))return get_date_from_gmt(str_replace('T',' ',substr($raw,0,19)),'H:i');
            if(preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u',$raw,$m))return str_pad($m[1],2,'0',STR_PAD_LEFT).':'.$m[2];
        }
        foreach($vars as $field=>$value){
            if(!is_scalar($value)||preg_match('/created|updated|modified/i',(string)$field))continue;
            if(preg_match('/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u',(string)$value,$m))return str_pad($m[1],2,'0',STR_PAD_LEFT).':'.$m[2];
        }
        return '';
    }

    private static function merge_all_live_sessions( &$data ) {
        if(!self::mdg_ready())return;
        foreach(self::events(500) as $event){
            if((string)$event->status!=='onsale')continue;
            $province=self::norm((string)($event->province_name??'')); $district=self::norm((string)($event->district??''));
            $best='';$score_best=0;
            foreach(array_keys($data['events']) as $candidate){$norm=self::norm($candidate);$score=0;if($province&&false!==strpos($norm,$province))$score+=3;if($district&&false!==strpos($norm,$district))$score+=2;if($score>$score_best){$best=$candidate;$score_best=$score;}}
            $event_key=$score_best>=3?$best:trim((string)$event->title);
            if(!$event_key)$event_key=trim((string)($event->province_name??'Etkinlik'));
            if(!isset($data['events'][$event_key]))$data['events'][$event_key]=array('tickets'=>0,'revenue'=>0,'sessions'=>array());
            foreach((array)MDG_Sessions::by_event(absint($event->id)) as $session){
                $time=self::session_time($session);if(!$time)continue;
                if(!isset($data['events'][$event_key]['sessions'][$time]))$data['events'][$event_key]['sessions'][$time]=array('tickets'=>0,'revenue'=>0,'types'=>array());
            }
            ksort($data['events'][$event_key]['sessions'],SORT_NATURAL);
        }
    }

    private static function parse_ticket_name( $name ) {
        $event = preg_replace( '/\s+[–—-]\s+\d{1,2}[:.]\d{2}\s+Bileti.*$/ui', '', (string) $name );
        if ( ! $event || $event === $name ) $event = preg_replace( '/\s+Bileti\s+[–—-].*$/ui', '', (string) $name );
        preg_match( '/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u', (string) $name, $m );
        $session = ! empty( $m[0] ) ? str_replace( '.', ':', $m[0] ) : 'Seans belirtilmemiş';
        $kind = self::norm( $name );
        if ( false !== strpos( $kind, 'aile' ) ) $type='Aile Paketi';
        elseif ( false !== strpos( $kind, 'çocuk' ) || false !== strpos( $kind, 'cocuk' ) ) $type='Çocuk';
        elseif ( false !== strpos( $kind, 'yetişkin' ) || false !== strpos( $kind, 'yetiskin' ) ) $type='Yetişkin';
        else $type='Diğer';
        return array( 'event'=>trim( $event ) ?: (string) $name, 'session'=>$session, 'type'=>$type );
    }

    private static function period_filter( $days ) {
        echo '<div class="mdgv5-filter"><strong>Dönem:</strong>';
        foreach ( array(1=>'Bugün',7=>'Son 7 Gün',30=>'Son 30 Gün',90=>'Son 90 Gün') as $value=>$label ) {
            $url=add_query_arg(array('page'=>'mdg-v5-center','mdg_days'=>$value),admin_url('admin.php'));
            echo '<a class="button ' . ($days===$value?'current':'') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        } echo '</div>';
    }

    private static function capacity_key( $event, $session ) { return sha1( (string) $event . '|' . (string) $session ); }

    private static function capacities() {
        $saved=get_option(self::CAPACITY_OPTION,array()); return is_array($saved)?$saved:array();
    }

    public static function save_capacities() {
        if(!current_user_can(self::CAP)) wp_die('Yetkiniz yok.');
        check_admin_referer('mdg_v5_save_capacities','mdg_v5_capacity_nonce');
        $saved=self::capacities(); $posted=isset($_POST['capacities'])?(array)wp_unslash($_POST['capacities']):array();
        foreach($posted as $key=>$value){ $key=sanitize_key($key); if(!preg_match('/^[a-f0-9]{40}$/',$key)) continue; $value=absint($value); if($value>0)$saved[$key]=$value; else unset($saved[$key]); }
        update_option(self::CAPACITY_OPTION,$saved,false);
        $days=isset($_POST['mdg_days'])?absint($_POST['mdg_days']):7; $event=isset($_POST['mdg_event'])?sanitize_key($_POST['mdg_event']):'';
        wp_safe_redirect(add_query_arg(array('page'=>'mdg-v5-center','mdg_days'=>$days,'mdg_event'=>$event,'capacity_saved'=>1),admin_url('admin.php'))); exit;
    }

    private static function event_breakdown( $events, $days ) {
        if ( ! $events ) { echo '<p>Bu dönemde satış bulunamadı.</p>'; return; }
        uasort( $events, function($a,$b){ return $b['tickets'] <=> $a['tickets']; } );
        $selected=isset($_GET['mdg_event'])?sanitize_key(wp_unslash($_GET['mdg_event'])):'';
        echo '<form method="get" class="mdgv5-filter"><input type="hidden" name="page" value="mdg-v5-center"><input type="hidden" name="mdg_days" value="'.absint($days).'"><label for="mdg-event"><strong>Etkinlik:</strong></label><select id="mdg-event" name="mdg_event"><option value="">Tüm etkinlikler</option>';
        foreach($events as $event=>$row){$hash=sha1($event);echo '<option value="'.esc_attr($hash).'" '.selected($selected,$hash,false).'>'.esc_html($event).'</option>';}
        echo '</select><button class="button">Göster</button></form>';
        $capacities=self::capacities();
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_save_capacities"><input type="hidden" name="mdg_days" value="'.absint($days).'"><input type="hidden" name="mdg_event" value="'.esc_attr($selected).'">';
        wp_nonce_field('mdg_v5_save_capacities','mdg_v5_capacity_nonce');
        echo '<table class="widefat striped mdgv5-table"><thead><tr><th>Etkinlik</th><th>Seans</th><th>Dağılım</th><th>Satılan</th><th>Kapasite</th><th>Doluluk / Kalan</th><th>Hız</th><th>Ciro</th></tr></thead><tbody>';
        foreach ( $events as $event=>$event_row ) {
            if($selected && sha1($event)!==$selected) continue;
            $event_capacity=0; foreach($event_row['sessions'] as $sname=>$srow){$skey=self::capacity_key($event,$sname);$event_capacity+=isset($capacities[$skey])?absint($capacities[$skey]):0;}
            $event_percent=$event_capacity>0?min(100,round(($event_row['tickets']/$event_capacity)*100,1)):0;
            $first=true; $rowspan=max(1,count($event_row['sessions']));
            foreach ( $event_row['sessions'] as $session=>$session_row ) {
                $key=self::capacity_key($event,$session); $capacity=isset($capacities[$key])?absint($capacities[$key]):0; $sold=absint($session_row['tickets']);
                $percent=$capacity>0?min(100,round(($sold/$capacity)*100,1)):0; $remaining=$capacity>0?max(0,$capacity-$sold):null;
                $per_day=$days>0?$sold/$days:$sold; $speed=$per_day>=10?'Hızlı':($per_day>=3?'Normal':'Yavaş'); $speed_class=$speed==='Hızlı'?'fast':($speed==='Yavaş'?'slow':'');
                echo '<tr>';
                if($first){ echo '<td rowspan="'.absint($rowspan).'"><strong>'.esc_html($event).'</strong><div class="mdgv5-sub">Toplam '.number_format_i18n($event_row['tickets']).' bilet · '.esc_html(self::money($event_row['revenue'])).'</div>'.($event_capacity?'<div class="mdgv5-sub">Kapasite '.number_format_i18n($event_capacity).' · Doluluk %'.number_format_i18n($event_percent,1).'</div>':'').'</td>'; $first=false; }
                $types=array(); foreach($session_row['types'] as $type=>$qty) $types[]=esc_html($type).': '.number_format_i18n($qty);
                echo '<td><strong>'.esc_html($session).'</strong></td><td>'.implode(' · ',$types).'</td><td>'.number_format_i18n($sold).'</td>';
                echo '<td><input class="mdgv5-cap" type="number" min="0" step="1" name="capacities['.esc_attr($key).']" value="'.($capacity?esc_attr($capacity):'').'" placeholder="Örn. 500"></td>';
                if($capacity){$barclass=$percent>=90?'danger':($percent>=70?'warn':'');echo '<td><strong>%'.esc_html(number_format_i18n($percent,1)).'</strong><div class="mdgv5-progress"><span class="'.esc_attr($barclass).'" style="width:'.esc_attr($percent).'%"></span></div><span class="mdgv5-sub">'.number_format_i18n($remaining).' bilet kaldı</span></td>';}else echo '<td><span class="mdgv5-sub">Kapasite girin</span></td>';
                echo '<td><span class="mdgv5-speed '.esc_attr($speed_class).'">'.esc_html($speed).'</span><div class="mdgv5-sub">'.esc_html(number_format_i18n($per_day,1)).' bilet/gün</div></td><td>'.esc_html(self::money($session_row['revenue'])).'</td></tr>';
            }
        }
        echo '</tbody></table><p><button class="button button-primary">Kapasiteleri Kaydet</button></p></form>';
    }

    private static function sales_chart( $daily ) {
        $max=0; foreach($daily as $row) $max=max($max,(float)$row['revenue']); echo '<div class="mdgv5-bars">';
        foreach($daily as $date=>$row){ $height=$max>0?max(3,round(((float)$row['revenue']/$max)*135)):3;
            echo '<div class="mdgv5-barcol" title="'.esc_attr(wp_date('d.m.Y',strtotime($date)).' · '.self::money($row['revenue']).' · '.absint($row['tickets']).' bilet').'"><div class="mdgv5-barval">'.esc_html(absint($row['tickets'])).'</div><div class="mdgv5-bar" style="height:'.esc_attr($height).'px"></div><div class="mdgv5-barlab">'.esc_html(wp_date('d.m',strtotime($date))).'</div></div>'; }
        echo '</div>';
    }

    public static function render_center() {
        if ( ! current_user_can( self::CAP ) ) { return; }
        self::css();
        $counts = self::counts();
        $days = self::selected_days();
        $sales = self::sales_snapshot( $days );
        echo '<div class="wrap mdgv5"><h1>🎪 Madagaskar Yönetim Merkezi V' . esc_html( self::VERSION ) . '</h1>';
        if(!empty($_GET['capacity_saved'])) echo '<div class="notice notice-success is-dismissible"><p>Kapasiteler kaydedildi.</p></div>';
        echo '<div class="mdgv5-safe"><strong>Canlı satış özeti.</strong> Bu ekran WooCommerce verilerini yalnızca okur; sipariş, bilet, ödeme veya etkinlik kayıtlarını değiştirmez.</div>';
        self::period_filter( $days );
        if ( ! $sales['available'] ) {
            echo '<div class="notice notice-error"><p><strong>WooCommerce aktif değil.</strong> Satış göstergeleri oluşturulamadı.</p></div>';
        } else {
            echo '<div class="mdgv5-grid">';
            self::kpi(number_format_i18n($sales['tickets']),'Satılan Bilet',$sales['orders'].' başarılı sipariş');
            self::kpi(self::money($sales['revenue']),'Net Satış','İadeler düşülmüştür');
            self::kpi(self::money($sales['average']),'Ortalama Sepet','Başarılı sipariş başına');
            self::kpi(number_format_i18n($sales['pending']),'Ödeme Bekleyen',$sales['failed'].' başarısız / iptal');
            echo '</div><div class="mdgv5-grid">';
            self::kpi(number_format_i18n($sales['adult']),'Yetişkin','Ürün adına göre');
            self::kpi(number_format_i18n($sales['child']),'Çocuk','Ürün adına göre');
            self::kpi(number_format_i18n($sales['family']),'Aile Paketi','Paket sipariş adedi');
            self::kpi(self::money($sales['refund']),'İade','Seçili dönemde');
            echo '</div><div class="mdgv5-two"><div class="mdgv5-card"><h2>Günlük satış</h2><p class="mdgv5-sub">Çubukların üzerindeki sayı bilet adedidir.</p>';
            self::sales_chart($sales['daily']);
            echo '</div><div class="mdgv5-card"><h2>Dönem özeti</h2><p><strong>'.number_format_i18n(count($sales['events'])).'</strong> etkinlikte satış</p><p><strong>'.number_format_i18n($sales['adult']).'</strong> yetişkin</p><p><strong>'.number_format_i18n($sales['child']).'</strong> çocuk</p><p><strong>'.number_format_i18n($sales['family']).'</strong> aile paketi</p><p class="mdgv5-sub">Aile paketi kapasitede dört bilet sayılır.</p></div></div>';
            echo '<div class="mdgv5-card"><h2>Etkinlik ve seans performansı</h2><p class="mdgv5-sub">Satışlar etkinlik, seans ve bilet türüne göre birleştirilmiştir.</p>';
            self::event_breakdown($sales['events'],$days);
            echo '</div><div class="mdgv5-card"><h2>Son başarılı siparişler</h2><table class="widefat striped"><thead><tr><th>Sipariş</th><th>Tarih</th><th>Müşteri</th><th>Bilet</th><th>Tutar</th><th>Durum</th></tr></thead><tbody>';
            foreach($sales['recent'] as $row) echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page=wc-orders&action=edit&id='.absint($row['id']))).'">#'.absint($row['id']).'</a></td><td>'.esc_html($row['date']).'</td><td>'.esc_html($row['customer']).'</td><td>'.absint($row['tickets']).'</td><td>'.esc_html(self::money($row['total'])).'</td><td>'.esc_html($row['status']).'</td></tr>';
            if(!$sales['recent']) echo '<tr><td colspan="6">Bu dönemde başarılı sipariş bulunamadı.</td></tr>';
            echo '</tbody></table></div>';
        }

        echo '<div class="mdgv5-flow"><span class="mdgv5-step">1. Etkinliği hazırla</span><span class="mdgv5-arrow">→</span><span class="mdgv5-step">2. Taslak satış nesneleri</span><span class="mdgv5-arrow">→</span><span class="mdgv5-step">3. Aile Paketi</span><span class="mdgv5-arrow">→</span><span class="mdgv5-step">4. Kontrol</span><span class="mdgv5-arrow">→</span><span class="mdgv5-step">5. Satışa Aç</span></div>';

        echo '<div class="mdgv5-grid">';
        self::kpi( $counts['onsale'], 'Satıştaki Etkinlik', 'Etkinlikler' );
        self::kpi( $counts['draft'], 'Taslak Etkinlik', 'Hazırlanıyor' );
        self::kpi( $counts['closed'], 'Satış Kapalı', 'Arşiv / geçici kapalı' );
        $family_settings = self::family_settings();
        self::kpi( self::family_plugin_active() ? 'Aktif' : 'Kapalı', 'Aile Paketi 2+2', self::family_plugin_active() ? 'Etkinliğe göre fiyat · 4 QR' : 'Eklenti kontrolü gerekli' );
        echo '</div>';

        echo '<div class="mdgv5-grid">';
        echo '<div class="mdgv5-card"><h2>➕ Yeni Etkinlik</h2><p>Excel yükleyin veya formu elle doldurun. Salon, seans, görseller ve standart Çocuk/Yetişkin biletleri tek ekranda hazırlanır.</p><a class="button button-primary button-hero" href="' . esc_url( admin_url( 'admin.php?page=mdg-publish' ) ) . '">Etkinlik Yayınla</a></div>';
        echo '<div class="mdgv5-card"><h2>🗓️ Etkinlikler</h2><p>Taslak üretim, Aile Paketi ve canlı yayın durumlarını aynı tabloda görün.</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdg-v5-events' ) ) . '">Etkinlikleri Yönet</a></div>';
        echo '<div class="mdgv5-card"><h2>🎟️ Satışlar ve Biletler</h2><p>Sipariş, satış raporu, müşteri ve bilet listelerine tek yerden ulaşın.</p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=mdg-v5-sales' ) ) . '">Satış Merkezi</a></div>';
        echo '<div class="mdgv5-card"><h2>💰 Gider ve Kârlılık</h2><p>Etkinlik giderlerini manuel girin, fatura veya makbuz ekleyin; ciro, toplam gider ve net sonucu izleyin.</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdg-v5-finance' ) ) . '">Finans Merkezini Aç</a></div>';
        if ( class_exists( 'MDGY_Core' ) ) {
            echo '<div class="mdgv5-card"><h2>📣 V2 – Pazarlama</h2><p>Meta Ads, Instagram ve GA4 performansını kampanya ve trafik kaynağı düzeyinde izleyin.</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdgy-marketing' ) ) . '">Pazarlama Merkezi</a></div>';
            echo '<div class="mdgv5-card"><h2>💬 V3 – CRM</h2><p>Kommo, WhatsApp ve reklamdan siparişe kadar dönüşüm zincirini izleyin.</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdgy-crm' ) ) . '">CRM Merkezi</a></div>';
        }
        echo '<div class="mdgv5-card"><h2>⚙️ Ayarlar</h2><p>Salon, QR, entegrasyon ve hazırlık kontrollerine gerektiğinde erişin.</p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=mdg-settings' ) ) . '">Ayarlar</a></div>';
        if ( class_exists( 'MDGY_Core' ) ) {
            echo '<div class="mdgv5-card"><h2>🔌 Entegrasyonlar</h2><p>Meta, Instagram, GA4 ve Kommo bağlantılarını kurun; senkronizasyon durumunu kontrol edin.</p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=mdgy-integrations' ) ) . '">Bağlantıları Yönet</a></div>';
        }
        echo '</div>';

        echo '<div class="mdgv5-card"><h2>Son Etkinlikler</h2>';
        self::events_table( array_slice( self::events( 12 ), 0, 8 ), false );
        echo '</div></div>';
    }

    private static function kpi( $value, $title, $note ) {
        echo '<div class="mdgv5-card"><div class="mdgv5-kpi">' . esc_html( $value ) . '</div><h3>' . esc_html( $title ) . '</h3><p style="margin-bottom:0;color:#646970">' . esc_html( $note ) . '</p></div>';
    }

    public static function render_events() {
        if ( ! current_user_can( self::CAP ) ) { return; }
        self::css();
        echo '<div class="wrap mdgv5"><h1>🗓️ Etkinlikler</h1>';
        echo '<div class="mdgv5-note"><strong>Tek çalışma ekranı:</strong> Yeni etkinliği “Etkinlik Yayınla” ekranında hazırlayın. Sonra bu sayfada taslak satış nesnelerini üretin, Aile Paketi 2+2’yi açın ve hazırlık yeşil olduğunda satışa alın.</div>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdg-publish' ) ) . '">+ Yeni Etkinlik / Excel İçe Aktar</a></p>';
        self::events_table( self::events( 100 ), true );
        echo '</div>';
    }

    private static function events_table( $events, $controls ) {
        if ( ! $events ) { echo '<p>Etkinlik bulunamadı.</p>'; return; }
        echo '<table class="widefat striped mdgv5-table"><thead><tr><th>Etkinlik</th><th>Konum</th><th>Seans</th><th>Durum</th><th>Aile Paketi</th><th>Hazırlık</th><th>İşlem</th></tr></thead><tbody>';
        foreach ( $events as $event ) {
            $event_id = absint( $event->id );
            $sessions = class_exists( 'MDG_Sessions' ) ? (array) MDG_Sessions::by_event( $event_id ) : array();
            $status = (string) $event->status;
            $readiness_text = '—';
            $readiness_ok = false;
            $plan = null;
            $state = null;

            if ( 'draft' === $status && class_exists( 'MDG_New_Event_Production_Plan' ) ) {
                try { $plan = MDG_New_Event_Production_Plan::build( $event ); } catch ( Throwable $e ) { $plan = array( 'ready'=>false, 'errors'=>array( $e->getMessage() ) ); }
                if ( $plan && class_exists( 'MDG_New_Event_Draft_Producer' ) ) {
                    $state = MDG_New_Event_Draft_Producer::state( $event_id, (string) ( $plan['production_key'] ?? '' ) );
                }
                if ( $state && 'complete' === (string) $state['status'] && class_exists( 'MDG_Production_Readiness' ) ) {
                    $check = MDG_Production_Readiness::check_event( $event );
                    $readiness_ok = ! empty( $check['ready'] );
                    $readiness_text = $readiness_ok ? 'CANLI YAYINA HAZIR' : implode( ' · ', array_slice( (array) ( $check['errors'] ?? array() ), 0, 3 ) );
                } elseif ( $plan && ! empty( $plan['ready'] ) ) {
                    $readiness_text = ( $state && 'clean' === (string) $state['status'] ) ? 'Üretim planı hazır' : 'Taslak üretim: ' . (string) ( $state['status'] ?? 'kontrol' );
                } else {
                    $readiness_text = $plan ? implode( ' · ', array_slice( (array) ( $plan['errors'] ?? array() ), 0, 3 ) ) : 'Üretim planı kontrol edilemedi';
                }
            } elseif ( 'onsale' === $status ) {
                $readiness_ok = true;
                $readiness_text = 'Satışta';
            }

            echo '<tr>';
            echo '<td><strong>#' . $event_id . ' ' . esc_html( (string) $event->title ) . '</strong></td>';
            echo '<td>' . esc_html( trim( (string) $event->province_name . ' / ' . (string) $event->district, ' /' ) ) . '</td>';
            echo '<td>' . count( $sessions ) . '</td>';
            echo '<td><strong>' . esc_html( self::status_label( $status ) ) . '</strong></td>';
            echo '<td>' . ( self::family_enabled( $event_id ) ? '<span class="mdgv5-ok">Açık</span>' : '<span style="color:#646970">Kapalı</span>' ) . '</td>';
            echo '<td>' . ( $readiness_ok ? '<span class="mdgv5-ok">' : '<span class="mdgv5-warn">' ) . esc_html( $readiness_text ) . '</span></td>';
            echo '<td><div class="mdgv5-actions">';

            if ( 'draft' === $status ) {
                echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page'=>'mdg-publish','edit'=>$event_id,'mdg_production_plan'=>1 ), admin_url( 'admin.php' ) ) ) . '">Düzenle</a>';

                if ( $controls && $plan && ! empty( $plan['ready'] ) && $state && 'clean' === (string) $state['status'] ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Bu etkinlik için WooCommerce/Tickera satış nesneleri yalnızca TASLAK olarak oluşturulsun mu?\');">';
                    echo '<input type="hidden" name="action" value="mdg_create_new_event_sales_draft"><input type="hidden" name="event_id" value="' . $event_id . '"><input type="hidden" name="confirm_create" value="1">';
                    wp_nonce_field( 'mdg_create_new_event_sales_draft_' . $event_id, 'mdg_nonce' );
                    submit_button( 'Taslak Satış Nesnelerini Oluştur', 'secondary', 'submit', false );
                    echo '</form>';
                }

                if ( $controls && $state && 'complete' === (string) $state['status'] ) {
                    self::family_form( $event_id, true );
                    if ( $readiness_ok ) {
                        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Etkinlik ve doğrulanmış WooCommerce/Tickera nesneleri CANLI SATIŞA açılacak. Devam edilsin mi?\');">';
                        echo '<input type="hidden" name="action" value="mdg_publish_event_live"><input type="hidden" name="event_id" value="' . $event_id . '"><input type="hidden" name="confirm_live" value="1">';
                        wp_nonce_field( 'mdg_publish_event_live_' . $event_id, 'mdg_nonce' );
                        submit_button( 'ETKİNLİĞİ SATIŞA AÇ', 'primary', 'submit', false );
                        echo '</form>';
                    }
                }
            } elseif ( 'onsale' === $status ) {
                if ( $controls ) { self::family_form( $event_id, true ); }
                if ( ! empty( $event->public_slug ) ) {
                    echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( home_url( '/etkinlik/' . trim( (string) $event->public_slug, '/' ) . '/' ) ) . '">Etkinlik Sayfası ↗</a>';
                }
            } else {
                echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page'=>'mdg-publish','edit'=>$event_id ), admin_url( 'admin.php' ) ) ) . '">Görüntüle</a>';
            }

            echo '</div></td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function render_sales() {
        if ( ! current_user_can( self::CAP ) ) { return; }
        self::css();
        echo '<div class="wrap mdgv5"><h1>🎟️ Satışlar ve Biletler</h1><div class="mdgv5-safe"><strong>V5.0.1:</strong> Eski/V4 sayfaları menüden yalnız görsel olarak gizlenir; erişim kayıtları korunur. Bu kartlardaki bağlantılar doğrudan çalışır.</div><div class="mdgv5-grid">';
        self::link_card( 'WooCommerce Siparişleri', 'Ödeme, sipariş durumu ve sipariş kalemlerini yönetin.', admin_url( 'admin.php?page=wc-orders' ) );
        self::link_card( 'Satış Raporları', 'Şehir / seans bazında satış ve doluluk raporları.', admin_url( 'admin.php?page=mdg-reports' ) );
        self::link_card( 'Müşteri / Bilet Listeleri', 'Müşteri, bilet, QR ve check-in listeleri.', admin_url( 'admin.php?page=mdg-customers' ) );
        self::link_card( 'Biletlerim', 'V4 güvenli bilet linkleri ve PDF akışı.', admin_url( 'admin.php?page=mdg-v4-biletlerim' ) );
        self::link_card( 'Erteleme / Aktarım', 'V4 güvenli erteleme ve bilet aktarım merkezi.', admin_url( 'admin.php?page=mdg-v4-postpone' ) );
        self::link_card( 'İptal / İade', 'V4 dry-run ve onaylı iade merkezi.', admin_url( 'admin.php?page=mdg-v4-refund' ) );
        echo '</div></div>';
    }

    private static function link_card( $title, $text, $url ) {
        echo '<div class="mdgv5-card"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p><a class="button" href="' . esc_url( $url ) . '">Aç</a></div>';
    }

    public static function render_dev() {
        if ( ! current_user_can( self::CAP ) ) { return; }
        self::css();
        echo '<div class="wrap mdgv5"><h1>🛠️ Geliştirici Araçları</h1>';
        echo '<div class="mdgv5-note"><strong>Günlük kullanım için gerekli değildir.</strong> Bu ekran yalnız eski/hotfix/teşhis araçlarına erişimi korur. Hiçbir eklenti otomatik kapatılmaz veya silinmez.</div>';
        echo '<div class="mdgv5-grid">';
        echo '<div class="mdgv5-card"><h2>Eski Madagaskar ekranları</h2><ul>';
        $seen = array();
        foreach ( self::$legacy_menu as $item ) {
            if ( ! $item['slug'] || isset( $seen[ $item['slug'] ] ) ) { continue; }
            $seen[ $item['slug'] ] = 1;
            if ( in_array( $item['slug'], array( 'mdg-v5-center','mdg-v5-events','mdg-v5-sales','mdg-v5-finance','mdg-v5-dev','mdgy-marketing','mdgy-crm','mdgy-integrations' ), true ) ) { continue; }
            echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( $item['slug'] ) ) ) . '">' . esc_html( $item['label'] ?: $item['slug'] ) . '</a></li>';
        }
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=mdg-dashboard&legacy=1' ) ) . '">Eski Genel Bakış</a></li>';
        echo '</ul></div>';

        echo '<div class="mdgv5-card"><h2>V4 motor ekranları</h2><ul>';
        if ( self::$hidden_v4 ) {
            foreach ( self::$hidden_v4 as $item ) {
                if ( ! $item['slug'] ) { continue; }
                echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( $item['slug'] ) ) ) . '">' . esc_html( $item['label'] ?: $item['slug'] ) . '</a></li>';
            }
        } else {
            echo '<li>V4 menüsü algılanmadı.</li>';
        }
        echo '</ul></div>';

        echo '<div class="mdgv5-card"><h2>Araçlar / Hotfix</h2><ul>';
        if ( self::$hidden_tools ) {
            foreach ( self::$hidden_tools as $item ) {
                echo '<li><a href="' . esc_url( admin_url( 'tools.php?page=' . rawurlencode( $item['slug'] ) ) ) . '">' . esc_html( $item['label'] ?: $item['slug'] ) . '</a></li>';
            }
        } else {
            echo '<li>Gizlenen Madagaskar aracı yok.</li>';
        }
        echo '</ul></div>';
        echo '</div></div>';
    }
}

MDG_Yonetim_Merkezi_V5::boot();
