<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Upload;

use Commerce\Embroidery\Model\Upload\UploadedLogo;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class UploadedLogoTest extends TestCase
{
    public function testItCarriesTheStoredNameAndItsMediaRelativePath(): void
    {
        $logo = new UploadedLogo('a1b2c3.png', 'embroidery/logo/a/1/a1b2c3.png');

        $this->assertSame('a1b2c3.png', $logo->fileName);
        $this->assertSame('embroidery/logo/a/1/a1b2c3.png', $logo->relativePath);
    }

    public function testTheArrayFormIsWhatTheEndpointReturns(): void
    {
        $logo = new UploadedLogo('a1b2c3.png', 'embroidery/logo/a/1/a1b2c3.png');

        $this->assertSame(
            ['file_name' => 'a1b2c3.png', 'path' => 'embroidery/logo/a/1/a1b2c3.png'],
            $logo->toArray()
        );
    }

    /**
     * No absolute server path appears in the response, which would hand a
     * prober the layout.
     */
    public function testThereIsNoPlaceForAnAbsoluteServerPath(): void
    {
        $logo = new UploadedLogo('a1b2c3.png', 'embroidery/logo/a/1/a1b2c3.png');

        $this->assertSame(['file_name', 'path'], array_keys($logo->toArray()));
        $this->assertStringStartsNotWith('/', $logo->relativePath);
    }

    public function testItIsImmutable(): void
    {
        foreach (['fileName', 'relativePath'] as $property) {
            $this->assertTrue(
                (new ReflectionProperty(UploadedLogo::class, $property))->isReadOnly(),
                sprintf('%s must be read-only.', $property)
            );
        }
    }
}
