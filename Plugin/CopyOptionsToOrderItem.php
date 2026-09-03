<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Plugin;

use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item as OrderItem;

/**
 * Copies embroidery options from the quote item onto the order item.
 */
class CopyOptionsToOrderItem
{
    /**
     * @param array<string, mixed> $additionalOptions
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     *
     * phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function afterConvert(
        ToOrderItem $subject,
        OrderItemInterface $orderItem,
        CartItemInterface $item,
        $additionalOptions = []
    ): OrderItemInterface {
        // convert() may be handed an address item, and a third party may
        // return another OrderItemInterface; neither carries these options.
        if (!$orderItem instanceof OrderItem || !$item instanceof QuoteItem) {
            return $orderItem;
        }

        $productOptions = $orderItem->getProductOptions();

        if (!is_array($productOptions)) {
            $productOptions = [];
        }

        $copied = false;

        foreach (OptionCodeInterface::ALL as $code) {
            $option = $item->getOptionByCode($code);

            if ($option === null) {
                continue;
            }

            $productOptions[$code] = $option->getValue();
            $copied = true;
        }

        if ($copied) {
            $orderItem->setProductOptions($productOptions);
        }

        return $orderItem;
    }
}
