<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Venue_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 20 );
        add_action( 'admin_post_mmc_add_venue', array( $this, 'handle_add_venue' ) );
        add_action( 'admin_post_mmc_add_program_venue', array( $this, 'handle_add_program_venue' ) );
        add_action( 'admin_post_mmc_update_allocation', array( $this, 'handle_update_allocation' ) );
        add_action( 'admin_post_mmc_generate_allocation_letter', array( $this, 'handle_generate_letter' ) );
        add_action( 'admin_post_mmc_confirm_venue', array( $this, 'handle_confirm_venue' ) );
        add_action( 'admin_post_mmc_update_venue_finance', array( $this, 'handle_update_finance' ) );
    }

    public function menu() {
        add_submenu_page( 'mmc-dashboard', 'Salonlar', 'Salonlar', 'mmc_view_programs', 'mmc-venues', array( $this, 'venues_page' ) );
        add_submenu_page( 'mmc-dashboard', 'Salon & Tahsis', 'Salon & Tahsis', 'mmc_view_programs', 'mmc-venue-flow', array( $this, 'venue_flow_page' ) );
    }

    public function venues_page() {
        $this->guard( 'mmc_view_programs' );
        $venues = MMC_Venue_Service::all_venues();
        $using_mdg = MMC_Venue_Service::mdg_available();
        ?>
        <div class="wrap mmc-wrap">
            <h1>Salonlar</h1>
            <?php $this->notice(); ?>
            <?php if ( $using_mdg ) : ?>
                <div class="notice notice-success inline"><p><strong>Tek salon kaynağı aktif:</strong> Bu ekran salon bilgilerini doğrudan <strong>Madagaskar → Salonlar</strong> kayıtlarından okur. MMC ikinci bir salon ana kaydı oluşturmaz.</p></div>
                <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=mdg-venues' ) ); ?>">Madagaskar → Salonlar Ekranını Aç</a></p>
            <?php else : ?>
                <div class="notice notice-warning inline"><p><strong>Madagaskar Bilet Yönetimi salon kaynağı algılanmadı.</strong> Geriye uyumluluk için eski MMC salon kayıtları gösteriliyor.</p></div>
            <?php endif; ?>

            <div class="mmc-panel">
                <h2>Kayıtlı Salonlar</h2>
                <p class="description">Programlarda salon seçimi bu listedeki kayıtlardan yapılır. Salon adı, il/ilçe, adres, kapasite ve harita bilgisi burada yeniden girilmez.</p>
                <table class="widefat striped"><thead><tr><th>Salon</th><th>İl / İlçe</th><th>Kapasite</th><th>İletişim</th><th>Harita</th><th>Kaynak</th></tr></thead><tbody>
                <?php if ( ! $venues ) : ?><tr><td colspan="6">Henüz salon kaydı yok. <?php if ( $using_mdg ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=mdg-venues' ) ); ?>">Madagaskar → Salonlar</a> ekranından salon ekleyin.<?php endif; ?></td></tr><?php else : foreach ( $venues as $v ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $v->venue_name ); ?></strong><br><small><?php echo esc_html( $v->address ); ?></small></td>
                        <td><?php echo esc_html( $v->province_name . ' / ' . ( $v->district_name ?: '-' ) ); ?></td>
                        <td><?php echo esc_html( $v->default_capacity ?: '-' ); ?></td>
                        <td><?php echo esc_html( trim( $v->contact_name . ' ' . $v->contact_phone ) ?: '-' ); ?></td>
                        <td><?php if ( $v->maps_url ) : ?><a href="<?php echo esc_url( $v->maps_url ); ?>" target="_blank" rel="noopener">Aç</a><?php else : ?>-<?php endif; ?></td>
                        <td><?php echo esc_html( 'mdg' === ( $v->venue_source ?? '' ) ? 'Madagaskar → Salonlar' : 'Eski MMC' ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody></table>
            </div>
        </div>
        <?php
    }

    public function venue_flow_page() {
        $this->guard( 'mmc_view_programs' );
        $programs = MMC_Program_Service::all_programs();
        $program_id = absint( $_GET['program_id'] ?? 0 );
        $program = $program_id ? MMC_Program_Service::get_program( $program_id ) : null;
        ?>
        <div class="wrap mmc-wrap">
            <h1>Salon Araştırması & Tahsis</h1>
            <?php $this->notice(); ?>
            <div class="mmc-panel">
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="mmc-inline-form">
                    <input type="hidden" name="page" value="mmc-venue-flow">
                    <label>Program <select name="program_id" required><option value="">Program seçin</option>
                    <?php foreach ( $programs as $p ) : ?><option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $program_id, $p->id ); ?>><?php echo esc_html( $p->program_code . ' — ' . $p->province_name . ' / ' . ( $p->district_name ?: 'Genel' ) ); ?></option><?php endforeach; ?>
                    </select></label><button class="button button-primary">Aç</button>
                </form>
            </div>
            <?php if ( $program ) $this->render_program_venues( $program ); ?>
        </div>
        <?php
    }

    private function render_program_venues( $program ) {
        $venues = MMC_Venue_Service::all_venues();
        $program_province = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $program->province_name ) : $program->province_name;
        $same_province = array_values( array_filter( $venues, function( $v ) use ( $program_province ) {
            $vp = class_exists( 'MMC_Region_Service' ) ? MMC_Region_Service::normalize_place_name( $v->province_name ) : $v->province_name;
            return $vp === $program_province;
        } ) );
        if ( $same_province ) { $venues = $same_province; }
        usort( $venues, function( $a, $b ) use ( $program ) {
            $ad = strcasecmp( (string)$a->district_name, (string)$program->district_name ) === 0 ? 0 : 1;
            $bd = strcasecmp( (string)$b->district_name, (string)$program->district_name ) === 0 ? 0 : 1;
            if ( $ad !== $bd ) return $ad - $bd;
            return strnatcasecmp( $a->district_name . '|' . $a->venue_name, $b->district_name . '|' . $b->venue_name );
        } );
        $rows = MMC_Venue_Service::venues_for_program( $program->id );
        $statuses = MMC_Venue_Service::allocation_statuses();
        $finance = MMC_Venue_Service::finance_entries_for_program( $program->id );
        $detail_id = absint( $_GET['pv_id'] ?? 0 );
        $detail = $detail_id ? MMC_Venue_Service::get_program_venue( $detail_id ) : null;
        ?>
        <div class="mmc-panel mmc-hero-panel">
            <div><small><?php echo esc_html( $program->program_code ); ?></small><h2><?php echo esc_html( $program->province_name . ' / ' . ( $program->district_name ?: 'Genel' ) ); ?></h2></div>
            <div><strong>Aşama:</strong> <?php echo esc_html( MMC_Program_Service::statuses()[ $program->status ] ?? $program->status ); ?></div>
        </div>

        <?php if ( current_user_can( 'mmc_manage_venues' ) || current_user_can( 'mmc_manage_programs' ) ) : ?>
        <div class="mmc-panel">
            <h2>1. Salon Alternatifi Ekle</h2>
            <?php if ( ! $venues ) : ?><p>Bu il için kayıtlı salon bulunamadı. <a href="<?php echo esc_url( admin_url( 'admin.php?page=mdg-venues' ) ); ?>">Madagaskar → Salonlar</a> ekranından salon ekleyin.</p><?php else : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mmc-form-grid">
                <input type="hidden" name="action" value="mmc_add_program_venue"><input type="hidden" name="program_id" value="<?php echo esc_attr( $program->id ); ?>"><input type="hidden" name="venue_source" value="<?php echo esc_attr( MMC_Venue_Service::primary_source() ); ?>">
                <?php wp_nonce_field( 'mmc_add_program_venue_' . $program->id, 'mmc_nonce' ); ?>
                <label>Salon <select name="venue_id" required><option value="">Seçin</option><?php foreach ( $venues as $v ) : ?><option value="<?php echo esc_attr( $v->id ); ?>"><?php echo esc_html( $v->province_name . ' / ' . ( $v->district_name ?: '-' ) . ' — ' . $v->venue_name ); ?></option><?php endforeach; ?></select></label>
                <label>Öncelik <input type="number" min="1" name="priority_order" value="1"></label>
                <label>Talep Edilen Tarih <input type="date" name="requested_date" value="<?php echo esc_attr( $program->planned_date ); ?>"></label>
                <label>Seans Planı <input name="requested_sessions" placeholder="12:00, 14:00, 16:00"></label>
                <label class="mmc-span-2">Alternatif Tarihler <input name="alternative_dates" placeholder="4 Ekim / 10 Ekim gibi"></label>
                <label class="mmc-span-2">Dilekçe Muhatabı <input name="target_institution" placeholder="Örn. Ankara Gençlik ve Spor İl Müdürlüğüne"></label>
                <label>Kira Bedeli (biliniyorsa) <input type="number" step="0.01" min="0" name="rental_amount"></label>
                <label>Teminat (biliniyorsa) <input type="number" step="0.01" min="0" name="deposit_amount"></label>
                <label>Ödeme Son Tarihi <input type="date" name="payment_due_date"></label>
                <label class="mmc-span-2">Not <textarea name="notes" rows="2"></textarea></label>
                <div><button class="button button-primary">Program Salonlarına Ekle</button></div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="mmc-panel">
            <h2>2. Salon Alternatifleri ve Tahsis Durumu</h2>
            <table class="widefat striped"><thead><tr><th>Salon</th><th>Tarih / Seans</th><th>Tahsis</th><th>Kira</th><th>Teminat</th><th>Seçim</th><th></th></tr></thead><tbody>
            <?php if ( ! $rows ) : ?><tr><td colspan="7">Henüz salon alternatifi eklenmedi.</td></tr><?php else : foreach ( $rows as $r ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $r->venue_name ); ?></strong><br><small><?php echo esc_html( $r->district_name ); ?></small></td>
                    <td><?php echo esc_html( $r->requested_date ?: '-' ); ?><br><small><?php echo esc_html( $r->requested_sessions ?: '-' ); ?></small></td>
                    <td><?php echo esc_html( $statuses[ $r->allocation_status ] ?? $r->allocation_status ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $r->rental_amount, 2 ) . ' TL' ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $r->deposit_amount, 2 ) . ' TL' ); ?></td>
                    <td><?php echo $r->is_selected ? '<strong>✅ Kesin Salon</strong>' : 'Alternatif'; ?></td>
                    <td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page'=>'mmc-venue-flow','program_id'=>$program->id,'pv_id'=>$r->id ), admin_url( 'admin.php' ) ) ); ?>">Aç / Güncelle</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody></table>
        </div>

        <?php if ( $detail && (int) $detail->program_id === (int) $program->id ) : $letter = MMC_Venue_Service::get_latest_letter( $detail->id ); ?>
        <div class="mmc-grid-2">
            <div class="mmc-panel">
                <h2>3. Tahsis Dosyası — <?php echo esc_html( $detail->venue_name ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mmc-form-grid">
                    <input type="hidden" name="action" value="mmc_update_allocation"><input type="hidden" name="program_venue_id" value="<?php echo esc_attr( $detail->id ); ?>">
                    <?php wp_nonce_field( 'mmc_update_allocation_' . $detail->id, 'mmc_nonce' ); ?>
                    <label>Talep Tarihi <input type="date" name="requested_date" value="<?php echo esc_attr( $detail->requested_date ); ?>"></label>
                    <label>Seanslar <input name="requested_sessions" value="<?php echo esc_attr( $detail->requested_sessions ); ?>"></label>
                    <label class="mmc-span-2">Alternatif Tarihler <input name="alternative_dates" value="<?php echo esc_attr( $detail->alternative_dates ); ?>"></label>
                    <label class="mmc-span-2">Muhatap Kurum <input name="target_institution" value="<?php echo esc_attr( $detail->target_institution ); ?>"></label>
                    <label>Durum <select name="allocation_status"><?php foreach ( $statuses as $k=>$v ) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($detail->allocation_status,$k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?></select></label>
                    <label>Giden Evrak Sayısı <input name="outgoing_reference" value="<?php echo esc_attr( $detail->outgoing_reference ); ?>"></label>
                    <label>Gönderim Tarihi <input type="datetime-local" name="request_sent_at" value="<?php echo esc_attr( $detail->request_sent_at ? str_replace(' ','T',substr($detail->request_sent_at,0,16)) : '' ); ?>"></label>
                    <label>Cevap Evrak Sayısı <input name="response_reference" value="<?php echo esc_attr( $detail->response_reference ); ?>"></label>
                    <label>Cevap Tarihi <input type="datetime-local" name="response_at" value="<?php echo esc_attr( $detail->response_at ? str_replace(' ','T',substr($detail->response_at,0,16)) : '' ); ?>"></label>
                    <label>Kira <input type="number" step="0.01" name="rental_amount" value="<?php echo esc_attr( $detail->rental_amount ); ?>"></label>
                    <label>Teminat <input type="number" step="0.01" name="deposit_amount" value="<?php echo esc_attr( $detail->deposit_amount ); ?>"></label>
                    <label>Ödeme Son Tarihi <input type="date" name="payment_due_date" value="<?php echo esc_attr( $detail->payment_due_date ); ?>"></label>
                    <label class="mmc-span-2">Not <textarea name="notes" rows="3"><?php echo esc_textarea( $detail->notes ); ?></textarea></label>
                    <div><button class="button button-primary">Tahsis Kaydını Güncelle</button></div>
                </form>
                <div class="mmc-action-row">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mmc_generate_allocation_letter"><input type="hidden" name="program_venue_id" value="<?php echo esc_attr( $detail->id ); ?>"><?php wp_nonce_field( 'mmc_generate_letter_' . $detail->id, 'mmc_nonce' ); ?><button class="button">Tahsis Dilekçesini Hazırla / Yenile</button></form>
                    <?php if ( 'approved' === $detail->allocation_status || $detail->response_reference ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Bu salon programın kesin salonu olarak işaretlenecek; kira ve teminat finans kayıtları açılacak. Onaylıyor musunuz?');"><input type="hidden" name="action" value="mmc_confirm_venue"><input type="hidden" name="program_venue_id" value="<?php echo esc_attr( $detail->id ); ?>"><?php wp_nonce_field( 'mmc_confirm_venue_' . $detail->id, 'mmc_nonce' ); ?><button class="button button-primary">Salonu Kesinleştir</button></form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mmc-panel">
                <h2>4. Tahsis Dilekçesi</h2>
                <?php if ( $letter ) : ?><p><strong><?php echo esc_html( $letter->title ); ?></strong></p><textarea class="mmc-doc-preview" readonly><?php echo esc_textarea( $letter->content ); ?></textarea><p class="description">Bu sürüm dilekçeyi taslak olarak üretir. Resmî sayı/EBYS/gönderim işlemi insan onayıyla yapılır.</p><?php else : ?><p>Henüz dilekçe üretilmedi.</p><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $finance ) : ?>
        <div class="mmc-panel">
            <h2>5. Salon Finansmanı</h2>
            <p>Kira gerçek giderdir. Teminat, iade edilene kadar <strong>iade bekleyen teminat</strong> olarak ayrı tutulur.</p>
            <table class="widefat striped"><thead><tr><th>Kayıt</th><th>Sınıf</th><th>Tutar</th><th>Vade</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
            <?php foreach ( $finance as $f ) : ?>
                <tr><td><?php echo esc_html( $f->title ); ?></td><td><?php echo esc_html( 'expense' === $f->entry_class ? 'Gider' : ( 'deposit_asset' === $f->entry_class ? 'İade Bekleyen Teminat' : $f->entry_class ) ); ?></td><td><?php echo esc_html( number_format_i18n( (float)$f->amount, 2 ) . ' TL' ); ?></td><td><?php echo esc_html( $f->due_date ?: '-' ); ?></td><td><?php echo esc_html( $f->status ); ?></td><td>
                    <?php if ( current_user_can( 'mmc_manage_finance' ) && 'paid' !== $f->status ) : ?><form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="mmc-finance-form"><input type="hidden" name="action" value="mmc_update_venue_finance"><input type="hidden" name="entry_id" value="<?php echo esc_attr($f->id); ?>"><input type="hidden" name="program_id" value="<?php echo esc_attr($program->id); ?>"><?php wp_nonce_field('mmc_update_finance_'.$f->id,'mmc_nonce'); ?><input name="reference_no" placeholder="Dekont/ref." class="small-text"><button class="button button-small" name="finance_status" value="paid">Ödendi</button></form><?php endif; ?>
                </td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php endif; ?>
        <?php
    }

    public function handle_add_venue() {
        $this->guard_any( array( 'mmc_manage_venues', 'mmc_manage_programs' ) );
        check_admin_referer( 'mmc_add_venue', 'mmc_nonce' );
        $id = MMC_Venue_Service::add_venue( wp_unslash( $_POST ) );
        if ( is_wp_error( $id ) ) wp_die( esc_html( $id->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-venues','mmc_msg'=>'venue_added' ), admin_url('admin.php') ) ); exit;
    }

    public function handle_add_program_venue() {
        $this->guard_any( array( 'mmc_manage_venues', 'mmc_manage_programs' ) );
        $pid = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_add_program_venue_' . $pid, 'mmc_nonce' );
        $id = MMC_Venue_Service::add_program_venue( $pid, wp_unslash( $_POST ) );
        if ( is_wp_error( $id ) ) wp_die( esc_html( $id->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-venue-flow','program_id'=>$pid,'pv_id'=>$id,'mmc_msg'=>'program_venue_added' ), admin_url('admin.php') ) ); exit;
    }

    public function handle_update_allocation() {
        $this->guard_any( array( 'mmc_manage_venues', 'mmc_manage_programs' ) );
        $id = absint( $_POST['program_venue_id'] ?? 0 );
        check_admin_referer( 'mmc_update_allocation_' . $id, 'mmc_nonce' );
        $row = MMC_Venue_Service::get_program_venue( $id );
        $r = MMC_Venue_Service::update_allocation( $id, wp_unslash( $_POST ) );
        if ( is_wp_error( $r ) ) wp_die( esc_html( $r->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-venue-flow','program_id'=>$row->program_id,'pv_id'=>$id,'mmc_msg'=>'allocation_updated' ), admin_url('admin.php') ) ); exit;
    }

    public function handle_generate_letter() {
        $this->guard_any( array( 'mmc_manage_venues', 'mmc_manage_programs' ) );
        $id = absint( $_POST['program_venue_id'] ?? 0 );
        check_admin_referer( 'mmc_generate_letter_' . $id, 'mmc_nonce' );
        $row = MMC_Venue_Service::get_program_venue( $id );
        $r = MMC_Venue_Service::generate_allocation_letter( $id );
        if ( is_wp_error( $r ) ) wp_die( esc_html( $r->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-venue-flow','program_id'=>$row->program_id,'pv_id'=>$id,'mmc_msg'=>'letter_generated' ), admin_url('admin.php') ) ); exit;
    }

    public function handle_confirm_venue() {
        $this->guard_any( array( 'mmc_manage_venues', 'mmc_manage_programs' ) );
        $id = absint( $_POST['program_venue_id'] ?? 0 );
        check_admin_referer( 'mmc_confirm_venue_' . $id, 'mmc_nonce' );
        $row = MMC_Venue_Service::get_program_venue( $id );
        $r = MMC_Venue_Service::confirm_venue( $id );
        if ( is_wp_error( $r ) ) wp_die( esc_html( $r->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-venue-flow','program_id'=>$row->program_id,'pv_id'=>$id,'mmc_msg'=>'venue_confirmed' ), admin_url('admin.php') ) ); exit;
    }

    public function handle_update_finance() {
        $this->guard( 'mmc_manage_finance' );
        $id = absint( $_POST['entry_id'] ?? 0 );
        $pid = absint( $_POST['program_id'] ?? 0 );
        check_admin_referer( 'mmc_update_finance_' . $id, 'mmc_nonce' );
        $r = MMC_Venue_Service::update_finance_entry( $id, sanitize_key($_POST['finance_status'] ?? ''), wp_unslash($_POST['reference_no'] ?? ''), wp_unslash($_POST['notes'] ?? '') );
        if ( is_wp_error( $r ) ) wp_die( esc_html( $r->get_error_message() ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'mmc-venue-flow','program_id'=>$pid,'mmc_msg'=>'finance_updated' ), admin_url('admin.php') ) ); exit;
    }

    private function notice() {
        $msg = sanitize_key( $_GET['mmc_msg'] ?? '' );
        $map = array(
            'venue_added' => 'Salon ana kaydı oluşturuldu.',
            'program_venue_added' => 'Salon program alternatiflerine eklendi.',
            'allocation_updated' => 'Tahsis dosyası güncellendi.',
            'letter_generated' => 'Tahsis dilekçesi taslağı hazırlandı.',
            'venue_confirmed' => 'Salon kesinleştirildi; kira/teminat ve sonraki görevler otomatik açıldı.',
            'finance_updated' => 'Salon finans kaydı güncellendi.',
        );
        if ( isset( $map[$msg] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[$msg] ) . '</p></div>';
    }

    private function guard( $cap ) {
        if ( ! current_user_can( $cap ) ) wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'madagaskar-management-center' ) );
    }

    private function guard_any( $caps ) {
        foreach ( $caps as $cap ) if ( current_user_can( $cap ) ) return;
        wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'madagaskar-management-center' ) );
    }
}
