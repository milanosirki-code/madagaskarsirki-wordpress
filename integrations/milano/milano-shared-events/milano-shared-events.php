<?php
/**
 * Plugin Name: Milano Sirki - Ortak Etkinlikler (Taslak)
 * Description: Dogrulanmis Milano seanslarini guvenli, varsayilan olarak kapali salt-okunur katalogda gosterir. Satis, odeme veya QR uretmez.
 * Version: 0.3.0
 * Requires PHP: 7.4
 * Text Domain: milano-shared-events
 *
 * WARNING: Draft only. Do not deploy without server-side secret setup and tests.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mso_events_public_enabled() {
    // Two independent gates are required to show real Milano sessions publicly:
    // (1) the host's explicit server-side opt-in, and (2) a valid authenticated
    // canonical feed. A missing constant always keeps the catalogue closed.
    return defined( 'DUNYA_MILANO_PUBLIC_CATALOG_ENABLED' )
        && true === DUNYA_MILANO_PUBLIC_CATALOG_ENABLED;
}

/**
 * Server-to-server only. No endpoint override from request parameters.
 * No cache: inventory information must not be treated as checkout authority.
 * Neither token nor source payload is written to WordPress options or logs.
 */
function mso_events_source() {
    if ( ! defined( 'DUNYA_MILANO_FEED_TOKEN' ) || strlen( (string) DUNYA_MILANO_FEED_TOKEN ) < 32 ) {
        return new WP_Error( 'mso_token_missing', 'Besleme baglantisi hazir degil.' );
    }
    $response = wp_safe_remote_get(
        'https://madagaskarsirki.com/wp-json/dunya-milano/v1/sessions',
        array(
            'timeout'             => 8,
            'redirection'         => 0,
            'limit_response_size' => 262144,
            'sslverify'           => true,
            'headers'             => array(
                'Accept'             => 'application/json',
                'Cache-Control'      => 'no-store',
                'X-Dunya-Milano-Key' => (string) DUNYA_MILANO_FEED_TOKEN,
            ),
        )
    );
    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'mso_feed_unavailable', 'Etkinlik servisine su anda erisilemiyor.' );
    }
    $raw = wp_remote_retrieve_body( $response );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) || 1 !== ( $data['schema_version'] ?? null )
        || true !== ( $data['read_only'] ?? null ) || ! isset( $data['sessions'] )
        || ! is_array( $data['sessions'] ) || ! isset( $data['generated_at'] )
        || ! is_string( $data['generated_at'] ) ) {
        return new WP_Error( 'mso_schema_invalid', 'Etkinlik servisi beklenmedik veri dondurdu.' );
    }

    // Reject stale responses and grossly skewed source clocks.
    $generated = strtotime( $data['generated_at'] );
    if ( ! $generated || abs( time() - $generated ) > 180 ) {
        return new WP_Error( 'mso_stale_feed', 'Etkinlik servisi guncel degil.' );
    }
    if ( count( $data['sessions'] ) > 120 ) {
        return new WP_Error( 'mso_too_many', 'Etkinlik servisi veri sinirini asti.' );
    }

    $output = array();
    foreach ( $data['sessions'] as $row ) {
        // A session must belong exclusively to Milano; unknown or other-brand
        // ownership is never inferred from a venue, domain, query or label.
        // Fail closed until the canonical MDG feed supplies event_owner_brand.
        if ( ! is_array( $row ) || ! isset( $row['event_owner_brand'] )
            || ! is_string( $row['event_owner_brand'] )
            || ! in_array( $row['event_owner_brand'], array( 'milano', 'madagaskar' ), true ) ) {
            return new WP_Error( 'mso_brand_missing', 'Etkinlik marka sahipligi dogrulanamadi.' );
        }
        if ( 'milano' !== $row['event_owner_brand'] ) {
            continue;
        }
        if ( ! is_array( $row )
            || ! isset( $row['session_id'], $row['event_id'], $row['date'],
                        $row['time'], $row['province'], $row['venue'],
                        $row['available_units'], $row['availability'], $row['start_utc'] )
            || ! is_int( $row['session_id'] ) || $row['session_id'] < 1
            || ! is_int( $row['event_id'] ) || $row['event_id'] < 1
            || ! is_string( $row['date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row['date'] )
            || ! is_string( $row['time'] ) || ! preg_match( '/^\d{2}:\d{2}$/', $row['time'] )
            || ! is_int( $row['available_units'] ) || $row['available_units'] < 0
            || ! in_array( $row['availability'], array( 'available', 'sold_out' ), true )
            || ( 0 === $row['available_units'] ) !== ( 'sold_out' === $row['availability'] )
            || ! is_string( $row['start_utc'] )
            || ! is_string( $row['province'] ) || ! is_string( $row['venue'] ) ) {
            return new WP_Error( 'mso_invalid_session', 'Seans bilgileri dogrulanamadi.' );
        }
        $start = strtotime( $row['start_utc'] . ' UTC' );
        if ( ! $start || $start <= time() ) {
            continue;
        }
        $output[] = array(
            'session_id'      => $row['session_id'],
            'event_id'        => $row['event_id'],
            'province'        => sanitize_text_field( $row['province'] ),
            'district'        => sanitize_text_field( (string) ( $row['district'] ?? '' ) ),
            'venue'           => sanitize_text_field( $row['venue'] ),
            'date'            => $row['date'],
            'time'            => $row['time'],
            'available_units' => $row['available_units'],
            'availability'    => $row['availability'],
        );
    }
    return $output;
}


/**
 * Read the announcement-only, manually approved Milano calendar.
 *
 * Unlike the transactional /sessions feed, this route can represent approved
 * DRAFT shows without opening checkout. No inventory, price or ticket mapping
 * is fetched, cached or transmitted to the visitor.
 */
function mso_upcoming_source() {
    if ( ! defined( 'DUNYA_MILANO_FEED_TOKEN' )
        || strlen( (string) DUNYA_MILANO_FEED_TOKEN ) < 32 ) {
        return new WP_Error( 'mso_upcoming_no_key', 'Etkinlik takvimi henuz dogrulanamiyor.' );
    }
    $response = wp_safe_remote_get(
        'https://madagaskarsirki.com/wp-json/dunya-milano/v1/upcoming',
        array(
            'timeout'             => 8,
            'redirection'         => 0,
            'limit_response_size' => 262144,
            'sslverify'           => true,
            'headers'             => array(
                'Accept'             => 'application/json',
                'Cache-Control'      => 'no-store',
                'X-Dunya-Milano-Key' => (string) DUNYA_MILANO_FEED_TOKEN,
            ),
        )
    );
    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'mso_upcoming_unavailable', 'Onayli Milano takvimine erisilemiyor.' );
    }
    $payload = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $payload )
        || ( $payload['schema_version'] ?? null ) !== 1
        || ( $payload['read_only'] ?? null ) !== true
        || ( $payload['catalog_mode'] ?? null ) !== 'announcement_only'
        || ! isset( $payload['sessions'], $payload['generated_at'] )
        || ! is_array( $payload['sessions'] )
        || ! is_string( $payload['generated_at'] )
        || count( $payload['sessions'] ) > 120 ) {
        return new WP_Error( 'mso_upcoming_schema', 'Etkinlik takvimi beklenmedik bicimde.' );
    }
    $generated = strtotime( $payload['generated_at'] );
    if ( ! $generated || abs( time() - $generated ) > 180 ) {
        return new WP_Error( 'mso_upcoming_stale', 'Etkinlik takvimi guncel degil.' );
    }

    $output = array();
    $allowed_fields = array(
        'event_id', 'event_owner_brand', 'session_id', 'province', 'district',
        'venue', 'venue_address', 'date', 'time', 'start_utc',
    );
    foreach ( $payload['sessions'] as $row ) {
        if ( ! is_array( $row ) || ! isset( $row['event_owner_brand'] )
            || ! is_string( $row['event_owner_brand'] )
            || ! in_array( $row['event_owner_brand'], array( 'milano', 'madagaskar' ), true ) ) {
            return new WP_Error( 'mso_upcoming_brand', 'Etkinlik markasi dogrulanamadi.' );
        }
        if ( 'milano' !== $row['event_owner_brand'] ) { continue; }
        foreach ( array_keys( $row ) as $field ) {
            if ( ! in_array( $field, $allowed_fields, true ) ) {
                return new WP_Error( 'mso_upcoming_extra', 'Etkinlik takvimine beklenmeyen alan eklendi.' );
            }
        }
        if ( ! isset( $row['event_id'], $row['session_id'],
                    $row['province'], $row['venue'],
                    $row['date'], $row['time'], $row['start_utc'] )
            || ! is_int( $row['event_id'] ) || $row['event_id'] < 1
            || ! is_int( $row['session_id'] ) || $row['session_id'] < 1
            || ! is_string( $row['province'] ) || '' === trim( $row['province'] )
            || ! is_string( $row['venue'] ) || '' === trim( $row['venue'] )
            || ! is_string( $row['date'] )
            || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row['date'] )
            || ! is_string( $row['time'] )
            || ! preg_match( '/^\d{2}:\d{2}$/', $row['time'] )
            || ! is_string( $row['start_utc'] )
            || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['start_utc'] )
            || ( isset( $row['district'] ) && ! is_string( $row['district'] ) )
            || ( isset( $row['venue_address'] ) && ! is_string( $row['venue_address'] ) ) ) {
            return new WP_Error( 'mso_upcoming_invalid', 'Etkinlik takvimi dogrulanamadi.' );
        }
        if ( strtotime( $row['start_utc'] . ' UTC' ) <= time() ) { continue; }
        $output[] = array(
            'province' => sanitize_text_field( $row['province'] ),
            'district' => sanitize_text_field( (string) ( $row['district'] ?? '' ) ),
            'venue'    => sanitize_text_field( $row['venue'] ),
            'date'     => $row['date'],
            'time'     => $row['time'],
        );
    }
    return $output;
}

function mso_events_shortcode() {
    // Public visitors see only a generic pending message unless the operator
    // explicitly enables a server-side, Milano-only read-only public catalog.
    // Admins may inspect the same data without enabling public catalogue.
    $is_admin = current_user_can( 'manage_options' );
    if ( ! $is_admin && ! mso_events_public_enabled() ) {
        return '<p class="mso-events-pending">Etkinlik takvimi hazirlaniyor. Bilet satisi henuz aktif degildir.</p>';
    }

    $sessions = $is_admin ? mso_events_source() : mso_upcoming_source();
    if ( is_wp_error( $sessions ) ) {
        return '<p class="mso-events-pending">Etkinlik verisi henuz dogrulanamiyor. Bilet satisi kapali.</p>';
    }
    if ( ! $sessions ) {
        return '<div class="mso-events-pending" role="status"><strong>Milano Sirki etkinlik hazırlığı</strong><p>Milano markasına atanmış, doğrulanmış gelecek seans bulunamadı. Bilet satışları kapalı.</p></div>';
    }

    // Self-contained scoped styles for a secure read-only catalogue. No
    // third-party scripts, links, prices, checkout or QR actions.
    $output = '<style>
    .mso-preview{--mso-navy:#091746;--mso-red:#c72528;--mso-gold:#f5c04a;--mso-cream:#fff8ed;font-family:inherit;color:var(--mso-navy);border:1px solid #e4e8f0;border-radius:26px;background:linear-gradient(140deg,#fff8ed 0%,#fff 60%,#f4f6fb 100%);padding:clamp(18px,3vw,34px)}
    .mso-preview *{box-sizing:border-box}
    .mso-preview__top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:22px}
    .mso-preview__eyebrow{display:block;color:#8a251f;font-size:12px;letter-spacing:.13em;font-weight:800;text-transform:uppercase;margin-bottom:8px}
    .mso-preview__heading{font-size:clamp(25px,3vw,35px);font-weight:850;line-height:1.2;margin:0;color:var(--mso-navy)}
    .mso-preview__hint{font-size:13px;line-height:1.55;color:#3d4861;max-width:420px;margin:0}
    .mso-preview__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,255px),1fr));gap:16px}
    .mso-preview__card{background:#fff;border:1px solid #e5e8ef;border-radius:20px;overflow:hidden;box-shadow:0 8px 25px rgba(9,23,70,.065)}
    .mso-preview__bar{height:7px;background:linear-gradient(90deg,var(--mso-red),#f5c04a)}
    .mso-preview__card-body{padding:20px}
    .mso-preview__meta{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
    .mso-preview__label{font-size:11px;color:#58637b;letter-spacing:.09em;font-weight:800;text-transform:uppercase}
    .mso-preview__status{font-size:12px;font-weight:800;border-radius:100px;padding:6px 10px;background:#e9f6ee;color:#165a37}
    .mso-preview__status--soldout{background:#fff0ee;color:#a42620}
    .mso-preview__city{font-size:25px;line-height:1.15;font-weight:850;margin:0 0 10px;color:var(--mso-navy)}
    .mso-preview__venue{font-size:14px;color:#3d4861;line-height:1.55;margin:0 0 16px;min-height:44px}
    .mso-preview__date{display:flex;align-items:center;gap:12px;background:#f6f7fb;border-radius:13px;padding:12px 14px;font-size:14px;font-weight:750;color:var(--mso-navy)}
    .mso-preview__time{margin-left:auto;background:#fff;border:1px solid #dee4f0;border-radius:8px;padding:6px 8px;font-variant-numeric:tabular-nums}
    .mso-preview__foot{margin-top:18px;border-top:1px solid #edf0f5;padding-top:14px;font-size:12px;color:#58637b}
    @media(max-width:600px){.mso-preview{border-radius:17px;padding:16px}.mso-preview__card-body{padding:17px}.mso-preview__city{font-size:22px}}
    </style>';
    $output .= '<section class="mso-preview" aria-label="Milano Sirki doğrulanmış seans takvimi">';
    $output .= '<div class="mso-preview__top"><div><span class="mso-preview__eyebrow">Uluslararası Milano Sirki</span><h2 class="mso-preview__heading">Şehirler ve seanslar</h2></div>';
    $output .= '<p class="mso-preview__hint">' . ( $is_admin
        ? '<strong>Yonetici test gorunumu:</strong> Salt okunur önizleme. Kapasite anlık değişebilir; bilet satışı, ödeme ve QR işlemi yapılmaz.'
        : 'Kesinleşen Milano Sirki gösterilerinin bilgilendirme takvimi. Bilet satışı henüz açık değildir.'
    ) . '</p></div>';
    $output .= '<div class="mso-preview__grid">';

    foreach ( $sessions as $session ) {
        $sold_out = $is_admin && 'sold_out' === $session['availability'];
        $city = $session['province'];
        $district = $session['district'];
        $venue = $session['venue'];
        $date = $session['date'];
        $time = $session['time'];
        $capacity = $is_admin ? $session['available_units'] : null;
        $output .= '<article class="mso-preview__card"><div class="mso-preview__bar" aria-hidden="true"></div><div class="mso-preview__card-body">';
        $output .= '<div class="mso-preview__meta"><span class="mso-preview__label">Türkiye Turnesi · Seans Önizleme</span>';
        $output .= '<span class="mso-preview__status' . ( $sold_out ? ' mso-preview__status--soldout' : '' ) . '">' . ( $sold_out ? 'Kontenjan dolu' : ( $is_admin ? 'Satış uygunluğu testte' : 'Gösteri duyurusu' ) ) . '</span></div>';
        $output .= '<h3 class="mso-preview__city">' . esc_html( $city ) . '</h3>';
        $output .= '<p class="mso-preview__venue">' . esc_html( ( $district ? $district . ' · ' : '' ) . $venue ) . '</p>';
        $output .= '<div class="mso-preview__date"><span>' . esc_html( $date ) . '</span><span class="mso-preview__time">' . esc_html( $time ) . '</span></div>';
        $output .= '<div class="mso-preview__foot">' . ( $is_admin
            ? esc_html( $sold_out ? 'Kalan birim: 0' : 'Anlık kalan kapasite birimi: ' . $capacity )
            : 'Bilet satışı henüz aktif değildir.'
        ) . '</div>';
        $output .= '</div></article>';
    }

    $output .= '</div></section>';
    return $output;
}

add_shortcode( 'milano_ortak_etkinlikler', 'mso_events_shortcode' );
