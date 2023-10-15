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

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

class TranslationHelper {

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
    public static function translateableTables() : array
    {
        return array_merge(
            self::COLUMN_TRANSLATEABLE_TABLES,
            ExtensionManagementUtility::isLoaded('tx_news') ? ['tx_news_domain_model_news'] : []
        );
    }

    /**
     * Collect translateable fields from TCA.
     *
     * @param string $table
     * @return array
     */
    public static function translateableColumns(string $table) : array
    {
        $textColumns = array_filter($GLOBALS['TCA'][$table]['columns'], function($v, $k) use ($table) {

            if ($table == 'sys_file_reference' && in_array($k, ['tablenames', 'fieldname', 'table_local'])) {
                return;
            }

            $config = $v['config'];
            if (isset($config['renderType'])) {
                return;
            }

            if (!in_array($config['type'], self::COLUMN_TRANSLATEABLE_TYPES)) {
                return;
            }

            $evalList = GeneralUtility::trimExplode(',', $config['eval'] ?? '', true);
            $evalIntersect = array_intersect($evalList, self::COLUMN_TRANSLATEABLE_EXCLUDE_EVALS);
            if (!empty($evalIntersect)) {
                return;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $fileReferenceColumns = array_filter($GLOBALS['TCA'][$table]['columns'], function($v) {
            $config = $v['config'];

            if (!isset($config['type']) || !isset($config['foreign_table']) || $config['foreign_table'] != 'sys_file_reference') {
                return false;
            }

            if (VersionNumberUtility::convertVersionStringToArray((new Typo3Version())->getVersion())['version_main'] > 11) {
                if ($config['type'] != 'file') {
                    return false;
                }
            } else {
                if ($config['type'] != 'inline') {
                    return false;
                }
            }

            return true;
        });

        return [
            self::COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD => array_keys($textColumns),
            self::COLUMNS_TRANSLATEABLE_GROUP_FILEREFERENCE => array_keys($fileReferenceColumns)
        ];
    }

    /**
     * Get setting from TCA.
     *
     * @param string $table
     * @return string|null
     */
    public static function translationOrigPointerField(string $table) : ?string
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
    public static function unusedTranslateableColumns(string $table, string $value, int $type) : array
    {
        $translateableColumns = self::translateableColumns($table);
        $valueList = GeneralUtility::trimExplode(',', $value, true);

        return array_diff($translateableColumns[$type], $valueList);
    }

    /**
     * Receive possible translatable languages.
     * @param array|null $siteLanguages
     * @return array
     */
    public static function possibleTranslationLanguages(?array $siteLanguages) : array
    {
        if (empty($siteLanguages)) {
            return [];
        }
        $languages = array_filter($siteLanguages, function($k) {
            if ($k === 0) {
                return;
            }
            return true;
        }, ARRAY_FILTER_USE_KEY);

        return $languages;
    }


    /**
     * Receive translate configuration for a table by site configuration.
     * Todo: Check translations and columns if still exists, otherwise reduce items.
     *
     * @param array $siteConfiguration
     * @param string $table
     * @return array|true[]|null
     */
    public static function translationSettingsDefaults(array $siteConfiguration, string $table) : ?array
    {
        $fieldnameAutotranslateEnabled = self::configurationFieldname($table,'enabled');
        $fieldnameAutotranslateLanguages = self::configurationFieldname($table,'languages');
        $fieldnameAutotranslateTextFields = self::configurationFieldname($table,'textfields');
        $fieldnameAutotranslateFileReferences = self::configurationFieldname($table,'fileReferences');

        if (!isset($siteConfiguration[$fieldnameAutotranslateEnabled]) || $siteConfiguration[$fieldnameAutotranslateEnabled] === FALSE || $table == 'sys_file_reference') {
            return null;
        }

        return [
            'autotranslateLanguages' => $siteConfiguration[$fieldnameAutotranslateLanguages],
            'autotranslateTextfields' => $siteConfiguration[$fieldnameAutotranslateTextFields],
            'autotranslateFileReferences' => $siteConfiguration[$fieldnameAutotranslateFileReferences] ?? NULL,
        ];
    }

    /**
     * Receive possible fields which should  be translated.
     *
     * @param int $pageId
     * @param string $table
     * @return array|null
     * @throws SiteNotFoundException
     */
    public static function translationTextfields(int $pageId, string $table) : ?array {
        if ($pageId === 0) {
            return null;
        }

        $siteConfiguration = self::siteConfigurationValue($pageId);
        $translationSettings = TranslationHelper::translationSettingsDefaults($siteConfiguration, $table);
        return GeneralUtility::trimExplode(',', $translationSettings['autotranslateTextfields'], true);
    }

    /**
     * Receive possible file references which should be translated.
     *
     * @param int $pageId
     * @param string $table
     * @return array|null
     * @throws SiteNotFoundException
     */
    public static function translationFileReferences(int $pageId, string $table) : ?array {

        if ($pageId === 0) {
            return null;
        }

        $siteConfiguration = self::siteConfigurationValue($pageId);
        $translationSettings = TranslationHelper::translationSettingsDefaults($siteConfiguration, $table);
        return GeneralUtility::trimExplode(',', $translationSettings['autotranslateFileReferences'], true);
    }

    /**
     * Generate fieldname to store in site configuration.
     *
     * @param string $table
     * @param string $fieldname
     * @return string
     */
    public static function configurationFieldname(string $table, string $fieldname) : string
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
     * Receive a setting by page from site configuration.
     *
     * @param int $pageId
     * @param array|null $keyPath
     * @return array|mixed|null
     * @throws SiteNotFoundException
     */
    public static function siteConfigurationValue(int $pageId, array $keyPath = null)
    {
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
    }

    /**
     * Receive api key by page from site configuration.
     *
     * @param int|null $pageId
     * @return string|null
     * @throws SiteNotFoundException
     */
    public static function apiKey(int $pageId = null) : ?string
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        if (is_null($pageId)) {
            // get first apiKey from site configuration
            $sites = $siteFinder->getAllSites();
            foreach ($sites as $site) {
                $configuration = $site->getConfiguration();
                if (!empty($configuration['deeplAuthKey'])) {
                    return $configuration['deeplAuthKey'];
                }
            }
        } else {
            $site = $siteFinder->getSiteByPageId($pageId);
            $configuration = $site->getConfiguration();
        }

        return $configuration['deeplAuthKey'] ?? null;
    }

    /**
     * Receive possible languages by page.
     *
     * @return SiteLanguage[]
     * @throws SiteNotFoundException
     */
    public static function fetchSysLanguages()
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $request = self::getRequest();
        $parsedBody = $request->getParsedBody();

        $pid = 0;
        if (empty($pid) && isset($parsedBody['popViewId'])) {
            $pid = $parsedBody['popViewId'];
        }

        if (empty($pid) && isset($parsedBody['effectivePid'])) {
            $pid = $parsedBody['effectivePid'];
        }

        if (empty($pid) && is_array($parsedBody['data']) && is_array($parsedBody['data']['pages'])) { // on page insert
            $pageRecord = current($parsedBody['data']['pages']);
            if (isset($pageRecord['pid'])) {
                $pid = $pageRecord['pid'];
            }
        }

        // on page update
        if (empty($pid)) {
            $queryParams = $request->getQueryParams();
            if (is_array($queryParams) && is_array($queryParams['data']) && is_array($queryParams['data']['pages'])) {
                $pid = current(array_keys($queryParams['data']['pages']));
            }
        }

        if (empty((int)$pid)) {
            return [];
        }

        $site = $siteFinder->getSiteByPageId((int)$pid);
        $languages = $site->getLanguages();
        ksort($languages);

        return $languages;
    }

    /**
     * Get request interface.
     *
     * @return ServerRequestInterface
     */
    private static function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
