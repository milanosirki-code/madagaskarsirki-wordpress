<?php
/**
 * Plugin Name: Madagaskar Bilet Yönetimi V4.0
 * Description: Madagaskar Sirki için WooCommerce + PayTR + Tickera üzerine kurulu tek yönetim merkezi. Satış kapatma, erteleme/aktarım, Biletlerim, iki kişili iptal/tam iade + iade sonrası ticket uzlaştırma ve geçiş envanterini güvenli biçimde tek yerde yönetir.
 * Version: 4.0.14-transition
 * Author: Madagaskar Sirki / Dünya Organizasyon
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 9.0
 * Text Domain: madagaskar-v4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Bilet_Yonetimi_V4 {
    const VERSION = '4.0.14-transition';
    const MENU_SLUG = 'madagaskar-v4';
    const CAP = 'manage_woocommerce';
    const MAP_POST_TYPE = 'mdg_postpone_map';
    const TRANSFER_POST_TYPE = 'mdg_v4_transfer';
    const REFUND_POST_TYPE = 'mdg_v4_refund_case';
    const SALES_META = '_mdg_v371_sales_closed';
    const INVALID_META = '_mdg_invalidated';
    const INVALID_REASON_META = '_mdg_invalidated_reason';
    const LEGACY_MAPPING_STATE = '_mdg_v387_mapping_state';
    const LEGACY_MAPPING_PAYLOAD = '_mdg_v387_mapping_payload';
    const LEGACY_MAPPING_HASH = '_mdg_v387_mapping_hash';
    const LEGACY_TRANSFER_FLAG = '_mdg_v387_ticket_transfer_performed';
    const BILETLERIM_PAGE = 'biletlerim';
    const WA_URL = 'https://wa.me/903129113710';
    const TEST_TICKET_META = '_mdg_v4_test_ticket';
    const TEST_MAP_META = '_mdg_v4_test_map_id';
    const TEST_CREATED_META = '_mdg_v4_test_created_utc';

    private static $instance = null;

    public static function boot() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ], 5 );
        add_action( 'admin_menu', [ $this, 'admin_menu' ], 90 );
        add_action( 'admin_menu', [ $this, 'hide_legacy_dev_menus' ], 99999 );
        add_action( 'admin_notices', [ $this, 'dependency_notice' ] );

        add_action( 'admin_post_mdg_v4_sales_toggle', [ $this, 'handle_sales_toggle' ] );
        add_action( 'admin_post_mdg_v4_transfer_execute', [ $this, 'handle_transfer_execute' ] );
        add_action( 'admin_post_mdg_v4_transfer_rollback', [ $this, 'handle_transfer_rollback' ] );
        add_action( 'admin_post_mdg_v4_test_ticket_create', [ $this, 'handle_test_ticket_create' ] );
        add_action( 'admin_post_mdg_v4_test_ticket_delete', [ $this, 'handle_test_ticket_delete' ] );
        add_action( 'admin_post_mdg_v4_postpone_create', [ $this, 'handle_postpone_create' ] );
        add_action( 'admin_post_mdg_v4_postpone_cleanup', [ $this, 'handle_postpone_cleanup' ] );
        add_action( 'admin_post_mdg_v4_refund_request', [ $this, 'handle_refund_request' ] );
        add_action( 'admin_post_mdg_v4_refund_approve', [ $this, 'handle_refund_approve' ] );
        add_action( 'admin_post_mdg_v4_refund_execute', [ $this, 'handle_refund_execute' ] );
        add_action( 'admin_post_mdg_v4_refund_reconcile', [ $this, 'handle_refund_reconcile' ] );
        add_action( 'admin_post_mdg_v4_refund_cancel_case', [ $this, 'handle_refund_cancel_case' ] );
        add_action( 'admin_post_mdg_v4_deactivate_diagnostics', [ $this, 'handle_deactivate_diagnostics' ] );

        add_action( 'template_redirect', [ $this, 'maybe_render_biletlerim' ], 0 );
        add_action( 'init', [ $this, 'register_legacy_biletlerim_compat' ], 999 );

        // V4 owns the legacy reversible sales-close gate during migration.
        // Keeping the same meta key makes this safe while V3.7.1 is still active; duplicate false decisions are idempotent.
        add_filter( 'woocommerce_is_purchasable', [ $this, 'filter_sales_closed_purchasable' ], 99, 2 );
        add_filter( 'woocommerce_variation_is_purchasable', [ $this, 'filter_sales_closed_purchasable' ], 99, 2 );

        if ( ! function_exists( 'mdg_v4_biletlerim_url' ) ) {
            function mdg_v4_biletlerim_url( $order_id ) {
                return MDG_Bilet_Yonetimi_V4::boot()->biletlerim_url( $order_id );
            }
        }
    }

    public function register_post_types() {
        if ( ! post_type_exists( self::MAP_POST_TYPE ) ) {
            register_post_type( self::MAP_POST_TYPE, [
                'labels' => [ 'name' => 'Madagaskar Erteleme Eşlemeleri', 'singular_name' => 'Erteleme Eşlemesi' ],
                'public' => false,
                'show_ui' => false,
                'show_in_menu' => false,
                'supports' => [ 'title' ],
                'map_meta_cap' => true,
            ] );
        }

        register_post_type( self::TRANSFER_POST_TYPE, [
            'labels' => [ 'name' => 'Madagaskar Aktarım Kayıtları', 'singular_name' => 'Aktarım Kaydı' ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => [ 'title' ],
            'map_meta_cap' => true,
        ] );

        register_post_type( self::REFUND_POST_TYPE, [
            'labels' => [ 'name' => 'Madagaskar İade Kayıtları', 'singular_name' => 'İade Kaydı' ],
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => [ 'title' ],
            'map_meta_cap' => true,
        ] );
    }

    private function cap() {
        return current_user_can( self::CAP ) || current_user_can( 'manage_options' );
    }

    private function require_cap() {
        if ( ! $this->cap() ) wp_die( 'Bu işlemi yapma yetkiniz yok.' );
    }

    public function admin_menu() {
        add_menu_page(
            'Madagaskar Bilet Yönetimi V4',
            'Madagaskar V4',
            self::CAP,
            self::MENU_SLUG,
            [ $this, 'render_dashboard' ],
            'dashicons-tickets-alt',
            56
        );

        add_submenu_page( self::MENU_SLUG, 'Genel Bakış', 'Genel Bakış', self::CAP, self::MENU_SLUG, [ $this, 'render_dashboard' ] );
        add_submenu_page( self::MENU_SLUG, 'Satış Yönetimi', 'Satış Yönetimi', self::CAP, 'mdg-v4-sales', [ $this, 'render_sales' ] );
        add_submenu_page( self::MENU_SLUG, 'Erteleme / Aktarım', 'Erteleme / Aktarım', self::CAP, 'mdg-v4-postpone', [ $this, 'render_postpone' ] );
        add_submenu_page( self::MENU_SLUG, 'Biletlerim', 'Biletlerim', self::CAP, 'mdg-v4-biletlerim', [ $this, 'render_biletlerim_admin' ] );
        add_submenu_page( self::MENU_SLUG, 'İptal / İade', 'İptal / İade', self::CAP, 'mdg-v4-refund', [ $this, 'render_refund_admin' ] );
        add_submenu_page( self::MENU_SLUG, 'Entegrasyonlar', 'Entegrasyonlar', self::CAP, 'mdg-v4-integrations', [ $this, 'render_integrations' ] );
        add_submenu_page( self::MENU_SLUG, 'Geçiş Merkezi', 'Geçiş Merkezi', self::CAP, 'mdg-v4-migration', [ $this, 'render_migration' ] );
        add_submenu_page( self::MENU_SLUG, '3.6.3 Emeklilik Denetimi', '3.6.3 Emeklilik', self::CAP, 'mdg-v4-retirement-audit', [ $this, 'render_retirement_audit' ] );
    }

    public function hide_legacy_dev_menus() {
        global $menu, $submenu;
        if ( ! is_admin() ) return;

        $regex = '/(?:V3\.|V3\.8|V3\.9|Canary|Dry-Run|Denetim|Trace|Runtime|Snapshot|Preflight|Kök Neden|Önizleme|Gate|Callback|İzleme)/iu';

        if ( ! empty( $submenu['tools.php'] ) && is_array( $submenu['tools.php'] ) ) {
            foreach ( $submenu['tools.php'] as $row ) {
                $label = isset( $row[0] ) ? wp_strip_all_tags( $row[0] ) : '';
                $slug  = isset( $row[2] ) ? $row[2] : '';
                if ( $slug && false !== stripos( $label, 'Madagaskar' ) && preg_match( $regex, $label ) ) {
                    remove_submenu_page( 'tools.php', $slug );
                }
            }
        }

        if ( is_array( $submenu ) ) {
            foreach ( array_keys( $submenu ) as $parent ) {
                if ( $parent === self::MENU_SLUG || empty( $submenu[$parent] ) || ! is_array( $submenu[$parent] ) ) continue;
                foreach ( $submenu[$parent] as $row ) {
                    $label = isset( $row[0] ) ? wp_strip_all_tags( $row[0] ) : '';
                    $slug  = isset( $row[2] ) ? $row[2] : '';
                    if ( $slug && false !== stripos( $label, 'Madagaskar' ) && preg_match( $regex, $label ) ) {
                        remove_submenu_page( $parent, $slug );
                    }
                }
            }
        }
    }

    private function styles() {
        echo '<style>
        .mdgv4{max-width:1400px}.mdgv4-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:18px 0}.mdgv4-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px}.mdgv4-card h2,.mdgv4-card h3{margin-top:0}.mdgv4-ok{color:#16803a;font-weight:700}.mdgv4-bad{color:#b42318;font-weight:700}.mdgv4-warn{color:#996800;font-weight:700}.mdgv4-note{border-left:4px solid #2271b1;background:#f0f6fc;padding:13px 15px;margin:14px 0}.mdgv4-safe{border-left:4px solid #16803a;background:#f2fbf5;padding:13px 15px;margin:14px 0}.mdgv4-danger{border-left:4px solid #b42318;background:#fff0f0;padding:13px 15px;margin:14px 0}.mdgv4-table code{white-space:nowrap}.mdgv4-actions{display:flex;gap:8px;flex-wrap:wrap}.mdgv4-kpi{font-size:32px;font-weight:700;line-height:1}.mdgv4-small{color:#646970;font-size:12px}.mdgv4-code{font-family:monospace;background:#f6f7f7;padding:2px 5px;border-radius:3px}
        </style>';
    }

    private function yesno( $ok, $yes = 'Evet', $no = 'Hayır' ) {
        return $ok ? '<span class="mdgv4-ok">'.esc_html($yes).'</span>' : '<span class="mdgv4-bad">'.esc_html($no).'</span>';
    }

    private function deps() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        return [
            'woocommerce' => class_exists( 'WooCommerce' ) || function_exists( 'WC' ),
            'tickera' => class_exists( 'TC' ) || post_type_exists( 'tc_events' ) || post_type_exists( 'tc_tickets_instances' ),
            'tickera_download' => function_exists( 'tickera_get_ticket_download_link' ) || function_exists( 'tickera_get_raw_ticket_download_link' ),
            'biletlerim_legacy' => function_exists( 'ms_biletlerim_url' ),
            'paytr' => $this->plugin_name_active_like( 'PayTR' ),
            'bridge' => $this->plugin_name_active_like( 'Bridge for WooCommerce' ) || $this->plugin_name_active_like( 'Tickera Bridge for WooCommerce' ),
            'checkinera' => $this->plugin_name_active_like( 'Checkinera' ),
        ];
    }

    private function plugin_name_active_like( $needle ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugins = get_plugins();
        foreach ( $plugins as $file => $data ) {
            if ( false !== stripos( $data['Name'] ?? '', $needle ) && is_plugin_active( $file ) ) return true;
        }
        return false;
    }

    public function dependency_notice() {
        if ( ! $this->cap() ) return;
        $d = $this->deps();
        if ( $d['woocommerce'] && $d['tickera'] && $d['bridge'] ) return;
        echo '<div class="notice notice-warning"><p><strong>Madagaskar V4:</strong> WooCommerce + Tickera + Tickera Bridge çekirdeğinden en az biri algılanamadı. V4 geçiş ekranları açılır; ancak üretim işlemlerini çalıştırmayın.</p></div>';
    }

    public function render_dashboard() {
        $this->require_cap(); $this->styles();
        $d = $this->deps();
        $legacy = $this->legacy_plugins();
        $maps = $this->mapping_records();
        $last = $this->last_transfer();

        echo '<div class="wrap mdgv4"><h1>Madagaskar Bilet Yönetimi V4.0</h1>';
        echo '<div class="mdgv4-safe"><strong>Geçiş modu:</strong> V4 eski V3.x eklentilerini otomatik silmez veya kapatmaz. Geliştirme/test menülerini gizler, üretim işlevlerini tek merkezde toplamaya başlar. Doğrulama tamamlanana kadar eski çekirdek eklentileri aktif bırakın.</div>';
        echo '<div class="mdgv4-grid">';
        $this->kpi_card( 'Aktif eski Madagaskar eklentisi', count( array_filter( $legacy, fn($x)=>$x['active'] ) ), 'Geçiş Merkezi' );
        $this->kpi_card( 'Tanı/test eklentisi', count( array_filter( $legacy, fn($x)=>$x['category']==='diagnostic' && $x['active'] ) ), 'Kapatılmaya aday' );
        $this->kpi_card( 'Kilitli erteleme eşlemesi', count( $maps ), 'Mevcut V3.8.7 kayıtları okunur' );
        $this->kpi_card( 'Son aktarım', $last ? '#'.$last->ID : '—', $last ? get_post_meta($last->ID,'_mdg_v4_state',true) : 'Henüz V4 aktarımı yok' );
        echo '</div>';

        echo '<div class="mdgv4-card"><h2>Çekirdek durum</h2><table class="widefat striped mdgv4-table"><tbody>';
        $checks = [
            'WooCommerce' => $d['woocommerce'],
            'Tickera' => $d['tickera'],
            'Tickera Bridge for WooCommerce' => $d['bridge'],
            'Tickera PDF/Download helper' => $d['tickera_download'],
            'PayTR' => $d['paytr'],
            'Checkinera' => $d['checkinera'],
            'Mevcut Biletlerim helper (geçiş uyumluluğu)' => $d['biletlerim_legacy'],
        ];
        foreach ( $checks as $label=>$ok ) echo '<tr><td>'.esc_html($label).'</td><td>'.$this->yesno($ok).'</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-grid">';
        echo '<div class="mdgv4-card"><h2>Satış Yönetimi</h2><p>Eski <code>_mdg_v371_sales_closed</code> kilidini tek ekrandan kontrol eder.</p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=mdg-v4-sales')).'">Aç</a></div>';
        echo '<div class="mdgv4-card"><h2>Erteleme / Aktarım</h2><p>Mevcut V3.8.7 eşlemelerini okur; bilet aktarımını snapshot + otomatik rollback ile çalıştırır.</p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=mdg-v4-postpone')).'">Aç</a></div>';
        echo '<div class="mdgv4-card"><h2>Biletlerim</h2><p>Mevcut veya V4 güvenli linkini test eder; Tickera PDF bağlantısını doğrudan kullanır.</p><a class="button" href="'.esc_url(admin_url('admin.php?page=mdg-v4-biletlerim')).'">Aç</a></div>';
        echo '<div class="mdgv4-card"><h2>İptal / İade</h2><p>Tam iadeyi önce dry-run ile denetler; iki farklı yönetici onayı ve değişmez snapshot ile gerçek iadeyi kilitler.</p><a class="button" href="'.esc_url(admin_url('admin.php?page=mdg-v4-refund')).'">Aç</a></div>';
        echo '<div class="mdgv4-card"><h2>Geçiş Merkezi</h2><p>Eski eklentileri KALACAK / V4&#39;A TAŞINACAK / KAPATILACAK olarak sınıflandırır.</p><a class="button" href="'.esc_url(admin_url('admin.php?page=mdg-v4-migration')).'">Aç</a></div>';
        echo '</div></div>';
    }

    private function kpi_card( $title, $value, $note ) {
        echo '<div class="mdgv4-card"><div class="mdgv4-kpi">'.esc_html($value).'</div><h3>'.esc_html($title).'</h3><div class="mdgv4-small">'.esc_html($note).'</div></div>';
    }

    private function sales_closed_for_product( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return false;
        $id = absint( $product->get_id() );
        if ( $id && 'yes' === get_post_meta( $id, self::SALES_META, true ) ) return true;
        $parent_id = absint( $product->get_parent_id() );
        if ( $parent_id && 'yes' === get_post_meta( $parent_id, self::SALES_META, true ) ) return true;
        return false;
    }

    public function filter_sales_closed_purchasable( $purchasable, $product ) {
        if ( $this->sales_closed_for_product( $product ) ) return false;
        return $purchasable;
    }

    /* -------------------- SALES -------------------- */

    public function render_sales() {
        $this->require_cap(); $this->styles();
        $product_id = absint( $_GET['product_id'] ?? 1709 );
        $p = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
        $closed = $p ? ( 'yes' === get_post_meta( $product_id, self::SALES_META, true ) ) : false;
        $children = $p && $p->is_type('variable') ? $p->get_children() : [];

        echo '<div class="wrap mdgv4"><h1>Satış Yönetimi</h1>';
        echo '<div class="mdgv4-note"><strong>Tek kaynak:</strong> Geçiş boyunca mevcut V3.7.1 ile uyumlu <code>'.esc_html(self::SALES_META).'</code> metası kullanılır. Böylece eski satış kapatma davranışı bozulmaz.</div>';
        echo '<div class="mdgv4-card"><form method="get"><input type="hidden" name="page" value="mdg-v4-sales"><label><strong>WooCommerce ana ürün ID</strong><br><input type="number" name="product_id" min="1" value="'.esc_attr($product_id).'" style="width:220px"></label> '; submit_button('Kontrol Et','secondary','',false); echo '</form></div>';

        if ( ! $p ) {
            echo '<div class="mdgv4-danger">Ürün #'.esc_html($product_id).' bulunamadı.</div></div>'; return;
        }

        echo '<div class="mdgv4-card"><h2>#'.esc_html($product_id).' — '.esc_html($p->get_name()).'</h2><table class="widefat striped"><tbody>';
        echo '<tr><td>Tür</td><td><code>'.esc_html($p->get_type()).'</code></td></tr>';
        echo '<tr><td>WooCommerce durum</td><td><code>'.esc_html(get_post_status($product_id)).'</code></td></tr>';
        echo '<tr><td>Stok</td><td>'.$this->yesno($p->is_in_stock()).'</td></tr>';
        echo '<tr><td>V3.7.1 satış kilidi</td><td>'.($closed?'<span class="mdgv4-bad">KAPALI</span>':'<span class="mdgv4-ok">AÇIK</span>').'</td></tr>';
        $v4_gate_hooked = ( false !== has_filter( 'woocommerce_is_purchasable', [ $this, 'filter_sales_closed_purchasable' ] ) ) && ( false !== has_filter( 'woocommerce_variation_is_purchasable', [ $this, 'filter_sales_closed_purchasable' ] ) );
        echo '<tr><td>V4 satış kapısı callback</td><td>'.$this->yesno($v4_gate_hooked).'</td></tr>';
        echo '<tr><td>V4 bağımsız kilit kararı</td><td>'.($this->sales_closed_for_product($p)?'<span class="mdgv4-bad">SATIŞ KAPALI</span>':'<span class="mdgv4-ok">SATIŞ AÇIK</span>').'</td></tr>';
        echo '<tr><td>Varyasyon sayısı</td><td>'.count($children).'</td></tr>';
        echo '</tbody></table>';

        echo '<h3>Varyasyonlar</h3><table class="widefat striped"><thead><tr><th>ID</th><th>Ad</th><th>Fiyat</th><th>Stok</th><th>Kendi satış kilidi</th></tr></thead><tbody>';
        foreach ( $children as $cid ) {
            $v=wc_get_product($cid); if(!$v) continue;
            echo '<tr><td>#'.esc_html($cid).'</td><td>'.esc_html($v->get_name()).'</td><td>'.wp_kses_post(wc_price($v->get_price())).'</td><td>'.$this->yesno($v->is_in_stock()).'</td><td>'.esc_html((string)get_post_meta($cid,self::SALES_META,true) ?: '[boş]').'</td></tr>';
        }
        echo '</tbody></table>';

        echo '<div class="mdgv4-actions" style="margin-top:18px">';
        $action = admin_url('admin-post.php');
        echo '<form method="post" action="'.esc_url($action).'">'; wp_nonce_field('mdg_v4_sales_toggle_'.$product_id); echo '<input type="hidden" name="action" value="mdg_v4_sales_toggle"><input type="hidden" name="product_id" value="'.esc_attr($product_id).'"><input type="hidden" name="mode" value="close"><input type="hidden" name="confirm" value="SATISI KAPAT">'; submit_button('SATIŞI KAPAT','secondary','',false); echo '</form>';
        echo '<form method="post" action="'.esc_url($action).'" onsubmit="return confirm(\'Bu ürünün satış kilidi kaldırılacak. Emin misiniz?\')">'; wp_nonce_field('mdg_v4_sales_toggle_'.$product_id); echo '<input type="hidden" name="action" value="mdg_v4_sales_toggle"><input type="hidden" name="product_id" value="'.esc_attr($product_id).'"><input type="hidden" name="mode" value="open"><input type="hidden" name="confirm" value="SATISI AC">'; submit_button('SATIŞI AÇ','primary','',false); echo '</form>';
        echo '</div></div></div>';
    }

    public function handle_sales_toggle() {
        $this->require_cap();
        $product_id = absint($_POST['product_id'] ?? 0);
        check_admin_referer('mdg_v4_sales_toggle_'.$product_id);
        $mode = sanitize_key($_POST['mode'] ?? '');
        $confirm = strtoupper(trim(sanitize_text_field(wp_unslash($_POST['confirm'] ?? ''))));
        if ( ! $product_id || ! function_exists('wc_get_product') || ! wc_get_product($product_id) ) wp_die('Ürün bulunamadı.');

        if ( 'close' === $mode && 'SATISI KAPAT' === $confirm ) {
            update_post_meta($product_id,self::SALES_META,'yes');
        } elseif ( 'open' === $mode && 'SATISI AC' === $confirm ) {
            delete_post_meta($product_id,self::SALES_META);
            $p=wc_get_product($product_id);
            if($p && $p->is_type('variable')) foreach($p->get_children() as $cid) delete_post_meta($cid,self::SALES_META);
        } else wp_die('Onay geçersiz.');

        clean_post_cache($product_id);
        wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-sales','product_id'=>$product_id,'updated'=>1],admin_url('admin.php'))); exit;
    }

    /* -------------------- MAPPINGS / TRANSFER -------------------- */

    private function mapping_records() {
        return get_posts([
            'post_type'=>self::MAP_POST_TYPE,
            'post_status'=>['private','publish','draft'],
            'posts_per_page'=>100,
            'orderby'=>'ID','order'=>'DESC',
            'meta_key'=>self::LEGACY_MAPPING_STATE,
            'meta_value'=>'locked',
        ]);
    }

    private function parse_mapping( $map_id ) {
        $p = get_post($map_id);
        if(!$p || $p->post_type!==self::MAP_POST_TYPE) return ['ok'=>false,'reason'=>'Eşleme kaydı bulunamadı.'];
        $state=(string)get_post_meta($map_id,self::LEGACY_MAPPING_STATE,true);
        $json=(string)get_post_meta($map_id,self::LEGACY_MAPPING_PAYLOAD,true);
        $hash=(string)get_post_meta($map_id,self::LEGACY_MAPPING_HASH,true);
        $payload=json_decode($json,true);
        $calc=$json?hash('sha256',$json):'';
        if($state!=='locked') return ['ok'=>false,'reason'=>'Eşleme locked değil.'];
        if(!$json || !is_array($payload)) return ['ok'=>false,'reason'=>'Eşleme payload okunamadı.'];
        if(!$hash || !hash_equals($hash,$calc)) return ['ok'=>false,'reason'=>'Eşleme SHA-256 bütünlüğü geçmedi.'];
        if(($payload['schema']??'')!=='mdg-postponement-mapping-v1') return ['ok'=>false,'reason'=>'Eşleme şeması desteklenmiyor.'];
        $source=absint($payload['source_event_id']??0); $target=absint($payload['target_event_id']??0);
        if(!$source||!$target||$source===$target) return ['ok'=>false,'reason'=>'Kaynak/hedef kimlikleri geçersiz.'];
        $vmap=[];
        foreach((array)($payload['variations']??[]) as $v){
            $sv=absint($v['source_variation_id']??0); $tv=absint($v['target_variation_id']??0);
            if($sv&&$tv) $vmap[$sv]=['target'=>$tv,'time'=>sanitize_text_field($v['time']??''),'label'=>sanitize_text_field($v['label']??''),'price'=>(float)($v['price']??0)];
        }
        if(!$vmap) return ['ok'=>false,'reason'=>'Varyasyon eşleme tablosu boş.'];
        return ['ok'=>true,'post'=>$p,'payload'=>$payload,'source'=>$source,'target'=>$target,'vmap'=>$vmap,'hash'=>$hash,'transferred'=>(get_post_meta($map_id,self::LEGACY_TRANSFER_FLAG,true)==='yes')];
    }

    private function source_ticket_ids( $source_event_id ) {
        global $wpdb;
        $sql=$wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID WHERE p.post_type=%s AND pm.meta_key=%s AND pm.meta_value=%s ORDER BY p.ID ASC",'tc_tickets_instances','event_id',(string)$source_event_id);
        return array_map('intval',(array)$wpdb->get_col($sql));
    }

    private function protected_ticket_meta_hash( $ticket_id ) {
        $meta = get_post_meta( $ticket_id );
        if ( ! is_array( $meta ) ) return '';
        // Aktarımın değiştirmesine izin verilen iki alan hash dışında tutulur.
        unset( $meta['event_id'], $meta['ticket_type_id'] );
        ksort( $meta );
        foreach ( $meta as $key => $vals ) {
            if ( is_array( $vals ) ) {
                $vals = array_map( 'strval', $vals );
                sort( $vals, SORT_STRING );
                $meta[$key] = $vals;
            }
        }
        return hash( 'sha256', wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    private function ticket_state( $ticket_id ) {
        $p=get_post($ticket_id); if(!$p) return ['exists'=>false];
        $order=function_exists('wc_get_order')?wc_get_order((int)$p->post_parent):false;
        $ticket_code=(string)get_post_meta($ticket_id,'ticket_code',true);
        return [
            'exists'=>true,'ticket_id'=>(int)$ticket_id,'post_type'=>(string)$p->post_type,'post_status'=>(string)$p->post_status,'post_parent'=>(int)$p->post_parent,
            'event_id'=>(int)get_post_meta($ticket_id,'event_id',true),'ticket_type_id'=>(int)get_post_meta($ticket_id,'ticket_type_id',true),
            'ticket_code_hash'=>$ticket_code?hash('sha256',$ticket_code):'', 'protected_meta_hash'=>$this->protected_ticket_meta_hash($ticket_id), 'invalidated'=>(string)get_post_meta($ticket_id,self::INVALID_META,true), 'invalid_reason'=>(string)get_post_meta($ticket_id,self::INVALID_REASON_META,true),
            'order_exists'=>(bool)$order,'order_status'=>$order?(string)$order->get_status():'','order_paid'=>$order?(bool)$order->is_paid():false,'order_total'=>$order?(float)$order->get_total():0.0,
            'payment_method'=>$order?(string)$order->get_payment_method():'','order_key_hash'=>$order?(string)hash('sha256',(string)$order->get_order_key()):'',
            'is_v4_test'=>(get_post_meta($ticket_id,self::TEST_TICKET_META,true)==='yes'),
        ];
    }

    private function target_variation_ok( $target_var, $expected_price ) {
        if(!function_exists('wc_get_product')) return false;
        $v=wc_get_product($target_var); if(!$v || !$v->is_type('variation')) return false;
        if($v->get_parent_id()<=0) return false;
        if(abs((float)$v->get_price()-(float)$expected_price)>0.01) return false;
        return true;
    }

    private function scan_mapping( $map_id ) {
        $m=$this->parse_mapping($map_id);
        if(!$m['ok']) return ['mapping'=>$m,'rows'=>[],'summary'=>['total'=>0,'eligible'=>0,'excluded'=>0,'locked'=>0,'orders'=>0]];
        $rows=[]; $orders=[]; $sum=['total'=>0,'eligible'=>0,'excluded'=>0,'locked'=>0,'orders'=>0];
        foreach($this->source_ticket_ids($m['source']) as $tid){
            $s=$this->ticket_state($tid); $sum['total']++;
            $vm=$m['vmap'][$s['ticket_type_id']]??null;
            $reasons=[]; $decision='AKTARILABİLİR';
            if(!$vm){$decision='KİLİT';$reasons[]='Kaynak varyasyon eşleme tablosunda yok';}
            if($s['post_type']!=='tc_tickets_instances'){$decision='AKTARIM DIŞI';$reasons[]='post_type farklı';}
            if($s['post_status']!=='publish'){$decision='AKTARIM DIŞI';$reasons[]='ticket status='.$s['post_status'];}
            if($s['event_id']!==$m['source']){$decision='AKTARIM DIŞI';$reasons[]='event_id kaynak değil';}
            $is_test=!empty($s['is_v4_test']);
            if(!$is_test && !$s['order_exists']){$decision='AKTARIM DIŞI';$reasons[]='sipariş yok';}
            if(!$is_test && $s['order_exists'] && in_array($s['order_status'],['refunded','cancelled','failed'],true)){$decision='AKTARIM DIŞI';$reasons[]='sipariş '.$s['order_status'];}
            if(!$is_test && $s['order_exists'] && !$s['order_paid']){$decision='AKTARIM DIŞI';$reasons[]='sipariş ödenmemiş';}
            if($s['invalidated']==='yes'){$decision='AKTARIM DIŞI';$reasons[]='ticket invalidated'.($s['invalid_reason']?':'.$s['invalid_reason']:'');}
            if(!$s['ticket_code_hash']){$decision='AKTARIM DIŞI';$reasons[]='ticket_code yok';}
            if($vm && !$this->target_variation_ok($vm['target'],$vm['price'])){$decision='KİLİT';$reasons[]='hedef varyasyon/fiyat güvenliği geçmedi';}
            if($decision==='AKTARILABİLİR'){$sum['eligible']++;if($s['post_parent']>0)$orders[$s['post_parent']]=true;}elseif($decision==='KİLİT')$sum['locked']++;else $sum['excluded']++;
            $rows[]=$s+['decision'=>$decision,'reason'=>$reasons?implode('; ',$reasons):($is_test?'V4 sentetik test ticketı; sipariş/ödeme kapısı test amacıyla atlandı':'Tüm güvenlik kapıları geçti'),'target_event'=>$m['target'],'target_var'=>$vm['target']??0,'session'=>$vm['time']??'','label'=>$vm['label']??'','price'=>$vm['price']??0];
        }
        $sum['orders']=count($orders);
        return ['mapping'=>$m,'rows'=>$rows,'summary'=>$sum];
    }

    private function test_ticket_ids_for_map( $map_id ) {
        $q = new WP_Query([
            'post_type' => 'tc_tickets_instances',
            'post_status' => [ 'publish', 'private', 'draft', 'trash' ],
            'posts_per_page' => 20,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'DESC',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => self::TEST_TICKET_META, 'value' => 'yes' ],
                [ 'key' => self::TEST_MAP_META, 'value' => (string) absint( $map_id ) ],
            ],
        ]);
        return array_map( 'intval', (array) $q->posts );
    }

    private function unique_test_ticket_code() {
        global $wpdb;
        for ( $i = 0; $i < 12; $i++ ) {
            $code = 'MDGV4TEST-' . strtoupper( wp_generate_password( 18, false, false ) );
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%s LIMIT 1",
                'ticket_code',
                $code
            ) );
            if ( ! $exists ) return $code;
        }
        return 'MDGV4TEST-' . strtoupper( wp_generate_uuid4() );
    }

    private function render_test_ticket_tool( $map_id, $m ) {
        $test_ids = $this->test_ticket_ids_for_map( $map_id );

        echo '<div class="mdgv4-card"><h2>Yönetici Test Ticketı</h2>';
        echo '<div class="mdgv4-note"><strong>Güvenli test:</strong> Bu araç WooCommerce siparişi, PayTR ödemesi, müşteri, ürün veya stok oluşturmaz/değiştirmez. Yalnız <code>tc_tickets_instances</code> içinde açıkça V4 TEST olarak işaretlenmiş sentetik bir ticket oluşturur. Amaç snapshot → aktarım → doğrulama → rollback zincirini gerçek satış açmadan sınamaktır.</div>';

        if ( $test_ids ) {
            echo '<table class="widefat striped"><thead><tr><th>Test ticket</th><th>Durum</th><th>event_id</th><th>ticket_type_id</th><th>Oluşturulma UTC</th></tr></thead><tbody>';
            foreach ( $test_ids as $tid ) {
                $p = get_post( $tid );
                if ( ! $p ) continue;
                echo '<tr><td>#'.esc_html($tid).'</td><td><code>'.esc_html($p->post_status).'</code></td><td>#'.esc_html((int)get_post_meta($tid,'event_id',true)).'</td><td>#'.esc_html((int)get_post_meta($tid,'ticket_type_id',true)).'</td><td>'.esc_html((string)get_post_meta($tid,self::TEST_CREATED_META,true)).'</td></tr>';
            }
            echo '</tbody></table>';

            if ( ! $m['transferred'] ) {
                $tid = (int) $test_ids[0];
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:14px" onsubmit="return confirm(\'V4 test ticketı kalıcı olarak silinecek. Emin misiniz?\')">';
                wp_nonce_field('mdg_v4_test_ticket_delete_'.$tid);
                echo '<input type="hidden" name="action" value="mdg_v4_test_ticket_delete"><input type="hidden" name="ticket_id" value="'.esc_attr($tid).'"><input type="hidden" name="map_id" value="'.esc_attr($map_id).'">';
                submit_button('TEST TICKETINI TEMİZLE','secondary','',false);
                echo '</form>';
            } else {
                echo '<div class="mdgv4-danger" style="margin-top:14px"><strong>Test ticketı hedefe aktarılmış durumda.</strong> Temizlemeden önce V4 aktarım rollback işlemini tamamlayın.</div>';
            }
        } else {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('mdg_v4_test_ticket_create_'.$map_id);
            echo '<input type="hidden" name="action" value="mdg_v4_test_ticket_create"><input type="hidden" name="map_id" value="'.esc_attr($map_id).'">';
            echo '<label><strong>Kaynak seans / bilet tipi</strong><br><select name="source_var">';
            foreach ( $m['vmap'] as $source_var => $vm ) {
                $label = trim( ($vm['time'] ? $vm['time'].' — ' : '') . ($vm['label'] ?: ('Varyasyon #'.$source_var)) );
                echo '<option value="'.esc_attr($source_var).'">#'.esc_html($source_var).' — '.esc_html($label).'</option>';
            }
            echo '</select></label> ';
            submit_button('1 ADET V4 TEST TICKETI OLUŞTUR','secondary','',false);
            echo '</form>';
        }
        echo '</div>';
    }

    public function handle_test_ticket_create() {
        $this->require_cap();
        $map_id = absint( $_POST['map_id'] ?? 0 );
        check_admin_referer( 'mdg_v4_test_ticket_create_'.$map_id );

        $m = $this->parse_mapping( $map_id );
        if ( ! $m['ok'] ) wp_die( 'Eşleme geçersiz: '.esc_html( $m['reason'] ) );
        if ( $m['transferred'] ) wp_die( 'Bu eşleme aktarılmış durumda. Önce rollback yapın.' );
        if ( $this->test_ticket_ids_for_map( $map_id ) ) wp_die( 'Bu eşleme için zaten bir V4 test ticketı var.' );

        $source_var = absint( $_POST['source_var'] ?? 0 );
        if ( ! $source_var || empty( $m['vmap'][$source_var] ) ) wp_die( 'Kaynak varyasyon eşlemede bulunamadı.' );

        $ticket_code = $this->unique_test_ticket_code();
        $ticket_id = wp_insert_post([
            'post_type' => 'tc_tickets_instances',
            'post_status' => 'publish',
            'post_parent' => 0,
            'post_title' => sprintf( 'MDG V4 TEST Ticket | Map #%d | Source #%d/%d', $map_id, $m['source'], $source_var ),
            'post_author' => get_current_user_id(),
            'meta_input' => [
                'event_id' => (string) $m['source'],
                'ticket_type_id' => (string) $source_var,
                'ticket_code' => $ticket_code,
                self::TEST_TICKET_META => 'yes',
                self::TEST_MAP_META => (string) $map_id,
                self::TEST_CREATED_META => gmdate( 'c' ),
            ],
        ], true );

        if ( is_wp_error( $ticket_id ) || ! $ticket_id ) {
            wp_die( is_wp_error( $ticket_id ) ? esc_html( $ticket_id->get_error_message() ) : 'V4 test ticketı oluşturulamadı.' );
        }

        clean_post_cache( $ticket_id );
        wp_safe_redirect( add_query_arg(
            [ 'page' => 'mdg-v4-postpone', 'map_id' => $map_id, 'test_created' => $ticket_id ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    public function handle_test_ticket_delete() {
        $this->require_cap();
        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        $map_id = absint( $_POST['map_id'] ?? 0 );
        check_admin_referer( 'mdg_v4_test_ticket_delete_'.$ticket_id );

        $p = get_post( $ticket_id );
        if ( ! $p || $p->post_type !== 'tc_tickets_instances' ) wp_die( 'Test ticket bulunamadı.' );
        if ( get_post_meta( $ticket_id, self::TEST_TICKET_META, true ) !== 'yes' ) wp_die( 'Bu kayıt V4 test ticketı değil.' );
        if ( absint( get_post_meta( $ticket_id, self::TEST_MAP_META, true ) ) !== $map_id ) wp_die( 'Test ticket / eşleme ilişkisi geçersiz.' );

        $m = $this->parse_mapping( $map_id );
        if ( $m['ok'] && $m['transferred'] ) wp_die( 'Test ticket hedefe aktarılmış durumda. Önce V4 rollback işlemini tamamlayın.' );

        wp_delete_post( $ticket_id, true );
        wp_safe_redirect( add_query_arg(
            [ 'page' => 'mdg-v4-postpone', 'map_id' => $map_id, 'test_deleted' => 1 ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }


    /* -------------------- V4.0.4 NEW POSTPONEMENT BUILDER -------------------- */

    private function postpone_valid_date( $date ) {
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) return false;
        return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
    }

    private function postpone_display_date( $date ) {
        if ( ! $this->postpone_valid_date( $date ) ) return (string) $date;
        list( $y, $m, $d ) = explode( '-', $date );
        return $d . '.' . $m . '.' . $y;
    }

    private function postpone_lower_tr( $text ) {
        $text = strtr( (string) $text, [ 'İ'=>'i','I'=>'ı','Ş'=>'ş','Ğ'=>'ğ','Ü'=>'ü','Ö'=>'ö','Ç'=>'ç' ] );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
    }

    private function postpone_month_tr( $month ) {
        $months = [ 1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık' ];
        return $months[ (int) $month ] ?? '';
    }

    private function postpone_time_from_title( $title ) {
        if ( preg_match( '/(?:^|\D)([01]?\d|2[0-3])[:.]([0-5]\d)(?:\D|$)/u', (string) $title, $m ) ) {
            return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
        }
        return '';
    }

    private function postpone_date_from_title( $title ) {
        $title = trim( (string) $title );
        if ( preg_match( '/(?:^|\D)(\d{1,2})[.\/-](\d{1,2})[.\/-](20\d{2})(?:\D|$)/u', $title, $m ) ) {
            $iso = sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
            return $this->postpone_valid_date( $iso ) ? $iso : '';
        }
        $months = [
            'ocak'=>1,'şubat'=>2,'subat'=>2,'mart'=>3,'nisan'=>4,'mayıs'=>5,'mayis'=>5,'haziran'=>6,'temmuz'=>7,
            'ağustos'=>8,'agustos'=>8,'eylül'=>9,'eylul'=>9,'ekim'=>10,'kasım'=>11,'kasim'=>11,'aralık'=>12,'aralik'=>12,
        ];
        if ( preg_match( '/(?:^|\D)(\d{1,2})\s+(Ocak|Şubat|Subat|Mart|Nisan|Mayıs|Mayis|Haziran|Temmuz|Ağustos|Agustos|Eylül|Eylul|Ekim|Kasım|Kasim|Aralık|Aralik)\s+(20\d{2})(?:\D|$)/iu', $title, $m ) ) {
            $key = $this->postpone_lower_tr( $m[2] );
            if ( isset( $months[$key] ) ) {
                $iso = sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $months[$key], (int) $m[1] );
                return $this->postpone_valid_date( $iso ) ? $iso : '';
            }
        }
        return '';
    }

    private function postpone_replace_date_text( $text, $source_date, $new_date ) {
        if ( ! is_string( $text ) || $text === '' || ! $this->postpone_valid_date( $source_date ) || ! $this->postpone_valid_date( $new_date ) ) return $text;
        list( $sy, $sm, $sd ) = array_map( 'intval', explode( '-', $source_date ) );
        list( $ny, $nm, $nd ) = array_map( 'intval', explode( '-', $new_date ) );
        $src_month = $this->postpone_month_tr( $sm );
        $new_month = $this->postpone_month_tr( $nm );
        $search = [
            sprintf('%04d-%02d-%02d',$sy,$sm,$sd), sprintf('%02d.%02d.%04d',$sd,$sm,$sy), sprintf('%d.%d.%04d',$sd,$sm,$sy),
            sprintf('%02d/%02d/%04d',$sd,$sm,$sy), sprintf('%d/%d/%04d',$sd,$sm,$sy), sprintf('%02d-%02d-%04d',$sd,$sm,$sy),
            sprintf('%d-%d-%04d',$sd,$sm,$sy), sprintf('%02d %s %04d',$sd,$src_month,$sy), sprintf('%d %s %04d',$sd,$src_month,$sy),
        ];
        $replace = [
            sprintf('%04d-%02d-%02d',$ny,$nm,$nd), sprintf('%02d.%02d.%04d',$nd,$nm,$ny), sprintf('%d.%d.%04d',$nd,$nm,$ny),
            sprintf('%02d/%02d/%04d',$nd,$nm,$ny), sprintf('%d/%d/%04d',$nd,$nm,$ny), sprintf('%02d-%02d-%04d',$nd,$nm,$ny),
            sprintf('%d-%d-%04d',$nd,$nm,$ny), sprintf('%02d %s %04d',$nd,$new_month,$ny), sprintf('%d %s %04d',$nd,$new_month,$ny),
        ];
        return str_replace( $search, $replace, $text );
    }

    private function postpone_replace_date_recursive( $value, $source_date, $new_date ) {
        if ( is_string( $value ) ) return $this->postpone_replace_date_text( $value, $source_date, $new_date );
        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) $value[$k] = $this->postpone_replace_date_recursive( $v, $source_date, $new_date );
        } elseif ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $k => $v ) $value->{$k} = $this->postpone_replace_date_recursive( $v, $source_date, $new_date );
        }
        return $value;
    }

    private function postpone_shift_datetime( $value, $source_date, $new_date ) {
        if ( is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}(.*)$/s', $value, $m ) ) return $new_date . $m[1];
        return $this->postpone_replace_date_recursive( $value, $source_date, $new_date );
    }

    private function postpone_clone_taxonomies( $source_id, $target_id, $post_type ) {
        foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
            if ( $taxonomy === 'product_visibility' ) continue;
            $ids = wp_get_object_terms( $source_id, $taxonomy, [ 'fields'=>'ids' ] );
            if ( is_wp_error( $ids ) ) continue;
            wp_set_object_terms( $target_id, array_map( 'intval', (array) $ids ), $taxonomy, false );
        }
    }

    private function postpone_skip_meta_key( $key, $extra = [] ) {
        if ( in_array( $key, $extra, true ) ) return true;
        foreach ( [ '_mdg_v385', '_mdg_v3851', '_mdg_v387', '_mdg_v4' ] as $prefix ) {
            if ( strpos( (string) $key, $prefix ) === 0 ) return true;
        }
        return false;
    }

    private function postpone_clone_meta( $source_id, $target_id, $source_date, $new_date, $extra_exclude = [] ) {
        $all = get_post_meta( $source_id );
        foreach ( $all as $key => $values ) {
            if ( $this->postpone_skip_meta_key( $key, $extra_exclude ) ) continue;
            delete_post_meta( $target_id, $key );
            foreach ( (array) $values as $raw ) {
                $value = $this->postpone_replace_date_recursive( maybe_unserialize( $raw ), $source_date, $new_date );
                add_post_meta( $target_id, $key, $value );
            }
        }
    }

    private function postpone_variation_label( $variation_id ) {
        $meta = get_post_meta( $variation_id );
        foreach ( $meta as $key => $values ) {
            if ( strpos( $key, 'attribute_' ) !== 0 ) continue;
            $val = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
            if ( ! is_scalar( $val ) || (string) $val === '' ) continue;
            $taxonomy = substr( $key, strlen( 'attribute_' ) );
            $term = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', (string) $val, $taxonomy ) : false;
            if ( $term && ! is_wp_error( $term ) ) return $term->name;
            return (string) $val;
        }
        return get_the_title( $variation_id );
    }

    private function postpone_variations( $product_id, $target = false ) {
        $ids = get_posts([
            'post_type'=>'product_variation','post_status'=>'any','post_parent'=>absint($product_id),
            'numberposts'=>-1,'fields'=>'ids','orderby'=>[ 'menu_order'=>'ASC','ID'=>'ASC' ],
        ]);
        $out = [];
        foreach ( array_values( array_unique( array_map( 'absint', (array) $ids ) ) ) as $id ) {
            $price = get_post_meta( $id, '_regular_price', true );
            if ( $price === '' ) $price = get_post_meta( $id, '_price', true );
            $out[] = [
                'id'=>$id,
                'price'=>(string)$price,
                'label'=>$this->postpone_variation_label($id),
                'source_variation_id'=>$target?absint(get_post_meta($id,'_mdg_v3851_source_variation_id',true)):0,
                'source_product_id'=>$target?absint(get_post_meta($id,'_mdg_v3851_source_product_id',true)):0,
                'target_product_id'=>$target?absint(get_post_meta($id,'_mdg_v3851_target_product_id',true)):0,
            ];
        }
        return $out;
    }

    private function postpone_source_products( $event_id ) {
        $ids = get_posts([
            'post_type'=>'product','post_status'=>'any','numberposts'=>-1,'fields'=>'ids',
            'meta_key'=>'_event_name','meta_value'=>(string)absint($event_id),
        ]);
        $out = [];
        foreach ( array_values( array_unique( array_map( 'absint', (array) $ids ) ) ) as $id ) {
            $title = get_the_title( $id );
            $wc = function_exists('wc_get_product') ? wc_get_product($id) : false;
            $out[] = [
                'id'=>$id,'title'=>$title,'time'=>$this->postpone_time_from_title($title),'date'=>$this->postpone_date_from_title($title),
                'closed'=>'yes'===get_post_meta($id,self::SALES_META,true),
                'status'=>(string)get_post_status($id),
                'type'=>$wc?(string)$wc->get_type():'',
                'variations'=>$this->postpone_variations($id,false),
            ];
        }
        usort( $out, fn($a,$b)=>strcmp($a['time'],$b['time']) );
        return $out;
    }

    private function postpone_target_products( $event_id ) {
        $ids = get_posts([
            'post_type'=>'product','post_status'=>'any','numberposts'=>-1,'fields'=>'ids',
            'meta_key'=>'_mdg_v385_target_event_id','meta_value'=>(string)absint($event_id),
        ]);
        $out = [];
        foreach ( array_values( array_unique( array_map( 'absint', (array) $ids ) ) ) as $id ) {
            $title = get_the_title( $id );
            $out[] = [
                'id'=>$id,'title'=>$title,'time'=>$this->postpone_time_from_title($title),'date'=>$this->postpone_date_from_title($title),
                'closed'=>'yes'===get_post_meta($id,self::SALES_META,true),
                'status'=>(string)get_post_status($id),
                'source_product_id'=>absint(get_post_meta($id,'_mdg_v385_source_product_id',true)),
                'variations'=>$this->postpone_variations($id,true),
            ];
        }
        usort( $out, fn($a,$b)=>strcmp($a['time'],$b['time']) );
        return $out;
    }

    private function postpone_index_by_time( $rows ) {
        $out = [];
        foreach ( (array) $rows as $r ) $out[$r['time']][] = $r;
        return $out;
    }

    private function postpone_consistent_date( $products ) {
        $dates = array_values( array_unique( array_filter( array_column( (array)$products, 'date' ) ) ) );
        return count( $dates ) === 1 ? $dates[0] : '';
    }

    private function postpone_product_hidden( $product_id ) {
        if ( ! taxonomy_exists( 'product_visibility' ) ) return true;
        $slugs = wp_get_object_terms( $product_id, 'product_visibility', [ 'fields'=>'slugs' ] );
        return ! is_wp_error($slugs) && in_array('exclude-from-catalog',(array)$slugs,true) && in_array('exclude-from-search',(array)$slugs,true);
    }

    private function postpone_mapping_for_source( $source_id ) {
        $ids = get_posts([
            'post_type'=>self::MAP_POST_TYPE,'post_status'=>'any','numberposts'=>1,'fields'=>'ids',
            'meta_key'=>'_mdg_v387_source_event_id','meta_value'=>(string)absint($source_id),
        ]);
        return $ids ? absint($ids[0]) : 0;
    }

    private function postpone_mapping_for_target( $target_id ) {
        $ids = get_posts([
            'post_type'=>self::MAP_POST_TYPE,'post_status'=>'any','numberposts'=>1,'fields'=>'ids',
            'meta_key'=>'_mdg_v387_target_event_id','meta_value'=>(string)absint($target_id),
        ]);
        return $ids ? absint($ids[0]) : 0;
    }

    private function postpone_existing_target( $source_id, $new_date ) {
        $ids = get_posts([
            'post_type'=>'tc_events','post_status'=>'any','numberposts'=>1,'fields'=>'ids',
            'meta_query'=>[
                'relation'=>'AND',
                [ 'key'=>'_mdg_v385_source_event_id','value'=>(string)absint($source_id),'compare'=>'=' ],
                [ 'key'=>'_mdg_v385_target_date','value'=>(string)$new_date,'compare'=>'=' ],
            ],
        ]);
        return $ids ? absint($ids[0]) : 0;
    }

    private function postpone_preflight( $source_id, $new_date ) {
        $source = get_post( $source_id );
        $source_ok = $source && $source->post_type === 'tc_events';
        $products = $source_ok ? $this->postpone_source_products($source_id) : [];
        $source_date = $this->postpone_consistent_date($products);
        $expected = [ '12:00','14:00','16:00' ];
        $times = array_column($products,'time');
        sort($times,SORT_STRING);
        $expected_sorted=$expected; sort($expected_sorted,SORT_STRING);
        $times_ok = count($products)===3 && $times===$expected_sorted;
        $all_closed = $products && count(array_filter($products,fn($p)=>$p['closed']))===count($products);
        $all_variable = $products && count(array_filter($products,fn($p)=>$p['type']==='variable' && count($p['variations'])>0))===count($products);
        $date_ok = $this->postpone_valid_date($source_date);
        $new_date_ok = $this->postpone_valid_date($new_date);
        $new_after = $date_ok && $new_date_ok && strcmp($new_date,$source_date)>0;
        $existing_target = ($source_id && $new_date_ok)?$this->postpone_existing_target($source_id,$new_date):0;
        $source_map = $source_id?$this->postpone_mapping_for_source($source_id):0;
        $ready = $source_ok && count($products)===3 && $times_ok && $all_closed && $all_variable && $date_ok && $new_after && !$existing_target && !$source_map && $this->cap();
        return compact('source','source_ok','products','source_date','times_ok','all_closed','all_variable','date_ok','new_date_ok','new_after','existing_target','source_map','ready');
    }

    private function postpone_clone_event( $source, $source_date, $new_date ) {
        $title = $this->postpone_replace_date_text($source->post_title,$source_date,$new_date);
        if ( $title === $source->post_title ) $title .= ' — '.$this->postpone_display_date($new_date).' (Taslak)';
        $id = wp_insert_post(wp_slash([
            'post_type'=>'tc_events','post_status'=>'draft','post_title'=>$title,
            'post_content'=>$this->postpone_replace_date_text($source->post_content,$source_date,$new_date),
            'post_excerpt'=>$this->postpone_replace_date_text($source->post_excerpt,$source_date,$new_date),
            'post_parent'=>absint($source->post_parent),'menu_order'=>intval($source->menu_order),
            'comment_status'=>$source->comment_status,'ping_status'=>$source->ping_status,'post_author'=>get_current_user_id(),
        ]),true);
        if ( is_wp_error($id) ) return $id;
        $this->postpone_clone_taxonomies($source->ID,$id,'tc_events');
        $this->postpone_clone_meta($source->ID,$id,$source_date,$new_date,[ '_edit_lock','_edit_last','_wp_old_slug' ]);
        foreach ( [ 'event_date_time','event_end_date_time' ] as $key ) {
            $old = get_post_meta($source->ID,$key,true);
            if ( $old !== '' && $old !== null ) update_post_meta($id,$key,$this->postpone_shift_datetime($old,$source_date,$new_date));
        }
        update_post_meta($id,'_mdg_v385_source_event_id',absint($source->ID));
        update_post_meta($id,'_mdg_v385_source_date',$source_date);
        update_post_meta($id,'_mdg_v385_target_date',$new_date);
        update_post_meta($id,'_mdg_v385_created_by',get_current_user_id());
        update_post_meta($id,'_mdg_v385_created_at',current_time('mysql'));
        update_post_meta($id,'_mdg_v385_bundle_complete','no');
        update_post_meta($id,'_mdg_v404_created_by_v4','yes');
        return $id;
    }

    private function postpone_clone_product( $source_product_id, $target_event_id, $source_event_id, $source_date, $new_date ) {
        $source = get_post($source_product_id);
        if ( !$source || $source->post_type!=='product' ) return new WP_Error('mdg_invalid_product','Kaynak WooCommerce seans ürünü bulunamadı.');
        $id = wp_insert_post(wp_slash([
            'post_type'=>'product','post_status'=>'draft',
            'post_title'=>$this->postpone_replace_date_text($source->post_title,$source_date,$new_date),
            'post_content'=>$this->postpone_replace_date_text($source->post_content,$source_date,$new_date),
            'post_excerpt'=>$this->postpone_replace_date_text($source->post_excerpt,$source_date,$new_date),
            'post_parent'=>0,'menu_order'=>intval($source->menu_order),'comment_status'=>$source->comment_status,
            'ping_status'=>$source->ping_status,'post_author'=>get_current_user_id(),
        ]),true);
        if ( is_wp_error($id) ) return $id;
        $this->postpone_clone_taxonomies($source_product_id,$id,'product');
        $this->postpone_clone_meta($source_product_id,$id,$source_date,$new_date,[
            '_edit_lock','_edit_last','_wp_old_slug','_sku','_total_sales','_wc_average_rating','_wc_rating_count','_wc_review_count'
        ]);
        update_post_meta($id,'_event_name',(string)absint($target_event_id));
        update_post_meta($id,self::SALES_META,'yes');
        update_post_meta($id,'_sku','');
        update_post_meta($id,'_total_sales','0');
        update_post_meta($id,'_mdg_v385_source_event_id',absint($source_event_id));
        update_post_meta($id,'_mdg_v385_source_product_id',absint($source_product_id));
        update_post_meta($id,'_mdg_v385_target_event_id',absint($target_event_id));
        update_post_meta($id,'_mdg_v385_source_date',$source_date);
        update_post_meta($id,'_mdg_v385_target_date',$new_date);
        update_post_meta($id,'_mdg_v385_created_at',current_time('mysql'));
        update_post_meta($id,'_mdg_v404_created_by_v4','yes');
        if ( taxonomy_exists('product_visibility') ) {
            wp_set_object_terms($id,[ 'exclude-from-catalog','exclude-from-search' ],'product_visibility',true);
        }
        clean_post_cache($id);
        return $id;
    }

    private function postpone_clone_variation( $source_var_id, $target_parent_id, $source_parent_id, $target_event_id, $source_date, $new_date ) {
        $source = get_post($source_var_id);
        if ( !$source || $source->post_type!=='product_variation' ) return new WP_Error('mdg_invalid_variation','Kaynak varyasyon bulunamadı.');
        $id = wp_insert_post(wp_slash([
            'post_type'=>'product_variation','post_status'=>'publish','post_parent'=>absint($target_parent_id),
            'post_title'=>get_the_title($target_parent_id).' — varyasyon',
            'post_excerpt'=>$this->postpone_replace_date_text($source->post_excerpt,$source_date,$new_date),
            'menu_order'=>intval($source->menu_order),'post_author'=>get_current_user_id(),
        ]),true);
        if ( is_wp_error($id) ) return $id;
        $this->postpone_clone_meta($source_var_id,$id,$source_date,$new_date,[ '_edit_lock','_edit_last','_wp_old_slug','_sku','_total_sales' ]);
        update_post_meta($id,'_sku','');
        update_post_meta($id,'_total_sales','0');
        update_post_meta($id,self::SALES_META,'yes');
        update_post_meta($id,'_mdg_v3851_source_variation_id',absint($source_var_id));
        update_post_meta($id,'_mdg_v3851_source_product_id',absint($source_parent_id));
        update_post_meta($id,'_mdg_v3851_target_product_id',absint($target_parent_id));
        update_post_meta($id,'_mdg_v3851_created_at',current_time('mysql'));
        update_post_meta($id,'_mdg_v404_created_by_v4','yes');
        return $id;
    }

    private function postpone_bundle_report( $source_id, $target_id ) {
        $source=get_post($source_id); $target=get_post($target_id);
        $events_ok=$source&&$target&&$source->post_type==='tc_events'&&$target->post_type==='tc_events'&&$source_id!==$target_id;
        $target_draft=$target&&$target->post_status==='draft';
        $bundle_link=$target&&absint(get_post_meta($target_id,'_mdg_v385_source_event_id',true))===$source_id;
        $source_products=$this->postpone_source_products($source_id);
        $target_products=$this->postpone_target_products($target_id);
        $source_date=$this->postpone_consistent_date($source_products);
        $target_date=(string)get_post_meta($target_id,'_mdg_v385_target_date',true);
        if(!$this->postpone_valid_date($target_date))$target_date=$this->postpone_consistent_date($target_products);
        $dates_ok=$this->postpone_valid_date($source_date)&&$this->postpone_valid_date($target_date)&&strcmp($target_date,$source_date)>0;
        $sby=$this->postpone_index_by_time($source_products); $tby=$this->postpone_index_by_time($target_products);
        $rows=[];$variation_rows=[];$all_ok=true;
        foreach([ '12:00','14:00','16:00' ] as $time){
            $s=(isset($sby[$time])&&count($sby[$time])===1)?$sby[$time][0]:null;
            $t=(isset($tby[$time])&&count($tby[$time])===1)?$tby[$time][0]:null;
            $ok=$s&&$t&&$t['source_product_id']===$s['id']&&$t['status']==='draft'&&$t['closed']&&$this->postpone_product_hidden($t['id']);
            $sv=$s?$s['variations']:[]; $tv=$t?$t['variations']:[];
            $target_by_source=[];
            foreach($tv as $x){ if($x['source_variation_id'])$target_by_source[$x['source_variation_id']]=$x; }
            $var_ok=count($sv)>0&&count($sv)===count($tv);
            foreach($sv as $x){
                $y=$target_by_source[$x['id']]??null;
                $one=$y&&$y['source_product_id']===$s['id']&&$y['target_product_id']===$t['id']&&(string)$y['price']===(string)$x['price'];
                $var_ok=$var_ok&&$one;
                $variation_rows[]=[
                    'time'=>$time,'label'=>$x['label'],'source_id'=>$x['id'],'target_id'=>$y?$y['id']:0,
                    'source_price'=>(string)$x['price'],'target_price'=>$y?(string)$y['price']:'','ok'=>(bool)$one
                ];
            }
            $ok=$ok&&$var_ok; $all_ok=$all_ok&&$ok;
            $rows[]=['time'=>$time,'source'=>$s,'target'=>$t,'ok'=>(bool)$ok];
        }
        $ready=$events_ok&&$target_draft&&$bundle_link&&$dates_ok&&count($source_products)===3&&count($target_products)===3&&$all_ok&&!$this->postpone_mapping_for_source($source_id)&&!$this->postpone_mapping_for_target($target_id);
        return compact('source','target','events_ok','target_draft','bundle_link','source_products','target_products','source_date','target_date','dates_ok','rows','variation_rows','ready');
    }

    private function postpone_create_mapping( $r, $source_id, $target_id ) {
        $products=[]; foreach($r['rows'] as $row)$products[]=[
            'time'=>$row['time'],'source_product_id'=>absint($row['source']['id']??0),'target_product_id'=>absint($row['target']['id']??0)
        ];
        $variations=[]; foreach($r['variation_rows'] as $v)$variations[]=[
            'time'=>$v['time'],'label'=>$v['label'],'source_variation_id'=>absint($v['source_id']),
            'target_variation_id'=>absint($v['target_id']),'price'=>(string)$v['source_price']
        ];
        usort($products,fn($a,$b)=>strcmp($a['time'],$b['time']));
        usort($variations,function($a,$b){$c=strcmp($a['time'],$b['time']);return $c!==0?$c:($a['source_variation_id']<=>$b['source_variation_id']);});
        $payload=[
            'schema'=>'mdg-postponement-mapping-v1','source_event_id'=>$source_id,'target_event_id'=>$target_id,
            'source_date'=>$r['source_date'],'target_date'=>$r['target_date'],'products'=>$products,'variations'=>$variations,
            'ticket_transfer_performed'=>false,
        ];
        $json=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(!$json)return new WP_Error('mdg_mapping_json','Eşleme özeti oluşturulamadı.');
        $hash=hash('sha256',$json);
        $title=sprintf('Erteleme Eşlemesi #%d → #%d | %s → %s',$source_id,$target_id,$this->postpone_display_date($r['source_date']),$this->postpone_display_date($r['target_date']));
        $map_id=wp_insert_post([
            'post_type'=>self::MAP_POST_TYPE,'post_status'=>'private','post_title'=>$title,'post_author'=>get_current_user_id()
        ],true);
        if(is_wp_error($map_id)||!$map_id)return is_wp_error($map_id)?$map_id:new WP_Error('mdg_mapping_insert','Eşleme kaydı oluşturulamadı.');
        update_post_meta($map_id,'_mdg_v387_source_event_id',$source_id);
        update_post_meta($map_id,'_mdg_v387_target_event_id',$target_id);
        update_post_meta($map_id,'_mdg_v387_source_date',$r['source_date']);
        update_post_meta($map_id,'_mdg_v387_target_date',$r['target_date']);
        update_post_meta($map_id,self::LEGACY_MAPPING_STATE,'locked');
        update_post_meta($map_id,self::LEGACY_MAPPING_PAYLOAD,$json);
        update_post_meta($map_id,self::LEGACY_MAPPING_HASH,$hash);
        update_post_meta($map_id,'_mdg_v387_created_by',get_current_user_id());
        update_post_meta($map_id,'_mdg_v387_created_at',current_time('mysql'));
        update_post_meta($map_id,'_mdg_v387_single_user_development_mode','yes');
        update_post_meta($map_id,self::LEGACY_TRANSFER_FLAG,'no');
        update_post_meta($map_id,'_mdg_v387_version','4.0.4');
        update_post_meta($map_id,'_mdg_v404_created_bundle','yes');
        return $map_id;
    }

    private function postpone_create_bundle_and_mapping( $source_id, $new_date, $pre ) {
        $event_id=0;$products=[];$variations=[];$map_id=0;
        try{
            $event_id=$this->postpone_clone_event($pre['source'],$pre['source_date'],$new_date);
            if(is_wp_error($event_id)||!$event_id)throw new Exception(is_wp_error($event_id)?$event_id->get_error_message():'Hedef etkinlik oluşturulamadı.');
            foreach($pre['products'] as $sp){
                $tp=$this->postpone_clone_product($sp['id'],$event_id,$source_id,$pre['source_date'],$new_date);
                if(is_wp_error($tp)||!$tp)throw new Exception(is_wp_error($tp)?$tp->get_error_message():'Hedef ürün oluşturulamadı.');
                $products[]=(int)$tp;
                foreach($sp['variations'] as $sv){
                    $tv=$this->postpone_clone_variation($sv['id'],$tp,$sp['id'],$event_id,$pre['source_date'],$new_date);
                    if(is_wp_error($tv)||!$tv)throw new Exception(is_wp_error($tv)?$tv->get_error_message():'Hedef varyasyon oluşturulamadı.');
                    $variations[]=(int)$tv;
                }
                if(class_exists('WC_Product_Variable'))WC_Product_Variable::sync($tp);
                if(function_exists('wc_delete_product_transients'))wc_delete_product_transients($tp);
                clean_post_cache($tp);
                update_post_meta($tp,'_mdg_v3851_variations_repaired','yes');
                update_post_meta($tp,'_mdg_v3851_repaired_at',current_time('mysql'));
            }
            if(count($products)!==3)throw new Exception('Üç hedef seans ürününün tamamı oluşturulamadı.');
            update_post_meta($event_id,'_mdg_v385_bundle_complete','yes');
            update_post_meta($event_id,'_mdg_v385_target_product_ids',$products);
            $report=$this->postpone_bundle_report($source_id,$event_id);
            if(!$report['ready'])throw new Exception('Oluşturulan hedef paket güvenlik doğrulamasını geçmedi.');
            $map_id=$this->postpone_create_mapping($report,$source_id,$event_id);
            if(is_wp_error($map_id)||!$map_id)throw new Exception(is_wp_error($map_id)?$map_id->get_error_message():'Eşleme kilitlenemedi.');
            return ['ok'=>true,'event_id'=>$event_id,'product_ids'=>$products,'variation_ids'=>$variations,'map_id'=>$map_id];
        }catch(Throwable $e){
            if($map_id)wp_delete_post($map_id,true);
            foreach(array_reverse($variations) as $id)wp_delete_post($id,true);
            foreach(array_reverse($products) as $id)wp_delete_post($id,true);
            if($event_id)wp_delete_post($event_id,true);
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }

    public function handle_postpone_create() {
        $this->require_cap();
        $source_id=absint($_POST['source_event_id']??0);
        $new_date=sanitize_text_field(wp_unslash($_POST['new_date']??''));
        check_admin_referer('mdg_v4_postpone_create_'.$source_id);
        $phrase='YENI ERTELEME '.$source_id.' '.$new_date.' OLUSTUR';
        $confirm=trim(sanitize_text_field(wp_unslash($_POST['confirm']??'')));
        if(!hash_equals($phrase,$confirm))wp_die('Onay cümlesi eşleşmedi. Hiçbir kayıt oluşturulmadı.');
        $pre=$this->postpone_preflight($source_id,$new_date);
        if(!$pre['ready'])wp_die('Yeni erteleme güvenlik kapısı geçmedi. Hiçbir kayıt oluşturulmadı.');
        $lock='mdg_v404_create_'.$source_id.'_'.preg_replace('/[^0-9]/','',$new_date);
        if(!add_option($lock,(string)time(),'','no'))wp_die('Aynı erteleme oluşturma işlemi şu anda çalışıyor. Tekrar tıklamayın.');
        try{
            $pre=$this->postpone_preflight($source_id,$new_date);
            if(!$pre['ready'])throw new Exception('Güvenlik koşulları işlem sırasında değişti.');
            $result=$this->postpone_create_bundle_and_mapping($source_id,$new_date,$pre);
            if(empty($result['ok']))throw new Exception($result['message']??'Erteleme paketi oluşturulamadı.');
            delete_option($lock);
            wp_safe_redirect(add_query_arg([
                'page'=>'mdg-v4-postpone','map_id'=>(int)$result['map_id'],'created_target'=>(int)$result['event_id'],'result'=>'bundle_created'
            ],admin_url('admin.php')));exit;
        }catch(Throwable $e){
            delete_option($lock);
            wp_die('Yeni erteleme oluşturulamadı ve V4 tarafından oluşturulan taslak kayıtlar geri alındı. Neden: '.esc_html($e->getMessage()));
        }
    }

    private function render_postpone_builder() {
        $source_id=absint($_GET['new_source_event_id']??0);
        $new_date=sanitize_text_field(wp_unslash($_GET['new_date']??''));
        if($new_date&&!$this->postpone_valid_date($new_date))$new_date='';
        $events=get_posts([
            'post_type'=>'tc_events','post_status'=>['publish','draft','private'],'posts_per_page'=>100,'orderby'=>'ID','order'=>'DESC'
        ]);
        echo '<div class="mdgv4-card"><h2>Yeni Erteleme Oluştur <span class="mdgv4-small">V4.0.14</span></h2>';
        echo '<div class="mdgv4-note"><strong>Tek merkez:</strong> Kaynak satışları kapalıysa V4 hedef Tickera etkinliğini, 12:00 / 14:00 / 16:00 WooCommerce seans ürünlerini ve çocuk/yetişkin varyasyonlarını <strong>taslak + gizli + satış kilitli</strong> olarak kopyalar; ardından seans/varyasyon eşlemesini doğrular ve SHA-256 ile kilitler. Kaynak sipariş, bilet, QR, ödeme ve müşteri kayıtlarına dokunmaz.</div>';
        echo '<form method="get"><input type="hidden" name="page" value="mdg-v4-postpone"><label><strong>Kaynak etkinlik</strong><br><select name="new_source_event_id" style="min-width:460px"><option value="0">Seçin</option>';
        foreach($events as $ev)echo '<option value="'.esc_attr($ev->ID).'" '.selected($source_id,$ev->ID,false).'>#'.esc_html($ev->ID).' — '.esc_html($ev->post_title).'</option>';
        echo '</select></label> &nbsp; <label><strong>Yeni tarih</strong><br><input type="date" name="new_date" value="'.esc_attr($new_date).'"></label> ';
        submit_button('YENİ ERTELEME ÖNİZLEMESİ','secondary','',false); echo '</form>';

        if(!$source_id||!$new_date){echo '<p class="mdgv4-small" style="margin-top:14px">Önce kaynak etkinliği ve yeni tarihi seçin.</p></div>';return;}
        $pre=$this->postpone_preflight($source_id,$new_date);
        echo '<h3>Güvenlik kapıları</h3><table class="widefat striped"><tbody>';
        $checks=[
            'Kaynak Tickera etkinliği geçerli'=>$pre['source_ok'],
            'Tam 3 seans ürünü var'=>count($pre['products'])===3,
            'Seanslar 12:00 / 14:00 / 16:00'=>$pre['times_ok'],
            'Kaynak tarih tüm seanslarda aynı'=>$pre['date_ok'],
            'Kaynak satışları kapalı'=>$pre['all_closed'],
            'Üç ürün de variable ve varyasyonları mevcut'=>$pre['all_variable'],
            'Yeni tarih kaynak tarihten sonra'=>$pre['new_after'],
            'Kaynak için daha önce kilitli eşleme yok'=>!$pre['source_map'],
            'Aynı kaynak + yeni tarih taslağı daha önce yok'=>!$pre['existing_target'],
        ];
        foreach($checks as $label=>$ok)echo '<tr><td>'.esc_html($label).'</td><td>'.$this->yesno($ok).'</td></tr>';
        echo '</tbody></table>';
        if($pre['products']){
            echo '<h3>Oluşturulacak taslak paket</h3><div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Seans</th><th>Kaynak ürün</th><th>Yeni başlık</th><th>Varyasyon</th><th>Fiyatlar</th></tr></thead><tbody>';
            foreach($pre['products'] as $p){
                $prices=array_map(fn($v)=>$v['price'],$p['variations']);
                echo '<tr><td><strong>'.esc_html($p['time']).'</strong></td><td>#'.esc_html($p['id']).' — '.esc_html($p['title']).'</td><td>'.esc_html($this->postpone_replace_date_text($p['title'],$pre['source_date'],$new_date)).'</td><td>'.count($p['variations']).'</td><td>'.esc_html(implode(' / ',$prices)).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        if($pre['source_map'])echo '<div class="mdgv4-danger">Bu kaynak etkinlik zaten eşleme #'.esc_html($pre['source_map']).' içinde kullanılmış. Yeni erteleme oluşturma kilitlidir.</div>';
        if($pre['existing_target'])echo '<div class="mdgv4-danger">Aynı kaynak ve tarih için hedef taslak #'.esc_html($pre['existing_target']).' zaten var.</div>';
        if($pre['ready']){
            $phrase='YENI ERTELEME '.$source_id.' '.$new_date.' OLUSTUR';
            echo '<div class="mdgv4-danger"><strong>Gerçek taslak kayıt oluşturur.</strong> Hedef etkinlik ve ürünler draft/gizli/satış kilitli kalır; henüz bilet aktarımı yapılmaz.</div>';
            echo '<p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('mdg_v4_postpone_create_'.$source_id);
            echo '<input type="hidden" name="action" value="mdg_v4_postpone_create"><input type="hidden" name="source_event_id" value="'.esc_attr($source_id).'"><input type="hidden" name="new_date" value="'.esc_attr($new_date).'">';
            echo '<input type="text" name="confirm" style="width:600px;max-width:100%" autocomplete="off"> ';
            submit_button('TASLAK HEDEF + EŞLEMEYİ OLUŞTUR','primary','',false);
            echo '</form>';
        }else{
            echo '<div class="mdgv4-note">Tüm güvenlik kapıları geçmeden oluşturma düğmesi açılmaz.</div>';
        }
        echo '</div>';
    }


    /* -------------------- V4.0.5 SAFE DRAFT BUNDLE CLEANUP -------------------- */

    private function postpone_order_refs_for_ids( $product_ids, $variation_ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', array_merge( (array) $product_ids, (array) $variation_ids ) ) ) ) );
        if ( ! $ids ) return 0;
        $ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = "
            SELECT COUNT(DISTINCT order_item_id)
            FROM {$wpdb->woocommerce_order_itemmeta}
            WHERE meta_key IN ('_product_id','_variation_id')
              AND CAST(meta_value AS UNSIGNED) IN ($ph)
        ";
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$ids ) );
    }

    private function postpone_target_ticket_count( $target_event_id ) {
        $q = new WP_Query([
            'post_type' => 'tc_tickets_instances',
            'post_status' => [ 'publish','private','draft','trash' ],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => [[
                'key' => 'event_id',
                'value' => (string) absint( $target_event_id ),
                'compare' => '=',
            ]],
        ]);
        return (int) $q->found_posts;
    }

    private function postpone_cleanup_report( $map_id, $m = null ) {
        $map_id = absint( $map_id );
        if ( ! $m ) $m = $this->parse_mapping( $map_id );

        $report = [
            'ok' => false,
            'map_id' => $map_id,
            'mapping_ok' => ! empty( $m['ok'] ),
            'created_by_v4' => false,
            'mapping_locked' => false,
            'not_transferred' => false,
            'no_active_transfer' => true,
            'target_event_id' => 0,
            'target_event_draft' => false,
            'target_event_v4' => false,
            'product_ids' => [],
            'variation_ids' => [],
            'products_ok' => false,
            'variations_ok' => false,
            'no_target_tickets' => false,
            'target_ticket_count' => 0,
            'no_order_refs' => false,
            'order_ref_count' => 0,
            'last_transfer_id' => 0,
            'last_transfer_state' => '',
        ];
        if ( empty( $m['ok'] ) ) return $report;

        $target_id = absint( $m['target'] );
        $report['target_event_id'] = $target_id;
        $report['created_by_v4'] = get_post_meta( $map_id, '_mdg_v404_created_bundle', true ) === 'yes';
        $report['mapping_locked'] = get_post_meta( $map_id, self::LEGACY_MAPPING_STATE, true ) === 'locked';
        $report['not_transferred'] = ! $m['transferred'];

        $last = $this->last_transfer_for_map( $map_id );
        if ( $last ) {
            $report['last_transfer_id'] = (int) $last->ID;
            $report['last_transfer_state'] = (string) get_post_meta( $last->ID, '_mdg_v4_state', true );
            if ( $report['last_transfer_state'] === 'success' ) $report['no_active_transfer'] = false;
        }

        $target = get_post( $target_id );
        $report['target_event_draft'] = $target && $target->post_type === 'tc_events' && $target->post_status === 'draft';
        $report['target_event_v4'] = $target && get_post_meta( $target_id, '_mdg_v404_created_by_v4', true ) === 'yes';

        $product_ids = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key' => '_mdg_v385_target_event_id', 'value' => (string) $target_id, 'compare' => '=' ],
                [ 'key' => '_mdg_v404_created_by_v4', 'value' => 'yes', 'compare' => '=' ],
            ],
        ]);
        $product_ids = array_values( array_unique( array_map( 'absint', (array) $product_ids ) ) );
        $report['product_ids'] = $product_ids;

        $products_ok = count( $product_ids ) === 3;
        foreach ( $product_ids as $pid ) {
            if ( get_post_status( $pid ) !== 'draft'
                || get_post_meta( $pid, self::SALES_META, true ) !== 'yes'
                || get_post_meta( $pid, '_mdg_v404_created_by_v4', true ) !== 'yes'
                || ! $this->postpone_product_hidden( $pid )
            ) {
                $products_ok = false;
            }
        }
        $report['products_ok'] = $products_ok;

        $variation_ids = [];
        $variations_ok = true;
        foreach ( $product_ids as $pid ) {
            $vars = get_posts([
                'post_type' => 'product_variation',
                'post_status' => 'any',
                'post_parent' => $pid,
                'numberposts' => -1,
                'fields' => 'ids',
            ]);
            foreach ( (array) $vars as $vid ) {
                $vid = absint( $vid );
                $variation_ids[] = $vid;
                if ( get_post_meta( $vid, '_mdg_v404_created_by_v4', true ) !== 'yes'
                    || absint( get_post_meta( $vid, '_mdg_v3851_target_product_id', true ) ) !== $pid
                ) {
                    $variations_ok = false;
                }
            }
        }
        $variation_ids = array_values( array_unique( $variation_ids ) );
        if ( count( $variation_ids ) < 3 ) $variations_ok = false;
        $report['variation_ids'] = $variation_ids;
        $report['variations_ok'] = $variations_ok;

        $report['target_ticket_count'] = $this->postpone_target_ticket_count( $target_id );
        $report['no_target_tickets'] = $report['target_ticket_count'] === 0;

        $report['order_ref_count'] = $this->postpone_order_refs_for_ids( $product_ids, $variation_ids );
        $report['no_order_refs'] = $report['order_ref_count'] === 0;

        $report['ok'] =
            $report['created_by_v4']
            && $report['mapping_locked']
            && $report['not_transferred']
            && $report['no_active_transfer']
            && $report['target_event_draft']
            && $report['target_event_v4']
            && $report['products_ok']
            && $report['variations_ok']
            && $report['no_target_tickets']
            && $report['no_order_refs'];

        return $report;
    }

    private function render_postpone_cleanup( $map_id, $m ) {
        $r = $this->postpone_cleanup_report( $map_id, $m );
        if ( ! $r['created_by_v4'] ) return;

        echo '<div class="mdgv4-card"><h2>V4 Taslak Paketi Temizleme <span class="mdgv4-small">V4.0.5</span></h2>';
        echo '<div class="mdgv4-note"><strong>Test/geri alma aracı:</strong> Yalnız V4 tarafından oluşturulmuş, hâlâ taslak olan ve hiçbir bilet/sipariş tarafından kullanılmayan hedef paketi temizler. Kaynak etkinliğe, kaynak ürünlere, mevcut siparişlere, PayTR, müşterilere veya QR/ticket_code kayıtlarına dokunmaz.</div>';

        echo '<table class="widefat striped"><tbody>';
        $checks = [
            'Eşleme V4 tarafından oluşturuldu' => $r['created_by_v4'],
            'Eşleme SHA-256 kilitli' => $r['mapping_locked'],
            'Bilet aktarımı yapılmamış / rollback tamam' => $r['not_transferred'] && $r['no_active_transfer'],
            'Hedef Tickera etkinliği draft ve V4 kaydı' => $r['target_event_draft'] && $r['target_event_v4'],
            'Tam 3 hedef ürün draft + gizli + satış kilitli' => $r['products_ok'],
            'Hedef varyasyonların tamamı V4 tarafından oluşturulmuş' => $r['variations_ok'],
            'Hedef etkinliğe bağlı ticket yok' => $r['no_target_tickets'],
            'Hedef ürün/varyasyonlara bağlı WooCommerce sipariş satırı yok' => $r['no_order_refs'],
        ];
        foreach ( $checks as $label => $ok ) {
            echo '<tr><td>'.esc_html($label).'</td><td>'.$this->yesno($ok).'</td></tr>';
        }
        echo '</tbody></table>';

        echo '<p class="mdgv4-small">Hedef event: #'.esc_html($r['target_event_id'])
            .' | Ürün: '.esc_html(implode(', ',array_map(fn($x)=>'#'.$x,$r['product_ids'])))
            .' | Varyasyon: '.esc_html(count($r['variation_ids']))
            .' | Hedef ticket: '.esc_html($r['target_ticket_count'])
            .' | Sipariş referansı: '.esc_html($r['order_ref_count']).'</p>';

        if ( $r['last_transfer_id'] ) {
            echo '<p class="mdgv4-small">Son aktarım kaydı: #'.esc_html($r['last_transfer_id']).' — <code>'.esc_html($r['last_transfer_state']).'</code></p>';
        }

        if ( $r['ok'] ) {
            $phrase = 'TASLAK TEMIZLE '.$map_id.' '.$r['target_event_id'].' ONAYLIYORUM';
            echo '<div class="mdgv4-danger"><strong>Kalıcı temizlik:</strong> V4 hedef varyasyonlarını, hedef ürünleri, hedef Tickera etkinliğini ve bu V4 eşleme kaydını siler. Kaynak taraf değişmez.</div>';
            echo '<p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('mdg_v4_postpone_cleanup_'.$map_id);
            echo '<input type="hidden" name="action" value="mdg_v4_postpone_cleanup">';
            echo '<input type="hidden" name="map_id" value="'.esc_attr($map_id).'">';
            echo '<input type="text" name="confirm" style="width:620px;max-width:100%" autocomplete="off"> ';
            submit_button('V4 TASLAK PAKETİNİ TEMİZLE','secondary','',false);
            echo '</form>';
        } else {
            echo '<div class="mdgv4-danger">Temizlik güvenlik kapısı kapalıdır. V4 bu paketi otomatik silmez.</div>';
        }
        echo '</div>';
    }

    public function handle_postpone_cleanup() {
        $this->require_cap();
        $map_id = absint( $_POST['map_id'] ?? 0 );
        check_admin_referer( 'mdg_v4_postpone_cleanup_'.$map_id );

        $m = $this->parse_mapping( $map_id );
        $r = $this->postpone_cleanup_report( $map_id, $m );
        if ( ! $r['ok'] ) wp_die( 'Taslak paket temizlik güvenlik kapısı geçmedi. Hiçbir kayıt silinmedi.' );

        $phrase = 'TASLAK TEMIZLE '.$map_id.' '.$r['target_event_id'].' ONAYLIYORUM';
        $confirm = trim( sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) ) );
        if ( ! hash_equals( $phrase, $confirm ) ) wp_die( 'Onay cümlesi eşleşmedi. Hiçbir kayıt silinmedi.' );

        $lock = 'mdg_v405_cleanup_'.$map_id;
        if ( ! add_option( $lock, (string) time(), '', 'no' ) ) wp_die( 'Bu paket için temizlik işlemi şu anda çalışıyor.' );

        try {
            $m = $this->parse_mapping( $map_id );
            $r = $this->postpone_cleanup_report( $map_id, $m );
            if ( ! $r['ok'] ) throw new Exception( 'Güvenlik koşulları işlem sırasında değişti.' );

            foreach ( array_reverse( $r['variation_ids'] ) as $vid ) {
                $p = get_post( $vid );
                if ( $p && get_post_meta( $vid, '_mdg_v404_created_by_v4', true ) === 'yes' ) {
                    wp_delete_post( $vid, true );
                }
            }

            foreach ( array_reverse( $r['product_ids'] ) as $pid ) {
                $p = get_post( $pid );
                if ( $p && $p->post_status === 'draft' && get_post_meta( $pid, '_mdg_v404_created_by_v4', true ) === 'yes' ) {
                    wp_delete_post( $pid, true );
                }
            }

            $target = get_post( $r['target_event_id'] );
            if ( $target && $target->post_status === 'draft' && get_post_meta( $r['target_event_id'], '_mdg_v404_created_by_v4', true ) === 'yes' ) {
                wp_delete_post( $r['target_event_id'], true );
            }

            wp_delete_post( $map_id, true );
            delete_option( $lock );

            wp_safe_redirect( add_query_arg([
                'page' => 'mdg-v4-postpone',
                'cleanup_success' => 1,
                'cleaned_map' => $map_id,
                'cleaned_target' => $r['target_event_id'],
            ], admin_url('admin.php') ) );
            exit;
        } catch ( Throwable $e ) {
            delete_option( $lock );
            wp_die( 'Taslak paket temizliği durduruldu. Neden: '.esc_html( $e->getMessage() ) );
        }
    }

    public function render_postpone() {
        $this->require_cap(); $this->styles();
        $maps=$this->mapping_records();
        $map_id=absint($_GET['map_id']??($maps[0]->ID??0));
        $scan=$map_id?$this->scan_mapping($map_id):null;
        echo '<div class="wrap mdgv4"><h1>Erteleme / Bilet Aktarımı</h1>';
        echo '<div class="mdgv4-safe"><strong>V4 aktarım kuralı:</strong> WooCommerce sipariş satırı, ücret, PayTR kaydı, müşteri, ticket post ID ve QR/ticket_code değişmez. Yalnız Tickera ticket instance üzerindeki <code>event_id</code> ve <code>ticket_type_id</code> değişir. Her işlem snapshot + SHA-256 + anlık doğrulama + otomatik rollback kullanır.</div>';
        $this->render_postpone_builder();
        if(!$maps){echo '<div class="mdgv4-danger">Kilitli V3.8.7 erteleme eşleme kaydı bulunamadı. Önce doğrulanmış eşleme gerekir.</div></div>';return;}
        echo '<div class="mdgv4-card"><form method="get"><input type="hidden" name="page" value="mdg-v4-postpone"><label><strong>Eşleme kaydı</strong><br><select name="map_id">';
        foreach($maps as $mp) echo '<option value="'.esc_attr($mp->ID).'" '.selected($map_id,$mp->ID,false).'>#'.esc_html($mp->ID).' — '.esc_html($mp->post_title).'</option>';
        echo '</select></label> ';submit_button('Tara','secondary','',false);echo '</form></div>';
        if(!$scan){echo '</div>';return;}
        $m=$scan['mapping']; if(!$m['ok']){echo '<div class="mdgv4-danger">'.esc_html($m['reason']).'</div></div>';return;}
        $s=$scan['summary'];
        $this->render_postpone_cleanup($map_id,$m);
        $this->render_test_ticket_tool($map_id,$m);
        echo '<div class="mdgv4-grid">';$this->kpi_card('Kaynakta ticket',$s['total'],'');$this->kpi_card('Aktarılabilir',$s['eligible'],'');$this->kpi_card('Aktarım dışı',$s['excluded'],'');$this->kpi_card('Kilit / eşleme dışı',$s['locked'],'');$this->kpi_card('Etkilenen sipariş',$s['orders'],'');echo '</div>';
        echo '<div class="mdgv4-card"><h2>Eşleme</h2><table class="widefat striped"><tbody><tr><td>Kaynak event</td><td>#'.esc_html($m['source']).'</td></tr><tr><td>Hedef event</td><td>#'.esc_html($m['target']).'</td></tr><tr><td>Mapping SHA-256</td><td><code>'.esc_html($m['hash']).'</code></td></tr><tr><td>Daha önce aktarım yapılmış</td><td>'.$this->yesno($m['transferred']).'</td></tr></tbody></table></div>';
        echo '<div class="mdgv4-card"><h2>Ticket planı</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Ticket</th><th>Test</th><th>Sipariş</th><th>Durum</th><th>Seans</th><th>Bilet tipi</th><th>Kaynak</th><th>Hedef</th><th>Karar</th><th>Neden</th></tr></thead><tbody>';
        foreach($scan['rows'] as $r){$color=$r['decision']==='AKTARILABİLİR'?'#16803a':($r['decision']==='KİLİT'?'#b42318':'#996800');echo '<tr><td>#'.esc_html($r['ticket_id']).'</td><td>'.(!empty($r['is_v4_test'])?'<strong class="mdgv4-warn">V4 TEST</strong>':'—').'</td><td>'.($r['post_parent']>0?'#'.esc_html($r['post_parent']):'—').'</td><td><code>'.esc_html($r['post_status']).'</code></td><td>'.esc_html($r['session']).'</td><td>'.esc_html($r['label']).'</td><td>#'.esc_html($r['event_id']).' / #'.esc_html($r['ticket_type_id']).'</td><td>#'.esc_html($r['target_event']).' / #'.esc_html($r['target_var']).'</td><td><strong style="color:'.$color.'">'.esc_html($r['decision']).'</strong></td><td>'.esc_html($r['reason']).'</td></tr>';}
        echo '</tbody></table></div>';

        $phrase='TOPLU AKTARIM '.$map_id.' '.$m['source'].' '.$m['target'].' ONAYLIYORUM';
        if($s['eligible']>0 && $s['locked']===0 && !$m['transferred']){
            echo '<h3>Gerçek aktarım onayı</h3><div class="mdgv4-danger">Bu düğme gerçek veri yazar. Yalnız yukarıda <strong>AKTARILABİLİR</strong> görünen ticket kayıtlarında iki meta alanı değişir.</div><p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('mdg_v4_transfer_'.$map_id);echo '<input type="hidden" name="action" value="mdg_v4_transfer_execute"><input type="hidden" name="map_id" value="'.esc_attr($map_id).'"><input type="text" name="confirm" style="width:720px;max-width:100%;padding:8px" autocomplete="off"> ';submit_button('KONTROLLÜ TOPLU AKTARIMI UYGULA','primary','',false);echo '</form>';
        } elseif($m['transferred']) echo '<div class="mdgv4-safe"><strong>Bu eşleme aktarılmış olarak işaretli.</strong> Aynı mapping üzerinden ikinci kez toplu aktarım yapılamaz.</div>';
        elseif($s['eligible']===0) echo '<div class="mdgv4-note">Aktarılabilir aktif/ödenmiş bilet bulunmadı. Yazma düğmesi açılmaz.</div>';
        else echo '<div class="mdgv4-danger">Kilit/eşleme dışı ticket bulundu. Toplu aktarım kapalıdır.</div>';

        $last=$this->last_transfer_for_map($map_id);
        if($last){$state=(string)get_post_meta($last->ID,'_mdg_v4_state',true);echo '<h3>Son V4 aktarım kaydı</h3><p>#'.esc_html($last->ID).' — <code>'.esc_html($state).'</code></p>'; if($state==='success'){ $rbphrase='ROLLBACK '.$last->ID.' ONAYLIYORUM'; echo '<p>Gerekirse güvenli geri alma için aynen yazın: <code>'.esc_html($rbphrase).'</code></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('mdg_v4_rollback_'.$last->ID);echo '<input type="hidden" name="action" value="mdg_v4_transfer_rollback"><input type="hidden" name="transfer_id" value="'.esc_attr($last->ID).'"><input type="text" name="confirm" style="width:420px"> ';submit_button('AKTARIMI GERİ AL','secondary','',false);echo '</form>';}}
        echo '</div></div>';
    }

    private function create_transfer_record( $map_id, $scan ) {
        $title=sprintf('V4 Erteleme Aktarımı Map #%d | #%d → #%d',$map_id,$scan['mapping']['source'],$scan['mapping']['target']);
        $id=wp_insert_post(['post_type'=>self::TRANSFER_POST_TYPE,'post_status'=>'private','post_title'=>$title,'post_author'=>get_current_user_id()],true);
        if(is_wp_error($id)||!$id) return is_wp_error($id)?$id:new WP_Error('create','Aktarım kaydı oluşturulamadı.');
        $snapshot=[];
        foreach($scan['rows'] as $r) if($r['decision']==='AKTARILABİLİR') $snapshot[]=[
            'ticket_id'=>$r['ticket_id'],'source_event'=>$r['event_id'],'source_var'=>$r['ticket_type_id'],'target_event'=>$r['target_event'],'target_var'=>$r['target_var'],
            'post_type'=>$r['post_type'],'post_status'=>$r['post_status'],'post_parent'=>$r['post_parent'],'ticket_code_hash'=>$r['ticket_code_hash'],'protected_meta_hash'=>$r['protected_meta_hash'],'order_status'=>$r['order_status'],'order_paid'=>$r['order_paid'],'order_total'=>$r['order_total'],'payment_method'=>$r['payment_method'],'order_key_hash'=>$r['order_key_hash'],'is_v4_test'=>$r['is_v4_test']
        ];
        $payload=['schema'=>'mdg-v4-transfer-v1','map_id'=>$map_id,'mapping_hash'=>$scan['mapping']['hash'],'created_utc'=>gmdate('c'),'user_id'=>get_current_user_id(),'tickets'=>$snapshot];
        $json=wp_json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$hash=hash('sha256',$json);
        update_post_meta($id,'_mdg_v4_map_id',$map_id);update_post_meta($id,'_mdg_v4_state','snapshot');update_post_meta($id,'_mdg_v4_payload',$json);update_post_meta($id,'_mdg_v4_hash',$hash);update_post_meta($id,'_mdg_v4_created_utc',gmdate('c'));
        return $id;
    }

    private function verify_transfer_payload( $transfer_id ) {
        $json=(string)get_post_meta($transfer_id,'_mdg_v4_payload',true);$hash=(string)get_post_meta($transfer_id,'_mdg_v4_hash',true);$data=json_decode($json,true);if(!$json||!is_array($data)||!$hash||!hash_equals($hash,hash('sha256',$json))) return false;return $data;
    }

    private function invariant_ok( $before, $after, $target_event, $target_var ) {
        $base = $after['exists'] && $after['event_id']===$target_event && $after['ticket_type_id']===$target_var && $after['post_type']===$before['post_type'] && $after['post_status']===$before['post_status'] && $after['post_parent']===$before['post_parent'] && $after['ticket_code_hash']===$before['ticket_code_hash'] && $after['protected_meta_hash']===$before['protected_meta_hash'] && $after['is_v4_test']===$before['is_v4_test'];
        if ( ! $base ) return false;
        if ( ! empty( $before['is_v4_test'] ) ) return true;
        return $after['order_exists'] && $after['order_status']===$before['order_status'] && $after['order_paid']===$before['order_paid'] && abs($after['order_total']-$before['order_total'])<=0.01 && $after['payment_method']===$before['payment_method'] && $after['order_key_hash']===$before['order_key_hash'];
    }

    public function handle_transfer_execute() {
        $this->require_cap();$map_id=absint($_POST['map_id']??0);check_admin_referer('mdg_v4_transfer_'.$map_id);
        $scan=$this->scan_mapping($map_id);$m=$scan['mapping'];if(!$m['ok'])wp_die('Eşleme geçersiz: '.$m['reason']);
        $phrase='TOPLU AKTARIM '.$map_id.' '.$m['source'].' '.$m['target'].' ONAYLIYORUM';$confirm=trim(sanitize_text_field(wp_unslash($_POST['confirm']??'')));if(!hash_equals($phrase,$confirm))wp_die('Onay cümlesi eşleşmedi. Hiçbir kayıt değiştirilmedi.');
        if($m['transferred']||$scan['summary']['eligible']<=0||$scan['summary']['locked']>0)wp_die('Aktarım güvenlik kapısı geçmedi.');
        $transfer_id=$this->create_transfer_record($map_id,$scan);if(is_wp_error($transfer_id))wp_die($transfer_id->get_error_message());$payload=$this->verify_transfer_payload($transfer_id);if(!$payload)wp_die('Snapshot bütünlüğü oluşturulamadı.');
        $changed=[];$failed=false;$fail_reason='';
        foreach($payload['tickets'] as $t){
            $before=$this->ticket_state((int)$t['ticket_id']);
            // Snapshot son saniyede de aynı olmalı.
            foreach(['post_type','post_status','post_parent','event_id','ticket_type_id','ticket_code_hash','protected_meta_hash','order_status','order_paid','payment_method','order_key_hash','is_v4_test'] as $k){if((string)$before[$k] !== (string)($t[$k==='event_id'?'source_event':($k==='ticket_type_id'?'source_var':$k)]??'')){$failed=true;$fail_reason='snapshot_changed_ticket_'.$t['ticket_id'];break 2;}}
            if(abs((float)$before['order_total']-(float)$t['order_total'])>0.01){$failed=true;$fail_reason='order_total_changed_'.$t['ticket_id'];break;}
            update_post_meta((int)$t['ticket_id'],'event_id',(int)$t['target_event']);update_post_meta((int)$t['ticket_id'],'ticket_type_id',(int)$t['target_var']);clean_post_cache((int)$t['ticket_id']);
            $after=$this->ticket_state((int)$t['ticket_id']);
            if(!$this->invariant_ok($before,$after,(int)$t['target_event'],(int)$t['target_var'])){$failed=true;$fail_reason='post_write_invariant_'.$t['ticket_id'];break;}
            $changed[]=(int)$t['ticket_id'];
        }
        if($failed){foreach(array_reverse($payload['tickets']) as $t){if(!in_array((int)$t['ticket_id'],$changed,true))continue;update_post_meta((int)$t['ticket_id'],'event_id',(int)$t['source_event']);update_post_meta((int)$t['ticket_id'],'ticket_type_id',(int)$t['source_var']);clean_post_cache((int)$t['ticket_id']);}update_post_meta($transfer_id,'_mdg_v4_state','rolled_back');update_post_meta($transfer_id,'_mdg_v4_failure',$fail_reason);wp_die('Aktarım sırasında sapma algılandı ve değiştirilen ticket kayıtları geri alındı. Neden: '.esc_html($fail_reason));}
        update_post_meta($transfer_id,'_mdg_v4_state','success');update_post_meta($transfer_id,'_mdg_v4_completed_utc',gmdate('c'));update_post_meta($map_id,self::LEGACY_TRANSFER_FLAG,'yes');update_post_meta($map_id,'_mdg_v4_transfer_id',$transfer_id);
        wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-postpone','map_id'=>$map_id,'result'=>'success'],admin_url('admin.php')));exit;
    }

    private function last_transfer() { $p=get_posts(['post_type'=>self::TRANSFER_POST_TYPE,'post_status'=>'private','posts_per_page'=>1,'orderby'=>'ID','order'=>'DESC']);return $p?$p[0]:null; }
    private function last_transfer_for_map($map_id){$p=get_posts(['post_type'=>self::TRANSFER_POST_TYPE,'post_status'=>'private','posts_per_page'=>1,'orderby'=>'ID','order'=>'DESC','meta_key'=>'_mdg_v4_map_id','meta_value'=>$map_id]);return $p?$p[0]:null;}

    public function handle_transfer_rollback() {
        $this->require_cap();$id=absint($_POST['transfer_id']??0);check_admin_referer('mdg_v4_rollback_'.$id);$phrase='ROLLBACK '.$id.' ONAYLIYORUM';$confirm=trim(sanitize_text_field(wp_unslash($_POST['confirm']??'')));if(!hash_equals($phrase,$confirm))wp_die('Rollback onayı eşleşmedi.');
        if(get_post_meta($id,'_mdg_v4_state',true)!=='success')wp_die('Yalnız başarılı aktarım geri alınabilir.');$payload=$this->verify_transfer_payload($id);if(!$payload)wp_die('Aktarım snapshot bütünlüğü geçmedi.');
        // Rollback öncesi tüm ticketlar hâlâ hedef durumda ve korunmuş olmalı.
        foreach($payload['tickets'] as $t){$s=$this->ticket_state((int)$t['ticket_id']);if(!$s['exists']||$s['event_id']!==(int)$t['target_event']||$s['ticket_type_id']!==(int)$t['target_var']||$s['post_status']!==$t['post_status']||$s['post_parent']!==(int)$t['post_parent']||$s['ticket_code_hash']!==$t['ticket_code_hash']||$s['protected_meta_hash']!==$t['protected_meta_hash']||$s['order_status']!==$t['order_status']||$s['order_paid']!==(bool)$t['order_paid']||abs($s['order_total']-(float)$t['order_total'])>0.01)wp_die('Rollback güvenlik kapısı geçmedi; ticket #'.(int)$t['ticket_id'].' aktarım sonrası değişmiş.');}
        foreach($payload['tickets'] as $t){update_post_meta((int)$t['ticket_id'],'event_id',(int)$t['source_event']);update_post_meta((int)$t['ticket_id'],'ticket_type_id',(int)$t['source_var']);clean_post_cache((int)$t['ticket_id']);}
        update_post_meta($id,'_mdg_v4_state','rollback_success');update_post_meta($id,'_mdg_v4_rollback_utc',gmdate('c'));$map_id=absint($payload['map_id']??0);if($map_id){update_post_meta($map_id,self::LEGACY_TRANSFER_FLAG,'no');delete_post_meta($map_id,'_mdg_v4_transfer_id');}
        wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-postpone','map_id'=>$map_id,'result'=>'rollback'],admin_url('admin.php')));exit;
    }

    /* -------------------- BILETLERIM -------------------- */

    private function v4_token( $order ) {
        if(!$order) return '';
        $data=(int)$order->get_id().'|'.(string)$order->get_order_key().'|'.strtolower(trim((string)$order->get_billing_email()));
        return hash_hmac('sha256',$data,wp_salt('auth'));
    }

    /**
     * Legacy MS Biletlerim token algorithm.
     * This MUST remain byte-for-byte compatible with previously sent customer links:
     * HMAC-SHA256( order_id|order_key, wp_salt('auth') ).
     */
    private function legacy_token( $order ) {
        if ( ! $order ) return '';
        $data = (int) $order->get_id() . '|' . (string) $order->get_order_key();
        return hash_hmac( 'sha256', $data, wp_salt( 'auth' ) );
    }

    public function legacy_token_public( $order ) {
        return $this->legacy_token( $order );
    }

    public function biletlerim_url( $order_id ) {
        $order=function_exists('wc_get_order')?wc_get_order(absint($order_id)):false;if(!$order)return '';
        return add_query_arg(['order_id'=>$order->get_id(),'token'=>$this->v4_token($order)],home_url('/'.self::BILETLERIM_PAGE.'/'));
    }

    public function legacy_biletlerim_url( $order_id ) {
        $order=function_exists('wc_get_order')?wc_get_order(absint($order_id)):false;if(!$order)return '';
        return add_query_arg(['order_id'=>$order->get_id(),'token'=>$this->legacy_token($order)],home_url('/'.self::BILETLERIM_PAGE.'/'));
    }

    /**
     * After legacy Code Snippets have had a chance to load, provide compatibility aliases
     * only when they are absent. This lets MS Kommo Otomatik Bilet Linki continue calling
     * ms_biletlerim_url() after the old MS Biletlerim snippet is disabled.
     */
    public function register_legacy_biletlerim_compat() {
        if ( ! function_exists( 'ms_biletlerim_token' ) ) {
            function ms_biletlerim_token( $order ) {
                return MDG_Bilet_Yonetimi_V4::boot()->legacy_token_public( $order );
            }
        }
        if ( ! function_exists( 'ms_biletlerim_url' ) ) {
            function ms_biletlerim_url( $order_id ) {
                return MDG_Bilet_Yonetimi_V4::boot()->legacy_biletlerim_url( $order_id );
            }
        }
    }

    private function biletlerim_token_mode( $order, $token ) {
        $token = (string) $token;
        if ( $token === '' || ! $order ) return '';
        $v4 = $this->v4_token( $order );
        if ( $v4 !== '' && hash_equals( $v4, $token ) ) return 'v4';
        $legacy = $this->legacy_token( $order );
        if ( $legacy !== '' && hash_equals( $legacy, $token ) ) return 'legacy';
        return '';
    }

    private function legacy_helper_origin() {
        if ( ! function_exists( 'ms_biletlerim_url' ) ) return 'Yok';
        try {
            $r = new ReflectionFunction( 'ms_biletlerim_url' );
            $file = (string) $r->getFileName();
            if ( $file && realpath( $file ) === realpath( __FILE__ ) ) return 'V4 uyumluluk aliası';
            return 'Harici / eski MS Biletlerim';
        } catch ( Throwable $e ) {
            return 'Bilinmiyor';
        }
    }

    private function ticket_download_url( $ticket_id ) {
        $url='';
        try {
            if(function_exists('tickera_get_raw_ticket_download_link')) $url=tickera_get_raw_ticket_download_link('','',absint($ticket_id),true);
            if(!$url && function_exists('tickera_get_ticket_download_link')) $url=tickera_get_ticket_download_link('','',absint($ticket_id),true);
        } catch(Throwable $e){$url='';}
        if(is_string($url)&&preg_match('/href=["\']([^"\']+)["\']/i',$url,$m))$url=html_entity_decode($m[1]);
        return is_string($url)?esc_url_raw(html_entity_decode(wp_strip_all_tags($url))):'';
    }

    private function order_ticket_ids( $order_id ) {
        $q=new WP_Query(['post_type'=>'tc_tickets_instances','post_status'=>'publish','posts_per_page'=>-1,'post_parent'=>absint($order_id),'fields'=>'ids','orderby'=>'ID','order'=>'ASC','no_found_rows'=>true]);return array_map('intval',$q->posts);
    }

    public function maybe_render_biletlerim() {
        if(is_admin()||!isset($_GET['order_id'],$_GET['token']))return;
        $path=trim((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
        if(substr($path,-strlen(self::BILETLERIM_PAGE))!==self::BILETLERIM_PAGE)return;
        if(!function_exists('wc_get_order'))return;
        $order=wc_get_order(absint($_GET['order_id']));if(!$order)return;
        $token=sanitize_text_field(wp_unslash($_GET['token']));
        $token_mode=$this->biletlerim_token_mode($order,$token);if(!$token_mode)return;
        status_header(200);nocache_headers();
        $tickets=$this->order_ticket_ids($order->get_id());
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Biletleriniz — Madagaskar Sirki</title><style>body{margin:0;background:#c95743;font-family:Arial,sans-serif;color:#fff}.box{max-width:960px;margin:50px auto;background:#090923;border-radius:24px;padding:34px}.brand{color:#d4af37;letter-spacing:3px;text-align:center}.ticket{border:1px solid #8f7527;border-radius:16px;padding:22px;margin:18px 0;background:#15152d;display:flex;align-items:center;justify-content:space-between;gap:18px}.btn{display:inline-block;background:#cf5945;color:#fff;text-decoration:none;padding:16px 22px;border-radius:12px;font-weight:700}.muted{color:#ccc;text-align:center}.help{color:#f0c945;text-decoration:none}@media(max-width:700px){.box{margin:15px}.ticket{display:block}.btn{margin-top:14px}}</style></head><body><div class="box"><div class="brand">🎪 MADAGASKAR SİRKİ TÜRKİYE</div><h1 style="text-align:center">Biletleriniz</h1><p class="muted">Sipariş #'.esc_html($order->get_id()).' · '.count($tickets).' bilet</p>';
        if(!$tickets){echo '<p class="muted">Bu sipariş için aktif bilet bulunamadı.</p>';}else foreach($tickets as $i=>$tid){$url=$this->ticket_download_url($tid);$label=(string)get_the_title($tid);if(!$label)$label='Madagaskar Sirki';echo '<div class="ticket"><div><div style="color:#d4af37;font-weight:700">BİLET '.($i+1).'</div><h2>'.esc_html($label).'</h2></div>'.($url?'<a class="btn" href="'.esc_url($url).'" target="_blank" rel="noopener">🎟 Bileti Görüntüle / İndir</a>':'<span>Bilet bağlantısı hazırlanamadı.</span>').'</div>';}
        echo '<hr style="border-color:#2c2c45"><p class="muted">Koltuk numarası bulunmamaktadır.<br>Lütfen seans saatinden en az <strong>30 dakika önce</strong> salonda hazır bulununuz.</p><p class="muted">Bilgi & Destek: <a class="help" href="'.esc_url(self::WA_URL).'">WhatsApp</a></p></div></body></html>';exit;
    }

    public function render_biletlerim_admin() {
        $this->require_cap();$this->styles();$order_id=absint($_GET['order_id']??1846);$order=function_exists('wc_get_order')?wc_get_order($order_id):false;
        echo '<div class="wrap mdgv4"><h1>Biletlerim</h1><div class="mdgv4-note"><strong>V4.0.14 geçişi:</strong> V4 kendi yeni tokenını ve daha önce müşterilere gönderilmiş eski <code>MS Biletlerim</code> tokenlarını birlikte doğrular. Eski <code>ms_biletlerim_url()</code> kapatıldığında V4 aynı fonksiyon adına geriye dönük uyumluluk aliası sağlar.</div><div class="mdgv4-card"><form method="get"><input type="hidden" name="page" value="mdg-v4-biletlerim"><label><strong>Sipariş ID</strong><br><input type="number" name="order_id" value="'.esc_attr($order_id).'" min="1"></label> ';submit_button('Denetle','secondary','',false);echo '</form></div>';
        if(!$order){echo '<div class="mdgv4-danger">Sipariş bulunamadı.</div></div>';return;}
        $tickets=$this->order_ticket_ids($order_id);
        $legacy='';if(function_exists('ms_biletlerim_url')){try{$legacy=(string)ms_biletlerim_url($order_id);}catch(Throwable $e){$legacy='';}}
        $legacy_v4=$this->legacy_biletlerim_url($order_id);
        $legacy_token_ok=false;
        if($legacy){$q=[];$query=(string)parse_url($legacy,PHP_URL_QUERY);if($query!==''){parse_str($query,$q);$legacy_token=sanitize_text_field((string)($q['token']??''));$legacy_token_ok=($legacy_token!==''&&hash_equals($this->legacy_token($order),$legacy_token));}}
        $origin=$this->legacy_helper_origin();
        echo '<div class="mdgv4-card"><h2>Sipariş #'.esc_html($order_id).'</h2><table class="widefat striped"><tbody><tr><td>Durum</td><td><code>'.esc_html($order->get_status()).'</code></td></tr><tr><td>Ödendi</td><td>'.$this->yesno($order->is_paid()).'</td></tr><tr><td>Aktif Tickera ticket</td><td>'.count($tickets).'</td></tr><tr><td><code>ms_biletlerim_url()</code> kaynağı</td><td><strong>'.esc_html($origin).'</strong></td></tr><tr><td>Mevcut helper linki</td><td>'.$this->yesno((bool)$legacy).'</td></tr><tr><td>Mevcut helper tokenı V4 legacy algoritmasıyla uyumlu</td><td>'.$this->yesno($legacy_token_ok).'</td></tr><tr><td>V4 legacy-token uyumluluğu</td><td>'.$this->yesno((bool)$legacy_v4).'</td></tr><tr><td>V4 yeni link</td><td>'.$this->yesno(true).'</td></tr></tbody></table><div class="mdgv4-actions" style="margin-top:16px">';
        if($legacy)echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url($legacy).'">Mevcut Helper Linkini Aç</a>';
        echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url($legacy_v4).'">V4 Legacy-Uyumlu Linki Aç</a>';
        echo '<a class="button button-primary" target="_blank" rel="noopener" href="'.esc_url($this->biletlerim_url($order_id)).'">V4 Biletlerim Linkini Aç</a></div></div>';
        echo '<div class="mdgv4-card"><h2>Ticketlar</h2><table class="widefat striped"><thead><tr><th>Ticket</th><th>event_id</th><th>ticket_type_id</th><th>QR</th><th>PDF</th></tr></thead><tbody>';foreach($tickets as $tid){$url=$this->ticket_download_url($tid);echo '<tr><td>#'.esc_html($tid).'</td><td>#'.esc_html((int)get_post_meta($tid,'event_id',true)).'</td><td>#'.esc_html((int)get_post_meta($tid,'ticket_type_id',true)).'</td><td>'.$this->yesno((bool)get_post_meta($tid,'ticket_code',true)).'</td><td>'.($url?'<a class="button" target="_blank" rel="noopener" href="'.esc_url($url).'">PDF Aç</a>':'<span class="mdgv4-bad">URL yok</span>').'</td></tr>'; }echo '</tbody></table></div></div>';
    }


    /* -------------------- CANCELLATION / FULL REFUND V4.0.6 -------------------- */

    private function refund_all_ticket_ids( $order_id ) {
        $q = new WP_Query([
            'post_type'      => 'tc_tickets_instances',
            'post_status'    => [ 'publish', 'private', 'draft', 'trash' ],
            'posts_per_page' => -1,
            'post_parent'    => absint( $order_id ),
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        return array_map( 'intval', (array) $q->posts );
    }

    private function refund_ticket_snapshot( $ticket_id ) {
        $p = get_post( $ticket_id );
        if ( ! $p ) return [ 'id'=>$ticket_id, 'exists'=>false ];
        return [
            'id'             => (int) $ticket_id,
            'exists'         => true,
            'post_status'    => (string) $p->post_status,
            'event_id'       => (int) get_post_meta( $ticket_id, 'event_id', true ),
            'ticket_type_id' => (int) get_post_meta( $ticket_id, 'ticket_type_id', true ),
            'invalidated'    => (string) get_post_meta( $ticket_id, self::INVALID_META, true ),
            'invalid_reason' => (string) get_post_meta( $ticket_id, self::INVALID_REASON_META, true ),
            'ticket_code_hash' => hash( 'sha256', (string) get_post_meta( $ticket_id, 'ticket_code', true ) ),
        ];
    }

    private function refund_gateway_info( $order ) {
        $info = [
            'id' => $order ? (string) $order->get_payment_method() : '',
            'title' => $order ? (string) $order->get_payment_method_title() : '',
            'found' => false,
            'supports_refunds' => false,
            'class' => '',
        ];
        if ( ! $order || ! function_exists( 'wc_get_payment_gateway_by_order' ) ) return $info;
        try {
            $gateway = wc_get_payment_gateway_by_order( $order );
            if ( $gateway && is_object( $gateway ) ) {
                $info['found'] = true;
                $info['class'] = get_class( $gateway );
                $info['supports_refunds'] = method_exists( $gateway, 'supports' ) && $gateway->supports( 'refunds' );
            }
        } catch ( Throwable $e ) {}
        return $info;
    }

    private function refund_line_items_for_full_refund( $order ) {
        $out = [];
        if ( ! $order ) return $out;
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $qty = max( 0, (int) $item->get_quantity() );
            $taxes = $item->get_taxes();
            $refund_tax = [];
            foreach ( (array) ( $taxes['total'] ?? [] ) as $tax_id => $tax_total ) {
                $refund_tax[ $tax_id ] = wc_format_decimal( $tax_total );
            }
            $out[ $item_id ] = [
                'qty'          => $qty,
                'refund_total' => wc_format_decimal( $item->get_total() ),
                'refund_tax'   => array_filter( $refund_tax, static fn($v) => (float) $v != 0.0 ),
            ];
        }
        return $out;
    }

    private function refund_snapshot( $order ) {
        if ( ! $order ) return [];
        $tickets = [];
        foreach ( $this->refund_all_ticket_ids( $order->get_id() ) as $tid ) {
            $tickets[] = $this->refund_ticket_snapshot( $tid );
        }
        $items = [];
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $items[] = [
                'item_id'      => (int) $item_id,
                'product_id'   => (int) $item->get_product_id(),
                'variation_id' => (int) $item->get_variation_id(),
                'qty'          => (int) $item->get_quantity(),
                'total'        => (string) wc_format_decimal( $item->get_total() ),
                'total_tax'    => (string) wc_format_decimal( $item->get_total_tax() ),
            ];
        }
        return [
            'schema'           => 'mdg-v4-full-refund-v1',
            'order_id'         => (int) $order->get_id(),
            'status'           => (string) $order->get_status(),
            'is_paid'          => (bool) $order->is_paid(),
            'currency'         => (string) $order->get_currency(),
            'total'            => (string) wc_format_decimal( $order->get_total() ),
            'total_refunded'   => (string) wc_format_decimal( $order->get_total_refunded() ),
            'remaining_refund' => (string) wc_format_decimal( $order->get_remaining_refund_amount() ),
            'payment_method'   => (string) $order->get_payment_method(),
            'transaction_hash' => hash( 'sha256', (string) $order->get_transaction_id() ),
            'shipping_total'   => (string) wc_format_decimal( $order->get_shipping_total() ),
            'discount_total'   => (string) wc_format_decimal( $order->get_discount_total() ),
            'items'            => $items,
            'tickets'          => $tickets,
        ];
    }

    private function refund_snapshot_json( $snapshot ) {
        return wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    private function refund_snapshot_hash( $snapshot ) {
        return hash( 'sha256', (string) $this->refund_snapshot_json( $snapshot ) );
    }

    private function refund_existing_cases( $order_id ) {
        return get_posts([
            'post_type'      => self::REFUND_POST_TYPE,
            'post_status'    => 'private',
            'posts_per_page' => 20,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'meta_key'       => '_mdg_v4_refund_order_id',
            'meta_value'     => absint( $order_id ),
        ]);
    }

    private function refund_live_case_for_order( $order_id ) {
        foreach ( $this->refund_existing_cases( $order_id ) as $case ) {
            $state = (string) get_post_meta( $case->ID, '_mdg_v4_refund_state', true );
            if ( in_array( $state, [ 'pending_approval', 'approved', 'executing', 'attention_required' ], true ) ) return $case;
        }
        return null;
    }

    private function refund_preflight( $order ) {
        $r = [
            'order_exists' => (bool) $order,
            'order_id' => $order ? (int) $order->get_id() : 0,
            'status_ok' => false,
            'paid' => false,
            'no_prior_refund' => false,
            'amount_positive' => false,
            'full_amount_matches' => false,
            'gateway' => [ 'id'=>'','title'=>'','found'=>false,'supports_refunds'=>false,'class'=>'' ],
            'tickets' => [],
            'tickets_exist' => false,
            'tickets_all_active' => false,
            'no_live_case' => false,
            'line_items' => [],
            'line_items_exist' => false,
            'ready' => false,
            'snapshot' => [],
            'snapshot_hash' => '',
        ];
        if ( ! $order ) return $r;

        $r['paid'] = (bool) $order->is_paid();
        $r['status_ok'] = ! in_array( $order->get_status(), [ 'refunded','cancelled','failed','trash' ], true );
        $r['no_prior_refund'] = abs( (float) $order->get_total_refunded() ) < 0.00001;
        $remaining = (float) $order->get_remaining_refund_amount();
        $total = (float) $order->get_total();
        $r['amount_positive'] = $remaining > 0.0;
        $r['full_amount_matches'] = abs( $remaining - $total ) <= 0.01;

        $r['gateway'] = $this->refund_gateway_info( $order );

        $ticket_ids = $this->refund_all_ticket_ids( $order->get_id() );
        foreach ( $ticket_ids as $tid ) $r['tickets'][] = $this->refund_ticket_snapshot( $tid );
        $r['tickets_exist'] = count( $r['tickets'] ) > 0;
        $r['tickets_all_active'] = $r['tickets_exist'];
        foreach ( $r['tickets'] as $t ) {
            if ( empty( $t['exists'] )
                || $t['post_status'] !== 'publish'
                || $t['invalidated'] === 'yes'
                || empty( $t['ticket_code_hash'] )
            ) {
                $r['tickets_all_active'] = false;
            }
        }

        $r['line_items'] = $this->refund_line_items_for_full_refund( $order );
        $r['line_items_exist'] = count( $r['line_items'] ) > 0;
        $r['no_live_case'] = ! $this->refund_live_case_for_order( $order->get_id() );

        $r['snapshot'] = $this->refund_snapshot( $order );
        $r['snapshot_hash'] = $this->refund_snapshot_hash( $r['snapshot'] );

        $r['ready'] =
            $r['order_exists']
            && $r['status_ok']
            && $r['paid']
            && $r['no_prior_refund']
            && $r['amount_positive']
            && $r['full_amount_matches']
            && $r['gateway']['found']
            && $r['gateway']['supports_refunds']
            && $r['tickets_exist']
            && $r['tickets_all_active']
            && $r['line_items_exist']
            && $r['no_live_case'];

        return $r;
    }

    private function refund_case_payload( $case_id ) {
        $json = (string) get_post_meta( $case_id, '_mdg_v4_refund_snapshot', true );
        $hash = (string) get_post_meta( $case_id, '_mdg_v4_refund_snapshot_hash', true );
        if ( ! $json || ! $hash || ! hash_equals( $hash, hash( 'sha256', $json ) ) ) return null;
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : null;
    }

    private function refund_case_invariants_ok( $case_id, $order ) {
        $saved = $this->refund_case_payload( $case_id );
        if ( ! $saved || ! $order ) return false;
        $now = $this->refund_snapshot( $order );

        foreach ( [ 'order_id','status','is_paid','currency','total','total_refunded','remaining_refund','payment_method','transaction_hash' ] as $key ) {
            if ( (string) ( $saved[$key] ?? '' ) !== (string) ( $now[$key] ?? '' ) ) return false;
        }

        $saved_tickets = $saved['tickets'] ?? [];
        $now_tickets = $now['tickets'] ?? [];
        if ( count( $saved_tickets ) !== count( $now_tickets ) ) return false;

        $index = [];
        foreach ( $now_tickets as $t ) $index[ (int) $t['id'] ] = $t;
        foreach ( $saved_tickets as $t ) {
            $tid = (int) $t['id'];
            if ( ! isset( $index[$tid] ) ) return false;
            foreach ( [ 'post_status','event_id','ticket_type_id','invalidated','invalid_reason','ticket_code_hash' ] as $key ) {
                if ( (string) ( $t[$key] ?? '' ) !== (string) ( $index[$tid][$key] ?? '' ) ) return false;
            }
        }
        return true;
    }

    private function refund_case_user_name( $user_id ) {
        $u = get_userdata( absint( $user_id ) );
        return $u ? $u->display_name . ' (#' . $u->ID . ')' : '#' . absint( $user_id );
    }

    private function refund_restore_tickets( $snapshots ) {
        foreach ( (array) $snapshots as $t ) {
            $tid = absint( $t['id'] ?? 0 );
            if ( ! $tid || ! get_post( $tid ) ) continue;
            if ( get_post_status( $tid ) === 'trash' && ( $t['post_status'] ?? '' ) !== 'trash' ) {
                wp_untrash_post( $tid );
            }
            if ( (string) ( $t['invalidated'] ?? '' ) === '' ) {
                delete_post_meta( $tid, self::INVALID_META );
            } else {
                update_post_meta( $tid, self::INVALID_META, (string) $t['invalidated'] );
            }
            if ( (string) ( $t['invalid_reason'] ?? '' ) === '' ) {
                delete_post_meta( $tid, self::INVALID_REASON_META );
            } else {
                update_post_meta( $tid, self::INVALID_REASON_META, (string) $t['invalid_reason'] );
            }
            clean_post_cache( $tid );
        }
    }

    private function refund_invalidate_tickets( $snapshots, $case_id ) {
        $changed = [];
        foreach ( (array) $snapshots as $t ) {
            $tid = absint( $t['id'] ?? 0 );
            if ( ! $tid || ! get_post( $tid ) ) return new WP_Error( 'mdg_ticket_missing', 'Ticket #' . $tid . ' bulunamadı.' );
            if ( get_post_status( $tid ) !== 'publish' ) return new WP_Error( 'mdg_ticket_status', 'Ticket #' . $tid . ' artık aktif değil.' );
            if ( get_post_meta( $tid, self::INVALID_META, true ) === 'yes' ) return new WP_Error( 'mdg_ticket_invalid', 'Ticket #' . $tid . ' zaten geçersiz.' );

            update_post_meta( $tid, self::INVALID_META, 'yes' );
            update_post_meta( $tid, self::INVALID_REASON_META, 'full_refund_v4_case_' . absint( $case_id ) );
            $trashed = wp_trash_post( $tid );
            if ( ! $trashed || get_post_status( $tid ) !== 'trash' ) {
                $this->refund_restore_tickets( $changed );
                return new WP_Error( 'mdg_ticket_trash', 'Ticket #' . $tid . ' güvenli biçimde geçersizleştirilemedi.' );
            }
            $changed[] = $t;
        }
        return true;
    }


    private function refund_reconcile_tickets_after_full_refund( $snapshots, $case_id ) {
        $reason = 'full_refund_v4_case_' . absint( $case_id );

        foreach ( (array) $snapshots as $t ) {
            $tid = absint( $t['id'] ?? 0 );
            if ( ! $tid || ! get_post( $tid ) ) {
                return new WP_Error( 'mdg_reconcile_ticket_missing', 'Ticket #' . $tid . ' bulunamadı.' );
            }

            // Para iadesi sonrası yalnız ilk snapshot ile aynı ticket üzerinde çalış.
            $current_event = (int) get_post_meta( $tid, 'event_id', true );
            $current_type  = (int) get_post_meta( $tid, 'ticket_type_id', true );
            $current_code_hash = hash( 'sha256', (string) get_post_meta( $tid, 'ticket_code', true ) );

            if ( (int) ( $t['event_id'] ?? 0 ) !== $current_event
                || (int) ( $t['ticket_type_id'] ?? 0 ) !== $current_type
                || ! hash_equals( (string) ( $t['ticket_code_hash'] ?? '' ), $current_code_hash )
            ) {
                return new WP_Error(
                    'mdg_reconcile_ticket_changed',
                    'Ticket #' . $tid . ' ilk iade snapshotından sonra kimlik/eşleme değiştirmiş. Otomatik uzlaştırma durduruldu.'
                );
            }

            // İade başarılı olduğundan geçersizlik meta alanı idempotent biçimde yeniden sabitlenir.
            update_post_meta( $tid, self::INVALID_META, 'yes' );
            update_post_meta( $tid, self::INVALID_REASON_META, $reason );

            if ( get_post_status( $tid ) !== 'trash' ) {
                $trashed = wp_trash_post( $tid );
                if ( ! $trashed ) {
                    return new WP_Error( 'mdg_reconcile_trash_failed', 'Ticket #' . $tid . ' trash durumuna alınamadı.' );
                }
            }

            clean_post_cache( $tid );

            if ( get_post_status( $tid ) !== 'trash'
                || get_post_meta( $tid, self::INVALID_META, true ) !== 'yes'
                || get_post_meta( $tid, self::INVALID_REASON_META, true ) !== $reason
            ) {
                return new WP_Error( 'mdg_reconcile_postcheck_failed', 'Ticket #' . $tid . ' uzlaştırma sonrası doğrulanamadı.' );
            }
        }

        return true;
    }

    private function refund_reconcile_case_preflight( $case_id ) {
        $r = [
            'ok' => false,
            'case_id' => absint( $case_id ),
            'order_id' => 0,
            'refund_id' => 0,
            'state' => '',
            'order' => false,
            'refund' => false,
            'snapshot' => null,
            'order_refunded' => false,
            'remaining_zero' => false,
            'full_amount_refunded' => false,
            'refund_object_valid' => false,
            'refund_parent_matches' => false,
            'tickets' => [],
            'reason' => '',
        ];

        $case = get_post( $case_id );
        if ( ! $case || $case->post_type !== self::REFUND_POST_TYPE ) return $r;

        $r['state'] = (string) get_post_meta( $case_id, '_mdg_v4_refund_state', true );
        $r['order_id'] = absint( get_post_meta( $case_id, '_mdg_v4_refund_order_id', true ) );
        $r['refund_id'] = absint( get_post_meta( $case_id, '_mdg_v4_refund_wc_refund_id', true ) );
        $r['snapshot'] = $this->refund_case_payload( $case_id );
        $r['reason'] = 'full_refund_v4_case_' . absint( $case_id );

        if ( ! $r['order_id'] || ! $r['refund_id'] || ! is_array( $r['snapshot'] ) ) return $r;

        $r['order'] = function_exists( 'wc_get_order' ) ? wc_get_order( $r['order_id'] ) : false;
        $r['refund'] = function_exists( 'wc_get_order' ) ? wc_get_order( $r['refund_id'] ) : false;

        if ( $r['order'] ) {
            $r['order_refunded'] = $r['order']->get_status() === 'refunded';
            $r['remaining_zero'] = (float) $r['order']->get_remaining_refund_amount() <= 0.01;
            $r['full_amount_refunded'] =
                abs( abs( (float) $r['order']->get_total_refunded() ) - (float) $r['order']->get_total() ) <= 0.01;
        }

        $r['refund_object_valid'] = $r['refund'] && is_a( $r['refund'], 'WC_Order_Refund' );
        if ( $r['refund_object_valid'] ) {
            $r['refund_parent_matches'] = (int) $r['refund']->get_parent_id() === (int) $r['order_id'];
        }

        $r['tickets'] = (array) ( $r['snapshot']['tickets'] ?? [] );

        $r['ok'] =
            $r['state'] === 'attention_required'
            && $r['order']
            && $r['order_refunded']
            && $r['remaining_zero']
            && $r['full_amount_refunded']
            && $r['refund_object_valid']
            && $r['refund_parent_matches']
            && count( $r['tickets'] ) > 0;

        return $r;
    }

    public function render_refund_admin() {
        $this->require_cap(); $this->styles();
        $order_id = absint( $_GET['order_id'] ?? 1846 );
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
        $pre = $this->refund_preflight( $order );

        echo '<div class="wrap mdgv4"><h1>İptal / İade Yönetimi <span class="mdgv4-small">V4.0.14</span></h1>';
        echo '<div class="mdgv4-danger"><strong>Gerçek para işlemi:</strong> Bu modül tam iade çalıştırıldığında ödeme sağlayıcısına iade çağrısı gönderir. Test sırasında gerçek siparişte “İADEYİ GERÇEKLEŞTİR” düğmesini kullanmayın. Önce yalnız dry-run/önizleme ve iki kullanıcı onay akışını doğrulayın.</div>';
        echo '<div class="mdgv4-note"><strong>V4 güvenlik modeli:</strong> yalnız tam iade; önce değişmez SHA-256 snapshot; talep eden yönetici kendi talebini onaylayamaz; ikinci farklı yönetici onayı gerekir; PayTR/ödeme geçidi otomatik iadeyi desteklemiyorsa gerçek iade düğmesi açılmaz. Gateway iadesi başarısız olursa ticket geçersizleştirmesi geri alınır. Gateway iadesi başarılı olup Tickera bir ticketı yeniden publish ederse V4 para iadesini tekrarlamadan yalnız ticket durumunu uzlaştırır.</div>';

        echo '<div class="mdgv4-card"><form method="get"><input type="hidden" name="page" value="mdg-v4-refund"><label><strong>Sipariş ID</strong><br><input type="number" name="order_id" min="1" value="'.esc_attr($order_id).'"></label> ';
        submit_button('DRY-RUN DENETLE','secondary','',false);
        echo '</form></div>';

        if ( ! $order ) {
            echo '<div class="mdgv4-danger">Sipariş #'.esc_html($order_id).' bulunamadı.</div></div>';
            return;
        }

        $g = $pre['gateway'];
        echo '<div class="mdgv4-card"><h2>Sipariş #'.esc_html($order_id).' — Dry-Run</h2><table class="widefat striped"><tbody>';
        $rows = [
            'WooCommerce durum' => '<code>'.esc_html($order->get_status()).'</code>',
            'Sipariş toplamı' => wp_kses_post( wc_price( $order->get_total(), [ 'currency'=>$order->get_currency() ] ) ),
            'Daha önce iade' => wp_kses_post( wc_price( $order->get_total_refunded(), [ 'currency'=>$order->get_currency() ] ) ),
            'Kalan tam iade tutarı' => wp_kses_post( wc_price( $order->get_remaining_refund_amount(), [ 'currency'=>$order->get_currency() ] ) ),
            'Ödeme yöntemi' => esc_html( $g['title'] ?: $g['id'] ),
            'Gateway sınıfı' => '<code>'.esc_html( $g['class'] ?: 'bulunamadı' ).'</code>',
            'Gateway otomatik iade desteği' => $this->yesno( $g['supports_refunds'] ),
            'Tickera ticket sayısı' => (string) count( $pre['tickets'] ),
            'Snapshot SHA-256' => '<code>'.esc_html( $pre['snapshot_hash'] ).'</code>',
        ];
        foreach ( $rows as $label=>$val ) echo '<tr><td>'.esc_html($label).'</td><td>'.$val.'</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-card"><h2>Güvenlik kapıları</h2><table class="widefat striped"><tbody>';
        $checks = [
            'Sipariş durumu tam iadeye uygun' => $pre['status_ok'],
            'Sipariş ödenmiş' => $pre['paid'],
            'Önceden kısmi/tam iade yok' => $pre['no_prior_refund'],
            'Kalan iade tutarı pozitif' => $pre['amount_positive'],
            'Kalan tutar sipariş toplamının tamamı' => $pre['full_amount_matches'],
            'Ödeme geçidi bulundu' => $g['found'],
            'Ödeme geçidi API üzerinden otomatik iade destekliyor' => $g['supports_refunds'],
            'Aktif Tickera ticket mevcut' => $pre['tickets_exist'],
            'Tüm ticketlar publish + QR mevcut + geçersiz değil' => $pre['tickets_all_active'],
            'WooCommerce ürün satırı mevcut' => $pre['line_items_exist'],
            'Açık V4 iade talebi yok' => $pre['no_live_case'],
        ];
        foreach ( $checks as $label=>$ok ) echo '<tr><td>'.esc_html($label).'</td><td>'.$this->yesno($ok).'</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-card"><h2>Ticketlar</h2><table class="widefat striped"><thead><tr><th>Ticket</th><th>Durum</th><th>event_id</th><th>ticket_type_id</th><th>QR</th><th>Geçersiz</th><th>Neden</th></tr></thead><tbody>';
        foreach ( $pre['tickets'] as $t ) {
            echo '<tr><td>#'.esc_html($t['id']).'</td><td><code>'.esc_html($t['post_status']).'</code></td><td>#'.esc_html($t['event_id']).'</td><td>#'.esc_html($t['ticket_type_id']).'</td><td>'.$this->yesno(!empty($t['ticket_code_hash'])).'</td><td>'.$this->yesno($t['invalidated']==='yes','Evet','Hayır').'</td><td>'.esc_html($t['invalid_reason']).'</td></tr>';
        }
        if ( ! $pre['tickets'] ) echo '<tr><td colspan="7">Ticket bulunamadı.</td></tr>';
        echo '</tbody></table></div>';

        $cases = $this->refund_existing_cases( $order_id );
        if ( $cases ) {
            echo '<div class="mdgv4-card"><h2>V4 iade kayıtları</h2><table class="widefat striped"><thead><tr><th>Kayıt</th><th>Durum</th><th>Talep eden</th><th>Onaylayan</th><th>Refund ID</th></tr></thead><tbody>';
            foreach ( $cases as $c ) {
                $state = (string) get_post_meta($c->ID,'_mdg_v4_refund_state',true);
                $req = absint(get_post_meta($c->ID,'_mdg_v4_refund_requester',true));
                $app = absint(get_post_meta($c->ID,'_mdg_v4_refund_approver',true));
                $rid = absint(get_post_meta($c->ID,'_mdg_v4_refund_wc_refund_id',true));
                echo '<tr><td>#'.esc_html($c->ID).'</td><td><code>'.esc_html($state).'</code></td><td>'.esc_html($this->refund_case_user_name($req)).'</td><td>'.($app?esc_html($this->refund_case_user_name($app)):'—').'</td><td>'.($rid?'#'.esc_html($rid):'—').'</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $live = $this->refund_live_case_for_order( $order_id );
        if ( ! $live && $pre['ready'] ) {
            $phrase = 'IADE TALEBI '.$order_id.' OLUSTUR';
            echo '<div class="mdgv4-card"><h2>1. Aşama — İade talebi oluştur</h2><p>Bu aşama <strong>para iadesi yapmaz</strong>; yalnız sipariş/ticket snapshotını kilitler.</p><p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('mdg_v4_refund_request_'.$order_id);
            echo '<input type="hidden" name="action" value="mdg_v4_refund_request"><input type="hidden" name="order_id" value="'.esc_attr($order_id).'">';
            echo '<label><strong>İade nedeni</strong><br><input type="text" name="reason" maxlength="180" style="width:620px;max-width:100%" value="Müşteri talebi / tam iade"></label><br><br>';
            echo '<input type="text" name="confirm" style="width:420px;max-width:100%" autocomplete="off"> ';
            submit_button('İADE TALEBİNİ KİLİTLE','primary','',false);
            echo '</form></div>';
        } elseif ( ! $live && ! $pre['ready'] ) {
            echo '<div class="mdgv4-note">Dry-run güvenlik kapılarının tamamı geçmeden yeni iade talebi oluşturulmaz.</div>';
        }

        if ( $live ) {
            $case_id = (int) $live->ID;
            $state = (string) get_post_meta($case_id,'_mdg_v4_refund_state',true);
            $requester = absint(get_post_meta($case_id,'_mdg_v4_refund_requester',true));
            $approver = absint(get_post_meta($case_id,'_mdg_v4_refund_approver',true));
            $reason = (string) get_post_meta($case_id,'_mdg_v4_refund_reason',true);
            $current = get_current_user_id();

            echo '<div class="mdgv4-card"><h2>Açık iade kaydı #'.esc_html($case_id).'</h2><table class="widefat striped"><tbody>';
            echo '<tr><td>Durum</td><td><code>'.esc_html($state).'</code></td></tr>';
            echo '<tr><td>Talep eden</td><td>'.esc_html($this->refund_case_user_name($requester)).'</td></tr>';
            echo '<tr><td>Onaylayan</td><td>'.($approver?esc_html($this->refund_case_user_name($approver)):'—').'</td></tr>';
            echo '<tr><td>Neden</td><td>'.esc_html($reason).'</td></tr>';
            echo '<tr><td>Snapshot SHA-256</td><td><code>'.esc_html((string)get_post_meta($case_id,'_mdg_v4_refund_snapshot_hash',true)).'</code></td></tr>';
            echo '</tbody></table>';

            if ( $state === 'pending_approval' ) {
                if ( $current === $requester ) {
                    echo '<div class="mdgv4-note"><strong>İkinci kişi gerekli:</strong> Bu talebi oluşturan kullanıcı kendi iadesini onaylayamaz. Farklı bir WooCommerce yöneticisi hesabıyla giriş yapıp aynı siparişi açın.</div>';
                } else {
                    $phrase = 'IADE ONAY '.$case_id.' '.$order_id;
                    echo '<h3>2. Aşama — Farklı yönetici onayı</h3><p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                    wp_nonce_field('mdg_v4_refund_approve_'.$case_id);
                    echo '<input type="hidden" name="action" value="mdg_v4_refund_approve"><input type="hidden" name="case_id" value="'.esc_attr($case_id).'">';
                    echo '<input type="text" name="confirm" style="width:420px;max-width:100%" autocomplete="off"> ';
                    submit_button('İKİNCİ KİŞİ ONAYINI VER','primary','',false);
                    echo '</form>';
                }
                if ( $current === $requester ) {
                    echo '<hr>';
                    $this->render_refund_case_cancel_form( $case_id, $order_id, 'TALEBİ İPTAL ET' );
                }
            }


            if ( $state === 'attention_required' ) {
                $rec = $this->refund_reconcile_case_preflight( $case_id );
                echo '<h3>İade Sonrası Ticket Uzlaştırma</h3>';
                echo '<div class="mdgv4-safe"><strong>Para iadesini tekrarlamaz.</strong> Bu araç yalnız mevcut WooCommerce refund kaydını ve tam iade durumunu doğrular; sonra ilk snapshotta yer alan Tickera ticketlarını geçersiz + trash durumuna sabitler.</div>';
                echo '<table class="widefat striped"><tbody>';
                echo '<tr><td>WooCommerce Refund ID</td><td>'.($rec['refund_id']?'#'.esc_html($rec['refund_id']):'—').'</td></tr>';
                echo '<tr><td>Sipariş refunded</td><td>'.$this->yesno($rec['order_refunded']).'</td></tr>';
                echo '<tr><td>Kalan iade 0</td><td>'.$this->yesno($rec['remaining_zero']).'</td></tr>';
                echo '<tr><td>Toplamın tamamı iade edilmiş</td><td>'.$this->yesno($rec['full_amount_refunded']).'</td></tr>';
                echo '<tr><td>Refund nesnesi geçerli</td><td>'.$this->yesno($rec['refund_object_valid']).'</td></tr>';
                echo '<tr><td>Refund bu siparişe ait</td><td>'.$this->yesno($rec['refund_parent_matches']).'</td></tr>';
                echo '<tr><td>Snapshot ticket sayısı</td><td>'.esc_html(count($rec['tickets'])).'</td></tr>';
                echo '</tbody></table>';

                if ( $rec['ok'] ) {
                    $phrase = 'TICKET UZLASTIR '.$case_id.' '.$order_id.' ONAYLIYORUM';
                    echo '<p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                    wp_nonce_field('mdg_v4_refund_reconcile_'.$case_id);
                    echo '<input type="hidden" name="action" value="mdg_v4_refund_reconcile">';
                    echo '<input type="hidden" name="case_id" value="'.esc_attr($case_id).'">';
                    echo '<input type="text" name="confirm" style="width:520px;max-width:100%" autocomplete="off"> ';
                    submit_button('YALNIZ TICKET DURUMUNU UZLAŞTIR','primary','',false);
                    echo '</form>';
                } else {
                    echo '<div class="mdgv4-danger">Uzlaştırma güvenlik kapısı kapalı. Para iadesi veya ticket üzerinde otomatik işlem yapılmadı.</div>';
                }
            }

            if ( $state === 'approved' ) {
                $invariants = $this->refund_case_invariants_ok( $case_id, $order );
                echo '<h3>3. Aşama — Gerçek tam iade</h3>';
                echo '<p>Snapshot hâlâ değişmemiş: '.$this->yesno($invariants).'</p>';

                if ( $current === $requester ) {
                    echo '<div class="mdgv4-danger">Talep eden kullanıcı gerçek iadeyi çalıştıramaz. İadeyi, ikinci onayı veren farklı yönetici hesabı çalıştırmalıdır.</div>';
                } else {
                    if ( $invariants && $pre['gateway']['supports_refunds'] ) {
                        $phrase = 'GERCEK TAM IADE '.$case_id.' '.$order_id.' ONAYLIYORUM';
                        echo '<div class="mdgv4-danger"><strong>Bu düğme gerçek para iadesidir.</strong> Ödeme geçidine API iadesi gönderilir, WooCommerce ürünleri stokta geri kazanılır ve Tickera ticketları geçersizleştirilip çöp durumuna alınır. Başarılı ödeme iadesinin otomatik geri dönüşü yoktur.</div>';
                        echo '<p>Onay için aynen yazın:<br><code>'.esc_html($phrase).'</code></p>';
                        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                        wp_nonce_field('mdg_v4_refund_execute_'.$case_id);
                        echo '<input type="hidden" name="action" value="mdg_v4_refund_execute"><input type="hidden" name="case_id" value="'.esc_attr($case_id).'">';
                        echo '<input type="text" name="confirm" style="width:540px;max-width:100%" autocomplete="off"> ';
                        submit_button('İADEYİ GERÇEKLEŞTİR','delete','',false);
                        echo '</form>';
                    } else {
                        echo '<div class="mdgv4-danger">Gerçek iade güvenlik kapısı kapalı: sipariş/ticket snapshotı değişmiş veya ödeme geçidi otomatik iadeyi desteklemiyor.</div>';
                    }
                }

                if ( $current === $requester || $current === $approver ) {
                    echo '<hr><div class="mdgv4-note"><strong>Güvenli vazgeçme:</strong> Gerçek iade henüz çalıştırılmadıysa onaylanmış talep kapatılabilir. Bu işlem siparişe, PayTR’ye veya ticketlara dokunmaz.</div>';
                    $this->render_refund_case_cancel_form( $case_id, $order_id, 'ONAYLI TALEPTEN VAZGEÇ' );
                }
            }
            echo '</div>';
        }

        echo '</div>';
    }


    private function render_refund_case_cancel_form( $case_id, $order_id, $label = 'TALEBİ İPTAL ET' ) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:14px">';
        wp_nonce_field('mdg_v4_refund_cancel_'.$case_id);
        echo '<input type="hidden" name="action" value="mdg_v4_refund_cancel_case">';
        echo '<input type="hidden" name="case_id" value="'.esc_attr($case_id).'">';
        submit_button( $label, 'secondary', '', false );
        echo '</form>';
    }

    public function handle_refund_request() {
        $this->require_cap();
        $order_id = absint($_POST['order_id'] ?? 0);
        check_admin_referer('mdg_v4_refund_request_'.$order_id);
        $phrase = 'IADE TALEBI '.$order_id.' OLUSTUR';
        $confirm = trim(sanitize_text_field(wp_unslash($_POST['confirm'] ?? '')));
        if ( ! hash_equals($phrase,$confirm) ) wp_die('Onay cümlesi eşleşmedi. Hiçbir kayıt oluşturulmadı.');

        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        $pre = $this->refund_preflight($order);
        if ( ! $pre['ready'] ) wp_die('İade dry-run güvenlik kapısı geçmedi. Hiçbir işlem yapılmadı.');

        $reason = trim(sanitize_text_field(wp_unslash($_POST['reason'] ?? '')));
        if ( $reason === '' ) $reason = 'Müşteri talebi / tam iade';

        $json = $this->refund_snapshot_json($pre['snapshot']);
        $hash = hash('sha256',$json);
        $case_id = wp_insert_post([
            'post_type' => self::REFUND_POST_TYPE,
            'post_status' => 'private',
            'post_title' => 'V4 Tam İade Talebi | Sipariş #'.$order_id,
            'post_author' => get_current_user_id(),
        ], true);
        if ( is_wp_error($case_id) || ! $case_id ) wp_die(is_wp_error($case_id)?esc_html($case_id->get_error_message()):'İade kaydı oluşturulamadı.');

        update_post_meta($case_id,'_mdg_v4_refund_order_id',$order_id);
        update_post_meta($case_id,'_mdg_v4_refund_state','pending_approval');
        update_post_meta($case_id,'_mdg_v4_refund_requester',get_current_user_id());
        update_post_meta($case_id,'_mdg_v4_refund_reason',$reason);
        update_post_meta($case_id,'_mdg_v4_refund_snapshot',$json);
        update_post_meta($case_id,'_mdg_v4_refund_snapshot_hash',$hash);
        update_post_meta($case_id,'_mdg_v4_refund_created_utc',gmdate('c'));

        wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-refund','order_id'=>$order_id,'case_id'=>$case_id,'result'=>'requested'],admin_url('admin.php')));
        exit;
    }

    public function handle_refund_approve() {
        $this->require_cap();
        $case_id = absint($_POST['case_id'] ?? 0);
        check_admin_referer('mdg_v4_refund_approve_'.$case_id);
        $case = get_post($case_id);
        if ( ! $case || $case->post_type !== self::REFUND_POST_TYPE ) wp_die('İade kaydı bulunamadı.');
        if ( get_post_meta($case_id,'_mdg_v4_refund_state',true) !== 'pending_approval' ) wp_die('Bu kayıt ikinci onay beklemiyor.');

        $order_id = absint(get_post_meta($case_id,'_mdg_v4_refund_order_id',true));
        $requester = absint(get_post_meta($case_id,'_mdg_v4_refund_requester',true));
        $current = get_current_user_id();
        if ( !$current || $current === $requester ) wp_die('Talep eden kullanıcı kendi tam iade talebini onaylayamaz.');

        $phrase = 'IADE ONAY '.$case_id.' '.$order_id;
        $confirm = trim(sanitize_text_field(wp_unslash($_POST['confirm'] ?? '')));
        if ( ! hash_equals($phrase,$confirm) ) wp_die('İkinci kişi onay cümlesi eşleşmedi.');

        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        $pre = $this->refund_preflight($order);
        if ( !$order || !$this->refund_case_invariants_ok($case_id,$order) ) wp_die('Sipariş/ticket snapshotı ilk talepten sonra değişmiş. Onay verilmedi.');
        if ( !$pre['gateway']['supports_refunds'] ) wp_die('Ödeme geçidi otomatik iade desteği vermiyor. Onay verilmedi.');

        update_post_meta($case_id,'_mdg_v4_refund_state','approved');
        update_post_meta($case_id,'_mdg_v4_refund_approver',$current);
        update_post_meta($case_id,'_mdg_v4_refund_approved_utc',gmdate('c'));

        wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-refund','order_id'=>$order_id,'case_id'=>$case_id,'result'=>'approved'],admin_url('admin.php')));
        exit;
    }

    public function handle_refund_cancel_case() {
        $this->require_cap();
        $case_id = absint($_POST['case_id'] ?? 0);
        check_admin_referer('mdg_v4_refund_cancel_'.$case_id);
        $case = get_post($case_id);
        if ( ! $case || $case->post_type !== self::REFUND_POST_TYPE ) wp_die('İade kaydı bulunamadı.');

        $state = (string) get_post_meta($case_id,'_mdg_v4_refund_state',true);
        if ( ! in_array($state,[ 'pending_approval','approved' ],true) ) {
            wp_die('Yalnız ikinci onay bekleyen veya onaylanmış fakat henüz çalıştırılmamış talep kapatılabilir.');
        }

        $requester = absint(get_post_meta($case_id,'_mdg_v4_refund_requester',true));
        $approver = absint(get_post_meta($case_id,'_mdg_v4_refund_approver',true));
        $current = get_current_user_id();

        if ( $state === 'pending_approval' && $current !== $requester ) {
            wp_die('İkinci onay bekleyen talebi yalnız talebi oluşturan kullanıcı iptal edebilir.');
        }
        if ( $state === 'approved' && $current !== $requester && $current !== $approver ) {
            wp_die('Onaylanmış talebi yalnız talep eden veya ikinci onayı veren kullanıcı kapatabilir.');
        }

        if ( absint(get_post_meta($case_id,'_mdg_v4_refund_wc_refund_id',true)) > 0 ) {
            wp_die('Bu kayıt için WooCommerce refund kaydı oluşmuş. Güvenli vazgeçme yapılamaz.');
        }
        if ( get_post_meta($case_id,'_mdg_v4_refund_execute_started_utc',true) ) {
            wp_die('Gerçek iade çalıştırılmaya başlanmış. Güvenli vazgeçme yapılamaz.');
        }

        $order_id = absint(get_post_meta($case_id,'_mdg_v4_refund_order_id',true));
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        if ( !$order ) wp_die('Sipariş bulunamadı; talep otomatik kapatılmadı.');
        if ( abs((float)$order->get_total_refunded()) > 0.00001 ) {
            wp_die('Siparişte iade hareketi bulundu. Talep otomatik kapatılmadı.');
        }

        update_post_meta($case_id,'_mdg_v4_refund_state','cancelled');
        update_post_meta($case_id,'_mdg_v4_refund_cancelled_by',$current);
        update_post_meta($case_id,'_mdg_v4_refund_cancelled_from',$state);
        update_post_meta($case_id,'_mdg_v4_refund_cancelled_utc',gmdate('c'));

        wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-refund','order_id'=>$order_id,'result'=>'case_cancelled'],admin_url('admin.php')));
        exit;
    }


    public function handle_refund_reconcile() {
        $this->require_cap();
        $case_id = absint($_POST['case_id'] ?? 0);
        check_admin_referer('mdg_v4_refund_reconcile_'.$case_id);

        $rec = $this->refund_reconcile_case_preflight( $case_id );
        if ( ! $rec['ok'] ) {
            wp_die('Uzlaştırma güvenlik kapısı geçmedi. PayTR veya ticket üzerinde işlem yapılmadı.');
        }

        $phrase = 'TICKET UZLASTIR '.$case_id.' '.$rec['order_id'].' ONAYLIYORUM';
        $confirm = trim(sanitize_text_field(wp_unslash($_POST['confirm'] ?? '')));
        if ( ! hash_equals($phrase,$confirm) ) {
            wp_die('Uzlaştırma onay cümlesi eşleşmedi. Hiçbir işlem yapılmadı.');
        }

        // Bu handler wc_create_refund(), gateway process_refund() veya herhangi bir ödeme API çağrısı YAPMAZ.
        $result = $this->refund_reconcile_tickets_after_full_refund(
            $rec['tickets'],
            $case_id
        );

        if ( is_wp_error($result) ) {
            update_post_meta($case_id,'_mdg_v4_refund_state','attention_required');
            update_post_meta($case_id,'_mdg_v4_refund_last_error',$result->get_error_message());
            wp_die('Ticket uzlaştırma tamamlanamadı: '.esc_html($result->get_error_message()));
        }

        $order = wc_get_order($rec['order_id']);
        $remaining = $order ? (float)$order->get_remaining_refund_amount() : -1;
        $ticket_ok = true;
        foreach ( $rec['tickets'] as $t ) {
            $tid = absint($t['id'] ?? 0);
            if ( get_post_status($tid) !== 'trash'
                || get_post_meta($tid,self::INVALID_META,true) !== 'yes'
                || get_post_meta($tid,self::INVALID_REASON_META,true) !== $rec['reason']
            ) {
                $ticket_ok = false;
            }
        }

        if ( $order
            && $order->get_status() === 'refunded'
            && $remaining <= 0.01
            && $ticket_ok
        ) {
            update_post_meta($case_id,'_mdg_v4_refund_state','success');
            update_post_meta($case_id,'_mdg_v4_refund_reconciled_utc',gmdate('c'));
            update_post_meta($case_id,'_mdg_v4_refund_reconciled_by',get_current_user_id());
            delete_post_meta($case_id,'_mdg_v4_refund_last_error');
        } else {
            update_post_meta($case_id,'_mdg_v4_refund_state','attention_required');
            update_post_meta($case_id,'_mdg_v4_refund_last_error','Manuel uzlaştırma sonrası post-check geçmedi. remaining='.$remaining.' ticket_ok='.($ticket_ok?'1':'0'));
        }

        wp_safe_redirect(add_query_arg([
            'page'=>'mdg-v4-refund',
            'order_id'=>$rec['order_id'],
            'case_id'=>$case_id,
            'result'=>'ticket_reconciled',
        ],admin_url('admin.php')));
        exit;
    }

    public function handle_refund_execute() {
        $this->require_cap();
        $case_id = absint($_POST['case_id'] ?? 0);
        check_admin_referer('mdg_v4_refund_execute_'.$case_id);
        $case = get_post($case_id);
        if ( ! $case || $case->post_type !== self::REFUND_POST_TYPE ) wp_die('İade kaydı bulunamadı.');
        if ( get_post_meta($case_id,'_mdg_v4_refund_state',true) !== 'approved' ) wp_die('İade kaydı ikinci kişi tarafından onaylanmamış.');

        $order_id = absint(get_post_meta($case_id,'_mdg_v4_refund_order_id',true));
        $requester = absint(get_post_meta($case_id,'_mdg_v4_refund_requester',true));
        $approver = absint(get_post_meta($case_id,'_mdg_v4_refund_approver',true));
        $current = get_current_user_id();
        if ( !$approver || $approver === $requester ) wp_die('İki farklı kullanıcı onayı doğrulanamadı.');
        if ( $current === $requester ) wp_die('Talep eden kullanıcı gerçek iadeyi çalıştıramaz.');

        $phrase = 'GERCEK TAM IADE '.$case_id.' '.$order_id.' ONAYLIYORUM';
        $confirm = trim(sanitize_text_field(wp_unslash($_POST['confirm'] ?? '')));
        if ( ! hash_equals($phrase,$confirm) ) wp_die('Gerçek iade onay cümlesi eşleşmedi.');

        $lock = 'mdg_v406_refund_'.$case_id;
        if ( ! add_option($lock,(string)time(),'','no') ) wp_die('Bu iade kaydı şu anda başka bir işlem tarafından çalıştırılıyor.');

        try {
            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
            if ( !$order ) throw new Exception('Sipariş bulunamadı.');
            if ( !$this->refund_case_invariants_ok($case_id,$order) ) throw new Exception('Sipariş/ticket snapshotı değişmiş.');
            $pre = $this->refund_preflight($order);
            if ( !$pre['paid'] || !$pre['no_prior_refund'] || !$pre['full_amount_matches'] || !$pre['gateway']['supports_refunds'] || !$pre['tickets_all_active'] ) {
                throw new Exception('Son güvenlik kapısı geçmedi.');
            }

            update_post_meta($case_id,'_mdg_v4_refund_state','executing');
            update_post_meta($case_id,'_mdg_v4_refund_execute_user',$current);
            update_post_meta($case_id,'_mdg_v4_refund_execute_started_utc',gmdate('c'));

            $ticket_snapshots = $pre['snapshot']['tickets'];
            $invalidated = $this->refund_invalidate_tickets($ticket_snapshots,$case_id);
            if ( is_wp_error($invalidated) ) throw new Exception($invalidated->get_error_message());

            $refund = wc_create_refund([
                'amount'         => wc_format_decimal($order->get_remaining_refund_amount()),
                'reason'         => (string) get_post_meta($case_id,'_mdg_v4_refund_reason',true),
                'order_id'       => $order_id,
                'line_items'     => $pre['line_items'],
                'refund_payment' => true,
                'restock_items'  => true,
            ]);

            if ( is_wp_error($refund) ) {
                $this->refund_restore_tickets($ticket_snapshots);
                update_post_meta($case_id,'_mdg_v4_refund_state','approved');
                update_post_meta($case_id,'_mdg_v4_refund_last_error',$refund->get_error_message());
                throw new Exception('Ödeme geçidi iadesi başarısız; ticketlar eski durumuna döndürüldü. '.$refund->get_error_message());
            }
            if ( !$refund || ! is_a($refund,'WC_Order_Refund') ) {
                $this->refund_restore_tickets($ticket_snapshots);
                update_post_meta($case_id,'_mdg_v4_refund_state','approved');
                throw new Exception('WooCommerce refund kaydı oluşturulamadı; ticketlar eski durumuna döndürüldü.');
            }

            update_post_meta($case_id,'_mdg_v4_refund_wc_refund_id',$refund->get_id());
            update_post_meta($case_id,'_mdg_v4_refund_completed_utc',gmdate('c'));

            // Gateway/WooCommerce hookları ticket durumunu değiştirmiş olabilir.
            // Para iadesi başarıyla oluştuğu için burada yalnız ticket state idempotent biçimde uzlaştırılır.
            $reconciled = $this->refund_reconcile_tickets_after_full_refund($ticket_snapshots,$case_id);

            $order = wc_get_order($order_id);
            if ( $order && $order->get_status() !== 'refunded' && (float)$order->get_remaining_refund_amount() <= 0.01 ) {
                $order->update_status('refunded','Madagaskar V4 tam iade doğrulaması tamamlandı.',true);
                $order = wc_get_order($order_id);
            }

            // Sipariş statüsü update hookları ticketı yeniden publish etmişse bir kez daha uzlaştır.
            if ( ! is_wp_error($reconciled) ) {
                $reconciled = $this->refund_reconcile_tickets_after_full_refund($ticket_snapshots,$case_id);
            }

            $remaining = $order ? (float)$order->get_remaining_refund_amount() : -1;
            $ticket_ok = ! is_wp_error($reconciled);
            if ( $ticket_ok ) {
                foreach ( $ticket_snapshots as $t ) {
                    $tid = absint($t['id']);
                    if ( get_post_status($tid) !== 'trash'
                        || get_post_meta($tid,self::INVALID_META,true) !== 'yes'
                        || get_post_meta($tid,self::INVALID_REASON_META,true) !== 'full_refund_v4_case_'.absint($case_id)
                    ) {
                        $ticket_ok = false;
                    }
                }
            }

            if ( $remaining <= 0.01 && $ticket_ok ) {
                update_post_meta($case_id,'_mdg_v4_refund_state','success');
                delete_post_meta($case_id,'_mdg_v4_refund_last_error');
                update_post_meta($case_id,'_mdg_v4_refund_reconciled_utc',gmdate('c'));
            } else {
                update_post_meta($case_id,'_mdg_v4_refund_state','attention_required');
                $reconcile_error = is_wp_error($reconciled) ? $reconciled->get_error_message() : '';
                update_post_meta(
                    $case_id,
                    '_mdg_v4_refund_last_error',
                    'Provider refund oluşturuldu fakat post-check tam geçmedi. remaining='.$remaining.' ticket_ok='.($ticket_ok?'1':'0').' '.$reconcile_error
                );
            }

            delete_option($lock);
            wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-refund','order_id'=>$order_id,'case_id'=>$case_id,'result'=>'refund_complete'],admin_url('admin.php')));
            exit;
        } catch ( Throwable $e ) {
            delete_option($lock);
            wp_die('Tam iade durduruldu. '.$e->getMessage());
        }
    }

    /* -------------------- INTEGRATIONS -------------------- */

    public function render_integrations() {
        $this->require_cap();$this->styles();$d=$this->deps();
        echo '<div class="wrap mdgv4"><h1>Entegrasyonlar</h1><div class="mdgv4-card"><table class="widefat striped"><thead><tr><th>Katman</th><th>Durum</th><th>V4 görevi</th></tr></thead><tbody>';
        $rows=[['WooCommerce',$d['woocommerce'],'Sipariş / ürün / varyasyon / tam iade'],['PayTR',$d['paytr'],'Ödeme ve gateway iade desteği'],['Tickera',$d['tickera'],'Bilet / QR / PDF'],['Tickera Bridge',$d['bridge'],'WooCommerce ↔ Tickera'],['Checkinera',$d['checkinera'],'Giriş / check-in'],['Biletlerim',$d['biletlerim_legacy']||true,'Güvenli müşteri bağlantısı'],['WhatsApp',true,'+90 312 911 37 10']];
        foreach($rows as $r)echo '<tr><td>'.esc_html($r[0]).'</td><td>'.$this->yesno($r[1]).'</td><td>'.esc_html($r[2]).'</td></tr>';echo '</tbody></table></div></div>';
    }

    /* -------------------- MIGRATION -------------------- */


    private function retirement_legacy_plugin_file() {
        return WP_PLUGIN_DIR . '/madagaskar-bilet-yonetimi/madagaskar-bilet-yonetimi.php';
    }

    private function retirement_v4_root() {
        return wp_normalize_path( plugin_dir_path( __FILE__ ) );
    }

    private function retirement_legacy_root() {
        return wp_normalize_path( dirname( $this->retirement_legacy_plugin_file() ) . '/' );
    }

    private function retirement_path_is_under( $file, $root ) {
        if ( ! $file || ! $root ) return false;
        $file = wp_normalize_path( $file );
        $root = trailingslashit( wp_normalize_path( $root ) );
        return 0 === strpos( $file, $root );
    }

    private function retirement_callback_file( $callback ) {
        try {
            if ( $callback instanceof Closure ) {
                $r = new ReflectionFunction( $callback );
                return $r->getFileName();
            }

            if ( is_string( $callback ) && function_exists( $callback ) ) {
                $r = new ReflectionFunction( $callback );
                return $r->getFileName();
            }

            if ( is_array( $callback ) && count( $callback ) === 2 ) {
                $target = $callback[0];
                $method = $callback[1];
                if ( is_object( $target ) || is_string( $target ) ) {
                    $r = new ReflectionMethod( $target, $method );
                    return $r->getFileName();
                }
            }

            if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
                $r = new ReflectionMethod( $callback, '__invoke' );
                return $r->getFileName();
            }
        } catch ( Throwable $e ) {
            return '';
        }

        return '';
    }

    private function retirement_callback_label( $callback ) {
        if ( $callback instanceof Closure ) return 'Closure';

        if ( is_string( $callback ) ) return $callback;

        if ( is_array( $callback ) && count( $callback ) === 2 ) {
            $left = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
            return $left . '::' . (string) $callback[1];
        }

        if ( is_object( $callback ) ) return get_class( $callback ) . '::__invoke';

        return 'Bilinmeyen callback';
    }

    private function retirement_runtime_callbacks( $root ) {
        global $wp_filter;

        $rows = [];
        if ( ! is_array( $wp_filter ) ) return $rows;

        foreach ( $wp_filter as $hook_name => $hook ) {
            if ( ! is_object( $hook ) || empty( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) continue;

            foreach ( $hook->callbacks as $priority => $items ) {
                foreach ( (array) $items as $item ) {
                    if ( ! isset( $item['function'] ) ) continue;
                    $file = $this->retirement_callback_file( $item['function'] );
                    if ( ! $this->retirement_path_is_under( $file, $root ) ) continue;

                    $rows[] = [
                        'hook' => (string) $hook_name,
                        'priority' => (int) $priority,
                        'accepted_args' => isset( $item['accepted_args'] ) ? (int) $item['accepted_args'] : 0,
                        'callback' => $this->retirement_callback_label( $item['function'] ),
                        'file' => wp_normalize_path( (string) $file ),
                    ];
                }
            }
        }

        usort( $rows, function( $a, $b ) {
            $x = strcmp( $a['hook'], $b['hook'] );
            if ( 0 !== $x ) return $x;
            return $a['priority'] <=> $b['priority'];
        } );

        return $rows;
    }

    private function retirement_runtime_shortcodes( $root ) {
        global $shortcode_tags;

        $rows = [];
        foreach ( (array) $shortcode_tags as $tag => $callback ) {
            $file = $this->retirement_callback_file( $callback );
            if ( ! $this->retirement_path_is_under( $file, $root ) ) continue;

            $rows[] = [
                'tag' => (string) $tag,
                'callback' => $this->retirement_callback_label( $callback ),
                'file' => wp_normalize_path( (string) $file ),
            ];
        }

        usort( $rows, fn( $a, $b ) => strcmp( $a['tag'], $b['tag'] ) );
        return $rows;
    }

    private function retirement_php_files( $root, $max_files = 800 ) {
        $root = wp_normalize_path( $root );
        if ( ! is_dir( $root ) ) return [];

        $files = [];
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
                    function( $current, $key, $iterator ) {
                        if ( $current->isDir() ) {
                            $n = strtolower( $current->getFilename() );
                            if ( in_array( $n, [ 'vendor', 'node_modules', 'tests', 'test', '.git' ], true ) ) return false;
                        }
                        return true;
                    }
                )
            );

            foreach ( $it as $file ) {
                if ( count( $files ) >= $max_files ) break;
                if ( ! $file->isFile() ) continue;
                if ( strtolower( $file->getExtension() ) !== 'php' ) continue;
                $files[] = wp_normalize_path( $file->getPathname() );
            }
        } catch ( Throwable $e ) {
            return $files;
        }

        sort( $files );
        return $files;
    }

    private function retirement_parse_source_bundle( $root ) {
        $files = $this->retirement_php_files( $root, 500 );
        $functions = [];
        $classes = [];
        $constants = [];
        $hooks = [];
        $shortcodes = [];
        $hash_parts = [];
        $total_bytes = 0;

        foreach ( $files as $file ) {
            $size = @filesize( $file );
            if ( false === $size || $size > 2 * 1024 * 1024 ) continue;

            $src = @file_get_contents( $file );
            if ( false === $src ) continue;

            $total_bytes += strlen( $src );
            $hash_parts[] = str_replace( wp_normalize_path( $root ), '', $file ) . ':' . hash( 'sha256', $src );

            if ( preg_match_all( '/\badd_(?:action|filter)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $src, $m ) ) {
                foreach ( $m[1] as $h ) $hooks[$h] = true;
            }
            if ( preg_match_all( '/\badd_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $src, $m ) ) {
                foreach ( $m[1] as $h ) $shortcodes[$h] = true;
            }
            if ( preg_match_all( '/\bdefine\s*\(\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]/i', $src, $m ) ) {
                foreach ( $m[1] as $c ) $constants[$c] = true;
            }

            try {
                $tokens = token_get_all( $src );
                $n = count( $tokens );
                $brace = 0;
                $class_depths = [];
                $pending_class = false;

                for ( $i = 0; $i < $n; $i++ ) {
                    $t = $tokens[$i];

                    if ( is_string( $t ) ) {
                        if ( $t === '{' ) {
                            $brace++;
                            if ( $pending_class ) {
                                $class_depths[] = $brace;
                                $pending_class = false;
                            }
                        } elseif ( $t === '}' ) {
                            if ( $class_depths && end( $class_depths ) === $brace ) array_pop( $class_depths );
                            $brace--;
                        }
                        continue;
                    }

                    if ( in_array( $t[0], [ T_CLASS, T_INTERFACE, T_TRAIT ], true ) ) {
                        for ( $j = $i + 1; $j < min( $n, $i + 12 ); $j++ ) {
                            if ( is_array( $tokens[$j] ) && $tokens[$j][0] === T_STRING ) {
                                $classes[$tokens[$j][1]] = true;
                                $pending_class = true;
                                break;
                            }
                        }
                        continue;
                    }

                    if ( $t[0] === T_FUNCTION && empty( $class_depths ) ) {
                        for ( $j = $i + 1; $j < min( $n, $i + 12 ); $j++ ) {
                            if ( is_array( $tokens[$j] ) && $tokens[$j][0] === T_STRING ) {
                                $functions[$tokens[$j][1]] = true;
                                break;
                            }
                            if ( is_string( $tokens[$j] ) && $tokens[$j] === '(' ) break; // anonymous function
                        }
                    }
                }
            } catch ( Throwable $e ) {
                // Static regex results are still useful.
            }
        }

        ksort( $functions );
        ksort( $classes );
        ksort( $constants );
        ksort( $hooks );
        ksort( $shortcodes );
        sort( $hash_parts );

        return [
            'files' => $files,
            'file_count' => count( $files ),
            'total_bytes' => $total_bytes,
            'bundle_sha256' => hash( 'sha256', implode( "\n", $hash_parts ) ),
            'functions' => array_keys( $functions ),
            'classes' => array_keys( $classes ),
            'constants' => array_keys( $constants ),
            'hooks' => array_keys( $hooks ),
            'shortcodes' => array_keys( $shortcodes ),
        ];
    }

    private function retirement_external_scan_roots() {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $roots = [];
        $active = (array) get_option( 'active_plugins', [] );
        if ( is_multisite() ) {
            $active = array_unique( array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) ) );
        }

        $legacy_file = wp_normalize_path( $this->retirement_legacy_plugin_file() );
        $v4_file = wp_normalize_path( __FILE__ );

        foreach ( $active as $base ) {
            $main = wp_normalize_path( WP_PLUGIN_DIR . '/' . $base );
            if ( $main === $legacy_file || $main === $v4_file ) continue;
            $root = is_dir( dirname( $main ) ) ? dirname( $main ) : $main;
            $roots[$root] = 'Aktif eklenti: ' . $base;
        }

        if ( defined( 'WPMU_PLUGIN_DIR' ) && is_dir( WPMU_PLUGIN_DIR ) ) {
            $roots[wp_normalize_path( WPMU_PLUGIN_DIR )] = 'MU eklentileri';
        }

        if ( function_exists( 'get_stylesheet_directory' ) ) {
            $roots[wp_normalize_path( get_stylesheet_directory() )] = 'Aktif tema/child theme';
        }
        if ( function_exists( 'get_template_directory' ) ) {
            $roots[wp_normalize_path( get_template_directory() )] = 'Parent tema';
        }

        // Do not scan the legacy plugin or V4 itself if roots overlap.
        unset( $roots[$this->retirement_legacy_root()] );
        unset( $roots[$this->retirement_v4_root()] );

        return $roots;
    }

    private function retirement_external_symbol_refs( $symbols ) {
        $symbols = array_values( array_unique( array_filter( array_map( 'strval', (array) $symbols ), function( $s ) {
            return strlen( $s ) >= 5;
        } ) ) );

        // Keep the scan bounded and focused on plugin-owned API-like symbols.
        usort( $symbols, fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
        $symbols = array_slice( $symbols, 0, 160 );

        if ( ! $symbols ) return [ 'symbols_scanned' => [], 'refs' => [], 'files_scanned' => 0, 'truncated' => false ];

        $regex = '/(?<![A-Za-z0-9_])(' . implode( '|', array_map( function( $s ) {
            return preg_quote( $s, '/' );
        }, $symbols ) ) . ')(?![A-Za-z0-9_])/i';

        $refs = [];
        $files_scanned = 0;
        $truncated = false;

        foreach ( $this->retirement_external_scan_roots() as $root => $label ) {
            foreach ( $this->retirement_php_files( $root, 900 ) as $file ) {
                $files_scanned++;
                if ( $files_scanned > 2500 ) {
                    $truncated = true;
                    break 2;
                }

                $size = @filesize( $file );
                if ( false === $size || $size > 2 * 1024 * 1024 ) continue;
                $src = @file_get_contents( $file );
                if ( false === $src ) continue;

                if ( preg_match_all( $regex, $src, $m ) ) {
                    foreach ( array_unique( $m[1] ) as $symbol ) {
                        $refs[] = [
                            'symbol' => $symbol,
                            'source' => $label,
                            'file' => wp_normalize_path( $file ),
                        ];
                        if ( count( $refs ) >= 100 ) {
                            $truncated = true;
                            break 3;
                        }
                    }
                }
            }
        }

        return [
            'symbols_scanned' => $symbols,
            'refs' => $refs,
            'files_scanned' => $files_scanned,
            'truncated' => $truncated,
        ];
    }

    private function retirement_is_critical_hook( $hook ) {
        return (bool) preg_match(
            '/^(?:init|wp|wp_loaded|template_redirect|parse_request|query_vars|the_content|rest_api_init|woocommerce_|wp_ajax_|admin_post_|status_header|wp_headers|send_headers)/i',
            (string) $hook
        );
    }

    private function retirement_shortcode_post_usage( $tags ) {
        global $wpdb;
        $out = [];

        foreach ( array_slice( (array) $tags, 0, 30 ) as $tag ) {
            $needle = '%[' . $wpdb->esc_like( $tag ) . '%';
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s",
                    $needle
                )
            );
            $out[$tag] = $count;
        }

        return $out;
    }

    public function render_retirement_audit() {
        $this->require_cap();
        $this->styles();

        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        $legacy_file = wp_normalize_path( $this->retirement_legacy_plugin_file() );
        $legacy_root = $this->retirement_legacy_root();
        $v4_root = $this->retirement_v4_root();
        $legacy_base = plugin_basename( $legacy_file );

        $found = is_file( $legacy_file );
        $active = $found && is_plugin_active( $legacy_base );
        $readable = $found && is_readable( $legacy_file );
        $v4_active = is_plugin_active( plugin_basename( __FILE__ ) );

        $legacy_data = $found ? get_plugin_data( $legacy_file, false, false ) : [];
        $bundle = $found ? $this->retirement_parse_source_bundle( $legacy_root ) : [
            'file_count'=>0,'total_bytes'=>0,'bundle_sha256'=>'','functions'=>[],'classes'=>[],'constants'=>[],'hooks'=>[],'shortcodes'=>[]
        ];

        $legacy_runtime = $found ? $this->retirement_runtime_callbacks( $legacy_root ) : [];
        $v4_runtime = $this->retirement_runtime_callbacks( $v4_root );
        $legacy_shortcodes = $found ? $this->retirement_runtime_shortcodes( $legacy_root ) : [];
        $v4_shortcodes = $this->retirement_runtime_shortcodes( $v4_root );

        $v4_hook_names = [];
        foreach ( $v4_runtime as $r ) $v4_hook_names[$r['hook']] = true;
        $v4_shortcode_names = [];
        foreach ( $v4_shortcodes as $r ) $v4_shortcode_names[$r['tag']] = true;

        $critical_unmirrored = [];
        foreach ( $legacy_runtime as $r ) {
            if ( $this->retirement_is_critical_hook( $r['hook'] ) && empty( $v4_hook_names[$r['hook']] ) ) {
                $critical_unmirrored[] = $r;
            }
        }

        $shortcode_unmirrored = [];
        foreach ( $legacy_shortcodes as $r ) {
            if ( empty( $v4_shortcode_names[$r['tag']] ) ) $shortcode_unmirrored[] = $r;
        }

        $symbols = array_unique( array_merge( $bundle['functions'], $bundle['classes'], $bundle['constants'] ) );
        $external = $found ? $this->retirement_external_symbol_refs( $symbols ) : [ 'symbols_scanned'=>[], 'refs'=>[], 'files_scanned'=>0, 'truncated'=>false ];
        $shortcode_usage = $this->retirement_shortcode_post_usage( array_column( $legacy_shortcodes, 'tag' ) );

        $v4_source = @file_get_contents( __FILE__ );
        $biletlerim_alias_in_v4 = false !== strpos( (string) $v4_source, 'function ms_biletlerim_url' )
            && false !== strpos( (string) $v4_source, 'register_legacy_biletlerim_compat' );
        $v4_ticket_download = function_exists( 'tickera_get_ticket_download_link' );

        $ready =
            $found
            && $active
            && $readable
            && $v4_active
            && empty( $critical_unmirrored )
            && empty( $shortcode_unmirrored )
            && empty( $external['refs'] )
            && ! $external['truncated']
            && $biletlerim_alias_in_v4
            && $v4_ticket_download;

        echo '<div class="wrap mdgv4">';
        echo '<h1>3.6.3 Emeklilik Denetimi <span class="mdgv4-small">V4.0.14 — yalnız okuma</span></h1>';
        echo '<div class="mdgv4-note"><strong>Amaç:</strong> Eski ana <code>madagaskar-bilet-yonetimi/madagaskar-bilet-yonetimi.php</code> eklentisini kapatmadan önce gerçek dosyasını, çalışma anındaki hook/shortcode callbacklerini ve diğer aktif kodlardaki doğrudan sembol bağımlılıklarını denetler. Bu ekran eklenti kapatmaz, dosya değiştirmez, sipariş/ticket/PayTR verisine yazmaz.</div>';

        echo '<div class="mdgv4-grid">';
        echo '<div class="mdgv4-card"><div class="mdgv4-kpi">'.($found?'1':'0').'</div><p>Eski çekirdek bulundu</p><p class="mdgv4-small">'.esc_html($legacy_base).'</p></div>';
        echo '<div class="mdgv4-card"><div class="mdgv4-kpi">'.count($legacy_runtime).'</div><p>Runtime callback</p><p class="mdgv4-small">3.6.3 dosya kökünden kayıtlı</p></div>';
        echo '<div class="mdgv4-card"><div class="mdgv4-kpi">'.count($critical_unmirrored).'</div><p>Eşleşmeyen kritik hook</p><p class="mdgv4-small">0 olması tercih edilir</p></div>';
        echo '<div class="mdgv4-card"><div class="mdgv4-kpi">'.count($external['refs']).'</div><p>Dış sembol referansı</p><p class="mdgv4-small">'.esc_html($external['files_scanned']).' PHP dosyası tarandı</p></div>';
        echo '</div>';

        if ( $ready ) {
            echo '<div class="mdgv4-safe"><strong>SONUÇ: KONTROLLÜ KAPATMA DENEMESİNE HAZIR.</strong> Statik/runtime denetimde açık engel bulunmadı. Bu sonuç otomatik olarak “sil” anlamına gelmez; bir sonraki adım yalnız 3.6.3’ü devre dışı bırakıp Biletlerim/PDF/QR/satış/V4 admin canary kontrollerini yapmaktır.</div>';
        } else {
            echo '<div class="mdgv4-danger"><strong>SONUÇ: HENÜZ KAPATMAYIN.</strong> Aşağıdaki kapılardan en az biri geçmedi veya tarama tamamlanamadı. 3.6.3 aktif kalsın.</div>';
        }

        echo '<div class="mdgv4-card"><h2>Temel güvenlik kapıları</h2><table class="widefat striped"><tbody>';
        echo '<tr><td>Eski plugin dosyası bulundu</td><td>'.$this->yesno($found).'</td></tr>';
        echo '<tr><td>Eski 3.6.3 aktif</td><td>'.$this->yesno($active).'</td></tr>';
        echo '<tr><td>Eski kaynak okunabilir</td><td>'.$this->yesno($readable).'</td></tr>';
        echo '<tr><td>V4 aktif</td><td>'.$this->yesno($v4_active).'</td></tr>';
        echo '<tr><td>V4 içinde <code>ms_biletlerim_url()</code> uyumluluk aliası kaynakta mevcut</td><td>'.$this->yesno($biletlerim_alias_in_v4).'</td></tr>';
        echo '<tr><td>Tickera resmi download helper runtime mevcut</td><td>'.$this->yesno($v4_ticket_download).'</td></tr>';
        echo '<tr><td>Eşleşmeyen kritik runtime hook yok</td><td>'.$this->yesno(empty($critical_unmirrored)).'</td></tr>';
        echo '<tr><td>Eşleşmeyen runtime shortcode yok</td><td>'.$this->yesno(empty($shortcode_unmirrored)).'</td></tr>';
        echo '<tr><td>Diğer aktif plugin/tema/MU kodlarında doğrudan eski sembol referansı yok</td><td>'.$this->yesno(empty($external['refs'])).'</td></tr>';
        echo '<tr><td>Dış bağımlılık taraması kesilmeden tamamlandı</td><td>'.$this->yesno(!$external['truncated']).'</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-card"><h2>Eski çekirdek kimliği</h2><table class="widefat striped"><tbody>';
        echo '<tr><td>Plugin adı</td><td>'.esc_html($legacy_data['Name'] ?? '—').'</td></tr>';
        echo '<tr><td>Sürüm</td><td><code>'.esc_html($legacy_data['Version'] ?? '—').'</code></td></tr>';
        echo '<tr><td>Dosya</td><td><code>'.esc_html($legacy_base).'</code></td></tr>';
        echo '<tr><td>PHP dosyası</td><td>'.esc_html($bundle['file_count']).'</td></tr>';
        echo '<tr><td>Kaynak boyutu</td><td>'.esc_html(size_format($bundle['total_bytes'])).'</td></tr>';
        echo '<tr><td>Paket SHA-256</td><td><code>'.esc_html($bundle['bundle_sha256'] ?: '—').'</code></td></tr>';
        echo '<tr><td>Statik global fonksiyon</td><td>'.esc_html(count($bundle['functions'])).'</td></tr>';
        echo '<tr><td>Statik class/interface/trait</td><td>'.esc_html(count($bundle['classes'])).'</td></tr>';
        echo '<tr><td>Statik hook etiketi</td><td>'.esc_html(count($bundle['hooks'])).'</td></tr>';
        echo '<tr><td>Statik shortcode etiketi</td><td>'.esc_html(count($bundle['shortcodes'])).'</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-card"><h2>Runtime hook callbackleri</h2>';
        if ( ! $legacy_runtime ) {
            echo '<div class="mdgv4-safe">3.6.3 kökünden runtime callback bulunmadı.</div>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Hook</th><th>Öncelik</th><th>Callback</th><th>V4 aynı hookta</th><th>Kritik</th></tr></thead><tbody>';
            foreach ( $legacy_runtime as $r ) {
                $mirror = ! empty( $v4_hook_names[$r['hook']] );
                $critical = $this->retirement_is_critical_hook( $r['hook'] );
                echo '<tr><td><code>'.esc_html($r['hook']).'</code></td><td>'.esc_html($r['priority']).'</td><td>'.esc_html($r['callback']).'</td><td>'.$this->yesno($mirror).'</td><td>'.($critical?'<span class="mdgv4-warn">Evet</span>':'Hayır').'</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div class="mdgv4-card"><h2>Runtime shortcode sahipliği</h2>';
        if ( ! $legacy_shortcodes ) {
            echo '<div class="mdgv4-safe">3.6.3 tarafından kayıtlı runtime shortcode bulunmadı.</div>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Shortcode</th><th>Callback</th><th>V4 aynı tag</th><th>İçerikte kullanım</th></tr></thead><tbody>';
            foreach ( $legacy_shortcodes as $r ) {
                $mirror = ! empty( $v4_shortcode_names[$r['tag']] );
                echo '<tr><td><code>['.esc_html($r['tag']).']</code></td><td>'.esc_html($r['callback']).'</td><td>'.$this->yesno($mirror).'</td><td>'.esc_html($shortcode_usage[$r['tag']] ?? 0).'</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div class="mdgv4-card"><h2>Dış sembol bağımlılığı taraması</h2>';
        echo '<p class="mdgv4-small">Aktif eklentiler, MU eklentileri ve aktif/parent tema PHP dosyalarında eski çekirdeğin tanımladığı global fonksiyon/class/constant adları aranır. Bu statik bir aday taramasıdır; veritabanında saklanan/eval edilen snippet kodlarını garanti etmez.</p>';
        if ( ! $external['refs'] ) {
            echo '<div class="mdgv4-safe">Doğrudan eski sembol referansı bulunmadı.</div>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Sembol</th><th>Kaynak</th><th>Dosya</th></tr></thead><tbody>';
            foreach ( $external['refs'] as $r ) {
                echo '<tr><td><code>'.esc_html($r['symbol']).'</code></td><td>'.esc_html($r['source']).'</td><td><code>'.esc_html($r['file']).'</code></td></tr>';
            }
            echo '</tbody></table>';
        }
        if ( $external['truncated'] ) {
            echo '<div class="mdgv4-danger">Tarama güvenlik sınırına ulaştı; sonuç eksik olabilir. Bu durumda 3.6.3 kapatılmamalı.</div>';
        }
        echo '</div>';

        if ( $critical_unmirrored ) {
            echo '<div class="mdgv4-card"><h2>Engel: V4’te aynı hook etiketi bulunmayan kritik callbackler</h2><table class="widefat striped"><thead><tr><th>Hook</th><th>Callback</th><th>Dosya</th></tr></thead><tbody>';
            foreach ( $critical_unmirrored as $r ) {
                echo '<tr><td><code>'.esc_html($r['hook']).'</code></td><td>'.esc_html($r['callback']).'</td><td><code>'.esc_html($r['file']).'</code></td></tr>';
            }
            echo '</tbody></table></div>';
        }

        if ( $shortcode_unmirrored ) {
            echo '<div class="mdgv4-card"><h2>Engel: V4’te aynı tag bulunmayan shortcode</h2><table class="widefat striped"><thead><tr><th>Shortcode</th><th>Callback</th></tr></thead><tbody>';
            foreach ( $shortcode_unmirrored as $r ) {
                echo '<tr><td><code>['.esc_html($r['tag']).']</code></td><td>'.esc_html($r['callback']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '<div class="mdgv4-note"><strong>Sonraki adım:</strong> Bu sayfanın sonucuna göre ilerleyin. “KONTROLLÜ KAPATMA DENEMESİNE HAZIR” çıkmadan eski 3.6.3 eklentisini kapatmayın. Hazır çıkarsa da silmeyin; önce yalnız devre dışı bırakıp V4, Biletlerim, Tickera PDF/QR ve satış canary kontrollerini yapacağız.</div>';
        echo '</div>';
    }

    private function legacy_plugins() {
        include_once ABSPATH.'wp-admin/includes/plugin.php';$all=get_plugins();$self=plugin_basename(__FILE__);$out=[];
        foreach($all as $file=>$data){
            if($file===$self)continue;
            $name=(string)($data['Name']??'');
            $desc=(string)($data['Description']??'');
            $version=(string)($data['Version']??'');
            if(false===stripos($name,'Madagaskar'))continue;
            $category=$this->classify_legacy($file,$name,$version,$desc);
            $out[]=['file'=>$file,'name'=>$name,'version'=>$version,'description'=>$desc,'active'=>is_plugin_active($file),'category'=>$category];
        }
        usort($out,fn($a,$b)=>strcmp($a['category'].'|'.$a['name'],$b['category'].'|'.$b['name']));return $out;
    }

    private function classify_legacy($file,$name,$version='',$desc='') {
        // V4.0.14: üretim kararı açıklama metninden çıkarılmaz. Eski eklenti açıklamalarında
        // başka sürüm adları geçtiği için V4.0.8'de yanlış pozitif sınıflandırma oluşabiliyordu.
        // Kritik üretim modülleri yalnız gerçek plugin basename'i ile kesin olarak eşleştirilir.
        $file = strtolower( str_replace('\\','/',(string)$file) );

        $migrated_off = [
            'madagaskar-bilet-yonetimi-v3-6-4/madagaskar-bilet-yonetimi-v3-6-4.php',
            'madagaskar-bilet-yonetimi-v3-7-1/madagaskar-bilet-yonetimi-v3-7-1.php',
            'madagaskar-bilet-yonetimi-v3-7-2/madagaskar-bilet-yonetimi-v3-7-2.php',
            'madagaskar-bilet-yonetimi-v3-7-3/madagaskar-bilet-yonetimi-v3-7-3.php',
            'madagaskar-bilet-yonetimi-v3-8-1/madagaskar-bilet-yonetimi-v3-8-1.php',
            'madagaskar-bilet-yonetimi-v3-8-5/madagaskar-bilet-yonetimi-v3-8-5.php',
            'madagaskar-bilet-yonetimi-v3-8-5-1/madagaskar-bilet-yonetimi-v3-8-5-1.php',
            'madagaskar-bilet-yonetimi-v3-8-7/madagaskar-bilet-yonetimi-v3-8-7.php',
        ];
        if(in_array($file,$migrated_off,true))return 'migrated_off';

        $validation_pending = [
        ];
        if(in_array($file,$validation_pending,true))return 'validation_pending';

        if($file==='madagaskar-menu-duzenleyici/madagaskar-menu-duzenleyici.php')return 'helper';
        if($file==='madagaskar-bilet-yonetimi/madagaskar-bilet-yonetimi.php')return 'keep_until_validated';

        // Geri kalan eski deney/denetim/canary araçları isim veya açıklama üzerinden tanınabilir;
        // bunlar üretim kapatma kararı vermediği için yanlış pozitif burada güvenlik riski oluşturmaz.
        $s=$name.' '.$desc.' '.$version;
        if(preg_match('/Dry-Run|Denetim|Trace|Runtime|Canary|Snapshot|Preflight|Önizleme|Gate|Kök Neden|Callback|İzleme|Geliştirme Modu|Test Aktarımı|Test Ticket|Test Bilet/iu',$s))return 'diagnostic';

        return 'keep_until_validated';
    }

    private function migration_decision_label($x){
        $c=(string)($x['category']??'');
        $active=!empty($x['active']);
        if($c==='migrated_off')return $active?'V4’E TAŞINDI — KAPATILMALI':'V4’E TAŞINDI — KAPALI KALACAK';
        if($c==='validation_pending')return $active?'DOĞRULAMA BEKLİYOR — AÇIK KALACAK':'DOĞRULAMA BEKLİYOR — YANLIŞLIKLA KAPALI';
        if($c==='helper')return $active?'YARDIMCI — AÇIK KALABİLİR':'YARDIMCI — KAPALI';
        if($c==='diagnostic')return $active?'TANI/TEST — KAPATILABİLİR':'TANI/TEST — KAPALI KALACAK';
        if($c==='keep_until_validated')return $active?'ŞİMDİLİK KALACAK':'ŞİMDİLİK KAPALI';
        return $c;
    }

    private function migration_decision_color($x){
        $c=(string)($x['category']??'');
        $active=!empty($x['active']);
        if($c==='migrated_off')return $active?'#b32d2e':'#16803a';
        if($c==='validation_pending')return $active?'#996800':'#b32d2e';
        if($c==='helper')return '#2271b1';
        if($c==='diagnostic')return $active?'#996800':'#16803a';
        return '#16803a';
    }

    public function render_migration() {
        $this->require_cap();$this->styles();
        $list=$this->legacy_plugins();
        $diag=array_filter($list,fn($x)=>$x['category']==='diagnostic'&&$x['active']);
        $migrated=array_filter($list,fn($x)=>$x['category']==='migrated_off');
        $migrated_wrong=array_filter($migrated,fn($x)=>$x['active']);
        $pending=array_filter($list,fn($x)=>$x['category']==='validation_pending');
        $pending_wrong=array_filter($pending,fn($x)=>!$x['active']);
        $helpers=array_filter($list,fn($x)=>$x['category']==='helper');

        $expected_counts = ['migrated'=>8,'pending'=>0,'diagnostic'=>0,'helper'=>1];
        $count_mismatch =
            count($migrated) !== $expected_counts['migrated']
            || count($pending) !== $expected_counts['pending']
            || count($diag) !== $expected_counts['diagnostic']
            || count($helpers) !== $expected_counts['helper'];

        echo '<div class="wrap mdgv4"><h1>Geçiş Merkezi <span class="mdgv4-small">V4.0.14</span></h1>';
        echo '<div class="mdgv4-safe"><strong>Durum merkezi:</strong> Bu ekran artık yalnız plan değil, doğrulanmış geçiş durumunu gösterir. Hiçbir üretim eklentisini otomatik kapatmaz veya silmez.</div>';
        echo '<div class="mdgv4-note"><strong>Son üretim doğrulaması:</strong> Gerçek tam iade sonrası WooCommerce siparişinin refunded, kalan iadenin 0, Refund ID’nin oluşmuş ve Tickera ticketının geçersiz + trash duruma uzlaştırılmış olması V4 geçişini tamamlayan kapıdır.</div>';
        if($count_mismatch){
            echo '<div class="mdgv4-danger"><strong>Geçiş sayacı beklenen durumla eşleşmiyor.</strong> Beklenen: 8 V4’e taşındı / 0 doğrulama bekliyor / 0 aktif tanı-test / 1 yardımcı. Otomatik işlem yapılmadı.</div>';
        }
        echo '<div class="mdgv4-note"><strong>V4.0.14 final sınıflandırma:</strong> Kritik üretim eklentileri yalnız gerçek plugin dosya yolu/basename üzerinden eşleştirilir. Doğrulanmış final durum: 8 taşındı, 0 doğrulama bekliyor, 0 aktif tanı/test, 1 yardımcı.</div>';

        echo '<div class="mdgv4-grid">';
        echo '<div class="mdgv4-card"><h2>'.count($migrated).'</h2><p>V4’e taşındı</p><p class="mdgv4-small">Kapalı kalması gereken eski üretim modülleri</p></div>';
        echo '<div class="mdgv4-card"><h2>'.count($pending).'</h2><p>Doğrulama bekliyor</p><p class="mdgv4-small">Şimdilik açık kalacak üretim modülleri</p></div>';
        echo '<div class="mdgv4-card"><h2>'.count($diag).'</h2><p>Aktif tanı/test</p><p class="mdgv4-small">Normalde 0 olmalı</p></div>';
        echo '<div class="mdgv4-card"><h2>'.count($helpers).'</h2><p>Yardımcı eklenti</p><p class="mdgv4-small">İş mantığı geçişinden bağımsız</p></div>';
        echo '</div>';

        if($migrated_wrong){
            echo '<div class="mdgv4-danger"><strong>Dikkat:</strong> V4’e taşındığı halde yeniden aktif görünen eski modül var. Otomatik işlem yapılmadı.</div>';
        }
        if($pending_wrong){
            echo '<div class="mdgv4-danger"><strong>Dikkat:</strong> Doğrulama bekleyen bir üretim modülü kapalı görünüyor. Gerçek doğrulama tamamlanmadan bu modül kapalı bırakılmamalı.</div>';
        }
        echo '<div class="mdgv4-card"><h2>Madagaskar eklenti envanteri</h2><table class="widefat striped"><thead><tr><th>Eklenti</th><th>Sürüm</th><th>Aktif</th><th>Geçiş kararı</th></tr></thead><tbody>';
        foreach($list as $x){
            $color=$this->migration_decision_color($x);
            echo '<tr><td><strong>'.esc_html($x['name']).'</strong><br><span class="mdgv4-small">'.esc_html($x['file']).'</span></td><td>'.esc_html($x['version']).'</td><td>'.$this->yesno($x['active']).'</td><td><strong style="color:'.$color.'">'.esc_html($this->migration_decision_label($x)).'</strong></td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-card"><h2>Geçiş özeti</h2><table class="widefat striped"><tbody>';
        echo '<tr><td>V3.7.1 — Etkinlik Satışlarını Kapat</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.8.1 — Erteleme Seans Eşleme Hazırlığı</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.8.5 — Güvenli Hedef Taslak Oluşturma</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.8.5.1 — Varyasyon Onarım Güvenliği</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.8.7 — Doğrulanmış Seans Eşlemesini Sabitleme</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.6.4 — Tek Sipariş Tam İade</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.7.2 — Güvenli Kullanıcı Rolü</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>V3.7.3 — İki Aşamalı İptal Onayı</td><td><strong style="color:#16803a">V4’E TAŞINDI — KAPALI KALACAK</strong></td></tr>';
        echo '<tr><td>Madagaskar Bilet Yönetimi 3.6.3</td><td><strong style="color:#16803a">ŞİMDİLİK KALACAK</strong></td></tr>';
        echo '<tr><td>Madagaskar Yönetim Menü Düzenleyici</td><td><strong style="color:#2271b1">YARDIMCI — AÇIK KALABİLİR</strong></td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="mdgv4-card"><h2>Tanı / test temizliği</h2><p>Aktif tanı/test adayı: <strong>'.count($diag).'</strong>. Bu işlem yalnız tanı/test eklentilerini <strong>silmeden devre dışı bırakır</strong>; üretim çekirdeğine dokunmaz.</p>';
        if($diag){
            $phrase='V4 TEST ARACLARINI KAPAT';
            echo '<p>Onay için aynen yazın: <code>'.esc_html($phrase).'</code></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('mdg_v4_deactivate_diagnostics');
            echo '<input type="hidden" name="action" value="mdg_v4_deactivate_diagnostics"><input type="text" name="confirm" style="width:360px"> ';
            submit_button('TANI/TEST EKLENTİLERİNİ DEVRE DIŞI BIRAK','secondary','',false);
            echo '</form>';
        }else{
            echo '<div class="mdgv4-safe">Aktif tanı/test eklentisi kalmamış.</div>';
        }
        echo '</div>';

        echo '<div class="mdgv4-safe"><strong>V4 geçiş doğrulaması tamamlandı:</strong> satış kapatma, erteleme, Biletlerim, iki kullanıcı onayı, PayTR tam iade ve iade sonrası Tickera ticket uzlaştırması üretimde doğrulandı. V3.6.4 dahil taşınan eski üretim modülleri kapalı kalabilir. Eski ana Madagaskar Bilet Yönetimi 3.6.3 için <strong>3.6.3 Emeklilik</strong> sayfasındaki yalnız-okuma denetimi tamamlanmadan kapatma yapılmaz.</div>';
        echo '</div>';
    }

    public function handle_deactivate_diagnostics() {
        $this->require_cap();check_admin_referer('mdg_v4_deactivate_diagnostics');$confirm=trim(sanitize_text_field(wp_unslash($_POST['confirm']??'')));if(!hash_equals('V4 TEST ARACLARINI KAPAT',$confirm))wp_die('Onay cümlesi eşleşmedi.');include_once ABSPATH.'wp-admin/includes/plugin.php';$list=$this->legacy_plugins();$files=[];foreach($list as $x)if($x['category']==='diagnostic'&&$x['active'])$files[]=$x['file'];if($files)deactivate_plugins($files,true);wp_safe_redirect(add_query_arg(['page'=>'mdg-v4-migration','deactivated'=>count($files)],admin_url('admin.php')));exit;
    }
}

MDG_Bilet_Yonetimi_V4::boot();
