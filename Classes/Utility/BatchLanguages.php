<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Site\SiteFinder;

final class BatchLanguages
{
    /**
     * Populates the languages of the current page for the tca select field items
     */
    public function populateLanguagesFromSiteConfiguration(array &$parameters): void
    {
        $pageId = (int)$parameters['row']['pid'];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $parameters['items'] = [];
        try {
            $site = $siteFinder->getSiteByPageId($pageId);
            $possibleTranslationLanguages = TranslationHelper::possibleTranslationLanguages($site->getLanguages());
            foreach ($possibleTranslationLanguages as $language) {
                $parameters['items'][] = [
                    'label' => $language->getTitle(),
                    // @extensionScannerIgnoreLine
                    'value' => $language->getLanguageId(),
                ];
            }
        } catch (SiteNotFoundException) {
            // Site not found, no languages to add
        }
    }
}
