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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Translator {

    const AUTOTRANSLATE_LAST = 'autotranslate_last';
    const AUTOTRANSLATE_EXCLUDE = 'autotranslate_exclude';
    const AUTOTRANSLATE_LANGUAGES = 'autotranslate_languages';

    public $languages = [];
    public $siteLanguages = [];
    public $logger = null;
    protected $apiKey = null;

    /**
     * object constructor
     *
     * @return void
     */
    function __construct() {
        $this->logger = GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        // load languages from TYPO3 configuration
        $this->languages = TranslationHelper::fetchSysLanguages();
    }

    /**
     * Translate the loaded record to target languages
     *
     * @param string $table
     * @param int $recordUid
     * @param array $columns
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function translate(string $table, int $recordUid, int $pageId) : void
    {

        $record = Records::getRecord($table, $recordUid);

        // set api key
        $this->apiKey = TranslationHelper::apiKey($pageId);
        if ($this->apiKey === null) {
            return;
        }

        // set site languages by pageId
        $this->siteLanguages = TranslationHelper::siteConfigurationValue($pageId, ['languages']);

        // load translation columns for table
        $columns = TranslationHelper::translationTextfields($pageId, $table);
        if ($columns === null) {
            return;
        }

        // exit if record is localized one
        $parentField = TranslationHelper::translationOrigPointerField($table);
        if ($parentField === null || $record[$parentField] > 0) {
            return;
        }

        // exit if record is marked for exclude
        if ($record[self::AUTOTRANSLATE_EXCLUDE] === 1) {
            return;
        }

        // set target languages by record if null is given
        $languagesToTranslate = $record[self::AUTOTRANSLATE_LANGUAGES] ?? '';

        // loop over all target languages
        $languageIds = GeneralUtility::trimExplode(',', $languagesToTranslate, true);
        foreach ($languageIds as $languageId) {

            // Skip translation if language matches original record
            if ((int)$languageId === $record['sys_language_uid']) {
                continue;
            }

            $existingTranslation = Records::getRecordTranslation($table, $recordUid, (int)$languageId);

            // Skip this record if source record is not updated
            // @Todo: check if needed
            if (isset($existingTranslation[self::AUTOTRANSLATE_LAST]) && $record['tstamp'] < $existingTranslation[self::AUTOTRANSLATE_LAST]) {
                continue;
            }

            if (!$existingTranslation) {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start([], []);
                $localizedUid = $dataHandler->localize($table, $recordUid, $languageId);
            } else {
                $localizedUid = $existingTranslation['uid'];
            }

            // Translate properties with given service
            $translatedColumns = $this->translateRecordProperties($record, (int)$languageId, $columns);

            Records::updateRecord($table, $localizedUid, $translatedColumns);

            if (!$existingTranslation) {
                $this->generateSlugs($table, $localizedUid);
            }
        }

        Records::updateRecord($table, $recordUid, [
            self::AUTOTRANSLATE_LAST => time()
        ]);

        // only support tt_content at beginning
        if (!in_array($table, TranslationHelper::translateableTables())) {
            return;
        }

        $sysFileReferenceColumns = TranslationHelper::translationFileReferences($pageId, $table);
        if ($sysFileReferenceColumns === null) {
            return;
        }

        // load translation columns for sys_file_reference
        $sysFileReferenceTranslationColumns = TranslationHelper::translationTextfields($pageId, 'sys_file_reference');

        if ($sysFileReferenceTranslationColumns === null) {
            return;
        }

        foreach ($sysFileReferenceColumns as $sysFileReferenceColumn) {
            $this->translateSysFileReference($table, $recordUid, $sysFileReferenceColumn, $languagesToTranslate, $sysFileReferenceTranslationColumns);
        }

    }

    /**
     * @param string $table
     * @param int $uid
     * @param string $column
     * @param string $languages
     * @param array $columns
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function translateSysFileReference(string $table, int $uid, string $column, string $languages, array $columns): void
    {

        $localizedUids = Records::getLocalizedUids($table, $uid);

        $languageIds = GeneralUtility::trimExplode(',', $languages, true);

        // get original references
        $tca = BackendUtility::getTcaFieldConfiguration($table, $column);

        // return for not existend defined fields
        if (empty($tca)) {
            return;
        }

        $constraints = [
            "{$tca['foreign_table_field']} = '{$table}'",
            "fieldname = '{$column}'",
            "sys_language_uid = 0",
            "{$tca['foreign_field']} = {$uid}"
        ];

        foreach ($tca['foreign_match_fields'] as $k => $v) {
            $constraints[] = "{$k} = '{$v}'";
        }

        $originalReferences = Records::getRecords($tca['foreign_table'], 'uid', $constraints);

        foreach ($originalReferences as $originalReferenceUid) {
            $record = Records::getRecord('sys_file_reference', $originalReferenceUid);

            $translatedReferencesByLanguage = Records::getLocalizedUids($tca['foreign_table'], $originalReferenceUid);

            foreach ($languageIds as $languageId) {

                $translatedColumns = [];
                $translatedRecordUid = $translatedReferencesByLanguage[(int)$languageId] ?? null;

                if ($translatedRecordUid === null) {
                    $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                    $dataHandler->start([], []);
                    $translatedRecordUid = $dataHandler->localize($tca['foreign_table'], $originalReferenceUid, $languageId);
                    $translatedColumns += [$tca['foreign_field'] => $localizedUids[(int)$languageId]];
                }

                $translatedColumns += $this->translateRecordProperties($record, (int)$languageId, $columns);

                if (count($translatedColumns)) {
                    Records::updateRecord($tca['foreign_table'], $translatedRecordUid, $translatedColumns);
                }
            }
        }
    }

    /**
     * This function translates the given object properties
     *
     * @param array $record
     * @param int $targetLanguageUid
     * @param array $columns
     * @return array
     */
    private function translateRecordProperties(array $record, int $targetLanguageUid, array $columns) : array
    {
        // create translation array from source record by keys from fielmap

        $translatedColumns = [];

        try {

            // prepare translated record with source record
            // create translation array from source record by keys from fielmap
            $toTranslateObject = array_intersect_key($record, array_flip($columns));

            $toTranslate = array_filter($toTranslateObject, fn($value) => !is_null($value) && $value !== '');
            $target = $this->language($targetLanguageUid);
            if (count($toTranslate) > 0 && !empty($target['deeplTargetLang'])) {
                $translator = new \DeepL\Translator($this->apiKey);
                $result = $translator->translateText($toTranslate, null , $target['deeplTargetLang']);
            }

            $keys = array_keys($toTranslate);
            if (!empty($result)) {
                foreach ($result as $k => $v) {
                    $translatedColumns[$keys[$k]] = $v->text;
                }
            }

            // synchronized properties
            $translatedColumns['hidden'] = $record['hidden'];
            $translatedColumns[self::AUTOTRANSLATE_LAST] = time();

            $this->logger->info(sprintf('Successful translated to target language %s.', $target['title']));

        } catch (\Exception $e) {
            $this->logger->info(sprintf('Translation Error: %s',$e->getMessage()));
        }

        return $translatedColumns;
    }

    /**
     * @param int $languageId
     * @return array|null
     */
    private function language(int $languageId) : ?array
    {
        foreach ($this->siteLanguages as $language) {
            if ($language['languageId'] == $languageId) {
                return $language;
            }
        }

        return null;
    }

    /**
     * Generate Slugs function
     *
     * @param string $table
     * @param integer $uid
     * @return void
     */
    private function generateSlugs(string $table, int $uid) : void
    {
        $slugFields = SlugUtility::slugFields($table);
        if (!empty($slugFields)) {
            $record = Records::getRecord($table, $uid);
            $fieldsToUpdate = [];
            foreach (array_keys($slugFields) as $field) {
                $slug = SlugUtility::generateSlug($record, $table, $field);
                if ($slug === null) {
                    continue;
                }
                $fieldsToUpdate[$field] = $slug;
            }
            if (!empty($fieldsToUpdate)) {
                Records::updateRecord($table, $uid, $fieldsToUpdate);
            }
        }

    }
}