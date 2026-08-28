<?php
/**
 * EmbroiderySelectionTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Personalization;

use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmbroiderySelectionTest extends TestCase
{
    public function testIndexesSidesSoOnlyTheLastOneForASideSurvives(): void
    {
        $selection = new EmbroiderySelection([
            new SideSelection(Side::Left, [1 => 'first']),
            new SideSelection(Side::Left, [1 => 'second']),
        ]);

        self::assertCount(1, $selection->all());
        self::assertSame([1 => 'second'], $selection->get(Side::Left)?->textLines);
    }

    /**
     * A blank side carried through to the order and the ERP export is an empty
     * structure the export mapper then has to defend against.
     */
    public function testDropsSidesTheShopperLeftBlank(): void
    {
        $selection = new EmbroiderySelection([
            new SideSelection(Side::Left),
            new SideSelection(Side::Right, [1 => 'Jane Doe']),
        ]);

        self::assertNull($selection->get(Side::Left));
        self::assertNotNull($selection->get(Side::Right));
        self::assertFalse($selection->isEmpty());
    }

    public function testAnEmptySelectionSaysSo(): void
    {
        self::assertTrue((new EmbroiderySelection())->isEmpty());
        self::assertSame([], (new EmbroiderySelection())->toArray());
    }

    public function testRejectsAnythingThatIsNotASideSelection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmbroiderySelection(['left' => ['text_line_1' => 'Jane Doe']]);
    }

    public function testTheStoredShapeIsKeyedBySide(): void
    {
        $selection = new EmbroiderySelection([new SideSelection(Side::Right, [1 => 'Jane Doe'])]);

        self::assertSame(['right'], array_keys($selection->toArray()));
    }
}
