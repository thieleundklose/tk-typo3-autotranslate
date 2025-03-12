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

use DeepL\TranslateTextOptions;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Service\GlossaryService;

class Translator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const AUTOTRANSLATE_LAST = 'autotranslate_last';
    const AUTOTRANSLATE_EXCLUDE = 'autotranslate_exclude';
    const AUTOTRANSLATE_LANGUAGES = 'autotranslate_languages';

    const TRANSLATE_MODE_BOTH = 'create_update';
    const TRANSLATE_MODE_UPDATE_ONLY = 'update_only';
    const TRANSLATE_MODE_CREATE_ONLY = 'create_only';

    public $languages = [];
    public $siteLanguages = [];
    protected $apiKey = null;
    protected $pageId = null;
    protected $glossaryService = null;

    /**
     * object constructor
     *
     * @param int $pageId
     * @return void
     */
    function __construct(int $pageId) {
        $this->pageId = $pageId;
        $this->apiKey = TranslationHelper::apiKey($this->pageId);
        $this->siteLanguages = TranslationHelper::siteConfigurationValue($this->pageId, ['languages']);
        $this->glossaryService = GeneralUtility::makeInstance(GlossaryService::class);
    }

    /**
     * Translate the loaded record to target languages
     *
     * @param string $table
     * @param int $recordUid
     * @param DataHandler|null $parentObject
     * @param string|null $languagesToTranslate
     * @param string $translateMode
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function translate(string $table, int $recordUid, ?DataHandler $parentObject = null, ?string $languagesToTranslate = null, string $translateMode = self::TRANSLATE_MODE_BOTH): void
    {
        if ($this->apiKey === null) {
            return;
        }

        $record = Records::getRecord($table, $recordUid);

        // exit if record is localized one
        $parentField = TranslationHelper::translationOrigPointerField($table);
        if ($parentField === null || $record[$parentField] > 0) {
            return;
        }

        // exit if record is marked for exclude
        if ($record[self::AUTOTRANSLATE_EXCLUDE] === 1) {
            return;
        }

        // load translation columns for table
        $columns = TranslationHelper::translationTextfields($this->pageId, $table);
        if ($columns === null) {
            return;
        }

        // set target languages by record if null is given
        if (is_null($languagesToTranslate)) {
            $languagesToTranslate = $record[self::AUTOTRANSLATE_LANGUAGES] ?? '';
        }

        $localizedContents = [];
        // loop over all target languages
        $languageIds = GeneralUtility::trimExplode(',', $languagesToTranslate, true);
        foreach ($languageIds as $languageId) {
            $localizedContents[$languageId] = [];

            // Skip translation if language matches original record
            if ((int)$languageId === $record['sys_language_uid']) {
                continue;
            }

            $existingTranslation = Records::getRecordTranslation($table, $recordUid, (int)$languageId);

            if ($translateMode === self::TRANSLATE_MODE_UPDATE_ONLY && !$existingTranslation) {
                LogUtility::log($this->logger, 'No Translation of {table} with uid {uid} because mode "update only".', [
                    'table' => $table,
                    'uid' => $recordUid
                ]);
                continue;
            }

            if (!$existingTranslation) {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start([], []);

                $localizedUid = $dataHandler->localize($table, $recordUid, $languageId);
                if ($localizedUid === false) {
                    LogUtility::log($this->logger, 'No Translation of {table} with uid {uid} because DataHandler localize failed.', [
                        'table' => $table,
                        'uid' => $recordUid
                    ]);
                    continue;
                }
            } else {
                $localizedUid = $existingTranslation['uid'];
            }
            $localizedContents[$languageId][$recordUid] = $localizedUid;

            $columnsSysFileLanguage = TranslationHelper::translationTextfields($this->pageId, 'sys_file_reference');
            $autotranslateSysFileReferences = TranslationHelper::translationFileReferences($this->pageId, $table);
            if (!empty($autotranslateSysFileReferences)) {

                // add deleted / hidden etc
                $autotranslateSysFileReferencesStmt = "'" . implode("','", $autotranslateSysFileReferences) . "'";
                $references = Records::getRecords('sys_file_reference', 'uid', [
                    "uid_foreign = " . $recordUid,
                    "deleted = 0",
                    "sys_language_uid = 0",
                    "tablenames = '{$table}'",
                    "fieldname IN ({$autotranslateSysFileReferencesStmt})",
                ]);

                if (!empty($references)) {
                    foreach ($references as $referenceUid) {

                        $referenceTranslation = Records::getRecordTranslation('sys_file_reference', $referenceUid, (int)$languageId);

                        if ($translateMode === self::TRANSLATE_MODE_UPDATE_ONLY && empty($referenceTranslation)) {
                            LogUtility::log($this->logger, 'No sys_file_reference {referenceUid} Translation of {table} with uid {uid} because mode "update only".', [
                                'table' => $table,
                                'uid' => $recordUid,
                                'referenceUid' => $referenceUid,
                            ]);
                            continue;
                        }

                        if (empty($referenceTranslation)) {
                            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                            $dataHandler->start([], []);
                            $translatedSysFileReferenceUid = $dataHandler->localize('sys_file_reference', $referenceUid, $languageId);

                            Records::updateRecord(
                                'sys_file_reference',
                                $translatedSysFileReferenceUid,
                                [
                                    'uid_foreign' => $localizedContents[$languageId][$recordUid],
                                ]
                            );

                        } else {
                            $translatedSysFileReferenceUid = $referenceTranslation['uid'];
                        }

                        if (count($columnsSysFileLanguage)) {
                            if ($parentObject !== null && isset($parentObject->datamap['sys_file_reference']) && isset($parentObject->datamap['sys_file_reference'][$referenceUid])) {
                                $recordSysFileReference = $parentObject->datamap['sys_file_reference'][$referenceUid];
                            } else {
                                $recordSysFileReference = Records::getRecord('sys_file_reference', $referenceUid);
                            }
                            $translatedColumns = $this->translateRecordProperties($recordSysFileReference, (int)$languageId, $columnsSysFileLanguage);
                            if (count($translatedColumns)) {
                                Records::updateRecord('sys_file_reference', $translatedSysFileReferenceUid, $translatedColumns);
                            }
                        }
                    }
                }
            }


            // Translate properties with given service
            $translatedColumns = $this->translateRecordProperties($record, (int)$languageId, $columns);

            if (count($translatedColumns) > 0) {
                Records::updateRecord($table, $localizedUid, $translatedColumns);
            }

            if (!$existingTranslation) {
                $this->generateSlugs($table, $localizedUid);
            }
        }

        Records::updateRecord($table, $recordUid, [
            self::AUTOTRANSLATE_LAST => time()
        ]);

    }

    /**
     * This function translates the given object properties
     *
     * @param array $record
     * @param int $targetLanguageUid
     * @param array $columns
     * @return array
     */
    public function translateRecordProperties(array $record, int $targetLanguageUid, array $columns): array
    {
        // create translation array from source record by keys from fielmap
        $translatedColumns = [];

        try {
            // prepare translated record with source record
            // create translation array from source record by keys from fielmap
            $toTranslateObject = array_intersect_key($record, array_flip($columns));

            $toTranslate = array_filter($toTranslateObject, fn($value) => !is_null($value) && $value !== '');
            $deeplSourceLang = $this->deeplSourceLanguage();
            $deeplTargetLang = $this->deeplTargetLanguage($targetLanguageUid);
            $result = null;
            if (count($toTranslate) > 0 && $deeplTargetLang !== null) {
                $translator = new \DeepL\Translator($this->apiKey);
                $translatorOptions = [TranslateTextOptions::TAG_HANDLING => 'html'];

                // get optional glossary from handled by 3rd party extension
                if ($deeplSourceLang && $deeplTargetLang && TranslationHelper::glossaryEnabled($this->pageId)) {
                    $glossary = $this->glossaryService->getGlossary($deeplSourceLang, $deeplTargetLang, $this->pageId, $translator);
                    if ($glossary) {
                        $translatorOptions['glossary'] = $glossary->glossaryId;
                    }
                }

                $result = $translator->translateText($toTranslate, $deeplSourceLang, $deeplTargetLang, $translatorOptions);
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

            if (isset($record['pi_flexform'])) {
                $translatedColumns['pi_flexform'] = $record['pi_flexform'];
            }

            LogUtility::log($this->logger, 'Successful translated to target language {deeplTargetLang}.', ['deeplTargetLang' => $deeplTargetLang, 'toTranslate' => $toTranslate, 'result' => $result, 'translatedColumns' => $translatedColumns]);
        } catch (\Exception $e) {
            LogUtility::log($this->logger, 'Translation Error: {error}.', ['error' => $e->getMessage()], LogUtility::MESSAGE_ERROR);
        }

        return $translatedColumns;
    }

    /**
     * @return string|null
     */
    private function deeplSourceLanguage(): ?string
    {
        foreach ($this->siteLanguages as $language) {
            if ($language['languageId'] === 0) {
                if (empty($language['deeplSourceLang'])) {
                    return null;
                }
                return $language['deeplSourceLang'];
            }
        }

        return null;
    }

    /**
     * @param int $languageId
     * @return string|null
     */
    private function deeplTargetLanguage(int $languageId): ?string
    {
        foreach ($this->siteLanguages as $language) {
            if ($language['languageId'] === $languageId) {
                return $language['deeplTargetLang'] ?? null;
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
    private function generateSlugs(string $table, int $uid): void
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
