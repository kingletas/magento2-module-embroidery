<?php
/**
 * FieldMapExportMapper.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model\Export;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\OrderExportMapperInterface;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\Charge\ChargeBreakdown;
use Commerce\Embroidery\Model\Charge\ChargeCalculator;
use Commerce\Embroidery\Model\Personalization\EmbroiderySelection;
use Commerce\Embroidery\Model\Personalization\SideSelection;

/**
 * Renders a selection using a field-name map supplied from di.xml.
 */
class FieldMapExportMapper implements OrderExportMapperInterface
{
    /**
     * @param string                $totalField         Field carrying the total surcharge.
     * @param string                $detailsField       Field carrying the itemised detail list.
     * @param array<string, string> $sideFieldTemplates sprintf templates taking the capitalised side.
     * @param array<string, string> $detailLabels       sprintf templates for human-readable labels.
     * @param array<string, float>  $staticFields       Fields always emitted with a fixed value.
     */
    public function __construct(
        private readonly ThreadColorRepositoryInterface $threadColorRepository,
        private readonly string $totalField = 'MonogramPrice',
        private readonly string $detailsField = 'MonogramDetails',
        private readonly array $sideFieldTemplates = [
            'logo_price' => '%sLogoPrice',
            'line_1' => '%sLineOne',
            'line_2' => '%sLineTwo',
            'line_3' => '%sLineThree',
        ],
        private readonly array $detailLabels = [
            'font_style' => '%s Chest Embroidery Font Style',
            'thread_color' => '%s Chest Embroidery Thread Color',
            'logo_type' => '%s Chest Logo Type',
            'logo_location' => '%s Chest Logo Location',
            'logo_upload' => '%s Chest Upload',
            'text_line' => '%s Chest Embroidered Text %d',
        ],
        private readonly array $staticFields = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function map(EmbroiderySelection $selection, ChargeBreakdown $charges): array
    {
        $payload = $this->staticFields;
        $payload[$this->totalField] = $charges->total;
        $payload[$this->detailsField] = null;

        if ($selection->isEmpty()) {
            return $payload;
        }

        $threadColors = $this->resolveThreadColors($selection);
        $details = [];

        foreach ($selection->all() as $sideSelection) {
            $label = $sideSelection->side->label();
            $prefix = $sideSelection->side->value . '_';

            $payload[$this->sideField('logo_price', $label)] =
                $charges->get($prefix . ChargeCalculator::COMPONENT_STOCK_LOGO)
                + $charges->get($prefix . ChargeCalculator::COMPONENT_CUSTOM_LOGO);

            foreach ([1, 2, 3] as $lineNumber) {
                $payload[$this->sideField('line_' . $lineNumber, $label)] =
                    isset($sideSelection->textLines[$lineNumber])
                        ? $charges->get($prefix . ChargeCalculator::COMPONENT_TEXT)
                        : 0.0;
            }

            $details += $this->mapSideDetails($sideSelection, $threadColors);
        }

        // Null rather than an empty array: the receiving system distinguishes
        // the two.
        $payload[$this->detailsField] = $details === [] ? null : $details;

        return $payload;
    }

    /**
     * @param array<string, ThreadColorInterface> $threadColors
     *
     * @return array<string, array{label: string, value: string}>
     */
    private function mapSideDetails(SideSelection $selection, array $threadColors): array
    {
        $label = $selection->side->label();
        $prefix = $selection->side->value;
        $details = [];

        foreach ($selection->textLines as $lineNumber => $text) {
            $details[sprintf('%s_text_%d', $prefix, $lineNumber)] = [
                'label' => sprintf($this->detailLabels['text_line'], $label, $lineNumber),
                'value' => $text,
            ];
        }

        if ($selection->fontStyle !== null) {
            $details[$prefix . '_font_style'] = [
                'label' => sprintf($this->detailLabels['font_style'], $label),
                'value' => $selection->fontStyle,
            ];
        }

        if ($selection->threadColorCode !== null) {
            $threadColor = $threadColors[$selection->threadColorCode] ?? null;

            $details[$prefix . '_thread_color'] = [
                'label' => sprintf($this->detailLabels['thread_color'], $label),
                // Name plus id when the colour is known, so the fulfiller can
                // match on either.
                'value' => $threadColor !== null
                    ? sprintf('%s/%d', $threadColor->getName(), $threadColor->getThreadColorId())
                    : $selection->threadColorCode,
            ];
        }

        if ($selection->hasLogo()) {
            $details[$prefix . '_logo_type'] = [
                'label' => sprintf($this->detailLabels['logo_type'], $label),
                'value' => $selection->logoType,
            ];

            if ($selection->logoFileName !== null) {
                $details[$prefix . '_logo_upload'] = [
                    'label' => sprintf($this->detailLabels['logo_upload'], $label),
                    'value' => $selection->logoFileName,
                ];
            }

            if ($selection->logoLocation !== null) {
                $details[$prefix . '_logo_location'] = [
                    'label' => sprintf($this->detailLabels['logo_location'], $label),
                    'value' => $selection->logoLocation,
                ];
            }
        }

        return $details;
    }

    /**
     * @return array<string, ThreadColorInterface>
     */
    private function resolveThreadColors(EmbroiderySelection $selection): array
    {
        $codes = [];

        foreach ($selection->all() as $sideSelection) {
            if ($sideSelection->threadColorCode !== null) {
                $codes[] = $sideSelection->threadColorCode;
            }
        }

        return $codes === [] ? [] : $this->threadColorRepository->getByCodes($codes);
    }

    private function sideField(string $key, string $sideLabel): string
    {
        $template = $this->sideFieldTemplates[$key] ?? ('%s' . ucfirst($key));

        return sprintf($template, $sideLabel);
    }
}
