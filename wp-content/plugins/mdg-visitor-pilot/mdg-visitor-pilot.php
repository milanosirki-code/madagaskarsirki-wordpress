<?php
/**
 * Plugin Name: Madagaskar Visitor Recommendation Pilot
 * Description: Bounded, read-only İzmir ticket recommendation pilot with optional Gemini intake.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */
namespace Madagaskar\Visitor;
if ( ! defined( 'ABSPATH' ) ) { exit; }
foreach ( array( 'Quote' => 'class-core.php', 'Catalogue' => 'class-catalogue.php', 'WordPressSource' => 'class-wordpress-source.php' ) as $class => $file ) {
    if ( ! class_exists( '\Madagaskar\WebAssistant\\' . $class ) ) { require_once __DIR__ . '/domain/' . $file; }
}
require_once __DIR__ . '/pilot.php';
require_once __DIR__ . '/page.php';
add_action( 'rest_api_init', array( Api::class, 'routes' ) );
add_action( 'template_redirect', array( Page::class, 'render' ), 1 );
add_filter( 'the_content', array( Page::class, 'entry' ), 20 );
