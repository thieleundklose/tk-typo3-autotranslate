<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ThieleUndKlose\Autotranslate\Utility;

use ThieleUndKlose\Autotranslate\Hooks\DataHandler as AutotranslateDataHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugUtility
{
    public static function usesTranslatedFields(array $slugFields, array $translatedFields, array $previousRecord = []): bool
    {
        foreach ($slugFields as $slugField) {
            foreach ($slugField['config']['generatorOptions']['fields'] ?? [] as $fieldNames) {
                foreach (GeneralUtility::trimExplode(',', implode(',', (array)$fieldNames), true) as $fieldName) {
                    if (
                        array_key_exists($fieldName, $translatedFields)
                        && (!array_key_exists($fieldName, $previousRecord)
                            || (string)$previousRecord[$fieldName] !== (string)$translatedFields[$fieldName])
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** Store a non-empty slug through DataHandler so EXT:redirects sees the change. */
    public static function updatePageSlug(int $uid, string $slug): bool
    {
        $previousSlug = Records::getRecord('pages', $uid, 'slug');
        if ($previousSlug === null || $slug === '' || $slug === $previousSlug) {
            return false;
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        AutotranslateDataHandler::runWithSuspendedHook(static function () use ($dataHandler, $uid, $slug): void {
            $dataHandler->start(['pages' => [$uid => ['slug' => $slug]]], []);
            $dataHandler->process_datamap();
        });

        if ($dataHandler->errorLog !== []) {
            throw new \RuntimeException(implode(' ', $dataHandler->errorLog));
        }

        if (Records::getRecord('pages', $uid, 'slug') === $previousSlug) {
            throw new \RuntimeException(sprintf('TYPO3 did not update the slug of page %d.', $uid));
        }

        return true;
    }

    /** Keep a page's own path segment while replacing an obsolete parent path. */
    public static function replaceParentPrefix(string $slug, string $newParentSlug, array $oldParentSlugs): ?string
    {
        $newPrefix = rtrim($newParentSlug, '/');
        if (strpos($slug, $newPrefix . '/') === 0) {
            return null;
        }

        usort($oldParentSlugs, static function (string $a, string $b): int {
            return strlen($b) <=> strlen($a);
        });
        foreach ($oldParentSlugs as $oldParentSlug) {
            $oldPrefix = rtrim($oldParentSlug, '/');
            if ($oldPrefix !== '' && strpos($slug, $oldPrefix . '/') === 0) {
                return $newPrefix . substr($slug, strlen($oldPrefix));
            }
        }

        return null;
    }

    /**
     * Receive possible slug fields which should be generated for new items.
     *
     * @param string $table
     * @return array|null
     */
    public static function slugFields(string $table): ?array
    {

        $slugFields = array_filter($GLOBALS['TCA'][$table]['columns'], function($v) {
            return isset($v['config']['type']) && $v['config']['type'] == 'slug' ? true : false;
        });

        return $slugFields;
    }

    /**
     * @param array $record
     * @param string $tableName
     * @param string $field
     * @return string|null
     */
    public static function generateSlug(array $record, string $tableName, string $field, ?array $slugFields = null): ?string
    {
        $slugFields ??= self::slugFields($tableName);

        if (empty($slugFields) || !isset($slugFields[$field]) || ($slugFields[$field]['exclude'] ?? false)) {
            return null;
        }

        $fieldConfig = $slugFields[$field]['config'];

        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $tableName,
            $field,
            $fieldConfig
        );

        $evalInfo = GeneralUtility::trimExplode(',', $fieldConfig['eval'] ?? '', true);
        $state = RecordStateFactory::forName($tableName)->fromArray($record, $record['pid'], $record['uid']);

        // Generate slug
        $slug = $slugHelper->generate($record, (int)$record['pid']);

        // build slug depending on eval configuration
        if (in_array('uniqueInSite', $evalInfo)) {
            $slug = $slugHelper->buildSlugForUniqueInSite($slug, $state);
        } else if (in_array('uniqueInPid', $evalInfo)) {
            $slug = $slugHelper->buildSlugForUniqueInPid($slug, $state);
        } else if (in_array('unique', $evalInfo)) {
            $slug = $slugHelper->buildSlugForUniqueInTable($slug, $state);
        }

        return $slug;
    }
}
