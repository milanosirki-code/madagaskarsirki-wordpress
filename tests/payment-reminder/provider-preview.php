<?php
define( 'ABSPATH', __DIR__ );
$GLOBALS['sends'] = 0;
$GLOBALS['writes'] = 0;

function sanitize_key( $value ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function wp_remote_request() { $GLOBALS['sends']++; throw new RuntimeException('send forbidden'); }
function wp_remote_post() { $GLOBALS['sends']++; throw new RuntimeException('send forbidden'); }
function update_option() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }
function update_post_meta() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }

require dirname( __DIR__, 2 ) . '/prototypes/payment-reminder/payment-reminder-provider-preview.php';

$checks = 0;
$check = function( $name, $actual, $expected ) use ( &$checks ) {
    if ( $actual !== $expected ) {
        throw new RuntimeException( $name . ': ' . json_encode( array( $actual, $expected ) ) );
    }
    $checks++;
};

$draft = mdg_payment_reminder_template_draft();
$check( 'draft status', $draft['template_status'], 'draft_unverified' );
$check( 'placeholder exists', strpos( $draft['body'], '{{PAYMENT_LINK}}' ) !== false, true );

$live_like = mdg_payment_reminder_provider_readiness( array(
    'provider_name' => 'kommo_whatsapp_candidate',
    'template_verified' => false,
    'messaging_adapter_verified' => false,
) );
$check( 'provider not ready without verified template', $live_like['provider_ready'], false );
$check( 'template blocker', $live_like['provider_readiness_reason'], 'NO_VERIFIED_PAYMENT_REMINDER_TEMPLATE' );
$check( 'send hard off', $live_like['send_enabled'], false );
$check( 'real sends zero', $live_like['real_send_count'], 0 );

$template_only = mdg_payment_reminder_provider_readiness( array(
    'template_verified' => true,
    'messaging_adapter_verified' => false,
) );
$check( 'adapter blocker', $template_only['provider_readiness_reason'], 'NO_VERIFIED_MESSAGING_ADAPTER' );
$check( 'template alone cannot enable', $template_only['provider_ready'], false );

$all_evidence = mdg_payment_reminder_provider_readiness( array(
    'template_verified' => true,
    'messaging_adapter_verified' => true,
) );
$check( 'evidence can mark readiness', $all_evidence['provider_ready'], true );
$check( 'readiness never enables send', $all_evidence['send_enabled'], false );

$preview = mdg_payment_reminder_message_preview( true, array() );
$encoded = json_encode( $preview, JSON_UNESCAPED_UNICODE );
$check( 'preview mode', $preview['mode'], 'PREVIEW' );
$check( 'preview has no raw placeholder after rendering', strpos( $encoded, '{{PAYMENT_LINK}}' ), false );
$check( 'preview does not contain URL scheme', strpos( $encoded, 'https://' ), false );
$check( 'preview says link exists without exposing it', strpos( $preview['message_preview'], 'GÜVENLİ ÖDEME BAĞLANTISI VAR' ) !== false, true );
$check( 'preview send hard off', $preview['send_enabled'], false );

$check( 'no writes', $GLOBALS['writes'], 0 );
$check( 'no sends', $GLOBALS['sends'], 0 );

echo $checks . " provider/template preview checks passed; writes0; sends0\n";
