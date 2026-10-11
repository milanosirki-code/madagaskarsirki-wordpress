<?php
/**
 * Madagaskar Story yorum sayısı — 1.0.0
 * Hook: authenticated admin_post_mck2_story_result, priority 1.
 * Display-only bridge for raffle plugin 3.3.0. No domain writes.
 * Rollback: deactivate this snippet.
 */
function mdg_story_comment_copy_20261009($html, $total, $winners) {
    if (strpos($html, 'id="storySvg"') === false) { return $html; }
    $pattern = '~(<text\b[^>]*>)([0-9]+)(</text>\s*<text\b[^>]*>)geçerli katılım arasından(</text>\s*<text\b[^>]*>)yapılan çekiliş sonucu belirlenmiştir\.(</text>)~u';
    $count = 0;
    $updated = preg_replace_callback($pattern, static function ($m) use ($total, $winners) {
        return $m[1] . max(0, (int)$total) . $m[3] . 'yorum arasından' . $m[4]
            . 'yapılan çekiliş sonucunda ' . max(0, (int)$winners) . ' kazanan belirlenmiştir.' . $m[5];
    }, $html, -1, $count);
    return is_string($updated) && $count === 1 ? $updated : $html;
}
add_action('admin_post_mck2_story_result', static function () {
    if (!current_user_can('manage_options')) { return; }
    $id = absint($_GET['campaign_id'] ?? 0);
    if (!$id || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'mck2_story_result_' . $id)) { return; }
    global $wpdb;
    $campaign = $wpdb->get_row($wpdb->prepare("SELECT total_comments,status FROM {$wpdb->prefix}mck_campaigns WHERE id=%d", $id));
    if (!$campaign || $campaign->status !== 'drawn') { return; }
    $winners = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mck_draws WHERE campaign_id=%d AND result_type='winner'", $id));
    ob_start(static function ($html) use ($campaign, $winners) {
        return mdg_story_comment_copy_20261009($html, $campaign->total_comments, $winners);
    });
}, 1);
