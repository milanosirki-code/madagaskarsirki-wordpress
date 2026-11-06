<?php
namespace Madagaskar\WebAssistant;

/** Validates normalized native source records. No WordPress/DB writes. */
final class Catalogue {
    const BRAND = 'Madagaskar Sirki';
    const PRODUCTION_ORIGIN = 'https://madagaskarsirki.com';
    const STAGING_ORIGIN = 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com';

    public static function offers( array $b, $now, $origin = self::PRODUCTION_ORIGIN ) {
        if ( ! in_array( $origin, array( self::PRODUCTION_ORIGIN, self::STAGING_ORIGIN ), true ) ) {
            throw new Failure( 'UNVERIFIED_ORIGIN' );
        }
        $program = $b['program'];
        $mmc = $b['mmc_event'];
        $mdg = $b['mdg_event'];
        if ( $program['brand'] !== self::BRAND ) { throw new Failure( 'WRONG_BRAND' ); }
        if ( ! in_array( $program['status'], array( 'sales_open', 'promotion', 'operations', 'show_day' ), true )
            || $mmc['status'] !== 'sales_open' || $mdg['status'] !== 'onsale' ) {
            throw new Failure( 'STATE_CONFLICT' );
        }
        if ( count( $b['program_bridges'] ) !== 1 || count( $b['event_bridges'] ) !== 1
            || $b['program_bridges'][0] !== $b['event_bridges'][0] ) {
            throw new Failure( 'AMBIGUOUS_BRIDGE' );
        }
        $bridge = $b['program_bridges'][0];
        if ( $bridge['program_id'] !== $program['id'] || $bridge['mmc_event_id'] !== $mmc['id']
            || $bridge['mdg_event_id'] !== $mdg['id'] || $mmc['program_id'] !== $program['id'] ) {
            throw new Failure( 'BRIDGE_ID_MISMATCH' );
        }
        if ( ! $b['venue_matches'] ) { throw new Failure( 'VENUE_MISMATCH' ); }
        $url = parse_url( $b['ticket_url'] );
        if ( ! $url || ( $url['scheme'] ?? '' ) !== 'https' || ( $url['host'] ?? '' ) !== parse_url( $origin, PHP_URL_HOST )
            || isset( $url['user'] ) || isset( $url['pass'] ) || isset( $url['port'] ) || isset( $url['query'] ) || isset( $url['fragment'] )
            || ! preg_match( '#\A/etkinlik/[a-z0-9-]+/\z#', $url['path'] ?? '' ) ) {
            throw new Failure( 'UNVERIFIED_URL' );
        }
        Value::window( $mdg['sale_start'], $mdg['sale_end'], $now );
        $offers = array(); $blocked = array();
        foreach ( $b['sessions'] as $s ) {
            try {
                if ( $s['event_id'] !== $mdg['id'] || $s['status'] !== 'onsale' ) { throw new Failure( 'SESSION_NOT_ONSALE' ); }
                $start = Value::timestamp( $s['start_at'] );
                if ( $start <= $now || $s['native_closed'] ) { throw new Failure( 'SESSION_CLOSED' ); }
                Value::window( $s['sale_start'], $s['sale_end'], $now );
                $local = ( new \DateTimeImmutable( '@' . $start ) )->setTimezone( new \DateTimeZone( 'Europe/Istanbul' ) );
                if ( $local->format( 'Y-m-d' ) !== $mmc['event_date'] || $mmc['event_date'] !== $program['planned_date']
                    || count( array_keys( $b['mmc_session_times'], $local->format( 'Y-m-d H:i:s' ), true ) ) !== 1 ) {
                    throw new Failure( 'SESSION_TIME_MISMATCH' );
                }
                if ( ! $s['product_ready'] || $s['tickera_event_id'] < 1 ) { throw new Failure( 'PRODUCT_NOT_READY' ); }
                $tickets = array();
                foreach ( $s['tickets'] as $t ) {
                    if ( ! $t['active'] ) { continue; }
                    $role = in_array( $t['code'], array( 'CHILD', 'COCUK' ), true ) ? 'child'
                        : ( in_array( $t['code'], array( 'ADULT', 'YETISKIN' ), true ) ? 'adult' : '' );
                    if ( ! $role || isset( $tickets[ $role ] ) || $t['capacity_units'] !== 1 ) { throw new Failure( 'UNSUPPORTED_TICKET_CATALOGUE' ); }
                    $price = Value::minor( $t['price'] );
                    if ( ! $t['variation_ready'] || $price < 1 || $price !== Value::minor( $t['wc_price'] ) ) {
                        throw new Failure( 'TICKET_PRICE_OR_MAPPING_CONFLICT' );
                    }
                    $tickets[ $role ] = array( 'code' => $t['code'], 'price_minor' => $price,
                        'variation_id' => $t['variation_id'], 'max_quantity' => $t['max_quantity'] );
                }
                if ( count( $tickets ) !== 2 ) { throw new Failure( 'MISSING_TICKET_TYPE' ); }
                $available = Value::integer( $s['available_units'], 0, PHP_INT_MAX );
                if ( ! $available ) { throw new Failure( 'SOLD_OUT' ); }
                $family = $b['family'];
                if ( $family['available'] && ( $b['family_conflict'] || $family['price_minor'] < 1
                    || $family['price_minor'] > 2 * ( $tickets['adult']['price_minor'] + $tickets['child']['price_minor'] ) ) ) {
                    $family = array( 'available' => false, 'price_minor' => null, 'reason' => 'FAMILY_CATALOGUE_CONFLICT' );
                }
                $expiry = min( $now + 60, $start );
                $family24 = $b['family24'] ?? array( 'available' => false, 'price_minor' => null );
                if ( ! empty( $family24['available'] ) && ( $b['family_conflict'] || ! is_int( $family24['price_minor'] )
                    || $family24['price_minor'] < 1 || $family24['price_minor'] > 2 * $tickets['adult']['price_minor'] + 4 * $tickets['child']['price_minor'] ) ) {
                    $family24 = array( 'available' => false, 'price_minor' => null, 'reason' => 'FAMILY24_CATALOGUE_CONFLICT' );
                }
                foreach ( array( $mdg['sale_end'], $s['sale_end'] ) as $end ) {
                    if ( $end ) { $expiry = min( $expiry, Value::timestamp( $end ) ); }
                }
                $offer = array(
                    'schema_version' => '1.0', 'brand' => self::BRAND, 'program_id' => $program['id'],
                    'mmc_event_id' => $mmc['id'], 'mdg_event_id' => $mdg['id'], 'session_id' => $s['id'],
                    'title' => $mdg['title'], 'city' => $program['city'], 'district' => $program['district'],
                    'venue' => $b['venue'], 'starts_at' => $local->format( DATE_ATOM ), 'timezone' => 'Europe/Istanbul',
                    'tickets' => $tickets, 'family' => $family, 'family24' => $family24, 'available_units' => $available,
                    'parent_max_quantity' => $s['parent_max_quantity'],
                    'ticket_url' => $b['ticket_url'], 'policy_version' => Quote::POLICY,
                );
                $offer['source_revision'] = hash( 'sha256', json_encode( $offer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
                $offer['verified_at'] = $now; $offer['expires_at'] = $expiry;
                $offers[] = $offer;
            } catch ( Failure $e ) {
                $blocked[] = array( 'session_id' => $s['id'], 'reason' => $e->getMessage() );
            }
        }
        return array( 'offers' => $offers, 'blocked' => $blocked );
    }
}

