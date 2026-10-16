/**
 * MDG Event Schema v1 - MDG data only, no ticket writes.
 * Shared payment infrastructure does NOT imply shared brand pages.
 * Offer/price intentionally omitted pending published/checkout reconciliation.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'wp_head', static function () {
    if ( is_admin() || is_feed() || wp_doing_ajax() ) { return; }
    $path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
    if ( ! is_string( $path ) || ! preg_match( '~^/etkinlik/([a-z0-9-]+)/?$~', $path, $matches ) ) { return; }
    $slug = sanitize_title( $matches[1] );
    if ( strpos( $slug, 'madagaskar-sirki-' ) !== 0 ) { return; }
    global $wpdb;
    $et = $wpdb->prefix . 'mdg_events';
    $st = $wpdb->prefix . 'mdg_sessions';
    $event = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, public_slug, province_name, district, venue_name, venue_address, status, short_description, long_description, organizer_name, hero_attachment_id FROM {$et} WHERE public_slug = %s LIMIT 1", $slug ) );
    if ( ! $event || ! preg_match( '/^Madagaskar\s+Sirki\b/iu', trim( (string) $event->title ) ) ) { return; }
    $ev_status = strtolower( (string) $event->status );
    if ( ! in_array( $ev_status, array( 'onsale', 'soldout', 'cancelled', 'canceled', 'postponed' ), true ) ) { return; }
    if ( ! trim( (string) $event->venue_name ) || ! trim( (string) $event->venue_address ) ) { return; }

    $sessions = $wpdb->get_results( $wpdb->prepare( "SELECT id, start_at, end_at, status FROM {$st} WHERE event_id = %d ORDER BY start_at ASC, id ASC LIMIT 24", (int) $event->id ) );
    if ( ! is_array( $sessions ) || ! $sessions ) { return; }

    $tz = wp_timezone();
    $utc = new DateTimeZone( 'UTC' );
    $url = home_url( '/etkinlik/' . $slug . '/' );
    $image = (int) $event->hero_attachment_id > 0 ? wp_get_attachment_image_url( (int) $event->hero_attachment_id, 'full' ) : false;
    $description = trim( wp_strip_all_tags( (string) ( $event->short_description ?: $event->long_description ?: 'Hayvan kullanilmayan modern aile sirki; akrobasi, denge ve sahne performanslari.' ) ) );
    $organization = trim( (string) $event->organizer_name ) ?: 'Dunya Organizasyon';
    $graph = array();

    foreach ( $sessions as $session ) {
        try {
            $start_utc = new DateTimeImmutable( (string) $session->start_at, $utc );
            if ( $start_utc->getTimestamp() < time() - DAY_IN_SECONDS ) { continue; }
            $start = $start_utc->setTimezone( $tz );
            $end = $session->end_at
                ? ( new DateTimeImmutable( (string) $session->end_at, $utc ) )->setTimezone( $tz )
                : null;
        } catch ( Throwable $error ) {
            continue;
        }

        $ss = strtolower( (string) $session->status );
        $status = 'https://schema.org/EventScheduled';
        if ( in_array( $ev_status, array( 'cancelled', 'canceled' ), true ) || in_array( $ss, array( 'cancelled', 'canceled' ), true ) ) {
            $status = 'https://schema.org/EventCancelled';
        } elseif ( 'postponed' === $ev_status || 'postponed' === $ss ) {
            $status = 'https://schema.org/EventPostponed';
        }
        if ( 'draft' === $ss ) { continue; }

        $item = array(
            '@type' => 'Event',
            '@id' => $url . '#session-' . (int) $session->id,
            'name' => 'Madagaskar Sirki - Buyulu Dunya Turu (' . $start->format( 'H:i' ) . ' seansi)',
            'url' => $url,
            'startDate' => $start->format( DateTimeInterface::ATOM ),
            'eventStatus' => $status,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => $description,
            'location' => array(
                '@type' => 'Place',
                'name' => trim( (string) $event->venue_name ),
                'address' => array(
                    '@type' => 'PostalAddress',
                    'streetAddress' => trim( (string) $event->venue_address ),
                    'addressLocality' => trim( (string) $event->district ),
                    'addressRegion' => trim( (string) $event->province_name ),
                    'addressCountry' => 'TR',
                ),
            ),
            'performer' => array( '@type' => 'PerformingGroup', 'name' => 'Madagaskar Sirki' ),
            'organizer' => array( '@type' => 'Organization', 'name' => $organization, 'url' => home_url( '/' ) ),
        );
        if ( $end && $end > $start ) { $item['endDate'] = $end->format( DateTimeInterface::ATOM ); }
        if ( $image ) { $item['image'] = array( esc_url_raw( $image ) ); }
        $graph[] = $item;
    }
    if ( ! $graph ) { return; }
    $json = wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG );
    if ( $json ) {
        echo "\n<script type=\"application/ld+json\" id=\"mdg-event-schema-v1\">" . $json . "</script>\n";
    }
}, 22 );