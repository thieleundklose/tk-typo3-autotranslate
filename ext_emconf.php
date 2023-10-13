<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Thiele & Klose - Autotranslate',
    'description' => 'Integration of automatic translations into the backend process when maintaining pages and content elements by editors.',
    'category' => 'plugin',
    'author' => '',
    'author_email' => '',
    'state' => 'beta',
    'clearCacheOnLoad' => 0,
    'version' => '0.2.1',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-11.5.99',
            'php' => '7.4.0-8.1.99',
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
