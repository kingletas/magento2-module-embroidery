<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\ViewModel;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\SelectionReader;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Renders an item's embroidery selections for display.
 */
class EmbroideryDetails implements ArgumentInterface
{
    public function __construct(
        private readonly SelectionReader $selectionReader,
        private readonly ThreadColorRepositoryInterface $threadColorRepository
    ) {
    }

    /**
     * @return array<int, array{side: string, rows: array<int, array{label: \Magento\Framework\Phrase, value: string}>}>
     */
    public function forQuoteItem(CartItemInterface $item): array
    {
        return $this->render($this->selectionReader->fromQuoteItem($item));
    }

    /**
     * @return array<int, array{side: string, rows: array<int, array{label: \Magento\Framework\Phrase, value: string}>}>
     */
    public function forOrderItem(OrderItemInterface $item): array
    {
        return $this->render($this->selectionReader->fromOrderItem($item));
    }

    /**
     * @return array<int, array{side: string, rows: array<int, array{label: \Magento\Framework\Phrase, value: string}>}>
     */
    private function render(?EmbroiderySelection $selection): array
    {
        if ($selection === null || $selection->isEmpty()) {
            return [];
        }

        $threadColors = $this->resolveThreadColors($selection);
        $blocks = [];

        foreach ($selection->all() as $side) {
            $blocks[] = [
                'side' => $side->side->label(),
                'rows' => $this->rowsFor($side, $threadColors),
            ];
        }

        return $blocks;
    }

    /**
     * @param array<string, ThreadColorInterface> $threadColors
     *
     * @return array<int, array{label: \Magento\Framework\Phrase, value: string}>
     */
    private function rowsFor(SideSelection $side, array $threadColors): array
    {
        $rows = [];

        foreach ($side->textLines as $lineNumber => $text) {
            $rows[] = ['label' => __('Line %1', $lineNumber), 'value' => $text];
        }

        if ($side->fontStyle !== null) {
            $rows[] = ['label' => __('Font'), 'value' => $side->fontStyle];
        }

        if ($side->threadColorCode !== null) {
            $threadColor = $threadColors[$side->threadColorCode] ?? null;
            $rows[] = [
                'label' => __('Thread colour'),
                // Fall back to the stored code when a colour has since been
                // deleted, rather than rendering an empty row on a historical
                // order.
                'value' => $threadColor?->getName() ?? $side->threadColorCode,
            ];
        }

        if ($side->hasLogo()) {
            $rows[] = ['label' => __('Logo'), 'value' => ucfirst($side->logoType)];

            if ($side->logoLocation !== null) {
                $rows[] = ['label' => __('Logo position'), 'value' => $side->logoLocation];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, ThreadColorInterface>
     */
    private function resolveThreadColors(EmbroiderySelection $selection): array
    {
        $codes = array_filter(array_map(
            static fn (SideSelection $side): ?string => $side->threadColorCode,
            $selection->all()
        ));

        return $codes === [] ? [] : $this->threadColorRepository->getByCodes($codes);
    }
}
