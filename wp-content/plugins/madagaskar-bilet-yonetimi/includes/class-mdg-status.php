<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Status {
    const DRAFT      = 'draft';
    const ONSALE     = 'onsale';
    const CLOSED     = 'closed';
    const SOLDOUT    = 'soldout';
    const POSTPONED  = 'postponed';
    const CANCELLED  = 'cancelled';
    const COMPLETED  = 'completed';

    public static function all() {
        return array(
            self::DRAFT     => 'Taslak',
            self::ONSALE    => 'Satışta',
            self::CLOSED    => 'Satış Kapalı',
            self::SOLDOUT   => 'Tükendi',
            self::POSTPONED => 'Ertelendi',
            self::CANCELLED => 'İptal Edildi',
            self::COMPLETED => 'Tamamlandı',
        );
    }

    public static function label( $status ) {
        $all = self::all();
        return isset( $all[ $status ] ) ? $all[ $status ] : $status;
    }
}
