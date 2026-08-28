<?php
/**
 * SelectionMapper.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

/**
 * Builds selections from the storefront's posted structure and from stored
 * payloads, discarding anything unusable.
 */
class SelectionMapper
{
    /**
     * Line numbers the storefront posts, in order.
     *
     * @var int[]
     */
    private const array TEXT_LINE_NUMBERS = [1, 2, 3];

    /**
     * @param array<string, mixed> $data Keyed by side value.
     */
    public function selectionFromArray(array $data): EmbroiderySelection
    {
        $selections = [];

        foreach (Side::cases() as $side) {
            $sideData = $data[$side->value] ?? null;

            if (is_array($sideData)) {
                $selections[] = $this->sideFromArray($side, $sideData);
            }
        }

        return new EmbroiderySelection($selections);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sideFromArray(Side $side, array $data): SideSelection
    {
        $logoType = (string) ($data['logo_type'] ?? SideSelection::LOGO_NONE);

        return new SideSelection(
            $side,
            $this->textLines($data),
            $this->nullableString($data['font_style'] ?? null),
            $this->nullableString($data['thread_color'] ?? null),
            in_array($logoType, SideSelection::LOGO_TYPES, true) ? $logoType : SideSelection::LOGO_NONE,
            $this->nullableString($data['logo_file_name'] ?? null),
            $this->nullableString($data['logo_location'] ?? null)
        );
    }

    /**
     * Text lines, from either shape they arrive in.
     *
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function textLines(array $data): array
    {
        $keyed = $data['text_lines'] ?? null;
        $keyed = is_array($keyed) ? $keyed : null;
        $lines = [];

        foreach (self::TEXT_LINE_NUMBERS as $lineNumber) {
            $line = $keyed === null
                ? $this->nullableString($data['text_line_' . $lineNumber] ?? null)
                : $this->nullableString($keyed[$lineNumber] ?? null);

            if ($line !== null) {
                // Keyed by line number rather than appended, so line 3 without
                // line 2 does not silently become line 2 and get charged at the
                // wrong rate.
                $lines[$lineNumber] = $line;
            }
        }

        return $lines;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
