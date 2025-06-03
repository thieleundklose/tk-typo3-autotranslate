<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\TsConfig;

use TYPO3\CMS\Core\Configuration\Event\ModifyLoadedPageTsConfigEvent as LegacyModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\ModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Site\SiteFinder;

class Loader
{
    public function addPageConfigurationCore11(LegacyModifyLoadedPageTsConfigEvent $event): void
    {
        if (class_exists(ModifyLoadedPageTsConfigEvent::class)) {
            // TYPO3 v12 calls both old and new event. Check for class existence of new event to
            // skip handling of old event in v12, but continue to work with < v12.
            // Simplify this construct when v11 compat is dropped, clean up Services.yaml.
            return;
        }
        $this->findAndAddConfiguration($event);
    }

    public function addPageConfiguration(ModifyLoadedPageTsConfigEvent $event): void
    {
        $this->findAndAddConfiguration($event);
    }

    protected function findAndAddConfiguration($event): void
    {
        // Business code
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
