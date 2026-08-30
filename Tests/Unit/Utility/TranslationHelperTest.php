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
}
