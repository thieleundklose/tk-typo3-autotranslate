<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\ValueObject\TranslationResult;

final class TranslationResultTest extends TestCase
{
    public function testMergeAggregatesTranslatedFieldsAndUniqueSkippedReasons(): void
    {
        $result = TranslationResult::skipped('No translatable fields found.');
        $translatedResult = new TranslationResult();
        $translatedResult->addTranslatedFields(2);
        $translatedResult->addSkippedReason('No translatable fields found.');
        $translatedResult->addSkippedReason('Record is excluded from automatic translation.');

        $result->merge($translatedResult);

        self::assertTrue($result->hasTranslations());
        self::assertSame(2, $result->getTranslatedFieldCount());
        self::assertSame(
            ['No translatable fields found.', 'Record is excluded from automatic translation.'],
            $result->getSkippedReasons()
        );
    }

    public function testSkippedResultHasNoTranslationsAndProvidesSummary(): void
    {
        $result = TranslationResult::skipped('No translatable fields found.');

        self::assertFalse($result->hasTranslations());
        self::assertSame(0, $result->getTranslatedFieldCount());
        self::assertSame('No translatable fields found.', $result->getSkippedReasonSummary());
    }

    public function testErrorIsAvailableAsErrorAndSkippedReason(): void
    {
        $result = new TranslationResult();
        $result->addError('Site language 2 failed: Missing target language.');

        self::assertTrue($result->hasErrors());
        self::assertSame(
            'Site language 2 failed: Missing target language.',
            $result->getErrorSummary()
        );
        self::assertSame(
            'Site language 2 failed: Missing target language.',
            $result->getSkippedReasonSummary()
        );
    }

    public function testWarningIsAvailableAsSkippedReasonAndSurvivesMerge(): void
    {
        $warningResult = new TranslationResult();
        $warningResult->addWarning('No translatable fields are configured.');
        $result = new TranslationResult();

        $result->merge($warningResult);

        self::assertTrue($result->hasWarnings());
        self::assertSame(
            'No translatable fields are configured.',
            $result->getSkippedReasonSummary()
        );
    }

    public function testAssumedSuccessfulResultSurvivesMerge(): void
    {
        $legacyResult = new TranslationResult();
        $legacyResult->markAsAssumedSuccessful();
        $result = new TranslationResult();

        $result->merge($legacyResult);

        self::assertTrue($result->hasTranslations());
        self::assertTrue($result->isAssumedSuccessful());
        self::assertSame(0, $result->getTranslatedFieldCount());
    }
}
