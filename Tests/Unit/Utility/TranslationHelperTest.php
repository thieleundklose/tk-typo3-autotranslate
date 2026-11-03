<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;

final class TranslationHelperTest extends TestCase
{
    public function testExtractChangedFieldsReturnsNullForNewRecords(): void
    {
        self::assertNull(TranslationHelper::extractChangedFieldsFromDatamap('new', [
            'title' => 'Hello',
            'hidden' => 0,
        ]));
    }

    public function testExtractChangedFieldsReturnsDatamapKeysForUpdates(): void
    {
        self::assertSame(
            ['title', 'hidden'],
            TranslationHelper::extractChangedFieldsFromDatamap('update', [
                'title' => 'World',
                'hidden' => 1,
            ])
        );
    }

    public function testExtractChangedFieldsReturnsEmptyArrayForUpdateWithoutFields(): void
    {
        self::assertSame([], TranslationHelper::extractChangedFieldsFromDatamap('update', []));
    }

    public function testExtractChangedFieldsIgnoresSubmittedValuesThatDidNotChange(): void
    {
        self::assertSame(
            [],
            TranslationHelper::extractChangedFieldsFromDatamap(
                'update',
                ['header' => 'Unchanged headline', 'hidden' => 0],
                ['header' => 'Unchanged headline', 'hidden' => '0']
            )
        );
    }

    public function testExtractChangedFieldsReturnsOnlyValuesThatActuallyChanged(): void
    {
        self::assertSame(
            ['header'],
            TranslationHelper::extractChangedFieldsFromDatamap(
                'update',
                ['header' => 'New headline', 'bodytext' => 'Unchanged body'],
                ['header' => 'Old headline', 'bodytext' => 'Unchanged body']
            )
        );
    }

    public function testFilterChangedTranslatableColumnsReturnsAllColumnsWhenChangedFieldsAreNull(): void
    {
        self::assertSame(
            ['title', 'description'],
            TranslationHelper::filterChangedTranslatableColumns(['title', 'description'], null)
        );
    }

    public function testFilterChangedTranslatableColumnsReturnsOnlyChangedTranslatableColumns(): void
    {
        self::assertSame(
            ['title'],
            TranslationHelper::filterChangedTranslatableColumns(
                ['title', 'description', 'teaser'],
                ['title', 'hidden', 'tstamp']
            )
        );
    }

    public function testFilterChangedTranslatableColumnsReturnsEmptyArrayForStatusOnlyUpdates(): void
    {
        self::assertSame(
            [],
            TranslationHelper::filterChangedTranslatableColumns(
                ['title', 'description'],
                ['hidden', 'starttime', 'endtime']
            )
        );
    }

    public function testFilterChangedTranslatableColumnsPreservesConfiguredColumnOrder(): void
    {
        self::assertSame(
            ['title', 'description'],
            TranslationHelper::filterChangedTranslatableColumns(
                ['title', 'teaser', 'description'],
                ['description', 'title']
            )
        );
    }

    public function testFilterReferenceColumnsKeepsColumnsTheFormSubmitted(): void
    {
        self::assertSame(
            ['title'],
            TranslationHelper::filterReferenceColumnsToTranslate(
                ['title'],
                ['title' => 'Changed source text'],
                ['title' => 'Hand corrected translation'],
                ['title' => 'Changed source text']
            )
        );
    }

    public function testFilterReferenceColumnsKeepsColumnsThatStillHoldTheSourceValue(): void
    {
        self::assertSame(
            ['title', 'text'],
            TranslationHelper::filterReferenceColumnsToTranslate(
                ['title', 'text'],
                ['hidden' => 0],
                ['title' => 'Source title', 'text' => 'Source text'],
                ['title' => 'Source title', 'text' => 'Source text']
            )
        );
    }

    public function testFilterReferenceColumnsDropsColumnsThatWereTranslatedBefore(): void
    {
        self::assertSame(
            ['text'],
            TranslationHelper::filterReferenceColumnsToTranslate(
                ['title', 'text'],
                ['hidden' => 0],
                ['title' => 'Hand corrected title', 'text' => 'Source text'],
                ['title' => 'Source title', 'text' => 'Source text']
            )
        );
    }

    public function testFilterReferenceColumnsReturnsAllColumnsWithoutSubmittedFormData(): void
    {
        self::assertSame(
            ['title', 'text'],
            TranslationHelper::filterReferenceColumnsToTranslate(
                ['title', 'text'],
                null,
                ['title' => 'Hand corrected title', 'text' => 'Source text'],
                ['title' => 'Source title', 'text' => 'Source text']
            )
        );
    }

    public function testFilterReferenceColumnsReturnsAllColumnsWithoutTranslationRecord(): void
    {
        self::assertSame(
            ['title', 'text'],
            TranslationHelper::filterReferenceColumnsToTranslate(
                ['title', 'text'],
                ['hidden' => 0],
                null,
                ['title' => 'Source title', 'text' => 'Source text']
            )
        );
    }
}
