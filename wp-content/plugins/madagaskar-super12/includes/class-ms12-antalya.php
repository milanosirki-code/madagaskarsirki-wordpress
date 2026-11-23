<?php
/** Read-only guard for the separately tested Antalya native family checkout. */
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class MS12_Antalya {
    public const EVENT = 63;
    private const PROGRAM = 53;
    private const GATEWAY = 'paytr_payment_gateway';
    private const TICKERA = 15894;
    private const FAMILY_PRICES = [
        'FAMILY_2_2' => '1300.00',
        'FAMILY_2_4' => '1500.00',
    ];
    private const FAMILY_CAPACITY = ['FAMILY_2_2'=>4, 'FAMILY_2_4'=>6];
    private const FAMILY_VARIATIONS = [
        319 => ['FAMILY_2_2'=>15900, 'FAMILY_2_4'=>15901],
        320 => ['FAMILY_2_2'=>15904, 'FAMILY_2_4'=>15905],
    ];

    private static function exact_price($value, string $expected): bool {
        if (!is_int($value) && !is_string($value)) return false;
        if (!preg_match('/\A([0-9]{1,6})(?:\.([0-9]{1,2}))?\z/', (string)$value, $parts)) return false;
        return $parts[1].'.'.str_pad($parts[2]??'',2,'0') === $expected;
    }

    public static function family_ready(object $row): bool {
        $event = (int)($row->event_id??0);
        $session = (int)($row->session_id??0);
        $parent = (int)($row->wc_product_id??0);
        $family = self::FAMILY_VARIATIONS[$session] ?? null;
        if ($event !== self::EVENT || !$family || !$parent || (int)($row->tickera_event_id??0) !== self::TICKERA
            || (string)get_post_meta($parent,'_event_name',true) !== (string)self::TICKERA
            || get_post_meta($parent,'_tc_is_ticket',true) !== 'yes') return false;
        global $wpdb;
        $table = MDG_DB::table('ticket_types');
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT code,price,capacity_units,wc_variation_id FROM {$table} WHERE session_id=%d AND is_active=1 AND code IN ('FAMILY_2_2','FAMILY_2_4')",
            $session
        ));
        if (!is_array($records) || count($records) !== 2) return false;
        $seen = [];
        foreach ($records as $record) {
            $code = (string)$record->code;
            $id = (int)$record->wc_variation_id;
            if (!isset($family[$code]) || isset($seen[$code]) || $id !== $family[$code]
                || !self::exact_price($record->price,self::FAMILY_PRICES[$code])
                || (int)$record->capacity_units !== self::FAMILY_CAPACITY[$code]) return false;
            $variation = wc_get_product($id);
            if (!$variation || !$variation->is_type('variation') || (int)$variation->get_parent_id() !== $parent
                || $variation->get_status() !== 'publish'
                || !self::exact_price($variation->get_price(), self::FAMILY_PRICES[$code])) return false;
            $seen[$code] = true;
        }
        return count($seen) === 2;
    }

    public static function canonical_payment_ready(int $program_id): bool {
        if ($program_id !== self::PROGRAM || !class_exists('MMC_Event_Service')
            || !is_callable(['MMC_Event_Service','event_for_program']) || !is_callable(['MMC_Event_Service','integrations'])) return false;
        $event = MMC_Event_Service::event_for_program(self::PROGRAM);
        if (!is_object($event) || (int)($event->program_id??0) !== self::PROGRAM
            || ($event->event_date??'') !== '2026-12-13'
            || ($event->status??'') !== 'sales_open' || (int)($event->id??0) < 1) return false;
        $event_id = (int)$event->id;
        $rows = (array)MMC_Event_Service::integrations($event_id);
        $gates = [];
        foreach ($rows as $integration) {
            $channel = (string)($integration->channel??'');
            if (!in_array($channel,['woocommerce','tickera','paytr'],true)) continue;
            if (array_key_exists($channel,$gates)) return false; // No conflicting or ambiguous health rows.
            $status = $integration->status??'';
            $gates[$channel] = $channel==='paytr'
                ? ($status==='live' && ($integration->external_id??'')===self::GATEWAY)
                : in_array($status,['verified','live'],true);
        }
        if (empty($gates['woocommerce']) || empty($gates['tickera']) || empty($gates['paytr'])) return false;
        // An integration classification is editable. Require a linked, genuine positive paid
        // line from normal WooCommerce → MMC synchronization as well. No client data leaves WP.
        global $wpdb;
        $ledger = $wpdb->prefix . 'mmc_sales_ledger';
        $paid = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$ledger} WHERE program_id=%d AND event_id=%d AND channel='woocommerce'
             AND payment_method=%s AND paid_at IS NOT NULL AND external_order_id>0
             AND order_status IN ('processing','completed') AND net_amount>0 LIMIT 1",
            self::PROGRAM, $event_id, self::GATEWAY
        ));
        return (string)$paid === '1';
    }
}
