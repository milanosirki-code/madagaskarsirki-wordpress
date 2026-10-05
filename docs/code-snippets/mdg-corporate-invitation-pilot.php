<?php
/**
 * Denizli corporate invitation interaction pilot, 2026-10-05.
 * Read-only prototype: no coupon, cart, order, payment, capacity or ticket writes.
 * Code Snippets: remove opening PHP tag; scope global; embed shortcode only on
 * a new password-protected page. See docs/MDG_CORPORATE_INVITATION_PILOT.md.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'MDG_Corporate_Invitation_Pilot_20261005', false ) ) {
    final class MDG_Corporate_Invitation_Pilot_20261005 {
        const SHORTCODE = 'mdg_corporate_invitation_pilot';
        const CODE = 'TEST-DENIZLI';

        public static function sessions() {
            return array(
                '1730' => array( 'label'=>'17:30', 'parent'=>2425, 'adult'=>2427, 'child'=>2426, 'session'=>99 ),
                '1930' => array( 'label'=>'19:30', 'parent'=>2428, 'adult'=>2430, 'child'=>2429, 'session'=>100 ),
            );
        }

        /** Pure policy; strict whole counts, no coercion of negatives/decimals. */
        public static function quote( $code, $session, $adults, $children, $price ) {
            $sessions = self::sessions();
            if ( ! is_string( $code ) || self::CODE !== strtoupper( trim( $code ) ) ) {
                return array( 'error'=>'Test kampanya kodu geçersiz.' );
            }
            if ( ! is_string( $session ) || ! isset( $sessions[ $session ] ) ) {
                return array( 'error'=>'Lütfen geçerli bir seans seçin.' );
            }
            foreach ( array( $adults, $children ) as $count ) {
                if ( ! is_scalar( $count ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $count ) ) {
                    return array( 'error'=>'Bilet adetlerini tam sayı olarak seçin.' );
                }
            }
            $adults = (int) $adults;
            $children = (int) $children;
            if ( $adults > 2 || $children > 4 || $children > 2 * $adults ) {
                return array( 'error'=>'Bu pilotta 1 veya 2 yetişkin seçilebilir. Her ücretli yetişkin için en fazla 2 çocuk ücretsizdir.' );
            }
            if ( ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price <= 0 ) {
                return array( 'error'=>'Yetişkin fiyatı doğrulanamadı.' );
            }
            return array(
                'session'=>$session, 'adults'=>$adults, 'children'=>$children,
                'capacity_units'=>$adults + $children,
                'adult_unit_price'=>(float) $price, 'total'=>round( $adults * (float) $price, 2 ),
                'child_total'=>0, 'test_only'=>true,
            );
        }

        /** Read canonical Woo variation metadata; never fall back to a stored price. */
        public static function price( $session ) {
            if ( ! function_exists( 'wc_get_product' ) ) { return null; }
            $sessions = self::sessions();
            if ( ! isset( $sessions[ $session ] ) ) { return null; }
            $s = $sessions[ $session ];
            $parent = wc_get_product( $s['parent'] );
            if ( ! $parent || 'publish' !== $parent->get_status() ) { return null; }
            foreach ( array( 'adult', 'child' ) as $role ) {
                $v = wc_get_product( $s[ $role ] );
                if ( ! $v || ! $v->is_type( 'variation' ) || $v->get_parent_id() !== $s['parent'] ||
                    'publish' !== $v->get_status() || (int) $v->get_meta( '_mdg_event_id' ) !== 12 ||
                    (int) $v->get_meta( '_mdg_session_id' ) !== $s['session'] ) { return null; }
                if ( 'adult' === $role ) { $adult = $v; }
            }
            return $adult->get_price();
        }

        private static function text_input( $key ) {
            $value = $_POST[ $key ] ?? '';
            return is_string( $value ) ? sanitize_text_field( wp_unslash( $value ) ) : '';
        }

        public static function render() {
            // Guard even if the shortcode is rendered indirectly by a REST/template call.
            $page = get_post();
            if ( ! $page || post_password_required( $page ) ) { return ''; }
            $sessions = self::sessions();
            $prices = array();
            foreach ( $sessions as $key => $s ) { $prices[ $key ] = self::price( $key ); }
            $result = null;
            $session = '1730';
            $adults = '1';
            $children = '2';
            if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['mdg_corporate_pilot_submit'] ) ) {
                $nonce = self::text_input( 'mdg_corporate_pilot_nonce' );
                if ( ! wp_verify_nonce( $nonce, self::SHORTCODE ) ) {
                    $result = array( 'error'=>'Sayfanın süresi doldu. Sayfayı yenileyip tekrar deneyin.' );
                } else {
                    $session = self::text_input( 'mdg_pilot_session' );
                    $adults = self::text_input( 'mdg_pilot_adults' );
                    $children = self::text_input( 'mdg_pilot_children' );
                    $result = self::quote( self::text_input( 'mdg_pilot_code' ), $session, $adults, $children, $prices[ $session ] ?? null );
                }
            }
            ob_start();
            ?>
            <section class="mdg-corporate-pilot" aria-label="Kurumsal çocuk davetiyesi denemesi">
                <style>
                .mdg-corporate-pilot{max-width:740px;margin:24px auto;padding:clamp(20px,4vw,40px);background:#fff8ed;color:#242019;border:1px solid #ead8b9;border-radius:16px}
                .mdg-corporate-pilot *{box-sizing:border-box}
                .mdg-corporate-pilot .mdg-pilot-tag{font-weight:700;color:#9a2e24;letter-spacing:.08em}
                .mdg-corporate-pilot h2{font-size:clamp(25px,5vw,38px);line-height:1.15;margin:16px 0}
                .mdg-corporate-pilot p{line-height:1.65}
                .mdg-corporate-pilot .mdg-pilot-fields{display:grid;grid-template-columns:1fr 1fr;gap:18px}
                .mdg-corporate-pilot label{display:block;font-weight:600}
                .mdg-corporate-pilot input,.mdg-corporate-pilot select{display:block;width:100%;padding:12px;margin-top:8px;border:1px solid #9b8c77;border-radius:6px;background:white;color:#242019;font:inherit;min-height:48px}
                .mdg-corporate-pilot button{margin-top:24px;padding:14px 24px;border:0;border-radius:6px;background:#a93428;color:#fff;font:inherit;font-weight:700;cursor:pointer}
                .mdg-corporate-pilot :focus-visible{outline:3px solid #8a641a;outline-offset:3px}
                .mdg-corporate-pilot .mdg-pilot-result{margin-top:24px;border-top:2px solid #d8bd83;padding-top:16px}
                .mdg-corporate-pilot .mdg-pilot-error{color:#94251e;font-weight:600}
                @media(max-width:540px){.mdg-corporate-pilot .mdg-pilot-fields{grid-template-columns:1fr}}
                </style>
                <p class="mdg-pilot-tag">TEST KURUMU · DENİZLİ</p>
                <h2>Çocuklarla birlikte sirke!</h2>
                <p><strong>8 Ekim 2026 · Özay Gönlüm Salonu</strong><br>Denizli Büyükşehir Belediyesi Kongre ve Kültür Merkezi</p>
                <p>Kurumsal kampanya denemesinde her ücretli yetişkinin yanında <strong>3–12 yaş arası en fazla iki çocuk ücretsiz</strong>. 0–2 yaş zaten ücretsizdir; bu formda sayılmaz. 13 yaş ve üzeri yetişkin bileti seçmelidir.</p>
                <p><strong>Deneme sayfası:</strong> Ödeme alınmaz ve girişte kullanılabilecek bilet veya QR oluşturulmaz. Test kodu: <strong><?php echo esc_html( self::CODE ); ?></strong>.</p>
                <form method="post" action="<?php echo esc_url( get_permalink( $page ) ); ?>">
                    <?php wp_nonce_field( self::SHORTCODE, 'mdg_corporate_pilot_nonce', false ); ?>
                    <div class="mdg-pilot-fields">
                        <label>Kampanya kodu<input name="mdg_pilot_code" value="<?php echo esc_attr( self::CODE ); ?>" maxlength="40" autocomplete="off" required></label>
                        <label>Seans<select name="mdg_pilot_session" required>
                            <?php foreach ( $sessions as $key => $s ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $session, $key ); ?>><?php echo esc_html( $s['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Ücretli yetişkin<select name="mdg_pilot_adults" required>
                            <?php for ( $n=1; $n<=2; $n++ ) : ?><option value="<?php echo esc_attr( $n ); ?>" <?php selected( $adults, (string) $n ); ?>><?php echo esc_html( $n ); ?> yetişkin</option><?php endfor; ?>
                        </select></label>
                        <label>Ücretsiz çocuk (3–12 yaş)<select name="mdg_pilot_children" required>
                            <?php for ( $n=1; $n<=4; $n++ ) : ?><option value="<?php echo esc_attr( $n ); ?>" <?php selected( $children, (string) $n ); ?>><?php echo esc_html( $n ); ?> çocuk</option><?php endfor; ?>
                        </select></label>
                    </div>
                    <button type="submit" name="mdg_corporate_pilot_submit" value="1">Kampanyayı dene</button>
                </form>
                <?php if ( null !== $result ) : ?>
                    <div class="mdg-pilot-result" role="status" aria-live="polite">
                        <?php if ( isset( $result['error'] ) ) : ?>
                            <p class="mdg-pilot-error"><?php echo esc_html( $result['error'] ); ?></p>
                        <?php else : ?>
                            <h3>Deneme özeti · <?php echo esc_html( $sessions[ $result['session'] ]['label'] ); ?></h3>
                            <p><?php echo esc_html( $result['adults'] ); ?> ücretli yetişkin + <?php echo esc_html( $result['children'] ); ?> ücretsiz çocuk.<br>
                            Yetişkin birim fiyatı: <?php echo wp_kses_post( wc_price( $result['adult_unit_price'] ) ); ?><br>
                            Çocuk toplamı: <?php echo wp_kses_post( wc_price( 0 ) ); ?><br>
                            <strong>Kampanya ile ödenecek toplam: <?php echo wp_kses_post( wc_price( $result['total'] ) ); ?></strong></p>
                            <p>Gerçek kampanya açıldığında ödeme tamamlandıktan sonra yetişkin ve çocuk QR biletleri aynı seans ve siparişe bağlı üretilecektir. Çocuklar ücretli yetişkin eşliğinde giriş yapacaktır.</p>
                            <p><strong>Bu özet bilet veya rezervasyon değildir.</strong> Koltuk/kapasite ayrılmadı.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php
            return ob_get_clean();
        }

        public static function robots( $robots ) {
            if ( is_singular( 'page' ) ) {
                $p = get_post();
                if ( $p && has_shortcode( $p->post_content, self::SHORTCODE ) ) {
                    unset( $robots['index'], $robots['follow'] );
                    $robots['noindex'] = true;
                    $robots['nofollow'] = true;
                }
            }
            return $robots;
        }
    }
    add_shortcode( MDG_Corporate_Invitation_Pilot_20261005::SHORTCODE, array( 'MDG_Corporate_Invitation_Pilot_20261005', 'render' ) );
    add_filter( 'wp_robots', array( 'MDG_Corporate_Invitation_Pilot_20261005', 'robots' ) );
}
