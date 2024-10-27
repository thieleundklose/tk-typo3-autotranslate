<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Site\SiteFinder;

class BatchLanguages
{
    /**
     * Populates the languages of the current page for the tca select field items
     *
     * @param array &$parameters
     * @param mixed $parentObject
     *
     * @return void
     */
    public function populateLanguagesFromSiteConfiguration(array &$parameters, $parentObject)
    {
        $pageId = (int)$parameters['row']['pid'];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $parameters['items'] = [];
        try {
            $site = $siteFinder->getSiteByPageId($pageId);
            $siteConfiguration = $site->getConfiguration();
            $possibleTranslationLanguages = TranslationHelper::possibleTranslationLanguages($siteConfiguration['languages'] ?? []);
            foreach ($possibleTranslationLanguages as $language) {
                $parameters['items'][] = [$language['title'], $language['languageId']];
            }
        } catch (SiteNotFoundException $e) {
        }
    }
}
