<?php
/**
 * CopyOptionsToOrderItemTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Plugin;

use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Commerce\Embroidery\Plugin\CopyOptionsToOrderItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\Option;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CopyOptionsToOrderItemTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $productOptions = [];

    public function testEveryEmbroideryOptionIsCopiedOntoTheOrderItem(): void
    {
        $orderItem = $this->orderItem();

        $this->plugin()->afterConvert(
            $this->subject(),
            $orderItem,
            $this->quoteItem([
                OptionCodeInterface::OPTIONS => '{"sides":[]}',
                OptionCodeInterface::SURCHARGE => '12.50',
                OptionCodeInterface::PRICE_BREAKDOWN => '{"text":12.5}',
            ])
        );

        $this->assertSame('{"sides":[]}', $this->productOptions[OptionCodeInterface::OPTIONS]);
        $this->assertSame('12.50', $this->productOptions[OptionCodeInterface::SURCHARGE]);
        $this->assertSame('{"text":12.5}', $this->productOptions[OptionCodeInterface::PRICE_BREAKDOWN]);
    }

    public function testTheOrderItemIsReturnedForTheNextPlugin(): void
    {
        $orderItem = $this->orderItem();

        $this->assertSame(
            $orderItem,
            $this->plugin()->afterConvert($this->subject(), $orderItem, $this->quoteItem([]))
        );
    }

    /**
     * The options array is added to rather than replaced, so other modules'
     * options survive.
     */
    public function testTheOptionsAlreadyOnTheOrderItemAreKept(): void
    {
        $this->productOptions = ['info_buyRequest' => ['qty' => 1]];

        $this->plugin()->afterConvert(
            $this->subject(),
            $this->orderItem(),
            $this->quoteItem([OptionCodeInterface::OPTIONS => '{"sides":[]}'])
        );

        $this->assertSame(['qty' => 1], $this->productOptions['info_buyRequest']);
    }

    /**
     * Most lines in a basket carry no embroidery.
     */
    public function testAnItemWithoutEmbroideryIsNotWrittenTo(): void
    {
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductOptions')->willReturn([]);
        $orderItem->expects($this->never())->method('setProductOptions');

        $this->plugin()->afterConvert($this->subject(), $orderItem, $this->quoteItem([]));
    }

    /**
     * A line can carry the surcharge without the breakdown - an older basket
     * restored from a session predating the breakdown.
     */
    public function testThePresentOptionsAreCopiedEvenIfSomeAreMissing(): void
    {
        $this->plugin()->afterConvert(
            $this->subject(),
            $this->orderItem(),
            $this->quoteItem([OptionCodeInterface::SURCHARGE => '12.50'])
        );

        $this->assertSame([OptionCodeInterface::SURCHARGE], array_keys($this->productOptions));
    }

    /**
     * `getProductOptions()` answers null on an untouched order item, and array
     * access would fatal.
     */
    public function testAnOrderItemWithNoOptionsAtAllIsHandled(): void
    {
        $this->productOptions = null;

        $this->plugin()->afterConvert(
            $this->subject(),
            $this->orderItem(),
            $this->quoteItem([OptionCodeInterface::OPTIONS => '{"sides":[]}'])
        );

        $this->assertSame('{"sides":[]}', $this->productOptions[OptionCodeInterface::OPTIONS]);
    }

    /**
     * The codes are copied in the order the contract lists them, so an order
     * item's options array is the same shape whichever line produced it.
     */
    public function testTheOptionsAreCopiedInTheOrderTheContractDeclares(): void
    {
        $this->plugin()->afterConvert(
            $this->subject(),
            $this->orderItem(),
            $this->quoteItem([
                OptionCodeInterface::PRICE_BREAKDOWN => '{"text":12.5}',
                OptionCodeInterface::SURCHARGE => '12.50',
                OptionCodeInterface::OPTIONS => '{"sides":[]}',
            ])
        );

        $this->assertSame(OptionCodeInterface::ALL, array_keys($this->productOptions));
    }

    private function plugin(): CopyOptionsToOrderItem
    {
        return new CopyOptionsToOrderItem();
    }

    private function subject(): ToOrderItem&MockObject
    {
        return $this->createMock(ToOrderItem::class);
    }

    private function orderItem(): OrderItem&MockObject
    {
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductOptions')->willReturnCallback(fn (): ?array => $this->productOptions);
        $orderItem->method('setProductOptions')->willReturnCallback(
            function (array $options) use (&$orderItem): OrderItem {
                $this->productOptions = $options;

                return $orderItem;
            }
        );

        return $orderItem;
    }

    /**
     * @param array<string, string> $options Option code => stored value.
     */
    private function quoteItem(array $options): QuoteItem&MockObject
    {
        $item = $this->createMock(QuoteItem::class);
        $item->method('getOptionByCode')->willReturnCallback(
            function (string $code) use ($options): ?Option {
                if (!isset($options[$code])) {
                    return null;
                }

                $option = $this->createMock(Option::class);
                $option->method('getValue')->willReturn($options[$code]);

                return $option;
            }
        );

        return $item;
    }
}
