<?php
/**
 * Plugin Name: Milano Sirki - Bilet Satışı Geçici Kilit (İnceleme Taslağı)
 * Description: Sadece Milano alan adında WooCommerce sepet/ödeme akışını daima kapalı tutacak savunma katmanı. İnceleme taslağı; canlıya yüklenmedi.
 * Version: 0.1.0
 * Requires PHP: 7.4
 *
 * DRAFT ONLY -- Not deployed to any WordPress instance.
 *
 * Security model: hard-coded OFF, no option, request parameter, admin menu,
 * nonce, user role, or environment constant can turn sales on.
 * Decommission this guard only in an independently reviewed release after
 * the canonical brand routing, PayTR, MDG and Tickera contracts are live.
 *
 * Does NOT protect unknown third-party payment webhook implementations or
 * central Madagaskar checkout. Those need separate source-side protections.
 */
if (!defined('ABSPATH')) { exit; }

/** This plugin MUST be scoped to Milano only; never block Madagaskar sales. */
function msf_is_milano_site() {
    if (!function_exists('home_url') || !function_exists('wp_parse_url')) {
        return false;
    }
    $host=wp_parse_url(home_url('/'),PHP_URL_HOST);
    if (!is_string($host)) { return false; }
    $host=strtolower(rtrim($host,'.'));
    return in_array($host,array('milanosirki.com','www.milanosirki.com'),true);
}

/** No admin/request feature switch can enable checkout while installed. */
function msf_sales_frozen() { return msf_is_milano_site(); }

function msf_notice() {
    return 'Milano Sirki çevrimiçi bilet satışları henüz açılmadı. Seans duyurularını takip edebilirsiniz.';
}

/** Classic Woo add-to-cart POST / product page. */
function msf_reject_add_to_cart($allowed) {
    if(!msf_sales_frozen()) { return $allowed; }
    if(function_exists('wc_add_notice')) { wc_add_notice(msf_notice(),'error'); }
    return false;
}
add_filter('woocommerce_add_to_cart_validation','msf_reject_add_to_cart',1,1);

/** Hide the purchasability layer independently of product / variation links. */
function msf_reject_purchasable($purchasable) {
    return msf_sales_frozen() ? false : $purchasable;
}
add_filter('woocommerce_is_purchasable','msf_reject_purchasable',1,1);
add_filter('woocommerce_variation_is_purchasable','msf_reject_purchasable',1,1);

/** An old cart/session cannot complete after plugin installation. */
function msf_block_classic_cart() {
    if(msf_sales_frozen() && function_exists('wc_add_notice')) {
        wc_add_notice(msf_notice(),'error');
    }
}
add_action('woocommerce_check_cart_items','msf_block_classic_cart',1);

function msf_block_classic_checkout($checkout_data,$errors) {
    if(msf_sales_frozen() && is_object($errors) && method_exists($errors,'add')) {
        $errors->add('milano_sales_frozen',msf_notice());
    }
}
add_action('woocommerce_after_checkout_validation','msf_block_classic_checkout',1,2);

function msf_block_order_creation($order,$data) {
    if(msf_sales_frozen()) { throw new \Exception(msf_notice()); }
}
add_action('woocommerce_checkout_create_order','msf_block_order_creation',1,2);

/** Woo Blocks / Store API: exceptions must prevent cart/order mutations. */
function msf_block_store_cart($product,$request) {
    if(msf_sales_frozen()) { throw new \Exception(msf_notice()); }
}
add_action('woocommerce_store_api_validate_add_to_cart','msf_block_store_cart',1,2);

function msf_block_store_checkout($order,$request) {
    if(msf_sales_frozen()) { throw new \Exception(msf_notice()); }
}
add_action('woocommerce_store_api_checkout_update_order_from_request','msf_block_store_checkout',1,2);

/** Prevent ordinary payment methods appearing in any Woo checkout template. */
function msf_hide_payment_gateways($gateways) {
    return msf_sales_frozen() ? array() : $gateways;
}
add_filter('woocommerce_available_payment_gateways','msf_hide_payment_gateways',1,1);

/**
 * Defense-in-depth ONLY. This does not replace provider-signature validation,
 * atomic Woo payment settlement, session holds or pre-Tickera issuance gate.
 * Do not install Woo/payment plugins or publish checkout based on this alone.
 */
