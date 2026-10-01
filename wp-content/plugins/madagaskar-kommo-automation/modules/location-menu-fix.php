<?php
add_action('admin_menu', function () {
    if (!function_exists('mdg_kommo_location_replies_page_simple')) {
        return;
    }
    add_submenu_page(
        'mmc-dashboard',
        'Kommo Konum Cevapları',
        'Kommo Konum Cevapları',
        'manage_options',
        'mmc-kommo-location-replies',
        'mdg_kommo_location_replies_page_simple',
        58
    );
}, 99);

add_action('admin_menu', function () {
    global $submenu;
    if (empty($submenu['mmc-dashboard']) || !is_array($submenu['mmc-dashboard'])) {
        return;
    }
    $seen = false;
    foreach ($submenu['mmc-dashboard'] as $index => $item) {
        if (($item[2] ?? '') !== 'mmc-kommo-location-replies') {
            continue;
        }
        if ($seen) {
            unset($submenu['mmc-dashboard'][$index]);
        } else {
            $seen = true;
        }
    }
}, 1000);
add_action('admin_menu', function () {
    if (!function_exists('mdg_kommo_location_replies_page_simple')) {
        return;
    }
    remove_submenu_page('mmc-dashboard', 'mmc-kommo-location-replies');
    add_submenu_page(
        'mmc-dashboard',
        'Kommo Konum Cevapları',
        'Kommo Konum Cevapları',
        'manage_options',
        'mmc-kommo-location-replies',
        'mdg_kommo_location_replies_page_simple',
        58
    );
}, 99);
add_action('admin_menu', function () {
    if (!function_exists('mdg_kommo_location_replies_page_simple')) {
        return;
    }
    add_submenu_page(
        'mmc-dashboard',
        'Kommo Konum Cevapları',
        'Kommo Konum Cevapları',
        'manage_options',
        'mmc-kommo-location-replies',
        'mdg_kommo_location_replies_page_simple',
        58
    );
}, 99);
