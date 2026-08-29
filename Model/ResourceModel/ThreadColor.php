<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\ResourceModel;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Magento requires the _construct() initialiser, which trips PHPMD naming.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class ThreadColor extends AbstractDb
{
    public const string TABLE_NAME = 'commerce_embroidery_thread_color';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, ThreadColorInterface::THREAD_COLOR_ID);
    }

    /**
     * Insert or update many colours in one statement.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function upsertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return (int) $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            $rows,
            [
                ThreadColorInterface::NAME,
                ThreadColorInterface::HEX_CODE,
                ThreadColorInterface::PANTONE_CODE,
                ThreadColorInterface::SORT_ORDER,
                ThreadColorInterface::IS_ACTIVE,
            ]
        );
    }

    /**
     * @param string[] $codes
     *
     * @return array<string, array<string, mixed>> Keyed by code.
     */
    public function loadByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes)));

        if ($codes === []) {
            return [];
        }

        $connection = $this->getConnection();

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getMainTable())
                ->where(ThreadColorInterface::CODE . ' IN (?)', $codes)
        );

        $byCode = [];

        foreach ($rows as $row) {
            $byCode[(string) $row[ThreadColorInterface::CODE]] = $row;
        }

        return $byCode;
    }
}
