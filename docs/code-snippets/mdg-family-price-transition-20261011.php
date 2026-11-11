<?php
/**
 * Scoped compatibility bridge for the 2026-10-11 catalog price transition.
 * Recalculate family discounts from current cart prices before the existing fee hook.
 * No order, ticket, QR, capacity, campaign or payment writes.
 * Rollback: deactivate this snippet after restoring catalog prices.
 */
if (!defined('ABSPATH')) { exit; }
if (!function_exists('mdg_family_refresh_discount_20261011')) {
function mdg_family_refresh_discount_20261011($cart) {
    if (!is_object($cart) || !method_exists($cart, 'get_cart')) { return; }
    $targets = array(10 => 1300, 13 => 1100, 19 => 1100, 21 => 1100, 22 => 1100, 23 => 1100);
    $groups = array();
    foreach ($cart->get_cart() as $key => $item) {
        $event = (int)($item['mdg_family22_event_id'] ?? 0);
        if (empty($item['mdg_family22']) || empty($item['mdg_family22_group'])
            || !isset($targets[$event]) || isset($item['mdg_campaign_manifest'])) { continue; }
        $role = (string)($item['mdg_family22_component'] ?? '');
        if (!in_array($role, array('child', 'adult'), true)
            || empty($item['data']) || !method_exists($item['data'], 'get_price')) { continue; }
        $group = (string)$item['mdg_family22_group'];
        $price = $item['data']->get_price();
        if (!is_numeric($price) || (float)$price < 0) { continue; }
        if (!isset($groups[$group])) {
            $groups[$group] = array('event' => $event, 'keys' => array(), 'prices' => array(), 'invalid' => false);
        }
        $g =& $groups[$group];
        if ($g['event'] !== $event || (isset($g['prices'][$role]) && abs($g['prices'][$role]-(float)$price) > .009)) {
            $g['invalid'] = true;
        }
        $g['prices'][$role] = (float)$price;
        $g['keys'][] = $key;
        unset($g);
    }
    foreach ($groups as $g) {
        if ($g['invalid'] || !isset($g['prices']['child'], $g['prices']['adult'])) { continue; }
        $package = (float)$targets[$g['event']];
        $discount = round(max(0, 2*$g['prices']['child'] + 2*$g['prices']['adult'] - $package), wc_get_price_decimals());
        foreach ($g['keys'] as $key) {
            $cart->cart_contents[$key]['mdg_family22_discount_per'] = $discount;
            $cart->cart_contents[$key]['mdg_family22_package_price'] = $package;
        }
    }
}
}
add_action('woocommerce_cart_calculate_fees', 'mdg_family_refresh_discount_20261011', 19, 1);
