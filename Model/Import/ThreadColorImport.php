<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Model\Import;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;

/**
 * CSV import for thread colours.
 */
class ThreadColorImport extends AbstractEntity
{
    public const string ENTITY_CODE = 'commerce_embroidery_thread_colors';

    private const string COLUMN_CODE = 'code';
    private const string COLUMN_NAME = 'name';
    private const string COLUMN_HEX = 'hex_code';
    private const string COLUMN_PANTONE = 'pantone_code';
    private const string COLUMN_SORT_ORDER = 'sort_order';
    private const string COLUMN_IS_ACTIVE = 'is_active';

    private const string HEX_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    /**
     * Untyped because AbstractEntity declares it untyped: a typed redeclaration
     * is a PHP fatal.
     *
     * phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     *
     * @var bool
     */
    protected $needColumnCheck = true;

    /** @var bool */
    protected $logInHistory = true;

    /** @var string[] */
    protected $validColumnNames = [
        self::COLUMN_CODE,
        self::COLUMN_NAME,
        self::COLUMN_HEX,
        self::COLUMN_PANTONE,
        self::COLUMN_SORT_ORDER,
        self::COLUMN_IS_ACTIVE,
    ];

    protected string $masterAttributeCode = self::COLUMN_CODE;

    public function __construct(
        ImportHelper $importExportData,
        Data $importData,
        Helper $resourceHelper,
        ProcessingErrorAggregatorInterface $errorAggregator,
        JsonHelper $jsonHelper,
        private readonly ThreadColorResource $resource
    ) {
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->errorAggregator = $errorAggregator;
        $this->jsonHelper = $jsonHelper;

        $this->initMessageTemplates();
    }

    public function getEntityTypeCode(): string
    {
        return self::ENTITY_CODE;
    }

    /**
     * @return string[]
     */
    public function getValidColumnNames(): array
    {
        return $this->validColumnNames;
    }

    /**
     * @param array<string, mixed> $rowData
     * @param int                  $rowNum
     *
     * phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function validateRow(array $rowData, $rowNum): bool
    {
        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        $code = trim((string) ($rowData[self::COLUMN_CODE] ?? ''));

        if ($code === '') {
            $this->addRowError('CodeIsRequired', $rowNum);
        } elseif (preg_match('/^[a-z0-9][a-z0-9-]*$/', $code) !== 1) {
            $this->addRowError('CodeIsInvalid', $rowNum);
        }

        if ($this->getBehavior() === Import::BEHAVIOR_DELETE) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        if (trim((string) ($rowData[self::COLUMN_NAME] ?? '')) === '') {
            $this->addRowError('NameIsRequired', $rowNum);
        }

        $hex = trim((string) ($rowData[self::COLUMN_HEX] ?? ''));

        // A malformed hex value renders as a transparent swatch, which looks to
        // a shopper exactly like a white thread.
        if ($hex === '' || preg_match(self::HEX_PATTERN, $hex) !== 1) {
            $this->addRowError('HexIsInvalid', $rowNum);
        }

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    private function initMessageTemplates(): void
    {
        $this->addMessageTemplate('CodeIsRequired', __('The code column cannot be empty.'));
        $this->addMessageTemplate(
            'CodeIsInvalid',
            __('Codes may contain only lowercase letters, digits and hyphens, e.g. "ceil-blue".')
        );
        $this->addMessageTemplate('NameIsRequired', __('The name column cannot be empty.'));
        $this->addMessageTemplate('HexIsInvalid', __('The hex_code column must look like "#1a2b3c".'));
    }

    protected function _importData(): bool
    {
        return match ($this->getBehavior()) {
            Import::BEHAVIOR_DELETE => $this->deleteRows(),
            Import::BEHAVIOR_APPEND, Import::BEHAVIOR_REPLACE => $this->upsertRows(),
            default => false,
        };
    }

    private function upsertRows(): bool
    {
        $written = 0;

        while ($bunch = $this->_dataSourceModel->getNextUniqueBunch($this->getIds())) {
            $rows = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                $code = trim((string) $row[self::COLUMN_CODE]);

                // Keyed by code, so a bunch carrying the same colour twice
                // writes it once.
                $rows[$code] = [
                    ThreadColorInterface::CODE => $code,
                    ThreadColorInterface::NAME => trim((string) $row[self::COLUMN_NAME]),
                    ThreadColorInterface::HEX_CODE => mb_strtolower(trim((string) $row[self::COLUMN_HEX])),
                    ThreadColorInterface::PANTONE_CODE => $this->nullableString($row[self::COLUMN_PANTONE] ?? null),
                    ThreadColorInterface::SORT_ORDER => (int) ($row[self::COLUMN_SORT_ORDER] ?? 0),
                    ThreadColorInterface::IS_ACTIVE => $this->toFlag($row[self::COLUMN_IS_ACTIVE] ?? '1'),
                ];
            }

            if ($rows !== []) {
                $written += $this->resource->upsertMany(array_values($rows));
            }
        }

        return $written > 0;
    }

    private function deleteRows(): bool
    {
        $codes = [];

        while ($bunch = $this->_dataSourceModel->getNextUniqueBunch($this->getIds())) {
            foreach ($bunch as $rowNum => $row) {
                if ($this->validateRow($row, $rowNum)) {
                    $codes[] = trim((string) $row[self::COLUMN_CODE]);
                }
            }
        }

        if ($codes === []) {
            return false;
        }

        $connection = $this->resource->getConnection();

        return $connection->delete(
            $this->resource->getMainTable(),
            $connection->quoteInto(ThreadColorInterface::CODE . ' IN (?)', array_unique($codes))
        ) > 0;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toFlag(mixed $value): int
    {
        $value = mb_strtolower(trim((string) $value));

        return in_array($value, ['0', 'no', 'false', 'n', ''], true) ? 0 : 1;
    }
}
