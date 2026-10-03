<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.7: Salon Maps -> otomatik QR -> Tickera bilet elementi köprüsü.
 *
 * Bu sınıf Tickera çekirdeğini değiştirmez. Salon kaydındaki doğrulanmış Maps
 * URL'si için MDG_QR tarafından Media Library'ye yazılan PNG'yi, bilet örneğinin
 * bağlı olduğu MDG etkinliğinden çözümler ve Tickera Ticket Designer'a özel bir
 * "Madagaskar Salon Konumu QR" elementi olarak sunar.
 */
final class MDG_Ticket_Venue_QR {

    private static $element_registered = false;
    const ELEMENT_NAME = 'tc_mdg_venue_qr_element';

    public static function hooks() {
        // `tc_load_ticket_template_elements` ana callback'i plugin bootstrap dosyasında
        // plugins_loaded'dan ÖNCE kaydedilir. Burada yalnızca güvenli fallback'ler var.

        // Klasik/erken yükleme için güvenli fallback.
        self::register_tickera_element();
        add_action( 'init', array( __CLASS__, 'register_tickera_element' ), 2 );
        add_action( 'mdg_venue_saved', array( __CLASS__, 'sync_draft_event_snapshots' ), 10, 2 );
    }

    public static function load_tickera_template_element( $value = null ) {
        if ( ! function_exists( '\\Tickera\\tickera_register_template_element' ) ) { return $value; }
        if ( ! class_exists( '\\Tickera\\TC_Ticket_Template_Elements' ) ) { return $value; }

        $class = 'Tickera\\Ticket\\Element\\tc_mdg_venue_qr_element';
        if ( ! class_exists( $class, false ) ) {
            require_once MDG_BILET_DIR . 'includes/class-mdg-ticket-venue-qr-element.php';
        } else {
            // Registry AJAX isteğinde yeniden oluşturuluyorsa tekrar eklemek güvenlidir;
            // Tickera registry class adına göre çalışır.
            \Tickera\tickera_register_template_element(
                $class,
                __( 'Madagaskar Salon Konumu QR', 'madagaskar-bilet' )
            );
        }

        self::$element_registered = class_exists( $class, false );
        return $value;
    }

    public static function register_tickera_element() {
        if ( self::$element_registered ) { return; }

        // Tickera 3.6 / Bridge / Ticket Type (Custom) tarafından kullanılan
        // gerçek namespaced registry. Tanı çıktısı bunu doğruladı.
        if ( ! function_exists( '\\Tickera\\tickera_register_template_element' ) ) { return; }
        if ( ! class_exists( '\\Tickera\\TC_Ticket_Template_Elements' ) ) { return; }

        $class = 'Tickera\\Ticket\\Element\\tc_mdg_venue_qr_element';
        if ( ! class_exists( $class, false ) ) {
            require_once MDG_BILET_DIR . 'includes/class-mdg-ticket-venue-qr-element.php';
        }
        if ( ! class_exists( $class, false ) ) { return; }

        \Tickera\tickera_register_template_element(
            $class,
            __( 'Madagaskar Salon Konumu QR', 'madagaskar-bilet' )
        );

        self::$element_registered = true;
    }

    /**
     * Ticket Designer element HTML.
     */
    public static function ticket_element_html( $ticket_instance_id, $width = 145 ) {
        $resolved = self::resolve_for_ticket_instance( absint( $ticket_instance_id ) );
        if ( empty( $resolved['qr_url'] ) ) {
            // PDF üretimini asla kırma; Maps/QR eksikse boş placeholder döndür.
            return '<span></span>';
        }
        $width = min( 320, max( 80, absint( $width ) ) );
        return '<div style="text-align:center;line-height:1.15"><div style="font-size:18px;font-weight:600;color:#d99a21;margin-bottom:8px">Salon Konumu</div><img src="' . esc_url( $resolved['qr_url'] ) . '" width="' . esc_attr( $width ) . '" alt="Salon Konumu QR"></div>';
    }

    /**
     * Bir Tickera bilet instance'ını V2 etkinlik/salon/QR verisine çözümler.
     */
    public static function resolve_for_ticket_instance( $ticket_instance_id ) {
        $ticket_instance_id = absint( $ticket_instance_id );
        $out = array(
            'ticket_instance_id' => $ticket_instance_id,
            'tickera_event_id'   => 0,
            'mdg_event_id'       => 0,
            'venue_id'           => 0,
            'venue_name'         => '',
            'maps_url'           => '',
            'qr_attachment_id'   => 0,
            'qr_url'             => '',
            'source'             => '',
        );
        if ( ! $ticket_instance_id ) { return $out; }

        $tickera_event_id = self::ticket_instance_event_id( $ticket_instance_id );
        $out['tickera_event_id'] = $tickera_event_id;
        if ( ! $tickera_event_id ) { return $out; }

        global $wpdb;
        $sessions = MDG_DB::table( 'sessions' );
        $events   = MDG_DB::table( 'events' );
        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.* FROM {$events} e INNER JOIN {$sessions} s ON s.event_id=e.id WHERE s.tickera_event_id=%d ORDER BY e.id DESC LIMIT 1",
            $tickera_event_id
        ) );
        if ( ! $event ) { return $out; }

        $out['mdg_event_id'] = (int) $event->id;
        $out['venue_id']     = (int) $event->venue_id;
        $out['venue_name']   = (string) $event->venue_name;

        // Etkinlik snapshot'ı birinci tercih. Taslakta QR sonradan tamamlandıysa
        // salon master kaydına güvenli fallback yapılır.
        $maps_url = trim( (string) $event->venue_maps_url );
        $qr_id    = absint( $event->venue_qr_attachment_id );
        $source   = 'event_snapshot';

        if ( ( ! $maps_url || ! $qr_id || ! MDG_QR::attachment_url( $qr_id ) ) && $event->venue_id ) {
            $venue = MDG_Venues::get( (int) $event->venue_id );
            if ( $venue ) {
                if ( ! $maps_url && ! empty( $venue->maps_url ) ) { $maps_url = trim( (string) $venue->maps_url ); }
                $venue_qr_id = absint( $venue->location_qr_attachment_id );
                if ( $venue_qr_id && MDG_QR::attachment_url( $venue_qr_id ) ) { $qr_id = $venue_qr_id; }
                if ( ! empty( $venue->name ) ) { $out['venue_name'] = (string) $venue->name; }
                $source = 'venue_master_fallback';
            }
        }

        $out['maps_url']         = $maps_url;
        $out['qr_attachment_id'] = $qr_id;
        $out['qr_url']           = $qr_id ? MDG_QR::attachment_url( $qr_id ) : '';
        $out['source']           = $source;
        return $out;
    }

    private static function ticket_instance_event_id( $ticket_instance_id ) {
        foreach ( array( 'event_id', '_event_id', 'event_name', '_event_name' ) as $key ) {
            $value = get_post_meta( $ticket_instance_id, $key, true );
            if ( is_numeric( $value ) && absint( $value ) ) { return absint( $value ); }
        }

        // Bridge varyasyon/ticket type üzerinden yedek çözüm.
        foreach ( array( 'ticket_type_id', 'ticket_id', '_ticket_type_id' ) as $key ) {
            $type_id = absint( get_post_meta( $ticket_instance_id, $key, true ) );
            if ( ! $type_id ) { continue; }
            foreach ( array( '_event_name', 'event_name', 'event_id' ) as $event_key ) {
                $value = get_post_meta( $type_id, $event_key, true );
                if ( is_numeric( $value ) && absint( $value ) ) { return absint( $value ); }
            }
            if ( function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $type_id );
                if ( $product && method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
                    $value = get_post_meta( $product->get_parent_id(), '_event_name', true );
                    if ( is_numeric( $value ) && absint( $value ) ) { return absint( $value ); }
                }
            }
        }
        return 0;
    }

    /**
     * Salon güncellenince sadece TASLAK V2 etkinlik snapshot'larını günceller.
     * Satışa açılmış/geçmiş etkinliğin tarihsel snapshot'ına dokunmaz.
     */
    public static function sync_draft_event_snapshots( $venue_id, $venue ) {
        $venue_id = absint( $venue_id );
        if ( ! $venue_id || ! $venue ) { return; }
        global $wpdb;
        $wpdb->update(
            MDG_DB::table( 'events' ),
            array(
                'venue_name'             => (string) $venue->name,
                'venue_address'          => (string) $venue->address,
                'venue_latitude'         => null !== $venue->latitude ? $venue->latitude : null,
                'venue_longitude'        => null !== $venue->longitude ? $venue->longitude : null,
                'venue_maps_url'         => (string) $venue->maps_url,
                'venue_qr_attachment_id' => $venue->location_qr_attachment_id ? absint( $venue->location_qr_attachment_id ) : null,
                'venue_default_capacity' => (int) $venue->default_capacity,
                'venue_default_duration' => (int) $venue->default_duration,
                'updated_at'             => MDG_DB::now(),
            ),
            array( 'venue_id' => $venue_id, 'status' => 'draft' )
        );
    }

    public static function render_admin_panel() {
        $element_ready = class_exists( 'Tickera\\Ticket\\Element\\tc_mdg_venue_qr_element', false ) && function_exists( '\\Tickera\\tickera_register_template_element' );
        echo '<div class="mdg-panel"><h2>V2.7.5 Dinamik Salon Konumu QR</h2>';
        echo '<p>Salonun Maps bağlantısı değiştiğinde QR, Media Library içinde yeniden üretilir. Tickera bilet PDF zinciri için salon Maps → QR çözümü hazırdır. V2.7.5 ile bu QR artık etkinlik-bazlı klon Ticket Designer şablonuna bağlanır.</p>';
        echo '<p><strong>QR veri çözümleyici:</strong> <span class="mdg-status is-active">Hazır</span></p>';
        echo '<p class="description">Mevcut statik salon QR görselini henüz kaldırmayın. Önce aşağıdaki son biletlerde dinamik QR çözümünü doğrulayın; ardından Ticket Designer içindeki statik QR yerine bu elementi kullanacağız.</p>';

        $tickets = get_posts( array(
            'post_type'      => 'tc_tickets_instances',
            'post_status'    => 'any',
            'posts_per_page' => 5,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
        if ( ! $tickets ) {
            echo '<p>Test edilecek Tickera bilet instance kaydı bulunamadı.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Bilet</th><th>Tickera Etkinliği</th><th>V2 Etkinliği / Salon</th><th>Dinamik Maps / QR</th><th>Durum</th></tr></thead><tbody>';
        foreach ( $tickets as $ticket_id ) {
            $r = self::resolve_for_ticket_instance( $ticket_id );
            $ready = ! empty( $r['maps_url'] ) && ! empty( $r['qr_url'] );
            echo '<tr><td>#' . esc_html( $ticket_id ) . '</td><td>' . ( $r['tickera_event_id'] ? '#' . esc_html( $r['tickera_event_id'] ) : '—' ) . '</td>';
            echo '<td>' . ( $r['mdg_event_id'] ? '#' . esc_html( $r['mdg_event_id'] ) . ' · ' . esc_html( $r['venue_name'] ) : 'V2 eşleşmesi yok' ) . '</td><td>';
            if ( $r['maps_url'] ) { echo '<a href="' . esc_url( $r['maps_url'] ) . '" target="_blank" rel="noopener noreferrer">Maps aç ↗</a>'; } else { echo 'Maps yok'; }
            if ( $r['qr_url'] ) { echo '<br><img src="' . esc_url( $r['qr_url'] ) . '" alt="Dinamik Salon QR" style="width:96px;height:96px;object-fit:contain;background:#fff;padding:4px;margin-top:6px">'; }
            echo '</td><td><span class="mdg-status ' . ( $ready ? 'is-active' : 'is-warning' ) . '">' . ( $ready ? 'Hazır' : 'Maps/QR eksik' ) . '</span></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
