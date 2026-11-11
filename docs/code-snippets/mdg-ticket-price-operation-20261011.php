<?php
/* Temporary, authenticated migration diagnostics. No payment/order/ticket writes.
 * Rollback: original prices in mdg_price_change_20261011_backup. Deactivate after verification.
 */
if (!defined('ABSPATH')) { exit; }
final class MDG_Price_Change_20261011 {
    const BACKUP = 'mdg_price_change_20261011_backup';
    public static function plan() { return json_decode('[{"id":201,"event":10,"session":88,"parent":2387,"variation":2388,"old":300,"price":290},{"id":202,"event":10,"session":88,"parent":2387,"variation":2389,"old":600,"price":585},{"id":203,"event":10,"session":89,"parent":2390,"variation":2391,"old":300,"price":290},{"id":204,"event":10,"session":89,"parent":2390,"variation":2392,"old":600,"price":585},{"id":205,"event":10,"session":90,"parent":2393,"variation":2394,"old":300,"price":290},{"id":206,"event":10,"session":90,"parent":2393,"variation":2395,"old":600,"price":585},{"id":241,"event":13,"session":104,"parent":2433,"variation":2434,"old":250,"price":245},{"id":242,"event":13,"session":104,"parent":2433,"variation":2435,"old":500,"price":490},{"id":243,"event":13,"session":105,"parent":2436,"variation":2437,"old":250,"price":245},{"id":244,"event":13,"session":105,"parent":2436,"variation":2438,"old":500,"price":490},{"id":245,"event":13,"session":106,"parent":2439,"variation":2440,"old":250,"price":245},{"id":246,"event":13,"session":106,"parent":2439,"variation":2441,"old":500,"price":490},{"id":290,"event":19,"session":124,"parent":2951,"variation":2952,"old":250,"price":245},{"id":291,"event":19,"session":124,"parent":2951,"variation":2953,"old":500,"price":490},{"id":292,"event":19,"session":125,"parent":2954,"variation":2955,"old":250,"price":245},{"id":293,"event":19,"session":125,"parent":2954,"variation":2956,"old":500,"price":490},{"id":306,"event":21,"session":132,"parent":2990,"variation":2991,"old":250,"price":245},{"id":307,"event":21,"session":132,"parent":2990,"variation":2992,"old":500,"price":490},{"id":308,"event":21,"session":133,"parent":2993,"variation":2994,"old":250,"price":245},{"id":309,"event":21,"session":133,"parent":2993,"variation":2995,"old":500,"price":490},{"id":310,"event":21,"session":134,"parent":2996,"variation":2997,"old":250,"price":245},{"id":311,"event":21,"session":134,"parent":2996,"variation":2998,"old":500,"price":490},{"id":320,"event":22,"session":139,"parent":3443,"variation":3444,"old":250,"price":245},{"id":321,"event":22,"session":139,"parent":3443,"variation":3445,"old":500,"price":490},{"id":322,"event":22,"session":140,"parent":3446,"variation":3447,"old":250,"price":245},{"id":323,"event":22,"session":140,"parent":3446,"variation":3448,"old":500,"price":490},{"id":336,"event":23,"session":147,"parent":3480,"variation":3481,"old":250,"price":245},{"id":337,"event":23,"session":147,"parent":3480,"variation":3482,"old":500,"price":490},{"id":338,"event":23,"session":148,"parent":3483,"variation":3484,"old":250,"price":245},{"id":339,"event":23,"session":148,"parent":3483,"variation":3485,"old":500,"price":490},{"id":340,"event":23,"session":149,"parent":3486,"variation":3487,"old":250,"price":245},{"id":341,"event":23,"session":149,"parent":3486,"variation":3488,"old":500,"price":490}]', true); }
    public static function permission() { return current_user_can('manage_woocommerce') && current_user_can('manage_options'); }
    public static function monetary_rows($ids = null) {
        global $wpdb;
        $vars = implode(',', array_column(self::plan(), 'variation'));
        $filter = $ids === null ? "oi.order_item_id IN (SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key='_variation_id' AND meta_value IN ($vars))"
            : 'oi.order_item_id IN (' . implode(',', array_map('absint', $ids ?: array(0))) . ')';
        return $wpdb->get_results("SELECT oi.order_item_id,oi.order_id,m.meta_key,m.meta_value
            FROM {$wpdb->prefix}woocommerce_order_items oi JOIN {$wpdb->prefix}woocommerce_order_itemmeta m ON m.order_item_id=oi.order_item_id
            WHERE $filter AND m.meta_key IN ('_qty','_variation_id','_product_id','_line_subtotal','_line_total','_line_tax','_line_subtotal_tax')
            ORDER BY oi.order_item_id,m.meta_key,m.meta_id", ARRAY_A);
    }
    public static function ticket_rows($ids = null) {
        global $wpdb;
        if ($ids === null) {
            $money = self::monetary_rows();
            $orders = array_unique(array_column($money,'order_id'));
            $filter = 'p.post_parent IN (' . implode(',', array_map('absint',$orders ?: array(0))) . ')';
        } else { $filter = 'p.ID IN (' . implode(',', array_map('absint',$ids ?: array(0))) . ')'; }
        return $wpdb->get_results("SELECT p.ID,p.post_parent,m.meta_key,m.meta_value FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key IN ('ticket_code','item_id','ticket_type_id','event_id')
            WHERE p.post_type='tc_tickets_instances' AND $filter ORDER BY p.ID,m.meta_key,m.meta_id", ARRAY_A);
    }
    public static function state() {
        global $wpdb;
        $catalog = array();
        foreach (self::plan() as $r) {
            $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mdg_ticket_types WHERE id=%d",$r['id']), ARRAY_A);
            $v = wc_get_product($r['variation']);
            if (!$t || !$v || $v->get_parent_id() !== $r['parent']
                || (int)$t['wc_variation_id'] !== $r['variation'] || (int)$t['session_id'] !== $r['session']
                || (int)$v->get_meta('_mdg_event_id') !== $r['event'] || $v->get_status() !== 'publish') {
                return new WP_Error('price_identity','Bilet eşleşmesi değişti.',array('status'=>409));
            }
            $catalog[] = array('plan'=>$r,'mdg'=>$t,'wc'=>array('regular'=>$v->get_regular_price(),'sale'=>$v->get_sale_price(),'price'=>$v->get_price()));
        }
        $mmc = $wpdb->get_results("SELECT t.* FROM {$wpdb->prefix}mmc_ticket_types t JOIN {$wpdb->prefix}mmc_mdg_event_bridge b ON b.mmc_event_id=t.event_id
            WHERE b.is_active=1 AND b.mdg_event_id IN (10,13,19,21,22,23) ORDER BY t.id", ARRAY_A);
        $channels = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mmc_channel_prices WHERE event_id IN (4,5,6,7,12,13) ORDER BY id", ARRAY_A);
        $money = self::monetary_rows();
        $tickets = self::ticket_rows();
        return array('catalog'=>$catalog,'mmc'=>$mmc,'channels'=>$channels,'family_option'=>get_option('mdg_family_package_22_v1'),
            'item_ids'=>array_values(array_unique(array_column($money,'order_item_id'))),'money_hash'=>hash('sha256',wp_json_encode($money)),
            'ticket_ids'=>array_values(array_unique(array_column($tickets,'ID'))),'ticket_hash'=>hash('sha256',wp_json_encode($tickets)),
            'sample_summary_hash'=>self::sample_summary_hash());
    }
    public static function sample_summary_hash() {
        $out=array();
        foreach (array(5071,5076,5078) as $id) {
            $data=function_exists('mdg_ticket_price_summary_data')?mdg_ticket_price_summary_data(array('ticket_id'=>$id)):array();
            $out[$id]=hash('sha256',wp_json_encode($data));
        }
        return $out;
    }
    public static function preview() {
        $s=self::state(); if(is_wp_error($s))return $s;
        return array('state_hash'=>hash('sha256',wp_json_encode($s)), 'catalog'=>$s['catalog'],'mmc'=>$s['mmc'],
            'old_items'=>count($s['item_ids']),'old_tickets'=>count($s['ticket_ids']),'money_hash'=>$s['money_hash'],'ticket_hash'=>$s['ticket_hash']);
    }
    public static function backup($request) {
        if(get_option(self::BACKUP)) return array('existing'=>true);
        $s=self::state(); if(is_wp_error($s))return $s;
        if(!hash_equals(hash('sha256',wp_json_encode($s)),(string)$request->get_param('state_hash')))
            return new WP_Error('state_changed','Önizleme yenilenmeli.',array('status'=>409));
        add_option(self::BACKUP,$s,'',false);
        return array('saved'=>true,'old_items'=>count($s['item_ids']),'old_tickets'=>count($s['ticket_ids']));
    }
    public static function mdg_apply() {
        global $wpdb;
        if(!get_option(self::BACKUP) || !function_exists('mdg_family_refresh_discount_20261011'))
            return new WP_Error('missing_backup','Yedek veya aile koruması yok.',array('status'=>409));
        $s=self::state(); if(is_wp_error($s))return $s;
        foreach($s['catalog'] as $r) {
            if(!in_array((float)$r['mdg']['price'],array((float)$r['plan']['old'],(float)$r['plan']['price']),true))
                return new WP_Error('unexpected_price','Beklenmeyen fiyat.',array('status'=>409));
        }
        $wpdb->query('START TRANSACTION');
        foreach(self::plan() as $r) {
            $ok=$wpdb->update($wpdb->prefix.'mdg_ticket_types',array('price'=>number_format($r['price'],2,'.',''),'updated_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$r['id'],'wc_variation_id'=>$r['variation']));
            if(false===$ok){$wpdb->query('ROLLBACK');return new WP_Error('price_write','Fiyat kaydedilemedi.',array('status'=>500));}
        }
        $wpdb->query('COMMIT');
        return array('updated'=>count(self::plan()));
    }
    public static function verify() {
        $b=get_option(self::BACKUP); if(!$b)return new WP_Error('no_backup','Yedek yok.');
        $s=self::state(); if(is_wp_error($s))return $s;
        $matches=array();
        foreach($s['catalog'] as $r) {
            $matches[]=array('variation'=>$r['plan']['variation'],'mdg'=>$r['mdg']['price'],'wc'=>$r['wc']['price'],
                'ok'=>abs((float)$r['mdg']['price']-$r['plan']['price'])<.009 && abs((float)$r['wc']['price']-$r['plan']['price'])<.009);
        }
        return array('catalog'=>$matches,'old_items_unchanged'=>hash_equals($b['money_hash'],hash('sha256',wp_json_encode(self::monetary_rows($b['item_ids'])))),
            'old_tickets_unchanged'=>hash_equals($b['ticket_hash'],hash('sha256',wp_json_encode(self::ticket_rows($b['ticket_ids'])))),
            'old_pdf_price_data_unchanged'=>$b['sample_summary_hash']===self::sample_summary_hash(), 'mmc'=>$s['mmc'],'family_tests'=>self::family_tests());
    }
    public static function family_tests() {
        if(!function_exists('mdg_family_refresh_discount_20261011'))return array('missing_guard');
        $tests=array();
        foreach(array(array(23,3481,3482,1100,400),array(10,2388,2389,1300,500)) as $case) {
            foreach(array(1,2) as $packages) {
                $cart=new MDG_Price_Test_Cart_20261011();
                foreach(array('child'=>$case[1],'adult'=>$case[2]) as $role=>$id) {
                    $cart->cart_contents[$role]=array('data'=>wc_get_product($id),'quantity'=>2*$packages,'mdg_family22'=>1,
                        'mdg_family22_group'=>'test','mdg_family22_event_id'=>$case[0],'mdg_family22_component'=>$role,
                        'mdg_family22_packages'=>$packages,'mdg_family22_discount_per'=>$case[4],'mdg_family22_package_price'=>$case[3]);
                }
                mdg_family_refresh_discount_20261011($cart);
                MDG_Family_Package_22_V1::apply_family_discount($cart);
                $gross=0;foreach($cart->get_cart() as $item)$gross+=(float)$item['data']->get_price()*$item['quantity'];
                $total=$gross+array_sum($cart->fees);
                $tests[]=array('event'=>$case[0],'packages'=>$packages,'total'=>$total,'expected'=>$case[3]*$packages,'ok'=>abs($total-$case[3]*$packages)<.009);
                $cart->cart_contents['child']['quantity']=1;$cart->fees=array();
                mdg_family_refresh_discount_20261011($cart);MDG_Family_Package_22_V1::apply_family_discount($cart);
                $tests[]=array('event'=>$case[0],'partial_package_no_discount'=>array_sum($cart->fees)===0);
            }
        }
        return $tests;
    }
}
final class MDG_Price_Test_Cart_20261011 {
    public $cart_contents=array(); public $fees=array();
    public function get_cart(){return $this->cart_contents;}
    public function add_fee($name,$amount,$taxable=false){$this->fees[]=$amount;}
}
add_action('rest_api_init',function(){
    foreach(array('preview'=>'GET','backup'=>'POST','mdg-apply'=>'POST','verify'=>'GET') as $route=>$method){
        register_rest_route('mdg-price-change/v1','/'.$route,array('methods'=>$method,
            'permission_callback'=>array('MDG_Price_Change_20261011','permission'),
            'callback'=>array('MDG_Price_Change_20261011',str_replace('-','_',$route))));
    }
});
