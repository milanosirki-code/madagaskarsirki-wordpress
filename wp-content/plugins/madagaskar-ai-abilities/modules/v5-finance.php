<?php

if ( ! function_exists( 'mdg_ai_v5_finance_can_run' ) ) {
    function mdg_ai_v5_finance_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_finance_forbidden', 'Bu işlem için WooCommerce yönetim yetkisi gerekir.' );
        }
        global $wpdb;
        foreach ( array( 'expenses','incomes','fixed_costs','attendance' ) as $suffix ) {
            $table = $wpdb->prefix . 'mdg_v5_' . $suffix;
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
                return new WP_Error( 'mdg_ai_finance_table_missing', 'V5 finans tablosu bulunamadı: ' . $suffix );
            }
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_v5_finance_table' ) ) {
    function mdg_ai_v5_finance_table( $suffix ) {
        global $wpdb;
        $allowed = array( 'expenses','incomes','fixed_costs','attendance' );
        if ( ! in_array( $suffix, $allowed, true ) ) return '';
        return $wpdb->prefix . 'mdg_v5_' . $suffix;
    }
}

if ( ! function_exists( 'mdg_ai_v5_date_ok' ) ) {
    function mdg_ai_v5_date_ok( $date ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) return false;
        list( $y,$m,$d ) = array_map( 'intval', explode( '-', $date ) );
        return checkdate( $m,$d,$y );
    }
}

if ( ! function_exists( 'mdg_ai_v5_month_ok' ) ) {
    function mdg_ai_v5_month_ok( $month ) {
        if ( ! preg_match( '/^\d{4}-\d{2}$/', (string) $month ) ) return false;
        list( $y,$m ) = array_map( 'intval', explode( '-', $month ) );
        return $y >= 2000 && $m >= 1 && $m <= 12;
    }
}

/** Manual refund cash is an accounting disclosure, never a gateway refund request. */
if ( ! function_exists( 'mdg_ai_v5_refund_cash_can_run' ) ) {
    function mdg_ai_v5_refund_cash_can_run( $input = null ) {
        if ( ! current_user_can('manage_options') || ! current_user_can('manage_woocommerce') ) {
            return new WP_Error('mdg_refund_cash_forbidden','Yönetici ve WooCommerce yönetim yetkisi gerekir.');
        }
        return mdg_ai_v5_finance_can_run($input);
    }
}
if ( ! function_exists( 'mdg_ai_v5_refund_cash_hash' ) ) {
    function mdg_ai_v5_refund_cash_hash( $row ) {
        $data=(array)$row; ksort($data);
        return hash('sha256',wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
}
if ( ! function_exists( 'mdg_ai_v5_refund_cash_reconcile' ) ) {
    function mdg_ai_v5_refund_cash_reconcile( $input = array() ) {
        global $wpdb;
        $permission=mdg_ai_v5_refund_cash_can_run($input);
        if (is_wp_error($permission)) return $permission;
        $key=(string)($input['plan_id']??''); $operation=(string)($input['operation']??'preview');
        if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/D',$key) || !in_array($operation,array('preview','apply','rollback'),true)) {
            return new WP_Error('mdg_refund_cash_input','Plan veya işlem geçersiz.');
        }
        // Plans are private, non-autoload site options. No amount or arbitrary row can be supplied by the API caller.
        $plans=(array)get_option('mdg_v5_refund_cash_review_plans',array()); $plan=$plans[$key]??null;
        if (!is_array($plan) || empty($plan['record_id']) || empty($plan['event_id']) || empty($plan['event_uuid']) ||
            !is_int($plan['amount_cents']??null) || $plan['amount_cents']<=0 || $plan['amount_cents']>10000000000 ||
            !preg_match('/^[a-f0-9]{64}$/D',(string)($plan['expected_hash']??'')) ||
            (int)($plan['confirmed_by']??0)!==get_current_user_id() || empty($plan['confirmed_at']) ||
            empty($plan['evidence_ref']) || empty($plan['notes']) || strlen((string)($plan['reason']??''))<10) {
            return new WP_Error('mdg_refund_cash_plan','Özel doğrulanmış düzeltme planı bulunamadı.');
        }
        if (!class_exists('MDG_V5_Finance_Identity') || !class_exists('MDG_V5_Finance') ||
            !MDG_V5_Finance_Identity::valid((int)$plan['event_id'].'|'.$plan['event_uuid'])) {
            return new WP_Error('mdg_refund_cash_identity','Kanonik program doğrulanamadı.');
        }
        $id=(int)$plan['record_id']; $table=mdg_ai_v5_finance_table('expenses');
        $history=$wpdb->prefix.'mdg_v5_finance_history';
        // Same lock as the owning Finance Records editor; row locks cover other writers too.
        $lock='mdg_fin_'.substr(hash('sha256',$wpdb->prefix.'expense'.$id),0,40);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1) {
            return new WP_Error('mdg_refund_cash_busy','Kayıt başka işlemde.');
        }
        $transaction=false;
        try {
            foreach (array($table,$history,$wpdb->prefix.'mdg_v5_settlements',$wpdb->prefix.'mdg_v5_payments') as $required) {
                $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$required));
                if (!$status || strtolower($status->Engine)!=='innodb') throw new RuntimeException('İşlem tabloları InnoDB olmalıdır.');
            }
            if (false===$wpdb->query('START TRANSACTION')) throw new RuntimeException('İşlem başlatılamadı.');
            $transaction=true;
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$id));
            if (!$row || $row->scope!=='program' || (int)$row->event_id!==(int)$plan['event_id'] ||
                !in_array($row->category,array('İade / WooCommerce','İade / Biletinial'),true) ||
                !MDG_V5_Finance::is_ticket_refund($row) ||
                preg_match('/^(woo|meta|mmc)[_:\-]/i',(string)($row->document_no??''))) {
                throw new RuntimeException('Yalnız doğrulanmış manuel bilet iade anaparası düzeltilebilir.');
            }
            foreach (array('settlements','payments') as $kind) {
                $tracking=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$wpdb->prefix.'mdg_v5_'.$kind.' WHERE record_type=%s AND record_id=%d','expense',$id));
                if ($tracking===null || (int)$tracking!==0) {
                    throw new RuntimeException('Ödeme takibi bulunan kayıt kaynak hareketleriyle ayrıca mutabakat edilmelidir.');
                }
            }
            $marker='[refund-cash:'.$key.':'.mdg_ai_v5_refund_cash_hash($plan).']';
            $latest=$wpdb->get_row($wpdb->prepare("SELECT * FROM $history WHERE record_type=%s AND record_id=%d AND operation IN ('refund_cash','refund_cash_rollback') ORDER BY id DESC LIMIT 1",'expense',$id));
            $same=$latest && strpos($latest->reason,$marker)===0;
            $current_hash=mdg_ai_v5_refund_cash_hash($row);
            $target_notes=sanitize_textarea_field($plan['notes']);
            $target_amount=number_format($plan['amount_cents']/100,2,'.','');
            if ($same && $latest->operation==='refund_cash_rollback') {
                if ($current_hash!==mdg_ai_v5_refund_cash_hash(json_decode($latest->after_data,true))) throw new RuntimeException('Geri alınan kayıt sonradan değişmiş.');
                if ($operation==='apply') throw new RuntimeException('Bu plan geri alınmış; yeniden uygulama için yeni doğrulama gerekir.');
                $result=array('status'=>'already_rolled_back','record_id'=>$id,'amount'=>(float)$row->amount);
            } elseif ($operation==='rollback') {
                if (!$same || $latest->operation!=='refund_cash' || $current_hash!==mdg_ai_v5_refund_cash_hash(json_decode($latest->after_data,true))) throw new RuntimeException('Geri alma görüntüsü eşleşmiyor; sonraki değişiklikler korunur.');
                $original=json_decode($latest->before_data,true);
                if (!is_array($original) || mdg_ai_v5_refund_cash_hash($original)!==$plan['expected_hash']) throw new RuntimeException('Önceki kayıt doğrulanamadı.');
                $data=array_intersect_key($original,array_flip(array('amount','notes','updated_at')));
                $result=array('status'=>'rolled_back','record_id'=>$id,'amount'=>(float)$original['amount']);
            } elseif ($same && $latest->operation==='refund_cash') {
                if ($current_hash!==mdg_ai_v5_refund_cash_hash(json_decode($latest->after_data,true))) throw new RuntimeException('Düzeltilen kayıt sonradan değişmiş.');
                $result=array('status'=>'already_applied','record_id'=>$id,'amount'=>(float)$row->amount);
            } else {
                if ($current_hash!==$plan['expected_hash']) throw new RuntimeException('Kayıt doğrulanan önceki görüntüden farklı; işlem yapılmadı.');
                $result=array('status'=>$operation==='preview'?'preview':'applied','record_id'=>$id,'event_id'=>(int)$row->event_id,'before_amount'=>(float)$row->amount,'amount'=>(float)$target_amount,'gateway_refund_created'=>false);
                if ($operation==='apply') $data=array('amount'=>$target_amount,'notes'=>$target_notes,'updated_at'=>current_time('mysql'));
            }
            if (isset($data)) {
                if (false===$wpdb->update($table,$data,array('id'=>$id))) throw new RuntimeException('Düzeltme yazılamadı.');
                $after=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$id));
                $expected=array_merge((array)$row,$data);
                if (!$after || mdg_ai_v5_refund_cash_hash($after)!==mdg_ai_v5_refund_cash_hash($expected)) throw new RuntimeException('Düzeltme yeniden okuma doğrulaması başarısız.');
                $audit=array('record_type'=>'expense','record_id'=>$id,'operation'=>$operation==='rollback'?'refund_cash_rollback':'refund_cash','before_data'=>wp_json_encode($row),'after_data'=>wp_json_encode($after),'reason'=>$marker.' '.sanitize_textarea_field($plan['reason']).' | '.sanitize_textarea_field($plan['evidence_ref']),'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'));
                if (false===$wpdb->insert($history,$audit)) throw new RuntimeException('İşlem geçmişi yazılamadı; düzeltme geri alındı.');
                if (false===$wpdb->query('COMMIT')) throw new RuntimeException('İşlem tamamlanamadı.');
            } else {
                $wpdb->query('ROLLBACK');
            }
            $transaction=false;
            return $result;
        } catch (Throwable $e) {
            if ($transaction) $wpdb->query('ROLLBACK');
            return new WP_Error('mdg_refund_cash_rejected',$e instanceof RuntimeException?$e->getMessage():'Düzeltme tamamlanamadı; işlem geri alındı.');
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
}
if ( ! function_exists( 'mdg_ai_v5_refund_cash_preview' ) ) {
    function mdg_ai_v5_refund_cash_preview( $input = array() ) {
        $input['operation']='preview';
        return mdg_ai_v5_refund_cash_reconcile($input);
    }
}

if ( ! function_exists( 'mdg_ai_v5_finance_where' ) ) {
    function mdg_ai_v5_finance_where( $type, $input, &$params ) {
        global $wpdb;
        $where = array( '1=1' );
        $month = sanitize_text_field( $input['month'] ?? '' );
        $event_id = absint( $input['event_id'] ?? 0 );
        $scope = sanitize_key( $input['scope'] ?? '' );
        $province = sanitize_text_field( $input['province'] ?? '' );

        if ( $month && mdg_ai_v5_month_ok( $month ) ) {
            if ( 'expenses' === $type ) { $where[] = 'expense_date LIKE %s'; $params[] = $month . '-%'; }
            if ( 'incomes' === $type ) { $where[] = 'income_date LIKE %s'; $params[] = $month . '-%'; }
            if ( 'fixed_costs' === $type ) { $where[] = 'cost_month=%s'; $params[] = $month; }
        }
        if ( $event_id && in_array( $type, array( 'expenses','incomes','attendance' ), true ) ) {
            $where[] = 'event_id=%d'; $params[] = $event_id;
        }
        if ( $scope && in_array( $type, array( 'expenses','incomes' ), true ) ) {
            $where[] = 'scope=%s'; $params[] = $scope;
        }
        if ( $province && in_array( $type, array( 'expenses','incomes' ), true ) ) {
            $where[] = 'province=%s'; $params[] = $province;
        }
        return implode( ' AND ', $where );
    }
}

if ( ! function_exists( 'mdg_ai_v5_expenses_list' ) ) {
    function mdg_ai_v5_expenses_list( $input = array() ) {
        global $wpdb;
        $params = array();
        $where = mdg_ai_v5_finance_where( 'expenses', $input, $params );
        $limit = isset( $input['limit'] ) ? max( 1, min( 500, absint( $input['limit'] ) ) ) : 100;
        $sql = 'SELECT * FROM ' . mdg_ai_v5_finance_table('expenses') . ' WHERE ' . $where . ' ORDER BY expense_date DESC,id DESC LIMIT %d';
        $params[] = $limit;
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        return array( 'count'=>count($rows), 'items'=>array_map(function($r){
            return array(
                'id'=>(int)$r->id,'event_id'=>(int)$r->event_id,'scope'=>(string)$r->scope,'province'=>(string)$r->province,
                'related_name'=>(string)$r->related_name,'category'=>(string)$r->category,'description'=>(string)$r->description,
                'vendor'=>(string)$r->vendor,'amount'=>(float)$r->amount,'expense_date'=>(string)$r->expense_date,
                'payment_method'=>(string)$r->payment_method,'document_no'=>(string)$r->document_no,
                'attachment_id'=>(int)$r->attachment_id,'notes'=>(string)($r->notes??''),'created_by'=>(int)$r->created_by,
                'created_at'=>(string)$r->created_at,'updated_at'=>(string)$r->updated_at
            );
        },(array)$rows) );
    }
}

if ( ! function_exists( 'mdg_ai_v5_incomes_list' ) ) {
    function mdg_ai_v5_incomes_list( $input = array() ) {
        global $wpdb;
        $params=array();
        $where=mdg_ai_v5_finance_where('incomes',$input,$params);
        $limit=isset($input['limit'])?max(1,min(500,absint($input['limit']))):100;
        $sql='SELECT * FROM '.mdg_ai_v5_finance_table('incomes').' WHERE '.$where.' ORDER BY income_date DESC,id DESC LIMIT %d';
        $params[]=$limit;
        $rows=$wpdb->get_results($wpdb->prepare($sql,$params));
        return array('count'=>count($rows),'items'=>array_map(function($r){
            return array(
                'id'=>(int)$r->id,'event_id'=>(int)$r->event_id,'scope'=>(string)$r->scope,'province'=>(string)$r->province,
                'category'=>(string)$r->category,'channel'=>(string)$r->channel,'description'=>(string)$r->description,
                'gross_amount'=>(float)$r->gross_amount,'commission_amount'=>(float)$r->commission_amount,'net_amount'=>(float)$r->net_amount,
                'ticket_count'=>(int)$r->ticket_count,'income_date'=>(string)$r->income_date,'collection_status'=>(string)$r->collection_status,
                'payment_method'=>(string)$r->payment_method,'source_ref'=>(string)$r->source_ref,'attachment_id'=>(int)$r->attachment_id,
                'notes'=>(string)($r->notes??''),'created_by'=>(int)$r->created_by,'created_at'=>(string)$r->created_at,'updated_at'=>(string)$r->updated_at
            );
        },(array)$rows));
    }
}

if ( ! function_exists( 'mdg_ai_v5_fixed_costs_list' ) ) {
    function mdg_ai_v5_fixed_costs_list( $input = array() ) {
        global $wpdb;
        $params=array();
        $where=mdg_ai_v5_finance_where('fixed_costs',$input,$params);
        $limit=isset($input['limit'])?max(1,min(500,absint($input['limit']))):100;
        $sql='SELECT * FROM '.mdg_ai_v5_finance_table('fixed_costs').' WHERE '.$where.' ORDER BY cost_month DESC,id DESC LIMIT %d';
        $params[]=$limit;
        $rows=$wpdb->get_results($wpdb->prepare($sql,$params));
        return array('count'=>count($rows),'items'=>array_map(function($r){
            return array(
                'id'=>(int)$r->id,'cost_month'=>(string)$r->cost_month,'cost_type'=>(string)$r->cost_type,'name'=>(string)$r->name,
                'headcount'=>(int)$r->headcount,'pay_amount'=>(float)$r->pay_amount,'currency'=>(string)$r->currency,'fx_rate'=>(float)$r->fx_rate,
                'pay_basis'=>(string)$r->pay_basis,'insured_count'=>(int)$r->insured_count,'tax_per_person'=>(float)$r->tax_per_person,
                'salary_total'=>(float)$r->salary_total,'tax_total'=>(float)$r->tax_total,'total_amount'=>(float)$r->total_amount,
                'document_no'=>(string)$r->document_no,'attachment_id'=>(int)$r->attachment_id,'notes'=>(string)($r->notes??''),
                'created_by'=>(int)$r->created_by,'created_at'=>(string)$r->created_at,'updated_at'=>(string)$r->updated_at
            );
        },(array)$rows));
    }
}

if ( ! function_exists( 'mdg_ai_v5_attendance_list' ) ) {
    function mdg_ai_v5_attendance_list( $input = array() ) {
        global $wpdb;
        $params=array();
        $where=mdg_ai_v5_finance_where('attendance',$input,$params);
        $sql='SELECT * FROM '.mdg_ai_v5_finance_table('attendance').' WHERE '.$where.' ORDER BY event_id DESC';
        $rows=$params?$wpdb->get_results($wpdb->prepare($sql,$params)):$wpdb->get_results($sql);
        return array('count'=>count($rows),'items'=>array_map(function($r){
            return array(
                'id'=>(int)$r->id,'event_id'=>(int)$r->event_id,'avg_ticket_price'=>(float)$r->avg_ticket_price,
                'manual_attendance'=>(int)$r->manual_attendance,'include_in_reports'=>!empty($r->include_in_reports),
                'notes'=>(string)($r->notes??''),'updated_by'=>(int)$r->updated_by,'updated_at'=>(string)$r->updated_at
            );
        },(array)$rows));
    }
}

if ( ! function_exists( 'mdg_ai_v5_web_summary' ) ) {
    function mdg_ai_v5_web_summary( $input = array() ) {
        global $wpdb;
        $scope = sanitize_key( $input['scope'] ?? '' );
        if ( $scope && 'program' !== $scope ) {
            return array( 'gross'=>0.0, 'net'=>0.0, 'tickets'=>0, 'event_count'=>0 );
        }
        if ( ! class_exists( 'MDG_DB' ) || ! class_exists( 'MDG_V5_Finance' ) ) {
            return array( 'gross'=>0.0, 'net'=>0.0, 'tickets'=>0, 'event_count'=>0 );
        }

        $month = sanitize_text_field( $input['month'] ?? '' );
        $event_id = absint( $input['event_id'] ?? 0 );
        $province = sanitize_text_field( $input['province'] ?? '' );
        $events_table = MDG_DB::table( 'events' );
        $order_map = MDG_DB::table( 'order_map' );

        $events = (array) $wpdb->get_results( 'SELECT * FROM ' . $events_table . ' ORDER BY id DESC LIMIT 1000' );
        $event_ids = array();
        foreach ( $events as $event ) {
            $id = absint( $event->id ?? 0 );
            if ( ! $id ) continue;
            if ( $event_id && $id !== $event_id ) continue;
            if ( $province && 0 !== strcasecmp( trim( (string) ( $event->province_name ?? '' ) ), $province ) ) continue;
            if ( $month ) {
                if ( ! mdg_ai_v5_month_ok( $month ) ) continue;
                $date = MDG_V5_Finance::event_date( $event );
                if ( 0 !== strpos( (string) $date, $month . '-' ) ) continue;
            }
            $event_ids[] = $id;
        }
        if ( ! $event_ids ) {
            return array( 'gross'=>0.0, 'net'=>0.0, 'tickets'=>0, 'event_count'=>0 );
        }

        $paid_statuses = array( 'processing','completed','paid','wc-processing','wc-completed','wc-paid' );
        if ( function_exists( 'wc_get_is_paid_statuses' ) ) {
            foreach ( (array) wc_get_is_paid_statuses() as $status ) {
                $status = sanitize_key( $status );
                if ( $status ) {
                    $paid_statuses[] = $status;
                    $paid_statuses[] = 'wc-' . $status;
                }
            }
        }
        $paid_statuses = array_values( array_unique( $paid_statuses ) );

        $event_ph = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
        $status_ph = implode( ',', array_fill( 0, count( $paid_statuses ), '%s' ) );
        $sql = "SELECT event_id,
                       COALESCE(SUM(line_total),0) gross,
                       COALESCE(SUM(quantity),0) tickets
                FROM {$order_map}
                WHERE event_id IN ({$event_ph})
                  AND order_status IN ({$status_ph})
                  AND paid_at IS NOT NULL
                  AND paid_at <> '0000-00-00 00:00:00'
                GROUP BY event_id";
        $rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $event_ids, $paid_statuses ) ) );
        $gross = 0.0;
        $tickets = 0;
        foreach ( $rows as $row ) {
            $gross += (float) $row->gross;
            $tickets += (int) $row->tickets;
        }
        if (class_exists('MDG_V5_Finance') && is_callable(array('MDG_V5_Finance','web_revenues_for_events'))) $gross=array_sum(MDG_V5_Finance::web_revenues_for_events($event_ids));
        return array(
            'gross'       => round( $gross, 2 ),
            'net'         => round( $gross, 2 ),
            'tickets'     => $tickets,
            'event_count' => count( $rows ),
        );
    }
}

if ( ! function_exists( 'mdg_ai_v5_manual_income_summary' ) ) {
    function mdg_ai_v5_manual_income_summary( $input = array() ) {
        global $wpdb;
        $params = array();
        $where = mdg_ai_v5_finance_where( 'incomes', $input, $params );
        $sql = 'SELECT * FROM ' . mdg_ai_v5_finance_table( 'incomes' ) . ' WHERE ' . $where . " AND LOWER(TRIM(channel))<>'woocommerce'";
        $rows = $params ? (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : (array) $wpdb->get_results( $sql );

        $out = array( 'count'=>0, 'gross'=>0.0, 'commission'=>0.0, 'net'=>0.0, 'tickets'=>0 );
        foreach ( $rows as $row ) {
            $net_total = max( 0.0, (float) $row->net_amount );
            $settled = 0.0;
            if ( class_exists( 'MDG_V5_Finance_Records' ) && method_exists( 'MDG_V5_Finance_Records', 'collected' ) ) {
                $settled = max( 0.0, (float) MDG_V5_Finance_Records::collected( $row ) );
            } elseif ( 'collected' === (string) $row->collection_status ) {
                $settled = $net_total;
            }
            if ( $settled <= 0 ) continue;

            $ratio = $net_total > 0 ? min( 1.0, $settled / $net_total ) : 0.0;
            $out['count']++;
            $out['gross'] += (float) $row->gross_amount * $ratio;
            $out['commission'] += (float) $row->commission_amount * $ratio;
            $out['net'] += $settled;
            $out['tickets'] += (int) $row->ticket_count;
        }
        foreach ( array( 'gross','commission','net' ) as $key ) $out[$key] = round( $out[$key], 2 );
        return $out;
    }
}

if ( ! function_exists( 'mdg_ai_v5_operating_expense_summary' ) ) {
    function mdg_ai_v5_operating_expense_summary( $input = array() ) {
        global $wpdb;
        $params = array();
        $where = mdg_ai_v5_finance_where( 'expenses', $input, $params );
        $sql = 'SELECT * FROM ' . mdg_ai_v5_finance_table( 'expenses' ) . ' WHERE ' . $where;
        $rows = $params ? (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : (array) $wpdb->get_results( $sql );

        $count = 0;
        $total = 0.0;
        $refunds = 0.0;
        foreach ( $rows as $row ) {
            $is_refund = class_exists('MDG_V5_Finance') && is_callable(array('MDG_V5_Finance','is_ticket_refund')) && MDG_V5_Finance::is_ticket_refund($row);
            if ( $is_refund ) {
                $refunds += (float) $row->amount;
                continue;
            }
            $count++;
            $total += (float) $row->amount;
        }
        return array( 'count'=>$count, 'total'=>round($total,2), 'refund_cash'=>round($refunds,2) );
    }
}

if ( ! function_exists( 'mdg_ai_v5_finance_summary' ) ) {
    function mdg_ai_v5_finance_summary( $input = array() ) {
        global $wpdb;

        $event_id=absint($input['event_id']??0);
        if($event_id){unset($input['month']);$input['scope']='program';}
        $manual = mdg_ai_v5_manual_income_summary( $input );
        $web = mdg_ai_v5_web_summary( $input );
        $expense_summary = mdg_ai_v5_operating_expense_summary( $input );

        $params = array();
        $fixed_where = mdg_ai_v5_finance_where( 'fixed_costs', $input, $params );
        $fixed_sql = 'SELECT COUNT(*) c,COALESCE(SUM(total_amount),0) total FROM ' . mdg_ai_v5_finance_table( 'fixed_costs' ) . ' WHERE ' . $fixed_where;
        $fixed_row = $params ? $wpdb->get_row( $wpdb->prepare( $fixed_sql, $params ) ) : $wpdb->get_row( $fixed_sql );

        $income_net = (float) $manual['net'] + (float) $web['net'];
        $income_gross = (float) $manual['gross'] + (float) $web['gross'];
        $income_commission = (float) $manual['commission'];
        $income_tickets = (int) $manual['tickets'] + (int) $web['tickets'];
        $income_count = (int) $manual['count'] + (int) $web['event_count'];
        $expenses = (float) $expense_summary['total'];
        $fixed = (float) $fixed_row->total;
        $common=0.0;
        if($event_id&&class_exists('MDG_V5_Finance')){
            $shares=MDG_V5_Finance::cost_shares($event_id);
            $fixed=$shares['fixed_share'];$common=$shares['common'];
            $fixed_row->c=$shares['fixed_count'];
        }


        return array(
            'basis'=>$event_id?'program_lifetime':'record_month',
            'filters'=>array(
                'month'=>sanitize_text_field($input['month']??''),
                'event_id'=>absint($input['event_id']??0),
                'scope'=>sanitize_key($input['scope']??''),
                'province'=>sanitize_text_field($input['province']??'')
            ),
            'income'=>array(
                'count'=>$income_count,
                'gross'=>round($income_gross,2),
                'commission'=>round($income_commission,2),
                'net'=>round($income_net,2),
                'ticket_count'=>$income_tickets,
                'web_gross'=>(float)$web['gross'],
                'web_event_count'=>(int)$web['event_count'],
                'manual_collected_net'=>(float)$manual['net']
            ),
            'expenses'=>array(
                'count'=>(int)$expense_summary['count'],
                'total'=>$expenses,
                'shared_allocated'=>round($common,2),
                'ticket_refund_cash'=>(float)$expense_summary['refund_cash']
            ),
            'fixed_costs'=>array('count'=>(int)$fixed_row->c,'total'=>round($fixed,2)),
            'total_cost'=>round($expenses+$common+$fixed,2),
            'profit'=>round($income_net-$expenses-$common-$fixed,2)
        );
    }
}

if ( ! function_exists( 'mdg_ai_v5_expense_create' ) ) {
    function mdg_ai_v5_expense_create( $input ) {
        global $wpdb;
        $scope=sanitize_key($input['scope']??'program');
        $event=absint($input['event_id']??0);
        $amount=(float)($input['amount']??0);
        $date=sanitize_text_field($input['expense_date']??'');
        $category=sanitize_text_field($input['category']??'');
        if(!in_array($scope,array('program','province','general','person','vehicle'),true))$scope='program';
        if($amount<=0||!mdg_ai_v5_date_ok($date)||!$category||('program'===$scope&&!$event)){
            return new WP_Error('mdg_ai_finance_required','Gider için tutar, tarih, kategori ve program kapsamındaysa event_id zorunludur.');
        }
        if ($scope==='program' &&
            (!class_exists('MDG_V5_Finance_Identity') ||
             !MDG_V5_Finance_Identity::valid($event.'|'.($input['event_uuid']??'')))) {
            return new WP_Error('mdg_ai_finance_identity','MDG etkinlik kimliği ve event_uuid birlikte doğrulanmalıdır; MMC program_id kullanılamaz.');
        }
        $now=current_time('mysql');
        $ok=$wpdb->insert(mdg_ai_v5_finance_table('expenses'),array(
            'event_id'=>$event,'scope'=>$scope,'province'=>sanitize_text_field($input['province']??''),
            'related_name'=>sanitize_text_field($input['related_name']??''),'category'=>$category,
            'description'=>sanitize_text_field($input['description']??''),'vendor'=>sanitize_text_field($input['vendor']??''),
            'amount'=>$amount,'expense_date'=>$date,'payment_method'=>sanitize_text_field($input['payment_method']??''),
            'document_no'=>sanitize_text_field($input['document_no']??''),'attachment_id'=>0,
            'notes'=>sanitize_textarea_field($input['notes']??''),'created_by'=>get_current_user_id(),
            'created_at'=>$now,'updated_at'=>$now
        ));
        if(false===$ok||!$wpdb->insert_id)return new WP_Error('mdg_ai_finance_insert_failed','Gider kaydedilemedi.');
        return array('created'=>true,'expense_id'=>(int)$wpdb->insert_id);
    }
}

if ( ! function_exists( 'mdg_ai_v5_income_create' ) ) {
    function mdg_ai_v5_income_create( $input ) {
        global $wpdb;
        $scope=sanitize_key($input['scope']??'program');
        $event=absint($input['event_id']??0);
        $category=sanitize_text_field($input['category']??'');
        $gross=(float)($input['gross_amount']??0);
        $commission=max(0,(float)($input['commission_amount']??0));
        $date=sanitize_text_field($input['income_date']??'');
        $channel=sanitize_text_field($input['channel']??'');
        $ref=sanitize_text_field($input['source_ref']??'');
        $description=sanitize_text_field($input['description']??'');
        $is_corporate='Kurumsal / Toplu Satış'===$category;
        if($is_corporate){$scope='general';$event=0;}
        if($gross<=0||!mdg_ai_v5_date_ok($date)||!$channel||('program'===$scope&&!$event)||($is_corporate&&!$description)){
            return new WP_Error('mdg_ai_finance_required','Gelir için brüt tutar, tarih, kanal ve program kapsamındaysa event_id zorunludur.');
        }
        if($ref){
            $dup=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.mdg_ai_v5_finance_table('incomes').' WHERE source_ref=%s AND channel=%s LIMIT 1',$ref,$channel));
            if($dup)return new WP_Error('mdg_ai_finance_duplicate','Aynı source_ref ve kanal ile gelir kaydı zaten var. Kayıt ID: '.$dup);
        }
        if ($scope==='program' &&
            (!class_exists('MDG_V5_Finance_Identity') ||
             !MDG_V5_Finance_Identity::valid($event.'|'.($input['event_uuid']??'')))) {
            return new WP_Error('mdg_ai_finance_identity','MDG etkinlik kimliği ve event_uuid birlikte doğrulanmalıdır; MMC program_id kullanılamaz.');
        }
        $commission=min($gross,$commission); $now=current_time('mysql');
        $ok=$wpdb->insert(mdg_ai_v5_finance_table('incomes'),array(
            'event_id'=>$event,'scope'=>$scope,'province'=>sanitize_text_field($input['province']??''),
            'category'=>$category,'channel'=>$channel,'description'=>$description,'gross_amount'=>$gross,
            'commission_amount'=>$commission,'net_amount'=>max(0,$gross-$commission),'ticket_count'=>absint($input['ticket_count']??0),
            'income_date'=>$date,'collection_status'=>sanitize_key($input['collection_status']??'collected'),
            'payment_method'=>sanitize_text_field($input['payment_method']??''),'source_ref'=>$ref,'attachment_id'=>0,
            'notes'=>sanitize_textarea_field($input['notes']??''),'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now
        ));
        if(false===$ok||!$wpdb->insert_id)return new WP_Error('mdg_ai_finance_insert_failed','Gelir kaydedilemedi.');
        return array('created'=>true,'income_id'=>(int)$wpdb->insert_id,'net_amount'=>max(0,$gross-$commission));
    }
}

if ( ! function_exists( 'mdg_ai_v5_fixed_cost_create' ) ) {
    function mdg_ai_v5_fixed_cost_create( $input ) {
        global $wpdb;
        $month=sanitize_text_field($input['cost_month']??'');
        $name=sanitize_text_field($input['name']??'');
        $pay=(float)($input['pay_amount']??0);
        $fx=max(0,(float)($input['fx_rate']??1));
        $basis=sanitize_key($input['pay_basis']??'group');
        $head=absint($input['headcount']??0);
        $insured=absint($input['insured_count']??0);
        $taxpp=max(0,(float)($input['tax_per_person']??0));
        if(!mdg_ai_v5_month_ok($month)||!$name||$pay<0||$fx<=0||!in_array($basis,array('group','person'),true)){
            return new WP_Error('mdg_ai_finance_required','Sabit gider için ay, ad, geçerli tutar/döviz kuru ve pay_basis zorunludur.');
        }
        $salary=round($pay*$fx*(('person'===$basis)?max(1,$head):1),2);
        $tax=round($insured*$taxpp,2); $now=current_time('mysql');
        $ok=$wpdb->insert(mdg_ai_v5_finance_table('fixed_costs'),array(
            'cost_month'=>$month,'cost_type'=>sanitize_key($input['cost_type']??'operating'),'name'=>$name,
            'headcount'=>$head,'pay_amount'=>$pay,'currency'=>sanitize_text_field($input['currency']??'TRY'),
            'fx_rate'=>$fx,'pay_basis'=>$basis,'insured_count'=>$insured,'tax_per_person'=>$taxpp,
            'salary_total'=>$salary,'tax_total'=>$tax,'total_amount'=>$salary+$tax,
            'document_no'=>sanitize_text_field($input['document_no']??''),'attachment_id'=>0,
            'notes'=>sanitize_textarea_field($input['notes']??''),'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now
        ));
        if(false===$ok||!$wpdb->insert_id)return new WP_Error('mdg_ai_finance_insert_failed','Sabit gider kaydedilemedi.');
        return array('created'=>true,'fixed_cost_id'=>(int)$wpdb->insert_id,'salary_total'=>$salary,'tax_total'=>$tax,'total_amount'=>$salary+$tax);
    }
}

if ( ! function_exists( 'mdg_ai_v5_attendance_set' ) ) {
    function mdg_ai_v5_attendance_set( $input ) {
        global $wpdb;
        $event=absint($input['event_id']??0);
        $avg=(float)($input['avg_ticket_price']??0);
        if (($input['scope']??'program')==='program' &&
            (!class_exists('MDG_V5_Finance_Identity') ||
             !MDG_V5_Finance_Identity::valid($event.'|'.($input['event_uuid']??'')))) {
            return new WP_Error('mdg_ai_finance_identity','MDG etkinlik kimliği ve event_uuid birlikte doğrulanmalıdır; MMC program_id kullanılamaz.');
        }
        if(!$event||$avg<=0)return new WP_Error('mdg_ai_finance_required','Katılım kaydı için event_id ve ortalama bilet fiyatı zorunludur.');
        $data=array(
            'event_id'=>$event,'avg_ticket_price'=>$avg,'manual_attendance'=>absint($input['manual_attendance']??0),
            'include_in_reports'=>array_key_exists('include_in_reports',$input)?(!empty($input['include_in_reports'])?1:0):1,
            'notes'=>sanitize_textarea_field($input['notes']??''),'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')
        );
        $ok=$wpdb->replace(mdg_ai_v5_finance_table('attendance'),$data);
        if(false===$ok)return new WP_Error('mdg_ai_finance_attendance_failed','Katılım kaydedilemedi.');
        return array('updated'=>true,'event_id'=>$event);
    }
}

if ( ! function_exists( 'mdg_ai_v5_finance_delete' ) ) {
    function mdg_ai_v5_finance_delete( $input ) {
        global $wpdb;
        $type=sanitize_key($input['type']??'');
        $id=absint($input['id']??0);
        $map=array('expense'=>'expenses','income'=>'incomes','fixed_cost'=>'fixed_costs','attendance'=>'attendance');
        if(!$id||!isset($map[$type]))return new WP_Error('mdg_ai_finance_delete_invalid','Geçersiz finans kayıt türü veya ID.');
        $table=mdg_ai_v5_finance_table($map[$type]);
        $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id=%d",$id));
        if(!$exists)return new WP_Error('mdg_ai_finance_not_found','Finans kaydı bulunamadı.');
        $ok=$wpdb->delete($table,array('id'=>$id));
        if(false===$ok)return new WP_Error('mdg_ai_finance_delete_failed','Finans kaydı silinemedi.');
        return array('deleted'=>true,'type'=>$type,'id'=>$id);
    }
}

add_action('wp_abilities_api_categories_init',function(){
 if(function_exists('wp_register_ability_category')){
  wp_register_ability_category('madagaskar-finans-v5',array(
   'label'=>'Madagaskar V5 Finans',
   'description'=>'Gelir, gider, sabit gider, katılım ve kârlılık işlemleri.'
  ));
 }
});

add_action('wp_abilities_api_init',function(){
 if(!function_exists('wp_register_ability'))return;
 $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
 $write=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);
 $idemwrite=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>true),'public'=>true);
 wp_register_ability('madagaskar/finance-refund-cash-preview',array(
  'label'=>'Manuel İade Muhasebe Düzeltmesini Önizle','description'=>'Özel teyit planındaki manuel iade anaparası düzeltmesini salt okunur önizler; para iadesi başlatmaz.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>array('plan_id'=>array('type'=>'string','pattern'=>'^[a-zA-Z0-9_-]{8,64}$')),'required'=>array('plan_id'),'additionalProperties'=>false),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_refund_cash_preview','permission_callback'=>'mdg_ai_v5_refund_cash_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/finance-refund-cash-reconcile',array(
  'label'=>'Manuel İade Muhasebe Kaydını Mutabıklaştır','description'=>'Yalnız özel teyit planındaki manuel iade kaydını eski görüntü ve program UUID kontrolüyle gerekçeli düzeltir veya geri alır. Sipariş ve ödeme sağlayıcısında para iadesi başlatmaz.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>array('plan_id'=>array('type'=>'string','pattern'=>'^[a-zA-Z0-9_-]{8,64}$'),'operation'=>array('type'=>'string','enum'=>array('apply','rollback'))),'required'=>array('plan_id','operation'),'additionalProperties'=>false),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_refund_cash_reconcile','permission_callback'=>'mdg_ai_v5_refund_cash_can_run','meta'=>$write
 ));
 $filter=array(
  'month'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}$'),
  'event_id'=>array('type'=>'integer','minimum'=>1),
  'scope'=>array('type'=>'string','enum'=>array('program','province','general','person','vehicle')),
  'province'=>array('type'=>'string'),
  'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>500)
 );

 wp_register_ability('madagaskar/finance-expenses-list',array(
  'label'=>'Giderleri Listele','description'=>'V5 gider kayıtlarını ay, etkinlik, kapsam ve ile göre listeler.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>$filter),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_expenses_list','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/finance-incomes-list',array(
  'label'=>'Gelirleri Listele','description'=>'V5 gelir kayıtlarını ay, etkinlik, kapsam ve ile göre listeler.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>$filter),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_incomes_list','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/finance-fixed-costs-list',array(
  'label'=>'Sabit Giderleri Listele','description'=>'Aylık sabit gider kayıtlarını listeler.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>array(
   'month'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}$'),'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>500)
  )),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_fixed_costs_list','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/finance-attendance-list',array(
  'label'=>'Katılım Kayıtlarını Listele','description'=>'Etkinlik bazlı ortalama bilet fiyatı ve manuel katılım kayıtlarını getirir.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>array('event_id'=>array('type'=>'integer','minimum'=>1))),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_attendance_list','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/finance-summary',array(
  'label'=>'Gelir Gider ve Kârlılık Özeti','description'=>'Seçilen ay/etkinlik/kapsam için net gelir, gider, sabit gider, toplam maliyet ve kârı hesaplar.',
  'category'=>'madagaskar-finans-v5','input_schema'=>array('type'=>'object','properties'=>$filter),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_finance_summary','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/finance-expense-create',array(
  'label'=>'Gider Kaydı Oluştur','description'=>'V5 Gider ve Kârlılık modülüne mevcut form kurallarıyla yeni gider kaydı ekler. Kritik yazma işlemidir.',
  'category'=>'madagaskar-finans-v5',
  'input_schema'=>array('type'=>'object','properties'=>array(
   'scope'=>array('type'=>'string','enum'=>array('program','province','general','person','vehicle')),
   'event_id'=>array('type'=>'integer','minimum'=>1),'event_uuid'=>array('type'=>'string','description'=>'Program kapsamı için zorunlu MDG public_uuid; MMC kimliği değildir.'),'province'=>array('type'=>'string'),'related_name'=>array('type'=>'string'),
   'category'=>array('type'=>'string'),'description'=>array('type'=>'string'),'vendor'=>array('type'=>'string'),
   'amount'=>array('type'=>'number','exclusiveMinimum'=>0),'expense_date'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$'),
   'payment_method'=>array('type'=>'string'),'document_no'=>array('type'=>'string'),'notes'=>array('type'=>'string')
  ),'required'=>array('category','amount','expense_date')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_expense_create','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$write
 ));
 wp_register_ability('madagaskar/finance-income-create',array(
  'label'=>'Gelir Kaydı Oluştur','description'=>'V5 Gider ve Kârlılık modülüne mükerrer source_ref kontrolüyle yeni gelir kaydı ekler. Kritik yazma işlemidir.',
  'category'=>'madagaskar-finans-v5',
  'input_schema'=>array('type'=>'object','properties'=>array(
   'scope'=>array('type'=>'string','enum'=>array('program','province','general','person','vehicle')),
   'event_id'=>array('type'=>'integer','minimum'=>1),'event_uuid'=>array('type'=>'string','description'=>'Program kapsamı için zorunlu MDG public_uuid; MMC kimliği değildir.'),'province'=>array('type'=>'string'),'category'=>array('type'=>'string'),
   'channel'=>array('type'=>'string'),'description'=>array('type'=>'string'),'gross_amount'=>array('type'=>'number','exclusiveMinimum'=>0),
   'commission_amount'=>array('type'=>'number','minimum'=>0),'ticket_count'=>array('type'=>'integer','minimum'=>0),
   'income_date'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$'),'collection_status'=>array('type'=>'string'),
   'payment_method'=>array('type'=>'string'),'source_ref'=>array('type'=>'string'),'notes'=>array('type'=>'string')
  ),'required'=>array('channel','gross_amount','income_date')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_income_create','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$write
 ));
 wp_register_ability('madagaskar/finance-fixed-cost-create',array(
  'label'=>'Sabit Gider Kaydı Oluştur','description'=>'Aylık sabit gideri maaş/döviz/sigorta formülüyle hesaplayıp kaydeder. Kritik yazma işlemidir.',
  'category'=>'madagaskar-finans-v5',
  'input_schema'=>array('type'=>'object','properties'=>array(
   'cost_month'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}$'),'cost_type'=>array('type'=>'string'),'name'=>array('type'=>'string'),
   'headcount'=>array('type'=>'integer','minimum'=>0),'pay_amount'=>array('type'=>'number','minimum'=>0),'currency'=>array('type'=>'string'),
   'fx_rate'=>array('type'=>'number','exclusiveMinimum'=>0),'pay_basis'=>array('type'=>'string','enum'=>array('group','person')),
   'insured_count'=>array('type'=>'integer','minimum'=>0),'tax_per_person'=>array('type'=>'number','minimum'=>0),
   'document_no'=>array('type'=>'string'),'notes'=>array('type'=>'string')
  ),'required'=>array('cost_month','name','pay_amount')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_fixed_cost_create','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$write
 ));
 wp_register_ability('madagaskar/finance-attendance-set',array(
  'label'=>'Etkinlik Katılımını Kaydet','description'=>'Etkinlik için ortalama bilet fiyatı, manuel katılım ve rapora dahil edilme durumunu kaydeder. Kritik ve idempotent yazma işlemidir.',
  'category'=>'madagaskar-finans-v5',
  'input_schema'=>array('type'=>'object','properties'=>array(
   'event_id'=>array('type'=>'integer','minimum'=>1),'event_uuid'=>array('type'=>'string','description'=>'MDG public_uuid; program kapsamında zorunlu.'),'avg_ticket_price'=>array('type'=>'number','exclusiveMinimum'=>0),
   'manual_attendance'=>array('type'=>'integer','minimum'=>0),'include_in_reports'=>array('type'=>'boolean'),'notes'=>array('type'=>'string')
  ),'required'=>array('event_id','avg_ticket_price')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_attendance_set','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$idemwrite
 ));
 wp_register_ability('madagaskar/finance-delete',array(
  'label'=>'Finans Kaydını Sil','description'=>'Gelir, gider, sabit gider veya katılım kaydını siler. Geri alınamaz kritik işlemdir.',
  'category'=>'madagaskar-finans-v5',
  'input_schema'=>array('type'=>'object','properties'=>array(
   'type'=>array('type'=>'string','enum'=>array('expense','income','fixed_cost','attendance')),
   'id'=>array('type'=>'integer','minimum'=>1)
  ),'required'=>array('type','id')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_v5_finance_delete','permission_callback'=>'mdg_ai_v5_finance_can_run','meta'=>$write
 ));
});
