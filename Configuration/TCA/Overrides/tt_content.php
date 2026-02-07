<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$table = 'tt_content';
$extKey = 'autotranslate';
$llPath = 'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang_db.xlf:';
$languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? 'sys_language_uid';
$tempColumns = [
    'autotranslate_exclude' => [
        'exclude' => true,
        'label' => $llPath . 'autotranslate_exclude',
        'displayCond' => 'FIELD:' . $languageField . ':<=:0',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
            'items' => [
                [
                    'label' => '',
                ]
            ],
        ],
    ],
    'autotranslate_languages' => [
        'exclude' => true,
        'label' => $llPath . 'autotranslate_languages',
        'displayCond' => 'FIELD:' . $languageField . ':<=:0',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'itemsProcFunc' => 'ThieleUndKlose\Autotranslate\UserFunction\FormEngine\AutotranslateLanguagesItems->itemsProcFunc',
        ],
    ],
    'autotranslate_last' => [
        'exclude' => true,
        'label' => $llPath . 'autotranslate_last',
        'displayCond' => 'FIELD:' . $languageField . ':>:0',
        'config' => [
            'type' => 'datetime',
            'size' => 13,
            'readOnly' => true,
            'default' => 0
        ],
    ],
];
ExtensionManagementUtility::addTCAcolumns($table, $tempColumns);

ExtensionManagementUtility::addFieldsToPalette(
    $table,
    'language',
    '--linebreak--,autotranslate_exclude,--linebreak--,autotranslate_languages,--linebreak--,autotranslate_last',
    'after:' . $languageField
);
