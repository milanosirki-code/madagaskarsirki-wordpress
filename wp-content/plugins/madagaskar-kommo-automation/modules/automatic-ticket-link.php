<?php
/**
 * MADAGASKAR SİRKİ
 * WooCommerce → Kommo Otomatik Bilet Linki
 *
 * Ödeme başarılı olduğunda:
 * 1. Güvenli Biletlerim bağlantısını üretir.
 * 2. Kommo'daki Order#XXXX kartını bulur.
 * 3. Bağlantıyı Kommo "Bilet Linki" alanına yazar.
 * 4. Kart henüz oluşmadıysa otomatik tekrar dener.
 *
 * Code Snippets kullanırken <?php etiketi eklemeyin.
 */

/* =========================================================
 * AYARLAR
 * ======================================================= */

if ( ! defined( 'MS_KOMMO_TOKEN' ) && defined( 'MMC_KOMMO_TOKEN' ) ) {
	define( 'MS_KOMMO_TOKEN', MMC_KOMMO_TOKEN );
}

if ( ! defined( 'MS_KOMMO_BASE_URL' ) ) {
	define(
		'MS_KOMMO_BASE_URL',
		'https://milanosirki.kommo.com/api/v4'
	);
}

/**
 * Kommo "Bilet Linki" özel alan ID.
 */
if ( ! defined( 'MS_KOMMO_BILET_FIELD_ID' ) ) {
	define(
		'MS_KOMMO_BILET_FIELD_ID',
		2448404
	);
}

/**
 * WooCommerce siparişlerinin geldiği Kommo pipeline ID.
 */
if ( ! defined( 'MS_KOMMO_PIPELINE_ID' ) ) {
	define(
		'MS_KOMMO_PIPELINE_ID',
		14297695
	);
}


/* =========================================================
 * LOG
 * ======================================================= */

if ( ! function_exists( 'ms_kommo_log' ) ) {

	function ms_kommo_log( $level, $message, $context = [] ) {

		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$context['source'] = 'madagaskar-kommo-bilet';

		wc_get_logger()->log(
			$level,
			$message,
			$context
		);
	}
}


/* =========================================================
 * TOKEN KONTROLÜ
 * ======================================================= */

if ( ! function_exists( 'ms_kommo_token_hazir_mi' ) ) {

	function ms_kommo_token_hazir_mi() {

		if ( ! defined( 'MS_KOMMO_TOKEN' ) ) {
			return false;
		}

		$token = trim( (string) MS_KOMMO_TOKEN );

		if ( empty( $token ) ) {
			return false;
		}

		if (
			'YENI_KOMMO_TOKENINI_BURAYA_YAPISTIR'
			=== $token
		) {
			return false;
		}

		return true;
	}
}


/* =========================================================
 * SENKRONİZASYONU PLANLA
 * ======================================================= */

if ( ! function_exists( 'ms_kommo_schedule_bilet_sync' ) ) {

	function ms_kommo_schedule_bilet_sync( $order_id ) {

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		/**
		 * Başarılı aktarım daha önce yapıldıysa tekrar çalışma.
		 */
		if (
			'yes' ===
			$order->get_meta( '_ms_kommo_bilet_link_synced' )
		) {
			return;
		}

		$hook  = 'ms_kommo_bilet_link_sync_action';
		$args  = [ $order_id, 0 ];
		$group = 'madagaskar-kommo';

		/**
		 * İlk deneme 15 saniye sonra.
		 */
		if ( function_exists( 'as_schedule_single_action' ) ) {

			$already_scheduled = false;

			if ( function_exists( 'as_next_scheduled_action' ) ) {

				$already_scheduled = as_next_scheduled_action(
					$hook,
					$args,
					$group
				);
			}

			if ( ! $already_scheduled ) {

				as_schedule_single_action(
					time() + 15,
					$hook,
					$args,
					$group,
					true
				);
			}

			return;
		}

		/**
		 * Action Scheduler yoksa WP-Cron kullan.
		 */
		if ( ! wp_next_scheduled( $hook, $args ) ) {

			wp_schedule_single_event(
				time() + 15,
				$hook,
				$args
			);
		}
	}
}


/* =========================================================
 * HANGİ SİPARİŞLERDE ÇALIŞACAK?
 * ======================================================= */

add_action(
	'woocommerce_payment_complete',
	'ms_kommo_schedule_bilet_sync',
	20,
	1
);

add_action(
	'woocommerce_order_status_processing',
	'ms_kommo_schedule_bilet_sync',
	20,
	1
);

add_action(
	'woocommerce_order_status_completed',
	'ms_kommo_schedule_bilet_sync',
	20,
	1
);


/* =========================================================
 * TEKRAR DENEME
 * ======================================================= */

if ( ! function_exists( 'ms_kommo_retry_bilet_sync' ) ) {

	function ms_kommo_retry_bilet_sync( $order_id, $attempt ) {

		/**
		 * Bekleme süreleri:
		 * 30 saniye
		 * 60 saniye
		 * 2 dakika
		 * 5 dakika
		 * 10 dakika
		 */
		$delays = [
			30,
			60,
			120,
			300,
			600,
		];

		$attempt = absint( $attempt );

		if ( $attempt >= count( $delays ) ) {

			ms_kommo_log(
				'error',
				'Kommo Bilet Linki senkronizasyonu maksimum deneme sayısına ulaştı.',
				[
					'order_id' => absint( $order_id ),
				]
			);

			return;
		}

		$next_attempt = $attempt + 1;
		$hook         = 'ms_kommo_bilet_link_sync_action';
		$args         = [
			absint( $order_id ),
			$next_attempt,
		];
		$group        = 'madagaskar-kommo';
		$timestamp    = time() + $delays[ $attempt ];

		if ( function_exists( 'as_schedule_single_action' ) ) {

			as_schedule_single_action(
				$timestamp,
				$hook,
				$args,
				$group,
				true
			);

			return;
		}

		wp_schedule_single_event(
			$timestamp,
			$hook,
			$args
		);
	}
}


/* =========================================================
 * KOMMO'DA ORDER#XXXX KARTINI BUL
 * ======================================================= */

if ( ! function_exists( 'ms_kommo_find_order_lead' ) ) {

	function ms_kommo_find_order_lead( $order_id ) {

		$lead_name = 'Order#' . absint( $order_id );

		$url =
			MS_KOMMO_BASE_URL .
			'/leads?query=' .
			rawurlencode( $lead_name ) .
			'&limit=50';

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 20,
				'headers' => [
					'Authorization' =>
						'Bearer ' . MS_KOMMO_TOKEN,
					'Accept' =>
						'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {

			return new WP_Error(
				'kommo_request_error',
				$response->get_error_message()
			);
		}

		$http_code = wp_remote_retrieve_response_code(
			$response
		);

		/**
		 * Kart henüz oluşturulmamış olabilir.
		 */
		if ( 204 === $http_code ) {
			return 0;
		}

		if ( 200 !== $http_code ) {

			return new WP_Error(
				'kommo_http_error',
				'Kommo kart sorgusu HTTP ' . $http_code,
				[
					'http_code' => $http_code,
				]
			);
		}

		$data = json_decode(
			wp_remote_retrieve_body( $response ),
			true
		);

		if ( empty( $data['_embedded']['leads'] ) ) {
			return 0;
		}

		foreach ( $data['_embedded']['leads'] as $lead ) {

			$current_name = trim(
				(string) ( $lead['name'] ?? '' )
			);

			$current_pipeline = (int) (
				$lead['pipeline_id'] ?? 0
			);

			if ( $current_name !== $lead_name ) {
				continue;
			}

			if (
				$current_pipeline !==
				(int) MS_KOMMO_PIPELINE_ID
			) {
				continue;
			}

			return (int) $lead['id'];
		}

		return 0;
	}
}


/* =========================================================
 * KOMMO BİLET LİNKİ ALANINI GÜNCELLE
 * ======================================================= */

if ( ! function_exists( 'ms_kommo_write_bilet_url' ) ) {

	function ms_kommo_write_bilet_url(
		$lead_id,
		$bilet_url
	) {

		$endpoint =
			MS_KOMMO_BASE_URL .
			'/leads/' .
			absint( $lead_id );

		$body = [
			'custom_fields_values' => [
				[
					'field_id' =>
						(int) MS_KOMMO_BILET_FIELD_ID,
					'values' => [
						[
							'value' =>
								esc_url_raw( $bilet_url ),
						],
					],
				],
			],
		];

		$response = wp_remote_request(
			$endpoint,
			[
				'method'  => 'PATCH',
				'timeout' => 20,
				'headers' => [
					'Authorization' =>
						'Bearer ' . MS_KOMMO_TOKEN,
					'Content-Type' =>
						'application/json',
					'Accept' =>
						'application/json',
				],
				'body' => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {

			return new WP_Error(
				'kommo_patch_error',
				$response->get_error_message()
			);
		}

		$http_code = wp_remote_retrieve_response_code(
			$response
		);

		if (
			200 !== $http_code &&
			204 !== $http_code
		) {

			return new WP_Error(
				'kommo_patch_http_error',
				'Kommo güncellemesi HTTP ' . $http_code,
				[
					'http_code' => $http_code,
					'response'  =>
						wp_remote_retrieve_body( $response ),
				]
			);
		}

		return true;
	}
}


/* =========================================================
 * ASIL SENKRONİZASYON
 * ======================================================= */

add_action(
	'ms_kommo_bilet_link_sync_action',
	'ms_kommo_run_bilet_sync',
	10,
	2
);

if ( ! function_exists( 'ms_kommo_run_bilet_sync' ) ) {

	function ms_kommo_run_bilet_sync(
		$order_id,
		$attempt = 0
	) {

		$order_id = absint( $order_id );
		$attempt  = absint( $attempt );

		if ( ! $order_id ) {
			return;
		}

		if ( ! ms_kommo_token_hazir_mi() ) {

			ms_kommo_log(
				'error',
				'Kommo erişim anahtarı girilmemiş.',
				[
					'order_id' => $order_id,
				]
			);

			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {

			ms_kommo_log(
				'error',
				'WooCommerce siparişi bulunamadı.',
				[
					'order_id' => $order_id,
				]
			);

			return;
		}

		/**
		 * Daha önce başarıyla aktarıldıysa çık.
		 */
		if (
			'yes' ===
			$order->get_meta( '_ms_kommo_bilet_link_synced' )
		) {
			return;
		}

		/**
		 * MS Biletlerim kodu aktif olmalı.
		 */
		if ( ! function_exists( 'ms_biletlerim_url' ) ) {

			ms_kommo_log(
				'warning',
				'ms_biletlerim_url fonksiyonu bulunamadı.',
				[
					'order_id' => $order_id,
					'attempt'  => $attempt,
				]
			);

			ms_kommo_retry_bilet_sync(
				$order_id,
				$attempt
			);

			return;
		}

		/**
		 * Güvenli müşteri bağlantısını üret.
		 */
		$bilet_url = ms_biletlerim_url( $order_id );

		if ( empty( $bilet_url ) ) {

			ms_kommo_log(
				'warning',
				'Biletlerim bağlantısı üretilemedi.',
				[
					'order_id' => $order_id,
					'attempt'  => $attempt,
				]
			);

			ms_kommo_retry_bilet_sync(
				$order_id,
				$attempt
			);

			return;
		}

		/**
		 * Kommo sipariş kartını bul.
		 */
		$lead_id = ms_kommo_find_order_lead( $order_id );

		if ( is_wp_error( $lead_id ) ) {

			$error_data = $lead_id->get_error_data();

			$http_code = is_array( $error_data )
				? ( $error_data['http_code'] ?? 0 )
				: 0;

			ms_kommo_log(
				'warning',
				'Kommo kart sorgusu başarısız: ' .
				$lead_id->get_error_message(),
				[
					'order_id' => $order_id,
					'attempt'  => $attempt,
					'http_code' => $http_code,
				]
			);

			/**
			 * 401 veya 403 ise anahtar/yetki sorunu vardır.
			 */
			if (
				401 === (int) $http_code ||
				403 === (int) $http_code
			) {
				return;
			}

			ms_kommo_retry_bilet_sync(
				$order_id,
				$attempt
			);

			return;
		}

		if ( ! $lead_id ) {

			ms_kommo_log(
				'info',
				'Kommo sipariş kartı henüz bulunamadı.',
				[
					'order_id' => $order_id,
					'attempt'  => $attempt,
				]
			);

			ms_kommo_retry_bilet_sync(
				$order_id,
				$attempt
			);

			return;
		}

		/**
		 * Bilet bağlantısını Kommo'ya yaz.
		 */
		$result = ms_kommo_write_bilet_url(
			$lead_id,
			$bilet_url
		);

		if ( is_wp_error( $result ) ) {

			$error_data = $result->get_error_data();

			$http_code = is_array( $error_data )
				? ( $error_data['http_code'] ?? 0 )
				: 0;

			ms_kommo_log(
				'warning',
				'Kommo Bilet Linki yazılamadı: ' .
				$result->get_error_message(),
				[
					'order_id' => $order_id,
					'lead_id'  => $lead_id,
					'attempt'  => $attempt,
					'http_code' => $http_code,
				]
			);

			if (
				401 === (int) $http_code ||
				403 === (int) $http_code
			) {
				return;
			}

			ms_kommo_retry_bilet_sync(
				$order_id,
				$attempt
			);

			return;
		}

		/**
		 * Başarılı aktarımı siparişe kaydet.
		 */
		$order->update_meta_data(
			'_ms_kommo_bilet_link_synced',
			'yes'
		);

		$order->update_meta_data(
			'_ms_kommo_lead_id',
			$lead_id
		);

		$order->update_meta_data(
			'_ms_kommo_bilet_link',
			esc_url_raw( $bilet_url )
		);

		$order->update_meta_data(
			'_ms_kommo_bilet_link_synced_at',
			time()
		);

		$order->save();

		ms_kommo_log(
			'info',
			'Kommo Bilet Linki başarıyla aktarıldı.',
			[
				'order_id' => $order_id,
				'lead_id'  => $lead_id,
			]
		);
	}
}