<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dynamic public "Bilet Al" directory.
 *
 * Lists only ON_SALE MDG events that still have a future session.
 * Existing WordPress page content is not modified; the plugin takes over the
 * exact /bilet-al/ request with a dedicated public template.
 */
final class MDG_Public_Tickets {
    const SHORTCODE = 'mdg_bilet_al';

    public static function hooks() {
        add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
        add_filter( 'template_include', array( __CLASS__, 'template_include' ), 100 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function template_include( $template ) {
        if ( is_admin() || ! self::is_ticket_page_request() ) {
            return $template;
        }

        $plugin_template = MDG_BILET_DIR . 'templates/public-tickets.php';
        return is_readable( $plugin_template ) ? $plugin_template : $template;
    }

    public static function enqueue() {
        if ( self::is_ticket_page_request() ) {
            wp_enqueue_style(
                'mdg-public-tickets',
                MDG_BILET_URL . 'assets/public-tickets.css',
                array(),
                MDG_BILET_VERSION
            );
        }
    }

    public static function shortcode() {
        return self::render();
    }

    public static function is_ticket_page_request() {
        if ( is_page() ) {
            $id = get_queried_object_id();
            if ( $id ) {
                $slug  = (string) get_post_field( 'post_name', $id );
                $title = trim( (string) get_the_title( $id ) );
                $norm  = sanitize_title( $title );
                if ( in_array( $slug, array( 'bilet-al', 'biletal' ), true ) || in_array( $norm, array( 'bilet-al', 'biletal' ), true ) ) {
                    return true;
                }
            }
        }

        $path = '';
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $raw_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
            $path = trim( rawurldecode( (string) $raw_path ), '/' );
        }
        return in_array( sanitize_title( $path ), array( 'bilet-al', 'biletal' ), true );
    }

    public static function active_events() {
        global $wpdb;
        $events   = MDG_DB::table( 'events' );
        $sessions = MDG_DB::table( 'sessions' );
        $now_utc  = current_time( 'mysql', true );

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events ) ) !== $events ||
             $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions ) ) !== $sessions ) {
            return array();
        }

        return (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*,
                    MIN(s.start_at) AS next_session_utc,
                    MAX(s.end_at) AS last_session_utc,
                    COUNT(s.id) AS future_session_count
             FROM {$events} e
             INNER JOIN {$sessions} s ON s.event_id = e.id
             WHERE e.status = %s
               AND s.end_at >= %s
             GROUP BY e.id
             ORDER BY MIN(s.start_at) ASC, e.province_name ASC, e.id ASC",
            MDG_Status::ONSALE,
            $now_utc
        ) );
    }

    private static function event_session_labels( $event_id ) {
        $labels  = array();
        $now_utc = current_time( 'mysql', true );
        foreach ( (array) MDG_Sessions::by_event( $event_id ) as $session ) {
            if ( ! empty( $session->end_at ) && (string) $session->end_at < $now_utc ) {
                continue;
            }
            list( $date, $time ) = MDG_Sessions::local_parts( $session->start_at );
            $labels[] = array( 'date' => $date, 'time' => $time );
        }
        return $labels;
    }

    private static function min_price( $event_id ) {
        $min = null;
        foreach ( (array) MDG_Sessions::ticket_catalogue_for_event( $event_id ) as $ticket ) {
            if ( isset( $ticket->is_active ) && ! (int) $ticket->is_active ) { continue; }
            $price = (float) $ticket->price;
            if ( null === $min || $price < $min ) { $min = $price; }
        }
        return $min;
    }

    private static function local_date_label( $utc ) {
        if ( ! $utc ) { return ''; }
        list( $date ) = MDG_Sessions::local_parts( $utc );
        if ( ! $date ) { return ''; }
        try {
            $tz = wp_timezone();
            $dt = new DateTimeImmutable( $date . ' 12:00:00', $tz );
            return wp_date( 'd F Y, l', $dt->getTimestamp(), $tz );
        } catch ( Exception $e ) {
            return $date;
        }
    }

    public static function render() {
        $events = self::active_events();
        ob_start();
        ?>
        <main id="primary" class="mdg-tickets-page">
            <section class="mdg-tickets" aria-labelledby="mdg-tickets-title">
                <div class="mdg-tickets__hero">
                    <p class="mdg-tickets__eyebrow">MADAGASKAR SİRKİ TÜRKİYE</p>
                    <h1 id="mdg-tickets-title">Biletinizi Seçin</h1>
                    <p>Satışa açık Madagaskar Sirki gösterilerini şehir, tarih, salon ve seans bilgileriyle tek ekranda inceleyin. Bilet seçimi için ilgili etkinliğe geçin.</p>
                </div>

                <div class="mdg-tickets__body">
                    <div class="mdg-tickets__heading-row">
                        <div>
                            <p class="mdg-tickets__eyebrow">GÜNCEL GÖSTERİLER</p>
                            <h2>Satıştaki Etkinlikler</h2>
                        </div>
                        <?php if ( $events ) : ?><span class="mdg-tickets__count"><?php echo esc_html( count( $events ) ); ?> etkinlik</span><?php endif; ?>
                    </div>

                    <?php if ( ! $events ) : ?>
                        <div class="mdg-tickets__empty">
                            <strong>Şu anda satışa açık etkinlik bulunmuyor.</strong>
                            <span>Yeni şehir ve tarihler satışa açıldığında burada otomatik görünecek.</span>
                        </div>
                    <?php else : ?>
                        <div class="mdg-tickets__list">
                        <?php foreach ( $events as $event ) :
                            $url       = MDG_Public_Event::live_url( $event );
                            $sessions  = self::event_session_labels( (int) $event->id );
                            $min_price = self::min_price( (int) $event->id );
                            $date      = self::local_date_label( $event->next_session_utc );
                            $hero_id   = absint( $event->hero_attachment_id );
                            ?>
                            <article class="mdg-ticket-event-card">
                                <div class="mdg-ticket-event-card__media">
                                    <?php if ( $hero_id ) : ?>
                                        <?php echo wp_get_attachment_image( $hero_id, 'medium_large', false, array( 'loading'=>'lazy', 'alt'=>(string)$event->title ) ); ?>
                                    <?php else : ?>
                                        <div class="mdg-ticket-event-card__placeholder">🎪</div>
                                    <?php endif; ?>
                                </div>
                                <div class="mdg-ticket-event-card__content">
                                    <div class="mdg-ticket-event-card__topline">
                                        <span class="mdg-ticket-event-card__badge">SATIŞTA</span>
                                        <span><?php echo esc_html( $event->province_name . ' · ' . $event->district ); ?></span>
                                    </div>
                                    <h3><?php echo esc_html( $event->title ); ?></h3>
                                    <dl class="mdg-ticket-event-card__facts">
                                        <?php if ( $date ) : ?><div><dt>Tarih</dt><dd><?php echo esc_html( $date ); ?></dd></div><?php endif; ?>
                                        <div><dt>Salon</dt><dd><?php echo esc_html( $event->venue_name ); ?></dd></div>
                                        <?php if ( null !== $min_price ) : ?>
                                            <div><dt>Fiyat</dt><dd><?php echo wp_kses_post( function_exists( 'wc_price' ) ? wc_price( $min_price ) : number_format_i18n( $min_price, 2 ) . ' ₺' ); ?>'den başlayan</dd></div>
                                        <?php endif; ?>
                                    </dl>

                                    <?php if ( $sessions ) : ?>
                                    <div class="mdg-ticket-event-card__sessions" aria-label="Seanslar">
                                        <?php foreach ( $sessions as $session ) : ?>
                                            <span><?php echo esc_html( $session['time'] ); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>

                                    <a class="mdg-ticket-event-card__button" href="<?php echo esc_url( $url ); ?>">Bilet Seçimine Git</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mdg-tickets__footer-note">
                    <strong>Güvenli ödeme · QR bilet · Mobil bilet</strong>
                    <span>Yeni etkinlik canlı yayına alındığında bu liste otomatik güncellenir.</span>
                </div>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }
}
