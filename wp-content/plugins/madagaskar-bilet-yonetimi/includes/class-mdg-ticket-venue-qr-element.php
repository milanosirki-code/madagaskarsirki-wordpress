<?php
namespace Tickera\Ticket\Element;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Madagaskar Salon Konumu QR
 *
 * Tickera 3.6 modern Ticket Designer, ADD-ON FIELDS listesini Tickera'nın
 * klasik template-element registry'sinden de besliyor. Bridge ve mevcut
 * "Ticket Type (Custom)" eklentisinin kullandığı kayıt deseninin aynısıdır.
 */
if ( ! class_exists( '\\Tickera\\TC_Ticket_Template_Elements' ) ) { return; }

if ( ! class_exists( __NAMESPACE__ . '\\tc_mdg_venue_qr_element', false ) ) {
    class tc_mdg_venue_qr_element extends \Tickera\TC_Ticket_Template_Elements {
        public $element_name = 'tc_mdg_venue_qr_element';
        public $element_title = 'Madagaskar Salon Konumu QR';
        public $font_awesome_icon = '<i class="fa fa-map-marker"></i>';

        public function on_creation() {
            $this->element_title = apply_filters(
                'mdg_tc_venue_qr_element_title',
                __( 'Madagaskar Salon Konumu QR', 'madagaskar-bilet' )
            );
        }

        public function admin_content() {
            if ( method_exists( get_parent_class( $this ), 'get_cell_alignment' ) ) {
                echo parent::get_cell_alignment(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            $width = isset( $this->template_metas[ $this->element_name . '_width' ] )
                ? absint( $this->template_metas[ $this->element_name . '_width' ] )
                : 145;

            echo '<label>' . esc_html__( 'QR genişliği (px)', 'madagaskar-bilet' ) . '</label>';
            echo '<input class="ticket_element_padding" type="number" min="80" max="320" name="' . esc_attr( $this->element_name ) . '_width_post_meta" value="' . esc_attr( $width ) . '">';

            if ( method_exists( get_parent_class( $this ), 'get_element_margins' ) ) {
                echo parent::get_element_margins(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }

            echo '<p style="font-size:12px;opacity:.75">' . esc_html__( 'QR hedefi Madagaskar → Salonlar kaydındaki Maps bağlantısından otomatik gelir.', 'madagaskar-bilet' ) . '</p>';
        }

        public function ticket_content( $ticket_instance_id = false, $ticket_type_id = false ) {
            $width = isset( $this->template_metas[ $this->element_name . '_width' ] )
                ? absint( $this->template_metas[ $this->element_name . '_width' ] )
                : 145;

            if ( ! class_exists( '\\MDG_Ticket_Venue_QR' ) ) {
                return '';
            }

            return \MDG_Ticket_Venue_QR::ticket_element_html( absint( $ticket_instance_id ), $width );
        }
    }
}


// Tickera'nın Bridge ve mevcut Ticket Type (Custom) eklentilerinde kullandığı
// kayıt deseni: element dosyası yüklendiği anda namespaced registry'ye eklenir.
if ( function_exists( '\\Tickera\\tickera_register_template_element' ) ) {
    \Tickera\tickera_register_template_element(
        __NAMESPACE__ . '\\tc_mdg_venue_qr_element',
        __( 'Madagaskar Salon Konumu QR', 'madagaskar-bilet' )
    );
}
