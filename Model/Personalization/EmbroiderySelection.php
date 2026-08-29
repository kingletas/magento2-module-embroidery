<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

use InvalidArgumentException;

/**
 * Everything a shopper chose for one cart line.
 */
class EmbroiderySelection
{
    /** @var array<string, SideSelection> Keyed by side value. */
    private readonly array $sides;

    /**
     * Indexing and blank-dropping happen here, so no caller can hold two
     * entries for one side.
     *
     * @param SideSelection[] $selections
     */
    public function __construct(array $selections = [])
    {
        $sides = [];

        foreach ($selections as $selection) {
            if (!$selection instanceof SideSelection) {
                throw new InvalidArgumentException('Expected a SideSelection.');
            }

            // Drop sides the shopper left blank rather than carrying empty
            // structures all the way to the order and the ERP export.
            if (!$selection->isEmpty()) {
                $sides[$selection->side->value] = $selection;
            }
        }

        $this->sides = $sides;
    }

    public function isEmpty(): bool
    {
        return $this->sides === [];
    }

    public function get(Side $side): ?SideSelection
    {
        return $this->sides[$side->value] ?? null;
    }

    /**
     * @return SideSelection[]
     */
    public function all(): array
    {
        return array_values($this->sides);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (SideSelection $selection): array => $selection->toArray(),
            $this->sides
        );
    }
}
