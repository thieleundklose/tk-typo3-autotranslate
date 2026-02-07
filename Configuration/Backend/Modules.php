<?php

declare(strict_types=1);

use ThieleUndKlose\Autotranslate\Controller\BatchTranslationController;

return [
    'web_autotranslate' => [
        'parent' => 'web',
        'position' => [],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/batchtranslation',
        'labels' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Autotranslate',
        'icon' => 'EXT:autotranslate/Resources/Public/Icons/Backend.png',
        'controllerActions' => [
            BatchTranslationController::class => [
                'default', 'showLogs', 'create'
            ]
        ],
    ],
];
