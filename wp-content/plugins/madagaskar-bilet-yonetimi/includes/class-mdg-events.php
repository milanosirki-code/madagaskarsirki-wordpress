<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Events {
    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'events' ) . ' WHERE id=%d', absint( $id ) ) );
    }

    public static function get_by_public_slug( $slug ) {
        global $wpdb;
        $slug = sanitize_title( (string) $slug );
        if ( ! $slug ) { return null; }
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'events' ) . ' WHERE public_slug=%s LIMIT 1', $slug ) );
    }

    public static function get_by_uuid( $uuid ) {
        global $wpdb;
        $uuid = sanitize_text_field( (string) $uuid );
        if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $uuid ) ) { return null; }
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'events' ) . ' WHERE public_uuid=%s LIMIT 1', $uuid ) );
    }

    public static function faq_items( $event ) {
        if ( ! $event || empty( $event->faq_json ) ) { return array(); }
        $decoded = json_decode( (string) $event->faq_json, true );
        if ( ! is_array( $decoded ) ) { return array(); }
        $items = array();
        foreach ( $decoded as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $q = sanitize_text_field( (string) ( $row['question'] ?? '' ) );
            $a = sanitize_textarea_field( (string) ( $row['answer'] ?? '' ) );
            if ( $q && $a ) { $items[] = array( 'question' => $q, 'answer' => $a ); }
        }
        return array_slice( $items, 0, 30 );
    }


    public static function drafts() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . MDG_DB::table( 'events' ) . " WHERE status='draft' ORDER BY updated_at DESC, id DESC LIMIT 250" );
    }

    public static function gallery_ids( $event ) {
        if ( ! $event || empty( $event->gallery_attachment_ids ) ) { return array(); }
        $decoded = json_decode( (string) $event->gallery_attachment_ids, true );
        if ( ! is_array( $decoded ) ) { return array(); }
        return array_values( array_filter( array_unique( array_map( 'absint', $decoded ) ) ) );
    }

    public static function save_draft_from_request() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_save_event_draft', 'mdg_nonce' );

        global $wpdb;
        $table = MDG_DB::table( 'events' );
        $id = absint( $_POST['event_id'] ?? 0 );
        $old = $id ? self::get( $id ) : null;
        if ( $old && 'draft' !== $old->status ) { self::redirect_error( 'Satışa açılmış veya kapanmış bir etkinlik bu taslak ekranından değiştirilemez.' ); }

        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $venue_id = absint( $_POST['venue_id'] ?? 0 );
        $venue = $venue_id ? MDG_Venues::get( $venue_id ) : null;
        if ( ! $title ) { self::redirect_error( 'Etkinlik adı zorunludur.' ); }
        if ( ! $venue || ! (int) $venue->is_active ) { self::redirect_error( 'Aktif bir salon seçmelisiniz.' ); }

        $province_code = sanitize_text_field( wp_unslash( $_POST['province_code'] ?? '' ) );
        $district = sanitize_text_field( wp_unslash( $_POST['district'] ?? '' ) );
        if ( $province_code !== (string) $venue->province_code || $district !== (string) $venue->district ) {
            self::redirect_error( 'İl, ilçe ve salon seçimi birbiriyle eşleşmiyor. Lütfen salonu yeniden seçin.' );
        }

        $hero_id = absint( $_POST['hero_attachment_id'] ?? 0 );
        if ( $hero_id && ! wp_attachment_is_image( $hero_id ) ) { $hero_id = 0; }
        $gallery_ids = self::sanitize_attachment_ids( wp_unslash( $_POST['gallery_attachment_ids'] ?? '' ) );
        $video_url = self::safe_https_url( wp_unslash( $_POST['video_url'] ?? '' ) );
        if ( is_wp_error( $video_url ) ) { self::redirect_error( $video_url->get_error_message() ); }

        $show_duration_raw = isset( $_POST['show_duration'] ) && '' !== $_POST['show_duration'] ? absint( $_POST['show_duration'] ) : 0;
        $show_duration = $show_duration_raw ? min( 360, max( 15, $show_duration_raw ) ) : (int) $venue->default_duration;
        $doors_open = min( 180, max( 0, absint( $_POST['doors_open_before'] ?? 30 ) ) );
        $seating = sanitize_key( wp_unslash( $_POST['seating_type'] ?? 'free' ) );
        if ( ! in_array( $seating, array( 'free', 'numbered', 'mixed' ), true ) ) { $seating = 'free'; }

        // V2.4: validate the canonical session capacity model and ticket catalogue before touching DB.
        $sessions = MDG_Sessions::normalize_from_request( $show_duration );
        if ( is_wp_error( $sessions ) ) { self::redirect_error( $sessions->get_error_message() ); }
        $ticket_types = MDG_Sessions::normalize_ticket_types_from_request();
        if ( is_wp_error( $ticket_types ) ) { self::redirect_error( $ticket_types->get_error_message() ); }

        $faq = self::normalize_faq_from_request();

        $data = array(
            'title'                         => $title,
            'venue_id'                      => (int) $venue->id,
            'province_code'                 => (string) $venue->province_code,
            'province_name'                 => (string) $venue->province_name,
            'district'                      => (string) $venue->district,
            'venue_name'                    => (string) $venue->name,
            'venue_address'                 => (string) $venue->address,
            'venue_latitude'                => null !== $venue->latitude ? $venue->latitude : null,
            'venue_longitude'               => null !== $venue->longitude ? $venue->longitude : null,
            'venue_maps_url'                => (string) $venue->maps_url,
            'venue_qr_attachment_id'        => $venue->location_qr_attachment_id ? (int) $venue->location_qr_attachment_id : null,
            'venue_default_capacity'        => (int) $venue->default_capacity,
            'venue_default_duration'        => (int) $venue->default_duration,
            'short_description'             => sanitize_textarea_field( wp_unslash( $_POST['short_description'] ?? '' ) ),
            'long_description'              => wp_kses_post( wp_unslash( $_POST['long_description'] ?? '' ) ),
            'hero_attachment_id'            => $hero_id ?: null,
            'gallery_attachment_ids'        => wp_json_encode( $gallery_ids ),
            'video_url'                     => $video_url,
            'age_info'                      => sanitize_text_field( wp_unslash( $_POST['age_info'] ?? '' ) ),
            'show_duration'                 => $show_duration,
            'doors_open_before'             => $doors_open,
            'seating_type'                  => $seating,
            'rules'                         => sanitize_textarea_field( wp_unslash( $_POST['rules'] ?? '' ) ),
            'organizer_name'                => sanitize_text_field( wp_unslash( $_POST['organizer_name'] ?? '' ) ),
            'faq_json'                      => wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'seo_title'                     => sanitize_text_field( wp_unslash( $_POST['seo_title'] ?? '' ) ),
            'seo_description'               => sanitize_textarea_field( wp_unslash( $_POST['seo_description'] ?? '' ) ),
            'status'                        => 'draft',
            'updated_at'                    => MDG_DB::now(),
        );

        if ( function_exists( 'mb_substr' ) ) {
            $data['short_description'] = mb_substr( $data['short_description'], 0, 600, 'UTF-8' );
            $data['seo_title'] = mb_substr( $data['seo_title'], 0, 190, 'UTF-8' );
            $data['seo_description'] = mb_substr( $data['seo_description'], 0, 320, 'UTF-8' );
        }

        $wpdb->query( 'START TRANSACTION' );
        try {
            if ( $id ) {
                $ok = $wpdb->update( $table, $data, array( 'id' => $id ) );
                if ( false === $ok ) { throw new Exception( 'Etkinlik taslağı güncellenemedi.' ); }
                $action = 'event.draft.updated';
            } else {
                $data['public_uuid'] = wp_generate_uuid4();
                $data['created_by'] = get_current_user_id();
                $data['created_at'] = MDG_DB::now();
                $ok = $wpdb->insert( $table, $data );
                if ( false === $ok ) { throw new Exception( 'Etkinlik taslağı kaydedilemedi.' ); }
                $id = (int) $wpdb->insert_id;
                $action = 'event.draft.created';
            }

            $structure = MDG_Sessions::replace_draft_structure( $id, $sessions, $ticket_types );
            if ( is_wp_error( $structure ) ) { throw new Exception( $structure->get_error_message() ); }

            self::audit( $action, $id, array(
                'title' => $title,
                'venue_id' => (int) $venue->id,
                'province' => (string) $venue->province_name,
                'district' => (string) $venue->district,
                'hero_attachment_id' => $hero_id,
                'gallery_count' => count( $gallery_ids ),
                'seating_type' => $seating,
                'session_count' => count( $sessions ),
                'faq_count' => count( $faq ),
                'ticket_types' => array_map( function ( $type ) {
                    return array(
                        'code' => $type['code'],
                        'label' => $type['label'],
                        'price' => $type['price'],
                        'capacity_units' => $type['capacity_units'],
                    );
                }, $ticket_types ),
            ) );

            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_error( $e->getMessage() ? $e->getMessage() : 'Etkinlik taslağı kaydedilirken bir hata oluştu.' );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'mdg-publish', 'edit' => $id, 'mdg_saved' => 1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function duplicate_draft_from_request() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $source_id = absint( $_GET['event_id'] ?? 0 );
        check_admin_referer( 'mdg_duplicate_event_draft_' . $source_id );
        $source = self::get( $source_id );
        if ( ! $source || 'draft' !== $source->status ) { self::redirect_error( 'Yalnızca etkinlik taslakları kopyalanabilir.' ); }

        $sessions_src = MDG_Sessions::by_event( $source_id );
        $types_src = MDG_Sessions::ticket_catalogue_for_event( $source_id );
        if ( ! $sessions_src || ! $types_src ) { self::redirect_error( 'Kopyalanacak taslakta seans veya bilet türü bulunamadı.' ); }

        $sessions = array();
        foreach ( $sessions_src as $row ) {
            $sessions[] = array(
                'start_at'=>(string) $row->start_at,
                'end_at'=>(string) $row->end_at,
                'capacity_total'=>(int) $row->capacity_total,
            );
        }
        $ticket_types = array();
        foreach ( $types_src as $row ) {
            $ticket_types[] = array(
                'code'=>(string) $row->code,
                'label'=>(string) $row->label,
                'price'=>(string) $row->price,
                'capacity_units'=>(int) $row->capacity_units,
                'sort_order'=>(int) $row->sort_order,
            );
        }

        global $wpdb;
        $table = MDG_DB::table( 'events' );
        $data = (array) $source;
        unset( $data['id'] );
        $data['public_uuid'] = wp_generate_uuid4();
        $data['import_code'] = null; // Kopya bağımsız bir manuel taslak olur.
        $copy_title = 'Kopya – ' . (string) $source->title;
        $data['title'] = function_exists( 'mb_substr' ) ? mb_substr( $copy_title, 0, 190, 'UTF-8' ) : substr( $copy_title, 0, 190 );
        $data['status'] = 'draft';
        $data['sale_start'] = null; $data['sale_end'] = null; $data['wp_page_id'] = null;
        $data['cancellation_reason'] = null; $data['postponement_note'] = null;
        $data['created_by'] = get_current_user_id();
        $data['created_at'] = MDG_DB::now(); $data['updated_at'] = MDG_DB::now();

        $wpdb->query( 'START TRANSACTION' );
        try {
            if ( false === $wpdb->insert( $table, $data ) ) { throw new Exception( 'Etkinlik taslağı kopyalanamadı.' ); }
            $new_id = (int) $wpdb->insert_id;
            $structure = MDG_Sessions::replace_draft_structure( $new_id, $sessions, $ticket_types );
            if ( is_wp_error( $structure ) ) { throw new Exception( $structure->get_error_message() ); }
            self::audit( 'event.draft.duplicated', $new_id, array( 'source_event_id'=>$source_id ) );
            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_error( $e->getMessage() ? $e->getMessage() : 'Taslak kopyalanırken hata oluştu.' );
        }

        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-publish', 'edit'=>$new_id, 'mdg_duplicated'=>1 ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function normalize_faq_from_request() {
        $questions = isset( $_POST['faq_question'] ) && is_array( $_POST['faq_question'] ) ? wp_unslash( $_POST['faq_question'] ) : array();
        $answers   = isset( $_POST['faq_answer'] ) && is_array( $_POST['faq_answer'] ) ? wp_unslash( $_POST['faq_answer'] ) : array();
        $items = array();
        $count = min( 30, max( count( $questions ), count( $answers ) ) );
        for ( $i = 0; $i < $count; $i++ ) {
            $q = sanitize_text_field( (string) ( $questions[ $i ] ?? '' ) );
            $a = sanitize_textarea_field( (string) ( $answers[ $i ] ?? '' ) );
            if ( '' === $q && '' === $a ) { continue; }
            if ( '' === $q || '' === $a ) { continue; }
            if ( function_exists( 'mb_substr' ) ) {
                $q = mb_substr( $q, 0, 300, 'UTF-8' );
                $a = mb_substr( $a, 0, 1200, 'UTF-8' );
            }
            $items[] = array( 'question' => $q, 'answer' => $a, 'sort_order' => ( count( $items ) + 1 ) * 10 );
        }
        return $items;
    }

    private static function sanitize_attachment_ids( $csv ) {
        $ids = array();
        foreach ( preg_split( '/[\s,]+/', (string) $csv ) as $raw ) {
            $id = absint( $raw );
            if ( $id && wp_attachment_is_image( $id ) ) { $ids[] = $id; }
        }
        return array_slice( array_values( array_unique( $ids ) ), 0, 30 );
    }

    private static function safe_https_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) { return ''; }
        $safe = esc_url_raw( $url, array( 'https' ) );
        if ( ! $safe || 0 !== stripos( $safe, 'https://' ) ) {
            return new WP_Error( 'mdg_bad_video_url', 'Tanıtım videosu bağlantısı geçerli bir HTTPS adresi olmalıdır.' );
        }
        return $safe;
    }

    private static function audit( $action, $id, $context ) {
        global $wpdb;
        $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
            'user_id'     => get_current_user_id(),
            'action_key'  => $action,
            'object_type' => 'event',
            'object_id'   => absint( $id ),
            'context'     => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'created_at'  => MDG_DB::now(),
        ) );
    }

    private static function redirect_error( $message ) {
        $args = array( 'page' => 'mdg-publish', 'mdg_error' => rawurlencode( (string) $message ) );
        if ( ! empty( $_POST['event_id'] ) ) { $args['edit'] = absint( $_POST['event_id'] ); }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
