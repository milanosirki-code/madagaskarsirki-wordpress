<?php
/**
 * Plugin Name: Madagaskar Legacy Redirects
 * Description: Kaldırılmış eski Madagaskar URL'lerini güncel kanonik sayfalara 301 ile yönlendirir.
 * Version: 1.0.3
 * Author: Dünya Organizasyon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MDG_LEGACY_REDIRECTS_VERSION', '1.0.3' );

add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}

		$path = '/' . trim( rawurldecode( $path ), '/' );

		$redirects = array(
			'/akrobasi'       => '/gosteriler/akrobasi/',
			'/hula-hoop'      => '/gosteriler/hula-hoop/',
			'/palyaco'        => '/gosteriler/palyaco/',
			'/tum-gosteriler' => '/gosteriler/',
			// Search Console: confirmed legacy paths, 26 October 2026.
			'/ana-sayfa' => '/',
			'/gosteriler__trashed/sahne-arkasi' => '/sahne-arkasi/',
			'/sehirler__trashed-2/gaziantep' => '/sehirler/gaziantep/',
			'/galeri/basin-gorselleri' => '/galeri/',
			'/gosteriler__trashed/akrobasi' => '/gosteriler/akrobasi/',
			'/galeri__trashed/basin-gorselleri' => '/galeri/',
			'/galeri/fotograflar' => '/galeri/',
			'/gosteriler/jonglor' => '/jonglor/',
			'/sehirler-2' => '/sehirler/',
			'/sehirler-2/mersin' => '/sehirler/mersin/',
			'/festival-organizasyonlari' => '/kurumsal/festival-organizasyonlari/',
			'/sehirler-2/ankara' => '/sehirler/ankara/',
			'/sehirler-2/konya' => '/sehirler/konya/',
			'/galeri-2' => '/galeri/',
			'/gosteriler-2' => '/gosteriler/',
			'/shop' => '/bilet-al/',
			'/urun/madagaskar-sirki-ankara-26-eylul-2026-1200-bileti' => '/bilet-al/',
			'/urun/madagaskar-sirki-ankara-26-eylul-2026-1400-bileti' => '/bilet-al/',
			'/urun/madagaskar-sirki-ankara-26-eylul-2026-1600-bileti' => '/bilet-al/',
			'/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1200-bileti' => '/bilet-al/',
			'/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1400-bileti' => '/bilet-al/',
			'/urun/madagaskar-sirki-ankara-cubuk-2026-09-26-1600-bileti' => '/bilet-al/',
		);

		if ( ! isset( $redirects[ $path ] ) ) {
			return;
		}

		wp_safe_redirect(
			home_url( $redirects[ $path ] ),
			301,
			'Madagaskar Legacy Redirects'
		);
		exit;
	},
	1
);
