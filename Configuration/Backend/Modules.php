<?php 
// used in TYPO3 v12

use ThieleUndKlose\Autotranslate\Controller\BatchTranslationController;

return [
    'web_autotranslate' => [
        'parent' => 'web',
        'position' => [],
        'access' => 'user,group',
        'workspaces' => 'live',
        'path' => '/module/batchtranslation',
        'labels' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Autotranslate',
        'icon' => 'EXT:autotranslate/Resources/Public/Icons/Extension.png',
        'controllerActions' => [
            BatchTranslationController::class => [
                'batchTranslation', 'showLogs', 'setLevels'
            ]
        ],
    ],
];
