<?php
/** Süper 12 yayın denetimi: satıştaki tüm seanslarda otomatik açık, genel ve seans bazlı durdurma. Ödeme veya satış verisi yazmaz. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MS12_Pilot {
    public const SESSION = 309;
    public const EVENT = 59;
    public static function live_site(): bool {
        return function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production'
            && in_array(home_url('/'), ['https://madagaskarsirki.com/', 'https://www.madagaskarsirki.com/'], true);
    }
    public static function checkout_compatible(): bool {
        if (!function_exists('wc_get_page_id') || !function_exists('has_block')) return false;
        $page = get_post(wc_get_page_id('checkout'));
        if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') return false;
        $body = (string)$page->post_content;
        return (int)$page->ID === 1251 && has_block('woocommerce/checkout', $body)
            && strpos($body, 'wp-block-tickera-checkout-fields') !== false;
    }
    public static function dependencies(): bool {
        return self::dependency_blocker() === null;
    }

    public static function dependency_blocker(): ?string {
        if (!self::live_site()) return 'Canlı site ve ortam doğrulanamadı.';
        if (!self::checkout_compatible()) return 'WooCommerce Blocks / Tickera ödeme şablonu uyuşmuyor.';
        if (!defined('WC_VERSION') || WC_VERSION !== '11.3.1') return 'WooCommerce sürümü doğrulanan sürümle uyuşmuyor.';
        if (!defined('MDG_BILET_VERSION') || MDG_BILET_VERSION !== '3.6.3-ticket-invalidation-dry-run') return 'Madagaskar biletleme sürümü uyuşmuyor.';
        if (!class_exists('MDG_Bilet_Yonetimi_V4') || MDG_Bilet_Yonetimi_V4::VERSION !== '4.0.15-transition') return 'Madagaskar Bilet Yönetimi V4 sürümü uyuşmuyor.';
        if (!class_exists('Paytr_Payment_Gateway')) return 'PayTR ödeme eklentisi yüklenmemiş.';
        if (!class_exists('TC_WooCommerce_Bridge') || !class_exists('\\Tickera\\TC')) return 'Tickera veya WooCommerce Bridge yüklenmemiş.';
        if (!function_exists('get_woocommerce_currency') || get_woocommerce_currency() !== 'TRY') return 'WooCommerce para birimi TRY değil.';
        if (!function_exists('wc_tax_enabled') || wc_tax_enabled()) return 'WooCommerce vergi yapılandırması doğrulanan ayarla uyuşmuyor.';
        return null;
    }

    /** Genel anahtar: varsayılan AÇIK. Yalnız 'off' yazılırsa tüm Süper 12 kartları kapanır. */
    private const GLOBAL_OPTION = 'ms12_auto_all';
    /** Seans bazlı durdurma listesi (session_id dizisi). */
    private const STOP_OPTION = 'ms12_stopped_sessions';

    public static function globally_on(): bool {
        return get_option(self::GLOBAL_OPTION, 'on') !== 'off';
    }
    public static function stopped(): array {
        $list = get_option(self::STOP_OPTION, []);
        return is_array($list) ? array_values(array_unique(array_map('intval', $list))) : [];
    }
    private static function is_stopped(int $session): bool {
        return in_array($session, self::stopped(), true);
    }

    /**
     * Süper 12, Aile Paketi 1 ve 2 gibi satıştaki her seansta sunulur: aynı etkinlik,
     * aynı salon, aynı seans. Ayrı salon teyidi, pilot doğrulaması veya seans bazlı
     * opt-in yoktur. Fiyat, ürün eşleşmesi, kapasite ve ödeme kontrolleri katalogda kalır.
     */
    public static function enabled(int $session = self::SESSION): bool {
        if (!self::dependencies() || !self::globally_on() || self::is_stopped($session)) return false;
        return class_exists('MS12_Catalog') && MS12_Catalog::session($session) !== null;
    }
    public static function enabled_sessions(?int $event = null): array {
        if (!self::dependencies() || !self::globally_on() || !class_exists('MS12_Catalog')) return [];
        $stopped = self::stopped();
        $result = [];
        foreach (MS12_Catalog::upcoming() as $row) {
            $sid = (int)$row->session_id;
            if (($event === null || $event === (int)$row->event_id) && !in_array($sid, $stopped, true)) $result[] = $sid;
        }
        return $result;
    }
    public static function available(int $event, int $session): bool {
        if (!self::dependencies() || !self::globally_on() || self::is_stopped($session) || !class_exists('MS12_Catalog')) return false;
        $row = MS12_Catalog::session($session);
        return $row !== null && (int)$row->event_id === $event;
    }

    public static function admin_controls(): void {
        if (!current_user_can('manage_options') || !self::live_site()) return;
        $dependency = self::dependency_blocker();
        $on = self::globally_on();
        echo '<h2>Süper 12: satıştaki tüm seanslar</h2>';
        echo '<p>Süper 12, Aile Paketi 1 ve 2 gibi satışta olan her seansta otomatik sunulur. Ayrı salon teyidi veya seans bazlı açma gerekmez. Fiyat kuralı, ürün eşleşmesi, en az 12 boş yer ve ödeme kontrolleri her satışta yapılır.</p>';
        if ($dependency !== null) echo '<p><strong>Genel engel:</strong> ' . esc_html($dependency) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ms12_pilot_switch"><input type="hidden" name="ms12_scope" value="global">';
        echo '<input type="hidden" name="pilot_state" value="' . ($on ? 'off' : 'on') . '">';
        wp_nonce_field('ms12_global_switch');
        echo '<p>Genel durum: <strong>' . ($on ? 'AÇIK' : 'KAPALI') . '</strong> ';
        submit_button($on ? 'Tüm Süper 12 satışını durdur' : 'Süper 12 satışını tüm seanslarda aç', 'secondary', 'submit', false);
        echo '</p></form>';
    }

    /** Tek seans için durdur / yeniden aç düğmesi (tablo satırında kullanılır). */
    public static function session_status_html(int $event, int $session): string {
        if (!self::live_site() || !current_user_can('manage_options')) return '';
        $stopped = self::is_stopped($session);
        $form  = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
        $form .= '<input type="hidden" name="action" value="ms12_pilot_switch"><input type="hidden" name="ms12_scope" value="session">';
        $form .= '<input type="hidden" name="pilot_session" value="' . (int)$session . '">';
        $form .= '<input type="hidden" name="pilot_state" value="' . ($stopped ? 'on' : 'off') . '">';
        $form .= wp_nonce_field('ms12_session_' . $session, '_wpnonce', true, false);
        $form .= '<button type="submit" class="button button-small">' . ($stopped ? 'Yeniden aç' : 'Bu seansı durdur') . '</button></form>';
        return $form;
    }

    public static function session_is_stopped(int $session): bool { return self::is_stopped($session); }

    private static function posted_value(string $key): string {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    }

    public static function admin_switch(): void {
        if (!self::live_site() || !current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Yetkisiz işlem.', '', ['response'=>403]);
        }
        $scope = self::posted_value('ms12_scope');
        $state = self::posted_value('pilot_state');
        if (!in_array($state, ['on','off'], true)) wp_die('Geçersiz işlem.', '', ['response'=>409]);
        if ($scope === 'global') {
            check_admin_referer('ms12_global_switch');
            update_option(self::GLOBAL_OPTION, $state, false);
        } elseif ($scope === 'session') {
            $raw = self::posted_value('pilot_session');
            $session = (int)$raw;
            if ($session < 1 || $raw !== (string)$session) wp_die('Geçersiz seans.', '', ['response'=>409]);
            check_admin_referer('ms12_session_' . $session);
            $list = array_diff(self::stopped(), [$session]);
            if ($state === 'off') $list[] = $session;
            update_option(self::STOP_OPTION, array_values(array_unique($list)), false);
        } else {
            wp_die('Geçersiz işlem.', '', ['response'=>409]);
        }
        wp_safe_redirect(admin_url('tools.php?page=madagaskar-super12'));
        exit;
    }

    public static function summary(): string {
        if (!self::live_site()) return 'Canlı pilot bu alan adında kullanılamaz.';
        if (!self::checkout_compatible()) return 'Canlı ödeme sayfası mevcut WooCommerce Blocks ve Tickera şablonu olarak doğrulanmadı; kapsam kapalı.';
        if (!self::globally_on()) return 'Süper 12 genel olarak KAPALI.';
        $active = self::enabled_sessions();
        if (!$active) return 'Satışta uygun seans bulunamadı.';
        return 'Satıştaki ' . count($active) . ' seansta açık. Fiyat, eşleşme ve kapasite her satışta ayrıca doğrulanır.';
    }
}
