<?php
/**
 * Madagaskar Sirki - Bilet Al dinamik sayfa cache koruması
 *
 * /bilet-al/ sayfasının eski HTML çıktısının cache'den
 * gösterilmesini önlemeye yardımcı olur.
 *
 * Satış, ürün, sipariş, Tickera veya etkinlik verisine dokunmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Bilet Al sayfasını cache dışı bırak.
 */
add_action( 'template_redirect', function () {

	if ( is_admin() ) {
		return;
	}

	$path = '';

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {

		$raw_path = wp_parse_url(
			wp_unslash( $_SERVER['REQUEST_URI'] ),
			PHP_URL_PATH
		);

		$path = trim(
			rawurldecode( (string) $raw_path ),
			'/'
		);
	}

	if (
		! in_array(
			sanitize_title( $path ),
			array(
				'bilet-al',
				'biletal',
			),
			true
		)
	) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
		define( 'DONOTCACHEOBJECT', true );
	}

	nocache_headers();

}, 0 );


/**
 * Yalnızca ilk etkinleştirmede WordPress object cache temizliği.
 */
add_action( 'init', function () {

	$key = 'mdg_bilet_al_cache_fix_v1';

	if ( get_option( $key ) ) {
		return;
	}

	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	update_option(
		$key,
		current_time( 'mysql' ),
		false
	);

}, 1 );