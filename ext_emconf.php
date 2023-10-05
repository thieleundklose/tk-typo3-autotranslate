<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Thiele & Klose - Autotranslate',
    'description' => 'Integration of automatic translations into the backend process when maintaining pages and content elements by editors.',
    'category' => 'plugin',
    'author' => '',
    'author_email' => '',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
            'php' => '7.3.0-7.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'news' => '10.0.0-10.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'ThieleUndKlose\\Autotranslate\\' => 'Classes/',
            'DeepL\\' => 'Resources/Private/Deeplcom/DeeplPhp/src/',
        ],
	],
];
