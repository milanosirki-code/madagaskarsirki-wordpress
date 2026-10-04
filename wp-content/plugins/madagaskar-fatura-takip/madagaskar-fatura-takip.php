<?php
/**
 * Plugin Name: Madagaskar Fatura Takip
 * Description: WooCommerce siparişlerinde fatura kesildi / WhatsApp gönderildi takibi ve Mikro ePortal için hızlı kopyalama ekranı.
 * Version: 1.1.0
 * Author: Dünya Organizasyon
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class MDG_Fatura_Takip_V1 {
    const VERSION = '1.1.0';
    const META_INVOICE_STATUS = '_mdg_invoice_status';
    const META_INVOICE_DATE   = '_mdg_invoice_date';
    const META_WA_STATUS      = '_mdg_invoice_whatsapp_sent';
    const META_WA_DATE        = '_mdg_invoice_whatsapp_date';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ], 99 );
        add_action( 'admin_post_mdg_invoice_mark', [ __CLASS__, 'handle_mark' ] );
        add_action( 'admin_post_mdg_invoice_unmark', [ __CLASS__, 'handle_unmark' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
    }

    public static function menu() {
        add_submenu_page(
            'woocommerce',
            'Fatura Takip',
            'Fatura Takip',
            'manage_woocommerce',
            'mdg-fatura-takip',
            [ __CLASS__, 'render' ]
        );
    }

    public static function assets( $hook ) {
        if ( false === strpos( $hook, 'mdg-fatura-takip' ) ) return;
        wp_add_inline_style( 'woocommerce_admin_styles', self::css() );
        wp_add_inline_script( 'jquery', self::js() );
    }

    private static function css() {
        return '.mdgft-cards{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:14px;max-width:950px;margin:18px 0}.mdgft-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.mdgft-card strong{font-size:24px;display:block;margin-top:6px}.mdgft-ok{color:#008a20;font-weight:700}.mdgft-wait{color:#b32d2e;font-weight:700}.mdgft-mid{color:#996800;font-weight:700}.mdgft-actions{display:flex;gap:6px;flex-wrap:wrap}.mdgft-copy{cursor:pointer}.mdgft-small{font-size:12px;color:#646970}.mdgft-table td{vertical-align:top}.mdgft-pill{display:inline-block;border-radius:999px;padding:3px 8px;background:#f0f0f1}.mdgft-toolbar{background:#fff;border:1px solid #dcdcde;padding:12px;margin:14px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}.mdgft-address{max-width:300px;white-space:normal}.mdgft-product{max-width:300px;white-space:normal}.mdgft-copybox{display:none} @media(max-width:900px){.mdgft-cards{grid-template-columns:1fr}.mdgft-hide-sm{display:none}}';
    }

    private static function js() {
        return <<<'JS'
jQuery(function($){
    $(document).on('click','.mdgft-copy',function(e){
        e.preventDefault();
        var id=$(this).data('copy');
        var el=document.getElementById(id);
        if(!el) return;
        var text=el.value||el.textContent;
        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(text).then(function(){
                var b=$(e.currentTarget),o=b.text();
                b.text('Kopyalandı ✓');
                setTimeout(function(){b.text(o);},1200);
            });
        } else {
            el.style.display='block';
            el.select();
            document.execCommand('copy');
            el.style.display='none';
        }
    });
});
JS;
    }

    private static function get_filter() {
        $f = isset($_GET['filter']) ? sanitize_key( wp_unslash($_GET['filter']) ) : 'pending';
        return in_array( $f, [ 'pending','invoiced','done','all' ], true ) ? $f : 'pending';
    }

    private static function get_orders() {
        if ( ! function_exists( 'wc_get_orders' ) ) return [];
        $orders = wc_get_orders([
            'limit'   => 200,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => array_keys( wc_get_order_statuses() ),
            'return'  => 'objects',
        ]);
        $paid = [];
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) continue;
            if ( ! $order->is_paid() && ! in_array( $order->get_status(), [ 'processing','completed' ], true ) ) continue;
            $paid[] = $order;
        }
        return $paid;
    }

    private static function matches_filter( WC_Order $order, $filter ) {
        $inv = $order->get_meta( self::META_INVOICE_STATUS, true ) === 'issued';
        $wa  = $order->get_meta( self::META_WA_STATUS, true ) === 'yes';
        if ( 'pending' === $filter ) return ! $inv;
        if ( 'invoiced' === $filter ) return $inv && ! $wa;
        if ( 'done' === $filter ) return $inv && $wa;
        return true;
    }

    private static function customer_tax_id( WC_Order $order ) {
        $keys = [
            '_billing_tc_kimlik_no','billing_tc_kimlik_no','tc_kimlik_no','_tc_kimlik_no',
            '_billing_tckn','billing_tckn','tckn','_tckn','_billing_vkn','billing_vkn','vkn','_vkn',
            '_billing_tax_no','billing_tax_no','tax_number','_tax_number'
        ];
        foreach ( $keys as $k ) {
            $v = trim( (string) $order->get_meta( $k, true ) );
            if ( $v !== '' ) return $v;
        }
        return '';
    }

    private static function product_text( WC_Order $order ) {
        $out = [];
        foreach ( $order->get_items() as $item ) {
            $name = $item->get_name();
            $qty  = (int) $item->get_quantity();
            $out[] = $qty . ' x ' . $name;
        }
        return implode("\n", $out);
    }

    private static function address_text( WC_Order $order ) {
        $parts = array_filter([
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_postcode(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_country(),
        ]);
        return implode(', ', $parts);
    }

    /**
     * Sipariş toplamı KDV dahil kabul edilir.
     * %10 KDV, dahil tutardan geriye doğru hesaplanır.
     * Hesap kuruş bazında yapılır; böylece matrah + KDV her zaman toplam tutara eşittir.
     */
    private static function vat_amounts( WC_Order $order ) {
        $rate = 10;
        $gross_cents = (int) round( (float) $order->get_total() * 100 );
        $net_cents   = (int) round( $gross_cents * 100 / ( 100 + $rate ) );
        $vat_cents   = $gross_cents - $net_cents;

        return [
            'rate'  => $rate,
            'net'   => $net_cents / 100,
            'vat'   => $vat_cents / 100,
            'gross' => $gross_cents / 100,
        ];
    }

    /**
     * Kopyalama alanında HTML para sembolü/entity kullanma.
     * Örnek: 750,00 TL
     */
    private static function plain_money( $amount ) {
        return number_format( (float) $amount, 2, ',', '.' ) . ' TL';
    }

    private static function copy_text( WC_Order $order ) {
        $name = trim( $order->get_formatted_billing_full_name() );
        $tax  = self::customer_tax_id( $order );
        $lines = [
            'Sipariş: #' . $order->get_id(),
            'Ad Soyad: ' . $name,
            'Adres: ' . self::address_text( $order ),
            'Telefon: ' . $order->get_billing_phone(),
            'E-posta: ' . $order->get_billing_email(),
        ];
        if ( $tax ) $lines[] = 'T.C./VKN: ' . $tax;
        $lines[] = 'Ürünler:';
        $lines[] = self::product_text( $order );

        $amounts = self::vat_amounts( $order );
        $lines[] = '';
        $lines[] = 'KDV Hariç Tutar: ' . self::plain_money( $amounts['net'] );
        $lines[] = 'KDV Oranı: %' . $amounts['rate'];
        $lines[] = 'KDV Tutarı: ' . self::plain_money( $amounts['vat'] );
        $lines[] = 'KDV Dahil Toplam: ' . self::plain_money( $amounts['gross'] );

        return implode("\n", $lines);
    }

    public static function handle_mark() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die('Yetkiniz yok.');
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $kind = sanitize_key( $_POST['kind'] ?? '' );
        check_admin_referer( 'mdg_invoice_mark_' . $order_id );
        $order = wc_get_order( $order_id );
        if ( ! $order ) wp_die('Sipariş bulunamadı.');
        if ( 'invoice' === $kind ) {
            $order->update_meta_data( self::META_INVOICE_STATUS, 'issued' );
            $order->update_meta_data( self::META_INVOICE_DATE, current_time('mysql') );
            $order->add_order_note( 'Fatura Takip: Fatura kesildi olarak işaretlendi.' );
        } elseif ( 'whatsapp' === $kind ) {
            $order->update_meta_data( self::META_WA_STATUS, 'yes' );
            $order->update_meta_data( self::META_WA_DATE, current_time('mysql') );
            $order->add_order_note( 'Fatura Takip: Fatura WhatsApp ile gönderildi olarak işaretlendi.' );
        }
        $order->save();
        self::redirect_back();
    }

    public static function handle_unmark() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die('Yetkiniz yok.');
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $kind = sanitize_key( $_POST['kind'] ?? '' );
        check_admin_referer( 'mdg_invoice_unmark_' . $order_id );
        $order = wc_get_order( $order_id );
        if ( ! $order ) wp_die('Sipariş bulunamadı.');
        if ( 'invoice' === $kind ) {
            $order->delete_meta_data( self::META_INVOICE_STATUS );
            $order->delete_meta_data( self::META_INVOICE_DATE );
        } elseif ( 'whatsapp' === $kind ) {
            $order->delete_meta_data( self::META_WA_STATUS );
            $order->delete_meta_data( self::META_WA_DATE );
        }
        $order->save();
        self::redirect_back();
    }

    private static function redirect_back() {
        $filter = isset($_POST['filter']) ? sanitize_key( wp_unslash($_POST['filter']) ) : 'pending';
        wp_safe_redirect( admin_url('admin.php?page=mdg-fatura-takip&filter=' . $filter) );
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        if ( ! function_exists( 'wc_get_orders' ) ) {
            echo '<div class="wrap"><h1>Fatura Takip</h1><div class="notice notice-error"><p>WooCommerce aktif değil.</p></div></div>';
            return;
        }
        $orders = self::get_orders();
        $counts = [ 'pending'=>0, 'invoiced'=>0, 'done'=>0 ];
        foreach ( $orders as $o ) {
            $inv = $o->get_meta(self::META_INVOICE_STATUS,true) === 'issued';
            $wa  = $o->get_meta(self::META_WA_STATUS,true) === 'yes';
            if ( ! $inv ) $counts['pending']++;
            elseif ( ! $wa ) $counts['invoiced']++;
            else $counts['done']++;
        }
        $filter = self::get_filter();
        $filtered = array_values( array_filter( $orders, function( $o ) use ( $filter ) { return self::matches_filter( $o, $filter ); } ) );

        echo '<div class="wrap"><h1>🧾 Madagaskar – Fatura Takip</h1>';
        echo '<p>Ödeme alınmış WooCommerce siparişlerini tek ekranda takip edin. Bu ekran sipariş durumunu, PayTR işlemini veya Tickera biletlerini değiştirmez.</p>';
        echo '<div class="mdgft-cards">';
        echo '<div class="mdgft-card">Fatura Bekleyen<strong class="mdgft-wait">'.esc_html($counts['pending']).'</strong></div>';
        echo '<div class="mdgft-card">Fatura Kesildi / WhatsApp Bekliyor<strong class="mdgft-mid">'.esc_html($counts['invoiced']).'</strong></div>';
        echo '<div class="mdgft-card">Tamamlandı<strong class="mdgft-ok">'.esc_html($counts['done']).'</strong></div>';
        echo '</div>';

        $base = admin_url('admin.php?page=mdg-fatura-takip');
        echo '<div class="mdgft-toolbar">';
        foreach ( [ 'pending'=>'Fatura Bekleyen','invoiced'=>'WhatsApp Bekleyen','done'=>'Tamamlanan','all'=>'Tümü' ] as $k=>$label ) {
            $cls = $filter === $k ? 'button button-primary' : 'button';
            echo '<a class="'.$cls.'" href="'.esc_url(add_query_arg('filter',$k,$base)).'">'.esc_html($label).'</a>';
        }
        echo '<span class="mdgft-small">Son 200 ödeme alınmış sipariş gösterilir.</span></div>';

        echo '<table class="widefat striped mdgft-table"><thead><tr><th>Sipariş</th><th>Müşteri / Fatura</th><th class="mdgft-hide-sm">Ürün</th><th>Tutar</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';
        if ( empty($filtered) ) echo '<tr><td colspan="6">Bu filtrede sipariş yok.</td></tr>';
        foreach ( $filtered as $order ) {
            $id = $order->get_id();
            $inv = $order->get_meta(self::META_INVOICE_STATUS,true) === 'issued';
            $wa  = $order->get_meta(self::META_WA_STATUS,true) === 'yes';
            $tax = self::customer_tax_id($order);
            $copy_id = 'mdgft-copy-'.$id;
            $phone = preg_replace('/\D+/', '', (string)$order->get_billing_phone());
            if ( substr($phone,0,1)==='0' ) $phone = '90'.substr($phone,1);
            elseif ( substr($phone,0,2)!=='90' && strlen($phone)===10 ) $phone = '90'.$phone;
            $wa_url = $phone ? 'https://wa.me/'.$phone : '';
            $edit_url = method_exists($order,'get_edit_order_url') ? $order->get_edit_order_url() : admin_url('post.php?post='.$id.'&action=edit');
            echo '<tr>';
            echo '<td><strong>#'.esc_html($id).'</strong><br><span class="mdgft-small">'.esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('d.m.Y H:i') : '').'</span><br><a href="'.esc_url($edit_url).'">Siparişi aç</a></td>';
            echo '<td><strong>'.esc_html($order->get_formatted_billing_full_name()).'</strong><br><span class="mdgft-address">'.esc_html(self::address_text($order)).'</span><br>'.($tax ? 'T.C./VKN: <code>'.esc_html($tax).'</code><br>' : '').esc_html($order->get_billing_phone()).'<br>'.esc_html($order->get_billing_email()).'<textarea class="mdgft-copybox" id="'.esc_attr($copy_id).'">'.esc_textarea(self::copy_text($order)).'</textarea></td>';
            echo '<td class="mdgft-hide-sm mdgft-product">'.nl2br(esc_html(self::product_text($order))).'</td>';
            $amounts = self::vat_amounts( $order );
            echo '<td><strong>'.esc_html(self::plain_money($amounts['gross'])).'</strong><br><span class="mdgft-small">KDV hariç: '.esc_html(self::plain_money($amounts['net'])).'<br>KDV %'.esc_html($amounts['rate']).': '.esc_html(self::plain_money($amounts['vat'])).'</span></td>';
            echo '<td>';
            echo $inv ? '<div class="mdgft-ok">✓ Fatura kesildi</div><div class="mdgft-small">'.esc_html($order->get_meta(self::META_INVOICE_DATE,true)).'</div>' : '<div class="mdgft-wait">● Fatura bekliyor</div>';
            echo $wa ? '<div class="mdgft-ok">✓ WhatsApp gönderildi</div><div class="mdgft-small">'.esc_html($order->get_meta(self::META_WA_DATE,true)).'</div>' : '<div class="mdgft-mid">● WhatsApp bekliyor</div>';
            echo '</td>';
            echo '<td><div class="mdgft-actions">';
            echo '<button type="button" class="button mdgft-copy" data-copy="'.esc_attr($copy_id).'">Mikro için kopyala</button>';
            if ( $wa_url ) echo '<a class="button" target="_blank" rel="noopener" href="'.esc_url($wa_url).'">WhatsApp aç</a>';
            if ( ! $inv ) self::mark_form($id,'invoice','Fatura Kesildi','mdg_invoice_mark');
            else self::mark_form($id,'invoice','Fatura işaretini kaldır','mdg_invoice_unmark');
            if ( $inv && ! $wa ) self::mark_form($id,'whatsapp','WhatsApp Gönderildi','mdg_invoice_mark');
            elseif ( $wa ) self::mark_form($id,'whatsapp','WhatsApp işaretini kaldır','mdg_invoice_unmark');
            echo '</div></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="mdgft-small" style="margin-top:16px">Not: Bu sürüm faturayı Mikro ePortal üzerinde otomatik oluşturmaz; manuel faturayı hızlandırır ve hangi siparişin faturalandığını kalıcı olarak WooCommerce sipariş meta verisinde takip eder. Mikro tarafından resmi API/entegrasyon erişimi sağlanırsa sonraki sürümde fatura oluşturma ve PDF/WhatsApp gönderimi otomatikleştirilebilir.</p>';
        echo '</div>';
    }

    private static function mark_form( $order_id, $kind, $label, $action ) {
        $filter = self::get_filter();
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">';
        echo '<input type="hidden" name="action" value="'.esc_attr($action).'">';
        echo '<input type="hidden" name="order_id" value="'.absint($order_id).'">';
        echo '<input type="hidden" name="kind" value="'.esc_attr($kind).'">';
        echo '<input type="hidden" name="filter" value="'.esc_attr($filter).'">';
        wp_nonce_field( ( 'mdg_invoice_unmark' === $action ? 'mdg_invoice_unmark_' : 'mdg_invoice_mark_' ) . $order_id );
        $class = ( $kind === 'invoice' && $action === 'mdg_invoice_mark' ) ? 'button button-primary' : 'button';
        echo '<button type="submit" class="'.esc_attr($class).'">'.esc_html($label).'</button></form>';
    }
}

MDG_Fatura_Takip_V1::init();
