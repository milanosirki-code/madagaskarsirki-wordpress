<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit campaign allocations into the finance screen the operators use. */
final class MDGY_Meta_Expenses {
    const OPTION = 'mdgy_meta_expense_allocations_v1';

    public static function boot() {
        add_action( 'admin_post_mdgy_meta_allocate', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_mdg_v5_delete_expense', array( __CLASS__, 'protect_expense' ), 1 );
        add_action( 'admin_post_mdgy_meta_pause', array( __CLASS__, 'pause' ) );
    }

    public static function allocations( $rows ) {
        $out = array(); $total = 0;
        foreach ( (array) $rows as $r ) {
            $id = absint( $r['event_id'] ?? 0 );
            $value = str_replace( ',', '.', (string) ( $r['percent'] ?? '' ) );
            if ( ! $id && '' === $value ) { continue; }
            if ( ! $id || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value) || ! is_numeric( $value ) || (float) $value <= 0 || (float) $value > 100 || isset( $out[$id] ) ) {
                return new WP_Error( 'allocation', 'Her programı bir kez seçin ve geçerli bir yüzde girin.' );
            }
            $basis = (int) round( (float) $value * 100 );
            $out[$id] = $basis; $total += $basis;
        }
        if ( 10000 !== $total ) { return new WP_Error( 'allocation', 'Program paylarının toplamı %100 olmalıdır.' ); }
        return $out;
    }

    public static function split( $amount, $allocations ) {
        $cents = (int) round( $amount * 100 ); $remaining = $cents; $out = array();
        $last = array_key_last( $allocations );
        foreach ( $allocations as $id => $basis ) {
            $share = $id === $last ? $remaining : (int) floor( $cents * $basis / 10000 );
            $remaining -= $share; $out[$id] = $share / 100;
        }
        return $out;
    }

    private static function date_ok( $date ) {
        $d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    public static function save() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdgy_meta_allocate' );
        global $wpdb;
        $account = sanitize_text_field( wp_unslash( $_POST['account'] ?? '' ) );
        $campaign = sanitize_text_field( wp_unslash( $_POST['campaign'] ?? '' ) );
        $start = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
        if ( empty( $_POST['acknowledge'] ) ) { wp_die( 'Manuel kayıt dönemiyle çakışmadığını doğrulayın.' ); }
        $allocations = self::allocations( wp_unslash( $_POST['allocation'] ?? array() ) );
        if ( is_wp_error( $allocations ) ) { wp_die( esc_html( $allocations->get_error_message() ) ); }
        if ( ! self::date_ok( $start ) || ! class_exists( 'MDG_DB' ) ) { wp_die( 'Geçerli başlangıç tarihi ve bilet modülü gerekli.' ); }
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mdgy_meta_daily WHERE account_id=%s AND campaign_id=%s AND level_name='campaign'", $account, $campaign ) );
        if ( ! $exists ) { wp_die( 'Kampanya senkronize edilmiş veride bulunamadı.' ); }
        foreach ( $allocations as $id => $basis ) {
            if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MDG_DB::table('events') . ' WHERE id=%d', $id ) ) ) { wp_die( 'Program bulunamadı.' ); }
        }
        // Changing an existing allocation could silently move historical costs: refuse it.
        $key = hash( 'sha256', $account . '|' . $campaign );
        $map_lock = substr(hash('sha256',$wpdb->prefix.self::OPTION.'maps'),0,60);
        if ('1' !== (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$map_lock))) { wp_die('Eşleştirme kaydı meşgul; yeniden deneyin.'); }
        $maps = get_option( self::OPTION, array() );
        $map = array( 'account'=>$account, 'campaign'=>$campaign, 'start'=>$start, 'allocations'=>$allocations );
        if ( isset( $maps[$key] ) && array_diff_key($maps[$key],array('paused'=>true)) !== $map ) { wp_die( 'Bu kampanya zaten eşleştirildi. Geçmiş giderler için kontrollü mutabakat gerekir.' ); }
        $maps[$key] = $map; update_option( self::OPTION, $maps, false );
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$map_lock));
        $result = self::sync();
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        wp_safe_redirect( admin_url( 'admin.php?page=mdgy-marketing&meta_expenses_saved=1' ) ); exit;
    }

    public static function sync() {
        global $wpdb;
        if ( ! class_exists( 'MDG_V5_Finance' ) || ! class_exists( 'MDG_DB' ) ) { return new WP_Error( 'dependency', 'V5 Finans ve bilet modülü gerekli.' ); }
        $maps = get_option( self::OPTION, array() );
        if ( ! $maps ) { return 0; }
        $lock = substr( hash( 'sha256', $wpdb->prefix . self::OPTION ), 0, 60 );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) { return new WP_Error( 'busy', 'Reklam gider senkronizasyonu zaten çalışıyor.' ); }
        $count = 0; $skipped = 0;
        try {
            foreach ( $maps as $map ) {
                if ( ! empty($map['paused']) ) { continue; }
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mdgy_meta_daily WHERE account_id=%s AND campaign_id=%s AND level_name='campaign' AND metric_date>=%s ORDER BY metric_date", $map['account'], $map['campaign'], $map['start'] ) );
                foreach ( $rows as $r ) {
                    $raw = json_decode( $r->raw_json, true );
                    if ( 'TRY' !== ( $raw['account_currency'] ?? '' ) ) { $skipped++; continue; }
                    if ( ! self::date_ok( $r->metric_date ) || ! is_numeric( $r->spend ) || (float)$r->spend < 0 ) { continue; }
                    foreach ( self::split( (float)$r->spend, $map['allocations'] ) as $id => $amount ) {
                        $event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table('events') . ' WHERE id=%d', $id ) );
                        if ( ! $event ) { return new WP_Error( 'event_missing', 'Eşleştirilmiş program bulunamadı; gider aktarımı durduruldu.' ); }
                        $ref = 'META-' . hash( 'sha256', $map['account'].'|'.$map['campaign'].'|'.$r->metric_date.'|'.$id );
                        $table = $wpdb->prefix . 'mdg_v5_expenses';
                        $old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE document_no=%s AND vendor='Meta Ads API' LIMIT 1", $ref ) );
                        $now = current_time('mysql');
                        $data = array( 'event_id'=>$id, 'scope'=>'program', 'province'=>$event->province_name ?? '', 'category'=>'Reklam / Sosyal Medya', 'description'=>'Meta: '.$r->campaign_name, 'amount'=>$amount, 'expense_date'=>$r->metric_date, 'notes'=>'Otomatik Meta harcaması; ödeme veya fatura teyidi değildir. Kampanya: '.$map['campaign'], 'updated_at'=>$now );
                        if ( $old ) { $ok = $wpdb->update( $table, $data, array('id'=>$old->id) ); }
                        elseif ( $amount > 0 ) { $ok = $wpdb->insert( $table, array_merge( $data, array('document_no'=>$ref,'vendor'=>'Meta Ads API','payment_method'=>'Ödenmedi','created_by'=>0,'created_at'=>$now) ) ); }
                        else { continue; }
                        if ( false === $ok ) { return new WP_Error( 'write', 'Reklam gideri kaydedilemedi; senkronizasyonu yeniden deneyin.' ); }
                        $count++;
                    }
                }
            }
        } finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
        if ( $skipped ) { return new WP_Error('currency', $skipped.' günlük kaydın TRY para birimi doğrulanamadı; bu kayıtlar giderlere aktarılmadı. Meta senkronizasyonunu yeniden çalıştırın.'); }
        return $count;
    }

    public static function protect_expense() {
        if ( ! current_user_can('manage_woocommerce') ) { return; }
        global $wpdb;
        $id = absint($_POST['expense_id'] ?? 0);
        check_admin_referer('mdg_v5_delete_expense_'.$id,'mdg_v5_finance_nonce');
        $row = $wpdb->get_row($wpdb->prepare("SELECT vendor,document_no FROM {$wpdb->prefix}mdg_v5_expenses WHERE id=%d",$id));
        if ($row && 'Meta Ads API' === $row->vendor && 0 === strpos($row->document_no,'META-')) {
            wp_die('Bu gider Meta tarafından otomatik güncellenir. Aktarımı durdurmak için Pazarlama ekranında kampanyayı duraklatın. Geçmiş giderler korunur.');
        }
    }

    public static function pause() {
        if (!current_user_can('manage_woocommerce')) { wp_die('Yetkiniz yok.'); }
        check_admin_referer('mdgy_meta_pause');
        global $wpdb;
        $lock=substr(hash('sha256',$wpdb->prefix.self::OPTION.'maps'),0,60);
        if ('1' !== (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))) { wp_die('Eşleştirme meşgul; yeniden deneyin.'); }
        try {
            $key=sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
            $maps=get_option(self::OPTION,array());
            if(isset($maps[$key])) { $maps[$key]['paused']=empty($maps[$key]['paused']); update_option(self::OPTION,$maps,false); }
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
        wp_safe_redirect(admin_url('admin.php?page=mdgy-marketing')); exit;
    }

    public static function render() {
        global $wpdb;
        echo '<div class="mdgy-panel"><h2>Otomatik Reklam Giderleri / Program Eşleştirme</h2><p>Başlangıç tarihinden sonraki Meta harcamaları Gider ve Kârlılık ekranına günlük yazılır. Daha önce manuel kaydettiğiniz son günün ertesi gününü seçin. Ajans ve tasarım ücretlerini manuel girmeye devam edin. Ödeme ayrıca takip edilir.</p>';
        if ( ! class_exists('MDG_DB') || ! class_exists('MDG_V5_Finance') ) { echo '<p>V5 Finans ve bilet modülü gerekli.</p></div>'; return; }
        $events = $wpdb->get_results( 'SELECT * FROM '.MDG_DB::table('events').' ORDER BY id DESC' );
        $maps = get_option( self::OPTION, array() );
        $campaigns = $wpdb->get_results( "SELECT account_id,campaign_id,MAX(campaign_name) campaign_name,SUM(spend) spend,MIN(metric_date) first_date FROM {$wpdb->prefix}mdgy_meta_daily WHERE level_name='campaign' GROUP BY account_id,campaign_id ORDER BY spend DESC" );
        foreach ( $campaigns as $r ) {
            $key = hash('sha256',$r->account_id.'|'.$r->campaign_id);
            echo '<h3>'.esc_html($r->campaign_name.' — '.$r->account_id).'</h3>';
            if ( isset($maps[$key]) ) {
                echo '<p>Eşleştirildi. Başlangıç: '.esc_html($maps[$key]['start']).'</p><ul>';
                foreach($maps[$key]['allocations'] as $id=>$basis){foreach($events as $event){if((int)$event->id===$id)echo '<li>'.esc_html(($event->title ?? ('Program #'.$id)).' — %'.($basis/100)).'</li>';}}
                echo '</ul><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdgy_meta_pause"><input type="hidden" name="key" value="'.esc_attr($key).'">';
                wp_nonce_field('mdgy_meta_pause');
                echo '<button class="button">'.(!empty($maps[$key]['paused'])?'Otomatik gideri devam ettir':'Otomatik gideri duraklat').'</button></form>'; continue;
            }
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mdgy_meta_allocate"><input type="hidden" name="account" value="'.esc_attr($r->account_id).'"><input type="hidden" name="campaign" value="'.esc_attr($r->campaign_id).'">';
            wp_nonce_field('mdgy_meta_allocate');
            echo '<p><label>Otomatik gider başlangıcı <input required type="date" name="start" value="'.esc_attr(wp_date('Y-m-d',time()+DAY_IN_SECONDS)).'"></label></p>';
            for($i=0;$i<4;$i++){
                echo '<p><select name="allocation['.$i.'][event_id]"><option value="">Program seçin</option>';
                foreach($events as $e)echo '<option value="'.absint($e->id).'">'.esc_html(($e->province_name ?? '').' / '.($e->district ?? '').' — '.($e->event_date ?? '').' — '.($e->title ?? '').' #'.$e->id).'</option>';
                echo '</select> <label>Pay % <input type="number" min="0.01" max="100" step="0.01" name="allocation['.$i.'][percent]" value="'.($i===0?'100':'').'"></label></p>';
            }
            echo '<p><label><input required type="checkbox" name="acknowledge" value="1"> Seçtiğim tarihten itibaren bu Meta harcamasını ayrıca manuel gider olarak girmeyeceğim.</label></p><button class="button button-primary">Eşleştir ve Otomatik Gideri Başlat</button></form>';
        }
        if(!$campaigns)echo '<p>Önce Meta Ads Senkronize Et düğmesine basın.</p>';
        echo '<p>Para birimi TRY olarak doğrulanmayan eski kayıtlar aktarılmaz; Meta senkronizasyonuyla yeniden okunmaları gerekir. Eşleştirilmemiş kampanyalar gider yazmaz.</p></div>';
    }
}
