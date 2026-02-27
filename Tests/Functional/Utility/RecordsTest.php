<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Functional\Utility;

use ThieleUndKlose\Autotranslate\Utility\Records;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for Records utility.
 *
 * Verifies that Records::getRecord() correctly returns null for non-existent
 * or soft-deleted records, and returns the expected array for existing records.
 * This is the foundation of the null access bug fix (#109).
 *
 * @see https://github.com/thieleundklose/tk-typo3-autotranslate/issues/109
 */
final class RecordsTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $this->coreExtensionsToLoad = [
            'frontend',
        ];
        $this->testExtensionsToLoad = [
            'thieleundklose/autotranslate',
        ];
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/tt_content.csv');
    }

    /**
     * Verifies that Records::getRecord() returns null when the requested UID
     * does not exist in the database. This is the scenario that caused the
     * "Trying to access array offset on null" PHP Warning in Translator.php.
     */
    public function testGetRecordReturnsNullForNonExistentUid(): void
    {
        $result = Records::getRecord('pages', 999999);

        self::assertNull($result);
    }

    /**
     * Verifies that Records::getRecord() returns null for a soft-deleted
     * record (deleted=1). The DeletedRestriction filters these out, so
     * getRecord() must return null, not the deleted row.
     */
    public function testGetRecordReturnsNullForSoftDeletedRecord(): void
    {
        $result = Records::getRecord('pages', 3);

        self::assertNull($result);
    }

    /**
     * Verifies that Records::getRecord() returns the full record array
     * for an existing, non-deleted page.
     */
    public function testGetRecordReturnsArrayForExistingPage(): void
    {
        $result = Records::getRecord('pages', 2);

        self::assertIsArray($result);
        self::assertSame(2, $result['uid']);
        self::assertSame('Active Page', $result['title']);
    }

    /**
     * Verifies that Records::getRecord() returns hidden records.
     * The HiddenRestriction is removed in Records::getQueryBuilder(),
     * so hidden records must still be accessible.
     */
    public function testGetRecordReturnsHiddenRecord(): void
    {
        $result = Records::getRecord('tt_content', 3);

        self::assertIsArray($result);
        self::assertSame(3, $result['uid']);
        self::assertSame('Hidden Content', $result['header']);
    }

    /**
     * Verifies that Records::getRecord() returns null for a soft-deleted
     * tt_content record. This is the exact scenario from the bug report:
     * a record deleted between batch query and translate() call.
     */
    public function testGetRecordReturnsNullForDeletedTtContent(): void
    {
        $result = Records::getRecord('tt_content', 2);

        self::assertNull($result);
    }

    /**
     * Verifies that Records::getRecord() returns a single column value
     * when the $column parameter is specified.
     */
    public function testGetRecordReturnsSingleColumn(): void
    {
        $result = Records::getRecord('pages', 2, 'title');

        self::assertSame('Active Page', $result);
    }

    /**
     * Verifies that Records::getRecord() returns null when requesting
     * a single column from a non-existent record.
     */
    public function testGetRecordReturnsSingleColumnNullForNonExistentUid(): void
    {
        $result = Records::getRecord('pages', 999999, 'title');

        self::assertNull($result);
    }

    /**
     * Verifies that the null return value from Records::getRecord() would
     * trigger a PHP Warning when used as an array, documenting the original bug.
     * The fix in Translator::translate() adds a null check before array access.
     */
    public function testNullRecordCausesWarningWhenAccessedAsArray(): void
    {
        $record = Records::getRecord('pages', 999999);

        self::assertNull($record, 'Precondition: getRecord must return null for non-existent UID');

        $warningTriggered = false;
        set_error_handler(static function (int $errno, string $errstr) use (&$warningTriggered): bool {
            if ($errno === E_WARNING && strpos($errstr, 'Trying to access array offset on null') !== false) {
                $warningTriggered = true;
                return true;
            }
            return false;
        });

        try {
            // Reproduce the original bug: Translator.php line 98
            /** @phpstan-ignore-next-line Intentionally testing broken code pattern */
            $_ = $record['l10n_parent'] > 0;
        } finally {
            restore_error_handler();
        }

        self::assertTrue($warningTriggered, 'Accessing array offset on null must trigger PHP Warning');
    }

    /**
     * Verifies that Records::getRecords() returns UIDs for existing records
     * and excludes deleted records when a delete constraint is applied.
     */
    public function testGetRecordsReturnsUidsForExistingRecords(): void
    {
        $uids = Records::getRecords('tt_content', 'uid', [
            'pid = 2',
            'deleted = 0',
            'sys_language_uid = 0',
        ]);

        self::assertContains(1, $uids, 'Active content must be in results');
        self::assertContains(3, $uids, 'Hidden content must be in results (HiddenRestriction removed)');
        self::assertNotContains(2, $uids, 'Deleted content must not be in results');
    }
}
