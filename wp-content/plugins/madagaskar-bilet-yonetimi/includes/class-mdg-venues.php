<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Venues {
    const DISTRICT_CACHE_OPTION = 'mdg_tr_districts_cache_v2';
    const DISTRICT_CACHE_TS_OPTION = 'mdg_tr_districts_cache_ts_v2';

    /** Plaka kodu => il adı. */
    public static function provinces() {
        return array(
            '01'=>'Adana','02'=>'Adıyaman','03'=>'Afyonkarahisar','04'=>'Ağrı','05'=>'Amasya','06'=>'Ankara','07'=>'Antalya','08'=>'Artvin','09'=>'Aydın','10'=>'Balıkesir',
            '11'=>'Bilecik','12'=>'Bingöl','13'=>'Bitlis','14'=>'Bolu','15'=>'Burdur','16'=>'Bursa','17'=>'Çanakkale','18'=>'Çankırı','19'=>'Çorum','20'=>'Denizli',
            '21'=>'Diyarbakır','22'=>'Edirne','23'=>'Elazığ','24'=>'Erzincan','25'=>'Erzurum','26'=>'Eskişehir','27'=>'Gaziantep','28'=>'Giresun','29'=>'Gümüşhane','30'=>'Hakkari',
            '31'=>'Hatay','32'=>'Isparta','33'=>'Mersin','34'=>'İstanbul','35'=>'İzmir','36'=>'Kars','37'=>'Kastamonu','38'=>'Kayseri','39'=>'Kırklareli','40'=>'Kırşehir',
            '41'=>'Kocaeli','42'=>'Konya','43'=>'Kütahya','44'=>'Malatya','45'=>'Manisa','46'=>'Kahramanmaraş','47'=>'Mardin','48'=>'Muğla','49'=>'Muş','50'=>'Nevşehir',
            '51'=>'Niğde','52'=>'Ordu','53'=>'Rize','54'=>'Sakarya','55'=>'Samsun','56'=>'Siirt','57'=>'Sinop','58'=>'Sivas','59'=>'Tekirdağ','60'=>'Tokat',
            '61'=>'Trabzon','62'=>'Tunceli','63'=>'Şanlıurfa','64'=>'Uşak','65'=>'Van','66'=>'Yozgat','67'=>'Zonguldak','68'=>'Aksaray','69'=>'Bayburt','70'=>'Karaman',
            '71'=>'Kırıkkale','72'=>'Batman','73'=>'Şırnak','74'=>'Bartın','75'=>'Ardahan','76'=>'Iğdır','77'=>'Yalova','78'=>'Karabük','79'=>'Kilis','80'=>'Osmaniye','81'=>'Düzce'
        );
    }

    /**
     * İlçe verisi yalnızca yönetim kolaylığı içindir; canlı satış bu kaynağa bağlı değildir.
     * Başarılı ilk alımdan sonra yerel option önbelleğinde tutulur.
     */
    public static function districts() {
        $cached = get_option( self::DISTRICT_CACHE_OPTION, array() );
        $cached_at = (int) get_option( self::DISTRICT_CACHE_TS_OPTION, 0 );
        $max_age = (int) apply_filters( 'mdg_district_cache_ttl', 30 * DAY_IN_SECONDS );

        if ( is_array( $cached ) && ! empty( $cached ) && $cached_at > 0 && ( time() - $cached_at ) < $max_age ) {
            return $cached;
        }

        $fresh = self::refresh_districts();
        if ( ! empty( $fresh ) ) { return $fresh; }
        if ( is_array( $cached ) && ! empty( $cached ) ) { return $cached; }

        return array(
            '06' => array(
                'Akyurt','Altındağ','Ayaş','Bala','Beypazarı','Çamlıdere','Çankaya','Çubuk','Elmadağ','Etimesgut','Evren','Gölbaşı','Güdül','Haymana','Kahramankazan','Kalecik','Keçiören','Kızılcahamam','Mamak','Nallıhan','Polatlı','Pursaklar','Sincan','Şereflikoçhisar','Yenimahalle'
            ),
        );
    }

    public static function refresh_districts() {
        /*
         * Eski V2.4.x kaynağı bazı ilçe adlarını ASCII olarak döndürüyordu
         * (örn. Altindag). Yeni kaynak Türkçe karakterleri koruyan cities.json
         * biçimidir. Bu veri yalnızca yönetim ekranı kolaylığı içindir; canlı
         * satış akışı uzak kaynağa bağlı değildir ve son başarılı veri option'da
         * önbelleğe alınır.
         */
        $url = apply_filters( 'mdg_district_source_url', 'https://raw.githubusercontent.com/enisbt/turkey-cities/refs/heads/master/cities.json' );
        $response = wp_remote_get( $url, array(
            'timeout'     => 12,
            'redirection' => 3,
            'user-agent'  => 'Madagaskar-Bilet-Yonetimi/' . ( defined( 'MDG_BILET_VERSION' ) ? MDG_BILET_VERSION : '2' ),
        ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return array(); }
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $decoded ) ) { return array(); }

        $map = array();

        // cities.json: [{"plate":"06","counties":["altındağ", ...]}, ...]
        foreach ( $decoded as $city ) {
            if ( ! is_array( $city ) || empty( $city['plate'] ) || empty( $city['counties'] ) || ! is_array( $city['counties'] ) ) { continue; }
            $code = str_pad( (string) absint( $city['plate'] ), 2, '0', STR_PAD_LEFT );
            if ( ! isset( self::provinces()[ $code ] ) ) { continue; }
            foreach ( $city['counties'] as $county ) {
                $name = trim( (string) $county );
                if ( '' !== $name ) { $map[ $code ][] = self::title_case_tr( $name ); }
            }
        }

        // Eski tablo biçimi için geriye dönük parser (filtre ile eski kaynak verilirse).
        if ( empty( $map ) ) {
            $rows = array();
            foreach ( $decoded as $block ) {
                if ( is_array( $block ) && isset( $block['type'], $block['name'], $block['data'] ) && 'table' === $block['type'] && 'ilce' === $block['name'] && is_array( $block['data'] ) ) {
                    $rows = $block['data'];
                    break;
                }
            }
            foreach ( $rows as $row ) {
                $il_id = isset( $row['il_id'] ) ? absint( $row['il_id'] ) : 0;
                $name  = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
                if ( $il_id < 1 || $il_id > 81 || '' === $name ) { continue; }
                $code = str_pad( (string) $il_id, 2, '0', STR_PAD_LEFT );
                $map[ $code ][] = self::title_case_tr( $name );
            }
        }

        foreach ( $map as $code => $districts ) {
            $districts = array_values( array_unique( array_filter( $districts ) ) );
            natcasesort( $districts );
            $map[ $code ] = array_values( $districts );
        }
        if ( count( $map ) >= 80 ) {
            update_option( self::DISTRICT_CACHE_OPTION, $map, false );
            update_option( self::DISTRICT_CACHE_TS_OPTION, time(), false );
            return $map;
        }
        return array();
    }

    private static function title_case_tr( $value ) {
        $value = trim( (string) $value );
        if ( function_exists( 'mb_convert_case' ) ) {
            return mb_convert_case( mb_strtolower( $value, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
        }
        return ucwords( strtolower( $value ) );
    }

    /** Türkçe karakter / büyük-küçük harf farklarını eşleştirme için normalize eder. */
    public static function normalize_place_key( $value ) {
        $value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
        $value = remove_accents( $value );
        if ( function_exists( 'mb_strtolower' ) ) { $value = mb_strtolower( $value, 'UTF-8' ); }
        else { $value = strtolower( $value ); }
        // remove_accents() bazı ortamlarda Türkçe noktasız ı için farklı davranabilir.
        $value = str_replace( array( 'ı', 'İ' ), array( 'i', 'i' ), $value );
        return $value;
    }

    /** İlçe adını seçili ilin sözlüğündeki doğru Türkçe yazıma dönüştürür. */
    public static function canonical_district_name( $province_code, $district ) {
        $district = trim( (string) $district );
        if ( '' === $district ) { return ''; }
        $map = self::districts();
        if ( empty( $map[ $province_code ] ) ) { return self::title_case_tr( $district ); }
        $needle = self::normalize_place_key( $district );
        foreach ( $map[ $province_code ] as $candidate ) {
            if ( self::normalize_place_key( $candidate ) === $needle ) { return $candidate; }
        }
        return self::title_case_tr( $district );
    }

    public static function district_is_valid( $province_code, $district ) {
        $map = self::districts();
        if ( empty( $map[ $province_code ] ) ) { return true; }
        $needle = self::normalize_place_key( $district );
        foreach ( $map[ $province_code ] as $candidate ) {
            if ( self::normalize_place_key( $candidate ) === $needle ) { return true; }
        }
        return false;
    }

    /**
     * Eski ASCII/cache kaynaklı ilçe yazımlarını veri kaybı olmadan düzeltir.
     * Aynı salon için çakışma doğacaksa otomatik birleştirme/silme yapmaz.
     */
    public static function repair_existing_district_names() {
        global $wpdb;
        $table = MDG_DB::table( 'venues' );
        $rows = $wpdb->get_results( "SELECT id, province_code, district, name FROM {$table}" );
        if ( ! $rows ) { return array( 'updated'=>0, 'skipped'=>0 ); }
        $updated = 0; $skipped = 0;
        foreach ( $rows as $row ) {
            $canonical = self::canonical_district_name( (string) $row->province_code, (string) $row->district );
            if ( '' === $canonical || $canonical === (string) $row->district ) { continue; }
            if ( self::normalize_place_key( $canonical ) !== self::normalize_place_key( $row->district ) ) { continue; }
            $conflict = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE province_code=%s AND district=%s AND name=%s AND id<>%d LIMIT 1",
                $row->province_code, $canonical, $row->name, (int) $row->id
            ) );
            if ( $conflict ) { $skipped++; continue; }
            $ok = $wpdb->update( $table, array( 'district'=>$canonical, 'updated_at'=>MDG_DB::now() ), array( 'id'=>(int) $row->id ) );
            if ( false !== $ok ) { $updated++; }
        }
        return array( 'updated'=>$updated, 'skipped'=>$skipped );
    }

    private static function duplicate_id( $province_code, $district, $name, $exclude_id = 0 ) {
        global $wpdb;
        $table = MDG_DB::table( 'venues' );
        $sql = "SELECT id FROM {$table} WHERE province_code=%s AND district=%s AND name=%s";
        $args = array( $province_code, $district, $name );
        if ( $exclude_id ) { $sql .= ' AND id<>%d'; $args[] = absint( $exclude_id ); }
        $sql .= ' LIMIT 1';
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
    }

    private static function validate_maps_url( $maps_url ) {
        $maps_url = trim( (string) $maps_url );
        if ( '' === $maps_url ) { return ''; }
        $validated = esc_url_raw( $maps_url, array( 'https' ) );
        if ( ! $validated || 0 !== stripos( $validated, 'https://' ) ) {
            return new WP_Error( 'mdg_invalid_maps_url', 'Harita bağlantısı geçerli bir HTTPS adresi olmalıdır.' );
        }
        return $validated;
    }

    /**
     * Salon kaydını yapar; QR üretim sorunu salon kaydını engellemez.
     */
    public static function save_from_request() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_save_venue', 'mdg_nonce' );

        global $wpdb;
        $table = MDG_DB::table( 'venues' );
        $id = absint( $_POST['venue_id'] ?? 0 );
        $old = $id ? self::get( $id ) : null;

        $province_code = sanitize_text_field( wp_unslash( $_POST['province_code'] ?? '' ) );
        $provinces = self::provinces();
        $province_name = isset( $provinces[ $province_code ] ) ? $provinces[ $province_code ] : '';
        $district = sanitize_text_field( wp_unslash( $_POST['district'] ?? '' ) );
        if ( '__manual__' === $district || '' === $district ) {
            $district = sanitize_text_field( wp_unslash( $_POST['district_manual'] ?? '' ) );
        }
        $district = self::canonical_district_name( $province_code, $district );
        $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );

        $maps_url = self::validate_maps_url( wp_unslash( $_POST['maps_url'] ?? '' ) );
        if ( is_wp_error( $maps_url ) ) { self::redirect_error( $maps_url->get_error_message() ); }

        $data = array(
            'name'             => $name,
            'province_code'    => $province_code,
            'province_name'    => $province_name,
            'district'         => $district,
            'address'          => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
            'latitude'         => '' !== ( $_POST['latitude'] ?? '' ) ? (float) $_POST['latitude'] : null,
            'longitude'        => '' !== ( $_POST['longitude'] ?? '' ) ? (float) $_POST['longitude'] : null,
            'maps_url'         => $maps_url,
            'contact_name'     => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
            'contact_phone'    => sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) ),
            'default_capacity' => max( 1, absint( $_POST['default_capacity'] ?? 500 ) ),
            'default_duration' => min( 360, max( 15, absint( $_POST['default_duration'] ?? 60 ) ) ),
            'notes'            => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
            'is_active'        => isset( $_POST['is_active'] ) ? 1 : 0,
            'updated_at'       => MDG_DB::now(),
        );

        if ( ! $data['name'] || ! $data['province_code'] || ! $data['province_name'] || ! $data['district'] || ! $data['address'] ) {
            self::redirect_error( 'İl, ilçe, salon adı ve adres zorunludur.' );
        }
        if ( ! self::district_is_valid( $province_code, $district ) ) {
            self::redirect_error( 'Seçilen ilçe bu ile ait görünmüyor. Lütfen il ve ilçeyi yeniden seçin.' );
        }
        if ( self::duplicate_id( $province_code, $district, $name, $id ) ) {
            self::redirect_error( 'Bu il / ilçe içinde aynı isimde bir salon zaten kayıtlı.' );
        }
        if ( null !== $data['latitude'] && ( $data['latitude'] < -90 || $data['latitude'] > 90 ) ) {
            self::redirect_error( 'Enlem -90 ile 90 arasında olmalıdır.' );
        }
        if ( null !== $data['longitude'] && ( $data['longitude'] < -180 || $data['longitude'] > 180 ) ) {
            self::redirect_error( 'Boylam -180 ile 180 arasında olmalıdır.' );
        }

        if ( $id ) {
            $updated = $wpdb->update( $table, $data, array( 'id' => $id ) );
            if ( false === $updated ) { self::redirect_error( 'Salon güncellenemedi. Veritabanı hatası oluştu.' ); }
            $action_key = 'venue.updated';
        } else {
            $data['created_at'] = MDG_DB::now();
            $inserted = $wpdb->insert( $table, $data );
            if ( false === $inserted ) { self::redirect_error( 'Salon kaydedilemedi. Veritabanı hatası oluştu.' ); }
            $id = (int) $wpdb->insert_id;
            $action_key = 'venue.created';
        }

        self::audit( $action_key, $id, $data );

        $warning = self::sync_location_qr_after_save( $id, $old, $maps_url );
        do_action( 'mdg_venue_saved', $id, self::get( $id ) );

        $args = array( 'mdg_saved' => 1 );
        if ( $warning ) { $args['mdg_warning'] = rawurlencode( $warning ); }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=mdg-venues' ) ) );
        exit;
    }

    /**
     * Maps URL hash değişmediyse mevcut QR tekrar üretilmez.
     * URL değiştiğinde eski attachment silinmez: geçmiş etkinlik snapshot'ları onu kullanabilir.
     */
    private static function sync_location_qr_after_save( $venue_id, $old, $maps_url ) {
        global $wpdb;
        $table = MDG_DB::table( 'venues' );
        $venue_id = absint( $venue_id );
        $new_hash = MDG_QR::maps_hash( $maps_url );
        $old_hash = $old ? (string) $old->location_qr_hash : '';
        $old_attachment = $old ? absint( $old->location_qr_attachment_id ) : 0;
        $force = ! empty( $_POST['force_qr'] );
        $data_url = isset( $_POST['qr_data_url'] ) ? wp_unslash( $_POST['qr_data_url'] ) : '';

        if ( '' === $maps_url ) {
            $wpdb->update( $table, array(
                'location_qr_attachment_id' => null,
                'location_qr_hash'          => null,
                'qr_updated_at'             => null,
            ), array( 'id' => $venue_id ) );
            if ( $old_hash || $old_attachment ) { self::audit_simple( 'venue.qr.cleared', $venue_id, array() ); }
            return '';
        }

        if ( ! $force && $new_hash === $old_hash && $old_attachment && get_post( $old_attachment ) ) {
            return '';
        }

        if ( '' === $data_url ) {
            if ( $new_hash !== $old_hash ) {
                // Yanlış/eski QR'ın yeni konumu göstermesini önle.
                $wpdb->update( $table, array(
                    'location_qr_attachment_id' => null,
                    'location_qr_hash'          => null,
                    'qr_updated_at'             => null,
                ), array( 'id' => $venue_id ) );
            }
            self::audit_simple( 'venue.qr.failed', $venue_id, array( 'reason' => 'browser_data_missing' ) );
            return 'Salon kaydedildi ancak konum QR görseli oluşturulamadı. Harita bağlantısını açıp “QR’ı Yeniden Oluştur” ile tekrar deneyebilirsiniz.';
        }

        $attachment_id = MDG_QR::save_png_data_url( $data_url, $venue_id, $maps_url );
        if ( is_wp_error( $attachment_id ) ) {
            if ( $new_hash !== $old_hash ) {
                $wpdb->update( $table, array(
                    'location_qr_attachment_id' => null,
                    'location_qr_hash'          => null,
                    'qr_updated_at'             => null,
                ), array( 'id' => $venue_id ) );
            }
            self::audit_simple( 'venue.qr.failed', $venue_id, array( 'reason' => $attachment_id->get_error_code() ) );
            return 'Salon kaydedildi ancak konum QR görseli Media Library’ye yazılamadı: ' . $attachment_id->get_error_message();
        }

        $wpdb->update( $table, array(
            'location_qr_attachment_id' => absint( $attachment_id ),
            'location_qr_hash'          => $new_hash,
            'qr_updated_at'             => MDG_DB::now(),
        ), array( 'id' => $venue_id ) );

        self::audit_simple( 'venue.qr.generated', $venue_id, array(
            'attachment_id' => absint( $attachment_id ),
            'maps_hash'     => $new_hash,
            'forced'        => (bool) $force,
        ) );
        return '';
    }

    private static function redirect_error( $message ) {
        wp_safe_redirect( add_query_arg( 'mdg_error', rawurlencode( $message ), admin_url( 'admin.php?page=mdg-venues' ) ) );
        exit;
    }

    private static function audit( $action_key, $id, $data ) {
        self::audit_simple( $action_key, $id, array(
            'province_code' => $data['province_code'],
            'province_name' => $data['province_name'],
            'district'      => $data['district'],
            'name'          => $data['name'],
            'is_active'     => $data['is_active'],
            'maps_url'      => $data['maps_url'],
        ) );
    }

    private static function audit_simple( $action_key, $id, $context ) {
        global $wpdb;
        $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
            'user_id'     => get_current_user_id(),
            'action_key'  => $action_key,
            'object_type' => 'venue',
            'object_id'   => absint( $id ),
            'context'     => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'created_at'  => MDG_DB::now(),
        ) );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MDG_DB::table( 'venues' ) . ' WHERE id = %d', absint( $id ) ) );
    }

    public static function all( $active_only = false ) {
        global $wpdb;
        $table = MDG_DB::table( 'venues' );
        $sql = "SELECT * FROM {$table}" . ( $active_only ? ' WHERE is_active = 1' : '' ) . ' ORDER BY province_code, district, name';
        return $wpdb->get_results( $sql );
    }
}
