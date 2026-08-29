<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Api\Data;

/**
 * A thread colour a shopper can choose.
 */
interface ThreadColorInterface
{
    public const string THREAD_COLOR_ID = 'thread_color_id';
    public const string CODE = 'code';
    public const string NAME = 'name';
    public const string HEX_CODE = 'hex_code';
    public const string PANTONE_CODE = 'pantone_code';
    public const string SORT_ORDER = 'sort_order';
    public const string IS_ACTIVE = 'is_active';
    public const string CREATED_AT = 'created_at';
    public const string UPDATED_AT = 'updated_at';

    public function getThreadColorId(): ?int;

    public function setThreadColorId(?int $id): self;

    /**
     * Stable machine identifier, e.g. "ceil-blue".
     */
    public function getCode(): string;

    public function setCode(string $code): self;

    public function getName(): string;

    public function setName(string $name): self;

    public function getHexCode(): string;

    public function setHexCode(string $hexCode): self;

    public function getPantoneCode(): ?string;

    public function setPantoneCode(?string $pantoneCode): self;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): self;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;
}
