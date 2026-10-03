<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dynamic public Cities directory.
 *
 * V3.1.2 uses a standalone public shell matching the event-page rendering strategy.
 * This is safer with block themes/page builders and leaves the existing page
 * content untouched, so rollback is simply deactivating/updating the plugin.
 */
final class MDG_Public_Cities {
    const SHORTCODE = 'mdg_sehirler';

    public static function hooks() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
        add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    /**
     * Dedicated plugin template for the real public Şehirler page.
     * We no longer depend on the theme calling the_content().
     */
    public static function template_include( $template ) {
        if ( is_admin() || ! self::is_cities_request() ) {
            return $template;
        }

        $plugin_template = MDG_BILET_DIR . 'templates/public-cities.php';
        if ( is_readable( $plugin_template ) ) {
            return $plugin_template;
        }
        return $template;
    }

    public static function enqueue() {
        if ( self::is_cities_request() ) {
            wp_enqueue_style(
                'mdg-public-cities',
                MDG_BILET_URL . 'assets/public-cities.css',
                array(),
                MDG_BILET_VERSION
            );
        }
    }

    public static function shortcode() {
        return self::render();
    }

    /**
     * Match by queried page first; URL-path fallback covers unusual themes.
     */
    public static function is_cities_request() {
        if ( is_page() ) {
            $id = get_queried_object_id();
            if ( $id ) {
                $slug  = (string) get_post_field( 'post_name', $id );
                $title = trim( (string) get_the_title( $id ) );
                $norm  = sanitize_title( $title );
                if ( in_array( $slug, array( 'sehirler', 'şehirler' ), true ) || 'sehirler' === $norm ) {
                    return true;
                }
            }
        }

        // Safe path fallback: only exact /sehirler or /şehirler request.
        $path = '';
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $raw_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
            $path = trim( rawurldecode( (string) $raw_path ), '/' );
        }
        return in_array( sanitize_title( $path ), array( 'sehirler' ), true );
    }

    /**
     * Returns one card per city. If multiple live events exist in one city,
     * nearest upcoming event is used as the primary card and event_count is kept.
     */
    public static function active_cities() {
        global $wpdb;
        $events   = MDG_DB::table( 'events' );
        $sessions = MDG_DB::table( 'sessions' );
        $now_utc  = current_time( 'mysql', true );

        // Fail closed rather than breaking the public page if a table is missing.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events ) ) !== $events ||
             $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions ) ) !== $sessions ) {
            return array();
        }

        $sql = $wpdb->prepare(
            "SELECT e.*,
                    MIN(s.start_at) AS next_session_utc,
                    MAX(s.end_at) AS last_session_utc,
                    COUNT(s.id) AS future_session_count
             FROM {$events} e
             INNER JOIN {$sessions} s ON s.event_id = e.id
             WHERE e.status = %s
               AND s.end_at >= %s
             GROUP BY e.id
             ORDER BY MIN(s.start_at) ASC, e.id ASC",
            MDG_Status::ONSALE,
            $now_utc
        );
        $rows = $wpdb->get_results( $sql );
        if ( ! $rows ) { return array(); }

        $cities = array();
        foreach ( $rows as $event ) {
            $key = (string) $event->province_code;
            if ( ! $key ) { $key = sanitize_title( (string) $event->province_name ); }
            if ( ! isset( $cities[ $key ] ) ) {
                $cities[ $key ] = array(
                    'province_code' => (string) $event->province_code,
                    'province_name' => (string) $event->province_name,
                    'event'         => $event,
                    'event_count'   => 1,
                );
            } else {
                $cities[ $key ]['event_count']++;
            }
        }
        return array_values( $cities );
    }

    public static function render() {
        $cities = self::active_cities();
        ob_start();
        ?>
        <main id="primary" class="mdg-cities-page">
        <section class="mdg-cities" aria-labelledby="mdg-cities-title">
            <div class="mdg-cities__hero">
                <p class="mdg-cities__eyebrow">MADAGASKAR SİRKİ TÜRKİYE</p>
                <h1 id="mdg-cities-title">Madagaskar Sirki Şehrinize Geliyor</h1>
                <p>Kesinleşen ve bilet satışı açık gösteriler burada otomatik olarak yayınlanır. Tarih, salon ve seans bilgilerini inceleyerek biletinizi güvenli biçimde satın alabilirsiniz.</p>
            </div>

            <div class="mdg-cities__calendar">
                <p class="mdg-cities__eyebrow">2026 TURNE TAKVİMİ</p>
                <h2>Satıştaki Şehirler</h2>
                <?php if ( ! $cities ) : ?>
                    <div class="mdg-cities__empty">
                        <strong>Yeni gösteri tarihleri hazırlanıyor.</strong>
                        <span>Satışa açılan şehirler burada otomatik olarak görünecek.</span>
                    </div>
                <?php else : ?>
                    <div class="mdg-cities__grid">
                    <?php foreach ( $cities as $city ) :
                        $event = $city['event'];
                        $parts = MDG_Sessions::local_parts( $event->next_session_utc );
                        $date  = isset( $parts[0] ) ? $parts[0] : '';
                        $date_label = '';
                        if ( $date ) {
                            $tz = wp_timezone();
                            try {
                                $dt = new DateTimeImmutable( $date . ' 12:00:00', $tz );
                                $date_label = wp_date( 'd F Y', $dt->getTimestamp(), $tz );
                            } catch ( Exception $e ) {
                                $date_label = $date;
                            }
                        }
                        $url = MDG_Public_Event::live_url( $event );
                        $tickets = MDG_Sessions::ticket_catalogue_for_event( $event->id );
                        $min_price = null;
                        foreach ( (array) $tickets as $ticket ) {
                            if ( isset( $ticket->is_active ) && ! (int) $ticket->is_active ) { continue; }
                            $p = (float) $ticket->price;
                            if ( null === $min_price || $p < $min_price ) { $min_price = $p; }
                        }
                        ?>
                        <article class="mdg-city-card">
                            <div class="mdg-city-card__badge">BİLETLER SATIŞTA</div>
                            <h3><?php echo esc_html( $city['province_name'] ); ?></h3>
                            <?php if ( $date_label ) : ?><p class="mdg-city-card__date"><?php echo esc_html( $date_label ); ?></p><?php endif; ?>
                            <p class="mdg-city-card__venue"><?php echo esc_html( $event->district . ' · ' . $event->venue_name ); ?></p>
                            <?php if ( null !== $min_price ) : ?>
                                <p class="mdg-city-card__price"><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $min_price ) : number_format_i18n( $min_price, 2 ) . ' ₺' ); ?>'den başlayan</p>
                            <?php endif; ?>
                            <?php if ( (int) $city['event_count'] > 1 ) : ?>
                                <p class="mdg-city-card__count"><?php echo esc_html( (int) $city['event_count'] ); ?> aktif etkinlik</p>
                            <?php endif; ?>
                            <a class="mdg-city-card__button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $city['province_name'] ); ?> Biletlerini İncele</a>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mdg-cities__future">
                <h2>Yeni Şehirler Yakında</h2>
                <p>Henüz satışa açılmamış şehirler bu listeye eklenmez. Yeni etkinlik canlı yayına alındığı anda ilgili şehir otomatik olarak burada görünür.</p>
            </div>
        </section>
        </main>
        <?php
        return ob_get_clean();
    }
}
