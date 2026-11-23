<?php
/** Individually opted-in deployment profiles. No provider, payment or sales-data writes. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MS12_Pilot {
    public const SESSION = 309;
    public const EVENT = 59;
    // Limited early rollout only. These exact session IDs have their own evidence
    // and cannot count as the paid S309 control for any other profile.
    private const EARLY_CANARY = [314,294,290];

    private static function profiles(): array {
        static $profiles;
        if ($profiles === null) $profiles = require __DIR__ . '/profiles.php';
        return $profiles;
    }

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

    private static function option(int $session): string {
        return $session === self::SESSION ? 'ms12_pilot_profile' : 'ms12_pilot_' . $session . '_profile';
    }
    private static function token(int $session): string {
        return in_array($session, [309,310], true) ? 'ayvalik-' . $session . '-v1' : 'seans-' . $session . '-v1';
    }
    private static function canary_token(int $session): string {
        return 'seans-' . $session . '-erken-kanarya-v1';
    }
    private static function nonce(int $session): string {
        return $session === self::SESSION ? 'ms12_pilot_switch' : 'ms12_pilot_switch_' . $session;
    }
    private static function phrase(int $session): string {
        return (in_array($session, [309,310], true) ? 'AYVALIK ' : 'SEANS ') . $session;
    }
    private static function venue_field(int $session): string {
        return $session === 310 ? 'venue_310' : 'venue_hours';
    }
    private static function opted_in(int $session): bool {
        $stored = get_option(self::option($session), '');
        return $stored === self::token($session) || (in_array($session, self::EARLY_CANARY, true)
            && $stored === self::canary_token($session));
    }
    public static function enabled(int $session = self::SESSION): bool {
        $profile = self::profiles()[$session] ?? null;
        return $profile !== null && self::dependencies() && self::opted_in($session);
    }
    public static function enabled_sessions(?int $event = null): array {
        if (!self::dependencies()) return [];
        $result = [];
        foreach (self::profiles() as $session => $profile) {
            if (($event === null || $event === $profile['event']) && self::opted_in($session)) $result[] = $session;
        }
        return $result;
    }
    public static function available(int $event, int $session): bool {
        return (self::profiles()[$session]['event'] ?? 0) === $event && self::enabled($session);
    }

    public static function matches(object $row, array $types): bool {
        $session = (int)($row->session_id??0);
        $profile = self::profiles()[$session] ?? null;
        if ($profile === null || (int)($row->event_id??0) !== $profile['event']
            || (int)($row->wc_product_id??0) !== $profile['product']
            || ($types['adultVariationId']??0) !== $profile['adult'] || ($types['childVariationId']??0) !== $profile['child']
            || ($types['adultPrice']??'') !== $profile['adult_price'] || ($types['childPrice']??'') !== $profile['child_price']
            || ($types['adultCode']??'') !== $profile['adult_code'] || ($types['childCode']??'') !== $profile['child_code']) return false;
        // Preserve original pre-existing order gates exactly. All other opt-ins are bound
        // to their checked local show schedule; a reschedule needs a new review.
        if ($session === 309 || $session === 310) return true;
        try {
            if (empty($row->start_at)) return false;
            $start = new DateTimeImmutable((string)$row->start_at, new DateTimeZone('UTC'));
            return $start->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('Y-m-d H:i') === $profile['show_at'];
        } catch (Exception $e) { return false; }
    }

    private static function live_blocker(int $session, array $profile): ?string {
        // Preserve the original #309 exception. No new session may skip catalog readiness.
        if ($session === self::SESSION) return null;
        if (!class_exists('MS12_Catalog')) return 'Süper 12 katalog modülü yüklenmemiş.';
        return MS12_Catalog::deployment_blocker($profile['event'], $session);
    }

    public static function admin_controls(): void {
        if (!current_user_can('manage_options') || !self::live_site()) return;
        $dependency = self::dependency_blocker();
        echo '<h2>Süper 12: seans bazlı satış kontrolü</h2><p>Yeni seanslar kapalıdır. Toplu açma yoktur. Yazılı salon saati eksik seansı atlayın.</p>';
        foreach (self::profiles() as $session => $profile) {
            $on = self::opted_in($session);
            $label = $profile['place'] . ' ' . $profile['show_at'] . ' (#' . $session . ')';
            if (!$on && ($why = $dependency ?? self::live_blocker($session, $profile)) !== null) {
                echo '<p>' . esc_html($label . ': ' . $why . ' Kapalı.') . '</p>';
                continue;
            }
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<p><strong>' . esc_html($label) . '</strong> · ' . ($on ? 'Açık seçenek' : 'Kapalı seçenek') . '</p>';
            echo '<input type="hidden" name="action" value="ms12_pilot_switch">';
            echo '<input type="hidden" name="pilot_session" value="' . (int)$session . '">';
            echo '<input type="hidden" name="pilot_state" value="' . ($on ? 'off' : 'on') . '">';
            wp_nonce_field(self::nonce($session));
            if (!$on) {
                echo '<label>Başlatmadan önce ' . esc_html(self::phrase($session)) . ' yazın: <input type="text" name="pilot_check" autocomplete="off" required></label> ';
                if ($profile['requires_paid_pilot_and_venue']) {
                    $canary = in_array($session, self::EARLY_CANARY, true);
                    echo '<p><label><input type="checkbox" name="qa_309" value="paid-reconciled"' . ($canary ? '' : ' required') . '> Normal açılış: Ayvalık 18.00 pilotunun ilk gerçek ödemesinde PayTR/Woo tahsilatını, kapasiteyi ve QR/PDF biletlerini doğruladım.</label></p>';
                    echo '<p><label><input type="checkbox" name="' . esc_attr(self::venue_field($session)) . '" value="written-hours-verified" required> Bu seans için ekip girişi, seyirci kabulü, gösteri bitişi, çıkış ve teslimi karşılayan güncel yazılı salon onayını gördüm.</label></p>';
                    if ($canary) {
                        echo '<fieldset><legend>İlk Ayvalık ödemesinden önce, yalnız bu seans için sınırlı açılış</legend>';
                        echo '<p><label><input type="checkbox" name="pilot_mode" value="early-canary"> Normal açılış yerine bu sınırlı açılışı seçiyorum.</label></p>';
                        echo '<p><label><input type="checkbox" name="qa_canary_checkout" value="isolated-checkout-verified"> Yalıtılmış sipariş/QR ve kapasite testlerinin bu seansın tarife ve ürün modelini kapsadığını doğruladım.</label></p>';
                        echo '<p><label><input type="checkbox" name="qa_canary_owner" value="owner-approved-before-first-309"> İşletme sahibinin, Ayvalık 18.00 ilk gerçek ödemesi henüz doğrulanmadan yalnız bu seansın sınırlı açılışına açık onayını gördüm.</label></p></fieldset>';
                    }
                }
            }
            submit_button($on ? 'Yalnız bu seansı durdur' : 'Yalnız bu seansı başlat', 'secondary', 'submit', false);
            echo '</form>';
        }
    }

    private static function posted_value(string $key): string {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    }

    /** Return the precise stored mode or nothing. A canary never writes paid QA state. */
    private static function authorized_token(int $session, array $profile, ?string &$reason = null): ?string {
        $mode = self::posted_value('pilot_mode');
        if (!$profile['requires_paid_pilot_and_venue']) {
            if ($mode === '') return self::token($session);
            $reason = 'Bu seans için seçilen açılış modu geçersiz.';
            return null;
        }
        if (self::posted_value(self::venue_field($session)) !== 'written-hours-verified') {
            $reason = 'Bu seansı kapsayan güncel yazılı salon kullanım teyidi işaretlenmemiş.';
            return null;
        }
        if ($mode === '') {
            if (self::posted_value('qa_309') === 'paid-reconciled') return self::token($session);
            $reason = 'Ayvalık #309 gerçek ödeme ve QR/PDF pilot doğrulaması işaretlenmemiş.';
            return null;
        }
        if ($mode !== 'early-canary' || !in_array($session, self::EARLY_CANARY, true)) {
            $reason = 'Bu seans için seçilen açılış modu geçersiz.';
        } elseif (self::posted_value('qa_309') !== '') {
            $reason = 'Normal ve erken açılış aynı anda seçilemez.';
        } elseif (self::posted_value('qa_canary_checkout') !== 'isolated-checkout-verified') {
            $reason = 'Sınırlı açılış için yalıtılmış ödeme, QR ve kapasite testi teyidi eksik.';
        } elseif (self::posted_value('qa_canary_owner') !== 'owner-approved-before-first-309') {
            $reason = 'Bu seansın sınırlı açılışı için işletme onayı teyidi eksik.';
        } else {
            return self::canary_token($session);
        }
        return null;
    }

    public static function admin_switch(): void {
        if (!self::live_site() || !current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Yetkisiz işlem.', '', ['response'=>403]);
        }
        // Old S309 forms omit session. Never allow comma lists, aliases or event substitution.
        $raw_session = array_key_exists('pilot_session', $_POST) ? self::posted_value('pilot_session') : '309';
        $session = (int)$raw_session;
        $profile = self::profiles()[$session] ?? null;
        if ($profile === null || $raw_session !== (string)$session) {
            wp_die('Bilinmeyen pilot seansı.', '', ['response'=>409]);
        }
        check_admin_referer(self::nonce($session));
        $state = self::posted_value('pilot_state');
        if ($state === 'off') {
            delete_option(self::option($session));
        } else {
            $reason = null;
            if ($state !== 'on') {
                $reason = 'Geçersiz seans işlemi.';
            } elseif (($reason = self::dependency_blocker()) !== null) {
                // Keep the original gate order.
            } elseif (!hash_equals(self::phrase($session), trim(self::posted_value('pilot_check')))) {
                $reason = 'Doğrulama ifadesi uyuşmuyor. Beklenen: ' . self::phrase($session) . '.';
            } elseif (($token = self::authorized_token($session, $profile, $reason)) !== null) {
                $reason = self::live_blocker($session, $profile);
                if ($reason === null) update_option(self::option($session), $token, false);
            } elseif ($reason === null) {
                $reason = 'Açılış yetkilendirmesi doğrulanamadı.';
            }
            if ($reason !== null) wp_die($reason, '', ['response'=>409]);
        }
        wp_safe_redirect(admin_url('tools.php?page=madagaskar-super12'));
        exit;
    }

    public static function summary(): string {
        if (!self::live_site()) return 'Canlı pilot bu alan adında kullanılamaz.';
        if (!self::checkout_compatible()) return 'Canlı ödeme sayfası mevcut WooCommerce Blocks ve Tickera şablonu olarak doğrulanmadı; kapsam kapalı.';
        $active = self::enabled_sessions();
        if (!$active) return 'Kapsam kapalı.';
        return 'Açık seçenekler: ' . implode(', ', array_map('strval', $active)) . '. Hazırlık şartları her satışta ayrıca doğrulanır.';
    }
}
