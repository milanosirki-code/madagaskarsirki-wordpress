<?php
/**
 * Plugin Name: USKD Madagaskar Etkinlik Akışı
 * Description: Madagaskar Sirki'nin yayındaki şehirler sayfasından şehir, tarih, salon ve seans bilgilerini güvenli biçimde gösterir. Fiyat bilgisi göstermez.
 * Version: 0.2.0
 * Author: USKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'USKD_MDG_EVENTS_VERSION', '0.2.0' );
define( 'USKD_MDG_EVENTS_SOURCE', 'https://madagaskarsirki.com/wp-json/wp/v2/pages/1311?_fields=content' );
define( 'USKD_MDG_EVENTS_CACHE_KEY', 'uskd_mdg_events_source_v1' );
define( 'USKD_MDG_EVENTS_CACHE_TTL', 5 * MINUTE_IN_SECONDS );

add_shortcode( 'uskd_madagaskar_events', 'uskd_mdg_events_shortcode' );

/**
 * Fetch the public Madagaskar cities page through WordPress REST.
 *
 * @return string|WP_Error
 */
function uskd_mdg_events_source_html() {
	$cached = get_transient( USKD_MDG_EVENTS_CACHE_KEY );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$source_url = apply_filters( 'uskd_mdg_events_source_url', USKD_MDG_EVENTS_SOURCE );

	$response = wp_safe_remote_get(
		$source_url,
		array(
			'timeout'     => 8,
			'redirection' => 3,
			'headers'     => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'USKD-Madagaskar-Events/' . USKD_MDG_EVENTS_VERSION . '; ' . home_url( '/' ),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new WP_Error(
			'uskd_mdg_http_error',
			sprintf( 'Madagaskar etkinlik kaynağı HTTP %d döndürdü.', $status )
		);
	}

	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['content']['rendered'] ) ) {
		return new WP_Error( 'uskd_mdg_invalid_source', 'Madagaskar etkinlik kaynağı beklenen içeriği döndürmedi.' );
	}

	$html = (string) $data['content']['rendered'];
	set_transient( USKD_MDG_EVENTS_CACHE_KEY, $html, USKD_MDG_EVENTS_CACHE_TTL );

	return $html;
}

/**
 * Parse public event cards from the Madagaskar cities page.
 *
 * @param string $html Source HTML.
 * @return array|WP_Error
 */
function uskd_mdg_events_parse( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return new WP_Error( 'uskd_mdg_dom_missing', 'Sunucuda DOMDocument desteği bulunamadı.' );
	}

	$previous = libxml_use_internal_errors( true );
	$doc      = new DOMDocument();

	$loaded = $doc->loadHTML(
		'<?xml encoding="utf-8" ?>' . (string) $html,
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return new WP_Error( 'uskd_mdg_parse_failed', 'Etkinlik kaynağı ayrıştırılamadı.' );
	}

	$xpath = new DOMXPath( $doc );
	$cards = $xpath->query(
		"//*[contains(concat(' ', normalize-space(@class), ' '), ' msc-live-card ')]"
	);

	if ( ! $cards || 0 === $cards->length ) {
		return new WP_Error( 'uskd_mdg_no_events', 'Yayındaki etkinlik kartı bulunamadı.' );
	}

	$events = array();

	foreach ( $cards as $card ) {
		$city_node    = $xpath->query( './/h3', $card )->item( 0 );
		$date_node    = uskd_mdg_events_xpath_class_first( $xpath, $card, 'msc-live-date' );
		$venue_node   = uskd_mdg_events_xpath_class_first( $xpath, $card, 'msc-live-venue' );
		$session_rows = $xpath->query(
			".//*[contains(concat(' ', normalize-space(@class), ' '), ' msc-session-row ')]//span",
			$card
		);

		$city  = $city_node ? uskd_mdg_events_text( $city_node->textContent ) : '';
		$date  = $date_node ? uskd_mdg_events_text( $date_node->textContent ) : '';
		$venue = $venue_node ? uskd_mdg_events_text( $venue_node->textContent ) : '';

		$sessions = array();
		if ( $session_rows ) {
			foreach ( $session_rows as $session_node ) {
				$session = uskd_mdg_events_text( $session_node->textContent );
				if ( '' !== $session ) {
					$sessions[] = $session;
				}
			}
		}

		$sessions = array_values( array_unique( $sessions ) );

		if ( '' === $city || '' === $date || '' === $venue || empty( $sessions ) ) {
			continue;
		}

		$events[] = array(
			'city'     => $city,
			'date'     => $date,
			'venue'    => $venue,
			'sessions' => $sessions,
		);
	}

	if ( empty( $events ) ) {
		return new WP_Error( 'uskd_mdg_incomplete_events', 'Etkinlik kartlarında gerekli şehir, tarih, salon veya seans alanları bulunamadı.' );
	}

	return $events;
}

/**
 * Find the first descendant with a class name.
 *
 * @param DOMXPath $xpath XPath instance.
 * @param DOMNode  $context Context node.
 * @param string   $class_name Class to find.
 * @return DOMNode|null
 */
function uskd_mdg_events_xpath_class_first( DOMXPath $xpath, DOMNode $context, $class_name ) {
	$nodes = $xpath->query(
		".//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $class_name . " ')]",
		$context
	);

	return $nodes && $nodes->length ? $nodes->item( 0 ) : null;
}

/**
 * Normalize visible text.
 *
 * @param string $value Raw text.
 * @return string
 */
function uskd_mdg_events_text( $value ) {
	$value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
	$value = preg_replace( '/\s+/u', ' ', $value );
	return trim( (string) $value );
}

/**
 * Render shortcode output.
 *
 * @return string
 */
function uskd_mdg_events_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'limit' => 0,
		),
		(array) $atts,
		'uskd_madagaskar_events'
	);

	$limit = absint( $atts['limit'] );
	$html = uskd_mdg_events_source_html();

	if ( is_wp_error( $html ) ) {
		return uskd_mdg_events_fallback();
	}

	$events = uskd_mdg_events_parse( $html );
	if ( is_wp_error( $events ) ) {
		return uskd_mdg_events_fallback();
	}

	if ( $limit > 0 ) {
		$events = array_slice( $events, 0, $limit );
	}

	ob_start();
	?>
	<div class="uskd-mdg-events">
		<style>
			.uskd-mdg-events{font-family:Arial,Helvetica,sans-serif;color:#1f1f1f}
			.uskd-mdg-events *{box-sizing:border-box}
			.uskd-mdg-events__note{margin:0 0 20px;color:#6b6b6b;font-size:15px}
			.uskd-mdg-events__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
			.uskd-mdg-events__card{background:#fff;border:1px solid #e2e2e2;border-radius:16px;padding:22px;box-shadow:0 7px 22px rgba(0,0,0,.045)}
			.uskd-mdg-events__city{color:#8b0e1a;font-size:13px;font-weight:900;letter-spacing:.7px;text-transform:uppercase;margin-bottom:14px}
			.uskd-mdg-events__date{font-size:30px;line-height:1.08;font-weight:900;margin-bottom:14px}
			.uskd-mdg-events__venue{font-size:15px;line-height:1.45;color:#5f5f5f;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid #ececec}
			.uskd-mdg-events__label{display:block;font-size:10px;color:#747474;font-weight:900;letter-spacing:.8px;text-transform:uppercase;margin-bottom:8px}
			.uskd-mdg-events__times{display:flex;flex-wrap:wrap;gap:8px}
			.uskd-mdg-events__time{display:inline-flex;align-items:center;justify-content:center;min-width:74px;padding:9px 12px;background:#f6e9eb;color:#8b0e1a;font-size:14px;font-weight:900;border-radius:9px}
			@media(max-width:900px){.uskd-mdg-events__grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
			@media(max-width:640px){.uskd-mdg-events__grid{grid-template-columns:1fr;gap:13px}.uskd-mdg-events__card{padding:18px}.uskd-mdg-events__date{font-size:27px}}
		</style>
		<p class="uskd-mdg-events__note">Program Madagaskar Sirki’nin güncel şehirler sayfasından otomatik alınır.</p>
		<div class="uskd-mdg-events__grid">
			<?php foreach ( $events as $event ) : ?>
				<article class="uskd-mdg-events__card">
					<div class="uskd-mdg-events__city"><?php echo esc_html( $event['city'] ); ?></div>
					<div class="uskd-mdg-events__date"><?php echo esc_html( $event['date'] ); ?></div>
					<div class="uskd-mdg-events__venue"><?php echo esc_html( $event['venue'] ); ?></div>
					<span class="uskd-mdg-events__label">Seanslar</span>
					<div class="uskd-mdg-events__times">
						<?php foreach ( $event['sessions'] as $session ) : ?>
							<span class="uskd-mdg-events__time"><?php echo esc_html( $session ); ?></span>
						<?php endforeach; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Render a safe fallback without exposing price data.
 *
 * @return string
 */
function uskd_mdg_events_fallback() {
	return '<div class="uskd-mdg-events-fallback">Güncel etkinlik listesi şu anda yüklenemedi. <a href="' .
		esc_url( 'https://madagaskarsirki.com/sehirler/' ) .
		'" target="_blank" rel="noopener">Madagaskar Sirki Şehirler</a> sayfasını açabilirsiniz.</div>';
}
