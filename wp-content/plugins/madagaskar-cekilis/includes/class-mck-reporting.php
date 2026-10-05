<?php
/** Local, SELECT-only reporting. No schema migration or draw initialization. */
final class MCK_Reporting {
    const VERSION = '1.0.0';
    private static $overview;
    private static $audit = array();

    public static function overview() {
        if (self::$overview !== null) return self::$overview;
        global $wpdb;
        $p = $wpdb->prefix . 'mck_';
        $has_audit = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $p.'redraw_audit')) === $p.'redraw_audit';
        $audit_join = $has_audit ? "LEFT JOIN (SELECT campaign_id,COUNT(*) replacements,COUNT(DISTINCT draw_id) replaced_slots,SUM(old_verification_status='disqualified') historical_disqualified FROM {$p}redraw_audit GROUP BY campaign_id) a ON a.campaign_id=c.id" : '';
        $audit_cols = $has_audit ? 'COALESCE(a.replacements,0) replacements,COALESCE(a.replaced_slots,0) replaced_slots,COALESCE(a.historical_disqualified,0) historical_disqualified' : '0 replacements,0 replaced_slots,0 historical_disqualified';
        $sql = "SELECT c.*,COALESCE(cm.comments,0) comments,COALESCE(cm.participants,0) participants,COALESCE(cm.valid_comments,0) valid_comments,COALESCE(cm.valid_users,0) valid_users,
            COALESCE(d.results,0) results,COALESCE(d.winners,0) winners,COALESCE(d.reserves,0) reserves,COALESCE(d.verified,0) verified,COALESCE(d.pending,0) pending,COALESCE(d.disqualified,0) disqualified,COALESCE(d.unknown_verification,0) unknown_verification,{$audit_cols}
            FROM {$p}campaigns c
            LEFT JOIN (SELECT campaign_id,COUNT(*) comments,COUNT(DISTINCT NULLIF(LOWER(username),'')) participants,SUM(is_valid=1) valid_comments,COUNT(DISTINCT CASE WHEN is_valid=1 THEN NULLIF(LOWER(username),'') END) valid_users FROM {$p}comments GROUP BY campaign_id) cm ON cm.campaign_id=c.id
            LEFT JOIN (SELECT campaign_id,COUNT(*) results,SUM(result_type='winner') winners,SUM(result_type='reserve') reserves,SUM(verification_status='verified') verified,SUM(verification_status='pending') pending,SUM(verification_status='disqualified') disqualified,SUM(verification_status NOT IN ('verified','pending','disqualified')) unknown_verification FROM {$p}draws GROUP BY campaign_id) d ON d.campaign_id=c.id
            {$audit_join} ORDER BY c.id DESC";
        $rows = (array)$wpdb->get_results($sql, ARRAY_A);
        $totals = array_fill_keys(array('campaigns','comments','winners','reserves','results','verified','pending','disqualified','replacements','historical_disqualified'), 0);
        foreach ($rows as &$r) {
            $r = self::derive($r);
            $totals['campaigns']++;
            foreach ($totals as $k => $v) if ($k !== 'campaigns') $totals[$k] += (int)$r[$k];
        }
        unset($r);
        $totals['unique_participants'] = (int)$wpdb->get_var("SELECT COUNT(DISTINCT NULLIF(LOWER(username),'')) FROM {$p}comments");
        return self::$overview = array('campaigns'=>$rows,'totals'=>$totals,'audit_available'=>$has_audit);
    }

    public static function derive(array $r) {
        $r['eligible_entries'] = (int)$r['one_user_one_entry'] === 1 ? (int)$r['valid_users'] : (int)$r['valid_comments'];
        $r['expected_slots'] = (int)$r['winner_count'] + (int)$r['reserve_count'];
        $r['missing_slots'] = max(0,(int)$r['winner_count']-(int)$r['winners']) + max(0,(int)$r['reserve_count']-(int)$r['reserves']);
        $drawn = $r['status'] === 'drawn' || !empty($r['drawn_at']) || (int)$r['results'] > 0;
        $r['complete'] = $drawn && $r['expected_slots'] > 0 && !$r['missing_slots'] && (int)$r['results'] === (int)$r['verified'] && !(int)$r['unknown_verification'];
        $r['display_state'] = $r['complete'] ? 'Tamamlandı' : ($drawn ? ((int)$r['pending'] > 0 ? 'Doğrulama Bekliyor' : 'Çekiliş Yapıldı') : (in_array($r['status'],array('draft','ready'),true) ? 'Açık' : (string)$r['status']));
        return $r;
    }

    public static function campaign($id) {
        foreach (self::overview()['campaigns'] as $r) if ((int)$r['id']===(int)$id) return $r;
        return null;
    }

    public static function matches(array $r,$filter) {
        if ($filter==='pending') return (int)$r['pending'] > 0;
        if ($filter==='complete') return $r['complete'];
        if ($filter==='replacement') return (int)$r['replacements'] > 0;
        if ($filter==='missing') return $r['missing_slots'] > 0;
        return true;
    }

    public static function audit($campaign_id = 0) {
        $campaign_id = absint($campaign_id);
        if (isset(self::$audit[$campaign_id])) return self::$audit[$campaign_id];
        if (!self::overview()['audit_available']) return self::$audit[$campaign_id] = array();
        global $wpdb;
        $p = $wpdb->prefix;
        $where = $campaign_id ? $wpdb->prepare(' WHERE a.campaign_id=%d',$campaign_id) : '';
        // One join resolves all actors; never one user query per audit row.
        return self::$audit[$campaign_id] = (array)$wpdb->get_results("SELECT a.id,a.campaign_id,a.draw_id,a.result_type,a.position_no,a.old_comment_id,a.old_username,a.old_verification_status,a.old_verification_note,a.new_comment_id,a.new_username,a.actor_user_id,a.created_at,c.title campaign_title,u.display_name actor_name FROM {$p}mck_redraw_audit a LEFT JOIN {$p}mck_campaigns c ON c.id=a.campaign_id LEFT JOIN {$p}users u ON u.ID=a.actor_user_id {$where} ORDER BY a.id DESC",ARRAY_A);
    }

    public static function result_metadata($campaign_id) {
        $meta = array();
        foreach (self::audit($campaign_id) as $a) if (!isset($meta[(int)$a['draw_id']])) $meta[(int)$a['draw_id']]=$a;
        return $meta;
    }

    public static function verification_label($status) {
        $labels=array('verified'=>'Doğrulandı','pending'=>'Bekliyor','disqualified'=>'Doğrulanmadı / şartı sağlamadı');
        return $labels[$status] ?? (string)$status;
    }

    private static function cards(array $values) {
        echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">';
        foreach ($values as $label=>$value) echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;min-width:130px"><div>'.esc_html($label).'</div><strong style="font-size:23px">'.esc_html($value).'</strong></div>';
        echo '</div>';
    }

    public static function render_list() {
        if (!current_user_can('manage_options')) return;
        $data=self::overview();$t=$data['totals'];
        self::cards(array('Toplam kampanya'=>$t['campaigns'],'Toplam katılım (yorum)'=>$t['comments'],'Benzersiz kullanıcı'=>$t['unique_participants'],'Asil kazanan'=>$t['winners'],'Doğrulama bekleyen'=>$t['pending'],'Disqualified (mevcut)'=>$t['disqualified'],'Replacement işlemi'=>$t['replacements']));
        echo '<p>Katılım yorum sayısıdır; benzersiz kullanıcı tüm kampanyalar arasında tekilleştirilir. Doğrulama sayıları asil + yedek sonuçları kapsar. Geçmiş disqualified kayıtları: <strong>'.(int)$t['historical_disqualified'].'</strong>.</p>';
        $filter=sanitize_key($_GET['report_filter'] ?? 'all');
        $labels=array('all'=>'Tümü','pending'=>'Doğrulama bekleyen','complete'=>'Tamamlanan','replacement'=>'Replacement olan','missing'=>'Eksik winner slotu');
        if (!isset($labels[$filter])) $filter='all';
        echo '<nav aria-label="Kampanya filtreleri">';
        foreach ($labels as $k=>$label) echo '<a class="button '.($filter===$k?'button-primary':'').'" style="margin:0 6px 8px 0" href="'.esc_url(add_query_arg(array('page'=>'madagaskar-cekilis','report_filter'=>$k),admin_url('admin.php'))).'">'.esc_html($label).'</a>';
        echo '</nav>';$shown=0;
        foreach ($data['campaigns'] as $r) {
            if (!self::matches($r,$filter)) continue;$shown++;
            $url=add_query_arg(array('page'=>'madagaskar-cekilis','view'=>'campaign','campaign_id'=>$r['id']),admin_url('admin.php'));
            echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:18px;margin:14px 0;max-width:1100px"><h2 style="margin-top:0"><a href="'.esc_url($url).'">'.esc_html($r['title']).'</a> <small>— '.esc_html($r['display_state']).'</small></h2><p><a href="'.esc_url($r['post_url']).'" target="_blank" rel="noopener">Instagram post</a> · Çekiliş tarihi: '.esc_html($r['drawn_at'] ?: 'Henüz çekilmedi').'</p>';
            self::render_campaign_metrics($r);
            echo '<p><a class="button" href="'.esc_url($url).'">Detay / sonuçlar / geçmiş</a> '.self::export_link($r['id']).'</p></section>';
        }
        if (!$shown) echo '<p>Bu filtrede kampanya yok.</p>';
        self::render_audit(0);
    }

    public static function render_campaign_metrics(array $r) {
        self::cards(array('Yorum'=>$r['comments'],'Benzersiz kullanıcı'=>$r['participants'],'Uygun aday / hak'=>$r['eligible_entries'],'Asil'=>$r['winners'],'Yedek'=>$r['reserves'],'Doğrulandı'=>$r['verified'],'Doğrulanmadı'=>$r['disqualified'],'Bekleyen'=>$r['pending'],'Replacement'=>$r['replacements']));
        echo '<p>Eksik slot: <strong>'.(int)$r['missing_slots'].'</strong> · Replacement uygulanmış slot: '.(int)$r['replaced_slots'].' · Geçmiş disqualified: '.(int)$r['historical_disqualified'].'. Uygun aday / hak, kayıtlı geçerli yorumlardan kampanyanın tek kullanıcı kuralıyla hesaplanır; yeniden replacement havuzu değildir.</p>';
    }

    public static function render_audit($campaign_id) {
        $rows=self::audit($campaign_id);
        echo '<h2>Replacement geçmişi</h2>';
        if (!$rows) {echo '<p>Replacement kaydı yok.</p>';return;}
        echo '<table class="widefat striped"><thead><tr><th>Tarih/saat</th><th>Kampanya / slot</th><th>Eski kişi → yeni kişi</th><th>Neden / eski durum</th><th>Admin</th></tr></thead><tbody>';
        foreach ($rows as $a) echo '<tr><td>'.esc_html($a['created_at']).'</td><td>'.esc_html($a['campaign_title']).' · '.esc_html($a['result_type']).' #'.(int)$a['position_no'].'</td><td>@'.esc_html($a['old_username']).' → @'.esc_html($a['new_username']).'</td><td>'.esc_html(self::verification_label($a['old_verification_status'])).($a['old_verification_note']!=='' ? ' · '.esc_html($a['old_verification_note']) : '').'</td><td>'.esc_html($a['actor_name'] ?: 'Admin #'.(int)$a['actor_user_id']).'</td></tr>';
        echo '</tbody></table>';
    }

    public static function export_link($id) {
        $url=wp_nonce_url(add_query_arg(array('action'=>'mck_report_export','campaign_id'=>(int)$id),admin_url('admin-post.php')),'mck_report_export_'.(int)$id);
        return '<a class="button" href="'.esc_url($url).'">Sonuç CSV indir</a>';
    }

    public static function csv_cell($value) {
        $value=(string)$value;
        // Also catch formula prefixes after leading whitespace/control characters.
        return preg_match('/^[\x00-\x20]*[=+@-]/u',$value) ? "'".$value : $value;
    }

    public static function csv_rows($id) {
        global $wpdb;
        $meta=self::result_metadata($id);
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT id,username,result_type,verification_status,drawn_at FROM {$wpdb->prefix}mck_draws WHERE campaign_id=%d ORDER BY result_type DESC,position_no,id",absint($id)),ARRAY_A);
        $out=array(array('username','result_type','verification_state','selected_at','replacement_flag'));
        foreach ($rows as $r) $out[]=array_map(array(__CLASS__,'csv_cell'),array($r['username'],$r['result_type'],$r['verification_status'],$r['drawn_at'],isset($meta[(int)$r['id']])?'1':'0'));
        return $out;
    }

    public static function authorize_export($id) {
        if (!current_user_can('manage_options')) wp_die('Bu işlem için yetkiniz yok.', '', array('response'=>403));
        check_admin_referer('mck_report_export_'.absint($id));
        if (!self::campaign($id)) wp_die('Çekiliş bulunamadı.', '', array('response'=>404));
    }

    public static function export() {
        $id=absint($_GET['campaign_id'] ?? 0);
        self::authorize_export($id);
        nocache_headers();header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="madagaskar-sonuc-'.(int)$id.'.csv"');
        echo "\xEF\xBB\xBF";$out=fopen('php://output','w');
        foreach (self::csv_rows($id) as $row) fputcsv($out,$row,';', '"','');
        fclose($out);exit;
    }
}
