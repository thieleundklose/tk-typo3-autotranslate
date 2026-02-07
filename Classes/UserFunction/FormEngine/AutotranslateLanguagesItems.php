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

final class AutotranslateLanguagesItems
{
    /**
     * Add possible languages from site configuration respecting current tree position to backend tca form.
     *
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function itemsProcFunc(array &$config): void
    {
        $table = $config['table'];
        $row = $config['row'];

        // cancel filling for translated records because it is not needed
        if (!is_array($row['sys_language_uid']) && !empty($row['sys_language_uid'])) {
            return;
        }

        $sitePid = (int)$row['pid'];

        // Handle cases where pid < 0 (insert after record)
        if ($sitePid < 0) {
            $sitePid = (int)Records::getRecord($table, abs($sitePid), 'pid');
        }

        // Handle cases where pid == 0 and table is 'pages' (editing pages)
        if ($sitePid === 0 && $table === 'pages' && isset($row['uid']) && is_numeric($row['uid'])) {
            $sitePid = (int)$row['uid'];
        }

        // Exit early if sitePid is still 0 (creating pages on root level)
        if ($sitePid === 0) {
            return;
        }

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $siteConfiguration = $siteFinder->getSiteByPageId($sitePid);
            $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());
            foreach ($languages as $language) {
                $config['items'][] = [
                    'label' => $language->getTitle(),
                    'value' => $language->getLanguageId(),
                ];
            }
        } catch (SiteNotFoundException) {
            // Site not found, no languages to add
        }
    }
}
