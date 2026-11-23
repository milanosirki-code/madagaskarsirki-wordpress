<?php
/** Family-package style expansion for Süper 12. No WordPress or sales writes. */
declare(strict_types=1);

final class MS12_Family3_Selection {
    public const CODE = 'MDG_SUPER12';
    public const MAX_BUNDLES = 2;

    /** One standalone positive package selection; empty selections are ignored. */
    public static function quantity(string $raw): int {
        if (strlen($raw) > 12000 || !preg_match('/^\s*\[/', $raw)) {
            throw new InvalidArgumentException('Invalid selection.');
        }
        $rows = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        $quantity = 0;
        foreach ($rows as $line) {
            $code = $line['code'] ?? null;
            $qty = $line['qty'] ?? null;
            if (!is_array($line) || !is_string($code) || (!is_int($qty) && !is_string($qty))
                || preg_match('/\A[0-2]\z/D', (string)$qty) !== 1) {
                throw new InvalidArgumentException('Invalid ticket line.');
            }
            if ((int)$qty === 0) continue;
            if (strtoupper($code) !== self::CODE || $quantity !== 0) {
                throw new InvalidArgumentException('Package must be selected once, on its own.');
            }
            $quantity = (int)$qty;
        }
        if ($quantity === 0) throw new InvalidArgumentException('Package quantity is missing.');
        return $quantity;
    }

    /** Expand only an already-authorized canonical quote to native MDG cart lines. */
    public static function native_lines(array $quote): array {
        $quantity = $quote['count'] ?? null;
        $adult = $quote['adultCode'] ?? null;
        $child = $quote['childCode'] ?? null;
        if (!is_int($quantity) || $quantity < 1 || $quantity > self::MAX_BUNDLES
            || !self::is_native_code($adult) || !self::is_native_code($child) || $adult === $child) {
            throw new InvalidArgumentException('Invalid native event ticket mapping.');
        }
        return [
            ['code'=>$adult,'qty'=>$quantity * MS12_Rules::ADULTS],
            ['code'=>$child,'qty'=>$quantity * MS12_Rules::CHILDREN],
        ];
    }

    private static function is_native_code($code): bool {
        return is_string($code) && $code !== self::CODE && preg_match('/\A[A-Z0-9_]+\z/D', $code) === 1;
    }
}
