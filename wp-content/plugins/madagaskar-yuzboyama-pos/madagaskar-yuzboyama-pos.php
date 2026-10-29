<?php
/**
 * Plugin Name: Madagaskar Yüz Boyama POS
 * Description: Yüz boyama için POS benzeri hızlı ödeme. [mdg_yuzboyama_pos] kısa kodu kişi sayısını alır, fatura formu göstermeden siparişi hazırlar ve doğrudan kart ödeme ekranına yönlendirir. Ödeme sonrası teşekkür sayfasında büyük işlem kodu gösterir. Bilet ödeme sayfasına (/odeme/) dokunmaz.
 * Version: 1.0.2
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
	if ( ! class_exists( 'MDG_Yuzboyama' ) || version_compare( MDG_Yuzboyama::VERSION, '1.0.3', '<' ) || ! $product || ! $product->is_purchasable() ) {
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
			<label for="mdg-yb-name">Ad soyad (isteğe bağlı)</label>
			<input id="mdg-yb-name" name="customer_name" type="text" autocomplete="name" maxlength="160" placeholder="Ödeme yapan kişinin adı">
			<p class="mdg-yb-small">İsminizi girerseniz görevli ödemenizi isminizle de bulabilir.</p>
			<label for="mdg-yb-qty">Kaç kişi?</label>
			<div class="mdg-yb-quantity">
				<button type="button" data-yb-step="-1" aria-label="Kişi sayısını azalt">−</button>
				<input id="mdg-yb-qty" type="number" inputmode="numeric" min="1" max="<?php echo (int) MDG_YBPOS_MAX_QTY; ?>" step="1" name="quantity" value="1" required>
				<button type="button" data-yb-step="1" aria-label="Kişi sayısını artır">+</button>
			</div>
			<p class="mdg-yb-total">Toplam <strong id="mdg-yb-total" data-unit="<?php echo esc_attr( $price ); ?>" aria-live="polite"><?php echo esc_html( wc_format_localized_price( $price ) ); ?> TL</strong></p>
			<button class="mdg-yb-primary" type="submit">Kartla öde</button>
		</form>
		<p class="mdg-yb-small">Ad soyad isteğe bağlıdır; kart ekranında ödemenizi tamamlayın. Bu ödeme yalnız yüz boyama içindir; gösteri giriş bileti değildir.</p>
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

	$qty = filter_var( wp_unslash( $_POST['quantity'] ?? '' ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1, 'max_range' => MDG_YBPOS_MAX_QTY ) ) );

	try {
		$product = wc_get_product( MDG_YBPOS_PRODUCT_ID );
		if ( ! class_exists( 'MDG_Yuzboyama' ) || version_compare( MDG_Yuzboyama::VERSION, '1.0.3', '<' ) || ! $product || ! $product->is_purchasable() ) {
			throw new Exception( 'urun' );
		}
		if ( ! $qty || 'TRY' !== get_woocommerce_currency() || (int) round( (float) wc_get_price_to_display( $product ) * 100 ) !== 10000 ) {
			throw new Exception( 'tutar' );
		}
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$gateway = $gateways['paytr_payment_gateway'] ?? null;
		if ( ! $gateway || 'yes' !== $gateway->enabled ) { throw new Exception( 'odeme' ); }
		$order = wc_create_order( array( 'status' => 'pending', 'created_via' => 'mdg_yuzboyama_pos' ) );
		if ( is_wp_error( $order ) ) {
			throw new Exception( 'siparis' );
		}
		$order->add_product( $product, $qty );
		$order->set_address( mdg_ybpos_billing(), 'billing' );
		$name = MDG_Yuzboyama::clean_name( wp_unslash( $_POST['customer_name'] ?? '' ) );
		if ( $name !== '' ) { $order->set_billing_first_name( $name ); $order->set_billing_last_name( '' ); }
		$order->set_customer_ip_address( $ip );

		$order->set_payment_method( $gateway );
		$order->update_meta_data( MDG_YBPOS_META_FLAG, 'yes' );
		$order->calculate_totals();
		if ( (int) round( (float) $order->get_total() * 100 ) !== $qty * 10000 ) {
			$order->update_status( 'cancelled', 'Yüz boyama toplamı doğrulanamadı.' );
			throw new Exception( 'tutar' );
		}
		MDG_Yuzboyama::order_meta( $order );
		$order->save();
		$order->add_order_note( sprintf( 'Yüz boyama POS siparişi: %d kişi. İşlem kodu doğrulanmış ödeme sonrasında oluşturulur.', $qty ) );

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

/* KDV belgesi ve güvenli işlem kodu MDG_Yuzboyama::receipt tarafından
 * woocommerce_thankyou üzerinden tek kez gösterilir. Ödenmemiş siparişe kod verilmez. */

/* Yönetim panelinde sipariş ekranında kodu göster. */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	if ( mdg_ybpos_is_pos_order( $order ) && class_exists( 'MDG_Yuzboyama' ) ) {
		echo '<p><strong>Yüz boyama kodu:</strong> ' . esc_html( MDG_Yuzboyama::payment_code( $order ) ) . '</p>';
	}
} );
