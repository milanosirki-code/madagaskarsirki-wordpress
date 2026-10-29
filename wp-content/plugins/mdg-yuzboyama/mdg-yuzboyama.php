<?php
/**
 * Plugin Name: Madagaskar Yüz Boyama
 * Description: Sürekli açık yüz boyama ödemesi, tek kullanımlı kişi hakları ve V5 gelir bağlantısı.
 * Version: 1.0.3
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: Dünya Organizasyon
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/policy.php';

final class MDG_Yuzboyama {
    const VERSION = '1.0.3';
    const SKU = 'MDG-YUZBOYAMA-100';
    const CAP = 'manage_woocommerce';

    public static function boot() {
        register_activation_hook(__FILE__, array(__CLASS__, 'install'));
        add_action('init', function() { if (get_option('mdg_yb_version') !== self::VERSION) self::install(); }, 5);
        add_action('before_woocommerce_init', function() {
            if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
                Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        add_shortcode('mdg_yuzboyama', array(__CLASS__, 'shop'));
        add_shortcode('mdg_yuzboyama_kontrol', array(__CLASS__, 'staff'));
        add_action('wp_enqueue_scripts', function() {
            if (is_page(array('yuzboyama','yuzboyama-kontrol')) || (function_exists('is_order_received_page') && is_order_received_page())) self::assets();
        });
        add_action('template_redirect', array(__CLASS__, 'request'), 2);
        add_filter('wp_robots', array(__CLASS__, 'robots'));
        add_filter('wp_sitemaps_posts_query_args', array(__CLASS__, 'sitemap'), 10, 2);
        add_filter('woocommerce_is_checkout', array(__CLASS__, 'is_checkout'));
        add_filter('woocommerce_add_to_cart_validation', array(__CLASS__, 'validate_cart'), 99, 3);
        add_filter('woocommerce_coupon_is_valid_for_product', array(__CLASS__, 'coupon'), 99, 4);
        add_filter('woocommerce_available_payment_gateways', array(__CLASS__, 'gateways'), 99);
        add_action('woocommerce_check_cart_items', array(__CLASS__, 'check_cart'));
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'order_meta'), 20, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array(__CLASS__, 'order_meta'), 20, 2);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'check_cart'));
        add_action('woocommerce_payment_complete', array(__CLASS__, 'paid'), 30);
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'status_changed'), 30, 4);
        add_action('woocommerce_order_refunded', array(__CLASS__, 'paid'), 30);
        add_action('woocommerce_thankyou', array(__CLASS__, 'receipt'), 5);
        add_action('woocommerce_order_details_after_order_table', array(__CLASS__, 'order_receipt'));
        add_action('admin_menu', function() {
            add_submenu_page('woocommerce', 'Yüz Boyama', 'Yüz Boyama', self::CAP, 'mdg-yuzboyama', array(__CLASS__, 'admin'));
        });
        add_action('rest_api_init', array(__CLASS__, 'routes'));
    }

    private static function table($name = 'orders') { global $wpdb; return $wpdb->prefix . 'mdg_yb_' . $name; }
    public static function product() { return function_exists('wc_get_product_id_by_sku') ? (int) wc_get_product_id_by_sku(self::SKU) : 0; }
    private static function url() { return home_url('/yuzboyama/'); }
    private static function can_staff() { return current_user_can(self::CAP); }
    private static function money($n) { return number_format_i18n($n, 2) . ' TL'; }
    /** VAT is included in the agreed gross service price; never added on top. */
    public static function vat_breakdown($gross) {
        $total = max(0, (int)round((float)$gross * 100));
        $base = (int)round($total * 100 / 120);
        return array('rate'=>20, 'base'=>$base / 100, 'vat'=>($total-$base) / 100, 'total'=>$total / 100);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $t = self::table();
        dbDelta("CREATE TABLE $t (
            order_id bigint unsigned NOT NULL,
            code varchar(32) NOT NULL,
            customer_name varchar(160) NOT NULL DEFAULT '',
            used_qty int unsigned NOT NULL DEFAULT 0,
            event_id bigint unsigned NOT NULL DEFAULT 0,
            event_uuid varchar(64) NOT NULL DEFAULT '',
            paid_at datetime NOT NULL,
            finance_id bigint unsigned NOT NULL DEFAULT 0,
            finance_error varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (order_id),
            UNIQUE KEY code (code),
            KEY paid_at (paid_at)
        ) ENGINE=InnoDB $c;");
        $t = self::table('uses');
        dbDelta("CREATE TABLE $t (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint unsigned NOT NULL,
            request_key varchar(36) NOT NULL,
            qty int unsigned NOT NULL,
            staff_id bigint unsigned NOT NULL,
            used_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_key (request_key),
            KEY order_id (order_id)
        ) ENGINE=InnoDB $c;");
        update_option('mdg_yb_version', self::VERSION, false);
    }

    private static function cart_has_yb() {
        if (!function_exists('WC') || !WC()->cart || !self::product()) return false;
        foreach (WC()->cart->get_cart() as $item) if ((int)$item['product_id'] === self::product()) return true;
        return false;
    }
    private static function only_yb() {
        if (!self::cart_has_yb()) return false;
        foreach (WC()->cart->get_cart() as $item) if ((int)$item['product_id'] !== self::product()) return false;
        return true;
    }
    public static function is_checkout($value) { return $value || (is_page('yuzboyama') && isset($_GET['odeme'])); }
    public static function robots($r) {
        if (is_page(array('yuzboyama', 'yuzboyama-kontrol')) || (function_exists('is_product') && is_product() && get_the_ID() === self::product())) {
            $r['noindex'] = true; $r['nofollow'] = true; unset($r['index']);
        }
        return $r;
    }
    public static function sitemap($args, $type) {
        if ($type === 'page') {
            $ids = array();
            foreach (array('yuzboyama','yuzboyama-kontrol') as $slug) { $p = get_page_by_path($slug); if ($p) $ids[] = $p->ID; }
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? array(), $ids);
        }
        return $args;
    }
    public static function validate_cart($valid, $id, $qty) {
        $pid = self::product();
        if (!$pid || !WC()->cart) return $valid;
        $adding_yb = (int)$id === $pid;
        foreach (WC()->cart->get_cart() as $item) {
            if ($adding_yb !== ((int)$item['product_id'] === $pid)) {
                wc_add_notice('Yüz boyama ve gösteri biletleri ayrı ödenmelidir. Mevcut sepetinizi tamamlayın veya düzenleyin.', 'error');
                return false;
            }
        }
        if ($adding_yb && ($qty < 1 || $qty > 50 || (int)$qty != $qty)) return false;
        return $valid;
    }
    public static function coupon($valid, $product, $coupon, $values) {
        return (int)$product->get_id() === self::product() ? false : $valid;
    }
    public static function gateways($gateways) {
        if (self::only_yb() || (function_exists('is_checkout_pay_page') && is_checkout_pay_page() && self::order_is_yb(wc_get_order(absint(get_query_var('order-pay')))))) return isset($gateways['paytr_payment_gateway']) ? array('paytr_payment_gateway' => $gateways['paytr_payment_gateway']) : array();
        return $gateways;
    }
    public static function check_cart() {
        if (!self::cart_has_yb()) return;
        if (!self::only_yb()) { wc_add_notice('Yüz boyama ayrı bir sipariş olarak ödenmelidir.', 'error'); return; }
        $qty = 0;
        foreach (WC()->cart->get_cart() as $item) $qty += (int)$item['quantity'];
        if ($qty < 1 || $qty > 50 || (int)round((float)WC()->cart->get_total('edit') * 100) !== $qty * 10000) {
            wc_add_notice('Yüz boyama toplamı kişi başı 100 TL olmalıdır. Lütfen sepeti kontrol edin.', 'error');
        }
    }

    public static function request() {
        if (!is_page(array('yuzboyama','yuzboyama-kontrol'))) return;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        if (is_page('yuzboyama-kontrol') && !is_user_logged_in()) { auth_redirect(); exit; }
        if (is_page('yuzboyama') && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['mdg_yb_buy'])) {
            check_admin_referer('mdg_yb_buy', 'mdg_yb_nonce');
            $q = filter_var(wp_unslash($_POST['quantity'] ?? ''), FILTER_VALIDATE_INT, array('options'=>array('min_range'=>1,'max_range'=>50)));
            if (!$q || !self::product()) { wc_add_notice('Geçerli bir kişi sayısı seçin.', 'error'); wp_safe_redirect(self::url()); exit; }
            // Preserve existing ticket carts. A mixed basket is never silently cleared.
            if (WC()->cart && !WC()->cart->is_empty() && !self::only_yb()) { wc_add_notice('Sepetinizde gösteri bileti var. Önce mevcut siparişinizi tamamlayın.', 'error'); wp_safe_redirect(self::url()); exit; }
            if (self::only_yb()) {
                $cart = WC()->cart->get_cart(); $key = array_key_first($cart);
                WC()->cart->set_quantity($key, $q);
            } else {
                WC()->cart->add_to_cart(self::product(), $q);
            }
            if (self::only_yb()) wp_safe_redirect(add_query_arg('odeme', '1', self::url())); else wp_safe_redirect(self::url());
            exit;
        }
    }

    public static function shop() {
        if (!function_exists('WC') || !self::product()) return '<p>Yüz boyama ödeme sayfası hazırlanıyor.</p>';
        self::assets();
        if (isset($_GET['odeme']) && self::only_yb()) return '<div class="mdg-yb-checkout"><h2>Yüz Boyama Ödemesi</h2><p>Kişi başı 100 TL. Ödeme sonrası işlem kodunuzu görevliye gösterin.</p>' . do_shortcode('[woocommerce_checkout]') . '</div>';
        ob_start(); wc_print_notices(); ?>
        <section class="mdg-yb-card">
          <p class="mdg-yb-kicker">MADAGASKAR SİRKİ · ETKİNLİK ALANI</p>
          <h2>Biraz renk,<br>kocaman bir gülümseme.</h2>
          <p>Yüz boyama ücretinizi burada kartınızla ödeyin. Ödeme sonrası işlem kodunuzu yüz boyama görevlisine gösterin.</p>
          <div class="mdg-yb-price"><strong>100 TL</strong><span> / kişi</span></div>
          <form method="post" action="<?php echo esc_url(self::url()); ?>">
          <?php wp_nonce_field('mdg_yb_buy', 'mdg_yb_nonce'); ?>
          <label for="mdg-yb-qty">Kaç kişi için ödeme yapacaksınız?</label>
          <div class="mdg-yb-quantity"><button type="button" data-yb-step="-1" aria-label="Kişi sayısını azalt">−</button><input id="mdg-yb-qty" type="number" inputmode="numeric" min="1" max="50" step="1" name="quantity" value="1" required><button type="button" data-yb-step="1" aria-label="Kişi sayısını artır">+</button></div>
          <p class="mdg-yb-total">Toplam <strong id="mdg-yb-total" aria-live="polite">100 TL</strong></p>
          <button class="mdg-yb-primary" name="mdg_yb_buy" value="1" type="submit">Kartla ödemeye geç</button>
          </form><p class="mdg-yb-small">Bu ödeme yalnız yüz boyama hizmeti içindir. Gösteri giriş bileti değildir. Birden fazla kişi için tek ödeme yapabilirsiniz.</p>
        </section>
        <?php return ob_get_clean();
    }

    public static function assets() {
        static $done = false; if ($done) return; $done = true;
        wp_enqueue_style('mdg-yb', plugins_url('style.css', __FILE__), array(), self::VERSION);
        wp_enqueue_script('mdg-yb', plugins_url('app.js', __FILE__), array(), self::VERSION, true);
        wp_localize_script('mdg-yb', 'MDGYB', array('api'=>rest_url('mdg-yuzboyama/v1/'), 'nonce'=>wp_create_nonce('wp_rest')));
    }

    public static function order_meta($order, $data = null) {
        $items = $order->get_items(); $qty = 0;
        foreach ($items as $item) {
            if ((int)$item->get_product_id() !== self::product()) return;
            $qty += (int)$item->get_quantity();
        }
        if (!$qty) return;
        $name = sanitize_text_field($order->get_formatted_billing_full_name());
        if ($name === 'Yüz Boyama Müşterisi') $name = '';
        $order->update_meta_data('_mdg_yb_customer_name', self::clean_name($name));
        $event = self::choose_event(wp_date('Y-m-d'));
        $order->update_meta_data('_mdg_yb', 'yes');
        $order->update_meta_data('_mdg_yb_event_id', $event ? (int)$event->id : 0);
        $order->update_meta_data('_mdg_yb_event_uuid', $event ? (string)$event->event_uuid : '');
        $order->update_meta_data('_mdg_yb_event_date', wp_date('Y-m-d'));
        $vat = self::vat_breakdown($order->get_total());
        $order->update_meta_data('_mdg_yb_vat_rate', $vat['rate']);
        $order->update_meta_data('_mdg_yb_vat_base', $vat['base']);
        $order->update_meta_data('_mdg_yb_vat_amount', $vat['vat']);
        $order->update_meta_data('_mdg_yb_vat_included', 'yes');
    }
    private static function order_is_yb($order) {
        if (!$order || ($order->get_meta('_mdg_yb') !== 'yes' && $order->get_meta('_mdg_yb_pos') !== 'yes')) return false;
        foreach ($order->get_items() as $item) if ((int)$item->get_product_id() !== self::product()) return false;
        return count($order->get_items()) > 0;
    }
    public static function capacity($order) {
        if (!self::order_is_yb($order)) return 0;
        $q = 0; $rq = 0;
        foreach ($order->get_items() as $id=>$item) { $q += (int)$item->get_quantity(); $rq += abs((int)$order->get_qty_refunded_for_item($id)); }
        $refund = (int)round($order->get_total_refunded() * 100);
        return MDG_YB_Policy::capacity($order->is_paid() && $order->get_payment_method() === 'paytr_payment_gateway' && (bool)$order->get_date_paid() && strpos((string)$order->get_meta('paytr_order_id'),'PAYTRWOO') !== false, $order->get_currency(), $q, (int)round($order->get_total()*100), $refund, $rq, $refund > 0 && $rq === 0);
    }
    private static function row($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE order_id=%d', $id)); }

    public static function paid($order_id) {
        global $wpdb;
        $order = wc_get_order($order_id);
        if (!self::order_is_yb($order)) return;
        if ($order->get_meta('_mdg_yb') !== 'yes') { self::order_meta($order); $order->save(); }
        $row = self::row($order_id);
        if (!$row && self::capacity($order) > 0) {
            $d = $order->get_date_paid();
            $date = wp_date('Y-m-d', $d->getTimestamp());
            $event = self::choose_event($date);
            // Assignment uses the actual paid day, rather than an earlier abandoned checkout day.
            $data = array('order_id'=>$order_id,'code'=>strtoupper(bin2hex(random_bytes(8))), 'customer_name'=>self::clean_name($order->get_meta('_mdg_yb_customer_name')), 'used_qty'=>0, 'event_id'=>$event ? $event->id : 0, 'event_uuid'=>$event ? $event->event_uuid : '', 'paid_at'=>wp_date('Y-m-d H:i:s', $d->getTimestamp()));
            $wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.self::table().' (order_id,code,customer_name,used_qty,event_id,event_uuid,paid_at) VALUES (%d,%s,%s,%d,%d,%s,%s)', array_values($data)));
        }
        if (self::row($order_id)) self::finance($order_id);
    }
    /** Only expose the canonical, verified code; never the legacy four-digit POS code. */
    public static function payment_code($order) {
        if (!self::order_is_yb($order) || self::capacity($order) < 1) return '';
        $row = self::row($order->get_id());
        return $row ? (string)$row->code : '';
    }
    public static function status_changed($id, $old, $new, $order) { self::paid($id); }

    public static function receipt($id) {
        $order = wc_get_order($id);
        if (!self::order_is_yb($order)) return;
        $key = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
        if (!self::can_staff() && !hash_equals((string)$order->get_order_key(), $key) && !(get_current_user_id() && (int)$order->get_customer_id() === get_current_user_id())) return;
        self::assets();
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        self::paid($id); $row = self::row($id); $capacity = self::capacity($order);
        echo '<section class="mdg-yb-card mdg-yb-receipt"><h2>'.esc_html($capacity ? 'Ödemeniz alındı' : 'Ödeme / iade durumunu kontrol edin').'</h2>';
        if ($row && $capacity) {
            $vat = self::vat_breakdown($order->get_total());
            $people = 0; foreach ($order->get_items() as $item) $people += (int)$item->get_quantity();
            $paid_date = $order->get_date_paid();
            $date = $paid_date ? wp_date('d.m.Y H:i', $paid_date->getTimestamp()) : $row->paid_at;
            echo '<p><strong>Yüz Boyama Ödeme Belgesi</strong></p>';
            if (!empty($row->customer_name)) echo '<p>'.esc_html($row->customer_name).'</p>';
            echo '<table style="width:100%;border-collapse:collapse;text-align:left"><tbody>';
            foreach (array('İşlem tarihi'=>$date, 'Kişi sayısı'=>$people.' kişi', 'KDV hariç tutar'=>self::money($vat['base']), 'KDV (%20)'=>self::money($vat['vat']), 'Toplam tutar (KDV dahil)'=>self::money($vat['total'])) as $label=>$value) {
                echo '<tr><th scope="row" style="padding:10px 0;border-bottom:1px solid #d8d5c5">'.esc_html($label).'</th><td style="padding:10px 0;border-bottom:1px solid #d8d5c5;text-align:right">'.esc_html($value).'</td></tr>';
            }
            echo '</tbody></table><p>Görevliye bu işlem kodunu gösterin:</p><strong class="mdg-yb-code">'.esc_html($row->code).'</strong><p>Kullanılan: '.(int)$row->used_qty.' · Kalan: '.max(0,$capacity-(int)$row->used_qty).'</p><p class="mdg-yb-small">Sipariş #'.(int)$id.'<br>Görevli, kodu kendi kontrol ekranından doğrular.</p>';
        } else {
            echo '<p>Ödeme henüz doğrulanmadıysa kısa bir süre sonra sayfayı yenileyin. Görevli onayı için ödeme sistemde doğrulanmış olmalıdır.</p>';
        }
        echo '</section>';
    }
    public static function order_receipt($order) { if (!function_exists('is_order_received_page') || !is_order_received_page()) self::receipt($order->get_id()); }

    public static function events($date = '') {
        global $wpdb;
        if (!class_exists('MDG_DB') || !class_exists('MDG_V5_Finance') || !class_exists('MDG_V5_Finance_Identity')) return array();
        $rows = $wpdb->get_results('SELECT *,public_uuid AS event_uuid FROM '.MDG_DB::table('events').' ORDER BY id DESC LIMIT 1000');
        return array_values(array_filter((array)$rows, function($e) use ($date) {
            global $wpdb;
            if (in_array(strtolower($e->status ?? ''), array('cancelled','canceled','trash','draft'), true) || empty($e->event_uuid) || ($date && MDG_V5_Finance::event_date($e) !== $date) || !MDG_V5_Finance_Identity::valid($e->id.'|'.$e->event_uuid)) return false;
            if (class_exists('MMC_MDG_Bridge_Service')) {
                $states = $wpdb->get_col($wpdb->prepare('SELECT p.status FROM '.MMC_MDG_Bridge_Service::table().' b INNER JOIN '.$wpdb->prefix.'mmc_programs p ON p.id=b.program_id WHERE b.mdg_event_id=%d AND b.is_active=1',$e->id));
                foreach ((array)$states as $state) if (in_array(strtolower($state),array('cancelled','canceled','draft','trash'),true)) return false;
            }
            return true;
        }));
    }
    private static function choose_event($date) {
        $events = self::events($date); $selected = (int)get_option('mdg_yb_stand_event', 0);
        foreach ($events as $e) if ((int)$e->id === $selected) return $e;
        return count($events) === 1 ? reset($events) : null;
    }

    /** Own-source V5 adapter. Never changes ticket mappings or another source's rows. */
    public static function finance($order_id) {
        global $wpdb;
        $lock = 'mdg_yb_fin_'.$order_id;
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,2)', $lock)) !== 1) return;
        try {
            $row = self::row($order_id); $order = wc_get_order($order_id);
            if (!$row || !$order || !function_exists('mdg_ai_v5_income_create')) return;
            $ref = 'woo_yuzboyama_'.$order_id; $channel = 'Yüz Boyama / PayTR';
            $table = $wpdb->prefix.'mdg_v5_incomes'; $history = $wpdb->prefix.'mdg_v5_finance_history';
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE source_ref=%s AND channel=%s LIMIT 1", $ref, $channel));
            $net = $order->is_paid() ? max(0, round($order->get_total()-$order->get_total_refunded(), 2)) : 0;
            $data = array('scope'=>$row->event_id ? 'program':'general','event_id'=>(int)$row->event_id,'event_uuid'=>$row->event_uuid,'category'=>'Yüz Boyama','channel'=>$channel,'description'=>'Yüz Boyama · Sipariş #'.$order_id,'gross_amount'=>$net,'commission_amount'=>0,'net_amount'=>$net,'ticket_count'=>0,'income_date'=>substr($row->paid_at,0,10),'collection_status'=>'collected','payment_method'=>'Kredi Kartı / POS','source_ref'=>$ref,'notes'=>'Otomatik yüz boyama kaydı. Satış: '.self::money($order->get_total()).'; iade: '.self::money($order->get_total_refunded()).'. PayTR komisyonu ve banka aktarımı ayrıca mutabakat edilir.');
            if (!$existing && $net > 0) {
                $result = mdg_ai_v5_income_create($data);
                if (is_wp_error($result)) { $wpdb->update(self::table(), array('finance_error'=>substr($result->get_error_message(),0,240)), array('order_id'=>$order_id)); return; }
                $wpdb->update(self::table(), array('finance_id'=>(int)$result['income_id'],'finance_error'=>''), array('order_id'=>$order_id));
            } elseif ($existing) {
                $update = array_intersect_key($data, array_flip(array('scope','event_id','category','description','gross_amount','net_amount','notes')));
                $different = false; foreach ($update as $k=>$v) if ((string)$existing->$k !== (string)$v && !in_array($k,array('gross_amount','net_amount'),true)) $different=true;
                if ((float)$existing->gross_amount !== (float)$net || (float)$existing->net_amount !== (float)$net) $different=true;
                if ($different) {
                    // Do not overwrite a finance record that has acquired settlement/payment tracking.
                    foreach (array('payments','settlements') as $suffix) {
                        $tracking = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'mdg_v5_'.$suffix.' WHERE record_type=%s AND record_id=%d','income',$existing->id));
                        if ($tracking === null || (int)$tracking > 0) throw new RuntimeException('Finans takibi bulunan kayıt için manuel mutabakat gerekli.');
                    }
                    $wpdb->query('START TRANSACTION');
                    $update['updated_at'] = current_time('mysql');
                    if (false === $wpdb->update($table,$update,array('id'=>$existing->id,'source_ref'=>$ref,'channel'=>$channel))) throw new RuntimeException('Finans güncellenemedi.');
                    $after = array_merge((array)$existing,$update);
                    if (false === $wpdb->insert($history,array('record_type'=>'income','record_id'=>$existing->id,'operation'=>'yb_sync','before_data'=>wp_json_encode($existing),'after_data'=>wp_json_encode($after),'reason'=>'Yüz boyama kaynak siparişi / programı güncellendi.','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')))) throw new RuntimeException('Finans işlem geçmişi yazılamadı.');
                    $wpdb->query('COMMIT');
                }
                $wpdb->update(self::table(),array('finance_id'=>$existing->id,'finance_error'=>''),array('order_id'=>$order_id));
            }
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            $wpdb->update(self::table(),array('finance_error'=>substr($e->getMessage(),0,240)),array('order_id'=>$order_id));
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }

    public static function routes() {
        register_rest_route('mdg-yuzboyama/v1','/status',array('methods'=>'GET','permission_callback'=>function(){return self::can_staff();},'callback'=>function(){global $wpdb;$engines=array();foreach(array('orders','uses') as $suffix){$s=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',self::table($suffix)));$engines[$suffix]=$s?$s->Engine:'missing';}return array('version'=>self::VERSION,'product_id'=>self::product(),'engines'=>$engines,'finance_adapter'=>function_exists('mdg_ai_v5_income_create'),'canonical_identity'=>class_exists('MDG_V5_Finance_Identity'),'today_candidates'=>count(self::events(wp_date('Y-m-d'))),'orders'=>(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table()),'unassigned'=>(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table().' WHERE event_id=0'));}));
        register_rest_route('mdg-yuzboyama/v1','/lookup',array('methods'=>'POST','permission_callback'=>function(){return self::can_staff();},'callback'=>array(__CLASS__,'lookup')));
        register_rest_route('mdg-yuzboyama/v1','/search',array('methods'=>'POST','permission_callback'=>function(){return self::can_staff();},'callback'=>array(__CLASS__,'search')));
        register_rest_route('mdg-yuzboyama/v1','/redeem',array('methods'=>'POST','permission_callback'=>function(){return self::can_staff();},'callback'=>array(__CLASS__,'redeem')));
    }
    public static function lookup($request) {
        global $wpdb;
        $code = strtoupper(sanitize_text_field($request['code'] ?? ''));
        if (!preg_match('/^[A-F0-9]{16}$/D',$code)) return new WP_Error('invalid_code','16 haneli işlem kodunu kontrol edin.',array('status'=>400));
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE code=%s',$code));
        if (!$row) return new WP_Error('not_found','İşlem bulunamadı.',array('status'=>404));
        $order = wc_get_order($row->order_id); $capacity = self::capacity($order);
        return array('order_id'=>(int)$row->order_id,'code'=>$code,'customer_name'=>(string)($row->customer_name ?? ''),'event_id'=>(int)$row->event_id,'paid'=>$capacity>0,'qty'=>$capacity,'used'=>(int)$row->used_qty,'remaining'=>max(0,$capacity-$row->used_qty),'amount'=>$order ? self::money($order->get_total()):'','paid_at'=>$row->paid_at,'request_key'=>wp_generate_uuid4());
    }
    public static function redeem($request) {
        global $wpdb;
        $key = sanitize_text_field($request['request_key'] ?? '');
        $qty = filter_var($request['qty'] ?? 0,FILTER_VALIDATE_INT);
        if (!preg_match('/^[a-f0-9-]{36}$/D',$key) || !$qty || $qty < 1) return new WP_Error('invalid_input','İşlem bilgisi geçersiz.',array('status'=>400));
        $info = self::lookup($request); if (is_wp_error($info)) return $info;
        $lock = 'mdg_yb_use_'.$info['order_id'];
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,3)',$lock)) !== 1) return new WP_Error('busy','İşlem sürüyor. Tekrar kontrol edin.',array('status'=>409));
        try {
            $wpdb->query('START TRANSACTION');
            $prior = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('uses').' WHERE request_key=%s',$key));
            if ($prior) {
                if ((int)$prior->order_id !== $info['order_id'] || (int)$prior->qty !== $qty) throw new RuntimeException('İstek kodu başka işlemde kullanılmış.');
            } else {
                $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE order_id=%d FOR UPDATE',$info['order_id']));
                $capacity = self::capacity(wc_get_order($info['order_id']));
                if (!MDG_YB_Policy::can_redeem($capacity,(int)$row->used_qty,$qty)) throw new RuntimeException('Ödeme doğrulanmadı veya yeterli kullanılmamış hak yok.');
                if (false === $wpdb->insert(self::table('uses'),array('order_id'=>$row->order_id,'request_key'=>$key,'qty'=>$qty,'staff_id'=>get_current_user_id(),'used_at'=>current_time('mysql')))) throw new RuntimeException('Kullanım kaydı yazılamadı.');
                if (false === $wpdb->update(self::table(),array('used_qty'=>$row->used_qty+$qty),array('order_id'=>$row->order_id))) throw new RuntimeException('Kişi hakkı güncellenemedi.');
            }
            $wpdb->query('COMMIT');
            return self::lookup($request);
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK'); return new WP_Error('not_redeemed',$e->getMessage(),array('status'=>409));
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }

    public static function clean_name($name) {
        $name = sanitize_text_field(is_scalar($name) ? (string)$name : '');
        return function_exists('mb_substr') ? mb_substr($name,0,160) : substr($name,0,160);
    }
    public static function day_bounds($date) {
        if (!is_string($date)) return false;
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',(string)$date,$m) || !checkdate((int)$m[2],(int)$m[3],(int)$m[1])) return false;
        return array($date.' 00:00:00',(new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d').' 00:00:00');
    }
    public static function search($request) {
        global $wpdb;
        $query = trim(sanitize_text_field($request['query'] ?? ''));
        if (preg_match('/^[A-Fa-f0-9]{16}$/D',$query)) {
            $info = self::lookup(array('code'=>$query));
            return is_wp_error($info) ? $info : array('matches'=>array($info),'more'=>false);
        }
        $bounds = self::day_bounds($request['date'] ?? wp_date('Y-m-d'));
        if (!$bounds || strlen($query) < 2 || strlen($query) > 640) return new WP_Error('invalid_search','Arama için geçerli bir tarih ve en az iki harf girin.',array('status'=>400));
        $codes = $wpdb->get_col($wpdb->prepare('SELECT code FROM '.self::table().' WHERE paid_at >= %s AND paid_at < %s AND customer_name LIKE %s ORDER BY paid_at DESC LIMIT 21',$bounds[0],$bounds[1],'%'.$wpdb->esc_like($query).'%'));
        $more = count($codes) > 20; $matches = array();
        foreach (array_slice($codes,0,20) as $code) {
            $info = self::lookup(array('code'=>$code));
            if (!is_wp_error($info)) $matches[] = $info;
        }
        return array('matches'=>$matches,'more'=>$more);
    }
    /** Selecting today's stand also allocates earlier unassigned receipts from that day. */
    private static function assign_day($event) {
        global $wpdb;
        $date = MDG_V5_Finance::event_date($event);
        $bounds = self::day_bounds($date);
        if (!$bounds || $date !== wp_date('Y-m-d')) return;
        $ids = $wpdb->get_col($wpdb->prepare('SELECT order_id FROM '.self::table().' WHERE event_id=0 AND paid_at >= %s AND paid_at < %s',$bounds[0],$bounds[1]));
        foreach ($ids as $id) {
            if ($wpdb->update(self::table(),array('event_id'=>$event->id,'event_uuid'=>$event->event_uuid),array('order_id'=>$id,'event_id'=>0))) self::finance((int)$id);
        }
    }
    public static function staff() {
        if (!self::can_staff()) return '<p>Bu ekran için WooCommerce yönetim yetkisi olan görevli hesabıyla giriş yapın.</p>';
        self::assets();
        $date = wp_date('Y-m-d'); $event = self::choose_event($date);
        $label = $event ? MDG_V5_Finance::event_name($event) : 'Bugünün stand programı seçilmemiş';
        return '<section class="mdg-yb-card"><h2>Yüz Boyama · Personel Kontrolü</h2><p><strong>Etkinlik:</strong> '.esc_html($label).'</p><p>İşlem koduyla veya ödeme yapan kişinin adıyla arayın. İsim aramasında ödeme gününü seçin. İsmi girilmeyen ödemeler kodla bulunur.</p><form id="mdg-yb-lookup"><label for="mdg-yb-code">İşlem kodu veya ad soyad</label><input id="mdg-yb-code" name="code" autocomplete="off" maxlength="160" required><label for="mdg-yb-date">Ödeme günü (isim araması)</label><input id="mdg-yb-date" name="date" type="date" value="'.esc_attr($date).'" required><button class="mdg-yb-primary">Ödemeyi kontrol et</button></form><div id="mdg-yb-result" aria-live="polite"></div><p class="mdg-yb-small">Yalnız doğrulanmış kalan kişi haklarını kullanın. Aynı isimle birden çok ödeme bulunursa işlem kodu ve saatini karşılaştırın.</p></section>';
    }

    public static function admin() {
        if (!self::can_staff()) return;
        global $wpdb;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('mdg_yb_admin');
            if (isset($_POST['stand_event'])) {
                $id = absint($_POST['stand_event']); $valid = !$id;
                foreach (self::events() as $e) if ((int)$e->id === $id) $valid = true;
                if ($valid) {
                    update_option('mdg_yb_stand_event',$id,false);
                    foreach (self::events(wp_date('Y-m-d')) as $event) if ((int)$event->id === $id) self::assign_day($event);
                }
            }
            if (isset($_POST['assign_order'])) {
                $id = absint($_POST['assign_order']); $event = absint($_POST['event']); $row = self::row($id);
                if ($row) foreach (self::events(substr($row->paid_at,0,10)) as $e) if ((int)$e->id === $event) {
                    $wpdb->update(self::table(),array('event_id'=>$e->id,'event_uuid'=>$e->event_uuid),array('order_id'=>$id)); self::finance($id);
                }
            }
            if (isset($_POST['retry'])) foreach ($wpdb->get_col('SELECT order_id FROM '.self::table().' ORDER BY paid_at DESC LIMIT 100') as $id) self::finance((int)$id);
        }
        $date = sanitize_text_field(wp_unslash($_GET['date'] ?? wp_date('Y-m-d')));
        $bounds = self::day_bounds($date);
        if (!$bounds) { $date=wp_date('Y-m-d'); $bounds=self::day_bounds($date); }
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table().' WHERE paid_at >= %s AND paid_at < %s ORDER BY paid_at DESC', $bounds[0],$bounds[1]));
        $total=0;$qty=0;$used=0;
        foreach ($rows as $r) { $o=wc_get_order($r->order_id); if ($o && $o->is_paid()) $total+=max(0,$o->get_total()-$o->get_total_refunded()); $qty+=self::capacity($o);$used+=$r->used_qty; }
        echo '<div class="wrap"><h1>Yüz Boyama</h1><p><a href="'.esc_url(self::url()).'" target="_blank">Müşteri ödeme sayfası</a> · <a href="'.esc_url(home_url('/yuzboyama-kontrol/')).'" target="_blank">Personel kontrol ekranı</a></p><p>100 TL / kişi · '.esc_html($date).' · Tahsilat (iade sonrası, PayTR komisyonu öncesi): <strong>'.esc_html(self::money($total)).'</strong> · Hak: '.$qty.' · Kullanılan: '.$used.'</p>';
        echo '<form method="get"><input type="hidden" name="page" value="mdg-yuzboyama"><input type="date" name="date" value="'.esc_attr($date).'"><button class="button">Günü göster</button></form><hr><form method="post">';wp_nonce_field('mdg_yb_admin');
        echo '<label>Stand programı <select name="stand_event"><option value="0">O gün tek program varsa otomatik; yoksa atanmamış</option>';
        foreach (self::events() as $e) echo '<option value="'.(int)$e->id.'" '.selected(get_option('mdg_yb_stand_event',0),$e->id,false).'>'.esc_html(MDG_V5_Finance::event_date($e).' · '.MDG_V5_Finance::event_name($e)).'</option>';
        echo '</select></label> <button class="button">Kaydet</button><p>Seçim yalnız ödeme günüyle aynı tarihteyse uygulanır; başka günün programına satış yazılmaz. Bugünün programını seçtiğinizde bugün daha önce atanmamış yüz boyama gelirleri de bu programa eklenir. Programı olmayan satışlar genel gelirde kalır.</p></form><table class="widefat striped"><thead><tr><th>Sipariş / saat</th><th>Ödeme / hak</th><th>Program</th><th>Finans</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $o=wc_get_order($r->order_id);$cap=self::capacity($o);
            echo '<tr><td>#'.(int)$r->order_id.'<br>'.esc_html($r->paid_at).'</td><td>'.esc_html($o ? wc_get_order_status_name($o->get_status()):'Sipariş yok').' · '.esc_html($o ? self::money($o->get_total()):'').'<br>Kullanılan '.$r->used_qty.' / Kalan '.max(0,$cap-$r->used_qty).'</td><td>';
            if ($r->event_id) echo 'Program #'.(int)$r->event_id;
            else {
                echo 'Atanmamış';$candidates=self::events(substr($r->paid_at,0,10));
                if ($candidates) { echo '<form method="post">';wp_nonce_field('mdg_yb_admin');echo '<input type="hidden" name="assign_order" value="'.(int)$r->order_id.'"><select name="event">';foreach($candidates as $e)echo '<option value="'.(int)$e->id.'">'.esc_html(MDG_V5_Finance::event_name($e)).'</option>';echo '</select><button class="button">Programa ata</button></form>'; }
            }
            echo '</td><td>'.($r->finance_error ? esc_html($r->finance_error):($r->finance_id ? 'V5 #'.(int)$r->finance_id:'Aktarım bekliyor')).'</td></tr>';
        }
        if (!$rows) echo '<tr><td colspan="4">Bu gün için yüz boyama tahsilatı yok.</td></tr>';
        echo '</tbody></table><form method="post">';wp_nonce_field('mdg_yb_admin');echo '<p><button class="button" name="retry" value="1">Son 100 yüz boyama kaydını finansla eşitle</button></p></form></div>';
    }
}
MDG_Yuzboyama::boot();
