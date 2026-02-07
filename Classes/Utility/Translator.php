<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use DeepL\TranslateTextOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Service\GlossaryService;
use ThieleUndKlose\Autotranslate\Service\TranslationCacheService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;

class Translator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const AUTOTRANSLATE_LAST = 'autotranslate_last';
    public const AUTOTRANSLATE_EXCLUDE = 'autotranslate_exclude';
    public const AUTOTRANSLATE_LANGUAGES = 'autotranslate_languages';

    public const TRANSLATE_MODE_BOTH = 'create_update';
    public const TRANSLATE_MODE_UPDATE_ONLY = 'update_only';
    public const TRANSLATE_MODE_CREATE_ONLY = 'create_only';

    public array $languages = [];
    public array $siteLanguages = [];
    protected ?string $apiKey = null;
    protected ?int $pageId = null;
    protected ?GlossaryService $glossaryService = null;

    public function __construct(int $pageId)
    {
        $this->pageId = $pageId;
        ['key' => $this->apiKey] = TranslationHelper::apiKey($this->pageId);
        $this->siteLanguages = TranslationHelper::siteConfigurationValue($this->pageId, ['languages']) ?? [];
        $this->glossaryService = GeneralUtility::makeInstance(GlossaryService::class);
    }

    /**
     * Translate the loaded record to target languages
     *
     * @throws \RuntimeException If DeepL API key is invalid or has no characters left
     */
    public function translate(
        string $table,
        int $recordUid,
        ?DataHandler $parentObject = null,
        ?string $languagesToTranslate = null,
        string $translateMode = self::TRANSLATE_MODE_BOTH
    ): void {
        $deeplApiKeyDetails = DeeplApiHelper::checkApiKey($this->apiKey);
        if ($deeplApiKeyDetails['error']) {
            LogUtility::log($this->logger, 'DeepL API Key is not valid: {error}', [
                'error' => $deeplApiKeyDetails['error'],
            ]);
            throw new \RuntimeException('DeepL API Key is not valid: ' . $deeplApiKeyDetails['error']);
        }
        if (!$deeplApiKeyDetails['isValid']) {
            LogUtility::log($this->logger, 'DeepL API Key is not valid: {error}', [
                'error' => 'No API Key given.',
            ]);
            throw new \RuntimeException('DeepL API Key is not valid: No API Key given.');
        }
        if ($deeplApiKeyDetails['charactersLeft'] <= 0) {
            LogUtility::log($this->logger, 'DeepL API Key has no characters left: {charactersLeft}', [
                'charactersLeft' => $deeplApiKeyDetails['charactersLeft'],
            ]);
            throw new \RuntimeException('DeepL API Key has no characters left: ' . $deeplApiKeyDetails['charactersLeft']);
        }

        $record = Records::getRecord($table, $recordUid);

        if ($record === null) {
            LogUtility::log($this->logger, 'Record {table}:{uid} not found, skipping translation.', [
                'table' => $table,
                'uid' => $recordUid,
            ], LogUtility::MESSAGE_WARNING);
            return;
        }

        // Exit if record is a localized one
        $parentField = TranslationHelper::translationOrigPointerField($table);
        if ($parentField === null || (int)($record[$parentField] ?? 0) > 0) {
            return;
        }

        // Exit if record is marked for exclude
        if ((int)($record[self::AUTOTRANSLATE_EXCLUDE] ?? 0) === 1) {
            return;
        }

        // Load translation columns for table
        $columns = TranslationHelper::translationTextfields($this->pageId, $table);
        if ($columns === null || $columns === []) {
            LogUtility::log($this->logger, 'No text fields configured for table {table} on page {pageId}. Check site configuration.', [
                'table' => $table,
                'pageId' => $this->pageId,
            ], LogUtility::MESSAGE_WARNING);
            return;
        }

        // Set target languages by record if null is given
        if ($languagesToTranslate === null) {
            $languagesToTranslate = $record[self::AUTOTRANSLATE_LANGUAGES] ?? '';
        }

        $localizedContents = [];
        $languageIds = GeneralUtility::trimExplode(',', $languagesToTranslate, true);

        if ($languageIds === []) {
            LogUtility::log($this->logger, 'No target languages set for record {table}:{uid}.', [
                'table' => $table,
                'uid' => $recordUid,
            ], LogUtility::MESSAGE_WARNING);
            return;
        }

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
                    'uid' => $recordUid,
                ]);
                continue;
            }

            if (!$existingTranslation) {
                $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
                $dataHandler->start([], []);
                $localizedUid = $dataHandler->localize($table, $recordUid, $languageId);
            } else {
                $localizedUid = (int)$existingTranslation['uid'];
            }

            if (empty($localizedUid)) {
                LogUtility::log($this->logger, 'No Translation of {table} with uid {uid} because DataHandler localize failed.', [
                    'table' => $table,
                    'uid' => $recordUid,
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

                        $references = match ($type) {
                            'file' => Records::getRecords($referenceTable, 'uid', [
                                "{$foreignField} = " . $recordUid,
                                "deleted = 0",
                                "sys_language_uid = 0",
                                "tablenames = '{$table}'",
                                "fieldname = '{$referenceColumn}'",
                            ]),
                            'inline' => Records::getRecords($referenceTable, 'uid', array_merge(
                                [
                                    "{$foreignField} = " . $recordUid,
                                    "deleted = 0",
                                    "sys_language_uid = 0",
                                ],
                                isset($GLOBALS['TCA'][$referenceTable]['columns']['fieldname'])
                                    ? ["fieldname = '{$referenceColumn}'"]
                                    : []
                            )),
                            default => null,
                        };

                        if ($references === null) {
                            LogUtility::log($this->logger, 'Unsupported reference type {type} for column {referenceColumn} in table {table}.', [
                                'type' => $type,
                                'referenceColumn' => $referenceColumn,
                                'table' => $table,
                            ], LogUtility::MESSAGE_WARNING);
                            continue;
                        }

                        if (!empty($references)) {
                            foreach ($references as $referenceUid) {
                                $referenceUid = (int)$referenceUid;
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
                                    $translatedReferenceUid = (int)$dataHandler->localize($referenceTable, $referenceUid, $languageId);

                                    Records::updateRecord(
                                        $referenceTable,
                                        $translatedReferenceUid,
                                        [
                                            $foreignField => $localizedContents[$languageId][$recordUid],
                                        ]
                                    );
                                } else {
                                    $translatedReferenceUid = (int)$referenceTranslation['uid'];
                                }

                                if (!empty($columnsReference)) {
                                    $recordReference = ($parentObject !== null && isset($parentObject->datamap[$referenceTable][$referenceUid]))
                                        ? $parentObject->datamap[$referenceTable][$referenceUid]
                                        : Records::getRecord($referenceTable, $referenceUid);

                                    if ($recordReference === null) {
                                        continue;
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
            self::AUTOTRANSLATE_LAST => time(),
        ]);
    }

    /**
     * Translate the given record properties
     */
    public function translateRecordProperties(array $record, int $targetLanguageUid, array $columns, string $table, int $localizedUid): array
    {
        $translatedColumns = [];

        try {
            $toTranslateObject = array_intersect_key($record, array_flip($columns));
            $toTranslate = array_filter($toTranslateObject, static fn($value) => is_string($value) && $value !== '');
            $deeplSourceLang = $this->deeplSourceLanguage();
            $deeplTargetLang = $this->deeplTargetLanguage($targetLanguageUid);
            $result = null;
            $glossary = null;

            if ($deeplTargetLang === null) {
                throw new \RuntimeException(
                    'No DeepL target language configured for language uid ' . $targetLanguageUid
                    . '. Please set "deeplTargetLang" in Site Configuration → Languages for this language.'
                );
            }

            if (count($toTranslate) > 0) {
                $toTranslate = $this->extractAndReplaceTranslatableHtmlAttributes($toTranslate);
                $translator = new \DeepL\Translator($this->apiKey);

                // Get optional glossary handled by 3rd party extension
                if ($deeplSourceLang && $deeplTargetLang && TranslationHelper::glossaryEnabled($this->pageId)) {
                    $glossary = $this->glossaryService->getGlossary($deeplSourceLang, $deeplTargetLang, $this->pageId, $translator);
                }

                // Experimental: translate flexform fields
                // TODO: Let user define which fields in flexform should be translated to prevent translating settings
                if (isset($toTranslate['pi_flexform'])) {
                    $xml = simplexml_load_string($record['pi_flexform']);

                    foreach ($xml->xpath('//field') as $field) {
                        $value = (string)$field->value;
                        if (!empty(trim($value))
                            && !str_contains($value, '<')
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
                    if ($v === null) {
                        continue;
                    }

                    $field = $keys[$k];
                    if (str_starts_with($field, '__ATTR__')) {
                        $translatedAttributes[$field] = $v->text;
                    }
                }

                foreach ($result as $k => $v) {
                    if ($v === null) {
                        continue;
                    }

                    $field = $keys[$k];
                    if (str_starts_with($field, '__ATTR__')) {
                        continue;
                    }
                    $translatedValue = $this->restoreTranslatedHtmlAttributes($v->text, $translatedAttributes);
                    $translatedColumns[$field] = $translatedValue;
                }
            }

            // Add fields to copy in translation from extension configuration
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

            // Set date and time of translation
            $translatedColumns[self::AUTOTRANSLATE_LAST] = time();

            LogUtility::log($this->logger, 'Successful translated to target language {deeplTargetLang}.', [
                'deeplTargetLang' => $deeplTargetLang,
                'toTranslate' => $toTranslate,
                'result' => $result,
                'translatedColumns' => $translatedColumns,
            ]);
        } catch (\Exception $e) {
            LogUtility::log($this->logger, 'Translation Error: {error}.', ['error' => $e->getMessage()], LogUtility::MESSAGE_ERROR);
            throw $e;
        }

        return $translatedColumns;
    }

    private function deeplSourceLanguage(): ?string
    {
        foreach ($this->siteLanguages as $language) {
            if ($language['languageId'] === 0) {
                return empty($language['deeplSourceLang']) ? null : $language['deeplSourceLang'];
            }
        }

        return null;
    }

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
     * Replaces translatable HTML attributes with placeholders and returns the modified array.
     */
    private function extractAndReplaceTranslatableHtmlAttributes(array $toTranslate): array
    {
        $attributeMap = [
            ['tag' => 'a', 'attr' => 'title'],
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

    private function isHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    /**
     * Extracts all values of a specific attribute from a specific tag in HTML.
     */
    private function extractHtmlAttributes(string $html, string $tagName, string $attributeName): array
    {
        $values = [];
        if (trim($html) === '') {
            return $values;
        }

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $xpath = new \DOMXPath($doc);
        $query = '//' . $tagName . '[@' . $attributeName . ']';
        foreach ($xpath->query($query) as $node) {
            /** @var \DOMElement $node */
            $values[] = $node->getAttribute($attributeName);
        }

        return $values;
    }

    /**
     * Replaces a specific attribute of a tag in an HTML string with a placeholder.
     */
    private function replaceHtmlAttributeWithPlaceholder(string $html, string $tag, string $attr, string $original, string $placeholder): string
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($doc);
        $query = '//' . $tag . '[@' . $attr . ']';

        foreach ($xpath->query($query) as $node) {
            /** @var \DOMElement $node */
            if ($node->getAttribute($attr) === $original) {
                $node->setAttribute($attr, $placeholder);
            }
        }

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

        $cacheService = GeneralUtility::makeInstance(TranslationCacheService::class);

        // Translate text fields with cache
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

        // Translate HTML fields with cache
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

        // Restore field order from $toTranslate
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
     * Check if the field is a richtext field
     */
    public function isRichtextField(array $record, string $table, string $columnName): bool
    {
        $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$columnName]['config'] ?? null;
        if (!$fieldConfig) {
            return false;
        }

        // Check for CType specific configuration
        $ctype = $record['CType'] ?? null;
        if ($ctype && isset($GLOBALS['TCA'][$table]['types'][$ctype]['columnsOverrides'][$columnName]['config'])) {
            $fieldConfig = $GLOBALS['TCA'][$table]['types'][$ctype]['columnsOverrides'][$columnName]['config'];
        }

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
                'cached' => count($partialCache['cached']),
            ]);

            $freshTranslations = $translator->translateText(
                $partialCache['uncached'],
                $sourceLang,
                $targetLang,
                $options
            );

            foreach ($freshTranslations as $resultIndex => $result) {
                $originalIndex = $partialCache['mapping'][$resultIndex];
                $finalResults[$originalIndex] = $result;
            }

            $cacheService->cacheIndividualTranslations(
                $partialCache['uncached'],
                $freshTranslations,
                $sourceLang,
                $targetLang,
                $options
            );
        }

        // Cache complete result (only if all results are valid)
        $validResults = array_filter($finalResults, static fn($result) => $result !== null);
        if (count($validResults) === count($finalResults)) {
            $cacheService->setCachedTranslation($completeCacheKey, $finalResults);
        } else {
            LogUtility::log($this->logger, 'Not caching complete result due to null values: {valid}/{total}', [
                'valid' => count($validResults),
                'total' => count($finalResults),
            ]);
        }

        return $finalResults;
    }

    /**
     * Replaces placeholders in the HTML with the translated attribute values.
     */
    private function restoreTranslatedHtmlAttributes(string $html, array $attrTranslations): string
    {
        foreach ($attrTranslations as $placeholder => $translatedValue) {
            if ($translatedValue !== null) {
                $html = str_replace($placeholder, $translatedValue, $html);
            }
        }

        return $html;
    }

    /**
     * Builds l10n_state array for translated fields
     */
    private function buildL10nState(string $table, int $targetLanguageUid, array $translatedFields, int $localizedUid): string
    {
        if (!isset($GLOBALS['TCA'][$table]['ctrl']['transOrigDiffSourceField'])) {
            return '{}';
        }

        try {
            $existingTranslation = Records::getRecordTranslation($table, $localizedUid, $targetLanguageUid);

            $l10nState = [];
            if ($existingTranslation && !empty($existingTranslation['l10n_state'])) {
                $l10nState = json_decode($existingTranslation['l10n_state'], true) ?: [];
            }

            foreach ($translatedFields as $field) {
                $l10nState[$field] = 'custom';
            }

            return json_encode($l10nState);
        } catch (\Exception $e) {
            LogUtility::log($this->logger, 'Error building l10n_state: {error}', [
                'error' => $e->getMessage(),
                'table' => $table,
            ], LogUtility::MESSAGE_ERROR);

            return '{}';
        }
    }

    /**
     * Generate slugs for a translated record
     */
    private function generateSlugs(string $table, int $uid): void
    {
        $slugFields = SlugUtility::slugFields($table);
        if (empty($slugFields)) {
            return;
        }

        $record = Records::getRecord($table, $uid);
        $fieldsToUpdate = [];

        foreach (array_keys($slugFields) as $field) {
            $slug = SlugUtility::generateSlug($record, $table, $field);
            if ($slug !== null) {
                $fieldsToUpdate[$field] = $slug;
            }
        }

        if (!empty($fieldsToUpdate)) {
            Records::updateRecord($table, $uid, $fieldsToUpdate);
        }
    }
}
