<?php
/**
 * PricedProduct.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Support;

use Magento\Catalog\Model\Product;

/**
 * The product behind a cart line, priced.
 *
 * @SuppressWarnings(PHPMD.MissingConstructor)
 */
class PricedProduct extends Product
{
    /**
     * Untyped because `AbstractModel` declares it untyped, and redeclaring an
     * untyped parent property with a type is a fatal.
     *
     * @var array<string, mixed>
     */
    protected $_data = [];

    public function __construct(private readonly float $finalPrice = 0.0)
    {
    }

    public function getFinalPrice($qty = null)
    {
        return $this->finalPrice;
    }
}
