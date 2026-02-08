<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use ThieleUndKlose\Autotranslate\Hooks\DataHandler;
use ThieleUndKlose\Autotranslate\Task\BatchTranslationTask;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Log\Writer\DatabaseWriter;

defined('TYPO3') or die();

// DataHandler hooks for automatic translation
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['autotranslate'] = DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['autotranslate'] = DataHandler::class;

// Logging configuration — all extension loggers write to the custom log table
$logWriterConfig = [
    LogLevel::INFO => [
        DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Command']['BatchTranslation']['writerConfiguration'] = $logWriterConfig;
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Utility']['LogUtility']['writerConfiguration'] = $logWriterConfig;
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Utility']['Translator']['writerConfiguration'] = $logWriterConfig;

// XCLASS: use site-specific API key for deepltranslate_core
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\WebVision\Deepltranslate\Core\Configuration::class] = [
    'className' => \ThieleUndKlose\Autotranslate\Xclass\Deepltranslate\Core\Configuration::class,
];

// Cache configurations
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['autotranslate'] ??= [
    'backend' => FileBackend::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['autotranslate_cache'] ??= [
    'backend' => FileBackend::class,
];

// Scheduler task with progress bar in Scheduler module
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][BatchTranslationTask::class] = [
    'extension' => 'autotranslate',
    'title' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:scheduler.task.title',
    'description' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:scheduler.task.description',
];
