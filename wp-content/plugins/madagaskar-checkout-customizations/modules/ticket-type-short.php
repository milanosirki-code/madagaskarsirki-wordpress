<?php
add_filter( 'tc_ticket_type_element', 'madagaskar_ticket_type_short', 20, 1 );

function madagaskar_ticket_type_short( $title ) {

    $text = wp_strip_all_tags( $title );

    // Tickera varyasyonu parantez içinde veriyorsa:
    // Ürün Adı (Bilet Tipi: Çocuk 3-12 Yaş)
    if ( preg_match( '/\(([^()]*)\)\s*$/u', $text, $match ) ) {

        $variation = trim( $match[1] );

        // "Bilet Tipi:" gibi nitelik adını kaldır.
        if ( strpos( $variation, ':' ) !== false ) {
            $parts = explode( ':', $variation, 2 );
            $variation = trim( $parts[1] );
        }

        return $variation;
    }

    // Mevcut Madagaskar ürün adlandırması:
    // "... 12:00 Bileti - Çocuk 3-12 Yaş"
    if ( preg_match( '/Bileti\s*-\s*(.+)$/u', $text, $match ) ) {
        return trim( $match[1] );
    }

    return $title;
}