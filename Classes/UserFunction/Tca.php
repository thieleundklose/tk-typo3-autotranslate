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

namespace ThieleUndKlose\Autotranslate\UserFunction;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Site\SiteFinder;

class Tca {

    /**
     * Get label for batches from language and translation date
     *
     * @param array &$parameters
     * @return void
     */
    public function batchLabel(&$parameters)
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
            $parameters['title'] = $parameters['row']['uid'];
            return;
        }

        $pageId = (int)$parameters['row']['pid'];
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageId);

        $parameters['title'] = $translationDate;

        foreach ($site->getAllLanguages() as $siteLanguage) {
            if ($siteLanguage->getLanguageId() === $languageId) {
                $parameters['title'] = $siteLanguage->getTitle() . ' - ' . $parameters['title'];
                break;
            }
        }

    }
}
