<?php

use ThieleUndKlose\Autotranslate\Xclass\Deepltranslate\Core\Configuration as ConfigurationXclass;
use WebVision\Deepltranslate\Core\Configuration;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['autotranslate'] =
    \ThieleUndKlose\Autotranslate\Hooks\DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['autotranslate'] =
    \ThieleUndKlose\Autotranslate\Hooks\DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['autotranslate'] =
    \ThieleUndKlose\Autotranslate\Hooks\DataHandler::class;
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Command']['BatchTranslation']['writerConfiguration'] = [
    \Psr\Log\LogLevel::INFO => [
        \TYPO3\CMS\Core\Log\Writer\DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Utility']['LogUtility']['writerConfiguration'] = [
    \Psr\Log\LogLevel::INFO => [
        \TYPO3\CMS\Core\Log\Writer\DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];
$GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Utility']['Translator']['writerConfiguration'] = [
    \Psr\Log\LogLevel::INFO => [
        \TYPO3\CMS\Core\Log\Writer\DatabaseWriter::class => [
            'logTable' => 'tx_autotranslate_log',
        ],
    ],
];

// xlass optional 3rd party extension to use apiKey dependent on site config
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Configuration::class] = [
    'className' => ConfigurationXclass::class,
];
