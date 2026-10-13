<?php
/**
 * Milano virtual family 2+2 -> REAL child/adult MDG ticket lines.
 * GitHub-only staging contract; NO WordPress hooks, Woo writes, fees,
 * Tickera issuance, PayTR payment, MDG hold, or QR creation.
 *
 * Mirrors the INSTALLED Madagaskar family 1.1.3 architecture, not a
 * fictional WC FAMILY4 variation: 1 family => 2 child + 2 adult real units.
 * Pricing and ownership must come from a fresh trusted central MMC/MDG
 * snapshot; client coupon, prices and role classification are NEVER trusted.
 */
if(!defined('ABSPATH')){exit;}
final class MCPI_Milano_Family22_Normalizer {
    private static function blocked($reason) {
        return array('ok'=>false,'reason'=>$reason);
    }
    private static function positive($value) {
        return is_int($value) && $value > 0;
    }
    private static function code($value) {
        return is_string($value) && (bool)preg_match('/\A[A-Z0-9_-]{1,24}\z/D',$value);
    }
    /**
     * $request = event_id, session_id, family_quantity, normal_lines
     *   normal_lines: [{code,quantity}] only actual child/adult codes.
     * $central = event_id, session_id, owner_brand, order_brand,
     *   family_price_cents, types [{kind,code,price_cents,capacity_units,
     *   ticket_type_id,variation_id,active}].
     * Result is authoritative proposed checkout plan, not a purchase approval.
     */
    public static function normalize($request,$central) {
        if(!is_array($request)||!is_array($central)
           ||!isset($request['event_id'],$request['session_id'],
                   $request['family_quantity'],$request['normal_lines'])
           ||!isset($central['event_id'],$central['session_id'],
                   $central['owner_brand'],$central['order_brand'],
                   $central['family_price_cents'],$central['types'])
           ||!self::positive($request['event_id'])
           ||!self::positive($request['session_id'])
           ||$request['event_id']!==$central['event_id']
           ||$request['session_id']!==$central['session_id']
           ||'milano'!==$central['owner_brand']
           ||'milano'!==$central['order_brand'])return self::blocked('wrong_brand_or_session');
        $families=$request['family_quantity'];
        if(!is_int($families)||$families<1||$families>10
           ||!is_int($central['family_price_cents'])
           ||$central['family_price_cents']<1
           ||$central['family_price_cents']>10000000
           ||!is_array($central['types'])
           ||count($central['types'])<2
           ||count($central['types'])>30
           ||!is_array($request['normal_lines'])
           ||count($request['normal_lines'])>20)
            return self::blocked('invalid_family_request');
        $types=array();
        $codes=array();
        foreach($central['types'] as $type) {
            if(!is_array($type)||!isset($type['kind'],$type['code'],
                    $type['price_cents'],$type['capacity_units'],
                    $type['ticket_type_id'],$type['variation_id'],$type['active'])
               ||!self::code($type['code'])
               ||!self::positive($type['ticket_type_id'])
               ||!self::positive($type['variation_id'])
               ||!is_int($type['capacity_units'])
               ||!is_int($type['price_cents'])
               ||$type['price_cents']<0||$type['price_cents']>10000000
               ||!is_bool($type['active'])
               ||isset($codes[$type['code']]))
                return self::blocked('invalid_catalogue');
            $codes[$type['code']]=true;
            if(!$type['active'])continue;
            // The live family module explicitly rejects any real 4-unit type.
            if(1!==$type['capacity_units'])
                return self::blocked('real_multi_unit_type_forbidden');
            if('child'===$type['kind']||'adult'===$type['kind']) {
                if(isset($types[$type['kind']]))
                    return self::blocked('ambiguous_child_adult_type');
                $types[$type['kind']]=$type;
            }
        }
        if(!isset($types['child'],$types['adult'])
           ||$types['child']['code']===$types['adult']['code'])
            return self::blocked('child_adult_missing');
        $base=2*$types['child']['price_cents']+2*$types['adult']['price_cents'];
        $discount=$base-$central['family_price_cents'];
        if($discount<0)return self::blocked('price_greater_than_components');
        $qty=array('child'=>2*$families,'adult'=>2*$families);
        foreach($request['normal_lines'] as $line) {
            if(!is_array($line)||!isset($line['code'],$line['quantity'])
               ||!self::code($line['code'])
               ||!is_int($line['quantity'])
               ||$line['quantity']<1)
                return self::blocked('invalid_normal_line');
            if($line['code']===$types['child']['code'])$kind='child';
            elseif($line['code']===$types['adult']['code'])$kind='adult';
            else return self::blocked('unknown_or_virtual_family_ticket');
            $qty[$kind]+=$line['quantity'];
            if($qty[$kind]>20)return self::blocked('too_many_per_ticket_type');
        }
        $units=$qty['child']+$qty['adult'];
        if($units>100)return self::blocked('too_many_admissions');
        $full=$qty['child']*$types['child']['price_cents']
             +$qty['adult']*$types['adult']['price_cents'];
        $discount_total=$families*$discount;
        $payable=$full-$discount_total;
        if($payable<1||$payable>100000000)
            return self::blocked('invalid_payment_total');

        $lines=array();
        foreach(array('child','adult') as $kind){
            $t=$types[$kind];
            $lines[]=array('code'=>$t['code'],'quantity'=>$qty[$kind],
                'variation_id'=>$t['variation_id'],
                'ticket_type_id'=>$t['ticket_type_id'],
                'capacity_units_per_ticket_type'=>1,
                'line_admission_units'=>$qty[$kind],
                'unit_price_cents'=>$t['price_cents']);
        }
        return array(
            'ok'=>true,'event_id'=>$central['event_id'],
            'session_id'=>$central['session_id'],
            'brand'=>'milano','family_packages'=>$families,
            'expected_admission_units'=>$units,
            'expected_ticket_instances'=>$units,
            'component_subtotal_cents'=>$full,
            'discount_fee_cents'=>$discount_total,
            'payable_cents'=>$payable,
            'lines'=>$lines
        );
    }
}
