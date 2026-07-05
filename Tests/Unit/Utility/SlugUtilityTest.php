<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Utility\SlugUtility;

/**
 * Unit tests for SlugUtility.
 *
 * Verifies that slug field detection and generation correctly handles
 * the TCA 'exclude' flag and missing 'eval' configuration.
 *
 * @see https://github.com/thieleundklose/tk-typo3-autotranslate/issues/108
 */
final class SlugUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset TCA for each test
        unset($GLOBALS['TCA']['test_table']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TCA']['test_table']);
        parent::tearDown();
    }

    /**
     * Verifies that slugFields() returns columns whose TCA type is 'slug'
     * and ignores columns with other types.
     */
    public function testSlugFieldsReturnsOnlySlugTypeColumns(): void
    {
        $GLOBALS['TCA']['test_table']['columns'] = [
            'title' => [
                'config' => ['type' => 'input'],
            ],
            'slug' => [
                'config' => ['type' => 'slug', 'generatorOptions' => ['fields' => ['title']]],
            ],
            'description' => [
                'config' => ['type' => 'text'],
            ],
        ];

        $result = SlugUtility::slugFields('test_table');

        self::assertIsArray($result);
        self::assertArrayHasKey('slug', $result);
        self::assertArrayNotHasKey('title', $result);
        self::assertArrayNotHasKey('description', $result);
    }

    /**
     * Verifies that generateSlug() returns null when the slug field has
     * 'exclude' => true in TCA. Fields marked as excluded should not have
     * their slugs auto-generated during translation.
     *
     * This is the primary regression test for issue #108.
     */
    public function testGenerateSlugReturnsNullForExcludedField(): void
    {
        $GLOBALS['TCA']['test_table']['columns'] = [
            'slug' => [
                'exclude' => true,
                'config' => [
                    'type' => 'slug',
                    'generatorOptions' => ['fields' => ['title']],
                ],
            ],
        ];

        $record = ['uid' => 1, 'pid' => 1, 'title' => 'Test Title', 'slug' => ''];
        $result = SlugUtility::generateSlug($record, 'test_table', 'slug');

        self::assertNull($result, 'generateSlug() must return null for excluded slug fields');
    }

    /**
     * Verifies that generateSlug() returns null when the requested field
     * does not exist in the TCA slug fields.
     */
    public function testGenerateSlugReturnsNullForNonExistentField(): void
    {
        $GLOBALS['TCA']['test_table']['columns'] = [
            'slug' => [
                'config' => ['type' => 'slug', 'generatorOptions' => ['fields' => ['title']]],
            ],
        ];

        $record = ['uid' => 1, 'pid' => 1, 'title' => 'Test'];
        $result = SlugUtility::generateSlug($record, 'test_table', 'nonexistent_field');

        self::assertNull($result);
    }

    /**
     * Verifies that generateSlug() returns null when the table has no
     * slug fields at all.
     */
    public function testGenerateSlugReturnsNullForTableWithoutSlugFields(): void
    {
        $GLOBALS['TCA']['test_table']['columns'] = [
            'title' => [
                'config' => ['type' => 'input'],
            ],
        ];

        $record = ['uid' => 1, 'pid' => 1, 'title' => 'Test'];
        $result = SlugUtility::generateSlug($record, 'test_table', 'slug');

        self::assertNull($result);
    }
}
