<?php
if ( ! function_exists( 'mdg_ai_ops_can_run' ) ) {
    function mdg_ai_ops_can_run( $input = null ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'mdg_ai_ops_forbidden', 'Bu işlem için WooCommerce yönetim yetkisi gerekir.' );
        }
        if ( ! class_exists( 'MDG_DB' ) ) {
            return new WP_Error( 'mdg_ai_ops_mdg_missing', 'Madagaskar Bilet Yönetimi veri katmanı kullanılamıyor.' );
        }
        return true;
    }
}

if ( ! function_exists( 'mdg_ai_customer_ticket_filters' ) ) {
    function mdg_ai_customer_ticket_filters( $input ) {
        $range=sanitize_key($input['range']??'30d');
        if(!in_array($range,array('today','yesterday','7d','30d','all','custom'),true))$range='30d';
        $date_from=sanitize_text_field($input['date_from']??'');
        $date_to=sanitize_text_field($input['date_to']??'');
        $tz=wp_timezone(); $now=new DateTimeImmutable('now',$tz); $start=null; $end=null;
        if('today'===$range){$start=$now->setTime(0,0,0);$end=$start->modify('+1 day');}
        elseif('yesterday'===$range){$end=$now->setTime(0,0,0);$start=$end->modify('-1 day');}
        elseif('7d'===$range){$end=$now->setTime(0,0,0)->modify('+1 day');$start=$now->setTime(0,0,0)->modify('-6 days');}
        elseif('30d'===$range){$end=$now->setTime(0,0,0)->modify('+1 day');$start=$now->setTime(0,0,0)->modify('-29 days');}
        elseif('custom'===$range){
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from))$start=new DateTimeImmutable($date_from.' 00:00:00',$tz);
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to))$end=(new DateTimeImmutable($date_to.' 00:00:00',$tz))->modify('+1 day');
        }
        if($start&&!$date_from)$date_from=$start->format('Y-m-d');
        if($end&&!$date_to)$date_to=$end->modify('-1 day')->format('Y-m-d');
        $utc=new DateTimeZone('UTC');
        $status=sanitize_key($input['order_status']??'paid');
        if(!in_array($status,array('paid','all','processing','completed','on-hold','pending','refunded','cancelled','failed'),true))$status='paid';
        return array(
            'range'=>$range,'date_from'=>$date_from,'date_to'=>$date_to,
            'utc_from'=>$start?$start->setTimezone($utc)->format('Y-m-d H:i:s'):'',
            'utc_to'=>$end?$end->setTimezone($utc)->format('Y-m-d H:i:s'):'',
            'province'=>sanitize_text_field($input['province_code']??''),
            'event_id'=>absint($input['event_id']??0),'session_id'=>absint($input['session_id']??0),
            'ticket_type_id'=>absint($input['ticket_type_id']??0),'order_status'=>$status,
            'order_id'=>absint($input['order_id']??0),'paged'=>max(1,absint($input['page']??1)),
            'per_page'=>max(1,min(200,absint($input['per_page']??50)))
        );
    }
}

if ( ! function_exists( 'mdg_ai_customer_ticket_local_datetime' ) ) {
    function mdg_ai_customer_ticket_local_datetime( $utc_datetime ) {
        $value=trim((string)$utc_datetime);
        if(''===$value)return '';
        return get_date_from_gmt($value,'Y-m-d H:i:s');
    }
}

if ( ! function_exists( 'mdg_ai_customer_tickets_query' ) ) {
    function mdg_ai_customer_tickets_query( $input = array() ) {
        global $wpdb;
        $f=mdg_ai_customer_ticket_filters($input);
        $m=MDG_DB::table('order_map'); $e=MDG_DB::table('events'); $s=MDG_DB::table('sessions'); $t=MDG_DB::table('ticket_types');

        $where=array('1=1'); $args=array();
        if($f['utc_from']){$where[]='COALESCE(m.paid_at,m.created_at) >= %s';$args[]=$f['utc_from'];}
        if($f['utc_to']){$where[]='COALESCE(m.paid_at,m.created_at) < %s';$args[]=$f['utc_to'];}
        if($f['province']){$where[]='e.province_code=%s';$args[]=$f['province'];}
        if($f['event_id']){$where[]='m.event_id=%d';$args[]=$f['event_id'];}
        if($f['session_id']){$where[]='m.session_id=%d';$args[]=$f['session_id'];}
        if($f['ticket_type_id']){$where[]='m.ticket_type_id=%d';$args[]=$f['ticket_type_id'];}
        if($f['order_id']){$where[]='m.order_id=%d';$args[]=$f['order_id'];}
        if('paid'===$f['order_status']){$where[]='m.paid_at IS NOT NULL';$where[]="m.order_status NOT IN ('failed','cancelled','refunded','trash')";}
        elseif('all'!==$f['order_status']){$where[]='m.order_status=%s';$args[]=$f['order_status'];}
        $where_sql=implode(' AND ',$where);
        $prepare=static function($sql,$extra=array())use($wpdb,$args){$all=array_merge($args,$extra);return $all?$wpdb->prepare($sql,$all):$sql;};

        $summary=$wpdb->get_row($prepare("SELECT COUNT(DISTINCT m.order_id) orders,COALESCE(SUM(m.quantity),0) tickets,COALESCE(SUM(m.units_total),0) units,COALESCE(SUM(m.line_total),0) revenue FROM {$m} m JOIN {$e} e ON e.id=m.event_id WHERE {$where_sql}"),ARRAY_A);
        $summary=array_merge(array('orders'=>0,'tickets'=>0,'units'=>0,'revenue'=>0),(array)$summary);
        $total_rows=(int)$wpdb->get_var($prepare("SELECT COUNT(*) FROM {$m} m JOIN {$e} e ON e.id=m.event_id WHERE {$where_sql}"));
        $limit=(int)$f['per_page']; $offset=max(0,($f['paged']-1)*$limit);

        $rows=$wpdb->get_results($prepare(
            "SELECT m.*,e.title,e.province_name,e.province_code,e.district,e.venue_name,s.start_at,t.label ticket_label,t.code ticket_code,t.price ticket_price
             FROM {$m} m JOIN {$e} e ON e.id=m.event_id JOIN {$s} s ON s.id=m.session_id JOIN {$t} t ON t.id=m.ticket_type_id
             WHERE {$where_sql}
             ORDER BY COALESCE(m.paid_at,m.created_at) DESC,m.order_id DESC,m.order_item_id DESC LIMIT %d OFFSET %d",
            array($limit,$offset)
        ));

        $order_cache=array(); $items=array();
        foreach((array)$rows as $row){
            $oid=(int)$row->order_id;
            if(!array_key_exists($oid,$order_cache))$order_cache[$oid]=function_exists('wc_get_order')?wc_get_order($oid):null;
            $order=$order_cache[$oid];
            $items[]=array(
                'order_id'=>$oid,'order_item_id'=>(int)$row->order_item_id,'event_id'=>(int)$row->event_id,
                'session_id'=>(int)$row->session_id,'ticket_type_id'=>(int)$row->ticket_type_id,
                'event_title'=>(string)$row->title,'province_name'=>(string)$row->province_name,'province_code'=>(string)$row->province_code,
                'district'=>(string)$row->district,'venue_name'=>(string)$row->venue_name,'session_start'=>mdg_ai_customer_ticket_local_datetime($row->start_at),
                'ticket_label'=>(string)$row->ticket_label,'ticket_code'=>(string)$row->ticket_code,'ticket_price'=>(float)$row->ticket_price,
                'quantity'=>(int)$row->quantity,'units_total'=>(int)$row->units_total,'line_total'=>(float)$row->line_total,
                'mapped_order_status'=>(string)$row->order_status,'paid_at'=>(string)($row->paid_at??''),
                'customer_name'=>$order?trim((string)$order->get_formatted_billing_full_name()):'',
                'phone'=>$order?(string)$order->get_billing_phone():'',
                'email'=>$order?(string)$order->get_billing_email():'',
                'woocommerce_status'=>$order?(string)$order->get_status():'',
                'woocommerce_total'=>$order?(float)$order->get_total():0
            );
        }
        return array(
            'filters'=>$f,
            'summary'=>array(
                'orders'=>(int)$summary['orders'],'tickets'=>(int)$summary['tickets'],
                'units'=>(int)$summary['units'],'revenue'=>(float)$summary['revenue']
            ),
            'total_rows'=>$total_rows,'page'=>$f['paged'],'per_page'=>$limit,'items'=>$items
        );
    }
}

if ( ! function_exists( 'mdg_ai_customer_ticket_lookups' ) ) {
    function mdg_ai_customer_ticket_lookups( $input = array() ) {
        global $wpdb;
        $f=mdg_ai_customer_ticket_filters($input);
        $e=MDG_DB::table('events'); $s=MDG_DB::table('sessions'); $t=MDG_DB::table('ticket_types');
        $provinces=$wpdb->get_results("SELECT DISTINCT province_code,province_name FROM {$e} ORDER BY province_name");
        $events=$f['province']
            ? $wpdb->get_results($wpdb->prepare("SELECT id,title,province_name,province_code FROM {$e} WHERE province_code=%s ORDER BY created_at DESC",$f['province']))
            : $wpdb->get_results("SELECT id,title,province_name,province_code FROM {$e} ORDER BY created_at DESC");
        $sessions=$f['event_id']?$wpdb->get_results($wpdb->prepare("SELECT id,start_at,event_id FROM {$s} WHERE event_id=%d ORDER BY start_at ASC",$f['event_id'])):array();
        foreach((array)$sessions as $session){
            $session->start_at_utc=(string)$session->start_at;
            $session->start_at=mdg_ai_customer_ticket_local_datetime($session->start_at);
        }
        $types=$f['session_id']?$wpdb->get_results($wpdb->prepare("SELECT id,label,code,price FROM {$t} WHERE is_active=1 AND session_id=%d ORDER BY sort_order,label",$f['session_id'])):array();
        return array('provinces'=>$provinces,'events'=>$events,'sessions'=>$sessions,'ticket_types'=>$types);
    }
}

if ( ! function_exists( 'mdg_ai_sales_audit' ) ) {
    function mdg_ai_sales_audit( $input = array() ) {
        if(!class_exists('MDG_Sales_Audit')||!method_exists('MDG_Sales_Audit','report'))return new WP_Error('mdg_ai_audit_missing','Satış denetim servisi bulunamadı.');
        return MDG_Sales_Audit::report();
    }
}

if ( ! function_exists( 'mdg_ai_daily_report_snapshot' ) ) {
    function mdg_ai_daily_report_snapshot( $input ) {
        if(!class_exists('MMC_Report_Service'))return new WP_Error('mdg_ai_report_missing','MMC rapor servisi bulunamadı.');
        $date=sanitize_text_field($input['report_date']??'');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return new WP_Error('mdg_ai_report_date','report_date YYYY-AA-GG olmalıdır.');
        return array('report_date'=>$date,'snapshot'=>MMC_Report_Service::snapshot($date));
    }
}

if ( ! function_exists( 'mdg_ai_report_runs' ) ) {
    function mdg_ai_report_runs( $input = array() ) {
        if(!class_exists('MMC_Report_Service'))return new WP_Error('mdg_ai_report_missing','MMC rapor servisi bulunamadı.');
        $limit=max(1,min(100,absint($input['limit']??20)));
        return array('next_run'=>MMC_Report_Service::next_run(),'recipient'=>MMC_Report_Service::recipient(),'report_hour'=>MMC_Report_Service::report_hour(),'items'=>MMC_Report_Service::recent_runs($limit));
    }
}

if ( ! function_exists( 'mdg_ai_report_run_get' ) ) {
    function mdg_ai_report_run_get( $input ) {
        if(!class_exists('MMC_Report_Service'))return new WP_Error('mdg_ai_report_missing','MMC rapor servisi bulunamadı.');
        $id=absint($input['run_id']??0);
        $run=MMC_Report_Service::get_run($id);
        if(!$run)return new WP_Error('mdg_ai_report_run_missing','Rapor çalışması bulunamadı.');
        return array('run'=>$run);
    }
}

if ( ! function_exists( 'mdg_ai_report_send_now' ) ) {
    function mdg_ai_report_send_now( $input ) {
        if(!class_exists('MMC_Report_Service'))return new WP_Error('mdg_ai_report_missing','MMC rapor servisi bulunamadı.');
        $date=sanitize_text_field($input['report_date']??'');
        if($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return new WP_Error('mdg_ai_report_date','report_date YYYY-AA-GG olmalıdır.');
        $force=!empty($input['force']);
        $result=MMC_Report_Service::generate_and_send($date,'ai',$force);
        if(is_wp_error($result))return $result;
        return array('sent'=>true,'result'=>$result);
    }
}

add_action('wp_abilities_api_categories_init',function(){
 if(function_exists('wp_register_ability_category')){
  wp_register_ability_category('madagaskar-rapor-musteri',array(
   'label'=>'Madagaskar Satış Raporları ve Müşteri/Bilet',
   'description'=>'Satış sistemi denetimi, müşteri/bilet operasyon listeleri ve MMC günlük raporları.'
  ));
 }
});

add_action('wp_abilities_api_init',function(){
 if(!function_exists('wp_register_ability'))return;
 $read=array('annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true),'public'=>true);
 $critical=array('annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false),'public'=>true);

 $filters=array(
  'range'=>array('type'=>'string','enum'=>array('today','yesterday','7d','30d','all','custom')),
  'date_from'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$'),
  'date_to'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$'),
  'province_code'=>array('type'=>'string'),'event_id'=>array('type'=>'integer','minimum'=>1),
  'session_id'=>array('type'=>'integer','minimum'=>1),'ticket_type_id'=>array('type'=>'integer','minimum'=>1),
  'order_status'=>array('type'=>'string','enum'=>array('paid','all','processing','completed','on-hold','pending','refunded','cancelled','failed')),
  'order_id'=>array('type'=>'integer','minimum'=>1),'page'=>array('type'=>'integer','minimum'=>1),
  'per_page'=>array('type'=>'integer','minimum'=>1,'maximum'=>200)
 );

 wp_register_ability('madagaskar/customer-tickets-query',array(
  'label'=>'Müşteri / Bilet Listesini Sorgula',
  'description'=>'Madagaskar Müşteri/Bilet ekranının kendi order_map veri modelini kullanarak sipariş, müşteri, etkinlik, seans ve bilet türü satırlarını getirir.',
  'category'=>'madagaskar-rapor-musteri','input_schema'=>array('type'=>'object','properties'=>$filters),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_customer_tickets_query','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/customer-ticket-lookups',array(
  'label'=>'Müşteri/Bilet Filtre Seçeneklerini Getir',
  'description'=>'Müşteri/Bilet ekranındaki il, etkinlik, seans ve bilet türü filtre seçeneklerini getirir.',
  'category'=>'madagaskar-rapor-musteri','input_schema'=>array('type'=>'object','properties'=>$filters),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_customer_ticket_lookups','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/sales-system-audit',array(
  'label'=>'Satış Sistemini Denetle',
  'description'=>'WooCommerce, HPOS, Tickera, Bridge ve örnek bilet ürünleri için mevcut Madagaskar satış denetim raporunu getirir.',
  'category'=>'madagaskar-rapor-musteri','input_schema'=>array('type'=>'object','properties'=>array()),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_sales_audit','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/daily-report-snapshot',array(
  'label'=>'Günlük Rapor Anlık Görünümünü Getir',
  'description'=>'MMC günlük rapor motorunun seçilen tarih için oluşturduğu salt-okunur snapshot verisini getirir.',
  'category'=>'madagaskar-rapor-musteri',
  'input_schema'=>array('type'=>'object','properties'=>array('report_date'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$')),'required'=>array('report_date')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_daily_report_snapshot','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/report-runs',array(
  'label'=>'Günlük Rapor Geçmişini Getir',
  'description'=>'MMC günlük raporunun alıcısını, saatini, sonraki çalışmasını ve yakın rapor çalıştırmalarını getirir.',
  'category'=>'madagaskar-rapor-musteri',
  'input_schema'=>array('type'=>'object','properties'=>array('limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>100))),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_report_runs','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/report-run-get',array(
  'label'=>'Tek Rapor Çalışmasını Getir','description'=>'Rapor çalışma ID’sine göre kayıt detayını getirir.',
  'category'=>'madagaskar-rapor-musteri',
  'input_schema'=>array('type'=>'object','properties'=>array('run_id'=>array('type'=>'integer','minimum'=>1)),'required'=>array('run_id')),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_report_run_get','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$read
 ));
 wp_register_ability('madagaskar/report-send-now',array(
  'label'=>'Günlük Raporu Şimdi Gönder',
  'description'=>'MMC günlük rapor motoruyla seçilen tarih raporunu yapılandırılmış alıcıya e-posta olarak gönderir. Harici e-posta gönderdiği için kritik işlemdir.',
  'category'=>'madagaskar-rapor-musteri',
  'input_schema'=>array('type'=>'object','properties'=>array(
    'report_date'=>array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$'),'force'=>array('type'=>'boolean')
  )),
  'output_schema'=>array('type'=>'object'),'execute_callback'=>'mdg_ai_report_send_now','permission_callback'=>'mdg_ai_ops_can_run','meta'=>$critical
 ));
});
