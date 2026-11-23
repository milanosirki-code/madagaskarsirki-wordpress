<?php
/** Staging-only integration with the native MDG cart and capacity owner. */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/class-ms12-family3-selection.php';

final class MS12_Checkout
{
    public const CODE = MS12_Family3_Selection::CODE;
    private const KEY = '_ms12_bundle';
    private const ERROR = 'Süper 12 sepeti doğrulanamadı. Aile paketleri, diğer biletler ve kuponlar ayrı sipariş gerektirir. Sepeti temizleyip yeniden seçin.';
    private static $quote;
    private static $request;

    public static function allowed_environment(): bool
    {
        return (class_exists('MS12_Plugin') && MS12_Plugin::lab_enabled())
            || (class_exists('MS12_Pilot') && MS12_Pilot::live_site());
    }

    /** The callback owns the canonical server-side event/session eligibility lookup. */
    public static function boot(callable $quote): void
    {
        if (!self::allowed_environment() || self::$quote || !class_exists('MS12_Rules')) { return; }
        self::$quote = $quote;
        foreach (array('wp_ajax_mdg_live_add_to_cart', 'wp_ajax_nopriv_mdg_live_add_to_cart') as $action) {
            add_action($action, array(__CLASS__, 'adapt'), -5);
        }
        add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'mark'), 70, 4);
        add_filter('woocommerce_add_cart_item', array(__CLASS__, 'isolate_price_object'), 70, 1);
        add_filter('woocommerce_get_cart_item_from_session', array(__CLASS__, 'isolate_price_object'), 70, 1);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'label'), 30, 2);
        add_action('woocommerce_before_calculate_totals', array(__CLASS__, 'prices'), 90);
        add_action('woocommerce_check_cart_items', array(__CLASS__, 'validate_classic_cart'), 1);
        add_action('woocommerce_store_api_validate_cart_item', array(__CLASS__, 'validate_store_cart'), 1, 2);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'copy_order_metadata'), 25, 4);
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'flag_order'), 25, 1);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array(__CLASS__, 'flag_order'), 25, 1);
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'verify_classic_order'), 4, 3);
        add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'verify_order'), 4, 1);
        add_action('woocommerce_before_pay_action', array(__CLASS__, 'verify_order'), 4, 1);
    }

    public static function adapt(): void
    {
        self::$request = null;
        $raw = isset($_POST['lines']) && is_string($_POST['lines']) ? wp_unslash($_POST['lines']) : '';
        if (stripos($raw, self::CODE) === false) { return; } // Leave all existing packages untouched.
        $event = absint($_POST['event_id'] ?? 0);
        $session = absint($_POST['session_id'] ?? 0);
        check_ajax_referer('mdg_live_cart_' . $event, 'nonce');
        try {
            if (!$event || !$session || strlen($raw) > 12000 || !function_exists('WC')) { throw new RuntimeException(); }
            $count = MS12_Family3_Selection::quantity($raw);
            $group = self::quoted($event, $session, $count);
            if (function_exists('wc_load_cart') && (!WC()->cart || !WC()->session)) { wc_load_cart(); }
            if (!WC()->cart || WC()->cart->get_cart() || WC()->cart->get_applied_coupons() || WC()->cart->get_fees()) { throw new RuntimeException(); }
            $group['group'] = wp_generate_uuid4();
            self::$request = $group;
            // Only one owner may add lines, hold capacity or mint tickets: native MDG.
            // Existing virtual family adapters see only native codes and do nothing.
            $_POST['lines'] = wp_slash(wp_json_encode(MS12_Family3_Selection::native_lines($group)));
        } catch (Throwable $e) {
            wp_send_json_error(array('message' => self::ERROR), 409);
        }
    }

    private static function quoted(int $event, int $session, int $count): array
    {
        $offer = call_user_func(self::$quote, $event, $session);
        if (!is_array($offer) || $count < 1 || $count > 2 || $count > ($offer['maxBundles'] ?? 0)
            || !isset($offer['priceCents'], $offer['adultPrice'], $offer['adultCode'], $offer['childCode'],
                      $offer['adultVariationId'], $offer['childVariationId'])
            || !is_string($offer['adultCode']) || !is_string($offer['childCode'])
            || !preg_match('/\\A[A-Z0-9_]+\\z/D', $offer['adultCode']) || !preg_match('/\\A[A-Z0-9_]+\\z/D', $offer['childCode'])
            || $offer['adultCode'] === $offer['childCode'] || $offer['adultCode'] === self::CODE || $offer['childCode'] === self::CODE
            || !function_exists('get_woocommerce_currency') || get_woocommerce_currency() !== 'TRY'
            || !function_exists('wc_get_price_decimals') || wc_get_price_decimals() !== 2) {
            throw new RuntimeException();
        }
        $ids = array('adult' => $offer['adultVariationId'], 'child' => $offer['childVariationId']);
        $expanded = MS12_Rules::expand($offer['adultPrice'], $count, $ids, ($offer['approvedAltTariff'] ?? false) === true);
        if ($expanded['bundle_cents'] !== $offer['priceCents']) { throw new RuntimeException(); }
        $unit = array();
        foreach ($expanded['tickets'] as $ticket) {
            $role = $ticket['kind'];
            if (isset($unit[$role]) && $unit[$role] !== $ticket['unit_cents']) { throw new RuntimeException(); }
            $unit[$role] = $ticket['unit_cents'];
        }
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            // A tax-inclusive cross-jurisdiction price promise needs real Woo checkout testing.
            // For this adapter only native virtual and reliably zero-tax fixture products qualify.
            if (!$product || !$product->is_type('variation') || !$product->is_virtual()
                || (wc_tax_enabled() && $product->get_tax_status() !== 'none')) { throw new RuntimeException(); }
        }
        return array(
            'event' => $event, 'session' => $session, 'count' => $count, 'ids' => $ids, 'unit' => $unit,
            'total' => $expanded['total_cents'], 'adultCode' => $offer['adultCode'], 'childCode' => $offer['childCode'],
        );
    }

    public static function mark(array $data, $product_id, $variation_id, $qty): array
    {
        $group = self::$request;
        if (!$group) { return $data; }
        if (empty($data['mdg_live_sale']) || (int)($data['mdg_event_id'] ?? 0) !== $group['event']
            || (int)($data['mdg_session_id'] ?? 0) !== $group['session']) { throw new RuntimeException(self::ERROR); }
        foreach ($group['ids'] as $role => $id) {
            if ((int)$variation_id !== $id) { continue; }
            $expected = $group['count'] * ($role === 'adult' ? MS12_Rules::ADULTS : MS12_Rules::CHILDREN);
            if ((int)$qty !== $expected) { throw new RuntimeException(self::ERROR); }
            $data[self::KEY] = array('group' => $group['group'], 'event' => $group['event'], 'session' => $group['session'],
                'count' => $group['count'], 'total' => $group['total'], 'role' => $role);
            return $data;
        }
        throw new RuntimeException(self::ERROR);
    }

    public static function label(array $labels, array $item): array
    {
        $tag = $item[self::KEY] ?? null;
        if (is_array($tag) && isset($tag['count'])) {
            $labels[] = array('key' => 'Süper 12', 'value' => (int)$tag['count'] . ' × (4 yetişkin + 8 çocuk)');
        }
        return $labels;
    }

    public static function isolate_price_object(array $item): array
    {
        if (isset($item[self::KEY]) && isset($item['data']) && is_object($item['data'])) {
            $item['data'] = clone $item['data'];
        }
        return $item;
    }

    private static function bundle(array $rows): ?array
    {
        $tagged = array_filter($rows, static function ($row) { return isset($row['tag']); });
        if (!$tagged) { return null; }
        if (count($rows) !== 2 || count($tagged) !== 2) { throw new RuntimeException(self::ERROR); }
        $first = reset($tagged)['tag'];
        if (!is_array($first) || empty($first['group']) || !isset($first['event'], $first['session'], $first['count'], $first['total'])) { throw new RuntimeException(self::ERROR); }
        $group = self::quoted((int)$first['event'], (int)$first['session'], (int)$first['count']);
        if ((int)$first['total'] !== $group['total']) { throw new RuntimeException(self::ERROR); }
        $seen = array();
        foreach ($rows as $key => $row) {
            $tag = $row['tag'];
            if (!is_array($tag) || ($tag['group'] ?? '') !== $first['group'] || ($tag['event'] ?? 0) !== $first['event']
                || ($tag['session'] ?? 0) !== $first['session'] || ($tag['count'] ?? 0) !== $first['count']
                || ($tag['total'] ?? 0) !== $first['total']) { throw new RuntimeException(self::ERROR); }
            $role = $tag['role'] ?? '';
            if (!isset($group['ids'][$role]) || isset($seen[$role]) || $row['id'] !== $group['ids'][$role]
                || $row['qty'] !== $group['count'] * ($role === 'adult' ? MS12_Rules::ADULTS : MS12_Rules::CHILDREN)) {
                throw new RuntimeException(self::ERROR);
            }
            $seen[$role] = $key;
        }
        $group['keys'] = $seen;
        return $group;
    }

    private static function cart_bundle($cart): ?array
    {
        $rows = array();
        foreach ($cart->get_cart() as $key => $item) {
            $rows[$key] = array('tag' => $item[self::KEY] ?? null, 'id' => (int)($item['variation_id'] ?? 0), 'qty' => (int)($item['quantity'] ?? 0));
        }
        $group = self::bundle($rows);
        if ($group && ($cart->get_applied_coupons() || $cart->get_fees() || $cart->needs_shipping())) { throw new RuntimeException(self::ERROR); }
        return $group;
    }

    private static function is_native_first_insert($cart): bool
    {
        $request = self::$request;
        if (!$request || (!doing_action('wp_ajax_mdg_live_add_to_cart') && !doing_action('wp_ajax_nopriv_mdg_live_add_to_cart'))
            || $cart->get_applied_coupons() || $cart->get_fees() || $cart->needs_shipping()) { return false; }
        $items = $cart->get_cart();
        if (count($items) !== 1) { return false; }
        $item = reset($items);
        $tag = $item[self::KEY] ?? null;
        // The native loop adds adult first and Woo recalculates after each add.
        // Only defer that exact in-flight row; a one-row cart must still fail checkout.
        return is_array($tag) && $tag === array(
            'group' => $request['group'], 'event' => $request['event'], 'session' => $request['session'],
            'count' => $request['count'], 'total' => $request['total'], 'role' => 'adult',
        ) && !empty($item['mdg_live_sale'])
            && (int)($item['mdg_event_id'] ?? 0) === $request['event']
            && (int)($item['mdg_session_id'] ?? 0) === $request['session']
            && (int)($item['variation_id'] ?? 0) === $request['ids']['adult']
            && (int)($item['quantity'] ?? 0) === $request['count'] * MS12_Rules::ADULTS;
    }

    public static function prices($cart): void
    {
        try {
            if (self::is_native_first_insert($cart)) { return; }
            $group = self::cart_bundle($cart);
            if (!$group) { return; }
            foreach ($group['keys'] as $role => $key) {
                $item = $cart->get_cart_item($key);
                // Marked cart lines are scoped objects. Do not alter prices in the DB.
                $item['data']->set_price(self::decimal($group['unit'][$role]));
            }
        } catch (Throwable $e) {
            if (function_exists('wc_has_notice') && !wc_has_notice(self::ERROR, 'error')) { wc_add_notice(self::ERROR, 'error'); }
        }
    }

    public static function validate_classic_cart(): void
    {
        if (!WC()->cart) { return; }
        try { self::cart_bundle(WC()->cart); }
        catch (Throwable $e) { if (!wc_has_notice(self::ERROR, 'error')) { wc_add_notice(self::ERROR, 'error'); } }
    }

    public static function validate_store_cart($product, $item): void
    {
        if (!WC()->cart) { return; }
        try { self::cart_bundle(WC()->cart); }
        catch (Throwable $e) { self::reject_checkout(); }
    }

    public static function copy_order_metadata($item, $key, $values, $order): void
    {
        if (isset($values[self::KEY])) { $item->add_meta_data(self::KEY, $values[self::KEY], true); }
    }

    public static function flag_order($order): void
    {
        if (!$order instanceof WC_Order || !WC()->cart) { return; }
        foreach (WC()->cart->get_cart() as $item) {
            if (isset($item[self::KEY])) {
                $order->update_meta_data(self::KEY, 'pending_verification');
                // Persist for Store API payment retries; classic saves after this hook.
                if ($order->get_id()) { $order->save_meta_data(); }
                return;
            }
        }
    }

    public static function verify_classic_order($order_id, $posted, $order): void
    {
        self::verify_order($order ?: wc_get_order($order_id));
    }

    public static function verify_order($order): void
    {
        if (!$order instanceof WC_Order) { return; }
        try {
            $cart = WC()->cart ? self::cart_bundle(WC()->cart) : null;
            $rows = array();
            foreach ($order->get_items() as $key => $item) {
                $tag = $item->get_meta(self::KEY, true);
                $rows[$key] = array('tag' => $tag ?: null, 'id' => (int)$item->get_variation_id(), 'qty' => (int)$item->get_quantity(), 'item' => $item);
            }
            $group = self::bundle($rows);
            if (!$group && !$cart && !$order->get_meta(self::KEY, true)) { return; }
            // Blocks versions that don't persist private cart-item meta must not take money.
            if (!$group || ($cart && ($group['event'] !== $cart['event'] || $group['session'] !== $cart['session'] || $group['count'] !== $cart['count']))
                || $order->get_currency() !== 'TRY' || $order->get_items('fee') || $order->get_items('coupon') || $order->get_items('shipping')
                || self::cents($order->get_total_tax()) !== 0 || self::cents($order->get_total()) !== $group['total']) {
                throw new RuntimeException();
            }
            foreach ($group['keys'] as $role => $key) {
                $row = $rows[$key];
                if (self::cents($row['item']->get_total()) !== $row['qty'] * $group['unit'][$role]) { throw new RuntimeException(); }
            }
        } catch (Throwable $e) { self::reject_checkout(); }
    }

    private static function reject_checkout(): void
    {
        if (class_exists('\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException')
            && defined('REST_REQUEST') && REST_REQUEST) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException('mdg_super12_invalid', self::ERROR, 409);
        }
        throw new RuntimeException(self::ERROR);
    }

    private static function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private static function cents($amount): int
    {
        $amount = (string)$amount;
        if (!preg_match('/\\A(\\d{1,8})(?:\\.(\\d{1,2}))?\\z/D', $amount, $matches)) { throw new RuntimeException(); }
        return (int)$matches[1] * 100 + (int)str_pad($matches[2] ?? '', 2, '0');
    }
}
