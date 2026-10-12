<?php
/**
 * Plugin Name: Milano Sirki - Ortak Etkinlikler (Taslak)
 * Description: Ortak Madagaskar/Milano seanslarini salt-okunur olarak yonetici onizlemesinde gosterir. Satis/odeme yapmaz.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: milano-shared-events
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * This is a deliberate pilot gate: public visitors never see the source-derived
 * event cards until a canonical, capacity-aware feed is verified.
 */
function mso_events_public_enabled() {
    return false;
}

function mso_events_source() {
    $cached = get_transient( 'mso_events_readonly_v1' );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    // Fixed public WordPress REST resource. No arbitrary URL/user input.
    $url = 'https://madagaskarsirki.com/wp-json/wp/v2/pages/1311?_fields=content';
    $response = wp_safe_remote_get( $url, array(
        'timeout'     => 8,
        'redirection' => 2,
        'headers'     => array( 'Accept' => 'application/json' ),
    ) );
    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'mso_feed_unavailable', 'Etkinlik kaynagi su anda kullanilamiyor.' );
    }
    $payload = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $payload ) || ! isset( $payload['content']['rendered'] )
        || ! is_string( $payload['content']['rendered'] ) ) {
        return new WP_Error( 'mso_feed_invalid', 'Etkinlik kaynagi beklenmedik bicimde.' );
    }

    $result = mso_events_parse_html( $payload['content']['rendered'] );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    set_transient( 'mso_events_readonly_v1', $result, 5 * MINUTE_IN_SECONDS );
    return $result;
}

function mso_events_clean( $text ) {
    $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
    return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

function mso_events_node_by_class( $xpath, $node, $name ) {
    $found = $xpath->query(
        ".//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $name . " ')]",
        $node
    );
    return $found && $found->length ? $found->item( 0 ) : null;
}

function mso_events_parse_html( $html ) {
    if ( ! class_exists( 'DOMDocument' ) ) {
        return new WP_Error( 'mso_dom_missing', 'DOMDocument kullanilamiyor.' );
    }
    $previous = libxml_use_internal_errors( true );
    $dom      = new DOMDocument();
    $loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . (string) $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();
    libxml_use_internal_errors( $previous );
    if ( ! $loaded ) {
        return new WP_Error( 'mso_parse_failed', 'Etkinlik kartlari okunamadi.' );
    }

    $xpath = new DOMXPath( $dom );
    $cards = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' msc-live-card ')]" );
    if ( ! $cards || ! $cards->length ) {
        return new WP_Error( 'mso_no_cards', 'Etkinlik karti bulunamadi.' );
    }

    $events = array();
    foreach ( $cards as $card ) {
        $city_node = $xpath->query( './/h3', $card )->item( 0 );
        $date_node = mso_events_node_by_class( $xpath, $card, 'msc-live-date' );
        $venue_node = mso_events_node_by_class( $xpath, $card, 'msc-live-venue' );
        $times = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' msc-session-row ')]//span",
            $card
        );

        $city = $city_node ? mso_events_clean( $city_node->textContent ) : '';
        $date = $date_node ? mso_events_clean( $date_node->textContent ) : '';
        $venue = $venue_node ? mso_events_clean( $venue_node->textContent ) : '';
        $sessions = array();
        if ( $times ) {
            foreach ( $times as $time ) {
                $value = mso_events_clean( $time->textContent );
                if ( '' !== $value ) {
                    $sessions[] = $value;
                }
            }
        }
        $sessions = array_values( array_unique( $sessions ) );
        if ( '' === $city || '' === $date || '' === $venue || ! $sessions ) {
            continue;
        }
        $events[] = array(
            'city'     => $city,
            'date'     => $date,
            'venue'    => $venue,
            'sessions' => $sessions,
        );
    }
    if ( ! $events ) {
        return new WP_Error( 'mso_empty_feed', 'Gecerli etkinlik karti bulunamadi.' );
    }
    return $events;
}

function mso_events_shortcode() {
    if ( ! mso_events_public_enabled() && ! current_user_can( 'manage_options' ) ) {
        return '<p class="mso-events-pending">Etkinlik takvimi hazirlaniyor. Bilet satisi henuz aktif degildir.</p>';
    }
    $events = mso_events_source();
    if ( is_wp_error( $events ) ) {
        return '<p class="mso-events-pending">Etkinlik listesi su anda yuklenemiyor.</p>';
    }

    $output  = '<div class="mso-event-preview">';
    $output .= '<p><strong>Yonetici onizlemesi:</strong> Kaynak, canli kapasite veya satis uygunlugu icin dogrulanmamistir.</p>';
    $output .= '<div class="mso-event-preview__grid">';
    foreach ( $events as $event ) {
        $output .= '<article class="mso-event-preview__card">';
        $output .= '<h3>' . esc_html( $event['city'] ) . '</h3>';
        $output .= '<p>' . esc_html( $event['date'] ) . '</p>';
        $output .= '<p>' . esc_html( $event['venue'] ) . '</p>';
        $output .= '<p>Seanslar: ' . esc_html( implode( ', ', $event['sessions'] ) ) . '</p>';
        $output .= '</article>';
    }
    $output .= '</div></div>';
    return $output;
}

add_shortcode( 'milano_ortak_etkinlikler', 'mso_events_shortcode' );
