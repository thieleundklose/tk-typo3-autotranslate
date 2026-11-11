<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Tests\Unit\Utility;

use DeepL\Translator;
use DeepL\Usage;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use ThieleUndKlose\Autotranslate\Utility\DeeplApiHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DeeplApiHelperTest extends TestCase
{
    private mixed $confVarsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->confVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->confVarsBackup;
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }

    public function testTheConfiguredTranslatorReplacesTheDeepLClient(): void
    {
        $replacement = new DeeplApiHelperTestTranslator();
        $this->configure(DeeplApiHelperTestTranslator::class, [DeeplApiHelperTestTranslator::class => $replacement]);

        self::assertSame($replacement, DeeplApiHelper::createTranslator('a-deepl-key'));
    }

    public function testALeadingBackslashIsAccepted(): void
    {
        $replacement = new DeeplApiHelperTestTranslator();
        $this->configure('\\' . DeeplApiHelperTestTranslator::class, [DeeplApiHelperTestTranslator::class => $replacement]);

        self::assertSame($replacement, DeeplApiHelper::createTranslator(''));
        self::assertTrue(DeeplApiHelper::hasConfiguredTranslator());
    }

    public function testWithoutTheSettingDeepLTranslates(): void
    {
        $this->configure('', []);

        $translator = DeeplApiHelper::createTranslator('a-deepl-key');

        self::assertSame(Translator::class, get_class($translator));
        self::assertFalse(DeeplApiHelper::hasConfiguredTranslator());
    }

    public function testTheConfiguredTranslatorNeedsNoApiKeyAndIsNotAskedForUsage(): void
    {
        $this->configure(DeeplApiHelperTestTranslator::class, [DeeplApiHelperTestTranslator::class => new DeeplApiHelperTestTranslator()]);

        $details = DeeplApiHelper::checkApiKey(null);

        self::assertTrue($details['isValid']);
        self::assertNull($details['error']);
        self::assertNull($details['usage']);
        self::assertNull($details['charactersLeft']);
    }

    public function testWithoutTheSettingAMissingApiKeyIsStillInvalid(): void
    {
        $this->configure('', []);

        $details = DeeplApiHelper::checkApiKey(null);

        self::assertFalse($details['isValid']);
    }

    public function testAClassThatIsNoDeepLTranslatorIsReportedAndNeverReplacedByDeepL(): void
    {
        $this->configure(\stdClass::class, []);

        $details = DeeplApiHelper::checkApiKey('a-deepl-key');

        self::assertFalse($details['isValid']);
        self::assertStringContainsString('stdClass', (string)$details['error']);
        self::assertTrue(DeeplApiHelper::hasConfiguredTranslator(), 'A broken setting must not fall back to DeepL');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1794326402);
        DeeplApiHelper::createTranslator('a-deepl-key');
    }

    public function testAServiceThatResolvesToSomethingElseIsRefused(): void
    {
        $this->configure(DeeplApiHelperTestTranslator::class, [DeeplApiHelperTestTranslator::class => new \stdClass()]);

        self::assertFalse(DeeplApiHelper::checkApiKey('a-deepl-key')['isValid']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1794326401);
        DeeplApiHelper::createTranslator('a-deepl-key');
    }

    public function testTheConfiguredTranslatorListsTheLanguages(): void
    {
        $this->configure(DeeplApiHelperTestTranslator::class, [DeeplApiHelperTestTranslator::class => new DeeplApiHelperTestTranslator()]);

        self::assertSame([['German', 'DE']], DeeplApiHelper::getCachedLanguages('', 'target'));
    }

    /**
     * @param array<string, object> $services
     */
    private function configure(string $translatorClass, array $services): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['autotranslate']['translatorClass'] = $translatorClass;
        GeneralUtility::setContainer(new class ($services) implements ContainerInterface {
            /**
             * @param array<string, object> $services
             */
            public function __construct(private readonly array $services)
            {
            }

            public function get(string $id): object
            {
                return $this->services[$id] ?? throw new \RuntimeException('No service ' . $id);
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        });
    }
}

/**
 * A replacement translator as an integrating extension would ship one. Every
 * method that would reach DeepL fails the test.
 */
final class DeeplApiHelperTestTranslator extends Translator
{
    public function __construct()
    {
        parent::__construct('test-placeholder');
    }

    public function getUsage(): Usage
    {
        throw new \LogicException('The usage of a configured translator must not be asked for.');
    }

    public function getTargetLanguages(): array
    {
        return [new \DeepL\Language('German', 'DE', true)];
    }
}
