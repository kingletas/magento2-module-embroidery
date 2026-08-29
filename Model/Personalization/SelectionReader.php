<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads and writes embroidery selections on quote and order items.
 */
class SelectionReader
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly SelectionMapper $selectionMapper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether a quote item carries embroidery.
     */
    public function isEmbroidered(CartItemInterface $item): bool
    {
        return $item->getOptionByCode(OptionCodeInterface::OPTIONS) !== null;
    }

    public function fromQuoteItem(CartItemInterface $item): ?EmbroiderySelection
    {
        $option = $item->getOptionByCode(OptionCodeInterface::OPTIONS);

        return $option === null ? null : $this->decode((string) $option->getValue());
    }

    public function fromOrderItem(OrderItemInterface $item): ?EmbroiderySelection
    {
        $options = $item->getProductOptions();

        if (!is_array($options) || !isset($options[OptionCodeInterface::OPTIONS])) {
            return null;
        }

        $value = $options[OptionCodeInterface::OPTIONS];

        return is_array($value)
            ? $this->selectionMapper->selectionFromArray($value)
            : $this->decode((string) $value);
    }

    public function encode(EmbroiderySelection $selection): string
    {
        return $this->serializer->serialize($selection->toArray());
    }

    /**
     * Decode a stored payload, tolerating corruption.
     */
    private function decode(string $payload): ?EmbroiderySelection
    {
        if (trim($payload) === '') {
            return null;
        }

        try {
            $decoded = $this->serializer->unserialize($payload);
        } catch (Throwable $e) {
            $this->logger->warning(
                'Embroidery: could not decode a stored selection.',
                ['exception' => $e]
            );

            return null;
        }

        return is_array($decoded) ? $this->selectionMapper->selectionFromArray($decoded) : null;
    }
}
