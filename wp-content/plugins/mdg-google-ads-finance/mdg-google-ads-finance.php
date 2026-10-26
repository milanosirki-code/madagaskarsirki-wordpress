<?php
/**
 * Plugin Name: MDG Google Ads Finans
 * Description: Google Ads günlük gerçekleşen harcamalarını Finans V5'e kontrollü aktarır.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }

final class MDG_GAds_Finance {
    const OPTION = 'mdg_gads_config_v1';
    const SECRET = 'mdg_gads_secret_v1';
    const HOOK = 'mdg_gads_finance_hourly';
    const VENDOR = 'Google Ads API';

    public static function boot() {
        add_action('admin_menu', function () { add_submenu_page('mdg-dashboard', 'Google Ads Finans', 'Google Ads Finans', 'manage_options', 'mdg-gads-finance', array(__CLASS__, 'render')); });
        add_action('admin_post_mdg_gads_action', array(__CLASS__, 'admin_action'));
        add_action('admin_post_mdg_gads_callback', array(__CLASS__, 'callback'));
        add_action(self::HOOK, array(__CLASS__, 'cron'));
        // Only our source-owned amount/identity is protected. Native payment tracking remains usable.
        add_action('admin_post_mdg_v5_finance_record', array(__CLASS__, 'protect'), 1);
        add_action('admin_post_mdg_v5_save_allocation', array(__CLASS__, 'protect_allocation'), 1);
    }
    public static function table($name) { global $wpdb; return $wpdb->prefix.'mdg_gads_'.$name; }
    public static function config() { return (array)get_option(self::OPTION, array()); }
    private static function save_config($config) { update_option(self::OPTION, $config, false); if (self::config() !== $config) throw new RuntimeException('Ayar kaydedilemedi.'); }
    private static function write($ok) { if ($ok === false) throw new RuntimeException('Veritabanı yazımı başarısız; işlem geri alındı.'); }
    public static function date_ok($date) { $d=DateTimeImmutable::createFromFormat('!Y-m-d', (string)$date); return $d && $d->format('Y-m-d') === $date; }
    public static function cents($micros) {
        $s=(string)$micros;
        if (!preg_match('/^\d{1,16}$/D', $s) || strlen($s)>15) throw new RuntimeException('Geçersiz cost_micros.');
        return intdiv((int)$s+5000,10000);
    }
    public static function ref($customer,$campaign,$date,$uuid) { return 'GADS-'.hash('sha256', $customer.'|'.$campaign.'|'.$date.'|'.$uuid); }
    public static function install() {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $collate=$wpdb->get_charset_collate();
        dbDelta('CREATE TABLE '.self::table('daily')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            customer_id varchar(20) NOT NULL,
            campaign_id varchar(24) NOT NULL,
            metric_date date NOT NULL,
            campaign_name varchar(255) NOT NULL,
            cost_micros bigint unsigned NOT NULL DEFAULT 0,
            impressions bigint unsigned NOT NULL DEFAULT 0,
            clicks bigint unsigned NOT NULL DEFAULT 0,
            currency varchar(3) NOT NULL,
            event_uuid varchar(36) NOT NULL DEFAULT '',
            expense_id bigint unsigned NOT NULL DEFAULT 0,
            expense_hash varchar(64) NOT NULL DEFAULT '',
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source (customer_id,campaign_id,metric_date)
        ) ENGINE=InnoDB $collate;");
        dbDelta('CREATE TABLE '.self::table('evidence')." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            expense_id bigint unsigned NOT NULL,
            kind varchar(10) NOT NULL,
            reference_no varchar(190) NOT NULL,
            attachment_id bigint unsigned NOT NULL,
            payment_id bigint unsigned NOT NULL DEFAULT 0,
            created_by bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY match_ref (expense_id,kind,reference_no)
        ) ENGINE=InnoDB $collate;");
        self::engines();
    }
    private static function engines() {
        global $wpdb;
        foreach (array(self::table('daily'),self::table('evidence'),$wpdb->prefix.'mdg_v5_expenses',$wpdb->prefix.'mdg_v5_finance_history',$wpdb->prefix.'mdg_v5_payments',$wpdb->prefix.'mdg_v5_settlements') as $table) {
            $row=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$table));
            if (!$row || strtolower($row->Engine)!=='innodb') throw new RuntimeException('Finans tabloları hazır/InnoDB değil.');
        }
    }
    private static function secret($set=null) {
        $key=hash('sha256',wp_salt('auth').'mdg-gads-v1',true);
        if ($set!==null) {
            $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(wp_json_encode($set),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
            if ($cipher===false) throw new RuntimeException('Kimlik bilgileri şifrelenemedi.');
            $sealed=base64_encode($iv.$tag.$cipher);update_option(self::SECRET,$sealed,false);
            if (get_option(self::SECRET)!==$sealed) throw new RuntimeException('Kimlik bilgileri kaydedilemedi.');
            return $set;
        }
        $raw=base64_decode((string)get_option(self::SECRET,''),true);
        if (!$raw) return array();
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        if ($plain===false) throw new RuntimeException('Kimlik bilgileri açılamadı; güvenli bağlantıyı yenileyin.');
        return (array)json_decode($plain,true);
    }
    private static function token_request($body) {
        $r=wp_remote_post('https://oauth2.googleapis.com/token',array('timeout'=>30,'redirection'=>0,'body'=>$body));
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200) throw new RuntimeException('Google OAuth başarısız; bağlantı iznini kontrol edin.');
        $j=json_decode(wp_remote_retrieve_body($r),true);
        if (empty($j['access_token'])) throw new RuntimeException('Google erişim belirteci alınamadı.');
        return $j;
    }
    private static function access_token() {
        $s=self::secret();
        if (empty($s['client_id'])||empty($s['client_secret'])||empty($s['refresh_token'])) throw new RuntimeException('Google OAuth kullanıcı onayı gerekli.');
        return self::token_request(array('grant_type'=>'refresh_token','client_id'=>$s['client_id'],'client_secret'=>$s['client_secret'],'refresh_token'=>$s['refresh_token']))['access_token'];
    }
    private static function query($query,$token) {
        $c=self::config();$customer=$c['customer']??'';$version=$c['version']??'v26';
        if (!preg_match('/^\d{10}$/D',$customer)||!preg_match('/^v\d{2}$/D',$version)) throw new RuntimeException('Google hesap/sürüm ayarı geçersiz.');
        $headers=array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json');
        if (!empty($c['manager'])) $headers['login-customer-id']=$c['manager'];
        // API access is attached to the OAuth Cloud project (developer-token sunset, 2026-09-09).
        $all=array();$page='';$seen=array();
        do {
            $body=array('query'=>$query);if ($page) $body['pageToken']=$page;
            $r=wp_remote_post('https://googleads.googleapis.com/'.$version.'/customers/'.$customer.'/googleAds:search',array('headers'=>$headers,'body'=>wp_json_encode($body),'timeout'=>60,'redirection'=>0));
            if (is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200) {
                $code=is_wp_error($r)?'transport':(string)wp_remote_retrieve_response_code($r);
                throw new RuntimeException('Google Ads API erişimi başarısız ('.$code.'); Cloud proje API izni ve hesap erişimini kontrol edin.');
            }
            $j=json_decode(wp_remote_retrieve_body($r),true);
            if (!is_array($j)||isset($j['error'])||(!isset($j['fieldMask'])&&!isset($j['results']))) throw new RuntimeException('Google Ads yanıtı doğrulanamadı.');
            $all=array_merge($all,$j['results']??array());$page=$j['nextPageToken']??'';
            if ($page && isset($seen[$page])) throw new RuntimeException('Google sayfalama döngüsü.');
            $seen[$page]=true;
            if (count($all)>100000) throw new RuntimeException('Sorgu sınırı aşıldı.');
        } while ($page);
        return $all;
    }
    public static function normalize($rows,$customer,$from,$to) {
        $out=array();
        foreach ($rows as $r) {
            $id=(string)($r['campaign']['id']??'');$date=$r['segments']['date']??'';$account=$r['customer']??array();
            if (!preg_match('/^\d{1,24}$/D',$id)||!self::date_ok($date)||$date<$from||$date>$to||($account['id']??'')!==$customer||($account['currencyCode']??'')!=='TRY') throw new RuntimeException('Hesap, tarih veya TRY para birimi doğrulanamadı.');
            $metrics=$r['metrics']??array();$micros=(string)($metrics['costMicros']??'0');self::cents($micros);
            foreach (array('impressions','clicks') as $field) if (!preg_match('/^\d{1,15}$/D',(string)($metrics[$field]??'0'))) throw new RuntimeException('Geçersiz metrik.');
            $key=$id.'|'.$date;if(isset($out[$key])) throw new RuntimeException('Aynı kampanya/gün iki kez döndü.');
            $out[$key]=array('customer_id'=>$customer,'campaign_id'=>$id,'metric_date'=>$date,'campaign_name'=>sanitize_text_field($r['campaign']['name']??''),'cost_micros'=>$micros,'currency'=>'TRY','impressions'=>(string)($metrics['impressions']??'0'),'clicks'=>(string)($metrics['clicks']??'0'));
        }
        return $out;
    }
    private static function event($uuid) {
        global $wpdb;
        if (!class_exists('MDG_DB')||!preg_match('/^[a-f0-9-]{36}$/Di',$uuid)) throw new RuntimeException('MDG program UUID gerekli.');
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.MDG_DB::table('events').' WHERE public_uuid=%s',$uuid));
        if (count($rows)!==1||in_array($rows[0]->status,array('draft','trash','cancelled','canceled'),true)) throw new RuntimeException('MDG program kimliği/durumu doğrulanamadı.');
        return $rows[0];
    }
    public static function fetch() {
        $c=self::config();$from=$c['start']??'';
        if (!self::date_ok($from)) throw new RuntimeException('Başlangıç tarihi gerekli.');
        $token=self::access_token();$meta=self::query('SELECT customer.id, customer.currency_code, customer.time_zone FROM customer',$token);
        $m=$meta[0]['customer']??array();
        if (count($meta)!==1||($m['id']??'')!==$c['customer']||($m['currencyCode']??'')!=='TRY') throw new RuntimeException('Hesap/TRY doğrulaması başarısız.');
        $zone=new DateTimeZone($m['timeZone']);$today=(new DateTimeImmutable('now',$zone))->format('Y-m-d');
        if ($from>$today||(new DateTimeImmutable($from))->diff(new DateTimeImmutable($today))->days>366) throw new RuntimeException('Aktarım dönemi en fazla 366 gün olmalıdır.');
        $inventory=self::query('SELECT campaign.id, campaign.name, campaign.status FROM campaign',$token);
        $campaigns=array();foreach ($inventory as $r) $campaigns[(string)$r['campaign']['id']]=$r['campaign'];
        $q="SELECT customer.id, customer.currency_code, campaign.id, campaign.name, segments.date, metrics.cost_micros, metrics.impressions, metrics.clicks FROM campaign WHERE segments.date BETWEEN '$from' AND '$today'";
        $rows=self::normalize(self::query($q,$token),$c['customer'],$from,$today);
        global $wpdb;
        // Google suppresses all-zero metric rows. A successful complete query can zero a prior expense.
        $old=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table('daily').' WHERE customer_id=%s AND metric_date>=%s AND metric_date<=%s',$c['customer'],$from,$today),ARRAY_A);
        foreach($old as $r) {
            $key=$r['campaign_id'].'|'.$r['metric_date'];
            if (!isset($rows[$key])) {
                if (!isset($campaigns[$r['campaign_id']])) throw new RuntimeException('Önceki kampanya envanterde yok; sıfırlama durduruldu.');
                $r['cost_micros']='0';$r['impressions']='0';$r['clicks']='0';$r['campaign_name']=$campaigns[$r['campaign_id']]['name'];
                $rows[$key]=array_intersect_key($r,array_flip(array('customer_id','campaign_id','metric_date','campaign_name','cost_micros','currency','impressions','clicks')));
            }
        }
        return array('rows'=>$rows,'campaigns'=>$campaigns,'from'=>$from,'to'=>$today,'timezone'=>$m['timeZone']);
    }
    public static function preview($data) {
        $c=self::config();$totals=array();$unmapped=0;
        foreach($data['rows'] as $r) {
            $id=$r['campaign_id'];$totals[$id]=($totals[$id]??0)+(int)$r['cost_micros'];
            if (!isset($c['maps'][$id])) $unmapped++;
        }
        foreach($c['maps']??array() as $id=>$uuid) { if(!isset($data['campaigns'][$id])) throw new RuntimeException('Eşlenen kampanya hesapta bulunamadı.');self::event($uuid); }
        return array('campaigns'=>$data['campaigns'],'totals_micros'=>$totals,'unmapped_days'=>$unmapped,'from'=>$data['from'],'to'=>$data['to'],'timezone'=>$data['timezone']);
    }
    private static function audit($id,$before,$after) {
        global $wpdb;self::write($wpdb->insert($wpdb->prefix.'mdg_v5_finance_history',array('record_type'=>'expense','record_id'=>$id,'operation'=>'gads_sync','before_data'=>wp_json_encode($before),'after_data'=>wp_json_encode($after),'reason'=>'Google Ads gerçekleşen günlük maliyet; ödeme teyidi değildir.','created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'))));
    }
    public static function apply($rows) {
        global $wpdb;self::engines();$c=self::config();$count=0;
        foreach($rows as $r) {
            $uuid=$c['maps'][$r['campaign_id']]??'';$event=$uuid?self::event($uuid):null;
            $table=$wpdb->prefix.'mdg_v5_expenses';$daily=self::table('daily');
            self::write($wpdb->query('START TRANSACTION'));
            try {
                $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM $daily WHERE customer_id=%s AND campaign_id=%s AND metric_date=%s FOR UPDATE",$r['customer_id'],$r['campaign_id'],$r['metric_date']));
                $eid=$old?(int)$old->expense_id:0;$before=null;$hash=$old?$old->expense_hash:'';
                if ($old && $old->event_uuid && $old->event_uuid!==$uuid) throw new RuntimeException('Geçmiş program eşleştirmesi değiştirilemez.');
                if ($event) {
                    $ref=self::ref($r['customer_id'],$r['campaign_id'],$r['metric_date'],$uuid);
                    if ($eid) {
                        $before=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d FOR UPDATE",$eid));
                        if (!$before||$before->document_no!==$ref||$before->vendor!==self::VENDOR||!hash_equals($hash,hash('sha256',wp_json_encode($before)))) throw new RuntimeException('Kaynak gider değişmiş; üzerine yazılmadı.');
                    } elseif ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE document_no=%s",$ref))) throw new RuntimeException('Bağsız kaynak referansı mevcut; inceleme gerekli.');
                    $amount=number_format(self::cents($r['cost_micros'])/100,2,'.','');
                    $name=function_exists('mb_substr')?mb_substr($r['campaign_name'],0,200,'UTF-8'):substr($r['campaign_name'],0,200);
                    $owned=array('event_id'=>(int)$event->id,'scope'=>'program','province'=>$event->province_name??'','category'=>'Reklam / Google Ads','description'=>'Google Ads: '.$name,'amount'=>$amount,'expense_date'=>$r['metric_date']);
                    $change=!$before;foreach($owned as $k=>$v) if($before&&(string)$before->$k!==(string)$v)$change=true;
                    if ($change&&($eid||self::cents($r['cost_micros'])>0)) {
                        $owned['updated_at']=current_time('mysql');
                        if ($eid) self::write($wpdb->update($table,$owned,array('id'=>$eid)));
                        else { self::write($wpdb->insert($table,$owned+array('document_no'=>$ref,'vendor'=>self::VENDOR,'payment_method'=>'Ödenmedi','notes'=>'API maliyeti. Fatura ve banka hareketi ayrı eşleştirilir; ikinci gider yazılmaz.','created_by'=>0,'created_at'=>current_time('mysql'))));$eid=(int)$wpdb->insert_id; }
                        $after=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$eid));
                        if (!$after) throw new RuntimeException('Yazım sonrası gider okunamadı.');
                        self::audit($eid,$before,$after);$hash=hash('sha256',wp_json_encode($after));$count++;
                    }
                }
                $record=$r+array('event_uuid'=>$uuid,'expense_id'=>$eid,'expense_hash'=>$hash,'updated_at'=>current_time('mysql'));
                self::write($old?$wpdb->update($daily,$record,array('id'=>$old->id)):$wpdb->insert($daily,$record));
                self::write($wpdb->query('COMMIT'));
            } catch (Throwable $e) { $wpdb->query('ROLLBACK');throw $e; }
        }
        return $count;
    }
    private static function lock_name() { global $wpdb;return 'mdg_gads_'.substr(hash('sha256',$wpdb->prefix),0,32); }
    public static function sync($dry=true) {
        global $wpdb;$lock=self::lock_name();
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!=='1') throw new RuntimeException('Google aktarımı zaten çalışıyor.');
        try { $data=self::fetch();$summary=self::preview($data);if(!$dry)$summary['changed']=self::apply($data['rows']);return $summary; }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
    }
    public static function cron() {
        if (empty(self::config()['enabled'])) return;
        try { $r=self::sync(false);update_option('mdg_gads_last_run',array('at'=>current_time('mysql'),'ok'=>true,'result'=>$r),false); }
        catch (Throwable $e) { update_option('mdg_gads_last_run',array('at'=>current_time('mysql'),'ok'=>false,'error'=>$e->getMessage()),false); }
    }
    public static function protect() {
        if (($_POST['record_type']??'')!=='expense'||($_POST['operation']??'')!=='edit') return;
        global $wpdb;$row=$wpdb->get_row($wpdb->prepare("SELECT vendor FROM {$wpdb->prefix}mdg_v5_expenses WHERE id=%d",absint($_POST['record_id']??0)));
        if ($row && $row->vendor===self::VENDOR) wp_die('Otomatik Google giderinin tutarı ve kimliği yalnız kaynaktan güncellenir. Ödeme takibi ayrı yapılabilir.');
    }
    public static function protect_allocation() {
        global $wpdb;$vendor=$wpdb->get_var($wpdb->prepare("SELECT vendor FROM {$wpdb->prefix}mdg_v5_expenses WHERE id=%d",absint($_POST['expense_id']??0)));
        if($vendor===self::VENDOR)wp_die('Google kampanyası program UUID ile eşlidir; ikinci ortak gider payı oluşturulamaz.');
    }
    private static function callback_url() { return admin_url('admin-post.php?action=mdg_gads_callback'); }
    public static function callback() {
        if(!current_user_can('manage_options'))wp_die('Yetkiniz yok.');
        $state=sanitize_text_field(wp_unslash($_GET['state']??''));$key='mdg_gads_oauth_'.get_current_user_id();$expected=get_transient($key);delete_transient($key);
        if(!$expected||!hash_equals($expected,$state))wp_die('OAuth durumu geçersiz veya süresi dolmuş.');
        try {
            if(!empty($_GET['error']))throw new RuntimeException('Google kullanıcı onayı verilmedi.');
            $s=self::secret();$j=self::token_request(array('grant_type'=>'authorization_code','code'=>sanitize_text_field(wp_unslash($_GET['code']??'')),'client_id'=>$s['client_id'],'client_secret'=>$s['client_secret'],'redirect_uri'=>self::callback_url()));
            if(empty($j['refresh_token']))throw new RuntimeException('Çevrimdışı erişim izni eksik.');
            $s['refresh_token']=$j['refresh_token'];self::secret($s);
            wp_safe_redirect(admin_url('admin.php?page=mdg-gads-finance'));exit;
        }catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
    }
    public static function admin_action() {
        if(!current_user_can('manage_options'))wp_die('Yetkiniz yok.');check_admin_referer('mdg_gads_action');$op=sanitize_key($_POST['operation']??'');
        global $wpdb;$locked=false;$lock=self::lock_name();
        try {
            if(in_array($op,array('config','credentials','pause'),true)) {
                if((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!=='1')throw new RuntimeException('Google aktarımı çalışıyor; ayarı daha sonra kaydedin.');$locked=true;
            }
            self::install();$c=self::config();
            if($op==='credentials') {
                $id=sanitize_text_field(wp_unslash($_POST['client_id']??''));$secret=trim(wp_unslash($_POST['client_secret']??''));
                if(!preg_match('/^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/D',$id)||strlen($secret)<10)throw new RuntimeException('Web OAuth istemcisi gerekli.');
                self::secret(array('client_id'=>$id,'client_secret'=>$secret));$c['enabled']=false;self::save_config($c);delete_transient('mdg_gads_preview');
            } elseif($op==='oauth') {
                $s=self::secret();if(empty($s['client_id']))throw new RuntimeException('Önce OAuth istemcisini kaydedin.');
                $state=bin2hex(random_bytes(32));set_transient('mdg_gads_oauth_'.get_current_user_id(),$state,600);
                wp_redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(array('client_id'=>$s['client_id'],'redirect_uri'=>self::callback_url(),'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/adwords','access_type'=>'offline','prompt'=>'consent','state'=>$state)));exit;
            } elseif($op==='config') {
                if(!empty($c['enabled']))throw new RuntimeException('Önce aktarımı duraklatın.');
                $customer=str_replace('-','',sanitize_text_field(wp_unslash($_POST['customer']??'')));$manager=str_replace('-','',sanitize_text_field(wp_unslash($_POST['manager']??'')));$start=sanitize_text_field(wp_unslash($_POST['start']??''));
                if(!preg_match('/^\d{10}$/D',$customer)||($manager&&!preg_match('/^\d{10}$/D',$manager))||!self::date_ok($start)||empty($_POST['acknowledge']))throw new RuntimeException('Hesap, başlangıç ve manuel gider çakışma teyidi gerekli.');
                if(!empty($c['customer'])&&$c['customer']!==$customer)throw new RuntimeException('Hesap kimliği değiştirilemez.');
                if(!empty($c['start'])&&$start>$c['start'])throw new RuntimeException('Revizyonları kaçırmamak için başlangıç ileri taşınamaz.');
                $maps=$c['maps']??array();$campaign=sanitize_text_field(wp_unslash($_POST['campaign']??''));$uuid=sanitize_text_field(wp_unslash($_POST['uuid']??''));
                if($campaign||$uuid){if(!preg_match('/^\d{1,24}$/D',$campaign))throw new RuntimeException('Kampanya ID geçersiz.');self::event($uuid);if(isset($maps[$campaign])&&$maps[$campaign]!==$uuid)throw new RuntimeException('Geçmiş program eşleştirmesi değiştirilemez.');$maps[$campaign]=$uuid;}
                self::save_config(array('customer'=>$customer,'manager'=>$manager,'start'=>$start,'version'=>'v26','maps'=>$maps,'enabled'=>false));delete_transient('mdg_gads_preview');
            } elseif($op==='test') {
                $r=self::sync(true);set_transient('mdg_gads_preview',array('config_hash'=>hash('sha256',wp_json_encode($c)),'at'=>time(),'result'=>$r),1800);
            } elseif($op==='enable') {
                $p=get_transient('mdg_gads_preview');if(!$p||!hash_equals($p['config_hash'],hash('sha256',wp_json_encode($c)))||empty($c['maps']))throw new RuntimeException('Önce güncel yapılandırmayla başarılı API testi ve program eşleştirmesi gerekli.');
                self::sync(false);$c['enabled']=true;self::save_config($c);
                if(!wp_next_scheduled(self::HOOK)&&!wp_schedule_event(time()+300,'hourly',self::HOOK))throw new RuntimeException('Zamanlama oluşturulamadı.');
            } elseif($op==='pause') { $c['enabled']=false;self::save_config($c);wp_clear_scheduled_hook(self::HOOK); }
            elseif($op==='evidence') { self::evidence(); }
            else throw new RuntimeException('İşlem bilinmiyor.');
            if($locked){$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));$locked=false;}
            wp_safe_redirect(admin_url('admin.php?page=mdg-gads-finance'));exit;
        }catch(Throwable $e){if($locked)$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));wp_die(esc_html($e->getMessage()));}
    }
    private static function evidence() {
        global $wpdb;$id=absint($_POST['expense_id']??0);$kind=sanitize_key($_POST['kind']??'');$ref=sanitize_text_field(wp_unslash($_POST['reference']??''));$att=absint($_POST['attachment_id']??0);$pid=absint($_POST['payment_id']??0);
        $expense=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mdg_v5_expenses WHERE id=%d AND vendor=%s",$id,self::VENDOR));
        if(!$expense||!in_array($kind,array('invoice','bank'),true)||!$ref||strlen($ref)>190||get_post_type($att)!=='attachment'||empty($_POST['verified']))throw new RuntimeException('Google gideri, belge eki, referans ve belge kontrolü teyidi gerekli.');
        if($kind==='bank') {
            $payment=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mdg_v5_payments WHERE id=%d AND record_type='expense' AND record_id=%d AND status='posted'",$pid,$id));
            if(!$payment||$payment->reference_no!==$ref)throw new RuntimeException('Önce yerel ödeme ekranından aynı dekont referansıyla ödeme hareketini kaydedin.');
        }else $pid=0;
        $data=array('expense_id'=>$id,'kind'=>$kind,'reference_no'=>$ref,'attachment_id'=>$att,'payment_id'=>$pid,'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'));
        $existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('evidence').' WHERE expense_id=%d AND kind=%s AND reference_no=%s',$id,$kind,$ref));
        if($existing){if((int)$existing->attachment_id!==$att||(int)$existing->payment_id!==$pid)throw new RuntimeException('Aynı belge eşleştirmesi farklı bilgiler içeriyor.');return;}
        self::write($wpdb->query('START TRANSACTION'));try{self::write($wpdb->insert(self::table('evidence'),$data));self::audit($id,null,array('evidence'=>$data));self::write($wpdb->query('COMMIT'));}catch(Throwable $e){$wpdb->query('ROLLBACK');throw $e;}
    }
    private static function form($op,$label) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdg_gads_action"><input type="hidden" name="operation" value="'.esc_attr($op).'">';wp_nonce_field('mdg_gads_action');echo '<h2>'.esc_html($label).'</h2>'; }
    private static function field($label,$name,$value='',$type='text') { echo '<p><label>'.esc_html($label).' <input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" autocomplete="off"></label></p>'; }
    private static function button($text) { echo '<button class="button">'.esc_html($text).'</button></form>'; }
    public static function render() {
        if(!current_user_can('manage_options'))return;$c=self::config();echo '<div class="wrap"><h1>Google Ads · Finans V5</h1><p>Günlük gerçekleşen harcama; fatura ve banka ödemesi ayrı izlenir. Yeni giderler Ödenmedi olarak kaydedilir. Eşleşmeyen kampanyalar inceleme havuzunda kalır.</p>';
        echo '<p>Aktarım: <strong>'.(!empty($c['enabled'])?'Açık':'Kapalı').'</strong> · OAuth dönüş adresi: <code>'.esc_html(self::callback_url()).'</code></p>';
        self::form('credentials','Güvenli Google API bağlantısı');echo '<p>Google Cloud üzerinde Google Ads API üretim erişimi olan Web OAuth istemcisi kullanın. Gizli anahtarı bu yönetici formuna girin.</p>';self::field('Client ID','client_id');self::field('Client secret','client_secret','','password');self::button('İstemciyi Kaydet');
        self::form('oauth','Google kullanıcı onayı');self::button('Google ile Güvenli Bağlan');
        self::form('config','Hesap ve kalıcı program eşleştirmesi');self::field('Müşteri hesabı','customer',$c['customer']??'');self::field('Yönetici hesabı (isteğe bağlı)','manager',$c['manager']??'');self::field('Başlangıç','start',$c['start']??'','date');self::field('Kampanya ID (yeni eşleştirme)','campaign');self::field('MDG public UUID','uuid');echo '<p><label><input type="checkbox" name="acknowledge" value="1"> Bu dönemde aynı Google harcamasının manuel giderlerle çakışmadığını kontrol ettim.</label></p>';self::button('Kaydet');
        foreach(array('test'=>'Salt Okunur API Testi','enable'=>'Testten Sonra Aktarımı Başlat','pause'=>'Aktarımı Duraklat') as $op=>$label){self::form($op,$label);self::button($label);}
        $p=get_transient('mdg_gads_preview');echo '<h2>Son API testi / son otomatik çalışma</h2><pre>'.esc_html(wp_json_encode(array('preview'=>$p,'last_run'=>get_option('mdg_gads_last_run')),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';
        echo '<h2>Program UUID eşleştirmeleri</h2><pre>'.esc_html(wp_json_encode($c['maps']??array(),JSON_PRETTY_PRINT)).'</pre>';
        global $wpdb;$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',self::table('daily')));
        if($exists){$rows=$wpdb->get_results('SELECT campaign_id,MAX(campaign_name) campaign_name,event_uuid,SUM(cost_micros) micros,COUNT(*) days FROM '.self::table('daily').' GROUP BY campaign_id,event_uuid');echo '<h2>Aktarılan kaynak / inceleme havuzu</h2><pre>'.esc_html(wp_json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>';}
        self::form('evidence','Fatura / banka belgesini eşleştir');self::field('Gider ID','expense_id','','number');echo '<select name="kind"><option value="invoice">Google faturası</option><option value="bank">Banka / kart ödemesi</option></select>';self::field('Fatura / dekont referansı','reference');self::field('Belge medya eki ID','attachment_id','','number');self::field('Mevcut ödeme hareketi ID (banka için)','payment_id','','number');echo '<p><label><input type="checkbox" name="verified" value="1"> Belgeyi ve ilgili tutar/dönem/hesap eşleşmesini kontrol ettim.</label></p>';self::button('Yalnız Belge Bağlantısını Kaydet');echo '<p>Fatura bağlantısı yeni gider veya ödeme oluşturmaz. Banka eşleştirmesi var olan ödeme hareketine bağlanır; Google harcamasını ikinci kez maliyete eklemez.</p></div>';
    }
}
MDG_GAds_Finance::boot();
register_activation_hook(__FILE__, array('MDG_GAds_Finance','install'));
register_deactivation_hook(__FILE__, function(){wp_clear_scheduled_hook(MDG_GAds_Finance::HOOK);});
