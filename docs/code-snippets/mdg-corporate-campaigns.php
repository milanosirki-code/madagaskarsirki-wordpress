<?php
/** Shared city-scoped campaigns; scoped Woo cart pricing, existing paid ticket pipeline. */
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

        public static function adapt($row) {
            if(!is_array($row)){return array();}
            if(!isset($row['cities']) || !is_array($row['cities'])){
                $row['cities']=array();
                if(!empty($row['province'])){
                    $p=is_array($row['pricing']??null)?array_intersect_key($row['pricing'],array('adult_campaign'=>1,'child_campaign'=>1)):array();
                    $row['cities'][self::normalize($row['province'])]=array('province'=>$row['province'],'active'=>true,'end_date'=>$row['end_date']??'','pricing'=>$p,'legacy'=>true);
                    $row['end_date']='';
                }
            }
            unset($row['province'],$row['pricing']);
            $row['schema_version']=2;
            return $row;
        }
        public static function registry() {
            $rows=get_option(self::OPTION,array());
            if(!is_array($rows)){return array();}
            foreach($rows as $key=>$row){$rows[$key]=self::adapt($row);}
            return $rows;
        }

        public static function resolve($raw,$events,$registry,$today) {
            $key=self::normalize($raw);
            if(!$key){return null;}
            if(isset($registry[$key])){
                $row=self::adapt($registry[$key]);
                if(empty($row['active']) || (!empty($row['end_date']) && $row['end_date']<$today)){return null;}
                $cities=array();
                foreach($row['cities'] as $city){
                    if(!is_array($city) || empty($city['active']) || empty($city['province']) || (!empty($city['end_date']) && $city['end_date']<$today)){continue;}
                    $cities[self::normalize($city['province'])]=$city;
                }
                if(!$cities){return null;}
                return array('key'=>$key,'name'=>(string)($row['name']??''),'province'=>implode(' / ',array_column($cities,'province')),'cities'=>$cities,'test_only'=>false);
            }
            foreach($events as $event){
                $province=(string)$event->province_name;
                if('test-'.self::normalize($province)===$key){return array('key'=>$key,'name'=>'Test Kurumu','province'=>$province,'test_only'=>true,'pricing'=>array());}
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
                if('onsale' !== (string)$event->status){continue;}
                $city_key=self::normalize((string)$event->province_name);
                if(!empty($campaign['test_only'])){
                    if($city_key!==self::normalize($campaign['province'])){continue;}
                    $city=array('pricing'=>array());
                }else{
                    if(!isset($campaign['cities'][$city_key])){continue;}
                    $city=$campaign['cities'][$city_key];
                }
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
                        $regular=(float)$price;
                        $effective=(float)$price;
                        if ( empty($campaign['test_only']) ) {
                            $pricing=is_array($city['pricing']??null)?$city['pricing']:array();
                            $campaign_key=$role.'_campaign';
                            if(isset($pricing[$campaign_key]) && is_numeric($pricing[$campaign_key]) && (float)$pricing[$campaign_key]>0){
                                // Kampanya hiçbir zaman canlı normal fiyattan daha pahalı olamaz.
                                $effective=min($regular,(float)$pricing[$campaign_key]);
                            } elseif(!empty($city['legacy']) && $role==='adult' && (int)$event->id===12 && self::normalize((string)$event->province_name)==='denizli'){
                                // Mevcut Denizli kurumsal kampanyalarının eski davranışını koru.
                                $effective=min($regular,475.0);
                            }
                        }
                        $types[$role] = array('variation'=>(int)$t->wc_variation_id,'price'=>$effective,'regular_price'=>$regular,'parent'=>(int)$s->wc_product_id,'type_id'=>(int)($t->id??0));
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

        public static function ages_from_birthdates($birthdates,$show_date,$today) {
            if(!is_array($birthdates) || count($birthdates)>20 || array_keys($birthdates)!==array_keys(array_values($birthdates))) { return array('error'=>'Doğum tarihleri geçersiz.'); }
            $show=DateTimeImmutable::createFromFormat('!Y-m-d',$show_date);
            if(!$show || $show->format('Y-m-d')!==$show_date){return array('error'=>'Gösteri tarihi doğrulanamadı.');}
            $ages=array();
            foreach($birthdates as $birth){
                if(!is_string($birth) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birth)){return array('error'=>'Her çocuğun doğum tarihini eksiksiz girin.');}
                $d=DateTimeImmutable::createFromFormat('!Y-m-d',$birth);
                if(!$d || $d->format('Y-m-d')!==$birth || $birth>$today || $birth>$show_date){return array('error'=>'Doğum tarihi geçersiz veya gelecekte olamaz.');}
                $age=$d->diff($show)->y;
                if($age>120){return array('error'=>'Doğum tarihi geçerli yaş sınırının dışında.');}
                $ages[]=(string)$age;
            }
            return $ages;
        }

        public static function quote_birthdates($catalogue,$session,$adults,$birthdates,$today) {
            if(!is_string($session) || !preg_match('/^[1-9][0-9]*$/D',$session)){return array('error'=>'Seans seçimi geçersiz.');}
            foreach($catalogue as $row){
                if(!isset($row['sessions'][(int)$session]))continue;
                $ages=self::ages_from_birthdates($birthdates,$row['sessions'][(int)$session]['date'],$today);
                if(isset($ages['error']))return $ages;
                return self::quote($catalogue,$session,$adults,$ages);
            }
            return array('error'=>'Seçilen seans kampanyanıza ait değil veya artık satışa açık değil.');
        }

        public static function quote( $catalogue, $session, $adults, $ages ) {
            foreach ( array($session,$adults) as $v ) {
                if ( ! is_string($v) || ! preg_match('/^[1-9][0-9]*$/D',$v) ) { return array('error'=>'Seans ve kişi sayılarını geçerli tam sayılar olarak seçin.'); }
            }
            $a=(int)$adults;
            if ( $a>10 || !is_array($ages) || count($ages)>20 || array_keys($ages)!==array_keys(array_values($ages)) ) { return array('error'=>'Kişi sayısı veya çocuk yaşları geçersiz.'); }
            $infants=0; $eligible=0; $older=0; $clean=array();
            foreach($ages as $age) {
                if(!is_string($age) || !preg_match('/^(?:0|[1-9][0-9]{0,2})$/D',$age) || (int)$age>120) { return array('error'=>'Her çocuğun yaşını 0 ile 120 arasında tam sayı olarak girin.'); }
                $age=(int)$age; $clean[]=$age;
                if($age<=2){$infants++;}elseif($age<=12){$eligible++;}else{$older++;}
            }
            $adult_tickets=$a+$older;
            $free=min($eligible,2*$adult_tickets);
            $paid=$eligible-$free;
            foreach ( $catalogue as $row ) {
                if ( ! isset($row['sessions'][(int)$session]) ) { continue; }
                $s=$row['sessions'][(int)$session];
                if ( null!==$s['available'] && $a+count($ages)>$s['available'] ) { return array('error'=>'Seçtiğiniz seansın kalan kapasitesi bu kişi sayısı için yeterli değil.'); }
                $details=array(); $remaining=$free;
                foreach($clean as $age){
                    if($age<=2){$kind='infant';$price=0;}
                    elseif($age>=13){$kind='adult';$price=$s['types']['adult']['price'];}
                    elseif($remaining>0){$kind='free_child';$price=0;$remaining--;}
                    else{$kind='paid_child';$price=$s['types']['child']['price'];}
                    $details[]=array('age'=>$age,'kind'=>$kind,'price'=>$price);
                }
                return array('adults'=>$a,'adult_tickets'=>$adult_tickets,'older'=>$older,'children'=>count($ages),'infants'=>$infants,'free_children'=>$free,'paid_children'=>$paid,'free_allowance'=>2*$adult_tickets,'details'=>$details,'normal_total'=>round($adult_tickets*($s['types']['adult']['regular_price']??$s['types']['adult']['price'])+$eligible*($s['types']['child']['regular_price']??$s['types']['child']['price']),2),'total'=>round($adult_tickets*$s['types']['adult']['price']+$paid*$s['types']['child']['price'],2),'session'=>$s,'event'=>$row['event']);
            }
            return array('error'=>'Seçilen seans kampanyanıza ait değil veya artık satışa açık değil.');
        }

        public static function event_intro($event) {
            $short=trim((string)($event->short_description??''));
            $long=trim((string)($event->long_description??''));
            return array(
                'hero'=>max(0,(int)($event->hero_attachment_id??0)),
                'short'=>$short,
                'about'=>$long!==''?wp_trim_words(wp_strip_all_tags(strip_shortcodes($long)),65,'…'):'Uluslararası sanatçılarla hazırlanan, tamamen hayvansız, ailelere uygun canlı sirk deneyimi.'
            );
        }

        private static function input( $source, $key ) {
            $v = $source[$key] ?? '';
            return is_string($v) ? sanitize_text_field(wp_unslash($v)) : '';
        }

        private static function price_input( $source, $key, $required=true ) {
            $v=self::input($source,$key);
            if($v===''){return $required?null:'';}
            $v=str_replace(',','.',$v);
            if(!preg_match('/^(?:[0-9]+)(?:\.[0-9]{1,2})?$/D',$v)){return null;}
            $n=round((float)$v,2);
            return $n>0?$n:null;
        }

        private static function validate_pricing($source) {
            $pricing=array(
                'adult_campaign'=>self::price_input($source,'adult_campaign'),
                'child_campaign'=>self::price_input($source,'child_campaign'),
            );
            foreach($pricing as $v){if(null===$v){return array('error'=>'İndirimli fiyatları 0’dan büyük, en fazla iki ondalıklı sayı olarak girin.');}}
            return $pricing;
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
                $count=self::input($_POST,'mdg_campaign_children');
                $births=$_POST['mdg_campaign_birthdates']??array();
                if(!preg_match('/^(?:0|[1-9][0-9]*)$/D',$count) || !is_array($births) || count($births)!==(int)$count) { $quote=array('error'=>'Seçtiğiniz çocuk sayısı kadar doğum tarihi girin.'); }
                else { $quote=self::quote_birthdates($catalogue,self::input($_POST,'mdg_campaign_session'),self::input($_POST,'mdg_campaign_adults'),$births,current_time('Y-m-d')); }
            }
            ob_start(); ?>
            <section class="mdg-campaigns" aria-label="Kurumsal kampanyalar">
            <style>
            .mdg-campaigns{max-width:900px;margin:24px auto;color:#29221c;font-family:inherit}.mdg-campaigns *{box-sizing:border-box}.mdg-campaigns .mc-intro,.mdg-campaigns .mc-card{padding:clamp(20px,4vw,36px);border:1px solid #e3d2b9;border-radius:16px;background:#fff8ed;margin-bottom:22px}.mdg-campaigns h2{font-size:clamp(26px,5vw,42px);line-height:1.15;margin:8px 0 18px}.mdg-campaigns .mc-poster{margin:0 auto 24px;max-width:560px}.mdg-campaigns .mc-poster-image{display:block;width:100%;height:auto;border-radius:10px}.mdg-campaigns .mc-about{padding:16px;background:#fff;border-radius:10px;margin-bottom:20px}.mdg-campaigns .mc-about h4{margin:0 0 8px;font-size:1.1em}.mdg-campaigns h3{margin:0 0 12px;color:#8e291e}.mdg-campaigns p{line-height:1.6}.mdg-campaigns .mc-kicker{font-weight:700;color:#9b3027;letter-spacing:.07em}.mdg-campaigns label{display:block;font-weight:600}.mdg-campaigns input,.mdg-campaigns select{display:block;width:100%;padding:12px;margin:8px 0 14px;border:1px solid #9a8870;border-radius:6px;background:#fff;color:#29221c;font:inherit;min-height:48px}.mdg-campaigns button{padding:14px 22px;border:0;border-radius:6px;background:#a93428;color:#fff;font:inherit;font-weight:700;cursor:pointer}.mdg-campaigns .mc-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.mdg-campaigns .mc-error{color:#96251c;font-weight:600}.mdg-campaigns .mc-result{border-top:2px solid #d6b979;padding-top:16px}.mdg-campaigns :focus-visible{outline:3px solid #987023;outline-offset:3px}@media(max-width:540px){.mdg-campaigns .mc-grid{grid-template-columns:1fr}}
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
                <p>Biletler başarılı ödeme sonrasında oluşturulur. TEST- kodları yalnız hesaplama içindir.</p>
            </div>
            <?php if($campaign): ?>
                <h2><?php echo esc_html($campaign['province']); ?> gösterileri</h2>
                <p><?php echo esc_html($campaign['name']); ?> · Her ücretli yetişkin bileti için 3–12 yaş dahil iki ücretsiz çocuk hakkı vardır. Bu sayıyı aşan çocuklar tanımlı kampanya çocuk fiyatıyla; kampanya fiyatı yoksa güncel normal çocuk fiyatıyla hesaplanır. 0–2 yaş ücretsizdir ve kampanya hakkını kullanmaz; 13 yaş ve üzeri yetişkin fiyatıyla hesaplanır.</p>
                <?php if(isset($quote['error'])): ?><p class="mc-error" role="alert"><?php echo esc_html($quote['error']); ?></p><?php endif; ?>
                <?php if(!$catalogue): ?><p class="mc-card">Bu kod için şu anda satışa açık uygun seans bulunmuyor. Yeni gösteriler satışa açıldığında burada otomatik görünecek.</p><?php endif; ?>
                <?php foreach($catalogue as $row): $event=$row['event']; ?>
                <article class="mc-card" data-event-id="<?php echo esc_attr($event->id); ?>">
                    <?php $intro=self::event_intro($event); if($intro['hero']): ?>
                    <figure class="mc-poster"><?php echo wp_get_attachment_image($intro['hero'],'large',false,array('class'=>'mc-poster-image','loading'=>'lazy','alt'=>(string)$event->title.' gösteri afişi')); ?></figure>
                    <?php endif; ?>
                    <h3><?php echo esc_html($event->title); ?></h3>
                    <?php if($intro['short']!==''): ?><p class="mc-event-intro"><?php echo esc_html($intro['short']); ?></p><?php endif; ?>
                    <div class="mc-about"><h4>Gösteride sizi neler bekliyor?</h4><p><?php echo esc_html($intro['about']); ?></p></div>
                    <p><strong><?php echo esc_html($event->venue_name); ?></strong><br><?php echo esc_html($event->province_name.' / '.$event->district); ?></p>
                    <?php $first=reset($row['sessions']); $adult_price=$first['types']['adult']; if($adult_price['price']<$adult_price['regular_price']): ?>
                    <p><strong>1 yetişkin + 2 çocuk (3–12 yaş):</strong> <del><?php echo wp_kses_post(wc_price($adult_price['regular_price']+2*$first['types']['child']['regular_price'])); ?></del> <strong><?php echo wp_kses_post(wc_price($adult_price['price'])); ?></strong>. Normal biletlerin ayrı ayrı toplamı ile karşılaştırılmıştır.</p>
                    <?php endif; ?>
                    <?php
                    $chosen=$post && self::input($_POST,'mdg_campaign_event')===(string)$event->id;
                    $adult_input=$chosen?self::input($_POST,'mdg_campaign_adults'):'1';
                    $child_input=$chosen?self::input($_POST,'mdg_campaign_children'):'2';
                    $child_count=preg_match('/^(?:0|[1-9][0-9]*)$/D',$child_input)?min(20,(int)$child_input):2;
                    $age_inputs=$chosen && isset($_POST['mdg_campaign_birthdates']) && is_array($_POST['mdg_campaign_birthdates'])?array_values($_POST['mdg_campaign_birthdates']):array();
                    ?>
                    <form method="post" data-age-quote data-today="<?php echo esc_attr(current_time('Y-m-d')); ?>" action="<?php echo esc_url(get_permalink($page)); ?>">
                        <?php wp_nonce_field(self::SHORTCODE,'mdg_campaign_nonce',false); ?>
                        <input type="hidden" name="mdg_campaign_code" value="<?php echo esc_attr($campaign['key']); ?>">
                        <input type="hidden" name="mdg_campaign_event" value="<?php echo esc_attr($event->id); ?>">
                        <label>Gösteri tarihi ve seans<select name="mdg_campaign_session" required>
                        <?php foreach($row['sessions'] as $s): ?>
                            <option value="<?php echo esc_attr($s['id']); ?>" data-show-date="<?php echo esc_attr($s['date']); ?>" data-adult-price="<?php echo esc_attr($s['types']['adult']['price']); ?>" data-child-price="<?php echo esc_attr($s['types']['child']['price']); ?>" data-normal-adult-price="<?php echo esc_attr($s['types']['adult']['regular_price']); ?>" data-normal-child-price="<?php echo esc_attr($s['types']['child']['regular_price']); ?>" <?php echo $chosen && self::input($_POST,'mdg_campaign_session')===(string)$s['id']?'selected':''; ?>><?php echo esc_html($s['date'].' · '.$s['time'].' · Yetişkin '.number_format_i18n($s['types']['adult']['price'],2).' TL · Çocuk '.number_format_i18n($s['types']['child']['price'],2).' TL'); ?></option>
                        <?php endforeach; ?></select></label>
                        <div class="mc-grid"><label>Yetişkin sayısı (13 yaş ve üzeri)<select name="mdg_campaign_adults"><?php for($i=1;$i<=10;$i++): ?><option value="<?php echo esc_attr($i); ?>" <?php echo $adult_input===(string)$i?'selected':''; ?>><?php echo esc_html($i); ?> yetişkin</option><?php endfor; ?></select></label>
                        <label>Birlikte gelen çocuk sayısı<select name="mdg_campaign_children"><?php for($i=0;$i<=20;$i++): ?><option value="<?php echo esc_attr($i); ?>" <?php echo $child_count===$i?'selected':''; ?>><?php echo esc_html($i); ?> çocuk</option><?php endfor; ?></select></label></div>
                        <p>Yaş, seçtiğiniz gösterinin tarihine göre otomatik hesaplanır. 13 yaş ve üzerini yetişkin sayısına ekleyebilir veya doğum tarihini aşağıda girebilirsiniz; aynı kişiyi iki kez eklemeyin.</p>
                        <div class="mc-grid" data-child-ages>
                        <?php for($i=0;$i<$child_count;$i++): $age=isset($age_inputs[$i]) && is_string($age_inputs[$i])?$age_inputs[$i]:''; ?>
                        <label><?php echo esc_html($i+1); ?>. çocuğun doğum tarihi<input type="date" max="<?php echo esc_attr(current_time('Y-m-d')); ?>" name="mdg_campaign_birthdates[]" value="<?php echo esc_attr($age); ?>" required data-child-birthdate><span data-age-status></span></label>
                        <?php endfor; ?></div>
                        <p data-live-quote role="status" aria-live="polite">Doğum tarihlerini girdiğinizde yaşlar ve bilet tutarı otomatik hesaplanır.</p>
                        <button data-count-fallback type="submit" name="mdg_campaign_action" value="ages" formnovalidate>Çocuk sayısına göre doğum tarihi alanlarını güncelle</button>
                        <button name="mdg_campaign_action" value="quote" type="submit">Kampanya tutarını hesapla</button>
                    </form>
                    <?php if($quote && !isset($quote['error']) && (int)$quote['event']->id===(int)$event->id): ?>
                        <div class="mc-result" role="status">
                        <p><?php echo esc_html($quote['session']['date'].' · '.$quote['session']['time']); ?><br><?php echo esc_html($quote['adult_tickets']); ?> ücretli yetişkin bileti<?php echo $quote['older']?' (yaşı 13 ve üzeri olan '.esc_html($quote['older']).' kişi dahil)':''; ?><br><?php echo esc_html($quote['free_children']); ?> ücretsiz çocuk bileti · <?php echo esc_html($quote['paid_children']); ?> ücretli çocuk bileti · <?php echo esc_html($quote['infants']); ?> kişi 0–2 yaş ücretsiz.<br><?php if($quote['normal_total']>$quote['total']): ?>Normal bilet toplamı: <del><?php echo wp_kses_post(wc_price($quote['normal_total'])); ?></del><br><?php endif; ?><strong>Kampanyalı toplam: <?php echo wp_kses_post(wc_price($quote['total'])); ?></strong></p>
                        <?php foreach($quote['details'] as $i=>$detail): ?><p><?php echo esc_html(($i+1).'. çocuk · '.$detail['age'].' yaş · '.array('infant'=>'0–2 yaş ücretsiz','adult'=>'Yetişkin bileti','free_child'=>'Kampanyadan ücretsiz','paid_child'=>'Normal çocuk bileti')[$detail['kind']]); ?> · <?php echo wp_kses_post(wc_price($detail['price'])); ?></p><?php endforeach; ?>
                        <p>Bu özet rezervasyon değildir. Ücretsiz çocuklar yetişkinleriyle birlikte giriş yapar.</p>
                        <?php if(!$campaign['test_only']): ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="mdg_campaign_checkout">
                        <?php wp_nonce_field(self::SHORTCODE,'mdg_campaign_nonce',false); ?>
                        <?php foreach(array('mdg_campaign_code','mdg_campaign_session','mdg_campaign_adults','mdg_campaign_children') as $field): ?>
                        <input type="hidden" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr(self::input($_POST,$field)); ?>">
                        <?php endforeach; foreach((array)($_POST['mdg_campaign_birthdates']??array()) as $birth): ?>
                        <input type="hidden" name="mdg_campaign_birthdates[]" value="<?php echo esc_attr($birth); ?>">
                        <?php endforeach; ?>
                        <button type="submit">Ödemeye devam et</button></form>
                        <?php else: ?><p>Test koduyla ödeme veya bilet oluşturulmaz.</p><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
            </section>
            <?php return ob_get_clean();
        }

        // Footer scripts bypass the_content character conversion (e.g. && -> &#038;).
        public static function script() {
            $p=get_post();
            if(!is_singular('page') || !$p || !has_shortcode($p->post_content,self::SHORTCODE)){return;}
            ?>
            <script>
            (function(){
                const money=n=>new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY'}).format(n);
                document.querySelectorAll('.mdg-campaigns form[data-age-quote]').forEach(form=>{
                    if(form.dataset.ageEnhanced)return;form.dataset.ageEnhanced='1';
                    const adults=form.querySelector('[name="mdg_campaign_adults"]'),count=form.querySelector('[name="mdg_campaign_children"]'),session=form.querySelector('[name="mdg_campaign_session"]'),box=form.querySelector('[data-child-ages]'),summary=form.querySelector('[data-live-quote]');
                    const update=()=>{
                        const option=session.selectedOptions[0],ap=Number(option.dataset.adultPrice),cp=Number(option.dataset.childPrice),nap=Number(option.dataset.normalAdultPrice??ap),ncp=Number(option.dataset.normalChildPrice??cp);
                        const ageOf=raw=>{
                            if(!/^\d{4}-\d{2}-\d{2}$/.test(raw)||raw>form.dataset.today||raw>option.dataset.showDate)return null;
                            const [y,m,d]=raw.split('-').map(Number),date=new Date(Date.UTC(y,m-1,d));
                            if(date.getUTCFullYear()!==y||date.getUTCMonth()!==m-1||date.getUTCDate()!==d)return null;
                            const [sy,sm,sd]=option.dataset.showDate.split('-').map(Number);
                            const age=sy-y-((sm<m||(sm===m&&sd<d))?1:0);
                            return age>=0&&age<=120?age:null;
                        };
                        const inputs=Array.from(box.querySelectorAll('[data-child-birthdate]'));
                        const computed=inputs.map(i=>ageOf(i.value));
                        const valid=computed.every(a=>a!==null);
                        const older=computed.filter(a=>a!==null&&a>=13).length;
                        const paidAdults=Number(adults.value)+older,allowance=paidAdults*2;let left=allowance,free=0,paid=0,infant=0;
                        inputs.forEach((input,index)=>{
                            const status=input.parentElement.querySelector('[data-age-status]');
                            const age=computed[index];
                            if(age===null){status.textContent='Geçerli doğum tarihi girin';return;}
                            if(age<=2){infant++;status.textContent='0–2 yaş ücretsiz';}
                            else if(age>=13){status.textContent='Yetişkin bileti · '+money(ap);}
                            else if(left>0){left--;free++;status.textContent='Kampanyadan ücretsiz';}
                            else{paid++;status.textContent='Ücretli çocuk bileti · '+money(cp);}
                            status.textContent=age+' yaş · '+status.textContent;
                        });
                        summary.textContent=valid?paidAdults+' ücretli yetişkin bileti · '+free+' ücretsiz çocuk · '+paid+' ücretli çocuk · '+infant+' kişi 0–2 yaş ücretsiz. ':'Ücretsiz çocuk hakkı: '+allowance+'. Tam tutar için bütün çocukların doğum tarihini girin.';
                        if(valid){
                            const normal=paidAdults*nap+(free+paid)*ncp,total=paidAdults*ap+paid*cp;
                            if(normal>total){const previous=document.createElement('del');previous.textContent=money(normal);summary.append(document.createTextNode('Normal bilet toplamı: '),previous,document.createElement('br'));}
                            const current=document.createElement('strong');current.textContent='Kampanyalı toplam: '+money(total);summary.append(current);
                        }
                    };
                    const resize=()=>{
                        const n=Number(count.value);
                        while(box.children.length>n)box.lastElementChild.remove();
                        while(box.children.length<n){const label=document.createElement('label');label.append(document.createTextNode((box.children.length+1)+'. çocuğun doğum tarihi'));const input=document.createElement('input');input.type='date';input.max=form.dataset.today;input.required=true;input.name='mdg_campaign_birthdates[]';input.dataset.childBirthdate='';label.append(input);const status=document.createElement('span');status.dataset.ageStatus='';label.append(status);box.append(label);}
                        update();
                    };
                    count.addEventListener('change',resize);count.addEventListener('input',resize);adults.addEventListener('change',update);session.addEventListener('change',update);box.addEventListener('input',update);window.addEventListener('pageshow',resize);resize();
                    const fallback=form.querySelector('[data-count-fallback]');if(fallback)fallback.hidden=true;
                });
            })();
            </script>
            <?php
        }

        public static function sign($manifest) {
            return hash_hmac('sha256',wp_json_encode($manifest),wp_salt('auth'));
        }

        public static function signed($item) {
            $m=$item['mdg_campaign_manifest']??null;
            return is_array($m) && is_string($item['mdg_campaign_signature']??null) && hash_equals(self::sign($m),$item['mdg_campaign_signature']);
        }

        public static function plan($quote) {
            if(isset($quote['error'])){return array();}
            $s=$quote['session'];
            return array(
                'adult'=>array('qty'=>$quote['adult_tickets'],'type'=>$s['types']['adult'],'free'=>false),
                'paid_child'=>array('qty'=>$quote['paid_children'],'type'=>$s['types']['child'],'free'=>false),
                'free_child'=>array('qty'=>$quote['free_children'],'type'=>$s['types']['child'],'free'=>true),
                'infant'=>array('qty'=>$quote['infants'],'type'=>$s['types']['child'],'free'=>true)
            );
        }

        public static function manifest_quote($m) {
            if(!is_array($m) || !isset($m['code'],$m['session'],$m['adults'],$m['births'])){return array('error'=>'Kampanya seçimi doğrulanamadı.');}
            $events=self::source_events();
            $campaign=self::resolve($m['code'],$events,self::registry(),current_time('Y-m-d'));
            if(!$campaign || $campaign['test_only']){return array('error'=>'Kampanya kodu satış için geçerli değil.');}
            return self::quote_birthdates(self::catalogue($campaign,$events),$m['session'],$m['adults'],$m['births'],current_time('Y-m-d'));
        }

        public static function checkout() {
            check_admin_referer(self::SHORTCODE,'mdg_campaign_nonce');
            if(!function_exists('WC') || !class_exists('MDG_Live_Sales')){wp_die('Bilet satış sistemi hazır değil.');}
            $births=$_POST['mdg_campaign_birthdates']??array();
            $count=self::input($_POST,'mdg_campaign_children');
            if(!is_array($births) || !preg_match('/^(?:0|[1-9][0-9]*)$/D',$count) || (int)$count!==count($births)){wp_die('Çocuk sayısı ve doğum tarihleri uyuşmuyor.');}
            $m=array('code'=>self::normalize(self::input($_POST,'mdg_campaign_code')),'session'=>self::input($_POST,'mdg_campaign_session'),'adults'=>self::input($_POST,'mdg_campaign_adults'),'births'=>$births);
            $q=self::manifest_quote($m);
            if(isset($q['error'])){wp_die(esc_html($q['error']));}
            $plan=self::plan($q);
            // Verify existing Tickera mapping; never invent a product or ticket event.
            $parent=wc_get_product($q['session']['types']['adult']['parent']);
            $event_id=(int)$parent->get_meta('_event_name');
            if('yes'!==$parent->get_meta('_tc_is_ticket') || !$event_id || 'tc_events'!==get_post_type($event_id) || (int)get_post_meta($event_id,'_mdg_event_id',true)!==(int)$q['event']->id){wp_die('QR bilet bağlantısı doğrulanamadı.');}
            if(function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)){wc_load_cart();}
            if(!WC()->cart){wp_die('Sepet başlatılamadı.');}
            $cart=WC()->cart; $old=array(); $added=array();
            // Replace only this flow's previous selection. Preserve ordinary tickets.
            foreach($cart->get_cart() as $key=>$item){if(isset($item['mdg_campaign_manifest'])){$old[$key]=$item;$cart->remove_cart_item($key);}}
            try {
                foreach($plan as $role=>$row){
                    if(!$row['qty'])continue;
                    $t=$row['type'];
                    $attrs=wc_get_product_variation_attributes($t['variation']);
                    if(!$attrs){throw new Exception('Bilet seçeneği okunamadı.');}
                    $key=$cart->add_to_cart($t['parent'],$row['qty'],$t['variation'],$attrs,array(
                        'mdg_campaign_manifest'=>$m,'mdg_campaign_signature'=>self::sign($m),'mdg_campaign_role'=>$role,
                        MDG_Live_Sales::CART_FLAG=>1,'mdg_event_id'=>(int)$q['event']->id,'mdg_session_id'=>(int)$m['session'],
                        'mdg_ticket_type_id'=>$t['type_id'],'mdg_capacity_units'=>1
                    ));
                    if(!$key){throw new Exception('Biletler sepete eklenemedi.');}
                    $added[]=$key;
                }
                $cart->calculate_totals(); $cart->set_session();
            } catch(Throwable $e) {
                foreach($added as $key){$cart->remove_cart_item($key);}
                foreach($old as $key=>$item){$cart->restore_cart_item($key);}
                $cart->calculate_totals(); $cart->set_session();
                wp_die(esc_html($e->getMessage()));
            }
            wp_safe_redirect(wc_get_checkout_url()); exit;
        }

        public static function cart_state($cart) {
            $groups=array();
            foreach($cart->get_cart() as $key=>$item){
                if(!isset($item['mdg_campaign_manifest']))continue;
                if(!self::signed($item)){return array('error'=>'Kampanya seçimi doğrulanamadı. Yeniden seçin.');}
                $signature=$item['mdg_campaign_signature'];
                if(!isset($groups[$signature])){$groups[$signature]=array('manifest'=>$item['mdg_campaign_manifest'],'items'=>array());}
                $groups[$signature]['items'][$key]=$item;
            }
            if(count($groups)>1){return array('error'=>'Kampanya seçimini kampanya sayfasından yeniden yapın.');}
            $prices=array();
            foreach($groups as $group){
                $q=self::manifest_quote($group['manifest']);
                if(isset($q['error']))return $q;
                $plan=self::plan($q); $counts=array_fill_keys(array_keys($plan),0);
                foreach($group['items'] as $key=>$item){
                    $role=$item['mdg_campaign_role']??'';
                    if(!isset($plan[$role]) || (int)$item['variation_id']!==$plan[$role]['type']['variation'] || (int)$item['product_id']!==$plan[$role]['type']['parent'] || (int)$item['quantity']<1){return array('error'=>'Kampanya biletleri değişmiş. Kampanya sayfasından yeniden seçin.');}
                    $counts[$role]+=(int)$item['quantity'];
                    $prices[$key]=$plan[$role]['free']?0:$plan[$role]['type']['price'];
                }
                foreach($plan as $role=>$row){if($counts[$role]!==$row['qty'])return array('error'=>'Kişi sayısı değişmiş. Ücretsiz çocuk haklarını kampanya sayfasından yeniden hesaplayın.');}
            }
            return array('prices'=>$prices);
        }

        public static function prices($cart) {
            // Restore catalog price first, so an invalid zero-price selection never remains free.
            foreach($cart->get_cart() as $key=>$item){if(isset($item['mdg_campaign_manifest'])){$p=wc_get_product($item['variation_id']);if($p){$cart->cart_contents[$key]['data']=clone $p;}}}
            $state=self::cart_state($cart);
            if(isset($state['error']))return;
            foreach($state['prices'] as $key=>$price){$cart->cart_contents[$key]['data']->set_price($price);}
        }

        public static function validate_cart() {
            if(!function_exists('WC') || !WC()->cart)return;
            $state=self::cart_state(WC()->cart);
            if(isset($state['error'])){wc_add_notice($state['error'],'error');}
        }

        public static function validate_order($order) {
            if(!function_exists('WC') || !WC()->cart)return;
            $state=self::cart_state(WC()->cart);
            if(isset($state['error']))throw new Exception($state['error']);
        }

        public static function item_meta($item,$cart_key,$values,$order) {
            if(!isset($values['mdg_campaign_manifest']) || !self::signed($values))return;
            $m=$values['mdg_campaign_manifest'];
            $item->add_meta_data('_mdg_campaign_code',$m['code'],true);
            $item->add_meta_data('_mdg_campaign_group',$values['mdg_campaign_signature'],true);
            $item->add_meta_data('_mdg_campaign_role',$values['mdg_campaign_role'],true);
            $item->add_meta_data('Kampanya',self::role_label($values['mdg_campaign_role']),true);
            if(in_array($values['mdg_campaign_role'],array('free_child','infant'),true)){$item->add_meta_data('Giriş koşulu','Aynı siparişteki ücretli yetişkin ile birlikte giriş',true);}
            // Raw birthdates remain in the Woo session only, never in order or ticket metadata.
        }

        public static function role_label($role) {
            return array('adult'=>'Ücretli yetişkin','paid_child'=>'Ücretli çocuk','free_child'=>'Ücretsiz çocuk davetiyesi','infant'=>'0–2 yaş ücretsiz')[$role]??'';
        }

        public static function item_display($data,$item) {
            if(isset($item['mdg_campaign_manifest'])){$data[]=array('key'=>'Kampanya','value'=>self::role_label($item['mdg_campaign_role']??''));}
            return $data;
        }

        public static function coupon_product($valid,$product,$coupon,$item) {
            return isset($item['mdg_campaign_manifest'])?false:$valid;
        }

        public static function admin_menu() {
            add_submenu_page('mmc-dashboard','Okul / Kurumsal Kampanyalar','Okul Kampanyaları','manage_woocommerce',self::ADMIN,array(__CLASS__,'admin_page'));
        }

        public static function admin_form($key,$op) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo '<input type="hidden" name="action" value="mdg_corporate_campaign_update"><input type="hidden" name="code" value="'.esc_attr($key).'"><input type="hidden" name="op" value="'.esc_attr($op).'">';
            wp_nonce_field(self::ADMIN);
        }
        public static function city_fields($provinces,$city=array(),$fixed=false) {
            $p=$city['pricing']??array(); ?>
            <p><label>İl <select name="province" required <?php echo $fixed?'disabled':''; ?>><?php foreach($provinces as $name): ?><option <?php selected($city['province']??'',$name); ?> value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option><?php endforeach; ?></select></label><?php if($fixed): ?><input type="hidden" name="province" value="<?php echo esc_attr($city['province']); ?>"><?php endif; ?></p>
            <p>Normal fiyat: <strong>Programdan otomatik</strong></p>
            <p><label>Yetişkin indirimli fiyat <input type="number" name="adult_campaign" min="0.01" step="0.01" required value="<?php echo esc_attr($p['adult_campaign']??''); ?>"></label> TL</p>
            <p><label>Çocuk indirimli fiyat <input type="number" name="child_campaign" min="0.01" step="0.01" required value="<?php echo esc_attr($p['child_campaign']??''); ?>"></label> TL</p>
            <p><label>İl son geçerlilik tarihi <input type="date" name="end_date" value="<?php echo esc_attr($city['end_date']??''); ?>"></label></p>
            <?php
        }
        public static function admin_page() {
            if(!current_user_can('manage_woocommerce')){return;}
            $provinces=class_exists('MDG_Venues')?MDG_Venues::provinces():array();
            $registry=self::registry(); $key=self::normalize(self::input($_GET,'edit')); $editing=$registry[$key]??null; ?>
            <div class="wrap"><h1>Okul / Kurumsal Kampanyalar</h1>
            <p>Bir kampanya koduna birden fazla il bağlayabilirsiniz. Normal fiyat ilgili program / seans / WooCommerce biletinden canlı alınır. Yalnız indirimli fiyatları girin. Mevcut çocuk ve yaş kuralları korunur.</p>
            <?php if(isset($_GET['saved'])||isset($_GET['updated'])): ?><div class="notice notice-success"><p>Kampanya kaydedildi.</p></div><?php endif; ?>
            <?php if($editing): ?>
            <h2><?php echo esc_html(strtoupper($key)); ?> — Kampanya bilgileri</h2>
            <?php self::admin_form($key,'info'); ?>
            <p><label>Kampanya adı <input name="name" class="regular-text" required maxlength="120" value="<?php echo esc_attr($editing['name']??''); ?>"></label></p>
            <p>Kampanya kodu: <strong><?php echo esc_html(strtoupper($key)); ?></strong></p>
            <p><label><input type="checkbox" name="active" value="1" <?php checked(!empty($editing['active'])); ?>> Aktif</label></p>
            <p><label>Genel son geçerlilik tarihi <input type="date" name="end_date" value="<?php echo esc_attr($editing['end_date']??''); ?>"></label> Genel tarih ve il tarihi birlikte uygulanır.</p>
            <button class="button button-primary">Kampanya bilgilerini kaydet</button></form>
            <h2>Kampanyaya bağlı iller</h2>
            <table class="widefat striped"><thead><tr><th>İl</th><th>Normal fiyat</th><th>Yetişkin indirimli fiyat</th><th>Çocuk indirimli fiyat</th><th>Son geçerlilik tarihi</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
            <?php foreach($editing['cities'] as $ck=>$city): $p=$city['pricing']??array(); ?>
            <tr><td><?php echo esc_html($city['province']); ?></td><td>Programdan otomatik</td><td><?php echo isset($p['adult_campaign'])?esc_html(number_format_i18n($p['adult_campaign'],2).' TL'):(!empty($city['legacy'])?'Mevcut fiyat kuralı korunuyor':'Tanımlanmadı'); ?></td><td><?php echo isset($p['child_campaign'])?esc_html(number_format_i18n($p['child_campaign'],2).' TL'):(!empty($city['legacy'])?'Canlı normal çocuk fiyatı':'Tanımlanmadı'); ?></td><td><?php echo esc_html($city['end_date']?:'Süresiz'); ?></td><td><?php echo !empty($city['active'])?'Aktif':'Pasif'; ?></td><td>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page='.self::ADMIN.'&edit='.rawurlencode($key).'&city='.rawurlencode($ck))); ?>">Düzenle</a>
            <?php self::admin_form($key,'city_toggle'); ?><input type="hidden" name="province" value="<?php echo esc_attr($city['province']); ?>"><button class="button"><?php echo !empty($city['active'])?'Pasife Al':'Etkinleştir'; ?></button></form></td></tr>
            <?php endforeach; ?></tbody></table>
            <?php $ck=self::normalize(self::input($_GET,'city')); if(isset($editing['cities'][$ck])): ?>
            <h2>İl düzenle — <?php echo esc_html($editing['cities'][$ck]['province']); ?></h2>
            <?php self::admin_form($key,'city_edit'); self::city_fields($provinces,$editing['cities'][$ck],true); ?><button class="button button-primary">İl fiyatlarını kaydet</button></form>
            <?php endif; ?>
            <details id="mdg-add-city"><summary class="button" style="margin:20px 0">+ İl Ekle</summary>
            <?php self::admin_form($key,'city_add'); self::city_fields($provinces); ?><p>Fiyatlar girilmeden yeni il kaydedilmez. Diğer illerin fiyatları değişmez.</p><button class="button button-primary">İli ekle</button></form></details>
            <?php else: ?>
            <h2>Yeni kampanya oluştur</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="mdg_corporate_campaign_save"><?php wp_nonce_field(self::ADMIN); ?>
            <p><label>Kampanya adı <input name="name" required maxlength="120"></label></p><p><label>Kampanya kodu <input name="code" required maxlength="80" placeholder="Örn. demo-okul-c"></label></p>
            <?php self::city_fields($provinces); ?><button class="button button-primary">Kampanyayı oluştur</button></form>
            <?php endif; ?>
            <h2>Tanımlı kampanyalar</h2><table class="widefat striped"><thead><tr><th>Kod</th><th>Kampanya</th><th>İller</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
            <?php foreach($registry as $k=>$row): ?><tr><td><?php echo esc_html(strtoupper($k)); ?></td><td><?php echo esc_html($row['name']??''); ?></td><td><?php echo esc_html(implode(' / ',array_column($row['cities'],'province'))); ?></td><td><?php echo !empty($row['active'])?'Aktif':'Pasif'; ?></td><td><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page='.self::ADMIN.'&edit='.rawurlencode($k))); ?>">Düzenle</a></td></tr><?php endforeach; ?></tbody></table>
            <h2>Otomatik test kodları</h2><p>TEST- kodları yalnız hesaplama içindir; ödeme veya bilet oluşturmaz.</p></div>
            <?php
        }

        public static function save() {
            if(!current_user_can('manage_woocommerce')){wp_die('Bu işlem için yetkiniz yok.');}
            check_admin_referer(self::ADMIN);
            $key=self::normalize(self::input($_POST,'code'));
            $name=self::input($_POST,'name'); $province=self::input($_POST,'province'); $date=self::input($_POST,'end_date');
            $pricing=self::validate_pricing($_POST);
            if(isset($pricing['error'])){wp_die(esc_html($pricing['error']));}
            $provinces=class_exists('MDG_Venues') ? MDG_Venues::provinces() : array();
            if(!$key || strpos($key,'test-')===0 || !$name || !in_array($province,$provinces,true)){wp_die('Kurum, kod veya il geçersiz. TEST- kodları ayrılmıştır.');}
            if($date!=='') {
                $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if(!$d || $d->format('Y-m-d')!==$date){wp_die('Son geçerlilik günü geçersiz.');}
            }
            $rows=self::registry();
            if(isset($rows[$key])){wp_die('Bu kod zaten tanımlı. Mevcut kodun Düzenle butonunu kullanın.');}
            if(count($rows)>=200){wp_die('En fazla 200 kurum kodu tanımlanabilir.');}
            $rows[$key]=array('name'=>$name,'end_date'=>'','active'=>true,'schema_version'=>2,'cities'=>array(self::normalize($province)=>array('province'=>$province,'end_date'=>$date,'active'=>true,'pricing'=>$pricing,'legacy'=>false)));
            update_option(self::OPTION,$rows,false);
            wp_safe_redirect(admin_url('admin.php?page='.self::ADMIN.'&saved=1')); exit;
        }

        private static function valid_date($date) {
            if($date===''){return true;}
            $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            return $d && $d->format('Y-m-d')===$date;
        }
        public static function update() {
            if(!current_user_can('manage_woocommerce')){wp_die('Bu işlem için yetkiniz yok.');}
            check_admin_referer(self::ADMIN);
            $key=self::normalize(self::input($_POST,'code')); $rows=self::registry();
            if(!$key || !isset($rows[$key])){wp_die('Kampanya kodu bulunamadı.');}
            $op=self::input($_POST,'op');
            if($op==='info'){
                $name=self::input($_POST,'name'); $date=self::input($_POST,'end_date');
                if(!$name || !self::valid_date($date)){wp_die('Kampanya adı veya tarih geçersiz.');}
                $rows[$key]['name']=$name; $rows[$key]['end_date']=$date; $rows[$key]['active']=self::input($_POST,'active')==='1';
            }else{
                $province=self::input($_POST,'province'); $ck=self::normalize($province);
                $provinces=class_exists('MDG_Venues')?MDG_Venues::provinces():array();
                if(!in_array($province,$provinces,true)){wp_die('İl geçersiz.');}
                if($op==='city_toggle'){
                    if(!isset($rows[$key]['cities'][$ck])){wp_die('İl bulunamadı.');}
                    $rows[$key]['cities'][$ck]['active']=empty($rows[$key]['cities'][$ck]['active']);
                }elseif($op==='city_add' || $op==='city_edit'){
                    $exists=isset($rows[$key]['cities'][$ck]);
                    if(($op==='city_add' && $exists)||($op==='city_edit' && !$exists)){wp_die('İl zaten bağlı veya düzenlenecek il bulunamadı.');}
                    $pricing=self::validate_pricing($_POST); $date=self::input($_POST,'end_date');
                    if(isset($pricing['error'])){wp_die(esc_html($pricing['error']));}
                    if(!self::valid_date($date)){wp_die('Tarih geçersiz.');}
                    $city=$exists?$rows[$key]['cities'][$ck]:array('province'=>$province,'active'=>true);
                    $city['pricing']=$pricing; $city['end_date']=$date; $city['legacy']=false;
                    $rows[$key]['cities'][$ck]=$city;
                }else{wp_die('İşlem geçersiz.');}
            }
            update_option(self::OPTION,$rows,false);
            wp_safe_redirect(admin_url('admin.php?page='.self::ADMIN.'&updated=1&edit='.rawurlencode($key))); exit;
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
            $path=trim((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
            if('kurumsal-davetiye-pilot'===$path){
                $target=get_permalink(4520);
                $code=self::input($_GET,'kod');
                if($code!==''){$target=add_query_arg('kod',$code,$target);}
                wp_safe_redirect($target,'POST'===($_SERVER['REQUEST_METHOD']??'')?307:301);exit;
            }
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
    add_action('admin_menu',array('MDG_Corporate_Campaigns_20261005','admin_menu'),99);
    add_action('wp_footer',array('MDG_Corporate_Campaigns_20261005','script'),30);
    add_action('admin_post_mdg_corporate_campaign_save',array('MDG_Corporate_Campaigns_20261005','save'));
    add_action('admin_post_mdg_corporate_campaign_update',array('MDG_Corporate_Campaigns_20261005','update'));
    add_action('admin_post_mdg_corporate_campaign_toggle',array('MDG_Corporate_Campaigns_20261005','toggle'));
    add_action('admin_post_mdg_campaign_checkout',array('MDG_Corporate_Campaigns_20261005','checkout'));
    add_action('admin_post_nopriv_mdg_campaign_checkout',array('MDG_Corporate_Campaigns_20261005','checkout'));
    add_action('woocommerce_before_calculate_totals',array('MDG_Corporate_Campaigns_20261005','prices'),1000);
    add_action('woocommerce_check_cart_items',array('MDG_Corporate_Campaigns_20261005','validate_cart'));
    add_action('woocommerce_checkout_create_order',array('MDG_Corporate_Campaigns_20261005','validate_order'),1);
    add_action('woocommerce_store_api_checkout_order_processed',array('MDG_Corporate_Campaigns_20261005','validate_order'),1);
    add_action('woocommerce_checkout_create_order_line_item',array('MDG_Corporate_Campaigns_20261005','item_meta'),10,4);
    add_filter('woocommerce_get_item_data',array('MDG_Corporate_Campaigns_20261005','item_display'),10,2);
    add_filter('woocommerce_coupon_is_valid_for_product',array('MDG_Corporate_Campaigns_20261005','coupon_product'),10,4);
}
