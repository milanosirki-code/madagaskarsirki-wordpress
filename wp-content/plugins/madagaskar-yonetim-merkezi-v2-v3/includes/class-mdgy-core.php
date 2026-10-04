<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDGY_Core {
    const CAP = 'manage_woocommerce';
    const PARENT = 'mdg-dashboard';
    const OPT_SETTINGS = 'mdgy_settings_v1';
    const OPT_META_TOKEN = 'mdgy_secret_meta_token_v1';
    const OPT_GA4_JSON = 'mdgy_secret_ga4_json_v1';
    const OPT_KOMMO_TOKEN = 'mdgy_secret_kommo_token_v1';
    const OPT_WEBHOOK_KEY = 'mdgy_kommo_webhook_key_v1';
    const CRON_HOOK = 'mdgy_hourly_sync_v1';
    const META_TEST_TRANSIENT = 'mdgy_meta_test_v1_';

    private static $instance = null;

    public static function boot() {
        if ( (string) get_option( 'mdgy_db_version', '' ) !== (string) MDGY_VERSION ) {
            self::create_tables();
        }
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
        }
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        self::create_tables();
        if ( ! get_option( self::OPT_WEBHOOK_KEY ) ) {
            update_option( self::OPT_WEBHOOK_KEY, wp_generate_password( 40, false, false ), false );
        }
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    private function __construct() {
        // V5 ana menusu 9000 onceliginde kurulur. Biraz sonra ekleyerek
        // Pazarlama/CRM ekranlarini ayni Madagaskar menusu altinda tutuyoruz.
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 9050 );
        add_action( 'admin_post_mdgy_save_integrations', array( $this, 'handle_save_integrations' ) );
        add_action( 'admin_post_mdgy_manual_sync', array( $this, 'handle_manual_sync' ) );
        add_action( self::CRON_HOOK, array( $this, 'cron_sync' ) );

        add_action( 'rest_api_init', array( $this, 'register_rest' ) );

        // UTM / click-id capture for reklam -> satış zinciri.
        add_action( 'template_redirect', array( $this, 'capture_attribution' ), 1 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_attribution_to_order' ), 20, 2 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'attach_store_api_attribution' ), 20, 2 );
        add_action( 'woocommerce_payment_complete', array( $this, 'mirror_order_attribution' ), 30 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'mirror_order_attribution' ), 30 );
        add_action( 'woocommerce_order_status_completed', array( $this, 'mirror_order_attribution' ), 30 );

        // GA4 temel etiketi ve e-ticaret olaylari.
        add_action( 'wp_head', array( $this, 'render_ga4_base_tag' ), 2 );
        add_action( 'wp_footer', array( $this, 'render_ga4_ecommerce_events' ), 99 );
    }

    /* ======================================================
     * DB
     * ==================================================== */

    private static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'mdgy_' . $name;
    }

    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $meta = self::table( 'meta_daily' );
        $ig   = self::table( 'instagram_daily' );
        $ga4  = self::table( 'ga4_daily' );
        $leads = self::table( 'kommo_leads' );
        $events = self::table( 'crm_events' );
        $attr = self::table( 'attribution' );
        $log  = self::table( 'sync_log' );

        dbDelta( "CREATE TABLE {$meta} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            row_key CHAR(64) NOT NULL,
            metric_date DATE NOT NULL,
            level_name VARCHAR(24) NOT NULL DEFAULT 'campaign',
            account_id VARCHAR(100) NOT NULL DEFAULT '',
            campaign_id VARCHAR(100) NOT NULL DEFAULT '',
            campaign_name VARCHAR(255) NOT NULL DEFAULT '',
            adset_id VARCHAR(100) NOT NULL DEFAULT '',
            adset_name VARCHAR(255) NOT NULL DEFAULT '',
            ad_id VARCHAR(100) NOT NULL DEFAULT '',
            ad_name VARCHAR(255) NOT NULL DEFAULT '',
            spend DECIMAL(18,4) NOT NULL DEFAULT 0,
            impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reach BIGINT UNSIGNED NOT NULL DEFAULT 0,
            clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
            link_clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ctr DECIMAL(14,6) NOT NULL DEFAULT 0,
            cpc DECIMAL(18,6) NOT NULL DEFAULT 0,
            purchases DECIMAL(18,4) NOT NULL DEFAULT 0,
            purchase_value DECIMAL(18,4) NOT NULL DEFAULT 0,
            raw_json LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY row_key (row_key),
            KEY metric_date (metric_date),
            KEY campaign_id (campaign_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$ig} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_date DATE NOT NULL,
            ig_user_id VARCHAR(100) NOT NULL,
            username VARCHAR(190) NOT NULL DEFAULT '',
            followers_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            media_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            posts_seen BIGINT UNSIGNED NOT NULL DEFAULT 0,
            likes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            comments BIGINT UNSIGNED NOT NULL DEFAULT 0,
            raw_json LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY day_user (metric_date,ig_user_id),
            KEY metric_date (metric_date)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$ga4} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            row_key CHAR(64) NOT NULL,
            metric_date DATE NOT NULL,
            source_name VARCHAR(190) NOT NULL DEFAULT '',
            medium_name VARCHAR(190) NOT NULL DEFAULT '',
            campaign_name VARCHAR(255) NOT NULL DEFAULT '',
            sessions BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_users BIGINT UNSIGNED NOT NULL DEFAULT 0,
            new_users BIGINT UNSIGNED NOT NULL DEFAULT 0,
            page_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            add_to_carts BIGINT UNSIGNED NOT NULL DEFAULT 0,
            purchases DECIMAL(18,4) NOT NULL DEFAULT 0,
            purchase_revenue DECIMAL(18,4) NOT NULL DEFAULT 0,
            raw_json LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY row_key (row_key),
            KEY metric_date (metric_date),
            KEY source_name (source_name)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$leads} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NOT NULL,
            pipeline_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            price DECIMAL(18,2) NOT NULL DEFAULT 0,
            source_name VARCHAR(190) NOT NULL DEFAULT '',
            order_id BIGINT UNSIGNED NULL,
            created_ts BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_ts BIGINT UNSIGNED NOT NULL DEFAULT 0,
            closed_ts BIGINT UNSIGNED NOT NULL DEFAULT 0,
            synced_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY lead_id (lead_id),
            KEY pipeline_status (pipeline_id,status_id),
            KEY created_ts (created_ts),
            KEY order_id (order_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) NOT NULL DEFAULT '',
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            lead_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            pipeline_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            occurred_at DATETIME NOT NULL,
            payload_hash CHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY occurred_at (occurred_at),
            KEY lead_id (lead_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$attr} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NULL,
            kommo_lead_id BIGINT UNSIGNED NULL,
            source_name VARCHAR(190) NOT NULL DEFAULT '',
            medium_name VARCHAR(190) NOT NULL DEFAULT '',
            campaign_name VARCHAR(255) NOT NULL DEFAULT '',
            campaign_id VARCHAR(190) NOT NULL DEFAULT '',
            content_name VARCHAR(255) NOT NULL DEFAULT '',
            term_name VARCHAR(255) NOT NULL DEFAULT '',
            fbclid VARCHAR(255) NOT NULL DEFAULT '',
            gclid VARCHAR(255) NOT NULL DEFAULT '',
            landing_url TEXT NULL,
            order_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            paid_at DATETIME NULL,
            first_touch_json LONGTEXT NULL,
            last_touch_json LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY paid_at (paid_at),
            KEY source_name (source_name),
            KEY campaign_name (campaign_name),
            KEY kommo_lead_id (kommo_lead_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$log} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_name VARCHAR(40) NOT NULL,
            status_name VARCHAR(20) NOT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NOT NULL,
            rows_count INT UNSIGNED NOT NULL DEFAULT 0,
            message TEXT NULL,
            PRIMARY KEY (id),
            KEY source_name (source_name),
            KEY started_at (started_at)
        ) {$charset};" );

        update_option( 'mdgy_db_version', MDGY_VERSION, false );
    }

    /* ======================================================
     * Crypto / settings
     * ==================================================== */

    private static function crypto_key() {
        $seed = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'mdgy-auth' ) . '|' . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'mdgy-secure' );
        return hash( 'sha256', $seed, true );
    }

    private static function encrypt( $plain ) {
        $plain = (string) $plain;
        if ( '' === $plain ) { return ''; }
        if ( ! function_exists( 'openssl_encrypt' ) ) { return ''; }
        $iv = random_bytes( 16 );
        $cipher = openssl_encrypt( $plain, 'AES-256-CBC', self::crypto_key(), OPENSSL_RAW_DATA, $iv );
        if ( false === $cipher ) { return ''; }
        return 'enc1:' . base64_encode( $iv . $cipher );
    }

    private static function decrypt( $stored ) {
        $stored = (string) $stored;
        if ( 0 !== strpos( $stored, 'enc1:' ) ) { return ''; }
        $raw = base64_decode( substr( $stored, 5 ), true );
        if ( false === $raw || strlen( $raw ) <= 16 ) { return ''; }
        $iv = substr( $raw, 0, 16 );
        $cipher = substr( $raw, 16 );
        $plain = openssl_decrypt( $cipher, 'AES-256-CBC', self::crypto_key(), OPENSSL_RAW_DATA, $iv );
        return false === $plain ? '' : (string) $plain;
    }

    private static function settings() {
        $defaults = array(
            'meta_api_version' => 'v26.0',
            'meta_ad_account_id' => '',
            'meta_ig_user_id' => '',
            'ga4_property_id' => '',
            'ga4_measurement_id' => 'G-W7V5B4WKL3',
            'kommo_subdomain' => '',
            'kommo_order_field_id' => '',
            'sync_days' => 30,
        );
        return wp_parse_args( (array) get_option( self::OPT_SETTINGS, array() ), $defaults );
    }

    private static function secret( $which ) {
        $map = array(
            'meta' => self::OPT_META_TOKEN,
            'ga4' => self::OPT_GA4_JSON,
            'kommo' => self::OPT_KOMMO_TOKEN,
        );
        if ( empty( $map[ $which ] ) ) { return ''; }
        return self::decrypt( get_option( $map[ $which ], '' ) );
    }

    private function require_cap() {
        if ( ! current_user_can( self::CAP ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Bu işlemi yapma yetkiniz yok.' );
        }
    }

    /* ======================================================
     * Admin menu/pages
     * ==================================================== */

    public function admin_menu() {
        add_submenu_page( self::PARENT, 'V2 – Pazarlama', 'V2 – Pazarlama', self::CAP, 'mdgy-marketing', array( $this, 'render_marketing' ), 40 );
        add_submenu_page( self::PARENT, 'V3 – CRM', 'V3 – CRM', self::CAP, 'mdgy-crm', array( $this, 'render_crm' ), 50 );
        add_submenu_page( self::PARENT, 'Yönetim Merkezi Entegrasyonları', 'Entegrasyonlar', self::CAP, 'mdgy-integrations', array( $this, 'render_integrations' ), 60 );
    }

    private function styles() {
        echo '<style>
        .mdgy{max-width:1500px}.mdgy-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:18px 0}.mdgy-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px}.mdgy-card h2,.mdgy-card h3{margin-top:0}.mdgy-kpi{font-size:30px;font-weight:750;line-height:1.1}.mdgy-sub{color:#646970;font-size:12px;margin-top:6px}.mdgy-ok{color:#16803a;font-weight:700}.mdgy-bad{color:#b42318;font-weight:700}.mdgy-warn{color:#996800;font-weight:700}.mdgy-note{border-left:4px solid #2271b1;background:#f0f6fc;padding:13px 15px;margin:14px 0}.mdgy-safe{border-left:4px solid #16803a;background:#f2fbf5;padding:13px 15px;margin:14px 0}.mdgy-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;margin-top:18px}.mdgy-table td,.mdgy-table th{vertical-align:top}.mdgy-form-grid{display:grid;grid-template-columns:220px minmax(320px,720px);gap:12px 18px;align-items:center}.mdgy-form-grid input[type=text],.mdgy-form-grid input[type=password],.mdgy-form-grid input[type=number],.mdgy-form-grid textarea{width:100%}.mdgy-tag{display:inline-block;padding:3px 8px;border-radius:999px;background:#f0f0f1;font-size:12px}.mdgy-actions{display:flex;gap:8px;flex-wrap:wrap}.mdgy-code{font-family:monospace;background:#f6f7f7;padding:8px 10px;border:1px solid #dcdcde;border-radius:6px;word-break:break-all}.mdgy-result{border:1px solid #c3c4c7;border-radius:8px;padding:12px;margin:10px 0;background:#fff}.mdgy-result ul{margin-bottom:0}
        @media(max-width:782px){.mdgy-form-grid{grid-template-columns:1fr;gap:5px}.mdgy-form-grid>div:nth-child(2n){margin-bottom:12px}.mdgy-panel{padding:14px}.mdgy-actions .button{width:100%;text-align:center}}
        </style>';
    }

    private static function date_range_from_request( $default_days = 30 ) {
        $tz = wp_timezone();
        $today = new DateTimeImmutable( 'today', $tz );
        $from = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) );
        $to   = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) { $to = $today->format( 'Y-m-d' ); }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) { $from = $today->modify( '-' . max( 0, (int) $default_days - 1 ) . ' days' )->format( 'Y-m-d' ); }
        return array( $from, $to );
    }

    private function filter_form( $page, $from, $to ) {
        echo '<form method="get" class="mdgy-panel" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">';
        echo '<input type="hidden" name="page" value="' . esc_attr( $page ) . '">';
        echo '<div><label><strong>Başlangıç</strong></label><br><input type="date" name="from" value="' . esc_attr( $from ) . '"></div>';
        echo '<div><label><strong>Bitiş</strong></label><br><input type="date" name="to" value="' . esc_attr( $to ) . '"></div>';
        echo '<div><button class="button button-primary">Göster</button></div>';
        echo '</form>';
    }

    public function render_center() {
        $this->require_cap(); $this->styles();
        list( $from, $to ) = self::date_range_from_request( 30 );
        $m = $this->marketing_summary( $from, $to );
        $c = $this->crm_summary( $from, $to );
        $s = $this->sales_summary( $from, $to );
        $a = $this->attribution_summary( $from, $to );

        echo '<div class="wrap mdgy"><h1>🎪 Madagaskar Yönetim Merkezi</h1>';
        echo '<p>Satış, reklam, web trafiği, Kommo ve WhatsApp verilerini tek ekranda birleştiren salt-okunur yönetim görünümü.</p>';
        $this->filter_form( 'mdgy-center', $from, $to );

        echo '<div class="mdgy-grid">';
        $this->kpi( 'Bilet Cirosu', self::money( $s['revenue'] ), number_format_i18n( $s['tickets'] ) . ' bilet' );
        $this->kpi( 'Meta Harcama', self::money( $m['meta_spend'] ), number_format_i18n( $m['meta_clicks'] ) . ' tıklama' );
        $roas = $m['meta_spend'] > 0 ? $a['meta_revenue'] / $m['meta_spend'] : 0;
        $this->kpi( 'Atfedilen Meta ROAS', $roas ? number_format_i18n( $roas, 2 ) . 'x' : '—', self::money( $a['meta_revenue'] ) . ' atfedilen gelir' );
        $this->kpi( 'GA4 Oturum', number_format_i18n( $m['ga4_sessions'] ), number_format_i18n( $m['ga4_purchases'] ) . ' satın alma' );
        $this->kpi( 'Kommo Yeni Lead', number_format_i18n( $c['new_leads'] ), number_format_i18n( $c['won_leads'] ) . ' kazanılan' );
        $this->kpi( 'WhatsApp', number_format_i18n( $c['incoming_messages'] ) . ' gelen', number_format_i18n( $c['outgoing_messages'] ) . ' giden webhook' );
        $this->kpi( 'Instagram Takipçi', number_format_i18n( $m['ig_followers'] ), 'son senkron' );
        $this->kpi( 'Reklamdan Sipariş', number_format_i18n( $a['meta_orders'] ), 'UTM / fbclid ile atıf' );
        echo '</div>';

        echo '<div class="mdgy-grid">';
        echo '<div class="mdgy-card"><h2>V2 – Pazarlama</h2><p>Meta Ads + Instagram + GA4. Kampanya harcaması, trafik, tıklama ve satın alma metrikleri.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdgy-marketing' ) ) . '">Pazarlama Panelini Aç</a></p></div>';
        echo '<div class="mdgy-card"><h2>V3 – CRM</h2><p>Kommo lead akışı, WhatsApp webhookları ve WooCommerce sipariş atıfları.</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=mdgy-crm' ) ) . '">CRM Panelini Aç</a></p></div>';
        echo '<div class="mdgy-card"><h2>Entegrasyon Durumu</h2>' . $this->connection_status_html() . '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=mdgy-integrations' ) ) . '">Bağlantıları Yönet</a></p></div>';
        echo '</div>';

        echo '<div class="mdgy-note"><strong>Atıf kuralı:</strong> Reklam → satış eşlemesi bundan sonraki ziyaretlerde UTM parametreleri, <code>fbclid</code>/<code>gclid</code> ve WooCommerce sipariş metalarıyla tutulur. Eski siparişler geriye dönük olarak tahmin edilmez.</div>';
        echo '</div>';
    }

    public function render_marketing() {
        $this->require_cap(); $this->styles();
        list( $from, $to ) = self::date_range_from_request( 30 );
        $m = $this->marketing_summary( $from, $to );
        echo '<div class="wrap mdgy"><h1>📣 V2 – Pazarlama Merkezi</h1>';
        echo '<p>Meta Ads, Instagram ve Google Analytics 4 verilerini aynı tarih aralığında karşılaştırın.</p>';
        $this->filter_form( 'mdgy-marketing', $from, $to );
        echo '<div class="mdgy-grid">';
        $this->kpi( 'Meta Harcama', self::money( $m['meta_spend'] ), '' );
        $this->kpi( 'Meta Gösterim', number_format_i18n( $m['meta_impressions'] ), '' );
        $this->kpi( 'Meta Erişim', number_format_i18n( $m['meta_reach'] ), '' );
        $this->kpi( 'Meta Tıklama', number_format_i18n( $m['meta_clicks'] ), '' );
        $this->kpi( 'GA4 Oturum', number_format_i18n( $m['ga4_sessions'] ), '' );
        $this->kpi( 'GA4 Satın Alma', number_format_i18n( $m['ga4_purchases'] ), self::money( $m['ga4_revenue'] ) );
        $this->kpi( 'Instagram Takipçi', number_format_i18n( $m['ig_followers'] ), '' );
        echo '</div>';

        $this->render_sync_buttons( array( 'meta', 'instagram', 'ga4' ) );
        $this->render_meta_campaigns( $from, $to );
        $this->render_ga4_sources( $from, $to );
        $this->render_instagram_panel();

        echo '<div class="mdgy-panel"><h2>Meta reklam URL standardı</h2><p>Reklam → satış zincirinin kampanya seviyesinde bağlanabilmesi için Meta reklam URL parametrelerinde şu standardı kullanın:</p>';
        echo '<div class="mdgy-code">utm_source=meta&amp;utm_medium=paid_social&amp;utm_campaign={{campaign.name}}&amp;utm_id={{campaign.id}}&amp;utm_content={{ad.name}}&amp;utm_term={{adset.name}}</div>';
        echo '<p class="mdgy-sub">Panel bu parametreleri ilk ve son temas olarak saklar; WooCommerce siparişine aktarır ve Meta kampanya harcamasıyla eşleştirir.</p></div>';
        echo '</div>';
    }

    public function render_crm() {
        $this->require_cap(); $this->styles();
        list( $from, $to ) = self::date_range_from_request( 30 );
        $c = $this->crm_summary( $from, $to );
        $a = $this->attribution_summary( $from, $to );
        echo '<div class="wrap mdgy"><h1>💬 V3 – CRM Merkezi</h1>';
        echo '<p>Kommo + WhatsApp + reklam → müşteri → sipariş dönüşüm zinciri.</p>';
        $this->filter_form( 'mdgy-crm', $from, $to );
        echo '<div class="mdgy-grid">';
        $this->kpi( 'Yeni Lead', number_format_i18n( $c['new_leads'] ), '' );
        $this->kpi( 'Kazanılan Lead', number_format_i18n( $c['won_leads'] ), '' );
        $this->kpi( 'Kaybedilen Lead', number_format_i18n( $c['lost_leads'] ), '' );
        $this->kpi( 'WhatsApp Gelen', number_format_i18n( $c['incoming_messages'] ), '' );
        $this->kpi( 'WhatsApp Giden', number_format_i18n( $c['outgoing_messages'] ), 'Kommo webhook sayımı' );
        $this->kpi( 'Atfedilen Sipariş', number_format_i18n( $a['orders'] ), self::money( $a['revenue'] ) );
        echo '</div>';
        $this->render_sync_buttons( array( 'kommo' ) );
        $this->render_kommo_status_table( $from, $to );
        $this->render_attribution_table( $from, $to );

        $url = $this->kommo_webhook_url();
        echo '<div class="mdgy-panel"><h2>Kommo WhatsApp Webhook</h2><p>Kommo → Ayarlar → Entegrasyonlar → Web hooks bölümünde aşağıdaki adresi ekleyin:</p><div class="mdgy-code">' . esc_html( $url ) . '</div>';
        echo '<p><strong>İşaretlenecek olaylar:</strong> Lead added, Lead edited, Lead stage changed, Incoming message received, Outgoing message sent.</p>';
        echo '<p class="mdgy-sub">Webhook metin/telefon içeriğini rapor tablosuna kaydetmez; olay türü, entity/lead kimliği ve zaman bilgisi üzerinden sayım yapar.</p></div>';
        echo '</div>';
    }

    public function render_integrations() {
        $this->require_cap(); $this->styles();
        $s = self::settings();
        echo '<div class="wrap mdgy"><h1>🔌 Yönetim Merkezi Entegrasyonları</h1>';
        if ( ! empty( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Ayarlar kaydedildi.</p></div>'; }
        if ( ! empty( $_GET['sync'] ) ) { echo '<div class="notice notice-success"><p>Senkronizasyon çalıştırıldı. Ayrıntı için aşağıdaki son senkron kayıtlarına bakın.</p></div>'; }
        $this->render_meta_test_result();
        echo '<div class="mdgy-safe"><strong>Güvenlik:</strong> Meta, Google ve Kommo gizli anahtarları WordPress option tablosunda AES-256-CBC ile şifreli saklanır. Bu panel harici sistemlerde reklam/lead değiştirmez; yalnız raporlama için okur ve Kommo webhook olaylarını kabul eder.</div>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mdgy-panel">';
        echo '<input type="hidden" name="action" value="mdgy_save_integrations">';
        wp_nonce_field( 'mdgy_save_integrations' );
        echo '<h2>Meta Ads + Instagram</h2><div class="mdgy-form-grid">';
        $this->field( 'Graph API sürümü', '<input type="text" name="meta_api_version" value="' . esc_attr( $s['meta_api_version'] ) . '" placeholder="v26.0">' );
        $this->field( 'Meta Ad Account ID', '<input type="text" name="meta_ad_account_id" value="' . esc_attr( $s['meta_ad_account_id'] ) . '" placeholder="act_123456789 veya 123456789">' );
        $this->field( 'Instagram Business ID', '<input type="text" name="meta_ig_user_id" value="' . esc_attr( $s['meta_ig_user_id'] ) . '">' );
        $this->field( 'Meta Access Token', '<input type="password" name="meta_token" value="" autocomplete="new-password" placeholder="Değiştirmek için yeni token girin"><div class="mdgy-sub">Mevcut token: ' . ( self::secret( 'meta' ) ? '<span class="mdgy-ok">kayıtlı</span>' : '<span class="mdgy-bad">yok</span>' ) . '</div>' );
        echo '</div><hr><h2>Google Analytics 4</h2><div class="mdgy-form-grid">';
        $this->field( 'GA4 Property ID', '<input type="text" name="ga4_property_id" value="' . esc_attr( $s['ga4_property_id'] ) . '" placeholder="123456789">' );
        $this->field( 'GA4 Ölçüm Kimliği', '<input type="text" name="ga4_measurement_id" value="' . esc_attr( $s['ga4_measurement_id'] ) . '" placeholder="G-XXXXXXXXXX"><div class="mdgy-sub">Ziyaret ve e-ticaret olaylarını GA4 web akışına gönderir.</div>' );
        $this->field( 'Service Account JSON', '<textarea rows="5" name="ga4_json" placeholder="Değiştirmek için yeni JSON yapıştırın"></textarea><div class="mdgy-sub">Mevcut anahtar: ' . ( self::secret( 'ga4' ) ? '<span class="mdgy-ok">kayıtlı</span>' : '<span class="mdgy-bad">yok</span>' ) . '</div>' );
        echo '</div><hr><h2>Kommo + WhatsApp</h2><div class="mdgy-form-grid">';
        $this->field( 'Kommo alt alan adı', '<input type="text" name="kommo_subdomain" value="' . esc_attr( $s['kommo_subdomain'] ) . '" placeholder="hesabiniz (hesabiniz.kommo.com)">' );
        $this->field( 'Long-lived token', '<input type="password" name="kommo_token" value="" autocomplete="new-password" placeholder="Değiştirmek için yeni token girin"><div class="mdgy-sub">Mevcut token: ' . ( self::secret( 'kommo' ) ? '<span class="mdgy-ok">kayıtlı</span>' : '<span class="mdgy-bad">yok</span>' ) . '</div>' );
        $this->field( 'Woo Sipariş No alan ID (opsiyonel)', '<input type="text" name="kommo_order_field_id" value="' . esc_attr( $s['kommo_order_field_id'] ) . '" placeholder="Kommo custom field ID"><div class="mdgy-sub">Varsa bu alan öncelikle kullanılır. Boşsa Kommo lead adı Order#2245 biçimindeyse sipariş numarası başlıktan otomatik çıkarılır.</div>' );
        $this->field( 'Varsayılan senkron gün sayısı', '<input type="number" min="1" max="90" name="sync_days" value="' . esc_attr( (int) $s['sync_days'] ) . '">' );
        echo '</div><p class="mdgy-actions"><button class="button button-primary">Ayarları Kaydet</button><button class="button" name="test_meta" value="1">Kaydet, Meta Bağlantısını Test Et ve Hesapları Bul</button></p>';
        echo '<p class="mdgy-sub">Meta testi için erişim anahtarında en az <code>ads_read</code> ve <code>read_insights</code>; Instagram hesabını bulmak için ayrıca <code>pages_show_list</code> ve <code>instagram_basic</code> izinleri gerekir.</p></form>';

        echo '<div class="mdgy-panel"><h2>Kommo webhook adresi</h2><div class="mdgy-code">' . esc_html( $this->kommo_webhook_url() ) . '</div></div>';
        $this->render_sync_buttons( array( 'meta', 'instagram', 'ga4', 'kommo' ) );
        $this->render_sync_log();
        echo '</div>';
    }

    private function field( $label, $html ) {
        echo '<div><strong>' . esc_html( $label ) . '</strong></div><div>' . $html . '</div>';
    }

    private function kpi( $label, $value, $sub ) {
        echo '<div class="mdgy-card"><div class="mdgy-kpi">' . esc_html( $value ) . '</div><div style="margin-top:8px"><strong>' . esc_html( $label ) . '</strong></div>';
        if ( $sub ) { echo '<div class="mdgy-sub">' . esc_html( $sub ) . '</div>'; }
        echo '</div>';
    }

    private static function money( $n ) {
        return number_format_i18n( (float) $n, 2 ) . ' TL';
    }

    private function connection_status_html() {
        $s = self::settings();
        $rows = array(
            'Meta Ads' => ( $s['meta_ad_account_id'] && self::secret( 'meta' ) ),
            'Instagram' => ( $s['meta_ig_user_id'] && self::secret( 'meta' ) ),
            'GA4' => ( $s['ga4_property_id'] && self::secret( 'ga4' ) ),
            'Kommo' => ( $s['kommo_subdomain'] && self::secret( 'kommo' ) ),
        );
        $html = '<ul>';
        foreach ( $rows as $name => $ok ) { $html .= '<li>' . esc_html( $name ) . ': ' . ( $ok ? '<span class="mdgy-ok">Hazır</span>' : '<span class="mdgy-warn">Bağlantı bekliyor</span>' ) . '</li>'; }
        return $html . '</ul>';
    }

    /* ======================================================
     * Settings handlers
     * ==================================================== */

    public function handle_save_integrations() {
        $this->require_cap(); check_admin_referer( 'mdgy_save_integrations' );
        $s = self::settings();
        $s['meta_api_version'] = preg_match( '/^v\d+\.\d+$/', sanitize_text_field( wp_unslash( $_POST['meta_api_version'] ?? '' ) ) ) ? sanitize_text_field( wp_unslash( $_POST['meta_api_version'] ) ) : 'v26.0';
        $s['meta_ad_account_id'] = preg_replace( '/[^0-9act_]/i', '', sanitize_text_field( wp_unslash( $_POST['meta_ad_account_id'] ?? '' ) ) );
        $s['meta_ig_user_id'] = preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['meta_ig_user_id'] ?? '' ) ) );
        $s['ga4_property_id'] = preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['ga4_property_id'] ?? '' ) ) );
        $measurement_id = strtoupper( sanitize_text_field( wp_unslash( $_POST['ga4_measurement_id'] ?? '' ) ) );
        $s['ga4_measurement_id'] = preg_match( '/^G-[A-Z0-9]+$/', $measurement_id ) ? $measurement_id : 'G-W7V5B4WKL3';
        $s['kommo_subdomain'] = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', sanitize_text_field( wp_unslash( $_POST['kommo_subdomain'] ?? '' ) ) ) );
        $s['kommo_order_field_id'] = preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['kommo_order_field_id'] ?? '' ) ) );
        $s['sync_days'] = min( 90, max( 1, absint( $_POST['sync_days'] ?? 30 ) ) );
        update_option( self::OPT_SETTINGS, $s, false );

        $meta = trim( (string) wp_unslash( $_POST['meta_token'] ?? '' ) );
        if ( '' !== $meta ) { update_option( self::OPT_META_TOKEN, self::encrypt( $meta ), false ); }
        $ga4 = trim( (string) wp_unslash( $_POST['ga4_json'] ?? '' ) );
        if ( '' !== $ga4 ) {
            $decoded = json_decode( $ga4, true );
            if ( is_array( $decoded ) && ! empty( $decoded['client_email'] ) && ! empty( $decoded['private_key'] ) ) {
                update_option( self::OPT_GA4_JSON, self::encrypt( wp_json_encode( $decoded ) ), false );
            }
        }
        $kommo = trim( (string) wp_unslash( $_POST['kommo_token'] ?? '' ) );
        if ( '' !== $kommo ) { update_option( self::OPT_KOMMO_TOKEN, self::encrypt( $kommo ), false ); }

        if ( ! empty( $_POST['test_meta'] ) ) {
            $result = $this->test_meta_connection();
            set_transient( self::META_TEST_TRANSIENT . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
            wp_safe_redirect( admin_url( 'admin.php?page=mdgy-integrations&meta_test=1' ) ); exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=mdgy-integrations&saved=1' ) ); exit;
    }

    private function test_meta_connection() {
        try {
            $profile = $this->meta_get( 'me', array( 'fields'=>'id,name' ) );
            $accounts = $this->meta_get( 'me/adaccounts', array( 'fields'=>'id,name,account_status,currency,timezone_name', 'limit'=>100 ) );
            $ads = array_values( (array) ( $accounts['data'] ?? array() ) );
            $igs = array();
            $ig_warning = '';
            try {
                $pages = $this->meta_get( 'me/accounts', array( 'fields'=>'id,name,instagram_business_account{id,username}', 'limit'=>100 ) );
            } catch ( Exception $e ) {
                $pages = array( 'data'=>array() );
                $ig_warning = sanitize_text_field( $e->getMessage() );
            }
            foreach ( (array) ( $pages['data'] ?? array() ) as $page ) {
                if ( empty( $page['instagram_business_account']['id'] ) ) { continue; }
                $igs[] = array(
                    'id'=>sanitize_text_field( $page['instagram_business_account']['id'] ),
                    'username'=>sanitize_text_field( $page['instagram_business_account']['username'] ?? '' ),
                    'page'=>sanitize_text_field( $page['name'] ?? '' ),
                );
            }
            $s = self::settings();
            if ( empty( $s['meta_ad_account_id'] ) && 1 === count( $ads ) ) { $s['meta_ad_account_id'] = sanitize_text_field( $ads[0]['id'] ?? '' ); }
            if ( empty( $s['meta_ig_user_id'] ) && 1 === count( $igs ) ) { $s['meta_ig_user_id'] = $igs[0]['id']; }
            update_option( self::OPT_SETTINGS, $s, false );
            return array( 'status'=>'ok', 'name'=>sanitize_text_field( $profile['name'] ?? '' ), 'ads'=>$ads, 'igs'=>$igs, 'ig_warning'=>$ig_warning );
        } catch ( Exception $e ) {
            return array( 'status'=>'error', 'message'=>sanitize_text_field( $e->getMessage() ) );
        }
    }

    private function render_meta_test_result() {
        if ( empty( $_GET['meta_test'] ) ) { return; }
        $key = self::META_TEST_TRANSIENT . get_current_user_id();
        $r = get_transient( $key ); delete_transient( $key );
        if ( ! is_array( $r ) ) { return; }
        if ( 'ok' !== ( $r['status'] ?? '' ) ) {
            echo '<div class="notice notice-error"><p><strong>Meta bağlantısı kurulamadı:</strong> ' . esc_html( $r['message'] ?? 'Bilinmeyen hata.' ) . '</p></div>'; return;
        }
        echo '<div class="notice notice-success"><p><strong>Meta bağlantısı başarılı.</strong> Kullanıcı: ' . esc_html( $r['name'] ?? '' ) . '</p></div>';
        echo '<div class="mdgy-panel"><h2>Bulunan Meta hesapları</h2>';
        echo '<div class="mdgy-result"><strong>Reklam hesapları</strong><ul>';
        if ( empty( $r['ads'] ) ) { echo '<li>Hesap bulunamadı. Token izinlerini ve işletme erişimini kontrol edin.</li>'; }
        foreach ( (array) ( $r['ads'] ?? array() ) as $a ) { echo '<li><strong>' . esc_html( $a['name'] ?? '' ) . '</strong> — ' . esc_html( $a['id'] ?? '' ) . ' · ' . esc_html( $a['currency'] ?? '' ) . ' · ' . esc_html( $a['timezone_name'] ?? '' ) . '</li>'; }
        echo '</ul></div><div class="mdgy-result"><strong>Instagram işletme hesapları</strong><ul>';
        if ( empty( $r['igs'] ) ) { echo '<li>Bağlı Instagram Business hesabı bulunamadı veya gerekli izinler eksik.' . ( ! empty( $r['ig_warning'] ) ? ' Ayrıntı: ' . esc_html( $r['ig_warning'] ) : '' ) . '</li>'; }
        foreach ( (array) ( $r['igs'] ?? array() ) as $ig ) { echo '<li><strong>@' . esc_html( $ig['username'] ?? '' ) . '</strong> — ID: ' . esc_html( $ig['id'] ?? '' ) . ' · Sayfa: ' . esc_html( $ig['page'] ?? '' ) . '</li>'; }
        echo '</ul></div><p class="mdgy-sub">Tek hesap bulunduysa kimliği otomatik kaydedilir. Birden fazla hesap varsa doğru ID’yi yukarıdaki ilgili alana yazıp ayarları kaydedin.</p></div>';
    }

    private function render_sync_buttons( $sources ) {
        echo '<div class="mdgy-panel"><h2>Manuel Senkronizasyon</h2><div class="mdgy-actions">';
        foreach ( $sources as $source ) {
            $labels = array( 'meta'=>'Meta Ads', 'instagram'=>'Instagram', 'ga4'=>'GA4', 'kommo'=>'Kommo' );
            $configured = $this->source_is_configured( $source );
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="mdgy_manual_sync"><input type="hidden" name="source" value="' . esc_attr( $source ) . '">';
            wp_nonce_field( 'mdgy_manual_sync_' . $source );
            echo '<button class="button"' . disabled( $configured, false, false ) . '>' . esc_html( $labels[ $source ] ?? $source ) . ( $configured ? ' Senkronize Et' : ' — Bilgi Eksik' ) . '</button></form>';
        }
        echo '</div><p class="mdgy-sub">Otomatik senkronizasyon saatlik çalışır ve son birkaç günü yeniden okuyarak geciken dönüşümleri tamamlar.</p></div>';
    }

    public function handle_manual_sync() {
        $this->require_cap();
        $source = sanitize_key( $_POST['source'] ?? '' );
        check_admin_referer( 'mdgy_manual_sync_' . $source );
        $s = self::settings(); $days = (int) $s['sync_days'];
        $to = wp_date( 'Y-m-d' ); $from = wp_date( 'Y-m-d', time() - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS );
        $this->sync_source( $source, $from, $to );
        wp_safe_redirect( admin_url( 'admin.php?page=mdgy-integrations&sync=1' ) ); exit;
    }

    public function cron_sync() {
        $to = wp_date( 'Y-m-d' ); $from = wp_date( 'Y-m-d', time() - 3 * DAY_IN_SECONDS );
        foreach ( array( 'meta','instagram','ga4','kommo' ) as $source ) {
            if ( $this->source_is_configured( $source ) ) { $this->sync_source( $source, $from, $to ); }
        }
    }

    private function source_is_configured( $source ) {
        $s = self::settings();
        if ( 'meta' === $source ) { return ! empty( $s['meta_ad_account_id'] ) && '' !== self::secret( 'meta' ); }
        if ( 'instagram' === $source ) { return ! empty( $s['meta_ig_user_id'] ) && '' !== self::secret( 'meta' ); }
        if ( 'ga4' === $source ) { return ! empty( $s['ga4_property_id'] ) && '' !== self::secret( 'ga4' ); }
        if ( 'kommo' === $source ) { return ! empty( $s['kommo_subdomain'] ) && '' !== self::secret( 'kommo' ); }
        return false;
    }

    private function sync_source( $source, $from, $to ) {
        $start = current_time( 'mysql' ); $rows = 0; $status = 'ok'; $msg = '';
        try {
            if ( 'meta' === $source ) { $rows = $this->sync_meta( $from, $to ); }
            elseif ( 'instagram' === $source ) { $rows = $this->sync_instagram( $from, $to ); }
            elseif ( 'ga4' === $source ) { $rows = $this->sync_ga4( $from, $to ); }
            elseif ( 'kommo' === $source ) { $rows = $this->sync_kommo( $from, $to ); }
            else { throw new Exception( 'Bilinmeyen kaynak.' ); }
        } catch ( Exception $e ) { $status = 'error'; $msg = $e->getMessage(); }
        $this->log_sync( $source, $status, $start, current_time( 'mysql' ), $rows, $msg );
        return array( 'status'=>$status, 'rows'=>$rows, 'message'=>$msg );
    }

    private function log_sync( $source, $status, $start, $finish, $rows, $message ) {
        global $wpdb; $wpdb->insert( self::table( 'sync_log' ), array(
            'source_name'=>$source,'status_name'=>$status,'started_at'=>$start,'finished_at'=>$finish,'rows_count'=>(int)$rows,'message'=>(string)$message
        ), array( '%s','%s','%s','%s','%d','%s' ) );
    }

    /* ======================================================
     * Meta Ads / Instagram
     * ==================================================== */

    private function meta_get( $path, $params = array() ) {
        $s = self::settings(); $token = self::secret( 'meta' );
        if ( ! $token ) { throw new Exception( 'Meta Access Token kayıtlı değil.' ); }
        $version = $s['meta_api_version'] ?: 'v26.0';
        $url = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . ltrim( $path, '/' );
        if ( $params ) { $url = add_query_arg( $params, $url ); }
        $res = wp_remote_get( $url, array( 'timeout'=>30, 'headers'=>array( 'Authorization'=>'Bearer ' . $token, 'Accept'=>'application/json' ) ) );
        if ( is_wp_error( $res ) ) { throw new Exception( $res->get_error_message() ); }
        $code = wp_remote_retrieve_response_code( $res ); $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $code < 200 || $code >= 300 ) { $err = is_array( $body ) && ! empty( $body['error']['message'] ) ? $body['error']['message'] : 'Meta API HTTP ' . $code; throw new Exception( $err ); }
        return is_array( $body ) ? $body : array();
    }

    private static function action_value( $actions, $types ) {
        $sum = 0.0;
        foreach ( (array) $actions as $a ) {
            if ( ! empty( $a['action_type'] ) && in_array( $a['action_type'], $types, true ) ) { $sum += (float) ( $a['value'] ?? 0 ); }
        }
        return $sum;
    }

    private function sync_meta( $from, $to ) {
        global $wpdb; $s = self::settings();
        $account = $s['meta_ad_account_id'];
        if ( ! $account ) { throw new Exception( 'Meta Ad Account ID kayıtlı değil.' ); }
        if ( 0 !== strpos( $account, 'act_' ) ) { $account = 'act_' . preg_replace( '/\D+/', '', $account ); }
        $params = array(
            'fields' => 'date_start,date_stop,campaign_id,campaign_name,spend,impressions,reach,clicks,ctr,cpc,actions,action_values',
            'level' => 'campaign', 'time_increment' => 1, 'limit' => 500,
            'time_range' => wp_json_encode( array( 'since'=>$from, 'until'=>$to ) ),
        );
        $data = $this->meta_get( $account . '/insights', $params ); $rows = 0; $page = 0;
        while ( true ) {
            foreach ( (array) ( $data['data'] ?? array() ) as $r ) {
                $date = sanitize_text_field( $r['date_start'] ?? '' ); if ( ! $date ) { continue; }
                $purchases = self::action_value( $r['actions'] ?? array(), array( 'purchase','omni_purchase','offsite_conversion.fb_pixel_purchase','onsite_conversion.purchase' ) );
                $pvalue = self::action_value( $r['action_values'] ?? array(), array( 'purchase','omni_purchase','offsite_conversion.fb_pixel_purchase','onsite_conversion.purchase' ) );
                $links = self::action_value( $r['actions'] ?? array(), array( 'link_click','outbound_click' ) );
                $cid = sanitize_text_field( $r['campaign_id'] ?? '' );
                $row_key = hash( 'sha256', $date . '|campaign|' . $cid );
                $wpdb->replace( self::table( 'meta_daily' ), array(
                    'row_key'=>$row_key,'metric_date'=>$date,'level_name'=>'campaign','account_id'=>$account,
                    'campaign_id'=>$cid,'campaign_name'=>sanitize_text_field( $r['campaign_name'] ?? '' ),'adset_id'=>'','adset_name'=>'','ad_id'=>'','ad_name'=>'',
                    'spend'=>(float)($r['spend']??0),'impressions'=>(int)($r['impressions']??0),'reach'=>(int)($r['reach']??0),'clicks'=>(int)($r['clicks']??0),'link_clicks'=>(int)$links,
                    'ctr'=>(float)($r['ctr']??0),'cpc'=>(float)($r['cpc']??0),'purchases'=>$purchases,'purchase_value'=>$pvalue,
                    'raw_json'=>wp_json_encode( $r ),'updated_at'=>current_time( 'mysql' )
                ) ); $rows++;
            }
            $next = $data['paging']['next'] ?? ''; if ( ! $next || ++$page >= 20 ) { break; }
            $res = wp_remote_get( esc_url_raw( $next ), array( 'timeout'=>30, 'headers'=>array( 'Authorization'=>'Bearer ' . self::secret( 'meta' ) ) ) );
            if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 300 ) { break; }
            $data = json_decode( wp_remote_retrieve_body( $res ), true ); if ( ! is_array( $data ) ) { break; }
        }
        return $rows;
    }

    private function sync_instagram( $from, $to ) {
        global $wpdb; $s = self::settings(); $ig = $s['meta_ig_user_id'];
        if ( ! $ig ) { throw new Exception( 'Instagram Business ID kayıtlı değil.' ); }
        $profile = $this->meta_get( $ig, array( 'fields'=>'id,username,followers_count,media_count' ) );
        try {
            $media = $this->meta_get( $ig . '/media', array( 'fields'=>'id,media_type,timestamp,like_count,comments_count,permalink','limit'=>100 ) );
        } catch ( Exception $e ) {
            // Bazı Instagram izin setlerinde like/comment alanları kapalı olabilir.
            // Profil + yayın sayımı yine çalışsın; etkileşimler 0 kalır.
            $media = $this->meta_get( $ig . '/media', array( 'fields'=>'id,media_type,timestamp,permalink','limit'=>100 ) );
        }
        $by = array();
        foreach ( (array) ( $media['data'] ?? array() ) as $r ) {
            $ts = ! empty( $r['timestamp'] ) ? strtotime( $r['timestamp'] ) : 0; if ( ! $ts ) { continue; }
            $d = gmdate( 'Y-m-d', $ts ); if ( $d < $from || $d > $to ) { continue; }
            if ( empty( $by[$d] ) ) { $by[$d] = array( 'posts'=>0,'likes'=>0,'comments'=>0 ); }
            $by[$d]['posts']++; $by[$d]['likes'] += (int)($r['like_count']??0); $by[$d]['comments'] += (int)($r['comments_count']??0);
        }
        // Profile snapshot always on today, media aggregates on their dates.
        $today = wp_date( 'Y-m-d' ); if ( empty( $by[$today] ) ) { $by[$today] = array( 'posts'=>0,'likes'=>0,'comments'=>0 ); }
        $rows = 0;
        foreach ( $by as $d=>$v ) {
            $wpdb->replace( self::table( 'instagram_daily' ), array(
                'metric_date'=>$d,'ig_user_id'=>$ig,'username'=>sanitize_text_field($profile['username']??''),
                'followers_count'=>(int)($profile['followers_count']??0),'media_count'=>(int)($profile['media_count']??0),
                'posts_seen'=>(int)$v['posts'],'likes'=>(int)$v['likes'],'comments'=>(int)$v['comments'],
                'raw_json'=>wp_json_encode( array( 'profile'=>$profile,'aggregate'=>$v ) ),'updated_at'=>current_time('mysql')
            ) ); $rows++;
        }
        return $rows;
    }

    /* ======================================================
     * GA4 Data API
     * ==================================================== */

    private function ga4_access_token() {
        $json = self::secret( 'ga4' ); if ( ! $json ) { throw new Exception( 'GA4 Service Account JSON kayıtlı değil.' ); }
        $sa = json_decode( $json, true ); if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) { throw new Exception( 'GA4 Service Account JSON geçersiz.' ); }
        $b64 = static function( $v ) { return rtrim( strtr( base64_encode( $v ), '+/', '-_' ), '=' ); };
        $now = time();
        $header = $b64( wp_json_encode( array( 'alg'=>'RS256','typ'=>'JWT' ) ) );
        $payload = $b64( wp_json_encode( array( 'iss'=>$sa['client_email'],'scope'=>'https://www.googleapis.com/auth/analytics.readonly','aud'=>$sa['token_uri']??'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600 ) ) );
        $input = $header . '.' . $payload; $sig = '';
        if ( ! openssl_sign( $input, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256 ) ) { throw new Exception( 'GA4 JWT imzası oluşturulamadı.' ); }
        $jwt = $input . '.' . $b64( $sig );
        $res = wp_remote_post( $sa['token_uri']??'https://oauth2.googleapis.com/token', array( 'timeout'=>30, 'body'=>array( 'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt ) ) );
        if ( is_wp_error( $res ) ) { throw new Exception( $res->get_error_message() ); }
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( wp_remote_retrieve_response_code( $res ) >= 300 || empty( $body['access_token'] ) ) { throw new Exception( $body['error_description'] ?? 'Google OAuth token alınamadı.' ); }
        return $body['access_token'];
    }

    private function sync_ga4( $from, $to ) {
        global $wpdb; $s = self::settings(); $property = $s['ga4_property_id']; if ( ! $property ) { throw new Exception( 'GA4 Property ID kayıtlı değil.' ); }
        $token = $this->ga4_access_token();
        $url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( $property ) . ':runReport';
        $body = array(
            'dateRanges'=>array(array('startDate'=>$from,'endDate'=>$to)),
            'dimensions'=>array(array('name'=>'date'),array('name'=>'sessionSource'),array('name'=>'sessionMedium'),array('name'=>'sessionCampaignName')),
            'metrics'=>array(array('name'=>'sessions'),array('name'=>'totalUsers'),array('name'=>'newUsers'),array('name'=>'screenPageViews'),array('name'=>'addToCarts'),array('name'=>'ecommercePurchases'),array('name'=>'purchaseRevenue')),
            'limit'=>'100000'
        );
        $res = wp_remote_post( $url, array( 'timeout'=>45, 'headers'=>array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'), 'body'=>wp_json_encode($body) ) );
        if ( is_wp_error($res) ) { throw new Exception($res->get_error_message()); }
        $data = json_decode( wp_remote_retrieve_body($res), true ); $code = wp_remote_retrieve_response_code($res);
        if ( $code >= 300 ) { throw new Exception( $data['error']['message'] ?? 'GA4 API HTTP '.$code ); }
        $rows=0;
        foreach ( (array)($data['rows']??array()) as $r ) {
            $dims = array_map( static function($x){ return (string)($x['value']??''); }, (array)($r['dimensionValues']??array()) );
            $mets = array_map( static function($x){ return (string)($x['value']??'0'); }, (array)($r['metricValues']??array()) );
            $date8 = $dims[0]??''; $date = preg_match('/^\d{8}$/',$date8) ? substr($date8,0,4).'-'.substr($date8,4,2).'-'.substr($date8,6,2) : $from;
            $source = $dims[1]??''; $medium=$dims[2]??''; $campaign=$dims[3]??'';
            $key = hash('sha256',$date.'|'.$source.'|'.$medium.'|'.$campaign);
            $wpdb->replace( self::table('ga4_daily'), array(
                'row_key'=>$key,'metric_date'=>$date,'source_name'=>$source,'medium_name'=>$medium,'campaign_name'=>$campaign,
                'sessions'=>(int)($mets[0]??0),'total_users'=>(int)($mets[1]??0),'new_users'=>(int)($mets[2]??0),'page_views'=>(int)($mets[3]??0),'add_to_carts'=>(int)($mets[4]??0),'purchases'=>(float)($mets[5]??0),'purchase_revenue'=>(float)($mets[6]??0),
                'raw_json'=>wp_json_encode($r),'updated_at'=>current_time('mysql')
            ) ); $rows++;
        }
        return $rows;
    }

    /* ======================================================
     * Kommo
     * ==================================================== */

    private function kommo_get( $path, $params=array() ) {
        $s = self::settings(); $token = self::secret('kommo'); if(!$s['kommo_subdomain']||!$token){ throw new Exception('Kommo alt alan adı veya token kayıtlı değil.'); }
        $url = 'https://' . $s['kommo_subdomain'] . '.kommo.com/api/v4/' . ltrim($path,'/'); if($params){$url=add_query_arg($params,$url);} 
        $res=wp_remote_get($url,array('timeout'=>30,'headers'=>array('Authorization'=>'Bearer '.$token,'Accept'=>'application/json')));
        if(is_wp_error($res)){throw new Exception($res->get_error_message());} $code=wp_remote_retrieve_response_code($res); $data=json_decode(wp_remote_retrieve_body($res),true);
        if($code===204){return array();} if($code<200||$code>=300){throw new Exception($data['detail']??$data['title']??('Kommo API HTTP '.$code));} return is_array($data)?$data:array();
    }

    private function lead_order_id_from_custom_fields( $lead, $field_id ) {
        if(!$field_id){return 0;} foreach((array)($lead['custom_fields_values']??array()) as $cf){ if((string)($cf['field_id']??'')!==(string)$field_id){continue;} foreach((array)($cf['values']??array()) as $v){$raw=(string)($v['value']??''); if(preg_match('/\d+/', $raw,$m)){return absint($m[0]);}} } return 0;
    }

    /**
     * WooCommerce'in Kommo entegrasyonu sipariş lead'lerini genellikle
     * "Order#2245" biçiminde adlandırıyor. Özel sipariş alanı tanımlı değilse
     * güvenli yedek eşleştirme olarak sipariş numarasını lead adından çıkarır.
     * Yanlış eşleşmeyi önlemek için mümkünse WooCommerce siparişinin gerçekten
     * var olduğunu da doğrular.
     */
    private function lead_order_id_from_name( $lead ) {
        $name = trim( (string) ( $lead['name'] ?? '' ) );
        if ( '' === $name || ! preg_match( '/^Order\s*#\s*(\d+)\s*$/i', $name, $m ) ) {
            return 0;
        }

        $order_id = absint( $m[1] );
        if ( ! $order_id ) { return 0; }

        if ( function_exists( 'wc_get_order' ) && ! wc_get_order( $order_id ) ) {
            return 0;
        }

        return $order_id;
    }

    private function lead_order_id( $lead, $field_id ) {
        $order_id = $this->lead_order_id_from_custom_fields( $lead, $field_id );
        if ( $order_id ) { return $order_id; }
        return $this->lead_order_id_from_name( $lead );
    }

    /**
     * Kommo API PATCH helper. V3 CRM normalde raporlama odaklidir; bu metod
     * yalnizca WooCommerce siparisi ile guvenle eslesen lead'e etkinlik
     * baglami alanlarini (sehir/tarih/seans/salon/adres/konum) yazar.
     */
    private function kommo_patch( $path, $body ) {
        $s = self::settings();
        $token = self::secret( 'kommo' );
        if ( ! $s['kommo_subdomain'] || ! $token ) { throw new Exception( 'Kommo alt alan adi veya token kayitli degil.' ); }
        $url = 'https://' . $s['kommo_subdomain'] . '.kommo.com/api/v4/' . ltrim( $path, '/' );
        $res = wp_remote_request( $url, array(
            'method'  => 'PATCH',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $res ) ) { throw new Exception( $res->get_error_message() ); }
        $code = wp_remote_retrieve_response_code( $res );
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( $code < 200 || $code >= 300 ) {
            throw new Exception( is_array( $data ) ? ( $data['detail'] ?? $data['title'] ?? ( 'Kommo API HTTP ' . $code ) ) : ( 'Kommo API HTTP ' . $code ) );
        }
        return is_array( $data ) ? $data : array();
    }

    private static function normalize_db_key( $key ) {
        $key = strtolower( (string) $key );
        $key = preg_replace( '/[^a-z0-9]+/i', '_', $key );
        return trim( (string) $key, '_' );
    }

    /** Flatten top-level DB records and embedded JSON snapshots. */
    private static function flatten_record_values( $record, $prefix = '', $depth = 0 ) {
        if ( $depth > 4 ) { return array(); }
        if ( is_object( $record ) ) { $record = get_object_vars( $record ); }
        if ( ! is_array( $record ) ) { return array(); }
        $out = array();
        foreach ( $record as $k => $v ) {
            $nk = self::normalize_db_key( $prefix ? ( $prefix . '_' . $k ) : $k );
            if ( is_scalar( $v ) || null === $v ) {
                $sv = trim( (string) $v );
                if ( '' !== $sv ) { $out[ $nk ] = $sv; }
                if ( is_string( $v ) && '' !== $sv && ( '{' === substr( $sv, 0, 1 ) || '[' === substr( $sv, 0, 1 ) ) ) {
                    $decoded = json_decode( $sv, true );
                    if ( is_array( $decoded ) ) { $out = array_merge( $out, self::flatten_record_values( $decoded, $nk, $depth + 1 ) ); }
                }
            } elseif ( is_array( $v ) || is_object( $v ) ) {
                $out = array_merge( $out, self::flatten_record_values( $v, $nk, $depth + 1 ) );
            }
        }
        return $out;
    }

    private static function pick_flat_value( $flat, $candidates ) {
        foreach ( (array) $candidates as $candidate ) {
            $key = self::normalize_db_key( $candidate );
            if ( isset( $flat[ $key ] ) && '' !== trim( (string) $flat[ $key ] ) ) { return trim( (string) $flat[ $key ] ); }
        }
        return '';
    }

    private static function normalize_iso_date( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) { return ''; }
        if ( preg_match( '/^(20\d{2}-\d{2}-\d{2})/', $raw, $m ) ) { return $m[1]; }
        if ( preg_match( '/^(\d{1,2})[.\/-](\d{1,2})[.\/-](20\d{2})$/', $raw, $m ) ) {
            return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
        }
        if ( ctype_digit( $raw ) && (int) $raw > 1000000000 ) { return wp_date( 'Y-m-d', (int) $raw ); }
        $ts = strtotime( $raw );
        return $ts ? wp_date( 'Y-m-d', $ts ) : '';
    }

    private function order_session_value( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) { return ''; }
        $times = array();
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $texts = array();
            if ( method_exists( $item, 'get_name' ) ) { $texts[] = (string) $item->get_name(); }
            if ( method_exists( $item, 'get_meta_data' ) ) {
                foreach ( (array) $item->get_meta_data() as $meta ) {
                    $data = is_object( $meta ) && method_exists( $meta, 'get_data' ) ? $meta->get_data() : (array) $meta;
                    $key = (string) ( $data['key'] ?? '' );
                    $val = $data['value'] ?? '';
                    if ( is_scalar( $val ) ) {
                        if ( preg_match( '/seans|session|saat|time/i', $key ) ) { $texts[] = (string) $val; }
                        else { $texts[] = (string) $val; }
                    }
                }
            }
            foreach ( $texts as $text ) {
                if ( preg_match_all( '/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u', $text, $m, PREG_SET_ORDER ) ) {
                    foreach ( $m as $hit ) { $times[] = str_pad( $hit[1], 2, '0', STR_PAD_LEFT ) . ':' . $hit[2]; }
                }
            }
        }
        $times = array_values( array_unique( array_filter( $times ) ) );
        sort( $times, SORT_NATURAL );
        return implode( ', ', $times );
    }

    private function venue_record_for_event_flat( $event_flat ) {
        if ( ! class_exists( 'MDG_DB' ) ) { return null; }
        $venue_id = absint( self::pick_flat_value( $event_flat, array(
            'venue_id','salon_id','location_id','event_venue_id','venue_snapshot_id','salon_snapshot_id'
        ) ) );
        if ( ! $venue_id ) { return null; }
        global $wpdb;
        foreach ( array( 'venues', 'salons' ) as $logical ) {
            $table = MDG_DB::table( $logical );
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists === $table ) {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", $venue_id ) );
                if ( $row ) { return $row; }
            }
        }
        return null;
    }

    private function event_date_from_event_and_sessions( $event, $event_id ) {
        $flat = self::flatten_record_values( $event );
        $raw = self::pick_flat_value( $flat, array(
            'event_date','show_date','session_date','start_date','date','starts_at','start_at','start_datetime','datetime','start_time'
        ) );
        $date = self::normalize_iso_date( $raw );
        if ( $date ) { return $date; }
        if ( class_exists( 'MDG_Sessions' ) ) {
            $dates = array();
            foreach ( (array) MDG_Sessions::by_event( $event_id ) as $session ) {
                $sf = self::flatten_record_values( $session );
                $sr = self::pick_flat_value( $sf, array( 'session_date','event_date','show_date','start_date','date','starts_at','start_at','start_datetime','datetime','start_time' ) );
                $sd = self::normalize_iso_date( $sr );
                if ( $sd ) { $dates[] = $sd; }
            }
            if ( $dates ) { sort( $dates, SORT_STRING ); return $dates[0]; }
        }
        return '';
    }

    /**
     * Resolve the event context from the Madagaskar event/venue database.
     * Salonlar is the source of truth; the event snapshot is preferred so
     * later venue edits cannot rewrite a historical order's context.
     */
    private function order_event_snapshot( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'MDG_DB' ) ) { return array(); }
        $order = wc_get_order( $order_id );
        if ( ! $order ) { return array(); }
        global $wpdb;
        $map_table = MDG_DB::table( 'order_map' );
        $event_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$map_table} WHERE order_id=%d ORDER BY id ASC LIMIT 1", $order_id ) );
        if ( ! $event_id ) { return array(); }
        $events_table = MDG_DB::table( 'events' );
        $event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$events_table} WHERE id=%d LIMIT 1", $event_id ) );
        if ( ! $event ) { return array(); }

        $ef = self::flatten_record_values( $event );
        $venue = $this->venue_record_for_event_flat( $ef );
        $vf = $venue ? self::flatten_record_values( $venue ) : array();
        // Event snapshot fields win over the live venue row.
        $merged = array_merge( $vf, $ef );

        $city = self::pick_flat_value( $merged, array(
            'province_name','city_name','province','city','venue_snapshot_province_name','venue_snapshot_city','salon_snapshot_city'
        ) );
        $salon = self::pick_flat_value( $merged, array(
            'venue_snapshot_name','venue_snapshot_title','salon_snapshot_name','venue_name','salon_name','hall_name','location_name','name'
        ) );
        // Never use the event title itself as a salon name.
        if ( $salon && isset( $ef['title'] ) && $salon === (string) $ef['title'] && ! $venue ) { $salon = ''; }
        $address = self::pick_flat_value( $merged, array(
            'venue_snapshot_address','venue_snapshot_full_address','salon_snapshot_address','venue_address','salon_address','event_address','full_address','address'
        ) );
        $map_url = self::pick_flat_value( $merged, array(
            'venue_snapshot_maps_url','venue_snapshot_map_url','venue_snapshot_google_maps_url','salon_snapshot_maps_url',
            'maps_url','map_url','google_maps_url','google_maps','map_link','maps_link','location_url','venue_map_url'
        ) );
        if ( ! $map_url ) {
            $lat = self::pick_flat_value( $merged, array( 'venue_snapshot_lat','venue_snapshot_latitude','latitude','lat' ) );
            $lng = self::pick_flat_value( $merged, array( 'venue_snapshot_lng','venue_snapshot_longitude','longitude','lng','lon' ) );
            if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
                $map_url = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $lat . ',' . $lng );
            }
        }

        $snapshot = array(
            'event_id' => $event_id,
            'city'     => sanitize_text_field( $city ),
            'date'     => $this->event_date_from_event_and_sessions( $event, $event_id ),
            'session'  => sanitize_text_field( $this->order_session_value( $order ) ),
            'venue'    => sanitize_text_field( $salon ),
            'address'  => sanitize_textarea_field( $address ),
            'map_url'  => esc_url_raw( $map_url ),
        );
        return array_filter( $snapshot, static function( $v ) { return '' !== $v && null !== $v; } );
    }

    private function save_order_event_snapshot( $order, $snapshot ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) || ! $snapshot ) { return; }
        $order->update_meta_data( '_mdgy_event_snapshot_v1', wp_json_encode( $snapshot ) );
        $map = array(
            'city'    => '_mdgy_event_city',
            'date'    => '_mdgy_event_date',
            'session' => '_mdgy_event_session',
            'venue'   => '_mdgy_event_venue',
            'address' => '_mdgy_event_address',
            'map_url' => '_mdgy_event_map_url',
        );
        foreach ( $map as $key => $meta_key ) {
            if ( isset( $snapshot[ $key ] ) && '' !== (string) $snapshot[ $key ] ) { $order->update_meta_data( $meta_key, $snapshot[ $key ] ); }
        }
        $order->save();
    }

    private function kommo_lead_field_definitions() {
        $cache_key = 'mdgy_kommo_lead_fields_v122';
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) { return $cached; }
        $defs = array(); $page = 1;
        do {
            $data = $this->kommo_get( 'leads/custom_fields', array( 'page'=>$page, 'limit'=>250 ) );
            $items = (array) ( $data['_embedded']['custom_fields'] ?? array() );
            foreach ( $items as $field ) {
                $name = trim( (string) ( $field['name'] ?? '' ) );
                if ( '' === $name || empty( $field['id'] ) ) { continue; }
                $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
                if ( ! isset( $defs[ $key ] ) ) { $defs[ $key ] = array(); }
                $defs[ $key ][] = array( 'id'=>(int)$field['id'], 'type'=>(string)($field['type']??'text'), 'name'=>$name );
            }
            $page++;
        } while ( count( $items ) === 250 && $page <= 10 );
        set_transient( $cache_key, $defs, 15 * MINUTE_IN_SECONDS );
        return $defs;
    }

    private function kommo_custom_field_value( $definition, $raw ) {
        $type = (string) ( $definition['type'] ?? 'text' );
        if ( in_array( $type, array( 'date', 'date_time', 'birthday' ), true ) ) {
            $date = self::normalize_iso_date( $raw );
            if ( ! $date ) { return null; }
            try {
                $dt = new DateTimeImmutable( $date . ' 12:00:00', wp_timezone() );
                return $dt->getTimestamp();
            } catch ( Throwable $e ) { return null; }
        }
        return (string) $raw;
    }

    private function sync_order_event_snapshot_to_kommo( $order_id, $lead_id ) {
        if ( ! $order_id || ! $lead_id || ! function_exists( 'wc_get_order' ) ) { return false; }
        $order = wc_get_order( $order_id );
        if ( ! $order ) { return false; }
        $snapshot = $this->order_event_snapshot( $order_id );
        if ( ! $snapshot ) { return false; }
        $this->save_order_event_snapshot( $order, $snapshot );

        $field_map = array(
            'Şehir'            => 'city',
            'Gösteri Tarihi'   => 'date',
            'Seans'            => 'session',
            'Salon'            => 'venue',
            'Etkinlik Adresi'  => 'address',
            'Konum Linki'      => 'map_url',
        );
        $defs = $this->kommo_lead_field_definitions();
        $custom_values = array(); $seen = array();
        foreach ( $field_map as $label => $snap_key ) {
            if ( empty( $snapshot[ $snap_key ] ) ) { continue; }
            $lookup = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );
            foreach ( (array) ( $defs[ $lookup ] ?? array() ) as $def ) {
                $fid = (int) ( $def['id'] ?? 0 );
                if ( ! $fid || isset( $seen[ $fid ] ) ) { continue; }
                $value = $this->kommo_custom_field_value( $def, $snapshot[ $snap_key ] );
                if ( null === $value || '' === (string) $value ) { continue; }
                $custom_values[] = array( 'field_id'=>$fid, 'values'=>array( array( 'value'=>$value ) ) );
                $seen[ $fid ] = true;
            }
        }
        if ( ! $custom_values ) {
            $order->update_meta_data( '_mdgy_event_kommo_sync_error', 'Kommo etkinlik alanlari bulunamadi.' );
            $order->save();
            return false;
        }
        try {
            $this->kommo_patch( 'leads', array( array( 'id'=>(int)$lead_id, 'custom_fields_values'=>$custom_values ) ) );
            $order->update_meta_data( '_mdgy_event_kommo_synced_at', current_time( 'mysql' ) );
            $order->delete_meta_data( '_mdgy_event_kommo_sync_error' );
            $order->save();
            return true;
        } catch ( Throwable $e ) {
            $order->update_meta_data( '_mdgy_event_kommo_sync_error', sanitize_text_field( $e->getMessage() ) );
            $order->save();
            return false;
        }
    }

    private function sync_kommo( $from, $to ) {
        global $wpdb; $s=self::settings(); $tz=wp_timezone();
        $from_ts=(new DateTimeImmutable($from.' 00:00:00',$tz))->getTimestamp(); $to_ts=(new DateTimeImmutable($to.' 23:59:59',$tz))->getTimestamp();
        $page=1;$rows=0;
        do{
            $data=$this->kommo_get('leads',array('page'=>$page,'limit'=>250,'with'=>'source','filter[updated_at][from]'=>$from_ts,'filter[updated_at][to]'=>$to_ts,'order[updated_at]'=>'asc'));
            $items=(array)($data['_embedded']['leads']??array());
            foreach($items as $lead){
                $source=''; if(!empty($lead['_embedded']['source']['name'])){$source=sanitize_text_field($lead['_embedded']['source']['name']);}
                $order_id=$this->lead_order_id($lead,$s['kommo_order_field_id']);
                $wpdb->replace(self::table('kommo_leads'),array(
                    'lead_id'=>(int)($lead['id']??0),'pipeline_id'=>(int)($lead['pipeline_id']??0),'status_id'=>(int)($lead['status_id']??0),'price'=>(float)($lead['price']??0),'source_name'=>$source,
                    'order_id'=>$order_id?:null,'created_ts'=>(int)($lead['created_at']??0),'updated_ts'=>(int)($lead['updated_at']??0),'closed_ts'=>(int)($lead['closed_at']??0),'synced_at'=>current_time('mysql')
                ));
                if($order_id){
                    $lead_id=(int)($lead['id']??0);
                    $wpdb->update(self::table('attribution'),array('kommo_lead_id'=>$lead_id,'updated_at'=>current_time('mysql')),array('order_id'=>$order_id),array('%d','%s'),array('%d'));
                    if(function_exists('wc_get_order')){
                        $order=wc_get_order($order_id);
                        if($order && method_exists($order,'update_meta_data')){
                            $order->update_meta_data('_mdgy_kommo_lead_id',$lead_id);
                            $order->save();
                            // Site (Salonlar + Etkinlik Yayınla) verisini eşleşen Kommo sipariş lead'ine taşı.
                            $this->sync_order_event_snapshot_to_kommo($order_id,$lead_id);
                        }
                    }
                }
                $rows++;
            }
            $page++;
        }while(count($items)===250 && $page<=20);
        return $rows;
    }

    /* ======================================================
     * Kommo webhook REST
     * ==================================================== */

    public function register_rest() {
        register_rest_route( 'mdgy/v1', '/kommo/webhook', array(
            'methods'=>'POST','callback'=>array($this,'handle_kommo_webhook'),'permission_callback'=>'__return_true'
        ) );
    }

    private function kommo_webhook_url() {
        $key=(string)get_option(self::OPT_WEBHOOK_KEY,''); if(!$key){$key=wp_generate_password(40,false,false);update_option(self::OPT_WEBHOOK_KEY,$key,false);} 
        return add_query_arg('key',rawurlencode($key),rest_url('mdgy/v1/kommo/webhook'));
    }

    public function handle_kommo_webhook( WP_REST_Request $request ) {
        $key=(string)$request->get_param('key'); if(!$key || !hash_equals((string)get_option(self::OPT_WEBHOOK_KEY,''),$key)){return new WP_REST_Response(array('ok'=>false),403);} 
        $params=$request->get_params(); $count=0;
        // Kommo posts x-www-form-urlencoded nested arrays. Walk known event families.
        $map=array(
            'leads'=>array('add'=>'add_lead','update'=>'update_lead','status'=>'status_lead'),
            'contacts'=>array('add'=>'add_contact','update'=>'update_contact'),
            'talks'=>array('add'=>'add_talk','update'=>'update_talk'),
            'messages'=>array('add'=>'add_message','outgoing'=>'add_outgoing_message'),
        );
        foreach($map as $family=>$actions){
            if(empty($params[$family])||!is_array($params[$family])){continue;}
            foreach($actions as $action=>$event_type){
                if(empty($params[$family][$action])||!is_array($params[$family][$action])){continue;}
                foreach($params[$family][$action] as $item){$this->store_crm_event($event_type,$family,$item);$count++;}
            }
        }
        // Some Kommo webhook payloads use message / lead singular shapes. Record a safe generic event if known keys exist.
        if(!$count){
            $flat=wp_json_encode(array_keys((array)$params));
            $etype='webhook'; if(isset($params['message'])){$etype='add_message';} elseif(isset($params['lead'])){$etype='update_lead';}
            $this->store_crm_event($etype,'generic',array('id'=>0,'_hash_basis'=>$flat));$count=1;
        }
        return new WP_REST_Response(array('ok'=>true,'events'=>$count),200);
    }

    private function store_crm_event( $event_type, $entity_type, $item ) {
        global $wpdb; $item=is_array($item)?$item:array();
        $entity_id=absint($item['id']??$item['element_id']??0); $lead_id=absint($item['lead_id']??($entity_type==='leads'?$entity_id:0));
        $pipeline=absint($item['pipeline_id']??0); $status=absint($item['status_id']??0);
        $ts=absint($item['updated_at']??$item['created_at']??$item['last_modified']??time());
        $basis=wp_json_encode(array('event'=>$event_type,'entity'=>$entity_type,'id'=>$entity_id,'lead'=>$lead_id,'pipeline'=>$pipeline,'status'=>$status,'ts'=>$ts,'keys'=>array_keys($item)));
        $wpdb->insert(self::table('crm_events'),array(
            'event_type'=>sanitize_key($event_type),'entity_type'=>sanitize_key($entity_type),'entity_id'=>$entity_id,'lead_id'=>$lead_id,'pipeline_id'=>$pipeline,'status_id'=>$status,
            'occurred_at'=>wp_date('Y-m-d H:i:s',$ts),'payload_hash'=>hash('sha256',$basis),'created_at'=>current_time('mysql')
        ));
    }

    /* ======================================================
     * Attribution capture -> Woo order
     * ==================================================== */

    public function capture_attribution() {
        if ( is_admin() || wp_doing_ajax() ) { return; }
        $keys=array('utm_source','utm_medium','utm_campaign','utm_id','utm_content','utm_term','fbclid','gclid'); $hit=array();
        foreach($keys as $k){if(isset($_GET[$k])&&''!==$_GET[$k]){$hit[$k]=sanitize_text_field(wp_unslash($_GET[$k]));}}
        if(!$hit){return;} $hit['landing_url']=home_url(add_query_arg(array(),$GLOBALS['wp']->request??'')); $hit['captured_at']=time();
        $secure=is_ssl(); $cookie_opts=array('expires'=>time()+90*DAY_IN_SECONDS,'path'=>COOKIEPATH?:'/','domain'=>COOKIE_DOMAIN?:'','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax');
        $encoded=base64_encode(wp_json_encode($hit));
        if(empty($_COOKIE['mdgy_first_touch'])){setcookie('mdgy_first_touch',$encoded,$cookie_opts);$_COOKIE['mdgy_first_touch']=$encoded;}
        setcookie('mdgy_last_touch',$encoded,$cookie_opts);$_COOKIE['mdgy_last_touch']=$encoded;
    }

    private static function read_touch_cookie( $name ) {
        if(empty($_COOKIE[$name])){return array();} $raw=base64_decode((string)$_COOKIE[$name],true); if(false===$raw){return array();} $data=json_decode($raw,true); return is_array($data)?$data:array();
    }

    public function attach_attribution_to_order( $order, $data ) {
        if(!is_object($order)||!method_exists($order,'update_meta_data')){return;} $this->write_touch_meta($order);
    }

    public function attach_store_api_attribution( $order, $request ) {
        if(is_object($order)&&method_exists($order,'update_meta_data')){$this->write_touch_meta($order);} return $order;
    }

    private function write_touch_meta( $order ) {
        $first=self::read_touch_cookie('mdgy_first_touch'); $last=self::read_touch_cookie('mdgy_last_touch');
        if($first){$order->update_meta_data('_mdgy_first_touch',wp_json_encode($first));}
        if($last){$order->update_meta_data('_mdgy_last_touch',wp_json_encode($last)); foreach(array('utm_source','utm_medium','utm_campaign','utm_id','utm_content','utm_term','fbclid','gclid') as $k){if(isset($last[$k])){$order->update_meta_data('_mdgy_'.$k,$last[$k]);}}}
    }

    public function mirror_order_attribution( $order_id ) {
        if(!function_exists('wc_get_order')){return;} $order=wc_get_order($order_id); if(!$order){return;}
        $last=json_decode((string)$order->get_meta('_mdgy_last_touch'),true); if(!is_array($last)){$last=array();}
        $first=json_decode((string)$order->get_meta('_mdgy_first_touch'),true); if(!is_array($first)){$first=array();}
        $event_id=0; if(class_exists('MDG_DB')){global $wpdb;$map=MDG_DB::table('order_map');$event_id=(int)$wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$map} WHERE order_id=%d ORDER BY id ASC LIMIT 1",$order_id));}
        $paid=$order->get_date_paid(); $paid_at=$paid?$paid->date('Y-m-d H:i:s'):null;
        global $wpdb;
        $existing_lead_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT kommo_lead_id FROM " . self::table('attribution') . " WHERE order_id=%d LIMIT 1", $order_id ) );
        $wpdb->replace(self::table('attribution'),array(
            'order_id'=>(int)$order_id,'event_id'=>$event_id?:null,'kommo_lead_id'=>$existing_lead_id?:null,
            'source_name'=>sanitize_text_field($last['utm_source']??''),'medium_name'=>sanitize_text_field($last['utm_medium']??''),'campaign_name'=>sanitize_text_field($last['utm_campaign']??''),'campaign_id'=>sanitize_text_field($last['utm_id']??''),'content_name'=>sanitize_text_field($last['utm_content']??''),'term_name'=>sanitize_text_field($last['utm_term']??''),'fbclid'=>sanitize_text_field($last['fbclid']??''),'gclid'=>sanitize_text_field($last['gclid']??''),'landing_url'=>esc_url_raw($last['landing_url']??''),
            'order_total'=>(float)$order->get_total(),'paid_at'=>$paid_at,'first_touch_json'=>wp_json_encode($first),'last_touch_json'=>wp_json_encode($last),'updated_at'=>current_time('mysql')
        ));
    }

    /* ======================================================
     * GA4 e-commerce events (read-only measurement)
     * ==================================================== */

    private function ga4_product_item( $product, $quantity = 1, $price = null ) {
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) { return array(); }
        $product_id = (int) $product->get_id();
        $parent_id = method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0;
        $category_id = $parent_id ?: $product_id;
        $categories = function_exists( 'wc_get_product_category_list' )
            ? wp_get_post_terms( $category_id, 'product_cat', array( 'fields' => 'names' ) )
            : array();
        if ( is_wp_error( $categories ) ) { $categories = array(); }
        $item = array(
            'item_id'   => (string) ( method_exists( $product, 'get_sku' ) && $product->get_sku() ? $product->get_sku() : $product_id ),
            'item_name' => (string) $product->get_name(),
            'price'     => (float) ( null === $price ? $product->get_price() : $price ),
            'quantity'  => max( 1, (int) $quantity ),
        );
        if ( $categories ) { $item['item_category'] = (string) reset( $categories ); }
        if ( $parent_id && method_exists( $product, 'get_attribute_summary' ) ) {
            $variant = trim( wp_strip_all_tags( (string) $product->get_attribute_summary() ) );
            if ( $variant ) { $item['item_variant'] = $variant; }
        }
        return $item;
    }

    private function ga4_cart_payload() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return array(); }
        $items = array();
        foreach ( WC()->cart->get_cart() as $line ) {
            $product = isset( $line['data'] ) ? $line['data'] : null;
            $quantity = max( 1, (int) ( $line['quantity'] ?? 1 ) );
            $unit_price = isset( $line['line_total'] ) ? ( (float) $line['line_total'] / $quantity ) : null;
            $item = $this->ga4_product_item( $product, $quantity, $unit_price );
            if ( $item ) { $items[] = $item; }
        }
        if ( ! $items ) { return array(); }
        return array(
            'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY',
            'value'    => (float) WC()->cart->get_total( 'edit' ),
            'items'    => $items,
        );
    }

    private function ga4_purchase_payload() {
        if ( ! function_exists( 'wc_get_order_id_by_order_key' ) || ! function_exists( 'wc_get_order' ) ) { return array(); }
        $order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
        if ( ! $order_key ) { return array(); }
        $order_id = (int) wc_get_order_id_by_order_key( $order_key );
        $order = $order_id ? wc_get_order( $order_id ) : false;
        if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $order_key ) ) { return array(); }
        if ( method_exists( $order, 'is_paid' ) && ! $order->is_paid() ) { return array(); }
        $items = array();
        foreach ( $order->get_items( 'line_item' ) as $line ) {
            $product = $line->get_product();
            $quantity = max( 1, (int) $line->get_quantity() );
            $unit_price = (float) $line->get_total() / $quantity;
            $item = $this->ga4_product_item( $product, $quantity, $unit_price );
            if ( ! $item ) {
                $item = array(
                    'item_id'   => (string) $line->get_product_id(),
                    'item_name' => (string) $line->get_name(),
                    'price'     => $unit_price,
                    'quantity'  => $quantity,
                );
            }
            $items[] = $item;
        }
        if ( ! $items ) { return array(); }
        $payload = array(
            'transaction_id' => (string) $order->get_order_number(),
            'value'          => (float) $order->get_total(),
            'tax'            => (float) $order->get_total_tax(),
            'shipping'       => (float) $order->get_shipping_total(),
            'currency'       => (string) $order->get_currency(),
            'items'          => $items,
        );
        $coupons = method_exists( $order, 'get_coupon_codes' ) ? $order->get_coupon_codes() : array();
        if ( $coupons ) { $payload['coupon'] = implode( ',', array_map( 'sanitize_text_field', $coupons ) ); }
        return $payload;
    }

    public function render_ga4_base_tag() {
        if ( is_admin() ) { return; }
        $measurement_id = strtoupper( (string) self::settings()['ga4_measurement_id'] );
        if ( ! preg_match( '/^G-[A-Z0-9]+$/', $measurement_id ) ) { return; }
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $measurement_id ); ?>"></script>
        <script id="mdgy-ga4-base">
        window.dataLayer=window.dataLayer||[];
        window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};
        window.gtag('js',new Date());
        window.gtag('config',<?php echo wp_json_encode( $measurement_id ); ?>,{'send_page_view':true});
        </script>
        <?php
    }

    public function render_ga4_ecommerce_events() {
        if ( is_admin() ) { return; }
        $events = array();
        $product_item = array();
        if ( function_exists( 'is_product' ) && is_product() ) {
            global $product;
            $product_item = $this->ga4_product_item( $product );
            if ( $product_item ) {
                $events[] = array( 'name' => 'view_item', 'params' => array(
                    'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY',
                    'value' => (float) $product_item['price'], 'items' => array( $product_item ),
                ) );
            }
        }
        $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
        $is_event_page = false !== strpos( $request_path, '/etkinlik/' );
        if ( $is_event_page && is_singular() ) {
            $post_id = get_queried_object_id();
            if ( $post_id ) {
                $product_item = array(
                    'item_id'   => 'event-' . (string) $post_id,
                    'item_name' => (string) get_the_title( $post_id ),
                    'price'     => 0,
                    'quantity'  => 1,
                );
                $events[] = array( 'name' => 'view_item', 'params' => array(
                    'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY',
                    'value' => 0, 'items' => array( $product_item ),
                ) );
            }
        }
        if ( function_exists( 'is_checkout' ) && is_checkout() ) {
            if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
                $purchase = $this->ga4_purchase_payload();
                if ( $purchase ) { $events[] = array( 'name' => 'purchase', 'params' => $purchase ); }
            } else {
                $checkout = $this->ga4_cart_payload();
                if ( $checkout ) { $events[] = array( 'name' => 'begin_checkout', 'params' => $checkout ); }
            }
        }
        $is_catalog = ( function_exists( 'is_shop' ) && is_shop() )
            || ( function_exists( 'is_product_category' ) && is_product_category() )
            || ( function_exists( 'is_product_tag' ) && is_product_tag() );
        ?>
        <script id="mdgy-ga4-ecommerce">
        (function(w,d){
            w.dataLayer=w.dataLayer||[];
            w.gtag=w.gtag||function(){w.dataLayer.push(arguments);};
            var initial=<?php echo wp_json_encode( $events, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;
            var currentItem=<?php echo wp_json_encode( $product_item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;
            var isMdgyEvent=/\/etkinlik\//i.test(w.location.pathname)||d.body.classList.contains('mdg-public-event-page');
            if(isMdgyEvent&&(!currentItem||!currentItem.item_id)){
                var eventTitle=(d.querySelector('h1')&&d.querySelector('h1').textContent.trim())||d.title.replace(/\s*[|–-].*$/,'').trim();
                var eventSlug=w.location.pathname.replace(/^\/+|\/+$/g,'').split('/').pop()||'etkinlik';
                currentItem={item_id:'event-'+eventSlug,item_name:eventTitle,price:0,quantity:1};
                initial.push({name:'view_item',params:{currency:'<?php echo esc_js( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' ); ?>',value:0,items:[currentItem]}});
            }
            function send(name,params){
                try{
                    if(name==='purchase'){
                        var purchaseKey='mdgy_ga4_purchase_'+String(params.transaction_id||'');
                        if(w.localStorage&&w.localStorage.getItem(purchaseKey)){return;}
                        w.gtag('event',name,params);
                        if(w.localStorage){w.localStorage.setItem(purchaseKey,'1');}
                        return;
                    }
                    if(name==='begin_checkout'){
                        var cartKey='mdgy_ga4_checkout_'+JSON.stringify(params.items||[]);
                        if(w.sessionStorage&&w.sessionStorage.getItem(cartKey)){return;}
                        w.gtag('event',name,params);
                        if(w.sessionStorage){w.sessionStorage.setItem(cartKey,'1');}
                        return;
                    }
                    w.gtag('event',name,params);
                }catch(e){}
            }
            initial.forEach(function(evt){send(evt.name,evt.params);});
            var lastAdd={id:'',time:0};
            function sendAdd(item,quantity){
                if(!item||!item.item_id){return;}
                item=Object.assign({},item,{quantity:Math.max(1,parseInt(quantity||1,10)||1)});
                var now=Date.now(),id=String(item.item_id);
                if(lastAdd.id===id&&now-lastAdd.time<1500){return;}
                lastAdd={id:id,time:now};
                send('add_to_cart',{currency:'<?php echo esc_js( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' ); ?>',value:Number(item.price||0)*item.quantity,items:[item]});
            }
            function itemFromElement(el){
                if(!el){return currentItem;}
                var form=el.closest?el.closest('form'):null;
                var scope=(el.closest&&el.closest('.product,.event,.etkinlik,article'))||form||d;
                var id=el.getAttribute('data-product_id')||el.getAttribute('data-product-id')||'';
                if(!id&&form){var idInput=form.querySelector('[name="add-to-cart"],[name="product_id"],[name="variation_id"]');if(idInput){id=idInput.value||idInput.getAttribute('data-product_id')||'';}}
                var heading=scope.querySelector?scope.querySelector('.woocommerce-loop-product__title,.product_title,h1,h2,h3'):null;
                var price=Number(el.getAttribute('data-price')||0);
                if(!id&&currentItem&&currentItem.item_id){return Object.assign({},currentItem);}
                return id?{item_id:String(id),item_name:heading?heading.textContent.trim():('Bilet '+id),price:price}:currentItem;
            }
            function mdgyTicketSelection(){
                var items=[],value=0;
                d.querySelectorAll('.mdg-ticket-option').forEach(function(row){
                    var input=row.querySelector('input[type="number"]'),quantity=Math.max(0,parseInt(input&&input.value||0,10)||0);
                    if(!quantity){return;}
                    var code=row.getAttribute('data-ticket-code')||'BILET';
                    var price=Number(row.getAttribute('data-ticket-price')||0);
                    var label=row.querySelector('strong');
                    items.push({item_id:String(code),item_name:((currentItem&&currentItem.item_name)?currentItem.item_name+' – ':'')+(label?label.textContent.trim():code),price:price,quantity:quantity});
                    value+=price*quantity;
                });
                return {items:items,value:value};
            }
            d.addEventListener('submit',function(e){
                var form=e.target&&e.target.closest?e.target.closest('form'):null;
                if(!form){return;}
                var trigger=form.querySelector('[name="add-to-cart"],[name="product_id"],[data-product_id],[data-product-id]');
                var ticketForm=/bilet|ticket|cart|sepet|checkout/i.test((form.className||'')+' '+(form.getAttribute('action')||'')+' '+(form.textContent||''));
                if(!trigger&&(!currentItem||!ticketForm)){return;}
                var qty=form.querySelector('[name="quantity"]'); sendAdd(itemFromElement(trigger||form),qty?qty.value:1);
            },true);
            d.addEventListener('click',function(e){
                var clicked=e.target&&e.target.closest?e.target.closest('a,button,input[type="submit"],.add_to_cart_button,[name="add-to-cart"],[data-product_id],[data-product-id]'):null;
                var label=clicked?((clicked.textContent||clicked.value||'')+' '+(clicked.className||'')):'';
                if(clicked&&clicked.matches('[data-mdg-checkout-preview]')){
                    var selection=mdgyTicketSelection();
                    if(selection.items.length){send('add_to_cart',{currency:'<?php echo esc_js( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' ); ?>',value:selection.value,items:selection.items});}
                    return;
                }
                var el=clicked&&(clicked.matches('.add_to_cart_button,[name="add-to-cart"],[data-product_id],[data-product-id]')||/bilet\s*al|ticket|sepete\s*ekle/i.test(label))?clicked:null;
                if(!el){return;}
                var form=el.closest('form'),qty=form&&form.querySelector('[name="quantity"]');
                sendAdd(itemFromElement(el),qty?qty.value:(el.getAttribute('data-quantity')||1));
            },true);
            if(w.jQuery){w.jQuery(d.body).on('added_to_cart',function(evt,fragments,hash,button){
                var el=button&&button[0]?button[0]:null;if(!el){return;}
                var id=el.getAttribute('data-product_id')||'';
                var title=el.closest('.product')&&el.closest('.product').querySelector('.woocommerce-loop-product__title');
                sendAdd({item_id:String(id),item_name:title?title.textContent.trim():('Ürün '+id),price:Number(el.getAttribute('data-price')||0)},el.getAttribute('data-quantity')||1);
            });}
        })(window,document);
        </script>
        <?php
    }

    /* ======================================================
     * Summaries / render tables
     * ==================================================== */

    private function marketing_summary( $from, $to ) {
        global $wpdb; $meta=self::table('meta_daily');$ga4=self::table('ga4_daily');$ig=self::table('instagram_daily');
        $m=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(spend),0) spend,COALESCE(SUM(impressions),0) impressions,COALESCE(SUM(reach),0) reach,COALESCE(SUM(clicks),0) clicks FROM {$meta} WHERE metric_date BETWEEN %s AND %s",$from,$to),ARRAY_A);
        $g=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(sessions),0) sessions,COALESCE(SUM(purchases),0) purchases,COALESCE(SUM(purchase_revenue),0) revenue FROM {$ga4} WHERE metric_date BETWEEN %s AND %s",$from,$to),ARRAY_A);
        $followers=(int)$wpdb->get_var("SELECT followers_count FROM {$ig} ORDER BY metric_date DESC,id DESC LIMIT 1");
        return array('meta_spend'=>(float)($m['spend']??0),'meta_impressions'=>(int)($m['impressions']??0),'meta_reach'=>(int)($m['reach']??0),'meta_clicks'=>(int)($m['clicks']??0),'ga4_sessions'=>(int)($g['sessions']??0),'ga4_purchases'=>(float)($g['purchases']??0),'ga4_revenue'=>(float)($g['revenue']??0),'ig_followers'=>$followers);
    }

    private function crm_summary( $from, $to ) {
        global $wpdb;$leads=self::table('kommo_leads');$events=self::table('crm_events');
        $tz=wp_timezone();$from_ts=(new DateTimeImmutable($from.' 00:00:00',$tz))->getTimestamp();$to_ts=(new DateTimeImmutable($to.' 23:59:59',$tz))->getTimestamp();
        $new=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$leads} WHERE created_ts BETWEEN %d AND %d",$from_ts,$to_ts));
        $won=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$leads} WHERE status_id=142 AND closed_ts BETWEEN %d AND %d",$from_ts,$to_ts));
        $lost=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$leads} WHERE status_id=143 AND closed_ts BETWEEN %d AND %d",$from_ts,$to_ts));
        $inc=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type IN ('add_message','incoming_message') AND DATE(occurred_at) BETWEEN %s AND %s",$from,$to));
        $out=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type IN ('add_outgoing_message','outgoing_message') AND DATE(occurred_at) BETWEEN %s AND %s",$from,$to));
        return array('new_leads'=>$new,'won_leads'=>$won,'lost_leads'=>$lost,'incoming_messages'=>$inc,'outgoing_messages'=>$out);
    }

    private function sales_summary( $from, $to ) {
        if(!class_exists('MDG_DB')){return array('tickets'=>0,'revenue'=>0);}
        global $wpdb;$m=MDG_DB::table('order_map');
        $tz=wp_timezone();$utc=new DateTimeZone('UTC');$f=(new DateTimeImmutable($from.' 00:00:00',$tz))->setTimezone($utc)->format('Y-m-d H:i:s');$t=(new DateTimeImmutable($to.' 23:59:59',$tz))->setTimezone($utc)->format('Y-m-d H:i:s');
        $r=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(quantity),0) tickets,COALESCE(SUM(line_total),0) revenue FROM {$m} WHERE paid_at BETWEEN %s AND %s AND order_status NOT IN ('failed','cancelled','refunded','trash')",$f,$t),ARRAY_A);
        return array('tickets'=>(int)($r['tickets']??0),'revenue'=>(float)($r['revenue']??0));
    }

    private function attribution_summary( $from, $to ) {
        global $wpdb;$a=self::table('attribution');
        $all=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) orders,COALESCE(SUM(order_total),0) revenue FROM {$a} WHERE DATE(paid_at) BETWEEN %s AND %s",$from,$to),ARRAY_A);
        $meta=$wpdb->get_row($wpdb->prepare("SELECT COUNT(*) orders,COALESCE(SUM(order_total),0) revenue FROM {$a} WHERE DATE(paid_at) BETWEEN %s AND %s AND (LOWER(source_name) IN ('meta','facebook','instagram','fb','ig') OR fbclid<>'')",$from,$to),ARRAY_A);
        return array('orders'=>(int)($all['orders']??0),'revenue'=>(float)($all['revenue']??0),'meta_orders'=>(int)($meta['orders']??0),'meta_revenue'=>(float)($meta['revenue']??0));
    }

    private function render_meta_campaigns( $from, $to ) {
        global $wpdb;$t=self::table('meta_daily');$rows=$wpdb->get_results($wpdb->prepare("SELECT campaign_id,campaign_name,SUM(spend) spend,SUM(impressions) impressions,SUM(reach) reach,SUM(clicks) clicks,SUM(link_clicks) link_clicks,SUM(purchases) purchases,SUM(purchase_value) purchase_value FROM {$t} WHERE metric_date BETWEEN %s AND %s GROUP BY campaign_id,campaign_name ORDER BY spend DESC LIMIT 100",$from,$to));
        echo '<div class="mdgy-panel"><h2>Meta Kampanyaları</h2><table class="widefat striped mdgy-table"><thead><tr><th>Kampanya</th><th>Harcama</th><th>Gösterim</th><th>Erişim</th><th>Tıklama</th><th>CTR</th><th>Meta Purchase</th><th>Meta Değer</th></tr></thead><tbody>';
        if(!$rows){echo '<tr><td colspan="8">Henüz Meta verisi yok.</td></tr>';} foreach($rows as $r){$ctr=$r->impressions?($r->clicks/$r->impressions*100):0;echo '<tr><td><strong>'.esc_html($r->campaign_name?:$r->campaign_id).'</strong><div class="mdgy-sub">'.esc_html($r->campaign_id).'</div></td><td>'.esc_html(self::money($r->spend)).'</td><td>'.esc_html(number_format_i18n($r->impressions)).'</td><td>'.esc_html(number_format_i18n($r->reach)).'</td><td>'.esc_html(number_format_i18n($r->clicks)).'</td><td>%'.esc_html(number_format_i18n($ctr,2)).'</td><td>'.esc_html(number_format_i18n($r->purchases,2)).'</td><td>'.esc_html(self::money($r->purchase_value)).'</td></tr>';}
        echo '</tbody></table></div>';
    }

    private function render_ga4_sources( $from, $to ) {
        global $wpdb;$t=self::table('ga4_daily');$rows=$wpdb->get_results($wpdb->prepare("SELECT source_name,medium_name,campaign_name,SUM(sessions) sessions,SUM(total_users) users,SUM(add_to_carts) carts,SUM(purchases) purchases,SUM(purchase_revenue) revenue FROM {$t} WHERE metric_date BETWEEN %s AND %s GROUP BY source_name,medium_name,campaign_name ORDER BY sessions DESC LIMIT 100",$from,$to));
        echo '<div class="mdgy-panel"><h2>GA4 Trafik Kaynakları</h2><table class="widefat striped mdgy-table"><thead><tr><th>Kaynak / Medium</th><th>Kampanya</th><th>Oturum</th><th>Kullanıcı</th><th>Sepete Ekleme</th><th>Satın Alma</th><th>Gelir</th></tr></thead><tbody>';
        if(!$rows){echo '<tr><td colspan="7">Henüz GA4 verisi yok.</td></tr>';} foreach($rows as $r){echo '<tr><td><strong>'.esc_html($r->source_name).' / '.esc_html($r->medium_name).'</strong></td><td>'.esc_html($r->campaign_name).'</td><td>'.esc_html(number_format_i18n($r->sessions)).'</td><td>'.esc_html(number_format_i18n($r->users)).'</td><td>'.esc_html(number_format_i18n($r->carts)).'</td><td>'.esc_html(number_format_i18n($r->purchases,2)).'</td><td>'.esc_html(self::money($r->revenue)).'</td></tr>';}
        echo '</tbody></table></div>';
    }

    private function render_instagram_panel() {
        global $wpdb;$t=self::table('instagram_daily');$r=$wpdb->get_row("SELECT * FROM {$t} ORDER BY metric_date DESC,id DESC LIMIT 1");
        echo '<div class="mdgy-panel"><h2>Instagram</h2>'; if(!$r){echo '<p>Henüz Instagram verisi yok.</p>';} else {echo '<div class="mdgy-grid">';$this->kpi('Takipçi',number_format_i18n($r->followers_count),'@'.$r->username);$this->kpi('Toplam Medya',number_format_i18n($r->media_count),'');$this->kpi('Senkron Günündeki Medya',number_format_i18n($r->posts_seen),'');$this->kpi('Beğeni',number_format_i18n($r->likes),'çekilen son medya örnekleri');$this->kpi('Yorum',number_format_i18n($r->comments),'');echo '</div>';}
        echo '</div>';
    }

    private function render_kommo_status_table( $from, $to ) {
        global $wpdb;$t=self::table('kommo_leads');$tz=wp_timezone();$f=(new DateTimeImmutable($from.' 00:00:00',$tz))->getTimestamp();$tt=(new DateTimeImmutable($to.' 23:59:59',$tz))->getTimestamp();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT pipeline_id,status_id,COUNT(*) cnt,SUM(price) total_price FROM {$t} WHERE updated_ts BETWEEN %d AND %d GROUP BY pipeline_id,status_id ORDER BY cnt DESC",$f,$tt));
        echo '<div class="mdgy-panel"><h2>Kommo Pipeline / Aşama</h2><table class="widefat striped"><thead><tr><th>Pipeline ID</th><th>Status ID</th><th>Lead</th><th>Lead Değeri</th></tr></thead><tbody>';
        if(!$rows){echo '<tr><td colspan="4">Henüz Kommo lead verisi yok.</td></tr>';} foreach($rows as $r){$special=((int)$r->status_id===142?'Kazanıldı':((int)$r->status_id===143?'Kaybedildi':''));echo '<tr><td>'.esc_html($r->pipeline_id).'</td><td>'.esc_html($r->status_id).' '.($special?'<span class="mdgy-tag">'.esc_html($special).'</span>':'').'</td><td>'.esc_html(number_format_i18n($r->cnt)).'</td><td>'.esc_html(self::money($r->total_price)).'</td></tr>';}
        echo '</tbody></table></div>';
    }

    private function render_attribution_table( $from, $to ) {
        global $wpdb;$t=self::table('attribution');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE DATE(paid_at) BETWEEN %s AND %s ORDER BY paid_at DESC LIMIT 100",$from,$to));
        echo '<div class="mdgy-panel"><h2>Reklam → Sipariş Atıf Zinciri</h2><table class="widefat striped mdgy-table"><thead><tr><th>Sipariş</th><th>Etkinlik</th><th>Kaynak</th><th>Kampanya</th><th>Tutar</th><th>Kommo Lead</th><th>Ödeme</th></tr></thead><tbody>';
        if(!$rows){echo '<tr><td colspan="7">Henüz UTM ile atfedilmiş yeni sipariş yok.</td></tr>';} foreach($rows as $r){
            $snapshot=array();
            if(function_exists('wc_get_order')){
                $o=wc_get_order($r->order_id);
                if($o){$snapshot=json_decode((string)$o->get_meta('_mdgy_event_snapshot_v1'),true);if(!is_array($snapshot)){$snapshot=array();}}
            }
            if(!$snapshot){$snapshot=$this->order_event_snapshot((int)$r->order_id);}
            $event_bits=array_filter(array($snapshot['city']??'', $snapshot['date']??'', $snapshot['session']??''));
            $event_text=$event_bits?implode(' · ',$event_bits):'—';
            $venue=(string)($snapshot['venue']??'');
            echo '<tr><td><strong>#'.esc_html($r->order_id).'</strong></td><td>'.esc_html($event_text).($venue?'<div class="mdgy-sub">'.esc_html($venue).'</div>':'').'</td><td>'.esc_html($r->source_name?:'direct').' / '.esc_html($r->medium_name).($r->fbclid?'<div class="mdgy-sub">fbclid mevcut</div>':'').'</td><td>'.esc_html($r->campaign_name).($r->campaign_id?'<div class="mdgy-sub">'.esc_html($r->campaign_id).'</div>':'').'</td><td>'.esc_html(self::money($r->order_total)).'</td><td>'.($r->kommo_lead_id?'#'.esc_html($r->kommo_lead_id):'—').'</td><td>'.esc_html($r->paid_at).'</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function render_sync_log() {
        global $wpdb;$t=self::table('sync_log');$rows=$wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 20");
        echo '<div class="mdgy-panel"><h2>Son Senkronizasyonlar</h2><table class="widefat striped"><thead><tr><th>Kaynak</th><th>Durum</th><th>Satır</th><th>Zaman</th><th>Mesaj</th></tr></thead><tbody>';
        if(!$rows){echo '<tr><td colspan="5">Henüz senkron kaydı yok.</td></tr>';} foreach($rows as $r){echo '<tr><td>'.esc_html($r->source_name).'</td><td>'.($r->status_name==='ok'?'<span class="mdgy-ok">OK</span>':'<span class="mdgy-bad">Hata</span>').'</td><td>'.esc_html($r->rows_count).'</td><td>'.esc_html($r->finished_at).'</td><td>'.esc_html($r->message).'</td></tr>';}
        echo '</tbody></table></div>';
    }
}
