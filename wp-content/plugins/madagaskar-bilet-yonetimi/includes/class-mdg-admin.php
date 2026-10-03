<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Admin {
    public function hooks() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_mdg_save_venue', array( 'MDG_Venues', 'save_from_request' ) );
        add_action( 'admin_post_mdg_import_venues', array( 'MDG_Venue_Importer', 'import_from_request' ) );
        add_action( 'admin_post_mdg_save_event_draft', array( 'MDG_Events', 'save_draft_from_request' ) );
        add_action( 'admin_post_mdg_preview_event_import', array( 'MDG_Event_Importer', 'preview_from_request' ) );
        add_action( 'admin_post_mdg_commit_event_import', array( 'MDG_Event_Importer', 'commit_from_request' ) );
        add_action( 'admin_post_mdg_duplicate_event_draft', array( 'MDG_Events', 'duplicate_draft_from_request' ) );
    }

    public function menu() {
        add_menu_page( 'Madagaskar Bilet Yönetimi', 'Madagaskar', 'manage_woocommerce', 'mdg-dashboard', array( $this, 'dashboard' ), 'dashicons-tickets-alt', 56 );
        add_submenu_page( 'mdg-dashboard', 'Genel Bakış', 'Genel Bakış', 'manage_woocommerce', 'mdg-dashboard', array( $this, 'dashboard' ) );
        add_submenu_page( 'mdg-dashboard', 'Etkinlik Yayınla', 'Etkinlik Yayınla', 'manage_woocommerce', 'mdg-publish', array( $this, 'publish_event' ) );
        add_submenu_page( 'mdg-dashboard', 'Salonlar', 'Salonlar', 'manage_woocommerce', 'mdg-venues', array( $this, 'venues' ) );
        add_submenu_page( 'mdg-dashboard', 'Yayındaki Etkinlikler', 'Yayındaki Etkinlikler', 'manage_woocommerce', 'mdg-live-events', array( $this, 'events_live' ) );
        add_submenu_page( 'mdg-dashboard', 'Süresi Dolanlar', 'Süresi Dolanlar', 'manage_woocommerce', 'mdg-past-events', array( $this, 'events_past' ) );
        add_submenu_page( 'mdg-dashboard', 'İptal / Erteleme', 'İptal / Erteleme', 'manage_woocommerce', 'mdg-cancel', array( $this, 'cancel_refund' ) );
        add_submenu_page( 'mdg-dashboard', 'Satış Raporları', 'Satış Raporları', 'manage_woocommerce', 'mdg-reports', array( $this, 'reports' ) );
        add_submenu_page( 'mdg-dashboard', 'Müşteri / Bilet Listeleri', 'Müşteri / Bilet Listeleri', 'manage_woocommerce', 'mdg-customers', array( $this, 'customers' ) );
        add_submenu_page( 'mdg-dashboard', 'Ayarlar', 'Ayarlar', 'manage_woocommerce', 'mdg-settings', array( $this, 'settings' ) );
    }

    public function assets( $hook ) {
        if ( false === strpos( $hook, 'mdg-' ) ) { return; }
        wp_enqueue_style( 'mdg-admin', MDG_BILET_URL . 'assets/admin.css', array(), MDG_BILET_VERSION );
        wp_enqueue_media();
        wp_enqueue_script( 'mdg-qrcodejs', MDG_BILET_URL . 'assets/vendor/qrcodejs/qrcode.min.js', array(), '1.0.0', true );
        wp_enqueue_script( 'mdg-admin', MDG_BILET_URL . 'assets/admin.js', array( 'mdg-qrcodejs' ), MDG_BILET_VERSION, true );
        $venue_rows = array();
        foreach ( MDG_Venues::all( true ) as $v ) {
            $venue_rows[] = array(
                'id' => (int) $v->id,
                'province_code' => (string) $v->province_code,
                'province_name' => (string) $v->province_name,
                'district' => (string) $v->district,
                'name' => (string) $v->name,
                'address' => (string) $v->address,
                'default_capacity' => (int) $v->default_capacity,
                'default_duration' => (int) $v->default_duration,
                'maps_url' => (string) $v->maps_url,
                'location_qr_attachment_id' => (int) $v->location_qr_attachment_id,
                'address_complete' => ! empty( trim( (string) $v->address ) ),
            );
        }
        wp_localize_script( 'mdg-admin', 'MDG_VENUE_DATA', array(
            'districts' => MDG_Venues::districts(),
            'venues' => $venue_rows,
        ) );
    }

    private function header( $title, $desc = '' ) {
        echo '<div class="wrap mdg-wrap"><h1>🎪 ' . esc_html( $title ) . '</h1>';
        if ( $desc ) { echo '<p class="mdg-lead">' . esc_html( $desc ) . '</p>'; }
    }

    private function footer() { echo '</div>'; }

    public function dashboard() {
        global $wpdb;
        $this->header( 'Madagaskar Bilet Yönetimi', 'Yeni sistemin tek yönetim merkezi. Bu temel sürüm mevcut satış akışına müdahale etmez.' );
        $v = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MDG_DB::table( 'venues' ) );
        $e = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . MDG_DB::table( 'events' ) . " WHERE status IN ('onsale','closed','soldout','postponed')" );
        $s = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MDG_DB::table( 'sessions' ) );
        echo '<div class="mdg-cards">';
        $cards = array( 'Kayıtlı Salonlar' => $v, 'Aktif/İzlenen Etkinlikler' => $e, 'Seans Kayıtları' => $s, 'Sürüm' => MDG_BILET_VERSION );
        foreach ( $cards as $label => $value ) { echo '<div class="mdg-card"><div class="mdg-card-value">' . esc_html( $value ) . '</div><div>' . esc_html( $label ) . '</div></div>'; }
        echo '</div><div class="notice notice-info inline"><p><strong>V2.9.1 aşaması:</strong> Ankara canlı satış zinciri ve dinamik salon QR doğrulandı. Yeni şehirler için Üretim Planı önce salt-okunur doğrulama yapar; plan yeşilse V2.9.1 satış nesnelerini yalnızca TASLAK olarak oluşturabilir. Halka açma ayrı kontroldür.</p></div>';
        $this->footer();
    }

    public function venues() {
        $edit = isset( $_GET['edit'] ) ? MDG_Venues::get( absint( $_GET['edit'] ) ) : null;
        $this->header( 'Salonlar', 'Bir salonu yalnızca bir kez tanımlayın; sonraki etkinliklerde listeden seçin. Harita bağlantısından salon konum QR’ı otomatik hazırlanır.' );
        if ( isset( $_GET['mdg_saved'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Salon kaydedildi.</p></div>';
        if ( isset( $_GET['mdg_warning'] ) ) echo '<div class="notice notice-warning"><p>' . esc_html( wp_unslash( $_GET['mdg_warning'] ) ) . '</p></div>';
        if ( isset( $_GET['mdg_error'] ) ) echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['mdg_error'] ) ) . '</p></div>';
        if ( isset( $_GET['mdg_event_import_error'] ) ) echo '<div class="notice notice-error"><p><strong>Etkinlik Excel:</strong> ' . esc_html( wp_unslash( $_GET['mdg_event_import_error'] ) ) . '</p></div>';
        if ( isset( $_GET['mdg_event_import_done'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Etkinlik Excel içe aktarımı tamamlandı.</strong> Yeni taslak: ' . esc_html( absint( $_GET['mdg_event_inserted'] ?? 0 ) ) . ' · Güncellenen taslak: ' . esc_html( absint( $_GET['mdg_event_updated'] ?? 0 ) ) . '</p></div>';
        }
        if ( isset( $_GET['mdg_duplicated'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Etkinlik taslağı kopyalandı. Yeni kopyayı düzenleyebilirsiniz.</p></div>';
        if ( isset( $_GET['mdg_import_error'] ) ) echo '<div class="notice notice-error"><p><strong>Salon içe aktarma:</strong> ' . esc_html( wp_unslash( $_GET['mdg_import_error'] ) ) . '</p></div>';
        if ( isset( $_GET['mdg_imported'] ) ) {
            $imported = absint( $_GET['mdg_imported'] ); $merged = absint( $_GET['mdg_merged'] ?? 0 ); $district_synced = absint( $_GET['mdg_district_synced'] ?? 0 ); $ambiguous = absint( $_GET['mdg_ambiguous'] ?? 0 ); $skipped = absint( $_GET['mdg_skipped'] ?? 0 ); $incomplete = absint( $_GET['mdg_incomplete'] ?? 0 ); $errors = absint( $_GET['mdg_errors'] ?? 0 );
            echo '<div class="notice notice-success is-dismissible"><p><strong>Salon arşivi senkronize edildi.</strong> Yeni: ' . esc_html( $imported ) . ' · Birleştirilen: ' . esc_html( $merged ) . ' · İlçesi düzeltilen: ' . esc_html( $district_synced ) . ' · Aynı isim/farklı ilçe güvenli ayrılan: ' . esc_html( $ambiguous ) . ' · Atlanan: ' . esc_html( $skipped ) . ' · Adresi eksik: ' . esc_html( $incomplete ) . ' · Hata: ' . esc_html( $errors ) . '</p></div>';
        }

        $provinces = MDG_Venues::provinces();

        echo '<div class="mdg-panel mdg-import-panel"><h2>Salon Arşivini İçe Aktar</h2><p>Son sezon salon listenizi <strong>.xlsx</strong> veya <strong>.csv</strong> olarak topluca aktarın. Excel dosyasında öncelikle <strong>V2 İçe Aktarım</strong> sayfası kullanılır. Mevcut salonlar silinmez. Aynı salonın eski kaydında ilçe yerine il/bölge adı yazılmışsa ve yeni dosyada daha doğru resmi ilçe tekil biçimde bulunuyorsa salon ID’si korunarak ilçe senkronize edilir. Aynı isim farklı gerçek ilçelerde bulunuyorsa otomatik birleştirme yapılmaz.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="mdg-import-form">';
        echo '<input type="hidden" name="action" value="mdg_import_venues">'; wp_nonce_field( 'mdg_import_venues', 'mdg_import_nonce' );
        echo '<input type="file" name="venue_file" accept=".xlsx,.csv" required> ';
        submit_button( 'Salonları İçe Aktar', 'secondary', 'submit', false );
        echo '<p class="description"><strong>Güvenlik:</strong> İçe aktarım hiçbir salonu silmez; mevcut dolu adres/Maps/iletişim bilgisinin üzerine boş değer yazmaz ve aynı isimli farklı ilçe salonlarını körlemesine birleştirmez. Adresi eksik geçmiş salonlar sisteme alınabilir; etkinlik yayına alınmadan önce tamamlanmaları gerekir.</p></form></div>';

        $existing_qr_url = $edit ? MDG_QR::attachment_url( $edit->location_qr_attachment_id ?? 0 ) : '';
        $has_existing_qr = $existing_qr_url ? '1' : '0';
        $current_maps_url = $edit->maps_url ?? '';

        echo '<div class="mdg-two-col"><div class="mdg-panel"><h2>' . ( $edit ? 'Salonu Düzenle' : 'Yeni Salon Tanımla' ) . '</h2><p class="description">İl seçildiğinde ilçe listesi otomatik gelir. Salon bir kez kaydedilir; sonraki etkinliklerde listeden seçilir.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-mdg-venue-form data-mdg-current-maps-url="' . esc_attr( $current_maps_url ) . '" data-mdg-has-existing-qr="' . esc_attr( $has_existing_qr ) . '">';
        echo '<input type="hidden" name="action" value="mdg_save_venue"><input type="hidden" name="venue_id" value="' . esc_attr( $edit->id ?? 0 ) . '">';
        echo '<input type="hidden" name="qr_data_url" value="" data-mdg-qr-data><input type="hidden" name="force_qr" value="0" data-mdg-force-qr>';
        wp_nonce_field( 'mdg_save_venue', 'mdg_nonce' );

        echo '<label>İl</label><select name="province_code" data-mdg-province required><option value="">Seçiniz</option>';
        foreach ( $provinces as $code => $name ) { echo '<option value="' . esc_attr( $code ) . '" ' . selected( $edit->province_code ?? '', $code, false ) . '>' . esc_html( $name ) . '</option>'; }
        echo '</select>';
        echo '<label>İlçe</label><select name="district" data-mdg-district data-selected="' . esc_attr( $edit->district ?? '' ) . '" required><option value="">Önce il seçiniz</option></select>';
        echo '<div data-mdg-district-manual-wrap style="display:none"><label>İlçe (manuel)</label><input name="district_manual" data-mdg-district-manual value="" placeholder="İlçe adını yazın"><p class="description">İlçe listesi yüklenemezse veya yeni bir idari değişiklik varsa kullanın.</p></div>';

        echo '<label>Salon adı</label><input name="name" required value="' . esc_attr( $edit->name ?? '' ) . '">';
        echo '<label>Açık adres</label><textarea name="address" rows="4" required>' . esc_textarea( $edit->address ?? '' ) . '</textarea>';
        echo '<div class="mdg-grid-2"><div><label>Varsayılan kapasite</label><input type="number" min="1" name="default_capacity" value="' . esc_attr( $edit->default_capacity ?? 500 ) . '"></div><div><label>Varsayılan seans süresi (dk)</label><input type="number" min="15" max="360" name="default_duration" value="' . esc_attr( $edit->default_duration ?? 60 ) . '"></div></div>';
        echo '<div class="mdg-grid-2"><div><label>Enlem <span class="description">(opsiyonel)</span></label><input type="number" step="0.0000001" name="latitude" value="' . esc_attr( $edit->latitude ?? '' ) . '"></div><div><label>Boylam <span class="description">(opsiyonel)</span></label><input type="number" step="0.0000001" name="longitude" value="' . esc_attr( $edit->longitude ?? '' ) . '"></div></div>';

        echo '<div class="mdg-map-box"><label><strong>Google Maps / Harita bağlantısı</strong></label><input type="url" name="maps_url" data-mdg-maps-url value="' . esc_attr( $current_maps_url ) . '" placeholder="https://maps.app.goo.gl/…">';
        echo '<p class="mdg-field-note">Bu bağlantı biletteki <strong>Salon Konumu</strong> QR kodunun hedefidir. Harici QR servisi kullanılmaz.</p>';
        echo '<div class="mdg-map-actions"><a class="button" target="_blank" rel="noopener noreferrer" data-mdg-open-map href="' . esc_url( $current_maps_url ?: '#' ) . '" style="' . ( $current_maps_url ? '' : 'display:none' ) . '">Konumu Aç</a><button class="button" type="button" data-mdg-regenerate-qr>QR’ı Yeniden Oluştur</button></div>';
        echo '<div class="mdg-qr-preview" data-mdg-qr-preview>';
        if ( $existing_qr_url ) {
            echo '<img src="' . esc_url( $existing_qr_url ) . '" alt="Salon Konumu QR"><div><span class="mdg-qr-status is-success" data-mdg-qr-status>QR hazır</span><p class="mdg-field-note">Son güncelleme: ' . esc_html( $edit->qr_updated_at ?? '-' ) . '</p></div>';
        } else {
            echo '<div><span class="mdg-qr-status is-muted" data-mdg-qr-status>Harita bağlantısı girildiğinde QR otomatik hazırlanır.</span></div>';
        }
        echo '</div></div>';

        echo '<div class="mdg-grid-2"><div><label>Salon yetkilisi <span class="description">(opsiyonel)</span></label><input name="contact_name" value="' . esc_attr( $edit->contact_name ?? '' ) . '"></div><div><label>Yetkili telefonu <span class="description">(opsiyonel)</span></label><input name="contact_phone" value="' . esc_attr( $edit->contact_phone ?? '' ) . '"></div></div>';
        echo '<label>Operasyon notları</label><textarea name="notes" rows="3">' . esc_textarea( $edit->notes ?? '' ) . '</textarea>';
        echo '<label class="mdg-check"><input type="checkbox" name="is_active" value="1" ' . checked( isset( $edit->is_active ) ? $edit->is_active : 1, 1, false ) . '> Aktif salon</label>';
        submit_button( $edit ? 'Salonu Güncelle' : 'Salonu Kaydet' );
        echo '</form></div>';

        echo '<div class="mdg-panel"><h2>Kayıtlı Salonlar</h2>';
        $rows = MDG_Venues::all();
        if ( ! $rows ) { echo '<p>Henüz salon kaydı yok.</p>'; } else {
            echo '<div class="mdg-venue-filters" data-mdg-venue-filters>';
            echo '<div><label>Salon / adres ara</label><input type="search" placeholder="Örn: ANFA, Altınpark…" data-mdg-venue-search></div>';
            echo '<div><label>İl</label><select data-mdg-venue-filter-province><option value="">Tüm iller</option>';
            $used_provinces = array(); foreach ( $rows as $r ) { $used_provinces[ (string) $r->province_name ] = (string) $r->province_name; } ksort( $used_provinces, SORT_NATURAL | SORT_FLAG_CASE );
            foreach ( $used_provinces as $pname ) { echo '<option value="' . esc_attr( $pname ) . '">' . esc_html( $pname ) . '</option>'; }
            echo '</select></div>';
            echo '<div><label>İlçe</label><select data-mdg-venue-filter-district><option value="">Tüm ilçeler</option></select></div>';
            echo '<div><label>Durum</label><select data-mdg-venue-filter-status><option value="">Tümü</option><option value="active">Aktif</option><option value="inactive">Pasif</option></select></div>';
            echo '<div><label>Eksik bilgi</label><select data-mdg-venue-filter-info><option value="">Tümü</option><option value="address_missing">Adres eksik</option><option value="maps_missing">Maps eksik</option><option value="qr_missing">QR eksik</option><option value="complete">Bilgileri tam</option></select></div>';
            echo '<div class="mdg-venue-filter-actions"><button type="button" class="button" data-mdg-venue-filter-clear>Filtreleri Temizle</button><span data-mdg-venue-filter-count></span></div>';
            echo '</div>';
            echo '<div class="mdg-table-scroll"><table class="widefat striped" data-mdg-venue-table><thead><tr><th>İl / İlçe</th><th>Salon</th><th>Kapasite</th><th>Konum QR</th><th>Durum</th><th></th></tr></thead><tbody>';
            foreach ( $rows as $row ) {
                $url = add_query_arg( array( 'page' => 'mdg-venues', 'edit' => $row->id ), admin_url( 'admin.php' ) );
                $qr_url = MDG_QR::attachment_url( $row->location_qr_attachment_id ?? 0 );
                $address_complete = ! empty( trim( (string) $row->address ) );
                $maps_complete = ! empty( trim( (string) $row->maps_url ) );
                $qr_complete = ! empty( $qr_url );
                echo '<tr data-mdg-venue-row data-province="' . esc_attr( $row->province_name ) . '" data-district="' . esc_attr( $row->district ) . '" data-status="' . ( $row->is_active ? 'active' : 'inactive' ) . '" data-address-complete="' . ( $address_complete ? '1' : '0' ) . '" data-maps-complete="' . ( $maps_complete ? '1' : '0' ) . '" data-qr-complete="' . ( $qr_complete ? '1' : '0' ) . '"><td>' . esc_html( $row->province_name . ' / ' . $row->district ) . '</td><td><strong>' . esc_html( $row->name ) . '</strong><br><small>' . esc_html( $row->address ) . '</small>';
                if ( $row->maps_url ) { echo '<br><a href="' . esc_url( $row->maps_url ) . '" target="_blank" rel="noopener noreferrer">Haritayı aç</a>'; }
                echo '</td><td>' . esc_html( $row->default_capacity ) . '</td><td>';
                if ( $qr_url ) { echo '<img class="mdg-table-qr" src="' . esc_url( $qr_url ) . '" alt="Konum QR"><br><small>Hazır</small>'; }
                elseif ( $row->maps_url ) { echo '<span class="mdg-status is-inactive">QR eksik</span>'; }
                else { echo '<span class="description">Harita yok</span>'; }
                echo '</td><td><span class="mdg-status ' . ( $row->is_active ? 'is-active' : 'is-inactive' ) . '">' . ( $row->is_active ? 'Aktif' : 'Pasif' ) . '</span>';
                if ( ! $address_complete ) { echo '<br><span class="mdg-status is-warning">Adres eksik</span>'; }
                echo '</td><td><a href="' . esc_url( $url ) . '">Düzenle</a></td></tr>';
            }
            echo '</tbody></table></div><p class="mdg-no-filter-results" data-mdg-venue-no-results hidden>Bu filtrelere uyan salon bulunamadı.</p>';
        }
        echo '</div></div>';
        $this->footer();
    }

    public function publish_event() {
        $edit = isset( $_GET['edit'] ) ? MDG_Events::get( absint( $_GET['edit'] ) ) : null;
        if ( $edit && 'draft' !== $edit->status ) { $edit = null; }
        $this->header( 'Etkinlik Yayınla', 'Etkinlik içeriği, salon, seanslar, ortak kapasite ve bilet fiyatlarını tek ekranda hazırlayın. Önce V2.9 Üretim Planını doğrulayın; ardından V2.9.1 ile WooCommerce/Tickera satış nesnelerini yalnızca taslak olarak oluşturun.' );
        echo '<p class="description"><strong>Madagaskar Bilet Yönetimi sürümü:</strong> ' . esc_html( MDG_BILET_VERSION ) . '</p>';

        if ( isset( $_GET['mdg_saved'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Etkinlik taslağı, seanslar ve bilet türleri kaydedildi.</p></div>';
        if ( isset( $_GET['mdg_error'] ) ) echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['mdg_error'] ) ) . '</p></div>';
        if ( isset( $_GET['mdg_event_import_error'] ) ) echo '<div class="notice notice-error"><p><strong>Etkinlik Excel:</strong> ' . esc_html( wp_unslash( $_GET['mdg_event_import_error'] ) ) . '</p></div>';
        if ( isset( $_GET['mdg_event_import_done'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Etkinlik Excel içe aktarımı tamamlandı.</strong> Yeni taslak: ' . esc_html( absint( $_GET['mdg_event_inserted'] ?? 0 ) ) . ' · Güncellenen taslak: ' . esc_html( absint( $_GET['mdg_event_updated'] ?? 0 ) ) . '</p></div>';
        }
        if ( isset( $_GET['mdg_duplicated'] ) ) echo '<div class="notice notice-success is-dismissible"><p>Etkinlik taslağı kopyalandı. Yeni kopyayı düzenleyebilirsiniz.</p></div>';

        $provinces = MDG_Venues::provinces();
        $hero_id = $edit ? absint( $edit->hero_attachment_id ) : 0;
        $hero_url = $hero_id ? wp_get_attachment_image_url( $hero_id, 'medium_large' ) : '';
        $gallery_ids = $edit ? MDG_Events::gallery_ids( $edit ) : array();
        $faq_rows = $edit ? MDG_Events::faq_items( $edit ) : array();
        if ( ! $faq_rows ) { $faq_rows = array( array( 'question'=>'', 'answer'=>'' ) ); }
        $organizer_default = 'Dünya Organizasyon Medya Turizm Eğitim Danışmanlık Reklam Seyahat Acenteliği Ltd. Şti.';
        $session_rows = array();
        $ticket_rows = array();
        if ( $edit ) {
            foreach ( MDG_Sessions::by_event( $edit->id ) as $session ) {
                list( $local_date, $local_time ) = MDG_Sessions::local_parts( $session->start_at );
                $session_rows[] = array( 'date' => $local_date, 'time' => $local_time, 'capacity' => (int) $session->capacity_total );
            }
            foreach ( MDG_Sessions::ticket_catalogue_for_event( $edit->id ) as $type ) {
                $ticket_rows[] = array( 'enabled' => true, 'code' => (string) $type->code, 'label' => (string) $type->label, 'price' => (string) $type->price, 'units' => (int) $type->capacity_units );
            }
        }
        if ( ! $session_rows ) { $session_rows[] = array( 'date' => '', 'time' => '', 'capacity' => '' ); }
        if ( ! $ticket_rows ) {
            $ticket_rows = array(
                array( 'enabled' => true,  'code' => 'COCUK',   'label' => 'Çocuk 3–12 Yaş',      'price' => '', 'units' => 1 ),
                array( 'enabled' => true,  'code' => 'YETISKIN','label' => 'Yetişkin 13 Yaş ve üzeri', 'price' => '', 'units' => 1 ),
            );
        }

        $this->event_import_panel();

        echo '<div class="mdg-event-layout"><div class="mdg-panel mdg-event-form-panel">';
        echo '<h2>' . ( $edit ? 'Etkinlik Taslağını Düzenle' : 'Yeni Etkinlik Taslağı' ) . '</h2>';
        echo '<p class="description">Bu ekran halka açık profesyonel etkinlik sayfası ve satış motorunun ana veri kaynağıdır: içerik, görseller, salon snapshot bilgileri, seanslar, ortak kapasite ve bilet fiyatları burada tanımlanır.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-mdg-event-form>';
        echo '<input type="hidden" name="action" value="mdg_save_event_draft"><input type="hidden" name="event_id" value="' . esc_attr( $edit->id ?? 0 ) . '">';
        wp_nonce_field( 'mdg_save_event_draft', 'mdg_nonce' );

        echo '<div class="mdg-section"><h3>1. Etkinlik ve Salon</h3>';
        echo '<label>Etkinlik adı</label><input type="text" name="title" data-mdg-event-title required value="' . esc_attr( $edit->title ?? '' ) . '" placeholder="Örn: Madagaskar Sirki – Ankara">';
        echo '<div class="mdg-grid-3"><div><label>İl</label><select name="province_code" data-mdg-event-province required><option value="">Seçiniz</option>';
        foreach ( $provinces as $code => $name ) { echo '<option value="' . esc_attr( $code ) . '" ' . selected( $edit->province_code ?? '', $code, false ) . '>' . esc_html( $name ) . '</option>'; }
        echo '</select></div><div><label>İlçe</label><select name="district" data-mdg-event-district data-selected="' . esc_attr( $edit->district ?? '' ) . '" required><option value="">Önce il seçiniz</option></select></div><div><label>Salon</label><select name="venue_id" data-mdg-event-venue data-selected="' . esc_attr( $edit->venue_id ?? 0 ) . '" required><option value="">Önce ilçe seçiniz</option></select></div></div>';
        echo '<div class="mdg-venue-snapshot-preview" data-mdg-venue-snapshot><strong>Salon seçildiğinde:</strong> adres, varsayılan kapasite, süre ve Maps bilgisi burada görünecek. Taslak kaydedildiğinde bu bilgiler etkinliğe snapshot olarak yazılır.</div>';
        echo '</div>';

        echo '<div class="mdg-section"><h3>2. Görseller ve Video</h3>';
        echo '<div class="mdg-media-grid"><div><label>Kapak / Hero görseli</label><input type="hidden" name="hero_attachment_id" value="' . esc_attr( $hero_id ) . '" data-mdg-hero-id><div class="mdg-media-preview mdg-hero-preview" data-mdg-hero-preview>';
        if ( $hero_url ) echo '<img src="' . esc_url( $hero_url ) . '" alt="Etkinlik kapak görseli">'; else echo '<span>Henüz kapak görseli seçilmedi.</span>';
        echo '</div><div class="mdg-media-actions"><button type="button" class="button button-secondary" data-mdg-choose-hero>Kapak Görseli Seç</button><button type="button" class="button" data-mdg-clear-hero>Temizle</button></div><p class="mdg-field-note">Öneri: yatay, güçlü bir sahne/afiş görseli. Web tarafında responsive boyutlar üretilecek.</p></div>';

        echo '<div><label>Gösteri galerisi</label><input type="hidden" name="gallery_attachment_ids" value="' . esc_attr( implode( ',', $gallery_ids ) ) . '" data-mdg-gallery-ids><div class="mdg-gallery-preview" data-mdg-gallery-preview>';
        foreach ( $gallery_ids as $gid ) { $u = wp_get_attachment_image_url( $gid, 'thumbnail' ); if ( $u ) echo '<img src="' . esc_url( $u ) . '" data-id="' . esc_attr( $gid ) . '" alt="Galeri görseli">'; }
        echo '</div><div class="mdg-media-actions"><button type="button" class="button button-secondary" data-mdg-choose-gallery>Galeri Görselleri Seç</button><button type="button" class="button" data-mdg-clear-gallery>Galeriyi Temizle</button></div><p class="mdg-field-note">Birden fazla sahne fotoğrafı seçilebilir. Aynı pencerede çoklu seçim yapabilir veya seçiciyi tekrar açıp yeni görseller ekleyebilirsiniz. En fazla 30 görsel kaydedilir. <strong data-mdg-gallery-count>' . esc_html( count( $gallery_ids ) ) . ' görsel seçili.</strong></p></div></div>';
        echo '<label>Tanıtım videosu bağlantısı <span class="description">(opsiyonel)</span></label><input type="url" name="video_url" value="' . esc_attr( $edit->video_url ?? '' ) . '" placeholder="https://www.youtube.com/... veya https://vimeo.com/...">';
        echo '</div>';

        echo '<div class="mdg-section"><h3>3. Gösteri Tanıtımı</h3>';
        echo '<label>Kısa tanıtım</label><textarea name="short_description" rows="3" maxlength="600" placeholder="Etkinlik kartı ve ilk ekran için kısa, güçlü tanıtım...">' . esc_textarea( $edit->short_description ?? '' ) . '</textarea><p class="mdg-field-note">İlk ekranda ve sosyal paylaşım/özet alanlarında kullanılacak. 600 karakteri geçmesin.</p>';
        echo '<label>Gösteri hakkında</label>';
        wp_editor( $edit->long_description ?? '', 'mdg_long_description', array( 'textarea_name' => 'long_description', 'media_buttons' => false, 'teeny' => false, 'textarea_rows' => 12 ) );
        echo '</div>';

        echo '<div class="mdg-section"><h3>4. Ziyaretçi Bilgileri</h3><div class="mdg-grid-3"><div><label>Yaş bilgisi</label><input type="text" name="age_info" value="' . esc_attr( $edit->age_info ?? '' ) . '" placeholder="Örn: 3 yaş ve üzeri bilete tabidir"></div><div><label>Gösteri süresi (dk)</label><input type="number" min="15" max="360" name="show_duration" data-mdg-show-duration value="' . esc_attr( $edit->show_duration ?? '' ) . '"></div><div><label>Kapı açılışı (dk önce)</label><input type="number" min="0" max="180" name="doors_open_before" value="' . esc_attr( isset( $edit->doors_open_before ) && null !== $edit->doors_open_before ? $edit->doors_open_before : 30 ) . '"></div></div>';
        $seat = $edit->seating_type ?? 'free';
        echo '<label>Oturma düzeni</label><select name="seating_type"><option value="free" ' . selected( $seat, 'free', false ) . '>Serbest oturma</option><option value="numbered" ' . selected( $seat, 'numbered', false ) . '>Numaralı koltuk</option><option value="mixed" ' . selected( $seat, 'mixed', false ) . '>Karma</option></select>';
        echo '<label>Etkinlik kuralları / önemli bilgiler</label><textarea name="rules" rows="8" placeholder="Her kuralı yeni satıra yazabilirsiniz...">' . esc_textarea( $edit->rules ?? '' ) . '</textarea>';
        echo '<label>Organizatör</label><input type="text" name="organizer_name" value="' . esc_attr( $edit->organizer_name ?? $organizer_default ) . '">';
        echo '</div>';

        echo '<div class="mdg-section"><h3>5. Tarih, Seanslar ve Ortak Kapasite</h3>';
        echo '<p class="mdg-field-note mdg-note-strong">Her seansın tek ortak kapasitesi vardır. Çocuk ve yetişkin biletleri birer kişilik kapasite tüketir.</p>';
        echo '<div class="mdg-session-list" data-mdg-session-list>';
        foreach ( $session_rows as $i => $sr ) {
            echo '<div class="mdg-session-row" data-mdg-session-row><div><label>Tarih</label><input type="date" name="session_date[]" value="' . esc_attr( $sr['date'] ) . '" required></div><div><label>Seans saati</label><input type="time" name="session_time[]" value="' . esc_attr( $sr['time'] ) . '" required></div><div><label>Ortak kapasite</label><input type="number" min="1" max="100000" name="session_capacity[]" data-mdg-session-capacity value="' . esc_attr( $sr['capacity'] ) . '" placeholder="Salon kapasitesi" required></div><div class="mdg-row-actions"><button type="button" class="button" data-mdg-remove-session>Seansı Sil</button></div></div>';
        }
        echo '</div><button type="button" class="button button-secondary" data-mdg-add-session>+ Yeni Seans Ekle</button>';
        echo '<p class="mdg-field-note">Örnek: 26.09.2026 için 12:00, 14:00 ve 16:00 seansları üç ayrı satır olarak girilir. Her satırın kapasitesi bağımsızdır.</p>';
        echo '</div>';

        echo '<div class="mdg-section"><h3>6. Bilet Türleri ve Fiyatlar</h3>';
        echo '<p class="mdg-field-note mdg-note-strong">Aktif bilet türleri bütün seanslara uygulanır. “Kapasite tüketimi”, bir adet satışın seans kapasitesinden kaç kişi düşeceğini belirtir.</p>';
        echo '<div class="mdg-ticket-list" data-mdg-ticket-list>';
        foreach ( $ticket_rows as $i => $tr ) {
            echo '<div class="mdg-ticket-row" data-mdg-ticket-row>';
            echo '<div class="mdg-ticket-enabled"><label>Aktif</label><input type="checkbox" name="ticket_enabled[' . esc_attr( $i ) . ']" value="1" ' . checked( ! empty( $tr['enabled'] ), true, false ) . '></div>';
            echo '<input type="hidden" name="ticket_code[' . esc_attr( $i ) . ']" value="' . esc_attr( $tr['code'] ) . '" data-mdg-ticket-code>';
            echo '<div><label>Bilet türü</label><input type="text" name="ticket_label[' . esc_attr( $i ) . ']" value="' . esc_attr( $tr['label'] ) . '" placeholder="Örn: Çocuk 3–12 Yaş"></div>';
            echo '<div><label>Fiyat (TL)</label><input type="number" min="0" max="1000000" step="0.01" name="ticket_price[' . esc_attr( $i ) . ']" value="' . esc_attr( $tr['price'] ) . '" placeholder="0,00"></div>';
            echo '<div><label>Kapasite tüketimi</label><input type="number" min="1" max="50" name="ticket_units[' . esc_attr( $i ) . ']" value="' . esc_attr( $tr['units'] ) . '"></div>';
            echo '<div class="mdg-row-actions"><button type="button" class="button" data-mdg-remove-ticket>Bilet Türünü Sil</button></div>';
            echo '</div>';
        }
        echo '</div><button type="button" class="button button-secondary" data-mdg-add-ticket>+ Bilet Türü Ekle</button>';
        echo '<div class="notice notice-info inline mdg-capacity-example"><p><strong>Aile paketi:</strong> Etkinlik oluşturulduktan sonra Madagaskar → Etkinlikler ekranından ayrıca açılır ve fiyatlandırılır. Excel şablonunda ve yeni etkinlik formunda hazır aile paketi satırı bulunmaz.</p></div>';
        echo '</div>';

        echo '<div class="mdg-section"><h3>7. Sık Sorulan Sorular</h3>';
        echo '<p class="mdg-field-note mdg-note-strong">SSS alanları müşterinin etkinlik sayfasında açılır-kapanır biçimde gösterilir. Excel’den gelen SSS kayıtlarını burada görebilir ve düzenleyebilirsiniz.</p>';
        echo '<div class="mdg-faq-admin-list" data-mdg-faq-list>';
        foreach ( $faq_rows as $faq ) {
            echo '<div class="mdg-faq-admin-row" data-mdg-faq-row><div><label>Soru</label><input type="text" name="faq_question[]" value="' . esc_attr( $faq['question'] ?? '' ) . '" placeholder="Örn: Oturma düzeni nasıl?"></div><div><label>Cevap</label><textarea name="faq_answer[]" rows="3" placeholder="Kısa ve net cevap...">' . esc_textarea( $faq['answer'] ?? '' ) . '</textarea></div><button type="button" class="button" data-mdg-remove-faq>SSS’yi Sil</button></div>';
        }
        echo '</div><button type="button" class="button button-secondary" data-mdg-add-faq>+ SSS Ekle</button>';
        echo '</div>';

        echo '<div class="mdg-section"><h3>8. SEO ve Paylaşım</h3>';
        echo '<label>SEO başlığı <span class="description">(opsiyonel)</span></label><input type="text" name="seo_title" maxlength="190" value="' . esc_attr( $edit->seo_title ?? '' ) . '" placeholder="Boş bırakılırsa etkinlik adından otomatik üretilecek">';
        echo '<label>SEO açıklaması <span class="description">(opsiyonel)</span></label><textarea name="seo_description" rows="3" maxlength="320" placeholder="Google ve paylaşım için kısa açıklama...">' . esc_textarea( $edit->seo_description ?? '' ) . '</textarea>';
        echo '<p class="mdg-field-note">V2.5 önizlemesinde Event Schema ve Open Graph hazırlanır; taslak sayfa noindex olarak yalnızca yöneticiye açılır. Canlı halka açık URL satış entegrasyonu aşamasında etkinleştirilecek.</p>';
        echo '</div>';

        echo '<div class="mdg-save-bar"><div><strong>Önce Madagaskar TASLAĞI kaydedilir.</strong><br><span>Ardından V2.9 Üretim Planı WooCommerce/Tickera/Designer/SKU planını gösterir; plan hazırsa V2.9.1 güvenli TASLAK üretim düğmesi açılır.</span></div><div class="mdg-save-actions">';
        if ( $edit ) { $preview_url = MDG_Public_Event::preview_url( $edit ); echo '<a class="button button-secondary" target="_blank" rel="noopener" href="' . esc_url( $preview_url ) . '">Müşteri Sayfasını Önizle ↗</a>'; }
        submit_button( $edit ? 'Taslağı Güncelle' : 'Etkinlik Taslağını Kaydet', 'primary', 'submit', false );
        echo '</div></div></form></div>';

        if ( $edit && class_exists( 'MDG_New_Event_Production_Plan' ) ) { MDG_New_Event_Production_Plan::render_for_event( (int) $edit->id ); }

        echo '<div class="mdg-panel mdg-drafts-panel"><h2>Etkinlik Taslakları</h2>';
        $drafts = MDG_Events::drafts();
        if ( ! $drafts ) { echo '<p>Henüz etkinlik taslağı yok.</p>'; }
        else {
            echo '<table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Salon</th><th>Seans</th><th>Görseller</th><th>Durum</th><th></th></tr></thead><tbody>';
            foreach ( $drafts as $d ) {
                $edit_url = add_query_arg( array( 'page' => 'mdg-publish', 'edit' => $d->id ), admin_url( 'admin.php' ) );
                $gcount = count( MDG_Events::gallery_ids( $d ) );
                $scount = count( MDG_Sessions::by_event( $d->id ) );
                $copy_url = wp_nonce_url( add_query_arg( array( 'action'=>'mdg_duplicate_event_draft', 'event_id'=>$d->id ), admin_url( 'admin-post.php' ) ), 'mdg_duplicate_event_draft_' . $d->id );
                $preview_url = MDG_Public_Event::preview_url( $d );
                $plan_url = add_query_arg( array( 'page'=>'mdg-publish', 'edit'=>$d->id, 'mdg_production_plan'=>1 ), admin_url( 'admin.php' ) ) . '#mdg-production-plan';
                echo '<tr><td><strong>' . esc_html( $d->title ) . '</strong><br><small>' . esc_html( $d->province_name . ' / ' . $d->district ) . '</small></td><td>' . esc_html( $d->venue_name ) . '</td><td>' . esc_html( $scount ) . '</td><td>' . ( $d->hero_attachment_id ? 'Kapak ✓' : 'Kapak yok' ) . '<br><small>Galeri: ' . esc_html( $gcount ) . '</small></td><td>' . esc_html( MDG_Status::label( $d->status ) ) . '</td><td><a href="' . esc_url( $edit_url ) . '">Düzenle</a> · <a href="' . esc_url( $plan_url ) . '"><strong>Üretim Planı</strong></a> · <a target="_blank" rel="noopener" href="' . esc_url( $preview_url ) . '">Önizle</a> · <a href="' . esc_url( $copy_url ) . '">Kopyala</a></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<div class="notice notice-info inline mdg-draft-note"><p><strong>Güvenli geliştirme kuralı:</strong> Bu taslaklar mevcut canlı WooCommerce/Tickera etkinliklerine bağlanmaz. Seans ve fiyat verileri yalnızca Madagaskar V2 tablolarında tutulur; çalışan satış akışını değiştirmez.</p></div>';
        echo '</div></div>';
        $this->footer();
    }

    private function event_import_panel() {
        $preview = MDG_Event_Importer::get_preview();
        echo '<div class="mdg-panel mdg-import-panel mdg-event-import-panel"><h2>Excel’den Etkinlik Taslağı İçe Aktar</h2>';
        echo '<p>Etkinlik, seans, bilet türü, görsel ve SSS verilerini tek Excel dosyasından hazırlayın. Sistem önce <strong>önizleme</strong> yapar; siz onaylamadan Madagaskar tablolarına kayıt yazmaz.</p>';
        echo '<p><a class="button" href="' . esc_url( 'https://madagaskarsirki.com/wp-content/uploads/2026/09/Madagaskar_Etkinlik_Sablonu.xlsx' ) . '">Excel Şablonunu İndir</a> <span class="description">Çocuk ve yetişkin biletleri için Ankara örneği içerir. Aile paketi ayrıca Etkinlikler ekranından yönetilir.</span></p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="mdg-import-form">';
        echo '<input type="hidden" name="action" value="mdg_preview_event_import">';
        wp_nonce_field( 'mdg_preview_event_import', 'mdg_event_import_nonce' );
        echo '<input type="file" name="event_file" accept=".xlsx" required> ';
        submit_button( 'Excel’i Önizle', 'secondary', 'submit', false );
        echo '<p class="description">Aynı <code>event_code</code> tekrar yüklenirse yalnızca mevcut TASLAK güncellenir. Satışa açılmış etkinlik Excel ile ezilmez.</p></form>';

        if ( $preview ) {
            $summary = $preview['summary'] ?? array();
            echo '<div class="mdg-import-preview"><h3>İçe Aktarım Önizlemesi</h3>';
            if ( ! empty( $preview['file_name'] ) ) echo '<p><strong>Dosya:</strong> ' . esc_html( $preview['file_name'] ) . '</p>';
            echo '<div class="mdg-cards mdg-import-cards">';
            foreach ( array( 'Etkinlik'=>absint($summary['event_count']??0), 'Seans'=>absint($summary['session_count']??0), 'Aktif Bilet Türü'=>absint($summary['ticket_type_count']??0), 'Bulunan Görsel'=>absint($summary['image_count']??0) ) as $label=>$value ) {
                echo '<div class="mdg-card"><div class="mdg-card-value">' . esc_html( $value ) . '</div><div>' . esc_html( $label ) . '</div></div>';
            }
            echo '</div>';

            if ( ! empty( $preview['events'] ) ) {
                echo '<table class="widefat striped"><thead><tr><th>Kod</th><th>Etkinlik</th><th>Salon</th><th>Seans</th><th>Bilet Türü</th><th>İşlem</th></tr></thead><tbody>';
                foreach ( $preview['events'] as $ev ) {
                    echo '<tr><td><code>' . esc_html( $ev['event_code'] ) . '</code></td><td><strong>' . esc_html( $ev['title'] ) . '</strong></td><td>' . esc_html( $ev['province_name'] . ' / ' . $ev['district'] . ' – ' . $ev['venue_name'] ) . '</td><td>' . esc_html( count( $ev['sessions'] ) ) . '</td><td>' . esc_html( count( $ev['ticket_types'] ) ) . '</td><td>' . ( 'update' === $ev['mode'] ? 'Taslak güncellenecek' : 'Yeni taslak' ) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
            if ( ! empty( $preview['warnings'] ) ) {
                echo '<div class="notice notice-warning inline"><p><strong>Uyarılar:</strong></p><ul>';
                foreach ( array_slice( $preview['warnings'], 0, 30 ) as $warning ) echo '<li>' . esc_html( $warning ) . '</li>';
                echo '</ul></div>';
            }
            if ( ! empty( $preview['fatal_errors'] ) ) {
                echo '<div class="notice notice-error inline"><p><strong>Kayıt yapılmadan önce düzeltilmesi gereken hatalar:</strong></p><ul>';
                foreach ( array_slice( $preview['fatal_errors'], 0, 50 ) as $error ) echo '<li>' . esc_html( $error ) . '</li>';
                echo '</ul><p>Hata varken onay butonu gösterilmez. Excel’i düzeltip yeniden önizleyin.</p></div>';
            } else {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mdg-import-commit">';
                echo '<input type="hidden" name="action" value="mdg_commit_event_import">';
                wp_nonce_field( 'mdg_commit_event_import', 'mdg_event_import_commit_nonce' );
                submit_button( 'Önizlemeyi Onayla ve Taslakları Oluştur', 'primary', 'submit', false );
                echo '</form>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    public function events_live() { $this->events_table( false ); }
    public function events_past() { $this->events_table( true ); }

    private function events_table( $past ) {
        global $wpdb;
        $this->header( $past ? 'Süresi Dolan Etkinlikler' : 'Yayındaki Etkinlikler' );
        if ( ! $past && isset( $_GET['mdg_published'] ) ) {
            $published = MDG_Events::get( absint( $_GET['event_id'] ?? 0 ) );
            $public_url = $published ? MDG_Public_Event::live_url( $published ) : '';
            echo '<div class="notice notice-success inline"><p><strong>Etkinlik canlı yayına alındı.</strong>' . ( $public_url ? ' <a href="' . esc_url( $public_url ) . '" target="_blank" rel="noopener">Halka açık etkinlik sayfasını aç ↗</a>' : '' ) . '</p></div>';
        }
        $events = MDG_DB::table( 'events' );
        $sessions = MDG_DB::table( 'sessions' );
        $op = $past ? '<' : '>=';
        $status_sql = $past ? "e.status <> 'draft'" : "e.status IN ('onsale','closed','soldout','postponed')";
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.*, MIN(s.start_at) first_start, MAX(s.end_at) last_end, COUNT(s.id) session_count,
                    SUM(s.capacity_total) cap, SUM(s.sold_units) sold
             FROM {$events} e LEFT JOIN {$sessions} s ON s.event_id=e.id
             WHERE {$status_sql}
             GROUP BY e.id
             HAVING last_end {$op} %s
             ORDER BY first_start " . ( $past ? 'DESC' : 'ASC' ) . ' LIMIT 250',
            MDG_DB::now()
        ) );
        if ( ! $rows ) { echo '<div class="mdg-panel"><p>Kayıt bulunmuyor.</p></div>'; $this->footer(); return; }
        echo '<div class="mdg-panel"><table class="widefat striped"><thead><tr><th>Etkinlik</th><th>Konum</th><th>İlk Seans</th><th>Seans</th><th>Satış</th><th>Durum</th><th>Sayfa</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            $public_url = ( ! $past && MDG_Status::ONSALE === (string) $r->status ) ? MDG_Public_Event::live_url( $r ) : '';

            // Sessions are stored canonically in UTC. Admin tables must always display
            // the WordPress site timezone (Türkiye on the production site) instead of
            // leaking the raw UTC MySQL value such as 09:00 for a 12:00 local session.
            $first_start_label = '—';
            if ( ! empty( $r->first_start ) ) {
                list( $local_date, $local_time ) = MDG_Sessions::local_parts( (string) $r->first_start );
                if ( $local_date && $local_time ) {
                    $ts = strtotime( $local_date . ' 12:00:00' );
                    $first_start_label = ( $ts ? wp_date( 'd.m.Y', $ts ) : $local_date ) . ' ' . $local_time;
                }
            }

            echo '<tr><td><strong>' . esc_html( $r->title ) . '</strong></td><td>' . esc_html( $r->province_name . ' / ' . $r->district . ' – ' . $r->venue_name ) . '</td><td>' . esc_html( $first_start_label ) . '</td><td>' . esc_html( $r->session_count ) . '</td><td>' . esc_html( (int) $r->sold . ' / ' . (int) $r->cap ) . '</td><td>' . esc_html( MDG_Status::label( $r->status ) ) . '</td><td>' . ( $public_url ? '<a class="button" href="' . esc_url( $public_url ) . '" target="_blank" rel="noopener">Etkinlik Sayfası ↗</a>' : '—' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        $this->footer();
    }

    public function cancel_refund() {
        if ( class_exists( 'MDG_Refund_Preview' ) ) { MDG_Refund_Preview::render(); return; }
        $this->placeholder( 'İptal / Erteleme', 'İade önizleme modülü yüklenemedi.' );
    }
    public function reports() {
        if ( class_exists( 'MDG_Sales_Reports' ) ) { MDG_Sales_Reports::render(); return; }
        $this->placeholder( 'Satış Raporları', 'Rapor modülü yüklenemedi.' );
    }
    public function customers() {
        if ( class_exists( 'MDG_Customer_Tickets' ) ) { MDG_Customer_Tickets::render(); return; }
        $this->placeholder( 'Müşteri / Bilet Listeleri', 'Müşteri/bilet modülü yüklenemedi.' );
    }

    private function placeholder( $title, $text ) {
        $this->header( $title );
        echo '<div class="mdg-panel"><p>' . esc_html( $text ) . '</p><p><strong>Bu ekran henüz mevcut canlı satış akışına bağlanmamıştır.</strong></p></div>';
        $this->footer();
    }

    public function settings() {
        $this->header( 'Ayarlar' );
        echo '<div class="mdg-panel"><h2>Sistem ilkeleri</h2><ul><li>Ödeme yoksa bilet yok.</li><li>Gerçek kapasite kaynağı seanstır.</li><li>Kommo/WhatsApp/e-posta arızası bilet satışını durdurmaz.</li><li>İptal edilen etkinlik silinmez; geçmiş kayıt korunur.</li><li>WooCommerce siparişlerine doğrudan SQL yazılmaz; HPOS uyumlu CRUD API kullanılır.</li></ul></div>';
        if ( class_exists( 'MDG_Production_Readiness' ) ) { MDG_Production_Readiness::render(); }
        if ( class_exists( 'MDG_Ticket_Venue_QR' ) ) { MDG_Ticket_Venue_QR::render_admin_panel(); }
        if ( class_exists( 'MDG_Ticket_Template_QR_Binder' ) ) { MDG_Ticket_Template_QR_Binder::render_admin_panel(); }
        echo '<details class="mdg-panel" style="margin-top:18px"><summary style="cursor:pointer;font-weight:700">Geçiş / Tanı Araçları</summary><div style="margin-top:16px">';
        if ( class_exists( 'MDG_Sales_Audit' ) ) { MDG_Sales_Audit::render(); }
        if ( class_exists( 'MDG_Sales_Dry_Run' ) ) { MDG_Sales_Dry_Run::render(); }
        if ( class_exists( 'MDG_Sales_Adopter' ) ) { MDG_Sales_Adopter::render(); }
        echo '</div></details>';
        $this->footer();
    }
}
