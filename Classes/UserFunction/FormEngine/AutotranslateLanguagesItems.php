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

namespace ThieleUndKlose\Autotranslate\UserFunction\FormEngine;

use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use ThieleUndKlose\Autotranslate\Utility\Records;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;

class AutotranslateLanguagesItems {

    /**
     * Add possible languages from site configuration respecting current tree position to backend tca form.
     *
     * @param array $config
     * @param $pObj
     * @return void
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function itemsProcFunc(array &$config, &$pObj)
    {
        $table = $config['table'];
        $row = $config['row'];

        // cancel filling for translated records because it is not needed
        if (!is_array($row['sys_language_uid']) && !empty($row['sys_language_uid'])) {
            return;
        }

        $sitePid = (int)$row['pid'];
        // pid < 0 respects the record (content or page) to insert after
        // pid > 0 and numeric represent the page uid to insert on
        if (is_numeric($row['pid']) && $row['pid']<0) {
            $sitePid = (int)Records::getRecord($table, abs($row['pid']), 'pid');
        }

        // set site pid on editing pages
        if ($row['pid'] == 0 && $table == 'pages' && is_numeric($row['uid'])) {
            $sitePid = (int)$row['uid'];
        }

        // step out on create pages on root level
        if ($sitePid === 0) {
            return;
        }

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $siteConfiguration = $siteFinder->getSiteByPageId($sitePid);
            $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());
            foreach ($languages as $language) {
                array_push($config['items'], array($language->getTitle(), $language->getLanguageId()));
            }
        } catch (SiteNotFoundException $e) {

        }
    }
}
