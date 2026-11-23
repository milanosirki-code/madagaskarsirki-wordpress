<?php
/** Pure Süper 12 rules. Money inputs are decimal TRY strings or whole-lira integers. */
final class MS12_Rules
{
    const ADULTS = 4;
    const CHILDREN = 8;
    const PEOPLE = 12;

    /**
     * Only pass true when the caller has verified that the session is approved for online tariffs.
     * Floats, signs, exponent notation, thousands separators and extra precision are rejected.
     */
    public static function price_cents($adult_price, $approved_online_tariff = false)
    {
        if (!is_bool($approved_online_tariff)) {
            throw new InvalidArgumentException('Online tariff approval must be a boolean.');
        }
        if (is_int($adult_price)) {
            $adult_price = (string) $adult_price;
        }
        $allowed_tariffs = $approved_online_tariff ? '490|500|580|600' : '500|600';
        if (!is_string($adult_price) || !preg_match('/\A(' . $allowed_tariffs . ')(?:[.,]0{1,2})?\z/D', $adult_price, $match)) {
            throw new InvalidArgumentException($approved_online_tariff
                ? 'Adult price must be exactly 490, 500, 580 or 600 TRY.'
                : 'Adult price must be exactly 500 or 600 TRY.');
        }
        return ($match[1] === '500' || $match[1] === '490') ? 225000 : 250000;
    }

    /** A mapping names two different existing product IDs; existence is checked by the adapter. */
    public static function validate_mapping($mapping)
    {
        if (!is_array($mapping) || count($mapping) !== 2
            || !array_key_exists('adult', $mapping) || !array_key_exists('child', $mapping)) {
            throw new InvalidArgumentException('Mapping requires exactly adult and child product IDs.');
        }
        self::positive_integer($mapping['adult'], 'adult product ID');
        self::positive_integer($mapping['child'], 'child product ID');
        if ($mapping['adult'] === $mapping['child']) {
            throw new InvalidArgumentException('Adult and child products must differ.');
        }
        return $mapping;
    }

    /** Equal allocation, remainder cents assigned from index zero; no float arithmetic. */
    public static function allocate_cents($total_cents, $people)
    {
        self::positive_integer($total_cents, 'total cents');
        self::positive_integer($people, 'people');
        // Bound allocations to prevent accidental unbounded memory consumption.
        if ($people > 120000) {
            throw new InvalidArgumentException('Too many people.');
        }
        $base = intdiv($total_cents, $people);
        $remainder = $total_cents % $people;
        $result = array_fill(0, $people, $base);
        for ($i = 0; $i < $remainder; ++$i) {
            ++$result[$i];
        }
        return $result;
    }

    /**
     * Expand into individually priced tickets, adults first in each bundle.
     * Each bundle conserves its own total, so adding bundles never reprices another bundle.
     * product_id is included only when a validated mapping is supplied.
     */
    public static function expand($adult_price, $bundle_quantity, $mapping = null, $approved_online_tariff = false)
    {
        $bundle_cents = self::price_cents($adult_price, $approved_online_tariff);
        self::positive_integer($bundle_quantity, 'bundle quantity');
        if ($bundle_quantity > 10000 || $bundle_quantity > intdiv(PHP_INT_MAX, $bundle_cents)) {
            throw new InvalidArgumentException('Bundle quantity exceeds the safe limit.');
        }
        if ($mapping !== null) {
            $mapping = self::validate_mapping($mapping);
        }
        $unit_cents = self::allocate_cents($bundle_cents, self::PEOPLE);
        $tickets = array();
        for ($bundle = 0; $bundle < $bundle_quantity; ++$bundle) {
            foreach ($unit_cents as $index => $cents) {
                $kind = $index < self::ADULTS ? 'adult' : 'child';
                $ticket = array('bundle_index' => $bundle, 'kind' => $kind, 'unit_cents' => $cents);
                if ($mapping !== null) {
                    $ticket['product_id'] = $mapping[$kind];
                }
                $tickets[] = $ticket;
            }
        }
        return array(
            'bundle_quantity' => $bundle_quantity,
            'bundle_cents' => $bundle_cents,
            'adults' => self::ADULTS * $bundle_quantity,
            'children' => self::CHILDREN * $bundle_quantity,
            'people' => self::PEOPLE * $bundle_quantity,
            'total_cents' => $bundle_cents * $bundle_quantity,
            'tickets' => $tickets,
        );
    }

    private static function positive_integer($value, $name)
    {
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException($name . ' must be a positive integer.');
        }
    }
}
