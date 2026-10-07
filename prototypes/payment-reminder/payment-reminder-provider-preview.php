<?php
/**
 * SOURCE-ONLY PROTOTYPE — DO NOT DEPLOY
 *
 * Issue #186 provider/template readiness preview.
 *
 * This file does not call Kommo, WhatsApp, Meta or any external provider.
 * SEND is intentionally unavailable.
 */

if ( ! function_exists( 'mdg_payment_reminder_template_draft' ) ) {
    function mdg_payment_reminder_template_draft() {
        return array(
            'template_key'     => 'madagaskar_odeme_hatirlatma_v1',
            'template_status'  => 'draft_unverified',
            'template_version' => 'DRAFT-1',
            'body'             => "Madagaskar Sirki\nMerhaba, bilet siparişinizin ödemesi tamamlanmamış görünüyor.\nGösteri ve seansınız hâlâ satışa açıksa ödemenizi güvenli ödeme bağlantınız üzerinden tamamlayabilirsiniz.\n\nÖdeme bağlantısı: {{PAYMENT_LINK}}\n\nÖdeme yaptıysanız bu mesajı dikkate almayınız.\nBilgi ve destek: WhatsApp +90 312 911 37 10",
        );
    }
}

if ( ! function_exists( 'mdg_payment_reminder_provider_readiness' ) ) {
    function mdg_payment_reminder_provider_readiness( $evidence = array() ) {
        $evidence = is_array( $evidence ) ? $evidence : array();

        $template_verified = ! empty( $evidence['template_verified'] );
        $messaging_adapter = ! empty( $evidence['messaging_adapter_verified'] );
        $provider_name     = sanitize_key( (string) ( $evidence['provider_name'] ?? 'kommo_whatsapp_candidate' ) );

        $reason = '';
        if ( ! $template_verified ) {
            $reason = 'NO_VERIFIED_PAYMENT_REMINDER_TEMPLATE';
        } elseif ( ! $messaging_adapter ) {
            $reason = 'NO_VERIFIED_MESSAGING_ADAPTER';
        }

        return array(
            'provider_candidate'          => $provider_name ?: 'kommo_whatsapp_candidate',
            'provider_ready'              => $template_verified && $messaging_adapter,
            'provider_readiness_reason'   => $reason,
            'template_verified'           => $template_verified,
            'messaging_adapter_verified'  => $messaging_adapter,
            'send_enabled'                => false,
            'mode'                        => 'PREVIEW',
            'real_send_count'             => 0,
        );
    }
}

if ( ! function_exists( 'mdg_payment_reminder_message_preview' ) ) {
    function mdg_payment_reminder_message_preview( $payment_link_present, $provider_evidence = array() ) {
        $template = mdg_payment_reminder_template_draft();
        $provider = mdg_payment_reminder_provider_readiness( $provider_evidence );

        $body = str_replace(
            '{{PAYMENT_LINK}}',
            $payment_link_present ? '[GÜVENLİ ÖDEME BAĞLANTISI VAR — PREVIEW URL GÖSTERMEZ]' : '[ÖDEME BAĞLANTISI YOK]',
            $template['body']
        );

        return array(
            'template_key'               => $template['template_key'],
            'template_status'            => $template['template_status'],
            'template_version'           => $template['template_version'],
            'message_preview'            => $body,
            'payment_link_present'       => (bool) $payment_link_present,
            'provider_candidate'         => $provider['provider_candidate'],
            'provider_ready'             => $provider['provider_ready'],
            'provider_readiness_reason'  => $provider['provider_readiness_reason'],
            'send_enabled'               => false,
            'mode'                       => 'PREVIEW',
            'real_send_count'            => 0,
        );
    }
}
