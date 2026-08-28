<?php
/**
 * SelectionMapperTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Personalization;

use Commerce\Embroidery\Model\Personalization\SelectionMapper;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use PHPUnit\Framework\TestCase;

class SelectionMapperTest extends TestCase
{
    private SelectionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SelectionMapper();
    }

    public function testKeepsLineNumbersRatherThanCompactingThem(): void
    {
        $selection = $this->mapper->sideFromArray(Side::Left, [
            'text_line_1' => 'Jane Doe',
            'text_line_2' => '   ',
            'text_line_3' => 'Cardiology',
        ]);

        self::assertSame([1 => 'Jane Doe', 3 => 'Cardiology'], $selection->textLines);
    }

    public function testTrimsAndDropsBlankValues(): void
    {
        $selection = $this->mapper->sideFromArray(Side::Left, [
            'text_line_1' => '  Jane Doe  ',
            'font_style' => '  ',
            'thread_color' => 'ceil-blue',
        ]);

        self::assertSame([1 => 'Jane Doe'], $selection->textLines);
        self::assertNull($selection->fontStyle);
        self::assertSame('ceil-blue', $selection->threadColorCode);
    }

    /**
     * The logo type reaches the pricing rules, so an unrecognised value must
     * not pass through and be priced as whatever it happens to match.
     */
    public function testAnUnknownLogoTypeFallsBackToNone(): void
    {
        $selection = $this->mapper->sideFromArray(Side::Left, ['logo_type' => 'free-please']);

        self::assertSame(SideSelection::LOGO_NONE, $selection->logoType);
        self::assertFalse($selection->hasLogo());
    }

    public function testMapsOnlyTheSidesThePayloadCarries(): void
    {
        $selection = $this->mapper->selectionFromArray([
            'left' => ['text_line_1' => 'Jane Doe'],
            'right' => 'not an array',
        ]);

        self::assertNotNull($selection->get(Side::Left));
        self::assertNull($selection->get(Side::Right));
    }

    /**
     * A side the shopper left blank must not reach the order or the ERP export
     * as an empty structure.
     */
    public function testDropsSidesWithNothingOnThem(): void
    {
        $selection = $this->mapper->selectionFromArray([
            'left' => ['text_line_1' => '   ', 'logo_type' => 'none'],
        ]);

        self::assertTrue($selection->isEmpty());
    }

    public function testAnEmptyPayloadMapsToAnEmptySelection(): void
    {
        self::assertTrue($this->mapper->selectionFromArray([])->isEmpty());
    }
}
