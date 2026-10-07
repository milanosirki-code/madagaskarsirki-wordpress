<?php
/**
 * SOURCE-ONLY REPLACEMENT EXCERPT for live Code Snippets #42.
 *
 * Goal: remove the corporate flow's dependency on legacy MS_KOMMO_TOKEN.
 * Base URL remains MS_KOMMO_BASE_URL because current production Kommo
 * configuration still reports that non-secret value as its subdomain source.
 *
 * DO NOT DEPLOY THIS FILE BY ITSELF.
 */

if ( ! function_exists( 'mdg_corp_kommo_token_v3' ) ) {
    function mdg_corp_kommo_token_v3() {
        if ( ! defined( 'MMC_KOMMO_TOKEN' ) ) {
            return '';
        }

        $token = trim( (string) MMC_KOMMO_TOKEN );

        if (
            '' === $token ||
            'YENI_KOMMO_TOKENINI_BURAYA_YAPISTIR' === $token ||
            'KOMMO_TOKEN_DEGERINI_BURAYA_YAPISTIR' === $token
        ) {
            return '';
        }

        return $token;
    }
}

if ( ! function_exists( 'mdg_corp_kommo_ready_v3' ) ) {
    function mdg_corp_kommo_ready_v3() {
        return (
            '' !== mdg_corp_kommo_token_v3() &&
            defined( 'MS_KOMMO_BASE_URL' ) &&
            '' !== trim( (string) MS_KOMMO_BASE_URL )
        );
    }
}

/**
 * Inside mdg_corp_kommo_request_v3(), replace the legacy Authorization value
 * with this exact dynamic value:
 *
 * 'Authorization' => 'Bearer ' . mdg_corp_kommo_token_v3(),
 */
