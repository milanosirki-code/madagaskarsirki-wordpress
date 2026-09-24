<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC v1.3.25 unified admin navigation.
 *
 * Navigation only:
 * - Existing MMC, MDG and Okul Tanıtım page slugs/callbacks stay intact.
 * - Detail pages stay registered in WordPress and are hidden only in the rendered admin menu.
 * - WooCommerce, Tickera, PayTR, QR, MDG and school data are untouched.
 * - If MMC is disabled, legacy top-level menus return automatically.
 */
class MMC_Navigation_Admin {
    private $mdg_items = array();
    private $v4_items = array();
    private $school_items = array();
    private $extra_legacy_items = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'compact_navigation' ), 999999 );
        add_filter( 'parent_file', array( $this, 'parent_file' ), 999999 );
        add_filter( 'submenu_file', array( $this, 'submenu_file' ), 999999 );
        add_action( 'admin_head', array( $this, 'hub_admin_css' ), 999999 );
        add_action( 'admin_footer', array( $this, 'admin_menu_cleanup_js' ), 999999 );
    }

    public function compact_navigation() {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return;
        }

        global $submenu;

        $this->mdg_items    = $this->capture_items( isset( $submenu['mdg-dashboard'] ) ? $submenu['mdg-dashboard'] : array() );
        $this->v4_items     = $this->capture_items( isset( $submenu['madagaskar-v4'] ) ? $submenu['madagaskar-v4'] : array() );
        $this->school_items = $this->capture_items( isset( $submenu['mad-okul'] ) ? $submenu['mad-okul'] : array() );
        $this->extra_legacy_items = $this->capture_known_top_level_items();

        if ( $this->mdg_items ) {
            remove_menu_page( 'mdg-dashboard' );
        }
        if ( $this->v4_items ) {
            remove_menu_page( 'madagaskar-v4' );
        }
        if ( $this->school_items ) {
            remove_menu_page( 'mad-okul' );
        }
        foreach ( $this->extra_legacy_items as $item ) {
            if ( ! empty( $item['top_level'] ) ) {
                remove_menu_page( $item['slug'] );
            }
        }

        $this->register_hubs();
        // Keep detail pages registered so admin.php?page=... remains accessible.
        $this->rename_visible_pages();
        $this->reorder_visible_pages();
    }

    private function register_hubs() {
        add_submenu_page(
            'mmc-dashboard',
            'Hazırlık & Bölge',
            'Hazırlık & Bölge',
            'mmc_view_programs',
            'mmc-prep-region-hub',
            array( $this, 'prep_region_hub' )
        );

        add_submenu_page(
            'mmc-dashboard',
            'Salon & Etkinlik',
            'Salon & Etkinlik',
            'mmc_view_programs',
            'mmc-venue-event-hub',
            array( $this, 'venue_event_hub' )
        );

        if ( $this->mdg_items || $this->v4_items || $this->extra_legacy_items ) {
            add_submenu_page(
                'mmc-dashboard',
                'Bilet Yönetimi',
                'Bilet Yönetimi',
                'manage_woocommerce',
                'mmc-mdg-hub',
                array( $this, 'mdg_hub' )
            );
        }

        add_submenu_page(
            'mmc-dashboard',
            'Satış & Müşteri',
            'Satış & Müşteri',
            'mmc_view_programs',
            'mmc-sales-customer-hub',
            array( $this, 'sales_customer_hub' )
        );

        add_submenu_page(
            'mmc-dashboard',
            'Okul Tanıtım & Saha',
            'Okul Tanıtım & Saha',
            'mmc_manage_field',
            'mmc-school-hub',
            array( $this, 'school_hub' )
        );

        add_submenu_page(
            'mmc-dashboard',
            'Pazarlama',
            'Pazarlama',
            'mmc_manage_marketing',
            'mmc-marketing-hub',
            array( $this, 'marketing_hub' )
        );

        add_submenu_page(
            'mmc-dashboard',
            'Kommo & AI',
            'Kommo & AI',
            'mmc_manage_kommo',
            'mmc-kommo-hub',
            array( $this, 'kommo_hub' )
        );

        add_submenu_page(
            'mmc-dashboard',
            'Finans',
            'Finans',
            'mmc_manage_finance',
            'mmc-finance-hub',
            array( $this, 'finance_hub' )
        );

        add_submenu_page(
            'mmc-dashboard',
            'Sistem & Yetkiler',
            'Sistem & Yetkiler',
            'mmc_manage_settings',
            'mmc-system-hub',
            array( $this, 'system_hub' )
        );
    }

    private function hidden_detail_pages() {
        return array(
            'mmc-preparation',
            'mmc-region-data',
            'mmc-population-data',
            'mmc-workflow',
            'mmc-venues',
            'mmc-venue-flow',
            'mmc-events',
            'mmc-sales-prep',
            'mmc-sales',
            'mmc-field',
            'mmc-marketing',
            'mmc-kommo',
            'mmc-finance',
            'mmc-roles',
            'mmc-system',
            'mmc-snippets',
        );
    }

    private function rename_visible_pages() {
        global $submenu;
        if ( empty( $submenu['mmc-dashboard'] ) || ! is_array( $submenu['mmc-dashboard'] ) ) {
            return;
        }

        $labels = array(
            'mmc-dashboard'      => 'Kontrol Paneli',
            'mmc-programs'       => 'Programlar',
            'mmc-operations'     => 'Operasyon',
            'mmc-night-reports'  => 'Raporlar',
            'mmc-integrity'      => 'Program Bütünlüğü',
        );

        foreach ( $submenu['mmc-dashboard'] as &$item ) {
            if ( ! is_array( $item ) || empty( $item[2] ) ) {
                continue;
            }
            $slug = (string) $item[2];
            if ( isset( $labels[ $slug ] ) ) {
                $item[0] = $labels[ $slug ];
            }
        }
        unset( $item );
    }

    private function reorder_visible_pages() {
        global $submenu;
        if ( empty( $submenu['mmc-dashboard'] ) || ! is_array( $submenu['mmc-dashboard'] ) ) {
            return;
        }

        $desired = array(
            'mmc-dashboard',
            'mmc-programs',
            'mmc-prep-region-hub',
            'mmc-venue-event-hub',
            'mmc-mdg-hub',
            'mmc-sales-customer-hub',
            'mmc-school-hub',
            'mmc-marketing-hub',
            'mmc-kommo-hub',
            'mmc-operations',
            'mmc-finance-hub',
            'mmc-night-reports',
            'mmc-integrity',
            'mmc-system-hub',
        );

        $by_slug = array();
        $unknown = array();

        foreach ( $submenu['mmc-dashboard'] as $item ) {
            if ( ! is_array( $item ) || empty( $item[2] ) ) {
                continue;
            }
            $slug = (string) $item[2];
            if ( in_array( $slug, $desired, true ) ) {
                $by_slug[ $slug ] = $item;
            } else {
                $unknown[] = $item;
            }
        }

        $ordered = array();
        foreach ( $desired as $slug ) {
            if ( isset( $by_slug[ $slug ] ) ) {
                $ordered[] = $by_slug[ $slug ];
            }
        }

        // Preserve future/third-party MMC modules instead of silently making them unreachable.
        foreach ( $unknown as $item ) {
            $ordered[] = $item;
        }

        $submenu['mmc-dashboard'] = $ordered;
    }

    private function capture_items( $items ) {
        $captured = array();
        $seen = array();

        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) || empty( $item[2] ) ) {
                continue;
            }

            $slug = (string) $item[2];
            if ( isset( $seen[ $slug ] ) ) {
                continue;
            }

            $seen[ $slug ] = true;
            $captured[] = array(
                'title'      => isset( $item[0] ) ? wp_strip_all_tags( (string) $item[0] ) : $slug,
                'capability' => isset( $item[1] ) ? (string) $item[1] : 'manage_options',
                'slug'       => $slug,
            );
        }

        return $captured;
    }

    public function prep_region_hub() {
        $this->guard( 'mmc_view_programs' );
        $this->render_group_hub(
            'Hazırlık & Bölge',
            'Program hazırlığı, tanıtım havzası, bölgesel veri ve iş akışı tek merkezde.',
            array(
                $this->card( 'Program Hazırlığı', 'mmc-preparation', 'mmc_view_programs', 'Tanıtım havzası, hedef ilçe ve program hazırlık kontrolü.' ),
                $this->card( 'Bölge Veri Ambarı', 'mmc-region-data', 'mmc_manage_region_data', 'Nüfus, okul ve öğrenci kaynaklarının program analizine bağlandığı alan.' ),
                $this->card( 'Nüfus ve Eğitim Verisi', 'mmc-population-data', 'manage_options', 'İl ve ilçe bazlı nüfus/eğitim veri kaynağı.' ),
                $this->card( 'İş Akışı', 'mmc-workflow', 'mmc_view_programs', 'Programın hazırlıktan kapanışa görev ve aşama akışı.' )
            )
        );
    }

    public function venue_event_hub() {
        $this->guard( 'mmc_view_programs' );
        $cards = array(
            $this->card( 'Salonlar', 'mmc-venues', 'mmc_view_programs', 'Tekil salon ana kayıtları ve konum bilgileri.' ),
            $this->card( 'Salon & Tahsis', 'mmc-venue-flow', 'mmc_view_programs', 'Aktif program için salon seçimi ve tahsis durumu.' ),
            $this->card( 'Etkinlik & Seans', 'mmc-events', 'mmc_view_programs', 'MMC etkinliği, tarih ve bağımsız seans kayıtları.' ),
            $this->card( 'Satış Hazırlığı', 'mmc-sales-prep', 'mmc_view_programs', 'WooCommerce/Tickera satış nesneleri oluşturulmadan önce hazırlık kontrolü.' )
        );
        foreach ( $this->legacy_bilet_items_for( 'venue' ) as $item ) {
            $legacy = $this->legacy_card( $item, 'mdg' );
            $legacy['title'] = 'MDG ' . $legacy['title'] . ' (Legacy)';
            $cards[] = $legacy;
        }
        $this->render_group_hub(
            'Salon & Etkinlik',
            'Salon ana kayıtları, tahsis, etkinlik, seans ve satış hazırlığı aynı program kimliği altında.',
            $cards
        );
    }

    public function mdg_hub() {
        $this->guard( 'manage_woocommerce' );

        $cards = array(
            $this->legacy_card(
                array( 'title' => 'Aile Paketi 2+2', 'slug' => 'tools.php?page=mdg-family-package-22', 'capability' => 'manage_woocommerce' ),
                'mdg'
            ),
        );
        $cards[0]['note'] = 'Etkinlik bazında 2 yetişkin + 2 çocuk paketini açın ve fiyatını kaydedin.';
        foreach ( $this->legacy_bilet_items_for( 'mdg' ) as $item ) {
            $cards[] = $this->legacy_card( $item, 'mdg' );
        }

        $this->render_group_hub(
            'Bilet Yönetimi',
            'Madagaskar Bilet Yönetimi motorunun çekirdek etkinlik ve bilet operasyonları. Satış, finans, pazarlama, CRM ve entegrasyon araçları kendi merkezlerine ayrılmıştır.',
            $cards
        );
    }

    public function sales_customer_hub() {
        $this->guard( 'mmc_view_programs' );

        $cards = array(
            $this->card( 'Satış & Doluluk', 'mmc-sales', 'mmc_view_programs', 'MMC Program ID bazlı sipariş, bilet, kişi, ciro ve doluluk görünümü.' ),
        );

        foreach ( $this->legacy_bilet_items_for( 'sales' ) as $item ) {
            $cards[] = $this->legacy_card( $item, 'mdg' );
        }

        $this->render_group_hub(
            'Satış & Müşteri',
            'MMC satış ledgerı ile eski MDG order_map raporları mutabakat amacıyla yan yana kullanılır; aynı satış ikinci kez gelir sayılmaz.',
            $cards
        );
    }

    public function school_hub() {
        $this->guard( 'mmc_manage_field' );

        $cards = array(
            $this->card( 'MMC Okul / Saha', 'mmc-field', 'mmc_manage_field', 'MMC Program ID bazlı hedef okul, saha planı ve ziyaret takibi.' ),
        );

        foreach ( $this->school_items as $item ) {
            $cards[] = array(
                'title'      => $item['title'],
                'slug'       => $item['slug'],
                'capability' => $item['capability'],
                'note'       => $this->item_note( $item['slug'], 'school' ),
                'legacy'     => true,
            );
        }

        $this->render_group_hub(
            'Okul Tanıtım & Saha',
            'MMC saha hedefleri ile mevcut Okul Tanıtım, MEBBİS, rota ve görev araçları tek merkezde. Legacy okul verileri taşınmaz veya yeniden yazılmaz.',
            $cards
        );
    }

    public function marketing_hub() {
        $this->guard( 'mmc_manage_marketing' );
        $cards = array(
            $this->card( 'MMC Pazarlama', 'mmc-marketing', 'mmc_manage_marketing', 'Afiş, sosyal medya ve Meta reklam planı.' ),
        );
        foreach ( $this->legacy_bilet_items_for( 'marketing' ) as $item ) {
            $cards[] = $this->legacy_card( $item, 'mdg' );
        }
        $this->render_group_hub( 'Pazarlama', 'MMC pazarlama planı ile eski MDG pazarlama araçları aynı merkezde.', $cards );
    }

    public function kommo_hub() {
        $this->guard( 'mmc_manage_kommo' );
        $cards = array(
            $this->card( 'MMC Kommo & AI', 'mmc-kommo', 'mmc_manage_kommo', 'MMC Program ID bazlı Kommo ve AI profil yönetimi.' ),
        );
        foreach ( $this->legacy_bilet_items_for( 'kommo' ) as $item ) {
            $cards[] = $this->legacy_card( $item, 'mdg' );
        }
        $this->render_group_hub( 'Kommo & AI', 'MMC Kommo profili ile eski MDG CRM araçları aynı merkezde.', $cards );
    }

    public function finance_hub() {
        $this->guard( 'mmc_manage_finance' );
        $cards = array(
            $this->card( 'MMC Finans & Kapanış', 'mmc-finance', 'mmc_manage_finance', 'Program bazlı gelir, gider, fatura, teminat ve kapanış defteri.' ),
        );
        foreach ( $this->legacy_bilet_items_for( 'finance' ) as $item ) {
            $cards[] = $this->legacy_card( $item, 'mdg' );
        }
        $this->render_group_hub( 'Finans', 'MMC finans defteri ile eski MDG gider/kârlılık araçları aynı merkezde.', $cards );
    }

    public function system_hub() {
        $this->guard( 'mmc_manage_settings' );
        $cards = array(
            $this->card( 'Yetkiler', 'mmc-roles', 'mmc_manage_settings', 'MMC rol ve yetki yönetimi.' ),
            $this->card( 'Kurulum & Sağlık', 'mmc-system', 'mmc_manage_settings', 'Eklenti, tablo, entegrasyon ve sistem sağlık kontrolleri.' ),
            $this->card( 'Snippet Envanteri', 'mmc-snippets', 'mmc_manage_settings', 'Code Snippets kayıtlarını sınıflandırır; test, legacy ve çakışma adaylarını salt-okunur gösterir.' ),
        );
        foreach ( $this->legacy_bilet_items_for( 'system' ) as $item ) {
            $cards[] = $this->legacy_card( $item, 'mdg' );
        }
        $this->render_group_hub( 'Sistem & Yetkiler', 'Rol, yetki, kurulum, sağlık, entegrasyon ve geliştirici araçları.', $cards );
    }

    private function render_legacy_hub( $title, $description, $items, $type ) {
        $cards = array();

        foreach ( $items as $item ) {
            $cards[] = array(
                'title'      => $item['title'],
                'slug'       => $item['slug'],
                'capability' => $item['capability'],
                'note'       => $this->item_note( $item['slug'], $type ),
                'legacy'     => true,
            );
        }

        $this->render_group_hub( $title, $description, $cards );
    }

    private function render_group_hub( $title, $description, $cards ) {
        $program_id = class_exists( 'MMC_Integrity_Service' ) ? MMC_Integrity_Service::active_program_id() : 0;
        ?>
        <div class="wrap mmc-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <p class="mmc-lead"><?php echo esc_html( $description ); ?></p>

            <div class="mmc-panel">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                <?php
                $shown = 0;
                foreach ( $cards as $card ) :
                    if ( empty( $card['slug'] ) || empty( $card['capability'] ) || ! current_user_can( $card['capability'] ) ) {
                        continue;
                    }
                    $shown++;
                    $url = ! empty( $card['legacy'] )
                        ? $this->legacy_url( $card['slug'], $program_id )
                        : $this->mmc_url( $card['slug'] );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" style="display:block;text-decoration:none;border:1px solid #dcdcde;border-radius:8px;padding:14px;background:#fff">
                        <strong style="display:block;margin-bottom:5px"><?php echo esc_html( $card['title'] ); ?></strong>
                        <span style="color:#646970"><?php echo esc_html( $card['note'] ); ?></span>
                    </a>
                <?php endforeach; ?>
                </div>
                <?php if ( ! $shown ) : ?><p>Bu bölümde mevcut kullanıcı için erişilebilir araç bulunamadı.</p><?php endif; ?>
            </div>

        </div>
        <?php
    }

    private function legacy_card( $item, $type ) {
        return array(
            'title'      => $item['title'],
            'slug'       => $item['slug'],
            'capability' => $item['capability'],
            'note'       => $this->item_note( $item['slug'], $type ),
            'legacy'     => true,
        );
    }

    private function capture_known_top_level_items() {
        global $menu, $submenu;

        $known = array(
            'madagaskar-etkinlik-yayinla' => array(
                'title' => 'Etkinlik Yayınla (Legacy)',
                'destination' => 'venue',
            ),
        );

        $items = array();
        foreach ( $known as $slug => $meta ) {
            $found = false;

            foreach ( (array) $menu as $row ) {
                if ( ! is_array( $row ) || empty( $row[2] ) || (string) $row[2] !== $slug ) {
                    continue;
                }

                $items[] = array(
                    'title'       => ! empty( $row[0] ) ? wp_strip_all_tags( (string) $row[0] ) : $meta['title'],
                    'capability'  => ! empty( $row[1] ) ? (string) $row[1] : 'manage_woocommerce',
                    'slug'        => $slug,
                    'destination' => $meta['destination'],
                    'top_level'   => true,
                );
                $found = true;
                break;
            }

            if ( $found ) {
                continue;
            }

            if ( ! empty( $submenu[ $slug ] ) ) {
                foreach ( $this->capture_items( $submenu[ $slug ] ) as $row ) {
                    $row['destination'] = $meta['destination'];
                    $row['top_level'] = false;
                    $items[] = $row;
                }
            }
        }

        return $items;
    }

    private function legacy_bilet_items_for( $destination ) {
        $items = array_merge(
            $this->mdg_items_for( $destination ),
            $this->v4_items_for( $destination ),
            $this->extra_items_for( $destination )
        );

        $seen = array();
        $out = array();
        foreach ( $items as $item ) {
            $key = (string) ( $item['slug'] ?? '' );
            if ( '' === $key || isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $out[] = $item;
        }

        return $out;
    }

    private function v4_items_for( $destination ) {
        $items = array();
        foreach ( $this->v4_items as $item ) {
            if ( $this->v4_destination( $item ) === $destination ) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private function extra_items_for( $destination ) {
        $items = array();
        foreach ( $this->extra_legacy_items as $item ) {
            if ( ( $item['destination'] ?? 'mdg' ) === $destination ) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private function v4_destination( $item ) {
        $slug = (string) ( $item['slug'] ?? '' );

        if ( in_array( $slug, array( 'mdg-v4-sales', 'mdg-v4-biletlerim' ), true ) ) {
            return 'sales';
        }
        if ( in_array( $slug, array( 'mdg-v4-integrations', 'mdg-v4-migration' ), true ) ) {
            return 'system';
        }

        return 'mdg';
    }

    private function mdg_items_for( $destination ) {
        $items = array();
        $seen_titles = array();

        foreach ( $this->mdg_items as $item ) {
            if ( $this->mdg_destination( $item ) !== $destination ) {
                continue;
            }

            $title_key = sanitize_title( remove_accents( wp_strip_all_tags( (string) $item['title'] ) ) );
            if ( $title_key && isset( $seen_titles[ $title_key ] ) ) {
                continue;
            }

            if ( $title_key ) {
                $seen_titles[ $title_key ] = true;
            }
            $items[] = $item;
        }

        return $items;
    }

    private function mdg_destination( $item ) {
        $title = sanitize_title( remove_accents( wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ) ) );
        $slug  = sanitize_title( remove_accents( (string) ( $item['slug'] ?? '' ) ) );
        $key   = $title . '-' . $slug;

        if ( false !== strpos( $key, 'gider' ) || false !== strpos( $key, 'karlilik' ) || false !== strpos( $key, 'finans' ) ) {
            return 'finance';
        }
        if ( false !== strpos( $key, 'pazarlama' ) || false !== strpos( $key, 'marketing' ) ) {
            return 'marketing';
        }
        if ( false !== strpos( $key, 'crm' ) || false !== strpos( $key, 'kommo' ) ) {
            return 'kommo';
        }
        if ( false !== strpos( $key, 'entegrasyon' ) || false !== strpos( $key, 'integration' ) || false !== strpos( $key, 'gelistirici' ) || false !== strpos( $key, 'developer' ) ) {
            return 'system';
        }
        if ( false !== strpos( $key, 'satislar-ve-biletler' ) || false !== strpos( $key, 'satis-rapor' ) || false !== strpos( $key, 'musteri' ) || false !== strpos( $key, 'customer' ) || 'iadeler' === $title || false !== strpos( $key, 'refund' ) ) {
            return 'sales';
        }
        if ( 'salonlar' === $title || 'etkinlikler' === $title ) {
            return 'venue';
        }

        return 'mdg';
    }

    private function card( $title, $slug, $capability, $note ) {
        return array(
            'title'      => $title,
            'slug'       => $slug,
            'capability' => $capability,
            'note'       => $note,
            'legacy'     => false,
        );
    }

    private function legacy_url( $slug, $program_id = 0 ) {
        if ( preg_match( '#^https?://#i', $slug ) ) {
            return $slug;
        }

        if ( false !== strpos( $slug, '.php' ) || false !== strpos( $slug, '?' ) ) {
            return admin_url( ltrim( $slug, '/' ) );
        }

        $args = array( 'page' => $slug );
        if ( $program_id ) {
            $args['program_id'] = $program_id;
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    private function mmc_url( $page ) {
        $args = array( 'page' => $page );
        if ( class_exists( 'MMC_Integrity_Service' ) ) {
            $program_id = MMC_Integrity_Service::active_program_id();
            if ( $program_id ) {
                $args['program_id'] = $program_id;
            }
        }
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    private function item_note( $slug, $type ) {
        $notes = array(
            'mdg-dashboard'        => 'Eski MDG genel bakış ve motor durumu.',
            'mdg-publish'          => 'Etkinlik, seans, kapasite ve bilet fiyatı hazırlığı.',
            'mdg-venues'           => 'Eski MDG salon kayıtları ve konum bilgileri.',
            'mdg-live-events'      => 'Yayındaki etkinlikler ve canlı etkinlik sayfaları.',
            'mdg-past-events'      => 'Süresi dolan etkinliklerin arşiv görünümü.',
            'mdg-cancel'           => 'İptal / erteleme ve güvenli iade önizleme araçları.',
            'mdg-reports'          => 'MDG order_map tabanlı satış raporları.',
            'mdg-customers'        => 'Sipariş, müşteri ve Tickera bilet listeleri.',
            'mdg-settings'         => 'MDG teknik ayarları.',
            'madagaskar-v4'        => 'V4 geçiş merkezi genel görünümü; eski motoru otomatik kapatmaz.',
            'mdg-v4-sales'         => 'V4 satış kapatma / açma ve geçiş dönemi satış kontrolleri.',
            'mdg-v4-postpone'      => 'V4 erteleme eşleme, aktarım ve rollback araçları.',
            'mdg-v4-biletlerim'    => 'V4 Biletlerim uyumluluk ve indirme bağlantısı testleri.',
            'mdg-v4-integrations'  => 'V4 WooCommerce / Tickera / PayTR entegrasyon görünümü.',
            'mdg-v4-migration'     => 'Eski eklentilerin geçiş ve kapatma envanteri.',
            'madagaskar-etkinlik-yayinla' => 'Eski tekil Etkinlik Yayınla ekranı; callback ve URL korunur.',
            'mad-okul'             => 'Okul Tanıtım genel operasyon görünümü.',
            'mad-okul-list'        => 'Tüm okul kayıtları ve ziyaret durumları.',
            'mad-okul-import'      => 'MEBBİS okul listesi içe aktarma.',
            'mad-okul-missing'     => 'Adresi eksik kurumların kontrolü.',
            'mad-okul-rural'       => 'Kırsal olarak ayrılan okul kayıtları.',
            'mad-okul-route'       => 'Google Maps rota görünümü.',
            'mad-okul-programs'    => 'Legacy okul programı ve salon kayıtları.',
            'mad-okul-assign'      => 'Saha personeli görev dağıtımı.',
            'mad-okul-settings'    => 'Harita ve rota servis ayarları.',
            'mad-okul-route-plan'  => 'Rota planı ve PDF çıktıları.',
        );

        if ( isset( $notes[ $slug ] ) ) {
            return $notes[ $slug ];
        }

        return 'mdg' === $type
            ? 'Madagaskar Bilet Yönetimi tarafından sağlanan mevcut ekran.'
            : 'Okul Tanıtım eklentisi tarafından sağlanan mevcut ekran.';
    }

    public function hub_admin_css() {
        $page = $this->current_page();
        $hubs = $this->hub_pages();

        if ( ! in_array( $page, $hubs, true ) ) {
            return;
        }

        // Keep warning/error notices visible. Routine success/update notices on navigation hubs
        // are hidden to reduce visual noise, especially on mobile.
        echo '<style>
            #wpbody-content .notice-success,
            #wpbody-content .updated,
            #wpbody-content div.updated { display:none!important; }
        </style>';
    }

    private function hub_pages() {
        return array(
            'mmc-prep-region-hub',
            'mmc-venue-event-hub',
            'mmc-mdg-hub',
            'mmc-sales-customer-hub',
            'mmc-school-hub',
            'mmc-marketing-hub',
            'mmc-kommo-hub',
            'mmc-finance-hub',
            'mmc-system-hub',
        );
    }

    public function admin_menu_cleanup_js() {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return;
        }

        $hidden = wp_json_encode( $this->hidden_detail_pages() );
        $page = $this->current_page();
        $is_hub = in_array( $page, $this->hub_pages(), true ) ? 'true' : 'false';
        $is_mmc = 0 === strpos( $page, 'mmc-' ) ? 'true' : 'false';

        echo '<script>(function(){';
        echo 'var hidden=' . $hidden . ';';
        echo 'document.querySelectorAll("#toplevel_page_mmc-dashboard .wp-submenu a").forEach(function(a){';
        echo 'try{var u=new URL(a.href,window.location.href);var p=u.searchParams.get("page");if(hidden.indexOf(p)!==-1){var li=a.closest("li");if(li){li.style.display="none";}}}catch(e){}';
        echo '});';

        echo 'if(' . $is_hub . '){document.querySelectorAll("#wpbody-content .notice, #wpbody-content .updated").forEach(function(n){';
        echo 'if(n.classList.contains("notice-warning")||n.classList.contains("notice-error")){return;}';
        echo 'if(n.classList.contains("notice-success")||n.classList.contains("updated")){n.style.display="none";}';
        echo '});}';

        // Hide only two known non-operational notices on MMC pages. Real warnings/errors remain visible.
        echo 'if(' . $is_mmc . '){';
        echo 'var phrases=["Madagaskar V5 Finans Güncellemesi","Complete \\"Tickera - Custom Forms\\" Activation Now","Complete \'Tickera - Custom Forms\' Activation Now"];';
        echo 'document.querySelectorAll("#wpbody-content .notice,#wpbody-content .updated,#wpbody-content .error,#wpbody-content [class*=notice]").forEach(function(n){';
        echo 'var t=(n.textContent||"").trim();if(!t){return;}';
        echo 'for(var i=0;i<phrases.length;i++){if(t.indexOf(phrases[i])!==-1){n.style.display="none";break;}}';
        echo '});';
        echo '}';
        echo '})();</script>';
    }

    public static function navigation_health() {
        global $menu;

        $legacy = array(
            'mdg-dashboard' => 'Madagaskar Bilet Yönetimi',
            'madagaskar-v4' => 'Madagaskar V4',
            'mad-okul' => 'Okul Tanıtım',
            'madagaskar-etkinlik-yayinla' => 'Etkinlik Yayınla (Legacy)',
        );

        $visible = array();
        foreach ( (array) $menu as $row ) {
            if ( ! is_array( $row ) || empty( $row[2] ) ) {
                continue;
            }
            $slug = (string) $row[2];
            if ( isset( $legacy[ $slug ] ) ) {
                $visible[] = $legacy[ $slug ] . ' [' . $slug . ']';
            }
        }

        return array(
            'unified' => empty( $visible ),
            'remaining' => $visible,
            'canonical_parent' => 'mmc-dashboard',
        );
    }

    private function guard( $capability ) {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-management-center' ) );
        }
    }

    private function current_page() {
        return sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
    }

    private function group_for_page( $page ) {
        $groups = array(
            'mmc-preparation'     => 'mmc-prep-region-hub',
            'mmc-region-data'     => 'mmc-prep-region-hub',
            'mmc-population-data' => 'mmc-prep-region-hub',
            'mmc-workflow'        => 'mmc-prep-region-hub',
            'mmc-venues'          => 'mmc-venue-event-hub',
            'mmc-venue-flow'      => 'mmc-venue-event-hub',
            'mmc-events'          => 'mmc-venue-event-hub',
            'mmc-sales-prep'      => 'mmc-venue-event-hub',
            'mmc-sales'           => 'mmc-sales-customer-hub',
            'mmc-field'           => 'mmc-school-hub',
            'mmc-marketing'       => 'mmc-marketing-hub',
            'mmc-kommo'           => 'mmc-kommo-hub',
            'mmc-finance'         => 'mmc-finance-hub',
            'mmc-roles'           => 'mmc-system-hub',
            'mmc-system'          => 'mmc-system-hub',
            'mmc-snippets'        => 'mmc-system-hub',
        );

        if ( isset( $groups[ $page ] ) ) {
            return $groups[ $page ];
        }

        if ( in_array( $page, array(
            'mmc-prep-region-hub',
            'mmc-venue-event-hub',
            'mmc-mdg-hub',
            'mmc-sales-customer-hub',
            'mmc-school-hub',
            'mmc-marketing-hub',
            'mmc-kommo-hub',
            'mmc-finance-hub',
            'mmc-system-hub',
        ), true ) ) {
            return $page;
        }

        foreach ( $this->mdg_items as $item ) {
            if ( $item['slug'] !== $page ) {
                continue;
            }
            $destination = $this->mdg_destination( $item );
            $map = array(
                'sales'     => 'mmc-sales-customer-hub',
                'marketing' => 'mmc-marketing-hub',
                'kommo'     => 'mmc-kommo-hub',
                'finance'   => 'mmc-finance-hub',
                'system'    => 'mmc-system-hub',
                'venue'     => 'mmc-venue-event-hub',
            );
            return $map[ $destination ] ?? 'mmc-mdg-hub';
        }

        foreach ( $this->v4_items as $item ) {
            if ( $item['slug'] !== $page ) {
                continue;
            }
            $destination = $this->v4_destination( $item );
            $map = array(
                'sales'  => 'mmc-sales-customer-hub',
                'system' => 'mmc-system-hub',
            );
            return $map[ $destination ] ?? 'mmc-mdg-hub';
        }

        foreach ( $this->extra_legacy_items as $item ) {
            if ( $item['slug'] !== $page ) {
                continue;
            }
            return 'venue' === ( $item['destination'] ?? '' )
                ? 'mmc-venue-event-hub'
                : 'mmc-mdg-hub';
        }

        foreach ( $this->school_items as $item ) {
            if ( $item['slug'] === $page && 'mad-okul-my-tasks' !== $page ) {
                return 'mmc-school-hub';
            }
        }

        if ( 0 === strpos( $page, 'mdg-v4-' ) || 'madagaskar-v4' === $page ) {
            return in_array( $page, array( 'mdg-v4-sales', 'mdg-v4-biletlerim' ), true )
                ? 'mmc-sales-customer-hub'
                : ( in_array( $page, array( 'mdg-v4-integrations', 'mdg-v4-migration' ), true )
                    ? 'mmc-system-hub'
                    : 'mmc-mdg-hub' );
        }

        if ( 'madagaskar-etkinlik-yayinla' === $page ) {
            return 'mmc-venue-event-hub';
        }

        if ( 0 === strpos( $page, 'mdg-' ) ) {
            return 'mmc-mdg-hub';
        }

        if ( 0 === strpos( $page, 'mad-okul' ) && 'mad-okul-my-tasks' !== $page ) {
            return 'mmc-school-hub';
        }

        return '';
    }

    public function parent_file( $parent_file ) {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return $parent_file;
        }

        $group = $this->group_for_page( $this->current_page() );
        return $group ? 'mmc-dashboard' : $parent_file;
    }

    public function submenu_file( $submenu_file ) {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return $submenu_file;
        }

        $group = $this->group_for_page( $this->current_page() );
        return $group ?: $submenu_file;
    }
}
