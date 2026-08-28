<?php
/**
 * UploadedLogo.php
 *
 * @package     Commerce_Embroidery
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\Embroidery\Model\Upload;

/**
 * A stored logo, as reported back to the storefront.
 */
class UploadedLogo
{
    public function __construct(
        public readonly string $fileName,
        public readonly string $relativePath
    ) {
    }

    /**
     * @return array{file_name: string, path: string}
     */
    public function toArray(): array
    {
        return [
            'file_name' => $this->fileName,
            'path' => $this->relativePath,
        ];
    }
}
