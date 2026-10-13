<?php
/**
 * Staging-only browser smoke check of the registered WordPress Abilities API.
 * Reads schemas and validates inputs; never executes a write ability.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function mdg_ai_brand_smoke_is_staging() {
    $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    return 0 === strpos( $host, 'staging-' )
        && str_ends_with( $host, '.wpcomstaging.com' );
}
function mdg_ai_brand_smoke_results() {
    $rows = array();
    $add = static function( $label, $ok ) use ( &$rows ) {
        $rows[] = array( 'label' => $label, 'ok' => (bool) $ok );
    };
    $add( 'WordPress Abilities API mevcut', function_exists( 'wp_get_ability' ) );
    if ( ! function_exists( 'wp_get_ability' ) ) { return $rows; }
    $ability = wp_get_ability( 'madagaskar/program-create' );
    $add( 'Program oluşturma API kayıtlı', null !== $ability );
    $add( 'Program callback yüklü', function_exists( 'mdg_ai_program_create' ) );
    if ( ! $ability ) { return $rows; }
    $schema = $ability->get_input_schema();
    $add( 'Marka alanı zorunlu', in_array( 'brand', $schema['required'] ?? array(), true ) );
    $add( 'Yalnız iki kanonik marka kabul ediliyor', array( 'Madagaskar Sirki', 'Milano Sirki' ) === ( $schema['properties']['brand']['enum'] ?? array() ) );
    $base = array( 'province_name'=>'Yalova', 'district_name'=>'Merkez', 'plan_year'=>2026, 'planned_date'=>'2026-10-22' );
    foreach ( array( 'Milano Sirki', 'Madagaskar Sirki' ) as $brand ) {
        $add( $brand . ' geçerli giriş', true === $ability->validate_input( $base + array( 'brand'=>$brand ) ) );
    }
    $add( 'Markasız giriş reddedildi', is_wp_error( $ability->validate_input( $base ) ) );
    foreach ( array( '', 'both', 'Milano+Madagaskar' ) as $brand ) {
        $add( 'Geçersiz marka reddedildi: ' . ( $brand ?: '(boş)' ), is_wp_error( $ability->validate_input( $base + array( 'brand'=>$brand ) ) ) );
    }
    foreach ( array( 'one', 'batch' ) as $kind ) {
        $legacy = wp_get_ability( 'madagaskar/legacy-mdg-mmc-migrate-' . $kind );
        $add( 'Legacy ' . $kind . ' API kayıtlı', null !== $legacy );
        if ( ! $legacy ) { continue; }
        $input = array( 'confirmation'=>'MIGRATE_LEGACY_MDG_TO_MMC', 'brand'=>'Madagaskar Sirki' );
        $input[ 'one' === $kind ? 'mdg_event_id' : 'mdg_event_ids' ] = 'one' === $kind ? 1 : array( 1 );
        $add( 'Legacy ' . $kind . ' açık Madagaskar markası geçerli', true === $legacy->validate_input( $input ) );
        $input['brand'] = 'Milano Sirki';
        $add( 'Legacy ' . $kind . ' Milano markası reddedildi', is_wp_error( $legacy->validate_input( $input ) ) );
        unset( $input['brand'] );
        $add( 'Legacy ' . $kind . ' markasız giriş reddedildi', is_wp_error( $legacy->validate_input( $input ) ) );
    }
    return $rows;
}
function mdg_ai_brand_smoke_page() {
    if ( ! mdg_ai_brand_smoke_is_staging() || ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'Bu kontrol yalnız yetkili prova ortamında kullanılabilir.', 'madagaskar-ai-abilities' ) );
    }
    $rows = mdg_ai_brand_smoke_results();
    $passed = count( array_filter( $rows, static fn( $row ) => $row['ok'] ) );
    echo '<div class="wrap"><h1>Milano marka API prova doğrulaması</h1>';
    echo '<p>Yalnız kayıtlı API şeması ve giriş doğrulaması sınanır. Program, sipariş, ödeme, bilet veya QR oluşturulmaz.</p>';
    echo '<h2>' . esc_html( $passed . ' / ' . count( $rows ) . ' kontrol başarılı' ) . '</h2><table class="widefat striped"><thead><tr><th>Kontrol</th><th>Sonuç</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        echo '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $row['ok'] ? 'PASS' : 'FAIL' ) . '</td></tr>';
    }
    echo '</tbody></table><p>Bu kontrol ödeme / QR uçtan uca testi değildir.</p></div>';
}
add_action( 'admin_menu', static function() {
    if ( mdg_ai_brand_smoke_is_staging() ) {
        add_management_page( 'Milano marka API prova doğrulaması', 'Milano marka API prova', 'manage_woocommerce', 'mdg-brand-api-smoke', 'mdg_ai_brand_smoke_page' );
    }
} );
