<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only executive dashboard aggregation.
 *
 * Important: This service MUST NOT mutate operational, sales, finance or
 * marketing data while rendering the dashboard. It aggregates the records
 * already stored by their owning modules.
 */
class MMC_Dashboard_Service {
    public static function overview() {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        $start = $today . ' 00:00:00';
        $end   = wp_date( 'Y-m-d H:i:s', strtotime( $start . ' +1 day' ) );

        $sales = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(DISTINCT external_order_id) AS orders_count,
                    COALESCE(SUM(net_quantity),0) AS ticket_count,
                    COALESCE(SUM(capacity_units),0) AS audience_units,
                    COALESCE(SUM(net_amount),0) AS net_revenue
             FROM {$wpdb->prefix}mmc_sales_ledger
             WHERE paid_at >= %s AND paid_at < %s
               AND order_status IN ('processing','completed')",
            $start, $end
        ), ARRAY_A );

        $issues = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN order_status IN ('failed','cancelled') THEN external_order_id END) AS failed_orders,
                    COUNT(DISTINCT CASE WHEN refunded_amount > 0 THEN external_order_id END) AS refund_orders,
                    COALESCE(SUM(CASE WHEN refunded_amount > 0 THEN refunded_amount ELSE 0 END),0) AS refund_amount
             FROM {$wpdb->prefix}mmc_sales_ledger
             WHERE updated_at >= %s AND updated_at < %s",
            $start, $end
        ), ARRAY_A );

        $meta = $wpdb->get_row(
            "SELECT COALESCE(SUM(spend),0) AS spend,
                    COALESCE(SUM(purchases),0) AS purchases,
                    COALESCE(SUM(revenue),0) AS revenue
             FROM {$wpdb->prefix}mmc_meta_plans
             WHERE status <> 'completed' OR end_at IS NULL OR end_at >= NOW()",
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $meta_spend = (float) ( $meta['spend'] ?? 0 );
        $meta_purchases = (int) ( $meta['purchases'] ?? 0 );

        $lead_value = apply_filters( 'mmc_dashboard_active_leads', null );
        $lead_available = is_numeric( $lead_value );
        $program_leads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_kommo_profiles WHERE crm_status='synced' AND kommo_lead_id<>''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $next_show = $wpdb->get_row( $wpdb->prepare(
            "SELECT e.*, p.program_code, p.province_name, p.district_name, p.status AS program_status
             FROM {$wpdb->prefix}mmc_events e
             INNER JOIN {$wpdb->prefix}mmc_programs p ON p.id=e.program_id
             WHERE e.event_date >= %s
               AND p.status NOT IN ('cancelled','completed')
             ORDER BY e.event_date ASC, e.id ASC LIMIT 1",
            $today
        ) );

        $open_tasks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_tasks WHERE status='open'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $overdue_tasks = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_tasks WHERE status='open' AND due_at IS NOT NULL AND due_at < %s",
            current_time( 'mysql' )
        ) );

        return array(
            'today' => $today,
            'orders_count' => (int) ( $sales['orders_count'] ?? 0 ),
            'ticket_count' => (int) ( $sales['ticket_count'] ?? 0 ),
            'audience_units' => (int) ( $sales['audience_units'] ?? 0 ),
            'net_revenue' => (float) ( $sales['net_revenue'] ?? 0 ),
            'failed_orders' => (int) ( $issues['failed_orders'] ?? 0 ),
            'refund_orders' => (int) ( $issues['refund_orders'] ?? 0 ),
            'refund_amount' => (float) ( $issues['refund_amount'] ?? 0 ),
            'meta_spend' => $meta_spend,
            'meta_purchases' => $meta_purchases,
            'meta_revenue' => (float) ( $meta['revenue'] ?? 0 ),
            'meta_cpa' => $meta_purchases > 0 ? $meta_spend / $meta_purchases : 0,
            'meta_roas' => $meta_spend > 0 ? (float) ( $meta['revenue'] ?? 0 ) / $meta_spend : 0,
            'active_leads' => $lead_available ? (int) $lead_value : null,
            'lead_metric_available' => $lead_available,
            'synced_program_leads' => $program_leads,
            'next_show' => $next_show,
            'open_tasks' => $open_tasks,
            'overdue_tasks' => $overdue_tasks,
        );
    }

    public static function program_rows( $filters = array() ) {
        global $wpdb;
        $where = array( '1=1' );
        $args = array();

        $status = sanitize_key( $filters['status'] ?? '' );
        $province = sanitize_text_field( $filters['province'] ?? '' );
        $q = sanitize_text_field( $filters['q'] ?? '' );

        if ( $status && isset( MMC_Program_Service::statuses()[ $status ] ) ) {
            $where[] = 'p.status=%s'; $args[] = $status;
        } elseif ( empty( $filters['include_closed'] ) ) {
            $where[] = "p.status NOT IN ('completed','cancelled')";
        }
        if ( $province ) { $where[] = 'p.province_name=%s'; $args[] = $province; }
        if ( $q ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where[] = '(p.program_code LIKE %s OR p.province_name LIKE %s OR p.district_name LIKE %s)';
            array_push( $args, $like, $like, $like );
        }

        $sql = "SELECT p.*, e.id AS event_id, e.event_date, e.event_title, e.status AS event_status
                FROM {$wpdb->prefix}mmc_programs p
                LEFT JOIN {$wpdb->prefix}mmc_events e ON e.id=(SELECT e2.id FROM {$wpdb->prefix}mmc_events e2 WHERE e2.program_id=p.id ORDER BY e2.id ASC LIMIT 1)
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY COALESCE(e.event_date,p.planned_date,'2999-12-31') ASC, p.id DESC";
        $programs = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $rows = array();
        foreach ( $programs as $p ) { $rows[] = self::program_row( $p ); }
        return $rows;
    }

    public static function provinces() {
        global $wpdb;
        return $wpdb->get_col( "SELECT DISTINCT province_name FROM {$wpdb->prefix}mmc_programs WHERE province_name<>'' ORDER BY province_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    public static function critical_alerts( $limit = 12 ) {
        $rows = self::program_rows( array() );
        $alerts = array();
        foreach ( $rows as $row ) {
            foreach ( $row['risks'] as $risk ) {
                if ( ! in_array( $risk['level'], array( 'critical','high' ), true ) ) continue;
                $alerts[] = array(
                    'level' => $risk['level'],
                    'program_id' => (int) $row['program']->id,
                    'program_code' => $row['program']->program_code,
                    'location' => trim( $row['program']->province_name . ' / ' . $row['program']->district_name, ' /' ),
                    'message' => $risk['message'],
                    'date' => $row['event_date'],
                );
            }
        }
        usort( $alerts, function( $a, $b ) {
            $rank = array( 'critical'=>0, 'high'=>1 );
            $r = ( $rank[$a['level']] ?? 9 ) <=> ( $rank[$b['level']] ?? 9 );
            if ( 0 !== $r ) return $r;
            return strcmp( (string)$a['date'], (string)$b['date'] );
        } );
        return array_slice( $alerts, 0, max( 1, absint( $limit ) ) );
    }

    private static function program_row( $p ) {
        global $wpdb;
        $program_id = (int) $p->id;
        $event_id = (int) ( $p->event_id ?? 0 );
        $today = current_time( 'Y-m-d' );
        $event_date = $p->event_date ?: $p->planned_date;
        $days = null;
        if ( $event_date ) {
            $days = (int) floor( ( strtotime( $event_date . ' 12:00:00' ) - strtotime( $today . ' 12:00:00' ) ) / DAY_IN_SECONDS );
        }

        $sales = array( 'ticket_count'=>0,'sold_capacity'=>0,'net_revenue'=>0,'failed_orders'=>0,'capacity'=>0,'occupancy'=>0 );
        if ( $event_id ) {
            $s = MMC_Sales_Service::summary( $event_id );
            $sales = array_merge( $sales, $s );
            $sales['capacity'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(capacity),0) FROM {$wpdb->prefix}mmc_sessions WHERE event_id=%d AND status='active'", $event_id ) );
            $sales['occupancy'] = $sales['capacity'] > 0 ? round( 100 * (int)$sales['sold_capacity'] / $sales['capacity'], 1 ) : 0;
        }

        $field = class_exists('MMC_Field_Service') ? MMC_Field_Service::summary( $program_id ) : array();
        $ops = self::operations_readonly( $program_id );
        $meta = class_exists('MMC_Marketing_Service') ? MMC_Marketing_Service::meta_plan( $program_id ) : null;
        $finance = self::finance_readonly( $program_id );
        $kommo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mmc_kommo_profiles WHERE program_id=%d LIMIT 1", $program_id ) );
        $tasks = self::task_counts( $program_id );

        $row = array(
            'program' => $p,
            'event_date' => $event_date,
            'days_to_show' => $days,
            'sales' => $sales,
            'field' => $field,
            'operations' => $ops,
            'meta' => $meta,
            'finance' => $finance,
            'kommo' => $kommo,
            'tasks' => $tasks,
        );
        $row['risks'] = self::risk_rules( $row );
        $row['risk_level'] = self::highest_risk( $row['risks'] );
        return $row;
    }


    private static function operations_readonly( $program_id ) {
        global $wpdb;
        $check = $wpdb->prefix . 'mmc_operation_checklist';
        $res = $wpdb->prefix . 'mmc_program_resources';
        $all = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) total, SUM(status='done') done_count, SUM(status='problem') problems FROM $check WHERE program_id=%d AND status<>'not_applicable'",
            $program_id
        ), ARRAY_A );
        $pre = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) total, SUM(status='done') done_count, SUM(status='problem') problems FROM $check WHERE program_id=%d AND phase IN ('pre_departure','venue_setup') AND is_required=1 AND status<>'not_applicable'",
            $program_id
        ), ARRAY_A );
        $counts = $wpdb->get_results( $wpdb->prepare(
            "SELECT resource_type,COUNT(*) total FROM $res WHERE program_id=%d AND status<>'cancelled' GROUP BY resource_type",
            $program_id
        ), OBJECT_K );
        $pre_total=(int)($pre['total']??0); $pre_done=(int)($pre['done_count']??0);
        return array(
            'check_total'=>(int)($all['total']??0), 'check_done'=>(int)($all['done_count']??0), 'problems'=>(int)($all['problems']??0),
            'pre_total'=>$pre_total, 'pre_done'=>$pre_done, 'pre_percent'=>$pre_total?round(100*$pre_done/$pre_total,1):0,
            'vehicles'=>isset($counts['vehicle'])?(int)$counts['vehicle']->total:0,
            'artists'=>isset($counts['artist'])?(int)$counts['artist']->total:0,
            'people'=>isset($counts['person'])?(int)$counts['person']->total:0,
            'equipment'=>isset($counts['equipment'])?(int)$counts['equipment']->total:0,
        );
    }

    private static function finance_readonly( $program_id ) {
        global $wpdb;
        $sales_revenue = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(net_amount),0) FROM {$wpdb->prefix}mmc_sales_ledger WHERE program_id=%d", $program_id ) );
        $entries = $wpdb->get_results( $wpdb->prepare( "SELECT entry_class,status,amount FROM {$wpdb->prefix}mmc_finance_entries WHERE program_id=%d", $program_id ) );
        $income = 0; $expense = 0; $pending = 0; $unpaid_deposit = 0;
        foreach ( $entries as $e ) {
            if ( 'cancelled' === $e->status ) continue;
            if ( 'income' === $e->entry_class && in_array( $e->status, array('paid','realized','incurred'), true ) ) $income += (float)$e->amount;
            if ( 'expense' === $e->entry_class && in_array( $e->status, array('paid','realized','incurred'), true ) ) $expense += (float)$e->amount;
            if ( in_array( $e->status, array('planned','pending'), true ) && 'deposit_asset' !== $e->entry_class ) $pending++;
            if ( 'deposit_asset' === $e->entry_class && in_array( $e->status, array('planned','pending'), true ) && (float)$e->amount > 0 ) $unpaid_deposit++;
        }
        $dep = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(GREATEST(deposit_amount-refunded_amount-deduction_amount,0)),0) outstanding,
                    SUM(CASE WHEN status NOT IN ('resolved','cancelled') THEN 1 ELSE 0 END) unresolved
             FROM {$wpdb->prefix}mmc_deposit_refunds WHERE program_id=%d", $program_id
        ), ARRAY_A );
        $pending_invoices = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mmc_invoices WHERE program_id=%d AND status='pending'", $program_id ) );
        $revenue = $sales_revenue + $income;
        return array(
            'revenue' => $revenue,
            'expense' => $expense,
            'profit' => $revenue - $expense,
            'margin' => $revenue > 0 ? round( 100 * ( $revenue - $expense ) / $revenue, 1 ) : 0,
            'pending_entries' => $pending,
            'unpaid_deposits' => $unpaid_deposit,
            'deposit_outstanding' => (float) ( $dep['outstanding'] ?? 0 ),
            'unresolved_deposits' => (int) ( $dep['unresolved'] ?? 0 ),
            'pending_invoices' => $pending_invoices,
        );
    }

    private static function task_counts( $program_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT SUM(status='open') open_count,
                    SUM(status='open' AND priority='critical') critical_count,
                    SUM(status='open' AND due_at IS NOT NULL AND due_at < %s) overdue_count
             FROM {$wpdb->prefix}mmc_tasks WHERE program_id=%d",
            current_time('mysql'), $program_id
        ), ARRAY_A );
    }

    private static function risk_rules( $row ) {
        $p = $row['program']; $days = $row['days_to_show']; $risks = array();
        if ( 'cancelled' === $p->status ) return array( array('level'=>'info','message'=>'Program iptal edildi.') );
        if ( (int)($row['tasks']['critical_count'] ?? 0) > 0 ) $risks[] = array('level'=>'critical','message'=>(int)$row['tasks']['critical_count'].' açık kritik görev var.');
        if ( (int)($row['tasks']['overdue_count'] ?? 0) > 0 ) $risks[] = array('level'=>'high','message'=>(int)$row['tasks']['overdue_count'].' gecikmiş görev var.');
        if ( null !== $days && $days >= 0 && $days <= 7 && empty($p->event_id) ) $risks[] = array('level'=>'critical','message'=>'Gösteriye 7 günden az kaldı ancak etkinlik kaydı yok.');
        if ( null !== $days && $days >= 0 && $days <= 5 && (int)($row['sales']['capacity'] ?? 0) > 0 && (float)($row['sales']['occupancy'] ?? 0) < 25 ) $risks[] = array('level'=>'high','message'=>'Gösteriye yakın dönemde doluluk %25’in altında.');
        if ( null !== $days && $days >= 0 && $days <= 3 && (int)($row['field']['target_schools'] ?? 0) > 0 && (float)($row['field']['visit_percent'] ?? 0) < 70 ) $risks[] = array('level'=>'high','message'=>'Saha ziyaretleri %70’in altında.');
        if ( null !== $days && $days >= 0 && $days <= 2 && (int)($row['operations']['pre_total'] ?? 0) > 0 && (float)($row['operations']['pre_percent'] ?? 0) < 100 ) $risks[] = array('level'=>'critical','message'=>'Zorunlu operasyon hazırlıkları tamamlanmadı.');
        if ( (int)($row['operations']['problems'] ?? 0) > 0 ) $risks[] = array('level'=>'critical','message'=>(int)$row['operations']['problems'].' operasyon problemi işaretli.');
        if ( $row['meta'] && 'stop_required' === $row['meta']->status ) $risks[] = array('level'=>'critical','message'=>'Meta reklamları için durdurma işlemi gerekiyor.');
        if ( $row['kommo'] && in_array( $row['kommo']->ai_source_status, array('refresh_needed','error'), true ) ) $risks[] = array('level'=>'high','message'=>'Kommo AI kaynağı güncelleme/kontrol bekliyor.');
        if ( (int)$row['finance']['pending_invoices'] > 0 && null !== $days && $days < 0 ) $risks[] = array('level'=>'high','message'=>$row['finance']['pending_invoices'].' fatura kapanmayı bekliyor.');
        if ( (float)$row['finance']['deposit_outstanding'] > 0.01 && null !== $days && $days < 0 ) $risks[] = array('level'=>'high','message'=>'Teminat iadesi bekleniyor: '.self::money($row['finance']['deposit_outstanding']).'.');
        if ( ! $risks ) $risks[] = array('level'=>'ok','message'=>'Kritik uyarı yok.');
        return $risks;
    }

    private static function highest_risk( $risks ) {
        $rank = array('critical'=>5,'high'=>4,'medium'=>3,'info'=>2,'ok'=>1);
        $best='ok'; $score=0;
        foreach($risks as $r){$v=$rank[$r['level']]??0;if($v>$score){$score=$v;$best=$r['level'];}}
        return $best;
    }

    private static function money( $value ) { return number_format_i18n( (float)$value, 2 ) . ' TL'; }
}
