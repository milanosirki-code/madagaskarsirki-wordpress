<?php

/**
 * Madagaskar Sirki
 * Ana sayfa, Şehirler ve Ankara için SEO başlık/açıklamaları.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sayfaya göre SEO bilgilerini döndürür.
 */
function ms_turne_seo_bilgileri() {
    if ( is_front_page() ) {
        return array(
            'title'       => 'Madagaskar Sirki 2026 Türkiye Turnesi ve Biletleri',
            'description' => 'Madagaskar Sirki 2026–2027 Türkiye turnesi, gösteri tarihleri ve online bilet seçenekleri. Akrobasi ve renkli aile gösterileri için yerinizi ayırın.',
        );
    }

    if ( is_page( 1311 ) ) {
        return array(
            'title'       => '2026 Türkiye Turnesi ve Şehirler | Madagaskar Sirki',
            'description' => 'Madagaskar Sirki 2026–2027 Türkiye turnesi şehirlerini ve kesinleşen gösteri tarihlerini inceleyin. Güncel salon, tarih ve seans bilgilerine buradan ulaşın.',
        );
    }

    if ( is_page( 37 ) ) {
        return array(
            'title'       => 'Ankara Sirk Gösterileri ve Güncel Biletler | Madagaskar Sirki',
            'description' => 'Madagaskar Sirki Ankara gösterilerinin güncel tarih, salon ve seans bilgilerini inceleyin. Hayvansız, ailelere uygun canlı gösteriler için bilet seçeneklerini keşfedin.',
        );
    }

    return array();
}

/**
 * Tarayıcı ve Google başlığı.
 */
add_filter( 'pre_get_document_title', function ( $title ) {
    $seo = ms_turne_seo_bilgileri();

    if ( ! empty( $seo['title'] ) ) {
        return $seo['title'];
    }

    return $title;
}, 99 );

/**
 * Jetpack SEO açıklaması.
 */
add_filter( 'jetpack_seo_meta_tags', function ( $tags ) {
    $seo = ms_turne_seo_bilgileri();

    if ( ! empty( $seo['description'] ) ) {
        $tags['description'] = $seo['description'];
    }

    return $tags;
}, 99 );

/**
 * Facebook, WhatsApp ve sosyal medya ön izleme bilgileri.
 */
add_filter( 'jetpack_open_graph_tags', function ( $tags ) {
    $seo = ms_turne_seo_bilgileri();

    if ( ! empty( $seo['title'] ) ) {
        $tags['og:title'] = $seo['title'];
    }

    if ( ! empty( $seo['description'] ) ) {
        $tags['og:description'] = $seo['description'];
    }

    return $tags;
}, 99 );

/**
 * Değişiklik sonrası önbelleği bir defa temizler.
 */
add_action( 'init', function () {
    $version = 'ms_turne_seo_v2';

    if ( get_option( $version ) ) {
        return;
    }

    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }

    update_option( $version, current_time( 'mysql' ), false );
}, 99 );