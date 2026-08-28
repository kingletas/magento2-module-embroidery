<?php
/**
 * PersonalisedCartLine.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Behaviour\Fake;

use Commerce\Embroidery\Test\Unit\Fake\CartLine;
use Magento\Framework\DataObject;

/**
 * A cart line that answers `getOptionByCode()`, the way a stored line does.
 */
final class PersonalisedCartLine extends CartLine
{
    /** @var array<string, DataObject> Options the line already carries. */
    private array $options = [];

    /**
     * Store an option on the line, as a previous request would have.
     */
    public function withOption(string $code, string $value): self
    {
        $this->options[$code] = new DataObject(['code' => $code, 'value' => $value]);

        return $this;
    }

    /**
     * @param  string $code
     * @return DataObject|null
     */
    public function getOptionByCode($code)
    {
        return $this->options[$code] ?? null;
    }

    /**
     * Options added during pricing land here too, so a second pass reads what
     * the first wrote.
     *
     * @param  DataObject|array<string, mixed> $option
     * @return $this
     */
    public function addOption($option)
    {
        parent::addOption($option);

        $data = $option instanceof DataObject ? $option->getData() : (array) $option;
        $code = (string) ($data['code'] ?? '');

        if ($code !== '') {
            $this->options[$code] = new DataObject($data);
        }

        return $this;
    }
}
