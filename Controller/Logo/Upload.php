<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Controller\Logo;

use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\Side;
use Commerce\Embroidery\Model\Upload\LogoStorage;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * POST — store a customer's embroidery logo.
 */
class Upload implements HttpPostActionInterface
{
    /**
     * Form field names, one per side.
     */
    private const array FILE_KEYS = [
        'left' => 'embroidery_logo_left',
        'right' => 'embroidery_logo_right',
    ];

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LogoStorage $logoStorage,
        private readonly Config $config,
        private readonly LoggerInterface $logger
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

        $uploaded = [];
        $errors = [];

        foreach (Side::cases() as $side) {
            $fileKey = self::FILE_KEYS[$side->value];

            if (!$this->hasFile($fileKey)) {
                continue;
            }

            try {
                $uploaded[$side->value] = $this->logoStorage->store($fileKey)->toArray();
            } catch (LocalizedException $e) {
                // Localised exceptions are written for shoppers, so they are
                // safe to show.
                $errors[$side->value] = (string) $e->getMessage();
            } catch (Throwable $e) {
                $this->logger->error(
                    'Embroidery: a logo upload failed.',
                    ['exception' => $e, 'side' => $side->value]
                );
                $errors[$side->value] = (string) __('The logo could not be uploaded. Please try again.');
            }
        }

        if ($uploaded === [] && $errors === []) {
            return $result->setHttpResponseCode(400)
                ->setData(['success' => false, 'message' => __('No logo image was received.')]);
        }

        return $result->setData([
            'success' => $errors === [],
            'uploaded' => $uploaded,
            'errors' => $errors,
        ]);
    }

    private function hasFile(string $fileKey): bool
    {
        if (!$this->request instanceof HttpRequest) {
            return false;
        }

        $file = $this->request->getFiles($fileKey);

        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }
}
