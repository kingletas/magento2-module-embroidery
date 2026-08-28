<?php
/**
 * ThreadColorRepositoryTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Repository;

use Commerce\Foundation\Model\Repository\SearchResultBuilder;
use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\Data\ThreadColorInterfaceFactory;
use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterface;
use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterfaceFactory;
use Commerce\Embroidery\Model\Repository\ThreadColorRepository;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor\Collection;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor\CollectionFactory;
use Commerce\Embroidery\Model\ThreadColor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ThreadColorRepositoryTest extends TestCase
{
    /** @var array<string, array<string, mixed>> Stored rows, keyed by code. */
    private array $rows = [];

    /** @var array<int, string[]> One entry per batch lookup. */
    private array $codeLookups = [];

    /** @var array<int, int> Ids the resource was asked to load. */
    private array $idLoads = [];

    /** @var ThreadColorInterface[] */
    private array $activeColors = [];

    private int $collectionsBuilt = 0;
    private ThreadColorResource&MockObject $resource;

    protected function setUp(): void
    {
        $this->codeLookups = [];
        $this->idLoads = [];
        $this->collectionsBuilt = 0;
        $this->rows = [
            'T-100' => [ThreadColorInterface::CODE => 'T-100', ThreadColorInterface::NAME => 'Ceil Blue'],
            'T-101' => [ThreadColorInterface::CODE => 'T-101', ThreadColorInterface::NAME => 'Navy'],
        ];
        $this->activeColors = [];

        $this->resource = $this->createMock(ThreadColorResource::class);
        $this->resource->method('loadByCodes')->willReturnCallback(
            function (array $codes): array {
                $this->codeLookups[] = $codes;

                return array_intersect_key($this->rows, array_flip($codes));
            }
        );
        $this->resource->method('load')->willReturnCallback(
            function ($entity, $value, $field = null): void {
                $this->idLoads[] = (int) $value;

                if ((int) $value === 3) {
                    $entity->setData(ThreadColorInterface::THREAD_COLOR_ID, 3);
                    $entity->setData(ThreadColorInterface::CODE, 'T-100');
                }
            }
        );
    }

    public function testAColourIsLoadedById(): void
    {
        $this->assertSame(3, $this->repository()->getById(3)->getThreadColorId());
    }

    public function testAnUnknownIdIsNoSuchEntity(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository()->getById(404);
    }

    public function testAColourIsLoadedByItsCode(): void
    {
        $this->assertSame('Ceil Blue', $this->repository()->getByCode('T-100')->getName());
    }

    public function testAnUnknownCodeIsNoSuchEntity(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository()->getByCode('T-999');
    }

    public function testManyCodesAreResolvedInOneQuery(): void
    {
        $found = $this->repository()->getByCodes(['T-100', 'T-101']);

        $this->assertSame(['T-100', 'T-101'], array_keys($found));
        $this->assertCount(1, $this->codeLookups);
    }

    /**
     * The same handful of codes is asked for several times per storefront
     * request.
     */
    public function testARepeatedCodeIsNotLookedUpTwice(): void
    {
        $repository = $this->repository();

        $first = $repository->getByCodes(['T-100']);
        $second = $repository->getByCodes(['T-100']);

        $this->assertSame($first['T-100'], $second['T-100']);
        $this->assertCount(1, $this->codeLookups);
    }

    /**
     * A second call naming one known and one new code queries for the new one
     * alone, rather than refetching what is already in hand.
     */
    public function testOnlyTheCodesStillUnknownAreQueriedFor(): void
    {
        $repository = $this->repository();

        $repository->getByCodes(['T-100']);
        $found = $repository->getByCodes(['T-100', 'T-101']);

        $this->assertSame(['T-101'], $this->codeLookups[1]);
        $this->assertSame(['T-100', 'T-101'], array_keys($found));
    }

    /**
     * A code that resolves to nothing is simply absent, which is what lets a
     * caller rendering a historical order fall back to the stored code.
     */
    public function testAnUnknownCodeIsOmittedRatherThanReturnedEmpty(): void
    {
        $this->assertSame(['T-100'], array_keys($this->repository()->getByCodes(['T-100', 'T-999'])));
    }

    /**
     * `IN ()` is a syntax error on MySQL, and an item carrying no colour codes
     * at all is the ordinary case for a logo-only embroidery.
     */
    public function testAnEmptyOrBlankCodeSetIsAnsweredWithoutQuerying(): void
    {
        $repository = $this->repository();

        $this->assertSame([], $repository->getByCodes([]));
        $this->assertSame([], $repository->getByCodes(['', '   ']));
        $this->assertSame([], $this->codeLookups);
    }

    public function testCodesAreTrimmedAndDeduplicatedBeforeQuerying(): void
    {
        $this->repository()->getByCodes([' T-100 ', 'T-100', 'T-101']);

        $this->assertSame(['T-100', 'T-101'], $this->codeLookups[0]);
    }

    /**
     * Every storefront request that renders the form asks for this, and the
     * list does not change between two asks within one request.
     */
    public function testTheActiveListIsBuiltOnce(): void
    {
        $this->activeColors = [$this->color('T-100')];
        $repository = $this->repository();

        $repository->getActive();
        $repository->getActive();

        $this->assertSame(1, $this->collectionsBuilt);
    }

    /**
     * The code index is populated from the active list, so the pricing lookups
     * cost nothing.
     */
    public function testTheActiveListAlsoPrimesTheCodeIndex(): void
    {
        $this->activeColors = [$this->color('T-100')];
        $repository = $this->repository();

        $repository->getActive();
        $repository->getByCodes(['T-100']);

        $this->assertSame([], $this->codeLookups);
    }

    public function testSavingReturnsTheColourItPersisted(): void
    {
        $entity = $this->entity();

        $this->assertSame($entity, $this->repository()->save($entity));
    }

    /**
     * A driver-level failure surfaces as CouldNotSaveException rather than a
     * raw PDOException.
     */
    public function testAFailedSaveIsWrappedInTheContractsException(): void
    {
        $this->resource = $this->createMock(ThreadColorResource::class);
        $this->resource->method('save')->willThrowException(new RuntimeException('duplicate key'));

        $this->expectException(CouldNotSaveException::class);

        $this->repository()->save($this->entity());
    }

    public function testAFailedDeleteIsWrappedInTheContractsException(): void
    {
        $this->resource = $this->createMock(ThreadColorResource::class);
        $this->resource->method('delete')->willThrowException(new RuntimeException('foreign key'));

        $this->expectException(CouldNotDeleteException::class);

        $this->repository()->delete($this->entity());
    }

    /**
     * The admin grid reloads immediately after a save.
     */
    public function testASaveForgetsTheMemoisedLookups(): void
    {
        $repository = $this->repository();
        $repository->getByCodes(['T-100']);

        $repository->save($this->entity());
        $repository->getByCodes(['T-100']);

        $this->assertCount(2, $this->codeLookups);
    }

    public function testADeleteForgetsTheMemoisedActiveList(): void
    {
        $this->activeColors = [$this->color('T-100')];
        $repository = $this->repository();
        $repository->getActive();

        $repository->delete($this->entity());
        $repository->getActive();

        $this->assertSame(2, $this->collectionsBuilt);
    }

    public function testDeletingByIdLoadsTheRowFirst(): void
    {
        $this->repository()->deleteById(3);

        $this->assertSame([3], $this->idLoads);
    }

    /**
     * Deleting an id that does not exist fails rather than reporting a silent
     * success.
     */
    public function testDeletingAnIdThatDoesNotExistFails(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository()->deleteById(404);
    }

    public function testTheListGoesThroughTheSharedSearchResultBuilder(): void
    {
        $results = $this->createMock(ThreadColorSearchResultsInterface::class);
        $builder = $this->createMock(SearchResultBuilder::class);
        $builder->expects($this->once())->method('build')->willReturn($results);

        $this->assertSame(
            $results,
            $this->repository($builder)->getList($this->createMock(SearchCriteriaInterface::class))
        );
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

    private function color(string $code): ThreadColor
    {
        return $this->entity()->setCode($code);
    }

    private function repository(?SearchResultBuilder $builder = null): ThreadColorRepository
    {
        $entityFactory = $this->createMock(ThreadColorInterfaceFactory::class);
        $entityFactory->method('create')->willReturnCallback(fn (): ThreadColor => $this->entity());

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturnCallback(
            function (): Collection {
                $this->collectionsBuilt++;

                $collection = $this->createMock(Collection::class);
                $collection->method('addActiveFilter')->willReturnSelf();
                $collection->method('addDefaultOrder')->willReturnSelf();
                $collection->method('getItems')->willReturnCallback(fn (): array => $this->activeColors);

                return $collection;
            }
        );

        $searchResultsFactory = $this->createMock(ThreadColorSearchResultsInterfaceFactory::class);
        $searchResultsFactory->method('create')
            ->willReturn($this->createMock(ThreadColorSearchResultsInterface::class));

        return new ThreadColorRepository(
            $this->resource,
            $entityFactory,
            $collectionFactory,
            $builder ?? $this->createMock(SearchResultBuilder::class),
            $searchResultsFactory
        );
    }
}
