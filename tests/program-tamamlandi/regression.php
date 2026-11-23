<?php
// php tests/program-tamamlandi/regression.php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../../wp-content/plugins/madagaskar-program-tamamlandi/madagaskar-program-tamamlandi.php';

$fail = 0; $n = 0;
function check( $cond, $msg ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $msg\n"; } }
$tz = new DateTimeZone( 'Europe/Istanbul' );
$at = function ( $s ) use ( $tz ) { return new DateTimeImmutable( $s, $tz ); };
$C  = 'MDG_Program_Tamamlandi';

// Gün bitimi sınırı.
check( ! $C::is_completed( '2026-11-07', $at( '2026-11-07 23:59:00' ) ), 'aynı gün 23:59 henüz tamamlanmadı' );
check( $C::is_completed( '2026-11-07', $at( '2026-11-08 00:00:00' ) ), 'ertesi gün 00:00 tamamlandı' );
check( ! $C::is_completed( 'bozuk', $at( '2030-01-01' ) ), 'bozuk tarih false' );
// UTC zaman verildiğinde İstanbul saatine göre değerlendirilir.
check( ! $C::is_completed( '2026-11-07', new DateTimeImmutable( '2026-11-07 20:30:00', new DateTimeZone( 'UTC' ) ) ), 'UTC 20:30 = İstanbul 23:30, tamamlanmadı' );
check( $C::is_completed( '2026-11-07', new DateTimeImmutable( '2026-11-07 21:30:00', new DateTimeZone( 'UTC' ) ) ), 'UTC 21:30 = İstanbul 00:30, tamamlandı' );

// Harita.
$today = $at( '2026-11-23 10:00' );
check( null !== $C::entry_for( 9385, $today ), 'Tire tamamlandı' );
check( null !== $C::entry_for( 9178, $today ), 'Trakya tamamlandı' );
check( null === $C::entry_for( 15282, $today ), 'Ayvalık henüz değil' );
check( null !== $C::entry_for( 15282, $at( '2026-11-27 00:01' ) ), 'Ayvalık 27 Kasımda tamamlandı' );
check( null === $C::entry_for( 16546, $at( '2026-12-13 19:00' ) ), 'Kepez gösteri günü henüz değil' );
check( null === $C::entry_for( 8796, $today ), 'genel İzmir rehberi haritada yok' );
check( null === $C::entry_for( 999999, $today ), 'bilinmeyen yazı' );

$ids = array_keys( $C::map() );
check( count( $ids ) === count( array_unique( $ids ) ), 'yinelenen ID yok' );
foreach ( $C::map() as $id => $e ) {
	check( 3 === count( $e ) && preg_match( '/^2026-\d{2}-\d{2}$/', $e[0] ), "kayıt biçimi $id" );
}

// HTML.
$h = $C::notice_html( $C::entry_for( 9178, $today ) );
check( false !== strpos( $h, 'Program tamamlandı' ), 'başlık var' );
check( false !== strpos( $h, 'Trakya turnesi programı 11–15 Kasım 2026 tarihlerinde tamamlandı' ), 'çoğul ifade' );
check( false !== strpos( $h, '/sehirler/' ) && false !== strpos( $h, 'basinda-madagaskar-sirki' ), 'bağlantılar' );
check( false !== strpos( $C::notice_html( array( 'x', '<b>', 'y' ) ), '&lt;b&gt;' ), 'etiket kaçışlanır' );

echo "$n kontrol, $fail hata\n";
exit( $fail ? 1 : 0 );
