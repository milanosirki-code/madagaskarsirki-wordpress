<?php
/**
 * SOURCE-ONLY PROTOTYPE — DO NOT DEPLOY
 *
 * Issue #186 canary/idempotency contract.
 * No provider call, no order mutation, no SEND.
 */

if ( ! function_exists( 'mdg_payment_reminder_stable_ids' ) ) {
    function mdg_payment_reminder_stable_ids( $values ) {
        $values = array_values( array_unique( array_map( 'intval', (array) $values ) ) );
        sort( $values, SORT_NUMERIC );
        return $values;
    }
}

if ( ! function_exists( 'mdg_payment_reminder_idempotency_key' ) ) {
    function mdg_payment_reminder_idempotency_key( $order_id, $event_ids, $template_key, $template_version ) {
        $material = array(
            'order_id'         => (int) $order_id,
            'event_ids'        => mdg_payment_reminder_stable_ids( $event_ids ),
            'template_key'     => sanitize_key( (string) $template_key ),
            'template_version' => sanitize_key( (string) $template_version ),
        );

        return hash( 'sha256', wp_json_encode( $material ) );
    }
}

if ( ! function_exists( 'mdg_payment_reminder_snapshot_hash' ) ) {
    function mdg_payment_reminder_snapshot_hash( $preview ) {
        $preview = is_array( $preview ) ? $preview : array();

        $material = array(
            'order_id'             => (int) ( $preview['order_id'] ?? 0 ),
            'event_ids'            => mdg_payment_reminder_stable_ids( $preview['event_ids'] ?? array() ),
            'session_ids'          => mdg_payment_reminder_stable_ids( $preview['session_ids'] ?? array() ),
            'program_ids'          => mdg_payment_reminder_stable_ids( $preview['program_ids'] ?? array() ),
            'eligibility'          => (string) ( $preview['eligibility'] ?? 'EXCLUDED' ),
            'exclusion_reason'     => (string) ( $preview['exclusion_reason'] ?? '' ),
            'payment_link_present' => ! empty( $preview['payment_link_present'] ),
            'replacement_paid'     => $preview['replacement_paid'] ?? null,
            'already_reminded'     => ! empty( $preview['already_reminded'] ),
            'template_version'     => (string) ( $preview['template_version'] ?? '' ),
        );

        return hash( 'sha256', wp_json_encode( $material ) );
    }
}

if ( ! function_exists( 'mdg_payment_reminder_canary_preview' ) ) {
    function mdg_payment_reminder_canary_preview( $preview, $provider ) {
        $preview  = is_array( $preview ) ? $preview : array();
        $provider = is_array( $provider ) ? $provider : array();

        $eligible       = ( $preview['eligibility'] ?? '' ) === 'ELIGIBLE';
        $payment_ready  = ! empty( $preview['payment_link_present'] );
        $provider_ready = ! empty( $provider['provider_ready'] );
        $already_sent   = ! empty( $preview['already_reminded'] );

        $reason = '';
        if ( ! $eligible ) {
            $reason = 'NOT_ELIGIBLE';
        } elseif ( ! $payment_ready ) {
            $reason = 'PAYMENT_LINK_NOT_READY';
        } elseif ( $already_sent ) {
            $reason = 'ALREADY_REMinded';
        } elseif ( ! $provider_ready ) {
            $reason = 'PROVIDER_NOT_READY';
        }

        $template_key     = sanitize_key( (string) ( $preview['template_key'] ?? 'madagaskar_odeme_hatirlatma_v1' ) );
        $template_version = sanitize_key( (string) ( $preview['template_version'] ?? 'draft-1' ) );

        return array(
            'canary_ready_for_owner_review' => '' === $reason,
            'canary_block_reason'           => $reason,
            'owner_approval_required'       => true,
            'send_enabled'                  => false,
            'mode'                          => 'CANARY_PREVIEW',
            'real_send_count'               => 0,
            'idempotency_key'               => mdg_payment_reminder_idempotency_key(
                (int) ( $preview['order_id'] ?? 0 ),
                $preview['event_ids'] ?? array(),
                $template_key,
                $template_version
            ),
            'eligibility_snapshot_hash'     => mdg_payment_reminder_snapshot_hash( $preview ),
            'receipt_storage_proposal'      => 'woocommerce_order_meta_after_provider_acceptance',
            'receipt_meta_keys'             => array(
                '_ms_oh_reminder_sent_at',
                '_ms_oh_reminder_provider',
                '_ms_oh_reminder_provider_message_id',
                '_ms_oh_reminder_template_version',
                '_ms_oh_reminder_idempotency_key',
                '_ms_oh_reminder_snapshot_hash',
            ),
        );
    }
}
