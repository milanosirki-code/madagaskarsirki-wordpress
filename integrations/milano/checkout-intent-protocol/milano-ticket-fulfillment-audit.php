<?php
/**
 * Independent Milano admission-to-Tickera reconciliation.
 *
 * Staging-only / side-effect-free. No hooks, QR/PII, DB reads, payment writes,
 * ticket creation, seat release or check-in. Inputs MUST be constructed from
 * trustworthy central MDG/Woo snapshots, not request or callback fields.
 *
 * Important: a WC family item quantity=1 can reserve 4 admission units.
 * Ticket count must equal SUM(quantity * capacity_units_per_ticket_type),
 * not SUM(Woo product quantity). Brand & session are verified using trusted
 * order/item mapping; Tickera event_id is compared per ticket instance.
 */
if(!defined('ABSPATH')){exit;}
final class MCPI_Tickera_Fulfillment_Audit {
    private static function reject($reason){
        return array('ok'=>false,'reason'=>$reason);
    }
    private static function pid($value){
        return is_int($value)&&$value>0;
    }
    private static function line_key($value){
        return is_string($value) && (bool)preg_match('/\A[A-Z0-9_-]{1,50}\z/D',$value);
    }
    public static function check($quote,$woo,$tickets) {
        if(!is_array($quote)||!is_array($woo)||!is_array($tickets)
            ||!isset($quote['order_id'],$quote['source_brand'],$quote['owner_brand'],
                     $quote['session_id'],$quote['event_id'],$quote['payment_final'],
                     $quote['mdg_committed'],$quote['lines'])
            ||!self::pid($quote['order_id'])
            ||!self::pid($quote['session_id'])||!self::pid($quote['event_id'])
            ||'milano'!==$quote['source_brand']
            ||'milano'!==$quote['owner_brand']
            ||true!==$quote['payment_final']
            ||true!==$quote['mdg_committed']
            ||!is_array($quote['lines'])||!$quote['lines'])
            return self::reject('untrusted_order_or_unsettled');
        $expected=array();$order_items=array();$all_items=array();$total=0;

        foreach($quote['lines'] as $line){
            if(!is_array($line)||!isset($line['code'],$line['quantity'],
                 $line['capacity_units_per_ticket_type'],$line['line_admission_units'],
                 $line['variation_id'],$line['ticket_type_id'])
                ||!self::line_key($line['code'])
                ||!self::pid($line['variation_id'])
                ||!self::pid($line['ticket_type_id'])
                ||!is_int($line['quantity'])||$line['quantity']<1
                ||!is_int($line['capacity_units_per_ticket_type'])
                ||$line['capacity_units_per_ticket_type']<1
                ||$line['capacity_units_per_ticket_type']>50
                ||!is_int($line['line_admission_units'])
                ||isset($expected[$line['code']]))return self::reject('invalid_or_duplicate_quote_line');
            $units=$line['quantity']*$line['capacity_units_per_ticket_type'];
            if($units>100||$units!==$line['line_admission_units'])
                return self::reject('quote_admission_math');
            $total+=$units;
            if($total>100)return self::reject('too_many_admissions');
            $expected[$line['code']]=array(
                'units'=>$units,'quantity'=>$line['quantity'],
                'variation'=>$line['variation_id'],
                'ticket_type'=>$line['ticket_type_id'],'issued'=>0
            );
        }

        if(!$woo||count($woo)!==count($expected))return self::reject('woo_line_set_mismatch');
        foreach($woo as $item) {
            if(!is_array($item)||!isset($item['code'],$item['order_id'],
                $item['order_item_id'],$item['quantity'],$item['variation_id'],
                $item['mdg_session_id'])||!self::line_key($item['code'])
                ||!isset($expected[$item['code']])
                ||!self::pid($item['order_item_id'])
                ||$item['order_id']!==$quote['order_id']
                ||$item['mdg_session_id']!==$quote['session_id']
                ||isset($order_items[$item['code']])||isset($all_items[$item['order_item_id']])
                ||$item['quantity']!==$expected[$item['code']]['quantity']
                ||$item['variation_id']!==$expected[$item['code']]['variation'])
                return self::reject('wrong_or_duplicate_woo_line');
            $order_items[$item['code']]=$item['order_item_id'];
            $all_items[$item['order_item_id']]=$item['code'];
        }

        if(count($tickets)!==$total)
            return array('ok'=>false,'reason'=>'admission_ticket_count_mismatch',
                         'expected'=>$total,'found'=>count($tickets));
        $ids=array();$hashes=array();
        foreach($tickets as $ticket){
            if(!is_array($ticket)||!isset($ticket['id'],$ticket['code_hash'],
                     $ticket['order_id'],$ticket['order_item_id'],$ticket['event_id'],
                     $ticket['ticket_type_id'],$ticket['status'])
                ||!self::pid($ticket['id'])
                ||!is_string($ticket['code_hash'])
                ||!preg_match('/\A[a-f0-9]{64}\z/D',$ticket['code_hash'])
                ||!self::pid($ticket['order_item_id'])
                ||!isset($all_items[$ticket['order_item_id']])
                ||$ticket['order_id']!==$quote['order_id']
                ||$ticket['event_id']!==$quote['event_id']
                ||'publish'!==$ticket['status'])
                return self::reject('invalid_or_cross_order_ticket');
            $code=$all_items[$ticket['order_item_id']];
            if($ticket['ticket_type_id']!==$expected[$code]['ticket_type'])
                return self::reject('wrong_ticket_type');
            if(isset($ids[$ticket['id']])||isset($hashes[$ticket['code_hash']]))
                return self::reject('duplicate_ticket_identity');
            $ids[$ticket['id']]=true;
            $hashes[$ticket['code_hash']]=true;
            $expected[$code]['issued']++;
        }
        foreach($expected as $code=>$line){
            if($line['issued']!==$line['units'])
                return self::reject('per_line_admission_mismatch');
        }
        return array('ok'=>true,'admission_units'=>$total,'ticket_instances'=>count($tickets),
                     'commercial_lines'=>count($expected));
    }
}
