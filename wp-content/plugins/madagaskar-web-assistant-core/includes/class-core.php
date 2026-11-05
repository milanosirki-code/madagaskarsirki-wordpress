<?php
namespace Madagaskar\WebAssistant;

final class Failure extends \RuntimeException {}

final class Value {
    public static function integer( $value, $min, $max ) {
        if ( ! is_int( $value ) || $value < $min || $value > $max ) {
            throw new Failure( 'INVALID_INTEGER' );
        }
        return $value;
    }

    /** Source amounts are decimal strings, never rounded binary floats. */
    public static function minor( $value ) {
        if ( ! is_string( $value ) || ! preg_match( '/\A(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?\z/', $value, $m ) ) {
            throw new Failure( 'INVALID_MONEY' );
        }
        return (int) $m[1] * 100 + (int) str_pad( $m[2] ?? '', 2, '0' );
    }

    public static function timestamp( $value, $zone = 'UTC' ) {
        if ( ! is_string( $value ) ) { throw new Failure( 'INVALID_DATE' ); }
        $dt = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new \DateTimeZone( $zone ) );
        if ( ! $dt || $dt->format( 'Y-m-d H:i:s' ) !== $value || $dt->getTimestamp() <= 0 ) {
            throw new Failure( 'INVALID_DATE' );
        }
        return $dt->getTimestamp();
    }

    public static function window( $start, $end, $now ) {
        if ( $start && self::timestamp( $start ) > $now ) { throw new Failure( 'SALES_NOT_STARTED' ); }
        if ( $end && self::timestamp( $end ) <= $now ) { throw new Failure( 'SALES_CLOSED' ); }
    }
}

/** Pure calculation over a server-created offer; not a client-facing API. */
final class Quote {
    const POLICY = 'family-policy-2026-11-03';

    public static function calculate( array $offer, array $party, $now ) {
        Value::integer( $now, 1, PHP_INT_MAX );
        foreach ( $party as $key => $value ) {
            if ( ! in_array( $key, array( 'adults', 'children', 'infants' ), true ) ) {
                throw new Failure( 'UNSUPPORTED_PARTY_FIELD' );
            }
        }
        $adults = Value::integer( $party['adults'] ?? null, 1, 20 );
        $children = Value::integer( $party['children'] ?? null, 0, 20 );
        $infants = Value::integer( $party['infants'] ?? 0, 0, 20 );
        if ( $now < $offer['verified_at'] || $now >= $offer['expires_at'] ) {
            throw new Failure( 'STALE_OFFER' );
        }
        $adult = $offer['tickets']['adult'];
        $child = $offer['tickets']['child'];
        $a_price = Value::integer( $adult['price_minor'], 1, 999999999 );
        $c_price = Value::integer( $child['price_minor'], 1, 999999999 );
        $available = Value::integer( $offer['available_units'], 0, PHP_INT_MAX );
        $a_max = Value::integer( $adult['max_quantity'], 0, 20 );
        $c_max = Value::integer( $child['max_quantity'], 0, 20 );
        $parent_max = Value::integer( $offer['parent_max_quantity'], 0, 40 );
        $family = $offer['family'];
        $f_price = ! empty( $family['available'] ) ? Value::integer( $family['price_minor'], 1, 999999999 ) : 0;
        $baseline = $adults * $a_price + $children * $c_price;
        $options = array();
        // Each package covers 1 or 2 actual adults, at most 4 actual people;
        // unused child allowances are permitted. It always issues 2+2 components.
        for ( $f = 0; $f <= ( $f_price ? min( 10, $adults ) : 0 ); $f++ ) {
            for ( $covered_adults = $f; $covered_adults <= min( 2 * $f, $adults ); $covered_adults++ ) {
                $covered_children = min( $children, 4 * $f - $covered_adults );
                $single_adults = $adults - $covered_adults;
                $single_children = $children - $covered_children;
                $issued_adults = 2 * $f + $single_adults;
                $issued_children = 2 * $f + $single_children;
                $units = 4 * $f + $single_adults + $single_children;
                if ( $units > $available || $units > $parent_max || $issued_adults > $a_max || $issued_children > $c_max ) { continue; }
                $total = $f * $f_price + $single_adults * $a_price + $single_children * $c_price;
                $key = $f . ':' . $single_adults . ':' . $single_children;
                $lines = array();
                if ( $f ) { $lines[] = array( 'code' => 'family_2_2', 'quantity' => $f, 'unit_minor' => $f_price ); }
                if ( $single_adults ) { $lines[] = array( 'code' => $adult['code'], 'quantity' => $single_adults, 'unit_minor' => $a_price ); }
                if ( $single_children ) { $lines[] = array( 'code' => $child['code'], 'quantity' => $single_children, 'unit_minor' => $c_price ); }
                $options[ $key ] = array(
                    'lines' => $lines, 'total_minor' => $total, 'savings_minor' => $baseline - $total,
                    'capacity_units' => $units, 'family_quantity' => $f,
                    'issued_adults' => $issued_adults, 'issued_children' => $issued_children,
                );
            }
        }
        $options = array_values( $options );
        usort( $options, static function( $a, $b ) {
            foreach ( array( 'total_minor', 'capacity_units', 'family_quantity', 'issued_adults', 'issued_children' ) as $key ) {
                if ( $a[ $key ] !== $b[ $key ] ) { return $a[ $key ] <=> $b[ $key ]; }
            }
            return 0;
        } );
        if ( ! $options ) { throw new Failure( 'NO_FEASIBLE_COMBINATION' ); }
        return array(
            'policy_version' => self::POLICY, 'session_id' => $offer['session_id'],
            'source_revision' => $offer['source_revision'], 'verified_at' => $offer['verified_at'],
            'expires_at' => $offer['expires_at'], 'currency' => 'TRY',
            'party' => array( 'adults' => $adults, 'children' => $children, 'infants' => $infants ),
            'attendance_count' => $adults + $children + $infants,
            'regular_total_minor' => $baseline,
            'regular_option_available' => $adults <= $a_max && $children <= $c_max && $adults + $children <= min( $available, $parent_max ),
            'recommended' => $options[0], 'alternatives' => array_slice( $options, 1 ),
            'ticket_url' => $offer['ticket_url'], 'reservation' => false,
        );
    }
}
