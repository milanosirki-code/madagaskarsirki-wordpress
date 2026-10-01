<?php
add_filter( 'option_woocommerce_checkout_phone_field', function( $value ) {
    return 'required';
} );

add_filter( 'default_option_woocommerce_checkout_phone_field', function( $value ) {
    return 'required';
} );