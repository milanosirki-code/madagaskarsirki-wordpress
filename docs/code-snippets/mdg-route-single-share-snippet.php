<?php
/**
 * Madagaskar route single-share helper for Code Snippets.
 */

add_action('admin_footer', function () {
    if (!is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ('mad-okul-route-plan' !== $page) {
        return;
    }

    $program_id = isset($_GET['mmc_program_id']) ? absint($_GET['mmc_program_id']) : 0;
    $nonce      = wp_create_nonce('mdg_public_routes');
    $ajax_url   = admin_url('admin-ajax.php');
    ?>
    <style>
        #mdg-single-route-share {
            margin: 14px 0 18px;
            padding: 14px;
            border: 1px solid #b7c7ff;
            border-radius: 8px;
            background: #f6f8ff;
            max-width: 760px;
        }
        #mdg-single-route-share .mdg-route-title {
            margin: 0 0 8px;
            font-weight: 700;
        }
        #mdg-single-route-share .button {
            margin: 4px 8px 4px 0;
        }
        #mdg-single-route-share .mdg-route-note {
            margin: 8px 0 0;
            color: #50575e;
        }
    </style>
    <script>
    (function () {
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var nonce = <?php echo wp_json_encode($nonce); ?>;
        var programId = <?php echo (int) $program_id; ?>;

        function compactText(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function lineStartingWith(prefix) {
            var lines = (document.body.innerText || '').split(/\n+/);
            for (var i = 0; i < lines.length; i++) {
                var line = compactText(lines[i]);
                if (line.indexOf(prefix) === 0) {
                    return line.replace(prefix, '').trim();
                }
            }
            return '';
        }

        function collectRoutes() {
            var seen = {};
            var routes = [];
            document.querySelectorAll('a.button-primary[href*="google.com/maps/dir"]').forEach(function (link) {
                var title = compactText(link.textContent);
                var url = link.href;
                if (!title || !url || seen[url]) {
                    return;
                }
                seen[url] = true;
                routes.push({
                    title: title,
                    url: url
                });
            });

            var title = '';
            (document.body.innerText || '').split(/\n+/).some(function (line) {
                line = compactText(line);
                if (line.indexOf('Okul Tanıtım Rota Planı') !== -1) {
                    title = line;
                    return true;
                }
                return false;
            });

            return {
                title: title || 'Madagaskar Sirki Okul Tanıtım Rotaları',
                salon: lineStartingWith('Salon:'),
                address: lineStartingWith('Adres:'),
                date: lineStartingWith('Tarih:'),
                routes: routes
            };
        }

        function saveRoutes(openWhatsApp) {
            var payload = collectRoutes();
            if (!payload.routes.length) {
                alert('Maps rota bağlantısı bulunamadı. Önce rota planının yüklendiğinden emin olun.');
                return;
            }

            var params = new URLSearchParams();
            params.set('action', 'mdg_save_public_routes');
            params.set('nonce', nonce);
            params.set('program_id', String(programId || 0));
            params.set('payload', JSON.stringify(payload));

            var status = document.getElementById('mdg-single-route-status');
            if (status) {
                status.textContent = 'Tek link hazırlanıyor...';
            }

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: params.toString()
            })
                .then(function (response) { return response.json(); })
                .then(function (response) {
                    if (!response || !response.success || !response.data || !response.data.url) {
                        throw new Error(response && response.data && response.data.message ? response.data.message : 'Tek link üretilemedi.');
                    }

                    var url = response.data.url;
                    var message = payload.title + '\n\nRota seçim linki:\n' + url + '\n\nKendi bölümünüzü seçip Google Maps ile açın.';

                    if (status) {
                        status.innerHTML = 'Hazır: <a href="' + url + '" target="_blank" rel="noopener">Tek rota seçim sayfasını aç</a>';
                    }

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).catch(function () {});
                    }

                    if (openWhatsApp) {
                        window.open('https://wa.me/?text=' + encodeURIComponent(message), '_blank', 'noopener');
                    }
                })
                .catch(function (error) {
                    if (status) {
                        status.textContent = error.message || 'Tek link hazırlanamadı.';
                    }
                    alert(error.message || 'Tek link hazırlanamadı.');
                });
        }

        function insertPanel() {
            if (document.getElementById('mdg-single-route-share')) {
                return;
            }

            var routeLinks = document.querySelectorAll('a.button-primary[href*="google.com/maps/dir"]');
            if (!routeLinks.length) {
                return;
            }

            var anchor = routeLinks[0].parentNode;
            var panel = document.createElement('div');
            panel.id = 'mdg-single-route-share';
            panel.innerHTML =
                '<p class="mdg-route-title">Tek link paylaşımı</p>' +
                '<button type="button" class="button button-primary" id="mdg-single-route-whatsapp">Tek Linki WhatsApp ile Paylaş</button>' +
                '<button type="button" class="button" id="mdg-single-route-copy">Tek Linki Hazırla / Kopyala</button>' +
                '<p class="mdg-route-note" id="mdg-single-route-status">Grupta tek link paylaşılır; ekip kendi bölümünü seçim sayfasından açar.</p>';

            anchor.parentNode.insertBefore(panel, anchor);
            document.getElementById('mdg-single-route-whatsapp').addEventListener('click', function () {
                saveRoutes(true);
            });
            document.getElementById('mdg-single-route-copy').addEventListener('click', function () {
                saveRoutes(false);
            });
        }

        if ('loading' === document.readyState) {
            document.addEventListener('DOMContentLoaded', insertPanel);
        } else {
            insertPanel();
        }
    }());
    </script>
    <?php
});

add_action('wp_ajax_mdg_save_public_routes', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Yetki yok.'], 403);
    }

    check_ajax_referer('mdg_public_routes', 'nonce');

    $program_id = isset($_POST['program_id']) ? absint($_POST['program_id']) : 0;
    $raw        = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
    $payload    = json_decode($raw, true);

    if (!$program_id || !is_array($payload) || empty($payload['routes']) || !is_array($payload['routes'])) {
        wp_send_json_error(['message' => 'Rota verisi eksik.'], 400);
    }

    $routes = [];
    foreach ($payload['routes'] as $route) {
        $title = isset($route['title']) ? sanitize_text_field($route['title']) : '';
        $url   = isset($route['url']) ? esc_url_raw($route['url']) : '';
        if (!$title || !$url || false === strpos($url, 'google.com/maps/dir')) {
            continue;
        }
        $routes[] = [
            'title' => $title,
            'url'   => $url,
        ];
    }

    if (!$routes) {
        wp_send_json_error(['message' => 'Geçerli Maps bağlantısı bulunamadı.'], 400);
    }

    $option_name = 'mdg_public_routes_' . $program_id;
    $existing    = get_option($option_name, []);
    $token       = is_array($existing) && !empty($existing['token']) ? sanitize_text_field($existing['token']) : wp_generate_password(12, false, false);

    $data = [
        'token'      => $token,
        'program_id' => $program_id,
        'title'      => isset($payload['title']) ? sanitize_text_field($payload['title']) : 'Madagaskar Sirki Okul Tanıtım Rotaları',
        'salon'      => isset($payload['salon']) ? sanitize_text_field($payload['salon']) : '',
        'address'    => isset($payload['address']) ? sanitize_text_field($payload['address']) : '',
        'date'       => isset($payload['date']) ? sanitize_text_field($payload['date']) : '',
        'routes'     => $routes,
        'updated'    => current_time('mysql'),
    ];

    update_option($option_name, $data, false);
    update_option('mdg_public_routes_latest_program', $program_id, false);

    $url = add_query_arg(
        [
            'mdg_routes' => '1',
            'program_id' => $program_id,
            'key'        => $token,
        ],
        home_url('/')
    );

    wp_send_json_success(['url' => $url, 'count' => count($routes)]);
});

add_action('template_redirect', function () {
    if (!isset($_GET['mdg_routes'])) {
        return;
    }

    $program_id = isset($_GET['program_id']) ? absint($_GET['program_id']) : absint(get_option('mdg_public_routes_latest_program'));
    $key        = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
    $data       = $program_id ? get_option('mdg_public_routes_' . $program_id, []) : [];

    if (!is_array($data) || empty($data['routes']) || empty($data['token']) || !hash_equals((string) $data['token'], (string) $key)) {
        status_header(404);
        nocache_headers();
        wp_die('Rota linki bulunamadı veya süresi yenilenmiş olabilir.', 'Rota bulunamadı', ['response' => 404]);
    }

    nocache_headers();
    status_header(200);
    ?>
    <!doctype html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html($data['title']); ?></title>
        <style>
            body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f4f6fb; color: #1d2327; }
            .mdg-wrap { max-width: 780px; margin: 0 auto; padding: 18px; }
            .mdg-head { background: #111827; color: #fff; padding: 18px; border-radius: 10px; margin-bottom: 14px; }
            .mdg-head h1 { font-size: 22px; line-height: 1.25; margin: 0 0 10px; }
            .mdg-meta { margin: 4px 0; color: #d1d5db; font-size: 14px; }
            .mdg-card { background: #fff; border: 1px solid #dbe3f0; border-radius: 10px; padding: 14px; margin: 10px 0; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
            .mdg-card h2 { font-size: 17px; margin: 0 0 10px; }
            .mdg-button { display: block; text-align: center; text-decoration: none; background: #3157e8; color: #fff; padding: 13px 14px; border-radius: 8px; font-weight: 700; }
            .mdg-note { color: #667085; font-size: 13px; margin: 14px 0 0; }
        </style>
    </head>
    <body>
        <main class="mdg-wrap">
            <section class="mdg-head">
                <h1><?php echo esc_html($data['title']); ?></h1>
                <?php if (!empty($data['date'])) : ?><p class="mdg-meta">Tarih: <?php echo esc_html($data['date']); ?></p><?php endif; ?>
                <?php if (!empty($data['salon'])) : ?><p class="mdg-meta">Salon: <?php echo esc_html($data['salon']); ?></p><?php endif; ?>
                <?php if (!empty($data['address'])) : ?><p class="mdg-meta">Adres: <?php echo esc_html($data['address']); ?></p><?php endif; ?>
            </section>

            <?php foreach ($data['routes'] as $route) : ?>
                <section class="mdg-card">
                    <h2><?php echo esc_html($route['title']); ?></h2>
                    <a class="mdg-button" href="<?php echo esc_url($route['url']); ?>" target="_blank" rel="noopener">Google Maps ile Aç</a>
                </section>
            <?php endforeach; ?>

            <p class="mdg-note">Kendi bölümünüzü seçip Google Maps ile açın. Link güncellendiğinde bu sayfa son kaydedilen rota planını gösterir.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
});
