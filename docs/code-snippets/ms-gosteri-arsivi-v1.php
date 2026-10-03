/**
 * ==========================================================
 * MADAGASKAR SİRKİ
 * GÖSTERİ ARŞİVİ V1
 * ==========================================================
 *
 * Madagaskar Bilet Yönetimi eklentisinin (MDG) events + sessions
 * verisini kullanarak, oturum bitiş saati şu ândan ÖNCE olan
 * (yani gerçekleşmiş) etkinlikleri şehir/tarih/salon olarak
 * listeler.
 *
 * SADECE OKUMA YAPAR.
 *
 * - Etkinlik oluşturmaz / değiştirmez
 * - Seans değiştirmez
 * - Ürün değiştirmez
 * - Siparişe dokunmaz
 * - Satış/fiyat bilgisi göstermez (arşiv, satış sayfası değildir)
 *
 * Kapsam: yalnızca GERÇEKLEŞMİŞ gösteriler. İptal edilip hiç
 * oynanmamış etkinlikler (örn. Eskişehir event id 6, Kırıkkale
 * event id 15 — bkz. docs/KIRIKKALE_CANCELLATION_REFUND_2026-10-02.md)
 * snippet #30'daki ("MS Dinamik Şehirler ve Biletler V1") aynı
 * hariç tutma deseniyle burada da hariç tutulur, çünkü bu etkinlikler
 * hiç gerçekleşmedi ve arşivde yer almamalı.
 *
 * Sayfa shortcode:
 *
 * [ms_gosteri_arsivi]
 *
 * Önerilen sayfa: /bilet-al/arsiv/ (Bilet Al'ın alt sayfası,
 * üst menüde ayrı görünmez).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/* =========================================================
 * GERÇEKLEŞMİŞ ETKİNLİKLER
 * ======================================================= */

function ms_gosteri_arsivi_past_events( $limit = 100 ) {

	global $wpdb;

	if ( ! class_exists( 'MDG_DB' ) ) {
		return array();
	}

	$events_table   = MDG_DB::table( 'events' );
	$sessions_table = MDG_DB::table( 'sessions' );

	if (
		$wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table )
		) !== $events_table ||
		$wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table )
		) !== $sessions_table
	) {
		return array();
	}

	$now_utc = current_time( 'mysql', true );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT e.*,
			       MAX(s.start_at) AS last_session_utc
			FROM {$events_table} e
			INNER JOIN {$sessions_table} s
				ON s.event_id = e.id
			WHERE s.end_at < %s
              AND e.id <> 6  /* 3 Ekim 2026 Eskişehir: organizatör iptali, hiç oynanmadı */
              AND e.id <> 15 /* 2 Ekim 2026 Kırıkkale: iptal + tam iade, hiç oynanmadı (PR #92) */
			GROUP BY e.id
			ORDER BY MAX(s.start_at) DESC, e.id DESC
			LIMIT %d
			",
			$now_utc,
			(int) $limit
		)
	);

	if ( ! $rows ) {
		return array();
	}

	$result = array();

	foreach ( $rows as $event ) {

		$name =
			isset( $event->province_name )
				? trim( (string) $event->province_name )
				: '';

		if ( ! $name ) {
			continue;
		}

		/*
		 * Yerel tarihe çevir.
		 */
		$date = '';

		try {

			$utc = new DateTimeImmutable(
				$event->last_session_utc,
				new DateTimeZone( 'UTC' )
			);

			$local = $utc->setTimezone( wp_timezone() );

			$date = $local->format( 'Y-m-d' );

		} catch ( Throwable $e ) {
			continue;
		}

		$event_title =
			isset( $event->title )
				? trim( (string) $event->title )
				: '';

		$short_title = $event_title
			? trim(
				preg_replace(
					'/^Madagaskar\s+Sirki\s*[–—-]\s*/iu',
					'',
					$event_title
				)
			)
			: '';

		$card_name = $name;

		if (
			$short_title &&
			$short_title !== $event_title &&
			sanitize_title( $short_title ) !== sanitize_title( $name )
		) {
			$card_name = $name . ' – ' . $short_title;
		} elseif (
			isset( $event->district ) &&
			trim( (string) $event->district )
		) {
			$card_name =
				$name . ' – ' . trim( (string) $event->district );
		}

		$venue_parts = array();

		if (
			isset( $event->district ) &&
			trim( (string) $event->district )
		) {
			$venue_parts[] = trim( (string) $event->district );
		}

		if (
			isset( $event->venue_name ) &&
			trim( (string) $event->venue_name )
		) {
			$venue_parts[] = trim( (string) $event->venue_name );
		}

		$venue = implode( ' · ', $venue_parts );

		$result[ 'event-' . (int) $event->id ] = array(
			'name'       => $card_name,
			'date'       => $date,
			'date_label' =>
				function_exists( 'ms_city_v2_date_label' )
					? ms_city_v2_date_label( $date )
					: $date,
			'venue'      => $venue,
		);
	}

	return $result;
}


/* =========================================================
 * ARŞİV SAYFASI
 * ======================================================= */

function ms_gosteri_arsivi_render_page() {

	$events = ms_gosteri_arsivi_past_events();

	ob_start();
	?>

<style id="ms-gosteri-arsivi-css">

.msa-page{
	--ms-red:#c84b3b;
	--ms-black:#101017;
	--ms-cream:#f5f0e6;
	--ms-soft:#ece5d8;
	--ms-muted:#66666f;

	position:relative;

	width:100vw !important;
	max-width:100vw !important;

	margin-left:calc(50% - 50vw) !important;
	margin-right:calc(50% - 50vw) !important;

	padding:48px 0 64px !important;

	background:var(--ms-cream);

	color:var(--ms-black);
}

.msa-page *,
.msa-page *::before,
.msa-page *::after{
	box-sizing:border-box;
}

.msa-page a{
	text-decoration:none;
}

.msa-page .msa-container{
	width:calc(100% - 48px) !important;
	max-width:1100px !important;

	margin-left:auto !important;
	margin-right:auto !important;
}

.msa-page .msa-eyebrow{
	display:inline-flex;

	margin-bottom:14px;

	padding:9px 14px;

	border-radius:999px;

	background:var(--ms-black);

	color:#fff;

	font-size:12px;
	font-weight:900;

	letter-spacing:1.5px;
}

.msa-page h1{
	margin:0 0 10px !important;

	font-size:clamp(34px,4vw,48px) !important;

	line-height:1.05 !important;

	letter-spacing:-1.5px !important;

	color:var(--ms-black) !important;
}

.msa-page .msa-lead{
	max-width:640px;

	margin:0 0 36px !important;

	font-size:16px;

	line-height:1.6;

	color:#37373e;
}

.msa-page .msa-grid{
	display:grid !important;

	grid-template-columns:repeat(3,1fr) !important;

	gap:16px !important;
}

@media (max-width:860px){

	.msa-page .msa-grid{
		grid-template-columns:repeat(2,1fr) !important;
	}
}

@media (max-width:560px){

	.msa-page .msa-grid{
		grid-template-columns:1fr !important;
	}
}

.msa-page .msa-card{
	padding:20px;

	border-radius:18px;

	background:#fff;

	box-shadow:0 10px 28px rgba(15,15,23,.06);
}

.msa-page .msa-card h3{
	margin:0 0 4px !important;

	font-size:18px !important;

	color:var(--ms-black) !important;
}

.msa-page .msa-card .msa-date{
	margin:0 0 8px !important;

	font-size:13px;
	font-weight:700;

	color:var(--ms-red);
}

.msa-page .msa-card .msa-venue{
	margin:0 !important;

	font-size:13px;

	color:var(--ms-muted);
}

.msa-page .msa-empty{
	padding:40px;

	border-radius:18px;

	background:#fff;

	text-align:center;

	color:var(--ms-muted);
}

.msa-page .msa-back{
	display:inline-flex;

	margin-bottom:28px;

	font-size:14px;
	font-weight:700;

	color:var(--ms-black);
}

.msa-page .msa-back:hover{
	color:var(--ms-red);
}

</style>

<main class="msa-page">

	<div class="msa-container">

		<a class="msa-back" href="/bilet-al/">
			← Bilet Al'a dön
		</a>

		<span class="msa-eyebrow">
			ARŞİV
		</span>

		<h1>
			Gösteri arşivi
		</h1>

		<p class="msa-lead">
			Madagaskar Sirki'nin Türkiye turnesinde bugüne kadar
			gerçekleşmiş gösterileri — şehir, tarih ve salon
			bilgisiyle.
		</p>

		<?php if ( ! $events ) : ?>

			<div class="msa-empty">
				Henüz arşivlenmiş gösteri yok.
			</div>

		<?php else : ?>

			<div class="msa-grid">

				<?php foreach ( $events as $event ) : ?>

					<article class="msa-card">

						<h3>
							<?php echo esc_html( $event['name'] ); ?>
						</h3>

						<p class="msa-date">
							<?php echo esc_html( $event['date_label'] ); ?>
						</p>

						<?php if ( $event['venue'] ) : ?>

							<p class="msa-venue">
								<?php echo esc_html( $event['venue'] ); ?>
							</p>

						<?php endif; ?>

					</article>

				<?php endforeach; ?>

			</div>

		<?php endif; ?>

	</div>

</main>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'ms_gosteri_arsivi',
	'ms_gosteri_arsivi_render_page'
);
