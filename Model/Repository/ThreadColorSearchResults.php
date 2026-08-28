<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model\Repository;

use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Typed search results for thread colours.
 */
class ThreadColorSearchResults extends SearchResults implements ThreadColorSearchResultsInterface
{
}
