<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Service;

use DeepL\TextResult;
use PHPUnit\Framework\TestCase;
use ThieleUndKlose\Autotranslate\Service\TranslationCacheService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Unit tests for TranslationCacheService serialization.
 *
 * Verifies that the serialize/unserialize roundtrip for DeepL TextResult
 * objects preserves all data. This is the regression test for issue #94:
 * unserializeTextResults() created anonymous classes instead of real
 * TextResult objects, causing serializeTextResults() to discard them
 * on re-serialization (instanceof TextResult check fails for anonymous classes).
 *
 * @see https://github.com/thieleundklose/tk-typo3-autotranslate/issues/94
 */
final class TranslationCacheServiceTest extends TestCase
{
    private TranslationCacheService $service;
    private \ReflectionClass $reflection;
    private \ReflectionMethod $serialize;
    private \ReflectionMethod $unserialize;

    protected function setUp(): void
    {
        $this->reflection = new \ReflectionClass(TranslationCacheService::class);
        $this->service = $this->reflection->newInstanceWithoutConstructor();

        $this->serialize = $this->reflection->getMethod('serializeTextResults');
        $this->serialize->setAccessible(true);

        $this->unserialize = $this->reflection->getMethod('unserializeTextResults');
        $this->unserialize->setAccessible(true);
    }

    /**
     * Verifies that a single serialize → unserialize roundtrip preserves
     * the translated text, detected source language, and billed characters.
     */
    public function testSingleRoundtripPreservesData(): void
    {
        $original = [
            new TextResult('Hallo Welt', 'de', 10, 'quality_optimized'),
            new TextResult('Bonjour le monde', 'fr', 16),
        ];

        $serialized = $this->serialize->invoke($this->service, $original);
        $unserialized = $this->unserialize->invoke($this->service, $serialized);

        self::assertSame('Hallo Welt', $unserialized[0]->text);
        self::assertSame('de', $unserialized[0]->detectedSourceLang);
        self::assertSame(10, $unserialized[0]->billedCharacters);
        self::assertSame('quality_optimized', $unserialized[0]->modelTypeUsed);

        self::assertSame('Bonjour le monde', $unserialized[1]->text);
        self::assertSame('fr', $unserialized[1]->detectedSourceLang);
        self::assertSame(16, $unserialized[1]->billedCharacters);
    }

    /**
     * Verifies that a double roundtrip (serialize → unserialize → serialize
     * → unserialize) preserves all data. This is the exact scenario that
     * triggered issue #94: partial cache hits are unserialized, then mixed
     * with fresh TextResult objects and re-serialized as a complete cache entry.
     *
     * Without the fix, the second serialize() converts the anonymous class
     * objects from the first unserialize() to null (instanceof TextResult fails).
     */
    public function testDoubleRoundtripPreservesData(): void
    {
        $original = [
            new TextResult('Hello World', 'en', 11),
            new TextResult('Bonjour', 'fr', 7),
        ];

        // First roundtrip
        $serialized1 = $this->serialize->invoke($this->service, $original);
        $unserialized1 = $this->unserialize->invoke($this->service, $serialized1);

        // Second roundtrip (this is where the bug manifests)
        $serialized2 = $this->serialize->invoke($this->service, $unserialized1);
        $unserialized2 = $this->unserialize->invoke($this->service, $serialized2);

        self::assertNotNull($serialized2[0], 'Re-serialized entry must not be null');
        self::assertNotNull($serialized2[1], 'Re-serialized entry must not be null');

        self::assertSame('Hello World', $serialized2[0]['text']);
        self::assertSame('Bonjour', $serialized2[1]['text']);

        self::assertSame('Hello World', $unserialized2[0]->text);
        self::assertSame('Bonjour', $unserialized2[1]->text);
    }

    /**
     * Verifies that null values in the result array are preserved through
     * the roundtrip. Null entries represent translation failures and must
     * remain null, not be converted to empty TextResult objects.
     */
    public function testNullValuesArePreservedThroughRoundtrip(): void
    {
        $original = [
            new TextResult('Hello', 'en', 5),
            null,
            new TextResult('World', 'en', 5),
        ];

        $serialized = $this->serialize->invoke($this->service, $original);
        $unserialized = $this->unserialize->invoke($this->service, $serialized);

        self::assertNotNull($unserialized[0]);
        self::assertSame('Hello', $unserialized[0]->text);
        self::assertNull($unserialized[1], 'Null entries must remain null');
        self::assertNotNull($unserialized[2]);
        self::assertSame('World', $unserialized[2]->text);
    }

    /**
     * Verifies that unserialized results are instances of DeepL\TextResult,
     * not anonymous classes. This ensures compatibility with instanceof
     * checks throughout the codebase, particularly in serializeTextResults().
     */
    public function testUnserializedResultsAreTextResultInstances(): void
    {
        $original = [new TextResult('Test', 'en', 4)];

        $serialized = $this->serialize->invoke($this->service, $original);
        $unserialized = $this->unserialize->invoke($this->service, $serialized);

        self::assertInstanceOf(TextResult::class, $unserialized[0]);
    }

    public function testMalformedCachedTranslationIsReturnedAsCacheMiss(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('has')->with('cache-key')->willReturn(true);
        $cache->method('get')->with('cache-key')->willReturn([
            0 => [
                'text' => 'Bonjour',
                'detected_source_lang' => '',
                'billed_characters' => 7,
            ],
        ]);

        $cacheProperty = $this->reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($this->service, $cache);

        self::assertNull($this->service->getCachedTranslation('cache-key'));
    }
}
