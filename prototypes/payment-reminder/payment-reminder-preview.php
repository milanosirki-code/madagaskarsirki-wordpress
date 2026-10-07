<?php
/**
 * SOURCE-ONLY PROTOTYPE — DO NOT DEPLOY
 *
 * Issue #186 payment-reminder preview adapter.
 *
 * Security contract:
 * - read-only;
 * - no remote requests;
 * - no metadata/options/transient/order writes;
 * - never returns or logs the private WooCommerce payment URL;
 * - SEND remains unavailable.
 */

if ( ! function_exists( 'mdg_payment_reminder_payment_link_presence' ) ) {
    function mdg_payment_reminder_payment_link_presence( $order ) {
        $result = array(
            'payment_link_present'     => false,
            'payment_link_source'      => 'woocommerce_order_get_checkout_payment_url',
            'payment_link_same_origin' => false,
        );

        if (
            ! is_object( $order ) ||
            ! method_exists( $order, 'get_checkout_payment_url' ) ||
            ! method_exists( $order, 'needs_payment' ) ||
            ! $order->needs_payment()
        ) {
            return $result;
        }

        $url = (string) $order->get_checkout_payment_url();

        if ( '' === $url ) {
            return $result;
        }

        $url_host  = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_HOST ) : parse_url( $url, PHP_URL_HOST );
        $home      = function_exists( 'home_url' ) ? home_url( '/' ) : '';
        $home_host = $home ? ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $home, PHP_URL_HOST ) : parse_url( $home, PHP_URL_HOST ) ) : '';

        if ( ! $url_host || ! $home_host || strtolower( (string) $url_host ) !== strtolower( (string) $home_host ) ) {
            return $result;
        }

        $result['payment_link_present']     = true;
        $result['payment_link_same_origin'] = true;

        return $result;
    }
}

if ( ! function_exists( 'mdg_payment_reminder_preview' ) ) {
    function mdg_payment_reminder_preview( $order, $eligibility_report ) {
        $eligibility_report = is_array( $eligibility_report ) ? $eligibility_report : array();

        $order_id = (
            is_object( $order ) &&
            method_exists( $order, 'get_id' )
        ) ? (int) $order->get_id() : 0;

        $payment = mdg_payment_reminder_payment_link_presence( $order );

        return array(
            'order_id'                  => $order_id,
            'event_ids'                 => array_values( array_map( 'intval', (array) ( $eligibility_report['event_ids'] ?? array() ) ) ),
            'session_ids'               => array_values( array_map( 'intval', (array) ( $eligibility_report['session_ids'] ?? array() ) ) ),
            'program_ids'               => array_values( array_map( 'intval', (array) ( $eligibility_report['program_ids'] ?? array() ) ) ),
            'eligibility'               => (string) ( $eligibility_report['eligibility'] ?? 'EXCLUDED' ),
            'exclusion_reason'          => (string) ( $eligibility_report['exclusion_reason'] ?? 'PREVIEW_INPUT_MISSING' ),
            'phone_available'           => (bool) ( $eligibility_report['phone_available'] ?? false ),
            'phone_masked'              => (string) ( $eligibility_report['phone_masked'] ?? '' ),
            'replacement_paid'          => $eligibility_report['replacement_paid'] ?? null,
            'already_reminded'          => (bool) ( $eligibility_report['already_reminded'] ?? false ),
            'payment_link_present'      => $payment['payment_link_present'],
            'payment_link_source'       => $payment['payment_link_source'],
            'payment_link_same_origin'  => $payment['payment_link_same_origin'],
            'provider'                  => 'UNDECIDED',
            'message_template_version'  => 'DRAFT-1',
            'mode'                      => 'PREVIEW',
            'real_send_count'           => 0,
        );
    }
}
