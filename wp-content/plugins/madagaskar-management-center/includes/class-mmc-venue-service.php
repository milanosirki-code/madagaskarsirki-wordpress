<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Venue_Service {
    public static function allocation_statuses() {
        return array(
            'draft'     => 'Taslak',
            'prepared'  => 'Dilekçe Hazır',
            'sent'      => 'Gönderildi',
            'pending'   => 'Cevap Bekleniyor',
            'approved'  => 'Onaylandı',
            'rejected'  => 'Reddedildi',
            'cancelled' => 'İptal',
        );
    }

    public static function primary_source() {
        return self::mdg_available() ? 'mdg' : 'legacy';
    }

    public static function mdg_available() {
        return class_exists( 'MDG_Venues' );
    }

    /**
     * Salon ana kaydının tek kaynağı Madagaskar Bilet Yönetimi > Salonlar'dır.
     * MDG sınıfı kullanılamazsa eski MMC salon tablosu yalnız geriye uyumluluk için okunur.
     */
    public static function add_venue( $data ) {
        if ( self::mdg_available() ) {
            return new WP_Error(
                'mmc_venue_use_mdg',
                'Yeni salonu Madagaskar → Salonlar ekranından ekleyin. MMC ikinci bir salon ana kaydı oluşturmaz.'
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mmc_venues';
        $name     = sanitize_text_field( $data['venue_name'] ?? '' );
        $province = sanitize_text_field( $data['province_name'] ?? '' );
        $district = sanitize_text_field( $data['district_name'] ?? '' );
        if ( ! $name || ! $province ) {
            return new WP_Error( 'mmc_venue_missing', 'Salon adı ve il zorunludur.' );
        }
        $now = current_time( 'mysql' );
        $ok  = $wpdb->insert(
            $table,
            array(
                'venue_name'        => $name,
                'province_name'     => $province,
                'district_name'     => $district,
                'institution_name'  => sanitize_text_field( $data['institution_name'] ?? '' ),
                'address'           => sanitize_textarea_field( $data['address'] ?? '' ),
                'maps_url'          => esc_url_raw( $data['maps_url'] ?? '' ),
                'default_capacity'  => absint( $data['default_capacity'] ?? 0 ) ?: null,
                'contact_name'      => sanitize_text_field( $data['contact_name'] ?? '' ),
                'contact_phone'     => sanitize_text_field( $data['contact_phone'] ?? '' ),
                'email'             => sanitize_email( $data['email'] ?? '' ),
                'operation_notes'   => sanitize_textarea_field( $data['operation_notes'] ?? '' ),
                'is_active'         => 1,
                'created_by'        => get_current_user_id() ?: null,
                'created_at'        => $now,
                'updated_at'        => $now,
            )
        );
        return false === $ok ? new WP_Error( 'mmc_venue_insert', 'Salon kaydı oluşturulamadı.' ) : (int) $wpdb->insert_id;
    }

    public static function all_venues() {
        if ( self::mdg_available() ) {
            $out = array();
            foreach ( (array) MDG_Venues::all( true ) as $venue ) {
                $out[] = self::adapt_mdg_venue( $venue );
            }
            usort( $out, function( $a, $b ) {
                return strnatcasecmp(
                    $a->province_name . '|' . $a->district_name . '|' . $a->venue_name,
                    $b->province_name . '|' . $b->district_name . '|' . $b->venue_name
                );
            } );
            return $out;
        }
        return self::legacy_venues();
    }

    public static function venues_for_program( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_program_venues';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE program_id=%d ORDER BY is_selected DESC, priority_order ASC, id ASC",
                absint( $program_id )
            )
        );
        $out = array();
        foreach ( (array) $rows as $row ) {
            $hydrated = self::hydrate_program_venue( $row );
            if ( $hydrated ) { $out[] = $hydrated; }
        }
        return $out;
    }

    public static function add_program_venue( $program_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_program_venues';
        $venue_id = absint( $data['venue_id'] ?? 0 );
        $program_id = absint( $program_id );
        $source = sanitize_key( $data['venue_source'] ?? self::primary_source() );
        if ( ! in_array( $source, array( 'mdg', 'legacy' ), true ) ) { $source = self::primary_source(); }

        if ( ! $program_id || ! $venue_id ) {
            return new WP_Error( 'mmc_program_venue_missing', 'Program ve salon seçimi zorunludur.' );
        }
        if ( ! self::master_venue( $venue_id, $source ) ) {
            return new WP_Error( 'mmc_program_venue_invalid', 'Seçilen salon ana kayıtta bulunamadı.' );
        }

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE program_id=%d AND venue_id=%d AND venue_source=%s LIMIT 1",
            $program_id, $venue_id, $source
        ) );
        if ( $exists ) {
            return new WP_Error( 'mmc_program_venue_exists', 'Bu salon zaten programın alternatifleri arasında.' );
        }

        $now = current_time( 'mysql' );
        $ok = $wpdb->insert(
            $table,
            array(
                'program_id'         => $program_id,
                'venue_id'           => $venue_id,
                'venue_source'       => $source,
                'priority_order'     => max( 1, absint( $data['priority_order'] ?? 1 ) ),
                'requested_date'     => self::date_or_null( $data['requested_date'] ?? '' ),
                'alternative_dates'  => sanitize_textarea_field( $data['alternative_dates'] ?? '' ),
                'requested_sessions' => sanitize_text_field( $data['requested_sessions'] ?? '' ),
                'allocation_status'  => 'draft',
                'target_institution' => sanitize_text_field( $data['target_institution'] ?? '' ),
                'rental_amount'      => self::money( $data['rental_amount'] ?? 0 ),
                'deposit_amount'     => self::money( $data['deposit_amount'] ?? 0 ),
                'payment_due_date'   => self::date_or_null( $data['payment_due_date'] ?? '' ),
                'notes'              => sanitize_textarea_field( $data['notes'] ?? '' ),
                'created_by'         => get_current_user_id() ?: null,
                'created_at'         => $now,
                'updated_at'         => $now,
            )
        );
        if ( false === $ok ) {
            return new WP_Error( 'mmc_program_venue_insert', 'Salon program dosyasına eklenemedi.' );
        }

        $id = (int) $wpdb->insert_id;
        MMC_Program_Service::add_log( $program_id, 'venue_candidate_added', 'program_venue', $id, null, array( 'venue_id' => $venue_id, 'venue_source' => $source ), 'Salon alternatifi eklendi.' );
        if ( empty($data['preserve_program_status']) ) {
            MMC_Program_Service::set_status( $program_id, 'venue_research', 'Salon araştırması başladı.' );
        }
        return $id;
    }

    /**
     * Satış Hazırlığı gibi hızlı akışlardan mevcut ana salon kaydını
     * programa ekler (gerekirse) ve tek işlemde kesin salon yapar.
     * Yeni salon ana kaydı oluşturmaz.
     */
    public static function quick_confirm_master_venue( $program_id, $venue_id, $venue_source = '' ) {
        global $wpdb;

        $program_id = absint( $program_id );
        $venue_id   = absint( $venue_id );
        $source     = sanitize_key( $venue_source );

        $program = class_exists( 'MMC_Program_Service' ) ? MMC_Program_Service::get_program( $program_id ) : null;
        if ( ! $program || ! $venue_id ) {
            return new WP_Error( 'mmc_quick_venue_missing', 'Program ve salon seçimi zorunludur.' );
        }

        if ( ! in_array( $source, array( 'mdg', 'legacy' ), true ) ) {
            $source = self::primary_source();
        }

        $venue = self::master_venue( $venue_id, $source );
        if ( ! $venue ) {
            return new WP_Error( 'mmc_quick_venue_invalid', 'Seçilen salon ana kayıtta bulunamadı.' );
        }
        if ( self::place_key($venue->province_name) !== self::place_key($program->province_name) ) {
            return new WP_Error('mmc_quick_venue_province','Salonun ili programın iliyle uyuşmuyor.');
        }

        $table = $wpdb->prefix . 'mmc_program_venues';
        $program_venue_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE program_id=%d AND venue_id=%d AND venue_source=%s ORDER BY id ASC LIMIT 1",
            $program_id,
            $venue_id,
            $source
        ) );
        $existing_event = class_exists('MMC_Event_Service') ? MMC_Event_Service::event_for_program($program_id) : null;
        if ($existing_event && in_array((string)$existing_event->status,array('sales_open','closed'),true)
            && (int)$existing_event->program_venue_id !== $program_venue_id) {
            return new WP_Error('mmc_quick_venue_live','Satışa açılmış etkinliğin salonu otomatik değiştirilemez. Önce satış bağlantılarını kontrol edin.');
        }

        if ( ! $program_venue_id ) {
            $event = class_exists( 'MMC_Event_Service' ) ? MMC_Event_Service::event_for_program( $program_id ) : null;
            $date  = $event && ! empty( $event->event_date ) ? $event->event_date : $program->planned_date;

            $created = self::add_program_venue(
                $program_id,
                array(
                    'venue_id'       => $venue_id,
                    'venue_source'   => $source,
                    'priority_order' => 1,
                    'requested_date' => $date,
                    'notes'          => 'Satış Hazırlığı ekranından hızlı salon bağlantısı.',
                    'preserve_program_status' => true,
                )
            );

            if ( is_wp_error( $created ) ) {
                return $created;
            }

            $program_venue_id = (int) $created;
        }

        // Hızlı bağlantı, program yaşam döngüsünü geriye çekmez.
        // Yalnız seçili/kesin salon ilişkisini kurar ve mevcut MMC etkinliğine bağlar.
        $now = current_time( 'mysql' );
        $wpdb->update(
            $table,
            array( 'is_selected' => 0, 'updated_at' => $now ),
            array( 'program_id' => $program_id )
        );

        $current = self::get_program_venue( $program_venue_id );
        $wpdb->update(
            $table,
            array(
                'is_selected'       => 1,
                'allocation_status' => 'approved',
                'response_at'       => $current && ! empty( $current->response_at ) ? $current->response_at : $now,
                'updated_at'        => $now,
            ),
            array( 'id' => $program_venue_id )
        );

        if ( class_exists( 'MMC_Event_Service' ) ) {
            $event_result = MMC_Event_Service::ensure_event_for_program( $program_id, $program_venue_id );
            if ( is_wp_error( $event_result ) ) {
                return $event_result;
            }
        }

        if ( class_exists( 'MMC_Program_Service' ) ) {
            MMC_Program_Service::add_log(
                $program_id,
                'venue_quick_linked',
                'program_venue',
                $program_venue_id,
                null,
                array(
                    'venue_id'     => $venue_id,
                    'venue_source' => $source,
                    'venue_name'   => $venue->venue_name,
                ),
                'Satış Hazırlığı ekranından kesin salon bağlantısı kuruldu; program durumu korunmuştur.'
            );
        }

        return self::get_program_venue( $program_venue_id );
    }

    /**
     * Programın iline uyan salonları döndürür.
     * Aynı ilçe eşleşmeleri ilk sırada gelir; böylece hızlı seçim ekranı
     * yüzlerce salon yerine önce ilgili adayları gösterir.
     */
    public static function venue_candidates_for_program( $program_id ) {
        $program = class_exists( 'MMC_Program_Service' ) ? MMC_Program_Service::get_program( absint( $program_id ) ) : null;
        if ( ! $program ) {
            return array();
        }

        $province_key = self::place_key( $program->province_name );
        $district_key = self::place_key( $program->district_name );
        $rows = array();

        foreach ( (array) self::all_venues() as $venue ) {
            if ( $province_key && self::place_key( $venue->province_name ) !== $province_key ) {
                continue;
            }

            $venue->mmc_exact_district = $district_key && self::place_key( $venue->district_name ) === $district_key;
            $rows[] = $venue;
        }

        usort( $rows, function( $a, $b ) {
            $a_exact = ! empty( $a->mmc_exact_district ) ? 1 : 0;
            $b_exact = ! empty( $b->mmc_exact_district ) ? 1 : 0;
            if ( $a_exact !== $b_exact ) {
                return $a_exact > $b_exact ? -1 : 1;
            }

            return strnatcasecmp(
                (string) $a->district_name . '|' . (string) $a->venue_name,
                (string) $b->district_name . '|' . (string) $b->venue_name
            );
        } );

        return $rows;
    }

    public static function get_program_venue( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_program_venues';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d LIMIT 1", absint( $id ) ) );
        return $row ? self::hydrate_program_venue( $row ) : null;
    }

    private static function hydrate_program_venue( $row ) {
        if ( ! $row ) { return null; }
        $source = ! empty( $row->venue_source ) ? sanitize_key( $row->venue_source ) : 'legacy';
        $venue = self::master_venue( (int) $row->venue_id, $source );
        if ( ! $venue ) {
            // Geriye uyumluluk: eski satırlarda kaynak işareti olmayabilir.
            $venue = self::master_venue( (int) $row->venue_id, 'mdg' );
            if ( ! $venue ) { $venue = self::master_venue( (int) $row->venue_id, 'legacy' ); }
        }
        if ( ! $venue ) { return null; }
        foreach ( get_object_vars( $venue ) as $key => $value ) {
            if ( ! property_exists( $row, $key ) ) { $row->{$key} = $value; }
        }
        $row->venue_source = $source;
        return $row;
    }

    private static function master_venue( $venue_id, $source ) {
        if ( 'mdg' === $source && self::mdg_available() ) {
            $venue = MDG_Venues::get( absint( $venue_id ) );
            return $venue ? self::adapt_mdg_venue( $venue ) : null;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_venues';
        $venue = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d AND is_active=1 LIMIT 1", absint( $venue_id ) ) );
        if ( $venue ) { $venue->venue_source = 'legacy'; }
        return $venue;
    }

    private static function legacy_venues() {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_venues';
        $rows = $wpdb->get_results( "SELECT * FROM $table WHERE is_active=1 ORDER BY province_name, district_name, venue_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        foreach ( (array) $rows as $row ) { $row->venue_source = 'legacy'; }
        return $rows;
    }

    private static function adapt_mdg_venue( $venue ) {
        $out = new stdClass();
        $out->id                = (int) $venue->id;
        $out->venue_name        = (string) $venue->name;
        $out->province_name     = (string) $venue->province_name;
        $out->district_name     = (string) $venue->district;
        $out->institution_name  = '';
        $out->address           = (string) $venue->address;
        $out->maps_url          = (string) $venue->maps_url;
        $out->default_capacity  = (int) $venue->default_capacity;
        $out->default_duration  = isset( $venue->default_duration ) ? (int) $venue->default_duration : 60;
        $out->contact_name      = (string) $venue->contact_name;
        $out->contact_phone     = (string) $venue->contact_phone;
        $out->email             = '';
        $out->operation_notes   = isset( $venue->notes ) ? (string) $venue->notes : '';
        $out->latitude          = isset( $venue->latitude ) ? $venue->latitude : null;
        $out->longitude         = isset( $venue->longitude ) ? $venue->longitude : null;
        $out->venue_source      = 'mdg';
        return $out;
    }

    public static function update_allocation( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_program_venues';
        $row = self::get_program_venue( $id );
        if ( ! $row ) {
            return new WP_Error( 'mmc_program_venue_not_found', 'Program salon kaydı bulunamadı.' );
        }

        $allowed = self::allocation_statuses();
        $status  = sanitize_key( $data['allocation_status'] ?? $row->allocation_status );
        if ( ! isset( $allowed[ $status ] ) ) {
            $status = $row->allocation_status;
        }

        $update = array(
            'requested_date'      => self::date_or_null( $data['requested_date'] ?? $row->requested_date ),
            'alternative_dates'   => sanitize_textarea_field( $data['alternative_dates'] ?? $row->alternative_dates ),
            'requested_sessions'  => sanitize_text_field( $data['requested_sessions'] ?? $row->requested_sessions ),
            'allocation_status'   => $status,
            'target_institution'  => sanitize_text_field( $data['target_institution'] ?? $row->target_institution ),
            'outgoing_reference'  => sanitize_text_field( $data['outgoing_reference'] ?? $row->outgoing_reference ),
            'response_reference'  => sanitize_text_field( $data['response_reference'] ?? $row->response_reference ),
            'request_sent_at'     => self::datetime_or_null( $data['request_sent_at'] ?? $row->request_sent_at ),
            'response_at'         => self::datetime_or_null( $data['response_at'] ?? $row->response_at ),
            'rental_amount'       => self::money( $data['rental_amount'] ?? $row->rental_amount ),
            'deposit_amount'      => self::money( $data['deposit_amount'] ?? $row->deposit_amount ),
            'payment_due_date'    => self::date_or_null( $data['payment_due_date'] ?? $row->payment_due_date ),
            'notes'               => sanitize_textarea_field( $data['notes'] ?? $row->notes ),
            'updated_at'          => current_time( 'mysql' ),
        );

        $ok = $wpdb->update( $table, $update, array( 'id' => absint( $id ) ) );
        if ( false === $ok ) {
            return new WP_Error( 'mmc_allocation_update', 'Tahsis kaydı güncellenemedi.' );
        }

        MMC_Program_Service::add_log( $row->program_id, 'venue_allocation_updated', 'program_venue', $id, $row->allocation_status, $status, 'Salon tahsis durumu güncellendi.' );

        if ( in_array( $status, array( 'sent', 'pending' ), true ) ) {
            MMC_Program_Service::set_status( $row->program_id, 'allocation_pending', 'Salon tahsis cevabı bekleniyor.' );
        } elseif ( 'prepared' === $status ) {
            MMC_Program_Service::set_status( $row->program_id, 'allocation_request', 'Salon tahsis dilekçesi hazırlandı.' );
        }

        return true;
    }

    public static function generate_allocation_letter( $program_venue_id ) {
        global $wpdb;
        $row = self::get_program_venue( $program_venue_id );
        if ( ! $row ) {
            return new WP_Error( 'mmc_letter_row_missing', 'Salon kaydı bulunamadı.' );
        }
        $program = MMC_Program_Service::get_program( $row->program_id );
        if ( ! $program ) {
            return new WP_Error( 'mmc_letter_program_missing', 'Program kaydı bulunamadı.' );
        }

        $institution = $row->target_institution ?: ( $row->institution_name ?: '[İLGİLİ KURUM]' );
        $date = $row->requested_date ?: ( $program->planned_date ?: '[TARİH]' );
        $sessions = $row->requested_sessions ?: '[SEANS SAATLERİ]';
        $alt = trim( (string) $row->alternative_dates );

        $body  = $institution . "\n\n";
        $body .= "Konu: Salon Tahsis Talebi\n\n";
        $body .= "Dünya Organizasyon Medya Turizm Eğitim Danışmanlık Reklam Seyahat Acenteliği Ltd. Şti. tarafından, Madagaskar Sirki turne programı kapsamında ";
        $body .= $program->province_name . ( $program->district_name ? ' / ' . $program->district_name : '' ) . " bölgesinde ailelere yönelik hayvansız modern sirk gösterisi düzenlenmesi planlanmaktadır.\n\n";
        $body .= $row->venue_name . " salonunun " . $date . " tarihinde, " . $sessions . " seansları için uygun olması hâlinde tarafımıza tahsis edilmesini arz ederiz.\n";
        if ( $alt ) {
            $body .= "Alternatif tarih/tarihler: " . $alt . "\n";
        }
        $body .= "\nTahsis şartları, salon kullanım bedeli, teminat tutarı, salon giriş/çıkış saatleri ve gerekli diğer işlemler hakkında tarafımıza bilgi verilmesini rica ederiz.\n\n";
        $body .= "Dünya Organizasyon Medya Turizm Eğitim Danışmanlık Reklam Seyahat Acenteliği Ltd. Şti.\n";
        $body .= "Kızılay Mahallesi, Menekşe 1 Caddesi, Şeref Apartmanı No: 3, İç Kapı No: 11, Çankaya / Ankara\n";
        $body .= "Telefon: +90 506 034 38 74\n";

        $docs = $wpdb->prefix . 'mmc_documents';
        $now  = current_time( 'mysql' );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $docs WHERE program_id=%d AND entity_type='program_venue' AND entity_id=%d AND doc_type='venue_allocation_request' ORDER BY id DESC LIMIT 1", $row->program_id, $row->id ) );
        $payload = array(
            'program_id'  => $row->program_id,
            'entity_type' => 'program_venue',
            'entity_id'   => $row->id,
            'doc_type'    => 'venue_allocation_request',
            'title'       => 'Salon Tahsis Talep Dilekçesi — ' . $row->venue_name,
            'content'     => $body,
            'status'      => 'draft',
            'updated_at'  => $now,
        );
        if ( $existing ) {
            $wpdb->update( $docs, $payload, array( 'id' => $existing ) );
            $doc_id = (int) $existing;
        } else {
            $payload['created_by'] = get_current_user_id() ?: null;
            $payload['created_at'] = $now;
            $wpdb->insert( $docs, $payload );
            $doc_id = (int) $wpdb->insert_id;
        }

        $wpdb->update( $wpdb->prefix . 'mmc_program_venues', array( 'allocation_status' => 'prepared', 'updated_at' => $now ), array( 'id' => $row->id ) );
        MMC_Program_Service::set_status( $row->program_id, 'allocation_request', 'Salon tahsis dilekçesi hazırlandı.' );
        MMC_Program_Service::add_log( $row->program_id, 'allocation_letter_generated', 'document', $doc_id, null, null, 'Salon tahsis dilekçesi üretildi.' );
        return $doc_id;
    }

    public static function get_latest_letter( $program_venue_id ) {
        global $wpdb;
        $docs = $wpdb->prefix . 'mmc_documents';
        $row = self::get_program_venue( $program_venue_id );
        if ( ! $row ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $docs WHERE program_id=%d AND entity_type='program_venue' AND entity_id=%d AND doc_type='venue_allocation_request' ORDER BY id DESC LIMIT 1", $row->program_id, $row->id ) );
    }

    public static function confirm_venue( $program_venue_id ) {
        global $wpdb;
        $row = self::get_program_venue( $program_venue_id );
        if ( ! $row ) {
            return new WP_Error( 'mmc_confirm_venue_missing', 'Salon kaydı bulunamadı.' );
        }

        $table = $wpdb->prefix . 'mmc_program_venues';
        $wpdb->update( $table, array( 'is_selected' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'program_id' => $row->program_id ) );
        $wpdb->update(
            $table,
            array( 'is_selected' => 1, 'allocation_status' => 'approved', 'response_at' => $row->response_at ?: current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $row->id )
        );

        self::ensure_finance_entry( $row->program_id, $row->id, 'venue_rent', 'expense', (float) $row->rental_amount, 'Salon kira bedeli — ' . $row->venue_name, $row->payment_due_date );
        self::ensure_finance_entry( $row->program_id, $row->id, 'venue_deposit', 'deposit_asset', (float) $row->deposit_amount, 'Salon teminatı — ' . $row->venue_name, $row->payment_due_date );

        self::ensure_task( $row->program_id, 'finance', 'Salon kira bedelini öde ve dekontu kaydet', 'high' );
        self::ensure_task( $row->program_id, 'finance', 'Salon teminatını yatır ve dekontu kaydet', 'high' );
        self::ensure_task( $row->program_id, 'event', 'Etkinlik ve seans kayıtlarını oluştur', 'high' );
        self::ensure_task( $row->program_id, 'sales', 'Bilet satış hazırlığını başlat', 'high' );
        self::ensure_task( $row->program_id, 'system', 'Salon tahsis/onay belgesini Program Dosyasına yükle', 'normal' );

        if ( class_exists( 'MMC_Event_Service' ) ) {
            MMC_Event_Service::ensure_event_for_program( $row->program_id, $row->id );
        }

        MMC_Program_Service::set_status( $row->program_id, 'venue_confirmed', 'Salon kesinleşti; finans ve etkinlik hazırlık görevleri açıldı.' );
        MMC_Program_Service::add_log( $row->program_id, 'venue_confirmed', 'program_venue', $row->id, null, array( 'venue_id' => $row->venue_id, 'venue_name' => $row->venue_name ), 'Salon kesinleştirildi.' );
        return true;
    }

    public static function finance_entries_for_program( $program_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_finance_entries';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE program_id=%d ORDER BY id ASC", absint( $program_id ) ) );
    }

    private static function ensure_finance_entry( $program_id, $program_venue_id, $category, $class, $amount, $title, $due_date ) {
        global $wpdb;
        if ( $amount <= 0 ) return;
        $table = $wpdb->prefix . 'mmc_finance_entries';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE program_id=%d AND related_entity_type='program_venue' AND related_entity_id=%d AND category=%s LIMIT 1", $program_id, $program_venue_id, $category ) );
        if ( $exists ) return;
        $now = current_time( 'mysql' );
        $wpdb->insert( $table, array(
            'program_id' => $program_id,
            'related_entity_type' => 'program_venue',
            'related_entity_id' => $program_venue_id,
            'entry_class' => $class,
            'category' => $category,
            'title' => $title,
            'amount' => $amount,
            'status' => 'planned',
            'due_date' => $due_date ?: null,
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ) );
    }

    private static function ensure_task( $program_id, $module, $title, $priority = 'normal' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_tasks';
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE program_id=%d AND module=%s AND title=%s AND status<>'cancelled' LIMIT 1", $program_id, $module, $title ) );
        if ( $exists ) return;
        $now = current_time( 'mysql' );
        $wpdb->insert( $table, array(
            'program_id' => $program_id,
            'module' => $module,
            'title' => $title,
            'status' => 'open',
            'priority' => $priority,
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ) );
    }

    private static function date_or_null( $value ) {
        $value = sanitize_text_field( (string) $value );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
    }

    private static function datetime_or_null( $value ) {
        $value = sanitize_text_field( (string) $value );
        if ( ! $value ) return null;
        $value = str_replace( 'T', ' ', $value );
        return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $value ) ? substr( $value, 0, 16 ) . ':00' : null;
    }

    public static function update_finance_entry( $entry_id, $status, $reference_no = '', $notes = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'mmc_finance_entries';
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d LIMIT 1", absint( $entry_id ) ) );
        if ( ! $entry ) {
            return new WP_Error( 'mmc_finance_missing', 'Finans kaydı bulunamadı.' );
        }
        $allowed = array( 'planned', 'paid', 'refunded', 'cancelled' );
        $status = sanitize_key( $status );
        if ( ! in_array( $status, $allowed, true ) ) {
            return new WP_Error( 'mmc_finance_status', 'Geçersiz finans durumu.' );
        }
        $data = array(
            'status' => $status,
            'reference_no' => sanitize_text_field( $reference_no ),
            'notes' => sanitize_textarea_field( $notes ),
            'updated_at' => current_time( 'mysql' ),
        );
        if ( 'paid' === $status ) {
            $data['paid_at'] = current_time( 'mysql' );
        }
        if ( 'refunded' === $status ) {
            $data['refunded_at'] = current_time( 'mysql' );
        }
        $ok = $wpdb->update( $table, $data, array( 'id' => absint( $entry_id ) ) );
        if ( false === $ok ) {
            return new WP_Error( 'mmc_finance_update', 'Finans kaydı güncellenemedi.' );
        }
        MMC_Program_Service::add_log( $entry->program_id, 'venue_finance_updated', 'finance_entry', $entry->id, $entry->status, $status, $entry->title );
        if ( 'paid' === $status ) {
            $remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE program_id=%d AND category IN ('venue_rent','venue_deposit') AND status<>'paid' AND status<>'cancelled'", $entry->program_id ) );
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE program_id=%d AND category IN ('venue_rent','venue_deposit')", $entry->program_id ) );
            if ( $total > 0 && 0 === $remaining ) {
                MMC_Program_Service::set_status( $entry->program_id, 'event_setup', 'Salon kira ve teminat ödeme kayıtları tamamlandı; etkinlik hazırlığına geçildi.' );
            } else {
                MMC_Program_Service::set_status( $entry->program_id, 'venue_payment', 'Salon kira/teminat ödeme süreci devam ediyor.' );
            }
        }
        return true;
    }

    private static function place_key( $value ) {
        $value = trim( (string) $value );
        if ( function_exists( 'remove_accents' ) ) {
            $value = remove_accents( $value );
        }
        if ( function_exists( 'mb_strtolower' ) ) {
            $value = mb_strtolower( $value, 'UTF-8' );
        } else {
            $value = strtolower( $value );
        }
        return preg_replace( '/[^a-z0-9]+/u', '', $value );
    }

    private static function money( $value ) {
        if ( is_string( $value ) ) {
            $value = trim( $value );
            if ( false !== strpos( $value, ',' ) ) {
                $value = str_replace( '.', '', $value );
                $value = str_replace( ',', '.', $value );
            }
        }
        return round( (float) $value, 2 );
    }
}
