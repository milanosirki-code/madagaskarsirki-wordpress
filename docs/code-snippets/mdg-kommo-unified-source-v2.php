<?php
/**
 * Madagaskar Kommo Unified Source V2 — MMC Canonical
 *
 * Serves the existing hidden Kommo source URL from MMC canonical data.
 * Does NOT create/update/delete any Kommo source.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'mdg_kommo_unified_v2_today' ) ) {
    function mdg_kommo_unified_v2_today() {
        return wp_date( 'Y-m-d', time(), wp_timezone() );
    }
}

if ( ! function_exists( 'mdg_kommo_unified_v2_family_fallback' ) ) {
    function mdg_kommo_unified_v2_family_fallback( $program_id, $tickets ) {
        foreach ( (array) $tickets as $ticket ) {
            if ( 'family_2_2' === (string) ( $ticket['code'] ?? '' ) ) {
                return $tickets;
            }
        }

        global $wpdb;
        $program_id = absint( $program_id );
        if ( ! $program_id ) { return $tickets; }

        $mdg_event_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT mdg_event_id FROM {$wpdb->prefix}mmc_mdg_event_bridge WHERE program_id = %d AND is_active = 1 LIMIT 1",
            $program_id
        ) );
        if ( ! $mdg_event_id ) { return $tickets; }

        $settings = get_option( 'mdg_family_package_22_v1', array() );
        if ( ! is_array( $settings ) || empty( $settings['events'][ $mdg_event_id ] ) ) {
            return $tickets;
        }

        $price = isset( $settings['event_prices'][ $mdg_event_id ] )
            ? (float) $settings['event_prices'][ $mdg_event_id ]
            : (float) ( $settings['price'] ?? 0 );
        if ( $price <= 0 ) { return $tickets; }

        $tickets[] = array(
            'code' => 'family_2_2',
            'name' => 'Aile Paketi 2+2',
            'price' => $price,
            'capacity_units' => 4,
        );
        return $tickets;
    }
}
if ( ! function_exists( 'mdg_kommo_unified_v2_collect' ) ) {
    function mdg_kommo_unified_v2_collect() {
        if ( ! class_exists( 'MMC_Program_Service' ) || ! class_exists( 'MMC_Event_Service' ) || ! class_exists( 'MMC_Venue_Service' ) ) {
            return array();
        }

        $today = mdg_kommo_unified_v2_today();
        $rows  = array();

        foreach ( (array) MMC_Program_Service::all_programs() as $program ) {
            $program_id = absint( $program->id ?? 0 );
            if ( ! $program_id ) { continue; }

            $program_status = (string) ( $program->status ?? '' );
            if ( in_array( $program_status, array( 'cancelled', 'completed' ), true ) ) { continue; }

            $event = MMC_Event_Service::event_for_program( $program_id );
            if ( ! $event ) { continue; }

            $event_status = (string) ( $event->status ?? '' );
            if ( ! in_array( $event_status, array( 'sales_ready', 'sales_open' ), true ) ) { continue; }

            $date = (string) ( $event->event_date ?: $program->planned_date );
            if ( ! $date || $date < $today ) { continue; }

            $venue = ! empty( $event->program_venue_id )
                ? MMC_Venue_Service::get_program_venue( (int) $event->program_venue_id )
                : null;
            if ( ! $venue ) { continue; }

            $sessions = array();
            foreach ( (array) MMC_Event_Service::sessions( (int) $event->id ) as $session ) {
                if ( 'active' !== (string) ( $session->status ?? '' ) ) { continue; }
                $raw_time = (string) $session->session_time;
                if ( preg_match( '/\b([0-2]\d:[0-5]\d)(?::[0-5]\d)?\b/', $raw_time, $match ) ) {
                    $sessions[] = $match[1];
                }
            }
            $sessions = array_values( array_unique( $sessions ) );
            sort( $sessions );

            $tickets = array();
            foreach ( (array) MMC_Event_Service::ticket_types( (int) $event->id ) as $ticket ) {
                if ( empty( $ticket->is_active ) ) { continue; }
                $tickets[] = array(
                    'code'           => (string) ( $ticket->ticket_code ?? '' ),
                    'name'           => (string) ( $ticket->ticket_name ?? '' ),
                    'price'          => (float) ( $ticket->price ?? 0 ),
                    'capacity_units' => (int) ( $ticket->capacity_units ?? 1 ),
                );
            }

            $tickets = mdg_kommo_unified_v2_family_fallback( $program_id, $tickets );

            $price_parts = array();
            foreach ( $tickets as $ticket ) {
                $label = $ticket['name'] ?: $ticket['code'];
                $price_parts[] = $label . ' ' . number_format_i18n( $ticket['price'], 2 ) . ' TL';
            }

            $province = (string) ( $program->province_name ?? '' );
            $district = (string) ( $program->district_name ?? '' );
            $city_label = $province;
            if ( $district && 'merkez' !== sanitize_title( $district ) ) {
                $city_label .= ' / ' . $district;
            }

            $rows[] = array(
                'program_id'     => $program_id,
                'program_code'   => (string) ( $program->program_code ?? '' ),
                'program_status' => $program_status,
                'event_id'       => (int) $event->id,
                'event_status'   => $event_status,
                'event_title'    => (string) ( $event->event_title ?? 'Madagaskar Sirki' ),
                'date'           => $date,
                'city'           => $province,
                'district'       => $district,
                'city_label'     => $city_label,
                'venue'          => (string) ( $venue->venue_name ?? '' ),
                'address'        => trim( (string) preg_replace( '/\s+/u', ' ', (string) ( $venue->address ?? '' ) ) ),
                'maps_url'       => (string) ( $venue->maps_url ?? '' ),
                'sessions'       => $sessions,
                'tickets'        => $tickets,
                'prices'         => implode( '; ', $price_parts ),
                'ticket_url'     => home_url( '/bilet-al/' ),
            );
        }

        usort( $rows, function( $a, $b ) {
            $cmp = strcmp( (string) $a['date'], (string) $b['date'] );
            if ( 0 !== $cmp ) { return $cmp; }
            return strcmp( (string) $a['program_code'], (string) $b['program_code'] );
        } );

        return $rows;
    }
}

if ( ! function_exists( 'mdg_kommo_unified_v2_compact_text' ) ) {
    function mdg_kommo_unified_v2_compact_text( $rows ) {
        $lines = array(
            'MADAGASKAR SİRKİ — GÜNCEL AKTİF PROGRAM',
            'Kaynak: MMC canonical program/event/venue/session/ticket data + aktif aile paketi yapılandırması.',
            '0–2 yaş ücretsiz; 3–12 çocuk; 13+ yetişkin. Çocuklar yetişkin eşliğinde katılır.',
        );

        foreach ( (array) $rows as $row ) {
            $lines[] = sprintf(
                '%s | %s | %s | %s | %s | %s',
                $row['city_label'],
                wp_date( 'd.m.Y', strtotime( $row['date'] ), wp_timezone() ),
                $row['venue'],
                implode( '/', $row['sessions'] ),
                $row['prices'] ?: 'Fiyat kaydı yok',
                $row['ticket_url']
            );
        }

        return implode( "\n", $lines );
    }
}

if ( ! function_exists( 'mdg_kommo_unified_v2_locations_text' ) ) {
    function mdg_kommo_unified_v2_locations_text( $rows ) {
        $lines = array(
            'MADAGASKAR SİRKİ — GÜNCEL ETKİNLİK KONUMLARI',
            'Konum sorularında salon + açık adres + kayıtlı Google Maps bağlantısını kullan; bağlantı yoksa uydurma.',
        );

        foreach ( (array) $rows as $row ) {
            $lines[] = sprintf(
                '%s | %s | %s | %s',
                $row['city_label'],
                $row['venue'],
                $row['address'],
                $row['maps_url'] ?: 'Maps bağlantısı yok'
            );
        }

        return implode( "\n", $lines );
    }
}

if ( ! function_exists( 'mdg_kommo_unified_v2_is_endpoint' ) ) {
    function mdg_kommo_unified_v2_is_endpoint() {
        if ( ! empty( $_GET['mdg_kommo_active_events'] ) ) { return true; }
        $path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = trim( (string) wp_parse_url( $path, PHP_URL_PATH ), '/' );
        return 'kommo-ai-bilgi-merkezi' === $path;
    }
}

if ( ! function_exists( 'mdg_kommo_unified_v2_serve' ) ) {
    function mdg_kommo_unified_v2_serve() {
        if ( ! mdg_kommo_unified_v2_is_endpoint() ) { return; }

        $expected = (string) get_option( 'mdg_kommo_active_events_token', '' );
        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        if ( ! $expected || ( ! hash_equals( $expected, $token ) && ! current_user_can( 'manage_options' ) ) ) {
            status_header( 404 );
            exit;
        }

        $rows = mdg_kommo_unified_v2_collect();

        // The hidden URL has no WordPress page, so its main query can be 404.
        // Authentication has succeeded; serve the source as a successful resource.
        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow', true );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );

        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>MMC | Kommo AI Bilgi Merkezi</title><meta name="robots" content="noindex,nofollow"></head><body><main>';
        echo '<h1>Madagaskar Sirki — Güncel Bilgi Merkezi</h1>';
        echo '<p>Son güncelleme: ' . esc_html( wp_date( 'd.m.Y H:i', time(), wp_timezone() ) ) . '</p>';
        echo '<p>Bu kaynak MMC canonical verisinden canlı üretilir. Geçmiş ve iptal programlar gösterilmez.</p>';

        echo '<section><h2>Aktif programlar</h2>';
        if ( ! $rows ) {
            echo '<p>Aktif program bulunamadı.</p>';
        }
        foreach ( $rows as $row ) {
            echo '<article><h3>' . esc_html( $row['city_label'] . ' — ' . $row['event_title'] ) . '</h3><ul>';
            echo '<li>Program kodu: ' . esc_html( $row['program_code'] ) . '</li>';
            echo '<li>Tarih: ' . esc_html( wp_date( 'd.m.Y', strtotime( $row['date'] ), wp_timezone() ) ) . '</li>';
            echo '<li>Salon: ' . esc_html( $row['venue'] ) . '</li>';
            echo '<li>Adres: ' . esc_html( $row['address'] ) . '</li>';
            echo '<li>Google Maps: ' . ( $row['maps_url'] ? '<a href="' . esc_url( $row['maps_url'] ) . '">' . esc_html( $row['maps_url'] ) . '</a>' : 'Kayıtlı bağlantı yok' ) . '</li>';
            echo '<li>Seanslar: ' . esc_html( implode( ', ', $row['sessions'] ) ) . '</li>';
            echo '<li>Fiyatlar: ' . esc_html( $row['prices'] ?: 'Fiyat kaydı yok' ) . '</li>';
            echo '<li>Bilet: <a href="' . esc_url( $row['ticket_url'] ) . '">' . esc_html( $row['ticket_url'] ) . '</a></li>';
            echo '</ul></article>';
        }
        echo '</section>';

        echo '<section><h2>Kısa program kaynağı</h2><pre>' . esc_html( mdg_kommo_unified_v2_compact_text( $rows ) ) . '</pre></section>';
        echo '<section><h2>Konum kaynağı</h2><pre>' . esc_html( mdg_kommo_unified_v2_locations_text( $rows ) ) . '</pre></section>';
        echo '</main></body></html>';
        exit;
    }
}

add_action( 'template_redirect', 'mdg_kommo_unified_v2_serve', 0 );

if ( ! function_exists( 'mdg_kommo_unified_v2_preview' ) ) {
    function mdg_kommo_unified_v2_preview( $input = array() ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'Yönetici yetkisi gerekir.' );
        }
        $rows = mdg_kommo_unified_v2_collect();
        return array(
            'generated_at' => current_time( 'mysql' ),
            'count' => count( $rows ),
            'events' => $rows,
            'program_text' => mdg_kommo_unified_v2_compact_text( $rows ),
            'locations_text' => mdg_kommo_unified_v2_locations_text( $rows ),
            'existing_sources' => array(
                'url' => get_option( 'mdg_kommo_active_events_url_source', array() ),
                'program' => get_option( 'mdg_kommo_active_events_text_source', array() ),
                'locations' => get_option( 'mdg_kommo_active_events_locations_text_source', array() ),
            ),
        );
    }
}

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) { return; }
    wp_register_ability( 'madagaskar/kommo-unified-source-v2-preview', array(
        'label' => 'Kommo Unified Source V2 Önizleme',
        'description' => 'MMC canonical verisinden üretilecek birleşik Kommo program ve konum kaynağını salt-okunur döndürür.',
        'category' => 'madagaskar-kommo',
        'input_schema' => array( 'type'=>'object', 'properties'=>array() ),
        'output_schema' => array( 'type'=>'object' ),
        'execute_callback' => 'mdg_kommo_unified_v2_preview',
        'permission_callback' => function(){ return current_user_can( 'manage_options' ); },
        'meta' => array(
            'annotations' => array( 'readonly'=>true, 'destructive'=>false, 'idempotent'=>true ),
            'public' => true,
            'show_in_rest' => true,
        ),
    ) );
} );

if ( ! function_exists( 'mdg_kommo_unified_v2_cleanup_candidates' ) ) {
    function mdg_kommo_unified_v2_cleanup_candidates() {
        global $wpdb;
        $out = array();

        $rows = $wpdb->get_results(
            "SELECT kp.program_id,kp.ai_source_id,kp.ai_source_status,p.program_code,p.status AS program_status,p.planned_date
             FROM {$wpdb->prefix}mmc_kommo_profiles kp
             LEFT JOIN {$wpdb->prefix}mmc_programs p ON p.id=kp.program_id
             ORDER BY kp.program_id ASC"
        );

        foreach ( (array) $rows as $row ) {
            $program_id = absint( $row->program_id ?? 0 );
            if ( ! $program_id ) { continue; }

            $state = get_option( 'mmc_kommo_ai_text_source_' . $program_id, array() );
            if ( is_array( $state ) && ! empty( $state['legacy_url_source_id'] ) ) {
                $out[] = array(
                    'source_id' => (string) $state['legacy_url_source_id'],
                    'program_id' => $program_id,
                    'program_code' => (string) ( $row->program_code ?? '' ),
                    'kind' => 'legacy_url',
                    'level' => 'safe_candidate',
                    'reason' => 'Program text transport kullanıyor; bu ID önceki URL kaynağı olarak yalnız rollback/audit alanında tutuluyor.',
                );
            }

            if ( 'cancelled' === (string) ( $row->program_status ?? '' ) && ! empty( $row->ai_source_id ) ) {
                $out[] = array(
                    'source_id' => (string) $row->ai_source_id,
                    'program_id' => $program_id,
                    'program_code' => (string) ( $row->program_code ?? '' ),
                    'kind' => 'cancelled_program_source',
                    'level' => 'safe_candidate',
                    'reason' => 'Program iptal edildi; source başka yerel profile tarafından paylaşılmıyor.',
                );
            } elseif ( ! empty( $row->planned_date ) && (string) $row->planned_date < mdg_kommo_unified_v2_today() && ! empty( $row->ai_source_id ) ) {
                $out[] = array(
                    'source_id' => (string) $row->ai_source_id,
                    'program_id' => $program_id,
                    'program_code' => (string) ( $row->program_code ?? '' ),
                    'kind' => 'past_program_source',
                    'level' => 'review',
                    'reason' => 'Program tarihi geçmiş; program kapanış durumu doğrulanmadan source silinmemeli.',
                );
            }
        }

        $seen = array();
        $dedup = array();
        foreach ( $out as $row ) {
            $key = $row['source_id'] . '|' . $row['kind'];
            if ( isset( $seen[$key] ) ) { continue; }
            $seen[$key] = true;
            $dedup[] = $row;
        }
        return $dedup;
    }
}

if ( ! function_exists( 'mdg_kommo_unified_v2_admin_menu' ) ) {
    function mdg_kommo_unified_v2_admin_menu() {
        add_submenu_page(
            'mmc-dashboard',
            'Kommo Unified Source V2',
            'Kommo Unified V2',
            'manage_options',
            'mdg-kommo-unified-v2',
            'mdg_kommo_unified_v2_admin_page',
            60
        );
    }
}
add_action( 'admin_menu', 'mdg_kommo_unified_v2_admin_menu', 60 );

if ( ! function_exists( 'mdg_kommo_unified_v2_admin_page' ) ) {
    function mdg_kommo_unified_v2_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Yetki yok.' ); }

        $rows = mdg_kommo_unified_v2_collect();
        $program_text = mdg_kommo_unified_v2_compact_text( $rows );
        $locations_text = mdg_kommo_unified_v2_locations_text( $rows );

        $url_state = get_option( 'mdg_kommo_active_events_url_source', array() );
        $program_state = get_option( 'mdg_kommo_active_events_text_source', array() );
        $locations_state = get_option( 'mdg_kommo_active_events_locations_text_source', array() );
        $candidates = mdg_kommo_unified_v2_cleanup_candidates();

        echo '<div class="wrap"><h1>Kommo Unified Source V2 — MMC Canonical</h1>';
        echo '<p>Bu ekran dış Kommo kaynağı oluşturmaz, güncellemez veya silmez. Canlı MMC verisini ve manuel Kommo bakımında kullanılacak metinleri gösterir.</p>';

        echo '<table class="widefat striped"><thead><tr><th>Kaynak</th><th>Source ID</th><th>Durum</th></tr></thead><tbody>';
        echo '<tr><td>Unified gizli URL</td><td>' . esc_html( (string) ( $url_state['source_id'] ?? '—' ) ) . '</td><td>Mevcut sabit kaynak; canlı endpoint #110 tarafından MMC verisinden üretilir.</td></tr>';
        echo '<tr><td>Unified Program metni</td><td>' . esc_html( (string) ( $program_state['source_id'] ?? '—' ) ) . '</td><td>Kommo panelinde manuel güncelleme gerekir.</td></tr>';
        echo '<tr><td>Unified Konum metni</td><td>' . esc_html( (string) ( $locations_state['source_id'] ?? '—' ) ) . '</td><td>Kommo panelinde manuel güncelleme gerekir.</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Güncel program metni</h2>';
        echo '<p>Etkinlik: <strong>' . esc_html( (string) count( $rows ) ) . '</strong> · Karakter: <strong>' . esc_html( (string) mb_strlen( $program_text, 'UTF-8' ) ) . '</strong> / 1950</p>';
        echo '<textarea readonly rows="16" style="width:100%;font-family:monospace">' . esc_textarea( $program_text ) . '</textarea>';

        echo '<h2>Güncel konum metni</h2>';
        echo '<p>Karakter: <strong>' . esc_html( (string) mb_strlen( $locations_text, 'UTF-8' ) ) . '</strong> / 5000</p>';
        echo '<textarea readonly rows="16" style="width:100%;font-family:monospace">' . esc_textarea( $locations_text ) . '</textarea>';

        echo '<h2>Kommo panelinde manuel güncelleme</h2>';
        echo '<ol><li>Kommo → AI / Bilgi Kaynakları ekranını açın.</li>';
        echo '<li>Program source ID <strong>' . esc_html( (string) ( $program_state['source_id'] ?? '—' ) ) . '</strong> kaynağını açıp program metnini tamamen değiştirin ve kaydedin.</li>';
        echo '<li>Konum source ID <strong>' . esc_html( (string) ( $locations_state['source_id'] ?? '—' ) ) . '</strong> kaynağını açıp konum metnini tamamen değiştirin ve kaydedin.</li>';
        echo '<li>Uşak, Didim, Manisa, Ankara/Sincan, Ankara/Yenimahalle, Ankara/Mamak, Eskişehir ve İzmir sorgularını test edin.</li></ol>';

        echo '<h2>Kaynak temizlik adayları</h2>';
        echo '<p>AI v2 için belgelenmiş delete endpoint olmadığı için bu ekran silme yapmaz. Önce Kommo panelinde kaynak kimliğini doğrulayın.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Source ID</th><th>Program</th><th>Tür</th><th>Seviye</th><th>Gerekçe</th></tr></thead><tbody>';
        if ( ! $candidates ) {
            echo '<tr><td colspan="5">Aday yok.</td></tr>';
        }
        foreach ( $candidates as $candidate ) {
            echo '<tr><td>' . esc_html( $candidate['source_id'] ) . '</td><td>' . esc_html( $candidate['program_code'] . ' (#' . $candidate['program_id'] . ')' ) . '</td><td>' . esc_html( $candidate['kind'] ) . '</td><td>' . esc_html( $candidate['level'] ) . '</td><td>' . esc_html( $candidate['reason'] ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
