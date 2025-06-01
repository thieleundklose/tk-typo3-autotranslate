<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;

final class DisableLanguageSyncListener
{
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites = $siteFinder->getAllSites();

        foreach ($sites as $site) {
            foreach (TranslationHelper::additionalTables() as $table) {
                $settings = TranslationHelper::translationSettingsDefaults(
                    $site->getConfiguration(),
                    $table
                );
                $textFields = GeneralUtility::trimExplode(
                    ',',
                    $settings['autotranslateTextfields'] ?? '',
                    true
                );
                foreach ($textFields as $field) {
                    if (
                        $GLOBALS['TCA'][$table]['columns'][$field]['config']['behaviour']['allowLanguageSynchronization'] ?? null === true
                    ) {
                        $GLOBALS['TCA'][$table]['columns'][$field]['config']['behaviour']['allowLanguageSynchronization'] = false;
                    }
                }
            }
        }
    }
}
