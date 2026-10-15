<?php
/**
 * Milano Tickera admission-slot identity ledger, STAGING REVIEW ONLY.
 *
 * NOT A WORDPRESS PLUGIN: no hook, REST, schema migration, Woo/PayTR calls,
 * ticket creation, QR, stock hold or status changes. A future trusted central
 * worker MUST verify settled payment, original order brand, MDG ownership,
 * Woo line and canonical expected guest units before calling this component.
 *
 * One slot = (Woo ORDER id, WC ORDER_ITEM id, guest unit index 0..n-1).
 * This is NOT the Woo product quantity in the family-package case: the
 * canonical MDG snapshot/line may mean one family product = FOUR guests.
 * A table PRIMARY KEY serializes concurrent claims for the same slot.
 *
 * Never time-expire claimed/creating after a worker crash. Reconcile real
 * tc_tickets_instances.post_parent (= Woo ID), item_id (= WC order_item_id)
 * and ticket type with the external ticket ID before retrying.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_Ticket_Issuance_Ledger {
    private static function table_ok($table) {
        return is_string($table) && (bool) preg_match('/\A[A-Za-z0-9_]{1,64}\z/D',$table);
    }
    private static function status($status) { return array('status'=>$status); }

    public static function ddl($table) {
        if(!self::table_ok($table))return null;
        return "CREATE TABLE {$table} (\n"
           ." order_id bigint(20) unsigned NOT NULL,\n"
           ." order_item_id bigint(20) unsigned NOT NULL,\n"
           ." unit_index smallint(5) unsigned NOT NULL,\n"
           ." event_id bigint(20) unsigned NOT NULL,\n"
           ." session_id bigint(20) unsigned NOT NULL,\n"
           ." ticket_type_id bigint(20) unsigned NOT NULL,\n"
           ." expected_units smallint(5) unsigned NOT NULL,\n"
           ." state varchar(24) NOT NULL,\n"
           ." claim_token char(32) NOT NULL,\n"
           ." ticket_instance_id bigint(20) unsigned NULL DEFAULT NULL,\n"
           ." created_at datetime NOT NULL,\n"
           ." updated_at datetime NOT NULL,\n"
           ." PRIMARY KEY (order_id, order_item_id, unit_index),\n"
           ." UNIQUE KEY ticket_instance_id (ticket_instance_id)\n"
           .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }
    private static function identity_ok($x) {
        if(!is_array($x) || !isset(
            $x['order_id'],$x['order_item_id'],$x['event_id'],$x['session_id'],
            $x['ticket_type_id'],$x['expected_units'],$x['unit_index'],
            $x['owner_brand'],$x['order_brand'],$x['settlement_confirmed']
        ))return false;
        foreach(array('order_id','order_item_id','event_id','session_id','ticket_type_id') as $field) {
            if(!is_int($x[$field]) || $x[$field] < 1)return false;
        }
        return 'milano'===$x['owner_brand']
           && 'milano'===$x['order_brand']
           && true===$x['settlement_confirmed']
           && is_int($x['expected_units']) && $x['expected_units']>=1
           && $x['expected_units']<=100
           && is_int($x['unit_index'])
           && $x['unit_index']>=0
           && $x['unit_index']<$x['expected_units'];
    }
    /** Claim one synthetic pre-verified unit (not a ticket) */
    public static function claim($db,$table,$identity,$now) {
        if(!self::table_ok($table)||!self::identity_ok($identity)
          ||!is_int($now)||$now<1||!is_object($db)
          ||!method_exists($db,'insert')||!method_exists($db,'get_row')
          ||!method_exists($db,'prepare'))return self::status('blocked');
        try{$token=bin2hex(random_bytes(16));}
        catch(Exception $e){return self::status('unavailable');}
        $utc=gmdate('Y-m-d H:i:s',$now);
        $data=array(
          'order_id'=>$identity['order_id'],
          'order_item_id'=>$identity['order_item_id'],
          'unit_index'=>$identity['unit_index'],
          'event_id'=>$identity['event_id'],
          'session_id'=>$identity['session_id'],
          'ticket_type_id'=>$identity['ticket_type_id'],
          'expected_units'=>$identity['expected_units'],
          'state'=>'claimed',
          'claim_token'=>$token,
          'ticket_instance_id'=>null,
          'created_at'=>$utc,
          'updated_at'=>$utc
        );
        $result=$db->insert($table,$data,
          array('%d','%d','%d','%d','%d','%d','%d','%s','%s','%d','%s','%s'));
        if(1===$result)return array('status'=>'claimed','claim_token'=>$token);
        $row=$db->get_row($db->prepare(
          "SELECT event_id,session_id,ticket_type_id,expected_units,state,ticket_instance_id
           FROM {$table} WHERE order_id=%d AND order_item_id=%d AND unit_index=%d LIMIT 1",
          $identity['order_id'],$identity['order_item_id'],$identity['unit_index']
        ));
        if(!is_object($row))return self::status('unavailable');
        foreach(array('event_id','session_id','ticket_type_id','expected_units') as $field){
            if(!isset($row->$field)||(int)$row->$field!==$identity[$field]){
                return self::status('identity_conflict');
            }
        }
        if('issued'===$row->state && isset($row->ticket_instance_id)
          && (int)$row->ticket_instance_id>0){
            return array('status'=>'already_issued','ticket_instance_id'=>(int)$row->ticket_instance_id);
        }
        if('claimed'===$row->state && empty($row->ticket_instance_id))
            return self::status('processing');
        return self::status('reconcile_required');
    }

    /**
     * Called ONLY after a verified single vendor ticket instance exists
     * matching Woo order parent, line item_id, MDG session and type.
     * Does not mark a Woo order paid, issue a QR or send an email.
     */
    public static function attach($db,$table,$identity,$token,$ticket_id,$now) {
        if(!self::table_ok($table)||!self::identity_ok($identity)
            ||!is_object($db)||!method_exists($db,'query')
            ||!method_exists($db,'prepare')||!is_string($token)
            ||!preg_match('/\A[a-f0-9]{32}\z/D',$token)
            ||!is_int($ticket_id)||$ticket_id<1||!is_int($now)||$now<1)return false;
        $sql=$db->prepare(
          "UPDATE {$table} SET ticket_instance_id=%d,state=%s,updated_at=%s
           WHERE order_id=%d AND order_item_id=%d AND unit_index=%d
             AND event_id=%d AND session_id=%d AND ticket_type_id=%d
             AND expected_units=%d AND claim_token=%s
             AND state=%s AND ticket_instance_id IS NULL",
          $ticket_id,'issued',gmdate('Y-m-d H:i:s',$now),
          $identity['order_id'],$identity['order_item_id'],$identity['unit_index'],
          $identity['event_id'],$identity['session_id'],$identity['ticket_type_id'],
          $identity['expected_units'],$token,'claimed'
        );
        return 1===$db->query($sql);
    }
}
