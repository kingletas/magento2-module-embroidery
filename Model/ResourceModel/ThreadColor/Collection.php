<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\ResourceModel\ThreadColor;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Commerce\Embroidery\Model\ThreadColor;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Magento requires the _construct() initialiser, which trips PHPMD naming.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Collection extends AbstractCollection
{

    /**
     * Set through the setter rather than by redeclaring the property.
     */
    protected function _construct(): void
    {
        $this->_setIdFieldName(ThreadColorInterface::THREAD_COLOR_ID);
        $this->_init(ThreadColor::class, ThreadColorResource::class);
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter(ThreadColorInterface::IS_ACTIVE, 1);

        return $this;
    }

    public function addDefaultOrder(): self
    {
        $this->setOrder(ThreadColorInterface::SORT_ORDER, self::SORT_ORDER_ASC);
        // Secondary sort so colours sharing a sort order have a stable
        // presentation rather than whatever the storage engine returns.
        $this->setOrder(ThreadColorInterface::NAME, self::SORT_ORDER_ASC);

        return $this;
    }
}
