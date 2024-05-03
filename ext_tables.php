<?php
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die();

$versionInformation = GeneralUtility::makeInstance(Typo3Version::class);

if ($versionInformation->getMajorVersion() < 12) {
    ExtensionManagementUtility::allowTableOnStandardPages(
        'tx_autotranslate_batch_item',
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'autotranslate',
        'web',
        'm1',
        '',
        [
            \ThieleUndKlose\Autotranslate\Controller\BatchTranslationLegacyController::class =>
                'batchTranslationLegacy, showLogsLegacy, create'
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:autotranslate/Resources/Public/Icons/Extension.png',
            'labels' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf',
        ]
    );
}
