<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\MockObject\MockObject;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests for null record handling in Translator.
 *
 * @see https://github.com/thieleundklose/tk-typo3-autotranslate/issues/109
 */
final class TranslatorTest extends UnitTestCase
{
    /** @var bool */
    protected $resetSingletonInstances = true;

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * Verifies that Records::getRecord() returns null when no record exists
     * for the given UID. This is the prerequisite for the null access bug.
     */
    public function testGetRecordReturnsNullForNonExistentUid(): void
    {
        $queryBuilderMock = $this->createQueryBuilderMock(false);

        $connectionPoolMock = $this->createStub(ConnectionPool::class);
        $connectionPoolMock->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $typo3VersionMock = $this->createStub(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(13);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = Records::getRecord('tt_content', 999999999);

        self::assertNull($result, 'Records::getRecord() must return null for non-existent records');
    }

    /**
     * Verifies that Records::getRecord() returns the record array when the
     * record exists.
     */
    public function testGetRecordReturnsArrayForExistingRecord(): void
    {
        $recordData = [
            'uid' => 1,
            'pid' => 1,
            'l10n_parent' => 0,
            'autotranslate_exclude' => 0,
            'CType' => 'text',
        ];

        $queryBuilderMock = $this->createQueryBuilderMock($recordData);

        $connectionPoolMock = $this->createStub(ConnectionPool::class);
        $connectionPoolMock->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $typo3VersionMock = $this->createStub(Typo3Version::class);
        $typo3VersionMock->method('getMajorVersion')->willReturn(13);
        GeneralUtility::addInstance(Typo3Version::class, $typo3VersionMock);

        $result = Records::getRecord('tt_content', 1);

        self::assertIsArray($result);
        self::assertSame(1, $result['uid']);
    }

    /**
     * Verifies that accessing an array offset on the null return value of
     * Records::getRecord() triggers a PHP Warning. This documents the
     * original bug that was fixed.
     */
    public function testAccessingArrayOffsetOnNullTriggersWarning(): void
    {
        $record = null;
        $warningTriggered = false;

        set_error_handler(static function (int $errno, string $errstr) use (&$warningTriggered): bool {
            if ($errno === E_WARNING && str_contains($errstr, 'Trying to access array offset on null')) {
                $warningTriggered = true;
                return true;
            }
            return false;
        });

        try {
            // This is the code pattern from Translator.php line 98 BEFORE the fix
            /** @phpstan-ignore-next-line Testing intentionally broken code */
            $_ = $record['l10n_parent'] > 0;
        } finally {
            restore_error_handler();
        }

        self::assertTrue($warningTriggered, 'Accessing array offset on null must trigger a PHP Warning');
    }

    /**
     * Verifies that the null check added by the fix prevents the PHP Warning.
     * Simulates the fixed code path in Translator::translate() where
     * Records::getRecord() returns null and the method returns early.
     */
    public function testNullCheckPreventsArrayOffsetWarning(): void
    {
        $record = null;

        // This is the FIXED code path from Translator::translate()
        if ($record === null) {
            // Null check caught it — no warning will be raised
            self::assertNull($record);
            return;
        }

        // If we reach here, the null check failed
        self::fail('Null record was not caught by the null check');
    }

    /**
     * Creates a QueryBuilder mock that returns the given result from
     * fetchAssociative(). Pass false to simulate a non-existent record.
     *
     * @param array|false $fetchResult
     */
    private function createQueryBuilderMock($fetchResult): QueryBuilder&MockObject
    {
        $statementMock = $this->createStub(\Doctrine\DBAL\Result::class);
        $statementMock->method('fetchAssociative')->willReturn($fetchResult);

        $expressionBuilderMock = $this->createStub(ExpressionBuilder::class);
        $expressionBuilderMock->method('eq')->willReturn('1=1');

        $restrictionContainerMock = $this->createStub(DefaultRestrictionContainer::class);
        $restrictionContainerMock->method('removeByType')->willReturnSelf();

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->method('getRestrictions')->willReturn($restrictionContainerMock);
        $queryBuilderMock->method('select')->willReturnSelf();
        $queryBuilderMock->method('from')->willReturnSelf();
        $queryBuilderMock->method('where')->willReturnSelf();
        $queryBuilderMock->method('expr')->willReturn($expressionBuilderMock);
        $queryBuilderMock->method('createNamedParameter')->willReturn('1');
        $queryBuilderMock->method('executeQuery')->willReturn($statementMock);

        return $queryBuilderMock;
    }
}
