add_action('admin_menu', function () {
    add_submenu_page(
        'mmc-dashboard',
        'Kommo Konum Cevapları',
        'Kommo Konum Cevapları',
        'manage_options',
        'mmc-kommo-location-replies',
        'mdg_kommo_location_replies_page',
        58
    );
});

add_action('admin_init', function () {
    if (!is_admin() || empty($_POST['mdg_kommo_location_save'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        wp_die('Yetkiniz yok.');
    }
    check_admin_referer('mdg_kommo_location_save');

    $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
    if (!$program_id) {
        $program_id = 0;
    }

    $data = [
        'city' => sanitize_text_field(wp_unslash($_POST['city'] ?? '')),
        'event_name' => sanitize_text_field(wp_unslash($_POST['event_name'] ?? '')),
        'venue_name' => sanitize_text_field(wp_unslash($_POST['venue_name'] ?? '')),
        'address' => sanitize_textarea_field(wp_unslash($_POST['address'] ?? '')),
        'maps_url' => esc_url_raw(wp_unslash($_POST['maps_url'] ?? '')),
        'keywords' => sanitize_textarea_field(wp_unslash($_POST['keywords'] ?? 'konum, yer nerede, salon nerede, adres, nasıl giderim, yol tarifi')),
        'updated_at' => current_time('mysql'),
    ];

    update_option('mdg_kommo_location_reply_' . $program_id, $data, false);

    $redirect = add_query_arg(
        [
            'page' => 'mmc-kommo-location-replies',
            'program_id' => $program_id,
            'saved' => 1,
        ],
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_footer', function () {
    if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'mmc-kommo-hub') {
        return;
    }
    $program_id = isset($_GET['program_id']) ? absint($_GET['program_id']) : mdg_kommo_location_guess_active_program_id();
    $href = add_query_arg(
        [
            'page' => 'mmc-kommo-location-replies',
            'program_id' => $program_id,
        ],
        admin_url('admin.php')
    );
    ?>
    <script>
    (function () {
        const wrap = document.querySelector('.wrap') || document.querySelector('#wpbody-content');
        if (!wrap || document.getElementById('mdg-kommo-location-card')) return;
        const card = document.createElement('div');
        card.id = 'mdg-kommo-location-card';
        card.className = 'notice notice-info';
        card.style.padding = '14px 16px';
        card.style.marginTop = '14px';
        card.innerHTML = '<p style="font-size:15px;margin:0 0 8px"><strong>Kommo konum cevapları</strong></p><p style="margin:0 0 10px">Müşteri konum, adres veya yol tarifi sorduğunda kullanılacak salon adı, açık adres ve doğrulanmış Google Haritalar bağlantısını buradan hazırlayın.</p><p style="margin:0"><a class="button button-primary" href="<?php echo esc_url($href); ?>">Konum Cevaplarını Aç</a></p>';
        wrap.appendChild(card);
    })();
    </script>
    <?php
});

function mdg_kommo_location_replies_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Yetkiniz yok.');
    }

    $program_id = isset($_GET['program_id']) ? absint($_GET['program_id']) : mdg_kommo_location_guess_active_program_id();
    $program_id = $program_id ?: 0;
    $stored = get_option('mdg_kommo_location_reply_' . $program_id, []);
    $context = mdg_kommo_location_context($program_id);
    $data = array_merge([
        'city' => $context['city'],
        'event_name' => $context['event_name'],
        'venue_name' => $context['venue_name'],
        'address' => $context['address'],
        'maps_url' => $context['maps_url'],
        'keywords' => 'konum, yer nerede, salon nerede, adres, nasıl giderim, yol tarifi',
    ], is_array($stored) ? $stored : []);

    $reply = mdg_kommo_location_reply_text($data);
    $instruction = "Müşteri “konum”, “yer nerede”, “salon nerede”, “adres”, “nasıl giderim” veya “yol tarifi” dediğinde, konuşmada belirtilen şehir ve etkinliğe ait salon adı, açık adres ve doğrulanmış Google Haritalar bağlantısı birlikte gönder. Şehir belli değilse yalnızca “Hangi şehirdeki gösterimizin konumunu öğrenmek istersiniz?” diye sor. Google Haritalar bağlantısı uydurma ve farklı şehirdeki salonun bağlantısını gönderme.";
    $missing = [];
    foreach (['city' => 'Şehir', 'venue_name' => 'Salon adı', 'address' => 'Açık adres', 'maps_url' => 'Google Maps bağlantısı'] as $key => $label) {
        if (empty($data[$key])) {
            $missing[] = $label;
        }
    }
    ?>
    <div class="wrap mdg-kommo-loc">
        <h1>Kommo Konum Cevapları</h1>
        <?php if (!empty($_GET['saved'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Konum cevabı kaydedildi.</p></div>
        <?php endif; ?>

        <style>
            .mdg-kommo-loc .mdg-grid{display:grid;grid-template-columns:minmax(320px,1.1fr) minmax(320px,.9fr);gap:18px;align-items:start}
            .mdg-kommo-loc .mdg-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}
            .mdg-kommo-loc label{font-weight:600;display:block;margin:12px 0 5px}
            .mdg-kommo-loc input[type=text],.mdg-kommo-loc input[type=url],.mdg-kommo-loc textarea{width:100%;max-width:100%}
            .mdg-kommo-loc textarea{min-height:74px}
            .mdg-kommo-loc .mdg-copy{width:100%;min-height:150px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
            .mdg-kommo-loc .mdg-status{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
            .mdg-kommo-loc .mdg-pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#f0f0f1}
            .mdg-kommo-loc .ok{background:#d1e7dd;color:#0f5132}
            .mdg-kommo-loc .bad{background:#f8d7da;color:#842029}
            @media (max-width: 900px){.mdg-kommo-loc .mdg-grid{grid-template-columns:1fr}}
        </style>

        <div class="mdg-status">
            <span class="mdg-pill">Program ID: <?php echo esc_html((string) $program_id); ?></span>
            <span class="mdg-pill <?php echo empty($missing) ? 'ok' : 'bad'; ?>"><?php echo empty($missing) ? 'Kommo cevabı hazır' : 'Eksik: ' . esc_html(implode(', ', $missing)); ?></span>
        </div>

        <div class="mdg-grid">
            <div class="mdg-card">
                <h2>Etkinlik Konum Kaydı</h2>
                <p>Bu alan Kommo, WhatsApp ve hatırlatma mesajlarında kullanılacak tek konum cevabını hazırlar.</p>

                <form method="post">
                    <?php wp_nonce_field('mdg_kommo_location_save'); ?>
                    <input type="hidden" name="mdg_kommo_location_save" value="1">
                    <label>Program ID</label>
                    <input type="text" name="program_id" value="<?php echo esc_attr((string) $program_id); ?>">

                    <label>Şehir / İlçe</label>
                    <input type="text" name="city" value="<?php echo esc_attr($data['city']); ?>" placeholder="Örn. Çubuk / Ankara">

                    <label>Etkinlik adı</label>
                    <input type="text" name="event_name" value="<?php echo esc_attr($data['event_name']); ?>" placeholder="Örn. Madagaskar Sirki - Çubuk">

                    <label>Salon adı</label>
                    <input type="text" name="venue_name" value="<?php echo esc_attr($data['venue_name']); ?>" placeholder="Örn. Gençlik ve Spor İlçe Müdürlüğü Kapalı Spor Salonu">

                    <label>Açık adres</label>
                    <textarea name="address" placeholder="Açık adres"><?php echo esc_textarea($data['address']); ?></textarea>

                    <label>Doğrulanmış Google Haritalar bağlantısı</label>
                    <input type="url" name="maps_url" value="<?php echo esc_attr($data['maps_url']); ?>" placeholder="https://maps.app.goo.gl/...">

                    <label>Arama kelimeleri</label>
                    <textarea name="keywords"><?php echo esc_textarea($data['keywords']); ?></textarea>

                    <p>
                        <button class="button button-primary" type="submit">Kaydet</button>
                        <?php if (!empty($data['maps_url'])) : ?>
                            <a class="button" target="_blank" rel="noopener" href="<?php echo esc_url($data['maps_url']); ?>">Haritada Aç</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <div class="mdg-card">
                <h2>Kommo Hazır Cevap</h2>
                <textarea id="mdg-kommo-reply" class="mdg-copy" readonly><?php echo esc_textarea($reply); ?></textarea>
                <p><button type="button" class="button button-primary" data-copy="#mdg-kommo-reply">Cevabı Kopyala</button></p>

                <h2>Ana Talimat</h2>
                <textarea id="mdg-kommo-instruction" class="mdg-copy" readonly><?php echo esc_textarea($instruction); ?></textarea>
                <p><button type="button" class="button" data-copy="#mdg-kommo-instruction">Talimatı Kopyala</button></p>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-copy]');
        if (!button) return;
        const field = document.querySelector(button.getAttribute('data-copy'));
        if (!field) return;
        field.focus();
        field.select();
        try {
            await navigator.clipboard.writeText(field.value);
            const old = button.textContent;
            button.textContent = 'Kopyalandı';
            setTimeout(() => button.textContent = old, 1200);
        } catch (e) {
            document.execCommand('copy');
        }
    });
    </script>
    <?php
}

function mdg_kommo_location_reply_text($data) {
    $city = trim((string) ($data['city'] ?? ''));
    $event_name = trim((string) ($data['event_name'] ?? ''));
    $venue_name = trim((string) ($data['venue_name'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $maps_url = trim((string) ($data['maps_url'] ?? ''));

    $title = $city ? $city . ' gösterimizin konumu:' : 'Gösterimizin konumu:';
    $lines = [$title];
    if ($event_name) {
        $lines[] = $event_name;
    }
    if ($venue_name) {
        $lines[] = $venue_name;
    }
    if ($address) {
        $lines[] = $address;
    }
    if ($maps_url) {
        $lines[] = '';
        $lines[] = 'Yol tarifi: ' . $maps_url;
    } else {
        $lines[] = '';
        $lines[] = 'Yol tarifi bağlantısı henüz doğrulanmamış. Canlı temsilci kontrol etmelidir.';
    }
    return implode("\n", $lines);
}

function mdg_kommo_location_guess_active_program_id() {
    if (isset($_GET['program_id'])) {
        return absint($_GET['program_id']);
    }
    if (isset($_GET['mmc_program_id'])) {
        return absint($_GET['mmc_program_id']);
    }
    $active = get_option('mmc_active_program_id');
    return $active ? absint($active) : 3;
}

function mdg_kommo_location_context($program_id) {
    global $wpdb;
    $context = [
        'city' => '',
        'event_name' => '',
        'venue_name' => '',
        'address' => '',
        'maps_url' => '',
    ];

    $program_id = absint($program_id);
    if (!$program_id) {
        return $context;
    }

    $tables = $wpdb->get_col('SHOW TABLES');
    if (!is_array($tables)) {
        return $context;
    }

    foreach ($tables as $table) {
        $lower = strtolower($table);
        if (strpos($lower, 'program') === false && strpos($lower, 'event') === false && strpos($lower, 'venue') === false && strpos($lower, 'mad') === false && strpos($lower, 'mmc') === false) {
            continue;
        }

        $cols = $wpdb->get_results('SHOW COLUMNS FROM `' . esc_sql($table) . '`', ARRAY_A);
        if (!$cols) {
            continue;
        }
        $col_names = array_map(fn($c) => $c['Field'], $cols);
        $id_col = mdg_kommo_location_first_col($col_names, ['program_id', 'mmc_program_id', 'id']);
        if (!$id_col) {
            continue;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM `' . esc_sql($table) . '` WHERE `' . esc_sql($id_col) . '` = %d LIMIT 1', $program_id), ARRAY_A);
        if (!$row) {
            continue;
        }

        $city = mdg_kommo_location_value($row, ['city', 'il', 'district', 'ilce', 'region', 'location']);
        $event = mdg_kommo_location_value($row, ['event_title', 'title', 'program_code', 'name']);
        $venue = mdg_kommo_location_value($row, ['venue_name', 'salon_name', 'salon', 'venue', 'place_name']);
        $address = mdg_kommo_location_value($row, ['address', 'venue_address', 'salon_address', 'open_address', 'adres']);
        $maps = mdg_kommo_location_value($row, ['maps_url', 'google_maps_url', 'google_map_url', 'map_url', 'location_url', 'venue_map_url']);

        if (!$context['city'] && $city) {
            $context['city'] = $city;
        }
        if (!$context['event_name'] && $event) {
            $context['event_name'] = $event;
        }
        if (!$context['venue_name'] && $venue) {
            $context['venue_name'] = $venue;
        }
        if (!$context['address'] && $address) {
            $context['address'] = $address;
        }
        if (!$context['maps_url'] && $maps) {
            $context['maps_url'] = $maps;
        }
    }

    return $context;
}

function mdg_kommo_location_first_col($columns, $candidates) {
    foreach ($candidates as $candidate) {
        foreach ($columns as $column) {
            if (strtolower($column) === strtolower($candidate)) {
                return $column;
            }
        }
    }
    return '';
}

function mdg_kommo_location_value($row, $candidates) {
    foreach ($candidates as $candidate) {
        foreach ($row as $key => $value) {
            if (strtolower($key) === strtolower($candidate) && $value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : '';
            }
        }
    }
    return '';
}
