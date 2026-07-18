<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\TsConfig;

use TYPO3\CMS\Core\Configuration\Event\ModifyLoadedPageTsConfigEvent as LegacyModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\Information\Typo3Version;
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
        $typo3MajorVersion = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

        foreach ($sites as $site) {
            $rootPageId = (int)$site->getRootPageId();
            foreach (TranslationHelper::tablesToTranslate() as $table) {
                $settings = TranslationHelper::translationSettingsDefaults($site->getConfiguration(), $table);
                if (!$settings) {
                    continue;
                }
                $event->addTsConfig('
                    [' . $this->buildSiteRootCondition($rootPageId, $typo3MajorVersion) . ']
                        TCAdefaults.' . $table . '.autotranslate_languages = ' . $settings['autotranslateLanguages'] . '
                    [end]
                ');
            }
        }
    }

    private function buildSiteRootCondition(int $rootPageId, int $typo3MajorVersion): string
    {
        if ($typo3MajorVersion <= 11) {
            return 'traverse(page, "uid") == ' . $rootPageId . ' || ' . $rootPageId . ' in tree.rootLineParentIds';
        }

        return 'site("rootPageId") == ' . $rootPageId;
    }
}
