<?php

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

foreach (TranslationHelper::additionalTables() as $table) {
    $extKey = 'autotranslate';
    $llPath = 'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang_db.xlf:';
    $tempColumns = [
        'autotranslate_exclude' => [
            'exclude' => 1,
            'label' => $llPath . 'autotranslate_exclude',
            'displayCond' => 'FIELD:' . $GLOBALS['TCA']['tt_content']['ctrl']['transOrigPointerField'] . ':<=:0',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
                'items' => [
                    [
                        0 => '',
                        1 => '',
                    ]
                ],
            ],
        ],
        'autotranslate_languages' => [
            'exclude' => 1,
            'label' => $llPath . 'autotranslate_languages',
            'displayCond' => 'FIELD:' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . ':<=:0',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'itemsProcFunc' => 'ThieleUndKlose\Autotranslate\UserFunction\FormEngine\AutotranslateLanguagesItems->itemsProcFunc',
            ],
        ],
        'autotranslate_last' => [
            'exclude' => 1,
            'label' => $llPath . 'autotranslate_last',
            'displayCond' => 'FIELD:' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . ':>:0',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 13,
                'readOnly' => true,
                'eval' => 'datetime,int',
                'default' => 0
            ],
        ],
    ];
    ExtensionManagementUtility::addTCAcolumns($table, $tempColumns, 1);

    ExtensionManagementUtility::addToAllTCAtypes(
        $table,
        'autotranslate_exclude,autotranslate_languages,autotranslate_last',
        '',
        'after:' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']
    );


    // remove allowLanguageSynchronization from the fields you want to translate. Otherwise, translations would be reset. (tt_address)
    $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
    $sites = $siteFinder->getAllSites();
    foreach($sites as $site) {
        $settings = TranslationHelper::translationSettingsDefaults($site->getConfiguration(), $table);
        $textFields = GeneralUtility::trimExplode(',', $settings['autotranslateTextfields'] ?? '', true);
        foreach ($textFields as $field) {
            if ($GLOBALS['TCA'][$table]['columns'][$field]['config']['behaviour']['allowLanguageSynchronization'] ?? null === true) {
                $GLOBALS['TCA'][$table]['columns'][$field]['config']['behaviour']['allowLanguageSynchronization'] = false;
            }
        }
    }
}
