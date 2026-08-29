<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Charge;

use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;

/**
 * Prices an embroidery selection.
 */
class ChargeCalculator
{
    public const string COMPONENT_TEXT = 'text';
    public const string COMPONENT_STOCK_LOGO = 'stock_logo';
    public const string COMPONENT_CUSTOM_LOGO = 'custom_logo';
    public const string COMPONENT_CUSTOM_LOGO_FEE = 'custom_logo_fee';

    /**
     * Prices already read, keyed by store id and config path.
     *
     * @var array<string, float>
     */
    private array $prices = [];

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function calculate(EmbroiderySelection $selection, ?int $storeId = null): ChargeBreakdown
    {
        if ($selection->isEmpty()) {
            return new ChargeBreakdown();
        }

        $components = [];
        // The custom-logo setup fee is charged once per line, not once per
        // side: it covers digitising the artwork, which happens once.
        $customLogoFeeCharged = false;

        foreach ($selection->all() as $sideSelection) {
            $prefix = $sideSelection->side->value . '_';

            $textCharge = $this->priceText($sideSelection, $storeId);

            if ($textCharge > 0.0) {
                $components[$prefix . self::COMPONENT_TEXT] = $textCharge;
            }

            match ($sideSelection->logoType) {
                SideSelection::LOGO_STOCK => $components[$prefix . self::COMPONENT_STOCK_LOGO] = $this->price(
                    'stock_logo',
                    $storeId,
                    fn (): float => $this->config->getStockLogoPrice($storeId)
                ),
                SideSelection::LOGO_CUSTOM => $components[$prefix . self::COMPONENT_CUSTOM_LOGO] = $this->price(
                    'custom_logo',
                    $storeId,
                    fn (): float => $this->config->getCustomLogoPrice($storeId)
                ),
                default => null,
            };

            if ($sideSelection->logoType === SideSelection::LOGO_CUSTOM && !$customLogoFeeCharged) {
                $components[self::COMPONENT_CUSTOM_LOGO_FEE] = $this->price(
                    'custom_logo_fee',
                    $storeId,
                    fn (): float => $this->config->getCustomLogoFee($storeId)
                );
                $customLogoFeeCharged = true;
            }
        }

        return new ChargeBreakdown($components);
    }

    /**
     * Price one side's text.
     */
    private function priceText(SideSelection $selection, ?int $storeId): float
    {
        $total = 0.0;

        foreach (array_keys($selection->textLines) as $lineNumber) {
            $line = (int) $lineNumber;
            $total += $this->price(
                'text_line_' . $line,
                $storeId,
                fn (): float => $this->config->getTextLinePrice($line, $storeId)
            );
        }

        return $total;
    }

    /**
     * One number from the store's price table, read at most once per store.
     *
     * @param callable(): float $read
     */
    private function price(string $key, ?int $storeId, callable $read): float
    {
        if ($storeId === null) {
            return $read();
        }

        return $this->prices[$storeId . '/' . $key] ??= $read();
    }

    public function calculateForSide(SideSelection $selection, ?int $storeId = null): ChargeBreakdown
    {
        return $this->calculate(new EmbroiderySelection([$selection]), $storeId);
    }
}
