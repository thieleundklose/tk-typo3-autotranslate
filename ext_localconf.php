<?php

declare(strict_types=1);

use ThieleUndKlose\Autotranslate\Hooks\DataHandler;
use TYPO3\CMS\Core\Log\Writer\DatabaseWriter;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;

defined('TYPO3') or die();

// DataHandler hooks for automatic translation
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['autotranslate'] = DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['autotranslate'] = DataHandler::class;

// Logging configuration
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Command']['BatchTranslation']['writerConfiguration'] = [
    LogLevel::INFO => [
        DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Utility']['LogUtility']['writerConfiguration'] = [
    LogLevel::INFO => [
        DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Utility']['Translator']['writerConfiguration'] = [
    LogLevel::INFO => [
        DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];

// xclass optional 3rd party extension to use apiKey dependent on site config
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\WebVision\Deepltranslate\Core\Configuration::class] = [
    'className' => \ThieleUndKlose\Autotranslate\Xclass\Deepltranslate\Core\Configuration::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['autotranslate'] ??= [
    'backend' => FileBackend::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['autotranslate_cache'] ??= [
    'backend' => FileBackend::class,
];
