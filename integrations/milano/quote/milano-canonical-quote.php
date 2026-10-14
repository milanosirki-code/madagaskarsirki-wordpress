<?php
/**
 * Plugin Name: Milano Merkezi Tekil Teklif Ön Kontrolü
 * Description: Kanonik MMC–MDG–Woo fiyat ve kapasite ön izlemesi. Satış, sipariş, ödeme, bilet veya stok yazmaz.
 * Version: 0.1.1
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
final class Milano_Quote_Readonly_01 {
    public static function boot() { add_action('rest_api_init',array(__CLASS__,'routes')); }
    public static function routes() {
        register_rest_route('dunya-milano/v2','/quote',array(
            'methods'=>'POST','permission_callback'=>array(__CLASS__,'authorize'),
            'callback'=>array(__CLASS__,'handle_quote')
        ));
        if (parse_url(site_url(),PHP_URL_HOST)==='staging-f321-milanosirki-lgajf.wpcomstaging.com') {
            register_rest_route('dunya-milano/v2','/yalova-preview',array(
                'methods'=>'GET','permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
                'callback'=>array(__CLASS__,'preview')
            ));
            register_rest_route('dunya-milano/v2','/staging-guard-tests',array(
                'methods'=>'GET','permission_callback'=>static function(){return current_user_can('manage_woocommerce');},
                'callback'=>array(__CLASS__,'staging_guards')
            ));
        }
    }
    public static function authorize($request) {
        if (!defined('DUNYA_MILANO_QUOTE_TOKEN') || strlen((string)DUNYA_MILANO_QUOTE_TOKEN)<32) {
            return new WP_Error('not_configured','Server-side authorization unavailable.',array('status'=>503));
        }
        $given=(string)$request->get_header('X-Dunya-Milano-Quote-Key');
        if (!$given||!hash_equals((string)DUNYA_MILANO_QUOTE_TOKEN,$given)) {
            return new WP_Error('forbidden','Forbidden.',array('status'=>403));
        }
        return true;
    }
    private static function error($code,$message,$status=409) {
        return new WP_Error($code,$message,array('status'=>$status));
    }
    private static function bounded($n) {
        if (!is_int($n)&&!(is_string($n)&&preg_match('/^\d{1,2}$/D',$n))) return null;
        return (int)$n>=0&&(int)$n<=20?(int)$n:null;
    }
    public static function handle_quote($request) {
        $body=$request->get_json_params();
        if (!is_array($body)) return self::error('bad_json','JSON object required.',400);
        return self::calculate(absint($body['event_id']??0),absint($body['session_id']??0),
            self::bounded($body['children']??0),self::bounded($body['adults']??0),
            self::bounded($body['families']??0),false);
    }
    public static function preview() {
        $out=array();
        foreach(array(150,151) as $id) {
            $r=self::calculate(24,$id,0,0,1,true);
            if (is_wp_error($r)) return $r;
            $out[]=$r;
        }
        return rest_ensure_response(array('preview_only'=>true,'sales_opened'=>false,'quotes'=>$out));
    }

    public static function staging_guards() {
        $cases=array(
            'madagaskar_must_be_rejected'=>self::calculate(7,60,0,0,1,true),
            'wrong_session_must_be_rejected'=>self::calculate(24,60,0,0,1,true),
            'oversized_family_must_be_rejected'=>self::calculate(24,150,0,0,11,true),
            'zero_quantity_must_be_rejected'=>self::calculate(24,150,0,0,0,true)
        );
        $codes=array();$pass=true;
        foreach($cases as $label=>$res) {
            $codes[$label]=is_wp_error($res)?$res->get_error_code():'unexpected_success';
            if(!is_wp_error($res)){$pass=false;}
        }
        return rest_ensure_response(array(
            'read_only'=>true,'all_fail_closed'=>$pass,'cases'=>$codes,
            'no_orders_or_holds_created'=>true
        ));
    }
    private static function calculate($eid,$sid,$children,$adults,$families,$preview) {
        if (!class_exists('MDG_Events')||!class_exists('MDG_Capacity')
            ||!class_exists('MDG_Status')||!class_exists('MDG_Sessions')
            ||!function_exists('wc_get_product')) return self::error('deps','Central ledger not ready.',503);
        if (!$eid||!$sid||$children===null||$adults===null||$families===null||$families>10
            ||$children+2*$families>20||$adults+2*$families>20) return self::error('qty','Invalid quantities.',400);
        $units=$children+$adults+4*$families;
        if ($units<1||$units>500) return self::error('qty','Invalid capacity units.',400);
        global $wpdb;
        $p=$wpdb->prefix;
        $owner=$wpdb->get_results($wpdb->prepare(
          "SELECT b.id,b.program_id,b.mmc_event_id,p.brand FROM {$p}mmc_mdg_event_bridge b
           INNER JOIN {$p}mmc_programs p ON p.id=b.program_id
           WHERE b.mdg_event_id=%d AND b.is_active=1 LIMIT 2",$eid));
        if ($wpdb->last_error||count((array)$owner)!==1||(string)$owner[0]->brand!=='Milano Sirki')
            return self::error('brand','No unique canonical Milano owner.');
        $event=MDG_Events::get($eid);
        if (!$event||false===stripos((string)$event->title,'Milano')
           ||false!==stripos((string)$event->title,'Madagaskar'))
            return self::error('event','Milano event identity mismatch.');
        if ($preview) {
            if ((string)$event->status!=='draft') return self::error('stage_status','Draft staging required.');
        } else {
            if ((string)$event->status!==MDG_Status::ONSALE) return self::error('sales_closed','Not on sale.');
        }
        $s=$wpdb->get_row($wpdb->prepare(
          "SELECT * FROM {$p}mdg_sessions WHERE id=%d AND event_id=%d LIMIT 1",$sid,$eid));
        if (!$s||(!$preview&&(string)$s->status!==MDG_Status::ONSALE))
            return self::error('session','Session unavailable.');
        $now=current_time('mysql',true);
        if ((string)$s->start_at<=$now||(!$preview&&MDG_Sessions::sales_closed_by_time($s)))
            return self::error('expired','Session expired.');
        if (!$preview) {
            foreach(array($event->sale_start??null,$s->sale_start??null) as $v) {
                if ($v&&(string)$v>$now) return self::error('not_started','Sale window not yet open.');
            }
            foreach(array($event->sale_end??null,$s->sale_end??null) as $v) {
                if ($v&&(string)$v<=$now) return self::error('ended','Sale window ended.');
            }
        }
        $productId=(int)$s->wc_product_id;
        $product=wc_get_product($productId);
        $post=get_post($productId);
        if (!$product||!$product->is_type('variable')||!$post
            ||(int)get_post_meta($productId,'_mdg_event_id',true)!==$eid
            ||(int)get_post_meta($productId,'_mdg_session_id',true)!==$sid
            ||false===stripos((string)$post->post_title,'Milano Sirki')
            ||(!$preview&&$post->post_status!=='publish'))
            return self::error('product','Canonical product invalid.');
        $types=$wpdb->get_results($wpdb->prepare(
           "SELECT id,code,price,capacity_units,wc_variation_id FROM {$p}mdg_ticket_types
            WHERE session_id=%d AND is_active=1 ORDER BY id",$sid));
        if ($wpdb->last_error||count((array)$types)!==2) return self::error('types','Expected two active ticket types.');
        $prices=array();$vars=array();
        foreach($types as $t) {
            $k=strtoupper((string)$t->code);
            $id=(int)$t->wc_variation_id;
            $v=$id?wc_get_product($id):null;
            if (!in_array($k,array('CHILD','ADULT'),true)||isset($prices[$k])
                ||(int)$t->capacity_units!==1||!$v||!$v->is_type('variation')
                ||(int)$v->get_parent_id()!==$productId
                ||(int)get_post_meta($id,'_mdg_event_id',true)!==$eid
                ||(int)get_post_meta($id,'_mdg_session_id',true)!==$sid
                ||abs((float)$v->get_price()-(float)$t->price)>0.009
                ||(!$preview&&(!$v->is_purchasable()||!$v->is_in_stock())))
                return self::error('variation','Variation or price changed.');
            $prices[$k]=(float)$t->price;$vars[$k]=$id;
        }
        if (!isset($prices['CHILD'],$prices['ADULT'])) return self::error('types','Types missing.');
        $familyPrice=0;
        if ($families) {
            $f=$wpdb->get_results($wpdb->prepare(
                "SELECT price,capacity_units FROM {$p}mmc_ticket_types
                 WHERE event_id=%d AND ticket_code='family_2_2' AND is_active=1 LIMIT 2",
                (int)$owner[0]->mmc_event_id));
            $opt=get_option('mdg_family_package_22_v1',array());
            $list=is_array($opt)&&is_array($opt['events']??null)?$opt['events']:array();
            $enabled=isset($list[$eid])||in_array($eid,$list,true)||in_array((string)$eid,$list,true);
            if (count((array)$f)!==1||(int)$f[0]->capacity_units!==4
                ||(float)$f[0]->price<=0||!$enabled)
                return self::error('family','Family package not eligible.');
            $familyPrice=(float)$f[0]->price;
            if ($familyPrice>2*$prices['CHILD']+2*$prices['ADULT'])
                return self::error('family','Family price exceeds components.');
        }
        $available=(int)MDG_Capacity::available($sid);
        if ($available<$units) return self::error('capacity','Insufficient current capacity.');
        $local=MDG_Sessions::local_parts((string)$s->start_at);
        $sum=round($children*$prices['CHILD']+$adults*$prices['ADULT']+$families*$familyPrice,2);
        return array('brand'=>'milano','event_id'=>$eid,'session_id'=>$sid,
            'date'=>$local[0]??'','time'=>$local[1]??'','children'=>$children,
            'adults'=>$adults,'families'=>$families,'units'=>$units,'amount'=>$sum,
            'currency'=>'TRY','capacity_snapshot'=>$available,
            'product_id'=>$productId,'child_variation_id'=>$vars['CHILD'],'adult_variation_id'=>$vars['ADULT'],
            'family_price'=>$families?$familyPrice:null,'preview_only'=>$preview,
            'order_created'=>false,'capacity_reserved'=>false,'ticket_created'=>false);
    }
}
Milano_Quote_Readonly_01::boot();
