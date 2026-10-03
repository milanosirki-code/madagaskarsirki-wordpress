<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Public_Event {
    public static function hooks() {
        add_action( 'init', array( __CLASS__, 'register_rewrite' ), 5 );
        add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 99 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render_request' ), 0 );
    }

    public static function register_rewrite() {
        add_rewrite_rule( '^etkinlik/([^/]+)/?$', 'index.php?mdg_event_slug=$matches[1]', 'top' );
    }

    public static function maybe_flush_rewrite() {
        if ( (string) get_option( 'mdg_rewrite_version', '' ) !== (string) MDG_BILET_VERSION ) {
            flush_rewrite_rules( false );
            update_option( 'mdg_rewrite_version', MDG_BILET_VERSION, false );
        }
    }

    public static function query_vars( $vars ) {
        $vars[] = 'mdg_event_slug';
        return $vars;
    }

    public static function preview_url( $event ) {
        if ( ! $event || empty( $event->id ) || empty( $event->public_uuid ) ) { return ''; }
        return add_query_arg( array(
            'mdg_event_preview' => rawurlencode( (string) $event->public_uuid ),
            '_wpnonce'          => wp_create_nonce( 'mdg_event_preview_' . absint( $event->id ) ),
        ), home_url( '/' ) );
    }

    public static function live_url( $event ) {
        if ( ! $event || empty( $event->public_slug ) ) { return ''; }
        return home_url( '/etkinlik/' . rawurlencode( (string) $event->public_slug ) . '/' );
    }

    public static function maybe_render_request() {
        if ( ! empty( $_GET['mdg_event_preview'] ) ) {
            $uuid  = sanitize_text_field( wp_unslash( $_GET['mdg_event_preview'] ) );
            $event = MDG_Events::get_by_uuid( $uuid );
            if ( ! $event || MDG_Status::DRAFT !== (string) $event->status ) { self::not_found(); }
            if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) { self::not_found(); }
            $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
            if ( ! wp_verify_nonce( $nonce, 'mdg_event_preview_' . absint( $event->id ) ) ) { self::not_found(); }
            nocache_headers();
            header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
            self::render( $event, true );
            exit;
        }

        $slug = sanitize_title( (string) get_query_var( 'mdg_event_slug' ) );
        if ( ! $slug ) { return; }
        $event = MDG_Events::get_by_public_slug( $slug );
        if ( ! $event || MDG_Status::ONSALE !== (string) $event->status ) { self::not_found_public(); }
        status_header( 200 );
        nocache_headers(); // Live cart nonce and session availability must not be served stale.
        self::render( $event, false );
        exit;
    }

    private static function not_found() {
        status_header( 404 );
        nocache_headers();
        exit( 'Etkinlik önizlemesi bulunamadı.' );
    }

    private static function not_found_public() {
        status_header( 404 );
        nocache_headers();
        exit( 'Etkinlik bulunamadı veya satışa kapalı.' );
    }

    private static function render( $event, $is_preview ) {
        $sessions = MDG_Sessions::by_event( $event->id );
        $tickets  = MDG_Sessions::ticket_catalogue_for_event( $event->id );
        $gallery  = MDG_Events::gallery_ids( $event );
        $faq      = MDG_Events::faq_items( $event );
        $hero_id  = absint( $event->hero_attachment_id );
        $hero_url = $hero_id ? wp_get_attachment_image_url( $hero_id, 'full' ) : '';
        $hero_warning = false;
        if ( $hero_id ) {
            $hero_meta = wp_get_attachment_metadata( $hero_id );
            if ( ! empty( $hero_meta['width'] ) && ! empty( $hero_meta['height'] ) ) {
                $hero_ratio = (float) $hero_meta['width'] / max( 1, (float) $hero_meta['height'] );
                $hero_warning = $hero_ratio < 1.45;
            }
        }

        $session_data = array();
        foreach ( $sessions as $session ) {
            list( $date, $time ) = MDG_Sessions::local_parts( $session->start_at );
            $session_data[] = array(
                'id'       => (int) $session->id,
                'date'     => $date,
                'time'     => $time,
                'capacity' => (int) $session->capacity_total,
                'start_at' => (string) $session->start_at,
                'end_at'   => (string) $session->end_at,
            );
        }
        $first = $session_data ? $session_data[0] : null;
        $date_label = $first ? wp_date( 'd F Y, l', strtotime( $first['date'] . ' 12:00:00' ) ) : 'Tarih hazırlanıyor';

        $ticket_data = array();
        $min_price = null;
        $max_price = null;
        foreach ( $tickets as $ticket ) {
            if ( isset( $ticket->is_active ) && ! (int) $ticket->is_active ) { continue; }
            $price = (float) $ticket->price;
            if ( null === $min_price || $price < $min_price ) { $min_price = $price; }
            if ( null === $max_price || $price > $max_price ) { $max_price = $price; }
            $ticket_data[] = array(
                'code' => (string) $ticket->code,
                'label' => (string) $ticket->label,
                'price' => $price,
                'units' => max( 1, (int) $ticket->capacity_units ),
            );
        }

        $title = $event->seo_title ? (string) $event->seo_title : (string) $event->title;
        $description = $event->seo_description ? (string) $event->seo_description : (string) $event->short_description;
        $schema = self::schema( $event, $session_data, $hero_url );
        $public_url = $is_preview ? '' : self::live_url( $event );
        if ( $public_url ) {
            $schema['url'] = $public_url;
            if ( null !== $min_price ) {
                $schema['offers'] = array(
                    '@type'         => 'AggregateOffer',
                    'url'           => $public_url . '#bilet-secimi',
                    'priceCurrency' => 'TRY',
                    'lowPrice'      => (float) $min_price,
                    'highPrice'     => (float) ( null !== $max_price ? $max_price : $min_price ),
                    'offerCount'    => count( $ticket_data ),
                    'availability'  => 'https://schema.org/InStock',
                );
            }
        }
        $organizer_display = self::organizer_display_name( $event->organizer_name );
        $first_session_summary = $first ? wp_date( 'd F', strtotime( $first['date'] . ' 12:00:00' ) ) . ' · ' . $first['time'] : 'Seans seçimi';
        $mapping_ready = self::sales_mapping_complete( (int) $event->id );
        $sales_enabled = $mapping_ready && ( ( $is_preview && current_user_can( 'manage_woocommerce' ) ) || ( ! $is_preview && MDG_Status::ONSALE === (string) $event->status ) );
        if ( $sales_enabled ) {
            $cart_test_config = array(
                'enabled'  => true,
                'mode'     => $is_preview ? 'preview' : 'live',
                'action'   => $is_preview ? 'mdg_preview_add_to_cart' : 'mdg_live_add_to_cart',
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( ( $is_preview ? 'mdg_preview_cart_' : 'mdg_live_cart_' ) . (int) $event->id ),
                'eventId'  => (int) $event->id,
                'cartUrl'  => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
            );
        } else {
            $cart_test_config = array( 'enabled' => false, 'mode' => $is_preview ? 'preview' : 'live' );
        }
        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( $is_preview ) : ?><meta name="robots" content="noindex,nofollow,noarchive"><?php else : ?><meta name="robots" content="index,follow,max-image-preview:large"><?php endif; ?>
<title><?php echo esc_html( $title ); ?></title>
<?php if ( $description ) : ?><meta name="description" content="<?php echo esc_attr( $description ); ?>"><?php endif; ?>
<?php if ( $hero_url ) : ?><meta property="og:image" content="<?php echo esc_url( $hero_url ); ?>"><?php endif; ?>
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<?php if ( $public_url ) : ?><meta property="og:url" content="<?php echo esc_url( $public_url ); ?>"><link rel="canonical" href="<?php echo esc_url( $public_url ); ?>"><?php endif; ?>
<?php if ( $description ) : ?><meta property="og:description" content="<?php echo esc_attr( $description ); ?>"><?php endif; ?>
<link rel="stylesheet" href="<?php echo esc_url( MDG_BILET_URL . 'assets/public-event.css?ver=' . rawurlencode( MDG_BILET_VERSION ) ); ?>">
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php wp_head(); ?>
</head>
<body class="mdg-public-event-page">
<?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>
<?php if ( $is_preview ) : ?><div class="mdg-preview-bar"><strong>TASLAK ÖNİZLEME</strong><span>Bu sayfa yalnızca yetkili kullanıcıya açıktır; satışa ve arama motorlarına açık değildir.</span><a href="<?php echo esc_url( add_query_arg( array( 'page'=>'mdg-publish', 'edit'=>$event->id ), admin_url( 'admin.php' ) ) ); ?>">Yönetimde Düzenle</a></div><?php endif; ?>
<?php if ( $is_preview && $hero_warning ) : ?><div class="mdg-preview-hint">Kapak görseli kare/dikey görünüyor. Satış sayfası için 16:9 veya en az 1.45:1 oranında güçlü bir sahne/afiş görseli kullanmanız önerilir.</div><?php endif; ?>

<main class="mdg-event-shell">
    <section class="mdg-event-hero">
        <div class="mdg-event-hero-media">
            <?php if ( $hero_id ) : ?>
                <?php echo wp_get_attachment_image( $hero_id, 'large', false, array( 'class'=>'mdg-hero-image', 'loading'=>'eager', 'fetchpriority'=>'high', 'alt'=>(string)$event->title ) ); ?>
            <?php else : ?>
                <div class="mdg-hero-placeholder"><span>🎪</span><strong>Kapak görseli hazırlanıyor</strong></div>
            <?php endif; ?>
        </div>
        <div class="mdg-event-hero-copy">
            <div class="mdg-kicker"><?php echo esc_html( $event->province_name . ' · ' . $event->district ); ?></div>
            <h1><?php echo esc_html( $event->title ); ?></h1>
            <?php if ( $event->short_description ) : ?><p class="mdg-event-lead"><?php echo esc_html( $event->short_description ); ?></p><?php endif; ?>
            <div class="mdg-hero-facts">
                <div><span>Tarih</span><strong><?php echo esc_html( $date_label ); ?></strong></div>
                <div><span>Salon</span><strong><?php echo esc_html( $event->venue_name ); ?></strong></div>
                <?php if ( null !== $min_price ) : ?><div><span>Fiyat</span><strong><?php echo esc_html( self::money( $min_price ) ); ?>'den başlayan</strong></div><?php endif; ?>
            </div>
            <a class="mdg-primary-cta" href="#bilet-secimi">Bilet Seçimine Git</a>
            <div class="mdg-trust-line"><span><?php echo $sales_enabled ? '✓ Güvenli ödeme' : '✓ Güvenli ödeme hazırlanıyor'; ?></span><span>✓ QR bilet</span><span>✓ Mobil bilet</span></div>
        </div>
    </section>

    <section class="mdg-ticket-card" id="bilet-secimi">
        <div class="mdg-section-heading"><div><span class="mdg-eyebrow">BİLET SEÇİMİ</span><h2>Seansınızı ve biletlerinizi seçin</h2></div><span class="mdg-preview-chip"><?php echo $sales_enabled ? ( $is_preview ? 'Güvenli ödeme' : 'Satışta' ) : 'Bilet önizleme'; ?></span></div>
        <?php if ( $session_data ) : ?>
            <div class="mdg-session-picker" data-mdg-session-picker>
                <?php foreach ( $session_data as $i => $s ) : ?>
                    <button type="button" class="mdg-session-option<?php echo 0 === $i ? ' is-selected' : ''; ?>" data-session-id="<?php echo esc_attr( $s['id'] ); ?>" data-session-time="<?php echo esc_attr( $s['time'] ); ?>" data-session-date="<?php echo esc_attr( wp_date( 'd F', strtotime( $s['date'] . ' 12:00:00' ) ) ); ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>">
                        <span><?php echo esc_html( wp_date( 'd M', strtotime( $s['date'] . ' 12:00:00' ) ) ); ?></span>
                        <strong><?php echo esc_html( $s['time'] ); ?></strong>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else : ?><p>Henüz seans tanımlanmadı.</p><?php endif; ?>

        <div class="mdg-ticket-options" data-mdg-ticket-options>
            <?php foreach ( $ticket_data as $ticket ) : ?>
                <div class="mdg-ticket-option" data-ticket-code="<?php echo esc_attr( $ticket['code'] ); ?>" data-ticket-price="<?php echo esc_attr( (string) $ticket['price'] ); ?>" data-ticket-units="<?php echo esc_attr( (string) $ticket['units'] ); ?>">
                    <div><strong><?php echo esc_html( $ticket['label'] ); ?></strong><?php if ( $ticket['units'] > 1 ) : ?><span><?php echo esc_html( $ticket['units'] ); ?> kişilik giriş hakkı</span><?php endif; ?></div>
                    <div class="mdg-ticket-price"><?php echo esc_html( self::money( $ticket['price'] ) ); ?></div>
                    <div class="mdg-qty"><button type="button" data-minus aria-label="Azalt">−</button><input type="number" value="0" min="0" max="20" readonly autocomplete="off" inputmode="numeric"><button type="button" data-plus aria-label="Artır">+</button></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mdg-ticket-summary"><div><span>Seçilen kişi</span><strong data-mdg-people>0</strong></div><div><span>Toplam</span><strong data-mdg-total>0 ₺</strong></div><button type="button" class="mdg-checkout-preview" data-mdg-checkout-preview disabled>Devam etmek için bilet seçin</button></div>
        <p class="mdg-preview-note"><?php echo $sales_enabled ? 'Bilet seçiminiz sepete güvenli biçimde aktarılır; ödeme adımında sipariş bilgilerinizi tamamlayabilirsiniz.' : 'Bilet satışı henüz etkinleştirilmedi.'; ?></p><div class="mdg-cart-test-message" data-mdg-cart-test-message hidden></div>
    </section>

    <section class="mdg-content-grid">
        <article class="mdg-main-content">
            <?php if ( $event->long_description ) : ?><section class="mdg-content-section"><span class="mdg-eyebrow">GÖSTERİ</span><h2>Madagaskar Sirki'ni Keşfedin</h2><div class="mdg-rich-text"><?php echo wp_kses_post( wpautop( $event->long_description ) ); ?></div></section><?php endif; ?>

            <?php if ( $gallery ) : ?><section class="mdg-content-section"><span class="mdg-eyebrow">GALERİ</span><h2>Gösteriden Kareler</h2><div class="mdg-photo-grid">
                <?php foreach ( $gallery as $gid ) : $full=wp_get_attachment_image_url($gid,'full'); if(!$full)continue; ?><button type="button" class="mdg-photo" data-mdg-lightbox="<?php echo esc_url( $full ); ?>"><?php echo wp_get_attachment_image( $gid, 'medium_large', false, array( 'loading'=>'lazy', 'alt'=>'Madagaskar Sirki gösteri görseli' ) ); ?></button><?php endforeach; ?>
            </div></section><?php endif; ?>

            <?php if ( $event->video_url ) : ?><section class="mdg-content-section mdg-video-callout"><div><span class="mdg-eyebrow">VİDEO</span><h2>Gösteriyi yakından keşfedin</h2></div><a href="<?php echo esc_url( $event->video_url ); ?>" target="_blank" rel="noopener noreferrer">Tanıtım Videosunu Aç ↗</a></section><?php endif; ?>

            <?php if ( $faq ) : ?><section class="mdg-content-section"><span class="mdg-eyebrow">MERAK EDİLENLER</span><h2>Sık Sorulan Sorular</h2><div class="mdg-faq-list">
                <?php foreach ( $faq as $faq_i => $item ) : ?><details<?php echo 0 === $faq_i ? ' open' : ''; ?>><summary><?php echo esc_html( $item['question'] ); ?></summary><div><?php echo nl2br( esc_html( $item['answer'] ) ); ?></div></details><?php endforeach; ?>
            </div></section><?php endif; ?>
        </article>

        <aside class="mdg-event-aside">
            <section class="mdg-aside-card"><h3>Etkinlik Bilgileri</h3><dl>
                <?php if ( $event->show_duration ) : ?><div><dt>Süre</dt><dd><?php echo esc_html( (int)$event->show_duration . ' dakika' ); ?></dd></div><?php endif; ?>
                <?php if ( $event->doors_open_before || '0' === (string)$event->doors_open_before ) : ?><div><dt>Kapı Açılışı</dt><dd><?php echo esc_html( (int)$event->doors_open_before . ' dk önce' ); ?></dd></div><?php endif; ?>
                <div><dt>Oturma</dt><dd><?php echo esc_html( self::seating_label( $event->seating_type ) ); ?></dd></div>
                <?php if ( $event->age_info ) : ?><div><dt>Yaş</dt><dd><?php echo esc_html( $event->age_info ); ?></dd></div><?php endif; ?>
            </dl></section>

            <section class="mdg-aside-card"><h3>Salon ve Ulaşım</h3><strong><?php echo esc_html( $event->venue_name ); ?></strong><?php if($event->venue_address):?><p><?php echo esc_html( $event->venue_address ); ?></p><?php else:?><p class="mdg-muted">Açık adres henüz tamamlanmadı.</p><?php endif;?>
                <?php if ( $event->venue_maps_url ) : ?><a class="mdg-secondary-cta" href="<?php echo esc_url( $event->venue_maps_url ); ?>" target="_blank" rel="noopener noreferrer">Yol Tarifi Al ↗</a><?php else : ?><span class="mdg-map-missing">Maps bağlantısı hazırlanıyor</span><?php endif; ?>
            </section>

            <?php if ( $event->rules ) : ?><section class="mdg-aside-card"><h3>Önemli Bilgiler</h3><ul class="mdg-rule-list"><?php foreach ( self::rule_lines( $event->rules ) as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul></section><?php endif; ?>
            <?php if ( $event->organizer_name ) : ?><section class="mdg-aside-card mdg-organizer"><span>Organizatör</span><strong><?php echo esc_html( $organizer_display ); ?></strong></section><?php endif; ?>
        </aside>
    </section>
</main>

<div class="mdg-desktop-sticky" data-mdg-desktop-sticky><div><strong><?php echo esc_html( $event->title ); ?></strong><span><b data-mdg-sticky-session><?php echo esc_html( $first_session_summary ); ?></b> · <b data-mdg-sticky-price><?php echo null !== $min_price ? esc_html( self::money($min_price) . "'den başlayan" ) : 'Bilet seçimi'; ?></b></span></div><a href="#bilet-secimi">Bilet Al</a></div>
<div class="mdg-mobile-sticky"><div><span data-mdg-mobile-summary><?php echo esc_html( $first_session_summary ); ?><?php echo null !== $min_price ? esc_html( ' · ' . self::money($min_price) . "'den" ) : ''; ?></span><strong><?php echo esc_html( $event->title ); ?></strong></div><a href="#bilet-secimi">Bilet Al</a></div>
<div class="mdg-lightbox" data-mdg-lightbox-modal hidden><button type="button" data-mdg-lightbox-close aria-label="Kapat">×</button><img src="" alt="Gösteri görseli"></div>
<script>window.MDG_EVENT_SALES=<?php echo wp_json_encode( $cart_test_config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;</script>
<script src="<?php echo esc_url( MDG_BILET_URL . 'assets/public-event.js?ver=' . rawurlencode( MDG_BILET_VERSION ) ); ?>" defer></script>
<?php wp_footer(); ?>
</body></html><?php
    }

    private static function sales_mapping_complete( $event_id ) {
        if ( ! class_exists( 'WooCommerce' ) ) { return false; }
        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $sessions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$sessions_table} WHERE event_id=%d ORDER BY start_at ASC, id ASC", $event_id ) );
        if ( ! $sessions ) { return false; }
        $tickera_id = 0;
        foreach ( $sessions as $session ) {
            if ( ! (int) $session->wc_product_id || ! (int) $session->tickera_event_id ) { return false; }
            if ( ! $tickera_id ) { $tickera_id = (int) $session->tickera_event_id; }
            if ( $tickera_id !== (int) $session->tickera_event_id ) { return false; }
            $types = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$types_table} WHERE session_id=%d AND is_active=1 ORDER BY sort_order ASC, id ASC", $session->id ) );
            if ( ! $types ) { return false; }
            foreach ( $types as $type ) {
                if ( ! (int) $type->wc_variation_id ) { return false; }
            }
        }
        return true;
    }

    private static function schema( $event, $sessions, $hero_url ) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => (string) $event->title,
            'description' => wp_strip_all_tags( (string) ( $event->short_description ?: $event->long_description ) ),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => array(
                '@type'=>'Place', 'name'=>(string)$event->venue_name,
                'address'=>array('@type'=>'PostalAddress','streetAddress'=>(string)$event->venue_address,'addressLocality'=>(string)$event->district,'addressRegion'=>(string)$event->province_name,'addressCountry'=>'TR'),
            ),
            'organizer' => array('@type'=>'Organization','name'=>(string)$event->organizer_name),
        );
        if ( $hero_url ) { $schema['image'] = array( $hero_url ); }
        if ( $sessions ) {
            $first = $sessions[0];
            $schema['startDate'] = self::iso_from_utc( $first['start_at'] );
            $schema['endDate'] = self::iso_from_utc( $first['end_at'] );
            if ( count($sessions) > 1 ) {
                $schema['subEvent'] = array_map(function($s) use ($event){ return array('@type'=>'Event','name'=>(string)$event->title.' '.$s['time'],'startDate'=>self::iso_from_utc($s['start_at']),'endDate'=>self::iso_from_utc($s['end_at'])); }, $sessions);
            }
        }
        return $schema;
    }

    private static function iso_from_utc( $mysql ) {
        try {
            $dt = new DateTimeImmutable( (string)$mysql, new DateTimeZone('UTC') );
            return $dt->setTimezone( wp_timezone() )->format( DATE_ATOM );
        } catch ( Exception $e ) { return ''; }
    }

    private static function money( $amount ) {
        $amount = (float) $amount;
        $decimals = ( abs( $amount - round( $amount ) ) < 0.001 ) ? 0 : 2;
        return number_format_i18n( $amount, $decimals ) . ' ₺';
    }

    private static function seating_label( $key ) {
        if ( 'numbered' === $key ) { return 'Numaralı koltuk'; }
        if ( 'mixed' === $key ) { return 'Karma oturma'; }
        return 'Serbest oturma';
    }

    private static function organizer_display_name( $name ) {
        $name = trim( (string) $name );
        if ( ! $name ) { return ''; }
        if ( 0 === stripos( $name, 'Dünya Organizasyon' ) ) { return 'Dünya Organizasyon'; }
        return $name;
    }

    private static function rule_lines( $text ) {
        $lines = preg_split( '/\r\n|\r|\n/', (string)$text );
        $out = array();
        foreach ( $lines as $line ) {
            $line = trim( preg_replace( '/^[•\-–—\s]+/u', '', (string)$line ) );
            if ( $line ) { $out[] = $line; }
        }
        return $out;
    }
}
