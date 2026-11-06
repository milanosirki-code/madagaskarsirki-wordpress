<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Pure calculations. Prices and declarations must come from the server. */
final class MDG_Family24_Pricing {
    public static function quantity( $value ) {
        if ( ! is_scalar( $value ) || ! preg_match( '/^(0|[1-9][0-9]*)$/D', (string) $value ) || (float) $value > 20 ) {
            throw new InvalidArgumentException( 'Bilet adedi 0–20 arasında tam sayı olmalıdır.' );
        }
        return (int) $value;
    }

    public static function expand( array $lines, $child_code, $adult_code ) {
        $normal = array();
        $packages = array( 'family_2_4' => 0, 'family_2_2' => 0 );
        foreach ( $lines as $line ) {
            if ( ! is_array( $line ) || ! isset( $line['code'], $line['qty'] ) || ! is_string( $line['code'] ) ) {
                throw new InvalidArgumentException( 'Bilet seçimi geçersiz.' );
            }
            $code = strtoupper( $line['code'] );
            $qty = self::quantity( $line['qty'] );
            if ( 'MDG_FAMILY_2_4' === $code ) { $packages['family_2_4'] += $qty; }
            elseif ( 'MDG_FAMILY_2_2' === $code ) { $packages['family_2_2'] += $qty; }
            else { $normal[ $code ] = ( $normal[ $code ] ?? 0 ) + $qty; }
        }
        $normal[ $child_code ] = ( $normal[ $child_code ] ?? 0 ) + 4 * $packages['family_2_4'] + 2 * $packages['family_2_2'];
        $normal[ $adult_code ] = ( $normal[ $adult_code ] ?? 0 ) + 2 * ( $packages['family_2_4'] + $packages['family_2_2'] );
        foreach ( $normal as $qty ) {
            if ( $qty > 20 ) { throw new InvalidArgumentException( 'Paket ve tekil biletlerin toplamı bilet türü başına 20 adedi aşamaz.' ); }
        }
        $rewritten = array();
        foreach ( $normal as $code => $qty ) { if ( $qty > 0 ) { $rewritten[] = array( 'code' => $code, 'qty' => $qty ); } }
        return array( 'lines' => $rewritten, 'packages' => $packages );
    }

    /** Allocate the declared large packages first; never discount an incomplete package. */
    public static function discount( array $packages, $child_qty, $adult_qty, $child_price, $adult_price ) {
        $child_qty = max( 0, (int) $child_qty );
        $adult_qty = max( 0, (int) $adult_qty );
        $discount = 0.0;
        $effective = array();
        foreach ( array( 'family_2_4' => 4, 'family_2_2' => 2 ) as $code => $children ) {
            $p = $packages[ $code ] ?? array();
            $count = min( max( 0, (int) ( $p['qty'] ?? 0 ) ), (int) floor( $child_qty / $children ), (int) floor( $adult_qty / 2 ) );
            $target = (float) ( $p['price'] ?? 0 );
            $base = round( $children * $child_price + 2 * $adult_price, 2 );
            if ( $target <= 0 || $target > $base ) { $count = 0; }
            $effective[ $code ] = $count;
            $discount += $count * max( 0, round( $base - $target, 2 ) );
            $child_qty -= $count * $children;
            $adult_qty -= $count * 2;
        }
        return array( 'amount' => round( $discount, 2 ), 'effective' => $effective );
    }

    /** Integer cents, including deterministic remainder, preserve the exact paid total. */
    public static function allocate( array $gross, $discount ) {
        $cents = array_map( static function( $n ) { return max( 0, (int) round( 100 * $n ) ); }, $gross );
        $total = array_sum( $cents );
        $remaining = min( $total, max( 0, (int) round( 100 * $discount ) ) );
        $base_discount = $remaining;
        $cumulative = 0;
        $allocated = 0;
        $net = array();
        foreach ( $cents as $id => $value ) {
            $cumulative += $value;
            $next = $total ? (int) floor( $base_discount * $cumulative / $total ) : 0;
            $share = $next - $allocated;
            $allocated = $next;
            $net[ $id ] = max( 0, $value - $share ) / 100;
        }
        return $net;
    }
}
