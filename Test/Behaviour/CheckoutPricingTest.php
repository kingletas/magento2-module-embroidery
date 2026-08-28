<?php
/**
 * CheckoutPricingTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Behaviour;

use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Commerce\Embroidery\Model\Personalization\SelectionMapper;
use Commerce\Embroidery\Model\Personalization\SelectionReader;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use Commerce\Embroidery\Observer\ApplyEmbroideryPrice;
use Commerce\Embroidery\Observer\FlagOrderWithEmbroidery;
use Commerce\Embroidery\Test\Behaviour\Fake\PersonalisedCartLine;
use Commerce\Embroidery\Test\Unit\Fake\PricedProduct;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A personalised garment, from the cart to the order.
 */
class CheckoutPricingTest extends TestCase
{
    private const SECTION = 'commerce_embroidery';
    private const STORE = 1;

    private LoggerInterface $logger;

    /** @var array<string, string> */
    private array $settings = [];

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->settings = [
            self::SECTION . '/general/enabled' => '1',
            self::SECTION . '/charges/text_line_1_price' => '2.50',
            self::SECTION . '/charges/text_line_2_price' => '2.00',
            self::SECTION . '/charges/stock_logo_price' => '5.00',
            self::SECTION . '/charges/custom_logo_price' => '8.00',
            self::SECTION . '/charges/custom_logo_fee' => '15.00',
        ];
    }

    public function testAPersonalisedLineIsPricedAtTheGarmentPlusItsEmbroidery(): void
    {
        $line = $this->lineOf(20.00, $this->nameOnTheLeftChest());

        $this->collectTotals($line);

        // 20.00 garment + 2.50 first line of text.
        $this->assertSame(22.50, (float) $line->getCustomPrice());
        $this->assertSame(22.50, (float) $line->getOriginalCustomPrice());
    }

    /**
     * A checkout collects totals several times - adding the item, applying a
     * coupon, estimating shipping, reaching payment.
     */
    public function testCollectingTotalsRepeatedlyDoesNotCompoundTheSurcharge(): void
    {
        $line = $this->lineOf(20.00, $this->nameOnTheLeftChest());

        $this->collectTotals($line);
        $this->collectTotals($line);
        $this->collectTotals($line);
        $this->collectTotals($line);

        $this->assertSame(22.50, (float) $line->getCustomPrice());
    }

    /**
     * Nothing in the pricing path reads a request, so a cron prices a line as
     * the shopper would.
     */
    public function testALaterCollectionWithNoRequestStillChargesForTheEmbroidery(): void
    {
        $line = $this->lineOf(20.00, $this->nameOnTheLeftChest());

        // The request that added the item.
        $this->collectTotals($line);

        // Some time later: a coupon, a shipping estimate, a cron.
        $line->setCustomPrice(null);
        $line->setOriginalCustomPrice(null);
        $this->collectTotals($line);

        $this->assertSame(22.50, (float) $line->getCustomPrice());
    }

    /**
     * No pricing state is held on the observer, so one cart line cannot be
     * charged for another's.
     */
    public function testTwoLinesInOneCartDoNotBleedIntoEachOther(): void
    {
        $expensive = $this->lineOf(20.00, $this->bothChestsWithACustomLogo());
        $plain = $this->lineOf(30.00, $this->nameOnTheLeftChest());

        $observer = $this->observer();
        $this->collectTotals($expensive, $observer);
        $this->collectTotals($plain, $observer);

        // 30.00 garment + 2.50 for one line of text, and nothing of the other
        // line's logo work.
        $this->assertSame(32.50, (float) $plain->getCustomPrice());
    }

    /**
     * Most of a cart is not personalised.
     */
    public function testAPlainGarmentIsNotGivenACustomPriceAtAll(): void
    {
        $line = $this->lineOf(30.00, null);

        $this->collectTotals($line);

        $this->assertNull($line->getCustomPrice());
    }

    /**
     * Both the parent line and its child are collected.
     */
    public function testTheChildOfAConfigurableIsNotChargedTwice(): void
    {
        $parent = $this->lineOf(20.00, $this->nameOnTheLeftChest());
        $child = new PersonalisedCartLine(new PricedProduct(20.00), $parent);
        $child->withOption(OptionCodeInterface::OPTIONS, $this->encode($this->nameOnTheLeftChest()));
        $child->setStoreId(self::STORE);

        $this->collectTotals($child);

        $this->assertNull($child->getCustomPrice());
    }

    /**
     * The breakdown is what the admin, the packing slip and the ERP export all
     * read.
     */
    public function testTheLineRecordsWhatItWasChargedAndWhy(): void
    {
        $line = $this->lineOf(20.00, $this->bothChestsWithACustomLogo());

        $this->collectTotals($line);

        $surcharge = $line->getOptionByCode(OptionCodeInterface::SURCHARGE);
        $breakdown = $line->getOptionByCode(OptionCodeInterface::PRICE_BREAKDOWN);

        $this->assertNotNull($surcharge);
        $this->assertNotNull($breakdown);

        $components = (array) (new Json())->unserialize((string) $breakdown->getValue());

        // Left: one line of text.
        $this->assertSame(2.50, (float) $components['left_text']);
        $this->assertSame(8.00, (float) $components['right_custom_logo']);
        $this->assertSame(15.00, (float) $components['custom_logo_fee']);
        $this->assertSame(25.50, (float) $surcharge->getValue());
    }

    /**
     * It covers preparing the artwork, which happens once.
     */
    public function testTheDigitisingFeeIsChargedOncePerLineRatherThanPerSide(): void
    {
        $line = $this->lineOf(0.00, new EmbroiderySelection([
            new SideSelection(Side::Left, [], logoType: SideSelection::LOGO_CUSTOM),
            new SideSelection(Side::Right, [], logoType: SideSelection::LOGO_CUSTOM),
        ]));

        $this->collectTotals($line);

        // Two custom logos at 8.00, and one 15.00 fee.
        $this->assertSame(31.00, (float) $line->getCustomPrice());
    }

    public function testWithTheModuleSwitchedOffNoLineIsRepriced(): void
    {
        $this->settings[self::SECTION . '/general/enabled'] = '0';
        $line = $this->lineOf(20.00, $this->nameOnTheLeftChest());

        $this->collectTotals($line);

        $this->assertNull($line->getCustomPrice());
    }

    /**
     * A payload that will not decode is a historical order's problem, not this
     * shopper's.
     */
    public function testACorruptSelectionDoesNotEmptyTheCart(): void
    {
        $line = new PersonalisedCartLine(new PricedProduct(20.00));
        $line->withOption(OptionCodeInterface::OPTIONS, '{not json at all');
        $line->setStoreId(self::STORE);

        $this->collectTotals($line);

        $this->assertNull($line->getCustomPrice());
    }

    /**
     * An embroidered order goes to a different bench.
     */
    public function testAnOrderContainingEmbroideryIsFlagged(): void
    {
        $order = $this->orderWithItems([
            ['sku' => 'PLAIN-TOP', 'options' => []],
            ['sku' => 'LOGO-POLO', 'options' => [OptionCodeInterface::OPTIONS => ['left' => []]]],
        ]);

        $this->placeOrder($order);

        $this->assertSame(1, $order->getData(FlagOrderWithEmbroidery::ATTRIBUTE));
    }

    /**
     * An unset flag and a false flag read the same to PHP and differently to a
     * report filtering on the column.
     */
    public function testAnOrderWithNoEmbroideryIsFlaggedAsSuch(): void
    {
        $order = $this->orderWithItems([['sku' => 'PLAIN-TOP', 'options' => []]]);

        $this->placeOrder($order);

        $this->assertSame(0, $order->getData(FlagOrderWithEmbroidery::ATTRIBUTE));
    }

    private function collectTotals(PersonalisedCartLine $line, ?ApplyEmbroideryPrice $observer = null): void
    {
        $observer ??= $this->observer();

        $event = new Observer();
        $event->setEvent(new Event(['quote_item' => $line]));

        $observer->execute($event);
    }

    private function placeOrder(OrderInterface $order): void
    {
        $event = new Observer();
        $event->setEvent(new Event(['order' => $order]));

        (new FlagOrderWithEmbroidery())->execute($event);
    }

    private function observer(): ApplyEmbroideryPrice
    {
        $serializer = new Json();
        $config = new Config($this->scopeConfig($this->settings), self::SECTION);

        return new ApplyEmbroideryPrice(
            new SelectionReader($serializer, new SelectionMapper(), $this->logger),
            new ChargeCalculator($config),
            $serializer,
            $config,
            $this->logger
        );
    }

    private function lineOf(float $price, ?EmbroiderySelection $selection): PersonalisedCartLine
    {
        $line = new PersonalisedCartLine(new PricedProduct($price));
        $line->setStoreId(self::STORE);

        if ($selection !== null) {
            $line->withOption(OptionCodeInterface::OPTIONS, $this->encode($selection));
        }

        return $line;
    }

    private function encode(EmbroiderySelection $selection): string
    {
        return (new Json())->serialize($selection->toArray());
    }

    private function nameOnTheLeftChest(): EmbroiderySelection
    {
        return new EmbroiderySelection([new SideSelection(Side::Left, ['1' => 'Ada Lovelace'])]);
    }

    private function bothChestsWithACustomLogo(): EmbroiderySelection
    {
        return new EmbroiderySelection([
            new SideSelection(Side::Left, ['1' => 'Ada Lovelace']),
            new SideSelection(Side::Right, [], logoType: SideSelection::LOGO_CUSTOM),
        ]);
    }

    /**
     * @param array<int, array{sku: string, options: array<string, mixed>}> $items
     */
    private function orderWithItems(array $items): OrderInterface
    {
        $orderItems = [];

        foreach ($items as $item) {
            // A real order item, because `getProductOptions()` is on the model
            // rather than the interface.
            $orderItem = $this->createPartialMock(\Magento\Sales\Model\Order\Item::class, []);
            $orderItem->setSku($item['sku']);
            $orderItem->setProductOptions($item['options']);
            $orderItems[] = $orderItem;
        }

        $order = $this->createPartialMock(\Magento\Sales\Model\Order::class, ['getItems']);
        $order->method('getItems')->willReturn($orderItems);

        return $order;
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
