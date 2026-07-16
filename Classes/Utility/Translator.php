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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Service\GlossaryService;
use ThieleUndKlose\Autotranslate\Service\TranslationCacheService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;

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
     * Cached DeepL checkApiKey() result for this Translator instance.
     * Populated on the first actual DeepL invocation and reused for all
     * subsequent ones so cron runs only hit the usage endpoint once.
     */
    private ?array $deeplApiKeyDetails = null;

    /**
     * object constructor
     *
     * @param int $pageId
     * @return void
     */
    function __construct(int $pageId) {
        $this->pageId = $pageId;
        list('key' => $this->apiKey) = TranslationHelper::apiKey($this->pageId);
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
     * @param string[]|null $changedFields Datamap fields for the current save; null keeps full translation.
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function translate(
        string $table,
        int $recordUid,
        ?DataHandler $parentObject = null,
        ?string $languagesToTranslate = null,
        string $translateMode = self::TRANSLATE_MODE_BOTH,
        ?array $changedFields = null
    ): void {

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
            $mainRecordColumns = $existingTranslation
                ? TranslationHelper::filterChangedTranslatableColumns($columns, $changedFields)
                : $columns;
            $referenceChangedFields = $existingTranslation ? $changedFields : null;

            if ($translateMode === self::TRANSLATE_MODE_UPDATE_ONLY && !$existingTranslation) {
                LogUtility::log($this->logger, 'No Translation of {table} with uid {uid} because mode "update only".', [
                    'table' => $table,
                    'uid' => $recordUid
                ]);
                continue;
            }

            if ($this->hasDeepLTranslationWork($record, $table, (int)$languageId, $mainRecordColumns, $parentObject, $translateMode, $referenceChangedFields)) {
                $this->ensureValidApiKey();
            }

            if (!$existingTranslation) {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start([], []);
                $localizedUid = $dataHandler->localize($table, $recordUid, $languageId);
            } else {
                $localizedUid = $existingTranslation['uid'];
            }

            if ($localizedUid === null || $localizedUid === false) {
                LogUtility::log($this->logger, 'No Translation of {table} with uid {uid} because DataHandler localize failed.', [
                    'table' => $table,
                    'uid' => $recordUid
                ]);
                continue;
            }

            $localizedContents[$languageId][$recordUid] = $localizedUid;
            $this->translateAdditionalReferences(
                $table,
                $recordUid,
                (int)$localizedContents[$languageId][$recordUid],
                (int)$languageId,
                $parentObject,
                $translateMode,
                $referenceChangedFields
            );

            $this->synchronizeLocalizedRelations(
                $table,
                $record,
                $localizedUid,
                (int)$languageId,
                $translateMode
            );

            // Translate properties with given service
            $translatedColumns = $this->translateRecordProperties($record, (int)$languageId, $mainRecordColumns, $table, $localizedUid);

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

    private function translateAdditionalReferences(
        string $table,
        int $recordUid,
        int $localizedUid,
        int $targetLanguageUid,
        ?DataHandler $parentObject,
        string $translateMode,
        ?array $changedFields = null,
        array $processedReferences = []
    ): void {
        $processedKey = $table . ':' . $recordUid . ':' . $targetLanguageUid;
        if (isset($processedReferences[$processedKey])) {
            return;
        }
        $processedReferences[$processedKey] = true;

        foreach (TranslationHelper::additionalReferenceTables() as $referenceTable) {
            $columnsReference = TranslationHelper::translationTextfields($this->pageId, $referenceTable);
            $autotranslateReferences = TranslationHelper::translationReferenceColumns($this->pageId, $table, $referenceTable);

            if (empty($autotranslateReferences)) {
                continue;
            }

            foreach ($autotranslateReferences as $referenceColumn) {
                $foreignField = $this->getForeignFieldForReferenceColumn($table, $referenceColumn, $referenceTable);
                if ($foreignField === null) {
                    continue;
                }

                foreach ($this->getReferenceUidsForTranslation($table, $recordUid, $referenceTable, $referenceColumn) as $referenceUid) {
                    if (!$this->shouldProcessReferenceColumn($changedFields, $referenceColumn, $referenceTable, (int)$referenceUid, $parentObject)) {
                        continue;
                    }

                    $translatedReferenceUid = $this->ensureLocalizedInlineReferenceRecord(
                        $referenceTable,
                        (int)$referenceUid,
                        $targetLanguageUid,
                        $foreignField,
                        $localizedUid,
                        $translateMode,
                        $table,
                        $recordUid
                    );

                    if ($translatedReferenceUid === null) {
                        continue;
                    }

                    if (!empty($columnsReference)) {
                        $recordReference = $this->getReferenceRecordForTranslation($referenceTable, (int)$referenceUid, $parentObject);
                        if (is_array($recordReference)) {
                            $translatedColumns = $this->translateRecordProperties($recordReference, $targetLanguageUid, $columnsReference, $referenceTable, $translatedReferenceUid);
                            if (count($translatedColumns)) {
                                Records::updateRecord($referenceTable, $translatedReferenceUid, $translatedColumns);
                            }
                        }
                    }

                    $this->translateAdditionalReferences(
                        $referenceTable,
                        (int)$referenceUid,
                        $translatedReferenceUid,
                        $targetLanguageUid,
                        $parentObject,
                        $translateMode,
                        null,
                        $processedReferences
                    );
                }
            }
        }
    }

    private function getReferenceRecordForTranslation(string $referenceTable, int $referenceUid, ?DataHandler $parentObject): ?array
    {
        if ($parentObject !== null && isset($parentObject->datamap[$referenceTable][$referenceUid])) {
            return $parentObject->datamap[$referenceTable][$referenceUid];
        }

        $recordReference = Records::getRecord($referenceTable, $referenceUid);
        return is_array($recordReference) ? $recordReference : null;
    }

    private function shouldProcessReferenceColumn(
        ?array $changedFields,
        string $referenceColumn,
        string $referenceTable,
        int $referenceUid,
        ?DataHandler $parentObject
    ): bool {
        if ($changedFields === null) {
            return true;
        }

        if (in_array($referenceColumn, $changedFields, true)) {
            return true;
        }

        if ($parentObject === null) {
            return false;
        }

        if (isset($parentObject->datamap[$referenceTable][$referenceUid])) {
            return true;
        }

        foreach ($parentObject->substNEWwithIDs as $newId => $uid) {
            if ((int)$uid === $referenceUid && isset($parentObject->datamap[$referenceTable][$newId])) {
                return true;
            }
        }

        return false;
    }

    private function synchronizeLocalizedRelations(
        string $table,
        array $record,
        int $localizedUid,
        int $targetLanguageUid,
        string $translateMode
    ): void {
        $ctrl = $GLOBALS['TCA'][$table]['ctrl'] ?? [];
        $translationMetadataColumns = array_filter([
            $ctrl['languageField'] ?? 'sys_language_uid',
            $ctrl['transOrigPointerField'] ?? null,
            $ctrl['transOrigDiffSourceField'] ?? null,
            'l10n_state',
        ]);

        foreach (($GLOBALS['TCA'][$table]['columns'] ?? []) as $columnName => $columnConfiguration) {
            if (in_array($columnName, $translationMetadataColumns, true)) {
                continue;
            }

            $config = $columnConfiguration['config'] ?? [];
            $type = $config['type'] ?? null;

            if (!in_array($type, ['select', 'group', 'category'], true)) {
                continue;
            }

            $referenceTable = $this->resolveReferenceTableForColumn($config);
            if ($referenceTable === null || !isset($GLOBALS['TCA'][$referenceTable]['ctrl']['transOrigPointerField'])) {
                continue;
            }

            $relatedSourceUids = $this->getRelatedRecordUids($table, $record, $columnName, $config, $referenceTable);
            $translatedRelationUids = [];
            foreach ($relatedSourceUids as $relatedSourceUid) {
                $translatedRelationUid = $this->ensureLocalizedReferenceRecord(
                    $referenceTable,
                    $relatedSourceUid,
                    $targetLanguageUid,
                    $translateMode
                );

                if ($translatedRelationUid !== null) {
                    $translatedRelationUids[] = $translatedRelationUid;
                }
            }

            $this->updateRelationField($table, $localizedUid, $columnName, $config, $translatedRelationUids);
        }
    }

    private function resolveReferenceTableForColumn(array $config): ?string
    {
        if (($config['type'] ?? null) === 'category') {
            return 'sys_category';
        }

        return $config['foreign_table'] ?? null;
    }

    private function getRelatedRecordUids(
        string $table,
        array $record,
        string $columnName,
        array $config,
        string $referenceTable
    ): array {
        if (!empty($config['MM'])) {
            return $this->getRelatedMmRecordUids((int)$record['uid'], $config, $referenceTable);
        }

        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);
        $relationHandler->start(
            (string)($record[$columnName] ?? ''),
            $config['allowed'] ?? $referenceTable,
            $config['MM'] ?? '',
            (int)$record['uid'],
            $table,
            $config
        );

        $relatedUids = array_values(array_unique(array_map(
            'intval',
            $relationHandler->tableArray[$referenceTable] ?? []
        )));

        return array_values(array_filter($relatedUids, static function (int $relatedUid) use ($referenceTable): bool {
            if ($relatedUid <= 0) {
                return false;
            }

            return is_array(Records::getRecord($referenceTable, $relatedUid));
        }));
    }

    private function getRelatedMmRecordUids(int $recordUid, array $config, string $referenceTable): array
    {
        $mmTable = (string)$config['MM'];
        $columnMap = $this->resolveMmColumns($config);
        $sortingColumn = !empty($config['MM_opposite_field']) ? 'sorting_foreign' : 'sorting';
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($mmTable);

        $queryBuilder
            ->select($columnMap['foreign'])
            ->from($mmTable)
            ->where(
                $queryBuilder->expr()->eq(
                    $columnMap['local'],
                    $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)
                )
            )
            ->orderBy($sortingColumn, 'ASC');

        foreach (($config['MM_match_fields'] ?? []) as $field => $value) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    $field,
                    $queryBuilder->createNamedParameter((string)$value)
                )
            );
        }

        $relatedUids = $queryBuilder->executeQuery()->fetchFirstColumn();
        $relatedUids = array_values(array_unique(array_map('intval', $relatedUids)));

        return array_values(array_filter($relatedUids, static function (int $relatedUid) use ($referenceTable): bool {
            if ($relatedUid <= 0) {
                return false;
            }

            return is_array(Records::getRecord($referenceTable, $relatedUid));
        }));
    }

    private function ensureLocalizedReferenceRecord(
        string $referenceTable,
        int $referenceUid,
        int $targetLanguageUid,
        string $translateMode
    ): ?int {
        $referenceTranslation = Records::getRecordTranslation($referenceTable, $referenceUid, $targetLanguageUid);

        if (!empty($referenceTranslation)) {
            return (int)$referenceTranslation['uid'];
        }

        return null;
    }

    private function ensureLocalizedInlineReferenceRecord(
        string $referenceTable,
        int $referenceUid,
        int $targetLanguageUid,
        string $foreignField,
        int $localizedParentUid,
        string $translateMode,
        string $parentTable,
        int $parentUid
    ): ?int {
        $referenceTranslation = Records::getRecordTranslation($referenceTable, $referenceUid, $targetLanguageUid);
        if (empty($referenceTranslation)) {
            $referenceTranslation = $this->findLocalizedInlineReferenceRecord(
                $referenceTable,
                $referenceUid,
                $targetLanguageUid,
                $foreignField,
                $localizedParentUid
            );
        }

        if ($translateMode === self::TRANSLATE_MODE_UPDATE_ONLY && empty($referenceTranslation)) {
            LogUtility::log($this->logger, 'No {referenceTable} {referenceUid} Translation of {table} with uid {uid} because mode "update only".', [
                'referenceTable' => $referenceTable,
                'table' => $parentTable,
                'uid' => $parentUid,
                'referenceUid' => $referenceUid,
            ]);
            return null;
        }

        if (empty($referenceTranslation)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], []);
            $translatedReferenceUid = $dataHandler->localize($referenceTable, $referenceUid, $targetLanguageUid);

            if ($translatedReferenceUid === false || $translatedReferenceUid === null) {
                $referenceTranslation = Records::getRecordTranslation($referenceTable, $referenceUid, $targetLanguageUid);
                if (empty($referenceTranslation)) {
                    $referenceTranslation = $this->findLocalizedInlineReferenceRecord(
                        $referenceTable,
                        $referenceUid,
                        $targetLanguageUid,
                        $foreignField,
                        $localizedParentUid
                    );
                }
                if (!empty($referenceTranslation)) {
                    $translatedReferenceUid = (int)$referenceTranslation['uid'];
                }
            }

            if ($translatedReferenceUid === false || $translatedReferenceUid === null) {
                LogUtility::log($this->logger, 'No {referenceTable} {referenceUid} Translation of {table} with uid {uid} because DataHandler localize failed.', [
                    'referenceTable' => $referenceTable,
                    'table' => $parentTable,
                    'uid' => $parentUid,
                    'referenceUid' => $referenceUid,
                    'languageUid' => $targetLanguageUid,
                ], LogUtility::MESSAGE_WARNING);
                return null;
            }
        } else {
            $translatedReferenceUid = (int)$referenceTranslation['uid'];
        }

        Records::updateRecord(
            $referenceTable,
            (int)$translatedReferenceUid,
            $this->buildInlineReferenceLocalizationUpdateFields(
                $referenceTable,
                $referenceUid,
                $foreignField,
                $localizedParentUid
            )
        );

        return (int)$translatedReferenceUid;
    }

    private function findLocalizedInlineReferenceRecord(
        string $referenceTable,
        int $referenceUid,
        int $targetLanguageUid,
        string $foreignField,
        int $localizedParentUid
    ): ?array {
        $sourceRecord = Records::getRecord($referenceTable, $referenceUid);
        if (!is_array($sourceRecord)) {
            return null;
        }

        $columns = $GLOBALS['TCA'][$referenceTable]['columns'] ?? [];
        $constraints = [
            'sys_language_uid' => $targetLanguageUid,
            $foreignField => $localizedParentUid,
        ];

        $ctrl = $GLOBALS['TCA'][$referenceTable]['ctrl'] ?? [];
        $parentField = $ctrl['transOrigPointerField'] ?? null;
        if ($parentField !== null) {
            $constraints[$parentField] = $referenceUid;
        }

        if (isset($columns['deleted'])) {
            $constraints['deleted'] = 0;
        }
        if (isset($columns['parenttable']) && isset($sourceRecord['parenttable'])) {
            $constraints['parenttable'] = $sourceRecord['parenttable'];
        }
        if (isset($columns['sorting']) && isset($sourceRecord['sorting'])) {
            $constraints['sorting'] = $sourceRecord['sorting'];
        }

        $orderBy = [];
        if (isset($columns['sorting'])) {
            $orderBy['sorting'] = 'ASC';
        }
        $orderBy['uid'] = 'ASC';

        return Records::getFirstRecordByFields($referenceTable, $constraints, $orderBy);
    }

    private function buildInlineReferenceLocalizationUpdateFields(
        string $referenceTable,
        int $referenceUid,
        string $foreignField,
        int $localizedParentUid
    ): array {
        $fields = [
            $foreignField => $localizedParentUid,
        ];

        $ctrl = $GLOBALS['TCA'][$referenceTable]['ctrl'] ?? [];
        $parentField = $ctrl['transOrigPointerField'] ?? null;
        if ($parentField !== null) {
            $fields[$parentField] = $referenceUid;
        }

        $translationSourceField = $ctrl['translationSource'] ?? null;
        if ($translationSourceField !== null) {
            $fields[$translationSourceField] = $referenceUid;
        }

        $disabledField = $ctrl['enablecolumns']['disabled'] ?? 'hidden';
        if (isset($GLOBALS['TCA'][$referenceTable]['columns'][$disabledField])) {
            $fields[$disabledField] = (int)(
                Records::getRecord($referenceTable, $referenceUid, $disabledField) ?? 0
            );
        }

        return $fields;
    }

    private function isTranslationCreationAllowedForTable(string $table): bool
    {
        $siteConfiguration = TranslationHelper::siteConfigurationValue($this->pageId);
        if (!is_array($siteConfiguration)) {
            return true;
        }

        $enabledField = TranslationHelper::configurationFieldname($table, 'enabled');
        if (!array_key_exists($enabledField, $siteConfiguration)) {
            return true;
        }

        return (bool)$siteConfiguration[$enabledField];
    }

    private function updateRelationField(
        string $table,
        int $localizedUid,
        string $columnName,
        array $config,
        array $translatedRelationUids
    ): void {
        $translatedRelationUids = array_values(array_unique(array_filter(array_map('intval', $translatedRelationUids))));
        $fieldValue = in_array(($config['renderType'] ?? ''), ['selectSingle', 'selectTree'], true)
            ? (string)($translatedRelationUids[0] ?? 0)
            : implode(',', $translatedRelationUids);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            $table => [
                $localizedUid => [
                    $columnName => $fieldValue,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();
    }

    private function resolveMmColumns(array $config): array
    {
        if (!empty($config['MM_opposite_field'])) {
            return [
                'local' => 'uid_foreign',
                'foreign' => 'uid_local',
            ];
        }

        return [
            'local' => 'uid_local',
            'foreign' => 'uid_foreign',
        ];
    }

    /**
     * This function translates the given object properties
     *
     * @param array $record
     * @param int $targetLanguageUid
     * @param array $columns
     * @param string $table
     * @param int $localizedUid
     * @return array
     */
    public function translateRecordProperties(array $record, int $targetLanguageUid, array $columns, string $table, int $localizedUid): array
    {
        // create translation array from source record by keys from fielmap
        $translatedColumns = [];

        $toTranslateObject = array_intersect_key($record, array_flip($columns));
        $toTranslate = array_filter(
            $toTranslateObject,
            fn($value, $field) => $this->isSupportedTextTranslationValue($table, (string)$field, $value),
            ARRAY_FILTER_USE_BOTH
        );
        $deeplSourceLang = $this->deeplSourceLanguage();
        $deeplTargetLang = $this->deeplTargetLanguage($targetLanguageUid);

        if (count($toTranslate) > 0 && $deeplTargetLang !== null) {
            $this->ensureValidApiKey();
        }

        try {
            // prepare translated record with source record
            // create translation array from source record by keys from fielmap
            $result = null;
            $glossary = null;
            if (count($toTranslate) > 0 && $deeplTargetLang !== null) {
                $toTranslate = $this->extractAndReplaceTranslatableHtmlAttributes($toTranslate);
                $translator = new \DeepL\Translator($this->apiKey);

                // get optional glossary from handled by 3rd party extension
                if ($deeplSourceLang && $deeplTargetLang && TranslationHelper::glossaryEnabled($this->pageId)) {
                    $glossary = $this->glossaryService->getGlossary($deeplSourceLang, $deeplTargetLang, $this->pageId, $translator);
                }

                // it is experimental to add flexform fields to translation
                // TODO let define which fields in flexform should be translated to prevent translating settings
                if (isset($toTranslate['pi_flexform'])) {
                    $xml = simplexml_load_string($record['pi_flexform']);

                    foreach ($xml->xpath('//field') as $field) {
                        $value = (string)$field->value;
                        if (!empty(trim($value))
                            && strpos($value, '<') === false
                            && is_string($value)
                            && !is_numeric($value)
                            && $value !== ''
                        ) {
                            $translationResult = $this->translateItems(
                                $record,
                                $table,
                                [$value],
                                $deeplSourceLang,
                                $deeplTargetLang,
                                $glossary
                            );
                            if (!empty($translationResult)) {
                                $field->value[0] = $translationResult[0]->text;
                            }
                        }
                    }

                    $translatedColumns['pi_flexform'] = $xml->asXML();
                    unset($toTranslate['pi_flexform']);
                }

                $result = empty($toTranslate) ? [] : $this->translateItems($record, $table, $toTranslate, $deeplSourceLang, $deeplTargetLang, $glossary);
            }

            $keys = array_keys($toTranslate);
            if (!empty($result)) {
                $translatedAttributes = [];
                foreach ($result as $k => $v) {
                    // Skip null values to prevent str_replace errors
                    if ($v === null) {
                        continue;
                    }

                    $field = $keys[$k];
                    if (strpos($field, '__ATTR__') === 0) {
                        $translatedAttributes[$field] = $v->text;
                    }
                }

                foreach ($result as $k => $v) {
                    // Skip null values to prevent str_replace errors
                    if ($v === null) {
                        continue;
                    }

                    $field = $keys[$k];
                    if (strpos($field, '__ATTR__') === 0) {
                        continue;
                    }
                    $translatedValue = $this->restoreTranslatedHtmlAttributes($v->text, $translatedAttributes);
                    $translatedColumns[$field] = $translatedValue;
                }
            }

            // add fields to copy in translation from extension configuration
            $fieldsToCopy = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate', 'fieldsToCopy');
            $fields = $fieldsToCopy ? GeneralUtility::trimExplode(',', $fieldsToCopy, true) : [];
            foreach ($record as $field => $value) {
                if (isset($record[$field]) && !isset($translatedColumns[$field]) && in_array($field, $fields, true)) {
                    $translatedColumns[$field] = $value;
                }
            }

            if (!empty($translatedColumns)) {
                $translatedColumns['l10n_state'] = $this->buildL10nState($table, $targetLanguageUid, array_keys($translatedColumns), $localizedUid);
            }

            // set date and time of translation
            $translatedColumns[self::AUTOTRANSLATE_LAST] = time();

            LogUtility::log($this->logger, 'Successful translated to target language {deeplTargetLang}.', ['deeplTargetLang' => $deeplTargetLang, 'toTranslate' => $toTranslate, 'result' => $result, 'translatedColumns' => $translatedColumns]);
        } catch (\Exception $e) {
            LogUtility::log($this->logger, 'Translation Error: {error}.', ['error' => $e->getMessage()], LogUtility::MESSAGE_ERROR);
        }

        return $translatedColumns;
    }

    private function isSupportedTextTranslationValue(string $table, string $field, $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $fieldConfiguration = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];
        $fieldType = $fieldConfiguration['type'] ?? null;
        if (!in_array($fieldType, ['input', 'text'], true)) {
            return false;
        }

        $evalList = GeneralUtility::trimExplode(',', (string)($fieldConfiguration['eval'] ?? ''), true);
        if (in_array('int', $evalList, true)) {
            return false;
        }

        return !is_numeric($value) || !is_scalar($value);
    }

    /**
     * Builds l10n_state array for translated fields
     *
     * @param string $table
     * @param int $targetLanguageUid
     * @param array $translatedFields
     * @param int $localizedUid
     * @return string JSON encoded l10n_state
     */
    private function buildL10nState(string $table, int $targetLanguageUid, array $translatedFields, int $localizedUid): string
    {
        // check if table supports l10n_state
        if (!isset($GLOBALS['TCA'][$table]['ctrl']['transOrigDiffSourceField'])) {
            return '{}';
        }

        try {
            // load existing translation if available
            $existingTranslation = Records::getRecordTranslation($table, $localizedUid, $targetLanguageUid);

            $l10nState = [];
            if ($existingTranslation && !empty($existingTranslation['l10n_state'])) {
                $l10nState = json_decode($existingTranslation['l10n_state'], true) ?: [];
            }

            // set all translated fields to "custom"
            foreach ($translatedFields as $field) {
                $l10nState[$field] = 'custom';
            }

            return json_encode($l10nState);

        } catch (\Exception $e) {
            LogUtility::log($this->logger, 'Error building l10n_state: {error}', [
                'error' => $e->getMessage(),
                'table' => $table
            ], LogUtility::MESSAGE_ERROR);

            return '{}';
        }
    }

    private function hasDeepLTranslationWork(
        array $record,
        string $table,
        int $targetLanguageUid,
        array $columns,
        ?DataHandler $parentObject,
        string $translateMode,
        ?array $changedFields = null
    ): bool {
        if ($this->recordHasDeepLTranslationWork($record, $table, $targetLanguageUid, $columns)) {
            return true;
        }

        return $this->referencesHaveDeepLTranslationWork($record, $table, (int)$record['uid'], $targetLanguageUid, $parentObject, $translateMode, $changedFields);
    }

    private function referencesHaveDeepLTranslationWork(
        array $record,
        string $table,
        int $recordUid,
        int $targetLanguageUid,
        ?DataHandler $parentObject,
        string $translateMode,
        ?array $changedFields = null,
        array $processedReferences = []
    ): bool {
        $processedKey = $table . ':' . $recordUid . ':' . $targetLanguageUid;
        if (isset($processedReferences[$processedKey])) {
            return false;
        }
        $processedReferences[$processedKey] = true;

        foreach (TranslationHelper::additionalReferenceTables() as $referenceTable) {
            $columnsReference = TranslationHelper::translationTextfields($this->pageId, $referenceTable);
            $autotranslateReferences = TranslationHelper::translationReferenceColumns($this->pageId, $table, $referenceTable);
            if (empty($autotranslateReferences)) {
                continue;
            }

            foreach ($autotranslateReferences as $referenceColumn) {
                foreach ($this->getReferenceUidsForTranslation($table, $recordUid, $referenceTable, $referenceColumn) as $referenceUid) {
                    if (!$this->shouldProcessReferenceColumn($changedFields, $referenceColumn, $referenceTable, (int)$referenceUid, $parentObject)) {
                        continue;
                    }

                    $referenceTranslation = Records::getRecordTranslation($referenceTable, (int)$referenceUid, $targetLanguageUid);
                    if ($translateMode === self::TRANSLATE_MODE_UPDATE_ONLY && empty($referenceTranslation)) {
                        continue;
                    }

                    $recordReference = $this->getReferenceRecordForTranslation($referenceTable, (int)$referenceUid, $parentObject);

                    if (!is_array($recordReference)) {
                        continue;
                    }

                    if (!empty($columnsReference) && $this->recordHasDeepLTranslationWork($recordReference, $referenceTable, $targetLanguageUid, $columnsReference)) {
                        return true;
                    }

                    if ($this->referencesHaveDeepLTranslationWork($recordReference, $referenceTable, (int)$referenceUid, $targetLanguageUid, $parentObject, $translateMode, null, $processedReferences)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function recordHasDeepLTranslationWork(array $record, string $table, int $targetLanguageUid, array $columns): bool
    {
        if ($this->deeplTargetLanguage($targetLanguageUid) === null) {
            return false;
        }

        $toTranslateObject = array_intersect_key($record, array_flip($columns));
        $toTranslate = array_filter(
            $toTranslateObject,
            fn($value, $field) => $this->isSupportedTextTranslationValue($table, (string)$field, $value),
            ARRAY_FILTER_USE_BOTH
        );

        return count($toTranslate) > 0;
    }

    private function getReferenceUidsForTranslation(string $table, int $recordUid, string $referenceTable, string $referenceColumn): array
    {
        $type = $GLOBALS['TCA'][$table]['columns'][$referenceColumn]['config']['type'] ?? null;
        $foreignField = $this->getForeignFieldForReferenceColumn($table, $referenceColumn, $referenceTable);
        if ($foreignField === null) {
            return [];
        }

        switch ($type) {
            case 'file':
                return Records::getRecords($referenceTable, 'uid', [
                    "{$foreignField} = " . $recordUid,
                    "deleted = 0",
                    "sys_language_uid = 0",
                    "tablenames = '{$table}'",
                    "fieldname = '{$referenceColumn}'",
                ]);
            case 'inline':
                $constraints = [
                    "{$foreignField} = " . $recordUid,
                    "deleted = 0",
                    "sys_language_uid = 0",
                ];

                if (isset($GLOBALS['TCA'][$referenceTable]['columns']['fieldname'])) {
                    $constraints[] = "fieldname = '{$referenceColumn}'";
                }

                return Records::getRecords($referenceTable, 'uid', $constraints);
        }

        return [];
    }

    private function getForeignFieldForReferenceColumn(string $table, string $referenceColumn, string $referenceTable): ?string
    {
        $config = $GLOBALS['TCA'][$table]['columns'][$referenceColumn]['config'] ?? [];
        $foreignField = $config['foreign_field'] ?? null;
        if (is_string($foreignField) && $foreignField !== '') {
            return $foreignField;
        }

        if (($config['type'] ?? null) === 'file' && $referenceTable === 'sys_file_reference') {
            return 'uid_foreign';
        }

        return null;
    }

    /**
     * Validate the configured DeepL API key exactly once per Translator
     * instance and re-use the cached usage details on every subsequent
     * DeepL invocation. Throws RuntimeException if the key is unusable.
     */
    private function ensureValidApiKey(): void
    {
        if ($this->deeplApiKeyDetails === null) {
            $this->deeplApiKeyDetails = DeeplApiHelper::checkApiKey($this->apiKey);
        }

        $details = $this->deeplApiKeyDetails;
        if ($details['error']) {
            LogUtility::log($this->logger, 'DeepL API Key is not valid: {error}', [
                'error' => $details['error']
            ]);
            throw new \RuntimeException('DeepL API Key is not valid: ' . $details['error']);
        }
        if (!empty($details['warning'])) {
            LogUtility::log($this->logger, 'DeepL API warning: {warning}', [
                'warning' => $details['warning']
            ], LogUtility::MESSAGE_WARNING);
        }
        if (!$details['isValid']) {
            LogUtility::log($this->logger, 'DeepL API Key is not valid: {error}', [
                'error' => 'No API Key given.'
            ]);
            throw new \RuntimeException('DeepL API Key is not valid: No API Key given.');
        }
        if ($details['charactersLeft'] !== null && $details['charactersLeft'] <= 0) {
            LogUtility::log($this->logger, 'DeepL API Key has no characters left: {charactersLeft}', [
                'charactersLeft' => $details['charactersLeft']
            ]);
            throw new \RuntimeException('DeepL API Key has no characters left: ' . $details['charactersLeft']);
        }
    }

    private function translateItems(array $record, string $table, array $toTranslate, ?string $deeplSourceLang, string $deeplTargetLang, ?Glossary $glossary): array
    {
        $translator = new \DeepL\Translator($this->apiKey);
        $baseOptions = [
            TranslateTextOptions::SPLIT_SENTENCES => true,
        ];

        if ($glossary) {
            $baseOptions[TranslateTextOptions::GLOSSARY] = $glossary->glossaryId;
        }
        $htmlOptions = array_merge($baseOptions, [
            TranslateTextOptions::TAG_HANDLING => 'html',
        ]);

        $richtextMap = $this->mapRichtextFields($toTranslate, $table, $record);

        $toTranslateText = [];
        $toTranslateHtml = [];

        foreach ($toTranslate as $field => $value) {
            if (!($richtextMap[$field] ?? false)) {
                $toTranslateText[$field] = $value;
            } else {
                $toTranslateHtml[$field] = $value;
            }
        }

        // Translation Cache Service
        $cacheService = GeneralUtility::makeInstance(\ThieleUndKlose\Autotranslate\Service\TranslationCacheService::class);

        // Translate Text Fields with Cache
        $translatedTextFields = [];
        if (!empty($toTranslateText)) {
            $translatedTextFields = $this->translateWithCache(
                array_values($toTranslateText),
                $deeplSourceLang,
                $deeplTargetLang,
                $baseOptions,
                $translator,
                $cacheService
            );
        }

        // Translate HTML Fields with Cache
        $translatedHtmlFields = [];
        if (!empty($toTranslateHtml)) {
            $translatedHtmlFields = $this->translateWithCache(
                array_values($toTranslateHtml),
                $deeplSourceLang,
                $deeplTargetLang,
                $htmlOptions,
                $translator,
                $cacheService
            );
        }

        // to bring back order of fields as in $toTranslate
        $mergedResults = [];
        $textIndex = 0;
        $htmlIndex = 0;

        foreach (array_keys($toTranslate) as $field) {
            if (array_key_exists($field, $toTranslateText)) {
                $mergedResults[] = $translatedTextFields[$textIndex] ?? null;
                $textIndex++;
            } elseif (array_key_exists($field, $toTranslateHtml)) {
                $mergedResults[] = $translatedHtmlFields[$htmlIndex] ?? null;
                $htmlIndex++;
            }
        }

        return $mergedResults;
    }

    /**
     * Translate texts with caching support
     */
    private function translateWithCache(
        array $texts,
        ?string $sourceLang,
        string $targetLang,
        array $options,
        \DeepL\Translator $translator,
        TranslationCacheService $cacheService
    ): array {
        if (empty($texts)) {
            return [];
        }

        // Check for complete cache hit first
        $completeCacheKey = $cacheService->generateCacheKey($texts, $sourceLang, $targetLang, $options);
        $completeCache = $cacheService->getCachedTranslation($completeCacheKey);

        if ($completeCache !== null) {
            LogUtility::log($this->logger, 'Complete cache hit for {count} texts', ['count' => count($texts)]);
            return $completeCache;
        }

        // Check for partial cache hits
        $partialCache = $cacheService->getPartialCacheHits($texts, $sourceLang, $targetLang, $options);

        $finalResults = array_fill(0, count($texts), null);

        // Fill cached results
        foreach ($partialCache['cached'] as $index => $result) {
            $finalResults[$index] = $result;
        }

        // Translate uncached texts
        if (!empty($partialCache['uncached'])) {
            LogUtility::log($this->logger, 'Translating {uncached} texts, {cached} from cache', [
                'uncached' => count($partialCache['uncached']),
                'cached' => count($partialCache['cached'])
            ]);

            $freshTranslations = $translator->translateText(
                $partialCache['uncached'],
                $sourceLang,
                $targetLang,
                $options
            );

            // Fill fresh results and cache them individually
            foreach ($freshTranslations as $resultIndex => $result) {
                $originalIndex = $partialCache['mapping'][$resultIndex];
                $finalResults[$originalIndex] = $result;
            }

            // Cache individual translations
            $cacheService->cacheIndividualTranslations(
                $partialCache['uncached'],
                $freshTranslations,
                $sourceLang,
                $targetLang,
                $options
            );
        }

        // Cache complete result (filter out null values to prevent cache corruption)
        $validResults = array_filter($finalResults, fn($result) => $result !== null);
        if (count($validResults) === count($finalResults)) {
            // Only cache if all results are valid
            $cacheService->setCachedTranslation($completeCacheKey, $finalResults);
        } else {
            LogUtility::log($this->logger, 'Not caching complete result due to null values: {valid}/{total}', [
                'valid' => count($validResults),
                'total' => count($finalResults)
            ]);
        }

        return $finalResults;
    }

    /**
     * Gibt ein Array zurück, das für jedes Feld aus $toTranslate angibt, ob es ein Richtext-Feld ist.
     *
     * @param array $toTranslate
     * @param string $table
     * @param array $record
     * @return array [ 'fieldname' => bool ]
     */
    public function mapRichtextFields(array $toTranslate, string $table, array $record): array
    {
        $result = [];
        foreach (array_keys($toTranslate) as $columnName) {
            $result[$columnName] = $this->isRichtextField($record, $table, $columnName);
        }
        return $result;
    }

    /**
     * check if the field is a richtext field
     *
     * @param array $record
     * @param string $table
     * @param string $columnName
     * @return boolean
     */
    function isRichtextField(array $record, string $table, string $columnName): bool
    {
        // get tca configuration for the field
        $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$columnName]['config'] ?? null;
        if (!$fieldConfig) {
            return false;
        }

        // check for CType specific configuration
        $ctype = $record['CType'] ?? null;
        if ($ctype && isset($GLOBALS['TCA'][$table]['types'][$ctype]['columnsOverrides'][$columnName]['config'])) {
            $fieldConfig = $GLOBALS['TCA'][$table]['types'][$ctype]['columnsOverrides'][$columnName]['config'];
        }

        // check if the field is a richtext field
        return isset($fieldConfig['enableRichtext']) && $fieldConfig['enableRichtext'] === true;
    }
    /**
     * Replaces placeholders in the HTML with the translated attribute values.
     *
     * @param string $html
     * @param array $attrTranslations [placeholder => translation]
     * @return string
     */
    private function restoreTranslatedHtmlAttributes(string $html, array $attrTranslations): string
    {
        foreach ($attrTranslations as $placeholder => $translatedValue) {
            // Add null check to prevent str_replace errors
            if ($translatedValue !== null) {
                $html = str_replace($placeholder, $translatedValue, $html);
            }
        }
        return $html;
    }

    /**
     * Replaces translatable HTML attributes with placeholders and returns the modified array
     * and the mapping table.
     *
     * @param array $toTranslate
     * @return array [array $modifiedToTranslate, array $attrMap]
     */
    private function extractAndReplaceTranslatableHtmlAttributes(array $toTranslate): array
    {
        $attributeMap = [
            ['tag' => 'a', 'attr' => 'title'],
            // add more attributes as needed
        ];

        $attrMap = [];
        $attrCounter = 1;

        foreach ($toTranslate as $field => &$value) {

            if (!is_string($value) || trim($value) === '' || !$this->isHtml($value)) {
                continue;
            }

            foreach ($attributeMap as $map) {
                $found = $this->extractHtmlAttributes($value, $map['tag'], $map['attr']);
                foreach ($found as $attrValue) {
                    $placeholder = '__ATTR__' . $attrCounter . '__';
                    $attrMap[$placeholder] = $attrValue;
                    $value = $this->replaceHtmlAttributeWithPlaceholder($value, $map['tag'], $map['attr'], $attrValue, $placeholder);
                    $attrCounter++;
                }
            }
        }
        unset($value);

        // Add the attributes as separate entries to translate
        foreach ($attrMap as $placeholder => $original) {
            $toTranslate[$placeholder] = $original;
        }

        return $toTranslate;
    }

    private function isHtml(string $value): bool {
        return $value !== strip_tags($value);
    }

    /**
     * Extrahiert alle Werte eines bestimmten Attributs anhand eines Tag-Namens aus HTML.
     *
     * @param string $html Der HTML-String
     * @param string $tagName Der Tag-Name (z.B. 'a')
     * @param string $attributeName Das Attribut (z.B. 'title')
     * @return array Array mit allen gefundenen Attributwerten
     */
    private function extractHtmlAttributes(string $html, string $tagName, string $attributeName): array
    {
        $values = [];
        if (trim($html) === '') {
            return $values;
        }

        $doc = new \DOMDocument();
        // Fehler unterdrücken, falls ungültiges HTML
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $xpath = new \DOMXPath($doc);
        // XPath-Query für alle gewünschten Attribute
        $query = sprintf('//' . $tagName . '[@' . $attributeName . ']');
        foreach ($xpath->query($query) as $node) {
            /** @var \DOMElement $node */
            $values[] = $node->getAttribute($attributeName);
        }
        return $values;
    }

    /**
     * Ersetzt ein bestimmtes Attribut eines Tags in einem HTML-String durch einen Platzhalter.
     *
     * @param string $html Der HTML-String
     * @param string $tag Der Tag-Namen (z.B. 'a')
     * @param string $attr Das Attribut (z.B. 'title')
     * @param string $original Der Originalwert des Attributs, der ersetzt werden soll
     * @param string $placeholder Der Platzhalter, der den Originalwert ersetzen soll
     * @return string Der modifizierte HTML-String
     */
    private function replaceHtmlAttributeWithPlaceholder(string $html, string $tag, string $attr, string $original, string $placeholder): string
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($doc);
        $query = sprintf('//' . $tag . '[@' . $attr . ']');
        foreach ($xpath->query($query) as $node) {
            /** @var \DOMElement $node */
            if ($node->getAttribute($attr) === $original) {
                $node->setAttribute($attr, $placeholder);
            }
        }
        // body extrahieren, da loadHTML immer ein vollständiges HTML-Dokument erzeugt
        $body = $doc->getElementsByTagName('body')->item(0);
        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $doc->saveHTML($child);
        }
        return $innerHTML;
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
                $slug = SlugUtility::generateSlug($record, $table, $field, $slugFields);
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
