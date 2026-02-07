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
     * @return void
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function translate(string $table, int $recordUid, ?DataHandler $parentObject = null, ?string $languagesToTranslate = null, string $translateMode = self::TRANSLATE_MODE_BOTH): void
    {

        $deeplApiKeyDetails = DeeplApiHelper::checkApiKey($this->apiKey);
        if ($deeplApiKeyDetails['error']){
            LogUtility::log($this->logger, 'DeepL API Key is not valid: {error}', [
                'error' => $deeplApiKeyDetails['error']
            ]);
            throw new \RuntimeException('DeepL API Key is not valid: ' . $deeplApiKeyDetails['error']);
        }
        if (!$deeplApiKeyDetails['isValid']) {
            LogUtility::log($this->logger, 'DeepL API Key is not valid: {error}', [
                'error' => 'No API Key given.'
            ]);
            throw new \RuntimeException('DeepL API Key is not valid: No API Key given.');
        }
        if ($deeplApiKeyDetails['charactersLeft'] <= 0) {
            LogUtility::log($this->logger, 'DeepL API Key has no characters left: {charactersLeft}', [
                'charactersLeft' => $deeplApiKeyDetails['charactersLeft']
            ]);
            throw new \RuntimeException('DeepL API Key has no characters left: ' . $deeplApiKeyDetails['charactersLeft']);
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
            $referenceTables = TranslationHelper::additionalReferenceTables();
            foreach ($referenceTables as $referenceTable) {
                $columnsReference = TranslationHelper::translationTextfields($this->pageId, $referenceTable);
                $autotranslateReferences = TranslationHelper::translationReferenceColumns($this->pageId, $table, $referenceTable);

                if (!empty($autotranslateReferences)) {
                    foreach ($autotranslateReferences as $referenceColumn) {
                        $type = $GLOBALS['TCA'][$table]['columns'][$referenceColumn]['config']['type'] ?? null;
                        $foreignField = $GLOBALS['TCA'][$table]['columns'][$referenceColumn]['config']['foreign_field'];

                        switch ($type) {
                            // sys_file_reference
                            case 'file':
                                $references = Records::getRecords($referenceTable, 'uid', [
                                    "{$foreignField} = " . $recordUid,
                                    "deleted = 0",
                                    "sys_language_uid = 0",
                                    "tablenames = '{$table}'",
                                    "fieldname = '{$referenceColumn}'",
                                ]);
                                break;
                            case 'inline':

                                $constraints = [
                                    "{$foreignField} = " . $recordUid,
                                    "deleted = 0",
                                    "sys_language_uid = 0",
                                ];

                                // Only add fieldname constraint if the inline table has this field
                                if (isset($GLOBALS['TCA'][$referenceTable]['columns']['fieldname'])) {
                                    $constraints[] = "fieldname = '{$referenceColumn}'";
                                }

                                $references = Records::getRecords($referenceTable, 'uid', $constraints);
                                break;
                            default:
                                LogUtility::log($this->logger, 'Unsupported reference type {type} for column {referenceColumn} in table {table}.', [
                                    'type' => $type,
                                    'referenceColumn' => $referenceColumn,
                                    'table' => $table,
                                ], LogUtility::MESSAGE_WARNING);
                                continue 2;
                        }

                        if (!empty($references)) {
                            foreach ($references as $referenceUid) {

                                $referenceTranslation = Records::getRecordTranslation($referenceTable, $referenceUid, (int)$languageId);

                                if ($translateMode === self::TRANSLATE_MODE_UPDATE_ONLY && empty($referenceTranslation)) {
                                    LogUtility::log($this->logger, 'No {referenceTable} {referenceUid} Translation of {table} with uid {uid} because mode "update only".', [
                                        'referenceTable' => $referenceTable,
                                        'table' => $table,
                                        'uid' => $recordUid,
                                        'referenceUid' => $referenceUid,
                                    ]);
                                    continue;
                                }

                                if (empty($referenceTranslation)) {
                                    $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                                    $dataHandler->start([], []);
                                    $translatedReferenceUid = $dataHandler->localize($referenceTable, $referenceUid, $languageId);

                                    Records::updateRecord(
                                        $referenceTable,
                                        $translatedReferenceUid,
                                        [
                                            $foreignField => $localizedContents[$languageId][$recordUid],
                                        ]
                                    );

                                } else {
                                    $translatedReferenceUid = $referenceTranslation['uid'];
                                }

                                if (count($columnsReference)) {
                                    if ($parentObject !== null && isset($parentObject->datamap[$referenceTable]) && isset($parentObject->datamap[$referenceTable][$referenceUid])) {
                                        $recordReference = $parentObject->datamap[$referenceTable][$referenceUid];
                                    } else {
                                        $recordReference = Records::getRecord($referenceTable, $referenceUid);
                                    }
                                    $translatedColumns = $this->translateRecordProperties($recordReference, (int)$languageId, $columnsReference, $table, $translatedReferenceUid);
                                    if (count($translatedColumns)) {
                                        Records::updateRecord($referenceTable, $translatedReferenceUid, $translatedColumns);
                                    }
                                }
                            }
                        }
                    }
                }
            }


            // Translate properties with given service
            $translatedColumns = $this->translateRecordProperties($record, (int)$languageId, $columns, $table, $localizedUid);

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
     * @param string $table
     * @param int $localizedUid
     * @return array
     */
    public function translateRecordProperties(array $record, int $targetLanguageUid, array $columns, string $table, int $localizedUid): array
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
        // Suppress errors for invalid HTML
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $xpath = new \DOMXPath($doc);
        // XPath query for all matching attributes
        $query = sprintf('//' . $tagName . '[@' . $attributeName . ']');
        foreach ($xpath->query($query) as $node) {
            /** @var \DOMElement $node */
            $values[] = $node->getAttribute($attributeName);
        }
        return $values;
    }

    /**
     * Replaces a specific attribute of a tag in an HTML string with a placeholder.
     *
     * @param string $html The HTML string
     * @param string $tag The tag name (e.g. 'a')
     * @param string $attr The attribute (e.g. 'title')
     * @param string $original The original attribute value to replace
     * @param string $placeholder The placeholder to replace the original value with
     * @return string The modified HTML string
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
        // Extract body since loadHTML always creates a complete HTML document
        $body = $doc->getElementsByTagName('body')->item(0);
        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $doc->saveHTML($child);
        }
        return $innerHTML;
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
     * Returns an array indicating whether each field in $toTranslate is a richtext field.
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
