<?php
define( 'ABSPATH', __DIR__ );
define( 'MMC_KOMMO_TOKEN', 'unit-test-token' );
define( 'MS_KOMMO_BASE_URL', 'https://milanosirki.kommo.com/api/v4' );

require dirname( __DIR__, 2 ) . '/prototypes/security/snippet-42-mmc-token-migration.php';

$checks = 0;
$check = function( $name, $actual, $expected ) use ( &$checks ) {
    if ( $actual !== $expected ) {
        throw new RuntimeException( $name . ': ' . json_encode( array( $actual, $expected ) ) );
    }
    $checks++;
};

$check( 'MMC token resolver', mdg_corp_kommo_token_v3(), 'unit-test-token' );
$check( 'ready with MMC token + existing base URL', mdg_corp_kommo_ready_v3(), true );

$source = file_get_contents( dirname( __DIR__, 2 ) . '/prototypes/security/snippet-42-mmc-token-migration.php' );
$check( 'no legacy token constant reference', strpos( $source, 'MS_KOMMO_TOKEN' ), false );
$check( 'current token constant required', strpos( $source, 'MMC_KOMMO_TOKEN' ) !== false, true );
$check( 'no fixed bearer credential', preg_match( '/Bearer\s+[A-Za-z0-9._-]{20,}/', $source ), 0 );
$check( 'dynamic helper is used in proposed authorization', strpos( $source, "Bearer ' . mdg_corp_kommo_token_v3()" ) !== false, true );

echo $checks . " snippet42 token-migration checks passed\n";
