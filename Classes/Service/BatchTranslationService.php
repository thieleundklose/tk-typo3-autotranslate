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
        $siteConfiguration = $siteFinder->getSiteByPageId($item->getPid());
        $defaultLanguage = TranslationHelper::defaultLanguageFromSiteConfiguration($siteConfiguration);
        $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());

        // check if target language is in pissible translation languages
        if (!isset($languages[$item->getSysLanguageUid()])) {
            $message = 'Target language ({targetLanguages}) not in site languages ({siteLanguages}).';
            $messageData = [
                'targetLanguages' => $item->getSysLanguageUid(),
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
        $translateAbleTables = TranslationHelper::translateableTables();
        foreach ($translateAbleTables as $table) {

            if ($table === 'pages') {
                // translate page
                $translator->translate($table, $item->getPid(), null, (string)$item->getSysLanguageUid(), $item->getMode());
            } else {
                // get and translate other content placed on page
                $records = Records::getRecords($table, 'uid', [
                    "pid = " . $item->getPid(),
                    "deleted = 0",
                    "sys_language_uid = " . $defaultLanguage->getLanguageId(),
                ]);
                foreach ($records as $uid) {
                    $translator->translate($table, $uid, null, (string)$item->getSysLanguageUid(), $item->getMode());
                }
            }
        }

        return true;
    }

}
