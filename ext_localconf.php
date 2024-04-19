<?php
defined('TYPO3') || die();

(static function() {

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
})();

