<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * MMC -> legacy MDG "Etkinlik Yayınla" admin bridge.
 *
 * Keeps the legacy MDG screen as the editor/production surface while MMC remains
 * the program source. No WooCommerce/Tickera/PayTR live object is created here.
 */
class MMC_MDG_Publish_Admin {
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'render_publish_bridge' ), 5 );
        add_action( 'admin_post_mmc_create_mdg_draft', array( $this, 'handle_create_draft' ) );
    }

    public function render_publish_bridge() {
        if ( ! is_admin() || ! $this->is_publish_page() ) { return; }
        if ( ! ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'mmc_manage_programs' ) ) ) { return; }
        if ( ! class_exists( 'MMC_MDG_Bridge_Service' ) ) { return; }

        $programs = MMC_MDG_Bridge_Service::publish_programs();
        $program_id = absint( $_GET['program_id'] ?? 0 );
        $preview = $program_id ? MMC_MDG_Bridge_Service::publish_preview( $program_id ) : null;

        if ( ! empty( $_GET['mmc_mdg_error'] ) ) {
            echo '<div class="notice notice-error"><p><strong>MMC → Etkinlik Yayınla:</strong> ' .
                esc_html( wp_unslash( $_GET['mmc_mdg_error'] ) ) . '</p></div>';
        }

        if ( ! empty( $_GET['mmc_mdg_created'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>MMC programından MDG etkinlik taslağı oluşturuldu.</strong> Salon, tarih, seans ve bilet fiyatları aktarıldı. Görselleri kontrol edip üretim planına geçebilirsiniz.</p></div>';
        }

        echo '<div class="notice notice-info" style="border-left-color:#3858e9;padding:0 14px 14px;margin-top:14px;">';
        echo '<h2 style="margin:14px 0 6px;">MMC Programından Etkinlik Taslağı</h2>';
        echo '<p style="margin-top:0;">Program bilgilerini ikinci kez yazmayın. MMC’de kesinleşen salon, tarih, seans ve bilet fiyatlarını mevcut <strong>Etkinlik Yayınla</strong> taslağına aktarın.</p>';

        if ( ! $programs ) {
            echo '<p><strong>Aktarılabilecek MMC programı bulunamadı.</strong></p>';
            echo '</div>';
            return;
        }

        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin:12px 0;">';
        echo '<input type="hidden" name="page" value="mdg-publish">';
        echo '<label style="min-width:480px;max-width:100%;"><strong>MMC Programı</strong><br>';
        echo '<select name="program_id" required style="min-width:480px;max-width:100%;">';
        echo '<option value="">Program seçin</option>';
        foreach ( $programs as $row ) {
            $p = $row['program'];
            $e = $row['event'];
            $v = $row['venue'];
            $mark = ! empty( $row['bridge'] ) ? '✓ ' : ( ! empty( $row['ready'] ) ? '' : '⚠ ' );
            $label = $mark . $p->program_code . ' — ' .
                ( $e && $e->event_date ? mysql2date( 'd.m.Y', $e->event_date ) . ' — ' : '' ) .
                $p->province_name . ' / ' . ( $p->district_name ?: 'Genel' );
            if ( $v && ! empty( $v->venue_name ) ) {
                $label .= ' — ' . $v->venue_name;
            }
            echo '<option value="' . esc_attr( $p->id ) . '" ' . selected( $program_id, $p->id, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        echo '<button class="button button-primary">Programı Aç</button>';
        echo '</form>';

        if ( ! $preview ) {
            echo '<p class="description">Bir program seçtiğinizde salon, tarih, seans ve fiyat özeti burada görünecek.</p>';
            echo '</div>';
            return;
        }

        $program = $preview['program'];
        $event = $preview['event'];
        $venue = $preview['venue'];

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;margin:12px 0;">';
        $this->summary_card( 'Program', $program ? $program->program_code : '—' );
        $this->summary_card( 'Şehir / İlçe', $program ? $program->province_name . ' / ' . ( $program->district_name ?: 'Genel' ) : '—' );
        $this->summary_card( 'Tarih', $event && $event->event_date ? mysql2date( 'd.m.Y', $event->event_date ) : '—' );
        $this->summary_card( 'Kesin Salon', $venue && ! empty( $venue->venue_name ) ? $venue->venue_name : '—' );
        $this->summary_card( 'Seans', count( (array) $preview['sessions'] ) . ' adet' );
        $this->summary_card( 'Bilet Türü', count( (array) $preview['tickets'] ) . ' adet' );
        echo '</div>';

        if ( ! empty( $preview['sessions'] ) ) {
            $labels = array();
            foreach ( $preview['sessions'] as $session ) {
                $labels[] = mysql2date( 'H:i', $session->session_time ) . ' (' . number_format_i18n( (int) $session->capacity ) . ' kişi)';
            }
            echo '<p><strong>Seanslar:</strong> ' . esc_html( implode( ' · ', $labels ) ) . '</p>';
        }

        if ( ! empty( $preview['tickets'] ) ) {
            $labels = array();
            foreach ( $preview['tickets'] as $ticket ) {
                $labels[] = $ticket->ticket_name . ' ' . number_format_i18n( (float) $ticket->price, 2 ) . ' TL';
            }
            echo '<p><strong>Kendi site fiyatları:</strong> ' . esc_html( implode( ' · ', $labels ) ) . '</p>';
        }

        if ( ! empty( $preview['bridge'] ) ) {
            $mdg_event = MMC_MDG_Bridge_Service::get_mdg_event( (int) $preview['bridge']->mdg_event_id );
            if ( $mdg_event ) {
                $url = add_query_arg(
                    'draft' === (string) $mdg_event->status
                        ? array(
                            'page'       => 'mdg-publish',
                            'edit'       => (int) $mdg_event->id,
                            'program_id' => (int) $program_id,
                        )
                        : array(
                            'page'       => 'mdg-live-events',
                            'program_id' => (int) $program_id,
                        ),
                    admin_url( 'admin.php' )
                );
                echo '<div style="padding:10px 12px;background:#edfaef;border-left:4px solid #00a32a;margin-top:10px;">';
                echo '<strong>✓ Bu MMC programı MDG etkinliğine bağlı.</strong> ';
                echo '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( 'draft' === (string) $mdg_event->status ? 'Bağlı Taslağı Aç' : 'Bağlı Etkinliği Aç' ) . '</a>';
                echo '</div>';
                echo '</div>';
                return;
            }
        }

        if ( empty( $preview['ready'] ) ) {
            echo '<div style="padding:10px 12px;background:#fff8e5;border-left:4px solid #dba617;margin-top:10px;">';
            echo '<strong>Aktarım için eksikler:</strong><ul style="margin-bottom:0;">';
            foreach ( (array) $preview['errors'] as $error ) {
                echo '<li>' . esc_html( $error ) . '</li>';
            }
            echo '</ul>';
            echo '<p style="margin-bottom:0;"><a class="button" href="' . esc_url( add_query_arg( array( 'page'=>'mmc-venue-event-hub', 'program_id'=>$program_id ), admin_url( 'admin.php' ) ) ) . '">Salon & Etkinlik Merkezini Aç</a></p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'mmc_manage_programs' ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
            echo '<input type="hidden" name="action" value="mmc_create_mdg_draft">';
            echo '<input type="hidden" name="program_id" value="' . esc_attr( $program_id ) . '">';
            wp_nonce_field( 'mmc_create_mdg_draft_' . $program_id, 'mmc_nonce' );
            echo '<button class="button button-primary button-hero" onclick="return confirm(\'MMC programından MDG etkinlik taslağı oluşturulsun mu? Bu işlem canlı WooCommerce/Tickera satış nesnesi oluşturmaz.\');">MMC Programından Taslak Oluştur</button>';
            echo '<p class="description">Salon, tarih, seans, kapasite ve bilet fiyatları aktarılır. Taslak olarak kalır; kapak/galeri görsellerini ekledikten ve üretim planını doğruladıktan sonra mevcut MDG yayın akışı devam eder.</p>';
            echo '</form>';
        }

        echo '</div>';
    }

    public function handle_create_draft() {
        if ( ! ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'mmc_manage_programs' ) ) ) {
            wp_die( 'Bu işlemi yapma yetkiniz yok.' );
        }

        $program_id = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_create_mdg_draft_' . $program_id, 'mmc_nonce' );

        if ( ! $program_id || ! class_exists( 'MMC_MDG_Bridge_Service' ) ) {
            $this->redirect_error( $program_id, 'Program veya MDG köprü servisi bulunamadı.' );
        }

        $result = MMC_MDG_Bridge_Service::create_draft_from_program( $program_id );
        if ( is_wp_error( $result ) ) {
            $this->redirect_error( $program_id, $result->get_error_message() );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'            => 'mdg-publish',
                    'edit'            => absint( $result ),
                    'program_id'      => $program_id,
                    'mmc_mdg_created' => 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    private function summary_card( $label, $value ) {
        echo '<div style="border:1px solid #dcdcde;background:#fff;border-radius:6px;padding:9px 10px;">';
        echo '<small style="display:block;color:#646970;margin-bottom:3px;">' . esc_html( $label ) . '</small>';
        echo '<strong>' . esc_html( $value ) . '</strong>';
        echo '</div>';
    }

    private function is_publish_page() {
        return isset( $_GET['page'] ) && 'mdg-publish' === sanitize_key( wp_unslash( $_GET['page'] ) );
    }

    private function redirect_error( $program_id, $message ) {
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'          => 'mdg-publish',
                    'program_id'    => absint( $program_id ),
                    'mmc_mdg_error' => (string) $message,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
