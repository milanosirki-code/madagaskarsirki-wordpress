<?php
/**
 * Madagaskar ticket recovery, 2026-11-09.
 * Reuses the site's existing Kommo token resolver and approved ticket Salesbot.
 * No checkout/payment hooks. Remove/disable this snippet to roll back.
 * WPCode PHP snippet: omit this opening PHP tag when importing.
 */
if (!defined('ABSPATH')) { return; }
// Conditional declaration avoids PHP early class binding bypassing boot().
if (!class_exists('MDG_Ticket_Recovery_20261109', false)) {
final class MDG_Ticket_Recovery_20261109 {
    private static $booted = false;
    const BOT_ID = 110115;
    const BASE = 'https://milanosirki.kommo.com/api/v4';
    const JOB = 'mdg_ticket_recovery_send';
    const GROUP = 'mdg-ticket-recovery';
    const COOLDOWN = 900;

    public static function boot() {
        if (self::$booted) { return; }
        self::$booted = true;
        add_filter('tickera_download_ticket_url_front_link', array(__CLASS__, 'label'), 90);
        add_filter('tc_download_ticket_url_front_link', array(__CLASS__, 'label'), 90);
        add_action('template_redirect', array(__CLASS__, 'submit'), 2);
        add_filter('the_content', array(__CLASS__, 'append_form'), 35);
        add_action(self::JOB, array(__CLASS__, 'send'), 10, 1);
        add_action('mdg_ticket_recovery_unlock', array(__CLASS__, 'unlock'), 10, 1);
    }

    public static function label($html) {
        // The same filter also receives raw URLs. Preserve them and all hrefs.
        if (!is_string($html) || stripos($html, '<a') === false) { return $html; }
        return preg_replace('/(<a\b[^>]*>)\s*(?:Download|İndir)\s*(<\/a>)/iu', '$1QR Biletimi Aç / İndir$2', $html);
    }

    public static function phone($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 14 && substr($digits, 0, 4) === '0090') { $digits = substr($digits, 4); }
        elseif (strlen($digits) === 12 && substr($digits, 0, 2) === '90') { $digits = substr($digits, 2); }
        elseif (strlen($digits) === 11 && $digits[0] === '0') { $digits = substr($digits, 1); }
        return preg_match('/^5[0-9]{9}$/D', $digits) ? $digits : '';
    }

    public static function ready() {
        return function_exists('wc_get_orders') && function_exists('ms_biletlerim_url')
            && function_exists('ms_kommo_token_value') && function_exists('ms_kommo_write_bilet_url')
            && defined('MS_KOMMO_BASE_URL') && rtrim(MS_KOMMO_BASE_URL, '/') === self::BASE
            && trim((string) ms_kommo_token_value()) !== '';
    }

    public static function eligible($order) {
        return is_object($order) && method_exists($order, 'get_type') && $order->get_type() === 'shop_order'
            && $order->has_status(array('processing', 'completed')) && $order->is_paid()
            && (float) $order->get_total() > 0;
    }

    public static function find_orders($phone) {
        if ($phone === '') { return array(); }
        $found = array();
        $base = array('status' => array('processing', 'completed'), 'limit' => 3,
            'orderby' => 'date', 'order' => 'DESC', 'date_created' => '>' . (time() - 90 * DAY_IN_SECONDS));
        // The checkout phone validator stores a canonical E.164 custom meta key.
        $queries = array(array_merge($base, array('meta_query' => array(array(
            'key' => '_billing_phone_valid', 'value' => '+90' . $phone, 'compare' => '='
        )))));
        // WooCommerce's billing_phone argument works with both HPOS and CPT storage.
        foreach (array($phone, '0' . $phone, '90' . $phone, '+90' . $phone) as $variant) {
            $queries[] = array_merge($base, array('billing_phone' => $variant));
        }
        foreach ($queries as $query) {
            foreach (wc_get_orders($query) as $order) {
                if (self::eligible($order) && self::phone($order->get_billing_phone()) === $phone) {
                    $found[$order->get_id()] = $order;
                }
            }
        }
        // The public result never includes these IDs, names, counts, or ticket URLs.
        usort($found, function ($a, $b) { return $b->get_id() <=> $a->get_id(); });
        return array_slice($found, 0, 3);
    }

    private static function throttle($kind, $value, $limit) {
        $key = 'mdg_tr_' . $kind . '_' . hash_hmac('sha256', $value, wp_salt('nonce'));
        $count = (int) get_transient($key);
        if ($count >= $limit) { return false; }
        set_transient($key, $count + 1, self::COOLDOWN);
        return true;
    }

    public static function submit() {
        if (!is_page('biletlerim') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || empty($_POST['mdg_ticket_recovery'])) { return; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers();
        $nonce = isset($_POST['mdg_tr_nonce']) && is_string($_POST['mdg_tr_nonce']) ? wp_unslash($_POST['mdg_tr_nonce']) : '';
        $raw = isset($_POST['mdg_tr_phone']) && is_string($_POST['mdg_tr_phone']) ? wp_unslash($_POST['mdg_tr_phone']) : '';
        $phone = self::phone($raw);
        $trap = !empty($_POST['mdg_tr_website']);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!$trap && wp_verify_nonce($nonce, 'mdg_ticket_recovery') && self::ready()
            && self::throttle('ip', $ip, 5) && $phone !== '' && self::throttle('phone', $phone, 1)) {
            foreach (self::find_orders($phone) as $order) { self::queue($order->get_id()); }
        }
        // PRG and an identical acknowledgement prevent phone/order enumeration.
        wp_safe_redirect(add_query_arg('ticket_recovery', 'requested', get_permalink()) . '#mdg-ticket-recovery', 303);
        exit;
    }

    public static function queue($order_id) {
        $args = array((int) $order_id);
        if (function_exists('as_schedule_single_action')) {
            return as_schedule_single_action(time() + 1, self::JOB, $args, self::GROUP, true);
        }
        if (!wp_next_scheduled(self::JOB, $args)) {
            return wp_schedule_single_event(time() + 1, self::JOB, $args, true);
        }
        return false;
    }

    public static function append_form($content) {
        if (is_admin() || is_feed() || !is_page('biletlerim') || !self::ready()
            || !empty($_GET['token']) || !empty($_GET['order_id']) || strpos($content, 'id="mdg-ticket-recovery"') !== false) {
            return $content;
        }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        $received = isset($_GET['ticket_recovery']) && $_GET['ticket_recovery'] === 'requested';
        $html = '<section id="mdg-ticket-recovery" aria-label="WhatsApp bilet bağlantısı" style="max-width:720px;margin:24px auto;padding:28px;border:1px solid #d4af37;border-radius:16px;background:#0d0b26;color:#fff;box-sizing:border-box">';
        $html .= '<h2 style="color:#fff;font-size:24px">Sipariş numaramı bulamıyorum</h2>';
        if ($received) {
            $html .= '<p role="status">Kayıtlı numarayla eşleşen ödenmiş bir sipariş varsa bilet bağlantısı WhatsApp gönderim sırasına alınır. Mesaj ulaşmazsa WhatsApp bilet desteğine yazabilirsiniz.</p>';
        }
        $html .= '<p>Satın alırken kullandığınız cep telefonunu yazın. Bağlantı yalnızca siparişte kayıtlı ve Kommo ana kişi kaydıyla doğrulanan numaraya gönderilir.</p>';
        $html .= '<form method="post" action="' . esc_url(get_permalink() . '#mdg-ticket-recovery') . '"><input type="hidden" name="mdg_ticket_recovery" value="1">';
        $html .= wp_nonce_field('mdg_ticket_recovery', 'mdg_tr_nonce', true, false);
        $html .= '<p hidden aria-hidden="true"><label>Boş bırakın<input name="mdg_tr_website" tabindex="-1" autocomplete="off"></label></p>';
        $html .= '<label for="mdg-tr-phone">Satın alırken kullandığınız telefon</label><input id="mdg-tr-phone" name="mdg_tr_phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="24" required placeholder="05xx xxx xx xx" style="display:block;width:100%;min-height:48px;padding:12px;box-sizing:border-box;margin:8px 0 16px;background:#fff;color:#17132c;border-radius:8px">';
        $html .= '<button type="submit" style="min-height:48px;padding:12px 20px;border:0;border-radius:8px;background:#d4af37;color:#17132c;font-weight:700">Bilet bağlantımı WhatsApp’a gönder</button></form>';
        $html .= '<p>Tekrar gönderim talepleri arasında en az 15 dakika bırakın. <a style="color:#d4af37" href="https://wa.me/903129113710">WhatsApp bilet desteği</a></p></section>';
        return $content . $html;
    }

    private static function api($path, $method = 'GET', $body = null) {
        $args = array('method' => $method, 'timeout' => 15, 'redirection' => 0,
            'headers' => array('Authorization' => 'Bearer ' . ms_kommo_token_value(),
                'Accept' => 'application/json', 'Content-Type' => 'application/json'));
        if ($body !== null) { $args['body'] = wp_json_encode($body); }
        return wp_remote_request(self::BASE . $path, $args);
    }

    public static function recipient_matches($lead, $contact, $order_id, $phone) {
        if (($lead['name'] ?? '') !== 'Order#' . (int) $order_id
            || (int) ($lead['pipeline_id'] ?? 0) !== (int) MS_KOMMO_PIPELINE_ID) { return false; }
        $main = array_values(array_filter($lead['_embedded']['contacts'] ?? array(), function ($c) { return !empty($c['is_main']); }));
        if (count($main) !== 1 || (int) $main[0]['id'] !== (int) ($contact['id'] ?? 0)) { return false; }
        foreach ($contact['custom_fields_values'] ?? array() as $field) {
            if (($field['field_code'] ?? '') !== 'PHONE') { continue; }
            foreach ($field['values'] ?? array() as $value) {
                if ($phone !== '' && self::phone($value['value'] ?? '') === $phone) { return true; }
            }
        }
        return false;
    }

    public static function valid_url($url, $order_id) {
        $parts = wp_parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== 'madagaskarsirki.com' || ($parts['path'] ?? '') !== '/biletlerim/'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) { return false; }
        parse_str($parts['query'] ?? '', $query);
        return (string) ($query['order_id'] ?? '') === (string) (int) $order_id
            && is_string($query['token'] ?? null) && (bool) preg_match('/^[a-f0-9]{64}$/D', $query['token']);
    }

    public static function send($order_id) {
        if (!self::ready() || !defined('MS_KOMMO_PIPELINE_ID')) { return false; }
        $order = wc_get_order((int) $order_id);
        if (!self::eligible($order)) { return false; }
        $phone = self::phone($order->get_billing_phone());
        $lead_id = absint($order->get_meta('_ms_kommo_lead_id'));
        if ($phone === '' || !$lead_id) { return false; }
        $response = self::api('/leads/' . $lead_id . '?with=contacts');
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return false; }
        $lead = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($lead)) { return false; }
        $main = array_values(array_filter($lead['_embedded']['contacts'] ?? array(), function ($c) { return !empty($c['is_main']); }));
        if (count($main) !== 1 || empty($main[0]['id'])) { return false; }
        $response = self::api('/contacts/' . absint($main[0]['id']));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return false; }
        $contact = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($contact) || !self::recipient_matches($lead, $contact, $order->get_id(), $phone)) { return false; }
        // Remote reads may take time: recheck a refund or phone/CRM reassignment.
        $fresh = wc_get_order($order->get_id());
        if (!self::eligible($fresh) || self::phone($fresh->get_billing_phone()) !== $phone
            || absint($fresh->get_meta('_ms_kommo_lead_id')) !== $lead_id) { return false; }
        $order = $fresh;
        $url = ms_biletlerim_url($order->get_id());
        if (!self::valid_url($url, $order->get_id())) { return false; }
        // Atomic per-order send lease: even concurrent or duplicate jobs cannot resend.
        $lock = 'mdg_tr_send_' . $order->get_id();
        if (!add_option($lock, time(), '', false)) { return false; }
        $cleanup = wp_schedule_single_event(time() + self::COOLDOWN, 'mdg_ticket_recovery_unlock', array($order->get_id()), true);
        if ($cleanup !== true) {
            delete_option($lock);
            return false;
        }
        // Refresh only this verified order's ticket URL. Never reset purchase/delivery flags.
        $written = ms_kommo_write_bilet_url($lead_id, $url);
        if (is_wp_error($written) || $written !== true) { return false; }
        $fresh = wc_get_order($order->get_id());
        if (!self::eligible($fresh) || self::phone($fresh->get_billing_phone()) !== $phone
            || absint($fresh->get_meta('_ms_kommo_lead_id')) !== $lead_id) { return false; }
        $response = self::api('/bots/' . self::BOT_ID . '/run', 'POST', array('entity_id' => $lead_id, 'entity_type' => 'leads'));
        // 202 = queued, not delivered. Do not claim or store delivery success.
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 202;
    }

    public static function unlock($order_id) { delete_option('mdg_tr_send_' . absint($order_id)); }
}
}
MDG_Ticket_Recovery_20261109::boot();
