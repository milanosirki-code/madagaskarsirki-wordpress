<?php
/**
 * Plugin Name: Madagaskar Web Assistant Core
 * Description: Read-only catalogue, ticket quotes and an administrator-only preview on the verified staging site.
 * Version: 0.2.1
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/includes/class-core.php';
require_once __DIR__ . '/includes/class-catalogue.php';
require_once __DIR__ . '/includes/class-wordpress-source.php';
require_once __DIR__ . '/includes/class-service.php';
require_once __DIR__ . '/includes/class-preview.php';

if ( \Madagaskar\WebAssistant\Preview::is_staging() ) {
    add_action( 'rest_api_init', array( '\Madagaskar\WebAssistant\Preview', 'routes' ) );
    add_action( 'admin_menu', array( '\Madagaskar\WebAssistant\Preview', 'menu' ) );
    add_action( 'admin_enqueue_scripts', array( '\Madagaskar\WebAssistant\Preview', 'assets' ) );
}
