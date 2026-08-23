<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use ThieleUndKlose\Autotranslate\ValueObject\TranslationResult;

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
}
