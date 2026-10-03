<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Event_Importer {
    const MAX_FILE_SIZE = 15728640; // 15 MB
    const PREVIEW_TTL   = 1800;     // 30 dk
    const MAX_EVENTS    = 100;
    const MAX_ROWS      = 5000;

    public static function preview_from_request() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_preview_event_import', 'mdg_event_import_nonce' );

        if ( empty( $_FILES['event_file'] ) || ! is_array( $_FILES['event_file'] ) ) {
            self::redirect_error( 'İçe aktarılacak Excel dosyasını seçin.' );
        }
        $file = $_FILES['event_file'];
        if ( ! empty( $file['error'] ) ) { self::redirect_error( 'Dosya yükleme hatası: ' . absint( $file['error'] ) ); }
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { self::redirect_error( 'Yüklenen geçici dosya doğrulanamadı.' ); }
        if ( ! empty( $file['size'] ) && (int) $file['size'] > self::MAX_FILE_SIZE ) { self::redirect_error( 'Excel dosyası 15 MB sınırını aşıyor.' ); }
        $name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
        if ( 'xlsx' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) { self::redirect_error( 'Etkinlik içe aktarımında yalnızca .xlsx dosyası kabul edilir.' ); }

        $sheets = self::read_xlsx_workbook( $file['tmp_name'] );
        if ( is_wp_error( $sheets ) ) { self::redirect_error( $sheets->get_error_message() ); }
        $preview = self::build_preview( $sheets );
        if ( is_wp_error( $preview ) ) { self::redirect_error( $preview->get_error_message() ); }
        $preview['file_name'] = $name;
        $preview['created_at'] = time();
        set_transient( self::preview_key(), $preview, self::PREVIEW_TTL );

        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-publish', 'mdg_event_import_preview'=>1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function commit_from_request() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_commit_event_import', 'mdg_event_import_commit_nonce' );
        $preview = self::get_preview();
        if ( ! $preview || empty( $preview['events'] ) ) { self::redirect_error( 'İçe aktarım önizlemesi bulunamadı veya süresi doldu. Excel dosyasını yeniden önizleyin.' ); }
        if ( ! empty( $preview['fatal_errors'] ) ) { self::redirect_error( 'Önizlemede kritik hata bulunduğu için kayıt yapılmadı. Excel dosyasını düzeltip yeniden önizleyin.' ); }

        global $wpdb;
        $events_table = MDG_DB::table( 'events' );
        $inserted = 0; $updated = 0;
        $wpdb->query( 'START TRANSACTION' );
        try {
            foreach ( $preview['events'] as $event ) {
                $existing = self::event_by_import_code( $event['event_code'] );
                if ( $existing && 'draft' !== $existing->status ) { throw new Exception( $event['event_code'] . ': aynı kod satışa açılmış bir etkinlikte kullanılıyor; üzerine yazılamaz.' ); }

                /*
                 * Salon çözümleme sırası:
                 * 1) Excel önizlemesinde otomatik eşleşen aktif salon,
                 * 2) Aynı event_code ile mevcut TASLAK üzerinde daha önce manuel seçilmiş aktif salon,
                 * 3) Salon yok: taslak salon seçimi bekleyerek kaydedilir.
                 *
                 * Böylece aynı Excel tekrar yüklendiğinde manuel salon seçimi silinmez.
                 */
                $venue = null;

                if ( ! empty( $event['venue_id'] ) ) {
                    $candidate = MDG_Venues::get( absint( $event['venue_id'] ) );
                    if ( $candidate && (int) $candidate->is_active ) {
                        $venue = $candidate;
                    }
                }

                if ( ! $venue && $existing && ! empty( $existing->venue_id ) ) {
                    $candidate = MDG_Venues::get( absint( $existing->venue_id ) );
                    if ( $candidate && (int) $candidate->is_active ) {
                        $venue = $candidate;
                    }
                }

                $data = array(
                    'import_code'                  => $event['event_code'],
                    'title'                        => $event['title'],
                    'venue_id'                     => $venue ? (int) $venue->id : null,
                    'province_code'                => $venue ? (string) $venue->province_code : (string) ( $event['province_code'] ?? '' ),
                    'province_name'                => $venue ? (string) $venue->province_name : (string) ( $event['province_name'] ?? '' ),
                    'district'                     => $venue ? (string) $venue->district : (string) ( $event['district'] ?? '' ),
                    'venue_name'                   => $venue ? (string) $venue->name : '',
                    'venue_address'                => $venue ? (string) $venue->address : '',
                    'venue_latitude'               => $venue && null !== $venue->latitude ? $venue->latitude : null,
                    'venue_longitude'              => $venue && null !== $venue->longitude ? $venue->longitude : null,
                    'venue_maps_url'               => $venue ? (string) $venue->maps_url : '',
                    'venue_qr_attachment_id'       => $venue && $venue->location_qr_attachment_id ? (int) $venue->location_qr_attachment_id : null,
                    'venue_default_capacity'       => $venue ? (int) $venue->default_capacity : 0,
                    'venue_default_duration'       => $venue ? (int) $venue->default_duration : (int) $event['show_duration'],
                    'short_description'            => $event['short_description'],
                    'long_description'             => $event['long_description'],
                    'hero_attachment_id'           => $event['hero_attachment_id'] ?: null,
                    'gallery_attachment_ids'       => wp_json_encode( $event['gallery_attachment_ids'] ),
                    'video_url'                    => $event['video_url'],
                    'age_info'                     => $event['age_info'],
                    'show_duration'                => $event['show_duration'],
                    'doors_open_before'            => $event['doors_open_before'],
                    'seating_type'                 => $event['seating_type'],
                    'rules'                        => $event['rules'],
                    'organizer_name'               => $event['organizer_name'],
                    'faq_json'                     => wp_json_encode( $event['faq'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
                    'seo_title'                    => $event['seo_title'],
                    'seo_description'              => $event['seo_description'],
                    'status'                       => 'draft',
                    'updated_at'                   => MDG_DB::now(),
                );

                if ( $existing ) {
                    $ok = $wpdb->update( $events_table, $data, array( 'id'=>(int) $existing->id ) );
                    if ( false === $ok ) { throw new Exception( $event['event_code'] . ': taslak güncellenemedi.' ); }
                    $event_id = (int) $existing->id;
                    $updated++;
                    $action = 'event.import.updated';
                } else {
                    $data['public_uuid'] = wp_generate_uuid4();
                    $data['created_by'] = get_current_user_id();
                    $data['created_at'] = MDG_DB::now();
                    $ok = $wpdb->insert( $events_table, $data );
                    if ( false === $ok ) { throw new Exception( $event['event_code'] . ': taslak oluşturulamadı.' ); }
                    $event_id = (int) $wpdb->insert_id;
                    $inserted++;
                    $action = 'event.import.created';
                }

                $structure = MDG_Sessions::replace_draft_structure( $event_id, $event['sessions'], $event['ticket_types'] );
                if ( is_wp_error( $structure ) ) { throw new Exception( $event['event_code'] . ': ' . $structure->get_error_message() ); }
                self::audit( $action, $event_id, array(
                    'event_code'=>$event['event_code'],
                    'sessions'=>count( $event['sessions'] ),
                    'ticket_types'=>count( $event['ticket_types'] ),
                    'source'=>'xlsx',
                    'venue_mode'=>$venue ? 'resolved_or_preserved' : 'manual_pending',
                    'imported_venue_name'=>(string) ( $event['imported_venue_name'] ?? '' ),
                ) );
            }
            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_error( $e->getMessage() ? $e->getMessage() : 'Etkinlik Excel içe aktarımı sırasında hata oluştu.' );
        }

        delete_transient( self::preview_key() );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-publish', 'mdg_event_import_done'=>1, 'mdg_event_inserted'=>$inserted, 'mdg_event_updated'=>$updated ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function get_preview() {
        $data = get_transient( self::preview_key() );
        return is_array( $data ) ? $data : array();
    }

    private static function preview_key() { return 'mdg_event_import_preview_' . get_current_user_id(); }

    private static function build_preview( array $sheets ) {
        $required = array( 'etkinlikler', 'seanslar', 'bilet turleri' );
        foreach ( $required as $key ) {
            if ( empty( $sheets[ $key ] ) ) { return new WP_Error( 'mdg_event_sheet_missing', 'Excel içinde zorunlu sayfa bulunamadı: ' . $key ); }
        }

        $event_rows = self::assoc_rows( $sheets['etkinlikler'] );
        $session_rows = self::assoc_rows( $sheets['seanslar'] );
        $ticket_rows = self::assoc_rows( $sheets['bilet turleri'] );
        $image_rows = ! empty( $sheets['gorseller'] ) ? self::assoc_rows( $sheets['gorseller'] ) : array();
        $faq_rows = ! empty( $sheets['sss'] ) ? self::assoc_rows( $sheets['sss'] ) : array();
        if ( is_wp_error( $event_rows ) || is_wp_error( $session_rows ) || is_wp_error( $ticket_rows ) || is_wp_error( $image_rows ) || is_wp_error( $faq_rows ) ) {
            foreach ( array( $event_rows, $session_rows, $ticket_rows, $image_rows, $faq_rows ) as $value ) { if ( is_wp_error( $value ) ) { return $value; } }
        }

        $events = array(); $fatal = array(); $warnings = array();
        foreach ( $event_rows as $row_no => $row ) {
            if ( ! self::enabled( $row['import_enabled'] ?? 'Evet' ) ) { continue; }
            $code = self::event_code( $row['event_code'] ?? '' );
            if ( ! $code ) { $fatal[] = 'Etkinlikler satır ' . $row_no . ': event_code zorunludur.'; continue; }
            if ( isset( $events[ $code ] ) ) { $fatal[] = $code . ': Etkinlikler sayfasında event_code birden fazla kez kullanılmış.'; continue; }
            if ( count( $events ) >= self::MAX_EVENTS ) { $fatal[] = 'Bir dosyada en fazla ' . self::MAX_EVENTS . ' etkinlik içe aktarılabilir.'; break; }

            $title = sanitize_text_field( $row['title'] ?? '' );
            $province_code = self::province_code( $row['province'] ?? '' );
            $district = $province_code ? MDG_Venues::canonical_district_name( $province_code, sanitize_text_field( $row['district'] ?? '' ) ) : '';
            $venue_name = sanitize_text_field( $row['venue_name'] ?? '' );

            /*
             * V2.9.2+ Manuel Salon Seçimi:
             * Salon adı Excel'de zorunlu değildir.
             * İl ve ilçe etkinliğin taslak konumunu belirler;
             * salon eşleşirse otomatik bağlanır, eşleşmezse taslak
             * salon seçilmeden oluşturulabilir.
             */
            if ( ! $title || ! $province_code || ! $district ) {
                $fatal[] = $code . ': title, province ve district alanları zorunludur.'; continue;
            }

            $province_name = (string) ( MDG_Venues::provinces()[ $province_code ] ?? '' );
            $venue = null;

            if ( $venue_name ) {
                $venue = self::find_venue( $province_code, $district, $venue_name );

                if ( ! $venue ) {
                    $warnings[] = $code . ': Salon otomatik eşleştirilemedi (' . $province_name . ' / ' . $district . ' / ' . $venue_name . '). Taslak oluşturulacak; salonu Etkinlik Yayınla ekranından manuel seçiniz.';
                }
            } else {
                $warnings[] = $code . ': Excel dosyasında salon belirtilmedi. Taslak oluşturulacak; salonu Etkinlik Yayınla ekranından manuel seçiniz.';
            }

            $duration_default = $venue ? (int) $venue->default_duration : 60;
            $duration = self::int_range( $row['show_duration'] ?? '', 15, 360, $duration_default );
            $doors = self::int_range( $row['doors_open_before'] ?? '', 0, 180, 30 );
            $video = self::safe_https_url( $row['video_url'] ?? '' );
            if ( is_wp_error( $video ) ) { $fatal[] = $code . ': ' . $video->get_error_message(); $video = ''; }
            $hero = self::resolve_image( $row['hero_attachment_id'] ?? '', $row['hero_media_url'] ?? '' );
            if ( ! $hero && ( trim( (string) ( $row['hero_attachment_id'] ?? '' ) ) || trim( (string) ( $row['hero_media_url'] ?? '' ) ) ) ) {
                $warnings[] = $code . ': Kapak görseli WordPress ortamlarında bulunamadı; taslak kapaksız oluşturulacak.';
            }

            $existing = self::event_by_import_code( $code );
            if ( $existing && 'draft' !== $existing->status ) { $fatal[] = $code . ': aynı event_code satışa açılmış bir etkinlikte mevcut; üzerine yazılamaz.'; }

            $events[ $code ] = array(
                'event_code'=>$code,
                'title'=>$title,
                'venue_id'=>$venue ? (int) $venue->id : 0,
                'province_code'=>(string) $province_code,
                'province_name'=>$venue ? (string) $venue->province_name : $province_name,
                'district'=>$venue ? (string) $venue->district : $district,
                'venue_name'=>$venue ? (string) $venue->name : '',
                'imported_venue_name'=>$venue ? '' : $venue_name,
                'venue_pending'=>$venue ? 0 : 1,
                'short_description'=>self::cut( sanitize_textarea_field( $row['short_description'] ?? '' ), 600 ),
                'long_description'=>wp_kses_post( $row['long_description'] ?? '' ),
                'age_info'=>sanitize_text_field( $row['age_info'] ?? '' ),
                'show_duration'=>$duration,
                'doors_open_before'=>$doors,
                'seating_type'=>self::seating( $row['seating_type'] ?? '' ),
                'rules'=>sanitize_textarea_field( $row['rules'] ?? '' ),
                'organizer_name'=>sanitize_text_field( $row['organizer_name'] ?? '' ),
                'video_url'=>$video,
                'hero_attachment_id'=>$hero,
                'gallery_attachment_ids'=>array(),
                'seo_title'=>self::cut( sanitize_text_field( $row['seo_title'] ?? '' ), 190 ),
                'seo_description'=>self::cut( sanitize_textarea_field( $row['seo_description'] ?? '' ), 320 ),
                'sessions'=>array(),
                'ticket_types'=>array(),
                'faq'=>array(),
                'mode'=>$existing ? 'update' : 'create',
            );
        }

        foreach ( $session_rows as $row_no => $row ) {
            if ( ! self::enabled( $row['import_enabled'] ?? 'Evet' ) ) { continue; }
            $code = self::event_code( $row['event_code'] ?? '' );
            if ( ! isset( $events[ $code ] ) ) { if ( $code ) { $warnings[] = 'Seanslar satır ' . $row_no . ': ' . $code . ' Etkinlikler sayfasında aktif değil; satır atlandı.'; } continue; }
            $date = self::normalize_date( $row['date'] ?? '' );
            $time = self::normalize_time( $row['time'] ?? '' );
            $cap = self::int_range( $row['capacity'] ?? '', 1, 100000, 0 );
            if ( ! $date || ! $time || $cap < 1 ) { $fatal[] = $code . ': Seanslar satır ' . $row_no . ' tarih/saat/kapasite geçersiz.'; continue; }
            $session = self::session_row( $date, $time, $cap, $events[ $code ]['show_duration'] );
            if ( is_wp_error( $session ) ) { $fatal[] = $code . ': ' . $session->get_error_message(); continue; }
            foreach ( $events[ $code ]['sessions'] as $existing_session ) {
                if ( $existing_session['start_at'] === $session['start_at'] ) { $fatal[] = $code . ': aynı tarih/saat seansı iki kez girilmiş (' . $date . ' ' . $time . ').'; continue 2; }
            }
            $events[ $code ]['sessions'][] = $session;
        }

        foreach ( $ticket_rows as $row_no => $row ) {
            if ( ! self::enabled( $row['import_enabled'] ?? 'Evet' ) || ! self::enabled( $row['active'] ?? 'Evet' ) ) { continue; }
            $code = self::event_code( $row['event_code'] ?? '' );
            if ( ! isset( $events[ $code ] ) ) { continue; }
            $label = sanitize_text_field( $row['label'] ?? '' );
            $ticket_code = self::ticket_code( $row['ticket_code'] ?? '', $label );
            $price = self::decimal( $row['price'] ?? '' );
            $units = self::int_range( $row['capacity_units'] ?? '', 1, 50, 1 );
            $sort = self::int_range( $row['sort_order'] ?? '', 1, 9999, ( count( $events[ $code ]['ticket_types'] ) + 1 ) * 10 );
            if ( ! $label || ! $ticket_code || null === $price ) { $fatal[] = $code . ': Bilet Türleri satır ' . $row_no . ' ad/kod/fiyat alanı geçersiz.'; continue; }
            foreach ( $events[ $code ]['ticket_types'] as $tt ) { if ( $tt['code'] === $ticket_code ) { $fatal[] = $code . ': bilet türü kodu tekrar ediyor: ' . $ticket_code; continue 2; } }
            $events[ $code ]['ticket_types'][] = array(
                'code'=>$ticket_code, 'label'=>$label, 'price'=>number_format( $price, 2, '.', '' ), 'capacity_units'=>$units, 'sort_order'=>$sort
            );
        }

        foreach ( $image_rows as $row_no => $row ) {
            if ( ! self::enabled( $row['import_enabled'] ?? 'Hayır' ) ) { continue; }
            $code = self::event_code( $row['event_code'] ?? '' );
            if ( ! isset( $events[ $code ] ) ) { continue; }
            $type = strtolower( remove_accents( sanitize_text_field( $row['image_type'] ?? 'gallery' ) ) );
            $id = self::resolve_image( $row['attachment_id'] ?? '', $row['media_url'] ?? '' );
            if ( ! $id ) { $warnings[] = $code . ': Görseller satır ' . $row_no . ' WordPress ortamlarında bulunamadı; atlandı.'; continue; }
            if ( 'hero' === $type || 'kapak' === $type ) { $events[ $code ]['hero_attachment_id'] = $id; }
            else { $events[ $code ]['gallery_attachment_ids'][] = $id; }
        }

        foreach ( $faq_rows as $row_no => $row ) {
            if ( ! self::enabled( $row['import_enabled'] ?? 'Hayır' ) ) { continue; }
            $code = self::event_code( $row['event_code'] ?? '' );
            if ( ! isset( $events[ $code ] ) ) { continue; }
            $q = sanitize_text_field( $row['question'] ?? '' );
            $a = sanitize_textarea_field( $row['answer'] ?? '' );
            if ( $q && $a ) { $events[ $code ]['faq'][] = array( 'question'=>$q, 'answer'=>$a, 'sort_order'=>self::int_range( $row['sort_order'] ?? '', 1, 9999, 10 ) ); }
        }

        foreach ( $events as $code => &$event ) {
            if ( ! $event['sessions'] ) { $fatal[] = $code . ': en az bir aktif seans bulunmalıdır.'; }
            if ( ! $event['ticket_types'] ) { $fatal[] = $code . ': en az bir aktif bilet türü bulunmalıdır.'; }
            usort( $event['sessions'], function( $a, $b ){ return strcmp( $a['start_at'], $b['start_at'] ); } );
            usort( $event['ticket_types'], function( $a, $b ){ return $a['sort_order'] <=> $b['sort_order']; } );
            $event['gallery_attachment_ids'] = array_slice( array_values( array_unique( array_map( 'absint', $event['gallery_attachment_ids'] ) ) ), 0, 30 );
            usort( $event['faq'], function( $a, $b ){ return $a['sort_order'] <=> $b['sort_order']; } );
        }
        unset( $event );

        return array(
            'events'=>array_values( $events ),
            'fatal_errors'=>array_values( array_unique( $fatal ) ),
            'warnings'=>array_values( array_unique( $warnings ) ),
            'summary'=>array(
                'event_count'=>count( $events ),
                'session_count'=>array_sum( array_map( function( $e ){ return count( $e['sessions'] ); }, $events ) ),
                'ticket_type_count'=>array_sum( array_map( function( $e ){ return count( $e['ticket_types'] ); }, $events ) ),
                'image_count'=>array_sum( array_map( function( $e ){ return ( $e['hero_attachment_id'] ? 1 : 0 ) + count( $e['gallery_attachment_ids'] ); }, $events ) ),
            ),
        );
    }

    private static function assoc_rows( $rows ) {
        if ( ! is_array( $rows ) || count( $rows ) < 1 ) { return array(); }
        $header_index = -1; $headers = array();
        foreach ( $rows as $i => $row ) {
            $candidate = array_map( array( __CLASS__, 'normalize_header' ), $row );
            if ( in_array( 'event_code', $candidate, true ) ) { $header_index = $i; $headers = $candidate; break; }
        }
        if ( $header_index < 0 ) { return new WP_Error( 'mdg_event_headers', 'Excel sayfasında event_code başlığı bulunamadı.' ); }
        $out = array();
        foreach ( array_slice( $rows, $header_index + 1, self::MAX_ROWS ) as $offset => $row ) {
            $assoc = array(); $has = false;
            foreach ( $headers as $i => $header ) {
                if ( ! $header ) { continue; }
                $value = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
                $assoc[ $header ] = $value;
                if ( '' !== $value ) { $has = true; }
            }
            if ( $has ) { $out[ $header_index + 2 + $offset ] = $assoc; }
        }
        return $out;
    }

    private static function find_venue( $province_code, $district, $venue_name ) {
        $district_key = MDG_Venues::normalize_place_key( $district );
        $name_key = MDG_Venues::normalize_place_key( $venue_name );
        foreach ( MDG_Venues::all( true ) as $venue ) {
            if ( (string) $venue->province_code !== (string) $province_code ) { continue; }
            if ( MDG_Venues::normalize_place_key( $venue->district ) !== $district_key ) { continue; }
            if ( MDG_Venues::normalize_place_key( $venue->name ) !== $name_key ) { continue; }
            return $venue;
        }
        return null;
    }

    private static function event_by_import_code( $code ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'events' ) . ' WHERE import_code=%s LIMIT 1', $code ) );
    }

    private static function session_row( $date, $time, $cap, $duration ) {
        try {
            $local = new DateTimeImmutable( $date . ' ' . $time . ':00', wp_timezone() );
            if ( $local->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) { return new WP_Error( 'mdg_import_datetime', 'geçersiz tarih veya saat: ' . $date . ' ' . $time ); }
            $utc = $local->setTimezone( new DateTimeZone( 'UTC' ) );
            $end = $utc->modify( '+' . absint( $duration ) . ' minutes' );
            return array( 'start_at'=>$utc->format('Y-m-d H:i:s'), 'end_at'=>$end->format('Y-m-d H:i:s'), 'capacity_total'=>absint($cap) );
        } catch ( Exception $e ) { return new WP_Error( 'mdg_import_datetime', 'tarih veya saat işlenemedi.' ); }
    }

    private static function normalize_date( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        if ( is_numeric( $value ) ) {
            $serial = (float) $value;
            if ( $serial > 1 && $serial < 100000 ) {
                $unix = (int) round( ( $serial - 25569 ) * 86400 );
                return gmdate( 'Y-m-d', $unix );
            }
        }
        foreach ( array( 'Y-m-d', 'd.m.Y', 'd/m/Y' ) as $fmt ) {
            $dt = DateTimeImmutable::createFromFormat( '!' . $fmt, $value );
            if ( $dt && $dt->format( $fmt ) === $value ) { return $dt->format( 'Y-m-d' ); }
        }
        return '';
    }

    private static function normalize_time( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) { return ''; }
        if ( is_numeric( $value ) ) {
            $fraction = (float) $value;
            if ( $fraction >= 0 && $fraction < 1 ) {
                $mins = (int) round( $fraction * 1440 );
                $mins = $mins % 1440;
                return sprintf( '%02d:%02d', intdiv( $mins, 60 ), $mins % 60 );
            }
        }
        if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $m ) ) {
            $h=(int)$m[1]; $min=(int)$m[2];
            if ( $h >=0 && $h <=23 && $min>=0 && $min<=59 ) { return sprintf('%02d:%02d',$h,$min); }
        }
        return '';
    }

    private static function seating( $value ) {
        $v = strtolower( remove_accents( trim( (string) $value ) ) );
        if ( in_array( $v, array( 'numbered','numarali','numarali koltuk' ), true ) ) { return 'numbered'; }
        if ( in_array( $v, array( 'mixed','karma' ), true ) ) { return 'mixed'; }
        return 'free';
    }

    private static function enabled( $value ) {
        $v = strtolower( remove_accents( trim( (string) $value ) ) );
        return ! in_array( $v, array( 'hayir','hayır','no','0','false','pasif','inactive','off' ), true );
    }

    private static function event_code( $value ) {
        $v = strtoupper( remove_accents( trim( (string) $value ) ) );
        $v = preg_replace( '/[^A-Z0-9_-]+/', '-', $v );
        return substr( trim( $v, '-' ), 0, 80 );
    }

    private static function ticket_code( $value, $label ) {
        $v = strtoupper( remove_accents( trim( (string) $value ) ) );
        if ( ! $v ) { $v = strtoupper( remove_accents( trim( (string) $label ) ) ); }
        $v = preg_replace( '/[^A-Z0-9]+/', '_', $v );
        return substr( trim( $v, '_' ), 0, 40 );
    }

    private static function decimal( $value ) {
        $v = str_replace( array( '₺','TL','tl',' ' ), '', trim( (string) $value ) );
        $v = str_replace( ',', '.', $v );
        if ( '' === $v || ! is_numeric( $v ) ) { return null; }
        $f = round( (float) $v, 2 );
        return ( $f >= 0 && $f <= 1000000 ) ? $f : null;
    }

    private static function int_range( $value, $min, $max, $default ) {
        if ( '' === trim( (string) $value ) || ! is_numeric( str_replace(',', '.', (string)$value) ) ) { return $default; }
        $i = (int) round( (float) str_replace(',', '.', (string)$value) );
        return min( $max, max( $min, $i ) );
    }

    private static function safe_https_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) { return ''; }
        $safe = esc_url_raw( $url, array( 'https' ) );
        if ( ! $safe || 0 !== stripos( $safe, 'https://' ) ) { return new WP_Error( 'mdg_import_url', 'video_url geçerli bir HTTPS adresi olmalıdır.' ); }
        return $safe;
    }

    private static function resolve_image( $attachment_id, $media_url ) {
        $id = absint( $attachment_id );
        if ( $id && wp_attachment_is_image( $id ) ) { return $id; }
        $url = esc_url_raw( trim( (string) $media_url ), array( 'https' ) );
        if ( ! $url ) { return 0; }
        $id = attachment_url_to_postid( $url );
        return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
    }

    private static function province_code( $province ) {
        $needle = strtolower( remove_accents( trim( (string) $province ) ) );
        foreach ( MDG_Venues::provinces() as $code=>$name ) {
            if ( strtolower( remove_accents( $name ) ) === $needle ) { return $code; }
        }
        if ( preg_match( '/^\d{1,2}$/', $needle ) ) {
            $code = str_pad( $needle, 2, '0', STR_PAD_LEFT );
            if ( isset( MDG_Venues::provinces()[ $code ] ) ) { return $code; }
        }
        return '';
    }

    private static function cut( $value, $max ) {
        $value = (string) $value;
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max );
    }

    private static function normalize_header( $value ) {
        $value = strtolower( remove_accents( trim( (string) $value ) ) );
        $value = preg_replace( '/[^a-z0-9]+/', '_', $value );
        return trim( $value, '_' );
    }

    private static function sheet_key( $name ) {
        $name = strtolower( remove_accents( trim( (string) $name ) ) );
        $name = preg_replace( '/\s+/u', ' ', $name );
        return $name;
    }

    private static function read_xlsx_workbook( $path ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem(); global $wp_filesystem;
        $tmp = wp_tempnam( 'mdg-events-xlsx' );
        if ( ! $tmp ) { return new WP_Error( 'mdg_event_xlsx_tmp', 'Excel için geçici klasör oluşturulamadı.' ); }
        @unlink( $tmp ); $dir = $tmp . '-dir';
        if ( ! wp_mkdir_p( $dir ) ) { return new WP_Error( 'mdg_event_xlsx_dir', 'Excel geçici klasörü oluşturulamadı.' ); }
        $unzipped = unzip_file( $path, $dir );
        if ( is_wp_error( $unzipped ) ) { if ($wp_filesystem) {$wp_filesystem->delete($dir,true);} return new WP_Error('mdg_event_xlsx_unzip','Excel açılamadı: '.$unzipped->get_error_message()); }

        $map = self::sheet_paths( $dir );
        if ( is_wp_error( $map ) ) { if ($wp_filesystem) {$wp_filesystem->delete($dir,true);} return $map; }
        $shared = self::read_shared_strings( $dir . '/xl/sharedStrings.xml' );
        $out = array();
        foreach ( $map as $name=>$sheet_path ) {
            $key = self::sheet_key( $name );
            if ( ! in_array( $key, array('etkinlikler','seanslar','bilet turleri','gorseller','sss'), true ) ) { continue; }
            $rows = self::read_sheet_xml( $sheet_path, $shared );
            if ( is_wp_error( $rows ) ) { if ($wp_filesystem) {$wp_filesystem->delete($dir,true);} return $rows; }
            $out[$key] = $rows;
        }
        if ( $wp_filesystem ) { $wp_filesystem->delete( $dir, true ); }
        return $out;
    }

    private static function sheet_paths( $dir ) {
        $workbook=$dir.'/xl/workbook.xml'; $rels=$dir.'/xl/_rels/workbook.xml.rels';
        if ( ! file_exists($workbook) || ! file_exists($rels) ) { return new WP_Error('mdg_event_xlsx_structure','Excel çalışma kitabı yapısı okunamadı.'); }
        $wb=file_get_contents($workbook); $rl=file_get_contents($rels);
        if ( false===$wb || false===$rl ) { return new WP_Error('mdg_event_xlsx_read','Excel çalışma kitabı okunamadı.'); }
        $relationships=array();
        if ( preg_match_all('/<(?:[A-Za-z0-9_.-]+:)?Relationship\b[^>]*\/?\s*>/u',$rl,$tags) ) {
            foreach($tags[0] as $tag){ $a=self::xml_attributes($tag); $id=$a['Id']??($a['id']??''); $target=$a['Target']??($a['target']??''); if($id&&$target){$relationships[$id]=$target;} }
        }
        $out=array();
        if ( preg_match_all('/<(?:[A-Za-z0-9_.-]+:)?sheet\b[^>]*\/?\s*>/u',$wb,$tags) ) {
            foreach($tags[0] as $tag){
                $a=self::xml_attributes($tag); $name=isset($a['name'])?html_entity_decode($a['name'],ENT_QUOTES|ENT_XML1,'UTF-8'):''; $rid=$a['r:id']??($a['id']??'');
                if(!$name||!$rid||empty($relationships[$rid])){continue;}
                $target=rawurldecode(html_entity_decode($relationships[$rid],ENT_QUOTES|ENT_XML1,'UTF-8')); $target=str_replace('\\','/',$target); $target=preg_replace('#^/+#','',$target);
                $sheet=(0===strpos($target,'xl/'))?$dir.'/'.$target:$dir.'/xl/'.$target; $sheet=self::normalize_path($sheet);
                if(file_exists($sheet)){$out[$name]=$sheet;}
            }
        }
        return $out;
    }

    private static function xml_attributes( $tag ) {
        $attrs=array(); if(preg_match_all('/\b([A-Za-z_][A-Za-z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/u',(string)$tag,$m,PREG_SET_ORDER)){
            foreach($m as $a){$attrs[$a[1]]=isset($a[2])&&''!==$a[2]?$a[2]:($a[3]??'');}
        } return $attrs;
    }
    private static function normalize_path($path){$path=str_replace('\\','/',(string)$path);$prefix=(0===strpos($path,'/'))?'/':'';$parts=array();foreach(explode('/',$path) as $p){if(''===$p||'.'===$p){continue;}if('..'===$p){array_pop($parts);continue;}$parts[]=$p;}return $prefix.implode('/',$parts);}
    private static function read_shared_strings($path){if(!file_exists($path)){return array();}$xml=file_get_contents($path);if(false===$xml){return array();}$out=array();if(preg_match_all('/<(?:[A-Za-z0-9_]+:)?si\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?si>/us',$xml,$items)){foreach($items[1] as $item){$parts=array();if(preg_match_all('/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/us',$item,$texts)){foreach($texts[1] as $text){$parts[]=self::xml_text($text);}}$out[]=implode('',$parts);}}return $out;}
    private static function read_sheet_xml($path,$shared){$xml=file_get_contents($path);if(false===$xml){return new WP_Error('mdg_event_sheet_read','Excel veri sayfası okunamadı.');}$rows=array();if(!preg_match_all('/<(?:[A-Za-z0-9_]+:)?row\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?row>/us',$xml,$row_matches)){return array();}foreach($row_matches[1] as $row_xml){if(count($rows)>=self::MAX_ROWS+1){break;}$row=array();$row_xml=preg_replace('/<(?:[A-Za-z0-9_.-]+:)?c\b[^>]*\/\s*>/u','',$row_xml);if(preg_match_all('/<(?:[A-Za-z0-9_.-]+:)?c\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_.-]+:)?c>/us',$row_xml,$cells,PREG_SET_ORDER)){foreach($cells as $cell){$attrs=$cell[1];$body=$cell[2];if(!preg_match('/\br="([A-Z]+)\d+"/',$attrs,$ref)){continue;}$idx=self::column_index($ref[1]);$type='';if(preg_match('/\bt="([^"]+)"/',$attrs,$tm)){$type=$tm[1];}$value='';if('inlineStr'===$type&&preg_match('/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/us',$body,$vm)){$value=self::xml_text($vm[1]);}elseif(preg_match('/<(?:[A-Za-z0-9_]+:)?v\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/us',$body,$vm)){$raw=self::xml_text($vm[1]);if('s'===$type){$si=(int)$raw;$value=$shared[$si]??'';}else{$value=$raw;}}$row[$idx]=trim((string)$value);}}if($row){$max=max(array_keys($row));$dense=array_fill(0,$max+1,'');foreach($row as $i=>$v){$dense[$i]=$v;}$rows[]=$dense;}}return $rows;}
    private static function xml_text($value){$value=preg_replace('/<!\[CDATA\[(.*?)\]\]>/us','$1',(string)$value);return html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_XML1,'UTF-8');}
    private static function column_index($letters){$letters=strtoupper($letters);$n=0;for($i=0,$l=strlen($letters);$i<$l;$i++){$n=$n*26+(ord($letters[$i])-64);}return max(0,$n-1);}

    private static function audit( $action, $id, $context ) {
        global $wpdb; $wpdb->insert( MDG_DB::table('audit_log'), array(
            'user_id'=>get_current_user_id(),'action_key'=>$action,'object_type'=>'event','object_id'=>absint($id),
            'context'=>wp_json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'created_at'=>MDG_DB::now(),
        ) );
    }

    private static function redirect_error( $message ) {
        wp_safe_redirect( add_query_arg( array('page'=>'mdg-publish','mdg_event_import_error'=>rawurlencode((string)$message)), admin_url('admin.php') ) ); exit;
    }
}
