<?php
/**
 * Plugin Name: Madagaskar V5 Finans Güncellemesi
 * Description: V5.7.4 finans ekranında web gelirini tahsilat tarihi yerine program tarihine göre raporlar ve sabit gider türlerini genişletir.
 * Version: 1.2.0
 * Author: Dünya Organizasyon
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MDG_V5_Finans_Guncellemesi {
	private const VERSION = '1.2.0';
	private const TARGET_RELATIVE = 'madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php';
	private const OPTION_STATUS = 'mdg_v5_finans_guncellemesi_status';

	public static function boot(): void {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_patch' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'fixed_cost_types' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'overview_summary_fix' ) );
	}

	public static function activate(): void {
		self::apply_patch();
	}

	public static function ensure_patch(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = (array) get_option( self::OPTION_STATUS, array() );
		if ( empty( $status['ok'] ) || empty( $status['target_hash'] ) ) {
			self::apply_patch();
			return;
		}

		$path = WP_PLUGIN_DIR . '/' . self::TARGET_RELATIVE;
		if ( is_readable( $path ) && hash_file( 'sha256', $path ) !== $status['target_hash'] ) {
			self::apply_patch();
		}
	}

	private static function replacement_function(): string {
		return <<<'PHP'
private static function web_revenues($from,$to){
        global$wpdb;
        if(!class_exists('MDG_DB'))return array();

        $month=substr((string)$from,0,7);
        $month_events=self::month_events(self::events(),$month);
        $event_ids=array_values(array_filter(array_map('absint',array_keys($month_events))));
        if(!$event_ids)return array();

        $paid_statuses=array('processing','completed','paid','wc-processing','wc-completed','wc-paid');
        if(function_exists('wc_get_is_paid_statuses')){
            foreach((array)wc_get_is_paid_statuses()as$status){
                $status=sanitize_key($status);
                if($status){$paid_statuses[]=$status;$paid_statuses[]='wc-'.$status;}
            }
        }
        $paid_statuses=array_values(array_unique($paid_statuses));

        $map=MDG_DB::table('order_map');
        $event_placeholders=implode(',',array_fill(0,count($event_ids),'%d'));
        $status_placeholders=implode(',',array_fill(0,count($paid_statuses),'%s'));
        $sql="SELECT event_id,COALESCE(SUM(line_total),0) revenue FROM {$map} WHERE event_id IN ({$event_placeholders}) AND order_status IN ({$status_placeholders}) AND paid_at IS NOT NULL AND paid_at<>'0000-00-00 00:00:00' GROUP BY event_id";
        $prepared=$wpdb->prepare($sql,array_merge($event_ids,$paid_statuses));

        $out=array();
        foreach((array)$wpdb->get_results($prepared)as$r)$out[(int)$r->event_id]=(float)$r->revenue;
        return$out;
    }
PHP;
	}

	private static function apply_patch(): void {
		$path = WP_PLUGIN_DIR . '/' . self::TARGET_RELATIVE;
		$status = array(
			'ok'      => false,
			'message' => '',
			'time'    => current_time( 'mysql' ),
		);

		if ( ! is_readable( $path ) || ! is_writable( $path ) ) {
			$status['message'] = 'V5 finans dosyası okunamıyor veya yazılamıyor.';
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		$source = file_get_contents( $path );
		if ( false === $source ) {
			$status['message'] = 'V5 finans dosyası okunamadı.';
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		if ( false !== strpos( $source, '$month_events=self::month_events(self::events(),$month);' ) ) {
			$status['ok']          = true;
			$status['message']     = 'Program tarihine göre gelir düzeltmesi zaten etkin.';
			$status['target_hash'] = hash( 'sha256', $source );
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		$pattern = '~private static function web_revenues\(\$from,\$to\)\{.*?\}\n\s*private static function month_events~s';
		if ( 1 !== preg_match( $pattern, $source ) ) {
			$status['message'] = 'Beklenen V5.7.4 gelir fonksiyonu bulunamadı; güvenlik nedeniyle dosya değiştirilmedi.';
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		$backup = $path . '.backup-before-program-revenue-' . gmdate( 'Ymd-His' );
		if ( ! copy( $path, $backup ) ) {
			$status['message'] = 'Yedek alınamadığı için güncelleme uygulanmadı.';
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		$replacement = self::replacement_function() . "\n    private static function month_events";
		$updated = preg_replace( $pattern, $replacement, $source, 1, $count );
		if ( 1 !== $count || ! is_string( $updated ) || $updated === $source ) {
			$status['message'] = 'Gelir fonksiyonu güvenli biçimde değiştirilemedi.';
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		$tmp = $path . '.mdg-finance-tmp';
		if ( false === file_put_contents( $tmp, $updated, LOCK_EX ) || ! rename( $tmp, $path ) ) {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			copy( $backup, $path );
			$status['message'] = 'Dosya yazılamadı; alınan yedek geri yüklendi.';
			update_option( self::OPTION_STATUS, $status, false );
			return;
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true );
		}

		$status['ok']          = true;
		$status['message']     = 'Web geliri artık program tarihine göre ilgili aya ve programa yazılıyor.';
		$status['backup']      = basename( $backup );
		$status['target_hash'] = hash_file( 'sha256', $path );
		update_option( self::OPTION_STATUS, $status, false );
	}

	public static function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = (array) get_option( self::OPTION_STATUS, array() );
		if ( empty( $status ) ) {
			return;
		}

		$class = ! empty( $status['ok'] ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>Madagaskar V5 Finans Güncellemesi:</strong> ' . esc_html( (string) ( $status['message'] ?? '' ) ) . '</p></div>';
	}

	public static function fixed_cost_types( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview';
		if ( 'mdg-v5-finance' !== $page || 'fixed' !== $section ) {
			return;
		}

		$types = array(
			'telephone'   => 'Telefon',
			'internet'    => 'İnternet',
			'natural_gas' => 'Doğalgaz',
			'electricity' => 'Elektrik',
			'rent'        => 'Kira',
			'dues'        => 'Aidat',
		);

		wp_register_script( 'mdg-v5-finance-update', '', array(), self::VERSION, true );
		wp_enqueue_script( 'mdg-v5-finance-update' );

		$script = <<<'JS'
(function () {
    'use strict';
    var types = %s;
    function add(select) {
        if (!select) return;
        Object.keys(types).forEach(function (key) {
            if (select.querySelector('option[value="' + key + '"]')) return;
            var option = document.createElement('option');
            option.value = key;
            option.textContent = types[key];
            select.appendChild(option);
        });
    }
    function init() {
        var action = document.querySelector('input[name="action"][value="mdg_v5_save_fixed_cost"]');
        var form = action ? action.closest('form') : null;
        var type = form ? form.querySelector('select[name="cost_type"]') : null;
        var name = form ? form.querySelector('input[name="name"]') : null;
        add(type);
        add(document.querySelector('form.mdgv5-filter select[name="filter"]'));
        if (type && name) type.addEventListener('change', function () {
            var oldAuto = name.dataset.mdgAutoName || '';
            var label = types[type.value] || '';
            if (label && (!name.value.trim() || name.value === oldAuto)) {
                name.value = label;
                name.dataset.mdgAutoName = label;
            }
        });
        document.querySelectorAll('.mdgv5-card table .mdgv5-sub').forEach(function (node) {
            var text = node.textContent.trim();
            Object.keys(types).forEach(function (key) {
                if (text.indexOf(key + ' /') === 0) node.textContent = types[key] + text.slice(key.length);
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
JS;

		wp_add_inline_script( 'mdg-v5-finance-update', sprintf( $script, wp_json_encode( $types, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}

	/**
	 * The V5.7.4 overview renders its headline web total from the legacy
	 * payment-date query even though the programme table uses web_revenues().
	 * Keep the headline and net-result cards on the same source of truth as the
	 * visible programme rows.
	 */
	public static function overview_summary_fix( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview';
		if ( 'mdg-v5-finance' !== $page || 'overview' !== $section ) {
			return;
		}

		wp_register_script( 'mdg-v5-overview-summary-fix', '', array(), self::VERSION, true );
		wp_enqueue_script( 'mdg-v5-overview-summary-fix' );
		wp_add_inline_script(
			'mdg-v5-overview-summary-fix',
			<<<'JS'
(function () {
    'use strict';

    function parseMoney(text) {
        var cleaned = String(text || '').replace(/[^0-9,.-]/g, '').replace(/\./g, '').replace(',', '.');
        var value = Number(cleaned);
        return Number.isFinite(value) ? value : 0;
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency', currency: 'TRY', minimumFractionDigits: 2, maximumFractionDigits: 2
        }).format(value).replace('TRY', '₺');
    }

    function findCard(label) {
        var labelNode = Array.prototype.find.call(document.querySelectorAll('body *'), function (node) {
            return node.children.length === 0 && node.textContent.trim() === label;
        }) || null;
        if (!labelNode) return null;
        var card = labelNode.parentElement;
        while (card && card !== document.body) {
            if (moneyNode(card)) return card;
            card = card.parentElement;
        }
        return null;
    }

    function moneyNode(card) {
        if (!card) return null;
        return Array.prototype.find.call(card.querySelectorAll('*'), function (node) {
            return node.children.length === 0 && /-?\s*₺\s*[\d.]+,\d{2}/.test(node.textContent.trim());
        }) || null;
    }

    function programmeWebTotal() {
        var tables = document.querySelectorAll('.mdgv5-card table');
        for (var t = 0; t < tables.length; t++) {
            var headers = tables[t].querySelectorAll('thead th');
            var webIndex = -1;
            for (var h = 0; h < headers.length; h++) {
                if (headers[h].textContent.trim() === 'Web') webIndex = h;
            }
            if (webIndex < 0) continue;

            var total = 0;
            var rows = tables[t].querySelectorAll('tbody tr');
            for (var r = 0; r < rows.length; r++) {
                var cells = rows[r].querySelectorAll('td');
                if (cells[webIndex]) total += parseMoney(cells[webIndex].textContent);
            }
            return total;
        }
        return null;
    }

    function update() {
        var web = programmeWebTotal();
        if (web === null) return;

        var webNode = moneyNode(findCard('Web sitesi geliri'));
        var otherNode = moneyNode(findCard('Diğer tahsil edilmiş gelir'));
        var expenseNode = moneyNode(findCard('Toplam gider'));
        var netNode = moneyNode(findCard('İşletme net sonucu'));

        if (webNode) webNode.textContent = formatMoney(web);
        if (netNode && expenseNode) {
            var other = otherNode ? parseMoney(otherNode.textContent) : 0;
            var expense = parseMoney(expenseNode.textContent);
            netNode.textContent = formatMoney(web + other - expense);
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', update); else update();
}());
JS
		);
	}
}

MDG_V5_Finans_Guncellemesi::boot();
