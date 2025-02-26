<?php

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

foreach (TranslationHelper::additionalTables() as $table) {
    // check to set translationSource' => 'l10n_source', if not set!! otherwise there would be an error on write back to missing db field
    $extKey = 'autotranslate';
    $llPath = 'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang_db.xlf:';
    $tempColumns = [
        'autotranslate_exclude' => [
            'exclude' => 1,
            'label' => $llPath . 'autotranslate_exclude',
            'displayCond' => 'FIELD:' . $GLOBALS['TCA']['tt_content']['ctrl']['translationSource'] . ':<=:0',
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
            'displayCond' => 'FIELD:' . $GLOBALS['TCA'][$table]['ctrl']['translationSource'] . ':<=:0',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'itemsProcFunc' => 'ThieleUndKlose\Autotranslate\UserFunction\FormEngine\AutotranslateLanguagesItems->itemsProcFunc',
            ],
        ],
        'autotranslate_last' => [
            'exclude' => 1,
            'label' => $llPath . 'autotranslate_last',
            'displayCond' => 'FIELD:' . $GLOBALS['TCA'][$table]['ctrl']['translationSource'] . ':>:0',
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
}
