<?php
/**
 * Milano 2+2 virtual family price -> two REAL Woo/MDG ticket lines.
 * PURE STAGING COMPOSITION, NOT A WORDPRESS PLUGIN.
 *
 * The caller MUST verify the ORIGINAL signed intent before invoking this.
 * $snapshot and its family policy must come from central MMC/MDG/Woo only.
 * Never read prices/discount from browser, JS or remote Milano site.
 *
 * This conservative v1 contract caps the expanded basket to the signed
 * protocol's 8 units per real line and 20 total quantities. Larger family
 * baskets require a separately reviewed protocol, not silent truncation.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Milano_Central_Family_Quote {
    private static function blocked( $why ) {
        return array( 'status' => 'blocked', 'reason' => $why );
    }

    public static function contains_family( $intent ) {
        if ( ! is_array( $intent ) || ! isset( $intent['items'] )
            || ! is_array( $intent['items'] ) ) { return false; }
        foreach ( $intent['items'] as $item ) {
            if ( is_array( $item ) && ( $item['code'] ?? null ) === 'FAMILY22' ) {
                return true;
            }
        }
        return false;
    }

    /** Validate signed family intent against a trusted central ticket snapshot. */
    public static function validate( $intent, $snapshot, $now ) {
        if ( ! function_exists( 'mcpi_schema_valid' )
            || ! mcpi_schema_valid( $intent ) || ! self::contains_family( $intent )
            || ! class_exists( 'MCPI_Central_Eligibility' )
            || ! class_exists( 'MCPI_Milano_Family22_Normalizer' )
            || ! is_array( $snapshot ) || ! is_array( $snapshot['family'] ?? null )
            || ! is_array( $snapshot['tickets'] ?? null ) ) {
            return self::blocked( 'missing_authoritative_family_contract' );
        }
        $f = $snapshot['family'];
        if ( ( $f['enabled'] ?? null ) !== true
            || ! is_int( $f['price_cents'] ?? null )
            || ! is_int( $f['adult_ticket_type_id'] ?? null )
            || ! is_int( $f['child_ticket_type_id'] ?? null )
            || ! is_string( $f['adult_code'] ?? null )
            || ! is_string( $f['child_code'] ?? null )
            || $f['adult_code'] === $f['child_code']
            || $f['adult_code'] === 'FAMILY22' || $f['child_code'] === 'FAMILY22' ) {
            return self::blocked( 'invalid_central_family_price_or_types' );
        }

        $families = 0;
        $normal = array();
        $requested = array();
        foreach ( $intent['items'] as $item ) {
            $code = $item['code'];
            if ( 'FAMILY22' === $code ) {
                $families = $item['quantity'];
            } elseif ( $code === $f['adult_code'] || $code === $f['child_code'] ) {
                $normal[] = $item;
                $requested[$code] = $item['quantity'];
            } else {
                return self::blocked( 'unapproved_family_basket_type' );
            }
        }
        // This is deliberately more restrictive than the LIVE Madagaskar
        // module: currently an isolated quote contract, not a checkout rollout.
        if ( $families < 1 || $families > 4 ) {
            return self::blocked( 'unapproved_family_quantity' );
        }

        $central_types = array();
        foreach ( array(
            'adult' => array( 'code' => $f['adult_code'], 'id' => $f['adult_ticket_type_id'] ),
            'child' => array( 'code' => $f['child_code'], 'id' => $f['child_ticket_type_id'] ),
        ) as $kind => $expected ) {
            $matches = array_values( array_filter( $snapshot['tickets'], static function( $type ) use ( $expected ) {
                return is_array( $type ) && ( $type['code'] ?? null ) === $expected['code'];
            } ) );
            if ( count( $matches ) !== 1 ) {
                return self::blocked( 'family_ticket_type_ambiguous' );
            }
            $type = $matches[0];
            $central_types[] = array(
                'kind' => $kind,
                'code' => $expected['code'],
                'price_cents' => $type['price_cents'] ?? null,
                'capacity_units' => $type['capacity_units'] ?? null,
                'ticket_type_id' => $expected['id'],
                'variation_id' => $type['variation_id'] ?? null,
                'active' => $type['active'] ?? null,
            );
        }
        $family_plan = MCPI_Milano_Family22_Normalizer::normalize(
            array(
                'event_id' => $intent['event_id'],
                'session_id' => $intent['session_id'],
                'family_quantity' => $families,
                'normal_lines' => $normal,
            ),
            array(
                'event_id' => $snapshot['event']['id'] ?? null,
                'session_id' => $snapshot['session']['id'] ?? null,
                'owner_brand' => $snapshot['event']['owner_brand'] ?? null,
                'order_brand' => 'milano',
                'family_price_cents' => $f['price_cents'],
                'types' => $central_types,
            )
        );
        if ( true !== ( $family_plan['ok'] ?? false ) ) {
            return self::blocked( $family_plan['reason'] ?? 'invalid_family_plan' );
        }

        // Internally expand FAMILY22 to real ticket types BEFORE the existing
        // central eligibility/capacity/variation/price validation. The client
        // never signs or supplies this normalized internal view.
        $expanded_lines = array();
        foreach ( $family_plan['lines'] as $line ) {
            $expanded_lines[] = array(
                'code' => $line['code'],
                'quantity' => $line['quantity'],
            );
        }
        usort( $expanded_lines, static function( $a, $b ) {
            return strcmp( $a['code'], $b['code'] );
        } );
        $expanded = $intent;
        $expanded['items'] = $expanded_lines;
        if ( ! mcpi_schema_valid( $expanded ) ) {
            return self::blocked( 'expanded_admission_limit' );
        }

        $base = MCPI_Central_Eligibility::validate( $expanded, $snapshot, $now );
        if ( 'eligible' !== ( $base['status'] ?? '' ) ) {
            return $base;
        }
        if ( ( $family_plan['expected_admission_units'] ?? null ) !== $base['admission_units']
            || ( $family_plan['component_subtotal_cents'] ?? null ) !== $base['total_cents']
            || ( $family_plan['payable_cents'] ?? 0 ) < 1 ) {
            return self::blocked( 'central_family_price_inconsistent' );
        }
        $base['component_subtotal_cents'] = $base['total_cents'];
        $base['total_cents'] = $family_plan['payable_cents'];
        $base['family_packages'] = $families;
        $base['family_discount_cents'] = $family_plan['discount_fee_cents'];
        $base['family_virtual_line_stored_as_ticket'] = false;
        return $base;
    }
}
