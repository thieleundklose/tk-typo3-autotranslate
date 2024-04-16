<?php 
// used in TYPO3 v12
return [
    'web_autotranslate' => [
        'parent' => 'web',
        'position' => [],
        'access' => 'user,group',
        'icon' => 'EXT:autotranslate/Resources/Public/Icons/Extension.png',

        'labels' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf',
        'path' => '/module/batchtranslation',
        'extensionName' => 'Autotranslate',
        'target' => [
            '_default' => \ThieleUndKlose\Autotranslate\Controller\BatchTranslationController::class . '::batchTranslationAction',
        ],
        'controllerActions' => [
            \ThieleUndKlose\Autotranslate\Controller\BatchTranslationController::class =>
                'batchTranslation',
        ],
    ],
];
