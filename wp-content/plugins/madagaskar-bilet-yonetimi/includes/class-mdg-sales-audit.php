<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Sales_Audit {
    public static function report() {
        $report = array(
            'woocommerce' => array(
                'active' => class_exists( 'WooCommerce' ),
                'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
                'crud' => function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Variable' ),
                'hpos' => null,
            ),
            'tickera' => array(
                'event_post_type' => post_type_exists( 'tc_events' ),
                'ticket_instance_post_type' => post_type_exists( 'tc_tickets_instances' ),
            ),
            'bridge_plugins' => array(),
            'sample_products' => array(),
            'warnings' => array(),
        );

        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && method_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
            $report['woocommerce']['hpos'] = (bool) \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
        $active = (array) get_option( 'active_plugins', array() );
        if ( is_multisite() ) {
            $network = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
            $active = array_values( array_unique( array_merge( $active, $network ) ) );
        }
        foreach ( $plugins as $file => $data ) {
            $hay = strtolower( (string) ( $data['Name'] ?? '' ) . ' ' . $file . ' ' . ( $data['Description'] ?? '' ) );
            if ( false !== strpos( $hay, 'tickera' ) || false !== strpos( $hay, 'woocommerce bridge' ) || false !== strpos( $hay, 'bridge for woocommerce' ) ) {
                $report['bridge_plugins'][] = array(
                    'file' => $file,
                    'name' => (string) ( $data['Name'] ?? $file ),
                    'version' => (string) ( $data['Version'] ?? '' ),
                    'active' => in_array( $file, $active, true ),
                );
            }
        }

        if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) ) {
            $q = new WP_Query( array(
                'post_type' => 'product',
                'post_status' => array( 'publish', 'private', 'draft' ),
                'posts_per_page' => 8,
                'orderby' => 'ID',
                'order' => 'DESC',
                'fields' => 'ids',
                'meta_query' => array(
                    array( 'key' => '_tc_is_ticket', 'value' => 'yes' ),
                ),
                'no_found_rows' => true,
            ) );
            foreach ( (array) $q->posts as $product_id ) {
                $product = wc_get_product( $product_id );
                if ( ! $product ) { continue; }
                $event_id = absint( get_post_meta( $product_id, '_event_name', true ) );
                $report['sample_products'][] = array(
                    'id' => (int) $product_id,
                    'name' => $product->get_name(),
                    'status' => $product->get_status(),
                    'type' => $product->get_type(),
                    'event_id' => $event_id,
                    'event_title' => $event_id ? get_the_title( $event_id ) : '',
                    'variation_count' => $product->is_type( 'variable' ) ? count( $product->get_children() ) : 0,
                    'sku' => $product->get_sku(),
                );
            }
        }

        if ( ! $report['woocommerce']['active'] ) { $report['warnings'][] = 'WooCommerce aktif değil.'; }
        if ( ! $report['woocommerce']['crud'] ) { $report['warnings'][] = 'WooCommerce ürün CRUD sınıfları algılanmadı.'; }
        if ( ! $report['tickera']['event_post_type'] ) { $report['warnings'][] = 'Tickera tc_events post type algılanmadı.'; }
        if ( ! $report['sample_products'] ) { $report['warnings'][] = 'Mevcut _tc_is_ticket=yes WooCommerce ürünü bulunamadı; Bridge eşlemesi canlı örnek üzerinden doğrulanamıyor.'; }
        $bridge_active = false;
        foreach ( $report['bridge_plugins'] as $plugin ) { if ( ! empty( $plugin['active'] ) ) { $bridge_active = true; break; } }
        if ( ! $bridge_active ) { $report['warnings'][] = 'Aktif Tickera / WooCommerce Bridge eklentisi listede net olarak tespit edilemedi.'; }

        return $report;
    }

    public static function render() {
        $r = self::report();
        echo '<div class="mdg-panel"><h2>V2.6 Satış Motoru Uyumluluk Kontrolü</h2>';
        echo '<p>Bu ekran <strong>salt okunurdur</strong>. WooCommerce ürünü, Tickera etkinliği, sipariş veya bilet oluşturmaz; mevcut çalışan satış altyapısını yalnızca analiz eder.</p>';

        echo '<div class="mdg-cards">';
        self::card( 'WooCommerce', $r['woocommerce']['active'] ? 'Hazır' : 'Eksik', $r['woocommerce']['active'] );
        self::card( 'WC CRUD', $r['woocommerce']['crud'] ? 'Hazır' : 'Eksik', $r['woocommerce']['crud'] );
        self::card( 'HPOS', null === $r['woocommerce']['hpos'] ? 'Bilinmiyor' : ( $r['woocommerce']['hpos'] ? 'Aktif' : 'Legacy' ), true );
        self::card( 'Tickera Event', $r['tickera']['event_post_type'] ? 'Hazır' : 'Eksik', $r['tickera']['event_post_type'] );
        echo '</div>';

        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>WooCommerce sürümü</th><td>' . esc_html( $r['woocommerce']['version'] ?: 'Algılanmadı' ) . '</td></tr>';
        echo '<tr><th>Tickera etkinlik tipi</th><td>' . ( $r['tickera']['event_post_type'] ? '<span class="mdg-status is-active">tc_events aktif</span>' : '<span class="mdg-status is-warning">Algılanmadı</span>' ) . '</td></tr>';
        echo '<tr><th>Tickera bilet instance tipi</th><td>' . ( $r['tickera']['ticket_instance_post_type'] ? '<span class="mdg-status is-active">tc_tickets_instances aktif</span>' : '<span class="description">Algılanmadı</span>' ) . '</td></tr>';
        echo '</tbody></table>';

        echo '<h3 style="margin-top:24px">Tickera / WooCommerce ile ilgili etkin eklentiler</h3>';
        if ( ! $r['bridge_plugins'] ) { echo '<p>İlgili eklenti kaydı bulunamadı.</p>'; }
        else {
            echo '<table class="widefat striped"><thead><tr><th>Eklenti</th><th>Sürüm</th><th>Durum</th></tr></thead><tbody>';
            foreach ( $r['bridge_plugins'] as $p ) {
                echo '<tr><td><strong>' . esc_html( $p['name'] ) . '</strong><br><small>' . esc_html( $p['file'] ) . '</small></td><td>' . esc_html( $p['version'] ) . '</td><td>' . ( $p['active'] ? '<span class="mdg-status is-active">Aktif</span>' : '<span class="mdg-status is-inactive">Pasif</span>' ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h3 style="margin-top:24px">Çalışan Bridge ürünlerinden salt-okunur örnek</h3>';
        echo '<p class="description">Bir sonraki sürümde yeni ürün üretmeden önce mevcut çalışan ürünün Tickera bağını bu örneklerden doğrulayacağız.</p>';
        if ( ! $r['sample_products'] ) { echo '<p>Örnek Tickera/WooCommerce ürünü bulunamadı.</p>'; }
        else {
            echo '<div class="mdg-table-scroll"><table class="widefat striped"><thead><tr><th>Ürün</th><th>Tip / Durum</th><th>Tickera Etkinliği</th><th>Varyasyon</th><th>SKU</th></tr></thead><tbody>';
            foreach ( $r['sample_products'] as $p ) {
                echo '<tr><td><strong>#' . esc_html( $p['id'] ) . ' ' . esc_html( $p['name'] ) . '</strong></td><td>' . esc_html( $p['type'] . ' / ' . $p['status'] ) . '</td><td>' . ( $p['event_id'] ? '#' . esc_html( $p['event_id'] ) . ' ' . esc_html( $p['event_title'] ) : 'Bağ yok' ) . '</td><td>' . esc_html( $p['variation_count'] ) . '</td><td>' . esc_html( $p['sku'] ?: '—' ) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        if ( $r['warnings'] ) {
            echo '<div class="notice notice-warning inline" style="margin-top:20px"><p><strong>Kontrol notları:</strong></p><ul style="list-style:disc;padding-left:20px">';
            foreach ( $r['warnings'] as $w ) { echo '<li>' . esc_html( $w ) . '</li>'; }
            echo '</ul></div>';
        } else {
            echo '<div class="notice notice-success inline" style="margin-top:20px"><p><strong>Temel uyumluluk kontrolleri geçti.</strong> Sonraki adımda yalnızca yeni Madagaskar V2 taslağı için kontrollü ürün/etkinlik üretim dry-run modülü hazırlanabilir.</p></div>';
        }

        echo '<p><strong>V2.6 güvenlik sınırı:</strong> Bu sürüm hiçbir satış nesnesine yazma yapmaz.</p></div>';
    }

    private static function card( $label, $value, $ok ) {
        echo '<div class="mdg-card"><div class="mdg-card-value" style="font-size:20px">' . esc_html( $value ) . '</div><div>' . esc_html( $label ) . '</div></div>';
    }
}
