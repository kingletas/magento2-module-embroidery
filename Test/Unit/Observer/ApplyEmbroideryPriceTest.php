<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Observer;

use Commerce\Embroidery\Model\Charge\ChargeBreakdown;
use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Commerce\Embroidery\Model\Personalization\SelectionReader;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use Commerce\Embroidery\Observer\ApplyEmbroideryPrice;
use Commerce\Embroidery\Test\Support\CartLine;
use Commerce\Embroidery\Test\Support\PricedProduct;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ApplyEmbroideryPriceTest extends TestCase
{
    private const BASE_PRICE = 20.0;

    private LoggerInterface&MockObject $logger;
    private ?EmbroiderySelection $selection = null;
    private ChargeBreakdown $charges;
    private ?RuntimeException $calculatorFailure = null;
    private PricedProduct $product;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->calculatorFailure = null;
        $this->product = new PricedProduct(self::BASE_PRICE);
        $this->charges = new ChargeBreakdown(['text' => 6.0, 'logo' => 4.5]);
        $this->selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'A. Nurse'], 'block', 'T-100'),
        ]);
    }

    public function testTheSurchargeIsAddedToTheProductsOwnPrice(): void
    {
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertSame(self::BASE_PRICE + 10.5, (float) $line->getData('custom_price'));
    }

    /**
     * Both prices are set, or a later collector restores the pre-surcharge one.
     */
    public function testBothCustomPricesAreSetTogether(): void
    {
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertSame(
            (float) $line->getData('custom_price'),
            (float) $line->getData('original_custom_price')
        );
    }

    /**
     * Computed from the product each time; adding to the current price
     * compounds the surcharge.
     */
    public function testRecollectingTotalsDoesNotCompoundTheSurcharge(): void
    {
        $line = $this->line();
        $observer = $this->observer();

        $observer->execute($this->event($line));
        $observer->execute($this->event($line));
        $observer->execute($this->event($line));

        $this->assertSame(self::BASE_PRICE + 10.5, (float) $line->getData('custom_price'));
    }

    /**
     * Magento refuses a custom price on a line unless the product is in super
     * mode; without it the surcharge is silently discarded.
     */
    public function testTheProductIsPutIntoSuperModeSoTheCustomPriceIsAccepted(): void
    {
        $this->observer()->execute($this->event($this->line()));

        $this->assertTrue((bool) $this->product->getData('is_super_mode'));
    }

    public function testTheSurchargeAndItsBreakdownAreRecordedOnTheLine(): void
    {
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertSame(
            [OptionCodeInterface::SURCHARGE, OptionCodeInterface::PRICE_BREAKDOWN],
            array_column($line->addedOptions, 'code')
        );
        $this->assertSame('10.5', $line->addedOptions[0]['value']);
        $this->assertEquals(
            ['text' => 6.0, 'logo' => 4.5],
            (array) (new Json())->unserialize($line->addedOptions[1]['value'])
        );
    }

    /**
     * The price is a pure function of the line's stored option.
     */
    public function testTheSelectionIsReadFromTheLineRatherThanTheRequest(): void
    {
        $reader = $this->createMock(SelectionReader::class);
        $reader->expects($this->once())->method('fromQuoteItem')->willReturn($this->selection);

        $this->observer(reader: $reader)->execute($this->event($this->line()));
    }

    /**
     * Charges belong on the line the shopper sees.
     */
    public function testAChildOfAConfigurableIsLeftAlone(): void
    {
        $line = $this->line(hasParent: true);

        $this->observer()->execute($this->event($line));

        $this->assertNull($line->getData('custom_price'));
    }

    public function testALineWithoutEmbroideryIsLeftAlone(): void
    {
        $this->selection = null;
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertNull($line->getData('custom_price'));
        $this->assertSame([], $line->addedOptions);
    }

    public function testAnEmptySelectionIsLeftAlone(): void
    {
        $this->selection = new EmbroiderySelection([]);
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertNull($line->getData('custom_price'));
    }

    /**
     * A free personalisation sets no custom price, which would freeze the line
     * against price rules.
     */
    public function testAZeroSurchargeLeavesThePriceAlone(): void
    {
        $this->charges = new ChargeBreakdown([]);
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertNull($line->getData('custom_price'));
        $this->assertSame([], $line->addedOptions);
    }

    public function testNothingHappensWhenTheFeatureIsDisabled(): void
    {
        $line = $this->line();

        $this->observer(enabled: false)->execute($this->event($line));

        $this->assertNull($line->getData('custom_price'));
    }

    /**
     * The event carries whatever dispatched it; a missing item must not become
     * a type error inside a totals collection.
     */
    public function testAnEventWithoutAQuoteItemIsIgnored(): void
    {
        $this->logger->expects($this->never())->method('error');

        $observer = $this->observer();

        $observer->execute(new Observer(['event' => new Event([])]));
        $observer->execute(new Observer(['event' => new Event(['quote_item' => 'nope'])]));
    }

    /**
     * Totals are collected on every cart view.
     */
    public function testAPricingFailureIsContainedAndLogged(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('surcharge'));

        $this->calculatorFailure = new RuntimeException('no such config scope');
        $line = $this->line();

        $this->observer()->execute($this->event($line));

        $this->assertNull($line->getData('custom_price'));
    }

    private function event(mixed $item): Observer
    {
        return new Observer(['event' => new Event(['quote_item' => $item])]);
    }

    private function line(bool $hasParent = false): CartLine
    {
        $line = new CartLine($this->product, $hasParent ? new CartLine($this->product) : null);
        $line->setData('store_id', 1);
        $line->setData('qty', 1);
        $line->setData('item_id', 7);

        return $line;
    }

    private function observer(bool $enabled = true, ?SelectionReader $reader = null): ApplyEmbroideryPrice
    {
        if ($reader === null) {
            $reader = $this->createMock(SelectionReader::class);
            $reader->method('fromQuoteItem')->willReturnCallback(fn (): ?EmbroiderySelection => $this->selection);
        }

        $calculator = $this->createMock(ChargeCalculator::class);
        $calculator->method('calculate')->willReturnCallback(
            function (): ChargeBreakdown {
                if ($this->calculatorFailure !== null) {
                    throw $this->calculatorFailure;
                }

                return $this->charges;
            }
        );

        $config = new Config(
            $this->scopeConfig(['test_embroidery/general/enabled' => $enabled ? '1' : '0']),
            'test_embroidery'
        );

        return new ApplyEmbroideryPrice($reader, $calculator, new Json(), $config, $this->logger);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function scopeConfig(array $values): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => !in_array($values[$path] ?? null, [null, '', '0', 0, false], true)
        );

        return $scopeConfig;
    }
}
