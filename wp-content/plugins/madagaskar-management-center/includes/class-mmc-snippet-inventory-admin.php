<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MMC_Snippet_Inventory_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 45 );
    }

    public function menu() {
        add_submenu_page(
            'mmc-dashboard',
            'Snippet Envanteri',
            'Snippet Envanteri',
            'mmc_manage_settings',
            'mmc-snippets',
            array( $this, 'render' )
        );
    }

    public function render() {
        if ( ! current_user_can( 'mmc_manage_settings' ) ) {
            wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'madagaskar-management-center' ) );
        }

        $items = MMC_Snippet_Inventory_Service::inventory();
        $summary = MMC_Snippet_Inventory_Service::summary();
        $filter = sanitize_key( wp_unslash( $_GET['snippet_class'] ?? '' ) );
        $active_filter = sanitize_key( wp_unslash( $_GET['snippet_active'] ?? '' ) );

        if ( $filter ) {
            $items = array_values( array_filter( $items, function( $x ) use ( $filter ) { return $x['class'] === $filter; } ) );
        }
        if ( 'active' === $active_filter ) {
            $items = array_values( array_filter( $items, function( $x ){ return ! empty( $x['active'] ); } ) );
        } elseif ( 'passive' === $active_filter ) {
            $items = array_values( array_filter( $items, function( $x ){ return empty( $x['active'] ); } ) );
        }
        ?>
        <div class="wrap mmc-wrap">
            <h1>Snippet Envanteri & Çakışma Merkezi</h1>
            <p class="mmc-lead">Code Snippets kayıtlarını salt-okunur analiz eder. Bu ekran snippet kapatmaz, silmez veya kod değiştirmez.</p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin:16px 0">
                <?php
                $cards = array(
                    'Toplam'=>$summary['total'],
                    'Aktif'=>$summary['active'],
                    'Üretim'=>$summary['production'],
                    'Test/Tanı'=>$summary['test'],
                    'Geçici'=>$summary['temporary'],
                    'Legacy'=>$summary['legacy'],
                    'Çakışma Adayı'=>$summary['conflicts'],
                    'Uyarı'=>$summary['warnings'],
                );
                foreach ( $cards as $label=>$value ) :
                ?>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px">
                        <strong style="font-size:24px;display:block"><?php echo (int)$value; ?></strong>
                        <span><?php echo esc_html( $label ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mmc-panel" style="margin-bottom:16px">
                <form method="get">
                    <input type="hidden" name="page" value="mmc-snippets">
                    <select name="snippet_class">
                        <option value="">Tüm sınıflar</option>
                        <?php foreach ( array('production','test','temporary','legacy','review') as $cls ) : ?>
                            <option value="<?php echo esc_attr($cls); ?>" <?php selected($filter,$cls); ?>><?php echo esc_html(MMC_Snippet_Inventory_Service::class_label($cls)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="snippet_active">
                        <option value="">Aktif + Pasif</option>
                        <option value="active" <?php selected($active_filter,'active'); ?>>Yalnız aktif</option>
                        <option value="passive" <?php selected($active_filter,'passive'); ?>>Yalnız pasif</option>
                    </select>
                    <?php submit_button('Filtrele','secondary','',false); ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=snippets')); ?>">Code Snippets'i Aç</a>
                </form>
            </div>

            <div class="mmc-panel" style="overflow:auto">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th><th>Durum</th><th>Sınıf</th><th>Risk</th><th>Snippet</th><th>Kod İzi</th><th>Çakışma / Kanıt</th><th>Öneri</th><th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( ! $items ) : ?>
                        <tr><td colspan="9">Snippet kaydı bulunamadı.</td></tr>
                    <?php endif; ?>
                    <?php foreach ( $items as $item ) :
                        $risk_color = 'warning' === $item['risk'] ? '#996800' : ( 'critical' === $item['risk'] ? '#b32d2e' : '#16803a' );
                    ?>
                        <tr>
                            <td><?php echo (int)$item['id']; ?></td>
                            <td><strong><?php echo $item['active'] ? 'Aktif' : 'Pasif'; ?></strong></td>
                            <td><?php echo esc_html(MMC_Snippet_Inventory_Service::class_label($item['class'])); ?></td>
                            <td><strong style="color:<?php echo esc_attr($risk_color); ?>"><?php echo esc_html(strtoupper($item['risk'])); ?></strong>
                                <?php if ( $item['risk_reason'] ) : ?><br><small><?php echo esc_html($item['risk_reason']); ?></small><?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($item['name']); ?></strong>
                                <?php if ( $item['scope'] ) : ?><br><small>Scope: <?php echo esc_html($item['scope']); ?></small><?php endif; ?>
                            </td>
                            <td>
                                <small>Hash: <?php echo esc_html($item['code_hash']); ?></small><br>
                                <?php if ( $item['hooks'] ) : ?><small>Hook: <?php echo esc_html(implode(', ',array_slice($item['hooks'],0,5))); ?></small><br><?php endif; ?>
                                <?php if ( $item['functions'] ) : ?><small>Fn: <?php echo esc_html(implode(', ',array_slice($item['functions'],0,5))); ?></small><br><?php endif; ?>
                                <?php if ( $item['shortcodes'] ) : ?><small>Shortcode: <?php echo esc_html(implode(', ',array_slice($item['shortcodes'],0,4))); ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $item['conflicts'] ) : ?>
                                    <?php foreach ( $item['conflicts'] as $conflict ) : ?><div>⚠ <?php echo esc_html($conflict); ?></div><?php endforeach; ?>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($item['recommendation']); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page'=>'edit-snippet','id'=>(int)$item['id']),admin_url('admin.php'))); ?>">Kodu Aç</a>
                                <details style="margin-top:6px"><summary>Önizleme</summary><small><?php echo esc_html($item['code_preview']); ?></small></details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
