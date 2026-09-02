<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Observer;

use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;

/**
 * Records on the order whether any of its lines carries embroidery.
 */
class FlagOrderWithEmbroidery implements ObserverInterface
{
    public const string ATTRIBUTE = 'has_embroidery';

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');

        if (!$order instanceof Order) {
            return;
        }

        $order->setData(self::ATTRIBUTE, $this->hasEmbroideredItem($order) ? 1 : 0);
    }

    private function hasEmbroideredItem(Order $order): bool
    {
        foreach ($order->getItems() ?? [] as $item) {
            if (!$item instanceof OrderItem) {
                continue;
            }

            $options = $item->getProductOptions();

            if (is_array($options) && !empty($options[OptionCodeInterface::OPTIONS])) {
                return true;
            }
        }

        return false;
    }
}
