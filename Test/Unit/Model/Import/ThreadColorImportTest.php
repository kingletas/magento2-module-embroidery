<?php
/**
 * @package   Commerce_Embroidery
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\Embroidery\Test\Unit\Model\Import;

use Commerce\Embroidery\Api\Data\ThreadColorInterface;
use Commerce\Embroidery\Model\Import\ThreadColorImport;
use Commerce\Embroidery\Model\ResourceModel\ThreadColor as ThreadColorResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ThreadColorImportTest extends TestCase
{
    /** @var array<int, array<int, array<string, mixed>>> Bunches handed out in order. */
    private array $bunches = [];

    /** @var array<int, array{code: string, row: int}> */
    private array $errors = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $upserts = [];

    /** @var array<int, string> */
    private array $deletes = [];

    private int $bunchesTaken = 0;

    protected function setUp(): void
    {
        $this->bunches = [];
        $this->errors = [];
        $this->upserts = [];
        $this->deletes = [];
        $this->bunchesTaken = 0;
    }

    public function testTheEntityCodeAndColumnsAreWhatTheCsvDeclares(): void
    {
        $import = $this->import();

        $this->assertSame(ThreadColorImport::ENTITY_CODE, $import->getEntityTypeCode());
        $this->assertContains('code', $import->getValidColumnNames());
        $this->assertContains('hex_code', $import->getValidColumnNames());
    }

    public function testAWellFormedRowValidates(): void
    {
        $this->assertTrue($this->import()->validateRow($this->row(), 1));
        $this->assertSame([], $this->errors);
    }

    public function testARowWithNoCodeIsRejected(): void
    {
        $this->assertFalse($this->import()->validateRow($this->row(['code' => '  ']), 1));
        $this->assertSame(['CodeIsRequired'], array_column($this->errors, 'code'));
    }

    /**
     * The code is part of a swatch's identity and matched case-sensitively, so
     * the shape is narrow.
     */
    public function testACodeOutsideTheAcceptedShapeIsRejected(): void
    {
        foreach (['Ceil Blue', 'CEIL-BLUE', '-leading', 'ceil_blue', 'ceil.blue'] as $index => $code) {
            $this->errors = [];

            $this->assertFalse(
                $this->import()->validateRow($this->row(['code' => $code]), $index + 1),
                sprintf('"%s" should not be an acceptable code.', $code)
            );
            $this->assertSame(['CodeIsInvalid'], array_column($this->errors, 'code'));
        }
    }

    public function testALowercaseHyphenatedCodeIsAccepted(): void
    {
        $this->assertTrue($this->import()->validateRow($this->row(['code' => 'ceil-blue-2']), 1));
    }

    public function testARowWithNoNameIsRejected(): void
    {
        $this->assertFalse($this->import()->validateRow($this->row(['name' => '  ']), 1));
        $this->assertSame(['NameIsRequired'], array_column($this->errors, 'code'));
    }

    /**
     * A malformed hex renders as a transparent swatch, which a shopper reads as
     * white.
     */
    public function testAMalformedHexIsRejected(): void
    {
        foreach (['', '1a2b3c', '#12345', 'blue', '#1a2b3g'] as $index => $hex) {
            $this->errors = [];

            $this->assertFalse(
                $this->import()->validateRow($this->row(['hex_code' => $hex]), $index + 1),
                sprintf('"%s" should not be an acceptable hex value.', $hex)
            );
            $this->assertSame(['HexIsInvalid'], array_column($this->errors, 'code'));
        }
    }

    /**
     * A delete row identifies the colour by code; demanding a name and a hex
     * would make every delete row fail validation.
     */
    public function testADeleteRowNeedsOnlyItsCode(): void
    {
        $import = $this->import(Import::BEHAVIOR_DELETE);

        $this->assertTrue($import->validateRow(['code' => 'ceil-blue'], 1));
        $this->assertSame([], $this->errors);
    }

    /**
     * Magento calls `validateRow()` twice per row, so the checks do not report
     * it twice.
     */
    public function testARowIsOnlyValidatedOnce(): void
    {
        $import = $this->import();

        $import->validateRow($this->row(['code' => '']), 1);
        $import->validateRow($this->row(['code' => '']), 1);

        $this->assertCount(1, $this->errors);
    }

    public function testAValidRowIsWrittenWithEveryColumnMapped(): void
    {
        $this->bunches = [[1 => $this->row()]];

        $this->assertTrue($this->import()->importData());

        $written = $this->upserts[0][0];
        $this->assertSame('ceil-blue', $written[ThreadColorInterface::CODE]);
        $this->assertSame('Ceil Blue', $written[ThreadColorInterface::NAME]);
        $this->assertSame('#7fa8d4', $written[ThreadColorInterface::HEX_CODE]);
        $this->assertSame('PMS 291', $written[ThreadColorInterface::PANTONE_CODE]);
        $this->assertSame(20, $written[ThreadColorInterface::SORT_ORDER]);
        $this->assertSame(1, $written[ThreadColorInterface::IS_ACTIVE]);
    }

    /**
     * Hex values are compared as strings elsewhere, so `#7FA8D4` and `#7fa8d4`
     * would otherwise be two different colours.
     */
    public function testTheHexIsNormalisedToLowercase(): void
    {
        $this->bunches = [[1 => $this->row(['hex_code' => '#7FA8D4'])]];

        $this->import()->importData();

        $this->assertSame('#7fa8d4', $this->upserts[0][0][ThreadColorInterface::HEX_CODE]);
    }

    /**
     * Most colours have no Pantone equivalent, and a blank cell must become
     * NULL rather than an empty string that no lookup will ever match.
     */
    public function testABlankPantoneBecomesNull(): void
    {
        $this->bunches = [[1 => $this->row(['pantone_code' => '   '])]];

        $this->import()->importData();

        $this->assertNull($this->upserts[0][0][ThreadColorInterface::PANTONE_CODE]);
    }

    /**
     * A spreadsheet writes a boolean column as anything from "yes" to "FALSE".
     */
    public function testTheActiveColumnIsReadTheWayASpreadsheetWritesOne(): void
    {
        $asASpreadsheetWritesThem = [
            '0' => 0, 'no' => 0, 'NO' => 0, 'false' => 0,
            'n' => 0, '' => 0, '1' => 1, 'yes' => 1,
        ];

        foreach ($asASpreadsheetWritesThem as $value => $expected) {
            $this->upserts = [];
            $this->bunchesTaken = 0;
            $this->bunches = [[1 => $this->row(['is_active' => (string) $value])]];

            $this->import()->importData();

            $this->assertSame(
                $expected,
                $this->upserts[0][0][ThreadColorInterface::IS_ACTIVE],
                sprintf('"%s" should read as %d.', $value, $expected)
            );
        }
    }

    /**
     * A colour listed twice in one bunch is written once.
     */
    public function testAColourRepeatedInOneBunchIsWrittenOnce(): void
    {
        $this->bunches = [[
            1 => $this->row(['name' => 'First']),
            2 => $this->row(['name' => 'Second']),
        ]];

        $this->import()->importData();

        $this->assertCount(1, $this->upserts[0]);
        $this->assertSame('Second', $this->upserts[0][0][ThreadColorInterface::NAME]);
    }

    public function testAnInvalidRowIsNotWritten(): void
    {
        $this->bunches = [[
            1 => $this->row(['code' => '']),
            2 => $this->row(),
        ]];

        $this->import()->importData();

        $this->assertCount(1, $this->upserts[0]);
    }

    public function testABunchWithNothingValidWritesNothing(): void
    {
        $this->bunches = [[1 => $this->row(['code' => ''])]];

        $this->assertFalse($this->import()->importData());
        $this->assertSame([], $this->upserts);
    }

    public function testEveryBunchIsProcessed(): void
    {
        $this->bunches = [
            [1 => $this->row(['code' => 'ceil-blue'])],
            [2 => $this->row(['code' => 'navy'])],
        ];

        $this->import()->importData();

        $this->assertCount(2, $this->upserts);
    }

    public function testDeleteRemovesTheListedCodes(): void
    {
        $this->bunches = [[
            1 => ['code' => 'ceil-blue'],
            2 => ['code' => 'navy'],
        ]];

        $this->assertTrue($this->import(Import::BEHAVIOR_DELETE)->importData());
        $this->assertStringContainsString('ceil-blue', $this->deletes[0]);
        $this->assertStringContainsString('navy', $this->deletes[0]);
    }

    /**
     * One statement for every code, bound through `quoteInto` because they come
     * from a file.
     */
    public function testTheCodesAreRemovedInOneQuotedStatement(): void
    {
        $this->bunches = [[1 => ['code' => "ceil-blue' OR 1=1 --"]]];

        $this->import(Import::BEHAVIOR_DELETE)->importData();

        $this->assertSame([], $this->deletes);
        $this->assertSame(['CodeIsInvalid'], array_column($this->errors, 'code'));
    }

    public function testDeletingNothingIsReportedAsNoChange(): void
    {
        $this->bunches = [[1 => ['code' => '']]];

        $this->assertFalse($this->import(Import::BEHAVIOR_DELETE)->importData());
        $this->assertSame([], $this->deletes);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'code' => 'ceil-blue',
            'name' => 'Ceil Blue',
            'hex_code' => '#7fa8d4',
            'pantone_code' => 'PMS 291',
            'sort_order' => '20',
            'is_active' => '1',
        ];
    }

    private function import(string $behavior = Import::BEHAVIOR_APPEND): ThreadColorImport
    {
        $importData = $this->createMock(Data::class);
        $importData->method('getNextUniqueBunch')->willReturnCallback(
            fn (): array|bool => $this->bunches[$this->bunchesTaken++] ?? false
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            static fn (string $text, $value): string
                => str_replace('?', is_array($value) ? "'" . implode("','", $value) . "'" : (string) $value, $text)
        );
        $connection->method('delete')->willReturnCallback(
            function (string $table, $where = ''): int {
                $this->deletes[] = (string) $where;

                return 2;
            }
        );

        $resource = $this->createMock(ThreadColorResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn(ThreadColorResource::TABLE_NAME);
        $resource->method('upsertMany')->willReturnCallback(
            function (array $rows): int {
                $this->upserts[] = $rows;

                return count($rows);
            }
        );

        $import = new ThreadColorImport(
            $this->createMock(ImportHelper::class),
            $importData,
            $this->createMock(Helper::class),
            $this->errorAggregator(),
            $this->createMock(JsonHelper::class),
            $resource
        );
        $import->setParameters(['behavior' => $behavior]);

        return $import;
    }

    private function errorAggregator(): ProcessingErrorAggregatorInterface&MockObject
    {
        $aggregator = $this->createMock(ProcessingErrorAggregatorInterface::class);
        $aggregator->method('addError')->willReturnCallback(
            function ($code, $level = null, $rowNumber = null) use (&$aggregator) {
                $this->errors[] = ['code' => (string) $code, 'row' => (int) $rowNumber];

                return $aggregator;
            }
        );
        $aggregator->method('isRowInvalid')->willReturnCallback(
            fn ($rowNumber): bool => in_array((int) $rowNumber, array_column($this->errors, 'row'), true)
        );
        $aggregator->method('hasToBeTerminated')->willReturn(false);

        return $aggregator;
    }
}
