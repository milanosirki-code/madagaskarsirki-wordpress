/**
 * ==========================================================
 * MADAGASKAR SİRKİ
 * ŞEHİRLER DİNAMİK V2
 * ==========================================================
 *
 * Madagaskar Bilet Yönetimi eklentisinin RESMİ
 * events + sessions verisini kullanır.
 *
 * SADECE OKUMA YAPAR.
 *
 * - Etkinlik oluşturmaz
 * - Seans değiştirmez
 * - Ürün değiştirmez
 * - Siparişe dokunmaz
 * - Tickera biletlerine dokunmaz
 * - Kommo'ya dokunmaz
 *
 * Şehirler sayfası shortcode:
 *
 * [ms_sehirler_sayfasi_v2]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/* =========================================================
 * TÜRKÇE TARİH
 * ======================================================= */

function ms_city_v2_date_label( $date ) {

	if ( ! $date ) {
		return '';
	}

	$months = array(
		1  => 'Ocak',
		2  => 'Şubat',
		3  => 'Mart',
		4  => 'Nisan',
		5  => 'Mayıs',
		6  => 'Haziran',
		7  => 'Temmuz',
		8  => 'Ağustos',
		9  => 'Eylül',
		10 => 'Ekim',
		11 => 'Kasım',
		12 => 'Aralık',
	);

	try {

		$dt = new DateTimeImmutable(
			$date . ' 12:00:00',
			wp_timezone()
		);

		return
			$dt->format( 'j' ) .
			' ' .
			$months[ (int) $dt->format( 'n' ) ] .
			' ' .
			$dt->format( 'Y' );

	} catch ( Throwable $e ) {

		return $date;
	}
}


/* =========================================================
 * ŞEHİR SAYFALARI
 * ======================================================= */

function ms_city_v2_known_pages() {

	$result = array();

	/*
	 * Mevcut şehir URL standardizasyon snippet'ini kullan.
	 */
	if ( function_exists( 'ms_sehir_sayfalari' ) ) {

		foreach (
			ms_sehir_sayfalari()
			as $page_id => $slug
		) {

			$page = get_post( $page_id );

			if (
				! $page ||
				$page->post_type !== 'page'
			) {
				continue;
			}

			$name = preg_replace(
				'/\s+Sirk Gösterisi.*$/iu',
				'',
				$page->post_title
			);

			$name = preg_replace(
				'/\s*\|.*$/u',
				'',
				$name
			);

			$result[ sanitize_title( $slug ) ] = array(
				'id'   => (int) $page_id,
				'name' => trim( $name ),
				'url'  => get_permalink( $page_id ),
			);
		}
	}

	return $result;
}


/* =========================================================
 * ETKİNLİĞİN GELECEK SEANSLARI
 * ======================================================= */

function ms_city_v2_event_sessions( $event_id ) {

	global $wpdb;

	if (
		! class_exists( 'MDG_DB' )
	) {
		return array();
	}

	$table =
		MDG_DB::table( 'sessions' );

	/*
	 * Tablo yoksa güvenli şekilde boş dön.
	 */
	if (
		$wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		) !== $table
	) {
		return array();
	}

	$now_utc =
		current_time( 'mysql', true );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT id, start_at, end_at
			FROM {$table}
			WHERE event_id = %d
			  AND end_at >= %s
			ORDER BY start_at ASC, id ASC
			",
			(int) $event_id,
			$now_utc
		)
	);

	if ( ! $rows ) {
		return array();
	}

	$result = array();

	foreach ( $rows as $row ) {

		$date = '';
		$time = '';

		/*
		 * Madagaskar Bilet Yönetimi'nin kendi
		 * timezone dönüştürücüsünü kullan.
		 */
		if (
			class_exists( 'MDG_Sessions' ) &&
			method_exists(
				'MDG_Sessions',
				'local_parts'
			)
		) {

			$parts =
				MDG_Sessions::local_parts(
					$row->start_at
				);

			$date =
				isset( $parts[0] )
					? $parts[0]
					: '';

			$time =
				isset( $parts[1] )
					? $parts[1]
					: '';
		}


		/*
		 * Fallback.
		 */
		if ( ! $date || ! $time ) {

			try {

				$utc =
					new DateTimeImmutable(
						$row->start_at,
						new DateTimeZone( 'UTC' )
					);

				$local =
					$utc->setTimezone(
						wp_timezone()
					);

				$date =
					$local->format( 'Y-m-d' );

				$time =
					$local->format( 'H:i' );

			} catch ( Throwable $e ) {

				continue;
			}
		}


        /* Türkiye yerel gününe göre geçmiş seansları dışla (Issue #87). */
        try {
            $turkey_timezone = new DateTimeZone( 'Europe/Istanbul' );
            $today_tr = ( new DateTimeImmutable( 'now', $turkey_timezone ) )->format( 'Y-m-d' );
            $session_day_tr = ( new DateTimeImmutable( $row->start_at, new DateTimeZone( 'UTC' ) ) )
                ->setTimezone( $turkey_timezone )->format( 'Y-m-d' );
            if ( $session_day_tr < $today_tr ) {
                continue;
            }
        } catch ( Throwable $e ) {
            continue;
        }

		$result[] = array(
			'id'   => (int) $row->id,
			'date' => $date,
			'time' => substr( $time, 0, 5 ),
		);
	}

	return $result;
}


/* =========================================================
 * ETKİNLİK MİNİMUM FİYATI
 * ======================================================= */

function ms_city_v2_min_price( $event_id ) {

	if (
		! class_exists( 'MDG_Sessions' ) ||
		! method_exists(
			'MDG_Sessions',
			'ticket_catalogue_for_event'
		)
	) {
		return null;
	}

	$tickets =
		MDG_Sessions::ticket_catalogue_for_event(
			$event_id
		);

	if ( ! $tickets ) {
		return null;
	}

	$min = null;

	foreach ( (array) $tickets as $ticket ) {

		if (
			isset( $ticket->is_active ) &&
			! (int) $ticket->is_active
		) {
			continue;
		}

		if ( ! isset( $ticket->price ) ) {
			continue;
		}

		$price =
			(float) $ticket->price;

		if (
			$min === null ||
			$price < $min
		) {
			$min = $price;
		}
	}

	return $min;
}


/* =========================================================
 * CANLI ŞEHİRLER
 * ======================================================= */

function ms_city_v2_live_cities() {

	global $wpdb;

	if (
		! class_exists( 'MDG_DB' ) ||
		! class_exists( 'MDG_Status' )
	) {
		return array();
	}

	$events_table =
		MDG_DB::table( 'events' );

	$sessions_table =
		MDG_DB::table( 'sessions' );

	if (
		$wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$events_table
			)
		) !== $events_table ||
		$wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$sessions_table
			)
		) !== $sessions_table
	) {
		return array();
	}

	$now_utc =
		current_time( 'mysql', true );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"
			SELECT e.*,
			       MIN(s.start_at) AS next_session_utc
			FROM {$events_table} e
			INNER JOIN {$sessions_table} s
				ON s.event_id = e.id
			WHERE e.status = %s
              AND e.id <> 6 /* 3 Ekim 2026 Eskişehir: organizatör iptali */
			  AND s.end_at >= %s
			GROUP BY e.id
			ORDER BY MIN(s.start_at) ASC, e.id ASC
			",
			MDG_Status::ONSALE,
			$now_utc
		)
	);

	if ( ! $rows ) {
		return array();
	}

	$province_counts = array();

	foreach ( $rows as $row_event ) {
		$row_province = isset( $row_event->province_name )
			? trim( (string) $row_event->province_name )
			: '';

		if ( ! $row_province ) {
			continue;
		}

		$row_slug = sanitize_title( $row_province );

		if ( ! isset( $province_counts[ $row_slug ] ) ) {
			$province_counts[ $row_slug ] = 0;
		}

		$province_counts[ $row_slug ]++;
	}

	$result = array();

	foreach ( $rows as $event ) {

		$name =
			isset( $event->province_name )
				? trim(
					(string) $event->province_name
				)
				: '';

		if ( ! $name ) {
			continue;
		}

		$province_slug =
			sanitize_title( $name );

		$card_name = $name;

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

		if (
			isset( $province_counts[ $province_slug ] ) &&
			$province_counts[ $province_slug ] > 1
		) {
			if (
				$short_title &&
				$short_title !== $event_title &&
				sanitize_title( $short_title ) !== $province_slug
			) {
				$card_name = $name . ' – ' . $short_title;
			} elseif (
				isset( $event->district ) &&
				trim( (string) $event->district )
			) {
				$card_name =
					$name . ' – ' .
					trim( (string) $event->district );
			}
		}

		$sessions =
			ms_city_v2_event_sessions(
				$event->id
			);

		if ( ! $sessions ) {
			continue;
		}

		$first_date =
			$sessions[0]['date'];

		$times = array();

		foreach ( $sessions as $session ) {

			if (
				$session['date']
				!== $first_date
			) {
				continue;
			}

			$times[
				$session['time']
			] = $session['time'];
		}

		ksort(
			$times,
			SORT_STRING
		);

		$venue_parts = array();

		if (
			isset( $event->district ) &&
			trim( (string) $event->district )
		) {
			$venue_parts[] =
				trim(
					(string) $event->district
				);
		}

		if (
			isset( $event->venue_name ) &&
			trim(
				(string) $event->venue_name
			)
		) {
			$venue_parts[] =
				trim(
					(string) $event->venue_name
				);
		}

		$venue =
			implode(
				' · ',
				$venue_parts
			);

		$url = '';

		if (
			class_exists( 'MDG_Public_Event' ) &&
			method_exists(
				'MDG_Public_Event',
				'live_url'
			)
		) {
			$url =
				MDG_Public_Event::live_url(
					$event
				);
		}

		$result[
			'event-' . (int) $event->id
		] = array(
			'name'          => $card_name,
			'province_name' => $name,
			'province_slug' => $province_slug,
			'event'         => $event,
			'date'          => $first_date,
			'date_label'    =>
				ms_city_v2_date_label(
					$first_date
				),
			'times'         => $times,
			'venue'         => $venue,
			'price'         =>
				ms_city_v2_min_price(
					$event->id
				),
			'url'           => $url,
		);
	}

	return $result;
}


/* =========================================================
 * CANLI ŞEHİR KARTLARI
 * ======================================================= */

function ms_city_v2_render_live() {

	$cities =
		ms_city_v2_live_cities();

	if ( ! $cities ) {

		return '
		<div class="msc-empty">
			<strong>Yeni gösteri tarihleri hazırlanıyor.</strong>
			<span>
				Satışa açılan şehirler burada otomatik olarak görünecek.
			</span>
		</div>';
	}

	ob_start();
	?>

	<div class="msc-live-grid">

		<?php foreach ( $cities as $city ) : ?>

			<article class="msc-live-card">

				<div class="msc-live-top">

					<div>

						<span class="msc-eyebrow">
							YAKLAŞAN GÖSTERİ
						</span>

						<h3>
							<?php
							echo esc_html(
								$city['name']
							);
							?>
						</h3>

						<p class="msc-live-date">
							<?php
							echo esc_html(
								$city['date_label']
							);
							?>
						</p>

					</div>

					<span class="msc-sale-badge">
						SATIŞTA
					</span>

				</div>


				<?php if ( $city['venue'] ) : ?>

					<p class="msc-live-venue">
						<?php
						echo esc_html(
							$city['venue']
						);
						?>
					</p>

				<?php endif; ?>


				<?php if ( $city['times'] ) : ?>

					<div class="msc-session-row">

						<?php
						foreach (
							$city['times']
							as $time
						) :
						?>

							<span>
								<?php
								echo esc_html(
									$time
								);
								?>
							</span>

						<?php endforeach; ?>

					</div>

				<?php endif; ?>


				<?php
				if (
					$city['price'] !== null
				) :
				?>

					<p class="msc-live-price">

						<strong>
							Başlangıç:
						</strong>

						<?php
						echo wp_kses_post(
							function_exists(
								'wc_price'
							)
								? wc_price(
									$city['price']
								)
								: number_format_i18n(
									$city['price'],
									2
								) . ' ₺'
						);
						?>

					</p>

				<?php endif; ?>


				<div class="msc-live-actions">

					<?php if ( $city['url'] ) : ?>

						<a
							class="msc-button msc-button-primary"
							href="<?php echo esc_url( $city['url'] ); ?>"
						>
							<?php
							echo esc_html(
								$city['name']
							);
							?>
							Biletlerini İncele
						</a>

					<?php endif; ?>

				</div>

			</article>

		<?php endforeach; ?>

	</div>

	<?php

	return ob_get_clean();
}


/* =========================================================
 * SATIŞTA OLMAYAN ŞEHİRLER
 * ======================================================= */

function ms_city_v2_render_upcoming() {

	$known =
		ms_city_v2_known_pages();

	$live =
		ms_city_v2_live_cities();

	$live_provinces = array();

	foreach ( $live as $live_event ) {

		if (
			! empty(
				$live_event['province_slug']
			)
		) {
			$live_provinces[
				$live_event['province_slug']
			] = true;
		}
	}

	if ( ! $known ) {
		return '';
	}

	ob_start();
	?>

	<div class="msc-upcoming-grid">

		<?php foreach ( $known as $slug => $city ) : ?>

			<?php
			if ( isset( $live_provinces[ $slug ] ) ) {
				continue;
			}
			?>

			<a
				class="msc-upcoming-city"
				href="<?php echo esc_url( $city['url'] ); ?>"
			>

				<strong>
					<?php
					echo esc_html(
						$city['name']
					);
					?>
				</strong>

				<span>
					Yakında
				</span>

			</a>

		<?php endforeach; ?>

	</div>

	<?php

	return ob_get_clean();
}


/* =========================================================
 * TAM ŞEHİRLER SAYFASI
 * ======================================================= */

function ms_city_v2_render_page() {

	ob_start();
	?>

<style id="ms-sehirler-v2-css">

/* =========================================================
   TEMEL
   ======================================================= */

.msc-final-page{
	--ms-red:#c84b3b;
	--ms-red-dark:#a93a2e;
	--ms-black:#101017;
	--ms-cream:#f5f0e6;
	--ms-soft:#ece5d8;
	--ms-white:#ffffff;
	--ms-muted:#66666f;

	position:relative;

	width:100vw !important;
	max-width:100vw !important;

	margin-left:calc(50% - 50vw) !important;
	margin-right:calc(50% - 50vw) !important;

	padding:0 !important;

	background:var(--ms-cream);

	color:var(--ms-black);

	overflow:hidden;
}

.msc-final-page *,
.msc-final-page *::before,
.msc-final-page *::after{
	box-sizing:border-box;
}

.msc-final-page a{
	text-decoration:none;
}

.msc-final-page .msc-container{
	width:calc(100% - 48px) !important;
	max-width:1280px !important;

	margin-left:auto !important;
	margin-right:auto !important;
}


/* =========================================================
   HERO
   ======================================================= */

.msc-final-page .msc-hero{
	padding:42px 0 45px !important;
}

.msc-final-page .msc-hero-grid{
	display:grid !important;

	grid-template-columns:
		minmax(0,1.08fr)
		minmax(400px,.92fr) !important;

	gap:24px !important;

	align-items:stretch !important;
}

.msc-final-page .msc-hero-copy,
.msc-final-page .msc-hero-media{
	min-height:560px;

	background:#fff;

	border-radius:26px;

	box-shadow:
		0 16px 45px
		rgba(15,15,23,.08);
}

.msc-final-page .msc-hero-copy{
	padding:48px !important;

	display:flex !important;
	flex-direction:column !important;
	justify-content:space-between !important;
}

.msc-final-page .msc-kicker{
	display:inline-flex;

	width:max-content;

	margin-bottom:24px;

	padding:10px 15px;

	border-radius:999px;

	background:var(--ms-black);

	color:#fff;

	font-size:12px;
	font-weight:900;

	letter-spacing:1.5px;
}

.msc-final-page h1{
	max-width:760px;

	margin:0 0 25px !important;

	font-size:clamp(48px,5vw,72px) !important;

	line-height:.97 !important;

	letter-spacing:-2.5px !important;

	color:var(--ms-black) !important;
}

.msc-final-page .msc-lead{
	max-width:710px;

	margin:0 !important;

	font-size:18px;

	line-height:1.65;

	color:#37373e;
}

.msc-final-page .msc-actions{
	display:flex;

	flex-wrap:wrap;

	gap:10px;
}


/* =========================================================
   BUTON
   ======================================================= */

.msc-final-page .msc-button{
	display:inline-flex;

	align-items:center;
	justify-content:center;

	min-height:50px;

	padding:0 22px;

	border-radius:999px;

	font-size:14px;

	font-weight:850;

	transition:.18s ease;
}

.msc-final-page .msc-button:hover{
	transform:translateY(-1px);
}

.msc-final-page .msc-button-primary{
	background:var(--ms-red);

	color:#fff;

	box-shadow:
		0 10px 24px
		rgba(200,75,59,.22);
}

.msc-final-page .msc-button-primary:hover{
	background:var(--ms-red-dark);
}

.msc-final-page .msc-button-light{
	background:#fff;

	color:var(--ms-black);

	border:1px solid rgba(15,15,23,.10);
}


/* =========================================================
   HERO FOTOĞRAF
   ======================================================= */

.msc-final-page .msc-hero-media{
	padding:18px;

	display:flex;

	flex-direction:column;

	gap:15px;
}

.msc-final-page .msc-photo{
	position:relative;

	flex:1;

	min-height:420px;

	overflow:hidden;

	padding:30px;

	border-radius:20px;

	display:flex;

	align-items:flex-end;

	background-image:
		linear-gradient(
			180deg,
			rgba(10,10,15,.03) 0%,
			rgba(10,10,15,.12) 45%,
			rgba(10,10,15,.87) 100%
		),
		url("https://madagaskarsirki.com/wp-content/uploads/2026/08/1001155378-1.jpg");

	background-size:cover;
	background-position:center 40%;
	background-repeat:no-repeat;
}

.msc-final-page .msc-photo-label{
	display:inline-flex;

	margin-bottom:14px;

	padding:8px 12px;

	border-radius:999px;

	background:rgba(15,15,23,.68);

	color:#fff;

	font-size:11px;

	font-weight:900;
}

.msc-final-page .msc-photo h2{
	margin:0 !important;

	color:#fff !important;

	font-size:39px !important;

	line-height:1.04 !important;

	letter-spacing:-1px !important;

	text-shadow:
		0 3px 18px
		rgba(0,0,0,.45);
}

.msc-final-page .msc-stats{
	display:grid;

	grid-template-columns:
		repeat(3,minmax(0,1fr));

	gap:10px;
}

.msc-final-page .msc-stat{
	min-height:92px;

	padding:12px 8px;

	border-radius:15px;

	background:var(--ms-cream);

	display:flex;

	flex-direction:column;

	align-items:center;
	justify-content:center;

	text-align:center;
}

.msc-final-page .msc-stat strong{
	font-size:17px;
}

.msc-final-page .msc-stat span{
	margin-top:4px;

	font-size:10px;

	color:var(--ms-muted);
}


/* =========================================================
   BÖLÜMLER
   ======================================================= */

.msc-final-page .msc-section{
	padding:44px 0 !important;
}

.msc-final-page .msc-section-soft{
	background:var(--ms-soft);
}

.msc-final-page .msc-section-head{
	margin-bottom:23px;
}

.msc-final-page .msc-eyebrow{
	display:block;

	margin-bottom:8px;

	color:var(--ms-red);

	font-size:11px;

	font-weight:900;

	letter-spacing:1.1px;
}

.msc-final-page .msc-section-head h2{
	margin:0 !important;

	font-size:38px !important;

	line-height:1.05 !important;

	letter-spacing:-1px !important;

	color:var(--ms-black) !important;
}

.msc-final-page .msc-section-head p{
	max-width:720px;

	margin:9px 0 0;

	color:var(--ms-muted);

	font-size:15px;

	line-height:1.55;
}


/* =========================================================
   CANLI ŞEHİRLER
   ======================================================= */

.msc-final-page .msc-live-grid{
	display:grid !important;

	grid-template-columns:
		repeat(2,minmax(0,1fr)) !important;

	gap:20px !important;

	width:100% !important;
}

.msc-final-page .msc-live-card{
	min-height:330px;

	padding:30px;

	border-radius:23px;

	background:#fff;

	box-shadow:
		0 14px 38px
		rgba(15,15,23,.07);

	display:flex;

	flex-direction:column;
}

.msc-final-page .msc-live-top{
	display:flex;

	justify-content:space-between;

	align-items:flex-start;

	gap:18px;
}

.msc-final-page .msc-live-card h3{
	margin:0 0 6px !important;

	font-size:35px !important;

	line-height:1.05 !important;

	color:var(--ms-black) !important;
}

.msc-final-page .msc-live-date{
	margin:0;

	font-size:15px;

	color:var(--ms-muted);
}

.msc-final-page .msc-sale-badge{
	display:inline-flex;

	padding:9px 13px;

	border-radius:999px;

	background:var(--ms-red);

	color:#fff;

	font-size:10px;

	font-weight:900;
}

.msc-final-page .msc-live-venue{
	margin:19px 0 0;

	color:var(--ms-muted);

	font-size:14px;
}

.msc-final-page .msc-session-row{
	display:flex;

	flex-wrap:wrap;

	gap:9px;

	margin:20px 0 4px;
}

.msc-final-page .msc-session-row span{
	min-width:82px;

	padding:12px 14px;

	border-radius:999px;

	border:1px solid rgba(15,15,23,.09);

	background:var(--ms-cream);

	text-align:center;

	font-size:14px;

	font-weight:900;
}

.msc-final-page .msc-live-price{
	margin:13px 0 0;

	font-size:14px;
}

.msc-final-page .msc-live-actions{
	margin-top:auto;

	padding-top:22px;
}


/* =========================================================
   YAKINDA ŞEHİRLER
   ======================================================= */

.msc-final-page .msc-upcoming-grid{
	display:grid !important;

	grid-template-columns:
		repeat(3,minmax(0,1fr)) !important;

	gap:13px !important;
}

.msc-final-page .msc-upcoming-city{
	min-height:88px;

	padding:20px;

	border-radius:18px;

	background:#fff;

	display:flex;

	align-items:center;
	justify-content:space-between;

	gap:12px;

	color:var(--ms-black);

	box-shadow:
		0 7px 20px
		rgba(15,15,23,.05);
}

.msc-final-page .msc-upcoming-city strong{
	font-size:18px;
}

.msc-final-page .msc-upcoming-city span{
	font-size:11px;

	font-weight:850;

	color:var(--ms-muted);
}


/* =========================================================
   3 ADIM
   ======================================================= */

.msc-final-page .msc-steps{
	display:grid;

	grid-template-columns:
		repeat(3,minmax(0,1fr));

	gap:18px;
}

.msc-final-page .msc-step{
	min-height:215px;

	padding:26px;

	border-radius:21px;

	background:#fff;

	box-shadow:
		0 13px 35px
		rgba(15,15,23,.06);
}

.msc-final-page .msc-step-number{
	display:block;

	margin-bottom:24px;

	color:var(--ms-red);

	font-size:12px;

	font-weight:900;
}

.msc-final-page .msc-step h3{
	margin:0 0 8px !important;

	font-size:22px !important;

	color:var(--ms-black) !important;
}

.msc-final-page .msc-step p{
	margin:0;

	font-size:14px;

	line-height:1.6;

	color:var(--ms-muted);
}


/* =========================================================
   GÜVEN BANDI
   ======================================================= */

.msc-final-page .msc-trust{
	display:grid;

	grid-template-columns:
		repeat(4,minmax(0,1fr));

	overflow:hidden;

	border-radius:21px;

	background:var(--ms-black);
}

.msc-final-page .msc-trust div{
	padding:24px 14px;

	text-align:center;

	border-right:
		1px solid
		rgba(255,255,255,.09);
}

.msc-final-page .msc-trust div:last-child{
	border-right:none;
}

.msc-final-page .msc-trust strong{
	display:block;

	margin-bottom:5px;

	color:#fff;

	font-size:14px;
}

.msc-final-page .msc-trust span{
	font-size:11px;

	color:
		rgba(255,255,255,.64);
}


/* =========================================================
   CTA
   ======================================================= */

.msc-final-page .msc-final{
	padding:10px 0 55px;
}

.msc-final-page .msc-final-box{
	padding:40px;

	border-radius:27px;

	background:
		linear-gradient(
			135deg,
			#101018,
			#202028
		);

	display:grid;

	grid-template-columns:
		minmax(0,1.15fr)
		minmax(0,.85fr);

	gap:25px;

	align-items:center;
}

.msc-final-page .msc-final-box h2{
	margin:0 0 10px !important;

	color:#fff !important;

	font-size:38px !important;
}

.msc-final-page .msc-final-box p{
	max-width:650px;

	margin:0;

	color:rgba(255,255,255,.70);

	font-size:15px;

	line-height:1.6;
}

.msc-final-page .msc-final-actions{
	display:flex;

	flex-wrap:wrap;

	justify-content:flex-end;

	gap:10px;
}


/* =========================================================
   BOŞ DURUM
   ======================================================= */

.msc-final-page .msc-empty{
	padding:35px;

	border-radius:20px;

	background:#fff;

	display:flex;

	flex-direction:column;

	gap:5px;
}


/* =========================================================
   TABLET
   ======================================================= */

@media(max-width:980px){

	.msc-final-page .msc-hero-grid{
		grid-template-columns:1fr !important;
	}

	.msc-final-page .msc-hero-copy,
	.msc-final-page .msc-hero-media{
		min-height:auto;
	}

	.msc-final-page .msc-live-grid{
		grid-template-columns:
			repeat(2,minmax(0,1fr)) !important;
	}

	.msc-final-page .msc-upcoming-grid{
		grid-template-columns:
			repeat(2,minmax(0,1fr)) !important;
	}

}


/* =========================================================
   TELEFON
   ======================================================= */

@media(max-width:700px){

	.msc-final-page .msc-container{
		width:calc(100% - 22px) !important;
	}

	.msc-final-page .msc-hero{
		padding:15px 0 28px !important;
	}

	.msc-final-page .msc-hero-copy{
		padding:26px !important;
	}

	.msc-final-page h1{
		font-size:43px !important;

		letter-spacing:-1.6px !important;
	}

	.msc-final-page .msc-lead{
		font-size:16px;
	}

	.msc-final-page .msc-actions{
		display:grid;

		grid-template-columns:1fr;
	}

	.msc-final-page .msc-actions .msc-button{
		width:100%;
	}

	.msc-final-page .msc-photo{
		min-height:390px;

		padding:23px;

		background-position:center 38%;
	}

	.msc-final-page .msc-photo h2{
		font-size:32px !important;
	}

	.msc-final-page .msc-live-grid,
	.msc-final-page .msc-upcoming-grid,
	.msc-final-page .msc-steps,
	.msc-final-page .msc-final-box{
		grid-template-columns:1fr !important;
	}

	.msc-final-page .msc-trust{
		grid-template-columns:1fr 1fr;
	}

	.msc-final-page .msc-live-card{
		padding:24px;
	}

	.msc-final-page .msc-live-card h3{
		font-size:30px !important;
	}

	.msc-final-page .msc-final-actions{
		display:grid;

		grid-template-columns:1fr;

		width:100%;
	}

	.msc-final-page .msc-final-actions .msc-button{
		width:100%;
	}

}

</style>


<main class="msc-final-page">

	<!-- HERO -->
	<section class="msc-hero">

		<div class="msc-container msc-hero-grid">


			<div class="msc-hero-copy">

				<div>

					<span class="msc-kicker">
						2026–2027 TÜRKİYE TURNESİ
					</span>

					<h1>
						Madagaskar Sirki
						Şehrinize Geliyor
					</h1>

					<p class="msc-lead">
						Akrobasi, denge, jonglörlük ve birbirinden
						renkli sahne performanslarıyla Madagaskar
						Sirki Türkiye turnesinde. Şehrinizdeki
						gösteriyi seçin, size uygun seansı bulun
						ve biletinizi online alın.
					</p>

				</div>


				<div class="msc-actions">

					<a
						href="#turne-takvimi"
						class="msc-button msc-button-primary"
					>
						Turne Takvimini İncele
					</a>

					<a
						href="https://wa.me/903129113710"
						class="msc-button msc-button-light"
					>
						WhatsApp ile Bilgi Al
					</a>

				</div>

			</div>


			<div class="msc-hero-media">

				<div class="msc-photo">

					<div>

						<span class="msc-photo-label">
							MADAGASKAR SİRKİ
						</span>

						<h2>
							Şehrini seç.<br>
							Seansını seç.<br>
							Heyecana katıl.
						</h2>

					</div>

				</div>


				<div class="msc-stats">

					<div class="msc-stat">
						<strong>1 Saat</strong>
						<span>Gösteri süresi</span>
					</div>

					<div class="msc-stat">
						<strong>Hayvansız</strong>
						<span>Sahne gösterisi</span>
					</div>

					<div class="msc-stat">
						<strong>Ailece</strong>
						<span>Ortak deneyim</span>
					</div>

				</div>

			</div>

		</div>

	</section>


	<!-- SATIŞTAKİ ŞEHİRLER -->
	<section
		class="msc-section"
		id="turne-takvimi"
	>

		<div class="msc-container">

			<div class="msc-section-head">

				<span class="msc-eyebrow">
					TURNE TAKVİMİ
				</span>

				<h2>
					Satıştaki gösteriler
				</h2>

				<p>
					Yayına alınmış Madagaskar Sirki etkinlikleri
					otomatik olarak burada görünür. Tarih, salon,
					seans ve başlangıç fiyatı doğrudan bilet
					yönetim sisteminden alınır.
				</p>

			</div>


			<?php
			echo ms_city_v2_render_live();
			?>

		</div>

	</section>


	<!-- YAKINDA -->
	<section class="msc-section msc-section-soft">

		<div class="msc-container">

			<div class="msc-section-head">

				<span class="msc-eyebrow">
					TÜRKİYE TURNESİ
				</span>

				<h2>
					Yeni şehirler
				</h2>

				<p>
					Henüz satışa açılmamış turne şehirleri.
					Yeni etkinlik satışa açıldığında şehir
					otomatik olarak yukarıdaki bölüme geçer.
				</p>

			</div>


			<?php
			echo ms_city_v2_render_upcoming();
			?>

		</div>

	</section>


	<!-- 3 ADIM -->
	<section class="msc-section">

		<div class="msc-container">

			<div class="msc-section-head">

				<span class="msc-eyebrow">
					BİLETİNİ AL
				</span>

				<h2>
					Üç adımda gösteriye hazır
				</h2>

			</div>


			<div class="msc-steps">

				<article class="msc-step">

					<span class="msc-step-number">
						01
					</span>

					<h3>
						Şehrini seç
					</h3>

					<p>
						Turne takviminden satıştaki
						gösterinin bulunduğu şehri seç.
					</p>

				</article>


				<article class="msc-step">

					<span class="msc-step-number">
						02
					</span>

					<h3>
						Seansını seç
					</h3>

					<p>
						Tarih ve saat seçeneklerinden
						sana uygun gösteriyi belirle.
					</p>

				</article>


				<article class="msc-step">

					<span class="msc-step-number">
						03
					</span>

					<h3>
						Biletini al
					</h3>

					<p>
						Güvenli online ödeme ile biletini
						tamamla ve Biletlerim alanından
						görüntüle.
					</p>

				</article>

			</div>

		</div>

	</section>


	<!-- GÜVEN -->
	<section class="msc-section">

		<div class="msc-container">

			<div class="msc-trust">

				<div>
					<strong>Güvenli Ödeme</strong>
					<span>Online ödeme altyapısı</span>
				</div>

				<div>
					<strong>Dijital Bilet</strong>
					<span>Biletlerim alanından erişim</span>
				</div>

				<div>
					<strong>WhatsApp Destek</strong>
					<span>Bilet işlemlerinde destek</span>
				</div>

				<div>
					<strong>Türkiye Turnesi</strong>
					<span>Yeni şehir ve tarihler</span>
				</div>

			</div>

		</div>

	</section>


	<!-- CTA -->
	<section class="msc-final">

		<div class="msc-container">

			<div class="msc-final-box">

				<div>

					<span class="msc-eyebrow">
						MADAGASKAR SİRKİ
					</span>

					<h2>
						Şehriniz henüz satışa açılmadı mı?
					</h2>

					<p>
						Yeni şehir ve gösteri tarihleri
						satışa açıldıkça turne takvimi
						otomatik olarak güncellenir.
					</p>

				</div>


				<div class="msc-final-actions">

					<a
						href="https://wa.me/903129113710"
						class="msc-button msc-button-light"
					>
						WhatsApp’tan Bilgi Al
					</a>

					<a
						href="/"
						class="msc-button msc-button-primary"
					>
						Anasayfaya Dön
					</a>

				</div>

			</div>

		</div>

	</section>

</main>

	<?php

	return ob_get_clean();
}


add_shortcode(
	'ms_sehirler_sayfasi_v2',
	'ms_city_v2_render_page'
);
