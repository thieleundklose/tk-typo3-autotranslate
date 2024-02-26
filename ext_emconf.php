<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Thiele & Klose - Autotranslate',
    'description' => 'Integration of automatic translations into the backend process when maintaining pages and content elements by editors.',
    'category' => 'be',
    'author' => 'Mike Zimmer',
    'author_company' => 'Thiele & Klose GmbH',
    'author_email' => 'typo3@thieleundklose.de',
    'state' => 'beta',
    'version' => '0.9.3',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-12.4.99',
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
