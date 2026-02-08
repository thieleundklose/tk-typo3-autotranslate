<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\TsConfig;

use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\ModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Site\SiteFinder;

final class Loader
{
    public function addPageConfiguration(ModifyLoadedPageTsConfigEvent $event): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        foreach ($sites as $site) {
            foreach (TranslationHelper::tablesToTranslate() as $table) {
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
