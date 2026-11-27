<?php
defined( 'ABSPATH' ) || exit;

final class MDG_Mikro_Draft {
    public static function date( $format, $timestamp ) {
        return wp_date( $format, $timestamp, new DateTimeZone( 'Europe/Istanbul' ) );
    }

    // Woo stores the province as TR06 and the district in billing_city.
    private static function province( $state ) {
        $states = WC()->countries->get_states( 'TR' );
        // Names from the Mikro reference worksheet in Turkish plate-code order.
        $names = explode( '|', 'ADANA|ADIYAMAN|AFYON|AĞRI|AMASYA|ANKARA|ANTALYA|ARTVİN|AYDIN|BALIKESİR|BİLECİK|BİNGÖL|BİTLİS|BOLU|BURDUR|BURSA|ÇANAKKALE|ÇANKIRI|ÇORUM|DENİZLİ|DİYARBAKIR|EDİRNE|ELAZIĞ|ERZİNCAN|ERZURUM|ESKİŞEHİR|GAZİANTEP|GİRESUN|GÜMÜŞHANE|HAKKARİ|HATAY|ISPARTA|MERSİN|İSTANBUL|İZMİR|KARS|KASTAMONU|KAYSERİ|KIRKLARELİ|KIRŞEHİR|KOCAELİ|KONYA|KÜTAHYA|MALATYA|MANİSA|K.MARAŞ|MARDİN|MUĞLA|MUŞ|NEVŞEHİR|NİĞDE|ORDU|RİZE|SAKARYA|SAMSUN|SİİRT|SİNOP|SİVAS|TEKİRDAĞ|TOKAT|TRABZON|TUNCELİ|ŞANLIURFA|UŞAK|VAN|YOZGAT|ZONGULDAK|AKSARAY|BAYBURT|KARAMAN|KIRIKKALE|BATMAN|ŞIRNAK|BARTIN|ARDAHAN|IĞDIR|YALOVA|KARABÜK|KİLİS|OSMANİYE|DÜZCE' );
        foreach ( $states as $code => $name ) {
            if ( $code === $state || sanitize_title( $name ) === sanitize_title( $state ) ) {
                $index = (int) substr( $code, 2 ) - 1;
                return $names[ $index ] ?? '';
            }
        }
        return '';
    }

    private static function tckn( $value ) {
        if ( '' === $value || '11111111111' === $value ) {
            return '11111111111';
        }
        if ( ! preg_match( '/^[1-9][0-9]{10}$/D', $value ) ) {
            throw new RuntimeException( 'TCKN biçimini kontrol edin.' );
        }
        $d = array_map( 'intval', str_split( $value ) );
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        if ( ( ( $odd * 7 - $even ) % 10 + 10 ) % 10 !== $d[9] ||
             array_sum( array_slice( $d, 0, 10 ) ) % 10 !== $d[10] ) {
            throw new RuntimeException( 'Girilen TCKN doğrulanamadı.' );
        }
        return $value;
    }

    public static function build( $order, $cutoff ) {
        if ( ! $order || ! $cutoff || ! $order->get_date_created() ||
             $order->get_date_created()->getTimestamp() <= $cutoff ) {
            throw new RuntimeException( 'Kurulum öncesi sipariş.' );
        }
        if ( ! $order->is_paid() || ! $order->get_date_paid() || $order->get_date_paid()->getTimestamp() <= $cutoff ||
             ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ||
             $order->get_refunds() ) {
            throw new RuntimeException( 'Ödeme, iade veya sipariş durumu kontrol gerektiriyor.' );
        }
        if ( 'issued' === $order->get_meta( '_mdg_invoice_status' ) || $order->get_meta( '_mdg_mikro_document' ) ||
             in_array( $order->get_meta( '_dof_invoice_status' ), array( 'invoiced', 'sent' ), true ) ) {
            throw new RuntimeException( 'Bu sipariş için belge kaydedilmiş.' );
        }
        $parasut = (array) get_option( 'mps_parasut_settings', array() );
        if ( ( class_exists( 'MPS_Parasut' ) && ! empty( $parasut['enabled'] ) ) ||
             $order->get_meta( '_mps_parasut_invoice_id' ) || $order->get_meta( '_mps_parasut_edoc_id' ) ||
             $order->get_meta( '_mps_parasut_state' ) || $order->get_meta( '_mps_parasut_job_id' ) ||
             ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action(
                 'mps_parasut_process_order', array( (int) $order->get_id() ), 'mps-parasut' ) ) ) {
            throw new RuntimeException( 'Paraşüt otomasyonu veya bu siparişe ait Paraşüt işlemi var. Belge durumunu kontrol edin.' );
        }
        if ( ! in_array( $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ||
             'paytr_payment_gateway' !== $order->get_payment_method() || 'TRY' !== $order->get_currency() ) {
            throw new RuntimeException( 'Satış kanalı, ödeme yöntemi veya para birimi elle kontrol edilmeli.' );
        }
        if ( 'individual' !== $order->get_meta( '_mdg_invoice_type' ) || $order->get_billing_company() ||
             $order->get_meta( '_mdg_invoice_tax_number' ) || $order->get_meta( '_mdg_invoice_company_title' ) ) {
            throw new RuntimeException( 'Alıcı türünü ve e-Fatura kaydını kontrol edin.' );
        }
        $first = trim( $order->get_billing_first_name() );
        $last = trim( $order->get_billing_last_name() );
        $district = trim( $order->get_billing_city() );
        $province = self::province( $order->get_billing_state() );
        if ( ! $first || ! $last || 'TR' !== $order->get_billing_country() || ! $province ||
             ! preg_match( '/^\p{L}[\p{L} .\x{2019}\x{0027}-]*$/uD', $district ) ||
             ! is_email( $order->get_billing_email() ) ) {
            throw new RuntimeException( 'Alıcının adını, soyadını, Türkiye fatura il/ilçesini ve e-postasını kontrol edin.' );
        }
        $customer = array(
            'M' => self::tckn( trim( (string) $order->get_meta( '_mdg_invoice_tckn' ) ) ),
            'O' => $first, 'P' => $last, 'Q' => 'TÜRKİYE', 'R' => $province, 'S' => $district,
            'V' => $order->get_billing_email(),
        );
        if ( $order->get_items( 'fee' ) || $order->get_items( 'shipping' ) ) {
            throw new RuntimeException( 'Bilet dışı ücret veya teslimat satırı var.' );
        }
        $lines = array();
        $gross_sum = 0;
        $tax_sum = 0;
        foreach ( $order->get_items() as $item ) {
            $qty = (int) $item->get_quantity();
            $ticket = get_post_meta( $item->get_product_id(), '_tc_is_ticket', true );
            if ( 'yes' !== $ticket || $qty < 1 || (float) $qty !== (float) $item->get_quantity() ) {
                throw new RuntimeException( 'Bilet ürünü veya miktarı doğrulanamadı.' );
            }
            // Woo line totals already include coupon reductions. Tax may be embedded in the sales price.
            $gross = (int) round( 100 * ( (float) $item->get_total() + (float) $item->get_total_tax() ) );
            $tax = (int) round( 100 * (float) $item->get_total_tax() );
            $net = (int) round( $gross / 1.10 );
            $vat = (int) round( $net * 0.10 );
            if ( $gross <= 0 || $gross !== $net + $vat || ( $tax && $vat !== $tax ) ) {
                throw new RuntimeException( 'Kalem tutarı ürün kartındaki %10 KDV ile kuruşu kuruşuna eşleşmiyor.' );
            }
            $details = $item->get_name();
            if ( $item->get_variation_id() && $item->get_product() ) {
                $details .= ' ' . wp_strip_all_tags( wc_get_formatted_variation( $item->get_product(), true, true ) );
            }
            $lines[] = array( 'C' => 'madagaskar', 'D' => 'Madagaskar sirki bilet ücreti', 'E' => $qty,
                'F' => 'ADET', 'G' => round( $net / ( 100 * $qty ), 8 ), 'H' => 10,
                'O' => trim( $details ) . ' | Sipariş #' . $order->get_id() );
            $gross_sum += $gross;
            $tax_sum += $tax;
        }
        if ( ! $lines || $gross_sum !== (int) round( 100 * (float) $order->get_total() ) ||
             $tax_sum !== (int) round( 100 * (float) $order->get_total_tax() ) ) {
            throw new RuntimeException( 'Bilet toplamı sipariş ile eşleşmiyor.' );
        }
        return array( 'customer' => $customer, 'lines' => $lines, 'paid' => $order->get_date_paid()->getTimestamp(),
            'gross' => $gross_sum );
    }
}
