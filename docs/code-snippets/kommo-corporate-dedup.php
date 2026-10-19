<?php
/** Corporate form exact-repeat guard. Requires active corporate form snippet 42. */
final class MDG_Corporate_Dedup_20261013 {
    private static $key = '';
    private static $claim = array();
    public static function fingerprint($data) {
        $values = array();
        foreach (array('company','contact_name','phone','city','event_date','event_type','audience','note') as $field) {
            $value = isset($data[$field]) ? (string) $data[$field] : '';
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            if ('phone' === $field) { $value = preg_replace('/\D/', '', $value); }
            if ('audience' === $field) { $value = (string) absint($value); }
            $values[$field] = $value;
        }
        return hash('sha256', wp_json_encode($values));
    }
    public static function release($key, $expected) {
        global $wpdb;
        // Compare-and-delete prevents removal of another request's reservation.
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            $key, maybe_serialize($expected)
        ));
        if ($deleted) { wp_cache_delete($key, 'options'); }
        return (bool) $deleted;
    }
    public static function redirect($status) {
        wp_safe_redirect(add_query_arg('mdg_teklif', $status, home_url('/kurumsal/')) . '#kurumsal-form');
        exit;
    }
    public static function begin() {
        if (!function_exists('mdg_handle_corporate_quote_v2')) { return; }
        $nonce = isset($_POST['mdg_corporate_quote_nonce']) && is_string($_POST['mdg_corporate_quote_nonce']) ? sanitize_text_field(wp_unslash($_POST['mdg_corporate_quote_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mdg_corporate_quote') || !empty($_POST['website']) || empty($_POST['contact_consent'])) { return; }
        $data = array();
        foreach (array('company','contact_name','phone','city','event_date','event_type','audience','note') as $field) {
            if (isset($_POST[$field]) && !is_scalar($_POST[$field])) { return; }
            $raw = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
            $data[$field] = 'note' === $field ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
        }
        foreach (array('company','contact_name','phone','city','event_type') as $required) {
            if ('' === $data[$required]) { return; }
        }
        if (!in_array($data['event_type'], array('Okul','Belediye','AVM','Festival','Özel Organizasyon','Diğer'), true)) { return; }
        $key = 'mdg_corp_dedup_' . self::fingerprint($data);
        $existing = get_option($key, false);
        if (is_array($existing) && $existing['expires'] <= time()) {
            self::release($key, $existing);
            $existing = get_option($key, false);
        }
        if ($existing) { self::redirect(!empty($existing['lead_id']) ? 'ok' : 'rate'); }
        $claim = array('owner' => wp_generate_uuid4(), 'expires' => time() + 300, 'lead_id' => 0);
        if (!add_option($key, $claim, '', false)) { self::redirect('rate'); }
        self::$key = $key;
        self::$claim = $claim;
        wp_schedule_single_event($claim['expires'], 'mdg_corp_dedup_cleanup', array($key));
        register_shutdown_function(array(__CLASS__, 'shutdown'));
    }
    public static function recorded($meta_id, $post_id, $meta_key, $value) {
        if (!self::$key || '_mdg_contact_consent' !== $meta_key || 'mdg_corp_lead' !== get_post_type($post_id)) { return; }
        if (get_option(self::$key) !== self::$claim) { return; }
        $saved = self::$claim;
        $saved['lead_id'] = (int) $post_id;
        $saved['expires'] = time() + DAY_IN_SECONDS;
        update_option(self::$key, $saved, false);
        update_post_meta($post_id, '_mdg_corp_submission_hash', substr(self::$key, strlen('mdg_corp_dedup_')));
        wp_schedule_single_event($saved['expires'], 'mdg_corp_dedup_cleanup', array(self::$key));
    }
    public static function shutdown() {
        if (self::$key) { self::release(self::$key, self::$claim); }
    }
    public static function cleanup($key) {
        $value = get_option($key, false);
        if (is_array($value) && $value['expires'] <= time()) { self::release($key, $value); }
    }
}
add_action('admin_post_mdg_corporate_quote', array('MDG_Corporate_Dedup_20261013','begin'), 5);
add_action('admin_post_nopriv_mdg_corporate_quote', array('MDG_Corporate_Dedup_20261013','begin'), 5);
add_action('added_post_meta', array('MDG_Corporate_Dedup_20261013','recorded'), 10, 4);
add_action('mdg_corp_dedup_cleanup', array('MDG_Corporate_Dedup_20261013','cleanup'));
