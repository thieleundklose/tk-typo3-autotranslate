<?php
defined('TYPO3') || die();

(static function() {

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['autotranslate'] =
        \ThieleUndKlose\Autotranslate\Hooks\DataHandler::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['autotranslate'] =
        \ThieleUndKlose\Autotranslate\Hooks\DataHandler::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['autotranslate'] = 
        \ThieleUndKlose\Autotranslate\Hooks\DataHandler::class;
    // $GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Command']['BatchTranslation'] = [
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'] = [
        \Psr\Log\LogLevel::INFO => [
            \TYPO3\CMS\Core\Log\Writer\SyslogWriter::class => []
            // \TYPO3\CMS\Core\Log\Writer\DatabaseWriter::class => [
            //     'logTable' => 'sys_log',
            // ],
        ],
    ];
})();

