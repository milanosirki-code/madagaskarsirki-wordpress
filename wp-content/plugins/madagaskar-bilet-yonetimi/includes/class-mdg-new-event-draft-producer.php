<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * V2.9.1 — Yeni etkinlik için güvenli TASLAK satış nesnesi üretimi.
 *
 * Dry-run planı yeşil geçmeden çalışmaz. WooCommerce ürünlerini ve Tickera
 * etkinliğini yalnızca draft/private güvenlik katmanında oluşturur; halka açmaz.
 * Mapping en son yazılır. Hata halinde yalnızca bu production_key ile bu çağrıda
 * oluşturulan nesneler temizlenir.
 */
final class MDG_New_Event_Draft_Producer {

    public static function hooks() {
        add_action( 'admin_post_mdg_create_new_event_sales_draft', array( __CLASS__, 'handle_create' ) );
        add_action( 'admin_post_mdg_rollback_new_event_sales_draft', array( __CLASS__, 'handle_rollback' ) );
    }

    public static function render_controls( array $plan ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $event_id = absint( $plan['event_id'] ?? 0 );
        if ( ! $event_id ) { return; }

        $state = self::state( $event_id, (string) ( $plan['production_key'] ?? '' ) );

        echo '<div class="mdg-panel" style="margin-top:18px;border-left:4px solid #3858e9">';
        echo '<h3 style="margin-top:0">V2.9.1 — Taslak Satış Nesnelerini Oluştur</h3>';
        echo '<p>Bu aşama <strong>halka açılmaz</strong>. 1 Tickera etkinliği, her seans için 1 WooCommerce variable product ve aktif bilet türleri için varyasyonlar oluşturulur. Ürünler <strong>taslak</strong> kalır; mapping ve salon QR Ticket Designer klonu ancak bütün adımlar başarılı olursa bağlanır.</p>';

        if ( isset( $_GET['mdg_draft_production_notice'] ) ) {
            $notice = sanitize_text_field( wp_unslash( $_GET['mdg_draft_production_notice'] ) );
            $type   = sanitize_key( wp_unslash( $_GET['mdg_draft_production_type'] ?? 'ok' ) );
            $class  = 'error' === $type ? 'notice-error' : ( 'warn' === $type ? 'notice-warning' : 'notice-success' );
            echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p>' . esc_html( $notice ) . '</p></div>';
        }

        if ( 'complete' === $state['status'] ) {
            echo '<div class="notice notice-success inline"><p><strong>TASLAK ÜRETİM TAMAMLANDI.</strong> Yeni satış nesneleri oluşturuldu ve MDG mapping yazıldı. Henüz halka açık değiller.</p></div>';
            echo '<table class="widefat striped"><tbody>';
            self::row( 'Production key', $state['production_key'], true );
            self::row( 'Tickera etkinliği', '#' . (int) $state['tickera_event_id'] . ' · ' . get_the_title( (int) $state['tickera_event_id'] ) . ' · ' . get_post_status( (int) $state['tickera_event_id'] ) );
            self::row( 'WooCommerce ürünleri', implode( ', ', array_map( function ( $id ) { return '#' . (int) $id . ' (' . get_post_status( (int) $id ) . ')'; }, $state['product_ids'] ) ) );
            self::row( 'Varyasyonlar', implode( ', ', array_map( function ( $id ) { return '#' . (int) $id; }, $state['variation_ids'] ) ) );
            self::row( 'Ticket Designer', $state['designer_id'] ? 'Designer #' . (int) $state['designer_id'] . ' · etkinliğe özel salon QR klonu' : 'Kontrol gerekli' );
            echo '</tbody></table>';
            echo '<p class="description">Bir sonraki aşama: bu taslak satış nesnelerini tek ekranda doğrulama → yönetici sepet/checkout testi → kontrollü publish. Bu ekrandan otomatik publish yapılmaz.</p>';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:14px" onsubmit="return confirm(&quot;Yalnızca bu etkinlik için V2.9.1 tarafından oluşturulan TASLAK satış nesneleri silinecek ve mapping geri alınacak. Devam edilsin mi?&quot;);">';
            echo '<input type="hidden" name="action" value="mdg_rollback_new_event_sales_draft"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
            wp_nonce_field( 'mdg_rollback_new_event_sales_draft_' . $event_id, 'mdg_nonce' );
            echo '<label style="display:block;margin:8px 0"><input type="checkbox" name="confirm_rollback" value="1" required> Bu etkinliğin yalnızca MDG tarafından oluşturulan taslak satış nesnelerini geri almayı onaylıyorum.</label>';
            submit_button( 'Taslak Üretimi Geri Al', 'secondary', 'submit', false );
            echo '</form>';
            echo '</div>';
            return;
        }

        if ( 'partial' === $state['status'] || 'orphan' === $state['status'] ) {
            echo '<div class="notice notice-warning inline"><p><strong>Kısmi/Yetim üretim izi bulundu.</strong> Yeni kopya oluşturulmadı. Önce bu production key ile ilişkilendirilmiş taslak nesneleri temizleyin.</p></div>';
            if ( $state['object_ids'] ) { echo '<p><code>' . esc_html( implode( ', ', array_map( 'absint', $state['object_ids'] ) ) ) . '</code></p>'; }
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(&quot;Yalnızca MDG production key ile işaretli TASLAK nesneler temizlenecek. Devam edilsin mi?&quot;);">';
            echo '<input type="hidden" name="action" value="mdg_rollback_new_event_sales_draft"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
            wp_nonce_field( 'mdg_rollback_new_event_sales_draft_' . $event_id, 'mdg_nonce' );
            echo '<label style="display:block;margin:8px 0"><input type="checkbox" name="confirm_rollback" value="1" required> Yetim/kısmi taslak üretim izlerini temizlemeyi onaylıyorum.</label>';
            submit_button( 'Güvenli Temizleme / Geri Al', 'secondary', 'submit', false );
            echo '</form></div>';
            return;
        }

        if ( empty( $plan['ready'] ) ) {
            echo '<div class="notice notice-error inline"><p>Dry-run bloklayıcıları çözülmeden oluşturma butonu açılmaz.</p></div></div>';
            return;
        }

        echo '<div class="notice notice-info inline"><p><strong>Oluşturulacak:</strong> 1 Tickera taslağı + ' . (int) $plan['session_count'] . ' WooCommerce variable product + ' . esc_html( self::variation_count( $plan ) ) . ' varyasyon + etkinlik-bazlı salon QR Designer klonu.</p></div>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:14px" onsubmit="return confirm(&quot;Dry-run planındaki satış nesneleri TASLAK olarak oluşturulsun mu? Halka açılmayacaktır.&quot;);">';
        echo '<input type="hidden" name="action" value="mdg_create_new_event_sales_draft"><input type="hidden" name="event_id" value="' . esc_attr( $event_id ) . '">';
        wp_nonce_field( 'mdg_create_new_event_sales_draft_' . $event_id, 'mdg_nonce' );
        echo '<label style="display:block;margin:8px 0"><input type="checkbox" name="confirm_create" value="1" required> Dry-run planını onaylıyorum; satış nesneleri yalnızca TASLAK olarak oluşturulsun.</label>';
        submit_button( 'Taslak Satış Nesnelerini Oluştur', 'primary', 'submit', false );
        echo '</form></div>';
    }

    public static function handle_create() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_admin_referer( 'mdg_create_new_event_sales_draft_' . $event_id, 'mdg_nonce' );
        if ( empty( $_POST['confirm_create'] ) ) { self::redirect( $event_id, 'error', 'Taslak üretim onayı verilmedi.' ); }

        $event = MDG_Events::get( $event_id );
        if ( ! $event || MDG_Status::DRAFT !== (string) $event->status ) { self::redirect( $event_id, 'error', 'Yalnızca MDG taslak etkinliği için üretim yapılabilir.' ); }
        if ( ! class_exists( 'MDG_New_Event_Production_Plan' ) ) { self::redirect( $event_id, 'error', 'Dry-run üretim planı kullanılamıyor.' ); }

        $plan = MDG_New_Event_Production_Plan::build( $event );
        if ( empty( $plan['ready'] ) ) { self::redirect( $event_id, 'error', 'Dry-run hazır değil: ' . implode( ' | ', (array) $plan['errors'] ) ); }

        $state = self::state( $event_id, (string) $plan['production_key'] );
        if ( 'complete' === $state['status'] ) { self::redirect( $event_id, 'ok', 'Taslak satış nesneleri zaten oluşturulmuş; yeni kopya üretilmedi.' ); }
        if ( 'clean' !== $state['status'] ) { self::redirect( $event_id, 'warn', 'Kısmi/Yetim MDG üretim izi var. Önce güvenli temizleme/geri alma işlemini kullanın.' ); }

        $created = array( 'tickera_event_id'=>0, 'product_ids'=>array(), 'variation_ids'=>array(), 'category_id'=>0, 'category_created'=>false );
        try {
            $created['tickera_event_id'] = self::create_tickera_event( $event, $plan );
            if ( ! $created['tickera_event_id'] ) { throw new Exception( 'Tickera etkinlik taslağı oluşturulamadı.' ); }

            $cat = self::ensure_category( (string) $event->province_name, (string) $plan['production_key'] );
            if ( is_wp_error( $cat ) ) { throw new Exception( $cat->get_error_message() ); }
            $created['category_id'] = (int) $cat['term_id'];
            $created['category_created'] = ! empty( $cat['created'] );

            $mapping = array();
            foreach ( (array) $plan['products'] as $product_plan ) {
                $result = self::create_session_product( $event, $product_plan, $plan, $created['tickera_event_id'], $created['category_id'] );
                $created['product_ids'][] = (int) $result['product_id'];
                foreach ( $result['variation_ids'] as $variation_id ) { $created['variation_ids'][] = (int) $variation_id; }
                $mapping[] = $result;
            }

            self::write_mappings( $event_id, $created['tickera_event_id'], $mapping );

            $bind = class_exists( 'MDG_Ticket_Template_QR_Binder' ) ? MDG_Ticket_Template_QR_Binder::bind_event( $event_id ) : array( 'ok'=>false, 'message'=>'Salon QR bağlayıcı sınıfı bulunamadı.' );
            if ( empty( $bind['ok'] ) ) { throw new Exception( 'Ticket Designer salon QR bağlanamadı: ' . (string) ( $bind['message'] ?? '' ) ); }

            self::audit( 'event.sales_draft.created', $event_id, array(
                'production_key'  => $plan['production_key'],
                'tickera_event_id'=> $created['tickera_event_id'],
                'product_ids'     => $created['product_ids'],
                'variation_ids'   => $created['variation_ids'],
                'category_id'     => $created['category_id'],
            ) );
        } catch ( Throwable $e ) {
            // First unwind any mapping/template that may have been written, then remove only this run's objects.
            self::rollback_designer_binding( $event_id );
            self::clear_mappings( $event_id );
            self::delete_objects_by_key( (string) $plan['production_key'] );
            self::delete_managed_category_if_empty( (string) $plan['production_key'] );
            self::redirect( $event_id, 'error', 'Taslak üretim geri alındı: ' . $e->getMessage() );
        }

        self::redirect( $event_id, 'ok', 'Taslak satış nesneleri başarıyla oluşturuldu. Halka açılmadı; sonraki aşama doğrulama ve testtir.' );
    }

    public static function handle_rollback() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Yetkiniz yok.' ); }
        $event_id = absint( $_POST['event_id'] ?? 0 );
        check_admin_referer( 'mdg_rollback_new_event_sales_draft_' . $event_id, 'mdg_nonce' );
        if ( empty( $_POST['confirm_rollback'] ) ) { self::redirect( $event_id, 'error', 'Geri alma onayı verilmedi.' ); }

        $event = MDG_Events::get( $event_id );
        if ( ! $event || MDG_Status::DRAFT !== (string) $event->status ) { self::redirect( $event_id, 'error', 'Canlı/satışta etkinlik için bu geri alma aracı kullanılamaz.' ); }
        $plan = MDG_New_Event_Production_Plan::build( $event );
        $key  = (string) ( $plan['production_key'] ?? '' );
        if ( ! $key ) { self::redirect( $event_id, 'error', 'Production key hesaplanamadı.' ); }

        $state = self::state( $event_id, $key );
        if ( ! self::rollback_safe( $state ) ) { self::redirect( $event_id, 'error', 'Geri alma güvenlik kontrolü geçmedi. Publish olmuş veya MDG dışı nesne algılandı.' ); }

        self::rollback_designer_binding( $event_id );
        self::clear_mappings( $event_id );
        self::delete_objects_by_key( $key );
        self::delete_managed_category_if_empty( $key );
        self::audit( 'event.sales_draft.rolled_back', $event_id, array( 'production_key'=>$key ) );
        self::redirect( $event_id, 'ok', 'V2.9.1 taslak satış üretimi geri alındı; MDG etkinlik taslağı korunuyor.' );
    }

    public static function state( $event_id, $production_key ) {
        global $wpdb;
        $event_id = absint( $event_id );
        $sessions = MDG_Sessions::by_event( $event_id );
        $product_ids = array(); $variation_ids = array(); $tickera_ids = array(); $has_mapping = false; $mapping_complete = (bool) $sessions;
        foreach ( (array) $sessions as $session ) {
            $types = MDG_Sessions::ticket_types_by_session( (int) $session->id );
            if ( (int) $session->wc_product_id ) { $product_ids[] = (int) $session->wc_product_id; $has_mapping = true; } else { $mapping_complete = false; }
            if ( (int) $session->tickera_event_id ) { $tickera_ids[] = (int) $session->tickera_event_id; $has_mapping = true; } else { $mapping_complete = false; }
            if ( ! $types ) { $mapping_complete = false; }
            foreach ( (array) $types as $type ) {
                if ( (int) $type->wc_variation_id ) { $variation_ids[] = (int) $type->wc_variation_id; $has_mapping = true; } else { $mapping_complete = false; }
            }
        }
        $product_ids = array_values( array_unique( $product_ids ) );
        $variation_ids = array_values( array_unique( $variation_ids ) );
        $tickera_ids = array_values( array_unique( $tickera_ids ) );

        $objects = self::objects_by_key( $production_key );
        $managed_ids = array_values( array_unique( array_merge( $objects['products'], $objects['variations'], $objects['events'] ) ) );
        $all_mapped_ids = array_values( array_unique( array_merge( $product_ids, $variation_ids, $tickera_ids ) ) );

        $managed_ok = true;
        foreach ( $all_mapped_ids as $id ) {
            if ( '1' !== (string) get_post_meta( $id, '_mdg_managed', true ) || (string) get_post_meta( $id, '_mdg_production_key', true ) !== (string) $production_key ) {
                $managed_ok = false;
                break;
            }
        }
        $tickera_single = 1 === count( $tickera_ids );
        $designer_id = 0;
        $bindings = get_option( 'mdg_ticket_template_qr_bindings_v1', array() );
        if ( is_array( $bindings ) && ! empty( $bindings[ $event_id ]['clone_template_id'] ) ) { $designer_id = absint( $bindings[ $event_id ]['clone_template_id'] ); }

        $status = 'clean';
        if ( $mapping_complete && $has_mapping && $managed_ok && $tickera_single ) { $status = 'complete'; }
        elseif ( $has_mapping ) { $status = 'partial'; }
        elseif ( $managed_ids ) { $status = 'orphan'; }

        return array(
            'status'           => $status,
            'production_key'   => (string) $production_key,
            'product_ids'      => $product_ids,
            'variation_ids'    => $variation_ids,
            'tickera_event_id' => $tickera_single ? (int) $tickera_ids[0] : 0,
            'designer_id'      => $designer_id,
            'object_ids'       => $managed_ids,
        );
    }

    private static function create_tickera_event( $event, array $plan ) {
        $p = $plan['products'][0];
        $first_end_local = self::utc_to_local_mysql( (string) $p['end_at'] );
        $post_id = wp_insert_post( array(
            'post_type'    => 'tc_events',
            'post_status'  => 'draft',
            'post_title'   => (string) $plan['tickera']['title'],
            'post_name'    => (string) $plan['tickera']['slug'],
            'post_content' => wp_kses_post( (string) $event->long_description ),
        ), true );
        if ( is_wp_error( $post_id ) ) { throw new Exception( $post_id->get_error_message() ); }
        $post_id = absint( $post_id );

        $meta = array(
            '_mdg_managed'                 => '1',
            '_mdg_event_id'                => (int) $event->id,
            '_mdg_production_key'          => (string) $plan['production_key'],
            'event_date_time'              => (string) $plan['tickera']['first_start_local'],
            'event_end_date_time'          => $first_end_local,
            'event_location'               => (string) $plan['tickera']['location'],
            'event_terms'                  => (string) $plan['tickera']['event_terms'],
            'event_logo_file_url'          => wp_get_attachment_url( (int) $plan['tickera']['hero_attachment_id'] ) ?: '',
            'event_presentation_page'      => $post_id,
            'hide_event_after_expiration'  => '1',
            'limit_level'                  => '1',
            'limit_level_value'            => (string) max( 1, (int) $p['capacity_total'] ),
            'show_tickets_automatically'   => '0',
        );
        foreach ( $meta as $key => $value ) { update_post_meta( $post_id, $key, $value ); }
        return $post_id;
    }

    private static function create_session_product( $event, array $product_plan, array $plan, $tickera_event_id, $category_id ) {
        if ( ! class_exists( 'WC_Product_Variable' ) || ! class_exists( 'WC_Product_Variation' ) || ! class_exists( 'WC_Product_Attribute' ) ) {
            throw new Exception( 'WooCommerce ürün CRUD sınıfları kullanılamıyor.' );
        }

        $labels = array();
        foreach ( (array) $product_plan['variations'] as $v ) { $labels[] = (string) $v['label']; }
        $labels = array_values( array_unique( $labels ) );

        $product = new WC_Product_Variable();
        $product->set_name( (string) $product_plan['product_name'] );
        $product->set_slug( (string) $product_plan['product_slug'] );
        $product->set_status( 'draft' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_manage_stock( false );
        $product->set_stock_status( 'instock' );
        $product->set_image_id( (int) $event->hero_attachment_id );
        $product->set_description( wp_kses_post( (string) $event->long_description ) );
        $product->set_short_description( wp_kses_post( (string) $event->short_description ) );
        if ( $category_id ) { $product->set_category_ids( array( absint( $category_id ) ) ); }

        $attr = new WC_Product_Attribute();
        $attr->set_id( 0 );
        $attr->set_name( 'Bilet Tipi' );
        $attr->set_options( $labels );
        $attr->set_position( 0 );
        $attr->set_visible( true );
        $attr->set_variation( true );
        $product->set_attributes( array( $attr ) );
        $product_id = absint( $product->save() );
        if ( ! $product_id ) { throw new Exception( 'WooCommerce seans ürünü oluşturulamadı: ' . $product_plan['product_name'] ); }

        self::set_product_bridge_meta( $product_id, $event, $product_plan, $plan, $tickera_event_id );
        self::set_base_designer_template( $product_id, absint( $plan['template']['base_template_id'] ) );

        $variation_ids = array();
        $map = array();
        foreach ( (array) $product_plan['variations'] as $vplan ) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id( $product_id );
            $variation->set_status( 'publish' ); // Parent draft olduğu için halka açık/satın alınabilir değildir.
            $variation->set_sku( (string) $vplan['sku'] );
            $variation->set_regular_price( (string) $vplan['price'] );
            $variation->set_price( (string) $vplan['price'] );
            $variation->set_virtual( true );
            $variation->set_manage_stock( false );
            $variation->set_stock_status( 'instock' );
            $variation->set_image_id( (int) $event->hero_attachment_id );
            $variation->set_attributes( array( sanitize_title( 'Bilet Tipi' ) => (string) $vplan['label'] ) );
            $variation_id = absint( $variation->save() );
            if ( ! $variation_id ) { throw new Exception( 'WooCommerce varyasyonu oluşturulamadı: ' . $vplan['label'] ); }

            update_post_meta( $variation_id, '_mdg_managed', '1' );
            update_post_meta( $variation_id, '_mdg_event_id', (int) $event->id );
            update_post_meta( $variation_id, '_mdg_session_id', (int) $product_plan['session_id'] );
            update_post_meta( $variation_id, '_mdg_ticket_type_id', (int) $vplan['ticket_type_id'] );
            update_post_meta( $variation_id, '_mdg_production_key', (string) $plan['production_key'] );
            self::set_base_designer_template( $variation_id, absint( $plan['template']['base_template_id'] ) );

            $variation_ids[] = $variation_id;
            $map[] = array( 'ticket_type_id'=>(int) $vplan['ticket_type_id'], 'variation_id'=>$variation_id );
        }

        // Let WooCommerce recalculate variable product lookup data after children exist.
        if ( class_exists( 'WC_Product_Variable' ) ) { WC_Product_Variable::sync( $product_id ); }
        wc_delete_product_transients( $product_id );

        return array(
            'session_id'    => (int) $product_plan['session_id'],
            'product_id'    => $product_id,
            'variation_ids' => $variation_ids,
            'type_map'      => $map,
        );
    }

    private static function set_product_bridge_meta( $product_id, $event, array $product_plan, array $plan, $tickera_event_id ) {
        $meta = array(
            '_tc_is_ticket'                => 'yes',
            '_event_name'                  => absint( $tickera_event_id ),
            '_allow_ticket_checkout'       => 'no',
            '_available_checkins_per_ticket'=> '1',
            '_ticket_availability'         => 'open_ended',
            '_ticket_checkin_availability' => 'open_ended',
            '_ticket_template'             => '0',
            '_owner_form_template'         => '-1',
            '_mdg_managed'                 => '1',
            '_mdg_event_id'                => (int) $event->id,
            '_mdg_session_id'              => (int) $product_plan['session_id'],
            '_mdg_production_key'          => (string) $plan['production_key'],
        );
        foreach ( $meta as $key=>$value ) { update_post_meta( $product_id, $key, $value ); }
    }

    private static function set_base_designer_template( $object_id, $template_id ) {
        if ( ! $template_id ) { return; }
        if ( function_exists( 'tickera_ticket_designer_save_template_value' ) ) {
            tickera_ticket_designer_save_template_value( $object_id, 'd_' . absint( $template_id ), '_ticket_template' );
        } else {
            update_post_meta( $object_id, '_ticket_template', 'd_' . absint( $template_id ) );
        }
        update_post_meta( $object_id, 'tc_designer_template_id', absint( $template_id ) );
    }

    private static function write_mappings( $event_id, $tickera_event_id, array $mapping ) {
        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $now = MDG_DB::now();
        $wpdb->query( 'START TRANSACTION' );
        try {
            foreach ( $mapping as $row ) {
                $fresh = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sessions_table} WHERE id=%d AND event_id=%d FOR UPDATE", (int)$row['session_id'], (int)$event_id ) );
                if ( ! $fresh ) { throw new Exception( 'MDG seansı bulunamadı.' ); }
                if ( (int)$fresh->wc_product_id || (int)$fresh->tickera_event_id ) { throw new Exception( 'MDG seansı üretim sırasında başka nesneye bağlandı.' ); }
                $ok = $wpdb->update( $sessions_table, array(
                    'wc_product_id'=>(int)$row['product_id'],
                    'tickera_event_id'=>(int)$tickera_event_id,
                    'updated_at'=>$now,
                ), array( 'id'=>(int)$row['session_id'] ) );
                if ( false === $ok ) { throw new Exception( 'MDG seans mapping yazılamadı.' ); }

                foreach ( $row['type_map'] as $tm ) {
                    $fresh_type = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$types_table} WHERE id=%d AND session_id=%d FOR UPDATE", (int)$tm['ticket_type_id'], (int)$row['session_id'] ) );
                    if ( ! $fresh_type ) { throw new Exception( 'MDG bilet türü bulunamadı.' ); }
                    if ( (int)$fresh_type->wc_variation_id ) { throw new Exception( 'MDG bilet türü üretim sırasında başka varyasyona bağlandı.' ); }
                    $ok = $wpdb->update( $types_table, array( 'wc_variation_id'=>(int)$tm['variation_id'], 'updated_at'=>$now ), array( 'id'=>(int)$tm['ticket_type_id'] ) );
                    if ( false === $ok ) { throw new Exception( 'MDG varyasyon mapping yazılamadı.' ); }
                }
            }
            $wpdb->query( 'COMMIT' );
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            throw $e;
        }
    }

    private static function clear_mappings( $event_id ) {
        global $wpdb;
        $sessions_table = MDG_DB::table( 'sessions' );
        $types_table = MDG_DB::table( 'ticket_types' );
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$sessions_table} WHERE event_id=%d", (int)$event_id ) );
        if ( $ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "UPDATE {$types_table} SET wc_variation_id=NULL, updated_at=%s WHERE session_id IN ({$placeholders})", array_merge( array( MDG_DB::now() ), array_map( 'absint', $ids ) ) ) );
        }
        $wpdb->update( $sessions_table, array( 'wc_product_id'=>null, 'tickera_event_id'=>null, 'updated_at'=>MDG_DB::now() ), array( 'event_id'=>(int)$event_id ) );
    }

    private static function ensure_category( $province_name, $production_key ) {
        $exists = term_exists( $province_name, 'product_cat' );
        if ( $exists ) {
            $id = is_array( $exists ) ? absint( $exists['term_id'] ?? 0 ) : absint( $exists );
            return array( 'term_id'=>$id, 'created'=>false );
        }
        $created = wp_insert_term( $province_name, 'product_cat', array( 'slug'=>sanitize_title( $province_name ) ) );
        if ( is_wp_error( $created ) ) { return $created; }
        $id = absint( $created['term_id'] ?? 0 );
        update_term_meta( $id, '_mdg_managed', '1' );
        update_term_meta( $id, '_mdg_production_key', (string) $production_key );
        return array( 'term_id'=>$id, 'created'=>true );
    }

    private static function objects_by_key( $key ) {
        if ( ! $key ) { return array( 'products'=>array(), 'variations'=>array(), 'events'=>array() ); }
        $q = new WP_Query( array(
            'post_type'      => array( 'product','product_variation','tc_events' ),
            'post_status'    => array( 'publish','private','draft','pending','future' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key'=>'_mdg_production_key', 'value'=>(string)$key ) ),
            'no_found_rows'  => true,
        ) );
        $out = array( 'products'=>array(), 'variations'=>array(), 'events'=>array() );
        foreach ( (array) $q->posts as $id ) {
            $type = get_post_type( $id );
            if ( 'product' === $type ) { $out['products'][] = (int)$id; }
            elseif ( 'product_variation' === $type ) { $out['variations'][] = (int)$id; }
            elseif ( 'tc_events' === $type ) { $out['events'][] = (int)$id; }
        }
        return $out;
    }

    private static function delete_objects_by_key( $key ) {
        $objects = self::objects_by_key( $key );
        foreach ( $objects['variations'] as $id ) { if ( self::safe_managed_draft( $id, $key ) ) { wp_delete_post( $id, true ); } }
        foreach ( $objects['products'] as $id ) { if ( self::safe_managed_draft( $id, $key ) ) { wp_delete_post( $id, true ); } }
        foreach ( $objects['events'] as $id ) { if ( self::safe_managed_draft( $id, $key ) ) { wp_delete_post( $id, true ); } }
    }

    private static function delete_created_ids( array $created, $key ) {
        foreach ( array_reverse( array_map( 'absint', (array)$created['variation_ids'] ) ) as $id ) { if ( self::safe_managed_draft( $id, $key ) ) { wp_delete_post( $id, true ); } }
        foreach ( array_reverse( array_map( 'absint', (array)$created['product_ids'] ) ) as $id ) { if ( self::safe_managed_draft( $id, $key ) ) { wp_delete_post( $id, true ); } }
        if ( ! empty( $created['tickera_event_id'] ) && self::safe_managed_draft( (int)$created['tickera_event_id'], $key ) ) { wp_delete_post( (int)$created['tickera_event_id'], true ); }
        if ( ! empty( $created['category_created'] ) ) { self::delete_managed_category_if_empty( $key ); }
    }

    private static function safe_managed_draft( $post_id, $key ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) { return false; }
        if ( '1' !== (string)get_post_meta( $post_id, '_mdg_managed', true ) ) { return false; }
        if ( (string)get_post_meta( $post_id, '_mdg_production_key', true ) !== (string)$key ) { return false; }
        $type = get_post_type( $post_id );
        $status = get_post_status( $post_id );
        if ( 'product_variation' === $type ) {
            $parent = wp_get_post_parent_id( $post_id );
            return $parent && in_array( get_post_status( $parent ), array( 'draft','private','pending' ), true );
        }
        return in_array( $status, array( 'draft','private','pending' ), true );
    }

    private static function rollback_safe( array $state ) {
        foreach ( (array)$state['object_ids'] as $id ) {
            if ( ! self::safe_managed_draft( $id, (string)$state['production_key'] ) ) { return false; }
        }
        return true;
    }

    private static function rollback_designer_binding( $event_id ) {
        $clone_id = 0;
        $bindings = get_option( 'mdg_ticket_template_qr_bindings_v1', array() );
        if ( is_array( $bindings ) && ! empty( $bindings[ (int)$event_id ]['clone_template_id'] ) ) {
            $clone_id = absint( $bindings[ (int)$event_id ]['clone_template_id'] );
        }
        if ( class_exists( 'MDG_Ticket_Template_QR_Binder' ) ) {
            MDG_Ticket_Template_QR_Binder::rollback_event( (int)$event_id );
        }
        if ( $clone_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'tickera_ticket_templates';
            $name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$table} WHERE id=%d", $clone_id ) );
            if ( 0 === strpos( $name, 'MDG AutoQR |' ) ) {
                $wpdb->delete( $table, array( 'id'=>$clone_id ), array( '%d' ) );
            }
        }
    }

    private static function delete_managed_category_if_empty( $key ) {
        $terms = get_terms( array( 'taxonomy'=>'product_cat', 'hide_empty'=>false, 'meta_query'=>array( array( 'key'=>'_mdg_production_key','value'=>(string)$key ) ) ) );
        if ( is_wp_error( $terms ) ) { return; }
        foreach ( (array)$terms as $term ) {
            if ( '1' !== (string)get_term_meta( $term->term_id, '_mdg_managed', true ) ) { continue; }
            $posts = get_posts( array( 'post_type'=>'product', 'post_status'=>'any', 'posts_per_page'=>1, 'fields'=>'ids', 'tax_query'=>array( array( 'taxonomy'=>'product_cat','field'=>'term_id','terms'=>array( $term->term_id ) ) ) ) );
            if ( ! $posts ) { wp_delete_term( $term->term_id, 'product_cat' ); }
        }
    }

    private static function utc_to_local_mysql( $utc_mysql ) {
        try {
            $dt = new DateTimeImmutable( (string)$utc_mysql, new DateTimeZone( 'UTC' ) );
            return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
        } catch ( Throwable $e ) { return (string)$utc_mysql; }
    }

    private static function variation_count( array $plan ) {
        $n = 0;
        foreach ( (array)$plan['products'] as $p ) { $n += count( (array)$p['variations'] ); }
        return $n;
    }

    private static function row( $label, $value, $code = false ) {
        echo '<tr><th style="width:240px">' . esc_html( $label ) . '</th><td>' . ( $code ? '<code>' . esc_html( $value ) . '</code>' : esc_html( $value ) ) . '</td></tr>';
    }

    private static function redirect( $event_id, $type, $message ) {
        $url = add_query_arg( array(
            'page'                        => 'mdg-publish',
            'edit'                        => absint( $event_id ),
            'mdg_production_plan'         => 1,
            'mdg_draft_production_type'   => sanitize_key( $type ),
            'mdg_draft_production_notice' => rawurlencode( (string)$message ),
        ), admin_url( 'admin.php' ) ) . '#mdg-production-plan';
        wp_safe_redirect( $url );
        exit;
    }

    private static function audit( $action, $event_id, array $context ) {
        global $wpdb;
        $wpdb->insert( MDG_DB::table( 'audit_log' ), array(
            'user_id'     => get_current_user_id(),
            'action_key'  => (string)$action,
            'object_type' => 'event',
            'object_id'   => (int)$event_id,
            'context'     => wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            'created_at'  => MDG_DB::now(),
        ) );
    }
}
