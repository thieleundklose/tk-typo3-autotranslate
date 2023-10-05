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

namespace ThieleUndKlose\Autotranslate\EventListener;

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Configuration\Event\ModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\Site\SiteFinder;

class PageTsConfigListener
{
    /**
     * Set tca defaults by site config.
     *
     * @param ModifyLoadedPageTsConfigEvent $event
     * @return void
     */
    public function onModifyLoadedPageTsConfig(ModifyLoadedPageTsConfigEvent $event): void
    {
        $siteFinder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        foreach ($sites as $site) {
            foreach (TranslationHelper::translateableTables() as $table) {
                $settings = TranslationHelper::translationSettingsDefaults($site->getConfiguration(), $table);
                if (!$settings) {
                    continue;
                }
                $event->addTsConfig('
                    [traverse(page, "uid") == ' . $site->getRootPageId() . ' || ' . $site->getRootPageId() . '  in tree.rootLineParentIds]
                        TCAdefaults.' . $table . '.autotranslate_languages = ' . $settings['autotranslateLanguages'] . '
                    [end]
                ');
            }
        }
    }
}
