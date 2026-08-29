<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Api;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface ThreadColorRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $threadColorId): ThreadColorInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): ThreadColorInterface;

    /**
     * Look up many codes at once.
     *
     * @param string[] $codes
     * @return array<string, ThreadColorInterface> Keyed by code; misses omitted.
     */
    public function getByCodes(array $codes): array;

    public function getList(SearchCriteriaInterface $searchCriteria): ThreadColorSearchResultsInterface;

    /**
     * Every active colour, in sort order. Cached per request.
     *
     * @return ThreadColorInterface[]
     */
    public function getActive(): array;

    /**
     * @throws CouldNotSaveException
     */
    public function save(ThreadColorInterface $threadColor): ThreadColorInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(ThreadColorInterface $threadColor): void;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $threadColorId): void;
}
