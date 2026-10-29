<?php
/**
 * Plugin Name: Madagaskar Yüz Boyama POS
 * Description: Yüz boyama için POS benzeri hızlı ödeme. [mdg_yuzboyama_pos] kısa kodu kişi sayısını alır, fatura formu göstermeden siparişi hazırlar ve doğrudan kart ödeme ekranına yönlendirir. Ödeme sonrası teşekkür sayfasında büyük işlem kodu gösterir. Bilet ödeme sayfasına (/odeme/) dokunmaz.
 * Version: 1.0.0
 * Author: Madagaskar Sirki
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MDG_YBPOS_PRODUCT_ID = 9012;
const MDG_YBPOS_MAX_QTY    = 50;
const MDG_YBPOS_META_CODE  = '_mdg_yb_pos_kod';
const MDG_YBPOS_META_FLAG  = '_mdg_yb_pos';

/* Sabit fatura bilgileri: müşteriden bilgi istenmez, ödeme altyapısı için doldurulur. */
function mdg_ybpos_billing() {
	return array(
		'first_name' => 'Yüz Boyama',
		'last_name'  => 'Müşterisi',
		'email'      => 'madagaskarsirkiturkiye@gmail.com',
		'phone'      => '03129113710',
		'address_1'  => 'Etkinlik Alanı - Yüz Boyama',
		'city'       => 'Ankara',
		'state'      => '',
		'postcode'   => '06000',
		'country'    => 'TR',
	);
}

function mdg_ybpos_is_pos_order( $order ) {
	return $order instanceof WC_Order && 'yes' === $order->get_meta( MDG_YBPOS_META_FLAG );
}

/* ---------- Kısa kod ---------- */
add_shortcode( 'mdg_yuzboyama_pos', function () {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}
	$product = wc_get_product( MDG_YBPOS_PRODUCT_ID );
	if ( ! $product || ! $product->is_purchasable() ) {
		return '<section class="mdg-yb-card"><h2>Yüz boyama ödemesi şu an kapalı.</h2><p>Lütfen görevliye danışın.</p></section>';
	}
	$price = (float) wc_get_price_to_display( $product );
	$error = isset( $_GET['yb_hata'] ) ? sanitize_key( wp_unslash( $_GET['yb_hata'] ) ) : '';
	$msgs  = array(
		'yogun' => 'Çok fazla deneme yapıldı. Lütfen birkaç dakika sonra tekrar deneyin.',
		'genel' => 'Ödeme başlatılamadı. Lütfen tekrar deneyin veya görevliye danışın.',
	);
	ob_start();
	?>
	<section class="mdg-yb-card">
		<p class="mdg-yb-kicker">MADAGASKAR SİRKİ · ETKİNLİK ALANI</p>
		<h2>Biraz renk,<br>kocaman bir gülümseme.</h2>
		<p>Kişi sayısını seçin, kartınızla ödeyin. Ekranda çıkan kodu yüz boyama görevlisine gösterin.</p>
		<div class="mdg-yb-price"><strong><?php echo esc_html( wc_format_localized_price( $price ) ); ?> TL</strong><span> / kişi</span></div>
		<?php if ( isset( $msgs[ $error ] ) ) : ?>
			<p class="mdg-yb-small" role="alert"><?php echo esc_html( $msgs[ $error ] ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( get_permalink() ); ?>">
			<input type="hidden" name="mdg_ybpos_buy" value="1">
			<label for="mdg-yb-qty">Kaç kişi?</label>
			<div class="mdg-yb-quantity">
				<button type="button" data-yb-step="-1" aria-label="Kişi sayısını azalt">−</button>
				<input id="mdg-yb-qty" type="number" inputmode="numeric" min="1" max="<?php echo (int) MDG_YBPOS_MAX_QTY; ?>" step="1" name="quantity" value="1" required>
				<button type="button" data-yb-step="1" aria-label="Kişi sayısını artır">+</button>
			</div>
			<p class="mdg-yb-total">Toplam <strong id="mdg-yb-total" data-unit="<?php echo esc_attr( $price ); ?>" aria-live="polite"><?php echo esc_html( wc_format_localized_price( $price ) ); ?> TL</strong></p>
			<button class="mdg-yb-primary" type="submit">Kartla öde</button>
		</form>
		<p class="mdg-yb-small">Bilgi girmenize gerek yok, doğrudan kart ekranı açılır. Bu ödeme yalnız yüz boyama içindir; gösteri giriş bileti değildir.</p>
	</section>
	<script>
	(function(){
		var q=document.getElementById('mdg-yb-qty'),t=document.getElementById('mdg-yb-total');
		if(!q||!t)return;
		var unit=parseFloat(t.getAttribute('data-unit'))||0,max=<?php echo (int) MDG_YBPOS_MAX_QTY; ?>;
		function fmt(n){return n.toLocaleString('tr-TR',{maximumFractionDigits:2})+' TL';}
		function upd(){var v=parseInt(q.value,10);if(isNaN(v)||v<1)v=1;if(v>max)v=max;q.value=v;t.textContent=fmt(v*unit);}
		document.querySelectorAll('.mdg-yb-quantity [data-yb-step]').forEach(function(b){
			b.addEventListener('click',function(){q.value=(parseInt(q.value,10)||1)+parseInt(b.getAttribute('data-yb-step'),10);upd();});
		});
		q.addEventListener('input',function(){var v=parseInt(q.value,10);if(!isNaN(v)&&v>0)t.textContent=fmt(Math.min(v,max)*unit);});
		q.addEventListener('change',upd);
		var f=q.form;if(f)f.addEventListener('submit',function(){var b=f.querySelector('.mdg-yb-primary');if(b){b.disabled=true;b.textContent='Kart ekranı açılıyor…';}});
	})();
	</script>
	<?php
	return ob_get_clean();
} );

/* ---------- Form gönderimi: sipariş oluştur, kart ekranına yönlendir ---------- */
add_action( 'template_redirect', function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['mdg_ybpos_buy'] ) ) {
		return;
	}
	$back = wp_get_referer() ? wp_get_referer() : home_url( '/yuzboyama/' );
	$back = remove_query_arg( 'yb_hata', $back );

	// Basit kötüye kullanım sınırı: IP başına 10 dakikada en fazla 15 deneme.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
	$key = 'mdg_ybpos_rl_' . md5( $ip );
	$hit = (int) get_transient( $key );
	if ( $hit >= 15 ) {
		wp_safe_redirect( add_query_arg( 'yb_hata', 'yogun', $back ) );
		exit;
	}
	set_transient( $key, $hit + 1, 10 * MINUTE_IN_SECONDS );

	$qty = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
	$qty = max( 1, min( MDG_YBPOS_MAX_QTY, $qty ) );

	try {
		$product = wc_get_product( MDG_YBPOS_PRODUCT_ID );
		if ( ! $product || ! $product->is_purchasable() ) {
			throw new Exception( 'urun' );
		}
		$order = wc_create_order( array( 'status' => 'pending', 'created_via' => 'mdg_yuzboyama_pos' ) );
		if ( is_wp_error( $order ) ) {
			throw new Exception( 'siparis' );
		}
		$order->add_product( $product, $qty );
		$order->set_address( mdg_ybpos_billing(), 'billing' );
		$order->set_customer_ip_address( $ip );

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		foreach ( $gateways as $id => $gw ) {
			if ( 'yes' === $gw->enabled && false !== stripos( $id, 'paytr' ) ) {
				$order->set_payment_method( $gw );
				break;
			}
		}

		$code = 'YB-' . str_pad( (string) wp_rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
		$order->update_meta_data( MDG_YBPOS_META_FLAG, 'yes' );
		$order->update_meta_data( MDG_YBPOS_META_CODE, $code );
		$order->calculate_totals();
		$order->save();
		$order->add_order_note( sprintf( 'Yüz boyama POS siparişi: %d kişi. İşlem kodu: %s', $qty, $code ) );

		if ( WC()->session ) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
			WC()->session->set( 'order_awaiting_payment', $order->get_id() );
		}

		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	} catch ( Exception $e ) {
		wp_safe_redirect( add_query_arg( 'yb_hata', 'genel', $back ) );
		exit;
	}
}, 1 );

/* Misafir siparişinde ödeme ve teşekkür ekranında e-posta doğrulaması istenmesin. */
add_filter( 'woocommerce_order_email_verification_required', function ( $required, $order ) {
	return mdg_ybpos_is_pos_order( $order ) ? false : $required;
}, 10, 2 );

/* Ödeme ekranında PayTR'yi otomatik seç. */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() ) {
		return;
	}
	$order_id = absint( get_query_var( 'order-pay' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	if ( ! mdg_ybpos_is_pos_order( $order ) ) {
		return;
	}
	?>
	<script>
	(function(){var r=document.querySelector('#order_review input[name="payment_method"][value*="paytr"]');if(r&&!r.checked){r.checked=true;r.dispatchEvent(new Event('change',{bubbles:true}));}})();
	</script>
	<?php
} );

/* ---------- Teşekkür ekranı: büyük işlem kodu ---------- */
add_action( 'woocommerce_before_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! mdg_ybpos_is_pos_order( $order ) ) {
		return;
	}
	$code = (string) $order->get_meta( MDG_YBPOS_META_CODE );
	$qty  = 0;
	foreach ( $order->get_items() as $item ) {
		$qty += (int) $item->get_quantity();
	}
	$paid = $order->is_paid();
	?>
	<div style="max-width:520px;margin:0 auto 24px;padding:26px 20px;border-radius:24px;text-align:center;background:<?php echo $paid ? '#eafaf1' : '#fff8e6'; ?>;box-shadow:0 12px 36px rgba(0,0,0,.08)">
		<p style="margin:0 0 6px;font-weight:800;font-size:15px;color:<?php echo $paid ? '#1f7a4a' : '#8a6a12'; ?>">
			<?php echo $paid ? '✅ ÖDEME ALINDI' : '⏳ Ödeme onayı bekleniyor'; ?>
		</p>
		<p style="margin:0 0 4px;font-size:15px;color:#555">Yüz boyama · <?php echo (int) $qty; ?> kişi</p>
		<p style="margin:10px 0;font-size:48px;line-height:1.1;font-weight:900;letter-spacing:.06em;color:#2b1d3a"><?php echo esc_html( $code ); ?></p>
		<p style="margin:0;font-size:16px;color:#333"><?php echo $paid ? 'Bu ekranı yüz boyama görevlisine gösterin.' : 'Birkaç saniye sonra sayfayı yenileyin. Sorun olursa görevliye danışın.'; ?></p>
	</div>
	<?php
}, 1 );

/* Yönetim panelinde sipariş ekranında kodu göster. */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	if ( mdg_ybpos_is_pos_order( $order ) ) {
		echo '<p><strong>Yüz boyama kodu:</strong> ' . esc_html( (string) $order->get_meta( MDG_YBPOS_META_CODE ) ) . '</p>';
	}
} );
