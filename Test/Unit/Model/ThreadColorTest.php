<?php
/**
 * ThreadColorTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Commerce\Embroidery\Model\ThreadColor;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ThreadColorTest extends TestCase
{
    /**
     * Read off the declared name, because `getResourceName()` answers with
     * whatever was injected.
     */
    public function testTheEntityDeclaresItsOwnResourceModel(): void
    {
        $declared = (new ReflectionProperty(ThreadColor::class, '_resourceName'))->getValue($this->entity());

        $this->assertSame(ThreadColorResource::class, $declared);
    }

    public function testTheEntityIsKeyedOnTheThreadColorId(): void
    {
        $this->assertSame(ThreadColorInterface::THREAD_COLOR_ID, $this->entity()->getIdFieldName());
    }

    public function testEveryFieldRoundTripsThroughItsSetter(): void
    {
        $entity = $this->entity()
            ->setThreadColorId(3)
            ->setCode('T-100')
            ->setName('Ceil Blue')
            ->setHexCode('#7FA8D4')
            ->setPantoneCode('PMS 291')
            ->setSortOrder(20)
            ->setIsActive(true)
            ->setCreatedAt('2026-01-01 00:00:00')
            ->setUpdatedAt('2026-01-02 00:00:00');

        $this->assertSame(3, $entity->getThreadColorId());
        $this->assertSame('T-100', $entity->getCode());
        $this->assertSame('Ceil Blue', $entity->getName());
        $this->assertSame('#7FA8D4', $entity->getHexCode());
        $this->assertSame('PMS 291', $entity->getPantoneCode());
        $this->assertSame(20, $entity->getSortOrder());
        $this->assertTrue($entity->isActive());
        $this->assertSame('2026-01-01 00:00:00', $entity->getCreatedAt());
        $this->assertSame('2026-01-02 00:00:00', $entity->getUpdatedAt());
    }

    /**
     * MySQL hands back "0" and "1" for a tinyint, and `(bool) "0"` is only
     * false by luck of PHP's rules - the same expression on "0.0" is true.
     */
    public function testTheActiveFlagIsABooleanWhicheverWayTheDatabaseSpellsIt(): void
    {
        $entity = $this->entity();

        $entity->setData(ThreadColorInterface::IS_ACTIVE, '1');
        $this->assertTrue($entity->isActive());

        $entity->setData(ThreadColorInterface::IS_ACTIVE, '0');
        $this->assertFalse($entity->isActive());

        $entity->setData(ThreadColorInterface::IS_ACTIVE, 0);
        $this->assertFalse($entity->isActive());
    }

    /**
     * Stored as a tinyint, not as PHP's `true`/`false` - which serialise to "1"
     * and "", and "" in a NOT NULL tinyint column is a write error.
     */
    public function testTheActiveFlagIsStoredAsAnInteger(): void
    {
        $entity = $this->entity();

        $this->assertSame(1, $entity->setIsActive(true)->getData(ThreadColorInterface::IS_ACTIVE));
        $this->assertSame(0, $entity->setIsActive(false)->getData(ThreadColorInterface::IS_ACTIVE));
    }

    public function testTheNumericGettersCoerceWhatTheDatabaseHandsBack(): void
    {
        $entity = $this->entity();
        $entity->setData(ThreadColorInterface::THREAD_COLOR_ID, '3');
        $entity->setData(ThreadColorInterface::SORT_ORDER, '20');

        $this->assertSame(3, $entity->getThreadColorId());
        $this->assertSame(20, $entity->getSortOrder());
    }

    /**
     * Most colours have no Pantone equivalent, and the column holds "" as often
     * as NULL depending on how the row was imported.
     */
    public function testAnAbsentPantoneCodeIsNullWhicheverWayItWasStored(): void
    {
        $entity = $this->entity();

        $this->assertNull($entity->getPantoneCode());

        $entity->setData(ThreadColorInterface::PANTONE_CODE, '');
        $this->assertNull($entity->getPantoneCode());
    }

    public function testAnUnsavedColourHasNoIdRatherThanZero(): void
    {
        $this->assertNull($this->entity()->getThreadColorId());
    }

    public function testTheRequiredStringsDefaultToEmptyRatherThanNull(): void
    {
        $entity = $this->entity();

        $this->assertSame('', $entity->getCode());
        $this->assertSame('', $entity->getName());
        $this->assertSame('', $entity->getHexCode());
        $this->assertSame(0, $entity->getSortOrder());
    }

    private function entity(): ThreadColor
    {
        $resource = $this->createMock(ThreadColorResource::class);
        $resource->method('getIdFieldName')->willReturn(ThreadColorInterface::THREAD_COLOR_ID);

        return new ThreadColor(
            $this->createMock(Context::class),
            $this->createMock(Registry::class),
            $resource
        );
    }
}
