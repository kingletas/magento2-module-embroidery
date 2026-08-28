<?php
/**
 * IndexTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Controller\Options;

use Commerce\Embroidery\Controller\Options\Index;
use Commerce\Embroidery\Model\Config;
use Commerce\Embroidery\Model\Personalization\OptionsProvider;
use Commerce\Embroidery\Test\Unit\Fake\ArrayScopeConfig;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private ?int $status = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var int[] */
    private array $requestedScopes = [];

    private ?StoreManagerInterface $storeManager = null;

    protected function setUp(): void
    {
        $this->status = null;
        $this->data = [];
        $this->requestedScopes = [];
        $this->storeManager = null;
    }

    /**
     * Read-only and cacheable, so GET is declared and no form key is demanded.
     */
    public function testItOnlyAnswersGets(): void
    {
        $this->assertInstanceOf(HttpGetActionInterface::class, $this->controller());
    }

    public function testThePayloadIsTheFormOptionsPlusAnEnabledFlag(): void
    {
        $this->controller()->execute();

        $this->assertTrue($this->data['enabled']);
        $this->assertArrayHasKey('thread_colors', $this->data);
        $this->assertArrayHasKey('prices', $this->data);
        $this->assertNull($this->status);
    }

    /**
     * Prices are store-scoped.
     */
    public function testThePricesAreResolvedForTheCurrentStore(): void
    {
        $this->storeManager = $this->storeManagerFor(2);

        $this->controller()->execute();

        $this->assertSame([2], $this->requestedScopes);
    }

    /**
     * A store that cannot be resolved is not a reason to refuse the form; the
     * default scope's prices are a usable answer.
     */
    public function testAnUnresolvableStoreFallsBackToTheDefaultScope(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')
            ->willThrowException(new NoSuchEntityException(__('No such store.')));
        $this->storeManager = $storeManager;

        $this->controller()->execute();

        $this->assertSame([0], $this->requestedScopes);
        $this->assertTrue($this->data['enabled']);
    }

    /**
     * A disabled feature answers as an unregistered route, giving nothing away.
     */
    public function testADisabledFeatureIsNotFoundAndCarriesNoOptions(): void
    {
        $this->controller(enabled: false)->execute();

        $this->assertSame(404, $this->status);
        $this->assertSame(['enabled' => false], $this->data);
    }

    /**
     * The flag is set on the response rather than merged, so no option list can
     * overrule it.
     */
    public function testTheEnabledFlagIsNotSomethingTheOptionListCanOverride(): void
    {
        $provider = $this->createMock(OptionsProvider::class);
        $provider->method('getFormOptions')->willReturn(['enabled' => false, 'sides' => []]);

        $this->controller(provider: $provider)->execute();

        $this->assertTrue($this->data['enabled']);
    }

    private function controller(bool $enabled = true, ?OptionsProvider $provider = null): Index
    {
        if ($provider === null) {
            $provider = $this->createMock(OptionsProvider::class);
            $provider->method('getFormOptions')->willReturnCallback(
                function (?int $storeId = null): array {
                    $this->requestedScopes[] = (int) $storeId;

                    return ['sides' => [], 'thread_colors' => [], 'prices' => []];
                }
            );
        }

        $config = new Config(
            new ArrayScopeConfig(['test_embroidery/general/enabled' => $enabled ? '1' : '0']),
            'test_embroidery'
        );

        return new Index(
            $this->jsonFactory(),
            $provider,
            $this->storeManager ?? $this->storeManagerFor(0),
            $config
        );
    }

    private function storeManagerFor(int $storeId): StoreManagerInterface&MockObject
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return $storeManager;
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
