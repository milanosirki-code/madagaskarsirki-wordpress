<?php
/**
 * Plugin Name: Milano Checkout Intent Contract — Staging Test
 * Description: Isolated admin-only contract tests for signed Milano checkout intents. Does not create orders, holds, payments, or tickets.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Milano_Stage_Intent_Contract {
    const HOST='staging-f321-milanosirki-lgajf.wpcomstaging.com';
    const ORIGIN='https://milanosirki.com';
    const AUD='milano:central-checkout';
    const MAX_AGE=300;
    private static function b64u($s) { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
    private static function ub64($s) {
        if (!is_string($s)||!preg_match('/^[A-Za-z0-9_-]+$/D',$s)) return false;
        return base64_decode(strtr($s,'-_','+/'),true);
    }
    private static function key() {
        // Staging-only internal self-test; a real cross-domain request needs a dedicated
        // server-to-server secret configured out of band, never wp_salt() or user-visible JS.
        return wp_salt('auth');
    }
    private static function sign($claims) {
        $body=self::b64u(wp_json_encode($claims,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        return $body.'.'.self::b64u(hash_hmac('sha256',$body,self::key(),true));
    }
    private static function clean($token,$now) {
        $pieces=explode('.',(string)$token);
        if(count($pieces)!==2||strlen($token)>3200) return new WP_Error('invalid_format','Invalid token.');
        $sig=self::ub64($pieces[1]);
        if(!is_string($sig)||strlen($sig)!==32||!hash_equals(hash_hmac('sha256',$pieces[0],self::key(),true),$sig))
            return new WP_Error('bad_signature','Signature mismatch.');
        $decoded=self::ub64($pieces[0]);
        $c=is_string($decoded)?json_decode($decoded,true):null;
        if(!is_array($c)||count($c)!==14) return new WP_Error('claims','Unexpected claim shape.');
        if(($c['v']??null)!==1||($c['aud']??null)!==self::AUD||($c['brand']??null)!=='milano'
            ||($c['origin']??null)!==self::ORIGIN||($c['event_id']??null)!==24
            ||!in_array($c['session_id']??null,array(150,151),true)
            ||($c['families']??null)!==1||($c['children']??null)!==0||($c['adults']??null)!==0
            ||($c['amount_minor']??null)!==110000||($c['units']??null)!==4
            ||!is_int($c['iat']??null)||!is_int($c['exp']??null)
            ||($c['iat']??0)>$now+30||($c['exp']??0)<=$now
            ||($c['exp']-$c['iat'])>self::MAX_AGE||($c['exp']-$c['iat'])<1
            ||!is_string($c['nonce']??null)||!preg_match('/^[a-f0-9]{32}$/D',$c['nonce'])) {
            return new WP_Error('claims','Unapproved brand, order or expiration claims.');
        }
        return $c;
    }
    private static function fixture($sid,$now,$change=array()) {
        $c=array(
            'v'=>1,'aud'=>self::AUD,'brand'=>'milano','origin'=>self::ORIGIN,
            'event_id'=>24,'session_id'=>$sid,'families'=>1,'children'=>0,'adults'=>0,
            'amount_minor'=>110000,'units'=>4,'iat'=>$now,'exp'=>$now+180,
            'nonce'=>bin2hex(random_bytes(16))
        );
        // A fixture carries 14 keys; signature covers ALL 14 claims.
        return array_merge($c,$change);
    }
    private static function check_site() {
        return parse_url(site_url(),PHP_URL_HOST)===self::HOST && current_user_can('manage_woocommerce');
    }
    public static function boot() {
        add_action('rest_api_init', static function() {
            if(parse_url(site_url(),PHP_URL_HOST)!==self::HOST) return;
            register_rest_route('milano-staging/v1','/checkout-contract-smoke',array(
                'methods'=>'GET',
                'permission_callback'=>static function(){return self::check_site();},
                'callback'=>array(__CLASS__,'smoke')
            ));
        });
    }
    public static function smoke() {
        if(!class_exists('MDG_Events')||!class_exists('MDG_Capacity')) {
            return new WP_Error('dependencies','MDG staging missing.',array('status'=>503));
        }
        $event=MDG_Events::get(24);
        if(!$event||(string)$event->title!=='Milano Sirki — Yalova'||$event->status!=='draft') {
            return new WP_Error('wrong_event','Not the approved staging draft.',array('status'=>409));
        }
        global $wpdb;
        $p=$wpdb->prefix;
        $owners=$wpdb->get_results("SELECT pr.brand FROM {$p}mmc_mdg_event_bridge b
            INNER JOIN {$p}mmc_programs pr ON pr.id=b.program_id
            WHERE b.mdg_event_id=24 AND b.is_active=1 LIMIT 2");
        if(count((array)$owners)!==1||(string)$owners[0]->brand!=='Milano Sirki') {
            return new WP_Error('owner','Not canonical Milano owner.',array('status'=>409));
        }
        $type=$wpdb->get_row("SELECT price,capacity_units FROM {$p}mmc_ticket_types
            WHERE event_id=14 AND ticket_code='family_2_2' AND is_active=1 LIMIT 1");
        if(!$type||(float)$type->price!==1100.0||(int)$type->capacity_units!==4)
            return new WP_Error('family','Unapproved family price.',array('status'=>409));
        $now=time();
        $base=self::fixture(150,$now);
        // Fixed schema validation itself must not allow extra or missing claims.
        $ok=self::clean(self::sign($base),$now);
        $cases=array();
        $cases['valid_intent']=is_array($ok);
        $parts=explode('.',self::sign($base));
        $raw=self::ub64($parts[0]);$tampered=json_decode($raw,true);$tampered['amount_minor']=1;
        $corrupted=self::b64u(wp_json_encode($tampered)).'.'.$parts[1];
        $cases['tamper_blocked']=is_wp_error(self::clean($corrupted,$now));
        $cases['expired_blocked']=is_wp_error(self::clean(self::sign(self::fixture(150,$now-400,array('exp'=>$now-200))),$now));
        $cases['foreign_origin_blocked']=is_wp_error(self::clean(self::sign(self::fixture(150,$now,array('origin'=>'https://madagaskarsirki.com'))),$now));
        $cases['foreign_brand_blocked']=is_wp_error(self::clean(self::sign(self::fixture(150,$now,array('brand'=>'madagaskar'))),$now));
        $cases['foreign_session_blocked']=is_wp_error(self::clean(self::sign(self::fixture(999,$now)),$now));
        $cases['price_changed_blocked']=is_wp_error(self::clean(self::sign(self::fixture(150,$now,array('amount_minor'=>109900))),$now));
        $cases['extra_claim_blocked']=is_wp_error(self::clean(self::sign(self::fixture(150,$now,array('redirect'=>'https://attacker.invalid'))),$now));
        $capacity=(int)MDG_Capacity::available(150);
        return rest_ensure_response(array(
          'test_only'=>true,'all_pass'=>!in_array(false,$cases,true),'cases'=>$cases,
          'mdg_event'=>24,'session'=>150,'capacity_snapshot'=>$capacity,
          'token_exported'=>false,'order_created'=>false,'hold_created'=>false,
          'payment_created'=>false,'ticket_created'=>false,
          'note'=>'Pure signature/claim grammar test; NOT a checkout endpoint or a payment authorization.'
        ));
    }
}
Milano_Stage_Intent_Contract::boot();
