<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

final class MS12_Catalog {
    /** Owner-approved extra tariff scope: these exact existing 26 sessions only. */
    private const APPROVED = [
        52 => [283,284], 53 => [285,286], 54 => [287,288], 55 => [289,290],
        56 => [291,292], 57 => [293,294], 58 => [297,298], 59 => [309,310],
        60 => [311,312], 61 => [313,314,315], 62 => [316,317,318], 63 => [319,320],
    ];

    public static function approved_online_tariff(int $eid, int $sid): bool {
        return in_array($sid, self::APPROVED[$eid] ?? [], true);
    }

    public static function lab_sessions(): array {
        if (!defined('MS12_LAB_SESSIONS') || !is_array(MS12_LAB_SESSIONS)) { return []; }
        return array_values(array_unique(array_filter(MS12_LAB_SESSIONS, static function ($value): bool {
            return is_int($value) && $value > 0;
        })));
    }

    private static function rows(?int $sid = null): array {
        global $wpdb;
        $events = MDG_DB::table('events');
        $sessions = MDG_DB::table('sessions');
        $query = "SELECT e.id event_id,e.title,e.public_slug,s.id session_id,s.start_at,s.wc_product_id,s.tickera_event_id
            FROM {$events} e JOIN {$sessions} s ON s.event_id=e.id
            WHERE e.status='onsale' AND s.status='onsale' AND s.start_at > UTC_TIMESTAMP()
                AND (e.sale_start IS NULL OR e.sale_start <= UTC_TIMESTAMP())
                AND (e.sale_end IS NULL OR e.sale_end > UTC_TIMESTAMP())
                AND (s.sale_start IS NULL OR s.sale_start <= UTC_TIMESTAMP())
                AND (s.sale_end IS NULL OR s.sale_end > UTC_TIMESTAMP())
                AND e.public_slug IS NOT NULL AND e.public_slug <> ''";
        if ($sid !== null) { $query .= $wpdb->prepare(' AND s.id=%d', $sid); }
        return $wpdb->get_results($query . ' ORDER BY s.start_at,s.id LIMIT 500') ?: [];
    }

    public static function upcoming(): array { return self::rows(); }
    public static function session(int $sid): ?object { return self::rows($sid)[0] ?? null; }

    private static function minor(string $money): ?int {
        if (!preg_match('/\A([0-9]{1,7})(?:\.([0-9]{1,2}))?\z/', $money, $m)) { return null; }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    public static function types(object $session, ?string &$blocker = null): ?array {
        global $wpdb;
        $table = MDG_DB::table('ticket_types');
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT code,price,capacity_units,wc_variation_id FROM {$table} WHERE session_id=%d AND is_active=1",
            (int) $session->session_id
        ));
        if (!$records) { $blocker = 'Seansın aktif MDG bilet türleri bulunamadı.'; return null; }
        if ((int) $session->wc_product_id <= 0) { $blocker = 'Seansın WooCommerce ana ürün bağlantısı eksik.'; return null; }
        if ((int) $session->tickera_event_id <= 0) { $blocker = 'Seansın Tickera etkinlik bağlantısı eksik.'; return null; }
        $types = [];
        foreach ($records as $record) {
            $code = strtoupper((string) $record->code);
            $role = ['ADULT' => 'adult','YETISKIN' => 'adult','Y' => 'adult','CHILD' => 'child','COCUK' => 'child','C' => 'child'][$code] ?? null;
            if ($role === null) { continue; }
            $id = (int) $record->wc_variation_id;
            $label = $role === 'adult' ? 'Yetişkin' : 'Çocuk';
            if (isset($types[$role])) { $blocker = $label . ' bilet türü birden fazla kez eşleşiyor.'; return null; }
            if ((int) $record->capacity_units !== 1) { $blocker = $label . ' bileti bir kişilik kapasiteyle eşleşmiyor.'; return null; }
            if ($id <= 0) { $blocker = $label . ' biletinin WooCommerce varyasyon bağlantısı eksik.'; return null; }
            $product = wc_get_product($id);
            $mdg_price = self::minor((string) $record->price);
            if (!$product || !$product->is_type('variation')) { $blocker = $label . ' WooCommerce varyasyonu bulunamadı.'; return null; }
            if ((int) $product->get_parent_id() !== (int) $session->wc_product_id) { $blocker = $label . ' varyasyonu seansın ana ürününe bağlı değil.'; return null; }
            if ($product->get_status() !== 'publish') { $blocker = $label . ' WooCommerce varyasyonu yayında değil.'; return null; }
            if ($mdg_price === null || $mdg_price <= 0) { $blocker = $label . ' biletinin MDG fiyatı geçersiz.'; return null; }
            if (self::minor((string) $product->get_price()) !== $mdg_price) { $blocker = $label . ' biletinin MDG ve WooCommerce fiyatları uyuşmuyor.'; return null; }
            $types[$role] = [$code, $id, (string) $record->price];
        }
        if (!isset($types['adult'], $types['child'])) { $blocker = 'Yetişkin ve çocuk bilet türlerinin ikisi de eşleşmeli.'; return null; }
        if ($types['adult'][1] === $types['child'][1]) { $blocker = 'Yetişkin ve çocuk için aynı WooCommerce varyasyonu tanımlı.'; return null; }
        return [
            'adultCode' => $types['adult'][0], 'adultVariationId' => $types['adult'][1], 'adultPrice' => $types['adult'][2],
            'childCode' => $types['child'][0], 'childVariationId' => $types['child'][1], 'childPrice' => $types['child'][2],
        ];
    }

    public static function checkout_quote(int $eid, int $sid): ?array {
        if (MS12_Plugin::lab_enabled()) { return self::lab_quote($eid, $sid); }
        return MS12_Pilot::available($eid, $sid) ? self::quote($eid, $sid, false) : null;
    }

    /** Read-only; does not depend on or change the session's deployment option. */
    public static function deployment_readiness(int $eid, int $sid): bool {
        return self::deployment_blocker($eid, $sid) === null;
    }

    /** First failed existing technical gate; null means technically ready, not authorized. */
    public static function deployment_blocker(int $eid, int $sid): ?string {
        if (!MS12_Pilot::dependencies()) return 'Canlı ortam, ödeme şablonu veya zorunlu eklenti/para birimi/vergi ayarları doğrulanamadı.';
        if (!class_exists('MDG_Capacity')) return 'MDG seans kapasitesi okunamıyor.';
        if (MDG_Capacity::available($sid) < MS12_Rules::PEOPLE) return 'Seansın boş kapasitesi 12 kişinin altında.';
        $blocker = null;
        $quote = self::quote($eid, $sid, false, $blocker);
        if (!$quote) return $blocker ?? 'Seansın Süper 12 satış eşleşmesi doğrulanamadı.';
        foreach (['adultVariationId','childVariationId'] as $key) {
            $variation = wc_get_product($quote[$key]);
            $label = $key === 'adultVariationId' ? 'Yetişkin' : 'Çocuk';
            if (!$variation) return $label . ' WooCommerce varyasyonu okunamıyor.';
            if (!$variation->is_virtual()) return $label . ' WooCommerce varyasyonu sanal ürün olarak tanımlı değil.';
            if (!$variation->is_in_stock()) return $label . ' WooCommerce varyasyonu stokta değil.';
            if (!$variation->is_purchasable()) return $label . ' WooCommerce varyasyonu satın alınabilir değil.';
        }
        // Do not use the pre-opening capacity check in checkout/retry: an order can
        // already own the last hold. Native MDG reserves paid inventory atomically.
        return null;
    }

    public static function lab_quote(int $eid, int $sid): ?array {
        if (!MS12_Plugin::lab_enabled() || !in_array($sid, self::lab_sessions(), true)) { return null; }
        return self::quote($eid, $sid, true);
    }

    private static function quote(int $eid, int $sid, bool $lab, ?string &$blocker = null): ?array {
        if (get_woocommerce_currency() !== 'TRY') { $blocker = 'WooCommerce para birimi TRY değil.'; return null; }
        $row = self::session($sid);
        if (!$row) { $blocker = 'Seans veya etkinlik gelecekte ve açık satış penceresinde bulunamadı.'; return null; }
        if ((int) $row->event_id !== $eid) { $blocker = 'Seans beklenen etkinliğe bağlı değil.'; return null; }
        if (!class_exists('MMC_MDG_Bridge_Service') || !class_exists('MMC_Program_Service')) { $blocker = 'MMC program eşleştirme servisi kullanılamıyor.'; return null; }
        $pid = MMC_MDG_Bridge_Service::program_for_mdg_event($eid);
        $program = $pid ? MMC_Program_Service::get_program($pid) : null;
        if (!$program) { $blocker = 'Etkinliğe bağlı MMC programı bulunamadı.'; return null; }
        if ($program->brand !== 'Madagaskar Sirki') { $blocker = 'MMC programı Madagaskar Sirki markasına bağlı değil.'; return null; }
        // 0.4.2: Satış sinyali MDG etkinlik/seans 'onsale' durumudur (aile paketleriyle aynı).
        // MMC program aşaması (ör. sales_prep) Süper 12'yi ayrıca engellemez; yalnız iptal/kapalı programlar engellenir.
        if (in_array($program->status, ['cancelled','closed','archived'], true)) { $blocker = 'MMC programı iptal veya kapalı.'; return null; }
        $types = self::types($row, $blocker);
        if (!$types) { return null; }
        // 0.4.0: Sabit profil listesi ve Antalya'ya özel ek kanıt şartı kaldırıldı. Süper 12,
        // aile paketleri gibi canlı MDG/Woo eşleşmesiyle (types) doğrulanır.
        $approved = self::approved_online_tariff($eid, $sid)
            || ($lab && defined('MS12_LAB_APPROVED_ALT_SESSIONS') && is_array(MS12_LAB_APPROVED_ALT_SESSIONS)
                && in_array($sid, MS12_LAB_APPROVED_ALT_SESSIONS, true));
        try { $price = MS12_Rules::price_cents($types['adultPrice'], $approved); }
        catch (InvalidArgumentException $e) { $blocker = 'Bilet tarifesi Süper 12 fiyat kuralına uygun değil.'; return null; }
        return $types + ['priceCents' => $price, 'maxBundles' => 2, 'approvedAltTariff' => $approved];
    }
}
