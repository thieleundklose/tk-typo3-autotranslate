<?php

use ThieleUndKlose\Autotranslate\Utility\DeeplApiHelper;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$siteConfiguration = isset($_REQUEST['site']) ? GeneralUtility::makeInstance(SiteFinder::class)->getSiteByIdentifier($_REQUEST['site'])->getConfiguration(): null;

$palettes = [];

$deeplAuthKeyDescription = ['Enter your generated API key or generate a new one at https://www.deepl.com/account/'];

if (!empty($siteConfiguration['deeplAuthKey'])) {
    $source = null;
    $apiKey = $siteConfiguration['deeplAuthKey'];
} else {
    list('key' => $apiKey, 'source' => $source) = TranslationHelper::apiKey();
}

$deeplApiKeyDetails = DeeplApiHelper::checkApiKey($apiKey);
if ($source) {
    $maskedApiKey = str_repeat('*', 20) . substr($apiKey, 20);
    $deeplAuthKeyDescription[] = 'Defined: ' . $source . ' (' . $maskedApiKey . ')';
}
if ($deeplApiKeyDetails['usage']) {
    $usage = $deeplApiKeyDetails['usage'];
    $usage = str_replace(PHP_EOL, ' ', $usage);
    $usage = str_replace('Characters: ', '', $usage);
    $deeplAuthKeyDescription[] = $usage. ' Characters';
}
if ($deeplApiKeyDetails['error']) {
    $deeplAuthKeyDescription[] = $deeplApiKeyDetails['error'];
}

// add deepl auth key
$GLOBALS['SiteConfiguration']['site']['columns']['deeplAuthKey'] = [
    'label' => 'DeepL API key (overwrites the one from the extension settings to use a special key for this page configuration)',
    'description' => implode(PHP_EOL, $deeplAuthKeyDescription),
    'config' => [
        'type' => 'input',
        'size' => 50,
        'eval' => 'trim'
    ],
];
$palettes['deeplAuthKey'] = ['showitem' => 'deeplAuthKey'];

$GLOBALS['SiteConfiguration']['site']['columns']['autotranslateUseDeeplGlossary'] = [
    'label' => 'Enable the use of the DeepL Translate glossary',
    'description' => 'In TYPO3 >= v12 you can use DeepL Glossaries from https://extensions.typo3.org/extension/deepltranslate_glossary',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 0,
        'readOnly' => !ExtensionManagementUtility::isLoaded('deepltranslate_glossary'),
        'items' => [
            [
                0 => '',
                1 => ''
            ]
        ]
    ],
];
$palettes['deeplGlossary'] = ['showitem' => 'autotranslateUseDeeplGlossary'];

// add translateable tables
$tablesToTranslate = TranslationHelper::tablesToTranslate();

$possibleTranslationLanguages = array_map(function ($v) {
    return $v['languageId'] . ' => ' . ( isset($v['title']) ? $v['title'] : 'no title defined' );
}, TranslationHelper::possibleTranslationLanguages($siteConfiguration['languages'] ?? []));
$possibleTranslationLanguagesDescription = !empty($possibleTranslationLanguages) ? 'Comma seperated list of language uids. (' . implode(', ', $possibleTranslationLanguages) . ')' : 'First define Languages in Site Configuration.';

foreach ($tablesToTranslate as $table) {
    $tableUpperCamelCase = GeneralUtility::underscoredToUpperCamelCase($table);

    $additionalFields = [];

    $fieldname = TranslationHelper::configurationFieldname($table,'enabled');
    $GLOBALS['SiteConfiguration']['site']['columns'][$fieldname] = [
        'label' => 'Enable Autotranslation for '.$tableUpperCamelCase,
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
            'items' => [
                [
                    0 => '',
                    1 => ''
                ]
            ]
        ],
    ];
    $additionalFields[] = $fieldname;

    $fieldname = TranslationHelper::configurationFieldname($table, 'languages');
    $GLOBALS['SiteConfiguration']['site']['columns'][$fieldname] = [
        'label' => 'Translate into the following languages by default',
        'description' => $possibleTranslationLanguagesDescription,
        'config' => [
            'type' => 'input',
            'size' => 20,
            'eval' => 'trim'
        ],
    ];
    $additionalFields[] = $fieldname;

    $fieldname = TranslationHelper::configurationFieldname($table,'textfields');
    $fieldsUnusedTextField = TranslationHelper::unusedTranslateableColumns($table, $siteConfiguration[$fieldname] ?? '', TranslationHelper::COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD);
    $descriptionAppendix = !empty($fieldsUnusedTextField) ? PHP_EOL . ' Unused: ' . implode(', ', $fieldsUnusedTextField) : '';
    $GLOBALS['SiteConfiguration']['site']['columns'][$fieldname] = [
        'label' => 'Text fields',
        'description' => 'Comma seperated list of columns.' . $descriptionAppendix,
        'config' => [
            'type' => 'text',
            'cols' => 80,
            'rows' => 5,
            'eval' => 'trim'
        ],
    ];
    $additionalFields[] = $fieldname;


    $fieldname = TranslationHelper::configurationFieldname($table,'fileReferences');
    $fieldsUnusedFileReference = TranslationHelper::unusedTranslateableColumns($table, $siteConfiguration[$fieldname] ?? '', TranslationHelper::COLUMNS_TRANSLATEABLE_GROUP_FILEREFERENCE);
    $descriptionAppendix = !empty($fieldsUnusedFileReference) ? PHP_EOL . ' Unused: ' . implode(', ', $fieldsUnusedFileReference) : '';
    $GLOBALS['SiteConfiguration']['site']['columns'][$fieldname] = [
        'label' => 'File references',
        'description' => 'Comma seperated list of columns.' . $descriptionAppendix,
        'config' => [
            'type' => 'text',
            'cols' => 80,
            'rows' => 5,
            'eval' => 'trim'
        ],
    ];
    $additionalFields[] = $fieldname;

    $palettes['autotranslate' . $tableUpperCamelCase] = ['showitem' => implode(', --linebreak--, ', $additionalFields)];
}

// add static table sys_file_reference
$table = 'sys_file_reference';
$tableUpperCamelCase = GeneralUtility::underscoredToUpperCamelCase($table);
$fieldname = TranslationHelper::configurationFieldname($table,'textfields');
$fieldsUnusedTextField = TranslationHelper::unusedTranslateableColumns($table, $siteConfiguration[$fieldname] ?? '', TranslationHelper::COLUMNS_TRANSLATEABLE_GROUP_TEXTFIELD);
$descriptionAppendix = !empty($fieldsUnusedTextField) ? PHP_EOL . ' Unused: ' . implode(', ', $fieldsUnusedTextField) : '';
$GLOBALS['SiteConfiguration']['site']['columns'][$fieldname] = [
    'label' => 'Autotranslation textfields for '.$tableUpperCamelCase,
    'description' => 'Comma seperated list of columns.' . $descriptionAppendix,
    'config' => [
        'type' => 'text',
        'cols' => 80,
        'rows' => 5,
        'eval' => 'trim'
    ],
];
$palettes['autotranslate' . $tableUpperCamelCase] = ['showitem' => $fieldname];


$GLOBALS['SiteConfiguration']['site']['palettes'] = array_merge($GLOBALS['SiteConfiguration']['site']['palettes'], $palettes);
$showItem = ',--palette--;;' . implode(',--palette--;;', array_keys($palettes));
$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] .= ', --div--;Autotranslate' . $showItem;

// settings for deepl translation source
$deeplSourceLangItems = [];

if (!$deeplApiKeyDetails['isValid']) {
    $deeplSourceLangItems[] = ['Please define valid DeepL api key first', ''];
} else {
    $deeplSourceLangItems[] = ['Please Choose'];
    $deeplSourceLangItems = array_merge(
        $deeplSourceLangItems,
        DeepLApiHelper::getCachedLanguages($apiKey, 'source')
    );
}

$GLOBALS['SiteConfiguration']['site_language']['columns']['deeplSourceLang'] = [
    'label' => 'Source language (Iso code)',
    'displayCond' => 'FIELD:languageId:=:0',
    'description' => 'Select the source language for DeepL translations. The automatic language detection may be inaccurate for individual words.',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => $deeplSourceLangItems,
        'minitems' => 0,
        'maxitems' => 1,
        'size' => 1,
        'readOnly' => !$deeplApiKeyDetails['isValid'] || count($deeplSourceLangItems) === 1,
    ],
];

// settings for deepl translation target
$deeplTargetLangItems = [];
if (!$deeplApiKeyDetails['isValid']) {
    $deeplTargetLangItems[] = ['Please define valid DeepL api key first', ''];
} else {
    $deeplTargetLangItems[] = ['Please Choose'];
    $deeplTargetLangItems = array_merge(
        $deeplTargetLangItems,
        DeepLApiHelper::getCachedLanguages($apiKey, 'target')
    );
}

$GLOBALS['SiteConfiguration']['site_language']['columns']['deeplTargetLang'] = [
    'label' => 'Target language (Iso code)',
    'displayCond' => 'FIELD:languageId:>:0',
    'description' => 'Select the target language into which DeepL should translate.',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => $deeplTargetLangItems,
        'minitems' => 0,
        'maxitems' => 1,
        'size' => 1,
        'readOnly' => !$deeplApiKeyDetails['isValid'] || count($deeplTargetLangItems) === 1,
    ],
];

$GLOBALS['SiteConfiguration']['site_language']['palettes']['autotranslate'] = [
    'showitem' => 'deeplSourceLang,deeplTargetLang',
];

$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] = str_replace(
    '--palette--;;default,',
    '--palette--;;default, --palette--;LLL:EXT:autotranslate/Resources/Private/Language/locallang.xlf:site_configuration.deepl.title;autotranslate,',
    $GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem']
);
