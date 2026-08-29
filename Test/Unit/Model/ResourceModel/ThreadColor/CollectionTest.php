<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\ResourceModel\ThreadColor;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor\Collection;
use Commerce\Embroidery\Model\ThreadColor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The real constructor builds a SELECT through the object manager, which a unit
 * test does not have.
 */
class CollectionTest extends TestCase
{
    public function testTheCollectionIsWiredToTheEntityAndItsResource(): void
    {
        $collection = $this->collection();

        $this->assertSame(ThreadColor::class, $collection->getModelName());
        $this->assertSame(ThreadColorResource::class, $collection->getResourceModelName());
    }

    /**
     * Set through the setter: the parent declares `$_idFieldName` untyped.
     */
    public function testTheIdFieldIsSetThroughTheSetter(): void
    {
        $this->assertSame(ThreadColorInterface::THREAD_COLOR_ID, $this->collection()->getIdFieldName());
    }

    public function testTheIdFieldIsNotTheFrameworkDefault(): void
    {
        $this->assertNotSame('id', $this->collection()->getIdFieldName());
    }

    /**
     * A deactivated colour is still in the table - the storefront picker is the
     * only thing it should disappear from.
     */
    public function testTheActiveScopeFiltersOnTheActiveFlag(): void
    {
        $collection = $this->partialCollection(['addFieldToFilter']);
        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with(ThreadColorInterface::IS_ACTIVE, 1);

        $this->assertSame($collection, $collection->addActiveFilter());
    }

    /**
     * A secondary sort on the name, so colours sharing a sort order keep a
     * stable order.
     */
    public function testTheDefaultOrderIsStableForColoursSharingASortOrder(): void
    {
        $orders = [];
        $collection = $this->partialCollection(['setOrder']);
        $collection->method('setOrder')->willReturnCallback(
            function (string $field, string $direction) use (&$orders, $collection) {
                $orders[] = [$field, $direction];

                return $collection;
            }
        );

        $this->assertSame($collection, $collection->addDefaultOrder());
        $this->assertSame(
            [
                [ThreadColorInterface::SORT_ORDER, Collection::SORT_ORDER_ASC],
                [ThreadColorInterface::NAME, Collection::SORT_ORDER_ASC],
            ],
            $orders
        );
    }

    private function collection(): Collection
    {
        $collection = (new ReflectionClass(Collection::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod($collection, '_construct'))->invoke($collection);

        return $collection;
    }

    /**
     * @param string[] $methods
     */
    private function partialCollection(array $methods): Collection&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }
}
