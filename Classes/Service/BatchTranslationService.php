<?php
namespace ThieleUndKlose\Autotranslate\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use ThieleUndKlose\Autotranslate\ValueObject\TranslationResult;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BatchTranslationService implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    private ?string $lastWarning = null;

    private ?string $lastNotice = null;

    public function getLastWarning(): ?string
    {
        return $this->lastWarning;
    }

    public function getLastNotice(): ?string
    {
        return $this->lastNotice;
    }

    /**
     * Translate the given item.
     * @param BatchItem $item
     * @return bool
     */
    public function translate(BatchItem $item): bool
    {
        $this->lastWarning = null;
        $this->lastNotice = null;
        $item->setError('');
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $siteConfiguration = $siteFinder->getSiteByPageId($item->getPid());
        } catch (\Exception $e) {
            $message = sprintf('No site configuration found for pid %s.', $item->getPid());

            return $this->fail($item, $message);
        }

        $defaultLanguageId = TranslationHelper::defaultLanguageIdFromSiteConfiguration($siteConfiguration);
        $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());

        // check if target language is in pissible translation languages
        if (!isset($languages[$item->getSysLanguageUid()])) {
            $message = 'Target language ({targetLanguage}) not in site languages ({siteLanguages}).';
            $messageData = [
                'targetLanguage' => $item->getSysLanguageUid(),
                'siteLanguages' => implode(',', array_keys($languages)),
            ];

            return $this->fail($item, $message, $messageData);
        }

        $targetLanguageConfiguration = $this->findTargetLanguageConfiguration(
            $siteConfiguration->getConfiguration()['languages'] ?? [],
            $item->getSysLanguageUid()
        );
        if (trim((string)($targetLanguageConfiguration['deeplTargetLang'] ?? '')) === '') {
            return $this->fail(
                $item,
                'No DeepL target language is configured for site language {targetLanguage}.',
                ['targetLanguage' => $item->getSysLanguageUid()]
            );
        }

        // check if page exists
        $pageRecord = Records::getRecord('pages', $item->getPid());
        if ($pageRecord === null) {
            return $this->fail($item, 'No page found for pid {pid}.', ['pid' => $item->getPid()]);
        }

        // init translation service
        $translator = GeneralUtility::makeInstance(Translator::class, $item->getPid());
        $changedFields = null; // Batch jobs are explicit full translations, independent of DataHandler changes.
        $tablesToTranslate = TranslationHelper::tablesToTranslate();
        $translationResult = new TranslationResult();
        try {
            foreach ($tablesToTranslate as $table) {
                if ($table === 'pages') {
                    // translate page
                    $translationResult->merge(
                        $translator->translateWithResult($table, $item->getPid(), null, (string)$item->getSysLanguageUid(), $item->getMode(), $changedFields)
                    );
                } else {
                    $constraints = [
                        "pid = " . $item->getPid(),
                        "sys_language_uid = " . $defaultLanguageId,
                    ];

                    // if record has column for exclude deleted
                    if (isset($GLOBALS['TCA'][$table]['ctrl']['delete'])) {
                        $constraints[] = $GLOBALS['TCA'][$table]['ctrl']['delete'] . ' = 0';
                    }

                    if ($table === 'tt_content') {
                        $translationResult->merge($this->translateGridElements($translator, $constraints, $item, $changedFields));
                        $translationResult->merge($this->translateRegularContent($translator, $constraints, $item, $changedFields));
                    } else {
                        $records = Records::getRecords($table, 'uid', $constraints);
                        foreach ($records as $uid) {
                            $translationResult->merge(
                                $translator->translateWithResult($table, $uid, null, (string)$item->getSysLanguageUid(), $item->getMode(), $changedFields)
                            );
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            return $this->fail(
                $item,
                'Translation failed: {error}',
                ['error' => $e->getMessage()]
            );
        }

        if ($translationResult->hasErrors()) {
            return $this->fail(
                $item,
                'Translation completed with errors: {errors}',
                ['errors' => $translationResult->getErrorSummary()]
            );
        }

        if (!$translationResult->hasTranslations()) {
            $message = 'No fields were translated for target language {targetLanguage}.';
            $messageData = ['targetLanguage' => $item->getSysLanguageUid()];
            if ($translationResult->getSkippedReasonSummary() !== '') {
                $message .= ' {reasons}';
                $messageData['reasons'] = $translationResult->getSkippedReasonSummary();
            }

            if ($translationResult->hasWarnings()) {
                return $this->completeWithWarning($item, $message, $messageData);
            }

            return $this->completeWithNotice($item, $message, $messageData);
        }

        $item->setError('');
        return true;
    }

    /**
     * Translates Grid-Elements and their child elements
     *
     * @param Translator $translator
     * @param array $constraints
     * @param BatchItem $item
     * @return TranslationResult
     */
    private function translateGridElements(Translator $translator, array $constraints, BatchItem $item, ?array $changedFields = null): TranslationResult
    {
        $translationResult = new TranslationResult();
        if (!ExtensionManagementUtility::isLoaded('gridelements')) {
            return $translationResult;
        }

        // Find only top-level containers first
        $topLevelContainerConstraints = array_merge($constraints, [
            "CType = 'gridelements_pi1'",
            "tx_gridelements_container = 0"
        ]);
        $topLevelContainers = Records::getRecords('tt_content', 'uid', $topLevelContainerConstraints);

        foreach ($topLevelContainers as $containerUid) {
            // Translate container and its children recursively
            $translationResult->merge(
                $this->translateContainerAndChildren($translator, $constraints, $containerUid, $item, $changedFields)
            );
        }

        return $translationResult;
    }

    /**
     * Recursively translates a container and all its children
     *
     * @param Translator $translator
     * @param array $constraints
     * @param int $containerUid
     * @param BatchItem $item
     * @return TranslationResult
     */
    private function translateContainerAndChildren(Translator $translator, array $constraints, int $containerUid, BatchItem $item, ?array $changedFields = null): TranslationResult
    {
        $translationResult = new TranslationResult();
        // First translate the container itself
        $translationResult->merge(
            $translator->translateWithResult('tt_content', $containerUid, null, (string)$item->getSysLanguageUid(), $item->getMode(), $changedFields)
        );

        // Get all direct children
        $childConstraints = array_merge($constraints, [
            "tx_gridelements_container = " . $containerUid
        ]);
        $childElements = Records::getRecords('tt_content', 'uid', $childConstraints);

        foreach ($childElements as $childUid) {
            $record = Records::getRecord('tt_content', $childUid);

            if ($record === null) {
                continue;
            }

            if ($record['CType'] === 'gridelements_pi1') {
                // If it's a container, translate it and its children recursively
                $translationResult->merge(
                    $this->translateContainerAndChildren($translator, $constraints, $childUid, $item, $changedFields)
                );
            } else {
                // If it's a regular content element, translate it
                $translationResult->merge(
                    $translator->translateWithResult('tt_content', $childUid, null, (string)$item->getSysLanguageUid(), $item->getMode(), $changedFields)
                );
            }
        }

        return $translationResult;
    }

    /**
     * Translates regular content elements (non-Grid-Elements)
     *
     * @param Translator $translator
     * @param array $constraints
     * @param BatchItem $item
     * @return TranslationResult
     */
    private function translateRegularContent(Translator $translator, array $constraints, BatchItem $item, ?array $changedFields = null): TranslationResult
    {
        $translationResult = new TranslationResult();
        $records = Records::getRecords('tt_content', 'uid', $constraints);

        foreach ($records as $uid) {
            $record = Records::getRecord('tt_content', $uid);

            if ($record === null) {
                continue;
            }

            // Skip if it's a Grid-Container or child element
            if ($this->isGridElementOrChild($record)) {
                continue;
            }

            $translationResult->merge(
                $translator->translateWithResult('tt_content', $uid, null, (string)$item->getSysLanguageUid(), $item->getMode(), $changedFields)
            );
        }

        return $translationResult;
    }

    private function fail(BatchItem $item, string $message, array $messageData = []): bool
    {
        $interpolatedMessage = LogUtility::interpolate($message, $messageData);
        LogUtility::log($this->logger, $message, $messageData, LogUtility::MESSAGE_ERROR);
        $item->setError($interpolatedMessage);

        return false;
    }

    private function completeWithWarning(BatchItem $item, string $message, array $messageData = []): bool
    {
        $this->lastWarning = LogUtility::interpolate($message, $messageData);
        LogUtility::log($this->logger, $message, $messageData, LogUtility::MESSAGE_WARNING);
        $item->setError('');

        return true;
    }

    private function completeWithNotice(BatchItem $item, string $message, array $messageData = []): bool
    {
        $this->lastNotice = LogUtility::interpolate($message, $messageData);
        LogUtility::log($this->logger, $message, $messageData, LogUtility::MESSAGE_NOTICE);
        $item->setError('');

        return true;
    }

    private function findTargetLanguageConfiguration(array $languages, int $targetLanguageUid): ?array
    {
        foreach ($languages as $language) {
            if ((int)($language['languageId'] ?? -1) === $targetLanguageUid) {
                return $language;
            }
        }

        return null;
    }

    /**
     * Checks if a record is a Grid-Element or child element
     *
     * @param array $record
     * @return bool
     */
    private function isGridElementOrChild(array $record): bool
    {
        if (!ExtensionManagementUtility::isLoaded('gridelements')) {
            return false;
        }
        return $record['CType'] === 'gridelements_pi1' || ($record && $record['tx_gridelements_container'] > 0);
    }
}
