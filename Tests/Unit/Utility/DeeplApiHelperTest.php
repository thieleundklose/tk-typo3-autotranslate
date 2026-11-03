<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Utility\DeeplApiHelper;

final class DeeplApiHelperTest extends TestCase
{
    public function testMaskApiKeyShowsOnlyTheLastFourCharacters(): void
    {
        self::assertSame(
            '********************ab12',
            DeeplApiHelper::maskApiKey('01234567-89ab-cdef-0123-456789abcdef:ab12')
        );
    }

    public function testMaskApiKeyHidesAShortKeyCompletely(): void
    {
        self::assertSame('********************', DeeplApiHelper::maskApiKey('12345678'));
    }

    public function testMaskApiKeyKeepsFourCharactersOfTheShortestLongerKey(): void
    {
        self::assertSame('********************6789', DeeplApiHelper::maskApiKey('123456789'));
    }

    public function testMaskApiKeyNeverContainsTheStartOfTheKey(): void
    {
        self::assertStringNotContainsString('0123', DeeplApiHelper::maskApiKey('01234567-89ab-cdef-0123-456789abcdef:ab12'));
    }
}
