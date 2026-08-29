<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Api;

use Commerce\Embroidery\Model\Charge\ChargeBreakdown;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;

/**
 * Renders an embroidery selection into whatever shape a downstream system
 * wants.
 */
interface OrderExportMapperInterface
{
    /**
     * @return array<string, mixed> The payload for one order line.
     */
    public function map(EmbroiderySelection $selection, ChargeBreakdown $charges): array;
}
