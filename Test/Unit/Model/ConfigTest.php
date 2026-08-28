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
use Commerce\Embroidery\Test\Unit\Fake\ArrayScopeConfig;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * The section id is a di.xml argument.
     */
    public function testEveryPathIsReadUnderTheConfiguredSection(): void
    {
        $config = new Config(
            new ArrayScopeConfig([
                'acme_embroidery/general/enabled' => '1',
                'acme_embroidery/charges/stock_logo_price' => '4.50',
            ]),
            'acme_embroidery'
        );

        self::assertTrue($config->isEnabled());
        self::assertSame(4.5, $config->getStockLogoPrice());
    }

    public function testAnUnconfiguredStoreHasTheFeatureOff(): void
    {
        self::assertFalse($this->config([])->isEnabled());
    }

    public function testTheDisabledFlagIsReadAsAFlagRatherThanForTruthiness(): void
    {
        self::assertFalse($this->config(['general/enabled' => '0'])->isEnabled());
    }

    public function testTheCmsBlockAndMessageSettingsDefaultToEmpty(): void
    {
        $config = $this->config([]);

        self::assertSame('', $config->getTermsCmsBlockId());
        self::assertSame('', $config->getUploadInfoCmsBlockId());
        self::assertSame('', $config->getUploadSizeMessage());
    }

    public function testTheCmsBlockAndMessageSettingsAreReadWhenSet(): void
    {
        $config = $this->config([
            'general/terms_cms_block' => 'embroidery_terms',
            'general/upload_info_cms_block' => 'embroidery_upload_info',
            'general/upload_size_message' => 'Up to 2 MB.',
        ]);

        self::assertSame('embroidery_terms', $config->getTermsCmsBlockId());
        self::assertSame('embroidery_upload_info', $config->getUploadInfoCmsBlockId());
        self::assertSame('Up to 2 MB.', $config->getUploadSizeMessage());
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

        self::assertSame(3.0, $config->getTextLinePrice(1));
        self::assertSame(2.0, $config->getTextLinePrice(2));
    }

    /**
     * A line with no price configured is free rather than falling back to
     * another line's price.
     */
    public function testAnUnpricedTextLineCostsNothing(): void
    {
        self::assertSame(0.0, $this->config([])->getTextLinePrice(3));
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

        self::assertSame(4.5, $config->getStockLogoPrice());
        self::assertSame(7.25, $config->getCustomLogoPrice());
        self::assertSame(25.0, $config->getCustomLogoFee());
    }

    public function testUnconfiguredChargesAreFree(): void
    {
        $config = $this->config([]);

        self::assertSame(0.0, $config->getStockLogoPrice());
        self::assertSame(0.0, $config->getCustomLogoPrice());
        self::assertSame(0.0, $config->getCustomLogoFee());
    }

    public function testNotificationIsOffUntilItIsSwitchedOn(): void
    {
        self::assertFalse($this->config([])->isNotificationEnabled());
        self::assertTrue($this->config(['notification/enabled' => '1'])->isNotificationEnabled());
    }

    /**
     * The shipped template and the `general` identity exist everywhere, so an
     * unconfigured store sends.
     */
    public function testTheTemplateAndSenderFallBackToShippedDefaults(): void
    {
        $config = $this->config([]);

        self::assertSame('commerce_embroidery_order', $config->getNotificationTemplate());
        self::assertSame('general', $config->getSenderIdentity());
    }

    public function testTheTemplateAndSenderAreConfigurable(): void
    {
        $config = $this->config([
            'notification/template' => 'acme_embroidery_order',
            'notification/sender_identity' => 'sales',
        ]);

        self::assertSame('acme_embroidery_order', $config->getNotificationTemplate());
        self::assertSame('sales', $config->getSenderIdentity());
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

        self::assertSame(
            ['a@example.test', 'b@example.test', 'c@example.test'],
            $config->getNotificationRecipients()
        );
    }

    public function testNoRecipientsIsAnEmptyListRatherThanAListWithAnEmptyEntry(): void
    {
        self::assertSame([], $this->config([])->getNotificationRecipients());
        self::assertSame([], $this->config(['notification/recipients' => ' , '])->getNotificationRecipients());
    }

    public function testTheStoreScopeIsPassedThrough(): void
    {
        self::assertSame(4.5, $this->config(['charges/stock_logo_price' => '4.50'])->getStockLogoPrice(2));
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

        return new Config(new ArrayScopeConfig($qualified), 'test_embroidery');
    }
}
