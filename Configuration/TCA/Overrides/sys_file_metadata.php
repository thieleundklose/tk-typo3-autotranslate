<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$table = 'sys_file_metadata';
$extKey = 'autotranslate';
$llPath = 'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang_db.xlf:';

try {
    $defaultLanguages = (string)GeneralUtility::makeInstance(ExtensionConfiguration::class)
        ->get($extKey, 'fileMetadataDefaultLanguages');
} catch (\Exception $e) {
    $defaultLanguages = '';
}

$tempColumns = [
    'autotranslate_exclude' => [
        'exclude' => 1,
        'label' => $llPath . 'file_metadata.autotranslate_exclude',
        'displayCond' => 'FIELD:sys_language_uid:<=:0',
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
        'label' => $llPath . 'file_metadata.autotranslate_languages',
        'description' => $llPath . 'file_metadata.autotranslate_languages.description',
        'displayCond' => 'FIELD:sys_language_uid:<=:0',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'itemsProcFunc' => 'ThieleUndKlose\Autotranslate\UserFunction\FormEngine\FileMetadataLanguagesItems->itemsProcFunc',
            'default' => $defaultLanguages,
        ],
    ],
    'autotranslate_last' => [
        'exclude' => 1,
        'label' => $llPath . 'file_metadata.autotranslate_last',
        'displayCond' => 'FIELD:sys_language_uid:>:0',
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
    'after:title'
);
