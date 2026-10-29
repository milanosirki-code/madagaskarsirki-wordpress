<?php
/** Pure entitlement policy, independent of WordPress and the payment UI. */
final class MDG_YB_Policy {
    public static function capacity($paid, $currency, $qty, $total_cents, $refund_cents, $refunded_qty, $has_unallocated_refund) {
        if (!$paid || $currency !== 'TRY' || $qty < 1 || $total_cents !== $qty * 10000 || $has_unallocated_refund) return 0;
        return max(0, min($qty - abs($refunded_qty), (int) floor(max(0, $total_cents - $refund_cents) / 10000)));
    }
    public static function can_redeem($capacity, $used, $count) {
        return is_int($count) && $count > 0 && $used >= 0 && $used + $count <= $capacity;
    }
}
