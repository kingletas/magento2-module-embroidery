<?php
/**
 * CartLine.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Support;

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * One line of a cart.
 *
 * @SuppressWarnings(PHPMD.MissingConstructor)
 */
class CartLine extends QuoteItem
{
    /**
     * Untyped because `AbstractModel` declares it untyped, and redeclaring an
     * untyped parent property with a type is a fatal.
     *
     * @var array<string, mixed>
     */
    protected $_data = [];

    /** @var array<int, array<string, mixed>> */
    public array $addedOptions = [];

    public function __construct(
        private readonly Product $product,
        private readonly ?QuoteItem $parentItem = null
    ) {
    }

    public function getProduct()
    {
        return $this->product;
    }

    public function getParentItem()
    {
        return $this->parentItem;
    }

    public function addOption($option)
    {
        $this->addedOptions[] = $option;

        return $this;
    }
}
