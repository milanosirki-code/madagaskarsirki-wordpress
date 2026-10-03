<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Salon harita QR görsellerini WordPress Media Library içinde saklar.
 * QR matrisi yönetim tarayıcısında yerel qrcode.js ile üretilir; sunucu
 * sadece doğrulanmış PNG verisini güvenli biçimde upload dizinine kaydeder.
 * Böylece canlı bilet akışı harici bir QR servisine bağlı değildir.
 */
final class MDG_QR {
    const MAX_PNG_BYTES = 2097152; // 2 MB

    public static function maps_hash( $maps_url ) {
        $maps_url = trim( (string) $maps_url );
        return $maps_url ? hash( 'sha256', $maps_url ) : '';
    }

    public static function attachment_url( $attachment_id ) {
        $attachment_id = absint( $attachment_id );
        if ( ! $attachment_id ) { return ''; }
        $url = wp_get_attachment_image_url( $attachment_id, 'full' );
        return $url ? $url : '';
    }

    public static function save_png_data_url( $data_url, $venue_id, $maps_url ) {
        $data_url = trim( (string) $data_url );
        $venue_id = absint( $venue_id );
        $maps_url = trim( (string) $maps_url );

        if ( ! $venue_id || ! $maps_url ) {
            return new WP_Error( 'mdg_qr_missing_context', 'QR kaydı için salon ve harita bağlantısı gereklidir.' );
        }

        if ( ! preg_match( '#^data:image/png;base64,([A-Za-z0-9+/=\r\n]+)$#', $data_url, $matches ) ) {
            return new WP_Error( 'mdg_qr_invalid_data_url', 'QR görsel verisi geçerli bir PNG değildir.' );
        }

        $bytes = base64_decode( preg_replace( '/\s+/', '', $matches[1] ), true );
        if ( false === $bytes || '' === $bytes ) {
            return new WP_Error( 'mdg_qr_decode_failed', 'QR PNG verisi çözülemedi.' );
        }

        if ( strlen( $bytes ) > self::MAX_PNG_BYTES ) {
            return new WP_Error( 'mdg_qr_too_large', 'QR PNG dosyası beklenenden büyük.' );
        }

        // PNG magic bytes.
        if ( 0 !== strncmp( $bytes, "\x89PNG\r\n\x1a\n", 8 ) ) {
            return new WP_Error( 'mdg_qr_invalid_png', 'QR görseli PNG imzası taşımıyor.' );
        }

        $hash = self::maps_hash( $maps_url );
        $filename = sanitize_file_name( 'madagaskar-salon-' . $venue_id . '-konum-' . substr( $hash, 0, 12 ) . '.png' );

        $upload = wp_upload_bits( $filename, null, $bytes );
        if ( ! empty( $upload['error'] ) ) {
            return new WP_Error( 'mdg_qr_upload_failed', 'QR dosyası kaydedilemedi: ' . $upload['error'] );
        }

        $attachment_id = wp_insert_attachment( array(
            'post_mime_type' => 'image/png',
            'post_title'     => 'Salon Konumu QR – ' . $venue_id,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ), $upload['file'], 0, true );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $upload['file'] );
            return $attachment_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        if ( is_array( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        update_post_meta( $attachment_id, '_mdg_qr_venue_id', $venue_id );
        update_post_meta( $attachment_id, '_mdg_qr_maps_hash', $hash );
        update_post_meta( $attachment_id, '_mdg_qr_maps_url', esc_url_raw( $maps_url ) );

        return (int) $attachment_id;
    }
}
