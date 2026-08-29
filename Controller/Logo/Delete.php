<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Controller\Logo;

use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Upload\LogoStorage;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * POST — remove a previously uploaded embroidery logo.
 */
class Delete implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LogoStorage $logoStorage,
        private readonly Config $config
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)
                ->setData(['success' => false, 'message' => __('Embroidery is not available.')]);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(403)
                ->setData(['success' => false, 'message' => __('Your session has expired. Please reload the page.')]);
        }

        $fileName = (string) $this->request->getParam('file_name', '');

        // No path is constructed here, and none is returned.
        $deleted = $fileName !== '' && $this->logoStorage->delete($fileName);

        return $result->setData([
            'success' => $deleted,
            'message' => $deleted
                ? __('The logo has been removed.')
                : __('That logo could not be removed.'),
        ]);
    }
}
