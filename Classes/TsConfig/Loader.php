<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\TsConfig;

use TYPO3\CMS\Core\TypoScript\IncludeTree\Event\ModifyLoadedPageTsConfigEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Site\SiteFinder;

class Loader
{
    /**
     * Builds and adds page TSconfig for site-specific form defaults.
     *
     * The loader collects TSconfig blocks for all configured sites and applies
     * them once to the event. This ensures that newly created records receive
     * the configured `autotranslate_languages` defaults for the current site
     * context.
     */
    public function addPageConfiguration(ModifyLoadedPageTsConfigEvent $event): void
    {
        $this->findAndAddConfiguration($event);
    }

    protected function findAndAddConfiguration(ModifyLoadedPageTsConfigEvent $event): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();
        $tsConfigBlocks = [];

        foreach ($sites as $site) {
            $rootPageId = (int)$site->getRootPageId();
            foreach (TranslationHelper::tablesToTranslate() as $table) {
                $settings = TranslationHelper::translationSettingsDefaults($site->getConfiguration(), $table);
                if (!$settings) {
                    continue;
                }
                $tsConfigBlocks[] = sprintf(
                    '[site("rootPageId") == %d]' . PHP_EOL
                    . '    TCAdefaults.%s.autotranslate_languages = %s' . PHP_EOL
                    . '[END]',
                    $rootPageId,
                    $table,
                    $settings['autotranslateLanguages']
                );
            }
        }

        if ($tsConfigBlocks !== []) {
            $event->addTsConfig(implode(PHP_EOL . PHP_EOL, $tsConfigBlocks));
        }
    }
}
