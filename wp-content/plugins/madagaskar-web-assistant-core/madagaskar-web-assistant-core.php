<?php
/**
 * Plugin Name: Madagaskar Web Assistant Core
 * Description: Internal read-only catalogue and deterministic ticket quotes. No public endpoints or checkout hooks.
 * Version: 0.1.0
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/includes/class-core.php';
require_once __DIR__ . '/includes/class-catalogue.php';
require_once __DIR__ . '/includes/class-wordpress-source.php';
require_once __DIR__ . '/includes/class-service.php';
