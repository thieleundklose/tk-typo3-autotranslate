<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Functional\Utility;

use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for Translator utility.
 *
 * Verifies that Translator::translate() handles null records gracefully
 * instead of triggering "Trying to access array offset on null" PHP warnings
 * and follow-up TypeErrors. This is the regression test for the null access
 * bug fix (#109).
 *
 * @see https://github.com/thieleundklose/tk-typo3-autotranslate/issues/109
 */
final class TranslatorTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'frontend',
    ];

    protected array $testExtensionsToLoad = [
        'thieleundklose/autotranslate',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/tt_content.csv');
    }

    /**
     * Verifies that Translator::translate() returns early without error
     * when called with a non-existent record UID. This is the exact
     * scenario from bug #109: a record deleted between batch query and
     * translate() call.
     *
     * Without the null check fix, Records::getRecord() returns null and
     * the following $record[$parentField] access raises a PHP warning,
     * which failOnWarning="true" turns into a test failure.
     */
    public function testTranslateSkipsNonExistentRecord(): void
    {
        $this->expectNotToPerformAssertions();

        $translator = GeneralUtility::makeInstance(Translator::class, 2);
        $translator->translate('tt_content', 999999);
    }

    /**
     * Verifies that Translator::translate() returns early without error
     * when called with a soft-deleted record UID. The DeletedRestriction
     * filters out deleted records, so Records::getRecord() returns null.
     * This is the exact production scenario: a record gets deleted between
     * the batch query (which found it) and the translate() call.
     */
    public function testTranslateSkipsSoftDeletedRecord(): void
    {
        $translator = GeneralUtility::makeInstance(Translator::class, 2);

        // tt_content uid=2 is soft-deleted (deleted=1) in fixtures
        $translator->translate('tt_content', 2);

        // The early return must skip the record entirely, including the
        // autotranslate_last update at the end of translate().
        $autotranslateLast = $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->select(['autotranslate_last'], 'tt_content', ['uid' => 2])
            ->fetchOne();

        self::assertSame(0, (int)$autotranslateLast, 'A skipped record must not be touched by translate()');
    }
}
