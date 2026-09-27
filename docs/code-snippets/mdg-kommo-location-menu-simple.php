add_action('admin_menu', function () {
    add_submenu_page(
        'mmc-dashboard',
        'Kommo Konum Cevapları',
        'Kommo Konum Cevapları',
        'manage_options',
        'mmc-kommo-location-replies',
        'mdg_kommo_location_replies_page_simple',
        58
    );
});

add_action('admin_post_mdg_kommo_location_save', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Yetkiniz yok.');
    }
    check_admin_referer('mdg_kommo_location_save');

    $program_id = absint($_POST['program_id'] ?? 0);
    $data = [
        'city' => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
        'event_name' => sanitize_text_field(wp_unslash($_POST['event_name'] ?? '')),
        'venue_name' => sanitize_text_field(wp_unslash($_POST['venue_name'] ?? '')),
        'address' => sanitize_textarea_field(wp_unslash($_POST['address'] ?? '')),
        'maps_url' => esc_url_raw(wp_unslash($_POST['maps_url'] ?? '')),
        'keywords' => sanitize_textarea_field(wp_unslash($_POST['keywords'] ?? '')),
        'updated_at' => current_time('mysql'),
    ];

    update_option('mdg_kommo_location_reply_' . $program_id, $data, false);

    wp_safe_redirect(add_query_arg([
        'page' => 'mmc-kommo-location-replies',
        'program_id' => $program_id,
        'saved' => 1,
    ], admin_url('admin.php')));
    exit;
});

add_action('admin_footer', function () {
    if (empty($_GET['page']) || !in_array($_GET['page'], ['mmc-kommo-hub', 'mmc-kommo'], true)) {
        return;
    }
    $program_id = absint($_GET['program_id'] ?? $_GET['mmc_program_id'] ?? 3);
    $url = add_query_arg(['page' => 'mmc-kommo-location-replies', 'program_id' => $program_id], admin_url('admin.php'));
    echo '<script>
    document.addEventListener("DOMContentLoaded", function(){
        var wrap = document.querySelector(".wrap") || document.querySelector("#wpbody-content");
        if(!wrap || document.getElementById("mdg-kommo-location-card")) return;
        var card = document.createElement("div");
        card.id = "mdg-kommo-location-card";
        card.className = "notice notice-info";
        card.style.padding = "14px 16px";
        card.style.marginTop = "14px";
        card.innerHTML = "<p style=\"font-size:15px;margin:0 0 8px\"><strong>Kommo konum cevapları</strong></p><p style=\"margin:0 0 10px\">Konum, adres ve yol tarifi soruları için kullanılacak salon adı, açık adres ve doğrulanmış Google Haritalar bağlantısını buradan hazırlayın.</p><p style=\"margin:0\"><a class=\"button button-primary\" href=\"' . esc_url($url) . '\">Konum Cevaplarını Aç</a></p>";
        wrap.appendChild(card);
    });
    </script>';
});

function mdg_kommo_location_replies_page_simple() {
    if (!current_user_can('manage_options')) {
        wp_die('Yetkiniz yok.');
    }

    $program_id = absint($_GET['program_id'] ?? $_GET['mmc_program_id'] ?? 3);
    $defaults = [
        'city' => '',
        'event_name' => '',
        'venue_name' => '',
        'address' => '',
        'maps_url' => '',
        'keywords' => 'konum, yer nerede, salon nerede, adres, nasıl giderim, yol tarifi',
    ];
    $data = array_merge($defaults, (array) get_option('mdg_kommo_location_reply_' . $program_id, []));
    $missing = [];
    foreach (['city' => 'Şehir', 'venue_name' => 'Salon adı', 'address' => 'Açık adres', 'maps_url' => 'Google Maps bağlantısı'] as $key => $label) {
        if (empty($data[$key])) {
            $missing[] = $label;
        }
    }

    $reply_lines = [];
    $reply_lines[] = ($data['city'] ? $data['city'] : 'Seçili şehir') . ' gösterimizin konumu:';
    if ($data['event_name']) {
        $reply_lines[] = $data['event_name'];
    }
    if ($data['venue_name']) {
        $reply_lines[] = $data['venue_name'];
    }
    if ($data['address']) {
        $reply_lines[] = $data['address'];
    }
    $reply_lines[] = '';
    $reply_lines[] = $data['maps_url'] ? 'Yol tarifi: ' . $data['maps_url'] : 'Yol tarifi bağlantısı henüz doğrulanmamış. Canlı temsilci kontrol etmelidir.';
    $reply = implode("\n", $reply_lines);

    $instruction = 'Müşteri “konum”, “yer nerede”, “salon nerede”, “adres”, “nasıl giderim” veya “yol tarifi” dediğinde, konuşmada belirtilen şehir ve etkinliğe ait salon adını, açık adresi ve doğrulanmış Google Haritalar bağlantısını birlikte gönder. Şehir belli değilse yalnızca “Hangi şehirdeki gösterimizin konumunu öğrenmek istersiniz?” diye sor. Google Haritalar bağlantısı uydurma ve farklı şehirdeki salonun bağlantısını gönderme.';

    echo '<div class="wrap mdg-kommo-loc"><h1>Kommo Konum Cevapları</h1>';
    if (!empty($_GET['saved'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Konum cevabı kaydedildi.</p></div>';
    }
    echo '<style>
    .mdg-kommo-loc .grid{display:grid;grid-template-columns:minmax(320px,1.05fr) minmax(320px,.95fr);gap:18px;align-items:start}
    .mdg-kommo-loc .card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}
    .mdg-kommo-loc label{font-weight:600;display:block;margin:12px 0 5px}
    .mdg-kommo-loc input[type=text],.mdg-kommo-loc input[type=url],.mdg-kommo-loc textarea{width:100%;max-width:100%}
    .mdg-kommo-loc textarea{min-height:76px}.mdg-kommo-loc .copy{min-height:155px;font-family:monospace}
    .mdg-kommo-loc .pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#f0f0f1;margin:0 8px 12px 0}
    .mdg-kommo-loc .ok{background:#d1e7dd;color:#0f5132}.mdg-kommo-loc .bad{background:#f8d7da;color:#842029}
    @media(max-width:900px){.mdg-kommo-loc .grid{grid-template-columns:1fr}}
    </style>';

    echo '<p><span class="pill">Program ID: ' . esc_html((string) $program_id) . '</span><span class="pill ' . (empty($missing) ? 'ok' : 'bad') . '">' . (empty($missing) ? 'Kommo cevabı hazır' : 'Eksik: ' . esc_html(implode(', ', $missing))) . '</span></p>';

    echo '<div class="grid"><div class="card"><h2>Etkinlik Konum Kaydı</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('mdg_kommo_location_save');
    echo '<input type="hidden" name="action" value="mdg_kommo_location_save">';
    echo '<label>Program ID</label><input type="text" name="program_id" value="' . esc_attr((string) $program_id) . '">';
    echo '<label>Şehir / İlçe</label><input type="text" name="city" value="' . esc_attr($data['city']) . '" placeholder="Örn. Çubuk / Ankara">';
    echo '<label>Etkinlik adı</label><input type="text" name="event_name" value="' . esc_attr($data['event_name']) . '" placeholder="Örn. Madagaskar Sirki - Çubuk">';
    echo '<label>Salon adı</label><input type="text" name="venue_name" value="' . esc_attr($data['venue_name']) . '">';
    echo '<label>Açık adres</label><textarea name="address">' . esc_textarea($data['address']) . '</textarea>';
    echo '<label>Doğrulanmış Google Haritalar bağlantısı</label><input type="url" name="maps_url" value="' . esc_attr($data['maps_url']) . '" placeholder="https://maps.app.goo.gl/...">';
    echo '<label>Arama kelimeleri</label><textarea name="keywords">' . esc_textarea($data['keywords']) . '</textarea>';
    echo '<p><button class="button button-primary" type="submit">Kaydet</button> ';
    if ($data['maps_url']) {
        echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url($data['maps_url']) . '">Haritada Aç</a>';
    }
    echo '</p></form></div>';

    echo '<div class="card"><h2>Kommo Hazır Cevap</h2><textarea id="mdg-kommo-reply" class="copy" readonly>' . esc_textarea($reply) . '</textarea><p><button type="button" class="button button-primary mdg-copy" data-target="mdg-kommo-reply">Cevabı Kopyala</button></p>';
    echo '<h2>Ana Talimat</h2><textarea id="mdg-kommo-instruction" class="copy" readonly>' . esc_textarea($instruction) . '</textarea><p><button type="button" class="button mdg-copy" data-target="mdg-kommo-instruction">Talimatı Kopyala</button></p></div></div></div>';
    echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".mdg-copy");if(!b)return;var f=document.getElementById(b.dataset.target);if(!f)return;f.focus();f.select();navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(f.value):document.execCommand("copy");var t=b.textContent;b.textContent="Kopyalandı";setTimeout(function(){b.textContent=t},1200);});</script>';
}
