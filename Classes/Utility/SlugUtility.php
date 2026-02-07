<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class for slug generation
 */
final class SlugUtility
{
    /**
     * Get slug fields that should be generated for new items
     *
     * @return array<string, array> Array of slug field configurations keyed by field name
     */
    public static function slugFields(string $table): array
    {
        $slugFields = [];
        $columns = $GLOBALS['TCA'][$table]['columns'] ?? [];

        foreach ($columns as $fieldName => $fieldConfig) {
            if (($fieldConfig['config']['type'] ?? '') === 'slug') {
                $slugFields[$fieldName] = $fieldConfig['config'];
            }
        }

        return $slugFields;
    }

    /**
     * Generate slug value for a specific field
     *
     * @param array $record The record data
     * @param string $table The table name
     * @param string $field The slug field name
     * @return string|null The generated slug or null on failure
     */
    public static function generateSlug(array $record, string $table, string $field): ?string
    {
        $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? null;

        if ($fieldConfig === null) {
            return null;
        }

        try {
            $slugHelper = GeneralUtility::makeInstance(
                SlugHelper::class,
                $table,
                $field,
                $fieldConfig
            );

            $recordState = RecordStateFactory::forName($table)
                ->fromArray($record, $record['pid'] ?? 0, $record['uid'] ?? 0);

            $slug = $slugHelper->generate($record, $record['pid'] ?? 0);

            return $slugHelper->buildSlugForUniqueInSite($slug, $recordState);
        } catch (\Exception) {
            return null;
        }
    }
}

