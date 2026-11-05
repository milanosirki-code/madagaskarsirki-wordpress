<?php
namespace Madagaskar\WebAssistant;

/** No anonymous routes, no production UI and no checkout mutation. */
final class Preview {
    public static function is_staging() {
        return function_exists( 'home_url' ) && rtrim( home_url( '/' ), '/' ) === Catalogue::STAGING_ORIGIN;
    }

    public static function permission() {
        return self::is_staging() && current_user_can( 'manage_woocommerce' );
    }

    public static function routes() {
        if ( ! self::is_staging() ) { return; }
        register_rest_route( 'mdg-web-assistant/v1', '/catalogue', array(
            'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'permission' ),
            'callback' => array( __CLASS__, 'catalogue' ),
        ) );
        register_rest_route( 'mdg-web-assistant/v1', '/quote', array(
            'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'permission' ),
            'callback' => array( __CLASS__, 'quote' ),
        ) );
    }

    private static function response( $data, $status = 200 ) {
        $response = new \WP_REST_Response( $data, $status );
        $response->header( 'Cache-Control', 'private, no-store, max-age=0' );
        $response->header( 'X-Robots-Tag', 'noindex, nofollow' );
        return $response;
    }

    public static function catalogue() {
        if ( ! self::permission() ) { return self::response( array( 'code' => 'FORBIDDEN' ), 403 ); }
        try { return self::response( Service::catalogue() ); }
        catch ( Failure $e ) { return self::response( array( 'code' => $e->getMessage() ), 503 ); }
    }

    public static function quote( $request ) {
        if ( ! self::permission() ) { return self::response( array( 'code' => 'FORBIDDEN' ), 403 ); }
        if ( strlen( $request->get_body() ) > 2048 ) { return self::response( array( 'code' => 'REQUEST_TOO_LARGE' ), 413 ); }
        $data = $request->get_json_params();
        if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'session_id', 'party', 'source_revision' ) )
            || ! is_array( $data['party'] ?? null ) || ! is_string( $data['source_revision'] ?? null ) ) {
            return self::response( array( 'code' => 'INVALID_REQUEST' ), 400 );
        }
        try {
            return self::response( Service::quote( $data['session_id'] ?? null, $data['party'], $data['source_revision'] ) );
        } catch ( Failure $e ) {
            return self::response( array( 'code' => $e->getMessage() ), 409 );
        }
    }

    public static function menu() {
        if ( ! self::is_staging() ) { return; }
        add_management_page( 'Bilet Önerisi Önizleme', 'Bilet Önerisi Önizleme', 'manage_woocommerce', 'mdg-web-assistant-preview', array( __CLASS__, 'page' ) );
    }

    public static function page() {
        if ( ! self::permission() ) { wp_die( 'Bu önizleme için yetkiniz yok.', '', array( 'response' => 403 ) ); }
        echo '<div class="wrap"><p class="mdg-wa-preview-label">Özel test ortamı · Müşteri ekranı önizlemesi</p>';
        echo self::markup(); // Static, trusted markup; remote data is rendered with textContent.
        echo '<script type="application/json" id="mdg-wa-config">' . wp_json_encode( array(
            'api' => rest_url( 'mdg-web-assistant/v1/' ), 'nonce' => wp_create_nonce( 'wp_rest' ),
            'origin' => Catalogue::STAGING_ORIGIN,
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script></div>';
    }

    public static function assets( $hook ) {
        if ( $hook !== 'tools_page_mdg-web-assistant-preview' || ! self::permission() ) { return; }
        $main = dirname( __DIR__ ) . '/madagaskar-web-assistant-core.php';
        wp_enqueue_style( 'mdg-web-assistant-preview', plugins_url( 'assets/preview.css', $main ), array(), '0.2.0' );
        wp_enqueue_script( 'mdg-web-assistant-preview', plugins_url( 'assets/preview.js', $main ), array(), '0.2.0', true );
    }

    public static function markup() {
        return '<section class="mdg-wa" id="mdg-wa" aria-labelledby="mdg-wa-title">
<header class="mdg-wa-header"><span class="mdg-wa-kicker">MADAGASKAR SİRKİ</span><h1 id="mdg-wa-title">Ailenize uygun bileti bulalım.</h1><p>Şehrinizi ve size uygun seansı seçin. Bilet seçeneklerini sizin için karşılaştıralım.</p></header>
<form id="mdg-wa-form">
<div class="mdg-wa-grid"><label>Şehir<select id="mdg-wa-city" required disabled><option value="">Şehirler yükleniyor…</option></select></label>
<label>Gösteri ve seans<select id="mdg-wa-session" required disabled><option value="">Önce şehrinizi seçin</option></select></label></div>
<fieldset><legend>Kaç kişi geleceksiniz?</legend><div class="mdg-wa-counts">
<label>Yetişkin <span>13 yaş ve üzeri</span><input id="mdg-wa-adults" type="number" min="1" max="20" step="1" value="2" required inputmode="numeric"></label>
<label>Çocuk <span>3–12 yaş</span><input id="mdg-wa-children" type="number" min="0" max="20" step="1" value="2" required inputmode="numeric"></label>
<label>Küçük misafir <span>0–2 yaş · ücretsiz, kucakta</span><input id="mdg-wa-infants" type="number" min="0" max="20" step="1" value="0" required inputmode="numeric"></label>
</div></fieldset>
<button class="mdg-wa-primary" id="mdg-wa-submit" type="submit" disabled>Bilet seçeneklerini karşılaştır</button>
<p class="mdg-wa-note">Okul davetiyesi veya özel kampanyanız varsa mevcut bilet ekranındaki koşulları kontrol edin.</p></form>
<p id="mdg-wa-status" role="status" aria-live="polite">Güncel gösteriler yükleniyor…</p>
<section id="mdg-wa-result" class="mdg-wa-result" aria-labelledby="mdg-wa-result-title" hidden>
<span class="mdg-wa-kicker">SİZE UYGUN SEÇENEK</span><h2 id="mdg-wa-result-title"></h2><p id="mdg-wa-result-session"></p><ul id="mdg-wa-lines"></ul>
<div class="mdg-wa-total"><span>Toplam</span><strong id="mdg-wa-total"></strong></div><p id="mdg-wa-savings"></p><p id="mdg-wa-capacity" class="mdg-wa-note"></p>
<a id="mdg-wa-ticket" class="mdg-wa-primary">Gösterinin bilet ekranına git</a>
<p class="mdg-wa-note">Bu öneri rezervasyon oluşturmaz. Seansınızı ve bilet adetlerinizi satış ekranında kontrol ederek ilerleyin.</p>
</section><noscript>Öneri ekranı için JavaScript gerekir. Güncel gösterilere mevcut Bilet Al sayfasından ulaşabilirsiniz.</noscript></section>';
    }
}
