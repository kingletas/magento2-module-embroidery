<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

/**
 * The product-option codes embroidery data travels under.
 */
interface OptionCodeInterface
{
    /**
     * The shopper's selections, as JSON.
     */
    public const string OPTIONS = 'embroidery_options';

    /**
     * Total embroidery surcharge for the line.
     */
    public const string SURCHARGE = 'embroidery_surcharge';

    /**
     * Per-component price breakdown, as JSON.
     */
    public const string PRICE_BREAKDOWN = 'embroidery_price_breakdown';

    /**
     * Every code this module writes, in the order they should be copied to the
     * order item.
     *
     * @var string[]
     */
    public const array ALL = [self::OPTIONS, self::SURCHARGE, self::PRICE_BREAKDOWN];
}
