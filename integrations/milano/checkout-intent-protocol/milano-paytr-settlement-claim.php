<?php
/**
 * PayTR callback merchant_oid atomic claim (STAGING CONTRACT; NOT DEPLOYED).
 * No WordPress plugin header, hook, REST endpoint, DB table creation, payment,
 * stock, MDG, WooCommerce status change, or Tickera ticket generation.
 *
 * The caller supplies a trusted Woo order snapshot and valid secrets. This
 * registry only serializes callbacks for THE SAME merchant_oid. Multiple
 * payment attempts for one Woo order require a separate order-level payment
 * state lock plus MDG capacity/QR uniqueness; never mistake this class for a
 * complete settlement engine.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MCPI_PayTR_Settlement_Claim {
    private static function valid_table( $name ) {
        return is_string($name) && (bool) preg_match('/\A[A-Za-z0-9_]{1,64}\z/D', $name);
    }
    public static function ddl( $table ) {
        if ( ! self::valid_table($table) ) { return null; }
        // MySQL InnoDB PRIMARY KEY guarantees at most one worker claims an OID.
        return "CREATE TABLE {$table} (\n"
            . " merchant_oid varchar(80) NOT NULL,\n"
            . " order_id bigint unsigned NOT NULL,\n"
            . " payload_hash char(64) NOT NULL,\n"
            . " outcome varchar(12) NOT NULL,\n"
            . " state varchar(12) NOT NULL,\n"
            . " claim_token char(32) NOT NULL,\n"
            . " created_at datetime NOT NULL,\n"
            . " updated_at datetime NOT NULL,\n"
            . " PRIMARY KEY (merchant_oid),\n"
            . " KEY order_id (order_id)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }
    private static function result( $status ) { return array('status'=>$status); }

    /**
     * Runs signed callback + central order/attempt preflight BEFORE touching DB.
     * Status claimed means ONLY "this worker has the OID"; it is not a paid
     * Woo order, admission ticket, settlement or stock reservation.
     */
    public static function claim( $db, $table, $notice, $order, $key, $salt, $now ) {
        if ( ! self::valid_table($table) || ! is_object($db)
            || ! method_exists($db,'prepare') || ! method_exists($db,'get_row')
            || ! method_exists($db,'insert') || ! is_int($now) || $now < 1
            || ! class_exists('MCPI_PayTR_Callback_Preflight') ) {
            return self::result('unavailable');
        }
        $check=MCPI_PayTR_Callback_Preflight::check($notice,$order,$key,$salt);
        if ( ! isset($check['status']) ) { return self::result('blocked'); }
        if ( 'already_final'===$check['status'] ) { return self::result('already_final'); }
        if ( ! in_array($check['status'],array('ready_success','ready_failure'),true) ) {
            return self::result($check['status']); // blocked/rejected/manual_review
        }

        $oid=$notice['merchant_oid'];
        // Only signed OID/status/total; no unhashed auxiliary fields or PII.
        $digest=hash('sha256',"milano.paytr.callback.v1\n".$oid."\n".$notice['status']."\n".$notice['total_amount']);
        try { $token=bin2hex(random_bytes(16)); }
        catch (Exception $e) { return self::result('unavailable'); }
        $utc=gmdate('Y-m-d H:i:s',$now);
        $inserted=$db->insert($table,array(
            'merchant_oid'=>$oid,
            'order_id'=>$order['id'],
            'payload_hash'=>$digest,
            'outcome'=>$notice['status'],
            'state'=>'claimed',
            'claim_token'=>$token,
            'created_at'=>$utc,
            'updated_at'=>$utc,
        ),array('%s','%d','%s','%s','%s','%s','%s','%s'));
        if ( 1===$inserted ) {
            return array('status'=>'claimed','claim_token'=>$token);
        }
        $row=$db->get_row($db->prepare(
            "SELECT order_id,payload_hash,state,outcome
             FROM {$table} WHERE merchant_oid=%s LIMIT 1", $oid
        ));
        if ( ! is_object($row) ) { return self::result('unavailable'); }
        if ( ! isset($row->order_id,$row->payload_hash,$row->outcome,$row->state)
            || (int)$row->order_id!==$order['id']
            || ! is_string($row->payload_hash)
            || ! hash_equals($digest,$row->payload_hash)
            || $row->outcome!==$notice['status'] ) {
            return self::result('conflict');
        }
        if ( 'claimed'===$row->state ) { return self::result('processing'); }
        if ( 'final'===$row->state ) { return self::result('already_final'); }
        return self::result('reconcile_required');
    }

    /** STAGING ONLY. Caller must atomically coordinate actual gateway/order,
     * MDG holds and Tickera tickets BEFORE marking a successful settlement
     * final. A crash after Woo side effects needs manual reconciliation.
     */
    public static function finish( $db, $table, $oid, $claim_token, $now ) {
        if ( ! self::valid_table($table) || ! is_object($db)
            || ! method_exists($db,'query') || ! method_exists($db,'prepare')
            || ! is_string($oid)
            || ! preg_match('/\A[0-9]{10,13}PAYTRWOO[1-9][0-9]*\z/D',$oid)
            || ! is_string($claim_token) || ! preg_match('/\A[a-f0-9]{32}\z/D',$claim_token)
            || ! is_int($now) || $now<1 ) { return false; }
        $changed=$db->query($db->prepare(
            "UPDATE {$table} SET state=%s,updated_at=%s
             WHERE merchant_oid=%s AND claim_token=%s AND state=%s",
            'final',gmdate('Y-m-d H:i:s',$now),$oid,$claim_token,'claimed'
        ));
        return 1===$changed;
    }
}
