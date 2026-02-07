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

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationHelper
{
    const COLUMN_TRANSLATEABLE_TABLES = ['pages', 'tt_content'];
    const COLUMN_TRANSLATEABLE_TYPES = ['text', 'input'];
    const COLUMN_TRANSLATEABLE_EXCLUDE_EVALS = ['int'];
    const COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD = 1;
    const COLUMNS_TRANSLATEABLE_GROUP_FILEREFERENCE = 2;

    /**
     * Later, the tables can be dynamically expanded.
     *
     * @return string[]
     */
    public static function tablesToTranslate(): array
    {
        return array_merge(
            self::COLUMN_TRANSLATEABLE_TABLES,
            self::additionalTables(),
        );
    }

    /**
     * Receive additional tables from extension settings
     *
     * @return array
     */
    public static function additionalTables(): array
    {
        $additionalTables = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('autotranslate', 'additionalTables');
        $tables = $additionalTables ? GeneralUtility::trimExplode(',', $additionalTables, true) : [];

        // Filter the tables to only include those that exist in $GLOBALS['TCA']
        return array_filter($tables, function ($table) {
            return isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']);
        });
    }

    /**
     * Get setting from TCA.
     *
     * @param string $table
     * @return string|null
     */
    public static function translationOrigPointerField(string $table): ?string
    {
        $tableTca = $GLOBALS['TCA'][$table] ?? null;
        $tableCtrl = $tableTca['ctrl'] ?? null;

        return $tableCtrl['transOrigPointerField'] ?? null;
    }

    /**
     * Filter not used translateable columns.
     *
     * @param string $table
     * @param string $value
     * @param int $type
     * @return array
     */
    public static function unusedTranslateableColumns(string $table, string $value, int $type): array
    {
        $translateableColumns = self::translateableColumns($table);
        $valueList = GeneralUtility::trimExplode(',', $value, true);

        return array_diff($translateableColumns[$type], $valueList);
    }

    /**
     * Collect translateable fields from TCA.
     *
     * @param string $table
     * @return array
     */
    public static function translateableColumns(string $table): array
    {
        $textColumns = array_filter($GLOBALS['TCA'][$table]['columns'], function ($v, $k) use ($table) {
            if ($table === 'sys_file_reference' && in_array($k, ['tablenames', 'fieldname', 'table_local'])) {
                return false;
            }

            $config = $v['config'];
            if (isset($config['renderType'])) {
                return false;
            }

            if (!in_array($config['type'], self::COLUMN_TRANSLATEABLE_TYPES)) {
                return false;
            }

            $evalList = GeneralUtility::trimExplode(',', $config['eval'] ?? '', true);
            $evalIntersect = array_intersect($evalList, self::COLUMN_TRANSLATEABLE_EXCLUDE_EVALS);
            if (!empty($evalIntersect)) {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $fileReferenceColumns = array_filter($GLOBALS['TCA'][$table]['columns'], function ($v) {
            $config = $v['config'];

            if (!isset($config['type']) || !isset($config['foreign_table']) || $config['foreign_table'] != 'sys_file_reference') {
                return false;
            }

            // TYPO3 13+ uses type=file for file references
            if ($config['type'] !== 'file') {
                return false;
            }

            return true;
        });

        return [
            self::COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD => array_keys($textColumns),
            self::COLUMNS_TRANSLATEABLE_GROUP_FILEREFERENCE => array_keys($fileReferenceColumns)
        ];
    }

    /**
     * Receive possible translatable languages.
     * @param array|null $siteLanguages
     * @return array
     */
    public static function possibleTranslationLanguages(?array $siteLanguages): array
    {
        if (empty($siteLanguages)) {
            return [];
        }

        return array_filter($siteLanguages, fn($k) => $k !== 0, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Receive default language from Site.
     * @param Site $site
     * @return SiteLanguage
     */
    public static function defaultLanguageFromSiteConfiguration(Site $site): SiteLanguage
    {
        return self::defaultLanguage($site->getLanguages());
    }

    /**
     * Receive default language.
     * @param array|null $siteLanguages
     * @return SiteLanguage
     * @throws SiteNotFoundException If no site languages are found
     */
    public static function defaultLanguage(?array $siteLanguages): SiteLanguage
    {
        if (empty($siteLanguages)) {
            throw new SiteNotFoundException('No site languages found.', 1633031234);
        }

        return $siteLanguages[0];
    }

    /**
     * Receive possible fields which should  be translated.
     *
     * @param int $pageId
     * @param string $table
     * @return array|null
     * @throws SiteNotFoundException
     */
    public static function translationTextfields(int $pageId, string $table): ?array
    {
        if ($pageId === 0) {
            return null;
        }

        $siteConfiguration = self::siteConfigurationValue($pageId);
        if (!is_array($siteConfiguration)) {
            return null;
        }

        $translationSettings = self::translationSettingsDefaults($siteConfiguration, $table);
        if ($translationSettings === null) {
            return null;
        }

        return GeneralUtility::trimExplode(',', $translationSettings['autotranslateTextfields'] ?? '', true);
    }

    /**
     * Receive a setting by page from site configuration.
     *
     * @param int $pageId
     * @param array|null $keyPath
     * @return array|mixed|null
     * @throws SiteNotFoundException
     */
    public static function siteConfigurationValue(int $pageId, ?array $keyPath = null): mixed
    {
        if (empty($pageId)) {
            return null;
        }

        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageId);
            $configuration = $site->getConfiguration();

            if ($keyPath === null) {
                return $configuration;
            }

            foreach ($keyPath as $key) {
                $configuration = $configuration[$key] ?? null;
            }

            return $configuration;
        } catch (SiteNotFoundException $e) {
            return null;
        }
    }

    /**
     * Receive translate configuration for a table by site configuration.
     * Todo: Check translations and columns if still exists, otherwise reduce items.
     *
     * @param array $siteConfiguration
     * @param string $table
     * @return array|true[]|null
     */
    public static function translationSettingsDefaults(array $siteConfiguration, string $table): ?array
    {
        $fieldnameAutotranslateEnabled = self::configurationFieldname($table, 'enabled');

        if ($table != 'sys_file_reference' && (!isset($siteConfiguration[$fieldnameAutotranslateEnabled]) || $siteConfiguration[$fieldnameAutotranslateEnabled] === FALSE)) {
            return null;
        }

        $fieldnameAutotranslateLanguages = self::configurationFieldname($table, 'languages');
        $fieldnameAutotranslateTextFields = self::configurationFieldname($table, 'textfields');
        $fieldnameAutotranslateFileReferences = self::configurationFieldname($table, 'fileReferences');

        return [
            'autotranslateLanguages' => $siteConfiguration[$fieldnameAutotranslateLanguages] ?? '',
            'autotranslateTextfields' => $siteConfiguration[$fieldnameAutotranslateTextFields] ?? '',
            'autotranslateFileReferences' => $siteConfiguration[$fieldnameAutotranslateFileReferences] ?? '',
        ];
    }

    /**
     * Generate fieldname to store in site configuration.
     *
     * @param string $table
     * @param string $fieldname
     * @return string
     */
    public static function configurationFieldname(string $table, string $fieldname): string
    {
        $parts = implode(
            '_',
            [
                'autotranslate',
                GeneralUtility::camelCaseToLowerCaseUnderscored($table),
                GeneralUtility::camelCaseToLowerCaseUnderscored($fieldname)
            ]
        );

        return GeneralUtility::underscoredToLowerCamelCase($parts);
    }

    /**
     * Receive possible columns of $table that are references to $referenceTable.
     *
     * @param int $pageId
     * @param string $table
     * @param string $referenceTable
     * @return array|null
     * @throws SiteNotFoundException
     */
    public static function translationReferenceColumns(int $pageId, string $table, string $referenceTable): ?array
    {
        if ($referenceTable === 'sys_file_reference') {
            return self::translationFileReferences($pageId, $table);
        }
        // Check if the table exists in TCA
        if (!isset($GLOBALS['TCA'][$table]['columns'])) {
            return null;
        }

        $referenceColumns = [];

        // Iterate through all columns of the table
        foreach ($GLOBALS['TCA'][$table]['columns'] as $columnName => $columnConfig) {
            $config = $columnConfig['config'] ?? [];

            // Check different types of references

            // 1. Inline references (IRRE - Inline Relational Record Editing)
            if (($config['type'] ?? '') === 'inline' &&
                ($config['foreign_table'] ?? '') === $referenceTable) {
                $referenceColumns[] = $columnName;
                continue;
            }

            // 2. Select references
            if (($config['type'] ?? '') === 'select' &&
                ($config['foreign_table'] ?? '') === $referenceTable) {
                $referenceColumns[] = $columnName;
                continue;
            }

            // 3. Group references (for files or records)
            if (($config['type'] ?? '') === 'group') {
                // Check allowed tables
                $allowedTables = $config['allowed'] ?? '';
                $allowedTablesArray = GeneralUtility::trimExplode(',', $allowedTables, true);

                if (in_array($referenceTable, $allowedTablesArray, true)) {
                    $referenceColumns[] = $columnName;
                    continue;
                }

                // Check foreign_table for group type
                if (($config['foreign_table'] ?? '') === $referenceTable) {
                    $referenceColumns[] = $columnName;
                    continue;
                }
            }

            // 4. File references
            if (($config['type'] ?? '') === 'file' && $referenceTable === 'sys_file_reference') {
                $referenceColumns[] = $columnName;
                continue;
            }

            // 5. Category references
            if (($config['type'] ?? '') === 'category' && $referenceTable === 'sys_category') {
                $referenceColumns[] = $columnName;
                continue;
            }

            // 6. MM references via MM table
            if (isset($config['MM']) && isset($config['foreign_table']) &&
                $config['foreign_table'] === $referenceTable) {
                $referenceColumns[] = $columnName;
                continue;
            }

            // Note: Flex form references (type=flex) are not supported
            // as diving into the flex form structure would be very complex
        }

        // Return null if no reference columns were found, otherwise return the array
        return empty($referenceColumns) ? null : $referenceColumns;
    }

    /**
     * Receive possible file references which should be translated.
     *
     * @param int $pageId
     * @param string $table
     * @return array|null
     * @throws SiteNotFoundException
     */
    public static function translationFileReferences(int $pageId, string $table): ?array
    {
        if ($pageId === 0) {
            return null;
        }

        $siteConfiguration = self::siteConfigurationValue($pageId);
        if (!is_array($siteConfiguration)) {
            return null;
        }

        $translationSettings = self::translationSettingsDefaults($siteConfiguration, $table);
        if ($translationSettings === null) {
            return null;
        }

        return GeneralUtility::trimExplode(',', $translationSettings['autotranslateFileReferences'] ?? '', true);
    }

    /**
     * Receive state to use glossary
     *
     * @param int
     * @return bool
     */
    public static function glossaryEnabled(int $pageId): bool
    {
        if (!ExtensionManagementUtility::isLoaded('deepltranslate_glossary')) {
            return false;
        }

        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageId);

            $configuration = $site->getConfiguration();
            if ($configuration['autotranslateUseDeeplGlossary'] ?? null) {
                return (bool)$configuration['autotranslateUseDeeplGlossary'];
            }
        } catch (SiteNotFoundException $e) {}

        return false;
    }

    /**
     * Receive api key by page from site configuration.
     *
     * @param int|null $pageId
     * @return string|null
     * @throws SiteNotFoundException
     */
    public static function apiKey(?int $pageId = null): array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        if (empty($pageId)) {
            // get pageId from context
            $context = GeneralUtility::makeInstance(Context::class);
            if ($context->hasAspect('page')) {
                $pageId = $context->getPropertyFromAspect('page', 'id');
            }
        }

        if ($pageId) {
            try {
                $site = $siteFinder->getSiteByPageId($pageId);
                $configuration = $site->getConfiguration();
                if ($configuration['deeplAuthKey'] ?? null) {
                    return [
                        'key' => $configuration['deeplAuthKey'],
                        'source' => 'Site configuration of page ' . $pageId
                    ];
                }
            } catch (SiteNotFoundException $e) {}
        }

        // get global apiKey from Extension Settings
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate');
        if ($extensionConfiguration['apiKey'] ?? null) {
            return [
                'key' => $extensionConfiguration['apiKey'],
                'source' => 'Extension settings of autotranslate'
            ];
        }

        // get global apiKey from 3rd party Extension Settings as fallback
        if (ExtensionManagementUtility::isLoaded('deepltranslate_glossary')) {
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('deepltranslate_core');
            if ($extensionConfiguration['apiKey'] ?? null) {
                return [
                    'key' => $extensionConfiguration['apiKey'],
                    'source' => 'Extension settings of deepltranslate_core'
                ];
            }
        }

        // get first apiKey from site configuration
        $sites = $siteFinder->getAllSites();
        foreach ($sites as $site) {
            $configuration = $site->getConfiguration();
            if ($configuration['deeplAuthKey'] ?? null) {
                return [
                    'key' => $configuration['deeplAuthKey'],
                    'source' => 'Site configuration of page ' . $site->getRootPageId()
                ];
            }
        }
        return [
            'key' => null,
            'source' => null
        ];
    }

    /**
     * Receive additional tables from extension settings
     *
     * @return array
     */
    public static function additionalReferenceTables(): array
    {
        $additionalReferenceTables = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('autotranslate', 'additionalReferenceTables');
        $tables = $additionalReferenceTables ? GeneralUtility::trimExplode(',', $additionalReferenceTables, true) : [];

        // Filter the tables to only include those that exist in $GLOBALS['TCA']
        return array_filter($tables, function ($table) {
            return isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']);
        });
    }
}
