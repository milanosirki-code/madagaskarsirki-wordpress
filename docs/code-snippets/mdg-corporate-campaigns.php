<?php
/** Shared city-scoped campaign catalogue. Preview only: no checkout/ticket writes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'MDG_Corporate_Campaigns_20261005', false ) ) {
    final class MDG_Corporate_Campaigns_20261005 {
        const SHORTCODE = 'mdg_corporate_campaigns';
        const OPTION = 'mdg_corporate_campaign_codes_v1';
        const ADMIN = 'mdg-corporate-campaigns';

        public static function normalize( $raw ) {
            if ( ! is_string( $raw ) || strlen( $raw ) > 120 ) { return ''; }
            $raw = strtr( trim( $raw ), array('İ'=>'i','I'=>'i','ı'=>'i','Ş'=>'s','ş'=>'s','Ğ'=>'g','ğ'=>'g','Ü'=>'u','ü'=>'u','Ö'=>'o','ö'=>'o','Ç'=>'c','ç'=>'c', "\u{0307}"=>'', '–'=>'-', '—'=>'-') );
            $raw = strtolower( $raw );
            $raw = preg_replace( '/[\s_]+/u', '-', $raw );
            return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $raw ) ? $raw : '';
        }

        public static function registry() {
            $rows = get_option( self::OPTION, array() );
            return is_array( $rows ) ? $rows : array();
        }

        public static function resolve( $raw, $events, $registry, $today ) {
            $key = self::normalize( $raw );
            if ( ! $key ) { return null; }
            if ( isset( $registry[ $key ] ) ) {
                $row = $registry[ $key ];
                if ( ! is_array( $row ) || empty( $row['active'] ) || empty( $row['province'] ) ||
                    ( ! empty( $row['end_date'] ) && $row['end_date'] < $today ) ) { return null; }
                return array( 'key'=>$key, 'name'=>(string) ( $row['name'] ?? '' ), 'province'=>(string)$row['province'], 'test_only'=>true );
            }
            // TEST codes are demonstration codes, never real discount authority.
            foreach ( $events as $event ) {
                $province = (string) $event->province_name;
                if ( 'test-' . self::normalize( $province ) === $key ) {
                    return array( 'key'=>$key, 'name'=>'Test Kurumu', 'province'=>$province, 'test_only'=>true );
                }
            }
            return null;
        }

        public static function source_events() {
            if ( ! class_exists( 'MDG_Public_Tickets' ) || ! is_callable( array( 'MDG_Public_Tickets', 'active_events' ) ) ) { return array(); }
            return (array) MDG_Public_Tickets::active_events();
        }

        public static function catalogue( $campaign, $events ) {
            if ( ! $campaign || ! class_exists( 'MDG_Sessions' ) || ! function_exists( 'wc_get_product' ) ) { return array(); }
            $result = array();
            $now = current_time( 'mysql', true );
            foreach ( $events as $event ) {
                if ( 'onsale' !== (string)$event->status || self::normalize( (string)$event->province_name ) !== self::normalize( $campaign['province'] ) ) { continue; }
                $sessions = array();
                foreach ( (array) MDG_Sessions::by_event( $event->id ) as $s ) {
                    if ( 'onsale' !== (string)$s->status || empty( $s->start_at ) || (string)$s->start_at < $now || empty( $s->wc_product_id ) ) { continue; }
                    $parent = wc_get_product( (int)$s->wc_product_id );
                    if ( ! $parent || 'publish' !== $parent->get_status() || ! $parent->is_type( 'variable' ) ) { continue; }
                    $types = array();
                    foreach ( (array) MDG_Sessions::ticket_types_by_session( $s->id ) as $t ) {
                        if ( ! (int)$t->is_active || (int)$t->capacity_units !== 1 ) { continue; }
                        $type = self::normalize( (string)$t->code );
                        $role = in_array( $type, array('adult','yetiskin'), true ) ? 'adult' : (in_array( $type,array('child','cocuk'),true ) ? 'child' : '');
                        if ( ! $role ) { continue; }
                        $v = wc_get_product( (int)$t->wc_variation_id );
                        if ( ! $v || ! $v->is_type('variation') || $v->get_parent_id() !== (int)$s->wc_product_id ||
                            'publish' !== $v->get_status() || ! $v->is_purchasable() || ! $v->is_in_stock() ||
                            (int)$v->get_meta('_mdg_event_id') !== (int)$event->id || (int)$v->get_meta('_mdg_session_id') !== (int)$s->id ) { continue; }
                        $price = $v->get_price();
                        if ( ! is_numeric($price) || ! is_finite((float)$price) || (float)$price <= 0 ) { continue; }
                        $types[$role] = array('variation'=>(int)$t->wc_variation_id,'price'=>(float)$price);
                    }
                    if ( ! isset($types['adult'],$types['child']) ) { continue; }
                    $available = class_exists('MDG_Capacity') ? MDG_Capacity::available((int)$s->id) : null;
                    if ( null !== $available && $available < 1 ) { continue; }
                    list($date,$time) = MDG_Sessions::local_parts($s->start_at);
                    $sessions[(int)$s->id] = array('id'=>(int)$s->id,'date'=>$date,'time'=>$time,'types'=>$types,'available'=>$available);
                }
                if ( $sessions ) { $result[(int)$event->id] = array('event'=>$event,'sessions'=>$sessions); }
            }
            return $result;
        }

        public static function quote( $catalogue, $session, $adults, $children ) {
            foreach ( array($session,$adults,$children) as $v ) {
                if ( ! is_string($v) || ! preg_match('/^[1-9][0-9]*$/D',$v) ) { return array('error'=>'Seans ve kişi sayılarını geçerli tam sayılar olarak seçin.'); }
            }
            $a=(int)$adults; $c=(int)$children;
            if ( $a>2 || $c>4 || $c>2*$a ) { return array('error'=>'Her ücretli yetişkin için en fazla 2 çocuk ücretsizdir. Pilot aile başına en fazla 2 yetişkin içindir.'); }
            foreach ( $catalogue as $row ) {
                if ( ! isset($row['sessions'][(int)$session]) ) { continue; }
                $s=$row['sessions'][(int)$session];
                if ( null!==$s['available'] && $a+$c>$s['available'] ) { return array('error'=>'Seçtiğiniz seansın kalan kapasitesi bu kişi sayısı için yeterli değil.'); }
                return array('adults'=>$a,'children'=>$c,'total'=>round($a*$s['types']['adult']['price'],2),'session'=>$s,'event'=>$row['event']);
            }
            return array('error'=>'Seçilen seans kampanyanıza ait değil veya artık satışa açık değil.');
        }

        private static function input( $source, $key ) {
            $v = $source[$key] ?? '';
            return is_string($v) ? sanitize_text_field(wp_unslash($v)) : '';
        }

        public static function render() {
            $page=get_post();
            if ( !$page || post_password_required($page) ) { return ''; }
            $events=self::source_events();
            $raw=self::input($_GET,'kod');
            $error=''; $quote=null;
            $post='POST'===($_SERVER['REQUEST_METHOD']??'') && isset($_POST['mdg_campaign_action']);
            if ($post) {
                $raw=self::input($_POST,'mdg_campaign_code');
                if ( !wp_verify_nonce(self::input($_POST,'mdg_campaign_nonce'),self::SHORTCODE) ) { $error='Sayfanın süresi doldu. Yenileyip tekrar deneyin.'; $raw=''; }
            }
            $campaign=$raw!=='' ? self::resolve($raw,$events,self::registry(),current_time('Y-m-d')) : null;
            if ( $raw!=='' && !$campaign ) { $error='Kampanya kodu geçersiz, süresi dolmuş veya kullanıma kapatılmış. Lütfen size iletilen kodu kontrol edin.'; }
            $catalogue=self::catalogue($campaign,$events);
            if ($post && $campaign && 'quote'===self::input($_POST,'mdg_campaign_action')) {
                $quote=self::quote($catalogue,self::input($_POST,'mdg_campaign_session'),self::input($_POST,'mdg_campaign_adults'),self::input($_POST,'mdg_campaign_children'));
            }
            ob_start(); ?>
            <section class="mdg-campaigns" aria-label="Kurumsal kampanyalar">
            <style>
            .mdg-campaigns{max-width:900px;margin:24px auto;color:#29221c;font-family:inherit}.mdg-campaigns *{box-sizing:border-box}.mdg-campaigns .mc-intro,.mdg-campaigns .mc-card{padding:clamp(20px,4vw,36px);border:1px solid #e3d2b9;border-radius:16px;background:#fff8ed;margin-bottom:22px}.mdg-campaigns h2{font-size:clamp(26px,5vw,42px);line-height:1.15;margin:8px 0 18px}.mdg-campaigns h3{margin:0 0 12px;color:#8e291e}.mdg-campaigns p{line-height:1.6}.mdg-campaigns .mc-kicker{font-weight:700;color:#9b3027;letter-spacing:.07em}.mdg-campaigns label{display:block;font-weight:600}.mdg-campaigns input,.mdg-campaigns select{display:block;width:100%;padding:12px;margin:8px 0 14px;border:1px solid #9a8870;border-radius:6px;background:#fff;color:#29221c;font:inherit;min-height:48px}.mdg-campaigns button{padding:14px 22px;border:0;border-radius:6px;background:#a93428;color:#fff;font:inherit;font-weight:700;cursor:pointer}.mdg-campaigns .mc-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.mdg-campaigns .mc-error{color:#96251c;font-weight:600}.mdg-campaigns .mc-result{border-top:2px solid #d6b979;padding-top:16px}.mdg-campaigns :focus-visible{outline:3px solid #987023;outline-offset:3px}@media(max-width:540px){.mdg-campaigns .mc-grid{grid-template-columns:1fr}}
            </style>
            <div class="mc-intro">
                <p class="mc-kicker">MADAGASKAR SİRKİ · KURUMSAL KAMPANYALAR</p>
                <h2>Kampanya kodunuzu girin</h2>
                <p>Size iletilen kodla ilinizdeki gösterileri ve seansları görüntüleyin. Kodlarda büyük veya küçük harf kullanabilirsiniz.</p>
                <form method="post" action="<?php echo esc_url(get_permalink($page)); ?>">
                    <?php wp_nonce_field(self::SHORTCODE,'mdg_campaign_nonce',false); ?>
                    <label>Kampanya kodu<input name="mdg_campaign_code" value="<?php echo esc_attr($raw); ?>" placeholder="Size iletilen kampanya kodu" maxlength="120" autocomplete="off" required></label>
                    <button name="mdg_campaign_action" value="open" type="submit">Gösterileri görüntüle</button>
                </form>
                <?php if($error): ?><p class="mc-error" role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
                <p><strong>Deneme aşaması:</strong> Kampanya hesabını inceleyebilirsiniz. Henüz ödeme alınmaz ve QR bilet oluşturulmaz.</p>
            </div>
            <?php if($campaign): ?>
                <h2><?php echo esc_html($campaign['province']); ?> gösterileri</h2>
                <p><?php echo esc_html($campaign['name']); ?> · Her ücretli yetişkinin yanında 3–12 yaş dahil en fazla 2 çocuk ücretsiz. 0–2 yaş zaten ücretsizdir; 13+ yetişkin bileti seçmelidir.</p>
                <?php if(!$catalogue): ?><p class="mc-card">Bu kod için şu anda satışa açık uygun seans bulunmuyor. Yeni gösteriler satışa açıldığında burada otomatik görünecek.</p><?php endif; ?>
                <?php foreach($catalogue as $row): $event=$row['event']; ?>
                <article class="mc-card" data-event-id="<?php echo esc_attr($event->id); ?>">
                    <h3><?php echo esc_html($event->title); ?></h3>
                    <p><strong><?php echo esc_html($event->venue_name); ?></strong><br><?php echo esc_html($event->province_name.' / '.$event->district); ?></p>
                    <form method="post" action="<?php echo esc_url(get_permalink($page)); ?>">
                        <?php wp_nonce_field(self::SHORTCODE,'mdg_campaign_nonce',false); ?>
                        <input type="hidden" name="mdg_campaign_code" value="<?php echo esc_attr($campaign['key']); ?>">
                        <label>Gösteri tarihi ve seans<select name="mdg_campaign_session" required>
                        <?php foreach($row['sessions'] as $s): ?>
                            <option value="<?php echo esc_attr($s['id']); ?>"><?php echo esc_html($s['date'].' · '.$s['time'].' · Yetişkin '.number_format_i18n($s['types']['adult']['price'],2).' TL'); ?></option>
                        <?php endforeach; ?></select></label>
                        <div class="mc-grid"><label>Ücretli yetişkin<select name="mdg_campaign_adults"><option value="1">1 yetişkin</option><option value="2">2 yetişkin</option></select></label>
                        <label>Ücretsiz çocuk (3–12 yaş)<select name="mdg_campaign_children"><option value="1">1 çocuk</option><option value="2" selected>2 çocuk</option><option value="3">3 çocuk</option><option value="4">4 çocuk</option></select></label></div>
                        <button name="mdg_campaign_action" value="quote" type="submit">Kampanya tutarını hesapla</button>
                    </form>
                    <?php if($quote && (isset($quote['error']) || (int)$quote['event']->id===(int)$event->id)): ?>
                        <div class="mc-result" role="status">
                        <?php if(isset($quote['error'])): ?><p class="mc-error"><?php echo esc_html($quote['error']); ?></p>
                        <?php else: ?><p><?php echo esc_html($quote['session']['date'].' · '.$quote['session']['time']); ?><br><?php echo esc_html($quote['adults']); ?> yetişkin + <?php echo esc_html($quote['children']); ?> ücretsiz çocuk.<br><strong>Toplam: <?php echo wp_kses_post(wc_price($quote['total'])); ?></strong></p><p>Bu deneme özeti bilet veya rezervasyon değildir.</p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
            </section>
            <?php return ob_get_clean();
        }

        public static function admin_menu() {
            add_submenu_page('woocommerce','Kurumsal Kampanyalar','Kurumsal Kampanyalar','manage_woocommerce',self::ADMIN,array(__CLASS__,'admin_page'));
        }

        public static function admin_page() {
            if(!current_user_can('manage_woocommerce')){return;}
            $provinces=class_exists('MDG_Venues') ? MDG_Venues::provinces() : array();
            $registry=self::registry();
            ?>
            <div class="wrap"><h1>Kurumsal Kampanyalar</h1>
            <p>Bir kodu bir ile bağlayın. Bilet Al sayfasında bu il için satışa açılan yeni gösteriler kampanya sayfasına otomatik gelir. Bu sürüm yalnız deneme hesabı yapar; gerçek kupon ve QR üretmez.</p>
            <?php if(isset($_GET['saved'])): ?><div class="notice notice-success"><p>Kampanya kodu kaydedildi.</p></div><?php endif; ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="mdg_corporate_campaign_save"><?php wp_nonce_field(self::ADMIN); ?>
            <p><label>Kurum adı <input name="name" required maxlength="120"></label></p>
            <p><label>Kampanya kodu <input name="code" required maxlength="80"></label> Büyük/küçük harf ve Türkçe harf farkı aranmaz. TEST- ile başlayan kodlar denemeler için ayrılmıştır.</p>
            <p><label>İl <select name="province" required><?php foreach($provinces as $name): ?><option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label></p>
            <p><label>Son geçerlilik günü (isteğe bağlı) <input type="date" name="end_date"></label></p>
            <button class="button button-primary" type="submit">Kodu ekle</button></form>
            <h2>Tanımlı kurum kodları</h2><table class="widefat striped"><thead><tr><th>Kod</th><th>Kurum</th><th>İl</th><th>Son gün</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
            <?php foreach($registry as $key=>$row): ?><tr><td><?php echo esc_html($key); ?></td><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html($row['province']); ?></td><td><?php echo esc_html($row['end_date']?:'Süresiz'); ?></td><td><?php echo !empty($row['active'])?'Aktif':'Kapalı'; ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mdg_corporate_campaign_toggle"><input type="hidden" name="code" value="<?php echo esc_attr($key); ?>"><?php wp_nonce_field(self::ADMIN); ?><button class="button" type="submit"><?php echo !empty($row['active'])?'Durdur':'Etkinleştir'; ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table><h2>Otomatik test kodları</h2><p>Bu kodlar gerçek bilet üretmez; yalnız güncel satış programını denemeye yarar.</p><ul>
            <?php $seen=array(); foreach(self::source_events() as $e): $key='test-'.self::normalize((string)$e->province_name); if(isset($seen[$key]))continue; $seen[$key]=true; ?><li><code><?php echo esc_html(strtoupper($key)); ?></code> — <?php echo esc_html($e->province_name); ?></li><?php endforeach; ?>
            </ul></div>
            <?php
        }

        public static function save() {
            if(!current_user_can('manage_woocommerce')){wp_die('Bu işlem için yetkiniz yok.');}
            check_admin_referer(self::ADMIN);
            $key=self::normalize(self::input($_POST,'code'));
            $name=self::input($_POST,'name'); $province=self::input($_POST,'province'); $date=self::input($_POST,'end_date');
            $provinces=class_exists('MDG_Venues') ? MDG_Venues::provinces() : array();
            if(!$key || strpos($key,'test-')===0 || !$name || !in_array($province,$provinces,true)){wp_die('Kurum, kod veya il geçersiz. TEST- kodları ayrılmıştır.');}
            if($date!=='') {
                $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if(!$d || $d->format('Y-m-d')!==$date){wp_die('Son geçerlilik günü geçersiz.');}
            }
            $rows=self::registry();
            if(isset($rows[$key])){wp_die('Bu kod zaten tanımlı. Harf farkı ayrı kod oluşturmaz.');}
            if(count($rows)>=200){wp_die('En fazla 200 kurum kodu tanımlanabilir.');}
            $rows[$key]=array('name'=>$name,'province'=>$province,'end_date'=>$date,'active'=>true);
            update_option(self::OPTION,$rows,false);
            wp_safe_redirect(admin_url('admin.php?page='.self::ADMIN.'&saved=1')); exit;
        }

        public static function toggle() {
            if(!current_user_can('manage_woocommerce')){wp_die('Bu işlem için yetkiniz yok.');}
            check_admin_referer(self::ADMIN);
            $rows=self::registry(); $key=self::normalize(self::input($_POST,'code'));
            if(isset($rows[$key])){ $rows[$key]['active']=empty($rows[$key]['active']); update_option(self::OPTION,$rows,false); }
            wp_safe_redirect(admin_url('admin.php?page='.self::ADMIN)); exit;
        }

        public static function robots($robots) {
            $p=get_post();
            if(is_singular('page') && $p && has_shortcode($p->post_content,self::SHORTCODE)) { unset($robots['index'],$robots['follow']); $robots['noindex']=true; $robots['nofollow']=true; }
            return $robots;
        }

        public static function no_cache() {
            $p=get_post();
            if(is_singular('page') && $p && has_shortcode($p->post_content,self::SHORTCODE)) {
                if(!defined('DONOTCACHEPAGE')){define('DONOTCACHEPAGE',true);}
                nocache_headers();
            }
        }
    }
    add_shortcode(MDG_Corporate_Campaigns_20261005::SHORTCODE,array('MDG_Corporate_Campaigns_20261005','render'));
    add_filter('wp_robots',array('MDG_Corporate_Campaigns_20261005','robots'));
    add_action('template_redirect',array('MDG_Corporate_Campaigns_20261005','no_cache'),1);
    add_action('admin_menu',array('MDG_Corporate_Campaigns_20261005','admin_menu'));
    add_action('admin_post_mdg_corporate_campaign_save',array('MDG_Corporate_Campaigns_20261005','save'));
    add_action('admin_post_mdg_corporate_campaign_toggle',array('MDG_Corporate_Campaigns_20261005','toggle'));
}
