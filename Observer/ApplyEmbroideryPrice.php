<?php
/**
 * ApplyEmbroideryPrice.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Observer;

use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Commerce\Embroidery\Model\Personalization\SelectionReader;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Adds the embroidery surcharge to a cart line's price.
 */
class ApplyEmbroideryPrice implements ObserverInterface
{
    public function __construct(
        private readonly SelectionReader $selectionReader,
        private readonly ChargeCalculator $chargeCalculator,
        private readonly SerializerInterface $serializer,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getData('quote_item');

        if (!$item instanceof QuoteItem) {
            return;
        }

        // Charges belong on the line the shopper sees, not on each child of a
        // configurable; applying them to both double-counts.
        if ($item->getParentItem() !== null) {
            return;
        }

        if (!$this->config->isEnabled((int) $item->getStoreId())) {
            return;
        }

        try {
            $this->apply($item);
        } catch (Throwable $e) {
            // A pricing failure must not empty the shopper's cart.
            $this->logger->error(
                'Embroidery: failed to apply the surcharge to a cart line.',
                ['exception' => $e, 'item_id' => $item->getId()]
            );
        }
    }

    private function apply(QuoteItem $item): void
    {
        $selection = $this->selectionReader->fromQuoteItem($item);

        if ($selection === null || $selection->isEmpty()) {
            return;
        }

        $charges = $this->chargeCalculator->calculate($selection, (int) $item->getStoreId());

        if ($charges->isZero()) {
            return;
        }

        // Computed from the product each time; adding to the item's current
        // price compounds the surcharge.
        $basePrice = (float) $item->getProduct()->getFinalPrice($item->getQty());

        $item->setCustomPrice($basePrice + $charges->total);
        $item->setOriginalCustomPrice($basePrice + $charges->total);
        $item->getProduct()->setIsSuperMode(true);

        $this->recordBreakdown($item, $charges->total, $charges->toArray());
    }

    /**
     * @param array<string, float> $breakdown
     */
    private function recordBreakdown(QuoteItem $item, float $total, array $breakdown): void
    {
        $item->addOption([
            'code' => OptionCodeInterface::SURCHARGE,
            'value' => (string) $total,
        ]);
        $item->addOption([
            'code' => OptionCodeInterface::PRICE_BREAKDOWN,
            'value' => $this->serializer->serialize($breakdown),
        ]);
    }
}
