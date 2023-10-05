<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$extKey = 'autotranslate';
$llPath = 'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang_db.xlf:';

$tempColumns = [
    'autotranslate_exclude' => [
        'exclude' => 1,
        'label' => $llPath . 'autotranslate_exclude',
        'displayCond' => 'FIELD:' . $GLOBALS['TCA']['pages']['ctrl']['translationSource'] . ':<=:0',
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
        'displayCond' => 'FIELD:' . $GLOBALS['TCA']['pages']['ctrl']['translationSource'] . ':<=:0',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'itemsProcFunc' => 'ThieleUndKlose\Autotranslate\UserFunction\FormEngine\AutotranslateLanguagesItems->itemsProcFunc',
        ],
    ],
     'autotranslate_last' => [
        'exclude' => 1,
        'label' => $llPath . 'autotranslate_last',
        'displayCond' => 'FIELD:' . $GLOBALS['TCA']['pages']['ctrl']['translationSource'] . ':>:0',
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
ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns, 1);

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'autotranslate_exclude,autotranslate_languages,autotranslate_last',
    '',
    'after:l18n_cfg',
);
