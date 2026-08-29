<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

/**
 * What the shopper chose for one chest.
 */
class SideSelection
{
    public const string LOGO_NONE = 'none';
    public const string LOGO_STOCK = 'stock';
    public const string LOGO_CUSTOM = 'custom';

    /**
     * Every accepted logo type.
     *
     * @var string[]
     */
    public const array LOGO_TYPES = [self::LOGO_NONE, self::LOGO_STOCK, self::LOGO_CUSTOM];

    /**
     * @param string[] $textLines Embroidered text, in order; empty entries dropped.
     */
    public function __construct(
        public readonly Side $side,
        public readonly array $textLines = [],
        public readonly ?string $fontStyle = null,
        public readonly ?string $threadColorCode = null,
        public readonly string $logoType = self::LOGO_NONE,
        public readonly ?string $logoFileName = null,
        public readonly ?string $logoLocation = null
    ) {
    }

    public function hasText(): bool
    {
        return $this->textLines !== [];
    }

    public function hasLogo(): bool
    {
        return $this->logoType !== self::LOGO_NONE;
    }

    public function isEmpty(): bool
    {
        return !$this->hasText() && !$this->hasLogo();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'side' => $this->side->value,
            'text_lines' => $this->textLines,
            'font_style' => $this->fontStyle,
            'thread_color' => $this->threadColorCode,
            'logo_type' => $this->logoType,
            'logo_file_name' => $this->logoFileName,
            'logo_location' => $this->logoLocation,
        ];
    }
}
