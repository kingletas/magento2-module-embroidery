<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Personalization;

use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Personalization\SideSelection;
use PHPUnit\Framework\TestCase;

class SideSelectionTest extends TestCase
{
    public function testRecognisesAnEmptySelection(): void
    {
        $this->assertTrue((new SideSelection(Side::Left))->isEmpty());
        $this->assertFalse((new SideSelection(Side::Left, [1 => 'x']))->isEmpty());
        $this->assertFalse(
            (new SideSelection(Side::Left, [], null, null, SideSelection::LOGO_STOCK))->isEmpty()
        );
    }

    public function testReportsTextAndLogoSeparately(): void
    {
        $selection = new SideSelection(Side::Left, [1 => 'Jane Doe']);

        $this->assertTrue($selection->hasText());
        $this->assertFalse($selection->hasLogo());
    }

    /**
     * The stored shape is read back by the order view and the ERP export, so it
     * is part of the contract rather than an implementation detail.
     */
    public function testRoundTripsThroughTheStoredShape(): void
    {
        $selection = new SideSelection(
            Side::Right,
            [1 => 'Jane Doe'],
            'script',
            'ceil-blue',
            SideSelection::LOGO_CUSTOM,
            'logo-abc.png',
            'above'
        );

        $this->assertSame([
            'side' => 'right',
            'text_lines' => [1 => 'Jane Doe'],
            'font_style' => 'script',
            'thread_color' => 'ceil-blue',
            'logo_type' => 'custom',
            'logo_file_name' => 'logo-abc.png',
            'logo_location' => 'above',
        ], $selection->toArray());
    }
}
