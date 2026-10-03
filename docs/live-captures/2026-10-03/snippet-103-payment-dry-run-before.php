<?php
/**
 * Madagaskar Sirki – Yarım Kalan Ödeme Kaydı V1 (DENEME MODU)
 *
 * Ödemesi tamamlanmayan siparişleri izler ve "bu müşteriye hatırlatma
 * gönderilirdi / gönderilmezdi" kararını WooCommerce günlüğüne yazar.
 *
 * BU SÜRÜM:
 * - Hiçbir mesaj göndermez. İçinde gönderim kodu yoktur.
 * - Kommo'ya yazmaz.
 * - Siparişi, sipariş notlarını ve sipariş alanlarını değiştirmez.
 * - Yalnızca günlüğe satır yazar ve kısa ömürlü sayaç (transient) tutar.
 *
 * Kayıtlar: WooCommerce > Durum > Günlükler > "madagaskar-odeme-hatirlatma"
 *
 * Kurallar:
 * - Sipariş "ödeme bekliyor" veya "başarısız" durumunda 20 dakika kaldıysa
 *   değerlendirilir.
 * - Sipariş bu sürede ödendiyse veya iptal edildiyse: gönderilmezdi.
 * - Aynı telefon bu siparişten sonra başka bir siparişi ödediyse: gönderilmezdi.
 * - Her sipariş için en fazla bir "gönderilirdi" kaydı yazılır.
 *
 * Geri alma: bu snippet'i devre dışı bırakmak yeterlidir.
 * Code Snippets kullanırken <?php etiketi eklemeyin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MS_OH_BEKLEME' ) ) {
    define( 'MS_OH_BEKLEME', 20 * 60 );
}

if ( ! defined( 'MS_OH_KANCA' ) ) {
    define( 'MS_OH_KANCA', 'ms_oh_kontrol' );
}

if ( ! defined( 'MS_OH_GRUP' ) ) {
    define( 'MS_OH_GRUP', 'madagaskar-odeme-hatirlatma' );
}

if ( ! defined( 'MS_OH_MAX_ERTELEME' ) ) {
    define( 'MS_OH_MAX_ERTELEME', 3 );
}


/**
 * Günlüğe yaz.
 */
if ( ! function_exists( 'ms_oh_log' ) ) {
    function ms_oh_log( $mesaj, $baglam = array() ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $baglam['source'] = MS_OH_GRUP;

        wc_get_logger()->info( '[DENEME MODU] ' . $mesaj, $baglam );
    }
}


/**
 * Sipariş için bir kontrol planla.
 */
if ( ! function_exists( 'ms_oh_planla' ) ) {
    function ms_oh_planla( $order_id, $ne_zaman = 0, $erteleme = 0 ) {
        $order_id = absint( $order_id );
        $erteleme = absint( $erteleme );

        if ( ! $order_id ) {
            return;
        }

        if ( ! $ne_zaman ) {
            $ne_zaman = time() + MS_OH_BEKLEME;
        }

        $args = array( $order_id, $erteleme );

        if ( function_exists( 'as_schedule_single_action' ) ) {

            if (
                function_exists( 'as_next_scheduled_action' ) &&
                as_next_scheduled_action( MS_OH_KANCA, $args, MS_OH_GRUP )
            ) {
                return;
            }

            as_schedule_single_action( $ne_zaman, MS_OH_KANCA, $args, MS_OH_GRUP );
            return;
        }

        if ( ! wp_next_scheduled( MS_OH_KANCA, $args ) ) {
            wp_schedule_single_event( $ne_zaman, MS_OH_KANCA, $args );
        }
    }
}


/**
 * Sipariş olaylarında yalnızca kontrol planlar.
 * Ödeme sayfasını hiçbir koşulda bozmaması için her hata yutulur.
 */
if ( ! function_exists( 'ms_oh_olay' ) ) {
    function ms_oh_olay( $order_id ) {
        try {
            ms_oh_planla( $order_id );
        } catch ( \Throwable $e ) {
            return;
        }
    }
}

add_action( 'woocommerce_new_order', 'ms_oh_olay', 99, 1 );
add_action( 'woocommerce_order_status_pending', 'ms_oh_olay', 99, 1 );
add_action( 'woocommerce_order_status_failed', 'ms_oh_olay', 99, 1 );


/**
 * Telefonun son 10 hanesi.
 */
if ( ! function_exists( 'ms_oh_telefon' ) ) {
    function ms_oh_telefon( $ham ) {
        $rakam = preg_replace( '/\D+/', '', (string) $ham );

        if ( strlen( $rakam ) < 10 ) {
            return '';
        }

        return substr( $rakam, -10 );
    }
}


/**
 * Aynı telefon bu siparişten sonra başka bir siparişi ödedi mi?
 */
if ( ! function_exists( 'ms_oh_sonradan_odedi_mi' ) ) {
    function ms_oh_sonradan_odedi_mi( $order ) {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return false;
        }

        $telefon = ms_oh_telefon( $order->get_billing_phone() );
        $tarih   = $order->get_date_created();

        if ( '' === $telefon || ! $tarih ) {
            return false;
        }

        $adaylar = wc_get_orders(
            array(
                'status'       => array( 'processing', 'completed' ),
                'date_created' => '>=' . $tarih->getTimestamp(),
                'limit'        => 50,
                'return'       => 'objects',
            )
        );

        if ( ! is_array( $adaylar ) ) {
            return false;
        }

        foreach ( $adaylar as $aday ) {
            if ( ! is_object( $aday ) || ! method_exists( $aday, 'get_billing_phone' ) ) {
                continue;
            }

            if ( (int) $aday->get_id() === (int) $order->get_id() ) {
                continue;
            }

            if ( hash_equals( $telefon, ms_oh_telefon( $aday->get_billing_phone() ) ) ) {
                return true;
            }
        }

        return false;
    }
}


/**
 * Planlanan kontrol: karar ver ve günlüğe yaz.
 */
if ( ! function_exists( 'ms_oh_kontrol' ) ) {
    function ms_oh_kontrol( $order_id, $erteleme = 0 ) {
        try {
            $order_id = absint( $order_id );
            $erteleme = absint( $erteleme );

            if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
                return;
            }

            /* Bu sipariş için karar daha önce yazıldıysa tekrar yazma. */
            if ( get_transient( 'ms_oh_yazildi_' . $order_id ) ) {
                return;
            }

            $order = wc_get_order( $order_id );

            if (
                ! $order ||
                ! is_object( $order ) ||
                ! method_exists( $order, 'get_type' ) ||
                'shop_order' !== $order->get_type()
            ) {
                return;
            }

            $durum = (string) $order->get_status();

            /* Sepet aşamasındaki taslak: henüz ödeme denenmedi, sessizce çık. */
            if ( 'checkout-draft' === $durum ) {
                return;
            }

            $baglam = array(
                'order_id'    => $order_id,
                'durum'       => $durum,
                'tutar'       => (string) $order->get_total(),
                'olusturma'   => (string) $order->get_created_via(),
                'telefon_son' => substr( ms_oh_telefon( $order->get_billing_phone() ), -4 ),
            );

            $urunler = array();

            foreach ( $order->get_items() as $kalem ) {
                if ( is_object( $kalem ) && method_exists( $kalem, 'get_name' ) ) {
                    $urunler[] = (string) $kalem->get_name();
                }
            }

            $baglam['urunler'] = implode( ' | ', $urunler );

            /* Ödendi, iptal edildi veya başka bir duruma geçti. */
            if ( ! in_array( $durum, array( 'pending', 'failed' ), true ) ) {
                ms_oh_log(
                    'Sipariş #' . $order_id . ': GÖNDERİLMEZDİ (durum: ' . $durum . ').',
                    $baglam
                );
                set_transient( 'ms_oh_yazildi_' . $order_id, 'atlandi', 3 * DAY_IN_SECONDS );
                return;
            }

            /* Son değişiklikten bu yana bekleme süresi dolmadıysa ertele. */
            $degisim = $order->get_date_modified();
            $gecen   = $degisim ? ( time() - $degisim->getTimestamp() ) : MS_OH_BEKLEME;

            if ( $gecen < ( MS_OH_BEKLEME - 60 ) && $erteleme < MS_OH_MAX_ERTELEME ) {
                ms_oh_planla(
                    $order_id,
                    time() + ( MS_OH_BEKLEME - $gecen ),
                    $erteleme + 1
                );
                return;
            }

            $baglam['bekleme_dk'] = (int) floor( $gecen / 60 );

            /* Yalnızca sitedeki ödeme sayfasından açılan siparişler. */
            if ( ! in_array( $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ) {
                ms_oh_log(
                    'Sipariş #' . $order_id . ': GÖNDERİLMEZDİ (ödeme sayfasından açılmamış).',
                    $baglam
                );
                set_transient( 'ms_oh_yazildi_' . $order_id, 'atlandi', 3 * DAY_IN_SECONDS );
                return;
            }

            if ( '' === ms_oh_telefon( $order->get_billing_phone() ) ) {
                ms_oh_log(
                    'Sipariş #' . $order_id . ': GÖNDERİLMEZDİ (geçerli telefon yok).',
                    $baglam
                );
                set_transient( 'ms_oh_yazildi_' . $order_id, 'atlandi', 3 * DAY_IN_SECONDS );
                return;
            }

            if ( ms_oh_sonradan_odedi_mi( $order ) ) {
                ms_oh_log(
                    'Sipariş #' . $order_id . ': GÖNDERİLMEZDİ (aynı telefon sonradan başka bir siparişi ödemiş).',
                    $baglam
                );
                set_transient( 'ms_oh_yazildi_' . $order_id, 'atlandi', 3 * DAY_IN_SECONDS );
                return;
            }

            ms_oh_log(
                'Sipariş #' . $order_id . ': GÖNDERİLİRDİ (ödeme ' . $baglam['bekleme_dk'] . ' dakikadır tamamlanmadı).',
                $baglam
            );

            set_transient( 'ms_oh_yazildi_' . $order_id, 'gonderilirdi', 3 * DAY_IN_SECONDS );

        } catch ( \Throwable $e ) {
            return;
        }
    }
}

add_action( MS_OH_KANCA, 'ms_oh_kontrol', 10, 2 );