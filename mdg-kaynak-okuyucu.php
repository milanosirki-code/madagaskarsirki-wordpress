<?php
/**
 * Plugin Name: MDG Kaynak Okuyucu (geçici)
 * Description: Etkinleştirildiğinde madagaskar-super12 eklentisinin dosyalarını base64 ZIP olarak tek bir ÖZEL taslak yazıya kopyalar. Başka hiçbir şeyi değiştirmez. İş bitince devre dışı bırakılır.
 * Version: 0.1.2
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function mdg_kaynak_okuyucu_dump() {
	$dir = WP_PLUGIN_DIR . '/madagaskar-super12';
	if ( ! is_dir( $dir ) ) { return; }
	$out = '';
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	$files = array();
	foreach ( $it as $f ) { if ( $f->isFile() && $f->getSize() < 2000000 ) { $files[] = $f->getPathname(); } }
	sort( $files );
	foreach ( $files as $path ) {
		$rel  = ltrim( substr( $path, strlen( $dir ) ), '/' );
		$data = file_get_contents( $path );
		$out .= "\n=====MS12FILE " . $rel . " sha256=" . hash( 'sha256', $data ) . " b64=" . strlen( $data ) . "=====\n" . chunk_split( base64_encode( $data ), 76, "\n" );
	}
	kses_remove_filters();
	wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => 'private',
		'post_title'   => 'TEMP-MS12-DUMP2 ' . gmdate( 'Y-m-d H:i:s' ),
		'post_content' => $out . str_repeat( "\n#PAD", 12000 ),
	) );
	kses_init_filters();
}
register_activation_hook( __FILE__, 'mdg_kaynak_okuyucu_dump' );
