<?php
/**
 * Plugin Name: MDG Gemini Staging Diagnostic
 * Description: Fixed-prompt, administrator-only Gemini connectivity check on the approved staging origin.
 * Version: 0.1.1
 * Requires PHP: 7.4
 */
namespace Madagaskar\GeminiDiagnostic;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Check {
    const ORIGIN = 'https://staging-f321-milanosirki-lgajf.wpcomstaging.com';
    const API = 'https://generativelanguage.googleapis.com/v1beta/';
    const LOCK = 'mdg_gemini_diagnostic_lock';

    public static function permission() {
        return rtrim( home_url( '/' ), '/' ) === self::ORIGIN && current_user_can( 'manage_options' );
    }
    public static function routes() {
        if ( rtrim( home_url( '/' ), '/' ) !== self::ORIGIN ) { return; }
        register_rest_route( 'mdg-gemini-diagnostic/v1', '/check', array(
            'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'permission' ),
            'callback' => array( __CLASS__, 'run' ),
        ) );
        register_rest_route( 'mdg-gemini-diagnostic/v1', '/models', array(
            'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'permission' ),
            'callback' => array( __CLASS__, 'models' ),
        ) );
    }
    private static function response( $code, $status, $extra = array() ) {
        $response = new \WP_REST_Response( array_merge( array( 'code' => $code, 'provider' => 'google', 'success' => $code === 'GEMINI_OK' ), $extra ), $status );
        $response->header( 'Cache-Control', 'private, no-store' );
        return $response;
    }
    private static function request( $path, $key, $body = null ) {
        $args = array( 'timeout' => 25, 'redirection' => 0, 'limit_response_size' => 131072,
            'headers' => array( 'x-goog-api-key' => $key, 'Content-Type' => 'application/json' ),
            'method' => $body === null ? 'GET' : 'POST' );
        if ( $body !== null ) { $args['body'] = wp_json_encode( $body ); }
        $raw = wp_remote_request( self::API . $path, $args );
        if ( is_wp_error( $raw ) ) { return array( 'code' => 'GOOGLE_NETWORK_ERROR', 'status' => 502 ); }
        $status = (int) wp_remote_retrieve_response_code( $raw );
        if ( $status !== 200 ) {
            $codes = array( 400 => 'GOOGLE_REQUEST_REJECTED', 401 => 'GOOGLE_AUTH_REJECTED', 403 => 'GOOGLE_ACCESS_DENIED', 404 => 'GOOGLE_MODEL_UNAVAILABLE', 429 => 'GOOGLE_QUOTA_OR_RATE_LIMIT' );
            $error = json_decode( wp_remote_retrieve_body( $raw ), true );
            $safe_status = $error['error']['status'] ?? null;
            if ( ! in_array( $safe_status, array( 'INVALID_ARGUMENT', 'UNAUTHENTICATED', 'PERMISSION_DENIED', 'NOT_FOUND', 'RESOURCE_EXHAUSTED', 'UNAVAILABLE', 'INTERNAL' ), true ) ) { $safe_status = null; }
            return array( 'code' => $codes[$status] ?? 'GOOGLE_SERVICE_ERROR', 'status' => 502, 'upstream_status' => $status, 'upstream_code' => $safe_status );
        }
        $data = json_decode( wp_remote_retrieve_body( $raw ), true );
        if ( ! is_array( $data ) ) { return array( 'code' => 'GOOGLE_INVALID_RESPONSE', 'status' => 502 ); }
        return array( 'data' => $data );
    }
    public static function models( $request ) { return self::run( $request, true ); }
    public static function run( $request, $list_only = false ) {
        if ( ! self::permission() ) { return self::response( 'FORBIDDEN', 403 ); }
        if ( strlen( $request->get_body() ) > 16 || ! in_array( trim( $request->get_body() ), array( '', '{}' ), true ) ) {
            return self::response( 'FIXED_TEST_ONLY', 400 );
        }
        if ( get_transient( 'mdg_gemini_diagnostic_cooldown' ) ) { return self::response( 'COOLDOWN', 429 ); }
        if ( ! add_option( self::LOCK, 1, '', false ) ) { return self::response( 'CHECK_IN_PROGRESS', 429 ); }
        try {
            set_transient( 'mdg_gemini_diagnostic_cooldown', 1, 60 );
            if ( ! in_array( 'ai-provider-for-google/plugin.php', (array) get_option( 'active_plugins', array() ), true )
                && ! isset( get_site_option( 'active_sitewide_plugins', array() )['ai-provider-for-google/plugin.php'] ) ) {
                return self::response( 'PROVIDER_INACTIVE', 503 );
            }
            $key = get_option( 'connectors_ai_google_api_key', '' );
            if ( ! is_string( $key ) || trim( $key ) === '' ) { return self::response( 'CREDENTIAL_MISSING', 503 ); }
            $models = self::request( 'models?pageSize=100', $key );
            if ( isset( $models['code'] ) ) { return self::response( $models['code'], $models['status'], array( 'phase' => 'models', 'upstream_status' => $models['upstream_status'] ?? null ) ); }
            $available = array();
            foreach ( $models['data']['models'] ?? array() as $model ) {
                if ( is_array( $model ) && in_array( 'generateContent', $model['supportedGenerationMethods'] ?? array(), true )
                    && preg_match( '#^models/gemini-[a-z0-9.-]{1,70}$#D', $model['name'] ?? '' ) ) { $available[] = $model['name']; }
            }
            if ( $list_only ) { return self::response( 'MODEL_LIST_OK', 200, array( 'models' => array_slice( $available, 0, 100 ) ) ); }
            $selected = null;
            foreach ( array( 'models/gemini-2.5-flash-lite', 'models/gemini-2.5-flash', 'models/gemini-2.0-flash' ) as $model ) {
                if ( in_array( $model, $available, true ) ) { $selected = $model; break; }
            }
            if ( $selected === null ) { return self::response( 'SUPPORTED_MODEL_NOT_FOUND', 503 ); }
            $config = array( 'maxOutputTokens' => 256, 'temperature' => 0 );
            if ( strpos( $selected, 'gemini-2.5-' ) !== false ) { $config['thinkingConfig'] = array( 'thinkingBudget' => 0 ); }
            $result = self::request( $selected . ':generateContent', $key, array(
                'contents' => array( array( 'role' => 'user', 'parts' => array( array( 'text' => 'Reply with exactly MDG_GEMINI_OK and nothing else.' ) ) ) ),
                'generationConfig' => $config,
            ) );
            if ( isset( $result['code'] ) ) { return self::response( $result['code'], $result['status'], array( 'phase' => 'generation', 'model' => $selected, 'upstream_status' => $result['upstream_status'] ?? null, 'upstream_code' => $result['upstream_code'] ?? null ) ); }
            $text = '';
            foreach ( $result['data']['candidates'][0]['content']['parts'] ?? array() as $part ) {
                if ( is_string( $part['text'] ?? null ) && empty( $part['thought'] ) ) { $text .= $part['text']; }
            }
            if ( trim( $text ) !== 'MDG_GEMINI_OK' ) { return self::response( 'GENERATION_NOT_CONFIRMED', 502 ); }
            return self::response( 'GEMINI_OK', 200, array( 'model' => $selected, 'fixed_prompt_only' => true, 'customer_data_sent' => false ) );
        } catch ( \Throwable $error ) {
            // Never return exception messages, provider bodies, request headers or credentials.
            return self::response( 'DIAGNOSTIC_INTERNAL_ERROR', 500 );
        } finally {
            delete_option( self::LOCK );
        }
    }
}
add_action( 'rest_api_init', array( Check::class, 'routes' ) );
