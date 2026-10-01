<?php
/**
 * Madagaskar Sirki
 * Tickera bilet PDF'sindeki "Adres: Adres:" tekrarını düzeltir.
 *
 * - Mevcut Bartın Tickera etkinliği #2124'ü bir kez düzeltir.
 * - Bundan sonra MDG tarafından oluşturulan yeni Tickera etkinliklerinde
 *   event_terms başındaki fazladan "Adres:" etiketini otomatik temizler.
 * - Sipariş, QR, bilet kodu, ürün, fiyat veya kapasiteye dokunmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Bir MDG Tickera etkinliğinin event_terms alanını temizle.
 */
if ( ! function_exists( 'mdg_fix_ticket_event_terms_address' ) ) {

	function mdg_fix_ticket_event_terms_address( $event_id ) {

		$event_id = absint( $event_id );

		if ( ! $event_id ) {
			return false;
		}

		if ( 'tc_events' !== get_post_type( $event_id ) ) {
			return false;
		}

		/*
		 * Yalnız Madagaskar üretim motorunun oluşturduğu
		 * Tickera etkinliklerine müdahale et.
		 */
		if (
			'1' !==
			(string) get_post_meta(
				$event_id,
				'_mdg_managed',
				true
			)
		) {
			return false;
		}

		$terms = (string) get_post_meta(
			$event_id,
			'event_terms',
			true
		);

		if ( '' === trim( $terms ) ) {
			return false;
		}

		/*
		 * Sadece metnin EN BAŞINDAKİ ilk "Adres:" ifadesini kaldır.
		 *
		 * Ticket Designer şablonundaki mevcut "Adres:" etiketi korunur.
		 */
		$clean = preg_replace(
			'/^\s*Adres\s*:\s*/u',
			'',
			$terms,
			1
		);

		if (
			! is_string( $clean ) ||
			$clean === $terms
		) {
			return false;
		}

		update_post_meta(
			$event_id,
			'event_terms',
			$clean
		);

		clean_post_cache( $event_id );

		return true;
	}
}


/**
 * Bundan sonra oluşturulan MDG Tickera etkinliklerinde
 * aynı problemi otomatik engelle.
 */
if ( ! function_exists( 'mdg_watch_ticket_event_terms_address' ) ) {

	function mdg_watch_ticket_event_terms_address(
		$meta_id,
		$object_id,
		$meta_key,
		$meta_value
	) {

		if ( 'event_terms' !== $meta_key ) {
			return;
		}

		mdg_fix_ticket_event_terms_address(
			$object_id
		);
	}
}


add_action(
	'added_post_meta',
	'mdg_watch_ticket_event_terms_address',
	30,
	4
);

add_action(
	'updated_post_meta',
	'mdg_watch_ticket_event_terms_address',
	30,
	4
);