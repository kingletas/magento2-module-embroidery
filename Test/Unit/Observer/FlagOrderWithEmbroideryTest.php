<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Observer;

use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Commerce\Embroidery\Observer\FlagOrderWithEmbroidery;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FlagOrderWithEmbroideryTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $written = [];

    public function testAnOrderWithAnEmbroideredLineIsFlagged(): void
    {
        $this->execute($this->order([
            $this->item([]),
            $this->item([OptionCodeInterface::OPTIONS => '{"sides":[]}']),
        ]));

        $this->assertSame(1, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    /**
     * Stored as 0 rather than left unset: the grid sync copies the column
     * across, and a NULL is not something an admin grid filter can match on.
     */
    public function testAnOrderWithoutEmbroideryIsExplicitlyFlaggedZero(): void
    {
        $this->execute($this->order([$this->item([]), $this->item([])]));

        $this->assertSame(0, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    public function testAnEmptyOrderIsFlaggedZero(): void
    {
        $this->execute($this->order([]));

        $this->assertSame(0, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    /**
     * `getItems()` answers null on an order not yet loaded with its lines, and
     * iterating null is a fatal in the middle of order placement.
     */
    public function testAnOrderWhoseItemsAreNotLoadedIsHandled(): void
    {
        $this->execute($this->order(null));

        $this->assertSame(0, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    /**
     * An option key present but empty is a line whose embroidery was removed.
     */
    public function testAnEmptyOptionValueDoesNotCountAsEmbroidery(): void
    {
        $this->execute($this->order([$this->item([OptionCodeInterface::OPTIONS => ''])]));

        $this->assertSame(0, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    /**
     * The surcharge alone is not embroidery: a free personalisation carries a
     * zero one.
     */
    public function testOnlyTheSelectionsOptionDecidesTheFlag(): void
    {
        $this->execute($this->order([$this->item([OptionCodeInterface::SURCHARGE => '0.00'])]));

        $this->assertSame(0, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    /**
     * The event carries whatever dispatched it; a missing order must not become
     * a type error inside order placement.
     */
    public function testAnEventWithoutAnOrderIsIgnored(): void
    {
        (new FlagOrderWithEmbroidery())->execute(new Observer(['event' => new Event([])]));
        (new FlagOrderWithEmbroidery())->execute(new Observer(['event' => new Event(['order' => 'nope'])]));

        $this->assertSame([], $this->written);
    }

    /**
     * A line whose options are not an array - a corrupted historical row - must
     * not stop the order being flagged on the lines that are readable.
     */
    public function testAMalformedLineIsSkippedAndTheRestStillCount(): void
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getProductOptions')->willReturn(null);

        $this->execute($this->order([$item, $this->item([OptionCodeInterface::OPTIONS => '{"sides":[]}'])]));

        $this->assertSame(1, $this->written[FlagOrderWithEmbroidery::ATTRIBUTE]);
    }

    private function execute(OrderInterface $order): void
    {
        (new FlagOrderWithEmbroidery())->execute(new Observer(['event' => new Event(['order' => $order])]));
    }

    /**
     * @param OrderItem[]|null $items
     */
    private function order(?array $items): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getItems')->willReturn($items);
        $order->method('setData')->willReturnCallback(
            function (string $key, $value = null) use (&$order) {
                $this->written[$key] = $value;

                return $order;
            }
        );

        return $order;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function item(array $options): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getProductOptions')->willReturn($options);

        return $item;
    }
}
