<?php
/**
 * EmbroideryDetailsTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\ViewModel;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\SelectionReader;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use Commerce\Embroidery\ViewModel\EmbroideryDetails;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmbroideryDetailsTest extends TestCase
{
    /** @var array<string, ThreadColorInterface> */
    private array $threadColors = [];

    /** @var array<int, string[]> */
    private array $codeLookups = [];

    private ?EmbroiderySelection $selection = null;

    protected function setUp(): void
    {
        $this->codeLookups = [];
        $this->threadColors = ['T-100' => $this->color('T-100', 'Ceil Blue')];
        $this->selection = new EmbroiderySelection([$this->side()]);
    }

    public function testItIsUsableAsALayoutViewModel(): void
    {
        self::assertInstanceOf(ArgumentInterface::class, $this->viewModel());
    }

    /**
     * The cart, the order view, the invoice and the admin screen all render
     * through here, so all four present the same thing.
     */
    public function testAQuoteItemAndAnOrderItemRenderIdentically(): void
    {
        $viewModel = $this->viewModel();

        self::assertEquals(
            $viewModel->forQuoteItem($this->createMock(QuoteItem::class)),
            $viewModel->forOrderItem($this->createMock(OrderItem::class))
        );
    }

    public function testEverySideBecomesABlockLabelledWithItsName(): void
    {
        $this->selection = new EmbroiderySelection([$this->side(Side::Left), $this->side(Side::Right)]);

        $blocks = $this->viewModel()->forQuoteItem($this->createMock(QuoteItem::class));

        self::assertSame(['Left', 'Right'], array_column($blocks, 'side'));
    }

    public function testEachTextLineIsRenderedWithItsLineNumber(): void
    {
        $rows = $this->rows();

        self::assertSame('Line 1', (string) $rows[0]['label']);
        self::assertSame('A. Nurse', $rows[0]['value']);
        self::assertSame('Line 2', (string) $rows[1]['label']);
        self::assertSame('RN', $rows[1]['value']);
    }

    /**
     * Line 3 without line 2 stays line 3: renumbering it would show the shopper
     * a different layout from the one they bought.
     */
    public function testAGapInTheTextLinesIsPreservedInTheLabels(): void
    {
        $this->selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'A. Nurse', 3 => 'Cardiology'], null, null),
        ]);

        self::assertSame(['Line 1', 'Line 3'], array_map(
            static fn (array $row): string => (string) $row['label'],
            $this->rows()
        ));
    }

    public function testTheThreadColourIsShownByItsName(): void
    {
        $rows = $this->rows();
        $threadRow = $this->rowLabelled($rows, 'Thread colour');

        self::assertSame('Ceil Blue', $threadRow['value']);
    }

    /**
     * A colour deleted since the order was placed still has to render - falling
     * back to the stored code beats an empty row on a historical order.
     */
    public function testADeletedThreadColourFallsBackToItsStoredCode(): void
    {
        $this->threadColors = [];

        self::assertSame('T-100', $this->rowLabelled($this->rows(), 'Thread colour')['value']);
    }

    /**
     * One lookup for every code on the item rather than one per side.
     */
    public function testTheThreadColoursAreResolvedInOneLookup(): void
    {
        $this->selection = new EmbroiderySelection([
            $this->side(Side::Left, 'T-100'),
            $this->side(Side::Right, 'T-101'),
        ]);

        $this->viewModel()->forQuoteItem($this->createMock(QuoteItem::class));

        self::assertCount(1, $this->codeLookups);
        self::assertSame(['T-100', 'T-101'], array_values($this->codeLookups[0]));
    }

    /**
     * An item whose sides carry no colour must not send an empty `IN ()` to the
     * repository.
     */
    public function testNoLookupHappensWhenNoSideNamesAColour(): void
    {
        $this->selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'A. Nurse'], null, null),
        ]);

        $this->viewModel()->forQuoteItem($this->createMock(QuoteItem::class));

        self::assertSame([], $this->codeLookups);
    }

    public function testTheFontIsShownWhenOneWasChosen(): void
    {
        self::assertSame('block', $this->rowLabelled($this->rows(), 'Font')['value']);
    }

    /**
     * A shopper who chose no font gets no font row, rather than a row saying
     * nothing.
     */
    public function testAnUnsetFieldContributesNoRow(): void
    {
        $this->selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'A. Nurse'], null, null),
        ]);

        $labels = array_map(static fn (array $row): string => (string) $row['label'], $this->rows());

        self::assertSame(['Line 1'], $labels);
    }

    public function testALogoIsShownWithItsTypeAndPosition(): void
    {
        $this->selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [], null, null, SideSelection::LOGO_STOCK, 'a1b2c3.png', 'left_chest'),
        ]);

        $rows = $this->rows();

        self::assertSame('Stock', $this->rowLabelled($rows, 'Logo')['value']);
        self::assertSame('left_chest', $this->rowLabelled($rows, 'Logo position')['value']);
    }

    /**
     * An item with no embroidery renders nothing at all, so a template can loop
     * over the result without a guard of its own.
     */
    public function testAnItemWithoutEmbroideryRendersNothing(): void
    {
        $this->selection = null;

        self::assertSame([], $this->viewModel()->forQuoteItem($this->createMock(QuoteItem::class)));
    }

    public function testAnEmptySelectionRendersNothing(): void
    {
        $this->selection = new EmbroiderySelection([]);

        self::assertSame([], $this->viewModel()->forQuoteItem($this->createMock(QuoteItem::class)));
    }

    /**
     * @return array<int, array{label: \Magento\Framework\Phrase, value: string}>
     */
    private function rows(): array
    {
        return $this->viewModel()->forQuoteItem($this->createMock(QuoteItem::class))[0]['rows'];
    }

    /**
     * @param array<int, array{label: \Magento\Framework\Phrase, value: string}> $rows
     *
     * @return array{label: \Magento\Framework\Phrase, value: string}
     */
    private function rowLabelled(array $rows, string $label): array
    {
        foreach ($rows as $row) {
            if ((string) $row['label'] === $label) {
                return $row;
            }
        }

        self::fail(sprintf('No row labelled "%s" was rendered.', $label));
    }

    private function side(Side $side = Side::Left, string $threadColorCode = 'T-100'): SideSelection
    {
        return new SideSelection(
            $side,
            [1 => 'A. Nurse', 2 => 'RN'],
            'block',
            $threadColorCode
        );
    }

    private function viewModel(): EmbroideryDetails
    {
        $reader = $this->createMock(SelectionReader::class);
        $reader->method('fromQuoteItem')->willReturnCallback(fn (): ?EmbroiderySelection => $this->selection);
        $reader->method('fromOrderItem')->willReturnCallback(fn (): ?EmbroiderySelection => $this->selection);

        $repository = $this->createMock(ThreadColorRepositoryInterface::class);
        $repository->method('getByCodes')->willReturnCallback(
            function (array $codes): array {
                $this->codeLookups[] = $codes;

                return array_intersect_key($this->threadColors, array_flip($codes));
            }
        );

        return new EmbroideryDetails($reader, $repository);
    }

    private function color(string $code, string $name): ThreadColorInterface&MockObject
    {
        $color = $this->createMock(ThreadColorInterface::class);
        $color->method('getCode')->willReturn($code);
        $color->method('getName')->willReturn($name);

        return $color;
    }
}
