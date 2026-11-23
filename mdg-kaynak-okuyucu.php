<?php
/**
 * Plugin Name: MDG Kaynak Okuyucu (geçici)
 * Description: Etkinleştirildiğinde madagaskar-super12 eklentisinin dosyalarını base64 ZIP olarak tek bir ÖZEL taslak yazıya kopyalar. Başka hiçbir şeyi değiştirmez. İş bitince devre dışı bırakılır.
 * Version: 0.1.1
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function mdg_kaynak_okuyucu_dump() {
	if ( ! class_exists( 'ZipArchive' ) ) { return; }
	$dir = WP_PLUGIN_DIR . '/madagaskar-super12';
	if ( ! is_dir( $dir ) ) { return; }
	$tmp = tempnam( get_temp_dir(), 'ms12' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) { return; }
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && $f->getSize() < 2000000 ) {
			$zip->addFile( $f->getPathname(), 'madagaskar-super12/' . ltrim( substr( $f->getPathname(), strlen( $dir ) ), '/' ) );
		}
	}
	$zip->close();
	$data = file_get_contents( $tmp );
	@unlink( $tmp );
	if ( false === $data ) { return; }
	kses_remove_filters();
	wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'private',
		'post_title'   => 'TEMP-MS12-KAYNAK ' . gmdate( 'Y-m-d H:i:s' ) . ' sha256=' . hash( 'sha256', $data ),
		'post_content' => 'MS12B64:' . base64_encode( $data ),
	) );
	kses_init_filters();
}
register_activation_hook( __FILE__, 'mdg_kaynak_okuyucu_dump' );
