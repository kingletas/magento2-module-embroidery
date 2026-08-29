<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

/**
 * Which chest an embroidery applies to.
 */
enum Side: string
{
    case Left = 'left';
    case Right = 'right';


    public function label(): string
    {
        return ucfirst($this->value);
    }
}
