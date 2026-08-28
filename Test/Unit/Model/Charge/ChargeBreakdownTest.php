<?php
/**
 * ChargeBreakdownTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Charge;

use Commerce\Embroidery\Model\Charge\ChargeBreakdown;
use PHPUnit\Framework\TestCase;

class ChargeBreakdownTest extends TestCase
{
    /**
     * The cart display, the order email and the ERP export all read this
     * object.
     */
    public function testTheTotalIsAlwaysTheSumOfTheComponents(): void
    {
        $breakdown = new ChargeBreakdown(['left_text' => 3.5, 'right_text' => 2.25]);

        $this->assertSame(5.75, $breakdown->total);
        $this->assertSame(array_sum($breakdown->components), $breakdown->total);
    }

    public function testZeroComponentsAreDroppedRatherThanCarried(): void
    {
        $breakdown = new ChargeBreakdown(['left_text' => 3.5, 'custom_logo' => 0.0]);

        $this->assertSame(['left_text' => 3.5], $breakdown->components);
        $this->assertSame(0.0, $breakdown->get('custom_logo'));
    }

    public function testAnEmptyBreakdownIsTheNoChargeState(): void
    {
        $breakdown = new ChargeBreakdown();

        $this->assertTrue($breakdown->isZero());
        $this->assertSame([], $breakdown->toArray());
        $this->assertSame(0.0, $breakdown->total);
    }

    public function testAmountsAreRoundedOnTheWayIn(): void
    {
        $breakdown = new ChargeBreakdown(['left_text' => 1.234567]);

        $this->assertSame(1.2346, $breakdown->get('left_text'));
    }
}
