<?php
/**
 * Standalone MMC "Yeni Program Aç" ownership-contract tests.
 * php integrations/milano/tests/mmc-program-brand-contract.php <scenario>
 * Uses stub DB, NO WordPress connection and NO persistent records.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
$scenario = $argv[1] ?? 'create_milano';
function contract_assert( $yes, $text ) {
    if ( ! $yes ) { fwrite( STDERR, "FAIL: {$text}\n" ); exit( 1 ); }
}
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $v ) ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_date( $format ) { return '2026'; }
function current_time( $format ) { return '2026-10-12 22:20:00'; }
function get_current_user_id() { return 9; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function do_action( $name, ...$args ) {}
class WP_Error {
    private $code;
    public function __construct( $code, $text ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class MMC_Region_Service {
    public static function normalize_place_name( $v ) { return sanitize_text_field( $v ); }
}
class StubDb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $programRows = array();
    public $auditRows = array();
    public $tasks = array();
    public $failProgram = false;
    public function esc_like( $s ) { return $s; }
    public function prepare( $sql, ...$args ) { return $sql; }
    public function get_var( $sql ) { return null; }
    public function insert( $table, $data, $format = null ) {
        if ( 'wp_mmc_programs' === $table ) {
            if ( $this->failProgram ) { return false; }
            $this->insert_id = 29;
            $this->programRows[] = $data;
        } elseif ( 'wp_mmc_logs' === $table ) {
            $this->auditRows[] = $data;
        } elseif ( 'wp_mmc_tasks' === $table ) {
            $this->tasks[] = $data;
        }
        return 1;
    }
}
$wpdb = new StubDb();
require dirname( __DIR__, 3 ) . '/wp-content/plugins/madagaskar-management-center/includes/class-mmc-program-service.php';
$service = 'MMC_Program_Service';
$valid = $service::brand_options();
contract_assert( count( $valid ) === 2, 'exactly two supported brands are required' );
contract_assert( isset( $valid['Milano Sirki'] ) && isset( $valid['Madagaskar Sirki'] ), 'brand options mismatch' );
$adminFile = dirname( __DIR__, 3 ) . '/wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php';
$adminSrc = file_get_contents( $adminFile );
contract_assert( false !== strpos( $adminSrc, '<select name="brand" required>' ), 'new program brand selection not mandatory' );
contract_assert( false !== strpos( $adminSrc, 'Markası Belirsiz — İnceleme Gerekli' ), 'legacy brand warning not present' );
contract_assert( false !== strpos( $adminSrc, '<th>Satış Markası</th>' ), 'list brand column not present' );
contract_assert( false !== strpos( $adminSrc, 'MMC_Program_Service::brand_options()' ), 'brand dropdown not populated from canonical service' );
contract_assert( false === strpos( $adminSrc, '<option value="Milano Sirki" selected' ), 'unsafe Milano default found' );
contract_assert( false === strpos( $adminSrc, '<option value="Madagaskar Sirki" selected' ), 'unsafe Madagaskar default found' );


$abilityFile = dirname( __DIR__, 3 ) . '/wp-content/plugins/madagaskar-ai-abilities/modules/program-venue-event-sales.php';
$abilitySrc = file_get_contents( $abilityFile );
contract_assert( false !== strpos( $abilitySrc, "'madagaskar/program-create'" ), 'MMC create ability missing' );
contract_assert( false !== strpos( $abilitySrc, "'brand'         => sanitize_text_field( " ), 'API callback drops sales brand' );
contract_assert( false !== strpos( $abilitySrc, "'brand' => array( 'type' => 'string', 'enum' => array( 'Madagaskar Sirki', 'Milano Sirki' ) )" ), 'API schema lacks canonical sales brand allowlist' );
contract_assert( false !== strpos( $abilitySrc, "'required' => array( 'province_name', 'brand' )" ), 'API schema does not require brand' );

$data = array(
    'brand' => 'Milano Sirki',
    'province_name' => 'Ankara',
    'district_name' => 'Çankaya',
    'plan_year' => 2027,
    'planned_date' => '2027-01-10',
    'notes' => 'Test fixture',
);
switch ( $scenario ) {
    case 'create_milano': break;
    case 'yalova_milano':
        $data['brand'] = 'Milano Sirki';
        $data['province_name'] = 'Yalova';
        $data['district_name'] = 'Merkez';
        $data['plan_year'] = 2026;
        $data['planned_date'] = '2026-10-22';
        break;
    case 'create_madagaskar': $data['brand'] = 'Madagaskar Sirki'; break;
    case 'create_alias_milano': $data['brand'] = 'milano'; break;
    case 'create_alias_madagaskar': $data['brand'] = 'madagaskar'; break;
    case 'create_trimmed': $data['brand'] = '  Milano Sirki  '; break;
    case 'missing_brand': unset( $data['brand'] ); break;
    case 'empty_brand': $data['brand'] = ''; break;
    case 'bad_brand': $data['brand'] = 'Milano+Madagaskar'; break;
    case 'ambiguous_brand': $data['brand'] = 'both'; break;
    case 'no_province': $data['province_name'] = ''; break;
    case 'db_failure': $wpdb->failProgram = true; break;
    default: contract_assert( false, 'unknown scenario' );
}
$result = $service::create_program( $data );
$brandRejected = in_array( $scenario, array( 'missing_brand', 'empty_brand', 'bad_brand', 'ambiguous_brand' ), true );
if ( $brandRejected ) {
    contract_assert( is_wp_error( $result ) && 'mmc_brand_required' === $result->get_error_code(), 'missing or invalid brand accepted' );
} elseif ( 'no_province' === $scenario ) {
    contract_assert( is_wp_error( $result ) && 'mmc_missing_province' === $result->get_error_code(), 'missing region accepted' );
} elseif ( 'db_failure' === $scenario ) {
    contract_assert( is_wp_error( $result ) && 'mmc_insert_failed' === $result->get_error_code(), 'DB failure not reported' );
} else {
    contract_assert( 29 === $result, 'program identity not returned' );
    contract_assert( 1 === count( $wpdb->programRows ), 'program was not inserted once' );
    $expected = in_array( $scenario, array( 'create_madagaskar', 'create_alias_madagaskar' ), true ) ? 'Madagaskar Sirki' : 'Milano Sirki';
    contract_assert( $expected === $wpdb->programRows[0]['brand'], 'canonical owner not persisted' );
    contract_assert( 'preparation' === $wpdb->programRows[0]['status'], 'new program must remain preparation' );
    if ( 'yalova_milano' === $scenario ) {
        contract_assert( $wpdb->programRows[0]['province_name'] === 'Yalova', 'Yalova region missing' );
        contract_assert( $wpdb->programRows[0]['district_name'] === 'Merkez', 'Yalova district missing' );
        contract_assert( $wpdb->programRows[0]['planned_date'] === '2026-10-22', 'Yalova date missing' );
        contract_assert( $wpdb->programRows[0]['brand'] === 'Milano Sirki', 'Yalova must not default to Madagaskar' );
    }
    contract_assert( 3 === count( $wpdb->tasks ), 'initial preparation tasks missing' );
    contract_assert( count( $wpdb->auditRows ) === 1, 'create audit record missing' );
    $audit = json_decode( $wpdb->auditRows[0]['new_value'], true );
    contract_assert( $expected === $audit['brand'], 'owner missing from immutable creation log' );
}
if ( is_wp_error( $result ) ) {
    contract_assert( ! count( $wpdb->programRows ) && ! count( $wpdb->tasks ), 'invalid request wrote program/tasks' );
}
echo "PASS {$scenario}\n";
