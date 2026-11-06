<?php
namespace Madagaskar\WebAssistant;

/** Internal admin-only entry point. No routes, scheduled jobs or public hooks. */
final class Service {
    private static function source() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { throw new Failure( 'FORBIDDEN' ); }
        return new WordPressSource();
    }

    public static function catalogue() {
        $source = self::source();
        $now = time(); $offers = array(); $blocked = array();
        $today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( new \DateTimeZone( 'Europe/Istanbul' ) )->format( 'Y-m-d' );
        foreach ( $source->programs() as $program ) {
            if ( (string) ( $program->brand ?? '' ) !== Catalogue::BRAND || (string) $program->planned_date < $today ) { continue; }
            try {
                $result = Catalogue::offers( $source->bundle( (int) $program->id ), $now );
                $offers = array_merge( $offers, $result['offers'] );
                $blocked = array_merge( $blocked, $result['blocked'] );
            } catch ( Failure $e ) {
                $blocked[] = array( 'program_id' => (int) $program->id, 'reason' => $e->getMessage() );
            }
        }
        usort( $offers, static function( $a, $b ) {
            return strcmp( $a['starts_at'], $b['starts_at'] ) ?: ( $a['session_id'] <=> $b['session_id'] );
        } );
        return array( 'verified_at' => $now, 'offers' => $offers, 'blocked' => $blocked );
    }

    /** Re-reads native data. Client may pass only an ID, party counts and prior revision. */
    public static function quote( $session_id, array $party, $expected_revision = null ) {
        Value::integer( $session_id, 1, PHP_INT_MAX );
        if ( null !== $expected_revision && ( ! is_string( $expected_revision ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $expected_revision ) ) ) {
            throw new Failure( 'INVALID_REVISION' );
        }
        $source = self::source(); $now = time();
        $result = Catalogue::offers( $source->bundle_for_session( $session_id ), $now );
        foreach ( $result['offers'] as $offer ) {
            if ( $offer['session_id'] !== $session_id ) { continue; }
            if ( null !== $expected_revision && ! hash_equals( $offer['source_revision'], $expected_revision ) ) {
                throw new Failure( 'STALE_SELECTION' );
            }
            return Quote::calculate( $offer, $party, time() );
        }
        throw new Failure( 'SESSION_UNAVAILABLE' );
    }
}
