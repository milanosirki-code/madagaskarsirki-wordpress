<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Integrity_Service {
    public static function remember_context() {
        if ( ! is_admin() || ! is_user_logged_in() ) { return; }
        $page = sanitize_key( wp_unslash( $_REQUEST['page'] ?? '' ) );
        if ( 0 !== strpos( $page, 'mmc-' ) && 0 !== strpos( $page, 'mad-okul' ) && 0 !== strpos( $page, 'mdg-' ) ) { return; }

        $program_id = self::request_program_id();
        if ( $program_id && MMC_Program_Service::get_program( $program_id ) ) {
            update_user_meta( get_current_user_id(), 'mmc_active_program_id', $program_id );
        }
    }

    public static function active_program_id() {
        $program_id = self::request_program_id();
        if ( $program_id && MMC_Program_Service::get_program( $program_id ) ) { return $program_id; }
        $saved = absint( get_user_meta( get_current_user_id(), 'mmc_active_program_id', true ) );
        return $saved && MMC_Program_Service::get_program( $saved ) ? $saved : 0;
    }

    private static function request_program_id() {
        $page = sanitize_key( wp_unslash( $_REQUEST['page'] ?? '' ) );
        if ( 0 === strpos( $page, 'mad-okul' ) ) {
            return absint( $_REQUEST['mmc_program_id'] ?? 0 );
        }
        if ( 0 === strpos( $page, 'mdg-' ) ) {
            $program_id = absint( $_REQUEST['mmc_program_id'] ?? 0 );
            if ( $program_id ) { return $program_id; }
            $mdg_event_id = absint( $_REQUEST['event_id'] ?? $_REQUEST['edit'] ?? 0 );
            if ( $mdg_event_id && class_exists('MMC_MDG_Bridge_Service') ) {
                return MMC_MDG_Bridge_Service::program_for_mdg_event( $mdg_event_id );
            }
            return 0;
        }
        return absint( $_REQUEST['program_id'] ?? $_REQUEST['mmc_program_id'] ?? 0 );
    }

    public static function maybe_redirect_to_context() {
        if ( ! is_admin() || wp_doing_ajax() || ! empty( $_POST ) ) { return; }
        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        if ( ! $page ) { return; }

        $mmc_pages = array( 'mmc-preparation','mmc-venue-flow','mmc-events','mmc-sales-prep','mmc-sales','mmc-kommo','mmc-marketing','mmc-field','mmc-operations','mmc-finance','mmc-integrity' );
        $school_pages = array( 'mad-okul-route','mad-okul-assign','mad-okul-route-plan' );
        $active = absint( get_user_meta( get_current_user_id(), 'mmc_active_program_id', true ) );
        if ( ! $active || ! MMC_Program_Service::get_program( $active ) ) { return; }

        if ( in_array( $page, $mmc_pages, true ) && empty( $_GET['program_id'] ) ) {
            wp_safe_redirect( add_query_arg( array( 'page'=>$page, 'program_id'=>$active ), admin_url( 'admin.php' ) ) );
            exit;
        }
        if ( in_array( $page, $school_pages, true ) && empty( $_GET['mmc_program_id'] ) ) {
            wp_safe_redirect( add_query_arg( array( 'page'=>$page, 'mmc_program_id'=>$active ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    public static function shortcuts( $program_id ) {
        $program_id = absint( $program_id );
        return array(
            'Hazırlık'   => add_query_arg( array( 'page'=>'mmc-preparation', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Salon'      => add_query_arg( array( 'page'=>'mmc-venue-flow', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Etkinlik'   => add_query_arg( array( 'page'=>'mmc-events', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Reklam'     => add_query_arg( array( 'page'=>'mmc-marketing', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Okul/Saha'  => add_query_arg( array( 'page'=>'mmc-field', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Okul Rota'  => add_query_arg( array( 'page'=>'mad-okul-route', 'mmc_program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Satış'      => add_query_arg( array( 'page'=>'mmc-sales', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'MDG Bilet'  => class_exists('MMC_MDG_Bridge_Service') ? MMC_MDG_Bridge_Service::admin_url( $program_id ) : add_query_arg( array( 'page'=>'mdg-dashboard', 'mmc_program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Kommo'      => add_query_arg( array( 'page'=>'mmc-kommo', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Operasyon'  => add_query_arg( array( 'page'=>'mmc-operations', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Finans'     => add_query_arg( array( 'page'=>'mmc-finance', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
            'Bütünlük'   => add_query_arg( array( 'page'=>'mmc-integrity', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ),
        );
    }

    public static function selected_venue( $program_id ) {
        global $wpdb;
        $pv = $wpdb->prefix . 'mmc_program_venues';
        $v  = $wpdb->prefix . 'mmc_venues';
        if ( ! self::table_exists( $pv ) || ! self::table_exists( $v ) ) { return null; }
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT pv.*,v.venue_name,v.province_name,v.district_name,v.address,v.maps_url
             FROM $pv pv INNER JOIN $v v ON v.id=pv.venue_id
             WHERE pv.program_id=%d AND pv.is_selected=1
             ORDER BY (pv.allocation_status='approved') DESC,pv.id DESC LIMIT 1",
            absint( $program_id )
        ) );
    }

    public static function checks( $program_id ) {
        global $wpdb;
        $program_id = absint( $program_id );
        $program = MMC_Program_Service::get_program( $program_id );
        if ( ! $program ) { return array(); }

        $out = array();
        $out[] = self::row( 'program', 'Program Dosyası', 'ok', $program->program_code . ' · MMC ID ' . $program_id, self::url( 'mmc-preparation', $program_id ) );

        $targets = self::count_where( 'mmc_program_target_districts', 'program_id', $program_id, " AND is_selected=1" );
        $out[] = self::row( 'region', 'Bölge / Tanıtım Havzası', $targets ? 'ok' : 'warning', $targets ? $targets . ' hedef ilçe programa bağlı.' : 'Programa bağlı hedef ilçe bulunamadı.', self::url( 'mmc-preparation', $program_id ) );

        $venue = self::selected_venue( $program_id );
        if ( ! $venue ) {
            $out[] = self::row( 'venue', 'Salon & Tahsis', 'warning', 'Kesin/seçili salon bağlantısı yok.', self::url( 'mmc-venue-flow', $program_id ) );
        } else {
            $sev = 'approved' === (string) $venue->allocation_status ? 'ok' : 'warning';
            $out[] = self::row( 'venue', 'Salon & Tahsis', $sev, $venue->venue_name . ' · Tahsis: ' . $venue->allocation_status . ' · program_id=' . $program_id, self::url( 'mmc-venue-flow', $program_id ) );
        }

        $event = class_exists( 'MMC_Event_Service' ) ? MMC_Event_Service::event_for_program( $program_id ) : null;
        if ( ! $event ) {
            $out[] = self::row( 'event', 'Etkinlik & Seans', 'warning', 'Bu Program ID için etkinlik henüz oluşturulmadı.', self::url( 'mmc-events', $program_id ) );
        } else {
            $sessions = self::count_where( 'mmc_sessions', 'event_id', (int) $event->id );
            $bad_date = 0;
            if ( $program->planned_date && self::table_exists( $wpdb->prefix . 'mmc_sessions' ) ) {
                $bad_date = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_sessions WHERE event_id=%d AND DATE(session_time)<>%s",
                    (int) $event->id, $program->planned_date
                ) );
            }
            $venue_mismatch = $venue && (int) $event->program_venue_id && (int) $event->program_venue_id !== (int) $venue->id;
            $date_mismatch = $program->planned_date && $event->event_date && $program->planned_date !== $event->event_date;
            $sev = ( $venue_mismatch || $date_mismatch || $bad_date ) ? 'critical' : ( $sessions ? 'ok' : 'warning' );
            $detail = 'Event #' . (int) $event->id . ' · ' . $sessions . ' seans · program_id=' . (int) $event->program_id;
            if ( $date_mismatch ) { $detail .= ' · TARİH UYUŞMUYOR'; }
            if ( $venue_mismatch ) { $detail .= ' · SALON UYUŞMUYOR'; }
            if ( $bad_date ) { $detail .= ' · ' . $bad_date . ' seans farklı tarihte'; }
            $out[] = self::row( 'event', 'Etkinlik & Seans', $sev, $detail, self::url( 'mmc-events', $program_id ) );
        }

        $expected = 0; $mapped = 0; $mapping_mismatch = 0;
        if ( $event ) {
            $session_count = self::count_where( 'mmc_sessions', 'event_id', (int) $event->id, " AND status='active'" );
            $ticket_count  = self::count_where( 'mmc_ticket_types', 'event_id', (int) $event->id, " AND is_active=1" );
            $expected = $session_count * $ticket_count;
            if ( self::table_exists( $wpdb->prefix . 'mmc_sales_mappings' ) ) {
                $mapped = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_sales_mappings
                     WHERE program_id=%d AND event_id=%d AND is_active=1
                       AND wc_product_id>0 AND wc_variation_id>0 AND tickera_event_id>0",
                    $program_id, (int) $event->id
                ) );
                $mapping_mismatch = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_sales_mappings WHERE program_id=%d AND event_id<>%d",
                    $program_id, (int) $event->id
                ) );
            }
        }
        $publish_sev = $mapping_mismatch ? 'critical' : ( $expected && $mapped >= $expected ? 'ok' : 'warning' );
        $publish_detail = $event ? ( 'Event durumu: ' . $event->status . ' · satış nesnesi eşleştirmesi ' . $mapped . '/' . $expected . ( $mapping_mismatch ? ' · farklı event_id kullanan kayıt var' : '' ) ) : 'Önce etkinlik oluşturulmalı.';
        $out[] = self::row( 'publish', 'Etkinlik Yayın / WooCommerce / Tickera', $publish_sev, $publish_detail, self::url( 'mmc-events', $program_id ) );

        foreach ( self::mdg_bridge_rows( $program_id, $event ) as $mdg_row ) {
            $out[] = $mdg_row;
        }

        $orders = 0; $net_revenue = 0.0;
        if ( self::table_exists( $wpdb->prefix . 'mmc_sales_ledger' ) ) {
            $sales_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT COUNT(DISTINCT external_order_id) orders_count,COALESCE(SUM(net_amount),0) net_revenue
                 FROM {$wpdb->prefix}mmc_sales_ledger WHERE program_id=%d",
                $program_id
            ) );
            if ( $sales_row ) { $orders=(int)$sales_row->orders_count; $net_revenue=(float)$sales_row->net_revenue; }
        }
        $sales_open = $event && 'sales_open' === (string)$event->status;
        $out[] = self::row(
            'sales', 'Satış & Doluluk',
            $sales_open ? 'ok' : 'warning',
            ( $event ? 'Event: '.$event->status.' · ' : '' ) . $orders . ' sipariş · ' . number_format_i18n( $net_revenue, 2 ) . ' TL net ciro · program_id=' . $program_id,
            self::url( 'mmc-sales', $program_id )
        );

        $meta = self::one_where( 'mmc_meta_plans', 'program_id', $program_id );
        $marketing_items = self::count_where( 'mmc_marketing_items', 'program_id', $program_id );
        $meta_mismatch = $meta && $event && (int) $meta->event_id && (int) $meta->event_id !== (int) $event->id;
        $out[] = self::row(
            'marketing', 'Afiş / Sosyal / Meta',
            $meta_mismatch ? 'critical' : ( $meta ? 'ok' : 'warning' ),
            $meta ? ( 'Meta plan #' . (int) $meta->id . ' · ' . $marketing_items . ' içerik · program_id=' . $program_id . ( $meta_mismatch ? ' · EVENT UYUŞMUYOR' : '' ) ) : 'Bu program için Meta planı henüz yok.',
            self::url( 'mmc-marketing', $program_id )
        );

        $targets_count = self::count_where( 'mmc_program_target_schools', 'program_id', $program_id );
        $routes_count  = self::count_where( 'mmc_field_routes', 'program_id', $program_id, " AND is_active=1" );
        $out[] = self::row( 'field', 'MMC Okul / Saha', $targets_count ? 'ok' : 'warning', $targets_count . ' hedef okul · ' . $routes_count . ' kayıtlı rota · program_id=' . $program_id, self::url( 'mmc-field', $program_id ) );

        $out[] = self::school_bridge_row( $program_id );

        $kommo = self::one_where( 'mmc_kommo_profiles', 'program_id', $program_id );
        $kommo_mismatch = $kommo && $event && (int) $kommo->event_id && (int) $kommo->event_id !== (int) $event->id;
        $out[] = self::row( 'kommo', 'Kommo / AI', $kommo_mismatch ? 'critical' : ( $kommo ? 'ok' : 'warning' ), $kommo ? ( 'Profil #' . (int)$kommo->id . ' · ' . $kommo->crm_status . ( $kommo_mismatch ? ' · EVENT UYUŞMUYOR' : '' ) ) : 'Program profili henüz oluşturulmadı.', self::url( 'mmc-kommo', $program_id ) );

        $operation = self::one_where( 'mmc_operation_plans', 'program_id', $program_id );
        $out[] = self::row( 'operations', 'Operasyon & Lojistik', $operation ? 'ok' : 'warning', $operation ? ( 'Plan #' . (int)$operation->id . ' · ' . $operation->status . ' · program_id=' . $program_id ) : 'Operasyon planı henüz oluşturulmadı.', self::url( 'mmc-operations', $program_id ) );

        $entries = self::count_where( 'mmc_finance_entries', 'program_id', $program_id );
        $closure = self::one_where( 'mmc_financial_closures', 'program_id', $program_id );
        $out[] = self::row( 'finance', 'Finans & Kapanış', $closure ? 'ok' : 'warning', $entries . ' finans kaydı · kapanış: ' . ( $closure ? $closure->close_status : 'oluşturulmadı' ) . ' · program_id=' . $program_id, self::url( 'mmc-finance', $program_id ) );

        return $out;
    }

    private static function mdg_bridge_rows( $program_id, $mmc_event ) {
        if ( ! class_exists('MMC_MDG_Bridge_Service') ) {
            return array( self::row(
                'mdg_engine',
                'MDG Bilet Motoru',
                'warning',
                'MMC MDG köprü servisi yüklenemedi.',
                add_query_arg( array('page'=>'mdg-dashboard','mmc_program_id'=>$program_id), admin_url('admin.php') )
            ) );
        }

        $s = MMC_MDG_Bridge_Service::status( $program_id );
        $url = MMC_MDG_Bridge_Service::admin_url( $program_id );
        if ( empty($s['available']) ) {
            return array( self::row(
                'mdg_engine',
                'MDG Bilet Motoru',
                'warning',
                'Madagaskar Bilet Yönetimi aktif değil veya MDG tabloları erişilebilir değil.',
                $url
            ) );
        }

        $rows = array();
        $rows[] = self::row(
            'mdg_engine',
            'MDG Bilet Motoru',
            'ok',
            'Aktif' . ( ! empty($s['version']) ? ' · sürüm ' . $s['version'] : '' ) . ' · events/sessions/ticket_types/order_map erişilebilir.',
            $url
        );

        if ( empty($s['linked']) ) {
            $strong = array_values( array_filter( (array)$s['candidates'], function($row){ return ! empty($row['strong']); } ) );
            $detail = $strong
                ? count($strong) . ' güçlü MDG etkinlik adayı bulundu; Program ID henüz kalıcı bağlanmadı.'
                : 'MMC Program ID ile MDG event_id arasında kalıcı köprü bulunamadı.';
            $rows[] = self::row(
                'mdg_bridge',
                'MMC ↔ MDG Etkinlik Köprüsü',
                'warning',
                $detail,
                $url,
                1 === count($strong)
            );
            return $rows;
        }

        $bridge = $s['bridge'];
        $mdg_event = $s['event'];
        $bridge_mismatch = $mmc_event && (int)$bridge->mmc_event_id !== (int)$mmc_event->id;
        $rows[] = self::row(
            'mdg_bridge',
            'MMC ↔ MDG Etkinlik Köprüsü',
            $bridge_mismatch ? 'critical' : 'ok',
            'MMC Program #' . (int)$program_id . ' / Event #' . (int)$bridge->mmc_event_id .
            ' ↔ MDG Event #' . (int)$bridge->mdg_event_id .
            ( $mdg_event ? ' · ' . $mdg_event->title : ' · MDG etkinliği bulunamadı' ) .
            ' · yöntem: ' . $bridge->match_method .
            ( $bridge_mismatch ? ' · MMC EVENT UYUŞMUYOR' : '' ),
            $url
        );

        $expected = (int)$s['identity_expected'];
        $matched = (int)$s['identity_matched'];
        $identity_sev = $expected > 0 && $matched === $expected ? 'ok' : ( $expected > 0 ? 'critical' : 'warning' );
        $rows[] = self::row(
            'mdg_identity',
            'MDG / WooCommerce / Tickera Kimliği',
            $identity_sev,
            'Satış eşleştirmesi ' . $matched . '/' . $expected .
            ' · MMC seans ' . (int)$s['sessions_mmc'] . ' · MDG seans ' . (int)$s['sessions_mdg'] .
            ( $expected && $matched !== $expected ? ' · ÜRÜN/VARYASYON/TICKERA EŞLEŞMESİ FARKLI' : '' ),
            $url
        );

        $sales = (array)$s['sales'];
        if ( empty($sales['has_sales']) ) {
            $rows[] = self::row(
                'mdg_sales_reconcile',
                'MDG ↔ MMC Satış Mutabakatı',
                'ok',
                'Her iki defterde de ücretli satış yok; çift sayım yapılmıyor.',
                self::url('mmc-sales',$program_id)
            );
        } else {
            $sales_ok = ! empty($sales['ok']);
            $detail = 'MDG: ' . (int)$sales['mdg_orders'] . ' sipariş / ' . (int)$sales['mdg_units'] . ' kişi · ' .
                      'MMC: ' . (int)$sales['mmc_orders'] . ' sipariş / ' . (int)$sales['mmc_units'] . ' kişi';
            if ( ! $sales_ok ) {
                $detail .= ' · MMC eksik satır: ' . count((array)$sales['missing_in_mmc']) .
                           ' · MMC fazla satır: ' . count((array)$sales['extra_in_mmc']);
            }
            $detail .= ' · MDG satır toplamı KDV hariç olabilir; ciro farkı bu kontrolde kritik ölçüt değildir.';
            $rows[] = self::row(
                'mdg_sales_reconcile',
                'MDG ↔ MMC Satış Mutabakatı',
                $sales_ok ? 'ok' : 'critical',
                $detail,
                self::url('mmc-sales',$program_id)
            );
        }
        return $rows;
    }

    public static function summary( $program_id ) {
        $summary = array( 'ok'=>0,'warning'=>0,'critical'=>0,'info'=>0 );
        foreach ( self::checks( $program_id ) as $row ) {
            if ( isset( $summary[ $row['severity'] ] ) ) { $summary[ $row['severity'] ]++; }
        }
        return $summary;
    }

    public static function repair_school_bridge( $program_id ) {
        if ( ! class_exists( 'Mad_Okul_Operations' ) || ! method_exists( 'Mad_Okul_Operations', 'ensure_mmc_bridge' ) ) {
            return new WP_Error( 'mmc_school_bridge_unavailable', 'Okul Tanıtım 1.7.4+ köprüsü bulunamadı.' );
        }
        $result = Mad_Okul_Operations::ensure_mmc_bridge( absint( $program_id ) );
        if ( is_wp_error( $result ) ) { return $result; }
        MMC_Program_Service::add_log( absint($program_id), 'school_bridge_repaired', 'school_bridge', (int)$result->id, null, array( 'legacy_program_id'=>(int)$result->id ), 'Okul Tanıtım kaydı MMC Program ID ile eşleştirildi.' );
        return $result;
    }

    private static function school_bridge_row( $program_id ) {
        if ( ! class_exists( 'Mad_Okul_Operations' ) || ! method_exists( 'Mad_Okul_Operations', 'bridge_status' ) ) {
            return self::row( 'school_bridge', 'Okul Tanıtım / Rota Köprüsü', 'warning', 'Okul Tanıtım 1.7.4+ köprüsü algılanmadı.', add_query_arg( array( 'page'=>'mad-okul-route','mmc_program_id'=>$program_id ), admin_url('admin.php') ), true );
        }
        $s = Mad_Okul_Operations::bridge_status( $program_id );
        if ( is_wp_error( $s ) ) {
            return self::row( 'school_bridge', 'Okul Tanıtım / Rota Köprüsü', 'critical', $s->get_error_message(), add_query_arg( array( 'page'=>'mad-okul-route','mmc_program_id'=>$program_id ), admin_url('admin.php') ), true );
        }
        $sev = ! empty( $s['linked'] ) ? 'ok' : ( ! empty( $s['ambiguous'] ) ? 'critical' : 'warning' );
        return self::row( 'school_bridge', 'Okul Tanıtım / Rota Köprüsü', $sev, (string)($s['detail'] ?? ''), add_query_arg( array( 'page'=>'mad-okul-route','mmc_program_id'=>$program_id ), admin_url('admin.php') ), empty($s['linked']) );
    }

    private static function row( $key, $label, $severity, $detail, $url = '', $repairable = false ) {
        return array( 'key'=>$key, 'label'=>$label, 'severity'=>$severity, 'detail'=>$detail, 'url'=>$url, 'repairable'=>$repairable );
    }

    private static function url( $page, $program_id ) {
        return add_query_arg( array( 'page'=>$page, 'program_id'=>absint($program_id) ), admin_url( 'admin.php' ) );
    }

    private static function one_where( $suffix, $field, $value ) {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        if ( ! self::table_exists( $table ) ) { return null; }
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE $field=%d ORDER BY id DESC LIMIT 1", absint($value) ) );
    }

    private static function count_where( $suffix, $field, $value, $extra = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        if ( ! self::table_exists( $table ) ) { return 0; }
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $field=%d $extra", absint($value) ) );
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }
}
