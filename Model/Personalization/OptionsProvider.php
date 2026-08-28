<?php
/**
 * OptionsProvider.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model\Personalization;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Api\ThreadColorRepositoryInterface;
use Commerce\Embroidery\Model\Config;

/**
 * The choices offered on the embroidery form.
 */
class OptionsProvider
{
    /**
     * @param array<string, string> $fontStyles    Value => label.
     * @param array<string, string> $logoLocations Value => label.
     * @param array<string, string> $logoTypes     Value => label.
     */
    public function __construct(
        private readonly ThreadColorRepositoryInterface $threadColorRepository,
        private readonly Config $config,
        private readonly array $fontStyles = [],
        private readonly array $logoLocations = [],
        private readonly array $logoTypes = []
    ) {
    }

    /**
     * Everything the storefront form needs, in one payload.
     *
     * @return array<string, mixed>
     */
    public function getFormOptions(?int $storeId = null): array
    {
        return [
            'sides' => array_map(
                static fn (Side $side): array => ['value' => $side->value, 'label' => $side->label()],
                Side::cases()
            ),
            'font_styles' => $this->toOptionList($this->fontStyles),
            'logo_locations' => $this->toOptionList($this->logoLocations),
            'logo_types' => $this->toOptionList($this->logoTypes),
            'thread_colors' => $this->getThreadColorOptions(),
            'prices' => [
                'text_line_1' => $this->config->getTextLinePrice(1, $storeId),
                'text_line_2' => $this->config->getTextLinePrice(2, $storeId),
                'text_line_3' => $this->config->getTextLinePrice(3, $storeId),
                'stock_logo' => $this->config->getStockLogoPrice($storeId),
                'custom_logo' => $this->config->getCustomLogoPrice($storeId),
                'custom_logo_fee' => $this->config->getCustomLogoFee($storeId),
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, hex: string}>
     */
    public function getThreadColorOptions(): array
    {
        return array_map(
            static fn (ThreadColorInterface $color): array => [
                'value' => $color->getCode(),
                'label' => $color->getName(),
                'hex' => $color->getHexCode(),
            ],
            $this->threadColorRepository->getActive()
        );
    }

    /**
     * @param array<string, string> $map
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function toOptionList(array $map): array
    {
        $options = [];

        foreach ($map as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => (string) $label];
        }

        return $options;
    }
}
