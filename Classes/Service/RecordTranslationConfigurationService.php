<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Service;

use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Site\SiteFinder;

class RecordTranslationConfigurationService
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    public function getConfiguration(string $table, array $record): ?array
    {
        if (!$this->hasTriggerFields($table) || !$this->isDefaultLanguageRecord($table, $record)) {
            return null;
        }

        if ((int)($record[Translator::AUTOTRANSLATE_EXCLUDE] ?? 0) === 1) {
            return null;
        }

        $pageId = $this->resolvePageId($table, $record);
        if ($pageId <= 0) {
            return null;
        }

        $site = $this->siteFinder->getSiteByPageId($pageId);
        $siteConfiguration = $site->getConfiguration();

        if (TranslationHelper::translationTextfields($pageId, $table) === null) {
            return null;
        }

        $configuredLanguageIds = $this->resolveConfiguredLanguageIds($table, $record, $siteConfiguration);
        $languages = [];
        foreach (TranslationHelper::possibleTranslationLanguages($site->getLanguages()) as $languageId => $language) {
            $languageId = (int)$languageId;
            if (!$this->getBackendUser()->checkLanguageAccess($languageId)) {
                continue;
            }

            $translated = Records::getRecordTranslation($table, (int)$record['uid'], $languageId) !== null;
            $languages[] = [
                'id' => $languageId,
                'title' => $language->getTitle(),
                'selected' => $configuredLanguageIds === []
                    ? true
                    : in_array($languageId, $configuredLanguageIds, true) || $translated,
                'translated' => $translated,
            ];
        }

        if ($languages === []) {
            return null;
        }

        return [
            'pageId' => $pageId,
            'languages' => $languages,
        ];
    }

    public function resolvePageId(string $table, array $record): int
    {
        if ($table === 'pages') {
            return (int)($record['uid'] ?? 0);
        }

        return (int)($record['pid'] ?? 0);
    }

    /**
     * @return int[]
     */
    public function sanitizeRequestedLanguageIds(array $availableLanguages, mixed $requestedLanguages): array
    {
        if (!is_array($requestedLanguages)) {
            return [];
        }

        $allowedLanguageIds = array_map(
            static fn(array $language): int => (int)$language['id'],
            $availableLanguages
        );

        $requestedLanguageIds = array_map('intval', $requestedLanguages);

        return array_values(array_filter(
            array_unique($requestedLanguageIds),
            static fn(int $languageId): bool => in_array($languageId, $allowedLanguageIds, true)
        ));
    }

    private function hasTriggerFields(string $table): bool
    {
        return isset($GLOBALS['TCA'][$table]['columns'][Translator::AUTOTRANSLATE_LANGUAGES]);
    }

    private function isDefaultLanguageRecord(string $table, array $record): bool
    {
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? 'sys_language_uid';
        $parentField = TranslationHelper::translationOrigPointerField($table);

        return (int)($record[$languageField] ?? 0) === 0
            && ($parentField === null || (int)($record[$parentField] ?? 0) === 0);
    }

    /**
     * @return int[]
     */
    private function resolveConfiguredLanguageIds(string $table, array $record, array $siteConfiguration): array
    {
        $recordLanguageList = trim((string)($record[Translator::AUTOTRANSLATE_LANGUAGES] ?? ''));
        if ($recordLanguageList !== '') {
            return array_map('intval', array_values(array_filter(explode(',', $recordLanguageList), 'strlen')));
        }

        $translationSettings = TranslationHelper::translationSettingsDefaults($siteConfiguration, $table);
        $defaultLanguageList = trim((string)($translationSettings['autotranslateLanguages'] ?? ''));
        if ($defaultLanguageList === '') {
            return [];
        }

        return array_map('intval', array_values(array_filter(explode(',', $defaultLanguageList), 'strlen')));
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
