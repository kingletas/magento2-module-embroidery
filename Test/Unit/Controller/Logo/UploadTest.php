<?php
/**
 * UploadTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Controller\Logo;

use Commerce\Embroidery\Controller\Logo\Upload;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Upload\LogoStorage;
use Commerce\Embroidery\Model\Upload\UploadedLogo;
use Commerce\Embroidery\Test\Unit\Fake\ArrayScopeConfig;
use Commerce\Embroidery\Test\Unit\Fake\RecordingLogger;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UploadTest extends TestCase
{
    private const LEFT_KEY = 'embroidery_logo_left';
    private const RIGHT_KEY = 'embroidery_logo_right';

    private ?int $status = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, array<string, mixed>> Form field => $_FILES entry. */
    private array $files = [];

    /** @var array<string, \Throwable> Form field => what storing it throws. */
    private array $storeFailures = [];

    /** @var string[] */
    private array $stored = [];

    private RecordingLogger $logger;
    private FormKeyValidator&MockObject $formKeyValidator;

    protected function setUp(): void
    {
        $this->status = null;
        $this->data = [];
        $this->storeFailures = [];
        $this->stored = [];
        $this->logger = new RecordingLogger();
        $this->files = [self::LEFT_KEY => ['error' => UPLOAD_ERR_OK, 'name' => 'logo.png']];

        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(true);
    }

    /**
     * `isXmlHttpRequest()` reads a header any client can set; POST-only plus a
     * form key does not.
     */
    public function testItOnlyAnswersPosts(): void
    {
        $controller = $this->controller();

        $this->assertInstanceOf(HttpPostActionInterface::class, $controller);
        $this->assertNotInstanceOf(HttpGetActionInterface::class, $controller);
    }

    public function testAStoredLogoIsReportedUnderItsSide(): void
    {
        $this->controller()->execute();

        $this->assertTrue($this->data['success']);
        $this->assertSame(['left'], array_keys($this->data['uploaded']));
        $this->assertSame('a1b2c3.png', $this->data['uploaded']['left']['file_name']);
        $this->assertSame([], $this->data['errors']);
    }

    public function testBothSidesCanBeUploadedInOneRequest(): void
    {
        $this->files[self::RIGHT_KEY] = ['error' => UPLOAD_ERR_OK, 'name' => 'logo2.png'];

        $this->controller()->execute();

        $this->assertSame(['left', 'right'], array_keys($this->data['uploaded']));
        $this->assertSame([self::LEFT_KEY, self::RIGHT_KEY], $this->stored);
    }

    /**
     * The outcome per side is kept in locals, not instance properties.
     */
    public function testOneSideFailingLeavesTheOthersResultIntact(): void
    {
        $this->files[self::RIGHT_KEY] = ['error' => UPLOAD_ERR_OK, 'name' => 'logo2.png'];
        $this->storeFailures[self::RIGHT_KEY] = new LocalizedException(__('That file is too large.'));

        $this->controller()->execute();

        $this->assertFalse($this->data['success']);
        $this->assertSame(['left'], array_keys($this->data['uploaded']));
        $this->assertSame(['right'], array_keys($this->data['errors']));
        $this->assertSame('That file is too large.', (string) $this->data['errors']['right']);
    }

    /**
     * A localised exception is written for shoppers, so it is safe to show.
     */
    public function testAShopperFacingFailureIsShownAsWritten(): void
    {
        $this->storeFailures[self::LEFT_KEY] = new LocalizedException(__('Only PNG and JPEG are accepted.'));

        $this->controller()->execute();

        $this->assertSame('Only PNG and JPEG are accepted.', (string) $this->data['errors']['left']);
        $this->assertSame([], $this->logger->errors);
    }

    /**
     * Anything else is not: `$e->getMessage()` on a filesystem failure carries
     * server paths and internal state into the browser.
     */
    public function testAnInternalFailureIsLoggedAndReportedAsAGenericMessage(): void
    {
        $this->storeFailures[self::LEFT_KEY] = new RuntimeException('/var/www/pub/media/embroidery not writable');

        $this->controller()->execute();

        $this->assertStringNotContainsString('/var/www', (string) $this->data['errors']['left']);
        $this->assertCount(1, $this->logger->errors);
    }

    /**
     * A form posted with no file at all is a client mistake, and answering 200
     * would have the browser report a successful upload of nothing.
     */
    public function testARequestCarryingNoFileIsABadRequest(): void
    {
        $this->files = [];

        $this->controller()->execute();

        $this->assertSame(400, $this->status);
        $this->assertFalse($this->data['success']);
    }

    /**
     * The browser posts an empty file input for the side the shopper left
     * alone; storing it would create a zero-byte logo.
     */
    public function testAnEmptyFileInputIsNotAnUpload(): void
    {
        $this->files = [self::LEFT_KEY => ['error' => UPLOAD_ERR_NO_FILE]];

        $this->controller()->execute();

        $this->assertSame(400, $this->status);
        $this->assertSame([], $this->stored);
    }

    public function testARejectedFormKeyIsForbiddenAndStoresNothing(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(false);

        $this->controller()->execute();

        $this->assertSame(403, $this->status);
        $this->assertSame([], $this->stored);
    }

    public function testADisabledFeatureIsNotFoundAndStoresNothing(): void
    {
        $this->controller(enabled: false)->execute();

        $this->assertSame(404, $this->status);
        $this->assertSame([], $this->stored);
    }

    public function testADisabledFeatureIsRefusedWithoutValidatingAnything(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->expects($this->never())->method('validate');

        $this->controller(enabled: false)->execute();
    }

    private function controller(bool $enabled = true): Upload
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFiles')->willReturnCallback(
            fn (string $key = '') => $this->files[$key] ?? null
        );

        $storage = $this->createMock(LogoStorage::class);
        $storage->method('store')->willReturnCallback(
            function (string $fileKey): UploadedLogo {
                if (isset($this->storeFailures[$fileKey])) {
                    throw $this->storeFailures[$fileKey];
                }

                $this->stored[] = $fileKey;

                return new UploadedLogo('a1b2c3.png', 'embroidery/logo/a/1/a1b2c3.png');
            }
        );

        $config = new Config(
            new ArrayScopeConfig(['test_embroidery/general/enabled' => $enabled ? '1' : '0']),
            'test_embroidery'
        );

        return new Upload(
            $request,
            $this->jsonFactory(),
            $this->formKeyValidator,
            $storage,
            $config,
            $this->logger
        );
    }

    private function jsonFactory(): JsonFactory
    {
        $json = $this->createMock(Json::class);
        $json->method('setHttpResponseCode')->willReturnCallback(function (int $code) use (&$json): Json {
            $this->status = $code;

            return $json;
        });
        $json->method('setData')->willReturnCallback(function ($data) use (&$json): Json {
            $this->data = (array) $data;

            return $json;
        });

        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($json);

        return $factory;
    }
}
