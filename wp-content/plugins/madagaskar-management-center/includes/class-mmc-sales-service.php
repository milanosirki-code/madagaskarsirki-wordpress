<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce/PayTR satış verisini MMC Program Dosyasına bağlayan güvenli okuma-senkron katmanı.
 * HPOS uyumluluğu için siparişler WooCommerce CRUD API üzerinden okunur.
 * Tickera tarafında ürün/etkinlik kimlikleri eşleştirilir; bilet/QR/check-in yönetimi Tickera'da kalır.
 */
class MMC_Sales_Service {

    public static function hooks() {
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'sync_order' ), 20, 1 );
        add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status_changed' ), 20, 4 );
        add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_order_refunded' ), 20, 2 );
    }

    public static function woocommerce_available() {
        return function_exists( 'wc_get_order' ) && function_exists( 'wc_get_orders' );
    }

    public static function bridge_detected() {
        return class_exists( 'TC' ) || defined( 'TC_VERSION' ) || post_type_exists( 'tc_events' );
    }

    public static function paytr_gateway_status() {
        $out = array( 'detected'=>false, 'enabled'=>false, 'id'=>'', 'title'=>'' );
        if ( ! self::woocommerce_available() || ! function_exists( 'WC' ) || ! WC() ) return $out;
        try {
            $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
            foreach ( (array) $gateways as $id => $gateway ) {
                $title = is_object( $gateway ) && method_exists( $gateway, 'get_title' ) ? (string) $gateway->get_title() : '';
                $haystack = strtolower( $id . ' ' . $title . ' ' . get_class( $gateway ) );
                if ( false !== strpos( $haystack, 'paytr' ) ) {
                    $out = array(
                        'detected' => true,
                        'enabled'  => is_object( $gateway ) && isset( $gateway->enabled ) ? 'yes' === $gateway->enabled : true,
                        'id'       => sanitize_key( $id ),
                        'title'    => sanitize_text_field( $title ),
                    );
                    break;
                }
            }
        } catch ( Throwable $e ) {
            // Yönetim ekranı hata vermesin; durum bilinmiyor olarak kalır.
        }
        return $out;
    }

    public static function mappings( $event_id ) {
        global $wpdb;
        $m = $wpdb->prefix . 'mmc_sales_mappings';
        $s = $wpdb->prefix . 'mmc_sessions';
        $t = $wpdb->prefix . 'mmc_ticket_types';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT m.*, s.session_time, s.capacity, t.ticket_code, t.ticket_name, t.capacity_units, t.price
             FROM $m m
             INNER JOIN $s s ON s.id=m.session_id
             INNER JOIN $t t ON t.id=m.ticket_type_id
             WHERE m.event_id=%d
             ORDER BY s.session_time ASC, t.sort_order ASC, t.id ASC",
            absint( $event_id )
        ) );
    }

    public static function get_mapping( $mapping_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_sales_mappings WHERE id=%d LIMIT 1", absint( $mapping_id ) ) );
    }

    public static function save_mapping( $event_id, $session_id, $ticket_type_id, $data ) {
        global $wpdb;
        $event = MMC_Event_Service::get_event( $event_id );
        if ( ! $event ) return new WP_Error( 'mmc_sales_event_missing', 'Etkinlik bulunamadı.' );

        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_sessions WHERE id=%d AND event_id=%d LIMIT 1", absint($session_id), absint($event_id) ) );
        $ticket  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_ticket_types WHERE id=%d AND event_id=%d LIMIT 1", absint($ticket_type_id), absint($event_id) ) );
        if ( ! $session || ! $ticket ) return new WP_Error( 'mmc_sales_mapping_invalid', 'Seans veya bilet türü bu etkinliğe ait değil.' );

        $wc_product_id       = absint( $data['wc_product_id'] ?? 0 );
        $wc_variation_id     = absint( $data['wc_variation_id'] ?? 0 );
        $tickera_event_id    = absint( $data['tickera_event_id'] ?? 0 );
        $tickera_ticket_id   = absint( $data['tickera_ticket_type_id'] ?? 0 );
        $active              = ! empty( $data['is_active'] ) ? 1 : 0;

        if ( $wc_product_id && self::woocommerce_available() ) {
            $product = wc_get_product( $wc_product_id );
            if ( ! $product ) return new WP_Error( 'mmc_sales_wc_product', 'WooCommerce ürün ID bulunamadı.' );
        }
        if ( $wc_variation_id && self::woocommerce_available() ) {
            $variation = wc_get_product( $wc_variation_id );
            if ( ! $variation ) return new WP_Error( 'mmc_sales_wc_variation', 'WooCommerce varyasyon ID bulunamadı.' );
            if ( $wc_product_id && method_exists( $variation, 'get_parent_id' ) && (int)$variation->get_parent_id() !== $wc_product_id ) {
                return new WP_Error( 'mmc_sales_wc_parent', 'Varyasyon seçilen WooCommerce ürününe ait değil.' );
            }
        }
        if ( $tickera_event_id && post_type_exists( 'tc_events' ) && 'tc_events' !== get_post_type( $tickera_event_id ) ) {
            return new WP_Error( 'mmc_sales_tickera_event', 'Tickera etkinlik ID doğrulanamadı.' );
        }

        $table = $wpdb->prefix . 'mmc_sales_mappings';
        if ( $active && $wc_variation_id ) {
            $owner = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT program_id FROM $table WHERE wc_variation_id=%d AND is_active=1 AND program_id<>%d LIMIT 1",
                $wc_variation_id, (int) $event->program_id
            ) );
            if ( $owner ) return new WP_Error( 'mmc_sales_variation_conflict', 'WooCommerce varyasyonu başka bir MMC programına bağlı: #' . $owner );
        }
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE event_id=%d AND session_id=%d AND ticket_type_id=%d LIMIT 1",
            $event_id, $session_id, $ticket_type_id
        ) );
        $row = array(
            'program_id'             => (int) $event->program_id,
            'event_id'               => absint( $event_id ),
            'session_id'             => absint( $session_id ),
            'ticket_type_id'         => absint( $ticket_type_id ),
            'wc_product_id'          => $wc_product_id ?: null,
            'wc_variation_id'        => $wc_variation_id ?: null,
            'tickera_event_id'       => $tickera_event_id ?: null,
            'tickera_ticket_type_id' => $tickera_ticket_id ?: null,
            'is_active'              => $active,
            'updated_at'             => current_time( 'mysql' ),
        );
        if ( $existing ) {
            $ok = $wpdb->update( $table, $row, array( 'id'=>(int)$existing ) );
            $mapping_id = (int) $existing;
        } else {
            $row['created_at'] = current_time( 'mysql' );
            $ok = $wpdb->insert( $table, $row );
            $mapping_id = (int) $wpdb->insert_id;
        }
        if ( false === $ok ) return new WP_Error( 'mmc_sales_mapping_save', 'Satış eşleştirmesi kaydedilemedi.' );

        self::refresh_integration_health( $event_id );
        MMC_Program_Service::add_log( $event->program_id, 'sales_mapping_saved', 'sales_mapping', $mapping_id, null, array(
            'session_id'=>$session_id, 'ticket_type_id'=>$ticket_type_id, 'wc_product_id'=>$wc_product_id,
            'wc_variation_id'=>$wc_variation_id, 'tickera_event_id'=>$tickera_event_id,
        ), $ticket->ticket_name );
        return $mapping_id;
    }

    /** Validate every session and product identity before importing a live MDG sales source. */
    public static function legacy_import_preview( $event_id, $legacy_event_id ) {
        global $wpdb;
        $errors=array(); $rows=array();
        $event=MMC_Event_Service::get_event(absint($event_id));
        $legacy=class_exists('MMC_MDG_Bridge_Service') ? MMC_MDG_Bridge_Service::get_mdg_event(absint($legacy_event_id)) : null;
        if(!$event || !$legacy || !class_exists('MDG_DB')){
            return array('ready'=>false,'errors'=>array('MMC veya MDG etkinliği bulunamadı.'),'rows'=>array());
        }
        $program=MMC_Program_Service::get_program((int)$event->program_id);
        if(!$program || 'draft'===(string)$legacy->status){$errors[]='Taslak MDG etkinliğinden canlı satış kimliği alınamaz.';}
        if($program && (sanitize_title((string)$program->province_name)!==sanitize_title((string)$legacy->province_name)
            || sanitize_title((string)$program->district_name)!==sanitize_title((string)$legacy->district))){$errors[]='İl veya ilçe uyuşmuyor.';}
        $mmc_sessions=array();
        foreach((array)MMC_Event_Service::sessions((int)$event->id) as $session){
            $mmc_sessions[substr((string)$session->session_time,0,16)]=$session;
        }
        $tickets=array();
        foreach((array)MMC_Event_Service::ticket_types((int)$event->id) as $ticket){
            if((int)$ticket->is_active){$tickets[sanitize_key((string)$ticket->ticket_code)]=$ticket;}
        }
        $sessions_table=MDG_DB::table('sessions'); $types_table=MDG_DB::table('ticket_types');
        $legacy_sessions=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM {$sessions_table} WHERE event_id=%d ORDER BY start_at,id",absint($legacy_event_id)));
        if(count($legacy_sessions)!==count($mmc_sessions)){$errors[]='Seans sayısı uyuşmuyor.';}
        $used_sessions=array();
        foreach($legacy_sessions as $session){
            $local=get_date_from_gmt((string)$session->start_at,'Y-m-d H:i');
            $target=$mmc_sessions[$local]??null;
            if(!$target){$errors[]='MMC seansı bulunamadı: '.$local;continue;}
            $used_sessions[$local]=true;
            $product_id=(int)$session->wc_product_id;
            if(!$product_id || !function_exists('wc_get_product') || !wc_get_product($product_id)){$errors[]='WooCommerce ana ürün bulunamadı: '.$local;continue;}
            $types=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM {$types_table} WHERE session_id=%d AND is_active=1",(int)$session->id));
            foreach($types as $type){
                $code=sanitize_key((string)$type->code);
                $ticket=$tickets[$code]??null;
                if(!$ticket){$errors[]='MMC bilet kodu bulunamadı: '.$code;continue;}
                $variation_id=(int)$type->wc_variation_id;
                $variation=$variation_id?wc_get_product($variation_id):null;
                if(!$variation || !method_exists($variation,'get_parent_id') || (int)$variation->get_parent_id()!==$product_id){$errors[]='Ürün/varyasyon uyuşmuyor: '.$local.' '.$code;continue;}
                $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_sales_mappings WHERE event_id=%d AND session_id=%d AND ticket_type_id=%d LIMIT 1",(int)$event->id,(int)$target->id,(int)$ticket->id));
                if($existing && (int)$existing->is_active && (int)$existing->wc_variation_id && (int)$existing->wc_variation_id!==$variation_id){$errors[]='Mevcut MMC eşleştirmesi farklı: '.$local.' '.$code;}
                $other=$wpdb->get_var($wpdb->prepare("SELECT program_id FROM {$wpdb->prefix}mmc_sales_mappings WHERE is_active=1 AND wc_variation_id=%d AND program_id<>%d LIMIT 1",$variation_id,(int)$event->program_id));
                if($other){$errors[]='Varyasyon başka bir programa bağlı: '.$variation_id;}
                $rows[]=array('session_id'=>(int)$target->id,'ticket_type_id'=>(int)$ticket->id,'time'=>$local,'ticket_name'=>(string)$ticket->ticket_name,'product_id'=>$product_id,'variation_id'=>$variation_id,'tickera_event_id'=>(int)$session->tickera_event_id);
            }
        }
        if(count($used_sessions)!==count($mmc_sessions)){$errors[]='MMC seanslarından biri MDG kaydında yok.';}
        if(!$rows){$errors[]='Aktarılabilir bilet türü bulunamadı.';}
        return array('ready'=>!$errors,'errors'=>array_values(array_unique($errors)),'rows'=>$rows,'legacy_title'=>(string)$legacy->title,'legacy_status'=>(string)$legacy->status);
    }

    public static function import_legacy_mdg_event( $event_id, $legacy_event_id ) {
        $preview=self::legacy_import_preview($event_id,$legacy_event_id);
        if(!$preview['ready']){return new WP_Error('mmc_legacy_unsafe',implode(' ',(array)$preview['errors']));}
        $event=MMC_Event_Service::get_event($event_id);
        $imported=0;
        foreach($preview['rows'] as $row){
            $saved=self::save_mapping($event_id,$row['session_id'],$row['ticket_type_id'],array(
                'wc_product_id'=>$row['product_id'],'wc_variation_id'=>$row['variation_id'],
                'tickera_event_id'=>$row['tickera_event_id'],'is_active'=>1,
            ));
            if(is_wp_error($saved)){return $saved;}
            $imported++;
        }
        MMC_Program_Service::add_log($event->program_id,'legacy_sales_mappings_imported','event',$event_id,null,array('legacy_event_id'=>absint($legacy_event_id),'imported'=>$imported),'Canlı MDG satış kimlikleri doğrulanarak MMC eşleştirmesine alındı.');
        return array('imported'=>$imported,'warnings'=>array());
    }

    public static function on_order_status_changed( $order_id, $from, $to, $order = null ) {
        self::sync_order( $order_id );
    }

    public static function on_order_refunded( $order_id, $refund_id ) {
        self::sync_order( $order_id );
    }

    public static function sync_order( $order_id ) {
        if ( ! self::woocommerce_available() ) return false;
        $order = wc_get_order( absint( $order_id ) );
        if ( ! $order ) return false;

        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            $product_id   = absint( $item->get_product_id() );
            $variation_id = absint( $item->get_variation_id() );
            $mapping = self::mapping_for_product( $product_id, $variation_id );
            if ( ! $mapping ) continue;
            self::upsert_ledger_item( $order, $item_id, $item, $mapping );
        }
        return true;
    }

    private static function mapping_for_product( $product_id, $variation_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_sales_mappings';
        if ( $variation_id ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE is_active=1 AND wc_variation_id=%d ORDER BY id ASC LIMIT 1", absint($variation_id) ) );
            if ( $row ) return $row;
        }
        if ( $product_id ) {
            return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE is_active=1 AND wc_product_id=%d AND (wc_variation_id IS NULL OR wc_variation_id=0) ORDER BY id ASC LIMIT 1", absint($product_id) ) );
        }
        return null;
    }

    private static function upsert_ledger_item( $order, $item_id, $item, $mapping ) {
        global $wpdb;
        $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_ticket_types WHERE id=%d LIMIT 1", (int)$mapping->ticket_type_id ) );
        if ( ! $ticket ) return;

        $qty = max( 0, (int) $item->get_quantity() );
        $ref_qty = 0;
        if ( method_exists( $order, 'get_qty_refunded_for_item' ) ) $ref_qty = abs( (int) $order->get_qty_refunded_for_item( $item_id ) );
        $net_qty = max( 0, $qty - $ref_qty );

        $gross = (float) $item->get_total() + (float) $item->get_total_tax();
        $ref_amount = 0.0;
        if ( method_exists( $order, 'get_total_refunded_for_item' ) ) $ref_amount += abs( (float) $order->get_total_refunded_for_item( $item_id ) );
        if ( method_exists( $order, 'get_tax_refunded_for_item' ) ) $ref_amount += abs( (float) $order->get_tax_refunded_for_item( $item_id ) );
        $paid_at_obj = method_exists( $order, 'get_date_paid' ) ? $order->get_date_paid() : null;
        $was_paid = (bool) $paid_at_obj;
        $net_amount = $was_paid ? max( 0, $gross - $ref_amount ) : 0;
        $counted_qty = $was_paid ? $net_qty : 0;
        $capacity = $counted_qty * max( 1, (int)$ticket->capacity_units );

        $method_id = sanitize_key( (string)$order->get_payment_method() );
        $method_title = sanitize_text_field( (string)$order->get_payment_method_title() );
        $table = $wpdb->prefix . 'mmc_sales_ledger';
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE channel='woocommerce' AND external_order_item_id=%d LIMIT 1", absint($item_id) ) );
        $now = current_time( 'mysql' );
        $row = array(
            'program_id'             => (int)$mapping->program_id,
            'event_id'               => (int)$mapping->event_id,
            'session_id'             => (int)$mapping->session_id,
            'ticket_type_id'         => (int)$mapping->ticket_type_id,
            'channel'                => 'woocommerce',
            'external_order_id'      => (int)$order->get_id(),
            'external_order_item_id' => absint($item_id),
            'order_status'           => sanitize_key( $order->get_status() ),
            'payment_method'         => $method_id,
            'payment_method_title'   => $method_title,
            'quantity'               => $qty,
            'refunded_quantity'      => $ref_qty,
            'net_quantity'           => $counted_qty,
            'capacity_units'         => $capacity,
            'gross_amount'           => round( $gross, 2 ),
            'refunded_amount'        => round( $ref_amount, 2 ),
            'net_amount'             => round( $net_amount, 2 ),
            'paid_at'                => $paid_at_obj ? $paid_at_obj->date( 'Y-m-d H:i:s' ) : null,
            'last_synced_at'         => $now,
            'updated_at'             => $now,
        );
        if ( $existing ) {
            $wpdb->update( $table, $row, array( 'id'=>(int)$existing ) );
        } else {
            $row['created_at'] = $now;
            $wpdb->insert( $table, $row );
        }

        if ( $was_paid && self::looks_like_paytr( $method_id, $method_title ) ) {
            self::set_integration_live( (int)$mapping->event_id, 'paytr', $method_id, '', 'Başarılı WooCommerce siparişinde PayTR ödeme yöntemi algılandı.' );
        }
        self::set_integration_live( (int)$mapping->event_id, 'woocommerce', (string)$mapping->wc_product_id, '', 'WooCommerce sipariş verisi MMC satış defterine senkronlandı.' );
    }

    private static function looks_like_paytr( $id, $title ) {
        return false !== strpos( strtolower( (string)$id . ' ' . (string)$title ), 'paytr' );
    }

    public static function sync_event_orders( $event_id, $lookback_days = 365 ) {
        if ( ! self::woocommerce_available() ) return new WP_Error( 'mmc_wc_missing', 'WooCommerce etkin değil.' );
        $event = MMC_Event_Service::get_event( $event_id );
        if ( ! $event ) return new WP_Error( 'mmc_sync_event', 'Etkinlik bulunamadı.' );
        $lookback_days = max( 1, min( 1500, absint( $lookback_days ) ?: 365 ) );

        $page = 1; $processed = 0; $matched_before = self::ledger_count( $event_id );
        do {
            $result = wc_get_orders( array(
                'limit'        => 100,
                'paged'        => $page,
                'paginate'     => true,
                'orderby'      => 'date',
                'order'        => 'DESC',
                'date_created' => '>' . ( time() - DAY_IN_SECONDS * $lookback_days ),
                'return'       => 'objects',
            ) );
            foreach ( (array)$result->orders as $order ) {
                self::sync_order( $order->get_id() );
                $processed++;
            }
            $page++;
        } while ( $page <= (int)$result->max_num_pages );

        self::refresh_integration_health( $event_id );
        $matched_after = self::ledger_count( $event_id );
        MMC_Program_Service::add_log( $event->program_id, 'sales_manual_sync', 'event', $event_id, null, array( 'orders_scanned'=>$processed, 'ledger_before'=>$matched_before, 'ledger_after'=>$matched_after ), 'WooCommerce satışları manuel senkronlandı.' );
        return array( 'scanned'=>$processed, 'matched'=>$matched_after );
    }

    private static function ledger_count( $event_id ) {
        global $wpdb;
        return (int)$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_sales_ledger WHERE event_id=%d", absint($event_id) ) );
    }

    public static function summary( $event_id ) {
        global $wpdb;
        $ledger = $wpdb->prefix . 'mmc_sales_ledger';
        $base = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(DISTINCT external_order_id) orders_count,
                    COALESCE(SUM(net_quantity),0) ticket_count,
                    COALESCE(SUM(capacity_units),0) sold_capacity,
                    COALESCE(SUM(gross_amount),0) gross_revenue,
                    COALESCE(SUM(refunded_amount),0) refunded_amount,
                    COALESCE(SUM(net_amount),0) net_revenue
             FROM $ledger WHERE event_id=%d",
            absint($event_id)
        ), ARRAY_A );
        $failed = (int)$wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT external_order_id) FROM $ledger WHERE event_id=%d AND order_status IN ('failed','cancelled')",
            absint($event_id)
        ) );
        $base['failed_orders'] = $failed;
        return $base;
    }

    public static function session_summary( $event_id ) {
        global $wpdb;
        $sessions = MMC_Event_Service::sessions( $event_id );
        $ledger = $wpdb->prefix . 'mmc_sales_ledger';
        $out = array();
        foreach ( $sessions as $s ) {
            $stats = $wpdb->get_row( $wpdb->prepare(
                "SELECT COALESCE(SUM(net_quantity),0) tickets, COALESCE(SUM(capacity_units),0) sold_capacity, COALESCE(SUM(net_amount),0) revenue
                 FROM $ledger WHERE event_id=%d AND session_id=%d",
                absint($event_id), (int)$s->id
            ) );
            $sold = (int)$stats->sold_capacity;
            $capacity = (int)$s->capacity;
            $out[] = array(
                'session'=>$s,
                'tickets'=>(int)$stats->tickets,
                'sold_capacity'=>$sold,
                'remaining'=>max(0,$capacity-$sold),
                'occupancy'=>$capacity>0 ? round( ($sold/$capacity)*100, 1 ) : 0,
                'revenue'=>(float)$stats->revenue,
            );
        }
        return $out;
    }

    public static function recent_orders( $event_id, $limit = 20 ) {
        global $wpdb;
        $l = $wpdb->prefix . 'mmc_sales_ledger';
        $s = $wpdb->prefix . 'mmc_sessions';
        $t = $wpdb->prefix . 'mmc_ticket_types';
        $limit = max(1,min(100,absint($limit)));
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, s.session_time, t.ticket_name
             FROM $l l INNER JOIN $s s ON s.id=l.session_id INNER JOIN $t t ON t.id=l.ticket_type_id
             WHERE l.event_id=%d ORDER BY l.updated_at DESC LIMIT %d",
            absint($event_id), $limit
        ) );
    }

    public static function mapping_coverage( $event_id ) {
        global $wpdb;
        $sessions = MMC_Event_Service::sessions( $event_id );
        $tickets  = array_filter( MMC_Event_Service::ticket_types( $event_id ), function($t){ return (int)$t->is_active===1; } );
        $required = count($sessions) * count($tickets);
        $mapped = (int)$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_sales_mappings WHERE event_id=%d AND is_active=1 AND wc_product_id IS NOT NULL AND wc_product_id>0", absint($event_id) ) );
        return array( 'required'=>$required, 'mapped'=>$mapped, 'complete'=>$required>0 && $mapped >= $required );
    }

    public static function refresh_integration_health( $event_id ) {
        $coverage = self::mapping_coverage( $event_id );
        if ( $coverage['complete'] ) self::set_integration_state( $event_id, 'woocommerce', 'verified', '', '', 'Tüm aktif seans/bilet kombinasyonları WooCommerce ürünlerine eşlendi.' );

        $maps = self::mappings( $event_id );
        $tickera_ok = ! empty($maps);
        foreach ( $maps as $m ) {
            if ( ! (int)$m->tickera_event_id ) { $tickera_ok=false; break; }
            if ( post_type_exists('tc_events') && 'tc_events' !== get_post_type((int)$m->tickera_event_id) ) { $tickera_ok=false; break; }
        }
        if ( $tickera_ok ) self::set_integration_state( $event_id, 'tickera', 'verified', '', '', 'Satış eşleştirmelerindeki Tickera etkinlikleri doğrulandı.' );

        $paytr = self::paytr_gateway_status();
        if ( $paytr['detected'] && $paytr['enabled'] ) self::set_integration_state( $event_id, 'paytr', 'prepared', $paytr['id'], '', 'PayTR ödeme geçidi WooCommerce içinde aktif algılandı; ilk başarılı ödeme canlı doğrulama sayılır.' );
    }

    private static function set_integration_live( $event_id, $channel, $external_id='', $url='', $note='' ) {
        self::set_integration_state( $event_id, $channel, 'live', $external_id, $url, $note );
    }

    private static function set_integration_state( $event_id, $channel, $status, $external_id='', $url='', $note='' ) {
        global $wpdb;
        $event = MMC_Event_Service::get_event($event_id); if(!$event) return;
        $table = $wpdb->prefix.'mmc_integration_status';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE event_id=%d AND channel=%s LIMIT 1",absint($event_id),sanitize_key($channel)));
        if(!$row) return;
        $rank = array('pending'=>0,'prepared'=>1,'verified'=>2,'live'=>3,'not_used'=>4,'error'=>-1);
        // Otomatik sağlık kontrolü manuel olarak daha ileri taşınmış bir durumu geriye çekmesin.
        if(isset($rank[$row->status],$rank[$status]) && $rank[$row->status] > $rank[$status] && 'error'!==$row->status) return;
        $wpdb->update($table,array(
            'status'=>$status,
            'external_id'=>$external_id ?: $row->external_id,
            'external_url'=>$url ?: $row->external_url,
            'notes'=>$note ?: $row->notes,
            'last_synced_at'=>current_time('mysql'),
            'updated_at'=>current_time('mysql'),
        ),array('id'=>(int)$row->id));
        self::evaluate_sales_open( $event_id );
    }

    private static function evaluate_sales_open( $event_id ) {
        global $wpdb;
        $event = MMC_Event_Service::get_event( $event_id ); if ( ! $event ) return;
        $rows = MMC_Event_Service::integrations( $event_id );
        $required = array( 'woocommerce','tickera','paytr' );
        foreach ( $required as $channel ) {
            $ok = false;
            foreach ( $rows as $r ) {
                if ( $r->channel === $channel && in_array( $r->status, array( 'verified','live' ), true ) ) { $ok = true; break; }
            }
            if ( ! $ok ) return;
        }
        $wpdb->update( $wpdb->prefix.'mmc_events', array( 'status'=>'sales_open','updated_at'=>current_time('mysql') ), array( 'id'=>absint($event_id) ) );
        MMC_Program_Service::set_status( $event->program_id, 'sales_open', 'WooCommerce, Tickera ve PayTR gerçek satış entegrasyonu doğrulandı.' );
    }
}
