<?php
define( 'ABSPATH', __DIR__ );
$GLOBALS['writes'] = 0;
$GLOBALS['sends']  = 0;

function home_url( $path = '/' ) { return 'https://madagaskarsirki.com' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function update_option() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }
function update_post_meta() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }
function set_transient() { $GLOBALS['writes']++; throw new RuntimeException('write forbidden'); }
function wp_remote_post() { $GLOBALS['sends']++; throw new RuntimeException('send forbidden'); }
function wp_remote_request() { $GLOBALS['sends']++; throw new RuntimeException('send forbidden'); }

class PreviewFixtureOrder {
    private $id;
    private $url;
    private $needs_payment;
    public function __construct( $id, $url, $needs_payment = true ) {
        $this->id = $id;
        $this->url = $url;
        $this->needs_payment = $needs_payment;
    }
    public function get_id() { return $this->id; }
    public function needs_payment() { return $this->needs_payment; }
    public function get_checkout_payment_url() { return $this->url; }
}

require dirname( __DIR__, 2 ) . '/prototypes/payment-reminder/payment-reminder-preview.php';

$checks = 0;
$check = function( $name, $actual, $expected ) use ( &$checks ) {
    if ( $actual !== $expected ) {
        throw new RuntimeException( $name . ': ' . json_encode( array( $actual, $expected ) ) );
    }
    $checks++;
};

$private = 'https://madagaskarsirki.com/odeme/order-pay/4849/?pay_for_order=true&key=wc_order_PRIVATE_TOKEN';
$eligibility = array(
    'event_ids'        => array( 12 ),
    'session_ids'      => array( 99 ),
    'program_ids'      => array( 10 ),
    'eligibility'      => 'ELIGIBLE',
    'exclusion_reason' => '',
    'phone_available'  => true,
    'phone_masked'     => '***1234',
    'replacement_paid' => false,
    'already_reminded' => false,
);

$order = new PreviewFixtureOrder( 4849, $private, true );
$preview = mdg_payment_reminder_preview( $order, $eligibility );

$check( 'mode', $preview['mode'], 'PREVIEW' );
$check( 'send count', $preview['real_send_count'], 0 );
$check( 'payment present', $preview['payment_link_present'], true );
$check( 'same origin', $preview['payment_link_same_origin'], true );
$check( 'canonical source', $preview['payment_link_source'], 'woocommerce_order_get_checkout_payment_url' );
$check( 'provider unresolved', $preview['provider'], 'UNDECIDED' );
$check( 'eligibility preserved', $preview['eligibility'], 'ELIGIBLE' );
$check( 'no private URL leak', strpos( json_encode( $preview ), $private ), false );
$check( 'no order key leak', strpos( json_encode( $preview ), 'PRIVATE_TOKEN' ), false );

$no_need = mdg_payment_reminder_preview(
    new PreviewFixtureOrder( 4850, $private, false ),
    $eligibility
);
$check( 'no payment required means no link', $no_need['payment_link_present'], false );

$external = mdg_payment_reminder_preview(
    new PreviewFixtureOrder( 4851, 'https://example.invalid/pay/4851/?token=SECRET', true ),
    $eligibility
);
$check( 'cross-origin rejected', $external['payment_link_present'], false );
$check( 'cross-origin marked unsafe', $external['payment_link_same_origin'], false );
$check( 'cross-origin URL not leaked', strpos( json_encode( $external ), 'example.invalid' ), false );
$check( 'cross-origin token not leaked', strpos( json_encode( $external ), 'SECRET' ), false );

$empty = mdg_payment_reminder_preview(
    new PreviewFixtureOrder( 4852, '', true ),
    $eligibility
);
$check( 'empty link absent', $empty['payment_link_present'], false );

$excluded = $eligibility;
$excluded['eligibility'] = 'EXCLUDED';
$excluded['exclusion_reason'] = 'EVENT_CANCELLED';
$excluded_preview = mdg_payment_reminder_preview( $order, $excluded );
$check( 'preview never upgrades eligibility', $excluded_preview['eligibility'], 'EXCLUDED' );
$check( 'exclusion preserved', $excluded_preview['exclusion_reason'], 'EVENT_CANCELLED' );

$check( 'no writes', $GLOBALS['writes'], 0 );
$check( 'no sends', $GLOBALS['sends'], 0 );

echo $checks . " payment reminder preview checks passed; writes0; sends0\n";
