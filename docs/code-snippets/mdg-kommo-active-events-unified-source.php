<?php
/**
 * Plugin Name: Madagaskar Kommo Active Events Unified Source
 * Version: 1.2.0
 * Description: Kommo AI icin satisdaki tum etkinlikleri tek, dinamik ve tokenli kaynak URL'sinde toplar.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Program kisa kaynagi: Kommo AI'nin guvenilir okudugu pratik sinir.
if ( ! defined( 'MDG_KOMMO_PROGRAM_TEXT_LIMIT' ) ) {
    define( 'MDG_KOMMO_PROGRAM_TEXT_LIMIT', 1950 );
}
// Konum kisa kaynagi siniri. Kommo belgelenmis bir ust sinir vermiyor; asilirsa kaynak kesilmez, hata gosterilir.
if ( ! defined( 'MDG_KOMMO_LOCATIONS_TEXT_LIMIT' ) ) {
    define( 'MDG_KOMMO_LOCATIONS_TEXT_LIMIT', 5000 );
}

add_action( 'template_redirect', 'mdg_kommo_active_events_endpoint', 0 );
add_action( 'admin_menu', 'mdg_kommo_active_events_admin_menu', 60 );
add_action( 'admin_post_mdg_kommo_active_events_sync', 'mdg_kommo_active_events_sync_post' );
add_action( 'admin_footer', 'mdg_kommo_active_events_admin_card' );
add_action( 'save_post_product', 'mdg_kommo_active_events_clear_cache' );
add_action( 'save_post_tc_events', 'mdg_kommo_active_events_clear_cache' );
add_action( 'woocommerce_update_product', 'mdg_kommo_active_events_clear_cache' );

function mdg_kommo_active_events_endpoint() {
    if ( ! mdg_kommo_active_events_is_endpoint_request() ) {
        return;
    }

    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
    if ( ! hash_equals( mdg_kommo_active_events_token(), $token ) && ! current_user_can( 'manage_options' ) ) {
        status_header( 404 );
        exit;
    }

    $events = mdg_kommo_active_events_collect();
    nocache_headers();
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'X-Robots-Tag: noindex, nofollow', true );

    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>MMC | Kommo AI Bilgi Merkezi</title>';
    echo '<meta name="robots" content="noindex,nofollow"></head><body>';
    echo '<main>';
    echo '<h1>MMC | Kommo AI Bilgi Merkezi</h1>';
    echo '<p>Son güncelleme: ' . esc_html( wp_date( 'd.m.Y H:i', time(), wp_timezone() ) ) . '</p>';
    mdg_kommo_active_events_instruction_section();
    mdg_kommo_active_events_quick_index( $events );
    echo '<section><h2>Aktif Etkinlikler</h2>';

    if ( empty( $events ) ) {
        echo '<p>Aktif satıştaki etkinlik bulunamadı.</p>';
    }

    foreach ( $events as $index => $event ) {
        echo '<article>';
        echo '<h3>Etkinlik ' . esc_html( (string) ( $index + 1 ) ) . ': ' . esc_html( $event['name'] ) . '</h3>';
        echo '<ul>';
        echo '<li>Durum: Aktif</li>';
        echo '<li>İl / İlçe: ' . esc_html( $event['city_label'] ) . '</li>';
        echo '<li>Tarih: ' . esc_html( $event['date_label'] ) . '</li>';
        echo '<li>Salon: ' . esc_html( $event['venue'] ) . '</li>';
        echo '<li>Açık adres: ' . esc_html( $event['address'] ) . '</li>';
        echo '<li>Google Maps: ' . ( $event['maps_url'] ? '<a href="' . esc_url( $event['maps_url'] ) . '">' . esc_html( $event['maps_url'] ) . '</a>' : 'Eksik - bağlantı uydurma' ) . '</li>';
        echo '<li>Seanslar: ' . esc_html( implode( ', ', $event['sessions'] ) ) . '</li>';
        echo '<li>Fiyatlar: ' . esc_html( $event['prices'] ) . '</li>';
        echo '<li>Bilet bağlantısı: <a href="' . esc_url( $event['ticket_url'] ) . '">' . esc_html( $event['ticket_url'] ) . '</a></li>';
        echo '<li>Arama kelimeleri: ' . esc_html( $event['keywords'] ) . '</li>';
        echo '</ul>';
        echo '<p>Önerilen kısa cevap: ' . esc_html( mdg_kommo_active_events_event_answer_text( $event ) ) . '</p>';
        echo '</article>';
    }

    echo '</section></main></body></html>';
    exit;
}

function mdg_kommo_active_events_is_endpoint_request() {
    if ( ! empty( $_GET['mdg_kommo_active_events'] ) ) {
        return true;
    }

    $path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $path = (string) wp_parse_url( $path, PHP_URL_PATH );
    $path = trim( $path, '/' );

    return 'kommo-ai-bilgi-merkezi' === $path;
}

function mdg_kommo_active_events_instruction_section() {
    echo '<section><h2>Kommo Ana Talimat</h2>';
    echo '<p>Bu sayfa Kommo AI için ana ve güncel bilgi merkezidir. Şehir, ilçe, tarih, salon, açık adres, Google Maps konumu, seans, fiyat, bilet bağlantısı ve etkinlik aktiflik durumu için bu kaynak önceliklidir.</p>';
    echo '<p>PDF, Temel Bilgiler, Ürünler ve Hizmetler gibi kaynaklar yalnız genel kurallar içindir. Program, salon, seans, fiyat ve konum bilgisi çelişirse bu gizli URL kaynağı esas alınır.</p>';
    echo '<p>Bu kaynakta aktif etkinlik bulunan şehir veya ilçe için "program yayımlanmadı" deme. Aktif kayıt varsa salon, tarih, seans, fiyat ve bilet bağlantısını ver.</p>';
    echo '<p>Müşteri "konum", "yer nerede", "salon nerede", "adres", "nasıl giderim" veya "yol tarifi" dediğinde ilgili etkinliğin salon adını, açık adresini ve doğrulanmış Google Maps bağlantısını birlikte gönder.</p>';
    echo '<p>Şehir belli değilse yalnızca "Hangi şehirdeki gösterimizin konumunu öğrenmek istersiniz?" diye sor. Google Maps bağlantısı eksikse bağlantı uydurma; açık adresi paylaş ve canlı temsilciye aktar.</p>';
    echo '<p>Müşteri yalnız "Ankara" derse aktif satıştaki Ankara adresli veya Ankara ilçeli tüm etkinlikleri birlikte listele; aktif kayıt varsa "program yayımlanmadı" deme.</p>';
    echo '</section>';
}

function mdg_kommo_active_events_customer_rules_section() {
    echo '<section><h2>Genel Müşteri Bilgileri</h2>';
    echo '<ul>';
    echo '<li>Gösteri: Madagaskar Sirki tamamen hayvansız, ailelere ve çocuklara uygun canlı sahne gösterisidir.</li>';
    echo '<li>Süre: Gösteri yaklaşık 60 dakika sürer. Salon kapıları genellikle gösteriden yaklaşık 30 dakika önce açılır; farklı salon notu varsa etkinlik kaydı esas alınır.</li>';
    echo '<li>Oturma düzeni: Genel model serbest/numarasız oturmadır. Özel koltuk veya numara belirtilmedikçe koltuk numarası sözü verme; özel etkinlikte farklı bilgi varsa etkinlik kaydı geçerlidir.</li>';
    echo '<li>Yaş kuralı: 0-2 yaş ücretsizdir. 3-12 yaş çocuk bileti, 13 yaş ve üzeri yetişkin bileti alır.</li>';
    echo '<li>Standart fiyat: Çocuk 250 TL, yetişkin 500 TL, aile paketi 1.100 TL. İzmir gibi özel fiyatlı etkinlikte etkinlik kaydındaki fiyatı kullan.</li>';
    echo '<li>Aile paketi: 1 aile paketi toplam 4 kişilik kapasite düşer. Etkinlik kaydında farklı paket yazmıyorsa standart paket bilgisini kullan.</li>';
    echo '<li>Kapıda bilet: Etkinlik günü salon girişinden nakit veya kredi kartıyla bilet alınabilir.</li>';
    echo '<li>Okuldan ücretsiz bilet / ücretsiz çocuk davetiyesi: Bu kural şehirden bağımsız genel bilet kuralıdır; aktif satıştaki tüm Madagaskar Sirki gösterilerinde bireysel kullanımda geçerlidir. Şehir bazlı ayrıca doğrulama arama. Toplu katılımda geçersizdir. Her ücretsiz çocuk bileti için yanında ücretli yetişkin bulunmalıdır. 1 ücretli yetişkin yanında en fazla 2 ücretsiz çocuk bileti kullanılabilir. Ücretsiz çocuk biletiyle çocuk tek başına giremez. Müşteri "okuldan ücretsiz çocuk biletim var" derse: "Evet, bireysel girişte kullanabilirsiniz; yanında ücretli yetişkin olması gerekir" diye cevap ver.</li>';
    echo '<li>Bilet merkezi: <a href="https://madagaskarsirki.com/bilet-al/">https://madagaskarsirki.com/bilet-al/</a>. Tüm online satışlarda yalnız bu merkezi sayfayı paylaş; şehir/seans için başka satış bağlantısı üretme veya tahmin etme.</li>';
    echo '<li>İletişim ve WhatsApp: +90 312 911 37 10. Merkez telefon: +90 506 034 38 74.</li>';
    echo '<li>Canlı temsilciye aktar: ödeme, bilet teslimi, iade/değişim, şikâyet, yanlış bilet, kurumsal/toplu organizasyon, özel kampanya uyuşmazlığı, hukuki/istisnai durumlar ve doğrulanmış politikası olmayan yiyecek-içecek gibi konular.</li>';
    echo '</ul>';
    echo '</section>';
}

function mdg_kommo_active_events_quick_index( array $events ) {
    if ( empty( $events ) ) {
        return;
    }

    $ankara_events = array();
    $date_lines    = array();

    foreach ( $events as $event ) {
        $date_short = mdg_kommo_active_events_short_date( $event['date_label'] );
        $line = sprintf(
            '%s: %s, seanslar %s, salon %s, bilet %s',
            $event['city_label'],
            $date_short,
            implode( ', ', $event['sessions'] ),
            $event['venue'],
            $event['ticket_url']
        );

        $date_lines[] = sprintf(
            '%s: %s - %s - %s',
            $date_short,
            $event['city_label'],
            $event['venue'],
            implode( ', ', $event['sessions'] )
        );

        if (
            mdg_kommo_active_events_is_ankara_event( $event ) 
        ) {
            $ankara_events[] = $line;
        }
    }

    echo '<section><h2>Hızlı Şehir ve Tarih İndeksi - Öncelikli Cevap Kuralları</h2>';
    echo '<p>Asistan önce bu bölümü kontrol eder. Burada şehir veya ilçe varsa "program yayımlanmadı" cevabı verilmez; aşağıdaki aktif program bilgisi gönderilir.</p>';

    if ( ! empty( $ankara_events ) ) {
        echo '<h3>Ankara aktif programları</h3>';
        echo '<p>Ankara için kesinleşen aktif program vardır. Müşteri yalnız "Ankara" derse Ankara ilçelerindeki tüm aktif gösterileri listele. "Ankara için program yayımlanmadı" deme.</p>';
        echo '<ul>';
        foreach ( $ankara_events as $line ) {
            echo '<li>' . esc_html( $line ) . '</li>';
        }
        echo '</ul>';
    }

    echo '<h3>Tüm aktif şehir/ilçe programları</h3>';
    echo '<ul>';
    foreach ( $events as $event ) {
        echo '<li>' . esc_html( $event['city_label'] . ': ' . mdg_kommo_active_events_short_date( $event['date_label'] ) . ', seanslar ' . implode( ', ', $event['sessions'] ) . ', salon ' . $event['venue'] ) . '</li>';
    }
    echo '</ul>';

    echo '<h3>Tarih indeksi</h3>';
    echo '<ul>';
    foreach ( $date_lines as $line ) {
        echo '<li>' . esc_html( $line ) . '</li>';
    }
    echo '</ul>';

    echo '</section>';
}

function mdg_kommo_active_events_is_ankara_event( array $event ) {
    foreach ( array( 'city_label', 'address', 'name', 'venue' ) as $field ) {
        $value = isset( $event[ $field ] ) ? (string) $event[ $field ] : '';
        if ( '' !== $value && false !== mb_stripos( $value, 'Ankara', 0, 'UTF-8' ) ) {
            return true;
        }
    }

    return false;
}

function mdg_kommo_active_events_short_date( $date_label ) {
    $date_label = trim( (string) $date_label );
    $date_label = preg_replace( '/\s*,\s*(Pazartesi|Salı|Sali|Çarşamba|Carsamba|Perşembe|Persembe|Cuma|Cumartesi|Pazar)\s*$/u', '', $date_label );
    return $date_label ? $date_label : 'Tarih kontrol edilecek';
}

function mdg_kommo_active_events_event_answer_text( array $event ) {
    $maps = ! empty( $event['maps_url'] ) ? ' Konum: ' . $event['maps_url'] : ' Google Maps bağlantısı eksikse açık adresi paylaş ve temsilciye aktar.';
    return sprintf(
        '%s gösterimiz %s tarihinde %s salonunda yapılacaktır. Adres: %s. Seanslar: %s. Fiyatlar: %s. Bilet: %s.%s',
        $event['city_label'],
        mdg_kommo_active_events_short_date( $event['date_label'] ),
        $event['venue'],
        $event['address'],
        implode( ', ', $event['sessions'] ),
        $event['prices'],
        $event['ticket_url'],
        $maps
    );
}

function mdg_kommo_active_events_admin_menu() {
    add_submenu_page(
        'mmc-dashboard',
        'Kommo Aktif Etkinlik Kaynağı',
        'Kommo Aktif Kaynak',
        'manage_options',
        'mdg-kommo-active-events-source',
        'mdg_kommo_active_events_admin_page',
        59
    );
}

function mdg_kommo_active_events_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Yetki yok.', 'madagaskar-management-center' ) );
    }

    $events     = mdg_kommo_active_events_collect( true );
    $source_url = mdg_kommo_active_events_source_url();
    $state      = get_option( 'mdg_kommo_active_events_url_source', array() );
    $text_state     = get_option( 'mdg_kommo_active_events_text_source', array() );
    $location_state = get_option( 'mdg_kommo_active_events_locations_text_source', array() );
    $last           = get_option( 'mdg_kommo_active_events_last_sync', array() );

    echo '<div class="wrap mdg-active-source"><h1>Kommo Aktif Etkinlik Kaynağı</h1>';
    echo '<style>.mdg-active-source .card{max-width:1180px}.mdg-active-source code{word-break:break-all}.mdg-active-source table{border-collapse:collapse;width:100%;background:#fff}.mdg-active-source th,.mdg-active-source td{border:1px solid #dcdcde;padding:8px;text-align:left;vertical-align:top}.mdg-active-source th{background:#f6f7f7}.mdg-active-source .ok{color:#0a7f32;font-weight:700}.mdg-active-source .bad{color:#b32d2e;font-weight:700}</style>';

    if ( ! empty( $_GET['synced'] ) ) {
        $notice = ! empty( $last['ok'] ) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $notice ) . ' is-dismissible"><p>Senkronizasyon sonuçlarını aşağıda kontrol edin.</p></div>';
    }

    echo '<div class="card"><h2>Gizli Kommo AI Bilgi Merkezi URL</h2>';
    echo '<p>Kommo AI içinde program, seans, fiyat, salon, adres, Google Maps, bilet bağlantıları ve müşteri güvenli genel kurallar için bu tek gizli kaynak kullanılacak.</p>';
    echo '<p><code>' . esc_html( $source_url ) . '</code></p>';
    echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $source_url ) . '">Kaynağı Aç</a> ';
    echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'mdg-kommo-active-events-source', 'refresh' => 1 ), admin_url( 'admin.php' ) ) ) . '">Önizlemeyi Yenile</a></p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'mdg_kommo_active_events_sync' );
    echo '<input type="hidden" name="action" value="mdg_kommo_active_events_sync">';
    echo '<p><button class="button button-primary" type="submit">Kommo’ya Gizli Bilgi Merkezi Olarak Ekle / Doğrula</button></p>';
    echo '</form>';

    $details = ! empty( $last['details'] ) && is_array( $last['details'] ) ? $last['details'] : array();
    $rows    = array(
        array( 'key' => 'url',       'label' => 'MMC | Aktif Satıştaki Etkinlikler (gizli URL kaynağı)',  'state' => $state,          'when' => $state['updated_at'] ?? ( $state['created_at'] ?? '' ) ),
        array( 'key' => 'program',   'label' => 'MMC | Aktif Programlar | Global Kısa Cevap',            'state' => $text_state,     'when' => $text_state['updated_at'] ?? '' ),
        array( 'key' => 'locations', 'label' => 'MMC | Aktif Etkinlik Konumları',                        'state' => $location_state, 'when' => $location_state['updated_at'] ?? '' ),
    );

    echo '<h3>Kommo kaynakları (son senkronizasyon: ' . esc_html( $last['at'] ?? 'henüz yok' ) . ')</h3>';
    echo '<table><thead><tr><th>Kaynak</th><th>Source ID</th><th>Kayıt zamanı</th><th>Son işlem sonucu</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        $detail   = isset( $details[ $row['key'] ] ) ? $details[ $row['key'] ] : array();
        $id       = ! empty( $row['state']['source_id'] ) ? '#' . (string) $row['state']['source_id'] : '<span class="bad">Yok</span>';
        $id_html  = 0 === strpos( $id, '<' ) ? $id : esc_html( $id );
        if ( empty( $detail ) ) {
            $result = '<span>Henüz senkronize edilmedi</span>';
        } elseif ( empty( $detail['ok'] ) ) {
            $result = '<span class="bad">HATA - Kommo\'daki kaynak GÜNCEL DEĞİL: ' . esc_html( (string) $detail['error'] ) . '</span>';
        } elseif ( ! empty( $detail['warning'] ) ) {
            $result = '<span style="color:#996800;font-weight:700">UYARI: ' . esc_html( (string) $detail['warning'] ) . '</span>';
        } else {
            $result = '<span class="ok">Başarılı (' . esc_html( (string) $detail['status'] ) . ')</span>';
        }
        echo '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . $id_html . '</td><td>' . esc_html( (string) $row['when'] ) . '</td><td>' . $result . '</td></tr>';
    }
    echo '</tbody></table>';

    $collect = get_option( 'mdg_kommo_active_events_last_collect', array() );
    if ( ! empty( $collect ) ) {
        echo '<p>Son okuma: ' . esc_html( (string) ( $collect['events'] ?? 0 ) ) . ' aktif etkinlik / ' . esc_html( (string) ( $collect['links'] ?? 0 ) ) . ' link · ' . esc_html( $collect['at'] ?? '' ) . '</p>';
        if ( ! empty( $collect['failed'] ) ) {
            echo '<p class="bad">Geçici okunamayan sayfalar (kaynaklar bu yüzden güncellenmedi): ' . esc_html( implode( ', ', (array) $collect['failed'] ) ) . '</p>';
        }
        if ( ! empty( $collect['not_event'] ) ) {
            echo '<p>Etkinlik verisi bulunamayan linkler (taslak/eski/tahmin edilen link olabilir): ' . esc_html( implode( ', ', (array) $collect['not_event'] ) ) . '</p>';
        }
    }
    if ( ! empty( $last['message'] ) && empty( $details ) ) {
        $class = ! empty( $last['ok'] ) ? 'ok' : 'bad';
        echo '<p class="' . esc_attr( $class ) . '">Son işlem: ' . esc_html( $last['message'] ) . '</p>';
    }
    echo '</div>';

    echo '<h2>Önizleme (' . esc_html( (string) count( $events ) ) . ' etkinlik)</h2>';
    echo '<table><thead><tr><th>Etkinlik</th><th>Tarih / Seans</th><th>Salon / Adres</th><th>Google Maps</th><th>Bilet</th></tr></thead><tbody>';
    foreach ( $events as $event ) {
        echo '<tr>';
        echo '<td><strong>' . esc_html( $event['name'] ) . '</strong><br>' . esc_html( $event['city_label'] ) . '</td>';
        echo '<td>' . esc_html( $event['date_label'] ) . '<br>' . esc_html( implode( ', ', $event['sessions'] ) ) . '</td>';
        echo '<td>' . esc_html( $event['venue'] ) . '<br>' . esc_html( $event['address'] ) . '</td>';
        echo '<td>' . ( $event['maps_url'] ? '<a target="_blank" rel="noopener" href="' . esc_url( $event['maps_url'] ) . '">Harita</a>' : '<span class="bad">Eksik</span>' ) . '</td>';
        echo '<td><a target="_blank" rel="noopener" href="' . esc_url( $event['ticket_url'] ) . '">Bilet</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function mdg_kommo_active_events_admin_card() {
    if ( empty( $_GET['page'] ) || ! in_array( sanitize_key( $_GET['page'] ), array( 'mmc-kommo', 'mmc-kommo-hub' ), true ) ) {
        return;
    }

    $href = add_query_arg( array( 'page' => 'mdg-kommo-active-events-source' ), admin_url( 'admin.php' ) );
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var wrap=document.querySelector(".wrap")||document.querySelector("#wpbody-content");if(!wrap||document.getElementById("mdg-active-events-source-card"))return;var card=document.createElement("div");card.id="mdg-active-events-source-card";card.className="notice notice-success";card.style.padding="14px 16px";card.style.marginTop="14px";card.innerHTML="<p style=\"font-size:15px;margin:0 0 8px\"><strong>Kommo tek aktif etkinlik kaynağı</strong></p><p style=\"margin:0 0 10px\">Satıştaki etkinlikler; dinamik URL kaynağına ek olarak ayrı kısa Program ve Konum kaynaklarına bölünür. Böylece şehir, salon, seans, fiyat ve Google Maps sorguları daha güvenilir eşleşir.</p><p style=\"margin:0\"><a class=\"button button-primary\" href=\"' . esc_url( $href ) . '\">Aktif Etkinlik Kaynağını Aç</a></p>";wrap.appendChild(card);});</script>';
}

function mdg_kommo_active_events_sync_post() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Yetki yok.', 'madagaskar-management-center' ) );
    }
    check_admin_referer( 'mdg_kommo_active_events_sync' );

    mdg_kommo_active_events_sync_all();

    wp_safe_redirect( add_query_arg( array( 'page' => 'mdg-kommo-active-events-source', 'synced' => 1 ), admin_url( 'admin.php' ) ) );
    exit;
}

function mdg_kommo_active_events_sync_all() {
    if ( get_transient( 'mdg_kommo_unified_sync_lock' ) ) { return; }
    set_transient( 'mdg_kommo_unified_sync_lock', 1, 5 * MINUTE_IN_SECONDS );
    try {
        mdg_kommo_active_events_collect( true );
        $collect    = get_option( 'mdg_kommo_active_events_last_collect', array() );
        $incomplete = ! empty( $collect['failed'] );

        // URL kaynagi canli sayfayi gosterir; etkinlik listesi eksik okunsa bile bundan etkilenmez.
        $url_result = mdg_kommo_active_events_create_url_source();

        if ( $incomplete ) {
            // Eksik liste Kommo'ya yazilmaz: bir etkinligi sessizce dusurmek, eski listeyi korumaktan daha kotudur.
            $msg = 'Bazı etkinlik sayfaları geçici olarak okunamadı; kaynak güncellenmedi, Kommo\'daki önceki sürüm GÜNCEL DEĞİL. Okunamayan: ' . implode( ', ', array_map( 'esc_url_raw', (array) $collect['failed'] ) );
            $text_result     = new WP_Error( 'mdg_kommo_collect_incomplete', $msg );
            $location_result = new WP_Error( 'mdg_kommo_collect_incomplete', $msg );
        } else {
            $text_result     = mdg_kommo_active_events_create_text_source();
            $location_result = mdg_kommo_active_events_create_locations_text_source();
        }

        $details = array(
            'url'       => mdg_kommo_active_events_describe_result( $url_result ),
            'program'   => mdg_kommo_active_events_describe_result( $text_result ),
            'locations' => mdg_kommo_active_events_describe_result( $location_result ),
        );
        $ok = $details['url']['ok'] && $details['program']['ok'] && $details['locations']['ok'];

        update_option(
            'mdg_kommo_active_events_last_sync',
            array(
                'at'      => current_time( 'mysql' ),
                'ok'      => $ok,
                'details' => $details,
                'message' => wp_json_encode( $details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            ),
            false
        );

        foreach ( $details as $name => $detail ) {
            if ( ! $detail['ok'] ) {
                error_log( 'MMC Kommo unified sources: ' . $name . ' kaynağı güncellenemedi: ' . $detail['error'] );
            }
        }
    } finally {
        delete_transient( 'mdg_kommo_unified_sync_lock' );
    }
}

/**
 * Bir kaynak isleminin sonucunu {ok,status,id,error,warning} biciminde ozetler.
 */
function mdg_kommo_active_events_describe_result( $result ) {
    if ( is_wp_error( $result ) ) {
        return array( 'ok' => false, 'status' => 'error', 'id' => '', 'error' => $result->get_error_message(), 'warning' => '' );
    }
    if ( ! is_array( $result ) ) {
        return array( 'ok' => false, 'status' => 'error', 'id' => '', 'error' => 'Kommo geçerli yanıt döndürmedi.', 'warning' => '' );
    }
    return array(
        'ok'      => true,
        'status'  => isset( $result['status'] ) ? (string) $result['status'] : 'updated',
        'id'      => isset( $result['id'] ) ? (string) $result['id'] : '',
        'error'   => '',
        'warning' => isset( $result['warning'] ) ? (string) $result['warning'] : '',
    );
}

add_action( 'mdg_kommo_unified_hourly_sync', 'mdg_kommo_active_events_sync_all' );
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'mdg_kommo_unified_hourly_sync' ) ) {
        wp_schedule_event( time() + 3600, 'hourly', 'mdg_kommo_unified_hourly_sync' );
    }
} );

function mdg_kommo_active_events_create_url_source() {
    $existing = get_option( 'mdg_kommo_active_events_url_source', array() );
    $token = '';
    if ( defined( 'MMC_KOMMO_TOKEN' ) && MMC_KOMMO_TOKEN ) {
        $token = (string) MMC_KOMMO_TOKEN;
    } elseif ( defined( 'MS_KOMMO_TOKEN' ) && MS_KOMMO_TOKEN ) {
        $token = (string) MS_KOMMO_TOKEN;
    }

    if ( '' === $token ) {
        return new WP_Error( 'mdg_kommo_token_missing', 'Kommo token bulunamadı.' );
    }

    $payload = array(
        'url'                 => mdg_kommo_active_events_source_url(),
        'with_nested'         => false,
        'available_functions' => mdg_kommo_active_events_available_functions(),
    );

    if ( ! empty( $existing['source_id'] ) ) {
        // Registration is not proof of re-indexing. Surface the API result honestly.
        $hash = md5( wp_json_encode( mdg_kommo_active_events_collect() ) );
        if ( isset( $existing['source_hash'] ) && hash_equals( (string) $existing['source_hash'], $hash ) ) {
            return array( 'id' => $existing['source_id'], 'status' => 'already_current' );
        }
        // Bu icerik icin guncelleme zaten denendi ve dogrulanamadi: her saat ayni istekleri tekrarlama.
        if ( isset( $existing['attempted_hash'] ) && hash_equals( (string) $existing['attempted_hash'], $hash ) ) {
            return array(
                'id'      => $existing['source_id'],
                'status'  => 'refresh_not_confirmed',
                'warning' => isset( $existing['last_refresh_error'] ) ? (string) $existing['last_refresh_error'] : 'URL kaynağı yenilemesi doğrulanamadı.',
            );
        }
        $result = mdg_kommo_active_events_source_update_request( $existing['source_id'], $payload, 'url' );
        if ( is_wp_error( $result ) ) {
            // Kaynak Kommo'da kayitli ve canli sayfayi gosterir; ancak API ile yeniden indeksleme DOGRULANAMADI.
            // Bu durum guncel sayilmaz: source_hash yazilmaz, uyari olarak raporlanir.
            $existing['attempted_hash']     = $hash;
            $existing['last_refresh_error'] = 'URL kaynağı Kommo\'da kayıtlı (canlı sayfa), ancak API ile yeniden indeksleme doğrulanamadı: ' . $result->get_error_message();
            update_option( 'mdg_kommo_active_events_url_source', $existing, false );
            error_log( 'MMC Kommo unified sources: ' . $existing['last_refresh_error'] );
            return array(
                'id'      => $existing['source_id'],
                'status'  => 'refresh_not_confirmed',
                'warning' => $existing['last_refresh_error'],
            );
        }
        $existing['source_hash'] = $hash;
        unset( $existing['attempted_hash'], $existing['last_refresh_error'] );
        $existing['updated_at'] = current_time( 'mysql' );
        update_option( 'mdg_kommo_active_events_url_source', $existing, false );
        return $result;
    }

    $response = wp_remote_post(
        'https://airewriter.kommo.com/api/v2/sources/url',
        array(
            'timeout' => 25,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );
    $data = '' !== $body ? json_decode( $body, true ) : array();

    if ( $code < 200 || $code >= 300 ) {
        $detail = is_array( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $body;
        return new WP_Error( 'mdg_kommo_url_source_error', 'Kommo URL source API hata kodu: ' . $code . ' - ' . substr( (string) $detail, 0, 600 ) );
    }

    $source_id = isset( $data['id'] ) ? sanitize_text_field( (string) $data['id'] ) : '';
    if ( '' === $source_id ) {
        return new WP_Error( 'mdg_kommo_url_source_missing_id', 'Kommo URL source oluşturuldu ancak ID dönmedi.' );
    }

    update_option(
        'mdg_kommo_active_events_url_source',
        array(
            'source_id'           => $source_id,
            'source_url'          => mdg_kommo_active_events_source_url(),
            'available_functions' => mdg_kommo_active_events_available_functions(),
            'functions_version'   => 'agent_v3',
            'source_hash'         => md5( wp_json_encode( mdg_kommo_active_events_collect() ) ),
            'created_at'          => current_time( 'mysql' ),
        ),
        false
    );

    return $data;
}

function mdg_kommo_active_events_create_text_source() {
    $events = mdg_kommo_active_events_collect();
    if ( empty( $events ) ) {
        return new WP_Error( 'mdg_kommo_text_source_empty', 'Aktif etkinlik bulunamadı.' );
    }

    $text     = mdg_kommo_active_events_compact_text( $events );
    $length   = mb_strlen( $text, 'UTF-8' );
    if ( $length > (int) MDG_KOMMO_PROGRAM_TEXT_LIMIT ) {
        return new WP_Error( 'program_overflow', 'Tüm program ' . $length . ' karakter; ' . (int) MDG_KOMMO_PROGRAM_TEXT_LIMIT . ' sınırına sığmadı. Kaynak kesilmedi ve Kommo\'daki önceki program kaynağı GÜNCEL DEĞİL (eski hash korunuyor).' );
    }
    $hash     = md5( $text . '|agent_v7_program' );
    $existing = get_option( 'mdg_kommo_active_events_text_source', array() );

    if ( ! empty( $existing['source_id'] ) && ! empty( $existing['source_hash'] ) && hash_equals( (string) $existing['source_hash'], $hash ) ) {
        return array(
            'id'      => $existing['source_id'],
            'status'  => 'already_current',
            'message' => 'Kısa metin kaynağı zaten güncel.',
        );
    }

    $payload = array(
        'name'                => 'MMC | Aktif Programlar | Global Kısa Cevap',
        'lang'                => 'tr',
        'text'                => $text,
        'available_functions' => mdg_kommo_active_events_available_functions(),
    );

    if ( ! empty( $existing['source_id'] ) ) {
        $response = mdg_kommo_active_events_source_update_request( (int) $existing['source_id'], $payload );
    } elseif ( function_exists( 'mdg_kommo_ai_text_source_request' ) ) {
        $response = mdg_kommo_ai_text_source_request( $payload );
    } else {
        $response = mdg_kommo_active_events_text_source_request( $payload );
    }

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( ! is_array( $response ) ) {
        return new WP_Error( 'invalid_response', 'Kommo geçerli güncelleme yanıtı döndürmedi; eski hash korunuyor.' );
    }

    $source_id = is_array( $response ) && ! empty( $response['id'] ) ? absint( $response['id'] ) : absint( $existing['source_id'] ?? 0 );
    if ( ! $source_id ) {
        return new WP_Error( 'mdg_kommo_text_source_missing_id', 'Kommo kısa metin source ID alınamadı.' );
    }

    update_option(
        'mdg_kommo_active_events_text_source',
        array(
            'source_id'   => $source_id,
            'source_hash' => $hash,
            'updated_at'  => current_time( 'mysql' ),
        ),
        false
    );

    $response['id']          = $source_id;
    $response['source_hash'] = $hash;
    return $response;
}

function mdg_kommo_active_events_create_locations_text_source() {
    $events = mdg_kommo_active_events_collect();
    if ( empty( $events ) ) {
        return new WP_Error( 'mdg_kommo_locations_source_empty', 'Aktif etkinlik bulunamadı.' );
    }

    $text     = mdg_kommo_active_events_compact_locations_text( $events );
    $length   = mb_strlen( $text, 'UTF-8' );
    if ( $length > (int) MDG_KOMMO_LOCATIONS_TEXT_LIMIT ) {
        return new WP_Error( 'locations_overflow', 'Konum metni ' . $length . ' karakter; ' . (int) MDG_KOMMO_LOCATIONS_TEXT_LIMIT . ' sınırını aşıyor. Kaynak kesilmedi ve Kommo\'daki önceki konum kaynağı GÜNCEL DEĞİL.' );
    }
    $hash     = md5( $text . '|agent_v6_locations' );
    $existing = get_option( 'mdg_kommo_active_events_locations_text_source', array() );

    if ( ! empty( $existing['source_id'] ) && ! empty( $existing['source_hash'] ) && hash_equals( (string) $existing['source_hash'], $hash ) ) {
        return array(
            'id'      => $existing['source_id'],
            'status'  => 'already_current',
            'message' => 'Konum kısa metin kaynağı zaten güncel.',
        );
    }

    $payload = array(
        'name'                => 'MMC | Aktif Etkinlik Konumları',
        'lang'                => 'tr',
        'text'                => $text,
        'available_functions' => mdg_kommo_active_events_available_functions(),
    );

    if ( ! empty( $existing['source_id'] ) ) {
        $response = mdg_kommo_active_events_source_update_request( (int) $existing['source_id'], $payload );
    } elseif ( function_exists( 'mdg_kommo_ai_text_source_request' ) ) {
        $response = mdg_kommo_ai_text_source_request( $payload );
    } else {
        $response = mdg_kommo_active_events_text_source_request( $payload );
    }

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( ! is_array( $response ) ) {
        return new WP_Error( 'invalid_response', 'Kommo geçerli güncelleme yanıtı döndürmedi; eski hash korunuyor.' );
    }

    $source_id = is_array( $response ) && ! empty( $response['id'] ) ? absint( $response['id'] ) : absint( $existing['source_id'] ?? 0 );
    if ( ! $source_id ) {
        return new WP_Error( 'mdg_kommo_locations_source_missing_id', 'Kommo konum kısa metin source ID alınamadı.' );
    }

    update_option(
        'mdg_kommo_active_events_locations_text_source',
        array(
            'source_id'   => $source_id,
            'source_hash' => $hash,
            'updated_at'  => current_time( 'mysql' ),
        ),
        false
    );

    $response['id']          = $source_id;
    $response['source_hash'] = $hash;
    return $response;
}

/**
 * Program kisa kaynagi. Her aktif etkinlik YALNIZ BIR satirdir: Sehir | Tarih | Salon | Seans | Fiyat.
 * Metin siniri asilirsa etkinlik dusurulmez; once fiyat biçimi, sonra yil/ust bilgi kisaltilir.
 * Fiyat her zaman etkinligin kendi kaydindan gelir; sehirler arasi varsayilan fiyat tasinmaz.
 */
function mdg_kommo_active_events_compact_text( array $events ) {
    $limit = (int) MDG_KOMMO_PROGRAM_TEXT_LIMIT;
    $bilet = 'https://madagaskarsirki.com/bilet-al/';

    $levels = array(
        // 1) Tam fiyat metni.
        array(
            'header'   => "AKTİF PROGRAM (Şehir | Tarih | Salon | Seans | Fiyat)\n",
            'footer'   => "\nAnkara denirse Ankara ilçelerinin hepsini listele. Konuşmadaki şehri koru. Online bilet: " . $bilet,
            'prices'   => 'full',
            'year'     => true,
        ),
        // 2) Kisa fiyat kodu (her etkinligin kendi fiyati): Ç=çocuk 3-12, Y=yetişkin 13+, A=aile paketi.
        array(
            'header'   => "AKTİF PROGRAM (Şehir | Tarih | Salon | Seans | Fiyat TL; Ç=çocuk 3-12, Y=yetişkin 13+, A=aile paketi, 0-2 yaş ücretsiz)\n",
            'footer'   => "\nAnkara denirse Ankara ilçelerinin hepsini listele. Konuşmadaki şehri koru. Online bilet: " . $bilet,
            'prices'   => 'code',
            'year'     => true,
        ),
        // 3) Kisa fiyat kodu + yilsiz tarih + kisa ust/alt bilgi.
        array(
            'header'   => "AKTİF PROGRAM (Şehir | Tarih | Salon | Seans | Fiyat TL; Ç=çocuk, Y=yetişkin, A=aile)\n",
            'footer'   => "\nAnkara: tüm ilçeleri say. Bilet: " . $bilet,
            'prices'   => 'code',
            'year'     => false,
        ),
    );

    $text = '';
    foreach ( $levels as $level ) {
        $lines = array();
        foreach ( $events as $event ) {
            $lines[] = mdg_kommo_active_events_compact_event_line( $event, $level['prices'], $level['year'] );
        }
        $text = $level['header'] . implode( "\n", $lines ) . $level['footer'];
        if ( mb_strlen( $text, 'UTF-8' ) <= $limit ) {
            return $text;
        }
    }

    // Son seviye de sigmadi: hicbir etkinlik kesilmez; cagiran taraf hatayi gorunur sekilde raporlar.
    return $text;
}

function mdg_kommo_active_events_compact_event_line( array $event, $price_mode = 'full', $with_year = true ) {
    $city = (string) $event['city_label'];
    if ( mdg_kommo_active_events_is_ankara_event( $event ) && false === mb_stripos( $city, 'Ankara', 0, 'UTF-8' ) ) {
        $city .= '/Ankara';
    }

    $date = mdg_kommo_active_events_short_date( $event['date_label'] );
    if ( ! $with_year ) {
        $date = trim( (string) preg_replace( '/\s+20\d{2}\s*$/u', '', $date ) );
    }

    $prices = (string) $event['prices'];
    if ( 'code' === $price_mode ) {
        $prices = mdg_kommo_active_events_price_code( $prices );
    }

    return sprintf(
        '%s | %s | %s | %s | %s',
        $city,
        $date,
        $event['venue'],
        implode( '/', $event['sessions'] ),
        $prices
    );
}

/**
 * "Çocuk 250 TL; Yetişkin 500 TL; Aile Paketi 1.100 TL" -> "Ç250/Y500/A1.100".
 * Fiyat metni tanınmazsa olduğu gibi bırakılır (değer uydurulmaz).
 */
function mdg_kommo_active_events_price_code( $prices ) {
    if ( preg_match( '/Çocuk\s*([0-9\.\,]+)\s*TL;\s*Yetişkin\s*([0-9\.\,]+)\s*TL;\s*Aile Paketi\s*([0-9\.\,]+)\s*TL/iu', (string) $prices, $m ) ) {
        return 'Ç' . $m[1] . '/Y' . $m[2] . '/A' . $m[3];
    }
    return (string) $prices;
}

/**
 * Konum kisa kaynagi. Her aktif etkinlik icin: Sehir/Ilce | Salon | Acik adres | Google Maps.
 * Maps baglantisi yoksa baglanti uydurulmaz.
 */
function mdg_kommo_active_events_compact_locations_text( array $events ) {
    $text  = "AKTİF ETKİNLİK KONUMLARI (Şehir/İlçe | Salon | Açık adres | Google Maps)\n";
    $text .= "Salon nerede, salon, adres, konum, konumu var mı, yol tarifi, nasıl giderim sorularında bu kaynağı kullan. Konuşmadaki şehri koru, şehri tekrar sorma. Salon + açık adres + kayıtlı Maps bağlantısını ver; bağlantı yoksa uydurma, adresi paylaş.\n";

    foreach ( $events as $event ) {
        $city = (string) $event['city_label'];
        if ( mdg_kommo_active_events_is_ankara_event( $event ) && false === mb_stripos( $city, 'Ankara', 0, 'UTF-8' ) ) {
            $city .= '/Ankara';
        }
        $maps  = ! empty( $event['maps_url'] ) ? $event['maps_url'] : 'Maps bağlantısı yok';
        $text .= sprintf(
            "%s | %s | %s | %s\n",
            $city,
            $event['venue'],
            $event['address'],
            $maps
        );
    }

    return trim( $text ); // Never silently cut off an event or Maps URL.
}

function mdg_kommo_active_events_limit_text( $text, $limit ) {
    $text  = trim( (string) $text );
    $limit = absint( $limit );
    if ( ! $limit ) {
        return $text;
    }

    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
    if ( $length <= $limit ) {
        return $text;
    }

    $suffix  = "\n[Kısaltıldı. Ayrıntılı salon/adres/Google Maps için tek URL kaynağı kullanılmalıdır.]";
    $cut_at  = max( 0, $limit - ( function_exists( 'mb_strlen' ) ? mb_strlen( $suffix, 'UTF-8' ) : strlen( $suffix ) ) );
    $trimmed = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $cut_at, 'UTF-8' ) : substr( $text, 0, $cut_at );
    return rtrim( $trimmed ) . $suffix;
}

function mdg_kommo_active_events_text_source_request( array $payload ) {
    $token = '';
    if ( defined( 'MMC_KOMMO_TOKEN' ) && MMC_KOMMO_TOKEN ) {
        $token = (string) MMC_KOMMO_TOKEN;
    } elseif ( defined( 'MS_KOMMO_TOKEN' ) && MS_KOMMO_TOKEN ) {
        $token = (string) MS_KOMMO_TOKEN;
    }

    if ( '' === $token ) {
        return new WP_Error( 'mdg_kommo_token_missing', 'Kommo token bulunamadı.' );
    }

    $response = wp_remote_post(
        'https://airewriter.kommo.com/api/v2/sources/text',
        array(
            'timeout' => 25,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );
    $data = '' !== $body ? json_decode( $body, true ) : array();

    if ( $code < 200 || $code >= 300 ) {
        $detail = is_array( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $body;
        return new WP_Error( 'mdg_kommo_text_source_error', 'Kommo AI text source API hata kodu: ' . $code . ' - ' . substr( (string) $detail, 0, 600 ) );
    }

    return is_array( $data ) ? $data : array();
}

function mdg_kommo_active_events_available_functions() {
    return array( 'agent' );
}

function mdg_kommo_active_events_source_update_request( $source_id, array $payload, $type = 'text' ) {
    $source_id = absint( $source_id );
    if ( ! $source_id ) {
        return new WP_Error( 'mdg_kommo_source_update_missing_id', 'Güncellenecek Kommo source ID bulunamadı.' );
    }

    $token = '';
    if ( defined( 'MMC_KOMMO_TOKEN' ) && MMC_KOMMO_TOKEN ) {
        $token = (string) MMC_KOMMO_TOKEN;
    } elseif ( defined( 'MS_KOMMO_TOKEN' ) && MS_KOMMO_TOKEN ) {
        $token = (string) MS_KOMMO_TOKEN;
    }

    if ( '' === $token ) {
        return new WP_Error( 'mdg_kommo_token_missing', 'Kommo token bulunamadı.' );
    }

    $type     = sanitize_key( $type );
    $attempts = array(
        array( 'PATCH', 'https://airewriter.kommo.com/api/v2/sources/' . $type . '/' . $source_id ),
        array( 'PUT', 'https://airewriter.kommo.com/api/v2/sources/' . $type . '/' . $source_id ),
        array( 'PATCH', 'https://airewriter.kommo.com/api/v2/sources/' . $source_id ),
        array( 'PUT', 'https://airewriter.kommo.com/api/v2/sources/' . $source_id ),
    );
    $errors = array();

    foreach ( $attempts as $attempt ) {
        $response = wp_remote_request(
            $attempt[1],
            array(
                'method'  => $attempt[0],
                'timeout' => 25,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $errors[] = $attempt[0] . ' ' . $attempt[1] . ': ' . $response->get_error_message();
            continue;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = '' !== $body ? json_decode( $body, true ) : array();

        if ( $code >= 200 && $code < 300 ) {
            if ( ! is_array( $data ) ) {
                $data = array();
            }
            $data['id']               = $source_id;
            $data['_update_endpoint'] = $attempt[0] . ' ' . $attempt[1];
            return $data;
        }

        $detail   = is_array( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $body;
        $errors[] = $attempt[0] . ' ' . $attempt[1] . ' => ' . $code . ' ' . substr( (string) $detail, 0, 300 );
    }

    return new WP_Error(
        'mdg_kommo_source_update_failed',
        'Kommo mevcut source güncellemesi başarısız: ' . implode( ' | ', $errors )
    );
}

function mdg_kommo_active_events_collect( $force_refresh = false ) {
    if ( ! $force_refresh ) {
        $cached = get_transient( 'mdg_kommo_active_events_rows' );
        if ( is_array( $cached ) ) {
            return $cached;
        }
    }

    $links     = mdg_kommo_active_events_detail_links();
    $events    = array();
    $failed    = array(); // Gecici okuma hatalari (zaman asimi / 5xx): etkinlik sessizce dusmemeli.
    $not_event = array(); // 404 veya etkinlik verisi yok: tahmin edilen link gecersiz olabilir.

    foreach ( $links as $link ) {
        $reason = '';
        $event  = mdg_kommo_active_events_parse_event_page( $link, 6, $reason );
        if ( empty( $event ) && 'transient' === $reason ) {
            $event = mdg_kommo_active_events_parse_event_page( $link, 15, $reason ); // Tek seferlik yeniden deneme.
        }
        if ( empty( $event ) ) {
            if ( 'transient' === $reason ) {
                $failed[] = $link;
            } else {
                $not_event[] = $link;
            }
            continue;
        }
        if ( ! empty( $event['name'] ) && mdg_kommo_active_events_is_future_event( $event ) ) {
            $event_key = sanitize_title( $event['name'] . '-' . $event['date_label'] );
            $events[ $event_key ] = $event;
        }
    }

    $events = array_values( $events );

    usort(
        $events,
        function ( $a, $b ) {
            return strcmp( (string) $a['sort_key'], (string) $b['sort_key'] );
        }
    );

    update_option(
        'mdg_kommo_active_events_last_collect',
        array(
            'at'        => current_time( 'mysql' ),
            'links'     => count( $links ),
            'events'    => count( $events ),
            'failed'    => $failed,
            'not_event' => $not_event,
        ),
        false
    );
    if ( ! empty( $failed ) ) {
        error_log( 'MMC Kommo unified sources: ' . count( $failed ) . ' etkinlik sayfası geçici olarak okunamadı: ' . implode( ', ', $failed ) );
    }

    // Eksik okunmus (gecici hata) liste onbellege alinmaz.
    if ( empty( $failed ) ) {
        set_transient( 'mdg_kommo_active_events_rows', $events, 10 * MINUTE_IN_SECONDS );
    }
    return $events;
}

function mdg_kommo_active_events_detail_links() {
    $response = wp_remote_get(
        add_query_arg( 'mdg_kommo_source_nocache', time(), home_url( '/bilet-al/' ) ),
        array(
            'timeout' => 15,
            'headers' => array(
                'Accept'        => 'text/html',
                'Cache-Control' => 'no-cache',
                'Pragma'        => 'no-cache',
            ),
        )
    );

    if ( is_wp_error( $response ) ) {
        // Liste sayfasi okunamasa bile WooCommerce urunlerinden uretilen linkler kullanilir.
        error_log( 'MMC Kommo unified sources: /bilet-al/ okunamadı: ' . $response->get_error_message() );
        $html = '';
    } else {
        $html = (string) wp_remote_retrieve_body( $response );
    }
    preg_match_all( '#href=["\']([^"\']*/etkinlik/[^"\']+)["\']#i', $html, $matches );

    $links = array();
    foreach ( $matches[1] as $href ) {
        $href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
        $href = strtok( $href, '#' );
        if ( 0 === strpos( $href, '/' ) ) {
            $href = home_url( $href );
        }
        $links[] = esc_url_raw( $href );
    }

    return array_values( array_unique( array_filter( array_merge( $links, mdg_kommo_active_events_product_detail_links() ) ) ) );
}

function mdg_kommo_active_events_product_detail_links() {
    if ( ! function_exists( 'wc_get_products' ) ) {
        return array();
    }

    $products = wc_get_products(
        array(
            'status'  => 'publish',
            'limit'   => 300,
            'orderby' => 'date',
            'order'   => 'DESC',
        )
    );

    $groups = array();
    foreach ( $products as $product ) {
        if ( ! is_object( $product ) || ! method_exists( $product, 'get_name' ) ) {
            continue;
        }
        if ( method_exists( $product, 'is_purchasable' ) && ! $product->is_purchasable() ) {
            continue;
        }
        if ( method_exists( $product, 'is_in_stock' ) && ! $product->is_in_stock() ) {
            continue;
        }

        $parsed = mdg_kommo_active_events_parse_product_name( $product->get_name() );
        if ( empty( $parsed['location'] ) || empty( $parsed['date'] ) ) {
            continue;
        }

        $key = md5( $parsed['location'] . '|' . $parsed['date'] );
        if ( empty( $groups[ $key ] ) ) {
            $groups[ $key ] = array(
                'location' => $parsed['location'],
                'date'     => $parsed['date'],
                'times'    => array(),
            );
        }
        if ( ! empty( $parsed['time'] ) ) {
            $groups[ $key ]['times'][] = $parsed['time'];
        }
    }

    $links = array();
    foreach ( $groups as $group ) {
        if ( ! mdg_kommo_active_events_product_group_is_future( $group['date'], $group['times'] ) ) {
            continue;
        }
        foreach ( mdg_kommo_active_events_candidate_event_slugs( $group['location'], $group['date'] ) as $slug ) {
            $links[] = home_url( '/etkinlik/' . $slug . '/' );
        }
    }

    return array_values( array_unique( $links ) );
}

function mdg_kommo_active_events_parse_product_name( $name ) {
    $name  = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
    $parts = preg_split( '/\s+[–—-]\s+/u', $name );
    if ( count( $parts ) < 4 ) {
        return array();
    }

    $time = '';
    if ( preg_match( '/([0-2]?\d[:.][0-5]\d)/u', $parts[3], $match ) ) {
        $time = str_replace( '.', ':', $match[1] );
    }

    return array(
        'location' => trim( $parts[1] ),
        'date'     => trim( $parts[2] ),
        'time'     => $time,
    );
}

function mdg_kommo_active_events_candidate_event_slugs( $location, $date_label ) {
    $date = mdg_kommo_active_events_parse_turkish_date( $date_label, '00:00' );
    if ( ! $date ) {
        return array();
    }

    $month_slug = sanitize_title( $date['month_name'] );
    $date_slug  = sprintf( '%02d-%s-%04d', $date['day'], $month_slug, $date['year'] );

    $location_parts = array_map( 'trim', explode( '/', (string) $location ) );
    $first          = isset( $location_parts[0] ) ? $location_parts[0] : '';
    $second         = isset( $location_parts[1] ) ? $location_parts[1] : '';
    $first_slug     = sanitize_title( $first );
    $second_slug    = sanitize_title( $second );
    $loc_slug       = '';

    if ( $first_slug && $second_slug && 'merkez' !== $second_slug ) {
        $loc_slug = 'ankara' === $first_slug ? $second_slug . '-' . $first_slug : $first_slug . '-' . $second_slug;
    } elseif ( $first_slug ) {
        $loc_slug = $first_slug;
    }

    $slugs = array();
    if ( $loc_slug ) {
        $slugs[] = 'madagaskar-sirki-' . $loc_slug . '-' . $date_slug;
    }

    return $slugs;
}

function mdg_kommo_active_events_parse_event_page( $url, $timeout = 6, &$reason = '' ) {
    $reason   = '';
    $response = wp_remote_get(
        $url,
        array(
            'timeout' => (int) $timeout,
            'headers' => array( 'Accept' => 'text/html' ),
        )
    );

    if ( is_wp_error( $response ) ) {
        $reason = 'transient';
        return array();
    }

    $status = (int) wp_remote_retrieve_response_code( $response );
    if ( $status >= 500 || 429 === $status ) {
        $reason = 'transient';
        return array();
    }
    if ( $status >= 400 ) {
        $reason = 'not_event';
        return array();
    }

    $html       = (string) wp_remote_retrieve_body( $response );
    $event_json = mdg_kommo_active_events_json_ld_event( $html );
    if ( empty( $event_json['startDate'] ) || empty( $event_json['location'] ) ) {
        $reason = 'not_event';
        return array();
    }

    $text       = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
    $text       = preg_replace( '/\s+/u', ' ', $text );

    $name       = isset( $event_json['name'] ) ? (string) $event_json['name'] : mdg_kommo_active_events_match_text( '/<h1[^>]*>(.*?)<\/h1>/is', $html );
    $location   = isset( $event_json['location'] ) && is_array( $event_json['location'] ) ? $event_json['location'] : array();
    $venue      = isset( $location['name'] ) ? (string) $location['name'] : mdg_kommo_active_events_between_text( $text, 'Salon', 'Fiyat' );
    $address    = mdg_kommo_active_events_address_from_location( $location );
    $maps_url   = mdg_kommo_active_events_maps_url( $html );
    $sessions   = mdg_kommo_active_events_sessions( $event_json, $text );
    $start_date = isset( $event_json['startDate'] ) ? (string) $event_json['startDate'] : '';
    $date_label = mdg_kommo_active_events_date_label( $start_date, $text );
    $prices     = mdg_kommo_active_events_prices( $event_json, $text, $name );

    return array(
        'name'       => $name ? $name : 'Madagaskar Sirki',
        'city_label' => mdg_kommo_active_events_city_label( $name, $address ),
        'date_label' => $date_label,
        'sort_key'   => $start_date ? $start_date : $date_label,
        'venue'      => $venue ? $venue : 'Salon bilgisi kontrol edilecek',
        'address'    => $address ? $address : 'Açık adres kontrol edilecek',
        'maps_url'   => $maps_url,
        'sessions'   => ! empty( $sessions ) ? $sessions : array( 'Seans bilgisi kontrol edilecek' ),
        'prices'     => $prices,
        'ticket_url' => 'https://madagaskarsirki.com/bilet-al/',
        'keywords'   => mdg_kommo_active_events_keywords( $name, $venue, $address ),
    );
}

function mdg_kommo_active_events_json_ld_event( $html ) {
    preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', (string) $html, $matches );
    foreach ( $matches[1] as $json ) {
        $json = html_entity_decode( trim( $json ), ENT_QUOTES, 'UTF-8' );
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            continue;
        }
        $event = mdg_kommo_active_events_find_event_json( $data );
        if ( $event ) {
            return $event;
        }
    }
    return array();
}

function mdg_kommo_active_events_find_event_json( $data ) {
    if ( ! is_array( $data ) ) {
        return array();
    }

    if ( isset( $data['@type'] ) ) {
        $type = is_array( $data['@type'] ) ? implode( ' ', $data['@type'] ) : (string) $data['@type'];
        if ( false !== stripos( $type, 'Event' ) && ( ! empty( $data['location'] ) || ! empty( $data['offers'] ) || ! empty( $data['subEvent'] ) ) ) {
            return $data;
        }
    }

    if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
        foreach ( $data['@graph'] as $item ) {
            $event = mdg_kommo_active_events_find_event_json( $item );
            if ( $event ) {
                return $event;
            }
        }
    }

    foreach ( $data as $item ) {
        if ( is_array( $item ) ) {
            $event = mdg_kommo_active_events_find_event_json( $item );
            if ( $event ) {
                return $event;
            }
        }
    }

    return array();
}

function mdg_kommo_active_events_address_from_location( $location ) {
    if ( empty( $location['address'] ) || ! is_array( $location['address'] ) ) {
        return '';
    }
    $address = $location['address'];
    $parts   = array();
    foreach ( array( 'streetAddress', 'addressLocality', 'addressRegion' ) as $key ) {
        if ( ! empty( $address[ $key ] ) ) {
            $parts[] = (string) $address[ $key ];
        }
    }
    return implode( ', ', array_unique( array_filter( $parts ) ) );
}

function mdg_kommo_active_events_maps_url( $html ) {
    if ( preg_match( '#href=["\'](https?://(?:maps\.app\.goo\.gl|www\.google\.com/maps|goo\.gl/maps)[^"\']+)["\']#i', (string) $html, $match ) ) {
        return esc_url_raw( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
    }
    return '';
}

function mdg_kommo_active_events_sessions( $event_json, $text ) {
    $sessions = array();
    if ( ! empty( $event_json['subEvent'] ) && is_array( $event_json['subEvent'] ) ) {
        foreach ( $event_json['subEvent'] as $sub_event ) {
            if ( ! empty( $sub_event['startDate'] ) ) {
                $timestamp = strtotime( (string) $sub_event['startDate'] );
                if ( $timestamp ) {
                    $sessions[] = wp_date( 'H:i', $timestamp, wp_timezone() );
                }
            }
        }
    }

    if ( empty( $sessions ) && preg_match_all( '/\b([0-2]?\d[:.][0-5]\d)\b/u', (string) $text, $matches ) ) {
        foreach ( $matches[1] as $time ) {
            $sessions[] = str_replace( '.', ':', $time );
        }
    }

    $sessions = array_values( array_unique( $sessions ) );
    sort( $sessions );
    return $sessions;
}

function mdg_kommo_active_events_date_label( $start_date, $text ) {
    $timestamp = $start_date ? strtotime( (string) $start_date ) : false;
    if ( $timestamp ) {
        return wp_date( 'd F Y, l', $timestamp, wp_timezone() );
    }
    if ( preg_match( '/\b(\d{1,2}\s+[A-Za-zÇĞİÖŞÜçğıöşü]+\s+20\d{2})\b/u', (string) $text, $match ) ) {
        return $match[1];
    }
    return 'Tarih kontrol edilecek';
}

function mdg_kommo_active_events_is_future_event( $event ) {
    $sort_key = isset( $event['sort_key'] ) ? (string) $event['sort_key'] : '';
    $date     = $sort_key ? strtotime( $sort_key ) : false;
    if ( ! $date ) {
        return true;
    }

    $tz       = wp_timezone();
    $date_day = wp_date( 'Y-m-d', $date, $tz );
    $now      = new DateTimeImmutable( 'now', $tz );
    $sessions = ! empty( $event['sessions'] ) && is_array( $event['sessions'] ) ? $event['sessions'] : array();

    if ( empty( $sessions ) ) {
        $end_of_day = new DateTimeImmutable( $date_day . ' 23:59:59', $tz );
        return $end_of_day >= $now;
    }

    foreach ( $sessions as $session ) {
        if ( preg_match( '/([0-2]?\d):([0-5]\d)/', (string) $session, $match ) ) {
            $session_dt = new DateTimeImmutable( $date_day . ' ' . sprintf( '%02d:%02d:00', (int) $match[1], (int) $match[2] ), $tz );
            if ( $session_dt->modify( '+75 minutes' ) >= $now ) {
                return true;
            }
        }
    }

    return false;
}

function mdg_kommo_active_events_product_group_is_future( $date_label, $times ) {
    $tz  = wp_timezone();
    $now = new DateTimeImmutable( 'now', $tz );

    if ( empty( $times ) ) {
        $parsed = mdg_kommo_active_events_parse_turkish_date( $date_label, '23:59' );
        return $parsed && $parsed['datetime'] >= $now;
    }

    foreach ( array_unique( (array) $times ) as $time ) {
        $parsed = mdg_kommo_active_events_parse_turkish_date( $date_label, $time );
        if ( $parsed && $parsed['datetime']->modify( '+75 minutes' ) >= $now ) {
            return true;
        }
    }

    return false;
}

function mdg_kommo_active_events_parse_turkish_date( $date_label, $time ) {
    $months = array(
        'ocak'    => 1,
        'subat'   => 2,
        'şubat'   => 2,
        'mart'    => 3,
        'nisan'   => 4,
        'mayis'   => 5,
        'mayıs'   => 5,
        'haziran' => 6,
        'temmuz'  => 7,
        'agustos' => 8,
        'ağustos' => 8,
        'eylul'   => 9,
        'eylül'   => 9,
        'ekim'    => 10,
        'kasim'   => 11,
        'kasım'   => 11,
        'aralik'  => 12,
        'aralık'  => 12,
    );

    $label = html_entity_decode( (string) $date_label, ENT_QUOTES, 'UTF-8' );
    if ( ! preg_match( '/(\d{1,2})\s+([A-Za-zÇĞİÖŞÜçğıöşü]+)\s+(20\d{2})/u', $label, $match ) ) {
        return null;
    }

    $month_key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $match[2], 'UTF-8' ) : strtolower( $match[2] );
    $month_key = strtr(
        $month_key,
        array(
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        )
    );
    $month_num = isset( $months[ $month_key ] ) ? $months[ $month_key ] : 0;
    if ( ! $month_num ) {
        return null;
    }

    $time = str_replace( '.', ':', (string) $time );
    if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
        $time = '00:00';
    }

    $tz       = wp_timezone();
    $datetime = new DateTimeImmutable(
        sprintf( '%04d-%02d-%02d %s:00', (int) $match[3], $month_num, (int) $match[1], $time ),
        $tz
    );

    return array(
        'day'        => (int) $match[1],
        'month'      => $month_num,
        'month_name' => $match[2],
        'year'       => (int) $match[3],
        'datetime'   => $datetime,
    );
}

function mdg_kommo_active_events_prices( $event_json, $text, $name ) {
    if ( preg_match( '/Çocuk bileti\s*([0-9\.\,]+)\s*TL.*?yetişkin bileti\s*([0-9\.\,]+)\s*TL.*?Aile Paketi\s*([0-9\.\,]+)\s*TL/iu', (string) $text, $match ) ) {
        return 'Çocuk ' . $match[1] . ' TL; Yetişkin ' . $match[2] . ' TL; Aile Paketi ' . $match[3] . ' TL';
    }

    return 'Fiyat kaydı doğrulanamadı; temsilciye aktar';
}

function mdg_kommo_active_events_tl( $value ) {
    $number = (float) str_replace( ',', '.', (string) $value );
    return number_format_i18n( $number, 0 );
}

function mdg_kommo_active_events_city_label( $name, $address ) {
    if ( preg_match( '/[–—-]\s*([^–—-]+)$/u', (string) $name, $match ) ) {
        return trim( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ) );
    }
    if ( preg_match( '/,\s*([^,]+)$/u', (string) $address, $match ) ) {
        return trim( $match[1] );
    }
    return 'Şehir / ilçe kontrol edilecek';
}

function mdg_kommo_active_events_keywords( $name, $venue, $address ) {
    $terms = array( 'Madagaskar Sirki', $name, $venue );
    if ( preg_match_all( '/[A-Za-zÇĞİÖŞÜçğıöşü]{4,}/u', (string) $address, $matches ) ) {
        $terms = array_merge( $terms, array_slice( $matches[0], 0, 6 ) );
    }
    $terms = array_filter( array_unique( array_map( 'trim', $terms ) ) );
    return implode( ', ', $terms );
}

function mdg_kommo_active_events_match_text( $pattern, $text ) {
    if ( preg_match( $pattern, (string) $text, $match ) ) {
        return trim( html_entity_decode( wp_strip_all_tags( $match[1] ), ENT_QUOTES, 'UTF-8' ) );
    }
    return '';
}

function mdg_kommo_active_events_between_text( $text, $start, $end ) {
    $pattern = '/' . preg_quote( $start, '/' ) . '\s*(.*?)\s*' . preg_quote( $end, '/' ) . '/iu';
    if ( preg_match( $pattern, (string) $text, $match ) ) {
        return trim( $match[1] );
    }
    return '';
}

function mdg_kommo_active_events_source_url() {
    return add_query_arg(
        array(
            'token' => mdg_kommo_active_events_token(),
        ),
        home_url( '/kommo-ai-bilgi-merkezi/' )
    );
}

function mdg_kommo_active_events_token() {
    $token = (string) get_option( 'mdg_kommo_active_events_token', '' );
    if ( '' === $token ) {
        $token = wp_generate_password( 32, false, false );
        update_option( 'mdg_kommo_active_events_token', $token, false );
    }
    return $token;
}

function mdg_kommo_active_events_clear_cache() {
    delete_transient( 'mdg_kommo_active_events_rows' );
    if ( ! wp_next_scheduled( 'mdg_kommo_unified_changed_sync' ) ) {
        wp_schedule_single_event( time() + 120, 'mdg_kommo_unified_changed_sync' );
    }
}


add_action( 'mdg_kommo_unified_changed_sync', 'mdg_kommo_active_events_sync_all' );
