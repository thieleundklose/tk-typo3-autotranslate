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
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
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
                            $translatedColumns = $this->translateRecordProperties($recordSysFileReference, (int)$languageId, $columnsSysFileLanguage, $table, $translatedSysFileReferenceUid);
                            if (count($translatedColumns)) {
                                Records::updateRecord('sys_file_reference', $translatedSysFileReferenceUid, $translatedColumns);
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
                            $translationResult = $this->translateText(
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

                $result = empty($toTranslate) ? [] : $this->translateText($record, $table, $toTranslate, $deeplSourceLang, $deeplTargetLang, $glossary);
            }

            $keys = array_keys($toTranslate);
            if (!empty($result)) {
                $translatedAttributes = [];
                foreach ($result as $k => $v) {
                    $field = $keys[$k];
                    if (strpos($field, '__ATTR__') === 0) {
                        $translatedAttributes[$field] = $v->text;
                    }
                }

                foreach ($result as $k => $v) {
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

    private function translateText(array $record, string $table, array $toTranslate, ?string $deeplSourceLang, string $deeplTargetLang, ?Glossary $glossary): array
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

        // Cache complete result
        $cacheService->setCachedTranslation($completeCacheKey, $finalResults);

        LogUtility::log($this->logger, 'TEMP!! Translation cache stats: {stats}', [
            'stats' => [
                'total_texts' => count($texts),
                'cache_hits' => count($partialCache['cached']),
                'api_calls' => count($partialCache['uncached']),
                'cache_hit_ratio' => round((count($partialCache['cached']) / count($texts)) * 100, 2) . '%'
            ]
        ]);

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
            $html = str_replace($placeholder, $translatedValue, $html);
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
