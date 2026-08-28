<?php
/**
 * ThreadColorTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\ResourceModel;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class ThreadColorTest extends TestCase
{
    /** @var array<int, array{table: string, rows: array<int, array<string, mixed>>, update: string[]}> */
    private array $upserts = [];

    /** @var array<int, array{condition: string, value: mixed}> */
    private array $conditions = [];

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->upserts = [];
        $this->conditions = [];
        $this->rows = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use (&$select): Select {
                $this->conditions[] = ['condition' => $condition, 'value' => $value];

                return $select;
            }
        );

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchAll')->willReturnCallback(fn (): array => $this->rows);
        $this->connection->method('insertOnDuplicate')->willReturnCallback(
            function (string $table, array $rows, array $update = []): int {
                $this->upserts[] = ['table' => $table, 'rows' => $rows, 'update' => $update];

                return count($rows);
            }
        );
    }

    public function testTheResourceIsWiredToItsTableAndKey(): void
    {
        $resource = (new ReflectionClass(ThreadColor::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod($resource, '_construct'))->invoke($resource);

        self::assertSame(
            ThreadColor::TABLE_NAME,
            (new ReflectionProperty(ThreadColor::class, '_mainTable'))->getValue($resource)
        );
        self::assertSame(ThreadColorInterface::THREAD_COLOR_ID, $resource->getIdFieldName());
    }

    /**
     * One statement for the batch.
     */
    public function testTheWholeBatchIsWrittenInOneStatement(): void
    {
        $rows = [
            [ThreadColorInterface::CODE => 'T-100', ThreadColorInterface::NAME => 'Ceil Blue'],
            [ThreadColorInterface::CODE => 'T-101', ThreadColorInterface::NAME => 'Navy'],
        ];

        self::assertSame(2, $this->resource()->upsertMany($rows));
        self::assertCount(1, $this->upserts);
        self::assertSame($rows, $this->upserts[0]['rows']);
    }

    /**
     * `code` is the natural key the import matches on; an upsert that rewrote
     * it could move a colour onto another's row.
     */
    public function testTheNaturalKeyIsNotRewrittenOnDuplicate(): void
    {
        $this->resource()->upsertMany([[ThreadColorInterface::CODE => 'T-100']]);

        self::assertNotContains(ThreadColorInterface::CODE, $this->upserts[0]['update']);
        self::assertNotContains(ThreadColorInterface::THREAD_COLOR_ID, $this->upserts[0]['update']);
        self::assertContains(ThreadColorInterface::NAME, $this->upserts[0]['update']);
        self::assertContains(ThreadColorInterface::IS_ACTIVE, $this->upserts[0]['update']);
    }

    public function testAnEmptyUpsertIsANoOp(): void
    {
        self::assertSame(0, $this->resource()->upsertMany([]));
        self::assertSame([], $this->upserts);
    }

    public function testColoursAreLoadedByCodeInOneQuery(): void
    {
        $this->rows = [
            [ThreadColorInterface::CODE => 'T-100', ThreadColorInterface::NAME => 'Ceil Blue'],
            [ThreadColorInterface::CODE => 'T-101', ThreadColorInterface::NAME => 'Navy'],
        ];

        $loaded = $this->resource()->loadByCodes(['T-100', 'T-101']);

        self::assertSame(['T-100', 'T-101'], array_keys($loaded));
        self::assertSame('Navy', $loaded['T-101'][ThreadColorInterface::NAME]);
    }

    public function testTheLookupIsRestrictedToTheRequestedCodes(): void
    {
        $this->resource()->loadByCodes(['T-100', 'T-101']);

        self::assertSame(ThreadColorInterface::CODE . ' IN (?)', $this->conditions[0]['condition']);
        self::assertSame(['T-100', 'T-101'], $this->conditions[0]['value']);
    }

    /**
     * A CSV repeating a code across rows is normal; the duplicates only inflate
     * the bound parameter list.
     */
    public function testDuplicateAndBlankCodesAreDroppedBeforeQuerying(): void
    {
        $this->resource()->loadByCodes(['T-100', 'T-100', '', 'T-101']);

        self::assertSame(['T-100', 'T-101'], $this->conditions[0]['value']);
    }

    /**
     * `IN ()` is a syntax error on MySQL, so an empty set has to be answered
     * before the query is built.
     */
    public function testAnEmptyCodeSetIsAnsweredWithoutQuerying(): void
    {
        self::assertSame([], $this->resource()->loadByCodes([]));
        self::assertSame([], $this->resource()->loadByCodes(['', '0']));
        self::assertSame([], $this->conditions);
    }

    private function resource(): ThreadColor&MockObject
    {
        $resource = $this->getMockBuilder(ThreadColor::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getMainTable')->willReturn(ThreadColor::TABLE_NAME);

        return $resource;
    }
}
