<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Export;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\Charge\ChargeBreakdown;
use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Export\FieldMapExportMapper;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use PHPUnit\Framework\TestCase;

/**
 * The shape handed to whatever fulfils the embroidery.
 */
final class FieldMapExportMapperTest extends TestCase
{
    private int $repositoryCalls = 0;

    protected function setUp(): void
    {
        $this->repositoryCalls = 0;
    }

    public function testAnEmptySelectionStillCarriesTheTotalAndANullDetailField(): void
    {
        $payload = $this->mapper()->map(new EmbroiderySelection(), new ChargeBreakdown());

        self::assertSame(0.0, $payload['MonogramPrice']);
        self::assertNull($payload['MonogramDetails']);
    }

    public function testStaticFieldsAreAlwaysEmitted(): void
    {
        $mapper = $this->mapper(staticFields: ['MonogramFlag' => 1.0]);

        $payload = $mapper->map(new EmbroiderySelection(), new ChargeBreakdown());

        self::assertSame(1.0, $payload['MonogramFlag']);
    }

    public function testTheTotalComesFromTheChargeBreakdown(): void
    {
        $charges = new ChargeBreakdown(['left_' . ChargeCalculator::COMPONENT_TEXT => 7.5]);

        $payload = $this->mapper()->map($this->selection(), $charges);

        self::assertSame(7.5, $payload['MonogramPrice']);
    }

    public function testTextLinesBecomeDetailEntriesWithTheirLineNumber(): void
    {
        $selection = $this->selection(textLines: [1 => 'Dr Ada Lovelace', 2 => 'Cardiology']);

        $details = $this->mapper()->map($selection, new ChargeBreakdown())['MonogramDetails'];

        self::assertSame('Left Chest Embroidered Text 1', $details['left_text_1']['label']);
        self::assertSame('Dr Ada Lovelace', $details['left_text_1']['value']);
        self::assertSame('Cardiology', $details['left_text_2']['value']);
    }

    public function testAKnownThreadColourIsRenderedAsNameAndId(): void
    {
        $selection = $this->selection(textLines: [1 => 'Ada'], threadColorCode: 'NVY');

        $details = $this->mapper(['NVY' => $this->threadColor('Navy', 12)])
            ->map($selection, new ChargeBreakdown())['MonogramDetails'];

        self::assertSame('Navy/12', $details['left_thread_color']['value']);
    }

    /**
     * A renamed or deleted colour must not export as an empty value on the
     * workshop ticket.
     */
    public function testAnUnknownThreadColourFallsBackToItsRawCodeRatherThanEmpty(): void
    {
        $selection = $this->selection(textLines: [1 => 'Ada'], threadColorCode: 'GONE');

        $details = $this->mapper()->map($selection, new ChargeBreakdown())['MonogramDetails'];

        self::assertSame('GONE', $details['left_thread_color']['value']);
        self::assertNotSame('', $details['left_thread_color']['value']);
    }

    /**
     * One batch for the whole selection.
     */
    public function testThreadColoursAreResolvedInOneBatchForTheWholeSelection(): void
    {
        $selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'Ada'], threadColorCode: 'NVY'),
            new SideSelection(Side::Right, [1 => 'Grace'], threadColorCode: 'RED'),
        ]);

        $this->mapper(['NVY' => $this->threadColor('Navy', 12), 'RED' => $this->threadColor('Red', 13)])
            ->map($selection, new ChargeBreakdown());

        self::assertSame(1, $this->repositoryCalls);
    }

    public function testASelectionWithNoThreadColourAsksTheRepositoryNothing(): void
    {
        $this->mapper()->map($this->selection(textLines: [1 => 'Ada']), new ChargeBreakdown());

        self::assertSame(0, $this->repositoryCalls);
    }

    public function testBothChestsProduceTheirOwnSideFields(): void
    {
        $selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'Ada']),
            new SideSelection(Side::Right, [1 => 'Grace']),
        ]);

        $payload = $this->mapper()->map($selection, new ChargeBreakdown());

        self::assertSame('Ada', $payload['MonogramDetails']['left_text_1']['value']);
        self::assertSame('Grace', $payload['MonogramDetails']['right_text_1']['value']);
        self::assertArrayHasKey('LeftLineOne', $payload);
        self::assertArrayHasKey('RightLineOne', $payload);
    }

    public function testALogoContributesItsTypeUploadAndLocation(): void
    {
        $selection = $this->selection(
            logoType: SideSelection::LOGO_CUSTOM,
            logoFileName: 'a1b2c3.png',
            logoLocation: 'above text'
        );

        $details = $this->mapper()->map($selection, new ChargeBreakdown())['MonogramDetails'];

        self::assertSame('custom', $details['left_logo_type']['value']);
        self::assertSame('a1b2c3.png', $details['left_logo_upload']['value']);
        self::assertSame('above text', $details['left_logo_location']['value']);
    }

    public function testALogoPriceIsTheSumOfTheStockAndCustomComponents(): void
    {
        $charges = new ChargeBreakdown([
            'left_' . ChargeCalculator::COMPONENT_STOCK_LOGO => 3.0,
            'left_' . ChargeCalculator::COMPONENT_CUSTOM_LOGO => 5.0,
        ]);

        $payload = $this->mapper()->map(
            $this->selection(logoType: SideSelection::LOGO_CUSTOM),
            $charges
        );

        self::assertSame(8.0, $payload['LeftLogoPrice']);
    }

    public function testAnAbsentTextLineIsPricedAtZeroRatherThanOmitted(): void
    {
        $payload = $this->mapper()->map(
            $this->selection(textLines: [1 => 'Ada']),
            new ChargeBreakdown(['left_' . ChargeCalculator::COMPONENT_TEXT => 4.0])
        );

        self::assertSame(4.0, $payload['LeftLineOne']);
        self::assertSame(0.0, $payload['LeftLineTwo']);
        self::assertSame(0.0, $payload['LeftLineThree']);
    }

    /**
     * Every field name a downstream system expects is di.xml configuration.
     */
    public function testEveryFieldNameComesFromConfiguration(): void
    {
        $mapper = new FieldMapExportMapper(
            $this->repository([]),
            totalField: 'EmbroideryTotal',
            detailsField: 'EmbroideryDetail',
            sideFieldTemplates: ['logo_price' => '%s_logo', 'line_1' => '%s_l1'],
        );

        $payload = $mapper->map($this->selection(textLines: [1 => 'Ada']), new ChargeBreakdown());

        self::assertArrayHasKey('EmbroideryTotal', $payload);
        self::assertArrayHasKey('EmbroideryDetail', $payload);
        self::assertArrayHasKey('Left_logo', $payload);
        self::assertArrayHasKey('Left_l1', $payload);
    }

    private function selection(
        array $textLines = [1 => 'Ada'],
        ?string $threadColorCode = null,
        string $logoType = SideSelection::LOGO_NONE,
        ?string $logoFileName = null,
        ?string $logoLocation = null
    ): EmbroiderySelection {
        return new EmbroiderySelection([
            new SideSelection(
                Side::Left,
                $textLines,
                null,
                $threadColorCode,
                $logoType,
                $logoFileName,
                $logoLocation
            ),
        ]);
    }

    /**
     * @param array<string, ThreadColorInterface> $colors
     */
    private function mapper(array $colors = [], array $staticFields = []): FieldMapExportMapper
    {
        return new FieldMapExportMapper($this->repository($colors), staticFields: $staticFields);
    }

    /**
     * @param array<string, ThreadColorInterface> $colors
     */
    private function repository(array $colors): ThreadColorRepositoryInterface
    {
        $repository = $this->createMock(ThreadColorRepositoryInterface::class);
        $repository->method('getByCodes')->willReturnCallback(
            function (array $codes) use ($colors): array {
                $this->repositoryCalls++;

                return array_intersect_key($colors, array_flip($codes));
            }
        );

        return $repository;
    }

    private function threadColor(string $name, int $id): ThreadColorInterface
    {
        $color = $this->createMock(ThreadColorInterface::class);
        $color->method('getName')->willReturn($name);
        $color->method('getThreadColorId')->willReturn($id);

        return $color;
    }
}
