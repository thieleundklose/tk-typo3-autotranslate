<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Autotranslate content with DeepL',
    'description' => 'This extension provides automatic translation of pages and content elements via DeepL API.',
    'category' => 'be',
    'author' => 'Mike Zimmer',
    'author_company' => 'Thiele & Klose GmbH',
    'author_email' => 'typo3@thieleundklose.de',
    'state' => 'stable',
    'version' => '1.3.2',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-13.9.99',
            'php' => '7.4.0-8.3.99',
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
