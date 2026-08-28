<?php
/**
 * ThreadColorRepository.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model\Repository;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\Data\ThreadColorInterfaceFactory;
use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterface;
use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterfaceFactory;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor\CollectionFactory;
use Commerce\Foundation\Model\Repository\SearchResultBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Throwable;

class ThreadColorRepository implements ThreadColorRepositoryInterface
{
    /** @var array<string, ThreadColorInterface> */
    private array $byCode = [];

    /** @var ThreadColorInterface[]|null */
    private ?array $active = null;

    public function __construct(
        private readonly ThreadColorResource $resource,
        private readonly ThreadColorInterfaceFactory $threadColorFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultBuilder $searchResultBuilder,
        private readonly ThreadColorSearchResultsInterfaceFactory $searchResultsFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getById(int $threadColorId): ThreadColorInterface
    {
        $threadColor = $this->threadColorFactory->create();
        $this->resource->load($threadColor, $threadColorId);

        if ($threadColor->getThreadColorId() === null) {
            throw NoSuchEntityException::singleField(ThreadColorInterface::THREAD_COLOR_ID, $threadColorId);
        }

        return $threadColor;
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $code): ThreadColorInterface
    {
        $found = $this->getByCodes([$code]);

        if (!isset($found[$code])) {
            throw NoSuchEntityException::singleField(ThreadColorInterface::CODE, $code);
        }

        return $found[$code];
    }

    /**
     * @inheritDoc
     */
    public function getByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn ($code): string => trim((string) $code), $codes),
            static fn (string $code): bool => $code !== ''
        )));

        if ($codes === []) {
            return [];
        }

        $resolved = [];
        $missing = [];

        foreach ($codes as $code) {
            if (isset($this->byCode[$code])) {
                $resolved[$code] = $this->byCode[$code];
                continue;
            }

            $missing[] = $code;
        }

        if ($missing === []) {
            return $resolved;
        }

        // One query for every code still unknown, rather than one per code.
        foreach ($this->resource->loadByCodes($missing) as $code => $row) {
            $threadColor = $this->threadColorFactory->create();
            $threadColor->setData($row);
            $resolved[$code] = $this->byCode[$code] = $threadColor;
        }

        return $resolved;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ThreadColorSearchResultsInterface
    {
        /** @var ThreadColorSearchResultsInterface $results */
        $results = $this->searchResultBuilder->build(
            $searchCriteria,
            $this->collectionFactory->create(),
            $this->searchResultsFactory->create()
        );

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getActive(): array
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter()->addDefaultOrder();

        $this->active = array_values($collection->getItems());

        // Populate the code index too: the storefront renders the swatch list
        // and then immediately looks colours up by code to price them.
        foreach ($this->active as $threadColor) {
            $this->byCode[$threadColor->getCode()] = $threadColor;
        }

        return $this->active;
    }

    /**
     * @inheritDoc
     */
    public function save(ThreadColorInterface $threadColor): ThreadColorInterface
    {
        try {
            $this->resource->save($threadColor);
        } catch (Throwable $e) {
            throw new CouldNotSaveException(__('The thread colour could not be saved.'), $e);
        }

        $this->forgetCaches();

        return $threadColor;
    }

    /**
     * @inheritDoc
     */
    public function delete(ThreadColorInterface $threadColor): void
    {
        try {
            $this->resource->delete($threadColor);
        } catch (Throwable $e) {
            throw new CouldNotDeleteException(__('The thread colour could not be deleted.'), $e);
        }

        $this->forgetCaches();
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $threadColorId): void
    {
        $this->delete($this->getById($threadColorId));
    }

    /**
     * A write invalidates both memoised views rather than serving stale colours
     * for the request.
     */
    private function forgetCaches(): void
    {
        $this->byCode = [];
        $this->active = null;
    }
}
