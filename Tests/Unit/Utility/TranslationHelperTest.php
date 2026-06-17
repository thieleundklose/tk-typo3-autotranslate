<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;

/**
 * Unit tests for TranslationHelper::filterChangedTranslatableColumns().
 *
 * The helper decides which translatable columns have to be handed over to
 * the translator based on the DataHandler datamap of the current save.
 */
final class TranslationHelperTest extends TestCase
{
    /**
     * A 'new' datamap status means the record is being created. The full
     * set of translatable columns must be translated on first save, so
     * the helper returns null to signal "no restriction".
     */
    public function testExtractChangedFieldsReturnsNullForNewRecords(): void
    {
        $result = TranslationHelper::extractChangedFieldsFromDatamap(
            'new',
            ['title' => 'Hello', 'hidden' => 0]
        );

        self::assertNull($result);
    }

    /**
     * On updates the helper returns the datamap keys so the caller can
     * intersect them with the configured translatable columns.
     */
    public function testExtractChangedFieldsReturnsDatamapKeysForUpdates(): void
    {
        $result = TranslationHelper::extractChangedFieldsFromDatamap(
            'update',
            ['title' => 'World', 'hidden' => 1]
        );

        self::assertSame(['title', 'hidden'], $result);
    }

    /**
     * An update with an empty datamap yields an empty list of changed
     * fields, which downstream is treated as "nothing translatable
     * changed" — no DeepL round-trip.
     */
    public function testExtractChangedFieldsReturnsEmptyArrayForUpdateWithoutFields(): void
    {
        $result = TranslationHelper::extractChangedFieldsFromDatamap('update', []);

        self::assertSame([], $result);
    }

    /**
     * A null changed-fields list represents a new record: the full set of
     * configured translatable columns must be returned so everything gets
     * translated on first save (existing behaviour).
     */
    public function testReturnsAllConfiguredColumnsWhenChangedFieldsIsNull(): void
    {
        $result = TranslationHelper::filterChangedTranslatableColumns(
            ['title', 'description'],
            null
        );

        self::assertSame(['title', 'description'], $result);
    }

    /**
     * An update that only touches non-translatable status fields
     * (e.g. hidden via a cron job) must yield an empty result so the
     * caller can skip the translation round-trip entirely.
     */
    public function testReturnsEmptyWhenOnlyNonTranslatableFieldsChanged(): void
    {
        $result = TranslationHelper::filterChangedTranslatableColumns(
            ['title', 'description'],
            ['hidden', 'starttime', 'tstamp']
        );

        self::assertSame([], $result);
    }

    /**
     * An update touching both translatable and non-translatable fields
     * must return only the translatable subset.
     */
    public function testReturnsOnlyChangedTranslatableColumns(): void
    {
        $result = TranslationHelper::filterChangedTranslatableColumns(
            ['title', 'description', 'teaser'],
            ['title', 'hidden', 'tstamp']
        );

        self::assertSame(['title'], $result);
    }

    /**
     * An explicit empty changed-fields list (as opposed to null) means
     * an update with no recorded changes — still nothing to translate.
     */
    public function testReturnsEmptyWhenChangedFieldsIsEmpty(): void
    {
        $result = TranslationHelper::filterChangedTranslatableColumns(
            ['title', 'description'],
            []
        );

        self::assertSame([], $result);
    }

    /**
     * Without configured translatable columns, every call returns an
     * empty list — there is nothing that could be translated.
     */
    public function testReturnsEmptyWhenNoTranslatableColumnsConfigured(): void
    {
        self::assertSame(
            [],
            TranslationHelper::filterChangedTranslatableColumns([], ['title', 'hidden'])
        );
        self::assertSame(
            [],
            TranslationHelper::filterChangedTranslatableColumns([], null)
        );
    }

    /**
     * The result order follows the configured translatable columns, not
     * the order of the changed-fields input, so downstream processing
     * (cache keys, DeepL batch order) stays stable across call sites.
     */
    public function testResultOrderFollowsConfiguredColumns(): void
    {
        $result = TranslationHelper::filterChangedTranslatableColumns(
            ['title', 'teaser', 'description'],
            ['description', 'title']
        );

        self::assertSame(['title', 'description'], $result);
    }
}
