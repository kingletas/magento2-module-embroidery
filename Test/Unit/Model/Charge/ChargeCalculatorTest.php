<?php
/**
 * ChargeCalculatorTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Charge;

use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChargeCalculatorTest extends TestCase
{
    private Config&MockObject $config;
    private ChargeCalculator $calculator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getTextLinePrice')->willReturnMap([
            [1, null, 5.00],
            [2, null, 4.00],
            [3, null, 3.00],
        ]);
        $this->config->method('getStockLogoPrice')->willReturn(8.00);
        $this->config->method('getCustomLogoPrice')->willReturn(12.00);
        $this->config->method('getCustomLogoFee')->willReturn(25.00);

        $this->calculator = new ChargeCalculator($this->config);
    }

    public function testAnEmptySelectionCostsNothing(): void
    {
        $charges = $this->calculator->calculate(new EmbroiderySelection([]));

        $this->assertSame(0.0, $charges->total);
        $this->assertTrue($charges->isZero());
    }

    public function testEachTextLineIsPricedAtItsOwnRate(): void
    {
        $charges = $this->calculator->calculate(new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'Jane Doe', 2 => 'RN']),
        ]));

        $this->assertSame(9.00, $charges->total);
    }

    /**
     * Pricing text lines by position in a compacted array charges a shopper who
     * filled in lines 1 and 3 at the line 1 and line 2 rates.
     */
    public function testLinesArePricedByLineNumberNotByPosition(): void
    {
        $charges = $this->calculator->calculate(new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'Jane Doe', 3 => 'Cardiology']),
        ]));

        $this->assertSame(8.00, $charges->total, 'Should be line 1 (5.00) + line 3 (3.00), not line 2.');
    }

    public function testStockAndCustomLogosArePricedDifferently(): void
    {
        $stock = $this->calculator->calculate(new EmbroiderySelection([
            new SideSelection(Side::Left, [], null, null, SideSelection::LOGO_STOCK),
        ]));
        $this->assertSame(8.00, $stock->total);

        $custom = $this->calculator->calculate(new EmbroiderySelection([
            new SideSelection(Side::Left, [], null, null, SideSelection::LOGO_CUSTOM),
        ]));
        $this->assertSame(37.00, $custom->total, 'Custom logo 12.00 plus the 25.00 setup fee.');
    }

    /**
     * The setup fee covers digitising the artwork, which happens once.
     */
    public function testTheCustomLogoSetupFeeIsChargedOncePerLineNotPerSide(): void
    {
        $charges = $this->calculator->calculate(new EmbroiderySelection([
            new SideSelection(Side::Left, [], null, null, SideSelection::LOGO_CUSTOM),
            new SideSelection(Side::Right, [], null, null, SideSelection::LOGO_CUSTOM),
        ]));

        // 12.00 + 12.00 for the two logos, plus one 25.00 fee.
        $this->assertSame(49.00, $charges->total);
        $this->assertSame(25.00, $charges->get(ChargeCalculator::COMPONENT_CUSTOM_LOGO_FEE));
    }

    public function testBothSidesAreChargedIndependently(): void
    {
        $charges = $this->calculator->calculate(new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'Jane']),
            new SideSelection(Side::Right, [1 => 'Doe']),
        ]));

        $this->assertSame(10.00, $charges->total);
        $this->assertSame(5.00, $charges->get('left_' . ChargeCalculator::COMPONENT_TEXT));
        $this->assertSame(5.00, $charges->get('right_' . ChargeCalculator::COMPONENT_TEXT));
    }

    /**
     * A blank side never reaches the calculator, so it contributes no empty
     * components.
     */
    public function testBlankSidesAreDiscarded(): void
    {
        $selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'Jane']),
            new SideSelection(Side::Right),
        ]);

        $this->assertCount(1, $selection->all());
        $this->assertNull($selection->get(Side::Right));
    }
}
