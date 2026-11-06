<?php
namespace Madagaskar\Visitor;
final class Page {
    private static $entry_rendered = false;
    public static function markup() {
        return '<main class="mdg-wa" id="mdg-vp"><header class="mdg-wa-header"><span class="mdg-wa-kicker">MADAGASKAR SİRKİ</span><h1>Ailenize uygun bileti bulalım.</h1><p>Şehrinizi ve seansınızı seçin; güncel bilet seçeneklerini karşılaştıralım. <a href="' . esc_url( home_url( '/bilet-al/' ) ) . '">Tüm gösterilere bakın</a>.</p></header>
<section class="mdg-vp-intake" aria-label="Planınızı yazın"><label for="vp-text">Nasıl bir planınız var?</label><textarea id="vp-text" rows="3" maxlength="600" aria-describedby="vp-privacy" placeholder="İzmir’de iki yetişkin, üç çocuk geleceğiz."></textarea><p id="vp-privacy" class="mdg-wa-note">Metniniz Google Gemini’ye gönderilir. Telefon, sipariş veya ödeme bilgisi yazmayın. Aşağıdaki formu doğrudan da kullanabilirsiniz.</p><button id="vp-parse" type="button" class="mdg-wa-primary" disabled>Yapay zekâ ile forma aktar</button><p id="vp-ai-status" role="status" aria-live="polite"></p></section>
<form id="vp-form"><div class="mdg-wa-grid"><label>Şehir<select id="vp-city" required disabled><option value="">Gösteriler yükleniyor…</option></select></label><label>Seans<select id="vp-session" required disabled><option value="">Önce şehri seçin</option></select></label></div>
<fieldset><legend>Kaç kişi geleceksiniz?</legend><div class="mdg-wa-counts"><label>Yetişkin<span>13 yaş ve üzeri</span><input id="vp-adults" type="number" min="1" max="20" step="1" value="2" required inputmode="numeric"></label><label>Çocuk<span>3–12 yaş</span><input id="vp-children" type="number" min="0" max="20" step="1" value="2" required inputmode="numeric"></label><label>Küçük misafir<span>0–2 yaş · ücretsiz, kucakta</span><input id="vp-infants" type="number" min="0" max="20" step="1" value="0" required inputmode="numeric"></label></div></fieldset>
<button id="vp-submit" class="mdg-wa-primary" type="submit" disabled>Bilet seçeneklerini karşılaştır</button><p class="mdg-wa-note">Okul davetiyesi veya özel kampanyanız varsa mevcut bilet ekranındaki koşulları kontrol edin.</p></form>
<p id="vp-status" role="status" aria-live="polite">Güncel gösteriler yükleniyor…</p><section id="vp-result" class="mdg-wa-result" hidden aria-labelledby="vp-title"><span class="mdg-wa-kicker">SİZE UYGUN SEÇENEK</span><h2 id="vp-title">En uygun bilet birleşimi</h2><p id="vp-date"></p><ul id="vp-lines"></ul><div class="mdg-wa-total"><span>Toplam</span><strong id="vp-total"></strong></div><p id="vp-savings"></p><p id="vp-capacity" class="mdg-wa-note"></p><a id="vp-ticket" class="mdg-wa-primary">Gösterinin bilet ekranına git</a><p id="vp-handoff" class="mdg-wa-note"></p></section>
<p class="mdg-wa-note">Öneri rezervasyon oluşturmaz. Fiyat ve uygunluk bilet ekranında yeniden kontrol edilir.</p><noscript>Bu araç için JavaScript gerekir. Güncel gösterilere Bilet Al sayfasından ulaşabilirsiniz.</noscript></main>';
    }
    public static function entry( $content ) {
        if ( ! Gate::enabled() || (string) get_option( 'mdg_vp_listed', '0' ) !== '1' || ! is_page( 'bilet-al' ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
        self::$entry_rendered = true;
        return self::entry_markup() . $content;
    }
    private static function entry_markup() {
        return '<aside id="mdg-vp-entry" aria-label="Bilet önerisi" style="padding:20px;margin:20px 0;max-width:100%;box-sizing:border-box;border:1px solid #e4c56c;border-radius:12px;background:#fffbef;color:#151515"><strong>Ailenize uygun bileti bulalım</strong><p>Şehrinizi seçin; kişi sayılarınıza göre güncel bilet seçeneklerini karşılaştırın.</p><a style="display:inline-flex;align-items:center;min-height:44px;padding:10px 16px;box-sizing:border-box;border-radius:10px;background:#c91f22;color:#fff;font-weight:700;text-decoration:none" href="' . esc_url( home_url( '/bilet-onerisi/' ) ) . '">Bilet önerisini aç →</a></aside>';
    }
    public static function footer_entry() {
        if ( self::$entry_rendered || ! Gate::enabled() || (string) get_option( 'mdg_vp_listed', '0' ) !== '1'
            || rtrim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) !== '/bilet-al' ) { return; }
        self::$entry_rendered = true;
        echo self::entry_markup();
        // The native directory bypasses the_content but calls wp_footer. Preserve its event DOM.
        echo '<script>(function(){var entry=document.getElementById("mdg-vp-entry"),hero=document.querySelector(".mdg-tickets__hero");if(entry&&hero)hero.appendChild(entry);})();</script>';
    }
    public static function render() {
        if ( ! Gate::enabled() || rtrim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) !== '/bilet-onerisi' ) { return; }
        status_header( 200 ); nocache_headers(); header( 'X-Robots-Tag: noindex, nofollow', true );
        $main = __DIR__ . '/mdg-visitor-pilot.php';
        wp_enqueue_style( 'mdg-visitor-pilot', plugins_url( 'style.css', $main ), array(), '1.1.0' );
        wp_enqueue_script( 'mdg-visitor-pilot', plugins_url( 'client.js', $main ), array(), '1.1.0', true );
        ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Ailenize Uygun Bilet · Madagaskar Sirki</title><?php wp_head(); ?></head><body class="mdg-vp-page"><nav aria-label="Siteye dön"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Madagaskar Sirki</a></nav><?php echo self::markup(); ?>
<script id="vp-config" type="application/json"><?php echo wp_json_encode( array( 'api' => rest_url( 'mdg-visitor/v1/' ), 'origin' => Gate::origin() ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script><?php wp_footer(); ?></body></html><?php
        exit;
    }
}
