<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Upload;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\MediaStorage\Model\File\Validator\Image;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Stores and removes customer-supplied embroidery logos.
 */
class LogoStorage
{
    /**
     * Names this class will act on: a 32-character hex id plus a known
     * extension.
     */
    private const string SAFE_NAME_PATTERN = '/^[a-f0-9]{32}\.(jpg|jpeg|png)$/';

    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * Arbitrary key the uploader files this callback under; it is a registry
     * name, not a lookup into anything.
     */
    private const string CALLBACK_IMAGE = 'embroidery_logo_image';

    /**
     * Hard ceiling regardless of PHP's own limits: an embroidery logo is a
     * small image, and accepting more is free disk exhaustion.
     */
    private const int MAX_BYTES = 5 * 1024 * 1024;

    private readonly WriteInterface $mediaDirectory;

    /**
     * @param string $subDirectory Folder under pub/media to store logos in.
     */
    public function __construct(
        Filesystem $filesystem,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Image $imageValidator,
        private readonly DriverInterface $driver,
        private readonly LoggerInterface $logger,
        private readonly string $subDirectory = 'embroidery'
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * Store an uploaded logo under a generated name.
     *
     * @param string $fileKey The `$_FILES` key to read.
     *
     * @throws LocalizedException When the upload is missing, too large, or not an allowed image.
     */
    public function store(string $fileKey): UploadedLogo
    {
        $uploader = $this->uploaderFactory->create(['fileId' => $fileKey]);

        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        // Reject anything whose contents are not actually an image, so a PHP
        // script renamed to .jpg cannot be stored.
        $uploader->setAllowCreateFolders(false);
        $uploader->setFilesDispersion(false);
        $uploader->setAllowRenameFiles(true);
        $uploader->addValidateCallback('size', $this, 'validateSize');
        $uploader->addValidateCallback(self::CALLBACK_IMAGE, $this, 'validateIsImage');

        // The stored name is generated, never taken from the client.
        $storedName = $this->generateName($this->extensionFor($uploader->getFileExtension()));

        $result = $uploader->save($this->absoluteBasePath(), $storedName);

        if (!is_array($result) || ($result['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new LocalizedException(__('The logo could not be uploaded. Please try a different image.'));
        }

        return new UploadedLogo($storedName, $this->relativePath($storedName));
    }

    /**
     * Delete a previously stored logo.
     *
     * @param string $fileName A bare file name; never a path.
     *
     * @return bool Whether a file was removed.
     */
    public function delete(string $fileName): bool
    {
        $relativePath = $this->safeRelativePath($fileName);

        if ($relativePath === null) {
            // Deliberately not an error to the caller: telling a prober that a
            // name was "invalid" rather than "not found" is information.
            $this->logger->warning(
                'Embroidery: refused a logo delete for an unacceptable file name.',
                ['file_name' => $fileName]
            );

            return false;
        }

        try {
            if (!$this->mediaDirectory->isExist($relativePath) || !$this->mediaDirectory->isFile($relativePath)) {
                return false;
            }

            return $this->mediaDirectory->delete($relativePath);
        } catch (Throwable $e) {
            $this->logger->error('Embroidery: failed to delete a logo.', ['exception' => $e]);

            return false;
        }
    }

    public function exists(string $fileName): bool
    {
        $relativePath = $this->safeRelativePath($fileName);

        try {
            return $relativePath !== null && $this->mediaDirectory->isExist($relativePath);
        } catch (FileSystemException) {
            return false;
        }
    }

    /**
     * Validate and resolve a caller-supplied name to a media-relative path.
     *
     * @return string|null Null when the name is not acceptable.
     */
    private function safeRelativePath(string $fileName): ?string
    {
        $fileName = trim($fileName);

        if (preg_match(self::SAFE_NAME_PATTERN, $fileName) !== 1) {
            return null;
        }

        $relativePath = $this->relativePath($fileName);

        // Second line of defence: where both paths exist on disk, confirm the
        // resolved target really does sit under the upload directory.
        $base = $this->realPath($this->absoluteBasePath());
        $resolved = $this->realPath($this->mediaDirectory->getAbsolutePath($relativePath));

        if ($base === null || $resolved === null) {
            return $relativePath;
        }

        return str_starts_with($resolved, rtrim($base, '/') . '/') ? $relativePath : null;
    }

    /**
     * Reject anything whose bytes are not actually an image.
     *
     * @throws LocalizedException
     */
    public function validateIsImage(string $filePath): void
    {
        if (!$this->imageValidator->isValid($filePath)) {
            throw new LocalizedException(
                __('Logo images must be a real PNG, JPEG or GIF image.')
            );
        }
    }
    /**
     * @throws LocalizedException
     */
    public function validateSize(string $filePath): void
    {
        // The file check comes first, so a directory, a broken symlink and an
        // unreadable path each fail.
        $size = $this->sizeOf($filePath);

        if ($size === null || $size > self::MAX_BYTES) {
            throw new LocalizedException(
                __('Logo images must be %1 MB or smaller.', (int) (self::MAX_BYTES / 1024 / 1024))
            );
        }
    }

    /**
     * The filesystem driver's answer, or null when the path does not resolve.
     */
    private function realPath(string $path): ?string
    {
        try {
            $resolved = $this->driver->getRealPath($path);
        } catch (FileSystemException) {
            return null;
        }

        return is_string($resolved) && $resolved !== '' ? $resolved : null;
    }

    /**
     * Size in bytes, or null when the path is not a readable file.
     */
    private function sizeOf(string $path): ?int
    {
        try {
            if (!$this->driver->isFile($path)) {
                return null;
            }

            $stat = $this->driver->stat($path);
        } catch (FileSystemException) {
            return null;
        }

        return isset($stat['size']) ? (int) $stat['size'] : null;
    }

    private function generateName(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function extensionFor(?string $extension): string
    {
        $extension = mb_strtolower(trim((string) $extension));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true) ? $extension : 'jpg';
    }

    private function relativePath(string $fileName): string
    {
        return trim($this->subDirectory, '/') . '/' . $fileName;
    }

    private function absoluteBasePath(): string
    {
        return $this->mediaDirectory->getAbsolutePath(trim($this->subDirectory, '/'));
    }
}
