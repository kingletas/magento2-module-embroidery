<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Controller\Options;

use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\OptionsProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * GET — the embroidery form's option lists and prices.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly OptionsProvider $optionsProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)->setData(['enabled' => false]);
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
        } catch (Throwable) {
            $storeId = null;
        }

        return $result->setData([
            'enabled' => true,
        ] + $this->optionsProvider->getFormOptions($storeId));
    }
}
