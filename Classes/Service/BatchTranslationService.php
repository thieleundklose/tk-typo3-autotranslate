<?php
namespace ThieleUndKlose\Autotranslate\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BatchTranslationService implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    /**
     * Translate the given item.
     * @param BatchItem $item
     * @return bool
     */
    public function translate(BatchItem $item): bool
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $siteConfiguration = $siteFinder->getSiteByPageId($item->getPid());
        } catch (\Exception $e) {
            $message = sprintf('No site configuration found for pid %s.', $item->getPid());

            LogUtility::log($this->logger, $message, [], LogUtility::MESSAGE_ERROR);
            $item->setError(LogUtility::interpolate($message, []));

            return false;
        }

        $defaultLanguage = TranslationHelper::defaultLanguageFromSiteConfiguration($siteConfiguration);
        $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());

        // check if target language is in pissible translation languages
        if (!isset($languages[$item->getSysLanguageUid()])) {
            $message = 'Target language ({targetLanguage}) not in site languages ({siteLanguages}).';
            $messageData = [
                'targetLanguage' => $item->getSysLanguageUid(),
                'siteLanguages' => implode(',', array_keys($languages)),
            ];

            LogUtility::log($this->logger, $message, $messageData, LogUtility::MESSAGE_ERROR);
            $item->setError(LogUtility::interpolate($message, $messageData));

            return false;
        }

        // check if page exists
        $pageRecord = Records::getRecord('pages', $item->getPid());
        if ($pageRecord === null) {
            LogUtility::log($this->logger, 'No page found ({pid}).', ['pid' => $item->getPid()], LogUtility::MESSAGE_WARNING);
            return false;
        }

        // init translation service
        $translator = GeneralUtility::makeInstance(Translator::class, $item->getPid());
        $tablesToTranslate = TranslationHelper::tablesToTranslate();
        foreach ($tablesToTranslate as $table) {

            if ($table === 'pages') {
                // translate page
                $translator->translate($table, $item->getPid(), null, (string)$item->getSysLanguageUid(), $item->getMode());
            } else {
                $constraints = [
                    "pid = " . $item->getPid(),
                    "sys_language_uid = " . $defaultLanguage->getLanguageId(),
                ];

                // if record has column for exclude deleted
                if (isset($GLOBALS['TCA'][$table]['ctrl']['delete'])) {
                    $constraints[] = $GLOBALS['TCA'][$table]['ctrl']['delete'] . ' = 0';
                }

                if ($table === 'tt_content') {
                    $this->translateGridElements($translator, $constraints, $item);
                    $this->translateRegularContent($translator, $constraints, $item);
                } else {
                    $records = Records::getRecords($table, 'uid', $constraints);
                    foreach ($records as $uid) {
                        $translator->translate($table, $uid, null, (string)$item->getSysLanguageUid(), $item->getMode());
                    }
                }
            }
        }
        return true;
    }

    /**
     * Translates Grid-Elements and their child elements
     *
     * @param Translator $translator
     * @param array $constraints
     * @param BatchItem $item
     * @return void
     */
    private function translateGridElements(Translator $translator, array $constraints, BatchItem $item): void
    {
        // Find only top-level containers first
        $topLevelContainerConstraints = array_merge($constraints, [
            "CType = 'gridelements_pi1'",
            "tx_gridelements_container = 0"
        ]);
        $topLevelContainers = Records::getRecords('tt_content', 'uid', $topLevelContainerConstraints);

        foreach ($topLevelContainers as $containerUid) {
            // Translate container and its children recursively
            $this->translateContainerAndChildren($translator, $constraints, $containerUid, $item);
        }
    }

    /**
     * Recursively translates a container and all its children
     *
     * @param Translator $translator
     * @param array $constraints
     * @param int $containerUid
     * @param BatchItem $item
     * @return void
     */
    private function translateContainerAndChildren(Translator $translator, array $constraints, int $containerUid, BatchItem $item): void
    {
        // First translate the container itself
        $translator->translate('tt_content', $containerUid, null, (string)$item->getSysLanguageUid(), $item->getMode());

        // Get all direct children
        $childConstraints = array_merge($constraints, [
            "tx_gridelements_container = " . $containerUid
        ]);
        $childElements = Records::getRecords('tt_content', 'uid', $childConstraints);

        foreach ($childElements as $childUid) {
            $record = Records::getRecord('tt_content', $childUid);

            if ($record['CType'] === 'gridelements_pi1') {
                // If it's a container, translate it and its children recursively
                $this->translateContainerAndChildren($translator, $constraints, $childUid, $item);
            } else {
                // If it's a regular content element, translate it
                $translator->translate('tt_content', $childUid, null, (string)$item->getSysLanguageUid(), $item->getMode());
            }
        }
    }

    /**
     * Translates regular content elements (non-Grid-Elements)
     *
     * @param Translator $translator
     * @param array $constraints
     * @param BatchItem $item
     * @return void
     */
    private function translateRegularContent(Translator $translator, array $constraints, BatchItem $item): void
    {
        $records = Records::getRecords('tt_content', 'uid', $constraints);

        foreach ($records as $uid) {
            $record = Records::getRecord('tt_content', $uid);

            // Skip if it's a Grid-Container or child element
            if ($this->isGridElementOrChild($record)) {
                continue;
            }

            $translator->translate('tt_content', $uid, null, (string)$item->getSysLanguageUid(), $item->getMode());
        }
    }

    /**
     * Checks if a record is a Grid-Element or child element
     *
     * @param array $record
     * @return bool
     */
    private function isGridElementOrChild(array $record): bool
    {
        return $record['CType'] === 'gridelements_pi1' || $record['tx_gridelements_container'] > 0;
    }
}
