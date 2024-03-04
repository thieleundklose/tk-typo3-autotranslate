<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

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
        $site = $siteFinder->getSiteByPageId($pageId);
        $languages = $site->getLanguages();

        $parameters['items'] = [];

        foreach ($languages as $language) {
            $parameters['items'][] = [$language->getTitle(), $language->getLanguageId()];
        }
    }
}
