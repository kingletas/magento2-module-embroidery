<?php
/**
 * DeleteTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Controller\Logo;

use Commerce\Embroidery\Controller\Logo\Delete;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Upload\LogoStorage;
use Commerce\Embroidery\Test\Unit\Fake\ArrayScopeConfig;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    private ?int $status = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var string[] */
    private array $deleteAttempts = [];

    private string $fileName = 'a1b2c3.png';
    private bool $storageDeletes = true;
    private FormKeyValidator&MockObject $formKeyValidator;

    protected function setUp(): void
    {
        $this->status = null;
        $this->data = [];
        $this->deleteAttempts = [];
        $this->fileName = 'a1b2c3.png';
        $this->storageDeletes = true;

        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(true);
    }

    /**
     * Deletion changes state, so it must not be reachable by a GET that an
     * `<img src>` on another site can trigger.
     */
    public function testItOnlyAnswersPosts(): void
    {
        $controller = $this->controller();

        $this->assertInstanceOf(HttpPostActionInterface::class, $controller);
        $this->assertNotInstanceOf(HttpGetActionInterface::class, $controller);
    }

    public function testADeletedLogoIsReportedAsSuccess(): void
    {
        $this->controller()->execute();

        $this->assertTrue($this->data['success']);
        $this->assertSame(['a1b2c3.png'], $this->deleteAttempts);
        $this->assertNull($this->status);
    }

    /**
     * The name never becomes a path here.
     */
    public function testTheParameterIsHandedToStorageAsABareNameAndNeverJoinedToAPath(): void
    {
        $this->fileName = '../../../app/etc/env.php';

        $this->controller()->execute();

        $this->assertSame(['../../../app/etc/env.php'], $this->deleteAttempts);
    }

    /**
     * Nothing in the response names anything on disk: a resolved absolute path
     * hands a prober the server's directory layout for free.
     */
    public function testTheResponseNamesNothingOnDisk(): void
    {
        $this->controller()->execute();

        $this->assertSame(['success', 'message'], array_keys($this->data));
        $this->assertStringNotContainsString('/', (string) $this->data['message']);
    }

    /**
     * An unacceptable name and a file that was never there are reported
     * identically, so the endpoint cannot be used to probe what exists.
     */
    public function testARejectedNameAndAMissingFileAreIndistinguishable(): void
    {
        $this->storageDeletes = false;
        $this->fileName = 'does-not-exist.png';
        $this->controller()->execute();
        $missing = ['success' => $this->data['success'], 'message' => (string) $this->data['message']];

        $this->fileName = '../../../app/etc/env.php';
        $this->controller()->execute();

        $this->assertSame(
            $missing,
            ['success' => $this->data['success'], 'message' => (string) $this->data['message']]
        );
    }

    /**
     * An empty name is answered before storage is asked, so the endpoint costs
     * nothing to a client that posts nothing.
     */
    public function testAnEmptyNameIsRefusedWithoutTouchingStorage(): void
    {
        $this->fileName = '';

        $this->controller()->execute();

        $this->assertFalse($this->data['success']);
        $this->assertSame([], $this->deleteAttempts);
    }

    public function testARejectedFormKeyIsForbiddenAndDeletesNothing(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(false);

        $this->controller()->execute();

        $this->assertSame(403, $this->status);
        $this->assertFalse($this->data['success']);
        $this->assertSame([], $this->deleteAttempts);
    }

    public function testADisabledFeatureIsNotFoundAndDeletesNothing(): void
    {
        $this->controller(enabled: false)->execute();

        $this->assertSame(404, $this->status);
        $this->assertSame([], $this->deleteAttempts);
    }

    /**
     * The config check comes before the form key, so a switched-off feature
     * validates nothing.
     */
    public function testADisabledFeatureIsRefusedWithoutValidatingAnything(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->expects($this->never())->method('validate');

        $this->controller(enabled: false)->execute();
    }

    private function controller(bool $enabled = true): Delete
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn (): string => $this->fileName);

        $storage = $this->createMock(LogoStorage::class);
        $storage->method('delete')->willReturnCallback(
            function (string $fileName): bool {
                $this->deleteAttempts[] = $fileName;

                return $this->storageDeletes;
            }
        );

        $config = new Config(
            new ArrayScopeConfig(['test_embroidery/general/enabled' => $enabled ? '1' : '0']),
            'test_embroidery'
        );

        return new Delete($request, $this->jsonFactory(), $this->formKeyValidator, $storage, $config);
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
