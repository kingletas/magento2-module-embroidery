<?php
/**
 * ConfigTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model;

use Commerce\Embroidery\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * The section id is a di.xml argument.
     */
    public function testEveryPathIsReadUnderTheConfiguredSection(): void
    {
        $config = new Config(
            $this->scopeConfig([
                'acme_embroidery/general/enabled' => '1',
                'acme_embroidery/charges/stock_logo_price' => '4.50',
            ]),
            'acme_embroidery'
        );

        $this->assertTrue($config->isEnabled());
        $this->assertSame(4.5, $config->getStockLogoPrice());
    }

    public function testAnUnconfiguredStoreHasTheFeatureOff(): void
    {
        $this->assertFalse($this->config([])->isEnabled());
    }

    public function testTheDisabledFlagIsReadAsAFlagRatherThanForTruthiness(): void
    {
        $this->assertFalse($this->config(['general/enabled' => '0'])->isEnabled());
    }

    public function testTheCmsBlockAndMessageSettingsDefaultToEmpty(): void
    {
        $config = $this->config([]);

        $this->assertSame('', $config->getTermsCmsBlockId());
        $this->assertSame('', $config->getUploadInfoCmsBlockId());
        $this->assertSame('', $config->getUploadSizeMessage());
    }

    public function testTheCmsBlockAndMessageSettingsAreReadWhenSet(): void
    {
        $config = $this->config([
            'general/terms_cms_block' => 'embroidery_terms',
            'general/upload_info_cms_block' => 'embroidery_upload_info',
            'general/upload_size_message' => 'Up to 2 MB.',
        ]);

        $this->assertSame('embroidery_terms', $config->getTermsCmsBlockId());
        $this->assertSame('embroidery_upload_info', $config->getUploadInfoCmsBlockId());
        $this->assertSame('Up to 2 MB.', $config->getUploadSizeMessage());
    }

    /**
     * Each text line has its own price path, so a store can charge more for the
     * second and third lines than the first.
     */
    public function testEachTextLineHasItsOwnPrice(): void
    {
        $config = $this->config([
            'charges/text_line_1_price' => '3.00',
            'charges/text_line_2_price' => '2.00',
        ]);

        $this->assertSame(3.0, $config->getTextLinePrice(1));
        $this->assertSame(2.0, $config->getTextLinePrice(2));
    }

    /**
     * A line with no price configured is free rather than falling back to
     * another line's price.
     */
    public function testAnUnpricedTextLineCostsNothing(): void
    {
        $this->assertSame(0.0, $this->config([])->getTextLinePrice(3));
    }

    /**
     * Charges are money.
     */
    public function testTheChargesAreReadAsFloats(): void
    {
        $config = $this->config([
            'charges/stock_logo_price' => '4.50',
            'charges/custom_logo_price' => '7.25',
            'charges/custom_logo_fee' => '25.00',
        ]);

        $this->assertSame(4.5, $config->getStockLogoPrice());
        $this->assertSame(7.25, $config->getCustomLogoPrice());
        $this->assertSame(25.0, $config->getCustomLogoFee());
    }

    public function testUnconfiguredChargesAreFree(): void
    {
        $config = $this->config([]);

        $this->assertSame(0.0, $config->getStockLogoPrice());
        $this->assertSame(0.0, $config->getCustomLogoPrice());
        $this->assertSame(0.0, $config->getCustomLogoFee());
    }

    public function testNotificationIsOffUntilItIsSwitchedOn(): void
    {
        $this->assertFalse($this->config([])->isNotificationEnabled());
        $this->assertTrue($this->config(['notification/enabled' => '1'])->isNotificationEnabled());
    }

    /**
     * The shipped template and the `general` identity exist everywhere, so an
     * unconfigured store sends.
     */
    public function testTheTemplateAndSenderFallBackToShippedDefaults(): void
    {
        $config = $this->config([]);

        $this->assertSame('commerce_embroidery_order', $config->getNotificationTemplate());
        $this->assertSame('general', $config->getSenderIdentity());
    }

    public function testTheTemplateAndSenderAreConfigurable(): void
    {
        $config = $this->config([
            'notification/template' => 'acme_embroidery_order',
            'notification/sender_identity' => 'sales',
        ]);

        $this->assertSame('acme_embroidery_order', $config->getNotificationTemplate());
        $this->assertSame('sales', $config->getSenderIdentity());
    }

    /**
     * The admin field is typed by hand, so addresses are trimmed before the
     * transport sees them.
     */
    public function testTheRecipientsAreSplitAndTrimmed(): void
    {
        $config = $this->config([
            'notification/recipients' => 'a@example.test, b@example.test ,,  c@example.test ',
        ]);

        $this->assertSame(
            ['a@example.test', 'b@example.test', 'c@example.test'],
            $config->getNotificationRecipients()
        );
    }

    public function testNoRecipientsIsAnEmptyListRatherThanAListWithAnEmptyEntry(): void
    {
        $this->assertSame([], $this->config([])->getNotificationRecipients());
        $this->assertSame([], $this->config(['notification/recipients' => ' , '])->getNotificationRecipients());
    }

    public function testTheStoreScopeIsPassedThrough(): void
    {
        $this->assertSame(4.5, $this->config(['charges/stock_logo_price' => '4.50'])->getStockLogoPrice(2));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function config(array $values): Config
    {
        $qualified = [];

        foreach ($values as $path => $value) {
            $qualified['test_embroidery/' . $path] = $value;
        }

        return new Config($this->scopeConfig($qualified), 'test_embroidery');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function scopeConfig(array $values): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => !in_array($values[$path] ?? null, [null, '', '0', 0, false], true)
        );

        return $scopeConfig;
    }
}
