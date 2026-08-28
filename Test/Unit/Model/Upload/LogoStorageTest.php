<?php
/**
 * LogoStorageTest.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Upload;

use Commerce\Embroidery\Model\Upload\LogoStorage;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\MediaStorage\Model\File\Validator\Image;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A storage class that accepted a path would be one controller away from
 * arbitrary deletion.
 */
class LogoStorageTest extends TestCase
{
    private WriteInterface&MockObject $mediaDirectory;
    private LoggerInterface&MockObject $logger;
    private Image&MockObject $imageValidator;
    private DriverInterface&MockObject $driver;
    private LogoStorage $storage;

    protected function setUp(): void
    {
        $this->mediaDirectory = $this->createMock(WriteInterface::class);
        $this->mediaDirectory->method('getAbsolutePath')
            ->willReturnCallback(static fn (string $p = ''): string => '/srv/media/' . ltrim($p, '/'));

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')
            ->with(DirectoryList::MEDIA)
            ->willReturn($this->mediaDirectory);

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->imageValidator = $this->createMock(Image::class);

        // Paths resolve to themselves, so the traversal assertions exercise the
        // containment check.
        $this->driver = $this->createMock(DriverInterface::class);
        $this->driver->method('getRealPath')->willReturnArgument(0);
        $this->driver->method('isFile')->willReturn(true);
        $this->driver->method('stat')->willReturn(['size' => 1024]);

        $this->storage = new LogoStorage(
            $filesystem,
            $this->createMock(UploaderFactory::class),
            $this->imageValidator,
            $this->driver,
            $this->logger,
            'embroidery'
        );
    }

    /**
     * The whole point of the class.
     */
    #[DataProvider('traversalProvider')]
    public function testRefusesToDeleteAnythingOutsideTheUploadDirectory(string $fileName): void
    {
        $this->mediaDirectory->expects(self::never())->method('delete');

        self::assertFalse($this->storage->delete($fileName));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalProvider(): array
    {
        return [
            'parent traversal' => ['../../../app/etc/env.php'],
            'encoded traversal' => ['..%2F..%2Fapp%2Fetc%2Fenv.php'],
            'absolute path' => ['/etc/passwd'],
            'nested path' => ['sub/dir/file.jpg'],
            'backslash traversal' => ['..\\..\\env.php'],
            'null byte' => ["abcdef01234567890abcdef012345678.jpg\0.php"],
            'wrong extension' => ['abcdef01234567890abcdef012345678.php'],
            'not a generated name' => ['my-logo.jpg'],
            'empty' => [''],
            'dot' => ['.'],
            'double dot' => ['..'],
        ];
    }

    public function testDeletesAGenuinelyGeneratedName(): void
    {
        $name = str_repeat('a1', 16) . '.jpg';

        $this->mediaDirectory->method('isExist')->willReturn(true);
        $this->mediaDirectory->method('isFile')->willReturn(true);
        $this->mediaDirectory->expects(self::once())
            ->method('delete')
            ->with('embroidery/' . $name)
            ->willReturn(true);

        self::assertTrue($this->storage->delete($name));
    }

    public function testDeletingAMissingFileReportsFalseRatherThanRaising(): void
    {
        $this->mediaDirectory->method('isExist')->willReturn(false);
        $this->mediaDirectory->expects(self::never())->method('delete');

        self::assertFalse($this->storage->delete(str_repeat('b2', 16) . '.png'));
    }

    /**
     * A rejected name and a missing file must be indistinguishable to the
     * caller, so the endpoint cannot be used to probe the filesystem.
     */
    public function testRejectedAndMissingAreReportedIdentically(): void
    {
        $this->mediaDirectory->method('isExist')->willReturn(false);

        self::assertSame(
            $this->storage->delete('../../../etc/passwd'),
            $this->storage->delete(str_repeat('c3', 16) . '.jpg')
        );
    }

    public function testAnUnacceptableNameIsLoggedForInvestigation(): void
    {
        $this->logger->expects(self::once())->method('warning');

        $this->storage->delete('../../../app/etc/env.php');
    }

    public function testExistsAppliesTheSameNameRules(): void
    {
        $this->mediaDirectory->method('isExist')->willReturn(true);

        self::assertFalse($this->storage->exists('../../../app/etc/env.php'));
        self::assertTrue($this->storage->exists(str_repeat('d4', 16) . '.jpeg'));
    }
    /**
     * The extension allow-list is not the content check.
     */
    public function testAFileWhoseBytesAreNotAnImageIsRejected(): void
    {
        $this->imageValidator->method('isValid')->willReturn(false);

        $this->expectException(LocalizedException::class);

        $this->storage->validateIsImage('/tmp/php-upload-not-really-a-jpeg');
    }

    public function testARealImageIsAccepted(): void
    {
        $this->imageValidator->expects(self::once())
            ->method('isValid')
            ->with('/tmp/php-upload')
            ->willReturn(true);

        $this->storage->validateIsImage('/tmp/php-upload');

        $this->addToAssertionCount(1);
    }

    /**
     * A callback the uploader cannot call is a defence that is not there.
     */
    public function testEveryRegisteredValidateCallbackIsActuallyCallable(): void
    {
        foreach (['validateIsImage', 'validateSize'] as $method) {
            self::assertTrue(method_exists($this->storage, $method), $method . ' is registered but missing');
            self::assertTrue(is_callable([$this->storage, $method]), $method . ' is not callable');
        }
    }
}
