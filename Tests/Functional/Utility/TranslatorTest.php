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
 * instead of triggering "Trying to access array offset on null" PHP Warning.
 * This is the regression test for the null access bug fix (#109).
 *
 * @see https://github.com/thieleundklose/tk-typo3-autotranslate/issues/109
 */
final class TranslatorTest extends FunctionalTestCase
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
     * Verifies that Translator::translate() returns early without error
     * when called with a non-existent record UID. This is the exact
     * scenario from bug #109: a record deleted between batch query and
     * translate() call would cause "Trying to access array offset on null".
     *
     * Without the null check fix, Records::getRecord() returns null and
     * the subsequent $record['l10n_parent'] access triggers a PHP Warning
     * which PHPUnit converts to a test failure.
     *
     * The record check was also moved before the DeepL API key validation
     * so that non-existent records are caught early, without requiring
     * a valid API key — making this scenario both testable and more efficient.
     */
    public function testTranslateSkipsNonExistentRecord(): void
    {
        $translator = GeneralUtility::makeInstance(Translator::class, 2);

        // Must not throw any exception or trigger PHP Warning.
        // Without the fix, $record['l10n_parent'] on null triggers:
        // "Trying to access array offset on null"
        $translator->translate('tt_content', 999999);

        // If we reach here, the null guard worked correctly
        self::assertTrue(true, 'translate() must return early for non-existent record without PHP Warning');
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

        self::assertTrue(true, 'translate() must return early for soft-deleted record without PHP Warning');
    }
}
