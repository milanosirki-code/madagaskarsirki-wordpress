<?php
/**
 * Public Milano adapter and common calendar normalization.
 * Milano uses this same public central catalog on milanosirki.com.
 * No credentials, order writes, checkout actions or price rendering.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function uskd_events_timezone() {
	return new DateTimeZone( 'Europe/Istanbul' );
}

/** Strictly normalize source dates; unknown or impossible dates are excluded. */
function uskd_events_date_iso( $value ) {
	$value = uskd_mdg_events_text( $value );
	$months = array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	foreach ( $months as $index => $month ) {
		$value = str_replace( $month, sprintf( '%02d', $index + 1 ), $value );
	}
	foreach ( array( 'Y-m-d', 'd.m.Y', 'j m Y', 'j n Y' ) as $format ) {
		$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, uskd_events_timezone() );
		$errors = DateTimeImmutable::getLastErrors();
		if ( $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ) {
			return $date->format( 'Y-m-d' );
		}
	}
	return '';
}

function uskd_events_date_label( $iso ) {
	$months = array( 1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $iso, uskd_events_timezone() );
	return $date ? $date->format( 'j' ) . ' ' . $months[ (int) $date->format( 'n' ) ] . ' ' . $date->format( 'Y' ) : '';
}

/** Retain only calendar fields, grouping sessions by source event/date/venue. */
function uskd_events_milano_parse( $payload ) {
	if ( ! is_array( $payload ) || 'milano' !== ( $payload['brand'] ?? '' ) || ! isset( $payload['sessions'] ) || ! is_array( $payload['sessions'] ) ) {
		return new WP_Error( 'uskd_milano_invalid', 'Milano etkinlik kaynağı okunamadı.' );
	}
	$events = array();
	foreach ( $payload['sessions'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['preview'] ) || false !== $row['preview'] ) {
			continue;
		}
		foreach ( array( 'title', 'venue', 'date', 'time' ) as $field ) {
			if ( ! isset( $row[ $field ] ) || ! is_string( $row[ $field ] ) ) {
				continue 2;
			}
		}
		if ( ! preg_match( '/^Milano Sirki\s*[—–-]\s*(.+)$/u', $row['title'], $match ) ) {
			continue;
		}
		$city = uskd_mdg_events_text( $match[1] );
		$venue = uskd_mdg_events_text( $row['venue'] );
		$date = uskd_events_date_iso( $row['date'] );
		if ( '' === $city || '' === $venue || '' === $date || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $row['time'] ) ) {
			continue;
		}
		$key = (string) absint( $row['event_id'] ?? 0 ) . '|' . $date . '|' . $city . '|' . $venue;
		if ( ! isset( $events[ $key ] ) ) {
			$events[ $key ] = array( 'brand' => 'Milano Sirki', 'city' => $city, 'date' => $date, 'venue' => $venue, 'sessions' => array() );
		}
		$events[ $key ]['sessions'][] = $row['time'];
	}
	if ( ! empty( $payload['sessions'] ) && empty( $events ) ) {
		return new WP_Error( 'uskd_milano_incomplete', 'Milano kaynağında geçerli etkinlik bulunamadı.' );
	}
	foreach ( $events as &$event ) {
		$event['sessions'] = array_values( array_unique( $event['sessions'] ) );
	}
	unset( $event );
	return array_values( $events );
}

function uskd_events_milano_source() {
	$key = 'uskd_milano_calendar_v030';
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$response = wp_safe_remote_get(
		'https://madagaskarsirki.com/wp-json/milano-handoff/v1/catalog',
		array( 'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 524288, 'headers' => array( 'Accept' => 'application/json' ) )
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'uskd_milano_unavailable', 'Milano programı şu anda yüklenemedi.' );
	}
	$events = uskd_events_milano_parse( json_decode( wp_remote_retrieve_body( $response ), true ) );
	if ( ! is_wp_error( $events ) ) {
		// Never cache the upstream price/capacity payload.
		set_transient( $key, $events, USKD_MDG_EVENTS_CACHE_TTL );
	}
	return $events;
}

/** Merge brands, remove past sessions and duplicates, sort before applying limits. */
function uskd_events_merge( $sources, $now = null ) {
	$now = $now instanceof DateTimeImmutable ? $now->setTimezone( uskd_events_timezone() ) : new DateTimeImmutable( 'now', uskd_events_timezone() );
	$merged = array();
	foreach ( $sources as $brand => $rows ) {
		if ( is_wp_error( $rows ) ) {
			continue;
		}
		foreach ( $rows as $row ) {
			$date = uskd_events_date_iso( $row['date'] ?? '' );
			if ( '' === $date || $date < $now->format( 'Y-m-d' ) ) {
				continue;
			}
			$times = array();
			foreach ( (array) ( $row['sessions'] ?? array() ) as $time ) {
				if ( ! is_string( $time ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) {
					continue;
				}
				if ( $date === $now->format( 'Y-m-d' ) && $time <= $now->format( 'H:i' ) ) {
					continue;
				}
				$times[] = $time;
			}
			if ( empty( $times ) || empty( $row['city'] ) || empty( $row['venue'] ) ) {
				continue;
			}
			$key = $brand . '|' . $date . '|' . $row['city'] . '|' . $row['venue'];
			if ( ! isset( $merged[ $key ] ) ) {
				$merged[ $key ] = array( 'brand' => $brand, 'city' => $row['city'], 'date' => uskd_events_date_label( $date ), 'date_iso' => $date, 'venue' => $row['venue'], 'sessions' => array() );
			}
			$merged[ $key ]['sessions'] = array_values( array_unique( array_merge( $merged[ $key ]['sessions'], $times ) ) );
			sort( $merged[ $key ]['sessions'], SORT_STRING );
		}
	}
	$merged = array_values( $merged );
	usort( $merged, function ( $a, $b ) {
		return array( $a['date_iso'], $a['sessions'][0], $a['brand'], $a['city'], $a['venue'] ) <=> array( $b['date_iso'], $b['sessions'][0], $b['brand'], $b['city'], $b['venue'] );
	} );
	return $merged;
}

/** A failure in either source never suppresses the other source. */
function uskd_events_collect() {
	$html = uskd_mdg_events_source_html();
	$madagaskar = is_wp_error( $html ) ? $html : uskd_mdg_events_parse( $html );
	$milano = uskd_events_milano_source();
	$sources = array( 'Madagaskar Sirki' => $madagaskar, 'Milano Sirki' => $milano );
	$failed = array();
	foreach ( $sources as $brand => $rows ) {
		if ( is_wp_error( $rows ) ) {
			$failed[] = $brand;
		}
	}
	return array( 'events' => uskd_events_merge( $sources ), 'failed' => $failed );
}
