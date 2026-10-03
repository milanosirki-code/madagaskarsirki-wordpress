<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$title       = 'Bilet Al | Madagaskar Sirki';
$description = 'Madagaskar Sirki satıştaki etkinlikleri, şehirleri, tarihleri, salonları, seansları ve bilet seçenekleri.';
$canonical   = home_url( '/bilet-al/' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="index,follow,max-image-preview:large">
<title><?php echo esc_html( $title ); ?></title>
<meta name="description" content="<?php echo esc_attr( $description ); ?>">
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
<meta property="og:url" content="<?php echo esc_url( $canonical ); ?>">
<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( MDG_BILET_URL . 'assets/public-tickets.css?ver=' . rawurlencode( MDG_BILET_VERSION ) ); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'mdg-public-tickets-page' ); ?>>
<?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>
<?php
if ( class_exists( 'MDG_Public_Tickets' ) ) {
    echo MDG_Public_Tickets::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>
<?php wp_footer(); ?>
</body>
</html>
