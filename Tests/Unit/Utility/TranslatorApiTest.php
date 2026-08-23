<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use ThieleUndKlose\Autotranslate\ValueObject\TranslationResult;
use TYPO3\CMS\Core\DataHandling\DataHandler;

final class TranslatorApiTest extends TestCase
{
    public function testExistingTranslateMethodKeepsVoidReturnType(): void
    {
        $returnType = (new ReflectionMethod(Translator::class, 'translate'))->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('void', $returnType->getName());
    }

    public function testBatchTranslationEntryPointReturnsStructuredResult(): void
    {
        $returnType = (new ReflectionMethod(Translator::class, 'translateWithResult'))->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(TranslationResult::class, $returnType->getName());
    }

    public function testResultEntryPointInvokesLegacyTranslateOverride(): void
    {
        $translator = new class extends Translator {
            public bool $translateCalled = false;

            public function __construct()
            {
            }

            public function translate(
                string $table,
                int $recordUid,
                ?DataHandler $parentObject = null,
                ?string $languagesToTranslate = null,
                string $translateMode = self::TRANSLATE_MODE_BOTH,
                ?array $changedFields = null
            ): void {
                $this->translateCalled = true;
            }
        };

        $result = $translator->translateWithResult('tt_content', 1);

        self::assertTrue($translator->translateCalled);
        self::assertTrue($result->isAssumedSuccessful());
    }

    public function testExistingTranslateMethodDoesNotSwallowUnexpectedExceptions(): void
    {
        $translator = new class extends Translator {
            public function __construct()
            {
            }

            protected function performTranslation(
                string $table,
                int $recordUid,
                ?DataHandler $parentObject = null,
                ?string $languagesToTranslate = null,
                string $translateMode = self::TRANSLATE_MODE_BOTH,
                ?array $changedFields = null
            ): TranslationResult {
                throw new RuntimeException('Unexpected translation failure.');
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected translation failure.');

        $translator->translate('tt_content', 1);
    }
}
