<?php
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
 
defined('TYPO3') or die();
 
(function () {
    ExtensionManagementUtility::allowTableOnStandardPages('tx_autotranslate_batch_items');
})();
