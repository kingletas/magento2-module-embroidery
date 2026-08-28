<?php
/**
 * Config.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model;

use Commerce\Foundation\Model\Config\ModuleConfig;

/**
 * Typed access to this module's settings.
 */
class Config extends ModuleConfig
{
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('general/enabled', $storeId);
    }

    public function getTermsCmsBlockId(?int $storeId = null): string
    {
        return $this->getString('general/terms_cms_block', '', $storeId);
    }

    public function getUploadInfoCmsBlockId(?int $storeId = null): string
    {
        return $this->getString('general/upload_info_cms_block', '', $storeId);
    }

    public function getUploadSizeMessage(?int $storeId = null): string
    {
        return $this->getString('general/upload_size_message', '', $storeId);
    }

    // ---------------------------------------------------------------- Charges

    public function getTextLinePrice(int $line, ?int $storeId = null): float
    {
        return $this->getFloat(sprintf('charges/text_line_%d_price', $line), 0.0, $storeId);
    }

    public function getStockLogoPrice(?int $storeId = null): float
    {
        return $this->getFloat('charges/stock_logo_price', 0.0, $storeId);
    }

    public function getCustomLogoPrice(?int $storeId = null): float
    {
        return $this->getFloat('charges/custom_logo_price', 0.0, $storeId);
    }

    public function getCustomLogoFee(?int $storeId = null): float
    {
        return $this->getFloat('charges/custom_logo_fee', 0.0, $storeId);
    }

    // ----------------------------------------------------------- Notification

    public function isNotificationEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('notification/enabled', $storeId);
    }

    public function getNotificationTemplate(?int $storeId = null): string
    {
        return $this->getString('notification/template', 'commerce_embroidery_order', $storeId);
    }

    public function getSenderIdentity(?int $storeId = null): string
    {
        return $this->getString('notification/sender_identity', 'general', $storeId);
    }

    /**
     * @return string[]
     */
    public function getNotificationRecipients(?int $storeId = null): array
    {
        return $this->getList('notification/recipients', $storeId);
    }
}
