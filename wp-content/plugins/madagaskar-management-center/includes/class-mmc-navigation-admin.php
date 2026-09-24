<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC v1.3.8 unified admin navigation.
 *
 * This class changes navigation only:
 * - Existing MDG and Okul Tanıtım pages keep their original slugs/callbacks.
 * - WooCommerce, Tickera, PayTR, QR and legacy database writes are untouched.
 * - If MMC is disabled, legacy top-level menus return automatically.
 */
class MMC_Navigation_Admin {
    private $mdg_items = array();
    private $school_items = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'merge_legacy_menus' ), 99999 );
        add_filter( 'parent_file', array( $this, 'parent_file' ), 99999 );
        add_filter( 'submenu_file', array( $this, 'submenu_file' ), 99999 );
    }

    public function merge_legacy_menus() {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return;
        }

        global $submenu;

        $this->mdg_items    = $this->capture_items( isset( $submenu['mdg-dashboard'] ) ? $submenu['mdg-dashboard'] : array() );
        $this->school_items = $this->capture_items( isset( $submenu['mad-okul'] ) ? $submenu['mad-okul'] : array() );

        if ( $this->mdg_items ) {
            remove_menu_page( 'mdg-dashboard' );
            add_submenu_page(
                'mmc-dashboard',
                'Bilet Yönetimi',
                'Bilet Yönetimi',
                'manage_woocommerce',
                'mmc-mdg-hub',
                array( $this, 'mdg_hub' ),
                55
            );
        }

        if ( $this->school_items ) {
            remove_menu_page( 'mad-okul' );
            add_submenu_page(
                'mmc-dashboard',
                'Okul Tanıtım',
                'Okul Tanıtım',
                'manage_options',
                'mmc-school-hub',
                array( $this, 'school_hub' ),
                75
            );
        }
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

    public function mdg_hub() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-management-center' ) );
        }

        $this->render_hub(
            'Bilet Yönetimi',
            'Eski Madagaskar Bilet Yönetimi motoru aynı slug, callback ve veri zinciriyle çalışmaya devam eder. MMC yalnızca yönetim navigasyonunu tek menü altında toplar.',
            $this->mdg_items,
            array(
                array(
                    'title' => 'MMC Satış & Doluluk',
                    'url'   => $this->mmc_url( 'mmc-sales' ),
                    'note'  => 'MMC Program ID bazlı satış görünümü ve MDG mutabakatı.',
                ),
                array(
                    'title' => 'Program Bütünlüğü',
                    'url'   => $this->mmc_url( 'mmc-integrity' ),
                    'note'  => 'MMC ↔ MDG ↔ WooCommerce ↔ Tickera bütünlük kontrolü.',
                ),
            ),
            'mdg'
        );
    }

    public function school_hub() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-management-center' ) );
        }

        $this->render_hub(
            'Okul Tanıtım',
            'Okul Tanıtım eklentisinin okul, MEBBİS, rota, program ve görev ekranları aynı veri tablolarını kullanmaya devam eder. MMC yalnızca bunları tek yönetim menüsünde toplar.',
            $this->school_items,
            array(
                array(
                    'title' => 'MMC Okul / Saha',
                    'url'   => $this->mmc_url( 'mmc-field' ),
                    'note'  => 'MMC Program ID bazlı hedef okul ve saha yönetimi.',
                ),
                array(
                    'title' => 'Program Bütünlüğü',
                    'url'   => $this->mmc_url( 'mmc-integrity' ),
                    'note'  => 'MMC Program ID ↔ Okul Tanıtım legacy program köprüsü.',
                ),
            ),
            'school'
        );
    }

    private function render_hub( $title, $description, $items, $mmc_links, $type ) {
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

            <div class="notice notice-info inline">
                <p><strong>Güvenli birleşim modu:</strong> Bu ekran eski modüllerin veri yapısını veya satış motorunu taşımaz. Yalnızca WordPress yönetim menüsünü tek çatı altında toplar. MMC devre dışı bırakılırsa eski üst menüler otomatik olarak geri gelir.</p>
            </div>

            <div class="mmc-panel">
                <h2>MMC Bağlantıları</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    <?php foreach ( $mmc_links as $link ) : ?>
                        <a href="<?php echo esc_url( $link['url'] ); ?>" style="display:block;text-decoration:none;border:1px solid #dcdcde;border-radius:8px;padding:14px;background:#fff">
                            <strong style="display:block;margin-bottom:5px"><?php echo esc_html( $link['title'] ); ?></strong>
                            <span style="color:#646970"><?php echo esc_html( $link['note'] ); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mmc-panel">
                <h2><?php echo 'mdg' === $type ? 'Madagaskar Bilet Yönetimi Ekranları' : 'Okul Tanıtım Ekranları'; ?></h2>
                <?php if ( ! $items ) : ?>
                    <p>Bağlı eski modül menüsü bulunamadı.</p>
                <?php else : ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    <?php foreach ( $items as $item ) :
                        if ( ! current_user_can( $item['capability'] ) ) { continue; }
                        $url = $this->legacy_url( $item['slug'], $program_id );
                        $note = $this->item_note( $item['slug'], $type );
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" style="display:block;text-decoration:none;border:1px solid #dcdcde;border-radius:8px;padding:14px;background:#fff">
                            <strong style="display:block;margin-bottom:5px"><?php echo esc_html( $item['title'] ); ?></strong>
                            <span style="color:#646970"><?php echo esc_html( $note ); ?></span>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
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

    private function current_page() {
        return sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
    }

    public function parent_file( $parent_file ) {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return $parent_file;
        }

        $page = $this->current_page();
        if ( 0 === strpos( $page, 'mdg-' ) ) {
            return 'mmc-dashboard';
        }

        if ( 0 === strpos( $page, 'mad-okul' ) && 'mad-okul-my-tasks' !== $page ) {
            return 'mmc-dashboard';
        }

        return $parent_file;
    }

    public function submenu_file( $submenu_file ) {
        if ( ! current_user_can( 'mmc_view_dashboard' ) ) {
            return $submenu_file;
        }

        $page = $this->current_page();
        if ( 0 === strpos( $page, 'mdg-' ) ) {
            return 'mmc-mdg-hub';
        }

        if ( 0 === strpos( $page, 'mad-okul' ) && 'mad-okul-my-tasks' !== $page ) {
            return 'mmc-school-hub';
        }

        return $submenu_file;
    }
}
