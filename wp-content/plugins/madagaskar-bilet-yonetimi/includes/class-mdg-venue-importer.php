<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Venue_Importer {
    const MAX_FILE_SIZE = 10485760; // 10 MB
    const MAX_ROWS = 5000;

    public static function import_from_request() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        check_admin_referer( 'mdg_import_venues', 'mdg_import_nonce' );

        if ( empty( $_FILES['venue_file'] ) || ! is_array( $_FILES['venue_file'] ) ) {
            self::redirect_error( 'İçe aktarılacak Excel veya CSV dosyasını seçin.' );
        }

        $file = $_FILES['venue_file'];
        if ( ! empty( $file['error'] ) ) {
            self::redirect_error( 'Dosya yükleme hatası: ' . absint( $file['error'] ) );
        }
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            self::redirect_error( 'Yüklenen geçici dosya doğrulanamadı.' );
        }
        if ( ! empty( $file['size'] ) && (int) $file['size'] > self::MAX_FILE_SIZE ) {
            self::redirect_error( 'Dosya 10 MB sınırını aşıyor.' );
        }

        $name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
        $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
            self::redirect_error( 'Yalnızca .xlsx veya .csv dosyası kabul edilir.' );
        }

        $rows = ( 'csv' === $ext ) ? self::read_csv( $file['tmp_name'] ) : self::read_xlsx( $file['tmp_name'] );
        if ( is_wp_error( $rows ) ) { self::redirect_error( $rows->get_error_message() ); }
        if ( empty( $rows ) ) { self::redirect_error( 'Dosyada içe aktarılabilecek satır bulunamadı.' ); }

        $result = self::import_rows( $rows );
        if ( is_wp_error( $result ) ) { self::redirect_error( $result->get_error_message() ); }

        self::redirect_result( $result );
    }

    /**
     * CSV'de virgül, noktalı virgül ve sekme otomatik algılanır.
     */
    private static function read_csv( $path ) {
        $handle = fopen( $path, 'rb' );
        if ( ! $handle ) { return new WP_Error( 'mdg_csv_open', 'CSV dosyası açılamadı.' ); }

        $first = fgets( $handle );
        if ( false === $first ) { fclose( $handle ); return array(); }
        rewind( $handle );

        $delimiters = array( ',' => substr_count( $first, ',' ), ';' => substr_count( $first, ';' ), "\t" => substr_count( $first, "\t" ) );
        arsort( $delimiters );
        $delimiter = (string) key( $delimiters );

        $rows = array();
        while ( false !== ( $data = fgetcsv( $handle, 0, $delimiter ) ) ) {
            if ( count( $rows ) >= self::MAX_ROWS + 1 ) { break; }
            if ( isset( $data[0] ) ) { $data[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $data[0] ); }
            $rows[] = array_map( static function( $value ) { return is_string( $value ) ? trim( $value ) : $value; }, $data );
        }
        fclose( $handle );
        return $rows;
    }

    /**
     * XLSX doğrudan okunur. WordPress unzip_file() ZipArchive yoksa PclZip yedeğini kullanabilir.
     * XML okuma için ek PHP uzantısına bağımlı olmamak adına küçük, kontrollü bir regex parser kullanılır.
     */
    private static function read_xlsx( $path ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        $tmp = wp_tempnam( 'mdg-venues-xlsx' );
        if ( ! $tmp ) { return new WP_Error( 'mdg_xlsx_tmp', 'Excel dosyası için geçici klasör oluşturulamadı.' ); }
        @unlink( $tmp );
        $dir = $tmp . '-dir';
        if ( ! wp_mkdir_p( $dir ) ) { return new WP_Error( 'mdg_xlsx_dir', 'Excel geçici klasörü oluşturulamadı.' ); }

        $unzipped = unzip_file( $path, $dir );
        if ( is_wp_error( $unzipped ) ) {
            if ( $wp_filesystem ) { $wp_filesystem->delete( $dir, true ); }
            return new WP_Error( 'mdg_xlsx_unzip', 'Excel dosyası açılamadı: ' . $unzipped->get_error_message() );
        }

        $sheet_file = self::locate_import_sheet( $dir );
        if ( is_wp_error( $sheet_file ) ) {
            if ( $wp_filesystem ) { $wp_filesystem->delete( $dir, true ); }
            return $sheet_file;
        }

        $shared = self::read_shared_strings( $dir . '/xl/sharedStrings.xml' );
        $rows   = self::read_sheet_xml( $sheet_file, $shared );
        if ( $wp_filesystem ) { $wp_filesystem->delete( $dir, true ); }
        return $rows;
    }

    private static function locate_import_sheet( $dir ) {
        $workbook = $dir . '/xl/workbook.xml';
        $rels     = $dir . '/xl/_rels/workbook.xml.rels';
        if ( ! file_exists( $workbook ) || ! file_exists( $rels ) ) {
            return new WP_Error( 'mdg_xlsx_structure', 'Excel çalışma kitabı yapısı okunamadı.' );
        }

        $wb = file_get_contents( $workbook );
        $rl = file_get_contents( $rels );
        if ( false === $wb || false === $rl ) { return new WP_Error( 'mdg_xlsx_read', 'Excel çalışma kitabı okunamadı.' ); }

        /*
         * XLSX XML attribute order is not fixed. Some generators write
         * Relationship attributes as Type -> Target -> Id, while Excel may use
         * Id -> Type -> Target. Therefore attributes are parsed independently
         * instead of relying on a specific order in a regex.
         */
        $sheets = array();
        if ( preg_match_all( '/<(?:[A-Za-z0-9_.-]+:)?sheet\b[^>]*\/?\s*>/u', $wb, $sheet_tags ) ) {
            foreach ( $sheet_tags[0] as $tag ) {
                $attrs = self::xml_attributes( $tag );
                $name  = isset( $attrs['name'] ) ? html_entity_decode( $attrs['name'], ENT_QUOTES | ENT_XML1, 'UTF-8' ) : '';
                $rid   = $attrs['r:id'] ?? ( $attrs['id'] ?? '' );
                if ( $name && $rid ) { $sheets[] = array( 'name' => $name, 'rid' => $rid ); }
            }
        }
        if ( empty( $sheets ) ) { return new WP_Error( 'mdg_xlsx_sheet', 'Excel içinde çalışma sayfası bulunamadı.' ); }

        $sheet_rid = '';
        foreach ( $sheets as $sheet_info ) {
            $normalized = strtolower( remove_accents( trim( $sheet_info['name'] ) ) );
            if ( 'v2 ice aktarim' === $normalized ) {
                $sheet_rid = $sheet_info['rid'];
                break;
            }
        }
        if ( ! $sheet_rid ) { $sheet_rid = $sheets[0]['rid']; }

        $relationships = array();
        if ( preg_match_all( '/<(?:[A-Za-z0-9_.-]+:)?Relationship\b[^>]*\/?\s*>/u', $rl, $rel_tags ) ) {
            foreach ( $rel_tags[0] as $tag ) {
                $attrs  = self::xml_attributes( $tag );
                $id     = $attrs['Id'] ?? ( $attrs['id'] ?? '' );
                $target = $attrs['Target'] ?? ( $attrs['target'] ?? '' );
                if ( $id && $target ) { $relationships[ $id ] = $target; }
            }
        }

        $target = $relationships[ $sheet_rid ] ?? '';
        if ( ! $target ) {
            return new WP_Error( 'mdg_xlsx_rel', 'Excel çalışma sayfası bağlantısı çözümlenemedi. Lütfen V2.4.2 veya üzeri sürümü kullanın.' );
        }

        $target = rawurldecode( html_entity_decode( $target, ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
        $target = str_replace( '\\', '/', $target );
        $target = preg_replace( '#^/+#', '', $target );
        if ( 0 === strpos( $target, 'xl/' ) ) { $sheet = $dir . '/' . $target; }
        else { $sheet = $dir . '/xl/' . $target; }
        $sheet = self::normalize_path( $sheet );

        if ( ! file_exists( $sheet ) ) { return new WP_Error( 'mdg_xlsx_sheet_file', 'Excel veri sayfası bulunamadı.' ); }
        return $sheet;
    }

    /**
     * XML açılış etiketindeki attribute'ları sıra bağımsız okur.
     * SimpleXML/XMLReader gerektirmez; paylaşımlı hosting uyumluluğu için tutulur.
     */
    private static function xml_attributes( $tag ) {
        $attrs = array();
        if ( preg_match_all( '/\b([A-Za-z_][A-Za-z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/u', (string) $tag, $m, PREG_SET_ORDER ) ) {
            foreach ( $m as $a ) {
                $attrs[ $a[1] ] = isset( $a[2] ) && '' !== $a[2] ? $a[2] : ( $a[3] ?? '' );
            }
        }
        return $attrs;
    }

    /** Dot-segment temizliği; yalnızca geçici XLSX dizini içindeki yollar için kullanılır. */
    private static function normalize_path( $path ) {
        $path = str_replace( '\\', '/', (string) $path );
        $prefix = ( 0 === strpos( $path, '/' ) ) ? '/' : '';
        $parts = array();
        foreach ( explode( '/', $path ) as $part ) {
            if ( '' === $part || '.' === $part ) { continue; }
            if ( '..' === $part ) { array_pop( $parts ); continue; }
            $parts[] = $part;
        }
        return $prefix . implode( '/', $parts );
    }

    private static function read_shared_strings( $path ) {
        if ( ! file_exists( $path ) ) { return array(); }
        $xml = file_get_contents( $path );
        if ( false === $xml ) { return array(); }
        $out = array();
        if ( preg_match_all( '/<(?:[A-Za-z0-9_]+:)?si\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?si>/us', $xml, $items ) ) {
            foreach ( $items[1] as $item ) {
                $parts = array();
                if ( preg_match_all( '/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/us', $item, $texts ) ) {
                    foreach ( $texts[1] as $text ) { $parts[] = self::xml_text( $text ); }
                }
                $out[] = implode( '', $parts );
            }
        }
        return $out;
    }

    private static function read_sheet_xml( $path, $shared ) {
        $xml = file_get_contents( $path );
        if ( false === $xml ) { return new WP_Error( 'mdg_xlsx_sheet_read', 'Excel veri sayfası okunamadı.' ); }
        $rows = array();

        if ( ! preg_match_all( '/<(?:[A-Za-z0-9_]+:)?row\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?row>/us', $xml, $row_matches ) ) { return array(); }
        foreach ( $row_matches[1] as $row_xml ) {
            if ( count( $rows ) >= self::MAX_ROWS + 1 ) { break; }
            $row = array();

            /*
             * Boş XLSX hücreleri çoğu üreticide <c ... /> olarak yazılır.
             * Bunlar paired-cell regex'ine bırakılırsa regex bir sonraki </c>
             * etiketine kadar uzayıp sütunları kaydırabilir. Boş hücreleri önce
             * kaldırıyoruz; dolu hücrelerin A1/F2/L2 gibi referansları sayesinde
             * eksik kolonlar aşağıda tekrar güvenle boş olarak doldurulur.
             */
            $row_xml = preg_replace( '/<(?:[A-Za-z0-9_.-]+:)?c\b[^>]*\/\s*>/u', '', $row_xml );

            if ( preg_match_all( '/<(?:[A-Za-z0-9_.-]+:)?c\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_.-]+:)?c>/us', $row_xml, $cells, PREG_SET_ORDER ) ) {
                foreach ( $cells as $cell ) {
                    $attrs = $cell[1];
                    $body  = $cell[2];
                    if ( ! preg_match( '/\br="([A-Z]+)\d+"/', $attrs, $ref ) ) { continue; }
                    $idx = self::column_index( $ref[1] );
                    $type = '';
                    if ( preg_match( '/\bt="([^"]+)"/', $attrs, $tm ) ) { $type = $tm[1]; }
                    $value = '';
                    if ( 'inlineStr' === $type && preg_match( '/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/us', $body, $vm ) ) {
                        $value = self::xml_text( $vm[1] );
                    } elseif ( preg_match( '/<(?:[A-Za-z0-9_]+:)?v\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/us', $body, $vm ) ) {
                        $raw = self::xml_text( $vm[1] );
                        if ( 's' === $type ) {
                            $si = (int) $raw;
                            $value = isset( $shared[ $si ] ) ? $shared[ $si ] : '';
                        } else {
                            $value = $raw;
                        }
                    }
                    $row[ $idx ] = trim( (string) $value );
                }
            }
            if ( $row ) {
                $max = max( array_keys( $row ) );
                $dense = array_fill( 0, $max + 1, '' );
                foreach ( $row as $i => $v ) { $dense[ $i ] = $v; }
                $rows[] = $dense;
            }
        }
        return $rows;
    }

    private static function xml_text( $value ) {
        $value = preg_replace( '/<!\[CDATA\[(.*?)\]\]>/us', '$1', (string) $value );
        return html_entity_decode( strip_tags( $value ), ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

    private static function column_index( $letters ) {
        $letters = strtoupper( $letters );
        $n = 0;
        for ( $i = 0, $l = strlen( $letters ); $i < $l; $i++ ) { $n = $n * 26 + ( ord( $letters[ $i ] ) - 64 ); }
        return max( 0, $n - 1 );
    }

    private static function import_rows( $rows ) {
        if ( count( $rows ) < 2 ) { return new WP_Error( 'mdg_import_empty', 'Dosyada başlık ve veri satırları bulunamadı.' ); }

        $header_index = -1;
        $headers = array();
        foreach ( $rows as $i => $row ) {
            $candidate = array_map( array( __CLASS__, 'normalize_header' ), $row );
            if ( in_array( 'province', $candidate, true ) && in_array( 'venue_name', $candidate, true ) ) {
                $header_index = $i; $headers = $candidate; break;
            }
        }
        if ( $header_index < 0 ) {
            return new WP_Error( 'mdg_import_headers', 'Beklenen başlıklar bulunamadı. “V2 İçe Aktarım” sayfasını kullanın.' );
        }

        $required = array( 'province', 'district_or_area', 'venue_name' );
        foreach ( $required as $key ) {
            if ( ! in_array( $key, $headers, true ) ) { return new WP_Error( 'mdg_import_header_' . $key, 'Zorunlu sütun eksik: ' . $key ); }
        }

        $map = array();
        foreach ( $headers as $i => $name ) { if ( $name ) { $map[ $name ] = $i; } }

        $result = array( 'inserted'=>0, 'merged'=>0, 'district_synced'=>0, 'ambiguous'=>0, 'skipped'=>0, 'incomplete'=>0, 'errors'=>0 );
        foreach ( array_slice( $rows, $header_index + 1, self::MAX_ROWS ) as $row ) {
            $province = self::cell( $row, $map, 'province' );
            $district = self::cell( $row, $map, 'district_or_area' );
            $name     = self::cell( $row, $map, 'venue_name' );
            if ( '' === $province && '' === $district && '' === $name ) { continue; }
            if ( '' === $province || '' === $district || '' === $name ) { $result['skipped']++; continue; }

            $code = self::province_code( $province );
            if ( ! $code ) { $result['skipped']++; continue; }

            $address = sanitize_textarea_field( self::cell( $row, $map, 'address' ) );
            $maps_url_raw = self::cell( $row, $map, 'maps_url' );
            $maps_url = '';
            if ( $maps_url_raw ) {
                $maps_url = esc_url_raw( $maps_url_raw, array( 'https' ) );
                if ( ! $maps_url || 0 !== stripos( $maps_url, 'https://' ) ) { $maps_url = ''; }
            }

            $capacity_raw = self::cell( $row, $map, 'default_capacity' );
            $duration_raw = self::cell( $row, $map, 'default_duration' );
            $status_raw   = strtolower( remove_accents( self::cell( $row, $map, 'status' ) ) );
            $is_active    = in_array( $status_raw, array( 'inactive', 'pasif', '0', 'no', 'hayir' ), true ) ? 0 : 1;
            $notes        = sanitize_textarea_field( self::cell( $row, $map, 'operation_notes' ) );

            $data = array(
                'province_code'    => $code,
                'province_name'    => MDG_Venues::provinces()[ $code ],
                'district'         => MDG_Venues::canonical_district_name( $code, sanitize_text_field( $district ) ),
                'name'             => sanitize_text_field( $name ),
                'address'          => $address,
                'default_capacity' => $capacity_raw !== '' ? max( 1, absint( $capacity_raw ) ) : 500,
                'default_duration' => $duration_raw !== '' ? min( 360, max( 15, absint( $duration_raw ) ) ) : 60,
                'maps_url'         => $maps_url,
                'latitude'         => self::nullable_float( self::cell( $row, $map, 'latitude' ), -90, 90 ),
                'longitude'        => self::nullable_float( self::cell( $row, $map, 'longitude' ), -180, 180 ),
                'contact_name'     => sanitize_text_field( self::cell( $row, $map, 'contact_name' ) ),
                'contact_phone'    => sanitize_text_field( self::cell( $row, $map, 'contact_phone' ) ),
                'notes'            => $notes,
                'is_active'        => $is_active,
                'updated_at'       => MDG_DB::now(),
            );

            if ( '' === $data['address'] ) { $result['incomplete']++; }
            $saved = self::upsert( $data );
            if ( 'inserted' === $saved ) { $result['inserted']++; }
            elseif ( 'merged' === $saved ) { $result['merged']++; }
            elseif ( 'district_synced' === $saved ) { $result['district_synced']++; }
            elseif ( 'ambiguous' === $saved ) { $result['ambiguous']++; }
            elseif ( 'skipped' === $saved ) { $result['skipped']++; }
            else { $result['errors']++; }
        }
        return $result;
    }

    private static function normalize_header( $value ) {
        $value = strtolower( remove_accents( trim( (string) $value ) ) );
        $value = preg_replace( '/[^a-z0-9]+/', '_', $value );
        return trim( $value, '_' );
    }

    private static function cell( $row, $map, $key ) {
        return isset( $map[ $key ], $row[ $map[ $key ] ] ) ? trim( (string) $row[ $map[ $key ] ] ) : '';
    }

    private static function province_code( $province ) {
        $needle = strtolower( remove_accents( trim( (string) $province ) ) );
        foreach ( MDG_Venues::provinces() as $code => $name ) {
            if ( strtolower( remove_accents( $name ) ) === $needle ) { return $code; }
        }
        if ( preg_match( '/^\d{1,2}$/', $needle ) ) {
            $code = str_pad( $needle, 2, '0', STR_PAD_LEFT );
            if ( isset( MDG_Venues::provinces()[ $code ] ) ) { return $code; }
        }
        return '';
    }

    private static function nullable_float( $value, $min, $max ) {
        $value = str_replace( ',', '.', trim( (string) $value ) );
        if ( '' === $value || ! is_numeric( $value ) ) { return null; }
        $f = (float) $value;
        return ( $f >= $min && $f <= $max ) ? $f : null;
    }

    /**
     * Güvenli senkronizasyon:
     * - Önce il + normalize ilçe + normalize salon adı ile eşleşir.
     * - Eşleşme yoksa aynı ilde normalize salon adı tek bir kayıtla eşleşiyorsa ve mevcut ilçe
     *   eski/generic/geçersiz iken gelen ilçe daha spesifik ve geçerliyse aynı salon ID'sinin ilçesini düzeltir.
     * - Aynı isim farklı resmi ilçelerde bulunabiliyorsa otomatik birleştirme yapmaz.
     * - Mevcut dolu adres/Maps/iletişim alanları boş Excel hücreleriyle asla silinmez.
     */
    private static function upsert( $data ) {
        global $wpdb;
        $table = MDG_DB::table( 'venues' );

        $all_in_province = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE province_code=%s",
            $data['province_code']
        ) );

        $incoming_name_key     = MDG_Venues::normalize_place_key( $data['name'] );
        $incoming_district_key = MDG_Venues::normalize_place_key( $data['district'] );
        $name_candidates       = array();
        $exact                  = null;

        foreach ( (array) $all_in_province as $candidate ) {
            if ( MDG_Venues::normalize_place_key( $candidate->name ) !== $incoming_name_key ) { continue; }
            $name_candidates[] = $candidate;
            if ( MDG_Venues::normalize_place_key( $candidate->district ) === $incoming_district_key ) {
                $exact = $candidate;
                break;
            }
        }

        if ( $exact ) {
            return self::merge_existing( $exact, $data, false );
        }

        /*
         * Eski arşivlerde "Eskişehir", "Mersin", "Van", "Altınpark", "İncek" gibi ilçe yerine
         * il/bölge adı yazılmış kayıtlar bulunabiliyor. Aynı ilde aynı salon adı TEK kayıt ise ve
         * mevcut ilçe generic/geçersiz, gelen ilçe resmi/geçerli ise salonu çoğaltmak yerine aynı ID'yi düzelt.
         */
        if ( 1 === count( $name_candidates ) ) {
            $candidate = $name_candidates[0];
            $current_generic  = self::district_is_generic_or_invalid( $data['province_code'], $candidate->province_name, $candidate->district );
            $incoming_generic = self::district_is_generic_or_invalid( $data['province_code'], $data['province_name'], $data['district'] );

            if ( $current_generic && ! $incoming_generic ) {
                // Hedef ilçede aynı isimli başka kayıt varsa güvenlik gereği otomatik düzeltme yapma.
                $target_conflict = false;
                foreach ( (array) $all_in_province as $other ) {
                    if ( (int) $other->id === (int) $candidate->id ) { continue; }
                    if ( MDG_Venues::normalize_place_key( $other->name ) === $incoming_name_key &&
                         MDG_Venues::normalize_place_key( $other->district ) === $incoming_district_key ) {
                        $target_conflict = true;
                        break;
                    }
                }
                if ( ! $target_conflict ) {
                    return self::merge_existing( $candidate, $data, true );
                }
            }

            // Gelen ilçe generic, mevcut kayıt daha spesifik ise mevcut ilçeyi koruyarak eksik alanları tamamla.
            if ( ! $current_generic && $incoming_generic ) {
                $keep = $data;
                $keep['district'] = (string) $candidate->district;
                return self::merge_existing( $candidate, $keep, false );
            }
        }

        /* Aynı isim birden fazla farklı ilçede varsa yeni kayıt olabilir; güvenlik için otomatik birleştirme yok. */
        $was_ambiguous = count( $name_candidates ) > 0;

        $data['created_at'] = MDG_DB::now();
        $ok = $wpdb->insert( $table, $data );
        if ( false === $ok ) { return 'error'; }
        $id = (int) $wpdb->insert_id;
        self::audit( 'venue.import_created', $id, array(
            'source'             => 'file',
            'incomplete_address' => empty( $data['address'] ),
            'same_name_other_district' => $was_ambiguous,
        ) );
        return $was_ambiguous ? 'ambiguous' : 'inserted';
    }

    private static function merge_existing( $existing, $data, $sync_district ) {
        global $wpdb;
        $table = MDG_DB::table( 'venues' );
        $update = array( 'updated_at' => MDG_DB::now() );

        if ( $sync_district && MDG_Venues::normalize_place_key( $existing->district ) !== MDG_Venues::normalize_place_key( $data['district'] ) ) {
            $update['district'] = $data['district'];
        }

        $fillable = array( 'address','maps_url','latitude','longitude','contact_name','contact_phone','notes' );
        foreach ( $fillable as $field ) {
            $incoming = $data[ $field ];
            $current  = $existing->$field;
            if ( ( null === $current || '' === trim( (string) $current ) ) && null !== $incoming && '' !== trim( (string) $incoming ) ) {
                $update[ $field ] = $incoming;
            }
        }
        if ( (int) $existing->default_capacity < 1 ) { $update['default_capacity'] = $data['default_capacity']; }
        if ( (int) $existing->default_duration < 15 ) { $update['default_duration'] = $data['default_duration']; }

        if ( count( $update ) > 1 ) {
            $ok = $wpdb->update( $table, $update, array( 'id' => (int) $existing->id ) );
            if ( false === $ok ) { return 'error'; }
            self::audit( $sync_district ? 'venue.import_district_synced' : 'venue.import_merged', (int) $existing->id, array(
                'source'       => 'file',
                'old_district' => (string) $existing->district,
                'new_district' => $sync_district ? (string) $data['district'] : (string) $existing->district,
            ) );
            return $sync_district ? 'district_synced' : 'merged';
        }
        return 'skipped';
    }

    private static function district_is_generic_or_invalid( $province_code, $province_name, $district ) {
        $district_key = MDG_Venues::normalize_place_key( $district );
        $province_key = MDG_Venues::normalize_place_key( $province_name );
        if ( '' === $district_key ) { return true; }
        if ( 'merkez' === $district_key || $district_key === $province_key ) { return true; }
        return ! MDG_Venues::district_is_valid( $province_code, $district );
    }

    private static function audit( $action_key, $id, $context ) {
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

    private static function redirect_result( $result ) {
        $args = array(
            'page'           => 'mdg-venues',
            'mdg_imported'   => absint( $result['inserted'] ),
            'mdg_merged'     => absint( $result['merged'] ),
            'mdg_district_synced' => absint( $result['district_synced'] ?? 0 ),
            'mdg_ambiguous'  => absint( $result['ambiguous'] ?? 0 ),
            'mdg_skipped'    => absint( $result['skipped'] ),
            'mdg_incomplete' => absint( $result['incomplete'] ),
            'mdg_errors'     => absint( $result['errors'] ),
        );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function redirect_error( $message ) {
        wp_safe_redirect( add_query_arg( array( 'page'=>'mdg-venues', 'mdg_import_error'=>rawurlencode( $message ) ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
