<?php
/**
 * Plugin Name: Madagaskar V5 Sabit Gider Türleri
 * Description: Madagaskar Yönetim Merkezi V5 aylık sabit gider ekranına Telefon, İnternet, Doğalgaz, Elektrik, Kira ve Aidat türlerini ekler.
 * Version: 1.0.0
 * Author: Dünya Organizasyon
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MDG_V5_Sabit_Gider_Turleri {
	private const PAGE = 'mdg-v5-finance';

	public static function boot(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
	}

	public static function dependency_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || class_exists( 'MDG_Yonetim_Merkezi_V5' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>Madagaskar V5 Sabit Gider Türleri:</strong> Madagaskar Yönetim Merkezi V5 etkin değil. Ek seçenekler, V5 etkinleştirildiğinde kullanılabilir.</p></div>';
	}

	public static function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE !== $hook && 'madagaskar_page_' . self::PAGE !== $hook ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( self::PAGE !== $page ) {
				return;
			}
		}

		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview';
		if ( 'fixed' !== $section ) {
			return;
		}

		wp_register_script( 'mdg-v5-fixed-cost-types', '', array(), '1.0.0', true );
		wp_enqueue_script( 'mdg-v5-fixed-cost-types' );

		$types = array(
			'telephone'   => 'Telefon',
			'internet'    => 'İnternet',
			'natural_gas' => 'Doğalgaz',
			'electricity' => 'Elektrik',
			'rent'        => 'Kira',
			'dues'        => 'Aidat',
		);

		$script = <<<'JS'
(function () {
    'use strict';

    var types = %s;

    function appendOptions(select) {
        if (!select) return;

        Object.keys(types).forEach(function (value) {
            if (select.querySelector('option[value="' + value + '"]')) return;
            var option = document.createElement('option');
            option.value = value;
            option.textContent = types[value];
            select.appendChild(option);
        });
    }

    function init() {
        var formType = document.querySelector('form input[name="action"][value="mdg_v5_save_fixed_cost"]');
        var form = formType ? formType.closest('form') : null;
        var typeSelect = form ? form.querySelector('select[name="cost_type"]') : null;
        var nameInput = form ? form.querySelector('input[name="name"]') : null;

        appendOptions(typeSelect);

        if (typeSelect && nameInput) {
            typeSelect.addEventListener('change', function () {
                var previousAutoName = nameInput.dataset.mdgAutoName || '';
                var selectedName = types[typeSelect.value] || '';

                if (selectedName && (!nameInput.value.trim() || nameInput.value === previousAutoName)) {
                    nameInput.value = selectedName;
                    nameInput.dataset.mdgAutoName = selectedName;
                }
            });
        }

        var filter = document.querySelector('form.mdgv5-filter select[name="filter"]');
        appendOptions(filter);

        document.querySelectorAll('.mdgv5-card table .mdgv5-sub').forEach(function (node) {
            var text = node.textContent.trim();
            Object.keys(types).forEach(function (key) {
                if (text.indexOf(key + ' /') === 0) {
                    node.textContent = types[key] + text.slice(key.length);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
JS;

		wp_add_inline_script(
			'mdg-v5-fixed-cost-types',
			sprintf( $script, wp_json_encode( $types, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
		);
	}
}

MDG_V5_Sabit_Gider_Turleri::boot();

