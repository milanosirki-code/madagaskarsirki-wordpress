<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC v1.3.9 compact unified admin navigation.
 *
 * Navigation only:
 * - Existing MMC, MDG and Okul Tanıtım page slugs/callbacks stay intact.
 * - Detail pages are removed only from the visible submenu and remain directly accessible.
 * - WooCommerce, Tickera, PayTR, QR, MDG and school data are untouched.
 * - If MMC is disabled, legacy top-level menus return automatically.
 */
class MMC_Navigation_Admin {
    private $mdg_items = array();
    private $school_items = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'compact_navigation' ), 999999 );
        add_filter( 'parent_file', array( $this, 'parent_file' ), 999999 );
        add_filter( 'submenu_file', array( $this, 'submenu_file' ), 999999 );
    }

    public function compact_navigation() {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return;
        }

        global $submenu;

        $this->mdg_items    = $this->capture_items( isset( $submenu['mdg-dashboard'] ) ? $submenu['mdg-dashboard'] : array() );
        $this->school_items = $this->capture_items( isset( $submenu['mad-okul'] ) ? $submenu['mad-okul'] : array() );

        if ( $this->mdg_items ) {
            remove_menu_page( 'mdg-dashboard' );
        }
        if ( $this->school_items ) {
            remove_menu_page( 'mad-okul' );
        }

        $this->register_hubs();
        $this->hide_detail_pages();
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

        if ( $this->mdg_items ) {
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
            'Sistem & Yetkiler',
            'Sistem & Yetkiler',
            'mmc_manage_settings',
            'mmc-system-hub',
            array( $this, 'system_hub' )
        );
    }

    private function hide_detail_pages() {
        $hidden = array(
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
            'mmc-roles',
            'mmc-system',
        );

        foreach ( $hidden as $slug ) {
            remove_submenu_page( 'mmc-dashboard', $slug );
        }
    }

    private function rename_visible_pages() {
        global $submenu;
        if ( empty( $submenu['mmc-dashboard'] ) || ! is_array( $submenu['mmc-dashboard'] ) ) {
            return;
        }

        $labels = array(
            'mmc-dashboard'      => 'Kontrol Paneli',
            'mmc-programs'       => 'Programlar',
            'mmc-marketing'      => 'Pazarlama',
            'mmc-kommo'          => 'Kommo & AI',
            'mmc-operations'     => 'Operasyon',
            'mmc-finance'        => 'Finans',
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
            'mmc-marketing',
            'mmc-kommo',
            'mmc-operations',
            'mmc-finance',
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
        $this->render_group_hub(
            'Salon & Etkinlik',
            'Salon ana kayıtları, tahsis, etkinlik, seans ve satış hazırlığı aynı program kimliği altında.',
            array(
                $this->card( 'Salonlar', 'mmc-venues', 'mmc_view_programs', 'Tekil salon ana kayıtları ve konum bilgileri.' ),
                $this->card( 'Salon & Tahsis', 'mmc-venue-flow', 'mmc_view_programs', 'Aktif program için salon seçimi ve tahsis durumu.' ),
                $this->card( 'Etkinlik & Seans', 'mmc-events', 'mmc_view_programs', 'MMC etkinliği, tarih ve bağımsız seans kayıtları.' ),
                $this->card( 'Satış Hazırlığı', 'mmc-sales-prep', 'mmc_view_programs', 'WooCommerce/Tickera satış nesneleri oluşturulmadan önce hazırlık kontrolü.' )
            )
        );
    }

    public function mdg_hub() {
        $this->guard( 'manage_woocommerce' );

        $items = array();
        foreach ( $this->mdg_items as $item ) {
            if ( in_array( $item['slug'], array( 'mdg-reports', 'mdg-customers' ), true ) ) {
                continue;
            }
            $items[] = $item;
        }

        $this->render_legacy_hub(
            'Bilet Yönetimi',
            'Madagaskar Bilet Yönetimi motoru aynı slug, callback ve veri zinciriyle çalışmaya devam eder. Bu merkez bilet motorunun üretim araçlarını toplar.',
            $items,
            'mdg'
        );
    }

    public function sales_customer_hub() {
        $this->guard( 'mmc_view_programs' );

        $cards = array(
            $this->card( 'Satış & Doluluk', 'mmc-sales', 'mmc_view_programs', 'MMC Program ID bazlı sipariş, bilet, kişi, ciro ve doluluk görünümü.' ),
        );

        foreach ( $this->mdg_items as $item ) {
            if ( ! in_array( $item['slug'], array( 'mdg-reports', 'mdg-customers' ), true ) ) {
                continue;
            }
            $cards[] = array(
                'title'      => $item['title'],
                'slug'       => $item['slug'],
                'capability' => $item['capability'],
                'note'       => $this->item_note( $item['slug'], 'mdg' ),
                'legacy'     => true,
            );
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

    public function system_hub() {
        $this->guard( 'mmc_manage_settings' );
        $this->render_group_hub(
            'Sistem & Yetkiler',
            'Rol, yetki, kurulum ve sağlık kontrolleri.',
            array(
                $this->card( 'Yetkiler', 'mmc-roles', 'mmc_manage_settings', 'MMC rol ve yetki yönetimi.' ),
                $this->card( 'Kurulum & Sağlık', 'mmc-system', 'mmc_manage_settings', 'Eklenti, tablo, entegrasyon ve sistem sağlık kontrolleri.' )
            )
        );
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
        $program = $program_id && class_exists( 'MMC_Program_Service' ) ? MMC_Program_Service::get_program( $program_id ) : null;
        ?>
        <div class="wrap mmc-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <p class="mmc-lead"><?php echo esc_html( $description ); ?></p>

            <?php if ( $program ) : ?>
                <div class="mmc-panel mmc-hero-panel">
                    <div>
                        <small>Aktif Program</small>
                        <h2><?php echo esc_html( $program->program_code ); ?></h2>
                        <p><?php echo esc_html( $program->province_name . ' / ' . ( $program->district_name ?: 'Genel' ) ); ?></p>
                    </div>
                    <div><strong>MMC Program ID:</strong> <?php echo (int) $program_id; ?></div>
                </div>
            <?php endif; ?>

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

            <div class="notice notice-info inline">
                <p><strong>Güvenli menü modu:</strong> Bu merkez yalnızca navigasyonu sadeleştirir. Kaynak sayfaların slug, callback, form action ve veri tabloları değiştirilmez.</p>
            </div>
        </div>
        <?php
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
            'mmc-roles'           => 'mmc-system-hub',
            'mmc-system'          => 'mmc-system-hub',
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
            'mmc-system-hub',
        ), true ) ) {
            return $page;
        }

        if ( 0 === strpos( $page, 'mdg-' ) ) {
            return in_array( $page, array( 'mdg-reports', 'mdg-customers' ), true )
                ? 'mmc-sales-customer-hub'
                : 'mmc-mdg-hub';
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
