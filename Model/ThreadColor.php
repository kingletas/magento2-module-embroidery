<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Magento requires the _construct() initialiser, which trips PHPMD naming.
 *
 * @SuppressWarnings("PHPMD.CamelCaseMethodName")
 */
class ThreadColor extends AbstractModel implements ThreadColorInterface
{
    protected function _construct(): void
    {
        $this->_init(ThreadColorResource::class);
    }

    public function getThreadColorId(): ?int
    {
        $value = $this->getData(self::THREAD_COLOR_ID);

        return $value === null ? null : (int) $value;
    }

    public function setThreadColorId(?int $id): ThreadColorInterface
    {
        return $this->setData(self::THREAD_COLOR_ID, $id);
    }

    public function getCode(): string
    {
        return (string) $this->getData(self::CODE);
    }

    public function setCode(string $code): ThreadColorInterface
    {
        return $this->setData(self::CODE, $code);
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function setName(string $name): ThreadColorInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getHexCode(): string
    {
        return (string) $this->getData(self::HEX_CODE);
    }

    public function setHexCode(string $hexCode): ThreadColorInterface
    {
        return $this->setData(self::HEX_CODE, $hexCode);
    }

    public function getPantoneCode(): ?string
    {
        $value = $this->getData(self::PANTONE_CODE);

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function setPantoneCode(?string $pantoneCode): ThreadColorInterface
    {
        return $this->setData(self::PANTONE_CODE, $pantoneCode);
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): ThreadColorInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): ThreadColorInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $createdAt): ThreadColorInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $updatedAt): ThreadColorInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
