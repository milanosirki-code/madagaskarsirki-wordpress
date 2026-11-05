<?php
$GLOBALS['fixture_family_active'] = true;
$GLOBALS['fixture_can_manage'] = true;
$GLOBALS['fixture_mmc_status'] = 'sales_open';
$GLOBALS['fixture_wrong_parent'] = false;
$GLOBALS['fixture_cart_calls'] = 0;
$GLOBALS['fixture_capacity'] = 500;
function current_user_can( $cap ) { return $cap === 'manage_woocommerce' && $GLOBALS['fixture_can_manage']; }
function wp_timezone_string() { return 'Europe/Istanbul'; }
function wc_load_cart() { $GLOBALS['fixture_cart_calls']++; throw new RuntimeException( 'Cart write attempted' ); }
function get_option( $key, $default = null ) {
    if ( $key !== 'mdg_family_package_22_v1' ) { throw new RuntimeException( 'Unexpected option read' ); }
    return array( 'events' => array( 36 => 1 ), 'event_prices' => array( 36 => 1300 ), 'price' => 1100 );
}
class FixtureDB {
    public $prefix = 'fixture_';
    public $writes = 0;
    public function prepare( $sql, $id ) { return str_replace( '%d', (string) $id, $sql ); }
    public function get_results( $sql ) {
        if ( strpos( $sql, 'SELECT program_id,mmc_event_id,mdg_event_id FROM fixture_mmc_mdg_event_bridge' ) !== 0 ) { throw new RuntimeException( 'Unexpected query' ); }
        return array( (object) array( 'program_id' => 26, 'mmc_event_id' => 26, 'mdg_event_id' => 36 ) );
    }
    public function get_var( $sql ) { return 36; }
    public function __call( $name, $args ) { $this->writes++; throw new RuntimeException( 'Forbidden DB method ' . $name ); }
}
$GLOBALS['wpdb'] = new FixtureDB();
class MMC_Program_Service {
    public static function get_program( $id ) {
        return (object) array( 'id' => 26, 'brand' => 'Madagaskar Sirki', 'status' => 'sales_open',
            'planned_date' => '2026-11-12', 'province_name' => 'Tekirdağ', 'district_name' => 'Çerkezköy' );
    }
    public static function all_programs() { return array( self::get_program( 26 ) ); }
}
class MMC_Event_Service {
    public static function event_for_program( $id ) { return (object) array( 'id' => 26, 'program_id' => 26, 'status' => $GLOBALS['fixture_mmc_status'], 'program_venue_id' => 26, 'event_date' => '2026-11-12' ); }
    public static function sessions( $id ) { return array( (object) array( 'status' => 'active', 'session_time' => '2026-11-12 18:00:00' ) ); }
    public static function ticket_types( $id ) { return array( (object) array( 'ticket_code' => 'family_2_2', 'price' => '1100', 'is_active' => $GLOBALS['fixture_family_active'] ? 1 : 0 ) ); }
}
class MMC_Venue_Service {
    public static function get_program_venue( $id ) { return (object) array( 'venue_source' => 'mdg', 'venue_id' => 50, 'program_id' => 26, 'is_selected' => 1 ); }
}
class MDG_Events {
    public static function get( $id ) { return (object) array( 'id' => 36, 'title' => 'Fixture', 'venue_id' => 50, 'venue_name' => 'Fixture', 'venue_address' => 'Fixture', 'status' => 'onsale', 'sale_start' => null, 'sale_end' => null ); }
}
class MDG_Sessions {
    public static function by_event( $id ) {
        return array( (object) array( 'id' => 223, 'event_id' => 36, 'status' => 'onsale', 'start_at' => '2026-11-12 15:00:00',
            'sale_start' => null, 'sale_end' => null, 'wc_product_id' => 900, 'tickera_event_id' => 800 ) );
    }
    public static function ticket_types_by_session( $id ) {
        return array(
            (object) array( 'id' => 1, 'code' => 'ADULT', 'price' => '490', 'is_active' => 1, 'capacity_units' => 1, 'wc_variation_id' => 901 ),
            (object) array( 'id' => 2, 'code' => 'CHILD', 'price' => '245', 'is_active' => 1, 'capacity_units' => 1, 'wc_variation_id' => 902 ),
        );
    }
    public static function sales_closed_by_time( $s ) { return false; }
}
class MDG_Capacity { public static function available( $id ) { return $GLOBALS['fixture_capacity']; } }
class MDG_Public_Event { public static function live_url( $e ) { return 'https://madagaskarsirki.com/etkinlik/fixture-2026/'; } }
class MDG_DB { public static function table( $name ) { return 'fixture_mdg_' . $name; } }
class MDG_Family_Package_22_V1 {}
class FixtureProduct {
    private $id;
    public function __construct( $id ) { $this->id = $id; }
    public function is_type( $type ) { return $type === ( $this->id === 900 ? 'variable' : 'variation' ); }
    public function get_parent_id() { return $GLOBALS['fixture_wrong_parent'] ? 999 : 900; }
    public function get_status() { return 'publish'; }
    public function is_purchasable() { return true; }
    public function is_in_stock() { return true; }
    public function get_max_purchase_quantity() { return -1; }
    public function get_price() { return $this->id === 901 ? '490' : '245'; }
}
function wc_get_product( $id ) { return new FixtureProduct( $id ); }
