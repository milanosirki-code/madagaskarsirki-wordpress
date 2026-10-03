<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.9.0 — Yeni etkinlik üretim planı (salt okunur / dry-run).
 *
 * Bu sınıf hiçbir WooCommerce ürünü, varyasyon, Tickera etkinliği, Ticket Designer
 * şablonu veya MDG mapping alanı oluşturmaz/değiştirmez. Amaç, gerçek üretim
 * sürümünden önce tam ve deterministik üretim planını doğrulamaktır.
 */
final class MDG_New_Event_Production_Plan {

    public static function render_for_event( $event_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $event = MDG_Events::get( absint( $event_id ) );
        if ( ! $event || 'draft' !== (string) $event->status ) { return; }

        $plan = self::build( $event );
        $open = ! empty( $_GET['mdg_production_plan'] );

        echo '<details id="mdg-production-plan" class="mdg-panel mdg-production-plan"' . ( $open ? ' open' : '' ) . ' style="margin-top:18px">';
        echo '<summary style="cursor:pointer;font-size:18px;font-weight:700">V2.9 Yeni Etkinlik Üretim Planı — DRY-RUN</summary>';
        echo '<div style="margin-top:16px">';
        echo '<p><strong>Salt okunur güvenlik modu:</strong> Bu bölüm yalnızca oluşturulacak satış nesnelerini hesaplar. WooCommerce, Tickera, Ticket Designer, sipariş veya mevcut Ankara satış verilerine <strong>hiçbir yazma yapmaz</strong>.</p>';

        self::render_preflight( $plan );
        self::render_identity( $event, $plan );
        self::render_tickera( $event, $plan );
        self::render_products( $event, $plan );
        self::render_template( $plan );
        self::render_commit_contract( $plan );

        // V2.9.2: Dry-run planı ile gerçek taslak üretim kontrollerini aynı ekranda bağla.
        // V2.9.1'de producer sınıfı yüklenmiş ve hook'ları kayıtlıydı ancak render_controls()
        // hiç çağrılmadığı için "Taslak Satış Nesnelerini Oluştur" butonu görünmüyordu.
        if ( class_exists( 'MDG_New_Event_Draft_Producer' ) ) {
            MDG_New_Event_Draft_Producer::render_controls( $plan );
        }

        echo '</div></details>';
    }

    public static function build( $event ) {
        $sessions = MDG_Sessions::by_event( (int) $event->id );
        $city_code = self::city_code( (string) $event->province_name );
        $district_code = self::district_code( (string) $event->district );
        $production_key = 'MDG-E' . (int) $event->id . '-' . substr( str_replace( '-', '', (string) $event->public_uuid ), 0, 12 );
        $warnings = array();
        $errors = array();
        $recognized_existing = array();

        $deps = array(
            'woo' => class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ),
            'tickera' => post_type_exists( 'tc_events' ) && post_type_exists( 'tc_tickets_instances' ),
            'bridge' => self::plugin_active_contains( 'bridge-for-woocommerce/bridge-for-woocommerce.php' ),
        );
        if ( ! $deps['woo'] ) { $errors[] = 'WooCommerce aktif değil.'; }
        if ( ! $deps['tickera'] ) { $errors[] = 'Tickera etkinlik/bilet tipleri aktif değil.'; }
        if ( ! $deps['bridge'] ) { $errors[] = 'Tickera Bridge for WooCommerce aktif değil.'; }

        if ( 'draft' !== (string) $event->status ) { $errors[] = 'Etkinlik taslak durumunda değil.'; }
        if ( empty( $event->title ) ) { $errors[] = 'Etkinlik başlığı eksik.'; }
        if ( empty( $event->hero_attachment_id ) || ! wp_attachment_is_image( (int) $event->hero_attachment_id ) ) { $errors[] = 'Kapak/Hero görseli eksik.'; }
        if ( empty( trim( (string) $event->venue_address ) ) ) { $errors[] = 'Salon açık adresi eksik.'; }
        if ( empty( trim( (string) $event->venue_maps_url ) ) ) { $errors[] = 'Salon Google Maps bağlantısı eksik.'; }
        if ( empty( $event->venue_qr_attachment_id ) || ! wp_get_attachment_url( (int) $event->venue_qr_attachment_id ) ) { $errors[] = 'Salon otomatik QR görseli eksik.'; }
        if ( ! $sessions ) { $errors[] = 'Etkinlikte seans yok.'; }

        $future_sessions = 0;
        $products = array();
        $catalogue_signature = null;
        $all_variation_skus = array();
        $has_multi_unit_ticket = false;
        $session_ids = array();

        foreach ( (array) $sessions as $session ) {
            $session_ids[] = (int) $session->id;
            if ( strtotime( (string) $session->start_at . ' UTC' ) > time() ) { $future_sessions++; }
            if ( (int) $session->wc_product_id || (int) $session->tickera_event_id ) {
                $mapped_ids = array_filter( array( (int) $session->wc_product_id, (int) $session->tickera_event_id ) );
                $same_run = ! empty( $mapped_ids );
                foreach ( $mapped_ids as $mapped_id ) {
                    if ( ! self::is_same_managed_object( $mapped_id, $production_key, (int) $event->id ) ) { $same_run = false; break; }
                }
                if ( $same_run ) {
                    $recognized_existing[] = 'MDG seans #' . (int) $session->id . ' mevcut V2.9 taslak üretimine bağlı.';
                } else {
                    $errors[] = 'MDG seans #' . (int) $session->id . ' zaten başka/uyumsuz satış nesnesine bağlanmış.';
                }
            }

            $types = MDG_Sessions::ticket_types_by_session( (int) $session->id );
            if ( ! $types ) {
                $errors[] = 'MDG seans #' . (int) $session->id . ' için aktif bilet türü yok.';
                continue;
            }

            $sig = array();
            foreach ( $types as $type ) {
                $sig[] = implode( '|', array( (string) $type->code, (string) $type->label, number_format( (float) $type->price, 2, '.', '' ), (int) $type->capacity_units ) );
                if ( (int) $type->wc_variation_id ) {
                    if ( self::is_same_managed_object( (int) $type->wc_variation_id, $production_key, (int) $event->id ) ) {
                        $recognized_existing[] = 'Bilet türü #' . (int) $type->id . ' mevcut V2.9 varyasyonuna bağlı.';
                    } else {
                        $errors[] = 'Bilet türü #' . (int) $type->id . ' başka/uyumsuz WooCommerce varyasyonuna bağlı.';
                    }
                }
                if ( (int) $type->capacity_units > 1 ) { $has_multi_unit_ticket = true; }
            }
            $sig_text = implode( '||', $sig );
            if ( null === $catalogue_signature ) { $catalogue_signature = $sig_text; }
            elseif ( $catalogue_signature !== $sig_text ) { $errors[] = 'Seanslar arasında bilet kataloğu farklı. İlk üretim sürümü bütün seanslarda aynı bilet türü/fiyat yapısını gerektirir.'; }

            list( $date, $time ) = MDG_Sessions::local_parts( $session->start_at );
            $ymd = str_replace( '-', '', $date );
            $hhmm = str_replace( ':', '', $time );
            $human_date = self::human_date_tr( $date );
            $location_label = trim( (string) $event->province_name . ( ! empty( $event->district ) ? ' / ' . (string) $event->district : '' ) );
            $product_name = 'Madagaskar Sirki – ' . $location_label . ' – ' . $human_date . ' – ' . $time . ' Bileti';
            $product_slug = sanitize_title( 'madagaskar-sirki-' . (string) $event->province_name . '-' . (string) $event->district . '-' . $date . '-' . $hhmm . '-bileti' );

            $slug_collision = self::product_slug_collision( $product_slug );
            if ( $slug_collision ) {
                if ( self::is_same_managed_object( $slug_collision, $production_key, (int) $event->id ) ) {
                    $recognized_existing[] = 'Ürün URL mevcut V2.9 taslak ürünüyle eşleşiyor: #' . $slug_collision . '.';
                } else {
                    $errors[] = 'Ürün URL çakışması: ' . $product_slug . ' (mevcut ürün #' . $slug_collision . ').';
                }
            }

            $variations = array();
            foreach ( $types as $type ) {
                $suffix = self::ticket_suffix( (string) $type->code, (string) $type->label );
                $sku = $city_code . '-' . $district_code . '-' . $ymd . '-' . $hhmm . '-' . $suffix;
                $sku_collision = self::sku_collision( $sku );
                if ( $sku_collision ) {
                    if ( self::is_same_managed_object( $sku_collision, $production_key, (int) $event->id ) ) {
                        $recognized_existing[] = 'SKU mevcut V2.9 varyasyonuyla eşleşiyor: ' . $sku . ' → #' . $sku_collision . '.';
                    } else {
                        $errors[] = 'SKU çakışması: ' . $sku . ' (mevcut ürün/varyasyon #' . $sku_collision . ').';
                    }
                }
                if ( isset( $all_variation_skus[ $sku ] ) ) { $errors[] = 'Plan içinde tekrar eden SKU oluştu: ' . $sku . '.'; }
                $all_variation_skus[ $sku ] = true;

                $variations[] = array(
                    'ticket_type_id' => (int) $type->id,
                    'code' => (string) $type->code,
                    'label' => (string) $type->label,
                    'price' => number_format( (float) $type->price, 2, '.', '' ),
                    'capacity_units' => (int) $type->capacity_units,
                    'sku' => $sku,
                );
            }

            $products[] = array(
                'session_id' => (int) $session->id,
                'date' => $date,
                'time' => $time,
                'start_at' => (string) $session->start_at,
                'end_at' => (string) $session->end_at,
                'capacity_total' => (int) $session->capacity_total,
                'product_name' => $product_name,
                'product_slug' => $product_slug,
                'variations' => $variations,
            );
        }

        if ( ! $future_sessions ) { $errors[] = 'Gelecekte en az bir seans bulunmalıdır.'; }
        if ( $has_multi_unit_ticket ) {
            $errors[] = 'Kapasite tüketimi 1’den büyük aktif bilet türü var. Aile Paketi gibi çoklu giriş hakları için Tickera’da bir satıştan birden fazla gerçek giriş hakkı üretme modülü tamamlanmadan gerçek üretim açılmayacak.';
        }

        $category = self::category_plan( (string) $event->province_name );
        $template = self::designer_plan();
        if ( ! $template['ready'] ) { $errors[] = $template['error']; }

        $first = $products ? $products[0] : array();
        $last = $products ? $products[ count( $products ) - 1 ] : array();
        $tickera_slug = sanitize_title( 'madagaskar-sirki-' . (string) $event->province_name . '-' . (string) $event->district . '-' . ( $first['date'] ?? '' ) );
        $tickera_slug_collision = self::tickera_slug_collision( $tickera_slug );
        if ( $tickera_slug_collision ) {
            if ( self::is_same_managed_object( $tickera_slug_collision, $production_key, (int) $event->id ) ) {
                $recognized_existing[] = 'Tickera URL mevcut V2.9 taslak etkinliğiyle eşleşiyor: #' . $tickera_slug_collision . '.';
            } else {
                $errors[] = 'Tickera etkinlik URL çakışması: ' . $tickera_slug . ' (mevcut etkinlik #' . $tickera_slug_collision . ').';
            }
        }

        $tickera = array(
            'title' => (string) $event->title,
            'slug' => $tickera_slug,
            'first_start_local' => ! empty( $first['date'] ) ? $first['date'] . ' ' . $first['time'] : '',
            'last_end_local' => ! empty( $last['end_at'] ) ? self::utc_to_local_mysql( $last['end_at'] ) : '',
            'location' => (string) $event->venue_name,
            'address' => (string) $event->venue_address,
            'event_terms' => self::planned_terms( $event ),
            'hero_attachment_id' => (int) $event->hero_attachment_id,
            'strategy' => '1 Tickera etkinliği + seans başına 1 WooCommerce variable product',
        );

        $warnings[] = 'İlk gerçek üretim sürümünde WooCommerce ürünleri ve Tickera etkinliği taslak/private oluşturulacak; otomatik halka açılmayacak.';
        $warnings[] = 'WooCommerce varyasyon stoğu kapasitenin gerçek kaynağı olmayacak. Tek gerçek kaynak MDG seans ortak kapasitesidir.';
        $warnings[] = 'Kommo, e-posta ve WhatsApp üretim işlemini bloklamayacak; entegrasyonlar ödeme/sipariş sonrası bağımsız kalacak.';
        if ( $recognized_existing ) {
            $warnings[] = 'V2.9 tekrar-çalıştırma koruması: Bu production key ile daha önce oluşturulmuş MDG taslak nesneleri bulundu ve çakışma olarak sayılmadı.';
        }

        return array(
            'ready' => empty( $errors ),
            'errors' => array_values( array_unique( $errors ) ),
            'warnings' => array_values( array_unique( $warnings ) ),
            'dependencies' => $deps,
            'event_id' => (int) $event->id,
            'production_key' => $production_key,
            'city_code' => $city_code,
            'district_code' => $district_code,
            'session_count' => count( $products ),
            'future_session_count' => $future_sessions,
            'session_ids' => $session_ids,
            'products' => $products,
            'category' => $category,
            'tickera' => $tickera,
            'template' => $template,
        );
    }

    private static function render_preflight( array $plan ) {
        echo '<h3>1. Üretim öncesi güvenlik kontrolü</h3>';
        echo '<div class="mdg-cards">';
        $cards = array(
            array( 'WooCommerce', ! empty( $plan['dependencies']['woo'] ), 'CRUD API' ),
            array( 'Tickera', ! empty( $plan['dependencies']['tickera'] ), 'Etkinlik + bilet' ),
            array( 'Bridge', ! empty( $plan['dependencies']['bridge'] ), 'WooCommerce köprüsü' ),
            array( 'Seans', $plan['session_count'] > 0, $plan['session_count'] . ' adet' ),
            array( 'Designer', ! empty( $plan['template']['ready'] ), ! empty( $plan['template']['base_template_id'] ) ? '#' . $plan['template']['base_template_id'] : 'Bulunamadı' ),
        );
        foreach ( $cards as $c ) {
            echo '<div class="mdg-card"><div class="mdg-card-value" style="font-size:18px;color:' . esc_attr( $c[1] ? '#087c2f' : '#b32d2e' ) . '">' . esc_html( $c[1] ? 'Hazır' : 'Eksik' ) . '</div><div><strong>' . esc_html( $c[0] ) . '</strong><br><small>' . esc_html( $c[2] ) . '</small></div></div>';
        }
        echo '</div>';

        if ( $plan['errors'] ) {
            echo '<div class="notice notice-error inline"><p><strong>Gerçek üretimi BLOKLAYAN noktalar:</strong></p><ul style="list-style:disc;padding-left:22px">';
            foreach ( $plan['errors'] as $error ) { echo '<li>' . esc_html( $error ) . '</li>'; }
            echo '</ul></div>';
        } else {
            echo '<div class="notice notice-success inline"><p><strong>DRY-RUN HAZIR:</strong> Plan çakışmasız üretilebilir görünüyor. Bu sürümde yine de oluşturma butonu yoktur.</p></div>';
        }
        if ( $plan['warnings'] ) {
            echo '<div class="notice notice-info inline"><ul style="list-style:disc;padding-left:22px">';
            foreach ( $plan['warnings'] as $warning ) { echo '<li>' . esc_html( $warning ) . '</li>'; }
            echo '</ul></div>';
        }
    }

    private static function render_identity( $event, array $plan ) {
        echo '<h3>2. Kimlik ve tekrar-çalıştırma koruması</h3>';
        echo '<table class="widefat striped"><tbody>';
        self::row( 'MDG etkinliği', '#' . (int) $event->id . ' ' . (string) $event->title );
        self::row( 'Üretim anahtarı', $plan['production_key'], true );
        self::row( 'Şehir / İlçe / SKU kodu', (string) $event->province_name . ' / ' . (string) $event->district . ' → ' . $plan['city_code'] . '-' . $plan['district_code'], true );
        self::row( 'WooCommerce kategori', $plan['category']['name'] . ( $plan['category']['exists'] ? ' (mevcut #' . $plan['category']['term_id'] . ')' : ' (gerçek üretimde oluşturulacak)' ) );
        echo '</tbody></table>';
        echo '<p class="description">Gerçek üretim sürümü her oluşturduğu nesneye MDG etkinlik/seans/bilet türü kimliklerini ve üretim anahtarını meta olarak yazacak. Aynı işlem tekrar çalıştırıldığında yeni kopya üretmek yerine kendi nesnelerini bulacak.</p>';
    }

    private static function render_tickera( $event, array $plan ) {
        $t = $plan['tickera'];
        echo '<h3>3. Planlanan Tickera etkinliği</h3>';
        echo '<table class="widefat striped"><tbody>';
        self::row( 'Strateji', $t['strategy'] );
        self::row( 'Etkinlik adı', $t['title'] );
        self::row( 'Temiz slug', $t['slug'], true );
        self::row( 'Başlangıç', $t['first_start_local'] );
        self::row( 'Bitiş', $t['last_end_local'] );
        self::row( 'Salon', $t['location'] );
        self::row( 'Adres', $t['address'] );
        self::row( 'Kapasite kaynağı', 'Tickera toplam limiti değil; MDG seans ortak kapasitesi' );
        echo '</tbody></table>';
        echo '<details style="margin-top:10px"><summary><strong>Planlanan etkinlik şartları / bilet metni</strong></summary><pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px">' . esc_html( $t['event_terms'] ) . '</pre></details>';
    }

    private static function render_products( $event, array $plan ) {
        echo '<h3>4. Seans bazlı WooCommerce ürün planı</h3>';
        if ( ! $plan['products'] ) { echo '<p>Planlanacak seans ürünü yok.</p>'; return; }
        echo '<div class="mdg-table-scroll"><table class="widefat striped"><thead><tr><th>Seans</th><th>Variable product</th><th>Ortak kapasite</th><th>Varyasyon / fiyat / SKU</th></tr></thead><tbody>';
        foreach ( $plan['products'] as $p ) {
            $vars = array();
            foreach ( $p['variations'] as $v ) {
                $vars[] = '<strong>' . esc_html( $v['label'] ) . '</strong> · ' . ( function_exists( 'wc_price' ) ? wp_kses_post( wc_price( (float) $v['price'] ) ) : esc_html( $v['price'] . ' TL' ) ) . ' · <code>' . esc_html( $v['sku'] ) . '</code> · kapasite tüketimi ' . (int) $v['capacity_units'];
            }
            echo '<tr>';
            echo '<td><strong>' . esc_html( $p['date'] . ' ' . $p['time'] ) . '</strong><br><small>MDG session #' . (int) $p['session_id'] . '</small></td>';
            echo '<td><strong>' . esc_html( $p['product_name'] ) . '</strong><br><code>' . esc_html( $p['product_slug'] ) . '</code><br><small>Durum: private/draft · Görsel: Hero #' . (int) $event->hero_attachment_id . '</small></td>';
            echo '<td><strong>' . (int) $p['capacity_total'] . ' kişi</strong><br><small>Varyasyon stokları ortak kapasite değildir.</small></td>';
            echo '<td>' . implode( '<br>', $vars ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_template( array $plan ) {
        echo '<h3>5. Bilet şablonu ve salon QR planı</h3>';
        if ( ! $plan['template']['ready'] ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( $plan['template']['error'] ) . '</p></div>';
            return;
        }
        echo '<p>Temel Ticket Designer: <strong>#' . (int) $plan['template']['base_template_id'] . ' ' . esc_html( $plan['template']['base_template_name'] ) . '</strong>.</p>';
        echo '<p>Gerçek üretimde temel şablon etkinlik için <strong>bir kez klonlanacak</strong>; yalnızca sağ-alt <em>Salon Konumu</em> QR görseli bu etkinliğin salon QR PNG’si ile değiştirilecek. <strong>GİRİŞ QR değiştirilmeyecek.</strong></p>';
        if ( ! empty( $plan['template']['candidate_id'] ) ) { echo '<p class="description">Tespit edilen statik salon QR element kimliği: <code>' . esc_html( $plan['template']['candidate_id'] ) . '</code></p>'; }
    }

    private static function render_commit_contract( array $plan ) {
        echo '<h3>6. Bir sonraki gerçek üretim sürümünün sözleşmesi</h3>';
        echo '<ol style="padding-left:22px">';
        echo '<li>Nonce + <code>manage_woocommerce</code> yetkisi + etkinlik durum kontrolü.</li>';
        echo '<li>Önce tek Tickera etkinliği oluştur; ID’yi MDG etkinliğine/seanslara güvenli biçimde bağla.</li>';
        echo '<li>Her seans için bir WooCommerce variable product ve aktif bilet türleri için variation oluştur.</li>';
        echo '<li>Her nesneye <code>_mdg_managed=1</code>, MDG kimlikleri ve <code>_mdg_production_key</code> yaz.</li>';
        echo '<li>Ürünleri ilk aşamada private/draft tut; otomatik halka açma yok.</li>';
        echo '<li>Etkinlik bazlı Ticket Designer klonu üret ve salon QR’ını yerleştir.</li>';
        echo '<li>Tüm adımlar başarılıysa mapping alanlarını yaz; ara adım hata verirse yalnızca bu üretim anahtarıyla oluşturulan yeni nesneleri rollback/temizleme listesine al.</li>';
        echo '<li>Sonrasında ayrı önizleme/test → kontrollü canlı yayın aşaması.</li>';
        echo '</ol>';
        echo '<div class="notice ' . ( $plan['ready'] ? 'notice-success' : 'notice-warning' ) . ' inline"><p><strong>V2.9 sonucu:</strong> ' . esc_html( $plan['ready'] ? 'Gerçek üretim kodunu yazmaya geçmek için dry-run planı teknik olarak hazır.' : 'Önce yukarıdaki bloklayıcı maddeler çözülmeli; gerçek üretim butonu açılmayacak.' ) . '</p></div>';
        
    }

    private static function row( $label, $value, $code = false ) {
        echo '<tr><th style="width:240px">' . esc_html( $label ) . '</th><td>' . ( $code ? '<code>' . esc_html( $value ) . '</code>' : esc_html( $value ) ) . '</td></tr>';
    }

    private static function category_plan( $province_name ) {
        $term = term_exists( $province_name, 'product_cat' );
        $id = is_array( $term ) ? absint( $term['term_id'] ?? 0 ) : absint( $term );
        return array( 'name'=>(string)$province_name, 'exists'=>(bool)$id, 'term_id'=>$id );
    }

    private static function designer_plan() {
        global $wpdb;
        $table = $wpdb->prefix . 'tickera_ticket_templates';
        if ( ! self::table_exists( $table ) ) {
            return array( 'ready'=>false, 'base_template_id'=>0, 'base_template_name'=>'', 'candidate_id'=>'', 'error'=>'Tickera Ticket Designer tablosu bulunamadı.' );
        }

        $base_id = 0;
        $bindings = get_option( 'mdg_ticket_template_qr_bindings_v1', array() );
        if ( is_array( $bindings ) ) {
            foreach ( array_reverse( $bindings, true ) as $binding ) {
                if ( ! empty( $binding['base_template_id'] ) ) { $base_id = absint( $binding['base_template_id'] ); break; }
            }
        }
        if ( ! $base_id ) {
            $base_id = absint( $wpdb->get_var( "SELECT id FROM {$table} WHERE status='active' AND name NOT LIKE 'MDG AutoQR |%' AND name LIKE '%Madagaskar%' ORDER BY id ASC LIMIT 1" ) );
        }
        if ( ! $base_id ) {
            return array( 'ready'=>false, 'base_template_id'=>0, 'base_template_name'=>'', 'candidate_id'=>'', 'error'=>'Temel Madagaskar Ticket Designer şablonu bulunamadı.' );
        }

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id,name,template_data,status FROM {$table} WHERE id=%d LIMIT 1", $base_id ) );
        if ( ! $row || 'active' !== (string) $row->status ) {
            return array( 'ready'=>false, 'base_template_id'=>$base_id, 'base_template_name'=>'', 'candidate_id'=>'', 'error'=>'Temel Ticket Designer şablonu aktif değil.' );
        }
        $candidate = self::detect_static_qr_candidate( (string) $row->template_data );
        if ( ! $candidate ) {
            return array( 'ready'=>false, 'base_template_id'=>$base_id, 'base_template_name'=>(string)$row->name, 'candidate_id'=>'', 'error'=>'Temel şablonda güvenli sağ-alt Salon Konumu QR görsel adayı bulunamadı.' );
        }
        return array(
            'ready'=>true,
            'base_template_id'=>$base_id,
            'base_template_name'=>(string)$row->name,
            'candidate_id'=>(string)($candidate['id'] ?? ''),
            'error'=>'',
        );
    }

    private static function detect_static_qr_candidate( $json ) {
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) { return array(); }
        $canvas_w = max( 1, (float) ( $data['width'] ?? 595 ) );
        $canvas_h = max( 1, (float) ( $data['height'] ?? 420 ) );
        $candidates = array();
        foreach ( $data['elements'] as $el ) {
            $type = strtolower( (string) ( $el['type'] ?? '' ) );
            $base = strtolower( (string) ( $el['baseType'] ?? '' ) );
            if ( 'google_map' === $type || 'qrcode' === $base || 'qr_code' === $type ) { continue; }
            if ( ! in_array( $type, array( 'image','logo','event_image','event_logo','sponsor_logo' ), true ) && 'image' !== $base ) { continue; }
            $x = (float) ( $el['x'] ?? 0 ); $y = (float) ( $el['y'] ?? 0 );
            $w = (float) ( $el['width'] ?? 0 ); $h = (float) ( $el['height'] ?? 0 );
            if ( $x < $canvas_w * 0.55 || $y < $canvas_h * 0.48 ) { continue; }
            if ( $w < 45 || $h < 45 || $w > 180 || $h > 180 ) { continue; }
            $ratio = $h > 0 ? $w / $h : 0;
            if ( $ratio < 0.65 || $ratio > 1.35 ) { continue; }
            $src = (string) ( $el['src'] ?? '' );
            $score = ( $x / $canvas_w ) + ( $y / $canvas_h ) + ( 1 - min( 1, abs( 1 - $ratio ) ) );
            if ( false !== stripos( $src, 'qr' ) ) { $score += 3; }
            $el['_score'] = $score;
            $candidates[] = $el;
        }
        if ( ! $candidates ) { return array(); }
        usort( $candidates, function( $a, $b ) { return $b['_score'] <=> $a['_score']; } );
        return $candidates[0];
    }

    private static function planned_terms( $event ) {
        $lines = array();
        if ( ! empty( $event->venue_address ) ) { $lines[] = 'Adres: ' . trim( (string) $event->venue_address ); }
        $lines[] = 'Bilgi & Destek WhatsApp Hattı: +90 312 911 37 10';
        if ( 'free' === (string) $event->seating_type ) { $lines[] = 'Koltuk numarası bulunmamaktadır. Lütfen seans saatinden en az 30 dakika önce salonda hazır bulununuz.'; }
        elseif ( ! empty( $event->rules ) ) { $lines[] = trim( (string) $event->rules ); }
        $lines[] = 'Fatura Bilgilendirmesi: Faturanızı etkinlik günü salon girişindeki görevlilerimizden teslim alabilirsiniz.';
        return implode( "\n", array_filter( array_unique( $lines ) ) );
    }

    private static function is_same_managed_object( $post_id, $production_key, $event_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || ! get_post( $post_id ) ) { return false; }
        if ( '1' !== (string) get_post_meta( $post_id, '_mdg_managed', true ) ) { return false; }
        if ( (string) get_post_meta( $post_id, '_mdg_production_key', true ) !== (string) $production_key ) { return false; }
        $meta_event_id = absint( get_post_meta( $post_id, '_mdg_event_id', true ) );
        return $meta_event_id === absint( $event_id );
    }

    private static function product_slug_collision( $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'product' );
        return $post ? (int) $post->ID : 0;
    }

    private static function tickera_slug_collision( $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'tc_events' );
        return $post ? (int) $post->ID : 0;
    }

    private static function sku_collision( $sku ) {
        if ( function_exists( 'wc_get_product_id_by_sku' ) ) { return absint( wc_get_product_id_by_sku( $sku ) ); }
        return 0;
    }

    private static function city_code( $province ) {
        $map = array(
            'Adana'=>'ADA','Adıyaman'=>'ADI','Afyonkarahisar'=>'AFY','Ağrı'=>'AGR','Amasya'=>'AMA','Ankara'=>'ANK','Antalya'=>'ANT','Artvin'=>'ART','Aydın'=>'AYD','Balıkesir'=>'BAL','Bilecik'=>'BIL','Bingöl'=>'BIN','Bitlis'=>'BIT','Bolu'=>'BOL','Burdur'=>'BRD','Bursa'=>'BUR','Çanakkale'=>'CAN','Çankırı'=>'CKR','Çorum'=>'COR','Denizli'=>'DEN','Diyarbakır'=>'DIY','Edirne'=>'EDI','Elazığ'=>'ELA','Erzincan'=>'ERC','Erzurum'=>'ERZ','Eskişehir'=>'ESK','Gaziantep'=>'GAZ','Giresun'=>'GIR','Gümüşhane'=>'GUM','Hakkari'=>'HAK','Hatay'=>'HAT','Isparta'=>'ISP','Mersin'=>'MER','İstanbul'=>'IST','İzmir'=>'IZM','Kars'=>'KRS','Kastamonu'=>'KAS','Kayseri'=>'KAY','Kırklareli'=>'KIR','Kırşehir'=>'KSH','Kocaeli'=>'KOC','Konya'=>'KON','Kütahya'=>'KUT','Malatya'=>'MAL','Manisa'=>'MAN','Kahramanmaraş'=>'KMR','Mardin'=>'MAR','Muğla'=>'MUG','Muş'=>'MUS','Nevşehir'=>'NEV','Niğde'=>'NIG','Ordu'=>'ORD','Rize'=>'RIZ','Sakarya'=>'SAK','Samsun'=>'SAM','Siirt'=>'SII','Sinop'=>'SIN','Sivas'=>'SIV','Tekirdağ'=>'TEK','Tokat'=>'TOK','Trabzon'=>'TRA','Tunceli'=>'TUN','Şanlıurfa'=>'URF','Uşak'=>'USK','Van'=>'VAN','Yozgat'=>'YOZ','Zonguldak'=>'ZON','Aksaray'=>'AKS','Bayburt'=>'BAY','Karaman'=>'KAR','Kırıkkale'=>'KRK','Batman'=>'BAT','Şırnak'=>'SRN','Bartın'=>'BAR','Ardahan'=>'ARD','Iğdır'=>'IGD','Yalova'=>'YAL','Karabük'=>'KRB','Kilis'=>'KIL','Osmaniye'=>'OSM','Düzce'=>'DUZ'
        );
        if ( isset( $map[ $province ] ) ) { return $map[ $province ]; }
        $x = strtoupper( remove_accents( (string) $province ) );
        $x = preg_replace( '/[^A-Z]/', '', $x );
        return substr( str_pad( $x, 3, 'X' ), 0, 3 );
    }

    private static function district_code( $district ) {
        $x = strtoupper( remove_accents( trim( (string) $district ) ) );
        $x = preg_replace( '/[^A-Z0-9]+/', '-', $x );
        $x = trim( $x, '-' );
        if ( '' === $x ) { return 'MERKEZ'; }
        return substr( $x, 0, 24 );
    }
    private static function ticket_suffix( $code, $label ) {
        $c = strtoupper( remove_accents( (string) $code ) );
        $l = strtoupper( remove_accents( (string) $label ) );
        if ( false !== strpos( $c, 'COCUK' ) || false !== strpos( $l, 'COCUK' ) ) { return 'C'; }
        if ( false !== strpos( $c, 'YETISKIN' ) || false !== strpos( $l, 'YETISKIN' ) ) { return 'Y'; }
        if ( false !== strpos( $c, 'AILE' ) || false !== strpos( $l, 'AILE' ) ) { return 'A22'; }
        $x = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', remove_accents( (string) $code ) ) );
        return substr( $x ?: 'B', 0, 6 );
    }

    private static function human_date_tr( $ymd ) {
        $months = array( 1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık' );
        $p = explode( '-', (string) $ymd );
        if ( 3 !== count( $p ) ) { return (string) $ymd; }
        $m = absint( $p[1] );
        return absint( $p[2] ) . ' ' . ( $months[ $m ] ?? $p[1] ) . ' ' . absint( $p[0] );
    }

    private static function utc_to_local_mysql( $utc_mysql ) {
        try {
            $dt = new DateTimeImmutable( (string) $utc_mysql, new DateTimeZone( 'UTC' ) );
            return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
        } catch ( Throwable $e ) { return (string) $utc_mysql; }
    }

    private static function plugin_active_contains( $basename ) {
        $active = (array) get_option( 'active_plugins', array() );
        if ( in_array( $basename, $active, true ) ) { return true; }
        if ( is_multisite() ) {
            $network = (array) get_site_option( 'active_sitewide_plugins', array() );
            if ( isset( $network[ $basename ] ) ) { return true; }
        }
        return false;
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }
}
