<?php
if(!function_exists('ms_v3p_guvenli_onbellek_istegi')){

/**
 * ============================================================
 * MADAGASKAR SİRKİ — GÜVENLİ SAYFA ÖNBELLEĞİ V3
 * ============================================================
 *
 * Amaç:
 * - Güvenli bilgi sayfalarında Tickera PHP oturumunu kapatmak.
 * - PHPSESSID ve sunucu tarafından oluşturulan _fbp başlıklarını kaldırmak.
 * - WordPress.com sayfa önbelleğine izin vermek.
 *
 * Korunan dinamik alanlar:
 * - WooCommerce ürünleri
 * - Sepet
 * - Ödeme
 * - Hesabım
 * - Biletlerim
 * - Tickera teknik sayfaları
 * - Sipariş ve bilet indirme bağlantıları
 * - Sepeti dolu kullanıcılar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * ------------------------------------------------------------
 * 1. İSTEK ÖNBELLEĞE UYGUN MU?
 * ------------------------------------------------------------
 */
function ms_v3p_guvenli_onbellek_istegi() {

    /*
     * Yönetim, kullanıcı oturumu, AJAX, REST ve POST istekleri.
     */
    if (
        is_admin() ||
        is_user_logged_in() ||
        wp_doing_ajax() ||
        (
            defined( 'REST_REQUEST' ) &&
            REST_REQUEST
        ) ||
        strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ) !== 'GET'
    ) {
        return false;
    }


    /*
     * Liste ve etkinlik sayfalarında zamanla değişen satış durumunu koru.
     * Geç çalışan çıktı tamponu diğer kodların no-cache kararını ezmemeli.
     */
    if (
        ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ||
        is_front_page() ||
        is_page( array( 'sehirler', 'bilet-al' ) )
    ) {
        return false;
    }

    /*
     * WooCommerce dinamik sayfaları.
     */
    if (
        (
            function_exists( 'is_cart' ) &&
            is_cart()
        ) ||
        (
            function_exists( 'is_checkout' ) &&
            is_checkout()
        ) ||
        (
            function_exists( 'is_account_page' ) &&
            is_account_page()
        ) ||
        (
            function_exists( 'is_product' ) &&
            is_product()
        ) ||
        (
            function_exists( 'is_product_category' ) &&
            is_product_category()
        )
    ) {
        return false;
    }


    /*
     * Özel ve teknik sayfalar.
     */
    $excluded_page_ids = array(
        1535, // Biletlerim
        1250, // Sepet
        1251, // Ödeme
        1252, // Hesabım
        1284, // Tickera
        1285, // Tickera
        1286, // Tickera
        1287, // Tickera
        1288, // Tickera
        1289, // Tickera
    );

    if ( is_page( $excluded_page_ids ) ) {
        return false;
    }


    /*
     * Dinamik işlem parametreleri.
     */
    $dangerous_parameters = array(
        'add-to-cart',
        'wc-ajax',
        'download_ticket',
        'mdg_davetiye',
        'mdg_protokol',
        'anahtar',
        'bilet',
        'kisi',
        'order_id',
        'order_key',
        'token',
        'nonce',
        'payment',
        'pay_for_order',
        'key',
    );

    foreach ( $dangerous_parameters as $parameter ) {

        if (
            isset( $_GET[ $parameter ] ) ||
            isset( $_POST[ $parameter ] )
        ) {
            return false;
        }
    }


    /*
     * WooCommerce veya Tickera sepeti bulunan ziyaretçiler.
     */
    foreach ( array_keys( $_COOKIE ) as $cookie_name ) {

        if (
            strpos(
                $cookie_name,
                'woocommerce_items_in_cart'
            ) === 0 ||
            strpos(
                $cookie_name,
                'wp_woocommerce_session_'
            ) === 0 ||
            strpos(
                $cookie_name,
                'woocommerce_cart_hash'
            ) === 0 ||
            strpos(
                $cookie_name,
                'tc_'
            ) === 0
        ) {
            return false;
        }
    }


    /*
     * Dinamik URL yolları.
     */
    $request_uri = isset( $_SERVER['REQUEST_URI'] )
        ? wp_unslash( $_SERVER['REQUEST_URI'] )
        : '';

    $request_path = wp_parse_url(
        $request_uri,
        PHP_URL_PATH
    );

    $request_path = trailingslashit(
        (string) $request_path
    );

    $excluded_paths = array(
        '/etkinlik/',
        '/sehirler/',
        '/bilet-al/',
        '/sepet/',
        '/odeme/',
        '/hesabim/',
        '/biletlerim/',
        '/cart/',
        '/checkout/',
        '/payment/',
        '/order-details/',
        '/process-payment/',
        '/order-received/',
        '/order-pay/',
        '/ipn/',
        '/wc-api/',
    );

    foreach ( $excluded_paths as $excluded_path ) {

        if (
            strpos(
                $request_path,
                $excluded_path
            ) === 0
        ) {
            return false;
        }
    }


    /*
     * Güvenli bilgi sayfası.
     */
    return true;
}


/**
 * ------------------------------------------------------------
 * 2. GEREKSİZ SET-COOKIE BAŞLIKLARINI KALDIR
 * ------------------------------------------------------------
 *
 * Yalnızca güvenli bilgi sayfalarında:
 * - PHPSESSID
 * - Sunucu tarafından tekrar oluşturulan _fbp
 *
 * kaldırılır.
 *
 * Diğer çerez başlıkları korunur.
 */
function ms_v3p_gereksiz_cookie_basliklarini_kaldir() {

    if ( headers_sent() ) {
        return;
    }

    $headers         = headers_list();
    $cookies_to_keep = array();

    $session_name = session_name();

    if ( ! $session_name ) {
        $session_name = 'PHPSESSID';
    }

    foreach ( $headers as $header_line ) {

        /*
         * Set-Cookie dışındaki başlıklarla ilgilenme.
         */
        if (
            stripos(
                $header_line,
                'Set-Cookie:'
            ) !== 0
        ) {
            continue;
        }


        /*
         * PHP oturum çerezini gönderme.
         */
        if (
            stripos(
                $header_line,
                'Set-Cookie: ' . $session_name . '='
            ) === 0
        ) {
            continue;
        }


        /*
         * Sunucu tarafından her istekte yeniden üretilen
         * Meta _fbp çerezini gönderme.
         *
         * Kullanıcının mevcut _fbp çerezi silinmez.
         */
        if (
            stripos(
                $header_line,
                'Set-Cookie: _fbp='
            ) === 0
        ) {
            continue;
        }


        /*
         * Diğer Set-Cookie başlıklarını koru.
         */
        $cookies_to_keep[] = $header_line;
    }


    /*
     * Önce bütün Set-Cookie başlıklarını kaldır.
     */
    header_remove( 'Set-Cookie' );


    /*
     * Korunması gerekenleri yeniden ekle.
     */
    foreach ( $cookies_to_keep as $cookie_header ) {

        header(
            $cookie_header,
            false
        );
    }
}


/**
 * ------------------------------------------------------------
 * 3. SAYFA ÇIKTISININ EN SONUNDA BAŞLIKLARI DÜZELT
 * ------------------------------------------------------------
 */
function ms_v3p_son_cikti_duzenle( $html ) {

    if ( ! ms_v3p_guvenli_onbellek_istegi() ) {
        return $html;
    }


    /*
     * Tickera tarafından açılan PHP oturumunu kapat.
     */
    if (
        session_status() === PHP_SESSION_ACTIVE
    ) {
        session_write_close();
    }


    /*
     * Tickera ve diğer eklentilerin sonradan eklediği
     * gereksiz çerez başlıklarını kaldır.
     */
    ms_v3p_gereksiz_cookie_basliklarini_kaldir();


    if ( ! headers_sent() ) {

        /*
         * PHP oturumundan kalan eski başlıkları temizle.
         */
        header_remove( 'Expires' );
        header_remove( 'Pragma' );
        header_remove( 'Cache-Control' );


        /*
         * Tarayıcı: 5 dakika
         * WordPress.com/CDN: 10 dakika
         */
        header(
            'Cache-Control: public, max-age=300, s-maxage=600, stale-while-revalidate=30',
            true
        );


        /*
         * Dış testlerde kodun çalıştığını gösterir.
         */
        header(
            'X-MS-Safe-Cache: enabled-v3',
            true
        );
    }


    return $html;
}


/**
 * ------------------------------------------------------------
 * 4. ÇIKTI TAMPONUNU BAŞLAT
 * ------------------------------------------------------------
 *
 * Sayfa oluşturulmadan önce başlar.
 * Başlıklar tüm eklentiler çalıştıktan sonra düzenlenir.
 */
add_action(
    'template_redirect',
    function () {

        if ( ! ms_v3p_guvenli_onbellek_istegi() ) {
            return;
        }

        ob_start(
            'ms_v3p_son_cikti_duzenle'
        );
    },
    -999
);


/**
 * ------------------------------------------------------------
 * 5. İLK ÇALIŞTIRMADA ÖNBELLEĞİ TEMİZLE
 * ------------------------------------------------------------
 */
add_action(
    'init',
    function () {

        $version_key =
            'ms_guvenli_onbellek_v3';

        if ( get_option( $version_key ) ) {
            return;
        }


        if (
            function_exists(
                'wp_cache_flush'
            )
        ) {
            wp_cache_flush();
        }


        update_option(
            $version_key,
            current_time( 'mysql' ),
            false
        );
    },
    99
);
}
