<?php
/**
 * OptionsProviderTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Personalization;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\OptionsProvider;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Test\Unit\Fake\ArrayScopeConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OptionsProviderTest extends TestCase
{
    /** @var ThreadColorInterface[] */
    private array $activeColors = [];

    protected function setUp(): void
    {
        $this->activeColors = [
            $this->color('T-100', 'Ceil Blue', '#7FA8D4'),
            $this->color('T-101', 'Navy', '#1B2A4A'),
        ];
    }

    /**
     * The thread colours were once listed twice - hardcoded in a bespoke XML
     * file and stored in a table with an admin grid and a CSV import.
     */
    public function testTheThreadColoursComeFromTheRepositoryRatherThanConfiguration(): void
    {
        self::assertSame(
            [
                ['value' => 'T-100', 'label' => 'Ceil Blue', 'hex' => '#7FA8D4'],
                ['value' => 'T-101', 'label' => 'Navy', 'hex' => '#1B2A4A'],
            ],
            $this->provider()->getThreadColorOptions()
        );
    }

    /**
     * A deactivated colour stays in the table for historical orders to render
     * against; only the picker has to stop offering it.
     */
    public function testOnlyActiveColoursAreOffered(): void
    {
        $repository = $this->createMock(ThreadColorRepositoryInterface::class);
        $repository->expects(self::once())->method('getActive')->willReturn([]);
        $repository->expects(self::never())->method('getList');

        self::assertSame([], $this->provider(repository: $repository)->getThreadColorOptions());
    }

    /**
     * Font styles, logo locations and logo types come from di.xml arrays.
     */
    public function testTheDeclarativeListsAreRenderedAsValueLabelPairs(): void
    {
        $options = $this->provider(
            fontStyles: ['block' => 'Block', 'script' => 'Script'],
            logoLocations: ['left_chest' => 'Left chest'],
            logoTypes: ['stock' => 'Stock logo', 'custom' => 'Custom logo'],
        )->getFormOptions();

        self::assertSame(
            [['value' => 'block', 'label' => 'Block'], ['value' => 'script', 'label' => 'Script']],
            $options['font_styles']
        );
        self::assertSame([['value' => 'left_chest', 'label' => 'Left chest']], $options['logo_locations']);
        self::assertCount(2, $options['logo_types']);
    }

    /**
     * A store that has not configured a list gets an empty one rather than a
     * shipped default it never asked for.
     */
    public function testAnUnconfiguredListIsEmpty(): void
    {
        $options = $this->provider()->getFormOptions();

        self::assertSame([], $options['font_styles']);
        self::assertSame([], $options['logo_locations']);
        self::assertSame([], $options['logo_types']);
    }

    /**
     * The sides are an enum rather than configuration.
     */
    public function testEverySideIsOfferedWithItsLabel(): void
    {
        $sides = $this->provider()->getFormOptions()['sides'];

        self::assertCount(count(Side::cases()), $sides);
        self::assertSame(
            array_map(static fn (Side $s): string => $s->value, Side::cases()),
            array_column($sides, 'value')
        );
        self::assertNotContains('', array_column($sides, 'label'));
    }

    /**
     * The prices go to the browser so the form can total a selection before it
     * is submitted; the server recomputes them, so these are for display.
     */
    public function testEveryChargeableComponentIsPricedInThePayload(): void
    {
        $prices = $this->provider(config: [
            'charges/text_line_1_price' => '3.00',
            'charges/text_line_2_price' => '2.00',
            'charges/text_line_3_price' => '1.00',
            'charges/stock_logo_price' => '4.50',
            'charges/custom_logo_price' => '7.25',
            'charges/custom_logo_fee' => '25.00',
        ])->getFormOptions()['prices'];

        self::assertSame(
            [
                'text_line_1' => 3.0,
                'text_line_2' => 2.0,
                'text_line_3' => 1.0,
                'stock_logo' => 4.5,
                'custom_logo' => 7.25,
                'custom_logo_fee' => 25.0,
            ],
            $prices
        );
    }

    /**
     * Prices are store-scoped, so a form rendered for one store view must not
     * quote another's.
     */
    public function testTheStoreScopeReachesThePriceLookups(): void
    {
        $prices = $this->provider(config: ['charges/stock_logo_price' => '4.50'])
            ->getFormOptions(2)['prices'];

        self::assertSame(4.5, $prices['stock_logo']);
    }

    public function testThePayloadCarriesEverySectionTheFormNeeds(): void
    {
        self::assertSame(
            ['sides', 'font_styles', 'logo_locations', 'logo_types', 'thread_colors', 'prices'],
            array_keys($this->provider()->getFormOptions())
        );
    }

    /**
     * @param array<string, string> $fontStyles
     * @param array<string, string> $logoLocations
     * @param array<string, string> $logoTypes
     * @param array<string, mixed>  $config
     */
    private function provider(
        array $fontStyles = [],
        array $logoLocations = [],
        array $logoTypes = [],
        array $config = [],
        ?ThreadColorRepositoryInterface $repository = null
    ): OptionsProvider {
        if ($repository === null) {
            $repository = $this->createMock(ThreadColorRepositoryInterface::class);
            $repository->method('getActive')->willReturnCallback(fn (): array => $this->activeColors);
        }

        $qualified = [];

        foreach ($config as $path => $value) {
            $qualified['test_embroidery/' . $path] = $value;
        }

        return new OptionsProvider(
            $repository,
            new Config(new ArrayScopeConfig($qualified), 'test_embroidery'),
            $fontStyles,
            $logoLocations,
            $logoTypes
        );
    }

    private function color(string $code, string $name, string $hex): ThreadColorInterface&MockObject
    {
        $color = $this->createMock(ThreadColorInterface::class);
        $color->method('getCode')->willReturn($code);
        $color->method('getName')->willReturn($name);
        $color->method('getHexCode')->willReturn($hex);

        return $color;
    }
}
