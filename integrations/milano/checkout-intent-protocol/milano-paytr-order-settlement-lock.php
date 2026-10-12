<?php
/**
 * Milano PayTR per-Woo-order success lock -- staging proof, not deployed.
 *
 * NO hooks, REST endpoints, automatic SQL migrations, Woo/MDG/PayTR side
 * effects or Tickera issuance. This lock complements the merchant-OID
 * callback registry and admission-slot ledger, but does not atomically join
 * their separate writes. No production settlement without a single trusted
 * orchestration/transaction protocol and post-crash reconciliation.
 *
 * Scope: one SUCCESSFUL payment candidate per Woo order. Failed PayTR
 * notifications should be tracked in the merchant_oid callback registry,
 * NOT allowed to reserve this success-only order lock.
 */
if ( ! defined('ABSPATH') ) { exit; }

final class MCPI_PayTR_Order_Settlement_Lock {
    private static function table_ok($value) {
        return is_string($value) && (bool)preg_match('/\A[A-Za-z0-9_]{1,64}\z/D',$value);
    }
    private static function status($s) { return array('status'=>$s); }

    public static function ddl($table) {
        if(!self::table_ok($table))return null;
        return "CREATE TABLE {$table} (\n"
          ." order_id bigint unsigned NOT NULL,\n"
          ." merchant_oid varchar(80) NOT NULL,\n"
          ." signed_payload_hash char(64) NOT NULL,\n"
          ." original_amount_cents bigint unsigned NOT NULL,\n"
          ." state varchar(24) NOT NULL,\n"
          ." worker_token char(32) NOT NULL,\n"
          ." created_utc datetime NOT NULL,\n"
          ." changed_utc datetime NOT NULL,\n"
          ." PRIMARY KEY (order_id),\n"
          ." UNIQUE KEY merchant_oid (merchant_oid)\n"
          .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    /**
     * $order is a fresh trusted central WooCommerce snapshot.
     * A valid signed success notice/attempt and brand check is mandatory.
     * Returns 'claimed' only for the first worker on a given Woo order.
     * 'claimed' is NOT confirmation of successful payment or capacity.
     */
    public static function claim($db,$table,$notice,$order,$key,$salt,$now) {
        if(!self::table_ok($table) || !is_object($db)
            || !method_exists($db,'insert')||!method_exists($db,'get_row')
            || !method_exists($db,'prepare')||!is_int($now)||$now<1
            || !class_exists('MCPI_PayTR_Callback_Preflight')) {
            return self::status('unavailable');
        }
        $check=MCPI_PayTR_Callback_Preflight::check($notice,$order,$key,$salt);
        if(!isset($check['status']))return self::status('blocked');
        if('already_final'===$check['status'])return self::status('already_final');
        if('ready_failure'===$check['status'])return self::status('failure_not_claimed');
        if('ready_success'!==$check['status'])return self::status($check['status']);

        $oid=$notice['merchant_oid'];
        $digest=hash('sha256',"milano.paytr.order-settlement.v1\n".$oid."\n"
                     .$notice['status']."\n".$notice['total_amount']);
        try{$token=bin2hex(random_bytes(16));}
        catch(Exception $e){return self::status('unavailable');}
        $utc=gmdate('Y-m-d H:i:s',$now);
        $result=$db->insert($table,array(
            'order_id'=>$order['id'],
            'merchant_oid'=>$oid,
            'signed_payload_hash'=>$digest,
            'original_amount_cents'=>(int)$order['total_cents'],
            'state'=>'claimed',
            'worker_token'=>$token,
            'created_utc'=>$utc,
            'changed_utc'=>$utc
        ),array('%d','%s','%s','%d','%s','%s','%s','%s'));
        if(1===$result)return array('status'=>'claimed','worker_token'=>$token);

        $existing=$db->get_row($db->prepare(
            "SELECT merchant_oid,signed_payload_hash,original_amount_cents,state
             FROM {$table} WHERE order_id=%d LIMIT 1",$order['id']
        ));
        if(!is_object($existing))return self::status('unavailable');
        if(!isset($existing->merchant_oid,$existing->signed_payload_hash,
                    $existing->original_amount_cents,$existing->state))
            return self::status('reconcile_required');
        if(!is_string($existing->merchant_oid)
            || !hash_equals($oid,$existing->merchant_oid))
            return self::status('different_payment_attempt');
        if(!is_string($existing->signed_payload_hash)
            || !hash_equals($digest,$existing->signed_payload_hash)
            || (string)$existing->original_amount_cents !== (string)$order['total_cents'])
            return self::status('payload_conflict');
        if('claimed'===$existing->state)return self::status('processing');
        if('final'===$existing->state)return self::status('already_final');
        return self::status('reconcile_required');
    }

    /**
     * Only called AFTER trusted central worker has independently established:
     * paid Woo order, settled MDG hold and all expected UNIQUE Tickera tickets.
     * This is a compare-and-swap marker, not those validations.
     * Never automatically take over stale claims; reconcile manually.
     */
    public static function mark_final($db,$table,$order_id,$oid,$worker_token,$now) {
        if(!self::table_ok($table)||!is_object($db)
            ||!method_exists($db,'query')||!method_exists($db,'prepare')
            ||!is_int($order_id)||$order_id<1
            ||!is_string($oid)
            ||!preg_match('/\A[0-9]{10,13}PAYTRWOO[1-9][0-9]*\z/D',$oid)
            ||!is_string($worker_token)
            ||!preg_match('/\A[a-f0-9]{32}\z/D',$worker_token)
            ||!is_int($now)||$now<1)return false;
        $changed=$db->query($db->prepare(
            "UPDATE {$table} SET state=%s,changed_utc=%s
             WHERE order_id=%d AND merchant_oid=%s AND worker_token=%s AND state=%s",
            'final',gmdate('Y-m-d H:i:s',$now),$order_id,$oid,$worker_token,'claimed'
        ));
        return 1===$changed;
    }
}
