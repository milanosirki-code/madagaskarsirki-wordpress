<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_V5_Finance {
    const DB_VERSION = '2.2.1';
    const DB_OPTION = 'mdg_v5_finance_db_version';
    const COUNT_OPTION = 'mdg_v5_month_program_counts';
    const ALLOCATION_OPTION = 'mdg_v5_expense_allocations_v1';
    const CAP = 'manage_woocommerce';

    public static function boot() {
        MDG_V5_Finance_Records::boot();
        if ( get_option( self::DB_OPTION, '' ) !== self::DB_VERSION ) { self::create_tables(); }
        foreach ( array( 'expense', 'income', 'fixed_cost' ) as $type ) {
            add_action( 'admin_post_mdg_v5_save_' . $type, array( __CLASS__, 'save_' . $type ) );
            add_action( 'admin_post_mdg_v5_delete_' . $type, array( __CLASS__, 'delete_' . $type ) );
        }
        add_action( 'admin_post_mdg_v5_save_program_count', array( __CLASS__, 'save_program_count' ) );
        add_action( 'admin_post_mdg_v5_save_attendance', array( __CLASS__, 'save_attendance' ) );
        add_action( 'admin_post_mdg_v5_save_allocation', array( __CLASS__, 'save_allocation' ) );
    }

    private static function table( $name ) { global $wpdb; return $wpdb->prefix . 'mdg_v5_' . $name; }

    /** Allocate cents without creating expense rows. Invalid/stale rules fail closed. */
    private static function allocation_shares($row, $rule) {
        if (!$row || 'program' === $row->scope || self::is_ticket_refund($row) || !is_array($rule)) return array();
        $cents = (int)round((float)$row->amount * 100);
        if ($cents <= 0 || $cents !== (int)($rule['amount_cents'] ?? -1)) return array();
        $weights = $rule['weights'] ?? array();
        if (!is_array($weights) || !$weights) return array();
        $sum = 0;
        foreach ($weights as $id => $weight) {
            if (!preg_match('/^[1-9][0-9]*$/', (string)$id) || !is_int($weight) || $weight <= 0 || $weight > 10000) return array();
            $sum += $weight;
        }
        if (10000 !== $sum) return array();
        ksort($weights, SORT_NUMERIC); $shares = array(); $used = 0; $remainders = array();
        foreach ($weights as $id => $weight) {
            $part = intdiv($cents * $weight, 10000); $shares[$id] = $part; $used += $part;
            $remainders[$id] = ($cents * $weight) % 10000;
        }
        arsort($remainders, SORT_NUMERIC);
        foreach ($remainders as $id => $unused) { if ($used >= $cents) break; $shares[$id]++; $used++; }
        foreach ($shares as $id => $part) $shares[$id] = $part / 100;
        return $shares;
    }

    public static function allocated_rows($rows, $event_id = 0) {
        $rules = (array)get_option(self::ALLOCATION_OPTION, array()); $out = array();
        $valid = array(); foreach (self::events() as $event) $valid[(int)$event->id] = true;
        foreach ($rows as $row) {
            $shares = self::allocation_shares($row, $rules[$row->id] ?? null);
            if (array_diff_key($shares, $valid)) continue;
            foreach ($shares as $id => $amount) {
                if ($event_id && (int)$id !== (int)$event_id) continue;
                $out[] = array('event_id'=>(int)$id, 'amount'=>$amount, 'source'=>$row);
            }
        }
        return $out;
    }

    public static function save_allocation() {
        if (!current_user_can(self::CAP)) wp_die('Yetkiniz yok.');
        check_admin_referer('mdg_v5_save_allocation','mdg_v5_finance_nonce'); global $wpdb;
        $id = absint($_POST['expense_id'] ?? 0);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('expenses').' WHERE id=%d', $id));
        if (!$row || 'program' === $row->scope || self::is_ticket_refund($row)) wp_die('Bu gider dağıtılamaz.');
        $cents = (int)round((float)$row->amount * 100);
        if ($cents <= 0 || $cents !== (int)($_POST['expected_cents'] ?? -1)) wp_die('Gider tutarı değişmiş. Sayfayı yenileyin.');
        $rules = (array)get_option(self::ALLOCATION_OPTION, array());
        $previous = $rules[$id] ?? null;
        if (isset($_POST['allocation_remove'])) {
            $entry = array('amount_cents'=>$cents, 'weights'=>array());
        } else {
            $valid = array(); foreach (self::events() as $event) $valid[(int)$event->id] = true;
            $weights = array(); $input = $_POST['allocation_percent'] ?? array();
            if (!is_array($input)) wp_die('Payları kontrol edin.');
            foreach ($input as $event_id => $value) {
                if (!is_scalar($value)) wp_die('Payları kontrol edin.');
                $value = trim(str_replace(',', '.', (string)wp_unslash($value)));
                if ('' === $value || '0' === $value) continue;
                if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $value)) wp_die('Yüzde 0–100 aralığında ve en fazla iki ondalık olmalı.');
                $weight = (int)round((float)$value * 100);
                if (!$weight) continue;
                if (!isset($valid[(int)$event_id]) || $weight > 10000) wp_die('Program veya yüzde geçersiz.');
                $weights[(int)$event_id] = $weight;
            }
            $entry = array('amount_cents'=>$cents, 'weights'=>$weights);
            if (!self::allocation_shares($row, $entry)) wp_die('Seçilen programların toplam payı yüzde 100 olmalı.');
        }
        $entry['updated_by'] = get_current_user_id(); $entry['updated_at'] = current_time('mysql');
        $entry['previous'] = $previous ? array_intersect_key($previous, array_flip(array('amount_cents','weights','updated_by','updated_at'))) : null;
        $rules[$id] = $entry;
        if (!update_option(self::ALLOCATION_OPTION, $rules, false) && get_option(self::ALLOCATION_OPTION) !== $rules) wp_die('Dağıtım kaydedilemedi.');
        self::go_saved('expense', substr($row->expense_date,0,7));
    }

    private static function allocation_form($row, $names) {
        if ('program' === $row->scope || self::is_ticket_refund($row)) return;
        $rules = (array)get_option(self::ALLOCATION_OPTION, array()); $rule = $rules[$row->id] ?? array();
        $shares = self::allocation_shares($row, $rule);
        echo '<details><summary>Programlara dağıt'.($shares ? ' — kayıtlı' : '').'</summary><p>Bu gider tek kayıt olarak kalır. Payların toplamı %100 olmalı; işletme toplamı değişmez.</p>';
        if (!empty($rule['weights']) && !$shares) echo '<p><strong>Tutar veya dağıtım değişmiş. Payları yeniden kaydedin; eski paylar hesaba katılmıyor.</strong></p>';
        foreach ($shares as $id => $amount) echo '<p>'.esc_html(($names[$id] ?? '#'.$id).' · '.($rule['weights'][$id]/100).'% · '.self::money($amount)).'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_save_allocation"><input type="hidden" name="expense_id" value="'.absint($row->id).'"><input type="hidden" name="expected_cents" value="'.(int)round((float)$row->amount*100).'">';
        self::context_fields();wp_nonce_field('mdg_v5_save_allocation','mdg_v5_finance_nonce');
        foreach ($names as $id => $name) echo '<p><label>'.esc_html($name.' #'.$id).' <input aria-label="'.esc_attr('Pay % #'.$id).'" type="number" name="allocation_percent['.absint($id).']" min="0" max="100" step="0.01" value="'.esc_attr(($rule['weights'][$id] ?? 0)/100).'"> %</label></p>';
        echo '<button class="button button-primary">Dağıtımı Kaydet</button> <button class="button" name="allocation_remove" value="1">Dağıtımı Kaldır</button></form></details>';
    }

    private static function create_tables() {
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php'; $c = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE " . self::table('expenses') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            scope VARCHAR(30) NOT NULL DEFAULT 'program',
            province VARCHAR(100) NOT NULL DEFAULT '',
            related_name VARCHAR(190) NOT NULL DEFAULT '',
            category VARCHAR(100) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            vendor VARCHAR(190) NOT NULL DEFAULT '',
            amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            expense_date DATE NOT NULL,
            payment_method VARCHAR(60) NOT NULL DEFAULT '',
            document_no VARCHAR(100) NOT NULL DEFAULT '',
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY expense_date (expense_date),
            KEY scope (scope),
            KEY category (category)
        ) {$c};" );
        dbDelta( "CREATE TABLE " . self::table('incomes') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            scope VARCHAR(30) NOT NULL DEFAULT 'program',
            province VARCHAR(100) NOT NULL DEFAULT '',
            category VARCHAR(100) NOT NULL DEFAULT '',
            channel VARCHAR(100) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            gross_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            commission_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            ticket_count INT UNSIGNED NOT NULL DEFAULT 0,
            income_date DATE NOT NULL,
            collection_status VARCHAR(30) NOT NULL DEFAULT 'collected',
            payment_method VARCHAR(60) NOT NULL DEFAULT '',
            source_ref VARCHAR(150) NOT NULL DEFAULT '',
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY income_date (income_date),
            KEY channel (channel),
            KEY source_ref (source_ref)
        ) {$c};" );
        dbDelta( "CREATE TABLE " . self::table('fixed_costs') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cost_month CHAR(7) NOT NULL,
            cost_type VARCHAR(30) NOT NULL DEFAULT 'operating',
            name VARCHAR(190) NOT NULL DEFAULT '',
            headcount INT UNSIGNED NOT NULL DEFAULT 0,
            pay_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'TRY',
            fx_rate DECIMAL(18,6) NOT NULL DEFAULT 1,
            pay_basis VARCHAR(30) NOT NULL DEFAULT 'group',
            insured_count INT UNSIGNED NOT NULL DEFAULT 0,
            tax_per_person DECIMAL(18,2) NOT NULL DEFAULT 0,
            salary_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            tax_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            document_no VARCHAR(100) NOT NULL DEFAULT '',
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY cost_month (cost_month),
            KEY cost_type (cost_type)
        ) {$c};" );
        dbDelta( "CREATE TABLE " . self::table('attendance') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            avg_ticket_price DECIMAL(18,2) NOT NULL DEFAULT 0,
            manual_attendance INT UNSIGNED NOT NULL DEFAULT 0,
            include_in_reports TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            notes TEXT NULL,
            updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_id (event_id)
        ) {$c};" );
        $expected_columns = array(
            'expenses' => array('id','event_id','scope','province','related_name','category','description','vendor','amount','expense_date','payment_method','document_no','attachment_id','notes','created_by','created_at','updated_at'),
            'incomes' => array('id','event_id','scope','province','category','channel','description','gross_amount','commission_amount','net_amount','ticket_count','income_date','collection_status','payment_method','source_ref','attachment_id','notes','created_by','created_at','updated_at'),
            'fixed_costs' => array('id','cost_month','cost_type','name','headcount','pay_amount','currency','fx_rate','pay_basis','insured_count','tax_per_person','salary_total','tax_total','total_amount','document_no','attachment_id','notes','created_by','created_at','updated_at'),
            'attendance' => array('id','event_id','avg_ticket_price','manual_attendance','include_in_reports','notes','updated_by','updated_at'),
        );
        foreach ($expected_columns as $name => $expected) {
            $actual = (array)$wpdb->get_col('SHOW COLUMNS FROM ' . self::table($name));
            if (array_diff($expected, $actual)) { return; }
        }
        update_option( self::DB_OPTION, self::DB_VERSION, false );
    }

    private static function events() { global $wpdb; return class_exists('MDG_DB') ? (array)$wpdb->get_results('SELECT * FROM '.MDG_DB::table('events').' ORDER BY id DESC LIMIT 1000') : array(); }
    private static function date_from_record($record) {
        foreach(array('event_date','session_date','start_date','starts_at','start_at','start_datetime','datetime','start_time','date') as$f){
            if(empty($record->$f)||!is_scalar($record->$f))continue;
            $raw=str_replace('T',' ',trim((string)$record->$f));
            if(preg_match('/^(\d{4}-\d{2}-\d{2})$/',$raw,$m))return$m[1];
            if(preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}/',$raw))return function_exists('get_date_from_gmt')?get_date_from_gmt(substr($raw,0,19),'Y-m-d'):substr($raw,0,10);
        }
        return'';
    }
    public static function event_date($e) {
        static $cache=array();$id=absint($e->id??0);if($id&&isset($cache[$id]))return$cache[$id];
        $date=self::date_from_record($e);
        if(!$date&&$id&&class_exists('MDG_Sessions')){
            $dates=array();foreach((array)MDG_Sessions::by_event($id)as$s){$d=self::date_from_record($s);if($d)$dates[]=$d;}
            if($dates){sort($dates,SORT_STRING);$date=$dates[0];}
        }
        if($id)$cache[$id]=$date;return$date;
    }
    public static function event_name($e) { $t=trim((string)($e->title??''));return$t?:trim((string)($e->province_name??'').' '.(string)($e->district??'')); }
    private static function expense_categories(){return array('Salon Kirası','Temizlik / Güvenlik','Sanatçı / Personel','Ulaşım / Yakıt','Konaklama','Yemek','Reklam / Sosyal Medya','Afiş / Tanıtım','Teknik / Ses / Işık','Kantin Malzemesi','Araç Bakım / Onarım','Araç Sigorta / Kasko','Vize / Davetiye','Çalışma İzni / Harç','SGK / Sigorta','Muhasebe','Vergi / Resmî Harç','Kira / Aidat','Elektrik / Su / Doğalgaz','Ofis / Genel Yönetim','Diğer');}
    private static function income_categories(){return array('Bilet Satışı','Kantin Yiyecek / Mısır','Kantin Oyuncak','Sponsorluk','Kurumsal / Toplu Satış','Diğer');}
    private static function income_channels(){return array('Biletinial','Diğer Bilet Şirketi','Kapı Nakit','Kapı POS / Kredi Kartı','Havale / EFT','Kantin Nakit','Kantin POS / Kredi Kartı','Diğer');}
    private static function payment_methods(){return array('Nakit','Kredi Kartı / POS','Banka Havalesi / EFT','Şirket Kartı','Ödenmedi','Diğer');}
    private static function money_value($v){$r=preg_replace('/[^0-9,.-]/','',trim((string)wp_unslash($v)));if(false!==strpos($r,',')){$r=str_replace('.','',$r);$r=str_replace(',','.',$r);}return round((float)$r,2);}
    private static function date_ok($d){return(bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$d);}
    private static function month_ok($m){return(bool)preg_match('/^\d{4}-\d{2}$/',(string)$m);}
    public static function context_event(){
        if(isset($_REQUEST['summary_event'])) return absint($_REQUEST['summary_event']);
        $pid=absint($_REQUEST['program_id']??0);
        if($pid&&class_exists('MMC_MDG_Bridge_Service')){$bridge=MMC_MDG_Bridge_Service::bridge_for_program($pid);if($bridge)return absint($bridge->mdg_event_id);}
        return 0;
    }
    public static function url($a=array()){return add_query_arg(array_merge(array('page'=>'mdg-v5-finance','summary_event'=>self::context_event(),'month'=>sanitize_text_field(wp_unslash($_REQUEST['month']??''))),$a),admin_url('admin.php'));}
    private static function context_fields(){echo '<input type="hidden" name="summary_event" value="'.self::context_event().'"><input type="hidden" name="month" value="'.esc_attr(sanitize_text_field(wp_unslash($_REQUEST['month']??wp_date('Y-m')))).'">';}
    private static function upload($field,$current=0){if(empty($_FILES[$field]['name']))return absint($current);require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';$id=media_handle_upload($field,0,array(),array('test_form'=>false));return is_wp_error($id)?$id:absint($id);}

    public static function save_expense(){
        if(!current_user_can(self::CAP))wp_die('Yetkiniz yok.');check_admin_referer('mdg_v5_save_expense','mdg_v5_finance_nonce');global$wpdb;
        $scope=sanitize_key($_POST['scope']??'program');$event=absint($_POST['event_id']??0);$amount=self::money_value($_POST['amount']??'');$date=sanitize_text_field(wp_unslash($_POST['expense_date']??''));$cat=sanitize_text_field(wp_unslash($_POST['category']??''));
        if(!in_array($scope,array('program','province','general','person','vehicle'),true))$scope='program';
        if($amount<=0||!self::date_ok($date)||!$cat||('program'===$scope&&!$event)){self::go_error('required','expense');}
        $att=self::upload('document');if(is_wp_error($att))self::go_error('upload','expense');$now=current_time('mysql');
        $saved = $wpdb->insert(self::table('expenses'),array('event_id'=>$event,'scope'=>$scope,'province'=>sanitize_text_field(wp_unslash($_POST['province']??'')),'related_name'=>sanitize_text_field(wp_unslash($_POST['related_name']??'')),'category'=>$cat,'description'=>sanitize_text_field(wp_unslash($_POST['description']??'')),'vendor'=>sanitize_text_field(wp_unslash($_POST['vendor']??'')),'amount'=>$amount,'expense_date'=>$date,'payment_method'=>sanitize_text_field(wp_unslash($_POST['payment_method']??'')),'document_no'=>sanitize_text_field(wp_unslash($_POST['document_no']??'')),'attachment_id'=>$att,'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));
        if (false === $saved || !$wpdb->insert_id) {
            wp_die('Gider kaydedilemedi. Girdiğiniz bilgileri korumak için tarayıcının Geri düğmesine basın. Lütfen yeniden denemeden önce kayıt listesini kontrol edin.', 'Gider kaydedilemedi', array('response'=>500, 'back_link'=>true));
        }
        self::go_saved('expense', substr($date,0,7));
    }
    public static function delete_expense(){self::delete_row('expenses','expense_id','mdg_v5_delete_expense_');}

    public static function save_income(){
        if(!current_user_can(self::CAP))wp_die('Yetkiniz yok.');check_admin_referer('mdg_v5_save_income','mdg_v5_finance_nonce');global$wpdb;
        $scope=sanitize_key($_POST['scope']??'program');$event=absint($_POST['event_id']??0);$category=sanitize_text_field(wp_unslash($_POST['category']??''));$gross=self::money_value($_POST['gross_amount']??'');$commission=max(0,self::money_value($_POST['commission_amount']??''));$date=sanitize_text_field(wp_unslash($_POST['income_date']??''));$channel=sanitize_text_field(wp_unslash($_POST['channel']??''));$ref=sanitize_text_field(wp_unslash($_POST['source_ref']??''));
        $is_corporate='Kurumsal / Toplu Satış'===$category;if($is_corporate){$scope='general';$event=0;}
        $description=sanitize_text_field(wp_unslash($_POST['description']??''));
        if($gross<=0||!self::date_ok($date)||!$channel||('program'===$scope&&!$event)||($is_corporate&&!$description))self::go_error('required',$is_corporate?'corporate':'income');
        if($ref&&(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::table('incomes').' WHERE source_ref=%s AND channel=%s LIMIT 1',$ref,$channel)))self::go_error('duplicate','income');
        $att=self::upload('document');if(is_wp_error($att))self::go_error('upload',$is_corporate?'corporate':'income');$now=current_time('mysql');
        $wpdb->insert(self::table('incomes'),array('event_id'=>$event,'scope'=>$scope,'province'=>sanitize_text_field(wp_unslash($_POST['province']??'')),'category'=>$category,'channel'=>$channel,'description'=>$description,'gross_amount'=>$gross,'commission_amount'=>min($gross,$commission),'net_amount'=>max(0,$gross-$commission),'ticket_count'=>absint($_POST['ticket_count']??0),'income_date'=>$date,'collection_status'=>sanitize_key($_POST['collection_status']??'collected'),'payment_method'=>sanitize_text_field(wp_unslash($_POST['payment_method']??'')),'source_ref'=>$ref,'attachment_id'=>$att,'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));self::go_saved($is_corporate?'corporate':'income');
    }
    public static function delete_income(){self::delete_row('incomes','income_id','mdg_v5_delete_income_');}

    public static function save_fixed_cost(){
        if(!current_user_can(self::CAP))wp_die('Yetkiniz yok.');check_admin_referer('mdg_v5_save_fixed_cost','mdg_v5_finance_nonce');global$wpdb;
        $month=sanitize_text_field(wp_unslash($_POST['cost_month']??''));$name=sanitize_text_field(wp_unslash($_POST['name']??''));$pay=self::money_value($_POST['pay_amount']??'');$fx=max(0,self::money_value($_POST['fx_rate']??1));$basis=sanitize_key($_POST['pay_basis']??'group');$head=absint($_POST['headcount']??0);$insured=absint($_POST['insured_count']??0);$taxpp=self::money_value($_POST['tax_per_person']??'');
        if(!self::month_ok($month)||!$name||$pay<0||$fx<=0)self::go_error('required','fixed',$month);
        $salary=round($pay*$fx*(('person'===$basis)?max(1,$head):1),2);$tax=round($insured*$taxpp,2);$att=self::upload('document');if(is_wp_error($att))self::go_error('upload','fixed',$month);$now=current_time('mysql');
        $wpdb->insert(self::table('fixed_costs'),array('cost_month'=>$month,'cost_type'=>sanitize_key($_POST['cost_type']??'operating'),'name'=>$name,'headcount'=>$head,'pay_amount'=>$pay,'currency'=>sanitize_text_field(wp_unslash($_POST['currency']??'TRY')),'fx_rate'=>$fx,'pay_basis'=>$basis,'insured_count'=>$insured,'tax_per_person'=>$taxpp,'salary_total'=>$salary,'tax_total'=>$tax,'total_amount'=>$salary+$tax,'document_no'=>sanitize_text_field(wp_unslash($_POST['document_no']??'')),'attachment_id'=>$att,'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'created_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now));self::go_saved('fixed',$month);
    }
    public static function delete_fixed_cost(){self::delete_row('fixed_costs','fixed_id','mdg_v5_delete_fixed_cost_');}
    private static function delete_row($table,$field,$nonce){MDG_V5_Finance_Records::reject_delete($table,absint($_POST[$field]??0));if(!current_user_can(self::CAP))wp_die('Yetkiniz yok.');$id=absint($_POST[$field]??0);check_admin_referer($nonce.$id,'mdg_v5_finance_nonce');global$wpdb;if($id)$wpdb->delete(self::table($table),array('id'=>$id));wp_safe_redirect(self::url(array('finance_deleted'=>1)));exit;}
    private static function go_error($e,$section,$month=''){wp_safe_redirect(self::url(array('finance_error'=>$e,'section'=>$section,'month'=>$month)));exit;}
    private static function go_saved($section,$month=''){wp_safe_redirect(self::url(array('finance_saved'=>$section,'section'=>$section,'month'=>$month)));exit;}
    public static function save_program_count(){if(!current_user_can(self::CAP))wp_die('Yetkiniz yok.');check_admin_referer('mdg_v5_save_program_count','mdg_v5_finance_nonce');$m=sanitize_text_field(wp_unslash($_POST['cost_month']??''));$n=absint($_POST['program_count']??0);if(self::month_ok($m)){$map=(array)get_option(self::COUNT_OPTION,array());if($n)$map[$m]=$n;else unset($map[$m]);update_option(self::COUNT_OPTION,$map,false);}self::go_saved('fixed',$m);}
    public static function save_attendance(){
        if(!current_user_can(self::CAP))wp_die('Yetkiniz yok.');check_admin_referer('mdg_v5_save_attendance','mdg_v5_finance_nonce');global$wpdb;
        $event=absint($_POST['event_id']??0);$avg=self::money_value($_POST['avg_ticket_price']??'');$manual=absint($_POST['manual_attendance']??0);$month=sanitize_text_field(wp_unslash($_POST['month']??''));
        if(!$event||$avg<=0){wp_safe_redirect(self::url(array('section'=>'attendance','month'=>$month,'finance_error'=>'required')));exit;}
        $wpdb->replace(self::table('attendance'),array('event_id'=>$event,'avg_ticket_price'=>$avg,'manual_attendance'=>$manual,'include_in_reports'=>isset($_POST['include_in_reports'])?1:0,'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')));
        wp_safe_redirect(self::url(array('section'=>'attendance','month'=>$month,'finance_saved'=>'attendance')));exit;
    }

    /** Legacy web snapshots stay visible, but the paid order map owns web revenue. */
    private static function is_web_snapshot($row) {
        return 'woocommerce' === strtolower(trim((string)($row->channel ?? '')));
    }

    /** Returned ticket principal is a cash movement, not a second operating cost. */
    private static function is_ticket_refund($row){
        return 'program'===($row->scope??'') && ('İade / WooCommerce'===trim((string)($row->category??'')) || (15===(int)($row->event_id??0) && 'İade / Biletinial'===trim((string)($row->category??''))));
    }

    private static $web_unverified = false;

    /** Read current WooCommerce state; the historical map only identifies program items. */
    private static function web_revenues_for_events($event_ids){
        global $wpdb;
        $out=array();
        $event_ids=array_values(array_unique(array_filter(array_map('absint',$event_ids))));
        if(!$event_ids)return $out;
        if(!class_exists('MDG_DB')||!function_exists('wc_get_order')){self::$web_unverified=true;return $out;}
        $statuses=array('processing','completed','paid');
        if(function_exists('wc_get_is_paid_statuses'))$statuses=array_merge($statuses,(array)wc_get_is_paid_statuses());
        $placeholders=implode(',',array_fill(0,count($event_ids),'%d'));
        $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT event_id,order_id,order_item_id,line_total FROM ".MDG_DB::table('order_map')." WHERE event_id IN ({$placeholders})",$event_ids));
        $orders=array();
        foreach($rows as $r){
            $oid=(int)$r->order_id;$eid=(int)$r->event_id;
            if(!array_key_exists($oid,$orders))$orders[$oid]=wc_get_order($oid);
            $order=$orders[$oid];
            if(!$order){self::$web_unverified=true;continue;}
            if(!in_array($order->get_status(),$statuses,true)||!$order->get_date_paid())continue;
            $item=$order->get_item((int)$r->order_item_id);
            if(!$item){self::$web_unverified=true;continue;}
            // MDG line_total excludes tax, so subtract only the matching item's tax-exclusive refund.
            $refund=abs((float)$order->get_total_refunded_for_item((int)$r->order_item_id));
            $net=max(0,(float)$r->line_total-$refund);
            $out[$eid]=($out[$eid]??0)+$net;
        }
        return $out;
    }

    private static function web_revenues($from,$to){
        $month=substr((string)$from,0,7);
        $month_events=self::month_events(self::events(),$month);
        return self::web_revenues_for_events(array_keys($month_events));
    }
    private static function month_events($events,$month){$out=array();foreach($events as$e){$status=strtolower((string)($e->status??''));if(0===strpos(self::event_date($e),$month)&&!in_array($status,array('cancelled','canceled','trash','draft'),true))$out[(int)$e->id]=$e;}return$out;}
    private static function money($n){return function_exists('wc_price')?wp_strip_all_tags(wc_price((float)$n)):number_format_i18n((float)$n,2).' TL';}

    public static function render(){
        if(!current_user_can(self::CAP))return;MDG_Yonetim_Merkezi_V5::css();global$wpdb;
        $section=sanitize_key($_GET['section']??'overview');$filter=sanitize_key($_GET['filter']??'all');$month=sanitize_text_field(wp_unslash($_GET['month']??wp_date('Y-m')));if(!self::month_ok($month))$month=wp_date('Y-m');$from=$month.'-01';$to=wp_date('Y-m-t',strtotime($from));$events=self::events();$names=array();$provinces=array();foreach($events as$e){$names[(int)$e->id]=self::event_name($e);if(!empty($e->province_name))$provinces[(string)$e->province_name]=(string)$e->province_name;}natcasesort($provinces);
        $expenses=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('expenses').' WHERE expense_date BETWEEN %s AND %s ORDER BY expense_date DESC,id DESC',$from,$to));$incomes=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('incomes').' WHERE income_date BETWEEN %s AND %s ORDER BY income_date DESC,id DESC',$from,$to));$fixed=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('fixed_costs').' WHERE cost_month=%s ORDER BY id DESC',$month));$attendance=(array)$wpdb->get_results('SELECT event_id,avg_ticket_price,manual_attendance,include_in_reports,notes,updated_at FROM '.self::table('attendance'),OBJECT_K);$web=self::web_revenues($from,$to);
        $web_total=array_sum($web);$manual=0;foreach($incomes as$r)if(!self::is_web_snapshot($r))$manual+=MDG_V5_Finance_Records::collected($r);$direct=0;$general=0;foreach($expenses as$r){if(self::is_ticket_refund($r))continue;if('program'===$r->scope)$direct+=(float)$r->amount;else$general+=(float)$r->amount;}$fixed_total=0;foreach($fixed as$r)$fixed_total+=(float)$r->total_amount;$all_month_events=self::month_events($events,$month);$month_events=array_filter($all_month_events,static function($e)use($attendance){$id=(int)$e->id;return!isset($attendance[$id])||(int)$attendance[$id]->include_in_reports===1;});$count_map=(array)get_option(self::COUNT_OPTION,array());$detected=count($month_events);$count=isset($count_map[$month])?absint($count_map[$month]):$detected;$share=$count?$fixed_total/$count:0;
        echo'<div class="wrap mdgv5"><h1>Gelir, Gider ve Kârlılık Merkezi</h1>';if(!empty($_GET['finance_saved']))echo'<div class="notice notice-success is-dismissible"><p>Kayıt başarıyla kaydedildi.</p></div>';if(!empty($_GET['finance_deleted']))echo'<div class="notice notice-success is-dismissible"><p>Kayıt silindi. Yüklenen belge korunmuştur.</p></div>';if(!empty($_GET['finance_error']))echo'<div class="notice notice-error"><p>'.('duplicate'===$_GET['finance_error']?'Aynı kanal ve referans numarası daha önce kaydedilmiş.':'Zorunlu alanları ve tutarları kontrol edin.').'</p></div>';
        self::render_program_summary($events);echo '<p class="mdgv5-note">WooCommerce kanalındaki eski gelir kayıtları inceleme için korunur; web gelirleri güncel WooCommerce sipariş durumu ve kalem iadeleriyle bir kez hesaplanır. Bilet iade hareketleri maliyete ikinci kez eklenmez; gider kayıtlarında korunur.</p>';
        if(self::$web_unverified)echo '<div class="notice notice-warning"><p>Bazı web satışlarının güncel sipariş veya bilet kalemi doğrulanamadı. Bu kayıtlar toplama katılmadı; gelir ve kâr toplamları eksik olabilir.</p></div>';
        echo'<div class="nav-tab-wrapper">';foreach(array('overview'=>'Genel Bakış','attendance'=>'Seyirci','income'=>'Program / Diğer Gelir','corporate'=>'Kurumsal Satış','expense'=>'Giderler','fixed'=>'Sabit Giderler','shared'=>'Ortak Gider Dağıtımı','accounts'=>'Kasa / Banka','history'=>'İşlem Geçmişi','documents'=>'Fatura / Teminat / Kapanış')as$k=>$v)echo'<a class="nav-tab '.($section===$k?'nav-tab-active':'').'" href="'.esc_url(self::url(array('section'=>$k,'month'=>$month))).'">'.esc_html($v).'</a>';echo'</div><form method="get" class="mdgv5-filter"><input type="hidden" name="page" value="mdg-v5-finance"><input type="hidden" name="section" value="'.esc_attr($section).'"><input type="hidden" name="summary_event" value="'.self::context_event().'"><label><strong>Rapor ayı</strong> <input type="month" name="month" value="'.esc_attr($month).'"></label>';
        $filter_options=array();if(in_array($section,array('income','corporate'),true))$filter_options=array('all'=>'Tüm gelir kayıtları','collected'=>'Tam tahsil edildi','partial'=>'Kısmi tahsil edildi','pending'=>'Tahsilat bekliyor');elseif('expense'===$section)$filter_options=array('all'=>'Tüm gider kapsamları','program'=>'Program','province'=>'İl','general'=>'Genel','person'=>'Personel','vehicle'=>'Araç');elseif('fixed'===$section)$filter_options=array('all'=>'Tüm sabit giderler','team'=>'Yabancı ekip','person'=>'Bireysel personel','operating'=>'Diğer sabit gider');if($filter_options){echo'<label><strong>Liste filtresi</strong><select name="filter">';foreach($filter_options as$k=>$v)echo'<option value="'.esc_attr($k).'" '.selected($filter,$k,false).'>'.esc_html($v).'</option>';echo'</select></label>';}echo'<button class="button">Göster</button></form>';
        $shown_incomes=$incomes;$shown_expenses=$expenses;$shown_fixed=$fixed;$selected_event=self::context_event();if($selected_event){$shown_incomes=$section==='corporate'?$incomes:array_values(array_filter($incomes,static function($r)use($selected_event){return (int)$r->event_id===$selected_event&&$r->scope==='program';}));$shown_expenses=array_values(array_filter($expenses,static function($r)use($selected_event){return (int)$r->event_id===$selected_event&&$r->scope==='program';}));}echo '<p>'.($selected_event?'Gelir / gider listeleri: seçili program, seçilen kayıt ayı. Ortak giderler için program seçimini temizleyin.':'Gelir / gider listeleri: bütün işletme, seçilen kayıt ayı.').'</p>';if('all'!==$filter){if(in_array($section,array('income','corporate'),true))$shown_incomes=array_values(array_filter($shown_incomes,static function($r)use($filter){$state=MDG_V5_Finance_Records::state('income',$r);return $filter==='collected'?$state['remaining']<=0:($filter==='partial'?$state['settled']>0&&$state['remaining']>0:$state['settled']<=0&&$state['remaining']>0);}));elseif('expense'===$section)$shown_expenses=array_values(array_filter($shown_expenses,static function($r)use($filter){return$r->scope===$filter;}));elseif('fixed'===$section)$shown_fixed=array_values(array_filter($fixed,static function($r)use($filter){return$r->cost_type===$filter;}));}
        MDG_V5_Finance_Records::render_selected($names);
        if(in_array($section,array('shared','accounts','history','documents'),true))MDG_V5_Finance_Records::render_section($section,$month,$events,$expenses);elseif('attendance'===$section)self::render_attendance($month,$all_month_events,$attendance,$web,$incomes);elseif('income'===$section)self::render_income($names,$provinces,$shown_incomes,false);elseif('corporate'===$section)self::render_income($names,$provinces,$shown_incomes,true);elseif('expense'===$section)self::render_expense($names,$provinces,$shown_expenses);elseif('fixed'===$section)self::render_fixed($month,$shown_fixed,$detected,$count,$fixed_total,$share);else self::render_overview($names,$month_events,$web,$incomes,$expenses,$share,$web_total,$manual,$direct,$general,$fixed_total);echo'</div>';
    }


    /** Read-only lifetime summary; MMC IDs are resolved through the verified bridge. */
    private static function render_program_summary($events) {
        global $wpdb;
        $id=self::context_event();
        $pid=absint($_GET['program_id']??0);
        if(!isset($_GET['summary_event']) && $pid && class_exists('MMC_MDG_Bridge_Service')) {
            $bridge=MMC_MDG_Bridge_Service::bridge_for_program($pid);
            if($bridge)$id=absint($bridge->mdg_event_id);
        }
        $selected=null;
        echo '<div class="mdgv5-card"><h2>Program Gelir / Gider Durumu</h2><form method="get" class="mdgv5-filter"><input type="hidden" name="page" value="mdg-v5-finance"><input type="hidden" name="section" value="'.esc_attr(sanitize_key($_GET['section']??'overview')).'"><input type="hidden" name="month" value="'.esc_attr(sanitize_text_field(wp_unslash($_GET['month']??wp_date('Y-m')))).'"><label><strong>Program seçin</strong><select name="summary_event"><option value="0">Program seçin</option>';
        foreach($events as $event){if((int)$event->id===$id)$selected=$event;echo '<option value="'.absint($event->id).'" '.selected($id,(int)$event->id,false).'>'.esc_html(self::event_name($event).' — '.self::event_date($event).' · '.($event->status??'durum yok').' #'.$event->id).'</option>';}
        echo '</select></label><button class="button button-primary">Programı Göster</button></form>';
        if(!$selected){echo '<p>Programın bütün tarihlerdeki gelir ve giderlerini görmek için seçim yapın. MMC eşleşmesi yoksa program tahmin edilmez.</p></div>';return;}
        if(sanitize_key($_GET['section']??'overview')!=='overview'){echo '<p><strong>'.esc_html(self::event_name($selected)).'</strong> · '.esc_html(self::event_date($selected)).' <a href="'.esc_url(self::url(array('section'=>'overview'))).'">Program özetini aç</a></p></div>';return;}
        $date=self::event_date($selected);$month=substr($date,0,7);
        $inc=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('incomes').' WHERE event_id=%d AND scope=%s ORDER BY income_date DESC,id DESC',$id,'program'));
        $exp=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('expenses').' WHERE event_id=%d AND scope=%s ORDER BY expense_date DESC,id DESC',$id,'program'));
        $program_web=self::web_revenues_for_events(array($id));$web=(float)($program_web[$id]??0);
        $refund_cash=0;$refund_channels=array();$collected=0;$pending=0;$total_exp=0;$unpaid=0;$unknown=0;$channels=array('Web sitesi'=>$web);$categories=array();
        foreach($inc as $r){if(self::is_web_snapshot($r))continue;$paid=MDG_V5_Finance_Records::collected($r);$collected+=$paid;$channels[$r->channel]=($channels[$r->channel]??0)+$paid;$pending+=max(0,(float)$r->net_amount-$paid);}
        foreach($exp as $r){if(self::is_ticket_refund($r)){$refund_cash+=(float)$r->amount;$refund_channels[$r->category]=($refund_channels[$r->category]??0)+(float)$r->amount;continue;}$total_exp+=(float)$r->amount;$categories[$r->category]=($categories[$r->category]??0)+(float)$r->amount;$state=MDG_V5_Finance_Records::state('expense',$r);if($state['known'])$unpaid+=$state['remaining'];else$unknown+=(float)$r->amount;}
        $fixed=0;$share=0;$count=0;
        if(self::month_ok($month)){$fixed=(float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(total_amount),0) FROM '.self::table('fixed_costs').' WHERE cost_month=%s',$month));$attendance=(array)$wpdb->get_results('SELECT event_id,include_in_reports FROM '.self::table('attendance'),OBJECT_K);$eligible=array_filter(self::month_events($events,$month),static function($e)use($attendance){return !isset($attendance[$e->id])||(int)$attendance[$e->id]->include_in_reports===1;});$counts=(array)get_option(self::COUNT_OPTION,array());$count=isset($counts[$month])?absint($counts[$month]):count($eligible);$share=isset($eligible[$id])&&$count?$fixed/$count:0;}
        $common_rows = self::allocated_rows((array)$wpdb->get_results('SELECT * FROM '.self::table('expenses').' WHERE scope <> "program"'), $id);
        $common = 0;
        foreach ($common_rows as $part) {
            $common += $part['amount'];
            $state=MDG_V5_Finance_Records::state('expense',$part['source']);
            if ($state['known']) $unpaid += $part['amount'] * ($state['remaining'] / max(0.01,(float)$part['source']->amount));
            else $unknown += $part['amount'];
            $category = $part['source']->category.' · ortak pay';
            $categories[$category] = ($categories[$category] ?? 0) + $part['amount'];
        }
        $income=$web+$collected;$result=$income-$total_exp-$common-$share;
        if(15===$id)echo '<div class="notice notice-warning inline"><p>Kırıkkale iade mutabakatı: gerçekleşen WooCommerce geri ödemesi 13.250 TL (işletme teyidi); sipariş iade kaydı 13.750 TL. Aradaki 500 TL fark kâr veya gider olarak eklenmedi. Tarihsel web tahsilatı 17.750 TL ile gerçekleşen geri ödeme arasındaki 4.500 TL de mutabakat bekler; gelir sayılmadı. Biletinial geri ödemesi 6.250 TL. Banka/PayTR mutabakatı tamamlanana kadar nakit kapanışı kesin değildir.</p></div>';
        echo '<h3>'.esc_html(self::event_name($selected).' — '.$date).'</h3><p>Programın tüm kayıt tarihleri · Son görüntüleme: '.esc_html(wp_date('d.m.Y H:i')).'</p><div class="mdgv5-grid">';
        self::card(self::money($income),'Kayıtlı net gelir','Web vergi hariç; diğer gelir tahsilat esaslı');
        self::card(self::money($total_exp+$common+$share),'Toplam program maliyeti','Doğrudan gider + ortak gider payı + sabit gider payı');
        self::card(self::money($result),'Kayıtlı verilere göre kâr / zarar','Tahsil edilmiş gelir − program maliyeti');
        self::card(self::money($refund_cash),'Kayıtlı bilet iadeleri','Geri ödeme hareketi; maliyetten ayrıca düşülmez');
        self::card(self::money($pending),'Tahsilat bekleyen gelir','Kâr hesabına eklenmedi');
        self::card(self::money($unpaid),'Kayıtlı kalan ödeme','Maliyete dâhil; ödeme takibi olan kayıtlar');
        self::card(self::money($common),'Ortak gider payı','Seçili programa dağıtılmış giderler');
        self::card(self::money($share),'Sabit gider payı',$month.' · dağıtımda '.$count.' program');
        echo '</div><p class="mdgv5-note">Bu sonuç mevcut kayıtlara dayanır; eksik gelir/giderler, iade mutabakatı ve programa dağıtılmamış genel/il/personel/araç giderleri sonucu değiştirebilir. Ödeme hareketi doğrulanmamış gider: '.esc_html(self::money($unknown)).'. '.(!$fixed?'Bu ay sabit gider kaydı yok.':'').' Program tamamlanmadıysa sonuç geçicidir.</p><div class="mdgv5-grid">';
        foreach(array('Gelir kanalları'=>$channels,'Gider kategorileri'=>$categories) as $heading=>$rows){echo '<div><h3>'.esc_html($heading).'</h3><table class="widefat striped"><thead><tr><th>Kalem</th><th>Tutar</th></tr></thead><tbody>';foreach($rows as $label=>$amount)echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html(self::money($amount)).'</td></tr>';if(!$rows)echo '<tr><td colspan="2">Kayıt yok.</td></tr>';echo '</tbody></table></div>';}
        echo '</div><details><summary>Programın gelir ve gider kayıtlarını göster</summary><table class="widefat striped"><thead><tr><th>Tarih</th><th>Tür / kategori</th><th>Açıklama</th><th>Tutar</th><th>Durum</th></tr></thead><tbody>';
        foreach($inc as $r)echo '<tr><td>'.esc_html($r->income_date).'</td><td>Gelir · '.esc_html($r->channel).'</td><td>'.esc_html($r->description).'</td><td>'.esc_html(self::money($r->net_amount)).'</td><td>'.esc_html(self::is_web_snapshot($r)?'Web kaydı — toplama ayrıca eklenmez':MDG_V5_Finance_Records::income_label($r)).'</td></tr>';
        foreach($exp as $r)echo '<tr><td>'.esc_html($r->expense_date).'</td><td>Gider · '.esc_html($r->category).'</td><td>'.esc_html($r->description).'</td><td>'.esc_html(self::money($r->amount)).'</td><td>'.esc_html($r->payment_method?:'Ödeme bilgisi yok').'</td></tr>';
        foreach ($common_rows as $part) {
            $source = $part['source'];
            echo '<tr><td>'.esc_html($source->expense_date).'</td><td>Ortak gider payı · '.esc_html($source->category).'</td><td>'.esc_html($source->description.' · kaynak gider #'.$source->id).'</td><td>'.esc_html(self::money($part['amount'])).'</td><td>Dağıtılmış pay</td></tr>';
        }
        echo '</tbody></table></details></div><h2>Aylık çalışma alanı</h2><p>Genel Bakış işletmenin tamamını; gelir ve gider listeleri seçili programı gösterir. Rapor ayı kayıt tarihine göre uygulanır.</p>';
    }

    private static function render_overview($names,$month_events,$web,$incomes,$expenses,$share,$web_total,$manual,$direct,$general,$fixed_total){
        $refund_cash=0;foreach($expenses as $r)if(self::is_ticket_refund($r))$refund_cash+=(float)$r->amount;
        echo'<div class="mdgv5-grid">';self::card(self::money($refund_cash),'Kayıtlı bilet iadeleri','Geri ödeme hareketi; maliyette tekrar sayılmaz');self::card(self::money($web_total),'Web sitesi geliri','Vergi hariç sipariş geliri; banka bakiyesi değildir');self::card(self::money($manual),'Diğer tahsil edilmiş gelir','Bilet şirketi, kapı ve kantin');self::card(self::money($direct+$general+$fixed_total),'Toplam gider','Program + genel + sabit');self::card(self::money($web_total+$manual-$direct-$general-$fixed_total),'Geçici işletme sonucu','Kayıtlı net gelir − tüm giderler');echo'</div><div class="mdgv5-card"><h2>Aylık gider dağılımı</h2><table class="widefat striped"><thead><tr><th>Doğrudan program gideri</th><th>Genel gider</th><th>Aylık sabit gider</th><th>Program başına sabit gider</th></tr></thead><tbody><tr><td>'.esc_html(self::money($direct)).'</td><td>'.esc_html(self::money($general)).'</td><td>'.esc_html(self::money($fixed_total)).'</td><td><strong>'.esc_html(self::money($share)).'</strong></td></tr></tbody></table></div>';
        $mi=array();foreach($incomes as$r)if(!self::is_web_snapshot($r))$mi[(int)$r->event_id]=($mi[(int)$r->event_id]??0)+MDG_V5_Finance_Records::collected($r);$ex=array();foreach($expenses as$r)if(!self::is_ticket_refund($r)&&'program'===$r->scope)$ex[(int)$r->event_id]=($ex[(int)$r->event_id]??0)+(float)$r->amount;
        $common_map = array();
        foreach (self::allocated_rows($expenses) as $part) $common_map[$part['event_id']] = ($common_map[$part['event_id']] ?? 0) + $part['amount'];
        echo'<div class="mdgv5-card"><h2>Program kârlılığı</h2><p class="mdgv5-sub">Faaliyet sonucu doğrudan ve dağıtılmış ortak giderleri; net sonuç ayrıca sabit gider payını içerir. Bu ayın gider payları kaynak gider tarihine göre gösterilir.</p><table class="widefat striped"><thead><tr><th>Program</th><th>Web</th><th>Diğer gelir</th><th>Doğrudan gider</th><th>Ortak gider payı</th><th>Faaliyet sonucu</th><th>Sabit gider payı</th><th>Kayıtlı net sonuç</th></tr></thead><tbody>';foreach($month_events as$id=>$e){$rev=($web[$id]??0)+($mi[$id]??0);$op=$rev-($ex[$id]??0)-($common_map[$id]??0);$net=$op-$share;echo'<tr><td><strong>#'.absint($id).' '.esc_html(self::event_name($e)).'</strong><div class="mdgv5-sub">'.esc_html(self::event_date($e)).'</div></td><td>'.esc_html(self::money($web[$id]??0)).'</td><td>'.esc_html(self::money($mi[$id]??0)).'</td><td>'.esc_html(self::money($ex[$id]??0)).'</td><td>'.esc_html(self::money($common_map[$id]??0)).'</td><td>'.esc_html(self::money($op)).'</td><td>'.esc_html(self::money($share)).'</td><td class="'.($net>=0?'mdgv5-ok':'mdgv5-bad').'">'.esc_html(self::money($net)).'</td></tr>';}if(!$month_events)echo'<tr><td colspan="8">Bu ay tarih bilgisi bulunan program bulunamadı. Program sayısını sabit gider ekranından elle girebilirsiniz.</td></tr>';echo'</tbody></table></div>';
    }

    private static function render_attendance($month,$events,$saved,$web,$incomes){
        $manual_ticket_revenue=array();foreach($incomes as$r){if(!self::is_web_snapshot($r)&&'collected'===$r->collection_status&&'Bilet Satışı'===$r->category&&$r->event_id)$manual_ticket_revenue[(int)$r->event_id]=($manual_ticket_revenue[(int)$r->event_id]??0)+(float)$r->gross_amount;}
        $total=0;$programs=0;$included=0;foreach($events as$id=>$e){$row=$saved[$id]??null;$is_included=!$row||(int)$row->include_in_reports===1;if(!$is_included)continue;$included++;$avg=$row?(float)$row->avg_ticket_price:0;$manual_count=$row?absint($row->manual_attendance):0;$ticket_revenue=(float)($web[$id]??0)+(float)($manual_ticket_revenue[$id]??0);$estimated=$avg>0?(int)round($ticket_revenue/$avg):0;$used=$manual_count?:$estimated;if($used){$total+=$used;$programs++;}}
        echo'<div class="mdgv5-grid">';self::card(number_format_i18n($total),'Aylık toplam seyirci','Manuel sayı varsa o, yoksa tahmin');self::card(number_format_i18n($programs?round($total/$programs):0),'Program başına ortalama','Seyirci toplamı ÷ hesaplanan program');self::card(number_format_i18n($included),'Hesaba katılan program','İptal/taslak ve hariç tutulanlar yok');echo'</div>';
        echo'<div class="mdgv5-card"><h2>Seyirci Sayısı Hesabı</h2><div class="mdgv5-note">Tahmini seyirci = yalnız bilet geliri ÷ ortalama bilet fiyatı. Eski/test programlarda “Hesaba dâhil” işaretini kaldırın. Mobilde tüm alanları görmek için tabloyu sola kaydırabilirsiniz.</div><table class="widefat striped"><thead><tr><th>Program</th><th>Bilet geliri</th><th>Ortalama bilet</th><th>Tahmini seyirci</th><th>Manuel/gerçek</th><th>Kullanılan sayı</th><th>Dâhil</th><th>Kaydet</th></tr></thead><tbody>';
        foreach($events as$id=>$e){$row=$saved[$id]??null;$is_included=!$row||(int)$row->include_in_reports===1;$avg=$row?(float)$row->avg_ticket_price:0;$manual_count=$row?absint($row->manual_attendance):0;$ticket_revenue=(float)($web[$id]??0)+(float)($manual_ticket_revenue[$id]??0);$estimated=$avg>0?(int)round($ticket_revenue/$avg):0;$used=$is_included?($manual_count?:$estimated):0;echo'<tr'.($is_included?'':' class="mdgv5-excluded"').'><td><strong>'.esc_html(self::event_name($e)).'</strong><div class="mdgv5-sub">'.esc_html(self::event_date($e)).'</div></td><td>'.esc_html(self::money($ticket_revenue)).'</td><td colspan="6"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="mdgv5-attendance-row"><input type="hidden" name="action" value="mdg_v5_save_attendance"><input type="hidden" name="event_id" value="'.absint($id).'"><input type="hidden" name="month" value="'.esc_attr($month).'">';wp_nonce_field('mdg_v5_save_attendance','mdg_v5_finance_nonce');echo'<input type="text" name="avg_ticket_price" value="'.esc_attr($avg?number_format($avg,2,',','.'):'350,00').'" aria-label="Ortalama bilet"><span>'.number_format_i18n($estimated).'</span><input type="number" min="0" name="manual_attendance" value="'.esc_attr($manual_count?:'').'" placeholder="Gerçek sayı" aria-label="Manuel seyirci"><strong>'.number_format_i18n($used).'</strong><label class="mdgv5-check"><input type="checkbox" name="include_in_reports" value="1" '.checked($is_included,true,false).'> Dâhil</label><button class="button">Kaydet</button></form></td></tr>';}
        if(!$events)echo'<tr><td colspan="8">Bu ay için program bulunamadı.</td></tr>';echo'</tbody></table></div>';
    }

    private static function render_income($names,$provinces,$rows,$corporate=false){
        $rows=array_values(array_filter($rows,static function($r)use($corporate){return $corporate?('Kurumsal / Toplu Satış'===$r->category):('Kurumsal / Toplu Satış'!==$r->category);}));
        echo'<div class="mdgv5-card"><h2>'.($corporate?'Kurumsal Satış Ekle':'Program / Diğer Gelir Ekle').'</h2><div class="mdgv5-note">'.($corporate?'Bu gelir için etkinlik açmanız gerekmez. Kurum veya toplu alıcı bilgisiyle doğrudan kaydedilir.':'Web sitesi satışlarını buraya girmeyin; WooCommerce ve PayTR gelirleri otomatik alınır.').'</div><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_save_income">';self::context_fields();wp_nonce_field('mdg_v5_save_income','mdg_v5_finance_nonce');echo'<div class="mdgv5-finance-form">';
        if($corporate){echo'<input type="hidden" name="scope" value="general"><input type="hidden" name="event_id" value="0"><input type="hidden" name="category" value="Kurumsal / Toplu Satış">';self::input('Kurum / müşteri *','description','');self::select('Tahsilat kanalı *','channel',array('Kurumsal Havale / EFT'=>'Havale / EFT','Kurumsal Nakit'=>'Nakit','Kurumsal POS / Kredi Kartı'=>'POS / Kredi Kartı','Diğer'=>'Diğer'),'Kurumsal Havale / EFT');}
        else{self::select('Kapsam','scope',array('program'=>'Program','province'=>'İl','general'=>'Genel'),'program');self::select('Program','event_id',$names,self::context_event());self::select('İl','province',$provinces,'');self::select('Gelir grubu','category',array_combine(self::income_categories(),self::income_categories()),'');self::select('Satış kanalı *','channel',array_combine(self::income_channels(),self::income_channels()),'');self::input('Açıklama','description','');}
        self::input('Brüt tutar (TL) *','gross_amount','');self::input('Komisyon / kesinti','commission_amount','0');self::input('Bilet adedi','ticket_count','0','number');self::input('Gelir tarihi *','income_date',wp_date('Y-m-d'),'date');self::select('Tahsilat durumu','collection_status',array('collected'=>'Tahsil edildi','pending'=>'Bekliyor'),'collected');self::input('Kaynak / sözleşme / mutabakat no','source_ref','');self::file_note();echo'</div><p><button class="button button-primary">'.($corporate?'Kurumsal Satışı Kaydet':'Geliri Kaydet').'</button></p></form></div><div class="mdgv5-card"><h2>'.($corporate?'Kurumsal Satış Kayıtları':'Program / Diğer Gelir Kayıtları').'</h2><table class="widefat striped"><thead><tr><th>Tarih</th><th>Program / kapsam</th><th>Kaynak</th><th>Brüt</th><th>Komisyon</th><th>Net</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>';foreach($rows as$r){echo'<tr><td>'.esc_html(wp_date('d.m.Y',strtotime($r->income_date))).'</td><td>'.esc_html($corporate?$r->description:($names[(int)$r->event_id]??ucfirst($r->scope))).'</td><td><strong>'.esc_html($r->channel).'</strong><div class="mdgv5-sub">'.esc_html($r->category.' '.$r->source_ref).'</div></td><td>'.esc_html(self::money($r->gross_amount)).'</td><td>'.esc_html(self::money($r->commission_amount)).'</td><td><strong>'.esc_html(self::money($r->net_amount)).'</strong></td><td>'.MDG_V5_Finance_Records::income_label($r).'</td><td>';MDG_V5_Finance_Records::row_actions('income',$r);echo'</td></tr>';}if(!$rows)echo'<tr><td colspan="8">Bu ay gelir kaydı yok.</td></tr>';echo'</tbody></table></div>';}

    private static function render_expense($names,$provinces,$rows){echo'<div class="mdgv5-card"><h2>Manuel Gider Ekle</h2><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_save_expense">';self::context_fields();wp_nonce_field('mdg_v5_save_expense','mdg_v5_finance_nonce');echo'<div class="mdgv5-finance-form">';self::select('Kapsam *','scope',array('program'=>'Doğrudan program gideri','province'=>'İl ortak gideri','general'=>'Genel / tek seferlik','person'=>'Personel','vehicle'=>'Araç'),'program');self::select('Program','event_id',$names,self::context_event());self::select('İl','province',$provinces,'');self::input('İlgili kişi / araç','related_name','');self::select('Kategori *','category',array_combine(self::expense_categories(),self::expense_categories()),'');self::input('Açıklama','description','');self::input('Tutar (TL) *','amount','');self::input('Gider tarihi *','expense_date',wp_date('Y-m-d'),'date');self::select('Ödeme yöntemi','payment_method',array_combine(self::payment_methods(),self::payment_methods()),'');self::input('Firma / kişi','vendor','');self::input('Belge / fatura no','document_no','');self::file_note();echo'</div><p><button class="button button-primary">Gideri Kaydet</button></p></form></div><div class="mdgv5-card"><h2>Gider Kayıtları</h2><table class="widefat striped"><thead><tr><th>Tarih</th><th>Kapsam</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>İşlem</th></tr></thead><tbody>';foreach($rows as$r){echo'<tr><td>'.esc_html(wp_date('d.m.Y',strtotime($r->expense_date))).'</td><td>'.esc_html('program'===$r->scope?($names[(int)$r->event_id]??'Program'):ucfirst($r->scope)).'</td><td>'.esc_html($r->category).'</td><td>'.esc_html($r->description).'</td><td><strong>'.esc_html(self::money($r->amount)).'</strong></td><td>';MDG_V5_Finance_Records::row_actions('expense',$r);self::allocation_form($r,$names);echo'</td></tr>';}if(!$rows)echo'<tr><td colspan="6">Bu ay gider kaydı yok.</td></tr>';echo'</tbody></table></div>';}

    private static function render_fixed($month,$rows,$detected,$count,$total,$share){echo'<div class="mdgv5-grid">';self::card(self::money($total),'Aylık sabit gider',count($rows).' kayıt');self::card($detected,'Sistemin bulduğu program','MDG durum filtresi + seyirci ekranındaki dâhil seçimi');self::card($count,'Dağıtımda kullanılan program','Elle düzeltilebilir');self::card(self::money($share),'Program başına maliyet','Aylık sabit gider ÷ program');echo'</div><div class="mdgv5-card"><h2>Program Sayısı</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="mdgv5-filter"><input type="hidden" name="action" value="mdg_v5_save_program_count"><input type="hidden" name="cost_month" value="'.esc_attr($month).'">';wp_nonce_field('mdg_v5_save_program_count','mdg_v5_finance_nonce');echo'<label><strong>Dağıtımda kullanılacak program sayısı</strong> <input type="number" min="0" name="program_count" value="'.esc_attr($count).'"></label><button class="button">Program Sayısını Kaydet</button><span class="mdgv5-sub">0 girilirse sistemin tespit ettiği sayı kullanılır.</span></form></div>';
        echo'<div class="mdgv5-card"><h2>Aylık Sabit Gider Ekle</h2><div class="mdgv5-note">Ekip ücreti kişi sayısıyla çarpılmaz. SGK/vergi yalnız tabi kişi sayısıyla çarpılır.</div><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_save_fixed_cost"><input type="hidden" name="cost_month" value="'.esc_attr($month).'">';wp_nonce_field('mdg_v5_save_fixed_cost','mdg_v5_finance_nonce');echo'<div class="mdgv5-finance-form">';self::select('Kayıt türü','cost_type',array('team'=>'Yabancı ekip','person'=>'Bireysel personel','operating'=>'Diğer sabit gider'),'operating');self::input('Personel / ekip / gider adı *','name','');self::input('Kişi sayısı','headcount','0','number');self::input('Ücret / gider tutarı *','pay_amount','');self::select('Para birimi','currency',array('TRY'=>'TL','USD'=>'USD','EUR'=>'EUR'),'TRY');self::input('Kur','fx_rate','1');self::select('Ücret biçimi','pay_basis',array('group'=>'Ekip / kayıt toplamı','person'=>'Kişi başı'),'group');self::input('SGK/vergiye tabi kişi','insured_count','0','number');self::input('Kişi başı SGK/vergi','tax_per_person','0');self::input('Belge / fatura no','document_no','');self::file_note();echo'</div><p><button class="button button-primary">Sabit Gideri Kaydet</button></p></form></div><div class="mdgv5-card"><h2>Sabit Gider Listesi</h2><table class="widefat striped"><thead><tr><th>Ad</th><th>Kişi</th><th>Ücret</th><th>Kur</th><th>Maaş/gider</th><th>SGK/vergi</th><th>Toplam</th><th>İşlem</th></tr></thead><tbody>';foreach($rows as$r){echo'<tr><td><strong>'.esc_html($r->name).'</strong><div class="mdgv5-sub">'.esc_html($r->cost_type.' / '.$r->pay_basis).'</div></td><td>'.absint($r->headcount).'</td><td>'.esc_html(number_format_i18n($r->pay_amount,2).' '.$r->currency).'</td><td>'.esc_html(number_format_i18n($r->fx_rate,2)).'</td><td>'.esc_html(self::money($r->salary_total)).'</td><td>'.esc_html(self::money($r->tax_total)).'</td><td><strong>'.esc_html(self::money($r->total_amount)).'</strong></td><td>';MDG_V5_Finance_Records::row_actions('fixed',$r);echo'</td></tr>';}if(!$rows)echo'<tr><td colspan="8">Bu ay sabit gider kaydı yok.</td></tr>';echo'</tbody><tfoot><tr><th colspan="6">Aylık toplam</th><th>'.esc_html(self::money($total)).'</th><th></th></tr></tfoot></table></div>';}

    private static function card($v,$t,$n){echo'<div class="mdgv5-card"><div class="mdgv5-kpi">'.esc_html($v).'</div><h3>'.esc_html($t).'</h3><p class="mdgv5-sub">'.esc_html($n).'</p></div>';}
    private static function input($label,$name,$value,$type='text'){echo'<label><strong>'.esc_html($label).'</strong><input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'"></label>';}
    private static function select($label,$name,$options,$value){echo'<label><strong>'.esc_html($label).'</strong><select name="'.esc_attr($name).'"><option value="">Seçin</option>';foreach($options as$k=>$v)echo'<option value="'.esc_attr($k).'" '.selected((string)$value,(string)$k,false).'>'.esc_html($v).'</option>';echo'</select></label>';}
    private static function file_note(){echo'<label><strong>Fatura / makbuz</strong><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp"></label><label class="mdgv5-wide"><strong>Not</strong><textarea name="notes" rows="3"></textarea></label>';}
    private static function delete_button($action,$field,$id){echo'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Bu kayıt silinsin mi?\');"><input type="hidden" name="action" value="'.esc_attr($action).'"><input type="hidden" name="'.esc_attr($field).'" value="'.absint($id).'">';wp_nonce_field($action.'_'.absint($id),'mdg_v5_finance_nonce');echo'<button class="button-link-delete">Sil</button></form>';}
}

/** Administrative records only. No bank, payment gateway or WooCommerce writes. */
final class MDG_V5_Finance_Records {
    const VERSION = '1.0.0';
    const ACCOUNTS = 'mdg_v5_finance_accounts_v1';
    private static $cache = array();

    public static function boot() {
        if (get_option('mdg_v5_finance_records_version') !== self::VERSION) self::install();
        add_action('admin_post_mdg_v5_finance_record', array(__CLASS__, 'save'));
        add_action('admin_post_mdg_v5_finance_account', array(__CLASS__, 'save_account'));
    }
    private static function table($name) { global $wpdb; return $wpdb->prefix.'mdg_v5_'.$name; }
    private static function install() {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
        dbDelta('CREATE TABLE '.self::table('settlements')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            record_type varchar(12) NOT NULL,
            record_id bigint unsigned NOT NULL,
            opening_amount decimal(14,2) NOT NULL DEFAULT 0,
            created_by bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY source (record_type,record_id)
        ) ENGINE=InnoDB $c;");
        dbDelta('CREATE TABLE '.self::table('payments')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            record_type varchar(12) NOT NULL,
            record_id bigint unsigned NOT NULL,
            account_id varchar(40) NOT NULL,
            amount decimal(14,2) NOT NULL,
            payment_date date NOT NULL,
            reference_no varchar(190) NOT NULL DEFAULT '',
            reason text NOT NULL,
            request_key varchar(36) NOT NULL,
            status varchar(12) NOT NULL DEFAULT 'posted',
            created_by bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY request_key (request_key), KEY source (record_type,record_id)
        ) ENGINE=InnoDB $c;");
        dbDelta('CREATE TABLE '.self::table('finance_history')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            record_type varchar(12) NOT NULL,
            record_id bigint unsigned NOT NULL,
            operation varchar(20) NOT NULL,
            before_data longtext NOT NULL,
            after_data longtext NOT NULL,
            reason text NOT NULL,
            created_by bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY source (record_type,record_id)
        ) ENGINE=InnoDB $c;");
        $ok=true; foreach(array('settlements','payments','finance_history') as $name) {
            $table=self::table($name);
            $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$table));if(!$status||strtolower($status->Engine)!=='innodb')$ok=false;
        }
        if($ok)update_option('mdg_v5_finance_records_version',self::VERSION,false);
    }
    private static function source($type) {
        $map=array('expense'=>'expenses','income'=>'incomes','fixed'=>'fixed_costs');
        return isset($map[$type]) ? self::table($map[$type]) : '';
    }
    private static function amount($type,$row) { return (float)($type==='income'?$row->net_amount:($type==='fixed'?$row->total_amount:$row->amount)); }
    public static function amounts($total,$opening,$payments) {
        $total=(int)round($total*100);$settled=(int)round($opening*100)+(int)round($payments*100);
        return array('settled'=>$settled/100.0,'remaining'=>max(0,$total-$settled)/100.0,'overpaid'=>$settled>$total);
    }
    private static function protected_source($type,$row) {
        if($type==='income'&&strtolower(trim((string)$row->channel))==='woocommerce')return true;
        if($type==='expense'&&(strpos((string)$row->category,'İade /')===0||strpos(strtolower((string)$row->category),'meta')!==false))return true;
        // Imported automatic sources retain their owner; manual entries stay editable.
        foreach(array('source_ref','document_no') as $field) if(preg_match('/^(woo|meta|mmc)[_:\-]/i',(string)($row->$field??'')))return true;
        return false;
    }
    public static function state($type,$row,$fresh=false) {
        global $wpdb;$key=$type.':'.$row->id;
        if(!$fresh&&isset(self::$cache[$key]))return self::$cache[$key];
        $start=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('settlements').' WHERE record_type=%s AND record_id=%d',$type,$row->id));
        $payments=(float)$wpdb->get_var($wpdb->prepare('SELECT COALESCE(SUM(amount),0) FROM '.self::table('payments')." WHERE record_type=%s AND record_id=%d AND status='posted'",$type,$row->id));
        $known=!!$start;
        $opening=$start?(float)$start->opening_amount:0;
        if(!$start&&$type==='income'){$known=true;$opening=$row->collection_status==='collected'?self::amount($type,$row):0;}
        if(!$start&&$type==='expense'&&$row->payment_method==='Ödenmedi')$known=true;
        return self::$cache[$key]=array_merge(self::amounts(self::amount($type,$row),$opening,$payments),array('known'=>$known,'tracked'=>!!$start,'opening'=>$opening));
    }
    public static function collected($row) { return self::state('income',$row)['settled']; }
    public static function income_label($row) {$state=self::state('income',$row);return $state['remaining']<=0?'Tam tahsil edildi':($state['settled']>0?'Kısmi tahsil edildi':'Tahsilat bekliyor');}
    public static function reject_delete($table,$id) {
        if(!current_user_can(MDG_V5_Finance::CAP))wp_die('Yetkiniz yok.');
        wp_die('Finans kayıtları iz bırakmadan silinemez. Kayıt listesindeki Düzenle alanından gerekçeli düzeltme yapın.');
    }
    private static function money($value) { return number_format_i18n((float)$value,2).' TL'; }
    private static function date($value) {
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$value))return false;
        $p=explode('-',$value);return checkdate((int)$p[1],(int)$p[2],(int)$p[0]);
    }
    private static function decimal($value) {
        $value=trim((string)wp_unslash($value));
        if(strpos($value,',')!==false)$value=str_replace(',','.',str_replace('.','',$value));
        if(!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/',$value))return null;
        return round((float)$value,2);
    }
    private static function text($name) { return sanitize_text_field(wp_unslash($_POST[$name]??'')); }
    private static function field($label,$name,$value='',$type='text',$required=true) {
        echo '<label style="display:block;margin:12px 0"><strong>'.esc_html($label).'</strong><br><input style="min-width:220px" type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.($required?'required':'').'></label>';
    }
    private static function form($type,$id,$operation) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_finance_record"><input type="hidden" name="record_type" value="'.esc_attr($type).'"><input type="hidden" name="record_id" value="'.absint($id).'"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="summary_event" value="'.MDG_V5_Finance::context_event().'"><input type="hidden" name="month" value="'.esc_attr(sanitize_text_field(wp_unslash($_GET['month']??wp_date('Y-m')))).'"><input type="hidden" name="request_key" value="'.esc_attr(wp_generate_uuid4()).'">';
        wp_nonce_field('mdg_v5_finance_record','mdg_v5_finance_nonce');
    }
    public static function row_actions($type,$row) {
        $s=self::state($type,$row);
        echo '<p>'.esc_html($s['known']?('Kayıtlı ödeme/tahsilat: '.self::money($s['settled']).' · kalan: '.self::money($s['remaining'])):'Ödeme hareketi doğrulanmamış').'</p>';
        if(self::protected_source($type,$row)){echo '<small>Otomatik / iade kaydı · kaynak sistemden yönetilir</small>';return;}
        echo '<a class="button" href="'.esc_url(MDG_V5_Finance::url(array('section'=>$type==='fixed'?'fixed':$type,'record_type'=>$type,'record_id'=>$row->id))).'">Düzenle / '.($type==='income'?'Tahsilat':'Ödeme').'</a>';
    }
    public static function render_selected($names) {
        global $wpdb;$type=sanitize_key($_GET['record_type']??'');$id=absint($_GET['record_id']??0);$table=self::source($type);
        if(!$table||!$id)return;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id));
        if(!$row||self::protected_source($type,$row))return;
        $state=self::state($type,$row);
        echo '<div class="mdgv5-card"><h2>Kayıt #'.absint($id).' · '.esc_html($type==='income'?'Gelir':($type==='fixed'?'Sabit gider':'Gider')).'</h2><p>Giderin maliyeti ödeme hareketlerinden ayrı tutulur. Tahsilat kademeli izlenir. Bu ekran yalnız muhasebe kaydı oluşturur.</p>';
        echo '<h3>Gerekçeli düzeltme</h3>';self::form($type,$id,'edit');
        echo '<input type="hidden" name="expected_hash" value="'.esc_attr(hash('sha256',wp_json_encode($row))).'">';
        self::field('Açıklama / ad','description',$type==='fixed'?$row->name:$row->description,'text',false);
        self::field('Not','notes',$row->notes??'','text',false);
        if($type==='fixed') {
            self::field('Ücret / gider tutarı','pay_amount',$row->pay_amount);
            self::field('Kur','fx_rate',$row->fx_rate);
            self::field('Kişi başı SGK / vergi','tax_per_person',$row->tax_per_person);
        } else {
            self::field($type==='income'?'Brüt tutar (TL)':'Maliyet (TL; hatalı kaydı iptal için 0)','amount',$type==='income'?$row->gross_amount:$row->amount);
            if($type==='income')self::field('Komisyon (TL)','commission',$row->commission_amount);
            self::field('Kayıt tarihi','record_date',$type==='income'?$row->income_date:$row->expense_date,'date');
            echo '<label>Kapsam <select name="scope">';foreach(array('program'=>'Program','province'=>'İl','general'=>'Genel','person'=>'Personel','vehicle'=>'Araç') as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($row->scope,$key,false).'>'.esc_html($label).'</option>';echo '</select></label>';
            echo '<label> Program <select name="event_id"><option value="0">Program yok</option>';foreach($names as $eid=>$name)echo '<option value="'.absint($eid).'" '.selected($row->event_id,$eid,false).'>'.esc_html($name.' #'.$eid).'</option>';echo '</select></label>';
        }
        self::field('Düzeltme gerekçesi','reason');echo '<button class="button">Düzeltmeyi Kaydet</button></form>';
        if(!$state['tracked']) {
            echo '<h3>Ödeme / tahsilat takibini başlat</h3><p>Önceki toplam ödeme/tahsilatı belgelere göre belirtin. Geçmiş tutar kasa/banka hareketine tekrar eklenmez.</p>';
            self::form($type,$id,'start');self::field('Daha önce ödenen / tahsil edilen toplam (TL)','opening_amount',$state['known']?$state['settled']:'');self::field('Teyit belgesi / gerekçe','reason');echo '<button class="button">Geçmiş Tutarı Teyit Et</button></form>';
        } else {
            echo '<details><summary>Geçmiş teyit tutarını düzelt</summary>';self::form($type,$id,'opening');self::field('Geçmiş ödeme / tahsilat toplamı (TL)','opening_amount',$state['opening']);self::field('Düzeltme gerekçesi','reason');echo '<button class="button">Geçmiş Tutarı Düzelt</button></form></details>';
            echo '<h3>'.($type==='income'?'Tahsilat':'Ödeme').' hareketi</h3><p>'.esc_html('Kayıtlı: '.self::money($state['settled']).' · Kalan: '.self::money($state['remaining'])).'</p>';
            $accounts=(array)get_option(self::ACCOUNTS,array());
            if(!$accounts)echo '<p>Önce Kasa / Banka sekmesinde hesap tanımlayın.</p>';
            elseif($state['remaining']>0) {
                self::form($type,$id,'payment');self::field('Hareket tutarı (TL)','amount');self::field('Gerçekleşme tarihi','payment_date',wp_date('Y-m-d'),'date');
                echo '<label>Hesap <select name="account_id" required><option value="">Seçin</option>';foreach($accounts as $aid=>$account)echo '<option value="'.esc_attr($aid).'">'.esc_html($account['name']).'</option>';echo '</select></label>';
                self::field('Dekont / referans','reference_no','','text',false);self::field('Hareket açıklaması','reason');echo '<button class="button button-primary">Hareketi Kaydet</button></form>';
            }
        }
        $payments=(array)$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('payments').' WHERE record_type=%s AND record_id=%d ORDER BY id DESC',$type,$id));
        foreach($payments as $payment) {
            echo '<p>'.esc_html('#'.$payment->id.' · '.$payment->payment_date.' · '.self::money($payment->amount).' · '.$payment->status.' · '.$payment->reference_no).'</p>';
            if($payment->status==='posted') {self::form($type,$id,'void');echo '<input type="hidden" name="payment_id" value="'.absint($payment->id).'">';self::field('Hatalı hareketi ters kayıtla kaldırma gerekçesi','reason');echo '<button class="button">Hareketi Ters Kaydet</button></form>';}
        }
        echo '</div>';
    }
    private static function history($type,$id,$operation,$before,$after,$reason) {
        global $wpdb;
        return false!==$wpdb->insert(self::table('finance_history'),array('record_type'=>$type,'record_id'=>$id,'operation'=>$operation,'before_data'=>wp_json_encode($before),'after_data'=>wp_json_encode($after),'reason'=>$reason,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql')));
    }
    /** Per-source MySQL lock also serializes against concurrent edits and retries. */
    public static function save() {
        if(!current_user_can(MDG_V5_Finance::CAP))wp_die('Yetkiniz yok.');
        check_admin_referer('mdg_v5_finance_record','mdg_v5_finance_nonce');global $wpdb;
        $type=sanitize_key($_POST['record_type']??'');$id=absint($_POST['record_id']??0);$table=self::source($type);$op=sanitize_key($_POST['operation']??'');$reason=self::text('reason');
        if(!$table||!$id||!$reason||!in_array($op,array('start','opening','edit','payment','void'),true))wp_die('Kayıt ve gerekçe zorunludur.');
        $lock='mdg_fin_'.substr(hash('sha256',$wpdb->prefix.$type.$id),0,40);
        if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock))!==1)wp_die('Kayıt başka işlemde. Yenileyip tekrar deneyin.');
        foreach(array('settlements','payments','finance_history') as $name){$status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',self::table($name)));if(!$status||strtolower($status->Engine)!=='innodb'){$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));wp_die('Finans takip tabloları hazır değil; işlem yapılmadı.');}}
        $error='';$wpdb->query('START TRANSACTION');
        try {
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$id));
            if(!$row||self::protected_source($type,$row))throw new RuntimeException('Kayıt bulunamadı veya kaynak sistemden yönetiliyor.');
            $state=self::state($type,$row,true);$before=$row;$after=$row;
            if($op==='start'||$op==='opening') {
                $opening=self::decimal($_POST['opening_amount']??'');
                if(($op==='start'&&$state['tracked'])||($op==='opening'&&!$state['tracked'])||$opening===null||$opening+($state['settled']-$state['opening'])>self::amount($type,$row))throw new RuntimeException('Geçmiş tutar geçersiz veya takip zaten başlatılmış.');
                if(false===($op==='start'?$wpdb->insert(self::table('settlements'),array('record_type'=>$type,'record_id'=>$id,'opening_amount'=>$opening,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'))):$wpdb->update(self::table('settlements'),array('opening_amount'=>$opening),array('record_type'=>$type,'record_id'=>$id))))throw new RuntimeException('Takip kaydedilemedi.');
                $before=$state;$after=array('opening_amount'=>$opening);
            } elseif($op==='payment') {
                $amount=self::decimal($_POST['amount']??'');$date=self::text('payment_date');$account=sanitize_key($_POST['account_id']??'');$accounts=(array)get_option(self::ACCOUNTS,array());$key=self::text('request_key');
                if(!preg_match('/^[a-f0-9-]{36}$/i',$key))throw new RuntimeException('İşlem anahtarı geçersiz.');
                $retry=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('payments').' WHERE request_key=%s',$key));
                if($retry) {
                    if($retry->record_type!==$type||(int)$retry->record_id!==$id||(float)$retry->amount!==$amount||$retry->account_id!==$account||$retry->payment_date!==$date)throw new RuntimeException('İşlem anahtarı farklı kayıt için kullanılmış.');
                    $wpdb->query('ROLLBACK');$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));self::redirect($type,$id);
                }
                if(!$state['tracked']||$amount===null||$amount<=0||$amount>$state['remaining']||!self::date($date)||$date>wp_date('Y-m-d')||!isset($accounts[$account]))throw new RuntimeException('Tutar kalan bakiyeyi aşamaz; tarih ve hesap geçerli olmalıdır. Önce geçmiş tutarı teyit edin.');
                $after=array('record_type'=>$type,'record_id'=>$id,'account_id'=>$account,'amount'=>$amount,'payment_date'=>$date,'reference_no'=>self::text('reference_no'),'reason'=>$reason,'request_key'=>$key,'status'=>'posted','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'));
                if(false===$wpdb->insert(self::table('payments'),$after))throw new RuntimeException('Hareket kaydedilemedi.');$before=$state;
            } elseif($op==='void') {
                $payment=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('payments').' WHERE id=%d AND record_type=%s AND record_id=%d',absint($_POST['payment_id']??0),$type,$id));
                if(!$payment||$payment->status!=='posted')throw new RuntimeException('Hareket bulunamadı veya daha önce ters kaydedilmiş.');
                if(false===$wpdb->update(self::table('payments'),array('status'=>'void'),array('id'=>$payment->id)))throw new RuntimeException('Ters kayıt kaydedilemedi.');
                $before=$payment;$after=array('payment_id'=>$payment->id,'status'=>'void');
            } else {
                $engine=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$table));
                if(!$engine||strtolower($engine->Engine)!=='innodb')throw new RuntimeException('Gerekçeli düzeltme için kaynak tablo InnoDB olmalıdır. Kayıt değiştirilmedi.');
                if(!hash_equals(hash('sha256',wp_json_encode($row)),self::text('expected_hash')))throw new RuntimeException('Kayıt başka kullanıcı tarafından değiştirildi. Sayfayı yenileyin.');
                $data=array('notes'=>self::text('notes'),'updated_at'=>current_time('mysql'));
                if($type==='fixed') {
                    $pay=self::decimal($_POST['pay_amount']??'');$fx=self::decimal($_POST['fx_rate']??'');$tax=self::decimal($_POST['tax_per_person']??'');
                    if($pay===null||$fx===null||$fx<=0||$tax===null||!self::text('description'))throw new RuntimeException('Ücret, kur veya vergi geçersiz.');
                    $salary=round($pay*$fx*($row->pay_basis==='person'?max(1,(int)$row->headcount):1),2);$tax_total=round((int)$row->insured_count*$tax,2);
                    $data=array_merge($data,array('name'=>self::text('description'),'pay_amount'=>$pay,'fx_rate'=>$fx,'tax_per_person'=>$tax,'salary_total'=>$salary,'tax_total'=>$tax_total,'total_amount'=>$salary+$tax_total));$total=$salary+$tax_total;
                } else {
                    $amount=self::decimal($_POST['amount']??'');$date=self::text('record_date');$scope=sanitize_key($_POST['scope']??'');$event=absint($_POST['event_id']??0);
                    if($amount===null||$amount<0||!self::date($date)||!in_array($scope,array('program','province','general','person','vehicle'),true))throw new RuntimeException('Tutar, tarih veya kapsam geçersiz.');
                    if($scope==='program'&&(!$event||!class_exists('MDG_DB')||!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.MDG_DB::table('events').' WHERE id=%d',$event))))throw new RuntimeException('Program doğrulanamadı.');
                    $data=array_merge($data,array('description'=>self::text('description'),'scope'=>$scope,'event_id'=>$scope==='program'?$event:0));
                    if($type==='income') {$commission=self::decimal($_POST['commission']??'');if($commission===null||$commission>$amount)throw new RuntimeException('Komisyon brüt tutarı aşamaz.');$data+=array('gross_amount'=>$amount,'commission_amount'=>$commission,'net_amount'=>$amount-$commission,'income_date'=>$date);$total=$amount-$commission;}
                    else {$data+=array('amount'=>$amount,'expense_date'=>$date);$total=$amount;}
                }
                if($state['tracked']&&$total<$state['settled'])throw new RuntimeException('Tutar kayıtlı ödeme/tahsilatın altına düşürülemez. Önce hatalı hareketi ters kaydedin.');
                if(false===$wpdb->update($table,$data,array('id'=>$id)))throw new RuntimeException('Düzeltme kaydedilemedi.');
                $after=$data;
            }
            if(!self::history($type,$id,$op,$before,$after,$reason))throw new RuntimeException('İşlem geçmişi yazılamadı; işlem geri alındı.');
            if(false===$wpdb->query('COMMIT'))throw new RuntimeException('İşlem tamamlanamadı.');
        } catch (RuntimeException $e) {$wpdb->query('ROLLBACK');$error=$e->getMessage();}
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        if($error)wp_die(esc_html($error));
        self::redirect($type,$id);
    }
    private static function redirect($type,$id) {wp_safe_redirect(MDG_V5_Finance::url(array('section'=>$type==='fixed'?'fixed':$type,'record_type'=>$type,'record_id'=>$id,'finance_saved'=>'record')));exit;}
    public static function save_account() {
        if(!current_user_can(MDG_V5_Finance::CAP))wp_die('Yetkiniz yok.');check_admin_referer('mdg_v5_finance_account','mdg_v5_finance_nonce');
        $name=self::text('account_name');$kind=sanitize_key($_POST['account_kind']??'');
        if(!$name||!in_array($kind,array('cash','bank'),true))wp_die('Hesap adı ve türü zorunludur.');
        $accounts=(array)get_option(self::ACCOUNTS,array());$key=str_replace('-','',wp_generate_uuid4());$accounts[$key]=array('name'=>$name,'kind'=>$kind,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'));
        if(!update_option(self::ACCOUNTS,$accounts,false))wp_die('Hesap kaydedilemedi.');
        wp_safe_redirect(MDG_V5_Finance::url(array('section'=>'accounts','finance_saved'=>'account')));exit;
    }
    public static function render_section($section,$month,$events,$expenses) {
        global $wpdb;
        if($section==='accounts') {
            $accounts=(array)get_option(self::ACCOUNTS,array());
            $rows=(array)$wpdb->get_results('SELECT account_id,record_type,SUM(amount) amount FROM '.self::table('payments')." WHERE status='posted' GROUP BY account_id,record_type");
            $totals=array();foreach($rows as $r)$totals[$r->account_id]=($totals[$r->account_id]??0)+($r->record_type==='income'?1:-1)*(float)$r->amount;
            echo '<div class="mdgv5-card"><h2>Kasa / Banka Hareketleri</h2><p>Aşağıdaki rakamlar bu merkezde kaydedilen hareketlerin tüm tarihlerdeki netidir. Açılış bakiyesi ve banka ekstresi alınmadığı için gerçek hesap bakiyesi değildir. Geçmiş teyit tutarları tekrar eklenmez.</p><table class="widefat striped"><tr><th>Hesap</th><th>Tür</th><th>Kayıtlı hareket neti</th></tr>';
            foreach($accounts as $id=>$account)echo '<tr><td>'.esc_html($account['name']).'</td><td>'.esc_html($account['kind']==='cash'?'Kasa':'Banka').'</td><td>'.esc_html(self::money($totals[$id]??0)).'</td></tr>';
            echo '</table><h3>Hesap Tanımla</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_v5_finance_account"><input type="hidden" name="summary_event" value="'.MDG_V5_Finance::context_event().'">';wp_nonce_field('mdg_v5_finance_account','mdg_v5_finance_nonce');self::field('Hesap adı','account_name');echo '<select name="account_kind"><option value="cash">Kasa</option><option value="bank">Banka</option></select> <button class="button">Hesap Ekle</button></form></div>';
        } elseif($section==='history') {
            $id=MDG_V5_Finance::context_event();$where='';$args=array();
            if($id){$where=' WHERE (record_type="expense" AND record_id IN (SELECT id FROM '.self::table('expenses').' WHERE event_id=%d)) OR (record_type="income" AND record_id IN (SELECT id FROM '.self::table('incomes').' WHERE event_id=%d))';$args=array($id,$id);}
            $sql='SELECT * FROM '.self::table('finance_history').$where.' ORDER BY id DESC LIMIT 100';$rows=(array)$wpdb->get_results($args?$wpdb->prepare($sql,$args):$sql);
            echo '<div class="mdgv5-card"><h2>İşlem Geçmişi</h2><p>Son 100 işlem · '.($id?'seçili programa bağlı mevcut kayıtlar':'bütün kayıtlar').' · tarih, kullanıcı, gerekçe ve önce/sonra değerleri</p><table class="widefat striped"><tr><th>Tarih / kullanıcı</th><th>Kayıt / işlem</th><th>Gerekçe</th><th>Değişiklik</th></tr>';
            foreach($rows as $row)echo '<tr><td>'.esc_html($row->created_at.' · kullanıcı #'.$row->created_by).'</td><td>'.esc_html($row->record_type.' #'.$row->record_id.' · '.$row->operation).'</td><td>'.esc_html($row->reason).'</td><td><details><summary>Önce / sonra</summary><pre style="white-space:pre-wrap">'.esc_html($row->before_data."\n→\n".$row->after_data).'</pre></details></td></tr>';
            echo '</table></div>';
        } elseif($section==='shared') {
            $parts=MDG_V5_Finance::allocated_rows($expenses);$distributed=0;foreach($parts as $part)$distributed+=$part['amount'];
            $common=0;foreach($expenses as $row)if($row->scope!=='program')$common+=(float)$row->amount;
            echo '<div class="mdgv5-card"><h2>Ortak Gider Dağıtımı · '.esc_html($month).'</h2><p>'.esc_html('Ortak gider: '.self::money($common).' · Dağıtılmış: '.self::money($distributed).' · Dağıtılmamış: '.self::money(max(0,$common-$distributed))).'</p><p>Her gider tek kayıttır. Paylaştırmak işletme maliyetini artırmaz. Tutar değişirse eski dağıtım geçersiz olur ve yeniden onay gerekir.</p><table class="widefat striped"><tr><th>Kaynak gider</th><th>Program</th><th>Pay</th></tr>';
            $names=array();foreach($events as $event)$names[$event->id]=MDG_V5_Finance::event_name($event);
            foreach($parts as $part)echo '<tr><td>'.esc_html('#'.$part['source']->id.' '.$part['source']->description).'</td><td>'.esc_html($names[$part['event_id']]??('#'.$part['event_id'])).'</td><td>'.esc_html(self::money($part['amount'])).'</td></tr>';
            echo '</table><p><a class="button" href="'.esc_url(MDG_V5_Finance::url(array('section'=>'expense','month'=>$month))).'">Giderleri ve Payları Düzenle</a></p><h3>Sabit gider dağıtımına giren programlar</h3><p>MDG durumları gösterilir. “closed” satış kapanışı olabilir; otomatik iptal sayılmaz. Listeyi kontrol edip Seyirci ekranından “Dâhil” seçimini düzenleyin. Elle girilmiş program sayısı da sabit gider ekranında görünür.</p><ul>';
            $attendance=(array)$wpdb->get_results('SELECT event_id,include_in_reports FROM '.self::table('attendance'),OBJECT_K);
            foreach($events as $event)if(strpos(MDG_V5_Finance::event_date($event),$month)===0)echo '<li>'.esc_html(($names[$event->id]??'#'.$event->id).' #'.$event->id.' · '.($event->status??'durum yok').' · '.(isset($attendance[$event->id])&&!(int)$attendance[$event->id]->include_in_reports?'elle hariç':'MDG durum filtresine bağlı')).'</li>';
            echo '</ul><a class="button" href="'.esc_url(MDG_V5_Finance::url(array('section'=>'attendance','month'=>$month))).'">Programları Kontrol Et</a></div>';
        } else {
            $id=MDG_V5_Finance::context_event();$pid=0;
            if($id&&class_exists('MMC_MDG_Bridge_Service')){$bridge=$wpdb->get_row($wpdb->prepare('SELECT program_id FROM '.MMC_MDG_Bridge_Service::table().' WHERE mdg_event_id=%d AND is_active=1 ORDER BY id DESC LIMIT 1',$id));if($bridge)$pid=absint($bridge->program_id);}
            echo '<div class="mdgv5-card"><h2>Fatura / Teminat / Kapanış</h2><p>Bu işlemler mevcut MMC kayıtlarında sürer. V5 giderlerini MMC defterine yeniden girmeyin; iki defterin toplamları birleştirilmez.</p>';
            if($id&&!$pid){echo '<p>Seçili programın MMC eşleşmesi doğrulanamadı; başka program tahmin edilmedi.</p></div>';return;}
            echo '<a class="button button-primary" href="'.esc_url(add_query_arg(array('page'=>'mmc-finance','program_id'=>$pid),admin_url('admin.php'))).'">MMC Fatura, Teminat ve Kapanış</a></div>';
        }
    }
}
