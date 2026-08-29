<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Charge;

/**
 * The embroidery surcharge for one cart line, itemised.
 */
class ChargeBreakdown
{
    /** @var array<string, float> */
    public readonly array $components;

    public readonly float $total;

    /**
     * Rounding and zero-dropping happen here, so two breakdowns from the same
     * components are equal.
     *
     * @param array<string, float> $components Component key => amount.
     */
    public function __construct(array $components = [])
    {
        $this->components = array_filter(
            array_map(static fn ($amount): float => round((float) $amount, 4), $components),
            static fn (float $amount): bool => $amount !== 0.0
        );
        $this->total = round(array_sum($this->components), 4);
    }

    public function isZero(): bool
    {
        return $this->total === 0.0;
    }

    public function get(string $component): float
    {
        return $this->components[$component] ?? 0.0;
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return $this->components;
    }
}
