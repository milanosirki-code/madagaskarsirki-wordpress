<?php
/**
 * Plugin Name: Madagaskar Ticket Session Datetime Fix
 * Description: Fixes Tickera Ticket Designer PDF date/time output so it uses the purchased WooCommerce session instead of the first/default event time.
 * Version: 1.0.0
 * Author: Madagaskar Sirki
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'tickera_ticket_designer_pre_generate', 'mdg_tdfix_pre_generate', 9, 6 );

foreach (
	array(
		'tickera_ticket_designer_ticket_data',
		'tickera_ticket_designer_resolved_ticket_data',
		'tc_ticket_designer_ticket_data',
		'tc_ticket_designer_resolved_ticket_data',
	)
	as $mdg_tdfix_filter
) {
	add_filter( $mdg_tdfix_filter, 'mdg_tdfix_filter_ticket_data', 20, 4 );
}

/**
 * Render the designer PDF before Tickera's default designer callback, but with
 * corrected event_datetime data. Returning null lets Tickera continue normally.
 */
function mdg_tdfix_pre_generate( $pre = null, $ticket_instance_id = 0, $ticket_type_id = 0, $ticket_template_id = 0, $output = 'S', $filename = '' ) {
	if ( null !== $pre ) {
		return $pre;
	}

	if (
		! class_exists( 'TC_Ticket_Designer_Template' )
		|| ! class_exists( 'TC_Ticket_Designer_PDF_Generator' )
		|| ! class_exists( 'TC_Ticket_Designer_Fields' )
	) {
		return $pre;
	}

	$designer_template_id = mdg_tdfix_get_designer_template_id( $ticket_template_id, $ticket_type_id );

	if ( ! $designer_template_id ) {
		return $pre;
	}

	$template = new TC_Ticket_Designer_Template( $designer_template_id );

	if ( method_exists( $template, 'get_id' ) && ! $template->get_id() ) {
		return $pre;
	}

	if ( function_exists( 'tickera_ticket_designer_ensure_tcpdf' ) && ! tickera_ticket_designer_ensure_tcpdf() ) {
		return $pre;
	}

	$ticket_data = TC_Ticket_Designer_Fields::resolve_ticket_data( (int) $ticket_instance_id );

	if ( ! is_array( $ticket_data ) || empty( $ticket_data ) ) {
		return $pre;
	}

	$ticket_data = mdg_tdfix_correct_ticket_data( $ticket_data, (int) $ticket_instance_id );

	return TC_Ticket_Designer_PDF_Generator::generate(
		$template,
		$ticket_data,
		$output ? $output : 'S',
		$filename ? $filename : ''
	);
}

function mdg_tdfix_filter_ticket_data( $ticket_data ) {
	if ( ! is_array( $ticket_data ) ) {
		return $ticket_data;
	}

	$ticket_instance_id = 0;

	foreach ( array_slice( func_get_args(), 1 ) as $arg ) {
		if ( is_numeric( $arg ) && (int) $arg > 0 ) {
			$ticket_instance_id = (int) $arg;
			break;
		}
	}

	return mdg_tdfix_correct_ticket_data( $ticket_data, $ticket_instance_id );
}

function mdg_tdfix_get_designer_template_id( $ticket_template_id, $ticket_type_id ) {
	if ( is_string( $ticket_template_id ) && preg_match( '/^d_(\d+)$/', $ticket_template_id, $matches ) ) {
		return (int) $matches[1];
	}

	$ticket_type_id = (int) $ticket_type_id;

	if ( $ticket_type_id > 0 ) {
		foreach ( array( 'tc_designer_template_id', '_tc_designer_template_id' ) as $meta_key ) {
			$template_id = (int) get_post_meta( $ticket_type_id, $meta_key, true );

			if ( $template_id > 0 ) {
				return $template_id;
			}
		}
	}

	if ( is_numeric( $ticket_template_id ) && (int) $ticket_template_id > 0 ) {
		return (int) $ticket_template_id;
	}

	return 0;
}

function mdg_tdfix_correct_ticket_data( array $ticket_data, $ticket_instance_id = 0 ) {
	$current_datetime = isset( $ticket_data['event_datetime'] ) ? (string) $ticket_data['event_datetime'] : '';
	$resolved         = mdg_tdfix_resolve_session_datetime( $ticket_data, (int) $ticket_instance_id, $current_datetime );

	if ( empty( $resolved['text'] ) || empty( $resolved['start'] ) ) {
		return $ticket_data;
	}

	$ticket_data['event_datetime']   = $resolved['text'];
	$ticket_data['event_time']       = $resolved['start'];
	$ticket_data['event_start_time'] = $resolved['start'];
	$ticket_data['event_end_time']   = $resolved['end'];
	$ticket_data['session_time']     = $resolved['start'];
	$ticket_data['seans']            = $resolved['start'];

	if ( ! empty( $resolved['date_text'] ) ) {
		$ticket_data['event_date'] = $resolved['date_text'];
	}

	return $ticket_data;
}

function mdg_tdfix_resolve_session_datetime( array $ticket_data, $ticket_instance_id, $current_datetime ) {
	$sources     = mdg_tdfix_collect_sources( $ticket_data, $ticket_instance_id );
	$time_source = mdg_tdfix_find_time_source( $sources );

	if ( empty( $time_source['start'] ) ) {
		return array();
	}

	$date_source = mdg_tdfix_find_date_source( $sources );

	if ( empty( $date_source['timestamp'] ) ) {
		$date_source = mdg_tdfix_parse_date_from_text( $current_datetime );
	}

	$start = $time_source['start'];
	$end   = ! empty( $time_source['end'] ) ? $time_source['end'] : mdg_tdfix_add_one_hour( $start );

	$date_text = '';

	if ( ! empty( $date_source['timestamp'] ) ) {
		$date_text = mdg_tdfix_format_date( (int) $date_source['timestamp'] );
	} else {
		$date_text = mdg_tdfix_extract_date_prefix( $current_datetime );
	}

	if ( '' === $date_text ) {
		return array();
	}

	return array(
		'text'      => trim( $date_text . ' ' . $start . ' - ' . $end ),
		'date_text' => $date_text,
		'start'     => $start,
		'end'       => $end,
	);
}

function mdg_tdfix_collect_sources( array $ticket_data, $ticket_instance_id ) {
	$sources = array();

	foreach (
		array(
			'product_name',
			'product_title',
			'ticket_type',
			'ticket_type_name',
			'ticket_name',
			'variation_name',
			'variation',
			'sku',
			'event_name',
			'event_date',
			'event_start_date',
			'event_start',
			'event_end',
			'event_time',
			'event_start_time',
			'event_end_time',
			'session_time',
			'seans',
		)
		as $key
	) {
		if ( isset( $ticket_data[ $key ] ) ) {
			mdg_tdfix_add_source( $sources, 'ticket_data:' . $key, $ticket_data[ $key ] );
		}
	}

	$ticket_meta = array();

	if ( $ticket_instance_id > 0 && function_exists( 'get_post_meta' ) ) {
		$ticket_meta = (array) get_post_meta( $ticket_instance_id );

		foreach ( $ticket_meta as $key => $values ) {
			if ( preg_match( '/date|time|session|seans|start|end|event|product|variation|ticket|order|item/i', (string) $key ) ) {
				mdg_tdfix_add_source( $sources, 'ticket_meta:' . $key, $values );
			}
		}
	}

	$ids = mdg_tdfix_resolve_ids( $ticket_data, $ticket_meta, $ticket_instance_id );

	if ( ! empty( $ids['order_id'] ) && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( (int) $ids['order_id'] );

		if ( $order ) {
			$item = null;

			if ( ! empty( $ids['item_id'] ) ) {
				$item = $order->get_item( (int) $ids['item_id'] );
			}

			if ( ! $item && ( ! empty( $ids['product_id'] ) || ! empty( $ids['variation_id'] ) ) ) {
				foreach ( $order->get_items( 'line_item' ) as $candidate ) {
					if (
						( ! empty( $ids['variation_id'] ) && (int) $candidate->get_variation_id() === (int) $ids['variation_id'] )
						|| ( ! empty( $ids['product_id'] ) && (int) $candidate->get_product_id() === (int) $ids['product_id'] )
					) {
						$item = $candidate;
						break;
					}
				}
			}

			if ( $item ) {
				mdg_tdfix_add_source( $sources, 'order_item:name', $item->get_name() );

				foreach ( $item->get_meta_data() as $meta ) {
					$data = method_exists( $meta, 'get_data' ) ? $meta->get_data() : array();
					$key  = isset( $data['key'] ) ? $data['key'] : '';
					$val  = isset( $data['value'] ) ? $data['value'] : '';

					mdg_tdfix_add_source( $sources, 'order_item_meta:' . $key, $key . ' ' . mdg_tdfix_flatten_value( $val ) );
				}

				if ( method_exists( $item, 'get_product_id' ) && ! empty( $item->get_product_id() ) ) {
					$ids['product_id'] = (int) $item->get_product_id();
				}

				if ( method_exists( $item, 'get_variation_id' ) && ! empty( $item->get_variation_id() ) ) {
					$ids['variation_id'] = (int) $item->get_variation_id();
				}
			}
		}
	}

	foreach ( array_unique( array_filter( array( $ids['variation_id'] ?? 0, $ids['product_id'] ?? 0 ) ) ) as $product_id ) {
		mdg_tdfix_add_product_sources( $sources, (int) $product_id );
	}

	return $sources;
}

function mdg_tdfix_resolve_ids( array $ticket_data, array $ticket_meta, $ticket_instance_id ) {
	$ids = array(
		'order_id'     => 0,
		'item_id'      => 0,
		'product_id'   => 0,
		'variation_id' => 0,
	);

	foreach (
		array(
			'order_id'     => array( 'order_id', 'wc_order_id', 'woocommerce_order_id', 'tc_order_id' ),
			'item_id'      => array( 'item_id', 'order_item_id', 'woocommerce_order_item_id', 'tc_order_item_id' ),
			'product_id'   => array( 'product_id', 'ticket_type_id', 'ticket_type', 'wc_product_id' ),
			'variation_id' => array( 'variation_id', 'wc_variation_id' ),
		)
		as $target => $keys
	) {
		foreach ( $keys as $key ) {
			if ( ! empty( $ticket_data[ $key ] ) && is_numeric( $ticket_data[ $key ] ) ) {
				$ids[ $target ] = (int) $ticket_data[ $key ];
				break;
			}
		}
	}

	foreach (
		array(
			'order_id'     => array( '_order_id', 'order_id', '_tc_order_id', 'tc_order_id', '_woocommerce_order_id', 'woocommerce_order_id' ),
			'item_id'      => array( '_order_item_id', 'order_item_id', '_tc_order_item_id', 'tc_order_item_id' ),
			'product_id'   => array( '_product_id', 'product_id', '_ticket_type_id', 'ticket_type_id', '_tc_ticket_type_id', 'tc_ticket_type_id' ),
			'variation_id' => array( '_variation_id', 'variation_id', '_wc_variation_id', 'wc_variation_id' ),
		)
		as $target => $keys
	) {
		if ( ! empty( $ids[ $target ] ) ) {
			continue;
		}

		foreach ( $keys as $key ) {
			if ( isset( $ticket_meta[ $key ][0] ) && is_numeric( $ticket_meta[ $key ][0] ) ) {
				$ids[ $target ] = (int) $ticket_meta[ $key ][0];
				break;
			}
		}
	}

	if ( empty( $ids['order_id'] ) && $ticket_instance_id > 0 && function_exists( 'get_post' ) ) {
		$post = get_post( $ticket_instance_id );

		if ( $post && ! empty( $post->post_parent ) ) {
			$ids['order_id'] = (int) $post->post_parent;
		}
	}

	return $ids;
}

function mdg_tdfix_add_product_sources( array &$sources, $product_id ) {
	if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	$product = wc_get_product( $product_id );

	if ( ! $product ) {
		return;
	}

	mdg_tdfix_add_source( $sources, 'product:' . $product_id . ':name', $product->get_name() );
	mdg_tdfix_add_source( $sources, 'product:' . $product_id . ':sku', $product->get_sku() );

	if ( method_exists( $product, 'get_attributes' ) ) {
		foreach ( (array) $product->get_attributes() as $key => $value ) {
			mdg_tdfix_add_source( $sources, 'product_attribute:' . $key, $key . ' ' . mdg_tdfix_flatten_value( $value ) );
		}
	}

	if ( function_exists( 'get_post_meta' ) ) {
		foreach ( (array) get_post_meta( $product_id ) as $key => $values ) {
			if ( preg_match( '/date|time|session|seans|start|end|event|sku/i', (string) $key ) ) {
				mdg_tdfix_add_source( $sources, 'product_meta:' . $key, $values );
			}
		}
	}

	if ( method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
		$parent = wc_get_product( $product->get_parent_id() );

		if ( $parent ) {
			mdg_tdfix_add_source( $sources, 'parent_product:name', $parent->get_name() );
			mdg_tdfix_add_source( $sources, 'parent_product:sku', $parent->get_sku() );
		}
	}
}

function mdg_tdfix_add_source( array &$sources, $label, $value ) {
	$text = mdg_tdfix_flatten_value( $value );
	$text = mdg_tdfix_normalize_space( $text );

	if ( '' === $text ) {
		return;
	}

	$sources[] = array(
		'label' => (string) $label,
		'text'  => $text,
	);
}

function mdg_tdfix_flatten_value( $value ) {
	if ( is_scalar( $value ) || null === $value ) {
		return (string) $value;
	}

	if ( is_array( $value ) ) {
		$parts = array();

		foreach ( $value as $key => $item ) {
			$parts[] = is_string( $key ) ? $key . ' ' . mdg_tdfix_flatten_value( $item ) : mdg_tdfix_flatten_value( $item );
		}

		return implode( ' ', $parts );
	}

	if ( is_object( $value ) ) {
		if ( method_exists( $value, '__toString' ) ) {
			return (string) $value;
		}

		if ( method_exists( $value, 'get_name' ) ) {
			return (string) $value->get_name();
		}
	}

	return '';
}

function mdg_tdfix_find_time_source( array $sources ) {
	foreach ( $sources as $source ) {
		$text = isset( $source['text'] ) ? $source['text'] : '';

		$range = mdg_tdfix_parse_time_range( $text );

		if ( ! empty( $range['start'] ) ) {
			return $range;
		}
	}

	return array();
}

function mdg_tdfix_find_date_source( array $sources ) {
	foreach ( $sources as $source ) {
		$parsed = mdg_tdfix_parse_date_from_text( isset( $source['text'] ) ? $source['text'] : '' );

		if ( ! empty( $parsed['timestamp'] ) ) {
			return $parsed;
		}
	}

	return array();
}

function mdg_tdfix_parse_time_range( $text ) {
	$text = mdg_tdfix_normalize_space( $text );

	if ( preg_match_all( '/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b/u', $text, $matches, PREG_SET_ORDER ) ) {
		$start = mdg_tdfix_format_time( $matches[0][1], $matches[0][2] );
		$end   = '';

		if ( isset( $matches[1] ) ) {
			$end = mdg_tdfix_format_time( $matches[1][1], $matches[1][2] );
		}

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	if ( preg_match( '/(?:^|[-_\s])([01]\d|2[0-3])([0-5]\d)(?:[-_\s]|$)/u', $text, $matches ) ) {
		return array(
			'start' => mdg_tdfix_format_time( $matches[1], $matches[2] ),
			'end'   => '',
		);
	}

	return array();
}

function mdg_tdfix_parse_date_from_text( $text ) {
	$text = mdg_tdfix_normalize_space( $text );

	if ( preg_match( '/\b(20\d{2})[-.\/](0?[1-9]|1[0-2])[-.\/](0?[1-9]|[12]\d|3[01])\b/u', $text, $matches ) ) {
		return mdg_tdfix_make_date( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
	}

	if ( preg_match( '/\b(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\b/u', $text, $matches ) ) {
		return mdg_tdfix_make_date( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
	}

	if ( preg_match( '/\b(0?[1-9]|[12]\d|3[01])\s+([[:alpha:]ğüşöçıİĞÜŞÖÇ]+)\s+(20\d{2})\b/u', $text, $matches ) ) {
		$month = mdg_tdfix_month_number( $matches[2] );

		if ( $month ) {
			return mdg_tdfix_make_date( (int) $matches[3], $month, (int) $matches[1] );
		}
	}

	return array();
}

function mdg_tdfix_month_number( $month ) {
	$month = function_exists( 'remove_accents' ) ? remove_accents( $month ) : $month;
	$month = strtolower( $month );

	$map = array(
		'ocak'    => 1,
		'subat'   => 2,
		'mart'    => 3,
		'nisan'   => 4,
		'mayis'   => 5,
		'haziran' => 6,
		'temmuz'  => 7,
		'agustos' => 8,
		'eylul'   => 9,
		'ekim'    => 10,
		'kasim'   => 11,
		'aralik'  => 12,
	);

	return isset( $map[ $month ] ) ? (int) $map[ $month ] : 0;
}

function mdg_tdfix_make_date( $year, $month, $day ) {
	if ( ! checkdate( $month, $day, $year ) ) {
		return array();
	}

	$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Europe/Istanbul' );
	$date     = DateTimeImmutable::createFromFormat( '!Y-n-j', $year . '-' . $month . '-' . $day, $timezone );

	if ( ! $date ) {
		return array();
	}

	return array(
		'timestamp' => $date->getTimestamp(),
	);
}

function mdg_tdfix_format_date( $timestamp ) {
	$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Europe/Istanbul' );

	if ( function_exists( 'wp_date' ) ) {
		return wp_date( 'j F Y', $timestamp, $timezone );
	}

	$date = new DateTimeImmutable( '@' . $timestamp );
	$date = $date->setTimezone( $timezone );

	return $date->format( 'j F Y' );
}

function mdg_tdfix_extract_date_prefix( $text ) {
	$text = mdg_tdfix_normalize_space( $text );
	$text = preg_replace( '/\b([01]?\d|2[0-3])[:.]([0-5]\d)\b(?:\s*[-–—]\s*\b([01]?\d|2[0-3])[:.]([0-5]\d)\b)?/u', '', $text );
	$text = trim( preg_replace( '/\s+/', ' ', $text ) );

	return $text;
}

function mdg_tdfix_format_time( $hour, $minute ) {
	return sprintf( '%02d:%02d', (int) $hour, (int) $minute );
}

function mdg_tdfix_add_one_hour( $time ) {
	if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches ) ) {
		return '';
	}

	$hour   = ( (int) $matches[1] + 1 ) % 24;
	$minute = (int) $matches[2];

	return sprintf( '%02d:%02d', $hour, $minute );
}

function mdg_tdfix_normalize_space( $value ) {
	$value = wp_strip_all_tags( (string) $value );
	$value = str_replace( array( "\r", "\n", "\t", '–', '—' ), array( ' ', ' ', ' ', '-', '-' ), $value );
	$value = preg_replace( '/\s+/u', ' ', $value );

	return trim( $value );
}
