<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TranslationHelper
{
    public const COLUMN_TRANSLATEABLE_TABLES = ['pages', 'tt_content'];
    public const COLUMN_TRANSLATEABLE_TYPES = ['text', 'input'];
    public const COLUMN_TRANSLATEABLE_EXCLUDE_EVALS = ['int'];
    public const COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD = 1;
    public const COLUMNS_TRANSLATEABLE_GROUP_FILEREFERENCE = 2;

    /**
     * @return string[]
     */
    public static function tablesToTranslate(): array
    {
        return array_merge(
            self::COLUMN_TRANSLATEABLE_TABLES,
            self::additionalTables(),
        );
    }

    public static function additionalTables(): array
    {
        $additionalTables = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('autotranslate', 'additionalTables');
        $tables = $additionalTables ? GeneralUtility::trimExplode(',', $additionalTables, true) : [];

        return array_filter($tables, static fn(string $table): bool => isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']));
    }

    public static function translationOrigPointerField(string $table): ?string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null;
    }

    public static function unusedTranslateableColumns(string $table, string $value, int $type): array
    {
        $translateableColumns = self::translateableColumns($table);
        $valueList = GeneralUtility::trimExplode(',', $value, true);

        return array_diff($translateableColumns[$type], $valueList);
    }

    public static function translateableColumns(string $table): array
    {
        $textColumns = array_filter($GLOBALS['TCA'][$table]['columns'], static function (array $v, string $k) use ($table): bool {
            if ($table === 'sys_file_reference' && in_array($k, ['tablenames', 'fieldname', 'table_local'], true)) {
                return false;
            }

            $config = $v['config'];
            if (isset($config['renderType'])) {
                return false;
            }

            if (!in_array($config['type'], self::COLUMN_TRANSLATEABLE_TYPES, true)) {
                return false;
            }

            $evalList = GeneralUtility::trimExplode(',', $config['eval'] ?? '', true);

            return array_intersect($evalList, self::COLUMN_TRANSLATEABLE_EXCLUDE_EVALS) === [];
        }, ARRAY_FILTER_USE_BOTH);

        $fileReferenceColumns = array_filter($GLOBALS['TCA'][$table]['columns'], static function (array $v): bool {
            $config = $v['config'];

            return isset($config['type'])
                && ($config['foreign_table'] ?? '') === 'sys_file_reference'
                && $config['type'] === 'file';
        });

        return [
            self::COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD => array_keys($textColumns),
            self::COLUMNS_TRANSLATEABLE_GROUP_FILEREFERENCE => array_keys($fileReferenceColumns),
        ];
    }

    public static function possibleTranslationLanguages(?array $siteLanguages): array
    {
        if (empty($siteLanguages)) {
            return [];
        }

        return array_filter($siteLanguages, static fn(int $k): bool => $k !== 0, ARRAY_FILTER_USE_KEY);
    }

    public static function defaultLanguageFromSiteConfiguration(Site $site): SiteLanguage
    {
        return self::defaultLanguage($site->getLanguages());
    }

    /**
     * @throws SiteNotFoundException
     */
    public static function defaultLanguage(?array $siteLanguages): SiteLanguage
    {
        if (empty($siteLanguages)) {
            throw new SiteNotFoundException('No site languages found.', 1633031234);
        }

        return $siteLanguages[0];
    }

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

    public static function siteConfigurationValue(int $pageId, ?array $keyPath = null): mixed
    {
        if ($pageId === 0) {
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
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    public static function translationSettingsDefaults(array $siteConfiguration, string $table): ?array
    {
        $fieldnameAutotranslateEnabled = self::configurationFieldname($table, 'enabled');

        if ($table !== 'sys_file_reference' && (!isset($siteConfiguration[$fieldnameAutotranslateEnabled]) || $siteConfiguration[$fieldnameAutotranslateEnabled] === false)) {
            return null;
        }

        return [
            'autotranslateLanguages' => $siteConfiguration[self::configurationFieldname($table, 'languages')] ?? '',
            'autotranslateTextfields' => $siteConfiguration[self::configurationFieldname($table, 'textfields')] ?? '',
            'autotranslateFileReferences' => $siteConfiguration[self::configurationFieldname($table, 'fileReferences')] ?? '',
        ];
    }

    public static function configurationFieldname(string $table, string $fieldname): string
    {
        $parts = implode('_', [
            'autotranslate',
            GeneralUtility::camelCaseToLowerCaseUnderscored($table),
            GeneralUtility::camelCaseToLowerCaseUnderscored($fieldname),
        ]);

        return GeneralUtility::underscoredToLowerCamelCase($parts);
    }

    /**
     * Get reference columns from $table pointing to $referenceTable.
     */
    public static function translationReferenceColumns(int $pageId, string $table, string $referenceTable): ?array
    {
        if ($referenceTable === 'sys_file_reference') {
            return self::translationFileReferences($pageId, $table);
        }

        if (!isset($GLOBALS['TCA'][$table]['columns'])) {
            return null;
        }

        $referenceColumns = [];

        foreach ($GLOBALS['TCA'][$table]['columns'] as $columnName => $columnConfig) {
            $config = $columnConfig['config'] ?? [];
            $type = $config['type'] ?? '';
            $foreignTable = $config['foreign_table'] ?? '';

            if (($type === 'inline' || $type === 'select') && $foreignTable === $referenceTable) {
                $referenceColumns[] = $columnName;
                continue;
            }

            if ($type === 'group') {
                $allowedTablesArray = GeneralUtility::trimExplode(',', $config['allowed'] ?? '', true);
                if (in_array($referenceTable, $allowedTablesArray, true) || $foreignTable === $referenceTable) {
                    $referenceColumns[] = $columnName;
                    continue;
                }
            }

            if ($type === 'file' && $referenceTable === 'sys_file_reference') {
                $referenceColumns[] = $columnName;
                continue;
            }

            if ($type === 'category' && $referenceTable === 'sys_category') {
                $referenceColumns[] = $columnName;
                continue;
            }

            if (isset($config['MM']) && $foreignTable === $referenceTable) {
                $referenceColumns[] = $columnName;
            }
        }

        return $referenceColumns === [] ? null : $referenceColumns;
    }

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

    public static function glossaryEnabled(int $pageId): bool
    {
        if (!ExtensionManagementUtility::isLoaded('deepltranslate_glossary')) {
            return false;
        }

        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
            return (bool)($site->getConfiguration()['autotranslateUseDeeplGlossary'] ?? false);
        } catch (SiteNotFoundException) {
            return false;
        }
    }

    /**
     * Resolve API key from site configuration, extension settings, or 3rd party extension.
     *
     * @return array{key: ?string, source: ?string}
     */
    public static function apiKey(?int $pageId = null): array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        if (empty($pageId)) {
            $context = GeneralUtility::makeInstance(Context::class);
            if ($context->hasAspect('page')) {
                $pageId = $context->getPropertyFromAspect('page', 'id');
            }
        }

        if ($pageId) {
            try {
                $configuration = $siteFinder->getSiteByPageId($pageId)->getConfiguration();
                if (!empty($configuration['deeplAuthKey'])) {
                    return ['key' => $configuration['deeplAuthKey'], 'source' => 'Site configuration of page ' . $pageId];
                }
            } catch (SiteNotFoundException) {
            }
        }

        // Global API key from extension settings
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate');
        if (!empty($extensionConfiguration['apiKey'])) {
            return ['key' => $extensionConfiguration['apiKey'], 'source' => 'Extension settings of autotranslate'];
        }

        // Fallback: 3rd party extension settings
        if (ExtensionManagementUtility::isLoaded('deepltranslate_glossary')) {
            $deeplConfig = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('deepltranslate_core');
            if (!empty($deeplConfig['apiKey'])) {
                return ['key' => $deeplConfig['apiKey'], 'source' => 'Extension settings of deepltranslate_core'];
            }
        }

        // Fallback: first site with a key
        foreach ($siteFinder->getAllSites() as $site) {
            $configuration = $site->getConfiguration();
            if (!empty($configuration['deeplAuthKey'])) {
                return ['key' => $configuration['deeplAuthKey'], 'source' => 'Site configuration of page ' . $site->getRootPageId()];
            }
        }

        return ['key' => null, 'source' => null];
    }

    public static function additionalReferenceTables(): array
    {
        $additionalReferenceTables = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('autotranslate', 'additionalReferenceTables');
        $tables = $additionalReferenceTables ? GeneralUtility::trimExplode(',', $additionalReferenceTables, true) : [];

        return array_filter($tables, static fn(string $table): bool => isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']));
    }
}
