<?php
/**
 * PricingCostTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Performance;

use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use Commerce\Foundation\Test\Support\BudgetAssertions;
use Commerce\Foundation\Test\Support\CountingScopeConfig;
use PHPUnit\Framework\TestCase;

/**
 * What pricing a cart costs in configuration reads.
 */
final class PricingCostTest extends TestCase
{
    use BudgetAssertions;

    private const SECTION = 'commerce_embroidery';
    private const STORE = 1;

    public function testPricingACartDoesNotReadConfigurationPerLine(): void
    {
        self::assertConstantCost(
            'config reads while pricing a cart',
            function (int $lines): int {
                [$calculator, $scopeConfig] = $this->calculator();

                for ($line = 0; $line < $lines; $line++) {
                    $calculator->calculate($this->bothChests(), self::STORE);
                }

                return $scopeConfig->reads();
            },
            [1, 50]
        );
    }

    /**
     * Each line of text has its own configured rate, so a read per line is what
     * is pinned here.
     */
    public function testPricingDoesNotReadConfigurationPerLineOfText(): void
    {
        [$calculator, $scopeConfig] = $this->calculator();

        $calculator->calculate($this->bothChests(), self::STORE);
        $firstPass = $scopeConfig->reads();

        $calculator->calculate($this->bothChests(), self::STORE);

        self::assertCostAtMost(
            'pricing a second identical line',
            $firstPass,
            $scopeConfig->reads(),
            $scopeConfig->summary()
        );
    }

    /**
     * Everything that makes the cost constant is a form of remembering, and
     * remembering goes wrong by remembering too widely.
     */
    public function testEachStoreIsPricedWithItsOwnRates(): void
    {
        [$calculator, $scopeConfig] = $this->calculator([
            self::SECTION . '/charges/text_line_1_price|2' => '9.00',
        ]);

        $one = $calculator->calculate($this->oneLineOfText(), 1);
        $two = $calculator->calculate($this->oneLineOfText(), 2);

        self::assertSame(2.5, $one->total, $scopeConfig->summary());
        self::assertSame(9.0, $two->total, $scopeConfig->summary());
    }

    /**
     * @param  array<string, mixed> $extraValues
     * @return array{0: ChargeCalculator, 1: CountingScopeConfig}
     */
    private function calculator(array $extraValues = []): array
    {
        $scopeConfig = new CountingScopeConfig($extraValues + [
            self::SECTION . '/charges/text_line_1_price' => '2.50',
            self::SECTION . '/charges/text_line_2_price' => '2.00',
            self::SECTION . '/charges/text_line_3_price' => '1.50',
            self::SECTION . '/charges/stock_logo_price' => '5.00',
            self::SECTION . '/charges/custom_logo_price' => '8.00',
            self::SECTION . '/charges/custom_logo_fee' => '15.00',
        ]);

        return [new ChargeCalculator(new Config($scopeConfig, self::SECTION)), $scopeConfig];
    }

    private function bothChests(): EmbroiderySelection
    {
        return new EmbroiderySelection([
            new SideSelection(Side::Left, ['1' => 'Ada Lovelace', '2' => 'RN'], logoType: SideSelection::LOGO_STOCK),
            new SideSelection(Side::Right, ['1' => 'Ward 4', '2' => 'Nights'], logoType: SideSelection::LOGO_CUSTOM),
        ]);
    }

    private function oneLineOfText(): EmbroiderySelection
    {
        return new EmbroiderySelection([new SideSelection(Side::Left, ['1' => 'Ada Lovelace'])]);
    }
}
