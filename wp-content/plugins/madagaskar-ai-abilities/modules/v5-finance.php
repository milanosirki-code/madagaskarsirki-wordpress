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

if ( ! function_exists( 'mdg_ai_v5_finance_summary' ) ) {
    function mdg_ai_v5_finance_summary( $input = array() ) {
        global $wpdb;
        $summary=array();
        foreach(array('expenses','incomes','fixed_costs') as $type){
            $params=array();
            $where=mdg_ai_v5_finance_where($type,$input,$params);
            if('expenses'===$type)$sql='SELECT COUNT(*) c,COALESCE(SUM(amount),0) total FROM '.mdg_ai_v5_finance_table($type).' WHERE '.$where;
            elseif('incomes'===$type)$sql='SELECT COUNT(*) c,COALESCE(SUM(gross_amount),0) gross,COALESCE(SUM(commission_amount),0) commission,COALESCE(SUM(net_amount),0) net,COALESCE(SUM(ticket_count),0) tickets FROM '.mdg_ai_v5_finance_table($type).' WHERE '.$where;
            else $sql='SELECT COUNT(*) c,COALESCE(SUM(total_amount),0) total FROM '.mdg_ai_v5_finance_table($type).' WHERE '.$where;
            $row=$params?$wpdb->get_row($wpdb->prepare($sql,$params)):$wpdb->get_row($sql);
            $summary[$type]=$row;
        }
        $income_net=(float)$summary['incomes']->net;
        $expenses=(float)$summary['expenses']->total;
        $fixed=(float)$summary['fixed_costs']->total;
        return array(
            'filters'=>array(
                'month'=>sanitize_text_field($input['month']??''),
                'event_id'=>absint($input['event_id']??0),
                'scope'=>sanitize_key($input['scope']??''),
                'province'=>sanitize_text_field($input['province']??'')
            ),
            'income'=>array(
                'count'=>(int)$summary['incomes']->c,'gross'=>(float)$summary['incomes']->gross,
                'commission'=>(float)$summary['incomes']->commission,'net'=>$income_net,'ticket_count'=>(int)$summary['incomes']->tickets
            ),
            'expenses'=>array('count'=>(int)$summary['expenses']->c,'total'=>$expenses),
            'fixed_costs'=>array('count'=>(int)$summary['fixed_costs']->c,'total'=>$fixed),
            'total_cost'=>$expenses+$fixed,
            'profit'=>$income_net-$expenses-$fixed
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
   'event_id'=>array('type'=>'integer','minimum'=>1),'province'=>array('type'=>'string'),'related_name'=>array('type'=>'string'),
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
   'event_id'=>array('type'=>'integer','minimum'=>1),'province'=>array('type'=>'string'),'category'=>array('type'=>'string'),
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
   'event_id'=>array('type'=>'integer','minimum'=>1),'avg_ticket_price'=>array('type'=>'number','exclusiveMinimum'=>0),
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
