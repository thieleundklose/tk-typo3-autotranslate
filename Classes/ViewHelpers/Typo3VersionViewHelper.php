<?php
namespace ThieleUndKlose\Autotranslate\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class Typo3VersionViewHelper extends AbstractViewHelper
{
    /**
     * @return int
     */
    public function render(): int
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
        return $typo3Version->getMajorVersion();
    }
}
