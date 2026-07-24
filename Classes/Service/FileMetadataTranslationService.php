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

namespace ThieleUndKlose\Autotranslate\Service;

use DeepL\TranslateTextOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ThieleUndKlose\Autotranslate\Hooks\DataHandler as AutotranslateDataHandlerHook;
use ThieleUndKlose\Autotranslate\Utility\DeeplApiHelper;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileMetadataTranslationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const TABLE = 'sys_file_metadata';

    /**
     * Translate configured sys_file_metadata fields into selected target languages.
     *
     * File metadata is global FAL data and does not have a reliable page/site
     * context. Therefore source and target DeepL languages are resolved from the
     * explicit extension configuration mapping instead of TYPO3 site settings.
     *
     * @param string[]|null $changedFields
     */
    public function translate(int $metadataUid, ?array $changedFields = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $record = Records::getRecord(self::TABLE, $metadataUid);
        if (!is_array($record) || (int)($record['sys_language_uid'] ?? 0) !== 0) {
            return;
        }

        if ((int)($record['autotranslate_exclude'] ?? 0) === 1) {
            return;
        }

        $fields = $this->configuredFields();
        if ($fields === []) {
            return;
        }

        if ($changedFields !== null) {
            $changedTranslatableFields = array_intersect($fields, $changedFields);
            $changedControlFields = array_intersect(['autotranslate_languages', 'autotranslate_exclude'], $changedFields);
            if ($changedTranslatableFields === [] && $changedControlFields === []) {
                return;
            }
        }

        $languageMapping = $this->languageMapping();
        $sourceLanguage = $this->sourceLanguageCode($languageMapping[0] ?? null);
        if ($sourceLanguage === null) {
            $this->log('Skipping sys_file_metadata translation because fileMetadataLanguageMapping has no source entry for language uid 0.', [], LogUtility::MESSAGE_WARNING);
            return;
        }

        $targetLanguageUids = GeneralUtility::intExplode(',', (string)($record['autotranslate_languages'] ?? ''), true);
        $targetLanguageUids = array_values(array_filter($targetLanguageUids, static fn(int $languageUid): bool => $languageUid > 0));
        if ($targetLanguageUids === []) {
            return;
        }

        $toTranslate = $this->extractTranslatableValues($record, $fields);
        if ($toTranslate === []) {
            return;
        }

        $apiKey = $this->extensionConfigurationValue('apiKey', null);
        if (!is_string($apiKey) || trim($apiKey) === '') {
            $this->log('Skipping sys_file_metadata translation because no DeepL API key is configured.', [], LogUtility::MESSAGE_WARNING);
            return;
        }

        $apiKeyDetails = DeeplApiHelper::checkApiKey($apiKey);
        if (!$apiKeyDetails['isValid'] || !empty($apiKeyDetails['error'])) {
            $this->log('Skipping sys_file_metadata translation because the DeepL API key is not valid: {error}', [
                'error' => $apiKeyDetails['error'] ?? 'unknown error',
            ], LogUtility::MESSAGE_WARNING);
            return;
        }

        $translator = DeeplApiHelper::createTranslator($apiKey);
        $cacheService = GeneralUtility::makeInstance(TranslationCacheService::class);
        $options = [
            TranslateTextOptions::SPLIT_SENTENCES => true,
        ];

        foreach ($targetLanguageUids as $targetLanguageUid) {
            $targetLanguage = $languageMapping[$targetLanguageUid] ?? null;
            if ($targetLanguage === null || $targetLanguage === '') {
                $this->log('Skipping sys_file_metadata translation to language uid {languageUid} because fileMetadataLanguageMapping has no target entry.', [
                    'languageUid' => $targetLanguageUid,
                ], LogUtility::MESSAGE_WARNING);
                continue;
            }

            $localizedUid = $this->resolveLocalizedMetadataUid($metadataUid, $targetLanguageUid);
            if ($localizedUid === null) {
                $this->log('Skipping sys_file_metadata translation to language uid {languageUid} because localization failed.', [
                    'languageUid' => $targetLanguageUid,
                    'metadataUid' => $metadataUid,
                ], LogUtility::MESSAGE_WARNING);
                continue;
            }

            $translatedColumns = $this->translateValues(
                $toTranslate,
                $sourceLanguage,
                $targetLanguage,
                $options,
                $translator,
                $cacheService
            );

            if ($translatedColumns === []) {
                continue;
            }

            $translatedColumns['autotranslate_last'] = time();
            Records::updateRecord(self::TABLE, $localizedUid, $translatedColumns);
        }
    }

    /**
     * Check whether global FAL metadata translation is enabled in extension configuration.
     */
    private function isEnabled(): bool
    {
        return (bool)$this->extensionConfigurationValue('enableFileMetadataTranslation', false);
    }

    /**
     * Resolve configured sys_file_metadata columns and keep only plain text compatible TCA fields.
     *
     * @return string[]
     */
    private function configuredFields(): array
    {
        $fields = GeneralUtility::trimExplode(',', (string)$this->extensionConfigurationValue('fileMetadataFields', ''), true);
        return array_values(array_filter($fields, static function (string $field): bool {
            $config = $GLOBALS['TCA'][self::TABLE]['columns'][$field]['config'] ?? null;
            return is_array($config) && in_array(($config['type'] ?? null), ['input', 'text'], true);
        }));
    }

    /**
     * Build the explicit TYPO3 language uid to DeepL language code mapping.
     *
     * FAL metadata records are not tied to a site, so the default language uid 0
     * must be configured explicitly as source language.
     *
     * @return array<int, string>
     */
    private function languageMapping(): array
    {
        $mapping = [];
        $items = GeneralUtility::trimExplode(',', (string)$this->extensionConfigurationValue('fileMetadataLanguageMapping', ''), true);
        foreach ($items as $item) {
            $parts = GeneralUtility::trimExplode('=', $item, true, 2);
            if (count($parts) !== 2 || !is_numeric($parts[0]) || $parts[1] === '') {
                continue;
            }
            $mapping[(int)$parts[0]] = strtoupper(str_replace('_', '-', $parts[1]));
        }

        return $mapping;
    }

    /**
     * Normalize configured DeepL source language codes.
     *
     * DeepL accepts regional variants such as EN-US or PT-BR as target languages,
     * but source_lang must use the base language code. Keeping the mapping
     * tolerant avoids hard failures when site language codes are copied into the
     * file metadata mapping.
     */
    private function sourceLanguageCode(?string $languageCode): ?string
    {
        if ($languageCode === null || trim($languageCode) === '') {
            return null;
        }

        $languageCode = strtoupper(str_replace('_', '-', trim($languageCode)));
        if (strpos($languageCode, '-') === false) {
            return $languageCode;
        }

        return (string)strtok($languageCode, '-');
    }

    /**
     * Extract non-empty string values from the configured metadata fields.
     *
     * Numeric values are ignored intentionally to avoid translating technical
     * metadata accidentally.
     *
     * @param string[] $fields
     * @return array<string, string>
     */
    private function extractTranslatableValues(array $record, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $value = $record[$field] ?? null;
            if (!is_string($value) || trim($value) === '' || is_numeric($value)) {
                continue;
            }
            $values[$field] = $value;
        }

        return $values;
    }

    /**
     * Find or create the localized sys_file_metadata record for the given target language.
     */
    private function resolveLocalizedMetadataUid(int $metadataUid, int $targetLanguageUid): ?int
    {
        $existingTranslation = Records::getRecordTranslation(self::TABLE, $metadataUid, $targetLanguageUid);
        if (is_array($existingTranslation) && !empty($existingTranslation['uid'])) {
            return (int)$existingTranslation['uid'];
        }

        return AutotranslateDataHandlerHook::runWithSuspendedHook(static function () use ($metadataUid, $targetLanguageUid): ?int {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], []);
            $localizedUid = $dataHandler->localize(self::TABLE, $metadataUid, $targetLanguageUid);

            if ($localizedUid === false || $localizedUid === null) {
                $translation = Records::getRecordTranslation(self::TABLE, $metadataUid, $targetLanguageUid);
                return is_array($translation) && !empty($translation['uid']) ? (int)$translation['uid'] : null;
            }

            return (int)$localizedUid;
        });
    }

    /**
     * Translate all metadata values for one target language and preserve field names.
     *
     * The same cache mechanism as regular record translations is used to avoid
     * repeated DeepL requests for unchanged metadata.
     *
     * @return array<string, string>
     */
    private function translateValues(
        array $toTranslate,
        string $sourceLanguage,
        string $targetLanguage,
        array $options,
        \DeepL\Translator $translator,
        TranslationCacheService $cacheService
    ): array {
        $texts = array_values($toTranslate);
        $cacheKey = $cacheService->generateCacheKey($texts, $sourceLanguage, $targetLanguage, $options);
        $result = $cacheService->getCachedTranslation($cacheKey);

        if ($result === null) {
            $result = $translator->translateText($texts, $sourceLanguage, $targetLanguage, $options);
            $cacheService->setCachedTranslation($cacheKey, $result);
            $cacheService->cacheIndividualTranslations($texts, $result, $sourceLanguage, $targetLanguage, $options);
        }

        $translatedColumns = [];
        $fields = array_keys($toTranslate);
        foreach ($result as $index => $translation) {
            if ($translation === null || !isset($fields[$index])) {
                continue;
            }
            $translatedColumns[$fields[$index]] = $translation->text;
        }

        return $translatedColumns;
    }

    /**
     * Read one AutoTranslate extension configuration value with a safe fallback.
     *
     * TYPO3 throws when the extension configuration is not available yet, for
     * example during early bootstrap or update steps.
     */
    private function extensionConfigurationValue(string $path, $default = null)
    {
        try {
            $value = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate', $path);
            return $value === null ? $default : $value;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Write an AutoTranslate log entry without requiring logger injection.
     */
    private function log(string $message, array $data = [], int $type = LogUtility::MESSAGE_INFO): void
    {
        $logger = $this->logger instanceof LoggerInterface ? $this->logger : new NullLogger();
        LogUtility::log($logger, $message, $data, $type);
    }
}
