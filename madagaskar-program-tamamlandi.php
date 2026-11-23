<?php
/**
 * Plugin Name: Madagaskar Program Tamamlandı
 * Description: Tarihi geçen etkinlik blog yazılarının başına "Program tamamlandı" notu ekler. Tarih Europe/Istanbul saatine göre, etkinlik gününün bitiminde (23.59 sonrası) devreye girer.
 * Version: 1.0.1
 * Author: Madagaskar Sirki
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MDG_Program_Tamamlandi {

	const VERSION = '1.0.1';
	const TZ      = 'Europe/Istanbul';

	/**
	 * post_id => [ son gün (Y-m-d), şehir etiketi, zaman ifadesi ].
	 * Yeni etkinlik yazıları buraya ya da `mdg_program_tamamlandi_map` filtresine eklenir.
	 */
	public static function map() {
		$map = array(
			// 2026 — tamamlananlar.
			2680  => array( '2026-10-03', 'Ankara Sincan', '3 Ekim 2026 tarihinde' ),
			2685  => array( '2026-10-04', 'Ankara Yenimahalle', '4 Ekim 2026 tarihinde' ),
			2688  => array( '2026-10-08', 'Denizli', '8 Ekim 2026 tarihinde' ),
			2700  => array( '2026-10-10', 'Ankara Mamak', '10 Ekim 2026 tarihinde' ),
			3601  => array( '2026-10-11', 'Eskişehir', '11 Ekim 2026 tarihinde' ),
			3602  => array( '2026-10-15', 'Uşak', '15 Ekim 2026 tarihinde' ),
			3603  => array( '2026-10-16', 'Didim', '16 Ekim 2026 tarihinde' ),
			3604  => array( '2026-10-17', 'Aydın Efeler', '17 Ekim 2026 tarihinde' ),
			3605  => array( '2026-10-18', 'Manisa', '18 Ekim 2026 tarihinde' ),
			6763  => array( '2026-10-25', 'Sakarya Adapazarı', '25 Ekim 2026 tarihinde' ),
			8486  => array( '2026-10-28', 'Çankırı', '28 Ekim 2026 tarihinde' ),
			7450  => array( '2026-10-30', 'Torbalı', '30 Ekim 2026 tarihinde' ),
			9385  => array( '2026-11-07', 'Tire', '7 Kasım 2026 tarihinde' ),
			9386  => array( '2026-11-07', 'Ödemiş', '7 Kasım 2026 tarihinde' ),
			2691  => array( '2026-11-08', 'İzmir', '8 Kasım 2026 tarihinde' ),
			9173  => array( '2026-11-11', 'Çorlu', '11 Kasım 2026 tarihinde' ),
			9174  => array( '2026-11-12', 'Çerkezköy', '12 Kasım 2026 tarihinde' ),
			9175  => array( '2026-11-13', 'Kırklareli', '13 Kasım 2026 tarihinde' ),
			9176  => array( '2026-11-15', 'Edirne', '15 Kasım 2026 tarihinde' ),
			9177  => array( '2026-11-15', 'Keşan', '15 Kasım 2026 tarihinde' ),
			9178  => array( '2026-11-15', 'Trakya turnesi', '11–15 Kasım 2026 tarihlerinde' ),
			// 2026 — yaklaşanlar (tarih geçince kendiliğinden devreye girer).
			15282 => array( '2026-11-26', 'Ayvalık', '26 Kasım 2026 tarihinde' ),
			15283 => array( '2026-11-27', 'Burhaniye', '27 Kasım 2026 tarihinde' ),
			15284 => array( '2026-11-28', 'Edremit', '28 Kasım 2026 tarihinde' ),
			15285 => array( '2026-11-29', 'Çanakkale', '29 Kasım 2026 tarihinde' ),
			11467 => array( '2026-12-02', 'Köyceğiz', '2 Aralık 2026 tarihinde' ),
			11468 => array( '2026-12-03', 'Dalaman', '3 Aralık 2026 tarihinde' ),
			11471 => array( '2026-12-04', 'Fethiye', '4 Aralık 2026 tarihinde' ),
			11470 => array( '2026-12-05', 'Marmaris', '5 Aralık 2026 tarihinde' ),
			11475 => array( '2026-12-05', 'Datça', '5 Aralık 2026 tarihinde' ),
			11474 => array( '2026-12-06', 'Milas', '6 Aralık 2026 tarihinde' ),
			15149 => array( '2026-12-06', 'Yatağan', '6 Aralık 2026 tarihinde' ),
			16546 => array( '2026-12-13', 'Antalya Kepez', '13 Aralık 2026 tarihinde' ),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$map = (array) apply_filters( 'mdg_program_tamamlandi_map', $map );
		}
		return $map;
	}

	/** Etkinlik günü bittiyse true: son gün 23:59:59 (İstanbul) geçmiş olmalı. */
	public static function is_completed( $end_ymd, DateTimeInterface $now ) {
		if ( ! is_string( $end_ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_ymd ) ) {
			return false;
		}
		$tz  = new DateTimeZone( self::TZ );
		$end = DateTimeImmutable::createFromFormat( '!Y-m-d', $end_ymd, $tz );
		if ( ! $end ) {
			return false;
		}
		$end = $end->setTime( 23, 59, 59 );
		return $now->getTimestamp() > $end->getTimestamp();
	}

	public static function now() {
		return new DateTimeImmutable( 'now', new DateTimeZone( self::TZ ) );
	}

	/** Tamamlanmışsa yazının kaydını döndürür, değilse null. */
	public static function entry_for( $post_id, DateTimeInterface $now = null ) {
		$map = self::map();
		$id  = (int) $post_id;
		if ( ! isset( $map[ $id ] ) || ! is_array( $map[ $id ] ) || count( $map[ $id ] ) < 3 ) {
			return null;
		}
		$now = $now ? $now : self::now();
		return self::is_completed( $map[ $id ][0], $now ) ? $map[ $id ] : null;
	}

	public static function esc( $s ) {
		return function_exists( 'esc_html' ) ? esc_html( $s ) : htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}

	public static function notice_html( array $entry ) {
		$label = self::esc( $entry[1] );
		$when  = self::esc( $entry[2] );
		return '<div class="mdg-program-tamamlandi" role="note">'
			. '<p class="mdg-pt-title"><strong>✓ Program tamamlandı</strong></p>'
			. '<p>Madagaskar Sirki ' . $label . ' programı ' . $when . ' tamamlandı. Bizi ağırlayan tüm ailelere teşekkür ederiz. Bu sayfadaki seans ve bilet bilgileri arşiv amaçlıdır.</p>'
			. '<p>Yaklaşan gösteriler için <a href="/sehirler/">Türkiye Turnesi takvimine</a> göz atabilir, basında çıkan haberlerimizi <a href="/madagaskar-blog/category/basinda-madagaskar-sirki/">Haberler</a> bölümünde okuyabilirsiniz.</p>'
			. '</div>';
	}

	public static function filter_content( $content ) {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$entry = self::entry_for( get_the_ID() );
		if ( ! $entry || false !== strpos( (string) $content, 'mdg-program-tamamlandi' ) ) {
			return $content;
		}
		return self::notice_html( $entry ) . $content;
	}

	public static function body_class( $classes ) {
		if ( function_exists( 'is_singular' ) && is_singular( 'post' ) && self::entry_for( get_queried_object_id() ) ) {
			$classes[] = 'mdg-program-tamamlandi-sayfa';
		}
		return $classes;
	}

	public static function styles() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( 'post' ) || ! self::entry_for( get_queried_object_id() ) ) {
			return;
		}
		echo '<style id="mdg-program-tamamlandi-css">'
			. '.mdg-program-tamamlandi{background:#eef7ee;border:2px solid #2e7d32;border-radius:12px;padding:16px 20px;margin:0 0 24px}'
			. '.mdg-program-tamamlandi p{margin:0 0 8px}.mdg-program-tamamlandi p:last-child{margin-bottom:0}'
			. '.mdg-program-tamamlandi .mdg-pt-title{color:#1b5e20;font-size:1.15em}'
			. '.mdg-program-tamamlandi a{text-decoration:underline}'
			// Tamamlanan programda bilet satış butonlarını yazı gövdesinde gizle (üst menü/alt bilgi etkilenmez).
			. '.mdg-program-tamamlandi-sayfa .wp-block-post-content .wp-block-buttons,'
			. '.mdg-program-tamamlandi-sayfa .entry-content .wp-block-buttons,'
			. '.mdg-program-tamamlandi-sayfa .wp-block-post-content a[href*="/tc-events/"].wp-block-button__link,'
			. '.mdg-program-tamamlandi-sayfa .wp-block-post-content a[href*="/bilet-al"].wp-block-button__link{display:none!important}'
			. '</style>';
	}

	public static function boot() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 5 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'styles' ), 20 );
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', array( 'MDG_Program_Tamamlandi', 'boot' ) );
}
