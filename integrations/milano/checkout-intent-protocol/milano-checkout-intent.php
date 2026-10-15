<?php
/**
 * Milano Sirki / Dünya Organizasyon — SERVER-TO-SERVER checkout intent
 * PROTOCOL DRAFT, v1. NOT a live WordPress plugin.
 *
 * This file intentionally registers NO WordPress REST route, WooCommerce
 * hooks, PayTR callback, order creation, inventory holds or QR generation.
 *
 * A valid HMAC authenticates the Milano server's REQUEST, never a price,
 * reservation, payment, or entitlement. The central checkout coordinator
 * must independently recheck the MDG event's owner, mapped session/variation,
 * actual price, remaining stock, reservation and PayTR credentials.
 *
 * The central receiver MUST atomically persist request_id as UNIQUE and
 * return the previously linked order on retries. These functions do not
 * implement persistence, so they do NOT prevent replay on their own.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** 90-second signed request; separate 30-second clock skew allowance. */
function mcpi_ttl_seconds() { return 90; }
function mcpi_skew_seconds() { return 30; }

/** Exact schema + bounded ticket quantities. No buyer PII or quoted prices. */
function mcpi_schema_valid( $payload ) {
    if ( ! is_array( $payload ) ) { return false; }
    $keys = array( 'v', 'brand', 'event_id', 'session_id', 'request_id', 'issued_at', 'expires_at', 'items' );
    if ( array_keys( $payload ) !== $keys ) { return false; }
    if ( 1 !== $payload['v'] || 'milano' !== $payload['brand']
        || ! is_int( $payload['event_id'] ) || $payload['event_id'] < 1
        || ! is_int( $payload['session_id'] ) || $payload['session_id'] < 1
        || ! is_string( $payload['request_id'] )
        || ! preg_match( '/\A[a-f0-9]{32}\z/D', $payload['request_id'] )
        || ! is_int( $payload['issued_at'] ) || $payload['issued_at'] <= 0
        || ! is_int( $payload['expires_at'] )
        || $payload['expires_at'] - $payload['issued_at'] !== mcpi_ttl_seconds()
        || ! is_array( $payload['items'] )
        || count( $payload['items'] ) < 1 || count( $payload['items'] ) > 8 ) {
        return false;
    }
    $total_quantity = 0;
    $seen = array();
    $previous = '';
    foreach ( $payload['items'] as $item ) {
        if ( ! is_array( $item ) || array_keys( $item ) !== array( 'code', 'quantity' )
            || ! is_string( $item['code'] )
            || ! preg_match( '/\A[A-Za-z0-9_-]{1,24}\z/D', $item['code'] )
            || ! is_int( $item['quantity'] )
            || $item['quantity'] < 1 || $item['quantity'] > 8 ) {
            return false;
        }
        // Canonical order rules prevent different signatures of the same lines.
        if ( isset( $seen[$item['code']] ) || ( '' !== $previous && strcmp( $previous, $item['code'] ) >= 0 ) ) {
            return false;
        }
        $seen[$item['code']] = true;
        $previous = $item['code'];
        $total_quantity += $item['quantity'];
    }
    return $total_quantity <= 20;
}

/** Generate a canonical intent using cryptographically secure server entropy. */
function mcpi_make_intent( $event_id, $session_id, $items, $now ) {
    if ( ! is_int( $event_id ) || ! is_int( $session_id ) || ! is_int( $now ) || $now <= 0
        || ! is_array( $items ) || ! function_exists( 'random_bytes' ) ) {
        return null;
    }
    $items = array_values( $items );
    usort( $items, static function( $a, $b ) {
        if ( ! is_array($a) || ! is_array($b) || ! isset($a['code'], $b['code'])
            || ! is_string($a['code']) || ! is_string($b['code']) ) {
            return 0;
        }
        return strcmp( $a['code'], $b['code'] );
    } );
    $payload = array(
        'v'          => 1,
        'brand'      => 'milano',
        'event_id'   => $event_id,
        'session_id' => $session_id,
        'request_id' => bin2hex( random_bytes( 16 ) ),
        'issued_at'  => $now,
        'expires_at' => $now + mcpi_ttl_seconds(),
        'items'      => $items,
    );
    return mcpi_schema_valid( $payload ) ? $payload : null;
}

/**
 * Protocol HMAC. Secret must come from a secure server configuration, never
 * the site HTML, form, JS, query string, Drive, GitHub or client-supplied data.
 */
function mcpi_sign( $payload, $secret ) {
    if ( ! mcpi_schema_valid( $payload ) || ! is_string( $secret ) || strlen( $secret ) < 32 ) {
        return null;
    }
    $encoded = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $encoded ) ) { return null; }
    return hash_hmac( 'sha256', "milano.checkout-intent.v1\n" . $encoded, $secret );
}

/** Fail closed. This validates message integrity and age only, not eligibility. */
function mcpi_verify( $payload, $signature, $secret, $now ) {
    if ( ! is_int( $now ) || ! is_string( $signature ) || ! preg_match( '/\A[a-f0-9]{64}\z/D', $signature ) ) {
        return false;
    }
    $expected = mcpi_sign( $payload, $secret );
    if ( null === $expected || ! hash_equals( $expected, $signature ) ) { return false; }
    if ( $payload['issued_at'] > $now + mcpi_skew_seconds()
        || $now > $payload['expires_at'] ) {
        return false;
    }
    return true;
}
