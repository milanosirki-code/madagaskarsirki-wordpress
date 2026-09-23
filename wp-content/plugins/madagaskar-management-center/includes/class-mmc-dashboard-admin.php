<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Dashboard_Admin {
    public static function render() {
        $overview = MMC_Dashboard_Service::overview();
        $filters = array(
            'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
            'province' => sanitize_text_field( wp_unslash( $_GET['province'] ?? '' ) ),
            'q' => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ),
            'include_closed' => ! empty( $_GET['include_closed'] ),
        );
        $rows = MMC_Dashboard_Service::program_rows( $filters );
        $alerts = MMC_Dashboard_Service::critical_alerts( 10 );
        $statuses = MMC_Program_Service::statuses();
        $provinces = MMC_Dashboard_Service::provinces();
        ?>
        <div class="wrap mmc-wrap mmc-exec-dashboard">
            <div class="mmc-dashboard-heading">
                <div><h1>Madagaskar Yönetici Dashboardu</h1><p class="mmc-lead">Tüm programların satış, saha, operasyon, reklam ve finans durumunu tek ekrandan izleyin.</p></div>
                <div class="mmc-dashboard-clock"><strong><?php echo esc_html( wp_date('d.m.Y') ); ?></strong><span>Canlı yönetim görünümü</span></div>
            </div>

            <div class="mmc-kpi-grid">
                <?php self::kpi('Bugünkü Satış / Ciro', number_format_i18n($overview['orders_count']).' sipariş', self::money($overview['net_revenue']), 'success'); ?>
                <?php self::kpi('Satılan Bilet', number_format_i18n($overview['ticket_count']), number_format_i18n($overview['audience_units']).' kişi kapasitesi', 'info'); ?>
                <?php if($overview['lead_metric_available']): self::kpi('Aktif Müşteri / Lead', number_format_i18n($overview['active_leads']), 'Kommo', 'info'); else: self::kpi('Aktif Müşteri / Lead', '—', number_format_i18n($overview['synced_program_leads']).' program lead senkron', 'muted'); endif; ?>
                <?php self::kpi('Meta Reklam Harcaması', self::money($overview['meta_spend']), $overview['meta_roas']>0?'ROAS '.number_format_i18n($overview['meta_roas'],2):'ROAS —', 'warning'); ?>
                <?php self::kpi('CPA / Satın Alma', $overview['meta_purchases']?self::money($overview['meta_cpa']):'—', number_format_i18n($overview['meta_purchases']).' satın alma', 'warning'); ?>
                <?php self::kpi('Başarısız Ödeme / İade', number_format_i18n($overview['failed_orders']).' / '.number_format_i18n($overview['refund_orders']), $overview['refund_amount']>0?self::money($overview['refund_amount']).' iade':'Bugün', ($overview['failed_orders']+$overview['refund_orders'])?'danger':'success'); ?>
                <?php self::next_show_kpi($overview['next_show']); ?>
            </div>

            <div class="mmc-dashboard-strip">
                <span><strong><?php echo esc_html(number_format_i18n($overview['open_tasks'])); ?></strong> açık görev</span>
                <span class="<?php echo $overview['overdue_tasks']?'mmc-text-danger':''; ?>"><strong><?php echo esc_html(number_format_i18n($overview['overdue_tasks'])); ?></strong> gecikmiş görev</span>
                <span><strong><?php echo esc_html(number_format_i18n(count($rows))); ?></strong> görüntülenen program</span>
            </div>

            <?php if($alerts): ?>
            <div class="mmc-panel mmc-alert-panel"><h2>Kritik Uyarılar</h2><div class="mmc-alert-list">
                <?php foreach($alerts as $a): ?>
                    <a class="mmc-alert mmc-risk-<?php echo esc_attr($a['level']); ?>" href="<?php echo esc_url(admin_url('admin.php?page=mmc-programs')); ?>">
                        <strong><?php echo esc_html($a['program_code'].' · '.$a['location']); ?></strong><span><?php echo esc_html($a['message']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div></div>
            <?php endif; ?>

            <div class="mmc-panel">
                <div class="mmc-panel-head"><div><h2>Program Kontrol Merkezi</h2><p>Faz, satış, saha, operasyon, finans ve risk aynı satırda.</p></div></div>
                <form method="get" class="mmc-dashboard-filters">
                    <input type="hidden" name="page" value="mmc-dashboard">
                    <input type="search" name="q" value="<?php echo esc_attr($filters['q']); ?>" placeholder="Kod, il veya ilçe ara">
                    <select name="status"><option value="">Aktif durumların tümü</option><?php foreach($statuses as $key=>$label): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'],$key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                    <select name="province"><option value="">Tüm iller</option><?php foreach($provinces as $province): ?><option value="<?php echo esc_attr($province); ?>" <?php selected($filters['province'],$province); ?>><?php echo esc_html($province); ?></option><?php endforeach; ?></select>
                    <label class="mmc-filter-check"><input type="checkbox" name="include_closed" value="1" <?php checked($filters['include_closed']); ?>> Kapanan/iptal programları göster</label>
                    <button class="button">Filtrele</button><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mmc-dashboard')); ?>">Temizle</a>
                </form>

                <div class="mmc-table-scroll mmc-dashboard-table"><table class="widefat striped">
                    <thead><tr><th>Program</th><th>Gösteri</th><th>Faz</th><th>Satış / Doluluk</th><th>Saha</th><th>Operasyon</th><th>Finans</th><th>Meta</th><th>Risk</th><th>Hızlı İşlem</th></tr></thead>
                    <tbody>
                    <?php if(!$rows): ?><tr><td colspan="10">Filtreye uygun program bulunamadı.</td></tr><?php else: foreach($rows as $row): self::program_row($row,$statuses); endforeach; endif; ?>
                    </tbody>
                </table></div>
            </div>

            <div class="mmc-panel">
                <h2>Yönetim Notu</h2>
                <p><strong>Aktif Müşteri / Lead</strong> kartı yalnız gerçek Kommo müşteri-lead metriği MMC'ye bağlandığında sayı gösterecek. Program takip lead'leri müşteri lead'i gibi sayılmıyor; böylece dashboard yanıltıcı veri üretmiyor.</p>
            </div>
        </div>
        <?php
    }

    private static function program_row($row,$statuses){
        $p=$row['program']; $sales=$row['sales']; $field=$row['field']; $ops=$row['operations']; $fin=$row['finance']; $meta=$row['meta'];
        $date=$row['event_date']; $days=$row['days_to_show']; $risk=$row['risk_level'];
        ?>
        <tr>
            <td><strong><?php echo esc_html($p->program_code); ?></strong><br><?php echo esc_html(trim($p->province_name.' / '.$p->district_name,' /')); ?></td>
            <td><?php if($date): ?><strong><?php echo esc_html(wp_date('d.m.Y',strtotime($date))); ?></strong><br><small><?php echo $days===0?'Bugün':($days>0?esc_html($days.' gün kaldı'):esc_html(abs($days).' gün önce')); ?></small><?php else: ?><span class="mmc-muted">Tarih yok</span><?php endif; ?></td>
            <td><span class="mmc-status-badge"><?php echo esc_html($statuses[$p->status]??$p->status); ?></span></td>
            <td><strong><?php echo esc_html(number_format_i18n((int)$sales['sold_capacity'])); ?> kişi</strong><br><?php echo esc_html(number_format_i18n((float)$sales['occupancy'],1)); ?>% doluluk<br><small><?php echo esc_html(self::money($sales['net_revenue'])); ?></small></td>
            <td><?php if((int)($field['target_schools']??0)>0): ?><strong><?php echo esc_html(number_format_i18n((float)$field['visit_percent'],1)); ?>%</strong><br><small><?php echo esc_html((int)$field['visited_schools'].' / '.(int)$field['target_schools'].' okul'); ?></small><?php else: ?><span class="mmc-muted">Hedef yok</span><?php endif; ?></td>
            <td><?php if((int)($ops['pre_total']??0)>0): ?><strong><?php echo esc_html(number_format_i18n((float)$ops['pre_percent'],1)); ?>%</strong><br><small><?php echo esc_html((int)$ops['problems'].' problem'); ?></small><?php else: ?><span class="mmc-muted">Plan yok</span><?php endif; ?></td>
            <td><strong><?php echo esc_html(self::money($fin['profit'])); ?></strong><br><small><?php echo esc_html(number_format_i18n((float)$fin['margin'],1)); ?>% marj</small><?php if($fin['deposit_outstanding']>0): ?><br><small class="mmc-text-warning">Teminat <?php echo esc_html(self::money($fin['deposit_outstanding'])); ?></small><?php endif; ?></td>
            <td><?php if($meta): ?><strong><?php echo esc_html(self::money($meta->spend)); ?></strong><br><small>CPA <?php echo (int)$meta->purchases>0?esc_html(self::money($meta->cpa)):'—'; ?> · ROAS <?php echo (float)$meta->roas>0?esc_html(number_format_i18n((float)$meta->roas,2)):'—'; ?></small><?php else: ?><span class="mmc-muted">Plan yok</span><?php endif; ?></td>
            <td><span class="mmc-risk-pill mmc-risk-<?php echo esc_attr($risk); ?>"><?php echo esc_html(self::risk_label($risk)); ?></span><details><summary>Detay</summary><?php foreach($row['risks'] as $r): ?><div class="mmc-risk-line mmc-risk-<?php echo esc_attr($r['level']); ?>"><?php echo esc_html($r['message']); ?></div><?php endforeach; ?></details></td>
            <td><div class="mmc-quick-actions"><a href="<?php echo esc_url(admin_url('admin.php?page=mmc-sales&program_id='.(int)$p->id)); ?>">Satış</a><a href="<?php echo esc_url(admin_url('admin.php?page=mmc-field&program_id='.(int)$p->id)); ?>">Saha</a><a href="<?php echo esc_url(admin_url('admin.php?page=mmc-operations&program_id='.(int)$p->id)); ?>">Operasyon</a><a href="<?php echo esc_url(admin_url('admin.php?page=mmc-finance&program_id='.(int)$p->id)); ?>">Finans</a></div></td>
        </tr>
        <?php
    }

    private static function kpi($label,$value,$sub,$tone='info'){
        ?><div class="mmc-kpi mmc-kpi-<?php echo esc_attr($tone); ?>"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($value); ?></strong><small><?php echo esc_html($sub); ?></small></div><?php
    }
    private static function next_show_kpi($event){
        if(!$event){self::kpi('Sıradaki Gösteri','—','Aktif gelecek program yok','muted');return;}
        $days=(int)floor((strtotime($event->event_date.' 12:00:00')-strtotime(current_time('Y-m-d').' 12:00:00'))/DAY_IN_SECONDS);
        $value=$days===0?'BUGÜN':($days.' gün');
        self::kpi('Sıradaki Gösteri',$value,$event->province_name.' / '.$event->district_name.' · '.wp_date('d.m.Y',strtotime($event->event_date)),$days<=2?'danger':'success');
    }
    private static function risk_label($risk){return array('critical'=>'Kritik','high'=>'Yüksek','medium'=>'Orta','info'=>'Bilgi','ok'=>'Normal')[$risk]??$risk;}
    private static function money($v){return number_format_i18n((float)$v,2).' TL';}
}
