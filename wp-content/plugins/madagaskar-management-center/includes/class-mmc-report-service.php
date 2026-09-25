<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nightly executive report engine.
 *
 * The report is deliberately based on MMC's own ledgers/snapshots instead of
 * scraping public pages. It sends an HTML email that remains easy for both a
 * human manager and the morning ChatGPT workflow to read.
 */
class MMC_Report_Service {
    const CRON_HOOK = 'mmc_nightly_report_cron';
    const DEFAULT_EMAIL = 'milanosirki@gmail.com';
    const DEFAULT_HOUR = 2;

    public static function hooks() {
        add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
        add_action( self::CRON_HOOK, array( __CLASS__, 'cron_run' ) );
    }

    public static function recipient() {
        $email = sanitize_email( (string) get_option( 'mmc_nightly_report_email', self::DEFAULT_EMAIL ) );
        return $email ?: self::DEFAULT_EMAIL;
    }

    public static function report_hour() {
        $hour = (int) get_option( 'mmc_nightly_report_hour', self::DEFAULT_HOUR );
        return max( 0, min( 23, $hour ) );
    }

    public static function ensure_schedule() {
        if ( wp_next_scheduled( self::CRON_HOOK ) ) { return; }
        self::schedule_next();
    }

    public static function schedule_next() {
        $tz = wp_timezone();
        $now = new DateTimeImmutable( 'now', $tz );
        $next = new DateTimeImmutable( $now->format( 'Y-m-d' ) . ' ' . sprintf( '%02d:00:00', self::report_hour() ), $tz );
        if ( $next <= $now ) { $next = $next->modify( '+1 day' ); }
        wp_schedule_event( $next->getTimestamp(), 'daily', self::CRON_HOOK );
        return $next->getTimestamp();
    }

    public static function reschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        return self::schedule_next();
    }

    public static function next_run() {
        return wp_next_scheduled( self::CRON_HOOK );
    }

    public static function cron_run() {
        $date = self::yesterday_date();
        self::generate_and_send( $date, 'cron', false );
    }

    public static function yesterday_date() {
        return current_datetime()->modify( '-1 day' )->format( 'Y-m-d' );
    }

    public static function generate_and_send( $report_date = '', $trigger = 'manual', $force = false ) {
        global $wpdb;
        $report_date = self::normalize_date( $report_date ?: self::yesterday_date() );
        if ( ! $report_date ) { return new WP_Error( 'mmc_report_date', 'Rapor tarihi geçersiz.' ); }

        $runs = $wpdb->prefix . 'mmc_report_runs';
        if ( ! $force ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $runs WHERE report_date=%s AND status='sent' ORDER BY id DESC LIMIT 1",
                $report_date
            ) );
            if ( $existing ) { return array( 'skipped'=>true, 'run_id'=>(int)$existing, 'message'=>'Bu tarih için rapor daha önce gönderilmiş.' ); }
        }

        $snapshot = self::snapshot( $report_date );
        $subject  = 'MADAGASKAR GECE RAPORU — ' . wp_date( 'd.m.Y', strtotime( $report_date ) );
        $html     = self::render_html( $snapshot );
        $recipient = self::recipient();
        $now = current_time( 'mysql' );

        $row = array(
            'report_date'   => $report_date,
            'generated_at'  => $now,
            'sent_at'       => null,
            'recipient'     => $recipient,
            'subject'       => $subject,
            'status'        => 'generated',
            'trigger_type'  => sanitize_key( $trigger ),
            'body_html'     => $html,
            'snapshot_json' => wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'error_message' => '',
            'created_by'    => get_current_user_id() ?: null,
            'created_at'    => $now,
            'updated_at'    => $now,
        );
        $wpdb->insert( $runs, $row );
        $run_id = (int) $wpdb->insert_id;

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $ok = wp_mail( $recipient, $subject, $html, $headers );
        if ( $ok ) {
            $wpdb->update( $runs, array( 'status'=>'sent', 'sent_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql') ), array( 'id'=>$run_id ) );
            return array( 'sent'=>true, 'run_id'=>$run_id, 'subject'=>$subject, 'recipient'=>$recipient );
        }

        $error = 'WordPress wp_mail() gönderim isteğini kabul etmedi. Site e-posta/SMTP yapılandırmasını kontrol edin.';
        $wpdb->update( $runs, array( 'status'=>'failed', 'error_message'=>$error, 'updated_at'=>current_time('mysql') ), array( 'id'=>$run_id ) );
        return new WP_Error( 'mmc_report_mail_failed', $error );
    }

    public static function send_test_email() {
        $recipient = self::recipient();
        $subject = 'MMC E-POSTA TESTİ — ' . wp_date( 'd.m.Y H:i' );
        $body = '<div style="font-family:Arial,sans-serif"><h2>Madagaskar Management Center</h2><p>Bu mesaj gece raporu e-posta kanalının testidir.</p><p><strong>Alıcı:</strong> ' . esc_html( $recipient ) . '<br><strong>Tarih:</strong> ' . esc_html( wp_date('d.m.Y H:i:s') ) . '</p><p>Bu konu başlığı sabah ChatGPT gece raporu aramasına dahil edilmez.</p></div>';
        $ok = wp_mail( $recipient, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        return $ok ? true : new WP_Error( 'mmc_report_test_failed', 'Test e-postası wp_mail() tarafından kabul edilmedi.' );
    }

    public static function snapshot( $report_date ) {
        global $wpdb;
        $report_date = self::normalize_date( $report_date );
        $prev_date = wp_date( 'Y-m-d', strtotime( $report_date . ' -1 day' ) );
        $day = self::daily_sales( $report_date );
        $prev = self::daily_sales( $prev_date );
        $woo = self::woocommerce_sales( $report_date );
        $woo_previous = self::woocommerce_sales( $prev_date );
        $legacy_events = self::upcoming_mdg_events( $report_date );
        $issues = self::daily_issues( $report_date );
        $rows = MMC_Dashboard_Service::program_rows( array() );
        $daily_by_program = self::daily_sales_by_program( $report_date );
        $meta_totals = self::meta_totals();
        $prior_snapshot = self::previous_snapshot( $report_date );
        $prior_meta = isset( $prior_snapshot['meta_totals'] ) && is_array( $prior_snapshot['meta_totals'] ) ? $prior_snapshot['meta_totals'] : array();

        $programs = array();
        foreach ( $rows as $row ) {
            $p = $row['program'];
            $pid = (int) $p->id;
            $event_id = (int) ( $p->event_id ?? 0 );
            $sessions = array();
            if ( $event_id ) {
                foreach ( MMC_Sales_Service::session_summary( $event_id ) as $ss ) {
                    $sessions[] = array(
                        'time' => (string) $ss['session']->session_time,
                        'capacity' => (int) $ss['session']->capacity,
                        'sold_capacity' => (int) $ss['sold_capacity'],
                        'remaining' => (int) $ss['remaining'],
                        'occupancy' => (float) $ss['occupancy'],
                        'revenue' => (float) $ss['revenue'],
                    );
                }
            }
            $programs[] = array(
                'program_id' => $pid,
                'program_code' => (string) $p->program_code,
                'location' => trim( (string)$p->province_name . ' / ' . (string)$p->district_name, ' /' ),
                'status' => (string) $p->status,
                'event_date' => $row['event_date'],
                'days_to_show' => $row['days_to_show'],
                'daily_sales' => $daily_by_program[ $pid ] ?? self::empty_sales(),
                'sales' => array(
                    'ticket_count'=>(int)($row['sales']['ticket_count']??0),
                    'sold_capacity'=>(int)($row['sales']['sold_capacity']??0),
                    'capacity'=>(int)($row['sales']['capacity']??0),
                    'occupancy'=>(float)($row['sales']['occupancy']??0),
                    'net_revenue'=>(float)($row['sales']['net_revenue']??0),
                ),
                'sessions' => $sessions,
                'field' => array(
                    'target_schools'=>(int)($row['field']['target_schools']??0),
                    'visited_schools'=>(int)($row['field']['visited_schools']??0),
                    'visit_percent'=>(float)($row['field']['visit_percent']??0),
                    'reported_students'=>(int)($row['field']['reported_students']??0),
                ),
                'operations' => array(
                    'pre_percent'=>(float)($row['operations']['pre_percent']??0),
                    'pre_done'=>(int)($row['operations']['pre_done']??0),
                    'pre_total'=>(int)($row['operations']['pre_total']??0),
                    'problems'=>(int)($row['operations']['problems']??0),
                ),
                'finance' => array(
                    'revenue'=>(float)($row['finance']['revenue']??0),
                    'expense'=>(float)($row['finance']['expense']??0),
                    'profit'=>(float)($row['finance']['profit']??0),
                    'margin'=>(float)($row['finance']['margin']??0),
                    'pending_invoices'=>(int)($row['finance']['pending_invoices']??0),
                    'deposit_outstanding'=>(float)($row['finance']['deposit_outstanding']??0),
                ),
                'meta' => $row['meta'] ? array(
                    'status'=>(string)$row['meta']->status,
                    'spend'=>(float)$row['meta']->spend,
                    'purchases'=>(int)$row['meta']->purchases,
                    'revenue'=>(float)$row['meta']->revenue,
                    'cpa'=>(float)$row['meta']->cpa,
                    'roas'=>(float)$row['meta']->roas,
                ) : null,
                'risk_level' => (string) $row['risk_level'],
                'risks' => $row['risks'],
            );
        }

        $alerts = MMC_Dashboard_Service::critical_alerts( 20 );
        $priorities = array_slice( $alerts, 0, 3 );
        if ( count( $priorities ) < 3 ) {
            $extra = self::fallback_priorities( 3 - count( $priorities ) );
            foreach ( $extra as $item ) { $priorities[] = $item; }
        }

        $pending_invoices = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT l.external_order_id)
             FROM {$wpdb->prefix}mmc_sales_ledger l
             LEFT JOIN {$wpdb->prefix}mmc_invoices i ON i.program_id=l.program_id AND i.source_type='woo_order' AND i.source_id=l.external_order_id
             WHERE l.order_status IN ('processing','completed') AND (i.id IS NULL OR i.status='pending')"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $open_ops = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_operation_checklist c INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=c.program_id WHERE c.status IN ('pending','problem') AND c.is_required=1 AND p.status NOT IN ('completed','cancelled')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $open_tasks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_tasks t INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=t.program_id WHERE t.status='open' AND p.status NOT IN ('completed','cancelled')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $overdue_tasks = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_tasks t INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=t.program_id WHERE t.status='open' AND t.due_at IS NOT NULL AND t.due_at<%s AND p.status NOT IN ('completed','cancelled')", current_time('mysql') ) );

        return array(
            'schema_version' => '1.3',
            'woocommerce_verification' => $woo,
            'previous_woocommerce_verification' => $woo_previous,
            'mdg_upcoming_events' => $legacy_events,
            'report_date' => $report_date,
            'generated_at' => current_time( 'mysql' ),
            'site_timezone' => wp_timezone_string(),
            'daily_sales' => $day,
            'previous_day_sales' => $prev,
            'sales_change' => self::sales_change( $day, $prev ),
            'daily_issues' => $issues,
            'pending_invoices' => $pending_invoices,
            'open_operations' => $open_ops,
            'open_tasks' => $open_tasks,
            'overdue_tasks' => $overdue_tasks,
            'meta_totals' => $meta_totals,
            'meta_delta_from_prior_report' => array(
                'spend' => isset($prior_meta['spend']) ? round( (float)$meta_totals['spend'] - (float)$prior_meta['spend'], 2 ) : null,
                'purchases' => isset($prior_meta['purchases']) ? (int)$meta_totals['purchases'] - (int)$prior_meta['purchases'] : null,
                'revenue' => isset($prior_meta['revenue']) ? round( (float)$meta_totals['revenue'] - (float)$prior_meta['revenue'], 2 ) : null,
            ),
            'programs' => $programs,
            'critical_alerts' => $alerts,
            'top_priorities' => $priorities,
        );
    }

    /** Read paid WooCommerce orders via CRUD, including HPOS stores, without changing orders. */
    private static function woocommerce_sales( $date ) {
        $result = array( 'status'=>'unavailable', 'message'=>'WooCommerce API kullanılamıyor.', 'orders_count'=>0, 'ticket_count'=>0, 'net_revenue'=>0.0, 'events'=>array() );
        if ( ! function_exists( 'wc_get_orders' ) ) { return $result; }
        $start = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
        $end = $start->modify( '+1 day' );
        $range = $start->getTimestamp() . '...' . ( $end->getTimestamp() - 1 );
        $events = array();
        $catalog = self::mdg_product_catalog();
        try {
            for ( $page=1; $page<=50; $page++ ) {
                $orders = wc_get_orders( array( 'status'=>array('processing','completed'), 'date_paid'=>$range, 'limit'=>100, 'page'=>$page, 'return'=>'objects' ) );
                if ( ! is_array($orders) ) { throw new RuntimeException('WooCommerce sipariş sorgusu sonuç vermedi.'); }
                foreach ( $orders as $order ) {
                    $paid = $order->get_date_paid();
                    if ( ! $paid || $paid->getTimestamp()<$start->getTimestamp() || $paid->getTimestamp()>=$end->getTimestamp() ) { continue; }
                    $result['orders_count']++;
                    $result['net_revenue'] += max(0,(float)$order->get_total()-(float)$order->get_total_refunded());
                    $order_events = array();
                    $order_amounts = array();
                    foreach ( $order->get_items('line_item') as $item_id=>$item ) {
                        $quantity = max(0,(int)$item->get_quantity()-(method_exists($order,'get_qty_refunded_for_item')?abs((int)$order->get_qty_refunded_for_item($item_id)):0));
                        $result['ticket_count'] += $quantity;
                        $product_id = (int)$item->get_product_id();
                        $variation_id = (int)$item->get_variation_id();
                        $match = $catalog[$variation_id] ?? $catalog[$product_id] ?? null;
                        $key = $match ? 'mdg:'.$match['id'] : 'product:'.$product_id;
                        if ( ! isset($events[$key]) ) {
                            $events[$key] = array('name'=>$match?$match['name']:(string)$item->get_name(), 'program_id'=>$match?$match['program_id']:0, 'orders_count'=>0, 'ticket_count'=>0, 'net_revenue'=>0.0);
                        }
                        $events[$key]['ticket_count'] += $quantity;
                        $order_amounts[$key] = ($order_amounts[$key]??0) + max(0,(float)$item->get_total()+(float)$item->get_total_tax()-(method_exists($order,'get_total_refunded_for_item')?abs((float)$order->get_total_refunded_for_item($item_id)):0));
                        $order_events[$key] = true;
                    }
                    $allocated = 0.0; $keys = array_keys($order_events); $weight_total = array_sum($order_amounts);
                    foreach ($keys as $index=>$key) {
                        $events[$key]['orders_count']++;
                        $order_net = max(0,(float)$order->get_total()-(float)$order->get_total_refunded());
                        $portion = $index===count($keys)-1 ? $order_net-$allocated : ($weight_total>0 ? round($order_net*$order_amounts[$key]/$weight_total,2) : 0);
                        $events[$key]['net_revenue'] += $portion; $allocated += $portion;
                    }
                }
                if (count($orders)<100) { break; }
                if ($page===50) { throw new RuntimeException('Sipariş tarama sınırı aşıldı; kısmi sonuç kullanılmaz.'); }
            }
        } catch ( Throwable $e ) {
            $result['message'] = 'WooCommerce sipariş sorgusu tamamlanamadı (' . esc_html(get_class($e)) . ', satır ' . (int)$e->getLine() . ').';
            return $result;
        }
        $result['status']='verified';
        $result['message']='Ödenmiş siparişler okundu.';
        $result['net_revenue']=round($result['net_revenue'],2);
        foreach($events as &$event){$event['net_revenue']=round($event['net_revenue'],2);}unset($event);
        $result['events']=array_values($events);
        return $result;
    }

    /** Use the MDG product and variation identities to group legacy sales. */
    private static function mdg_product_catalog() {
        global $wpdb;
        if ( ! class_exists('MMC_MDG_Bridge_Service') || ! MMC_MDG_Bridge_Service::legacy_available() ) { return array(); }
        $events=MDG_DB::table('events'); $sessions=MDG_DB::table('sessions'); $types=MDG_DB::table('ticket_types');
        $rows=$wpdb->get_results("SELECT e.id,e.title,s.wc_product_id,t.wc_variation_id FROM {$events} e JOIN {$sessions} s ON s.event_id=e.id LEFT JOIN {$types} t ON t.session_id=s.id", ARRAY_A);
        $catalog=array();
        foreach((array)$rows as $r){
            $entry=array('id'=>(int)$r['id'],'name'=>(string)$r['title'],'program_id'=>MMC_MDG_Bridge_Service::program_for_mdg_event((int)$r['id']));
            foreach(array('wc_product_id','wc_variation_id') as $column){if((int)$r[$column]){$catalog[(int)$r[$column]]=$entry;}}
        }
        return $catalog;
    }

    private static function upcoming_mdg_events( $date ) {
        global $wpdb;
        if ( ! class_exists('MMC_MDG_Bridge_Service') || ! MMC_MDG_Bridge_Service::legacy_available() ) { return array(); }
        $events=MDG_DB::table('events'); $sessions=MDG_DB::table('sessions');
        $rows=$wpdb->get_results("SELECT e.id,e.title,e.status,s.start_at FROM {$events} e JOIN {$sessions} s ON s.event_id=e.id WHERE s.start_at IS NOT NULL ORDER BY s.start_at ASC LIMIT 500", ARRAY_A);
        $out=array(); $cutoff=(new DateTimeImmutable($date.' 00:00:00',wp_timezone()))->modify('+31 days')->format('Y-m-d');
        foreach((array)$rows as $r){
            if(in_array((string)$r['status'],array('draft','cancelled','archived'),true)){continue;}
            $local=get_date_from_gmt((string)$r['start_at'],'Y-m-d');
            if($local<$date||$local>$cutoff){continue;}
            $key=(int)$r['id'];
            if(!isset($out[$key])){$out[$key]=array('id'=>$key,'name'=>(string)$r['title'],'date'=>$local,'program_id'=>MMC_MDG_Bridge_Service::program_for_mdg_event($key));}
        }
        return array_values($out);
    }

    private static function daily_sales( $date ) {
        global $wpdb;
        list( $start, $end ) = self::day_window( $date );
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(DISTINCT external_order_id) orders_count,
                    COALESCE(SUM(net_quantity),0) ticket_count,
                    COALESCE(SUM(capacity_units),0) audience_units,
                    COALESCE(SUM(net_amount),0) net_revenue
             FROM {$wpdb->prefix}mmc_sales_ledger
             WHERE paid_at >= %s AND paid_at < %s AND order_status IN ('processing','completed')",
            $start, $end
        ), ARRAY_A );
        return array(
            'orders_count'=>(int)($r['orders_count']??0),
            'ticket_count'=>(int)($r['ticket_count']??0),
            'audience_units'=>(int)($r['audience_units']??0),
            'net_revenue'=>(float)($r['net_revenue']??0),
        );
    }

    private static function daily_sales_by_program( $date ) {
        global $wpdb;
        list( $start, $end ) = self::day_window( $date );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT program_id, COUNT(DISTINCT external_order_id) orders_count,
                    COALESCE(SUM(net_quantity),0) ticket_count,
                    COALESCE(SUM(capacity_units),0) audience_units,
                    COALESCE(SUM(net_amount),0) net_revenue
             FROM {$wpdb->prefix}mmc_sales_ledger
             WHERE paid_at >= %s AND paid_at < %s AND order_status IN ('processing','completed')
             GROUP BY program_id",
            $start, $end
        ), ARRAY_A );
        $out = array();
        foreach ( $rows as $r ) {
            $out[(int)$r['program_id']] = array(
                'orders_count'=>(int)$r['orders_count'], 'ticket_count'=>(int)$r['ticket_count'],
                'audience_units'=>(int)$r['audience_units'], 'net_revenue'=>(float)$r['net_revenue'],
            );
        }
        return $out;
    }

    private static function daily_issues( $date ) {
        global $wpdb;
        list( $start, $end ) = self::day_window( $date );
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN order_status IN ('failed','cancelled') THEN external_order_id END) failed_orders,
                    COUNT(DISTINCT CASE WHEN refunded_amount>0 THEN external_order_id END) refund_orders,
                    COALESCE(SUM(CASE WHEN refunded_amount>0 THEN refunded_amount ELSE 0 END),0) refund_amount
             FROM {$wpdb->prefix}mmc_sales_ledger WHERE updated_at >= %s AND updated_at < %s",
            $start, $end
        ), ARRAY_A );
        return array(
            'failed_orders'=>(int)($r['failed_orders']??0),
            'refund_orders'=>(int)($r['refund_orders']??0),
            'refund_amount'=>(float)($r['refund_amount']??0),
        );
    }

    private static function meta_totals() {
        global $wpdb;
        $r = $wpdb->get_row(
            "SELECT COALESCE(SUM(spend),0) spend, COALESCE(SUM(purchases),0) purchases, COALESCE(SUM(revenue),0) revenue
             FROM {$wpdb->prefix}mmc_meta_plans WHERE status NOT IN ('completed')",
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $spend = (float)($r['spend']??0); $purchases=(int)($r['purchases']??0); $revenue=(float)($r['revenue']??0);
        return array( 'spend'=>$spend, 'purchases'=>$purchases, 'revenue'=>$revenue, 'cpa'=>$purchases?$spend/$purchases:0, 'roas'=>$spend?$revenue/$spend:0 );
    }

    private static function previous_snapshot( $report_date ) {
        global $wpdb;
        $json = $wpdb->get_var( $wpdb->prepare(
            "SELECT snapshot_json FROM {$wpdb->prefix}mmc_report_runs WHERE report_date < %s AND status='sent' ORDER BY report_date DESC,id DESC LIMIT 1",
            $report_date
        ) );
        if ( ! $json ) { return array(); }
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : array();
    }

    private static function fallback_priorities( $limit ) {
        global $wpdb;
        $limit = max( 0, absint( $limit ) );
        if ( ! $limit ) return array();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.title,t.priority,t.due_at,p.id program_id,p.program_code,p.province_name,p.district_name
             FROM {$wpdb->prefix}mmc_tasks t INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=t.program_id
             WHERE t.status='open' ORDER BY (t.priority='critical') DESC,(t.priority='high') DESC,COALESCE(t.due_at,'2999-12-31') ASC LIMIT %d",
            $limit
        ) );
        $out=array(); foreach($rows as $r){$out[]=array('level'=>in_array($r->priority,array('critical','high'),true)?$r->priority:'info','program_id'=>(int)$r->program_id,'program_code'=>$r->program_code,'location'=>trim($r->province_name.' / '.$r->district_name,' /'),'message'=>$r->title,'date'=>$r->due_at);}
        return $out;
    }

    private static function sales_change( $current, $previous ) {
        return array(
            'orders' => self::change_value( $current['orders_count'], $previous['orders_count'] ),
            'audience' => self::change_value( $current['audience_units'], $previous['audience_units'] ),
            'revenue' => self::change_value( $current['net_revenue'], $previous['net_revenue'] ),
        );
    }

    private static function change_value( $current, $previous ) {
        $current=(float)$current; $previous=(float)$previous;
        return array( 'absolute'=>$current-$previous, 'percent'=>$previous!=0?round((($current-$previous)/abs($previous))*100,1):null );
    }

    private static function day_window( $date ) { $tz=wp_timezone(); $start=new DateTimeImmutable($date.' 00:00:00',$tz); $end=$start->modify('+1 day'); return array($start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')); }
    private static function normalize_date( $date ) { $date=sanitize_text_field($date); $d=DateTime::createFromFormat('Y-m-d',$date); return $d&&$d->format('Y-m-d')===$date?$date:''; }
    private static function empty_sales(){return array('orders_count'=>0,'ticket_count'=>0,'audience_units'=>0,'net_revenue'=>0);}

    public static function recent_runs( $limit = 20 ) {
        global $wpdb; $limit=max(1,min(100,absint($limit)));
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_report_runs ORDER BY id DESC LIMIT %d", $limit ) );
    }
    public static function get_run( $run_id ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mmc_report_runs WHERE id=%d",absint($run_id)) ); }

    public static function render_html( $s ) {
        $money = function($v){ return number_format_i18n((float)$v,2).' TL'; };
        $d=$s['daily_sales']; $prev=$s['previous_day_sales']; $issues=$s['daily_issues']; $meta=$s['meta_totals']; $delta=$s['meta_delta_from_prior_report'];
        $woo=$s['woocommerce_verification']??array('status'=>'unavailable','events'=>array(),'orders_count'=>0,'ticket_count'=>0,'net_revenue'=>0);
        $woo_prev=$s['previous_woocommerce_verification']??array('status'=>'unavailable');
        $verified='verified'===$woo['status'];
        ob_start();
        ?>
<!doctype html><html><body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827">
<div style="max-width:1100px;margin:0 auto;padding:24px">
  <div style="background:#111827;color:white;padding:22px;border-radius:12px 12px 0 0"><h1 style="margin:0;font-size:24px">MADAGASKAR GECE RAPORU</h1><p style="margin:7px 0 0">Rapor tarihi: <strong><?php echo esc_html(wp_date('d.m.Y',strtotime($s['report_date']))); ?></strong> · Oluşturma: <?php echo esc_html(wp_date('d.m.Y H:i',strtotime($s['generated_at']))); ?></p></div>
  <div style="background:white;padding:22px;border-radius:0 0 12px 12px">
    <h2>1. Günlük Yönetim Özeti</h2>
    <p><strong><?php echo $verified?'WooCommerce ödenmiş sipariş doğrulaması':'Satış doğrulanamadı'; ?></strong> · MMC kayıtları ayrıca aşağıda gösterilir.</p>
    <table style="width:100%;border-collapse:collapse"><tr>
      <?php self::email_kpi('Sipariş',$verified?$woo['orders_count']:'—',$verified&&'verified'===($woo_prev['status']??'')?'Önceki gün '.$woo_prev['orders_count']:'Doğrulama bekleniyor'); ?>
      <?php self::email_kpi('Satılan Bilet',$verified?$woo['ticket_count']:'—','WooCommerce ürün adedi; paket kişi sayısı ayrıca doğrulanmalı'); ?>
      <?php self::email_kpi('Net Ciro',$verified?$money($woo['net_revenue']):'—',$verified&&'verified'===($woo_prev['status']??'')?'Önceki gün '.$money($woo_prev['net_revenue']):'Doğrulama bekleniyor'); ?>
      <?php self::email_kpi('MMC Başarısız / İade',$verified&&$woo['orders_count']===$d['orders_count']?$issues['failed_orders'].' / '.$issues['refund_orders']:'Doğrulanmadı','Yalnız MMC defteri; WooCommerce işlem durumları ayrıca kontrol edilmeli'); ?>
    </tr></table>
    <p><strong>Fatura:</strong> <?php echo $verified&&$woo['orders_count']===$d['orders_count']?esc_html($s['pending_invoices']).' MMC defterinde bekleyen':'WooCommerce ile fatura kuyruğu mutabık değil; ayrıca doğrulayın (MMC: '.esc_html($s['pending_invoices']).')'; ?> · <strong>MMC defteri:</strong> <?php echo esc_html($d['orders_count'].' sipariş / '.$d['ticket_count'].' bilet / '.$money($d['net_revenue'])); ?> · <strong>Operasyon:</strong> <?php echo esc_html($s['open_operations']); ?> zorunlu açık/problem · <strong>Görev:</strong> <?php echo esc_html($s['open_tasks']); ?> açık, <?php echo esc_html($s['overdue_tasks']); ?> gecikmiş.</p>

    <h2>2. WooCommerce Doğrudan Satış Doğrulaması</h2>
    <?php if(!$verified): ?><p style="color:#b91c1c">WooCommerce doğrulaması başarısız: <?php echo esc_html($woo['message']??'Kaynak kullanılamıyor.'); ?>. MMC satış sıfırı gerçek satış olarak yorumlanamaz.</p><?php else: ?>
    <p><strong><?php echo esc_html($woo['orders_count']); ?> ödenmiş sipariş · <?php echo esc_html($woo['ticket_count']); ?> ürün adedi · <?php echo esc_html($money($woo['net_revenue'])); ?></strong> · MMC farkı: <?php echo esc_html($woo['orders_count']-$d['orders_count']); ?> sipariş, <?php echo esc_html($money($woo['net_revenue']-$d['net_revenue'])); ?>. Tarih ölçütü: WooCommerce ödeme zamanı, site yerel saati. İade tutarı düşülür; ürün adedi aile paketinde kişi sayısı değildir.</p>
    <?php if($woo['orders_count']!=$d['orders_count']||abs($woo['net_revenue']-$d['net_revenue'])>0.01): ?><p style="color:#b91c1c"><strong>Mutabakat uyarısı:</strong> MMC satış defteri WooCommerce ile eşleşmiyor; doluluk ve fatura sıfırları kesin veri değildir.</p><?php endif; ?>
    <table style="width:100%;border-collapse:collapse"><thead><tr><th style="text-align:left">MDG etkinlik / ürün</th><th>Sipariş</th><th>Ürün adedi</th><th>Tutar</th><th>MMC bağlantısı</th></tr></thead><tbody><?php foreach($woo['events'] as $e): ?><tr><td><?php echo esc_html($e['name']); ?></td><td style="text-align:center"><?php echo esc_html($e['orders_count']); ?></td><td style="text-align:center"><?php echo esc_html($e['ticket_count']); ?></td><td style="text-align:center"><?php echo esc_html($money($e['net_revenue'])); ?></td><td style="text-align:center"><?php echo esc_html($e['program_id']?'MMC #'.$e['program_id']:'Eşleşmedi'); ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <h2>3. Meta Reklam Özeti</h2>
    <p><strong>Kaynak:</strong> MMC Meta plan kayıtları; Meta Ads canlı harcama doğrulaması yok. Buradaki sıfır, reklamlarda harcama olmadığı anlamına gelmez.</p>
    <p><strong>Toplam aktif harcama:</strong> <?php echo esc_html($money($meta['spend'])); ?> · <strong>Satın alma:</strong> <?php echo esc_html($meta['purchases']); ?> · <strong>CPA:</strong> <?php echo $meta['purchases']?esc_html($money($meta['cpa'])):'—'; ?> · <strong>ROAS:</strong> <?php echo $meta['spend']?esc_html(number_format_i18n($meta['roas'],2)):'—'; ?><?php if(null!==$delta['spend']): ?> · <strong>Son rapordan harcama farkı:</strong> <?php echo esc_html($money($delta['spend'])); ?><?php endif; ?></p>

    <h2>4. Program Bazlı Durum (yalnız MMC)</h2>
    <p>Satış ve doluluk MMC eşlemesine bağlıdır; WooCommerce mutabakatı yoksa sıfırlar kesin değildir.</p>
    <table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="background:#f3f4f6"><th style="padding:8px;text-align:left">Program</th><th>Gösteri</th><th>Günlük Satış</th><th>Toplam Doluluk</th><th>Saha</th><th>Operasyon</th><th>Finans</th><th>Risk</th></tr></thead><tbody>
    <?php foreach($s['programs'] as $p): ?>
      <tr style="border-bottom:1px solid #e5e7eb"><td style="padding:8px"><strong><?php echo esc_html($p['program_code']); ?></strong><br><?php echo esc_html($p['location']); ?></td><td style="text-align:center"><?php if($p['event_date']): echo esc_html(wp_date('d.m.Y',strtotime($p['event_date']))); if(null!==$p['days_to_show']): ?><br><small><?php echo $p['days_to_show']===0?'Bugün':($p['days_to_show']>0?esc_html($p['days_to_show'].' gün kaldı'):esc_html(abs($p['days_to_show']).' gün önce')); ?></small><?php endif; else: echo '—'; endif; ?></td><td style="text-align:center"><?php echo esc_html($p['daily_sales']['orders_count'].' sipariş / '.$money($p['daily_sales']['net_revenue'])); ?></td><td style="text-align:center"><?php echo esc_html($p['sales']['sold_capacity'].' / '.$p['sales']['capacity'].' · %'.number_format_i18n($p['sales']['occupancy'],1)); ?></td><td style="text-align:center"><?php echo $p['field']['target_schools']?esc_html($p['field']['visited_schools'].'/'.$p['field']['target_schools'].' · %'.number_format_i18n($p['field']['visit_percent'],1)):'—'; ?></td><td style="text-align:center"><?php echo $p['operations']['pre_total']?esc_html('%'.number_format_i18n($p['operations']['pre_percent'],1).' · '.$p['operations']['problems'].' sorun'):'—'; ?></td><td style="text-align:center"><?php echo esc_html($money($p['finance']['profit'])); ?><br><small><?php echo esc_html('%'.number_format_i18n($p['finance']['margin'],1).' marj'); ?></small></td><td style="text-align:center"><strong><?php echo esc_html(strtoupper($p['risk_level'])); ?></strong></td></tr>
      <?php if($p['sessions']): ?><tr><td colspan="8" style="padding:5px 12px 10px;color:#4b5563"><strong>Seanslar:</strong> <?php $chunks=array(); foreach($p['sessions'] as $ss){$chunks[]=esc_html($ss['time'].' — '.$ss['sold_capacity'].'/'.$ss['capacity'].' (%'.number_format_i18n($ss['occupancy'],1).')');} echo implode(' · ',$chunks); ?></td></tr><?php endif; ?>
    <?php endforeach; ?>
    </tbody></table>

    <h2>5. Yaklaşan MDG Etkinlikleri (aktif turne)</h2>
    <?php if(empty($s['mdg_upcoming_events'])): ?><p>MDG etkinlikleri doğrulanamadı veya yaklaşan etkinlik yok.</p><?php else: ?><table style="width:100%"><thead><tr><th style="text-align:left">Etkinlik</th><th>Tarih</th><th>MMC</th></tr></thead><tbody><?php foreach($s['mdg_upcoming_events'] as $e): ?><tr><td><?php echo esc_html($e['name']); ?></td><td><?php echo esc_html($e['date']); ?></td><td><?php echo esc_html($e['program_id']?'#'.$e['program_id']:'Bağlı değil'); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    <h2>6. Kritik Riskler</h2>
    <?php if(!$s['critical_alerts']): ?><p>Kritik/yüksek uyarı yok.</p><?php else: ?><ul><?php foreach($s['critical_alerts'] as $a): ?><li><strong><?php echo esc_html($a['program_code'].' · '.$a['location']); ?>:</strong> <?php echo esc_html($a['message']); ?></li><?php endforeach; ?></ul><?php endif; ?>

    <h2>7. İlk 3 Öncelik</h2>
    <?php if(!$s['top_priorities']): ?><p>Kritik öncelik bulunmuyor.</p><?php else: ?><ol><?php foreach($s['top_priorities'] as $a): ?><li><strong><?php echo esc_html(($a['program_code']??'Genel').' · '.($a['location']??'')); ?></strong> — <?php echo esc_html($a['message']); ?></li><?php endforeach; ?></ol><?php endif; ?>

    <h2>8. ChatGPT İçin Yapılandırılmış Özet</h2>
    <pre style="white-space:pre-wrap;background:#f9fafb;border:1px solid #e5e7eb;padding:12px;border-radius:8px;font-size:12px"><?php echo esc_html(self::machine_summary($s)); ?></pre>
    <p style="color:#6b7280;font-size:12px">Bu rapor MMC kayıtlarından otomatik oluşturulmuştur. WordPress wp_mail() başarısı e-postanın posta sunucusuna teslim talebinin kabul edildiğini gösterir; nihai Gmail teslimi site e-posta yapılandırmasına bağlıdır.</p>
  </div>
</div></body></html>
        <?php
        return (string) ob_get_clean();
    }

    private static function email_kpi($label,$value,$sub){ ?><td style="width:25%;padding:10px;border:1px solid #e5e7eb;vertical-align:top"><div style="font-size:12px;color:#6b7280"><?php echo esc_html($label); ?></div><div style="font-size:20px;font-weight:bold;margin:4px 0"><?php echo esc_html($value); ?></div><div style="font-size:11px;color:#6b7280"><?php echo esc_html($sub); ?></div></td><?php }

    private static function change_text($c){ if(null===$c['percent']) return ((float)$c['absolute']===0.0?'değişmedi':'yeni baz'); $p=(float)$c['percent']; return ($p>0?'+':'').number_format_i18n($p,1).'%'; }

    private static function machine_summary($s){
        $lines=array(); $d=$s['daily_sales'];
        $lines[]='REPORT_DATE='.$s['report_date'];
        $w=$s['woocommerce_verification']??array('status'=>'unavailable');
        $lines[]='SALES_SOURCE=woocommerce_paid_date';
        $lines[]='WOOCOMMERCE_VERIFICATION='.$w['status'];
        $lines[]='WOOCOMMERCE_ORDERS='.('verified'===$w['status']?$w['orders_count']:'UNVERIFIED');
        $lines[]='WOOCOMMERCE_TICKETS='.('verified'===$w['status']?$w['ticket_count']:'UNVERIFIED');
        $lines[]='WOOCOMMERCE_NET_REVENUE='.('verified'===$w['status']?round($w['net_revenue'],2):'UNVERIFIED');
        $lines[]='MMC_LEDGER_ORDERS='.$d['orders_count'];
        $lines[]='DAILY_ORDERS='.('verified'===$w['status']?$w['orders_count']:'UNVERIFIED');
        $lines[]='DAILY_TICKETS='.('verified'===$w['status']?$w['ticket_count']:'UNVERIFIED');
        $lines[]='DAILY_AUDIENCE_UNITS='.$d['audience_units'];
        $lines[]='DAILY_NET_REVENUE='.('verified'===$w['status']?round($w['net_revenue'],2):'UNVERIFIED');
        $lines[]='FAILED_ORDERS=UNVERIFIED';
        $lines[]='REFUND_ORDERS=UNVERIFIED';
        $lines[]='REFUND_AMOUNT=UNVERIFIED';
        $lines[]='PENDING_INVOICES='.('verified'===$w['status']&&$w['orders_count']===$d['orders_count']?$s['pending_invoices']:'UNVERIFIED');
        foreach($s['mdg_upcoming_events']??array() as $e){$lines[]='MDG_UPCOMING|'.$e['date'].'|'.$e['name'].'|mmc='.$e['program_id'];}
        $lines[]='OPEN_OPERATIONS='.$s['open_operations'];
        $lines[]='OPEN_TASKS='.$s['open_tasks'];
        $lines[]='OVERDUE_TASKS='.$s['overdue_tasks'];
        $lines[]='META_SOURCE=MMC_MANUAL_PLAN_NOT_LIVE';
        $lines[]='META_SPEND=UNVERIFIED';
        $lines[]='META_PURCHASES=UNVERIFIED';
        $lines[]='META_CPA=UNVERIFIED';
        $lines[]='META_ROAS=UNVERIFIED';
        foreach($s['programs'] as $p){$lines[]='PROGRAM|'.$p['program_code'].'|'.$p['location'].'|date='.($p['event_date']?:'').'|occupancy='.$p['sales']['occupancy'].'|daily_revenue='.round($p['daily_sales']['net_revenue'],2).'|field='.$p['field']['visit_percent'].'|ops='.$p['operations']['pre_percent'].'|profit='.round($p['finance']['profit'],2).'|risk='.$p['risk_level'];}
        return implode("\n",$lines);
    }
}
