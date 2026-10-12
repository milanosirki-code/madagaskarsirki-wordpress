<?php
/**
 * Plugin Name: Milano Sirki - Ortak Etkinlikler (Taslak)
 * Description: Madagaskar'in kanonik MDG seans beslemesini yoneticilere salt okunur gosterir. Satis, odeme veya QR uretmez.
 * Version: 0.2.1
 * Requires PHP: 7.4
 * Text Domain: milano-shared-events
 *
 * WARNING: Draft only. Do not deploy without server-side secret setup and tests.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mso_events_public_enabled() {
    // This milestone never renders inventory to public visitors.
    return false;
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

function mso_events_shortcode() {
    if ( ! mso_events_public_enabled() && ! current_user_can( 'manage_options' ) ) {
        return '<p class="mso-events-pending">Etkinlik takvimi hazirlaniyor. Bilet satisi henuz aktif degildir.</p>';
    }
    $sessions = mso_events_source();
    if ( is_wp_error( $sessions ) ) {
        return '<p class="mso-events-pending">Etkinlik verisi henuz dogrulanamiyor. Bilet satisi kapali.</p>';
    }
    if ( ! $sessions ) {
        return '<p class="mso-events-pending">Satisa uygun gelecek seans bulunamadi.</p>';
    }

    $output = '<div class="mso-event-preview">';
    $output .= '<p><strong>Yonetici test gorunumu:</strong> Salt okunur kapasite anlik degisebilir. Buradan bilet satin alinamaz.</p>';
    foreach ( $sessions as $session ) {
        $output .= '<article class="mso-event-preview__card">';
        $output .= '<h3>' . esc_html( $session['province'] ) . '</h3>';
        $output .= '<p>' . esc_html( $session['district'] . ' · ' . $session['venue'] ) . '</p>';
        $output .= '<p>' . esc_html( $session['date'] . ' ' . $session['time'] ) . '</p>';
        $output .= '<p>' . esc_html( $session['availability'] === 'sold_out' ? 'Kontenjan dolu' : 'Kalan birim: ' . $session['available_units'] ) . '</p>';
        $output .= '</article>';
    }
    $output .= '</div>';
    return $output;
}

add_shortcode( 'milano_ortak_etkinlikler', 'mso_events_shortcode' );
