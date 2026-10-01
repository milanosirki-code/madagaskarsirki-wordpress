<?php
/**
 * Madagaskar AI — Controlled Legacy MDG → MMC Migration
 *
 * Creates MMC control records for an already-live legacy MDG event while
 * reusing the existing WooCommerce and Tickera sales objects.
 *
 * Each event is migrated in its own database transaction. Any structural,
 * mapping, bridge or sales-reconciliation failure rolls the event back.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function mdg_ai_legacy_migrate_can_run( $input = null ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return new WP_Error( 'mdg_ai_legacy_migrate_forbidden', 'WooCommerce yönetim yetkisi gerekir.' );
    }
    foreach ( array(
        'MMC_Program_Service',
        'MMC_Venue_Service',
        'MMC_Event_Service',
        'MMC_Sales_Service',
        'MMC_MDG_Bridge_Service',
        'MDG_DB',
    ) as $class ) {
        if ( ! class_exists( $class ) ) {
            return new WP_Error( 'mdg_ai_legacy_migrate_missing', $class . ' kullanılamıyor.' );
        }
    }
    if ( ! function_exists( 'mdg_ai_legacy_preview_event_row' ) ) {
        return new WP_Error( 'mdg_ai_legacy_migrate_preview_missing', 'Read-only legacy migration preview modülü etkin olmalıdır.' );
    }
    return true;
}

function mdg_ai_legacy_migrate_require( $value, $context ) {
    if ( is_wp_error( $value ) ) {
        throw new RuntimeException( $context . ': ' . $value->get_error_message() );
    }
    return $value;
}

function mdg_ai_legacy_migrate_ticket_code( $code ) {
    $raw = strtoupper( trim( (string) $code ) );
    $raw = strtr( $raw, array(
        'Ç'=>'C','Ğ'=>'G','İ'=>'I','Ö'=>'O','Ş'=>'S','Ü'=>'U',
        'ç'=>'C','ğ'=>'G','ı'=>'I','i'=>'I','ö'=>'O','ş'=>'S','ü'=>'U',
    ) );
    $key = strtolower( trim( preg_replace( '/[^A-Z0-9]+/', '_', $raw ), '_' ) );
    $aliases = array(
        'child'      => 'child',
        'cocuk'      => 'child',
        'adult'      => 'adult',
        'yetiskin'   => 'adult',
        'family_2_2' => 'family_2_2',
        'aile_2_2'   => 'family_2_2',
        'family22'   => 'family_2_2',
        'aile22'     => 'family_2_2',
    );
    return $aliases[ $key ] ?? '';
}

function mdg_ai_legacy_migrate_local_datetime( $utc_datetime ) {
    if ( class_exists( 'MDG_Sessions' ) && method_exists( 'MDG_Sessions', 'local_parts' ) ) {
        $parts = MDG_Sessions::local_parts( $utc_datetime );
        if ( ! empty( $parts[0] ) && ! empty( $parts[1] ) ) {
            return (string) $parts[0] . ' ' . substr( (string) $parts[1], 0, 5 );
        }
    }
    if ( function_exists( 'get_date_from_gmt' ) && $utc_datetime ) {
        return (string) get_date_from_gmt( (string) $utc_datetime, 'Y-m-d H:i' );
    }
    return $utc_datetime ? substr( (string) $utc_datetime, 0, 16 ) : '';
}

function mdg_ai_legacy_migrate_one_internal( $mdg_event_id ) {
    global $wpdb;

    $mdg_event_id = absint( $mdg_event_id );
    $legacy_event = MMC_MDG_Bridge_Service::get_mdg_event( $mdg_event_id );
    if ( ! $legacy_event ) {
        return new WP_Error( 'mdg_ai_legacy_event_missing', 'MDG etkinliği bulunamadı.' );
    }

    $preview = mdg_ai_legacy_preview_event_row( $legacy_event );
    if ( empty( $preview['safe_for_later_write'] ) || 'create_program_then_bridge' !== ( $preview['suggested_action'] ?? '' ) ) {
        return new WP_Error(
            'mdg_ai_legacy_preview_not_safe',
            'Preview bu etkinliği güvenli create_program_then_bridge adayı olarak işaretlemiyor.'
        );
    }
    if ( ! empty( $preview['warnings'] ) ) {
        return new WP_Error( 'mdg_ai_legacy_preview_warning', implode( ' ', (array) $preview['warnings'] ) );
    }
    if ( MMC_MDG_Bridge_Service::program_for_mdg_event( $mdg_event_id ) ) {
        return new WP_Error( 'mdg_ai_legacy_already_bridged', 'MDG etkinliği zaten bir MMC programına bağlı.' );
    }
    if ( ! empty( $preview['matching_mmc_programs'] ) ) {
        return new WP_Error( 'mdg_ai_legacy_program_conflict', 'Aynı il/ilçe/tarihte mevcut MMC programı bulundu.' );
    }

    $planned_date = (string) ( $preview['derived']['planned_date'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $planned_date ) || $planned_date < wp_date( 'Y-m-d' ) ) {
        return new WP_Error( 'mdg_ai_legacy_date_invalid', 'Geçmiş veya belirsiz tarihli etkinlik migrate edilmez.' );
    }

    $sessions_table = MDG_DB::table( 'sessions' );
    $types_table    = MDG_DB::table( 'ticket_types' );
    $order_map      = MDG_DB::table( 'order_map' );

    $legacy_sessions = (array) $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$sessions_table} WHERE event_id=%d ORDER BY start_at ASC,id ASC",
            $mdg_event_id
        )
    );
    if ( ! $legacy_sessions ) {
        return new WP_Error( 'mdg_ai_legacy_no_sessions', 'Legacy event seansları bulunamadı.' );
    }

    $legacy_types_by_session = array();
    $code_sets = array();
    $price_sets = array();

    foreach ( $legacy_sessions as $legacy_session ) {
        if ( ! (int) $legacy_session->wc_product_id || ! (int) $legacy_session->tickera_event_id ) {
            return new WP_Error( 'mdg_ai_legacy_sales_identity_missing', 'Legacy seans WooCommerce/Tickera kimliği eksik.' );
        }

        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$types_table} WHERE session_id=%d AND is_active=1 ORDER BY sort_order ASC,id ASC",
                (int) $legacy_session->id
            )
        );
        if ( ! $rows ) {
            return new WP_Error( 'mdg_ai_legacy_ticket_types_missing', 'Legacy seans aktif bilet türü içermiyor.' );
        }

        $set = array();
        foreach ( $rows as $row ) {
            $code = mdg_ai_legacy_migrate_ticket_code( $row->code ?? '' );
            if ( ! in_array( $code, array( 'child', 'adult', 'family_2_2' ), true ) ) {
                return new WP_Error( 'mdg_ai_legacy_unknown_ticket', 'Bilinmeyen aktif legacy bilet kodu: ' . (string) ( $row->code ?? '' ) );
            }
            if ( ! (int) $row->wc_variation_id ) {
                return new WP_Error( 'mdg_ai_legacy_variation_missing', 'Legacy bilet varyasyon ID eksik: ' . $code );
            }
            $set[$code] = true;
            $price_sets[$code][ number_format( (float) $row->price, 2, '.', '' ) ] = true;
        }
        ksort( $set );
        $code_sets[(int) $legacy_session->id] = array_keys( $set );
        $legacy_types_by_session[(int) $legacy_session->id] = $rows;
    }

    $reference_codes = reset( $code_sets );
    foreach ( $code_sets as $session_id => $codes ) {
        if ( $codes !== $reference_codes ) {
            return new WP_Error( 'mdg_ai_legacy_ticket_set_mismatch', 'Seanslar arasında aktif bilet kodu seti farklı.' );
        }
    }
    foreach ( $price_sets as $code => $prices ) {
        if ( count( $prices ) !== 1 ) {
            return new WP_Error( 'mdg_ai_legacy_price_mismatch', 'Seanslar arasında ' . $code . ' fiyatı farklı.' );
        }
    }

    $wpdb->query( 'START TRANSACTION' );

    try {
        $program_id = mdg_ai_legacy_migrate_require(
            MMC_Program_Service::create_program( array(
                'province_name' => (string) $legacy_event->province_name,
                'district_name' => (string) $legacy_event->district,
                'plan_year'     => (int) substr( $planned_date, 0, 4 ),
                'planned_date'  => $planned_date,
                'notes'         => 'Legacy MDG event #' . $mdg_event_id . ' kontrollü MMC migration.',
            ) ),
            'Program oluşturma'
        );

        $program_venue = mdg_ai_legacy_migrate_require(
            MMC_Venue_Service::quick_confirm_master_venue(
                (int) $program_id,
                (int) $legacy_event->venue_id,
                'mdg'
            ),
            'Legacy MDG salonunu programa bağlama'
        );

        $event = MMC_Event_Service::event_for_program( (int) $program_id );
        if ( ! $event ) {
            throw new RuntimeException( 'MMC etkinlik taslağı oluşturulamadı.' );
        }

        mdg_ai_legacy_migrate_require(
            MMC_Event_Service::save_event(
                (int) $event->id,
                array(
                    'event_title'       => (string) $legacy_event->title,
                    'event_date'        => $planned_date,
                    'seating_mode'      => 'free',
                    'door_open_minutes' => 30,
                    'notes'             => 'Legacy MDG event #' . $mdg_event_id . ' satış nesneleri yeniden kullanılacaktır.',
                )
            ),
            'MMC event güncelleme'
        );

        $mmc_session_by_local = array();
        foreach ( $legacy_sessions as $legacy_session ) {
            $local = mdg_ai_legacy_migrate_local_datetime( $legacy_session->start_at );
            $capacity = max( 1, (int) ( $preview['venue']['exists'] ? ( $preview['sessions'][0]['capacity'] ?? 0 ) : 0 ) );
            $session_id = mdg_ai_legacy_migrate_require(
                MMC_Event_Service::add_session(
                    (int) $event->id,
                    $local,
                    $capacity,
                    'Legacy MDG session #' . (int) $legacy_session->id
                ),
                'MMC seans oluşturma ' . $local
            );
            $mmc_session_by_local[$local] = MMC_Event_Service::sessions( (int) $event->id );
        }

        $mmc_sessions = MMC_Event_Service::sessions( (int) $event->id );
        $mmc_session_by_local = array();
        foreach ( $mmc_sessions as $session ) {
            $mmc_session_by_local[ substr( (string) $session->session_time, 0, 16 ) ] = $session;
        }

        $mmc_tickets = MMC_Event_Service::ticket_types( (int) $event->id );
        $mmc_ticket_by_code = array();
        foreach ( $mmc_tickets as $ticket ) {
            $mmc_ticket_by_code[ (string) $ticket->ticket_code ] = $ticket;
        }

        foreach ( array( 'child', 'adult', 'family_2_2' ) as $code ) {
            $ticket = $mmc_ticket_by_code[$code] ?? null;
            if ( ! $ticket ) {
                throw new RuntimeException( 'MMC bilet türü bulunamadı: ' . $code );
            }

            $present = in_array( $code, $reference_codes, true );
            $price = $present ? (float) array_key_first( $price_sets[$code] ) : (float) $ticket->price;
            mdg_ai_legacy_migrate_require(
                MMC_Event_Service::update_ticket_type(
                    (int) $ticket->id,
                    array(
                        'ticket_name'    => (string) $ticket->ticket_name,
                        'price'          => $price,
                        'capacity_units' => 'family_2_2' === $code ? 4 : 1,
                        'is_active'      => $present ? 1 : 0,
                    )
                ),
                'MMC bilet türü güncelleme ' . $code
            );

            if ( ! $present ) {
                MMC_Event_Service::update_channel_price( (int) $event->id, (int) $ticket->id, 'biletinial', (float) $ticket->price, 0 );
            }
        }

        mdg_ai_legacy_migrate_require(
            MMC_Event_Service::mark_sales_ready( (int) $event->id ),
            'Satış hazırlığı doğrulama'
        );

        $mmc_tickets = MMC_Event_Service::ticket_types( (int) $event->id );
        $mmc_ticket_by_code = array();
        foreach ( $mmc_tickets as $ticket ) {
            if ( (int) $ticket->is_active === 1 ) {
                $mmc_ticket_by_code[ (string) $ticket->ticket_code ] = $ticket;
            }
        }

        $mapping_count = 0;
        foreach ( $legacy_sessions as $legacy_session ) {
            $local = mdg_ai_legacy_migrate_local_datetime( $legacy_session->start_at );
            $mmc_session = $mmc_session_by_local[$local] ?? null;
            if ( ! $mmc_session ) {
                throw new RuntimeException( 'MMC seans eşleşmesi bulunamadı: ' . $local );
            }

            foreach ( $legacy_types_by_session[(int) $legacy_session->id] as $legacy_ticket ) {
                $code = mdg_ai_legacy_migrate_ticket_code( $legacy_ticket->code ?? '' );
                $mmc_ticket = $mmc_ticket_by_code[$code] ?? null;
                if ( ! $mmc_ticket ) {
                    throw new RuntimeException( 'MMC bilet türü eşleşmesi bulunamadı: ' . $code );
                }

                mdg_ai_legacy_migrate_require(
                    MMC_Sales_Service::save_mapping(
                        (int) $event->id,
                        (int) $mmc_session->id,
                        (int) $mmc_ticket->id,
                        array(
                            'wc_product_id'          => (int) $legacy_session->wc_product_id,
                            'wc_variation_id'        => (int) $legacy_ticket->wc_variation_id,
                            'tickera_event_id'       => (int) $legacy_session->tickera_event_id,
                            'tickera_ticket_type_id' => 0,
                            'is_active'              => 1,
                        )
                    ),
                    'Legacy satış mapping ' . $local . ' / ' . $code
                );
                $mapping_count++;
            }
        }

        $coverage = MMC_Sales_Service::mapping_coverage( (int) $event->id );
        if ( empty( $coverage['complete'] ) ) {
            throw new RuntimeException(
                'Mapping coverage tamamlanmadı: ' . (int) $coverage['mapped'] . '/' . (int) $coverage['required']
            );
        }

        mdg_ai_legacy_migrate_require(
            MMC_MDG_Bridge_Service::link(
                (int) $program_id,
                $mdg_event_id,
                'legacy_migration',
                100
            ),
            'MMC↔MDG bridge'
        );

        $order_ids = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT order_id FROM {$order_map} WHERE event_id=%d ORDER BY order_id ASC",
                $mdg_event_id
            )
        );
        $orders_synced = 0;
        foreach ( $order_ids as $order_id ) {
            if ( MMC_Sales_Service::sync_order( (int) $order_id ) ) {
                $orders_synced++;
            }
        }
        MMC_Sales_Service::refresh_integration_health( (int) $event->id );

        $current_program = MMC_Program_Service::get_program( (int) $program_id );
        if ( $current_program && 'sales_open' !== $current_program->status ) {
            mdg_ai_legacy_migrate_require(
                MMC_Program_Service::set_status(
                    (int) $program_id,
                    'sales_prep',
                    'Legacy MDG etkinliği MMC kontrol zincirine alındı; mevcut canlı satış nesneleri yeniden kullanılıyor.'
                ),
                'Program satış hazırlık durumu'
            );
        }

        $status = MMC_MDG_Bridge_Service::status( (int) $program_id );
        if ( empty( $status['linked'] ) || ! empty( $status['stale'] ) ) {
            throw new RuntimeException( 'Bridge doğrulaması başarısız.' );
        }
        foreach ( array( 'province_match', 'district_match', 'date_match', 'venue_match', 'session_time_match' ) as $key ) {
            if ( empty( $status[$key] ) ) {
                throw new RuntimeException( 'Bridge yapısal doğrulaması başarısız: ' . $key );
            }
        }
        if ( isset( $status['identity_expected'], $status['identity_matched'] )
            && (int) $status['identity_expected'] !== (int) $status['identity_matched'] ) {
            throw new RuntimeException(
                'Satış kimliği eşleşmesi eksik: ' . (int) $status['identity_matched'] . '/' . (int) $status['identity_expected']
            );
        }

        $sales = (array) ( $status['sales'] ?? array() );
        if ( ! empty( $sales['has_sales'] ) && empty( $sales['ok'] ) ) {
            throw new RuntimeException(
                'MDG↔MMC satış mutabakatı başarısız. Eksik MMC kalemi: ' . count( (array) ( $sales['missing_in_mmc'] ?? array() ) )
            );
        }

        $summary = MMC_Sales_Service::summary( (int) $event->id );
        $final_program = MMC_Program_Service::get_program( (int) $program_id );

        $wpdb->query( 'COMMIT' );

        return array(
            'ok' => true,
            'mdg_event_id' => $mdg_event_id,
            'program_id' => (int) $program_id,
            'program_code' => (string) ( $final_program->program_code ?? '' ),
            'program_status' => (string) ( $final_program->status ?? '' ),
            'event_id' => (int) $event->id,
            'program_venue_id' => (int) ( $program_venue->id ?? 0 ),
            'sessions' => count( $mmc_sessions ),
            'active_ticket_codes' => array_values( $reference_codes ),
            'mappings' => $mapping_count,
            'coverage' => $coverage,
            'orders_found' => count( $order_ids ),
            'orders_synced' => $orders_synced,
            'sales_summary' => $summary,
            'bridge' => array(
                'linked' => ! empty( $status['linked'] ),
                'stale' => ! empty( $status['stale'] ),
                'identity_expected' => (int) ( $status['identity_expected'] ?? 0 ),
                'identity_matched' => (int) ( $status['identity_matched'] ?? 0 ),
                'session_time_match' => ! empty( $status['session_time_match'] ),
                'sales' => $sales,
            ),
        );

    } catch ( Throwable $e ) {
        $wpdb->query( 'ROLLBACK' );
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        return new WP_Error( 'mdg_ai_legacy_migrate_failed', $e->getMessage() );
    }
}

function mdg_ai_legacy_migrate_one( $input ) {
    $confirmation = (string) ( $input['confirmation'] ?? '' );
    if ( 'MIGRATE_LEGACY_MDG_TO_MMC' !== $confirmation ) {
        return new WP_Error( 'mdg_ai_legacy_confirmation', 'Açık migration onayı eksik.' );
    }
    return mdg_ai_legacy_migrate_one_internal( absint( $input['mdg_event_id'] ?? 0 ) );
}

function mdg_ai_legacy_migrate_batch( $input ) {
    $confirmation = (string) ( $input['confirmation'] ?? '' );
    if ( 'MIGRATE_LEGACY_MDG_TO_MMC' !== $confirmation ) {
        return new WP_Error( 'mdg_ai_legacy_confirmation', 'Açık migration onayı eksik.' );
    }

    $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $input['mdg_event_ids'] ?? array() ) ) ) ) );
    if ( ! $ids || count( $ids ) > 10 ) {
        return new WP_Error( 'mdg_ai_legacy_batch_ids', '1–10 arası MDG event ID gerekir.' );
    }

    $results = array();
    foreach ( $ids as $id ) {
        $result = mdg_ai_legacy_migrate_one_internal( $id );
        if ( is_wp_error( $result ) ) {
            $results[] = array(
                'mdg_event_id' => $id,
                'ok' => false,
                'error' => $result->get_error_message(),
            );
            if ( ! empty( $input['stop_on_error'] ) ) {
                break;
            }
            continue;
        }
        $results[] = $result;
    }

    return array(
        'requested' => $ids,
        'completed' => count( array_filter( $results, static function( $row ) { return ! empty( $row['ok'] ); } ) ),
        'failed' => count( array_filter( $results, static function( $row ) { return empty( $row['ok'] ); } ) ),
        'results' => $results,
    );
}

add_action( 'wp_abilities_api_init', function() {
    if ( ! function_exists( 'wp_register_ability' ) ) { return; }

    $write_meta = array(
        'annotations' => array(
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ),
        'public' => true,
    );

    wp_register_ability( 'madagaskar/legacy-mdg-mmc-migrate-one', array(
        'label' => 'Tek Legacy MDG Etkinliğini MMC’ye Taşı',
        'description' => 'Read-only preview ile güvenli bulunan tek legacy MDG etkinliği transaction içinde MMC program/salon/seans/satış mapping/bridge zincirine taşır; mevcut WooCommerce/Tickera satış nesnelerini yeniden kullanır.',
        'category' => 'madagaskar-legacy-migration-preview',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'mdg_event_id' => array( 'type'=>'integer', 'minimum'=>1 ),
                'confirmation' => array( 'type'=>'string' ),
            ),
            'required' => array( 'mdg_event_id', 'confirmation' ),
        ),
        'output_schema' => array( 'type'=>'object' ),
        'execute_callback' => 'mdg_ai_legacy_migrate_one',
        'permission_callback' => 'mdg_ai_legacy_migrate_can_run',
        'meta' => $write_meta,
    ) );

    wp_register_ability( 'madagaskar/legacy-mdg-mmc-migrate-batch', array(
        'label' => 'Legacy MDG Etkinliklerini Kontrollü MMC’ye Taşı',
        'description' => 'Her MDG event için ayrı transaction/rollback kullanarak en fazla 10 doğrulanmış legacy etkinliği MMC’ye taşır.',
        'category' => 'madagaskar-legacy-migration-preview',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'mdg_event_ids' => array(
                    'type'=>'array',
                    'items'=>array( 'type'=>'integer', 'minimum'=>1 ),
                    'minItems'=>1,
                    'maxItems'=>10,
                ),
                'confirmation' => array( 'type'=>'string' ),
                'stop_on_error' => array( 'type'=>'boolean' ),
            ),
            'required' => array( 'mdg_event_ids', 'confirmation' ),
        ),
        'output_schema' => array( 'type'=>'object' ),
        'execute_callback' => 'mdg_ai_legacy_migrate_batch',
        'permission_callback' => 'mdg_ai_legacy_migrate_can_run',
        'meta' => $write_meta,
    ) );
} );
