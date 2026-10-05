<?php
/** Read-only campaign sales report. Woo order CRUD supports HPOS and legacy storage. */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('MDG_Campaign_Sales_Report_20261005',false)) {
final class MDG_Campaign_Sales_Report_20261005 {
    const PAGE='mdg-campaign-sales';
    const SIZE=50;
    public static function menu() {
        add_submenu_page('woocommerce','Kampanya Satışları','Kampanya Satışları','manage_woocommerce',self::PAGE,array(__CLASS__,'page'));
    }
    public static function code($raw) {
        return class_exists('MDG_Corporate_Campaigns_20261005') ? MDG_Corporate_Campaigns_20261005::normalize($raw) : (is_string($raw)?sanitize_key($raw):'');
    }
    public static function query($code,$page) {
        global $wpdb;
        $items=$wpdb->prefix.'woocommerce_order_items'; $meta=$wpdb->prefix.'woocommerce_order_itemmeta';
        $where="m.meta_key='_mdg_campaign_code' AND m.meta_value<>''";
        if($code!==''){$where.=$wpdb->prepare(' AND m.meta_value=%s',$code);}
        $join="FROM {$items} i INNER JOIN {$meta} m ON m.order_item_id=i.order_item_id WHERE {$where}";
        $count=(int)$wpdb->get_var("SELECT COUNT(DISTINCT i.order_id) {$join}");
        $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT i.order_id {$join} ORDER BY i.order_id DESC LIMIT %d OFFSET %d",self::SIZE,(max(1,$page)-1)*self::SIZE));
        $codes=$wpdb->get_col("SELECT DISTINCT meta_value FROM {$meta} WHERE meta_key='_mdg_campaign_code' AND meta_value<>'' ORDER BY meta_value LIMIT 500");
        return array('count'=>$count,'ids'=>$ids,'codes'=>$codes);
    }
    public static function summarize($order,$filter='') {
        $groups=array();
        foreach($order->get_items('line_item') as $id=>$item){
            $code=(string)$item->get_meta('_mdg_campaign_code');
            if($code==='' || ($filter!=='' && $code!==$filter))continue;
            $sid=(int)$item->get_meta('_mdg_session_id');
            $product=$item->get_product();
            if(!$sid && $product){$sid=(int)$product->get_meta('_mdg_session_id');}
            $key=$code.'|'.$sid;
            if(!isset($groups[$key])){$groups[$key]=array('code'=>$code,'session'=>$sid,'roles'=>array('adult'=>0,'paid_child'=>0,'free_child'=>0,'infant'=>0),'refunded'=>0,'gross'=>0.0,'refund'=>0.0,'net'=>0.0,'paid'=>$order->is_paid() || (bool)$order->get_date_paid());}
            $g=&$groups[$key];$qty=(int)$item->get_quantity();$role=(string)$item->get_meta('_mdg_campaign_role');
            if(isset($g['roles'][$role])){$g['roles'][$role]+=$qty;}
            $g['refunded']+=abs((int)$order->get_qty_refunded_for_item($id));
            $gross=(float)$item->get_total()+(float)$item->get_total_tax();
            $refund=(float)$order->get_total_refunded_for_item($id);
            $taxes=$item->get_taxes();
            foreach(array_keys($taxes['total']??array()) as $rate){$refund+=(float)$order->get_tax_refunded_for_item($id,$rate);}
            $g['gross']+=$gross;$g['refund']+=$refund;
            $g['net']+= $g['paid'] ? max(0,$gross-$refund) : 0;
            unset($g);
        }
        return array_values($groups);
    }
    public static function session_label($sid) {
        if(!$sid || !class_exists('MDG_DB'))return 'Seans bilgisi bulunamadı';
        global $wpdb;
        $s=$wpdb->get_row($wpdb->prepare('SELECT event_id,start_at FROM '.MDG_DB::table('sessions').' WHERE id=%d',$sid));
        if(!$s)return 'Seans #'.$sid;
        $e=class_exists('MDG_Events')?MDG_Events::get((int)$s->event_id):null;
        $dt=class_exists('MDG_Sessions')?implode(' · ',MDG_Sessions::local_parts($s->start_at)):$s->start_at;
        return ($e?(string)$e->province_name.' · '.(string)$e->title.' · ':'').$dt;
    }
    public static function render($orders,$codes,$count,$page,$filter) {
        $registry=class_exists('MDG_Corporate_Campaigns_20261005')?MDG_Corporate_Campaigns_20261005::registry():array();
        $rows=array();$paid_total=0.0;$paid_count=0;
        foreach($orders as $order){foreach(self::summarize($order,$filter) as $g){$rows[]=array($order,$g);$paid_total+=$g['net'];if($g['paid'])$paid_count++;}}
        ob_start(); ?>
        <div class="wrap"><h1>Kampanya Satışları</h1>
        <p>Kurum koduyla alınan biletler. Bu ekran yalnız siparişleri okur; bilet ve ödeme durumunu değiştirmez.</p>
        <form method="get"><input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>">
        <label>Kampanya kodu <select name="campaign_code"><option value="">Tüm kampanyalar</option>
        <?php foreach(array_unique(array_merge(array_keys($registry),$codes)) as $code): ?><option value="<?php echo esc_attr($code); ?>" <?php selected($filter,$code); ?>><?php echo esc_html($code.' · '.($registry[$code]['name']??'Kayıtlı kampanya')); ?></option><?php endforeach; ?>
        </select></label> <button class="button">Filtrele</button></form>
        <p><strong>Bu sayfadaki tahsilat (satır bazlı iadeler düşülmüş): <?php echo wp_kses_post(wc_price($paid_total)); ?></strong></p>
        <p><?php echo esc_html($count); ?> kampanyalı sipariş · Sayfa <?php echo esc_html($page); ?>. En fazla 50 sipariş gösterilir. Bilet adetleri satın alınan adetlerdir; iadeler ayrı sütundadır. Bekleyen/başarısız ödemeler tahsilata dahil edilmez. Bu ekran giriş yapan kişi sayısını göstermez.</p>
        <div style="overflow-x:auto"><table class="widefat striped"><thead><tr>
        <?php foreach(array('Sipariş / Tarih','Kurum / Kod','Şehir / Gösteri / Seans','Yetişkin','Ücretli çocuk','Ücretsiz çocuk','0–2 yaş','İade edilen bilet','Kampanya tutarı','İade tutarı','Net tahsilat','Ödeme durumu') as $title): ?><th><?php echo esc_html($title); ?></th><?php endforeach; ?>
        </tr></thead><tbody>
        <?php if(!$rows): ?><tr><td colspan="12">Bu filtreye ait kampanyalı sipariş bulunmuyor.</td></tr><?php endif; ?>
        <?php foreach($rows as $row): list($order,$g)=$row; $date=$order->get_date_created(); ?>
        <tr><td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a><br><?php echo esc_html($date?$date->date_i18n('d.m.Y H:i'):''); ?></td>
        <td><?php echo esc_html($registry[$g['code']]['name']??'Kampanya'); ?><br><code><?php echo esc_html($g['code']); ?></code></td>
        <td><?php echo esc_html(self::session_label($g['session'])); ?></td>
        <?php foreach($g['roles'] as $qty): ?><td><?php echo esc_html($qty); ?></td><?php endforeach; ?>
        <td><?php echo esc_html($g['refunded']); ?></td>
        <?php foreach(array('gross','refund','net') as $amount): ?><td><?php echo wp_kses_post(wc_price($g[$amount])); ?></td><?php endforeach; ?>
        <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
        <p><?php if($page>1): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>self::PAGE,'campaign_code'=>$filter,'paged'=>$page-1),admin_url('admin.php'))); ?>">Önceki</a> <?php endif; ?>
        <?php if($page*self::SIZE<$count): ?><a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>self::PAGE,'campaign_code'=>$filter,'paged'=>$page+1),admin_url('admin.php'))); ?>">Sonraki</a><?php endif; ?></p>
        </div><?php return ob_get_clean();
    }
    public static function page() {
        if(!current_user_can('manage_woocommerce')){wp_die('Bu ekran için yetkiniz yok.');}
        if(!function_exists('wc_get_order')){echo '<div class="wrap"><p>WooCommerce kullanılamıyor.</p></div>';return;}
        $raw=$_GET['campaign_code']??''; $code=self::code(is_string($raw)?wp_unslash($raw):'');
        if(is_string($raw) && $raw!=='' && $code===''){echo '<div class="wrap"><p>Kampanya kodu geçersiz.</p></div>';return;}
        $page=max(1,min(100000,absint($_GET['paged']??1)));$data=self::query($code,$page);$orders=array();
        foreach($data['ids'] as $id){$order=wc_get_order((int)$id);if($order)$orders[]=$order;}
        echo self::render($orders,$data['codes'],$data['count'],$page,$code);
    }
    public static function rest() {
        register_rest_route('mdg-campaign-sales/v1','/report',array('methods'=>'GET','permission_callback'=>static function(){return current_user_can('manage_woocommerce');},'callback'=>static function($request){
            $code=self::code((string)$request->get_param('code'));$page=max(1,min(100000,absint($request->get_param('page'))));
            $data=self::query($code,$page);$orders=array();foreach($data['ids'] as $id){$o=wc_get_order((int)$id);if($o)$orders[]=$o;}
            return array('count'=>$data['count'],'page'=>$page,'html'=>self::render($orders,$data['codes'],$data['count'],$page,$code));
        }));
    }
}
add_action('admin_menu',array('MDG_Campaign_Sales_Report_20261005','menu'));
add_action('rest_api_init',array('MDG_Campaign_Sales_Report_20261005','rest'));
}
