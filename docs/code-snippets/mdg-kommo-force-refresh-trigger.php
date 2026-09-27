<?php
/**
 * One-click admin trigger for the Kommo AI source auto-refresh helper.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', function () {
    if ( empty( $_GET['mdg_kommo_force_source_refresh'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $program_id = isset( $_GET['program_id'] ) ? absint( $_GET['program_id'] ) : 0;
    if ( ! $program_id || ! function_exists( 'mdg_kommo_refresh_one_text_source' ) ) {
        update_option(
            'mdg_kommo_force_refresh_last',
            array(
                'at'      => current_time( 'mysql' ),
                'program' => $program_id,
                'ok'      => false,
                'message' => 'Yenileme fonksiyonu bulunamadı veya program ID eksik.',
            ),
            false
        );
        return;
    }

    $result = mdg_kommo_refresh_one_text_source( $program_id );
    update_option(
        'mdg_kommo_force_refresh_last',
        array(
            'at'      => current_time( 'mysql' ),
            'program' => $program_id,
            'ok'      => ! is_wp_error( $result ) && false !== $result,
            'message' => is_wp_error( $result ) ? $result->get_error_message() : wp_json_encode( $result ),
        ),
        false
    );
} );
