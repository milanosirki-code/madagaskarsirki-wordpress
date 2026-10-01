<?php
/** Public support links and ticket access landing page. */
add_filter('render_block', function ($html) {
    return str_replace(array('href="https://wa.me/"', "href='https://wa.me/'"), array('href="https://wa.me/903129113710"', "href='https://wa.me/903129113710'"), $html);
}, 20);
add_action('init', function () {
    if (shortcode_exists('ms_biletlerim')) { return; }
    add_shortcode('ms_biletlerim', function () {
        $account = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/');
        return '<section aria-label="Bilet erişimi" style="max-width:720px;margin:32px auto;padding:28px;border:1px solid #d4af37;border-radius:16px;background:#0d0b26;color:#fff;box-sizing:border-box"><p style="color:#d4af37;letter-spacing:2px">MADAGASKAR SİRKİ</p><h1 style="color:#fff;font-size:30px">Biletlerim</h1><p style="line-height:1.7">Biletinizi görüntülemek için sipariş e-postanızdaki kişisel bilet bağlantısını açın. E-postayı bulamıyorsanız spam / gereksiz klasörünü de kontrol edin.</p><p style="line-height:1.7">Üye olarak satın aldıysanız hesabınıza giriş yaparak siparişlerinizi inceleyebilirsiniz. Misafir olarak satın aldıysanız veya bağlantınız açılmıyorsa WhatsApp bilet destek hattımıza ulaşabilirsiniz.</p><div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:24px"><a style="display:inline-block;padding:12px 18px;border-radius:8px;background:#d4af37;color:#17132c;font-weight:700;text-decoration:none" href="' . esc_url($account) . '">Hesabım / Siparişlerim</a><a style="display:inline-block;padding:12px 18px;border-radius:8px;background:#fff;color:#123b27;font-weight:700;text-decoration:none" href="https://wa.me/903129113710">WhatsApp Bilet Desteği</a></div><p style="font-size:14px;line-height:1.7;margin-top:24px">Destek hattı: 0312 911 37 10. Kişisel bilet bağlantınızı ve QR kodunuzu herkese açık alanlarda paylaşmayın.</p></section>';
    });
}, 99);
add_filter('the_content', function ($content) {
    if (is_page('biletlerim')) {
        $content = str_replace('Bu sayfa yalnızca kişisel bilet bağlantınız üzerinden erişildiğinde çalışır.', '', $content);
    }
    return $content;
}, 9);