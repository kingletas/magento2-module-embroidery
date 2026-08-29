<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Wiring;

use Commerce\Foundation\Test\Support\ModuleWiringTestCase;

/**
 * This module's `etc/` against the code it names.
 */
class WiringTest extends ModuleWiringTestCase
{
    protected function moduleDir(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @inheritDoc
     */
    protected function settingsWithNoDefault(): array
    {
        return [
            // Both are CMS block identifiers chosen from the store's own
            // blocks.
            'commerce_embroidery/general/terms_cms_block',
            'commerce_embroidery/general/upload_info_cms_block',
            // Who gets told.
            'commerce_embroidery/notification/recipients',
        ];
    }
}
