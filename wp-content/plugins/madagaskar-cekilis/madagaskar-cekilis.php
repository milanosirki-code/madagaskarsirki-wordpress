<?php
/**
 * Plugin Name: Madagaskar Çekiliş Sistemi
 * Description: Instagram profesyonel hesap gönderilerindeki yorumları Meta Instagram API ile alır; çekiliş kampanyalarını, geçerli katılımları, asil/yedek seçimlerini ve denetim kayıtlarını kendi WordPress sitenizde yönetir.
 * Version: 3.4.0
 * Author: Dünya Organizasyon
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/includes/class-mck-reporting.php';

final class Madagaskar_Cekilis_V2 {
    const VERSION = '3.4.0';
    const API_VERSION = 'v26.0';
    const OPTION_TOKEN = 'mck2_ig_access_token';
    const OPTION_USERNAME = 'mck2_ig_username';
    const OPTION_CONNECTED_ID = 'mck2_ig_connected_id';
    const OPTION_RESULTS_PAGE_ID = 'mck2_results_page_id';
    const LOGO_URL = 'https://madagaskarsirki.com/wp-content/uploads/2026/08/ChatGPT-Image-17-Agu-2026-09_29_49.png';
    const DB_VERSION = '2.0.0';

    private $campaigns_table;
    private $comments_table;
    private $draws_table;

    public function __construct() {
        global $wpdb;
        $this->campaigns_table = $wpdb->prefix . 'mck_campaigns';
        $this->comments_table  = $wpdb->prefix . 'mck_comments';
        $this->draws_table     = $wpdb->prefix . 'mck_draws';

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('wp_sitemaps_init', [$this, 'register_wp_sitemap_provider']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
        add_action('admin_init', [$this, 'maybe_create_public_results_page']);
        add_action('admin_init', [$this, 'maybe_prepare_footer_results_link']);
        add_filter('wp_nav_menu_objects', [$this, 'hide_results_from_header_menu'], 20, 2);
        add_shortcode('madagaskar_cekilis_sonuclari', [$this, 'shortcode_results']);
        add_filter('pre_get_document_title', [$this, 'seo_document_title']);
        add_filter('wp_robots', [$this, 'seo_robots']);
        add_action('wp_head', [$this, 'seo_head'], 1);
        add_action('admin_post_mck2_save_settings', [$this, 'save_settings']);
        add_action('admin_post_mck2_test_connection', [$this, 'test_connection']);
        add_action('admin_post_mck2_create_campaign', [$this, 'create_campaign']);
        add_action('admin_post_mck2_sync_campaign', [$this, 'sync_campaign_action']);
        add_action('admin_post_mck2_draw_campaign', [$this, 'draw_campaign']);
        add_action('admin_post_mck2_export_campaign', [$this, 'export_campaign']);
        add_action('admin_post_mck_report_export', ['MCK_Reporting', 'export']);
        add_action('admin_post_mck2_verify_result', [$this, 'verify_result']);
        add_action('admin_post_mck2_publish_results', [$this, 'publish_results']);
        add_action('admin_post_mck2_unpublish_results', [$this, 'unpublish_results']);
        add_action('template_redirect', [$this, 'nocache_public_results'], -1000);
        add_action('admin_post_mck2_delete_campaign', [$this, 'delete_campaign']);
        add_action('admin_post_mck2_diagnose_campaign', [$this, 'diagnose_campaign']);
        add_action('admin_post_mck2_story_result', [$this, 'story_result']);
        add_action('template_redirect', [$this, 'maybe_render_public_result'], 0);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $campaigns = $wpdb->prefix . 'mck_campaigns';
        $comments  = $wpdb->prefix . 'mck_comments';
        $draws     = $wpdb->prefix . 'mck_draws';

        dbDelta("CREATE TABLE $campaigns (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(190) NOT NULL,
            post_url text NOT NULL,
            media_id varchar(100) NOT NULL DEFAULT '',
            min_mentions int unsigned NOT NULL DEFAULT 1,
            winner_count int unsigned NOT NULL DEFAULT 2,
            reserve_count int unsigned NOT NULL DEFAULT 2,
            one_user_one_entry tinyint(1) NOT NULL DEFAULT 0,
            one_user_one_win tinyint(1) NOT NULL DEFAULT 1,
            dedupe_same_text tinyint(1) NOT NULL DEFAULT 1,
            status varchar(30) NOT NULL DEFAULT 'draft',
            total_comments int unsigned NOT NULL DEFAULT 0,
            valid_entries int unsigned NOT NULL DEFAULT 0,
            invalid_comments int unsigned NOT NULL DEFAULT 0,
            fetched_at datetime NULL,
            drawn_at datetime NULL,
            audit_seed varchar(128) NOT NULL DEFAULT '',
            eligible_hash varchar(128) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset;");

        dbDelta("CREATE TABLE $comments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            comment_id varchar(100) NOT NULL,
            username varchar(190) NOT NULL DEFAULT '',
            comment_text longtext NOT NULL,
            comment_timestamp varchar(80) NOT NULL DEFAULT '',
            mentions longtext NULL,
            mention_count int unsigned NOT NULL DEFAULT 0,
            is_valid tinyint(1) NOT NULL DEFAULT 0,
            invalid_reason varchar(255) NOT NULL DEFAULT '',
            normalized_text varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_comment (campaign_id, comment_id),
            KEY campaign_valid (campaign_id, is_valid),
            KEY campaign_user (campaign_id, username)
        ) $charset;");

        dbDelta("CREATE TABLE $draws (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) unsigned NOT NULL,
            result_type varchar(20) NOT NULL,
            position_no int unsigned NOT NULL,
            comment_id varchar(100) NOT NULL,
            username varchar(190) NOT NULL,
            comment_text longtext NOT NULL,
            verification_status varchar(20) NOT NULL DEFAULT 'pending',
            verification_note text NULL,
            score_hash varchar(128) NOT NULL DEFAULT '',
            drawn_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY campaign_slot (campaign_id, result_type, position_no),
            KEY campaign_results (campaign_id)
        ) $charset;");

        update_option('mck2_db_version', self::DB_VERSION, false);
    }

    public function admin_menu() {
        add_menu_page(
            'Madagaskar Çekiliş',
            'Sirk Çekiliş',
            'manage_options',
            'madagaskar-cekilis',
            [$this, 'render_admin'],
            'dashicons-tickets-alt',
            58
        );
    }

    private function require_admin() {
        if (!current_user_can('manage_options')) {
            wp_die('Bu işlem için yetkiniz yok.');
        }
    }

    private function now() { return current_time('mysql'); }

    public function register_rewrite_rules() {
        add_rewrite_rule('^cekilis-dogrula/([A-Za-z0-9]{6})/?$', 'index.php?mck_verify=$matches[1]', 'top');
    }

    public function register_query_vars($vars) {
        if (!in_array('mck_verify', $vars, true)) $vars[] = 'mck_verify';
        return $vars;
    }

    public function maybe_flush_rewrite_rules() {
        $saved = (string)get_option('mck2_rewrite_version', '');
        if ($saved === self::VERSION) return;
        $this->register_rewrite_rules();
        flush_rewrite_rules(false);
        update_option('mck2_rewrite_version', self::VERSION, false);
    }

    private function verification_url($code) {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$code));
        return home_url('/cekilis-dogrula/' . rawurlencode($clean) . '/');
    }

    private function instagram_profile_url($username) {
        $user = preg_replace('/[^A-Za-z0-9._]/', '', ltrim((string)$username, '@'));
        if ($user === '') return 'https://www.instagram.com/';
        return 'https://www.instagram.com/' . rawurlencode($user) . '/';
    }

    private function public_results_url() {
        $page_id = intval(get_option(self::OPTION_RESULTS_PAGE_ID, 0));
        if ($page_id && get_post_status($page_id) === 'publish') {
            return get_permalink($page_id);
        }
        $page = get_page_by_path('cekilis-sonuclari');
        if ($page && $page->post_status === 'publish') {
            update_option(self::OPTION_RESULTS_PAGE_ID, intval($page->ID), false);
            return get_permalink($page->ID);
        }
        return home_url('/cekilis-sonuclari/');
    }

    public function maybe_create_public_results_page() {
        if (!current_user_can('manage_options')) return;
        $page_id = intval(get_option(self::OPTION_RESULTS_PAGE_ID, 0));
        if ($page_id && get_post_status($page_id)) return;
        $page = get_page_by_path('cekilis-sonuclari');
        if ($page) {
            update_option(self::OPTION_RESULTS_PAGE_ID, intval($page->ID), false);
            return;
        }
        $new_id = wp_insert_post([
            'post_title'   => 'Çekiliş Sonuçları',
            'post_name'    => 'cekilis-sonuclari',
            'post_content' => '[madagaskar_cekilis_sonuclari]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ], true);
        if (!is_wp_error($new_id) && $new_id) {
            update_option(self::OPTION_RESULTS_PAGE_ID, intval($new_id), false);
        }
    }

    public function maybe_add_results_page_to_menu() {
        // V3.2: Üst menüye otomatik ekleme devre dışı. Geriye dönük uyumluluk için metot korunur.
        return;
    }

    public function maybe_prepare_footer_results_link() {
        if (!current_user_can('manage_options')) return;
        // V3.3: bağlantı site Footer şablonuna manuel olarak eklendi.
        // Üst menüde eski bir kayıt kaldıysa ön yüzde gizlemeye devam ediyoruz.
        update_option('mck2_results_link_location', 'footer', false);
    }

    public function hide_results_from_header_menu($items, $args) {
        if (is_admin()) return $items;
        $page_id = intval(get_option(self::OPTION_RESULTS_PAGE_ID, 0));
        if (!$page_id) return $items;
        $loc = isset($args->theme_location) ? strtolower((string)$args->theme_location) : '';
        $menu_name = isset($args->menu) && is_object($args->menu) && isset($args->menu->name) ? strtolower((string)$args->menu->name) : '';
        $is_header = in_array($loc, ['primary','menu-1','header','main-menu','main','top','navigation'], true)
            || strpos($loc, 'header') !== false || strpos($loc, 'primary') !== false || strpos($loc, 'main') !== false || strpos($loc, 'top') !== false
            || strpos($menu_name, 'ana') !== false || strpos($menu_name, 'header') !== false || strpos($menu_name, 'primary') !== false || strpos($menu_name, 'main') !== false;
        if (!$is_header) return $items;
        return array_values(array_filter((array)$items, function($item) use ($page_id) {
            return !(isset($item->object, $item->object_id) && $item->object === 'page' && intval($item->object_id) === $page_id);
        }));
    }

    public function render_footer_results_link() {
        // V3.3: Footer bağlantısı tema/footer şablonuna manuel eklendi.
        // Eklenti artık ayrı bir footer şeridi üretmez.
        return;
    }

    private function is_results_page() {
        $page_id = intval(get_option(self::OPTION_RESULTS_PAGE_ID, 0));
        return $page_id ? is_page($page_id) : is_page('cekilis-sonuclari');
    }

    private function current_verify_campaign() {
        $code = (string)get_query_var('mck_verify', '');
        if ($code === '' && !empty($_GET['mck_verify'])) $code = sanitize_text_field(wp_unslash($_GET['mck_verify']));
        $campaign = $code ? $this->find_campaign_by_code($code) : null;
        return $campaign && $this->public_results_published($campaign) ? $campaign : null;
    }

    public function seo_document_title($title) {
        if ($this->is_results_page()) return 'Madagaskar Sirki Çekiliş Sonuçları | Resmî Sonuçlar';
        $c = $this->current_verify_campaign();
        if ($c) return mb_strtoupper(trim((string)$c->title)) . ' Çekiliş Sonucu | Madagaskar Sirki';
        return $title;
    }

    public function seo_robots($robots) {
        if ($this->is_results_page() || $this->current_verify_campaign()) {
            $robots['index'] = true; $robots['follow'] = true; $robots['max-image-preview'] = 'large';
            unset($robots['noindex'], $robots['nofollow']);
        }
        return $robots;
    }

    public function seo_head() {
        if (!$this->is_results_page() && !$this->current_verify_campaign()) return;
        $is_archive = $this->is_results_page();
        $c = $is_archive ? null : $this->current_verify_campaign();
        if ($is_archive) {
            $title = 'Madagaskar Sirki Çekiliş Sonuçları';
            $desc = 'Madagaskar Sirki resmî çekiliş sonuçları, kazanan kullanıcılar, geçerli katılım sayıları ve doğrulama kayıtları.';
            $url = $this->public_results_url();
        } else {
            $event = mb_strtoupper(trim((string)$c->title));
            $title = $event . ' Çekiliş Sonucu | Madagaskar Sirki';
            $desc = $event . ' Madagaskar Sirki çekiliş sonucu. Asil kazananlar, geçerli katılım sayısı, çekiliş zamanı ve resmî doğrulama kaydı.';
            $url = $this->verification_url($this->verification_code($c));
        }
        echo "\n<meta name=\"description\" content=\"" . esc_attr($desc) . "\">\n";
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url(self::LOGO_URL) . '">' . "\n";
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $is_archive ? 'CollectionPage' : 'WebPage',
            'name' => $title,
            'description' => $desc,
            'url' => $url,
            'isPartOf' => ['@type'=>'WebSite','name'=>'Madagaskar Sirki','url'=>home_url('/')],
        ];
        if (!$is_archive && $c) {
            $schema['datePublished'] = mysql2date('c', $c->drawn_at);
            $schema['dateModified'] = mysql2date('c', $c->updated_at ?: $c->drawn_at);
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    /**
     * Çekiliş doğrulama URL'lerini WordPress'in yerleşik wp-sitemap.xml
     * altyapısına ekler. /cekilis-sonuclari/ normal bir WordPress sayfası
     * olduğu için zaten wp-sitemap-posts-page-*.xml içinde yer alır.
     * Burada yalnızca sanal /cekilis-dogrula/KOD/ URL'lerini sağlayıcıya
     * ekliyoruz; böylece ayrı bir cekilis-sitemap.xml dosyasına gerek kalmaz.
     */
    public function register_wp_sitemap_provider() {
        if (!function_exists('wp_register_sitemap_provider') || !class_exists('WP_Sitemaps_Provider')) return;
        if (function_exists('wp_sitemaps_enabled') && !wp_sitemaps_enabled()) return;

        $plugin = $this;
        $provider = new class($plugin) extends WP_Sitemaps_Provider {
            private $plugin;

            public function __construct($plugin) {
                $this->plugin = $plugin;
                $this->name = 'cekilis';
                $this->object_type = 'cekilis';
            }

            public function get_url_list($page_num, $object_subtype = '') {
                return $this->plugin->wp_sitemap_verification_urls((int)$page_num);
            }

            public function get_max_num_pages($object_subtype = '') {
                return $this->plugin->wp_sitemap_verification_max_pages();
            }
        };

        wp_register_sitemap_provider('cekilis', $provider);
    }

    public function wp_sitemap_verification_urls($page_num = 1) {
        global $wpdb;
        $page_num = max(1, (int)$page_num);
        $max_urls = function_exists('wp_sitemaps_get_max_urls') ? (int)wp_sitemaps_get_max_urls('cekilis') : 2000;
        if ($max_urls < 1) $max_urls = 2000;
        $offset = ($page_num - 1) * $max_urls;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table} WHERE status='drawn' ORDER BY drawn_at DESC, id DESC LIMIT %d OFFSET %d",
            $max_urls,
            $offset
        ));

        $urls = [];
        foreach ((array)$rows as $c) {
            if (!$this->public_results_published($c)) continue;
            $entry = [
                'loc' => $this->verification_url($this->verification_code($c)),
            ];
            $modified = !empty($c->updated_at) ? $c->updated_at : $c->drawn_at;
            if ($modified) $entry['lastmod'] = mysql2date('c', $modified, false);
            $urls[] = $entry;
        }
        return $urls;
    }

    public function wp_sitemap_verification_max_pages() {
        global $wpdb;
        $campaigns = (array)$wpdb->get_results("SELECT * FROM {$this->campaigns_table} WHERE status='drawn'");
        $count = count(array_filter($campaigns, [$this, 'public_results_published']));
        $max_urls = function_exists('wp_sitemaps_get_max_urls') ? (int)wp_sitemaps_get_max_urls('cekilis') : 2000;
        if ($max_urls < 1) $max_urls = 2000;
        return $count > 0 ? (int)ceil($count / $max_urls) : 0;
    }

    private function maybe_redirect_legacy_sitemap() {
        if (is_admin()) return false;
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string)wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string)wp_parse_url($request_uri, PHP_URL_PATH);
        if (trim($path, '/') !== 'cekilis-sitemap.xml') return false;
        wp_safe_redirect(home_url('/wp-sitemap.xml'), 301);
        exit;
    }

    private function brand_logo_data_uri() {
        $cached = get_transient('mck2_brand_logo_data_uri');
        if (is_string($cached) && $cached !== '') return $cached;
        $resp = wp_remote_get(self::LOGO_URL, ['timeout'=>12, 'redirection'=>3]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return '';
        $body = wp_remote_retrieve_body($resp);
        if ($body === '') return '';
        $type = wp_remote_retrieve_header($resp, 'content-type');
        if (!$type || strpos($type, 'image/') !== 0) $type = 'image/png';
        $uri = 'data:' . $type . ';base64,' . base64_encode($body);
        if (strlen($uri) < 3000000) set_transient('mck2_brand_logo_data_uri', $uri, DAY_IN_SECONDS);
        return $uri;
    }

    public function shortcode_results($atts = []) {
        global $wpdb;
        $campaigns = array_values(array_filter((array)$wpdb->get_results("SELECT * FROM {$this->campaigns_table} WHERE status='drawn' ORDER BY drawn_at DESC, id DESC LIMIT 50"), [$this, 'public_results_published']));
        ob_start();
        ?>
        <style>
          .mck-results-shell{display:block;width:calc(100% - 40px);max-width:1180px;margin:38px auto 64px!important;background:#fbf7f2;padding:46px 34px 56px;box-sizing:border-box;font-family:Arial,Helvetica,sans-serif;color:#111827;border-radius:28px;box-shadow:0 18px 45px rgba(17,24,39,.10);overflow:hidden;clear:both}
          .mck-results-shell *{box-sizing:border-box}.mck-results-inner{width:100%;max-width:1040px;margin:0 auto!important;padding:0!important}.mck-results-head{text-align:center;margin:0 0 28px!important}
          .mck-results-logo{width:145px;height:145px;object-fit:contain;display:block;margin:0 auto 6px!important}
          .mck-results-kicker{font-weight:900;color:#7f1d1d;letter-spacing:.7px}.mck-results-title{margin:4px 0 7px!important;font-size:40px!important;line-height:1.08!important;color:#111827}.mck-results-sub{margin:0!important;color:#6b7280;font-size:16px;line-height:1.45}
          .mck-results-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;width:100%}.mck-result-card{min-width:0;background:#fff;border:1px solid #eadfd7;border-radius:20px;padding:24px;box-shadow:0 10px 28px rgba(17,24,39,.06);overflow-wrap:anywhere}
          .mck-result-label{font-size:13px;font-weight:900;color:#b91c1c;letter-spacing:.6px}.mck-result-city{margin:8px 0 7px!important;font-size:27px!important;line-height:1.15!important;color:#111827}.mck-result-meta{margin:0 0 15px!important;color:#6b7280;display:flex;flex-wrap:wrap;gap:4px 7px;align-items:center;font-size:14px;line-height:1.4}.mck-result-meta .mck-meta-date{white-space:nowrap}
          .mck-result-winner{font-size:18px;font-weight:900;margin:8px 0}.mck-result-winner a{color:#111827;text-decoration:none}.mck-result-winner a:hover{text-decoration:underline;color:#b91c1c}
          .mck-ticket{margin-top:16px;padding:15px 16px;border-radius:14px;background:#111827;color:#fff;font-size:14px;line-height:1.55}.mck-ticket p{margin:0 0 8px!important;color:#fff}.mck-ticket p:last-child{margin:0!important;color:#fbbf24;font-weight:900}.mck-code{margin:14px 0 9px;color:#6b7280;font-size:13px}.mck-code strong{letter-spacing:2px;color:#111827}
          .mck-verify-btn{display:inline-block;background:#b91c1c;color:#fff!important;text-decoration:none!important;font-weight:900;padding:12px 16px;border-radius:11px}
          @media(max-width:900px){.mck-results-shell{width:calc(100% - 24px);margin:24px auto 44px!important;padding:34px 20px 42px}.mck-results-grid{grid-template-columns:1fr}}
          @media(max-width:640px){.mck-results-shell{width:calc(100% - 12px);margin:10px auto 24px!important;padding:20px 10px 26px!important;border-radius:18px}.mck-results-head{margin-bottom:18px!important}.mck-results-logo{width:98px!important;height:98px!important;margin-bottom:4px!important}.mck-results-kicker{font-size:15px!important;line-height:1.2!important}.mck-results-title{font-size:30px!important;line-height:1.12!important;margin:3px 0 5px!important}.mck-results-sub{font-size:14px!important;line-height:1.4!important;max-width:300px;margin:0 auto!important}.mck-result-card{padding:16px!important;border-radius:18px}.mck-result-label{font-size:12px}.mck-result-city{font-size:23px!important;margin:6px 0!important}.mck-result-meta{font-size:13px!important;margin-bottom:12px!important}.mck-result-winner{font-size:17px!important;margin:7px 0!important}.mck-ticket{font-size:13px!important;line-height:1.5!important;padding:14px!important;border-radius:16px}.mck-code{font-size:12px!important;margin:12px 0 8px!important}.mck-verify-btn{display:block!important;width:100%!important;text-align:center!important;font-size:15px!important;padding:13px 14px!important;border-radius:12px}}
        </style>
        <section class="mck-results-shell"><div class="mck-results-inner">
          <div class="mck-results-head">
            <img src="<?php echo esc_url(self::LOGO_URL); ?>" alt="Madagaskar Sirki" class="mck-results-logo">
            <div class="mck-results-kicker">MADAGASKAR SİRKİ</div><h1 class="mck-results-title">Çekiliş Sonuçları</h1><p class="mck-results-sub">Resmî çekiliş sonuçları ve doğrulama kayıtları</p><p class="mck-results-sub" style="max-width:720px;margin:8px auto 0">Madagaskar Sirki tarafından düzenlenen Instagram çekilişlerinin asil kazananlarını, geçerli katılım sayılarını ve doğrulama kodlarını bu sayfadan kontrol edebilirsiniz.</p>
          </div>
        <?php if (!$campaigns): ?>
          <div class="mck-result-card" style="text-align:center">Henüz yayımlanmış çekiliş sonucu bulunmuyor.</div>
        <?php else: ?><div class="mck-results-grid">
          <?php foreach ($campaigns as $c):
              $wins = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE campaign_id=%d AND result_type='winner' ORDER BY position_no ASC", $c->id));
              if (!$this->public_results_published($c, $wins)) continue;
              $code = $this->verification_code($c); $verify_url = $this->verification_url($code); $display_title = mb_strtoupper(trim((string)$c->title));
          ?>
            <article class="mck-result-card"><div class="mck-result-label">ÇEKİLİŞ SONUCU</div><h2 class="mck-result-city"><?php echo esc_html($display_title); ?></h2>
              <p class="mck-result-meta"><span><strong><?php echo intval($c->valid_entries); ?></strong> geçerli katılım</span><span>·</span><span class="mck-meta-date"><?php echo esc_html(mysql2date('d.m.Y', $c->drawn_at)); ?> · <?php echo esc_html(mysql2date('H:i', $c->drawn_at)); ?></span></p>
              <div style="border-top:1px solid #f0e8e2;padding-top:12px">
                <?php foreach ($wins as $w): $profile = $this->instagram_profile_url($w->username); ?>
                  <div class="mck-result-winner"><?php echo intval($w->position_no); ?>. <a href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener nofollow">@<?php echo esc_html($w->username); ?></a></div>
                <?php endforeach; ?>
              </div>
              <div class="mck-ticket"><p><strong>🎟 Ücretsiz giriş hakkı:</strong> Her kazanan 1 veli + 1 çocuk için çift kişilik ücretsiz giriş hakkı kazanmıştır.</p><p>Salon gişesinde Instagram kullanıcı adınızı göstererek ücretsiz biletinizi temin edebilirsiniz.</p><p>İstediğiniz seansta kullanabilirsiniz.</p></div>
              <p class="mck-code">Doğrulama kodu: <strong><?php echo esc_html($code); ?></strong></p><a href="<?php echo esc_url($verify_url); ?>" class="mck-verify-btn">Sonucu doğrula</a>
            </article>
          <?php endforeach; ?></div>
        <?php endif; ?>
        </div></section>
        <?php return ob_get_clean();
    }

    private function ig_api_base() { return 'https://graph.instagram.com/' . self::API_VERSION; }

    private function redirect($args = []) {
        $base = ['page' => 'madagaskar-cekilis'];
        wp_safe_redirect(add_query_arg(array_merge($base, $args), admin_url('admin.php')));
        exit;
    }

    private function flash_error($message, $campaign_id = 0) {
        set_transient('mck2_err_' . get_current_user_id(), (string)$message, 120);
        $args = ['mck_notice'=>'error'];
        if ($campaign_id) { $args['view']='campaign'; $args['campaign_id']=$campaign_id; }
        $this->redirect($args);
    }

    private function token_key() {
        return hash('sha256', wp_salt('auth') . '|madagaskar-cekilis-v2', true);
    }

    private function encrypt_secret($plain) {
        if ($plain === '') return '';
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->token_key(), OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher !== false) return 'enc1:' . base64_encode($iv . $tag . $cipher);
        }
        return 'plain1:' . base64_encode($plain);
    }

    private function decrypt_secret($stored) {
        if (!$stored) return '';
        if (strpos($stored, 'enc1:') === 0 && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($stored, 5), true);
            if ($raw === false || strlen($raw) < 29) return '';
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->token_key(), OPENSSL_RAW_DATA, $iv, $tag);
            return $plain === false ? '' : $plain;
        }
        if (strpos($stored, 'plain1:') === 0) {
            $plain = base64_decode(substr($stored, 7), true);
            return $plain === false ? '' : $plain;
        }
        return $stored; // backward compatibility
    }

    private function get_token() {
        return $this->decrypt_secret((string)get_option(self::OPTION_TOKEN, ''));
    }

    private function api_get($url, $token = null) {
        $token = $token === null ? $this->get_token() : $token;
        if ($token === '') return new WP_Error('missing_token', 'Instagram erişim anahtarı kayıtlı değil.');
        $response = wp_remote_get($url, [
            'timeout' => 35,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
        if (is_wp_error($response)) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $body_text = wp_remote_retrieve_body($response);
        $body = json_decode($body_text, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($body) && isset($body['error']['message']) ? $body['error']['message'] : ('Instagram API HTTP ' . $code);
            return new WP_Error('instagram_api_error', $msg, ['status'=>$code,'body'=>$body]);
        }
        return is_array($body) ? $body : [];
    }

    private function diagnostic_http_get($url) {
        $token = $this->get_token();
        if ($token === '') return ['ok'=>false,'http'=>0,'error'=>'Token kayıtlı değil.'];
        $response = wp_remote_get($url, [
            'timeout' => 35,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
        if (is_wp_error($response)) return ['ok'=>false,'http'=>0,'error'=>$response->get_error_message()];
        $code = intval(wp_remote_retrieve_response_code($response));
        $body_text = (string)wp_remote_retrieve_body($response);
        $body = json_decode($body_text, true);
        $result = [
            'ok' => ($code >= 200 && $code < 300),
            'http' => $code,
            'top_keys' => is_array($body) ? array_keys($body) : [],
            'data_count' => (is_array($body) && isset($body['data']) && is_array($body['data'])) ? count($body['data']) : null,
            'first_item_keys' => (is_array($body) && !empty($body['data'][0]) && is_array($body['data'][0])) ? array_keys($body['data'][0]) : [],
            'has_paging' => (is_array($body) && !empty($body['paging'])) ? 1 : 0,
            'error' => (is_array($body) && !empty($body['error']['message'])) ? (string)$body['error']['message'] : '',
        ];
        if (is_array($body) && isset($body['comments']) && is_array($body['comments'])) {
            $result['comments_data_count'] = isset($body['comments']['data']) && is_array($body['comments']['data']) ? count($body['comments']['data']) : 0;
            $result['comments_keys'] = array_keys($body['comments']);
            $result['comments_first_item_keys'] = !empty($body['comments']['data'][0]) && is_array($body['comments']['data'][0]) ? array_keys($body['comments']['data'][0]) : [];
        }
        return $result;
    }

    private function run_comment_diagnostics($media_id) {
        $ig = $this->ig_api_base();
        $mid = rawurlencode($media_id);
        $tests = [
            'IG medya bilgisi' => add_query_arg(['fields'=>'id,permalink,media_type,comments_count,is_comment_enabled'], $ig.'/'.$mid),
            'IG comments / alan yok' => add_query_arg(['limit'=>5], $ig.'/'.$mid.'/comments'),
            'IG comments / temel alanlar' => add_query_arg(['fields'=>'id,text,timestamp','limit'=>5], $ig.'/'.$mid.'/comments'),
            'IG comments / kullanıcı adı' => add_query_arg(['fields'=>'id,text,username,timestamp','limit'=>5], $ig.'/'.$mid.'/comments'),
            'IG comments / from alanı' => add_query_arg(['fields'=>'id,text,from,timestamp','limit'=>5], $ig.'/'.$mid.'/comments'),
            'IG field expansion' => add_query_arg(['fields'=>'id,comments.limit(5){id,text,username,timestamp}'], $ig.'/'.$mid),
            'Facebook Graph karşılaştırma' => add_query_arg(['fields'=>'id,text,username,timestamp','limit'=>5], 'https://graph.facebook.com/'.self::API_VERSION.'/'.$mid.'/comments'),
        ];
        $out=[];
        foreach ($tests as $name=>$url) {
            $out[$name]=$this->diagnostic_http_get($url);
        }
        return $out;
    }

    private function api_get_with_fields_fallback($base, array $field_sets) {
        $last = null;
        foreach ($field_sets as $fields) {
            $url = add_query_arg(['fields'=>$fields, 'limit'=>100], $base);
            $data = $this->api_get($url);
            if (!is_wp_error($data)) return $data;
            $last = $data;
        }
        return $last ?: new WP_Error('api_error','Instagram API isteği başarısız oldu.');
    }

    private function normalize_permalink($url) {
        $parts = wp_parse_url(trim((string)$url));
        if (!$parts || empty($parts['path'])) return '';
        $path = trim((string)$parts['path'], '/');
        if ($path === '') return '';

        // Instagram aynı medyayı /p/, /reel/ veya /tv/ biçimlerinden biriyle
        // gösterebilir. API'deki permalink ile tarayıcıdaki paylaşım URL'si bu
        // öneklerde farklılaşabildiği için medya kısa kodunu esas alıyoruz.
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        if (count($segments) >= 2 && in_array(strtolower($segments[0]), ['p','reel','reels','tv'], true)) {
            return 'shortcode:' . $segments[1];
        }

        return 'path:/' . rtrim($path, '/') . '/';
    }

    private function find_media_id($post_url) {
        $target = $this->normalize_permalink($post_url);
        if ($target === '') return new WP_Error('bad_url','Geçerli bir Instagram gönderi bağlantısı girin.');
        $url = add_query_arg(['fields'=>'id,permalink,caption,timestamp,media_type','limit'=>100], $this->ig_api_base() . '/me/media');
        $pages = 0;
        while ($url && $pages < 50) {
            $pages++;
            $data = $this->api_get($url);
            if (is_wp_error($data)) return $data;
            foreach (($data['data'] ?? []) as $item) {
                if (!empty($item['permalink']) && $this->normalize_permalink($item['permalink']) === $target) return (string)$item['id'];
            }
            $url = $data['paging']['next'] ?? '';
        }
        return new WP_Error('media_not_found','Gönderi bağlı Instagram hesabının medya listesinde bulunamadı. Gönderinin bu hesaba ait olduğundan emin olun.');
    }

    private function get_media_info($media_id) {
        $base = $this->ig_api_base() . '/' . rawurlencode($media_id);
        $field_sets = [
            'id,permalink,media_type,comments_count,is_comment_enabled,username,owner',
            'id,permalink,media_type,comments_count,is_comment_enabled,username',
            'id,permalink,media_type,comments_count,is_comment_enabled',
            'id,permalink,media_type,comments_count',
            'id,permalink,media_type'
        ];
        $last = null;
        foreach ($field_sets as $fields) {
            $url = add_query_arg(['fields'=>$fields], $base);
            $data = $this->api_get($url);
            if (!is_wp_error($data)) return $data;
            $last = $data;
        }
        return $last ?: new WP_Error('media_info_error','Gönderi bilgileri Instagram API’den alınamadı.');
    }

    private function fetch_comments_via_field_expansion($media_id) {
        $base = $this->ig_api_base() . '/' . rawurlencode($media_id);
        $field_sets = [
            'comments.limit(50){id,text,username,timestamp,from}',
            'comments.limit(50){id,text,username,timestamp}',
            'comments.limit(50){id,text,from,timestamp}',
            'comments.limit(50){id,text,timestamp}'
        ];
        $last = null;
        foreach ($field_sets as $fields) {
            $url = add_query_arg(['fields'=>$fields], $base);
            $data = $this->api_get($url);
            if (is_wp_error($data)) { $last = $data; continue; }
            $node = $data['comments'] ?? null;
            if (!is_array($node)) continue;
            $comments = [];
            $pages = 0;
            while (true) {
                $pages++;
                foreach (($node['data'] ?? []) as $c) {
                    $username = (string)($c['username'] ?? ($c['from']['username'] ?? ''));
                    $comments[] = [
                        'id'=>(string)($c['id'] ?? ''),
                        'username'=>$username,
                        'text'=>(string)($c['text'] ?? ''),
                        'timestamp'=>(string)($c['timestamp'] ?? ''),
                    ];
                }
                $next = $node['paging']['next'] ?? '';
                if (!$next || $pages >= 200) break;
                $next_data = $this->api_get($next);
                if (is_wp_error($next_data)) return $next_data;
                // Pagination URL for an expanded edge generally returns a normal data/paging envelope.
                $node = isset($next_data['data']) ? $next_data : ($next_data['comments'] ?? []);
                if (!is_array($node)) break;
            }
            return $comments;
        }
        return $last ?: [];
    }

    private function fetch_all_comments($media_id) {
        $base = $this->ig_api_base() . '/' . rawurlencode($media_id) . '/comments';
        $first = $this->api_get_with_fields_fallback($base, [
            'id,text,username,timestamp,from',
            'id,text,username,timestamp',
            'id,text,from,timestamp',
            'id,text,timestamp'
        ]);
        if (is_wp_error($first)) return $first;

        $comments = [];
        $data = $first;
        $pages = 0;
        while (true) {
            $pages++;
            foreach (($data['data'] ?? []) as $c) {
                $username = (string)($c['username'] ?? ($c['from']['username'] ?? ''));
                $comments[] = [
                    'id'=>(string)($c['id'] ?? ''),
                    'username'=>$username,
                    'text'=>(string)($c['text'] ?? ''),
                    'timestamp'=>(string)($c['timestamp'] ?? ''),
                ];
            }
            $next = $data['paging']['next'] ?? '';
            if (!$next || $pages >= 200) break;
            $data = $this->api_get($next);
            if (is_wp_error($data)) return $data;
        }

        // Meta bazı hesaplarda /comments kenarında boş veri döndürebiliyor. Aynı yorumları
        // medya nesnesindeki comments field-expansion yoluyla ikinci kez dene.
        if (!$comments) {
            $fallback = $this->fetch_comments_via_field_expansion($media_id);
            if (is_wp_error($fallback)) return $fallback;
            if ($fallback) $comments = $fallback;
        }
        return $comments;
    }

    private function extract_mentions($text) {
        preg_match_all('/(?<![A-Za-z0-9._])@([A-Za-z0-9._]+)/u', (string)$text, $m);
        $mentions = array_map('strtolower', $m[1] ?? []);
        return array_values(array_unique($mentions));
    }

    private function normalize_comment_text($text) {
        $text = strtolower(trim(wp_strip_all_tags((string)$text)));
        $text = preg_replace('/\s+/u', ' ', $text);
        return mb_substr($text, 0, 255);
    }

    private function get_campaign($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->campaigns_table} WHERE id=%d", $id));
    }

    private function evaluate_comments($campaign, array $comments) {
        $own = strtolower(ltrim((string)get_option(self::OPTION_USERNAME, 'madagaskarsirkiturkiye'), '@'));
        $seen_text = [];
        $rows = [];

        foreach ($comments as $c) {
            $username = strtolower(ltrim(trim((string)$c['username']), '@'));
            $mentions = $this->extract_mentions($c['text']);
            // Marka hesabını ve kişinin kendisini arkadaş etiketi olarak sayma.
            $mentions = array_values(array_filter($mentions, function($m) use ($own, $username) {
                return $m !== '' && $m !== $own && $m !== $username;
            }));
            $mentions = array_values(array_unique($mentions));
            $norm = $this->normalize_comment_text($c['text']);
            $valid = 1;
            $reason = '';

            if ($username === '') {
                $valid = 0; $reason = 'Kullanıcı adı API yanıtında yok';
            } elseif (count($mentions) < intval($campaign->min_mentions)) {
                $valid = 0; $reason = 'Yeterli farklı arkadaş etiketi yok';
            } elseif (intval($campaign->dedupe_same_text) === 1) {
                $key = $username . '|' . $norm;
                if (isset($seen_text[$key])) {
                    $valid = 0; $reason = 'Aynı kullanıcının aynı yorum tekrarı';
                } else {
                    $seen_text[$key] = true;
                }
            }

            $rows[] = [
                'comment_id'=>(string)$c['id'],
                'username'=>$username,
                'comment_text'=>(string)$c['text'],
                'comment_timestamp'=>(string)$c['timestamp'],
                'mentions'=>wp_json_encode($mentions, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'mention_count'=>count($mentions),
                'is_valid'=>$valid,
                'invalid_reason'=>$reason,
                'normalized_text'=>$norm,
            ];
        }
        return $rows;
    }

    private function sync_campaign($campaign_id) {
        global $wpdb;
        $campaign = $this->get_campaign($campaign_id);
        if (!$campaign) return new WP_Error('not_found','Çekiliş bulunamadı.');
        $media_id = (string)$campaign->media_id;
        if ($media_id === '') {
            $found = $this->find_media_id($campaign->post_url);
            if (is_wp_error($found)) return $found;
            $media_id = $found;
            $wpdb->update($this->campaigns_table, ['media_id'=>$media_id,'updated_at'=>$this->now()], ['id'=>$campaign_id], ['%s','%s'], ['%d']);
            $campaign = $this->get_campaign($campaign_id);
        }

        $media_info = $this->get_media_info($media_id);
        if (is_wp_error($media_info)) return $media_info;

        $comments = $this->fetch_all_comments($media_id);
        if (is_wp_error($comments)) return $comments;

        $api_count = isset($media_info['comments_count']) ? intval($media_info['comments_count']) : null;
        if (!$comments && $api_count !== null && $api_count > 0) {
            $mode_hint = 'Token geçerli ve gönderi okunabiliyor. Bu sürüm yorum çağrılarını Meta’nın güncel sürümlü uç noktası üzerinden (' . self::API_VERSION . ') yapar. Liste yine boşsa Meta tarafında yorum erişim kısıtı vardır; uygulamayı Live moda almak tek başına zorunlu olmayabilir.';
            return new WP_Error('comments_empty_but_count_positive', 'Instagram API gönderide ' . $api_count . ' yorum olduğunu bildiriyor ancak yorum listesini boş döndürdü. ' . $mode_hint);
        }

        $rows = $this->evaluate_comments($campaign, $comments);

        $wpdb->delete($this->comments_table, ['campaign_id'=>$campaign_id], ['%d']);
        foreach ($rows as $r) {
            $wpdb->insert($this->comments_table, array_merge(['campaign_id'=>$campaign_id,'created_at'=>$this->now()], $r),
                ['%d','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s']);
        }

        $total = count($rows);
        $valid = 0;
        foreach ($rows as $r) if ($r['is_valid']) $valid++;
        $wpdb->update($this->campaigns_table, [
            'status'=>'ready',
            'total_comments'=>$total,
            'valid_entries'=>$valid,
            'invalid_comments'=>$total-$valid,
            'fetched_at'=>$this->now(),
            'updated_at'=>$this->now(),
        ], ['id'=>$campaign_id], ['%s','%d','%d','%d','%s','%s'], ['%d']);
        return true;
    }

    public function save_settings() {
        $this->require_admin();
        check_admin_referer('mck2_save_settings');
        $username = sanitize_text_field(wp_unslash($_POST['ig_username'] ?? 'madagaskarsirkiturkiye'));
        update_option(self::OPTION_USERNAME, ltrim($username,'@'), false);
        $token = trim((string)wp_unslash($_POST['access_token'] ?? ''));
        if ($token !== '') update_option(self::OPTION_TOKEN, $this->encrypt_secret($token), false);
        if (!empty($_POST['clear_token'])) {
            delete_option(self::OPTION_TOKEN);
            delete_option(self::OPTION_CONNECTED_ID);
        }
        $this->redirect(['tab'=>'settings','mck_notice'=>'settings_saved']);
    }

    public function test_connection() {
        $this->require_admin();
        check_admin_referer('mck2_test_connection');
        $data = $this->api_get('https://graph.instagram.com/me?fields=id,username,account_type');
        if (is_wp_error($data)) $this->flash_error($data->get_error_message());
        if (!empty($data['username'])) update_option(self::OPTION_USERNAME, sanitize_text_field($data['username']), false);
        if (!empty($data['id'])) update_option(self::OPTION_CONNECTED_ID, sanitize_text_field($data['id']), false);
        set_transient('mck2_conn_' . get_current_user_id(), $data, 120);
        $this->redirect(['tab'=>'settings','mck_notice'=>'connection_ok']);
    }

    public function create_campaign() {
        global $wpdb;
        $this->require_admin();
        check_admin_referer('mck2_create_campaign');
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $post_url = esc_url_raw(wp_unslash($_POST['post_url'] ?? ''));
        if ($title === '' || $post_url === '') $this->flash_error('Çekiliş adı ve Instagram gönderi bağlantısı zorunludur.');
        $now = $this->now();
        $wpdb->insert($this->campaigns_table, [
            'title'=>$title,
            'post_url'=>$post_url,
            'media_id'=>preg_replace('/[^0-9]/','',(string)wp_unslash($_POST['media_id'] ?? '')),
            'min_mentions'=>max(1,min(20,intval($_POST['min_mentions'] ?? 1))),
            'winner_count'=>max(1,min(100,intval($_POST['winner_count'] ?? 2))),
            'reserve_count'=>max(0,min(100,intval($_POST['reserve_count'] ?? 2))),
            'one_user_one_entry'=>!empty($_POST['one_user_one_entry']) ? 1 : 0,
            'one_user_one_win'=>!empty($_POST['one_user_one_win']) ? 1 : 0,
            'dedupe_same_text'=>!empty($_POST['dedupe_same_text']) ? 1 : 0,
            'status'=>'draft','created_at'=>$now,'updated_at'=>$now,
        ], ['%s','%s','%s','%d','%d','%d','%d','%d','%d','%s','%s','%s']);
        $id = intval($wpdb->insert_id);
        if (!$id) $this->flash_error('Çekiliş kaydı oluşturulamadı.');
        $sync = $this->sync_campaign($id);
        if (is_wp_error($sync)) $this->flash_error($sync->get_error_message(), $id);
        $this->redirect(['view'=>'campaign','campaign_id'=>$id,'mck_notice'=>'campaign_created']);
    }

    public function sync_campaign_action() {
        $this->require_admin();
        $id = intval($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_sync_campaign_' . $id);
        $campaign = $this->get_campaign($id);
        if (!$campaign) $this->flash_error('Çekiliş bulunamadı.');
        if ($campaign->status === 'drawn') $this->flash_error('Çekiliş sonucu oluşturulduktan sonra yorum listesi dondurulur. Yeni çekiliş açın.', $id);
        $sync = $this->sync_campaign($id);
        if (is_wp_error($sync)) $this->flash_error($sync->get_error_message(), $id);
        $this->redirect(['view'=>'campaign','campaign_id'=>$id,'mck_notice'=>'synced']);
    }

    private function eligible_rows_for_draw($campaign) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT comment_id,username,comment_text,comment_timestamp FROM {$this->comments_table} WHERE campaign_id=%d AND is_valid=1 ORDER BY comment_id ASC",
            $campaign->id
        ));
        if (intval($campaign->one_user_one_entry) === 1) {
            $seen=[]; $filtered=[];
            foreach ($rows as $r) {
                $u=strtolower($r->username);
                if (isset($seen[$u])) continue;
                $seen[$u]=true; $filtered[]=$r;
            }
            return $filtered;
        }
        return $rows;
    }

    public function draw_campaign() {
        global $wpdb;
        $this->require_admin();
        $id = intval($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_draw_campaign_' . $id);
        $campaign = $this->get_campaign($id);
        if (!$campaign) $this->flash_error('Çekiliş bulunamadı.');
        if ($campaign->status === 'drawn') $this->flash_error('Bu çekiliş daha önce sonuçlandırılmış.', $id);
        $rows = $this->eligible_rows_for_draw($campaign);
        if (!$rows) $this->flash_error('Geçerli çekiliş hakkı bulunamadı.', $id);

        $need = intval($campaign->winner_count) + intval($campaign->reserve_count);
        $unique_users = [];
        foreach ($rows as $r) $unique_users[strtolower($r->username)] = true;
        $capacity = intval($campaign->one_user_one_win) === 1 ? count($unique_users) : count($rows);
        if ($capacity < $need) $this->flash_error('Asil + yedek sayısı için yeterli farklı katılımcı yok. Kazanan/yedek sayısını azaltın.', $id);

        $eligible_material=[];
        foreach ($rows as $r) $eligible_material[]=$r->comment_id.'|'.strtolower($r->username);
        $eligible_hash = hash('sha256', implode("\n", $eligible_material));
        $seed_bytes = random_bytes(32);
        $seed = bin2hex($seed_bytes);
        $ranked=[];
        foreach ($rows as $r) {
            $material = $id . '|' . $r->comment_id . '|' . strtolower($r->username);
            $score = hash_hmac('sha256', $material, $seed_bytes);
            $ranked[] = ['row'=>$r,'score'=>$score];
        }
        usort($ranked, function($a,$b){
            $c=strcmp($a['score'],$b['score']);
            if ($c!==0) return $c;
            return strcmp($a['row']->comment_id,$b['row']->comment_id);
        });

        $selected=[]; $seen=[];
        foreach ($ranked as $x) {
            $u=strtolower($x['row']->username);
            if (intval($campaign->one_user_one_win)===1 && isset($seen[$u])) continue;
            $selected[]=$x; $seen[$u]=true;
            if (count($selected)>=$need) break;
        }
        if (count($selected)<$need) $this->flash_error('Yeterli benzersiz kazanan seçilemedi.', $id);

        $wpdb->delete($this->draws_table, ['campaign_id'=>$id], ['%d']);
        $drawn_at=$this->now();
        foreach ($selected as $i=>$x) {
            $is_winner = $i < intval($campaign->winner_count);
            $type = $is_winner ? 'winner' : 'reserve';
            $pos = $is_winner ? $i+1 : ($i-intval($campaign->winner_count))+1;
            $r=$x['row'];
            $wpdb->insert($this->draws_table, [
                'campaign_id'=>$id,'result_type'=>$type,'position_no'=>$pos,
                'comment_id'=>$r->comment_id,'username'=>$r->username,'comment_text'=>$r->comment_text,
                'verification_status'=>'pending','verification_note'=>'','score_hash'=>$x['score'],'drawn_at'=>$drawn_at
            ], ['%d','%s','%d','%s','%s','%s','%s','%s','%s','%s']);
        }
        $wpdb->update($this->campaigns_table, [
            'status'=>'drawn','drawn_at'=>$drawn_at,'audit_seed'=>$seed,'eligible_hash'=>$eligible_hash,'updated_at'=>$drawn_at
        ], ['id'=>$id], ['%s','%s','%s','%s','%s'], ['%d']);
        $this->redirect(['view'=>'campaign','campaign_id'=>$id,'mck_notice'=>'drawn']);
    }

    private function result_snapshot($row) {
        return hash('sha256', wp_json_encode([
            (int)$row->id, (int)$row->campaign_id, (string)$row->result_type,
            (int)$row->position_no, (string)$row->comment_id, (string)$row->username,
            (string)$row->drawn_at, (string)$row->score_hash, (string)$row->verification_status
        ]));
    }

    private function winner_rows($campaign_id, $lock = false) {
        global $wpdb;
        return (array)$wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->draws_table} WHERE campaign_id=%d AND result_type='winner' ORDER BY position_no ASC" . ($lock ? ' FOR UPDATE' : ''),
            $campaign_id
        ));
    }

    private function publication_ready($campaign, $rows) {
        if (!$campaign || $campaign->status !== 'drawn' || (int)$campaign->winner_count < 1 || count($rows) !== (int)$campaign->winner_count) return false;
        foreach (array_values($rows) as $i => $row) {
            if ((int)$row->campaign_id !== (int)$campaign->id || $row->result_type !== 'winner' ||
                (int)$row->position_no !== $i + 1 || $row->verification_status !== 'verified') return false;
        }
        return true;
    }

    private function publication_fingerprint($campaign, $rows) {
        return hash('sha256', wp_json_encode([
            (int)$campaign->id, (int)$campaign->winner_count, (string)$campaign->drawn_at,
            (string)$campaign->eligible_hash, array_map([$this, 'result_snapshot'], $rows)
        ]));
    }

    public function public_results_published($campaign, $rows = null) {
        if (!$campaign) return false;
        if ($rows === null) $rows = $this->winner_rows($campaign->id);
        if (!$this->publication_ready($campaign, $rows)) return false;
        $record = get_option('mck2_publication_' . (int)$campaign->id, []);
        return is_array($record) && !empty($record['fingerprint']) &&
            hash_equals((string)$record['fingerprint'], $this->publication_fingerprint($campaign, $rows));
    }

    public function nocache_public_results() {
        if (is_admin()) return;
        if (!$this->is_results_page() && !get_query_var('mck_verify', '') && empty($_GET['mck_verify'])) return;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
    }

    public function publish_results() {
        global $wpdb;
        $this->require_admin();
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('Yayın için POST isteği gerekir.');
        $id = absint($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_publish_results_' . $id);
        $expected = sanitize_text_field(wp_unslash($_POST['expected_results'] ?? ''));
        if (!$id || !$expected) $this->flash_error('Yayın ekranını yenileyin.', $id);
        if ($wpdb->query('START TRANSACTION') === false) $this->flash_error('Yayın işlemi başlatılamadı.', $id);
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->campaigns_table} WHERE id=%d FOR UPDATE", $id));
        $rows = $this->winner_rows($id, true);
        if (!$this->publication_ready($campaign, $rows) || !hash_equals($expected, $this->publication_fingerprint($campaign, $rows))) {
            $wpdb->query('ROLLBACK');
            $this->flash_error('Sonuçlar değişmiş veya asil adayların kontrolü tamamlanmamış. Ekranı yenileyin; tüm asil adayları doğruladıktan sonra yayımlayın.', $id);
        }
        if (!$this->public_results_published($campaign, $rows)) {
            $saved = update_option('mck2_publication_' . $id, [
                'fingerprint'=>$expected, 'published_at'=>$this->now(), 'published_by'=>get_current_user_id()
            ], false);
            if (!$saved) {
                $wpdb->query('ROLLBACK');
                $this->flash_error('Yayın kaydı yazılamadı.', $id);
            }
        }
        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            wp_cache_delete('mck2_publication_' . $id, 'options');
            $this->flash_error('Yayın kaydı onaylanamadı.', $id);
        }
        $this->redirect(['view'=>'campaign','campaign_id'=>$id,'mck_notice'=>'published']);
    }

    public function unpublish_results() {
        $this->require_admin();
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('POST isteği gerekir.');
        $id = absint($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_unpublish_results_' . $id);
        update_option('mck2_publication_' . $id, [], false);
        $this->redirect(['view'=>'campaign','campaign_id'=>$id,'mck_notice'=>'unpublished']);
    }

    public function verify_result() {
        global $wpdb;
        $this->require_admin();
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('POST isteği gerekir.');
        $draw_id = absint($_POST['draw_id'] ?? 0);
        $campaign_id = absint($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_verify_result_' . $draw_id);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE id=%d AND campaign_id=%d", $draw_id, $campaign_id));
        $expected = sanitize_text_field(wp_unslash($_POST['expected_result'] ?? ''));
        if (!$row || !$expected || !hash_equals($this->result_snapshot($row), $expected)) {
            $this->flash_error('Bu aday değişmiş. Ekranı yenileyip güncel adayı kontrol edin.', $campaign_id);
        }
        $status = sanitize_key($_POST['verification_status'] ?? 'pending');
        if (!in_array($status, ['pending','verified','disqualified'], true)) $this->flash_error('Geçersiz doğrulama durumu.', $campaign_id);
        $note = sanitize_textarea_field(wp_unslash($_POST['verification_note'] ?? ''));
        $where = ['id'=>$draw_id, 'campaign_id'=>$campaign_id, 'comment_id'=>$row->comment_id,
            'username'=>$row->username, 'drawn_at'=>$row->drawn_at, 'score_hash'=>$row->score_hash,
            'verification_status'=>$row->verification_status];
        $changed = $wpdb->update($this->draws_table, ['verification_status'=>$status,'verification_note'=>$note], $where,
            ['%s','%s'], ['%d','%d','%s','%s','%s','%s','%s']);
        if ($changed === false) $this->flash_error('Doğrulama kaydedilemedi.', $campaign_id);
        if ($changed === 0) {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE id=%d AND campaign_id=%d", $draw_id, $campaign_id));
            if (!$current || $current->comment_id !== $row->comment_id || $current->username !== $row->username ||
                $current->drawn_at !== $row->drawn_at || $current->score_hash !== $row->score_hash ||
                $current->verification_status !== $status || $current->verification_note !== $note) {
                $this->flash_error('Aday kayıt sırasında değişmiş. Ekranı yenileyin.', $campaign_id);
            }
        }
        $this->redirect(['view'=>'campaign','campaign_id'=>$campaign_id,'mck_notice'=>'verified']);
    }

    public function export_campaign() {
        global $wpdb;
        $this->require_admin();
        $id=intval($_GET['campaign_id'] ?? 0);
        check_admin_referer('mck2_export_campaign_' . $id);
        $campaign=$this->get_campaign($id);
        if (!$campaign) wp_die('Çekiliş bulunamadı.');
        $draw_map=[];
        foreach ($wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE campaign_id=%d",$id)) as $d) {
            $draw_map[$d->comment_id]=$d;
        }
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->comments_table} WHERE campaign_id=%d ORDER BY id ASC",$id));
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="madagaskar-cekilis-' . $id . '-' . gmdate('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','w');
        fputcsv($out,['Çekiliş','Gönderi','Durum','Kullanıcı','Yorum','Etiket Sayısı','Etiketler','Geçersiz Nedeni','Sonuç','Sıra','Doğrulama','Yorum Tarihi','Yorum ID','Eligible Hash'], ';','"','');
        foreach ($rows as $r) {
            $d=$draw_map[$r->comment_id] ?? null;
            $mentions=json_decode($r->mentions,true); if(!is_array($mentions))$mentions=[];
            fputcsv($out,array_map(['MCK_Reporting','csv_cell'],[
                $campaign->title,$campaign->post_url,$r->is_valid?'Geçerli':'Geçersiz','@'.$r->username,$r->comment_text,
                $r->mention_count,implode(', ',array_map(function($x){return '@'.$x;},$mentions)),$r->invalid_reason,
                $d ? ($d->result_type==='winner'?'Asil':'Yedek') : '',$d ? $d->position_no : '',$d ? $d->verification_status : '',
                $r->comment_timestamp,$r->comment_id,$campaign->eligible_hash
            ]),';','"','');
        }
        fclose($out); exit;
    }

    public function diagnose_campaign() {
        $this->require_admin();
        $id = intval($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_diagnose_campaign_' . $id);
        $campaign = $this->get_campaign($id);
        if (!$campaign) $this->flash_error('Çekiliş bulunamadı.');
        $media_id = (string)$campaign->media_id;
        if ($media_id === '') {
            $found = $this->find_media_id($campaign->post_url);
            if (is_wp_error($found)) $this->flash_error($found->get_error_message(), $id);
            $media_id = $found;
        }
        $diag = $this->run_comment_diagnostics($media_id);
        set_transient('mck2_diag_' . get_current_user_id() . '_' . $id, $diag, 300);
        $this->redirect(['view'=>'campaign','campaign_id'=>$id,'mck_notice'=>'diagnostics_ready']);
    }

    private function verification_code($campaign) {
        $material = (string)$campaign->eligible_hash . '|' . (string)$campaign->drawn_at . '|' . intval($campaign->id);
        $raw = hash('sha256', $material, true);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i=0; $i<6; $i++) {
            $code .= $alphabet[ord($raw[$i]) % strlen($alphabet)];
        }
        return $code;
    }

    private function find_campaign_by_code($code) {
        global $wpdb;
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$code));
        if (strlen($code) !== 6) return null;
        $rows = $wpdb->get_results("SELECT * FROM {$this->campaigns_table} WHERE status='drawn' ORDER BY id DESC LIMIT 500");
        foreach ($rows as $c) {
            if (hash_equals($this->verification_code($c), $code)) return $c;
        }
        return null;
    }

    private function verification_status_label($status) {
        if ($status === 'verified') return 'Doğrulandı';
        if ($status === 'disqualified') return 'Şartı sağlamadı';
        return 'Kontrol bekliyor';
    }

    public function maybe_render_public_result() {
        if (is_admin()) return;
        $this->maybe_redirect_legacy_sitemap();
        $code = (string)get_query_var('mck_verify', '');
        if ($code === '' && !empty($_GET['mck_verify'])) $code = sanitize_text_field(wp_unslash($_GET['mck_verify']));
        if ($code === '') return;
        global $wpdb;
        $c = $this->find_campaign_by_code($code);
        if (!$c || !$this->public_results_published($c)) {
            status_header(404); nocache_headers(); header('X-Robots-Tag: noindex, nofollow', true);
            echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Çekiliş sonucu bulunamadı</title></head><body style="font-family:Arial,sans-serif;background:#fbf7f2;padding:40px"><div style="max-width:760px;margin:auto;background:#fff;padding:30px;border-radius:18px;border:1px solid #eadfd7"><h1>Sonuç bulunamadı</h1><p>Sonuç henüz yayımlanmadı veya doğrulama kodu geçersiz.</p></div></body></html>'; exit;
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE campaign_id=%d AND result_type='winner' ORDER BY position_no ASC", $c->id));
        if (!$this->public_results_published($c, $rows)) {
            status_header(404); nocache_headers(); wp_die('Sonuçlar henüz yayımlanmadı veya yeniden kontrol bekliyor.', '', ['response'=>404]);
        }
        $code = $this->verification_code($c); $display_title = mb_strtoupper(trim((string)$c->title));
        header('X-Robots-Tag: index, follow', true); nocache_headers();
        $seo_title = $display_title . ' Çekiliş Sonucu | Madagaskar Sirki';
        $seo_desc = $display_title . ' Madagaskar Sirki çekiliş sonucu. Asil kazananlar, geçerli katılım sayısı, çekiliş zamanı ve resmî doğrulama kaydı.';
        $canonical = $this->verification_url($code);
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="index,follow,max-image-preview:large"><title>'.esc_html($seo_title).'</title><meta name="description" content="'.esc_attr($seo_desc).'"><link rel="canonical" href="'.esc_url($canonical).'">';
        echo '<style>body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#fbf7f2;color:#111827}.wrap{max-width:880px;margin:34px auto;padding:0 16px}.card{background:#fff;border:1px solid #eadfd7;border-radius:22px;padding:30px;box-shadow:0 12px 32px rgba(17,24,39,.07)}h1{margin:0 0 8px;font-size:32px;line-height:1.12}.brand{color:#7f1d1d;font-weight:900;letter-spacing:.5px}.meta{color:#6b7280;line-height:1.5}.code{font-size:32px;letter-spacing:6px;font-weight:900;background:#111827;color:#fff;display:inline-block;padding:13px 19px;border-radius:12px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:22px}.result{border:1px solid #eadfd7;border-radius:15px;padding:17px;background:#fff}.result a{color:#111827;text-decoration:none}.result a:hover{color:#b91c1c;text-decoration:underline}.badge{font-size:12px;font-weight:800;padding:5px 9px;border-radius:999px;background:#f7f1ed;display:inline-block}.hash{word-break:break-all;font-family:monospace;font-size:12px;background:#f9fafb;padding:12px;border-radius:10px}.ticket{margin-top:22px;background:#111827;color:#fff;border-radius:16px;padding:17px 18px;line-height:1.55}.ticket p{margin:0 0 8px}.ticket p:last-child{margin:0;color:#fbbf24;font-weight:900}.logo{width:122px;height:122px;object-fit:contain;display:block;margin:0 auto 10px}.back{display:inline-block;margin-top:18px;color:#b91c1c;font-weight:900;text-decoration:none}@media(max-width:640px){.wrap{margin:14px auto;padding:0 10px}.card{padding:18px 16px;border-radius:18px}.logo{width:92px;height:92px;margin-bottom:6px}.brand{font-size:14px;text-align:center}h1{font-size:25px!important;line-height:1.15!important}.meta{font-size:13px}.code{font-size:26px;letter-spacing:4px;padding:12px 15px}.grid{grid-template-columns:1fr;gap:10px;margin-top:16px}.result{padding:14px}.ticket{font-size:13px;padding:14px;border-radius:14px}.hash{font-size:10px;padding:10px}.back{display:block;text-align:center;padding:10px 0}}</style></head><body><div class="wrap"><div class="card">';
        echo '<img class="logo" src="'.esc_url(self::LOGO_URL).'" alt="Madagaskar Sirki"><div class="brand" style="text-align:center">MADAGASKAR SİRKİ</div><h1 style="text-align:center">'.esc_html($display_title).' — Çekiliş Sonucu</h1>';
        echo '<p class="meta">Çekiliş zamanı: '.esc_html(mysql2date('d.m.Y H:i', $c->drawn_at)).' · Geçerli katılım hakkı: <strong>'.intval($c->valid_entries).'</strong></p><p>Doğrulama kodu:</p><div class="code">'.esc_html($code).'</div><div class="grid">';
        foreach ($rows as $r) { $profile = $this->instagram_profile_url($r->username); echo '<div class="result"><div class="badge">Kazanan '.intval($r->position_no).'</div><h2 style="margin:10px 0 6px"><a href="'.esc_url($profile).'" target="_blank" rel="noopener nofollow">@'.esc_html($r->username).'</a></h2></div>'; }
        echo '</div><div class="ticket"><p><strong>🎟 Ücretsiz giriş hakkı</strong></p><p>Her kazanan <strong>1 veli + 1 çocuk</strong> için çift kişilik ücretsiz giriş hakkı kazanmıştır. Salon gişesinde Instagram kullanıcı adınızı göstererek ücretsiz biletinizi temin edebilirsiniz.</p><p>İstediğiniz seansta kullanabilirsiniz.</p></div>';
        echo '<h3 style="margin-top:28px">Denetim izi</h3><div class="hash">'.esc_html($c->eligible_hash).'</div><p class="meta" style="margin-top:22px">Bu sayfa Madagaskar Sirki çekiliş sisteminde kayıtlı sonuçların doğrulanması amacıyla oluşturulmuştur.</p><a class="back" href="'.esc_url($this->public_results_url()).'">← Tüm çekiliş sonuçları</a></div></div></body></html>'; exit;
    }

    public function story_result() {
        global $wpdb;
        $this->require_admin();
        $id = intval($_GET['campaign_id'] ?? 0);
        check_admin_referer('mck2_story_result_' . $id);
        $c = $this->get_campaign($id);
        if (!$c || !$this->public_results_published($c)) wp_die('Story için önce tüm asil adayları doğrulayın ve çekiliş sonuçlarını yayımlayın.');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE campaign_id=%d AND result_type='winner' ORDER BY position_no ASC", $c->id));
        $winners = array_values($rows);
        if (!$this->public_results_published($c, $winners)) wp_die('Sonuçlar değişmiş; kontrol edip yeniden yayımlayın.');
        $code = $this->verification_code($c);
        $verify_url = $this->verification_url($code);
        $account = get_option(self::OPTION_USERNAME,'madagaskarsirkiturkiye');
        $logo_data = $this->brand_logo_data_uri();
        $event_title = mb_strtoupper(trim((string)$c->title));
        if ($event_title === '') $event_title = 'ÇEKİLİŞ';
        $winner_count = max(1, count($winners));
        if ($winner_count <= 2) {
            $cols = $winner_count;
        } elseif ($winner_count <= 3) {
            $cols = 3;
        } else {
            $cols = 2;
        }
        $rows_count = (int)ceil($winner_count / $cols);
        $winner_start_y = 360;
        $row_gap = ($winner_count <= 2) ? 0 : 240;
        $winner_area_bottom = $winner_start_y + (($rows_count - 1) * $row_gap) + 155;
        $entries_y = max(720, $winner_area_bottom + 90);
        $note_y = $entries_y + 115;
        $info_y = $note_y + 365;
        $brand_y = 1695;
        nocache_headers();
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Story Sonuç Görseli</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#111827;color:#111;margin:0;padding:24px}.toolbar{max-width:1080px;margin:0 auto 16px;display:flex;gap:10px;flex-wrap:wrap}.toolbar button,.toolbar a{background:#2563eb;color:#fff;border:0;padding:12px 16px;border-radius:9px;text-decoration:none;font-weight:700;cursor:pointer}.toolbar a{background:#374151}.frame{max-width:540px;margin:auto;background:#fff;box-shadow:0 12px 35px rgba(0,0,0,.35)}svg{display:block;width:100%;height:auto}</style></head><body>';
        echo '<div class="toolbar"><button id="downloadPng">📥 Story PNG İndir (1080×1920)</button><a href="'.esc_url($verify_url).'" target="_blank">🔎 Sonucu Doğrula</a><a href="'.esc_url(admin_url('admin.php?page=madagaskar-cekilis&view=campaign&campaign_id='.$id)).'">← Çekilişe dön</a></div>';
        echo '<div class="frame"><svg id="storySvg" xmlns="http://www.w3.org/2000/svg" width="1080" height="1920" viewBox="0 0 1080 1920">';
        echo '<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#faf7f7"/></linearGradient></defs><rect width="1080" height="1920" fill="url(#bg)"/>';
        echo '<g opacity=".12" fill="#b91c1c"><circle cx="90" cy="160" r="10"/><circle cx="180" cy="100" r="7"/><circle cx="980" cy="220" r="12"/><circle cx="900" cy="130" r="6"/><circle cx="130" cy="1710" r="9"/><circle cx="930" cy="1770" r="11"/></g>';
        if ($logo_data !== '') { echo '<image href="'.esc_attr($logo_data).'" x="52" y="28" width="165" height="165" preserveAspectRatio="xMidYMid meet"/>'; }
        echo '<text x="540" y="92" text-anchor="middle" font-size="33" font-weight="800" fill="#7f1d1d">MADAGASKAR SİRKİ</text>';
        echo '<text x="540" y="152" text-anchor="middle" font-size="52" font-weight="900" fill="#111827">'.esc_html($event_title).'</text>';
        echo '<text x="540" y="215" text-anchor="middle" font-size="56" font-weight="900" fill="#b91c1c">ÇEKİLİŞ KAZANANLARI</text>';
        foreach ($winners as $i=>$r) {
            $row = intdiv($i, $cols);
            $col = $i % $cols;
            if ($cols === 1) {
                $x = 540;
            } elseif ($cols === 2) {
                $x = 300 + ($col * 480);
            } else {
                $x = 180 + ($col * 360);
            }
            $y = $winner_start_y + ($row * $row_gap);
            $initial = mb_strtoupper(mb_substr($r->username,0,1));
            $ulen = mb_strlen($r->username);
            $name_size = $ulen > 18 ? 25 : ($ulen > 14 ? 29 : 34);
            echo '<text x="'.intval($x-105).'" y="'.intval($y+22).'" font-size="86" font-weight="300" fill="#6b7280">'.intval($r->position_no).'.</text>';
            echo '<circle cx="'.intval($x).'" cy="'.intval($y-25).'" r="72" fill="#7f1d1d"/><text x="'.intval($x).'" y="'.intval($y+2).'" text-anchor="middle" font-size="64" font-weight="800" fill="#fff">'.esc_html($initial).'</text>';
            echo '<text x="'.intval($x).'" y="'.intval($y+100).'" text-anchor="middle" font-size="'.intval($name_size).'" font-weight="800" fill="#111827">@'.esc_html($r->username).'</text>';
        }
        echo '<text x="540" y="'.intval($entries_y).'" text-anchor="middle" font-size="54" font-weight="800" fill="#b91c1c">'.intval($c->total_comments).'</text>';
        echo '<text x="540" y="'.intval($entries_y+58).'" text-anchor="middle" font-size="43" fill="#111827">yorum arasından</text>';
        echo '<text x="540" y="'.intval($entries_y+106).'" text-anchor="middle" font-size="28" fill="#6b7280">yapılan çekiliş sonucunda '.intval(count($winners)).' kazanan belirlenmiştir.</text>';
        echo '<rect x="85" y="'.intval($note_y).'" width="910" height="320" rx="32" fill="#111827"/>';
        echo '<text x="540" y="'.intval($note_y+58).'" text-anchor="middle" font-size="31" font-weight="900" fill="#fff">🎟 ÜCRETSİZ GİRİŞ HAKKI</text>';
        echo '<text x="540" y="'.intval($note_y+112).'" text-anchor="middle" font-size="27" font-weight="700" fill="#fff">Her kazanan 1 veli + 1 çocuk için</text>';
        echo '<text x="540" y="'.intval($note_y+154).'" text-anchor="middle" font-size="27" font-weight="700" fill="#fff">çift kişilik ücretsiz giriş hakkı kazanmıştır.</text>';
        echo '<text x="540" y="'.intval($note_y+200).'" text-anchor="middle" font-size="24" fill="#e5e7eb">Salon gişesinde Instagram kullanıcı adınızı göstererek</text>';
        echo '<text x="540" y="'.intval($note_y+237).'" text-anchor="middle" font-size="24" fill="#e5e7eb">ücretsiz biletinizi temin edebilirsiniz.</text>';
        echo '<text x="540" y="'.intval($note_y+286).'" text-anchor="middle" font-size="25" font-weight="800" fill="#fbbf24">İstediğiniz seansta kullanabilirsiniz.</text>';
        echo '<text x="300" y="'.intval($info_y).'" text-anchor="middle" font-size="25" fill="#b91c1c">Doğrulama Kodu</text>';
        echo '<text x="300" y="'.intval($info_y+52).'" text-anchor="middle" font-size="46" font-weight="900" letter-spacing="5" fill="#111827">'.esc_html($code).'</text>';
        echo '<text x="780" y="'.intval($info_y).'" text-anchor="middle" font-size="25" fill="#b91c1c">Çekiliş Zamanı</text>';
        echo '<text x="780" y="'.intval($info_y+48).'" text-anchor="middle" font-size="25" font-weight="700" fill="#111827">'.esc_html(mysql2date('d.m.Y H:i', $c->drawn_at)).'</text>';
        if ($logo_data !== '') { echo '<image href="'.esc_attr($logo_data).'" x="338" y="'.intval($brand_y-52).'" width="122" height="122" preserveAspectRatio="xMidYMid meet"/>'; } else { echo '<circle cx="390" cy="'.intval($brand_y).'" r="52" fill="#7f1d1d"/><text x="390" y="'.intval($brand_y+18).'" text-anchor="middle" font-size="36" font-weight="900" fill="#fff">MS</text>'; }
        echo '<text x="460" y="'.intval($brand_y+12).'" font-size="32" font-weight="800" fill="#111827">@'.esc_html($account).'</text>';
        echo '<text x="540" y="1810" text-anchor="middle" font-size="22" fill="#6b7280">Sonucu doğrula: '.esc_html(preg_replace('#^https?://#','',$verify_url)).'</text>';
        echo '</svg></div>';
        echo '<script>document.getElementById("downloadPng").addEventListener("click",function(){var svg=document.getElementById("storySvg");var xml=new XMLSerializer().serializeToString(svg);var blob=new Blob([xml],{type:"image/svg+xml;charset=utf-8"});var url=URL.createObjectURL(blob);var img=new Image();img.onload=function(){var c=document.createElement("canvas");c.width=1080;c.height=1920;var ctx=c.getContext("2d");ctx.fillStyle="#ffffff";ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(img,0,0);URL.revokeObjectURL(url);c.toBlob(function(png){var a=document.createElement("a");a.href=URL.createObjectURL(png);a.download="madagaskar-cekilis-sonucu-'.esc_js($code).'.png";document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();},1500);},"image/png",1);};img.src=url;});</script></body></html>';
        exit;
    }

    public function delete_campaign() {
        global $wpdb;
        $this->require_admin();
        $id=intval($_POST['campaign_id'] ?? 0);
        check_admin_referer('mck2_delete_campaign_' . $id);
        $wpdb->delete($this->draws_table,['campaign_id'=>$id],['%d']);
        $wpdb->delete($this->comments_table,['campaign_id'=>$id],['%d']);
        $wpdb->delete($this->campaigns_table,['id'=>$id],['%d']);
        $this->redirect(['mck_notice'=>'deleted']);
    }

    private function notice_html() {
        $notice=sanitize_key($_GET['mck_notice'] ?? '');
        $messages=[
            'settings_saved'=>['success','Ayarlar kaydedildi.'],
            'connection_ok'=>['success','Instagram API bağlantısı başarılı.'],
            'campaign_created'=>['success','Çekiliş oluşturuldu ve yorumlar alındı.'],
            'synced'=>['success','Yorumlar Instagram’dan yeniden alındı.'],
            'drawn'=>['success','Çekiliş sonucu oluşturuldu ve katılım listesi donduruldu.'],
            'verified'=>['success','Kazanan doğrulama durumu kaydedildi.'],
            'published'=>['success','Çekiliş sonuçları yayımlandı.'],
            'unpublished'=>['success','Çekiliş sonuçları yayından kaldırıldı.'],
            'deleted'=>['success','Çekiliş kaydı silindi.'],
            'diagnostics_ready'=>['success','Instagram API teşhis testi tamamlandı. Sonuçlar aşağıda gösteriliyor.'],
        ];
        if ($notice==='error') {
            $err=get_transient('mck2_err_'.get_current_user_id()); delete_transient('mck2_err_'.get_current_user_id());
            echo '<div class="notice notice-error"><p>'.esc_html($err ?: 'Bir hata oluştu.').'</p></div>'; return;
        }
        if (isset($messages[$notice])) echo '<div class="notice notice-'.$messages[$notice][0].'"><p>'.esc_html($messages[$notice][1]).'</p></div>';
    }

    public function render_admin() {
        $this->require_admin();
        echo '<div class="wrap"><h1>🎪 Madagaskar Sirki – Çekiliş Yönetimi</h1>';
        $this->notice_html();
        $view=sanitize_key($_GET['view'] ?? '');
        if ($view==='campaign') {
            $this->render_campaign_detail(intval($_GET['campaign_id'] ?? 0));
        } else {
            $tab=sanitize_key($_GET['tab'] ?? 'campaigns');
            echo '<nav class="nav-tab-wrapper">';
            echo '<a class="nav-tab '.($tab==='campaigns'?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(['page'=>'madagaskar-cekilis','tab'=>'campaigns'],admin_url('admin.php'))).'">Çekilişler</a>';
            echo '<a class="nav-tab '.($tab==='new'?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(['page'=>'madagaskar-cekilis','tab'=>'new'],admin_url('admin.php'))).'">Yeni Çekiliş</a>';
            echo '<a class="nav-tab '.($tab==='settings'?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(['page'=>'madagaskar-cekilis','tab'=>'settings'],admin_url('admin.php'))).'">Instagram Bağlantısı</a>';
            echo '</nav>';
            if ($tab==='new') $this->render_new_campaign();
            elseif ($tab==='settings') $this->render_settings();
            else $this->render_campaigns();
        }
        echo '</div>';
    }

    private function card_start() { echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;margin:18px 0;max-width:1100px">'; }
    private function card_end() { echo '</div>'; }

    private function render_campaigns() {
        MCK_Reporting::render_list();
    }

    private function render_new_campaign() {
        $token=$this->get_token();
        $this->card_start();
        echo '<h2>Yeni Instagram Çekilişi</h2>';
        if (!$token) echo '<div class="notice notice-warning inline"><p>Önce <strong>Instagram Bağlantısı</strong> sekmesinden API erişim anahtarını kaydedin.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mck2_create_campaign">';
        wp_nonce_field('mck2_create_campaign');
        echo '<table class="form-table">';
        echo '<tr><th>Çekiliş adı</th><td><input required class="regular-text" name="title" placeholder="Çubuk – 26 Eylül 2026"></td></tr>';
        echo '<tr><th>Instagram gönderi bağlantısı</th><td><input required class="large-text" name="post_url" placeholder="https://www.instagram.com/p/..."></td></tr>';
        echo '<tr><th>Media ID</th><td><input class="regular-text" name="media_id"><p class="description">Normalde boş bırakın; sistem gönderi bağlantısından bulur.</p></td></tr>';
        echo '<tr><th>Minimum farklı arkadaş etiketi</th><td><input type="number" min="1" max="20" name="min_mentions" value="1"></td></tr>';
        echo '<tr><th>Asil kazanan</th><td><input type="number" min="1" max="100" name="winner_count" value="2"></td></tr>';
        echo '<tr><th>Yedek kazanan</th><td><input type="number" min="0" max="100" name="reserve_count" value="2"></td></tr>';
        echo '<tr><th>Katılım kuralları</th><td>';
        echo '<label><input type="checkbox" name="dedupe_same_text" value="1" checked> Aynı kullanıcının birebir aynı yorum tekrarını tek hak say</label><br>';
        echo '<label><input type="checkbox" name="one_user_one_entry" value="1"> Her kullanıcıya yalnızca 1 çekiliş hakkı ver</label><br>';
        echo '<label><input type="checkbox" name="one_user_one_win" value="1" checked> Aynı hesap birden fazla asil/yedek sıra kazanamasın</label>';
        echo '<p class="description"><strong>Madagaskar standart ayarı:</strong> “Her farklı yorum yeni şans” için ikinci kutu kapalı kalır.</p></td></tr>';
        echo '</table>'; submit_button('Çekilişi Oluştur ve Yorumları Getir'); echo '</form>'; $this->card_end();
    }

    private function render_settings() {
        $username=(string)get_option(self::OPTION_USERNAME,'madagaskarsirkiturkiye');
        $connected_id=(string)get_option(self::OPTION_CONNECTED_ID,'');
        $has_token=$this->get_token()!=='';
        $this->card_start();
        echo '<h2>Instagram API Bağlantısı</h2>';
        echo '<p>Sistem yalnızca sizin profesyonel Instagram hesabınızın kendi gönderilerindeki yorumları okur. Token tarayıcıya gösterilmez.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mck2_save_settings">'; wp_nonce_field('mck2_save_settings');
        echo '<table class="form-table"><tr><th>Instagram hesabı</th><td><input class="regular-text" name="ig_username" value="'.esc_attr($username).'"></td></tr>';
        echo '<tr><th>Access Token</th><td><input type="password" autocomplete="off" class="large-text" name="access_token" value=""><p class="description">'.($has_token?'Token kayıtlı. Değiştirmeyecekseniz boş bırakın.':'Henüz token kayıtlı değil.').'</p>';
        if ($has_token) echo '<label><input type="checkbox" name="clear_token" value="1"> Kayıtlı tokenı sil</label>';
        echo '</td></tr></table>'; submit_button('Ayarları Kaydet'); echo '</form>';
        if ($has_token) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:12px"><input type="hidden" name="action" value="mck2_test_connection">'; wp_nonce_field('mck2_test_connection'); submit_button('Instagram Bağlantısını Test Et','secondary','submit',false); echo '</form>';
        }
        if ($connected_id) echo '<p><strong>Bağlı hesap ID:</strong> '.esc_html($connected_id).'</p>';
        if (sanitize_key($_GET['mck_notice'] ?? '')==='connection_ok') {
            $d=get_transient('mck2_conn_'.get_current_user_id()); delete_transient('mck2_conn_'.get_current_user_id());
            if(is_array($d)) echo '<p><strong>Doğrulanan hesap:</strong> @'.esc_html($d['username'] ?? '').' &nbsp; <strong>Tür:</strong> '.esc_html($d['account_type'] ?? 'Professional').'</p>';
        }
        echo '<hr><h3>Gerekli Meta izinleri</h3><p><code>instagram_business_basic</code> ve <code>instagram_business_manage_comments</code>. Hesap Business veya Creator olmalıdır.</p>';
        echo '<p><strong>Teşhis:</strong> Gönderinin yorum sayısı okunup yorum listesi boş gelirse çekiliş ekranındaki <strong>API Teşhis Testi</strong> ile farklı yorum çağrılarını güvenli biçimde karşılaştırın.</p>';
        echo '<p class="description">Eklenti sürümü: '.esc_html(self::VERSION).'</p>';
        $this->card_end();
    }

    private function render_campaign_detail($id) {
        global $wpdb;
        $c=$this->get_campaign($id);
        if(!$c){echo '<p>Çekiliş bulunamadı.</p>';return;}
        echo '<p><a href="'.esc_url(add_query_arg(['page'=>'madagaskar-cekilis'],admin_url('admin.php'))).'">← Çekiliş listesine dön</a></p>';
        $this->card_start();
        $report=MCK_Reporting::campaign($id);
        if ($report) MCK_Reporting::render_campaign_metrics($report);
        echo '<h2>'.esc_html($c->title).'</h2><p><a href="'.esc_url($c->post_url).'" target="_blank" rel="noopener">Instagram gönderisini aç ↗</a></p>';
        echo '<p><strong>Toplam yorum:</strong> '.intval($c->total_comments).' &nbsp; | &nbsp; <strong>Geçerli hak:</strong> '.intval($c->valid_entries).' &nbsp; | &nbsp; <strong>Geçersiz:</strong> '.intval($c->invalid_comments).' &nbsp; | &nbsp; <strong>Durum:</strong> '.esc_html($c->status).'</p>';
        echo '<p><strong>Kural:</strong> En az '.intval($c->min_mentions).' farklı arkadaş etiketi. '.(intval($c->one_user_one_entry)?'Her kullanıcı 1 hak.':'Her farklı geçerli yorum 1 hak.').' '.(intval($c->dedupe_same_text)?'Birebir aynı yorum tekrarı sayılmaz.':'Aynı metin tekrarları da sayılır.').'</p>';
        if($c->status!=='drawn'){
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-right:8px"><input type="hidden" name="action" value="mck2_sync_campaign"><input type="hidden" name="campaign_id" value="'.intval($id).'">'; wp_nonce_field('mck2_sync_campaign_'.$id); submit_button('Yorumları Yenile','secondary','submit',false); echo '</form>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block"><input type="hidden" name="action" value="mck2_draw_campaign"><input type="hidden" name="campaign_id" value="'.intval($id).'">'; wp_nonce_field('mck2_draw_campaign_'.$id); submit_button('🎲 Çekilişi Yap','primary','submit',false); echo '</form>';
        }
        echo ' '.MCK_Reporting::export_link($id);
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-left:8px"><input type="hidden" name="action" value="mck2_diagnose_campaign"><input type="hidden" name="campaign_id" value="'.intval($id).'">'; wp_nonce_field('mck2_diagnose_campaign_'.$id); submit_button('🧪 API Teşhis Testi','secondary','submit',false); echo '</form>';
        echo '<p style="margin-top:14px"><em>Beğeni ve @'.esc_html(get_option(self::OPTION_USERNAME,'madagaskarsirkiturkiye')).' hesabını takip şartı, seçilen adaylarda Instagram uygulamasından son kontrolde doğrulanmalıdır.</em></p>';
        $diag = get_transient('mck2_diag_'.get_current_user_id().'_'.$id);
        if (is_array($diag)) {
            delete_transient('mck2_diag_'.get_current_user_id().'_'.$id);
            echo '<div style="margin-top:18px;padding:14px;border:1px solid #dcdcde;background:#f6f7f7"><h3 style="margin-top:0">API Teşhis Sonuçları</h3><p>Token gösterilmez; yalnızca HTTP durumu ve dönen veri yapısı özetlenir.</p><table class="widefat striped"><thead><tr><th>Test</th><th>HTTP</th><th>Veri</th><th>Anahtarlar / Hata</th></tr></thead><tbody>';
            foreach ($diag as $name=>$r) {
                $count = isset($r['comments_data_count']) ? 'comments: '.intval($r['comments_data_count']) : (isset($r['data_count']) && $r['data_count'] !== null ? 'data: '.intval($r['data_count']) : '—');
                $keys = [];
                if (!empty($r['top_keys'])) $keys[]='üst: '.implode(', ', array_map('sanitize_text_field',$r['top_keys']));
                if (!empty($r['first_item_keys'])) $keys[]='ilk kayıt: '.implode(', ', array_map('sanitize_text_field',$r['first_item_keys']));
                if (!empty($r['comments_first_item_keys'])) $keys[]='ilk yorum: '.implode(', ', array_map('sanitize_text_field',$r['comments_first_item_keys']));
                if (!empty($r['error'])) $keys[]='hata: '.sanitize_text_field($r['error']);
                echo '<tr><td>'.esc_html($name).'</td><td>'.intval($r['http'] ?? 0).'</td><td>'.esc_html($count).'</td><td>'.esc_html(implode(' | ',$keys)).'</td></tr>';
            }
            echo '</tbody></table><p><strong>Bu tablonun ekran görüntüsünü paylaşabilirsiniz;</strong> access token bu tabloda yer almaz.</p></div>';
        }
        $this->card_end();

        if($c->status==='drawn') $this->render_results($c);
        MCK_Reporting::render_audit($id);
        $this->render_comment_tables($c);

        $this->card_start();
        echo '<h3>Tehlikeli Alan</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Bu çekiliş ve tüm kayıtları silinsin mi?\')"><input type="hidden" name="action" value="mck2_delete_campaign"><input type="hidden" name="campaign_id" value="'.intval($id).'">'; wp_nonce_field('mck2_delete_campaign_'.$id); submit_button('Çekilişi Sil','delete','submit',false); echo '</form>';
        $this->card_end();
    }

    private function render_results($c) {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->draws_table} WHERE campaign_id=%d ORDER BY CASE WHEN result_type='winner' THEN 0 ELSE 1 END, position_no ASC",$c->id));
        $this->card_start();
        echo '<h2>🏆 Çekiliş Sonucu</h2><p>Doğrulama zamanı ve ilk seçimi yapan admin mevcut şemada kayıtlı değildir. Replacement admin/nedeni audit kaydından okunur.</p>';
        $replacement_meta=MCK_Reporting::result_metadata($c->id);
        foreach(['winner'=>'Asil','reserve'=>'Yedek'] as $type=>$label){
            echo '<h3>'.$label.'</h3><table class="widefat striped"><thead><tr><th>Sıra</th><th>Kullanıcı</th><th>Yorum referansı</th><th>Seçim / seçilme zamanı</th><th>Doğrulama zamanı</th><th>Replacement nedeni / admin</th><th>Takip / Beğeni Kontrolü</th></tr></thead><tbody>';
            foreach($rows as $r){if($r->result_type!==$type)continue;
                $replacement=$replacement_meta[(int)$r->id] ?? null;
                $reason=$replacement ? MCK_Reporting::verification_label($replacement['old_verification_status']).($replacement['old_verification_note']!=='' ? ' · '.$replacement['old_verification_note'] : '') : '—';
                $actor=$replacement ? ($replacement['actor_name'] ?: 'Admin #'.(int)$replacement['actor_user_id']) : 'Kayıt yok';
                echo '<tr><td>'.intval($r->position_no).'</td><td><strong>@'.esc_html($r->username).'</strong></td><td><code>'.esc_html($r->comment_id).'</code></td><td>'.($replacement?'Replacement':'İlk seçim').'<br>'.esc_html($r->drawn_at).'</td><td>Kayıt yok</td><td>'.esc_html($reason).'<br>'.esc_html($actor).'</td><td>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mck2_verify_result"><input type="hidden" name="draw_id" value="'.intval($r->id).'"><input type="hidden" name="campaign_id" value="'.intval($c->id).'">'; wp_nonce_field('mck2_verify_result_'.$r->id);
                echo '<input type="hidden" name="expected_result" value="'.esc_attr($this->result_snapshot($r)).'">';
                echo '<select name="verification_status"><option value="pending" '.selected($r->verification_status,'pending',false).'>Bekliyor</option><option value="verified" '.selected($r->verification_status,'verified',false).'>Doğrulandı</option><option value="disqualified" '.selected($r->verification_status,'disqualified',false).'>Şartı sağlamadı</option></select> ';
                echo '<input name="verification_note" value="'.esc_attr($r->verification_note).'" placeholder="Not" style="width:180px"> <button class="button">Kaydet</button></form>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p style="margin-top:15px"><strong>Çekiliş zamanı:</strong> '.esc_html($c->drawn_at).'</p>';
        echo '<p><strong>Denetim izi / Eligible Hash:</strong><br><code style="word-break:break-all">'.esc_html($c->eligible_hash).'</code></p>';
        echo '<details><summary>Tekrarlanabilir seçim tohumu (audit seed)</summary><code style="word-break:break-all">'.esc_html($c->audit_seed).'</code><p>Bu değer ve dondurulmuş geçerli yorum listesi kullanılarak sıralama teknik olarak yeniden hesaplanabilir.</p></details>';
        $code = $this->verification_code($c);
        $verify_url = $this->verification_url($code);
        $winners = $this->winner_rows($c->id);
        $published = $this->public_results_published($c, $winners);
        $ready = $this->publication_ready($c, $winners);
        echo '<div style="margin-top:18px;padding:14px;border:1px solid #dcdcde;border-radius:10px"><h3>Sonuç Yayını</h3><p><strong>'.($published ? 'Yayında' : 'Taslak — henüz yayımlanmadı').'</strong></p>';
        echo '<p>Asil adayların takip/beğeni kontrolünü Instagram üzerinden yapıp her birini Doğrulandı olarak kaydedin. Ardından sonuçları yayımlayın. Aday değişirse yayın yeniden onay gerektirir.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mck2_publish_results"><input type="hidden" name="campaign_id" value="'.intval($c->id).'"><input type="hidden" name="expected_results" value="'.esc_attr($this->publication_fingerprint($c, $winners)).'">';
        wp_nonce_field('mck2_publish_results_'.$c->id);
        echo '<button class="button button-primary"'.(!$ready || $published ? ' disabled' : '').'>Çekiliş sonuçlarını yayınla</button></form>';
        if (!$ready) echo '<p>Tüm asil adaylar doğrulanmadan yayın yapılamaz.</p>';
        if ($published) {
            $publication = get_option('mck2_publication_'.intval($c->id), []);
            echo '<p>Yayın zamanı: '.esc_html($publication['published_at'] ?? '').'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mck2_unpublish_results"><input type="hidden" name="campaign_id" value="'.intval($c->id).'">';
            wp_nonce_field('mck2_unpublish_results_'.$c->id);
            echo '<button class="button">Yayından kaldır</button></form>';
        }
        echo '</div>';
        $story_url = wp_nonce_url(admin_url('admin-post.php?action=mck2_story_result&campaign_id='.intval($c->id)), 'mck2_story_result_'.intval($c->id));
        echo '<div style="margin-top:18px;padding:14px;border:1px solid #e5e7eb;background:#f9fafb;border-radius:10px"><strong>Paylaşım / Doğrulama</strong><p style="margin:8px 0">Sonuç kodu: <code style="font-size:18px;letter-spacing:2px">'.esc_html($code).'</code></p><a class="button button-primary" href="'.esc_url($story_url).'" target="_blank">📱 Story Sonuç Görseli</a> <a class="button" href="'.esc_url($verify_url).'" target="_blank">🔎 Kamuya Açık Sonuç Doğrulama</a> <a class="button" href="'.esc_url($this->public_results_url()).'" target="_blank">🌐 Çekiliş Sonuçları Sayfası</a></div>';
        $this->card_end();
    }

    private function render_comment_tables($c) {
        global $wpdb;
        $valid=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->comments_table} WHERE campaign_id=%d AND is_valid=1 ORDER BY id ASC LIMIT 5000",$c->id));
        $invalid=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->comments_table} WHERE campaign_id=%d AND is_valid=0 ORDER BY id ASC LIMIT 5000",$c->id));
        $this->card_start();
        echo '<h2>Katılım Listesi</h2><details open><summary><strong>Geçerli yorumlar / haklar ('.count($valid).')</strong></summary><table class="widefat striped" style="margin-top:10px"><thead><tr><th>#</th><th>Kullanıcı</th><th>Yorum</th><th>Etiketler</th><th>Tarih</th></tr></thead><tbody>';
        foreach($valid as $i=>$r){$m=json_decode($r->mentions,true);if(!is_array($m))$m=[];echo '<tr><td>'.($i+1).'</td><td>@'.esc_html($r->username).'</td><td>'.esc_html($r->comment_text).'</td><td>'.esc_html(implode(', ',array_map(function($x){return '@'.$x;},$m))).'</td><td>'.esc_html($r->comment_timestamp).'</td></tr>';}
        echo '</tbody></table></details>';
        echo '<details style="margin-top:14px"><summary><strong>Geçersiz yorumlar ('.count($invalid).')</strong></summary><table class="widefat striped" style="margin-top:10px"><thead><tr><th>Kullanıcı</th><th>Yorum</th><th>Neden</th></tr></thead><tbody>';
        foreach($invalid as $r) echo '<tr><td>@'.esc_html($r->username).'</td><td>'.esc_html($r->comment_text).'</td><td>'.esc_html($r->invalid_reason).'</td></tr>';
        echo '</tbody></table></details>'; $this->card_end();
    }
}

register_activation_hook(__FILE__, ['Madagaskar_Cekilis_V2','activate']);
new Madagaskar_Cekilis_V2();
