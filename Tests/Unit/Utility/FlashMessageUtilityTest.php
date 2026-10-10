<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Utility\FlashMessageUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;

final class FlashMessageUtilityTest extends TestCase
{
    public function testCliMessageDoesNotRequireBackendSession(): void
    {
        self::assertTrue(Environment::isCli());

        $previousBackendUser = $GLOBALS['BE_USER'] ?? null;
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();

        try {
            FlashMessageUtility::addMessage(
                'No translation was performed.',
                'Translation skipped',
                FlashMessageUtility::MESSAGE_NOTICE
            );
        } finally {
            $GLOBALS['BE_USER'] = $previousBackendUser;
        }
    }
}
