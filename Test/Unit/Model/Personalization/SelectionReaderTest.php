<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Personalization;

use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\OptionCodeInterface;
use Commerce\Embroidery\Model\Personalization\SelectionMapper;
use Commerce\Embroidery\Model\Personalization\SelectionReader;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\Option;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SelectionReaderTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testAQuoteItemCarryingSelectionsIsRecognised(): void
    {
        $this->assertTrue($this->reader()->isEmbroidered($this->quoteItem($this->payload())));
        $this->assertFalse($this->reader()->isEmbroidered($this->quoteItem(null)));
    }

    public function testASelectionIsReadBackFromAQuoteItem(): void
    {
        $selection = $this->reader()->fromQuoteItem($this->quoteItem($this->payload()));

        $this->assertInstanceOf(EmbroiderySelection::class, $selection);
        $this->assertFalse($selection->isEmpty());
        $this->assertNotNull($selection->get(Side::Left));
    }

    public function testAQuoteItemWithoutSelectionsReadsAsNothing(): void
    {
        $this->assertNull($this->reader()->fromQuoteItem($this->quoteItem(null)));
    }

    /**
     * Product options arrive as an array or as a JSON string, and both shapes
     * read back.
     */
    public function testAnOrderItemsSelectionsAreReadWhicheverShapeTheyWereStoredIn(): void
    {
        $asJson = $this->reader()->fromOrderItem($this->orderItem([
            OptionCodeInterface::OPTIONS => $this->payload(),
        ]));
        $asArray = $this->reader()->fromOrderItem($this->orderItem([
            OptionCodeInterface::OPTIONS => (array) (new Json())->unserialize($this->payload()),
        ]));

        $this->assertInstanceOf(EmbroiderySelection::class, $asJson);
        $this->assertInstanceOf(EmbroiderySelection::class, $asArray);
        $this->assertEquals($asJson->toArray(), $asArray->toArray());
    }

    public function testAnOrderItemWithoutSelectionsReadsAsNothing(): void
    {
        $this->assertNull($this->reader()->fromOrderItem($this->orderItem([])));
        $this->assertNull($this->reader()->fromOrderItem($this->orderItem(null)));
    }

    public function testEncodingProducesTheSelectionsOwnArrayForm(): void
    {
        $reader = $this->reader();
        $selection = $reader->fromQuoteItem($this->quoteItem($this->payload()));

        $encoded = (array) (new Json())->unserialize($reader->encode($selection));

        $this->assertSame([Side::Left->value], array_keys($encoded));
        $this->assertSame([1 => 'A. Nurse', 2 => 'RN'], $encoded[Side::Left->value]['text_lines']);
        $this->assertSame('T-100', $encoded[Side::Left->value]['thread_color']);
    }

    /**
     * encode() writes the selection's own array form, so that form is what the
     * mapper has to read back.
     */
    public function testASelectionSurvivesBeingEncodedAndReadBack(): void
    {
        $reader = $this->reader();
        $original = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'A. Nurse', 3 => 'Cardiology'], 'block', 'T-100'),
            new SideSelection(
                Side::Right,
                [2 => 'St Jude'],
                logoType: SideSelection::LOGO_STOCK,
                logoFileName: 'crest.png'
            ),
        ]);

        $readBack = $reader->fromQuoteItem($this->quoteItem($reader->encode($original)));

        $this->assertInstanceOf(EmbroiderySelection::class, $readBack);
        $this->assertSame([Side::Left->value, Side::Right->value], array_keys($readBack->toArray()));
        $this->assertSame([1 => 'A. Nurse', 3 => 'Cardiology'], $readBack->get(Side::Left)?->textLines);
        $this->assertSame([2 => 'St Jude'], $readBack->get(Side::Right)?->textLines);
        $this->assertEquals($original->toArray(), $readBack->toArray());
    }

    /**
     * The same payload read off an order item, which is where a lost line would
     * actually cost the shopper their embroidery.
     */
    public function testAnEncodedSelectionSurvivesBeingReadBackFromAnOrderItem(): void
    {
        $reader = $this->reader();
        $original = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'A. Nurse'], 'block', 'T-100'),
        ]);

        $readBack = $reader->fromOrderItem($this->orderItem([
            OptionCodeInterface::OPTIONS => $reader->encode($original),
        ]));

        $this->assertInstanceOf(EmbroiderySelection::class, $readBack);
        $this->assertSame([1 => 'A. Nurse'], $readBack->get(Side::Left)?->textLines);
    }

    /**
     * A historical order with a malformed payload must still render.
     */
    public function testACorruptPayloadIsLoggedAndReadsAsNothing(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('decode'));

        $this->assertNull($this->reader()->fromQuoteItem($this->quoteItem('{not json')));
    }

    /**
     * An empty option value is an absence rather than a corruption, and is not
     * logged.
     */
    public function testAnEmptyPayloadIsNotTreatedAsCorruption(): void
    {
        $this->logger->expects($this->never())->method('warning');

        $this->assertNull($this->reader()->fromQuoteItem($this->quoteItem('   ')));
    }

    /**
     * A payload that decodes to a scalar is refused rather than read as an
     * empty personalisation.
     */
    public function testAPayloadThatIsNotAnObjectReadsAsNothing(): void
    {
        $this->assertNull($this->reader()->fromQuoteItem($this->quoteItem('"front"')));
    }

    private function payload(): string
    {
        return (new Json())->serialize([
            Side::Left->value => [
                'text_line_1' => 'A. Nurse',
                'text_line_2' => 'RN',
                'font_style' => 'block',
                'thread_color' => 'T-100',
            ],
        ]);
    }

    private function reader(): SelectionReader
    {
        return new SelectionReader(new Json(), new SelectionMapper(), $this->logger);
    }

    private function quoteItem(?string $payload): QuoteItem&MockObject
    {
        $item = $this->createMock(QuoteItem::class);
        $item->method('getOptionByCode')->willReturnCallback(
            function (string $code) use ($payload): ?Option {
                if ($payload === null || $code !== OptionCodeInterface::OPTIONS) {
                    return null;
                }

                $option = $this->createMock(Option::class);
                $option->method('getValue')->willReturn($payload);

                return $option;
            }
        );

        return $item;
    }

    /**
     * @param array<string, mixed>|null $options
     */
    private function orderItem(?array $options): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getProductOptions')->willReturn($options);

        return $item;
    }
}
