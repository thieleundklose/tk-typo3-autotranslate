<?php

use ThieleUndKlose\Autotranslate\Controller\BatchTranslationController;
use TYPO3\CMS\Core\Information\Typo3Version;

$iconIdentifier = (new Typo3Version())->getMajorVersion() < 14
    ? 'autotranslate-extension'
    : 'autotranslate-extension-v14';

return [
    'web_autotranslate' => [
        'parent' => 'web',
        'position' => [],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/batchtranslation',
        'labels' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Autotranslate',
        'iconIdentifier' => $iconIdentifier,
        'controllerActions' => [
            BatchTranslationController::class => [
                'default', 'showLogs', 'create'
            ]
        ],
    ],
];
