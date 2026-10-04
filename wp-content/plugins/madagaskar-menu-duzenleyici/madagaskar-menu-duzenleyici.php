<?php
/**
 * Plugin Name: Madagaskar Yönetim Menü Düzenleyici
 * Description: Madagaskar V3.x geliştirme araçlarını Araçlar menüsünden kaldırır; mevcut Madagaskar menüsünü bozmadan geliştirici araçlarını tek yerde toplar ve yinelenen operasyon menülerini önler. Veri değiştirmez.
 * Version: 1.1.0
 * Author: Madagaskar Sirki
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class MDG_Admin_Menu_Organizer_V11 {
    const CAP = 'manage_options';
    private static $tool_items = array();
    private static $parent_slug = '';
    private static $existing_submenu_labels = array();
    private static $top_menu_labels = array();

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'organize' ), 9999 );
    }

    private static function norm( $text ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = trim( preg_replace( '/\s+/u', ' ', $text ) );
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    private static function find_parent_slug() {
        global $menu;
        if ( ! is_array( $menu ) ) return '';
        foreach ( $menu as $item ) {
            $label = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
            $slug  = isset( $item[2] ) ? $item[2] : '';
            if ( $slug && false !== stripos( $label, 'Madagaskar' ) ) return $slug;
        }
        return '';
    }

    private static function snapshot_menus() {
        global $menu, $submenu;
        self::$top_menu_labels = array();
        if ( is_array($menu) ) {
            foreach ($menu as $row) {
                if ( isset($row[0]) ) self::$top_menu_labels[] = self::norm($row[0]);
            }
        }

        self::$existing_submenu_labels = array();
        if ( self::$parent_slug && ! empty($submenu[self::$parent_slug]) && is_array($submenu[self::$parent_slug]) ) {
            foreach ($submenu[self::$parent_slug] as $row) {
                if ( isset($row[0]) ) self::$existing_submenu_labels[] = self::norm($row[0]);
            }
        }
    }

    private static function has_submenu_label( $label ) {
        return in_array( self::norm($label), self::$existing_submenu_labels, true );
    }

    private static function has_top_menu_label( $label ) {
        return in_array( self::norm($label), self::$top_menu_labels, true );
    }

    private static function collect_mdg_tools() {
        global $submenu;
        $items = array();
        if ( empty( $submenu['tools.php'] ) || ! is_array( $submenu['tools.php'] ) ) return $items;
        foreach ( $submenu['tools.php'] as $row ) {
            $label = isset( $row[0] ) ? wp_strip_all_tags( $row[0] ) : '';
            $cap   = isset( $row[1] ) ? $row[1] : self::CAP;
            $slug  = isset( $row[2] ) ? $row[2] : '';
            if ( $slug && preg_match( '/^Madagaskar\s+V/i', $label ) ) {
                $items[] = array( 'label' => $label, 'cap' => $cap, 'slug' => $slug );
            }
        }
        return $items;
    }

    public static function organize() {
        self::$parent_slug = self::find_parent_slug();
        self::$tool_items  = self::collect_mdg_tools();

        foreach ( self::$tool_items as $item ) {
            remove_submenu_page( 'tools.php', $item['slug'] );
        }

        if ( ! self::$parent_slug ) return;
        self::snapshot_menus();

        // Mevcut Madagaskar menüsünde aynı isim varsa ikinci kez oluşturma.
        if ( ! self::has_submenu_label('Yönetim Merkezi') ) {
            add_submenu_page( self::$parent_slug, 'Madagaskar Yönetim Merkezi', 'Yönetim Merkezi', self::CAP, 'mdg-yonetim-merkezi', array( __CLASS__, 'render_dashboard' ) );
        }

        // Mevcut sistemde zaten İptal / Erteleme varsa dokunma, kopya oluşturma.
        if ( ! self::has_submenu_label('İptal / Erteleme') ) {
            add_submenu_page( self::$parent_slug, 'İptal / Erteleme', 'İptal / Erteleme', self::CAP, 'mdg-iptal-erteleme', array( __CLASS__, 'render_cancel_postpone' ) );
        }

        // WooCommerce/ödeme eklentisi zaten üst düzey "Ödemeler" menüsü sağlıyorsa Madagaskar altında tekrar etme.
        if ( ! self::has_top_menu_label('Ödemeler') && ! self::has_submenu_label('Ödemeler') ) {
            add_submenu_page( self::$parent_slug, 'Ödemeler', 'Ödemeler', self::CAP, 'mdg-odemeler', array( __CLASS__, 'render_payments' ) );
        }

        if ( ! self::has_submenu_label('İadeler') ) {
            add_submenu_page( self::$parent_slug, 'İadeler', 'İadeler', self::CAP, 'mdg-iadeler', array( __CLASS__, 'render_refunds' ) );
        }

        if ( ! self::has_submenu_label('Geliştirici Araçları') ) {
            add_submenu_page( self::$parent_slug, 'Geliştirici Araçları', 'Geliştirici Araçları', self::CAP, 'mdg-gelistirici-araclari', array( __CLASS__, 'render_devtools' ) );
        }
    }

    private static function tool_url( $slug ) {
        return admin_url( 'tools.php?page=' . rawurlencode( $slug ) );
    }

    private static function group_tools( $regex ) {
        $out = array();
        foreach ( self::$tool_items as $item ) {
            if ( preg_match( $regex, $item['label'] ) ) $out[] = $item;
        }
        return $out;
    }

    private static function cards_css() {
        echo '<style>.mdg-wrap{max-width:1100px}.mdg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:18px}.mdg-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px}.mdg-card h2{margin-top:0}.mdg-list{margin:0}.mdg-list li{margin:9px 0}.mdg-note{background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:14px 0}.mdg-safe{background:#edfaef;border-left:4px solid #00a32a;padding:12px 14px;margin:14px 0}</style>';
    }

    public static function render_dashboard() {
        self::cards_css();
        echo '<div class="wrap mdg-wrap"><h1>Madagaskar Yönetim Merkezi</h1><div class="mdg-safe"><strong>Bu eklenti yalnızca yönetim menüsünü düzenler.</strong> Etkinlik, ürün, bilet, sipariş, ödeme veya stok verisini değiştirmez.</div>';
        echo '<div class="mdg-grid">';
        echo '<div class="mdg-card"><h2>İptal / Erteleme</h2><p>Mevcut operasyon menünüz korunur. V3.x geliştirme ekranları Geliştirici Araçları altında tutulur.</p></div>';
        echo '<div class="mdg-card"><h2>Ödemeler</h2><p>Mevcut üst düzey Ödemeler menüsü korunur; Madagaskar altında ikinci bir kopyası oluşturulmaz.</p></div>';
        echo '<div class="mdg-card"><h2>İadeler</h2><p>İade kuyruğu ve ilgili güvenlik araçları için merkez.</p><a class="button" href="'.esc_url(admin_url('admin.php?page=mdg-iadeler')).'">Aç</a></div>';
        echo '<div class="mdg-card"><h2>Geliştirici Araçları</h2><p>V3.x test ve güvenlik ekranları Araçlar menüsünden kaldırılıp burada listelenir.</p><a class="button" href="'.esc_url(admin_url('admin.php?page=mdg-gelistirici-araclari')).'">Aç</a></div>';
        echo '</div></div>';
    }

    private static function render_tool_list( $title, $items, $empty = 'Bu grupta araç bulunamadı.' ) {
        echo '<div class="mdg-card"><h2>'.esc_html($title).'</h2>';
        if ( empty($items) ) { echo '<p>'.esc_html($empty).'</p></div>'; return; }
        echo '<ul class="mdg-list">';
        foreach ( $items as $item ) {
            echo '<li><a href="'.esc_url(self::tool_url($item['slug'])).'">'.esc_html($item['label']).'</a></li>';
        }
        echo '</ul></div>';
    }

    public static function render_cancel_postpone() {
        self::cards_css();
        $cancel = self::group_tools('/İptal|Satış Kapat|Yetkiler|İptal Onayı/i');
        $postpone = self::group_tools('/Erteleme|Seans Eşleme|Hedef Taslak|Tarih Güvenliği|Varyasyon Onarım/i');
        echo '<div class="wrap mdg-wrap"><h1>İptal / Erteleme</h1><div class="mdg-note">Bu sayfa yalnızca mevcut Madagaskar menüsünde ayrı bir İptal / Erteleme ekranı yoksa görünür.</div><div class="mdg-grid">';
        self::render_tool_list('İptal İşlemleri', $cancel);
        self::render_tool_list('Erteleme İşlemleri', $postpone);
        echo '</div></div>';
    }

    public static function render_payments() {
        self::cards_css();
        echo '<div class="wrap mdg-wrap"><h1>Ödemeler</h1><div class="mdg-grid"><div class="mdg-card"><h2>WooCommerce Siparişleri</h2><p>Ödeme durumu ve sipariş kayıtlarını görüntüleyin.</p></div></div></div>';
    }

    public static function render_refunds() {
        self::cards_css();
        $refunds = self::group_tools('/İade/i');
        echo '<div class="wrap mdg-wrap"><h1>İadeler</h1><div class="mdg-grid">';
        self::render_tool_list('İade Araçları', $refunds);
        echo '</div></div>';
    }

    public static function render_devtools() {
        self::cards_css();
        echo '<div class="wrap mdg-wrap"><h1>Geliştirici Araçları</h1><div class="mdg-note">Bu sayfadaki V3.x araçları geliştirme/test içindir. Araçlar menüsünde görünmezler; işlevleri değişmez.</div><div class="mdg-card"><ul class="mdg-list">';
        if ( empty(self::$tool_items) ) echo '<li>Madagaskar V3.x aracı bulunamadı.</li>';
        foreach ( self::$tool_items as $item ) echo '<li><a href="'.esc_url(self::tool_url($item['slug'])).'">'.esc_html($item['label']).'</a></li>';
        echo '</ul></div></div>';
    }
}
MDG_Admin_Menu_Organizer_V11::init();
