<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MMC_Sales_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 27 );
        add_action( 'admin_post_mmc_save_sales_mapping', array( $this, 'handle_save_mapping' ) );
        add_action( 'admin_post_mmc_sync_sales', array( $this, 'handle_sync' ) );
        add_action( 'admin_post_mmc_import_legacy_sales_mapping', array( $this, 'handle_legacy_import' ) );
    }

    public function menu() {
        add_submenu_page( 'mmc-dashboard', 'Satış & Doluluk', 'Satış & Doluluk', 'mmc_view_programs', 'mmc-sales', array( $this, 'page' ) );
    }

    public function page() {
        $this->guard('mmc_view_programs');
        $programs=MMC_Program_Service::all_programs();
        $program_id=absint($_GET['program_id']??0); $program=$program_id?MMC_Program_Service::get_program($program_id):null;
        ?>
        <div class="wrap mmc-wrap"><h1>Satış & Doluluk</h1><?php $this->notice(); ?>
            <div class="mmc-panel"><form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="mmc-inline-form"><input type="hidden" name="page" value="mmc-sales"><label>Program <select name="program_id" required><option value="">Program seçin</option><?php foreach($programs as $p): ?><option value="<?php echo esc_attr($p->id); ?>" <?php selected($program_id,$p->id); ?>><?php echo esc_html($p->program_code.' — '.$p->province_name.' / '.($p->district_name?:'Genel')); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Aç</button></form></div>
            <?php if($program) $this->render($program); ?>
        </div><?php
    }

    private function render($program){
        $event=MMC_Event_Service::event_for_program($program->id);
        if(!$event){ echo '<div class="mmc-panel"><p>Önce Etkinlik & Seans modülünde etkinlik oluşturun.</p></div>'; return; }
        $sessions=MMC_Event_Service::sessions($event->id); $tickets=array_filter(MMC_Event_Service::ticket_types($event->id),function($t){return (int)$t->is_active===1;});
        $maps=MMC_Sales_Service::mappings($event->id); $map_index=array(); foreach($maps as $m)$map_index[$m->session_id.':'.$m->ticket_type_id]=$m;
        $summary=MMC_Sales_Service::summary($event->id); $by_session=MMC_Sales_Service::session_summary($event->id); $recent=MMC_Sales_Service::recent_orders($event->id,25); $coverage=MMC_Sales_Service::mapping_coverage($event->id); $paytr=MMC_Sales_Service::paytr_gateway_status();
        ?>
        <div class="mmc-panel mmc-hero-panel"><div><small><?php echo esc_html($program->program_code); ?></small><h2><?php echo esc_html($event->event_title); ?></h2></div><div><strong>Eşleştirme:</strong> <?php echo esc_html($coverage['mapped'].' / '.$coverage['required']); ?></div></div>
        <div class="mmc-cards mmc-cards-5">
            <div class="mmc-card"><span>Net Ciro</span><strong><?php echo esc_html(number_format_i18n((float)$summary['net_revenue'],2)); ?> TL</strong></div>
            <div class="mmc-card"><span>Satılan Kişi Kapasitesi</span><strong><?php echo esc_html(number_format_i18n((int)$summary['sold_capacity'])); ?></strong><small>Aile paketi 4 kişi sayılır</small></div>
            <div class="mmc-card"><span>Bilet/Ürün Adedi</span><strong><?php echo esc_html(number_format_i18n((int)$summary['ticket_count'])); ?></strong></div>
            <div class="mmc-card"><span>Sipariş</span><strong><?php echo esc_html(number_format_i18n((int)$summary['orders_count'])); ?></strong></div>
            <div class="mmc-card"><span>İade</span><strong><?php echo esc_html(number_format_i18n((float)$summary['refunded_amount'],2)); ?> TL</strong><small>Başarısız/iptal: <?php echo esc_html((int)$summary['failed_orders']); ?></small></div>
        </div>

        <div class="mmc-grid-2">
            <div class="mmc-panel"><h2>1. Sistem Sağlığı</h2>
                <table class="widefat striped"><tbody>
                    <tr><th>WooCommerce</th><td><?php echo MMC_Sales_Service::woocommerce_available()?'🟢 Aktif':'🔴 Algılanmadı'; ?></td></tr>
                    <tr><th>Tickera</th><td><?php echo MMC_Sales_Service::bridge_detected()?'🟢 Algılandı':'🟡 Algılanmadı / kontrol gerekli'; ?></td></tr>
                    <tr><th>PayTR</th><td><?php echo $paytr['detected']?($paytr['enabled']?'🟢 Aktif — '.esc_html($paytr['title']):'🟡 Algılandı ancak pasif'):'🟡 Otomatik algılanmadı'; ?></td></tr>
                    <tr><th>Satış eşleştirmesi</th><td><?php echo $coverage['complete']?'🟢 Tam':'🟡 Eksik'; ?> — <?php echo esc_html($coverage['mapped'].'/'.$coverage['required']); ?></td></tr>
                </tbody></table>
            </div>
            <div class="mmc-panel"><h2>2. WooCommerce Siparişlerini Senkronla</h2><p>HPOS uyumlu WooCommerce sipariş API'si kullanılır. Yalnız bu etkinliğe eşlenmiş ürün/varyasyon satırları MMC satış defterine alınır.</p>
                <?php if(current_user_can('mmc_manage_sales')||current_user_can('mmc_manage_programs')): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mmc-inline-form"><input type="hidden" name="action" value="mmc_sync_sales"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_sync_sales_'.$event->id,'mmc_nonce'); ?><label>Geriye dönük gün <input type="number" name="lookback_days" value="365" min="1" max="1500" class="small-text"></label><button class="button button-primary">Satışları Şimdi Senkronla</button></form><?php endif; ?>
            </div>
        </div>

        <div class="mmc-panel"><h2>3. Seans × Bilet Satış Eşleştirmesi</h2><p class="description">Mevcut Madagaskar bilet yapısında genellikle her seans bir WooCommerce ana ürününe, çocuk/yetişkin/aile türleri ise varyasyonlara bağlanır. Eşleştirme satış verisinin hangi programa ve seansa ait olduğunu belirler.</p>
            <table class="widefat striped"><thead><tr><th>Seans</th><th>Bilet</th><th>WC Ürün</th><th>WC Varyasyon</th><th>Tickera Etkinlik</th><th>Aktif</th><th></th></tr></thead><tbody>
            <?php foreach($sessions as $s): foreach($tickets as $t): $key=$s->id.':'.$t->id; $m=$map_index[$key]??null; ?>
                <tr><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mmc_save_sales_mapping"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><input type="hidden" name="session_id" value="<?php echo esc_attr($s->id); ?>"><input type="hidden" name="ticket_type_id" value="<?php echo esc_attr($t->id); ?>"><?php wp_nonce_field('mmc_save_sales_mapping_'.$s->id.'_'.$t->id,'mmc_nonce'); ?>
                    <td><?php echo esc_html(mysql2date('d.m.Y H:i',$s->session_time)); ?></td><td><strong><?php echo esc_html($t->ticket_name); ?></strong><br><small><?php echo esc_html($t->capacity_units); ?> kişi kapasite</small></td>
                    <td><input type="number" min="0" name="wc_product_id" value="<?php echo esc_attr($m?$m->wc_product_id:''); ?>" class="small-text"></td>
                    <td><input type="number" min="0" name="wc_variation_id" value="<?php echo esc_attr($m?$m->wc_variation_id:''); ?>" class="small-text"></td>
                    <td><input type="number" min="0" name="tickera_event_id" value="<?php echo esc_attr($m?$m->tickera_event_id:''); ?>" class="small-text"></td>
                    <td><input type="checkbox" name="is_active" value="1" <?php checked($m?((int)$m->is_active===1):true); ?>></td><td><button class="button button-small">Kaydet</button></td>
                </form></tr>
            <?php endforeach; endforeach; ?></tbody></table>
        </div>

        <?php if(class_exists('MDG_DB')): ?><div class="mmc-panel"><h2>4. Mevcut Madagaskar Bilet Yönetiminden Eşleştirme Al</h2><p>Eski MDG etkinlik/seans tablolarındaki WooCommerce ürün, varyasyon ve Tickera etkinlik ID'lerini bu programa taşıyabilir. Canlı ürünlerde değişiklik yapmaz; yalnız MMC eşleştirme tablosunu doldurur.</p>
        <?php if(current_user_can('mmc_manage_sales')||current_user_can('mmc_manage_programs')): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mmc-inline-form"><input type="hidden" name="action" value="mmc_import_legacy_sales_mapping"><input type="hidden" name="event_id" value="<?php echo esc_attr($event->id); ?>"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_import_legacy_sales_mapping_'.$event->id,'mmc_nonce'); ?><label>Eski MDG Event ID <input type="number" min="1" name="legacy_event_id" required></label><button class="button">Eşleştirmeleri İçe Al</button></form><?php endif; ?></div><?php endif; ?>

        <div class="mmc-panel"><h2>5. Seans Bazlı Satış / Doluluk</h2><table class="widefat striped"><thead><tr><th>Seans</th><th>Kapasite</th><th>Satılan kişi</th><th>Kalan</th><th>Doluluk</th><th>Net Ciro</th></tr></thead><tbody><?php if(!$by_session): ?><tr><td colspan="6">Seans yok.</td></tr><?php else: foreach($by_session as $r): ?><tr><td><?php echo esc_html(mysql2date('d.m.Y H:i',$r['session']->session_time)); ?></td><td><?php echo esc_html($r['session']->capacity); ?></td><td><?php echo esc_html($r['sold_capacity']); ?></td><td><?php echo esc_html($r['remaining']); ?></td><td><strong><?php echo esc_html(number_format_i18n($r['occupancy'],1)); ?>%</strong></td><td><?php echo esc_html(number_format_i18n($r['revenue'],2)); ?> TL</td></tr><?php endforeach; endif; ?></tbody></table></div>

        <div class="mmc-panel"><h2>6. Son Eşleşen Sipariş Satırları</h2><table class="widefat striped"><thead><tr><th>Sipariş</th><th>Seans</th><th>Bilet</th><th>Adet</th><th>Kişi</th><th>Net</th><th>Ödeme</th><th>Durum</th><th>Senkron</th></tr></thead><tbody><?php if(!$recent): ?><tr><td colspan="9">Henüz eşleşen sipariş yok. Eşleştirmeleri kaydedip senkronizasyonu çalıştırın.</td></tr><?php else: foreach($recent as $r): ?><tr><td>#<?php echo esc_html($r->external_order_id); ?></td><td><?php echo esc_html(mysql2date('d.m.Y H:i',$r->session_time)); ?></td><td><?php echo esc_html($r->ticket_name); ?></td><td><?php echo esc_html($r->net_quantity); ?></td><td><?php echo esc_html($r->capacity_units); ?></td><td><?php echo esc_html(number_format_i18n((float)$r->net_amount,2)); ?> TL</td><td><?php echo esc_html($r->payment_method_title?:$r->payment_method); ?></td><td><?php echo esc_html($r->order_status); ?></td><td><?php echo esc_html($r->last_synced_at); ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
        <?php
    }

    public function handle_save_mapping(){
        $this->guard_write(); $event_id=absint($_POST['event_id']??0); $session_id=absint($_POST['session_id']??0); $ticket_id=absint($_POST['ticket_type_id']??0); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_save_sales_mapping_'.$session_id.'_'.$ticket_id,'mmc_nonce');
        $r=MMC_Sales_Service::save_mapping($event_id,$session_id,$ticket_id,wp_unslash($_POST)); $this->redirect($pid,$r,'mapping_saved');
    }
    public function handle_sync(){
        $this->guard_write(); $event_id=absint($_POST['event_id']??0); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_sync_sales_'.$event_id,'mmc_nonce'); $r=MMC_Sales_Service::sync_event_orders($event_id,absint($_POST['lookback_days']??365)); $this->redirect($pid,$r,'sales_synced');
    }
    public function handle_legacy_import(){
        $this->guard_write(); $event_id=absint($_POST['event_id']??0); $pid=absint($_POST['program_id']??0); check_admin_referer('mmc_import_legacy_sales_mapping_'.$event_id,'mmc_nonce'); $r=MMC_Sales_Service::import_legacy_mdg_event($event_id,absint($_POST['legacy_event_id']??0)); $this->redirect($pid,$r,'legacy_imported');
    }
    private function redirect($pid,$r,$msg){$args=array('page'=>'mmc-sales','program_id'=>$pid);if(is_wp_error($r))$args['mmc_error']=$r->get_error_message();else{$args['mmc_msg']=$msg;if(is_array($r)){$args['count']=absint($r['matched']??$r['imported']??0);$args['scanned']=absint($r['scanned']??0);}}wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;}
    private function guard($cap){if(!current_user_can($cap))wp_die('Bu sayfayı görüntüleme yetkiniz yok.');}
    private function guard_write(){if(!(current_user_can('mmc_manage_sales')||current_user_can('mmc_manage_programs')))wp_die('Bu işlemi yapma yetkiniz yok.');}
    private function notice(){if(!empty($_GET['mmc_error']))echo '<div class="notice notice-error"><p>'.esc_html(wp_unslash($_GET['mmc_error'])).'</p></div>';if(!empty($_GET['mmc_msg'])){$m=sanitize_key($_GET['mmc_msg']);$map=array('mapping_saved'=>'Satış eşleştirmesi kaydedildi.','sales_synced'=>'WooCommerce satışları senkronlandı.','legacy_imported'=>'Eski MDG satış eşleştirmeleri içe alındı.');$txt=$map[$m]??'İşlem tamamlandı.';if('sales_synced'===$m)$txt.=' Taranan sipariş: '.absint($_GET['scanned']??0).', eşleşen satış satırı: '.absint($_GET['count']??0).'.';if('legacy_imported'===$m)$txt.=' Aktarılan eşleştirme: '.absint($_GET['count']??0).'.';echo '<div class="notice notice-success"><p>'.esc_html($txt).'</p></div>';}}
}
