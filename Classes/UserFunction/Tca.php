<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\UserFunction;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Tca
{
    /**
     * Get label for batches from language and translation date
     */
    public function batchLabel(array &$parameters): void
    {
        if (!isset($parameters['row']['uid'])) {
            $parameters['title'] = 'item deleted from backend module';
            return;
        }

        $record = BackendUtility::getRecord($parameters['table'], $parameters['row']['uid']);
        if (!$record) {
            return;
        }

        $languageId = $record['sys_language_uid'];
        $translationTimestamp = $record['translate'];
        $translationDate = date('d-m-Y H:i', $translationTimestamp);

        if (!isset($parameters['row']['pid'])) {
            $parameters['title'] = (string)$parameters['row']['uid'];
            return;
        }

        $pageId = (int)$parameters['row']['pid'];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $parameters['title'] = $translationDate;

        try {
            $site = $siteFinder->getSiteByPageId($pageId);
            foreach ($site->getAllLanguages() as $siteLanguage) {
                // @extensionScannerIgnoreLine
                if ($siteLanguage->getLanguageId() === $languageId) {
                    $parameters['title'] = $siteLanguage->getTitle() . ' - ' . $parameters['title'];
                    break;
                }
            }
        } catch (SiteNotFoundException) {
            // Site not found, keep default title
        }
    }
}
