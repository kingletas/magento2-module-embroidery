<?php
/**
 * SideTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Personalization;

use Commerce\Embroidery\Model\Personalization\Side;
use PHPUnit\Framework\TestCase;

class SideTest extends TestCase
{
    public function testSideLabelsAreCapitalisedForExportFieldNames(): void
    {
        self::assertSame('Left', Side::Left->label());
        self::assertSame('Right', Side::Right->label());
    }

    public function testAllListsEverySide(): void
    {
        self::assertSame([Side::Left, Side::Right], Side::cases());
    }
}
