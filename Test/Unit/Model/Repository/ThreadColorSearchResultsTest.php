<?php
/**
 * ThreadColorSearchResultsTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Repository;

use Commerce\Embroidery\Api\Data\ThreadColorSearchResultsInterface;
use Commerce\Embroidery\Model\Repository\ThreadColorSearchResults;
use Magento\Framework\Api\SearchResults;
use PHPUnit\Framework\TestCase;

class ThreadColorSearchResultsTest extends TestCase
{
    /**
     * `getList()` declares a real return type, so the generic `SearchResults`
     * is a TypeError.
     */
    public function testItSatisfiesTheTypedReturnOfGetList(): void
    {
        $results = new ThreadColorSearchResults();

        self::assertInstanceOf(ThreadColorSearchResultsInterface::class, $results);
        self::assertInstanceOf(SearchResults::class, $results);
    }

    public function testItCarriesItemsAndATotalCountLikeAnySearchResult(): void
    {
        $results = new ThreadColorSearchResults();
        $results->setItems(['a', 'b']);
        $results->setTotalCount(2);

        self::assertSame(['a', 'b'], $results->getItems());
        self::assertSame(2, $results->getTotalCount());
    }
}
