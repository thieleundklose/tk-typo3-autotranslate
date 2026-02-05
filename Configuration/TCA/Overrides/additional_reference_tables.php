<?php

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

foreach (TranslationHelper::additionalReferenceTables() as $table) {
    $extKey = 'autotranslate';
    $llPath = 'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang_db.xlf:';
    $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? 'sys_language_uid';
    $tempColumns = [
        'autotranslate_last' => [
            'exclude' => 1,
            'label' => $llPath . 'autotranslate_last',
            'displayCond' => 'FIELD:' . $languageField . ':>:0',
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
        'autotranslate_last',
        '',
        'after:' . $languageField
    );

}
