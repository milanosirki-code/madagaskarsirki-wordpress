<?php
/**
 * Plugin Name: Madagaskar Kommo AI Source Auto Refresh
 * Description: MMC program kaynagi degistiginde Kommo direct-text kaynagini yeni metin kaynagi ile yeniler.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'mmc_kommo_process_queue', 'mdg_kommo_refresh_needed_text_sources', 20 );
add_action( 'mmc_kommo_process_queue_fast', 'mdg_kommo_refresh_needed_text_sources', 20 );
add_action( 'admin_post_mdg_kommo_refresh_needed_sources', 'mdg_kommo_refresh_needed_sources_post' );

function mdg_kommo_refresh_needed_sources_post() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Yetki yok.', 'madagaskar-management-center' ) );
    }
    check_admin_referer( 'mdg_kommo_refresh_needed_sources' );
    mdg_kommo_refresh_needed_text_sources();
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=mmc-kommo' ) );
    exit;
}

function mdg_kommo_refresh_needed_text_sources() {
    global $wpdb;

    if ( ! class_exists( 'MMC_Kommo_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
        return;
    }

    $table = $wpdb->prefix . 'mmc_kommo_profiles';
    $rows  = $wpdb->get_results(
        "SELECT * FROM {$table} WHERE ai_source_status IN ('refresh_needed','direct_pending') ORDER BY updated_at DESC LIMIT 10"
    );

    foreach ( (array) $rows as $profile ) {
        mdg_kommo_refresh_one_text_source( (int) $profile->program_id );
    }
}

function mdg_kommo_refresh_one_text_source( $program_id ) {
    global $wpdb;

    $program_id = absint( $program_id );
    if ( ! $program_id || ! class_exists( 'MMC_Kommo_Service' ) || ! class_exists( 'MMC_Program_Service' ) ) {
        return false;
    }

    $profile = MMC_Kommo_Service::ensure_profile( $program_id );
    if ( is_wp_error( $profile ) || ! $profile ) {
        return $profile;
    }

    $source_state = MMC_Kommo_Service::direct_text_source_state( $program_id );
    if (
        ! empty( $source_state['source_id'] )
        && ! empty( $source_state['source_hash'] )
        && hash_equals( (string) $source_state['source_hash'], (string) $profile->source_hash )
    ) {
        return true;
    }

    $program = MMC_Program_Service::get_program( $program_id );
    if ( ! $program ) {
        return false;
    }

    $feature = sanitize_key( (string) get_option( 'mmc_kommo_ai_mode', 'suggested_reply' ) );
    if ( ! in_array( $feature, array( 'suggested_reply', 'agent' ), true ) ) {
        $feature = 'suggested_reply';
    }

    $base_text = MMC_Kommo_Service::build_source_text( $program_id );
    $text      = mdg_kommo_source_text_with_priority_rules( $base_text, $program_id );
    $name      = sprintf(
        'MMC | %s | %s / %s | %s',
        $program->program_code,
        $program->province_name,
        $program->district_name ? $program->district_name : 'Genel',
        substr( (string) $profile->source_hash, 0, 8 )
    );

    $payload = array(
        'name'                => $name,
        'lang'                => 'tr',
        'text'                => $text,
        'available_functions' => array( $feature ),
    );

    $updated_existing_source = false;
    $response                = mdg_kommo_ai_text_source_request( $payload );
    if (
        is_wp_error( $response )
        && false !== stripos( $response->get_error_message(), 'source limit' )
        && ! empty( $source_state['source_id'] )
    ) {
        $update_response = mdg_kommo_ai_text_source_update_request( $source_state['source_id'], $payload );
        if ( ! is_wp_error( $update_response ) ) {
            $response                = $update_response;
            $updated_existing_source = true;
        } else {
            $response = new WP_Error(
                'mdg_kommo_ai_source_create_and_update_failed',
                $response->get_error_message() . ' | Update denemesi: ' . $update_response->get_error_message()
            );
        }
    }

    if ( is_wp_error( $response ) ) {
        $wpdb->update(
            $wpdb->prefix . 'mmc_kommo_profiles',
            array(
                'ai_source_status' => 'error',
                'last_error'       => $response->get_error_message(),
                'updated_at'       => current_time( 'mysql' ),
            ),
            array( 'id' => (int) $profile->id )
        );
        return $response;
    }

    $source_id = isset( $response['id'] ) ? sanitize_text_field( (string) $response['id'] ) : '';
    if ( '' === $source_id && $updated_existing_source && ! empty( $source_state['source_id'] ) ) {
        $source_id = sanitize_text_field( (string) $source_state['source_id'] );
    }
    if ( '' === $source_id ) {
        return false;
    }

    update_option(
        'mmc_kommo_ai_text_source_' . $program_id,
        array(
            'source_id'            => $source_id,
            'source_hash'          => (string) $profile->source_hash,
            'source_name'          => $name,
            'lang'                 => 'tr',
            'available_function'   => $feature,
            'created_at'           => current_time( 'mysql' ),
            'legacy_url_source_id' => (string) $profile->ai_source_id,
        ),
        false
    );
    update_option( 'mmc_kommo_ai_transport_' . $program_id, 'text', false );

    $wpdb->update(
        $wpdb->prefix . 'mmc_kommo_profiles',
        array(
            'ai_source_id'     => $source_id,
            'ai_source_status' => 'synced',
            'ai_synced_hash'   => (string) $profile->source_hash,
            'last_synced_at'   => current_time( 'mysql' ),
            'last_error'       => '',
            'updated_at'       => current_time( 'mysql' ),
        ),
        array( 'id' => (int) $profile->id )
    );

    if ( method_exists( 'MMC_Program_Service', 'add_log' ) ) {
        MMC_Program_Service::add_log(
            $program_id,
            'kommo_ai_text_source_refreshed',
            'program',
            $program_id,
            null,
            array(
                'source_id' => $source_id,
                'feature'   => $feature,
                'hash'      => (string) $profile->source_hash,
            ),
            'Kommo AI direct-text kaynagi guncel MMC program metniyle yenilendi.'
        );
    }

    return true;
}

function mdg_kommo_source_text_with_priority_rules( $base_text, $program_id ) {
    $program_id = absint( $program_id );
    $program    = class_exists( 'MMC_Program_Service' ) ? MMC_Program_Service::get_program( $program_id ) : null;
    $event      = ( class_exists( 'MMC_Event_Service' ) && $program_id ) ? MMC_Event_Service::event_for_program( $program_id ) : null;
    $cancelled  = ( $program && 'cancelled' === $program->status ) || ( $event && 'cancelled' === $event->status );

    $status_line = $cancelled ? 'Etkinlik Aktiflik Durumu: İptal' : 'Etkinlik Aktiflik Durumu: Aktif';

    $rules = array(
        'KOMMO ANA TALİMAT',
        'Bu site kaynağı şehir, ilçe, tarih, salon, adres, konum, seans, fiyat ve aktiflik için önceliklidir.',
        'PDF/Temel Bilgiler yalnız genel kurallar içindir. Aktif şehir için "program yayımlanmadı" deme.',
        'Konum/adres/yol tarifi sorusunda salon adı, açık adres ve Google Maps bağlantısını birlikte gönder.',
        '',
        'ZORUNLU ETKİNLİK ALANLARI',
        $status_line,
        'Bilet bağlantısı: https://madagaskarsirki.com/bilet-al/',
        '',
    );

    $important_lines = array();
    $capture_block   = false;
    $block_lines     = 0;
    $labels          = '(Program Kodu|Program Durumu|İl|Il|İlçe|Ilce|Etkinlik|Etkinlik Durumu|Tarih|Salon|Adres|Konum|Google Maps|Bilet|Bilet bağlantısı|Arama kelimeleri|Seanslar|Fiyatlar)';

    foreach ( preg_split( '/\R/u', (string) $base_text ) as $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            $capture_block = false;
            $block_lines   = 0;
            continue;
        }

        if ( preg_match( '/^' . $labels . '\s*:?/iu', $line ) ) {
            $important_lines[] = $line;
            $capture_block     = (bool) preg_match( '/^(Seanslar|Fiyatlar)\s*:?/iu', $line );
            $block_lines       = 0;
            continue;
        }

        if ( $capture_block && preg_match( '/^[-•]/u', $line ) && $block_lines < 8 ) {
            $important_lines[] = $line;
            $block_lines++;
        }
    }

    if ( empty( $important_lines ) ) {
        $important_lines[] = substr( (string) $base_text, 0, 1200 );
    }

    $text = implode( "\n", $rules ) . implode( "\n", array_unique( $important_lines ) );
    return mdg_kommo_limit_source_text( $text, 1950 );
}

function mdg_kommo_limit_source_text( $text, $limit ) {
    $text  = trim( (string) $text );
    $limit = absint( $limit );
    if ( ! $limit ) {
        return $text;
    }

    $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
    if ( $length <= $limit ) {
        return $text;
    }

    $suffix   = "\n[Kommo 2000 karakter limiti için kısaltıldı.]";
    $cut_at   = max( 0, $limit - ( function_exists( 'mb_strlen' ) ? mb_strlen( $suffix, 'UTF-8' ) : strlen( $suffix ) ) );
    $trimmed  = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $cut_at, 'UTF-8' ) : substr( $text, 0, $cut_at );
    return rtrim( $trimmed ) . $suffix;
}

function mdg_kommo_ai_text_source_request( $payload ) {
    $token = '';
    if ( defined( 'MMC_KOMMO_TOKEN' ) && MMC_KOMMO_TOKEN ) {
        $token = (string) MMC_KOMMO_TOKEN;
    } elseif ( defined( 'MS_KOMMO_TOKEN' ) && MS_KOMMO_TOKEN ) {
        $token = (string) MS_KOMMO_TOKEN;
    }

    if ( '' === $token ) {
        return new WP_Error( 'mdg_kommo_token_missing', 'Kommo token bulunamadı.' );
    }

    $response = wp_remote_post(
        'https://airewriter.kommo.com/api/v2/sources/text',
        array(
            'timeout' => 25,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );
    $data = '' !== $body ? json_decode( $body, true ) : array();

    if ( $code < 200 || $code >= 300 ) {
        $detail = is_array( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $body;
        return new WP_Error(
            'mdg_kommo_ai_source_error',
            'Kommo AI text source API hata kodu: ' . $code . ' - ' . substr( (string) $detail, 0, 600 )
        );
    }

    return is_array( $data ) ? $data : array();
}

function mdg_kommo_ai_text_source_update_request( $source_id, $payload ) {
    $source_id = absint( $source_id );
    if ( ! $source_id ) {
        return new WP_Error( 'mdg_kommo_ai_source_update_missing_id', 'Güncellenecek Kommo AI source ID bulunamadı.' );
    }

    $token = '';
    if ( defined( 'MMC_KOMMO_TOKEN' ) && MMC_KOMMO_TOKEN ) {
        $token = (string) MMC_KOMMO_TOKEN;
    } elseif ( defined( 'MS_KOMMO_TOKEN' ) && MS_KOMMO_TOKEN ) {
        $token = (string) MS_KOMMO_TOKEN;
    }

    if ( '' === $token ) {
        return new WP_Error( 'mdg_kommo_token_missing', 'Kommo token bulunamadı.' );
    }

    $attempts = array(
        array( 'PATCH', 'https://airewriter.kommo.com/api/v2/sources/text/' . $source_id ),
        array( 'PUT', 'https://airewriter.kommo.com/api/v2/sources/text/' . $source_id ),
        array( 'PATCH', 'https://airewriter.kommo.com/api/v2/sources/' . $source_id ),
        array( 'PUT', 'https://airewriter.kommo.com/api/v2/sources/' . $source_id ),
    );
    $errors   = array();

    foreach ( $attempts as $attempt ) {
        $response = wp_remote_request(
            $attempt[1],
            array(
                'method'  => $attempt[0],
                'timeout' => 25,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $errors[] = $attempt[0] . ' ' . $attempt[1] . ': ' . $response->get_error_message();
            continue;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = (string) wp_remote_retrieve_body( $response );
        $data = '' !== $body ? json_decode( $body, true ) : array();

        if ( $code >= 200 && $code < 300 ) {
            if ( ! is_array( $data ) ) {
                $data = array();
            }
            $data['id']               = isset( $data['id'] ) ? $data['id'] : $source_id;
            $data['_update_endpoint'] = $attempt[0] . ' ' . $attempt[1];
            return $data;
        }

        $detail   = is_array( $data ) ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : $body;
        $errors[] = $attempt[0] . ' ' . $attempt[1] . ' => ' . $code . ' ' . substr( (string) $detail, 0, 300 );
    }

    return new WP_Error(
        'mdg_kommo_ai_source_update_failed',
        'Kommo AI mevcut kaynak güncellemesi başarısız: ' . implode( ' | ', $errors )
    );
}
