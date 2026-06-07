<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Autotranslate content with DeepL',
    'description' => 'This extension provides automatic translation of pages and content elements via DeepL API.',
    'category' => 'be',
    'author' => 'Mike Zimmer',
    'author_company' => 'Thiele & Klose GmbH',
    'author_email' => 'typo3@thieleundklose.de',
    'state' => 'stable',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.9.99',
            'php' => '8.1.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'news' => '11.0.0-14.99.99',
            'deepltranslate_glossary' => '5.0.0-5.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'ThieleUndKlose\\Autotranslate\\' => 'Classes/',
            'DeepL\\' => 'Resources/Vendor/Deeplcom/DeeplPhp/src/',
            'Http\\Discovery\\' => 'Resources/Vendor/php-http/discovery/src/',
            'Http\\Message\\' => 'Resources/Vendor/php-http/multipart-stream-builder/src/',
        ],
	],
];
