<?php
/**
 * ThreadColorSearchResultsInterface.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface ThreadColorSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return ThreadColorInterface[]
     */
    public function getItems();

    /**
     * @param ThreadColorInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
